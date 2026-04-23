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
 * Get all task statuses from DB (cached per request)
 */
function getAllStatuses() {
    static $statuses = null;
    if ($statuses !== null) return $statuses;
    global $pdo;
    try {
        $statuses = $pdo->query("SELECT * FROM task_statuses ORDER BY sort_order ASC")->fetchAll();
    } catch (Exception $e) {
        $statuses = [];
    }
    return $statuses;
}

/**
 * Get a single status record by slug
 */
function getStatusBySlug($slug) {
    $all = getAllStatuses();
    foreach ($all as $s) {
        if ($s['slug'] === $slug) return $s;
    }
    return null;
}

/**
 * Get status label in current language
 */
function getStatusLabel($status) {
    $s = getStatusBySlug($status);
    if ($s) {
        $lang = getLang();
        return $lang['lang_code'] === 'ar' ? $s['label_ar'] : $s['label_en'];
    }
    return $status;
}

/**
 * Get inline style for a task status badge (dynamic colors from DB)
 */
function getStatusStyle($status) {
    $s = getStatusBySlug($status);
    if ($s) {
        return 'background:' . e($s['bg_color']) . ';color:' . e($s['color']);
    }
    return '';
}

/**
 * Get CSS class for a task status (kept for backwards compat, but inline style preferred)
 */
function getStatusClass($status) {
    // Return empty - we use inline styles now via getStatusStyle()
    return '';
}

/**
 * Get all status slugs as array
 */
function getAllStatusSlugs() {
    $all = getAllStatuses();
    return array_column($all, 'slug');
}

/**
 * Get the default status slug
 */
function getDefaultStatus() {
    $all = getAllStatuses();
    foreach ($all as $s) {
        if ($s['is_default']) return $s['slug'];
    }
    return !empty($all) ? $all[0]['slug'] : 'new';
}

/**
 * Check if a status is a "completed" type
 */
function isCompletedStatus($status) {
    $s = getStatusBySlug($status);
    return $s ? (bool)$s['is_completed'] : false;
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
 * Checks task-specific notify_users setting if available
 */
function getTaskNotifyUsers($taskId, $excludeUserId = null) {
    global $pdo;

    // Check if task has specific notification recipients
    try {
        $stmt = $pdo->prepare("SELECT notify_users FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if ($task && !empty($task['notify_users'])) {
            $users = array_map('intval', explode(',', $task['notify_users']));
            if ($excludeUserId) {
                $users = array_filter($users, fn($u) => $u != $excludeUserId);
            }
            return array_values($users);
        }
    } catch (Exception $e) {
        // Column might not exist yet, fall through to default
    }

    // Default: all active users
    $stmt = $pdo->query("SELECT id FROM users WHERE is_active = 1");
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
 * Calculate overall project completion from all tasks (using dynamic completed statuses)
 */
function calculateProjectProgress() {
    global $pdo;
    try {
        $total = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
        if ($total == 0) return 0;
        $completedSlugs = array_column(array_filter(getAllStatuses(), fn($s) => $s['is_completed']), 'slug');
        if (empty($completedSlugs)) return 0;
        $in = "'" . implode("','", $completedSlugs) . "'";
        $completed = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ($in)")->fetchColumn();
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
