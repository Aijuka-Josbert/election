<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
$config = require __DIR__ . '/config/config.php';

$pageTitle = 'Vote - UMU Varsity Ball';
require_once __DIR__ . '/includes/header.php';

$userId = (int) $_SESSION['user_id'];
$userStmt = $pdo->prepare('SELECT has_voted FROM users WHERE id = ?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
$hasVoted = $user ? (int) $user['has_voted'] : 0;
$votingOpen = (bool) ($config['app']['voting_open'] ?? true);
$start = $config['app']['voting_start'] ?? '';
$end = $config['app']['voting_end'] ?? '';
if ($start && $end) {
    $now = new DateTime('now');
    $startTime = new DateTime($start);
    $endTime = new DateTime($end);
    $votingOpen = $now >= $startTime && $now <= $endTime;
}

$limit = (int) ($config['app']['category_limit'] ?? 10);
$maleCategoriesStmt = $pdo->prepare('SELECT id, name, gender FROM categories WHERE gender = ? ORDER BY id LIMIT ?');
$maleCategoriesStmt->execute(['male', $limit]);
$femaleCategoriesStmt = $pdo->prepare('SELECT id, name, gender FROM categories WHERE gender = ? ORDER BY id LIMIT ?');
$femaleCategoriesStmt->execute(['female', $limit]);
$categories = array_merge($maleCategoriesStmt->fetchAll(), $femaleCategoriesStmt->fetchAll());
$contestants = $pdo->query('SELECT id, name, gender, photo, bio FROM contestants ORDER BY gender, name')->fetchAll();

$categoriesByGender = ['male' => [], 'female' => []];
foreach ($categories as $category) {
    $categoriesByGender[$category['gender']][] = $category;
}

$contestantsByGender = ['male' => [], 'female' => []];
foreach ($contestants as $contestant) {
    $contestantsByGender[$contestant['gender']][] = $contestant;
}

$errors = [];
$success = false;
$submittedScores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasVoted && $votingOpen) {
    if (!$categories || !$contestants) {
        $errors[] = 'Voting is not ready. Please check back later.';
    } else {
        foreach ($categories as $category) {
            foreach ($contestantsByGender[$category['gender']] as $contestant) {
                $score = $_POST['scores'][$category['id']][$contestant['id']] ?? null;
                if ($score === null || !is_numeric($score)) {
                    $errors[] = 'Please rate every contestant in all categories.';
                    break 2;
                }

                $scoreValue = (int) $score;
                if ($scoreValue < 1 || $scoreValue > 10) {
                    $errors[] = 'Scores must be between 1 and 10.';
                    break 2;
                }
            }
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO votes (user_id, contestant_id, category_id, score) VALUES (?, ?, ?, ?)');

        foreach ($categories as $category) {
            foreach ($contestantsByGender[$category['gender']] as $contestant) {
                $scoreValue = (int) $_POST['scores'][$category['id']][$contestant['id']];
                $insert->execute([$userId, $contestant['id'], $category['id'], $scoreValue]);
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
    $totalRatings += count($contestantsByGender[$category['gender']]);
}
?>
<section class="py-5">
    <div class="container">
        <div class="section-title">
            <span>Voting</span>
            <h2 class="mb-0">Rate each contestant</h2>
        </div>

        <?php if (!$votingOpen): ?>
            <div class="alert alert-warning">Voting is currently closed. Please check back later.</div>
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
            <form method="post" id="voteForm" data-total="<?php echo (int) $totalRatings; ?>">
                <?php foreach (['male' => 'Mr UMU Rubaga', 'female' => 'Mrs UMU Rubaga'] as $genderKey => $genderTitle): ?>
                    <div class="mb-5">
                        <h3 class="mb-4"><?php echo h($genderTitle); ?></h3>
                        <?php foreach ($categoriesByGender[$genderKey] as $category): ?>
                            <div class="vote-group mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0"><?php echo h($category['name']); ?></h4>
                                    <span class="badge badge-gold">Rate 1 - 10</span>
                                </div>
                                <div class="row g-3">
                                    <?php foreach ($contestantsByGender[$genderKey] as $contestant): ?>
                                        <div class="col-12 col-md-6">
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
                                                <div class="range-wrap mt-3">
                                                    <input
                                                        type="range"
                                                        class="form-range"
                                                        min="1"
                                                        max="10"
                                                        step="1"
                                                        name="scores[<?php echo (int) $category['id']; ?>][<?php echo (int) $contestant['id']; ?>]"
                                                        value="5"
                                                        required
                                                    >
                                                    <div class="d-flex justify-content-between">
                                                        <small>1</small>
                                                        <span class="range-value">5</span>
                                                        <small>10</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <button class="btn btn-primary btn-lg" type="submit">Submit Vote</button>
            </form>
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
                                    <?php foreach ($contestantsByGender[$category['gender']] as $contestant): ?>
                                        <?php
                                        $cell = $submittedScores[$category['id']][$contestant['id']] ?? null;
                                        $scoreValue = is_array($cell) ? $cell['score'] : (int) $cell;
                                        ?>
                                        <tr>
                                            <td><?php echo h($contestant['name']); ?></td>
                                            <td><?php echo (int) $scoreValue; ?>/10</td>
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
