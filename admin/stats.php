<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Voting Stats - UMU Varsity Ball';
$activePage = 'stats';

$categoryScores = $pdo->query(
    'SELECT c.id AS category_id, c.name AS category_name, c.gender,
            con.id AS contestant_id, con.name AS contestant_name, con.photo, con.gender AS contestant_gender,
            AVG(v.score) AS avg_score
     FROM categories c
     JOIN votes v ON v.category_id = c.id
     JOIN contestants con ON con.id = v.contestant_id
    AND (c.gender = con.gender OR c.gender = "all")
     GROUP BY c.id, con.id
     ORDER BY c.id, avg_score DESC'
)->fetchAll();

$categoryLeaders = [];
foreach ($categoryScores as $row) {
    $categoryId = $row['category_id'];
    $catGender = strtolower(trim((string)($row['gender'] ?? 'all')));
    $conGender = strtolower(trim((string)($row['contestant_gender'] ?? '')));

    if (!isset($categoryLeaders[$categoryId])) {
        $categoryLeaders[$categoryId] = [
            'category_name' => $row['category_name'],
            'male' => null,
            'female' => null,
        ];
    }

    if ($conGender === 'male') {
        if ($catGender === 'female') {
            continue; // category only for females
        }
        if ($categoryLeaders[$categoryId]['male'] === null || $row['avg_score'] > $categoryLeaders[$categoryId]['male']['avg_score']) {
            $categoryLeaders[$categoryId]['male'] = $row;
        }
    } elseif ($conGender === 'female') {
        if ($catGender === 'male') {
            continue; // category only for males
        }
        if ($categoryLeaders[$categoryId]['female'] === null || $row['avg_score'] > $categoryLeaders[$categoryId]['female']['avg_score']) {
            $categoryLeaders[$categoryId]['female'] = $row;
        }
    }
}

$overallScores = $pdo->query(
    'SELECT con.id AS contestant_id, con.name AS contestant_name, con.gender,
            AVG(v.score) AS avg_score
     FROM contestants con
     JOIN votes v ON v.contestant_id = con.id
    JOIN categories c ON c.id = v.category_id
    WHERE c.gender = con.gender OR c.gender = "all"
    GROUP BY con.id, con.gender
    ORDER BY con.gender, avg_score DESC'
)->fetchAll();

$maleScores = [];
$femaleScores = [];
foreach ($overallScores as $r) {
    $g = strtolower(trim((string)($r['gender'] ?? '')));
    if ($g === 'male') {
        $maleScores[] = $r;
    } elseif ($g === 'female') {
        $femaleScores[] = $r;
    } else {
        $maleScores[] = $r;
        $femaleScores[] = $r;
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Voting Stats</h2>
<?php if (empty($overallScores)): ?>
    <div class="alert alert-warning">No votes yet.</div>
<?php else: ?>
    <div class="card-dark p-4 mb-4">
        <h4 class="mb-3">Overall Rankings</h4>
        <div class="row">
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
                                    <th>Average Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maleScores as $row): ?>
                                    <tr>
                                        <td><?php echo h($row['contestant_name']); ?></td>
                                        <td><?php echo number_format((float) $row['avg_score'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
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
                                    <th>Average Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($femaleScores as $row): ?>
                                    <tr>
                                        <td><?php echo h($row['contestant_name']); ?></td>
                                        <td><?php echo number_format((float) $row['avg_score'], 2); ?></td>
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
                        <th>Male Winner</th>
                        <th>Average</th>
                        <th>Female Winner</th>
                        <th>Average</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryLeaders as $cat): ?>
                        <tr>
                            <td><?php echo h($cat['category_name']); ?></td>
                            <td>
                                <?php if (!empty($cat['male'])): ?>
                                    <?php echo h($cat['male']['contestant_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($cat['male']) ? number_format((float)$cat['male']['avg_score'], 2) : '—'; ?></td>
                            <td>
                                <?php if (!empty($cat['female'])): ?>
                                    <?php echo h($cat['female']['contestant_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($cat['female']) ? number_format((float)$cat['female']['avg_score'], 2) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
