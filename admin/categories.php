<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Manage Categories';
$activePage = 'categories';

$errors = [];
$success = '';
$editCategory = null;
ensure_active_column($pdo, 'categories');
// Widens categories.gender to actually allow 'all' — see the doc comment
// on this function for why this matters: without it, any category meant
// to apply to both genders silently shows nobody at all.
ensure_category_gender_enum($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } else {
    $action = $_POST['action'] ?? 'add';
    if ($action === 'delete') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId > 0) {
            if (has_votes_for($pdo, 'category_id', $categoryId)) {
                // Refuse to hard-delete: categories.votes has ON DELETE
                // CASCADE, so this would silently destroy every historical
                // ballot cast in this category. Archive instead — it
                // disappears from new ballots but historical results still
                // show it correctly.
                $archive = $pdo->prepare('UPDATE categories SET active = 0 WHERE id = ?');
                $archive->execute([$categoryId]);
                log_admin_action($pdo, 'category_archived', "id={$categoryId} reason=has_votes");
                $success = 'This category already has votes recorded, so it was archived (hidden from new ballots) instead of deleted, to protect existing results.';
            } else {
                $delete = $pdo->prepare('DELETE FROM categories WHERE id = ?');
                $delete->execute([$categoryId]);
                log_admin_action($pdo, 'category_deleted', "id={$categoryId}");
                $success = 'Category deleted.';
            }
        }
    } elseif ($action === 'toggle_active') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $newActive = (int) ($_POST['new_active'] ?? 0);
        if ($categoryId > 0) {
            $toggle = $pdo->prepare('UPDATE categories SET active = ? WHERE id = ?');
            $toggle->execute([$newActive ? 1 : 0, $categoryId]);
            log_admin_action($pdo, $newActive ? 'category_reactivated' : 'category_archived', "id={$categoryId}");
            $success = $newActive ? 'Category reactivated.' : 'Category archived.';
        }
    } elseif ($action === 'bulk_set_all') {
        // Quick fix for the common mistake of creating every category as
        // male-only or female-only when "All" (both genders competing
        // side by side) was actually intended — one click instead of
        // editing each category individually.
        $updated = $pdo->exec("UPDATE categories SET gender = 'all' WHERE gender != 'all'");
        log_admin_action($pdo, 'categories_bulk_set_all', "rows_affected={$updated}");
        $success = $updated > 0
            ? "Done — {$updated} categor" . ($updated === 1 ? 'y' : 'ies') . " switched to \"All\" (both genders)."
            : 'Every category was already set to "All".';
    } elseif ($action === 'update') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $gender = $_POST['gender'] ?? '';

        if ($categoryId <= 0) {
            $errors[] = 'Invalid category selected.';
        }
        if ($name === '') {
            $errors[] = 'Category name is required.';
        }
        if (!in_array($gender, ['male', 'female', 'all'], true)) {
            $errors[] = 'Select a valid gender.';
        }

        if (!$errors) {
            $update = $pdo->prepare('UPDATE categories SET name = ?, gender = ? WHERE id = ?');
            $update->execute([$name, $gender, $categoryId]);
            log_admin_action($pdo, 'category_updated', "id={$categoryId} name={$name} gender={$gender}");
            $success = 'Category updated.';
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $gender = $_POST['gender'] ?? '';

        if ($name === '') {
            $errors[] = 'Category name is required.';
        }

        if (!in_array($gender, ['male', 'female', 'all'], true)) {
            $errors[] = 'Select a valid gender.';
        }

        if (!$errors) {
            $insert = $pdo->prepare('INSERT INTO categories (name, gender, active) VALUES (?, ?, 1)');
            $insert->execute([$name, $gender]);
            log_admin_action($pdo, 'category_added', "name={$name} gender={$gender}");
            $success = 'Category added.';
        }
    }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $editStmt->execute([$editId]);
    $editCategory = $editStmt->fetch();
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY active DESC, gender, name')->fetchAll();
$genderRestrictedCount = count(array_filter($categories, fn($c) => in_array($c['gender'] ?? '', ['male', 'female'], true)));

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Categories</h2>
<?php if ($genderRestrictedCount > 0): ?>
    <div class="alert alert-warning">
        <strong><?php echo $genderRestrictedCount; ?></strong> categor<?php echo $genderRestrictedCount === 1 ? 'y is' : 'ies are'; ?>
        currently restricted to one gender. If that's not intentional — most categories in a contest like this should be
        "All" so both genders compete side by side — use the button below to fix every category at once, no SQL needed.
    </div>
<?php endif; ?>
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
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-dark p-4">
            <h4 class="mb-3"><?php echo $editCategory ? 'Edit Category' : 'Add Category'; ?></h4>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="<?php echo $editCategory ? 'update' : 'add'; ?>">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?php echo (int) $editCategory['id']; ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">Category name</label>
                    <input class="form-control" type="text" name="name" value="<?php echo h($editCategory['name'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gender</label>
                    <?php $currentGender = $editCategory ? normalize_category_gender($editCategory['gender'] ?? null) : 'all'; ?>
                    <select class="form-select" name="gender" required>
                        <option value="all" <?php echo $currentGender === 'all' ? 'selected' : ''; ?>>All — both genders compete side by side (recommended for most categories)</option>
                        <option value="male" <?php echo $currentGender === 'male' ? 'selected' : ''; ?>>Male only</option>
                        <option value="female" <?php echo $currentGender === 'female' ? 'selected' : ''; ?>>Female only</option>
                    </select>
                    <small class="text-muted">Only choose Male-only / Female-only for a category that's genuinely restricted to one gender.</small>
                </div>
                <button class="btn btn-primary" type="submit"><?php echo $editCategory ? 'Save Changes' : 'Save Category'; ?></button>
                <?php if ($editCategory): ?>
                    <a class="btn btn-outline-light" href="categories.php">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-dark p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h4 class="mb-0">Current Categories</h4>
                <?php if ($categories): ?>
                    <form method="post" onsubmit="return confirm('Switch every category that isn\'t already \"All\" to \"All\" (both genders)? This can be undone per-category via Edit.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="bulk_set_all">
                        <button class="btn btn-outline-warning btn-sm" type="submit">Set all categories to "All" (both genders)</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (!$categories): ?>
                <p class="text-muted">No categories yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <?php
                                $isActive = (int) ($category['active'] ?? 1) === 1;
                                $rawGender = (string) ($category['gender'] ?? '');
                                $isCorrupted = !in_array($rawGender, ['male', 'female', 'all'], true);
                                ?>
                                <tr<?php echo $isActive ? '' : ' style="opacity:.6;"'; ?>>
                                    <td><?php echo h($category['name']); ?></td>
                                    <td>
                                        <?php if ($isCorrupted): ?>
                                            <span class="badge bg-danger" title="This category's gender value is not a recognized male/female/all — currently treated as 'all' (inclusive) by the app, but click Edit and re-save to fix it properly.">⚠ unrecognized (treated as All)</span>
                                        <?php else: ?>
                                            <?php echo h($rawGender); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $isActive
                                            ? '<span class="badge bg-success">Active</span>'
                                            : '<span class="badge bg-secondary">Archived</span>'; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-light btn-sm" href="categories.php?edit=<?php echo (int) $category['id']; ?>">Edit</a>
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="category_id" value="<?php echo (int) $category['id']; ?>">
                                            <input type="hidden" name="new_active" value="<?php echo $isActive ? '0' : '1'; ?>">
                                            <button class="btn btn-outline-light btn-sm" type="submit"><?php echo $isActive ? 'Archive' : 'Reactivate'; ?></button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this category? If it already has votes it will be archived instead.');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?php echo (int) $category['id']; ?>">
                                            <button class="btn btn-outline-light btn-sm" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
