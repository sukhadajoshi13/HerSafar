<?php
// about_us.php — About Us page for HerSafar (fixed alignment)
if (session_status() === PHP_SESSION_NONE) session_start();
$userName = htmlspecialchars($_SESSION['user']['name'] ?? 'Guest');
$loggedIn = !empty($_SESSION['user']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>About Us — HerSafar</title>
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
      --gutter:22px; /* keep gutters consistent with header */
    }

    *{box-sizing:border-box}
    html,body{height:100%;margin:0}
    body{
      min-height:100vh;
      /* page background is still visible outside the container */
      background:
        radial-gradient(700px 300px at 8% 8%, rgba(139,92,246,0.03), transparent 12%),
        radial-gradient(600px 260px at 92% 92%, rgba(192,132,252,0.02), transparent 12%),
        linear-gradient(180deg,var(--bg-a),var(--bg-b));
      color:var(--text);
      -webkit-font-smoothing:antialiased;
      /* remove global padding — container/header will carry consistent gutters */
      padding: 0;
    }

    /* header assumed to have margin: 8px var(--gutter) */
    /* main container aligned with header gutters so they feel connected */
    main.container{
      width: calc(100% - (var(--gutter) * 2)); /* full width minus header gutters */
      max-width: var(--max-width);
      margin: 26px var(--gutter);                /* same left/right gutters as header */
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap:18px;
      box-sizing: border-box;
    }

    /* make .hero occupy full container width visually (no extra centering) */
    .hero{
      width: 100%;
      display:flex;
      gap:18px;
      align-items:center;
      padding:18px;
      border-radius:12px;
      background: var(--card-bg);
      border:1px solid var(--glass-border);
      box-shadow: var(--shadow);
      box-sizing: border-box;
    }
    .hero .left{ flex:1; }
    .hero h1{ margin:0;font-size:20px;color:var(--primary-1); }
    .hero p.lead{ margin-top:6px;color:var(--muted); font-size:13px; max-width:72ch; line-height:1.45; }

    /* content grid */
    .grid-3{
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap:14px;
      width:100%;
    }
    .panel{
      background:#fff;border-radius:10px;padding:14px;border:1px solid #f3e8ff;box-shadow:0 8px 24px rgba(145,90,220,0.03);
    }
    .panel h3{ margin:0 0 8px 0; color:var(--primary-1); font-size:15px; }
    .panel p{ margin:0;color:var(--muted); font-size:13px; line-height:1.45; }

    .team{ display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-top:8px; }
    .member{
      background:linear-gradient(180deg,#fff,#fff); border-radius:10px; padding:12px; border:1px solid #f3e8ff; text-align:center;
      box-shadow: 0 8px 20px rgba(145,90,220,0.03);
    }
    .avatar{ width:64px;height:64px;border-radius:10px;margin:0 auto; display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;background:linear-gradient(135deg,var(--primary-2),var(--accent)); font-size:18px; }
    .member h4{ margin:8px 0 4px 0; font-size:14px; color:#241235; }
    .member p.role{ margin:0;color:var(--muted); font-size:13px; }

    .how{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:12px;
      margin-top:10px;
      width:100%;
    }
    .how .box{ background: var(--card-bg); padding:12px; border-radius:10px; border:1px solid var(--glass-border); box-shadow: var(--shadow); font-size:13px; color:var(--muted); }

    /* footer sticky logic and container alignment */
    html, body {
      height: 100%;
      margin: 0;
      display: flex;
      flex-direction: column;
    }
    main.container { flex: 1 0 auto; }
    footer.site-footer {
      flex-shrink: 0;
      border-radius: 20px 20px 0 0;
      max-width: var(--max-width);
      width: calc(100% - (var(--gutter) * 2));
      margin: 0 var(--gutter);
      text-align: center;
      padding: 14px 12px;
      font-size: 13px;
      color: var(--muted);
      backdrop-filter: blur(10px) saturate(160%);
      -webkit-backdrop-filter: blur(10px) saturate(160%);
      background: rgba(255, 255, 255, 0.9);
      border-top: 1px solid rgba(255, 255, 255, 0.25);
      box-shadow: 0 -4px 18px rgba(91, 33, 182, 0.08);
    }
    footer.site-footer a { color: var(--primary-1); text-decoration:none; font-weight:500; margin:0 6px; }
    footer.site-footer a:hover { color: var(--primary-2); text-decoration:underline; }

    /* responsive tweaks */
    @media (max-width: 980px){
      /* match header responsive gutters if header reduces margins too */
      main.container{ margin: 16px 16px; padding:12px; width: calc(100% - 32px); }
      .hero{flex-direction:column; align-items:flex-start}
      .grid-3{ grid-template-columns:1fr; }
      .how{ grid-template-columns:1fr; }
      footer.site-footer { margin: 0 16px; width: calc(100% - 32px); }
    }
  </style>
</head>
<body>
<?php include 'header.php'; ?>
  <main class="container" role="main">
    <section class="hero" aria-labelledby="about-hero">
      <div class="left">
        <div style="font-size:12px;color:var(--primary-2);font-weight:700;margin-bottom:6px;">Community-first Carpooling</div>
        <h1 id="about-hero">About HerSafar</h1>
        <p class="lead">HerSafar is a women-first community carpool platform created to make daily travel safer, affordable and friendlier. We connect verified drivers and riders, encourage local groups, and focus on building trusted commuting communities.</p>

        <div style="margin-top:12px; display:flex; gap:8px;">
          <a href="search_results.php" class="cta-btn" style="font-size:13px;padding:8px 10px;">Find A Ride</a>
          <a href="post_ride.php" class="cta-btn" style="font-size:13px;padding:8px 10px;">Share A Ride</a>
        </div>
      </div>

          <div style="
      width:260px;               /* wider container */
      flex:0 0 260px;">
      <img
        src="https://img.freepik.com/vetores-gratis/pagina-de-destino-do-conceito-de-brainstorming_52683-26979.jpg"
        alt="Community carpooling illustration"
        loading="lazy"
        style="
          width:100%;
          height:150px;          /* taller for better visibility */
          object-fit:cover;
          border-radius:14px;    /* smoother rounded corners */
          display:block;
          box-shadow:0 10px 26px rgba(91,33,182,0.10);
          border:1px solid rgba(255,255,255,0.3);
        "
      >
    </div>


    </section>

    <section class="grid-3" aria-label="Our mission and features">
      <div class="panel">
        <h3>Our mission</h3>
        <p>To empower safer, greener and more social travel by enabling community-driven carpooling that works for everyday life — especially for women and neighbourhood groups.</p>
      </div>

      <div class="panel">
        <h3>What we do</h3>
        <p>We verify drivers, enable group communities for repeated commutes, provide simple booking and messaging, and help users find trustworthy travel partners quickly.</p>
      </div>

      <div class="panel">
        <h3>Trust & Safety</h3>
        <p>Driver verification, document uploads, in-app messaging and user feedback form the core of our safety process, so you can ride with confidence.</p>
      </div>
    </section>

    <section style="margin-top:6px">
      <h2 style="margin:0 0 8px 0;color:var(--primary-1);font-size:16px;">How it works</h2>
      <div class="how">
        <div class="box">
          <strong>1. Create or join a group</strong>
          <div style="margin-top:6px;color:var(--muted)">Find local groups (neighbourhood, office, college) to plan shared commutes.</div>
        </div>
        <div class="box">
          <strong>2. Post or request rides</strong>
          <div style="margin-top:6px;color:var(--muted)">Drivers post rides with seats & pricing; riders request seats and confirm with the driver.</div>
        </div>
        <div class="box">
          <strong>3. Verify & review</strong>
          <div style="margin-top:6px;color:var(--muted)">Drivers upload documents for admin review; riders use ratings and messages to choose safely.</div>
        </div>
        <div class="box">
          <strong>4. Travel together</strong>
          <div style="margin-top:6px;color:var(--muted)">Coordinate via in-app messages, meet at safe pickup points and enjoy a shared ride.</div>
        </div>
      </div>
    </section>
  </main>

<?php include 'footer.php'; ?>
</body>
</html>
