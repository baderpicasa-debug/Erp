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



// Load inventory items
$items = [];
$resItems = $conn->query("SELECT id, category, item_name FROM inventory_items ORDER BY category, item_name ASC");
while ($row = $resItems->fetch_assoc()) {
  $items[$row['category']][] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Selected Coffee – Warehouse Request</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
    h2 { text-align: center; margin-bottom: 20px; }
    .welcome-text { text-align: center; font-size: 18px; margin-bottom: 20px; color: #555; }
    .welcome-text strong { color: #007bff; }
    form { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 850px; margin: auto; }
    label { display:block; margin-top:10px; font-weight: bold; }
    input, button, select { padding:10px; margin-top:5px; width:100%; border:1px solid #ddd; border-radius:6px; font-size:16px; }
    .row { display:flex; gap:10px; margin-top:10px; flex-wrap: wrap; }
    .row select, .row input { flex:1; min-width: 150px; }
    button.primary { background:#007bff; color:#fff; border:none; cursor:pointer; }
    button.primary:hover { background:#0056b3; }
    button.secondary { background:#28a745; color:#fff; border:none; cursor:pointer; }
    button.secondary:hover { background:#1e7e34; }
    .alert { padding:12px; margin-bottom:15px; border-radius:6px; color:#155724; background:#d4edda; border:1px solid #c3e6cb; text-align:center;}
    .error-alert { padding:12px; margin-bottom:15px; border-radius:6px; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; text-align:center;}
    .actions { display:flex; gap:10px; flex-wrap: wrap; }
    @media (max-width: 720px){ .grid { grid-template-columns: 1fr; } .row { flex-direction: column; } }
  </style>
</head>
<body>
  <h2>📦 Warehouse Request</h2>
  
  <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert">✅ Order saved successfully.</div>
  <?php elseif (isset($_GET['error'])): ?>
    <?php
      $error_messages = [
        'invalid_method' => 'Invalid request method.',
        'invalid_employee' => 'Invalid employee information.',
        'invalid_branch' => 'Branch information is required.',
        'invalid_data' => 'Invalid data provided.',
        'invalid_data_structure' => 'Data structure mismatch.',
        'no_valid_items' => 'No valid items found to order.',
        'save_failed' => 'Failed to save order. Please try again.'
      ];
      $error = $_GET['error'];
      $message = $error_messages[$error] ?? 'An unknown error occurred.';
    ?>
    <div class="error-alert">❌ <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form action="save_order.php" method="POST" onsubmit="return validateAndSendOrder()">
    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
    <input type="hidden" name="employee" value="<?= htmlspecialchars($employee_id) ?>">
    <input type="hidden" name="branch" value="<?= htmlspecialchars($employee_branch) ?>">
    
    <?php if ($employee_name && $employee_branch): ?>
    <p class="welcome-text">
      Hello <strong><?= htmlspecialchars($employee_name) ?></strong>, what would you like to order from the warehouse today for branch <strong><?= htmlspecialchars($employee_branch) ?></strong>?
    </p>
    <?php endif; ?>
    
    <label style="margin-top:20px;">Items</label>
    
    <div id="orders">
      <div class="row">
        <select name="item[]" required>
          <option value="">Select an item</option>
          <?php foreach ($items as $cat => $list): ?>
            <optgroup label="<?= htmlspecialchars($cat) ?>">
              <?php foreach ($list as $it): ?>
                <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['item_name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>

        <input type="number" name="qty[]" placeholder="Quantity" min="0.01" max="9999" step="0.01" required />
        <select name="unit[]">
          <option>Piece</option>
          <option>Carton</option>
          <option>Kilo</option>
          <option>Gram</option>
        </select>
      </div>
    </div>

    <div class="actions" style="margin-top:12px;">
      <button type="button" class="secondary" onclick="addRow()">➕ Add Item</button>
      <button type="submit" class="primary">📩 Submit Order</button>
    </div>
    
    <div class="actions" style="margin-top:12px; text-align:center;">
      <a href="index.php" class="secondary" 
         style="display:inline-block; padding:8px 14px; background:#007bff; color:#fff; border-radius:6px; text-decoration:none;">
        ⚙️ Dashboard
      </a>
    </div>
  </form>

  <script>

    function addRow() {
      const first = document.querySelector("#orders .row");
      const div = document.createElement("div");
      div.className = "row";
      div.innerHTML = first.innerHTML;
      
      // Reset values
      div.querySelectorAll('input').forEach(i => i.value = '');
      const sel = div.querySelector('select[name="item[]"]');
      sel.selectedIndex = 0;
      
      // Add remove button
      const removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.innerHTML = "❌ Remove";
      removeBtn.className = "secondary";
      removeBtn.style.background = "#dc3545";
      removeBtn.onclick = function() { div.remove(); };
      div.appendChild(removeBtn);
      
      document.getElementById("orders").appendChild(div);
    }

    function fixWord(w) {
      const key = (w || "").trim();
      return corrections[key] || key;
    }

    function validateAndSendOrder() {
      // Check if at least one valid item is selected
      const rows = document.querySelectorAll("#orders .row");
      let hasValidItem = false;
      
      for (let row of rows) {
        const itemSelect = row.querySelector("select[name='item[]']");
        const qtyInput = row.querySelector("input[name='qty[]']");
        
        if (itemSelect.value && qtyInput.value && parseFloat(qtyInput.value) > 0) {
          hasValidItem = true;
          break;
        }
      }
      
      if (!hasValidItem) {
        alert("Please select at least one item with a valid quantity.");
        return false;
      }
      
      // Send WhatsApp message
      sendToWhatsApp();
      return true;
    }

    function sendToWhatsApp() {
      const employeeName = "<?= htmlspecialchars($employee_name) ?>";
      const branch = "<?= htmlspecialchars($employee_branch) ?>";
      const rows = document.querySelectorAll("#orders .row");

      const now = new Date();
      const datetime = now.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short', hour12: false });
      
      let message = "📦 New warehouse order\n";
      message += "🗓️ Date/Time: " + datetime + "\n";
      message += "👤 Employee: " + employeeName + "\n";
      message += "🏪 Branch: " + branch + "\n\n";

      rows.forEach(row => {
        const sel = row.querySelector("select[name='item[]']");
        const qtyInput = row.querySelector("input[name='qty[]']");
        const unitSelect = row.querySelector("select[name='unit[]']");
        
        if (sel.value && qtyInput.value && parseFloat(qtyInput.value) > 0) {
          const itemText = sel.options[sel.selectedIndex].text;
          const qty = qtyInput.value;
          const unit = unitSelect.value;
          message += "- " + itemText + " : " + qty + " " + unit + "\n";
        }
      });

      const phone = "<?= WA_PHONE ?>";
      if (phone && phone !== "") {
        const url = "https://wa.me/" + phone + "?text=" + encodeURIComponent(message);
        window.open(url, "_blank");
      }
    }

    // Validate quantity inputs
    document.addEventListener('input', function(e) {
      if (e.target.name === 'qty[]') {
        let value = parseFloat(e.target.value);
        if (value < 0) e.target.value = 0;
        if (value > 9999) e.target.value = 9999;
      }
    });
  </script>
</body>
</html>