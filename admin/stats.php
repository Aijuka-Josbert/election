<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Voting Stats - UMU Varsity Ball';
$activePage = 'stats';

$categoryScores = $pdo->query(
    'SELECT c.id AS category_id, c.name AS category_name, c.gender,
            con.id AS contestant_id, con.name AS contestant_name, con.photo,
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
    if (!isset($categoryLeaders[$categoryId]) || $row['avg_score'] > $categoryLeaders[$categoryId]['avg_score']) {
        $categoryLeaders[$categoryId] = $row;
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

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Voting Stats</h2>
<?php if (!$overallScores): ?>
    <div class="alert alert-warning">No votes yet.</div>
<?php else: ?>
    <div class="card-dark p-4 mb-4">
        <h4 class="mb-3">Overall Rankings</h4>
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle">
                <thead>
                    <tr>
                        <th>Contestant</th>
                        <th>Gender</th>
                        <th>Average Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overallScores as $row): ?>
                        <tr>
                            <td><?php echo h($row['contestant_name']); ?></td>
                            <td><?php echo h($row['gender']); ?></td>
                            <td><?php echo number_format((float) $row['avg_score'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-dark p-4">
        <h4 class="mb-3">Category Leaders</h4>
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Contestant</th>
                        <th>Average Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryLeaders as $leader): ?>
                        <tr>
                            <td><?php echo h($leader['category_name']); ?></td>
                            <td><?php echo h($leader['contestant_name']); ?></td>
                            <td><?php echo number_format((float) $leader['avg_score'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
