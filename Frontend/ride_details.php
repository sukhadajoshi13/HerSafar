<?php
require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$ride_id = (int)($_GET['id'] ?? 0);
if ($ride_id <= 0) { echo "Invalid ride id."; exit; }

// Load ride + driver
$stmt = $mysqli->prepare("
    SELECT r.*, u.id AS driver_id, u.name AS driver_name, u.email AS driver_email, u.phone AS driver_phone,
           u.bio AS driver_bio
    FROM rides r
    JOIN users u ON r.driver_id = u.id
    WHERE r.id = ? LIMIT 1
");
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (! $ride) { echo "Ride not found."; exit; }

// Counts by booking status
$counts = ['pending'=>0,'confirmed'=>0,'cancelled'=>0,'total'=>0];
$stmt = $mysqli->prepare("SELECT status, COUNT(*) AS cnt, SUM(seats) AS seats_sum FROM bookings WHERE ride_id = ? GROUP BY status");
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $status = $r['status'];
    $counts[$status] = (int)$r['seats_sum'];
    $counts['total'] += (int)$r['seats_sum'];
}
$stmt->close();

// Load passenger list (name, seats, status)
$passengers = [];
$stmt = $mysqli->prepare("
    SELECT b.id, b.user_id, b.seats, b.status, b.created_at, u.name AS passenger_name, u.phone AS passenger_phone, u.email AS passenger_email
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.ride_id = ?
    ORDER BY FIELD(b.status,'pending','confirmed','cancelled'), b.created_at ASC
");
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $passengers[] = $row;
$stmt->close();

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Ride details — Hersafar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg-a:#fbf9ff;
  --bg-b:#f7f3ff;
  --primary-1:#5b21b6;
  --primary-2:#8b5cf6;
  --muted:#6b4a86;
  --text:#241235;
  --card:#ffffff;
  --glass:rgba(255,255,255,0.6);
  --radius:12px;
  --shadow: 0 20px 50px rgba(91,33,182,0.06);
  --border:#eef2f6;
}
*{box-sizing:border-box}
html,body{height:100%;margin:0}
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

/* Keep gutters consistent with header */
:root { --gutter:22px; }

/* main container aligns with header gutters */
main.container{
  width: calc(100% - (var(--gutter) * 2));
  max-width: var(--max-width);
  margin: 26px var(--gutter);
  padding: 18px;
  box-sizing: border-box;
  flex: 1 0 auto; /* expand so footer is pushed to bottom */
}

.container {
  width: 100%;
  max-width: 980px;
  margin: 0 auto;        /* <-- centers horizontally */
  box-sizing: border-box;/* keeps padding inside the width */
  padding: 0 16px;       /* optional horizontal padding for small screens */
}


/* main card */
.card {
   margin: 20px auto 0;  
  background: linear-gradient(180deg, rgba(255,255,255,0.88), rgba(255,255,255,0.82));
  border-radius:16px;
  padding:20px;
  padding-top:64px; /* increased top padding to avoid overlap with absolute back link */
  box-shadow:var(--shadow);
  border:1px solid rgba(15,23,42,0.04);
  backdrop-filter: blur(6px) saturate(120%);
  position:relative;
 /* needed for absolute back link */
}

/* back link placed top-left of the card container */
.back-link {
  position:absolute;
  left:16px;
  top:16px;
  display:inline-flex;
  align-items:center;
  gap:8px;
  font-size:13px;
  color:var(--muted);
  text-decoration:none;
  background: rgba(255,255,255,0.6);
  padding:6px 10px;
  border-radius:8px;
  border:1px solid rgba(15,23,42,0.04);
  box-shadow: 0 6px 18px rgba(16,24,40,0.04);
}
.back-link:hover { transform:translateY(-1px); }

/* On small screens make the back-link part of the flow and reduce card top padding */
@media (max-width:680px){
  .card { padding-top:20px; }
  .back-link { position:static; display:inline-flex; margin-bottom:8px; box-shadow:none; background:transparent; border:0; padding:0; color:var(--muted); }
}

/* header row */
.header {
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
  margin-bottom:12px;
}
.title { margin:0; font-size:20px; font-weight:700; color:var(--primary-1); }
.sub { color:var(--muted); font-size:13px; margin-top:6px; }

/* meta pills */
.pills { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.pill {
  background:#fbfbff;
  padding:8px 12px;
  border-radius:999px;
  border:1px solid var(--border);
  font-weight:700;
  color:var(--primary-1);
  font-size:13px;
}

/* two-column layout */
.grid { display:grid; grid-template-columns:1fr 340px; gap:18px; }
@media(max-width:880px){ .grid{grid-template-columns:1fr} }

/* sections */
.section {
  background:linear-gradient(180deg,#fff,#fbfbff);
  border-radius:12px;
  padding:14px;
  border:1px solid rgba(15,23,42,0.03);
}
.section h3 { margin:0 0 8px 0; font-size:16px }

/* driver block */
.driver { display:flex; gap:12px; align-items:flex-start; }
.avatar {
  width:56px;height:56px;border-radius:12px;
  background:linear-gradient(135deg,var(--primary-2),var(--primary-1));
  color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px;
}
.driver .info { font-size:14px; color:var(--text); }
.driver .info .muted { color:var(--muted); font-size:13px; margin-top:6px; }

/* summary table */
.summary { display:flex; gap:10px; margin-top:8px; flex-wrap:wrap; }
.summary .card-small {
  background:#fff;padding:10px;border-radius:10px;border:1px solid var(--border);
  min-width:120px;text-align:center;
}
.card-small .num {font-weight:800;font-size:18px}
.card-small .label {color:var(--muted);font-size:13px;margin-top:6px}

/* passengers table */
.table-wrap { overflow:auto; margin-top:8px; }
table { width:100%; border-collapse:collapse; margin-top:8px; }
th, td { text-align:left; padding:10px 8px; border-bottom:1px solid #f1f5f9; font-size:14px; vertical-align:middle; }
th { font-weight:700; background:rgba(139,92,246,0.03); color:var(--text); }
.badge { display:inline-block; padding:6px 10px; border-radius:999px; font-weight:700; font-size:13px; }
.badge-available{ background:#eef2ff; color:var(--primary-1); }
.badge-pending{ background:#fff7ed; color:#92400e; }
.badge-confirm{ background:#ecfdf5; color:#065f46; }

/* footer */
.footer { margin-top:14px; display:flex; justify-content:space-between; align-items:center; gap:12px; }
.link-btn { color:var(--muted); text-decoration:none; font-weight:600; }
/* simple action button */
.btn {
  background:linear-gradient(90deg,var(--primary-2),var(--primary-1));
  color:#fff;
  padding:10px 14px;
  border-radius:10px;
  border:0;
  font-weight:700;
  cursor:pointer;
  box-shadow:0 8px 24px rgba(139,92,246,0.08);
}
.small { font-size:13px; color:var(--muted); }
</style>
</head>
<body>
  <?php include 'header.php' ?>
  <div class="container">
    <div class="card" role="main" aria-labelledby="ride-title">

      <!-- BACK LINK placed top-left of the card container -->
      <a class="back-link" href="manage_rides.php" aria-label="Back To Manage Bookings">← Back To Manage Bookings</a>

      <div class="header">
        <div>
          <h1 id="ride-title" class="title"><?php echo htmlspecialchars($ride['from_location'] . ' → ' . $ride['to_location']); ?></h1>
          <div class="sub">Ride details — <?php echo htmlspecialchars($ride['ride_date']); ?> &middot; <?php echo htmlspecialchars($ride['ride_time'] ?: '—'); ?></div>
        </div>
        <div class="pills" aria-hidden="true">
          <div class="pill">Price: ₹<?php echo htmlspecialchars($ride['price']); ?></div>
          <div class="pill">Seats: <?php echo (int)$ride['seats']; ?></div>
          <div class="pill">Available: <?php echo (int)$ride['available_seats']; ?></div>
        </div>
      </div>

      <div class="grid">
        <!-- left column: driver, bookings, passengers -->
        <div style="display:flex;flex-direction:column;gap:14px">
          <div class="section" aria-labelledby="driver-heading">
            <h3 id="driver-heading">Driver</h3>
            <div class="driver">
              <div class="avatar" aria-hidden="true"><?php echo htmlspecialchars(substr($ride['driver_name'],0,1)); ?></div>
              <div class="info">
                <div style="font-weight:800"><?php echo htmlspecialchars($ride['driver_name']); ?></div>
                <div class="muted"><?php echo htmlspecialchars($ride['driver_phone']); ?> &middot; <?php echo htmlspecialchars($ride['driver_email']); ?></div>
                <?php if (!empty($ride['driver_bio'])): ?>
                  <div class="muted" style="margin-top:8px"><?php echo nl2br(htmlspecialchars($ride['driver_bio'])); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="section" aria-labelledby="summary-heading">
            <h3 id="summary-heading">Bookings summary</h3>
            <div class="summary" role="list">
              <div class="card-small" role="listitem">
                <div class="num"><?php echo (int)$counts['pending']; ?></div>
                <div class="label">Pending seats</div>
              </div>
              <div class="card-small" role="listitem">
                <div class="num"><?php echo (int)$counts['confirmed']; ?></div>
                <div class="label">Confirmed seats</div>
              </div>
              <div class="card-small" role="listitem">
                <div class="num"><?php echo (int)$counts['cancelled']; ?></div>
                <div class="label">Cancelled seats</div>
              </div>
              <div class="card-small" role="listitem">
                <div class="num"><?php echo (int)$counts['total']; ?></div>
                <div class="label">Total requested</div>
              </div>
            </div>
          </div>

          <div class="section" aria-labelledby="passengers-heading">
            <h3 id="passengers-heading">Passengers (<?php echo count($passengers); ?>)</h3>

            <?php if (empty($passengers)): ?>
              <div class="small">No passengers have requested/booked this ride yet.</div>
            <?php else: ?>
              <div class="table-wrap" role="region" aria-label="Passengers list">
                <table>
                  <thead>
                    <tr>
                      <th style="width:44px">ID</th>
                      <th>Passenger</th>
                      <th>Contact</th>
                      <th style="width:80px">Seats</th>
                      <th style="width:130px">Status</th>
                      <th style="width:160px">Requested</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($passengers as $i => $p): ?>
                      <tr>
                        <td><?php echo $i+1; ?></td>
                        <td><?php echo htmlspecialchars($p['passenger_name']); ?></td>
                        <td class="small"><?php echo htmlspecialchars($p['passenger_phone']); ?> <br><span class="small"> | <?php echo htmlspecialchars($p['passenger_email']); ?></span></td>
                        <td><?php echo (int)$p['seats']; ?></td>
                        <td>
                          <?php if ($p['status'] === 'pending'): ?>
                            <span class="badge badge-pending">Pending</span>
                          <?php elseif ($p['status'] === 'confirmed'): ?>
                            <span class="badge badge-confirm">Confirmed</span>
                          <?php else: ?>
                            <span class="small">Cancelled</span>
                          <?php endif; ?>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($p['created_at']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- right column: quick info & actions -->
        <aside style="display:flex;flex-direction:column;gap:14px">
          <div class="section" aria-labelledby="quick-heading">
            <h3 id="quick-heading">Quick info</h3>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px" class="small">
              <div><strong>Date:</strong> <?php echo htmlspecialchars($ride['ride_date']); ?></div>
              <div><strong>Time:</strong> <?php echo htmlspecialchars($ride['ride_time'] ?: '—'); ?></div>
              <div><strong>Price:</strong> ₹<?php echo htmlspecialchars($ride['price']); ?></div>
              <div><strong>Total seats:</strong> <?php echo (int)$ride['seats']; ?></div>
              <div><strong>Available:</strong> <?php echo (int)$ride['available_seats']; ?></div>
            </div>
          </div>

          <div class="section" aria-labelledby="actions-heading">
            <h3 id="actions-heading">Actions</h3>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
              <a class="link-btn small" href="search_results.php?from=<?php echo urlencode($ride['from_location']); ?>&to=<?php echo urlencode($ride['to_location']); ?>">Find similar rides</a>
              <a class="link-btn small" href="search_results.php">Back to search</a>
              <div style="height:6px"></div>
              <?php if (!empty($_SESSION['user']) && ($_SESSION['user']['id'] == $ride['driver_id'])): ?>
                <form method="POST" action="delete_ride.php" onsubmit="return confirm('Delete this ride? This will remove all bookings.')">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                  <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
                  <button class="btn" type="submit">Delete ride</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </div>
   <?php include 'footer.php'; ?>
</body>
</html>
