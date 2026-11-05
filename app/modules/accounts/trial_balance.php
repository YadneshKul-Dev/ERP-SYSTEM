<?php
require_once __DIR__ . '/../../init.php';
requirePermission('trial_balance');

$db = Database::getInstance();

require_once __DIR__ . '/../../includes/header.php';

$asOf = $_GET['as_of'] ?? date('Y-m-d');
$typeFilter = $_GET['type'] ?? '';

$whereType = "";
$params = [$asOf];
if ($typeFilter) {
    $whereType = " AND coa.account_type = ?";
    $params[] = $typeFilter;
}

$data = $db->fetchAll("
    SELECT coa.account_code, coa.account_name, coa.account_type,
           COALESCE(SUM(jei.debit_amount), 0) as total_debit,
           COALESCE(SUM(jei.credit_amount), 0) as total_credit
    FROM chart_of_accounts coa
    LEFT JOIN journal_entry_items jei ON jei.account_code = coa.account_code
    LEFT JOIN journal_entries je ON je.id = jei.journal_entry_id AND je.status = 'posted' AND je.entry_date <= ?
    WHERE coa.is_active = 1 $whereType
    GROUP BY coa.id
    ORDER BY coa.account_code
", $params);

$accountTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];
$grandDr = 0; $grandCr = 0;
?>

<div class="page-header">
    <h1 class="page-title">Trial Balance</h1>
    <p class="page-subtitle">Verify that total debits equal total credits as of a given date</p>
</div>

<div class="action-bar">
    <form method="GET" class="search-bar">
        <label style="display:flex;align-items:center;gap:8px">
            <strong>As of:</strong>
            <input type="date" name="as_of" class="form-control" value="<?= htmlspecialchars($asOf) ?>" style="max-width:180px">
        </label>
        <select name="type" class="form-select" style="max-width:180px" onchange="this.form.submit()">
            <option value="">All Account Types</option>
            <?php foreach ($accountTypes as $t): ?>
            <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Generate</button>
        <button type="button" class="btn btn-success btn-sm" onclick="window.print()">Print</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3>Trial Balance as of <?= htmlspecialchars($asOf) ?></h3>
        <span><?= count($data) ?> accounts</span>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr><th>Code</th><th>Account Name</th><th>Type</th><th>Debit (₹)</th><th>Credit (₹)</th></tr>
            </thead>
            <tbody>
                <?php foreach ($data as $d):
                    $dr = (float)$d['total_debit'];
                    $cr = (float)$d['total_credit'];
                    $bal = $dr - $cr;
                    $grandDr += $dr;
                    $grandCr += $cr;
                ?>
                <tr>
                    <td><?= htmlspecialchars($d['account_code']) ?></td>
                    <td><?= htmlspecialchars($d['account_name']) ?></td>
                    <td><span class="badge bg-info"><?= htmlspecialchars($d['account_type']) ?></span></td>
                    <td><?= $bal > 0 ? formatCurrency($bal) : '-' ?></td>
                    <td><?= $bal < 0 ? formatCurrency(abs($bal)) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($data)): ?>
                <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-light)">No accounts found. Set up Chart of Accounts first.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;background:var(--light)">
                    <td colspan="3"><strong>Totals</strong></td>
                    <td><strong><?= formatCurrency($grandDr) ?></strong></td>
                    <td><strong><?= formatCurrency($grandCr) ?></strong></td>
                </tr>
                <tr style="font-weight:700;background:<?= $grandDr === $grandCr ? 'var(--success)' : 'var(--danger)' ?>;color:white">
                    <td colspan="5" style="text-align:center">
                        <?php if ($grandDr === $grandCr): ?>
                        ✓ BALANCED — Total Debits = Total Credits
                        <?php else: ?>
                        ✗ NOT BALANCED — Difference: <?= formatCurrency(abs($grandDr - $grandCr)) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
