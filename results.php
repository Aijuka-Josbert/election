<?php
$pageTitle = 'Results - UMU Varsity Ball';
$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$isAdmin = is_logged_in() && is_admin($config);
$tzName = $config['app']['timezone'] ?? 'UTC';
$tz = new DateTimeZone($tzName);
$now = new DateTime('now', $tz);
$votingEnabled = (bool) ($config['app']['voting_open'] ?? false);
$startValue = $config['app']['voting_start'] ?? '';
$endValue = $config['app']['voting_end'] ?? '';
$startTime = $startValue !== '' ? new DateTime($startValue, $tz) : null;
$endTime = $endValue !== '' ? new DateTime($endValue, $tz) : null;
$hasStarted = $startTime ? $now >= $startTime : true;
$hasEnded = $endTime ? $now > $endTime : false;
$showResults = $isAdmin;

$categoryScores = [];
$categoryLeaders = [];
$overallScores = [];
$overallWinners = ['male' => null, 'female' => null];
$chartLabels = [];
$chartScores = [];
$categoryLabels = [];
$categoryAverages = [];

if ($showResults) {
    $categoryScores = $pdo->query(
        'SELECT c.id AS category_id, c.name AS category_name, c.gender,
                con.id AS contestant_id, con.name AS contestant_name, con.photo,
                AVG(v.score) AS avg_score
         FROM categories c
         JOIN votes v ON v.category_id = c.id
         JOIN contestants con ON con.id = v.contestant_id
         GROUP BY c.id, con.id
         ORDER BY c.id, avg_score DESC'
    )->fetchAll();

    foreach ($categoryScores as $row) {
        $categoryId = $row['category_id'];
        if (!isset($categoryLeaders[$categoryId]) || $row['avg_score'] > $categoryLeaders[$categoryId]['avg_score']) {
            $categoryLeaders[$categoryId] = $row;
        }
    }

    $overallScores = $pdo->query(
        'SELECT con.id AS contestant_id, con.name AS contestant_name, con.gender, con.photo,
                AVG(v.score) AS avg_score
         FROM contestants con
         JOIN votes v ON v.contestant_id = con.id
         GROUP BY con.id
         ORDER BY avg_score DESC'
    )->fetchAll();

    foreach ($overallScores as $row) {
        if ($row['gender'] === 'male' && $overallWinners['male'] === null) {
            $overallWinners['male'] = $row;
        }
        if ($row['gender'] === 'female' && $overallWinners['female'] === null) {
            $overallWinners['female'] = $row;
        }
    }

    foreach (array_slice($overallScores, 0, 5) as $row) {
        $chartLabels[] = $row['contestant_name'];
        $chartScores[] = round((float) $row['avg_score'], 2);
    }

    $categoryAvgRows = $pdo->query(
        'SELECT c.name AS category_name, AVG(v.score) AS avg_score
         FROM categories c
         JOIN votes v ON v.category_id = c.id
         GROUP BY c.id
         ORDER BY c.id'
    )->fetchAll();

    foreach ($categoryAvgRows as $row) {
        $categoryLabels[] = $row['category_name'];
        $categoryAverages[] = round((float) $row['avg_score'], 2);
    }
}
?>
<section class="py-5">
    <div class="container">
        <div class="section-title">
            <span>Results</span>
            <h2 class="mb-0">Live leaderboard</h2>
        </div>

        <?php if (!$showResults): ?>
            <div class="alert alert-warning">Results are available to admins only.</div>
        <?php elseif (!$overallScores): ?>
            <div class="alert alert-warning">No votes yet. Results will appear once voting starts.</div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div class="text-muted">Admin view: full results with live totals.</div>
                <button class="btn btn-outline-light" type="button" id="printResultsBtn">Print results</button>
            </div>

            <div class="results-slider" data-autoplay="true">
                <div class="results-track">
                    <?php foreach ($categoryLeaders as $leader): ?>
                        <div class="result-slide">
                            <div class="card-dark p-4 h-100">
                                <div class="text-uppercase text-muted small">Category winner</div>
                                <h4 class="mt-2 mb-3"><?php echo h($leader['category_name']); ?></h4>
                                <div class="d-flex gap-3 align-items-center">
                                    <img class="contestant-img" style="width: 120px; height: 120px;" src="<?php echo h(asset_url($leader['photo'], $config)); ?>" alt="<?php echo h($leader['contestant_name']); ?>">
                                    <div>
                                        <h5 class="mb-1"><?php echo h($leader['contestant_name']); ?></h5>
                                        <div class="text-muted">Avg score: <?php echo number_format((float) $leader['avg_score'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach (['male' => 'Mr UMU Rubaga', 'female' => 'Mrs UMU Rubaga'] as $gender => $title): ?>
                        <?php $winner = $overallWinners[$gender]; ?>
                        <div class="result-slide">
                            <div class="leader-card winner-spotlight h-100">
                                <div class="text-uppercase text-muted small">Overall winner</div>
                                <h4 class="mt-2 mb-3"><?php echo h($title); ?></h4>
                                <?php if ($winner): ?>
                                    <div class="d-flex gap-3 align-items-center">
                                        <img class="contestant-img" style="width: 140px; height: 140px;" src="<?php echo h(asset_url($winner['photo'], $config)); ?>" alt="<?php echo h($winner['contestant_name']); ?>">
                                        <div>
                                            <h5 class="mb-1"><i class="bi bi-trophy-fill text-warning"></i> <?php echo h($winner['contestant_name']); ?></h5>
                                            <div class="text-muted">Average score: <?php echo number_format((float) $winner['avg_score'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No votes yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="slider-btn prev" type="button" aria-label="Previous slide">&#10094;</button>
                <button class="slider-btn next" type="button" aria-label="Next slide">&#10095;</button>
                <div class="slider-dots" aria-hidden="true"></div>
            </div>

            <div class="row g-4">
                <?php foreach ($categoryLeaders as $leader): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-dark p-3 h-100">
                            <h5 class="mb-2"><?php echo h($leader['category_name']); ?></h5>
                            <div class="d-flex gap-3 align-items-center">
                                <img class="contestant-img" style="width: 90px; height: 90px;" src="<?php echo h(asset_url($leader['photo'], $config)); ?>" alt="<?php echo h($leader['contestant_name']); ?>">
                                <div>
                                    <div class="fw-bold"><?php echo h($leader['contestant_name']); ?></div>
                                    <small class="text-muted">Avg: <?php echo number_format((float) $leader['avg_score'], 2); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card-dark p-4 mb-5">
                <h4 class="mb-3">Top contestants</h4>
                <canvas
                    id="overallChart"
                    height="140"
                    data-labels='<?php echo json_encode($chartLabels); ?>'
                    data-scores='<?php echo json_encode($chartScores); ?>'
                ></canvas>
            </div>

            <div class="card-dark p-4 mb-5">
                <h4 class="mb-3">Category averages</h4>
                <canvas
                    id="categoryChart"
                    height="220"
                    data-labels='<?php echo json_encode($categoryLabels); ?>'
                    data-scores='<?php echo json_encode($categoryAverages); ?>'
                ></canvas>
            </div>

            <div class="card-dark p-4">
                <h4 class="mb-2">Final statement</h4>
                <p class="text-muted mb-3">
                    <?php
                    $femaleWinner = $overallWinners['female']['contestant_name'] ?? 'TBD';
                    $maleWinner = $overallWinners['male']['contestant_name'] ?? 'TBD';
                    ?>
                    Congratulations to Mr UMU Rubaga: <?php echo h($maleWinner); ?>, and Mrs UMU Rubaga: <?php echo h($femaleWinner); ?>.
                </p>
                <a class="btn btn-outline-light" href="certificate.php">Download certificate</a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
