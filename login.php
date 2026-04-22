<?php
require_once 'config.php';

if (!file_exists(__DIR__ . '/installed.lock')) { redirect('install.php'); }
if (isLoggedIn()) { redirect('index.php'); }

$lang = getLang();
$dir = getDirection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = $lang['required_fields'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && !$user['is_active']) {
            $error = $lang['account_inactive'];
        } elseif ($user && password_verify($password, $user['password'])) {
            loginUser($user);
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            logActivity($user['id'], 'login', 'User logged in', 'user', $user['id']);
            redirect('index.php');
        } else {
            $error = $lang['login_error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang['lang_code'] ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($lang['login']) ?> - Waves</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="font-family:<?= $lang['font_family'] ?>">
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="login-logos-row">
                <img src="assets/img/waves-logo.png" alt="Waves" class="login-logo-img">
                <span class="login-logos-divider"></span>
                <img src="assets/img/details-logo.png" alt="Details" class="login-logo-img">
            </div>
            <p style="margin-top:16px"><?= e($lang['app_subtitle']) ?></p>
        </div>

        <!-- Language switcher -->
        <div style="text-align:center;margin-bottom:20px">
            <a href="<?= langUrl(getOtherLang()) ?>" style="color:#94a3b8;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border:1px solid rgba(255,255,255,.1);border-radius:8px;transition:.3s">
                <i class="fas fa-globe"></i> <?= getOtherLangLabel() ?>
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <?= csrfField() ?>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> <?= e($lang['email']) ?></label>
                <input type="email" name="email" class="form-control" placeholder="<?= e($lang['enter_email']) ?>" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> <?= e($lang['password']) ?></label>
                <input type="password" name="password" class="form-control" placeholder="<?= e($lang['enter_password']) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary login-btn">
                <i class="fas fa-sign-in-alt"></i> <?= e($lang['login']) ?>
            </button>
        </form>

        <div style="text-align:center;margin-top:18px">
            <a href="forgot_password.php" style="color:#94a3b8;font-size:13px"><?= e($lang['forgot_password']) ?></a>
        </div>

        <p style="text-align:center;margin-top:20px;color:#475569;font-size:12px">
            Waves Platform v<?= APP_VERSION ?> &copy; <?= date('Y') ?>
        </p>
    </div>
</div>
</body>
</html>
