<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Sign in / Create account (login.php) • v1.0.0.0
 *  Already signed in → straight to the app.
 * ═══════════════════════════════════════════════════════
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
if (isset($_SESSION['devil_uid'])) { header('Location: app.php'); exit; }
require_once __DIR__ . '/inc/icons.php';
$mode = (isset($_GET['mode']) && $_GET['mode'] === 'register') ? 'register' : 'login';
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
<title><?= $mode === 'register' ? 'Create your account' : 'Sign in' ?> — Devil AI</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0c0709; --panel:#171014; --panel2:#1d1216;
  --border:rgba(244,63,94,.18); --border-hi:rgba(244,63,94,.5);
  --red:#e11d48; --red2:#f43f5e; --pink:#fb7185; --soft:#fda4af;
  --text:#efe6ea; --dim:#a8929b; --dim2:#7c5b63;
  --serif:Georgia,'Times New Roman',serif;
  --sans:'Segoe UI',system-ui,-apple-system,Roboto,'Noto Sans',sans-serif;
}
body{min-height:100dvh;display:flex;flex-direction:column;background:radial-gradient(1100px 500px at 80% -10%,rgba(225,29,72,.15),transparent 60%),radial-gradient(800px 400px at -10% 110%,rgba(190,18,60,.10),transparent 55%),var(--bg);color:var(--text);font-family:var(--sans)}
a{text-decoration:none;color:inherit}
button{font:inherit;cursor:pointer}
.top{display:flex;justify-content:space-between;align-items:center;padding:18px 22px}
.top .brand{display:flex;align-items:center;gap:9px;font-weight:700}
.top .brand img{width:28px;height:28px;filter:drop-shadow(0 0 8px rgba(244,63,94,.5))}
.top a.back{font-size:.84rem;color:var(--dim);display:inline-flex;align-items:center;gap:6px}
.top a.back:hover{color:var(--soft)}
main{flex:1;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:400px;background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:34px 30px;box-shadow:0 30px 80px rgba(0,0,0,.45)}
.card .logo{text-align:center;display:block;margin-bottom:14px}
.card .logo img{width:56px;height:56px;filter:drop-shadow(0 0 18px rgba(244,63,94,.5))}
.card h1{font-family:var(--serif);font-weight:500;font-size:1.7rem;text-align:center}
.card .sub{color:var(--dim);font-size:.85rem;text-align:center;margin-top:6px}
.tabs{display:grid;grid-template-columns:1fr 1fr;background:rgba(0,0,0,.3);border:1px solid var(--border);border-radius:12px;padding:4px;margin:24px 0 4px}
.tabs button{border:none;background:none;color:var(--dim);padding:9px;border-radius:9px;font-size:.85rem;font-weight:600;transition:.15s}
.tabs button.on{background:rgba(244,63,94,.16);color:#fff;box-shadow:0 2px 10px rgba(244,63,94,.2)}
label{display:block;font-size:.74rem;font-weight:600;color:var(--soft);margin:16px 0 6px;letter-spacing:.3px}
.inrow{position:relative}
.inrow input{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:12px;color:var(--text);padding:12px 14px;font:inherit;font-size:.9rem;outline:none;transition:.15s}
.inrow input:focus{border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.12)}
.inrow .eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--dim2);padding:8px;display:flex;border-radius:8px}
.inrow .eye:hover{color:var(--soft)}
.formnote{font-size:.72rem;color:var(--dim2);margin-top:6px;line-height:1.5}
.submit{width:100%;margin-top:22px;border:none;border-radius:13px;padding:13px;font-weight:700;font-size:.92rem;background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 8px 24px rgba(244,63,94,.35);transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px}
.submit:hover{filter:brightness(1.1)}
.submit:disabled{opacity:.55;cursor:wait}
.err{display:none;margin-top:14px;background:rgba(190,18,60,.12);border:1px solid rgba(248,113,113,.4);color:#fecaca;font-size:.8rem;border-radius:11px;padding:11px 13px;line-height:1.5;align-items:flex-start;gap:9px}
.err.show{display:flex}
.err svg{flex-shrink:0;margin-top:1px;color:#fca5a5}
.ok-note{display:none;margin-top:14px;background:rgba(16,185,129,.09);border:1px solid rgba(52,211,153,.35);color:#bbf7d0;font-size:.8rem;border-radius:11px;padding:11px 13px;line-height:1.5}
.ok-note.show{display:block}
.alt{text-align:center;margin-top:20px;font-size:.82rem;color:var(--dim)}
.alt a{color:var(--soft);font-weight:600}
.alt a:hover{text-decoration:underline}
.foot{text-align:center;padding:18px;font-size:.72rem;color:var(--dim2)}
.hidden{display:none!important}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}

/* ═══════════ LIGHT THEME (Claude-style warm) ═══════════ */
[data-theme=light]{
  --bg:#faf9f7; --panel:#ffffff; --panel2:#f0ede9;
  --border:rgba(120,80,90,.18); --border-hi:rgba(190,30,60,.45);
  --red:#e11d48; --red2:#f43f5e; --pink:#c2415f; --soft:#a63d57;
  --text:#262023; --dim:#6e5f65; --dim2:#9c8b91;
}
[data-theme=light] body{background:radial-gradient(1100px 500px at 80% -10%,rgba(225,29,72,.06),transparent 60%),radial-gradient(800px 400px at -10% 110%,rgba(190,18,60,.05),transparent 55%),var(--bg)}
[data-theme=light] .tabs{background:rgba(120,80,90,.08)}
[data-theme=light] .card{box-shadow:0 24px 70px rgba(120,80,90,.16)}
[data-theme=light] .tabs button.on{background:#fff;color:#262023}
[data-theme=light] .top a.back:hover,[data-theme=light] .inrow .eye:hover{color:#a63d57}
</style>
</head>
<body>

<div class="top">
  <a class="brand" href="index.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
  <div style="display:flex;align-items:center;gap:10px">
    <button class="back" id="themeBtn" title="Switch theme" style="border:none;background:none;cursor:pointer;display:inline-flex;align-items:center;padding:6px" type="button"><?= icon('sun', 16) ?></button>
    <a class="back" href="index.php"><?= icon('chevron-right', 14) ?> Back to home</a>
  </div>
</div>

<main>
  <div class="card">
    <a class="logo" href="index.php"><img src="assets/logo.svg" alt="Devil AI logo"></a>
    <h1 id="title">Welcome back</h1>
    <p class="sub" id="subtitle">Sign in to continue to your chats.</p>

    <div class="tabs" role="tablist">
      <button type="button" id="tabLogin" class="<?= $mode === 'login' ? 'on' : '' ?>">Sign in</button>
      <button type="button" id="tabReg" class="<?= $mode === 'register' ? 'on' : '' ?>">Create account</button>
    </div>

    <!-- ── sign in ── -->
    <form id="loginForm" class="<?= $mode === 'register' ? 'hidden' : '' ?>" novalidate>
      <label for="lemail">Email</label>
      <div class="inrow"><input id="lemail" type="email" autocomplete="email" placeholder="you@example.com" required></div>
      <label for="lpass">Password</label>
      <div class="inrow">
        <input id="lpass" type="password" autocomplete="current-password" placeholder="Your password" required>
        <button class="eye" type="button" data-eye="lpass" aria-label="Show password"><?= icon('eye', 17) ?></button>
      </div>
      <div class="err" id="lerr"><?= icon('warning', 16) ?><span></span></div>
      <button class="submit" type="submit" id="lbtn"><?= icon('unlock', 16) ?> Sign in</button>
      <p class="alt">New here? <a href="#" id="toReg">Create an account</a> — it takes 20 seconds.</p>
    </form>

    <!-- ── register ── -->
    <form id="regForm" class="<?= $mode === 'register' ? '' : 'hidden' ?>" novalidate>
      <label for="rname">Display name</label>
      <div class="inrow"><input id="rname" type="text" autocomplete="name" maxlength="40" placeholder="How should we call you?" required></div>
      <label for="remail">Email</label>
      <div class="inrow"><input id="remail" type="email" autocomplete="email" placeholder="you@example.com" required></div>
      <label for="rpass">Password</label>
      <div class="inrow">
        <input id="rpass" type="password" autocomplete="new-password" placeholder="At least 8 characters" required>
        <button class="eye" type="button" data-eye="rpass" aria-label="Show password"><?= icon('eye', 17) ?></button>
      </div>
      <p class="formnote">By creating an account you accept our cookie choices (opt-in only) — you can change them anytime in the footer.</p>
      <div class="err" id="rerr"><?= icon('warning', 16) ?><span></span></div>
      <button class="submit" type="submit" id="rbtn"><?= icon('user', 16) ?> Create account</button>
      <p class="alt">Already have an account? <a href="#" id="toLogin">Sign in</a>.</p>
    </form>
  </div>
</main>

<div class="foot">Devil AI v1.0.0.0 • 100% PHP</div>

<script>
(function () {
'use strict';
var $ = function (s) { return document.querySelector(s); };
function showErr(el, msg) { el.classList.add('show'); el.querySelector('span').textContent = msg; }
function hideErr(el) { el.classList.remove('show'); }
function setBusy(btn, busy, label) {
  if (busy) { btn.disabled = true; btn.dataset.old = btn.innerHTML; btn.innerHTML = '<?= icon('loader', 16) ?> Please wait…'; }
  else { btn.disabled = false; if (btn.dataset.old) { btn.innerHTML = btn.dataset.old; } }
}
async function post(action, body) {
  try {
    var r = await fetch('api.php?action=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    return await r.json();
  } catch (e) { return { ok: false, error: 'Network error — please try again.' }; }
}

/* tabs */
var loginForm = $('#loginForm'), regForm = $('#regForm');
function showTab(reg) {
  loginForm.classList.toggle('hidden', reg);
  regForm.classList.toggle('hidden', !reg);
  $('#tabLogin').classList.toggle('on', !reg);
  $('#tabReg').classList.toggle('on', reg);
  $('#title').textContent = reg ? 'Create your account' : 'Welcome back';
  $('#subtitle').textContent = reg ? 'Your chats will be private and isolated.' : 'Sign in to continue to your chats.';
  hideErr($('#lerr')); hideErr($('#rerr'));
  try { history.replaceState(null, '', reg ? '?mode=register' : 'login.php'); } catch (e) {}
}
$('#tabLogin').addEventListener('click', function () { showTab(false); });
$('#tabReg').addEventListener('click', function () { showTab(true); });
$('#toReg').addEventListener('click', function (e) { e.preventDefault(); showTab(true); });
$('#toLogin').addEventListener('click', function (e) { e.preventDefault(); showTab(false); });

/* password visibility */
document.querySelectorAll('[data-eye]').forEach(function (b) {
  b.addEventListener('click', function () {
    var inp = document.getElementById(b.dataset.eye);
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    b.innerHTML = show ? <?= json_encode(icon('eye-off', 17)) ?> : <?= json_encode(icon('eye', 17)) ?>;
    inp.focus();
  });
});

/* sign in */
loginForm.addEventListener('submit', async function (e) {
  e.preventDefault();
  hideErr($('#lerr'));
  var email = $('#lemail').value.trim(), pass = $('#lpass').value;
  if (!email || !pass) { showErr($('#lerr'), 'Please fill in your email and password.'); return; }
  setBusy($('#lbtn'), true);
  var j = await post('login', { email: email, password: pass });
  setBusy($('#lbtn'), false);
  if (j.ok) { window.location.href = 'app.php'; }
  else { showErr($('#lerr'), j.error || 'Sign in failed.'); }
});

/* register */
regForm.addEventListener('submit', async function (e) {
  e.preventDefault();
  hideErr($('#rerr'));
  var name = $('#rname').value.trim(), email = $('#remail').value.trim(), pass = $('#rpass').value;
  if (name.length < 2) { showErr($('#rerr'), 'Please enter a display name (at least 2 characters).'); return; }
  if (!email) { showErr($('#rerr'), 'Please enter your email.'); return; }
  if (pass.length < 8) { showErr($('#rerr'), 'Password must be at least 8 characters long.'); return; }
  setBusy($('#rbtn'), true);
  var j = await post('register', { name: name, email: email, password: pass });
  setBusy($('#rbtn'), false);
  if (j.ok) { window.location.href = 'app.php'; }
  else { showErr($('#rerr'), j.error || 'Registration failed.'); }
});
/* theme toggle (respects the personalization cookie choice) */
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
      if (!prefs || prefs.personalization !== false) { localStorage.setItem('devil_theme', t); }
    } catch (e) {}
    setIco();
  });
  setIco();
})();
})();
</script>
</body>
</html>
