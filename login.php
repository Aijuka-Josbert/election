<?php
/*
 * login.php
 * Initiates Google OAuth login using `google/apiclient`. Redirects the
 * user to Google's consent screen. If already authenticated, redirects to voting.
 */
require_once __DIR__ . '/includes/session.php';
$config = require __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: vote.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Build Google client and redirect to consent screen
$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');

$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;
