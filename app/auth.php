<?php
// ============================================
// Authentication & Authorization System
// ============================================

class Auth
{
    private static $instance = null;
    private $user = null;
    private $permissions = [];

    private function __construct()
    {
        if (isset($_SESSION['user_id'])) {
            $this->loadUser($_SESSION['user_id']);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function login($username, $password)
    {
        $db = Database::getInstance();
        $enc = Encryption::getInstance();

        $user = $db->fetchOne(
            "SELECT u.*, r.role_name, r.permissions as role_permissions 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.username = ?",
            [$username]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Account is deactivated. Contact administrator.'];
        }

        // Check lockout
        if ($user['locked_until']) {
            $lockTime = strtotime($user['locked_until']);
            if ($lockTime > time()) {
                $remaining = ceil(($lockTime - time()) / 60);
                return ['success' => false, 'message' => "Account locked. Try again in {$remaining} minutes."];
            }
        }

        if (!$enc->verifyPassword($password, $user['password_hash'])) {
            $attempts = $user['login_attempts'] + 1;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $db->update('users', [
                    'login_attempts' => $attempts,
                    'locked_until' => date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME)
                ], 'id = ?', [$user['id']]);
                return ['success' => false, 'message' => 'Account locked for 15 minutes due to too many failed attempts.'];
            }
            $db->update('users', ['login_attempts' => $attempts], 'id = ?', [$user['id']]);
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Login success
        $db->update('users', [
            'login_attempts' => 0,
            'locked_until' => null,
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['employee_code'] = $user['employee_code'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $this->user = $user;
        $this->permissions = json_decode($user['role_permissions'] ?? '{}', true);

        $db->logActivity($user['id'], $user['employee_code'], 'login', 'auth', 'User logged in');

        return ['success' => true, 'message' => 'Login successful'];
    }

    public function logout()
    {
        if ($this->isLoggedIn()) {
            $db = Database::getInstance();
            $db->logActivity($_SESSION['user_id'], $_SESSION['employee_code'], 'logout', 'auth', 'User logged out');
        }
        session_destroy();
        $this->user = null;
        $this->permissions = [];
    }

    public function isLoggedIn()
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }

        // Refresh session
        $_SESSION['login_time'] = time();
        return true;
    }

    private function loadUser($userId)
    {
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT u.*, r.role_name, r.permissions as role_permissions 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ?",
            [$userId]
        );

        if ($user) {
            $this->user = $user;
            $this->permissions = json_decode($user['role_permissions'] ?? '{}', true);
        }
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getUserId()
    {
        return $this->user['id'] ?? null;
    }

    public function getEmployeeCode()
    {
        return $this->user['employee_code'] ?? null;
    }

    public function getRoleName()
    {
        return $this->user['role_name'] ?? null;
    }

    public function hasPermission($module, $action = 'view')
    {
        if ($this->getRoleName() === 'Super Admin') {
            return true;
        }

        if (!isset($this->permissions[$module])) {
            return false;
        }

        $perm = $this->permissions[$module];

        // New format: ['actions' => [...], 'scope' => 'all|own']
        if (is_array($perm) && isset($perm['actions'])) {
            return in_array($action, $perm['actions']);
        }

        // Old format: flat array of action strings
        if (is_array($perm) && isset($perm[0])) {
            return in_array($action, $perm);
        }

        return false;
    }

    public function getScope($module)
    {
        if ($this->getRoleName() === 'Super Admin') {
            return 'all';
        }

        if (!isset($this->permissions[$module])) {
            return 'all';
        }

        $perm = $this->permissions[$module];

        // New format with explicit scope
        if (is_array($perm) && isset($perm['scope'])) {
            return $perm['scope'];
        }

        // Old or unknown format — default to 'all' for safety
        return 'all';
    }

    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            header('Location: /index.php');
            exit;
        }
    }

    public function requirePermission($module, $action = 'view')
    {
        $this->requireLogin();
        if (!$this->hasPermission($module, $action)) {
            http_response_code(403);
            $_SESSION['error'] = 'Access denied. You do not have permission for this action.';
            header('Location: /dashboard.php');
            exit;
        }
    }

    public function checkCSRF($token)
    {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Invalid security token. Please try again.';
            header('Location: /dashboard.php');
            exit;
        }
    }

    public function isAdmin()
    {
        $role = $this->getRoleName();
        return in_array($role, ['Super Admin', 'Admin']);
    }
}
