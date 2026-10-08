<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Landing Page (index.php) • v1.0.0.0
 *  Public marketing page, Claude-style. Logged-in users
 *  get an "Open app" button instead of sign-in.
 * ═══════════════════════════════════════════════════════
 */
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
require_once __DIR__ . '/inc/icons.php';

/* is someone already signed in? */
$me = null;
if (isset($_SESSION['devil_uid'])) {
    $uf = __DIR__ . '/data/users.json';
    if (is_readable($uf)) {
        $users = json_decode((string)file_get_contents($uf), true);
        if (is_array($users) && isset($users[$_SESSION['devil_uid']])) {
            $u = $users[$_SESSION['devil_uid']];
            $me = ['name' => (string)($u['name'] ?? 'Devil'), 'email' => (string)($u['email'] ?? '')];
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0709">
<meta name="description" content="Devil AI — a custom-built AI assistant. Sinfully smart, surprisingly helpful. Private accounts, isolated chats, multiple models.">
<script>/* theme boot — runs before paint to avoid a flash of the wrong theme */
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();</script>
<title>Devil AI — Sinfully smart AI assistant</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="manifest" href="manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Devil AI">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0c0709; --bg2:#120b0e; --panel:#171014; --panel2:#1d1216;
  --border:rgba(244,63,94,.18); --border-hi:rgba(244,63,94,.45);
  --red:#e11d48; --red2:#f43f5e; --pink:#fb7185; --soft:#fda4af;
  --text:#efe6ea; --dim:#a8929b; --dim2:#7c5b63;
  --serif:Georgia,'Times New Roman',serif;
  --sans:'Segoe UI',system-ui,-apple-system,Roboto,'Noto Sans',sans-serif;
}
html{scroll-behavior:smooth}
body{background:radial-gradient(1200px 600px at 80% -10%,rgba(225,29,72,.14),transparent 60%),radial-gradient(900px 500px at -10% 110%,rgba(190,18,60,.10),transparent 55%),var(--bg);color:var(--text);font-family:var(--sans);line-height:1.6;overflow-x:hidden}
a{color:inherit;text-decoration:none}
button{font:inherit;cursor:pointer}
img{max-width:100%}
.wrap{max-width:1080px;margin:0 auto;padding:0 24px}

/* ── nav ── */
nav{position:sticky;top:0;z-index:50;background:rgba(12,7,9,.8);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--border)}
.navin{display:flex;align-items:center;gap:22px;padding:14px 0}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1.05rem;letter-spacing:.3px}
.brand img{width:30px;height:30px;filter:drop-shadow(0 0 8px rgba(244,63,94,.5))}
.navlinks{display:flex;gap:20px;font-size:.86rem;color:var(--dim);flex:1}
.navlinks a:hover{color:var(--soft)}
.navbtns{display:flex;gap:10px;align-items:center}
.btn{border:none;border-radius:12px;padding:10px 18px;font-size:.86rem;font-weight:600;transition:.2s;display:inline-flex;align-items:center;gap:8px}
.btn.primary{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 6px 20px rgba(244,63,94,.35)}
.btn.primary:hover{transform:translateY(-1px);filter:brightness(1.08)}
.btn.ghost{background:rgba(244,63,94,.07);border:1px solid var(--border);color:var(--soft)}
.btn.ghost:hover{background:rgba(244,63,94,.16);border-color:var(--border-hi)}
.userchip{display:flex;align-items:center;gap:8px;font-size:.84rem;color:var(--soft)}
.userchip .av{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#f43f5e,#7f1d1d);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.8rem}

/* ── hero ── */
.hero{text-align:center;padding:90px 0 60px;position:relative}
.pill{display:inline-flex;align-items:center;gap:8px;font-size:.74rem;color:var(--soft);border:1px solid var(--border);border-radius:999px;padding:6px 14px;background:rgba(244,63,94,.06);letter-spacing:.4px}
.pill svg{color:var(--pink)}
.hero h1{font-family:var(--serif);font-size:clamp(2.6rem,6vw,4.4rem);line-height:1.12;margin:26px 0 6px;font-weight:500;background:linear-gradient(100deg,#fff 15%,#fda4af 55%,#f43f5e 90%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.hero .sub{color:var(--dim);font-size:1.06rem;max-width:560px;margin:14px auto 0}
.hero .cta{display:flex;gap:14px;justify-content:center;margin-top:34px;flex-wrap:wrap}
.btn.big{padding:14px 26px;font-size:.95rem;border-radius:14px}

/* ── chat mockup ── */
.mock{margin:70px auto 0;max-width:720px;background:var(--panel);border:1px solid var(--border);border-radius:22px;box-shadow:0 30px 80px rgba(0,0,0,.5),0 0 0 1px rgba(244,63,94,.05);overflow:hidden}
.mock-bar{display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid var(--border);background:rgba(0,0,0,.25)}
.mock-bar i{width:11px;height:11px;border-radius:50%;display:block}
.mock-bar .r{background:#f43f5e}.mock-bar .y{background:#f59e0b}.mock-bar .g{background:#10b981}
.mock-bar span{flex:1;text-align:center;font-size:.72rem;color:var(--dim2);letter-spacing:.5px}
.mock-body{padding:26px 26px 18px;text-align:left}
.mk-user{margin-left:auto;max-width:75%;width:fit-content;background:var(--panel2);border:1px solid var(--border);border-radius:16px;border-bottom-right-radius:5px;padding:11px 15px;font-size:.88rem}
.mk-bot{display:flex;gap:11px;margin-top:18px;max-width:88%}
.mk-bot img{width:28px;height:28px;border-radius:50%;flex-shrink:0}
.mk-bot .who{font-size:.72rem;color:var(--dim2);letter-spacing:.3px}
.mk-bot .txt{font-size:.88rem;color:var(--text);margin-top:2px}
.mk-bot .txt b{color:#fff}
.mk-input{margin-top:22px;display:flex;align-items:center;gap:10px;background:var(--panel2);border:1px solid var(--border);border-radius:18px;padding:12px 14px}
.mk-input .model{display:flex;align-items:center;gap:6px;font-size:.72rem;color:var(--soft);border:1px solid var(--border);border-radius:999px;padding:5px 11px;white-space:nowrap}
.mk-input .ph{flex:1;color:var(--dim2);font-size:.84rem}
.mk-input .go{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#f43f5e,#be123c);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}

/* ── sections ── */
section{padding:80px 0}
.sec-head{text-align:center;max-width:620px;margin:0 auto 50px}
.sec-head .kicker{font-size:.74rem;letter-spacing:2px;text-transform:uppercase;color:var(--pink);font-weight:700}
.sec-head h2{font-family:var(--serif);font-size:clamp(1.8rem,4vw,2.6rem);font-weight:500;margin-top:12px}
.sec-head p{color:var(--dim);margin-top:12px;font-size:.95rem}

.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.card{background:var(--panel);border:1px solid var(--border);border-radius:18px;padding:24px;transition:.25s}
.card:hover{border-color:var(--border-hi);transform:translateY(-3px);box-shadow:0 14px 40px rgba(225,29,72,.12)}
.card .ic{width:42px;height:42px;border-radius:12px;background:rgba(244,63,94,.1);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--pink);margin-bottom:16px}
.card h3{font-size:1rem;margin-bottom:6px}
.card p{font-size:.84rem;color:var(--dim);line-height:1.6}

.models{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.mcard{position:relative;background:linear-gradient(160deg,var(--panel),var(--bg2));border:1px solid var(--border);border-radius:20px;padding:26px;overflow:hidden}
.mcard .badge{position:absolute;top:16px;right:16px;font-size:.64rem;letter-spacing:.8px;text-transform:uppercase;color:var(--soft);border:1px solid var(--border);padding:3px 9px;border-radius:999px;background:rgba(244,63,94,.07)}
.mcard .ic{color:var(--pink);margin-bottom:14px}
.mcard h3{font-family:var(--serif);font-size:1.25rem;font-weight:500}
.mcard .tag{color:var(--pink);font-size:.74rem;font-weight:600;letter-spacing:.4px;margin-top:2px}
.mcard p{color:var(--dim);font-size:.84rem;margin-top:10px}

.band{background:linear-gradient(120deg,rgba(244,63,94,.12),rgba(190,18,60,.05));border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.bandin{display:flex;align-items:center;gap:26px;justify-content:space-between;flex-wrap:wrap}
.bandin h2{font-family:var(--serif);font-size:clamp(1.5rem,3.5vw,2.1rem);font-weight:500;max-width:520px}
.bandin p{color:var(--dim);font-size:.92rem;margin-top:8px;max-width:520px}

/* ── footer ── */
footer{padding:50px 0 40px;border-top:1px solid var(--border)}
.fin{display:flex;justify-content:space-between;gap:26px;flex-wrap:wrap;align-items:flex-start}
.fbrand{display:flex;align-items:center;gap:10px;font-weight:700}
.fbrand img{width:26px;height:26px}
.fmeta{font-size:.76rem;color:var(--dim2);margin-top:10px;line-height:1.7}
.flinks{display:flex;gap:22px;font-size:.84rem;color:var(--dim);flex-wrap:wrap}
.flinks a:hover{color:var(--soft)}
.fcopy{margin-top:34px;padding-top:20px;border-top:1px solid var(--border);display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;font-size:.76rem;color:var(--dim2)}

@media (max-width:860px){.grid,.models{grid-template-columns:1fr 1fr}.navlinks{display:none}}
@media (max-width:600px){.grid,.models{grid-template-columns:1fr}.hero{padding:60px 0 40px}.navbtns .btn.ghost.hide-sm{display:none}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}

/* ═══════════ LIGHT THEME (Claude-style warm) ═══════════ */
[data-theme=light]{
  --bg:#faf9f7; --bg2:#f4f2ee; --panel:#ffffff; --panel2:#f0ede9;
  --border:rgba(120,80,90,.18); --border-hi:rgba(190,30,60,.45);
  --red:#e11d48; --red2:#f43f5e; --pink:#c2415f; --soft:#a63d57;
  --text:#262023; --dim:#6e5f65; --dim2:#82696f;
}
[data-theme=light] body{background:radial-gradient(1200px 600px at 80% -10%,rgba(225,29,72,.06),transparent 60%),radial-gradient(900px 500px at -10% 110%,rgba(190,18,60,.05),transparent 55%),var(--bg)}
[data-theme=light] nav{background:rgba(250,249,247,.88)}
[data-theme=light] .hero h1{background:linear-gradient(100deg,#1a1518 15%,#c2415f 55%,#e11d48 90%);-webkit-background-clip:text;background-clip:text}
[data-theme=light] .mock{box-shadow:0 30px 80px rgba(120,80,90,.18),0 0 0 1px rgba(120,80,90,.07)}
[data-theme=light] .mock-bar{background:rgba(120,80,90,.06)}
[data-theme=light] .mk-user{color:#262023}
[data-theme=light] .mk-bot .txt b{color:#141013}
[data-theme=light] .card{box-shadow:none}
[data-theme=light] .card:hover{box-shadow:0 14px 40px rgba(120,80,90,.14)}
</style>
</head>
<body>

<nav>
  <div class="wrap navin">
    <a class="brand" href="index.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
    <div class="navlinks">
      <a href="#features">Features</a>
      <a href="#models">Models</a>
      <a href="#privacy">Privacy</a>
      <a href="cookies.php">Cookies</a>
    </div>
    <div class="navbtns">
      <button class="btn ghost" id="themeBtn" title="Switch theme" style="padding:10px 12px" type="button"><?= icon('sun', 16) ?></button>
      <?php if ($me): ?>
        <span class="userchip"><span class="av"><?= htmlspecialchars(strtoupper(mb_substr($me['name'], 0, 1))) ?></span><?= htmlspecialchars($me['name']) ?></span>
        <a class="btn primary" href="chat">Open app <?= icon('arrow-right', 15) ?></a>
      <?php else: ?>
        <a class="btn ghost hide-sm" href="login.php">Sign in</a>
        <a class="btn primary" href="login.php">Get started <?= icon('arrow-right', 15) ?></a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<header class="hero wrap">
  <span class="pill"><?= icon('flame', 14) ?> Custom-built • Privacy-first • v1.0.0.0</span>
  <h1>Meet Devil AI.<br>Sinfully smart.</h1>
  <p class="sub">A one-of-a-kind AI assistant developed by BlazeNXT. Real answers, real privacy, zero tracking by default.</p>
  <div class="cta">
    <a class="btn primary big" href="<?= $me ? 'chat' : 'login.php' ?>"><?= icon('message', 17) ?> Start chatting</a>
    <a class="btn ghost big" href="#features">See what's inside</a>
  </div>

  <div class="mock" aria-hidden="true">
    <div class="mock-bar"><i class="r"></i><i class="y"></i><i class="g"></i><span>devil-ai — chat</span></div>
    <div class="mock-body">
      <div class="mk-user">Explain quantum computing like I'm five… but make it devilish.</div>
      <div class="mk-bot">
        <img src="assets/logo.svg" alt="">
        <div>
          <div class="who">DEVIL AI · DEVIL PRO</div>
          <div class="txt">Picture the tiniest coin in the universe. Normal computers flip it <b>one way at a time</b> — I flip it <b>every way at once</b>. That's quantum magic, and down here we respect magic.</div>
        </div>
      </div>
      <div class="mk-input">
        <span class="model"><?= icon('sparkles', 13) ?> Devil Pro</span>
        <span class="ph">Message Devil AI…</span>
        <span class="go"><?= icon('arrow-up', 15) ?></span>
      </div>
    </div>
  </div>
</header>

<section id="features">
  <div class="wrap">
    <div class="sec-head">
      <div class="kicker">Features</div>
      <h2>Everything a serious assistant needs</h2>
      <p>Built from scratch for speed, privacy and a clean multi-page experience — no bloat, no middlemen, no surprises.</p>
    </div>
    <div class="grid">
      <div class="card"><div class="ic"><?= icon('users', 20) ?></div><h3>Private accounts</h3><p>Sign in with just your email — a one-time code or magic link, no passwords to remember or leak. Your chats stay isolated.</p></div>
      <div class="card"><div class="ic"><?= icon('chat-group', 20) ?></div><h3>Isolated chats</h3><p>Every conversation is stored separately per account. Rename, revisit or delete your history anytime — you are in full control.</p></div>
      <div class="card"><div class="ic"><?= icon('layers', 20) ?></div><h3>Multiple models</h3><p>Switch between Devil Flash, Pro and Ultra mid-conversation. Pick the right brain for every task — from quick questions to deep work.</p></div>
      <div class="card"><div class="ic"><?= icon('cookie', 20) ?></div><h3>Cookie-first privacy</h3><p>Analytics and personalization are opt-in, never default. Accept all or fine-tune every category — your choice is remembered and respected.</p></div>
      <div class="card"><div class="ic"><?= icon('shield-check', 20) ?></div><h3>Hardened by default</h3><p>Passwordless sign-in, brute-force protection, per-account rate limits and server-side isolation. Security is not an afterthought.</p></div>
      <div class="card"><div class="ic"><?= icon('server', 20) ?></div><h3>Privacy-first &amp; lean</h3><p>No third-party scripts, no ads, no data brokers — just you and your devilishly good assistant.</p></div>
    </div>
  </div>
</section>

<section id="models" style="padding-top:20px">
  <div class="wrap">
    <div class="sec-head">
      <div class="kicker">Models</div>
      <h2>Three brains. One devil.</h2>
      <p>Pick a model from the chat box, exactly when you need it.</p>
    </div>
    <div class="models">
      <div class="mcard"><span class="badge">Fastest</span><div class="ic"><?= icon('zap', 26) ?></div><h3>Devil Flash</h3><div class="tag">Everyday questions</div><p>Snappy answers for chat, quick lookups and light tasks. Zero setup, always available.</p></div>
      <div class="mcard"><span class="badge">Balanced</span><div class="ic"><?= icon('sparkles', 26) ?></div><h3>Devil Pro</h3><div class="tag">Deeper thinking</div><p>Richer, more thoughtful responses for writing, coding, analysis and long conversations.</p></div>
      <div class="mcard"><span class="badge">Max power</span><div class="ic"><?= icon('crown', 26) ?></div><h3>Devil Ultra</h3><div class="tag">Heavy lifting</div><p>The strongest brain in hell — for complex reasoning, big documents and hard problems.</p></div>
    </div>
  </div>
</section>

<section id="privacy" class="band">
  <div class="wrap bandin">
    <div>
      <h2>Your chats belong to you. Full stop.</h2>
      <p>Conversations live only on this server, tied to your account. Nothing is sold, shared or used for advertising — and cookies stay opt-in.</p>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <a class="btn ghost" href="cookies.php"><?= icon('cookie', 16) ?> Cookie Policy</a>
      <a class="btn primary" href="<?= $me ? 'chat' : 'login.php' ?>">Create free account</a>
    </div>
  </div>
</section>

<footer>
  <div class="wrap">
    <div class="fin">
      <div>
        <div class="fbrand"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</div>
        <div class="fmeta">Custom-built AI assistant.<br>Developed by <a href="https://www.blazenxt.in" target="_blank" rel="noopener">BlazeNXT</a>.</div>
      </div>
      <div class="flinks">
        <a href="#features">Features</a>
        <a href="#models">Models</a>
        <a href="cookies.php">Cookie Policy</a>
        <a href="#" onclick="devilOpenCookies&&devilOpenCookies();return false">Cookie settings</a>
        <a href="login.php">Sign in</a>
      </div>
    </div>
    <div class="fcopy">
      <span>Devil AI v1.0.0.0</span>
      <span>Developed by <a href="https://www.blazenxt.in" target="_blank" rel="noopener">BlazeNXT</a></span>
    </div>
  </div>
</footer>

<script>
/* ── theme toggle (respects the personalization cookie choice) ── */
(function () {
  var SUN = <?= json_encode(icon('sun', 16)) ?>;
  var MOON = <?= json_encode(icon('moon', 16)) ?>;
  var b = document.getElementById('themeBtn');
  function cur() { return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'; }
  function setIco() { b.innerHTML = cur() === 'dark' ? SUN : MOON; }
  b.addEventListener('click', function () {
    var t = cur() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', t);
    try {
      var prefs = JSON.parse(localStorage.getItem('devil_cookie_prefs') || 'null');
      if (!prefs || prefs.personalization !== false) { localStorage.setItem('devil_theme', t); if(window.devilCookieSet){window.devilCookieSet('devil_theme',t,365);}else{document.cookie='devil_theme='+encodeURIComponent(t)+'; Max-Age=31536000; Path=/devil-ai/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');} }
    } catch (e) {}
    setIco();
  });
  setIco();
})();
</script>

<?php require __DIR__ . '/inc/cookiebar.php'; ?>
<script>
if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); }); }
</script>
</body>
</html>
