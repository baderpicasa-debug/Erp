<?php
require_once __DIR__ . '/config.php';

// Require login
if (empty($_SESSION['is_admin'])) {
  header('Location: admin_login.php');
  exit;
}

$conn = db();

// ============== Fetch all data =================
$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) die("❌ Invalid order ID");

// Fetch order header
$stmt = $conn->prepare("SELECT o.*, e.name AS employee_name FROM store_orders o JOIN employees e ON o.employee_id = e.id WHERE o.id=? LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$order) die("❌ Order not found");

// Fetch order items using the order ID
$stmt = $conn->prepare("SELECT d.id, d.item_id, d.qty, d.unit, i.item_name, i.category FROM store_order_items d JOIN inventory_items i ON d.item_id=i.id WHERE d.order_id=?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all employees for dropdown (no change)
$employees = $conn->query("SELECT id, name FROM employees ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch all inventory items (no change)
$res = $conn->query("SELECT id, item_name, category FROM inventory_items ORDER BY category, item_name");
$items = $res->fetch_all(MYSQLI_ASSOC);

// Group items by category for the dropdown
$grouped_items = [];
foreach ($items as $it) {
    $grouped_items[$it['category']][] = $it;
}

// ============== Handle POST (update) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int)$_POST['employee_id'];
    $branch = trim($_POST['branch']);

    // Start a transaction for data integrity
    $conn->begin_transaction();

    try {
        // update order header
        $stmt = $conn->prepare("UPDATE store_orders SET employee_id=?, branch=? WHERE id=?");
        $stmt->bind_param("isi", $employee_id, $branch, $order_id);
        $stmt->execute();
        $stmt->close();

        // clear old items
        $stmt = $conn->prepare("DELETE FROM store_order_items WHERE order_id=?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();

        // insert new items
        if (!empty($_POST['item_id'])) {
            $stmt = $conn->prepare("INSERT INTO store_order_items (order_id, item_id, qty, unit) VALUES (?,?,?,?)");
            foreach ($_POST['item_id'] as $idx => $item_id) {
                $item_id = (int)$item_id;
                $qty = (float)$_POST['qty'][$idx];
                $unit = trim($_POST['unit'][$idx]);
                // Only insert if valid data
                if ($item_id > 0 && $qty > 0 && $unit !== '') {
                    $stmt->bind_param("iids", $order_id, $item_id, $qty, $unit);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        $conn->commit();
        header("Location: admin_orders.php");
        exit;

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        die("❌ An error occurred: " . $exception->getMessage());
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Order</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; padding:20px; }
        .card { background:#fff; padding:20px; border-radius:10px; max-width:900px; margin:auto; box-shadow:0 2px 8px rgba(0,0,0,0.1);}
        input, select { padding:8px; margin:5px 0; border:1px solid #ddd; border-radius:6px; width:100%; }
        button { background:#007bff; color:#fff; padding:10px 15px; border:none; border-radius:6px; cursor:pointer; }
        button:hover { background:#0056b3; }
        table { width:100%; border-collapse:collapse; margin-top:15px; }
        th,td { border:1px solid #eee; padding:8px; text-align:center; }
        th { background:#f9f9f9; }
        .add-btn { background:#28a745; margin-top:10px; }
        .add-btn:hover { background:#218838; }
        .remove-btn { background:#dc3545; color:#fff; border:none; padding:6px 10px; cursor:pointer; border-radius:4px; }
        .remove-btn:hover { background:#a71d2a; }
    </style>
    <script>
        function addRow(){
            const tbody = document.getElementById('items-body');
            const row = document.createElement('tr');
            row.innerHTML = `
              <td>
                <select name="item_id[]" required>
                  <option value="">Select an item</option>
                  <?php foreach($grouped_items as $cat=>$list): ?>
                    <optgroup label="<?= htmlspecialchars($cat) ?>">
                      <?php foreach($list as $it): ?>
                        <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['item_name']) ?></option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="qty[]" step="0.01" value="1"></td>
              <td><input type="text" name="unit[]" value="Piece"></td>
              <td><button type="button" class="remove-btn" onclick="this.closest('tr').remove()">X</button></td>
            `;
            tbody.appendChild(row);
        }
    </script>
</head>
<body>
<div class="card">
    <h2>✏️ Edit Order #<?= $order_id ?></h2>
    <form method="post">
        <label>Employee</label>
        <select name="employee_id" required>
            <?php foreach($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= ($emp['id']==$order['employee_id']?'selected':'') ?>>
                    <?= htmlspecialchars($emp['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Branch</label>
        <input type="text" name="branch" value="<?= htmlspecialchars($order['branch']) ?>" required>

        <h3>Items</h3>
        <table>
            <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Action</th></tr></thead>
            <tbody id="items-body">
            <?php foreach($order_items as $it): ?>
                <tr>
                    <td>
                        <select name="item_id[]" required>
                            <option value="">Select an item</option>
                            <?php foreach($grouped_items as $cat=>$list): ?>
                                <optgroup label="<?= htmlspecialchars($cat) ?>">
                                    <?php foreach($list as $ii): ?>
                                        <option value="<?= $ii['id'] ?>" <?= ($ii['id']==$it['item_id']?'selected':'') ?>>
                                            <?= htmlspecialchars($ii['item_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="qty[]" step="0.01" value="<?= $it['qty'] ?>"></td>
                    <td><input type="text" name="unit[]" value="<?= htmlspecialchars($it['unit']) ?>"></td>
                    <td><button type="button" class="remove-btn" onclick="this.closest('tr').remove()">X</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="add-btn" onclick="addRow()">+ Add Item</button>
        <br><br>
        <button type="submit">💾 Save Changes</button>
    </form>
</div>
</body>
</html>