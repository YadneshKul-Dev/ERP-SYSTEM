<?php
require_once __DIR__ . '/../../init.php';
requirePermission('bank_reconciliation');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && hasPermission('bank_reconciliation', 'create')) {
        $db->insert('bank_reconciliation', [
            'account_code' => sanitize($_POST['account_code']),
            'statement_date' => sanitize($_POST['statement_date']),
            'ending_balance' => (float)$_POST['ending_balance'],
            'reconciled_balance' => (float)$_POST['reconciled_balance'],
            'difference' => (float)$_POST['ending_balance'] - (float)$_POST['reconciled_balance'],
            'status' => 'pending',
            'notes' => sanitize($_POST['notes'] ?? '')
        ]);
        showSuccess('Bank reconciliation record created');
    } elseif ($action === 'reconcile' && hasPermission('bank_reconciliation', 'edit')) {
        $id = (int)$_POST['id'];
        $db->update('bank_reconciliation', [
            'reconciled_balance' => (float)$_POST['reconciled_balance'],
            'difference' => (float)$_POST['ending_balance'] - (float)$_POST['reconciled_balance'],
            'status' => 'reconciled',
            'reconciled_at' => date('Y-m-d H:i:s'),
            'reconciled_by' => getUserId(),
            'notes' => sanitize($_POST['notes'] ?? '')
        ], 'id = ?', [$id]);
        showSuccess('Bank reconciliation completed');
    }
    header('Location: /modules/accounts/bank_reconciliation.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$recons = $db->fetchAll("SELECT * FROM bank_reconciliation ORDER BY statement_date DESC LIMIT 50");
$coa = $db->fetchAll("SELECT * FROM chart_of_accounts WHERE account_type = 'Asset' AND account_name LIKE '%Bank%' AND is_active=1 ORDER BY account_code");
?>

<div class="page-header">
    <h1 class="page-title">Bank Reconciliation</h1>
    <p class="page-subtitle">Match bank statement balances with book balances</p>
</div>

<div class="action-bar">
    <?php if (hasPermission('bank_reconciliation', 'create')): ?>
    <button class="btn btn-success" onclick="openModal('addReconModal')">+ New Reconciliation</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h3>Reconciliation Records</h3></div>
    <div class="card-body p-0">
        <table class="table">
            <thead><tr><th>Account</th><th>Statement Date</th><th>Ending Balance</th><th>Book Balance</th><th>Difference</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($recons as $r):
                    $acct = $db->fetchOne("SELECT account_name FROM chart_of_accounts WHERE account_code = ?", [$r['account_code']]);
                ?>
                <tr>
                    <td><?= htmlspecialchars($r['account_code']) ?> - <?= htmlspecialchars($acct['account_name'] ?? '') ?></td>
                    <td><?= formatDate($r['statement_date']) ?></td>
                    <td><?= formatCurrency($r['ending_balance']) ?></td>
                    <td><?= formatCurrency($r['reconciled_balance']) ?></td>
                    <td><?= formatCurrency($r['difference']) ?></td>
                    <td><?= getStatusBadge($r['status']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending' && hasPermission('bank_reconciliation', 'edit')): ?>
                        <a href="javascript:void(0)" class="btn btn-sm btn-success" onclick="reconcile(<?= $r['id'] ?>, '<?= $r['ending_balance'] ?>', '<?= $r['reconciled_balance'] ?>')">Reconcile</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recons)): ?>
                <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-light)">No reconciliation records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Reconciliation Modal -->
<div class="modal-overlay" id="addReconModal">
    <div class="modal">
        <div class="modal-header"><h3>New Bank Reconciliation</h3><button class="modal-close" onclick="closeModal('addReconModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Bank Account *</label>
                        <select name="account_code" class="form-select" required>
                            <option value="">Select Account</option>
                            <?php foreach ($coa as $a): ?>
                            <option value="<?= htmlspecialchars($a['account_code']) ?>"><?= htmlspecialchars($a['account_code'] . ' - ' . $a['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Statement Date *</label>
                        <input type="date" name="statement_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Ending Balance (per Bank) *</label>
                        <input type="number" step="0.01" name="ending_balance" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Book Balance (per Ledger) *</label>
                        <input type="number" step="0.01" name="reconciled_balance" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addReconModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Reconcile Modal -->
<div class="modal-overlay" id="reconcileModal">
    <div class="modal">
        <div class="modal-header"><h3>Complete Reconciliation</h3><button class="modal-close" onclick="closeModal('reconcileModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="reconcile">
                <input type="hidden" name="id" id="recon_id">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Ending Balance (Bank)</label>
                        <input type="text" id="recon_ending" class="form-control" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Book Balance *</label>
                        <input type="number" step="0.01" name="reconciled_balance" id="recon_book" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reconcileModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Complete Reconciliation</button>
            </div>
        </form>
    </div>
</div>

<script>
function reconcile(id, ending, book) {
    document.getElementById('recon_id').value = id;
    document.getElementById('recon_ending').value = ending;
    document.getElementById('recon_book').value = book;
    openModal('reconcileModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
