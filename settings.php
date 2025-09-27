<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['is_employee']) && empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

// Redirect Admin to their settings page
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    header('Location: admin_settings.php');
    exit;
}

$conn = db();
$employee_id = $_SESSION['employee_id'];
$message = '';
$error = '';

// Handle form submission for updating profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // CSRF Protection
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        die("Invalid request token");
    }
    
    $name = sanitizeInput($_POST['name']);
    $branch = sanitizeInput($_POST['branch']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);

    if ($name && $branch) {
        $stmt = $conn->prepare("UPDATE employees SET name=?, branch=?, email=?, phone=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $branch, $email, $phone, $employee_id);
        if ($stmt->execute()) {
            $_SESSION['employee_name'] = $name; // Update session with new name
            $message = "✅ Profile updated successfully!";
            logError("Employee profile updated for ID: $employee_id");
        } else {
            $error = "❌ Failed to update profile.";
            logError("Employee profile update failed for ID $employee_id: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $error = "❌ Name and branch are required fields.";
    }
}

// Handle password change if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // CSRF Protection
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        die("Invalid request token");
    }
    
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';

    // Password strength validation
    if (strlen($new_password) < 8) {
        $error = "❌ New password must be at least 8 characters long.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $new_password)) {
        $error = "❌ New password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
    } else {
        // Fetch current password hash from employees_auth table
        $stmt_pwd = $conn->prepare("SELECT password FROM employees_auth WHERE employee_id = ? LIMIT 1");
        $stmt_pwd->bind_param("i", $employee_id);
        $stmt_pwd->execute();
        $res_pwd = $stmt_pwd->get_result();
        $row_pwd = $res_pwd->fetch_assoc();
        $stmt_pwd->close();

        if ($row_pwd && password_verify($old_password, $row_pwd['password'])) {
            if ($new_password === $new_password_confirm && !empty($new_password)) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_upd = $conn->prepare("UPDATE employees_auth SET password = ?, is_temp_password = 0 WHERE employee_id = ?");
                $stmt_upd->bind_param("si", $new_password_hash, $employee_id);
                if ($stmt_upd->execute()) {
                    $message = "✅ Password changed successfully!";
                    unset($_SESSION['force_password_change']);
                    logError("Employee password changed successfully for ID: $employee_id");
                } else {
                    $error = "❌ Failed to change password.";
                    logError("Employee password update failed for ID $employee_id: " . $stmt_upd->error);
                }
                $stmt_upd->close();
            } else {
                $error = "❌ New passwords do not match or are empty.";
            }
        } else {
            $error = "❌ Incorrect old password.";
            logError("Employee password change attempt with wrong old password for ID: $employee_id");
        }
    }
}

// Fetch employee data
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 20px; }
        .container { background: #fff; padding: 25px; border-radius: 8px; max-width: 500px; margin: auto; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; }
        input { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 6px; }
        button { width: 100%; padding: 12px; background: #007bff; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #c82333; }
        button:hover { background: #0056b3; }
        .back-btn { display: block; text-align: center; margin-top: 15px; }
        .message { padding: 10px; border-radius: 6px; margin-bottom: 12px; text-align: center; font-size: 14px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .form-section { border-top: 1px solid #eee; margin-top: 20px; padding-top: 20px; }
        .password-requirements { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>✏️ My Profile</h2>
        <?php if ($message): ?><div class="message success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="update_profile" value="1">
            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($employee['name']) ?>" required>
            <label>Branch</label>
            <input type="text" name="branch" value="<?= htmlspecialchars($employee['branch']) ?>" required>
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($employee['email']) ?>">
            <label>Phone</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($employee['phone']) ?>">
            <button type="submit">💾 Save Changes</button>
        </form>

        <div class="form-section">
            <h3>🔒 Change Password</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                <input type="hidden" name="change_password" value="1">
                <label>Old Password</label>
                <input type="password" name="old_password" placeholder="Enter old password" required>
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password" required>
                <div class="password-requirements">
                    Password must be at least 8 characters and contain: uppercase letter, lowercase letter, number, and special character (@$!%*?&)
                </div>
                <label>Confirm New Password</label>
                <input type="password" name="new_password_confirm" placeholder="Confirm new password" required>
                <button type="submit">🔒 Change Password</button>
            </form>
        </div>

        <a href="index.php" class="back-btn">⬅️ Back to Dashboard</a>
    </div>
</body>
</html>