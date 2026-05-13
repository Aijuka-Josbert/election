<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/../config/config.php';

if (!is_logged_in() || !is_admin($config)) {
    http_response_code(403);
    echo 'Admin access only.';
    exit;
}
