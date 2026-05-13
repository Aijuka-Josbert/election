<?php
require_once __DIR__ . '/includes/session.php';
$config = require __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: vote.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');

$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;
