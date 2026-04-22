<?php
require_once 'config.php';
requireLogin();
$lang = getLang();
$pageTitle = $lang['dashboard'];

// --- Fetch all stats (single project - no project_id needed) ---
$totalTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status!='archived'")->fetchColumn();
$newTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status='new'")->fetchColumn();
$inProgressTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status='in_progress'")->fetchColumn();
$deliveredTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status='delivered'")->fetchColumn();
$pendingReview = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status='pending_review'")->fetchColumn();
$needsRevision = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status='needs_revision'")->fetchColumn();
$completedTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status='completed'")->fetchColumn();
$overdueTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed','archived')")->fetchColumn();
$completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

if (isAdmin()) {
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
}

// Recent tasks
$recentTasks = $pdo->query("SELECT t.*, u.name as creator_name FROM tasks t LEFT JOIN users u ON t.created_by=u.id WHERE t.status!='archived' ORDER BY t.updated_at DESC LIMIT 8")->fetchAll();

// Upcoming deadlines
$upcomingTasks = $pdo->query("SELECT * FROM tasks WHERE due_date >= CURDATE() AND status NOT IN ('completed','archived') ORDER BY due_date ASC LIMIT 6")->fetchAll();

// Recent comments
$recentComments = $pdo->query("SELECT c.*, u.name as author_name, u.role as author_role, t.title as task_title FROM task_comments c LEFT JOIN users u ON c.user_id=u.id LEFT JOIN tasks t ON c.task_id=t.id ORDER BY c.created_at DESC LIMIT 5")->fetchAll();

// Recent files
$recentFiles = $pdo->query("SELECT a.*, u.name as uploader_name, t.title as task_title FROM task_attachments a LEFT JOIN users u ON a.user_id=u.id LEFT JOIN tasks t ON a.task_id=t.id ORDER BY a.created_at DESC LIMIT 5")->fetchAll();

// Activity log (admin/pm)
if (isWavesSide()) {
    $activities = $pdo->query("SELECT al.*, u.name as user_name FROM activity_log al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 10")->fetchAll();
}

$projectTitle = getSetting('project_title', 'Waves × Details Project');
$projectDesc = getSetting('project_description', '');

require_once 'includes/header.php';
?>

<!-- Project Header -->
<div class="card mb-3">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div class="project-logos">
                <img src="assets/img/waves-logo.png" alt="Waves" style="height:36px;filter:brightness(0) invert(1);opacity:.85">
                <span style="color:var(--text-muted);font-size:18px;font-weight:300">&times;</span>
                <img src="assets/img/details-logo.png" alt="Details" style="height:36px;filter:brightness(0) invert(1);opacity:.85">
            </div>
            <div>
                <h2 style="font-size:18px;font-weight:800;margin-bottom:2px"><?= e($projectTitle) ?></h2>
                <?php if ($projectDesc): ?>
                    <p class="text-muted" style="font-size:13px"><?= e($projectDesc) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px">
            <div style="text-align:center">
                <div style="font-size:28px;font-weight:800;color:var(--primary-light)"><?= $completionRate ?>%</div>
                <div class="text-muted" style="font-size:12px"><?= e($lang['project_progress']) ?></div>
            </div>
            <div class="progress-bar" style="width:120px;height:8px">
                <div class="progress-fill" style="width:<?= $completionRate ?>%"></div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <?php if (isAdmin()): ?>
    <div class="stat-card purple">
        <div class="stat-header"><div class="stat-icon purple"><i class="fas fa-users"></i></div></div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label"><?= e($lang['total_users']) ?></div>
    </div>
    <?php endif; ?>
    <div class="stat-card blue">
        <div class="stat-header"><div class="stat-icon blue"><i class="fas fa-tasks"></i></div></div>
        <div class="stat-value"><?= $totalTasks ?></div>
        <div class="stat-label"><?= e($lang['total_tasks']) ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-header"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div></div>
        <div class="stat-value"><?= $completedTasks ?></div>
        <div class="stat-label"><?= e($lang['completed_tasks']) ?></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-header"><div class="stat-icon orange"><i class="fas fa-spinner"></i></div></div>
        <div class="stat-value"><?= $inProgressTasks ?></div>
        <div class="stat-label"><?= e($lang['in_progress_tasks']) ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-header"><div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div></div>
        <div class="stat-value"><?= $overdueTasks ?></div>
        <div class="stat-label"><?= e($lang['overdue_tasks']) ?></div>
    </div>
</div>

<!-- Status Breakdown -->
<div class="card mb-3">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-chart-pie"></i> <?= e($lang['tasks_by_status']) ?></div>
    </div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:12px">
            <?php
            $statuses = [
                'new' => ['icon' => 'fa-star', 'count' => $newTasks],
                'in_progress' => ['icon' => 'fa-spinner', 'count' => $inProgressTasks],
                'delivered' => ['icon' => 'fa-truck', 'count' => $deliveredTasks],
                'pending_review' => ['icon' => 'fa-eye', 'count' => $pendingReview],
                'needs_revision' => ['icon' => 'fa-redo', 'count' => $needsRevision],
                'completed' => ['icon' => 'fa-check-double', 'count' => $completedTasks],
            ];
            foreach ($statuses as $sk => $sv): ?>
                <div style="flex:1;min-width:120px;padding:14px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);text-align:center">
                    <div style="font-size:24px;font-weight:800;margin-bottom:4px"><?= $sv['count'] ?></div>
                    <span class="badge <?= getStatusClass($sk) ?>"><i class="fas <?= $sv['icon'] ?>"></i> <?= getStatusLabel($sk) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Recent / Upcoming Tasks -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-clock"></i> <?= e($lang['upcoming_deadlines']) ?></div>
            <a href="tasks.php" class="btn btn-sm btn-secondary"><?= e($lang['view_all']) ?></a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (!empty($upcomingTasks)): ?>
                <?php foreach ($upcomingTasks as $task): ?>
                    <div class="notif-item" onclick="window.location='task_view.php?id=<?= $task['id'] ?>'" style="cursor:pointer">
                        <div class="notif-icon task"><i class="fas fa-tasks"></i></div>
                        <div class="notif-content">
                            <div class="notif-title"><?= e($task['title']) ?></div>
                            <div class="notif-text">
                                <?php
                                $daysLeft = max(0, (int)((strtotime($task['due_date']) - time()) / 86400));
                                echo e(str_replace(':count', $daysLeft, $lang['days_left']));
                                ?>
                            </div>
                        </div>
                        <div>
                            <span class="badge <?= getStatusClass($task['status']) ?>"><?= getStatusLabel($task['status']) ?></span>
                            <div class="notif-time mt-1"><?= formatDate($task['due_date']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:40px"><i class="fas fa-calendar-check"></i><p><?= e($lang['no_tasks']) ?></p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Comments -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-comments"></i> <?= e($lang['recent_comments']) ?></div>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (!empty($recentComments)): ?>
                <?php foreach ($recentComments as $c): ?>
                    <div class="notif-item" onclick="window.location='task_view.php?id=<?= $c['task_id'] ?>'" style="cursor:pointer">
                        <div class="comment-avatar" style="width:36px;height:36px;font-size:13px"><?= mb_substr($c['author_name'], 0, 1) ?></div>
                        <div class="notif-content">
                            <div class="notif-title"><?= e($c['author_name']) ?>
                                <span class="badge <?= ($c['author_role'] === 'client') ? 'status-delivered' : 'status-new' ?>" style="font-size:10px;padding:2px 6px"><?= e(getRoleLabel($c['author_role'])) ?></span>
                            </div>
                            <div class="notif-text"><?= e(mb_substr($c['comment'], 0, 80)) ?></div>
                            <div class="text-muted" style="font-size:11px;margin-top:2px"><?= e($c['task_title'] ?? '') ?></div>
                        </div>
                        <div class="notif-time"><?= timeAgo($c['created_at']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:40px"><i class="fas fa-comments"></i><p><?= e($lang['no_comments']) ?></p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid-2 mt-3">
    <!-- Recent Files -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-paperclip"></i> <?= e($lang['recent_files']) ?></div>
        </div>
        <div class="card-body">
            <?php if (!empty($recentFiles)): ?>
                <?php foreach ($recentFiles as $f): ?>
                    <div class="file-list-item">
                        <i class="fas <?= getFileIcon($f['original_name']) ?>"></i>
                        <span class="file-name"><?= e($f['original_name']) ?></span>
                        <span class="file-size"><?= formatFileSize($f['file_size']) ?></span>
                        <span class="text-muted fs-sm"><?= e($f['uploader_name']) ?></span>
                        <a href="download.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-download"></i></a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-muted" style="padding:24px"><?= e($lang['no_files']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity Log (admin/pm) or Quick Actions -->
    <?php if (isWavesSide()): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-history"></i> <?= e($lang['activity_log']) ?></div>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (!empty($activities)): ?>
                <?php foreach ($activities as $act): ?>
                    <div class="notif-item">
                        <div class="notif-icon info"><i class="fas fa-circle-info"></i></div>
                        <div class="notif-content">
                            <div class="notif-title"><?= e($act['user_name'] ?? 'System') ?></div>
                            <div class="notif-text"><?= e($act['description']) ?></div>
                        </div>
                        <div class="notif-time"><?= timeAgo($act['created_at']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:40px"><i class="fas fa-history"></i><p><?= e($lang['no_data']) ?></p></div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-bolt"></i> <?= e($lang['quick_actions']) ?></div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
            <a href="tasks.php?status=pending_review" class="btn btn-secondary w-100" style="justify-content:flex-start"><i class="fas fa-eye"></i> <?= e($lang['pending_review_tasks']) ?> (<?= $pendingReview ?>)</a>
            <a href="tasks.php?status=delivered" class="btn btn-secondary w-100" style="justify-content:flex-start"><i class="fas fa-truck"></i> <?= e($lang['delivered_tasks']) ?> (<?= $deliveredTasks ?>)</a>
            <a href="tasks.php?status=needs_revision" class="btn btn-secondary w-100" style="justify-content:flex-start"><i class="fas fa-redo"></i> <?= e($lang['needs_revision_tasks']) ?> (<?= $needsRevision ?>)</a>
            <a href="notifications.php" class="btn btn-secondary w-100" style="justify-content:flex-start"><i class="fas fa-bell"></i> <?= e($lang['notifications']) ?></a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
