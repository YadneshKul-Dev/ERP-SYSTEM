<?php
// ============================================
// System Initialization
// ============================================

require_once __DIR__ . '/config.php';

$auth = Auth::getInstance();
$db = Database::getInstance();
$enc = Encryption::getInstance();

function getCurrentUser() {
    return Auth::getInstance()->getUser();
}

function hasPermission($module, $action = 'view') {
    return Auth::getInstance()->hasPermission($module, $action);
}

function requireLogin() {
    Auth::getInstance()->requireLogin();
}

function requirePermission($module, $action = 'view') {
    Auth::getInstance()->requirePermission($module, $action);
}

function getUserId() {
    return Auth::getInstance()->getUserId();
}

function getEmployeeCode() {
    return Auth::getInstance()->getEmployeeCode();
}

function isAdmin() {
    return Auth::getInstance()->isAdmin();
}

function getEmployeeName($empCode = null) {
    $db = Database::getInstance();
    if ($empCode === null) {
        $empCode = Auth::getInstance()->getEmployeeCode();
    }
    $emp = $db->fetchOne("SELECT first_name, last_name FROM employees WHERE employee_code = ?", [$empCode]);
    if ($emp) {
        return $emp['first_name'] . ' ' . $emp['last_name'];
    }
    return 'N/A';
}

function formatDate($date, $format = DATE_FORMAT) {
    if (!$date || $date === '0000-00-00') return '-';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = DATE_FORMAT . ' ' . TIME_FORMAT) {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

function formatCurrency($amount) {
    return '₹ ' . number_format($amount, 2);
}

function generateCode($prefix = 'GEN', $length = 6) {
    $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, $length));
    $num = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $rand . $num;
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function showSuccess($message) {
    $_SESSION['success'] = $message;
}

function showError($message) {
    $_SESSION['error'] = $message;
}

function getSuccess() {
    $msg = $_SESSION['success'] ?? '';
    unset($_SESSION['success']);
    return $msg;
}

function getError() {
    $msg = $_SESSION['error'] ?? '';
    unset($_SESSION['error']);
    return $msg;
}

function getModuleUrl($module, $action = 'list', $params = []) {
    $url = "modules/{$module}/index.php?action={$action}";
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $url .= "&{$key}=" . urlencode($value);
        }
    }
    return $url;
}

function getStatusBadge($status) {
    $colors = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'open' => 'info',
        'closed' => 'secondary',
        'in_progress' => 'primary',
        'resolved' => 'success',
        'paid' => 'success',
        'unpaid' => 'danger',
        'partial' => 'warning',
        'new' => 'info',
        'contacted' => 'primary',
        'qualified' => 'success',
        'lost' => 'danger',
        'present' => 'success',
        'absent' => 'danger',
        'leave' => 'warning',
        'half_day' => 'info',
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'info',
        'critical' => 'danger',
    ];
    $color = $colors[strtolower($status)] ?? 'secondary';
    return "<span class='badge bg-{$color}'>{$status}</span>";
}

function applyScopeFilter($module, $columns = ['created_by'])
{
    $auth = Auth::getInstance();
    $scope = $auth->getScope($module);
    if ($scope === 'all' || $auth->getRoleName() === 'Super Admin') {
        return ['', []];
    }

    $userId = $auth->getUserId();
    $empCode = $auth->getEmployeeCode();

    $clauses = [];
    $params = [];

    foreach ($columns as $col) {
        switch ($col) {
            case 'created_by':
            case 'user_id':
                $clauses[] = "$col = ?";
                $params[] = $userId;
                break;
            case 'assigned_to':
            case 'employee_code':
                $clauses[] = "$col = ?";
                $params[] = $empCode;
                break;
        }
    }

    if (empty($clauses)) {
        return [' 1=0', []];
    }

    return ['(' . implode(' OR ', $clauses) . ')', $params];
}

function verifyRecordOwnership($module, $table, $recordId, $columns = ['created_by'])
{
    $auth = Auth::getInstance();
    if ($auth->getRoleName() === 'Super Admin') {
        return true;
    }

    $scope = $auth->getScope($module);
    if ($scope === 'all') {
        return true;
    }

    $record = Database::getInstance()->fetchOne("SELECT * FROM {$table} WHERE id = ?", [(int)$recordId]);
    if (!$record) {
        return false;
    }

    $userId = $auth->getUserId();
    $empCode = $auth->getEmployeeCode();

    foreach ($columns as $col) {
        switch ($col) {
            case 'created_by':
            case 'user_id':
                if ((int)($record[$col] ?? 0) === $userId) {
                    return true;
                }
                break;
            case 'assigned_to':
            case 'employee_code':
                if (($record[$col] ?? '') === $empCode) {
                    return true;
                }
                break;
        }
    }

    return false;
}

function assertOwnership($module, $table, $recordId, $columns = ['created_by'])
{
    if (!verifyRecordOwnership($module, $table, $recordId, $columns)) {
        http_response_code(403);
        $_SESSION['error'] = 'Access denied: You do not own this record.';
        header('Location: /dashboard.php');
        exit;
    }
}

function paginate($total, $page, $perPage = 20) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    $html = '<nav><ul class="pagination pagination-sm justify-content-center">';
    
    if ($page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($page - 1) . '">&laquo;</a></li>';
    }
    
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($page < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($page + 1) . '">&raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}
