<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$config = require __DIR__ . '/../config/config.php';

$pageTitle = 'Manage Contestants - UMU Varsity Ball';
$activePage = 'contestants';

$errors = [];
$success = '';
$editContestant = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    if ($action === 'delete') {
        $contestantId = (int) ($_POST['contestant_id'] ?? 0);
        if ($contestantId > 0) {
            $stmt = $pdo->prepare('SELECT photo FROM contestants WHERE id = ?');
            $stmt->execute([$contestantId]);
            $contestant = $stmt->fetch();

            $delete = $pdo->prepare('DELETE FROM contestants WHERE id = ?');
            $delete->execute([$contestantId]);

            if ($contestant && !empty($contestant['photo'])) {
                $photoPath = realpath(__DIR__ . '/../' . $contestant['photo']);
                $uploadsPath = realpath($config['uploads']['contestants_dir']);
                if ($photoPath && $uploadsPath && strpos($photoPath, $uploadsPath) === 0) {
                    @unlink($photoPath);
                }
            }

            $success = 'Contestant deleted.';
        }
    } elseif ($action === 'update') {
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
            $errors[] = 'Uploads folder is missing or not writable.';
        } elseif (!is_writable($uploadDir)) {
            $errors[] = 'Uploads folder is not writable.';
        }

        $newPhotoPath = null;
        if ($photo && $photo['error'] !== UPLOAD_ERR_NO_FILE) {
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

                if ($newPhotoPath && $existing && !empty($existing['photo'])) {
                    $photoPath = realpath(__DIR__ . '/../' . $existing['photo']);
                    $uploadsPath = realpath($config['uploads']['contestants_dir']);
                    if ($photoPath && $uploadsPath && strpos($photoPath, $uploadsPath) === 0) {
                        @unlink($photoPath);
                    }
                }

                $success = 'Contestant updated.';
            }
        }
    } else {
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
            $errors[] = 'Uploads folder is missing or not writable.';
        } elseif (!is_writable($uploadDir)) {
            $errors[] = 'Uploads folder is not writable.';
        }

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
                $insert = $pdo->prepare('INSERT INTO contestants (name, gender, photo, bio) VALUES (?, ?, ?, ?)');
                $insert->execute([$name, $gender, $photoPath, $bio ?: null]);
                $success = 'Contestant added.';
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

$contestants = $pdo->query('SELECT * FROM contestants ORDER BY gender, name')->fetchAll();

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
                        <div class="col-md-6">
                            <div class="card-dark p-3 h-100">
                                <img class="contestant-img" src="<?php echo h(asset_url($contestant['photo'], $config)); ?>" alt="<?php echo h($contestant['name']); ?>">
                                <div class="mt-2">
                                    <strong><?php echo h($contestant['name']); ?></strong>
                                    <div class="text-muted text-uppercase"><?php echo h($contestant['gender']); ?></div>
                                </div>
                                <form method="post" class="mt-3">
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
