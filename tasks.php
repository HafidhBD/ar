<?php
require_once 'config.php';
requireLogin();
$lang = getLang();
$pageTitle = $lang['tasks'];
$msg = '';
$msgType = '';

// Auto-migrate: add notify_users column if missing
try { $pdo->query("SELECT notify_users FROM tasks LIMIT 1"); } catch (Exception $e) {
    try { $pdo->exec("ALTER TABLE tasks ADD COLUMN notify_users TEXT DEFAULT NULL AFTER estimated_hours"); } catch (Exception $e2) {}
}

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
        $internalNotes = trim($_POST['internal_notes'] ?? '');
        $clientNotes = trim($_POST['client_notes'] ?? '');
        $estimatedHours = $_POST['estimated_hours'] ?: null;

        // Notification recipients
        $notifyUserIds = $_POST['notify_users'] ?? [];
        $notifyUsersStr = !empty($notifyUserIds) ? implode(',', array_map('intval', $notifyUserIds)) : null;

        if (empty($title)) {
            $msg = $lang['required_fields'];
            $msgType = 'danger';
        } else {
            $stmt = $pdo->prepare("INSERT INTO tasks (title, description, category, status, priority, start_date, due_date, created_by, internal_notes, client_notes, estimated_hours, notify_users) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$title, $description, $category, $status, $priority, $startDate, $dueDate, getUserId(), $internalNotes, $clientNotes, $estimatedHours, $notifyUsersStr]);
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

// Get all active users for notification selector
$allUsers = $pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY role, name")->fetchAll();

// All statuses from DB
$allDbStatuses = getAllStatuses();
$allStatuses = array_column($allDbStatuses, 'slug');

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
                <?php foreach ($allDbStatuses as $st): ?>
                    <option value="<?= e($st['slug']) ?>" <?= $filterStatus === $st['slug'] ? 'selected' : '' ?>><?= e(getStatusLabel($st['slug'])) ?></option>
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
                        <?php
                            $curSt = getStatusBySlug($task['status']);
                            $stColor = $curSt ? $curSt['color'] : '#64748b';
                            $stBg = $curSt ? $curSt['bg_color'] : '#f1f5f9';
                        ?>
                        <?php if (isWavesSide()): ?>
                            <form method="POST" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <select name="status" class="status-select" style="background:<?= e($stBg) ?>;color:<?= e($stColor) ?>;border:1px solid <?= e($stColor) ?>30;font-weight:700;font-size:12px;padding:6px 12px;border-radius:20px;cursor:pointer;min-width:130px;appearance:none;-webkit-appearance:none;text-align:center;font-family:inherit" onchange="this.form.submit()">
                                    <?php foreach ($allDbStatuses as $st): ?>
                                        <option value="<?= e($st['slug']) ?>" <?= $task['status'] === $st['slug'] ? 'selected' : '' ?> data-color="<?= e($st['color']) ?>" data-bg="<?= e($st['bg_color']) ?>"><?= e(getStatusLabel($st['slug'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?>
                            <span class="badge" style="<?= getStatusStyle($task['status']) ?>"><?= getStatusLabel($task['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= getPriorityClass($task['priority']) ?>"><?= getPriorityLabel($task['priority']) ?></span></td>
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
        <div class="task-card-meta">
            <span class="badge" style="<?= getStatusStyle($task['status']) ?>"><?= getStatusLabel($task['status']) ?></span>
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
            <h3><span class="badge" style="<?= getStatusStyle($ks) ?>"><?= getStatusLabel($ks) ?></span></h3>
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
                            <?php foreach ($allDbStatuses as $st): ?>
                                <option value="<?= e($st['slug']) ?>" <?= $st['is_default'] ? 'selected' : '' ?>><?= e(getStatusLabel($st['slug'])) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                    <label class="form-label"><i class="fas fa-bell"></i> <?= e($lang['lang_code'] === 'ar' ? 'إرسال إشعارات إلى' : 'Send notifications to') ?></label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;padding:12px;background:#f8fafc;border:1px solid var(--border);border-radius:var(--radius)">
                        <?php foreach ($allUsers as $u): ?>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;padding:4px 8px;border-radius:6px;background:#fff;border:1px solid var(--border)">
                                <input type="checkbox" name="notify_users[]" value="<?= $u['id'] ?>" checked style="accent-color:#6366f1">
                                <?= e($u['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text"><?= e($lang['lang_code'] === 'ar' ? 'ألغِ التحديد لاستثناء مستخدم من الإشعارات' : 'Uncheck to exclude a user from notifications') ?></div>
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
<?php
$extraJS = "<script>initFileUpload('taskDropArea','taskFileInput','taskFileList');</script>";
endif; ?>

<script>
// Live color update for status selects
document.querySelectorAll('.status-select').forEach(function(sel){
    sel.addEventListener('change', function(){
        var opt = this.options[this.selectedIndex];
        var c = opt.getAttribute('data-color');
        var bg = opt.getAttribute('data-bg');
        if(c && bg){
            this.style.background = bg;
            this.style.color = c;
            this.style.borderColor = c + '30';
        }
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
