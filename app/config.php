<?php
// ============================================
// ERP System - Main Configuration
// ============================================

define('APP_NAME', 'ERP System');
define('APP_VERSION', '1.0.0');
define('APP_COMPANY', 'Enterprise Suite');
define('TIMEZONE', 'Asia/Kolkata');
define('DATE_FORMAT', 'd-m-Y');
define('TIME_FORMAT', 'H:i:s');

date_default_timezone_set(TIMEZONE);

// Security
define('MASTER_KEY_HASH', 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Database (SQLite)
define('DB_PATH', __DIR__ . '/data/erp_system.db');
define('DB_NAME', 'erp_system');
define('DB_ENCRYPTION_KEY', 'Erp@2024#Secure!Key$For%Database');

// Paths
define('BASE_PATH', __DIR__);
define('MODULES_PATH', __DIR__ . '/modules');
define('DATA_PATH', __DIR__ . '/data');
define('UPLOAD_PATH', __DIR__ . '/data/uploads');
define('BACKUP_PATH', __DIR__ . '/data/backups');
define('INCLUDES_PATH', __DIR__ . '/includes');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', DATA_PATH . '/error.log');

// Session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Auto load
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/encryption.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

// Initialize database
$db = Database::getInstance();
$db->init();
