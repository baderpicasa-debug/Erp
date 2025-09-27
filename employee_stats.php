<?php
require_once __DIR__ . '/config.php';

// Require login
if (empty($_SESSION['is_admin'])) {
  header('Location: login.php');
  exit;
}

$conn = db();
$employee_id = (int)($_GET['id'] ?? 0);

if (!$employee_id) {
  die("❌ Employee not specified");
}

/* ---------------- Fetch employee info ---------------- */
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) {
  die("❌ Employee not found");
}

$employee_name = $emp['name'];

/* ---------------- Handle CSV Export ---------------- */
if (isset($_GET['export'])) {
  if ($_GET['export'] === 'orders') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders_' . $employee_name . '.csv"');
    $out = fopen("php://output", "w");
    fputcsv($out, ["Branch", "Item", "Qty", "Unit", "Date"]);
    $stmt = $conn->prepare("
      SELECT o.branch, i.item_name, d.qty, d.unit, o.order_date
      FROM store_orders o
      JOIN store_order_items d ON o.id = d.order_id
      JOIN inventory_items i ON d.item_id = i.id
      WHERE o.employee_id = ?
      ORDER BY o.order_date DESC
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) fputcsv($out, $row);
    fclose($out);
    exit;
  }

  if ($_GET['export'] === 'inventory') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="inventory_' . $employee_name . '.csv"');
    $out = fopen("php://output", "w");
    fputcsv($out, ["Branch", "Item", "Qty Change", "Note", "Date"]);
    $stmt = $conn->prepare("
      SELECT e.branch, i.item_name, l.qty_change, l.note, l.created_at
      FROM branch_inventory_logs l
      JOIN employees e ON l.employee_id = e.id
      JOIN inventory_items i ON l.item_id = i.id
      WHERE l.employee_id = ?
      ORDER BY l.created_at DESC
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) fputcsv($out, $row);
    fclose($out);
    exit;
  }
}

/* ---------------- Orders ---------------- */
$stmt = $conn->prepare("
  SELECT o.branch, i.item_name, d.qty, d.unit, o.order_date
  FROM store_orders o
  JOIN store_order_items d ON o.id = d.order_id
  JOIN inventory_items i ON d.item_id = i.id
  WHERE o.employee_id = ?
  ORDER BY o.order_date DESC
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$orders = $stmt->get_result();
$total_orders = $orders->num_rows;
$stmt->close();

/* ---------------- Inventory ---------------- */
$stmt = $conn->prepare("
  SELECT e.branch, i.item_name, l.qty_change, l.note, l.created_at
  FROM branch_inventory_logs l
  JOIN employees e ON l.employee_id = e.id
  JOIN inventory_items i ON l.item_id = i.id
  WHERE l.employee_id = ?
  ORDER BY l.created_at DESC
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$inventory = $stmt->get_result();
$total_inventory = $inventory->num_rows;
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Employee Stats</title>
  <style>
    body { font-family: Arial; background:#f4f6f8; margin:20px; }
    h2 { text-align:center; margin-bottom:20px; }
    .card { background:#fff; padding:15px; border-radius:8px; margin:10px auto; max-width:900px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
    table { width:100%; border-collapse:collapse; margin:15px 0; }
    th,td { border:1px solid #eee; padding:8px; text-align:center; }
    th { background:#f3f3f3; }
    .stats { display:flex; gap:12px; flex-wrap:wrap; margin:20px auto; max-width:900px; }
    .stat { flex:1; background:#fff; border:1px solid #ddd; border-radius:6px; padding:12px; text-align:center; }
    .stat h3 { margin:0; font-size:16px; color:#555; }
    .stat p { font-size:22px; margin:6px 0 0; color:#007bff; font-weight:bold; }
    .export-btn { display:inline-block; margin:5px 0; padding:6px 12px; background:#28a745; color:#fff; text-decoration:none; border-radius:4px; }
    .export-btn:hover { background:#1e7e34; }
  </style>
</head>
<body>
  <h2>👤 Employee Statistics</h2>

  <div class="card">
    <p><strong>Name:</strong> <?= htmlspecialchars($emp['name']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($emp['email']) ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($emp['phone']) ?></p>
    <p><strong>Branch:</strong> <?= htmlspecialchars($emp['branch']) ?></p>
    <p><strong>Status:</strong> <?= $emp['is_active'] ? 'Active' : 'Deleted' ?></p>
  </div>

  <div class="stats">
    <div class="stat"><h3>Total Orders</h3><p><?= $total_orders ?></p></div>
    <div class="stat"><h3>Total Inventory Records</h3><p><?= $total_inventory ?></p></div>
  </div>

  <div class="card">
    <h3>📋 Orders</h3>
    <a href="?id=<?= urlencode($employee_id) ?>&export=orders" class="export-btn">⬇ Export Orders CSV</a>
    <table>
      <thead><tr><th>Branch</th><th>Item</th><th>Qty</th><th>Unit</th><th>Date</th></tr></thead>
      <tbody>
        <?php if ($total_orders): foreach($orders as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['branch']) ?></td>
            <td><?= htmlspecialchars($row['item_name']) ?></td>
            <td><?= (float)$row['qty'] ?></td>
            <td><?= htmlspecialchars($row['unit']) ?></td>
            <td><?= $row['order_date'] ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="5">No orders found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>📦 Inventory</h3>
    <a href="?id=<?= urlencode($employee_id) ?>&export=inventory" class="export-btn">⬇ Export Inventory CSV</a>
    <table>
      <thead><tr><th>Branch</th><th>Item</th><th>Qty</th><th>Note</th><th>Date</th></tr></thead>
      <tbody>
        <?php if ($total_inventory): foreach($inventory as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['branch']) ?></td>
            <td><?= htmlspecialchars($row['item_name']) ?></td>
            <td><?= (float)$row['qty_change'] ?></td>
            <td><?= htmlspecialchars($row['note']) ?></td>
            <td><?= $row['created_at'] ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="5">No inventory records found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>