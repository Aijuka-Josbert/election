<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Manage Categories - UMU Varsity Ball';
$activePage = 'categories';

$errors = [];
$success = '';
ensure_active_column($pdo, 'categories');

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

$categories = $pdo->query('SELECT * FROM categories ORDER BY active DESC, gender, name')->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Categories</h2>
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
            <h4 class="mb-3">Add Category</h4>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label class="form-label">Category name</label>
                    <input class="form-control" type="text" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender" required>
                        <option value="">Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="all">All (for categories that apply to all contestants)</option>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Save Category</button>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-dark p-4">
            <h4 class="mb-3">Current Categories</h4>
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
                                <?php $isActive = (int) ($category['active'] ?? 1) === 1; ?>
                                <tr<?php echo $isActive ? '' : ' style="opacity:.6;"'; ?>>
                                    <td><?php echo h($category['name']); ?></td>
                                    <td><?php echo h($category['gender']); ?></td>
                                    <td>
                                        <?php echo $isActive
                                            ? '<span class="badge bg-success">Active</span>'
                                            : '<span class="badge bg-secondary">Archived</span>'; ?>
                                    </td>
                                    <td class="text-end">
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
