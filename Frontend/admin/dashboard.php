<?php
// admin/dashboard.php — Refined White, Pink & Purple Theme
require_once __DIR__ . '/../dbcon.php';
require_once __DIR__ . '/../functions.php';
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: hersafar/login.php');
    exit;
}

// Stats
$res = $mysqli->query("SELECT COUNT(*) AS c FROM users");
$total_users = $res->fetch_assoc()['c'] ?? 0;
$res = $mysqli->query("SELECT COUNT(*) AS c FROM rides");
$total_rides = $res->fetch_assoc()['c'] ?? 0;
$res = $mysqli->query("SELECT COUNT(*) AS c FROM bookings");
$total_bookings = $res->fetch_assoc()['c'] ?? 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Dashboard — HerSafar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{
    font-family:'Poppins',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
    background:#fdfcff;
    color:#2d1b4e;
  }
  .dashboard{
    display:flex;
    min-height:100vh;
  }

  .sidebar{
    width:260px;
   background: linear-gradient(90deg, rgba(75,23,146,0.78), rgba(109,64,186,0.65), rgba(145,90,220,0.55));
    color:#fff;
    display:flex;
    flex-direction:column;
    padding:28px 22px;
    box-shadow:3px 0 18px rgba(180,80,255,0.15);
  }
  .brand{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:40px;
  }
  .logo-circle{
    width:46px;
    height:46px;
    border-radius:12px;
    background:white;
    color:#9333ea;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
    font-size:18px;
    box-shadow:0 3px 10px rgba(255,255,255,0.3);
  }
  .brand-text{
    font-weight:700;
    font-size:18px;
  }
  .brand-sub{
    font-size:12px;
    opacity:0.9;
  }

  .nav{
    display:flex;
    flex-direction:column;
    gap:8px;
    flex-grow:1;
  }
  .nav a{
    color:#fff;
    text-decoration:none;
    padding:10px 12px;
    border-radius:8px;
    font-weight:500;
    border:1px solid rgba(255,255,255,0.4);
    transition:all 0.25s ease;
  }
  .nav a:hover{
    background:rgba(255,255,255,0.15);
    transform:translateY(-2px);
  }

  .logout{
    margin-top:auto;
    margin-bottom:10px;
    text-align:center;
  }
  .logout a{
    display:block;
    background:white;
    color:#a855f7;
    padding:10px 12px;
    border-radius:8px;
    font-weight:600;
    text-decoration:none;
    border:1px solid rgba(255,255,255,0.5);
    transition:all 0.25s;
  }
  .logout a:hover{
    background:#f3e8ff;
    color:#9333ea;
  }

  .logged-user{
    margin-top:20px;
    font-size:13px;
    text-align:center;
    color:#fff;
    opacity:0.9;
  }

  /* Main Content */
  .main{
    flex:1;
    padding:30px;
    background:linear-gradient(180deg,#fff,#faf5ff);
    position:relative;
  }
  header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:28px;
  }
  header h1{
    font-size:26px;
    font-weight:700;
    color:#5b21b6;
  }
  header p{
    color:#7e22ce;
    font-size:14px;
  }
  .btn{
    padding:10px 16px;
    border-radius:8px;
    border:none;
    cursor:pointer;
    font-weight:600;
    font-family:'Poppins';
  }
  .btn-primary{
    background:linear-gradient(90deg,#e879f9,#c084fc);
    color:#fff;
    box-shadow:0 8px 22px rgba(200,100,255,0.25);
  }

  /* Stats */
  .stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:20px;
    margin-bottom:30px;
  }
  .card{
    background:white;
    border-radius:18px;
    padding:24px;
    border:1px solid #f3e8ff;
    box-shadow:0 8px 24px rgba(150,90,255,0.08);
    transition:transform .2s;
  }
  .card:hover{transform:translateY(-4px);}
  .label{
    color:#7e22ce;
    font-size:14px;
    font-weight:600;
  }
  .value{
    font-size:40px;
    font-weight:800;
    background:linear-gradient(90deg,#e879f9,#a855f7,#d946ef);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    margin:10px 0;
  }
  .desc{
    font-size:13px;
    color:#9d78d0;
  }

  /* Panel */
  .panel{
    background:white;
    border-radius:16px;
    padding:22px;
    border:1px solid #f3e8ff;
    box-shadow:0 8px 24px rgba(150,90,255,0.08);
  }
  .panel h2{
    font-size:18px;
    margin-bottom:14px;
    font-weight:700;
    color:#6b21a8;
  }
  table{
    width:100%;
    border-collapse:collapse;
  }
  th,td{
    padding:12px 10px;
    border-bottom:1px solid #f5f3ff;
    text-align:left;
    font-size:14px;
  }
  th{
    background:#faf5ff;
    color:#5b21b6;
    font-weight:600;
  }
  tbody tr:hover td{
    background:#fdf4ff;
  }
  .role{
    background:#f5d0fe;
    color:#7e22ce;
    padding:4px 10px;
    border-radius:999px;
    font-weight:600;
    font-size:13px;
  }

  footer{
    position:absolute;
    right:20px;
    bottom:10px;
    font-size:13px;
    color:#7e22ce;
    text-align:right;
  }

  @media(max-width:900px){
    .sidebar{display:none;}
    header{flex-direction:column;align-items:flex-start;gap:10px;}
    footer{position:static;margin-top:20px;text-align:center;}
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
      <a href="users.php">Manage Users</a>
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
        <h1>Admin Dashboard</h1>
        <p>Overview of the HerSafar platform</p>
      </div>
    </header>

    <!-- Stats -->
    <section class="stats">
      <div class="card">
        <div class="label">Total Users</div>
        <div class="value"><?php echo number_format($total_users); ?></div>
        <div class="desc">Registered members on HerSafar</div>
      </div>
      <div class="card">
        <div class="label">Total Rides</div>
        <div class="value"><?php echo number_format($total_rides); ?></div>
        <div class="desc">Created rides by drivers</div>
      </div>
      <div class="card">
        <div class="label">Total Bookings</div>
        <div class="value"><?php echo number_format($total_bookings); ?></div>
        <div class="desc">Completed & active bookings</div>
      </div>
    </section>

    <!-- Table -->
    <section class="panel">
      <h2>Recent Users</h2>
      <?php
      $list = $mysqli->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 8");
      if ($list && $list->num_rows): ?>
        <table>
          <thead>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr>
          </thead>
          <tbody>
            <?php while($u = $list->fetch_assoc()): ?>
              <tr>
                <td><?php echo (int)$u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['name']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><span class="role"><?php echo htmlspecialchars($u['role'] ?: 'user'); ?></span></td>
                <td><?php echo htmlspecialchars($u['created_at']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:#7e22ce;">No users found.</p>
      <?php endif; ?>
    </section>

    <footer>
      &copy; <?php echo date('Y'); ?> HerSafar Admin Panel — All Rights Reserved
    </footer>
  </main>
</div>
</body>
</html>
