<?php
/**
 * Migration Script: Separate Finance & Accounts into two distinct departments
 * Run this once after updating database.php
 */
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
$pdo = $db->getPdo();

echo "=== ERP System: Finance & Accounts Migration ===\n\n";

try {
    $db->beginTransaction();

    // 1. Create new tables
    echo "Creating chart_of_accounts table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chart_of_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_code VARCHAR(255) UNIQUE NOT NULL,
            account_name VARCHAR(255) NOT NULL,
            account_type VARCHAR(100) NOT NULL,
            parent_code VARCHAR(255),
            is_active TINYINT DEFAULT 1,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
    ");

    echo "Creating journal_entries table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS journal_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_number VARCHAR(255) UNIQUE NOT NULL,
            entry_date DATE NOT NULL,
            description TEXT,
            reference_type VARCHAR(100),
            reference_id INT,
            status VARCHAR(50) DEFAULT 'draft',
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
    ");

    echo "Creating journal_entry_items table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS journal_entry_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            journal_entry_id INT,
            account_code VARCHAR(255),
            debit_amount DECIMAL(12,2) DEFAULT 0,
            credit_amount DECIMAL(12,2) DEFAULT 0,
            description TEXT,
            FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE
            )
    ");

    echo "Creating budgets table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS budgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_code VARCHAR(255) UNIQUE NOT NULL,
            budget_name VARCHAR(255) NOT NULL,
            fiscal_year VARCHAR(10) NOT NULL,
            department VARCHAR(255),
            total_amount DECIMAL(14,2) DEFAULT 0,
            amount_used DECIMAL(14,2) DEFAULT 0,
            status VARCHAR(50) DEFAULT 'active',
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
    ");

    echo "Creating bank_reconciliation table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bank_reconciliation (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_code VARCHAR(255),
            statement_date DATE NOT NULL,
            ending_balance DECIMAL(14,2) DEFAULT 0,
            reconciled_balance DECIMAL(14,2) DEFAULT 0,
            difference DECIMAL(14,2) DEFAULT 0,
            status VARCHAR(50) DEFAULT 'pending',
            reconciled_at DATETIME,
            reconciled_by INT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
    ");

    // 2. Seed chart of accounts (skip if already exists)
    $coaCheck = $db->fetchOne("SELECT id FROM chart_of_accounts LIMIT 1");
    if (!$coaCheck) {
        echo "Seeding Chart of Accounts...\n";
        $accounts = [
            // Assets (1000-1999)
            ['1100', 'Cash & Bank', 'Asset', null],
            ['1101', 'Cash in Hand', 'Asset', '1100'],
            ['1102', 'Bank Account', 'Asset', '1100'],
            ['1200', 'Accounts Receivable', 'Asset', null],
            ['1300', 'Inventory', 'Asset', null],
            ['1400', 'Fixed Assets', 'Asset', null],
            ['1401', 'Office Equipment', 'Asset', '1400'],
            ['1402', 'Furniture & Fixtures', 'Asset', '1400'],
            ['1403', 'Vehicles', 'Asset', '1400'],
            // Liabilities (2000-2999)
            ['2100', 'Accounts Payable', 'Liability', null],
            ['2200', 'Loans & Borrowings', 'Liability', null],
            ['2300', 'Tax Payable', 'Liability', null],
            ['2301', 'GST Payable', 'Liability', '2300'],
            ['2302', 'TDS Payable', 'Liability', '2300'],
            // Equity (3000-3999)
            ['3100', 'Capital', 'Equity', null],
            ['3200', 'Retained Earnings', 'Equity', null],
            // Revenue (4000-4999)
            ['4100', 'Sales Revenue', 'Revenue', null],
            ['4200', 'Service Revenue', 'Revenue', null],
            ['4300', 'Other Income', 'Revenue', null],
            // Expenses (5000-5999)
            ['5100', 'Salaries & Wages', 'Expense', null],
            ['5200', 'Rent & Utilities', 'Expense', null],
            ['5300', 'Office Expenses', 'Expense', null],
            ['5400', 'Travel & Conveyance', 'Expense', null],
            ['5500', 'Professional Fees', 'Expense', null],
            ['5600', 'Depreciation', 'Expense', null],
            ['5700', 'Tax Expenses', 'Expense', null],
        ];
        foreach ($accounts as $a) {
            $db->insert('chart_of_accounts', [
                'account_code' => $a[0],
                'account_name' => $a[1],
                'account_type' => $a[2],
                'parent_code' => $a[3],
                'is_active' => 1
            ]);
        }
    }

    // 3. Split "Finance & Accounts" department into "Finance" and "Accounts"
    $oldDept = $db->fetchOne("SELECT id FROM departments WHERE department_name = 'Finance & Accounts'");
    if ($oldDept) {
        echo "Splitting 'Finance & Accounts' department...\n";

        // Check if new departments already exist
        $finDept = $db->fetchOne("SELECT id FROM departments WHERE department_name = 'Finance'");
        if (!$finDept) {
            $db->insert('departments', [
                'department_name' => 'Finance',
                'department_code' => 'DEPT-FIN-002',
                'description' => 'Financial Management, Invoicing, Payments, Budgeting'
            ]);
            $finDept = $db->fetchOne("SELECT id FROM departments WHERE department_name = 'Finance'");
        }

        $accDept = $db->fetchOne("SELECT id FROM departments WHERE department_name = 'Accounts'");
        if (!$accDept) {
            $db->insert('departments', [
                'department_name' => 'Accounts',
                'department_code' => 'DEPT-ACC-001',
                'description' => 'Accounting, Journal Entries, Ledger, GST, Bank Reconciliation'
            ]);
            $accDept = $db->fetchOne("SELECT id FROM departments WHERE department_name = 'Accounts'");
        }

        // Reassign employees
        // Neha Mehta (Accountant) → Accounts
        // Rohan Shah → Finance
        // Kavya Iyer → Accounts
        // Arjun Desai → Finance
        echo "Reassigning employees to new departments...\n";

        $neha = $db->fetchOne("SELECT id, employee_code FROM employees WHERE first_name = 'Neha' AND last_name = 'Mehta'");
        if ($neha) {
            $db->update('employees', ['department' => 'Accounts'], 'id = ?', [$neha['id']]);
            echo "  Neha Mehta → Accounts\n";
        }

        $rohan = $db->fetchOne("SELECT id, employee_code FROM employees WHERE first_name = 'Rohan' AND last_name = 'Shah'");
        if ($rohan) {
            $db->update('employees', ['department' => 'Finance'], 'id = ?', [$rohan['id']]);
            echo "  Rohan Shah → Finance\n";
        }

        $kavya = $db->fetchOne("SELECT id, employee_code FROM employees WHERE first_name = 'Kavya' AND last_name = 'Iyer'");
        if ($kavya) {
            $db->update('employees', ['department' => 'Accounts'], 'id = ?', [$kavya['id']]);
            echo "  Kavya Iyer → Accounts\n";
        }

        $arjun = $db->fetchOne("SELECT id, employee_code FROM employees WHERE first_name = 'Arjun' AND last_name = 'Desai'");
        if ($arjun) {
            $db->update('employees', ['department' => 'Finance'], 'id = ?', [$arjun['id']]);
            echo "  Arjun Desai → Finance\n";
        }

        // Deactivate old department
        // Actually keep it but mark inactive, or just leave it for reference
        echo "  'Finance & Accounts' department retained for reference\n";
    } else {
        echo "Finance & Accounts department not found, creating new ones...\n";
        $finDept = $db->fetchOne("SELECT id FROM departments WHERE department_name = 'Finance'");
        if (!$finDept) {
            $db->insert('departments', [
                'department_name' => 'Finance',
                'department_code' => 'DEPT-FIN-002',
                'description' => 'Financial Management, Invoicing, Payments, Budgeting'
            ]);
        }
        $accDept = $db->fetchOne("SELECT id FROM departments WHERE department_name = 'Accounts'");
        if (!$accDept) {
            $db->insert('departments', [
                'department_name' => 'Accounts',
                'department_code' => 'DEPT-ACC-001',
                'description' => 'Accounting, Journal Entries, Ledger, GST, Bank Reconciliation'
            ]);
        }
    }

    // 4. Update roles
    // Update Accountant role with Accounts-specific permissions
    $accountantRole = $db->fetchOne("SELECT id, permissions FROM roles WHERE role_name = 'Accountant'");
    if ($accountantRole) {
        echo "Updating Accountant role permissions...\n";
        $db->update('roles', [
            'permissions' => json_encode([
                'journal' => ['view', 'create', 'edit'],
                'ledger' => ['view'],
                'trial_balance' => ['view', 'export'],
                'bank_reconciliation' => ['view', 'create', 'edit'],
                'gst' => ['view', 'create', 'edit', 'file'],
                'chart_of_accounts' => ['view', 'create', 'edit'],
                'accounts_reports' => ['view', 'export'],
                'reports' => ['view', 'export']
            ]),
            'role_description' => 'Accounting operations: Journal, Ledger, Trial Balance, GST, Bank Reconciliation'
        ], 'id = ?', [$accountantRole['id']]);
    }

    // Create Finance Manager role
    $fmRole = $db->fetchOne("SELECT id FROM roles WHERE role_name = 'Finance Manager'");
    if (!$fmRole) {
        echo "Creating Finance Manager role...\n";
        $db->insert('roles', [
            'role_name' => 'Finance Manager',
            'role_description' => 'Financial management: Invoicing, Payments, Budget, Financial Reports',
            'permissions' => json_encode([
                'invoices' => ['view', 'create', 'edit', 'delete'],
                'payments' => ['view', 'create', 'edit', 'delete'],
                'budget' => ['view', 'create', 'edit', 'delete'],
                'financial_reports' => ['view', 'export'],
                'reports' => ['view', 'export']
            ]),
            'is_system' => 1
        ]);
    }

    // Update Neha Mehta's user role to Accountant (already correct, verify)
    if ($neha) {
        $nehaUser = $db->fetchOne("SELECT id, role_id FROM users WHERE employee_code = ?", [$neha['employee_code']]);
        if ($nehaUser && $accountantRole) {
            $currentRole = $db->fetchOne("SELECT role_name FROM roles WHERE id = ?", [$nehaUser['role_id']]);
            if ($currentRole && $currentRole['role_name'] !== 'Accountant') {
                $db->update('users', ['role_id' => $accountantRole['id']], 'id = ?', [$nehaUser['id']]);
                echo "  Updated Neha's role to Accountant\n";
            }
        }
    }

    // Assign Rohan Shah as Finance Manager
    if ($rohan) {
        $rohanUser = $db->fetchOne("SELECT id FROM users WHERE employee_code = ?", [$rohan['employee_code']]);
        if ($rohanUser) {
            $fmRole = $db->fetchOne("SELECT id FROM roles WHERE role_name = 'Finance Manager'");
            if ($fmRole) {
                $currentRole = $db->fetchOne("SELECT role_name FROM roles r JOIN users u ON r.id = u.role_id WHERE u.id = ?", [$rohanUser['id']]);
                if ($currentRole && $currentRole['role_name'] !== 'Finance Manager') {
                    $db->update('users', ['role_id' => $fmRole['id']], 'id = ?', [$rohanUser['id']]);
                    echo "  Rohan Shah → Finance Manager\n";
                }
            }
        }
    }

    $db->commit();
    echo "\n=== Migration completed successfully! ===\n";

} catch (Exception $e) {
    $db->rollback();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
