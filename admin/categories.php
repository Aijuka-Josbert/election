<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Manage Categories - UMU Varsity Ball';
$activePage = 'categories';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    if ($action === 'delete') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId > 0) {
            $delete = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $delete->execute([$categoryId]);
            $success = 'Category deleted.';
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
            $insert = $pdo->prepare('INSERT INTO categories (name, gender) VALUES (?, ?)');
            $insert->execute([$name, $gender]);
            $success = 'Category added.';
        }
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY gender, name')->fetchAll();

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
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo h($category['name']); ?></td>
                                    <td><?php echo h($category['gender']); ?></td>
                                    <td class="text-end">
                                        <form method="post">
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
