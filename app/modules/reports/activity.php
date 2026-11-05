<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear') {
    if (!isAdmin()) {
        showError('Access denied. Only administrators can clear the activity log.');
    } else {
        $db->delete('activity_logs', '1=1');
        $db->logActivity(getUserId(), getEmployeeCode(), 'clear_activity_log', 'reports', 'Cleared all activity logs');
        showSuccess('Activity log has been cleared successfully.');
    }
    header('Location: activity.php');
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $filterUser = isset($_GET['user']) ? sanitize($_GET['user']) : '';
    $filterModule = isset($_GET['module']) ? sanitize($_GET['module']) : '';
    $filterAction = isset($_GET['action']) ? sanitize($_GET['action']) : '';
    $dateFrom = isset($_GET['from']) ? sanitize($_GET['from']) : '';
    $dateTo = isset($_GET['to']) ? sanitize($_GET['to']) : '';

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "(description LIKE ? OR employee_code LIKE ? OR module LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterUser !== '') {
        $where[] = "employee_code = ?";
        $params[] = $filterUser;
    }
    if ($filterModule !== '') {
        $where[] = "module = ?";
        $params[] = $filterModule;
    }
    if ($filterAction !== '') {
        $where[] = "action = ?";
        $params[] = $filterAction;
    }
    if ($dateFrom !== '') {
        $where[] = "created_at >= ?";
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = "created_at <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $logs = $db->fetchAll(
        "SELECT * FROM activity_logs $whereClause ORDER BY created_at DESC",
        $params
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=activity_log_'.date('Y-m-d').'.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Timestamp', 'User', 'Employee Code', 'Action', 'Module', 'Description', 'IP Address']);
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            getEmployeeName($log['employee_code']),
            $log['employee_code'],
            $log['action'],
            $log['module'],
            $log['description'],
            $log['ip_address']
        ]);
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('activity');

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filterUser = isset($_GET['user']) ? sanitize($_GET['user']) : '';
$filterModule = isset($_GET['module']) ? sanitize($_GET['module']) : '';
$filterAction = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$dateFrom = isset($_GET['from']) ? sanitize($_GET['from']) : '';
$dateTo = isset($_GET['to']) ? sanitize($_GET['to']) : '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(description LIKE ? OR employee_code LIKE ? OR module LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterUser !== '') {
    $where[] = "employee_code = ?";
    $params[] = $filterUser;
}
if ($filterModule !== '') {
    $where[] = "module = ?";
    $params[] = $filterModule;
}
if ($filterAction !== '') {
    $where[] = "action = ?";
    $params[] = $filterAction;
}
if ($dateFrom !== '') {
    $where[] = "created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = "created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $db->fetchColumn("SELECT COUNT(*) FROM activity_logs $whereClause", $params);
$offset = ($page - 1) * $perPage;

$logs = $db->fetchAll(
    "SELECT * FROM activity_logs $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$modules = $db->fetchAll("SELECT DISTINCT module FROM activity_logs ORDER BY module");
$actions = $db->fetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$employees = $db->fetchAll("SELECT DISTINCT employee_code FROM activity_logs ORDER BY employee_code");
?>

<div class="page-header">
    <h1 class="page-title">Activity Log</h1>
    <p class="page-subtitle">Track all system activities and user actions</p>
</div>

<div class="action-bar mb-3">
    <a href="?export=csv<?= $search ? '&search=' . urlencode($search) : '' ?><?= $filterUser ? '&user=' . urlencode($filterUser) : '' ?><?= $filterModule ? '&module=' . urlencode($filterModule) : '' ?><?= $filterAction ? '&action=' . urlencode($filterAction) : '' ?><?= $dateFrom ? '&from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&to=' . urlencode($dateTo) : '' ?>" class="btn btn-success btn-sm">Export CSV</a>
    <?php if (isAdmin()): ?>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to clear all activity logs? This action cannot be undone.')">
        <input type="hidden" name="action" value="clear">
        <button type="submit" class="btn btn-danger btn-sm">Clear Activity Log</button>
    </form>
    <?php endif; ?>
</div>

<div class="filter-bar mb-3">
    <form method="GET" class="form-row">
        <div class="form-group">
            <label for="search" class="form-label">Search</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="Search descriptions..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="form-group">
            <label for="user" class="form-label">Employee</label>
            <select id="user" name="user" class="form-select">
                <option value="">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= htmlspecialchars($emp['employee_code']) ?>" <?= $filterUser === $emp['employee_code'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['employee_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="module" class="form-label">Module</label>
            <select id="module" name="module" class="form-select">
                <option value="">All Modules</option>
                <?php foreach ($modules as $mod): ?>
                <option value="<?= htmlspecialchars($mod['module']) ?>" <?= $filterModule === $mod['module'] ? 'selected' : '' ?>><?= htmlspecialchars($mod['module']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="action" class="form-label">Action</label>
            <select id="action" name="action" class="form-select">
                <option value="">All Actions</option>
                <?php foreach ($actions as $act): ?>
                <option value="<?= htmlspecialchars($act['action']) ?>" <?= $filterAction === $act['action'] ? 'selected' : '' ?>><?= htmlspecialchars($act['action']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
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
            <a href="activity.php" class="btn btn-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3>Activity Records (<?= $total ?> total)</h3>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Employee Code</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px;">No activity records found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= formatDateTime($log['created_at']) ?></td>
                    <td><?= htmlspecialchars(getEmployeeName($log['employee_code'])) ?></td>
                    <td><?= htmlspecialchars($log['employee_code']) ?></td>
                    <td><?= getStatusBadge($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['module']) ?></td>
                    <td><?= htmlspecialchars($log['description']) ?></td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginate($total, $page, $perPage) ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
