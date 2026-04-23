<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$lang = getLang();
$dir = getDirection();
$currentPage = basename($_SERVER['PHP_SELF']);
$unreadNotifications = getUnreadNotificationCount();
$currentUser = getCurrentUser();
$siteTitle = getSetting('site_title', 'Waves Platform');

function navActive($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="<?= $lang['lang_code'] ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? $lang['dashboard']) ?> - <?= e($siteTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="font-family:<?= $lang['font_family'] ?>">

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-logo">
            <img src="assets/img/waves-logo.png" alt="Waves" class="sidebar-logo-img">
        </a>
        <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?= navActive('index.php') ?>">
            <i class="fas fa-th-large"></i><span><?= e($lang['nav_dashboard']) ?></span>
        </a>
        <a href="tasks.php" class="nav-item <?= navActive('tasks.php') ?>">
            <i class="fas fa-tasks"></i><span><?= e($lang['nav_tasks']) ?></span>
        </a>

        <?php if (isAdmin()): ?>
            <a href="users.php" class="nav-item <?= navActive('users.php') ?>">
                <i class="fas fa-users"></i><span><?= e($lang['nav_users']) ?></span>
            </a>
            <a href="statuses.php" class="nav-item <?= navActive('statuses.php') ?>">
                <i class="fas fa-palette"></i><span><?= e($lang['lang_code'] === 'ar' ? 'إدارة الحالات' : 'Manage Statuses') ?></span>
            </a>
        <?php endif; ?>

        <?php if (isWavesSide()): ?>
            <a href="reports.php" class="nav-item <?= navActive('reports.php') ?>">
                <i class="fas fa-chart-bar"></i><span><?= e($lang['nav_reports']) ?></span>
            </a>
        <?php endif; ?>

        <div class="nav-divider"></div>

        <a href="notifications.php" class="nav-item <?= navActive('notifications.php') ?>">
            <i class="fas fa-bell"></i><span><?= e($lang['nav_notifications']) ?></span>
            <?php if ($unreadNotifications > 0): ?>
                <span class="nav-badge"><?= $unreadNotifications ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="nav-item <?= navActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i><span><?= e($lang['nav_profile']) ?></span>
        </a>

        <?php if (isAdmin()): ?>
            <a href="settings.php" class="nav-item <?= navActive('settings.php') ?>">
                <i class="fas fa-cog"></i><span><?= e($lang['nav_settings']) ?></span>
            </a>
        <?php endif; ?>

        <a href="logout.php" class="nav-item nav-item-danger">
            <i class="fas fa-sign-out-alt"></i><span><?= e($lang['logout']) ?></span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= mb_substr($currentUser['name'], 0, 1) ?></div>
            <div class="user-details">
                <span class="user-name"><?= e($currentUser['name']) ?></span>
                <span class="user-role"><?= e(getRoleLabel($currentUser['role'])) ?></span>
            </div>
        </div>
    </div>
</aside>

<!-- Main Content -->
<main class="main-content" id="mainContent">
    <header class="topbar">
        <div class="topbar-right">
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            <h2 class="page-title"><?= e($pageTitle ?? $lang['dashboard']) ?></h2>
        </div>
        <div class="topbar-left">
            <a href="<?= langUrl(getOtherLang()) ?>" class="lang-switch" title="<?= getOtherLangLabel() ?>">
                <i class="fas fa-globe"></i> <span><?= getOtherLangLabel() ?></span>
            </a>
            <div class="topbar-notifications" id="notifBtn">
                <i class="fas fa-bell"></i>
                <?php if ($unreadNotifications > 0): ?>
                    <span class="notif-badge"><?= $unreadNotifications ?></span>
                <?php endif; ?>
            </div>
            <div class="topbar-user">
                <span><?= e($currentUser['name']) ?></span>
                <div class="user-avatar-sm"><?= mb_substr($currentUser['name'], 0, 1) ?></div>
            </div>
        </div>
    </header>

    <div class="content-area">
