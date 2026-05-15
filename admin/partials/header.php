<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (!isset($config)) {
    $config = require __DIR__ . '/../../config/config.php';
}
require_once __DIR__ . '/../../includes/db.php';
if (isset($pdo)) {
    $config = apply_app_settings($config, $pdo);
}
$pageTitle = $pageTitle ?? 'Admin - UMU Varsity Ball';
$activePage = $activePage ?? '';
$baseUrl = base_url($config);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo h(asset_url('assets/css/style.css', $config)); ?>" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-topbar d-flex justify-content-end align-items-center mb-3">
            <a class="btn btn-danger btn-sm nav-logout" href="<?php echo h($baseUrl . '/logout.php'); ?>"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
