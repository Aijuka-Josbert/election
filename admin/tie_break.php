<?php
/*
 * admin/tie_break.php
 * Lists every current tie (per-category and overall) and lets an admin
 * record a manual decision for who wins. See record_tie_break() /
 * get_tie_break_winner() in includes/helpers.php for how a recorded
 * decision is applied — it only sticks while the same tie is still in
 * effect, so it can never silently override a result that has since
 * moved on.
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$config = require __DIR__ . '/../config/config.php';
if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
    ensure_category_gender_enum($pdo);
}

$pageTitle = 'Tie Breaks';
$activePage = 'tie_break';
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } else {
        $categoryId = $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
        $gender = $_POST['gender'] ?? '';
        $winnerId = (int) ($_POST['winner_contestant_id'] ?? 0);

        if (!in_array($gender, ['male', 'female'], true) || $winnerId <= 0) {
            $errors[] = 'Invalid tie-break submission.';
        } else {
            record_tie_break($pdo, $categoryId, $gender, $winnerId);
            $success = 'Tie broken — the selected contestant is now shown as the winner.';
        }
    }
}

$votingMode = get_voting_mode($config);
$board = get_leaderboard($pdo, $votingMode);

// Collect every currently-tied group (unresolved or already resolved —
// showing resolved ones too lets an admin change their mind).
$categoriesList = $pdo->query('SELECT id, name, gender FROM categories WHERE active = 1 ORDER BY id')->fetchAll();
$ties = [];

foreach ($categoriesList as $category) {
    $categoryGender = normalize_category_gender($category['gender'] ?? null);
    $checks = $categoryGender === 'all' ? ['male', 'female'] : [$categoryGender];
    foreach ($checks as $g) {
        $key = $categoryGender === 'all' ? $category['id'] . '_' . $g : $category['id'];
        $leader = $board['category_leaders'][$key] ?? null;
        if ($leader && !empty($leader['tied_with'])) {
            $ties[] = [
                'scope' => 'Category: ' . $category['name'] . ' (' . ucfirst($g) . ')',
                'category_id' => $category['id'],
                'gender' => $g,
                'leader' => $leader,
                'contenders' => array_merge([$leader['contestant_name']], $leader['tied_with']),
            ];
        }
    }
}
foreach (['female', 'male'] as $g) {
    $winner = $board['overall_winners'][$g] ?? null;
    if ($winner && !empty($winner['tied_with'])) {
        $ties[] = [
            'scope' => 'Overall ' . ($g === 'male' ? site_male_title($config) : site_female_title($config)),
            'category_id' => null,
            'gender' => $g,
            'leader' => $winner,
            'contenders' => array_merge([$winner['contestant_name']], $winner['tied_with']),
        ];
    }
}

// For rendering the contestant picker per tie, we need each contender's
// id, not just their name — re-query so the form can submit an id.
$contestantsById = [];
foreach ($pdo->query('SELECT id, name, photo FROM contestants') as $c) {
    $contestantsById[$c['name']] = $c; // names are effectively unique in this app's data model
}

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Tie Breaks</h2>
<p class="text-muted mb-4">
    A tie means two or more contestants have the exact same <?php echo $votingMode === 'simple' ? 'vote count' : 'average score'; ?>.
    Nothing here changes any vote — it only records which contestant is shown as the winner on results/certificates while the tie holds.
</p>
<?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?php echo h($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$ties): ?>
    <div class="alert alert-success">No ties right now.</div>
<?php else: ?>
    <?php foreach ($ties as $tie): ?>
        <div class="card-dark p-4 mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="mb-0"><?php echo h($tie['scope']); ?></h5>
                <?php if (!empty($tie['leader']['tie_broken'])): ?>
                    <span class="badge bg-success">Resolved by admin — currently showing <?php echo h($tie['leader']['contestant_name']); ?></span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark">Unresolved — currently showing <?php echo h($tie['leader']['contestant_name']); ?> (deterministic tiebreak, not a real decision)</span>
                <?php endif; ?>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="category_id" value="<?php echo h((string) $tie['category_id']); ?>">
                <input type="hidden" name="gender" value="<?php echo h($tie['gender']); ?>">
                <div class="row g-2 mb-3">
                    <?php foreach ($tie['contenders'] as $name): ?>
                        <?php $c = $contestantsById[$name] ?? null; ?>
                        <div class="col-md-4">
                            <label class="card-dark p-2 d-flex align-items-center gap-2" style="cursor:pointer;">
                                <input type="radio" name="winner_contestant_id" value="<?php echo (int) ($c['id'] ?? 0); ?>" required>
                                <?php if ($c): ?><img src="<?php echo h(asset_url($c['photo'], $config)); ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"><?php endif; ?>
                                <span><?php echo h($name); ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Declare winner</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/partials/footer.php'; ?>