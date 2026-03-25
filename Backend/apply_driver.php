<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'dbcon.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
    header('Location: login.php'); exit;
}

// Helper: normalize status -> 'pending'|'approved'|'rejected'|'cancelled'|'unknown'
function normalized_status(?string $s): string {
    $s = strtolower(trim((string)$s));
    if ($s === '') return 'unknown';
    if (in_array($s, ['pending','applied','requested','waiting','in_review','in-review','under review','submitted','apply','requested'])) return 'pending';
    if (in_array($s, ['approved','accepted','verified','approved_by_admin','confirm','confirmed'])) return 'approved';
    if (in_array($s, ['rejected','declined'])) return 'rejected';
    if (in_array($s, ['cancelled','withdrawn','cancel'])) return 'cancelled';
    return $s;
}

// Refresh canonical user info from DB (role + verified + name + vehicle fields)
$userInfo = [
    'id'=>$uid,
    'name' => $_SESSION['user']['name'] ?? 'User',
    'role' => 'passenger',
    'verified' => 0,
    'vehicle_make' => '',
    'vehicle_model' => '',
    'vehicle_number' => '',
    'license_number' => ''
];

if (isset($mysqli) && $mysqli instanceof mysqli) {
    $q = "SELECT id,name,role,verified,vehicle_make,vehicle_model,vehicle_number,license_number FROM users WHERE id = ? LIMIT 1";
    $stmt = $mysqli->prepare($q);
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $fresh = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($fresh) {
            $userInfo = array_merge($userInfo, $fresh);
            // keep session in sync (non-destructive)
            $_SESSION['user']['name'] = $userInfo['name'];
            $_SESSION['user']['role'] = $userInfo['role'];
            $_SESSION['user']['verified'] = (int)$userInfo['verified'];
        }
    }
}

// Load latest driver application row (if any)
$existing = null;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $stmt = $mysqli->prepare("SELECT id, user_id, status, note, created_at, processed_at FROM driver_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            $existing['status'] = normalized_status($existing['status'] ?? '');
        }
    }

    // Fallback: if no row found, check admin_user_messages for "appl" to synthesize a pending entry
    if (!$existing) {
        $stmt2 = $mysqli->prepare("SELECT created_at, message FROM admin_user_messages WHERE user_id = ? AND LOWER(message) LIKE ? ORDER BY created_at DESC LIMIT 1");
        if ($stmt2) {
            $like = '%appl%';
            $stmt2->bind_param('is', $uid, $like);
            $stmt2->execute();
            $msgRow = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            if ($msgRow) {
                $existing = [
                    'id' => null,
                    'user_id' => $uid,
                    'status' => 'pending',
                    'note' => $msgRow['message'],
                    'created_at' => $msgRow['created_at'],
                    'processed_at' => null,
                ];
            }
        }
    }
}

// Decide what to display
$display = [
    'type' => 'none', // 'pending' | 'confirmed' | 'none'
    'message' => '',
    'applied_at' => null,
];

// If user's account already role=driver and verified=1 -> confirmed
if ($userInfo['role'] === 'driver' && (int)$userInfo['verified'] === 1) {
    $display['type'] = 'confirmed';
    $display['message'] = 'Your account is verified as a driver. You can post rides now.';
} else {
    // If there's a driver_applications row (or synthesized) use it
    if ($existing) {
        $st = normalized_status($existing['status'] ?? '');
        $display['applied_at'] = $existing['created_at'] ?? null;

        if ($st === 'pending') {
            $display['type'] = 'pending';
            $display['message'] = 'Your application is currently pending review by our admins. Please wait for confirmation.';
        } elseif ($st === 'approved') {
            // approved in applications table but user row might not yet be verified — show confirmed message
            $display['type'] = 'confirmed';
            $display['message'] = 'Your application has been approved by admin. Your account will be marked verified shortly.';
        } elseif ($st === 'rejected') {
            $display['type'] = 'none';
            $display['message'] = 'Your application was rejected. Please contact support for details.';
        } elseif ($st === 'cancelled') {
            $display['type'] = 'none';
            $display['message'] = 'You previously withdrew this application.';
        } else {
            $display['type'] = 'none';
            $display['message'] = 'Application status: ' . htmlspecialchars($existing['status'] ?? 'Unknown') . '.';
        }
    } else {
        // No application row — but profile flow may have already set role='driver' (saved details) and verified=0
        if ($userInfo['role'] === 'driver' && (int)$userInfo['verified'] === 0) {
            $display['type'] = 'pending';
            $display['message'] = 'Your account is marked as a driver and is awaiting admin verification. Please wait for confirmation.';
            // try to infer applied_at from latest uploaded doc or admin message if present
            if (isset($mysqli) && $mysqli instanceof mysqli) {
                // check last uploaded document timestamp
                $stmt3 = $mysqli->prepare("SELECT uploaded_at FROM user_documents WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
                if ($stmt3) {
                    $stmt3->bind_param('i', $uid);
                    $stmt3->execute();
                    $r = $stmt3->get_result()->fetch_assoc();
                    $stmt3->close();
                    if ($r && !empty($r['uploaded_at'])) $display['applied_at'] = $r['uploaded_at'];
                }
                // fallback to admin message already checked earlier (synthesized as $existing) — nothing else required
            }
        } else {
            $display['type'] = 'none';
            $display['message'] = 'No driver application found. If you already applied, contact support or an admin to confirm, else go to profile and apply to be one.';
        }
    }
}

// Render page (UI matches your dashboard styling)
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Driver Application Status — HerSafar</title>
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
.section{margin-top:12px;background:#fff;border-radius:12px;box-shadow:0 4px 18px rgba(0,0,0,0.03);padding:22px;}
.section-title{font-size:18px;color:var(--primary-2);border-bottom:1px solid rgba(99,102,241,0.1);padding-bottom:8px;margin-bottom:12px}
.small{font-size:13px;color:var(--muted)}
.status-pending{background:#fff7ed;border:1px solid rgba(240, 131, 131, 0.47);color:#92400e;padding:14px;border-radius:10px;font-weight:700}
.status-confirm{background:#ecfdf5;border:1px solid rgba(16,185,129,0.08);color:#065f46;padding:14px;border-radius:10px;font-weight:700}
.status-none{background:#f3f4f6;border:1px solid rgba(15,23,42,0.04);color:#374151;padding:14px;border-radius:10px}
.footer{position:fixed;right:16px;bottom:10px;font-size:12px;color:var(--muted);background:rgba(255,255,255,0.6);padding:6px 10px;border-radius:8px;border:1px solid rgba(99,102,241,0.06);backdrop-filter:blur(6px)}
@media(max-width:900px){body{flex-direction:column}.sidebar{width:100%;flex-direction:row;align-items:center;gap:10px}}
</style>
</head>
<body>
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
      <a href="groups.php">Groups</a>
      <a href="manage_rides.php">Manage Bookings</a>
      <a href="join_ride.php">Join A Ride</a>
      <a href="apply_driver.php" class="active">Driver Applications</a>
    </nav>

    <div class="spacer"></div>
    <div class="logged-user">Signed in as<br><strong><?= htmlspecialchars($userInfo['name'] ?? ($_SESSION['user']['name'] ?? 'User')) ?></strong></div>

    <div class="bottom-links">
      <a href="change_password.php">Change Password</a>
     <a href="/herSafar/login.php">Logout</a>
    </div>
  </aside>

  <main class="main" role="main" aria-labelledby="apply-title">
    <div class="card" role="region" aria-labelledby="apply-title-inner">
      <div class="header-row">
        <h1 id="apply-title" class="h-title">Driver Application Status</h1>
        <div class="h-sub">This page displays the current status of your driver application.</div>
      </div>

      <div class="section" aria-live="polite">
        <div class="section-title" id="apply-title-inner">Status</div>

        <?php if ($display['type'] === 'pending'): ?>
          <div class="status-pending" role="status">
            ⏳ Pending — <?php echo htmlspecialchars($display['message']); ?>
            <?php if (!empty($display['applied_at'])): ?>
              <div class="small" style="margin-top:8px">Applied on: <?php echo htmlspecialchars($display['applied_at']); ?></div>
            <?php endif; ?>
          </div>

        <?php elseif ($display['type'] === 'confirmed'): ?>
          <div class="status-confirm" role="status">
            ✅ Confirmation — <?php echo htmlspecialchars($display['message']); ?>
          </div>

        <?php else: ?>
          <div class="status-none" role="status">
            ℹ️ <?php echo htmlspecialchars($display['message']); ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </main>
</body>
<div class="footer">&copy; <?= date('Y') ?> HerSafar — Safe, Smart, and Connected Travel</div>
</html>
