<?php
/*
 * vote.php
 * Main voting flow for authenticated users. Responsibilities:
 * - Load categories and contestants grouped by gender
 * - Enforce voting window and per-user voting flag
 * - Validate submitted scores and persist votes
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
$config = require __DIR__ . '/config/config.php';
if (isset($pdo)) {
    // Allow runtime overrides stored in DB (e.g., voting_open)
    $config = apply_app_settings($config, $pdo);
}

$pageTitle = 'Vote - UMU Varsity Ball';
require_once __DIR__ . '/includes/header.php';

// Check if user is admin
$isAdmin = is_logged_in() && is_admin($config);

// Load user voting flag and sanitize user ID
$userId = (int) $_SESSION['user_id'];
$userStmt = $pdo->prepare('SELECT has_voted FROM users WHERE id = ?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
$hasVoted = $user ? (int) $user['has_voted'] : 0;

// If has_voted is true but there are no votes (edge case), reset the flag
$voteCountStmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE user_id = ?');
$voteCountStmt->execute([$userId]);
$voteCount = (int) $voteCountStmt->fetchColumn();
if ($hasVoted === 1 && $voteCount === 0) {
    $hasVoted = 0;
    $updateFlag = $pdo->prepare('UPDATE users SET has_voted = 0 WHERE id = ?');
    $updateFlag->execute([$userId]);
}

// Check configured voting window and open flag (single shared
// implementation — see includes/helpers.php::voting_status_message()).
$votingStatus = voting_status_message($config);
$votingOpen = $votingStatus['open'];
$votingStatusMessage = $votingStatus['open'] ? '' : $votingStatus['message'];
$votingMode = get_voting_mode($config);
if (isset($pdo)) {
    ensure_votes_mode_column($pdo);
    ensure_active_column($pdo, 'categories');
    ensure_active_column($pdo, 'contestants');
}

// Load categories and contestants (limited by config). Only active ones —
// an archived category/contestant (see admin/categories.php,
// admin/contestants.php) must not be offered on new ballots, even though
// their historical votes still count correctly in results.
$limit = (int) ($config['app']['category_limit'] ?? 10);
$categoriesStmt = $pdo->prepare('SELECT id, name, gender FROM categories WHERE active = 1 ORDER BY id LIMIT ?');
$categoriesStmt->bindValue(1, $limit, PDO::PARAM_INT);
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll();

$contestants = $pdo->query('SELECT id, name, gender, photo, bio FROM contestants WHERE active = 1 ORDER BY gender, name')->fetchAll();

// Prepare categories steps (each category appears once). Categories with gender 'all' remain a single category.
// The UI will display both male and female contestants for each category as intended.
$categorySteps = $categories;

$contestantsByGender = ['male' => [], 'female' => []];
foreach ($contestants as $contestant) {
    $genderKey = $contestant['gender'] ?? 'male';
    if (!isset($contestantsByGender[$genderKey])) {
        $contestantsByGender[$genderKey] = [];
    }
    $contestantsByGender[$genderKey][] = $contestant;
}

$contestantSections = [
    ['key' => 'male', 'label' => 'Mr UMU Rubaga'],
    ['key' => 'female', 'label' => 'Mrs UMU Rubaga'],
];

$errors = [];
$success = false;
$submittedScores = [];
$submittedChoices = [];

// Given a category, return which gender group(s) of contestants apply to it.
function categoryContestantGroups(array $category): array
{
    $categoryGender = $category['gender'] ?? 'all';
    return $categoryGender === 'all' ? ['male', 'female'] : [$categoryGender];
}

// Handle form submit: validate the ballot and persist votes. Both modes
// share the same gating (not voted yet, voting currently open, CSRF valid)
// and the same "insert already happened" race handling; only the shape of
// what gets validated/inserted differs.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasVoted && $votingOpen) {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired or the form was resubmitted. Please reload the page and try again.';
    } elseif (!rate_limit_allow($pdo, rate_limit_client_bucket('vote_submit'), 8, 60)) {
        // Generous limit (8/min) — meant to catch scripted flooding, not
        // ordinary double-clicks. Bucketed per authenticated user id, not
        // IP, since many voters share campus/hostel WiFi NAT.
        $errors[] = 'Too many attempts. Please wait a moment and try again.';
    }

    // RE-VALIDATE voting time at submission to prevent race conditions
    // (uses the same shared window check as the page-load gate above).
    if (!$errors && !voting_status_message($config)['open']) {
        $errors[] = 'Voting window has closed. Your submission could not be processed.';
    } elseif (!$errors && (!$categories || !$contestants)) {
        $errors[] = 'Voting is not ready. Please check back later.';
    }

    if (!$errors && $votingMode === 'simple') {
        // SIMPLE MODE: one chosen contestant per category, per gender
        // group. For a gender-specific category that's one pick; for an
        // 'all' category (e.g. "Best Dressed") that's one male pick AND
        // one female pick — mirroring rating mode, where every contestant
        // of both genders gets rated independently in an 'all' category.
        $choices = [];
        foreach ($categories as $category) {
            foreach (categoryContestantGroups($category) as $groupKey) {
                $eligibleIds = array_map(
                    fn($c) => (int) $c['id'],
                    $contestantsByGender[$groupKey]
                );
                if (!$eligibleIds) {
                    continue;
                }

                $chosenId = $_POST['choices'][$category['id']][$groupKey] ?? null;
                if ($chosenId === null || !is_numeric($chosenId) || !in_array((int) $chosenId, $eligibleIds, true)) {
                    $errors[] = 'Please choose one contestant per gender in every category.';
                    break 2;
                }
                $choices[$category['id']][$groupKey] = (int) $chosenId;
            }
        }

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                // Lock this voter's row for the rest of the transaction so a
                // second concurrent submission (double-click, two tabs, a
                // network retry) blocks here instead of racing ahead —
                // without this, two concurrent simple-mode requests could
                // each pick a *different* contestant in the same category
                // and both succeed, since they don't collide on the votes
                // unique key (that key is per contestant, not per category).
                $lock = $pdo->prepare('SELECT has_voted FROM users WHERE id = ? FOR UPDATE');
                $lock->execute([$userId]);
                $lockedRow = $lock->fetch();

                if ($lockedRow && (int) $lockedRow['has_voted'] === 1) {
                    // Another request (already committed) beat us to it.
                    $pdo->commit();
                    $_SESSION['has_voted'] = 1;
                    $hasVoted = 1;
                    $success = true;
                } else {
                    $insert = $pdo->prepare('INSERT INTO votes (user_id, contestant_id, category_id, score, mode) VALUES (?, ?, ?, 1, ?)');
                    foreach ($choices as $categoryId => $genderChoices) {
                        foreach ($genderChoices as $contestantId) {
                            $insert->execute([$userId, $contestantId, $categoryId, $votingMode]);
                        }
                    }
                    $update = $pdo->prepare('UPDATE users SET has_voted = 1 WHERE id = ?');
                    $update->execute([$userId]);
                    $pdo->commit();

                    $_SESSION['has_voted'] = 1;
                    $hasVoted = 1;
                    $success = true;
                    $submittedChoices = $choices;
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                // Unique key (user_id, contestant_id, category_id) means this
                // is almost certainly a double-submit race, not real data
                // loss — the earlier request already recorded the vote.
                $update = $pdo->prepare('UPDATE users SET has_voted = 1 WHERE id = ?');
                $update->execute([$userId]);
                $_SESSION['has_voted'] = 1;
                $hasVoted = 1;
                $success = true;
            }
        }
    } elseif (!$errors) {
        // RATING MODE: score 1-5 for every contestant in every category.
        foreach ($categories as $category) {
            foreach (categoryContestantGroups($category) as $groupKey) {
                foreach ($contestantsByGender[$groupKey] as $contestant) {
                    $score = $_POST['scores'][$category['id']][$contestant['id']] ?? null;
                    if ($score === null || !is_numeric($score)) {
                        $errors[] = 'Please rate every contestant in all categories.';
                        break 3;
                    }

                    $scoreValue = (int) $score;
                    if ($scoreValue < 1 || $scoreValue > 5) {
                        $errors[] = 'Scores must be between 1 and 5.';
                        break 3;
                    }
                }
            }
        }

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                // Same per-voter row lock as simple mode above — closes the
                // race where two concurrent requests both pass the earlier
                // has_voted check and both insert a full ballot.
                $lock = $pdo->prepare('SELECT has_voted FROM users WHERE id = ? FOR UPDATE');
                $lock->execute([$userId]);
                $lockedRow = $lock->fetch();

                if ($lockedRow && (int) $lockedRow['has_voted'] === 1) {
                    $pdo->commit();
                    $_SESSION['has_voted'] = 1;
                    $hasVoted = 1;
                    $success = true;
                } else {
                    $insert = $pdo->prepare('INSERT INTO votes (user_id, contestant_id, category_id, score, mode) VALUES (?, ?, ?, ?, ?)');
                    foreach ($categories as $category) {
                        foreach (categoryContestantGroups($category) as $groupKey) {
                            foreach ($contestantsByGender[$groupKey] as $contestant) {
                                $scoreValue = (int) $_POST['scores'][$category['id']][$contestant['id']];
                                $insert->execute([$userId, $contestant['id'], $category['id'], $scoreValue, $votingMode]);
                            }
                        }
                    }

                    $update = $pdo->prepare('UPDATE users SET has_voted = 1 WHERE id = ?');
                    $update->execute([$userId]);
                    $pdo->commit();

                    $_SESSION['has_voted'] = 1;
                    $hasVoted = 1;
                    $success = true;
                    $submittedScores = $_POST['scores'];
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                // Same double-submit race as above.
                $update = $pdo->prepare('UPDATE users SET has_voted = 1 WHERE id = ?');
                $update->execute([$userId]);
                $_SESSION['has_voted'] = 1;
                $hasVoted = 1;
                $success = true;
            }
        }
    }
}

// If user has voted, load their submitted scores for display
if ($hasVoted && !$submittedScores) {
    $votesStmt = $pdo->prepare(
        'SELECT v.category_id, v.contestant_id, v.score, c.name AS category_name, con.name AS contestant_name
         FROM votes v
         JOIN categories c ON c.id = v.category_id
         JOIN contestants con ON con.id = v.contestant_id
         WHERE v.user_id = ?
         ORDER BY c.gender, c.id, con.name'
    );
    $votesStmt->execute([$userId]);
    foreach ($votesStmt->fetchAll() as $row) {
        $submittedScores[$row['category_id']][$row['contestant_id']] = [
            'score' => (int) $row['score'],
            'category_name' => $row['category_name'],
            'contestant_name' => $row['contestant_name'],
        ];
    }
}

$totalRatings = 0;
foreach ($categories as $category) {
    $totalRatings += count($contestants);
}

// Load current winners and leaders for display after voting. Previously
// this whole block was admin-only, hard-coded to AVG(score), and
// duplicated the same query results.php also runs separately. Now it
// (a) uses the shared, mode-aware get_leaderboard() helper, and
// (b) is shown to any voter once results are public, not admins only —
// matching the visibility rule results.php already uses.
$categoryLeaders = [];
$overallWinners = ['female' => null, 'male' => null];
$adminTotals = ['total_votes' => 0, 'total_voters' => 0];
$resultsPublic = (bool) ($config['app']['results_public'] ?? false);
$showWinners = $hasVoted && ($isAdmin || $resultsPublic);

if ($showWinners) {
    if ($isAdmin) {
        $adminTotalsStmt = $pdo->query(
            'SELECT COUNT(*) AS total_votes, COUNT(DISTINCT user_id) AS total_voters
             FROM votes'
        );
        $adminTotalsRow = $adminTotalsStmt->fetch();
        $adminTotals['total_votes'] = (int) ($adminTotalsRow['total_votes'] ?? 0);
        $adminTotals['total_voters'] = (int) ($adminTotalsRow['total_voters'] ?? 0);
    }

    $board = get_leaderboard($pdo, $votingMode);
    $categoryLeaders = $board['category_leaders'];
    $overallWinners = $board['overall_winners'];
}
?>
<?php // format_leaderboard_metric($row, $votingMode) renders "Avg 4.20/5" or "12 votes" depending on the active mode. ?>
<section class="py-5">
    <div class="container">
        <?php
        // Expose voting start/end timestamps (UTC) and admin enabled flag for client-side timers
        $clientVoting = voting_timestamps_for_client($config);
        ?>
        <div id="votingMeta" data-start="<?php echo h($clientVoting['start'] ?? ''); ?>" data-end="<?php echo h($clientVoting['end'] ?? ''); ?>" data-enabled="<?php echo (int) ($clientVoting['enabled'] ?? 0); ?>" data-base="<?php echo h($baseUrl); ?>" style="display:none"></div>
        <div class="section-title">
            <span>Voting</span>
            <h2 class="mb-0"><?php echo $votingMode === 'simple' ? 'Vote for your favourite' : 'Rate each contestant'; ?></h2>
        </div>

        <?php if (!$votingOpen): ?>
            <div class="alert alert-warning">
                <?php echo h($votingStatusMessage !== '' ? $votingStatusMessage : 'Voting is currently closed. Please check back later.'); ?>
            </div>
        <?php elseif ($hasVoted): ?>
            <div class="alert alert-success">You have already voted.</div>
        <?php elseif ($success): ?>
            <div class="alert alert-success">Your vote has been submitted successfully.</div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo h($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$categories || !$contestants): ?>
            <div class="alert alert-warning">No categories or contestants have been added yet.</div>
        <?php elseif (!$hasVoted && $votingOpen && $votingMode === 'rating'): ?>
            <!-- Category Progress Tracker -->
            <div class="category-progress-section mb-4">
                <div class="progress-header mb-3">
                    <h4 class="mb-1">Voting Progress</h4>
                    <p class="text-muted mb-0">
                        <span id="categoryCounter">0</span> of <span id="totalCategories"><?php echo (int) count($categories); ?></span> categories completed
                    </p>
                </div>
                
                <div class="category-progress-bar" id="categoryProgressContainer">
                    <?php foreach ($categories as $index => $category): ?>
                        <div class="progress-step" 
                             data-step="<?php echo (int) $index; ?>" 
                             data-category-id="<?php echo (int) $category['id']; ?>"
                             data-category-name="<?php echo h($category['name']); ?>">
                            <div class="step-circle">
                                <span class="step-number"><?php echo (int) $index + 1; ?></span>
                                <svg class="step-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <path d="M20 6L9 17l-5-5"></path>
                                </svg>
                            </div>
                            <div class="step-label">
                                <small class="step-category-name"><?php echo h($category['name']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Overall Progress Indicator -->
            <div class="card-dark p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4 class="mb-1">Complete all ratings</h4>
                        <p class="text-muted mb-0">You will submit <?php echo (int) $totalRatings; ?> total ratings.</p>
                    </div>
                    <div class="progress-pill" style="width: 200px;">
                        <span id="voteProgressBar" style="width: 0%"></span>
                    </div>
                    <small id="voteProgressText" class="text-muted">0% completed</small>
                </div>
            </div>
                    <?php
                    $stepTotal = count($categorySteps);
                    ?>
                    <form method="post" id="voteForm" class="vote-form" data-total="<?php echo (int) $totalRatings; ?>" data-steps="<?php echo (int) $stepTotal; ?>" data-categories="<?php echo htmlspecialchars(json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $categories)), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo csrf_field(); ?>
                        <?php foreach ($categorySteps as $index => $category): ?>
                            <div class="vote-step mb-5" data-step="<?php echo (int) $index; ?>" data-category-id="<?php echo (int) $category['id']; ?>" data-gender="<?php echo h($category['gender']); ?>" data-category="<?php echo h($category['name']); ?>" style="<?php echo $index === 0 ? '' : 'display:none;'; ?>">
                                <div class="vote-group mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div>
                                            <h3 class="mb-2"><?php echo h($category['name']); ?></h3>
                                            <small class="text-muted text-uppercase">
                                                <?php 
                                                $categoryGender = $category['gender'] ?? 'all';
                                                if ($categoryGender === 'all') {
                                                    echo 'Rate both male &amp; female';
                                                } elseif ($categoryGender === 'male') {
                                                    echo 'Rate Mr UMU Rubaga';
                                                } else {
                                                    echo 'Rate Mrs UMU Rubaga';
                                                }
                                                ?>
                                            </small>
                                        </div>
                                        <span class="badge badge-gold">Rate 1 - 5</span>
                                    </div>

                                    <div class="row g-4">
                                        <?php foreach ($contestantSections as $section): ?>
                                            <?php 
                                            $categoryGender = $category['gender'] ?? 'all';
                                            $shouldShow = ($categoryGender === 'all' || $categoryGender === $section['key']);
                                            if (!$shouldShow) continue;
                                            ?>
                                            <div class="col-12 col-md-6">
                                                <div class="gender-section-header mb-3">
                                                    <h5 class="mb-0"><?php echo h($section['label']); ?></h5>
                                                    <small class="text-muted text-uppercase"><?php echo h(ucfirst($section['key'])); ?></small>
                                                </div>
                                                <div class="contestants-list">
                                                    <div class="row g-3">
                                                        <?php foreach ($contestantsByGender[$section['key']] as $contestant): ?>
                                                            <div class="col-12">
                                                                <div class="card-dark contestant-card <?php echo $section['key'] === 'male' ? 'contestant-card-male' : 'contestant-card-female'; ?> p-3 h-100">
                                                                    <div class="d-flex gap-3">
                                                                        <img class="contestant-img" src="<?php echo h(asset_url($contestant['photo'], $config)); ?>" alt="<?php echo h($contestant['name']); ?>">
                                                                        <div>
                                                                            <h5 class="mb-1"><?php echo h($contestant['name']); ?></h5>
                                                                            <?php if (!empty($contestant['bio'])): ?>
                                                                                <small class="text-muted"><?php echo h($contestant['bio']); ?></small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="star-rating mt-3" role="radiogroup" aria-label="Rate <?php echo h($contestant['name']); ?>">
                                                                        <?php for ($star = 5; $star >= 1; $star--): ?>
                                                                            <?php $inputId = 'star_' . $category['id'] . '_' . $contestant['id'] . '_' . $star; ?>
                                                                            <input
                                                                                type="radio"
                                                                                class="star-input"
                                                                                id="<?php echo h($inputId); ?>"
                                                                                name="scores[<?php echo (int) $category['id']; ?>][<?php echo (int) $contestant['id']; ?>]"
                                                                                value="<?php echo $star; ?>"
                                                                                <?php echo $star === 5 ? 'required' : ''; ?>
                                                                            >
                                                                            <label for="<?php echo h($inputId); ?>" title="<?php echo $star; ?> stars">&#9733;</label>
                                                                        <?php endfor; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="vote-sticky-bar card-dark p-3" id="voteStepper">
                            <div class="vote-stepper-inner d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <h4 class="mb-1" id="currentCategoryTitle">Category 1</h4>
                                    <small class="text-muted" id="currentCategoryMeta"></small>
                                </div>
                                <div class="vote-stepper-actions d-flex gap-2 flex-wrap">
                                    <button class="btn btn-outline-light" type="button" id="prevCategoryBtn">Previous</button>
                                    <button class="btn btn-outline-light" type="button" id="nextCategoryBtn">Next category</button>
                                    <button class="btn btn-primary" type="submit" id="submitVoteBtn">Submit Vote</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="step-toast" id="stepToast" role="status" aria-live="polite"></div>
        <?php elseif (!$hasVoted && $votingOpen && $votingMode === 'simple'): ?>
            <!-- Simple one-click ballot: one contestant per category, one submit. -->
            <form method="post" id="simpleVoteForm" class="vote-form-simple">
                <?php echo csrf_field(); ?>
                <?php foreach ($categories as $category): ?>
                    <?php $categoryGender = $category['gender'] ?? 'all'; ?>
                    <div class="card-dark p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="mb-0"><?php echo h($category['name']); ?></h3>
                            <?php if ($categoryGender !== 'all'): ?>
                                <span class="badge badge-gold"><?php echo $categoryGender === 'male' ? 'Mr UMU Rubaga' : 'Mrs UMU Rubaga'; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="row g-4">
                            <?php foreach ($contestantSections as $section): ?>
                                <?php
                                $shouldShow = ($categoryGender === 'all' || $categoryGender === $section['key']);
                                if (!$shouldShow) continue;
                                ?>
                                <div class="col-12 col-md-6">
                                    <?php if ($categoryGender === 'all'): ?>
                                        <div class="gender-section-header mb-3">
                                            <h5 class="mb-0"><?php echo h($section['label']); ?></h5>
                                        </div>
                                    <?php endif; ?>
                                    <div class="row g-3">
                                        <?php foreach ($contestantsByGender[$section['key']] as $contestant): ?>
                                            <?php $choiceId = 'choice_' . $category['id'] . '_' . $contestant['id']; ?>
                                            <div class="col-12">
                                                <label class="card-dark contestant-card <?php echo $section['key'] === 'male' ? 'contestant-card-male' : 'contestant-card-female'; ?> p-3 h-100 d-flex gap-3 align-items-center choice-card" for="<?php echo h($choiceId); ?>" style="cursor:pointer;">
                                                    <input
                                                        type="radio"
                                                        class="choice-input"
                                                        id="<?php echo h($choiceId); ?>"
                                                        name="choices[<?php echo (int) $category['id']; ?>][<?php echo h($section['key']); ?>]"
                                                        value="<?php echo (int) $contestant['id']; ?>"
                                                        required
                                                        style="width: 20px; height: 20px; flex-shrink: 0;"
                                                    >
                                                    <img class="contestant-img" src="<?php echo h(asset_url($contestant['photo'], $config)); ?>" alt="<?php echo h($contestant['name']); ?>">
                                                    <div>
                                                        <h5 class="mb-1"><?php echo h($contestant['name']); ?></h5>
                                                        <?php if (!empty($contestant['bio'])): ?>
                                                            <small class="text-muted"><?php echo h($contestant['bio']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="vote-sticky-bar card-dark p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <small class="text-muted">Pick one contestant in every category, then submit once.</small>
                        <button class="btn btn-primary btn-lg" type="submit">Vote Now</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($showWinners): ?>
            <!-- Live Winners Section - visible to admins always, and to any
                 voter once results are made public (previously admin-only,
                 leaving regular voters with no winner announcement). -->
            <div class="card-dark p-4 mt-4 mb-4">
                <div class="section-title mb-4">
                    <span>Live Results</span>
                    <h3 class="mb-0">Current Winners</h3>
                </div>
                <div class="alert alert-info mb-4">
                    <strong>Live leaderboard:</strong> These are the current winners based on all votes cast so far. This updates in real-time as more people vote.
                </div>

                <?php if ($isAdmin): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card-dark p-3 h-100">
                            <div class="text-uppercase text-muted small mb-1">Total vote entries</div>
                            <div class="h4 mb-0"><?php echo number_format($adminTotals['total_votes']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card-dark p-3 h-100">
                            <div class="text-uppercase text-muted small mb-1">Total voters</div>
                            <div class="h4 mb-0"><?php echo number_format($adminTotals['total_voters']); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Overall Winners: females first, then males -->
                <div class="row g-4 mb-5">
                    <?php foreach (['female' => 'Mrs UMU Rubaga', 'male' => 'Mr UMU Rubaga'] as $gender => $title): ?>
                        <?php $winner = $overallWinners[$gender]; ?>
                        <div class="col-md-6">
                            <div class="card-dark p-4 h-100" style="background: linear-gradient(135deg, rgba(255,193,7,.1) 0%, rgba(255,152,0,.1) 100%);">
                                <div class="text-uppercase text-muted small mb-2">Overall Winner</div>
                                <h5 class="mb-3"><?php echo h($title); ?></h5>
                                <?php if ($winner): ?>
                                    <div class="d-flex gap-3 align-items-center">
                                        <img class="contestant-img" style="width: 100px; height: 100px; border-radius: 50%;" src="<?php echo h(asset_url($winner['photo'], $config)); ?>" alt="<?php echo h($winner['contestant_name']); ?>">
                                        <div>
                                            <h6 class="mb-1"><i class="bi bi-trophy-fill text-warning"></i> <?php echo h($winner['contestant_name']); ?></h6>
                                            <small class="text-muted"><?php echo h(format_leaderboard_metric($winner, $votingMode)); ?></small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No votes yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Category Winners -->
                <h5 class="mb-3">Per Category Winners</h5>
                <div class="row g-3 mb-4">
                    <?php foreach ($categoryLeaders as $leader): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card-dark p-3 h-100">
                                <small class="text-muted text-uppercase d-block mb-2">
                                    <?php echo h($leader['category_name']); ?>
                                    <?php if ($leader['gender'] === 'all'): ?>
                                        <span style="display: inline-block; margin-left: 4px; font-size: 0.75rem;">
                                            (<?php echo ucfirst($leader['contestant_gender'] ?? 'male'); ?>)
                                        </span>
                                    <?php endif; ?>
                                </small>
                                <div class="d-flex gap-3 align-items-center">
                                    <img class="contestant-img" style="width: 80px; height: 80px;" src="<?php echo h(asset_url($leader['photo'], $config)); ?>" alt="<?php echo h($leader['contestant_name']); ?>">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold small"><?php echo h($leader['contestant_name']); ?></div>
                                        <small class="text-muted"><?php echo h(format_leaderboard_metric($leader, $votingMode)); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Final statement + certificate download, matching results.php -->
                <div class="border-top pt-4">
                    <h5 class="mb-2">Final statement</h5>
                    <?php
                    $femaleWinnerName = $overallWinners['female']['contestant_name'] ?? 'TBD';
                    $maleWinnerName = $overallWinners['male']['contestant_name'] ?? 'TBD';
                    ?>
                    <p class="text-muted mb-3">
                        Congratulations to Mrs UMU Rubaga: <?php echo h($femaleWinnerName); ?>, and Mr UMU Rubaga: <?php echo h($maleWinnerName); ?>.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-light" href="certificate.php?gender=female">Download Mrs certificate</a>
                        <a class="btn btn-outline-light" href="certificate.php?gender=male">Download Mr certificate</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasVoted && $votingMode === 'simple' && $submittedChoices): ?>
            <div class="card-dark p-4 mt-4">
                <div class="section-title mb-3">
                    <span>Your Vote</span>
                    <h3 class="mb-0">Your submitted choices</h3>
                </div>
                <div class="alert alert-secondary mb-3">
                    <strong>Your ballot:</strong> here is who you chose in every category.
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Your choice</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <?php
                                $genderChoices = $submittedChoices[$category['id']] ?? [];
                                $chosenNames = [];
                                foreach ($genderChoices as $chosenId) {
                                    foreach ($contestants as $contestant) {
                                        if ((int) $contestant['id'] === (int) $chosenId) {
                                            $chosenNames[] = $contestant['name'];
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo h($category['name']); ?></td>
                                    <td><?php echo h($chosenNames ? implode(' & ', $chosenNames) : 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasVoted && $votingMode === 'rating' && $submittedScores): ?>
            <div class="card-dark p-4 mt-4">
                <div class="section-title mb-3">
                    <span>Your Votes</span>
                    <h3 class="mb-0">Your submitted ratings</h3>
                </div>
                <div class="alert alert-secondary mb-3">
                    <strong>Your personal selections:</strong> Below are the exact ratings you gave to each contestant. Your votes are combined with everyone else's to calculate the winners.
                </div>
                <?php foreach ($categories as $category): ?>
                    <div class="mb-3">
                        <h5 class="mb-2"><?php echo h($category['name']); ?></h5>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Contestant</th>
                                        <th>Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contestants as $contestant): ?>
                                        <?php
                                        // Only show contestants that match the category gender
                                        $categoryGender = $category['gender'] ?? 'all';
                                        $contestantGender = $contestant['gender'] ?? 'male';
                                        $shouldDisplay = ($categoryGender === 'all' || $categoryGender === $contestantGender);
                                        if (!$shouldDisplay) continue;
                                        
                                        $cell = $submittedScores[$category['id']][$contestant['id']] ?? null;
                                        $scoreValue = is_array($cell) ? $cell['score'] : (int) $cell;
                                        ?>
                                        <tr>
                                            <td><?php echo h($contestant['name']); ?></td>
                                            <td><?php echo (int) $scoreValue; ?>/5</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="alert alert-info mb-0">
                    <strong>How votes are counted:</strong> Each category uses average scores, and the overall winners are the highest combined average.
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
