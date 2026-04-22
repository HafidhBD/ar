<?php
require_once 'config.php';
if (isLoggedIn()) { redirect('index.php'); }

$lang = getLang();
$dir = getDirection();
$msg = '';
$msgType = '';
$validToken = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    if ($reset) { $validToken = true; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    requireCsrf();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (strlen($password) < 6) {
        $msg = $lang['required_fields'];
        $msgType = 'danger';
    } elseif ($password !== $confirm) {
        $msg = $lang['passwords_not_match'];
        $msgType = 'danger';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hashed, $reset['email']]);
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$reset['id']]);
        $msg = $lang['password_reset_success'];
        $msgType = 'success';
        $validToken = false;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang['lang_code'] ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($lang['reset_password']) ?> - Waves</title>
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
            <p><?= e($lang['reset_password']) ?></p>
        </div>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
            <?php if ($msgType === 'success'): ?>
                <a href="login.php" class="btn btn-primary login-btn"><i class="fas fa-sign-in-alt"></i> <?= e($lang['back_to_login']) ?></a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($validToken): ?>
            <form method="POST" class="login-form">
                <?= csrfField() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> <?= e($lang['new_password']) ?></label>
                    <input type="password" name="password" class="form-control" required minlength="6" placeholder="<?= e($lang['new_password']) ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> <?= e($lang['confirm_password']) ?></label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="<?= e($lang['confirm_password']) ?>">
                </div>
                <button type="submit" class="btn btn-primary login-btn"><i class="fas fa-save"></i> <?= e($lang['reset_password']) ?></button>
            </form>
        <?php elseif (!$msg): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($lang['invalid_reset_token']) ?></div>
            <a href="login.php" class="btn btn-primary login-btn"><i class="fas fa-arrow-right"></i> <?= e($lang['back_to_login']) ?></a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
