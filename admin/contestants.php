<?php
/*
 * admin/contestants.php
 * Admin UI for adding, updating and deleting contestants. Handles file
 * uploads with validations and cleans up old files when a photo is replaced.
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$config = require __DIR__ . '/../config/config.php';

$pageTitle = 'Manage Contestants';
$activePage = 'contestants';

$errors = [];
$success = '';
$editContestant = null;
ensure_active_column($pdo, 'contestants');

// Handle create / update / delete actions submitted by the admin form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } else {
    $action = $_POST['action'] ?? 'add';

    if ($action === 'delete') {
        // Delete contestant record and remove the photo if it exists under uploads
        $contestantId = (int) ($_POST['contestant_id'] ?? 0);
        if ($contestantId > 0) {
            if (has_votes_for($pdo, 'contestant_id', $contestantId)) {
                // Refuse to hard-delete: contestants.votes has ON DELETE
                // CASCADE, so this would silently destroy every historical
                // ballot cast for this contestant. Archive instead.
                $archive = $pdo->prepare('UPDATE contestants SET active = 0 WHERE id = ?');
                $archive->execute([$contestantId]);
                log_admin_action($pdo, 'contestant_archived', "id={$contestantId} reason=has_votes");
                $success = 'This contestant already has votes recorded, so they were archived (hidden from new ballots) instead of deleted, to protect existing results.';
            } else {
                $stmt = $pdo->prepare('SELECT photo FROM contestants WHERE id = ?');
                $stmt->execute([$contestantId]);
                $contestant = $stmt->fetch();

                $delete = $pdo->prepare('DELETE FROM contestants WHERE id = ?');
                $delete->execute([$contestantId]);
                log_admin_action($pdo, 'contestant_deleted', "id={$contestantId}");

                if ($contestant && !empty($contestant['photo'])) {
                    $photoPath = realpath(__DIR__ . '/../' . $contestant['photo']);
                    $uploadsPath = realpath($config['uploads']['contestants_dir']);
                    if ($photoPath && $uploadsPath && strpos($photoPath, $uploadsPath) === 0) {
                        @unlink($photoPath);
                    }
                }

                $success = 'Contestant deleted.';
            }
        }
    } elseif ($action === 'toggle_active') {
        $contestantId = (int) ($_POST['contestant_id'] ?? 0);
        $newActive = (int) ($_POST['new_active'] ?? 0);
        if ($contestantId > 0) {
            $toggle = $pdo->prepare('UPDATE contestants SET active = ? WHERE id = ?');
            $toggle->execute([$newActive ? 1 : 0, $contestantId]);
            log_admin_action($pdo, $newActive ? 'contestant_reactivated' : 'contestant_archived', "id={$contestantId}");
            $success = $newActive ? 'Contestant reactivated.' : 'Contestant archived.';
        }
    } elseif ($action === 'update') {
        // Update existing contestant: validate inputs and optional new photo
        $contestantId = (int) ($_POST['contestant_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $bio = trim($_POST['bio'] ?? '');
        $photo = $_FILES['photo'] ?? null;
        $uploadDir = $config['uploads']['contestants_dir'];

        if ($contestantId <= 0) {
            $errors[] = 'Invalid contestant selected.';
        }

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if (!in_array($gender, ['male', 'female'], true)) {
            $errors[] = 'Select a valid gender.';
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            $errors[] = "Uploads folder is missing and could not be created at: {$uploadDir} — the web server process needs write permission on its parent directory. On Linux: sudo chown -R www-data:www-data " . dirname($uploadDir) . " && sudo chmod -R 775 " . dirname($uploadDir) . " (replace www-data with your actual web server user if different).";
        } elseif (!is_writable($uploadDir)) {
            // Self-heal attempt: if the PHP process actually owns this
            // directory but the permission bits are just wrong (a common
            // misconfiguration — e.g. it was created 755 by a deploy
            // script running as a different user, or as 644), PHP can
            // fix that itself. This only succeeds when PHP already owns
            // the directory; it's a no-op (silently fails) when the
            // problem is ownership, which still needs the server command
            // below run once, manually, by whoever controls the server.
            @chmod($uploadDir, 0775);
            clearstatcache(true, $uploadDir);
            if (!is_writable($uploadDir)) {
                $errors[] = "Uploads folder exists but is not writable by the web server at: {$uploadDir} — fix with: sudo chown -R www-data:www-data {$uploadDir} && sudo chmod -R 775 {$uploadDir} (replace www-data with your actual web server user if different).";
            }
        }

        $newPhotoPath = null;
        if ($photo && $photo['error'] !== UPLOAD_ERR_NO_FILE) {
            // Validate uploaded file (error, size, mime)
            if ($photo['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Photo upload failed. Please choose a valid image.';
            } elseif ($photo['size'] > $config['uploads']['max_size']) {
                $errors[] = 'Photo is too large. Max 2MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($photo['tmp_name']);
                if (!in_array($mimeType, $config['uploads']['allowed_types'], true)) {
                    $errors[] = 'Only JPG, PNG, or WEBP images are allowed.';
                }
            }
        }

        if (!$errors) {
            $existingStmt = $pdo->prepare('SELECT photo FROM contestants WHERE id = ?');
            $existingStmt->execute([$contestantId]);
            $existing = $existingStmt->fetch();

            if ($photo && $photo['error'] !== UPLOAD_ERR_NO_FILE) {
                $extensionMap = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                $ext = $extensionMap[$mimeType] ?? 'jpg';
                $fileName = uniqid('contestant_', true) . '.' . $ext;
                $destination = $config['uploads']['contestants_dir'] . '/' . $fileName;

                if (!move_uploaded_file($photo['tmp_name'], $destination)) {
                    $errors[] = 'Unable to save the photo.';
                } else {
                    $newPhotoPath = $config['uploads']['contestants_url'] . '/' . $fileName;
                }
            }

            if (!$errors) {
                $photoToSave = $newPhotoPath ?: ($existing['photo'] ?? '');
                $update = $pdo->prepare('UPDATE contestants SET name = ?, gender = ?, photo = ?, bio = ? WHERE id = ?');
                $update->execute([$name, $gender, $photoToSave, $bio ?: null, $contestantId]);

                // Remove previous photo file when replaced
                if ($newPhotoPath && $existing && !empty($existing['photo'])) {
                    $photoPath = realpath(__DIR__ . '/../' . $existing['photo']);
                    $uploadsPath = realpath($config['uploads']['contestants_dir']);
                    if ($photoPath && $uploadsPath && strpos($photoPath, $uploadsPath) === 0) {
                        @unlink($photoPath);
                    }
                }

                $success = 'Contestant updated.';
                log_admin_action($pdo, 'contestant_updated', "id={$contestantId} name={$name}");
            }
        }
    } else {
        // Add new contestant (requires photo)
        $name = trim($_POST['name'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $bio = trim($_POST['bio'] ?? '');
        $photo = $_FILES['photo'] ?? null;
        $uploadDir = $config['uploads']['contestants_dir'];

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if (!in_array($gender, ['male', 'female'], true)) {
            $errors[] = 'Select a valid gender.';
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            $errors[] = "Uploads folder is missing and could not be created at: {$uploadDir} — the web server process needs write permission on its parent directory. On Linux: sudo chown -R www-data:www-data " . dirname($uploadDir) . " && sudo chmod -R 775 " . dirname($uploadDir) . " (replace www-data with your actual web server user if different).";
        } elseif (!is_writable($uploadDir)) {
            // Self-heal attempt: if the PHP process actually owns this
            // directory but the permission bits are just wrong (a common
            // misconfiguration — e.g. it was created 755 by a deploy
            // script running as a different user, or as 644), PHP can
            // fix that itself. This only succeeds when PHP already owns
            // the directory; it's a no-op (silently fails) when the
            // problem is ownership, which still needs the server command
            // below run once, manually, by whoever controls the server.
            @chmod($uploadDir, 0775);
            clearstatcache(true, $uploadDir);
            if (!is_writable($uploadDir)) {
                $errors[] = "Uploads folder exists but is not writable by the web server at: {$uploadDir} — fix with: sudo chown -R www-data:www-data {$uploadDir} && sudo chmod -R 775 {$uploadDir} (replace www-data with your actual web server user if different).";
            }
        }

        // Validate photo presence and type
        if (!$photo || $photo['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Photo upload failed. Please choose a valid image.';
        } elseif ($photo['size'] > $config['uploads']['max_size']) {
            $errors[] = 'Photo is too large. Max 2MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($photo['tmp_name']);
            if (!in_array($mimeType, $config['uploads']['allowed_types'], true)) {
                $errors[] = 'Only JPG, PNG, or WEBP images are allowed.';
            }
        }

        if (!$errors) {
            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            $ext = $extensionMap[$mimeType] ?? 'jpg';
            $fileName = uniqid('contestant_', true) . '.' . $ext;
            $destination = $config['uploads']['contestants_dir'] . '/' . $fileName;

            if (!move_uploaded_file($photo['tmp_name'], $destination)) {
                $errors[] = 'Unable to save the photo.';
            } else {
                $photoPath = $config['uploads']['contestants_url'] . '/' . $fileName;
                $insert = $pdo->prepare('INSERT INTO contestants (name, gender, photo, bio, active) VALUES (?, ?, ?, ?, 1)');
                $insert->execute([$name, $gender, $photoPath, $bio ?: null]);
                log_admin_action($pdo, 'contestant_added', "name={$name} gender={$gender}");
                $success = 'Contestant added.';
            }
        }
    }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM contestants WHERE id = ?');
    $editStmt->execute([$editId]);
    $editContestant = $editStmt->fetch();
}

$contestants = $pdo->query('SELECT * FROM contestants ORDER BY active DESC, gender, name')->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Contestants</h2>
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
            <h4 class="mb-3">Add Contestant</h4>
            <form method="post" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="<?php echo $editContestant ? 'update' : 'add'; ?>">
                <?php if ($editContestant): ?>
                    <input type="hidden" name="contestant_id" value="<?php echo (int) $editContestant['id']; ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" type="text" name="name" value="<?php echo h($editContestant['name'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender" required>
                        <option value="">Select gender</option>
                        <option value="male" <?php echo (($editContestant['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo (($editContestant['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bio (optional)</label>
                    <textarea class="form-control" name="bio" rows="3"><?php echo h($editContestant['bio'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Photo</label>
                    <input class="form-control" type="file" name="photo" accept="image/*" <?php echo $editContestant ? '' : 'required'; ?>>
                    <?php if ($editContestant && !empty($editContestant['photo'])): ?>
                        <small class="text-muted">Leave blank to keep current photo.</small>
                    <?php endif; ?>
                </div>
                <button class="btn btn-primary" type="submit">
                    <?php echo $editContestant ? 'Update Contestant' : 'Save Contestant'; ?>
                </button>
                <?php if ($editContestant): ?>
                    <a class="btn btn-outline-light ms-2" href="contestants.php">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-dark p-4">
            <h4 class="mb-3">Current Contestants</h4>
            <?php if (!$contestants): ?>
                <p class="text-muted">No contestants yet.</p>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($contestants as $contestant): ?>
                        <?php $isActive = (int) ($contestant['active'] ?? 1) === 1; ?>
                        <div class="col-md-6">
                            <div class="card-dark p-3 h-100"<?php echo $isActive ? '' : ' style="opacity:.6;"'; ?>>
                                <img class="contestant-img" src="<?php echo h(asset_url($contestant['photo'], $config)); ?>" alt="<?php echo h($contestant['name']); ?>">
                                <div class="mt-2">
                                    <strong><?php echo h($contestant['name']); ?></strong>
                                    <div class="text-muted text-uppercase">
                                        <?php echo h($contestant['gender']); ?>
                                        <?php echo $isActive
                                            ? '<span class="badge bg-success ms-1">Active</span>'
                                            : '<span class="badge bg-secondary ms-1">Archived</span>'; ?>
                                    </div>
                                </div>
                                <form method="post" class="mt-3 d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="contestant_id" value="<?php echo (int) $contestant['id']; ?>">
                                    <input type="hidden" name="new_active" value="<?php echo $isActive ? '0' : '1'; ?>">
                                    <button class="btn btn-outline-light btn-sm" type="submit"><?php echo $isActive ? 'Archive' : 'Reactivate'; ?></button>
                                </form>
                                <form method="post" class="mt-3 d-inline" onsubmit="return confirm('Delete this contestant? If they already have votes they will be archived instead.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="contestant_id" value="<?php echo (int) $contestant['id']; ?>">
                                    <button class="btn btn-outline-light btn-sm" type="submit">Delete</button>
                                </form>
                                <a class="btn btn-outline-light btn-sm mt-2" href="contestants.php?edit=<?php echo (int) $contestant['id']; ?>">Edit</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
