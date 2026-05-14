<?php

/*
 * includes/db.php
 * Initialize a PDO connection using runtime configuration resolved
 * from `config/config.php`. Exposes a `$pdo` variable for queries.
 */

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
$config = runtime_config($config);

// Build the DSN and create a PDO instance with common safe options
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['db']['host'],
    $config['db']['name'],
    $config['db']['charset']
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
} catch (PDOException $e) {
    // Fail fast with a generic message (do not leak DB details to users)
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}
