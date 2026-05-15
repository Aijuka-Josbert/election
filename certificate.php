<?php
$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/session.php';

if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
}

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
$canDownload = $isAdmin;

if (!$canDownload) {
    http_response_code(403);
    echo 'Certificates are available to admins only after voting has closed.';
    exit;
}

$certificateGender = strtolower(trim((string) ($_GET['gender'] ?? '')));
if (!in_array($certificateGender, ['male', 'female'], true)) {
    http_response_code(400);
    echo 'Select a valid certificate gender: male or female.';
    exit;
}

$winnerStmt = $pdo->prepare(
    'SELECT con.name AS contestant_name, AVG(v.score) AS avg_score
     FROM contestants con
     JOIN votes v ON v.contestant_id = con.id
     JOIN categories c ON c.id = v.category_id
     WHERE con.gender = ?
       AND (c.gender = con.gender OR c.gender = "all")
     GROUP BY con.id
     ORDER BY avg_score DESC
     LIMIT 1'
);
$winnerStmt->execute([$certificateGender]);
$winner = $winnerStmt->fetch();

$winnerName = $winner['contestant_name'] ?? 'TBD';
$winnerAverage = isset($winner['avg_score']) ? number_format((float) $winner['avg_score'], 2) : 'N/A';
$eventName = $config['app']['event_name'] ?? 'UMU Rubaga Varsity Ball';
$eventDate = $config['app']['event_date'] ?? '';

$titleLabel = $certificateGender === 'male' ? 'Mr UMU Rubaga' : 'Mrs UMU Rubaga';
$certificateHeading = $certificateGender === 'male'
    ? 'Certificate of Appreciation - Male Winner'
    : 'Certificate of Appreciation - Female Winner';

function pdf_escape(string $value): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

$issuedOn = date('Y-m-d');
$lines = [
    ['text' => $certificateHeading, 'size' => 24, 'x' => 72, 'y' => 780, 'font' => 'F2'],
    ['text' => 'This certificate recognizes the top winner of the', 'size' => 12, 'x' => 72, 'y' => 745],
    ['text' => $eventName, 'size' => 18, 'x' => 72, 'y' => 720],
    ['text' => '', 'size' => 10, 'x' => 72, 'y' => 690],
    ['text' => strtoupper($titleLabel) . ' WINNER', 'size' => 12, 'x' => 72, 'y' => 670],
    ['text' => $winnerName, 'size' => 22, 'x' => 72, 'y' => 640, 'font' => 'F2'],
    ['text' => 'Average score: ' . $winnerAverage, 'size' => 12, 'x' => 72, 'y' => 612],
];

if ($eventDate !== '') {
    $lines[] = ['text' => 'Event date: ' . $eventDate, 'size' => 10, 'x' => 72, 'y' => 560];
}

$lines[] = ['text' => 'Issued on ' . $issuedOn, 'size' => 10, 'x' => 72, 'y' => 560];

$content = "";
foreach ($lines as $line) {
    $font = $line['font'] ?? 'F1';
    $content .= "BT\n";
    $content .= "/{$font} {$line['size']} Tf\n";
    $content .= "{$line['x']} {$line['y']} Td\n";
    $content .= "(" . pdf_escape($line['text']) . ") Tj\n";
    $content .= "ET\n";
}

$objects = [];
$objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
$objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
$objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 6 0 R >> >> /Contents 5 0 R >>\nendobj\n";
$objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
$objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";
$objects[] = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Stonehenge >>\nendobj\n";

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $object) {
    $offsets[] = strlen($pdf);
    $pdf .= $object;
}

$xrefPosition = strlen($pdf);
$pdf .= "xref\n0 " . count($offsets) . "\n";
$pdf .= "0000000000 65535 f \n";
for ($i = 1; $i < count($offsets); $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
}

$pdf .= "trailer\n<< /Size " . count($offsets) . " /Root 1 0 R >>\n";
$pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="umu_vote_' . $certificateGender . '_winner_certificate_' . date('Ymd_His') . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
