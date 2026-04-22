<?php
require_once 'config.php';
if (isLoggedIn()) { redirect('index.php'); }

$lang = getLang();
$dir = getDirection();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = trim($_POST['email'] ?? '');
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = generateToken();
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")->execute([$email, $token, $expires]);
            $resetUrl = getBaseUrl() . "/reset_password.php?token=" . $token;
            $body = buildEmailTemplate(
                $lang['reset_password'],
                '<p>' . e(str_replace(':name', $user['name'], $lang['email_greeting'])) . '</p><p>' . e($lang['email_reset_body']) . '</p>',
                $resetUrl,
                $lang['reset_password']
            );
            sendEmail($email, $lang['email_reset_subject'], $body);
        }
        $msg = $lang['reset_email_sent'];
        $msgType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang['lang_code'] ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($lang['forgot_password']) ?> - Waves</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="font-family:<?= $lang['font_family'] ?>">
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <img src="assets/img/waves-logo.png" alt="Waves" class="login-logo-img" style="height:44px;margin-bottom:12px">
            <p><?= e($lang['forgot_password']) ?></p>
        </div>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
        <?php endif; ?>
        <form method="POST" class="login-form">
            <?= csrfField() ?>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> <?= e($lang['email']) ?></label>
                <input type="email" name="email" class="form-control" placeholder="<?= e($lang['enter_email']) ?>" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary login-btn"><i class="fas fa-paper-plane"></i> <?= e($lang['send_reset_link']) ?></button>
        </form>
        <div style="text-align:center;margin-top:18px">
            <a href="login.php" style="color:#94a3b8;font-size:13px"><i class="fas fa-arrow-right"></i> <?= e($lang['back_to_login']) ?></a>
        </div>
    </div>
</div>
</body>
</html>
