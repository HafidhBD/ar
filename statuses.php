<?php
require_once 'config.php';
requireLogin();
requireRole('admin');
$lang = getLang();
$pageTitle = $lang['lang_code'] === 'ar' ? 'إدارة الحالات' : 'Manage Statuses';
$msg = '';
$msgType = '';
$isAr = $lang['lang_code'] === 'ar';

// Auto-migrate
try { $pdo->query("SELECT id FROM task_statuses LIMIT 1"); } catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `task_statuses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `slug` VARCHAR(50) NOT NULL UNIQUE,
        `label_ar` VARCHAR(100) NOT NULL, `label_en` VARCHAR(100) NOT NULL,
        `color` VARCHAR(7) NOT NULL DEFAULT '#6366f1', `bg_color` VARCHAR(7) NOT NULL DEFAULT '#ede9fe',
        `sort_order` INT DEFAULT 0, `is_default` TINYINT(1) DEFAULT 0, `is_completed` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $defaults = [
        ['new','جديد','New','#5b21b6','#ede9fe',0,1,0],
        ['in_progress','جاري التنفيذ','In Progress','#1e40af','#dbeafe',1,0,0],
        ['delivered','تم التسليم','Delivered','#0e7490','#cffafe',2,0,0],
        ['pending_review','بانتظار المراجعة','Pending Review','#7c3aed','#f3e8ff',3,0,0],
        ['needs_revision','يحتاج تعديل','Needs Revision','#92400e','#fef3c7',4,0,0],
        ['completed','مكتمل','Completed','#166534','#dcfce7',5,0,1],
    ];
    $st = $pdo->prepare("INSERT IGNORE INTO task_statuses (slug,label_ar,label_en,color,bg_color,sort_order,is_default,is_completed) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($defaults as $d) $st->execute($d);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $slug = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['slug'] ?? '')));
        $labelAr = trim($_POST['label_ar'] ?? '');
        $labelEn = trim($_POST['label_en'] ?? '');
        $color = $_POST['color'] ?? '#6366f1';
        $bgColor = $_POST['bg_color'] ?? '#ede9fe';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        $isCompleted = isset($_POST['is_completed']) ? 1 : 0;

        if (empty($slug) || empty($labelAr) || empty($labelEn)) {
            $msg = $lang['required_fields']; $msgType = 'danger';
        } else {
            $check = $pdo->prepare("SELECT id FROM task_statuses WHERE slug=?");
            $check->execute([$slug]);
            if ($check->fetch()) {
                $msg = $isAr ? 'المعرف مستخدم مسبقاً' : 'Slug already exists'; $msgType = 'danger';
            } else {
                if ($isDefault) $pdo->exec("UPDATE task_statuses SET is_default=0");
                $pdo->prepare("INSERT INTO task_statuses (slug,label_ar,label_en,color,bg_color,sort_order,is_default,is_completed) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$slug, $labelAr, $labelEn, $color, $bgColor, $sortOrder, $isDefault, $isCompleted]);
                $msg = $isAr ? 'تم إضافة الحالة بنجاح' : 'Status added successfully'; $msgType = 'success';
            }
        }
    }

    if ($action === 'update') {
        $id = (int)$_POST['status_id'];
        $labelAr = trim($_POST['label_ar'] ?? '');
        $labelEn = trim($_POST['label_en'] ?? '');
        $color = $_POST['color'] ?? '#6366f1';
        $bgColor = $_POST['bg_color'] ?? '#ede9fe';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        $isCompleted = isset($_POST['is_completed']) ? 1 : 0;

        if ($isDefault) $pdo->exec("UPDATE task_statuses SET is_default=0");
        $pdo->prepare("UPDATE task_statuses SET label_ar=?, label_en=?, color=?, bg_color=?, sort_order=?, is_default=?, is_completed=? WHERE id=?")
            ->execute([$labelAr, $labelEn, $color, $bgColor, $sortOrder, $isDefault, $isCompleted, $id]);
        $msg = $isAr ? 'تم تحديث الحالة' : 'Status updated'; $msgType = 'success';
    }

    if ($action === 'delete') {
        $id = (int)$_POST['status_id'];
        // Don't delete if tasks use it
        $st = $pdo->prepare("SELECT slug FROM task_statuses WHERE id=?");
        $st->execute([$id]);
        $status = $st->fetch();
        if ($status) {
            $used = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status=?");
            $used->execute([$status['slug']]);
            if ($used->fetchColumn() > 0) {
                $msg = $isAr ? 'لا يمكن حذف حالة مستخدمة في مهام' : 'Cannot delete a status used by tasks'; $msgType = 'danger';
            } else {
                $pdo->prepare("DELETE FROM task_statuses WHERE id=?")->execute([$id]);
                $msg = $isAr ? 'تم حذف الحالة' : 'Status deleted'; $msgType = 'success';
            }
        }
    }
}

$statuses = $pdo->query("SELECT * FROM task_statuses ORDER BY sort_order ASC")->fetchAll();

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-palette"></i> <?= e($isAr ? 'حالات المهام' : 'Task Statuses') ?></div>
        <button class="btn btn-primary" onclick="openModal('addStatusModal')"><i class="fas fa-plus"></i> <?= e($isAr ? 'إضافة حالة' : 'Add Status') ?></button>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><?= e($isAr ? 'الترتيب' : 'Order') ?></th>
                    <th><?= e($isAr ? 'المعرف' : 'Slug') ?></th>
                    <th><?= e($isAr ? 'الاسم (عربي)' : 'Label (AR)') ?></th>
                    <th><?= e($isAr ? 'الاسم (English)' : 'Label (EN)') ?></th>
                    <th><?= e($isAr ? 'المعاينة' : 'Preview') ?></th>
                    <th><?= e($isAr ? 'الألوان' : 'Colors') ?></th>
                    <th><?= e($isAr ? 'خيارات' : 'Options') ?></th>
                    <th><?= e($lang['actions']) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statuses as $s): ?>
                <tr>
                    <td><?= $s['sort_order'] ?></td>
                    <td><code style="font-size:12px;background:#f1f5f9;padding:2px 8px;border-radius:4px"><?= e($s['slug']) ?></code></td>
                    <td><strong><?= e($s['label_ar']) ?></strong></td>
                    <td><?= e($s['label_en']) ?></td>
                    <td><span class="badge" style="background:<?= e($s['bg_color']) ?>;color:<?= e($s['color']) ?>"><?= e($isAr ? $s['label_ar'] : $s['label_en']) ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px">
                            <span style="width:20px;height:20px;border-radius:4px;background:<?= e($s['color']) ?>;display:inline-block;border:1px solid var(--border)"></span>
                            <span style="width:20px;height:20px;border-radius:4px;background:<?= e($s['bg_color']) ?>;display:inline-block;border:1px solid var(--border)"></span>
                            <span class="text-muted fs-sm"><?= e($s['color']) ?> / <?= e($s['bg_color']) ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if ($s['is_default']): ?><span class="badge" style="background:#dbeafe;color:#1e40af;font-size:11px"><?= e($isAr ? 'افتراضي' : 'Default') ?></span><?php endif; ?>
                        <?php if ($s['is_completed']): ?><span class="badge" style="background:#dcfce7;color:#166534;font-size:11px"><?= e($isAr ? 'مكتمل' : 'Completed') ?></span><?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-secondary" onclick='editStatus(<?= json_encode($s) ?>)'><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('<?= e($lang['confirm_delete']) ?>')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="status_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Status Modal -->
<div class="modal-overlay" id="addStatusModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plus-circle"></i> <?= e($isAr ? 'إضافة حالة جديدة' : 'Add New Status') ?></div>
            <button class="modal-close" onclick="closeModal('addStatusModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= e($isAr ? 'المعرف (بالإنجليزية، بدون مسافات)' : 'Slug (English, no spaces)') ?> *</label>
                    <input type="text" name="slug" class="form-control" required placeholder="e.g. under_review" pattern="[a-z0-9_]+">
                    <div class="form-text"><?= e($isAr ? 'أحرف إنجليزية صغيرة وأرقام و _ فقط' : 'Lowercase letters, numbers, and underscores only') ?></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= e($isAr ? 'الاسم بالعربي' : 'Arabic Label') ?> *</label>
                        <input type="text" name="label_ar" class="form-control" required placeholder="مثال: قيد المراجعة">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($isAr ? 'الاسم بالإنجليزي' : 'English Label') ?> *</label>
                        <input type="text" name="label_en" class="form-control" required placeholder="e.g. Under Review">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= e($isAr ? 'لون النص' : 'Text Color') ?></label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="color" name="color" value="#6366f1" style="width:50px;height:38px;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                            <input type="text" class="form-control" style="flex:1" value="#6366f1" onchange="this.previousElementSibling.value=this.value" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($isAr ? 'لون الخلفية' : 'Background Color') ?></label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="color" name="bg_color" value="#ede9fe" style="width:50px;height:38px;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                            <input type="text" class="form-control" style="flex:1" value="#ede9fe" onchange="this.previousElementSibling.value=this.value" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($isAr ? 'الترتيب' : 'Sort Order') ?></label>
                    <input type="number" name="sort_order" class="form-control" value="0" min="0">
                </div>
                <div style="display:flex;gap:20px">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                        <input type="checkbox" name="is_default" value="1" style="accent-color:#6366f1">
                        <?= e($isAr ? 'حالة افتراضية للمهام الجديدة' : 'Default status for new tasks') ?>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                        <input type="checkbox" name="is_completed" value="1" style="accent-color:#16a34a">
                        <?= e($isAr ? 'تعتبر مكتملة' : 'Counts as completed') ?>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> <?= e($isAr ? 'إضافة' : 'Add') ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStatusModal')"><?= e($lang['cancel']) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Status Modal -->
<div class="modal-overlay" id="editStatusModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit"></i> <?= e($isAr ? 'تعديل الحالة' : 'Edit Status') ?></div>
            <button class="modal-close" onclick="closeModal('editStatusModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="status_id" id="es_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?= e($isAr ? 'المعرف' : 'Slug') ?></label>
                    <input type="text" id="es_slug" class="form-control" disabled style="background:#f1f5f9">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= e($isAr ? 'الاسم بالعربي' : 'Arabic Label') ?> *</label>
                        <input type="text" name="label_ar" id="es_label_ar" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($isAr ? 'الاسم بالإنجليزي' : 'English Label') ?> *</label>
                        <input type="text" name="label_en" id="es_label_en" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= e($isAr ? 'لون النص' : 'Text Color') ?></label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="color" name="color" id="es_color" style="width:50px;height:38px;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                            <input type="text" id="es_color_text" class="form-control" style="flex:1" onchange="document.getElementById('es_color').value=this.value" oninput="document.getElementById('es_color').value=this.value">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e($isAr ? 'لون الخلفية' : 'Background Color') ?></label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="color" name="bg_color" id="es_bg_color" style="width:50px;height:38px;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                            <input type="text" id="es_bg_text" class="form-control" style="flex:1" onchange="document.getElementById('es_bg_color').value=this.value" oninput="document.getElementById('es_bg_color').value=this.value">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e($isAr ? 'الترتيب' : 'Sort Order') ?></label>
                    <input type="number" name="sort_order" id="es_sort" class="form-control" min="0">
                </div>
                <div style="display:flex;gap:20px">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                        <input type="checkbox" name="is_default" id="es_default" value="1" style="accent-color:#6366f1">
                        <?= e($isAr ? 'حالة افتراضية' : 'Default status') ?>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                        <input type="checkbox" name="is_completed" id="es_completed" value="1" style="accent-color:#16a34a">
                        <?= e($isAr ? 'تعتبر مكتملة' : 'Counts as completed') ?>
                    </label>
                </div>
                <div class="form-group mt-2">
                    <label class="form-label"><?= e($isAr ? 'معاينة' : 'Preview') ?></label>
                    <div id="es_preview" style="display:inline-flex"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= e($lang['save']) ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editStatusModal')"><?= e($lang['cancel']) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function editStatus(s) {
    document.getElementById('es_id').value = s.id;
    document.getElementById('es_slug').value = s.slug;
    document.getElementById('es_label_ar').value = s.label_ar;
    document.getElementById('es_label_en').value = s.label_en;
    document.getElementById('es_color').value = s.color;
    document.getElementById('es_color_text').value = s.color;
    document.getElementById('es_bg_color').value = s.bg_color;
    document.getElementById('es_bg_text').value = s.bg_color;
    document.getElementById('es_sort').value = s.sort_order;
    document.getElementById('es_default').checked = s.is_default == 1;
    document.getElementById('es_completed').checked = s.is_completed == 1;
    updatePreview();
    openModal('editStatusModal');
}
function updatePreview() {
    var c = document.getElementById('es_color').value;
    var bg = document.getElementById('es_bg_color').value;
    var label = document.getElementById('es_label_ar').value || 'معاينة';
    document.getElementById('es_preview').innerHTML = '<span class="badge" style="background:'+bg+';color:'+c+';font-size:13px;padding:6px 14px">'+label+'</span>';
}
document.querySelectorAll('#editStatusModal input').forEach(function(el) { el.addEventListener('input', updatePreview); });
</script>

<?php require_once 'includes/footer.php'; ?>
