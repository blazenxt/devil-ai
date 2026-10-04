<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Passwordless Sign-in (login.php) • v1.0.0.0
 *  Email → one-time code OR magic link. No passwords.
 *  Magic link: login.php?token=… (auto sign-in + redirect)
 * ═══════════════════════════════════════════════════════
 */
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
/* already signed in? — validate against users.json, else a deleted account
   with a stale session would bounce app.php ⇄ login.php forever */
if (isset($_SESSION['devil_uid'])) {
    $users = json_decode((string)@file_get_contents(__DIR__ . '/data/users.json'), true);
    if (is_array($users) && isset($users[$_SESSION['devil_uid']])) {
        header('Location: app.php'); exit;
    }
    unset($_SESSION['devil_uid'], $_SESSION['devil_name']); /* stale — clean it up */
}
require_once __DIR__ . '/inc/icons.php';

/* ── magic link (?token=…) — server-side verify + sign-in ── */
$tokenNote = null;
if (isset($_GET['token']) && is_string($_GET['token'])) {
    $token = trim($_GET['token']);
    $ok = false;
    if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
        $otpsFile = __DIR__ . '/data/otps.json';
        if (is_readable($otpsFile)) {
            $map = json_decode((string)file_get_contents($otpsFile), true);
            if (is_array($map)) {
                foreach ($map as $email => $rec) {
                    if (is_array($rec) && ($rec['purpose'] ?? '') === 'login'
                        && hash_equals((string)($rec['token'] ?? ''), $token)
                        && (int)($rec['expires'] ?? 0) >= time()) {
                        /* valid token → sign in (find or create the account) */
                        $usersFile = __DIR__ . '/data/users.json';
                        $users = is_readable($usersFile) ? json_decode((string)file_get_contents($usersFile), true) : [];
                        if (!is_array($users)) { $users = []; }
                        $found = null;
                        foreach ($users as $u) {
                            if (strcasecmp((string)($u['email'] ?? ''), (string)$email) === 0) { $found = $u; break; }
                        }
                        if (!$found) {
                            $local = explode('@', (string)$email)[0];
                            $first = trim((string)(preg_split('/[._\-+0-9]+/', $local)[0] ?? ''));
                            $name = $first !== '' ? ucfirst(mb_substr($first, 0, 20)) : 'Devil';
                            $uidNew = 'u' . bin2hex(random_bytes(8));
                            $users[$uidNew] = ['id' => $uidNew, 'name' => $name, 'email' => strtolower((string)$email), 'created' => time()];
                            $found = $users[$uidNew];
                            @file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
                        }
                        unset($map[$email]);
                        @file_put_contents($otpsFile, json_encode($map), LOCK_EX);
                        session_regenerate_id(true);
                        $_SESSION['devil_uid'] = (string)$found['id'];
                        devil_session_refresh();
                        $ok = true;
                        break;
                    }
                }
            }
        }
    }
    if ($ok) { header('Location: app.php'); exit; }
    $tokenNote = 'This magic link is invalid or has expired. Enter your email below to get a fresh code.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0709">
<script>/* theme boot — runs before paint to avoid a flash of the wrong theme */
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();</script>
<title>Sign in — Devil AI</title>
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
.top .right{display:flex;align-items:center;gap:10px}
.top a.back,.top button.tb{font-size:.84rem;color:var(--dim);display:inline-flex;align-items:center;gap:6px}
.top a.back:hover,.top button.tb:hover{color:var(--soft)}
.top button.tb{border:none;background:none;padding:6px}
main{flex:1;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:410px;background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:34px 30px;box-shadow:0 30px 80px rgba(0,0,0,.45)}
.card .logo{text-align:center;display:block;margin-bottom:14px}
.card .logo img{width:56px;height:56px;filter:drop-shadow(0 0 18px rgba(244,63,94,.5))}
.card h1{font-family:var(--serif);font-weight:500;font-size:1.65rem;text-align:center}
.card .sub{color:var(--dim);font-size:.85rem;text-align:center;margin-top:6px;line-height:1.5}
label{display:block;font-size:.74rem;font-weight:600;color:var(--soft);margin:18px 0 6px;letter-spacing:.3px}
.inrow{position:relative}
.inrow input{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:12px;color:var(--text);padding:12px 14px;font:inherit;font-size:.9rem;outline:none;transition:.15s}
.inrow input:focus{border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.12)}
.codebox input{font-family:ui-monospace,Consolas,monospace;letter-spacing:8px;font-size:1.15rem;text-align:center}
.formnote{font-size:.72rem;color:var(--dim2);margin-top:8px;line-height:1.55}
.submit{width:100%;margin-top:20px;border:none;border-radius:13px;padding:13px;font-weight:700;font-size:.92rem;background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 8px 24px rgba(244,63,94,.35);transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px}
.submit:hover{filter:brightness(1.1)}
.submit:disabled{opacity:.55;cursor:wait}
.err{display:none;margin-top:14px;background:rgba(190,18,60,.12);border:1px solid rgba(248,113,113,.4);color:#fecaca;font-size:.8rem;border-radius:11px;padding:11px 13px;line-height:1.5;align-items:flex-start;gap:9px}
.err.show{display:flex}
.err svg{flex-shrink:0;margin-top:1px;color:#fca5a5}
.ok-note{display:none;margin-top:14px;background:rgba(16,185,129,.09);border:1px solid rgba(52,211,153,.35);color:#bbf7d0;font-size:.8rem;border-radius:11px;padding:11px 13px;line-height:1.5}
.ok-note.show{display:block}
.altrow{display:flex;justify-content:space-between;align-items:center;margin-top:18px;font-size:.8rem;gap:10px;flex-wrap:wrap}
.altrow a{color:var(--soft);font-weight:600;cursor:pointer}
.altrow a:hover{text-decoration:underline}
.altrow a.muted{color:var(--dim2);font-weight:400}
.foot{text-align:center;padding:18px;font-size:.72rem;color:var(--dim2)}
.hidden{display:none!important}
.steps{display:flex;gap:6px;justify-content:center;margin-top:18px}
.steps span{width:26px;height:4px;border-radius:99px;background:var(--panel2);border:1px solid var(--border)}
.steps span.on{background:linear-gradient(90deg,#f43f5e,#be123c);border-color:transparent}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
/* light theme */
[data-theme=light]{--bg:#faf9f7;--panel:#ffffff;--panel2:#f0ede9;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--red:#e11d48;--red2:#f43f5e;--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f}
[data-theme=light] body{background:radial-gradient(1100px 500px at 80% -10%,rgba(225,29,72,.06),transparent 60%),radial-gradient(800px 400px at -10% 110%,rgba(190,18,60,.05),transparent 55%),var(--bg)}
[data-theme=light] .card{box-shadow:0 24px 70px rgba(120,80,90,.16)}
[data-theme=light] .card h1{color:#1a1518}
[data-theme=light] .err{color:#9f1239;background:rgba(190,18,60,.07);border-color:rgba(190,18,60,.3)}
[data-theme=light] .err svg{color:#b91c1c}
[data-theme=light] .ok-note{color:#166534;background:rgba(6,148,110,.08);border-color:rgba(6,148,110,.3)}
</style>
</head>
<body>

<div class="top">
  <a class="brand" href="index.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
  <div class="right">
    <button class="tb" id="themeBtn" title="Switch theme" type="button"><?= icon('sun', 16) ?></button>
    <a class="back" href="index.php"><?= icon('chevron-right', 14) ?> Back to home</a>
  </div>
</div>

<main>
  <div class="card">
    <a class="logo" href="index.php"><img src="assets/logo.svg" alt="Devil AI logo"></a>

    <?php if ($tokenNote): ?>
      <div class="err show"><?= icon('warning', 16) ?><span><?= htmlspecialchars($tokenNote) ?></span></div>
    <?php endif; ?>

    <!-- ══ STEP 1: email ══ -->
    <div id="step1">
      <h1>Welcome</h1>
      <p class="sub">Enter your email — we'll send you a one-time login code. No passwords, ever.</p>
      <form id="emailForm" novalidate>
        <label for="email">Email</label>
        <div class="inrow"><input id="email" type="email" inputmode="email" autocomplete="email" placeholder="you@example.com" required autofocus></div>
        <p class="formnote">New here? Just enter your email — your account is created automatically.</p>
        <div class="err" id="e1err"><?= icon('warning', 16) ?><span></span></div>
        <button class="submit" type="submit" id="e1btn"><?= icon('mail', 16) ?> Continue with email</button>
      </form>
    </div>

    <!-- ══ STEP 2: code ══ -->
    <div id="step2" class="hidden">
      <h1>Check your inbox</h1>
      <p class="sub">We sent a 6-digit code to <b id="sentTo"></b>. It expires in 10 minutes.</p>
      <form id="codeForm" novalidate>
        <label for="code">Login code</label>
        <div class="inrow codebox"><input id="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="••••••" autocomplete="one-time-code" required autofocus></div>
        <p class="formnote">Tip: the email also contains a magic link that signs you in with one click.</p>
        <div class="err" id="e2err"><?= icon('warning', 16) ?><span></span></div>
        <div class="ok-note" id="e2ok"></div>
        <button class="submit" type="submit" id="e2btn"><?= icon('unlock', 16) ?> Verify and sign in</button>
      </form>
      <div class="altrow">
        <a id="resend">Resend code</a>
        <a id="changeEmail" class="muted">Use a different email</a>
      </div>
    </div>

    <div class="steps"><span class="on" id="dot1"></span><span id="dot2"></span></div>
  </div>
</main>

<div class="foot">Devil AI v1.0.0.0 • Developed by BlazeNXT</div>

<script>
(function () {
'use strict';
var $ = function (s) { return document.querySelector(s); };
function post(action, body) {
  return fetch('api.php?action=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
    .then(function (r) { return r.json(); })
    .catch(function () { return { ok: false, error: 'Network error — please try again.' }; });
}
function showErr(el, msg) { el.classList.add('show'); el.querySelector('span').textContent = msg; }
function hideErr(el) { el.classList.remove('show'); }
function busy(btn, on) {
  if (on) { btn.disabled = true; btn.dataset.old = btn.innerHTML; btn.innerHTML = '<?= icon('loader', 16) ?> Please wait…'; }
  else { btn.disabled = false; if (btn.dataset.old) { btn.innerHTML = btn.dataset.old; } }
}

var email = '';
var step1 = $('#step1'), step2 = $('#step2');

/* step 1 → request code */
$('#emailForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  hideErr($('#e1err'));
  email = $('#email').value.trim();
  if (!email) { showErr($('#e1err'), 'Please enter your email address.'); return; }
  busy($('#e1btn'), true);
  var j = await post('otp_request', { email: email });
  busy($('#e1btn'), false);
  if (j.ok) {
    email = email.toLowerCase();
    step1.classList.add('hidden');
    step2.classList.remove('hidden');
    $('#dot2').classList.add('on');
    $('#sentTo').textContent = j.masked || email;
    $('#code').value = '';
    setTimeout(function () { $('#code').focus(); }, 60);
  } else {
    showErr($('#e1err'), j.error || 'Could not send the code.');
  }
});

/* step 2 → verify */
$('#codeForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  hideErr($('#e2err'));
  $('#e2ok').classList.remove('show');
  var code = $('#code').value.replace(/\D/g, '');
  if (code.length !== 6) { showErr($('#e2err'), 'Enter the 6-digit code from your email.'); return; }
  busy($('#e2btn'), true);
  var j = await post('otp_verify', { email: email, code: code });
  busy($('#e2btn'), false);
  if (j.ok) { window.location.href = 'app.php'; }
  else { showErr($('#e2err'), j.error || 'Wrong or expired code.'); }
});

/* resend with 60s cooldown */
var resendT = null;
$('#resend').addEventListener('click', async function () {
  if (resendT) { return; }
  hideErr($('#e2err'));
  var j = await post('otp_request', { email: email });
  if (j.ok) {
    var left = 60;
    var el = $('#resend');
    var tick = function () {
      el.textContent = 'Resend code (' + left + 's)';
      if (left-- > 0) { resendT = setTimeout(tick, 1000); }
      else { resendT = null; el.textContent = 'Resend code'; }
    };
    tick();
    $('#e2ok').textContent = 'A new code was sent to your email.';
    $('#e2ok').classList.add('show');
  } else {
    showErr($('#e2err'), j.error || 'Could not resend.');
  }
});

/* back to step 1 */
$('#changeEmail').addEventListener('click', function () {
  step2.classList.add('hidden');
  step1.classList.remove('hidden');
  $('#dot2').classList.remove('on');
  $('#code').value = '';
  hideErr($('#e2err'));
  setTimeout(function () { $('#email').focus(); }, 60);
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
      if (!prefs || prefs.personalization !== false) { localStorage.setItem('devil_theme', t); if(window.devilCookieSet){window.devilCookieSet('devil_theme',t,365);}else{document.cookie='devil_theme='+encodeURIComponent(t)+'; Max-Age=31536000; Path=/devil-ai/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');} }
    } catch (e) {}
    setIco();
  });
  setIco();
})();
})();
</script>
</body>
</html>
