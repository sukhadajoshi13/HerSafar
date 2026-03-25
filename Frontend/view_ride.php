<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$uid = (int)($_SESSION['user']['id'] ?? 0);
$role = $_SESSION['user']['role'] ?? null;
$csrf = csrf_token();

$ride_id = (int)($_GET['id'] ?? 0);
if ($ride_id <= 0) {
    set_flash('error','Invalid ride id.');
    header('Location: dashboard.php'); exit;
}

$stmt = $mysqli->prepare("
    SELECT r.*, u.id AS driver_id, u.name AS driver_name, u.phone AS driver_phone, u.email AS driver_email,
           u.vehicle_make, u.vehicle_model, u.vehicle_number, u.bio
    FROM rides r
    JOIN users u ON r.driver_id = u.id
    WHERE r.id = ? LIMIT 1
");
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ride) {
    set_flash('error','Ride not found.');
    header('Location: dashboard.php'); exit;
}

/* ---------- Booking seats summary: pending / confirmed / cancelled ---------- */
$counts = ['pending'=>0,'confirmed'=>0,'cancelled'=>0];
$stmt = $mysqli->prepare("
    SELECT status, COALESCE(SUM(seats),0) AS seats_sum
    FROM bookings
    WHERE ride_id = ?
    GROUP BY status
");
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $counts[$r['status']] = (int)$r['seats_sum'];
}
$stmt->close();

$confirmed_seats = $counts['confirmed'];
$pending_seats = $counts['pending'];
$cancelled_seats = $counts['cancelled'];
$total_seats = (int)$ride['seats'];
$available_seats = (int)$ride['available_seats'];

/* ---------- Load bookings for driver/admin ---------- */
$bookings = [];
$is_owner_or_admin = ($uid && (($uid === (int)$ride['driver_id']) || ($role === 'admin')));
if ($is_owner_or_admin) {
    $stmt = $mysqli->prepare("
        SELECT b.id, b.user_id, b.seats, b.status, b.created_at, u.name AS passenger_name, u.email AS passenger_email, u.phone AS passenger_phone
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.ride_id = ?
        ORDER BY FIELD(b.status,'pending','confirmed','cancelled'), b.created_at ASC
    ");
    $stmt->bind_param('i', $ride_id);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ---------- Current user's booking for this ride (if any) ---------- */
$my_booking = null;
if ($uid) {
    $stmt = $mysqli->prepare("SELECT id, seats, status, created_at FROM bookings WHERE ride_id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param('ii', $ride_id, $uid);
    $stmt->execute();
    $my_booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ---------- Recent messages to user (optional) ---------- */
$messages = [];
if ($uid) {
    $stmt = $mysqli->prepare("SELECT id, sender_id, message, sent_at FROM messages WHERE receiver_id = ? ORDER BY sent_at DESC LIMIT 10");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($m = $res->fetch_assoc()) $messages[] = $m;
        $stmt->close();
    }
}

/* ---------- Flash ---------- */
$flash = '';
if (!empty($_SESSION['msg'])) {
    $m = $_SESSION['msg'];
    $flash = "<div style=\"" . ($m['type']==='success' ? 'color:green' : 'color:red') . ";margin-bottom:12px\">" . htmlspecialchars($m['text']) . "</div>";
    unset($_SESSION['msg']);
}

/* ---------- Build share URL if share_token exists ---------- */
$share_url = '';
if (!empty($ride['share_token'])) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $share_url = rtrim("$proto://$host", '/') . '/view_ride.php?share=' . urlencode($ride['share_token']);
}

/* ---------- Generated links from session (post_ride results) ---------- */
$generated_links = $_SESSION['last_generated_share_links'] ?? null;
if (!empty($generated_links)) unset($_SESSION['last_generated_share_links']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>View Ride - HerSafar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg-a:#fbf9ff; --bg-b:#f7f3ff;
      --primary-1:#5b21b6; --primary-2:#8b5cf6; --accent:#c084fc;
      --muted:#6b4a86; --card:#ffffff;
      --radius:12px; --shadow:0 18px 50px rgba(91,33,182,0.06);
      --glass-border: rgba(255,255,255,0.14);
      --gutter:22px;
      --max-width:1100px;
      font-family: 'Poppins', system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      color-scheme: light;
      --text: #241235;
    }
    *{box-sizing:border-box}

/* Reset + layout helpers (match about_us.php) */
html, body {
  height: 100%;
  margin: 0;
  display: flex;
  flex-direction: column;
  -webkit-font-smoothing: antialiased;
  background:
    radial-gradient(700px 300px at 8% 8%, rgba(139,92,246,0.03), transparent 12%),
    radial-gradient(600px 260px at 92% 92%, rgba(192,132,252,0.02), transparent 12%),
    linear-gradient(180deg,var(--bg-a),var(--bg-b));
  color: var(--text);
}

/* main container aligns with header gutters */
main.container{
  width: calc(100% - (var(--gutter) * 2));
  max-width: var(--max-width);
  margin: 26px var(--gutter);
  padding: 18px;
  box-sizing: border-box;
  flex: 1 0 auto; /* expand so footer is pushed to bottom */
}

/* ====== Card + container (final, centered, roomy enough) ====== */
.wrap,
main.container {
  width: 100%;
  max-width: 100%;
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  overflow: visible;
}

/* Centered card: roomy enough for inner layout */
.card {
  width: calc(100% - 44px);   /* same gutters as header/footer (22px each side) */
  max-width: 1100px;          /* roomy enough for two-column content */
  margin: 20px auto;          /* top gap + center horizontally */
  background: linear-gradient(180deg, rgba(255,255,255,0.72), rgba(255,255,255,0.6));
  border-radius: var(--radius);
  padding: 22px;
  box-shadow: var(--shadow);
  border: 1px solid rgba(15,23,42,0.04);
  backdrop-filter: blur(8px) saturate(120%);
  box-sizing: border-box;
  overflow: visible;
}

/* Responsive inner grid */
.grid {
  display: grid;
  gap: 18px;
  grid-template-columns: 1fr minmax(240px, 360px);
  align-items: start;
}
@media (max-width: 980px) {
  .card { width: calc(100% - 28px); margin: 20px auto; padding:18px; }
  .grid { grid-template-columns: 1fr; }
}

/* Safety rules for inner contents */
.card table {
  width: 100%;
  border-collapse: collapse;
  overflow: auto;
  display: block;
  max-width: 100%;
}
.card code,
.card pre,
.card .link-row input,
.card input[type="text"],
.card input[type="email"],
.card textarea {
  word-break: break-word;
  overflow-wrap: anywhere;
  max-width: 100%;
  box-sizing: border-box;
}
.card .form-inline,
.card .link-row { display: flex; flex-wrap: wrap; gap: 8px; }
.side, .card-small { width: 100%; box-sizing: border-box; }
.card .overflow-auto { overflow: auto; max-width: 100%; }
@media (max-width: 480px) {
  .card { padding: 14px; margin: 16px; width: calc(100% - 32px); }
}
@media (max-width: 768px) {
  .card { width: calc(100% - 32px); margin: 24px auto; padding: 18px; }
}

/* topbar + text styles */
.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
.back{font-size:14px;color:var(--muted);text-decoration:none}
.title{font-size:20px;color:var(--primary-1);font-weight:700;margin:0}
.subtitle{color:var(--muted);font-size:13px;margin-top:4px}

/* section/content */
.section{background:#fff;border-radius:10px;padding:16px;border:1px solid rgba(15,23,42,0.04)}
.row{display:flex;gap:12px;align-items:center}
.route{font-weight:800;font-size:18px;color:#241235}
.meta{color:var(--muted);font-size:13px}
.pill{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:700;font-size:13px}
.badge-available{background:#eef2ff;color:#1e3a8a}
.badge-pending{background:#fff7ed;color:#92400e}
.badge-confirm{background:#ecfdf5;color:#065f46}

/* driver */
.driver-card{display:flex;gap:12px;align-items:center}
.avatar{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,var(--primary-2),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px}
.driver-info{font-size:14px}
.driver-info .name{font-weight:700;color:#241235}
.driver-info .sub{color:var(--muted);font-size:13px;margin-top:2px}

/* table */
th,td{padding:10px 8px;border-bottom:1px solid #f1f5f9;text-align:left;font-size:14px}
th{background:rgba(139,92,246,0.04);font-weight:700;color:#241235}

/* inputs and selects */
select.input, input.input{padding:8px;border-radius:8px;border:1px solid rgba(99,102,241,0.06);background:#fff}
.form-inline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}

/* ===== Compact button styles (drop-in replacement) ===== */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 700;
  font-size: 13px;
  line-height: 1;
  padding: 8px 12px;
  transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
  box-sizing: border-box;
}

/* small variant for table / inline actions */
.btn.sm {
  padding: 6px 9px;
  font-size: 12px;
  border-radius: 7px;
}

/* tiny variant */
.btn.xs {
  padding: 4px 8px;
  font-size: 11px;
  border-radius: 6px;
}

/* Primary */
.btn.primary {
  background: linear-gradient(90deg, var(--primary-2), var(--primary-1));
  color: #fff;
  box-shadow: 0 6px 16px rgba(91,33,182,0.09);
}
.btn.primary:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(91,33,182,0.12); }

/* Ghost / outline */
.btn.ghost {
  background: transparent;
  border: 1px solid rgba(15,23,42,0.06);
  color: #241235;
}
.btn.ghost:hover { background: rgba(15,23,42,0.03); }

/* Danger (red) */
.btn.danger {
  background: #ef4444;
  color: #fff;
  box-shadow: 0 6px 14px rgba(239,68,68,0.12);
}
.btn.danger:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(239,68,68,0.14); }

/* helpers */
.btn-row { display:flex; gap:8px; align-items:center; }
.copybtn { padding:6px 10px; font-size:12px; border-radius:8px; }

/* small UI helpers */
.side{display:flex;flex-direction:column;gap:12px}
.card-small{background:#fbfdff;padding:12px;border-radius:10px;border:1px solid rgba(15,23,42,0.04)}
.link-row{display:flex;gap:8px;align-items:center;margin-top:8px}
.link-row input{flex:1;padding:8px;border-radius:8px;border:1px solid #eef2ff;background:#fff}
.small{font-size:13px;color:var(--muted)}

.messages{background:linear-gradient(180deg,#fff,#fbfbff);padding:10px;border-radius:8px;border:1px solid rgba(15,23,42,0.03)}
.msg-item{padding:8px;border-bottom:1px solid #f1f5f9}
.muted{color:var(--muted)}

.footer{margin-top:12px;text-align:center;color:var(--muted);font-size:13px}
  </style>
</head>
<body>
  <?php include 'header.php' ?>
  <div class="wrap">
    <div class="card" role="main" aria-labelledby="ride-heading">

      <div class="topbar">
        <div>
          <a class="back" href="manage_rides.php">← Back To Manage Bookings</a>
        </div>

        <div style="text-align:right">
          <div style="margin-top:6px">
            <span class="pill badge-available">Available: <?= (int)$available_seats; ?></span>
            <span class="pill badge-confirm" style="margin-left:8px">Confirmed: <?= (int)$confirmed_seats; ?></span>
          </div>
        </div>
      </div>

      <?php echo $flash; ?>

      <div class="grid">

        <!-- LEFT -->
        <div>
          <div class="section" aria-labelledby="details-title">
            <div class="row" style="justify-content:space-between">
              <div>
                <div class="route"><?= htmlspecialchars($ride['from_location']) ?> → <?= htmlspecialchars($ride['to_location']) ?></div>
                <div class="meta" style="margin-top:6px">Price: <strong>₹<?= htmlspecialchars($ride['price']) ?></strong> • Seats: <strong><?= (int)$ride['seats'] ?></strong></div>
              </div>
            </div>

            <hr style="margin:12px 0;border:none;border-top:1px solid #f3f4f6">

            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
              <div style="flex:1;margin-right:12px">
                <h3 style="margin:0 0 8px 0">Driver</h3>
                <div class="driver-card">
                  <div class="avatar" aria-hidden="true"><?= htmlspecialchars(substr($ride['driver_name'],0,1)); ?></div>
                  <div class="driver-info">
                    <div class="name"><?= htmlspecialchars($ride['driver_name']); ?></div>
                    <div class="sub"><?= htmlspecialchars($ride['driver_phone'] ?? ''); ?></div>
                    <div class="small" style="margin-top:6px">
                      <?= htmlspecialchars(trim(($ride['vehicle_make'] ?? '') . ' ' . ($ride['vehicle_model'] ?? '') . ' ' . ($ride['vehicle_number'] ?? ''))); ?>
                    </div>
                  </div>
                </div>

                <?php if (!empty($ride['bio'])): ?>
                  <div style="margin-top:12px" class="muted"><?= nl2br(htmlspecialchars($ride['bio'])); ?></div>
                <?php endif; ?>
              </div>

              <div style="width:240px">
                <div class="section" style="padding:12px">
                  <div style="font-weight:700">Bookings summary</div>
                  <div class="small" style="margin-top:8px">Pending: <?= (int)$counts['pending']; ?></div>
                  <div class="small">Confirmed: <?= (int)$confirmed_seats; ?></div>
                  <div class="small">Cancelled: <?= (int)$counts['cancelled']; ?></div>
                </div>

                <?php if ($uid && ($uid === (int)$ride['driver_id'] || $role === 'admin')): ?>
                  <div style="margin-top:12px" class="small muted">Driver controls available below.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- bookings / booking form -->
          <div class="section" style="margin-top:14px">
            <?php if ($is_owner_or_admin): ?>
              <h3 style="margin:0 0 10px 0">Booking requests</h3>
              <?php if (empty($bookings)): ?>
                <div class="muted">No booking requests yet.</div>
              <?php else: ?>
                <table aria-describedby="booking-requests">
                  <thead>
                    <tr><th>ID</th><th>Passenger</th><th>Contact</th><th>Seats</th><th>Status</th><th>Requested</th><th>Actions</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach($bookings as $b): ?>
                      <tr>
                        <td><?= (int)$b['id']; ?></td>
                        <td><?= htmlspecialchars($b['passenger_name']); ?></td>
                        <td class="small"><?= htmlspecialchars($b['passenger_email']); ?> | <?= htmlspecialchars($b['passenger_phone']); ?></td>
                        <td><?= (int)$b['seats']; ?></td>
                        <td>
                          <?php if ($b['status'] === 'confirmed'): ?>
                            <span class="pill badge-confirm">Confirmed</span>
                          <?php elseif ($b['status'] === 'pending'): ?>
                            <span class="pill badge-pending">Pending</span>
                          <?php else: ?>
                            <span class="small muted">Cancelled</span>
                          <?php endif; ?>
                        </td>
                        <td class="small"><?= htmlspecialchars($b['created_at']); ?></td>
                        <td>
                          <?php if ($b['status'] === 'pending'): ?>
                            <div class="btn-row" style="display:inline-flex">
                              <form method="POST" action="booking_actions.php" style="display:inline">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="confirm">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['id']; ?>">
                                <button class="btn primary sm" type="submit" onclick="return confirm('Confirm this booking?')">Confirm</button>
                              </form>
                              <form method="POST" action="booking_actions.php" style="display:inline">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['id']; ?>">
                                <button class="btn ghost sm" type="submit" onclick="return confirm('Cancel this booking?')">Cancel</button>
                              </form>
                            </div>
                          <?php else: ?>
                            <span class="small muted">—</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>

            <?php else: /* passenger/public view */ ?>

              <?php if ($my_booking): ?>
                <h3 style="margin:0 0 10px 0">Your booking</h3>
                <div class="small">Seats: <strong><?= (int)$my_booking['seats']; ?></strong></div>
                <div class="small" style="margin-top:6px">Status: <strong><?= htmlspecialchars(ucfirst($my_booking['status'])); ?></strong></div>
                <div style="margin-top:8px">
                  <?php if ($my_booking['status'] === 'pending'): ?>
                    <div style="display:inline-block;background:#ecfdf5;color:#065f46;font-weight:700;padding:8px 12px;border-radius:8px;box-shadow:0 6px 18px rgba(6,95,70,0.06);">
                      Your booking is pending driver confirmation.
                    </div>
                  <?php elseif ($my_booking['status'] === 'confirmed'): ?>
                    <div style="display:inline-block;background:#ecfdf5;color:#065f46;font-weight:700;padding:8px 12px;border-radius:8px;box-shadow:0 6px 18px rgba(6,95,70,0.06);">
                      Booking confirmed — contact the driver if needed.
                    </div>
                  <?php else: ?>
                    <div class="small">Booking cancelled.</div>
                  <?php endif; ?>
                </div>
              <?php else: ?>

                <?php if (empty($uid)): ?>
                  <div class="muted">Please <a href="login.php">log in</a> to request booking.</div>

                <?php elseif ((int)$ride['available_seats'] <= 0): ?>
                  <div style="color:#ef4444;font-weight:700">No seats available.</div>

                <?php else: ?>
                  <h3 style="margin:0 0 10px 0">Request booking</h3>
                  <form method="POST" action="book_ride.php" class="form-inline" style="align-items:center">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="ride_id" value="<?= (int)$ride['id']; ?>">
                    <label class="small" for="seats">Seats</label>
                    <select id="seats" name="seats" class="input" style="width:96px">
                      <?php for($i=1;$i<=min(6,(int)$ride['available_seats']);$i++): ?>
                        <option value="<?= $i; ?>"><?= $i; ?></option>
                      <?php endfor; ?>
                    </select>
                    <div class="btn-row">
                      <button class="btn primary sm" type="submit">Book Ride</button>
                    </div>
                  </form>
                <?php endif; ?>

              <?php endif; ?>
            <?php endif; ?>
          </div>

          <!-- Messages -->
          <div class="section" style="margin-top:14px">
            <h3 style="margin:0 0 10px 0">Messages</h3>
            <div class="messages" role="log" aria-live="polite">
              <?php if (empty($messages)): ?>
                <div class="muted">No messages yet.</div>
              <?php else: ?>
                <?php foreach($messages as $m): ?>
                  <div class="msg-item">
                    <div class="small"><?= htmlspecialchars($m['sent_at']); ?></div>
                    <div><?= nl2br(htmlspecialchars($m['message'])); ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- RIGHT -->
        <aside class="side">
          <div class="card-small">
            <div style="font-weight:700">Share this ride</div>
            <div class="small" style="margin-top:6px">Share links generated when the driver posted the ride appear here.</div>

            <?php if (!empty($generated_links) && is_array($generated_links)): ?>
              <?php foreach($generated_links as $l): ?>
                <div style="margin-top:10px">
                  <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($l['label'] ?? 'Link'); ?></div>
                  <div class="link-row">
                    <input type="text" readonly value="<?= htmlspecialchars($l['url'] ?? ''); ?>" id="link-<?= htmlspecialchars($l['token']); ?>">
                    <button class="copybtn" data-clip="<?= htmlspecialchars($l['url'] ?? ''); ?>">Copy</button>
                  </div>
                  <div class="small" style="margin-top:6px">Token: <code><?= htmlspecialchars($l['token'] ?? ''); ?></code><?php if(!empty($l['group_id'])) echo ' • Group: ' . (int)$l['group_id']; ?></div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <?php if ($share_url): ?>
                <div class="link-row" style="margin-top:10px">
                  <input type="text" readonly value="<?= htmlspecialchars($share_url); ?>">
                  <button class="copybtn" data-clip="<?= htmlspecialchars($share_url); ?>">Copy</button>
                </div>
              <?php else: ?>
                <div class="small" style="margin-top:10px">No share links available for this ride.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <div class="card-small" aria-hidden="false">
            <div style="font-weight:700">Quick details</div>
            <div class="small" style="margin-top:8px">
              <div><strong>Date:</strong> <?= htmlspecialchars($ride['ride_date']); ?></div>
              <div style="margin-top:6px"><strong>Time:</strong> <?= htmlspecialchars($ride['ride_time'] ?: '—'); ?></div>
              <div style="margin-top:6px"><strong>Price:</strong> ₹<?= htmlspecialchars($ride['price']); ?></div>
              <div style="margin-top:6px"><strong>Seats left:</strong> <?= (int)$ride['available_seats']; ?></div>
            </div>

            <?php if ($is_owner_or_admin): ?>
              <div style="margin-top:10px;">
                <div class="btn-row" aria-hidden="false">
                  <form method="POST" action="delete_ride.php"
                        onsubmit="return confirm('Delete this ride? This will remove all bookings.')">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
                    <button class="btn danger sm" type="submit">Delete</button>
                  </form>
                </div>
              </div>
            <?php endif; ?>

          </div>

        </aside>

      </div>

      <div class="footer">All actions are subject to driver approval. Public links allow anyone with the token to view & book.</div>
    </div>
  </div>
   <?php include 'footer.php'; ?>

<script>
(function(){
  // copy-to-clipboard for generated links
  document.addEventListener('click', function(e){
    if (e.target && e.target.matches('.copybtn')) {
      var url = e.target.getAttribute('data-clip');
      if (!url) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function(){
          var prev = e.target.innerText;
          e.target.innerText = 'Copied';
          setTimeout(function(){ e.target.innerText = prev; }, 1400);
        }, function(){ alert('Copy failed — please copy manually.'); });
      } else {
        var input = e.target.parentElement.querySelector('input');
        if (input) {
          input.select();
          try { document.execCommand('copy'); e.target.innerText = 'Copied'; setTimeout(function(){ e.target.innerText = 'Copy'; },1400); }
          catch(err){ alert('Copy not supported — please copy manually.'); }
        }
      }
    }
  });
})();
</script>
</body>
</html>
