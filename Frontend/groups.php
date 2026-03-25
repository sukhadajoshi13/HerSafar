<?php
require_once 'dbcon.php';
require_once 'functions.php';
require_login();

if (session_status() === PHP_SESSION_NONE) session_start();

$uid = (int)($_SESSION['user']['id'] ?? 0);
$name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');

// load groups the user belongs to
$my_groups = [];
$stmt = $mysqli->prepare("
  SELECT g.id, g.name, g.description, g.join_token, g.created_at, u.name AS owner_name
  FROM groups g
  JOIN group_members gm ON gm.group_id = g.id
  JOIN users u ON g.owner_id = u.id
  WHERE gm.user_id = ?
  ORDER BY g.created_at DESC
  LIMIT 50
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
$my_groups = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$preview_group_id = $my_groups[0]['id'] ?? 0;
$preview_group_name = $my_groups[0]['name'] ?? '';

$group_messages = [];
if ($preview_group_id) {
    $stmt = $mysqli->prepare("SELECT gm.*, u.name FROM group_messages gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? ORDER BY gm.posted_at DESC LIMIT 20");
    $stmt->bind_param('i', $preview_group_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $msgs = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $group_messages = array_reverse($msgs);
}

$csrf = csrf_token();
$flash = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Groups — HerSafar</title>
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
      --radius:12px;
      --shadow:0 18px 50px rgba(91,33,182,0.08);
      --ease:cubic-bezier(.16,.84,.33,1);
      font-family:'Poppins',system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
      color-scheme: light;
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
      font-family: 'Poppins', system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    }

    /* SIDEBAR (kept exact size for other pages) */
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

/* logo container - fixed size to match the rest of sidebar */
.logo-circle {
  width:46px;
  height:46px;
  border-radius:12px;
  background: white;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
  flex-shrink:0;
}

/* image inside logo */
.logo-circle img {
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}

/* fallback initials when image fails */
.logo-initials {
  width:100%;
  height:100%;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight:700;
  color:#9333ea;
  background:transparent;
  font-size:16px;
}

/* brand text */
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

/* make logout point to the normal Hersafar login path */
.logout { margin-top:12px; }
.logout a { display:block; padding:10px 12px; border-radius:10px; background: rgba(255,255,255,0.95); color:#a855f7; text-decoration:none; font-weight:700; text-align:center; border:1px solid rgba(255,255,255,0.3); }
.logout a:hover{ background:#f3e8ff; color:#6b21b6; }

.sidebar .logged-user{margin-top:16px;font-size:13px;color:#fff;opacity:0.95;text-align:center}

    /* MAIN */
    .main{flex:1;display:flex;flex-direction:column;gap:12px}
    .card{
      width:100%;
      background: linear-gradient(180deg, rgba(255,255,255,0.62), rgba(255,255,255,0.44));
      border:1px solid var(--glass-border);
      border-radius:var(--radius);
      padding:22px;
      box-shadow:var(--shadow);
      backdrop-filter: blur(10px) saturate(120%);
    }
    .header-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
    .logo{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,var(--primary-2),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:18px}
    .h-title{font-size:20px;margin:0;color:var(--primary-1)}
    .h-sub{margin:0;color:var(--muted);font-size:13px}

    .muted{color:var(--muted);font-size:13px}
    .small{font-size:13px;color:var(--muted)}

    .row { display:flex; gap:10px; align-items:center; flex-wrap:wrap }
    /* buttons in header right (smaller, neat) */
    .header-actions { display:flex; gap:8px; align-items:center; }
    .btn {
      padding:10px 14px;border-radius:10px;border:0;background:linear-gradient(90deg,var(--primary-2),var(--primary-1));color:#fff;font-weight:700;cursor:pointer;
      box-shadow:0 8px 20px rgba(91,33,182,0.08); font-size:13px;
    }
    .btn.secondary { background: transparent; color: var(--primary-1); border:1px solid rgba(139,92,246,0.12); box-shadow:none; }

    .ghost {
      padding:8px 12px;border-radius:10px;border:1px solid rgba(99,102,241,0.08);background:rgba(255,255,255,0.95);color:#23123b;font-weight:700;cursor:pointer;font-size:13px;
    }

    .group-list{list-style:none;padding:0;margin:12px 0 0 0;display:grid;gap:10px}
    .group-item{padding:12px;border-radius:10px;border:1px solid rgba(15,23,42,0.04);background:linear-gradient(180deg,#fff,#fbfbff)}
    .group-item h4{margin:0;font-size:15px}
    .group-item .meta{font-size:13px;color:var(--muted);margin-top:6px}
    .group-actions{display:flex;gap:8px;margin-top:10px}

    .chat-box{max-height:320px;overflow:auto;border-radius:10px;padding:12px;border:1px solid rgba(15,23,42,0.04);background:#fbfdff}
    .chat-msg{margin-bottom:12px}
    .chat-msg .meta{font-size:12px;color:var(--muted)}
    .chat-msg .text{margin-top:6px;white-space:pre-wrap}

    input[type="text"], textarea, select { padding:10px;border-radius:8px;border:1px solid rgba(99,102,241,0.06);font-size:14px;width:100% }
    textarea{resize:vertical}

    .footer {
      position: fixed;
      right: 16px;
      bottom: 10px;
      z-index: 999;
      font-size: 12px;
      color: var(--muted);
      background: rgba(255,255,255,0.6);
      padding: 6px 10px;
      border-radius: 8px;
      border: 1px solid rgba(99,102,241,0.06);
      box-shadow: 0 6px 18px rgba(16,24,40,0.04);
      backdrop-filter: blur(6px) saturate(120%);
      -webkit-backdrop-filter: blur(6px) saturate(120%);
      pointer-events: none;
      opacity: 0.95;
    }
    .footer a { pointer-events: auto; color: inherit; text-decoration: none; font-weight:600 }

    @media(max-width:980px){
      body{flex-direction:column;padding:12px}
      .sidebar{width:100%;flex-direction:row;align-items:center;gap:10px;padding:12px}
      .nav{flex-wrap:wrap}
      .nav a{font-size:12px;padding:8px 10px}
      .main{width:100%}
      .header-actions { order: 2; margin-top:8px; }
    }
  </style>
</head>
<body>
  <aside class="sidebar" role="navigation" aria-label="Sidebar">
    <div class="brand">
      <div class="logo-circle" aria-hidden="true">
        <!-- Use absolute path from webroot. Rename file to logoc.png in /image for best results. -->
        <img src="image/logoc.png" alt="Hersafar logo" onerror="this.style.display='none';document.getElementById('logoInitials').style.display='flex'">
        <div id="logoInitials" class="logo-initials" style="display:none">HS</div>
      </div>

      <div>
        <div class="brand-text">HerSafar</div>
        <div class="brand-sub">User Dashboard</div>
      </div>
    </div>

    <nav class="nav" aria-label="Main navigation">
      <a href="dashboard.php">Dashboard</a>
      <a href="index.php">Home</a>
      <a href="profile.php">Profile</a>
      <a href="groups.php" class="active">Groups</a>
      <a href="manage_rides.php">Manage Bookings</a>
      <a href="join_ride.php">Join A Ride</a>
      <a href="apply_driver.php">Driver Applications</a>
    </nav>

    <div class="spacer"></div>

    <div class="sidebar-section">
      <div class="logged-user">Signed in as<br><strong><?php echo htmlspecialchars($name); ?></strong></div>
    </div>

    <div style="margin-top:14px" class="bottom-links">
      <a href="change_password.php">Change Password</a>
      <!-- changed to site-level login path as requested -->
      <a href="/hersafar/login.php">Logout</a>
    </div>
  </aside>

  <main class="main" aria-labelledby="groups-title">
    <div class="card">
      <div class="header-row">
        <div style="display:flex;align-items:center;gap:12px">
          <div>
            <h2 class="h-title" id="groups-title">Community — Groups</h2>
            <div class="h-sub">Create, join and chat in groups you are part of.</div>
          </div>
        </div>

        <!-- Buttons moved neatly to the right (smaller, professional) -->
        <div class="header-actions" role="toolbar" aria-label="Group actions">
          <button class="btn" onclick="location.href='create_group.php'">Create Group</button>
          <button class="btn secondary" onclick="location.href='manage_groups.php'">Manage Groups</button>
        </div>
      </div>

      <?php if ($flash): ?>
        <div style="margin-top:12px;padding:10px;border-radius:8px;background:<?php echo $flash['type']==='success' ? '#ecfdf5' : '#fff1f2'; ?>;border:1px solid <?php echo $flash['type']==='success' ? 'rgba(6,95,70,0.08)' : 'rgba(185,28,58,0.06)'; ?>;color:<?php echo $flash['type']==='success' ? '#065f46' : '#9b1c1c'; ?>;margin-bottom:12px">
          <?php echo htmlspecialchars($flash['text']); ?>
        </div>
      <?php endif; ?>

      <div class="content-grid" style="margin-top:8px; display:grid; grid-template-columns: 1fr 420px; gap:18px;">
        <section class="groups-panel" aria-labelledby="my-groups-title">
          <h3 id="my-groups-title">My Groups <span class="small" style="margin-left:8px;color:var(--muted);font-weight:500">(<?php echo count($my_groups); ?>)</span></h3>

          <?php if (empty($my_groups)): ?>
            <div class="muted" style="margin-top:12px">You are not a member of any group yet. Create one to get started.</div>
          <?php else: ?>
            <ul class="group-list" aria-live="polite">
              <?php foreach($my_groups as $g): ?>
                <li class="group-item" data-id="<?php echo (int)$g['id']; ?>">
                  <div class="top" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                    <div style="flex:1;min-width:0">
                      <h4><?php echo htmlspecialchars($g['name']); ?></h4>
                      <?php if (!empty($g['description'])): ?>
                        <div class="meta"><?php echo htmlspecialchars($g['description']); ?></div>
                      <?php endif; ?>
                      <div class="meta" style="margin-top:6px">Owner: <?php echo htmlspecialchars($g['owner_name']); ?></div>
                    </div>
                    <div style="text-align:right;min-width:120px">
                      <div class="small"><?php echo htmlspecialchars(date('M j, Y', strtotime($g['created_at']))); ?></div>
                      <div class="group-actions" style="margin-top:8px;display:flex;flex-direction:column;gap:8px">
                        <button class="ghost previewBtn" data-group="<?php echo (int)$g['id']; ?>">Preview</button>
                        <a class="ghost" href="group.php?id=<?php echo (int)$g['id']; ?>">Open</a>
                      </div>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <aside class="chat-panel" aria-labelledby="chat-preview-title">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px">
            <div>
              <h3 id="chat-preview-title">Group Chat Preview</h3>
              <div class="chat-meta small">Showing latest messages for: <strong id="previewTitle"><?php echo htmlspecialchars($preview_group_name ?: '—'); ?></strong></div>
            </div>
            <div class="small" style="color:var(--muted)"><?php if ($preview_group_id) echo 'Group ID: ' . (int)$preview_group_id; ?></div>
          </div>

          <div class="chat-box" id="chatBox" role="log" aria-live="polite" aria-atomic="false">
            <?php if (!$preview_group_id): ?>
              <div class="muted">Join or create a group to see chat here.</div>
            <?php else: ?>
              <?php if (empty($group_messages)): ?>
                <div class="muted">No messages yet in this group.</div>
              <?php else: ?>
                <?php foreach($group_messages as $m): ?>
                  <div class="chat-msg">
                    <div class="meta"><?php echo htmlspecialchars($m['name']); ?> • <?php echo htmlspecialchars($m['posted_at']); ?></div>
                    <div class="text"><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <form id="quickChatForm" method="POST" action="post_group_message.php" class="chat-form" style="margin-top:10px;display:flex;flex-direction:column;gap:8px">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="group_id" id="quick_group_id" value="<?php echo (int)$preview_group_id; ?>">
            <textarea name="message" id="quick_message" rows="3" placeholder="Write a quick message to the group..." aria-label="Message" style="padding:10px;border-radius:8px;border:1px solid rgba(99,102,241,0.06);"></textarea>

            <div class="chat-form-bottom" style="display:flex;gap:8px;align-items:center">
              <button class="btn" type="submit">Send</button>

              <select id="groupSelect" style="padding:8px;border-radius:8px;border:1px solid rgba(99,102,241,0.06);margin-left:8px;">
                <?php foreach($my_groups as $g): ?>
                  <option value="<?php echo (int)$g['id']; ?>" <?php echo ($g['id'] == $preview_group_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                <?php endforeach; ?>
              </select>

              <a class="small" id="openChatLink" href="group_chat.php?group_id=<?php echo (int)$preview_group_id; ?>" style="margin-left:auto">Open full chat</a>
            </div>
          </form>
        </aside>
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px">
        <div class="card" style="flex:1;min-width:320px;padding:16px">
          <h4 style="margin-top:0">Join a group</h4>
          <form method="POST" action="join_group.php" style="display:flex;gap:8px;align-items:center;margin-top:8px">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <input name="token" type="text" placeholder="Paste join token / link token here" style="flex:1;padding:10px;border-radius:8px;border:1px solid rgba(99,102,241,0.06)" />
            <button class="btn" type="submit">Join Group</button>
          </form>
          <div class="small muted" style="margin-top:8px">Or ask the group owner to send the shareable link.</div>
        </div>
      </div>

    </div>
  </main>

<script>
(function(){
  // Preview button behavior — fetch preview via endpoint group_preview.php?group_id=ID
  var previewButtons = document.querySelectorAll('.previewBtn');
  var chatBox = document.getElementById('chatBox');
  var previewTitle = document.getElementById('previewTitle');
  var groupSelect = document.getElementById('groupSelect');
  var quickGroupId = document.getElementById('quick_group_id');
  var openChatLink = document.getElementById('openChatLink');

  previewButtons.forEach(function(btn){
    btn.addEventListener('click', function(){
      var gid = this.getAttribute('data-group');
      if (!gid) return;
      if (groupSelect) {
        groupSelect.value = gid;
        quickGroupId.value = gid;
        openChatLink.href = 'group_chat.php?group_id=' + gid;
      }
      chatBox.innerHTML = '<div class="muted">Loading messages…</div>';
      fetch('group_preview.php?group_id=' + encodeURIComponent(gid), { credentials: 'same-origin' })
        .then(function(res){ if (!res.ok) throw res; return res.json(); })
        .then(function(json){
          if (!json.success) { chatBox.innerHTML = '<div class="muted">No messages or failed to load.</div>'; return; }
          previewTitle.textContent = json.group_name || 'Group';
          chatBox.innerHTML = '';
          if (!json.messages || !json.messages.length) {
            chatBox.innerHTML = '<div class="muted">No messages yet in this group.</div>';
            return;
          }
          json.messages.forEach(function(m){
            var d = document.createElement('div'); d.className = 'chat-msg';
            var meta = document.createElement('div'); meta.className = 'meta'; meta.textContent = m.name + ' • ' + m.posted_at;
            var text = document.createElement('div'); text.className = 'text'; text.textContent = m.message;
            d.appendChild(meta); d.appendChild(text); chatBox.appendChild(d);
          });
          chatBox.scrollTop = chatBox.scrollHeight;
        })
        .catch(function(){ chatBox.innerHTML = '<div class="muted">Failed to load messages.</div>'; });
    });
  });

  if (groupSelect) {
    groupSelect.addEventListener('change', function(){
      var gid = this.value;
      quickGroupId.value = gid;
      openChatLink.href = 'group_chat.php?group_id=' + gid;
    });
  }

  // quick send: basic inline check
  var quickForm = document.getElementById('quickChatForm');
  if (quickForm) {
    quickForm.addEventListener('submit', function(e){
      var txt = document.getElementById('quick_message').value.trim();
      if (!txt) { e.preventDefault(); alert('Enter a message'); return false; }
    });
  }

})();
</script>
<div class="footer">&copy; <?= date('Y') ?> HerSafar — Safe, Smart, and Connected Travel</div>
</body>
</html>
