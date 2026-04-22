<?php
require_once 'config.php';
requireLogin();
$lang = getLang();
$pageTitle = $lang['tasks'];
$msg = '';
$msgType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // Create task (PM/Admin only)
    if (isset($_POST['action']) && $_POST['action'] === 'create' && isWavesSide()) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $status = $_POST['status'] ?? 'new';
        $priority = $_POST['priority'] ?? 'medium';
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
            $stmt = $pdo->prepare("INSERT INTO tasks (title, description, category, status, priority, start_date, due_date, progress, created_by, internal_notes, client_notes, estimated_hours) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$title, $description, $category, $status, $priority, $startDate, $dueDate, $progress, getUserId(), $internalNotes, $clientNotes, $estimatedHours]);
            $taskId = $pdo->lastInsertId();

            // Handle file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                $uploadDir = UPLOAD_DIR . 'attachments/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                foreach ($_FILES['attachments']['name'] as $i => $fname) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK && !isDangerousFile($fname) && isAllowedExtension($fname)) {
                        $ext = pathinfo($fname, PATHINFO_EXTENSION);
                        $newName = uniqid('task_') . '.' . $ext;
                        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $uploadDir . $newName)) {
                            $pdo->prepare("INSERT INTO task_attachments (task_id, user_id, file_name, original_name, file_size, file_type) VALUES (?,?,?,?,?,?)")
                                ->execute([$taskId, getUserId(), $newName, $fname, $_FILES['attachments']['size'][$i], $_FILES['attachments']['type'][$i]]);
                        }
                    }
                }
            }

            logTaskStatusChange($taskId, getUserId(), null, $status, 'Task created');
            logActivity(getUserId(), 'create_task', "Created task: $title", 'task', $taskId);
            notifyTaskEvent($taskId, 'created', getUserId());

            $msg = $lang['task_created_success'];
            $msgType = 'success';
        }
    }

    // Update status quick-action
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $taskId = (int)$_POST['task_id'];
        $newStatus = $_POST['status'];
        $task = $pdo->prepare("SELECT * FROM tasks WHERE id=?");
        $task->execute([$taskId]);
        $t = $task->fetch();

        if ($t) {
            $oldStatus = $t['status'];
            $updates = ['status' => $newStatus];

            if ($newStatus === 'completed') $updates['completed_date'] = date('Y-m-d H:i:s');
            if ($newStatus === 'delivered') $updates['delivery_date'] = date('Y-m-d H:i:s');
            if ($newStatus === 'needs_revision') $updates['revision_count'] = $t['revision_count'] + 1;

            $sets = [];
            $vals = [];
            foreach ($updates as $k => $v) { $sets[] = "$k=?"; $vals[] = $v; }
            $vals[] = $taskId;
            $pdo->prepare("UPDATE tasks SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);

            logTaskStatusChange($taskId, getUserId(), $oldStatus, $newStatus);
            logActivity(getUserId(), 'update_task_status', "Changed status: {$t['title']} ($oldStatus -> $newStatus)", 'task', $taskId);
            notifyTaskEvent($taskId, 'status_changed', getUserId());

            $msg = $lang['task_updated_success'];
            $msgType = 'success';
        }
    }

    // Delete task
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isWavesSide()) {
        $taskId = (int)$_POST['task_id'];
        $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$taskId]);
        logActivity(getUserId(), 'delete_task', 'Deleted task #' . $taskId, 'task', $taskId);
        $msg = $lang['task_deleted_success'];
        $msgType = 'success';
    }
}

// Filters
$search = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterPriority = $_GET['priority'] ?? '';
$filterCategory = $_GET['category'] ?? '';
$view = $_GET['view'] ?? 'list';

$where = "WHERE status != 'archived'";
$params = [];

if ($search) {
    $where .= " AND (title LIKE ? OR description LIKE ? OR category LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($filterStatus) { $where .= " AND status=?"; $params[] = $filterStatus; }
if ($filterPriority) { $where .= " AND priority=?"; $params[] = $filterPriority; }
if ($filterCategory) { $where .= " AND category=?"; $params[] = $filterCategory; }

// Hide internal notes from clients
$selectCols = "t.*, u.name as creator_name";
$stmt = $pdo->prepare("SELECT $selectCols FROM tasks t LEFT JOIN users u ON t.created_by=u.id $where ORDER BY t.created_at DESC");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Get distinct categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM tasks WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// All statuses for the kanban view
$allStatuses = ['new', 'in_progress', 'delivered', 'pending_review', 'needs_revision', 'completed'];

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
<?php endif; ?>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-header" style="flex-wrap:wrap;gap:12px">
        <form method="GET" class="search-bar" style="flex:1;min-width:300px">
            <div class="search-input">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="<?= e($lang['search']) ?>..." value="<?= e($search) ?>">
            </div>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value=""><?= e($lang['all']) ?> <?= e($lang['status']) ?></option>
                <?php foreach ($allStatuses as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= getStatusLabel($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="priority" class="filter-select" onchange="this.form.submit()">
                <option value=""><?= e($lang['all']) ?> <?= e($lang['priority']) ?></option>
                <option value="low" <?= $filterPriority === 'low' ? 'selected' : '' ?>><?= e($lang['priority_low']) ?></option>
                <option value="medium" <?= $filterPriority === 'medium' ? 'selected' : '' ?>><?= e($lang['priority_medium']) ?></option>
                <option value="high" <?= $filterPriority === 'high' ? 'selected' : '' ?>><?= e($lang['priority_high']) ?></option>
                <option value="urgent" <?= $filterPriority === 'urgent' ? 'selected' : '' ?>><?= e($lang['priority_urgent']) ?></option>
            </select>
            <?php if (!empty($categories)): ?>
            <select name="category" class="filter-select" onchange="this.form.submit()">
                <option value=""><?= e($lang['all']) ?> <?= e($lang['task_category']) ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
        </form>
        <div style="display:flex;gap:8px;align-items:center">
            <div class="view-toggle">
                <button onclick="location.href='?view=list<?= $filterStatus ? '&status='.$filterStatus : '' ?>'" class="<?= $view === 'list' ? 'active' : '' ?>" title="<?= e($lang['view_list']) ?>"><i class="fas fa-list"></i></button>
                <button onclick="location.href='?view=cards<?= $filterStatus ? '&status='.$filterStatus : '' ?>'" class="<?= $view === 'cards' ? 'active' : '' ?>" title="<?= e($lang['view_cards']) ?>"><i class="fas fa-th-large"></i></button>
                <button onclick="location.href='?view=kanban<?= $filterStatus ? '&status='.$filterStatus : '' ?>'" class="<?= $view === 'kanban' ? 'active' : '' ?>" title="<?= e($lang['view_kanban']) ?>"><i class="fas fa-columns"></i></button>
            </div>
            <?php if (isWavesSide()): ?>
                <button class="btn btn-primary" onclick="openModal('createTaskModal')"><i class="fas fa-plus"></i> <?= e($lang['create_task']) ?></button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($view === 'list'): ?>
<!-- TABLE VIEW -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e($lang['task_title']) ?></th>
                    <th><?= e($lang['task_category']) ?></th>
                    <th><?= e($lang['status']) ?></th>
                    <th><?= e($lang['priority']) ?></th>
                    <th><?= e($lang['task_progress']) ?></th>
                    <th><?= e($lang['task_due_date']) ?></th>
                    <th><?= e($lang['actions']) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                <tr>
                    <td class="text-muted">#<?= $task['id'] ?></td>
                    <td>
                        <a href="task_view.php?id=<?= $task['id'] ?>" style="color:var(--text-primary);font-weight:600"><?= e($task['title']) ?></a>
                        <?php if ($task['description']): ?>
                            <div class="text-muted fs-sm" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(mb_substr($task['description'], 0, 60)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $task['category'] ? '<span class="badge status-archived">' . e($task['category']) . '</span>' : '-' ?></td>
                    <td>
                        <?php if (isWavesSide()): ?>
                            <form method="POST" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <select name="status" class="filter-select" style="min-width:130px;font-size:12px;padding:5px 8px;padding-inline-start:28px" onchange="this.form.submit()">
                                    <?php foreach ($allStatuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $task['status'] === $s ? 'selected' : '' ?>><?= getStatusLabel($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?>
                            <span class="badge <?= getStatusClass($task['status']) ?>"><?= getStatusLabel($task['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= getPriorityClass($task['priority']) ?>"><?= getPriorityLabel($task['priority']) ?></span></td>
                    <td>
                        <div class="progress-bar" style="width:70px"><div class="progress-fill" style="width:<?= $task['progress'] ?>%"></div></div>
                        <span class="progress-text"><?= $task['progress'] ?>%</span>
                    </td>
                    <td>
                        <?= formatDate($task['due_date']) ?>
                        <?php if ($task['due_date'] && !in_array($task['status'], ['completed', 'archived'])): ?>
                            <?php $daysLeft = (int)((strtotime($task['due_date']) - time()) / 86400); ?>
                            <?php if ($daysLeft < 0): ?>
                                <div class="text-danger fs-sm"><?= e(str_replace(':count', abs($daysLeft), $lang['overdue_by'])) ?></div>
                            <?php elseif ($daysLeft <= 3): ?>
                                <div class="text-warning fs-sm"><?= e(str_replace(':count', $daysLeft, $lang['days_left'])) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="task_view.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-eye"></i></a>
                            <?php if (isWavesSide()): ?>
                                <a href="task_edit.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i></a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('<?= e($lang['confirm_delete']) ?>')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?>
                <tr><td colspan="8">
                    <div class="empty-state"><i class="fas fa-tasks"></i><h3><?= e($lang['no_tasks']) ?></h3></div>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($view === 'cards'): ?>
<!-- CARDS VIEW -->
<div class="tasks-grid">
    <?php foreach ($tasks as $task): ?>
    <div class="task-card" onclick="window.location='task_view.php?id=<?= $task['id'] ?>'">
        <div class="task-card-header">
            <div>
                <span class="text-muted fs-sm">#<?= $task['id'] ?></span>
                <div class="task-card-title"><?= e($task['title']) ?></div>
            </div>
            <span class="badge <?= getPriorityClass($task['priority']) ?>"><?= getPriorityLabel($task['priority']) ?></span>
        </div>
        <?php if ($task['description']): ?>
            <p class="text-muted fs-sm" style="margin-bottom:12px"><?= e(mb_substr($task['description'], 0, 100)) ?></p>
        <?php endif; ?>
        <div style="margin-bottom:10px">
            <div class="progress-bar"><div class="progress-fill" style="width:<?= $task['progress'] ?>%"></div></div>
            <span class="progress-text"><?= $task['progress'] ?>%</span>
        </div>
        <div class="task-card-meta">
            <span class="badge <?= getStatusClass($task['status']) ?>"><?= getStatusLabel($task['status']) ?></span>
            <?php if ($task['category']): ?><span class="badge status-archived"><?= e($task['category']) ?></span><?php endif; ?>
            <?php if ($task['due_date']): ?><div class="task-meta-item"><i class="fas fa-calendar"></i> <?= formatDate($task['due_date']) ?></div><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($tasks)): ?>
    <div class="empty-state" style="grid-column:1/-1"><i class="fas fa-tasks"></i><h3><?= e($lang['no_tasks']) ?></h3></div>
    <?php endif; ?>
</div>

<?php elseif ($view === 'kanban'): ?>
<!-- KANBAN VIEW -->
<div class="kanban-board">
    <?php foreach ($allStatuses as $ks):
        $kanbanTasks = array_filter($tasks, fn($t) => $t['status'] === $ks);
    ?>
    <div class="kanban-column">
        <div class="kanban-header">
            <h3><span class="badge <?= getStatusClass($ks) ?>"><?= getStatusLabel($ks) ?></span></h3>
            <span class="kanban-count"><?= count($kanbanTasks) ?></span>
        </div>
        <div class="kanban-body">
            <?php foreach ($kanbanTasks as $task): ?>
            <div class="kanban-card" onclick="window.location='task_view.php?id=<?= $task['id'] ?>'">
                <div class="kanban-card-title"><?= e($task['title']) ?></div>
                <div class="kanban-card-meta">
                    <span class="badge <?= getPriorityClass($task['priority']) ?>"><?= getPriorityLabel($task['priority']) ?></span>
                    <?php if ($task['due_date']): ?><span><i class="fas fa-calendar"></i> <?= formatDate($task['due_date']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (isWavesSide()): ?>
<!-- Create Task Modal -->
<div class="modal-overlay" id="createTaskModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plus-circle"></i> <?= e($lang['create_task']) ?></div>
            <button class="modal-close" onclick="closeModal('createTaskModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= e($lang['task_title']) ?> *</label>
                    <input type="text" name="title" class="form-control" required placeholder="<?= e($lang['task_title']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['task_description']) ?></label>
                    <textarea name="description" class="form-control" rows="3" placeholder="<?= e($lang['task_description']) ?>"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['task_category']) ?></label>
                        <input type="text" name="category" class="form-control" placeholder="<?= e($lang['task_category']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['priority']) ?></label>
                        <select name="priority" class="form-control">
                            <option value="low"><?= e($lang['priority_low']) ?></option>
                            <option value="medium" selected><?= e($lang['priority_medium']) ?></option>
                            <option value="high"><?= e($lang['priority_high']) ?></option>
                            <option value="urgent"><?= e($lang['priority_urgent']) ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['task_start_date']) ?></label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['task_due_date']) ?></label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['status']) ?></label>
                        <select name="status" class="form-control">
                            <option value="new"><?= e($lang['status_new']) ?></option>
                            <option value="in_progress"><?= e($lang['status_in_progress']) ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['task_progress']) ?> (%)</label>
                        <input type="number" name="progress" class="form-control" min="0" max="100" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['task_client_notes']) ?></label>
                    <textarea name="client_notes" class="form-control" rows="2" placeholder="<?= e($lang['task_client_notes']) ?>"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['task_internal_notes']) ?></label>
                    <textarea name="internal_notes" class="form-control" rows="2" placeholder="<?= e($lang['task_internal_notes']) ?>"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['files']) ?></label>
                    <div class="file-upload-area" id="taskDropArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p><?= e($lang['drag_drop']) ?> <span><?= e($lang['click_upload']) ?></span></p>
                    </div>
                    <input type="file" name="attachments[]" id="taskFileInput" multiple style="display:none">
                    <ul class="file-list" id="taskFileList"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> <?= e($lang['create_task']) ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createTaskModal')"><?= e($lang['cancel']) ?></button>
            </div>
        </form>
    </div>
</div>
<script>initFileUpload('taskDropArea','taskFileInput','taskFileList');</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
