<?php

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';     
require_once 'functions.php';  

if (session_status() === PHP_SESSION_NONE) session_start();
require_login(); // your function should redirect if not logged in

$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

$errors = [];
$success = '';
$csrf = csrf_token();

// ---------- helper utilities ----------
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

function build_view_and_download_urls(array $doc) {
    $ret = ['view' => null, 'download' => null, 'exists_on_disk' => false, 'abs_path' => null];
    $dbPath = (string)($doc['file_path'] ?? '');
    if ($dbPath === '') return $ret;
    $abs = realpath(__DIR__ . '/' . ltrim($dbPath, '/'));
    $ret['abs_path'] = $abs ?: null;
    $ret['exists_on_disk'] = ($abs && is_file($abs));

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = dirname($script);
    if ($base === '/' || $base === '\\') $base = '';
    $base = rtrim($base, '/\\');

    if ($ret['exists_on_disk']) {
        $uploadsRoot = realpath(__DIR__ . '/uploads');
        if ($uploadsRoot && strpos($abs, $uploadsRoot) === 0) {
            $rel = str_replace('\\', '/', substr($abs, strlen(realpath(__DIR__))));
            if ($rel === false) $rel = '/' . ltrim($dbPath, '/');
            if ($rel === '' || $rel[0] !== '/') $rel = '/' . ltrim($rel, '/');
            $ret['view'] = $base . $rel;
            $ret['download'] = $base . '/download.php?doc_id=' . (int)$doc['id'] . '&dl=1';
            return $ret;
        } else {
            $ret['download'] = $base . '/download.php?doc_id=' . (int)$doc['id'] . '&dl=1';
            return $ret;
        }
    }

    $ret['download'] = $base . '/download.php?doc_id=' . (int)$doc['id'] . '&dl=1';
    return $ret;
}

// Simple function to insert a document record
function insert_user_document($mysqli, $user_id, $type, $file_path, $mime, $size_bytes) {
    $stmt = $mysqli->prepare("INSERT INTO user_documents (user_id, type, file_path, mime, size_bytes, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) return [false, $mysqli->error];
    $stmt->bind_param('isssi', $user_id, $type, $file_path, $mime, $size_bytes);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        return [false, $err];
    }
    $stmt->close();
    return [true, $mysqli->insert_id];
}

/**
 * handle_upload_field
 * - Saves files to: PROJECT_ROOT/uploads/docs/{uid}/photos/
 * - DB `file_path` stored as: /uploads/docs/{uid}/photos/<filename>
 * - Safer directory creation with retry/chmod on failure (helps local XAMPP permission issues)
 */
function handle_upload_field($fieldName, $uploadsBaseDir, $docType, $uid, $mysqli, &$errors) {
    if (empty($_FILES[$fieldName]) || !is_uploaded_file($_FILES[$fieldName]['tmp_name'])) return;
    $f = $_FILES[$fieldName];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload failed for {$fieldName} (PHP upload error code {$f['error']}).";
        return;
    }

    // size limit 8MB
    if ($f['size'] > 8 * 1024 * 1024) {
        $errors[] = "{$fieldName} too large (max 8MB).";
        return;
    }

    // target user directory: <uploadsBaseDir>/docs/{uid}/photos
    $userDir = rtrim($uploadsBaseDir, '/\\') . '/docs/' . intval($uid) . '/photos';

    // try to create directory; if fails, attempt permissive creation and retry
    if (!is_dir($userDir)) {
        $created = @mkdir($userDir, 0775, true);
        if (!$created) {
            $parent = dirname($userDir); // uploads/docs/{uid}
            // attempt to create grand-parents (uploads/docs) with permissive perms then retry
            @mkdir(dirname($parent), 0777, true); // ensure uploads exists
            @mkdir(dirname($userDir), 0777, true); // ensure uploads/docs exists
            @chmod(dirname($userDir), 0777);
            clearstatcache(true, $userDir);
            $created = @mkdir($userDir, 0775, true);
        }
        if (!$created) {
            $last = error_get_last();
            $errors[] = "Failed to create upload directory: {$userDir}. Server message: " . ($last['message'] ?? 'N/A') . ". Check permissions.";
            return;
        }
    }

    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', pathinfo($f['name'], PATHINFO_FILENAME));
    $timestamp = time();
    $newFilename = sprintf('%s_uid%d_%s_%s.%s', $docType, $uid, $timestamp, $safeName, $ext ?: 'dat');
    $target = $userDir . '/' . $newFilename;

    if (!@move_uploaded_file($f['tmp_name'], $target)) {
        $last = error_get_last();
        $errors[] = "Failed to move uploaded file for {$fieldName} to {$target}. Server message: " . ($last['message'] ?? 'N/A') . ". Check directory permissions.";
        return;
    }

    // store DB path as /uploads/docs/{uid}/photos/<filename>
    $relPath = '/uploads/docs/' . intval($uid) . '/photos/' . $newFilename;

    list($ok, $info) = insert_user_document($mysqli, $uid, $docType, $relPath, mime_content_type($target), filesize($target));
    if (!$ok) $errors[] = "DB insert failed for {$fieldName}: " . $info;
}

// ------------------ load user (existing) ------------------
$user = [
  'id'=>'','name'=>'','email'=>'','phone'=>'','gender'=>'female','role'=>'passenger','bio'=>'','verified'=>0,
  'vehicle_make'=>'','vehicle_model'=>'','vehicle_number'=>'','license_number'=>''
];

$q = "SELECT id,name,email,phone,gender,role,bio,verified,vehicle_make,vehicle_model,vehicle_number,license_number FROM users WHERE id = ? LIMIT 1";
$stmt = $mysqli->prepare($q);
if (!$stmt) {
    $errors[] = 'DB error (prepare load user): ' . $mysqli->error . ' — SQL: ' . $q;
} else {
    $stmt->bind_param('i',$uid);
    if (!$stmt->execute()) {
        $errors[] = 'DB error (execute load user): ' . $stmt->error;
    } else {
        $res = $stmt->get_result();
        if ($res) {
            $row = $res->fetch_assoc();
            if ($row) $user = array_replace($user, $row);
        } else {
            // fallback if get_result not available
            $stmt->close();
            $stmt2 = $mysqli->prepare($q);
            if ($stmt2) {
                $stmt2->bind_param('i',$uid);
                $stmt2->execute();
                $stmt2->bind_result($id,$name,$email,$phone,$gender,$role,$bio,$verified,$vehicle_make,$vehicle_model,$vehicle_number,$license_number);
                if ($stmt2->fetch()) {
                    $user = [
                      'id'=>$id,'name'=>$name,'email'=>$email,'phone'=>$phone,'gender'=>$gender,'role'=>$role,'bio'=>$bio,'verified'=>$verified,
                      'vehicle_make'=>$vehicle_make,'vehicle_model'=>$vehicle_model,'vehicle_number'=>$vehicle_number,'license_number'=>$license_number
                    ];
                }
                $stmt2->close();
            }
        }
    }
    $stmt->close();
}

// load latest-per-type existing uploaded documents for quick links
$existing_docs = [];
$stmt = $mysqli->prepare("SELECT id, type, file_path, mime, size_bytes, uploaded_at FROM user_documents WHERE user_id = ? ORDER BY uploaded_at DESC");
if (!$stmt) {
    $errors[] = 'DB error (prepare load docs): ' . $mysqli->error;
} else {
    $stmt->bind_param('i', $uid);
    if (!$stmt->execute()) $errors[] = 'DB error (execute load docs): ' . $stmt->error;
    else {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            if (!isset($existing_docs[$r['type']])) $existing_docs[$r['type']] = $r;
        }
    }
    $stmt->close();
}

// load all docs (for visible list)
$all_docs = [];
$stmt = $mysqli->prepare("SELECT id, type, file_path, mime, size_bytes, uploaded_at FROM user_documents WHERE user_id = ? ORDER BY uploaded_at DESC");
if ($stmt) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $all_docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// load admin messages sent to this user
$admin_messages = [];
$stmt = $mysqli->prepare("
    SELECT m.id, m.admin_id, m.user_id, m.message, m.created_at, u.name AS admin_name
    FROM admin_user_messages m
    LEFT JOIN users u ON m.admin_id = u.id
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
    LIMIT 25
");
if ($stmt) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $admin_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ------------------ handle form submission ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    if (!verify_csrf($_POST['csrf'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = in_array($_POST['gender'] ?? 'female',['female','male','other']) ? $_POST['gender'] : 'female';
        $account_pref = (($_POST['account_pref'] ?? '') === 'driver') ? 'driver' : 'passenger';
        $bio = trim($_POST['bio'] ?? '');
        $vehicle_make = trim($_POST['vehicle_make'] ?? '');
        $vehicle_model = trim($_POST['vehicle_model'] ?? '');
        $vehicle_number = trim($_POST['vehicle_number'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');

        if ($name === '') $errors[] = 'Name is required.';
        $wants_driver = ($account_pref === 'driver');
        if ($wants_driver && ($vehicle_make==='' || $vehicle_model==='' || $vehicle_number==='' || $license_number==='' )) {
            $errors[] = 'Please fill vehicle & license fields to apply as driver.';
        }

        // Base uploads folder (project-root/uploads)
        $uploadsBase = realpath(__DIR__);
        $uploadsBase = $uploadsBase . '/uploads';

        // process uploads into: uploads/docs/{uid}/photos
        handle_upload_field('aadhar_front', $uploadsBase, 'aadhar_front', $uid, $mysqli, $errors);
        handle_upload_field('aadhar_back',  $uploadsBase, 'aadhar_back',  $uid, $mysqli, $errors);
        handle_upload_field('pan_front',    $uploadsBase, 'pan_front',    $uid, $mysqli, $errors);
        handle_upload_field('pan_back',     $uploadsBase, 'pan_back',     $uid, $mysqli, $errors);
        handle_upload_field('license_scan', $uploadsBase, 'license',      $uid, $mysqli, $errors);

        if (empty($errors)) {
            try {
                $mysqli->begin_transaction();

                if ($wants_driver) {
                    $upd_sql = "UPDATE users SET name=?, phone=?, gender=?, bio=?, vehicle_make=?, vehicle_model=?, vehicle_number=?, license_number=?, role='driver', verified=0 WHERE id = ?";
                    $upd = $mysqli->prepare($upd_sql);
                    if (!$upd) throw new Exception('DB prepare failed (update user -> driver): ' . $mysqli->error);
                    $upd->bind_param('ssssssssi', $name, $phone, $gender, $bio, $vehicle_make, $vehicle_model, $vehicle_number, $license_number, $uid);
                    if (!$upd->execute()) throw new Exception('DB execute failed (update user -> driver): ' . $upd->error);
                    $upd->close();
                    $_SESSION['user']['role'] = 'driver';
                    $_SESSION['user']['verified'] = 0;
                } else {
                    $upd_sql = "UPDATE users SET name=?, phone=?, gender=?, bio=?, role='passenger' WHERE id = ?";
                    $upd = $mysqli->prepare($upd_sql);
                    if (!$upd) throw new Exception('DB prepare failed (update user -> passenger): ' . $mysqli->error);
                    $upd->bind_param('ssssi', $name, $phone, $gender, $bio, $uid);
                    if (!$upd->execute()) throw new Exception('DB execute failed (update user -> passenger): ' . $upd->error);
                    $upd->close();
                    $_SESSION['user']['role'] = 'passenger';
                }

                $mysqli->commit();

                // reload fresh user & docs for display
                $stmt3 = $mysqli->prepare("SELECT id,name,email,phone,gender,role,bio,verified,vehicle_make,vehicle_model,vehicle_number,license_number FROM users WHERE id = ? LIMIT 1");
                if ($stmt3) {
                    $stmt3->bind_param('i', $uid);
                    $stmt3->execute();
                    $user = $stmt3->get_result()->fetch_assoc();
                    $stmt3->close();
                }

                $stmt4 = $mysqli->prepare("SELECT id, type, file_path, mime, size_bytes, uploaded_at FROM user_documents WHERE user_id = ? ORDER BY uploaded_at DESC");
                if ($stmt4) {
                    $stmt4->bind_param('i', $uid);
                    $stmt4->execute();
                    $res4 = $stmt4->get_result();
                    $existing_docs = [];
                    $all_docs = [];
                    while ($r = $res4->fetch_assoc()) {
                        if (!isset($existing_docs[$r['type']])) $existing_docs[$r['type']] = $r;
                        $all_docs[] = $r;
                    }
                    $stmt4->close();
                }

                $success = 'Profile Saved';

            } catch (Exception $e) {
                $mysqli->rollback();
                $errors[] = 'Save failed: ' . $e->getMessage();
            }
        }
    }
}

// CSRF for display
$token = csrf_token();
$current_user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Profile — Hersafar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400,500,600,700&display=swap" rel="stylesheet">
<style>
/* keep your exact styles — unchanged from your last file */
:root{
  --bg-a:#fbf9ff; --bg-b:#f7f3ff;
  --primary-1:#5b21b6; --primary-2:#8b5cf6; --accent:#c084fc;
  --muted:#6b4a86; --glass-border: rgba(255,255,255,0.35);
  --radius:12px; --shadow:0 18px 50px rgba(91,33,182,0.08);
  --ease: cubic-bezier(.16,.84,.33,1);
  font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
}
*{box-sizing:border-box}
html,body{height:100%;margin:0}
body{min-height:100vh;display:flex;gap:20px;padding:20px;background:linear-gradient(180deg,var(--bg-a),var(--bg-b));color:#241235}

/* Sidebar (unchanged size/style) */
.sidebar{width:260px; background: linear-gradient(90deg, rgba(75,23,146,0.78), rgba(109,64,186,0.65), rgba(145,90,220,0.55));color:#fff;display:flex;flex-direction:column;padding:22px;border-radius:14px;box-shadow:3px 8px 30px rgba(168,85,247,0.08);flex-shrink:0}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.logo-circle{width:46px;height:46px;border-radius:12px;background:white;color:#9333ea;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px}
.brand-text{font-weight:700;font-size:16px}
.brand-sub{font-size:12px;opacity:0.95}
.nav{display:flex;flex-direction:column;gap:8px;margin-top:8px}
.nav a{color:#fff;text-decoration:none;padding:10px 12px;border-radius:10px;font-weight:600;border:1px solid rgba(255,255,255,0.12);transition:all .18s var(--ease);font-size:13px}
.nav a:hover{transform:translateY(-2px);background:rgba(255,255,255,0.08)}
.nav a.active{background:rgba(255,255,255,0.12);box-shadow:0 6px 18px rgba(0,0,0,0.05)}
.spacer{flex:1}
.bottom-links{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.bottom-links a{display:block;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.95);color:#a855f7;text-decoration:none;font-weight:700;text-align:center;border:1px solid rgba(255,255,255,0.3)}
.bottom-links a:hover{background:#f3e8ff;color:#6b21b6}
.sidebar .logged-user{margin-top:16px;font-size:13px;color:#fff;opacity:0.95;text-align:center}

/* Main */
.main{flex:1;display:flex;flex-direction:column;gap:12px}
.card{width:100%;max-width:1100px;background:linear-gradient(180deg, rgba(255,255,255,0.62), rgba(255,255,255,0.44));border:1px solid var(--glass-border);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);backdrop-filter:blur(10px) saturate(120%)}
.header-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
.h-title{font-size:20px;margin:0;color:var(--primary-1)}
.h-sub{margin:0;color:var(--muted);font-size:13px}
.form-grid{display:grid;grid-template-columns:1fr 360px;gap:18px 24px;align-items:start}
@media(max-width:980px){ .form-grid{grid-template-columns:1fr; } }
label{display:block;margin-bottom:6px;font-weight:600;color:var(--primary-1)}
input[type="text"], input[type="email"], select, textarea, input[type="file"]{width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(99,102,241,0.06);background:rgba(255,255,255,0.95);font-size:15px;color:#23123b;outline:none}
textarea{min-height:92px;resize:vertical}
.section{background:linear-gradient(180deg,rgba(255,255,255,0.6),rgba(255,255,255,0.4));padding:12px;border-radius:10px;border:1px solid rgba(237,233,255,0.6)}
.small{font-size:13px;color:var(--muted);margin-top:6px}
.actions{display:flex;gap:12px;align-items:center;margin-top:10px}
.btn{padding:8px 12px;border-radius:8px;border:0;background:linear-gradient(90deg,var(--primary-2),var(--primary-1));color:#fff;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 8px 20px rgba(91,33,182,0.08)}
.btn.ghost{background:transparent;border:1px solid rgba(15,23,42,0.06);color:#111827}
.err{background:#fff1f2;border-radius:10px;padding:12px;color:#7f1d1d;margin-bottom:12px}
.ok{background:#ecfdf5;border-radius:10px;padding:12px;color:#065f46;margin-bottom:12px}
.upload-list{display:grid;gap:8px;margin-top:8px}
.upload-item{display:flex;align-items:center;gap:8px;padding:8px;border-radius:8px;background:#fff;border:1px solid rgba(15,23,42,0.04)}
.upload-item a{font-weight:700;color:var(--primary-1);text-decoration:none}
.note{font-size:13px;color:var(--muted);margin-top:8px}

/* documents grid styles (same style as admin) */
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
.footer { position: fixed; right: 16px; bottom: 10px; font-size: 12px; color: var(--muted); background: rgba(255,255,255,0.6); padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(99,102,241,0.06); box-shadow: 0 6px 18px rgba(16,24,40,0.04); pointer-events: none; opacity: 0.95; }
.footer a { pointer-events: auto; color: inherit; text-decoration: none; font-weight:600 }
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

    <nav class="nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="index.php">Home</a>
      <a href="profile.php" class="active">Profile</a>
      <a href="groups.php">Groups</a>
      <a href="manage_rides.php">Manage Bookings</a>
      <a href="join_ride.php">Join A ride</a>
      <a href="apply_driver.php">Driver applications</a>
    </nav>

    <div class="spacer"></div>
    <div class="logged-user">Signed in as<br><strong><?= $current_user_name ?></strong></div>

    <div class="bottom-links">
      <a href="change_password.php">Change Password</a>
       <a href="/hersafar/login.php">Logout</a>
    </div>
  </aside>

  <main class="main" role="main" aria-labelledby="profile-title">
    <div class="card">
      <div class="header-row">
        <div style="display:flex;align-items:center;gap:12px">
          <div>
            <h2 class="h-title" id="profile-title">My Profile</h2>
            <div class="h-sub">Edit your details and apply to be a driver</div>
          </div>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="err" role="alert"><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="ok" role="status"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="POST" action="profile.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token); ?>">
        <input type="hidden" name="save_profile" value="1">

        <div class="form-grid">
          <div>
            <label for="name">Name</label>
            <input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($_POST['name'] ?? $user['name'] ?? ''); ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
              <div>
                <label for="phone">Phone</label>
                <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>">
              </div>
              <div>
                <label for="gender">Gender</label>
                <select id="gender" name="gender" aria-label="Gender">
                  <option value="female" <?php echo (($_POST['gender'] ?? $user['gender'])=='female')?'selected':''; ?>>Female</option>
                  <option value="male" <?php echo (($_POST['gender'] ?? $user['gender'])=='male')?'selected':''; ?>>Male</option>
                  <option value="other" <?php echo (($_POST['gender'] ?? $user['gender'])=='other')?'selected':''; ?>>Other</option>
                </select>
              </div>
            </div>

            <label style="margin-top:12px">Email</label>
            <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>

            <label style="margin-top:12px">Account preference</label>
            <select name="account_pref" id="accountPref">
              <option value="passenger" <?php echo ($user['role']==='passenger') ? 'selected' : ''; ?>>Passenger</option>
              <option value="driver" <?php echo ($user['role']==='driver') ? 'selected' : ''; ?>>Driver</option>
            </select>
            <div class="small">Select <strong>Driver</strong> to apply and save vehicle/license details. Admin will verify before full access.</div>

            <div style="margin-top:14px">
              <label>Notes / Bio</label>
              <textarea name="bio" rows="4"><?php echo htmlspecialchars($_POST['bio'] ?? $user['bio'] ?? ''); ?></textarea>
            </div>

            <div style="margin-top:14px">
              <h4 style="margin:0 0 8px 0;color:var(--primary-1)">Identity documents</h4>
              <div class="small">Passengers: Aadhar (front+back) or PAN (front). Drivers: Aadhar (front+back) + Driving license.</div>

              <label style="margin-top:10px">Aadhar — front</label>
              <input type="file" name="aadhar_front" accept=".jpg,.jpeg,.png,.pdf">
              <?php if (!empty($existing_docs['aadhar_front'])): ?>
                <div class="upload-item"><a href="<?php echo htmlspecialchars($existing_docs['aadhar_front']['file_path']); ?>" target="_blank" rel="noopener">View Aadhar front (raw path)</a></div>
              <?php endif; ?>

              <label style="margin-top:8px">Aadhar — back</label>
              <input type="file" name="aadhar_back" accept=".jpg,.jpeg,.png,.pdf">
              <?php if (!empty($existing_docs['aadhar_back'])): ?>
                <div class="upload-item"><a href="<?php echo htmlspecialchars($existing_docs['aadhar_back']['file_path']); ?>" target="_blank" rel="noopener">View Aadhar back (raw path)</a></div>
              <?php endif; ?>

              <label style="margin-top:8px">PAN — front</label>
              <input type="file" name="pan_front" accept=".jpg,.jpeg,.png,.pdf">
              <?php if (!empty($existing_docs['pan_front'])): ?>
                <div class="upload-item"><a href="<?php echo htmlspecialchars($existing_docs['pan_front']['file_path']); ?>" target="_blank" rel="noopener">View PAN front (raw path)</a></div>
              <?php endif; ?>

              <label style="margin-top:8px">PAN — back (optional)</label>
              <input type="file" name="pan_back" accept=".jpg,.jpeg,.png,.pdf">
              <?php if (!empty($existing_docs['pan_back'])): ?>
                <div class="upload-item"><a href="<?php echo htmlspecialchars($existing_docs['pan_back']['file_path']); ?>" target="_blank" rel="noopener">View PAN back (raw path)</a></div>
              <?php endif; ?>

            </div>

          </div>

          <aside>
            <div class="section" id="vehicleSection" style="display:<?php echo ( $user['role']==='driver' || !empty($existing_docs) ) ? 'block' : 'none'; ?>;">
              <h4>Vehicle & License Details</h4>

              <label style="margin-top:8px">Vehicle Name</label>
              <input name="vehicle_make" type="text" value="<?php echo htmlspecialchars($_POST['vehicle_make'] ?? $user['vehicle_make'] ?? ''); ?>">

              <label style="margin-top:8px">Vehicle Model</label>
              <input name="vehicle_model" type="text" value="<?php echo htmlspecialchars($_POST['vehicle_model'] ?? $user['vehicle_model'] ?? ''); ?>">

              <label style="margin-top:8px">Vehicle Number</label>
              <input name="vehicle_number" type="text" value="<?php echo htmlspecialchars($_POST['vehicle_number'] ?? $user['vehicle_number'] ?? ''); ?>">

              <label style="margin-top:8px">License Number</label>
              <input name="license_number" type="text" value="<?php echo htmlspecialchars($_POST['license_number'] ?? $user['license_number'] ?? ''); ?>">

              <label style="margin-top:8px">Driving license (upload)</label>
              <input type="file" name="license_scan" accept=".jpg,.jpeg,.png,.pdf">
              <?php if (!empty($existing_docs['license'])): ?>
                <div class="upload-item"><a href="<?php echo htmlspecialchars($existing_docs['license']['file_path']); ?>" target="_blank" rel="noopener">View driving license (raw path)</a></div>
              <?php endif; ?>

              <div class="small" style="margin-top:10px">
                <?php if ((int)($user['verified'] ?? 0) === 1): ?>
                  <div>Current status: <strong style="color:#15803d">Approved ✅</strong></div>
                  <div class="small">Your account is verified. You can post rides now.</div>
                <?php else: ?>
                  <div>Current status: <strong style="color:#d97706">Not Verified</strong></div>
                  <div class="small">Uploaded documents will be reviewed by admin.</div>
                <?php endif; ?>
              </div>
            </div>
          </aside>
        </div>

        <div class="actions" style="margin-top:16px">
          <button type="submit" class="btn">Save profile</button>
        </div>
      </form>

      <!-- Documents visible list for user -->
      <div style="margin-top:22px">
        <h3 style="margin:0 0 8px 0;color:var(--primary-1)">Your Uploaded Documents</h3>
        <div class="small">You can view or download the files you uploaded. Admin may use these to verify your account.</div>

        <?php if (empty($all_docs)): ?>
          <div class="note" style="margin-top:12px">You have not uploaded any documents yet.</div>
        <?php else: ?>
          <div class="docs-grid" role="list">
            <?php foreach ($all_docs as $doc):
              $urls = build_view_and_download_urls($doc);
              $isImage = strpos($doc['mime'] ?? '', 'image/') === 0;
              $isPdf = ($doc['mime'] ?? '') === 'application/pdf';
              $typeLabel = htmlspecialchars(ucfirst(str_replace('_',' ',$doc['type'])));
              $basename = htmlspecialchars(basename($doc['file_path']));
              $sizeHuman = hr_filesize((int)$doc['size_bytes']);
            ?>
              <article class="doc-card" role="listitem" aria-label="<?php echo $typeLabel; ?>">
                <div class="doc-preview" aria-hidden="true">
                  <?php if ($isImage && $urls['view']): ?>
                    <img src="<?php echo htmlspecialchars($urls['view']); ?>" alt="<?php echo $typeLabel; ?>">
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

                  <?php if (!$urls['exists_on_disk']): ?>
                    <div style="color:#a00;margin-top:6px;font-size:13px">Note: file not found on server (<?php echo htmlspecialchars($doc['file_path']); ?>).</div>
                  <?php endif; ?>
                </div>

                <div class="doc-actions">
                  <a class="view" href="<?php echo htmlspecialchars($urls['view'] ?? $urls['download']); ?>" target="_blank" rel="noopener">View</a>
                  <a class="download" href="<?php echo htmlspecialchars($urls['download']); ?>">Download</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ------------------ Admin messages (sent to this user) ------------------ -->
      <div style="margin-top:22px">
        <h3 style="margin:0 0 8px 0;color:var(--primary-1)">Messages From Admin</h3>
        <div class="small">Admin may send you notes about verification — check them here.</div>

        <?php if (empty($admin_messages)): ?>
          <div class="note" style="margin-top:12px">No messages from admin yet.</div>
        <?php else: ?>
          <div style="display:grid;gap:8px;margin-top:12px">
            <?php foreach ($admin_messages as $m): ?>
              <div style="padding:10px;border-radius:8px;border:1px solid rgba(15,23,42,0.04);background:#fff">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                  <div style="font-weight:700"><?php echo htmlspecialchars($m['admin_name'] ?? 'Admin'); ?></div>
                  <div class="small-muted" style="font-size:12px"><?php echo htmlspecialchars($m['created_at']); ?></div>
                </div>
                <div style="margin-top:8px;font-size:14px;color:#23123b"><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
<script>
(function(){
  var pref = document.getElementById('accountPref');
  var vehicleSection = document.getElementById('vehicleSection');
  function toggleVehicle(){
    if (!pref) return;
    if (pref.value === 'driver') vehicleSection.style.display = 'block';
    else vehicleSection.style.display = 'none';
  }
  if (pref) {
    pref.addEventListener('change', toggleVehicle);
    toggleVehicle();
  }
})();
</script>
<div class="footer">&copy; <?= date('Y') ?> HerSafar — Safe, Smart, and Connected Travel</div>
</body>
</html>
