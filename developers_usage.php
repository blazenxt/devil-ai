<?php
require_once __DIR__ . '/inc/developer_console.php';
dev_console_start('Usage', 'usage');
?>
<section class="hero">
  <h1><?= icon('gauge', 28) ?> Usage</h1>
  <p>Monitor API activity across all keys. Developer API requests are unlimited; this page only shows usage counts.</p>
</section>

<section class="grid">
  <div class="card"><h2><?= icon('key', 18) ?> Active keys</h2><div class="metric" id="mActive">—</div><p class="sub">Currently usable keys.</p></div>
  <div class="card"><h2><?= icon('trash', 18) ?> Revoked</h2><div class="metric" id="mRevoked">—</div><p class="sub">Keys no longer usable.</p></div>
  <div class="card"><h2><?= icon('gauge', 18) ?> Total requests</h2><div class="metric" id="mRequests">—</div><p class="sub">All-time requests.</p></div>
  <div class="card"><h2><?= icon('loader', 18) ?> This hour</h2><div class="metric" id="mHour">—</div><p class="sub" id="mLimit">Limit: Unlimited</p></div>
</section>

<section class="card">
  <h2><?= icon('server', 18) ?> Per-key usage</h2>
  <p class="sub">Shows total calls, current hour usage, and last-used time.</p>
  <div class="usageList" id="usageList"><div class="sub">Loading usage…</div></div>
</section>
<?php
dev_console_end(<<<'JS'
(function(){
var D=window.DevilDev;
D.apiGet('dev_usage').then(function(j){
  if(!j.ok){D.$('#usageList').innerHTML='<div class="sub">Could not load usage.</div>';return;}
  var s=j.summary||{};
  D.$('#mActive').textContent=D.fmt(s.active_keys);
  D.$('#mRevoked').textContent=D.fmt(s.revoked_keys);
  D.$('#mRequests').textContent=D.fmt(s.total_requests);
  D.$('#mHour').textContent=D.fmt(s.used_this_hour);
  D.$('#mLimit').textContent='Limit: Unlimited';
  var box=D.$('#usageList'); box.innerHTML='';
  var top=document.createElement('div'); top.className='usageItem'; top.innerHTML='<div><b>Total usage</b><span>'+D.fmt(s.total_requests)+' requests · '+D.fmt(s.used_this_hour)+' this hour · last used '+D.dt(s.last_used)+'</span></div>'; box.appendChild(top);
  (j.keys||[]).forEach(function(k){var row=document.createElement('div');row.className='usageItem';row.innerHTML='<div><b></b><span></span></div>';row.querySelector('b').textContent=k.name||'API key';row.querySelector('span').textContent=D.fmt(k.requests)+' total · '+D.fmt(k.used_this_hour)+' this hour · last used '+D.dt(k.last_used)+(k.revoked?' · revoked':'');box.appendChild(row)});
});
})();
JS);
