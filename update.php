<?php
/**
 * Waves Platform - Database Update Script
 * Run this file to apply database changes without reinstalling.
 * Delete this file after running it successfully.
 */
session_start();

// Load DB config
if (!file_exists(__DIR__ . '/config.php')) {
    die('config.php not found. Please install the platform first.');
}
require_once __DIR__ . '/config.php';

$results = [];
$errors = [];

// Only allow if logged in as admin OR via direct access (for first-time migration)
$isAdmin = isLoggedIn() && isAdmin();
$hasLock = file_exists(__DIR__ . '/installed.lock');

if (!$hasLock) {
    die('Platform is not installed yet. Run install.php first.');
}

// ============================================================
// MIGRATION 1: Create task_statuses table
// ============================================================
try {
    $pdo->query("SELECT id FROM task_statuses LIMIT 1");
    $results[] = ['task_statuses table', 'Already exists', 'skip'];
} catch (Exception $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `task_statuses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(50) NOT NULL UNIQUE,
            `label_ar` VARCHAR(100) NOT NULL,
            `label_en` VARCHAR(100) NOT NULL,
            `color` VARCHAR(7) NOT NULL DEFAULT '#6366f1',
            `bg_color` VARCHAR(7) NOT NULL DEFAULT '#ede9fe',
            `sort_order` INT DEFAULT 0,
            `is_default` TINYINT(1) DEFAULT 0,
            `is_completed` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Insert default statuses
        $defaults = [
            ['new',            'جديد',            'New',            '#5b21b6', '#ede9fe', 0, 1, 0],
            ['in_progress',    'جاري التنفيذ',    'In Progress',    '#1e40af', '#dbeafe', 1, 0, 0],
            ['delivered',      'تم التسليم',      'Delivered',      '#0e7490', '#cffafe', 2, 0, 0],
            ['pending_review', 'بانتظار المراجعة', 'Pending Review', '#7c3aed', '#f3e8ff', 3, 0, 0],
            ['needs_revision', 'يحتاج تعديل',     'Needs Revision', '#92400e', '#fef3c7', 4, 0, 0],
            ['completed',      'مكتمل',           'Completed',      '#166534', '#dcfce7', 5, 0, 1],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO task_statuses (slug,label_ar,label_en,color,bg_color,sort_order,is_default,is_completed) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($defaults as $d) $stmt->execute($d);

        $results[] = ['task_statuses table', 'Created with 6 default statuses', 'success'];
    } catch (Exception $e2) {
        $errors[] = 'task_statuses: ' . $e2->getMessage();
        $results[] = ['task_statuses table', $e2->getMessage(), 'error'];
    }
}

// ============================================================
// MIGRATION 2: Change tasks.status from ENUM to VARCHAR
// ============================================================
try {
    $col = $pdo->query("SHOW COLUMNS FROM tasks WHERE Field='status'")->fetch();
    if ($col && stripos($col['Type'], 'enum') !== false) {
        $pdo->exec("ALTER TABLE tasks MODIFY COLUMN `status` VARCHAR(50) DEFAULT 'new'");
        $results[] = ['tasks.status column', 'Changed from ENUM to VARCHAR(50)', 'success'];
    } else {
        $results[] = ['tasks.status column', 'Already VARCHAR', 'skip'];
    }
} catch (Exception $e) {
    $errors[] = 'tasks.status: ' . $e->getMessage();
    $results[] = ['tasks.status column', $e->getMessage(), 'error'];
}

// ============================================================
// MIGRATION 3: Add notify_users column to tasks
// ============================================================
try {
    $pdo->query("SELECT notify_users FROM tasks LIMIT 1");
    $results[] = ['tasks.notify_users column', 'Already exists', 'skip'];
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN `notify_users` TEXT DEFAULT NULL");
        $results[] = ['tasks.notify_users column', 'Added successfully', 'success'];
    } catch (Exception $e2) {
        $errors[] = 'notify_users: ' . $e2->getMessage();
        $results[] = ['tasks.notify_users column', $e2->getMessage(), 'error'];
    }
}

// ============================================================
// MIGRATION 4: Add ip_address column to activity_log
// ============================================================
try {
    $pdo->query("SELECT ip_address FROM activity_log LIMIT 1");
    $results[] = ['activity_log.ip_address', 'Already exists', 'skip'];
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE activity_log ADD COLUMN `ip_address` VARCHAR(45) DEFAULT NULL");
        $results[] = ['activity_log.ip_address', 'Added successfully', 'success'];
    } catch (Exception $e2) {
        $results[] = ['activity_log.ip_address', $e2->getMessage(), 'error'];
    }
}

// ============================================================
// MIGRATION 5: Create password_resets table if missing
// ============================================================
try {
    $pdo->query("SELECT id FROM password_resets LIMIT 1");
    $results[] = ['password_resets table', 'Already exists', 'skip'];
} catch (Exception $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(150) NOT NULL,
            `token` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $results[] = ['password_resets table', 'Created', 'success'];
    } catch (Exception $e2) {
        $results[] = ['password_resets table', $e2->getMessage(), 'error'];
    }
}

// ============================================================
// MIGRATION 6: Create task_status_history table if missing
// ============================================================
try {
    $pdo->query("SELECT id FROM task_status_history LIMIT 1");
    $results[] = ['task_status_history table', 'Already exists', 'skip'];
} catch (Exception $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `task_status_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `task_id` INT NOT NULL,
            `user_id` INT DEFAULT NULL,
            `old_status` VARCHAR(50) DEFAULT NULL,
            `new_status` VARCHAR(50) NOT NULL,
            `comment` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $results[] = ['task_status_history table', 'Created', 'success'];
    } catch (Exception $e2) {
        $results[] = ['task_status_history table', $e2->getMessage(), 'error'];
    }
}

// ============================================================
// MIGRATION 7: Create email_log table if missing
// ============================================================
try {
    $pdo->query("SELECT id FROM email_log LIMIT 1");
    $results[] = ['email_log table', 'Already exists', 'skip'];
} catch (Exception $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `recipient` VARCHAR(150) NOT NULL,
            `subject` VARCHAR(255) DEFAULT NULL,
            `status` VARCHAR(20) DEFAULT 'pending',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $results[] = ['email_log table', 'Created', 'success'];
    } catch (Exception $e2) {
        $results[] = ['email_log table', $e2->getMessage(), 'error'];
    }
}

// ============================================================
// MIGRATION 8: Rename attachments table to task_attachments if needed
// ============================================================
try {
    $pdo->query("SELECT id FROM task_attachments LIMIT 1");
    $results[] = ['task_attachments table', 'Already exists', 'skip'];
} catch (Exception $e) {
    try {
        // Check if old 'attachments' table exists
        $old = $pdo->query("SHOW TABLES LIKE 'attachments'")->fetch();
        if ($old) {
            $pdo->exec("RENAME TABLE `attachments` TO `task_attachments`");
            // Add version column if missing
            try { $pdo->exec("ALTER TABLE task_attachments ADD COLUMN `version` INT DEFAULT 1"); } catch (Exception $x) {}
            $results[] = ['task_attachments table', 'Renamed from attachments', 'success'];
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `task_attachments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` INT NOT NULL,
                `comment_id` INT DEFAULT NULL,
                `user_id` INT NOT NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `original_name` VARCHAR(255) NOT NULL,
                `file_size` BIGINT DEFAULT 0,
                `file_type` VARCHAR(100) DEFAULT NULL,
                `version` INT DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`comment_id`) REFERENCES `task_comments`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $results[] = ['task_attachments table', 'Created', 'success'];
        }
    } catch (Exception $e2) {
        $results[] = ['task_attachments table', $e2->getMessage(), 'error'];
    }
}

// ============================================================
// MIGRATION 9: Drop old projects table if exists (single-project system)
// ============================================================
try {
    $old = $pdo->query("SHOW TABLES LIKE 'projects'")->fetch();
    if ($old) {
        $results[] = ['projects table', 'Exists (legacy) - skipped drop for safety', 'skip'];
    } else {
        $results[] = ['projects table', 'Not present (correct)', 'skip'];
    }
} catch (Exception $e) {
    $results[] = ['projects table', 'Check skipped', 'skip'];
}

// Count results
$successCount = count(array_filter($results, fn($r) => $r[2] === 'success'));
$skipCount = count(array_filter($results, fn($r) => $r[2] === 'skip'));
$errorCount = count(array_filter($results, fn($r) => $r[2] === 'error'));

function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waves - Database Update</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Tajawal',sans-serif;background:linear-gradient(135deg,#f8fafc,#e2e8f0,#f8fafc);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:40px;width:100%;max-width:700px;box-shadow:0 8px 30px rgba(0,0,0,.08)}
        .logo{text-align:center;margin-bottom:28px}
        .logo h1{font-size:28px;font-weight:800;color:#6366f1;margin-bottom:4px}
        .logo p{color:#64748b;font-size:14px}
        .summary{display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap}
        .summary-item{flex:1;min-width:100px;padding:14px;border-radius:12px;text-align:center}
        .summary-item .num{font-size:28px;font-weight:800}
        .summary-item .lbl{font-size:12px;font-weight:500;margin-top:2px}
        .s-success{background:#f0fdf4;color:#166534}
        .s-skip{background:#f1f5f9;color:#64748b}
        .s-error{background:#fef2f2;color:#991b1b}
        table{width:100%;border-collapse:collapse;margin-bottom:24px}
        th{padding:10px 14px;text-align:right;font-size:12px;font-weight:600;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0}
        td{padding:10px 14px;font-size:13px;color:#334155;border-bottom:1px solid #f1f5f9}
        .badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700}
        .b-success{background:#dcfce7;color:#166534}
        .b-skip{background:#f1f5f9;color:#64748b}
        .b-error{background:#fee2e2;color:#991b1b}
        .btn{display:block;width:100%;padding:15px;background:linear-gradient(135deg,#6366f1,#7c3aed);color:#fff;border:none;border-radius:12px;font-family:'Tajawal',sans-serif;font-size:16px;font-weight:700;cursor:pointer;text-align:center;text-decoration:none;transition:.3s}
        .btn:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(99,102,241,.3)}
        .warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:12px 16px;border-radius:10px;margin-top:16px;font-size:13px;display:flex;align-items:center;gap:8px}
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1><i class="fas fa-database"></i> Database Update</h1>
        <p>تحديث قاعدة البيانات - Waves Platform</p>
    </div>

    <div class="summary">
        <div class="summary-item s-success"><div class="num"><?= $successCount ?></div><div class="lbl">تم التحديث</div></div>
        <div class="summary-item s-skip"><div class="num"><?= $skipCount ?></div><div class="lbl">موجود مسبقاً</div></div>
        <div class="summary-item s-error"><div class="num"><?= $errorCount ?></div><div class="lbl">خطأ</div></div>
    </div>

    <table>
        <thead><tr><th>العنصر</th><th>النتيجة</th><th>الحالة</th></tr></thead>
        <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td><strong><?= esc($r[0]) ?></strong></td>
                <td><?= esc($r[1]) ?></td>
                <td>
                    <?php if ($r[2] === 'success'): ?><span class="badge b-success"><i class="fas fa-check-circle"></i> تم</span>
                    <?php elseif ($r[2] === 'skip'): ?><span class="badge b-skip"><i class="fas fa-minus-circle"></i> تخطي</span>
                    <?php else: ?><span class="badge b-error"><i class="fas fa-times-circle"></i> خطأ</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($errorCount === 0): ?>
        <a href="index.php" class="btn"><i class="fas fa-check-double"></i> تم التحديث بنجاح — الذهاب للوحة التحكم</a>
    <?php else: ?>
        <a href="update.php" class="btn" style="background:#ef4444"><i class="fas fa-redo"></i> إعادة المحاولة</a>
    <?php endif; ?>

    <div class="warn"><i class="fas fa-shield-alt"></i> لأمان النظام، احذف ملف <strong>update.php</strong> بعد اكتمال التحديث.</div>
</div>
</body>
</html>
