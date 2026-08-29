<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
$configPath = __DIR__ . '/../config/config.php';
$localConfigPath = __DIR__ . '/../config/config.local.php';
$config = require $configPath;

$pageTitle = 'Settings - UMU Varsity Ball';
$activePage = 'settings';

$success = '';
$errors = [];
$warnings = [];
$settingsWritable = isset($pdo);
$settingsMode = 'database';

if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$settingsWritable) {
        $errors[] = 'Settings are locked. Database connection is unavailable.';
    } else {
    $eventDate = trim($_POST['event_date'] ?? '');
    $eventTime = trim($_POST['event_time'] ?? '');
    $tzName = $config['app']['timezone'] ?? 'UTC';
    $tz = new DateTimeZone($tzName);
    $config['app']['voting_open'] = !empty($_POST['voting_open']);
    $config['app']['results_public'] = !empty($_POST['results_public']);
    $config['app']['voting_mode'] = ($_POST['voting_mode'] ?? 'rating') === 'simple' ? 'simple' : 'rating';
    $startDate = trim($_POST['voting_start_date'] ?? '');
    $startTime = trim($_POST['voting_start_time'] ?? '');
    $endDate = trim($_POST['voting_end_date'] ?? '');
    $endTime = trim($_POST['voting_end_time'] ?? '');

    $resolvedStartTime = $startTime !== '' ? $startTime : '00:00';
    $resolvedEndTime = $endTime !== '' ? $endTime : '00:00';
    $resolvedEventTime = $eventTime !== '' ? $eventTime : '00:00';

    $config['app']['voting_start'] = $startDate
        ? $startDate . ' ' . $resolvedStartTime . ':00'
        : '';
    $config['app']['voting_end'] = $endDate
        ? $endDate . ' ' . $resolvedEndTime . ':00'
        : '';
    $config['app']['event_date'] = $eventDate
        ? $eventDate . ' ' . $resolvedEventTime . ':00'
        : '';

    if ($config['app']['voting_start'] !== '' && $config['app']['voting_end'] !== '') {
        $startCheck = new DateTime($config['app']['voting_start'], $tz);
        $endCheck = new DateTime($config['app']['voting_end'], $tz);
        if ($endCheck <= $startCheck) {
            $endCheck->modify('+1 day');
            $config['app']['voting_end'] = $endCheck->format('Y-m-d H:i:s');
            $warnings[] = 'Voting end time was before start time. End date was moved to the next day.';
        }
    }

    $saveOk = save_app_settings($pdo, [
        'event_date' => $config['app']['event_date'] ?? '',
        'voting_open' => !empty($config['app']['voting_open']) ? '1' : '0',
        'voting_start' => $config['app']['voting_start'] ?? '',
        'voting_end' => $config['app']['voting_end'] ?? '',
        'voting_mode' => $config['app']['voting_mode'] ?? 'rating',
        'results_public' => !empty($config['app']['results_public']) ? '1' : '0',
    ]);

    if (!$saveOk) {
        $errors[] = 'Unable to save settings to the database.';
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

$statusResult = voting_status_message($config);
$statusMessage = $statusResult['message'];
$statusClass = $statusResult['open'] ? 'alert-success' : 'alert-warning';
$votingModeValue = get_voting_mode($config);
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
<?php if ($warnings): ?>
    <div class="alert alert-warning">
        <?php foreach ($warnings as $warning): ?>
            <div><?php echo h($warning); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if (!$settingsWritable): ?>
    <div class="alert alert-warning">Settings are locked. Database connection is unavailable.</div>
<?php endif; ?>
<div class="alert <?php echo h($statusClass); ?>"><?php echo h($statusMessage); ?></div>
<div class="card-dark p-4">
    <form method="post">
        <?php echo csrf_field(); ?>
        <div class="mb-4">
            <label class="form-label d-block">Voting workflow</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="voting_mode" id="voting_mode_rating" value="rating" <?php echo $votingModeValue === 'rating' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="voting_mode_rating">
                    <strong>Rating ballot (current)</strong> — voters rate every contestant 1–5 in every category, one submit at the end.
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="voting_mode" id="voting_mode_simple" value="simple" <?php echo $votingModeValue === 'simple' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="voting_mode_simple">
                    <strong>Simple ballot (one-click)</strong> — voters pick one contestant per category and tap "Vote Now" once. No ratings.
                </label>
            </div>
            <small class="text-muted d-block mt-1">Switching this only changes how the ballot is presented — existing votes already cast are not affected or deleted.</small>
        </div>
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
        <button class="btn btn-primary mt-4" type="submit" <?php echo !$settingsWritable ? 'disabled' : ''; ?>>Save Settings</button>
    </form>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
