<?php
require_once 'config.php';
requireLogin();
requireRole(['admin', 'project_manager']);
$lang = getLang();

$taskId = (int)($_GET['id'] ?? 0);
if (!$taskId) redirect('tasks.php');

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id=?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();
if (!$task) redirect('tasks.php');

$pageTitle = $lang['edit_task'] . ' - ' . $task['title'];
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? $task['status'];
    $priority = $_POST['priority'] ?? $task['priority'];
    $startDate = $_POST['start_date'] ?: null;
    $dueDate = $_POST['due_date'] ?: null;
    $progress = (int)($_POST['progress'] ?? 0);
    $internalNotes = trim($_POST['internal_notes'] ?? '');
    $clientNotes = trim($_POST['client_notes'] ?? '');
    $estimatedHours = $_POST['estimated_hours'] ?: null;

    if (empty($title)) {
        $msg = $lang['required_fields'];
        $msgType = 'danger';
    } else {
        $oldStatus = $task['status'];
        $completedDate = ($status === 'completed' && $oldStatus !== 'completed') ? date('Y-m-d H:i:s') : $task['completed_date'];
        $deliveryDate = ($status === 'delivered' && $oldStatus !== 'delivered') ? date('Y-m-d H:i:s') : $task['delivery_date'];
        $revisionCount = ($status === 'needs_revision' && $oldStatus !== 'needs_revision') ? $task['revision_count'] + 1 : $task['revision_count'];

        $pdo->prepare("UPDATE tasks SET title=?, description=?, category=?, status=?, priority=?, start_date=?, due_date=?, progress=?, internal_notes=?, client_notes=?, estimated_hours=?, completed_date=?, delivery_date=?, revision_count=? WHERE id=?")
            ->execute([$title, $description, $category, $status, $priority, $startDate, $dueDate, $progress, $internalNotes, $clientNotes, $estimatedHours, $completedDate, $deliveryDate, $revisionCount, $taskId]);

        if ($oldStatus !== $status) {
            logTaskStatusChange($taskId, getUserId(), $oldStatus, $status);
            notifyTaskEvent($taskId, 'status_changed', getUserId());
        }
        logActivity(getUserId(), 'edit_task', "Edited task: $title", 'task', $taskId);

        $msg = $lang['task_updated_success'];
        $msgType = 'success';

        // Refresh
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id=?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
    }
}

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
<?php endif; ?>

<div style="margin-bottom:16px">
    <a href="task_view.php?id=<?= $taskId ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right"></i> <?= e($lang['back']) ?></a>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-edit"></i> <?= e($lang['edit_task']) ?> #<?= $task['id'] ?></div>
    </div>
    <form method="POST">
        <?= csrfField() ?>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label"><?= e($lang['task_title']) ?> *</label>
                <input type="text" name="title" class="form-control" required value="<?= e($task['title']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e($lang['task_description']) ?></label>
                <textarea name="description" class="form-control" rows="4"><?= e($task['description']) ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= e($lang['task_category']) ?></label>
                    <input type="text" name="category" class="form-control" value="<?= e($task['category']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['priority']) ?></label>
                    <select name="priority" class="form-control">
                        <?php foreach (['low','medium','high','urgent'] as $p): ?>
                            <option value="<?= $p ?>" <?= $task['priority'] === $p ? 'selected' : '' ?>><?= getPriorityLabel($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= e($lang['status']) ?></label>
                    <select name="status" class="form-control">
                        <?php foreach (['new','in_progress','delivered','pending_review','needs_revision','completed','archived'] as $s): ?>
                            <option value="<?= $s ?>" <?= $task['status'] === $s ? 'selected' : '' ?>><?= getStatusLabel($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['task_progress']) ?> (%)</label>
                    <input type="number" name="progress" class="form-control" min="0" max="100" value="<?= $task['progress'] ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= e($lang['task_start_date']) ?></label>
                    <input type="date" name="start_date" class="form-control" value="<?= $task['start_date'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['task_due_date']) ?></label>
                    <input type="date" name="due_date" class="form-control" value="<?= $task['due_date'] ?? '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e($lang['task_client_notes']) ?></label>
                <textarea name="client_notes" class="form-control" rows="2"><?= e($task['client_notes']) ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e($lang['task_internal_notes']) ?></label>
                <textarea name="internal_notes" class="form-control" rows="2"><?= e($task['internal_notes']) ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e($lang['task_progress']) ?> (<?= e($lang['task_revision_count']) ?>: <?= $task['estimated_hours'] ?? '0' ?>h)</label>
                <input type="number" name="estimated_hours" class="form-control" step="0.5" min="0" value="<?= $task['estimated_hours'] ?? '' ?>">
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= e($lang['save']) ?></button>
            <a href="task_view.php?id=<?= $taskId ?>" class="btn btn-secondary"><?= e($lang['cancel']) ?></a>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
