<?php
require_once __DIR__ . '/../../init.php';
requirePermission('budget');

$db = Database::getInstance();
$enc = Encryption::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && hasPermission('budget', 'create')) {
        $db->insert('budgets', [
            'budget_code' => $enc->generateEmployeeCode('BDG'),
            'budget_name' => sanitize($_POST['budget_name']),
            'fiscal_year' => sanitize($_POST['fiscal_year']),
            'department' => sanitize($_POST['department'] ?? ''),
            'total_amount' => (float)$_POST['total_amount'],
            'amount_used' => (float)($_POST['amount_used'] ?? 0),
            'status' => 'active',
            'created_by' => getUserId()
        ]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'budget', "Created budget: {$_POST['budget_name']}");
        showSuccess('Budget created successfully');
    } elseif ($action === 'edit' && hasPermission('budget', 'edit')) {
        $db->update('budgets', [
            'budget_name' => sanitize($_POST['budget_name']),
            'fiscal_year' => sanitize($_POST['fiscal_year']),
            'department' => sanitize($_POST['department'] ?? ''),
            'total_amount' => (float)$_POST['total_amount'],
            'amount_used' => (float)($_POST['amount_used'] ?? 0)
        ], 'id = ?', [(int)$_POST['id']]);
        showSuccess('Budget updated successfully');
    } elseif ($action === 'delete' && hasPermission('budget', 'delete')) {
        $db->delete('budgets', 'id = ?', [(int)$_POST['id']]);
        showSuccess('Budget deleted');
    }
    header('Location: /modules/finance/budget.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$fiscalYear = $_GET['fiscal_year'] ?? '';
$deptFilter = $_GET['department'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($fiscalYear) { $where .= " AND fiscal_year = ?"; $params[] = $fiscalYear; }
if ($deptFilter) { $where .= " AND department = ?"; $params[] = $deptFilter; }

$budgets = $db->fetchAll("SELECT * FROM budgets $where ORDER BY fiscal_year DESC, budget_name ASC");
$fiscalYears = $db->fetchAll("SELECT DISTINCT fiscal_year FROM budgets ORDER BY fiscal_year DESC");
$departments = $db->fetchAll("SELECT DISTINCT department FROM budgets WHERE department != '' ORDER BY department");
$allDepts = $db->fetchAll("SELECT department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
?>

<div class="page-header">
    <h1 class="page-title">Budget Management</h1>
    <p class="page-subtitle">Plan, track, and manage departmental budgets</p>
</div>

<div class="action-bar">
    <form method="GET" class="search-bar">
        <select name="fiscal_year" class="form-select" style="max-width:160px" onchange="this.form.submit()">
            <option value="">All Years</option>
            <?php foreach ($fiscalYears as $fy): ?>
            <option value="<?= htmlspecialchars($fy['fiscal_year']) ?>" <?= $fiscalYear === $fy['fiscal_year'] ? 'selected' : '' ?>><?= htmlspecialchars($fy['fiscal_year']) ?></option>
            <?php endforeach; ?>
            <option value="<?= date('Y') ?>" <?= !$fiscalYear ? 'selected' : '' ?>><?= date('Y') ?></option>
        </select>
        <select name="department" class="form-select" style="max-width:200px" onchange="this.form.submit()">
            <option value="">All Departments</option>
            <?php foreach ($allDepts as $d): ?>
            <option value="<?= htmlspecialchars($d['department_name']) ?>" <?= $deptFilter === $d['department_name'] ? 'selected' : '' ?>><?= htmlspecialchars($d['department_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>
    <?php if (hasPermission('budget', 'create')): ?>
    <button class="btn btn-success" onclick="openModal('addBudgetModal')">+ New Budget</button>
    <?php endif; ?>
</div>

<div class="row">
    <?php foreach ($budgets as $b):
        $usedPct = $b['total_amount'] > 0 ? round(($b['amount_used'] / $b['total_amount']) * 100, 1) : 0;
        $remaining = $b['total_amount'] - $b['amount_used'];
        $pctColor = $usedPct > 90 ? 'danger' : ($usedPct > 70 ? 'warning' : 'success');
    ?>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h3><?= htmlspecialchars($b['budget_name']) ?></h3>
                <span class="badge bg-info">FY <?= htmlspecialchars($b['fiscal_year']) ?></span>
            </div>
            <div class="card-body">
                <div class="info-grid" style="grid-template-columns:1fr 1fr">
                    <div class="info-item"><span class="info-label">Code</span><span class="info-value"><?= htmlspecialchars($b['budget_code']) ?></span></div>
                    <div class="info-item"><span class="info-label">Department</span><span class="info-value"><?= htmlspecialchars($b['department'] ?: 'Company-wide') ?></span></div>
                    <div class="info-item"><span class="info-label">Budget</span><span class="info-value"><?= formatCurrency($b['total_amount']) ?></span></div>
                    <div class="info-item"><span class="info-label">Used</span><span class="info-value"><?= formatCurrency($b['amount_used']) ?></span></div>
                    <div class="info-item"><span class="info-label">Remaining</span><span class="info-value"><?= formatCurrency($remaining) ?></span></div>
                    <div class="info-item"><span class="info-label">Status</span><span class="info-value"><?= getStatusBadge($b['status']) ?></span></div>
                </div>
                <div style="margin-top:12px">
                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                        <span><?= $usedPct ?>% used</span>
                        <span><?= formatCurrency($b['amount_used']) ?> / <?= formatCurrency($b['total_amount']) ?></span>
                    </div>
                    <div style="height:8px;background:var(--border);border-radius:4px;overflow:hidden">
                        <div style="height:100%;width:<?= $usedPct ?>%;background:var(--<?= $pctColor ?>);border-radius:4px;transition:width 0.3s"></div>
                    </div>
                </div>
                <div style="margin-top:12px;display:flex;gap:8px">
                    <?php if (hasPermission('budget', 'edit')): ?>
                    <button class="btn btn-sm btn-primary" onclick="editBudget(<?= $b['id'] ?>, '<?= htmlspecialchars($b['budget_name']) ?>', '<?= $b['fiscal_year'] ?>', '<?= htmlspecialchars($b['department'] ?? '') ?>', <?= $b['total_amount'] ?>, <?= $b['amount_used'] ?>)">Edit</button>
                    <?php endif; ?>
                    <?php if (hasPermission('budget', 'delete')): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete budget <?= htmlspecialchars($b['budget_name']) ?>?')">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($budgets)): ?>
    <div class="col-md-12">
        <div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-light)">No budgets created yet. Create one to start tracking.</div></div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Budget Modal -->
<div class="modal-overlay" id="addBudgetModal">
    <div class="modal">
        <div class="modal-header"><h3>New Budget</h3><button class="modal-close" onclick="closeModal('addBudgetModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Budget Name *</label>
                        <input type="text" name="budget_name" class="form-control" required placeholder="e.g., Annual Operations Budget">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Fiscal Year *</label>
                        <input type="text" name="fiscal_year" class="form-control" required placeholder="e.g., 2026" value="<?= date('Y') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Department</label>
                        <select name="department" class="form-select">
                            <option value="">Company-wide</option>
                            <?php foreach ($allDepts as $d): ?>
                            <option value="<?= htmlspecialchars($d['department_name']) ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Total Amount (₹) *</label>
                        <input type="number" step="0.01" name="total_amount" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Amount Used So Far (₹)</label>
                    <input type="number" step="0.01" name="amount_used" class="form-control" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addBudgetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Budget</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Budget Modal -->
<div class="modal-overlay" id="editBudgetModal">
    <div class="modal">
        <div class="modal-header"><h3>Edit Budget</h3><button class="modal-close" onclick="closeModal('editBudgetModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_budget_id">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Budget Name *</label>
                        <input type="text" name="budget_name" id="edit_budget_name" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Fiscal Year *</label>
                        <input type="text" name="fiscal_year" id="edit_fiscal_year" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Department</label>
                        <select name="department" id="edit_department" class="form-select">
                            <option value="">Company-wide</option>
                            <?php foreach ($allDepts as $d): ?>
                            <option value="<?= htmlspecialchars($d['department_name']) ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Total Amount (₹) *</label>
                        <input type="number" step="0.01" name="total_amount" id="edit_total_amount" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Amount Used (₹)</label>
                    <input type="number" step="0.01" name="amount_used" id="edit_amount_used" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editBudgetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Budget</button>
            </div>
        </form>
    </div>
</div>

<script>
function editBudget(id, name, year, dept, total, used) {
    document.getElementById('edit_budget_id').value = id;
    document.getElementById('edit_budget_name').value = name;
    document.getElementById('edit_fiscal_year').value = year;
    document.getElementById('edit_department').value = dept;
    document.getElementById('edit_total_amount').value = total;
    document.getElementById('edit_amount_used').value = used;
    openModal('editBudgetModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
