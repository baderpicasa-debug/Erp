<?php
require_once __DIR__ . '/config.php';

// Require login as admin
if (empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

$conn = db();
$admin_id = $_SESSION['admin_id'];
$message = '';
$error = '';

// Handle form submission for basic info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        die("Invalid request token");
    }
    
    $new_phone   = sanitizeInput($_POST['phone'] ?? '');
    $new_email   = sanitizeInput($_POST['email'] ?? '');
    $new_company = sanitizeInput($_POST['company_name'] ?? '');

    $stmt = $conn->prepare("UPDATE admins SET phone = ?, email = ?, company_name = ? WHERE id = ?");
    $stmt->bind_param("sssi", $new_phone, $new_email, $new_company, $admin_id);
    if ($stmt->execute()) {
        $message = "✅ Profile updated successfully.";
    } else {
        $error = "❌ Failed to update profile: " . $stmt->error;
        logError("Admin profile update failed for ID $admin_id: " . $stmt->error);
    }
    $stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        die("Invalid request token");
    }
    
    $old_password         = $_POST['old_password'] ?? '';
    $new_password         = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';

    // Initialize errors array
    $password_errors = [];

    // Corrected password validation logic
    if ($new_password !== $new_password_confirm) {
        $password_errors[] = "New passwords do not match.";
    }

    // Corrected regex to check for all conditions and minimum length
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $new_password)) {
        $password_errors[] = "New password must be at least 8 characters and contain uppercase, lowercase, number, and special character.";
    }

    if (!empty($password_errors)) {
        $error = "❌ " . implode(" ", $password_errors);
    } else {
        $stmt_pwd = $conn->prepare("SELECT password FROM admins WHERE id = ? LIMIT 1");
        $stmt_pwd->bind_param("i", $admin_id);
        $stmt_pwd->execute();
        $res_pwd = $stmt_pwd->get_result();
        $row_pwd = $res_pwd->fetch_assoc();
        $stmt_pwd->close();

        if ($row_pwd && password_verify($old_password, $row_pwd['password'])) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_upd = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt_upd->bind_param("si", $new_password_hash, $admin_id);
            if ($stmt_upd->execute()) {
                $message .= " ✅ Password changed successfully.";
                logError("Admin password changed successfully for ID: " . $admin_id);
            } else {
                $error .= " ❌ Failed to update password.";
                logError("Admin password update failed for ID " . $admin_id . ": " . $stmt_upd->error);
            }
            $stmt_upd->close();
        } else {
            $error .= " ❌ Incorrect old password.";
            logError("Admin password change attempt with wrong old password for ID: " . $admin_id);
        }
    }
}

// Fetch admin data
$stmt = $conn->prepare("SELECT username, phone, email, company_name FROM admins WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Stats
$stats = [];
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE is_active = 1");
$stats['employees'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM inventory_items");
$stats['items'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM store_orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['orders_month'] = $result->fetch_assoc()['count'];

// Last backup
$last_backup = 'Never';
if (file_exists('logs')) {
    $log_files = glob('logs/backup_*.log');
    if (!empty($log_files)) {
        $latest_log = max($log_files);
        $last_backup = date('Y-m-d H:i', filemtime($latest_log));
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; padding:20px; }
        .container { max-width:800px; margin:auto; }
        .section-box { background:#fff; padding: 20px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 20px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
        h2 { text-align:center; margin-bottom:30px; color:#333; }
        h3 { margin-top:0; color:#444; border-bottom:2px solid #007bff; padding-bottom:8px; }
        label { display:block; margin-top:15px; font-weight:bold; }
        input { width:100%; padding:10px; margin-top:5px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
        button { width:100%; padding:12px; background:#007bff; color:#fff; border:none; border-radius:6px; font-size:15px; cursor:pointer; margin-top:20px; }
        button.danger { background:#dc3545; }
        button.danger:hover { background:#c82333; }
        button:hover { background:#0056b3; }
        .back-btn { display:inline-block; margin-bottom:20px; color:#007bff; text-decoration:none; }
        .message { padding:12px; border-radius:6px; margin-bottom:15px; text-align:center; font-size:14px; }
        .success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .password-requirements { font-size: 12px; color: #666; margin-top: 5px; }
        .stats-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:15px; margin:15px 0; }
        .stat-card { background:#f8f9fa; padding:15px; border-radius:6px; text-align:center; border:1px solid #dee2e6; }
        .stat-number { font-size:24px; font-weight:bold; color:#007bff; margin-bottom:5px; }
        .stat-label { font-size:12px; color:#666; }
        .info-row { display:flex; justify-content:space-between; margin:8px 0; padding:8px 0; border-bottom:1px solid #eee; }
        .info-label { font-weight:bold; color:#555; }
        .info-value { color:#333; }
        .warning-text { color:#dc3545; font-size:12px; margin-top:10px; font-style:italic; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">← Back to Dashboard</a>
        <h2>⚙️ System Settings & Management</h2>
        
        <?php if ($message): ?><div class="message success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="section-box">
            <h3>📊 System Overview</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['employees'] ?></div>
                    <div class="stat-label">Active Employees</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['items'] ?></div>
                    <div class="stat-label">Inventory Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['orders_month'] ?></div>
                    <div class="stat-label">Orders (30 days)</div>
                </div>
            </div>
            
            <div class="info-row">
                <span class="info-label">Last Backup:</span>
                <span class="info-value"><?= htmlspecialchars($last_backup) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">System Admin:</span>
                <span class="info-value"><?= htmlspecialchars($admin_data['username']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Server Time:</span>
                <span class="info-value"><?= date('Y-m-d H:i:s') ?></span>
            </div>
        </div>

        <div class="section-box">
            <h3>👤 Administrator Profile</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                <input type="hidden" name="update_profile" value="1">
                
                <label>Username (Cannot be changed)</label>
                <input type="text" value="<?= htmlspecialchars($admin_data['username'] ?? '') ?>" disabled style="background:#f8f9fa;">
                
                <label>Contact Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($admin_data['phone'] ?? '') ?>">
                
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($admin_data['email'] ?? '') ?>">
                
                <label>Company Name</label>
                <input type="text" name="company_name" value="<?= htmlspecialchars($admin_data['company_name'] ?? '') ?>">
                
                <button type="submit">💾 Update Profile</button>
            </form>
        </div>

        <div class="section-box">
            <h3>🔒 Security Settings</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                <input type="hidden" name="change_password" value="1">
                
                <label>Current Password</label>
                <input type="password" name="old_password" required>
                
                <label>New Password</label>
                <input type="password" name="new_password" required>
                <div class="password-requirements">
                    Must be at least 8 characters with uppercase, lowercase, number, and special character
                </div>
                
                <label>Confirm New Password</label>
                <input type="password" name="new_password_confirm" required>
                
                <button type="submit">🔐 Change Password</button>
            </form>
        </div>

        <div class="section-box" style="border: 2px dashed #dc3545;">
            <h3>⚠️ System Maintenance</h3>
            <p style="color:#dc3545; font-weight:bold;">Use carefully - irreversible actions!</p>
            
            <a href="admin_backup_db.php" onclick="return confirm('Create database backup?');">
                <button type="button" style="background:#28a745; margin-bottom:10px;">⬇️ Create Database Backup</button>
            </a>

            <form method="POST" action="admin_clear_data_confirm.php">
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                <button type="submit" class="danger">🗑️ Clear All System Data</button>
            </form>
            
            <p class="warning-text">
                Make sure you created a backup before clearing data!
            </p>
        </div>
    </div>
</body>
</html>