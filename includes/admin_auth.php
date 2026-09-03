<?php

/*
 * includes/admin_auth.php
 * Guard for admin-only pages. Checks that the user is logged in and that
 * their email appears in the admin list from config. Returns 403 otherwise.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/../config/config.php';

if (!is_logged_in() || !is_admin($config)) {
    http_response_code(403);
    echo 'Admin access only.';
    exit;
}

/*
 * Idle timeout: auto-logout an admin after 10 minutes with no admin-page
 * request. This is the server-side enforcement — the matching client-side
 * warning timer in assets/js/main.js is UX only (a heads-up before it
 * happens) and is never what actually ends the session, since a client
 * can't be trusted to log itself out.
 */
$adminIdleTimeoutSeconds = 600;
$lastActivity = $_SESSION['last_activity'] ?? null;
if ($lastActivity !== null && (time() - (int) $lastActivity) > $adminIdleTimeoutSeconds) {
    $returnUrl = $_SERVER['REQUEST_URI'] ?? '';
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['return_url'] = $returnUrl;
    header('Location: ' . base_url($config) . '/login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();
