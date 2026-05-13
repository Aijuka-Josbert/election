<aside class="admin-sidebar">
    <div class="mb-4">
        <h4 class="mb-1">Admin Panel</h4>
        <small class="text-muted">UMU Varsity Ball</small>
    </div>
    <a class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="index.php">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </a>
    <a class="<?php echo $activePage === 'contestants' ? 'active' : ''; ?>" href="contestants.php">
        <i class="bi bi-people me-2"></i>Contestants
    </a>
    <a class="<?php echo $activePage === 'categories' ? 'active' : ''; ?>" href="categories.php">
        <i class="bi bi-tags me-2"></i>Categories
    </a>
    <a class="<?php echo $activePage === 'stats' ? 'active' : ''; ?>" href="stats.php">
        <i class="bi bi-bar-chart me-2"></i>Stats
    </a>
    <a class="<?php echo $activePage === 'users' ? 'active' : ''; ?>" href="users.php">
        <i class="bi bi-person-x me-2"></i>Users
    </a>
    <a class="<?php echo $activePage === 'settings' ? 'active' : ''; ?>" href="settings.php">
        <i class="bi bi-gear me-2"></i>Settings
    </a>
    <a href="../index.php">
        <i class="bi bi-house-door me-2"></i>View Site
    </a>
    <a href="../logout.php">
        <i class="bi bi-box-arrow-right me-2"></i>Logout
    </a>
</aside>
