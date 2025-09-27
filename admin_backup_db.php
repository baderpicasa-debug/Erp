<?php
require_once __DIR__ . '/config.php';

// التحقق من صلاحيات الأدمن
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die("Unauthorized access.");
}

// إعدادات الأمان
set_time_limit(300); // حد أقصى 5 دقائق
ini_set('memory_limit', '512M'); // حد أقصى للذاكرة

class SecureDatabaseBackup {
    private $conn;
    private $dbConfig;
    
    public function __construct() {
        $this->conn = db();
        $this->dbConfig = [
            'host' => DB_HOST,
            'user' => DB_USER,
            'password' => DB_PASS,
            'database' => DB_NAME
        ];
    }
    
    /**
     * إنشاء نسخة احتياطية آمنة باستخدام MySQL PHP Functions
     */
    public function createSecureBackup() {
        try {
            $filename = $this->generateSecureFilename();
            $backupContent = $this->generateSQLDump();
            
            // تشفير المحتوى (اختياري)
            if (defined('BACKUP_ENCRYPTION_KEY') && !empty(BACKUP_ENCRYPTION_KEY)) {
                $backupContent = $this->encryptBackup($backupContent);
                $filename .= '.encrypted';
            }
            
            $this->outputBackup($filename, $backupContent);
            
        } catch (Exception $e) {
            error_log("Backup Error: " . $e->getMessage());
            http_response_code(500);
            die("Backup failed. Please contact administrator.");
        }
    }
    
    /**
     * توليد اسم ملف آمن
     */
    private function generateSecureFilename() {
        $timestamp = date('Y-m-d_H-i-s');
        $hash = substr(hash('sha256', $this->dbConfig['database'] . time()), 0, 8);
        return "backup_{$this->dbConfig['database']}_{$timestamp}_{$hash}.sql";
    }
    
    /**
     * إنشاء SQL dump باستخدام PHP بدلاً من command line
     */
    private function generateSQLDump() {
        $dump = "-- MySQL Database Backup\n";
        $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- Database: {$this->dbConfig['database']}\n\n";
        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $dump .= "SET time_zone = \"+00:00\";\n\n";
        
        // الحصول على قائمة الجداول
        $tables = $this->getTables();
        
        foreach ($tables as $table) {
            $dump .= $this->dumpTable($table);
        }
        
        $dump .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
        return $dump;
    }
    
    /**
     * الحصول على قائمة الجداول بشكل آمن
     */
    private function getTables() {
        $tables = [];
        $result = $this->conn->query("SHOW TABLES");
        
        if ($result) {
            while ($row = $result->fetch_array(MYSQLI_NUM)) {
                $tables[] = $row[0];
            }
        }
        
        return $tables;
    }
    
    /**
     * عمل dump لجدول واحد
     */
    private function dumpTable($tableName) {
        // تنظيف اسم الجدول لمنع SQL injection
        $tableName = $this->conn->real_escape_string($tableName);
        
        $dump = "\n-- Table structure for table `{$tableName}`\n";
        $dump .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
        
        // الحصول على بنية الجدول
        $createResult = $this->conn->query("SHOW CREATE TABLE `{$tableName}`");
        if ($createResult) {
            $row = $createResult->fetch_array(MYSQLI_NUM);
            $dump .= $row[1] . ";\n\n";
        }
        
        // الحصول على البيانات
        $dump .= "-- Dumping data for table `{$tableName}`\n";
        $dataResult = $this->conn->query("SELECT * FROM `{$tableName}`");
        
        if ($dataResult && $dataResult->num_rows > 0) {
            // الحصول على أسماء الأعمدة
            $fields = $dataResult->fetch_fields();
            $fieldNames = array_map(function($field) { return "`{$field->name}`"; }, $fields);
            
            $dump .= "INSERT INTO `{$tableName}` (" . implode(', ', $fieldNames) . ") VALUES\n";
            
            $values = [];
            while ($row = $dataResult->fetch_array(MYSQLI_ASSOC)) {
                $rowValues = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $rowValues[] = 'NULL';
                    } else {
                        $rowValues[] = "'" . $this->conn->real_escape_string($value) . "'";
                    }
                }
                $values[] = '(' . implode(', ', $rowValues) . ')';
            }
            
            $dump .= implode(",\n", $values) . ";\n";
        } else {
            $dump .= "-- No data to dump\n";
        }
        
        return $dump;
    }
    
    /**
     * تشفير النسخة الاحتياطية (اختياري)
     */
    private function encryptBackup($data) {
        if (!function_exists('openssl_encrypt')) {
            throw new Exception("OpenSSL extension required for encryption");
        }
        
        $key = BACKUP_ENCRYPTION_KEY;
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        // دمج IV مع البيانات المشفرة
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * إرسال النسخة الاحتياطية للمتصفح
     */
    private function outputBackup($filename, $content) {
        // تسجيل العملية
        $this->logBackupActivity();
        
        // إعداد headers للتحميل
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        header('Pragma: no-cache');
        
        // إرسال المحتوى
        echo $content;
    }
    
    /**
     * تسجيل نشاط النسخ الاحتياطي
     */
    private function logBackupActivity() {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'admin_id' => $_SESSION['admin_id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'action' => 'database_backup'
        ];
        
        error_log("SECURITY LOG: " . json_encode($logEntry));
    }
}

// تنفيذ النسخ الاحتياطي
try {
    $backup = new SecureDatabaseBackup();
    $backup->createSecureBackup();
} catch (Exception $e) {
    error_log("Backup System Error: " . $e->getMessage());
    http_response_code(500);
    die("System error occurred. Please try again later.");
}
?>