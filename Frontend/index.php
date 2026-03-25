<?php
// index.php — HerSafar Landing Page 
include 'header.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>HerSafar — Share the Ride, Share the Care</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root{
      --primary: #4c0f7a;
      --accent: #7c3aed;
      --muted: #6b4a86;
      --bg-top: #fbf9ff;
      --bg-mid: #f3e8ff;
      --bg-bottom: #ffffff;
      --card-shadow: 0 14px 36px rgba(16,24,40,0.06);
      --glass-border: rgba(0,0,0,0.04);
      --radius: 14px;
      --section-gap: 32px;
      font-family: 'Poppins', system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    }

    /* ==== GLOBAL RESET + BACKGROUND FIX ==== */
    * { box-sizing: border-box; }
    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
      color: var(--primary);
      background: linear-gradient(180deg, var(--bg-top) 0%, var(--bg-mid) 45%, var(--bg-bottom) 100%) fixed;
      background-repeat: no-repeat;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    main.container {
      width: min(1180px, calc(100% - 40px));
      margin: 28px auto;
      min-height: calc(100vh - 156px);
    }

    .section { margin-bottom: var(--section-gap); }
    .center { text-align: center; }

    /* ==== WHITE CARD STYLE ==== */
    .card {
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--card-shadow);
      border: 1px solid var(--glass-border);
    }

    /* ==== HERO ==== */
    .hero {
      display: flex;
      gap: 20px;
      align-items: center;
      justify-content: space-between;
      padding: 28px;
    }
    .hero-left { flex: 1; padding-right: 12px; }
    .kicker {
      display: inline-block;
      font-size: 12px;
      font-weight: 700;
      color: var(--accent);
      background: rgba(124,58,237,0.06);
      padding: 6px 10px;
      border-radius: 999px;
      margin-bottom: 10px;
    }
    .hero-title {
      margin: 6px 0 8px;
      font-size: 30px;
      font-weight: 800;
      color: var(--primary);
      line-height: 1.1;
    }
    .hero-sub { margin: 0; font-size: 15px; color: var(--muted); max-width: 58ch; }
    .hero-right { flex: 0 0 360px; display:flex; align-items:center; justify-content:center; }
    .hero-right img { width: 320px; border-radius: 12px; object-fit: cover; box-shadow: 0 10px 28px rgba(76,15,122,0.08); }

    /* ==== MARQUEE ==== */
    .marquee {
      width: 100%;
      border-radius: 10px;
      overflow: hidden;
      background: linear-gradient(90deg, #c084fc, #a78bfa);
      color: #fff;
      font-weight: 600;
      padding: 8px 12px;
      margin-bottom: 20px;
    }
    .marquee .marq { white-space: nowrap; display: inline-block; animation: scroll 50s linear infinite; }
    @keyframes scroll { from { transform: translateX(100%); } to { transform: translateX(-100%); } }

    /* ==== QUICK SEARCH ==== */
    .quick-search-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
      padding: 18px;
    }
    .qs-title { font-size: 18px; font-weight: 700; margin: 0; color: var(--primary); text-align: center; }
    .qs-sub { font-size: 13px; color: var(--muted); margin: 0; text-align: center; }

    .quick-search {
      width: 100%;
      display: flex;
      gap: 12px;
      align-items: center;
      margin-top: 8px;
      flex-wrap: wrap;
    }
    .qs-col { flex: 1; min-width: 150px; display: flex; flex-direction: column; gap: 6px; }
    .qs-label { font-size: 13px; color: var(--muted); font-weight: 600; }
    .qs-input {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(15,23,42,0.06);
      font-size: 14px;
      outline: none;
    }
    .qs-input:focus {
      box-shadow: 0 10px 28px rgba(124,58,237,0.06);
      border-color: rgba(124,58,237,0.14);
    }

    .qs-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-start;
      align-items: center;
      padding-left: 20px;
      margin-top: 8px;
      min-width: 220px;
    }
    .btn-primary {
      background: linear-gradient(90deg, #8b5cf6, #6d28d9);
      color: #fff;
      border: none;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(109, 40, 217, 0.15);
      transition: all 0.2s ease;
    }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(109,40,217,0.22); }
    .btn-clear {
      background: #fff;
      border: 1px solid rgba(109,40,217,0.15);
      color: #6d28d9;
      padding: 7px 12px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .btn-clear:hover { background: rgba(139,92,246,0.08); border-color: rgba(109,40,217,0.25); }

    /* ==== FEATURES ==== */
    .features { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
    .feature-card { padding: 16px; }
    .feature-card h4 { margin: 0; font-size: 15px; color: var(--primary); }
    .feature-card p { margin: 0; font-size: 13px; color: var(--muted); }

    /* ==== ABOUT + CONTACT ==== */
    .split-row { display: flex; gap: 16px; flex-wrap: wrap; }
    .split-left, .split-right { padding: 18px; flex: 1; }
    .split-left h3, .split-right h3 { margin: 0 0 8px; color: var(--primary); }
    .split-left p, .split-right p { margin: 0; color: var(--muted); line-height: 1.6; }

    .section-sep { height: var(--section-gap); }

    /* ==== RESPONSIVE ==== */
    @media (max-width: 1100px) {
      .features { grid-template-columns: repeat(2, 1fr); }
      .hero-right img { width: 260px; }
    }
    @media (max-width: 720px) {
      .hero { flex-direction: column; padding: 20px; text-align: center; }
     .hero-right img {
  width: 100%;
  max-width: 500px;   /* increased from 360px → 420px */
  margin-left: 25px;  /* pushes it a little right */
  border-radius: 12px;
  object-fit: cover;
  box-shadow: 0 10px 28px rgba(76,15,122,0.08);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.hero-right img:hover {
  transform: scale(1.03); /* small hover effect for life */
  box-shadow: 0 12px 36px rgba(76,15,122,0.12);
}

      .quick-search { flex-direction: column; align-items: stretch; }
      .qs-actions { width: 100%; padding-left: 0; justify-content: center; }
      .features { grid-template-columns: 1fr; }
    }
    .spacer { height:22px; }
    .spacerx { height:10px; }
  </style>
</head>
<body>
  <main class="container" role="main">

    <!-- HERO SECTION -->
    <section class="section">
      <div class="card hero" role="region" aria-label="Hero">
        <div class="hero-left">
          <div class="kicker">Community-First Women Carpooling</div>
          <h1 class="hero-title">Travel Together, Travel Safer — HerSafar</h1>
          <p class="hero-sub">A women-first carpool community focused on safety, savings and trusted local groups. Post rides, join groups, and coordinate daily commutes with ease.</p>
        </div>
        <div class="hero-right" aria-hidden="true">
          <img src="img/main.jpeg" alt="Women carpooling together">
        </div>
      </div>
    </section>

    <!-- MARQUEE -->
    <div class="marquee" role="note" aria-live="polite">
      <span class="marq">🌸 HerSafar — Empowering women to travel together - Build trusted travel circles - Share rides, save time, and create connections - Together we move forward 🌸</span>
    </div>
<div class="spacerx" aria-hidden="true"></div>
    <!-- SEARCH SECTION -->
    <section class="section">
      <div class="card quick-search-wrap">
        <div class="center">
          <h2 class="qs-title">Search Best Ride</h2>
          <p class="qs-sub">Quickly find rides near you — enter origin, destination, date and (optional) time.</p>
        </div>

        <form class="quick-search" action="search_results.php" method="get" novalidate>
          <div class="qs-col">
            <label class="qs-label" for="from">From</label>
            <input class="qs-input" id="from" name="from" type="text" placeholder="City, area or landmark" required>
          </div>

          <div class="qs-col">
            <label class="qs-label" for="to">To</label>
            <input class="qs-input" id="to" name="to" type="text" placeholder="Destination" required>
          </div>

          <div class="qs-col">
            <label class="qs-label" for="date">Date</label>
            <input class="qs-input" id="date" name="date" type="date">
          </div>

          <div class="qs-col">
            <label class="qs-label" for="time">Time</label>
            <input class="qs-input" id="time" name="time" type="time">
          </div>

          <div class="qs-actions">
            <button class="btn-primary" type="submit">Search</button>
            <a class="btn-clear" href="search_results.php">Clear</a>
          </div>
        </form>
      </div>
    </section>
     <div class="spacer" aria-hidden="true"></div>
     <div class="spacer" aria-hidden="true"></div>

    <!-- WHY HER SAFAR -->
    <section class="section" aria-label="Why HerSafar">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;flex-wrap:wrap;">
        <h3 style="margin:0;color:var(--primary);font-weight:700;">Why HerSafar</h3>
        <p style="margin:0;color:var(--muted);">Practical benefits for everyday commuters — safe, social and sustainable.</p>
      </div>
     <div class="spacer" aria-hidden="true"></div>
      <div class="features">
        <div class="card feature-card"><h4>Verified Drivers</h4><p>Driver documents and admin checks to keep rides safe and reliable.</p></div>
        <div class="card feature-card"><h4>Group Communities</h4><p>Create/join local groups for consistent shared commutes (office, college, colony).</p></div>
        <div class="card feature-card"><h4>Easy Booking</h4><p>Quick seat requests and confirmations with clear pickup points.</p></div>
        <div class="card feature-card"><h4>Share Links</h4><p>Invite friends to a ride using shareable links and tokens.</p></div>
        <div class="card feature-card"><h4>Flexible Pricing</h4><p>Drivers set fair seat contributions — costs split transparently among riders.</p></div>
        <div class="card feature-card"><h4>Ratings & Feedback</h4><p>Build trust through reviews, ratings and verified profiles.</p></div>
        <div class="card feature-card"><h4>In-app Chat</h4><p>Coordinate pickup, route and time using quick messages inside the app.</p></div>
        <div class="card feature-card"><h4>Safety Tools</h4><p>Emergency contact info, ride tracking and admin support when needed.</p></div>
      </div>
    </section>

     <div class="spacer" aria-hidden="true"></div>
    <!-- ABOUT + CONTACT -->
    <section class="section">
      <div class="split-row">
        <div class="card split-left">
          <h3>About HerSafar</h3>
          <p>HerSafar is a women-first carpool community designed to make daily travel safer, more affordable and friendlier. We connect verified drivers and riders, encourage local commuting groups and make every shared ride simple and trustworthy.</p>
        </div>

        <div class="card split-right">
          <h3>Contact Us</h3>
          <p>Questions, feedback or partnership ideas? <strong style="color:var(--accent);">Get in touch!</strong> — we respond quickly during office hours.</p>
          <div style="margin-top:10px; font-size:14px; color:var(--muted); line-height:1.8;">
            <div><strong style="color:var(--primary);">📧 Email:</strong> <a href="mailto:support@hersafar.com" style="color:var(--accent); text-decoration:none;">support@hersafar.com</a></div>
            <div><strong style="color:var(--primary);">📞 Phone:</strong> +91 75584 01837</div>
            <div><strong style="color:var(--primary);">🕒 Hours:</strong> Mon–Fri: 10:00 AM – 6:00 PM <br> Sat: 10:00 AM – 2:00 PM <br> Sun: Closed</div>
          </div>
        </div>
      </div>
    </section>

  </main>

<?php include 'footer.php'; ?>

<script>
(function(){
  const dateEl = document.getElementById('date');
  const timeEl = document.getElementById('time');
  if (!dateEl) return;

  const today = new Date();
  const y = today.getFullYear();
  const m = String(today.getMonth()+1).padStart(2,'0');
  const d = String(today.getDate()).padStart(2,'0');
  const todayStr = `${y}-${m}-${d}`;
  dateEl.setAttribute('min', todayStr);

  function addMinutes(mins){
    const n = new Date();
    n.setMinutes(n.getMinutes()+mins);
    return String(n.getHours()).padStart(2,'0') + ':' + String(n.getMinutes()).padStart(2,'0');
  }

  dateEl.addEventListener('change', function(){
    if (!timeEl) return;
    if (dateEl.value === todayStr) timeEl.setAttribute('min', addMinutes(15));
    else timeEl.removeAttribute('min');
  });

  if (timeEl && dateEl.value === todayStr) timeEl.setAttribute('min', addMinutes(15));
})();
</script>
</body>
</html>
