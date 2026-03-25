<?php
// change_password.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
    header('Location: login.php'); exit;
}

$errors = [];
$success = null;
$csrf = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Basic validation
        if ($current === '') $errors[] = 'Current password is required.';
        if ($new === '') $errors[] = 'New password is required.';
        if ($confirm === '') $errors[] = 'Please confirm the new password.';
        if ($new !== $confirm) $errors[] = 'New password and confirmation do not match.';
        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters long.';

        if (empty($errors)) {
            // Fetch current hash from DB
            $stmt = $mysqli->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            if (!$stmt) {
                $errors[] = 'DB error: ' . $mysqli->error;
            } else {
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();

                if (!$row) {
                    $errors[] = 'User not found.';
                } else {
                    $hash = $row['password'];
                    if (!password_verify($current, $hash)) {
                        $errors[] = 'Current password is incorrect.';
                    } else {
                        // All good — update password
                        $newHash = password_hash($new, PASSWORD_DEFAULT);
                        $up = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
                        if (!$up) {
                            $errors[] = 'DB error (prepare update): ' . $mysqli->error;
                        } else {
                            $up->bind_param('si', $newHash, $uid);
                            if ($up->execute()) {
                                $success = 'Password changed successfully.';
                                // Optionally: force logout for security:
                                // logout_user(); header('Location: login.php'); exit;
                            } else {
                                $errors[] = 'Failed to update password: ' . $up->error;
                            }
                            $up->close();
                        }
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Change Password — Hersafar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg-a:#fbf9ff;
      --bg-b:#f7f3ff;
      --primary-1:#5b21b6;
      --primary-2:#8b5cf6;
      --accent:#c084fc;
      --muted:#6b4a86;
      --glass-border: rgba(255,255,255,0.35);
      --card-bg: rgba(255,255,255,0.56);
      --error-bg: #fff1f0;
      --error-border: rgba(185,28,58,0.08);
      --ok-bg: #ecfdf5;
      --ok-border: rgba(6,95,70,0.08);
      --radius:14px;
      --shadow: 0 20px 50px rgba(88,40,200,0.08);
      --ease: cubic-bezier(.16,.84,.33,1);
      font-family: 'Poppins', system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      color-scheme: light;
    }
    *{box-sizing:border-box}
    html,body{height:100%;margin:0}
    body{
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:28px;
      background:
        radial-gradient(700px 300px at 10% 10%, rgba(139,92,246,0.045), transparent 10%),
        radial-gradient(600px 260px at 90% 90%, rgba(192,132,252,0.03), transparent 10%),
        linear-gradient(180deg,var(--bg-a),var(--bg-b));
      -webkit-font-smoothing:antialiased;
      color:#241235;
    }

    .card{
      width:100%;
      max-width:640px;
      background: linear-gradient(180deg, rgba(255,255,255,0.60), rgba(255,255,255,0.42));
      border:1px solid var(--glass-border);
      border-radius:var(--radius);
      padding:28px;
      box-shadow:var(--shadow);
      backdrop-filter: blur(10px) saturate(120%);
      transition: transform 260ms var(--ease), box-shadow 260ms var(--ease);
    }
    .card:focus-within{ transform: translateY(-4px); box-shadow: 0 34px 80px rgba(88,40,200,0.12); }

    header {
      display:flex;
      align-items:center;
      gap:14px;
      margin-bottom:16px;
    }
    .logo {
      width:56px;height:56px;border-radius:12px;
      background:linear-gradient(135deg,var(--primary-2),var(--accent));
      display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px;
    }
    header h1{ margin:0; font-size:20px; color:var(--primary-1); }
    header p{ margin:0; color:var(--muted); font-size:13px; }

    .grid{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:14px 16px;
      align-items:start;
    }
    .full { grid-column: 1 / -1; }

    label { display:block; font-weight:600; color:var(--primary-1); margin-bottom:8px; font-size:13px; }
    input[type="password"]{
      width:100%;
      padding:12px 14px;
      border-radius:10px;
      border:1px solid rgba(99,102,241,0.06);
      background: rgba(255,255,255,0.92);
      font-size:15px;
      color:#23123b;
      outline:none;
      transition: box-shadow 180ms var(--ease), border-color 180ms var(--ease), transform 180ms var(--ease);
    }
    input:focus{
      border-color: rgba(139,92,246,0.22);
      box-shadow: 0 12px 36px rgba(109,40,217,0.06);
      transform: translateY(-2px);
    }

    .muted { color:var(--muted); font-size:13px; margin-top:6px; }

    .btn {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding:12px 16px;
      border-radius:10px;
      border:0;
      background: linear-gradient(90deg,var(--primary-2),var(--primary-1));
      color:#fff;
      font-weight:700;
      cursor:pointer;
      box-shadow: 0 16px 40px rgba(91,33,182,0.10);
      transition: transform 160ms var(--ease), box-shadow 160ms var(--ease);
    }
    .btn:hover { transform: translateY(-3px); box-shadow: 0 30px 70px rgba(91,33,182,0.14); }

    /* Add this CSS anywhere inside your <style> */
.back-link {
  position: absolute;
  top: 20px;
  right: 24px;
  color: var(--muted);            /* light purple-gray tone */
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  transition: color 160ms var(--ease);
}

.back-link:hover {
  color: var(--primary-1);        /* darker purple on hover */
  text-decoration: underline;
  text-underline-offset: 3px;
}


    .msg-err {
      background: var(--error-bg);
      border: 1px solid var(--error-border);
      color: #7f1d1d;
      padding:12px 14px;
      border-radius:10px;
      margin-bottom:12px;
    }
    .msg-ok {
      background: var(--ok-bg);
      border: 1px solid var(--ok-border);
      color: #065f46;
      padding:12px 14px;
      border-radius:10px;
      margin-bottom:12px;
    }

    .footer { margin-top:18px; text-align:right; color:var(--muted); font-size:13px; }

    @media(max-width:720px){
      .grid { grid-template-columns:1fr; }
      .footer { text-align:center; margin-top:18px; }
    }
  </style>
</head>
<body>
  <main class="card" role="main" aria-labelledby="change-password-title">
    <header>
      <div class="logo" aria-hidden="true">HS</div>
      <div>
        <h1 id="change-password-title">Change Password</h1>
        <p>Keep your account secure — choose a strong password.</p>
      </div>
    </header>

    <?php if (!empty($errors)): ?>
      <div class="msg-err" role="alert" aria-live="assertive">
        <strong>There was a problem</strong>
        <ul style="margin:8px 0 0 18px; padding:0;">
          <?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="msg-ok" role="status" aria-live="polite"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="change_password.php" autocomplete="off" novalidate>
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

      <div class="grid">
        <div class="full">
          <label for="current_password">Current password</label>
          <input id="current_password" name="current_password" type="password" required>
        </div>

        <div>
          <label for="new_password">New password</label>
          <input id="new_password" name="new_password" type="password" required>
        </div>

        <div>
          <label for="confirm_password">Confirm new password</label>
          <input id="confirm_password" name="confirm_password" type="password" required>
        </div>

        <div class="full muted">Choose a strong password (minimum 8 characters). Consider using letters, numbers and symbols.</div>

        <div class="full" style="display:flex;gap:12px;align-items:center;justify-content:flex-start;margin-top:6px">
          <button type="submit" class="btn" aria-label="Change password">Change password</button>
          <a href="Dashboard.php" class="back-link" aria-label="Back to dashboard">Back</a>
        </div>
      </div>
    </form>

    <div class="footer">&copy; <?php echo date('Y'); ?> HerSafar</div>
  </main>
</body>
</html>
