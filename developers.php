<?php
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
require_once __DIR__ . '/inc/icons.php';
$basePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
if ($basePath === '/') { $basePath = ''; }
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$baseUrl = ($https ? 'https://' : 'http://') . $host . $basePath;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<script>
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}if(t!=='light'&&t!=='dark'){t='dark'}document.documentElement.setAttribute('data-theme',t);})();
</script>
<title>Developer API — Devil AI</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}:root{--bg:#0c0709;--panel:#171014;--panel2:#1d1216;--panel3:#241721;--border:rgba(244,63,94,.18);--border-hi:rgba(244,63,94,.5);--red:#e11d48;--red2:#f43f5e;--pink:#fb7185;--soft:#fda4af;--text:#efe6ea;--dim:#a8929b;--dim2:#7c5b63;--sans:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif}[data-theme=light]{--bg:#faf9f7;--panel:#fff;--panel2:#f0ede9;--panel3:#e7e3dd;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f}body{min-height:100dvh;background:radial-gradient(900px 480px at 70% -10%,rgba(225,29,72,.13),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans);line-height:1.6}a{color:inherit;text-decoration:none}.top{height:66px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;border-bottom:1px solid var(--border);background:rgba(12,7,9,.72);backdrop-filter:blur(14px);position:sticky;top:0;z-index:10}[data-theme=light] .top{background:rgba(250,249,247,.82)}.brand{display:flex;align-items:center;gap:10px;font-weight:800}.brand img{width:30px;height:30px;filter:drop-shadow(0 0 8px rgba(244,63,94,.45))}.nav{display:flex;gap:10px;align-items:center}.btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:12px;padding:9px 13px;color:var(--soft);font-weight:700;font-size:.84rem}.btn:hover{background:rgba(244,63,94,.1);border-color:var(--border-hi)}.wrap{max-width:1060px;margin:0 auto;padding:32px 20px 52px}.hero{background:linear-gradient(135deg,rgba(244,63,94,.14),transparent 62%),var(--panel);border:1px solid var(--border);border-radius:26px;padding:30px;box-shadow:0 24px 80px rgba(0,0,0,.28)}.hero h1{font-size:clamp(1.8rem,4vw,2.7rem);letter-spacing:-.04em}.hero p{color:var(--dim);max-width:760px;margin-top:10px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:20px}.card{background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:22px;box-shadow:0 18px 60px rgba(0,0,0,.20)}.card h2{font-size:1.05rem;display:flex;align-items:center;gap:9px;margin-bottom:8px}.card h2 svg,.hero svg{color:var(--pink)}.sub{font-size:.86rem;color:var(--dim);margin-bottom:14px}.code{position:relative;background:#100a0d;border:1px solid var(--border);border-radius:15px;padding:15px;overflow:auto;color:#f3d0d7;font-family:ui-monospace,Consolas,monospace;font-size:.79rem;line-height:1.55;white-space:pre}[data-theme=light] .code{background:#f4f1ed;color:#43323a}.pill{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--border);border-radius:999px;padding:7px 10px;color:var(--dim);background:var(--panel2);font-size:.78rem;margin:4px 6px 4px 0}.list{padding-left:20px;color:var(--dim);font-size:.88rem}.list li{margin:5px 0}.foot{text-align:center;color:var(--dim2);font-size:.75rem;margin-top:24px}@media(max-width:820px){.grid{grid-template-columns:1fr}.top{padding:0 14px}.wrap{padding:22px 14px 42px}.hero{padding:24px}.nav .btn span{display:none}}
</style>
</head>
<body>
<header class="top">
  <a class="brand" href="app.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
  <div class="nav"><a class="btn" href="settings.php#developer"><?= icon('key', 15) ?> <span>API keys</span></a><a class="btn" href="app.php"><?= icon('chevron-right', 14) ?> <span>Back to chat</span></a></div>
</header>
<main class="wrap">
  <section class="hero">
    <h1><?= icon('code', 28) ?> Devil AI Developer API</h1>
    <p>Build apps with Devil AI using a simple OpenAI-compatible chat endpoint. Create a key from <b>Account settings → Developer API</b>, then call the endpoints below with a Bearer token.</p>
    <div style="margin-top:14px"><span class="pill"><?= icon('server', 14) ?> Base URL: <?= htmlspecialchars($baseUrl) ?>/v1</span><span class="pill"><?= icon('key', 14) ?> Auth: Bearer API key</span><span class="pill"><?= icon('check', 14) ?> JSON responses</span></div>
  </section>

  <div class="grid">
    <section class="card">
      <h2><?= icon('server', 18) ?> Models</h2>
      <p class="sub">List available Devil AI models.</p>
      <div class="code">curl <?= htmlspecialchars($baseUrl) ?>/v1/models \
  -H "Authorization: Bearer dv_live_YOUR_KEY"</div>
      <p class="sub" style="margin-top:14px">Supported model ids: <b>devil-flash</b>, <b>devil-pro</b>, <b>devil-ultra</b>.</p>
    </section>

    <section class="card">
      <h2><?= icon('message-circle', 18) ?> Chat completions</h2>
      <p class="sub">OpenAI-style request/response for text chat.</p>
      <div class="code">curl <?= htmlspecialchars($baseUrl) ?>/v1/chat/completions \
  -H "Authorization: Bearer dv_live_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "devil-flash",
    "messages": [
      {"role": "user", "content": "Write a one-line startup pitch."}
    ]
  }'</div>
    </section>

    <section class="card">
      <h2><?= icon('code', 18) ?> JavaScript example</h2>
      <p class="sub">Use server-side code for production. Do not expose keys in public frontend code.</p>
      <div class="code">const res = await fetch('<?= htmlspecialchars($baseUrl) ?>/v1/chat/completions', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer dv_live_YOUR_KEY',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    model: 'devil-pro',
    messages: [{ role: 'user', content: 'Explain APIs in Hinglish.' }]
  })
});
const data = await res.json();
console.log(data.choices[0].message.content);</div>
    </section>

    <section class="card">
      <h2><?= icon('shield-check', 18) ?> Notes</h2>
      <ul class="list">
        <li>Keys are shown only once. Revoke lost keys from Account settings.</li>
        <li>Streaming is not enabled yet; send normal JSON requests.</li>
        <li>Image data URLs are accepted in OpenAI-style content parts for vision-capable routing.</li>
        <li>Devil AI safety and identity rules always remain active.</li>
      </ul>
    </section>
  </div>
  <div class="foot">Devil AI Developer API • Developed by BlazeNXT</div>
</main>
</body>
</html>
