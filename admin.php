<?php
/**
 * DEVIL AI — Admin Panel
 * Password access + logged-in admin email access.
 */
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
require_once __DIR__ . '/inc/icons.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0709">
<meta name="robots" content="noindex">
<link rel="manifest" href="manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Devil AI">
<script>/* theme boot */
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();</script>
<title>Admin — Devil AI</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0c0709;--panel:#171014;--panel2:#1d1216;--panel3:#241721;--border:rgba(244,63,94,.18);--border-hi:rgba(244,63,94,.5);--red:#e11d48;--red2:#f43f5e;--pink:#fb7185;--soft:#fda4af;--text:#efe6ea;--dim:#a8929b;--dim2:#7c5b63;--good:#86efac;--bad:#fca5a5;--sans:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif}
[data-theme=light]{--bg:#faf9f7;--panel:#fff;--panel2:#f0ede9;--panel3:#e7e3dd;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f;--good:#15803d;--bad:#b91c1c}
body{min-height:100dvh;display:flex;flex-direction:column;background:radial-gradient(1100px 500px at 80% -10%,rgba(225,29,72,.13),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans)}
[data-theme=light] body{background:radial-gradient(1100px 500px at 80% -10%,rgba(225,29,72,.06),transparent 60%),var(--bg)}
a{text-decoration:none;color:inherit}button{font:inherit;cursor:pointer}button,input,select,textarea{font:inherit}
.top{display:flex;justify-content:space-between;align-items:center;padding:18px 22px;gap:14px}.brand{display:flex;align-items:center;gap:9px;font-weight:800}.brand img{width:28px;height:28px;filter:drop-shadow(0 0 8px rgba(244,63,94,.5))}.right{display:flex;align-items:center;gap:10px}.top a.back,.top button.tb{font-size:.84rem;color:var(--dim);display:inline-flex;align-items:center;gap:6px}.top a.back:hover,.top button.tb:hover{color:var(--soft)}.top button.tb{border:none;background:none;padding:6px}
main{flex:1;width:100%;max-width:1180px;margin:0 auto;padding:18px}.shell{display:grid;grid-template-columns:250px minmax(0,1fr);gap:16px}.side,.card{background:var(--panel);border:1px solid var(--border);border-radius:22px;box-shadow:0 24px 70px rgba(0,0,0,.28)}[data-theme=light] .side,[data-theme=light] .card{box-shadow:0 24px 70px rgba(120,80,90,.12)}.side{padding:14px;align-self:start;position:sticky;top:12px}.side h2{font-size:.86rem;color:var(--soft);display:flex;gap:8px;align-items:center;margin:4px 6px 12px}.tab{width:100%;border:1px solid transparent;background:transparent;color:var(--dim);border-radius:13px;padding:11px 12px;display:flex;align-items:center;gap:9px;text-align:left;font-weight:700;margin:4px 0}.tab:hover{background:rgba(244,63,94,.08);color:var(--soft)}.tab.on{background:rgba(244,63,94,.14);border-color:var(--border);color:var(--text)}
.card{padding:24px}.hero{margin-bottom:18px}.hero h1{font-size:1.35rem;display:flex;align-items:center;gap:10px}.hero h1 svg{color:var(--pink)}.sub{color:var(--dim);font-size:.84rem;line-height:1.55;margin-top:6px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.mini{background:var(--panel2);border:1px solid var(--border);border-radius:16px;padding:14px}.mini b{font-size:.78rem;color:var(--soft);display:flex;align-items:center;gap:7px}.mini strong{font-size:1.45rem;display:block;margin-top:8px}.section{display:none}.section.on{display:block}
label{display:block;font-size:.74rem;font-weight:700;color:var(--soft);margin:15px 0 6px;letter-spacing:.3px}select,input,textarea{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:11px;color:var(--text);padding:11px 13px;font-size:.88rem;outline:none;transition:.15s}select:focus,input:focus,textarea:focus{border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.08)}textarea{min-height:92px;resize:vertical;line-height:1.5}select option{background:var(--panel2)}.hint{font-size:.72rem;color:var(--dim2);line-height:1.55;margin-top:6px}.divider{border:none;border-top:1px solid var(--border);margin:20px 0}.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.switchrow{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid var(--border);background:var(--panel2);border-radius:14px;padding:12px 13px;margin-top:10px}.switchrow span{min-width:0}.switchrow b{display:block;font-size:.84rem}.switchrow small{color:var(--dim);line-height:1.45}.switch{width:48px;height:28px;border-radius:999px;background:var(--panel3);border:1px solid var(--border);position:relative;flex-shrink:0}.switch:before{content:'';position:absolute;width:22px;height:22px;left:3px;top:2px;border-radius:50%;background:var(--dim);transition:.16s}.switch.on{background:rgba(244,63,94,.22);border-color:var(--border-hi)}.switch.on:before{left:21px;background:var(--pink)}
.btnrow{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}.btn{border:none;border-radius:12px;padding:11px 16px;font-weight:800;font-size:.86rem;display:inline-flex;align-items:center;gap:8px;transition:.15s}.btn.primary{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 6px 18px rgba(244,63,94,.3)}.btn.primary:hover{filter:brightness(1.08)}.btn.ghost{border:1px solid var(--border);color:var(--soft);background:transparent}.btn.ghost:hover{background:rgba(244,63,94,.1)}.btn.danger{border:1px solid rgba(248,113,113,.35);color:#fecaca;background:rgba(190,18,60,.14)}[data-theme=light] .btn.danger{color:#9f1239;background:rgba(190,18,60,.08)}.status{margin-top:14px;font-size:.8rem;min-height:1.4em;white-space:pre-wrap;color:var(--dim);line-height:1.55}.status.ok{color:var(--good)}.status.bad{color:var(--bad)}.hidden{display:none!important}.banlist{margin-top:10px;display:grid;gap:8px;max-height:330px;overflow:auto}.ban{border:1px solid var(--border);background:var(--panel2);border-radius:13px;padding:10px;font-size:.78rem}.ban b{color:var(--soft)}.pill{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--border);background:var(--panel2);border-radius:999px;padding:6px 10px;color:var(--dim);font-size:.76rem;font-weight:700;margin:4px 5px 0 0}.lock{max-width:560px;margin:8vh auto;background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:30px;box-shadow:0 30px 80px rgba(0,0,0,.42)}.lock h1{font-size:1.3rem;display:flex;align-items:center;gap:10px}.foot{text-align:center;padding:18px;font-size:.72rem;color:var(--dim2)}
@media(max-width:850px){main{padding:10px}.shell{grid-template-columns:1fr}.side{position:static;border-radius:18px}.tabs{display:grid;grid-template-columns:repeat(2,1fr);gap:6px}.tab{margin:0;justify-content:center}.grid,.row{grid-template-columns:1fr}.card{padding:18px}.top{padding:14px}}
</style>
</head>
<body>
<div class="top">
  <a class="brand" href="./"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI <span style="color:var(--dim2);font-weight:500;font-size:.8rem">Admin</span></a>
  <div class="right"><button class="tb" id="themeBtn" title="Switch theme" type="button"><?= icon('sun', 16) ?></button><a class="back" href="./"><?= icon('chevron-right', 14) ?> Home</a></div>
</div>

<main>
  <div id="lockView" class="lock">
    <h1><?= icon('lock', 20) ?> Admin panel</h1>
    <p class="sub">Unlock with admin password, or sign in normally as <b>bk.w.p.bk@gmail.com</b> and open this page.</p>
    <label for="pw">Admin password</label>
    <input id="pw" type="password" autocomplete="off" placeholder="Leave empty if you are signed in as admin email">
    <div class="status bad" id="lockStatus"></div>
    <div class="btnrow"><button class="btn primary" id="unlockBtn" type="button"><?= icon('unlock', 15) ?> Unlock</button></div>
  </div>

  <div id="mainView" class="shell hidden">
    <aside class="side">
      <h2><?= icon('shield-check', 16) ?> Control Center</h2>
      <div class="tabs">
        <button class="tab on" data-tab="overview" type="button"><?= icon('gauge', 16) ?> Overview</button>
        <button class="tab" data-tab="models" type="button"><?= icon('sparkles', 16) ?> Models</button>
        <button class="tab" data-tab="security" type="button"><?= icon('shield', 16) ?> Security</button>
        <button class="tab" data-tab="mail" type="button"><?= icon('mail', 16) ?> Mail</button>
        <button class="tab" data-tab="admins" type="button"><?= icon('users', 16) ?> Admins</button>
      </div>
    </aside>

    <section class="card">
      <div class="hero">
        <h1><?= icon('settings', 22) ?> Admin settings</h1>
        <p class="sub">Manage models, admin emails, reCAPTCHA, email-domain rules, bot bans and datacenter CIDRs from one place.</p>
        <div id="adminPills"></div>
      </div>

      <div class="section on" id="tab-overview">
        <div class="grid">
          <div class="mini"><b><?= icon('shield', 14) ?> Active bans</b><strong id="mBans">0</strong><p class="hint">Temporary security blocks currently active.</p></div>
          <div class="mini"><b><?= icon('gauge', 14) ?> Rate buckets</b><strong id="mBuckets">0</strong><p class="hint">Recent IP/API/security request counters.</p></div>
        </div>
        <div class="btnrow"><button class="btn ghost" id="refreshBtn" type="button"><?= icon('retry', 15) ?> Refresh</button><button class="btn danger" id="clearSecBtn" type="button"><?= icon('trash', 15) ?> Clear bans/rate counters</button><button class="btn ghost" id="testBtn" type="button"><?= icon('zap', 15) ?> Test engines</button></div>
        <div class="status" id="overviewStatus"></div>
        <div class="banlist" id="banList"></div>
      </div>

      <div class="section" id="tab-models">
        <div class="row"><div><label>Quick pick 1 (fast)</label><select id="aFlash"></select></div><div><label>Quick pick 2 (balanced)</label><select id="aPro"></select></div></div>
        <label>Quick pick 3 (best)</label><select id="aUltra"></select>
        <hr class="divider">
        <label>Gemini API keys <span style="color:var(--dim2);font-weight:500">(one per line — pasted keys are added to the saved ones)</span></label>
        <textarea id="geminiKeys" rows="3" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Paste a Gemini API key here"></textarea>
        <p class="hint">Saved only in this server's private settings. The first key answers every request; if it hits its quota or rate limit the app switches to the next key automatically and keeps using it until the limit resets. <span id="geminiKeyInfo"></span></p>
        <div class="switchrow"><span><b>Replace saved Gemini keys</b><small>Overwrite the saved list with what you pasted instead of adding to it.</small></span><button class="switch" id="replaceGeminiKeys" type="button" aria-pressed="false"></button></div>
        <div class="switchrow"><span><b>Remove all saved Gemini keys</b><small>Clear every Gemini key when you save settings.</small></span><button class="switch" id="clearGeminiKeys" type="button" aria-pressed="false"></button></div>
        <hr class="divider">
        <label>OpenRouter API key <span style="color:var(--dim2);font-weight:500">(leave empty to keep saved)</span></label>
        <input id="openrouterKey" type="password" autocomplete="new-password" autocapitalize="off" spellcheck="false" placeholder="Not set">
        <p class="hint">Saved only in this server's private settings. Agent Mode and the three quick picks continue using Gemini. Models without a :free tag are marked and are not selected randomly in Battle.</p>
        <div class="switchrow"><span><b>Remove saved OpenRouter key</b><small>Clear the key when you save settings.</small></span><button class="switch" id="clearOpenrouterKey" type="button" aria-pressed="false"></button></div>
        <div class="row"><div><label for="aRate">Messages per user per hour</label><input id="aRate" type="number" min="1" max="1000" inputmode="numeric"></div><div><label for="aChats">Max saved chats per user</label><input id="aChats" type="number" min="1" max="500" inputmode="numeric"></div></div>
        <div class="btnrow"><button class="btn primary saveBtn" type="button"><?= icon('check', 15) ?> Save settings</button></div>
        <div class="status" id="modelStatus"></div>
      </div>

      <div class="section" id="tab-security">
        <div class="switchrow"><span><b>reCAPTCHA v3 verification</b><small>Invisible Google verification for login/signup OTP requests.</small></span><button class="switch" id="recaptchaToggle" type="button" aria-pressed="false"></button></div>
        <div class="row"><div><label>reCAPTCHA site key</label><input id="recSite" placeholder="6Lc..."></div><div><label>reCAPTCHA min score</label><input id="recScore" type="number" step="0.05" min="0.1" max="0.9"></div></div>
        <label>reCAPTCHA secret key <span style="color:var(--dim2);font-weight:500">(leave empty to keep existing)</span></label><input id="recSecret" placeholder="Secret key is never displayed">
        <label>reCAPTCHA v2 site key <span style="color:var(--dim2);font-weight:500">(optional — shows a checkbox if invisible verification fails)</span></label><input id="recV2Site" placeholder="6Lc...">
        <label>reCAPTCHA v2 secret key <span style="color:var(--dim2);font-weight:500">(leave empty to keep existing)</span></label><input id="recV2Secret" placeholder="Secret key is never displayed">
        <div class="switchrow"><span><b>Block disposable/temp emails</b><small>Mailinator, 10MinuteMail, mail.tm, Yopmail, fake/spam/burner patterns, and your extra block list.</small></span><button class="switch" id="dispToggle" type="button" aria-pressed="true"></button></div>
        <div class="switchrow"><span><b>Block subdomain emails</b><small>Blocks user@mail.google.com and user@sub.company.com while allowing normal root domains and common co.in/co.uk style domains.</small></span><button class="switch" id="subToggle" type="button" aria-pressed="true"></button></div>
        <div class="row"><div><label>Extra blocked email domains</label><textarea id="blockedDomains" placeholder="one domain per line"></textarea></div><div><label>Trusted/exception domains</label><textarea id="trustedDomains" placeholder="one domain per line"></textarea></div></div>
        <label>Datacenter CIDR block list</label><textarea id="dcCidrs" placeholder="Example: 203.0.113.0/24"></textarea><p class="hint">Used for suspicious non-browser/datacenter traffic. One IPv4 or CIDR per line.</p>
        <div class="btnrow"><button class="btn primary saveBtn" type="button"><?= icon('check', 15) ?> Save security</button></div>
        <div class="status" id="securityStatus"></div>
      </div>

      <div class="section" id="tab-mail">
        <div class="switchrow"><span><b>Gmail deliverability</b><small>Native PHP mail works on temp-mail, but Gmail often blocks it. Use a verified API/SMTP provider for reliable Gmail inbox delivery.</small></span><span class="pill" id="mailModePill"><?= icon('mail', 13) ?> mail()</span></div>
        <div class="row"><div><label>Mail transport</label><select id="mailTransport"><option value="mail">PHP mail() fallback</option><option value="smtp">SMTP authenticated</option><option value="resend">Resend API</option></select></div><div><label>Test recipient</label><input id="mailTestTo" placeholder="bk.w.p.bk@gmail.com"></div></div>
        <div class="row"><div><label>From email</label><input id="mailFromEmail" placeholder="noreply@your-domain.com"></div><div><label>From name</label><input id="mailFromName" placeholder="Devil AI"></div></div>
        <label>Reply-To email</label><input id="mailReplyTo" placeholder="bk.w.p.bk@gmail.com">
        <hr class="divider">
        <label>Resend API key <span style="color:var(--dim2);font-weight:500">(leave empty to keep saved)</span></label><input id="resendKey" type="password" autocomplete="new-password" placeholder="re_xxxxxxxxx">
        <p class="hint">For Resend, set transport to Resend API and use a verified sender domain. SMTP below remains as fallback.</p>
        <hr class="divider">
        <div class="row"><div><label>SMTP host</label><input id="smtpHost" placeholder="smtp.gmail.com / mail.your-domain.com"></div><div><label>SMTP port</label><input id="smtpPort" type="number" min="1" max="65535" placeholder="587"></div></div>
        <div class="row"><div><label>SMTP security</label><select id="smtpSecure"><option value="tls">STARTTLS / 587</option><option value="ssl">SSL / 465</option><option value="none">None / 25</option></select></div><div><label>SMTP username</label><input id="smtpUser" autocomplete="off"></div></div>
        <label>SMTP password <span style="color:var(--dim2);font-weight:500">(leave empty to keep saved)</span></label><input id="smtpPass" type="password" autocomplete="new-password">
        <div class="btnrow"><button class="btn primary saveBtn" type="button"><?= icon('check', 15) ?> Save mail settings</button><button class="btn ghost" id="mailTestBtn" type="button"><?= icon('mail', 15) ?> Send test mail</button><button class="btn ghost" id="feedbackRefreshBtn" type="button"><?= icon('retry', 15) ?> Load feedback inbox</button></div>
        <div class="status" id="mailStatus"></div>
        <div class="banlist" id="feedbackList"></div>
      </div>

      <div class="section" id="tab-admins">
        <label>Admin email addresses</label><textarea id="adminEmails" placeholder="one admin email per line"></textarea><p class="hint">bk.w.p.bk@gmail.com is protected and will stay admin. Admin emails get access after normal email login.</p>
        <label>New admin password <span style="color:var(--dim2);font-weight:500">(leave empty to keep)</span></label><input id="aPw" type="password" placeholder="Only if you want to change it" autocomplete="off">
        <div class="btnrow"><button class="btn primary saveBtn" type="button"><?= icon('check', 15) ?> Save admins</button></div>
        <div class="status" id="adminStatus"></div>
      </div>
    </section>
  </div>
</main>
<div class="foot">Devil AI v1.0.0.0 • Admin</div>

<script>
(function () {
'use strict';
var $ = function (s) { return document.querySelector(s); };
var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };
function post(action, body) {
  return fetch('api.php?action=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
    .then(function (r) { return r.json(); })
    .catch(function () { return { ok: false, error: 'Network error.' }; });
}
function lines(v) { return String(v || '').split(/[\n,;]+/).map(function (x) { return x.trim(); }).filter(Boolean); }
function joinLines(a) { return (a || []).join('\n'); }
function setSwitch(el, on) { el.classList.toggle('on', !!on); el.setAttribute('aria-pressed', on ? 'true' : 'false'); }
function getSwitch(el) { return el.classList.contains('on'); }
function status(id, msg, kind) { var el = $(id); if (!el) { return; } el.className = 'status' + (kind ? ' ' + kind : ''); el.textContent = msg || ''; }
var ENGINES = [], PW = '', CFG = {}, SEC = {};
var PILL_ICONS = {
  mail: <?= json_encode(icon('mail', 13), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
  shield: <?= json_encode(icon('shield', 13), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
  shieldCheck: <?= json_encode(icon('shield-check', 13), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
};

function fillSelect(el, val) {
  el.innerHTML = '';
  ENGINES.forEach(function (en) { var o = document.createElement('option'); o.value = en.id; o.textContent = en.label; el.appendChild(o); });
  el.value = val;
}
function renderSecurity() {
  $('#mBans').textContent = SEC.active_ban_count || 0;
  $('#mBuckets').textContent = SEC.rate_bucket_count || 0;
  var list = $('#banList'); list.innerHTML = '';
  var bans = SEC.active_bans || [];
  if (!bans.length) { list.innerHTML = '<div class="ban"><b>No active bans.</b><br><span style="color:var(--dim)">Security guard is quiet right now.</span></div>'; return; }
  bans.forEach(function (b) {
    var d = document.createElement('div'); d.className = 'ban';
    var until = b.until ? new Date(b.until * 1000).toLocaleString() : 'soon';
    d.innerHTML = '<b>' + String(b.ip || '').replace(/[<>&]/g, '') + '</b><br><span style="color:var(--dim)">' + String(b.reason || 'security').replace(/[<>&]/g, '') + ' • until ' + until + '</span>';
    list.appendChild(d);
  });
}
function fill(cfg, sec, adminUser) {
  CFG = cfg || {}; SEC = sec || {};
  fillSelect($('#aFlash'), (CFG.engines || {}).flash);
  fillSelect($('#aPro'), (CFG.engines || {}).pro);
  fillSelect($('#aUltra'), (CFG.engines || {}).ultra);
  $('#aRate').value = CFG.rate_per_hour || 40;
  $('#aChats').value = CFG.max_chats || 100;
  $('#openrouterKey').value = '';
  $('#openrouterKey').placeholder = CFG.openrouter_api_key_set ? 'Key saved — leave blank to keep' : 'Paste the API key here';
  setSwitch($('#clearOpenrouterKey'), false);
  var gk = CFG.gemini_api_key_count || 0;
  var gkTails = CFG.gemini_api_key_tails || [];
  $('#geminiKeys').value = '';
  $('#geminiKeys').placeholder = gk ? (gk === 1 ? '1 key saved — paste another to add it' : gk + ' keys saved — paste another to add it') : 'Paste a Gemini API key here';
  $('#geminiKeyInfo').textContent = gkTails.length ? ('Saved: ' + gkTails.join(', ')) : '';
  setSwitch($('#replaceGeminiKeys'), false);
  setSwitch($('#clearGeminiKeys'), false);
  $('#adminEmails').value = joinLines(CFG.admin_emails || []);
  $('#aPw').value = '';
  setSwitch($('#recaptchaToggle'), !!CFG.security_require_recaptcha);
  $('#recSite').value = CFG.recaptcha_site_key || '';
  $('#recSecret').value = '';
  $('#recSecret').placeholder = CFG.recaptcha_secret_set ? 'Secret key saved — leave blank to keep' : 'Secret key is not set';
  $('#recScore').value = CFG.recaptcha_min_score || 0.45;
  $('#recV2Site').value = CFG.recaptcha_v2_site_key || '';
  $('#recV2Secret').value = '';
  $('#recV2Secret').placeholder = CFG.recaptcha_v2_secret_set ? 'Secret key saved — leave blank to keep' : 'Secret key is not set';
  setSwitch($('#dispToggle'), CFG.security_block_disposable_emails !== false);
  setSwitch($('#subToggle'), CFG.security_block_subdomain_emails !== false);
  $('#blockedDomains').value = joinLines(CFG.security_extra_blocked_email_domains || []);
  $('#trustedDomains').value = joinLines(CFG.security_trusted_email_domains || []);
  $('#dcCidrs').value = joinLines(CFG.security_datacenter_cidrs || []);
  $('#mailTransport').value = CFG.mail_transport || 'mail';
  $('#mailTestTo').value = (CFG.admin_emails || ['bk.w.p.bk@gmail.com'])[0] || 'bk.w.p.bk@gmail.com';
  $('#mailFromEmail').value = CFG.mail_from_email || '';
  $('#mailFromName').value = CFG.mail_from_name || 'Devil AI';
  $('#mailReplyTo').value = CFG.mail_reply_to || 'bk.w.p.bk@gmail.com';
  $('#smtpHost').value = CFG.smtp_host || '';
  $('#smtpPort').value = CFG.smtp_port || 587;
  $('#smtpSecure').value = CFG.smtp_secure || 'tls';
  $('#smtpUser').value = CFG.smtp_username || '';
  $('#smtpPass').value = '';
  $('#smtpPass').placeholder = CFG.smtp_password_set ? 'SMTP password saved — leave blank to keep' : 'SMTP password not set';
  $('#resendKey').value = '';
  $('#resendKey').placeholder = CFG.resend_api_key_set ? 'Resend API key saved — leave blank to keep' : 'Resend API key not set';
  var mt = CFG.mail_transport || 'mail';
  $('#mailModePill').innerHTML = PILL_ICONS.mail + ' ' + (mt === 'resend' ? 'Resend API' : (mt === 'smtp' ? 'SMTP' : 'mail()'));
  $('#adminPills').innerHTML = '<span class="pill">' + PILL_ICONS.mail + ' Admin: bk.w.p.bk@gmail.com</span>' + (adminUser ? '<span class="pill">' + PILL_ICONS.shieldCheck + ' Signed in as admin</span>' : '') + (CFG.security_require_recaptcha ? '<span class="pill">' + PILL_ICONS.shield + ' reCAPTCHA on</span>' : '<span class="pill">' + PILL_ICONS.shield + ' reCAPTCHA off</span>');
  renderSecurity();
}
function payload() {
  return {
    current_admin_password: PW,
    new_admin_password: $('#aPw').value,
    engines: { flash: $('#aFlash').value, pro: $('#aPro').value, ultra: $('#aUltra').value },
    rate_per_hour: parseInt($('#aRate').value, 10) || 40,
    max_chats: parseInt($('#aChats').value, 10) || 100,
    admin_emails: lines($('#adminEmails').value),
    security_require_recaptcha: getSwitch($('#recaptchaToggle')),
    recaptcha_site_key: $('#recSite').value.trim(),
    recaptcha_secret_key: $('#recSecret').value.trim(),
    recaptcha_v2_site_key: $('#recV2Site').value.trim(),
    recaptcha_v2_secret_key: $('#recV2Secret').value.trim(),
    recaptcha_min_score: parseFloat($('#recScore').value) || 0.45,
    security_block_disposable_emails: getSwitch($('#dispToggle')),
    security_block_subdomain_emails: getSwitch($('#subToggle')),
    security_extra_blocked_email_domains: lines($('#blockedDomains').value),
    security_trusted_email_domains: lines($('#trustedDomains').value),
    security_datacenter_cidrs: lines($('#dcCidrs').value),
    mail_transport: $('#mailTransport').value,
    mail_from_email: $('#mailFromEmail').value.trim(),
    mail_from_name: $('#mailFromName').value.trim(),
    mail_reply_to: $('#mailReplyTo').value.trim(),
    smtp_host: $('#smtpHost').value.trim(),
    smtp_port: parseInt($('#smtpPort').value, 10) || 587,
    smtp_secure: $('#smtpSecure').value,
    smtp_username: $('#smtpUser').value.trim(),
    smtp_password: $('#smtpPass').value,
    resend_api_key: $('#resendKey').value.trim(),
    openrouter_api_key: $('#openrouterKey').value.trim(),
    openrouter_api_key_clear: getSwitch($('#clearOpenrouterKey')),
    gemini_api_keys: $('#geminiKeys').value.trim(),
    gemini_api_keys_replace: getSwitch($('#replaceGeminiKeys')),
    gemini_api_keys_clear: getSwitch($('#clearGeminiKeys'))
  };
}
function unlock() {
  PW = $('#pw').value || '';
  $('#lockStatus').textContent = 'Checking…';
  post('auth', { admin_password: PW }).then(function (j) {
    if (j.ok && j.config) {
      ENGINES = j.engines || [];
      fill(j.config, j.security || {}, !!j.admin_user);
      $('#lockView').classList.add('hidden');
      $('#mainView').classList.remove('hidden');
    } else { $('#lockStatus').textContent = j.error || 'Wrong password or admin email not signed in.'; }
  });
}
$('#unlockBtn').addEventListener('click', unlock);
$('#pw').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); unlock(); } });
$$('.tab').forEach(function (b) { b.addEventListener('click', function () { $$('.tab').forEach(function (x) { x.classList.remove('on'); }); $$('.section').forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on'); $('#tab-' + b.getAttribute('data-tab')).classList.add('on'); }); });
$$('.switch').forEach(function (b) { b.addEventListener('click', function () { setSwitch(b, !getSwitch(b)); }); });
$$('.saveBtn').forEach(function (b) { b.addEventListener('click', function () {
  status('#modelStatus', 'Saving…'); status('#securityStatus', 'Saving…'); status('#adminStatus', 'Saving…'); status('#mailStatus', 'Saving…');
  post('settings', payload()).then(function (j) {
    var ok = !!j.ok; var msg = ok ? 'Saved — settings are live.' : (j.error || 'Save failed.');
    status('#modelStatus', msg, ok ? 'ok' : 'bad'); status('#securityStatus', msg, ok ? 'ok' : 'bad'); status('#adminStatus', msg, ok ? 'ok' : 'bad'); status('#mailStatus', msg, ok ? 'ok' : 'bad');
    if (ok && j.security) { SEC = j.security; renderSecurity(); }
    $('#aPw').value = ''; $('#recSecret').value = ''; $('#smtpPass').value = '';
    if (ok) {   /* re-read the saved settings so the "N keys saved" counters are up to date */
      post('auth', { admin_password: PW }).then(function (r) { if (r.ok && r.config) { fill(r.config, r.security || {}, !!r.admin_user); } });
    }
  });
}); });
$('#refreshBtn').addEventListener('click', function () { post('auth', { admin_password: PW }).then(function (j) { if (j.ok) { fill(j.config, j.security || {}, !!j.admin_user); status('#overviewStatus', 'Refreshed.', 'ok'); } }); });
$('#clearSecBtn').addEventListener('click', function () { if (!confirm('Clear active security bans and rate counters?')) { return; } post('admin_security_clear', { current_admin_password: PW }).then(function (j) { if (j.ok) { SEC = j.security || {}; renderSecurity(); status('#overviewStatus', 'Security counters cleared.', 'ok'); } else { status('#overviewStatus', j.error || 'Could not clear counters.', 'bad'); } }); });
$('#testBtn').addEventListener('click', function () { status('#overviewStatus', 'Testing engines…'); post('test', { current_admin_password: PW }).then(function (j) { if (j.ok) { status('#overviewStatus', 'All engines alive. Sample reply: ' + (j.reply || '').slice(0, 140), 'ok'); } else { status('#overviewStatus', (j.error || 'Test failed') + (j.hint ? '\nHint: ' + j.hint : ''), 'bad'); } }); });
function renderFeedback(items) {
  var list = $('#feedbackList'); list.innerHTML = '';
  if (!items || !items.length) { list.innerHTML = '<div class="ban"><b>No feedback saved yet.</b><br><span style="color:var(--dim)">When users tap good/bad, it will appear here even if Gmail blocks mail.</span></div>'; return; }
  items.forEach(function (f) {
    var u = f.user || {}; var when = f.ts ? new Date(f.ts * 1000).toLocaleString() : '';
    var div = document.createElement('div'); div.className = 'ban';
    div.innerHTML = '<b>' + String(f.rating || '').toUpperCase() + '</b> <span style="color:var(--dim)">' + when + '</span><br>'
      + '<span style="color:var(--soft)">' + String(u.name || '').replace(/[<>&]/g, '') + ' &lt;' + String(u.email || '').replace(/[<>&]/g, '') + '&gt;</span><br>'
      + '<span style="color:var(--dim)">Chat: ' + String(f.chat_id || '').replace(/[<>&]/g, '') + ' • msg ' + String(f.message_index || '') + '</span>'
      + '<div style="white-space:pre-wrap;margin-top:8px;color:var(--text)">' + String(f.content || '').replace(/[&<>]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }) + '</div>';
    list.appendChild(div);
  });
}
function loadFeedback() {
  status('#mailStatus', 'Loading feedback inbox…');
  post('admin_feedback', { current_admin_password: PW }).then(function (j) {
    if (j.ok) { renderFeedback(j.feedback || []); status('#mailStatus', 'Feedback inbox loaded.', 'ok'); }
    else { status('#mailStatus', j.error || 'Could not load feedback.', 'bad'); }
  });
}
$('#feedbackRefreshBtn').addEventListener('click', loadFeedback);
$('#mailTestBtn').addEventListener('click', function () {
  status('#mailStatus', 'Sending test mail…');
  post('admin_mail_test', { current_admin_password: PW, to: $('#mailTestTo').value.trim() }).then(function (j) {
    if (j.ok) { status('#mailStatus', 'Test mail accepted for ' + (j.to || $('#mailTestTo').value) + '. Check inbox/spam/promotions.', 'ok'); }
    else { status('#mailStatus', j.error || 'Mail test failed.', 'bad'); }
  });
});

/* theme toggle */
(function () { var SUN = <?= json_encode(icon('sun', 16)) ?>; var MOON = <?= json_encode(icon('moon', 16)) ?>; var b = $('#themeBtn'); function cur(){return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';} function setIco(){b.innerHTML = cur() === 'dark' ? SUN : MOON;} b.addEventListener('click', function(){var t = cur() === 'dark' ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', t); try{localStorage.setItem('devil_theme', t); document.cookie='devil_theme='+encodeURIComponent(t)+'; Max-Age=31536000; Path=/devil-ai/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');}catch(e){} setIco();}); setIco(); })();
if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); }); }
})();
</script>
</body>
</html>
