<?php
require_once __DIR__ . '/config.php';

// Check for admin login
if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php');
    exit;
}

$conn = db();
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$new_password = null;

/**
 * Generates a strong, random password.
 * @param int $length The desired length of the password.
 * @return string A secure, randomly generated password.
 */
function generateSecurePassword($length = 16) {
    $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle(str_repeat($charset, ceil($length/strlen($charset)))), 0, $length);
}


if ($employee_id > 0) {
    // Generate a new temporary password
    $new_password_plain = generateSecurePassword();
    $new_password_hash = password_hash($new_password_plain, PASSWORD_DEFAULT);

    // Check if employee exists
    $stmt = $conn->prepare("SELECT id FROM employees_auth WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        // Update the password and set the 'is_temp_password' flag
        $stmt_upd = $conn->prepare("UPDATE employees_auth SET password = ?, is_temp_password = 1 WHERE employee_id = ?");
        $stmt_upd->bind_param("si", $new_password_hash, $employee_id);
        $stmt_upd->execute();
        $stmt_upd->close();
        $new_password = $new_password_plain;
    } else {
        // If the employee doesn't have an auth entry, create one and set the flag
        $stmt_ins = $conn->prepare("INSERT INTO employees_auth (employee_id, password, is_temp_password) VALUES (?, ?, 1)");
        $stmt_ins->bind_param("is", $employee_id, $new_password_hash);
        $stmt_ins->execute();
        $stmt_ins->close();
        $new_password = $new_password_plain;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <style>
        body { font-family: Arial; background: #f4f6f8; text-align: center; padding: 50px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); display: inline-block; }
        h2 { color: #28a745; }
        .password { font-size: 24px; font-weight: bold; background: #eee; padding: 10px; border-radius: 4px; margin: 20px 0; }
        .button-back { background: #007bff; color: #fff; padding: 10px 15px; border: none; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($new_password): ?>
            <h2>✅ Password Reset Successful</h2>
            <p>The new temporary password is:</p>
            <div class="password"><?= htmlspecialchars($new_password) ?></div>
            <p>Please provide this password to the employee immediately.</p>
        <?php else: ?>
            <p>❌ Invalid employee ID.</p>
        <?php endif; ?>
        <a href="admin_employees.php" class="button-back">⬅️ Back to Employees</a>
    </div>
</body>
</html>