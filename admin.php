<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Admin Panel (admin.php) • v1.0.0.0
 *  Owner-only page (password-protected). NOT linked from
 *  anywhere in the public UI — bookmark this URL.
 * ═══════════════════════════════════════════════════════
 */
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
require_once __DIR__ . '/inc/icons.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0709">
<meta name="robots" content="noindex">
<script>/* theme boot */
(function(){var t=null;try{t=localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();</script>
<title>Admin — Devil AI</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0c0709; --panel:#171014; --panel2:#1d1216; --panel3:#241721;
  --border:rgba(244,63,94,.18); --border-hi:rgba(244,63,94,.5);
  --red:#e11d48; --red2:#f43f5e; --pink:#fb7185; --soft:#fda4af;
  --text:#efe6ea; --dim:#a8929b; --dim2:#7c5b63;
  --sans:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif;
}
body{min-height:100dvh;display:flex;flex-direction:column;background:radial-gradient(1100px 500px at 80% -10%,rgba(225,29,72,.13),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans)}
a{text-decoration:none;color:inherit}
button{font:inherit;cursor:pointer}
.top{display:flex;justify-content:space-between;align-items:center;padding:18px 22px}
.top .brand{display:flex;align-items:center;gap:9px;font-weight:700}
.top .brand img{width:28px;height:28px;filter:drop-shadow(0 0 8px rgba(244,63,94,.5))}
.top .right{display:flex;align-items:center;gap:10px}
.top a.back,.top button.tb{font-size:.84rem;color:var(--dim);display:inline-flex;align-items:center;gap:6px}
.top a.back:hover,.top button.tb:hover{color:var(--soft)}
.top button.tb{border:none;background:none;padding:6px}
main{flex:1;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:560px;background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:30px;box-shadow:0 30px 80px rgba(0,0,0,.45);margin:20px 0}
.card h1{font-size:1.3rem;display:flex;align-items:center;gap:10px}
.card h1 svg{color:var(--pink)}
.card .sub{color:var(--dim);font-size:.83rem;margin-top:6px;line-height:1.55}
label{display:block;font-size:.74rem;font-weight:600;color:var(--soft);margin:16px 0 6px;letter-spacing:.3px}
select,input{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:11px;color:var(--text);padding:11px 13px;font:inherit;font-size:.88rem;outline:none;transition:.15s}
select:focus,input:focus{border-color:var(--border-hi)}
select option{background:var(--panel2)}
.hint{font-size:.72rem;color:var(--dim2);line-height:1.55;margin-top:6px}
.btnrow{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap}
.btn{border:none;border-radius:12px;padding:11px 18px;font-weight:600;font-size:.86rem;display:inline-flex;align-items:center;gap:8px;transition:.15s}
.btn.primary{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 6px 18px rgba(244,63,94,.3)}
.btn.primary:hover{filter:brightness(1.1)}
.btn.ghost{border:1px solid var(--border);color:var(--soft)}
.btn.ghost:hover{background:rgba(244,63,94,.1)}
.status{margin-top:14px;font-size:.8rem;min-height:1.4em;white-space:pre-wrap;color:var(--dim);line-height:1.55}
.status.ok{color:#86efac}
[data-theme=light] .status.ok{color:#15803d}
[data-theme=light] .status.bad{color:#b91c1c}
.status.bad{color:#fca5a5}
.divider{border:none;border-top:1px solid var(--border);margin:22px 0 6px}
.modelabel{font-size:.78rem;font-weight:700;color:var(--text);margin-top:18px;display:flex;align-items:center;gap:8px}
.modelabel svg{color:var(--pink)}
.hidden{display:none!important}
.foot{text-align:center;padding:18px;font-size:.72rem;color:var(--dim2)}
[data-theme=light]{--bg:#faf9f7;--panel:#ffffff;--panel2:#f0ede9;--panel3:#e7e3dd;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--red:#e11d48;--red2:#f43f5e;--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f}
[data-theme=light] body{background:radial-gradient(1100px 500px at 80% -10%,rgba(225,29,72,.06),transparent 60%),var(--bg)}
[data-theme=light] .card{box-shadow:0 24px 70px rgba(120,80,90,.16)}
</style>
</head>
<body>

<div class="top">
  <a class="brand" href="index.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI <span style="color:var(--dim2);font-weight:400;font-size:.8rem">Admin</span></a>
  <div class="right">
    <button class="tb" id="themeBtn" title="Switch theme" type="button"><?= icon('sun', 16) ?></button>
    <a class="back" href="index.php"><?= icon('chevron-right', 14) ?> Home</a>
  </div>
</div>

<main>
  <div class="card">

    <!-- ═══ lock screen ═══ -->
    <div id="lockView">
      <h1><?= icon('lock', 20) ?> Admin panel</h1>
      <p class="sub">Owner-only area. This page is not linked anywhere on the public site — the engines and keys that power Devil AI are configured here.</p>
      <label for="pw">Admin password</label>
      <input id="pw" type="password" autocomplete="off" placeholder="Enter the admin password">
      <div class="status bad" id="lockStatus"></div>
      <div class="btnrow"><button class="btn primary" id="unlockBtn" type="button"><?= icon('unlock', 15) ?> Unlock</button></div>
    </div>

    <!-- ═══ settings ═══ -->
    <div id="mainView" class="hidden">
      <h1><?= icon('settings', 20) ?> Admin settings</h1>
      <p class="sub">Engine names below are confidential — they are never shown on the public site.</p>

      <div class="modelabel"><?= icon('zap', 15) ?> Devil Flash — engine</div>
      <select id="aFlash"></select>
      <div class="modelabel"><?= icon('sparkles', 15) ?> Devil Pro — engine</div>
      <select id="aPro"></select>
      <div class="modelabel"><?= icon('crown', 15) ?> Devil Ultra — engine</div>
      <select id="aUltra"></select>

      <hr class="divider">

      <label for="aRate">Messages per user per hour</label>
      <input id="aRate" type="number" min="1" max="1000" inputmode="numeric">
      <label for="aChats">Max saved chats per user</label>
      <input id="aChats" type="number" min="1" max="500" inputmode="numeric">

      <hr class="divider">

      <label for="aPw">New admin password <span style="font-weight:400;color:var(--dim2)">(leave empty to keep)</span></label>
      <input id="aPw" type="password" placeholder="Only if you want to change it" autocomplete="off">

      <div class="status" id="status"></div>
      <div class="btnrow">
        <button class="btn primary" id="saveBtn" type="button"><?= icon('check', 15) ?> Save settings</button>
        <button class="btn ghost" id="testBtn" type="button"><?= icon('zap', 15) ?> Test engines</button>
      </div>
    </div>

  </div>
</main>

<div class="foot">Devil AI v1.0.0.0 • Admin</div>

<script>
(function () {
'use strict';
var $ = function (s) { return document.querySelector(s); };
function post(action, body) {
  return fetch('api.php?action=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
    .then(function (r) { return r.json(); })
    .catch(function () { return { ok: false, error: 'Network error.' }; });
}
var ENGINES = [], PW = '';

function fill(cfg) {
  function sel(el, val) {
    el.innerHTML = '';
    ENGINES.forEach(function (en) {
      var o = document.createElement('option');
      o.value = en.id; o.textContent = en.label;
      el.appendChild(o);
    });
    el.value = val;
  }
  sel($('#aFlash'), cfg.engines.flash);
  sel($('#aPro'), cfg.engines.pro);
  sel($('#aUltra'), cfg.engines.ultra);
  $('#aRate').value = cfg.rate_per_hour;
  $('#aChats').value = cfg.max_chats;
  $('#aPw').value = '';
  $('#status').textContent = '';
}

function tryUnlock() {
  var pw = $('#pw').value;
  if (!pw) { $('#lockStatus').textContent = 'Enter the password first.'; return; }
  PW = pw;
  $('#lockStatus').textContent = 'Checking…';
  post('auth', { admin_password: pw }).then(function (j) {
    if (j.ok && j.config) {
      ENGINES = j.engines || [];
      fill(j.config);
      $('#lockView').classList.add('hidden');
      $('#mainView').classList.remove('hidden');
    } else { $('#lockStatus').textContent = 'Wrong password.'; }
  });
}
$('#unlockBtn').addEventListener('click', tryUnlock);
$('#pw').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); tryUnlock(); } });

$('#saveBtn').addEventListener('click', function () {
  var st = $('#status');
  st.className = 'status'; st.textContent = 'Saving…';
  post('settings', {
    current_admin_password: PW,
    new_admin_password: $('#aPw').value,
    engines: { flash: $('#aFlash').value, pro: $('#aPro').value, ultra: $('#aUltra').value },
    rate_per_hour: parseInt($('#aRate').value, 10) || 40,
    max_chats: parseInt($('#aChats').value, 10) || 100
  }).then(function (j) {
    if (j.ok) { st.className = 'status ok'; st.textContent = 'Saved — settings are live.'; }
    else { st.className = 'status bad'; st.textContent = j.error || 'Save failed.'; }
  });
});

$('#testBtn').addEventListener('click', function () {
  var st = $('#status');
  st.className = 'status'; st.textContent = 'Testing engines…';
  post('test', { current_admin_password: PW }).then(function (j) {
    if (j.ok) { st.className = 'status ok'; st.textContent = 'All engines alive. Sample reply: ' + (j.reply || '').slice(0, 140); }
    else { st.className = 'status bad'; st.textContent = (j.error || 'Test failed') + (j.hint ? '\nHint: ' + j.hint : ''); }
  });
});

/* theme toggle */
(function () {
  var SUN = <?= json_encode(icon('sun', 16)) ?>;
  var MOON = <?= json_encode(icon('moon', 16)) ?>;
  var b = document.getElementById('themeBtn');
  function cur() { return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'; }
  function setIco() { b.innerHTML = cur() === 'dark' ? SUN : MOON; }
  b.addEventListener('click', function () {
    var t = cur() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem('devil_theme', t); } catch (e) {}
    setIco();
  });
  setIco();
})();
})();
</script>
</body>
</html>
