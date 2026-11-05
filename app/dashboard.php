<?php
// Handle logout BEFORE any output
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    require_once __DIR__ . '/init.php';
    Auth::getInstance()->logout();
    header('Location: /');
    exit;
}

require_once __DIR__ . '/includes/header.php';
$db = Database::getInstance();
$auth = Auth::getInstance();
$user = $auth->getUser();
$empCode = $auth->getEmployeeCode();
$role = $auth->getRoleName();

// Get employee details including department
$employee = $db->fetchOne("SELECT * FROM employees WHERE employee_code = ?", [$empCode]);
$department = $employee['department'] ?? '';
$designation = $employee['designation'] ?? '';
$empName = ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '');

// Determine department type for dashboard customization
$deptType = 'general';
if ($role === 'Super Admin' || $role === 'Admin') $deptType = 'admin';
elseif (stripos($department, 'Human Resource') !== false) $deptType = 'hr';
elseif (stripos($department, 'Finance') !== false && stripos($department, 'Account') === false) $deptType = 'finance';
elseif (stripos($department, 'Account') !== false) $deptType = 'accounts';
elseif (stripos($department, 'IT') !== false) $deptType = 'it';
elseif (stripos($department, 'CRM') !== false || stripos($department, 'Sales') !== false) $deptType = 'crm';
elseif (stripos($department, 'Operation') !== false) $deptType = 'operations';
elseif (stripos($department, 'Market') !== false) $deptType = 'marketing';
elseif (stripos($department, 'Support') !== false) $deptType = 'support';
elseif (stripos($department, 'Inventory') !== false) $deptType = 'inventory';
elseif (stripos($department, 'Purchas') !== false) $deptType = 'purchasing';
elseif (stripos($department, 'Legal') !== false) $deptType = 'legal';
?>

<div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">
        Welcome back, <strong><?= htmlspecialchars($empName ?: $user['username']) ?></strong>
        | <?= htmlspecialchars($department ?: 'Administration') ?> 
        | <?= htmlspecialchars($designation ?: $role) ?>
    </p>
</div>

<!-- Personal Stats (shown to ALL users) -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-primary">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= htmlspecialchars($empCode) ?></span>
            <span class="stat-label">Employee Code</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-info">
            <?php
            $todayAtt = $db->fetchOne("SELECT * FROM attendance WHERE employee_code = ? AND attendance_date = ?", [$empCode, date('Y-m-d')]);
            $todayStatus = $todayAtt ? ucfirst($todayAtt['status']) : 'Not Marked';
            ?>
            <span class="stat-value"><?= $todayStatus ?></span>
            <span class="stat-label">Today's Attendance</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
        </div>
        <div class="stat-info">
            <?php
            $myLeaves = $db->fetchColumn("SELECT COUNT(*) FROM leave_requests WHERE employee_code = ? AND status = 'pending'", [$empCode]);
            ?>
            <span class="stat-value"><?= $myLeaves ?></span>
            <span class="stat-label">My Pending Leaves</span>
        </div>
    </div>
    <?php if ($role !== 'Employee'): ?>
    <div class="stat-card">
        <div class="stat-icon bg-info">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $role ?></span>
            <span class="stat-label">Your Role</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Department-Specific Dashboard Sections -->
<div class="row mt-4">
    <div class="col-md-8">
        <?php if ($deptType === 'admin'): ?>
        <!-- ADMIN: Full system overview -->
        <div class="card">
            <div class="card-header"><h3>System Overview</h3></div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                    <?php
                    $totEmp = $db->fetchColumn("SELECT COUNT(*) FROM employees WHERE is_active=1");
                    $totDept = $db->fetchColumn("SELECT COUNT(*) FROM departments WHERE is_active=1");
                    $totUsers = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_active=1");
                    $totCust = $db->fetchColumn("SELECT COUNT(*) FROM customers WHERE status='active'");
                    $totTickets = $db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE status='open'");
                    $totLeads = $db->fetchColumn("SELECT COUNT(*) FROM leads");
                    ?>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totEmp ?></span><span class="stat-label">Employees</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totDept ?></span><span class="stat-label">Departments</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totUsers ?></span><span class="stat-label">Users</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totCust ?></span><span class="stat-label">Customers</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totTickets ?></span><span class="stat-label">Open Tickets</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totLeads ?></span><span class="stat-label">Leads</span></div></div>
                </div>
            </div>
        </div>

        <?php elseif ($deptType === 'hr'): ?>
        <!-- HR Dashboard -->
        <div class="card">
            <div class="card-header"><h3>HR Overview</h3><a href="/modules/hr/employees.php" class="btn btn-sm btn-primary">Manage Employees</a></div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                    <?php
                    $totalEmp = $db->fetchColumn("SELECT COUNT(*) FROM employees WHERE is_active=1");
                    $present = $db->fetchColumn("SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='present'", [date('Y-m-d')]);
                    $absent = $db->fetchColumn("SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='absent'", [date('Y-m-d')]);
                    $pendingLeave = $db->fetchColumn("SELECT COUNT(*) FROM leave_requests WHERE status='pending'");
                    ?>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totalEmp ?></span><span class="stat-label">Total Employees</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $present ?></span><span class="stat-label">Present Today</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $absent ?></span><span class="stat-label">Absent Today</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $pendingLeave ?></span><span class="stat-label">Pending Leaves</span></div></div>
                </div>
            </div>
        </div>

        <?php elseif ($deptType === 'finance'): ?>
        <!-- Finance Dashboard -->
        <div class="card">
            <div class="card-header"><h3>Finance Overview</h3><a href="/modules/accounts/invoices.php" class="btn btn-sm btn-primary">Invoices</a></div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                    <?php
                    $paid = $db->fetchColumn("SELECT COUNT(*) FROM invoices WHERE status='paid'");
                    $unpaid = $db->fetchColumn("SELECT COUNT(*) FROM invoices WHERE status='unpaid'");
                    $revenue = $db->fetchColumn("SELECT SUM(total) FROM invoices WHERE status='paid'") ?: 0;
                    $pendingAmt = $db->fetchColumn("SELECT SUM(amount_due) FROM invoices WHERE status='unpaid'") ?: 0;
                    $totalInv = $db->fetchColumn("SELECT COUNT(*) FROM invoices");
                    $monthRev = $db->fetchColumn("SELECT COALESCE(SUM(total),0) FROM invoices WHERE strftime('%Y-%m', invoice_date) = strftime('%Y-%m', 'now') AND status='paid'") ?: 0;
                    $budgetCnt = $db->fetchColumn("SELECT COUNT(*) FROM budgets WHERE status='active'");
                    ?>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totalInv ?></span><span class="stat-label">Total Invoices</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $paid ?></span><span class="stat-label">Paid</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $unpaid ?></span><span class="stat-label">Unpaid</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= formatCurrency($revenue) ?></span><span class="stat-label">Total Revenue</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= formatCurrency($monthRev) ?></span><span class="stat-label">This Month</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= formatCurrency($pendingAmt) ?></span><span class="stat-label">Outstanding</span></div></div>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><h3>Quick Actions</h3></div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="/modules/accounts/invoices.php?action=add" class="action-btn">+ New Invoice</a>
                    <a href="/modules/finance/payments.php?action=add" class="action-btn">+ Record Payment</a>
                    <a href="/modules/finance/budget.php" class="action-btn">View Budgets</a>
                    <a href="/modules/finance/reports.php" class="action-btn">Financial Reports</a>
                </div>
            </div>
        </div>

        <?php elseif ($deptType === 'accounts'): ?>
        <!-- Accounts Dashboard -->
        <div class="card">
            <div class="card-header"><h3>Accounts Overview</h3><a href="/modules/accounts/journal.php" class="btn btn-sm btn-primary">Journal</a></div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                    <?php
                    $totalAccts = $db->fetchColumn("SELECT COUNT(*) FROM chart_of_accounts WHERE is_active=1");
                    $draftJE = $db->fetchColumn("SELECT COUNT(*) FROM journal_entries WHERE status='draft'");
                    $postedJE = $db->fetchColumn("SELECT COUNT(*) FROM journal_entries WHERE status='posted'");
                    $pendingRecon = $db->fetchColumn("SELECT COUNT(*) FROM bank_reconciliation WHERE status='pending'");
                    $gstReturns = $db->fetchColumn("SELECT COUNT(*) FROM gst_returns WHERE status='filed'");
                    $jeTotal = $db->fetchColumn("SELECT COUNT(*) FROM journal_entries") ?: 0;
                    ?>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $totalAccts ?></span><span class="stat-label">Chart of Accounts</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $jeTotal ?></span><span class="stat-label">Journal Entries</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $postedJE ?></span><span class="stat-label">Posted</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $draftJE ?></span><span class="stat-label">Draft</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $pendingRecon ?></span><span class="stat-label">Pending Reconciliations</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $gstReturns ?></span><span class="stat-label">GST Returns Filed</span></div></div>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><h3>Quick Actions</h3></div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="/modules/accounts/journal.php?action=add" class="action-btn">+ New Journal Entry</a>
                    <a href="/modules/accounts/ledger.php" class="action-btn">View Ledger</a>
                    <a href="/modules/accounts/trial_balance.php" class="action-btn">Trial Balance</a>
                    <a href="/modules/accounts/bank_reconciliation.php" class="action-btn">Reconcile Bank</a>
                    <a href="/modules/accounts/gst.php" class="action-btn">GST Compliance</a>
                </div>
            </div>
        </div>

        <?php elseif ($deptType === 'crm' || $deptType === 'marketing'): ?>
        <!-- CRM Dashboard -->
        <div class="card">
            <div class="card-header"><h3>CRM Overview</h3><a href="/modules/crm/leads.php" class="btn btn-sm btn-primary">Manage Leads</a></div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                    <?php
                    $custs = $db->fetchColumn("SELECT COUNT(*) FROM customers WHERE status='active'");
                    $leads = $db->fetchColumn("SELECT COUNT(*) FROM leads");
                    $newLeads = $db->fetchColumn("SELECT COUNT(*) FROM leads WHERE status='new'");
                    $qualified = $db->fetchColumn("SELECT COUNT(*) FROM leads WHERE status='qualified'");
                    ?>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $custs ?></span><span class="stat-label">Customers</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $leads ?></span><span class="stat-label">Total Leads</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $newLeads ?></span><span class="stat-label">New Leads</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $qualified ?></span><span class="stat-label">Qualified</span></div></div>
                </div>
            </div>
        </div>

        <?php elseif ($deptType === 'it' || $deptType === 'support'): ?>
        <!-- IT/Support Dashboard -->
        <div class="card">
            <div class="card-header"><h3>Support Overview</h3><a href="/modules/ticketing/list.php" class="btn btn-sm btn-primary">View Tickets</a></div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                    <?php
                    $open = $db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE status='open'");
                    $inProg = $db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE status='in_progress'");
                    $resolved = $db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE status='resolved'");
                    $critical = $db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE priority='critical' AND status!='closed'");
                    ?>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $open ?></span><span class="stat-label">Open</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $inProg ?></span><span class="stat-label">In Progress</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $resolved ?></span><span class="stat-label">Resolved</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $critical ?></span><span class="stat-label">Critical</span></div></div>
                </div>
            </div>
        </div>

        <?php elseif ($deptType === 'operations'): ?>
        <div class="card">
            <div class="card-header"><h3>Operations Overview</h3></div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                    <?php
                    $deptEmp = $db->fetchColumn("SELECT COUNT(*) FROM employees WHERE department LIKE '%Operation%' AND is_active=1");
                    $allEmp = $db->fetchColumn("SELECT COUNT(*) FROM employees WHERE is_active=1");
                    $todayPres = $db->fetchColumn("SELECT COUNT(*) FROM attendance WHERE attendance_date=? AND status='present'", [date('Y-m-d')]);
                    ?>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $deptEmp ?></span><span class="stat-label">Team Size</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $allEmp ?></span><span class="stat-label">Total Employees</span></div></div>
                    <div class="stat-card" style="padding:12px"><div class="stat-info"><span class="stat-value"><?= $todayPres ?></span><span class="stat-label">Present Today</span></div></div>
                </div>
            </div>
        </div>

        <?php elseif ($deptType === 'inventory'): ?>
        <div class="card">
            <div class="card-header"><h3>Inventory Overview</h3></div>
            <div class="card-body">
                <p>Inventory management module coming soon. You can manage employees and track items from the menu.</p>
            </div>
        </div>

        <?php elseif ($deptType === 'purchasing'): ?>
        <div class="card">
            <div class="card-header"><h3>Purchasing Overview</h3></div>
            <div class="card-body">
                <p>Purchasing module coming soon. Use the system to create invoices and manage vendors.</p>
            </div>
        </div>

        <?php elseif ($deptType === 'legal'): ?>
        <div class="card">
            <div class="card-header"><h3>Legal Overview</h3></div>
            <div class="card-body">
                <p>Legal department dashboard. Use support tickets for legal case tracking.</p>
            </div>
        </div>

        <?php else: ?>
        <!-- General Employee Dashboard -->
        <div class="card">
            <div class="card-header"><h3>My Quick Actions</h3></div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="/modules/hr/profile.php" class="action-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        My Profile
                    </a>
                    <a href="/modules/hr/attendance.php" class="action-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        My Attendance
                    </a>
                    <a href="/modules/hr/leave.php?action=add" class="action-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Apply Leave
                    </a>
                    <a href="/modules/ticketing/list.php?action=add" class="action-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 11v4"/><path d="M10 13h4"/></svg>
                        New Ticket
                    </a>
                    <a href="/modules/hr/idcard.php" class="action-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="22" height="18" rx="2"/><line x1="6" y1="9" x2="10" y2="9"/><line x1="6" y1="13" x2="10" y2="13"/><line x1="14" y1="9" x2="18" y2="9"/><line x1="14" y1="13" x2="18" y2="13"/><line x1="10" y1="17" x2="14" y2="17"/></svg>
                        ID Card
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- My Recent Tickets (shown to everyone who has tickets) -->
        <?php
        $myTickets = $db->fetchAll("SELECT * FROM tickets WHERE created_by = ? OR assigned_to = ? ORDER BY created_at DESC LIMIT 5", [getUserId(), $empCode]);
        if ($myTickets):
        ?>
        <div class="card mt-3">
            <div class="card-header"><h3>My Recent Tickets</h3><a href="/modules/ticketing/list.php" class="btn btn-sm btn-primary">All Tickets</a></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead><tr><th>Code</th><th>Subject</th><th>Priority</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($myTickets as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['ticket_code']) ?></td>
                            <td><?= htmlspecialchars(substr($t['subject'],0,40)) ?>..</td>
                            <td><?= getStatusBadge($t['priority']) ?></td>
                            <td><?= getStatusBadge($t['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <!-- My Profile Card -->
        <div class="card">
            <div class="card-header"><h3>My Profile</h3><a href="/modules/hr/profile.php" class="btn btn-sm btn-primary">View</a></div>
            <div class="card-body">
                <div style="text-align:center;margin-bottom:16px">
                    <div style="width:64px;height:64px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 8px">
                        <?= strtoupper(substr($employee['first_name']??$user['username'],0,1)) ?>
                    </div>
                    <h4 style="margin:0"><?= htmlspecialchars($empName ?: $user['username']) ?></h4>
                    <small style="color:var(--text-light)"><?= htmlspecialchars($designation ?: $role) ?></small>
                </div>
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Code</span><span class="info-value"><?= htmlspecialchars($empCode) ?></span></div>
                    <div class="info-item"><span class="info-label">Department</span><span class="info-value"><?= htmlspecialchars($department ?: 'Administration') ?></span></div>
                    <div class="info-item"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($employee['official_email']??$user['email']??'-') ?></span></div>
                    <div class="info-item"><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($employee['phone_number']??'-') ?></span></div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <?php
        $myActivity = $db->fetchAll("SELECT * FROM activity_logs WHERE employee_code = ? ORDER BY created_at DESC LIMIT 5", [$empCode]);
        if ($myActivity):
        ?>
        <div class="card mt-3">
            <div class="card-header"><h3>My Activity</h3></div>
            <div class="card-body p-0">
                <div class="activity-list">
                    <?php foreach ($myActivity as $log): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="activity-content">
                            <span class="activity-action"><?= htmlspecialchars($log['action']) ?></span>
                            <span class="activity-module">[<?= htmlspecialchars($log['module']) ?>]</span>
                            <span class="activity-time"><?= formatDateTime($log['created_at']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Info -->
        <div class="card mt-3">
            <div class="card-header"><h3>System</h3></div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Time</span><span class="info-value"><?= date('d-m-Y H:i:s') ?></span></div>
                    <div class="info-item"><span class="info-label">Role</span><span class="info-value"><?= $role ?></span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
