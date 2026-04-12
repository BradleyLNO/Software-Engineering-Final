<?php
/**
 * Shared Dashboard Layout Partial
 * Usage: include 'partials/layout_start.php'; (with $pageTitle and $activeNav set)
 *
 * Requires: config.php already loaded, user already verified
 */

$isUniversity = isUniversityUser();
$isSecPersonnel = isSecurityPersonnel();

$firstName = htmlspecialchars($_SESSION['first_name'] ?? 'User');
$lastName  = htmlspecialchars($_SESSION['last_name'] ?? '');
$initials  = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

$userRole = '';
if ($isUniversity) {
    $roleMap = ['student' => 'Student', 'staff' => 'Staff', 'faculty' => 'Faculty', 'administrator' => 'Admin'];
    $userRole = $roleMap[$_SESSION['role'] ?? ''] ?? ucfirst($_SESSION['role'] ?? 'User');
} else {
    $userRole = 'Security Officer';
}

$pageTitle   = $pageTitle   ?? 'Dashboard';
$activeNav   = $activeNav   ?? 'dashboard';

// Fetch pending alerts count for security badge
$pendingCount = 0;
if ($isSecPersonnel) {
    $r = $conn->query("SELECT COUNT(*) as c FROM alerts WHERE alert_status = 'PENDING'");
    if ($r) $pendingCount = (int)($r->fetch_assoc()['c'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="dashboard-page">

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ─── SIDEBAR ─────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><i class="fa fa-shield-halved"></i></div>
        <div>
            <div class="sidebar-brand"><?= APP_NAME ?></div>
            <div class="sidebar-tagline">Campus Safety System</div>
        </div>
    </div>

    <div class="sidebar-user d-flex align-items-center gap-3">
        <div class="user-avatar"><?= $initials ?></div>
        <div>
            <div class="user-name"><?= $firstName . ' ' . $lastName ?></div>
            <div class="user-role"><?= $userRole ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>

        <?php if ($isUniversity): ?>
            <a href="dashboard_user.php"
               class="nav-link-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="fa fa-house nav-icon"></i> Dashboard
            </a>
            <a href="dashboard_user.php?view=send_alert"
               class="nav-link-item <?= $activeNav === 'send_alert' ? 'active' : '' ?>">
                <i class="fa fa-bell nav-icon"></i> Send Alert
            </a>
            <a href="dashboard_user.php?view=my_alerts"
               class="nav-link-item <?= $activeNav === 'my_alerts' ? 'active' : '' ?>">
                <i class="fa fa-list-check nav-icon"></i> My Alerts
            </a>

            <div class="nav-section-label">Account</div>
            <a href="settings_user.php"
               class="nav-link-item <?= $activeNav === 'settings' ? 'active' : '' ?>">
                <i class="fa fa-gear nav-icon"></i> Settings
            </a>

        <?php else: ?>
            <a href="dashboard_security.php"
               class="nav-link-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="fa fa-house nav-icon"></i> Dashboard
            </a>
            <a href="dashboard_security.php?view=active_alerts"
               class="nav-link-item <?= $activeNav === 'active_alerts' ? 'active' : '' ?>">
                <i class="fa fa-bell nav-icon"></i> Active Alerts
                <?php if ($pendingCount > 0): ?>
                    <span class="nav-badge"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="dashboard_security.php?view=all_alerts"
               class="nav-link-item <?= $activeNav === 'all_alerts' ? 'active' : '' ?>">
                <i class="fa fa-table-list nav-icon"></i> All Requests
            </a>
            <a href="dashboard_security.php?view=incidents"
               class="nav-link-item <?= $activeNav === 'incidents' ? 'active' : '' ?>">
                <i class="fa fa-clipboard nav-icon"></i> Incident Records
            </a>

            <div class="nav-section-label">Account</div>
            <a href="settings_security.php"
               class="nav-link-item <?= $activeNav === 'settings' ? 'active' : '' ?>">
                <i class="fa fa-gear nav-icon"></i> Settings
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link-item" style="border-radius:8px;"
           onclick="return confirm('Are you sure you want to log out?')">
            <i class="fa fa-right-from-bracket nav-icon"></i>
            <span>Log Out</span>
        </a>
    </div>
</aside>

<!-- ─── MAIN CONTENT ─────────────────────────────────────────── -->
<div class="main-content">
    <!-- Top bar -->
    <div class="top-bar">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fa fa-bars"></i>
        </button>
        <div class="top-bar-title"><?= htmlspecialchars($pageTitle) ?></div>
        <div class="top-bar-right">
            <?php if ($isSecPersonnel): ?>
                <?php $ds = $_SESSION['duty_status'] ?? 'OFF_DUTY'; ?>
                <span class="duty-badge <?= $ds === 'ON_DUTY' ? 'duty-on' : 'duty-off' ?>">
                    <i class="fa fa-circle fa-xs"></i>
                    <?= $ds === 'ON_DUTY' ? 'On Duty' : 'Off Duty' ?>
                </span>
            <?php endif; ?>
            <span class="live-indicator" id="liveIndicator">
                <span class="notif-dot"></span> Live
            </span>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fa fa-user me-1"></i><?= $firstName ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= $isUniversity ? 'settings_user.php' : 'settings_security.php' ?>">
                        <i class="fa fa-gear me-2"></i>Settings
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"
                           onclick="return confirm('Log out?')">
                        <i class="fa fa-right-from-bracket me-2"></i>Log Out
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Flash messages -->
    <div id="flashArea" style="padding: 12px 28px 0;">
        <?= renderFlash() ?>
    </div>

    <!-- Page body starts here -->
    <div class="page-body">
