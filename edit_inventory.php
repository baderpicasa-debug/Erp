<?php
require_once __DIR__ . '/config.php';

// Require login
if (empty($_SESSION['is_admin'])) {
  header('Location: login.php');
  exit;
}

$conn = db();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch log by ID
$sql = "SELECT l.id, l.employee_id, l.branch_id, l.item_id, 
               e.name AS employee, e.branch AS branch, 
               i.item_name, l.qty_change, l.note, l.created_at
        FROM branch_inventory_logs l
        JOIN employees e ON l.employee_id = e.id
        JOIN inventory_items i ON l.item_id = i.id
        WHERE l.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows == 0) {
    die("❌ Record not found. (ID: $id)");
}

$row = $res->fetch_assoc();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty  = floatval($_POST['qty']);
    $note = trim($_POST['note']);

    $sql_update = "UPDATE branch_inventory_logs 
                   SET qty_change = ?, note = ? 
                   WHERE id = ?";
    $stmt_upd = $conn->prepare($sql_update);

    if (!$stmt_upd) {
        die("❌ Prepare failed: " . $conn->error);
    }
    $stmt_upd->bind_param("dsi", $qty, $note, $id);
    if ($stmt_upd->execute()) {
        header("Location: admin_inventory.php?updated=1");
        exit;
    } else {
        die("❌ Update failed: " . $stmt_upd->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Inventory Log</title>
<style>
  body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px; }
  form { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); max-width: 600px; margin:auto; }
  h2 { text-align:center; margin-bottom:20px; }
  label { display:block; margin-top:10px; font-weight:bold; }
  input, textarea { width:100%; padding:8px; margin-top:5px; border:1px solid #ddd; border-radius:6px; }
  button { margin-top:15px; padding:10px 15px; border:none; border-radius:6px; background:#007bff; color:#fff; cursor:pointer; }
  button:hover { background:#0056b3; }
  .info { margin-bottom:15px; }
</style>
</head>
<body>

<h2>✏️ Edit Inventory Log</h2>

<form method="post">
  <div class="info"><strong>Employee:</strong> <?= htmlspecialchars($row['employee']) ?></div>
  <div class="info"><strong>Branch:</strong> <?= htmlspecialchars($row['branch']) ?></div>
  <div class="info"><strong>Item:</strong> <?= htmlspecialchars($row['item_name']) ?></div>
  <div class="info"><strong>Date:</strong> <?= $row['created_at'] ?></div>

  <label>Quantity</label>
  <input type="number" step="0.01" name="qty" value="<?= $row['qty_change'] ?>" required>

  <label>Note</label>
  <textarea name="note"><?= htmlspecialchars($row['note']) ?></textarea>

  <button type="submit">💾 Save Changes</button>
</form>

</body>
</html>