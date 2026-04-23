<?php
/**
 * Email notification system
 * Uses PHP mail() as fallback, SMTP when configured
 */

// Global var to capture last SMTP error for debugging
$GLOBALS['_smtp_last_error'] = '';
$GLOBALS['_smtp_debug_log'] = '';

function sendEmail($to, $subject, $body, $isHtml = true) {
    $GLOBALS['_smtp_last_error'] = '';
    $GLOBALS['_smtp_debug_log'] = '';

    $smtpHost = getSetting('smtp_host', '');
    $smtpPort = (int)getSetting('smtp_port', '587');
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

    $result = @mail($to, $subject, $body, $headers);
    if (!$result) {
        $GLOBALS['_smtp_last_error'] = 'PHP mail() function failed. Check server mail configuration.';
    }
    return $result;
}

function getLastEmailError() {
    return $GLOBALS['_smtp_last_error'] ?? '';
}

function getSmtpDebugLog() {
    return $GLOBALS['_smtp_debug_log'] ?? '';
}

/**
 * SMTP sender with full error checking (shared-hosting friendly)
 */
function sendSmtpEmail($host, $port, $user, $pass, $encryption, $fromEmail, $fromName, $to, $subject, $body, $isHtml) {
    $log = '';

    try {
        // Step 1: Connect
        $log .= "[CONNECT] {$host}:{$port} ({$encryption})\n";

        if ($encryption === 'ssl') {
            $socket = @fsockopen("ssl://{$host}", $port, $errno, $errstr, 15);
        } else {
            $socket = @fsockopen($host, $port, $errno, $errstr, 15);
        }

        if (!$socket) {
            $GLOBALS['_smtp_last_error'] = "Connection failed: {$errstr} (error {$errno}). Check host/port.";
            $GLOBALS['_smtp_debug_log'] = $log . "[ERROR] {$errstr}\n";
            return false;
        }

        stream_set_timeout($socket, 15);

        // Step 2: Read greeting
        $resp = smtpRead($socket);
        $log .= "[GREETING] {$resp}";
        if (!smtpOk($resp, 220)) {
            $GLOBALS['_smtp_last_error'] = "Server rejected connection: {$resp}";
            $GLOBALS['_smtp_debug_log'] = $log;
            fclose($socket);
            return false;
        }

        // Step 3: EHLO
        $resp = smtpCmd($socket, "EHLO " . (gethostname() ?: 'localhost'));
        $log .= "[EHLO] {$resp}";

        // Step 4: STARTTLS (if TLS)
        if ($encryption === 'tls') {
            $resp = smtpCmd($socket, "STARTTLS");
            $log .= "[STARTTLS] {$resp}";
            if (!smtpOk($resp, 220)) {
                $GLOBALS['_smtp_last_error'] = "STARTTLS failed: {$resp}";
                $GLOBALS['_smtp_debug_log'] = $log;
                fclose($socket);
                return false;
            }

            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                $GLOBALS['_smtp_last_error'] = "TLS encryption handshake failed. Try SSL encryption instead.";
                $GLOBALS['_smtp_debug_log'] = $log . "[ERROR] TLS handshake failed\n";
                fclose($socket);
                return false;
            }
            $log .= "[TLS] Encryption enabled\n";

            $resp = smtpCmd($socket, "EHLO " . (gethostname() ?: 'localhost'));
            $log .= "[EHLO2] {$resp}";
        }

        // Step 5: AUTH LOGIN
        $resp = smtpCmd($socket, "AUTH LOGIN");
        $log .= "[AUTH] {$resp}";
        if (!smtpOk($resp, 334)) {
            $GLOBALS['_smtp_last_error'] = "AUTH LOGIN not accepted: {$resp}";
            $GLOBALS['_smtp_debug_log'] = $log;
            fclose($socket);
            return false;
        }

        $resp = smtpCmd($socket, base64_encode($user));
        $log .= "[USER] " . substr($resp, 0, 50) . "\n";

        $resp = smtpCmd($socket, base64_encode($pass));
        $log .= "[PASS] " . substr($resp, 0, 50) . "\n";
        if (!smtpOk($resp, 235)) {
            $GLOBALS['_smtp_last_error'] = "Authentication failed. Check username/password. Server: " . trim($resp);
            $GLOBALS['_smtp_debug_log'] = $log;
            fclose($socket);
            return false;
        }

        // Step 6: MAIL FROM
        $resp = smtpCmd($socket, "MAIL FROM:<{$fromEmail}>");
        $log .= "[FROM] {$resp}";
        if (!smtpOk($resp, 250)) {
            $GLOBALS['_smtp_last_error'] = "MAIL FROM rejected: {$resp}";
            $GLOBALS['_smtp_debug_log'] = $log;
            fclose($socket);
            return false;
        }

        // Step 7: RCPT TO
        $resp = smtpCmd($socket, "RCPT TO:<{$to}>");
        $log .= "[RCPT] {$resp}";
        if (!smtpOk($resp, 250)) {
            $GLOBALS['_smtp_last_error'] = "Recipient rejected: {$resp}";
            $GLOBALS['_smtp_debug_log'] = $log;
            fclose($socket);
            return false;
        }

        // Step 8: DATA
        $resp = smtpCmd($socket, "DATA");
        $log .= "[DATA] {$resp}";
        if (!smtpOk($resp, 354)) {
            $GLOBALS['_smtp_last_error'] = "DATA command rejected: {$resp}";
            $GLOBALS['_smtp_debug_log'] = $log;
            fclose($socket);
            return false;
        }

        // Step 9: Send message with proper headers for deliverability
        $msgId = uniqid('waves_') . '.' . time() . '@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'waves-pm.com');
        $message = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Reply-To: {$fromEmail}\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "Date: " . date('r') . "\r\n";
        $message .= "Message-ID: <{$msgId}>\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "X-Mailer: Waves-Platform/1.0\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($body)) . "\r\n.\r\n";

        fwrite($socket, $message);
        $resp = smtpRead($socket);
        $log .= "[SENT] {$resp}";
        if (!smtpOk($resp, 250)) {
            $GLOBALS['_smtp_last_error'] = "Message not accepted: {$resp}";
            $GLOBALS['_smtp_debug_log'] = $log;
            fclose($socket);
            return false;
        }

        // Step 10: QUIT
        smtpCmd($socket, "QUIT");
        fclose($socket);

        $log .= "[DONE] Email sent successfully\n";
        $GLOBALS['_smtp_debug_log'] = $log;
        return true;

    } catch (Exception $e) {
        $GLOBALS['_smtp_last_error'] = "Exception: " . $e->getMessage();
        $GLOBALS['_smtp_debug_log'] = $log . "[EXCEPTION] " . $e->getMessage() . "\n";
        if (isset($socket) && is_resource($socket)) fclose($socket);
        return false;
    }
}

function smtpOk($response, $expectedCode) {
    return (int)substr(trim($response), 0, 3) === $expectedCode;
}

function smtpCmd($socket, $cmd) {
    fwrite($socket, $cmd . "\r\n");
    return smtpRead($socket);
}

function smtpRead($socket) {
    $response = '';
    $timeout = 15;
    $start = time();
    while (true) {
        $line = @fgets($socket, 515);
        if ($line === false) break;
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
        if (time() - $start > $timeout) break;
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
