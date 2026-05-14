<?php

/*
 * includes/auth.php
 * Simple guard used on pages that require an authenticated user. Redirects
 * to `login.php` when no user session is available.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
