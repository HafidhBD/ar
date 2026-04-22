<?php
require_once 'config.php';
requireLogin();
requireRole('admin');
$lang = getLang();
$pageTitle = $lang['settings'];
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    if (($_POST['action'] ?? '') === 'general') {
        setSetting('site_title', trim($_POST['site_title'] ?? ''));
        setSetting('project_title', trim($_POST['project_title'] ?? ''));
        setSetting('project_description', trim($_POST['project_description'] ?? ''));
        setSetting('default_language', $_POST['default_language'] ?? 'ar');
        setSetting('timezone', trim($_POST['timezone'] ?? 'Asia/Riyadh'));
        $msg = $lang['settings_saved']; $msgType = 'success';
    }

    if (($_POST['action'] ?? '') === 'smtp') {
        setSetting('smtp_host', trim($_POST['smtp_host'] ?? ''));
        setSetting('smtp_port', trim($_POST['smtp_port'] ?? '587'));
        setSetting('smtp_username', trim($_POST['smtp_username'] ?? ''));
        if (!empty($_POST['smtp_password'])) {
            setSetting('smtp_password', $_POST['smtp_password']);
        }
        setSetting('smtp_encryption', $_POST['smtp_encryption'] ?? 'tls');
        setSetting('smtp_from_email', trim($_POST['smtp_from_email'] ?? ''));
        setSetting('smtp_from_name', trim($_POST['smtp_from_name'] ?? ''));
        $msg = $lang['settings_saved']; $msgType = 'success';
    }

    if (($_POST['action'] ?? '') === 'upload') {
        setSetting('allowed_extensions', trim($_POST['allowed_extensions'] ?? ''));
        setSetting('max_upload_size', (int)($_POST['max_upload_size'] ?? 52428800));
        $msg = $lang['settings_saved']; $msgType = 'success';
    }

    if (($_POST['action'] ?? '') === 'test_email') {
        $testTo = trim($_POST['test_email_to'] ?? '');
        if (!empty($testTo)) {
            $html = buildEmailTemplate('Test Email', '<p>This is a test email from Waves Platform.</p><p>If you received this, your SMTP settings are working correctly.</p>');
            $result = sendEmail($testTo, 'Waves - Test Email', $html);
            if ($result) {
                $msg = $lang['email_sent_success']; $msgType = 'success';
            } else {
                $smtpError = getLastEmailError();
                $smtpLog = getSmtpDebugLog();
                $msg = $lang['email_sent_failed'];
                if ($smtpError) $msg .= ' — ' . $smtpError;
                $msgType = 'danger';
                // Store debug log to show below
                $debugLog = $smtpLog;
            }
        }
    }

    logActivity(getUserId(), 'update_settings', 'Updated system settings', 'settings', 0);
}

// Load current settings
$siteTitle = getSetting('site_title', 'Waves Platform');
$projectTitle = getSetting('project_title', 'Waves × Details Project');
$projectDesc = getSetting('project_description', '');
$defaultLang = getSetting('default_language', 'ar');
$tz = getSetting('timezone', 'Asia/Riyadh');
$smtpHost = getSetting('smtp_host', '');
$smtpPort = getSetting('smtp_port', '587');
$smtpUser = getSetting('smtp_username', '');
$smtpEncryption = getSetting('smtp_encryption', 'tls');
$smtpFromEmail = getSetting('smtp_from_email', '');
$smtpFromName = getSetting('smtp_from_name', 'Waves Platform');
$allowedExt = getSetting('allowed_extensions', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z,mp4,mov,avi,psd,ai,svg,txt,csv');
$maxUpload = getSetting('max_upload_size', '52428800');

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
<?php endif; ?>

<!-- General Settings -->
<div class="card mb-3">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-cog"></i> <?= e($lang['general_settings']) ?></div>
    </div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="general">
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= e($lang['site_title']) ?></label>
                    <input type="text" name="site_title" class="form-control" value="<?= e($siteTitle) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['default_language']) ?></label>
                    <select name="default_language" class="form-control">
                        <option value="ar" <?= $defaultLang === 'ar' ? 'selected' : '' ?>>العربية</option>
                        <option value="en" <?= $defaultLang === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Project Title</label>
                <input type="text" name="project_title" class="form-control" value="<?= e($projectTitle) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Project Description</label>
                <textarea name="project_description" class="form-control" rows="2"><?= e($projectDesc) ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e($lang['timezone']) ?></label>
                <input type="text" name="timezone" class="form-control" value="<?= e($tz) ?>" placeholder="Asia/Riyadh">
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= e($lang['save']) ?></button>
        </div>
    </form>
</div>

<!-- SMTP Settings -->
<div class="card mb-3">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-envelope"></i> <?= e($lang['smtp_settings']) ?></div>
    </div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="smtp">
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= e($lang['smtp_host']) ?></label>
                    <input type="text" name="smtp_host" class="form-control" value="<?= e($smtpHost) ?>" placeholder="smtp.example.com">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['smtp_port']) ?></label>
                    <input type="number" name="smtp_port" class="form-control" value="<?= e($smtpPort) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= e($lang['smtp_username']) ?></label>
                    <input type="text" name="smtp_username" class="form-control" value="<?= e($smtpUser) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['smtp_password']) ?></label>
                    <input type="password" name="smtp_password" class="form-control" placeholder="(unchanged)">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= e($lang['smtp_encryption']) ?></label>
                    <select name="smtp_encryption" class="form-control">
                        <option value="tls" <?= $smtpEncryption === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= $smtpEncryption === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= $smtpEncryption === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['smtp_from_email']) ?></label>
                    <input type="email" name="smtp_from_email" class="form-control" value="<?= e($smtpFromEmail) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e($lang['smtp_from_name']) ?></label>
                <input type="text" name="smtp_from_name" class="form-control" value="<?= e($smtpFromName) ?>">
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= e($lang['save']) ?></button>
        </div>
    </form>
</div>

<!-- Test Email -->
<div class="card mb-3">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-paper-plane"></i> <?= e($lang['test_email']) ?></div>
    </div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="test_email">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label"><?= e($lang['email']) ?></label>
                <input type="email" name="test_email_to" class="form-control" required placeholder="test@example.com">
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> <?= e($lang['test_email']) ?></button>
        </div>
    </form>
    <?php if (!empty($debugLog)): ?>
    <div class="card-body" style="border-top:1px solid var(--border)">
        <div class="form-label" style="color:var(--danger);margin-bottom:8px"><i class="fas fa-bug"></i> SMTP Debug Log:</div>
        <pre style="background:#f8fafc;border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;font-size:12px;color:#475569;white-space:pre-wrap;word-break:break-all;max-height:300px;overflow-y:auto;font-family:monospace"><?= e($debugLog) ?></pre>
    </div>
    <?php endif; ?>
</div>

<!-- Upload Settings -->
<div class="card mb-3">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-upload"></i> <?= e($lang['upload_settings']) ?></div>
    </div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label"><?= e($lang['allowed_extensions']) ?></label>
                <input type="text" name="allowed_extensions" class="form-control" value="<?= e($allowedExt) ?>">
                <div class="form-text">Comma-separated list of allowed file extensions</div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e($lang['max_upload_size']) ?> (bytes)</label>
                <input type="number" name="max_upload_size" class="form-control" value="<?= e($maxUpload) ?>">
                <div class="form-text"><?= formatFileSize((int)$maxUpload) ?></div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= e($lang['save']) ?></button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
