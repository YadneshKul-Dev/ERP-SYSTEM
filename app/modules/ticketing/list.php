<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['reply_message'])) {
    $ticketId = (int)($_POST['id'] ?? 0);
    $data = [
        'subject' => sanitize($_POST['subject'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'category' => sanitize($_POST['category'] ?? ''),
        'priority' => sanitize($_POST['priority'] ?? 'medium'),
        'status' => sanitize($_POST['status'] ?? 'open'),
        'assigned_to' => sanitize($_POST['assigned_to'] ?? ''),
        'customer_id' => (int)($_POST['customer_id'] ?? 0),
        'department' => sanitize($_POST['department'] ?? ''),
        'due_date' => sanitize($_POST['due_date'] ?? ''),
        'resolution' => sanitize($_POST['resolution'] ?? '')
    ];

    if (empty($data['subject'])) {
        showError('Subject is required');
        header('Location: list.php' . ($ticketId ? "?action=edit&id=$ticketId" : '?action=add'));
        exit;
    }

    if ($ticketId) {
        assertOwnership('tickets', 'tickets', $ticketId, ['created_by', 'assigned_to']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        $db->update('tickets', $data, 'id = ?', [$ticketId]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'edit', 'tickets', 'Edited ticket: ' . $data['subject']);
        showSuccess('Ticket updated successfully');
    } else {
        $data['ticket_code'] = generateCode('TKT');
        $data['created_by'] = getUserId();
        $db->insert('tickets', $data);
        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'tickets', 'Created ticket: ' . $data['subject']);
        showSuccess('Ticket created successfully');
    }
    header('Location: list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $message = sanitize($_POST['reply_message'] ?? '');

    if ($message && $ticketId) {
        $db->insert('ticket_replies', [
            'ticket_id' => $ticketId,
            'user_id' => getUserId(),
            'message' => $message
        ]);
        $db->update('tickets', ['status' => 'in_progress', 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND status = ?', [$ticketId, 'open']);
        $db->logActivity(getUserId(), getEmployeeCode(), 'reply', 'tickets', 'Replied to ticket #' . $ticketId);
        showSuccess('Reply added successfully');
    } else {
        showError('Reply message is required');
    }
    header('Location: list.php?action=view&id=' . $ticketId);
    exit;
}

if (isset($_GET['delete']) && $id) {
    assertOwnership('tickets', 'tickets', $id, ['created_by', 'assigned_to']);
    $ticket = $db->fetchOne("SELECT * FROM tickets WHERE id = ?", [$id]);
    if ($ticket) {
        $db->beginTransaction();
        $db->delete('ticket_replies', 'ticket_id = ?', [$id]);
        $db->delete('tickets', 'id = ?', [$id]);
        $db->commit();
        $db->logActivity(getUserId(), getEmployeeCode(), 'delete', 'tickets', 'Deleted ticket: ' . $ticket['subject']);
        showSuccess('Ticket deleted successfully');
    }
    header('Location: list.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('tickets');

if ($action === 'add' || ($action === 'edit' && $id)) {
    if ($action === 'edit') {
        assertOwnership('tickets', 'tickets', $id, ['created_by', 'assigned_to']);
        $ticket = $db->fetchOne("SELECT * FROM tickets WHERE id = ?", [$id]);
    } else {
        $ticket = null;
    }
    $customers = $db->fetchAll("SELECT id, customer_name, company_name FROM customers WHERE status = 'active' ORDER BY customer_name");
    $categories = ['technical', 'billing', 'general', 'feature', 'other'];
    $priorities = ['low', 'medium', 'high', 'critical'];
    $statuses = ['open', 'in_progress', 'resolved', 'closed'];
?>
<div class="page-header">
    <h1 class="page-title"><?= $ticket ? 'Edit Ticket' : 'Create Ticket' ?></h1>
    <p class="page-subtitle"><?= $ticket ? 'Update ticket information' : 'Submit a new support ticket' ?></p>
</div>

<div class="card">
    <div class="card-header">
        <h3>Ticket Details</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php if ($ticket): ?>
            <input type="hidden" name="id" value="<?= $ticket['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Subject *</label>
                    <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($ticket['subject'] ?? '') ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Customer</label>
                    <select name="customer_id" class="form-select">
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($ticket['customer_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['company_name'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($ticket['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Category</label>
                    <select name="category" class="form-select">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($ticket['category'] ?? '') === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Priority</label>
                    <select name="priority" class="form-select">
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?= $p ?>" <?= ($ticket['priority'] ?? 'medium') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= ($ticket['status'] ?? 'open') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Department</label>
                    <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($ticket['department'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Assigned To</label>
                    <input type="text" name="assigned_to" class="form-control" value="<?= htmlspecialchars($ticket['assigned_to'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($ticket['due_date'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Resolution</label>
                <textarea name="resolution" class="form-control" rows="3"><?= htmlspecialchars($ticket['resolution'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $ticket ? 'Update' : 'Save' ?></button>
                <a href="list.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php } elseif ($action === 'view' && $id) {
    assertOwnership('tickets', 'tickets', $id, ['created_by', 'assigned_to']);
    $ticket = $db->fetchOne("SELECT t.*, c.customer_name, c.company_name FROM tickets t LEFT JOIN customers c ON t.customer_id = c.id WHERE t.id = ?", [$id]);
    if (!$ticket) {
        showError('Ticket not found');
        header('Location: list.php');
        exit;
    }
    $replies = $db->fetchAll("SELECT r.*, u.username FROM ticket_replies r LEFT JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC", [$id]);
?>
<div class="page-header">
    <h1 class="page-title">Ticket <?= htmlspecialchars($ticket['ticket_code']) ?></h1>
    <p class="page-subtitle"><?= htmlspecialchars($ticket['subject']) ?></p>
    <div class="action-bar">
        <a href="?action=edit&id=<?= $ticket['id'] ?>" class="btn btn-sm btn-info">Edit</a>
        <a href="?delete=1&id=<?= $ticket['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this ticket?')">Delete</a>
        <a href="list.php" class="btn btn-sm btn-secondary">Back</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <div class="mb-3">
                    <h4>Description</h4>
                    <p><?= nl2br(htmlspecialchars($ticket['description'] ?? 'No description')) ?></p>
                </div>
                <?php if ($ticket['resolution']): ?>
                <div class="mb-3">
                    <h4>Resolution</h4>
                    <p><?= nl2br(htmlspecialchars($ticket['resolution'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <table class="table">
                    <tr><td><strong>Status</strong></td><td><?= getStatusBadge($ticket['status']) ?></td></tr>
                    <tr><td><strong>Priority</strong></td><td><?= getStatusBadge($ticket['priority']) ?></td></tr>
                    <tr><td><strong>Category</strong></td><td><?= htmlspecialchars(ucfirst($ticket['category'] ?? '-')) ?></td></tr>
                    <tr><td><strong>Customer</strong></td><td><?= htmlspecialchars($ticket['customer_name'] ?? '-') ?></td></tr>
                    <tr><td><strong>Department</strong></td><td><?= htmlspecialchars($ticket['department'] ?? '-') ?></td></tr>
                    <tr><td><strong>Assigned To</strong></td><td><?= htmlspecialchars($ticket['assigned_to'] ?? '-') ?></td></tr>
                    <tr><td><strong>Due Date</strong></td><td><?= formatDate($ticket['due_date']) ?></td></tr>
                    <tr><td><strong>Created</strong></td><td><?= formatDateTime($ticket['created_at']) ?></td></tr>
                </table>
            </div>
        </div>

        <h4 class="mt-4">Replies</h4>
        <?php if (empty($replies)): ?>
        <p class="text-muted">No replies yet.</p>
        <?php else: ?>
        <?php foreach ($replies as $reply): ?>
        <div class="card mt-3">
            <div class="card-header">
                <strong><?= htmlspecialchars($reply['username'] ?? 'System') ?></strong>
                <span class="text-muted"><?= formatDateTime($reply['created_at']) ?></span>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-header">
                <h4>Add Reply</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                    <div class="form-group">
                        <textarea name="reply_message" class="form-control" rows="4" placeholder="Write your reply..." required></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Post Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/modules.js"></script>

<?php } else {
    $search = sanitize($_GET['search'] ?? '');
    $statusFilter = sanitize($_GET['status'] ?? '');
    $priorityFilter = sanitize($_GET['priority'] ?? '');
    $departmentFilter = sanitize($_GET['department'] ?? '');
    $page = (int)($_GET['page'] ?? 1);
    $perPage = 20;

    $where = [];
    $params = [];
    if ($search) {
        $where[] = "(t.subject LIKE ? OR t.ticket_code LIKE ? OR t.description LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    if ($statusFilter) {
        $where[] = "t.status = ?";
        $params[] = $statusFilter;
    }
    if ($priorityFilter) {
        $where[] = "t.priority = ?";
        $params[] = $priorityFilter;
    }
    if ($departmentFilter) {
        $where[] = "t.department LIKE ?";
        $params[] = "%$departmentFilter%";
    }
    list($scopeWhere, $scopeParams) = applyScopeFilter('tickets', ['created_by', 'assigned_to']);
    if ($scopeWhere) {
        $where[] = $scopeWhere;
        $params = array_merge($params, $scopeParams);
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $db->fetchColumn("SELECT COUNT(*) FROM tickets t $whereClause", $params);
    $offset = ($page - 1) * $perPage;
    $tickets = $db->fetchAll("SELECT t.*, c.customer_name FROM tickets t LEFT JOIN customers c ON t.customer_id = c.id $whereClause ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset", $params);
?>
<div class="page-header">
    <h1 class="page-title">Tickets</h1>
    <p class="page-subtitle">Support ticket management</p>
    <a href="?action=add" class="btn btn-primary">Create Ticket</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>All Tickets</h3>
        <div class="filter-bar">
            <form method="GET" class="form-row">
                <div class="search-bar">
                    <input type="text" name="search" class="form-control" placeholder="Search tickets..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <?php foreach (['open', 'in_progress', 'resolved', 'closed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="priority" class="form-select" onchange="this.form.submit()">
                        <option value="">All Priorities</option>
                        <?php foreach (['low', 'medium', 'high', 'critical'] as $p): ?>
                        <option value="<?= $p ?>" <?= $priorityFilter === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" name="department" class="form-control" placeholder="Department" value="<?= htmlspecialchars($departmentFilter) ?>">
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Subject</th>
                    <th>Customer</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Department</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="9" class="text-center">No tickets found</td></tr>
                <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['ticket_code']) ?></td>
                    <td><?= htmlspecialchars($t['subject']) ?></td>
                    <td><?= htmlspecialchars($t['customer_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(ucfirst($t['category'] ?? '-')) ?></td>
                    <td><?= getStatusBadge($t['priority']) ?></td>
                    <td><?= getStatusBadge($t['status']) ?></td>
                    <td><?= htmlspecialchars($t['assigned_to'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['department'] ?? '-') ?></td>
                    <td class="action-bar">
                        <a href="?action=view&id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">View</a>
                        <a href="?action=edit&id=<?= $t['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                        <a href="?delete=1&id=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this ticket?')">Delete</a>
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
