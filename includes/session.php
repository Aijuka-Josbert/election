<?php

/*
 * includes/session.php
 * Configure session cookie parameters to improve security (Secure, HttpOnly,
 * SameSite) and start the PHP session if not already active.
 */

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }

    return false;
}

$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Start session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
