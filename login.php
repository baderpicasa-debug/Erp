<?php
require_once __DIR__ . '/config.php';

$error = "";

/**
 * Policy
 */
define('MAX_FAILED_ATTEMPTS', 5);      // عدد المحاولات قبل القفل
define('LOCKOUT_MINUTES', 15);        // مدة القفل بالدقائق
define('WINDOW_MINUTES', 15);         // نافذة احتساب المحاولات

function client_ip() {
    // بسيطة وفعالة لمعظم الحالات
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // ممكن تحتوي على عدة عناوين، خذ الأول
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function record_login_attempt($conn, $username, $ip, $ok) {
    // استخدم ip_address في الاستعلام بدلاً من ip
    $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, ok) VALUES (?, ?, ?)");
    if ($stmt) {
        $okInt = $ok ? 1 : 0;
        $stmt->bind_param('ssi', $username, $ip, $okInt);
        $stmt->execute();
        $stmt->close();
    }
}
function count_failed_attempts($conn, $username, $ip) {
    // عدد المحاولات الفاشلة للـ username أو الـ ip في نافذة WINDOW_MINUTES
    $stmt = $conn->prepare("
      SELECT
        (SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ok = 0 AND ts > (NOW() - INTERVAL ? MINUTE)) AS user_fails,
        (SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND ok = 0 AND ts > (NOW() - INTERVAL ? MINUTE)) AS ip_fails,
        (SELECT MAX(ts) FROM login_attempts WHERE username = ? AND ok = 0) AS last_user_fail
    ");
    if (!$stmt) return [0, 0, null];
    $window = WINDOW_MINUTES;
    $stmt->bind_param('sisss', $username, $window, $ip, $window, $username);
    $stmt->execute();
    $stmt->bind_result($userFails, $ipFails, $lastUserFail);
    $stmt->fetch();
    $stmt->close();
    return [$userFails ?? 0, $ipFails ?? 0, $lastUserFail];
}

function is_locked($conn, $username, $ip) {
    list($userFails, $ipFails, $lastUserFail) = count_failed_attempts($conn, $username, $ip);
    if ($userFails >= MAX_FAILED_ATTEMPTS || $ipFails >= MAX_FAILED_ATTEMPTS) {
        // احسب الزمن المتبقي إذا وُجد آخر فشل
        if ($lastUserFail) {
            $lockedUntil = strtotime($lastUserFail) + (LOCKOUT_MINUTES * 60);
            $now = time();
            if ($now < $lockedUntil) {
                return $lockedUntil - $now; // ثواني متبقية
            }
        }
        // إذا لم نتمكن من حساب آخر محاولة، اعطِ قفل افتراضي
        return LOCKOUT_MINUTES * 60;
    }
    return 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // حد الطول لتقليل مساحة الهجوم
    if (strlen($username) > 255) $username = substr($username, 0, 255);
    if (strlen($password) > 200) $password = substr($password, 0, 200);

    if ($username && $password) {
        $conn = db();
        $ip = client_ip();

        // قبل أي تحقق، افحص إن الحساب/الـ IP مقفول حاليًا
        $remainingLockSeconds = is_locked($conn, $username, $ip);
        if ($remainingLockSeconds > 0) {
            $mins = ceil($remainingLockSeconds / 60);
            $error = "🚫 Too many failed attempts. Try again after {$mins} minute(s).";
        } else {
            // حاول تسجيل الدخول كـ Admin
            $stmt_admin = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ? LIMIT 1");
            if ($stmt_admin) {
                $stmt_admin->bind_param("s", $username);
                $stmt_admin->execute();
                $res_admin = $stmt_admin->get_result();

                $authenticated = false;
                $userType = null;
                $userRow = null;

                if ($res_admin && $res_admin->num_rows === 1) {
                    $row = $res_admin->fetch_assoc();
                    if (password_verify($password, $row['password'])) {
                        $authenticated = true;
                        $userType = 'admin';
                        $userRow = $row;
                    }
                }
                $stmt_admin->close();

                if (!$authenticated) {
                    // إذا لم ينجح كأدمن: جرب الموظف
                    // ملاحظة: يفضل أن يكون في employees حقل username؛ لو ما موجود، الحد الأدنى تحقق من طول/حروف الاسم
                    $stmt_employee = $conn->prepare("
                        SELECT ea.password, ea.is_temp_password, e.id, e.name
                        FROM employees_auth ea
                        JOIN employees e ON ea.employee_id = e.id
                        WHERE e.name = ? AND e.is_active = 1
                        LIMIT 1
                    ");
                    if ($stmt_employee) {
                        $stmt_employee->bind_param("s", $username);
                        $stmt_employee->execute();
                        $res_employee = $stmt_employee->get_result();

                        if ($res_employee && $res_employee->num_rows === 1) {
                            $row = $res_employee->fetch_assoc();
                            if (password_verify($password, $row['password'])) {
                                $authenticated = true;
                                $userType = 'employee';
                                $userRow = $row;
                            }
                        }
                        $stmt_employee->close();
                    }
                }

                if ($authenticated && $userType === 'admin') {
                    // تسجيل نجاح
                    record_login_attempt($conn, $username, $ip, true);

                    session_regenerate_id(true);
                    $_SESSION['is_admin'] = true;
                    $_SESSION['admin_id'] = $userRow['id'];
                    $_SESSION['admin_name'] = $userRow['username'];
                    header("Location: index.php");
                    exit;
                } elseif ($authenticated && $userType === 'employee') {
                    // تسجيل نجاح
                    record_login_attempt($conn, $username, $ip, true);

                    session_regenerate_id(true);
                    $_SESSION['is_employee'] = true;
                    $_SESSION['employee_id'] = $userRow['id'];
                    $_SESSION['employee_name'] = $userRow['name'];

                    if ($userRow['is_temp_password'] == 1) {
                        $_SESSION['force_password_change'] = true;
                        header("Location: change_password.php");
                        exit;
                    } else {
                        header("Location: index.php");
                        exit;
                    }
                } else {
                    // فشل: سجّل المحاولة الفاشلة
                    record_login_attempt($conn, $username, $ip, false);

                    // تأخير بسيط عشوائي لإبطاء الهجمات الآلية
                    usleep(rand(200000, 700000)); // 0.2 - 0.7 ثانية

                    // أعد حساب الفشل للتحقق من إنّنا وصلنا للحدّ الأعلى الآن
                    $remaining = is_locked($conn, $username, $ip);
                    if ($remaining > 0) {
                        $mins = ceil($remaining / 60);
                        $error = "🚫 Too many failed attempts. Account/IP locked for {$mins} minute(s).";
                    } else {
                        // رسالة عامة لا تكشف سبب الفشل
                        $error = "❌ Invalid username or password.";
                    }
                }
            } else {
                // فشل في إعداد statement (نادر) - لا تعرض تفاصيل DB
                $error = "❌ Login failed. Please try again later.";
            }
        }
    } else {
        $error = "⚠️ Please enter both fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
<style>
  body { font-family: Arial; background:#f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
  .login-box { background:#fff; padding:25px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); width:350px; }
  h2 { text-align:center; margin-bottom:20px; }
  input { width:100%; padding:10px; margin:8px 0; border:1px solid #ddd; border-radius:6px; font-size:14px; }
  button { width:100%; padding:12px; background:#007bff; color:#fff; border:none; border-radius:6px; font-size:15px; cursor:pointer; }
  button:hover { background:#0056b3; }
  .error { background:#f8d7da; color:#721c24; padding:10px; border-radius:6px; margin-bottom:12px; text-align:center; font-size:14px; }
  .forgot-link { display:block; text-align:center; margin-top:10px; font-size:14px; }
</style>
</head>
<body>
  <div class="login-box">
    <h2>🔐 Login</h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="text" name="username" placeholder="Username" required autofocus maxlength="255">
      <input type="password" name="password" placeholder="Password" required maxlength="200">
      <button type="submit">Login</button>
    </form>
    <a href="employee_forgot_password.php" class="forgot-link">Forgot Password?</a>
  </div>
</body>
</html>