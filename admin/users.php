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
        } elseif ($action === 'reset_ballot') {
            // Replaces the manual "DELETE FROM votes WHERE user_id = ?;
            // UPDATE users SET has_voted = 0" a real admin would otherwise
            // need direct database access to run — same effect, but through
            // an audited, confirmed admin action instead of raw SQL. Meant
            // for correcting test votes / mistaken submissions, not for
            // letting someone "vote again" in a live election — it's logged
            // with who did it and when either way.
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                $pdo->beginTransaction();
                try {
                    $del = $pdo->prepare('DELETE FROM votes WHERE user_id = ?');
                    $del->execute([$userId]);
                    $rowsDeleted = $del->rowCount();
                    $upd = $pdo->prepare('UPDATE users SET has_voted = 0 WHERE id = ?');
                    $upd->execute([$userId]);
                    $pdo->commit();
                    log_admin_action($pdo, 'user_ballot_reset', "id={$userId} votes_removed={$rowsDeleted}");
                    $success = "Ballot reset — {$rowsDeleted} vote row(s) removed. This user can vote again.";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $errors[] = 'Unable to reset this ballot.';
                }
            }
        }
    }
}

$users = $pdo->query(
    'SELECT u.id, u.name, u.email, u.has_voted, u.created_at,
            (SELECT COUNT(*) FROM votes v WHERE v.user_id = u.id) AS vote_rows
     FROM users u
     ORDER BY u.has_voted DESC, u.created_at DESC'
)->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Users</h2>
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
        <p class="text-muted">No users yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php $hasVoted = (int) $user['has_voted'] === 1; ?>
                        <tr>
                            <td><?php echo h($user['name']); ?></td>
                            <td><?php echo h($user['email']); ?></td>
                            <td>
                                <?php if ($hasVoted): ?>
                                    <span class="badge bg-success">Voted</span>
                                    <small class="text-muted d-block"><?php echo (int) $user['vote_rows']; ?> vote row(s)</small>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not voted</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h($user['created_at']); ?></td>
                            <td class="text-end">
                                <?php if ($hasVoted): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete every vote this user cast and let them vote again? This cannot be undone.');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="reset_ballot">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                        <button class="btn btn-outline-warning btn-sm" type="submit">Reset ballot</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                        <button class="btn btn-outline-light btn-sm" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>