<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
$config = require __DIR__ . '/../../config/config.php';
$pageTitle = $pageTitle ?? 'Admin - UMU Varsity Ball';
$activePage = $activePage ?? '';
$baseUrl = rtrim($config['app']['base_url'] ?? '', '/');
$baseUrl = $baseUrl === '' ? '' : $baseUrl;
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
