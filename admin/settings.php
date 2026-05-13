<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;

$pageTitle = 'Settings - UMU Varsity Ball';
$activePage = 'settings';

$success = '';
$errors = [];
$isWritable = is_writable($configPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isWritable) {
        $errors[] = 'Settings are locked in production. Edit config.php manually.';
    } else {
    $eventDate = trim($_POST['event_date'] ?? '');
    $eventTime = trim($_POST['event_time'] ?? '');
    $config['app']['voting_open'] = !empty($_POST['voting_open']);
    $config['app']['results_public'] = !empty($_POST['results_public']);
    $startDate = trim($_POST['voting_start_date'] ?? '');
    $startTime = trim($_POST['voting_start_time'] ?? '');
    $endDate = trim($_POST['voting_end_date'] ?? '');
    $endTime = trim($_POST['voting_end_time'] ?? '');

    $config['app']['voting_start'] = ($startDate && $startTime)
        ? $startDate . ' ' . $startTime . ':00'
        : '';
    $config['app']['voting_end'] = ($endDate && $endTime)
        ? $endDate . ' ' . $endTime . ':00'
        : '';
    $config['app']['event_date'] = ($eventDate && $eventTime)
        ? $eventDate . ' ' . $eventTime . ':00'
        : ($config['app']['event_date'] ?? '');

    $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";

    if (file_put_contents($configPath, $content) === false) {
        $errors[] = 'Unable to save settings.';
    } else {
        $success = 'Settings updated.';
    }
    }
}

require_once __DIR__ . '/partials/header.php';

$startValue = '';
$endValue = '';
$startDateValue = '';
$startTimeValue = '';
$endDateValue = '';
$endTimeValue = '';
$eventDateValue = '';
$eventTimeValue = '';
if (!empty($config['app']['voting_start'])) {
    $startDateValue = substr($config['app']['voting_start'], 0, 10);
    $startTimeValue = substr($config['app']['voting_start'], 11, 5);
}
if (!empty($config['app']['voting_end'])) {
    $endDateValue = substr($config['app']['voting_end'], 0, 10);
    $endTimeValue = substr($config['app']['voting_end'], 11, 5);
}
if (!empty($config['app']['event_date'])) {
    $eventDateValue = substr($config['app']['event_date'], 0, 10);
    $eventTimeValue = substr($config['app']['event_date'], 11, 5);
}
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
<?php if (!$isWritable): ?>
    <div class="alert alert-warning">Settings are locked in production. Edit config.php manually on your host.</div>
<?php endif; ?>
<div class="card-dark p-4">
    <form method="post">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Event date</label>
                <input class="form-control" type="date" name="event_date" value="<?php echo h($eventDateValue); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Event time</label>
                <input class="form-control" type="time" name="event_time" value="<?php echo h($eventTimeValue); ?>">
            </div>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="voting_open" name="voting_open" <?php echo !empty($config['app']['voting_open']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="voting_open">Voting enabled</label>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Voting start date</label>
                <input class="form-control" type="date" name="voting_start_date" value="<?php echo h($startDateValue); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Voting start time</label>
                <input class="form-control" type="time" name="voting_start_time" value="<?php echo h($startTimeValue); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Voting end date</label>
                <input class="form-control" type="date" name="voting_end_date" value="<?php echo h($endDateValue); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Voting end time</label>
                <input class="form-control" type="time" name="voting_end_time" value="<?php echo h($endTimeValue); ?>">
            </div>
        </div>
        <small class="text-muted d-block mt-2">If both start and end are set, voting is open only within that window.</small>
        <div class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" id="results_public" name="results_public" <?php echo !empty($config['app']['results_public']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="results_public">Make results visible to everyone</label>
        </div>
        <button class="btn btn-primary mt-4" type="submit" <?php echo !$isWritable ? 'disabled' : ''; ?>>Save Settings</button>
    </form>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
