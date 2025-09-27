<?php
require_once __DIR__ . '/config.php';

// Redirect if not logged in as employee or admin
if (empty($_SESSION['is_employee']) && empty($_SESSION['is_admin'])) {
  header('Location: login.php');
  exit;
}

$conn = db();

// Get logged-in user info from session
$employee_id = $_SESSION['employee_id'] ?? null;
$employee_name = $_SESSION['employee_name'] ?? null;
$employee_branch = null;

if ($employee_id) {
    $stmt = $conn->prepare("SELECT branch FROM employees WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $employee_branch = $result->fetch_assoc()['branch'];
    }
    $stmt->close();
}

// Load inventory items (grouped by category)
$categories = [];
$resItems = $conn->query("SELECT id, category, item_name FROM inventory_items ORDER BY category, item_name ASC");
while ($row = $resItems->fetch_assoc()) {
  $categories[$row['category']][] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Selected Coffee — Branch Inventory</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body { font-family: Arial, sans-serif; margin:20px; background:#f9f9f9; }
    h2 { text-align:center; margin-bottom:20px; }
    .welcome-text { text-align: center; font-size: 18px; margin-bottom: 20px; color: #555; }
    .welcome-text strong { color: #007bff; }
    form { background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1); max-width:900px; margin:auto; }
    label { font-weight:bold; display:block; margin-top:10px; }
    select, input { padding:8px; margin-top:5px; border:1px solid #ddd; border-radius:6px; font-size:15px; }
    .category-title { font-weight:bold; margin:20px 0 10px; font-size:16px; color:#333; border-bottom:2px solid #007bff; padding-bottom:5px; }
    .item-row { display:flex; justify-content:space-between; align-items:center; margin:6px 0; }
    .item-name { flex:1; }
    .item-input { width:120px; }
    button { padding:12px 18px; border:none; border-radius:6px; cursor:pointer; margin-top:15px; font-size:15px; }
    button.primary { background:#007bff; color:#fff; }
    button.primary:hover { background:#0056b3; }
    a.secondary { background:#28a745; color:#fff; padding:12px 18px; border-radius:6px; text-decoration:none; }
    a.secondary:hover { background:#1e7e34; }
    .alert { background:#d4edda; color:#155724; padding:12px; border-radius:6px; margin-bottom:15px; border:1px solid #c3e6cb; text-align:center; }
    .error-alert { background:#f8d7da; color:#721c24; padding:12px; border-radius:6px; margin-bottom:15px; border:1px solid #f5c6cb; text-align:center; }
  </style>
</head>
<body>
  <h2>📦 Branch Inventory — End of Day</h2>

  <?php if (isset($_GET['success']) && $_GET['success']==1): ?>
    <div class="alert">✅ Inventory record saved successfully.</div>
  <?php elseif (isset($_GET['error'])): ?>
    <?php
      $error_messages = [
        'invalid_method' => '❌ Invalid request method.',
        'invalid_employee' => '❌ Invalid employee information.',
        'invalid_branch' => '❌ Branch information is required.',
        'no_quantities' => '❌ Please enter at least one quantity.',
        'no_valid_items' => '❌ No valid items found to save.',
        'save_failed' => '❌ Failed to save inventory. Please try again.',
        'invalid_data' => '❌ Invalid data provided.'
      ];
      $error = $_GET['error'];
      $message = $error_messages[$error] ?? '❌ An unknown error occurred.';
    ?>
    <div class="error-alert"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  
  <form method="POST" action="save_inventory.php" onsubmit="return prepareWhatsAppMessage()">
    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
    <input type="hidden" name="employee_id" value="<?= htmlspecialchars($employee_id) ?>">
    <input type="hidden" name="branch" value="<?= htmlspecialchars($employee_branch) ?>">
    
    <?php if ($employee_name && $employee_branch): ?>
      <p class="welcome-text">
        Hello <strong><?= htmlspecialchars($employee_name) ?></strong>, what is the inventory count today for branch <strong><?= htmlspecialchars($employee_branch) ?></strong>?
      </p>
    <?php endif; ?>

    <?php foreach ($categories as $cat => $items): ?>
      <div class="category-title"><?= htmlspecialchars($cat) ?></div>
      <?php foreach ($items as $item): ?>
        <div class="item-row">
          <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
          <input type="number" class="item-input" name="qty[<?= $item['id'] ?>]" min="0" max="9999" placeholder="0" value="0">
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <div style="display:flex; flex-direction:column; align-items:center; gap:12px; margin-top:20px;">
      <button type="submit" class="primary">💾 Save & Send</button>
      <a href="index.php" class="secondary">⚙️ Dashboard</a>
    </div>
  </form>

  <script>
    function prepareWhatsAppMessage() {
      const employeeName = "<?= htmlspecialchars($employee_name) ?>";
      const branch = "<?= htmlspecialchars($employee_branch) ?>";
      
      // التحقق من وجود بيانات قبل الإرسال
      let hasData = false;
      const qtyInputs = document.querySelectorAll("input[name^='qty']");
      
      qtyInputs.forEach(input => {
        const qty = input.value.trim();
        if (qty !== "" && qty !== "0" && parseInt(qty) > 0) {
          hasData = true;
        }
      });
      
      if (!hasData) {
        alert("Please enter at least one quantity before submitting.");
        return false;
      }
      
      const now = new Date();
      const datetime = now.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short', hour12: false });

      let message = "📦 Branch Inventory - End of Day\n";
      message += "🗓️ Date/Time: " + datetime + "\n";
      message += "👤 Employee: " + employeeName + "\n";
      message += "🏪 Branch: " + branch + "\n\n";

      qtyInputs.forEach(input => {
        const qty = input.value.trim();
        if (qty !== "" && qty !== "0" && parseInt(qty) > 0) {
          const label = input.parentElement.querySelector(".item-name").innerText;
          message += "- " + label + " : " + qty + "\n";
        }
      });

      sendToWhatsApp(message);
      return true;
    }

    function sendToWhatsApp(message) {
      const phone = "<?= WA_PHONE ?>";
      if (phone && phone !== "") {
        const url = "https://wa.me/" + phone + "?text=" + encodeURIComponent(message);
        window.open(url, "_blank");
      }
    }
    
    // التحقق من صحة الإدخال
    document.querySelectorAll('.item-input').forEach(input => {
      input.addEventListener('input', function() {
        let value = parseInt(this.value);
        if (value < 0) this.value = 0;
        if (value > 9999) this.value = 9999;
      });
    });
  </script>
</body>
</html>