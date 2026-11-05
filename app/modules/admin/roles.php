<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

$modules = [
    'dashboard' => ['view'],
    'admin' => ['view', 'create', 'edit', 'delete'],
    'users' => ['view', 'create', 'edit', 'delete'],
    'roles' => ['view', 'create', 'edit', 'delete'],
    'hr' => ['view', 'create', 'edit', 'delete'],
    'employees' => ['view', 'create', 'edit', 'delete'],
    'attendance' => ['view', 'create', 'edit', 'delete'],
    'leave' => ['view', 'create', 'edit', 'delete', 'approve'],
    'crm' => ['view', 'create', 'edit', 'delete'],
    'leads' => ['view', 'create', 'edit', 'delete'],
    'customers' => ['view', 'create', 'edit', 'delete'],
    'accounts' => ['view', 'create', 'edit', 'delete'],
    'finance' => ['view', 'create', 'edit', 'delete'],
    'invoices' => ['view', 'create', 'edit', 'delete'],
    'payments' => ['view', 'create', 'edit', 'delete'],
    'journal' => ['view', 'create', 'edit', 'delete', 'approve'],
    'ledger' => ['view', 'export'],
    'trial_balance' => ['view', 'export'],
    'bank_reconciliation' => ['view', 'create', 'edit'],
    'gst' => ['view', 'create', 'edit', 'file'],
    'chart_of_accounts' => ['view', 'create', 'edit', 'delete'],
    'budget' => ['view', 'create', 'edit', 'delete'],
    'financial_reports' => ['view', 'export'],
    'accounts_reports' => ['view', 'export'],
    'tickets' => ['view', 'create', 'edit', 'delete', 'assign'],
    'reports' => ['view', 'create', 'export'],
    'backup' => ['view', 'create', 'restore'],
    'settings' => ['view', 'edit'],
    'company' => ['view', 'create', 'edit'],
    'departments' => ['view', 'create', 'edit', 'delete'],
    'activity' => ['view', 'export'],
    'profile' => ['view', 'edit_own'],
    'id_card' => ['view', 'generate']
];

$departmentPresets = [
    'Human Resources' => ['modules' => ['hr', 'employees', 'attendance', 'leave'], 'label' => 'HR'],
    'Finance & Accounts' => ['modules' => ['accounts', 'finance', 'invoices', 'payments', 'journal', 'ledger', 'trial_balance', 'chart_of_accounts', 'budget', 'financial_reports', 'bank_reconciliation', 'gst', 'accounts_reports'], 'label' => 'Finance'],
    'IT Department' => ['modules' => ['tickets'], 'label' => 'IT'],
    'CRM & Sales' => ['modules' => ['crm', 'leads', 'customers'], 'label' => 'CRM'],
    'Operations' => ['modules' => ['hr', 'employees', 'attendance'], 'label' => 'Operations'],
    'Legal' => ['modules' => ['tickets'], 'label' => 'Legal'],
    'Marketing' => ['modules' => ['crm', 'leads', 'customers'], 'label' => 'Marketing'],
    'Purchasing' => ['modules' => ['invoices', 'payments'], 'label' => 'Purchasing'],
    'Inventory' => ['modules' => ['invoices'], 'label' => 'Inventory'],
    'Customer Support' => ['modules' => ['tickets', 'customers'], 'label' => 'Support'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_name = sanitize($_POST['role_name'] ?? '');
    $role_description = sanitize($_POST['role_description'] ?? '');
    $permissions = $_POST['permissions'] ?? [];
    $scopes = $_POST['scope'] ?? [];

    if (empty($role_name)) {
        showError('Role name is required.');
        header('Location: roles.php');
        exit;
    }

    $permData = [];
    foreach ($modules as $mod => $actions) {
        $selectedActions = $permissions[$mod] ?? [];
        $selectedActions = array_intersect($actions, $selectedActions);
        if (!empty($selectedActions)) {
            $scope = $scopes[$mod] ?? 'all';
            $permData[$mod] = [
                'actions' => array_values($selectedActions),
                'scope' => $scope === 'own' ? 'own' : 'all'
            ];
        }
    }

    $roleData = [
        'role_name' => $role_name,
        'role_description' => $role_description,
        'permissions' => json_encode($permData)
    ];

    if ($id > 0) {
        $db->update('roles', $roleData, 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'update', 'roles', 'Updated role ' . $role_name);
        showSuccess('Role updated successfully.');
    } else {
        $existing = $db->fetchOne("SELECT id FROM roles WHERE role_name = ?", [$role_name]);
        if ($existing) {
            showError('Role name already exists.');
            header('Location: roles.php');
            exit;
        }
        $db->insert('roles', $roleData);
        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'roles', 'Created role ' . $role_name);
        showSuccess('Role created successfully.');
    }
    header('Location: roles.php');
    exit;
}

if ($action === 'delete' && $id > 0 && hasPermission('roles', 'delete')) {
    $role = $db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
    if ($role) {
        if ($role['is_system']) {
            showError('System roles cannot be deleted.');
        } else {
            $db->delete('roles', 'id = ?', [$id]);
            $db->logActivity(getUserId(), getEmployeeCode(), 'delete', 'roles', 'Deleted role ' . $role['role_name']);
            showSuccess('Role deleted successfully.');
        }
    }
    header('Location: roles.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('roles');

$roles = $db->fetchAll("SELECT * FROM roles ORDER BY is_system DESC, role_name ASC");
$editRole = null;
if (($action === 'edit' || $action === 'view') && $id > 0) {
    $editRole = $db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
}
$editPerms = $editRole ? json_decode($editRole['permissions'] ?? '{}', true) : [];
?>

<div class="page-header">
    <h1 class="page-title">Roles & Permissions</h1>
    <?php if (hasPermission('roles', 'create')): ?>
        <a href="?action=add" class="btn btn-primary">Add Role</a>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3>All Roles</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>System</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($role['role_name']) ?></strong>
                                <?php if ($role['role_description']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($role['role_description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= $role['is_system'] ? getStatusBadge('locked') : '-' ?></td>
                            <td class="action-cell">
                                <?php if (hasPermission('roles', 'edit')): ?>
                                    <a href="?action=edit&id=<?= $role['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <?php endif; ?>
                                <?php if (hasPermission('roles', 'delete') && !$role['is_system']): ?>
                                    <a href="?action=delete&id=<?= $role['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete role <?= htmlspecialchars($role['role_name']) ?>?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="card">
            <div class="card-header">
                <h3><?= $action === 'edit' ? 'Edit' : 'Add' ?> Role</h3>
                <a href="roles.php" class="btn btn-sm btn-secondary">Back</a>
            </div>
            <div class="card-body">
                <form method="post" id="roleForm">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Role Name <span class="text-danger">*</span></label>
                            <input type="text" name="role_name" id="role_name" class="form-control" value="<?= htmlspecialchars($editRole['role_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Description</label>
                            <input type="text" name="role_description" id="role_description" class="form-control" value="<?= htmlspecialchars($editRole['role_description'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Department Presets -->
                    <?php if ($action === 'add'): ?>
                    <div class="card" style="background:var(--light);margin-bottom:16px">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-md-5">
                                    <label><strong>Quick-add: pick a department template</strong></label>
                                    <select id="presetDept" class="form-select" onchange="applyPreset()">
                                        <option value="">-- Select Department --</option>
                                        <?php foreach ($departmentPresets as $dept => $info): ?>
                                        <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($info['label']) ?> (<?= htmlspecialchars($dept) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>&nbsp;</label>
                                    <select id="presetType" class="form-select" onchange="applyPreset()">
                                        <option value="employee">Employee (own scope)</option>
                                        <option value="manager">Manager (all scope)</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2" style="display:flex;align-items:end">
                                    <button type="button" class="btn btn-sm btn-info" onclick="applyPreset()">Apply</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group"><label class="font-weight-bold">Permissions</label></div>
                    <div class="row">
                        <?php foreach ($modules as $mod => $actions):
                            $permVal = $editPerms[$mod] ?? [];
                            $savedActions = [];
                            $savedScope = 'all';
                            if (is_array($permVal) && isset($permVal['actions'])) {
                                $savedActions = $permVal['actions'];
                                $savedScope = $permVal['scope'] ?? 'all';
                            } elseif (is_array($permVal) && isset($permVal[0])) {
                                $savedActions = $permVal;
                            }
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="card card-sm">
                                <div class="card-header">
                                    <strong><?= ucfirst($mod) ?></strong>
                                    <label class="float-right"><input type="checkbox" class="select-module" data-module="<?= $mod ?>" <?= !empty($savedActions) ? 'checked' : '' ?>> All</label>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($actions as $act): ?>
                                    <div class="checkbox-item">
                                        <label>
                                            <input type="checkbox" name="permissions[<?= $mod ?>][]" value="<?= $act ?>" class="perm-<?= $mod ?>" <?= in_array($act, $savedActions) ? 'checked' : '' ?>>
                                            <?= ucfirst($act) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="scope-selector" style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">
                                        <span style="font-size:11px;font-weight:600;display:block;margin-bottom:4px">Scope:</span>
                                        <label style="font-size:12px;margin-right:8px">
                                            <input type="radio" name="scope[<?= $mod ?>]" value="all" <?= $savedScope === 'all' ? 'checked' : '' ?>> All
                                        </label>
                                        <label style="font-size:12px">
                                            <input type="radio" name="scope[<?= $mod ?>]" value="own" <?= $savedScope === 'own' ? 'checked' : '' ?>> Own
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="action-bar">
                        <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Update' : 'Create' ?> Role</button>
                        <a href="roles.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.select-module').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var module = this.dataset.module;
        var checked = this.checked;
        document.querySelectorAll('.perm-' + module).forEach(function(p) {
            p.checked = checked;
        });
    });
});

var departmentPresets = <?= json_encode($departmentPresets) ?>;

function applyPreset() {
    var dept = document.getElementById('presetDept').value;
    var type = document.getElementById('presetType').value;
    if (!dept) return;

    var preset = departmentPresets[dept];
    if (!preset) return;

    var maxScope = (type === 'employee') ? 'own' : 'all';
    var actions = (type === 'employee') ? ['view', 'create', 'edit'] : ['view', 'create', 'edit', 'delete'];

    // Uncheck all modules first
    document.querySelectorAll('.select-module').forEach(function(cb) {
        cb.checked = false;
        var module = cb.dataset.module;
        document.querySelectorAll('.perm-' + module).forEach(function(p) {
            p.checked = false;
        });
        // Reset scope to all
        var scopeRadios = document.querySelectorAll('input[name="scope[' + module + ']"]');
        scopeRadios.forEach(function(r) {
            r.checked = r.value === 'all';
        });
    });

    // Set role name hint
    var roleName = document.getElementById('role_name');
    var roleDesc = document.getElementById('role_description');
    var label = preset.label || dept;
    roleName.value = label + ' ' + (type === 'employee' ? 'Employee' : 'Manager');
    roleDesc.value = label + ' ' + (type === 'employee' ? 'Employee' : 'Manager') + ' role';

    // Enable specified modules
    preset.modules.forEach(function(mod) {
        var cb = document.querySelector('.select-module[data-module="' + mod + '"]');
        if (!cb) return;
        cb.checked = true;

        document.querySelectorAll('.perm-' + mod).forEach(function(p) {
            p.checked = actions.indexOf(p.value) !== -1;
        });

        // Set scope radio
        var scopeRadios = document.querySelectorAll('input[name="scope[' + mod + ']"]');
        scopeRadios.forEach(function(r) {
            r.checked = r.value === maxScope;
        });
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
