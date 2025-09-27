<?php
require_once __DIR__ . '/config.php';

// تحقق من تسجيل الدخول كأدمن
if (empty($_SESSION['is_admin'])) {
  header('Location: admin_login.php');
  exit;
}

$conn = db();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
  $stmt = $conn->prepare("DELETE FROM branch_inventory_logs WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
}

$conn->close();
header("Location: admin_inventory.php");
exit;