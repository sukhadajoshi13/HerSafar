<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

// verify role/verified
$stmt = $mysqli->prepare("SELECT id,name,role,verified FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow || ($userRow['role'] !== 'driver' && $userRow['role'] !== 'admin')) {
    set_flash('error','You must be an approved driver to post rides.');
    header('Location: apply_driver.php'); exit;
}
if ((int)$userRow['verified'] !== 1 && $userRow['role'] !== 'admin') {
    set_flash('error','Your driver profile is not yet verified by an admin.');
    header('Location: dashboard.php'); exit;
}

// load groups the driver belongs to
$groups = [];
$gstmt = $mysqli->prepare("
  SELECT g.id,g.name
  FROM groups g
  JOIN group_members gm ON gm.group_id = g.id
  WHERE gm.user_id = ?
  ORDER BY g.name
");
if ($gstmt) {
    $gstmt->bind_param('i',$uid);
    $gstmt->execute();
    $groups = $gstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $gstmt->close();
}

$errors = [];
$csrf = csrf_token();
$generated_links = [];
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// helper: validate date/time not past
function future_check($date, $time=null){
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return 'Invalid date format.';
    $today_mid = strtotime('today midnight');
    $date_mid = strtotime($date . ' 00:00:00');
    if ($date_mid === false) return 'Invalid date.';
    if ($date_mid < $today_mid) return 'Date cannot be in the past.';
    if ($date_mid == $today_mid && $time) {
        $ts = strtotime($date . ' ' . $time . ':00');
        if ($ts === false) return 'Invalid time.';
        if ($ts < time()) return 'Time cannot be in the past.';
    }
    return true;
}

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $from = trim($_POST['from'] ?? '');
        $to   = trim($_POST['to'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');
        $seats = max(1, (int)($_POST['seats'] ?? 1));
        $price = (float)($_POST['price'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $visibility = $_POST['visibility'] ?? 'private';
        $selected_groups = $_POST['groups'] ?? [];

        if ($from === '' || $to === '' || $date === '') $errors[] = 'Please fill From, To and Date.';
        $v = future_check($date, $time === '' ? null : $time);
        if ($v !== true) $errors[] = $v;

        // groups validation if chosen
        if ($visibility === 'groups' && !empty($selected_groups)) {
            $sel = array_map('intval', $selected_groups);
            $placeholders = implode(',', array_fill(0, count($sel), '?'));
            $types = 'i' . str_repeat('i', count($sel));
            $params = array_merge([$uid], $sel);
            $sql = "SELECT COUNT(*) AS cnt FROM group_members WHERE user_id = ? AND group_id IN ($placeholders)";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                // dynamic bind
                $refs = [];
                $refs[] = & $types;
                foreach ($params as $k => $v) $refs[] = & $params[$k];
                call_user_func_array([$stmt,'bind_param'],$refs);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ((int)$row['cnt'] !== count($sel)) $errors[] = 'Selected groups invalid.';
            } else {
                $errors[] = 'Group validation failed.';
            }
        }

        if (empty($errors)) {
            $mysqli->begin_transaction();
            try {
                $share_token = null;
                if ($visibility === 'public') $share_token = bin2hex(random_bytes(16));

                $ins = $mysqli->prepare("INSERT INTO rides (driver_id, `from_location`, `to_location`, ride_date, ride_time, seats, available_seats, price, notes, share_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                if (!$ins) throw new Exception('DB prepare failed.');
                $available_seats = $seats;
                $ride_time_param = ($time === '' ? null : $time);
                $types = 'issssiidss';
                $ins->bind_param($types, $uid, $from, $to, $date, $ride_time_param, $seats, $available_seats, $price, $notes, $share_token);
                if (!$ins->execute()) throw new Exception('DB execute failed: ' . $ins->error);
                $ride_id = $ins->insert_id;
                $ins->close();

                if ($share_token) {
                    $generated_links[] = ['label'=>'Public link','token'=>$share_token,'url'=>rtrim($baseUrl,'/').'/ride.php?token='.urlencode($share_token)];
                }

                if ($visibility === 'groups' && !empty($selected_groups)) {
                    $stmtShare = $mysqli->prepare("INSERT INTO ride_group_shares (ride_id, group_id, share_token, created_at) VALUES (?, ?, ?, NOW())");
                    if (!$stmtShare) throw new Exception('DB prepare failed (share).');
                    foreach (array_map('intval',$selected_groups) as $gid) {
                        $gt = bin2hex(random_bytes(12));
                        $stmtShare->bind_param('iis', $ride_id, $gid, $gt);
                        if (!$stmtShare->execute()) throw new Exception('Failed to create group share.');
                        $generated_links[] = ['label'=>"Group {$gid} link",'token'=>$gt,'url'=>rtrim($baseUrl,'/').'/ride.php?token='.urlencode($gt),'group_id'=>$gid];
                    }
                    $stmtShare->close();
                }

                $mysqli->commit();
                if (!empty($generated_links)) $_SESSION['last_generated_share_links'] = $generated_links;
                header('Location: ride.php?id='.$ride_id); exit;
            } catch (Exception $e) {
                $mysqli->rollback();
                $errors[] = 'Unable to create ride: ' . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Post a Ride — HerSafar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg-a:#fbf9ff; --bg-b:#f7f3ff;
  --primary-1:#5b21b6; --primary-2:#8b5cf6; --accent:#c084fc;
  --muted:#6b4a86; --glass-border: rgba(255,255,255,0.35);
  --card-bg: linear-gradient(180deg, rgba(255,255,255,0.62), rgba(255,255,255,0.44));
  --radius:12px; --shadow:0 18px 50px rgba(91,33,182,0.08);
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


/* centered main card */
.card {
  width: 100%;
  max-width: 820px;                         /* nice medium width */
  margin: 20px auto;                        /* centers it & adds top/bottom space */
  background: linear-gradient(180deg, rgba(255,255,255,0.88), rgba(255,255,255,0.82));
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  padding: 28px 24px;                       /* balanced inner spacing */
  box-shadow: var(--shadow);
  backdrop-filter: blur(10px) saturate(120%);
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

/* make sure it sits nicely under sticky header */
body {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  margin: 0;
}

main.container {
  flex: 1 0 auto;
  display: flex;
  align-items: center;       /* centers vertically */
  justify-content: center;   /* centers horizontally */
  padding: 20px;
}

/* optional responsive tweak */
@media (max-width: 768px) {
  .card {
    max-width: 92%;
    margin: 24px auto;
    padding: 20px;
  }
}

.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.h-title{font-size:20px;color:var(--primary-1);margin:0}
.h-sub{color:var(--muted);font-size:13px;margin:2px 0 0 0}
.grid{display:grid;grid-template-columns:1fr 300px;gap:18px}
@media(max-width:900px){ .grid{grid-template-columns:1fr} }
.field{margin-bottom:12px}
label{display:block;font-size:13px;color:var(--muted);margin-bottom:8px}
.input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(99,102,241,0.06);background:#fff;font-size:14px}
textarea{min-height:90px;resize:vertical}
.row{display:flex;gap:10px;align-items:center}
.btn{padding:10px 14px;border-radius:8px;border:0;cursor:pointer;font-weight:700;background:linear-gradient(90deg,var(--primary-2),var(--primary-1));color:#fff}
.btn.ghost{background:transparent;border:1px solid rgba(15,23,42,0.06);color:#23123b;padding:10px 12px}
.muted{color:var(--muted);font-size:13px}
.note{padding:10px;border-radius:8px;background:#fff7ed;border:1px solid rgba(249,231,159,0.4);color:#92400e}
.error{padding:10px;border-radius:8px;background:#fff1f2;color:#9b1c1c;margin-bottom:12px}
.ok{padding:10px;border-radius:8px;background:#ecfdf5;color:#065f46;margin-bottom:12px}
.side-card{background:#fbfdff;border-radius:10px;padding:12px;border:1px solid rgba(15,23,42,0.04)}
.group-list{max-height:220px;overflow:auto;padding:8px;border-radius:6px;border:1px dashed rgba(15,23,42,0.04)}
.group-item{padding:8px;border-radius:6px;display:flex;gap:8px;align-items:center}
.link-row{display:flex;gap:8px;align-items:center;margin-top:10px}
.link-input{flex:1;padding:8px;border-radius:6px;border:1px solid #eef2ff;background:#fff}
.copybtn{padding:8px 10px;border-radius:6px;border:0;background:#10b981;color:#fff;font-weight:600;cursor:pointer}
</style>
</head>
<body>
  <?php include 'header.php' ?>
  <div class="card" role="main" aria-labelledby="post-title">
    <div class="header">
      <div>
        <h1 id="post-title" class="h-title">Post A Ride</h1>
        <div class="h-sub">Create a ride, choose sharing and optionally generate tokens for groups or public use.</div>
      </div>
      <div class="muted">Driver: <strong><?php echo htmlspecialchars($userRow['name']); ?></strong></div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="error"><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div>
    <?php endif; ?>

    <div class="grid">
      <form method="POST" action="post_ride.php" style="min-width:0">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

        <div class="field">
          <label>From</label>
          <input class="input" type="text" name="from" value="<?php echo htmlspecialchars($_POST['from'] ?? ''); ?>" required>
        </div>

        <div class="field">
          <label>To</label>
          <input class="input" type="text" name="to" value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>" required>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="field">
            <label>Date</label>
            <input id="rideDate" class="input" type="date" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>" required>
          </div>
          <div class="field">
            <label>Time (optional)</label>
            <input id="rideTime" class="input" type="time" name="time" value="<?php echo htmlspecialchars($_POST['time'] ?? ''); ?>">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px">
          <div class="field">
            <label>Seats</label>
            <input class="input" type="number" name="seats" min="1" value="<?php echo htmlspecialchars($_POST['seats'] ?? 1); ?>">
          </div>
          <div class="field">
            <label>Price / seat (INR)</label>
            <input class="input" type="number" step="0.5" name="price" value="<?php echo htmlspecialchars($_POST['price'] ?? 0); ?>">
          </div>
        </div>

        <div class="field" style="margin-top:8px">
          <label>Notes</label>
          <textarea class="input" name="notes"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
        </div>

        <div class="field" style="margin-top:6px">
          <label>Share</label>
          <div class="row" style="align-items:center">
            <label style="display:flex;align-items:center;gap:8px"><input type="radio" name="visibility" value="private" <?php if(($_POST['visibility'] ?? '')==='private' || empty($_POST['visibility'])) echo 'checked'; ?>> Private</label>
            <label style="display:flex;align-items:center;gap:8px"><input type="radio" name="visibility" value="public" <?php if(($_POST['visibility'] ?? '')==='public') echo 'checked'; ?>> Public</label>
            <label style="display:flex;align-items:center;gap:8px"><input type="radio" name="visibility" value="groups" <?php if(($_POST['visibility'] ?? '')==='groups') echo 'checked'; ?>> Groups</label>
          </div>
        </div>

        <div class="field">
          <label>Share to my groups (if 'Groups' selected)</label>
          <div class="group-list">
            <?php if (empty($groups)): ?>
              <div class="muted">You don't belong to any groups yet.</div>
            <?php else: foreach($groups as $g): ?>
              <label class="group-item"><input type="checkbox" name="groups[]" value="<?php echo (int)$g['id']; ?>" <?php
                $checked = $_POST['groups'] ?? [];
                if (is_array($checked) && in_array($g['id'], array_map('intval', $checked))) echo 'checked';
              ?>> <?php echo htmlspecialchars($g['name']); ?></label>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <div class="row" style="margin-top:12px">
          <button class="btn" type="submit">Post ride</button>
          <a class="btn ghost" href="dashboard.php" style="text-decoration:none">Cancel</a>
        </div>
      </form>

      <aside>
        <div class="side-card">
          <div style="font-weight:700">Generated links</div>
          <div class="muted" style="font-size:13px;margin-top:6px">After creating a ride, any generated share links will appear here.</div>

          <?php
          if (!empty($_SESSION['last_generated_share_links'])):
            $links = $_SESSION['last_generated_share_links'];
            unset($_SESSION['last_generated_share_links']);
          ?>
            <?php foreach($links as $l): ?>
              <div style="margin-top:12px">
                <div style="font-weight:600"><?php echo htmlspecialchars($l['label']); ?></div>
                <div class="link-row">
                  <input class="link-input" readonly value="<?php echo htmlspecialchars($l['url']); ?>" id="link-<?php echo htmlspecialchars($l['token']); ?>">
                  <button class="copybtn" data-clip="<?php echo htmlspecialchars($l['url']); ?>">Copy</button>
                </div>
                <div class="muted" style="margin-top:6px;font-size:13px">Token: <code><?php echo htmlspecialchars($l['token']); ?></code><?php if(!empty($l['group_id'])) echo ' • Group: ' . (int)$l['group_id']; ?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="margin-top:12px" class="muted">No links yet — post a ride to generate them.</div>
          <?php endif; ?>
        </div>
      </aside>
    </div>
    </div>
        <?php include 'footer.php' ?>

<script>
(function(){
  // set min date
  var date = document.getElementById('rideDate');
  if (date) {
    var d = new Date();
    var y = d.getFullYear(), m = ('0'+(d.getMonth()+1)).slice(-2), day = ('0'+d.getDate()).slice(-2);
    date.min = y + '-' + m + '-' + day;
  }

  // client-side time validation
  var form = document.querySelector('form[action="post_ride.php"]');
  var timeInput = document.getElementById('rideTime');
  if (form) {
    form.addEventListener('submit', function(e){
      var d = date.value;
      var t = timeInput.value;
      if (!d) return;
      if (!t) return;
      var sel = new Date(d + 'T' + t + ':00');
      if (sel.getTime() < Date.now() - 5000) {
        alert('Selected time is in the past. Please choose a future time.');
        e.preventDefault();
        return false;
      }
    });
  }

  // copy-to-clipboard
  document.addEventListener('click', function(ev){
    var t = ev.target;
    if (t.matches('.copybtn')) {
      var url = t.getAttribute('data-clip');
      if (!url) return;
      navigator.clipboard.writeText(url).then(function(){
        var old = t.innerText;
        t.innerText = 'Copied';
        setTimeout(function(){ t.innerText = old; }, 1400);
      }, function(){ alert('Copy failed — please select and copy manually.'); });
    }
  });
})();
</script>
</body>
</html>
