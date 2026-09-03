<?php
/*
 * login.php
 * Initiates Google OAuth login using `google/apiclient`. Redirects the
 * user to Google's consent screen. If already authenticated, redirects to voting.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
$config = require __DIR__ . '/config/config.php';

// Capture ?return_url=... (set by includes/admin_auth.php on an idle
// timeout, or by any page that wants "log in, then come back here") into
// the session, so google-callback.php can send the user back to it once
// authenticated. Validated before ever being used as a redirect target —
// see safe_local_redirect_target().
$requestedReturnUrl = safe_local_redirect_target($_GET['return_url'] ?? null);
if ($requestedReturnUrl !== null) {
    $_SESSION['return_url'] = $requestedReturnUrl;
}

if (!empty($_SESSION['user_id'])) {
    $returnUrl = safe_local_redirect_target($_SESSION['return_url'] ?? null);
    unset($_SESSION['return_url']);
    header('Location: ' . ($returnUrl ?? 'vote.php'));
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// OAuth CSRF protection: without a `state` parameter, an attacker can
// start their own Google OAuth flow, grab the resulting authorization
// code, and trick a victim's browser into visiting
// google-callback.php?code=<attacker's code> — logging the victim in as
// the attacker (classic "login CSRF"). A random per-session state value,
// verified on the way back in google-callback.php, closes this.
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Build Google client and redirect to consent screen
$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');
$client->setState($state);

$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;
