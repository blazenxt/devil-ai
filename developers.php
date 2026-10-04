<?php
/** Devil AI — Developer Console */
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
require_once __DIR__ . '/inc/icons.php';

function dev_console_load_json(string $path): array {
    if (!is_readable($path)) { return []; }
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}
function dev_console_user(): ?array {
    $uid = isset($_SESSION['devil_uid']) ? (string)$_SESSION['devil_uid'] : '';
    if ($uid === '') { return null; }
    $users = dev_console_load_json(__DIR__ . '/data/users.json');
    return isset($users[$uid]) && is_array($users[$uid]) ? $users[$uid] : null;
}
$me = dev_console_user();
if (!$me) { header('Location: login.php'); exit; }
$basePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
if ($basePath === '/') { $basePath = ''; }
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$baseUrl = ($https ? 'https://' : 'http://') . $host . $basePath;
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
<title>Developer Console — Devil AI</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}:root{--bg:#0c0709;--bg2:#100a0d;--panel:#171014;--panel2:#1d1216;--panel3:#241721;--border:rgba(244,63,94,.18);--border-hi:rgba(244,63,94,.5);--red:#e11d48;--red2:#f43f5e;--pink:#fb7185;--soft:#fda4af;--text:#efe6ea;--dim:#a8929b;--dim2:#7c5b63;--ok:#86efac;--bad:#fca5a5;--sans:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif}[data-theme=light]{--bg:#faf9f7;--bg2:#f4f2ee;--panel:#fff;--panel2:#f0ede9;--panel3:#e7e3dd;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f;--ok:#15803d;--bad:#b91c1c}body{min-height:100dvh;background:radial-gradient(1000px 500px at 70% -10%,rgba(225,29,72,.13),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans);line-height:1.6}a{text-decoration:none;color:inherit}button,input,textarea,select{font:inherit}button{cursor:pointer;background:none;border:none;color:inherit}.top{height:66px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;border-bottom:1px solid var(--border);background:rgba(12,7,9,.72);backdrop-filter:blur(14px);position:sticky;top:0;z-index:50}[data-theme=light] .top{background:rgba(250,249,247,.84)}.brand{display:flex;align-items:center;gap:10px;font-weight:850}.brand img{width:30px;height:30px;filter:drop-shadow(0 0 8px rgba(244,63,94,.45))}.topnav{display:flex;gap:10px;align-items:center}.topbtn{display:inline-flex;align-items:center;gap:7px;color:var(--dim);font-size:.84rem;padding:8px 10px;border-radius:10px}.topbtn:hover{background:rgba(244,63,94,.1);color:var(--soft)}.layout{max-width:1220px;margin:0 auto;display:grid;grid-template-columns:260px 1fr;gap:22px;padding:24px 20px 48px}.side{position:sticky;top:90px;height:max-content;background:var(--panel);border:1px solid var(--border);border-radius:20px;padding:10px;box-shadow:0 18px 60px rgba(0,0,0,.25)}.navitem{display:flex;align-items:center;gap:10px;width:100%;padding:11px 12px;border-radius:12px;color:var(--dim);font-size:.88rem}.navitem:hover,.navitem.on{background:rgba(244,63,94,.12);color:var(--text)}.main{display:flex;flex-direction:column;gap:18px}.hero{background:linear-gradient(135deg,rgba(244,63,94,.16),transparent 62%),var(--panel);border:1px solid var(--border);border-radius:26px;padding:28px;box-shadow:0 24px 80px rgba(0,0,0,.3)}.hero h1{font-size:clamp(1.8rem,4vw,2.7rem);letter-spacing:-.045em}.hero p{color:var(--dim);max-width:790px;margin-top:8px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.grid.two{grid-template-columns:1fr 1fr}.card{background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:22px;box-shadow:0 18px 60px rgba(0,0,0,.22)}.card h2{font-size:1.02rem;display:flex;align-items:center;gap:9px;margin-bottom:7px}.card h2 svg,.hero svg{color:var(--pink)}.metric{font-size:1.75rem;font-weight:850;letter-spacing:-.04em;margin-top:8px}.sub{font-size:.82rem;color:var(--dim);line-height:1.55;margin-bottom:14px}.pill{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--border);border-radius:999px;padding:7px 10px;color:var(--dim);background:var(--panel2);font-size:.76rem;margin:4px 6px 4px 0}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;padding:10px 14px;font-weight:750;font-size:.84rem;transition:.15s}.btn.primary{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 8px 22px rgba(244,63,94,.28)}.btn.ghost{border:1px solid var(--border);color:var(--soft)}.btn.ghost:hover{background:rgba(244,63,94,.1);border-color:var(--border-hi)}.btn.danger{border:1px solid rgba(248,113,113,.38);background:rgba(190,18,60,.14);color:#fca5a5}.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.field{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:12px;color:var(--text);padding:12px 13px;outline:none}.field:focus{border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.08)}textarea.field{min-height:150px;resize:vertical}.status{font-size:.78rem;color:var(--dim);min-height:1.4em;margin-top:10px}.status.ok{color:var(--ok)}.status.bad{color:var(--bad)}.tokenBox{display:none;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.28);border-radius:14px;padding:12px;margin-top:12px}.tokenBox.show{display:block}.code,.tokenBox code{display:block;background:var(--bg2);border:1px solid var(--border);border-radius:13px;padding:13px;color:#f3d0d7;font-family:ui-monospace,Consolas,monospace;font-size:.78rem;line-height:1.55;white-space:pre;overflow:auto}[data-theme=light] .code,[data-theme=light] .tokenBox code{color:#43323a}.keyList,.modelList,.usageList{display:grid;gap:9px}.keyItem,.modelItem,.usageItem{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--panel2);border:1px solid var(--border);border-radius:14px;padding:12px}.keyItem.revoked{opacity:.58}.keyItem b,.modelItem b,.usageItem b{display:block;font-size:.88rem}.keyItem span,.modelItem span,.usageItem span{display:block;color:var(--dim);font-size:.72rem;margin-top:2px}.mini{padding:7px 10px;border-radius:10px;border:1px solid var(--border);color:var(--soft);font-size:.74rem;font-weight:800}.mini:hover{background:rgba(244,63,94,.1)}.playOut{min-height:170px;white-space:pre-wrap}.docsGrid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.foot{text-align:center;color:var(--dim2);font-size:.75rem;padding:10px 0 4px}@media(max-width:980px){.layout{grid-template-columns:1fr}.side{position:static}.grid{grid-template-columns:1fr 1fr}.docsGrid,.grid.two{grid-template-columns:1fr}}@media(max-width:620px){.top{padding:0 14px}.topbtn span{display:none}.layout{padding:18px 12px 40px}.hero,.card{padding:18px}.grid{grid-template-columns:1fr}.keyItem,.modelItem,.usageItem{align-items:flex-start;flex-direction:column}.row{align-items:stretch}.row .btn{width:100%}}
</style>
</head>
<body>
<header class="top">
  <a class="brand" href="app.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil API</a>
  <div class="topnav">
    <button class="topbtn" id="themeBtn" type="button" title="Switch theme"><?= icon('sun', 16) ?></button>
    <a class="topbtn" href="settings.php"><?= icon('settings', 15) ?> <span>Settings</span></a>
    <a class="topbtn" href="app.php"><?= icon('chevron-right', 14) ?> <span>Chat</span></a>
  </div>
</header>
<div class="layout">
  <aside class="side">
    <a class="navitem on" href="#dashboard"><?= icon('gauge', 16) ?> Dashboard</a>
    <a class="navitem" href="#keys"><?= icon('key', 16) ?> API Keys</a>
    <a class="navitem" href="#playground"><?= icon('play', 16) ?> Playground</a>
    <a class="navitem" href="#models"><?= icon('server', 16) ?> Models</a>
    <a class="navitem" href="#usage"><?= icon('gauge', 16) ?> Usage</a>
    <a class="navitem" href="#docs"><?= icon('code', 16) ?> Docs</a>
  </aside>
  <main class="main">
    <section class="hero" id="dashboard">
      <h1><?= icon('code', 28) ?> Developer Console</h1>
      <p>Welcome, <?= htmlspecialchars($name) ?>. Manage Devil AI API keys, test prompts in the playground, monitor usage, and integrate OpenAI-compatible endpoints into your apps.</p>
      <div style="margin-top:14px"><span class="pill" id="basePill"><?= icon('server', 14) ?> <?= htmlspecialchars($baseUrl) ?>/v1</span><span class="pill"><?= icon('shield-check', 14) ?> Bearer token auth</span><span class="pill"><?= icon('message-circle', 14) ?> Chat completions</span></div>
    </section>

    <section class="grid">
      <div class="card"><h2><?= icon('key', 18) ?> Active keys</h2><div class="metric" id="mActive">—</div><p class="sub">Keys currently usable by apps.</p></div>
      <div class="card"><h2><?= icon('gauge', 18) ?> Requests</h2><div class="metric" id="mRequests">—</div><p class="sub">Total API requests across keys.</p></div>
      <div class="card"><h2><?= icon('server', 18) ?> Models</h2><div class="metric" id="mModels">3</div><p class="sub">Available Devil models.</p></div>
      <div class="card"><h2><?= icon('loader', 18) ?> This hour</h2><div class="metric" id="mHour">—</div><p class="sub" id="mLimit">Rate limit loading…</p></div>
    </section>

    <section class="card" id="keys">
      <h2><?= icon('key', 18) ?> API Keys</h2>
      <p class="sub">Create secret keys for apps and servers. New keys are shown once — copy immediately.</p>
      <div class="row"><input class="field" id="apiKeyName" type="text" maxlength="48" placeholder="Key name, e.g. Production server"><button class="btn primary" id="apiCreate" type="button"><?= icon('plus', 15) ?> Create key</button></div>
      <div class="tokenBox" id="apiTokenBox"><p class="sub" style="margin-bottom:8px">Copy this key now. You won’t be able to see it again.</p><code id="apiToken"></code><div class="row" style="margin-top:10px"><button class="btn ghost" id="apiCopy" type="button"><?= icon('copy', 14) ?> Copy key</button></div></div>
      <div class="status" id="apiStatus"></div>
      <div class="keyList" id="apiList"><div class="sub">Loading API keys…</div></div>
    </section>

    <section class="card" id="playground">
      <h2><?= icon('play', 18) ?> Playground</h2>
      <p class="sub">Test Devil AI before integrating. Playground uses your logged-in account, not an API key.</p>
      <div class="grid two">
        <div>
          <label class="sub" for="pgModel" style="display:block;margin-bottom:6px">Model</label>
          <select class="field" id="pgModel"><option value="devil-flash">Devil Flash</option><option value="devil-pro">Devil Pro</option><option value="devil-ultra">Devil Ultra</option></select>
          <label class="sub" for="pgSystem" style="display:block;margin:12px 0 6px">Optional developer instruction</label>
          <input class="field" id="pgSystem" type="text" maxlength="1200" placeholder="e.g. Reply in Hinglish">
          <label class="sub" for="pgPrompt" style="display:block;margin:12px 0 6px">Prompt</label>
          <textarea class="field" id="pgPrompt" maxlength="4000" placeholder="Ask Devil AI anything…"></textarea>
          <div class="row" style="margin-top:12px"><button class="btn primary" id="pgRun" type="button"><?= icon('play', 15) ?> Run</button><button class="btn ghost" id="pgClear" type="button"><?= icon('x', 14) ?> Clear</button></div>
          <div class="status" id="pgStatus"></div>
        </div>
        <div>
          <label class="sub" style="display:block;margin-bottom:6px">Response</label>
          <div class="code playOut" id="pgOut">Response will appear here…</div>
        </div>
      </div>
    </section>

    <section class="card" id="models">
      <h2><?= icon('server', 18) ?> Models Available</h2>
      <p class="sub">Use these model ids in API requests.</p>
      <div class="modelList" id="modelList"></div>
    </section>

    <section class="card" id="usage">
      <h2><?= icon('gauge', 18) ?> Usage</h2>
      <p class="sub">Per-key usage summary. Rate limit is currently applied hourly.</p>
      <div class="usageList" id="usageList"><div class="sub">Loading usage…</div></div>
    </section>

    <section class="card" id="docs">
      <h2><?= icon('code', 18) ?> Docs</h2>
      <p class="sub">OpenAI-compatible JSON endpoints. Use API keys only on your server, never in public frontend code.</p>
      <div class="docsGrid">
        <div><h2 style="font-size:.95rem"><?= icon('server', 16) ?> List models</h2><div class="code">curl <?= htmlspecialchars($baseUrl) ?>/v1/models \
  -H "Authorization: Bearer dv_live_YOUR_KEY"</div></div>
        <div><h2 style="font-size:.95rem"><?= icon('message-circle', 16) ?> Chat completion</h2><div class="code">curl <?= htmlspecialchars($baseUrl) ?>/v1/chat/completions \
  -H "Authorization: Bearer dv_live_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "devil-flash",
    "messages": [
      {"role": "user", "content": "Explain APIs in Hinglish."}
    ]
  }'</div></div>
      </div>
      <h2 style="font-size:.95rem;margin-top:16px"><?= icon('code', 16) ?> JavaScript server example</h2>
      <div class="code">const res = await fetch('<?= htmlspecialchars($baseUrl) ?>/v1/chat/completions', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + process.env.DEVIL_API_KEY,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    model: 'devil-pro',
    messages: [{ role: 'user', content: 'Write a launch tweet.' }]
  })
});
const data = await res.json();
console.log(data.choices[0].message.content);</div>
    </section>
    <div class="foot">Devil AI Developer Console • Developed by BlazeNXT</div>
  </main>
</div>
<script>
(function(){
'use strict';
var $=function(s){return document.querySelector(s)};
var $$=function(s){return Array.prototype.slice.call(document.querySelectorAll(s))};
function api(action, body, method){return fetch('api.php?action='+action,{method:method||'POST',headers:{'Content-Type':'application/json'},body:method==='GET'?undefined:JSON.stringify(body||{})}).then(function(r){return r.json()}).catch(function(){return{ok:false,error:'Network error'}})}
function apiGet(action){return api(action,null,'GET')}
function cur(){return document.documentElement.getAttribute('data-theme')==='light'?'light':'dark'}
function setTheme(t){document.documentElement.setAttribute('data-theme',t);try{localStorage.setItem('devil_theme',t);document.cookie='devil_theme='+encodeURIComponent(t)+'; Max-Age=31536000; Path=/devil-ai/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'')}catch(e){}syncTheme()}
function syncTheme(){var b=$('#themeBtn');if(b){b.innerHTML=cur()==='dark'?<?= json_encode(icon('sun', 16)) ?>:<?= json_encode(icon('moon', 16)) ?>}}
$('#themeBtn').addEventListener('click',function(){setTheme(cur()==='dark'?'light':'dark')});syncTheme();
$$('.navitem').forEach(function(a){a.addEventListener('click',function(){ $$('.navitem').forEach(function(x){x.classList.remove('on')}); a.classList.add('on') })});
function fmt(n){return (n||0).toLocaleString()}
function dt(ts){if(!ts){return 'never'}try{return new Date(ts*1000).toLocaleString()}catch(e){return '—'}}
function setStatus(id,msg,cls){var el=$(id);if(!el)return;el.className='status '+(cls||'');el.textContent=msg||''}
function renderKeys(keys){var box=$('#apiList');box.innerHTML='';if(!keys||!keys.length){box.innerHTML='<div class="sub">No API keys yet. Create one to start building.</div>';return}keys.forEach(function(k){var row=document.createElement('div');row.className='keyItem'+(k.revoked?' revoked':'');var info=document.createElement('div');info.innerHTML='<b></b><span></span>';info.querySelector('b').textContent=k.name||'API key';info.querySelector('span').textContent=(k.prefix||'dv_live_…')+'••••'+(k.last4||'')+' · '+(k.revoked?'revoked':'created '+dt(k.created))+' · last used '+dt(k.last_used)+' · '+fmt(k.requests)+' calls';row.appendChild(info);if(!k.revoked){var b=document.createElement('button');b.className='mini';b.type='button';b.textContent='Revoke';b.addEventListener('click',function(){if(!confirm('Revoke this API key? Apps using it will stop working.'))return;setStatus('#apiStatus','Revoking…');api('dev_key_revoke',{id:k.id}).then(function(j){if(j.ok){setStatus('#apiStatus','API key revoked.','ok');loadConsole()}else{setStatus('#apiStatus',j.error||'Could not revoke key.','bad')}})});row.appendChild(b)}box.appendChild(row)});}
function renderModels(models){var box=$('#modelList');box.innerHTML='';var desc={"devil-flash":"Fast, lightweight model for everyday chat and app responses.","devil-pro":"Balanced model for deeper writing, reasoning, and production UX.","devil-ultra":"Most powerful Devil model for complex, high-quality responses."};(models||[]).forEach(function(m){var row=document.createElement('div');row.className='modelItem';var info=document.createElement('div');info.innerHTML='<b></b><span></span>';info.querySelector('b').textContent=(m.label||m.id)+' · '+m.id;info.querySelector('span').textContent=desc[m.id]||'Devil AI model';row.appendChild(info);box.appendChild(row)});}
function renderUsage(keys, summary){var box=$('#usageList');box.innerHTML='';var top=document.createElement('div');top.className='usageItem';top.innerHTML='<div><b>Total usage</b><span>'+fmt(summary.total_requests)+' requests · '+fmt(summary.used_this_hour)+' this hour · limit '+fmt(summary.rate_per_hour)+'/hour</span></div>';box.appendChild(top);(keys||[]).forEach(function(k){var row=document.createElement('div');row.className='usageItem';row.innerHTML='<div><b></b><span></span></div>';row.querySelector('b').textContent=k.name||'API key';row.querySelector('span').textContent=fmt(k.requests)+' total · '+fmt(k.used_this_hour)+' this hour · last used '+dt(k.last_used)+(k.revoked?' · revoked':'');box.appendChild(row)});}
function loadConsole(){apiGet('dev_usage').then(function(j){if(!j.ok){setStatus('#apiStatus',j.error||'Could not load developer console.','bad');return}var s=j.summary||{};$('#mActive').textContent=fmt(s.active_keys);$('#mRequests').textContent=fmt(s.total_requests);$('#mHour').textContent=fmt(s.used_this_hour);$('#mLimit').textContent='Limit: '+fmt(s.rate_per_hour)+'/hour';if(j.base_url){$('#basePill').innerHTML='<?= str_replace("'", "\\'", icon('server', 14)) ?> '+j.base_url}renderKeys(j.keys||[]);renderModels(j.models||[]);renderUsage(j.keys||[],s)})}
$('#apiCreate').addEventListener('click',function(){var name=$('#apiKeyName').value;setStatus('#apiStatus','Creating API key…');api('dev_key_create',{name:name}).then(function(j){if(j.ok){$('#apiTokenBox').classList.add('show');$('#apiToken').textContent=j.token||'';$('#apiKeyName').value='';setStatus('#apiStatus','API key created. Copy it now.','ok');loadConsole()}else{setStatus('#apiStatus',j.error||'Could not create API key.','bad')}})});
$('#apiCopy').addEventListener('click',function(){var t=$('#apiToken').textContent;if(!t)return;if(navigator.clipboard){navigator.clipboard.writeText(t).then(function(){setStatus('#apiStatus','Copied API key.','ok')}).catch(function(){setStatus('#apiStatus','Copy failed — select manually.','bad')})}else{setStatus('#apiStatus','Select and copy the key manually.','bad')}});
$('#pgRun').addEventListener('click',function(){var msg=$('#pgPrompt').value.trim();if(!msg){setStatus('#pgStatus','Enter a prompt first.','bad');return}$('#pgOut').textContent='Thinking…';setStatus('#pgStatus','Running playground…');api('dev_playground',{model:$('#pgModel').value,system:$('#pgSystem').value,message:msg}).then(function(j){if(j.ok){$('#pgOut').textContent=j.reply||'';setStatus('#pgStatus','Done · '+((j.usage&&j.usage.total_tokens)||0)+' estimated tokens','ok')}else{$('#pgOut').textContent='';setStatus('#pgStatus',j.error||'Playground failed.','bad')}})});
$('#pgClear').addEventListener('click',function(){$('#pgPrompt').value='';$('#pgSystem').value='';$('#pgOut').textContent='Response will appear here…';setStatus('#pgStatus','')});
loadConsole();
})();
</script>
</body>
</html>
