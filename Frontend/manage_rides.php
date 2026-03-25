<?php
require_once 'dbcon.php';
require_once 'functions.php';
require_login();

if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking_id'], $_POST['csrf_token'])) {
    // basic CSRF check (uses your csrf_token() helper)
    if (!hash_equals($csrf, $_POST['csrf_token'])) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid CSRF token.'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $cancel_bid = (int)$_POST['cancel_booking_id'];

    $mysqli->begin_transaction();

    try {
        // lock the booking row for update and get relevant fields
        $q = $mysqli->prepare("SELECT id, user_id, ride_id, seats, status FROM bookings WHERE id = ? FOR UPDATE");
        if (!$q) throw new Exception('Prepare failed: ' . $mysqli->error);
        $q->bind_param('i', $cancel_bid);
        $q->execute();
        $booking = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$booking) {
            throw new Exception('Booking not found.');
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);

        // ensure current user owns this booking
        if ((int)$booking['user_id'] !== $uid) {
            throw new Exception('You are not authorized to cancel this booking.');
        }

        // if already cancelled, nothing to do
        if ($booking['status'] === 'cancelled') {
            throw new Exception('Booking is already cancelled.');
        }

        // determine whether bookings table has 'updated_at' column
        $has_updated_at = false;
        $col_check = $mysqli->query("SHOW COLUMNS FROM bookings LIKE 'updated_at'");
        if ($col_check && $col_check->num_rows > 0) {
            $has_updated_at = true;
        }

        // prepare update depending on column presence
        if ($has_updated_at) {
            $u = $mysqli->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        } else {
            $u = $mysqli->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        }
        if (!$u) throw new Exception('Prepare failed: ' . $mysqli->error);
        $u->bind_param('i', $cancel_bid);
        $u->execute();
        $u->close();

        // if booking was confirmed, return seats to ride.available_seats
        if ($booking['status'] === 'confirmed') {
            $add = (int)$booking['seats'];
            $v = $mysqli->prepare("UPDATE rides SET available_seats = available_seats + ? WHERE id = ?");
            if (!$v) throw new Exception('Prepare failed: ' . $mysqli->error);
            $v->bind_param('ii', $add, $booking['ride_id']);
            $v->execute();
            $v->close();
        }

        $mysqli->commit();
        $_SESSION['msg'] = ['type' => 'success', 'text' => 'Booking cancelled successfully.'];
    } catch (Exception $ex) {
        $mysqli->rollback();
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Could not cancel booking: ' . $ex->getMessage()];
    }

    // redirect to avoid POST resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
// --- end cancellation handler ---

$uid = (int)($_SESSION['user']['id'] ?? 0);
$role = $_SESSION['user']['role'] ?? 'user';
$name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');

// Flash message
$flash_html = '';
if (!empty($_SESSION['msg'])) {
    $m = $_SESSION['msg'];
    $bg = $m['type'] === 'success' ? '#ecfdf5' : ($m['type'] === 'error' ? '#fff1f2' : '#fefce8');
    $color = $m['type'] === 'success' ? '#065f46' : ($m['type'] === 'error' ? '#9b1c1c' : '#78350f');
    $flash_html = "<div style='background:$bg;padding:12px 14px;border-radius:10px;color:$color;margin-bottom:14px;font-weight:500;border:1px solid rgba(0,0,0,0.05)'>".htmlspecialchars($m['text'])."</div>";
    unset($_SESSION['msg']);
}

// Load upcoming rides (driver)
$upcoming_drives = [];
$rides_with_counts = [];
if ($role === 'driver' || $role === 'admin') {
    $stmt = $mysqli->prepare("SELECT * FROM rides WHERE driver_id = ? AND ride_date >= CURDATE() ORDER BY ride_date, ride_time");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $upcoming_drives = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $count_stmt = $mysqli->prepare("
        SELECT 
            SUM(CASE WHEN status='confirmed' THEN seats ELSE 0 END) AS confirmed_seats,
            SUM(CASE WHEN status='pending' THEN seats ELSE 0 END) AS pending_seats
        FROM bookings WHERE ride_id = ?
    ");
    foreach ($upcoming_drives as $r) {
        $rid = (int)$r['id'];
        $count_stmt->bind_param('i', $rid);
        $count_stmt->execute();
        $rides_with_counts[$rid] = $count_stmt->get_result()->fetch_assoc();
    }
    $count_stmt->close();
}

// Load passenger bookings - include booking.created_at so we can pick the latest confirmed booking
$my_bookings = [];
$stmt = $mysqli->prepare("
  SELECT b.id AS booking_id, b.ride_id, b.seats AS booked_seats, b.status AS booking_status, b.created_at AS booked_at,
         r.from_location, r.to_location, r.ride_date, r.ride_time, r.available_seats, r.price AS ride_price,
         u.name AS driver_name
  FROM bookings b
  JOIN rides r ON b.ride_id = r.id
  JOIN users u ON r.driver_id = u.id
  WHERE b.user_id = ?
  ORDER BY b.created_at DESC
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$my_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Prepare list of confirmed bookings (most recent first)
$confirmed_bookings = array_values(array_filter($my_bookings, function($b){ return isset($b['booking_status']) && $b['booking_status'] === 'confirmed'; }));

// $csrf is already set near the top
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Bookings — HerSafar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400,600,700&display=swap" rel="stylesheet">
<style>
:root{
  --bg-a:#fbf9ff;--bg-b:#f7f3ff;
  --primary-1:#5b21b6;--primary-2:#8b5cf6;--accent:#c084fc;
  --muted:#6b4a86;--glass-border:rgba(255,255,255,0.35);
  --radius:12px;--shadow:0 18px 50px rgba(91,33,182,0.08);
  font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
  display:flex;gap:20px;min-height:100vh;padding:20px;
  background:
    radial-gradient(700px 300px at 10% 10%, rgba(139,92,246,0.045), transparent 10%),
    radial-gradient(600px 260px at 90% 90%, rgba(192,132,252,0.03), transparent 10%),
    linear-gradient(180deg,var(--bg-a),var(--bg-b));
  color:#241235;
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
.nav a{color:#fff;text-decoration:none;padding:10px 12px;border-radius:10px;font-weight:600;border:1px solid rgba(255,255,255,0.12);transition:all .18s;font-size:13px;}
.nav a:hover{transform:translateY(-2px);background:rgba(255,255,255,0.08)}
.nav a.active{background:rgba(255,255,255,0.12);box-shadow:0 6px 18px rgba(0,0,0,0.05)}

.spacer{flex:1}
.bottom-links{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.bottom-links a{display:block;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.95);color:#a855f7;text-decoration:none;font-weight:700;text-align:center;border:1px solid rgba(255,255,255,0.3)}
.bottom-links a:hover{background:#f3e8ff;color:#6b21b6}

.sidebar .logged-user{margin-top:16px;font-size:13px;color:#fff;opacity:0.95;text-align:center}

/* Main */
.main{flex:1;display:flex;flex-direction:column;gap:20px}
.card{
  background:linear-gradient(180deg,rgba(255,255,255,0.65),rgba(255,255,255,0.5));
  border:1px solid var(--glass-border);
  border-radius:var(--radius);
  padding:28px;
  box-shadow:var(--shadow);
  backdrop-filter:blur(10px) saturate(120%);
}
.header-row{margin-bottom:18px}
.h-title{font-size:22px;margin-bottom:4px;color:var(--primary-1)}
.h-sub{font-size:14px;color:var(--muted)}
.section{
  margin-top:12px;
  background:#fff;
  border-radius:12px;
  box-shadow:0 4px 18px rgba(0,0,0,0.03);
  padding:22px;
}
.section-title{
  font-size:18px;
  color:var(--primary-2);
  border-bottom:1px solid rgba(99,102,241,0.1);
  padding-bottom:8px;
  margin-bottom:12px;
}
.small{font-size:13px;color:var(--muted)}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:13px;font-weight:600;margin-right:6px}
.badge.confirm{background:#ecfdf5;color:#15803d}
.badge.pending{background:#fff7ed;color:#b45309}
.badge.default{background:#f3f4f6;color:#374151}
.ride{padding:14px 10px;border-radius:10px;border:1px solid #f1f5f9;margin-bottom:12px;background:#fff;}
.ride-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.ride-route{font-weight:700;color:#241235}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{padding:10px 8px;border-bottom:1px solid #f1f5f9;text-align:left;font-size:14px}
th{background:rgba(139,92,246,0.04);color:#241235;font-weight:600}

/* Receipt quick card (compact) */
.receipt-card{
  background:linear-gradient(180deg,#fff,#fbfbff);
  border:1px solid rgba(15,23,42,0.04);
  padding:10px;
  border-radius:10px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  margin-bottom:12px;
}
.receipt-card .left{font-size:13px}
.receipt-card .muted{font-size:12px;color:var(--muted);margin-top:4px}

/* Small button styles for receipts */
.btn-sm {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:6px 8px;            /* small */
  border-radius:8px;
  border:0;
  cursor:pointer;
  font-weight:700;
  font-size:13px;
  line-height:1;
  text-decoration:none;
  transition:all .16s ease;
}

/* Primary small */
.btn-sm.primary {
  background: linear-gradient(90deg,var(--primary-2),var(--primary-1));
  color:#fff;
  box-shadow: 0 6px 18px rgba(91,33,182,0.10);
}
.btn-sm.primary:hover { transform:translateY(-2px); }

/* Outline / ghost small */
.btn-sm.ghost {
  background:transparent;
  color:#111827;
  border:1px solid rgba(15,23,42,0.06);
  box-shadow:none;
}
.btn-sm.ghost:hover { background: rgba(243,244,246,0.9); }

/* anchor styled as small button */
.a-sm {
  display:inline-flex;
  align-items:center;
  padding:6px 8px;
  border-radius:8px;
  font-weight:700;
  font-size:13px;
  text-decoration:none;
  border:1px solid rgba(15,23,42,0.06);
  color:var(--primary-1);
  background:transparent;
}
.a-sm:hover { background: rgba(243,244,246,0.9); transform:translateY(-1px); }

/* footer & responsive */
.footer{position:fixed;right:16px;bottom:10px;font-size:12px;color:var(--muted);background:rgba(255,255,255,0.6);padding:6px 10px;border-radius:8px;border:1px solid rgba(99,102,241,0.06);backdrop-filter:blur(6px)}
@media(max-width:900px){body{flex-direction:column}.sidebar{width:100%;flex-direction:row;align-items:center;gap:10px}}
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
      <a href="dashboard.php">Dashboard</a>
      <a href="index.php">Home</a>
      <a href="profile.php">Profile</a>
      <a href="groups.php">Groups</a>
      <a href="manage_rides.php" class="active">Manage Bookings</a>
      <a href="join_ride.php">Join A Ride</a>
      <a href="apply_driver.php">Driver Applications</a>
    </nav>

    <div class="spacer"></div>
    <div class="logged-user">Signed in as<br><strong><?= $name ?></strong></div>

    <div class="bottom-links">
      <a href="change_password.php">Change Password</a>
      <a href="/hersafar/login.php">Logout</a>
    </div>
  </aside>

  <main class="main">
    <div class="card">
      <div class="header-row">
        <h2 class="h-title">Manage Bookings</h2>
        <div class="h-sub">Check your upcoming rides and bookings all in one place.</div>
      </div>

      <?= $flash_html ?>

      <!-- If user has at least one confirmed booking, show quick receipt area -->
      <?php if (!empty($confirmed_bookings)):
          // pick the most recent confirmed booking (already ordered by created_at desc)
          $latest_confirmed = $confirmed_bookings[0];
          $receipt_url = 'booking_receipt.php?id=' . (int)$latest_confirmed['booking_id'];
      ?>
        <div class="receipt-card" role="region" aria-label="Latest confirmed booking">
          <div class="left">
            <div style="font-weight:700">Latest confirmed booking</div>
            <div class="muted"><?= htmlspecialchars($latest_confirmed['from_location']) ?> → <?= htmlspecialchars($latest_confirmed['to_location']) ?> • <?= htmlspecialchars($latest_confirmed['ride_date']) ?> <?= htmlspecialchars($latest_confirmed['ride_time']) ?></div>
          </div>
          <div class="right">
            <a class="a-sm" href="<?= htmlspecialchars($receipt_url) ?>" target="_blank" rel="noopener" title="Open receipt">Receipt</a>
            <button type="button" class="btn-sm ghost" data-receipt-url="<?= htmlspecialchars($receipt_url) ?>" title="Open and print receipt">Print</button>
          </div>
        </div>
      <?php endif; ?>

      <!-- Upcoming rides section -->
      <?php if ($role === 'driver' || $role === 'admin'): ?>
      <div class="section">
        <div class="section-title">Upcoming Rides</div>
        <?php if (empty($upcoming_drives)): ?>
          <p class="small">You have no upcoming rides. <a href="post_ride.php" style="color:var(--primary-2);text-decoration:none;font-weight:600;">Post one now →</a></p>
        <?php else: ?>
          <?php foreach($upcoming_drives as $d):
            $rid = (int)$d['id'];
            $c = $rides_with_counts[$rid] ?? ['confirmed_seats'=>0,'pending_seats'=>0];
          ?>
            <div class="ride">
              <div class="ride-header">
                <div>
                  <div class="ride-route"><?= htmlspecialchars($d['from_location']) ?> → <?= htmlspecialchars($d['to_location']) ?></div>
                  <div class="small"><?= htmlspecialchars($d['ride_date']) ?> • <?= htmlspecialchars($d['ride_time']) ?> • Total: <?= (int)$d['seats'] ?></div>
                </div>
                <a href="view_ride.php?id=<?= $rid ?>" class="small" style="color:var(--primary-1);text-decoration:none;font-weight:600;">View Details →</a>
              </div>
              <div>
                <span class="badge confirm">Confirmed: <?= (int)$c['confirmed_seats'] ?></span>
                <span class="badge pending">Pending: <?= (int)$c['pending_seats'] ?></span>
                <span class="badge default">Available: <?= (int)$d['available_seats'] ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Passenger bookings section -->
      <div class="section">
        <div class="section-title">Your Bookings</div>
        <?php if (empty($my_bookings)): ?>
          <p class="small">You have no active bookings at the moment.</p>
        <?php else: ?>
          <div style="overflow:auto;">
            <table>
              <thead>
                <tr>
                  <th>Route</th>
                  <th>Date</th>
                  <th>Seats</th>
                  <th>Driver</th>
                  <th>Status</th>
                  <th>Available</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($my_bookings as $b): ?>
                  <tr>
                    <td><?= htmlspecialchars($b['from_location']) ?> → <?= htmlspecialchars($b['to_location']) ?></td>
                    <td><?= htmlspecialchars($b['ride_date']) ?> <?= htmlspecialchars($b['ride_time']) ?></td>
                    <td><?= (int)$b['booked_seats'] ?></td>
                    <td><?= htmlspecialchars($b['driver_name']) ?></td>
                    <td>
                      <?php if ($b['booking_status'] === 'confirmed'): ?>
                        <span class="badge confirm">Confirmed</span>
                      <?php elseif ($b['booking_status'] === 'pending'): ?>
                        <span class="badge pending">Pending</span>
                      <?php else: ?>
                        <span class="badge default">Cancelled</span>
                      <?php endif; ?>
                    </td>
                    <td><?= (int)$b['available_seats'] ?></td>
                    <td style="white-space:nowrap">
                      <?php if ($b['booking_status'] === 'confirmed'):
                        $rurl = 'booking_receipt.php?id=' . (int)$b['booking_id'];
                      ?>
                        <a class="a-sm" href="<?= htmlspecialchars($rurl) ?>" target="_blank" rel="noopener" title="Open receipt">Receipt</a>
                        <button type="button" class="btn-sm ghost" data-receipt-url="<?= htmlspecialchars($rurl) ?>" title="Open and print">Print</button>
                        <!-- Cancel form for confirmed bookings -->
                        <form method="post" style="display:inline;margin-left:8px" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                          <input type="hidden" name="cancel_booking_id" value="<?= (int)$b['booking_id'] ?>">
                          <button type="submit" class="btn-sm ghost" title="Cancel booking">Cancel</button>
                        </form>
                      <?php elseif ($b['booking_status'] === 'pending'): ?>
                        <span class="badge pending">Pending</span>
                        <!-- Allow cancelling pending bookings -->
                        <form method="post" style="display:inline;margin-left:8px" onsubmit="return confirm('Cancel your pending booking?');">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                          <input type="hidden" name="cancel_booking_id" value="<?= (int)$b['booking_id'] ?>">
                          <button type="submit" class="btn-sm ghost" title="Cancel booking">Cancel</button>
                        </form>
                      <?php else: ?>
                        <span class="small">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

<script>
(function(){
  // open a new window/tab and attempt to print the document. Best-effort; popup blockers may block window.open.
  function openAndPrint(url) {
    var w = window.open(url, '_blank', 'noopener');
    if (!w) {
      alert('Popup blocked. Please allow popups for this site to print receipts.');
      return;
    }
    // try to print when the new window loads; retry a few times to improve reliability
    var printed = false;
    function tryPrint() {
      try {
        if (w.document && !printed) {
          printed = true;
          w.focus();
          // Many receipt pages call window.print() themselves. This is a fallback.
          w.print();
        }
      } catch (e) {
        if (!printed) setTimeout(tryPrint, 300);
      }
    }
    if (w.addEventListener) {
      w.addEventListener('load', tryPrint);
    } else {
      setTimeout(tryPrint, 700);
    }
    setTimeout(tryPrint, 800);
    setTimeout(tryPrint, 1500);
  }

  document.addEventListener('click', function(e){
    var btn = e.target.closest('button[data-receipt-url]');
    if (!btn) return;
    var url = btn.getAttribute('data-receipt-url');
    if (!url) return;
    openAndPrint(url);
  }, false);
})();
</script>
<div class="footer">&copy; <?= date('Y') ?> HerSafar — Safe, Smart, and Connected Travel</div>
</body>
</html>
