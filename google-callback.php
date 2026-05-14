<?php
/*
 * google-callback.php
 * Handles the OAuth callback from Google. Exchanges the authorization code
 * for an access token, fetches basic profile info, validates the email domain
 * and upserts the user into the local `users` table. Finally sets session.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
$config = require __DIR__ . '/config/config.php';

require_once __DIR__ . '/vendor/autoload.php';

if (empty($_GET['code'])) {
    echo 'Missing Google authorization code.';
    exit;
}

// Exchange code for token
$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (!empty($token['error'])) {
    echo 'Google authentication failed.';
    exit;
}

$client->setAccessToken($token['access_token']);
$oauth = new Google_Service_Oauth2($client);
$userInfo = $oauth->userinfo->get();

// Enforce allowed email domain (e.g., stud.umu.ac.ug)
$email = strtolower($userInfo->email ?? '');
$domain = substr(strrchr($email, '@') ?: '', 1);

if ($domain !== $config['app']['allowed_domain']) {
    echo 'Only UMU student emails are allowed.';
    exit;
}

$googleId = $userInfo->id ?? '';
$name = $userInfo->name ?? 'UMU Student';

// Insert or update a local user record based on Google ID or email
$stmt = $pdo->prepare('SELECT id, has_voted FROM users WHERE google_id = :google_id OR email = :email LIMIT 1');
$stmt->execute([
    'google_id' => $googleId,
    'email' => $email,
]);
$user = $stmt->fetch();

if ($user) {
    $update = $pdo->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
    $update->execute([
        'name' => $name,
        'email' => $email,
        'id' => $user['id'],
    ]);
    $userId = (int) $user['id'];
    $hasVoted = (int) $user['has_voted'];
} else {
    $insert = $pdo->prepare('INSERT INTO users (google_id, name, email) VALUES (:google_id, :name, :email)');
    $insert->execute([
        'google_id' => $googleId,
        'name' => $name,
        'email' => $email,
    ]);
    $userId = (int) $pdo->lastInsertId();
    $hasVoted = 0;
}

// Set up the authenticated session and redirect to voting
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $name;
$_SESSION['user_email'] = $email;
$_SESSION['has_voted'] = $hasVoted;

header('Location: vote.php');
exit;
