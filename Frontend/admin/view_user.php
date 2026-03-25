<?php
require_once __DIR__ . '/../dbcon.php';
require_once __DIR__ . '/../functions.php';
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit;
}

$flash_html = '';
if (!empty($_SESSION['admin_msg'])) {
    $m = $_SESSION['admin_msg'];
    $color = $m['type'] === 'success' ? '#065f46' : ($m['type'] === 'error' ? '#b91c1c' : '#7e22ce');
    $flash_html = "<div style='background:#fff;padding:10px;border-radius:8px;border:1px solid #f3e8ff;color:$color;margin-bottom:12px'>"
                . htmlspecialchars($m['text']) . "</div>";
    unset($_SESSION['admin_msg']);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: users.php'); exit; }

$stmt = $mysqli->prepare('SELECT id,name,email,phone,gender,role,vehicle_make,vehicle_model,vehicle_number,license_number,bio,verified,active,created_at 
                          FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { header('Location: users.php'); exit; }

$rides = [];
$bookings = [];
if ($user['role'] === 'driver') {
    $stmt = $mysqli->prepare('SELECT id,from_location,to_location,ride_date,ride_time,seats,available_seats,price 
                              FROM rides WHERE driver_id = ? ORDER BY ride_date DESC LIMIT 20');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $rides = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $mysqli->prepare('SELECT b.id,b.ride_id,b.seats,b.status,b.created_at,r.from_location,r.to_location,r.ride_date
                              FROM bookings b JOIN rides r ON b.ride_id=r.id
                              WHERE b.user_id=? ORDER BY b.created_at DESC LIMIT 20');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$documents = [];
$stmt = $mysqli->prepare('SELECT id, type, file_path, mime, size_bytes, uploaded_at FROM user_documents WHERE user_id = ? ORDER BY uploaded_at DESC');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $documents[] = $r;
    }
    $stmt->close();
}

$admin_messages = [];
$stmt = $mysqli->prepare('SELECT m.id, m.admin_id, m.user_id, m.message, m.created_at, u.name AS admin_name
                          FROM admin_user_messages m
                          LEFT JOIN users u ON u.id = m.admin_id
                          WHERE m.user_id = ?
                          ORDER BY m.created_at DESC
                          LIMIT 200');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $admin_messages[] = $r;
    }
    $stmt->close();
}

$csrf = csrf_token();
function hr_filesize($bytes) {
    if ($bytes === null) return '—';
    $bytes = (int)$bytes;
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB','MB','GB','TB'];
    $i = 0;
    $v = $bytes / 1024;
    while ($v >= 1024 && $i < count($units)-1) { $v /= 1024; $i++; }
    return round($v, 2) . ' ' . $units[$i];
}

// safe URL builder — point to your download proxy
function download_url($doc_id, $force_dl = false) {
    $base = '/hersafar/download.php'; // change if needed
    $url = $base . '?doc_id=' . (int)$doc_id;
    if ($force_dl) $url .= '&dl=1';
    return $url;
}

function safe_file_url($path) {
    if (preg_match('#^https?://#i', $path)) return $path;
    $p = preg_replace('#\.\.[/\\\\]#', '', $path);
    $p = str_replace("\0", '', $p);
    return '/' . ltrim($p, '/');
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>View User — HerSafar Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400,500,600,700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:'Poppins',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
  background:#fdfcff;
  color:#2d1b4e;
}
.dashboard{display:flex;min-height:100vh}

.sidebar{
  width:260px;
background: linear-gradient(90deg, rgba(75,23,146,0.78), rgba(109,64,186,0.65), rgba(145,90,220,0.55));
  color:#fff;
  display:flex;
  flex-direction:column;
  padding:22px;
  border-radius:0px;
  box-shadow: 3px 8px 30px rgba(168,85,247,0.08);
  flex-shrink:0;
}

  .brand{display:flex;align-items:center;gap:10px;margin-bottom:40px;}
  .logo-circle{width:46px;height:46px;border-radius:12px;background:white;color:#9333ea;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;box-shadow:0 3px 10px rgba(255,255,255,0.3);}
  .brand-text{font-weight:700;font-size:18px}
  .brand-sub{font-size:12px;opacity:0.9}

  .nav{display:flex;flex-direction:column;gap:8px;flex-grow:1}
  .nav a{color:#fff;text-decoration:none;padding:10px 12px;border-radius:8px;font-weight:500;border:1px solid rgba(255,255,255,0.4);transition:all 0.25s ease}
  .nav a:hover{background:rgba(255,255,255,0.15);transform:translateY(-2px)}

  .logout{margin-top:auto;margin-bottom:10px;text-align:center}
  .logout a{display:block;background:white;color:#a855f7;padding:10px 12px;border-radius:8px;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,0.5)}
  .logout a:hover{background:#f3e8ff;color:#9333ea}

  .logged-user{margin-top:20px;font-size:13px;text-align:center;color:#fff;opacity:0.9}

/* Main */
.main{flex:1;padding:30px 40px;background:linear-gradient(180deg,#fff,#faf5ff)}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
h1{font-size:22px;font-weight:700;color:#6b21a8}
.back-btn{background:#fff;border:1px solid #f3e8ff;border-radius:8px;padding:8px 14px;color:#6b21a8;font-weight:600;text-decoration:none}
.back-btn:hover{background:#faf5ff;transform:translateY(-1px)}

.panel{background:white;border-radius:16px;padding:20px;border:1px solid #f3e8ff;box-shadow:0 8px 24px rgba(150,90,255,0.08);margin-bottom:20px}
.section-title{font-size:15px;font-weight:700;color:#6b21a8;margin-bottom:8px}
.row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #f3e8ff}
.k{color:#7e22ce;font-weight:600;font-size:13px}
.v{color:#3b0764;font-weight:700;font-size:14px}
table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
th,td{padding:8px;border-bottom:1px solid #f5f3ff;text-align:left}
th{background:#faf5ff;color:#6b21a8}
tbody tr:hover td{background:#fff7ff}

/* Documents grid */
.docs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:12px}
.doc-card{background:#fff;border-radius:10px;padding:10px;border:1px solid rgba(15,23,42,0.04);box-shadow:0 6px 18px rgba(13,16,40,0.02);display:flex;flex-direction:column;gap:8px;min-height:160px}
.doc-preview{height:110px;border-radius:8px;overflow:hidden;background:#fafafa;display:flex;align-items:center;justify-content:center;border:1px solid rgba(15,23,42,0.03)}
.doc-preview img{max-width:100%;max-height:100%;object-fit:contain}
.doc-meta{font-size:13px;color:#4b0f6b}
.doc-actions{display:flex;gap:8px;margin-top:auto}
.doc-actions a{padding:8px 10px;border-radius:8px;color:#6b21a8;text-decoration:none;font-weight:600;border:1px solid #f3e8ff;font-size:13px}
.doc-actions a.view{background:#ede9fe}
.doc-actions a.download{background:#fff7ed;color:#92400e;border:1px solid rgba(249,231,159,0.4)}

.icon-pdf{font-weight:800;color:#b91c1c;font-size:28px}
.small-muted{font-size:12px;color:#6b4a86}

/* Admin message section */
.msg-list{display:flex;flex-direction:column;gap:10px;margin-top:12px}
.msg-item{border-radius:10px;padding:10px;background:#fff8ff;border:1px solid rgba(243,230,255,0.6);box-shadow:0 6px 18px rgba(150,80,255,0.03)}
.msg-meta{font-size:12px;color:#6b21a8;font-weight:600;margin-bottom:6px}
.msg-text{font-size:14px;color:#2d1b4e;white-space:pre-wrap}

/* send form */
.send-form textarea{width:100%;min-height:84px;padding:10px;border-radius:8px;border:1px solid rgba(15,23,42,0.06);font-size:14px;resize:vertical}
.send-form .controls{display:flex;gap:8px;align-items:center;margin-top:8px}
.send-form .controls .btn{padding:8px 12px;border-radius:8px;background:#6b21a8;color:#fff;border:0;cursor:pointer}

/* Buttons */
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.btn{padding:8px 10px;border-radius:8px;border:1px solid #f3e8ff;background:#fff;color:#6b21a8;font-weight:600;font-size:13px;text-decoration:none;cursor:pointer}
.btn:hover{transform:translateY(-1px)}
.btn-refresh{background:#ede9fe;color:#5b21b6}
.btn-verify{background:#d1fae5;color:#065f46}
.btn-unverify{background:#f3f4f6;color:#4b5563}
.btn-deactivate{background:#fee2e2;color:#991b1b}
.btn-activate{background:#dbeafe;color:#1e3a8a}
footer{position:absolute;right:20px;bottom:10px;font-size:13px;color:#7e22ce;text-align:right}
@media(max-width:900px){.sidebar{display:none}header{flex-direction:column;align-items:flex-start;gap:8px}footer{position:static;margin-top:22px;text-align:center}}
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
      <a href="users.php" style="background:rgba(255,255,255,0.08)">Manage Users</a>
      <a href="rides_admin.php">Manage Rides</a>
    </nav>

    <div class="logout">
      <a href="../login.php">Logout</a>
    </div>

    <div class="logged-user">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong></div>
  </aside>

  <main class="main">
    <header>
      <h1>User Details</h1>
      <a href="users.php" class="back-btn">← Back to Users</a>
    </header>

    <?php echo $flash_html; ?>

    <section class="panel">
      <div class="section-title">Basic Information</div>
      <div class="row"><div class="k">Name</div><div class="v"><?php echo htmlspecialchars($user['name']); ?></div></div>
      <div class="row"><div class="k">Email</div><div class="v"><?php echo htmlspecialchars($user['email']); ?></div></div>
      <div class="row"><div class="k">Phone</div><div class="v"><?php echo htmlspecialchars($user['phone'] ?: '—'); ?></div></div>
      <div class="row"><div class="k">Gender</div><div class="v"><?php echo htmlspecialchars($user['gender'] ?: '—'); ?></div></div>
      <div class="row"><div class="k">Role</div><div class="v"><?php echo htmlspecialchars($user['role'] ?: 'user'); ?></div></div>
      <div class="row"><div class="k">Verified</div><div class="v"><?php echo $user['verified'] ? 'Yes' : 'No'; ?></div></div>
      <div class="row"><div class="k">Active</div><div class="v"><?php echo $user['active'] ? 'Yes' : 'No'; ?></div></div>
      <div class="row" style="border-bottom:none"><div class="k">Joined</div><div class="v"><?php echo htmlspecialchars($user['created_at']); ?></div></div>
    </section>

    <?php if ($user['role'] === 'driver'): ?>
      <section class="panel">
        <div class="section-title">Vehicle & License Details</div>
        <div class="row"><div class="k">Make</div><div class="v"><?php echo htmlspecialchars($user['vehicle_make'] ?: '—'); ?></div></div>
        <div class="row"><div class="k">Model</div><div class="v"><?php echo htmlspecialchars($user['vehicle_model'] ?: '—'); ?></div></div>
        <div class="row"><div class="k">Vehicle Number</div><div class="v"><?php echo htmlspecialchars($user['vehicle_number'] ?: '—'); ?></div></div>
        <div class="row" style="border-bottom:none"><div class="k">License</div><div class="v"><?php echo htmlspecialchars($user['license_number'] ?: '—'); ?></div></div>
      </section>

      <section class="panel">
        <div class="section-title">Rides Offered</div>
        <?php if (empty($rides)): ?>
          <p style="color:#7e22ce;font-size:13px">No rides found.</p>
        <?php else: ?>
          <table>
            <thead><tr><th>Route</th><th>Date</th><th>Time</th><th>Seats</th><th>Available</th><th>Price</th></tr></thead>
            <tbody>
              <?php foreach($rides as $r): ?>
              <tr>
                <td><?php echo htmlspecialchars($r['from_location'].' → '.$r['to_location']); ?></td>
                <td><?php echo htmlspecialchars($r['ride_date']); ?></td>
                <td><?php echo htmlspecialchars($r['ride_time']); ?></td>
                <td><?php echo (int)$r['seats']; ?></td>
                <td><?php echo (int)$r['available_seats']; ?></td>
                <td>₹<?php echo htmlspecialchars($r['price']); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <section class="panel">
        <div class="section-title">Recent Bookings</div>
        <?php if (empty($bookings)): ?>
          <p style="color:#7e22ce;font-size:13px">No bookings found.</p>
        <?php else: ?>
          <table>
            <thead><tr><th>Booking</th><th>Ride</th><th>Seats</th><th>Status</th><th>When</th></tr></thead>
            <tbody>
              <?php foreach($bookings as $b): ?>
              <tr>
                <td><?php echo (int)$b['id']; ?></td>
                <td><?php echo htmlspecialchars($b['from_location'].' → '.$b['to_location'].' ('.$b['ride_date'].')'); ?></td>
                <td><?php echo (int)$b['seats']; ?></td>
                <td><?php echo htmlspecialchars(ucfirst($b['status'])); ?></td>
                <td><?php echo htmlspecialchars($b['created_at']); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="panel">
      <div class="section-title">Uploaded Documents</div>
      <div class="small-muted">Inspect uploaded identity & license documents. Click View to open in a new tab (served securely via download.php).</div>

      <?php if (empty($documents)): ?>
        <p style="color:#7e22ce;margin-top:12px">No documents uploaded by this user.</p>
      <?php else: ?>
        <div class="docs-grid" role="list">
          <?php foreach ($documents as $doc): 
            $isImage = strpos($doc['mime'] ?? '', 'image/') === 0;
            $isPdf = ($doc['mime'] ?? '') === 'application/pdf';
            $typeLabel = htmlspecialchars(ucfirst(str_replace('_',' ',$doc['type'])));
            $basename = htmlspecialchars(basename($doc['file_path']));
            $sizeHuman = hr_filesize((int)$doc['size_bytes']);
            $viewUrl = download_url($doc['id'], false);
            $dlUrl   = download_url($doc['id'], true);
            ?>
            <article class="doc-card" role="listitem" aria-label="<?php echo $typeLabel; ?>">
              <div class="doc-preview" aria-hidden="true">
                <?php if ($isImage): ?>
                  <img src="<?php echo htmlspecialchars($viewUrl); ?>" alt="<?php echo $typeLabel; ?>">
                <?php elseif ($isPdf): ?>
                  <div class="icon-pdf">PDF</div>
                <?php else: ?>
                  <div class="small-muted">File</div>
                <?php endif; ?>
              </div>

              <div class="doc-meta">
                <div style="font-weight:700"><?php echo $typeLabel; ?></div>
                <div class="small-muted" title="<?php echo $basename; ?>"><?php echo $basename; ?></div>
                <div class="small-muted" style="margin-top:6px"><?php echo htmlspecialchars($doc['uploaded_at']); ?> • <?php echo $sizeHuman; ?></div>
              </div>

              <div class="doc-actions" aria-hidden="false">
                <a class="view" href="<?php echo htmlspecialchars($viewUrl); ?>" target="_blank" rel="noopener">View</a>
                <a class="download" href="<?php echo htmlspecialchars($dlUrl); ?>">Download</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="section-title">Admin message to user (verification notes)</div>
      <div class="small-muted">Send instructions or notes to the user about their verification.</div>

      <form class="send-form" method="POST" action="user_actions.php" style="margin-top:12px">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
        <input type="hidden" name="action" value="send_message">
        <label for="admin_message" style="display:block;margin-bottom:6px;font-weight:600;color:#6b21a8">Message to user</label>
        <textarea id="admin_message" name="message" placeholder="Write a clear message explaining what the user should do..." required></textarea>

        <div class="controls" style="margin-top:8px">
          <button class="btn" type="submit">Send message</button>
          <a class="btn" href="view_user.php?id=<?php echo (int)$user['id']; ?>" style="margin-left:6px">Cancel</a>
        </div>
      </form>

      <div style="margin-top:16px">
        <div style="font-weight:700;margin-bottom:8px">Recent admin messages</div>
        <?php if (empty($admin_messages)): ?>
          <div class="small-muted">No messages sent yet.</div>
        <?php else: ?>
          <div class="msg-list" aria-live="polite">
            <?php foreach ($admin_messages as $m): ?>
              <div class="msg-item" role="article">
                <div class="msg-meta"><?php echo htmlspecialchars($m['admin_name'] ?: 'Admin'); ?> • <?php echo htmlspecialchars($m['created_at']); ?></div>
                <div class="msg-text"><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <div class="section-title">Admin Actions</div>
      <div class="actions">
        <a class="btn btn-refresh" href="view_user.php?id=<?php echo $user['id']; ?>">Refresh</a>

        <form method="POST" action="user_actions.php" style="margin:0">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
          <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
          <?php if ($user['verified']): ?>
            <button class="btn btn-unverify" name="action" value="toggle_verify" onclick="return confirm('Remove verification?')">Unverify</button>
          <?php else: ?>
            <button class="btn btn-verify" name="action" value="toggle_verify" onclick="return confirm('Verify this user?')">Verify</button>
          <?php endif; ?>
        </form>

        <form method="POST" action="user_actions.php" style="margin:0">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
          <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
          <?php if ($user['active']): ?>
            <button class="btn btn-deactivate" name="action" value="toggle_active" onclick="return confirm('Deactivate this account?')">Deactivate</button>
          <?php else: ?>
            <button class="btn btn-activate" name="action" value="toggle_active" onclick="return confirm('Activate this account?')">Activate</button>
          <?php endif; ?>
        </form>
      </div>
    </section>
  </main>
</div>
</body>
</html>
