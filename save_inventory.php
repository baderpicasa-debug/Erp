<?php
require_once __DIR__ . '/config.php';

// التحقق من تسجيل الدخول
if (empty($_SESSION['is_employee']) && empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: branch_inventory.php?error=invalid_method');
    exit;
}

// CSRF Protection
if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    logError("CSRF token validation failed for inventory save - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    die("Invalid request token");
}

$conn = db();
$employee_id = (int)($_POST['employee_id'] ?? 0);
$branch = sanitizeInput($_POST['branch'] ?? '');
$qtys = $_POST['qty'] ?? [];

// التحقق من صحة البيانات الأساسية
if ($employee_id <= 0) {
    logError("Invalid employee_id in inventory save: $employee_id");
    header('Location: branch_inventory.php?error=invalid_employee');
    exit;
}

if (empty($branch)) {
    logError("Empty branch in inventory save for employee: $employee_id");
    header('Location: branch_inventory.php?error=invalid_branch');
    exit;
}

// التحقق من صحة البيانات المرسلة
if (!is_array($qtys) || empty($qtys)) {
    logError("Invalid quantities data for employee: $employee_id");
    header('Location: branch_inventory.php?error=no_quantities');
    exit;
}

// التحقق من صحة employee_id في قاعدة البيانات
$stmt_check = $conn->prepare("SELECT id FROM employees WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt_check->bind_param("i", $employee_id);
$stmt_check->execute();
$employee_exists = $stmt_check->get_result()->num_rows > 0;
$stmt_check->close();

if (!$employee_exists) {
    logError("Attempt to save inventory for non-existent or inactive employee: $employee_id");
    header('Location: branch_inventory.php?error=invalid_employee');
    exit;
}

$items_saved = 0;
$total_qty = 0;

if ($employee_id > 0 && $branch !== '') {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("
            INSERT INTO branch_inventory_logs 
            (employee_id, branch_id, item_id, qty_change, note, created_at) 
            VALUES (?, ?, ?, ?, '', NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }

        foreach ($qtys as $item_id => $qty) {
            $item_id = (int)$item_id;
            $qty = (int)$qty;
            
            // التحقق من صحة item_id
            if ($item_id <= 0) {
                continue; // تجاهل العناصر غير الصالحة
            }
            
            if ($qty > 0) {
                // التحقق من وجود العنصر في قاعدة البيانات
                $stmt_item_check = $conn->prepare("SELECT id FROM inventory_items WHERE id = ? LIMIT 1");
                $stmt_item_check->bind_param("i", $item_id);
                $stmt_item_check->execute();
                $item_exists = $stmt_item_check->get_result()->num_rows > 0;
                $stmt_item_check->close();
                
                if (!$item_exists) {
                    logError("Attempt to save inventory for non-existent item: $item_id by employee: $employee_id");
                    continue; // تجاهل العناصر غير الموجودة
                }
                
                $stmt->bind_param("isii", $employee_id, $branch, $item_id, $qty);
                if ($stmt->execute()) {
                    $items_saved++;
                    $total_qty += $qty;
                } else {
                    logError("Failed to insert inventory record for item $item_id, employee $employee_id: " . $stmt->error);
                }
            }
        }
        
        $stmt->close();
        
        if ($items_saved > 0) {
            $conn->commit();
            logError("Inventory saved successfully - Employee: $employee_id, Branch: $branch, Items: $items_saved, Total Qty: $total_qty");
            header("Location: branch_inventory.php?success=1");
        } else {
            $conn->rollback();
            logError("No valid items to save for employee: $employee_id");
            header("Location: branch_inventory.php?error=no_valid_items");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        logError("Inventory save failed for employee $employee_id: " . $e->getMessage());
        header("Location: branch_inventory.php?error=save_failed");
    }
} else {
    logError("Invalid data for inventory save - Employee: $employee_id, Branch: '$branch'");
    header("Location: branch_inventory.php?error=invalid_data");
}

$conn->close();
exit;
?>