<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['is_employee'])) {
    header('Location: login.php');
    exit;
}

$conn = db();
$employee_id = $_SESSION['employee_id'];

// ---------------- Queries ----------------
// Get total orders and total quantity for the employee
$sql_totals = "SELECT COUNT(DISTINCT o.id) AS total_orders,
                      COALESCE(SUM(d.qty),0) AS total_qty
               FROM store_orders o
               LEFT JOIN store_order_items d ON o.id = d.order_id
               WHERE o.employee_id = ?";

$stmt = $conn->prepare($sql_totals);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Main query: Get all orders with a concatenated list of items
$sql_orders = "SELECT o.id, o.branch, o.order_date,
                      GROUP_CONCAT(CONCAT(i.item_name, ':', d.qty, ':', d.unit) SEPARATOR '|') AS items_list
               FROM store_orders o
               LEFT JOIN store_order_items d ON o.id = d.order_id
               LEFT JOIN inventory_items i ON d.item_id = i.id
               WHERE o.employee_id = ?
               GROUP BY o.id
               ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql_orders);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 20px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; max-width: 1100px; margin: 0 auto 15px; }
        .logout a { color: #c00; text-decoration: none; }
        .cards { display: flex; gap: 12px; max-width: 1100px; margin: 0 auto 15px; }
        .card { flex: 1; min-width: 150px; background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 12px; text-align: center; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 10px; }
        th, td { border: 1px solid #eee; padding: 8px; text-align: center; }
        th { background: #f3f3f3; }
        .expand { cursor: pointer; color: #007bff; text-decoration: underline; }
        .hidden { display: none; }
    </style>
    <script>
        function toggleItems(orderId) {
            var row = document.getElementById("items-" + orderId);
            row.classList.toggle("hidden");
        }
    </script>
</head>
<body>
    <div class="topbar">
        <h2>📦 My Orders</h2>
    <div><a href="index.php"> ⚙️ Dashboard</a> | <a href="logout.php">Sign out</a></div>
    </div>

    <div class="cards">
        <div class="card"><h3>Total Orders</h3><p><?= (int)($totals['total_orders'] ?? 0) ?></p></div>
        <div class="card"><h3>Total Quantity</h3><p><?= (int)($totals['total_qty'] ?? 0) ?></p></div>
    </div>

    <table>
        <thead>
            <tr><th>#</th><th>Branch</th><th>Date</th><th>Total Qty</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php if ($orders && $orders->num_rows): while ($o = $orders->fetch_assoc()): ?>
                <tr>
                    <td><?= $o['id'] ?></td>
                    <td><?= htmlspecialchars($o['branch']) ?></td>
                    <td><?= date("Y-m-d H:i", strtotime($o['order_date'])) ?></td>
                    <td>
                        <?php
                            $total_qty = 0;
                            if ($o['items_list']) {
                                $items = explode('|', $o['items_list']);
                                foreach ($items as $item) {
                                    $parts = explode(':', $item);
                                    $total_qty += (float)$parts[1];
                                }
                            }
                            echo $total_qty;
                        ?>
                    </td>
                    <td><span class="expand" onclick="toggleItems(<?= $o['id'] ?>)">View Items</span></td>
                </tr>
                <tr id="items-<?= $o['id'] ?>" class="hidden">
                    <td colspan="5">
                        <table>
                            <thead><tr><th>Item</th><th>Qty</th><th>Unit</th></tr></thead>
                            <tbody>
                                <?php
                                    if ($o['items_list']) {
                                        $items = explode('|', $o['items_list']);
                                        foreach ($items as $item) {
                                            $parts = explode(':', $item);
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($parts[0]) . "</td>";
                                            echo "<td>" . htmlspecialchars($parts[1]) . "</td>";
                                            echo "<td>" . htmlspecialchars($parts[2]) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='3'>No items</td></tr>";
                                    }
                                ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="5">No orders found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>