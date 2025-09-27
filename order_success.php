<?php
// Set content type to UTF-8
header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['is_admin'])) {
  header('Location: admin_login.php');
  exit;
}

$order_id = $_GET['id'] ?? 0;
$wa_url   = $_GET['wa'] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Saved</title>
  <style>
    body { font-family: Arial; background:#f4f6f8; text-align:center; padding:40px; }
    .card { background:#fff; padding:20px; border-radius:10px; display:inline-block; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
    a { display:inline-block; margin:10px; padding:12px 18px; border-radius:6px; text-decoration:none; font-weight:bold; }
    .btn-back { background:#007bff; color:#fff; }
    .btn-back:hover { background:#0056b3; }
    .btn-wa { background:#25D366; color:#fff; }
    .btn-wa:hover { background:#128C7E; }
  </style>
</head>
<body>
  <div class="card">
    <h2>✅ Order #<?= htmlspecialchars($order_id) ?> saved successfully</h2>
    <a href="admin_orders.php" class="btn-back">📋 Back to Orders</a>
    <?php if ($wa_url): ?>
      <a href="<?= htmlspecialchars($wa_url) ?>" target="_blank" class="btn-wa">📲 Send to WhatsApp</a>
    <?php endif; ?>
  </div>
</body>
</html>