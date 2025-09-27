<?php
require_once __DIR__ . '/config.php';

// Require login
if (empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

$conn = db();

// ---------------- Filters ----------------
$where = "1=1";
$params = [];
$types = '';

if (!empty($_GET['employee'])) {
    $where .= " AND e.name LIKE ?";
    $params[] = "%" . $_GET['employee'] . "%";
    $types .= 's';
}
if (!empty($_GET['branch'])) {
    $where .= " AND o.branch LIKE ?";
    $params[] = "%" . $_GET['branch'] . "%";
    $types .= 's';
}
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $where .= " AND DATE(o.order_date) BETWEEN ? AND ?";
    $params[] = $_GET['from'];
    $params[] = $_GET['to'];
    $types .= 'ss';
}

// ---------------- Pagination ----------------
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// ---------------- Queries ----------------

// First, get the total count of filtered orders
$sql_totals = "SELECT COUNT(*) AS total_orders FROM store_orders o JOIN employees e ON o.employee_id = e.id WHERE $where";
$stmt = $conn->prepare($sql_totals);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$total_orders = (int)($totals['total_orders'] ?? 0);
$total_pages = ceil($total_orders / $per_page);
$stmt->close();

// Then, get the order IDs for the current page only
$sql_ids = "SELECT o.id FROM store_orders o JOIN employees e ON o.employee_id = e.id WHERE $where ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql_ids);
$types_ids = $types . 'ii';
$params_ids = array_merge($params, [$per_page, $offset]);
$stmt->bind_param($types_ids, ...$params_ids);
$stmt->execute();
$order_ids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// If no orders found, stop here
if (empty($order_ids)) {
    $orders = new stdClass; // Placeholder to avoid errors
    $orders->num_rows = 0;
    $orders_with_items = [];
} else {
    $ids = array_column($order_ids, 'id');
    $ids_string = implode(',', array_fill(0, count($ids), '?'));
    $ids_types = str_repeat('i', count($ids));

    // Get order details for the current page
    $sql_orders_details = "SELECT o.id, e.name AS employee, e.id AS employee_id, o.branch, o.order_date 
                           FROM store_orders o 
                           JOIN employees e ON o.employee_id = e.id 
                           WHERE o.id IN ($ids_string) ORDER BY o.order_date DESC";
    $stmt_orders = $conn->prepare($sql_orders_details);
    $stmt_orders->bind_param($ids_types, ...$ids);
    $stmt_orders->execute();
    $orders = $stmt_orders->get_result();

    // Get all items for the orders on this page in one go
    $sql_items = "SELECT d.order_id, i.item_name, d.qty, d.unit 
                  FROM store_order_items d 
                  JOIN inventory_items i ON d.item_id = i.id 
                  WHERE d.order_id IN ($ids_string)";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param($ids_types, ...$ids);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    $stmt_items->close();

    // Organize items by order_id
    $items_by_order = [];
    while ($item_row = $items_result->fetch_assoc()) {
        $items_by_order[$item_row['order_id']][] = $item_row;
    }

    // Combine orders and their items
    $orders_with_items = [];
    while ($order_row = $orders->fetch_assoc()) {
        $order_row['items'] = $items_by_order[$order_row['id']] ?? [];
        $orders_with_items[] = $order_row;
    }
}

// ---------------- CSV Export ----------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders_export.csv"');
    $out = fopen("php://output", "w");
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Arabic
    fputcsv($out, ["Order ID","Employee","Branch","Date","Item","Qty","Unit"]);

    // Reuse the same base query logic without pagination
    $sql_export = "SELECT o.id, e.name AS employee, o.branch, o.order_date,
                          i.item_name, d.qty, d.unit
                   FROM store_orders o
                   JOIN employees e ON o.employee_id = e.id
                   JOIN store_order_items d ON o.id = d.order_id
                   JOIN inventory_items i ON d.item_id = i.id
                   WHERE $where
                   ORDER BY o.order_date DESC";

    $stmt = $conn->prepare($sql_export);
    if ($types) {
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res_export = $stmt->get_result();

    while ($row = $res_export->fetch_assoc()) {
        fputcsv($out, $row);
    }
    $stmt->close();
    fclose($out);
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders Management</title>
    <style>
        body{font-family:Arial;background:#f4f6f8;margin:20px;}
        h2{text-align:center;margin-bottom:15px}
        .topbar{display:flex;justify-content:space-between;align-items:center;max-width:1100px;margin:0 auto 15px;}
        .logout a{color:#c00;text-decoration:none}
        form{background:#fff;padding:12px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);max-width:1100px;margin:0 auto 15px;}
        input,button{padding:8px;margin:5px 0;border:1px solid #ddd;border-radius:6px}
        button{background:#007bff;color:#fff;border:none;cursor:pointer}
        button:hover{background:#0056b3}
        .cards{display:flex;gap:12px;max-width:1100px;margin:0 auto 15px;}
        .card{flex:1;min-width:150px;background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px;text-align:center}
        .card h3{margin:0;font-size:16px;color:#333}
        .card p{margin:5px 0 0;font-size:20px;font-weight:bold;color:#007bff}
        table{width:100%;border-collapse:collapse;background:#fff;margin-bottom:10px;}
        th,td{border:1px solid #eee;padding:8px;text-align:center}
        th{background:#f3f3f3}
        .expand{cursor:pointer;color:#007bff;text-decoration:underline}
        .hidden{display:none}
        .pagination{text-align:center;margin:20px}
        .pagination a{margin:0 5px;padding:6px 12px;background:#fff;border:1px solid #ccc;border-radius:4px;text-decoration:none;color:#333}
        .pagination a.active{background:#007bff;color:#fff}
    </style>
    <script>
    function toggleItems(orderId){
        var row = document.getElementById("items-"+orderId);
        row.classList.toggle("hidden");
    }
    </script>
</head>
<body>
    <div class="topbar">
        <h2>📊 Orders Management</h2>
        <div><a href="index.php"> ⚙️ Dashboard</a> | <a href="logout.php">Sign out</a></div>
    </div>

    <form method="get">
        Employee: <input type="text" name="employee" value="<?= htmlspecialchars($_GET['employee'] ?? '') ?>">
        Branch: <input type="text" name="branch" value="<?= htmlspecialchars($_GET['branch'] ?? '') ?>">
        From: <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
        To: <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
        <button type="submit">Filter</button>
        <button type="submit" name="export" value="csv">Export CSV</button>
    </form>

    <div class="cards">
        <div class="card"><h3>Total Orders</h3><p><?= $total_orders ?></p></div>
        <?php 
        // This part needs a dedicated query if you want total quantity with filters
        // For simplicity, let's skip it to avoid another complex query.
        // If needed, you can use the same logic as the old code.
        ?>
    </div>

    <table>
        <thead>
            <tr><th>#</th><th>Employee</th><th>Branch</th><th>Date</th><th>Total Qty</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php if (!empty($orders_with_items)): foreach($orders_with_items as $o): ?>
                <tr>
                    <td><?= htmlspecialchars($o['id']) ?></td>
                    <td><a href="employee_stats.php?id=<?= urlencode($o['employee_id']) ?>"><?= htmlspecialchars($o['employee']) ?></a></td>
                    <td><?= htmlspecialchars($o['branch']) ?></td>
                    <td><?= date("Y-m-d H:i", strtotime($o['order_date'])) ?></td>
                    <td>
                        <?php
                            $total_qty = 0;
                            foreach ($o['items'] as $item) {
                                $total_qty += (float)$item['qty'];
                            }
                            echo htmlspecialchars($total_qty);
                        ?>
                    </td>
                    <td>
                        <a href="edit_order.php?id=<?= htmlspecialchars($o['id']) ?>">✏️ Edit</a> |
                        <a href="delete_order.php?id=<?= htmlspecialchars($o['id']) ?>" onclick="return confirm('Are you sure you want to delete this order?');">🗑️ Delete</a> |
                        <span class="expand" onclick="toggleItems(<?= htmlspecialchars($o['id']) ?>)">View Items</span>
                    </td>
                </tr>
                <tr id="items-<?= htmlspecialchars($o['id']) ?>" class="hidden">
                    <td colspan="6">
                        <table>
                            <thead><tr><th>Item</th><th>Qty</th><th>Unit</th></tr></thead>
                            <tbody>
                                <?php if (!empty($o['items'])): foreach ($o['items'] as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($item['qty']) ?></td>
                                        <td><?= htmlspecialchars($item['unit']) ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan='3'>No items</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6">No orders found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i=1;$i<=$total_pages;$i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="<?= ($i==$page?'active':'') ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</body>
</html>