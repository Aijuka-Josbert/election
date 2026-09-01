<?php
/*
 * results.php
 * Admin-facing results and leaderboards. Calculates category averages and
 * overall averages using SQL AVG() and prepares data for charts.
 * Only visible to admins by default; the $showResults flag controls visibility.
 */
$pageTitle = 'Results';
$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$isAdmin = is_logged_in() && is_admin($config);
$resultsPublic = (bool) ($config['app']['results_public'] ?? false);
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
// Show results to admins or if results are made public via settings
$showResults = $isAdmin || $resultsPublic;

$votingMode = get_voting_mode($config);
if (isset($pdo)) {
    ensure_category_gender_enum($pdo);
}

$categoryScores = [];
$categoryLeaders = [];
$overallScores = [];
$overallScoresByGender = ['female' => [], 'male' => []];
$overallWinners = ['female' => null, 'male' => null];
$maleChartLabels = [];
$maleChartScores = [];
$femaleChartLabels = [];
$femaleChartScores = [];
$categoryLabels = [];
$categoryAverages = [];
$adminTotals = ['total_votes' => 0, 'total_voters' => 0];

if ($showResults) {
    if ($isAdmin) {
        $adminTotalsStmt = $pdo->query(
            'SELECT COUNT(*) AS total_votes, COUNT(DISTINCT user_id) AS total_voters
             FROM votes'
        );
        $adminTotalsRow = $adminTotalsStmt->fetch();
        $adminTotals['total_votes'] = (int) ($adminTotalsRow['total_votes'] ?? 0);
        $adminTotals['total_voters'] = (int) ($adminTotalsRow['total_voters'] ?? 0);
    }

    // Category and overall leaders now come from the same shared, mode-aware
    // helper vote.php and certificate.php use — previously this page ran its
    // own third copy of the AVG(score) query, which would have silently
    // mis-ranked everyone once the simple one-click ballot mode was in use.
    $board = get_leaderboard($pdo, $votingMode);
    $categoryLeaders = $board['category_leaders'];
    $overallWinners = $board['overall_winners'];
    $overallScores = array_filter($overallWinners); // used below only to gate the "no votes yet" message

    // Overall contestant scores per gender, for the top-5 charts. Filtered
    // by v.mode = $votingMode, same as get_leaderboard() — this query was
    // missing that filter, so it could blend votes cast under a different
    // (e.g. previously-active) mode into these numbers instead of showing
    // only the currently-active mode's results.
    $metricSql = $votingMode === 'simple' ? 'COUNT(v.id)' : 'AVG(v.score)';
    $overallScoresStmt = $pdo->prepare(
        "SELECT con.id AS contestant_id, con.name AS contestant_name, con.gender, con.photo,
                $metricSql AS metric
         FROM contestants con
         JOIN votes v ON v.contestant_id = con.id AND v.mode = :mode
         JOIN categories c ON c.id = v.category_id
         WHERE c.gender = con.gender OR c.gender NOT IN (\"male\", \"female\")
         GROUP BY con.id, con.gender
         ORDER BY con.gender, metric DESC"
    );
    $overallScoresStmt->execute(['mode' => $votingMode]);
    $overallScoresRows = $overallScoresStmt->fetchAll();

    foreach ($overallScoresRows as $row) {
        $gender = $row['gender'] ?? '';
        if (!isset($overallScoresByGender[$gender])) {
            continue;
        }
        $overallScoresByGender[$gender][] = $row;
    }

    // Prepare top-5 labels and scores per gender.
    foreach (array_slice($overallScoresByGender['male'], 0, 5) as $row) {
        $maleChartLabels[] = $row['contestant_name'];
        $maleChartScores[] = round((float) $row['metric'], 2);
    }

    foreach (array_slice($overallScoresByGender['female'], 0, 5) as $row) {
        $femaleChartLabels[] = $row['contestant_name'];
        $femaleChartScores[] = round((float) $row['metric'], 2);
    }

    // Category-wise scores for charting, separated by gender for "all" categories
    $categoryAvgStmt = $pdo->prepare(
        "SELECT c.id AS category_id, c.name AS category_name, c.gender, con.gender AS contestant_gender, $metricSql AS metric
         FROM categories c
         JOIN votes v ON v.category_id = c.id AND v.mode = :mode
         JOIN contestants con ON con.id = v.contestant_id
         WHERE c.gender = con.gender OR c.gender NOT IN (\"male\", \"female\")
         GROUP BY c.id, con.gender
         ORDER BY c.gender, c.id, con.gender"
    );
    $categoryAvgStmt->execute(['mode' => $votingMode]);
    $categoryAvgRows = $categoryAvgStmt->fetchAll();

    foreach ($categoryAvgRows as $row) {
        $categoryGender = normalize_category_gender($row['gender'] ?? null);
        $contestantGender = $row['contestant_gender'] ?? 'male';
        
        if ($categoryGender === 'all') {
            // For "all" categories, separate by gender
            $label = $row['category_name'] . ' (' . ucfirst($contestantGender) . ')';
        } else {
            // For gender-specific categories
            $label = ucfirst($categoryGender) . ' - ' . $row['category_name'];
        }
        
        $categoryLabels[] = $label;
        $categoryAverages[] = round((float) $row['metric'], 2);
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
            <div class="alert alert-warning">Results are available to admins only (or when made public via settings).</div>
        <?php elseif (!$overallScores): ?>
            <div class="alert alert-warning">No votes yet. Results will appear once voting starts.</div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div class="text-muted">Admin view: full results with live totals.</div>
                <button class="btn btn-outline-light" type="button" id="printResultsBtn">Print results</button>
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

            <div class="results-slider" data-autoplay="true">
                <div class="results-track">
                    <?php foreach ($categoryLeaders as $leader): ?>
                        <div class="result-slide">
                            <div class="card-dark p-4 h-100">
                                <div class="text-uppercase text-muted small">Category winner</div>
                                <h4 class="mt-2 mb-3">
                                    <?php echo h($leader['category_name']); ?>
                                    <?php if (normalize_category_gender($leader['gender'] ?? null) === 'all'): ?>
                                        <small class="d-block" style="font-size: 0.75rem; margin-top: 4px;">
                                            (<?php echo ucfirst($leader['contestant_gender'] ?? 'male'); ?>)
                                        </small>
                                    <?php endif; ?>
                                </h4>
                                <div class="d-flex gap-3 align-items-center">
                                    <img class="contestant-img" style="width: 120px; height: 120px;" src="<?php echo h(asset_url($leader['photo'], $config)); ?>" alt="<?php echo h($leader['contestant_name']); ?>">
                                    <div>
                                        <h5 class="mb-1"><?php echo h($leader['contestant_name']); ?></h5>
                                        <div class="text-muted"><?php echo h(format_leaderboard_metric($leader, $votingMode)); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach (['female' => site_female_title($config), 'male' => site_male_title($config)] as $gender => $title): ?>
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
                <button class="slider-btn prev" type="button" aria-label="Previous slide">&#10094;</button>
                <button class="slider-btn next" type="button" aria-label="Next slide">&#10095;</button>
                <div class="slider-dots" aria-hidden="true"></div>
            </div>

            <div class="row g-4">
                <?php foreach ($categoryLeaders as $leader): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-dark p-3 h-100">
                            <h5 class="mb-2">
                                <?php echo h($leader['category_name']); ?>
                                <?php if (normalize_category_gender($leader['gender'] ?? null) === 'all'): ?>
                                    <small class="d-block text-muted" style="font-size: 0.85rem; font-weight: normal;">
                                        (<?php echo ucfirst($leader['contestant_gender'] ?? 'male'); ?>)
                                    </small>
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex gap-3 align-items-center">
                                <img class="contestant-img" style="width: 90px; height: 90px;" src="<?php echo h(asset_url($leader['photo'], $config)); ?>" alt="<?php echo h($leader['contestant_name']); ?>">
                                <div>
                                    <div class="fw-bold"><?php echo h($leader['contestant_name']); ?></div>
                                    <small class="text-muted"><?php echo h(format_leaderboard_metric($leader, $votingMode)); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-lg-6">
                    <div class="card-dark p-4 h-100">
                        <h4 class="mb-3">Top male contestants</h4>
                        <canvas
                            id="overallChartMale"
                            height="180"
                            data-labels='<?php echo json_encode($maleChartLabels); ?>'
                            data-scores='<?php echo json_encode($maleChartScores); ?>'
                        ></canvas>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card-dark p-4 h-100">
                        <h4 class="mb-3">Top female contestants</h4>
                        <canvas
                            id="overallChartFemale"
                            height="180"
                            data-labels='<?php echo json_encode($femaleChartLabels); ?>'
                            data-scores='<?php echo json_encode($femaleChartScores); ?>'
                        ></canvas>
                    </div>
                </div>
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
                    Congratulations to <?php echo h(site_female_title($config)); ?>: <?php echo h($femaleWinner); ?>, and <?php echo h(site_male_title($config)); ?>: <?php echo h($maleWinner); ?>.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-light" href="certificate.php?gender=female">Download Mrs certificate</a>
                    <a class="btn btn-outline-light" href="certificate.php?gender=male">Download Mr certificate</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
