<?php
/**
 * Email notification system
 * Uses PHP mail() as fallback, SMTP when configured
 */

function sendEmail($to, $subject, $body, $isHtml = true) {
    $smtpHost = getSetting('smtp_host', '');
    $smtpPort = getSetting('smtp_port', '587');
    $smtpUser = getSetting('smtp_username', '');
    $smtpPass = getSetting('smtp_password', '');
    $smtpEncryption = getSetting('smtp_encryption', 'tls');
    $fromEmail = getSetting('smtp_from_email', 'noreply@waves.sa');
    $fromName = getSetting('smtp_from_name', 'Waves Platform');

    // If SMTP is configured, use socket-based SMTP
    if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
        return sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpEncryption, $fromEmail, $fromName, $to, $subject, $body, $isHtml);
    }

    // Fallback to PHP mail()
    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    if ($isHtml) {
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }

    return @mail($to, $subject, $body, $headers);
}

/**
 * Simple SMTP sender (shared-hosting friendly, no external libs)
 */
function sendSmtpEmail($host, $port, $user, $pass, $encryption, $fromEmail, $fromName, $to, $subject, $body, $isHtml) {
    try {
        $socket = ($encryption === 'ssl')
            ? @fsockopen("ssl://{$host}", $port, $errno, $errstr, 10)
            : @fsockopen($host, $port, $errno, $errstr, 10);

        if (!$socket) return false;

        smtpRead($socket);
        smtpCmd($socket, "EHLO " . gethostname());

        if ($encryption === 'tls') {
            smtpCmd($socket, "STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            smtpCmd($socket, "EHLO " . gethostname());
        }

        smtpCmd($socket, "AUTH LOGIN");
        smtpCmd($socket, base64_encode($user));
        smtpCmd($socket, base64_encode($pass));
        smtpCmd($socket, "MAIL FROM:<{$fromEmail}>");
        smtpCmd($socket, "RCPT TO:<{$to}>");
        smtpCmd($socket, "DATA");

        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        $headers .= "\r\n";

        fwrite($socket, $headers . $body . "\r\n.\r\n");
        smtpRead($socket);
        smtpCmd($socket, "QUIT");
        fclose($socket);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function smtpCmd($socket, $cmd) {
    fwrite($socket, $cmd . "\r\n");
    return smtpRead($socket);
}

function smtpRead($socket) {
    $response = '';
    while ($line = @fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    return $response;
}

/**
 * Build HTML email template
 */
function buildEmailTemplate($title, $bodyContent, $actionUrl = '', $actionText = '') {
    $siteName = getSetting('site_title', 'Waves Platform');
    $lang = getLang();
    $dir = $lang['direction'] ?? 'rtl';

    $actionButton = '';
    if ($actionUrl && $actionText) {
        $actionButton = '<div style="text-align:center;margin:30px 0;">
            <a href="' . e($actionUrl) . '" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px;display:inline-block;">' . e($actionText) . '</a>
        </div>';
    }

    return '<!DOCTYPE html><html dir="' . $dir . '"><head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f1f5f9;font-family:Tajawal,Arial,sans-serif;">
    <div style="max-width:600px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
        <div style="background:linear-gradient(135deg,#6366f1,#4f46e5);padding:28px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:22px;">' . e($siteName) . '</h1>
        </div>
        <div style="padding:32px;">
            <h2 style="color:#1e293b;font-size:18px;margin-bottom:16px;">' . e($title) . '</h2>
            <div style="color:#475569;font-size:15px;line-height:1.8;">' . $bodyContent . '</div>
            ' . $actionButton . '
        </div>
        <div style="padding:20px;text-align:center;background:#f8fafc;color:#94a3b8;font-size:12px;border-top:1px solid #e2e8f0;">
            ' . e($lang['email_footer'] ?? 'Waves Platform') . '
        </div>
    </div></body></html>';
}

/**
 * Send notification email to a user
 */
function sendNotificationEmail($userId, $subject, $title, $bodyContent, $actionUrl = '', $actionText = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) return false;

        $html = buildEmailTemplate($title, $bodyContent, $actionUrl, $actionText);

        // Log email
        try {
            $pdo->prepare("INSERT INTO email_log (recipient, subject, status) VALUES (?, ?, 'pending')")
                ->execute([$user['email'], $subject]);
        } catch (Exception $e) {}

        $result = sendEmail($user['email'], $subject, $html);

        // Update log
        try {
            $pdo->prepare("UPDATE email_log SET status = ? WHERE recipient = ? AND subject = ? ORDER BY id DESC LIMIT 1")
                ->execute([$result ? 'sent' : 'failed', $user['email'], $subject]);
        } catch (Exception $e) {}

        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Notify users about a task event
 */
function notifyTaskEvent($taskId, $event, $excludeUserId = null) {
    global $pdo;
    $lang = getLang();

    try {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if (!$task) return;

        $users = getTaskNotifyUsers($taskId, $excludeUserId);
        $baseUrl = getBaseUrl();

        $subjects = [
            'created' => str_replace(':title', $task['title'], $lang['email_new_task']),
            'updated' => str_replace(':title', $task['title'], $lang['email_task_updated']),
            'comment' => str_replace(':title', $task['title'], $lang['email_new_comment']),
            'file' => str_replace(':title', $task['title'], $lang['email_file_uploaded']),
            'status_changed' => str_replace(':title', $task['title'], $lang['email_status_changed']),
            'delivered' => str_replace(':title', $task['title'], $lang['email_task_delivered']),
            'review' => str_replace(':title', $task['title'], $lang['email_review_needed']),
            'completed' => str_replace(':title', $task['title'], $lang['email_task_completed']),
        ];

        $subject = $subjects[$event] ?? $subjects['updated'];
        $link = $baseUrl . "/task_view.php?id={$taskId}";

        foreach ($users as $uid) {
            createNotification($uid, $subject, $task['title'], "task_view.php?id={$taskId}", 'task');
            sendNotificationEmail($uid, $subject, $subject, '<p>' . e($task['title']) . '</p>', $link, $lang['view_task']);
        }
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Get the base URL of the application
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return "{$protocol}://{$host}{$path}";
}
