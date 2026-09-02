<?php
require_once __DIR__ . '/vendor/autoload.php'; // Dompdf\Dompdf — see composer.json
$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/session.php';

// db.php either sets $pdo to a real PDO connection or exits early on
// failure — there is no code path where execution continues with $pdo
// unset. This explicit check exists only to make that guarantee visible
// to static analysis (editors/IDEs otherwise infer $pdo could be null
// from the `isset($pdo)` checks used elsewhere for pages that tolerate a
// missing DB) and to fail with a clear message instead of a bare
// TypeError if that guarantee is ever broken by a future edit.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo 'Database connection is unavailable.';
    exit;
}

$config = apply_app_settings($config, $pdo);

$isAdmin = is_logged_in() && is_admin($config);
$tzName = $config['app']['timezone'] ?? 'UTC';
$tz = new DateTimeZone($tzName);
$now = new DateTime('now', $tz);
$votingEnabled = (bool) ($config['app']['voting_open'] ?? false);
$startValue = $config['app']['voting_start'] ?? '';
$endValue = $config['app']['voting_end'] ?? '';
$startTime = $startValue !== '' ? new DateTime($startValue, $tz) : null;
$endTime = $endValue !== '' ? new DateTime($endValue, $tz) : null;
$hasStarted = $startTime ? $now >= $startTime : true;
$resultsPublic = (bool) ($config['app']['results_public'] ?? false);
// Matches the visibility rule used by results.php: admins always, everyone
// else once results have been made public. Previously this was admin-only
// while results.php and vote.php both rendered a "Download certificate"
// link for regular voters too — clicking it always hit this 403.
$canDownload = $isAdmin || $resultsPublic;

if (!$canDownload) {
    http_response_code(403);
    echo 'Certificates are available once results are public, or to the signed-in event admin. '
        . 'If you are the admin: confirm you are logged in with the email listed in config[\'app\'][\'admin_emails\'], '
        . 'or turn on "Make results visible to everyone" in Admin -> Settings.';
    exit;
}

$certificateGender = strtolower(trim((string) ($_GET['gender'] ?? '')));
if (!in_array($certificateGender, ['male', 'female'], true)) {
    http_response_code(400);
    echo 'Select a valid certificate gender: male or female.';
    exit;
}

$votingMode = get_voting_mode($config);
ensure_category_gender_enum($pdo);
$board = get_leaderboard($pdo, $votingMode);
$winner = $board['overall_winners'][$certificateGender] ?? null;

$winnerName = leaderboard_winner_label($winner) ?: 'TBD';
$winnerScoreLine = $winner ? format_leaderboard_metric($winner, $votingMode) : 'N/A';
$eventDate = $config['app']['event_date'] ?? '';

$titleLabel = $certificateGender === 'male' ? site_male_title($config) : site_female_title($config);
$institutionName = site_name($config);
$primaryColor = site_primary_color($config);
$accentColor = site_accent_color($config);
$logoDataUri = site_logo_data_uri($config);
$issuedOn = date('F j, Y');
$eventDateLabel = $eventDate !== '' ? date('F j, Y', strtotime($eventDate)) : null;

function certificate_html(
    string $institutionName,
    string $titleLabel,
    string $winnerName,
    string $winnerScoreLine,
    ?string $eventDateLabel,
    string $issuedOn,
    string $primaryColor,
    string $accentColor,
    ?string $logoDataUri
): string {
    $logoTag = $logoDataUri
        ? '<img class="logo" src="' . h($logoDataUri) . '">'
        : '';
    $footerRight = $eventDateLabel !== null
        ? '<td>Event date: ' . h($eventDateLabel) . '</td><td class="right">Issued: ' . h($issuedOn) . '</td>'
        : '<td colspan="2" class="right">Issued: ' . h($issuedOn) . '</td>';

    return <<<HTML
<html>
<head>
<style>
    @page { size: 842pt 595pt; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; width: 842pt; height: 595pt; font-family: 'DejaVu Sans', sans-serif; }
    .sheet { position: relative; width: 842pt; height: 595pt; }
    .border { position: absolute; top: 24pt; left: 24pt; right: 24pt; bottom: 24pt; border: 2pt solid {$primaryColor}; }
    .corner { position: absolute; width: 0; height: 0; border-style: solid; }
    .corner-tl { top: 0; left: 0; border-width: 80pt 80pt 0 0; border-color: {$primaryColor} transparent transparent transparent; }
    .corner-br { bottom: 0; right: 0; border-width: 0 0 80pt 80pt; border-color: transparent transparent {$accentColor} transparent; }
    .logo { position: absolute; top: 45pt; left: 50%; margin-left: -30pt; width: 60pt; height: 60pt; }
    .content { position: absolute; top: 118pt; left: 70pt; right: 70pt; text-align: center; }
    .eyebrow { color: {$primaryColor}; letter-spacing: 4pt; font-size: 12pt; font-weight: bold; text-transform: uppercase; }
    h1 { font-size: 26pt; margin: 6pt 0 4pt; color: #1a1a1a; }
    .presented { font-size: 11pt; color: #777; }
    .title-label { font-size: 13pt; letter-spacing: 2pt; color: {$accentColor}; text-transform: uppercase; margin-top: 14pt; }
    .winner-name { font-size: 32pt; font-weight: bold; margin: 6pt 0; color: #1a1a1a; }
    .score-line { font-size: 12pt; color: #777; }
    .footer { position: absolute; bottom: 48pt; left: 90pt; right: 90pt; }
    .footer table { width: 100%; }
    .footer td { font-size: 10pt; color: #999; border-top: 1pt solid #999; padding-top: 6pt; }
    .footer .right { text-align: right; }
</style>
</head>
<body>
<div class="sheet">
    <div class="border"></div>
    <div class="corner corner-tl"></div>
    <div class="corner corner-br"></div>
    {$logoTag}
    <div class="content">
        <div class="eyebrow">Certificate of Appreciation</div>
        <h1>{$institutionName}</h1>
        <div class="presented">This certificate is proudly presented to</div>
        <div class="title-label">{$titleLabel}</div>
        <div class="winner-name">{$winnerName}</div>
        <div class="score-line">{$winnerScoreLine}</div>
    </div>
    <div class="footer">
        <table><tr>{$footerRight}</tr></table>
    </div>
</div>
</body>
</html>
HTML;
}

$html = certificate_html(
    h($institutionName),
    h(strtoupper($titleLabel)) . ' WINNER',
    h($winnerName),
    h($winnerScoreLine),
    $eventDateLabel,
    $issuedOn,
    $primaryColor,
    $accentColor,
    $logoDataUri
);

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false); // see site_logo_data_uri() doc comment — no server-side fetches
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->render();
$pdf = $dompdf->output();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $certificateGender . '_winner_certificate_' . date('Ymd_His') . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
