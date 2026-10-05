/* Devil AI support chat widget — embed on any website. */
(function () {
  'use strict';
  var script = document.currentScript || (function(){var s=document.getElementsByTagName('script');return s[s.length-1];})();
  if (!script || script.__devilSupportLoaded) { return; }
  script.__devilSupportLoaded = true;

  function attr(name, fallback) {
    var v = script.getAttribute(name);
    return v === null || v === '' ? fallback : v;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }
  function scriptOrigin() {
    try { return new URL(script.src, location.href).origin; } catch (e) { return 'https://api.devil.blazenxt.in'; }
  }

  var cfg = {
    apiKey: attr('data-devil-api-key', attr('data-api-key', '')),
    apiBase: attr('data-api-base', scriptOrigin() + '/v1').replace(/\/+$/, ''),
    title: attr('data-title', 'Devil AI Support'),
    company: attr('data-company', document.title || location.hostname || 'this website'),
    welcome: attr('data-welcome', 'Hi! How can we help you today?'),
    placeholder: attr('data-placeholder', 'Type your question...'),
    model: attr('data-model', 'devil-flash'),
    primary: attr('data-primary-color', '#e11d48'),
    position: attr('data-position', 'right').toLowerCase() === 'left' ? 'left' : 'right',
    prompt: attr('data-system-prompt', '')
  };
  if (!cfg.prompt) {
    cfg.prompt = 'You are a helpful support chat assistant for ' + cfg.company + '. Answer clearly and politely. If you do not know a site-specific answer, ask for contact details so a human can follow up. Do not invent policies.';
  }

  var history = [{ role: 'system', content: cfg.prompt }];
  var open = false;

  var root = document.createElement('div');
  root.id = 'devil-support-widget';
  var shadow = root.attachShadow ? root.attachShadow({ mode: 'open' }) : root;
  document.documentElement.appendChild(root);

  var side = cfg.position === 'left' ? 'left:18px;right:auto;' : 'right:18px;left:auto;';
  shadow.innerHTML = '<style>'+
    ':host{all:initial}.wrap,.wrap *{box-sizing:border-box;font-family:Segoe UI,system-ui,-apple-system,Roboto,sans-serif}.wrap{position:fixed;'+side+'bottom:18px;z-index:2147483647;color:#f8edf1}.launcher{width:58px;height:58px;border-radius:20px;border:0;background:linear-gradient(135deg,'+cfg.primary+',#7f1d1d);box-shadow:0 16px 40px rgba(0,0,0,.32);display:grid;place-items:center;cursor:pointer;color:white;font-weight:900}.launcher svg{width:28px;height:28px}.panel{display:none;width:min(380px,calc(100vw - 24px));height:min(560px,calc(100vh - 100px));margin-bottom:12px;border:1px solid rgba(244,63,94,.30);border-radius:22px;background:#100a0d;box-shadow:0 24px 90px rgba(0,0,0,.42);overflow:hidden}.panel.on{display:flex;flex-direction:column}.head{padding:14px 15px;background:linear-gradient(135deg,'+cfg.primary+',#9f1239);display:flex;align-items:center;justify-content:space-between;gap:12px}.brand{display:flex;align-items:center;gap:10px;font-weight:900}.mark{width:32px;height:32px;border-radius:10px;background:rgba(255,255,255,.14);display:grid;place-items:center}.mark img{width:28px;height:28px}.close{border:0;background:rgba(255,255,255,.14);color:white;border-radius:10px;width:32px;height:32px;cursor:pointer;font-size:18px}.msgs{flex:1;overflow:auto;padding:14px;background:radial-gradient(650px 280px at 80% -10%,rgba(225,29,72,.14),transparent 58%),#0c0709}.msg{max-width:86%;margin:0 0 10px;padding:10px 12px;border-radius:16px;line-height:1.45;font-size:14px;white-space:pre-wrap;word-wrap:break-word}.bot{background:#1d1216;border:1px solid rgba(244,63,94,.16);color:#f8edf1}.user{background:linear-gradient(135deg,'+cfg.primary+',#be123c);color:white;margin-left:auto}.form{display:flex;gap:8px;padding:12px;border-top:1px solid rgba(244,63,94,.18);background:#171014}.input{flex:1;min-width:0;border:1px solid rgba(244,63,94,.22);background:#100a0d;color:#f8edf1;border-radius:14px;padding:11px 12px;outline:none}.input:focus{border-color:rgba(244,63,94,.65)}.send{border:0;border-radius:14px;padding:0 14px;background:linear-gradient(135deg,'+cfg.primary+',#be123c);color:#fff;font-weight:850;cursor:pointer}.hint{font-size:11px;color:#b99aa5;padding:0 14px 12px;background:#171014}.err{color:#fca5a5}.typing{opacity:.75;font-style:italic}@media(max-width:520px){.wrap{left:10px!important;right:10px!important;bottom:10px}.panel{width:100%;height:min(560px,calc(100vh - 92px))}.launcher{margin-left:auto}}'+
  '</style><div class="wrap"><div class="panel" part="panel"><div class="head"><div class="brand"><span class="mark"><img alt="Devil AI" src="https://api.devil.blazenxt.in/assets/logo.svg"></span><span>'+esc(cfg.title)+'</span></div><button class="close" type="button" aria-label="Close">×</button></div><div class="msgs" aria-live="polite"></div><form class="form"><input class="input" type="text" maxlength="1200" autocomplete="off" placeholder="'+esc(cfg.placeholder)+'"><button class="send" type="submit">Send</button></form><div class="hint">Powered by Devil AI</div></div><button class="launcher" type="button" aria-label="Open support chat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg></button></div>';

  var panel = shadow.querySelector('.panel');
  var launcher = shadow.querySelector('.launcher');
  var closeBtn = shadow.querySelector('.close');
  var msgs = shadow.querySelector('.msgs');
  var form = shadow.querySelector('.form');
  var input = shadow.querySelector('.input');
  var send = shadow.querySelector('.send');

  function add(role, text, cls) {
    var el = document.createElement('div');
    el.className = 'msg ' + (cls || role);
    el.textContent = text;
    msgs.appendChild(el);
    msgs.scrollTop = msgs.scrollHeight;
    return el;
  }
  function setOpen(v) {
    open = !!v;
    panel.classList.toggle('on', open);
    launcher.style.display = open ? 'none' : 'grid';
    if (open) { setTimeout(function(){ input.focus(); }, 60); }
  }
  launcher.addEventListener('click', function(){ setOpen(true); });
  closeBtn.addEventListener('click', function(){ setOpen(false); });
  add('bot', cfg.welcome, 'bot');

  async function ask(text) {
    if (!cfg.apiKey || cfg.apiKey.indexOf('YOUR_512_CHARACTER_KEY') !== -1) {
      add('bot', 'Support chat is not configured. Add data-devil-api-key to the embed snippet.', 'bot err');
      return;
    }
    history.push({ role: 'user', content: text });
    var typing = add('bot', 'Devil AI is typing...', 'bot typing');
    send.disabled = true;
    input.disabled = true;
    try {
      var res = await fetch(cfg.apiBase + '/chat/completions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + cfg.apiKey },
        body: JSON.stringify({ model: cfg.model, messages: history.slice(-16) })
      });
      var data = await res.json().catch(function(){ return {}; });
      if (!res.ok) { throw new Error((data.error && data.error.message) || data.error || 'Support chat failed.'); }
      var reply = data && data.choices && data.choices[0] && data.choices[0].message ? data.choices[0].message.content : '';
      if (!reply) { reply = 'Sorry, I could not generate a reply right now.'; }
      typing.className = 'msg bot';
      typing.textContent = reply;
      history.push({ role: 'assistant', content: reply });
    } catch (e) {
      typing.className = 'msg bot err';
      typing.textContent = e && e.message ? e.message : 'Network error. Please try again.';
    } finally {
      send.disabled = false;
      input.disabled = false;
      input.focus();
    }
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var text = input.value.trim();
    if (!text) { return; }
    input.value = '';
    add('user', text, 'user');
    ask(text);
  });
})();
