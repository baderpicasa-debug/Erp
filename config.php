<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تحميل متغيرات البيئة - النسخة المبسطة المجربة
$lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if (!empty($line) && strpos($line, '=') !== false && strpos($line, '#') !== 0) {
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . "=" . trim($value));
    }
}

// تعريف الثوابت الأساسية
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');
define('ADMIN_USER', $_ENV['ADMIN_USER'] ?? 'admin');
define('ADMIN_PASS_HASH', $_ENV['ADMIN_PASS_HASH'] ?? '');
define('WA_PHONE', $_ENV['WA_PHONE'] ?? '');
define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? 'false') === 'true');

// إعدادات النسخ الاحتياطي الآمنة
define('BACKUP_ENCRYPTION_KEY', hash('sha256', $_ENV['BACKUP_SECRET'] ?? 'default-secret-change-this'));
define('BACKUP_MAX_EXECUTION_TIME', 300); // 5 دقائق
define('BACKUP_MEMORY_LIMIT', '512M');
define('BACKUP_LOG_ENABLED', true);
define('BACKUP_ENCRYPTION_ENABLED', !empty($_ENV['BACKUP_SECRET']));

// الجداول المسموح بنسخها (whitelist)
define('ALLOWED_BACKUP_TABLES', [
    'admins',
    'employees', 
    'employees_auth',
    'inventory_items',
    'store_orders',
    'store_order_items',
    'branch_inventory_logs',
    'dictionary'
]);

// التحقق من المتغيرات المطلوبة
if (empty(DB_HOST) || empty(DB_USER) || empty(DB_PASS) || empty(DB_NAME)) {
    die("Missing database configuration");
}

// دالة قاعدة البيانات المحسنة
function db() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
// في config.php، غير إعدادات الأخطاء للإنتاج:
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
            }
            $conn->set_charset('utf8mb4');
            $conn->query("SET time_zone = '+03:00'");
        } catch (Exception $e) {
            if (APP_DEBUG) {
                die("Database error: " . $e->getMessage());
            } else {
                error_log("Database error: " . $e->getMessage());
                die("Database connection failed.");
            }
        }
    }
    return $conn;
}

// دوال الأمان
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateCSRF() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRF($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// دالة للتحقق من صلاحية أسماء الجداول
function isValidTableName($tableName) {
    return in_array($tableName, ALLOWED_BACKUP_TABLES) && 
           preg_match('/^[a-zA-Z0-9_]+$/', $tableName);
}

// دالة تسجيل أنشطة النسخ الاحتياطي
function logBackupActivity($action, $details = []) {
    if (!BACKUP_LOG_ENABLED) return;
    
    $logEntry = [
        'timestamp' => date('c'),
        'action' => $action,
        'admin_id' => $_SESSION['admin_id'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/backup_' . date('Y-m-d') . '.log';
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

// إعداد الجلسة الآمنة - نسخة مبسطة
function setupSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // إعدادات أمان شاملة
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1); // HTTPS only
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.regenerate_id', 1);
        
        session_start();
        
        // تجديد Session ID دوريًا
        if (!isset($_SESSION['last_regeneration'])) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

// دالة تسجيل الأخطاء
function logError($message) {
    $logDir = 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logMessage = date('Y-m-d H:i:s') . " - " . $message . PHP_EOL;
    error_log($logMessage, 3, $logDir . '/error.log');
}

// بدء الجلسة
setupSecureSession();
?>