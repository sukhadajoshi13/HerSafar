<?php
require_once __DIR__ . '/../dbcon.php';
require_once __DIR__ . '/../functions.php';
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit;
}

$ride_id = (int)($_GET['id'] ?? 0);
if ($ride_id <= 0) {
    header('Location: rides_admin.php');
    exit;
}

/* --- Load ride + driver info --- */
$stmt = $mysqli->prepare("
  SELECT r.*, u.name AS driver_name, u.email AS driver_email, u.phone AS driver_phone
  FROM rides r
  JOIN users u ON r.driver_id = u.id
  WHERE r.id = ?
  LIMIT 1
");
if (!$stmt) {
    die("DB error: " . $mysqli->error);
}
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    header('Location: rides_admin.php');
    exit;
}

/* --- Load bookings for the ride --- */
$bookings = [];
$stmt = $mysqli->prepare("
  SELECT b.id, b.user_id, b.seats, b.status, b.created_at, u.name AS passenger_name, u.email AS passenger_email, u.phone AS passenger_phone
  FROM bookings b
  JOIN users u ON b.user_id = u.id
  WHERE b.ride_id = ?
  ORDER BY FIELD(b.status,'pending','confirmed','cancelled'), b.created_at ASC
");
if ($stmt) {
    $stmt->bind_param('i', $ride_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $bookings[] = $row;
    $stmt->close();
}

/* --- Flash message (from ride_actions.php) --- */
$flash_html = '';
if (! empty($_SESSION['admin_msg'])) {
    $m = $_SESSION['admin_msg'];
    $color = $m['type'] === 'success' ? '#065f46' : ($m['type'] === 'error' ? '#b91c1c' : '#7e22ce');
    $flash_html = "<div style='background:#fff;padding:10px;border-radius:8px;border:1px solid #f3e8ff;color:$color;margin-bottom:12px'>"
                . htmlspecialchars($m['text']) . "</div>";
    unset($_SESSION['admin_msg']);
}

$csrf = csrf_token();
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>View Ride </title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:'Poppins',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
  background:#fdfcff;
  color:#2d1b4e;
}
.dashboard{display:flex;min-height:100vh}

/* Sidebar (copied from your reference) */
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

/* Main */
.main{
  flex:1;
  padding:30px 40px;
  background:linear-gradient(180deg,#fff,#faf5ff);
}
header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:20px;
}
h1{
  font-size:22px;
  font-weight:700;
  color:#6b21a8;
}
.back-btn{
  text-decoration:none;
  background:#fff;
  border:1px solid #f3e8ff;
  border-radius:8px;
  padding:8px 14px;
  color:#6b21a8;
  font-weight:600;
  font-size:13px;
}
.back-btn:hover{background:#faf5ff;transform:translateY(-1px)}

.panel{
  background:white;
  border-radius:16px;
  padding:20px;
  border:1px solid #f3e8ff;
  box-shadow:0 8px 24px rgba(150,90,255,0.08);
  margin-bottom:20px;
}
.section-title{
  font-size:15px;
  font-weight:700;
  color:#6b21a8;
  margin-bottom:8px;
}
.row{
  display:flex;
  justify-content:space-between;
  padding:8px 0;
  border-bottom:1px dashed #f3e8ff;
}
.k{color:#7e22ce;font-weight:600;font-size:13px}
.v{color:#3b0764;font-weight:700;font-size:14px}
table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
th,td{padding:8px;border-bottom:1px solid #f5f3ff;text-align:left}
th{background:#faf5ff;color:#6b21a8}
tbody tr:hover td{background:#fff7ff}

/* Buttons */
.actions{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
  margin-top:10px;
}
.btn{
  padding:8px 10px;
  border-radius:8px;
  border:1px solid #f3e8ff;
  background:#fff;
  color:#6b21a8;
  text-align:center;
  font-weight:600;
  font-size:13px;
  text-decoration:none;
  cursor:pointer;
}
.btn:hover{transform:translateY(-1px)}
.btn-primary{background:#ede9fe;color:#5b21b6;border:0}
.btn-confirm{background:#d1fae5;color:#065f46;border:0}
.btn-danger{background:#fee2e2;color:#991b1b;border:0}
.btn-ghost{background:transparent;border:1px solid #f3e8ff;color:#6b21a8}

/* Compact admin ride layout */
.layout{display:grid;grid-template-columns:1fr 340px;gap:16px}
@media(max-width:980px){ .layout{grid-template-columns:1fr} }

.card{
  background:white;
  border-radius:12px;
  padding:14px;
  border:1px solid #f3e8ff;
  box-shadow:0 8px 24px rgba(150,90,255,0.04);
}
.label{display:block;margin-bottom:6px;color:#6b21a8;font-weight:600;font-size:13px}
.input{width:100%;padding:8px;border-radius:8px;border:1px solid #f3e8ff;font-size:13px}

.status-pill{display:inline-block;padding:6px 8px;border-radius:999px;font-weight:700;font-size:12px}
.status-confirm{background:#ecfdf5;color:#065f46}
.status-pending{background:#fff7ed;color:#92400e}
.status-cancel{color:#9ca3af}

.small{font-size:13px;color:#6b4a86}
.footer-note{position:fixed;right:16px;bottom:10px;font-size:12px;color:#7e22ce}
@media(max-width:900px){
  .sidebar{display:none}
  header{flex-direction:column;align-items:flex-start;gap:8px}
  .footer-note{position:static;margin-top:18px;text-align:center}
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

    <div class="logged-user">Logged in as: <strong><?php echo h($_SESSION['user']['name']); ?></strong></div>
  </aside>

  <!-- Main -->
  <main class="main">
    <header>
      <div>
        <h1>Ride No - <?php echo (int)$ride['id']; ?></h1>
        <div class="small">Route: <?php echo h($ride['from_location']); ?> → <?php echo h($ride['to_location']); ?> </div>
      </div>
      <div>
        <a class="back-btn" href="rides_admin.php">← Back to rides</a>
      </div>
    </header>

    <?php echo $flash_html; ?>

    <div class="layout">
      <!-- Left: edit and bookings -->
      <div>
        <div class="card">
          <div class="section-title">Edit ride</div>
          <form method="POST" action="ride_actions.php">
            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
            <input type="hidden" name="action" value="update">

            <div style="display:flex;gap:10px;margin-bottom:8px">
              <div style="flex:1">
                <label class="label">From</label>
                <input class="input" name="from_location" type="text" value="<?php echo h($ride['from_location']); ?>" required>
              </div>
              <div style="flex:1">
                <label class="label">To</label>
                <input class="input" name="to_location" type="text" value="<?php echo h($ride['to_location']); ?>" required>
              </div>
            </div>

            <div style="display:flex;gap:10px;margin-bottom:8px;align-items:center">
              <div style="flex:0 0 150px">
                <label class="label">Date</label>
                <input class="input" name="ride_date" type="date" value="<?php echo h($ride['ride_date']); ?>" required>
              </div>
              <div style="flex:0 0 120px">
                <label class="label">Time</label>
                <input class="input" name="ride_time" type="time" value="<?php echo h($ride['ride_time']); ?>">
              </div>
              <div style="flex:0 0 110px">
                <label class="label">Price</label>
                <input class="input" name="price" type="number" step="0.50" value="<?php echo h($ride['price']); ?>">
              </div>
            </div>

            <div style="display:flex;gap:10px;margin-bottom:8px;align-items:center">
              <div style="flex:0 0 140px">
                <label class="label">Seats (total)</label>
                <input class="input" name="seats" type="number" min="1" value="<?php echo (int)$ride['seats']; ?>" required>
              </div>
              <div style="flex:0 0 160px">
                <label class="label">Available</label>
                <input class="input" name="available_seats" type="number" min="0" value="<?php echo (int)$ride['available_seats']; ?>" required>
              </div>
            </div>

            <div style="margin-bottom:8px">
              <label class="label">Notes</label>
              <textarea class="input" name="notes"><?php echo h($ride['notes']); ?></textarea>
            </div>

            <div class="actions">
              <button class="btn btn-primary" type="submit">Save changes</button>
              <form method="POST" action="ride_actions.php" onsubmit="return confirm('Delete this ride and all bookings?');" style="margin:0">
                <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
                <button class="btn btn-danger" name="action" value="delete" type="submit">Delete ride</button>
              </form>
            </div>
          </form>
        </div>

        <div class="card" style="margin-top:12px">
          <div class="section-title">Bookings <span class="small">(<?php echo count($bookings); ?>)</span></div>

          <?php if (empty($bookings)): ?>
            <div class="small">No bookings yet.</div>
          <?php else: ?>
            <table>
              <thead>
                <tr>
                  <th style="width:48px">ID</th>
                  <th>Passenger</th>
                  <th style="width:60px">Seats</th>
                  <th style="width:110px">Status</th>
                  <th style="width:135px">Requested</th>
                  <th style="width:190px">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($bookings as $b): ?>
                  <tr>
                    <td><?php echo (int)$b['id']; ?></td>
                    <td>
                      <div style="font-weight:700"><?php echo h($b['passenger_name']); ?></div>
                      <div class="small"><?php echo h($b['passenger_email']); ?><?php if(!empty($b['passenger_phone'])) echo ' · '.h($b['passenger_phone']); ?></div>
                    </td>
                    <td><?php echo (int)$b['seats']; ?></td>
                    <td>
                      <?php if ($b['status'] === 'confirmed'): ?>
                        <span class="status-pill status-confirm">Confirmed</span>
                      <?php elseif ($b['status'] === 'pending'): ?>
                        <span class="status-pill status-pending">Pending</span>
                      <?php else: ?>
                        <span class="status-pill status-cancel">Cancelled</span>
                      <?php endif; ?>
                    </td>
                    <td class="small"><?php echo h($b['created_at']); ?></td>
                    <td>
                      <div style="display:flex;gap:8px;align-items:center">
                        <?php if ($b['status'] === 'pending'): ?>
                          <form method="POST" action="ride_actions.php" style="margin:0">
                            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                            <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">
                            <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
                            <button class="btn btn-confirm" name="action" value="confirm_booking" type="submit">Confirm</button>
                          </form>
                        <?php endif; ?>

                        <?php if ($b['status'] !== 'cancelled'): ?>
                          <form method="POST" action="ride_actions.php" style="margin:0" onsubmit="return confirm('Cancel this booking?');">
                            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                            <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">
                            <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
                            <button class="btn" name="action" value="cancel_booking" type="submit">Cancel</button>
                          </form>
                        <?php endif; ?>

                        <a class="btn btn-ghost" href="users.php?id=<?php echo (int)$b['user_id']; ?>">Profile</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: quick info -->
      <aside>
        <div class="card">
          <div class="section-title">Quick info</div>
          <div class="small" style="margin-bottom:8px"><strong>Date & time:</strong><br><?php echo h($ride['ride_date']); ?> <?php echo h($ride['ride_time']); ?></div>
          <div class="small" style="margin-bottom:8px"><strong>Seats:</strong><br><?php echo (int)$ride['seats']; ?> total · <?php echo (int)$ride['available_seats']; ?> available</div>
          <div class="small" style="margin-bottom:8px"><strong>Price:</strong><br>₹<?php echo h($ride['price']); ?></div>
          <div class="small" style="margin-bottom:8px"><strong>Driver:</strong><br><?php echo h($ride['driver_name']); ?></div>
          <div class="small" style="margin-bottom:8px"><strong>Share token:</strong><br><?php echo h($ride['share_token'] ?? '—'); ?></div>

          <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">

          </div>

          <?php if (!empty($ride['notes'])): ?>
            <div style="margin-top:12px">
              <div class="section-title" style="font-size:13px;margin-bottom:6px">Notes</div>
              <div class="small"><?php echo nl2br(h($ride['notes'])); ?></div>
            </div>
          <?php endif; ?>
        </div>

        <div class="card" style="margin-top:12px">
          <div class="section-title">Activity</div>
          <div class="small">Created: <?php echo h($ride['created_at'] ?? '—'); ?></div>
          <div class="small" style="margin-top:6px">Updated: <?php echo h($ride['updated_at'] ?? '—'); ?></div>
        </div>
      </aside>
    </div>

    <div class="footer-note">&copy; <?php echo date('Y'); ?> HerSafar Admin</div>
  </main>
</div>
</body>
</html>
