<?php
require_once __DIR__ . '/../../init.php';
requirePermission('chart_of_accounts');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && hasPermission('chart_of_accounts', 'create')) {
        $db->insert('chart_of_accounts', [
            'account_code' => sanitize($_POST['account_code']),
            'account_name' => sanitize($_POST['account_name']),
            'account_type' => sanitize($_POST['account_type']),
            'parent_code' => sanitize($_POST['parent_code'] ?: null),
            'description' => sanitize($_POST['description'] ?? ''),
            'is_active' => (int)(!empty($_POST['is_active']))
        ]);
        showSuccess('Account created successfully');
    } elseif ($action === 'edit' && hasPermission('chart_of_accounts', 'edit')) {
        $db->update('chart_of_accounts', [
            'account_name' => sanitize($_POST['account_name']),
            'account_type' => sanitize($_POST['account_type']),
            'parent_code' => sanitize($_POST['parent_code'] ?: null),
            'description' => sanitize($_POST['description'] ?? ''),
            'is_active' => (int)(!empty($_POST['is_active']))
        ], 'id = ?', [(int)$_POST['id']]);
        showSuccess('Account updated successfully');
    } elseif ($action === 'delete' && hasPermission('chart_of_accounts', 'delete')) {
        $db->delete('chart_of_accounts', 'id = ?', [(int)$_POST['id']]);
        showSuccess('Account deleted successfully');
    } elseif ($action === 'toggle_status') {
        $coa = $db->fetchOne("SELECT is_active FROM chart_of_accounts WHERE id = ?", [(int)$_POST['id']]);
        if ($coa) {
            $db->update('chart_of_accounts', ['is_active' => $coa['is_active'] ? 0 : 1], 'id = ?', [(int)$_POST['id']]);
        }
    }
    header('Location: /modules/accounts/chart_of_accounts.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$typeFilter = $_GET['type'] ?? '';
$parentFilter = $_GET['parent'] ?? '';
$search = $_GET['search'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($typeFilter) {
    $where .= " AND account_type = ?";
    $params[] = $typeFilter;
}
if ($parentFilter === 'none') {
    $where .= " AND parent_code IS NULL";
} elseif ($parentFilter) {
    $where .= " AND parent_code = ?";
    $params[] = $parentFilter;
}
if ($search) {
    $where .= " AND (account_name LIKE ? OR account_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$accounts = $db->fetchAll("SELECT * FROM chart_of_accounts $where ORDER BY account_code");
$parentAccounts = $db->fetchAll("SELECT * FROM chart_of_accounts WHERE parent_code IS NULL ORDER BY account_code");
$accountTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];
?>

<div class="page-header">
    <h1 class="page-title">Chart of Accounts</h1>
    <p class="page-subtitle">Manage your chart of accounts for double-entry bookkeeping</p>
</div>

<div class="action-bar">
    <form method="GET" class="search-bar">
        <input type="text" name="search" class="form-control" placeholder="Search accounts..." value="<?= htmlspecialchars($search) ?>">
        <select name="type" class="form-select" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach ($accountTypes as $t): ?>
            <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
    </form>
    <?php if (hasPermission('chart_of_accounts', 'create')): ?>
    <button class="btn btn-success" onclick="openModal('addAccountModal')">+ New Account</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h3>Accounts List</h3><span><?= count($accounts) ?> accounts</span></div>
    <div class="card-body p-0">
        <table class="table">
            <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Parent</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                <?php $parent = $a['parent_code'] ? $db->fetchOne("SELECT account_name FROM chart_of_accounts WHERE account_code = ?", [$a['parent_code']]) : null; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['account_code']) ?></strong></td>
                    <td><?= htmlspecialchars($a['account_name']) ?></td>
                    <td><span class="badge bg-info"><?= htmlspecialchars($a['account_type']) ?></span></td>
                    <td><?= $parent ? htmlspecialchars($parent['account_name']) : '-' ?></td>
                    <td><?= $a['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td>
                        <?php if (hasPermission('chart_of_accounts', 'edit')): ?>
                        <a href="javascript:void(0)" class="btn btn-sm btn-primary" onclick="editAccount(<?= $a['id'] ?>)">Edit</a>
                        <?php endif; ?>
                        <?php if (hasPermission('chart_of_accounts', 'delete')): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this account?')">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                        <?php endif; ?>
                        <a href="javascript:void(0)" onclick="toggleStatus(<?= $a['id'] ?>)" class="btn btn-sm btn-<?= $a['is_active'] ? 'warning' : 'success' ?>"><?= $a['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($accounts)): ?>
                <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-light)">No accounts found. Create one to get started.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal-overlay" id="addAccountModal">
    <div class="modal">
        <div class="modal-header"><h3>New Account</h3><button class="modal-close" onclick="closeModal('addAccountModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Account Code *</label>
                        <input type="text" name="account_code" class="form-control" required placeholder="e.g., 1101">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Account Type *</label>
                        <select name="account_type" class="form-select" required>
                            <?php foreach ($accountTypes as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Account Name *</label>
                    <input type="text" name="account_name" class="form-control" required placeholder="e.g., Cash in Hand">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Parent Account (optional)</label>
                        <select name="parent_code" class="form-select">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($parentAccounts as $p): ?>
                            <option value="<?= htmlspecialchars($p['account_code']) ?>"><?= htmlspecialchars($p['account_code'] . ' - ' . $p['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <div class="checkbox-wrapper" style="margin-top:32px">
                            <input type="checkbox" id="is_active_add" name="is_active" checked>
                            <label for="is_active_add">Active</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAccountModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Account Modal -->
<div class="modal-overlay" id="editAccountModal">
    <div class="modal">
        <div class="modal-header"><h3>Edit Account</h3><button class="modal-close" onclick="closeModal('editAccountModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Account Code</label>
                        <input type="text" id="edit_account_code" class="form-control" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Account Type *</label>
                        <select name="account_type" id="edit_account_type" class="form-select" required>
                            <?php foreach ($accountTypes as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Account Name *</label>
                    <input type="text" name="account_name" id="edit_account_name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Parent Account</label>
                        <select name="parent_code" id="edit_parent_code" class="form-select">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($parentAccounts as $p): ?>
                            <option value="<?= htmlspecialchars($p['account_code']) ?>"><?= htmlspecialchars($p['account_code'] . ' - ' . $p['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <div class="checkbox-wrapper" style="margin-top:32px">
                            <input type="checkbox" id="edit_is_active" name="is_active">
                            <label for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editAccountModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Account</button>
            </div>
        </form>
    </div>
</div>

<script>
function editAccount(id) {
    fetch('/modules/accounts/ajax.php?action=get_account&id=' + id)
        .then(r => r.json())
        .then(a => {
            document.getElementById('edit_id').value = a.id;
            document.getElementById('edit_account_code').value = a.account_code;
            document.getElementById('edit_account_name').value = a.account_name;
            document.getElementById('edit_account_type').value = a.account_type;
            document.getElementById('edit_parent_code').value = a.parent_code || '';
            document.getElementById('edit_is_active').checked = a.is_active == 1;
            document.getElementById('edit_description').value = a.description || '';
            openModal('editAccountModal');
        });
}

function toggleStatus(id) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input name="id" value="' + id + '"><input name="action" value="toggle_status">';
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
