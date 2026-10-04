<?php
/**
 * Devil AI — Account Settings (MPA page)
 */
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
require_once __DIR__ . '/inc/icons.php';

function settings_data_dir(): string { return __DIR__ . '/data'; }
function settings_users_path(): string { return settings_data_dir() . '/users.json'; }
function settings_chats_dir(string $uid): string { return settings_data_dir() . '/chats/' . $uid; }
function settings_load_json(string $path): array {
    if (!is_readable($path)) { return []; }
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}
function settings_save_json_atomic(string $path, array $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { return false; }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) { return false; }
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}
function settings_current_uid(): ?string { return isset($_SESSION['devil_uid']) ? (string)$_SESSION['devil_uid'] : null; }
function settings_current_user(): ?array {
    $uid = settings_current_uid();
    if (!$uid) { return null; }
    $users = settings_load_json(settings_users_path());
    return isset($users[$uid]) && is_array($users[$uid]) ? $users[$uid] : null;
}
function settings_clean_name(string $name): string {
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags($name)) ?? '');
    if (mb_strlen($name) > 40) { $name = mb_substr($name, 0, 40); }
    return $name;
}

$uid = settings_current_uid();
$me = settings_current_user();
if (!$uid || !$me) { header('Location: login.php'); exit; }

if (empty($_SESSION['settings_csrf'])) { $_SESSION['settings_csrf'] = bin2hex(random_bytes(16)); }
$csrf = (string)$_SESSION['settings_csrf'];
$status = '';
$statusClass = '';

if (isset($_GET['export']) && $_GET['export'] === 'chats') {
    $payload = ['exported_at' => date('c'), 'user' => ['name' => (string)($me['name'] ?? ''), 'email' => (string)($me['email'] ?? '')], 'chats' => []];
    $dir = settings_chats_dir($uid);
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        $j = settings_load_json($f);
        if ($j) { $payload['chats'][] = $j; }
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="devil-ai-chats-' . date('Ymd-His') . '.json"');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $posted)) {
        $status = 'Security check failed. Refresh and try again.';
        $statusClass = 'bad';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'profile') {
            $name = settings_clean_name((string)($_POST['name'] ?? ''));
            if ($name === '') {
                $status = 'Display name cannot be empty.';
                $statusClass = 'bad';
            } else {
                $users = settings_load_json(settings_users_path());
                if (!isset($users[$uid]) || !is_array($users[$uid])) {
                    $status = 'Account not found. Please sign in again.';
                    $statusClass = 'bad';
                } else {
                    $users[$uid]['name'] = $name;
                    $users[$uid]['updated'] = time();
                    if (settings_save_json_atomic(settings_users_path(), $users)) {
                        $me = $users[$uid];
                        $status = 'Profile updated.';
                        $statusClass = 'ok';
                    } else {
                        $status = 'Could not save profile. Check data/ permissions.';
                        $statusClass = 'bad';
                    }
                }
            }
        }
    }
}

$name = (string)($me['name'] ?? 'Devil');
$email = (string)($me['email'] ?? '');
$created = !empty($me['created']) ? date('d M Y', (int)$me['created']) : '—';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0709">
<meta name="robots" content="noindex">
<script>
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();
</script>
<title>Account settings — Devil AI</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0c0709;--panel:#171014;--panel2:#1d1216;--panel3:#241721;--border:rgba(244,63,94,.18);--border-hi:rgba(244,63,94,.5);--red:#e11d48;--red2:#f43f5e;--pink:#fb7185;--soft:#fda4af;--text:#efe6ea;--dim:#a8929b;--dim2:#7c5b63;--sans:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif}
[data-theme=light]{--bg:#faf9f7;--panel:#fff;--panel2:#f0ede9;--panel3:#e7e3dd;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--red:#e11d48;--red2:#f43f5e;--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f}
body{min-height:100dvh;background:radial-gradient(1000px 480px at 75% -10%,rgba(225,29,72,.13),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans)}
a{text-decoration:none;color:inherit}button,input{font:inherit}button{cursor:pointer;background:none;border:none;color:inherit}.top{height:66px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;border-bottom:1px solid var(--border);background:rgba(12,7,9,.72);backdrop-filter:blur(14px);position:sticky;top:0;z-index:20}[data-theme=light] .top{background:rgba(250,249,247,.82)}.brand{display:flex;align-items:center;gap:10px;font-weight:750}.brand img{width:30px;height:30px;filter:drop-shadow(0 0 8px rgba(244,63,94,.45))}.topnav{display:flex;gap:10px;align-items:center}.tb,.back{display:inline-flex;align-items:center;gap:7px;color:var(--dim);font-size:.84rem;padding:8px 10px;border-radius:10px}.tb:hover,.back:hover{background:rgba(244,63,94,.1);color:var(--soft)}.layout{max-width:1120px;margin:0 auto;display:grid;grid-template-columns:260px 1fr;gap:22px;padding:26px 20px 46px}.side{position:sticky;top:92px;height:max-content;background:var(--panel);border:1px solid var(--border);border-radius:20px;padding:10px;box-shadow:0 18px 60px rgba(0,0,0,.25)}.navitem{display:flex;align-items:center;gap:10px;width:100%;padding:11px 12px;border-radius:12px;color:var(--dim);font-size:.88rem}.navitem.on,.navitem:hover{background:rgba(244,63,94,.12);color:var(--text)}.main{display:flex;flex-direction:column;gap:18px}.hero{background:linear-gradient(135deg,rgba(244,63,94,.12),transparent 60%),var(--panel);border:1px solid var(--border);border-radius:24px;padding:26px;box-shadow:0 24px 80px rgba(0,0,0,.32)}.hero h1{font-size:1.65rem;letter-spacing:-.03em}.hero p{color:var(--dim);font-size:.92rem;margin-top:7px;line-height:1.6}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.card{background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:22px;box-shadow:0 18px 60px rgba(0,0,0,.22)}.card h2{font-size:1.02rem;display:flex;align-items:center;gap:9px;margin-bottom:6px}.card h2 svg{color:var(--pink)}.sub{font-size:.82rem;color:var(--dim);line-height:1.55;margin-bottom:15px}label{display:block;color:var(--soft);font-weight:650;font-size:.74rem;margin:14px 0 6px;letter-spacing:.25px}input[type=text],input[readonly]{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:12px;color:var(--text);padding:12px 13px;outline:none}input:focus{border-color:var(--border-hi)}.readonly{color:var(--dim)}.btnrow{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 16px;font-weight:650;font-size:.86rem}.btn.primary{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 8px 22px rgba(244,63,94,.28)}.btn.ghost{border:1px solid var(--border);color:var(--soft)}.btn.ghost:hover{background:rgba(244,63,94,.1)}.btn.danger{border:1px solid rgba(248,113,113,.38);background:rgba(190,18,60,.14);color:#fca5a5}.btn.danger:hover{background:rgba(190,18,60,.25)}.status{font-size:.82rem;line-height:1.55;min-height:1.4em;margin-top:12px;color:var(--dim)}.status.ok{color:#86efac}.status.bad{color:#fca5a5}[data-theme=light] .status.ok{color:#15803d}[data-theme=light] .status.bad{color:#b91c1c}.avatarRow{display:flex;align-items:center;gap:14px;margin:15px 0}.avatar{width:58px;height:58px;border-radius:18px;display:grid;place-items:center;background:linear-gradient(135deg,#f43f5e,#7f1d1d);color:#fff;font-weight:800;font-size:1.3rem;box-shadow:0 10px 30px rgba(244,63,94,.28)}.meta{color:var(--dim);font-size:.82rem;line-height:1.55}.pillrow{display:flex;gap:10px;flex-wrap:wrap}.pill{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--border);border-radius:999px;padding:8px 11px;font-size:.78rem;color:var(--dim);background:var(--panel2)}.themeBtns{display:flex;gap:10px;flex-wrap:wrap}.themePick{border:1px solid var(--border);border-radius:14px;padding:13px 15px;color:var(--text);background:var(--panel2);min-width:120px;text-align:left}.themePick.on{border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.08)}.dev{font-size:.82rem;color:var(--dim);line-height:1.7}.dev a{color:var(--soft);text-decoration:underline;text-underline-offset:3px}.modal{position:fixed;inset:0;background:rgba(5,2,4,.72);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;z-index:200;padding:16px}.modal.hidden{display:none}.sheet{background:var(--panel);border:1px solid var(--border);border-radius:20px;max-width:460px;width:100%;padding:24px}.sheet h3{display:flex;align-items:center;gap:9px}.sheet h3 svg{color:var(--pink)}.foot{text-align:center;color:var(--dim2);font-size:.75rem;padding:8px 20px 28px}@media(max-width:850px){.layout{grid-template-columns:1fr}.side{position:static}.grid{grid-template-columns:1fr}.top{padding:0 14px}.hero{padding:22px}.back span{display:none}}
</style>
</head>
<body>
<header class="top">
  <a class="brand" href="app.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
  <div class="topnav">
    <button class="tb" id="themeBtn" type="button" title="Switch theme"><?= icon('sun', 16) ?></button>
    <a class="back" href="app.php"><?= icon('chevron-right', 14) ?> <span>Back to chat</span></a>
  </div>
</header>

<div class="layout">
  <aside class="side">
    <a class="navitem on" href="#profile"><?= icon('user', 16) ?> Profile</a>
    <a class="navitem" href="#appearance"><?= icon('sun', 16) ?> Appearance</a>
    <a class="navitem" href="#data"><?= icon('server', 16) ?> Data controls</a>
    <a class="navitem" href="#danger"><?= icon('warning', 16) ?> Danger zone</a>
  </aside>

  <main class="main">
    <section class="hero">
      <h1>Account settings</h1>
      <p>Manage your Devil AI profile, appearance, privacy choices, and data controls — split into its own page for a cleaner multi-page experience.</p>
      <div class="avatarRow">
        <div class="avatar"><?= htmlspecialchars(strtoupper(mb_substr($name, 0, 1))) ?></div>
        <div class="meta"><b><?= htmlspecialchars($name) ?></b><br><?= htmlspecialchars($email) ?><br>Member since <?= htmlspecialchars($created) ?></div>
      </div>
      <?php if ($status !== ''): ?><div class="status <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($status) ?></div><?php endif; ?>
    </section>

    <div class="grid">
      <section class="card" id="profile">
        <h2><?= icon('user', 18) ?> Profile</h2>
        <p class="sub">This is the name shown in the sidebar and account menu.</p>
        <form method="post" action="settings.php#profile">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="profile">
          <label for="name">Display name</label>
          <input id="name" name="name" type="text" maxlength="40" value="<?= htmlspecialchars($name) ?>" autocomplete="name">
          <label for="email">Email address</label>
          <input id="email" class="readonly" type="text" value="<?= htmlspecialchars($email) ?>" readonly>
          <div class="btnrow"><button class="btn primary" type="submit"><?= icon('check', 15) ?> Save profile</button></div>
        </form>
      </section>

      <section class="card" id="appearance">
        <h2><?= icon('sun', 18) ?> Appearance</h2>
        <p class="sub">Theme preference is stored on this device.</p>
        <div class="themeBtns">
          <button class="themePick" data-theme-pick="dark" type="button"><?= icon('moon', 16) ?><br><b>Dark</b><br><span class="sub">Devil dark</span></button>
          <button class="themePick" data-theme-pick="light" type="button"><?= icon('sun', 16) ?><br><b>Light</b><br><span class="sub">Warm light</span></button>
        </div>
      </section>

      <section class="card" id="data">
        <h2><?= icon('server', 18) ?> Data controls</h2>
        <p class="sub">Download your chats or update cookie/privacy choices.</p>
        <div class="pillrow">
          <span class="pill"><?= icon('shield-check', 15) ?> Passwordless login</span>
          <span class="pill"><?= icon('lock', 15) ?> Isolated chats</span>
        </div>
        <div class="btnrow">
          <a class="btn ghost" href="settings.php?export=chats"><?= icon('download', 15) ?> Export chats</a>
          <button class="btn ghost" id="cookieBtn" type="button"><?= icon('cookie', 15) ?> Cookie settings</button>
        </div>
      </section>

      <section class="card" id="danger">
        <h2><?= icon('warning', 18) ?> Danger zone</h2>
        <p class="sub">Delete your account and all saved chats permanently. You will need an email confirmation code.</p>
        <button class="btn danger" id="deleteBtn" type="button"><?= icon('trash', 15) ?> Delete account</button>
        <div class="status" id="deleteStatus"></div>
      </section>
    </div>

    <section class="card">
      <h2><?= icon('flame', 18) ?> About Devil AI</h2>
      <p class="dev">Developed by: <a href="https://www.blazenxt.in" target="_blank" rel="noopener">BlazeNXT</a><br>Devil AI uses a clean multi-page experience for chat, settings, admin, login, and policies.</p>
    </section>
  </main>
</div>

<div class="foot">Devil AI v1.0.0.0 • Developed by BlazeNXT</div>

<div class="modal hidden" id="deleteModal"><div class="sheet">
  <h3><?= icon('warning', 18) ?> Delete account</h3>
  <p class="sub" style="margin-top:12px">This permanently deletes your account and chats. First send a confirmation code to your email, then enter it here.</p>
  <div class="btnrow"><button class="btn ghost" id="sendDeleteCode" type="button"><?= icon('mail', 15) ?> Send code</button></div>
  <label for="deleteCode">Confirmation code</label>
  <input id="deleteCode" type="text" maxlength="6" inputmode="numeric" placeholder="6-digit code">
  <div class="status" id="modalStatus"></div>
  <div class="btnrow"><button class="btn danger" id="confirmDelete" type="button"><?= icon('trash', 15) ?> Delete forever</button><button class="btn ghost" id="cancelDelete" type="button">Cancel</button></div>
</div></div>

<?php require __DIR__ . '/inc/cookiebar.php'; ?>
<script>
(function(){
'use strict';
var $=function(s){return document.querySelector(s)};
function api(action, body){return fetch('api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body||{})}).then(function(r){return r.json()}).catch(function(){return{ok:false,error:'Network error.'}})}
function cur(){return document.documentElement.getAttribute('data-theme')==='light'?'light':'dark'}
function applyTheme(t){document.documentElement.setAttribute('data-theme',t);try{localStorage.setItem('devil_theme',t);if(window.devilCookieSet){window.devilCookieSet('devil_theme',t,365);}else{document.cookie='devil_theme='+encodeURIComponent(t)+'; Max-Age=31536000; Path=/devil-ai/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');}}catch(e){};syncTheme()}
function syncTheme(){var t=cur();document.querySelectorAll('[data-theme-pick]').forEach(function(b){b.classList.toggle('on',b.dataset.themePick===t)});var btn=$('#themeBtn');if(btn){btn.innerHTML=t==='dark'?<?= json_encode(icon('sun', 16)) ?>:<?= json_encode(icon('moon', 16)) ?>}}
$('#themeBtn').addEventListener('click',function(){applyTheme(cur()==='dark'?'light':'dark')});
document.querySelectorAll('[data-theme-pick]').forEach(function(b){b.addEventListener('click',function(){applyTheme(b.dataset.themePick)})});
syncTheme();
$('#cookieBtn').addEventListener('click',function(){if(window.devilOpenCookies){devilOpenCookies()}});
var modal=$('#deleteModal'), ms=$('#modalStatus');
$('#deleteBtn').addEventListener('click',function(){modal.classList.remove('hidden');ms.textContent='';$('#deleteCode').value=''});
$('#cancelDelete').addEventListener('click',function(){modal.classList.add('hidden')});
modal.addEventListener('click',function(e){if(e.target===modal){modal.classList.add('hidden')}});
$('#sendDeleteCode').addEventListener('click',function(){ms.className='status';ms.textContent='Sending code…';api('otp_request',{purpose:'delete'}).then(function(j){if(j.ok){ms.className='status ok';ms.textContent='Code sent to '+(j.masked||'your email')+'.'}else{ms.className='status bad';ms.textContent=j.error||'Could not send code.'}})});
$('#confirmDelete').addEventListener('click',function(){var code=$('#deleteCode').value.replace(/\D/g,'');if(code.length!==6){ms.className='status bad';ms.textContent='Enter the 6-digit code.';return}ms.className='status';ms.textContent='Deleting…';api('account_delete',{code:code}).then(function(j){if(j.ok){location.href='index.php'}else{ms.className='status bad';ms.textContent=j.error||'Delete failed.'}})});
})();
</script>
</body>
</html>
