<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invId = (int)($_POST['id'] ?? 0);
    $data = [
        'customer_id' => (int)($_POST['customer_id'] ?? 0),
        'invoice_date' => sanitize($_POST['invoice_date'] ?? date('Y-m-d')),
        'due_date' => sanitize($_POST['due_date'] ?? ''),
        'subtotal' => (float)($_POST['subtotal'] ?? 0),
        'tax_percentage' => (float)($_POST['tax_percentage'] ?? 0),
        'tax_amount' => (float)($_POST['tax_amount'] ?? 0),
        'discount' => (float)($_POST['discount'] ?? 0),
        'total' => (float)($_POST['total'] ?? 0),
        'amount_paid' => (float)($_POST['amount_paid'] ?? 0),
        'amount_due' => (float)($_POST['amount_due'] ?? 0),
        'status' => sanitize($_POST['status'] ?? 'unpaid'),
        'notes' => sanitize($_POST['notes'] ?? '')
    ];

    if (!$data['customer_id']) {
        showError('Please select a customer');
        header('Location: invoices.php?action=add');
        exit;
    }

    $db->beginTransaction();
    try {
        if ($invId) {
            assertOwnership('invoices', 'invoices', $invId, ['created_by']);
            $db->update('invoices', $data, 'id = ?', [$invId]);
            $db->delete('invoice_items', 'invoice_id = ?', [$invId]);
            $db->logActivity(getUserId(), getEmployeeCode(), 'edit', 'invoices', 'Edited invoice #' . $invId);
            showSuccess('Invoice updated successfully');
        } else {
            $data['invoice_number'] = generateCode('INV');
            $data['created_by'] = getUserId();
            $invId = $db->insert('invoices', $data);
            $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'invoices', 'Created invoice #' . $data['invoice_number']);
            showSuccess('Invoice created successfully');
        }

        $descriptions = $_POST['item_description'] ?? [];
        $quantities = $_POST['item_quantity'] ?? [];
        $unitPrices = $_POST['item_unit_price'] ?? [];
        $itemTotals = $_POST['item_total'] ?? [];

        for ($i = 0; $i < count($descriptions); $i++) {
            if (!empty($descriptions[$i])) {
                $db->insert('invoice_items', [
                    'invoice_id' => $invId,
                    'description' => sanitize($descriptions[$i]),
                    'quantity' => (float)($quantities[$i] ?? 1),
                    'unit_price' => (float)($unitPrices[$i] ?? 0),
                    'total' => (float)($itemTotals[$i] ?? 0)
                ]);
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        showError('Error saving invoice: ' . $e->getMessage());
    }
    header('Location: invoices.php');
    exit;
}

if (isset($_GET['delete']) && $id) {
    assertOwnership('invoices', 'invoices', $id, ['created_by']);
    $inv = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$id]);
    if ($inv) {
        $db->beginTransaction();
        $db->delete('invoice_items', 'invoice_id = ?', [$id]);
        $db->delete('invoices', 'id = ?', [$id]);
        $db->commit();
        $db->logActivity(getUserId(), getEmployeeCode(), 'delete', 'invoices', 'Deleted invoice #' . $inv['invoice_number']);
        showSuccess('Invoice deleted successfully');
    }
    header('Location: invoices.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('invoices');

if ($action === 'add' || ($action === 'edit' && $id)) {
    $inv = null;
    if ($action === 'edit') {
        assertOwnership('invoices', 'invoices', $id, ['created_by']);
        $inv = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$id]);
    }
    $items = $inv ? $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_id = ?", [$id]) : [];
    $customers = $db->fetchAll("SELECT id, customer_name, company_name FROM customers WHERE status = 'active' ORDER BY customer_name");
?>
<div class="page-header">
    <h1 class="page-title"><?= $inv ? 'Edit Invoice' : 'Create Invoice' ?></h1>
    <p class="page-subtitle"><?= $inv ? 'Update invoice details' : 'Generate a new invoice' ?></p>
</div>

<div class="card">
    <div class="card-header">
        <h3>Invoice Details</h3>
    </div>
    <div class="card-body">
        <form method="POST" id="invoiceForm">
            <?php if ($inv): ?>
            <input type="hidden" name="id" value="<?= $inv['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Customer *</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($inv['customer_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['company_name'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Invoice Date</label>
                    <input type="date" name="invoice_date" class="form-control" value="<?= htmlspecialchars($inv['invoice_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($inv['due_date'] ?? '') ?>">
                </div>
            </div>

            <h4 class="mt-3">Invoice Items</h4>
            <table class="table" id="invoiceItemsTable">
                <thead>
                    <tr>
                        <th style="width:40%">Description</th>
                        <th style="width:15%">Quantity</th>
                        <th style="width:20%">Unit Price</th>
                        <th style="width:20%">Total</th>
                        <th style="width:5%"></th>
                    </tr>
                </thead>
                <tbody id="invoiceItemsBody">
                    <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><input type="text" name="item_description[]" class="form-control" value="<?= htmlspecialchars($item['description']) ?>"></td>
                        <td><input type="number" name="item_quantity[]" class="form-control item-qty" step="0.01" min="0" value="<?= $item['quantity'] ?>" oninput="calcInvoiceTotal()"></td>
                        <td><input type="number" name="item_unit_price[]" class="form-control item-price" step="0.01" min="0" value="<?= $item['unit_price'] ?>" oninput="calcInvoiceTotal()"></td>
                        <td><input type="number" name="item_total[]" class="form-control item-total" step="0.01" readonly value="<?= $item['total'] ?>"></td>
                        <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();calcInvoiceTotal()">&times;</button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td><input type="text" name="item_description[]" class="form-control" placeholder="Item description"></td>
                        <td><input type="number" name="item_quantity[]" class="form-control item-qty" step="0.01" min="0" value="1" oninput="calcInvoiceTotal()"></td>
                        <td><input type="number" name="item_unit_price[]" class="form-control item-price" step="0.01" min="0" value="0" oninput="calcInvoiceTotal()"></td>
                        <td><input type="number" name="item_total[]" class="form-control item-total" step="0.01" readonly value="0"></td>
                        <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();calcInvoiceTotal()">&times;</button></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-success" onclick="addInvoiceItem()">+ Add Item</button>

            <div class="form-row mt-3">
                <div class="col-md-6"></div>
                <div class="col-md-6">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Subtotal</label>
                            <input type="number" name="subtotal" id="subtotal" class="form-control" step="0.01" readonly value="<?= $inv['subtotal'] ?? 0 ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Tax (%)</label>
                            <input type="number" name="tax_percentage" id="tax_percentage" class="form-control" step="0.01" min="0" value="<?= $inv['tax_percentage'] ?? 0 ?>" oninput="calcInvoiceTotal()">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Tax Amount</label>
                            <input type="number" name="tax_amount" id="tax_amount" class="form-control" step="0.01" readonly value="<?= $inv['tax_amount'] ?? 0 ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Discount</label>
                            <input type="number" name="discount" id="discount" class="form-control" step="0.01" min="0" value="<?= $inv['discount'] ?? 0 ?>" oninput="calcInvoiceTotal()">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Total</label>
                            <input type="number" name="total" id="total" class="form-control" step="0.01" readonly value="<?= $inv['total'] ?? 0 ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Status</label>
                            <select name="status" class="form-select" onchange="togglePaidFields()">
                                <option value="unpaid" <?= ($inv['status'] ?? 'unpaid') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                <option value="paid" <?= ($inv['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="partial" <?= ($inv['status'] ?? '') === 'partial' ? 'selected' : '' ?>>Partial</option>
                                <option value="cancelled" <?= ($inv['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row" id="paidFields">
                        <div class="form-group col-md-6">
                            <label>Amount Paid</label>
                            <input type="number" name="amount_paid" id="amount_paid" class="form-control" step="0.01" min="0" value="<?= $inv['amount_paid'] ?? 0 ?>" oninput="calcInvoiceTotal()">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Amount Due</label>
                            <input type="number" name="amount_due" id="amount_due" class="form-control" step="0.01" readonly value="<?= $inv['amount_due'] ?? 0 ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($inv['notes'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $inv ? 'Update' : 'Save' ?></button>
                <a href="invoices.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script src="../../assets/js/modules.js"></script>

<?php } elseif ($action === 'view' && $id) {
    assertOwnership('invoices', 'invoices', $id, ['created_by']);
    $inv = $db->fetchOne("SELECT i.*, c.customer_name, c.company_name, c.gst_number, c.address, c.city, c.state, c.pincode, c.phone as customer_phone, c.email as customer_email FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE i.id = ?", [$id]);
    if (!$inv) {
        showError('Invoice not found');
        header('Location: invoices.php');
        exit;
    }
    $items = $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_id = ?", [$id]);
?>
<div class="page-header">
    <h1 class="page-title">Invoice <?= htmlspecialchars($inv['invoice_number']) ?></h1>
    <p class="page-subtitle">Invoice details and print view</p>
    <div class="action-bar">
        <a href="?action=edit&id=<?= $inv['id'] ?>" class="btn btn-sm btn-info">Edit</a>
        <button onclick="window.print()" class="btn btn-sm btn-primary">Print</button>
        <a href="invoices.php" class="btn btn-sm btn-secondary">Back</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h4>Bill To:</h4>
                <p><strong><?= htmlspecialchars($inv['customer_name']) ?></strong><br>
                <?= htmlspecialchars($inv['company_name'] ?? '') ?><br>
                <?= nl2br(htmlspecialchars($inv['address'] ?? '')) ?><br>
                <?= htmlspecialchars($inv['city'] ?? '') ?>, <?= htmlspecialchars($inv['state'] ?? '') ?> - <?= htmlspecialchars($inv['pincode'] ?? '') ?><br>
                GST: <?= htmlspecialchars($inv['gst_number'] ?? '-') ?><br>
                Phone: <?= htmlspecialchars($inv['customer_phone'] ?? '-') ?><br>
                Email: <?= htmlspecialchars($inv['customer_email'] ?? '-') ?></p>
            </div>
            <div class="col-md-6 text-right">
                <h4>Invoice Details</h4>
                <p><strong>Invoice #:</strong> <?= htmlspecialchars($inv['invoice_number']) ?><br>
                <strong>Invoice Date:</strong> <?= formatDate($inv['invoice_date']) ?><br>
                <strong>Due Date:</strong> <?= formatDate($inv['due_date']) ?><br>
                <strong>Status:</strong> <?= getStatusBadge($inv['status']) ?></p>
            </div>
        </div>

        <table class="table mt-3">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($item['description']) ?></td>
                    <td class="text-right"><?= $item['quantity'] ?></td>
                    <td class="text-right"><?= formatCurrency($item['unit_price']) ?></td>
                    <td class="text-right"><?= formatCurrency($item['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row">
            <div class="col-md-6"></div>
            <div class="col-md-6">
                <table class="table">
                    <tr><td><strong>Subtotal</strong></td><td class="text-right"><?= formatCurrency($inv['subtotal']) ?></td></tr>
                    <tr><td><strong>Tax (<?= $inv['tax_percentage'] ?>%)</strong></td><td class="text-right"><?= formatCurrency($inv['tax_amount']) ?></td></tr>
                    <tr><td><strong>Discount</strong></td><td class="text-right"><?= formatCurrency($inv['discount']) ?></td></tr>
                    <tr><td><strong>Total</strong></td><td class="text-right"><strong><?= formatCurrency($inv['total']) ?></strong></td></tr>
                    <tr><td><strong>Paid</strong></td><td class="text-right"><?= formatCurrency($inv['amount_paid']) ?></td></tr>
                    <tr><td><strong>Due</strong></td><td class="text-right"><strong><?= formatCurrency($inv['amount_due']) ?></strong></td></tr>
                </table>
            </div>
        </div>

        <?php if ($inv['notes']): ?>
        <div class="mt-3">
            <h5>Notes:</h5>
            <p><?= nl2br(htmlspecialchars($inv['notes'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php } else {
    $search = sanitize($_GET['search'] ?? '');
    $statusFilter = sanitize($_GET['status'] ?? '');
    $page = (int)($_GET['page'] ?? 1);
    $perPage = 20;

    $where = [];
    $params = [];
    if ($search) {
        $where[] = "(i.invoice_number LIKE ? OR c.customer_name LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%"]);
    }
    if ($statusFilter) {
        $where[] = "i.status = ?";
        $params[] = $statusFilter;
    }
    list($scopeWhere, $scopeParams) = applyScopeFilter('invoices', ['created_by']);
    if ($scopeWhere) {
        $where[] = $scopeWhere;
        $params = array_merge($params, $scopeParams);
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $db->fetchColumn("SELECT COUNT(*) FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id $whereClause", $params);
    $offset = ($page - 1) * $perPage;
    $invoices = $db->fetchAll("SELECT i.*, c.customer_name, c.company_name FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id $whereClause ORDER BY i.created_at DESC LIMIT $perPage OFFSET $offset", $params);
?>
<div class="page-header">
    <h1 class="page-title">Invoices</h1>
    <p class="page-subtitle">Manage invoices</p>
    <a href="?action=add" class="btn btn-primary">Create Invoice</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>All Invoices</h3>
        <div class="filter-bar">
            <form method="GET" class="form-row">
                <div class="search-bar">
                    <input type="text" name="search" class="form-control" placeholder="Search invoices..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <?php foreach (['paid', 'unpaid', 'partial', 'cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="9" class="text-center">No invoices found</td></tr>
                <?php else: ?>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td><?= htmlspecialchars($inv['customer_name'] ?? '-') ?></td>
                    <td><?= formatDate($inv['invoice_date']) ?></td>
                    <td><?= formatDate($inv['due_date']) ?></td>
                    <td><?= formatCurrency($inv['total']) ?></td>
                    <td><?= formatCurrency($inv['amount_paid']) ?></td>
                    <td><?= formatCurrency($inv['amount_due']) ?></td>
                    <td><?= getStatusBadge($inv['status']) ?></td>
                    <td class="action-bar">
                        <a href="?action=view&id=<?= $inv['id'] ?>" class="btn btn-sm btn-primary">View</a>
                        <a href="?action=edit&id=<?= $inv['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                        <a href="?delete=1&id=<?= $inv['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this invoice?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginate($total, $page, $perPage) ?>

<script src="../../assets/js/modules.js"></script>

<?php } ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
