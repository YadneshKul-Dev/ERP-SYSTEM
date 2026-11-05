<?php
require_once __DIR__ . '/../../init.php';
requirePermission('journal');

$db = Database::getInstance();
$enc = Encryption::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && hasPermission('journal', 'create')) {
        $entryNo = $enc->generateEmployeeCode('JE');
        $entryId = $db->insert('journal_entries', [
            'entry_number' => $entryNo,
            'entry_date' => sanitize($_POST['entry_date']),
            'description' => sanitize($_POST['description'] ?? ''),
            'reference_type' => sanitize($_POST['reference_type'] ?? ''),
            'reference_id' => $_POST['reference_id'] ? (int)$_POST['reference_id'] : null,
            'status' => 'draft',
            'created_by' => getUserId()
        ]);

        $accounts = $_POST['account_code'] ?? [];
        $debits = $_POST['debit_amount'] ?? [];
        $credits = $_POST['credit_amount'] ?? [];
        $descriptions = $_POST['item_description'] ?? [];

        foreach ($accounts as $i => $code) {
            if (empty($code)) continue;
            $db->insert('journal_entry_items', [
                'journal_entry_id' => $entryId,
                'account_code' => sanitize($code),
                'debit_amount' => (float)($debits[$i] ?? 0),
                'credit_amount' => (float)($credits[$i] ?? 0),
                'description' => sanitize($descriptions[$i] ?? '')
            ]);
        }
        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'journal', "Created journal entry $entryNo");
        showSuccess("Journal entry $entryNo created successfully");
    } elseif ($action === 'edit' && hasPermission('journal', 'edit')) {
        $id = (int)$_POST['id'];
        $db->update('journal_entries', [
            'entry_date' => sanitize($_POST['entry_date']),
            'description' => sanitize($_POST['description'] ?? ''),
            'reference_type' => sanitize($_POST['reference_type'] ?? ''),
            'reference_id' => $_POST['reference_id'] ? (int)$_POST['reference_id'] : null,
        ], 'id = ?', [$id]);

        $db->delete('journal_entry_items', 'journal_entry_id = ?', [$id]);
        $accounts = $_POST['account_code'] ?? [];
        $debits = $_POST['debit_amount'] ?? [];
        $credits = $_POST['credit_amount'] ?? [];
        $descriptions = $_POST['item_description'] ?? [];
        foreach ($accounts as $i => $code) {
            if (empty($code)) continue;
            $db->insert('journal_entry_items', [
                'journal_entry_id' => $id,
                'account_code' => sanitize($code),
                'debit_amount' => (float)($debits[$i] ?? 0),
                'credit_amount' => (float)($credits[$i] ?? 0),
                'description' => sanitize($descriptions[$i] ?? '')
            ]);
        }
        $je = $db->fetchOne("SELECT entry_number FROM journal_entries WHERE id = ?", [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'edit', 'journal', "Edited journal entry {$je['entry_number']}");
        showSuccess('Journal entry updated successfully');
    } elseif ($action === 'post') {
        $id = (int)$_POST['id'];
        $db->update('journal_entries', ['status' => 'posted'], 'id = ?', [$id]);
        $je = $db->fetchOne("SELECT entry_number FROM journal_entries WHERE id = ?", [$id]);
        showSuccess("Journal entry {$je['entry_number']} posted successfully");
    } elseif ($action === 'reverse') {
        $id = (int)$_POST['id'];
        $orig = $db->fetchOne("SELECT * FROM journal_entries WHERE id = ?", [$id]);
        if ($orig) {
            $items = $db->fetchAll("SELECT * FROM journal_entry_items WHERE journal_entry_id = ?", [$id]);
            $entryNo = $enc->generateEmployeeCode('JR');
            $newId = $db->insert('journal_entries', [
                'entry_number' => $entryNo,
                'entry_date' => date('Y-m-d'),
                'description' => 'Reversal: ' . $orig['description'],
                'reference_type' => 'reversal',
                'reference_id' => $id,
                'status' => 'posted',
                'created_by' => getUserId()
            ]);
            foreach ($items as $item) {
                $db->insert('journal_entry_items', [
                    'journal_entry_id' => $newId,
                    'account_code' => $item['account_code'],
                    'debit_amount' => $item['credit_amount'],
                    'credit_amount' => $item['debit_amount'],
                    'description' => 'Reversal: ' . ($item['description'] ?? $orig['description'])
                ]);
            }
            showSuccess("Reversal entry $entryNo created and posted");
        }
    } elseif ($action === 'delete' && hasPermission('journal', 'delete')) {
        $id = (int)$_POST['id'];
        $je = $db->fetchOne("SELECT entry_number FROM journal_entries WHERE id = ?", [$id]);
        $db->delete('journal_entry_items', 'journal_entry_id = ?', [$id]);
        $db->delete('journal_entries', 'id = ?', [$id]);
        showSuccess("Journal entry {$je['entry_number']} deleted");
    }
    header('Location: /modules/accounts/journal.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$statusFilter = $_GET['status'] ?? '';
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$search = $_GET['search'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}
if ($fromDate) {
    $where .= " AND entry_date >= ?";
    $params[] = $fromDate;
}
if ($toDate) {
    $where .= " AND entry_date <= ?";
    $params[] = $toDate;
}
if ($search) {
    $where .= " AND (entry_number LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$total = $db->fetchColumn("SELECT COUNT(*) FROM journal_entries $where", $params);
$offset = ($page - 1) * $perPage;
$entries = $db->fetchAll("SELECT * FROM journal_entries $where ORDER BY entry_date DESC, id DESC LIMIT $perPage OFFSET $offset", $params);
$coa = $db->fetchAll("SELECT * FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code");
?>

<div class="page-header">
    <h1 class="page-title">Journal Entries</h1>
    <p class="page-subtitle">Record double-entry journal entries for all financial transactions</p>
</div>

<div class="action-bar">
    <form method="GET" class="search-bar" style="flex:1">
        <input type="text" name="search" class="form-control" placeholder="Search entry # or description..." value="<?= htmlspecialchars($search) ?>">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="posted" <?= $statusFilter === 'posted' ? 'selected' : '' ?>>Posted</option>
        </select>
        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($fromDate) ?>" placeholder="From" style="max-width:160px">
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($toDate) ?>" placeholder="To" style="max-width:160px">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>
    <?php if (hasPermission('journal', 'create')): ?>
    <button class="btn btn-success" onclick="openModal('addJournalModal')">+ New Entry</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h3>Journal Entries</h3><span><?= $total ?> entries</span></div>
    <div class="card-body p-0">
        <table class="table">
            <thead><tr><th>Entry #</th><th>Date</th><th>Description</th><th>Debit Total</th><th>Credit Total</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($entries as $e):
                    $totals = $db->fetchOne("SELECT SUM(debit_amount) as dr, SUM(credit_amount) as cr FROM journal_entry_items WHERE journal_entry_id = ?", [$e['id']]);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($e['entry_number']) ?></strong></td>
                    <td><?= formatDate($e['entry_date']) ?></td>
                    <td><?= htmlspecialchars(substr($e['description']??'',0,60)) ?></td>
                    <td><?= formatCurrency($totals['dr'] ?? 0) ?></td>
                    <td><?= formatCurrency($totals['cr'] ?? 0) ?></td>
                    <td><?= getStatusBadge($e['status']) ?></td>
                    <td>
                        <a href="javascript:void(0)" class="btn btn-sm btn-info" onclick="viewJournal(<?= $e['id'] ?>)">View</a>
                        <?php if ($e['status'] === 'draft'): ?>
                            <?php if (hasPermission('journal', 'edit')): ?>
                            <a href="javascript:void(0)" class="btn btn-sm btn-primary" onclick="editJournal(<?= $e['id'] ?>)">Edit</a>
                            <?php endif; ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Post this entry?')">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="action" value="post">
                                <button class="btn btn-sm btn-success">Post</button>
                            </form>
                            <?php if (hasPermission('journal', 'delete')): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this entry?')">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        <?php elseif ($e['status'] === 'posted'): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Create reversal entry?')">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="action" value="reverse">
                                <button class="btn btn-sm btn-warning">Reverse</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?>
                <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-light)">No journal entries found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginate($total, $page, $perPage) ?>

<!-- View Journal Modal -->
<div class="modal-overlay" id="viewJournalModal">
    <div class="modal" style="max-width:800px">
        <div class="modal-header"><h3>Journal Entry <span id="view_entry_number"></span></h3><button class="modal-close" onclick="closeModal('viewJournalModal')">&times;</button></div>
        <div class="modal-body">
            <div class="info-grid" style="margin-bottom:16px">
                <div class="info-item"><span class="info-label">Date</span><span id="view_entry_date" class="info-value"></span></div>
                <div class="info-item"><span class="info-label">Status</span><span id="view_status" class="info-value"></span></div>
                <div class="info-item"><span class="info-label">Description</span><span id="view_description" class="info-value"></span></div>
                <div class="info-item"><span class="info-label">Reference</span><span id="view_reference" class="info-value"></span></div>
            </div>
            <table class="table">
                <thead><tr><th>Account</th><th>Description</th><th>Debit</th><th>Credit</th></tr></thead>
                <tbody id="view_items"></tbody>
                <tfoot id="view_totals" style="font-weight:700"></tfoot>
            </table>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('viewJournalModal')">Close</button></div>
    </div>
</div>

<!-- Add Journal Modal -->
<div class="modal-overlay" id="addJournalModal">
    <div class="modal" style="max-width:900px">
        <div class="modal-header"><h3>New Journal Entry</h3><button class="modal-close" onclick="closeModal('addJournalModal')">&times;</button></div>
        <form method="POST" onsubmit="return validateJournal()">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Entry Date *</label>
                        <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Reference Type</label>
                        <select name="reference_type" class="form-select">
                            <option value="">None</option>
                            <option value="invoice">Invoice</option>
                            <option value="payment">Payment</option>
                            <option value="purchase">Purchase</option>
                            <option value="expense">Expense</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description *</label>
                    <input type="text" name="description" class="form-control" required placeholder="e.g., Sales revenue for January">
                </div>
                <div class="form-group">
                    <label>Entry Items (Debit = Credit)</label>
                    <div id="journalItems">
                        <div class="form-row" style="align-items:end;margin-bottom:8px" data-row="0">
                            <div class="col-md-4">
                                <select name="account_code[]" class="form-select" required>
                                    <option value="">Select Account</option>
                                    <?php foreach ($coa as $a): ?>
                                    <option value="<?= htmlspecialchars($a['account_code']) ?>"><?= htmlspecialchars($a['account_code'] . ' - ' . $a['account_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="debit_amount[]" class="form-control" placeholder="Debit" min="0" oninput="calcJournal()">
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="credit_amount[]" class="form-control" placeholder="Credit" min="0" oninput="calcJournal()">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-success" onclick="addJournalRow()">+</button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeJournalRow(this)" style="display:none">-</button>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:8px">
                        <span id="journalDiff" style="font-weight:600;color:var(--success)">Difference: ₹ 0.00</span>
                    </div>
                </div>
                <div id="addItemDescriptions"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addJournalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Journal Modal -->
<div class="modal-overlay" id="editJournalModal">
    <div class="modal" style="max-width:900px">
        <div class="modal-header"><h3>Edit Journal Entry</h3><button class="modal-close" onclick="closeModal('editJournalModal')">&times;</button></div>
        <form method="POST" onsubmit="return validateJournal()">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_journal_id">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Entry Date *</label>
                        <input type="date" name="entry_date" id="edit_entry_date" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Reference Type</label>
                        <select name="reference_type" id="edit_reference_type" class="form-select">
                            <option value="">None</option>
                            <option value="invoice">Invoice</option>
                            <option value="payment">Payment</option>
                            <option value="purchase">Purchase</option>
                            <option value="expense">Expense</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description *</label>
                    <input type="text" name="description" id="edit_description" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Entry Items</label>
                    <div id="editJournalItems"></div>
                    <div style="margin-top:8px"><span id="editJournalDiff" style="font-weight:600;color:var(--success)">Difference: ₹ 0.00</span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editJournalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Entry</button>
            </div>
        </form>
    </div>
</div>

<script>
var journalRowCount = 1;

function addJournalRow() {
    var container = document.getElementById('journalItems');
    var row = document.createElement('div');
    row.className = 'form-row';
    row.style.cssText = 'align-items:end;margin-bottom:8px';
    row.dataset.row = journalRowCount;
    var coaOptions = document.querySelector('#journalItems select').innerHTML;
    row.innerHTML = '<div class="col-md-4"><select name="account_code[]" class="form-select" required>' + coaOptions + '</select></div>' +
        '<div class="col-md-3"><input type="number" step="0.01" name="debit_amount[]" class="form-control" placeholder="Debit" min="0" oninput="calcJournal()"></div>' +
        '<div class="col-md-3"><input type="number" step="0.01" name="credit_amount[]" class="form-control" placeholder="Credit" min="0" oninput="calcJournal()"></div>' +
        '<div class="col-md-2"><button type="button" class="btn btn-sm btn-success" onclick="addJournalRow()">+</button> <button type="button" class="btn btn-sm btn-danger" onclick="removeJournalRow(this)">-</button></div>';
    container.appendChild(row);
    journalRowCount++;
    updateAddButtons();
}

function removeJournalRow(btn) {
    btn.closest('.form-row').remove();
    calcJournal();
    updateAddButtons();
}

function updateAddButtons() {
    var rows = document.querySelectorAll('#journalItems .form-row');
    rows.forEach(function(r, i) {
        var addBtn = r.querySelector('.btn-success');
        var delBtn = r.querySelector('.btn-danger');
        if (addBtn) addBtn.style.display = i === rows.length - 1 ? '' : 'none';
        if (delBtn) delBtn.style.display = rows.length > 1 ? '' : 'none';
    });
}

function calcJournal() {
    var totalDr = 0, totalCr = 0;
    document.querySelectorAll('input[name^="debit_amount"]').forEach(function(inp) {
        totalDr += parseFloat(inp.value) || 0;
    });
    document.querySelectorAll('input[name^="credit_amount"]').forEach(function(inp) {
        totalCr += parseFloat(inp.value) || 0;
    });
    var diff = totalDr - totalCr;
    var el = document.getElementById('journalDiff') || document.getElementById('editJournalDiff');
    if (el) {
        el.textContent = 'Difference: ₹ ' + Math.abs(diff).toFixed(2) + (diff !== 0 ? ' (NOT BALANCED)' : ' (Balanced)');
        el.style.color = diff === 0 ? 'var(--success)' : 'var(--danger)';
    }
}

function validateJournal() {
    var totalDr = 0, totalCr = 0;
    document.querySelectorAll('input[name^="debit_amount"]').forEach(function(inp) {
        totalDr += parseFloat(inp.value) || 0;
    });
    document.querySelectorAll('input[name^="credit_amount"]').forEach(function(inp) {
        totalCr += parseFloat(inp.value) || 0;
    });
    if (totalDr !== totalCr) {
        alert('Total Debits must equal Total Credits! Difference: ₹ ' + Math.abs(totalDr - totalCr).toFixed(2));
        return false;
    }
    if (totalDr === 0 && totalCr === 0) {
        alert('Please enter at least one item with amounts.');
        return false;
    }
    return true;
}

function viewJournal(id) {
    fetch('/modules/accounts/ajax.php?action=get_journal&id=' + id)
        .then(r => r.json())
        .then(data => {
            var e = data.entry, items = data.items;
            document.getElementById('view_entry_number').textContent = e.entry_number;
            document.getElementById('view_entry_date').textContent = e.entry_date;
            document.getElementById('view_status').innerHTML = '<?php
                $s = ["draft"=>"<span class=\"badge bg-warning\">Draft</span>","posted"=>"<span class=\"badge bg-success\">Posted</span>"];
                foreach($s as $k=>$v) echo "status_$k = \"$v\";";
            ?>'['status_' + e.status] || e.status;
            document.getElementById('view_description').textContent = e.description || '-';
            document.getElementById('view_reference').textContent = e.reference_type ? (e.reference_type + (e.reference_id ? ' #' + e.reference_id : '')) : '-';
            var tbody = document.getElementById('view_items');
            tbody.innerHTML = '';
            var tdr = 0, tcr = 0;
            items.forEach(function(item) {
                tdr += parseFloat(item.debit_amount) || 0;
                tcr += parseFloat(item.credit_amount) || 0;
                var row = tbody.insertRow();
                row.innerHTML = '<td>' + item.account_code + '</td><td>' + (item.description || '-') + '</td><td>' + parseFloat(item.debit_amount).toFixed(2) + '</td><td>' + parseFloat(item.credit_amount).toFixed(2) + '</td>';
            });
            document.getElementById('view_totals').innerHTML = '<tr><td colspan="2"><strong>Totals</strong></td><td><strong>' + tdr.toFixed(2) + '</strong></td><td><strong>' + tcr.toFixed(2) + '</strong></td></tr>';
            openModal('viewJournalModal');
        });
}

function editJournal(id) {
    fetch('/modules/accounts/ajax.php?action=get_journal&id=' + id)
        .then(r => r.json())
        .then(data => {
            var e = data.entry, items = data.items;
            document.getElementById('edit_journal_id').value = e.id;
            document.getElementById('edit_entry_date').value = e.entry_date;
            document.getElementById('edit_description').value = e.description || '';
            document.getElementById('edit_reference_type').value = e.reference_type || '';
            var container = document.getElementById('editJournalItems');
            var coaOptions = document.querySelector('#journalItems select').innerHTML;
            container.innerHTML = '';
            items.forEach(function(item, idx) {
                var div = document.createElement('div');
                div.className = 'form-row';
                div.style.cssText = 'align-items:end;margin-bottom:8px';
                var delBtn = items.length > 1 ? '<button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'.form-row\').remove();calcJournal();">-</button>' : '';
                div.innerHTML = '<div class="col-md-4"><select name="account_code[]" class="form-select" required>' + coaOptions.replace('value="' + item.account_code + '"', 'value="' + item.account_code + '" selected') + '</select></div>' +
                    '<div class="col-md-3"><input type="number" step="0.01" name="debit_amount[]" class="form-control" value="' + parseFloat(item.debit_amount).toFixed(2) + '" min="0" oninput="calcJournal()"></div>' +
                    '<div class="col-md-3"><input type="number" step="0.01" name="credit_amount[]" class="form-control" value="' + parseFloat(item.credit_amount).toFixed(2) + '" min="0" oninput="calcJournal()"></div>' +
                    '<div class="col-md-2">' + delBtn + '</div>';
                container.appendChild(div);
            });
            calcJournal();
            openModal('editJournalModal');
        });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
