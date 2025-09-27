<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['is_employee'])) {
    header('Location: login.php');
    exit;
}

$conn = db();
$employee_id = $_SESSION['employee_id'];

// ---------------- Base SQL ----------------
$sql_logs_base = "SELECT
                    l.id,
                    l.qty_change,
                    l.note,
                    l.created_at,
                    i.item_name
                  FROM branch_inventory_logs l
                  JOIN inventory_items i ON l.item_id = i.id
                  WHERE l.employee_id = ?";

$stmt = $conn->prepare($sql_logs_base . " ORDER BY l.created_at DESC");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result();

$groups = [];
if ($res && $res->num_rows) {
    while($r = $res->fetch_assoc()) {
        $date = substr($r['created_at'],0,10);
        if (!isset($groups[$date])) {
            $groups[$date] = [
                'date' => $date,
                'items' => []
            ];
        }
        $groups[$date]['items'][] = $r;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Inventory</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 20px; }
    h2 { text-align: center; margin-bottom: 20px; }
    .topbar { display: flex; justify-content: space-between; align-items: center; max-width: 1100px; margin: 0 auto 15px; }
    table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 10px; }
    th,td { border: 1px solid #eee; padding: 8px; text-align: center; }
    th { background: #f3f3f3; }
    .toggle-btn { cursor: pointer; color: #007bff; text-decoration: underline; }
    .hidden { display: none; }
    .sub-table { margin: 10px auto; width: 95%; border: 1px solid #ccc; }
    .actions a { margin: 0 5px; text-decoration: none; }
    .actions a.edit { color: #007bff; }
    .actions a.delete { color: #c00; }
</style>
<script>
function toggleItems(id){
    const row = document.getElementById("items-"+id);
    row.classList.toggle("hidden");
}
</script>
</head>
<body>
<div class="topbar">
    <h2>📦 My Inventory Records</h2>
    <div><a href="index.php"> ⚙️ Dashboard</a> | <a href="logout.php">Sign out</a></div>
</div>

<table>
<thead>
<tr>
  <th>Date</th>
  <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if ($groups):
  foreach($groups as $date=>$g): ?>
<tr>
  <td><?= $g['date'] ?></td>
  <td><span class="toggle-btn" onclick="toggleItems('<?= md5($date) ?>')">View Items</span></td>
</tr>
<tr id="items-<?= md5($date) ?>" class="hidden">
  <td colspan="2">
    <table class="sub-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Qty</th>
          <th>Note</th>
          <th>Date/Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($g['items'] as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['item_name']) ?></td>
          <td><?= $item['qty_change'] ?></td>
          <td><?= htmlspecialchars($item['note']) ?></td>
          <td><?= $item['created_at'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="2">No records found</td></tr>
<?php endif; ?>
</tbody>
</table>

</body>
</html>