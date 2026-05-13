<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;

$pageTitle = 'Settings - UMU Varsity Ball';
$activePage = 'settings';

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config['app']['voting_open'] = !empty($_POST['voting_open']);
    $config['app']['results_public'] = !empty($_POST['results_public']);
    $config['app']['voting_start'] = trim($_POST['voting_start'] ?? '');
    $config['app']['voting_end'] = trim($_POST['voting_end'] ?? '');

    $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";

    if (!is_writable($configPath)) {
        $errors[] = 'Config file is not writable.';
    } elseif (file_put_contents($configPath, $content) === false) {
        $errors[] = 'Unable to save settings.';
    } else {
        $success = 'Settings updated.';
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<h2 class="mb-4">Voting Settings</h2>
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
    <form method="post">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="voting_open" name="voting_open" <?php echo !empty($config['app']['voting_open']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="voting_open">Voting enabled</label>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Voting start (YYYY-MM-DD HH:MM:SS)</label>
                <input class="form-control" type="text" name="voting_start" value="<?php echo h($config['app']['voting_start'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Voting end (YYYY-MM-DD HH:MM:SS)</label>
                <input class="form-control" type="text" name="voting_end" value="<?php echo h($config['app']['voting_end'] ?? ''); ?>">
            </div>
        </div>
        <small class="text-muted d-block mt-2">If both start and end are set, voting is open only within that window.</small>
        <div class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" id="results_public" name="results_public" <?php echo !empty($config['app']['results_public']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="results_public">Make results visible to everyone</label>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save Settings</button>
    </form>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
