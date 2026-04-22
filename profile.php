<?php
require_once 'config.php';
requireLogin();
$lang = getLang();
$pageTitle = $lang['profile'];
$msg = '';
$msgType = '';
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // Update profile
    if (($_POST['action'] ?? '') === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email)) {
            $msg = $lang['required_fields']; $msgType = 'danger';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $check->execute([$email, getUserId()]);
            if ($check->fetch()) {
                $msg = $lang['email_exists']; $msgType = 'danger';
            } else {
                $pdo->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?")
                    ->execute([$name, $email, $phone, getUserId()]);
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $currentUser['name'] = $name;
                $currentUser['email'] = $email;
                $currentUser['phone'] = $phone;
                $msg = $lang['profile_updated']; $msgType = 'success';
            }
        }
    }

    // Change password
    if (($_POST['action'] ?? '') === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $currentUser['password'])) {
            $msg = $lang['wrong_password']; $msgType = 'danger';
        } elseif (strlen($newPass) < 6) {
            $msg = $lang['required_fields']; $msgType = 'danger';
        } elseif ($newPass !== $confirmPass) {
            $msg = $lang['passwords_not_match']; $msgType = 'danger';
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), getUserId()]);
            logActivity(getUserId(), 'change_password', 'Password changed', 'user', getUserId());
            $msg = $lang['password_changed']; $msgType = 'success';
        }
    }
}

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-user"></i> <?= e($lang['edit_profile']) ?></div>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="card-body">
                <div style="text-align:center;margin-bottom:24px">
                    <div class="user-avatar" style="width:80px;height:80px;font-size:32px;margin:0 auto 12px"><?= mb_substr($currentUser['name'], 0, 1) ?></div>
                    <h3 style="font-size:18px;font-weight:700"><?= e($currentUser['name']) ?></h3>
                    <span class="badge <?= $currentUser['role']==='admin'?'priority-urgent':($currentUser['role']==='project_manager'?'priority-high':'priority-medium') ?>">
                        <?= e(getRoleLabel($currentUser['role'])) ?>
                    </span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['user_name']) ?> *</label>
                    <input type="text" name="name" class="form-control" value="<?= e($currentUser['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['user_email']) ?> *</label>
                    <input type="email" name="email" class="form-control" value="<?= e($currentUser['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['user_phone']) ?></label>
                    <input type="text" name="phone" class="form-control" value="<?= e($currentUser['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['user_company']) ?></label>
                    <input type="text" class="form-control" value="<?= e($currentUser['company'] ?? '') ?>" disabled>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= e($lang['save']) ?></button>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="card" style="align-self:start">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-lock"></i> <?= e($lang['change_password']) ?></div>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label"><?= e($lang['current_password']) ?> *</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['new_password']) ?> *</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($lang['confirm_password']) ?> *</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> <?= e($lang['change_password']) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
