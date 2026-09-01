<?php
if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/helpers.php';
}
if (!isset($config)) {
    $config = require __DIR__ . '/../../config/config.php';
}
if (!isset($baseUrl)) {
    $baseUrl = base_url($config);
}
if (!isset($activePage)) {
    $activePage = '';
}
?>
<aside class="admin-sidebar">
    <div class="mb-4">
        <h4 class="title mb-1"><?php echo h(site_name($config)); ?></h4>
        <small class="text-muted">Admin Panel</small>
    </div>
    <a class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo h($baseUrl . '/admin/index.php'); ?>">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </a>
    <a class="<?php echo $activePage === 'contestants' ? 'active' : ''; ?>" href="<?php echo h($baseUrl . '/admin/contestants.php'); ?>">
        <i class="bi bi-people me-2"></i>Contestants
    </a>
    <a class="<?php echo $activePage === 'categories' ? 'active' : ''; ?>" href="<?php echo h($baseUrl . '/admin/categories.php'); ?>">
        <i class="bi bi-tags me-2"></i>Categories
    </a>
    <a class="<?php echo $activePage === 'stats' ? 'active' : ''; ?>" href="<?php echo h($baseUrl . '/admin/stats.php'); ?>">
        <i class="bi bi-bar-chart me-2"></i>Stats
    </a>
    <a class="<?php echo $activePage === 'integrity' ? 'active' : ''; ?>" href="<?php echo h($baseUrl . '/admin/integrity.php'); ?>">
        <i class="bi bi-shield-check me-2"></i>Data Integrity
    </a>
    <a class="<?php echo $activePage === 'users' ? 'active' : ''; ?>" href="<?php echo h($baseUrl . '/admin/users.php'); ?>">
        <i class="bi bi-person-x me-2"></i>Users
    </a>
    <a class="<?php echo $activePage === 'settings' ? 'active' : ''; ?>" href="<?php echo h($baseUrl . '/admin/settings.php'); ?>">
        <i class="bi bi-gear me-2"></i>Settings
    </a>
    <a href="<?php echo h($baseUrl . '/index.php'); ?>">
        <i class="bi bi-house-door me-2"></i>View Site
    </a>
    <a href="<?php echo h($baseUrl . '/logout.php'); ?>">
        <i class="bi bi-box-arrow-right me-2"></i>Logout
    </a>
</aside>
