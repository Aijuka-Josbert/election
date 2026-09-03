<?php
/*
 * config.php
 * Application configuration (database credentials, Google OAuth keys,
 * uploads settings and app flags). Sensitive values should be overridden
 * in `config.local.php` which is loaded if present to avoid committing
 * secrets into source control.
 */

$host = $_SERVER['HTTP_HOST'] ?? '';
$isLive = strpos($host, 'umuelections.fwh.is') !== false;

/*
 * SECURITY: never trust the hosting provider's php.ini defaults for this.
 * Shared/free hosts (this app's config below shows one) commonly ship with
 * display_errors=On, which would print raw file paths, SQL fragments, and
 * stack traces straight into a voter's browser on any uncaught error. Force
 * it off on the live host regardless of php.ini; keep it on for local dev
 * so mistakes are visible while building. Errors are still logged either
 * way, just never displayed to the person seeing the page.
 */
error_reporting(E_ALL);
ini_set('log_errors', '1');
if ($isLive) {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
}

/*
 * Some hosting environments (containers/shared hosts with restrictive
 * security policies — SELinux, grsecurity, some Docker seccomp profiles)
 * don't allow a process to mark memory pages executable, which is what
 * PCRE's JIT compiler needs. PHP still works fine without it — regexes
 * just run through PCRE's normal interpreter, which is negligibly slower
 * for this app's usage (a handful of preg_match() calls per request, not
 * a hot loop) — but every preg_match() call would otherwise throw a
 * "PCRE JIT will be disabled" warning, which is ugly, leaks the server's
 * file path, and (if the environment ever flips display_errors on by
 * mistake) would be visible to every visitor on every page. Disabling
 * JIT outright avoids the warning entirely rather than just hiding it.
 */
ini_set('pcre.jit', '0');

/*
 * SECURITY: real credentials must never live in this tracked file — this
 * repo is public on GitHub. Every value below is a safe placeholder that
 * only works together with config/config.local.php (untracked, see
 * config/config.local.php.example) which overrides it at the bottom of
 * this file. If config.local.php is missing, the app runs with these
 * placeholders and the DB connection will simply fail closed.
 */
$config = [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => getenv('DB_NAME') ?: 'umu_vote',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'environments' => [
        'local' => [
            'hosts' => ['localhost', '127.0.0.1'],
            'db' => [
                'host' => getenv('DB_HOST') ?: 'localhost',
                'name' => getenv('DB_NAME') ?: 'umu_vote',
                'user' => getenv('DB_USER') ?: 'root',
                'pass' => getenv('DB_PASS') ?: '',
                'charset' => 'utf8mb4',
            ],
        ],
        'live' => [
            'hosts' => ['umuelections.fwh.is'],
            'db' => [
                'host' => getenv('LIVE_DB_HOST') ?: '',
                'name' => getenv('LIVE_DB_NAME') ?: '',
                'user' => getenv('LIVE_DB_USER') ?: '',
                'pass' => getenv('LIVE_DB_PASS') ?: '',
                'charset' => 'utf8mb4',
            ],
        ],
    ],
    'app' => [
        'base_url' => '',
        'event_name' => 'UMU Rubaga Varsity Ball',
        'event_date' => '',
        'timezone' => 'Africa/Kampala',
        'allowed_domain' => 'stud.umu.ac.ug',
        'voting_open' => false,
        'voting_start' => '',
        'voting_end' => '',
        'voting_mode' => 'rating',
        'results_public' => false,
        'category_limit' => 10,
        'admin_emails' => [
            'josbert.aijuka@stud.umu.ac.ug',
            'nakanwagi.angela@stud.umu.ac.ug',
            'ian.kwagala@stud.umu.ac.ug',
            'nabayego.joanitah@stud.umu.ac.ug',
        ],
    ],
    'google' => [
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
        'redirect_uri' => $isLive
            ? 'https://umuelections.fwh.is/google-callback.php'
            : 'http://localhost/election/google-callback.php',
    ],
    'uploads' => [
        'contestants_dir' => __DIR__ . '/../uploads/contestants',
        'contestants_url' => 'uploads/contestants',
        'max_size' => 2 * 1024 * 1024,
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];

$configLocalPath = __DIR__ . '/.env';
if (is_file($configLocalPath)) {
    $localConfig = require $configLocalPath;
    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
    }
}

return $config;
