<?php
require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$uid = (int)$_SESSION['user']['id'];
$group_id = (int)($_GET['group_id'] ?? 0);
if ($group_id <= 0) { header('Location: dashboard.php'); exit; }

// check membership
$stmt = $mysqli->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $group_id, $uid);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$me) {
  $_SESSION['msg']=['type'=>'error','text'=>'You must be a member to view this chat.'];
  header('Location: group.php?id=' . $group_id);
  exit;
}

// load group
$stmt = $mysqli->prepare("SELECT * FROM groups WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

// load messages
$stmt = $mysqli->prepare("SELECT gm.*, u.name FROM group_messages gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? ORDER BY gm.posted_at ASC LIMIT 500");
$stmt->bind_param('i', $group_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($group['name']); ?> — Group Chat</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --primary-1:#5b21b6;
  --primary-2:#8b5cf6;
  --bg-a:#fbf9ff;
  --bg-b:#f7f3ff;
  --muted:#6b4a86;
  --you:#ede9fe;
  --radius:12px;
  --shadow:0 10px 30px rgba(91,33,182,0.08);
  font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
}
* { box-sizing:border-box; }

body {
  margin:0;
  background:linear-gradient(180deg,var(--bg-a),var(--bg-b));
  color:#241235;
  padding:20px;
  min-height:100vh;
  display:flex;
  justify-content:center;
  align-items:flex-start;
}
.container {
  width:100%;
  max-width:900px;
}
.back-btn {
  text-decoration:none;
  color:#241235;
  font-size:13px;
  border:1px solid rgba(0,0,0,0.08);
  padding:6px 10px;
  border-radius:8px;
  background:#fff;
  transition:all .2s;
  display:inline-block;
  margin-bottom:12px;
}
.back-btn:hover { background:#f3e8ff; color:var(--primary-1); }

.chat-wrap {
  width:100%;
  background:#fff;
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  display:flex;
  flex-direction:column;
  height:85vh;
  overflow:hidden;
}

.header {
  padding:14px 20px;
  border-bottom:1px solid rgba(0,0,0,0.05);
  text-align:center;
  background:linear-gradient(90deg,#faf5ff,#f5f3ff);
}
.header h2 {
  margin:0;
  font-size:18px;
  color:var(--primary-1);
  font-weight:700;
  letter-spacing:0.3px;
}

.chat-box {
  flex:1;
  overflow-y:auto;
  padding:20px;
  background:#fafaff;
  display:flex;
  flex-direction:column;
  scroll-behavior:smooth;
}
.message {
  margin-bottom:14px;
  max-width:70%;
  padding:10px 14px;
  border-radius:var(--radius);
  line-height:1.4;
  box-shadow:0 2px 5px rgba(0,0,0,0.04);
  word-wrap:break-word;
}
.message.you {
  align-self:flex-end;
  background:var(--you);
  color:#241235;
}
.message.other {
  align-self:flex-start;
  background:#fff;
  border:1px solid rgba(0,0,0,0.05);
}
.message strong {
  font-weight:600;
  display:block;
  margin-bottom:3px;
}
.timestamp {
  font-size:11px;
  color:var(--muted);
  margin-top:4px;
  text-align:right;
}
.input-box {
  padding:14px 20px;
  border-top:1px solid rgba(0,0,0,0.06);
  background:#fff;
  display:flex;
  flex-direction:column;
  gap:10px;
}
textarea {
  resize:none;
  width:100%;
  padding:10px;
  font-family:'Poppins';
  font-size:14px;
  border:1px solid #e5e7eb;
  border-radius:8px;
  min-height:60px;
  outline:none;
}
textarea:focus {
  border-color:#a78bfa;
  box-shadow:0 0 0 3px rgba(167,139,250,0.25);
}
.send-btn {
  align-self:flex-end;
  padding:10px 18px;
  background:linear-gradient(90deg,var(--primary-2),var(--primary-1));
  border:none;
  color:#fff;
  font-weight:600;
  border-radius:8px;
  cursor:pointer;
  transition:opacity .2s, transform .15s;
}
.send-btn:hover { opacity:0.9; transform:translateY(-2px); }

.empty {
  text-align:center;
  color:var(--muted);
  font-size:14px;
  margin-top:40px;
}
</style>
</head>
<body>
<div class="container">
  <!-- Back button OUTSIDE -->
  <a href="group.php?id=<?php echo $group_id; ?>" class="back-btn">← Back to Group</a>

  <div class="chat-wrap">
    <div class="header">
      <h2><?php echo htmlspecialchars($group['name']); ?> — Group Chat</h2>
    </div>

    <div class="chat-box" id="chatBox">
      <?php if (empty($messages)): ?>
        <div class="empty">No messages yet. Start the conversation!</div>
      <?php else: ?>
        <?php foreach($messages as $m): ?>
          <div class="message <?php echo ($m['user_id'] == $uid) ? 'you' : 'other'; ?>">
            <?php if ($m['user_id'] != $uid): ?>
              <strong><?php echo htmlspecialchars($m['name']); ?></strong>
            <?php endif; ?>
            <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
            <div class="timestamp"><?php echo date('M j, g:i A', strtotime($m['posted_at'])); ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <form method="POST" action="post_group_message.php" class="input-box">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
      <textarea name="message" placeholder="Type your message..." required></textarea>
      <button type="submit" class="send-btn">Send</button>
    </form>
  </div>
</div>

<script>
// Auto-scroll to bottom when chat opens
const chatBox = document.getElementById('chatBox');
if(chatBox){ chatBox.scrollTop = chatBox.scrollHeight; }
</script>
</body>
</html>
