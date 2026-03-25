<?php
require_once __DIR__ . '/../dbcon.php';
require_once __DIR__ . '/../functions.php';
session_start();
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: /login.php'); exit;
}


if (! empty($_SESSION['admin_msg'])) {
    $m = $_SESSION['admin_msg'];
    $style = $m['type'] === 'success' ? 'color:#065f46' : ($m['type'] === 'error' ? 'color:#b91c1c' : 'color:#7e22ce');
    $flash_html = "<div style=\"$style;margin-bottom:12px;padding:10px 12px;border-radius:8px;background:#fff\">".htmlspecialchars($m['text'])."</div>";
    unset($_SESSION['admin_msg']);
} else {
    $flash_html = '';
}

$roleFilter = $_GET['role'] ?? ''; 

$where = '';
if ($roleFilter === 'driver') {
    $where = " WHERE role = 'driver' ";
} elseif ($roleFilter === 'user') {
    $where = " WHERE role IN ('user','passenger','rider') ";
}

$sql = "SELECT id,name,email,phone,role,verified,active,created_at FROM users" . $where . " ORDER BY created_at DESC LIMIT 200";

$res = $mysqli->query($sql);
$users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Users — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  a{color:inherit;text-decoration:none}
  body{
    font-family:'Poppins',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
    background:#fdfcff;
    color:#2d1b4e;
  }
  .dashboard{display:flex;min-height:100vh;}
  /* Sidebar */
  .sidebar{
    width:260px;
    background: linear-gradient(90deg, rgba(75,23,146,0.78), rgba(109,64,186,0.65), rgba(145,90,220,0.55));
    color:#fff;
    display:flex;
    flex-direction:column;
    padding:28px 22px;
    box-shadow:3px 0 18px rgba(180,80,255,0.15);
  }
  .brand{display:flex;align-items:center;gap:10px;margin-bottom:40px}
  .logo-circle{width:46px;height:46px;border-radius:12px;background:white;color:#9333ea;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;box-shadow:0 3px 10px rgba(255,255,255,0.3)}
  .brand-text{font-weight:700;font-size:18px}
  .brand-sub{font-size:12px;opacity:0.9}

  .nav{display:flex;flex-direction:column;gap:8px;flex-grow:1}
  .nav a{color:#fff;text-decoration:none;padding:10px 12px;border-radius:8px;font-weight:500;border:1px solid rgba(255,255,255,0.4);transition:all 0.25s ease}
  .nav a:hover{background:rgba(255,255,255,0.15);transform:translateY(-2px)}

  .logout{margin-top:auto;margin-bottom:10px;text-align:center}
  .logout a{display:block;background:white;color:#a855f7;padding:10px 12px;border-radius:8px;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,0.5);transition:all 0.25s}
  .logout a:hover{background:#f3e8ff;color:#9333ea}

  .logged-user{margin-top:20px;font-size:13px;text-align:center;color:#fff;opacity:0.9}
  /* Main area */
  .main{flex:1;padding:30px;background:linear-gradient(180deg,#fff,#faf5ff);position:relative}
  header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
  header h1{font-size:24px;font-weight:700;color:#5b21b6}
  header p{color:#7e22ce;font-size:14px}
  .filters{display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
  .chip{padding:8px 12px;border-radius:10px;border:1px solid #f3e8ff;background:#fff;font-weight:600;color:#5b21b6}
  .chip.active{background:linear-gradient(90deg,#e879f9,#c084fc);color:#fff;border:none}

  .panel{background:white;border-radius:16px;padding:18px;border:1px solid #f3e8ff;box-shadow:0 8px 24px rgba(150,90,255,0.06)}

  /* Table */
  table{width:100%;border-collapse:collapse;font-size:14px}
  thead th{background:#faf5ff;color:#5b21b6;padding:12px;text-align:left;font-weight:700}
  tbody td{padding:12px;border-bottom:1px solid #f5f3ff;vertical-align:middle}
  tbody tr:hover td{background:#fff7ff}
  .role-badge{background:#f5d0fe;color:#7e22ce;padding:6px 10px;border-radius:999px;font-weight:700;font-size:13px;display:inline-block}
  .badge-yes{background:#ecfdf5;color:#065f46;padding:6px 8px;border-radius:8px;font-weight:700}
  .badge-no{background:#fff1f2;color:#9b1c1c;padding:6px 8px;border-radius:8px;font-weight:700}

  /* Actions layout stacked */
  .actions {display:flex;flex-direction:column;gap:8px;align-items:stretch}
  .action-form { margin:0; } /* remove default spacing for forms */

  .btn {
    padding:10px 12px;border-radius:8px;border:0;cursor:pointer;font-weight:600;font-size:14px;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;box-sizing:border-box;transition:all .14s ease;text-decoration:none;
  }

  /* lighter soft button colors */
  .btn-view {background:#fbf7ff;color:#5b21b6;border:1px solid #f3e8ff}
  .btn-view:hover { background:#f3eefc; }

  .btn-verify {background:#ecfdf5;color:#065f46;border:1px solid rgba(6,95,70,0.08)}
  .btn-verify:hover { background:#ddfbe8; }

  .btn-unverify {background:#f3f4f6;color:#374151;border:1px solid rgba(55,65,81,0.06)}
  .btn-unverify:hover { background:#e9eaf0; }

  .btn-active {background:#eef2ff;color:#1e40af;border:1px solid rgba(30,64,175,0.07)}
  .btn-active:hover { background:#e3e9ff; }

  .btn-inactive {background:#fff1f2;color:#9b1c1c;border:1px solid rgba(155,28,28,0.06)}
  .btn-inactive:hover { background:#ffe5e7; }

  /* Narrower action column so stacked buttons don't push table too wide */
  .col-action { width:220px; max-width:220px; }

  footer{position:absolute;right:20px;bottom:10px;font-size:13px;color:#7e22ce;text-align:right}
  @media(max-width:900px){
    .sidebar{display:none}
    header{flex-direction:column;align-items:flex-start;gap:8px}
    footer{position:static;margin-top:22px;text-align:center}
    .col-action{width:auto;max-width:none}
  }
</style>
</head>
<body>
<div class="dashboard">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <div class="logo-circle">HS</div>
      <div>
        <div class="brand-text">HerSafar Admin</div>
        <div class="brand-sub">Dashboard Panel</div>
      </div>
    </div>

    <nav class="nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="users.php" style="background:rgba(255,255,255,0.08)">Manage Users</a>
      <a href="rides_admin.php">Manage Rides</a>
    </nav>

   <div class="logout">
  <a href="../login.php">Logout</a>
</div>

    <div class="logged-user">
      Logged in as: <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong>
    </div>
  </aside>

  <!-- Main -->
  <main class="main">
    <header>
      <div>
        <h1>Manage Users</h1>
        <p>View, verify and manage platform users</p>
      </div>
    </header>

    <?php echo $flash_html; ?>

    <div class="filters" role="toolbar" aria-label="User filters">
      <a class="chip <?php echo ($roleFilter===''?'active':''); ?>" href="users.php">All</a>
      <a class="chip <?php echo ($roleFilter==='driver'?'active':''); ?>" href="users.php?role=driver">Drivers</a>
      <a class="chip <?php echo ($roleFilter==='user'?'active':''); ?>" href="users.php?role=user">Passengers</a>
    </div>

    <section class="panel" aria-labelledby="recent-users">
      <h2 id="recent-users" style="margin:0 0 12px;font-size:18px;color:#6b21a8">Recent Users</h2>

      <?php if (empty($users)): ?>
        <p style="color:#7e22ce">No users found.</p>
      <?php else: ?>
        <table aria-describedby="recent-users">
          <thead>
            <tr>
              <th style="width:64px">ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Verified</th>
              <th>Active</th>
              <th>Joined</th>
              <th class="col-action">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($users as $u): ?>
              <tr>
                <td><?php echo (int)$u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['name']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><span class="role-badge"><?php echo htmlspecialchars($u['role'] ?: 'user'); ?></span></td>
                <td><?php echo $u['verified'] ? '<span class="badge-yes">Verified</span>' : '<span class="badge-no">No</span>'; ?></td>
                <td><?php echo $u['active'] ? '<span class="badge-yes">Active</span>' : '<span class="badge-no">Inactive</span>'; ?></td>
                <td><?php echo htmlspecialchars($u['created_at']); ?></td>

                <td>
                  <div class="actions" role="group" aria-label="Actions for user <?php echo (int)$u['id']; ?>">
                    <div>
                      <a class="btn btn-view" href="view_user.php?id=<?php echo (int)$u['id']; ?>" title="View user">View user</a>
                    </div>

                   
                    <div>
                      <form method="POST" action="user_actions.php" class="action-form">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                        <?php if ($u['verified']): ?>
                          <button class="btn btn-unverify" name="action" value="toggle_verify" onclick="return confirm('Remove verification for this user?')">Unverify</button>
                        <?php else: ?>
                          <button class="btn btn-verify" name="action" value="toggle_verify" onclick="return confirm('Mark this user as verified?')">Verify</button>
                        <?php endif; ?>
                      </form>
                    </div>
                    <div>
                      <form method="POST" action="user_actions.php" class="action-form">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                        <?php if ($u['active']): ?>
                          <button class="btn btn-inactive" name="action" value="toggle_active" onclick="return confirm('Deactivate this account?')">Deactivate</button>
                        <?php else: ?>
                          <button class="btn btn-active" name="action" value="toggle_active" onclick="return confirm('Activate this account?')">Activate</button>
                        <?php endif; ?>
                      </form>
                    </div>

                  </div>
                </td>

              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <footer>
      &copy; <?php echo date('Y'); ?> HerSafar Admin Panel — All Rights Reserved
    </footer>
  </main>
</div>
</body>
</html>
