<?php
require_once __DIR__ . '/config.php';
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$is_employee = isset($_SESSION['is_employee']) && $_SESSION['is_employee'];

// Redirect if not logged in as employee or admin
if (empty($is_employee) && empty($is_admin)) {
  header('Location: login.php');
  exit;
}

// Set name to display based on user type
$display_name = '';
if ($is_admin && isset($_SESSION['admin_name'])) {
    $display_name = $_SESSION['admin_name'];
} elseif ($is_employee && isset($_SESSION['employee_name'])) {
    $display_name = $_SESSION['employee_name'];
} else {
    $display_name = 'User'; // Fallback
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Main Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body {
        font-family: Arial, sans-serif;
        background: #f9f9f9;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    h2 {
        text-align: center;
        margin: 30px 0;
        font-size: 24px;
    }
    .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        width: 90%;
        max-width: 600px;
    }
    .card {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 120px;
        border-radius: 15px;
        font-size: 16px;
        font-weight: bold;
        color: #fff;
        cursor: pointer;
        transition: 0.2s ease;
        text-align: center;
    }
    .card:hover {
        transform: scale(1.05);
    }
    .submit-order, .my-orders { background: #007bff; }
    .submit-inventory, .my-inventory { background: #28a745; }
    .settings { background: #607d8b; }
    .admin-orders { background: #dc3545; }
    .admin-inventory { background: #17a2b8; }
    .admin-items { background: #fd7e14; }
    .admin-employees { background: #9c27b0; }
    .admin-analytics { background: #4a69bd; }
    .admin-settings { background: #6c757d; }
    a {
        text-decoration: none;
        color: inherit;
    }
    .logout {
        margin: 30px 0;
        text-align: center;
    }
    .logout a {
        color: #c00;
        text-decoration: none;
        font-size: 16px;
    }
    @media (max-width: 600px) {
        .grid {
            grid-template-columns: 1fr;
        }
        .card {
            height: 100px;
            font-size: 14px;
        }
    }
  </style>
</head>
<body>
    <h2>👤 Welcome, <?= htmlspecialchars($display_name) ?></h2>
    
    <div class="grid">
        <?php if ($is_employee): ?>
            <a href="order.php">
                <div class="card submit-order">🛒 Submit Order</div>
            </a>
            <a href="branch_inventory.php">
                <div class="card submit-inventory">📦 Submit Inventory</div>
            </a>
            <a href="employee_orders.php">
                <div class="card my-orders">📋 My Orders</div>
            </a>
            <a href="employee_inventory.php">
                <div class="card my-inventory">📈 My Inventory</div>
            </a>
        <?php endif; ?>
        
        <?php if ($is_admin): ?>
            <a href="admin_orders.php">
              <div class="card admin-orders">📦 Orders Management</div>
            </a>
            <a href="admin_inventory.php">
              <div class="card admin-inventory">📊 Inventory Management</div>
            </a>
            <a href="admin_inventory_items.php">
              <div class="card admin-items">🛠 Manage Inventory Items</div>
            </a>
            <a href="admin_employees.php">
              <div class="card admin-employees">👨‍💼 Employees Management</div>
            </a>
        <?php endif; ?>

        <a href="settings.php">
            <div class="card settings">⚙️ Settings</div>
        </a>
    </div>

    <div class="logout">
      <a href="logout.php">🚪 Sign out</a>
    </div>
</body>
</html>