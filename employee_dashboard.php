<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['is_employee'])) {
    header('Location: employee_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Employee Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
      body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 20px; text-align: center; }
      .container { max-width: 800px; margin: auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
      h2 { margin-bottom: 20px; }
      .links a { display: inline-block; padding: 15px 25px; margin: 10px; background: #007bff; color: #fff; text-decoration: none; border-radius: 6px; }
      .links a:hover { background: #0056b3; }
  </style>
</head>
<body>
  <div class="container">
    <h2>مرحباً بك، <?= htmlspecialchars($_SESSION['employee_name']) ?></h2>
    <p>هذه لوحة تحكم الموظفين الخاصة بك.</p>
    <div class="links">
      <a href="employee_profile.php">الملف الشخصي</a>
      <a href="employee_orders.php">طلباتي</a>
      <a href="employee_inventory.php">جرد المخزون</a>
    </div>
  </div>
</body>
</html>