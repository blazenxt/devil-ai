<?php
require_once __DIR__ . '/inc/developer_console.php';
$baseUrl = dev_console_base_url();
dev_console_start('Developer Dashboard', 'dashboard');
?>
<section class="hero">
  <h1><?= icon('code', 28) ?> Developer Dashboard</h1>
  <p>Manage Devil AI developer access from a dedicated multi-page console: keys, usage, models, docs, and a live playground.</p>
  <div style="margin-top:14px"><span class="pill" id="basePill"><?= icon('server', 14) ?> <?= htmlspecialchars($baseUrl) ?>/v1</span><span class="pill"><?= icon('shield-check', 14) ?> Bearer token auth</span><span class="pill"><?= icon('message-circle', 14) ?> Chat completions</span></div>
</section>

<section class="grid">
  <div class="card"><h2><?= icon('key', 18) ?> Active keys</h2><div class="metric" id="mActive">—</div><p class="sub">Keys currently usable by apps.</p></div>
  <div class="card"><h2><?= icon('gauge', 18) ?> Requests</h2><div class="metric" id="mRequests">—</div><p class="sub">Total API requests across keys.</p></div>
  <div class="card"><h2><?= icon('server', 18) ?> Models</h2><div class="metric" id="mModels">3</div><p class="sub">Available Devil models.</p></div>
  <div class="card"><h2><?= icon('loader', 18) ?> This hour</h2><div class="metric" id="mHour">—</div><p class="sub" id="mLimit">Rate limit loading…</p></div>
</section>

<section class="card">
  <h2><?= icon('sparkles', 18) ?> Quick actions</h2>
  <p class="sub">Jump straight into the part you need.</p>
  <div class="grid three">
    <a class="quickCard" href="developers_keys.php"><b><?= icon('key', 15) ?> Create API keys</b><span>Generate, copy, and revoke developer tokens.</span></a>
    <a class="quickCard" href="developers_playground.php"><b><?= icon('play', 15) ?> Open Playground</b><span>Test models without writing code.</span></a>
    <a class="quickCard" href="developers_docs.php"><b><?= icon('code', 15) ?> Read Docs</b><span>Copy curl and JavaScript examples.</span></a>
  </div>
</section>

<section class="card">
  <h2><?= icon('gauge', 18) ?> Recent usage</h2>
  <p class="sub">Live summary from your API keys.</p>
  <div class="usageList" id="dashUsage"><div class="sub">Loading usage…</div></div>
</section>
<?php
dev_console_end(<<<'JS'
(function(){
var D=window.DevilDev;
D.apiGet('dev_usage').then(function(j){
  if(!j.ok){ D.$('#dashUsage').innerHTML='<div class="sub">Could not load usage.</div>'; return; }
  var s=j.summary||{};
  D.$('#mActive').textContent=D.fmt(s.active_keys);
  D.$('#mRequests').textContent=D.fmt(s.total_requests);
  D.$('#mHour').textContent=D.fmt(s.used_this_hour);
  D.$('#mLimit').textContent='Limit: '+D.fmt(s.rate_per_hour)+'/hour';
  var box=D.$('#dashUsage'); box.innerHTML='';
  var total=document.createElement('div'); total.className='usageItem';
  total.innerHTML='<div><b>Total usage</b><span>'+D.fmt(s.total_requests)+' requests · '+D.fmt(s.used_this_hour)+' this hour · last used '+D.dt(s.last_used)+'</span></div>';
  box.appendChild(total);
  (j.keys||[]).filter(function(k){return !k.revoked}).slice(0,4).forEach(function(k){
    var row=document.createElement('div'); row.className='usageItem';
    row.innerHTML='<div><b></b><span></span></div>';
    row.querySelector('b').textContent=k.name||'API key';
    row.querySelector('span').textContent=D.fmt(k.requests)+' total · '+D.fmt(k.used_this_hour)+' this hour · last used '+D.dt(k.last_used);
    box.appendChild(row);
  });
});
})();
JS);
