<?php
/**
 * Core helper functions for the Waves platform
 */

/**
 * Sanitize output to prevent XSS
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Format date
 */
function formatDate($date) {
    if (!$date) return '-';
    return date('Y-m-d', strtotime($date));
}

/**
 * Format date and time
 */
function formatDateTime($date) {
    if (!$date) return '-';
    return date('Y-m-d H:i', strtotime($date));
}

/**
 * Time ago string
 */
function timeAgo($datetime) {
    $lang = getLang();
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->days > 30) return formatDate($datetime);
    if ($diff->days > 0) return str_replace(':count', $diff->days, $lang['days_ago']);
    if ($diff->h > 0) return str_replace(':count', $diff->h, $lang['hours_ago']);
    if ($diff->i > 0) return str_replace(':count', $diff->i, $lang['minutes_ago']);
    return $lang['just_now'];
}

/**
 * Get status label in current language
 */
function getStatusLabel($status) {
    $lang = getLang();
    $map = [
        'new' => $lang['status_new'],
        'in_progress' => $lang['status_in_progress'],
        'delivered' => $lang['status_delivered'],
        'pending_review' => $lang['status_pending_review'],
        'needs_revision' => $lang['status_needs_revision'],
        'completed' => $lang['status_completed'],
        'archived' => $lang['status_archived'],
    ];
    return $map[$status] ?? $status;
}

/**
 * Get CSS class for a task status
 */
function getStatusClass($status) {
    $map = [
        'new' => 'status-new',
        'in_progress' => 'status-progress',
        'delivered' => 'status-delivered',
        'pending_review' => 'status-review',
        'needs_revision' => 'status-revision',
        'completed' => 'status-completed',
        'archived' => 'status-archived',
    ];
    return $map[$status] ?? '';
}

/**
 * Get priority label in current language
 */
function getPriorityLabel($priority) {
    $lang = getLang();
    $map = [
        'low' => $lang['priority_low'],
        'medium' => $lang['priority_medium'],
        'high' => $lang['priority_high'],
        'urgent' => $lang['priority_urgent'],
    ];
    return $map[$priority] ?? $priority;
}

/**
 * Get CSS class for a priority
 */
function getPriorityClass($priority) {
    $map = [
        'low' => 'priority-low',
        'medium' => 'priority-medium',
        'high' => 'priority-high',
        'urgent' => 'priority-urgent',
    ];
    return $map[$priority] ?? '';
}

/**
 * Get role label in current language
 */
function getRoleLabel($role) {
    $lang = getLang();
    $map = [
        'admin' => $lang['role_admin'],
        'project_manager' => $lang['role_project_manager'],
        'client' => $lang['role_client'],
    ];
    return $map[$role] ?? $role;
}

/**
 * Get file icon class based on extension
 */
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint',
        'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image', 'gif' => 'fa-file-image',
        'webp' => 'fa-file-image', 'svg' => 'fa-file-image',
        'psd' => 'fa-file-image', 'ai' => 'fa-file-image',
        'zip' => 'fa-file-archive', 'rar' => 'fa-file-archive', '7z' => 'fa-file-archive',
        'mp4' => 'fa-file-video', 'avi' => 'fa-file-video', 'mov' => 'fa-file-video',
        'mp3' => 'fa-file-audio', 'wav' => 'fa-file-audio',
        'txt' => 'fa-file-alt', 'csv' => 'fa-file-csv',
        'html' => 'fa-file-code', 'css' => 'fa-file-code', 'js' => 'fa-file-code', 'php' => 'fa-file-code',
    ];
    return $icons[$ext] ?? 'fa-file';
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

/**
 * Generate a random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if file extension is allowed
 */
function isAllowedExtension($filename) {
    $allowed = getSetting('allowed_extensions', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z,mp4,mov,avi,psd,ai,svg,txt,csv');
    $allowedArr = array_map('trim', explode(',', strtolower($allowed)));
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowedArr);
}

/**
 * Check if file is a dangerous type
 */
function isDangerousFile($filename) {
    $dangerous = ['php', 'php5', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'htaccess', 'htpasswd'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $dangerous);
}

/**
 * Get a system setting value
 */
function getSetting($key, $default = '') {
    global $pdo;
    if (!isset($pdo)) return $default;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Set a system setting value
 */
function setSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

/**
 * Log an activity
 */
function logActivity($userId, $action, $description, $entityType = null, $entityId = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $description, $entityType, $entityId, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Create a notification
 */
function createNotification($userId, $title, $message, $link = null, $type = 'info') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $message, $link, $type]);
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Notify multiple users
 */
function notifyUsers($userIds, $title, $message, $link = null, $type = 'info') {
    foreach ($userIds as $uid) {
        createNotification($uid, $title, $message, $link, $type);
    }
}

/**
 * Get users to notify for a task event
 */
function getTaskNotifyUsers($taskId, $excludeUserId = null) {
    global $pdo;
    // Get all admins and project managers, plus the client users
    $stmt = $pdo->query("SELECT id FROM users WHERE (role IN ('admin', 'project_manager', 'client')) AND is_active = 1");
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($excludeUserId) {
        $users = array_filter($users, fn($u) => $u != $excludeUserId);
    }
    return array_values($users);
}

/**
 * Log task status change in history
 */
function logTaskStatusChange($taskId, $userId, $oldStatus, $newStatus, $comment = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO task_status_history (task_id, user_id, old_status, new_status, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$taskId, $userId, $oldStatus, $newStatus, $comment]);
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Calculate overall project progress from all tasks
 */
function calculateProjectProgress() {
    global $pdo;
    try {
        $total = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status != 'archived'")->fetchColumn();
        if ($total == 0) return 0;
        $completed = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'")->fetchColumn();
        return round(($completed / $total) * 100);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Simple pagination helper
 */
function paginate($totalItems, $perPage = 20, $currentPage = 1) {
    $totalPages = max(1, ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [
        'total' => $totalItems,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) return '';
    $html = '<div class="pagination">';
    $separator = strpos($baseUrl, '?') !== false ? '&' : '?';

    if ($pagination['current_page'] > 1) {
        $html .= '<a href="' . $baseUrl . $separator . 'page=' . ($pagination['current_page'] - 1) . '"><i class="fas fa-chevron-right"></i></a>';
    }

    for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<span class="active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a>';
        }
    }

    if ($pagination['current_page'] < $pagination['total_pages']) {
        $html .= '<a href="' . $baseUrl . $separator . 'page=' . ($pagination['current_page'] + 1) . '"><i class="fas fa-chevron-left"></i></a>';
    }

    $html .= '</div>';
    return $html;
}
