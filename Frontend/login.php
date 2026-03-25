<?php
require_once 'dbcon.php';
require_once 'functions.php';

if (empty($_SESSION)) session_start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $errors[] = 'Email and password are required.';
        } else {
            $stmt = $mysqli->prepare('SELECT id,name,password,role,active,verified FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if ((int)$row['active'] === 0) {
                    $errors[] = 'Account is deactivated. Contact support.';
                } elseif (!password_verify($password, $row['password'])) {
                    $errors[] = 'Incorrect email or password.';
                } else {
                    // success
                    login_user(['id'=>$row['id'],'name'=>$row['name'],'email'=>$email,'role'=>$row['role']]);
                    // Redirect based on role
                    if (($row['role'] ?? '') === 'admin') {
                        header('Location:admin/dashboard.php');
                    } else {
                        header('Location:index.php');
                    }
                    exit;
                }
            } else {
                $errors[] = 'Incorrect email or password.';
            }
            $stmt->close();
        }
    }
}

$token = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>HerSafar — Sign in</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg-a: #fbf9ff;
      --bg-b: #f7f3ff;
      --primary-1: #5b21b6;
      --primary-2: #8b5cf6;
      --accent: #c084fc;
      --muted: #6b4a86;
      --card-bg: rgba(255,255,255,0.56);
      --glass-border: rgba(255,255,255,0.35);
      --shadow: 0 20px 50px rgba(88,40,200,0.08);
      --radius: 14px;
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
      padding:32px;
      background:
        radial-gradient(700px 300px at 10% 10%, rgba(139,92,246,0.05), transparent 10%),
        radial-gradient(600px 260px at 90% 90%, rgba(192,132,252,0.03), transparent 10%),
        linear-gradient(180deg,var(--bg-a),var(--bg-b));
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      color:#241235;
    }

    /* Container */
    .container {
      width:100%;
      max-width:460px;
      margin: 0 16px;
    }

 
    .card {
      background: linear-gradient(180deg, rgba(255,255,255,0.64), rgba(255,255,255,0.44));
      border: 1px solid var(--glass-border);
      border-radius: var(--radius);
      padding: 28px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(8px) saturate(120%);
      -webkit-backdrop-filter: blur(8px) saturate(120%);
      transition: transform 260ms var(--ease), box-shadow 260ms var(--ease);
    }
    .card:focus-within { transform: translateY(-4px); box-shadow: 0 34px 80px rgba(88,40,200,0.12); }


    .brand {
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:20px;
    }
    .logo {
      width:56px;height:56px;border-radius:12px;
      background: linear-gradient(135deg,var(--primary-2),var(--accent));
      color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;
      box-shadow: 0 10px 30px rgba(16,24,40,0.05);
    }
    .brand h1{ margin:0; font-size:20px; color:var(--primary-1); }
    .brand p{ margin:0; color:var(--muted); font-size:13px; }

    /* Form */
    form { display:flex; flex-direction:column; gap:14px; }
    label.field { display:block; font-weight:600; color:var(--primary-1); font-size:13px; margin-bottom:6px; }
    .field-row { display:flex; flex-direction:column; gap:8px; }

    input[type="email"],
    input[type="password"]{
      width:100%;
      padding:12px 14px;
      border-radius:10px;
      border: 1px solid rgba(99,102,241,0.06);
      background: rgba(255,255,255,0.85);
      font-size:15px;
      color:#23123b;
      outline:none;
      transition: box-shadow 180ms var(--ease), border-color 180ms var(--ease), transform 180ms var(--ease);
    }
    input:focus{
      border-color: rgba(139,92,246,0.22);
      box-shadow: 0 12px 40px rgba(109,40,217,0.06);
      transform: translateY(-2px);
    }

    /* Error box */
    .errors {
      background: rgba(255,241,242,0.95);
      border: 1px solid rgba(185,28,58,0.08);
      color: #7f1d1d;
      padding:12px 14px;
      border-radius:10px;
      font-size:14px;
    }
    .errors ul { margin:0; padding-left:18px; }

    /* Primary button */
    .btn {
      appearance:none;
      -webkit-appearance:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      width:100%;
      padding:12px 14px;
      border-radius:12px;
      border:0;
      font-weight:700;
      font-size:16px;
      color:#fff;
      background: linear-gradient(90deg,var(--primary-2),var(--primary-1));
      cursor:pointer;
      box-shadow: 0 16px 40px rgba(91,33,182,0.10);
      transition: transform 180ms var(--ease), box-shadow 180ms var(--ease), filter 120ms var(--ease);
    }
    .btn:hover { transform: translateY(-3px); box-shadow: 0 30px 70px rgba(91,33,182,0.14); }
    .btn:active { transform: translateY(-1px) scale(.998); }

    /* Small link (underlined on hover) */
    .switch {
      margin-top:14px;
      text-align:center;
      font-size:14px;
      color:var(--muted);
    }
    .switch a {
      color:var(--primary-1);
      text-decoration:none;
      font-weight:600;
      position:relative;
      padding-bottom:2px;
    }
    .switch a::after{
      content:"";
      position:absolute;
      left:0; right:0; bottom:-3px;
      height:2px;
      background: linear-gradient(90deg,var(--accent),var(--primary-2));
      transform-origin:left center;
      transform: scaleX(0);
      transition: transform 220ms var(--ease);
      border-radius:2px;
    }
    .switch a:hover::after,
    .switch a:focus::after { transform: scaleX(1); }

    /* Footer note */
    .footer {
      margin-top:18px;
      text-align:center;
      color:var(--muted);
      font-size:13px;
    }

    @media (max-width:480px){
      .card{ padding:20px; border-radius:12px; }
      .logo{ width:48px; height:48px; font-size:16px; }
      .btn{ font-size:15px; padding:12px; border-radius:10px; }
    }
  </style>
</head>
<body>
  <div class="container" role="main">
    <section class="card" aria-labelledby="login-heading">
      <div class="brand" aria-hidden="false">
        <div class="logo" aria-hidden="true">HS</div>
        <div class="title">
          <h1 id="login-heading">HerSafar — Log In</h1>
          <p>Women's Carpooling</p>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="errors" role="alert" aria-live="assertive">
          <strong>Sign in failed</strong>
          <ul>
            <?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" novalidate>
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token); ?>">

        <div class="field-row">
          <label for="email" class="field">Email</label>
          <input id="email" name="email" type="email" autocomplete="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="field-row">
          <label for="password" class="field">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>

        <div>
          <button type="submit" class="btn" aria-label="Sign in">Login</button>
        </div>
      </form>

      <div class="switch">
        Don't have an account?
        <a href="register.php">Create account</a>
      </div>

      <div class="footer">&copy; <?php echo date('Y'); ?> HerSafar — Secure access</div>
    </section>
  </div>
</body>
</html>
