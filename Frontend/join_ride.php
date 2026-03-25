<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$uid  = (int)($_SESSION['user']['id'] ?? 0);
$name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$csrf = csrf_token();

$flash_html = '';
if (!empty($_SESSION['msg'])) {
    $m = $_SESSION['msg'];
    $bg = $m['type'] === 'success' ? '#ecfdf5' : ($m['type'] === 'error' ? '#fff1f2' : '#fefce8');
    $color = $m['type'] === 'success' ? '#065f46' : ($m['type'] === 'error' ? '#9b1c1c' : '#78350f');
    $flash_html = "<div style='background:$bg;padding:12px 14px;border-radius:10px;color:$color;margin-bottom:14px;font-weight:500;border:1px solid rgba(0,0,0,0.05)'>".htmlspecialchars($m['text'])."</div>";
    unset($_SESSION['msg']);
}

$ride = null;
$error = '';

// helper: try to extract a token or id from an arbitrary string (URL or token/id)
function extract_token_or_id(string $input) {
    $input = trim($input);
    if ($input === '') return null;

    // If numeric only -> treat as ID
    if (ctype_digit($input)) return ['type'=>'id','value'=> (int)$input];

    // Try parse as URL
    if (preg_match('#^https?://#i', $input)) {
        $p = parse_url($input);
        if ($p !== false) {
            // parse query params
            if (!empty($p['query'])) {
                parse_str($p['query'], $q);
                // common param names: share, token, t, id
                if (!empty($q['share'])) return ['type'=>'token','value'=>preg_replace('/[^0-9A-Za-z_\-]/','', $q['share'])];
                if (!empty($q['token'])) return ['type'=>'token','value'=>preg_replace('/[^0-9A-Za-z_\-]/','', $q['token'])];
                if (!empty($q['t'])) return ['type'=>'token','value'=>preg_replace('/[^0-9A-Za-z_\-]/','', $q['t'])];
                if (!empty($q['id']) && ctype_digit($q['id'])) return ['type'=>'id','value'=> (int)$q['id']];
            }
            // fallback: maybe the path ends with token
            if (!empty($p['path'])) {
                $parts = array_values(array_filter(explode('/', $p['path'])));
                $last = end($parts);
                if ($last !== false && $last !== '') {
                    // if looks numeric id
                    if (ctype_digit($last)) return ['type'=>'id','value'=> (int)$last];
                    // else treat last segment as token (clean)
                    $tok = preg_replace('/[^0-9A-Za-z_\-]/','', $last);
                    if ($tok !== '') return ['type'=>'token','value'=>$tok];
                }
            }
        }
    }

    // otherwise treat as token string (clean)
    $token = preg_replace('/[^0-9A-Za-z_\-]/','', $input);
    if ($token === '') return null;
    return ['type'=>'token','value'=>$token];
}

// Handle POST: open_link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['open_link'])) {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $shared = trim($_POST['shared_link'] ?? '');
        $found = extract_token_or_id($shared);
        if (!$found) {
            $error = 'Please enter a valid ride link, token or id.';
        } else {
            if ($found['type'] === 'id') {
                // load by id
                $stmt = $mysqli->prepare("
                    SELECT r.*, u.name AS driver_name, u.phone AS driver_phone, u.email AS driver_email
                    FROM rides r
                    JOIN users u ON r.driver_id = u.id
                    WHERE r.id = ? LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param('i', $found['value']);
                    $stmt->execute();
                    $ride = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (! $ride) $error = 'No ride found for given id.';
                } else {
                    $error = 'DB error: ' . $mysqli->error;
                }
            } else { // token
                $token = $found['value'];
                // try rides.share_token first
                $stmt = $mysqli->prepare("
                    SELECT r.*, u.name AS driver_name, u.phone AS driver_phone, u.email AS driver_email
                    FROM rides r
                    JOIN users u ON r.driver_id = u.id
                    WHERE r.share_token = ? LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param('s', $token);
                    $stmt->execute();
                    $ride = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                } else {
                    $ride = null;
                }

                // if not found in rides, try ride_shares (if your schema has it)
                if (!$ride) {
                    $stmt = $mysqli->prepare("
                        SELECT r.*, u.name AS driver_name, u.phone AS driver_phone, u.email AS driver_email
                        FROM ride_shares rs
                        JOIN rides r ON rs.ride_id = r.id
                        JOIN users u ON r.driver_id = u.id
                        WHERE rs.token = ? LIMIT 1
                    ");
                    if ($stmt) {
                        $stmt->bind_param('s', $token);
                        $stmt->execute();
                        $ride = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                    }
                }

                if (! $ride) $error = 'No ride found for given token/link.';
            }
        }
    }
}

// Helper: nicer escape
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Join Ride by Link — HerSafar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
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
.nav a{color:#fff;text-decoration:none;padding:10px 12px;border-radius:10px;font-weight:600;border:1px solid rgba(255,255,255,0.12);transition:all .18s var(--ease);font-size:13px;}
.nav a:hover{transform:translateY(-2px);background:rgba(255,255,255,0.08)}
.nav a.active{background:rgba(255,255,255,0.12);box-shadow:0 6px 18px rgba(0,0,0,0.05)}

.spacer{flex:1}
.bottom-links{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.bottom-links a{display:block;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.95);color:#a855f7;text-decoration:none;font-weight:700;text-align:center;border:1px solid rgba(255,255,255,0.3)}
.bottom-links a:hover{background:#f3e8ff;color:#6b21b6}

.sidebar .logged-user{margin-top:16px;font-size:13px;color:#fff;opacity:0.95;text-align:center}

/* Main */
.main{flex:1;display:flex;flex-direction:column;gap:20px}
.container{background:linear-gradient(180deg,rgba(255,255,255,0.65),rgba(255,255,255,0.5));border:1px solid var(--glass-border);border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);backdrop-filter:blur(10px) saturate(120%);}

/* header */
.header-row{margin-bottom:18px}
.h-title{font-size:22px;margin-bottom:4px;color:var(--primary-1)}
.h-sub{font-size:14px;color:var(--muted)}

/* section */
.section{margin-top:12px;background:#fff;border-radius:12px;box-shadow:0 4px 18px rgba(0,0,0,0.03);padding:22px}
.section-title{font-size:18px;color:var(--primary-2);border-bottom:1px solid rgba(99,102,241,0.1);padding-bottom:8px;margin-bottom:12px}
.input{width:100%;padding:10px;border:1px solid #e6e9ef;border-radius:8px;font-size:14px}
.btn{padding:10px 14px;border-radius:8px;border:0;cursor:pointer;font-weight:700;font-size:13px}
.btn.primary{background:linear-gradient(90deg,var(--primary-2),var(--primary-1));color:#fff;box-shadow:0 8px 24px rgba(124,58,237,0.08)}
.error{background:#fff1f2;color:#7f1d1d;padding:10px;border-radius:8px;border-left:4px solid #ef4444;margin-top:10px}
.ride-details{margin-top:10px;padding:14px;border-radius:10px;background:#fff;border:1px solid rgba(15,23,42,0.04)}
.meta{color:var(--muted);font-size:14px;line-height:1.6}

/* improved ride details (copied/adapted from suggested block) */
.ride-panel {
  display:flex; flex-direction:column; gap:16px; background:linear-gradient(180deg,#ffffff,#fbfbff);
  border-radius:12px; padding:18px; border:1px solid rgba(15,23,42,0.04); box-shadow:0 10px 30px rgba(16,24,40,0.04);
}
.ride-top { display:flex; gap:16px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
.route-block { display:flex; flex-direction:column; gap:6px; min-width:0; }
.route-title { font-size:18px; font-weight:800; color:#241235; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.route-sub { color:#6b7280; font-size:13px; }
.badges { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.badge { font-weight:700; font-size:13px; padding:6px 10px; border-radius:999px; background:#f3f4f6; color:#111827; }
.badge.date { background:#eef2ff; color:#1e3a8a; }
.badge.time { background:#ecfdf5; color:#065f46; }
.badge.price { background:linear-gradient(90deg,#fef3c7,#fee2b3); color:#92400e; }

.driver-panel { display:flex; gap:12px; align-items:center; background:#fff; border-radius:10px; padding:12px; border:1px solid rgba(15,23,42,0.03); }
.avatar { width:64px; height:64px; border-radius:12px; display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff; background:linear-gradient(135deg,#8b5cf6,#5b21b6); font-size:20px; }
.driver-info { display:flex; flex-direction:column; gap:4px; min-width:0; }
.driver-name { font-weight:800; color:#241235; font-size:15px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.driver-contact { color:#6b7280; font-size:13px; }

.seat-strip { display:flex; flex-direction:column; gap:8px; margin-top:6px; }
.seat-info { display:flex; justify-content:space-between; align-items:center; gap:8px; font-weight:700; font-size:13px; }
.progress { height:10px; background:#f1f5f9; border-radius:999px; overflow:hidden; border:1px solid rgba(15,23,42,0.03); }
.progress > .fill { height:100%; width:0%; transition:width .35s ease; background:linear-gradient(90deg,#8b5cf6,#5b21b6); }

.ride-actions {
  display: flex;
  gap: 6px;
  align-items: center;
  justify-content: flex-start;
  flex-wrap: wrap;
  margin-top: 8px;
}

.btn-action,
.btn-secondary {
  font-size: 12px;
  font-weight: 600;
  padding: 7px 10px;
  border-radius: 8px;
  text-decoration: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease-in-out;
  white-space: nowrap;
}

.btn-action {
  background: linear-gradient(90deg, #8b5cf6, #5b21b6);
  color: #fff;
  border: none;
  box-shadow: 0 4px 10px rgba(139, 92, 246, 0.15);
}

.btn-secondary {
  background: #fff;
  color: #241235;
  border: 1px solid rgba(15, 23, 42, 0.1);
}

.btn-action:hover,
.btn-secondary:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.05);
}

.copy-small { padding:8px 10px; border-radius:10px; background:#f3f4f6; border:0; font-weight:700; cursor:pointer; }

.note { color:#6b7280; font-size:13px; line-height:1.5; margin-top:6px; }

.ride-meta-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:12px; }
@media(max-width:720px){ .ride-meta-grid{ grid-template-columns:1fr } }

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
      <a href="manage_rides.php">Manage Bookings</a>
      <a href="join_ride.php" class="active">Join A Ride</a>
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
    <div class="container">
      <div class="header-row">
        <h2 class="h-title">Join Ride by Link</h2>
        <div class="h-sub">Paste a ride link, token or id to view ride details below.</div>
      </div>

      <?= $flash_html ?>

      <div class="section">
        <div class="section-title">Open Ride</div>

        <form method="POST" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="text" name="shared_link" class="input" placeholder="Paste shared ride link, token, or id" value="<?= h($_POST['shared_link'] ?? '') ?>">
          <button class="btn primary" type="submit" name="open_link">Open Ride</button>
        </form>

        <?php if ($error): ?>
          <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($ride): 
            // prepare display variables safely
            $from = h($ride['from_location']);
            $to = h($ride['to_location']);
            $date = h($ride['ride_date']);
            $time = h($ride['ride_time'] ?: '—');
            $price = (int)($ride['price'] ?? $ride['price_per_seat'] ?? 0);
            $total = (int)($ride['seats'] ?? 0);
            $available = (int)($ride['available_seats'] ?? 0);
            if ($total <= 0) $total = max(1, $available);
            $percent = (int)(100 * (1 - ($available / max(1,$total))));
            if ($percent < 0) $percent = 0; if ($percent > 100) $percent = 100;
            $driver_name = h($ride['driver_name'] ?? '');
            $driver_phone = h($ride['driver_phone'] ?? '');
            $driver_email = h($ride['driver_email'] ?? '');
            $notes = nl2br(h($ride['notes'] ?? ''));
            $shareUrl = '';
            if (!empty($ride['share_token'])) {
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $shareUrl = rtrim("$proto://$host", '/') . '/view_ride.php?share=' . urlencode($ride['share_token']);
            }
        ?>
          <div style="margin-top:14px" class="ride-details">
            <div class="ride-panel" role="region" aria-label="Ride details">
              <div class="ride-top">
                <div class="route-block">
                  <div class="route-title" title="<?php echo $from . ' → ' . $to; ?>"><?php echo $from; ?> → <?php echo $to; ?></div>
                  <div class="route-sub"><?php echo $notes ?: '<span class="note">No additional notes</span>'; ?></div>
                </div>

                <div class="badges" aria-hidden="true">
                  <div class="badge date">📅 <?php echo $date; ?></div>
                  <div class="badge time">⏰ <?php echo $time; ?></div>
                  <div class="badge price">₹ <?php echo $price; ?></div>
                </div>
              </div>

              <div class="ride-meta-grid">
                <div>
                  <div class="driver-panel" aria-label="Driver">
                    <div class="avatar" aria-hidden="true"><?php echo substr($driver_name,0,1) ?: 'D'; ?></div>
                    <div class="driver-info">
                      <div class="driver-name"><?php echo $driver_name; ?></div>
                      <div class="driver-contact"><?php echo $driver_phone; ?> <?php if($driver_email) echo ' • ' . $driver_email; ?></div>
                    </div>
                  </div>

                  <?php if ($shareUrl): ?>
                    <div style="margin-top:12px; display:flex; gap:8px; align-items:center;">
                      <input type="text" readonly value="<?php echo h($shareUrl); ?>" style="flex:1;padding:10px;border-radius:8px;border:1px solid rgba(15,23,42,0.04);background:#fafafa">
                      <button class="copy-small" data-clip="<?php echo h($shareUrl); ?>">Copy</button>
                    </div>
                  <?php endif; ?>

                  <div class="note" style="margin-top:12px">Tip: click <strong>View &amp; Book Ride</strong> to proceed with booking or contact the driver directly.</div>
                </div>

                <div>
                  <div style="font-weight:800;color:#241235">Availability</div>

                  <div class="seat-strip">
                    <div class="seat-info">
                      <div class="left"><?php echo $available; ?> / <?php echo $total; ?> seats</div>
                      <div class="right"><?php echo $percent; ?>% filled</div>
                    </div>

                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $percent; ?>">
                      <div class="fill" style="width:<?php echo $percent; ?>%;"></div>
                    </div>

                    <div style="display:flex;gap:8px;align-items:center;margin-top:8px">
                      <a class="btn-action" href="ride.php?id=<?php echo (int)$ride['id']; ?>">View &amp; Book Ride</a>
                      <a class="btn-secondary" href="tel:<?php echo urlencode($ride['driver_phone']); ?>">Call Driver</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
    <div class="footer">&copy; <?= date('Y') ?> HerSafar — Safe, Smart, and Connected Travel</div>
  </main>

<script>
// Copy handler for copy-small buttons
document.addEventListener('click', function(e){
  var t = e.target;
  if (t && t.matches('.copy-small')) {
    var url = t.getAttribute('data-clip') || '';
    if (!url) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function(){
        var old = t.innerText;
        t.innerText = 'Copied';
        setTimeout(function(){ t.innerText = old; },1400);
      }, function(){ alert('Copy failed — please copy manually.'); });
    } else {
      alert('Clipboard API not available.');
    }
  }
});
</script>
</body>
</html>
