<?php
/*
 * helpers.php
 * Small utility helpers used across the app for escaping, URL helpers,
 * environment resolution and simple app settings persistence.
 */

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(array $config): bool
{
    if (empty($_SESSION['user_email'])) {
        return false;
    }

    // Admins are determined by email address listed in the config
    return in_array($_SESSION['user_email'], $config['app']['admin_emails'], true);
}

function base_url(array $config): string
{
    $base = trim($config['app']['base_url'] ?? '');
    if ($base !== '') {
        return rtrim($base, '/');
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($scriptName === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $scriptName);
    if (strpos($normalized, '/admin/') !== false) {
        $basePath = strstr($normalized, '/admin/', true);
        return $basePath === false ? '' : rtrim($basePath, '/');
    }

    $dir = rtrim(str_replace(basename($normalized), '', $normalized), '/');
    return $dir === '' ? '' : $dir;
}

function asset_url(string $path, array $config): string
{
    $base = absolute_base_url($config);
    $cleanPath = ltrim($path, '/');

    if ($base === '') {
        return $cleanPath;
    }

    return $base . '/' . $cleanPath;
}

function asset_url_versioned(string $path, array $config): string
{
    $url = asset_url($path, $config);
    $cleanPath = ltrim($path, '/');
    $fullPath = __DIR__ . '/../' . $cleanPath;

    if (!is_file($fullPath)) {
        return $url;
    }

    $version = (string) filemtime($fullPath);
    $separator = strpos($url, '?') === false ? '?' : '&';

    return $url . $separator . 'v=' . rawurlencode($version);
}

function absolute_base_url(array $config): string
{
    $basePath = base_url($config);
    
    // If base_url is explicitly set in config, use it
    if (!empty($config['app']['base_url'])) {
        return rtrim($config['app']['base_url'], '/');
    }
    
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return $basePath;
    }

    // Force HTTPS for live server
    $isLive = strpos($host, 'umuelections.fwh.is') !== false;
    $isHttps = $isLive || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';

    return $scheme . '://' . $host . $basePath;
}

function resolve_environment(array $config): string
{
    // Determine which environment configuration to use based on host
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $envs = $config['environments'] ?? [];
    foreach ($envs as $envName => $envConfig) {
        $hosts = $envConfig['hosts'] ?? [];
        foreach ($hosts as $envHost) {
            if ($envHost !== '' && strpos($host, strtolower($envHost)) !== false) {
                return $envName;
            }
        }
    }

    return 'local';
}

function runtime_config(array $config): array
{
    // Apply environment-specific overrides (e.g., DB credentials)
    $envName = resolve_environment($config);
    $envs = $config['environments'] ?? [];
    $envConfig = $envs[$envName] ?? [];

    if (!empty($envConfig['db'])) {
        $config['db'] = $envConfig['db'];
    }

    return $config;
}

function ensure_settings_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_settings (\n"
        . "setting_key VARCHAR(64) PRIMARY KEY,\n"
        . "setting_value TEXT NOT NULL\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function fetch_app_settings(PDO $pdo): array
{
    // Read app-level settings stored in DB and return as key => value array
    try {
        ensure_settings_table($pdo);
        $rows = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return $settings;
}

function apply_app_settings(array $config, PDO $pdo): array
{
    $settings = fetch_app_settings($pdo);
    if (!$settings) {
        return $config;
    }
    // Map persisted settings (strings) into proper types and merge into config
    $app = $config['app'] ?? [];
    foreach ($settings as $key => $value) {
        switch ($key) {
            case 'voting_open':
            case 'results_public':
                $app[$key] = (bool) (int) $value;
                break;
            case 'category_limit':
                $app[$key] = (int) $value;
                break;
            default:
                $app[$key] = $value;
                break;
        }
    }

    $config['app'] = $app;
    return $config;
}

function save_app_settings(PDO $pdo, array $settings): bool
{
    // Persist a set of application settings into the DB
    try {
        ensure_settings_table($pdo);
        $stmt = $pdo->prepare('REPLACE INTO app_settings (setting_key, setting_value) VALUES (?, ?)');
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, (string) $value]);
        }
    } catch (PDOException $e) {
        return false;
    }

    return true;
}

function get_voting_window(array $config): array
{
    $tzName = $config['app']['timezone'] ?? 'UTC';
    $tz = new DateTimeZone($tzName);
    $startStr = $config['app']['voting_start'] ?? '';
    $endStr = $config['app']['voting_end'] ?? '';

    $start = $startStr !== '' ? new DateTime($startStr, $tz) : null;
    $end = $endStr !== '' ? new DateTime($endStr, $tz) : null;

    if ($start && $end && $end <= $start) {
        $end = (clone $end)->modify('+1 day');
    }

    return [
        'start' => $start,
        'end' => $end,
        'enabled' => !empty($config['app']['voting_open']),
        'timezone' => $tzName,
    ];
}

function is_voting_open_config(array $config): bool
{
    $window = get_voting_window($config);
    if (!$window['enabled']) {
        return false;
    }

    $tz = new DateTimeZone($window['timezone']);
    $now = new DateTime('now', $tz);

    $start = $window['start'];
    $end = $window['end'];

    if ($start && $end) {
        return $now >= $start && $now <= $end;
    } elseif ($start) {
        return $now >= $start;
    } elseif ($end) {
        return $now <= $end;
    }

    return (bool) $window['enabled'];
}

function voting_timestamps_for_client(array $config): array
{
    $window = get_voting_window($config);
    $utc = new DateTimeZone('UTC');

    $startUtc = $window['start'] ? (clone $window['start'])->setTimezone($utc)->format(DateTime::ATOM) : '';
    $endUtc = $window['end'] ? (clone $window['end'])->setTimezone($utc)->format(DateTime::ATOM) : '';

    return [
        'start' => $startUtc,
        'end' => $endUtc,
        'enabled' => $window['enabled'] ? 1 : 0,
    ];
}

/**
 * Single source of truth for "is voting open right now, and what should the
 * banner say". Previously this exact logic was duplicated (and drifting)
 * across vote.php, admin/settings.php and results.php, which is what let a
 * disabled/expired voting window still appear open on some pages.
 */
function voting_status_message(array $config): array
{
    $window = get_voting_window($config);
    $tz = new DateTimeZone($window['timezone']);
    $now = new DateTime('now', $tz);
    $start = $window['start'];
    $end = $window['end'];

    if (!$window['enabled']) {
        return ['open' => false, 'message' => 'Voting is disabled by the admin.'];
    }

    if (!$start && !$end) {
        return ['open' => true, 'message' => 'Voting is open now.'];
    }

    $open = true;
    if ($start && $end) {
        $open = $now >= $start && $now <= $end;
    } elseif ($start) {
        $open = $now >= $start;
    } elseif ($end) {
        $open = $now <= $end;
    }

    if ($open) {
        return ['open' => true, 'message' => 'Voting is open now.'];
    }

    if ($start && $now < $start) {
        return ['open' => false, 'message' => 'Voting opens on ' . $start->format('M d, Y H:i') . ' (' . $window['timezone'] . ').'];
    }

    if ($end && $now > $end) {
        return ['open' => false, 'message' => 'Voting closed on ' . $end->format('M d, Y H:i') . ' (' . $window['timezone'] . ').'];
    }

    return ['open' => false, 'message' => 'Voting is currently closed.'];
}

/**
 * Which ballot workflow is active: 'rating' (the existing 1-5 rate-every-
 * contestant flow) or 'simple' (pick one contestant per category, one
 * "Vote Now" submit — a normal single-choice ballot). Defaults to 'rating'
 * so existing elections are unaffected until an admin opts in.
 */
function get_voting_mode(array $config): string
{
    $mode = strtolower(trim((string) ($config['app']['voting_mode'] ?? 'rating')));
    return $mode === 'simple' ? 'simple' : 'rating';
}

/**
 * Mode-aware leaderboard: ranks by AVG(score) in rating mode and by
 * vote COUNT in simple mode (AVG is meaningless once every vote is worth
 * the same 1 point). Used by vote.php, results.php and certificate.php so
 * there is one ranking implementation instead of three copies that can
 * disagree with each other.
 */
function get_leaderboard(PDO $pdo, string $mode): array
{
    $metricSql = $mode === 'simple' ? 'COUNT(v.id)' : 'AVG(v.score)';

    $categoryRows = $pdo->query(
        "SELECT c.id AS category_id, c.name AS category_name, c.gender,
                con.id AS contestant_id, con.name AS contestant_name, con.gender AS contestant_gender, con.photo,
                $metricSql AS metric
         FROM categories c
         JOIN votes v ON v.category_id = c.id
         JOIN contestants con ON con.id = v.contestant_id
            AND (c.gender = con.gender OR c.gender = \"all\")
         GROUP BY c.id, con.id
         ORDER BY c.id, con.gender, metric DESC"
    )->fetchAll();

    $categoryLeaders = [];
    foreach ($categoryRows as $row) {
        $contestantGender = $row['contestant_gender'] ?? 'male';
        $key = ($row['gender'] ?? '') === 'all' ? $row['category_id'] . '_' . $contestantGender : $row['category_id'];
        if (!isset($categoryLeaders[$key]) || $row['metric'] > $categoryLeaders[$key]['metric']) {
            $categoryLeaders[$key] = $row;
        }
    }

    $overallRows = $pdo->query(
        "SELECT con.id AS contestant_id, con.name AS contestant_name, con.gender, con.photo,
                $metricSql AS metric
         FROM contestants con
         JOIN votes v ON v.contestant_id = con.id
         JOIN categories c ON c.id = v.category_id
         WHERE c.gender = con.gender OR c.gender = \"all\"
         GROUP BY con.id, con.gender
         ORDER BY con.gender, metric DESC"
    )->fetchAll();

    // Female first, then male — matches the announcement order requested for
    // the results/winners screens.
    $overallWinners = ['female' => null, 'male' => null];
    foreach ($overallRows as $row) {
        $gender = $row['gender'] ?? '';
        if (($gender === 'male' || $gender === 'female') && $overallWinners[$gender] === null) {
            $overallWinners[$gender] = $row;
        }
    }

    return [
        'mode' => $mode,
        'category_leaders' => $categoryLeaders,
        'overall_winners' => $overallWinners,
    ];
}

function format_leaderboard_metric(?array $row, string $mode): string
{
    if (!$row) {
        return '';
    }
    $value = (float) $row['metric'];
    if ($mode === 'simple') {
        $count = (int) round($value);
        return number_format($count) . ' vote' . ($count === 1 ? '' : 's');
    }
    return 'Avg ' . number_format($value, 2) . '/5';
}

/**
 * Minimal session-bound CSRF protection for the vote and admin-settings
 * forms, neither of which had any token before.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
