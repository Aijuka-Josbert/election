<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';
if (!isset($config)) {
    $config = require __DIR__ . '/../config/config.php';
}
require_once __DIR__ . '/db.php';
if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = $pageTitle ?? 'UMU Varsity Ball Voting';
$baseUrl = base_url($config);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo h(asset_url_versioned('assets/css/style.css', $config)); ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark glass-nav">
    <div class="container">
        <a class="navbar-brand" href="<?php echo h($baseUrl . '/index.php'); ?>">
            <span class="brand-dot"></span>
            UMU Varsity Ball
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto gap-lg-3">
                <li class="nav-item"><a class="nav-link" href="<?php echo h($baseUrl . '/index.php'); ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo h($baseUrl . '/vote.php'); ?>">Vote</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo h($baseUrl . '/results.php'); ?>">Results</a></li>
                <?php if (!empty($_SESSION['user_email']) && is_admin($config)): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo h($baseUrl . '/admin/index.php'); ?>">Admin</a></li>
                <?php endif; ?>
            </ul>
        </div>
            <div class="ms-lg-4 mt-3 mt-lg-0 auth-action">
                <?php if (is_logged_in()): ?>
                    <a class="btn btn-outline-light btn-sm" href="<?php echo h($baseUrl . '/logout.php'); ?>">Logout</a>
                <?php else: ?>
                    <a class="btn btn-primary btn-sm" href="<?php echo h($baseUrl . '/login.php'); ?>">Login with Google</a>
                <?php endif; ?>
            </div>
    </div>
</nav>
<main>
