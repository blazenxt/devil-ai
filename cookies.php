<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Cookie Policy (cookies.php) • v1.0.0.0
 * ═══════════════════════════════════════════════════════
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/inc/icons.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0709">
<script>/* theme boot — runs before paint to avoid a flash of the wrong theme */
(function(){var t=null;try{t=localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();</script>
<title>Cookie Policy — Devil AI</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0c0709; --panel:#171014; --panel2:#1d1216;
  --border:rgba(244,63,94,.18); --border-hi:rgba(244,63,94,.45);
  --red:#e11d48; --red2:#f43f5e; --pink:#fb7185; --soft:#fda4af;
  --text:#efe6ea; --dim:#a8929b; --dim2:#7c5b63;
  --serif:Georgia,'Times New Roman',serif;
  --sans:'Segoe UI',system-ui,-apple-system,Roboto,'Noto Sans',sans-serif;
}
body{background:radial-gradient(1100px 500px at 80% -10%,rgba(225,29,72,.13),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans);line-height:1.65}
a{text-decoration:none;color:inherit}
button{font:inherit;cursor:pointer}
.top{position:sticky;top:0;z-index:40;background:rgba(12,7,9,.85);backdrop-filter:blur(12px);border-bottom:1px solid var(--border)}
.topin{max-width:840px;margin:0 auto;padding:14px 22px;display:flex;justify-content:space-between;align-items:center}
.brand{display:flex;align-items:center;gap:9px;font-weight:700}
.brand img{width:28px;height:28px;filter:drop-shadow(0 0 8px rgba(244,63,94,.5))}
.btn{border:none;border-radius:11px;padding:9px 16px;font-size:.84rem;font-weight:600;display:inline-flex;align-items:center;gap:8px;transition:.2s}
.btn.ghost{background:rgba(244,63,94,.07);border:1px solid var(--border);color:var(--soft)}
.btn.ghost:hover{background:rgba(244,63,94,.16)}
main{max-width:840px;margin:0 auto;padding:50px 22px 80px}
h1{font-family:var(--serif);font-weight:500;font-size:2.3rem}
.meta{color:var(--dim2);font-size:.8rem;margin-top:8px}
h2{font-family:var(--serif);font-weight:500;font-size:1.35rem;margin:42px 0 12px;padding-top:6px}
h2 .num{color:var(--pink);font-family:var(--sans);font-size:.85rem;font-weight:700;letter-spacing:1.5px;display:block;margin-bottom:4px}
p{color:var(--dim);font-size:.92rem;margin:10px 0}
p b,li b{color:var(--text)}
ul{margin:12px 0 12px 20px;color:var(--dim);font-size:.92rem}
li{margin:6px 0}
table{width:100%;border-collapse:collapse;margin:16px 0;font-size:.84rem}
th,td{border:1px solid var(--border);padding:10px 12px;text-align:left;vertical-align:top}
th{background:rgba(244,63,94,.07);color:var(--soft);font-size:.76rem;letter-spacing:.4px;text-transform:uppercase}
.tablewrap{overflow-x:auto;-webkit-overflow-scrolling:touch;margin:14px 0}
.tablewrap table{min-width:640px}
td{color:var(--dim)}
td b{color:var(--text)}
code{background:var(--panel2);border:1px solid var(--border);color:var(--soft);padding:2px 7px;border-radius:6px;font-size:.8em;font-family:ui-monospace,Consolas,monospace}
.note{background:rgba(244,63,94,.06);border:1px solid var(--border);border-radius:14px;padding:16px 18px;margin:22px 0;display:flex;gap:12px;align-items:flex-start}
.note svg{color:var(--pink);flex-shrink:0;margin-top:2px}
.note p{margin:0;font-size:.86rem}
@media (max-width:600px){h1{font-size:1.7rem}main{padding:34px 18px 60px}}

/* ═══════════ LIGHT THEME (Claude-style warm) ═══════════ */
[data-theme=light]{
  --bg:#faf9f7; --panel:#ffffff; --panel2:#f0ede9;
  --border:rgba(120,80,90,.18); --border-hi:rgba(190,30,60,.45);
  --red:#e11d48; --red2:#f43f5e; --pink:#c2415f; --soft:#a63d57;
  --text:#262023; --dim:#6e5f65; --dim2:#82696f;
}
[data-theme=light] body{background:radial-gradient(1100px 500px at 80% -10%,rgba(225,29,72,.06),transparent 60%),var(--bg)}
[data-theme=light] .top{background:rgba(250,249,247,.9)}
[data-theme=light] th{background:rgba(190,30,60,.06)}
</style>
</head>
<body>

<div class="top"><div class="topin">
  <a class="brand" href="index.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
  <div style="display:flex;gap:10px">
    <button class="btn ghost" id="themeBtn" title="Switch theme" style="padding:9px 11px" type="button"><?= icon('sun', 15) ?></button>
    <a class="btn ghost" href="#" onclick="devilOpenCookies&&devilOpenCookies();return false"><?= icon('settings', 15) ?> Cookie settings</a>
    <a class="btn ghost" href="index.php"><?= icon('chevron-right', 14) ?> Home</a>
  </div>
</div></div>

<main>
  <h1>Cookie Policy</h1>
  <p class="meta">Last updated: October 2026 • Applies to this Devil AI installation (v1.0.0.0)</p>

  <p>This policy explains how Devil AI uses cookies and similar technologies, and how you can control them. We keep it short and honest — like everything else around here.</p>

  <div class="note"><?= icon('shield-check', 20) ?><p><b>The short version:</b> essential cookies keep you signed in. Analytics and personalization cookies are <b>strictly opt-in</b> — they stay off until you explicitly enable them. You can change your choice at any time via the "Cookie settings" link in the footer.</p></div>

  <h2><span class="num">1</span>What are cookies?</h2>
  <p>Cookies are small text files that a website stores in your browser. They let a site remember things between page loads — for example, that you are signed in, or which preferences you chose. They cannot read your files or install anything on your device.</p>

  <h2><span class="num">2</span>The cookies we use</h2>
  <div class="tablewrap">
  <table>
    <tr><th>Category</th><th>Cookie</th><th>Purpose</th><th>Duration</th><th>Consent</th></tr>
    <tr><td><b>Essential</b></td><td><code>PHPSESSID</code></td><td>Keeps you signed in to your account and protects against session hijacking (HTTP-only).</td><td>Session</td><td>Always on — required</td></tr>
    <tr><td><b>Essential</b></td><td><code>devil_cookies</code></td><td>Remembers your cookie choices so we stop asking on every visit.</td><td>1 year</td><td>Always on — required</td></tr>
    <tr><td><b>Analytics</b> (optional)</td><td><code>devil_analytics</code></td><td>Anonymous, aggregated usage counters that help improve the service.</td><td>1 year</td><td>Opt-in only</td></tr>
    <tr><td><b>Personalization</b> (optional)</td><td><code>devil_personal</code></td><td>Remembers UI preferences such as your last selected model and sidebar state.</td><td>1 year</td><td>Opt-in only</td></tr>
  </table>
  </div>
  <p><b>We do not use advertising cookies, third-party trackers, or fingerprinting.</b> There are no external scripts on this site — no ad networks, no trackers, no data brokers.</p>

  <h2><span class="num">3</span>Managing your preferences</h2>
  <ul>
    <li><b>Cookie banner:</b> shown on your first visit — "Accept all" or "Manage cookies" with per-category toggles.</li>
    <li><b>Cookie settings:</b> available anytime from the footer of any page.</li>
    <li><b>Browser settings:</b> you can also block or delete cookies in your browser. Blocking essential cookies will sign you out and break the app.</li>
  </ul>
  <p>When you decline optional categories, we actively delete any leftover optional cookies — declining is a real action here, not a theater.</p>

  <h2><span class="num">4</span>Local storage</h2>
  <p>Your cookie choices are additionally stored in the browser's local storage (key <code>devil_cookie_prefs</code>) so the preference survives even if cookies are cleared by aggressive browser settings. Clearing site data in your browser removes it.</p>

  <h2><span class="num">5</span>Your data &amp; your rights</h2>
  <ul>
    <li>There are no account passwords at all — sign-in works via one-time email codes, so there is nothing to leak.</li>
    <li>Your chats are stored on this server, isolated per account, and can be deleted by you at any time from the app.</li>
    <li>To request deletion of your entire account, use the delete option in the app or contact the site owner.</li>
  </ul>

  <h2><span class="num">6</span>Changes to this policy</h2>
  <p>If we ever change how cookies are used, we will update this page and the banner will ask for your choices again.</p>

  <p style="margin-top:44px">Questions? The owner of this server is the data controller — reach out directly.</p>
</main>

<script>
/* ── theme toggle (respects the personalization cookie choice) ── */
(function () {
  var SUN = <?= json_encode(icon('sun', 15)) ?>;
  var MOON = <?= json_encode(icon('moon', 15)) ?>;
  var b = document.getElementById('themeBtn');
  function cur() { return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'; }
  function setIco() { b.innerHTML = cur() === 'dark' ? SUN : MOON; }
  b.addEventListener('click', function () {
    var t = cur() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', t);
    try {
      var prefs = JSON.parse(localStorage.getItem('devil_cookie_prefs') || 'null');
      if (!prefs || prefs.personalization !== false) { localStorage.setItem('devil_theme', t); }
    } catch (e) {}
    setIco();
  });
  setIco();
})();
</script>

<?php require __DIR__ . '/inc/cookiebar.php'; ?>
</body>
</html>
