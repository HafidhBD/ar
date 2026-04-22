<?php
require_once 'config.php';
requireLogin();
requireRole('admin');
$lang = getLang();
$pageTitle = $lang['users'];
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();

    if ($_POST['action'] === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'client';
        $company = trim($_POST['company'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $msg = $lang['required_fields']; $msgType = 'danger';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $msg = $lang['email_exists']; $msgType = 'danger';
            } else {
                $pdo->prepare("INSERT INTO users (name,email,password,phone,role,company) VALUES (?,?,?,?,?,?)")
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $role, $company]);
                logActivity(getUserId(), 'create_user', "Created user: $name", 'user', $pdo->lastInsertId());
                $msg = $lang['user_created']; $msgType = 'success';
            }
        }
    }

    if ($_POST['action'] === 'update') {
        $uid = (int)$_POST['user_id'];
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'client';
        $company = trim($_POST['company'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $check = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $check->execute([$email, $uid]);
        if ($check->fetch()) {
            $msg = $lang['email_exists']; $msgType = 'danger';
        } else {
            $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,role=?,company=?,is_active=? WHERE id=?")
                ->execute([$name, $email, $phone, $role, $company, $isActive, $uid]);
            if (!empty($_POST['password'])) {
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $uid]);
            }
            logActivity(getUserId(), 'update_user', "Updated user: $name", 'user', $uid);
            $msg = $lang['user_updated']; $msgType = 'success';
        }
    }

    if ($_POST['action'] === 'delete') {
        $uid = (int)$_POST['user_id'];
        if ($uid === getUserId()) {
            $msg = $lang['cannot_delete_self']; $msgType = 'danger';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            logActivity(getUserId(), 'delete_user', "Deleted user #$uid", 'user', $uid);
            $msg = $lang['user_deleted']; $msgType = 'success';
        }
    }
}

$search = $_GET['search'] ?? '';
$filterRole = $_GET['role'] ?? '';
$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }
if ($filterRole) { $where .= " AND role=?"; $params[] = $filterRole; }

$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="search-bar" style="width:100%">
            <form method="GET" class="search-bar" style="width:100%">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="<?= e($lang['search']) ?>..." value="<?= e($search) ?>">
                </div>
                <select name="role" class="filter-select" onchange="this.form.submit()">
                    <option value=""><?= e($lang['all']) ?></option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>><?= e($lang['role_admin']) ?></option>
                    <option value="project_manager" <?= $filterRole === 'project_manager' ? 'selected' : '' ?>><?= e($lang['role_project_manager']) ?></option>
                    <option value="client" <?= $filterRole === 'client' ? 'selected' : '' ?>><?= e($lang['role_client']) ?></option>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
            </form>
            <button class="btn btn-primary" onclick="openModal('createUserModal')"><i class="fas fa-plus"></i> <?= e($lang['create_user']) ?></button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>#</th><th><?= e($lang['user_name']) ?></th><th><?= e($lang['user_email']) ?></th>
                <th><?= e($lang['user_role']) ?></th><th><?= e($lang['user_company']) ?></th>
                <th><?= e($lang['user_status']) ?></th><th><?= e($lang['user_last_login']) ?></th><th><?= e($lang['actions']) ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><div class="d-flex align-center gap-1"><div class="user-avatar-sm"><?= mb_substr($u['name'],0,1) ?></div><strong><?= e($u['name']) ?></strong></div></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge <?= $u['role']==='admin'?'priority-urgent':($u['role']==='project_manager'?'priority-high':'priority-medium') ?>"><?= e(getRoleLabel($u['role'])) ?></span></td>
                    <td><?= e($u['company'] ?: '-') ?></td>
                    <td><?php if ($u['is_active']): ?><span class="badge status-completed"><?= e($lang['user_active']) ?></span><?php else: ?><span class="badge status-cancelled"><?= e($lang['user_inactive']) ?></span><?php endif; ?></td>
                    <td><?= $u['last_login'] ? formatDateTime($u['last_login']) : '-' ?></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-secondary" onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                            <?php if ($u['id'] !== getUserId()): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('<?= e($lang['confirm_delete']) ?>')">
                                <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?><tr><td colspan="8" class="text-center text-muted" style="padding:40px"><?= e($lang['no_data']) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="createUserModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title"><i class="fas fa-user-plus"></i> <?= e($lang['create_user']) ?></div><button class="modal-close" onclick="closeModal('createUserModal')"><i class="fas fa-times"></i></button></div>
        <form method="POST">
            <?= csrfField() ?><input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row"><div class="form-group"><label class="form-label"><?= e($lang['user_name']) ?> *</label><input type="text" name="name" class="form-control" required></div><div class="form-group"><label class="form-label"><?= e($lang['user_email']) ?> *</label><input type="email" name="email" class="form-control" required></div></div>
                <div class="form-row"><div class="form-group"><label class="form-label"><?= e($lang['password']) ?> *</label><input type="password" name="password" class="form-control" required minlength="6"></div><div class="form-group"><label class="form-label"><?= e($lang['user_phone']) ?></label><input type="text" name="phone" class="form-control"></div></div>
                <div class="form-row"><div class="form-group"><label class="form-label"><?= e($lang['user_role']) ?></label><select name="role" class="form-control"><option value="client"><?= e($lang['role_client']) ?></option><option value="project_manager"><?= e($lang['role_project_manager']) ?></option><option value="admin"><?= e($lang['role_admin']) ?></option></select></div><div class="form-group"><label class="form-label"><?= e($lang['user_company']) ?></label><input type="text" name="company" class="form-control"></div></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> <?= e($lang['create']) ?></button><button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')"><?= e($lang['cancel']) ?></button></div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title"><i class="fas fa-user-edit"></i> <?= e($lang['edit_user']) ?></div><button class="modal-close" onclick="closeModal('editUserModal')"><i class="fas fa-times"></i></button></div>
        <form method="POST">
            <?= csrfField() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" id="eu_id">
            <div class="modal-body">
                <div class="form-row"><div class="form-group"><label class="form-label"><?= e($lang['user_name']) ?></label><input type="text" name="name" id="eu_name" class="form-control" required></div><div class="form-group"><label class="form-label"><?= e($lang['user_email']) ?></label><input type="email" name="email" id="eu_email" class="form-control" required></div></div>
                <div class="form-row"><div class="form-group"><label class="form-label"><?= e($lang['new_password']) ?></label><input type="password" name="password" class="form-control" placeholder="(<?= e($lang['cancel']) ?>)"></div><div class="form-group"><label class="form-label"><?= e($lang['user_phone']) ?></label><input type="text" name="phone" id="eu_phone" class="form-control"></div></div>
                <div class="form-row"><div class="form-group"><label class="form-label"><?= e($lang['user_role']) ?></label><select name="role" id="eu_role" class="form-control"><option value="client"><?= e($lang['role_client']) ?></option><option value="project_manager"><?= e($lang['role_project_manager']) ?></option><option value="admin"><?= e($lang['role_admin']) ?></option></select></div><div class="form-group"><label class="form-label"><?= e($lang['user_company']) ?></label><input type="text" name="company" id="eu_company" class="form-control"></div></div>
                <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" id="eu_active" value="1"><span class="form-label" style="margin:0"><?= e($lang['user_active']) ?></span></label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= e($lang['save']) ?></button><button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')"><?= e($lang['cancel']) ?></button></div>
        </form>
    </div>
</div>

<script>
function editUser(u) {
    document.getElementById('eu_id').value = u.id;
    document.getElementById('eu_name').value = u.name;
    document.getElementById('eu_email').value = u.email;
    document.getElementById('eu_phone').value = u.phone || '';
    document.getElementById('eu_role').value = u.role;
    document.getElementById('eu_company').value = u.company || '';
    document.getElementById('eu_active').checked = u.is_active == 1;
    openModal('editUserModal');
}
</script>

<?php require_once 'includes/footer.php'; ?>
