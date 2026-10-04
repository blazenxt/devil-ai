<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Cookie Consent System (inc/cookiebar.php)
 *  Full-cookie mode: session + consent + personalization
 *  cookies for theme/model/sidebar/settings, with localStorage
 *  as a fallback mirror. No dependencies.
 *  v1.0.0.0
 * ═══════════════════════════════════════════════════════
 */
if (defined('DEVIL_COOKIEBAR')) { return; }
define('DEVIL_COOKIEBAR', 1);
$devilCookiePath = function_exists('devil_cookie_path') ? devil_cookie_path() : '/';
$devilCookieSecure = function_exists('devil_is_https') ? devil_is_https() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
?>
<!-- ═══ Cookie consent (Devil AI) — theme-aware: uses page CSS variables ═══ -->
<style>
.dcb *{box-sizing:border-box;margin:0;padding:0}
.dcb-banner{position:fixed;left:16px;bottom:16px;z-index:900;max-width:380px;background:var(--panel,#181015);border:1px solid var(--border,rgba(244,63,94,.25));border-radius:16px;padding:16px;box-shadow:0 16px 48px rgba(0,0,0,.35);font-family:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif;color:var(--text,#efe6ea);animation:dcb-in .3s ease}
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
.dcb-sheet{background:var(--panel,#181015);border:1px solid var(--border,rgba(244,63,94,.25));border-radius:18px;max-width:470px;width:100%;padding:22px;max-height:88vh;overflow:auto;color:var(--text,#efe6ea);box-shadow:0 24px 70px rgba(0,0,0,.4)}
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
  <p>We use cookies to keep you signed in and remember your theme, model, sidebar and settings. Analytics and personalization are <b>optional</b> — you decide. <a href="cookies.php">Read our Cookie Policy</a></p>
  <div class="dcb-btns">
    <button class="dcb-btn dcb-accept" id="dcbAcceptAll" type="button">Accept all cookies</button>
    <button class="dcb-btn dcb-manage" id="dcbManage" type="button">Manage cookies</button>
  </div>
</div>

<div class="dcb dcb-modal" id="dcbModal" style="display:none">
  <div class="dcb-sheet">
    <h3><?= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Z"/><path d="M8.5 10.5v.01M13.5 15.5v.01M8 15v.01M15.5 10v.01M11 19v.01M18 15v.01"/></svg>' ?> Manage cookies</h3>
    <p>Choose which cookies Devil AI may use. Essential cookies keep login and security working. Personalization stores theme, selected model, custom engine and sidebar state across visits.</p>

    <div class="dcb-cat">
      <div class="dcb-cat-txt"><b>Essential</b><span>Login session, CSRF/security, account deletion verification and cookie consent. Always on — without these the app cannot work.</span></div>
      <label class="dcb-switch"><input type="checkbox" checked disabled><span class="dcb-slider"></span></label>
    </div>
    <div class="dcb-cat">
      <div class="dcb-cat-txt"><b>Analytics</b><span>Anonymous usage flags for future improvement. No third-party trackers are loaded by this app.</span></div>
      <label class="dcb-switch"><input type="checkbox" id="dcbAnalytics"><span class="dcb-slider"></span></label>
    </div>
    <div class="dcb-cat">
      <div class="dcb-cat-txt"><b>Personalization</b><span>Remembers theme, last model, custom engine, sidebar state and cookie/settings choices on this device.</span></div>
      <label class="dcb-switch"><input type="checkbox" id="dcbPersonal"><span class="dcb-slider"></span></label>
    </div>

    <div class="dcb-foot">
      <button class="dcb-btn dcb-accept" id="dcbSavePrefs" type="button">Save preferences</button>
      <button class="dcb-btn dcb-accept" id="dcbAcceptAll2" type="button">Accept all</button>
      <button class="dcb-btn dcb-manage" id="dcbEssentialOnly" type="button">Essential only</button>
      <button class="dcb-btn dcb-manage" id="dcbCloseModal" type="button">Close</button>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var KEY = 'devil_cookie_prefs';
  var COOKIE_PATH = <?= json_encode($devilCookiePath) ?>;
  var COOKIE_SECURE = <?= $devilCookieSecure ? 'true' : 'false' ?>;
  var PERSONAL_KEYS = ['devil_theme', 'devil_model', 'devil_custom_model', 'devil_sb'];
  var $ = function (id) { return document.getElementById(id); };
  function cookieSuffix(days) {
    var maxAge = Math.max(0, Math.floor(days * 24 * 60 * 60));
    return '; Max-Age=' + maxAge + '; Path=' + COOKIE_PATH + '; SameSite=Lax' + (COOKIE_SECURE ? '; Secure' : '');
  }
  function setCookie(name, value, days) {
    document.cookie = name + '=' + encodeURIComponent(String(value)) + cookieSuffix(days || 365);
  }
  function delCookie(name) {
    document.cookie = name + '=; Max-Age=0; Path=' + COOKIE_PATH + '; SameSite=Lax' + (COOKIE_SECURE ? '; Secure' : '');
  }
  function getCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }
  function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function lsDel(k) { try { localStorage.removeItem(k); } catch (e) {} }
  function getPrefs() {
    var raw = lsGet(KEY) || getCookie('devil_cookies');
    if (!raw) { return null; }
    try {
      var p = JSON.parse(raw);
      if (p && typeof p === 'object') { lsSet(KEY, JSON.stringify(p)); return p; }
    } catch (e) {}
    return null;
  }
  function syncPersonalCookies(prefs) {
    if (!prefs || !prefs.personalization) {
      PERSONAL_KEYS.forEach(function (k) { delCookie(k); if (!prefs || prefs.personalization === false) { lsDel(k); } });
      delCookie('devil_personal');
      return;
    }
    setCookie('devil_personal', '1', 365);
    PERSONAL_KEYS.forEach(function (k) {
      var v = lsGet(k) || getCookie(k);
      if (v !== null && v !== '') { setCookie(k, v, 365); lsSet(k, v); }
    });
  }
  function apply(prefs) {
    prefs = prefs || { essential: true, analytics: false, personalization: false };
    prefs.essential = true;
    prefs.ts = Date.now();
    var raw = JSON.stringify(prefs);
    lsSet(KEY, raw);
    setCookie('devil_cookies', raw, 365);
    setCookie('devil_cookie_version', '2', 365);
    setCookie('devil_analytics', prefs.analytics ? '1' : '0', 365);
    if (!prefs.analytics) { delCookie('devil_analytics_id'); }
    syncPersonalCookies(prefs);
    $('dcbBanner').style.display = 'none';
    $('dcbModal').style.display = 'none';
    window.devilCookiePrefs = prefs;
    if (typeof window.devilOnCookiePrefs === 'function') { window.devilOnCookiePrefs(prefs); }
  }
  function haveChoice() { return !!getPrefs(); }

  window.devilCookieGet = getCookie;
  window.devilCookieSet = function (name, value, days) {
    var prefs = getPrefs();
    var personal = PERSONAL_KEYS.indexOf(name) !== -1;
    if (personal && (!prefs || !prefs.personalization)) { return; }
    setCookie(name, value, days || 365);
  };
  window.devilCookieDel = delCookie;
  window.devilCookiePrefs = getPrefs();
  window.devilSyncPersonalCookies = function () { syncPersonalCookies(getPrefs()); };

  if (!haveChoice()) { $('dcbBanner').style.display = 'block'; }
  else { syncPersonalCookies(getPrefs()); }

  $('dcbAcceptAll').addEventListener('click', function () { apply({ essential: true, analytics: true, personalization: true }); });
  $('dcbAcceptAll2').addEventListener('click', function () { apply({ essential: true, analytics: true, personalization: true }); });
  $('dcbEssentialOnly').addEventListener('click', function () { apply({ essential: true, analytics: false, personalization: false }); });
  $('dcbManage').addEventListener('click', function () {
    var p = getPrefs() || { analytics: false, personalization: false };
    $('dcbAnalytics').checked = !!p.analytics;
    $('dcbPersonal').checked = !!p.personalization;
    $('dcbModal').style.display = 'flex';
  });
  $('dcbCloseModal').addEventListener('click', function () { $('dcbModal').style.display = 'none'; });
  $('dcbModal').addEventListener('click', function (e) { if (e.target === $('dcbModal')) { $('dcbModal').style.display = 'none'; } });
  $('dcbSavePrefs').addEventListener('click', function () {
    apply({ essential: true, analytics: $('dcbAnalytics').checked, personalization: $('dcbPersonal').checked });
  });
  window.devilOpenCookies = function () { $('dcbManage').click(); };
})();
</script>
