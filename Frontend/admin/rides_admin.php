<?php
// admin/rides_admin.php — Admin rides manager with HerSafar admin theme (enhanced filters)
require_once __DIR__ . '/../dbcon.php';
require_once __DIR__ . '/../functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: /login.php'); exit;
}

// flash message
$flash_html = '';
if (! empty($_SESSION['admin_msg'])) {
    $m = $_SESSION['admin_msg'];
    $style = $m['type'] === 'success' ? 'color:#065f46' : ($m['type'] === 'error' ? 'color:#b91c1c' : 'color:#7e22ce');
    $flash_html = "<div style=\"$style;margin-bottom:12px;padding:10px 12px;border-radius:8px;background:#fff\">".htmlspecialchars($m['text'])."</div>";
    unset($_SESSION['admin_msg']);
}

// optional filters
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$date = trim($_GET['date'] ?? '');

// build query
$sql = "SELECT r.id, r.from_location, r.to_location, r.ride_date, r.ride_time, r.seats, r.available_seats, r.price, r.created_at, 
               u.id AS driver_id, u.name AS driver_name, u.email AS driver_email
        FROM rides r
        JOIN users u ON r.driver_id = u.id
        WHERE 1=1";
$params = [];
$types = '';

if ($from !== '') { $sql .= " AND r.from_location LIKE ?"; $params[] = "%$from%"; $types .= 's'; }
if ($to !== '')   { $sql .= " AND r.to_location LIKE ?";   $params[] = "%$to%";   $types .= 's'; }
if ($date !== '') { $sql .= " AND r.ride_date = ?";         $params[] = $date;    $types .= 's'; }

$sql .= " ORDER BY r.ride_date DESC, r.ride_time DESC LIMIT 200";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) die("DB prepare error: " . $mysqli->error);

if (!empty($params)) {
    $bind_names = [];
    $bind_names[] = $types;
    for ($i=0; $i<count($params); $i++) $bind_names[] = &$params[$i];
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}
$stmt->execute();
$res = $stmt->get_result();
$rides = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Rides — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  a{text-decoration:none;color:inherit}
  body{font-family:'Poppins',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#fdfcff;color:#2d1b4e;}
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
  /* Main area */
  .main{flex:1;padding:30px;background:linear-gradient(180deg,#fff,#faf5ff);position:relative}
  header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
  header h1{font-size:24px;font-weight:700;color:#5b21b6}
  header p{color:#7e22ce;font-size:14px}
  .chip{padding:8px 12px;border-radius:10px;border:1px solid #f3e8ff;background:#fff;font-weight:600;color:#5b21b6;}
  .panel{background:white;border-radius:16px;padding:20px;border:1px solid #f3e8ff;box-shadow:0 8px 24px rgba(150,90,255,0.06)}

  /* Filter form */
  .filter-form{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px;}
  .filter-form input[type="text"], .filter-form input[type="date"]{
    padding:10px 14px;border-radius:8px;border:1px solid #e9d5ff;background:#fff;min-width:160px;
  }
  .filter-form button{
    padding:10px 16px;border-radius:8px;border:0;cursor:pointer;font-weight:600;
    background:linear-gradient(90deg,#e879f9,#c084fc);color:#fff;
  }
  .filter-form a.reset{
    background:#faf5ff;color:#5b21b6;border:1px solid #f3e8ff;padding:10px 14px;border-radius:8px;font-weight:600;
  }
  .filter-form a.reset:hover{background:#f3eefc}

  /* Table */
  table{width:100%;border-collapse:collapse;font-size:14px}
  thead th{background:#faf5ff;color:#5b21b6;padding:12px;text-align:left;font-weight:700}
  tbody td{padding:12px;border-bottom:1px solid #f5f3ff;vertical-align:middle}
  tbody tr:hover td{background:#fff7ff}

  /* Actions */
  .action-row{display:flex;gap:8px;flex-wrap:wrap}
  .btn{padding:8px 10px;border-radius:8px;border:0;cursor:pointer;font-weight:600;font-size:13px;display:inline-flex;align-items:center;justify-content:center;min-height:40px;}
  .btn-view{background:#fbf7ff;color:#5b21b6;border:1px solid #f3e8ff}
  .btn-view:hover{background:#f3eefc}
  .btn-del{background:#fff1f2;color:#9b1c1c;border:1px solid rgba(155,28,28,0.06)}
  .btn-del:hover{background:#ffe5e7}

  footer{position:absolute;right:20px;bottom:10px;font-size:13px;color:#7e22ce;text-align:right}
  @media(max-width:900px){
    .sidebar{display:none}
    header{flex-direction:column;align-items:flex-start;gap:8px}
    footer{position:static;margin-top:22px;text-align:center}
    .filter-form{flex-direction:column;align-items:stretch}
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
      <a href="rides_admin.php" style="background:rgba(255,255,255,0.08)">Manage Rides</a>
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
        <h1>Manage Rides</h1>
        <p>Filter, view, and manage rides posted by verified drivers</p>
      </div>
    </header>

    <?php echo $flash_html; ?>

    <div class="panel">
      <form method="GET" class="filter-form">
        <input type="text" name="from" placeholder="From location" value="<?php echo htmlspecialchars($from); ?>">
        <input type="text" name="to" placeholder="To location" value="<?php echo htmlspecialchars($to); ?>">
        <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
        <button type="submit">Filter</button>
        <a href="rides_admin.php" class="reset">Reset</a>
      </form>

      <div style="overflow:auto;">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Route</th><th>Date</th><th>Time</th><th>Seats</th><th>Available</th><th>Price</th><th>Driver</th><th>Created</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rides)): ?>
              <tr><td colspan="10" style="text-align:center;color:#7e22ce;">No rides found.</td></tr>
            <?php else: foreach($rides as $r): ?>
              <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['from_location']).' → '.htmlspecialchars($r['to_location']); ?></td>
                <td><?php echo htmlspecialchars($r['ride_date']); ?></td>
                <td><?php echo htmlspecialchars($r['ride_time']); ?></td>
                <td><?php echo (int)$r['seats']; ?></td>
                <td><?php echo (int)$r['available_seats']; ?></td>
                <td>₹<?php echo htmlspecialchars($r['price']); ?></td>
                <td><?php echo htmlspecialchars($r['driver_name']).' ('.htmlspecialchars($r['driver_email']).')'; ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                <td>
                  <div class="action-row">
                    <a class="btn btn-view" href="view_ride.php?id=<?php echo $r['id']; ?>">View / Edit</a>
                    <form method="POST" action="ride_actions.php" onsubmit="return confirm('Delete this ride and all bookings?');">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="ride_id" value="<?php echo $r['id']; ?>">
                      <button class="btn btn-del" name="action" value="delete">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
