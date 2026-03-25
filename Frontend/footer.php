<?php
?>
<style>
html, body {
  height: 100%;
  margin: 0;
  display: flex;
  flex-direction: column;
}

main {
  flex: 1;
}


footer.site-footer {
  flex-shrink: 0;
  border-radius: 14px 14px 0 0;
  max-width: 1500px;
  width: calc(100% - 40px);
  margin: 0 auto;
  text-align: center;
  padding: 14px 12px;
  font-size: 13px;
  color: #6b4a86;
  backdrop-filter: blur(10px) saturate(160%);
  -webkit-backdrop-filter: blur(10px) saturate(160%);
  background: rgba(255, 255, 255, 0.42);
  border-top: 1px solid rgba(255, 255, 255, 0.25);
  box-shadow: 0 -4px 18px rgba(91, 33, 182, 0.08);
  position: sticky;
  bottom: 0;
}


footer.site-footer a {
  color: #5b21b6;
  text-decoration: none;
  font-weight: 500;
  margin: 0 4px;
  transition: color 0.2s ease;
}
.page-wrapper { min-height:100%; display:flex; flex-direction:column; }
.page-wrapper > main { flex:1 0 auto; }
footer.site-footer { margin-top: auto; /* <-- pushes footer to bottom */ ... }

footer.site-footer a:hover {
  color: #8b5cf6;
  text-decoration: underline;
}

@media (max-width: 768px) {
  footer.site-footer {
    font-size: 12px;
    width: calc(100% - 24px);
    padding: 12px 10px;
  }
}
</style>

<footer class="site-footer" role="contentinfo">
  &copy; <?php echo date('Y'); ?> <strong>HerSafar</strong> — Safe, Smart &amp; Connected Travel •
  <a href="#">Terms</a> •
  <a href="#">Privacy</a>
</footer>
