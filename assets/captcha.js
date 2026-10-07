/* ═══════════════════════════════════════════════════════════════
   Devil AI — shared captcha gate (Turnstile + reCAPTCHA v3/v2)
   ───────────────────────────────────────────────────────────
   • Keeps the action button DISABLED until captcha verification
     completes (invisible/auto verification first).
   • If auto verification fails (widget error, expiry, timeout or a
     server-side 403), the captcha is SHOWN — for EVERY captcha that
     is not verified yet: Turnstile re-renders interactively, reCAPTCHA
     falls back to a visible v2 checkbox when a v2 key is configured.
   • Status notes say exactly what is pending / what failed.
   • After a failure, once the user completes the captcha(s), the box
     hides, the button enables and the pending action auto-retries once.
   Usage:
     var cap = DevilCaptcha.create({
       turnstileSiteKey: '...', recaptchaSiteKey: '...', recaptchaV2SiteKey: '...',
       action: 'login',                 // recaptcha v3 action name
       button: '#e1btn',                // element kept disabled until ready
       note: '#captchaNote',            // live status line element
       box: '#captchaBox', boxNote: '#captchaBoxNote',   // visible fallback container
       tsHost: '#turnstileInvisibleHost', recHost: '#recaptchaV3Host',
       v2Host: '#captchaRecaptcha', visibleHost: '#captchaTurnstile',
       onGate: function (ready) {}      // extra hook fired on every gate change
     });
     cap.ready(); cap.gate(); cap.ensure(action).then(tokens => ...);
     cap.handleResponse(apiJson);       // true when it was a captcha failure
     cap.resetAfterUse();               // call after a successful submit
     cap.retry = function () { ... };   // optional: auto-rerun after recovery
   ═══════════════════════════════════════════════════════════════ */
(function (global) {
  'use strict';

  function resolveEl(sel) {
    if (!sel) { return null; }
    if (typeof sel === 'string') { return document.querySelector(sel); }
    return sel;
  }

  function create(opts) {
    opts = opts || {};
    var tsKey = opts.turnstileSiteKey || '';
    var recKey = opts.recaptchaSiteKey || '';
    var v2Key = opts.recaptchaV2SiteKey || '';
    var action = opts.action || 'login';
    var btn = resolveEl(opts.button);
    var noteEl = resolveEl(opts.note);
    var box = resolveEl(opts.box);
    var boxNote = resolveEl(opts.boxNote);
    var tsHost = resolveEl(opts.tsHost);
    var recHost = resolveEl(opts.recHost);
    var v2Host = resolveEl(opts.v2Host);
    var visibleHost = resolveEl(opts.visibleHost);
    var hasCaptcha = !!(tsKey || recKey);

    var tsToken = '', tsReady = !tsKey, tsWidget = null, tsVisibleWidget = null, tsInitDone = false;
    var v3Widget = null, v3Token = '', v3At = 0, v3Verified = false, recInitDone = false;
    var v2Widget = null, v2Token = '', v2Verified = false;
    var boxShown = false;
    var captchaFailed = false;

    function recReady() { return !recKey || v3Verified || v2Verified; }
    function ready() { return tsReady && recReady(); }
    function note(msg, warn) {
      if (!noteEl) { return; }
      noteEl.textContent = msg;
      if (noteEl.classList) { noteEl.classList.toggle('warn', !!warn); }
    }
    function pendingParts() {
      var parts = [];
      if (tsKey && !tsReady) { parts.push('Cloudflare'); }
      if (recKey && !recReady()) { parts.push('Google'); }
      return parts;
    }
    function pendingNote() {
      var parts = pendingParts();
      if (!parts.length) { return 'Security check passed.'; }
      var total = (tsKey ? 1 : 0) + (recKey ? 1 : 0);
      if (parts.length >= total) { return 'Checking security…'; }
      return 'Waiting for ' + parts.join(' and ') + ' verification…';
    }

    function gate() {
      var ok = ready();
      if (btn) { btn.disabled = !ok; }
      if (hasCaptcha) {
        if (ok) {
          if (boxShown) { boxShown = false; if (box) { box.classList.add('hidden'); } }
          note('Security check passed.');
          if (captchaFailed && api && typeof api.retry === 'function') {
            var fn = api.retry;
            api.retry = null;
            captchaFailed = false;
            setTimeout(fn, 60);
          } else { captchaFailed = false; }
        } else {
          note(pendingNote());
        }
      }
      if (typeof opts.onGate === 'function') { opts.onGate(ok); }
    }

    function showBox() {
      if (box) { box.classList.remove('hidden'); }
      boxShown = true;
      var parts = pendingParts();
      var msg = parts.length > 1
        ? 'Security checks failed — please complete the verification below.'
        : (parts[0] === 'Cloudflare'
          ? 'Cloudflare security check failed — please complete the verification below.'
          : 'Security verification failed — please complete the verification below.');
      if (boxNote) { boxNote.textContent = msg; }
      /* every captcha that is not verified yet gets a visible fallback */
      if (tsKey && !tsReady) { renderVisibleTurnstile(); }
      if (recKey && !recReady()) { renderRecaptchaPart(); }
      gate();
    }

    function renderVisibleTurnstile() {
      if (!tsKey) { return; }
      var tries = 0;
      (function go() {
        if (global.turnstile && turnstile.render) {
          if (tsVisibleWidget !== null) { try { turnstile.reset(tsVisibleWidget); } catch (e) {} return; }
          if (visibleHost) {
            tsVisibleWidget = turnstile.render(visibleHost, {
              sitekey: tsKey,
              callback: function (t) { tsToken = t || ''; tsReady = !!tsToken; gate(); },
              'error-callback': function () { tsToken = ''; tsReady = false; gate(); },
              'expired-callback': function () { tsToken = ''; tsReady = false; gate(); }
            });
          }
        } else if (tries++ < 150) { setTimeout(go, 100); }
      })();
    }

    function v3Execute(act) {
      return new Promise(function (resolve) {
        if (!recKey || v3Widget === null || !global.grecaptcha || !grecaptcha.execute) { resolve(''); return; }
        try {
          grecaptcha.execute(v3Widget, { action: act || action }).then(resolve).catch(function () { resolve(''); });
        } catch (e) { resolve(''); }
      });
    }
    function renderRecaptchaPart() {
      if (!recKey) { return; }
      if (v2Key) { renderV2(); return; }
      v3Execute(action).then(function (t) {
        v3Token = t || ''; v3At = Date.now(); v3Verified = !!t;
        gate();
      });
    }
    function renderV2() {
      if (!v2Key || v2Widget !== null) { return; }
      var tries = 0;
      (function go() {
        if (global.grecaptcha && grecaptcha.render) {
          if (v2Host) {
            v2Widget = grecaptcha.render(v2Host, {
              sitekey: v2Key,
              callback: function (t) { v2Token = t || ''; v2Verified = !!v2Token; gate(); },
              'expired-callback': function () { v2Token = ''; v2Verified = false; gate(); }
            });
          }
        } else if (tries++ < 150) { setTimeout(go, 100); }
      })();
    }

    /* fresh tokens for a submit; resolves {ts, rec, v2} */
    function ensure(act) {
      return new Promise(function (resolve) {
        var out = { ts: tsToken, rec: v3Token, v2: v2Token };
        function recStep() {
          if (recKey && !v2Verified && (!v3Token || (Date.now() - v3At) > 90000)) {
            v3Execute(act || action).then(function (t) {
              v3Token = t || ''; v3At = Date.now(); v3Verified = !!t;
              if (!t && !v2Verified) { showBox(); }
              out.rec = v3Token; out.v2 = v2Token;
              resolve(out);
            });
          } else {
            out.rec = v3Token; out.v2 = v2Token;
            resolve(out);
          }
        }
        if (tsKey && !tsToken) {
          try { if (global.turnstile && tsWidget !== null) { turnstile.reset(tsWidget); } } catch (e) {}
          var w = 0;
          (function poll() {
            if (tsToken) { out.ts = tsToken; recStep(); }
            else if (w++ > 100) { showBox(); recStep(); }
            else { setTimeout(poll, 100); }
          })();
        } else { recStep(); }
      });
    }

    /* returns true when the response error was a captcha failure (box shown) */
    function handleResponse(j) {
      var msg = (j && j.error) || '';
      var isTs = /Cloudflare security verification failed/i.test(msg);
      var isRec = /Security verification failed/i.test(msg);
      if (!isTs && !isRec) { return false; }
      captchaFailed = true;
      /* the failed submit burns the tokens — reset what was used */
      if (isTs) {
        tsToken = ''; tsReady = false;
        try { if (global.turnstile && tsWidget !== null) { turnstile.reset(tsWidget); } } catch (e) {}
      }
      if (isRec) { v3Token = ''; v3Verified = false; v3At = 0; }
      /* a solved v2 checkbox token is single-use — reset it so the user can re-solve */
      if (v2Widget !== null) { try { grecaptcha.reset(v2Widget); } catch (e) {} }
      v2Token = ''; v2Verified = false;
      showBox();
      return true;
    }

    /* after a successful submit: tokens are consumed → refresh for the next action */
    function resetAfterUse() {
      tsToken = ''; tsReady = false;
      v3Token = ''; v3Verified = false; v3At = 0;
      if (v2Widget !== null) { try { grecaptcha.reset(v2Widget); } catch (e) {} }
      v2Token = ''; v2Verified = false;
      if (tsKey) { try { if (global.turnstile && tsWidget !== null) { turnstile.reset(tsWidget); } } catch (e) {} }
      if (recKey && !v2Key) {
        v3Execute(action).then(function (t) { v3Token = t || ''; v3At = Date.now(); v3Verified = !!t; gate(); });
      } else { gate(); }
    }

    function initTurnstile() {
      if (tsInitDone) { return; }
      tsInitDone = true;
      if (!tsKey || !global.turnstile || !turnstile.render || !tsHost) { return; }
      try {
        /* Managed mode (no size param): silent for normal traffic; if it cannot
           verify, the error callback / timeout reveals the visible fallback. */
        tsWidget = turnstile.render(tsHost, {
          sitekey: tsKey,
          callback: function (t) { tsToken = t || ''; tsReady = !!tsToken; gate(); },
          'error-callback': function () { tsToken = ''; tsReady = false; showBox(); },
          'expired-callback': function () { tsToken = ''; tsReady = false; gate(); }
        });
        if (boxShown && !tsReady) { renderVisibleTurnstile(); }
      } catch (e) {}
    }
    function initRecaptcha() {
      if (recInitDone) { return; }
      recInitDone = true;
      if (!recKey || !global.grecaptcha || !grecaptcha.render || !recHost) { return; }
      try {
        v3Widget = grecaptcha.render(recHost, { sitekey: recKey, size: 'invisible' });
        v3Execute(action).then(function (t) {
          v3Token = t || ''; v3At = Date.now(); v3Verified = !!t;
          if (!v3Verified && !v2Verified) { showBox(); }
          gate();
        });
        if (boxShown && !recReady()) { renderRecaptchaPart(); }
      } catch (e) {}
    }

    var api = {
      ready: ready,
      gate: gate,
      ensure: ensure,
      handleResponse: handleResponse,
      resetAfterUse: resetAfterUse,
      showBox: showBox,
      retry: null
    };

    /* captcha API scripts call these on load (render=explicit mode) */
    global.devilCaptchaTurnstileApiReady = function () { initTurnstile(); };
    global.devilCaptchaRecaptchaApiReady = function () { initRecaptcha(); };
    if (global.turnstile) { initTurnstile(); }
    if (global.grecaptcha) { initRecaptcha(); }

    /* if a captcha API never answers (blocked/timeout), reveal the fallback */
    setTimeout(function () {
      if (boxShown) { return; }
      if ((tsKey && !tsReady) || (recKey && !recReady())) { showBox(); }
    }, 12000);

    gate();
    return api;
  }

  global.DevilCaptcha = { create: create };
})(window);
