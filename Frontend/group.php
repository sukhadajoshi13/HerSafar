<?php
require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$uid = (int)($_SESSION['user']['id'] ?? 0);
$group_id = (int)($_GET['id'] ?? 0);
if ($group_id <= 0) { set_flash('error','Invalid group id.'); header('Location: dashboard.php'); exit; }

// load group
$stmt = $mysqli->prepare("SELECT g.*, u.name AS owner_name FROM groups g JOIN users u ON g.owner_id = u.id WHERE g.id = ? LIMIT 1");
$stmt->bind_param('i', $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$group) { set_flash('error','Group not found.'); header('Location: dashboard.php'); exit; }

// membership check
$stmt = $mysqli->prepare("SELECT id, role FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $group_id, $uid);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();
$is_member = (bool)$me;
$is_owner = ($uid === (int)$group['owner_id']);

// handle actions
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf($_POST['csrf'] ?? '')) {
    $errors[] = 'Invalid CSRF token.';
  } else {
    if (!empty($_POST['action']) && $_POST['action'] === 'remove_member' && $is_owner) {
      $remove_uid = (int)$_POST['user_id'];
      if ($remove_uid === $uid) $errors[] = 'Owner cannot remove yourself.';
      else {
        $stmt = $mysqli->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $group_id, $remove_uid);
        if ($stmt->execute()) set_flash('success','Member removed.');
        else $errors[] = 'Failed to remove member.';
        $stmt->close();
        header('Location: group.php?id=' . $group_id); exit;
      }
    }
    if (!empty($_POST['action']) && $_POST['action'] === 'leave' && $is_member && !$is_owner) {
      $stmt = $mysqli->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
      $stmt->bind_param('ii', $group_id, $uid);
      if ($stmt->execute()) { set_flash('success','You left the group.'); header('Location: dashboard.php'); exit; }
      else $errors[] = 'Failed to leave group.';
      $stmt->close();
    }
  }
}

// load members
$stmt = $mysqli->prepare("SELECT gm.user_id, gm.role, u.name, u.email, u.phone, gm.joined_at FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? ORDER BY gm.role DESC, u.name ASC");
$stmt->bind_param('i', $group_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf = csrf_token();
$flash = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo e($group['name']); ?> — Hersafar Group</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg-a:#fbf9ff; --bg-b:#f7f3ff;
  --primary-1:#5b21b6; --primary-2:#8b5cf6;
  --muted:#6b4a86; --accent:#a855f7;
  --radius:12px; --shadow:0 18px 50px rgba(91,33,182,0.06);
  font-family:'Poppins',sans-serif;
}
body{margin:0;min-height:100vh;background:linear-gradient(180deg,var(--bg-a),var(--bg-b));color:#241235;padding:20px;}
.wrap{max-width:980px;margin:auto;}
.card{background:#fff;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
.header{display:flex;justify-content:space-between;align-items:center;}
.title{font-size:20px;color:var(--primary-1);}
.sub{font-size:13px;color:var(--muted);}
.back-btn{padding:5px 10px;border:1px solid rgba(0,0,0,0.08);border-radius:8px;background:#fff;color:#241235;text-decoration:none;font-size:13px;font-weight:600;}
.copy-btn{padding:8px 10px;border-radius:8px;border:none;background:#ede9fe;color:#4c1d95;font-weight:700;cursor:pointer;}
.copy-btn:hover{background:#c4b5fd;color:#fff;}
.share-row{display:flex;gap:8px;align-items:center;}
.share-input{flex:1;padding:10px;border:1px solid #e6e9f2;border-radius:8px;background:#fafafa;font-size:13px;}
table{width:100%;border-collapse:collapse;margin-top:16px;}
th,td{padding:10px 12px;text-align:left;font-size:14px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
th{background:#f3f0ff;color:#3b0764;font-weight:600;}
tr:hover{background:#fafaff;}
tr.owner-row{background:#f3e8ff!important;}
.role-badge{padding:6px 10px;border-radius:999px;font-size:13px;font-weight:700;}
.role-owner{background:#eef2ff;color:#5b21b6;}
.role-admin{background:#ecfdf5;color:#157a43;}
.role-member{background:#f3f4f6;color:#374151;}
.flash-success{background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px;margin:10px 0;}
.flash-error{background:#fff1f2;color:#9b1c1c;padding:10px;border-radius:8px;margin:10px 0;}
.small{font-size:13px;color:var(--muted);}
.actions{margin-top:10px;}
.btn.ghost{background:#fff;border:1px solid rgba(15,23,42,0.06);color:#241235;border-radius:8px;padding:8px 12px;font-weight:600;cursor:pointer;}
</style>
</head>
<body>
<div class="wrap">

  <div style="margin-bottom:12px;">
    <a class="back-btn" href="groups.php">← Back to Groups</a>
  </div>

  <div class="card">
    <div class="header">
      <div>
        <h1 class="title"><?php echo e($group['name']); ?></h1>
        <div class="sub">Created by <?php echo e($group['owner_name']); ?> • <?php echo e(date('M j, Y', strtotime($group['created_at']))); ?></div>
      </div>
      <div class="small"><?php echo $is_owner ? 'Owner' : ($is_member ? 'Member' : 'Not Joined'); ?></div>
    </div>

    <?php if ($group['description']): ?>
      <p class="small" style="margin-top:8px;"><?php echo nl2br(e($group['description'])); ?></p>
    <?php endif; ?>

    <?php if ($flash): ?>
      <div class="<?php echo $flash['type']==='success'?'flash-success':'flash-error'; ?>"><?php echo e($flash['text']); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="flash-error"><?php foreach($errors as $e) echo '<div>'.e($e).'</div>'; ?></div>
    <?php endif; ?>

    <div class="section">
      <h3 style="margin-bottom:6px;">Group Actions</h3>
      <div style="background:#f9f8ff;border-radius:10px;padding:12px;">
        <?php if ($is_owner): ?>
          <div style="font-weight:700;margin-bottom:6px">Shareable join link</div>
          <?php
            $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $link = $proto . '://' . $host . '/join_group.php?token=' . e($group['join_token']);
          ?>
          <div class="share-row">
            <input id="joinLink" class="share-input" readonly value="<?php echo e($link); ?>">
            <button id="copyBtn" class="copy-btn">Copy</button>
          </div>
          <div id="copyStatus" class="small" style="height:18px;"></div>
        <?php elseif (!$is_member): ?>
          <div class="small">You’re not a member. Join using the link below:</div>
          <div class="actions"><a href="join_group.php?token=<?php echo e($group['join_token']); ?>" class="btn ghost">Join Group</a></div>
        <?php else: ?>
          <div class="small">You can leave the group anytime:</div>
          <form method="POST" class="actions" onsubmit="return confirm('Leave this group?');">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="action" value="leave">
            <button type="submit" class="btn ghost">Leave Group</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="section">
      <h3 style="margin:18px 0 8px;">Members (<?php echo count($members); ?>)</h3>
      <?php if (empty($members)): ?>
        <p class="small muted">No members yet.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Contact</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($members as $m): 
              $isOwnerRow = ((int)$m['user_id'] === (int)$group['owner_id']);
            ?>
            <tr class="<?php echo $isOwnerRow ? 'owner-row' : ''; ?>">
              <td style="<?php echo $isOwnerRow ? 'font-weight:700;color:#5b21b6;' : ''; ?>">
                <?php echo e($m['name']); ?> 
                <?php if ($isOwnerRow): ?><span style="font-size:12px;background:#ddd6fe;color:#4c1d95;padding:2px 6px;border-radius:6px;margin-left:6px;">Owner</span><?php endif; ?>
              </td>
              <td class="small"><?php echo e($m['email']); ?><?php if ($m['phone']) echo ' / '.e($m['phone']); ?></td>
              <td><span class="role-badge <?php
                echo $m['role']==='owner'?'role-owner':($m['role']==='admin'?'role-admin':'role-member');
              ?>"><?php echo ucfirst($m['role']); ?></span></td>
              <td class="small"><?php echo e(date('M j, Y', strtotime($m['joined_at']))); ?></td>
              <td>
                <?php if ($is_owner && $m['user_id'] != $uid): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="remove_member">
                    <input type="hidden" name="user_id" value="<?php echo (int)$m['user_id']; ?>">
                    <button class="btn ghost" type="submit">Remove</button>
                  </form>
                <?php elseif ($m['user_id'] == $uid): ?>
                  <span class="small muted">You</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const copyBtn = document.getElementById('copyBtn');
const joinLink = document.getElementById('joinLink');
const copyStatus = document.getElementById('copyStatus');
if(copyBtn){
  copyBtn.addEventListener('click', ()=>{
    navigator.clipboard.writeText(joinLink.value).then(()=>{
      copyStatus.textContent = 'Copied!';
      setTimeout(()=>copyStatus.textContent='',2000);
    });
  });
}
</script>
</body>
</html>
