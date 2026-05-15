<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$config = require __DIR__ . '/../config/config.php';

$pageTitle = 'Admin Dashboard - UMU Varsity Ball';
$activePage = 'dashboard';
require_once __DIR__ . '/partials/header.php';

$totalVoters = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalVoted = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE has_voted = 1')->fetchColumn();
$totalVotes = (int) $pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn();
$totalContestants = (int) $pdo->query('SELECT COUNT(*) FROM contestants')->fetchColumn();

$overallScores = $pdo->query(
    'SELECT con.id AS contestant_id, con.name AS contestant_name, con.gender, con.photo,
            AVG(v.score) AS avg_score
     FROM contestants con
     JOIN votes v ON v.contestant_id = con.id
    JOIN categories c ON c.id = v.category_id
    WHERE c.gender = con.gender OR c.gender = "all"
    GROUP BY con.id, con.gender
    ORDER BY con.gender, avg_score DESC'
)->fetchAll();

$overallWinners = ['male' => null, 'female' => null];
foreach ($overallScores as $row) {
    if ($row['gender'] === 'male' && $overallWinners['male'] === null) {
        $overallWinners['male'] = $row;
    }
    if ($row['gender'] === 'female' && $overallWinners['female'] === null) {
        $overallWinners['female'] = $row;
    }
}
?>
<h2 class="mb-4">Dashboard</h2>
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
            <h6 class="text-muted">Total Ratings</h6>
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
    <?php foreach (['male' => 'Mr UMU Rubaga', 'female' => 'Mrs UMU Rubaga'] as $gender => $title): ?>
        <?php $winner = $overallWinners[$gender]; ?>
        <div class="col-md-6">
            <div class="leader-card">
                <h4 class="mb-3"><?php echo h($title); ?></h4>
                <?php if ($winner): ?>
                    <div class="d-flex gap-3 align-items-center">
                        <img class="contestant-img" style="width: 110px; height: 110px;" src="<?php echo h(asset_url($winner['photo'], $config)); ?>" alt="<?php echo h($winner['contestant_name']); ?>">
                        <div>
                            <h5 class="mb-1"><?php echo h($winner['contestant_name']); ?></h5>
                            <div class="text-muted">Avg score: <?php echo number_format((float) $winner['avg_score'], 2); ?></div>
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
