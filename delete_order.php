<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['is_admin'])) {
  header('Location: admin_login.php');
  exit;
}

$conn = db();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
  // حذف الأسطر المرتبطة
  $stmt = $conn->prepare("DELETE FROM store_order_items WHERE order_id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();

  // حذف الهيدر
  $stmt = $conn->prepare("DELETE FROM store_orders WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
}

$conn->close();
header("Location: admin_orders.php");
exit;
?>