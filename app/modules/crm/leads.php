<?php
// Handle POST and GET actions BEFORE header output
require_once __DIR__ . '/../../init.php';

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$auth = Auth::getInstance();
$currentUser = $auth->getUser();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('leads');
    $leadId = (int)($_POST['id'] ?? 0);
    $data = [
        'lead_name' => sanitize($_POST['lead_name'] ?? ''),
        'company_name' => sanitize($_POST['company_name'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'source' => sanitize($_POST['source'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'new'),
        'assigned_to' => sanitize($_POST['assigned_to'] ?? ''),
        'notes' => sanitize($_POST['notes'] ?? '')
    ];

    if (empty($data['lead_name'])) {
        showError('Lead name is required');
        header('Location: leads.php' . ($leadId ? "?action=edit&id=$leadId" : '?action=add'));
        exit;
    }

    if ($leadId) {
        assertOwnership('leads', 'leads', $leadId, ['created_by', 'assigned_to']);
        $db->update('leads', $data, 'id = ?', [$leadId]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'edit', 'leads', 'Edited lead: ' . $data['lead_name']);
        showSuccess('Lead updated successfully');
    } else {
        $data['lead_code'] = generateCode('GEN');
        $data['created_by'] = getUserId();
        $db->insert('leads', $data);
        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'leads', 'Created lead: ' . $data['lead_name']);
        showSuccess('Lead created successfully');
    }
    header('Location: leads.php');
    exit;
}

// Handle delete
if (isset($_GET['delete']) && $id) {
    requirePermission('leads');
    assertOwnership('leads', 'leads', $id, ['created_by', 'assigned_to']);
    $lead = $db->fetchOne("SELECT * FROM leads WHERE id = ?", [$id]);
    if ($lead) {
        $db->delete('leads', 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'delete', 'leads', 'Deleted lead: ' . $lead['lead_name']);
        showSuccess('Lead deleted successfully');
    }
    header('Location: leads.php');
    exit;
}

// Now include header for page rendering
require_once __DIR__ . '/../../includes/header.php';
requirePermission('leads');

// Show add/edit form
if ($action === 'add' || ($action === 'edit' && $id)) {
    if ($action === 'edit') {
        assertOwnership('leads', 'leads', $id, ['created_by', 'assigned_to']);
        $lead = $db->fetchOne("SELECT * FROM leads WHERE id = ?", [$id]);
    } else {
        $lead = null;
    }
    $sources = ['website', 'referral', 'call', 'social', 'other'];
    $statuses = ['new', 'contacted', 'qualified', 'lost'];
?>
<div class="page-header">
    <h1 class="page-title"><?= $lead ? 'Edit Lead' : 'Add Lead' ?></h1>
    <p class="page-subtitle"><?= $lead ? 'Update lead information' : 'Create a new lead' ?></p>
</div>

<div class="card">
    <div class="card-header">
        <h3>Lead Details</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php if ($lead): ?>
            <input type="hidden" name="id" value="<?= $lead['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Lead Name *</label>
                    <input type="text" name="lead_name" class="form-control" value="<?= htmlspecialchars($lead['lead_name'] ?? '') ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($lead['company_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($lead['phone'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($lead['email'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Source</label>
                    <select name="source" class="form-select">
                        <option value="">Select Source</option>
                        <?php foreach ($sources as $s): ?>
                        <option value="<?= $s ?>" <?= ($lead['source'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= ($lead['status'] ?? 'new') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Assigned To</label>
                    <input type="text" name="assigned_to" class="form-control" value="<?= htmlspecialchars($lead['assigned_to'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($lead['notes'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $lead ? 'Update' : 'Save' ?></button>
                <a href="leads.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php } else { 
    // List view
    $search = sanitize($_GET['search'] ?? '');
    $statusFilter = sanitize($_GET['status'] ?? '');
    $page = (int)($_GET['page'] ?? 1);
    $perPage = 20;

    $where = [];
    $params = [];
    if ($search) {
        $where[] = "(lead_name LIKE ? OR company_name LIKE ? OR phone LIKE ? OR email LIKE ? OR lead_code LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
    }
    if ($statusFilter) {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    list($scopeWhere, $scopeParams) = applyScopeFilter('leads', ['created_by', 'assigned_to']);
    if ($scopeWhere) {
        $where[] = $scopeWhere;
        $params = array_merge($params, $scopeParams);
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $db->fetchColumn("SELECT COUNT(*) FROM leads $whereClause", $params);
    $offset = ($page - 1) * $perPage;
    $leads = $db->fetchAll("SELECT * FROM leads $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
?>
<div class="page-header">
    <h1 class="page-title">Leads</h1>
    <p class="page-subtitle">Manage sales leads</p>
    <a href="?action=add" class="btn btn-primary">Add Lead</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>All Leads</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <input type="text" name="search" class="form-control" placeholder="Search leads..." value="<?= htmlspecialchars($search) ?>" style="max-width:250px">
            <select name="status" class="form-select" style="width:auto">
                <option value="">All Statuses</option>
                <?php foreach (['new', 'contacted', 'qualified', 'lost'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                <tr><td colspan="9" class="text-center">No leads found</td></tr>
                <?php else: ?>
                <?php foreach ($leads as $l): ?>
                <tr>
                    <td><?= htmlspecialchars($l['lead_code']) ?></td>
                    <td><?= htmlspecialchars($l['lead_name']) ?></td>
                    <td><?= htmlspecialchars($l['company_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($l['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($l['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($l['source'] ?? '-') ?></td>
                    <td><?= getStatusBadge($l['status']) ?></td>
                    <td><?= htmlspecialchars($l['assigned_to'] ?? '-') ?></td>
                    <td>
                        <a href="?action=edit&id=<?= $l['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                        <a href="?delete=1&id=<?= $l['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this lead?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginate($total, $page, $perPage) ?>

<?php } ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
