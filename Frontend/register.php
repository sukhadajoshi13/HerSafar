<?php
require_once 'dbcon.php';
require_once 'functions.php';

$errors = [];
$old = ['name'=>'','email'=>'','phone'=>'','gender'=>'female'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $gender = in_array($_POST['gender'] ?? 'female', ['female','male','other']) ? $_POST['gender'] : 'female';

        $old['name'] = $name;
        $old['email'] = $email;
        $old['phone'] = $phone;
        $old['gender'] = $gender;

        // 🚫 Restrict male registration
        if ($gender === 'male') {
            echo "<script>
                alert('Sorry, Registration Is For Women Only!');
                window.location.href = 'register.php';
            </script>";
            exit;
        }

        // validation
        if ($name === '' || $email === '' || $password === '') {
            $errors[] = 'Name, email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if (empty($errors)) {
            // check existing email
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = 'Email already registered. Please login or use password recovery.';
            }
            $stmt->close();
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';
            $verified = 0;
            $active = 1;

            $stmt = $mysqli->prepare('INSERT INTO users (name,email,password,phone,gender,role,verified,active,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $stmt->bind_param('ssssssii', $name, $email, $hash, $phone, $gender, $role, $verified, $active);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $stmt->close();
                // login the new user
                login_user(['id'=>$newId, 'name'=>$name, 'email'=>$email, 'role'=>$role]);
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Registration failed. Try again later.';
            }
        }
    }
}
$token = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>HerSafar — Create Account</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg-a:#fbf9ff;
      --bg-b:#f7f3ff;
      --primary-1:#5b21b6;
      --primary-2:#8b5cf6;
      --accent:#c084fc;
      --muted:#6b4a86;
      --glass-border: rgba(255,255,255,0.35);
      --error-bg: rgba(255,242,242,0.95);
      --error-border: rgba(185,28,58,0.08);
      --radius:14px;
      --shadow: 0 18px 50px rgba(91,33,182,0.08);
      --ease: cubic-bezier(.16,.84,.33,1);
      font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
      color-scheme: light;
    }

    *{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%}
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

    .card {
      width:100%;
      max-width:560px;
      background: linear-gradient(180deg, rgba(255,255,255,0.60), rgba(255,255,255,0.40));
      border: 1px solid var(--glass-border);
      border-radius: var(--radius);
      padding:32px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(10px) saturate(120%);
      -webkit-backdrop-filter: blur(10px) saturate(120%);
      transition: transform 260ms var(--ease), box-shadow 260ms var(--ease);
    }
    .card:focus-within{ transform: translateY(-3px); box-shadow: 0 30px 80px rgba(91,33,182,0.12); }

    .brand { display:flex; gap:12px; align-items:center; margin-bottom:18px; }
    .logo {
      width:64px;height:64px;border-radius:12px;
      background: linear-gradient(135deg,var(--primary-2),var(--accent));
      color:#fff; display:flex;align-items:center;justify-content:center; font-weight:800; font-size:20px;
      box-shadow: 0 12px 36px rgba(16,24,40,0.06);
    }
    .brand h1 { margin:0; font-size:22px; color:var(--primary-1); }
    .brand p { margin:0; color:var(--muted); font-size:13px; }

    .errors {
      background: var(--error-bg);
      border: 1px solid var(--error-border);
      color: #7f1d1d;
      padding:14px;
      border-radius:10px;
      margin-bottom:14px;
      font-size:14px;
    }
    .errors ul { margin:0; padding-left:18px; }

    form{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:14px 16px;
    }
    .full { grid-column: 1 / -1; }

    label.field-label {
      display:block;
      margin-bottom:8px;
      font-weight:600;
      color:var(--primary-1);
      font-size:13px;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    select {
      width:100%;
      padding:12px 14px;
      border-radius:10px;
      border: 1px solid rgba(99,102,241,0.06);
      background: rgba(255,255,255,0.9);
      font-size:15px;
      color:#23123b;
      outline:none;
      transition: box-shadow 180ms var(--ease), border-color 180ms var(--ease), transform 180ms var(--ease);
    }
    input:focus, select:focus {
      border-color: rgba(139,92,246,0.22);
      box-shadow: 0 12px 36px rgba(109,40,217,0.06);
      transform: translateY(-2px);
    }

    .btn-primary {
      grid-column: 1 / -1;
      width:100%;
      padding:12px 14px;
      border-radius:10px;
      border:0;
      font-weight:700;
      font-size:16px;
      color:#fff;
      background: linear-gradient(90deg,var(--primary-2),var(--primary-1));
      cursor:pointer;
      box-shadow: 0 18px 40px rgba(91,33,182,0.10);
      transition: transform 160ms var(--ease), box-shadow 160ms var(--ease);
    }
    .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 30px 70px rgba(91,33,182,0.14); }

    .switch {
      grid-column: 1 / -1;
      margin-top:8px;
      text-align:center;
      color:var(--muted);
      font-size:14px;
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
    .switch a:hover::after{ transform: scaleX(1); }

    .meta { grid-column:1 / -1; margin-top:12px; text-align:center; color:var(--muted); font-size:13px; }

    @media (max-width:720px){
      .card { padding:22px; }
      form { grid-template-columns: 1fr; }
      .logo { width:56px; height:56px; font-size:18px; }
    }
  </style>
</head>
<body>
  <main class="card" role="main" aria-labelledby="create-title">
    <div class="brand">
      <div class="logo" aria-hidden="true">HS</div>
      <div>
        <h1 id="create-title">Create account</h1>
        <p>Join Hersafar — connect, share rides, and travel safely.</p>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="errors" role="alert" aria-live="assertive">
        <strong>Registration failed</strong>
        <ul>
          <?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="register.php" autocomplete="off" novalidate>
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token); ?>">

      <div class="full">
        <label for="name" class="field-label">Full name</label>
        <input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($old['name']); ?>">
      </div>

      <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($old['email']); ?>">
      </div>

      <div>
        <label for="phone" class="field-label">Phone</label>
        <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($old['phone']); ?>">
      </div>

      <div>
        <label for="password" class="field-label">Password</label>
        <input id="password" name="password" type="password" required>
      </div>

      <div>
        <label for="gender" class="field-label">Gender</label>
        <select id="gender" name="gender" aria-label="Gender">
          <option value="female" <?php echo $old['gender']=='female'?'selected':''; ?>>Female</option>
          <option value="male" <?php echo $old['gender']=='male'?'selected':''; ?>>Male</option>
        </select>
      </div>

      <button type="submit" class="btn-primary">Register</button>

      <div class="switch">
        Already have an account?
        <a href="login.php">Login</a>
      </div>

      <div class="meta">&copy; <?php echo date('Y'); ?> Hersafar — Safe and Connected</div>
    </form>
  </main>
</body>
</html>
