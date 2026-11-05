<?php
require_once __DIR__ . '/../../init.php';
requirePermission('ledger');

$db = Database::getInstance();

require_once __DIR__ . '/../../includes/header.php';

$accountCode = $_GET['account_code'] ?? '';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$coa = $db->fetchAll("SELECT * FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code");

$entries = [];
$opening = 0;
$closing = 0;

if ($accountCode) {
    $opening = (float)($db->fetchColumn("
        SELECT SUM(jei.debit_amount) - SUM(jei.credit_amount)
        FROM journal_entry_items jei
        JOIN journal_entries je ON je.id = jei.journal_entry_id
        WHERE jei.account_code = ? AND je.status = 'posted' AND je.entry_date < ?
    ", [$accountCode, $from]) ?: 0);

    $entries = $db->fetchAll("
        SELECT je.entry_number, je.entry_date, je.description AS je_desc,
               jei.description AS item_desc, jei.debit_amount, jei.credit_amount
        FROM journal_entry_items jei
        JOIN journal_entries je ON je.id = jei.journal_entry_id
        WHERE jei.account_code = ? AND je.status = 'posted'
          AND je.entry_date BETWEEN ? AND ?
        ORDER BY je.entry_date ASC, je.id ASC
    ", [$accountCode, $from, $to]);

    $closing = $opening;
    foreach ($entries as $e) {
        $closing += (float)$e['debit_amount'] - (float)$e['credit_amount'];
    }
}

$acct = $accountCode ? $db->fetchOne("SELECT * FROM chart_of_accounts WHERE account_code = ?", [$accountCode]) : null;
?>

<div class="page-header">
    <h1 class="page-title">General Ledger</h1>
    <p class="page-subtitle">View detailed ledger entries for each account with running balance</p>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="form-row">
            <div class="form-group col-md-4">
                <label>Account *</label>
                <select name="account_code" class="form-select" required>
                    <option value="">Select Account</option>
                    <?php foreach ($coa as $a): ?>
                    <option value="<?= htmlspecialchars($a['account_code']) ?>" <?= $accountCode === $a['account_code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['account_code'] . ' - ' . $a['account_name'] . ' [' . $a['account_type'] . ']') ?>
                    </option>
                    <?php endforeach; ?>
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
            <div class="form-group col-md-2" style="display:flex;align-items:end">
                <button type="submit" class="btn btn-primary">View Ledger</button>
            </div>
        </form>
    </div>
</div>

<?php if ($accountCode && $acct): ?>
<div class="card mt-3">
    <div class="card-header">
        <h3><?= htmlspecialchars($acct['account_code'] . ' - ' . $acct['account_name']) ?></h3>
        <span class="badge bg-info"><?= htmlspecialchars($acct['account_type']) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th><th>Entry #</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background:var(--light);font-weight:600">
                    <td colspan="3">Opening Balance (as of <?= htmlspecialchars($from) ?>)</td>
                    <td></td><td></td>
                    <td><?= formatCurrency(abs($opening)) ?> <?= $opening >= 0 ? 'Dr' : 'Cr' ?></td>
                </tr>
                <?php $bal = $opening; ?>
                <?php foreach ($entries as $e):
                    $bal += (float)$e['debit_amount'] - (float)$e['credit_amount'];
                ?>
                <tr>
                    <td><?= formatDate($e['entry_date']) ?></td>
                    <td><?= htmlspecialchars($e['entry_number']) ?></td>
                    <td><?= htmlspecialchars($e['item_desc'] ?: $e['je_desc']) ?></td>
                    <td><?= (float)$e['debit_amount'] > 0 ? formatCurrency($e['debit_amount']) : '-' ?></td>
                    <td><?= (float)$e['credit_amount'] > 0 ? formatCurrency($e['credit_amount']) : '-' ?></td>
                    <td><strong><?= formatCurrency(abs($bal)) ?></strong> <?= $bal >= 0 ? 'Dr' : 'Cr' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-light)">No transactions in this period.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;background:var(--light)">
                    <td colspan="3">Closing Balance (as of <?= htmlspecialchars($to) ?>)</td>
                    <td><?php
                        $tdr = array_sum(array_map(function($e){return (float)$e['debit_amount'];}, $entries));
                        $tcr = array_sum(array_map(function($e){return (float)$e['credit_amount'];}, $entries));
                        echo formatCurrency($tdr);
                    ?></td>
                    <td><?= formatCurrency($tcr) ?></td>
                    <td><?= formatCurrency(abs($closing)) ?> <?= $closing >= 0 ? 'Dr' : 'Cr' ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php elseif ($accountCode): ?>
<div class="alert alert-warning mt-3">Account not found.</div>
<?php else: ?>
<div class="card mt-3">
    <div class="card-body" style="text-align:center;padding:48px;color:var(--text-light)">
        <p>Select an account and date range, then click "View Ledger" to see transactions.</p>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
