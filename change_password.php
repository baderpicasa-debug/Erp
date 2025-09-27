<?php
require_once __DIR__ . '/config.php';

// Redirect if not logged in as an employee
if (empty($_SESSION['is_employee'])) {
    header('Location: employee_login.php');
    exit;
}

// Redirect if password change is not forced
if (empty($_SESSION['force_password_change'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$employee_id = $_SESSION['employee_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';

    $conn = db();
    $stmt = $conn->prepare("SELECT password FROM employees_auth WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (password_verify($old_password, $row['password'])) {
            if ($new_password === $new_password_confirm && !empty($new_password)) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update the password and reset the `is_temp_password` flag to 0
                $stmt_upd = $conn->prepare("UPDATE employees_auth SET password = ?, is_temp_password = 0 WHERE employee_id = ?");
                $stmt_upd->bind_param("si", $new_password_hash, $employee_id);
                $stmt_upd->execute();
                $stmt_upd->close();
                
                // Clear the force change flag from the session
                unset($_SESSION['force_password_change']);
                header('Location: index.php?pwd_changed=1');
                exit;

            } else {
                $error = 'New passwords do not match or are empty.';
            }
        } else {
            $error = 'Incorrect old password.';
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <style>
        body { font-family: Arial; background:#f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
        .box { background:#fff; padding:25px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); width:350px; }
        h2 { text-align:center; margin-bottom:20px; }
        p { text-align: center; }
        input { width:100%; padding:10px; margin:8px 0; border:1px solid #ddd; border-radius:6px; font-size:14px; }
        button { width:100%; padding:12px; background:#007bff; color:#fff; border:none; border-radius:6px; font-size:15px; cursor:pointer; }
        .error { background:#f8d7da; color:#721c24; padding:10px; border-radius:6px; margin-bottom:12px; text-align:center; font-size:14px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔒 Change Your Password</h2>
        <p>Please change your temporary password to continue.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="password" name="old_password" placeholder="Old Password" required>
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="new_password_confirm" placeholder="Confirm New Password" required>
            <button type="submit">Change Password</button>
        </form>
    </div>
</body>
</html>