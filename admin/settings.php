<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
$configPath = __DIR__ . '/../config/config.php';
$localConfigPath = __DIR__ . '/../config/config.local.php';
$config = require $configPath;

$pageTitle = 'Settings';
$activePage = 'settings';

$success = '';
$errors = [];
$warnings = [];
$settingsWritable = isset($pdo);
$settingsMode = 'database';

if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'anonymize_ballots') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$settingsWritable) {
        $errors[] = 'Settings are locked. Database connection is unavailable.';
    } elseif (!empty($config['app']['voting_open'] ?? false)) {
        $errors[] = 'Close voting before anonymizing ballots — dedup protection still needs the voter link while voting is open.';
    } elseif (($_POST['confirm_anonymize'] ?? '') !== 'ANONYMIZE') {
        $errors[] = 'Type ANONYMIZE exactly to confirm this irreversible action.';
    } else {
        $rowsAffected = anonymize_ballots($pdo);
        log_admin_action($pdo, 'ballots_anonymized', "rows_affected={$rowsAffected}");
        $success = "Done — {$rowsAffected} vote row(s) had their voter link removed. Results are unaffected; this cannot be undone.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!$settingsWritable) {
        $errors[] = 'Settings are locked. Database connection is unavailable.';
    } elseif (!rate_limit_allow($pdo, rate_limit_client_bucket('admin_settings'), 30, 60)) {
        $errors[] = 'Too many attempts. Please wait a moment and try again.';
    } else {
    // Snapshot before mutation, for the audit log below.
    $previousVotingMode = $config['app']['voting_mode'] ?? 'rating';
    $previousVotingOpen = !empty($config['app']['voting_open']);
    $previousResultsPublic = !empty($config['app']['results_public']);

    $eventDate = trim($_POST['event_date'] ?? '');
    $eventTime = trim($_POST['event_time'] ?? '');
    $tzName = $config['app']['timezone'] ?? 'UTC';
    $tz = new DateTimeZone($tzName);
    $config['app']['voting_open'] = !empty($_POST['voting_open']);
    $config['app']['results_public'] = !empty($_POST['results_public']);
    $config['app']['voting_mode'] = ($_POST['voting_mode'] ?? 'rating') === 'simple' ? 'simple' : 'rating';

    // Branding — every field here is optional; leaving one blank falls
    // back to the app's built-in default (see site_name()/site_male_title()
    // etc. in includes/helpers.php), it never breaks the page.
    $config['app']['event_name'] = trim($_POST['event_name'] ?? '');
    $config['app']['event_tagline'] = trim($_POST['event_tagline'] ?? '');
    $config['app']['male_title'] = trim($_POST['male_title'] ?? '');
    $config['app']['female_title'] = trim($_POST['female_title'] ?? '');

    // Logo: an uploaded file (if provided) takes priority over the URL
    // field. Uploading compresses/resizes the same way contestant photos
    // do, then stores the resulting local path in app_settings.logo_url
    // — the same field the URL text input writes to, so
    // site_logo_url()/site_logo_data_uri() don't need to know which
    // source it came from.
    $logoUrlInput = trim($_POST['logo_url'] ?? '');
    if (!empty($_FILES['logo_upload']['name']) && ($_FILES['logo_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $logoFile = $_FILES['logo_upload'];
        if ($logoFile['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Logo upload failed. Please try again.';
        } else {
            $logoMime = @mime_content_type($logoFile['tmp_name']);
            $logoExtMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($logoExtMap[$logoMime])) {
                $errors[] = 'Logo must be a JPG, PNG, or WEBP image.';
            } else {
                $logoDir = __DIR__ . '/../uploads/branding';
                if (!is_dir($logoDir) && !mkdir($logoDir, 0775, true)) {
                    $errors[] = "Branding uploads folder could not be created at: {$logoDir} — same fix as the contestants uploads folder: sudo chown -R www-data:www-data " . dirname($logoDir) . " && sudo chmod -R 775 " . dirname($logoDir) . ".";
                } else {
                    @chmod($logoDir, 0775);
                    if (!is_writable($logoDir)) {
                        $errors[] = "Branding uploads folder exists but is not writable at: {$logoDir} — fix with: sudo chown -R www-data:www-data {$logoDir} && sudo chmod -R 775 {$logoDir}.";
                    } else {
                        $logoFileName = 'logo_' . uniqid('', true) . '.' . $logoExtMap[$logoMime];
                        $logoDestination = $logoDir . '/' . $logoFileName;
                        if (!move_uploaded_file($logoFile['tmp_name'], $logoDestination)) {
                            $errors[] = 'Unable to save the uploaded logo.';
                        } else {
                            compress_uploaded_image($logoDestination, 800, 85); // logos are small/simple — 800px is plenty
                            $logoUrlInput = 'uploads/branding/' . $logoFileName;
                        }
                    }
                }
            }
        }
    }

    if ($logoUrlInput !== '' && !preg_match('#^https?://#i', $logoUrlInput) && !str_starts_with($logoUrlInput, '/') && !str_starts_with($logoUrlInput, 'uploads/')) {
        $errors[] = 'Logo URL must start with http://, https://, or / (a path on this site).';
    } elseif (!$errors) {
        $config['app']['logo_url'] = $logoUrlInput;
    }
    foreach (['theme_primary_color' => 'Primary color', 'theme_accent_color' => 'Accent color', 'theme_background_color' => 'Background color', 'theme_text_color' => 'Text color'] as $colorField => $label) {
        $colorInput = trim($_POST[$colorField] ?? '');
        if ($colorInput !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $colorInput)) {
            $errors[] = "{$label} must be a hex color like #c8102e.";
        } else {
            $config['app'][$colorField] = $colorInput;
        }
    }

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

    $saveOk = false;
    if (!$errors) {
        $saveOk = save_app_settings($pdo, [
            'event_date' => $config['app']['event_date'] ?? '',
            'voting_open' => !empty($config['app']['voting_open']) ? '1' : '0',
            'voting_start' => $config['app']['voting_start'] ?? '',
            'voting_end' => $config['app']['voting_end'] ?? '',
            'voting_mode' => $config['app']['voting_mode'] ?? 'rating',
            'results_public' => !empty($config['app']['results_public']) ? '1' : '0',
            'event_name' => $config['app']['event_name'] ?? '',
            'event_tagline' => $config['app']['event_tagline'] ?? '',
            'male_title' => $config['app']['male_title'] ?? '',
            'female_title' => $config['app']['female_title'] ?? '',
            'logo_url' => $config['app']['logo_url'] ?? '',
            'theme_primary_color' => $config['app']['theme_primary_color'] ?? '',
            'theme_accent_color' => $config['app']['theme_accent_color'] ?? '',
            'theme_background_color' => $config['app']['theme_background_color'] ?? '',
            'theme_text_color' => $config['app']['theme_text_color'] ?? '',
        ]);
    }

    if (!$saveOk) {
        $errors[] = 'Unable to save settings to the database.';
    } else {
        $success = 'Settings updated.';

        // Audit trail for the sensitive toggles — who changed what, when.
        // Historical votes are never touched by this; get_leaderboard()
        // filters by each vote's own stamped mode (see includes/helpers.php),
        // so switching here only changes what NEW ballots look like.
        $newVotingMode = $config['app']['voting_mode'] ?? 'rating';
        if ($newVotingMode !== $previousVotingMode) {
            log_admin_action(
                $pdo,
                'voting_mode_changed',
                "previous_mode={$previousVotingMode} new_mode={$newVotingMode}"
            );
        }
        $newVotingOpen = !empty($config['app']['voting_open']);
        if ($newVotingOpen !== $previousVotingOpen) {
            log_admin_action($pdo, 'voting_open_changed', $newVotingOpen ? 'opened' : 'closed');
        }
        $newResultsPublic = !empty($config['app']['results_public']);
        if ($newResultsPublic !== $previousResultsPublic) {
            log_admin_action($pdo, 'results_public_changed', $newResultsPublic ? 'made public' : 'made private');
        }
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
$votesByMode = $settingsWritable ? vote_counts_by_mode($pdo) : [];
$otherModeVotes = 0;
foreach ($votesByMode as $modeKey => $count) {
    if ($modeKey !== $votingModeValue) {
        $otherModeVotes += $count;
    }
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
    <form method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="mb-4">
            <label class="form-label d-block">Branding</label>
            <small class="text-muted d-block mb-3">Every field here is optional — leave blank to keep the built-in default. This is what makes the project reusable for a different election without touching any code.</small>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Event / site name</label>
                    <input class="form-control" type="text" name="event_name" value="<?php echo h($config['app']['event_name'] ?? ''); ?>" placeholder="e.g. UMU Rubaga Varsity Ball">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tagline (shown on the homepage badge)</label>
                    <input class="form-control" type="text" name="event_tagline" value="<?php echo h($config['app']['event_tagline'] ?? ''); ?>" placeholder="e.g. Varsity Ball Voting">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Male contestant title</label>
                    <input class="form-control" type="text" name="male_title" value="<?php echo h($config['app']['male_title'] ?? ''); ?>" placeholder="e.g. Mr UMU Rubaga">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Female contestant title</label>
                    <input class="form-control" type="text" name="female_title" value="<?php echo h($config['app']['female_title'] ?? ''); ?>" placeholder="e.g. Mrs UMU Rubaga">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo — upload an image</label>
                    <input class="form-control" type="file" name="logo_upload" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted">JPG, PNG, or WEBP. Automatically resized and compressed. Uploading replaces the URL below.</small>
                    <?php if (!empty($config['app']['logo_url'])): ?>
                        <div class="mt-2">
                            <img src="<?php echo h(asset_url($config['app']['logo_url'], $config)); ?>" alt="Current logo" style="height: 48px; border-radius: 6px;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">...or Logo URL</label>
                    <input class="form-control" type="text" name="logo_url" value="<?php echo h($config['app']['logo_url'] ?? ''); ?>" placeholder="https://... or /assets/images/your-logo.png">
                    <small class="text-muted">Used for the navbar badge, browser tab icon, and certificates. Only used if you don't upload a file above.</small>
                </div>
                <div class="col-12">
                    <hr class="my-2" style="border-color: rgba(255,255,255,.15);">
                    <p class="text-muted small mb-2">Colors — each one controls a different part of the page, shown below each field.</p>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Primary color</label>
                    <input class="form-control form-control-color" type="color" name="theme_primary_color" value="<?php echo h(site_primary_color($config)); ?>">
                    <small class="text-muted">Buttons, links, and highlighted UI elements.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Accent color</label>
                    <input class="form-control form-control-color" type="color" name="theme_accent_color" value="<?php echo h(site_accent_color($config)); ?>">
                    <small class="text-muted">Badges (like the gold "Rate 1-5" tag) and secondary highlights.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Background color</label>
                    <input class="form-control form-control-color" type="color" name="theme_background_color" value="<?php echo h(site_background_color($config)); ?>">
                    <small class="text-muted">The page's base background, including the homepage hero section.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Text color</label>
                    <input class="form-control form-control-color" type="color" name="theme_text_color" value="<?php echo h(site_text_color($config)); ?>">
                    <small class="text-muted">Body text color — make sure it stays readable against your background color above.</small>
                </div>
            </div>
        </div>
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
            <?php if ($otherModeVotes > 0): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <strong>Heads up:</strong> <?php echo (int) $otherModeVotes; ?> vote row(s) were cast under a different voting mode than the one selected above. They are kept exactly as recorded and are excluded from the current mode's results/leaderboard (each vote remembers the mode it was cast under).
                </div>
            <?php endif; ?>
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

<div class="card-dark p-4 mt-4" style="border: 1px solid rgba(220,53,69,.4);">
    <h4 class="mb-2 text-danger">Danger zone — anonymize ballots</h4>
    <p class="text-muted mb-3">
        Removes the link between each recorded vote and the voter who cast it, without changing any score, contestant,
        or category — results and leaderboards are completely unaffected. Only available once voting is closed, since
        dedup protection while voting is open still needs that link. <strong>This cannot be undone.</strong>
    </p>
    <?php if (!empty($config['app']['voting_open'] ?? false)): ?>
        <div class="alert alert-secondary mb-0">Close voting first to enable this.</div>
    <?php else: ?>
        <form method="post" onsubmit="return confirm('This permanently removes the voter link from every vote. Results are unaffected, but this cannot be undone. Continue?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="anonymize_ballots">
            <div class="mb-3" style="max-width: 320px;">
                <label class="form-label">Type ANONYMIZE to confirm</label>
                <input class="form-control" type="text" name="confirm_anonymize" autocomplete="off" required>
            </div>
            <button class="btn btn-outline-danger" type="submit" <?php echo !$settingsWritable ? 'disabled' : ''; ?>>Anonymize all ballots</button>
        </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
