<?php
require_once 'dbcon.php';
require_once 'functions.php';
require_login();

if (session_status() === PHP_SESSION_NONE) session_start();

$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

$name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');

// Data for KPIs
$rides = (int)($mysqli->query("SELECT COUNT(*) AS c FROM rides WHERE driver_id=$uid")->fetch_assoc()['c'] ?? 0);
$bookings = (int)($mysqli->query("SELECT COUNT(*) AS c FROM bookings WHERE user_id=$uid")->fetch_assoc()['c'] ?? 0);
$groups = (int)($mysqli->query("SELECT COUNT(*) AS c FROM group_members WHERE user_id=$uid")->fetch_assoc()['c'] ?? 0);

// Upcoming rides (driver)
$upcoming = [];
if ($_SESSION['user']['role'] === 'driver' || $_SESSION['user']['role'] === 'admin') {
    $stmt = $mysqli->prepare("SELECT id, from_location, to_location, ride_date, ride_time, seats, available_seats, price FROM rides WHERE driver_id = ? AND ride_date >= CURDATE() ORDER BY ride_date, ride_time LIMIT 6");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $upcoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Recent bookings (either bookings made by user or bookings for driver's rides)
$recent = [];
if ($_SESSION['user']['role'] === 'driver' || $_SESSION['user']['role'] === 'admin') {
    // recent bookings for driver's rides
    $stmt = $mysqli->prepare("
      SELECT b.id AS booking_id, b.ride_id, b.seats, b.status, b.created_at,
             r.from_location, r.to_location, r.ride_date, u.name AS passenger_name
      FROM bookings b
      JOIN rides r ON b.ride_id = r.id
      JOIN users u ON b.user_id = u.id
      WHERE r.driver_id = ?
      ORDER BY b.created_at DESC
      LIMIT 6
    ");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // recent bookings made by this user
    $stmt = $mysqli->prepare("
      SELECT b.id AS booking_id, b.ride_id, b.seats, b.status, b.created_at,
             r.from_location, r.to_location, r.ride_date, u.name AS driver_name
      FROM bookings b
      JOIN rides r ON b.ride_id = r.id
      JOIN users u ON r.driver_id = u.id
      WHERE b.user_id = ?
      ORDER BY b.created_at DESC
      LIMIT 6
    ");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// simple earnings estimate for driver: sum of confirmed seats * price for upcoming rides (not persisted)
$estimated_earnings = 0.0;
if ($_SESSION['user']['role'] === 'driver' || $_SESSION['user']['role'] === 'admin') {
    $stmt = $mysqli->prepare("
      SELECT SUM(b.seats * r.price) AS total
      FROM bookings b
      JOIN rides r ON b.ride_id = r.id
      WHERE r.driver_id = ? AND b.status = 'confirmed' AND r.ride_date >= CURDATE()
    ");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $estimated_earnings = (float)($row['total'] ?? 0.0);
    $stmt->close();
}

// small helper for status label
function status_label($s) {
    if ($s === 'confirmed') return '<span class="s-confirm">Confirmed</span>';
    if ($s === 'pending') return '<span class="s-pending">Pending</span>';
    return '<span class="s-cancel">Cancelled</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dashboard — HersSafar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{
  --primary-1:#5b21b6;
  --primary-2:#8b5cf6;
  --muted:#6b4a86;
  --bg-a:#fbf9ff;
  --bg-b:#f7f3ff;
  --card:#ffffff;
  --glass:rgba(255,255,255,0.6);
  --radius:14px;
  --shadow:0 12px 30px rgba(91,33,182,0.06);
  font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
  color:#241235;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  min-height:100vh;
  background:
    radial-gradient(800px 400px at 10% 10%, rgba(139,92,246,0.05), transparent 10%),
    radial-gradient(600px 300px at 90% 90%, rgba(192,132,252,0.04), transparent 10%),
    linear-gradient(180deg,var(--bg-a),var(--bg-b));
  padding:22px;
  display:flex;
  gap:20px;
  -webkit-font-smoothing:antialiased;
}

/* SIDEBAR (same as dashboard) */
.sidebar{
  width:260px;
  background: linear-gradient(90deg, rgba(75,23,146,0.78), rgba(109,64,186,0.65), rgba(145,90,220,0.55));
  color:#fff;
  display:flex;
  flex-direction:column;
  padding:22px;
  border-radius:14px;
  box-shadow: 3px 8px 30px rgba(168,85,247,0.08);
  flex-shrink:0;
}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.logo-circle{width:46px;height:46px;border-radius:12px;background:white;color:#9333ea;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px}
.brand-text{font-weight:700;font-size:16px}
.brand-sub{font-size:12px;opacity:0.95}

.nav{display:flex;flex-direction:column;gap:8px;margin-top:8px}
.nav a{color:#fff;text-decoration:none;padding:10px 12px;border-radius:10px;font-weight:600;border:1px solid rgba(255,255,255,0.12);transition:all .18s var(--ease);font-size:13px;}
.nav a:hover{transform:translateY(-2px);background:rgba(255,255,255,0.08)}
.nav a.active{background:rgba(255,255,255,0.12);box-shadow:0 6px 18px rgba(0,0,0,0.05)}

.spacer{flex:1}
.bottom-links{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.bottom-links a{display:block;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.95);color:#a855f7;text-decoration:none;font-weight:700;text-align:center;border:1px solid rgba(255,255,255,0.3)}
.bottom-links a:hover{background:#f3e8ff;color:#6b21b6}

.sidebar .logged-user{margin-top:16px;font-size:13px;color:#fff;opacity:0.95;text-align:center}
/* Main area */
.main{flex:1;display:flex;flex-direction:column;gap:18px;min-width:0}

/* Hero compact */
.hero{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:18px;
  padding:18px;
  border-radius:14px;
  background:linear-gradient(180deg, rgba(255,255,255,0.9), rgba(255,255,255,0.75));
  border:1px solid rgba(99,102,241,0.06);
  box-shadow:var(--shadow);
}
.hero-left{min-width:0}
.greeting{font-size:18px;color:var(--primary-1);font-weight:600;margin-bottom:6px}
.help-text{font-size:13px;color:var(--muted);line-height:1.4}

/* KPI row */
.kpis{display:flex;gap:12px;margin-top:12px}
.kpi{
  background:var(--card);padding:12px;border-radius:10px;min-width:120px;text-align:center;border:1px solid rgba(15,23,42,0.03);
  box-shadow:0 6px 18px rgba(13,16,40,0.03);
}
.kpi .num{font-weight:700;color:var(--primary-1);font-size:20px}
.kpi .lbl{font-size:12px;color:var(--muted);margin-top:6px}

/* Three-column layout below hero */
.grid{
  display:grid;
  grid-template-columns: 1.2fr 1fr;
  gap:14px;
  align-items:start;
}

/* Upcoming & Recent panels */
.panel{
  background:var(--card);
  border-radius:12px;
  padding:14px;
  border:1px solid rgba(15,23,42,0.03);
  box-shadow:0 8px 30px rgba(13,16,40,0.03);
}
.panel h4{margin:0 0 8px 0;font-size:14px;color:var(--primary-1);font-weight:600}
.small{font-size:13px;color:var(--muted)}

/* upcoming list compact */
.upcoming-list{display:flex;flex-direction:column;gap:10px}
.up-item{display:flex;justify-content:space-between;align-items:center;padding:10px;border-radius:8px;background:linear-gradient(180deg,#fff,#faf8ff);border:1px solid rgba(15,23,42,0.03)}
.up-left{min-width:0}
.route{font-weight:700;font-size:13px;color:#111827}
.meta{font-size:12px;color:var(--muted);margin-top:4px}
.right-meta{text-align:right;font-size:13px;color:#111827}

/* recent activity */
.activity-list{display:flex;flex-direction:column;gap:8px}
.act-row{display:flex;justify-content:space-between;align-items:center;padding:8px;border-radius:8px;background:#fff;border:1px solid rgba(15,23,42,0.03)}
.act-left{min-width:0}
.act-title{font-size:13px;font-weight:600}
.act-sub{font-size:12px;color:var(--muted);margin-top:4px}
.s-confirm{display:inline-block;padding:6px 8px;border-radius:999px;background:#ecfdf5;color:#15803d;font-weight:700;font-size:12px}
.s-pending{display:inline-block;padding:6px 8px;border-radius:999px;background:#fff7ed;color:#b45309;font-weight:700;font-size:12px}
.s-cancel{display:inline-block;padding:6px 8px;border-radius:999px;background:#f3f4f6;color:#374151;font-weight:700;font-size:12px}

/* Earnings small */
.earn{display:flex;flex-direction:column;gap:6px}
.earn .val{font-weight:700;color:var(--primary-1);font-size:18px}
.earn .lbl{font-size:12px;color:var(--muted)}

/* footer note */
.note{font-size:12px;color:var(--muted);margin-top:6px}
.footer{position:fixed;right:16px;bottom:10px;font-size:12px;color:var(--muted);background:rgba(255,255,255,0.6);padding:6px 10px;border-radius:8px;border:1px solid rgba(99,102,241,0.06);backdrop-filter:blur(6px)}
/* responsive */
@media (max-width:980px){
  .grid{grid-template-columns:1fr}
  .kpis{flex-wrap:wrap}
  .hero{flex-direction:column;align-items:flex-start}
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

    <nav class="nav">
      <a href="dashboard.php" class="active">Dashboard</a>
      <a href="index.php">Home</a>
      <a href="profile.php">Profile</a>
      <a href="groups.php">Groups</a>
      <a href="manage_rides.php">Manage Bookings</a>
      <a href="join_ride.php">Join A Ride</a>
      <a href="apply_driver.php">Driver Applications</a>
    </nav>

    <div class="spacer"></div>
    <div class="logged-user small">Signed in as<br><strong><?= $name ?></strong></div>

    <div class="bottom-links">
      <a href="change_password.php">Change Password</a>
       <a href="/hersafar/login.php">Logout</a>
    </div>
  </aside>

  <main class="main">
    <section class="hero">
      <div class="hero-left">
        <div class="greeting">Welcome back, <?= $name ?>.</div>
        <div class="help-text">A concise snapshot of your rides, bookings, and activity. Everything you need at a glance — minimal, clear and professional.</div>

        <div class="kpis" role="list" aria-label="Key summary">
          <div class="kpi" role="listitem">
            <div class="num"><?= $rides ?></div>
            <div class="lbl">Upcoming rides</div>
          </div>
          <div class="kpi" role="listitem">
            <div class="num"><?= $bookings ?></div>
            <div class="lbl">My bookings</div>
          </div>
          <div class="kpi" role="listitem">
            <div class="num"><?= $groups ?></div>
            <div class="lbl">Groups joined</div>
          </div>
        </div>
      </div>

      <div class="earn">
        <div class="lbl small">Estimated upcoming earnings</div>
        <div class="val">₹<?= number_format($estimated_earnings,2) ?></div>
        <div class="note small">Earnings reflect confirmed seats on upcoming rides.</div>
      </div>
    </section>

    <div class="grid">
      <div class="panel">
        <h4>Upcoming rides</h4>
        <div class="small">Next scheduled trips you’re hosting (if any).</div>

        <?php if (empty($upcoming)): ?>
          <div style="margin-top:10px" class="small">No upcoming rides available. Post a ride to start sharing your trips.</div>
        <?php else: ?>
          <div class="upcoming-list" style="margin-top:12px">
            <?php foreach($upcoming as $r): ?>
              <div class="up-item" aria-label="<?= htmlspecialchars($r['from_location'].' to '.$r['to_location']) ?>">
                <div class="up-left">
                  <div class="route"><?= htmlspecialchars($r['from_location']) ?> → <?= htmlspecialchars($r['to_location']) ?></div>
                  <div class="meta"><?= htmlspecialchars($r['ride_date']) ?> • <?= htmlspecialchars($r['ride_time']) ?></div>
                </div>
                <div class="right-meta">
                  <div class="small">Seats: <?= (int)$r['available_seats'] ?>/<?= (int)$r['seats'] ?></div>
                  <div style="margin-top:6px;font-weight:700;color:#111">₹<?= htmlspecialchars($r['price']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="note small" style="margin-top:12px">Only upcoming rides within the next dates are shown (max 6).</div>
      </div>

      <div class="panel">
        <h4>Recent activity</h4>
        <div class="small">Latest bookings and updates — for drivers this shows recent bookings on their rides; for passengers it shows their bookings.</div>

        <?php if (empty($recent)): ?>
          <div style="margin-top:12px" class="small">No recent activity.</div>
        <?php else: ?>
          <div class="activity-list" style="margin-top:12px">
            <?php foreach($recent as $a): ?>
              <div class="act-row" aria-label="Activity <?= (int)$a['booking_id'] ?>">
                <div class="act-left">
                  <div class="act-title">
                    <?php
                      // show passenger or driver depending on role
                      if ($_SESSION['user']['role'] === 'driver' || $_SESSION['user']['role'] === 'admin') {
                        echo htmlspecialchars($a['passenger_name'] ?? 'Passenger');
                      } else {
                        echo htmlspecialchars($a['driver_name'] ?? ($a['passenger_name'] ?? 'Driver'));
                      }
                    ?>
                    <span style="font-weight:400;color:var(--muted);"> — <?= htmlspecialchars($a['from_location'].' → '.$a['to_location']) ?></span>
                  </div>
                  <div class="act-sub"><?= htmlspecialchars($a['ride_date']) ?> • seats: <?= (int)($a['seats'] ?? 0) ?></div>
                </div>

                <div style="text-align:right">
                  <?= status_label($a['status'] ?? ($a['booking_status'] ?? '')) ?>
                  <div class="small" style="margin-top:6px"><?= htmlspecialchars($a['created_at'] ?? '') ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="note small" style="margin-top:12px">Statuses: <span class="s-confirm">Confirmed</span> • <span class="s-pending">Pending</span> • <span class="s-cancel">Cancelled</span></div>
      </div>
    </div>

    <div class="panel">
      <h4>About this view</h4>
      <div class="small">This dashboard keeps your information compact and focused: KPIs on the left, quick upcoming rides in the center, and recent activity on the right. It's designed to be clean, low-noise, and professional. No action buttons are included here — use the dedicated pages for managing rides and bookings.</div>
    </div>
  </main>
</body>
<div class="footer">&copy; <?= date('Y') ?> HerSafar — Safe, Smart, and Connected Travel</div>
</html>
