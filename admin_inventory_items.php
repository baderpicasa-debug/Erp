<?php
require_once __DIR__ . '/config.php';

// Require login
if (empty($_SESSION['is_admin'])) {
  header('Location: login.php');
  exit;
}

$conn = db();

// ---------------- Add Item ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
  $cat = trim($_POST['category']);
  $custom_cat = trim($_POST['custom_category']);
  $name = trim($_POST['item_name']);

  if ($cat === "__other__" && $custom_cat !== "") {
    $cat = $custom_cat;
  }

  if ($cat && $name) {
    $stmt = $conn->prepare("INSERT INTO inventory_items (category, item_name) VALUES (?, ?)");
    $stmt->bind_param("ss", $cat, $name);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_inventory_items.php?success=1");
    exit;
  }
}

// ---------------- Update Item ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
  $id   = (int)$_POST['id'];
  $cat  = trim($_POST['category']);
  $name = trim($_POST['item_name']);

  if ($id && $cat && $name) {
    $stmt = $conn->prepare("UPDATE inventory_items SET category=?, item_name=? WHERE id=?");
    $stmt->bind_param("ssi", $cat, $name, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_inventory_items.php?updated=1");
    exit;
  }
}

// ---------------- Delete Item ----------------
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: admin_inventory_items.php?deleted=1");
  exit;
}

// ---------------- Load categories ----------------
$categories = [];
$res = $conn->query("SELECT DISTINCT category FROM inventory_items ORDER BY category ASC");
while ($row = $res->fetch_assoc()) {
  $categories[] = $row['category'];
}

// ---------------- Fetch all items ----------------
$res = $conn->query("SELECT id, category, item_name FROM inventory_items ORDER BY category, item_name ASC");
$items = $res->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Inventory Items</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body{font-family:Arial;background:#f4f6f8;margin:20px;}
    h2{text-align:center;margin-bottom:20px;}
    form,table{background:#fff;padding:15px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);margin:15px auto;max-width:900px;}
    input,button,select{padding:8px;margin:5px 0;border:1px solid #ddd;border-radius:6px;font-size:14px}
    button{cursor:pointer}
    button.primary{background:#007bff;color:#fff;border:none;}
    button.primary:hover{background:#0056b3}
    table{width:100%;border-collapse:collapse;margin-top:15px}
    th,td{border:1px solid #eee;padding:8px;text-align:center}
    th{background:#f3f3f3}
    .msg{padding:10px;border-radius:6px;margin:10px auto;max-width:900px;text-align:center}
    .success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
    .danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
    a.delete{color:#c00;text-decoration:none}
    .hidden{display:none;}
  </style>
  <script>
    function toggleCustomCategory(sel) {
      const input = document.getElementById('custom_category');
      if (sel.value === "__other__") {
        input.classList.remove('hidden');
        input.required = true;
      } else {
        input.classList.add('hidden');
        input.required = false;
        input.value = "";
      }
    }
  </script>
</head>
<body>
  <h2>🛠 Manage Inventory Items</h2>

  <?php if (isset($_GET['success'])): ?>
    <div class="msg success">✅ Item added successfully.</div>
  <?php elseif (isset($_GET['updated'])): ?>
    <div class="msg success">💾 Item updated successfully.</div>
  <?php elseif (isset($_GET['deleted'])): ?>
    <div class="msg danger">🗑️ Item deleted successfully.</div>
  <?php endif; ?>

  <!-- Add Item Form -->
  <form method="POST">
    <h3>➕ Add New Item</h3>
    <label>Category:</label>
    <select name="category" onchange="toggleCustomCategory(this)" required>
      <option value="">Select Category</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
      <?php endforeach; ?>
      <option value="__other__">Other (type manually)</option>
    </select>
    <input type="text" name="custom_category" id="custom_category" placeholder="Enter new category" class="hidden">

    <label>Item Name:</label>
    <input type="text" name="item_name" placeholder="Enter item name" required>

    <button type="submit" name="add_item" class="primary">➕ Add Item</button>
  </form>

  <!-- Items Table -->
  <h3>All Items</h3>
  <table>
    <thead>
      <tr><th>ID</th><th>Category</th><th>Item</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if ($items): foreach ($items as $item): ?>
        <tr>
          <form method="POST">
            <td><?= $item['id'] ?><input type="hidden" name="id" value="<?= $item['id'] ?>"></td>
            <td><input type="text" name="category" value="<?= htmlspecialchars($item['category']) ?>" required></td>
            <td><input type="text" name="item_name" value="<?= htmlspecialchars($item['item_name']) ?>" required></td>
            <td>
              <button type="submit" name="update_item" class="primary">💾 Save</button>
              <a href="?delete=<?= $item['id'] ?>" class="delete" onclick="return confirm('Delete this item?')">🗑️ Delete</a>
            </td>
          </form>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="4">No items found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div style="margin-top:20px;text-align:center;">
    <a href="index.php">⬅️ Back to Dashboard</a>
  </div>
</body>
</html>