<?php
require_once __DIR__ . '/config.php';

// ✅ تحقق من صلاحية الأدمن
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die("❌ Unauthorized access.");
}

// ✅ تحقق من التوكن المؤقت المرسل عبر الرابط (GET)
// هذا التوكن يضمن أن الطلب جاء من صفحة التأكيد
if (empty($_GET['token']) || $_GET['token'] !== ($_SESSION['temp_clear_token'] ?? '')) {
    http_response_code(400);
    die("❌ Unauthorized or expired request.");
}

// ✅ مسح التوكن المؤقت بعد استخدامه لمرة واحدة
unset($_SESSION['temp_clear_token']);

$conn = db();

// ✅ قائمة الجداول المسموح حذفها (whitelist)
$tables_to_clear = [
    'store_order_items',
    'store_orders',
    'branch_inventory_logs',
    'employees', 
    'inventory_items',
    'branches'
];

// Disable foreign key checks to allow truncation
$conn->query("SET FOREIGN_KEY_CHECKS = 0;");

foreach ($tables_to_clear as $table) {
    // ✅ تحقق إضافي لضمان أن اسم الجدول لا يحتوي على حروف ضارة
    if (preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        $conn->query("TRUNCATE TABLE `$table`");
    }
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
$conn->close();

error_log("Database cleared by admin ID: " . $_SESSION['admin_id']);

header("Location: admin_settings.php?success=clear");
exit;