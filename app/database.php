<?php
// ============================================
// Database Layer - SQLite with PDO
// ============================================

class Database
{
    private static $instance = null;
    private $pdo = null;

    private function __construct()
    {
        $this->connect();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect()
    {
        $dbPath = DB_PATH;
        $dataDir = dirname($dbPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $dsn = "sqlite:{$dbPath}";
        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        // Enable foreign keys
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    public function init()
    {
        $this->createTables();
        $this->seedDefaultData();
    }

    private function createTables()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS system_config (
                key_name VARCHAR(255) PRIMARY KEY,
                key_value TEXT,
                encrypted TINYINT DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_name VARCHAR(255) UNIQUE NOT NULL,
                role_description TEXT,
                permissions TEXT,
                is_system TINYINT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                employee_code VARCHAR(255) UNIQUE NOT NULL,
                email VARCHAR(255),
                phone VARCHAR(50),
                role_id INT,
                is_active TINYINT DEFAULT 1,
                login_attempts INT DEFAULT 0,
                locked_until DATETIME,
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (role_id) REFERENCES roles(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS employees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT UNIQUE,
                employee_code VARCHAR(255) UNIQUE NOT NULL,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                middle_name VARCHAR(255),
                date_of_birth DATE,
                gender VARCHAR(50),
                marital_status VARCHAR(50),
                blood_group VARCHAR(10),
                nationality VARCHAR(100) DEFAULT 'Indian',
                religion VARCHAR(100),
                department VARCHAR(255),
                designation VARCHAR(255),
                date_of_joining DATE,
                employment_type VARCHAR(100),
                work_location VARCHAR(255),
                reporting_to VARCHAR(255),
                shift_timing VARCHAR(255),
                weekly_off VARCHAR(100),
                aadhar_number_encrypted TEXT,
                aadhar_photo TEXT,
                pan_number_encrypted TEXT,
                pan_photo TEXT,
                passport_photo TEXT,
                phone_number VARCHAR(50),
                alternate_phone VARCHAR(50),
                personal_email VARCHAR(255),
                official_email VARCHAR(255),
                emergency_contact_name VARCHAR(255),
                emergency_contact_phone VARCHAR(50),
                emergency_contact_relation VARCHAR(255),
                address_aadhar TEXT,
                address_permanent TEXT,
                address_current TEXT,
                city VARCHAR(255),
                state VARCHAR(255),
                pincode VARCHAR(20),
                highest_education VARCHAR(255),
                university VARCHAR(255),
                year_of_passing VARCHAR(10),
                previous_company VARCHAR(255),
                previous_designation VARCHAR(255),
                years_of_experience VARCHAR(50),
                skills TEXT,
                bank_name VARCHAR(255),
                bank_account_number_encrypted TEXT,
                ifsc_code VARCHAR(50),
                bank_branch VARCHAR(255),
                uan_number_encrypted TEXT,
                esic_number_encrypted TEXT,
                is_active TINYINT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT,
                employee_code VARCHAR(255),
                action VARCHAR(100),
                module VARCHAR(100),
                description TEXT,
                ip_address VARCHAR(50),
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS companies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_name VARCHAR(255) NOT NULL,
                company_code VARCHAR(255) UNIQUE NOT NULL,
                gst_number VARCHAR(50),
                pan_number VARCHAR(50),
                address TEXT,
                city VARCHAR(255),
                state VARCHAR(255),
                pincode VARCHAR(20),
                country VARCHAR(100) DEFAULT 'India',
                phone VARCHAR(50),
                email VARCHAR(255),
                website VARCHAR(255),
                logo TEXT,
                founded_date DATE,
                registration_number VARCHAR(255),
                tax_registration VARCHAR(255),
                industry_type VARCHAR(255),
                no_of_employees INT DEFAULT 0,
                is_active TINYINT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS departments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                department_name VARCHAR(255) UNIQUE NOT NULL,
                department_code VARCHAR(255) UNIQUE NOT NULL,
                description TEXT,
                head_employee_code VARCHAR(255),
                is_active TINYINT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_code VARCHAR(255) UNIQUE NOT NULL,
                customer_name VARCHAR(255) NOT NULL,
                company_name VARCHAR(255),
                gst_number VARCHAR(50),
                phone VARCHAR(50),
                email VARCHAR(255),
                address TEXT,
                city VARCHAR(255),
                state VARCHAR(255),
                pincode VARCHAR(20),
                contact_person VARCHAR(255),
                contact_phone VARCHAR(50),
                lead_source VARCHAR(255),
                status VARCHAR(50) DEFAULT 'active',
                notes TEXT,
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_code VARCHAR(255) UNIQUE NOT NULL,
                subject VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(100),
                priority VARCHAR(50) DEFAULT 'medium',
                status VARCHAR(50) DEFAULT 'open',
                assigned_to VARCHAR(255),
                created_by INT,
                customer_id INT,
                department VARCHAR(255),
                due_date DATE,
                resolution TEXT,
                closed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ticket_replies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id INT,
                user_id INT,
                message TEXT,
                attachment TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ticket_id) REFERENCES tickets(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_number VARCHAR(255) UNIQUE NOT NULL,
                customer_id INT,
                invoice_date DATE,
                due_date DATE,
                subtotal DECIMAL(12,2) DEFAULT 0,
                tax_percentage DECIMAL(5,2) DEFAULT 0,
                tax_amount DECIMAL(12,2) DEFAULT 0,
                discount DECIMAL(12,2) DEFAULT 0,
                total DECIMAL(12,2) DEFAULT 0,
                amount_paid DECIMAL(12,2) DEFAULT 0,
                amount_due DECIMAL(12,2) DEFAULT 0,
                status VARCHAR(50) DEFAULT 'unpaid',
                notes TEXT,
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS invoice_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INT,
                description TEXT,
                quantity DECIMAL(10,2) DEFAULT 1,
                unit_price DECIMAL(12,2) DEFAULT 0,
                total DECIMAL(12,2) DEFAULT 0,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payment_number VARCHAR(255) UNIQUE NOT NULL,
                invoice_id INT,
                customer_id INT,
                payment_date DATE,
                amount DECIMAL(12,2) DEFAULT 0,
                payment_method VARCHAR(100),
                reference_number VARCHAR(255),
                notes TEXT,
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_code VARCHAR(255) UNIQUE NOT NULL,
                lead_name VARCHAR(255) NOT NULL,
                company_name VARCHAR(255),
                phone VARCHAR(50),
                email VARCHAR(255),
                source VARCHAR(100),
                status VARCHAR(50) DEFAULT 'new',
                assigned_to VARCHAR(255),
                notes TEXT,
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS attendance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_code VARCHAR(255),
                attendance_date DATE,
                check_in TIME,
                check_out TIME,
                status VARCHAR(50) DEFAULT 'present',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS leave_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_code VARCHAR(255),
                leave_type VARCHAR(100),
                from_date DATE,
                to_date DATE,
                reason TEXT,
                status VARCHAR(50) DEFAULT 'pending',
                approved_by VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
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

        $this->pdo->exec("
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

        $this->pdo->exec("
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

        $this->pdo->exec("
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

        $this->pdo->exec("
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

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS gst_returns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                return_period VARCHAR(10) NOT NULL,
                filing_date DATE,
                total_sales DECIMAL(14,2) DEFAULT 0,
                total_purchases DECIMAL(14,2) DEFAULT 0,
                gst_collected DECIMAL(14,2) DEFAULT 0,
                gst_paid DECIMAL(14,2) DEFAULT 0,
                net_gst_payable DECIMAL(14,2) DEFAULT 0,
                status VARCHAR(50) DEFAULT 'draft',
                filed_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function seedDefaultData()
    {
        $role = $this->fetchOne("SELECT id FROM roles WHERE role_name = ?", ['Super Admin']);
        if (!$role) {
            $allPermissions = $this->getAllPermissions();
            $this->insert('roles', [
                'role_name' => 'Super Admin',
                'role_description' => 'Full system access with all permissions',
                'permissions' => json_encode($allPermissions),
                'is_system' => 1
            ]);

            $this->insert('roles', [
                'role_name' => 'Admin',
                'role_description' => 'Administrative access with limited system config',
                'permissions' => json_encode($allPermissions),
                'is_system' => 1
            ]);

            $this->insert('roles', [
                'role_name' => 'HR Manager',
                'role_description' => 'Human Resource Management',
                'permissions' => json_encode([
                    'hr' => ['view', 'create', 'edit', 'delete'],
                    'attendance' => ['view', 'create', 'edit'],
                    'leave' => ['view', 'create', 'edit', 'approve'],
                    'reports' => ['view', 'export']
                ]),
                'is_system' => 1
            ]);

            $this->insert('roles', [
                'role_name' => 'Employee',
                'role_description' => 'Standard employee access',
                'permissions' => json_encode([
                    'profile' => ['view', 'edit_own'],
                    'attendance' => ['view_own'],
                    'leave' => ['view_own', 'create'],
                    'tickets' => ['view_own', 'create']
                ]),
                'is_system' => 1
            ]);

            $this->insert('roles', [
                'role_name' => 'Finance Manager',
                'role_description' => 'Financial management',
                'permissions' => json_encode([
                    'invoices' => ['view', 'create', 'edit', 'delete'],
                    'payments' => ['view', 'create', 'edit', 'delete'],
                    'budget' => ['view', 'create', 'edit', 'delete'],
                    'financial_reports' => ['view', 'export'],
                    'reports' => ['view', 'export']
                ]),
                'is_system' => 1
            ]);

            $this->insert('roles', [
                'role_name' => 'Accountant',
                'role_description' => 'Accounting operations',
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
                'is_system' => 1
            ]);

            $this->insert('roles', [
                'role_name' => 'CRM Manager',
                'role_description' => 'Customer Relationship Management',
                'permissions' => json_encode([
                    'crm' => ['view', 'create', 'edit', 'delete'],
                    'leads' => ['view', 'create', 'edit', 'delete'],
                    'customers' => ['view', 'create', 'edit', 'delete'],
                    'reports' => ['view', 'export']
                ]),
                'is_system' => 1
            ]);
        }

        $user = $this->fetchOne("SELECT id FROM users WHERE username = ?", ['admin']);
        if (!$user) {
            $role = $this->fetchOne("SELECT id FROM roles WHERE role_name = 'Super Admin'");
            $enc = Encryption::getInstance();
            $empCode = $enc->generateEmployeeCode('ADM');

            $this->insert('users', [
                'username' => 'admin',
                'password_hash' => $enc->hashPassword('admin@123'),
                'employee_code' => $empCode,
                'email' => 'admin@erp-system.local',
                'phone' => '0000000000',
                'role_id' => $role['id'],
                'is_active' => 1
            ]);

            $user = $this->fetchOne("SELECT id FROM users WHERE username = ?", ['admin']);

            $this->insert('employees', [
                'user_id' => $user['id'],
                'employee_code' => $empCode,
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'department' => 'Administration',
                'designation' => 'System Administrator',
                'date_of_joining' => date('Y-m-d'),
                'employment_type' => 'Permanent',
                'is_active' => 1
            ]);

            $this->insert('departments', [
                'department_name' => 'Administration',
                'department_code' => 'DEPT-ADM-001',
                'description' => 'System Administration Department'
            ]);

            $this->insert('departments', [
                'department_name' => 'Human Resources',
                'department_code' => 'DEPT-HR-001',
                'description' => 'Human Resource Management'
            ]);

            $this->insert('departments', [
                'department_name' => 'Finance',
                'department_code' => 'DEPT-FIN-001',
                'description' => 'Financial Management'
            ]);

            $this->insert('departments', [
                'department_name' => 'Accounts',
                'department_code' => 'DEPT-ACC-001',
                'description' => 'Accounting operations'
            ]);

            $this->insert('departments', [
                'department_name' => 'Sales & Marketing',
                'department_code' => 'DEPT-SLS-001',
                'description' => 'Sales and Marketing (CRM)'
            ]);

            $this->insert('departments', [
                'department_name' => 'IT Support',
                'department_code' => 'DEPT-IT-001',
                'description' => 'IT Support and Ticketing'
            ]);
        }
    }

    public function getAllPermissions()
    {
        return [
            'dashboard' => ['view'],
            'admin' => ['view', 'create', 'edit', 'delete'],
            'users' => ['view', 'create', 'edit', 'delete'],
            'roles' => ['view', 'create', 'edit', 'delete'],
            'hr' => ['view', 'create', 'edit', 'delete'],
            'employees' => ['view', 'create', 'edit', 'delete'],
            'attendance' => ['view', 'create', 'edit', 'delete'],
            'leave' => ['view', 'create', 'edit', 'delete', 'approve'],
            'crm' => ['view', 'create', 'edit', 'delete'],
            'leads' => ['view', 'create', 'edit', 'delete'],
            'customers' => ['view', 'create', 'edit', 'delete'],
            'accounts' => ['view', 'create', 'edit', 'delete'],
            'finance' => ['view', 'create', 'edit', 'delete'],
            'invoices' => ['view', 'create', 'edit', 'delete'],
            'payments' => ['view', 'create', 'edit', 'delete'],
            'journal' => ['view', 'create', 'edit', 'delete', 'approve'],
            'ledger' => ['view', 'export'],
            'trial_balance' => ['view', 'export'],
            'bank_reconciliation' => ['view', 'create', 'edit'],
            'gst' => ['view', 'create', 'edit', 'file'],
            'chart_of_accounts' => ['view', 'create', 'edit', 'delete'],
            'budget' => ['view', 'create', 'edit', 'delete'],
            'financial_reports' => ['view', 'export'],
            'accounts_reports' => ['view', 'export'],
            'tickets' => ['view', 'create', 'edit', 'delete', 'assign'],
            'reports' => ['view', 'create', 'export'],
            'backup' => ['view', 'create', 'restore'],
            'settings' => ['view', 'edit'],
            'company' => ['view', 'create', 'edit'],
            'departments' => ['view', 'create', 'edit', 'delete'],
            'activity' => ['view', 'export'],
            'profile' => ['view', 'edit_own'],
            'id_card' => ['view', 'generate']
        ];
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function fetchColumn($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    public function insert($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $sets = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$sets} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollback()
    {
        return $this->pdo->rollBack();
    }

    public function getLastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function backup()
    {
        $backupDir = BACKUP_PATH;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $filename = 'erp_backup_' . date('Y-m-d_H-i-s') . '.db';
        $backupFile = $backupDir . '/' . $filename;

        if (!file_exists(DB_PATH)) {
            return false;
        }
        if (!copy(DB_PATH, $backupFile)) {
            return false;
        }
        return $backupFile;
    }

    public function restore($backupFile)
    {
        if (!file_exists($backupFile)) {
            return false;
        }
        // Copy the backup file into place
        if (!copy($backupFile, DB_PATH)) {
            return false;
        }
        return true;
    }

    public function getTableSize()
    {
        if (!file_exists(DB_PATH)) {
            return 0;
        }
        return filesize(DB_PATH);
    }

    public function logActivity($userId, $empCode, $action, $module, $description = '')
    {
        $this->insert('activity_logs', [
            'user_id' => $userId,
            'employee_code' => $empCode,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    public function search($table, $columns, $searchTerm, $limit = 50)
    {
        $conditions = [];
        $params = [];
        foreach ($columns as $col) {
            $conditions[] = "{$col} LIKE ?";
            $params[] = "%{$searchTerm}%";
        }
        $where = implode(' OR ', $conditions);
        $sql = "SELECT * FROM {$table} WHERE {$where} LIMIT {$limit}";
        return $this->fetchAll($sql, $params);
    }
}
