<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

$config = require __DIR__ . '/../config/config.php';
if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
}

header('Content-Type: application/json; charset=utf-8');

$open = is_voting_open_config($config);
$ts = voting_timestamps_for_client($config);

echo json_encode([
    'open' => $open ? 1 : 0,
    'start' => $ts['start'] ?? '',
    'end' => $ts['end'] ?? '',
]);

exit;
