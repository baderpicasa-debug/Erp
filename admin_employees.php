<?php
require_once __DIR__ . '/config.php';

// Require login
if (empty($_SESSION['is_admin'])) {
  header('Location: login.php');
  exit;
}

$conn = db();

// Handle Add Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
  // CSRF Protection
  if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    die("Invalid request token");
  }
  
  $name  = sanitizeInput($_POST['name']);
  $branch = sanitizeInput($_POST['branch']);
  $email = sanitizeInput($_POST['email']);
  $phone = sanitizeInput($_POST['phone']);
  $password = trim($_POST['password']);

  if ($name && $branch && $password) {
    // Start a transaction for data integrity
    $conn->begin_transaction();
    try {
        // 1. Insert into employees table
        $stmt = $conn->prepare("INSERT INTO employees (name, branch, email, phone) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $branch, $email, $phone);
        $stmt->execute();
        $employee_id = $stmt->insert_id;
        $stmt->close();
        
        // 2. Insert into employees_auth table with hashed password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt_auth = $conn->prepare("INSERT INTO employees_auth (employee_id, password) VALUES (?, ?)");
        $stmt_auth->bind_param("is", $employee_id, $password_hash);
        $stmt_auth->execute();
        $stmt_auth->close();

        $conn->commit();
        header("Location: admin_employees.php?success=1");
        exit;
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        logError("Employee addition failed: " . $exception->getMessage());
        die("An error occurred while adding employee. Please try again.");
    }
  }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
  // CSRF Protection
  if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    die("Invalid request token");
  }
  
  $id    = (int)$_POST['id'];
  $name  = sanitizeInput($_POST['name']);
  $branch = sanitizeInput($_POST['branch']);
  $email = sanitizeInput($_POST['email']);
  $phone = sanitizeInput($_POST['phone']);
  
  if ($id && $name && $branch) {
    $stmt = $conn->prepare("UPDATE employees SET name=?, branch=?, email=?, phone=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $branch, $email, $phone, $id);
    $stmt->execute();
    $stmt->close();
  }
  header("Location: admin_employees.php?updated=1");
  exit;
}

// Handle Delete (Soft Delete)
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  if ($id > 0) {
    $conn->begin_transaction();
    try {
        // Soft delete the employee
        $stmt_emp = $conn->prepare("UPDATE employees SET is_active = 0 WHERE id = ?");
        $stmt_emp->bind_param("i", $id);
        $stmt_emp->execute();
        $stmt_emp->close();

        // Delete the employee's login credentials to prevent future logins
        $stmt_auth = $conn->prepare("DELETE FROM employees_auth WHERE employee_id = ?");
        $stmt_auth->bind_param("i", $id);
        $stmt_auth->execute();
        $stmt_auth->close();

        $conn->commit();
        header("Location: admin_employees.php?deleted=1");
        exit;
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        logError("Employee deletion failed: " . $exception->getMessage());
        die("An error occurred while deleting employee. Please try again.");
    }
  }
  header("Location: admin_employees.php?deleted=1");
  exit;
}

// Load Employees
$res = $conn->query("SELECT * FROM employees WHERE is_active = 1 ORDER BY id ASC");
$employees = $res->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Employees</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body { font-family: Arial, sans-serif; background:#f4f6f8; margin:20px; }
    h2 { text-align:center; margin-bottom:20px; }
    .alert { padding:12px; border-radius:6px; margin:10px auto; max-width:900px; text-align:center; }
    .success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .danger { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    table { width:100%; border-collapse:collapse; background:#fff; margin:20px auto; max-width:900px; }
    th,td { border:1px solid #eee; padding:10px; text-align:center; }
    th { background:#f3f3f3; }
    button { padding:6px 12px; border:none; border-radius:4px; cursor:pointer; font-size:14px; margin: 2px; }
    button.primary { background:#007bff; color:#fff; }
    button.danger { background:#c00; color:#fff; }
    .modal { display:none; position:fixed; z-index:1000; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
    .modal-content { background:#fff; margin:10% auto; padding:20px; border-radius:8px; max-width:500px; position:relative; }
    .close { position:absolute; top:10px; right:15px; cursor:pointer; font-size:18px; }
    input { padding:8px; width:100%; margin-top:5px; border:1px solid #ddd; border-radius:6px; }
  </style>
  <script>
    function openModal(id,name,branch,email,phone){
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_name').value = name;
      document.getElementById('edit_branch').value = branch;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_phone').value = phone;
      document.getElementById('editModal').style.display='block';
    }
    function closeModal(){
      document.getElementById('editModal').style.display='none';
    }
  </script>
</head>
<body>
  <h2>👥 Manage Employees</h2>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert success">✅ Employee added successfully.</div>
  <?php elseif (isset($_GET['updated'])): ?>
    <div class="alert success">💾 Employee updated successfully.</div>
  <?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert danger">🗑️ Employee deleted successfully.</div>
  <?php endif; ?>

<form method="POST" style="max-width:900px; margin:auto; background:#fff; padding:15px; border-radius:8px;">
    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
    <h3>➕ Add Employee</h3>
    <label>Name</label>
    <input type="text" name="name" required>
    <label>Branch</label>
    <input type="text" name="branch" required>
    <label>Email</label>
    <input type="email" name="email">
    <label>Phone</label>
    <input type="text" name="phone">
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit" name="add_employee" class="primary">Add</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Branch</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($employees): foreach ($employees as $i=>$emp): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td>
            <a href="employee_stats.php?id=<?= urlencode($emp['id']) ?>">
              <?= htmlspecialchars($emp['name']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($emp['branch']) ?></td>
          <td><?= htmlspecialchars($emp['email']) ?></td>
          <td><?= htmlspecialchars($emp['phone']) ?></td>
          <td>
            <button class="primary" 
              onclick="openModal('<?= $emp['id'] ?>','<?= htmlspecialchars($emp['name'],ENT_QUOTES) ?>','<?= htmlspecialchars($emp['branch'],ENT_QUOTES) ?>','<?= htmlspecialchars($emp['email'],ENT_QUOTES) ?>','<?= htmlspecialchars($emp['phone'],ENT_QUOTES) ?>')">
              ✏️ Edit
            </button>
            <a href="reset_employee_password.php?id=<?= $emp['id'] ?>" onclick="return confirm('⚠️ Are you sure you want to reset the password for <?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>?');">
              <button class="primary">🔄 Reset Password</button>
            </a>
            <a href="?delete=<?= $emp['id'] ?>" onclick="return confirm('Delete this employee?')">
              <button class="danger">🗑️ Delete</button>
            </a>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="6">No employees found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal()">&times;</span>
      <h3>✏️ Edit Employee</h3>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
        <input type="hidden" id="edit_id" name="id">
        <label>Name</label>
        <input type="text" id="edit_name" name="name" required>
        <label>Branch</label>
        <input type="text" id="edit_branch" name="branch" required>
        <label>Email</label>
        <input type="email" id="edit_email" name="email">
        <label>Phone</label>
        <input type="text" id="edit_phone" name="phone">
        <button type="submit" name="update_employee" class="primary">💾 Save</button>
      </form>
    </div>
  </div>

  <div style="text-align:center; margin-top:20px;">
    <a href="index.php">⬅️ Back to Dashboard</a>
  </div>
</body>
</html>