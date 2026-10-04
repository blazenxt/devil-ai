<?php
/** Developer Console shared shell for Devil AI */
if (defined('DEVIL_DEVELOPER_CONSOLE')) { return; }
define('DEVIL_DEVELOPER_CONSOLE', 1);

require_once __DIR__ . '/session.php';
devil_session_boot();
require_once __DIR__ . '/icons.php';

function dev_console_load_json(string $path): array {
    if (!is_readable($path)) { return []; }
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}

function dev_console_user(): ?array {
    $uid = isset($_SESSION['devil_uid']) ? (string)$_SESSION['devil_uid'] : '';
    if ($uid === '') { return null; }
    $users = dev_console_load_json(dirname(__DIR__) . '/data/users.json');
    return isset($users[$uid]) && is_array($users[$uid]) ? $users[$uid] : null;
}

function dev_console_require_user(): array {
    $u = dev_console_user();
    if (!$u) { header('Location: login.php'); exit; }
    return $u;
}

function dev_console_base_path(): string {
    $base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return $base === '/' ? '' : $base;
}

function dev_console_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https://' : 'http://') . $host . dev_console_base_path();
}

function dev_console_nav(string $active): void {
    $items = [
        'dashboard'  => ['href' => 'developers.php',             'icon' => 'gauge',          'label' => 'Dashboard'],
        'keys'       => ['href' => 'developers_keys.php',        'icon' => 'key',            'label' => 'API Keys'],
        'playground' => ['href' => 'developers_playground.php',  'icon' => 'play',           'label' => 'Playground'],
        'models'     => ['href' => 'developers_models.php',      'icon' => 'server',         'label' => 'Models'],
        'usage'      => ['href' => 'developers_usage.php',       'icon' => 'gauge',          'label' => 'Usage'],
        'docs'       => ['href' => 'developers_docs.php',        'icon' => 'code',           'label' => 'Docs'],
    ];
    foreach ($items as $key => $item) {
        $on = $key === $active ? ' on' : '';
        echo '<a class="navitem' . $on . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES) . '">' . icon($item['icon'], 16) . ' ' . htmlspecialchars($item['label']) . '</a>';
    }
}

function dev_console_start(string $title, string $active): void {
    $me = dev_console_require_user();
    $baseUrl = dev_console_base_url();
    $name = (string)($me['name'] ?? 'Developer');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<script>
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}document.documentElement.setAttribute('data-theme',t);})();
</script>
<title><?= htmlspecialchars($title) ?> — Devil AI</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0c0709;--bg2:#100a0d;--panel:#171014;--panel2:#1d1216;--panel3:#241721;--border:rgba(244,63,94,.18);--border-hi:rgba(244,63,94,.5);--red:#e11d48;--red2:#f43f5e;--pink:#fb7185;--soft:#fda4af;--text:#efe6ea;--dim:#a8929b;--dim2:#7c5b63;--ok:#86efac;--bad:#fca5a5;--sans:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif}
[data-theme=light]{--bg:#faf9f7;--bg2:#f4f2ee;--panel:#fff;--panel2:#f0ede9;--panel3:#e7e3dd;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f;--ok:#15803d;--bad:#b91c1c}
html{scroll-behavior:smooth;overflow-x:hidden}body{min-height:100dvh;overflow-x:hidden;background:radial-gradient(1000px 500px at 70% -10%,rgba(225,29,72,.13),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans);line-height:1.6}a{text-decoration:none;color:inherit}button,input,textarea,select{font:inherit}button{cursor:pointer;background:none;border:none;color:inherit}.top{height:66px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 22px;border-bottom:1px solid var(--border);background:rgba(12,7,9,.72);backdrop-filter:blur(14px);position:sticky;top:0;z-index:80;min-width:0}[data-theme=light] .top{background:rgba(250,249,247,.86)}.brand{display:flex;align-items:center;gap:10px;font-weight:850;min-width:0;white-space:nowrap}.brand img{width:30px;height:30px;filter:drop-shadow(0 0 8px rgba(244,63,94,.45));flex-shrink:0}.topnav{display:flex;gap:8px;align-items:center;flex-shrink:0}.topbtn{display:inline-flex;align-items:center;gap:7px;color:var(--dim);font-size:.84rem;padding:8px 10px;border-radius:10px;white-space:nowrap}.topbtn:hover{background:rgba(244,63,94,.1);color:var(--soft)}.layout{max-width:1220px;margin:0 auto;display:grid;grid-template-columns:260px minmax(0,1fr);gap:22px;padding:24px 20px 48px;min-width:0}.side{position:sticky;top:90px;height:max-content;background:var(--panel);border:1px solid var(--border);border-radius:20px;padding:10px;box-shadow:0 18px 60px rgba(0,0,0,.25);min-width:0}.navitem{display:flex;align-items:center;gap:10px;width:100%;padding:11px 12px;border-radius:12px;color:var(--dim);font-size:.88rem;white-space:nowrap}.navitem:hover,.navitem.on{background:rgba(244,63,94,.12);color:var(--text)}.main{display:flex;flex-direction:column;gap:18px;min-width:0}.main section{scroll-margin-top:86px}.hero{background:linear-gradient(135deg,rgba(244,63,94,.16),transparent 62%),var(--panel);border:1px solid var(--border);border-radius:26px;padding:28px;box-shadow:0 24px 80px rgba(0,0,0,.3);min-width:0}.hero h1{font-size:clamp(1.8rem,4vw,2.7rem);letter-spacing:-.045em;display:flex;align-items:center;gap:10px;flex-wrap:wrap}.hero p{color:var(--dim);max-width:790px;margin-top:8px}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.grid.two{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.card{background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:22px;box-shadow:0 18px 60px rgba(0,0,0,.22);min-width:0}.card h2{font-size:1.02rem;display:flex;align-items:center;gap:9px;margin-bottom:7px;min-width:0}.card h2 svg,.hero svg{color:var(--pink);flex-shrink:0}.metric{font-size:1.75rem;font-weight:850;letter-spacing:-.04em;margin-top:8px}.sub{font-size:.82rem;color:var(--dim);line-height:1.55;margin-bottom:14px}.pill{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--border);border-radius:999px;padding:7px 10px;color:var(--dim);background:var(--panel2);font-size:.76rem;margin:4px 6px 4px 0;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pill svg{flex-shrink:0}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;padding:10px 14px;font-weight:750;font-size:.84rem;transition:.15s;white-space:nowrap}.btn.primary{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 8px 22px rgba(244,63,94,.28)}.btn.ghost{border:1px solid var(--border);color:var(--soft)}.btn.ghost:hover{background:rgba(244,63,94,.1);border-color:var(--border-hi)}.btn.danger{border:1px solid rgba(248,113,113,.38);background:rgba(190,18,60,.14);color:#fca5a5}.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;min-width:0}.row .field{flex:1 1 220px}.field{width:100%;min-width:0;background:var(--panel2);border:1px solid var(--border);border-radius:12px;color:var(--text);padding:12px 13px;outline:none}.field:focus{border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.08)}textarea.field{min-height:150px;resize:vertical}.status{font-size:.78rem;color:var(--dim);min-height:1.4em;margin-top:10px}.status.ok{color:var(--ok)}.status.bad{color:var(--bad)}.tokenBox{display:none;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.28);border-radius:14px;padding:12px;margin-top:12px;min-width:0}.tokenBox.show{display:block}.code,.tokenBox code{display:block;max-width:100%;background:var(--bg2);border:1px solid var(--border);border-radius:13px;padding:13px;color:#f3d0d7;font-family:ui-monospace,Consolas,monospace;font-size:.78rem;line-height:1.55;white-space:pre;overflow:auto;-webkit-overflow-scrolling:touch}[data-theme=light] .code,[data-theme=light] .tokenBox code{color:#43323a}.keyList,.modelList,.usageList{display:grid;gap:9px}.keyItem,.modelItem,.usageItem,.quickCard{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--panel2);border:1px solid var(--border);border-radius:14px;padding:12px;min-width:0}.quickCard{align-items:flex-start;flex-direction:column;transition:.15s}.quickCard:hover{border-color:var(--border-hi);transform:translateY(-1px)}.keyItem.revoked{opacity:.58}.keyItem>div,.modelItem>div,.usageItem>div{min-width:0}.keyItem b,.modelItem b,.usageItem b,.quickCard b{display:block;font-size:.88rem;overflow:hidden;text-overflow:ellipsis}.keyItem span,.modelItem span,.usageItem span,.quickCard span{display:block;color:var(--dim);font-size:.72rem;margin-top:2px;overflow-wrap:anywhere}.mini{padding:7px 10px;border-radius:10px;border:1px solid var(--border);color:var(--soft);font-size:.74rem;font-weight:800;flex-shrink:0}.mini:hover{background:rgba(244,63,94,.1)}.playOut{min-height:170px;white-space:pre-wrap}.docsGrid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px}.foot{text-align:center;color:var(--dim2);font-size:.75rem;padding:10px 0 4px}
@media(max-width:980px){.layout{display:block;padding:16px 14px 42px}.side{position:sticky;top:66px;z-index:60;display:flex;gap:6px;overflow-x:auto;overflow-y:hidden;white-space:nowrap;border-radius:16px;padding:8px;margin:0 0 16px;box-shadow:0 12px 36px rgba(0,0,0,.24);scrollbar-width:none}.side::-webkit-scrollbar{display:none}.navitem{width:auto;flex:0 0 auto;padding:9px 11px}.main{gap:16px}.main section{scroll-margin-top:132px}.grid{grid-template-columns:1fr 1fr}.docsGrid,.grid.two,.grid.three{grid-template-columns:1fr}.hero{padding:24px}.card{padding:20px}}
@media(max-width:620px){.top{height:58px;padding:0 12px}.brand{font-size:.98rem;gap:8px}.brand img{width:27px;height:27px}.topnav{gap:4px}.topbtn{padding:8px;border-radius:9px}.topbtn span{display:none}.layout{padding:12px 10px 34px}.side{top:58px;margin-left:-2px;margin-right:-2px;border-radius:14px}.navitem{font-size:.8rem;padding:8px 10px;gap:7px}.hero{padding:18px;border-radius:20px}.hero h1{font-size:1.7rem;line-height:1.12}.hero p{font-size:.86rem}.card{padding:16px;border-radius:18px}.grid{grid-template-columns:1fr;gap:12px}.metric{font-size:1.55rem}.keyItem,.modelItem,.usageItem{align-items:flex-start;flex-direction:column}.mini{width:100%;text-align:center}.row{align-items:stretch;gap:8px}.row .field{flex-basis:100%}.row .btn{width:100%}.btn{white-space:normal}.code,.tokenBox code{font-size:.72rem;padding:11px}.playOut{min-height:140px}.pill{border-radius:14px;white-space:normal;text-overflow:clip}}
@media(max-width:380px){.hero h1{font-size:1.48rem}.card h2{font-size:.95rem}.sub{font-size:.78rem}.navitem svg{width:14px;height:14px}.navitem{font-size:.76rem}}
</style>
</head>
<body>
<header class="top">
  <a class="brand" href="developers.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil API</a>
  <div class="topnav">
    <button class="topbtn" id="themeBtn" type="button" title="Switch theme"><?= icon('sun', 16) ?></button>
    <a class="topbtn" href="settings.php"><?= icon('settings', 15) ?> <span>Settings</span></a>
    <a class="topbtn" href="app.php"><?= icon('chevron-right', 14) ?> <span>Chat</span></a>
  </div>
</header>
<div class="layout">
  <aside class="side"><?php dev_console_nav($active); ?></aside>
  <main class="main">
<?php
}

function dev_console_end(string $pageScript = ''): void {
    $baseUrl = dev_console_base_url();
?>
    <div class="foot">Devil AI Developer Console • Developed by BlazeNXT</div>
  </main>
</div>
<script>
(function(){
'use strict';
var SUN=<?= json_encode(icon('sun', 16)) ?>, MOON=<?= json_encode(icon('moon', 16)) ?>;
var $=function(s){return document.querySelector(s)};
var $$=function(s){return Array.prototype.slice.call(document.querySelectorAll(s))};
function api(action, body, method){return fetch('api.php?action='+action,{method:method||'POST',headers:{'Content-Type':'application/json'},body:method==='GET'?undefined:JSON.stringify(body||{})}).then(function(r){return r.json()}).catch(function(){return{ok:false,error:'Network error'}})}
function apiGet(action){return api(action,null,'GET')}
function cur(){return document.documentElement.getAttribute('data-theme')==='light'?'light':'dark'}
function setTheme(t){document.documentElement.setAttribute('data-theme',t);try{localStorage.setItem('devil_theme',t);document.cookie='devil_theme='+encodeURIComponent(t)+'; Max-Age=31536000; Path=/devil-ai/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'')}catch(e){}syncTheme()}
function syncTheme(){var b=$('#themeBtn');if(b){b.innerHTML=cur()==='dark'?SUN:MOON}}
function fmt(n){return (n||0).toLocaleString()}
function dt(ts){if(!ts){return 'never'}try{return new Date(ts*1000).toLocaleString()}catch(e){return '—'}}
function setStatus(id,msg,cls){var el=$(id);if(!el)return;el.className='status '+(cls||'');el.textContent=msg||''}
$('#themeBtn').addEventListener('click',function(){setTheme(cur()==='dark'?'light':'dark')});syncTheme();
window.DevilDev={api:api,apiGet:apiGet,$:$,$$:$$,fmt:fmt,dt:dt,setStatus:setStatus,baseUrl:<?= json_encode($baseUrl . '/v1') ?>};
})();
</script>
<?php if ($pageScript !== '') { echo "\n<scr" . "ipt>\n" . $pageScript . "\n</scr" . "ipt>\n"; } ?>
</body>
</html>
<?php
}
