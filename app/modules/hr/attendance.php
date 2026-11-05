<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'mark' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('attendance', 'create');
    $date = sanitize($_POST['date'] ?? date('Y-m-d'));
    $statuses = $_POST['status'] ?? [];
    $checkIns = $_POST['check_in'] ?? [];
    $checkOuts = $_POST['check_out'] ?? [];
    $notes = $_POST['notes'] ?? [];

    foreach ($statuses as $empCode => $status) {
        $existing = $db->fetchOne("SELECT id FROM attendance WHERE employee_code = ? AND attendance_date = ?", [$empCode, $date]);
        $data = [
            'employee_code' => $empCode,
            'attendance_date' => $date,
            'status' => sanitize($status),
            'check_in' => sanitize($checkIns[$empCode] ?? ''),
            'check_out' => sanitize($checkOuts[$empCode] ?? ''),
            'notes' => sanitize($notes[$empCode] ?? ''),
        ];
        if ($existing) {
            $db->update('attendance', $data, 'id = ?', [$existing['id']]);
        } else {
            $db->insert('attendance', $data);
        }
    }
    showSuccess('Attendance marked successfully');
    header('Location: ?action=list');
    exit;
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('attendance', 'edit');
    $db->update('attendance', [
        'attendance_date' => sanitize($_POST['attendance_date']),
        'status' => sanitize($_POST['status']),
        'check_in' => sanitize($_POST['check_in'] ?? ''),
        'check_out' => sanitize($_POST['check_out'] ?? ''),
        'notes' => sanitize($_POST['notes'] ?? ''),
    ], 'id = ?', [$id]);
    showSuccess('Attendance updated successfully');
    header('Location: ?action=list');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

if ($action === 'list') {
    if (!hasPermission('attendance', 'view') && !hasPermission('attendance', 'view_own')) {
        requirePermission('attendance', 'view');
    }

    $fromDate = sanitize($_GET['from_date'] ?? date('Y-m-01'));
    $toDate = sanitize($_GET['to_date'] ?? date('Y-m-d'));
    $empFilter = sanitize($_GET['employee_code'] ?? '');

    $where = "WHERE a.attendance_date BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];

    if (!hasPermission('attendance', 'view')) {
        $where .= " AND a.employee_code = ?";
        $params[] = Auth::getInstance()->getEmployeeCode();
    } elseif ($empFilter) {
        $where .= " AND a.employee_code = ?";
        $params[] = $empFilter;
    }

    $attendances = $db->fetchAll(
        "SELECT a.*, e.first_name, e.last_name, e.department 
         FROM attendance a 
         LEFT JOIN employees e ON a.employee_code = e.employee_code 
         $where 
         ORDER BY a.attendance_date DESC, a.employee_code ASC",
        $params
    );

    $employees = [];
    if (hasPermission('attendance', 'view')) {
        $employees = $db->fetchAll("SELECT employee_code, first_name, last_name FROM employees WHERE is_active = 1 ORDER BY first_name");
    }
    ?>
    <div class="page-header">
        <h1 class="page-title">Attendance</h1>
        <p class="page-subtitle">Manage daily attendance records</p>
    </div>

    <div class="action-bar">
        <form method="GET" class="filter-bar">
            <input type="hidden" name="action" value="list">
            <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>">
            <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>">
            <?php if (hasPermission('attendance', 'view')): ?>
            <select name="employee_code" class="form-select">
                <option value="">All Employees</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= htmlspecialchars($e['employee_code']) ?>" <?= $empFilter === $e['employee_code'] ? 'selected' : '' ?>><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="?action=list" class="btn btn-secondary">Reset</a>
        </form>
        <div class="action-buttons">
            <?php if (hasPermission('attendance', 'create')): ?>
            <a href="?action=mark" class="btn btn-success">Mark Attendance</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Notes</th>
                        <?php if (hasPermission('attendance', 'edit')): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendances as $a): ?>
                    <tr>
                        <td><?= formatDate($a['attendance_date']) ?></td>
                        <td><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?> (<?= htmlspecialchars($a['employee_code']) ?>)</td>
                        <td><?= htmlspecialchars($a['department'] ?? '-') ?></td>
                        <td><?= getStatusBadge($a['status']) ?></td>
                        <td><?= htmlspecialchars($a['check_in'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($a['check_out'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($a['notes'] ?? '-') ?></td>
                        <?php if (hasPermission('attendance', 'edit')): ?>
                        <td><a href="?action=edit&id=<?= $a['id'] ?>" class="btn btn-sm btn-primary">Edit</a></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($attendances)): ?>
                    <tr><td colspan="<?= hasPermission('attendance', 'edit') ? 8 : 7 ?>" style="text-align:center">No attendance records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php

} elseif ($action === 'mark') {
    requirePermission('attendance', 'create');

    $date = sanitize($_GET['date'] ?? date('Y-m-d'));
    $employees = $db->fetchAll("SELECT employee_code, first_name, last_name, department FROM employees WHERE is_active = 1 ORDER BY first_name");
    ?>
    <div class="page-header">
        <h1 class="page-title">Mark Attendance</h1>
        <p class="page-subtitle">Date: <?= formatDate($date) ?></p>
    </div>

    <form method="POST" class="card">
        <div class="card-body">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>" required>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <?php $existing = $db->fetchOne("SELECT * FROM attendance WHERE employee_code = ? AND attendance_date = ?", [$emp['employee_code'], $date]); ?>
                    <tr>
                        <td><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= htmlspecialchars($emp['employee_code']) ?>)</td>
                        <td><?= htmlspecialchars($emp['department'] ?? '-') ?></td>
                        <td>
                            <select name="status[<?= htmlspecialchars($emp['employee_code']) ?>]" class="form-select">
                                <option value="present" <?= ($existing['status'] ?? '') === 'present' ? 'selected' : '' ?>>Present</option>
                                <option value="absent" <?= ($existing['status'] ?? '') === 'absent' ? 'selected' : '' ?>>Absent</option>
                                <option value="leave" <?= ($existing['status'] ?? '') === 'leave' ? 'selected' : '' ?>>Leave</option>
                                <option value="half_day" <?= ($existing['status'] ?? '') === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                            </select>
                        </td>
                        <td><input type="time" name="check_in[<?= htmlspecialchars($emp['employee_code']) ?>]" class="form-control" value="<?= htmlspecialchars($existing['check_in'] ?? '') ?>"></td>
                        <td><input type="time" name="check_out[<?= htmlspecialchars($emp['employee_code']) ?>]" class="form-control" value="<?= htmlspecialchars($existing['check_out'] ?? '') ?>"></td>
                        <td><input type="text" name="notes[<?= htmlspecialchars($emp['employee_code']) ?>]" class="form-control" value="<?= htmlspecialchars($existing['notes'] ?? '') ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Attendance</button>
                <a href="?action=list" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
    <?php

} elseif ($action === 'edit') {
    requirePermission('attendance', 'edit');
    $att = $db->fetchOne("SELECT * FROM attendance WHERE id = ?", [$id]);
    if (!$att) {
        showError('Attendance record not found');
        header('Location: ?action=list');
        exit;
    }
    ?>
    <div class="page-header">
        <h1 class="page-title">Edit Attendance</h1>
    </div>

    <form method="POST" class="card">
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Employee</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($att['employee_code']) ?>" readonly>
                </div>
                <div class="form-group col-md-3">
                    <label>Date</label>
                    <input type="date" name="attendance_date" class="form-control" value="<?= htmlspecialchars($att['attendance_date']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Status</label>
                    <select name="status" class="form-select" required>
                        <option value="present" <?= $att['status'] === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= $att['status'] === 'absent' ? 'selected' : '' ?>>Absent</option>
                        <option value="leave" <?= $att['status'] === 'leave' ? 'selected' : '' ?>>Leave</option>
                        <option value="half_day" <?= $att['status'] === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Check In</label>
                    <input type="time" name="check_in" class="form-control" value="<?= htmlspecialchars($att['check_in'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Check Out</label>
                    <input type="time" name="check_out" class="form-control" value="<?= htmlspecialchars($att['check_out'] ?? '') ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= htmlspecialchars($att['notes'] ?? '') ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="?action=list" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
    <?php
}

require_once __DIR__ . '/../../includes/footer.php'; ?>
