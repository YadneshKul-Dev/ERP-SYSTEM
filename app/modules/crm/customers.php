<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $custId = (int)($_POST['id'] ?? 0);
    $data = [
        'customer_name' => sanitize($_POST['customer_name'] ?? ''),
        'company_name' => sanitize($_POST['company_name'] ?? ''),
        'gst_number' => sanitize($_POST['gst_number'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'address' => sanitize($_POST['address'] ?? ''),
        'city' => sanitize($_POST['city'] ?? ''),
        'state' => sanitize($_POST['state'] ?? ''),
        'pincode' => sanitize($_POST['pincode'] ?? ''),
        'contact_person' => sanitize($_POST['contact_person'] ?? ''),
        'contact_phone' => sanitize($_POST['contact_phone'] ?? ''),
        'lead_source' => sanitize($_POST['lead_source'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'active'),
        'notes' => sanitize($_POST['notes'] ?? '')
    ];

    if (empty($data['customer_name'])) {
        showError('Customer name is required');
        header('Location: customers.php' . ($custId ? "?action=edit&id=$custId" : '?action=add'));
        exit;
    }

    if ($custId) {
        $db->update('customers', $data, 'id = ?', [$custId]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'edit', 'customers', 'Edited customer: ' . $data['customer_name']);
        showSuccess('Customer updated successfully');
    } else {
        $data['customer_code'] = generateCode('CUST');
        $data['created_by'] = getUserId();
        $db->insert('customers', $data);
        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'customers', 'Created customer: ' . $data['customer_name']);
        showSuccess('Customer created successfully');
    }
    header('Location: customers.php');
    exit;
}

if (isset($_GET['delete']) && $id) {
    $cust = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
    if ($cust) {
        $db->delete('customers', 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'delete', 'customers', 'Deleted customer: ' . $cust['customer_name']);
        showSuccess('Customer deleted successfully');
    }
    header('Location: customers.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('customers');

if ($action === 'add' || ($action === 'edit' && $id)) {
    $cust = $action === 'edit' ? $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]) : null;
?>
<div class="page-header">
    <h1 class="page-title"><?= $cust ? 'Edit Customer' : 'Add Customer' ?></h1>
    <p class="page-subtitle"><?= $cust ? 'Update customer information' : 'Register a new customer' ?></p>
</div>

<div class="card">
    <div class="card-header">
        <h3>Customer Details</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php if ($cust): ?>
            <input type="hidden" name="id" value="<?= $cust['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Customer Name *</label>
                    <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($cust['customer_name'] ?? '') ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($cust['company_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($cust['phone'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cust['email'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>GST Number</label>
                    <input type="text" name="gst_number" class="form-control" value="<?= htmlspecialchars($cust['gst_number'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($cust['address'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($cust['city'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>State</label>
                    <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($cust['state'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($cust['pincode'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($cust['contact_person'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($cust['contact_phone'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Lead Source</label>
                    <input type="text" name="lead_source" class="form-control" value="<?= htmlspecialchars($cust['lead_source'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($cust['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($cust['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($cust['notes'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $cust ? 'Update' : 'Save' ?></button>
                <a href="customers.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php } else {
    $search = sanitize($_GET['search'] ?? '');
    $page = (int)($_GET['page'] ?? 1);
    $perPage = 20;

    $where = [];
    $params = [];
    if ($search) {
        $where[] = "(customer_name LIKE ? OR company_name LIKE ? OR phone LIKE ? OR email LIKE ? OR customer_code LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $db->fetchColumn("SELECT COUNT(*) FROM customers $whereClause", $params);
    $offset = ($page - 1) * $perPage;
    $customers = $db->fetchAll("SELECT * FROM customers $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
?>
<div class="page-header">
    <h1 class="page-title">Customers</h1>
    <p class="page-subtitle">Manage your customers</p>
    <a href="?action=add" class="btn btn-primary">Add Customer</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>All Customers</h3>
        <div class="search-bar">
            <form method="GET">
                <input type="text" name="search" class="form-control" placeholder="Search customers..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
            </form>
        </div>
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
                    <th>City</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="8" class="text-center">No customers found</td></tr>
                <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['customer_code']) ?></td>
                    <td><?= htmlspecialchars($c['customer_name']) ?></td>
                    <td><?= htmlspecialchars($c['company_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['city'] ?? '-') ?></td>
                    <td><?= getStatusBadge($c['status']) ?></td>
                    <td class="action-bar">
                        <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                        <a href="?delete=1&id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this customer?')">Delete</a>
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
