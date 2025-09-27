<?php
require_once __DIR__ . '/config.php';

// Set content type to UTF-8
header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php');
    exit;
}

$conn = db();

// ---------------- Filters Logic ----------------
$where = "1=1";
$params = [];
$types = '';

if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $where .= " AND o.order_date BETWEEN ? AND ?";
    $params[] = $_GET['from'] . ' 00:00:00';
    $params[] = $_GET['to'] . ' 23:59:59';
    $types .= 'ss';
}

// ---------------- Queries ----------------

// Query for orders per month
$orders_per_month = [];
$sql = "SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS month, COUNT(o.id) AS total_orders FROM store_orders o WHERE " . $where . " GROUP BY month ORDER BY month";
$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $orders_per_month[] = $row;
}
$stmt->close();

// Query for most popular items
$popular_items = [];
$sql = "SELECT i.item_name, SUM(d.qty) AS total_qty FROM store_order_items d JOIN inventory_items i ON d.item_id = i.id JOIN store_orders o ON d.order_id = o.id WHERE " . $where . " GROUP BY i.item_name ORDER BY total_qty DESC LIMIT 10";
$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $popular_items[] = $row;
}
$stmt->close();


// Query for employee activity
$employee_activity = [];
$sql = "SELECT e.name AS employee_name, COUNT(o.id) AS total_orders FROM store_orders o JOIN employees e ON o.employee_id = e.id WHERE " . $where . " GROUP BY e.name ORDER BY total_orders DESC LIMIT 10";
$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $employee_activity[] = $row;
}
$stmt->close();

// Generate textual insights
$total_orders_last_month = 0;
$total_orders_second_last_month = 0;

if (count($orders_per_month) >= 1) {
    $total_orders_last_month = $orders_per_month[count($orders_per_month) - 1]['total_orders'];
}
if (count($orders_per_month) >= 2) {
    $total_orders_second_last_month = $orders_per_month[count($orders_per_month) - 2]['total_orders'];
}

$orders_comparison = '';
if ($total_orders_last_month > $total_orders_second_last_month && $total_orders_second_last_month > 0) {
    $percentage = round((($total_orders_last_month - $total_orders_second_last_month) / $total_orders_second_last_month) * 100, 2);
    $orders_comparison = "Total orders increased by {$percentage}% this month compared to the previous month. This indicates a positive growth trend.";
} elseif ($total_orders_last_month < $total_orders_second_last_month && $total_orders_second_last_month > 0) {
    $percentage = round((($total_orders_second_last_month - $total_orders_last_month) / $total_orders_second_last_month) * 100, 2);
    $orders_comparison = "Total orders decreased by {$percentage}% this month. This may require further investigation into the reasons for the decline.";
} elseif ($total_orders_last_month == $total_orders_second_last_month && $total_orders_last_month > 0) {
    $orders_comparison = "Total orders remained stable this month compared to the previous one.";
} else {
    $orders_comparison = "No enough data to show a comparison between months.";
}

$top_employee_text = '';
if (!empty($employee_activity)) {
    $top_employee = $employee_activity[0]['employee_name'];
    $top_employee_orders = $employee_activity[0]['total_orders'];
    $top_employee_text = "The most active employee is **{$top_employee}** with **{$top_employee_orders}** orders. Great work! 🎉";
}

$most_requested_item_text = '';
if (!empty($popular_items)) {
    $most_requested_item = $popular_items[0]['item_name'];
    $most_requested_item_qty = (float)$popular_items[0]['total_qty'];
    $most_requested_item_text = "The most requested item is **{$most_requested_item}**, with a total quantity of **{$most_requested_item_qty}**. Make sure this item is always in stock!";
}

$conn->close();

$orders_data = json_encode($orders_per_month);
$items_data = json_encode($popular_items);
$employees_data = json_encode($employee_activity);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 20px; }
        h2 { text-align: center; margin-bottom: 20px; }
        .chart-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 20px auto;
            text-align: center;
        }
        .chart-description {
            margin-top: 10px;
            color: #555;
            font-size: 14px;
        }
        .insights {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 20px auto;
        }
        .insights h3 { margin-top: 0; }
        .insights p { margin-bottom: 5px; }
        .filter-form {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 20px auto;
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }
        .filter-form input, .filter-form button {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .filter-form button {
            background: #007bff;
            color: #fff;
            border: none;
            cursor: pointer;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<h2>ðŸ“ˆ Analytics Dashboard</h2>

<form method="get" class="filter-form">
    From: <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
    To: <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
    <button type="submit">Filter</button>
</form>

<div class="insights">
    <h3>Key Insights</h3>
    <?php if ($orders_comparison): ?>
        <p>â€¢ Orders: <?= $orders_comparison ?></p>
    <?php endif; ?>
    <?php if ($most_requested_item_text): ?>
        <p>â€¢ Items: <?= $most_requested_item_text ?></p>
    <?php endif; ?>
    <?php if ($top_employee_text): ?>
        <p>â€¢ Employees: <?= $top_employee_text ?></p>
    <?php endif; ?>
</div>

<div class="chart-container">
    <h3>Orders Per Month</h3>
    <canvas id="ordersChart"></canvas>
</div>

<div class="chart-container">
    <h3>Top 10 Most Requested Items</h3>
    <canvas id="itemsChart"></canvas>
</div>

<div class="chart-container">
    <h3>Top 10 Employees by Orders</h3>
    <canvas id="employeesChart"></canvas>
</div>

<script>
    const ordersData = <?= $orders_data ?>;
    const itemsData = <?= $items_data ?>;
    const employeesData = <?= $employees_data ?>;

    // Orders per Month Chart
    new Chart(document.getElementById('ordersChart'), {
        type: 'bar',
        data: {
            labels: ordersData.map(row => row.month),
            datasets: [{
                label: 'Total Orders',
                data: ordersData.map(row => row.total_orders),
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });

    // Top 10 Items Chart
    new Chart(document.getElementById('itemsChart'), {
        type: 'doughnut',
        data: {
            labels: itemsData.map(row => row.item_name),
            datasets: [{
                label: 'Total Quantity',
                data: itemsData.map(row => row.total_qty),
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#E7E9ED', '#8B5B47', '#42A855', '#D24933'
                ]
            }]
        },
        options: { responsive: true }
    });

    // Top 10 Employees Chart
    new Chart(document.getElementById('employeesChart'), {
        type: 'polarArea',
        data: {
            labels: employeesData.map(row => row.employee_name),
            datasets: [{
                label: 'Total Orders',
                data: employeesData.map(row => row.total_orders),
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#E7E9ED', '#8B5B47', '#42A855', '#D24933'
                ]
            }]
        },
        options: { responsive: true }
    });
</script>

</body>
</html>