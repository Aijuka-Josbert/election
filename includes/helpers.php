<?php

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

function absolute_base_url(array $config): string
{
    $basePath = base_url($config);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return $basePath;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';

    return $scheme . '://' . $host . $basePath;
}

function resolve_environment(array $config): string
{
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
    $envName = resolve_environment($config);
    $envs = $config['environments'] ?? [];
    $envConfig = $envs[$envName] ?? [];

    if (!empty($envConfig['db'])) {
        $config['db'] = $envConfig['db'];
    }

    return $config;
}
