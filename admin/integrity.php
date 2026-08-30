<?php
/*
 * admin/integrity.php
 * A reconciliation/diagnostics page: surfaces data that could silently
 * break voting or results — corrupted category gender values (see
 * includes/helpers.php::ensure_category_gender_enum()), vote counts split
 * by mode, and ballot totals — instead of an admin having to query the
 * database directly to find these things.
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$config = require __DIR__ . '/../config/config.php';
if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
    ensure_category_gender_enum($pdo);
    ensure_active_column($pdo, 'categories');
    ensure_active_column($pdo, 'contestants');
}

$pageTitle = 'Data Integrity';
$activePage = 'integrity';

$categories = $pdo->query('SELECT id, name, gender, active FROM categories ORDER BY id')->fetchAll();
$contestants = $pdo->query('SELECT id, name, gender, active FROM contestants ORDER BY id')->fetchAll();

$corruptedCategories = array_filter(
    $categories,
    fn($c) => !in_array((string) ($c['gender'] ?? ''), ['male', 'female', 'all'], true)
);
$corruptedContestants = array_filter(
    $contestants,
    fn($c) => !in_array((string) ($c['gender'] ?? ''), ['male', 'female'], true)
);

$categoryGenderCounts = ['male' => 0, 'female' => 0, 'all' => 0, 'other' => 0];
foreach ($categories as $c) {
    $g = (string) ($c['gender'] ?? '');
    if (isset($categoryGenderCounts[$g])) {
        $categoryGenderCounts[$g]++;
    } else {
        $categoryGenderCounts['other']++;
    }
}

$contestantGenderCounts = ['male' => 0, 'female' => 0, 'other' => 0];
foreach ($contestants as $c) {
    $g = (string) ($c['gender'] ?? '');
    if (isset($contestantGenderCounts[$g])) {
        $contestantGenderCounts[$g]++;
    } else {
        $contestantGenderCounts['other']++;
    }
}

$votesByMode = vote_counts_by_mode($pdo);
$totalVoteRows = (int) $pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn();
$totalVoters = (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM votes WHERE user_id IS NOT NULL")->fetchColumn();
$anonymizedVoteRows = (int) $pdo->query('SELECT COUNT(*) FROM votes WHERE user_id IS NULL')->fetchColumn();
$votersWithZeroVoteRows = (int) $pdo->query(
    "SELECT COUNT(*) FROM users u WHERE u.has_voted = 1
     AND NOT EXISTS (SELECT 1 FROM votes v WHERE v.user_id = u.id)"
)->fetchColumn();

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Data Integrity</h2>
<p class="text-muted mb-4">
    A quick health check on data that can silently break voting or results without throwing any visible error —
    review this after importing/seeding data, or whenever results look off.
</p>

<?php if ($corruptedCategories || $corruptedContestants): ?>
    <div class="alert alert-danger mb-4">
        <strong>Action needed.</strong>
        <?php if ($corruptedCategories): ?>
            <div><?php echo count($corruptedCategories); ?> categor<?php echo count($corruptedCategories) === 1 ? 'y has' : 'ies have'; ?> an unrecognized gender value — the app currently treats these as "all" (inclusive) so nothing is silently hidden, but fix them properly via <a class="alert-link" href="categories.php">Categories → Edit</a>.</div>
        <?php endif; ?>
        <?php if ($corruptedContestants): ?>
            <div><?php echo count($corruptedContestants); ?> contestant<?php echo count($corruptedContestants) === 1 ? ' has' : 's have'; ?> an unrecognized gender value and won't appear on any ballot. Fix via <a class="alert-link" href="contestants.php">Contestants → Edit</a>.</div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="alert alert-success mb-4">No unrecognized gender values found on categories or contestants.</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card-dark p-4">
            <h5 class="mb-3">Categories by gender</h5>
            <table class="table table-dark table-sm mb-0">
                <tbody>
                    <tr><td>Male</td><td class="text-end"><?php echo $categoryGenderCounts['male']; ?></td></tr>
                    <tr><td>Female</td><td class="text-end"><?php echo $categoryGenderCounts['female']; ?></td></tr>
                    <tr><td>All (both genders)</td><td class="text-end"><?php echo $categoryGenderCounts['all']; ?></td></tr>
                    <tr class="<?php echo $categoryGenderCounts['other'] > 0 ? 'table-danger' : ''; ?>">
                        <td>Unrecognized</td><td class="text-end"><?php echo $categoryGenderCounts['other']; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-dark p-4">
            <h5 class="mb-3">Contestants by gender</h5>
            <table class="table table-dark table-sm mb-0">
                <tbody>
                    <tr><td>Male</td><td class="text-end"><?php echo $contestantGenderCounts['male']; ?></td></tr>
                    <tr><td>Female</td><td class="text-end"><?php echo $contestantGenderCounts['female']; ?></td></tr>
                    <tr class="<?php echo $contestantGenderCounts['other'] > 0 ? 'table-danger' : ''; ?>">
                        <td>Unrecognized</td><td class="text-end"><?php echo $contestantGenderCounts['other']; ?></td>
                    </tr>
                </tbody>
            </table>
            <p class="text-muted small mt-2 mb-0">
                If this shows 0 female contestants and you expected some, that's the bug — add or edit them
                in <a href="contestants.php">Contestants</a> and set Gender to "Female".
            </p>
        </div>
    </div>
</div>

<div class="card-dark p-4">
    <h5 class="mb-3">Ballot reconciliation</h5>
    <table class="table table-dark table-sm mb-0">
        <tbody>
            <tr><td>Total vote rows</td><td class="text-end"><?php echo number_format($totalVoteRows); ?></td></tr>
            <?php foreach ($votesByMode as $modeKey => $count): ?>
                <tr><td>&nbsp;&nbsp;— cast under "<?php echo h($modeKey); ?>" mode</td><td class="text-end"><?php echo number_format($count); ?></td></tr>
            <?php endforeach; ?>
            <tr><td>Distinct voters (non-anonymized rows)</td><td class="text-end"><?php echo number_format($totalVoters); ?></td></tr>
            <tr><td>Anonymized vote rows (voter link removed)</td><td class="text-end"><?php echo number_format($anonymizedVoteRows); ?></td></tr>
            <tr class="<?php echo $votersWithZeroVoteRows > 0 ? 'table-warning' : ''; ?>">
                <td>Users marked has_voted=1 with zero vote rows</td>
                <td class="text-end"><?php echo number_format($votersWithZeroVoteRows); ?></td>
            </tr>
        </tbody>
    </table>
    <p class="text-muted small mt-2 mb-0">
        The last row should always be 0 — vote.php already self-heals this per-user on their next visit to
        /vote.php, but a non-zero count here means someone's has_voted flag and actual ballot are out of sync.
    </p>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
