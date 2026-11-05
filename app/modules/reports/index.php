<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php requirePermission('reports'); ?>

<?php
$db = Database::getInstance();

// Date range filter
$dateFrom = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$dateTo = isset($_GET['to']) ? sanitize($_GET['to']) : date('Y-m-d');

// Summary stats based on permissions
$stats = [];

if (hasPermission('employees', 'view')) {
    $stats['total_employees'] = $db->fetchColumn("SELECT COUNT(*) FROM employees WHERE is_active = 1");
    $stats['total_departments'] = $db->fetchColumn("SELECT COUNT(*) FROM departments WHERE is_active = 1");
}
if (hasPermission('hr', 'view')) {
    $stats['present_today'] = $db->fetchColumn("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'present'", [date('Y-m-d')]);
    $stats['pending_leaves'] = $db->fetchColumn("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
}
if (hasPermission('customers', 'view')) {
    $stats['total_customers'] = $db->fetchColumn("SELECT COUNT(*) FROM customers WHERE status = 'active'");
}
if (hasPermission('leads', 'view')) {
    $stats['total_leads'] = $db->fetchColumn("SELECT COUNT(*) FROM leads");
}
if (hasPermission('tickets', 'view')) {
    $stats['open_tickets'] = $db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE status = 'open'");
}
if (hasPermission('invoices', 'view')) {
    $stats['total_invoices'] = $db->fetchColumn("SELECT COUNT(*) FROM invoices WHERE status = 'paid'");
    $stats['total_revenue'] = $db->fetchColumn("SELECT SUM(total) FROM invoices WHERE status = 'paid'");
}
?>

<div class="page-header">
    <h1 class="page-title">Reports Dashboard</h1>
    <p class="page-subtitle">System-wide analytics and summary reports</p>
</div>

<div class="filter-bar mb-3">
    <form method="GET" class="form-row">
        <div class="form-group">
            <label for="from" class="form-label">From Date</label>
            <input type="date" id="from" name="from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label for="to" class="form-label">To Date</label>
            <input type="date" id="to" name="to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div class="form-actions" style="align-self:flex-end;">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="?" class="btn btn-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<div class="stats-grid">
    <?php if (isset($stats['total_employees'])): ?>
    <div class="stat-card">
        <div class="stat-icon bg-primary">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_employees'] ?></span>
            <span class="stat-label">Total Employees</span>
        </div>
    </div>
    <?php endif; ?>
    <?php if (isset($stats['total_customers'])): ?>
    <div class="stat-card">
        <div class="stat-icon bg-success">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_customers'] ?></span>
            <span class="stat-label">Total Customers</span>
        </div>
    </div>
    <?php endif; ?>
    <?php if (isset($stats['total_invoices'])): ?>
    <div class="stat-card">
        <div class="stat-icon bg-info">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_invoices'] ?></span>
            <span class="stat-label">Paid Invoices</span>
        </div>
    </div>
    <?php endif; ?>
    <?php if (isset($stats['total_revenue'])): ?>
    <div class="stat-card">
        <div class="stat-icon bg-success">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= formatCurrency($stats['total_revenue'] ?? 0) ?></span>
            <span class="stat-label">Total Revenue</span>
        </div>
    </div>
    <?php endif; ?>
    <?php if (isset($stats['open_tickets'])): ?>
    <div class="stat-card">
        <div class="stat-icon bg-danger">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['open_tickets'] ?></span>
            <span class="stat-label">Open Tickets</span>
        </div>
    </div>
    <?php endif; ?>
    <?php if (isset($stats['pending_leaves'])): ?>
    <div class="stat-card">
        <div class="stat-icon bg-warning">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['pending_leaves'] ?></span>
            <span class="stat-label">Pending Leaves</span>
        </div>
    </div>
    <?php endif; ?>
    <?php if (isset($stats['total_leads'])): ?>
    <div class="stat-card">
        <div class="stat-icon bg-secondary">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_leads'] ?></span>
            <span class="stat-label">Total Leads</span>
        </div>
    </div>
    <?php endif; ?>
    <?php if (isset($stats['total_departments'])): ?>
    <div class="stat-card">
        <div class="stat-icon bg-primary">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_departments'] ?></span>
            <span class="stat-label">Departments</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3>Employee Reports</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="?report=employees" class="action-btn">Employee List Report</a>
                    <a href="?report=attendance" class="action-btn">Attendance Report</a>
                    <a href="?report=leaves" class="action-btn">Leave Report</a>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                <h3>CRM Reports</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="?report=customers" class="action-btn">Customer Report</a>
                    <a href="?report=leads" class="action-btn">Lead Report</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3>Financial Reports</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="?report=invoices" class="action-btn">Invoice Report</a>
                    <a href="?report=payments" class="action-btn">Payment Report</a>
                    <a href="?report=revenue" class="action-btn">Revenue Summary</a>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                <h3>Export & Actions</h3>
            </div>
            <div class="card-body">
                <div class="action-bar">
                    <a href="activity.php" class="btn btn-info btn-sm">View Activity Log</a>
                    <a href="?export=csv" class="btn btn-success btn-sm">Export CSV</a>
                    <button onclick="window.print()" class="btn btn-secondary btn-sm">Print</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['export']) && $_GET['export'] === 'csv'): ?>
<?php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=report_'.date('Y-m-d').'.csv');
$output = fopen('php://output', 'w');
fputcsv($output, ['Report', 'Value', 'Date']);
foreach ($stats as $key => $value) {
    if (is_numeric($value) || is_string($value)) {
        fputcsv($output, [$key, $value, date('Y-m-d')]);
    }
}
fclose($output);
exit;
?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
