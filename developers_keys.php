<?php
require_once __DIR__ . '/inc/developer_console.php';
$recaptchaSiteKey = function_exists('devil_security_recaptcha_site_key') ? devil_security_recaptcha_site_key() : '';
$turnstileSiteKey = function_exists('devil_security_turnstile_site_key') ? devil_security_turnstile_site_key() : '';
dev_console_start('API Keys', 'keys');
?>
<section class="hero">
  <h1><?= icon('key', 28) ?> API Keys</h1>
  <p>Create and manage API keys for server-side apps. New keys are shown once, so copy them immediately and keep them secret.</p>
</section>

<section class="card">
  <h2><?= icon('plus', 18) ?> Create key</h2>
  <p class="sub">Use a clear name so you can identify this key later.</p>
  <?php if ($turnstileSiteKey !== ''): ?><div class="cf-turnstile" data-size="invisible" data-sitekey="<?= htmlspecialchars($turnstileSiteKey, ENT_QUOTES) ?>" data-callback="devilDevTurnstileReady"></div><?php endif; ?>
  <div class="row"><input class="field" id="apiKeyName" type="text" maxlength="48" placeholder="Key name, e.g. Production server"><button class="btn primary" id="apiCreate" type="button"><?= icon('plus', 15) ?> Create key</button></div>
  <div class="tokenBox" id="apiTokenBox"><p class="sub" style="margin-bottom:8px">Copy this key now. You won’t be able to see it again.</p><code id="apiToken"></code><div class="row" style="margin-top:10px"><button class="btn ghost" id="apiCopy" type="button"><?= icon('copy', 14) ?> Copy key</button></div></div>
  <div class="status" id="apiStatus"></div>
</section>

<section class="card">
  <h2><?= icon('key', 18) ?> Your keys</h2>
  <p class="sub">Revoke keys you no longer use. Apps using revoked keys will stop working.</p>
  <div class="keyList" id="apiList"><div class="sub">Loading API keys…</div></div>
</section>
<style>.cf-turnstile{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;opacity:0!important;pointer-events:none!important}.grecaptcha-badge{visibility:hidden!important;opacity:0!important}.captcha-note{font-size:.68rem;color:var(--dim2);text-align:center;margin-top:8px}</style>
<?php if ($turnstileSiteKey !== ''): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?php if ($recaptchaSiteKey !== ''): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptchaSiteKey, ENT_QUOTES) ?>" async defer></script>
<script>window.DEVIL_DEV_RECAPTCHA_SITE_KEY = <?= json_encode($recaptchaSiteKey) ?>;</script>
<?php endif; ?>
<?php
dev_console_end(<<<'JS'
(function(){
var D=window.DevilDev;
function renderKeys(keys){
  var box=D.$('#apiList'); box.innerHTML='';
  if(!keys||!keys.length){box.innerHTML='<div class="sub">No API keys yet. Create one to start building.</div>';return;}
  keys.forEach(function(k){
    var row=document.createElement('div'); row.className='keyItem'+(k.revoked?' revoked':'');
    var info=document.createElement('div'); info.innerHTML='<b></b><span></span>';
    info.querySelector('b').textContent=k.name||'API key';
    info.querySelector('span').textContent=(k.prefix||'devil_blazenxt_…')+'••••'+(k.last4||'')+' · '+(k.revoked?'revoked':'created '+D.dt(k.created))+' · last used '+D.dt(k.last_used)+' · '+D.fmt(k.requests)+' calls';
    row.appendChild(info);
    if(!k.revoked){var b=document.createElement('button');b.className='mini';b.type='button';b.textContent='Revoke';b.addEventListener('click',function(){if(!confirm('Revoke this API key? Apps using it will stop working.'))return;D.setStatus('#apiStatus','Revoking…');D.api('dev_key_revoke',{id:k.id}).then(function(j){if(j.ok){D.setStatus('#apiStatus','API key revoked.','ok');loadKeys()}else{D.setStatus('#apiStatus',j.error||'Could not revoke key.','bad')}})});row.appendChild(b);}
    box.appendChild(row);
  });
}
function loadKeys(){D.apiGet('dev_keys').then(function(j){if(j.ok){renderKeys(j.keys||[])}else{D.$('#apiList').innerHTML='<div class="sub">Could not load API keys.</div>';D.setStatus('#apiStatus',j.error||'Could not load keys.','bad')}})}
var TURNSTILE_TOKEN=''; window.devilDevTurnstileReady=function(t){TURNSTILE_TOKEN=t||'';};
function recaptchaToken(action){
  var siteKey=window.DEVIL_DEV_RECAPTCHA_SITE_KEY||'';
  if(!siteKey){return Promise.resolve('')}
  return new Promise(function(resolve){
    var tries=0;
    function run(){
      if(window.grecaptcha&&grecaptcha.execute){try{grecaptcha.ready(function(){grecaptcha.execute(siteKey,{action:action||'dev_key_create'}).then(resolve).catch(function(){resolve('')})})}catch(e){resolve('')}}
      else if(tries++<40){setTimeout(run,100)}
      else{resolve('')}
    }
    run();
  })
}
D.$('#apiCreate').addEventListener('click',function(){var name=D.$('#apiKeyName').value;D.setStatus('#apiStatus','Security check…');recaptchaToken('dev_key_create').then(function(token){D.setStatus('#apiStatus','Creating API key…');D.api('dev_key_create',{name:name,recaptcha_token:token,turnstile_token:TURNSTILE_TOKEN}).then(function(j){if(j.ok){D.$('#apiTokenBox').classList.add('show');D.$('#apiToken').textContent=j.token||'';D.$('#apiKeyName').value='';D.setStatus('#apiStatus','API key created. Copy it now.','ok');loadKeys()}else{D.setStatus('#apiStatus',j.error||'Could not create API key.','bad')}})})});
D.$('#apiCopy').addEventListener('click',function(){var t=D.$('#apiToken').textContent;if(!t)return;if(navigator.clipboard){navigator.clipboard.writeText(t).then(function(){D.setStatus('#apiStatus','Copied API key.','ok')}).catch(function(){D.setStatus('#apiStatus','Copy failed — select manually.','bad')})}else{D.setStatus('#apiStatus','Select and copy the key manually.','bad')}});
loadKeys();
})();
JS);
