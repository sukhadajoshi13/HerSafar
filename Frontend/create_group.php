<?php
require_once 'dbcon.php';
require_once 'functions.php';
require_login();

if (session_status() === PHP_SESSION_NONE) session_start();

$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $errors[] = 'Group name is required.';
        } else {
            try {
                $token = bin2hex(random_bytes(24));
            } catch (Exception $e) {
                $token = substr(bin2hex(openssl_random_pseudo_bytes(24)), 0, 48);
            }

            $stmt = $mysqli->prepare("INSERT INTO groups (owner_id, name, description, join_token) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('isss', $uid, $name, $desc, $token);
                if ($stmt->execute()) {
                    $group_id = $stmt->insert_id;
                    $stmt->close();

                    $stmt2 = $mysqli->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')");
                    if ($stmt2) {
                        $stmt2->bind_param('ii', $group_id, $uid);
                        $stmt2->execute();
                        $stmt2->close();
                    }

                    header('Location: groups.php');
                    exit;
                } else {
                    $errors[] = 'Failed to create group: ' . $stmt->error;
                    $stmt->close();
                }
            } else {
                $errors[] = 'Database error: ' . $mysqli->error;
            }
        }
    }
}

$csrf = csrf_token();
$current_user_name = htmlspecialchars($_SESSION['user']['name']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Group — HerSafar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg-a:#fbf9ff;
      --bg-b:#f7f3ff;
      --primary-1:#5b21b6;
      --primary-2:#8b5cf6;
      --accent:#c084fc;
      --muted:#6b4a86;
      --glass-border: rgba(255,255,255,0.35);
      --radius:12px;
      --shadow:0 18px 50px rgba(91,33,182,0.08);
      font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
    }
    *{box-sizing:border-box}
    html,body{height:100%;margin:0}
    body{
      display:flex;gap:20px;padding:20px;
      background:radial-gradient(700px 300px at 10% 10%, rgba(139,92,246,0.045), transparent 10%),
                 radial-gradient(600px 260px at 90% 90%, rgba(192,132,252,0.03), transparent 10%),
                 linear-gradient(180deg,var(--bg-a),var(--bg-b));
      color:#241235;
      -webkit-font-smoothing:antialiased;
    }

    /* SIDEBAR */
    .sidebar{
      width:260px; background: linear-gradient(90deg, rgba(75,23,146,0.78), rgba(109,64,186,0.65), rgba(145,90,220,0.55));
      display:flex;flex-direction:column;padding:22px;border-radius:14px;
      box-shadow:3px 8px 30px rgba(168,85,247,0.08);flex-shrink:0;
    }
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
    .logo-circle{width:46px;height:46px;border-radius:12px;background:white;color:#9333ea;
      display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px}
    .brand-text{font-weight:700;color:white;font-size:16px}
    .brand-sub{font-size:12px;color:white;opacity:0.95}
    .nav{display:flex;flex-direction:column;gap:8px;margin-top:8px}
    .nav a{color:#fff;text-decoration:none;padding:10px 12px;border-radius:10px;
      font-weight:600;border:1px solid rgba(255,255,255,0.12);transition:all .18s;font-size:13px;}
    .nav a:hover{background:rgba(255,255,255,0.08);transform:translateY(-2px)}
    .nav a.active{background:rgba(255,255,255,0.15)}
    .spacer{flex:1}
    .bottom-links{display:flex;flex-direction:column;gap:8px;margin-top:12px}
    .bottom-links a{display:block;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.95);
      color:#a855f7;text-decoration:none;font-weight:700;text-align:center;border:1px solid rgba(255,255,255,0.3)}
    .bottom-links a:hover{background:#f3e8ff;color:#6b21b6}
    .logged-user{text-align:center;font-size:13px;color:white;margin-top:16px;opacity:0.95}

    /* MAIN */
    .main{flex:1;display:flex;flex-direction:column;gap:12px}
    .card{
      width:100%;max-width:1100px;background:linear-gradient(180deg,rgba(255,255,255,0.62),rgba(255,255,255,0.44));
      border:1px solid var(--glass-border);border-radius:var(--radius);
      padding:22px;box-shadow:var(--shadow);backdrop-filter:blur(10px) saturate(120%);
    }
    .header-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
    .logo{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,var(--primary-2),var(--accent));
      display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:18px}
    .h-title{font-size:20px;margin:0;color:var(--primary-1)}
    .h-sub{margin:0;color:var(--muted);font-size:13px}
    label{display:block;margin-top:12px;font-weight:600;color:var(--primary-1)}
    input[type="text"], textarea{
      width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(99,102,241,0.06);
      background:rgba(255,255,255,0.95);font-size:15px;outline:none;
    }
    textarea{min-height:100px;resize:vertical}
    .actions{margin-top:18px;display:flex;gap:12px}
   .btn {
  padding: 10px 18px; /* reduced from 14x24 */
  border: none;
  border-radius: 8px;
  background: linear-gradient(90deg, var(--primary-2), var(--primary-1));
  color: #fff;
  font-weight: 600; /* slightly lighter weight for balance */
  font-size: 14px;  /* smaller font */
  cursor: pointer;
  box-shadow: 0 6px 18px rgba(91, 33, 182, 0.15);
  transition: all 0.2s ease;
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 30px rgba(91, 33, 182, 0.25);
}

/* Muted / secondary button */
.btn-muted {
  background: rgba(243, 244, 246, 0.9);
  color: #0f172a;
  border: 1px solid rgba(15, 23, 42, 0.06);
  padding: 9px 14px; /* smaller padding */
  border-radius: 8px;
  font-weight: 600;
  font-size: 13px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.btn-muted:hover {
  background: rgba(229, 231, 235, 0.95);
  transform: translateY(-2px);
}

    .err{background:#fff1f2;border-radius:10px;padding:12px;color:#7f1d1d;margin-bottom:12px}
    .footer{
      position:fixed;right:16px;bottom:10px;z-index:999;font-size:12px;color:var(--muted);
      background:rgba(255,255,255,0.6);padding:6px 10px;border-radius:8px;
      border:1px solid rgba(99,102,241,0.06);backdrop-filter:blur(6px) saturate(120%);
      opacity:.95;
    }
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="brand">
      <div class="logo-circle">HS</div>
      <div>
        <div class="brand-text">HerSafar</div>
        <div class="brand-sub">User Dashboard</div>
      </div>
    </div>

     <nav class="nav" aria-label="Main navigation">
      <a href="dashboard.php">Dashboard</a>
      <a href="index.php">Home</a>
      <a href="profile.php">Profile</a>
      <a href="groups.php"class="active">Groups</a>
      <a href="manage_rides.php">Manage Bookings</a>
      <a href="join_ride.php">Join A ride</a>
      <a href="apply_driver.php">Driver applications</a>
    </nav>

    <div class="spacer"></div>
    <div class="logged-user">
      Signed in as<br><strong><?= $current_user_name ?></strong>
    </div>

    <div class="bottom-links">
      <a href="change_password.php">Change Password</a>
      <a href="logout.php">Logout</a>
    </div>
  </aside>

  <main class="main">
    <div class="card">
      <div class="header-row">
        <div style="display:flex;align-items:center;gap:12px">
          <div>
            <h2 class="h-title">Create a Travel Group</h2>
            <div class="h-sub">Organize your travel community easily</div>
          </div>
        </div>
        <div class="small">Welcome, <?= $current_user_name ?></div>
      </div>

      <?php if ($errors): ?>
        <div class="err"><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div>
      <?php endif; ?>

      <form method="POST" action="create_group.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <label for="name">Group name</label>
        <input id="name" name="name" type="text" required placeholder="E.g., Office Carpool Team A">

        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" placeholder="Purpose, meeting point, rules..."></textarea>

        <div class="actions">
          <button type="submit" class="btn">Create group</button>
          <a href="groups.php" class="btn-muted">Cancel</a>
        </div>
      </form>
    </div>
  </main>
  <div class="footer">&copy; <?= date('Y') ?> HerSafar — Safe, Smart, and Connected Travel</div>
</body>
</html>
