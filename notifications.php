<?php
require_once 'config.php';
requireLogin();
$lang = getLang();
$pageTitle = $lang['notifications'];

// Mark all as read
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([getUserId()]);
    redirect('notifications.php');
}

// Mark single as read
if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id=? AND user_id=?");
    $stmt->execute([$nid, getUserId()]);
    $notif = $stmt->fetch();
    if ($notif) {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=?")->execute([$nid]);
        if ($notif['link']) redirect($notif['link']);
    }
    redirect('notifications.php');
}

// Fetch notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([getUserId()]);
$notifications = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-bell"></i> <?= e($lang['notifications']) ?></div>
        <?php if (!empty($notifications)): ?>
            <a href="?mark_all=1" class="btn btn-sm btn-secondary"><i class="fas fa-check-double"></i> <?= e($lang['mark_all_read']) ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding:0">
        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $n): ?>
                <a href="?read=<?= $n['id'] ?>" class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>" style="text-decoration:none;color:inherit">
                    <div class="notif-icon <?= e($n['type']) ?>">
                        <i class="fas fa-<?= $n['type'] === 'task' ? 'tasks' : ($n['type'] === 'success' ? 'check-circle' : ($n['type'] === 'warning' ? 'exclamation-triangle' : 'bell')) ?>"></i>
                    </div>
                    <div class="notif-content">
                        <div class="notif-title"><?= e($n['title']) ?></div>
                        <div class="notif-text"><?= e($n['message']) ?></div>
                    </div>
                    <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="padding:60px">
                <i class="fas fa-bell-slash"></i>
                <h3><?= e($lang['no_notifications']) ?></h3>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
