<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Users';
$activePage = 'users';

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } else {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $delete = $pdo->prepare('DELETE FROM users WHERE id = ? AND has_voted = 0');
            $delete->execute([$userId]);
            if ($delete->rowCount() > 0) {
                log_admin_action($pdo, 'user_deleted', "id={$userId}");
                $success = 'User removed.';
            } else {
                $errors[] = 'Unable to delete user. They may have voted already.';
            }
        }
    }
    }
}

$users = $pdo->query(
    'SELECT u.id, u.name, u.email, u.created_at
     FROM users u
     LEFT JOIN votes v ON v.user_id = u.id
     WHERE v.id IS NULL
     ORDER BY u.created_at DESC'
)->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Users (No Votes)</h2>
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
<div class="card-dark p-4">
    <?php if (!$users): ?>
        <p class="text-muted">No users without votes.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Registered</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo h($user['name']); ?></td>
                            <td><?php echo h($user['email']); ?></td>
                            <td><?php echo h($user['created_at']); ?></td>
                            <td class="text-end">
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
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
<?php require_once __DIR__ . '/partials/footer.php'; ?>
