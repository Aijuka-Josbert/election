<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$config = require __DIR__ . '/../config/config.php';
if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
}

$pageTitle = 'Voting Stats';
$activePage = 'stats';

$votingMode = get_voting_mode($config);
if (isset($pdo)) {
    ensure_category_gender_enum($pdo);
}
// Mode-aware — this page used to run its own 5th independent copy of the
// AVG(score)-only ranking SQL (vote.php, results.php, certificate.php and
// admin/index.php each had their own before this branch). Now uses the
// same shared helper as all of them, so this page can never disagree with
// results.php about who's leading, and stays correct once the simple
// one-click mode is active (where AVG is meaningless — every vote is
// worth 1, so ranking has to be by count instead).
$board = get_leaderboard($pdo, $votingMode);
$maleScores = $board['overall_all']['male'];
$femaleScores = $board['overall_all']['female'];

// Rebuild the category-by-gender table from the categories list (so
// categories with zero votes still show a row) plus the shared leaderboard.
$categoriesList = $pdo->query('SELECT id, name, gender FROM categories ORDER BY id')->fetchAll();
$categoryLeaders = [];
foreach ($categoriesList as $category) {
    $categoryId = $category['id'];
    $categoryGender = normalize_category_gender($category['gender'] ?? null);

    $maleLeader = null;
    $femaleLeader = null;
    if ($categoryGender === 'all') {
        $maleLeader = $board['category_leaders'][$categoryId . '_male'] ?? null;
        $femaleLeader = $board['category_leaders'][$categoryId . '_female'] ?? null;
    } elseif ($categoryGender === 'male') {
        $maleLeader = $board['category_leaders'][$categoryId] ?? null;
    } elseif ($categoryGender === 'female') {
        $femaleLeader = $board['category_leaders'][$categoryId] ?? null;
    }

    $categoryLeaders[$categoryId] = [
        'category_name' => $category['name'],
        'male' => $maleLeader,
        'female' => $femaleLeader,
    ];
}

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Voting Stats</h2>
<p class="text-muted mb-4">
    Showing results for the <strong><?php echo $votingMode === 'simple' ? 'simple ballot (one-click)' : 'rating ballot (1–5)'; ?></strong> mode
    — the one currently active in Settings.
</p>
<?php if (empty($maleScores) && empty($femaleScores)): ?>
    <div class="alert alert-warning">No votes yet.</div>
<?php else: ?>
    <div class="card-dark p-4 mb-4">
        <h4 class="mb-3">Overall Rankings</h4>
        <div class="row">
            <div class="col-md-6 mb-3">
                <h5>Female</h5>
                <?php if (empty($femaleScores)): ?>
                    <div class="alert alert-secondary">No female scores yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Contestant</th>
                                    <th><?php echo $votingMode === 'simple' ? 'Votes' : 'Average Score'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($femaleScores as $row): ?>
                                    <tr>
                                        <td><?php echo h($row['contestant_name']); ?></td>
                                        <td><?php echo h(format_leaderboard_metric($row, $votingMode)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <h5>Male</h5>
                <?php if (empty($maleScores)): ?>
                    <div class="alert alert-secondary">No male scores yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Contestant</th>
                                    <th><?php echo $votingMode === 'simple' ? 'Votes' : 'Average Score'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maleScores as $row): ?>
                                    <tr>
                                        <td><?php echo h($row['contestant_name']); ?></td>
                                        <td><?php echo h(format_leaderboard_metric($row, $votingMode)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-dark p-4">
        <h4 class="mb-3">Category Leaders</h4>
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Female Winner</th>
                        <th><?php echo $votingMode === 'simple' ? 'Votes' : 'Average'; ?></th>
                        <th>Male Winner</th>
                        <th><?php echo $votingMode === 'simple' ? 'Votes' : 'Average'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryLeaders as $cat): ?>
                        <tr>
                            <td><?php echo h($cat['category_name']); ?></td>
                            <td>
                                <?php if (!empty($cat['female'])): ?>
                                    <?php echo h($cat['female']['contestant_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($cat['female']) ? h(format_leaderboard_metric($cat['female'], $votingMode)) : '—'; ?></td>
                            <td>
                                <?php if (!empty($cat['male'])): ?>
                                    <?php echo h($cat['male']['contestant_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($cat['male']) ? h(format_leaderboard_metric($cat['male'], $votingMode)) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
