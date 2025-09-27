<?php
require_once __DIR__ . '/config.php';

// التحقق من تسجيل الدخول
if (empty($_SESSION['is_employee']) && empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: order.php?error=invalid_method');
    exit;
}

// CSRF Protection
if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    logError("CSRF token validation failed for order save - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    die("Invalid request token");
}

$conn = db();

// التحقق من صحة البيانات الأساسية
$employee_id = (int)($_POST['employee'] ?? 0);
$branch = sanitizeInput($_POST['branch'] ?? '');
$items = $_POST['item'] ?? [];
$qtys = $_POST['qty'] ?? [];
$units = $_POST['unit'] ?? [];

// التحقق من صحة employee_id
if ($employee_id <= 0) {
    logError("Invalid employee_id in order save: $employee_id");
    header('Location: order.php?error=invalid_employee');
    exit;
}

// التحقق من صحة branch
if (empty($branch)) {
    logError("Empty branch in order save for employee: $employee_id");
    header('Location: order.php?error=invalid_branch');
    exit;
}

// التحقق من صحة البيانات المرسلة
if (!is_array($items) || !is_array($qtys) || !is_array($units)) {
    logError("Invalid arrays in order save for employee: $employee_id");
    header('Location: order.php?error=invalid_data');
    exit;
}

if (empty($items) || count($items) !== count($qtys) || count($items) !== count($units)) {
    logError("Mismatched arrays in order save for employee: $employee_id");
    header('Location: order.php?error=invalid_data_structure');
    exit;
}

// التحقق من وجود الموظف في قاعدة البيانات
$stmt_check = $conn->prepare("SELECT id FROM employees WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt_check->bind_param("i", $employee_id);
$stmt_check->execute();
$employee_exists = $stmt_check->get_result()->num_rows > 0;
$stmt_check->close();

if (!$employee_exists) {
    logError("Attempt to save order for non-existent or inactive employee: $employee_id");
    header('Location: order.php?error=invalid_employee');
    exit;
}

$conn->begin_transaction();
$items_saved = 0;
$total_qty = 0;

try {
    // 1. احفظ الهيدر
    $stmt = $conn->prepare("INSERT INTO store_orders (employee_id, branch, order_date) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $employee_id, $branch);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert order header: " . $stmt->error);
    }
    
    $order_id = $stmt->insert_id;
    $stmt->close();

    // 2. احفظ الأسطر
    $stmt = $conn->prepare("INSERT INTO store_order_items (order_id, item_id, qty, unit) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare order items statement: " . $conn->error);
    }

    for ($i = 0; $i < count($items); $i++) {
        $item_id = (int)$items[$i];
        $qty = (float)$qtys[$i];
        $unit = sanitizeInput($units[$i]);
        
        // التحقق من صحة البيانات
        if ($item_id <= 0) {
            continue; // تجاهل العناصر غير الصالحة
        }
        
        if ($qty <= 0) {
            continue; // تجاهل الكميات غير الصالحة
        }
        
        if (empty($unit)) {
            $unit = 'Piece'; // وحدة افتراضية
        }
        
        // التحقق من وجود العنصر في قاعدة البيانات
        $stmt_item_check = $conn->prepare("SELECT id FROM inventory_items WHERE id = ? LIMIT 1");
        $stmt_item_check->bind_param("i", $item_id);
        $stmt_item_check->execute();
        $item_exists = $stmt_item_check->get_result()->num_rows > 0;
        $stmt_item_check->close();
        
        if (!$item_exists) {
            logError("Attempt to order non-existent item: $item_id by employee: $employee_id");
            continue; // تجاهل العناصر غير الموجودة
        }

        $stmt->bind_param("iids", $order_id, $item_id, $qty, $unit);
        
        if ($stmt->execute()) {
            $items_saved++;
            $total_qty += $qty;
        } else {
            logError("Failed to insert order item: item_id=$item_id, order_id=$order_id - " . $stmt->error);
        }
    }
    
    $stmt->close();
    
    if ($items_saved > 0) {
        $conn->commit();
        logError("Order saved successfully - Employee: $employee_id, Branch: $branch, Order ID: $order_id, Items: $items_saved, Total Qty: $total_qty");
        header("Location: order.php?success=1");
    } else {
        $conn->rollback();
        logError("No valid items to save in order for employee: $employee_id");
        header("Location: order.php?error=no_valid_items");
    }
    
} catch (Exception $e) {
    $conn->rollback();
    logError("Order save failed for employee $employee_id: " . $e->getMessage());
    header("Location: order.php?error=save_failed");
} finally {
    $conn->close();
}

exit;
?>