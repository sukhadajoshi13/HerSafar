<?php
require_once 'dbcon.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// try to obtain booking id from multiple sources
$booking_id = 0;
if (!empty($_GET['id'])) $booking_id = (int)$_GET['id'];
elseif (!empty($_GET['booking_id'])) $booking_id = (int)$_GET['booking_id'];

// optional: support token lookup (if you have tokens for bookings)
$token = trim((string)($_GET['token'] ?? ''));

// fallback to last_confirmed_booking_id in session (useful after redirect)
if ($booking_id <= 0 && !empty($_SESSION['last_confirmed_booking_id'])) {
    $booking_id = (int)$_SESSION['last_confirmed_booking_id'];
    // do not unset here so multiple prints work; your app may unset if preferred
}

// if still none and token provided, try token (example column: share_token)
if ($booking_id <= 0 && $token !== '') {
    $token = preg_replace('/[^0-9A-Za-z_\-]/', '', $token);
    $stmt = $mysqli->prepare("SELECT id FROM bookings WHERE share_token = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) $booking_id = (int)$r['id'];
    }
}

// if still invalid, show friendly message
if ($booking_id <= 0) {
    http_response_code(400);
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Receipt — Invalid booking</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>body{font-family:Arial,Helvetica,sans-serif;background:#f7fafc;color:#111;padding:28px} .box{max-width:760px;margin:0 auto;background:#fff;padding:20px;border-radius:10px;border:1px solid #eef}</style>
    </head><body>
      <div class="box">
        <h2>Booking not specified</h2>
        <p>We couldn't find a booking to build a receipt for. This page expects a booking id (e.g. <code>?id=123</code>), a booking token, or a recent confirmed booking in your session.</p>
        <p>What you can do:</p>
        <ul>
          <li>Open your bookings page and click "Print receipt" for the confirmed booking.</li>
          <li>If you just confirmed a booking, ensure your confirmation code sets <code>$_SESSION['last_confirmed_booking_id']</code> then redirects here.</li>
        </ul>
        <p><a href="dashboard.php">Back to dashboard</a> — <a href="my_bookings.php">My bookings</a></p>
      </div>
    </body></html>
    <?php
    exit;
}

// load booking + ride + driver + passenger
$stmt = $mysqli->prepare("
  SELECT 
    b.id AS booking_id, b.user_id AS passenger_id, b.seats AS seats_booked, b.status AS booking_status, b.created_at AS booked_at,
    r.id AS ride_id, r.from_location, r.to_location, r.ride_date, r.ride_time, r.price AS ride_price, r.seats AS ride_total_seats, r.available_seats,
    u_driver.id AS driver_id, u_driver.name AS driver_name, u_driver.phone AS driver_phone, u_driver.email AS driver_email,
    u_passenger.name AS passenger_name, u_passenger.email AS passenger_email, u_passenger.phone AS passenger_phone
  FROM bookings b
  JOIN rides r ON b.ride_id = r.id
  JOIN users u_driver ON r.driver_id = u_driver.id
  JOIN users u_passenger ON b.user_id = u_passenger.id
  WHERE b.id = ? 
  LIMIT 1
");
if (!$stmt) {
    error_log("DB prepare failed: " . $mysqli->error);
    http_response_code(500);
    echo "Server error.";
    exit;
}
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Receipt — Not found</title></head><body style="font-family:Arial;padding:20px">
      <h2>Booking not found</h2>
      <p>We could not find booking ID <?php echo (int)$booking_id; ?> in the database. It may have been deleted or you may be using an incorrect id.</p>
      <p><a href="dashboard.php">Back to dashboard</a></p>
    </body></html>
    <?php
    exit;
}

// Authorization: only passenger, driver or admin
$uid = (int)($_SESSION['user']['id'] ?? 0);
$role = $_SESSION['user']['role'] ?? null;
if (!($uid === (int)$booking['passenger_id'] || $uid === (int)$booking['driver_id'] || $role === 'admin')) {
    http_response_code(403);
    echo "You are not authorized to view this receipt.";
    exit;
}

// ensure it's confirmed (optional: you can show for pending too)
if ($booking['booking_status'] !== 'confirmed') {
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Receipt — Not confirmed</title></head>
    <body style="font-family:Arial;padding:20px">
      <h2>Booking is not confirmed</h2>
      <p>This booking is currently <strong><?php echo h($booking['booking_status']); ?></strong>. A printable receipt is only available for confirmed bookings.</p>
      <p><a href="javascript:history.back()">Back</a></p>
    </body></html>
    <?php
    exit;
}

// compute totals
$seats = (int)$booking['seats_booked'];
$price_per_seat = (float)($booking['ride_price'] ?? 0.0);
$total_amount = $price_per_seat * $seats;

// company contact — update to real values
$company_name = "HerSafar";
$company_contact_phone = "+91-7558401837";
$company_contact_email = "hersafarenquiry@gmail.com";

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Receipt</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--accent:#5b21b6;--muted:#6b7280;--card:#fff;--radius:12px}
*{box-sizing:border-box}
body{font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#f5f6fb;padding:20px;color:#111827}
.wrap{max-width:820px;margin:0 auto}
.receipt{background:var(--card);padding:22px;border-radius:12px;border:1px solid rgba(15,23,42,0.04);box-shadow:0 12px 30px rgba(16,24,40,0.04)}
.header{display:flex;justify-content:space-between;align-items:flex-start}
.brand{font-weight:700;color:var(--accent);font-size:18px}
.meta{color:var(--muted);font-size:13px}
.section{margin-top:18px;padding-top:12px;border-top:1px dashed #eef2ff}
.grid{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center}
.label{color:var(--muted);font-size:13px}
table{width:100%;border-collapse:collapse;margin-top:12px;font-size:14px}
th,td{padding:10px;border-bottom:1px solid #f3f4f6;text-align:left}
th{background:#faf8ff;text-align:left;color:#22163a;font-weight:700}
.total-row td{font-weight:800}
.print-actions{display:flex;gap:8px;align-items:center;margin-top:16px}
.btn{background:linear-gradient(90deg,#8b5cf6,#5b21b6);color:#fff;padding:10px 14px;border-radius:8px;border:0;cursor:pointer;font-weight:700}
.btn.ghost{background:transparent;border:1px solid rgba(15,23,42,0.06);color:#111827}
.small{font-size:13px;color:var(--muted)}
.contact{margin-top:14px;padding:12px;border-radius:10px;background:#fbfbff;border:1px solid rgba(15,23,42,0.03);font-size:14px}
@media print {
  body{background:white;padding:0}
  .print-hide{display:none!important}
  .receipt{box-shadow:none;border-radius:0;padding:0}
  .wrap{max-width:100%;margin:0}
}
</style>
</head>
<body>
  <div class="wrap">
    <div class="receipt" role="article" aria-label="Booking receipt">
      <div class="header">
        <div>
          <div class="brand"><?php echo h($company_name); ?></div>
          <div class="meta">Booking Receipt</div>
        </div>
        <div style="text-align:right">
          <div style="font-weight:700">Receipt: <?php echo h($booking['booking_id']); ?></div>
          <div class="small">Booked on: <?php echo h($booking['booked_at']); ?></div>
        </div>
      </div>

      <div class="section">
        <div style="display:flex;justify-content:space-between;gap:12px">
          <div>
            <div class="label">Passenger</div>
            <div style="font-weight:700"><?php echo h($booking['passenger_name']); ?></div>
            <div class="small"><?php echo h($booking['passenger_email']); ?><?php if(!empty($booking['passenger_phone'])) echo ' · '.h($booking['passenger_phone']); ?></div>
          </div>

          <div style="text-align:right">
            <div class="label">Driver</div>
            <div style="font-weight:700"><?php echo h($booking['driver_name']); ?></div>
            <div class="small"><?php echo h($booking['driver_phone']); ?> · <?php echo h($booking['driver_email']); ?></div>
          </div>
        </div>
      </div>

      <div class="section" aria-labelledby="ride-info">
        <div id="ride-info" style="font-weight:700;margin-bottom:8px">Ride details</div>

        <div class="grid">
          <div>
            <div class="label">Route</div>
            <div style="font-weight:700"><?php echo h($booking['from_location']); ?> → <?php echo h($booking['to_location']); ?></div>
            <div class="small"><?php echo h($booking['ride_date']); ?> • <?php echo h($booking['ride_time']); ?></div>
          </div>

          <div style="text-align:right">
            <div class="label">Ride ID</div>
            <div style="font-weight:700"><?php echo (int)$booking['ride_id']; ?></div>
          </div>
        </div>

        <table aria-label="fare">
          <thead>
            <tr><th>Description</th><th style="width:140px;text-align:right">Amount</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <div style="font-weight:700"><?php echo h($seats); ?> × Seat(s) @ ₹<?php echo number_format($price_per_seat,2); ?> each</div>
                <div class="small">Booking · Confirmed</div>
              </td>
              <td style="text-align:right">₹<?php echo number_format($total_amount,2); ?></td>
            </tr>

            <tr class="total-row">
              <td style="text-align:right">Total</td>
              <td style="text-align:right">₹<?php echo number_format($total_amount,2); ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="contact" role="contentinfo">
        <div style="font-weight:700;margin-bottom:6px">In an emergency or to contact HerSafar</div>
        <div class="small">Call: <strong><?php echo h($company_contact_phone); ?></strong> · Email: <strong><?php echo h($company_contact_email); ?></strong></div>
        <div class="small" style="margin-top:8px">Please share your Receipt # and Booking ID when contacting support.</div>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px">
        <div class="small">This is an electronic receipt. No signature required.</div>

        <div class="print-actions print-hide">
          <button class="btn" onclick="window.print()">Print Receipt</button>
          <a class="btn ghost" href="manage_rides.php">Close</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
