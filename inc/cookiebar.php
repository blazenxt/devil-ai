<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Cookie Consent System (inc/cookiebar.php)
 *  Claude-style: bottom-left banner + "Manage cookies"
 *  modal with toggles. Preferences stored in localStorage
 *  AND a 1-year cookie. Include on every public page.
 *  v1.0.0.0
 * ═══════════════════════════════════════════════════════
 *  Usage:  <?php require __DIR__.'/cookiebar.php'; ?>
 *  (prints HTML + CSS + JS — no dependencies)
 */
if (defined('DEVIL_COOKIEBAR')) { return; }
define('DEVIL_COOKIEBAR', 1);
?>
<!-- ═══ Cookie consent (Devil AI) — theme-aware: uses page CSS variables ═══ -->
<style>
.dcb *{box-sizing:border-box;margin:0;padding:0}
.dcb-banner{position:fixed;left:16px;bottom:16px;z-index:900;max-width:360px;background:var(--panel,#181015);border:1px solid var(--border,rgba(244,63,94,.25));border-radius:16px;padding:16px;box-shadow:0 16px 48px rgba(0,0,0,.35);font-family:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif;color:var(--text,#efe6ea);animation:dcb-in .3s ease}
.dcb-banner h4{font-size:.92rem;display:flex;align-items:center;gap:8px;color:var(--text,#fff)}
.dcb-banner h4 svg{color:var(--pink,#fb7185);flex-shrink:0}
.dcb-banner p{font-size:.78rem;line-height:1.55;color:var(--dim,#b9a3ab);margin-top:8px}
.dcb-banner p b{color:var(--text,#efe6ea)}
.dcb-banner p a{color:var(--soft,#fda4af);text-decoration:underline;cursor:pointer}
.dcb-btns{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.dcb-btn{border:none;border-radius:10px;padding:9px 14px;font-size:.8rem;font-weight:600;cursor:pointer;font-family:inherit;transition:.15s}
.dcb-accept{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff}
.dcb-accept:hover{filter:brightness(1.12)}
.dcb-manage{background:none;border:1px solid var(--border,rgba(244,63,94,.3));color:var(--soft,#fda4af)}
.dcb-manage:hover{background:rgba(244,63,94,.12)}
.dcb-modal{position:fixed;inset:0;z-index:950;background:rgba(5,2,4,.55);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:16px;font-family:'Segoe UI',system-ui,sans-serif}
.dcb-sheet{background:var(--panel,#181015);border:1px solid var(--border,rgba(244,63,94,.25));border-radius:18px;max-width:440px;width:100%;padding:22px;max-height:88vh;overflow:auto;color:var(--text,#efe6ea);box-shadow:0 24px 70px rgba(0,0,0,.4)}
.dcb-sheet h3{font-size:1.05rem;display:flex;align-items:center;gap:8px;color:var(--text,#fff)}
.dcb-sheet h3 svg{color:var(--pink,#fb7185)}
.dcb-sheet>p{font-size:.78rem;color:var(--dim,#b9a3ab);line-height:1.55;margin-top:8px}
.dcb-cat{display:flex;gap:12px;align-items:flex-start;padding:13px 0;border-bottom:1px solid var(--border,rgba(244,63,94,.12))}
.dcb-cat:last-of-type{border-bottom:none}
.dcb-cat-txt{flex:1;min-width:0}
.dcb-cat-txt b{font-size:.85rem;color:var(--text,#fff);display:block}
.dcb-cat-txt span{font-size:.72rem;color:var(--dim,#a1707b);line-height:1.5;display:block;margin-top:2px}
.dcb-switch{position:relative;width:42px;height:24px;flex-shrink:0;margin-top:2px}
.dcb-switch input{opacity:0;width:0;height:0;position:absolute}
.dcb-slider{position:absolute;inset:0;background:var(--panel3,#2a1c22);border-radius:999px;cursor:pointer;transition:.2s;border:1px solid var(--border,rgba(244,63,94,.2))}
.dcb-slider:before{content:"";position:absolute;width:17px;height:17px;left:3px;top:2.5px;border-radius:50%;background:var(--dim,#8a6b74);transition:.2s}
.dcb-switch input:checked + .dcb-slider{background:rgba(244,63,94,.45)}
.dcb-switch input:checked + .dcb-slider:before{transform:translateX(17px);background:#fda4af}
.dcb-switch input:disabled + .dcb-slider{opacity:.55;cursor:not-allowed}
.dcb-foot{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}
@keyframes dcb-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@media (max-width:480px){.dcb-banner{left:10px;right:10px;bottom:10px;max-width:none}}
</style>

<div class="dcb dcb-banner" id="dcbBanner" style="display:none">
  <h4><?= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Z"/><path d="M8.5 10.5v.01M13.5 15.5v.01M8 15v.01M15.5 10v.01M11 19v.01M18 15v.01"/></svg>' ?> Cookies, but make it devilish</h4>
  <p>We use a few cookies to keep you signed in and remember your choices. Analytics and personalization are <b>optional</b> — you decide. <a href="cookies.php">Read our Cookie Policy</a></p>
  <div class="dcb-btns">
    <button class="dcb-btn dcb-accept" id="dcbAcceptAll" type="button">Accept all cookies</button>
    <button class="dcb-btn dcb-manage" id="dcbManage" type="button">Manage cookies</button>
  </div>
</div>

<div class="dcb dcb-modal" id="dcbModal" style="display:none">
  <div class="dcb-sheet">
    <h3><?= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Z"/><path d="M8.5 10.5v.01M13.5 15.5v.01M8 15v.01M15.5 10v.01M11 19v.01M18 15v.01"/></svg>' ?> Manage cookies</h3>
    <p>Choose which cookies Devil AI may use. Essential cookies keep the app working — the rest is up to you. You can change this anytime from the footer.</p>

    <div class="dcb-cat">
      <div class="dcb-cat-txt"><b>Essential</b><span>Sign-in sessions, security and load balancing. Always on — without these the app cannot work.</span></div>
      <label class="dcb-switch"><input type="checkbox" checked disabled><span class="dcb-slider"></span></label>
    </div>
    <div class="dcb-cat">
      <div class="dcb-cat-txt"><b>Analytics</b><span>Anonymous usage stats that help us improve Devil AI. Off by default.</span></div>
      <label class="dcb-switch"><input type="checkbox" id="dcbAnalytics"><span class="dcb-slider"></span></label>
    </div>
    <div class="dcb-cat">
      <div class="dcb-cat-txt"><b>Personalization</b><span>Remembers your last model, sidebar state and preferences to tailor your experience. Off by default.</span></div>
      <label class="dcb-switch"><input type="checkbox" id="dcbPersonal"><span class="dcb-slider"></span></label>
    </div>

    <div class="dcb-foot">
      <button class="dcb-btn dcb-accept" id="dcbSavePrefs" type="button">Save preferences</button>
      <button class="dcb-btn dcb-accept" id="dcbAcceptAll2" type="button">Accept all</button>
      <button class="dcb-btn dcb-manage" id="dcbCloseModal" type="button">Close</button>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var KEY = 'devil_cookie_prefs';
  var $ = function (id) { return document.getElementById(id); };
  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
  }
  function getPrefs() {
    try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; }
  }
  function apply(prefs) {
    localStorage.setItem(KEY, JSON.stringify(prefs));
    setCookie('devil_cookies', JSON.stringify(prefs), 365);
    $('dcbBanner').style.display = 'none';
    $('dcbModal').style.display = 'none';
    /* Respect preferences: nothing non-essential is set when they are off */
    if (!prefs.analytics) { setCookie('devil_analytics', '', -1); }
    if (!prefs.personalization) { setCookie('devil_personal', '', -1); }
    if (typeof window.devilOnCookiePrefs === 'function') { window.devilOnCookiePrefs(prefs); }
  }
  function haveChoice() {
    if (getPrefs()) { return true; }
    var m = document.cookie.match(/(?:^|;\s*)devil_cookies=([^;]+)/);
    return !!m;
  }
  if (!haveChoice()) { $('dcbBanner').style.display = 'block'; }

  $('dcbAcceptAll').addEventListener('click', function () { apply({ essential: true, analytics: true, personalization: true, ts: Date.now() }); });
  $('dcbAcceptAll2').addEventListener('click', function () { apply({ essential: true, analytics: true, personalization: true, ts: Date.now() }); });
  $('dcbManage').addEventListener('click', function () {
    var p = getPrefs() || { analytics: false, personalization: false };
    $('dcbAnalytics').checked = !!p.analytics;
    $('dcbPersonal').checked = !!p.personalization;
    $('dcbModal').style.display = 'flex';
  });
  $('dcbCloseModal').addEventListener('click', function () { $('dcbModal').style.display = 'none'; });
  $('dcbModal').addEventListener('click', function (e) { if (e.target === $('dcbModal')) { $('dcbModal').style.display = 'none'; } });
  $('dcbSavePrefs').addEventListener('click', function () {
    apply({ essential: true, analytics: $('dcbAnalytics').checked, personalization: $('dcbPersonal').checked, ts: Date.now() });
  });
  /* Global hook: footer "Cookie settings" links call this */
  window.devilOpenCookies = function () { $('dcbManage').click(); };
})();
</script>
