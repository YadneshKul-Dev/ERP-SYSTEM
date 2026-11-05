<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payId = (int)($_POST['id'] ?? 0);
    $data = [
        'invoice_id' => (int)($_POST['invoice_id'] ?? 0),
        'customer_id' => (int)($_POST['customer_id'] ?? 0),
        'payment_date' => sanitize($_POST['payment_date'] ?? date('Y-m-d')),
        'amount' => (float)($_POST['amount'] ?? 0),
        'payment_method' => sanitize($_POST['payment_method'] ?? ''),
        'reference_number' => sanitize($_POST['reference_number'] ?? ''),
        'notes' => sanitize($_POST['notes'] ?? '')
    ];

    if (!$data['invoice_id'] || !$data['customer_id'] || $data['amount'] <= 0) {
        showError('Please fill all required fields');
        header('Location: payments.php' . ($payId ? "?action=edit&id=$payId" : '?action=add'));
        exit;
    }

    if ($payId) {
        $db->update('payments', $data, 'id = ?', [$payId]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'edit', 'payments', 'Edited payment #' . $payId);
        showSuccess('Payment updated successfully');
    } else {
        $data['payment_number'] = generateCode('PAY');
        $data['created_by'] = getUserId();
        $db->insert('payments', $data);

        $inv = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$data['invoice_id']]);
        if ($inv) {
            $newPaid = $inv['amount_paid'] + $data['amount'];
            $newDue = $inv['total'] - $newPaid;
            $newStatus = $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : $inv['status']);
            $db->update('invoices', [
                'amount_paid' => $newPaid,
                'amount_due' => $newDue,
                'status' => $newStatus
            ], 'id = ?', [$data['invoice_id']]);
        }

        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'payments', 'Created payment #' . $data['payment_number']);
        showSuccess('Payment created successfully');
    }
    header('Location: payments.php');
    exit;
}

if (isset($_GET['delete']) && $id) {
    $pay = $db->fetchOne("SELECT * FROM payments WHERE id = ?", [$id]);
    if ($pay) {
        $inv = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$pay['invoice_id']]);
        if ($inv) {
            $newPaid = max(0, $inv['amount_paid'] - $pay['amount']);
            $newDue = $inv['total'] - $newPaid;
            $newStatus = $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
            $db->update('invoices', [
                'amount_paid' => $newPaid,
                'amount_due' => $newDue,
                'status' => $newStatus
            ], 'id = ?', [$pay['invoice_id']]);
        }

        $db->delete('payments', 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'delete', 'payments', 'Deleted payment #' . $pay['payment_number']);
        showSuccess('Payment deleted successfully');
    }
    header('Location: payments.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('payments');

if ($action === 'add' || ($action === 'edit' && $id)) {
    $pay = $action === 'edit' ? $db->fetchOne("SELECT * FROM payments WHERE id = ?", [$id]) : null;
    $customers = $db->fetchAll("SELECT id, customer_name, company_name FROM customers WHERE status = 'active' ORDER BY customer_name");
    $invoices = $db->fetchAll("SELECT id, invoice_number, total, amount_paid, amount_due FROM invoices ORDER BY invoice_number DESC");
    $methods = ['cash', 'cheque', 'bank transfer', 'online'];
?>
<div class="page-header">
    <h1 class="page-title"><?= $pay ? 'Edit Payment' : 'Record Payment' ?></h1>
    <p class="page-subtitle"><?= $pay ? 'Update payment information' : 'Record a new payment' ?></p>
</div>

<div class="card">
    <div class="card-header">
        <h3>Payment Details</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php if ($pay): ?>
            <input type="hidden" name="id" value="<?= $pay['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Customer *</label>
                    <select name="customer_id" class="form-select" required onchange="loadCustomerInvoices(this.value)">
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($pay['customer_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['company_name'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Invoice *</label>
                    <select name="invoice_id" class="form-select" required>
                        <option value="">Select Invoice</option>
                        <?php foreach ($invoices as $inv): ?>
                        <option value="<?= $inv['id'] ?>" data-customer=""
                            <?= ($pay['invoice_id'] ?? 0) == $inv['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inv['invoice_number']) ?> - <?= formatCurrency($inv['total']) ?> (Due: <?= formatCurrency($inv['amount_due']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= htmlspecialchars($pay['payment_date'] ?? date('Y-m-d')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Amount *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="<?= htmlspecialchars($pay['amount'] ?? '') ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="">Select Method</option>
                        <?php foreach ($methods as $m): ?>
                        <option value="<?= $m ?>" <?= ($pay['payment_method'] ?? '') === $m ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" value="<?= htmlspecialchars($pay['reference_number'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($pay['notes'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $pay ? 'Update' : 'Save' ?></button>
                <a href="payments.php" class="btn btn-secondary">Cancel</a>
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
        $where[] = "(p.payment_number LIKE ? OR inv.invoice_number LIKE ? OR c.customer_name LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $db->fetchColumn("SELECT COUNT(*) FROM payments p LEFT JOIN invoices inv ON p.invoice_id = inv.id LEFT JOIN customers c ON p.customer_id = c.id $whereClause", $params);
    $offset = ($page - 1) * $perPage;
    $payments = $db->fetchAll("SELECT p.*, inv.invoice_number, c.customer_name FROM payments p LEFT JOIN invoices inv ON p.invoice_id = inv.id LEFT JOIN customers c ON p.customer_id = c.id $whereClause ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset", $params);
?>
<div class="page-header">
    <h1 class="page-title">Payments</h1>
    <p class="page-subtitle">Track all incoming payments</p>
    <a href="?action=add" class="btn btn-primary">Record Payment</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>All Payments</h3>
        <div class="search-bar">
            <form method="GET">
                <input type="text" name="search" class="form-control" placeholder="Search payments..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Payment #</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr><td colspan="8" class="text-center">No payments found</td></tr>
                <?php else: ?>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['payment_number']) ?></td>
                    <td><?= htmlspecialchars($p['invoice_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['customer_name'] ?? '-') ?></td>
                    <td><?= formatDate($p['payment_date']) ?></td>
                    <td><?= formatCurrency($p['amount']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($p['payment_method'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars($p['reference_number'] ?? '-') ?></td>
                    <td class="action-bar">
                        <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                        <a href="?delete=1&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this payment?')">Delete</a>
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
