<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$userName = htmlspecialchars($_SESSION['user']['name'] ?? 'Guest');
$loggedIn = !empty($_SESSION['user']);
?>
<!-- Header + small shared CSS (include once per page) -->
<style>
:root{
  --bg-a:#fbf9ff; --bg-b:#f7f3ff;
  --primary-1:#5b21b6; --primary-2:#8b5cf6; --accent:#c084fc;
  --muted:#6b4a86; --card-bg: rgba(255,255,255,0.78);
  --glass-border: rgba(255,255,255,0.14);
  --radius:14px; --shadow: 0 14px 40px rgba(91,33,182,0.06);
  --text: #261338; --max-width:1100px;
  font-family: 'Poppins', system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
}

/* Reset small layout touches for header fragment */
.site-header, .site-footer { box-sizing: border-box; }

/* Header (glass purple theme) */
header.site-header{
  width: calc(100% - 44px);
  margin: 8px 22px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  padding:12px 16px;
  background: linear-gradient(90deg, rgba(75,23,146,0.78), rgba(109,64,186,0.65), rgba(145,90,220,0.55));
  border-radius:14px;
  box-shadow: 0 12px 28px rgba(75,23,146,0.18);
  backdrop-filter: blur(14px) saturate(150%);
  color:#fff;
  position:sticky;
  top:12px;
  z-index:900;
  transition: transform .18s ease, box-shadow .18s ease;
}

/* Hover lift */
header.site-header:hover{ transform: translateY(-2px); box-shadow: 0 18px 48px rgba(75,23,146,0.22); }

.brand{ display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit; }
.logo{
  width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center;
  font-weight:800; font-size:16px;
  background: linear-gradient(135deg, var(--primary-2), var(--accent));
  box-shadow: 0 8px 22px rgba(91,33,182,0.10); color:#fff;
}
.brand .title{ font-weight:700; font-size:15px; line-height:1; color:white;}
.brand .subtitle{ font-size:11px; opacity:0.92; margin-top:2px; color:white;}

/* Nav */
nav.header-nav{ display:flex; gap:8px; align-items:center; justify-content: center; }
nav.header-nav a{
  color: rgba(255,255,255,0.95);
  text-decoration:none;
  padding:7px 10px;
  border-radius:8px;
  font-weight:600;
  font-size:13px;
  transition: all .15s ease;
  border:1px solid rgba(255,255,255,0.06);
  background:transparent;
  align-items:center;
  justify-content: center;
}
nav.header-nav a:hover{
  background: rgba(255,255,255,0.06);
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}

/* Right area */
.header-right{ display:flex; align-items:center; gap:10px; }
.greeting{ text-align:right; font-size:13px; color:rgba(255,255,255,0.95); line-height:1; }
.greeting .hi{ font-weight:600; font-size:14px; }
.cta-btn{
  background: linear-gradient(90deg,var(--primary-2),var(--primary-1));
  padding:8px 12px; border-radius:8px; color:#fff; font-weight:600; text-decoration:none; font-size:13px;
  box-shadow: 0 8px 22px rgba(75,23,146,0.12);
}
.cta-btn:hover{ transform: translateY(-2px); box-shadow: 0 14px 36px rgba(75,23,146,0.16); }

/* Footer (container styling to match theme + sticky bottom helper) */
footer.site-footer {
  border-radius: 20px 20px 0 0;
  max-width: var(--max-width);
  width: calc(100% - 40px);
  margin: 0 auto;
  text-align: center;
  padding: 14px 12px;
  font-size: 13px;
  color: var(--muted);
  backdrop-filter: blur(10px) saturate(160%);
  -webkit-backdrop-filter: blur(10px) saturate(160%);
  background: rgba(255,255,255,0.9);
  border-top: 1px solid rgba(255,255,255,0.25);
  box-shadow: 0 -4px 18px rgba(91, 33, 182, 0.08);
}

/* Layout helpers (pages that include header/footer should include these once) */
html, body {
  height: 100%;
  margin: 0;
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, var(--bg-a), var(--bg-b));
}
main.container { flex: 1 0 auto; max-width: var(--max-width); margin: 26px auto; padding: 18px; }

/* Responsive tweaks */
@media (max-width:980px){
  header.site-header{ width: calc(100% - 32px); margin:8px 16px; padding:10px; border-radius:12px; }
  nav.header-nav{ display:none; } /* hide full nav on small screens (you can replace with a hamburger later) */
}
</style>

<header class="site-header" role="banner" aria-label="Main header">
  <a class="brand" href="index.php" aria-label="HerSafar home">
    <div class="logo" aria-hidden="true">HS</div>
    <div>
      <div class="title">HerSafar</div>
      <div class="subtitle">Safe. Smart. Shared.</div>
    </div>
  </a>

  <nav class="header-nav" role="navigation" aria-label="Primary">
    <a href="index.php">Home</a>
    <a href="post_ride.php">Post Ride</a>
     <a href="search_results.php">Search Ride</a>
    <a href="groups.php">Community</a>
     <a href="join_ride.php">Join Ride</a>
    <a href="about.php">About</a>
    <a href="contact.php">Contact</a>
  </nav>

  <div class="header-right">
    <?php if ($loggedIn): ?>
      <div class="greeting" title="Account">
        <div class="hi">Hi, <?php echo $userName; ?></div>
      </div>
      <a class="cta-btn" href="dashboard.php" aria-label="Dashboard">Dashboard</a>
    <?php else: ?>
      <div style="font-size:13px;color:rgba(255,255,255,0.92);">Join The HerSafar Experience</div>
      <a class="cta-btn" href="login.php" aria-label="Login">Login</a>
    <?php endif; ?>
  </div>
</header>
