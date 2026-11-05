<?php
require_once __DIR__ . '/../../init.php';
requirePermission('gst');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('gst', 'file')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'file_return') {
        $db->insert('gst_returns', [
            'return_period' => sanitize($_POST['return_period']),
            'filing_date' => date('Y-m-d'),
            'total_sales' => (float)$_POST['total_sales'],
            'total_purchases' => (float)$_POST['total_purchases'],
            'gst_collected' => (float)$_POST['gst_collected'],
            'gst_paid' => (float)$_POST['gst_paid'],
            'net_gst_payable' => (float)$_POST['gst_collected'] - (float)$_POST['gst_paid'],
            'status' => 'filed',
            'filed_by' => getUserId()
        ]);
        showSuccess('GST return filed successfully');
        header('Location: /modules/accounts/gst.php');
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';

// Create GST returns table if not exists (migration)
$pdo = $db->getPdo();
$pdo->exec("CREATE TABLE IF NOT EXISTS gst_returns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_period VARCHAR(10) NOT NULL,
    filing_date DATE,
    total_sales DECIMAL(14,2) DEFAULT 0,
    total_purchases DECIMAL(14,2) DEFAULT 0,
    gst_collected DECIMAL(14,2) DEFAULT 0,
    gst_paid DECIMAL(14,2) DEFAULT 0,
    net_gst_payable DECIMAL(14,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'draft',
    filed_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$returns = $db->fetchAll("SELECT * FROM gst_returns ORDER BY return_period DESC LIMIT 20");

// Get invoice-wise GST summary
$gstInvoices = $db->fetchAll("
    SELECT i.invoice_number, i.invoice_date, c.customer_name, c.gst_number,
           i.total, i.tax_amount as gst_amount, i.status
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    ORDER BY i.invoice_date DESC LIMIT 50
");

$totalGstCollected = $db->fetchColumn("SELECT COALESCE(SUM(tax_amount), 0) FROM invoices WHERE status IN ('paid', 'unpaid')") ?: 0;
$totalSales = $db->fetchColumn("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE status IN ('paid', 'unpaid')") ?: 0;
?>

<div class="page-header">
    <h1 class="page-title">GST Compliance</h1>
    <p class="page-subtitle">Manage GST returns, invoice-wise tax summary, and filing</p>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
    <div class="stat-card">
        <div class="stat-icon bg-info"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
        <div class="stat-info"><span class="stat-value"><?= formatCurrency($totalSales) ?></span><span class="stat-label">Total Sales (GST)</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="stat-info"><span class="stat-value"><?= formatCurrency($totalGstCollected) ?></span><span class="stat-label">Total GST Collected</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="stat-info"><span class="stat-value"><?= count($returns) ?></span><span class="stat-label">Returns Filed</span></div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h3>Invoice-wise GST Summary</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>GSTIN</th><th>Total</th><th>GST</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($gstInvoices as $inv): ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
                            <td><?= formatDate($inv['invoice_date']) ?></td>
                            <td><?= htmlspecialchars($inv['customer_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($inv['gst_number'] ?? '-') ?></td>
                            <td><?= formatCurrency($inv['total']) ?></td>
                            <td><?= formatCurrency($inv['gst_amount']) ?></td>
                            <td><?= getStatusBadge($inv['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($gstInvoices)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-light)">No invoices found for GST reporting.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3>GST Returns</h3><?php if (hasPermission('gst', 'file')): ?><button class="btn btn-sm btn-success" onclick="openModal('fileReturnModal')">+ File Return</button><?php endif; ?></div>
            <div class="card-body p-0">
                <div style="max-height:400px;overflow-y:auto">
                    <table class="table">
                        <thead><tr><th>Period</th><th>Filed</th><th>Net GST</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($returns as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['return_period']) ?></td>
                                <td><?= formatDate($r['filing_date']) ?></td>
                                <td><?= formatCurrency($r['net_gst_payable']) ?></td>
                                <td><?= getStatusBadge($r['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($returns)): ?>
                            <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-light)">No returns filed yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- File GST Return Modal -->
<div class="modal-overlay" id="fileReturnModal">
    <div class="modal">
        <div class="modal-header"><h3>File GST Return</h3><button class="modal-close" onclick="closeModal('fileReturnModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="file_return">
                <div class="form-group">
                    <label>Return Period (MM/YYYY) *</label>
                    <input type="text" name="return_period" class="form-control" placeholder="e.g., 06/2026" required value="<?= date('m/Y') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Total Sales (₹) *</label>
                        <input type="number" step="0.01" name="total_sales" class="form-control" required value="<?= $totalSales ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Total Purchases (₹) *</label>
                        <input type="number" step="0.01" name="total_purchases" class="form-control" required value="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>GST Collected (₹) *</label>
                        <input type="number" step="0.01" name="gst_collected" class="form-control" required value="<?= $totalGstCollected ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>GST Paid (ITC) (₹)</label>
                        <input type="number" step="0.01" name="gst_paid" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('fileReturnModal')">Cancel</button>
                <button type="submit" class="btn btn-success">File Return</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
