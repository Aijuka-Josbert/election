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

// Check configured voting window and open flag
$votingOpen = (bool) ($config['app']['voting_open'] ?? true);
$start = $config['app']['voting_start'] ?? '';
$end = $config['app']['voting_end'] ?? '';
$tzName = $config['app']['timezone'] ?? 'UTC';
$tz = new DateTimeZone($tzName);
$now = new DateTime('now', $tz);
$startTime = $start ? new DateTime($start, $tz) : null;
$endTime = $end ? new DateTime($end, $tz) : null;

if ($startTime && $endTime && $endTime <= $startTime) {
    $endTime = (clone $endTime)->modify('+1 day');
}
$votingStatusMessage = '';

if (!$votingOpen) {
    $votingStatusMessage = 'Voting is disabled by the admin.';
}

if ($votingOpen && ($startTime || $endTime)) {
    // Apply start/end window if present
    if ($startTime && $endTime) {
        $votingOpen = $now >= $startTime && $now <= $endTime;
    } elseif ($startTime) {
        $votingOpen = $now >= $startTime;
    } elseif ($endTime) {
        $votingOpen = $now <= $endTime;
    }

    if (!$votingOpen) {
        if ($startTime && $now < $startTime) {
            $votingStatusMessage = 'Voting opens on ' . $startTime->format('M d, Y H:i') . ' (' . $tzName . ').';
        } elseif ($endTime && $now > $endTime) {
            $votingStatusMessage = 'Voting closed on ' . $endTime->format('M d, Y H:i') . ' (' . $tzName . ').';
        }
    }
}

// Load categories and contestants (limited by config)
$limit = (int) ($config['app']['category_limit'] ?? 10);
$categoriesStmt = $pdo->prepare('SELECT id, name, gender FROM categories ORDER BY id LIMIT ?');
$categoriesStmt->bindValue(1, $limit, PDO::PARAM_INT);
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll();

$contestants = $pdo->query('SELECT id, name, gender, photo, bio FROM contestants ORDER BY gender, name')->fetchAll();

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

// Handle form submit: validate every required score and persist votes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasVoted && $votingOpen) {
    // RE-VALIDATE voting time at submission to prevent race conditions
    $nowSubmit = new DateTime('now', $tz);
    $votingOpenAtSubmit = true;
    if ($startTime && $endTime) {
        $votingOpenAtSubmit = $nowSubmit >= $startTime && $nowSubmit <= $endTime;
    } elseif ($startTime) {
        $votingOpenAtSubmit = $nowSubmit >= $startTime;
    } elseif ($endTime) {
        $votingOpenAtSubmit = $nowSubmit <= $endTime;
    }
    
    if (!$votingOpenAtSubmit) {
        $errors[] = 'Voting window has closed. Your submission could not be processed.';
    } elseif (!$categories || !$contestants) {
        $errors[] = 'Voting is not ready. Please check back later.';
    } else {
        foreach ($categories as $category) {
            $categoryGender = $category['gender'] ?? 'all';
            // Determine which contestant groups to validate based on category gender
            $groupsToValidate = [];
            if ($categoryGender === 'all') {
                $groupsToValidate = ['male', 'female'];
            } else {
                $groupsToValidate = [$categoryGender];
            }
            
            foreach ($groupsToValidate as $groupKey) {
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
    }

    if (!$errors) {
        // Persist votes atomically and mark the user as having voted
        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO votes (user_id, contestant_id, category_id, score) VALUES (?, ?, ?, ?)');

        foreach ($categories as $category) {
            $categoryGender = $category['gender'] ?? 'all';
            // Determine which contestant groups to insert votes for based on category gender
            $groupsToInsert = [];
            if ($categoryGender === 'all') {
                $groupsToInsert = ['male', 'female'];
            } else {
                $groupsToInsert = [$categoryGender];
            }
            
            foreach ($groupsToInsert as $groupKey) {
                foreach ($contestantsByGender[$groupKey] as $contestant) {
                    $scoreValue = (int) $_POST['scores'][$category['id']][$contestant['id']];
                    $insert->execute([$userId, $contestant['id'], $category['id'], $scoreValue]);
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
?>
<section class="py-5">
    <div class="container">
        <div class="section-title">
            <span>Voting</span>
            <h2 class="mb-0">Rate each contestant</h2>
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
        <?php elseif (!$hasVoted && $votingOpen): ?>
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
                                                                <div class="card-dark p-3 h-100">
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
        <?php endif; ?>

        <?php if ($hasVoted && $submittedScores): ?>
            <div class="card-dark p-4 mt-4">
                <h4 class="mb-3">Your submitted ratings</h4>
                <p class="text-muted">Each category is required and averaged to determine winners.</p>
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
