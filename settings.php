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
<link rel="manifest" href="manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Devil AI">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0c0709;--panel:#171014;--panel2:#1d1216;--panel3:#241721;--border:rgba(244,63,94,.18);--border-hi:rgba(244,63,94,.5);--red:#e11d48;--red2:#f43f5e;--pink:#fb7185;--soft:#fda4af;--text:#efe6ea;--dim:#a8929b;--dim2:#7c5b63;--sans:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif}
[data-theme=light]{--bg:#faf9f7;--panel:#fff;--panel2:#f0ede9;--panel3:#e7e3dd;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--red:#e11d48;--red2:#f43f5e;--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f}
body{min-height:100dvh;background:radial-gradient(1000px 480px at 75% -10%,rgba(225,29,72,.13),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans)}
a{text-decoration:none;color:inherit}button,input,select{font:inherit}button{cursor:pointer;background:none;border:none;color:inherit}.top{height:66px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;border-bottom:1px solid var(--border);background:rgba(12,7,9,.72);backdrop-filter:blur(14px);position:sticky;top:0;z-index:20}[data-theme=light] .top{background:rgba(250,249,247,.82)}.brand{display:flex;align-items:center;gap:10px;font-weight:750}.brand img{width:30px;height:30px;filter:drop-shadow(0 0 8px rgba(244,63,94,.45))}.topnav{display:flex;gap:10px;align-items:center}.tb,.back{display:inline-flex;align-items:center;gap:7px;color:var(--dim);font-size:.84rem;padding:8px 10px;border-radius:10px}.tb:hover,.back:hover{background:rgba(244,63,94,.1);color:var(--soft)}.layout{max-width:1120px;margin:0 auto;display:grid;grid-template-columns:260px 1fr;gap:22px;padding:26px 20px 46px}.side{position:sticky;top:92px;height:max-content;background:var(--panel);border:1px solid var(--border);border-radius:20px;padding:10px;box-shadow:0 18px 60px rgba(0,0,0,.25)}.navitem{display:flex;align-items:center;gap:10px;width:100%;padding:11px 12px;border-radius:12px;color:var(--dim);font-size:.88rem}.navitem.on,.navitem:hover{background:rgba(244,63,94,.12);color:var(--text)}.main{display:flex;flex-direction:column;gap:18px}.hero{background:linear-gradient(135deg,rgba(244,63,94,.12),transparent 60%),var(--panel);border:1px solid var(--border);border-radius:24px;padding:26px;box-shadow:0 24px 80px rgba(0,0,0,.32)}.hero h1{font-size:1.65rem;letter-spacing:-.03em}.hero p{color:var(--dim);font-size:.92rem;margin-top:7px;line-height:1.6}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.card{background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:22px;box-shadow:0 18px 60px rgba(0,0,0,.22)}.card h2{font-size:1.02rem;display:flex;align-items:center;gap:9px;margin-bottom:6px}.card h2 svg{color:var(--pink)}.sub{font-size:.82rem;color:var(--dim);line-height:1.55;margin-bottom:15px}label{display:block;color:var(--soft);font-weight:650;font-size:.74rem;margin:14px 0 6px;letter-spacing:.25px}input[type=text],input[readonly],select{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:12px;color:var(--text);padding:12px 13px;outline:none}input:focus,select:focus{border-color:var(--border-hi)}.readonly{color:var(--dim)}.btnrow{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 16px;font-weight:650;font-size:.86rem}.btn.primary{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 8px 22px rgba(244,63,94,.28)}.btn.ghost{border:1px solid var(--border);color:var(--soft)}.btn.ghost:hover{background:rgba(244,63,94,.1)}.btn.danger{border:1px solid rgba(248,113,113,.38);background:rgba(190,18,60,.14);color:#fca5a5}.btn.danger:hover{background:rgba(190,18,60,.25)}.status{font-size:.82rem;line-height:1.55;min-height:1.4em;margin-top:12px;color:var(--dim)}.status.ok{color:#86efac}.status.bad{color:#fca5a5}[data-theme=light] .status.ok{color:#15803d}[data-theme=light] .status.bad{color:#b91c1c}.avatarRow{display:flex;align-items:center;gap:14px;margin:15px 0}.avatar{width:58px;height:58px;border-radius:18px;display:grid;place-items:center;background:linear-gradient(135deg,#f43f5e,#7f1d1d);color:#fff;font-weight:800;font-size:1.3rem;box-shadow:0 10px 30px rgba(244,63,94,.28)}.meta{color:var(--dim);font-size:.82rem;line-height:1.55}.pillrow{display:flex;gap:10px;flex-wrap:wrap}.pill{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--border);border-radius:999px;padding:8px 11px;font-size:.78rem;color:var(--dim);background:var(--panel2)}.themeBtns{display:flex;gap:10px;flex-wrap:wrap}.themePick{border:1px solid var(--border);border-radius:14px;padding:13px 15px;color:var(--text);background:var(--panel2);min-width:120px;text-align:left}.themePick.on{border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.08)}.voicePrefs{display:grid;gap:10px}.voiceSample{display:flex;align-items:center;justify-content:space-between;gap:10px;background:var(--panel2);border:1px solid var(--border);border-radius:14px;padding:12px}.voiceSample span{font-size:.78rem;color:var(--dim);line-height:1.5}.voiceRange{display:flex;align-items:center;gap:12px}.voiceRange input{width:100%;accent-color:var(--red2)}.voiceRange b{min-width:42px;text-align:right;color:var(--soft);font-size:.8rem}.miniStatus{font-size:.75rem;color:var(--dim);min-height:1.4em;margin-top:10px}.miniStatus.ok{color:#86efac}.miniStatus.bad{color:#fca5a5}[data-theme=light] .miniStatus.ok{color:#15803d}[data-theme=light] .miniStatus.bad{color:#b91c1c}.dev{font-size:.82rem;color:var(--dim);line-height:1.7}.dev a{color:var(--soft);text-decoration:underline;text-underline-offset:3px}.modal{position:fixed;inset:0;background:rgba(5,2,4,.72);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;z-index:200;padding:16px}.modal.hidden{display:none}.sheet{background:var(--panel);border:1px solid var(--border);border-radius:20px;max-width:460px;width:100%;padding:24px}.sheet h3{display:flex;align-items:center;gap:9px}.sheet h3 svg{color:var(--pink)}.hostGrid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}.hostGrid .wide{grid-column:1/-1}.hostGrid input[type=password],.hostGrid input[type=number]{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:12px;color:var(--text);padding:12px 13px;outline:none}@media(max-width:600px){.hostGrid{grid-template-columns:1fr}}.foot{text-align:center;color:var(--dim2);font-size:.75rem;padding:8px 20px 28px}@media(max-width:850px){.layout{grid-template-columns:1fr}.side{position:static}.grid{grid-template-columns:1fr}.top{padding:0 14px}.hero{padding:22px}.back span{display:none}}
</style>
</head>
<body>
<header class="top">
  <a class="brand" href="chat"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
  <div class="topnav">
    <button class="tb" id="themeBtn" type="button" title="Switch theme"><?= icon('sun', 16) ?></button>
    <a class="back" href="chat"><?= icon('chevron-right', 14) ?> <span>Back to chat</span></a>
  </div>
</header>

<div class="layout">
  <aside class="side">
    <a class="navitem on" href="#profile"><?= icon('user', 16) ?> Profile</a>
    <a class="navitem" href="#appearance"><?= icon('sun', 16) ?> Appearance</a>
    <a class="navitem" href="#voice"><?= icon('volume', 16) ?> Voice</a>
    <a class="navitem" href="#hosting"><?= icon('upload', 16) ?> Hosting (FTP / SFTP)</a>
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

      <section class="card" id="voice">
        <h2><?= icon('volume', 18) ?> Voice</h2>
        <p class="sub">Choose the assistant voice used for Read aloud and Live Voice Chat. Available voices depend on your browser/device.</p>
        <div class="voicePrefs">
          <label for="voiceSelect">Assistant voice</label>
          <select id="voiceSelect"><option value="__auto_indian">Auto Indian multilingual — recommended</option></select>
          <p class="sub" style="margin:-2px 0 2px">Auto mode prioritizes Indian voices and switches between Hindi, Indian English, Bengali, Tamil, Telugu and more when your browser provides them.</p>
          <label for="voiceLang">Voice input language</label>
          <select id="voiceLang"><option value="en-IN">English India / Hinglish</option><option value="hi-IN">Hindi India</option><option value="bn-IN">Bengali India</option><option value="ta-IN">Tamil India</option><option value="te-IN">Telugu India</option><option value="mr-IN">Marathi India</option><option value="gu-IN">Gujarati India</option><option value="kn-IN">Kannada India</option><option value="ml-IN">Malayalam India</option><option value="pa-IN">Punjabi India</option><option value="ur-IN">Urdu India</option></select>
          <label for="voiceRate">Speaking speed</label>
          <div class="voiceRange"><input id="voiceRate" type="range" min="0.75" max="1.35" step="0.05" value="1"><b id="voiceRateLabel">1.00×</b></div>
          <div class="voiceSample"><span>Preview: “Namaste, Devil AI ready hai. I can speak in an Indian voice.”</span><button class="btn ghost" id="voiceTest" type="button"><?= icon('play', 14) ?> Test</button></div>
          <div class="btnrow"><button class="btn primary" id="voiceSave" type="button"><?= icon('check', 15) ?> Save voice</button><button class="btn ghost" id="voiceDefault" type="button"><?= icon('volume-x', 15) ?> Reset</button></div>
          <div class="miniStatus" id="voiceStatus"></div>
        </div>
      </section>

      <section class="card" id="security-sessions"><h2>Active sessions</h2><p class="sub">Review devices signed in to your account and revoke unfamiliar sessions.</p><div id="securitySessions"><div class="sub">Loading sessions…</div></div><button class="btn danger" id="securityLogoutAll" type="button">Logout from all devices</button></section>

      <section class="card" id="security-history"><h2>Login history</h2><p class="sub">Recent sign-ins to your account. If you don’t recognize one, use “Logout from all devices” above.</p><div id="securityHistory"><div class="sub">Loading history…</div></div></section>

      <section class="card" id="security-alerts"><h2>Security alerts</h2><p class="sub">Sign-ins from devices we don’t recognize. If something looks wrong, log out everywhere right away.</p><div id="securityAlerts"><div class="sub">Loading alerts…</div></div><button class="btn danger" id="securityAlertLogoutAll" type="button">This wasn’t me — logout from all devices</button></section>

      <section class="card" id="hosting">
        <h2><?= icon('upload', 18) ?> Hosting (FTP / SFTP)</h2>
        <p class="sub">Connect your own web server. When you ask Devil Agent to publish a site, it uploads it to <b>remote folder / site-name /</b> on this server (it only adds or replaces files there — nothing is deleted). The password is stored encrypted and is never shown to the agent or in chats.</p>
        <div class="hostGrid">
          <div><label for="hProto">Protocol</label><select id="hProto"><option value="sftp">SFTP (SSH)</option><option value="ftp">FTP</option><option value="ftps">FTPS (FTP + TLS)</option></select></div>
          <div><label for="hPort">Port</label><input id="hPort" type="number" min="1" max="65535" placeholder="22"></div>
          <div class="wide"><label for="hHost">Server host</label><input id="hHost" type="text" maxlength="253" placeholder="example.com or 203.0.113.10" autocomplete="off" spellcheck="false"></div>
          <div><label for="hUser">Login user</label><input id="hUser" type="text" maxlength="128" autocomplete="off" spellcheck="false"></div>
          <div><label for="hPass">Password</label><input id="hPass" type="password" maxlength="256" autocomplete="new-password" placeholder=""></div>
          <div class="wide"><label for="hDir">Remote folder for sites</label><input id="hDir" type="text" maxlength="400" placeholder="/www/example.com/sites" spellcheck="false"></div>
          <div class="wide"><label for="hUrl">Public URL of that folder</label><input id="hUrl" type="text" maxlength="400" placeholder="https://example.com/sites" spellcheck="false"></div>
        </div>
        <div class="btnrow">
          <button class="btn primary" id="hSave" type="button"><?= icon('check', 15) ?> Save</button>
          <button class="btn ghost" id="hTest" type="button"><?= icon('server', 15) ?> Test connection</button>
          <button class="btn danger" id="hDel" type="button" hidden><?= icon('trash', 15) ?> Remove</button>
        </div>
        <div class="status" id="hStatus"></div>
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
          <button class="btn ghost" id="exportAllBtn" type="button"><?= icon('download', 15) ?> Download all my data</button>
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

function getCookie(name){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'=([^;]*)'));return m?decodeURIComponent(m[1]):null}
function prefRead(key){var v=null;try{v=localStorage.getItem(key)}catch(e){};if(v===null||v===''){v=window.devilCookieGet?window.devilCookieGet(key):getCookie(key)}return v}
function prefWrite(key,val){try{localStorage.setItem(key,val)}catch(e){};if(window.devilCookieSet){window.devilCookieSet(key,val,365)}else{document.cookie=key+'='+encodeURIComponent(val)+'; Max-Age=31536000; Path=/devil-ai/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'')}}
function prefDel(key){try{localStorage.removeItem(key)}catch(e){};if(window.devilCookieDel){window.devilCookieDel(key)}else{document.cookie=key+'=; Max-Age=0; Path=/devil-ai/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'')}}
function voiceKey(v){return (v.voiceURI||v.name||'')+'|'+(v.lang||'')}
function voicePref(){try{return JSON.parse(prefRead('devil_voice')||'{}')||{}}catch(e){return {}}}
var voiceSelect=$('#voiceSelect'), voiceLang=$('#voiceLang'), voiceRate=$('#voiceRate'), voiceRateLabel=$('#voiceRateLabel'), voiceStatus=$('#voiceStatus');
function setVoiceStatus(msg,cls){if(!voiceStatus){return}voiceStatus.className='miniStatus '+(cls||'');voiceStatus.textContent=msg||''}
function updateRateLabel(){if(voiceRateLabel&&voiceRate){voiceRateLabel.textContent=(parseFloat(voiceRate.value||'1')).toFixed(2)+'×'}}
function getVoices(){return (window.speechSynthesis&&speechSynthesis.getVoices)?speechSynthesis.getVoices():[]}
function isIndianVoice(v){var lang=String((v&&v.lang)||''),name=String((v&&v.name)||'');return /-IN\b/i.test(lang)||/(India|Indian|Hindi|Hindustan|Bengali|Bangla|Tamil|Telugu|Marathi|Gujarati|Kannada|Malayalam|Punjabi|Urdu|Ravi|Heera|Neerja|Kalpana|Hemant|Lekha|Priya)/i.test(name)}
function voiceScore(v,target){var lang=String((v&&v.lang)||''),base=target.split('-')[0],score=0;if(lang.toLowerCase()===target.toLowerCase()){score+=120}if(lang.split('-')[0].toLowerCase()===base.toLowerCase()){score+=70}if(/-IN\b/i.test(lang)){score+=45}if(isIndianVoice(v)){score+=25}if(/Google|Microsoft|Natural|Premium|Enhanced/i.test((v&&v.name)||'')){score+=8}if(v&&v.default){score+=2}return score}
function pickAutoVoice(target){var voices=getVoices().slice();if(!voices.length){return null}voices.sort(function(a,b){return voiceScore(b,target)-voiceScore(a,target)||String(a.name||'').localeCompare(String(b.name||''))});return voiceScore(voices[0],target)>0?voices[0]:null}
function populateVoices(){
  if(!voiceSelect){return}
  if(!('speechSynthesis' in window)){voiceSelect.innerHTML='<option value="">Voice selection is not supported in this browser</option>';voiceSelect.disabled=true;if(voiceLang){voiceLang.disabled=true}setVoiceStatus('Your browser does not expose voice choices.','bad');return}
  var pref=voicePref();
  var voices=getVoices().slice().sort(function(a,b){var ia=isIndianVoice(a)?0:1,ib=isIndianVoice(b)?0:1;if(ia!==ib){return ia-ib}return (a.lang||'').localeCompare(b.lang||'')||(a.name||'').localeCompare(b.name||'')});
  voiceSelect.innerHTML='';
  var autoOpt=document.createElement('option');autoOpt.value='__auto_indian';autoOpt.textContent='Auto Indian multilingual — recommended';autoOpt.dataset.mode='auto_indian';voiceSelect.appendChild(autoOpt);
  var browserOpt=document.createElement('option');browserOpt.value='';browserOpt.textContent='Default browser voice';browserOpt.dataset.mode='browser_default';voiceSelect.appendChild(browserOpt);
  voices.forEach(function(v){var o=document.createElement('option');o.value=voiceKey(v);o.textContent=(isIndianVoice(v)?'🇮🇳 ':'')+(v.name||'Voice')+' — '+(v.lang||'unknown')+(v.default?' • default':'');o.dataset.uri=v.voiceURI||'';o.dataset.name=v.name||'';o.dataset.lang=v.lang||'';voiceSelect.appendChild(o)});
  if(pref.mode==='browser_default'){voiceSelect.value=''}else if(pref.key&&pref.key!=='__auto_indian'){voiceSelect.value=pref.key}else{voiceSelect.value='__auto_indian'}
  if(!voiceSelect.value&&pref.uri){var wanted=voices.filter(function(v){return (v.voiceURI===pref.uri)||(v.name===pref.name&&v.lang===pref.lang)})[0];if(wanted){voiceSelect.value=voiceKey(wanted)}}
  if(voiceLang){voiceLang.value=pref.recLang||'en-IN'}
  if(voiceRate&&pref.rate){voiceRate.value=String(Math.min(1.35,Math.max(0.75,parseFloat(pref.rate)||1)))}
  updateRateLabel();
  var indian=voices.filter(isIndianVoice).length;
  setVoiceStatus(voices.length?('Loaded '+voices.length+' browser voices • '+indian+' Indian/multilingual preferred.'):('No extra voices found yet; auto mode will use your browser default.'),voices.length?'ok':'');
}
function selectedVoice(){var opt=voiceSelect&&voiceSelect.options[voiceSelect.selectedIndex];if(!opt){return {mode:'auto_indian',key:'__auto_indian'}}if(opt.value==='__auto_indian'){return {mode:'auto_indian',key:'__auto_indian',uri:'',name:'Auto Indian multilingual',lang:'en-IN'}}if(!opt.value){return {mode:'browser_default',key:'',uri:'',name:'',lang:''}}return {mode:'custom',key:opt.value,uri:opt.dataset.uri||'',name:opt.dataset.name||'',lang:opt.dataset.lang||''}}
function chooseSelectedVoice(){var pref=selectedVoice();if(pref.mode==='auto_indian'){return pickAutoVoice('en-IN')||pickAutoVoice('hi-IN')}if(pref.mode==='browser_default'){return null}return getVoices().filter(function(v){return (pref.uri&&v.voiceURI===pref.uri)||(v.name===pref.name&&v.lang===pref.lang)||voiceKey(v)===pref.key})[0]||null}
function speakSample(){if(!('speechSynthesis' in window)||!window.SpeechSynthesisUtterance){setVoiceStatus('Speech preview is not supported in this browser.','bad');return}try{speechSynthesis.cancel()}catch(e){}var u=new SpeechSynthesisUtterance('Namaste, Devil AI ready hai. I can speak in an Indian voice for Hindi, English and other Indian languages.');var v=chooseSelectedVoice();if(v){u.voice=v;u.lang=v.lang||'en-IN'}else{u.lang='en-IN'}u.rate=parseFloat(voiceRate&&voiceRate.value||'1')||1;u.onend=function(){setVoiceStatus('Preview complete.','ok')};u.onerror=function(){setVoiceStatus('Could not play that voice preview.','bad')};setVoiceStatus('Playing preview…');speechSynthesis.speak(u)}
if(voiceRate){voiceRate.addEventListener('input',updateRateLabel)}
if($('#voiceTest')){$('#voiceTest').addEventListener('click',speakSample)}
if($('#voiceSave')){$('#voiceSave').addEventListener('click',function(){var pref=selectedVoice();pref.rate=parseFloat(voiceRate&&voiceRate.value||'1')||1;pref.recLang=voiceLang&&voiceLang.value?voiceLang.value:'en-IN';pref.ts=Date.now();prefWrite('devil_voice',JSON.stringify(pref));setVoiceStatus('Voice saved. Indian/multilingual mode will be used in chat read-aloud and live voice.','ok')})}
if($('#voiceDefault')){$('#voiceDefault').addEventListener('click',function(){if(voiceSelect){voiceSelect.value='__auto_indian'}if(voiceLang){voiceLang.value='en-IN'}if(voiceRate){voiceRate.value='1'}updateRateLabel();var pref={mode:'auto_indian',key:'__auto_indian',uri:'',name:'Auto Indian multilingual',lang:'en-IN',recLang:'en-IN',rate:1,ts:Date.now()};prefWrite('devil_voice',JSON.stringify(pref));try{speechSynthesis.cancel()}catch(e){}setVoiceStatus('Reset to Auto Indian multilingual voice.','ok')})}
if(window.speechSynthesis&&typeof speechSynthesis.onvoiceschanged!=='undefined'){speechSynthesis.onvoiceschanged=populateVoices}
populateVoices();
setTimeout(populateVoices,350);
setTimeout(populateVoices,1200);

$('#cookieBtn').addEventListener('click',function(){if(window.devilOpenCookies){devilOpenCookies()}});
var modal=$('#deleteModal'), ms=$('#modalStatus');
$('#deleteBtn').addEventListener('click',function(){modal.classList.remove('hidden');ms.textContent='';$('#deleteCode').value=''});
$('#cancelDelete').addEventListener('click',function(){modal.classList.add('hidden')});
modal.addEventListener('click',function(e){if(e.target===modal){modal.classList.add('hidden')}});
$('#sendDeleteCode').addEventListener('click',function(){ms.className='status';ms.textContent='Sending code…';api('otp_request',{purpose:'delete'}).then(function(j){if(j.ok){ms.className='status ok';ms.textContent='Code sent to '+(j.masked||'your email')+'.'}else{ms.className='status bad';ms.textContent=j.error||'Could not send code.'}})});
$('#confirmDelete').addEventListener('click',function(){var code=$('#deleteCode').value.replace(/\D/g,'');if(code.length!==6){ms.className='status bad';ms.textContent='Enter the 6-digit code.';return}ms.className='status';ms.textContent='Deleting…';api('account_delete',{code:code}).then(function(j){if(j.ok){location.href='index.php'}else{ms.className='status bad';ms.textContent=j.error||'Delete failed.'}})});
})();
</script>
<script>
if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); }); }
</script>
<script>
(function(){var box=document.getElementById('securitySessions');if(!box)return;function esc(s){var d=document.createElement('span');d.textContent=String(s==null?'':s);return d.innerHTML}function load(){fetch('api.php?action=security_sessions').then(function(r){return r.json()}).then(function(j){if(!j.ok)throw 0;box.innerHTML=(j.sessions||[]).map(function(x){var text=esc(x.user_agent||'Unknown browser')+' · '+esc(x.ip||'Unknown IP')+' · '+esc(new Date((x.last_seen||0)*1000).toLocaleString());return '<div class="pillrow" style="justify-content:space-between;margin:9px 0"><span class="pill">'+text+(x.current?' · This device':'')+'</span>'+(x.current?'':'<button class="btn ghost revoke-session" data-id="'+esc(x.id)+'">Revoke</button>')+'</div>'}).join('')||'<div class="sub">No active sessions found.</div>';box.querySelectorAll('.revoke-session').forEach(function(b){b.onclick=function(){fetch('api.php?action=security_session_revoke',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:b.dataset.id})}).then(load)}})}).catch(function(){box.innerHTML='<div class="sub">Could not load sessions.</div>'})}document.getElementById('securityLogoutAll').onclick=function(){if(confirm('Logout from all devices?'))fetch('api.php?action=security_logout_all',{method:'POST'}).then(function(){location.href='login.php'})};load()})();
</script>
<script>
(function(){var box=document.getElementById('securityHistory');if(!box)return;function esc(s){var d=document.createElement('span');d.textContent=String(s==null?'':s);return d.innerHTML}function shortDevice(ua){ua=String(ua||'');var b=/Edg\//.test(ua)?'Edge':/Chrome\//.test(ua)?'Chrome':/Firefox\//.test(ua)?'Firefox':/Safari\//.test(ua)?'Safari':'';var o=/Windows/.test(ua)?'Windows':/Android/.test(ua)?'Android':/iPhone|iPad/.test(ua)?'iOS':/Mac OS/.test(ua)?'macOS':/Linux/.test(ua)?'Linux':'';return (b&&o)?(b+' on '+o):(b||o||'Unknown device')}function methodLabel(m){return ({email_code:'Email code',magic_link:'Magic link',github:'GitHub'})[String(m||'')]||'Sign-in'}function load(){fetch('api.php?action=security_login_history').then(function(r){return r.json()}).then(function(j){if(!j.ok)throw 0;var rows=(j.history||[]).map(function(x){var when=new Date((x.created||0)*1000).toLocaleString();return '<div class="pillrow" style="justify-content:space-between;margin:9px 0"><span class="pill">'+esc(methodLabel(x.method))+' · '+esc(shortDevice(x.user_agent))+' · '+esc(x.ip||'Unknown IP')+' · '+esc(when)+(x.new_device?' · <b style="color:var(--soft)">New device</b>':'')+'</span></div>'}).join('');box.innerHTML=rows||'<div class="sub">No sign-ins recorded yet.</div>'}).catch(function(){box.innerHTML='<div class="sub">Could not load login history.</div>'})}load()})();
</script>
<script>
(function(){var box=document.getElementById('securityAlerts');if(!box)return;function esc(s){var d=document.createElement('span');d.textContent=String(s==null?'':s);return d.innerHTML}function shortDevice(ua){ua=String(ua||'');var b=/Edg\//.test(ua)?'Edge':/Chrome\//.test(ua)?'Chrome':/Firefox\//.test(ua)?'Firefox':/Safari\//.test(ua)?'Safari':'';var o=/Windows/.test(ua)?'Windows':/Android/.test(ua)?'Android':/iPhone|iPad/.test(ua)?'iOS':/Mac OS/.test(ua)?'macOS':/Linux/.test(ua)?'Linux':'';return (b&&o)?(b+' on '+o):(b||o||'Unknown device')}function typeLabel(t){return ({new_device:'New device sign-in'})[String(t||'')]||'Security alert'}function load(){fetch('api.php?action=security_alerts').then(function(r){return r.json()}).then(function(j){if(!j.ok)throw 0;var rows=(j.alerts||[]).map(function(x){var d=x.details||{};var when=new Date((x.created||0)*1000).toLocaleString();return '<div class="pillrow" style="justify-content:space-between;margin:9px 0"><span class="pill">'+esc(typeLabel(x.type))+' · '+esc(shortDevice(d.user_agent||''))+' · '+esc(d.ip||'Unknown IP')+' · '+esc(when)+'</span><button class="btn ghost alert-dismiss" data-id="'+esc(x.id)+'">Dismiss</button></div>'}).join('');box.innerHTML=rows||'<div class="sub">No security alerts — you’re all clear.</div>';box.querySelectorAll('.alert-dismiss').forEach(function(b){b.onclick=function(){fetch('api.php?action=security_alert_dismiss',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:b.dataset.id})}).then(load)}})}).catch(function(){box.innerHTML='<div class="sub">Could not load alerts.</div>'})}document.getElementById('securityAlertLogoutAll').onclick=function(){if(confirm('Logout from all devices? This ends every session, including this one.'))fetch('api.php?action=security_logout_all',{method:'POST'}).then(function(){location.href='login.php'})};load()})();
(function(){var b=document.getElementById('exportAllBtn');if(!b)return;b.onclick=function(){b.disabled=true;var old=b.innerHTML;b.innerHTML='Preparing export…';fetch('api.php?action=security_export').then(function(r){if(!r.ok)throw 0;return r.blob()}).then(function(blob){var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='devil-ai-export-'+(new Date().toISOString().slice(0,19).replace(/[:T]/g,'-'))+'.json';document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(a.href)},4000);b.disabled=false;b.innerHTML=old}).catch(function(){b.disabled=false;b.innerHTML=old;alert('Could not prepare the export. Please try again.')})}})();
</script>
<script>
(function(){
  var $=function(id){return document.getElementById(id)};
  if(!$('hSave'))return;
  var st=$('hStatus'), saved=null;
  function say(m,c){st.className='status '+(c||'');st.textContent=m||''}
  function call(a,b){return fetch('api.php?action='+a,{method:b?'POST':'GET',headers:{'Content-Type':'application/json'},body:b?JSON.stringify(b):undefined}).then(function(r){return r.json()}).catch(function(){return{ok:false,error:'Network error.'}})}
  function form(){return{protocol:$('hProto').value,host:$('hHost').value.trim(),port:parseInt($('hPort').value,10)||0,username:$('hUser').value.trim(),password:$('hPass').value,remote_dir:$('hDir').value.trim(),public_url:$('hUrl').value.trim()}}
  function fill(h){saved=h;$('hDel').hidden=!h;if(!h)return;$('hProto').value=h.protocol;$('hHost').value=h.host;$('hPort').value=h.port;$('hUser').value=h.username;$('hDir').value=h.remote_dir;$('hUrl').value=h.public_url||'';$('hPass').value='';$('hPass').placeholder=h.has_password?'•••••••• (saved — leave empty to keep)':''}
  $('hProto').addEventListener('change',function(){var p=$('hPort');if(!p.value||p.value==='22'||p.value==='21'){p.value=this.value==='sftp'?22:21}});
  function busy(b){['hSave','hTest','hDel'].forEach(function(i){$(i).disabled=b})}
  call('host_get').then(function(j){if(j.ok){fill(j.hosting);var c=j.caps||{},miss=['sftp','ftp','ftps'].filter(function(k){return!c[k]});if(miss.length)say('Note: this server cannot use '+miss.join(', ').toUpperCase()+'.','')}});
  $('hTest').addEventListener('click',function(){busy(true);say('Connecting…');call('host_test',form()).then(function(j){busy(false);if(j.ok){say('✓ Connected. '+(j.note||(j.count!==undefined?('Folder has '+j.count+' item(s)'+(j.entries&&j.entries.length?': '+j.entries.slice(0,6).join(', ')+(j.count>6?' …':''):'')+'.'):'')),'ok')}else say(j.error||'Connection failed.','bad')})});
  $('hSave').addEventListener('click',function(){busy(true);say('Checking the connection and saving…');call('host_save',form()).then(function(j){busy(false);if(j.ok){fill(j.hosting);say('✓ Saved. '+(j.note||'Devil Agent can now deploy sites to this server.'),'ok')}else say(j.error||'Could not save.','bad')})});
  $('hDel').addEventListener('click',function(){if(!confirm('Remove this hosting server from Devil AI? (Files already on the server stay.)'))return;busy(true);call('host_delete',{}).then(function(j){busy(false);if(j.ok){fill(null);['hHost','hPort','hUser','hPass','hDir','hUrl'].forEach(function(i){$(i).value=''});$('hPass').placeholder='';say('Removed.','ok')}else say(j.error||'Could not remove.','bad')})});
})();
</script>
</body>
</html>
