<?php
$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/session.php';

$resultsPublic = (bool) ($config['app']['results_public'] ?? false);
$isAdmin = is_logged_in() && is_admin($config);
if (!$resultsPublic && !$isAdmin) {
    http_response_code(403);
    echo 'Certificates are available to admins only during the election period.';
    exit;
}

$overallScores = $pdo->query(
    'SELECT con.id AS contestant_id, con.name AS contestant_name, con.gender,
            AVG(v.score) AS avg_score
     FROM contestants con
     JOIN votes v ON v.contestant_id = con.id
     GROUP BY con.id
     ORDER BY avg_score DESC'
)->fetchAll();

$overallWinners = ['male' => null, 'female' => null];
foreach ($overallScores as $row) {
    if ($row['gender'] === 'male' && $overallWinners['male'] === null) {
        $overallWinners['male'] = $row;
    }
    if ($row['gender'] === 'female' && $overallWinners['female'] === null) {
        $overallWinners['female'] = $row;
    }
}

$femaleName = $overallWinners['female']['contestant_name'] ?? 'TBD';
$maleName = $overallWinners['male']['contestant_name'] ?? 'TBD';
$eventName = $config['app']['event_name'] ?? 'UMU Rubaga Varsity Ball';
$eventDate = $config['app']['event_date'] ?? '';

function pdf_escape(string $value): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

$issuedOn = date('Y-m-d');
$lines = [
    ['text' => 'Certificate of Achievement', 'size' => 24, 'x' => 72, 'y' => 760],
    ['text' => 'This certificate recognizes the overall winners of the', 'size' => 12, 'x' => 72, 'y' => 730],
    ['text' => $eventName, 'size' => 16, 'x' => 72, 'y' => 710],
    ['text' => 'Mr UMU Rubaga: ' . $maleName, 'size' => 14, 'x' => 72, 'y' => 670],
    ['text' => 'Mrs UMU Rubaga: ' . $femaleName, 'size' => 14, 'x' => 72, 'y' => 650],
];

if ($eventDate !== '') {
    $lines[] = ['text' => 'Event date: ' . $eventDate, 'size' => 10, 'x' => 72, 'y' => 620];
}

$lines[] = ['text' => 'Issued on ' . $issuedOn, 'size' => 10, 'x' => 72, 'y' => 600];

$content = "";
foreach ($lines as $line) {
    $content .= "BT\n";
    $content .= "/F1 {$line['size']} Tf\n";
    $content .= "{$line['x']} {$line['y']} Td\n";
    $content .= "(" . pdf_escape($line['text']) . ") Tj\n";
    $content .= "ET\n";
}

$objects = [];
$objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
$objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
$objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
$objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
$objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";

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

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="umu_vote_winners_certificate.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
