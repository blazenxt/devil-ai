<?php
require_once __DIR__ . '/inc/developer_console.php';
$recaptchaSiteKey = function_exists('devil_security_recaptcha_site_key') ? devil_security_recaptcha_site_key() : '';
$recaptchaV2SiteKey = function_exists('devil_security_recaptcha_v2_site_key') ? devil_security_recaptcha_v2_site_key() : '';
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
  <?php if ($turnstileSiteKey !== ''): ?><div id="turnstileInvisibleHost" class="cf-turnstile-host"></div><?php endif; ?>
  <?php if ($recaptchaSiteKey !== ''): ?><div id="recaptchaV3Host" class="recaptcha-host"></div><?php endif; ?>
  <div class="row"><input class="field" id="apiKeyName" type="text" maxlength="48" placeholder="Key name, e.g. Production server"><button class="btn primary" id="apiCreate" type="button"<?php if ($turnstileSiteKey !== '' || $recaptchaSiteKey !== ''): ?> disabled<?php endif; ?>><?= icon('plus', 15) ?> Create key</button></div>
  <div class="captcha-note" id="captchaNote"><?php if ($turnstileSiteKey !== '' || $recaptchaSiteKey !== ''): ?>Checking security…<?php else: ?>Protected by security verification.<?php endif; ?></div>
  <div class="captcha-visible hidden" id="captchaBox">
    <p class="captcha-note warn" id="captchaBoxNote">Security check failed — please complete the verification below.</p>
    <div id="captchaTurnstile"></div>
    <div id="captchaRecaptcha"></div>
  </div>
  <div class="tokenBox" id="apiTokenBox"><p class="sub" style="margin-bottom:8px">Copy this key now. You won’t be able to see it again.</p><code id="apiToken"></code><div class="row" style="margin-top:10px"><button class="btn ghost" id="apiCopy" type="button"><?= icon('copy', 14) ?> Copy key</button></div></div>
  <div class="status" id="apiStatus"></div>
</section>

<section class="card">
  <h2><?= icon('key', 18) ?> Your keys</h2>
  <p class="sub">Revoke keys you no longer use. Apps using revoked keys will stop working.</p>
  <div class="keyList" id="apiList"><div class="sub">Loading API keys…</div></div>
</section>
<style>.recaptcha-host,.cf-turnstile-host{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;opacity:0!important;pointer-events:none!important}.grecaptcha-badge{visibility:hidden!important;opacity:0!important}.captcha-note{font-size:.68rem;color:var(--dim2);text-align:center;margin-top:8px}.captcha-note.warn{color:#fca5a5}.btn:disabled{opacity:.55;cursor:not-allowed}.captcha-visible{margin-top:14px;border:1px solid var(--border);border-radius:14px;padding:16px;background:var(--panel2);display:flex;flex-direction:column;align-items:center;gap:10px}.captcha-visible.hidden{display:none}</style>
<script src="assets/captcha.js"></script>
<?php if ($turnstileSiteKey !== ''): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=devilCaptchaTurnstileApiReady&render=explicit" async defer></script>
<?php endif; ?>
<script>window.DEVIL_DEV_RECAPTCHA_SITE_KEY = <?= json_encode($recaptchaSiteKey) ?>; window.DEVIL_DEV_RECAPTCHA_V2_SITE_KEY = <?= json_encode($recaptchaV2SiteKey) ?>; window.DEVIL_DEV_TURNSTILE_SITE_KEY = <?= json_encode($turnstileSiteKey) ?>;</script>
<?php if ($recaptchaSiteKey !== '' || $recaptchaV2SiteKey !== ''): ?>
<script src="https://www.google.com/recaptcha/api.js?onload=devilCaptchaRecaptchaApiReady&render=explicit" async defer onerror="window.devilRecaptchaLoadFailed=1"></script>
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
var cap=null;
if(window.DevilCaptcha&&(window.DEVIL_DEV_TURNSTILE_SITE_KEY||window.DEVIL_DEV_RECAPTCHA_SITE_KEY)){
  cap=DevilCaptcha.create({
    turnstileSiteKey:window.DEVIL_DEV_TURNSTILE_SITE_KEY||'',
    recaptchaSiteKey:window.DEVIL_DEV_RECAPTCHA_SITE_KEY||'',
    recaptchaV2SiteKey:window.DEVIL_DEV_RECAPTCHA_V2_SITE_KEY||'',
    action:'dev_key_create',
    button:'#apiCreate',
    note:'#captchaNote',
    box:'#captchaBox',
    boxNote:'#captchaBoxNote',
    tsHost:'#turnstileInvisibleHost',
    recHost:'#recaptchaV3Host',
    v2Host:'#captchaRecaptcha',
    visibleHost:'#captchaTurnstile'
  });
}
function createKey(){
  var name=D.$('#apiKeyName').value;
  if(cap&&!cap.ready()){D.setStatus('#apiStatus','Please complete the security check first.','bad');return}
  D.setStatus('#apiStatus','Security check…');
  if(cap){cap.retry=createKey}
  var go=function(tk){D.setStatus('#apiStatus','Creating API key…');D.api('dev_key_create',{name:name,recaptcha_token:tk.rec,recaptcha_v2_token:tk.v2,turnstile_token:tk.ts}).then(function(j){if(j.ok){if(cap){cap.resetAfterUse();cap.retry=null}D.$('#apiTokenBox').classList.add('show');D.$('#apiToken').textContent=j.token||'';D.$('#apiKeyName').value='';D.setStatus('#apiStatus','API key created. Copy it now.','ok');loadKeys()}else{if(cap){cap.handleResponse(j)}D.setStatus('#apiStatus',j.error||'Could not create API key.','bad')}})};
  if(cap){cap.ensure('dev_key_create').then(go)}else{go({ts:'',rec:'',v2:''})}
}
D.$('#apiCreate').addEventListener('click',createKey);
D.$('#apiCopy').addEventListener('click',function(){var t=D.$('#apiToken').textContent;if(!t)return;if(navigator.clipboard){navigator.clipboard.writeText(t).then(function(){D.setStatus('#apiStatus','Copied API key.','ok')}).catch(function(){D.setStatus('#apiStatus','Copy failed — select manually.','bad')})}else{D.setStatus('#apiStatus','Select and copy the key manually.','bad')}});
loadKeys();
})();
JS);
