<?php
require_once __DIR__ . '/../../init.php';
requirePermission('financial_reports');

$db = Database::getInstance();

require_once __DIR__ . '/../../includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$reportType = $_GET['report'] ?? 'revenue';

// Revenue data
$revenueData = $db->fetchAll("
    SELECT DATE(invoice_date) as inv_date,
           COUNT(*) as invoice_count,
           SUM(total) as total_revenue,
           SUM(tax_amount) as total_tax,
           SUM(amount_due) as total_due,
           SUM(amount_paid) as total_paid
    FROM invoices
    WHERE invoice_date BETWEEN ? AND ?
    GROUP BY DATE(invoice_date)
    ORDER BY inv_date
", [$from, $to]);

$totals = $db->fetchOne("
    SELECT COUNT(*) as inv_count,
           COALESCE(SUM(total),0) as total_rev,
           COALESCE(SUM(tax_amount),0) as total_tax,
           COALESCE(SUM(amount_paid),0) as total_paid,
           COALESCE(SUM(amount_due),0) as total_due
    FROM invoices
    WHERE invoice_date BETWEEN ? AND ?
", [$from, $to]);

// Payment data
$paymentData = $db->fetchAll("
    SELECT DATE(payment_date) as pay_date,
           COUNT(*) as pay_count,
           SUM(amount) as total_amount,
           payment_method
    FROM payments
    WHERE payment_date BETWEEN ? AND ?
    GROUP BY DATE(payment_date), payment_method
    ORDER BY pay_date
", [$from, $to]);

$payTotals = $db->fetchOne("
    SELECT COUNT(*) as pay_count, COALESCE(SUM(amount),0) as total_pay
    FROM payments WHERE payment_date BETWEEN ? AND ?
", [$from, $to]);

// Budget vs Actual
$budgets = $db->fetchAll("
    SELECT budget_name, fiscal_year, department,
           total_amount, amount_used,
           (total_amount - amount_used) as remaining,
           CASE WHEN total_amount > 0 THEN ROUND((amount_used/total_amount)*100,1) ELSE 0 END as pct_used
    FROM budgets WHERE status = 'active'
    ORDER BY fiscal_year DESC, budget_name
");

// P&L Summary (from journal entries)
$plData = $db->fetchAll("
    SELECT coa.account_type, coa.account_name,
           COALESCE(SUM(jei.debit_amount),0) as dr,
           COALESCE(SUM(jei.credit_amount),0) as cr
    FROM chart_of_accounts coa
    LEFT JOIN journal_entry_items jei ON jei.account_code = coa.account_code
    LEFT JOIN journal_entries je ON je.id = jei.journal_entry_id AND je.status = 'posted' AND je.entry_date BETWEEN ? AND ?
    WHERE coa.account_type IN ('Revenue', 'Expense')
    GROUP BY coa.id
    ORDER BY coa.account_type, coa.account_code
", [$from, $to]);
?>

<div class="page-header">
    <h1 class="page-title">Financial Reports</h1>
    <p class="page-subtitle">Revenue analysis, payment summaries, budget vs actual, and P&L</p>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="form-row">
            <div class="form-group col-md-3">
                <label>Report Type</label>
                <select name="report" class="form-select" onchange="this.form.submit()">
                    <option value="revenue" <?= $reportType === 'revenue' ? 'selected' : '' ?>>Revenue Summary</option>
                    <option value="payments" <?= $reportType === 'payments' ? 'selected' : '' ?>>Payment Summary</option>
                    <option value="budget" <?= $reportType === 'budget' ? 'selected' : '' ?>>Budget vs Actual</option>
                    <option value="pl" <?= $reportType === 'pl' ? 'selected' : '' ?>>Profit & Loss</option>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label>From Date</label>
                <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div class="form-group col-md-3">
                <label>To Date</label>
                <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
            </div>
            <div class="form-group col-md-3" style="display:flex;align-items:end">
                <button type="submit" class="btn btn-primary">Generate</button>
                <button type="button" class="btn btn-success" style="margin-left:8px" onclick="window.print()">Print</button>
            </div>
        </form>
    </div>
</div>

<?php if ($reportType === 'revenue'): ?>
<div class="card mt-3">
    <div class="card-header"><h3>Revenue Summary (<?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?>)</h3></div>
    <div class="card-body">
        <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px">
            <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= formatCurrency($totals['total_rev']) ?></span><span class="stat-label">Total Revenue</span></div></div>
            <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= formatCurrency($totals['total_paid']) ?></span><span class="stat-label">Collected</span></div></div>
            <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= formatCurrency($totals['total_due']) ?></span><span class="stat-label">Outstanding</span></div></div>
            <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totals['inv_count'] ?></span><span class="stat-label">Invoices</span></div></div>
            <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= formatCurrency($totals['total_tax']) ?></span><span class="stat-label">Tax</span></div></div>
        </div>
        <table class="table">
            <thead><tr><th>Date</th><th>Invoices</th><th>Revenue</th><th>Tax</th><th>Paid</th><th>Due</th></tr></thead>
            <tbody>
                <?php foreach ($revenueData as $r): ?>
                <tr>
                    <td><?= formatDate($r['inv_date']) ?></td>
                    <td><?= $r['invoice_count'] ?></td>
                    <td><?= formatCurrency($r['total_revenue']) ?></td>
                    <td><?= formatCurrency($r['total_tax']) ?></td>
                    <td><?= formatCurrency($r['total_paid']) ?></td>
                    <td><?= formatCurrency($r['total_due']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($revenueData)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-light)">No invoices in this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'payments'): ?>
<div class="card mt-3">
    <div class="card-header"><h3>Payment Summary (<?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?>)</h3></div>
    <div class="card-body">
        <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:16px">
            <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= formatCurrency($payTotals['total_pay']) ?></span><span class="stat-label">Total Collections</span></div></div>
            <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $payTotals['pay_count'] ?></span><span class="stat-label">Payments Received</span></div></div>
        </div>
        <table class="table">
            <thead><tr><th>Date</th><th>Method</th><th>Count</th><th>Amount</th></tr></thead>
            <tbody>
                <?php foreach ($paymentData as $p): ?>
                <tr>
                    <td><?= formatDate($p['pay_date']) ?></td>
                    <td><?= htmlspecialchars($p['payment_method'] ?? 'N/A') ?></td>
                    <td><?= $p['pay_count'] ?></td>
                    <td><?= formatCurrency($p['total_amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($paymentData)): ?>
                <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-light)">No payments in this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'budget'): ?>
<div class="card mt-3">
    <div class="card-header"><h3>Budget vs Actual</h3></div>
    <div class="card-body p-0">
        <table class="table">
            <thead><tr><th>Budget</th><th>FY</th><th>Department</th><th>Allocated</th><th>Used</th><th>Remaining</th><th>% Used</th></tr></thead>
            <tbody>
                <?php foreach ($budgets as $b): ?>
                <?php $pctColor = $b['pct_used'] > 90 ? 'danger' : ($b['pct_used'] > 70 ? 'warning' : 'success'); ?>
                <tr>
                    <td><?= htmlspecialchars($b['budget_name']) ?></td>
                    <td><?= htmlspecialchars($b['fiscal_year']) ?></td>
                    <td><?= htmlspecialchars($b['department'] ?: 'Company-wide') ?></td>
                    <td><?= formatCurrency($b['total_amount']) ?></td>
                    <td><?= formatCurrency($b['amount_used']) ?></td>
                    <td><?= formatCurrency($b['remaining']) ?></td>
                    <td>
                        <span class="badge bg-<?= $pctColor ?>"><?= $b['pct_used'] ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($budgets)): ?>
                <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-light)">No active budgets found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'pl'): ?>
<div class="card mt-3">
    <div class="card-header"><h3>Profit & Loss Statement (<?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?>)</h3></div>
    <div class="card-body p-0">
        <table class="table">
            <thead><tr><th>Account</th><th>Type</th><th>Amount</th></tr></thead>
            <tbody>
                <?php
                $totalRevenue = 0; $totalExpenses = 0;
                foreach ($plData as $p):
                    $bal = (float)$p['cr'] - (float)$p['dr'];
                    if ($p['account_type'] === 'Revenue') $totalRevenue += $bal;
                    elseif ($p['account_type'] === 'Expense') $totalExpenses += abs($bal);
                ?>
                <tr>
                    <td><?= htmlspecialchars($p['account_name']) ?></td>
                    <td><span class="badge bg-<?= $p['account_type'] === 'Revenue' ? 'success' : 'danger' ?>"><?= htmlspecialchars($p['account_type']) ?></span></td>
                    <td><?= formatCurrency(abs($bal)) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($plData)): ?>
                <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--text-light)">No journal entries posted in this period. Revenue/Expense data will appear once journal entries are posted.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;background:var(--light)">
                    <td colspan="2">Total Revenue</td>
                    <td><?= formatCurrency($totalRevenue) ?></td>
                </tr>
                <tr style="font-weight:700;background:var(--light)">
                    <td colspan="2">Total Expenses</td>
                    <td><?= formatCurrency($totalExpenses) ?></td>
                </tr>
                <tr style="font-weight:700;background:<?= ($totalRevenue - $totalExpenses) >= 0 ? 'var(--success)' : 'var(--danger)' ?>;color:white">
                    <td colspan="2">NET <?= ($totalRevenue - $totalExpenses) >= 0 ? 'PROFIT' : 'LOSS' ?></td>
                    <td><?= formatCurrency(abs($totalRevenue - $totalExpenses)) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
