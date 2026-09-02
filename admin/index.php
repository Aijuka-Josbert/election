<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$config = require __DIR__ . '/../config/config.php';
if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
}

$pageTitle = 'Admin Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/partials/header.php';

$totalVoters = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalVoted = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE has_voted = 1')->fetchColumn();
$totalVotes = (int) $pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn();
$totalContestants = (int) $pdo->query('SELECT COUNT(*) FROM contestants')->fetchColumn();

$votingMode = get_voting_mode($config);
if (isset($pdo)) {
    ensure_category_gender_enum($pdo);
}
$votingStatus = voting_status_message($config);
$resultsPublic = (bool) ($config['app']['results_public'] ?? false);

// Mode-aware — was previously its own 4th independent copy of the
// AVG(score)-only winner query (vote.php, results.php and certificate.php
// each had their own before this branch); now uses the same shared helper
// so this dashboard can never show a different winner than the results
// page does, and stays correct once the simple one-click mode is in use.
$board = get_leaderboard($pdo, $votingMode);
$overallWinners = $board['overall_winners'];

$genderRestrictedCount = (int) $pdo->query("SELECT COUNT(*) FROM categories WHERE gender IN ('male','female')")->fetchColumn();
?>
<h2 class="mb-4">Dashboard</h2>
<?php if ($genderRestrictedCount > 0): ?>
    <div class="alert alert-warning">
        <strong><?php echo $genderRestrictedCount; ?></strong> categor<?php echo $genderRestrictedCount === 1 ? 'y is' : 'ies are'; ?>
        restricted to one gender — <a class="alert-link" href="categories.php">review in Categories</a> if that's not intentional.
    </div>
<?php endif; ?>

<div class="card-dark p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-uppercase text-muted small mb-1">Voting status</div>
            <h4 class="mb-0">
                <?php if ($votingStatus['open']): ?>
                    <span class="badge bg-success">● Voting open</span>
                <?php else: ?>
                    <span class="badge bg-secondary">● Voting closed</span>
                <?php endif; ?>
            </h4>
            <small class="text-muted"><?php echo h($votingStatus['message']); ?></small>
        </div>
        <div>
            <div class="text-uppercase text-muted small mb-1">Voting mode</div>
            <h4 class="mb-0"><?php echo $votingMode === 'simple' ? 'Simple ballot (one-click)' : 'Rating ballot (1–5)'; ?></h4>
        </div>
        <div>
            <div class="text-uppercase text-muted small mb-1">Results</div>
            <h4 class="mb-0"><?php echo $resultsPublic ? 'Public' : 'Admin only'; ?></h4>
        </div>
        <div>
            <a class="btn btn-outline-light btn-sm" href="settings.php">Change settings</a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card-dark p-3">
            <h6 class="text-muted">Total Voters</h6>
            <h3><?php echo $totalVoters; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-dark p-3">
            <h6 class="text-muted">Votes Submitted</h6>
            <h3><?php echo $totalVoted; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-dark p-3">
            <h6 class="text-muted">Total Vote Rows</h6>
            <h3><?php echo $totalVotes; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-dark p-3">
            <h6 class="text-muted">Contestants</h6>
            <h3><?php echo $totalContestants; ?></h3>
        </div>
    </div>
</div>
<div class="row g-4">
    <?php foreach (['female' => site_female_title($config), 'male' => site_male_title($config)] as $gender => $title): ?>
        <?php $winner = $overallWinners[$gender]; ?>
        <div class="col-md-6">
            <div class="leader-card">
                <h4 class="mb-3"><?php echo h($title); ?></h4>
                <?php if ($winner): ?>
                    <div class="d-flex gap-3 align-items-center">
                        <img class="contestant-img" style="width: 110px; height: 110px;" src="<?php echo h(asset_url($winner['photo'], $config)); ?>" alt="<?php echo h($winner['contestant_name']); ?>">
                        <div>
                            <h5 class="mb-1"><?php echo h(leaderboard_winner_label($winner)); ?></h5>
                            <div class="text-muted"><?php echo h(format_leaderboard_metric($winner, $votingMode)); ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No votes yet.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
