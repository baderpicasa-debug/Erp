<?php
require_once __DIR__ . '/config.php';

// ✅ تحقق من صلاحية الأدمن
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die("❌ Forbidden");
}

// ✅ تحقق CSRF
if (empty($_POST['csrf_token']) || !validateCSRF($_POST['csrf_token'])) {
    http_response_code(400);
    die("❌ Invalid CSRF token.");
}

// إذا تم التأكيد، قم بتوجيهه إلى صفحة الحذف الفعلية
if (isset($_POST['confirm_clear']) && $_POST['confirm_clear'] === 'I CONFIRM') {
    // قم بتوجيه المستخدم إلى صفحة الحذف الفعلية مع توكن جديد
    $_SESSION['temp_clear_token'] = generateCSRF();
    header("Location: admin_clear_data_final.php?token=" . $_SESSION['temp_clear_token']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Data Deletion</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f8f9fa; display:flex; justify-content:center; align-items:center; height:100vh; text-align:center; }
        .container { max-width:600px; padding:30px; background:#fff; border:2px solid #dc3545; border-radius:10px; box-shadow:0 4px 8px rgbargba(0,0,0,0.1); }
        h1 { color:#dc3545; }
        p { color:#6c757d; font-size:1.1em; }
        input[type="text"] { width:80%; padding:10px; margin:15px 0; border:1px solid #dc3545; border-radius:5px; font-size:1em; }
        button { padding:12px 25px; background:#dc3545; color:#fff; border:none; border-radius:5px; font-size:1em; cursor:pointer; }
        button:hover { background:#c82333; }
        .back-btn { display:block; margin-top:20px; color:#007bff; text-decoration:none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ Are you absolutely sure?</h1>
        <p>
            This action will **PERMANENTLY DELETE ALL** system data, including employees, inventory, and orders.
            <br>
            <span style="font-weight:bold; color:#000;">This action cannot be undone.</span>
        </p>
        <p>
            To confirm, type **"I CONFIRM"** in the box below and click the button.
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_POST['csrf_token'] ?>">
            <input type="text" name="confirm_clear" placeholder="Type 'I CONFIRM'" required>
            <button type="submit">Wipe All Data</button>
        </form>
        <a href="admin_settings.php" class="back-btn">← Cancel and go back</a>
    </div>
</body>
</html>