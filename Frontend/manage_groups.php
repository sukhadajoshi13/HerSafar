<?php
require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$uid = (int)$_SESSION['user']['id'];
$flash = get_flash();
$errors = [];

/* Handle POST actions (delete group, remove member, revoke share) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! verify_csrf($_POST['csrf'] ?? '')) {
        set_flash('error','Invalid CSRF token.');
        header('Location: manage_groups.php'); exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'delete_group') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $stmt = $mysqli->prepare("SELECT id FROM groups WHERE id = ? AND owner_id = ? LIMIT 1");
        $stmt->bind_param('ii', $gid, $uid);
        $stmt->execute();
        $g = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$g) { set_flash('error','Not authorized or group not found.'); header('Location: manage_groups.php'); exit; }
        $stmt = $mysqli->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->bind_param('i', $gid);
        if ($stmt->execute()) set_flash('success','Group deleted.');
        else set_flash('error','Failed to delete group: ' . $stmt->error);
        $stmt->close();
        header('Location: manage_groups.php'); exit;
    }

    if ($action === 'remove_member') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $remove_uid = (int)($_POST['user_id'] ?? 0);
        $stmt = $mysqli->prepare("SELECT id FROM groups WHERE id = ? AND owner_id = ? LIMIT 1");
        $stmt->bind_param('ii', $gid, $uid);
        $stmt->execute();
        $g = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$g) { set_flash('error','Not authorized.'); header('Location: manage_groups.php'); exit; }

        if ($remove_uid === $uid) { set_flash('error','Owner cannot remove themselves.'); header('Location: manage_groups.php'); exit; }

        $stmt = $mysqli->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $gid, $remove_uid);
        if ($stmt->execute()) set_flash('success','Member removed.');
        else set_flash('error','Failed to remove member: ' . $stmt->error);
        $stmt->close();
        header('Location: manage_groups.php'); exit;
    }

    if ($action === 'revoke_share') {
        $share_id = (int)($_POST['share_id'] ?? 0);
        $stmt = $mysqli->prepare("DELETE FROM ride_shares WHERE id = ? AND created_by = ? LIMIT 1");
        $stmt->bind_param('ii', $share_id, $uid);
        if ($stmt->execute()) set_flash('success','Share link revoked.');
        else set_flash('error','Failed to revoke share link.');
        $stmt->close();
        header('Location: manage_groups.php'); exit;
    }
}

/* Load groups owned by user */
$owned_groups = [];
$stmt = $mysqli->prepare("SELECT * FROM groups WHERE owner_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $uid);
$stmt->execute();
$owned_groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Load rides the user owns (driver) */
$my_rides = [];
$stmt = $mysqli->prepare("SELECT * FROM rides WHERE driver_id = ? ORDER BY ride_date DESC, ride_time DESC LIMIT 100");
$stmt->bind_param('i', $uid);
$stmt->execute();
$my_rides = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Load existing shares created by me */
$my_shares = [];
$stmt = $mysqli->prepare("SELECT rs.*, r.from_location, r.to_location, g.name AS group_name FROM ride_shares rs LEFT JOIN rides r ON rs.ride_id = r.id LEFT JOIN groups g ON rs.group_id = g.id WHERE rs.created_by = ? ORDER BY rs.created_at DESC LIMIT 200");
$stmt->bind_param('i', $uid);
if ($stmt->execute()) {
    $my_shares = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Groups & Share Rides — HerSafar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg-a:#fbf9ff;
      --bg-b:#f7f3ff;
      --primary-1:#5b21b6;
      --primary-2:#8b5cf6;
      --accent:#c084fc;
      --muted:#6b4a86;
      --glass-border: rgba(255,255,255,0.35);
      --card-bg: rgba(255,255,255,0.56);
      --err-bg:#fff1f2;
      --ok-bg:#ecfdf5;
      --radius:12px;
      --shadow:0 18px 50px rgba(91,33,182,0.08);
      --ease:cubic-bezier(.16,.84,.33,1);
      font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
    }
    *{box-sizing:border-box}
    html,body{height:100%;margin:0}
    body{
      min-height:100vh;
      display:flex;
      gap:20px;
      background:
        radial-gradient(700px 300px at 10% 10%, rgba(139,92,246,0.045), transparent 10%),
        radial-gradient(600px 260px at 90% 90%, rgba(192,132,252,0.03), transparent 10%),
        linear-gradient(180deg,var(--bg-a),var(--bg-b));
      -webkit-font-smoothing:antialiased;
      color:#241235;
      padding:20px;
    }

    /* SIDEBAR (kept exactly as in dashboard) */
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
    .nav a{
      color:#fff;text-decoration:none;padding:10px 12px;border-radius:10px;font-weight:600;border:1px solid rgba(255,255,255,0.12);transition:all .18s var(--ease);font-size:13px;
    }
    .nav a:hover{transform:translateY(-2px);background:rgba(255,255,255,0.08)}
    .nav a.active{background:rgba(255,255,255,0.12);box-shadow:0 6px 18px rgba(0,0,0,0.05)}

    .spacer{flex:1}
    .bottom-links{display:flex;flex-direction:column;gap:8px;margin-top:12px}
    .bottom-links a{display:block;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.95);color:#a855f7;text-decoration:none;font-weight:700;text-align:center;border:1px solid rgba(255,255,255,0.3)}
    .bottom-links a:hover{background:#f3e8ff;color:#6b21b6}

    .sidebar .logged-user{margin-top:16px;font-size:13px;color:#fff;opacity:0.95;text-align:center}

    /* MAIN (card styles kept consistent) */
    .main{flex:1;display:flex;flex-direction:column;gap:12px}
    .card{
      width:100%;
      max-width:1100px;
      background: linear-gradient(180deg, rgba(255,255,255,0.62), rgba(255,255,255,0.44));
      border:1px solid var(--glass-border);
      border-radius:var(--radius);
      padding:22px;
      box-shadow:var(--shadow);
      backdrop-filter: blur(10px) saturate(120%);
    }

    /* manage groups specific */
    .notice { padding:12px;border-radius:10px;margin-bottom:12px }
    .notice.success{ background: #ecfdf5; border-left:4px solid #16a34a; color:#064e3b }
    .notice.error{ background: #fff1f2; border-left:4px solid #ef4444; color:#7f1d1d }

    .grid{display:grid;grid-template-columns:1fr 360px;gap:14px}
    @media(max-width:980px){ .grid{grid-template-columns:1fr;} }
    .group-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6}
    .group-meta{color:var(--muted);font-size:13px}
   .btn {
  display: inline-block;
  padding: 6px 10px; /* smaller, tighter */
  border-radius: 6px; /* slightly smaller radius */
  border: none;
  cursor: pointer;
  font-weight: 600;
  font-size: 13px; /* smaller font for compact look */
  transition: all 0.2s ease;
}

/* Primary gradient button */
.btn-primary {
  background: linear-gradient(90deg, var(--primary-2), var(--primary-1));
  color: #fff;
  box-shadow: 0 4px 12px rgba(91, 33, 182, 0.15);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(91, 33, 182, 0.25);
}

/* Optional muted/secondary variant */
.btn-muted {
  background: rgba(243, 244, 246, 0.9);
  color: #0f172a;
  border: 1px solid rgba(15, 23, 42, 0.06);
  padding: 6px 10px;
  border-radius: 6px;
  font-weight: 500;
  font-size: 13px;
  transition: all 0.2s ease;
}

.btn-muted:hover {
  background: rgba(229, 231, 235, 0.95);
  transform: translateY(-2px);
}

    .btn-danger{background:linear-gradient(90deg,#ff6b6b,#ef4444);color:#fff}
    .ghost{background:transparent;border:1px solid rgba(15,23,42,0.06);padding:7px 10px;border-radius:8px;color:#0f172a}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    th,td{padding:10px 8px;border-bottom:1px solid #f1f5f9;text-align:left;font-size:14px}
    th{font-weight:600;color:#334155}
    .share-input{width:100%;padding:8px;border-radius:8px;border:1px solid rgba(15,23,42,0.06)}
    .inline-form{display:flex;gap:8px;align-items:center}
    .link-box{width:100%;padding:8px;border-radius:8px;border:1px solid rgba(15,23,42,0.06);background:#fafafa}
    .footer { position: fixed; right: 16px; bottom: 10px; z-index: 999; font-size: 12px; color: var(--muted); background: rgba(255,255,255,0.6); padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(99,102,241,0.06); box-shadow: 0 6px 18px rgba(16,24,40,0.04); backdrop-filter: blur(6px) saturate(120%); pointer-events: none; opacity: 0.95; }
    .footer a { pointer-events: auto; color: inherit; text-decoration: none; font-weight:600 }
  </style>
</head>
<body>
  <!-- Sidebar: exactly the same as your dashboard sidebar -->
  <aside class="sidebar" role="navigation" aria-label="Sidebar">
    <div class="brand">
      <div class="logo-circle">HS</div>
      <div>
        <div class="brand-text">HerSafar</div>
        <div class="brand-sub">User Dashboard</div>
      </div>
    </div>

     <nav class="nav" aria-label="Main navigation">
      <a href="dashboard.php">Dashboard</a>
      <a href="index.php">Home</a>
      <a href="profile.php">Profile</a>
      <a href="groups.php"class="active">Groups</a>
      <a href="manage_rides.php">Manage Bookings</a>
      <a href="join_ride.php">Join A ride</a>
      <a href="apply_driver.php">Driver applications</a>
    </nav>

    <div class="spacer"></div>

    <div class="sidebar-section">
      <div class="logged-user">Signed in as<br><strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong></div>
    </div>

    <div style="margin-top:14px" class="bottom-links">
      <a href="change_password.php">Change Password</a>
       <a href="/hersafar/login.php">Logout</a>
    </div>
  </aside>

  <!-- Main content: manage groups & shares -->
  <main class="main">
    <div class="card" role="main" aria-labelledby="manage-groups-title">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div>
          <h2 id="manage-groups-title" style="margin:0;color:var(--primary-1)">Manage Groups & Share Rides</h2>
          <div class="small" style="margin-top:6px">Create/revoke share links and manage the groups you own.</div>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="notice <?php echo ($flash['type']==='success') ? 'success' : 'error'; ?>">
          <?php echo htmlspecialchars($flash['text']); ?>
        </div>
      <?php endif; ?>

      <div class="grid">
        <!-- left/main column -->
        <div>
          <div style="margin-bottom:12px" class="card">
            <h3 style="margin:0 0 8px 0">Your Owned Groups</h3>
            <?php if (empty($owned_groups)): ?>
              <div class="muted">You don't own any groups yet.
                <a href="create_group.php" class="btn-primary btn" style="margin-left:8px;text-decoration:none">Create group</a>
              </div>
            <?php else: ?>
              <?php foreach($owned_groups as $g): ?>
                <div class="group-row" role="group" aria-label="<?php echo htmlspecialchars($g['name']); ?>">
                  <div>
                    <div style="font-weight:700"><?php echo htmlspecialchars($g['name']); ?></div>
                    <div class="group-meta">Created: <?php echo htmlspecialchars($g['created_at']); ?></div>
                    <?php if (!empty($g['description'])): ?><div class="muted" style="margin-top:6px"><?php echo htmlspecialchars($g['description']); ?></div><?php endif; ?>
                  </div>

                  <div style="display:flex;gap:8px;align-items:center">
                    <a class="ghost" href="group.php?id=<?php echo (int)$g['id']; ?>">Open</a>

                    <form method="POST" style="display:inline">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="action" value="delete_group">
                      <input type="hidden" name="group_id" value="<?php echo (int)$g['id']; ?>">
                      <button class="btn-danger btn" type="submit" onclick="return confirm('Delete this group? This removes memberships but not rides.')">Delete</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="card" style="margin-top:12px">
            <h3 style="margin:0 0 8px 0">Your Share Links</h3>
            <?php if (empty($my_shares)): ?>
              <div class="muted">No active share links yet.</div>
            <?php else: ?>
              <table>
                <thead>
                  <tr><th>Ride</th><th>Group</th><th>Created</th><th>Expires</th><th>Link</th><th style="width:120px">Action</th></tr>
                </thead>
                <tbody>
                  <?php foreach($my_shares as $s):
                    $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/ride.php?share=' . rawurlencode($s['token']);
                  ?>
                    <tr>
                      <td><?php echo htmlspecialchars(($s['from_location'] ?? '') . ' → ' . ($s['to_location'] ?? '')); ?></td>
                      <td><?php echo htmlspecialchars($s['group_name'] ?? 'Public'); ?></td>
                      <td class="small"><?php echo htmlspecialchars($s['created_at']); ?></td>
                      <td class="small"><?php echo htmlspecialchars($s['expires_at'] ?? '—'); ?></td>
                      <td><input class="link-box" type="text" readonly value="<?php echo $link; ?>"></td>
                      <td>
                        <form method="POST" style="display:inline">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                          <input type="hidden" name="action" value="revoke_share">
                          <input type="hidden" name="share_id" value="<?php echo (int)$s['id']; ?>">
                          <button class="btn-danger btn" type="submit" onclick="return confirm('Revoke this share link?')">Revoke</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- right/aside column -->
        <aside>
          <div class="card">
            <h3 style="margin:0 0 8px 0">Your Rides (to share)</h3>
            <?php if (empty($my_rides)): ?>
              <div class="muted">You have no rides. <a href="post_ride.php" class="btn-primary btn" style="text-decoration:none">Post a ride</a></div>
            <?php else: ?>
              <table>
                <thead><tr><th>Ride</th><th>Date</th><th style="width:70px">Avail</th></tr></thead>
                <tbody>
                  <?php foreach($my_rides as $r): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($r['from_location']) . ' → ' . htmlspecialchars($r['to_location']); ?></td>
                      <td class="small"><?php echo htmlspecialchars($r['ride_date']) . ' ' . htmlspecialchars($r['ride_time']); ?></td>
                      <td><?php echo (int)$r['available_seats']; ?></td>
                    </tr>
                    <tr>
                      <td colspan="3">
                        <form method="POST" action="generate_share.php" class="inline-form" style="margin-top:8px">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                          <input type="hidden" name="ride_id" value="<?php echo (int)$r['id']; ?>">
                          <select name="group_id" class="share-input" style="flex:1">
                            <option value="">Public (anyone with link)</option>
                            <?php foreach($owned_groups as $g): ?>
                              <option value="<?php echo (int)$g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button class="btn-primary btn" type="submit">Generate link</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </div>
  </main>

<script>
(function(){
  // keep same sidebar join-link behavior as dashboard
  var sidebarJoinLink = document.getElementById('sidebarJoinLink');
  if (sidebarJoinLink) {
    sidebarJoinLink.addEventListener('click', function(e){
      e.preventDefault();
      var el = document.getElementById('shared_link');
      if (el) { el.focus(); return; }
      var vs = document.getElementById('vehicleSection');
      if (vs) vs.scrollIntoView({behavior:'smooth', block:'center'});
    });
  }
})();
</script>
<div class="footer">&copy; <?= date('Y') ?> HerSafar — Safe, Smart, and Connected Travel</div>
</body>
</html>
