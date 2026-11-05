<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';

if ($action === 'apply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('leave', 'create');
    $fromDate = sanitize($_POST['from_date'] ?? '');
    $toDate = sanitize($_POST['to_date'] ?? '');

    if (strtotime($toDate) < strtotime($fromDate)) {
        showError('To date must be on or after from date');
    } else {
        $db->insert('leave_requests', [
            'employee_code' => Auth::getInstance()->getEmployeeCode(),
            'leave_type' => sanitize($_POST['leave_type'] ?? ''),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'reason' => sanitize($_POST['reason'] ?? ''),
            'status' => 'pending',
        ]);
        showSuccess('Leave request submitted successfully');
        header('Location: ?action=list');
        exit;
    }
}

if ($action === 'approve') {
    requirePermission('leave', 'approve');

    $id = (int)($_GET['id'] ?? 0);
    $status = $_GET['status'] ?? '';
    if (!in_array($status, ['approved', 'rejected'])) {
        showError('Invalid action');
        header('Location: ?action=list');
        exit;
    }

    $leave = $db->fetchOne("SELECT * FROM leave_requests WHERE id = ?", [$id]);
    if (!$leave) {
        showError('Leave request not found');
        header('Location: ?action=list');
        exit;
    }

    $db->update('leave_requests', [
        'status' => $status,
        'approved_by' => Auth::getInstance()->getEmployeeCode(),
    ], 'id = ?', [$id]);

    showSuccess('Leave request ' . $status . ' successfully');
    header('Location: ?action=list');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

if ($action === 'list') {
    if (!hasPermission('leave', 'view') && !hasPermission('leave', 'view_own')) {
        requirePermission('leave', 'view');
    }

    $statusFilter = sanitize($_GET['status'] ?? '');
    $myEmpCode = Auth::getInstance()->getEmployeeCode();

    $where = "WHERE 1=1";
    $params = [];

    if (!hasPermission('leave', 'view')) {
        $where .= " AND lr.employee_code = ?";
        $params[] = $myEmpCode;
    }
    if ($statusFilter !== '') {
        $where .= " AND lr.status = ?";
        $params[] = $statusFilter;
    }

    $leaves = $db->fetchAll(
        "SELECT lr.*, e.first_name, e.last_name, e.department 
         FROM leave_requests lr 
         LEFT JOIN employees e ON lr.employee_code = e.employee_code 
         $where 
         ORDER BY lr.created_at DESC",
        $params
    );

    $leaveCounts = [];
    if (!hasPermission('leave', 'view')) {
        $leaveCounts = $db->fetchAll(
            "SELECT leave_type, COUNT(*) as count, status 
             FROM leave_requests 
             WHERE employee_code = ? 
             GROUP BY leave_type, status",
            [$myEmpCode]
        );
    }

    $canApprove = hasPermission('leave', 'approve');
    ?>
    <div class="page-header">
        <h1 class="page-title">Leave Management</h1>
        <p class="page-subtitle">Apply, track, and manage leave requests</p>
    </div>

    <?php if (!empty($leaveCounts)): ?>
    <div class="stats-grid mb-3">
        <?php
        $leaveTypes = ['Sick', 'Casual', 'Earned', 'Other'];
        foreach ($leaveTypes as $lt):
            $approved = 0; $pending = 0; $rejected = 0;
            foreach ($leaveCounts as $lc) {
                if ($lc['leave_type'] === $lt) {
                    if ($lc['status'] === 'approved') $approved = $lc['count'];
                    elseif ($lc['status'] === 'pending') $pending = $lc['count'];
                    elseif ($lc['status'] === 'rejected') $rejected = $lc['count'];
                }
            }
        ?>
        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-value"><?= $approved + $pending + $rejected ?></span>
                <span class="stat-label"><?= $lt ?> Leave</span>
                <small>Approved: <?= $approved ?> | Pending: <?= $pending ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="action-bar">
        <form method="GET" class="filter-bar">
            <input type="hidden" name="action" value="list">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="?action=list" class="btn btn-secondary">Reset</a>
        </form>
        <div class="action-buttons">
            <?php if (hasPermission('leave', 'create')): ?>
            <a href="?action=apply" class="btn btn-success">Apply Leave</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <?php if ($canApprove): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaves as $lv): ?>
                    <?php
                        $from = new DateTime($lv['from_date']);
                        $to = new DateTime($lv['to_date']);
                        $days = $from->diff($to)->days + 1;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(($lv['first_name'] ?? '') . ' ' . ($lv['last_name'] ?? '')) ?> (<?= htmlspecialchars($lv['employee_code']) ?>)</td>
                        <td><?= htmlspecialchars($lv['department'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($lv['leave_type']) ?></td>
                        <td><?= formatDate($lv['from_date']) ?></td>
                        <td><?= formatDate($lv['to_date']) ?></td>
                        <td><?= $days ?></td>
                        <td><?= htmlspecialchars($lv['reason'] ?? '-') ?></td>
                        <td><?= getStatusBadge($lv['status']) ?></td>
                        <?php if ($canApprove): ?>
                        <td>
                            <?php if ($lv['status'] === 'pending'): ?>
                            <a href="?action=approve&id=<?= $lv['id'] ?>&status=approved" class="btn btn-sm btn-success" onclick="return confirm('Approve this leave?')">Approve</a>
                            <a href="?action=approve&id=<?= $lv['id'] ?>&status=rejected" class="btn btn-sm btn-danger" onclick="return confirm('Reject this leave?')">Reject</a>
                            <?php else: ?>
                            <span class="badge bg-secondary"><?= ucfirst($lv['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($leaves)): ?>
                    <tr><td colspan="<?= $canApprove ? 9 : 8 ?>" style="text-align:center">No leave requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php

} elseif ($action === 'apply') {
    requirePermission('leave', 'create');
    ?>
    <div class="page-header">
        <h1 class="page-title">Apply for Leave</h1>
        <p class="page-subtitle">Submit a new leave request</p>
    </div>

    <form method="POST" class="card">
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Leave Type *</label>
                    <select name="leave_type" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Sick">Sick Leave</option>
                        <option value="Casual">Casual Leave</option>
                        <option value="Earned">Earned Leave</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>From Date *</label>
                    <input type="date" name="from_date" class="form-control" required>
                </div>
                <div class="form-group col-md-4">
                    <label>To Date *</label>
                    <input type="date" name="to_date" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>Reason *</label>
                <textarea name="reason" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Request</button>
                <a href="?action=list" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
    <?php
}

require_once __DIR__ . '/../../includes/footer.php'; ?>
