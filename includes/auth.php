<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
