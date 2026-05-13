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

function asset_url(string $path, array $config): string
{
    $base = rtrim($config['app']['base_url'] ?? '', '/');
    $cleanPath = ltrim($path, '/');

    if ($base === '') {
        return $cleanPath;
    }

    return $base . '/' . $cleanPath;
}
