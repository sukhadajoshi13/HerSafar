<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';       
require_once 'functions.php';  

if (session_status() === PHP_SESSION_NONE) session_start();
$userName = htmlspecialchars($_SESSION['user']['name'] ?? 'Guest');
$loggedIn = !empty($_SESSION['user']);
$uid = (int)($_SESSION['user']['id'] ?? 0);

$errors = [];
$success = '';
$posted = false;
$csrf = csrf_token();

// simple server-side handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = true;
    // verify CSRF
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid form submission (CSRF).';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($name === '' || $email === '' || $message === '') {
            $errors[] = 'Please fill in name, email and message.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        } else {
            // prepare insert (no foreign key to avoid FK errors)
            if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
                $errors[] = 'Database connection not available.';
            } else {
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $stmt = $mysqli->prepare("INSERT INTO contact_messages (user_id, name, email, message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                if (!$stmt) {
                    $errors[] = 'Database prepare failed: ' . $mysqli->error;
                } else {
                    $stmt->bind_param('issss', $uid, $name, $email, $message, $ip);
                    if ($stmt->execute()) {
                        $success = 'Thank you — your message has been received. We will respond shortly.';
                        // clear POST fields
                        $_POST = [];
                    } else {
                        $errors[] = 'Failed to save message: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// contact details - edit these as needed
$contact_email = 'hersafarenquiry@gmail.com';
$contact_phone = '+91 7558401837';
$contact_address = 'HerSafar , BVCOEW, Katraj, Pune';
$map_lat = '18.459113';
$map_lng = '73.855169';
$map_label = 'HerSafar';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Contact — HerSafar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg-a:#fbf9ff;
      --bg-b:#f7f3ff;
      --primary-1:#5b21b6;
      --primary-2:#8b5cf6;
      --accent:#c084fc;
      --muted:#6b4a86;
      --card-bg: rgba(255,255,255,0.78);
      --glass-border: rgba(255,255,255,0.14);
      --radius:12px;
      --shadow: 0 14px 40px rgba(91,33,182,0.06);
      --text: #261338;
      font-family: 'Poppins', system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      --max-width:1100px;
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

    .grid{
      display:grid;
      grid-template-columns: 1fr 420px;
      gap:18px;
      align-items:start;
    }

    /* left column (form) */
    .card{
      background: var(--card-bg);
      border-radius:12px;
      padding:16px;
      border:1px solid var(--glass-border);
      box-shadow: var(--shadow);
      color:var(--text);
    }

    h1,h2{ margin:0; color:var(--primary-1); }
    h1{ font-size:20px; }
    p.lead{ margin-top:8px; color:var(--muted); font-size:13px; line-height:1.45; }

    label.small{ display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:var(--primary-1); }

    form.contact-form{ display:grid; gap:10px; }
    input[type="text"], input[type="email"], textarea{
      width:100%;
      padding:10px 12px;
      border-radius:8px;
      border:1px solid rgba(99,102,241,0.08);
      font-size:14px;
      background:rgba(255,255,255,0.96);
      color:#23123b;
      outline:none;
    }
    textarea{ min-height:120px; resize:vertical; }

    input:focus, textarea:focus{
      box-shadow: 0 10px 28px rgba(109,40,217,0.06);
      border-color: rgba(139,92,246,0.2);
    }

    .actions{ display:flex; gap:10px; margin-top:8px; align-items:center; }
    button.btn{
      border:0;
      padding:9px 12px;
      border-radius:8px;
      font-weight:600;
      cursor:pointer;
      font-size:14px;
    }
    button.btn.primary{
      background: linear-gradient(90deg,var(--primary-2),var(--primary-1));
      color:#fff;
      box-shadow: 0 10px 26px rgba(75,23,146,0.12);
    }
    button.btn.ghost{
      background:transparent;
      border:1px solid rgba(99,102,241,0.08);
      color:var(--primary-1);
    }

    /* right column (details + map) */
    .info{
      display:flex;
      flex-direction:column;
      gap:12px;
    }
    .info .box{
      background:#fff;
      border-radius:10px;
      padding:12px;
      border:1px solid #f3e8ff;
      box-shadow: 0 8px 24px rgba(145,90,220,0.03);
      font-size:14px;
    }
    .meta-row{ display:flex; gap:10px; align-items:center; font-size:13px; color:var(--muted); }

    .map{
      width:100%;
      height:220px;
      border-radius:10px;
      overflow:hidden;
      border:1px solid rgba(15,23,42,0.03);
    }

    .msg-success{ background:#ecfdf5; color:#065f46; padding:10px; border-radius:8px; border:1px solid #bbf7d0; }
    .msg-error{ background:#fff1f2; color:#7f1d1d; padding:10px; border-radius:8px; border:1px solid #fecaca; }

    @media (max-width:980px){
      header.site-header{ width: calc(100% - 32px); margin:8px 16px; padding:10px; border-radius:12px; }
      .grid{ grid-template-columns: 1fr; }
      .map{ height:200px; }
      main.container{ padding:12px; margin-top:12px; }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

  <main class="container" role="main">
    <div class="grid">
      <!-- LEFT: form -->
      <div class="card" aria-labelledby="contact-title">
        <h1 id="contact-title">Contact HerSafar</h1>
        <p class="lead">Have questions, feedback, or partnership ideas? Send us a message — we'll reply as soon as possible.</p>

        <?php if (!empty($errors)): ?>
          <div class="msg-error" role="alert">
            <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="msg-success" role="status"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form class="contact-form" method="post" action="">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

          <div>
            <label class="small">Your name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ($_SESSION['user']['name'] ?? '')); ?>">
          </div>

          <div>
            <label class="small">Email address</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ($_SESSION['user']['email'] ?? '')); ?>">
          </div>

          <div>
            <label class="small">Message</label>
            <textarea name="message" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
          </div>

          <div class="actions">
            <button type="submit" class="btn primary">Send message</button>
            <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="btn ghost" style="text-decoration:none;">Email Us</a>
          </div>
        </form>
      </div>

      <!-- RIGHT: info + map -->
      <aside class="info">
        <div class="box">
          <h2 style="font-size:15px;margin:0 0 8px 0;color:var(--primary-1)">Contact details</h2>
          <div class="meta-row"><strong>Email:</strong>&nbsp; <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>"><?php echo htmlspecialchars($contact_email); ?></a></div>
          <div class="meta-row"><strong>Phone:</strong>&nbsp; <?php echo htmlspecialchars($contact_phone); ?></div>
          <div class="meta-row"><strong>Address:</strong>&nbsp; <?php echo htmlspecialchars($contact_address); ?></div>
        </div>

        <div class="box">
          <h3 style="font-size:14px;margin:0 0 8px 0;color:var(--primary-1)">Find us</h3>
          <div class="map" role="img" aria-label="Map location">
            <!-- Google Maps embed; you can change the q parameter or use place ID -->
            <iframe
              width="100%" height="100%" frameborder="0" style="border:0"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              src="https://www.google.com/maps?q=<?php echo rawurlencode($map_lat . ',' . $map_lng . ' (' . $map_label . ')'); ?>&output=embed">
            </iframe>
          </div>
        </div>

        <div class="box">
          <h3 style="font-size:14px;margin:0 0 8px 0;color:var(--primary-1)">Office hours</h3>
          <div style="font-size:13px;color:var(--muted)">Mon — Fri: 10:00 — 18:00<br>Sat: 10:00 — 14:00<br>Sun: Closed</div>
        </div>
      </aside>
    </div>
  </main>
  <?php include 'footer.php'; ?>
</body>
</html>
