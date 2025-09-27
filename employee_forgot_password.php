<?php
require_once __DIR__ . '/config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if (!empty($username)) {
        $conn = db();
        
        // Check if the employee exists
        $stmt_emp = $conn->prepare("SELECT id, name FROM employees WHERE name = ? LIMIT 1");
        $stmt_emp->bind_param("s", $username);
        $stmt_emp->execute();
        $employee = $stmt_emp->get_result()->fetch_assoc();
        $stmt_emp->close();

        if ($employee) {
            // Get the admin's contact info from the database
            $stmt_admin = $conn->prepare("SELECT phone, email FROM admins LIMIT 1");
            $stmt_admin->execute();
            $admin = $stmt_admin->get_result()->fetch_assoc();
            $stmt_admin->close();

            if ($admin && !empty($admin['phone'])) {
                // Compose WhatsApp message using the phone number from the database
                $wa_message = "A password reset request has been submitted for employee: " . urlencode($employee['name']);
                $wa_url = "https://wa.me/" . htmlspecialchars($admin['phone']) . "?text=" . $wa_message;
                
                header("Location: " . $wa_url);
                exit;
            } else {
                // If the phone number is not found in the database
                $message = "❌ Admin contact information not found.";
            }

            // Note for future email functionality
            /*
            if ($admin && !empty($admin['email'])) {
                // FUTURE: Add email sending logic here
                // mail($admin['email'], 'Password Reset Request', 'A password reset has been requested for ' . $employee['name']);
            }
            */

        } else {
            $message = "❌ Employee not found. Please check the name.";
        }

        $conn->close();
    } else {
        $message = "⚠️ Please enter your name.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <style>
        body { font-family: Arial; background:#f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
        .box { background:#fff; padding:25px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); width:350px; }
        h2 { text-align:center; margin-bottom:20px; }
        p { text-align: center; }
        input { width:100%; padding:10px; margin:8px 0; border:1px solid #ddd; border-radius:6px; font-size:14px; }
        button { width:100%; padding:12px; background:#007bff; color:#fff; border:none; border-radius:6px; font-size:15px; cursor:pointer; }
        .message { background:#d4edda; color:#155724; padding:10px; border-radius:6px; margin-bottom:12px; text-align:center; font-size:14px; }
        .error { background:#f8d7da; color:#721c24; padding:10px; border-radius:6px; margin-bottom:12px; text-align:center; font-size:14px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>❓ Forgot Password</h2>
        <p>Enter your name to request a password reset.</p>
        <?php if (!empty($message)): ?><div class="error"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="post">
            <input type="text" name="username" placeholder="Employee Name" required autofocus>
            <button type="submit">Send Request</button>
        </form>
    </div>
</body>
</html>