<?php
require_once 'config.php';
requireLogin();
requireRole(['admin', 'project_manager']);
$lang = getLang();
$pageTitle = $lang['reports'];

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-d');

// Task stats
$statusStats = $pdo->query("SELECT status, COUNT(*) as cnt FROM tasks WHERE status!='archived' GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$priorityStats = $pdo->query("SELECT priority, COUNT(*) as cnt FROM tasks WHERE status!='archived' GROUP BY priority")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status!='archived'")->fetchColumn();
$completedTasks = (int)($statusStats['completed'] ?? 0);

// Date-range stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);
$tasksInRange = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE completed_date BETWEEN ? AND ?");
$stmt->execute([$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);
$completedInRange = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM task_comments WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);
$commentsInRange = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM task_attachments WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);
$filesInRange = (int)$stmt->fetchColumn();

// Overdue tasks
$overdueTasks = $pdo->query("SELECT * FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed','archived') ORDER BY due_date ASC")->fetchAll();

// User activity
$userActivity = $pdo->query("SELECT u.name, u.role, COUNT(al.id) as actions, MAX(al.created_at) as last_action FROM users u LEFT JOIN activity_log al ON u.id=al.user_id WHERE u.is_active=1 GROUP BY u.id ORDER BY actions DESC")->fetchAll();

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tasks_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8
    fputcsv($output, ['ID', 'Title', 'Status', 'Priority', 'Category', 'Start Date', 'Due Date', 'Revisions', 'Created']);
    $allTasks = $pdo->query("SELECT * FROM tasks ORDER BY id")->fetchAll();
    foreach ($allTasks as $t) {
        fputcsv($output, [$t['id'], $t['title'], getStatusLabel($t['status']), getPriorityLabel($t['priority']), $t['category'], $t['start_date'], $t['due_date'], $t['revision_count'], $t['created_at']]);
    }
    fclose($output);
    exit;
}

require_once 'includes/header.php';
?>

<!-- Filter Bar -->
<div class="card mb-3">
    <div class="card-header">
        <form method="GET" class="search-bar" style="width:100%">
            <div class="form-group" style="margin:0">
                <label class="form-label" style="margin-bottom:4px"><?= e($lang['from_date']) ?></label>
                <input type="date" name="from" class="form-control" value="<?= e($fromDate) ?>" style="min-width:150px">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label" style="margin-bottom:4px"><?= e($lang['to_date']) ?></label>
                <input type="date" name="to" class="form-control" value="<?= e($toDate) ?>" style="min-width:150px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fas fa-filter"></i> <?= e($lang['generate_report']) ?></button>
            <a href="?export=csv" class="btn btn-success btn-sm" style="align-self:flex-end"><i class="fas fa-file-csv"></i> <?= e($lang['export_csv']) ?></a>
        </form>
    </div>
</div>

<!-- Period Stats -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-header"><div class="stat-icon blue"><i class="fas fa-plus-circle"></i></div></div>
        <div class="stat-value"><?= $tasksInRange ?></div>
        <div class="stat-label"><?= e($lang['new_tasks']) ?> (<?= e($lang['date_range']) ?>)</div>
    </div>
    <div class="stat-card green">
        <div class="stat-header"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div></div>
        <div class="stat-value"><?= $completedInRange ?></div>
        <div class="stat-label"><?= e($lang['completed_tasks']) ?> (<?= e($lang['date_range']) ?>)</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-header"><div class="stat-icon purple"><i class="fas fa-comments"></i></div></div>
        <div class="stat-value"><?= $commentsInRange ?></div>
        <div class="stat-label"><?= e($lang['comments']) ?> (<?= e($lang['date_range']) ?>)</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-header"><div class="stat-icon orange"><i class="fas fa-paperclip"></i></div></div>
        <div class="stat-value"><?= $filesInRange ?></div>
        <div class="stat-label"><?= e($lang['files']) ?> (<?= e($lang['date_range']) ?>)</div>
    </div>
</div>

<div class="grid-2">
    <!-- Tasks by Status -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie"></i> <?= e($lang['tasks_by_status']) ?></div></div>
        <div class="card-body">
            <?php foreach (getAllStatuses() as $st):
                $cnt = (int)($statusStats[$st['slug']] ?? 0);
                $pct = $totalTasks > 0 ? round(($cnt / $totalTasks) * 100) : 0;
            ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                <span class="badge" style="<?= getStatusStyle($st['slug']) ?>;min-width:120px;justify-content:center"><?= e(getStatusLabel($st['slug'])) ?></span>
                <div class="progress-bar" style="flex:1"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
                <span style="font-weight:700;min-width:50px;text-align:center"><?= $cnt ?></span>
                <span class="text-muted fs-sm" style="min-width:40px"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tasks by Priority -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar"></i> <?= e($lang['tasks_by_priority']) ?></div></div>
        <div class="card-body">
            <?php
            $allPriorities = ['urgent','high','medium','low'];
            foreach ($allPriorities as $p):
                $cnt = (int)($priorityStats[$p] ?? 0);
                $pct = $totalTasks > 0 ? round(($cnt / $totalTasks) * 100) : 0;
            ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                <span class="badge <?= getPriorityClass($p) ?>" style="min-width:120px;justify-content:center"><?= getPriorityLabel($p) ?></span>
                <div class="progress-bar" style="flex:1"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
                <span style="font-weight:700;min-width:50px;text-align:center"><?= $cnt ?></span>
                <span class="text-muted fs-sm" style="min-width:40px"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Overdue Tasks -->
<?php if (!empty($overdueTasks)): ?>
<div class="card mt-3">
    <div class="card-header">
        <div class="card-title text-danger"><i class="fas fa-exclamation-triangle"></i> <?= e($lang['overdue_tasks']) ?> (<?= count($overdueTasks) ?>)</div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>#</th><th><?= e($lang['task_title']) ?></th><th><?= e($lang['status']) ?></th><th><?= e($lang['priority']) ?></th><th><?= e($lang['task_due_date']) ?></th><th><?= e($lang['overdue_tasks']) ?></th></tr></thead>
            <tbody>
                <?php foreach ($overdueTasks as $t): ?>
                <tr onclick="window.location='task_view.php?id=<?= $t['id'] ?>'" style="cursor:pointer">
                    <td>#<?= $t['id'] ?></td>
                    <td><strong><?= e($t['title']) ?></strong></td>
                    <td><span class="badge" style="<?= getStatusStyle($t['status']) ?>"><?= getStatusLabel($t['status']) ?></span></td>
                    <td><span class="badge <?= getPriorityClass($t['priority']) ?>"><?= getPriorityLabel($t['priority']) ?></span></td>
                    <td><?= formatDate($t['due_date']) ?></td>
                    <td class="text-danger fw-bold"><?= abs((int)((strtotime($t['due_date']) - time()) / 86400)) ?> <?= e(getCurrentLanguage() === 'ar' ? 'يوم' : 'days') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- User Activity -->
<div class="card mt-3">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users"></i> <?= e($lang['user_activity']) ?></div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th><?= e($lang['user_name']) ?></th><th><?= e($lang['user_role']) ?></th><th><?= e($lang['activity_summary']) ?></th><th><?= e($lang['task_last_updated']) ?></th></tr></thead>
            <tbody>
                <?php foreach ($userActivity as $ua): ?>
                <tr>
                    <td><strong><?= e($ua['name']) ?></strong></td>
                    <td><span class="badge <?= $ua['role']==='admin'?'priority-urgent':($ua['role']==='project_manager'?'priority-high':'priority-medium') ?>"><?= e(getRoleLabel($ua['role'])) ?></span></td>
                    <td><?= $ua['actions'] ?> actions</td>
                    <td><?= $ua['last_action'] ? timeAgo($ua['last_action']) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
