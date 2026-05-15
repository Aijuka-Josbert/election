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
