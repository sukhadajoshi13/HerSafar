<?php
require_once 'dbcon.php';
require_once 'functions.php';

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$date = trim($_GET['date'] ?? '');
$flex_days = (int)($_GET['flex_days'] ?? 0);
$time = trim($_GET['time'] ?? '');
$nearby = isset($_GET['nearby']) ? 1 : 0;
$where = ["r.available_seats > 0", "r.ride_date >= CURDATE()"];
$params = [];
$types = '';

if ($from !== '') { $where[] = "r.from_location LIKE CONCAT('%', ?, '%')"; $params[] = $from; $types .= 's'; }
if ($to !== '')   { $where[] = "r.to_location LIKE CONCAT('%', ?, '%')"; $params[] = $to;   $types .= 's'; }

if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    if ($flex_days > 0) {
        $where[] = "(r.ride_date BETWEEN DATE_SUB(?, INTERVAL ? DAY) AND DATE_ADD(?, INTERVAL ? DAY))";
        $params[] = $date; $types .= 's';
        $params[] = $flex_days; $types .= 'i';
        $params[] = $date; $types .= 's';
        $params[] = $flex_days; $types .= 'i';
    } else {
        $where[] = "(r.ride_date = ?)";
        $params[] = $date; $types .= 's';
    }
}

if ($time !== '' && preg_match('/^\d{2}:\d{2}$/', $time)) {
    $where[] = "(ABS(TIME_TO_SEC(TIMEDIFF(r.ride_time, ?))) <= 3600)";
    $params[] = $time; $types .= 's';
}

$sql = "SELECT r.*, u.name as driver_name, u.phone as driver_phone
        FROM rides r
        JOIN users u ON r.driver_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.ride_date ASC, r.ride_time ASC
        LIMIT 200";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) die('Prepare failed: ' . htmlspecialchars($mysqli->error));

if (!empty($params)) {
    $bind_names = [];
    $bind_names[] = $types;
    foreach ($params as $i => $val) $bind_names[] = & $params[$i];
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

$stmt->execute();
$res = $stmt->get_result();
$rides = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$today = date('Y-m-d');
$now_hm = date('H:i'); 
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Search Rides — Hersafar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#f6f8fb;
      --card:#ffffff;
      --muted:#6b7280;
      --accent:#6b21b6;
      --accent-2:#8b5cf6;
      --success:#10b981;
      --warning:#f59e0b;
      --danger:#ef4444;
      --glass-bg: rgba(255,255,255,0.12);
      --glass-border: rgba(255,255,255,0.2);
      --radius:16px;
      --shadow-lg: 0 20px 50px rgba(16,24,40,0.09);
      --shadow-sm: 0 8px 22px rgba(16,24,40,0.06);
      font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
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
:root { --gutter:22px; }

main.container{
  width: calc(100% - (var(--gutter) * 2));
  max-width: var(--max-width);
  margin: 26px var(--gutter);
  padding: 18px;
  box-sizing: border-box;
  flex: 1 0 auto; /* expand so footer is pushed to bottom */
}

    .container { max-width:1100px; margin:0 auto;  margin: 30px auto 0; }

    .header {
      display:flex; align-items:center; gap:12px; justify-content:space-between; margin-bottom:18px;
    }
    .title { font-size:22px; font-weight:800; color:var(--accent); margin:0; letter-spacing:-0.2px; }
    .subtitle { color:var(--muted); font-size:13px; }

    /* GLASSMORPH search box - hot */
    .search-hot {
      background: linear-gradient(180deg, rgba(255,255,255,0.16), rgba(255,255,255,0.06));
      border-radius:20px;
      padding:14px;
      box-shadow: var(--shadow-lg);
      border:1px solid rgba(255,255,255,0.14);
      display:flex;
      gap:12px;
      align-items:center;
      flex-wrap:wrap;
      margin-bottom:18px;
      backdrop-filter: blur(12px) saturate(130%);
      -webkit-backdrop-filter: blur(12px) saturate(130%);
      transition: transform .12s ease;
    }

    .search-hot:focus-within { transform: translateY(-4px); box-shadow: 0 28px 60px rgba(99,102,241,0.12); }

    .row {
      display:flex;
      gap:8px;
      align-items:center;
    }

    .input-pill {
      display:flex;
      align-items:center;
      gap:10px;
      background: linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
      border-radius:12px;
      padding:10px 12px;
      min-width:180px;
      border:1px solid rgba(15,23,42,0.04);
      box-shadow: var(--shadow-sm);
      color: #061130;
      transition: box-shadow .12s ease, transform .12s ease;
    }

    .input-pill:hover { box-shadow: 0 10px 22px rgba(16,24,40,0.06); transform: translateY(-2px); }
    .input-pill:focus-within { box-shadow: 0 14px 30px rgba(99,102,241,0.10); border-color: rgba(139,92,246,0.22); }

    .input-pill svg { opacity:0.95; width:18px; height:18px; flex:0 0 18px; stroke:currentColor; fill:none; stroke-width:1.5; color:var(--accent); }
    .input-pill input[type=text],
    .input-pill input[type=date],
    .input-pill input[type=time],
    .input-pill select {
      border:0;
      outline:none;
      background:transparent;
      font-size:14px;
      color:#061130;
      min-width:120px;
    }

    .grow { flex:1; min-width:220px; }

    .nearby {
      display:flex; gap:8px; align-items:center; background:transparent; padding:6px 8px; border-radius:10px;
      color:var(--muted); font-weight:700; font-size:13px;
    }

    /* NOTE: buttons centered below inputs and smaller */
    .hot-actions {
      width:100%;
      display:flex;
      justify-content:center;
      gap:10px;
      align-items:center;
      margin-top:12px;
      order:99; /* push to bottom of the search-hot */
    }

    .btn-hot {
      background: linear-gradient(90deg,var(--accent-2),var(--accent));
      color: #fff;
      border:0;
      padding:9px 12px; /* smaller */
      border-radius:10px;
      font-weight:700;      /* slightly lighter than previous */
      font-size:13px;
      cursor:pointer;
      box-shadow: 0 10px 28px rgba(99,102,241,0.10);
      transition: transform .12s ease, box-shadow .12s ease;
      min-width:110px;
    }
    .btn-hot:hover { transform: translateY(-2px); box-shadow: 0 18px 36px rgba(99,102,241,0.14); }

   /* ensure action area is a centered flex container */
.hot-actions {
  width: 100%;
  display: flex;
  justify-content: center; /* center all children horizontally */
  gap: 10px;
  align-items: center;
  margin-top: 12px;
  order: 99; /* push to bottom of the search-hot */
}

/* Center both Search and Clear buttons */
.hot-actions {
  width: 100%;
  display: flex;
  justify-content: center;   /* centers both buttons horizontally */
  align-items: center;
  gap: 12px;                  /* spacing between them */
  margin-top: 16px;
  order: 99;                  /* keep it below inputs */
}

/* Search button (smaller, modern) */
.btn-hot {
  background: linear-gradient(90deg, var(--accent-2), var(--accent));
  color: #fff;
  border: 0;
  padding: 9px 14px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(99,102,241,0.10);
  transition: transform .12s ease, box-shadow .12s ease;
  min-width: 110px;
  text-align: center;
}
.btn-hot:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 28px rgba(99,102,241,0.15);
}

/* Clear button centered next to search */
.btn-clear {
  background: transparent;
  color: var(--muted);
  border: 1px solid rgba(15,23,42,0.08);
  padding: 8px 12px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  min-width: 90px;
  text-align: center;
  transition: background .12s ease, color .12s ease;
}
.btn-clear:hover {
  background: rgba(107,33,182,0.06);
  color: var(--accent);
}

/* Optional: responsive - stack vertically on small screens */
@media (max-width: 520px) {
  .hot-actions {
    flex-direction: column;
    gap: 8px;
  }
  .btn-hot, .btn-clear {
    width: 100%;
    max-width: 260px;
  }
}


    @media (max-width:880px){
      .search-hot { padding:14px; }
      .input-pill { min-width: 140px; width:100%; }
      .row { width:100%; flex-wrap:wrap; }
      .hot-actions { margin-top:12px; }
      .btn-hot { width:45%; }
      .btn-clear { width:45%; }
    }

    /* grid cards (unchanged) */
    .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:14px; }
    @media (max-width:520px) { .grid { grid-template-columns: repeat(1, 1fr); } }

    .mini-card {
      background:var(--card); border-radius:12px; padding:12px; box-shadow: var(--shadow-sm);
      border:1px solid rgba(15,23,42,0.03); display:flex; flex-direction:column; gap:10px;
      transition: transform .12s ease, box-shadow .12s ease;
    }
    .mini-card:hover { transform: translateY(-6px); box-shadow: 0 18px 36px rgba(16,24,40,0.08); }

    .route { font-weight:700; color:#0f172a; font-size:15px; }
    .datetime { color:var(--muted); font-size:13px; margin-top:4px; }
    .driver { color:var(--muted); font-size:13px; margin-top:6px; display:flex; justify-content:space-between; align-items:center; gap:8px; }

    .meta-row { display:flex; justify-content:space-between; align-items:center; gap:8px; }
    .price { font-weight:800; color:#111827; font-size:16px; }
    .badge { font-weight:700; padding:6px 8px; border-radius:999px; font-size:12px; background:#f3f4f6; color:#111827; }

    .footer-actions { display:flex; gap:8px; align-items:center; justify-content:space-between; margin-top:6px; }
    .book-form { display:flex; gap:8px; align-items:center; }
    input.seats { width:64px; padding:8px; border-radius:8px; border:1px solid rgba(15,23,42,0.06); text-align:center; font-weight:700; }

    .details-link { color:var(--accent); font-weight:700; text-decoration:none; font-size:13px; }
    .details-link:hover { text-decoration:underline; }

    .empty { text-align:center; padding:36px; border-radius:12px; background:linear-gradient(180deg,#fff,#fbfbff); border:1px dashed rgba(15,23,42,0.04); color:var(--muted); }

    .seat-strip { display:flex; flex-direction:column; gap:6px; margin-top:6px; }
    .seat-info { display:flex; justify-content:space-between; align-items:center; gap:8px; font-weight:700; font-size:13px; }
    .seat-info .left { color:#111827; }
    .seat-info .right { color:var(--muted); font-weight:600; font-size:12px; }

    .progress {
      height:8px;
      background:#f1f5f9;
      border-radius:999px;
      overflow:hidden;
      border:1px solid rgba(15,23,42,0.03);
    }
    .progress > .fill {
      height:100%;
      background: linear-gradient(90deg,var(--accent-2),var(--accent));
      width:0%;
      transition: width .35s ease;
    }
    .low { color:var(--warning); font-weight:800; }
    .full { color:var(--danger); font-weight:800; }

    .small { font-size:13px; color:var(--muted); }
    .error { color: var(--danger); font-weight:700; font-size:13px; margin-left:8px; }
    .ok { color: var(--success); font-weight:700; font-size:13px; margin-left:8px; }
  </style>
  <?php include 'header.php' ?>
</head>
<body>
  <div class="container">
    <div class="header">
      <div>
        <h1 class="title">Find rides</h1>
        <div class="subtitle">Compact results — quick to scan and book</div>
      </div>
      <div class="small">Showing <?php echo count($rides); ?> results</div>
    </div>

    <form id="searchForm" method="GET" action="search_results.php" aria-label="Search rides">
      <div class="search-hot" role="search" aria-label="Search rides hotbox">

        <!-- From & To -->
        <div class="row grow" style="min-width:220px;">
          <label class="input-pill" title="From">
            <svg viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" role="img">
              <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 6.4 11.16 6.68 11.41.2.17.45.26.72.26.27 0 .52-.09.72-.26C12.6 20.16 19 14.25 19 9c0-3.87-3.13-7-7-7z" />
              <circle cx="12" cy="9" r="2" fill="currentColor" />
            </svg>
            <input id="from" name="from" type="text" placeholder="From (city or area)" value="<?php echo htmlspecialchars($from); ?>" aria-label="From">
          </label>

          <label class="input-pill" title="To" style="margin-left:8px;">
            <svg viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" role="img">
              <path d="M2 12h16" />
              <path d="M12 4l8 8-8 8" />
            </svg>
            <input id="to" name="to" type="text" placeholder="To (city or area)" value="<?php echo htmlspecialchars($to); ?>" aria-label="To">
          </label>
        </div>

        <!-- Date / Time / Flex / Nearby -->
        <div class="row" style="flex:0 0 auto;">
          <label class="input-pill" title="Date">
            <svg viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" role="img">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <path d="M16 2v4M8 2v4" />
            </svg>
            <!-- server-side min prevents past date selection -->
            <input id="date" name="date" type="date" value="<?php echo htmlspecialchars($date); ?>" min="<?php echo $today; ?>" aria-label="Date">
          </label>

          <label class="input-pill" title="Time" style="margin-left:8px;">
            <svg viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" role="img">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v6l4 2" />
            </svg>
            <!-- time min will be adjusted by JS when date==today -->
            <input id="time" name="time" type="time" value="<?php echo htmlspecialchars($time); ?>" aria-label="Time (optional)">
          </label>

          <label class="input-pill compact" style="margin-left:8px;" title="Flexible days">
            <select id="flex_days" name="flex_days" aria-label="Flexible days">
              <option value="0"<?php if($flex_days==0) echo ' selected'; ?>>Exact</option>
              <option value="1"<?php if($flex_days==1) echo ' selected'; ?>>±1 day</option>
              <option value="2"<?php if($flex_days==2) echo ' selected'; ?>>±2 days</option>
              <option value="3"<?php if($flex_days==3) echo ' selected'; ?>>±3 days</option>
            </select>
          </label>

          <label class="nearby" style="margin-left:8px;">
            <input id="nearby" name="nearby" type="checkbox" value="1" <?php if($nearby) echo 'checked'; ?> aria-label="Nearby"> Nearby
          </label>
        </div>

        <!-- ACTIONS now centered below and slightly smaller -->
        <div class="hot-actions">
          <button class="btn-hot" type="submit" aria-label="Search rides">Search</button>
          <a class="btn-clear" href="search_results.php" role="button" aria-label="Clear filters">Clear</a>
        </div>
      </div>
    </form>

    <div style="height:8px"></div>

    <?php if (empty($rides)): ?>
      <div class="empty">No rides found — try widening filters.</div>
    <?php else: ?>
      <div class="grid" role="list">
        <?php foreach($rides as $r):
          // compute seats display
          $totalSeats = (int)($r['seats'] ?? 0);
          if ($totalSeats <= 0) $totalSeats = (int)$r['available_seats'];
          $available = (int)($r['available_seats'] ?? 0);
          $percentFilled = 0;
          if ($totalSeats > 0) {
            $percentFilled = (int) (100 * (1 - ($available / $totalSeats)));
            if ($percentFilled < 0) $percentFilled = 0;
            if ($percentFilled > 100) $percentFilled = 100;
          }
          $lowThreshold = 2;
          $isLow = ($available <= $lowThreshold);
        ?>
          <article class="mini-card" role="listitem" aria-label="<?php echo htmlspecialchars($r['from_location'] . ' to ' . $r['to_location']); ?>">
            <div>
              <div class="route"><?php echo htmlspecialchars($r['from_location']); ?> → <?php echo htmlspecialchars($r['to_location']); ?></div>
              <div class="datetime small"><?php echo htmlspecialchars($r['ride_date']); ?> • <?php echo htmlspecialchars($r['ride_time']); ?></div>
            </div>

            <div class="driver">
              <div class="small">Driver: <?php echo htmlspecialchars($r['driver_name']); ?></div>
            </div>

            <?php if (!empty($r['notes'])): ?>
              <div class="small" style="margin-top:6px; color:#374151;"><?php echo nl2br(htmlspecialchars($r['notes'])); ?></div>
            <?php endif; ?>

            <div class="seat-strip" aria-hidden="true">
              <div class="seat-info">
                <div class="left"><?php echo ($isLow ? '<span class="low">Low seats</span> ' : '') . htmlspecialchars($available) . ' / ' . htmlspecialchars($totalSeats); ?> seats</div>
                <div class="right"><?php echo $percentFilled; ?>% filled</div>
              </div>
              <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $percentFilled; ?>">
                <div class="fill" style="width:<?php echo $percentFilled; ?>%; background: <?php echo ($percentFilled >= 90 ? 'linear-gradient(90deg,var(--danger),#ff7a7a)' : ($isLow ? 'linear-gradient(90deg,var(--warning),#ffcf6b)' : 'linear-gradient(90deg,var(--accent-2),var(--accent))')); ?>;"></div>
              </div>
            </div>

            <div class="meta-row" style="margin-top:6px">
              <div class="price">₹<?php echo htmlspecialchars($r['price']); ?></div>
              <div style="display:flex;gap:8px;align-items:center">
                <a class="details-link" href="ride_details.php?id=<?php echo (int)$r['id']; ?>">Details</a>
              </div>
            </div>

            <div class="footer-actions">
              <form method="POST" action="book_ride.php" class="book-form" aria-label="Request booking">
                <input type="hidden" name="ride_id" value="<?php echo (int)$r['id']; ?>">
                <input class="seats" name="seats" type="number" min="1" max="<?php echo (int)$r['available_seats']; ?>" value="1" aria-label="Seats">
                <button class="btn-hot" type="submit" style="padding:8px 10px; font-size:13px;">Request</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="height:28px"></div>
  </div>

<script>
(function(){
  const today = '<?php echo $today; ?>';
  const fallbackNow = '<?php echo $now_hm; ?>';
  const dateEl = document.getElementById('date');
  const timeEl = document.getElementById('time');
  const form = document.getElementById('searchForm');

  function addMinutesToNow(mins) {
    const d = new Date();
    d.setMinutes(d.getMinutes() + mins);
    const hh = String(d.getHours()).padStart(2,'0');
    const mm = String(d.getMinutes()).padStart(2,'0');
    return hh + ':' + mm;
  }

  if (dateEl) dateEl.setAttribute('min', today);

  function setTimeMinForDate() {
    if (!dateEl || !timeEl) return;
    const picked = dateEl.value;
    if (!picked) {
      timeEl.removeAttribute('min');
      return;
    }
    const now = new Date();
    const todayStr = today;
    if (picked === todayStr) {
      const minTime = addMinutesToNow(15);
      timeEl.setAttribute('min', minTime);
    } else {
      timeEl.removeAttribute('min');
    }
  }
  setTimeMinForDate();
  if (dateEl) dateEl.addEventListener('change', setTimeMinForDate);

  form.addEventListener('submit', function(e){
    const d = dateEl ? dateEl.value : '';
    const t = timeEl ? timeEl.value : '';
    if (!d) return;
    if (d < today) {
      e.preventDefault();
      alert('Please choose today or a future date.');
      dateEl.focus();
      return;
    }
    if (d === today && t) {
      const minAttr = timeEl.getAttribute('min');
      const nowMin = minAttr || fallbackNow;
      if (t < nowMin) {
        e.preventDefault();
        alert('Selected time is too soon. Please choose a time at or after ' + nowMin + ' (or leave time empty).');
        timeEl.focus();
        return;
      }
    }
  });

})();
</script>
</body>
<?php include 'footer.php' ?>
</html>
