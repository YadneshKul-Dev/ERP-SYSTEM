<?php
require_once __DIR__ . '/../init.php';
requireLogin();

$currentUser = getCurrentUser();
$success = getSuccess();
$error = getError();
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Dashboard</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                </div>
                <span class="sidebar-title"><?= APP_NAME ?></span>
                <button class="sidebar-toggle" onclick="toggleSidebar()">&times;</button>
            </div>
            
            <div class="sidebar-user">
                <div class="user-avatar">
                    <?= strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= getEmployeeName() ?></span>
                    <span class="user-role"><?= $currentUser['role_name'] ?? '' ?></span>
                    <span class="user-code"><?= $currentUser['employee_code'] ?? '' ?></span>
                </div>
            </div>
            
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="/dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php if (hasPermission('admin') || hasPermission('users') || hasPermission('roles')): ?>
                <li class="nav-section">Administration</li>
                <?php if (hasPermission('company')): ?>
                <li class="nav-item">
                    <a href="/modules/admin/company.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <span>Company Profile</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('users')): ?>
                <li class="nav-item">
                    <a href="/modules/admin/users.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>User Management</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('roles')): ?>
                <li class="nav-item">
                    <a href="/modules/admin/roles.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>Roles & Permissions</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('departments')): ?>
                <li class="nav-item">
                    <a href="/modules/admin/departments.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        <span>Departments</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if (hasPermission('hr') || hasPermission('employees')): ?>
                <li class="nav-section">Human Resources</li>
                <li class="nav-item">
                    <a href="/modules/hr/employees.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Employees</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/modules/hr/idcard.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="22" height="18" rx="2"/><line x1="6" y1="9" x2="10" y2="9"/><line x1="6" y1="13" x2="10" y2="13"/><line x1="14" y1="9" x2="18" y2="9"/><line x1="14" y1="13" x2="18" y2="13"/><line x1="10" y1="17" x2="14" y2="17"/></svg>
                        <span>ID Cards</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/modules/hr/attendance.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span>Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/modules/hr/leave.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                        <span>Leave Management</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission('crm') || hasPermission('leads') || hasPermission('customers')): ?>
                <li class="nav-section">CRM</li>
                <li class="nav-item">
                    <a href="/modules/crm/dashboard.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        <span>CRM Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/modules/crm/leads.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>Leads</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/modules/crm/customers.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/></svg>
                        <span>Customers</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission('invoices') || hasPermission('payments') || hasPermission('budget') || hasPermission('financial_reports')): ?>
                <li class="nav-section">Finance</li>
                <li class="nav-item">
                    <a href="/modules/accounts/invoices.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <span>Invoices</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/modules/finance/payments.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="5" width="22" height="14" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/><line x1="7" y1="15" x2="10" y2="15"/><line x1="13" y1="15" x2="17" y2="15"/></svg>
                        <span>Payments</span>
                    </a>
                </li>
                <?php if (hasPermission('budget')): ?>
                <li class="nav-item">
                    <a href="/modules/finance/budget.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        <span>Budget</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('financial_reports')): ?>
                <li class="nav-item">
                    <a href="/modules/finance/reports.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                        <span>Financial Reports</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (hasPermission('journal') || hasPermission('ledger') || hasPermission('trial_balance') || hasPermission('chart_of_accounts') || hasPermission('bank_reconciliation') || hasPermission('gst')): ?>
                <li class="nav-section">Accounts</li>
                <?php if (hasPermission('chart_of_accounts')): ?>
                <li class="nav-item">
                    <a href="/modules/accounts/chart_of_accounts.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <span>Chart of Accounts</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('journal')): ?>
                <li class="nav-item">
                    <a href="/modules/accounts/journal.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>Journal Entries</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('ledger')): ?>
                <li class="nav-item">
                    <a href="/modules/accounts/ledger.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                        <span>General Ledger</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('trial_balance')): ?>
                <li class="nav-item">
                    <a href="/modules/accounts/trial_balance.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
                        <span>Trial Balance</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('bank_reconciliation')): ?>
                <li class="nav-item">
                    <a href="/modules/accounts/bank_reconciliation.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="5" width="22" height="14" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/><line x1="7" y1="15" x2="10" y2="15"/><line x1="13" y1="15" x2="17" y2="15"/></svg>
                        <span>Bank Reconciliation</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasPermission('gst')): ?>
                <li class="nav-item">
                    <a href="/modules/accounts/gst.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>GST Compliance</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if (hasPermission('tickets')): ?>
                <li class="nav-section">Support</li>
                <li class="nav-item">
                    <a href="/modules/ticketing/list.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 11v4"/><path d="M10 13h4"/></svg>
                        <span>Ticketing System</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission('reports')): ?>
                <li class="nav-section">Reports</li>
                <li class="nav-item">
                    <a href="/modules/reports/index.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                        <span>Reports</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission('activity')): ?>
                <li class="nav-item">
                    <a href="/modules/reports/activity.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        <span>Activity Log</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission('backup')): ?>
                <li class="nav-section">System</li>
                <li class="nav-item">
                    <a href="/modules/backup/index.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        <span>Backup & Restore</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission('settings')): ?>
                <li class="nav-item">
                    <a href="/modules/admin/settings.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span>Settings</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-section">Account</li>
                <li class="nav-item">
                    <a href="/modules/hr/profile.php" class="nav-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>My Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/dashboard.php?logout=1" class="nav-link logout-link" onclick="return confirm('Are you sure you want to logout?')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <main class="main-content">
            <header class="top-bar">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="top-bar-title"><?= APP_NAME ?></div>
                <div class="top-bar-right">
                    <span class="user-greeting">Welcome, <?= getEmployeeName() ?></span>
                    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle Dark Mode">
                        <svg class="sun-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg class="moon-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                    <a href="/dashboard.php?logout=1" class="btn btn-sm btn-danger" onclick="return confirm('Logout?')">Logout</a>
                </div>
            </header>
            
            <div class="content-wrapper">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible" onclick="this.remove()"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible" onclick="this.remove()"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
