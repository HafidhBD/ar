<?php
require_once 'config.php';
requireLogin();
$lang = getLang();

$taskId = (int)($_GET['id'] ?? 0);
if (!$taskId) redirect('tasks.php');

$stmt = $pdo->prepare("SELECT t.*, u.name as creator_name FROM tasks t LEFT JOIN users u ON t.created_by=u.id WHERE t.id=?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();
if (!$task) redirect('tasks.php');

$pageTitle = $task['title'];
$msg = '';
$msgType = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // Add comment
    if (($_POST['action'] ?? '') === 'comment') {
        $comment = trim($_POST['comment'] ?? '');
        if (!empty($comment)) {
            $stmt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?,?,?)");
            $stmt->execute([$taskId, getUserId(), $comment]);
            $commentId = $pdo->lastInsertId();

            // Attachments on comment
            if (!empty($_FILES['comment_files']['name'][0])) {
                $uploadDir = UPLOAD_DIR . 'attachments/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                foreach ($_FILES['comment_files']['name'] as $i => $fname) {
                    if ($_FILES['comment_files']['error'][$i] === UPLOAD_ERR_OK && !isDangerousFile($fname) && isAllowedExtension($fname)) {
                        $ext = pathinfo($fname, PATHINFO_EXTENSION);
                        $newName = uniqid('cmt_') . '.' . $ext;
                        if (move_uploaded_file($_FILES['comment_files']['tmp_name'][$i], $uploadDir . $newName)) {
                            $pdo->prepare("INSERT INTO task_attachments (task_id, comment_id, user_id, file_name, original_name, file_size, file_type) VALUES (?,?,?,?,?,?,?)")
                                ->execute([$taskId, $commentId, getUserId(), $newName, $fname, $_FILES['comment_files']['size'][$i], $_FILES['comment_files']['type'][$i]]);
                        }
                    }
                }
            }

            logActivity(getUserId(), 'add_comment', "Comment on: {$task['title']}", 'task', $taskId);
            notifyTaskEvent($taskId, 'comment', getUserId());
            $msg = $lang['comment_added'];
            $msgType = 'success';
        }
    }

    // Upload files
    if (($_POST['action'] ?? '') === 'upload_files') {
        if (!empty($_FILES['delivery_files']['name'][0])) {
            $uploadDir = UPLOAD_DIR . 'attachments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $uploaded = 0;
            foreach ($_FILES['delivery_files']['name'] as $i => $fname) {
                if ($_FILES['delivery_files']['error'][$i] === UPLOAD_ERR_OK && !isDangerousFile($fname) && isAllowedExtension($fname)) {
                    $ext = pathinfo($fname, PATHINFO_EXTENSION);
                    $newName = uniqid('file_') . '.' . $ext;
                    if (move_uploaded_file($_FILES['delivery_files']['tmp_name'][$i], $uploadDir . $newName)) {
                        $pdo->prepare("INSERT INTO task_attachments (task_id, user_id, file_name, original_name, file_size, file_type) VALUES (?,?,?,?,?,?)")
                            ->execute([$taskId, getUserId(), $newName, $fname, $_FILES['delivery_files']['size'][$i], $_FILES['delivery_files']['type'][$i]]);
                        $uploaded++;
                    }
                }
            }
            if ($uploaded > 0) {
                logActivity(getUserId(), 'upload_file', "Uploaded $uploaded files to: {$task['title']}", 'task', $taskId);
                notifyTaskEvent($taskId, 'file', getUserId());
                $msg = $lang['files_uploaded_success'];
                $msgType = 'success';
            }
        }
    }

    // Update task (PM/Admin)
    if (($_POST['action'] ?? '') === 'update_task' && isWavesSide()) {
        $newStatus = $_POST['status'] ?? $task['status'];
        $newPriority = $_POST['priority'] ?? $task['priority'];
        $newDueDate = $_POST['due_date'] ?: null;
        $newProgress = (int)($_POST['progress'] ?? $task['progress']);

        $oldStatus = $task['status'];
        $completedDate = ($newStatus === 'completed' && $oldStatus !== 'completed') ? date('Y-m-d H:i:s') : $task['completed_date'];
        $deliveryDate = ($newStatus === 'delivered' && $oldStatus !== 'delivered') ? date('Y-m-d H:i:s') : $task['delivery_date'];
        $revisionCount = ($newStatus === 'needs_revision' && $oldStatus !== 'needs_revision') ? $task['revision_count'] + 1 : $task['revision_count'];

        $pdo->prepare("UPDATE tasks SET status=?, priority=?, due_date=?, progress=?, completed_date=?, delivery_date=?, revision_count=? WHERE id=?")
            ->execute([$newStatus, $newPriority, $newDueDate, $newProgress, $completedDate, $deliveryDate, $revisionCount, $taskId]);

        if ($oldStatus !== $newStatus) {
            logTaskStatusChange($taskId, getUserId(), $oldStatus, $newStatus);
            notifyTaskEvent($taskId, 'status_changed', getUserId());
        }
        logActivity(getUserId(), 'update_task', "Updated: {$task['title']}", 'task', $taskId);

        // Refresh task data
        $stmt = $pdo->prepare("SELECT t.*, u.name as creator_name FROM tasks t LEFT JOIN users u ON t.created_by=u.id WHERE t.id=?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        $msg = $lang['task_updated_success'];
        $msgType = 'success';
    }

    // Client: Request revision or approve
    if (($_POST['action'] ?? '') === 'client_action' && isClient()) {
        $clientAction = $_POST['client_status'] ?? '';
        if ($clientAction === 'needs_revision') {
            $oldStatus = $task['status'];
            $pdo->prepare("UPDATE tasks SET status='needs_revision', revision_count=revision_count+1 WHERE id=?")->execute([$taskId]);
            logTaskStatusChange($taskId, getUserId(), $oldStatus, 'needs_revision');
            notifyTaskEvent($taskId, 'status_changed', getUserId());
            // Refresh
            $stmt = $pdo->prepare("SELECT t.*, u.name as creator_name FROM tasks t LEFT JOIN users u ON t.created_by=u.id WHERE t.id=?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            $msg = $lang['task_updated_success'];
            $msgType = 'success';
        }
    }
}

// Fetch comments
$comments = $pdo->prepare("SELECT c.*, u.name as author_name, u.role as author_role FROM task_comments c LEFT JOIN users u ON c.user_id=u.id WHERE c.task_id=? ORDER BY c.created_at ASC");
$comments->execute([$taskId]);
$commentsList = $comments->fetchAll();

// Fetch comment attachments
$commentAttachments = [];
if (!empty($commentsList)) {
    $cIds = array_column($commentsList, 'id');
    $ph = implode(',', array_fill(0, count($cIds), '?'));
    $attStmt = $pdo->prepare("SELECT * FROM task_attachments WHERE comment_id IN ($ph)");
    $attStmt->execute($cIds);
    foreach ($attStmt->fetchAll() as $att) {
        $commentAttachments[$att['comment_id']][] = $att;
    }
}

// Fetch task files (no comment_id)
$taskFiles = $pdo->prepare("SELECT a.*, u.name as uploader_name FROM task_attachments a LEFT JOIN users u ON a.user_id=u.id WHERE a.task_id=? AND a.comment_id IS NULL ORDER BY a.created_at DESC");
$taskFiles->execute([$taskId]);
$filesList = $taskFiles->fetchAll();

// Fetch status history
$history = $pdo->prepare("SELECT h.*, u.name as user_name FROM task_status_history h LEFT JOIN users u ON h.user_id=u.id WHERE h.task_id=? ORDER BY h.created_at DESC");
$history->execute([$taskId]);
$historyList = $history->fetchAll();

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
<?php endif; ?>

<!-- Breadcrumb -->
<div style="margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;flex-wrap:wrap">
    <a href="tasks.php" class="text-muted"><?= e($lang['tasks']) ?></a>
    <i class="fas fa-chevron-left text-muted" style="font-size:9px"></i>
    <span>#<?= $task['id'] ?> - <?= e($task['title']) ?></span>
</div>

<div class="detail-grid">
    <!-- Main Content -->
    <div>
        <!-- Task Header Card -->
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title" style="font-size:17px"><?= e($task['title']) ?></div>
                <div class="btn-group">
                    <span class="badge <?= getStatusClass($task['status']) ?>" style="font-size:13px;padding:6px 14px"><?= getStatusLabel($task['status']) ?></span>
                    <span class="badge <?= getPriorityClass($task['priority']) ?>" style="font-size:13px;padding:6px 14px"><?= getPriorityLabel($task['priority']) ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($task['description']): ?>
                    <div style="line-height:1.9;font-size:14px;color:var(--text-secondary);margin-bottom:16px"><?= nl2br(e($task['description'])) ?></div>
                <?php endif; ?>

                <div class="detail-info">
                    <div class="detail-item"><div class="detail-label"><?= e($lang['task_id']) ?></div><div class="detail-value">#<?= $task['id'] ?></div></div>
                    <div class="detail-item"><div class="detail-label"><?= e($lang['task_created_by']) ?></div><div class="detail-value"><?= e($task['creator_name'] ?? '-') ?></div></div>
                    <div class="detail-item"><div class="detail-label"><?= e($lang['task_start_date']) ?></div><div class="detail-value"><?= formatDate($task['start_date']) ?></div></div>
                    <div class="detail-item"><div class="detail-label"><?= e($lang['task_due_date']) ?></div><div class="detail-value"><?= formatDate($task['due_date']) ?></div></div>
                    <?php if ($task['delivery_date']): ?>
                    <div class="detail-item"><div class="detail-label"><?= e($lang['task_delivery_date']) ?></div><div class="detail-value"><?= formatDateTime($task['delivery_date']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($task['completed_date']): ?>
                    <div class="detail-item"><div class="detail-label"><?= e($lang['status_completed']) ?></div><div class="detail-value"><?= formatDateTime($task['completed_date']) ?></div></div>
                    <?php endif; ?>
                    <div class="detail-item"><div class="detail-label"><?= e($lang['task_progress']) ?></div><div class="detail-value"><?= $task['progress'] ?>%</div></div>
                    <div class="detail-item"><div class="detail-label"><?= e($lang['task_revision_count']) ?></div><div class="detail-value"><?= $task['revision_count'] ?></div></div>
                    <?php if ($task['category']): ?>
                    <div class="detail-item"><div class="detail-label"><?= e($lang['task_category']) ?></div><div class="detail-value"><?= e($task['category']) ?></div></div>
                    <?php endif; ?>
                </div>

                <?php if ($task['client_notes']): ?>
                    <div style="margin-top:16px;padding:14px;background:var(--bg-input);border-radius:var(--radius);border:1px solid var(--border)">
                        <div class="detail-label" style="margin-bottom:6px"><i class="fas fa-sticky-note"></i> <?= e($lang['task_client_notes']) ?></div>
                        <p style="font-size:14px;color:var(--text-secondary);line-height:1.8"><?= nl2br(e($task['client_notes'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isWavesSide() && $task['internal_notes']): ?>
                    <div style="margin-top:12px;padding:14px;background:rgba(245,158,11,0.08);border-radius:var(--radius);border:1px solid rgba(245,158,11,0.15)">
                        <div class="detail-label" style="margin-bottom:6px;color:#fbbf24"><i class="fas fa-lock"></i> <?= e($lang['task_internal_notes']) ?></div>
                        <p style="font-size:14px;color:var(--text-secondary);line-height:1.8"><?= nl2br(e($task['internal_notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Files & Deliverables -->
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-file-export"></i> <?= e($lang['task_files']) ?> (<?= count($filesList) ?>)</div>
            </div>
            <div class="card-body">
                <?php foreach ($filesList as $file): ?>
                    <div class="file-list-item">
                        <i class="fas <?= getFileIcon($file['original_name']) ?>"></i>
                        <span class="file-name"><?= e($file['original_name']) ?></span>
                        <span class="file-size"><?= formatFileSize($file['file_size']) ?></span>
                        <span class="text-muted fs-sm"><?= e($file['uploader_name']) ?> - <?= timeAgo($file['created_at']) ?></span>
                        <a href="download.php?id=<?= $file['id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-download"></i></a>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($filesList)): ?>
                    <p class="text-center text-muted" style="padding:16px"><?= e($lang['no_files']) ?></p>
                <?php endif; ?>

                <!-- Upload form -->
                <form method="POST" enctype="multipart/form-data" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="upload_files">
                    <div class="file-upload-area" id="deliveryDrop">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p><?= e($lang['drag_drop']) ?> <span><?= e($lang['click_upload']) ?></span></p>
                    </div>
                    <input type="file" name="delivery_files[]" id="deliveryInput" multiple style="display:none">
                    <ul class="file-list" id="deliveryList"></ul>
                    <button type="submit" class="btn btn-primary mt-2" id="deliveryBtn" style="display:none"><i class="fas fa-upload"></i> <?= e($lang['upload_files']) ?></button>
                </form>
            </div>
        </div>

        <!-- Discussion -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-comments"></i> <?= e($lang['task_discussion']) ?> (<?= count($commentsList) ?>)</div>
            </div>
            <div class="card-body">
                <div class="comments-list">
                    <?php if (!empty($commentsList)): ?>
                        <?php foreach ($commentsList as $c): ?>
                            <div class="comment-item">
                                <div class="comment-avatar"><?= mb_substr($c['author_name'], 0, 1) ?></div>
                                <div class="comment-content">
                                    <div class="comment-header">
                                        <span class="comment-author"><?= e($c['author_name']) ?></span>
                                        <span class="comment-role <?= in_array($c['author_role'], ['admin','project_manager']) ? 'manager' : 'client' ?>">
                                            <?= e(getRoleLabel($c['author_role'])) ?>
                                        </span>
                                        <span class="comment-time"><?= timeAgo($c['created_at']) ?></span>
                                    </div>
                                    <div class="comment-text"><?= nl2br(e($c['comment'])) ?></div>
                                    <?php if (!empty($commentAttachments[$c['id']])): ?>
                                        <div class="comment-attachments">
                                            <?php foreach ($commentAttachments[$c['id']] as $att): ?>
                                                <a href="download.php?id=<?= $att['id'] ?>" class="comment-attachment">
                                                    <i class="fas <?= getFileIcon($att['original_name']) ?>"></i>
                                                    <?= e($att['original_name']) ?> <span class="text-muted">(<?= formatFileSize($att['file_size']) ?>)</span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted" style="padding:30px"><i class="fas fa-comments" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px"></i><?= e($lang['no_comments']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Add Comment -->
                <form method="POST" enctype="multipart/form-data" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="comment">
                    <div class="form-group">
                        <textarea name="comment" class="form-control" rows="3" placeholder="<?= e($lang['write_comment']) ?>" required></textarea>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                        <label style="cursor:pointer;display:flex;align-items:center;gap:8px;color:var(--text-muted);font-size:13px">
                            <i class="fas fa-paperclip"></i> <?= e($lang['attach_files']) ?>
                            <input type="file" name="comment_files[]" multiple style="display:none" onchange="this.parentElement.querySelector('span').textContent=this.files.length+' file(s)'">
                            <span></span>
                        </label>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> <?= e($lang['send_reply']) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Update Task (PM/Admin) -->
        <?php if (isWavesSide()): ?>
        <div class="card mb-3">
            <div class="card-header"><div class="card-title"><i class="fas fa-cog"></i> <?= e($lang['edit_task']) ?></div></div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_task">
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['status']) ?></label>
                        <select name="status" class="form-control">
                            <?php foreach (['new','in_progress','delivered','pending_review','needs_revision','completed'] as $s): ?>
                                <option value="<?= $s ?>" <?= $task['status'] === $s ? 'selected' : '' ?>><?= getStatusLabel($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['priority']) ?></label>
                        <select name="priority" class="form-control">
                            <?php foreach (['low','medium','high','urgent'] as $p): ?>
                                <option value="<?= $p ?>" <?= $task['priority'] === $p ? 'selected' : '' ?>><?= getPriorityLabel($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['task_due_date']) ?></label>
                        <input type="date" name="due_date" class="form-control" value="<?= $task['due_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($lang['task_progress']) ?> (%)</label>
                        <input type="number" name="progress" class="form-control" min="0" max="100" value="<?= $task['progress'] ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> <?= e($lang['update']) ?></button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Client Actions -->
        <?php if (isClient() && in_array($task['status'], ['delivered', 'pending_review'])): ?>
        <div class="card mb-3">
            <div class="card-header"><div class="card-title"><i class="fas fa-hand-pointer"></i> <?= e($lang['actions']) ?></div></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="client_action">
                    <input type="hidden" name="client_status" value="needs_revision">
                    <button type="submit" class="btn btn-warning w-100 mb-2"><i class="fas fa-redo"></i> <?= e($lang['status_needs_revision']) ?></button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Task History -->
        <div class="card mb-3">
            <div class="card-header"><div class="card-title"><i class="fas fa-history"></i> <?= e($lang['task_history']) ?></div></div>
            <div class="card-body" style="padding:0">
                <?php if (!empty($historyList)): ?>
                    <?php foreach ($historyList as $h): ?>
                        <div class="notif-item" style="padding:12px 16px">
                            <div style="width:28px;height:28px;border-radius:50%;background:var(--primary-glow);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="fas fa-exchange-alt" style="font-size:10px;color:var(--primary-light)"></i>
                            </div>
                            <div class="notif-content">
                                <div style="font-size:12px;font-weight:600"><?= e($h['user_name'] ?? 'System') ?></div>
                                <div class="text-muted" style="font-size:11px">
                                    <?php if ($h['old_status']): ?>
                                        <span class="badge <?= getStatusClass($h['old_status']) ?>" style="font-size:10px;padding:1px 6px"><?= getStatusLabel($h['old_status']) ?></span>
                                        <i class="fas fa-arrow-left" style="font-size:8px;margin:0 4px"></i>
                                    <?php endif; ?>
                                    <span class="badge <?= getStatusClass($h['new_status']) ?>" style="font-size:10px;padding:1px 6px"><?= getStatusLabel($h['new_status']) ?></span>
                                </div>
                            </div>
                            <div class="notif-time"><?= timeAgo($h['created_at']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted" style="padding:20px;font-size:13px"><?= e($lang['no_data']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit link for PM -->
        <?php if (isWavesSide()): ?>
        <a href="task_edit.php?id=<?= $task['id'] ?>" class="btn btn-secondary w-100 mb-3"><i class="fas fa-edit"></i> <?= e($lang['edit_task']) ?></a>
        <?php endif; ?>
    </div>
</div>

<?php
$extraJS = "<script>
initFileUpload('deliveryDrop','deliveryInput','deliveryList');
document.getElementById('deliveryInput').addEventListener('change', function() {
    document.getElementById('deliveryBtn').style.display = this.files.length > 0 ? 'inline-flex' : 'none';
});
</script>";
?>

<?php require_once 'includes/footer.php'; ?>
