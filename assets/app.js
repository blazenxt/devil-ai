(function () {
'use strict';
var $ = function (s) { return document.querySelector(s); };
var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };

/* ── state ── */
var models = [], modelById = {}, currentModel = 'flash';
var customModels = [], customById = {}, currentCustom = 'devil-09';
var chats = [], currentChat = null;   /* currentChat = {id, title, messages, temp?} */
var listModeFilter = 'all';
var busy = false, isTempChat = false, activeController = null, sendSeq = 0, inlineEdit = null, editRestoreChat = null;
var chatMode = ROUTE_MODE, agentMode = ROUTE_MODE === 'agent', agentEnabled = true;
var BATTLE_POOL = [], battleById = {}, cmpA = 'flash', cmpB = 'pro', cmpFromChat = false;
var MODE_INFO = {
  battle: { label: 'Battle Mode', chip: 'Battle', icon: 'swordsM', chipIcon: 'swords', path: 'battle', seg: 'battle' },
  agent:  { label: 'Agent Mode', chip: 'Agent', icon: 'sparkM', chipIcon: 'spark', path: 'agent', seg: 'agent' },
  sbs:    { label: 'Side by Side', chip: 'Side by Side', icon: 'columnsM', chipIcon: 'columns', path: 'side-by-side', seg: 'side-by-side' },
  ai:     { label: 'AI Mode', chip: 'Direct', icon: 'messageM', chipIcon: 'messageM', path: 'chat', seg: 'chat' }
};
var MODE_WELCOME = {
  ai:     { h: 'What shall we <mark>summon</mark> today?', p: 'Pick a model in the chat box and ask me anything.' },
  agent:  { h: 'What would you like to do?', p: 'Devil Agent searches the web, reads pages and calculates before answering.' },
  battle: { h: 'Let the <mark>battle</mark> begin', p: 'Two anonymous Devil models answer side by side. Vote for the better one — then their names are revealed.' },
  sbs:    { h: 'Compare <mark>side by side</mark>', p: 'Pick any two Devil models and see their answers next to each other.' }
};
function isCmp(m) { m = m || chatMode; return m === 'battle' || m === 'sbs'; }
function setChatMode(m) { chatMode = MODE_INFO[m] ? m : 'ai'; agentMode = chatMode === 'agent'; }
function newChatPath() { return MODE_INFO[chatMode].path; }
function syncModeUI() {
  var sw = $('#modeSw'); if (!sw) { return; }
  var info = MODE_INFO[chatMode];
  sw.classList.toggle('agent', chatMode !== 'ai');
  var tag = $('#modeTag'); if (tag) { tag.textContent = info.chip; }
  var agI = $('#agIco'), agT = $('#agTxt');
  if (agI) { agI.innerHTML = I[info.icon] || ''; }
  if (agT) { agT.textContent = info.label; }
  var ci = $('#modeChipIco'), cl = $('#modeChipLbl');
  if (ci) { ci.innerHTML = I[info.chipIcon] || ''; }
  if (cl) { cl.textContent = info.chip; }
  $$('.modeopt[data-mode]').forEach(function (o) {
    var on = o.dataset.mode === chatMode;
    o.classList.toggle('on', on);
    o.setAttribute('aria-checked', on ? 'true' : 'false');
    if (o.dataset.mode === 'agent') { o.disabled = !agentEnabled && !agentMode; }
  });
  var h = $('#welcome h2'), p = $('#welcome .sub');
  var w = MODE_WELCOME[chatMode];
  if (h) { h.innerHTML = w.h; }
  if (p) { p.textContent = w.p; }
  var bc = document.body.classList;
  bc.toggle('agent-mode', agentMode);
  bc.toggle('alt-mode', chatMode !== 'ai');
  bc.toggle('cmp-mode', isCmp());
  bc.toggle('battle-mode', chatMode === 'battle');
  bc.toggle('sbs-mode', chatMode === 'sbs');
  if (!agentMode) { setWorkspace(false); }
  syncEmptyState();
  if (typeof renderWorkspace === 'function') { renderWorkspace(); }
}
function syncEmptyState() {
  var m = document.getElementById('msgs');
  var empty = !!m && !m.children.length && !document.body.classList.contains('loading-chat');
  document.body.classList.toggle('agent-empty', empty);
  var ta = document.getElementById('inp');
  if (ta) { ta.placeholder = empty ? 'Ask anything…' : 'Ask followup…'; }
}
function setWorkspace(open) {
  var p = document.getElementById('wsPanel'), b = document.getElementById('wsBtn');
  if (!p) { return; }
  p.classList.toggle('open', open);
  document.body.classList.toggle('ws-docked', !!open);
  p.setAttribute('aria-hidden', open ? 'false' : 'true');
  if (b) { b.classList.toggle('on', open); b.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  if (open) { renderWorkspace(); }
}
function setModeMenu(open) {
  var sw = $('#modeSw'); if (!sw) { return; }
  sw.classList.toggle('open', open);
  $('#modeBtn').setAttribute('aria-expanded', open ? 'true' : 'false');
}
var MODE_TOAST = {
  ai: ['AI Mode — chat with 1 model at a time', 'sparkles'],
  agent: ['Agent Mode — I can search the web, read pages and calculate', 'check'],
  battle: ['Battle Mode — 2 anonymous models answer, you pick the winner', 'swords'],
  sbs: ['Side by Side — choose 2 models and compare their answers', 'columns']
};
function chooseMode(mode) {
  if (!MODE_INFO[mode]) { return; }
  setModeMenu(false);
  if (window.__devilCloseModeChip) { window.__devilCloseModeChip(); }
  if (mode === chatMode) { return; }
  if (mode === 'agent' && !agentEnabled) { toast('Agent Mode is currently disabled', 'warning'); return; }
  if (busy) { toast('Pause the response before switching mode', 'warning'); return; }
  var hasSaved = currentChat && !isTempChat && currentChat.messages && currentChat.messages.length;
  if (hasSaved || (isTempChat && isCmp(mode))) {
    /* a saved chat keeps its mode — switching starts a fresh chat in the other mode */
    window.location.href = MODE_INFO[mode].path;
    return;
  }
  setChatMode(mode);
  syncModeUI();
  updateChatActions();
  if (window.history) {
    var q = isTempChat ? '?temp=1' : '';
    history.replaceState(null, '', MODE_INFO[mode].path + q);
  }
  toast(MODE_TOAST[mode][0], MODE_TOAST[mode][1]);
}
(function () {
  var btn = $('#modeBtn'); if (!btn) { return; }
  btn.addEventListener('click', function (e) { e.stopPropagation(); setModeMenu(!$('#modeSw').classList.contains('open')); });
  $$('.modeopt[data-mode]').forEach(function (o) {
    o.addEventListener('click', function (e) { e.stopPropagation(); chooseMode(o.dataset.mode); });
  });
  var chip = $('#modeChip'), chipWrap = $('#modeChipWrap');
  function setChip(open) { if (!chipWrap) { return; } chipWrap.classList.toggle('open', open); chip.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  if (chip) { chip.addEventListener('click', function (e) { e.stopPropagation(); setModeMenu(false); closeCmpPickers(); setChip(!chipWrap.classList.contains('open')); }); }
  document.addEventListener('click', function (e) { if (!e.target.closest('#modeSw')) { setModeMenu(false); } if (!e.target.closest('#modeChipWrap')) { setChip(false); } if (!e.target.closest('.cmppick')) { closeCmpPickers(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setModeMenu(false); setChip(false); closeCmpPickers(); } });
  window.__devilCloseModeChip = function () { setChip(false); };
})();

/* ── keep every popover inside the viewport: pick the side with more room, cap height, scroll inside ── */
var FIT_MENUS = ['#modelMenu', '#customModelMenu', '#promptMenu', '#modeChipWrap .modemenu2', '.cmppick .cmpmenu', '#modeSw .modemenu'];
function fitMenu(m) {
  var st = m.style;
  ['top', 'bottom', 'max-height', 'overflow-y'].forEach(function (k) { st.removeProperty(k); });
  var list = m.querySelector('.cmlist');
  if (list) { list.style.removeProperty('max-height'); st.setProperty('max-height', 'none', 'important'); }
  if (getComputedStyle(m).display === 'none') { return; }
  var par = m.offsetParent || m.parentNode, pr = par.getBoundingClientRect(), r = m.getBoundingClientRect();
  var vh = window.innerHeight || document.documentElement.clientHeight, pad = 8, gap = 8;
  var up = r.top < pr.top - 1;
  var spaceUp = pr.top - gap - pad, spaceDown = vh - pr.bottom - gap - pad;
  var need = m.scrollHeight;
  if (m.closest('#modeSw')) { up = false; spaceDown = vh - r.top - pad; }
  else if (up ? need > spaceUp && spaceDown > spaceUp : need > spaceDown && spaceUp > spaceDown) {
    up = !up;
    st.setProperty('top', up ? 'auto' : 'calc(100% + ' + gap + 'px)', 'important');
    st.setProperty('bottom', up ? 'calc(100% + ' + gap + 'px)' : 'auto', 'important');
  }
  var room = Math.max(140, Math.floor(up ? spaceUp : spaceDown));
  if (list) {
    /* custom engines: keep the search box fixed, only the list scrolls */
    var chrome = m.offsetHeight - list.offsetHeight;
    list.style.setProperty('max-height', Math.max(90, Math.min(360, room - chrome)) + 'px', 'important');
    st.setProperty('overflow-y', 'hidden', 'important');
    st.setProperty('max-height', 'none', 'important');
    return;
  }
  st.setProperty('max-height', room + 'px', 'important');
  st.setProperty('overflow-y', 'auto', 'important');
}
function fitOpenMenus() { FIT_MENUS.forEach(function (sel) { $$(sel).forEach(fitMenu); }); }
document.addEventListener('click', function () { setTimeout(fitOpenMenus, 0); }, true);
document.addEventListener('keydown', function () { setTimeout(fitOpenMenus, 0); }, true);
document.addEventListener('input', function (e) { if (e.target.closest && e.target.closest('#customModelMenu')) { setTimeout(fitOpenMenus, 0); } }, true);
window.addEventListener('resize', fitOpenMenus);
if (window.visualViewport) { window.visualViewport.addEventListener('resize', fitOpenMenus); }

/* ── Side by Side model pickers ── */
function battleLabel(id) { return (battleById[id] || {}).label || (id === 'flash' ? 'Devil Flash' : (id === 'pro' ? 'Devil Pro' : 'Devil AI')); }
function battleIcon(id) {
  var m = battleById[id];
  if (m && I[m.icon]) { return I[m.icon]; }
  return '<img src="assets/logo.svg" width="15" height="15" alt="">';
}
function closeCmpPickers() { $$('.cmppick.open').forEach(function (w) { w.classList.remove('open'); }); }
function renderCmpPickers() {
  ['a', 'b'].forEach(function (s) {
    var w = document.getElementById('cmpPick' + s.toUpperCase()); if (!w) { return; }
    var cur = s === 'a' ? cmpA : cmpB;
    w.querySelector('.lb').textContent = battleLabel(cur);
    w.querySelector('.cmpbtn').title = 'Model ' + s.toUpperCase() + ': ' + battleLabel(cur);
    w.querySelector('.cmpmenu').innerHTML = '<div class="mhead">Model ' + s.toUpperCase() + '</div>' + BATTLE_POOL.map(function (m) {
      return '<button type="button" class="cmpopt' + (m.id === cur ? ' on' : '') + '" data-id="' + esc(m.id) + '"><span class="oi">' + battleIcon(m.id) + '</span><span>' + esc(m.label) + '</span><span class="ock">' + I.check + '</span></button>';
    }).join('');
  });
}
$$('.cmppick').forEach(function (w) {
  w.addEventListener('click', function (e) {
    e.stopPropagation();
    var opt = e.target.closest('.cmpopt');
    if (opt) {
      if (w.dataset.side === 'a') { cmpA = opt.dataset.id; store('devil_sbs_a', cmpA); } else { cmpB = opt.dataset.id; store('devil_sbs_b', cmpB); }
      renderCmpPickers(); closeCmpPickers(); return;
    }
    if (e.target.closest('.cmpbtn')) {
      var open = !w.classList.contains('open');
      closeCmpPickers(); if (window.__devilCloseModeChip) { window.__devilCloseModeChip(); }
      w.classList.toggle('open', open);
    }
  });
});
var personalOK = true;
function rawCookie(name) {
  if (window.devilCookieGet) { return window.devilCookieGet(name); }
  var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
  return m ? decodeURIComponent(m[1]) : null;
}
try {
  var prefsRaw = rawCookie('devil_cookies') || localStorage.getItem('devil_cookie_prefs') || 'null';
  var prefs = JSON.parse(prefsRaw);
  if (prefs && prefs.personalization === false) { personalOK = false; }
} catch (e) {}

function store(key, val) {
  if (!personalOK) { return; }
  try { localStorage.setItem(key, val); } catch (e) {}
  if (window.devilCookieSet) { window.devilCookieSet(key, val, 365); }
}
function read(key) {
  var v = null;
  try { v = localStorage.getItem(key); } catch (e) {}
  if (v === null || v === '') { v = rawCookie(key); }
  return v;
}
window.devilOnCookiePrefs = function (prefs) {
  personalOK = !!(prefs && prefs.personalization);
  if (personalOK && window.devilSyncPersonalCookies) { window.devilSyncPersonalCookies(); }
};

function providerIcon(key) {
  var k = String(key || 'devil').toLowerCase();
  var base = 'width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"';
  if (k === 'code') { return '<svg ' + base + ' fill="none" stroke="#60a5fa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 4l-4 16"/></svg>'; }
  if (k === 'image') { return '<svg ' + base + ' fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="m21 16-4-4a2 2 0 0 0-2.8 0L7 20"/></svg>'; }
  if (k === 'music') { return '<svg ' + base + ' fill="none" stroke="#f472b6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l10-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="16" cy="16" r="3"/></svg>'; }
  if (k === 'medical') { return '<svg ' + base + ' fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.5 8 8 10 4.5-2 8-5 8-10V6l-8-3Z"/><path d="M12 8v8M8 12h8"/></svg>'; }
  if (k === 'story') { return '<svg ' + base + ' fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/><path d="M8 7h8M8 11h6"/></svg>'; }
  return '<svg ' + base + ' fill="none" stroke="#fb7185" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c1 4-4 5.5-4 10a4 4 0 0 0 8 0c0-1.5-.6-2.6-1.3-3.6C13.6 9.7 13 8 13.5 6 12.8 6.6 12 7 12 2Z"/><path d="M12 22a6.5 6.5 0 0 0 6.5-6.5c0-2-1-4-2.5-5.5"/></svg>';
}

function activeModelLabel() {
  if (currentModel === 'custom') { return (customById[currentCustom] || {}).label || 'Custom AI'; }
  return (modelById[currentModel] || {}).label || 'Devil AI';
}
function rootVariantId() {
  return currentChat ? (currentChat.root_id || currentChat.id || '') : '';
}
function rootChatId() {
  return currentChat ? (currentChat.slug || currentChat.chat_slug || currentChat.root_slug || currentChat.root_id || currentChat.id || '') : '';
}
function activeVariantId() {
  if (!currentChat) { return ''; }
  return currentChat.active_variant || currentChat.variant_chat_id || rootVariantId();
}
function routeModel() {
  if (currentChat && currentChat.url_model) { return currentChat.url_model; }
  return currentModel === 'custom' ? 'custom' : (currentModel || 'flash');
}
function routeType() {
  if (currentChat && currentChat.url_type) { return currentChat.url_type; }
  return currentModel === 'custom' ? (currentCustom || 'custom') : 'chat';
}
function seg(s, fallback) {
  s = String(s || fallback || 'chat').toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');
  return s || fallback || 'chat';
}
function cleanAppBasePath() {
  var base = String(APP_BASE_PATH || '').trim();
  if (!base || base === '/' || base === './' || base === '.') { return ''; }
  // Guard against proxy/root-domain rewrites turning /devil-ai into just /.
  base = base.replace(/\\\//g, '/').replace(/\/+$/g, '');
  if (!base || base === '/') { return ''; }
  if (base.charAt(0) !== '/') { base = '/' + base; }
  return base;
}
function chatUrlFor(slug) {
  var base = cleanAppBasePath();
  if (chatMode !== 'ai') { return base + '/' + MODE_INFO[chatMode].seg + '/' + encodeURIComponent(slug); }
  return base + '/chat/' + encodeURIComponent(seg(routeModel(), 'flash')) + '/' + encodeURIComponent(seg(routeType(), 'chat')) + '/' + encodeURIComponent(slug);
}
function replaceChatUrl() {
  var slug = rootChatId();
  if (!window.history || !slug) { return; }
  history.replaceState(null, '', chatUrlFor(slug));
}
function chatUrlForItem(c) {
  var base = cleanAppBasePath();
  var slug = (c && (c.slug || c.id)) || '';
  if (c && c.mode && c.mode !== 'ai' && MODE_INFO[c.mode]) { return base + '/' + MODE_INFO[c.mode].seg + '/' + encodeURIComponent(slug); }
  var m = (c && c.url_model) || 'flash';
  var t = (c && c.url_type) || 'chat';
  return base + '/chat/' + encodeURIComponent(seg(m, 'flash')) + '/' + encodeURIComponent(seg(t, 'chat')) + '/' + encodeURIComponent(slug);
}

/* ── markdown (escape-first, XSS safe) ── */
function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function inline(s) {
  return s.replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
}
function md(src) {
  var lines = esc(src).split('\n'), out = [], inCode = false, buf = [], inList = false, curLang = '';
  function closeList() { if (inList) { out.push('</ul>'); inList = false; } }
  lines.forEach(function (line) {
    if (line.trim().indexOf('```') === 0) {
      if (inCode) {
        out.push('<pre data-lang="' + curLang + '"><code>' + buf.join('\n') + '</code></pre>');
        buf = []; inCode = false; curLang = '';
      } else { closeList(); inCode = true; curLang = line.trim().slice(3).trim().toLowerCase(); }
      return;
    }
    if (inCode) { buf.push(line); return; }
    var t = line.trim();
    if (/^(?:\d+\.|[-*])\s+/.test(t)) {
      if (!inList) { out.push('<ul>'); inList = true; }
      out.push('<li>' + inline(t.replace(/^(?:\d+\.|[-*])\s+/, '')) + '</li>');
      return;
    }
    closeList();
    if (/^###\s+/.test(t)) { out.push('<h4>' + inline(t.replace(/^###\s+/, '')) + '</h4>'); }
    else if (/^#{1,2}\s+/.test(t)) { out.push('<h3>' + inline(t.replace(/^#{1,2}\s+/, '')) + '</h3>'); }
    else if (t === '') { out.push('<div class="sp"></div>'); }
    else { out.push('<p>' + inline(t) + '</p>'); }
  });
  if (inList) { out.push('</ul>'); }
  if (inCode && buf.length) { out.push('<pre><code>' + buf.join('\n') + '</code></pre>'); }
  return out.join('');
}

/* ── api ── */
function api(action, body, method, signal) {
  method = method || (body === undefined ? 'GET' : 'POST');
  /* data that already arrived with the page is used once (fresh from the same response, not a cache) */
  if (method === 'GET' && window.__BOOT && Object.prototype.hasOwnProperty.call(window.__BOOT, action)) {
    var pre = window.__BOOT[action]; delete window.__BOOT[action];
    return Promise.resolve(pre);
  }
  var opt = { method: method, headers: { 'Content-Type': 'application/json' } };
  if (signal) { opt.signal = signal; }
  if (method === 'POST') { opt.body = JSON.stringify(body || {}); }
  /* NOTE: action may carry extra query params (chat_load&id=…) whose values
     are already encodeURIComponent'd by the caller — so don't re-encode here */
  return fetch('api.php' + (action ? '?action=' + action : ''), opt).then(function (r) {
    if (r.status === 401) { window.location.href = 'login.php'; throw new Error('signed out'); }
    /* Cloudflare wants a quick "are you human" check again (VPN / datacenter networks only):
       reload once so the check page shows, instead of failing with a network error */
    if (r.status === 403 && r.headers.get('cf-mitigated') === 'challenge') {
      var last = 0; try { last = Number(sessionStorage.getItem('devil_cf_reload') || 0); } catch (e) {}
      if (Date.now() - last > 60000) { try { sessionStorage.setItem('devil_cf_reload', String(Date.now())); } catch (e) {} window.location.reload(); }
      return { ok: false, error: 'Security check needed — please reload the page.' };
    }
    return r.json();
  }).catch(function (e) {
    if (e && e.name === 'AbortError') { return { ok: false, aborted: true, error: 'Paused.' }; }
    return { ok: false, error: 'Network error — please try again.' };
  });
}

/* ── toast ── */
var toastT;
function toast(msg, ico) {
  $('#toast').firstChild ? null : 0;
  $('#toast').innerHTML = (I[ico || 'check']) + '<span id="toastTxt"></span>';
  $('#toastTxt').textContent = msg;
  $('#toast').style.display = 'flex';
  clearTimeout(toastT);
  toastT = setTimeout(function () { $('#toast').style.display = 'none'; }, 2800);
}

function copyText(text) {
  return (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
    toast('Copied to clipboard');
  }, function () {
    toast('Copy failed — select the text manually', 'warning');
  });
}
function actionBtn(iconHtml, label, title, fn) {
  var b = document.createElement('button');
  b.type = 'button';
  b.title = title || label;
  b.setAttribute('aria-label', title || label);
  b.innerHTML = iconHtml;
  b.addEventListener('click', fn);
  return b;
}
function refreshMessageActions() {
  var retryBtns = $$('.retryAct');
  retryBtns.forEach(function (b) { b.hidden = true; });
  var last = msgs ? msgs.lastElementChild : null;
  if (last && last.classList.contains('msg-ai') && !last.classList.contains('thinking')) {
    Array.prototype.forEach.call(last.querySelectorAll('.retryAct'), function (rb) { rb.hidden = false; });
  }
}

function simpleHash(str) {
  var h = 0, i, chr;
  if (!str) { return '0'; }
  for (i = 0; i < str.length; i++) { chr = str.charCodeAt(i); h = ((h << 5) - h) + chr; h |= 0; }
  return String(Math.abs(h));
}
function feedbackKey(meta, text) {
  return 'devil_feedback_' + ((currentChat && currentChat.id) || 'temp') + '_' + (meta && meta.index !== undefined ? meta.index : simpleHash(text));
}
function markFeedbackButtons(up, down, chosen) {
  up.disabled = true; down.disabled = true;
  if (chosen === 'good') { up.classList.add('on'); }
  if (chosen === 'bad') { down.classList.add('on'); }
}
function sendFeedback(rating, text, meta, up, down) {
  var key = feedbackKey(meta, text);
  if (read(key)) { markFeedbackButtons(up, down, read(key)); toast('Feedback already sent'); return; }
  markFeedbackButtons(up, down, rating);
  store(key, rating);
  api('feedback', { rating: rating, content: text, chat_id: (currentChat && currentChat.id) || '', message_index: meta && meta.index !== undefined ? meta.index : -1 }).then(function (j) {
    if (j.ok) { toast(j.duplicate ? 'Feedback already sent' : 'Feedback sent'); }
    else { toast(j.error || 'Feedback could not be sent', 'warning'); }
  });
}
function autoGrowTextarea(t) {
  t.style.height = 'auto';
  t.style.height = Math.min(t.scrollHeight, 260) + 'px';
}
function cancelInlineEdit(focusComposer) {
  if (!inlineEdit) { return; }
  if (inlineEdit.form && inlineEdit.form.parentNode) { inlineEdit.form.remove(); }
  if (inlineEdit.el) { inlineEdit.el.classList.remove('editing'); }
  inlineEdit = null;
  if (focusComposer && inp) { inp.focus(); }
}
function beginEdit(index, text, el) {
  if (!currentChat || !currentChat.id || isTempChat) { toast('Only saved chats can be edited', 'warning'); return; }
  if (index === undefined || index === null || !currentChat.messages || !currentChat.messages[index] || currentChat.messages[index].role !== 'user') {
    toast('That message cannot be edited', 'warning'); return;
  }
  if (busy) { toast('Pause the response before editing', 'warning'); return; }
  cancelInlineEdit(false);
  var msgEl = el || (msgs.querySelector('[data-index="' + index + '"]'));
  if (!msgEl) { toast('Could not open editor for this message', 'warning'); return; }
  msgEl.classList.add('editing');
  var form = document.createElement('div');
  form.className = 'inlineEditBox';
  var ta = document.createElement('textarea');
  ta.value = text || '';
  ta.setAttribute('aria-label', 'Edit message');
  var row = document.createElement('div');
  row.className = 'inlineEditActions';
  var cancel = document.createElement('button');
  cancel.type = 'button'; cancel.className = 'cancel'; cancel.textContent = 'Cancel';
  var save = document.createElement('button');
  save.type = 'button'; save.className = 'save'; save.textContent = 'Save & regenerate';
  row.appendChild(cancel); row.appendChild(save);
  form.appendChild(ta); form.appendChild(row);
  var acts = msgEl.querySelector('.acts');
  msgEl.insertBefore(form, acts || null);
  inlineEdit = { chatId: rootChatId(), variant: activeVariantId(), index: index, original: text || '', el: msgEl, form: form, textarea: ta };
  cancel.addEventListener('click', function () { cancelInlineEdit(true); });
  save.addEventListener('click', submitInlineEdit);
  ta.addEventListener('input', function () { autoGrowTextarea(ta); });
  ta.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); submitInlineEdit(); }
    if (e.key === 'Escape') { e.preventDefault(); cancelInlineEdit(true); }
  });
  setTimeout(function () { autoGrowTextarea(ta); ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); }, 20);
}
function clearEdit() {
  cancelInlineEdit(false);
  var chip = $('#editChip');
  if (chip) { chip.classList.remove('show'); }
}
function submitInlineEdit() {
  if (!inlineEdit || busy) { return; }
  var state = inlineEdit;
  var text = state.textarea.value.trim();
  if (!text) { toast('Edited message is empty', 'warning'); return; }
  var originalChat = currentChat;
  editRestoreChat = originalChat;
  var originalMsg = (originalChat && originalChat.messages && originalChat.messages[state.index]) ? originalChat.messages[state.index] : {};

  if (state.form && state.form.parentNode) { state.form.remove(); }
  if (state.el) { state.el.classList.remove('editing'); }
  inlineEdit = null;

  var bubble = state.el ? state.el.querySelector('.bub') : null;
  if (bubble) { fillUserBubble(bubble, text, originalMsg.img, true, originalMsg.attachments || []); }
  var acts = state.el ? state.el.querySelector('.acts') : null;
  if (acts) { acts.style.display = 'none'; }
  while (state.el && state.el.nextSibling) { state.el.nextSibling.remove(); }

  busy = true;
  var seq = ++sendSeq;
  activeController = window.AbortController ? new AbortController() : null;
  resize();
  toast('Regenerating from edited message…', 'pencil');
  var th = addThinking();
  api('chat_edit', { id: state.chatId, variant: state.variant, message_index: state.index, message: text }, undefined, activeController ? activeController.signal : null).then(function (j) {
    if (seq !== sendSeq) { return; }
    th.remove();
    if (j.aborted) { toast('Edit paused', 'stop'); renderCurrentMessages(); return; }
    if (j.ok && j.chat) {
      editRestoreChat = null;
      isTempChat = false;
      currentChat = j.chat;
      currentChat.temp = false;
      currentChat.branch_groups = j.branch_groups || currentChat.branch_groups || {};
      replaceChatUrl();
      renderCurrentMessages();
      loadChats();
      updateChatActions();
      toast('Edited branch created');
    } else if (j.ok && j.id) {
      editRestoreChat = null;
      window.location.href = chatUrlFor(j.slug || j.id);
    } else {
      toast((j && j.error) || 'Edit failed', 'warning');
      editRestoreChat = null;
      currentChat = originalChat;
      renderCurrentMessages();
    }
  }).finally(function () {
    if (seq === sendSeq) {
      busy = false;
      if (!busy) { editRestoreChat = null; }
      activeController = null;
      resize();
    }
  });
}
function shareCurrentChat(btn) {
  if (!currentChat || !currentChat.id || isTempChat) { toast('Only saved chats can be shared', 'warning'); return; }
  if (btn) { btn.disabled = true; }
  api('chat_share', { id: rootChatId(), variant: activeVariantId() }).then(function (j) {
    if (j.ok && j.url) {
      if (navigator.clipboard) { navigator.clipboard.writeText(j.url).catch(function () {}); }
      toast('Share link copied');
    } else { toast(j.error || 'Could not create share link', 'warning'); }
  }).finally(function () { if (btn) { btn.disabled = false; } });
}

/* ── sidebar ── */
var sb = $('#sidebar'), bd = $('#backdrop');
function setSb(open) {
  sb.classList.toggle('closed', !open);
  document.body.classList.toggle('sb-closed', !open);
  bd.classList.toggle('show', open && window.innerWidth <= 900);
  store('devil_sb', open ? '1' : '0');
}
$('#sbToggle').addEventListener('click', function () { setSb(false); });
$('#sbOpen').addEventListener('click', function () { setSb(true); });
bd.addEventListener('click', function () { setSb(false); });
(function () {
  var saved = read('devil_sb');
  /* default: open on desktop, closed on mobile (first visit) */
  setSb(saved === null ? window.innerWidth > 900 : saved === '1');
})();

/* ── chat list ── */
function renderList(filter) {
  var box = $('#chatList');
  filter = (filter || '').toLowerCase();
  var now = new Date(), today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
  var yest = today - 86400000, week = today - 7 * 86400000;
  var groups = { pinned: [], today: [], yest: [], week: [], older: [] };
  chats.forEach(function (c) {
    if (filter && (c.title || '').toLowerCase().indexOf(filter) === -1) { return; }
    if (listModeFilter !== 'all' && (c.mode || 'ai') !== listModeFilter) { return; }
    var t = c.updated ? c.updated * 1000 : 0;
    if (c.pinned) { groups.pinned.push(c); }
    else if (t >= today) { groups.today.push(c); }
    else if (t >= yest) { groups.yest.push(c); }
    else if (t >= week) { groups.week.push(c); }
    else { groups.older.push(c); }
  });
  var html = '';
  var labels = { pinned: 'Pinned', today: 'Today', yest: 'Yesterday', week: 'Previous 7 days', older: 'Older' };
  var any = false;
  Object.keys(labels).forEach(function (k) {
    if (!groups[k].length) { return; }
    any = true;
    html += '<div class="grp' + (k === 'pinned' ? ' pin' : '') + '">' + (k === 'pinned' ? I.pinS + ' ' : '') + labels[k] + '</div>';
    groups[k].forEach(function (c) {
      var isOn = currentChat && (currentChat.id === c.id || currentChat.root_id === c.id || (currentChat.slug && currentChat.slug === c.slug));
      html += '<div class="chatitem' + (isOn ? ' on' : '') + (c.pinned ? ' pinned' : '') + '" data-id="' + c.id + '" data-slug="' + (c.slug || '') + '">' +
        '<span class="cmode" title="' + ((MODE_INFO[c.mode] || MODE_INFO.ai || { label: 'AI Mode' }).label) + '">' + I[c.mode === 'battle' ? 'swordsS' : (c.mode === 'sbs' ? 'columnsS' : (c.mode === 'agent' ? 'sparkS' : 'msgS'))] + '</span>' +
        '<span class="t"></span><span class="act">' +
        '<button data-pin="' + c.id + '" title="' + (c.pinned ? 'Unpin' : 'Pin to top') + '" class="' + (c.pinned ? 'pinned' : '') + '">' + I.pin + '</button>' +
        '<button data-rename="' + c.id + '" title="Rename">' + I.pencil + '</button>' +
        '<button data-del="' + c.id + '" title="Delete">' + I.trash + '</button></span></div>';
    });
  });
  if (!any) {
    var fl = listModeFilter !== 'all' ? ((MODE_INFO[listModeFilter] || {}).label || 'this mode') : '';
    html = '<div class="list-empty">' + (filter ? 'No chats match your search.' : (fl ? 'No ' + fl + ' chats yet.' : 'No chats yet — your conversations will appear here.')) + '</div>';
  }
  box.innerHTML = html;
  $$('#chatList .chatitem').forEach(function (el) {
    el.querySelector('.t').textContent = (chats.filter(function (c) { return c.id === el.dataset.id; })[0] || {}).title || 'New chat';
  });
}

function togglePin(id) {
  var c = chats.filter(function (x) { return x.id === id; })[0];
  if (!c) { return; }
  var want = !c.pinned;
  c.pinned = want; renderList($('#searchInp').value);
  api('chat_pin', { id: id, pinned: want }).then(function (j) {
    if (!j.ok) { c.pinned = !want; renderList($('#searchInp').value); toast(j.error || 'Could not pin the chat.'); return; }
    toast(want ? 'Pinned to the top' : 'Unpinned');
  }).catch(function () { c.pinned = !want; renderList($('#searchInp').value); toast('Network error.'); });
}
function setListFilter(f) {
  listModeFilter = ['all', 'ai', 'agent', 'battle', 'sbs'].indexOf(f) >= 0 ? f : 'all';
  store('devil_list_filter', listModeFilter);
  $$('#sbFilter button').forEach(function (b) { b.classList.toggle('on', b.dataset.f === listModeFilter); b.setAttribute('aria-selected', b.dataset.f === listModeFilter ? 'true' : 'false'); });
  renderList($('#searchInp').value);
}
$('#sbFilter').addEventListener('click', function (e) { var b = e.target.closest('button[data-f]'); if (b) { setListFilter(b.dataset.f); } });
(function () {
  var f = read('devil_list_filter');
  if (['ai', 'agent', 'battle', 'sbs'].indexOf(f) >= 0) {
    listModeFilter = f;
    $$('#sbFilter button').forEach(function (b) { b.classList.toggle('on', b.dataset.f === f); });
  }
})();

function loadChats() {
  return api('chats').then(function (j) {
    if (j.ok) { chats = j.chats || []; renderList($('#searchInp').value); updateDocTitle(); }
  });
}
$('#searchInp').addEventListener('input', function () { renderList(this.value); });

$('#chatList').addEventListener('click', function (e) {
  var rn = e.target.closest('[data-rename]'), del = e.target.closest('[data-del]'), pn = e.target.closest('[data-pin]');
  if (pn) { e.stopPropagation(); togglePin(pn.dataset.pin); return; }
  if (rn) { e.stopPropagation(); openRename(rn.dataset.rename); return; }
  if (del) { e.stopPropagation(); deleteChat(del.dataset.del); return; }
  var it = e.target.closest('.chatitem');
  if (it) {
    var c = chats.filter(function (x) { return x.id === it.dataset.id; })[0] || { id: it.dataset.id, slug: it.dataset.slug };
    window.location.href = chatUrlForItem(c);
  }
});

/* ── messages ── */
var msgs = $('#msgs'), welcome = $('#welcome'), scroller = $('#scroller');
function scrollDown() { scroller.scrollTop = scroller.scrollHeight; }
function branchGroupFor(index) {
  if (index === undefined || index === null || !currentChat || !currentChat.branch_groups) { return null; }
  return currentChat.branch_groups[String(index)] || currentChat.branch_groups[index] || null;
}
function activeVariantIndex(variants) {
  var id = activeVariantId();
  var root = rootVariantId();
  var n = variants.findIndex(function (v) { return v && (v.id === id || (id === root && v.id === root)); });
  return n < 0 ? 0 : n;
}
function makeBranchNav(index, group) {
  var variants = group && group.variants ? group.variants : [];
  if (!variants || variants.length < 2) { return null; }
  var active = activeVariantIndex(variants);
  var wrap = document.createElement('div');
  wrap.className = 'branchNav';
  var prev = document.createElement('button');
  prev.type = 'button'; prev.className = 'prev'; prev.innerHTML = '&lt;'; prev.title = active > 0 ? ('Show ' + (variants[active - 1].label || 'previous version')) : 'Oldest version';
  var count = document.createElement('span');
  count.className = 'count';
  count.textContent = (active + 1) + ' / ' + variants.length;
  count.title = (variants[active] && variants[active].title) ? variants[active].title : 'Message version';
  var next = document.createElement('button');
  next.type = 'button'; next.className = 'next'; next.innerHTML = '&gt;'; next.title = active < variants.length - 1 ? ('Show ' + (variants[active + 1].label || 'next version')) : 'Newest version';
  prev.disabled = active <= 0;
  next.disabled = active >= variants.length - 1;
  prev.addEventListener('click', function () { if (active > 0) { switchBranchVariant(variants[active - 1].id, index); } });
  next.addEventListener('click', function () { if (active < variants.length - 1) { switchBranchVariant(variants[active + 1].id, index); } });
  wrap.appendChild(prev);
  wrap.appendChild(count);
  wrap.appendChild(next);
  return wrap;
}
function switchBranchVariant(id, index) {
  var root = rootChatId();
  var original = rootVariantId();
  if (!id || !root || activeVariantId() === id) { return; }
  if (busy) { toast('Pause the response before switching versions', 'warning'); return; }
  cancelInlineEdit(false);
  var variant = (id === original) ? 'original' : id;
  api('chat_load&id=' + encodeURIComponent(root) + '&variant=' + encodeURIComponent(variant)).then(function (j) {
    if (!j.ok) { toast(j.error || 'Could not open that version', 'warning'); return; }
    isTempChat = false;
    currentChat = j.chat;
    currentChat.temp = false;
    currentChat.branch_groups = j.branch_groups || currentChat.branch_groups || {};
    replaceChatUrl();
    renderCurrentMessages();
    renderList($('#searchInp').value);
    updateChatActions();
    setTimeout(function () {
      var el = msgs.querySelector('[data-index="' + index + '"]');
      if (el && el.scrollIntoView) { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
    }, 60);
  });
}

function fillUserBubble(b, text, img, edited, attachments) {
  b.innerHTML = '';
  if (img) {
    var im = document.createElement('img');
    im.className = 'msg-img';
    im.src = img;
    im.alt = 'Attached image';
    im.addEventListener('click', function () { openImgView(img); });
    b.appendChild(im);
  }
  if (text) { b.appendChild(document.createTextNode(text)); }
  var files = (attachments || []).filter(function (a) { return a && (a.name || a.type); });
  if (files.length) {
    var fw = document.createElement('div');
    fw.className = 'msg-files';
    files.forEach(function (a) {
      var one = document.createElement('span');
      one.className = 'msg-file';
      one.innerHTML = I.paperclip || I.copy;
      var nm = document.createElement('span');
      nm.textContent = a.name || 'attachment';
      one.appendChild(nm);
      var ex = (String(a.name || '').match(/\.([a-z0-9]{1,5})$/i) || [])[1];
      if (ex) { var eb = document.createElement('b'); eb.className = 'ext'; eb.textContent = ex.toUpperCase(); one.appendChild(eb); }
      fw.appendChild(one);
    });
    b.appendChild(fw);
  }
  if (edited) {
    var badge = document.createElement('span');
    badge.className = 'editBadge';
    badge.textContent = 'Edited';
    b.appendChild(badge);
  }
}

function addUserMsg(text, img, meta) {
  meta = meta || {};
  var d = document.createElement('div');
  d.className = 'msg-user';
  if (meta.index !== undefined) { d.dataset.index = String(meta.index); }
  var b = document.createElement('div');
  b.className = 'bub';
  fillUserBubble(b, text, img, !!meta.edited, meta.attachments || []);
  d.appendChild(b);
  /* Agent Mode (the reference agent): attached files sit as chips above the bubble */
  if (agentMode) { var fw = b.querySelector('.msg-files'); if (fw) { d.insertBefore(fw, b); } if (typeof agtTaskCardClose === 'function') { agtTaskCardClose(); } }
  var nav = makeBranchNav(meta.index, meta.branchGroup || branchGroupFor(meta.index));
  if (nav) { d.appendChild(nav); }
  if (text) {
    var acts = document.createElement('div');
    acts.className = 'acts';
    acts.appendChild(actionBtn(I.copy, 'Copy', 'Copy message', function () { copyText(text); }));
    var eb = actionBtn(I.pencil, 'Edit', 'Edit and regenerate from here', function () {
      beginEdit(meta.index, text, d);
    });
    if (meta.index === undefined || isTempChat || isCmp()) { eb.hidden = true; }
    acts.appendChild(eb);
    d.appendChild(acts);
  }
  msgs.appendChild(d);
  welcome.style.display = 'none';
  refreshMessageActions();
  scrollDown();
}

/* full-size image viewer */
var imgView = $('#imgView');
function openImgView(src) {
  var im = document.createElement('img');
  im.src = src; im.alt = 'Attached image';
  imgView.innerHTML = '';
  imgView.appendChild(im);
  imgView.classList.add('show');
}
imgView.addEventListener('click', function () { imgView.classList.remove('show'); });

function addAiMsg(opts) {
  opts = opts || {};
  var d = document.createElement('div');
  d.className = 'msg-ai';
  d.innerHTML = '<div class="ava"><img src="assets/logo.svg" alt=""></div>' +
    '<div class="body"><div class="who"><b>Devil AI</b>' +
    (opts.modelTag ? '<span class="mtag">' + opts.modelTag + '</span>' : '') +
    '</div><div class="content"></div><div class="acts"></div></div>';
  msgs.appendChild(d);
  welcome.style.display = 'none';
  return d;
}

var AGX_TOOLS = {
  web_search: { verb: 'Searched the web', icon: 'globe' },
  fetch_url:  { verb: 'Read page', icon: 'fileText' },
  calculator: { verb: 'Calculated', icon: 'calc' },
  datetime:   { verb: 'Checked the time', icon: 'clock' }
};
function agxArg(s) {
  var inp = String(s.input || '').trim();
  if (s.tool === 'fetch_url') { try { var u = new URL(inp); return u.hostname.replace(/^www\./, '') + (u.pathname !== '/' ? u.pathname : ''); } catch (e) {} }
  if (s.tool === 'datetime' && !inp) { return 'server clock'; }
  return inp;
}
function fmtDur(ms) {
  if (!ms && ms !== 0) { return ''; }
  var sec = Math.max(1, Math.round(ms / 1000));
  return sec < 60 ? sec + 's' : Math.floor(sec / 60) + 'm ' + (sec % 60) + 's';
}
function renderAgentTrace(el, steps, ms) {
  steps = steps || [];
  if (!el || (!steps.length && !ms)) { return; }
  var box = document.createElement('div');
  box.className = 'agx' + (steps.length ? ' open' : '');
  var label = (ms ? 'Worked for ' + fmtDur(ms) : 'Worked') + (steps.length ? ' · ' + steps.length + ' step' + (steps.length > 1 ? 's' : '') : '');
  var rows = steps.map(function (s, i) {
    var t = AGX_TOOLS[s.tool] || { verb: 'Used ' + (s.tool || 'tool'), icon: 'brainS' };
    var out = String(s.output || '').trim();
    return '<div class="agx-step' + (s.ok ? '' : ' fail') + '" data-i="' + i + '">' +
      '<span class="agx-ic">' + (I[t.icon] || '') + '</span>' +
      '<button type="button" class="agx-row"><span class="agx-verb">' + esc(s.ok ? t.verb : t.verb + ' — failed') + '</span>' +
      '<span class="agx-arg">' + esc(agxArg(s)) + '</span>' + (out ? '<span class="agx-chev">' + (I.chevR || '') + '</span>' : '') + '</button>' +
      (s.tool === 'fetch_url' && /^https?:\/\//i.test(String(s.input || '').trim()) ? '<a class="agx-open" href="' + esc(String(s.input).trim()) + '" target="_blank" rel="noopener noreferrer" title="Open page">' + I.external + '</a>' : '') +
      (out ? '<div class="agx-out"><button type="button" class="agx-copy" title="Copy output">' + I.copy + '</button><div class="agx-otx">' + esc(out) + '</div></div>' : '') + '</div>';
  }).join('');
  box.innerHTML = '<button type="button" class="agx-head"><span class="agx-spark">' + (I.spark || '') + '</span><span>' + esc(label) + '</span>' +
    (steps.length ? '<span class="agx-chev">' + (I.chevR || '') + '</span>' : '') + '</button>' + agentSummaryHtml(steps) +
    (steps.length ? '<div class="agx-list">' + rows + '<div class="agx-done">' + (I.check || '') + ' Done</div></div>' : '');
  box.querySelector('.agx-head').addEventListener('click', function () { if (steps.length) { box.classList.toggle('open'); } });
  Array.prototype.forEach.call(box.querySelectorAll('.agx-step'), function (st) {
    var r = st.querySelector('.agx-row');
    if (st.querySelector('.agx-out')) { r.addEventListener('click', function () { st.classList.toggle('open'); }); }
    var cp = st.querySelector('.agx-copy');
    if (cp) { cp.addEventListener('click', function (e) { e.stopPropagation(); copyText(String((steps[Number(st.dataset.i)] || {}).output || '')); }); }
  });
  var body = el.querySelector('.body');
  if (body) { body.insertBefore(box, el.querySelector('.content')); }
  renderSourceCards(el, steps);
}

/* workspace panel: sources + tool activity gathered from this chat's agent steps */
function workspaceData() {
  var steps = [], seen = {}, sources = [];
  ((currentChat && currentChat.messages) || []).forEach(function (m) {
    if (!m || m.role !== 'assistant' || !m.agent_steps) { return; }
    m.agent_steps.forEach(function (s) {
      steps.push(s);
      var urls = [];
      if (s.tool === 'fetch_url' && s.input) { urls.push(String(s.input).trim()); }
      String(s.output || '').replace(/https?:\/\/[^\s<>"')\]]+/g, function (u) { urls.push(u.replace(/[.,;:]+$/, '')); return u; });
      urls.forEach(function (u) {
        if (seen[u]) { return; }
        try { var p = new URL(u); if (!/^https?:$/.test(p.protocol)) { return; } seen[u] = 1; sources.push({ url: u, host: p.hostname.replace(/^www\./, ''), path: p.pathname, read: s.tool === 'fetch_url' }); } catch (e) {}
      });
    });
  });
  return { steps: steps, sources: sources };
}
function renderWorkspace() {
  var box = document.getElementById('wsBody');
  if (!box) { return; }
  var d = workspaceData();
  if (!d.steps.length) {
    box.innerHTML = '<div class="ws-empty"><div class="wi">' + (I.spark || '') + '</div>Nothing here yet.<br>Sources the agent searches and reads will appear here.</div>';
    return;
  }
  var reads = d.sources.filter(function (x) { return x.read; }).length;
  var html = '<div class="ws-stats"><div class="ws-stat"><b>' + d.steps.length + '</b><span>tool steps</span></div><div class="ws-stat"><b>' + d.sources.length + '</b><span>sources' + (reads ? ' · ' + reads + ' read' : '') + '</span></div></div>';
  if (d.sources.length) {
    html += '<div class="ws-sec">Sources</div>' + d.sources.slice(0, 40).map(function (x) {
      return '<a class="ws-item" href="' + esc(x.url) + '" target="_blank" rel="noopener noreferrer"><span class="wi">' + (x.read ? I.fileText : I.link) + '</span><span class="wt"><b>' + esc(x.host) + '</b><small>' + esc(x.path && x.path !== '/' ? x.path : x.url) + '</small></span></a>';
    }).join('');
  }
  html += '<div class="ws-sec" style="margin-top:12px">Activity</div>' + d.steps.slice(-30).reverse().map(function (s) {
    var t = AGX_TOOLS[s.tool] || { verb: 'Used ' + (s.tool || 'tool'), icon: 'brainS' };
    return '<div class="ws-item"><span class="wi">' + (I[t.icon] || '') + '</span><span class="wt"><b>' + esc(t.verb) + '</b><small>' + esc(agxArg(s)) + '</small></span></div>';
  }).join('');
  box.innerHTML = html;
}

function aiContent(el, text, meta) {
  meta = meta || {};
  el.querySelector('.content').innerHTML = md(text);
  enhancePre(el);
  var acts = el.querySelector('.acts');
  acts.innerHTML = '';
  acts.appendChild(actionBtn(I.copy, 'Copy', 'Copy response', function () { copyText(text); }));
  acts.appendChild(actionBtn(I.volume, 'Speak', 'Read aloud', function () { speakText(text); }));
  var sh = actionBtn(I.share, 'Share', 'Share chat', function () { shareCurrentChat(sh); });
  if (!currentChat || !currentChat.id || isTempChat) { sh.hidden = true; }
  acts.appendChild(sh);
  var up = actionBtn(I.thumbUp, 'Good', 'Good response', function () { sendFeedback('good', text, meta, up, down); });
  var down = actionBtn(I.thumbDown, 'Bad', 'Bad response', function () { sendFeedback('bad', text, meta, up, down); });
  var saved = read(feedbackKey(meta, text));
  if (saved) { markFeedbackButtons(up, down, saved); }
  acts.appendChild(up);
  acts.appendChild(down);
  var rt = actionBtn(I.retry, 'Retry', 'Regenerate last response', retryLast);
  rt.className = 'retryAct';
  acts.appendChild(rt);
  if (!agentMode) {
    var rw = actionBtn(I.chevS, 'Retry with', 'Retry with another model', function (e) { e.stopPropagation(); if (!busy) { openRetryWith(rw); } });
    rw.className = 'retryAct rwith';
    acts.appendChild(rw);
    var sbsb = actionBtn(I.columns, 'Side by Side', 'Compare this prompt in Side by Side', function () { openInSideBySide(meta.index); });
    sbsb.className = 'sbsAct';
    acts.appendChild(sbsb);
  }
  var stat = [], wc = wordCount(text);
  if (meta.ms) { stat.push(fmtSecs(meta.ms)); }
  if (wc) { stat.push(wc + ' word' + (wc === 1 ? '' : 's')); }
  if (stat.length) {
    var sp = document.createElement('span');
    sp.className = 'mstat';
    sp.textContent = stat.join(' · ');
    sp.title = (meta.ms ? 'Answered in ' + fmtSecs(meta.ms) + ' · ' : '') + wc + ' words';
    acts.appendChild(sp);
  }
  refreshMessageActions();
  scrollDown();
}

function renderCurrentMessages() {
  document.body.classList.remove('loading-chat');
  msgs.innerHTML = '';
  var arr = (currentChat && currentChat.messages) ? currentChat.messages : [];
  welcome.style.display = arr.length ? 'none' : '';
  arr.forEach(function (m, idx) {
    if (!m || !m.role) { return; }
    if (m.compare) { renderCompareTurn(m, idx); return; }
    if (m.role === 'user') { addUserMsg(m.content || '', m.img || '', { index: idx, edited: !!m.edited, branchGroup: branchGroupFor(idx), attachments: m.attachments || [] }); }
    else { var el = addAiMsg({ modelTag: m.model_label }); aiContent(el, m.content || '', { index: idx, ms: m.ms || m.agent_ms }); if ((m.agent_steps && m.agent_steps.length) || m.agent_ms) { renderAgentTrace(el, m.agent_steps || [], m.agent_ms); } if (m.ask && idx === arr.length - 1) { renderAskChips(el, m.ask); } }
  });
  refreshMessageActions();
  scrollDown();
}

/* ── code preview (mini artifacts): add a toolbar + Preview button to html/css/js blocks ── */
function enhancePre(scope) {
  (scope || document).querySelectorAll('pre[data-lang]').forEach(function (pre) {
    if (pre.dataset.pv) { return; }
    var lang = (pre.dataset.lang || '').toLowerCase();
    if (['html', 'css', 'js', 'javascript'].indexOf(lang) === -1) { return; }
    var code = pre.querySelector('code');
    if (!code || !code.textContent.trim()) { return; }
    pre.dataset.pv = '1';
    var wrap = document.createElement('div');
    wrap.className = 'pvwrap';
    pre.parentNode.insertBefore(wrap, pre);
    var bar = document.createElement('div');
    bar.className = 'pvbar';
    var lbl = document.createElement('span');
    lbl.className = 'pvlang';
    lbl.textContent = (lang === 'javascript') ? 'JS' : lang.toUpperCase();
    var btn = document.createElement('button');
    btn.className = 'pvbtn';
    btn.type = 'button';
    btn.innerHTML = I.play + ' Preview';
    btn.addEventListener('click', function () { openPreview(lang, code.textContent); });
    bar.appendChild(lbl); bar.appendChild(btn);
    wrap.appendChild(bar);
    wrap.appendChild(pre);
  });
}

function openPreview(lang, code) {
  var src;
  if (lang === 'css') {
    src = '<!DOCTYPE html><html><head><style>' + code + '</style></head>'
        + '<body style="font-family:system-ui,sans-serif;padding:20px;max-width:640px;margin:0 auto">'
        + '<h1>Heading</h1><p>A paragraph with <a href="#">a link</a> and <strong>bold text</strong>.</p>'
        + '<button>Button</button><ul><li>List item one</li><li>List item two</li></ul>'
        + '<div class="card" style="margin-top:12px">.card element</div></body></html>';
  } else if (lang === 'js' || lang === 'javascript') {
    src = '<!DOCTYPE html><html><head><style>body{font-family:ui-monospace,Consolas,monospace;font-size:13px;white-space:pre-wrap;padding:14px;margin:0}</style></head>'
        + '<body><div id="__out"></div><script>'
        + 'var __out=document.getElementById("__out"),__log=[];'
        + 'console.log=function(){__log.push(Array.prototype.map.call(arguments,String).join(" "));__out.textContent=__log.join("\\n");};'
        + 'try{' + code + '}catch(e){__out.textContent="Error: "+e.message;}'
        + '<\/script></body></html>';
  } else {
    src = code;
  }
  var prettyLang = (lang === 'js' || lang === 'javascript') ? 'JavaScript' : lang.toUpperCase();
  $('#pvTitle').innerHTML = I.play + ' ' + prettyLang + ' preview';
  $('#pvFrame').srcdoc = src;
  $('#previewModal').classList.remove('hidden');
  /* keep the raw source for "open in new tab" */
  $('#pvNewTab').dataset.src = src;
}
(function () {
  var nt = $('#pvNewTab');
  nt.addEventListener('click', function () {
    var src = nt.dataset.src || '';
    var blob = new Blob([src], { type: 'text/html' });
    var url = URL.createObjectURL(blob);
    window.open(url, '_blank');
    setTimeout(function () { URL.revokeObjectURL(url); }, 30000);
  });
})();

/* ── theme toggle ── */
function curTheme() { return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'; }
function setThemeIco() {
  var ico = curTheme() === 'dark' ? I.sun : I.moon;
  $('#themeBtn').innerHTML = ico;
  var m = $('#themeBtnM'); if (m) { m.innerHTML = ico; }
}
function toggleTheme() {
  var t = curTheme() === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', t);
  store('devil_theme', t);
  setThemeIco();
}
$('#themeBtn').addEventListener('click', toggleTheme);
if ($('#themeBtnM')) { $('#themeBtnM').addEventListener('click', toggleTheme); }
setThemeIco();
function optsCanRetry(el) { return el === msgs.lastElementChild || el.nextElementSibling === null; }

function addThinking() {
  var d = addAiMsg({ modelTag: activeModelLabel() });
  d.classList.add('thinking');
  if (agentMode) {
    var t0 = Date.now();
    d.querySelector('.content').innerHTML = '<span class="agx-live"><span class="agx-spark">' + (I.spark || '') + '</span><span class="agx-shimmer">Planning the task…</span><span class="agx-time">0s</span></span>';
    var lab = d.querySelector('.agx-shimmer'), tm = d.querySelector('.agx-time');
    var iv = setInterval(function () {
      if (!document.body.contains(d)) { clearInterval(iv); return; }
      var sec = Math.round((Date.now() - t0) / 1000);
      tm.textContent = fmtDur(sec * 1000 || 1);
      lab.textContent = sec < 4 ? 'Planning the task…' : (sec < 14 ? 'Working with tools…' : 'Putting the answer together…');
    }, 1000);
    refreshMessageActions();
    scrollDown();
    return d;
  }
  d.querySelector('.content').innerHTML = '<span class="dots"><span></span><span></span><span></span></span> thinking…';
  refreshMessageActions();
  scrollDown();
  return d;
}

function addErr(text) {
  var d = document.createElement('div');
  d.className = 'msg-err';
  d.textContent = text;
  msgs.appendChild(d);
  scrollDown();
}

/* ── composer ── */
var inp = $('#inp'), sendBtn = $('#sendBtn'), quickVoiceBtn = $('#quickVoiceBtn');
var promptBtn = $('#promptBtn'), promptMenu = $('#promptMenu');
var PROMPT_LIBRARY = [
  { icon: 'lightbulb', title: 'Explain simply', desc: 'Make any topic easy to understand', text: 'Explain this in simple Hinglish with examples:\n\n' },
  { icon: 'sparkles', title: 'Brainstorm ideas', desc: 'Generate strong creative options', text: 'Brainstorm 10 high-quality ideas for:\n\n' },
  { icon: 'pencil', title: 'Rewrite better', desc: 'Improve tone, clarity and impact', text: 'Rewrite this to be clear, professional, and engaging:\n\n' },
  { icon: 'code', title: 'Debug code', desc: 'Find bugs and provide fixed code', text: 'Debug this code. Explain the issue and give the corrected version:\n\n' },
  { icon: 'message', title: 'Draft message', desc: 'Email, WhatsApp, caption or reply', text: 'Draft a concise and polished message for this situation:\n\n' },
  { icon: 'gauge', title: 'Make a plan', desc: 'Step-by-step action plan', text: 'Create a practical step-by-step plan for:\n\n' }
];
function renderPromptMenu() {
  if (!promptMenu) { return; }
  promptMenu.innerHTML = PROMPT_LIBRARY.map(function (p, i) {
    return '<button class="popt" type="button" data-prompt="' + i + '"><span class="ic">' + (I[p.icon] || I.sparkles) + '</span><span><b>' + p.title + '</b><span>' + p.desc + '</span></span></button>';
  }).join('');
  $$('#promptMenu .popt').forEach(function (b) {
    b.addEventListener('click', function () {
      var p = PROMPT_LIBRARY[parseInt(b.getAttribute('data-prompt'), 10)] || PROMPT_LIBRARY[0];
      inp.value = p.text;
      promptMenu.classList.remove('open');
      inp.focus();
      resize();
    });
  });
}
renderPromptMenu();
if (promptBtn && promptMenu) {
  promptBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    $('#modelMenu').classList.remove('open');
    $('#customModelMenu').classList.remove('open');
    promptMenu.classList.toggle('open');
  });
}

/* ── live voice chat (browser speech recognition + speech synthesis) ── */
var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
var voiceRec = null, voiceMode = false, voiceListening = false, voiceSpeaking = false, voiceFinal = '', voiceRestartTimer = null;
var voicePanelOpen = false, voiceMuted = false;
var voiceBtn = $('#voiceBtn'), voiceChip = $('#voiceChip'), voiceStatus = $('#voiceStatus');
var voiceLive = $('#voiceLive'), voiceLiveState = $('#voiceLiveState'), voiceUserText = $('#voiceUserText'), voiceAiText = $('#voiceAiText');
var voiceMute = $('#voiceMute'), voiceInterrupt = $('#voiceInterrupt'), voiceEnd = $('#voiceEnd'), voiceLiveClose = $('#voiceLiveClose');
function voiceCleanText(text) {
  return String(text || '')
    .replace(/```[\s\S]*?```/g, ' code block ')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1')
    .replace(/[*_#>~]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}
function voicePref() {
  try { return JSON.parse(read('devil_voice') || '{}') || {}; } catch (e) { return {}; }
}
function voiceKey(v) { return (v.voiceURI || v.name || '') + '|' + (v.lang || ''); }
var INDIAN_VOICE_LANGS = ['hi-IN', 'en-IN', 'bn-IN', 'ta-IN', 'te-IN', 'mr-IN', 'gu-IN', 'kn-IN', 'ml-IN', 'pa-IN', 'ur-IN'];
function detectSpeechLang(text) {
  text = String(text || '');
  if (/[\u0900-\u097F]/.test(text)) { return 'hi-IN'; }
  if (/[\u0980-\u09FF]/.test(text)) { return 'bn-IN'; }
  if (/[\u0B80-\u0BFF]/.test(text)) { return 'ta-IN'; }
  if (/[\u0C00-\u0C7F]/.test(text)) { return 'te-IN'; }
  if (/[\u0A80-\u0AFF]/.test(text)) { return 'gu-IN'; }
  if (/[\u0C80-\u0CFF]/.test(text)) { return 'kn-IN'; }
  if (/[\u0D00-\u0D7F]/.test(text)) { return 'ml-IN'; }
  if (/[\u0A00-\u0A7F]/.test(text)) { return 'pa-IN'; }
  if (/[\u0600-\u06FF]/.test(text)) { return 'ur-IN'; }
  return 'en-IN';
}
function isIndianVoice(v) {
  var lang = String((v && v.lang) || '');
  var name = String((v && v.name) || '');
  return /-IN\b/i.test(lang) || /(India|Indian|Hindi|Hindustan|Bengali|Bangla|Tamil|Telugu|Marathi|Gujarati|Kannada|Malayalam|Punjabi|Urdu|Ravi|Heera|Neerja|Kalpana|Hemant|Lekha|Priya)/i.test(name);
}
function voiceScore(v, targetLang) {
  var lang = String((v && v.lang) || '');
  var base = targetLang.split('-')[0];
  var score = 0;
  if (lang.toLowerCase() === targetLang.toLowerCase()) { score += 120; }
  if (lang.split('-')[0].toLowerCase() === base.toLowerCase()) { score += 70; }
  if (/-IN\b/i.test(lang)) { score += 45; }
  if (isIndianVoice(v)) { score += 25; }
  if (/Google|Microsoft|Natural|Premium|Enhanced/i.test((v && v.name) || '')) { score += 8; }
  if (v && v.default) { score += 2; }
  return score;
}
function pickIndianVoice(voices, targetLang) {
  voices = (voices || []).slice();
  if (!voices.length) { return null; }
  voices.sort(function (a, b) { return voiceScore(b, targetLang) - voiceScore(a, targetLang) || String(a.name || '').localeCompare(String(b.name || '')); });
  if (voiceScore(voices[0], targetLang) > 0) { return voices[0]; }
  return null;
}
function pickSpeechVoice(pref, text) {
  if (!('speechSynthesis' in window) || !speechSynthesis.getVoices) { return null; }
  var voices = speechSynthesis.getVoices() || [];
  if (!voices.length) { return null; }
  pref = pref || {};
  if (pref.mode !== 'auto_indian') {
    var saved = voices.filter(function (v) {
      return (pref.uri && v.voiceURI === pref.uri) ||
        (pref.name && v.name === pref.name && (!pref.lang || v.lang === pref.lang)) ||
        (pref.key && voiceKey(v) === pref.key);
    })[0];
    if (saved) { return saved; }
    if (pref.mode === 'browser_default') { return null; }
  }
  var target = detectSpeechLang(text);
  return pickIndianVoice(voices, target) || pickIndianVoice(voices, 'en-IN') || null;
}
function preferredRecognitionLang() {
  var pref = voicePref();
  if (pref && pref.recLang) { return pref.recLang; }
  var nav = navigator.language || 'en-IN';
  if (/-IN$/i.test(nav)) { return nav; }
  return 'en-IN';
}
if (window.speechSynthesis && typeof speechSynthesis.onvoiceschanged !== 'undefined') {
  speechSynthesis.onvoiceschanged = function () { speechSynthesis.getVoices(); };
}
function voiceShort(text, max) {
  text = voiceCleanText(text);
  max = max || 260;
  return text.length > max ? text.slice(0, max - 1).trim() + '…' : text;
}
function setVoiceStatus(msg) {
  msg = msg || 'Live voice ready';
  if (voiceStatus) { voiceStatus.textContent = msg; }
  if (voiceLiveState) { voiceLiveState.textContent = msg; }
}
function setVoiceUser(text, ghost) {
  if (!voiceUserText) { return; }
  voiceUserText.textContent = text || 'Tap the mic and start speaking…';
  voiceUserText.classList.toggle('ghost', !!ghost || !text);
}
function setVoiceAi(text, ghost) {
  if (!voiceAiText) { return; }
  voiceAiText.textContent = text || 'I’ll reply out loud here.';
  voiceAiText.classList.toggle('ghost', !!ghost || !text);
}
function updateVoiceUi() {
  if (voiceBtn) {
    voiceBtn.classList.toggle('on', voicePanelOpen);
    voiceBtn.classList.toggle('listening', voicePanelOpen && voiceListening && !voiceMuted);
    voiceBtn.classList.toggle('speaking', voicePanelOpen && voiceSpeaking);
    voiceBtn.setAttribute('aria-pressed', voicePanelOpen ? 'true' : 'false');
    voiceBtn.title = voicePanelOpen ? 'Live voice chat is open' : 'Live voice chat';
    voiceBtn.innerHTML = voicePanelOpen ? (voiceSpeaking ? I.volumeX : I.micOff) : I.mic;
  }
  if (quickVoiceBtn) {
    var inlineVoice = voiceMode && !voicePanelOpen;
    quickVoiceBtn.classList.toggle('on', inlineVoice);
    quickVoiceBtn.classList.toggle('listening', inlineVoice && voiceListening && !voiceMuted);
    quickVoiceBtn.classList.toggle('speaking', inlineVoice && voiceSpeaking);
    quickVoiceBtn.setAttribute('aria-pressed', inlineVoice ? 'true' : 'false');
    quickVoiceBtn.title = inlineVoice ? 'Turn off voice input' : 'Voice input';
    quickVoiceBtn.innerHTML = inlineVoice ? (voiceSpeaking ? I.volumeX : I.micOff) : I.mic;
  }
  if (voiceChip) { voiceChip.classList.toggle('show', voiceMode && !voicePanelOpen); }
  if (voiceLive) {
    voiceLive.classList.toggle('show', voicePanelOpen);
    voiceLive.classList.toggle('listening', voiceListening && !voiceMuted);
    voiceLive.classList.toggle('speaking', voiceSpeaking);
    voiceLive.classList.toggle('thinking', voiceMode && busy && !voiceSpeaking);
    voiceLive.classList.toggle('muted', voiceMuted);
    voiceLive.setAttribute('aria-hidden', voicePanelOpen ? 'false' : 'true');
  }
  document.body.classList.toggle('voice-open', voicePanelOpen);
  if (voiceMute) {
    voiceMute.classList.toggle('on', !voiceMuted);
    voiceMute.innerHTML = (voiceMuted ? I.micOff : I.mic) + '<span>' + (voiceMuted ? 'Unmute' : 'Mute') + '</span>';
  }
  if (voiceInterrupt) { voiceInterrupt.disabled = !(voiceSpeaking || busy); }
}
function initVoiceRec() {
  if (!SpeechRec) { return false; }
  if (voiceRec) { return true; }
  voiceRec = new SpeechRec();
  voiceRec.lang = preferredRecognitionLang();
  voiceRec.interimResults = true;
  voiceRec.continuous = false;
  voiceRec.maxAlternatives = 1;
  voiceRec.onstart = function () {
    voiceListening = true;
    setVoiceStatus('Listening… speak now');
    setVoiceUser('Listening…', true);
    updateVoiceUi();
  };
  voiceRec.onresult = function (e) {
    var interim = '', final = '';
    for (var i = e.resultIndex; i < e.results.length; i++) {
      var tx = e.results[i][0] ? e.results[i][0].transcript : '';
      if (e.results[i].isFinal) { final += tx + ' '; }
      else { interim += tx + ' '; }
    }
    if (interim.trim()) {
      setVoiceStatus('Listening…');
      setVoiceUser(interim.trim(), false);
    }
    if (final.trim()) {
      voiceFinal += ' ' + final.trim();
      setVoiceUser(voiceFinal.trim(), false);
    }
  };
  voiceRec.onerror = function (e) {
    voiceListening = false;
    updateVoiceUi();
    var err = e && e.error ? e.error : 'voice error';
    if (err === 'not-allowed' || err === 'service-not-allowed') {
      voiceMode = false;
      voicePanelOpen = false;
      setVoiceStatus('Microphone permission denied');
      updateVoiceUi();
      toast('Microphone permission denied', 'warning');
      return;
    }
    if (err === 'no-speech') {
      setVoiceStatus('Still listening…');
      return;
    }
    setVoiceStatus('Voice paused — tap mic if needed');
  };
  voiceRec.onend = function () {
    voiceListening = false;
    updateVoiceUi();
    var final = voiceFinal.trim();
    voiceFinal = '';
    if (voiceMode && final && !busy && !voiceMuted) {
      setVoiceUser(final, false);
      inp.value = final;
      resize();
      setVoiceStatus('Sending voice message…');
      setVoiceAi('Thinking…', true);
      setTimeout(function () { send(); }, 80);
      return;
    }
    if (voiceMode && !busy && !voiceSpeaking && !voiceMuted) {
      clearTimeout(voiceRestartTimer);
      voiceRestartTimer = setTimeout(startVoiceListening, 350);
    }
  };
  return true;
}
function startVoiceListening() {
  if (!voiceMode || busy || voiceSpeaking || voiceMuted) { updateVoiceUi(); return; }
  if (voiceListening) { return; }
  if (!initVoiceRec()) {
    voiceMode = false;
    voicePanelOpen = false;
    updateVoiceUi();
    toast('Live voice chat is not supported in this browser', 'warning');
    return;
  }
  if (voiceRec) { voiceRec.lang = preferredRecognitionLang(); }
  try { voiceRec.start(); }
  catch (e) { clearTimeout(voiceRestartTimer); voiceRestartTimer = setTimeout(startVoiceListening, 700); }
}
function stopVoiceListening() {
  clearTimeout(voiceRestartTimer);
  if (voiceRec && voiceListening) { try { voiceRec.abort(); } catch (e) {} }
  voiceListening = false;
  updateVoiceUi();
}
function setVoiceMode(on) {
  if (on) {
    if (!initVoiceRec()) { voiceMode = false; voicePanelOpen = false; updateVoiceUi(); toast('Live voice chat is not supported in this browser', 'warning'); return false; }
    voiceMode = true;
    voiceMuted = false;
    setVoiceStatus('Listening… speak now');
    if (window.speechSynthesis) { try { window.speechSynthesis.cancel(); } catch (e) {} }
    voiceSpeaking = false;
    updateVoiceUi();
    startVoiceListening();
    return true;
  }
  voiceMode = false;
  stopVoiceListening();
  if (window.speechSynthesis) { try { window.speechSynthesis.cancel(); } catch (e) {} }
  voiceSpeaking = false;
  voiceMuted = false;
  setVoiceStatus('Live voice ended');
  updateVoiceUi();
  return true;
}
function openVoiceLive() {
  if (!initVoiceRec()) { toast('Live voice chat is not supported in this browser', 'warning'); return; }
  voicePanelOpen = true;
  setVoiceUser('Listening…', true);
  setVoiceAi('I’ll reply out loud here.', true);
  updateVoiceUi();
  setVoiceMode(true);
}
function openInlineVoice() {
  if (inlineEdit) { toast('Save or cancel the edited message first', 'warning'); return; }
  if (busy) { pauseSend(); return; }
  if (voiceMode && !voicePanelOpen) { setVoiceMode(false); return; }
  if (!initVoiceRec()) { toast('Voice input is not supported in this browser', 'warning'); return; }
  voicePanelOpen = false;
  setVoiceUser('Listening…', true);
  setVoiceAi('', true);
  setVoiceStatus('Voice input on — listening…');
  setVoiceMode(true);
}
function endVoiceLive() {
  voicePanelOpen = false;
  setVoiceMode(false);
  updateVoiceUi();
}
function toggleVoiceMute() {
  if (!voiceMode) { return; }
  voiceMuted = !voiceMuted;
  if (voiceMuted) {
    stopVoiceListening();
    setVoiceStatus('Mic muted');
  } else {
    setVoiceStatus('Listening… speak now');
    startVoiceListening();
  }
  updateVoiceUi();
}
function interruptVoice() {
  if (voiceSpeaking) {
    if (window.speechSynthesis) { try { window.speechSynthesis.cancel(); } catch (e) {} }
    voiceSpeaking = false;
    setVoiceStatus('Stopped — listening…');
  }
  if (busy) { pauseSend(); setVoiceStatus('Response paused — listening…'); }
  updateVoiceUi();
  if (voiceMode && !voiceMuted) { setTimeout(startVoiceListening, 250); }
}
function speakText(text) {
  var clean = voiceCleanText(text);
  if (!clean) { return; }
  if (!('speechSynthesis' in window) || !window.SpeechSynthesisUtterance) { toast('Read aloud is not supported in this browser', 'warning'); return; }
  voiceSpeaking = true;
  stopVoiceListening();
  try { window.speechSynthesis.cancel(); } catch (e) {}
  var u = new SpeechSynthesisUtterance(clean.slice(0, 3800));
  var pref = voicePref();
  var chosenVoice = pickSpeechVoice(pref, clean);
  if (chosenVoice) { u.voice = chosenVoice; u.lang = chosenVoice.lang || detectSpeechLang(clean); }
  else { u.lang = (pref && pref.lang) || detectSpeechLang(clean) || navigator.language || 'en-IN'; }
  u.rate = Math.min(1.35, Math.max(0.75, parseFloat(pref.rate) || 1));
  u.pitch = 1;
  setVoiceStatus('Devil AI is speaking…');
  if (voicePanelOpen) { setVoiceAi(voiceShort(clean, 360), false); }
  updateVoiceUi();
  u.onend = u.onerror = function () {
    voiceSpeaking = false;
    setVoiceStatus(voiceMode ? (voiceMuted ? 'Mic muted' : 'Listening… speak now') : 'Live voice ready');
    updateVoiceUi();
    if (voiceMode && !voiceMuted && !busy) { setTimeout(startVoiceListening, 450); }
  };
  window.speechSynthesis.speak(u);
}
if (voiceBtn) { voiceBtn.addEventListener('click', function () {
  if (voiceSpeaking && !voiceMode) { interruptVoice(); return; }
  openVoiceLive();
}); }
if (quickVoiceBtn) { quickVoiceBtn.addEventListener('click', function () {
  if (voiceSpeaking && !voiceMode) { interruptVoice(); return; }
  openInlineVoice();
}); }
if ($('#voiceClose')) { $('#voiceClose').addEventListener('click', function () { voicePanelOpen = false; setVoiceMode(false); }); }
if (voiceEnd) { voiceEnd.addEventListener('click', endVoiceLive); }
if (voiceLiveClose) { voiceLiveClose.addEventListener('click', endVoiceLive); }
if (voiceMute) { voiceMute.addEventListener('click', toggleVoiceMute); }
if (voiceInterrupt) { voiceInterrupt.addEventListener('click', interruptVoice); }
document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && voicePanelOpen) { endVoiceLive(); } });

function updateDocTitle() {
  var t = '';
  if (isTempChat) { t = 'Temporary chat'; }
  else if (currentChat && currentChat.messages && currentChat.messages.length) {
    var c = currentChat.id ? chats.filter(function (x) { return x.id === currentChat.id; })[0] : null;
    t = (c && c.title) || currentChat.title || '';
  }
  var label = (MODE_INFO[chatMode] || MODE_INFO.ai).label;
  document.title = t ? (t + ' · ' + label + ' — Devil AI') : (label + ' — Devil AI');
}
function updateChatActions() {
  updateDocTitle();
  document.body.classList.toggle('temp-chat', isTempChat);
  var tb = $('#tempChatBtn');
  var showTemp = !busy && (!currentChat || isTempChat) && !isCmp();
  var ct = $('#chatTitle');
  if (ct) { ct.textContent = (currentChat && !isTempChat && currentChat.messages && currentChat.messages.length) ? (currentChat.title || '') : ''; }
  tb.style.display = showTemp ? 'inline-flex' : 'none';
  tb.classList.toggle('on', isTempChat);
  tb.setAttribute('aria-pressed', isTempChat ? 'true' : 'false');
  tb.title = isTempChat ? 'Turn off temporary chat' : 'Turn on temporary chat';
  var tt = tb.querySelector('.txt');
  if (tt) { tt.textContent = isTempChat ? 'Temp on' : 'Temp'; }
  $('#topNewChatBtn').style.display = (currentChat && !isTempChat) ? 'inline-flex' : 'none';
  var exb = $('#exportBtn');
  if (exb) { exb.style.display = (currentChat && currentChat.messages && currentChat.messages.length) ? 'inline-flex' : 'none'; }
}
function updateSendButton() {
  var hasPayload = !!(inp.value.trim() || (pendingFiles && pendingFiles.length));
  if (busy) {
    sendBtn.hidden = false;
    if (quickVoiceBtn) { quickVoiceBtn.hidden = true; }
    sendBtn.disabled = false;
    sendBtn.classList.add('stopmode');
    sendBtn.title = 'Pause response';
    sendBtn.innerHTML = I.stop;
  } else {
    sendBtn.classList.remove('stopmode');
    sendBtn.title = 'Send (Ctrl/⌘ + Enter)';
    sendBtn.innerHTML = I.send;
    sendBtn.disabled = !hasPayload;
    sendBtn.hidden = !hasPayload;
    if (quickVoiceBtn) { quickVoiceBtn.hidden = hasPayload; }
  }
  updateVoiceUi();
  updateChatActions();
}
function resize() { inp.style.height = 'auto'; inp.style.height = Math.min(inp.scrollHeight, 190) + 'px'; updateSendButton(); }
function compactHistoryForTemp() {
  if (!currentChat || !currentChat.messages) { return []; }
  /* agent turns carry a short work log (tool, input, result) so a follow-up continues where the agent stopped */
  return currentChat.messages.slice(-18).map(function (m) {
    var o = { role: m.role, content: m.content || '' };
    if (m.role === 'assistant' && m.agent_steps && m.agent_steps.length) {
      o.steps = m.agent_steps.slice(-40).map(function (s) { return { tool: s.tool, ok: !!s.ok, input: String(s.input || '').slice(0, 300), output: String(s.output || '').slice(0, 200) }; });
    }
    return o;
  }).filter(function (m) { return m.content; });
}
function startTempChat(forceOn) {
  if (busy) { return; }
  clearEdit();
  if (isTempChat && forceOn !== true) { stopTempChat(); return; }
  isTempChat = true;
  currentChat = { id: null, title: 'Temporary chat', temp: true, messages: [] };
  msgs.innerHTML = '';
  welcome.style.display = '';
  if (window.history) { history.replaceState(null, '', newChatPath() + '?temp=1'); }
  renderList($('#searchInp').value);
  updateChatActions();
  toast('Temporary chat on — this chat will not be saved', 'ghost');
  if (window.innerWidth > 900) { inp.focus(); }
}
function stopTempChat() {
  if (busy) { return; }
  clearEdit();
  isTempChat = false;
  currentChat = null;
  msgs.innerHTML = '';
  welcome.style.display = '';
  if (window.history) { history.replaceState(null, '', newChatPath()); }
  renderList($('#searchInp').value);
  updateChatActions();
  toast('Temporary chat off — new chats will be saved');
  if (window.innerWidth > 900) { inp.focus(); }
}
function pauseSend() {
  if (!busy) { return; }
  if (agentRun) { agentStop(); return; }
  sendSeq++;
  if (activeController) { try { activeController.abort(); } catch (e) {} }
  busy = false;
  pauseCompareTurns();
  $$('.thinking').forEach(function (el) { el.remove(); });
  if (editRestoreChat) {
    currentChat = editRestoreChat;
    editRestoreChat = null;
    renderCurrentMessages();
  }
  activeController = null;
  resize();
  toast('Response paused', 'stop');
}

/* ── attachments: images, docs, PDFs, spreadsheets, text/code, archives metadata ── */
var pendingImg = null;   /* first image data URL for vision */
var pendingFiles = [];
var fileInput = $('#fileInput'), imgChip = $('#imgChip');
var IMG_TYPES = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
var MAX_ATTACH = 6;
var MAX_FILE = 4 * 1024 * 1024;
var MAX_TOTAL_ATTACH = 7 * 1024 * 1024;

function fmtBytes(n) {
  if (!n && n !== 0) { return ''; }
  if (n < 1024) { return n + ' B'; }
  if (n < 1024 * 1024) { return Math.round(n / 1024) + ' KB'; }
  return (Math.round(n / 1024 / 102.4) / 10) + ' MB';
}
function isImgFile(file) { return IMG_TYPES.indexOf(file.type) !== -1 || /\.(png|jpe?g|gif|webp)$/i.test(file.name || ''); }
function fileToDataURL(file, cb) {
  var fr = new FileReader();
  fr.onload = function () { cb(fr.result); };
  fr.onerror = function () { toast('Could not read ' + (file.name || 'file'), 'warning'); };
  fr.readAsDataURL(file);
}
function addPendingFile(file, dataUrl) {
  pendingFiles.push({ name: file.name || 'attachment', type: file.type || 'application/octet-stream', size: file.size || 0, data: dataUrl, is_image: isImgFile(file) });
  pendingImg = (pendingFiles.filter(function (f) { return f.is_image; })[0] || {}).data || null;
  renderAttachmentChips();
  resize();
}
function handleFile(file) {
  if (!file || busy) { return; }
  if (pendingFiles.length >= MAX_ATTACH) { toast('Maximum ' + MAX_ATTACH + ' files per message', 'warning'); return; }
  var sbxUp = agentMode && SBX.on, maxF = sbxUp ? 20 * 1024 * 1024 : MAX_FILE, maxT = sbxUp ? 25 * 1024 * 1024 : MAX_TOTAL_ATTACH;
  if (file.size > maxF) { toast((file.name || 'File') + ' is too large (max ' + fmtBytes(maxF) + ')', 'warning'); return; }
  var total = pendingFiles.reduce(function (sum, f) { return sum + (f.size || 0); }, 0);
  if (total + file.size > maxT) { toast('Attachments are too large together (max ' + fmtBytes(maxT) + ')', 'warning'); return; }
  if (isImgFile(file)) {
    fileToDataURL(file, function (raw) {
      downscale(raw, file.type || 'image/jpeg', function (dataUrl) { addPendingFile(file, dataUrl); });
    });
  } else {
    fileToDataURL(file, function (dataUrl) { addPendingFile(file, dataUrl); });
  }
}
function handleFiles(list) {
  Array.prototype.slice.call(list || []).forEach(handleFile);
}

/* shrink images to keep chats light; small originals pass through */
function downscale(dataUrl, type, cb) {
  var im = new Image();
  im.onload = function () {
    var small = im.width <= 1400 && im.height <= 1400 && dataUrl.length < 300000;
    if (small) { cb(dataUrl); return; }
    var s = Math.min(1280 / Math.max(im.width, im.height), 1);
    var cv = document.createElement('canvas');
    cv.width = Math.max(1, Math.round(im.width * s));
    cv.height = Math.max(1, Math.round(im.height * s));
    cv.getContext('2d').drawImage(im, 0, 0, cv.width, cv.height);
    cb(cv.toDataURL('image/jpeg', 0.86));
  };
  im.onerror = function () { toast('Could not read that image', 'warning'); };
  im.src = dataUrl;
}

function renderAttachmentChips() {
  imgChip.innerHTML = '';
  pendingFiles.forEach(function (f, idx) {
    var chip = document.createElement('div');
    chip.className = 'filechip';
    if (f.is_image) {
      var th = document.createElement('img');
      th.src = f.data; th.alt = '';
      chip.appendChild(th);
    } else {
      var ic = document.createElement('span');
      ic.className = 'fic'; ic.innerHTML = I.paperclip || I.copy;
      chip.appendChild(ic);
    }
    var meta = document.createElement('div');
    meta.className = 'meta';
    var b = document.createElement('b');
    b.textContent = f.name || 'attachment';
    var sz = document.createElement('span');
    sz.textContent = (f.type ? f.type.split('/').pop().toUpperCase() + ' · ' : '') + fmtBytes(f.size);
    meta.appendChild(b); meta.appendChild(sz);
    var rm = document.createElement('button');
    rm.className = 'rm'; rm.type = 'button'; rm.title = 'Remove file';
    rm.innerHTML = I.x;
    rm.addEventListener('click', function () {
      pendingFiles.splice(idx, 1);
      pendingImg = (pendingFiles.filter(function (x) { return x.is_image; })[0] || {}).data || null;
      renderAttachmentChips();
      resize();
    });
    chip.appendChild(meta); chip.appendChild(rm);
    imgChip.appendChild(chip);
  });
  imgChip.classList.toggle('show', pendingFiles.length > 0);
}

$('#attachBtn').addEventListener('click', function () { if (!busy) { fileInput.click(); } });
fileInput.addEventListener('change', function () { handleFiles(fileInput.files); fileInput.value = ''; });
document.addEventListener('paste', function (e) {
  if (busy) { return; }
  var files = [];
  var items = (e.clipboardData || {}).items || [];
  for (var i = 0; i < items.length; i++) {
    if (items[i].kind === 'file') {
      var f = items[i].getAsFile();
      if (f) { files.push(f); }
    }
  }
  if (files.length) { e.preventDefault(); handleFiles(files); }
});
inp.addEventListener('input', function () {
  if (voiceMode && !voicePanelOpen && inp.value.trim()) { setVoiceMode(false); }
  resize();
});
inp.addEventListener('keydown', function (e) {
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); send(); }
});
sendBtn.addEventListener('click', function () { if (busy) { pauseSend(); } else { send(); } });
$('#tempChatBtn').addEventListener('click', startTempChat);
$('#topNewChatBtn').addEventListener('click', function () { if (busy) { pauseSend(); } window.location.href = newChatPath(); });
$('#editCancel').addEventListener('click', function () { clearEdit(); inp.value = ''; resize(); inp.focus(); });

function newChatView() {
  document.body.classList.remove('loading-chat');
  clearEdit();
  isTempChat = false;
  currentChat = null;
  msgs.innerHTML = '';
  welcome.style.display = '';
  renderList($('#searchInp').value);
  updateChatActions();
  if (window.innerWidth > 900) { inp.focus(); }
}
$('#newChatBtn').addEventListener('click', function () { window.location.href = newChatPath(); });

$$('#agentTasks .atask').forEach(function (c) {
  c.addEventListener('click', function () {
    var t = c.dataset.fill || '';
    inp.value = t; resize(); inp.focus();
    var gap = t.indexOf('  ');
    var pos = gap >= 0 ? gap + 1 : t.length;
    try { inp.setSelectionRange(pos, pos); } catch (e) {}
    if (typeof updateSendButton === 'function') { updateSendButton(); }
  });
});
$$('#welcome .card').forEach(function (c) {
  c.addEventListener('click', function () { inp.value = c.dataset.fill; resize(); send(); });
});

/* ── send / retry ── */
function send() {
  if (inlineEdit) { toast('Save or cancel the edited message first', 'warning'); return; }
  if (isCmp()) { sendCompare(); return; }
  var text = inp.value.trim();
  var files = pendingFiles.slice();
  var img = (files.filter(function (f) { return f.is_image; })[0] || {}).data || pendingImg;
  if ((!text && !files.length) || busy) { return; }
  inp.value = ''; pendingFiles = []; pendingImg = null; imgChip.classList.remove('show'); imgChip.innerHTML = '';
  resize();
  var publicFiles = files.map(function (f) { return { name: f.name, type: f.type, size: f.size, is_image: !!f.is_image }; });
  addUserMsg(text, img, { attachments: publicFiles });
  var payload = { message: text, model: currentModel, id: (currentChat && !isTempChat) ? rootChatId() : null };
  if (currentChat && !isTempChat) { payload.variant = activeVariantId(); }
  if (isTempChat) { payload.temp = true; payload.history = compactHistoryForTemp(); }
  if (currentModel === 'custom') { payload.custom_model = currentCustom; }
  if (img) { payload.image = img; }
  if (files.length) { payload.attachments = files.map(function (f) { return { name: f.name, type: f.type, size: f.size, data: f.data }; }); }
  runSend(payload);
}

function retryLast() {
  if (busy || !currentChat) { return; }
  var m = currentChat.messages;
  if (!m.length || m[m.length - 1].role !== 'assistant') { return; }
  /* drop last assistant message visually + in memory */
  m.pop();
  if (msgs.lastElementChild && msgs.lastElementChild.classList.contains('msg-ai')) { msgs.lastElementChild.remove(); }
  var payload = { id: isTempChat ? null : rootChatId(), variant: isTempChat ? '' : activeVariantId(), retry: true, model: currentModel, custom_model: currentModel === 'custom' ? currentCustom : undefined };
  if (isTempChat) { payload.temp = true; payload.history = compactHistoryForTemp(); }
  runSend(payload);
}

function runSend(payload) {
  if (agentMode) { runAgent(payload); return; }
  busy = true;
  clearFollowups(); closePop();
  if (voiceMode) { stopVoiceListening(); setVoiceStatus('Thinking…'); }
  var seq = ++sendSeq;
  activeController = window.AbortController ? new AbortController() : null;
  resize();
  var th = addThinking();
  api(agentMode ? 'agent_chat' : 'chat_send', payload, undefined, activeController ? activeController.signal : null).then(function (j) {
    if (seq !== sendSeq) { return; }
    th.remove();
    if (j.aborted) { toast('Response paused', 'stop'); return; }
    if (j.ok) {
      if (!currentChat) { currentChat = { id: j.id || null, title: j.title || 'New chat', temp: !!payload.temp, messages: [], branch_groups: {} }; }
      if (!currentChat.branch_groups) { currentChat.branch_groups = {}; }
      isTempChat = !!(payload.temp || j.temp || currentChat.temp);
      currentChat.temp = isTempChat;
      currentChat.id = isTempChat ? null : j.id;
      currentChat.root_id = isTempChat ? null : (j.id || currentChat.root_id || currentChat.id);
      if (!isTempChat) {
        currentChat.slug = j.slug || currentChat.slug || currentChat.root_id;
        currentChat.url_model = j.url_model || currentChat.url_model || routeModel();
        currentChat.url_type = j.url_type || currentChat.url_type || routeType();
        currentChat.mode = j.mode || currentChat.mode || (agentMode ? 'agent' : 'ai');
        currentChat.active_variant = j.variant || currentChat.active_variant || currentChat.root_id;
        currentChat.variant_chat_id = j.variant || currentChat.variant_chat_id || '';
      }
      currentChat.title = isTempChat ? 'Temporary chat' : j.title;
      if (!isTempChat) { replaceChatUrl(); }
      if (payload.retry) {
        /* keep existing user msg, replace assistant */
      } else {
        var um = { role: 'user', content: payload.message };
        if (payload.image) { um.img = payload.image; }
        if (payload.attachments) { um.attachments = payload.attachments.map(function (a) { return { name: a.name, type: a.type, size: a.size, is_image: /^data:image\//.test(a.data || '') }; }); }
        currentChat.messages.push(um);
      }
      var am = { role: 'assistant', content: j.reply, model_label: (j.model && j.model.label) || activeModelLabel() };
      if (j.agent && j.agent.steps && j.agent.steps.length) { am.agent_steps = j.agent.steps; }
      if (j.agent && j.agent.ms) { am.agent_ms = j.agent.ms; }
      if (j.ms) { am.ms = j.ms; }
      if (j.model && j.model.id) { am.model_id = j.model.id; if (j.model.id === 'custom' && payload.custom_model) { am.custom_model = payload.custom_model; } }
      if (currentChat && j.mode) { currentChat.mode = j.mode; }
      currentChat.messages.push(am);
      var aiIndex = currentChat.messages.length - 1;
      var el = addAiMsg({ modelTag: (j.model && j.model.label) || activeModelLabel() });
      aiContent(el, j.reply, { index: aiIndex, ms: j.ms || (j.agent && j.agent.ms) });
      if (j.agent) { renderAgentTrace(el, j.agent.steps || [], j.agent.ms); renderWorkspace(); }
      loadFollowups(el, payload.retry ? promptBefore(aiIndex) : payload.message, j.reply);
      if (voiceMode) { speakText(j.reply); }
      if (!isTempChat) { loadChats(); }
      updateChatActions();
    } else {
      if (payload.retry) { /* put a placeholder assistant error, keep chat usable */ }
      addErr(j.error + (j.hint ? '\nHint: ' + j.hint : ''));
      if (voiceMode) { setVoiceStatus('Error — listening again…'); }
    }
  }).finally(function () {
    if (seq === sendSeq) {
      busy = false;
      activeController = null;
      resize();
      if (voiceMode && !voiceSpeaking) { setTimeout(startVoiceListening, 350); }
    }
  });
}


/* ═══ Battle compare modes: Battle (anonymous) + Side by Side ═══ */
function cmpName(side, label) { return label ? label : ('Assistant ' + side.toUpperCase()); }
function cmpRevealed() { return chatMode === 'sbs' || !!(currentChat && currentChat.revealed); }
function lastCompareTurn() { var t = $$('.cmp-turn'); return t.length ? t[t.length - 1] : null; }
function buildCompareTurn(turnIdx, m) {
  var d = document.createElement('div');
  d.className = 'cmp-turn';
  if (turnIdx !== null && turnIdx !== undefined) { d.dataset.turn = String(turnIdx); }
  var html = '<div class="cmp-tabs" role="tablist"><button type="button" class="on" data-tab="a">Assistant A</button><button type="button" data-tab="b">Assistant B</button></div><div class="cmp-grid">';
  ['a', 'b'].forEach(function (s) {
    html += '<div class="cmp-col" data-side="' + s + '"><div class="cmp-head"><span class="cmpab">' + s.toUpperCase() + '</span><span class="cmp-name"></span><span class="cmp-time"></span><span class="sp"></span>' +
      '<button type="button" class="cmp-ibtn" data-act="copy" title="Copy">' + I.copy + '</button>' +
      '<button type="button" class="cmp-ibtn' + (cmpSyncOn() ? ' on' : '') + '" data-act="sync" title="' + (cmpSyncOn() ? 'Scroll sync on — click to turn off' : 'Scroll sync off — click to turn on') + '">' + I.linkS + '</button>' +
      '<button type="button" class="cmp-ibtn" data-act="retry" title="Regenerate this answer">' + I.retry + '</button>' +
      '<button type="button" class="cmp-ibtn" data-act="expand" title="Expand">' + I.maximize + '</button></div>' +
      '<div class="cmp-body"><div class="content"></div></div></div>';
  });
  html += '</div><div class="cmp-vote" hidden><div class="vq">Which response is better?<span class="kh"> Press 1 · 2 · 3 · 4</span></div>' +
    '<button type="button" data-vote="a">' + I.arrowL + ' A is better<kbd>1</kbd></button>' +
    '<button type="button" data-vote="tie">' + I.equal + ' It’s a tie<kbd>3</kbd></button>' +
    '<button type="button" data-vote="bad">' + I.thumbDown + ' Both are bad<kbd>4</kbd></button>' +
    '<button type="button" data-vote="b">B is better ' + I.arrowR + '<kbd>2</kbd></button>' +
    '<div class="cmp-vx"><button type="button" class="regen" data-act="regenboth" title="Get fresh answers from both models">' + I.retryS + ' Regenerate both</button></div></div><div class="cmp-result"></div>';
  d.innerHTML = html;
  d._m = m;
  (function () {
    var g = d.querySelector('.cmp-grid');
    g.addEventListener('scroll', function () {
      var right = g.scrollLeft > (g.scrollWidth - g.clientWidth) / 2;
      d.querySelectorAll('.cmp-tabs button').forEach(function (t) { t.classList.toggle('on', (t.dataset.tab === 'b') === right); });
    }, { passive: true });
  })();
  d.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b || !d.contains(b)) { return; }
    var col = b.closest('.cmp-col'), side = col ? col.dataset.side : '';
    var ans = side ? (d._m.answers[side] || {}) : {};
    if (b.dataset.vote) { voteCompare(d, b.dataset.vote); return; }
    if (b.dataset.tab) { var g = d.querySelector('.cmp-grid'), tc = d.querySelector('.cmp-col[data-side="' + b.dataset.tab + '"]'); g.scrollTo({ left: tc.offsetLeft - g.offsetLeft - 14, behavior: 'smooth' }); return; }
    var act = b.dataset.act;
    if (act === 'copy') { copyText(ans.content || ''); }
    else if (act === 'expand') {
      $('#cmpModalTitle').textContent = cmpName(side, cmpRevealed() ? ans.label : null);
      $('#cmpModalBody').innerHTML = md(ans.content || '');
      enhancePre($('#cmpModalBody'));
      $('#cmpModal').classList.remove('hidden');
    }
    else if (act === 'retry') { retryCompareSide(d, side); }
    else if (act === 'newbattle') { window.location.href = 'battle'; }
    else if (act === 'sync') { toggleCmpSync(); }
    else if (act === 'regenboth') { regenBoth(d); }
    else if (act === 'fork') { continueWithWinner(d, b.dataset.side, b); }
  });
  wireScrollSync(d);
  msgs.appendChild(d);
  welcome.style.display = 'none';
  $$('.cmp-turn').forEach(updateCompareTurn);
  return d;
}
function updateCompareTurn(d) {
  var m = d._m; if (!m || !m.answers) { return; }
  var revealed = cmpRevealed();
  ['a', 'b'].forEach(function (s) {
    var a = m.answers[s] || (m.answers[s] = { status: 'error' });
    var col = d.querySelector('.cmp-col[data-side="' + s + '"]');
    var lab = revealed ? (a.label || null) : null;
    col.classList.toggle('revealed', !!lab);
    col.querySelector('.cmp-name').textContent = cmpName(s, lab);
    var tb = d.querySelector('.cmp-tabs [data-tab="' + s + '"]'); if (tb) { tb.textContent = s.toUpperCase() + ' · ' + (lab || 'Assistant ' + s.toUpperCase()); }
    col.querySelector('.cmp-time').textContent = (a.status === 'done' && a.ms) ? fmtDur(a.ms) : '';
    col.classList.toggle('pending', a.status === 'pending');
    var c = col.querySelector('.content');
    if (a.status === 'done') {
      if (c.dataset.r !== 'done') { c.innerHTML = md(a.content || ''); enhancePre(col); c.dataset.r = 'done'; }
    } else if (a.status === 'pending') {
      if (c.dataset.r !== 'pending') { c.innerHTML = '<span class="cmp-wait"><span class="dots"><span></span><span></span><span></span></span><span class="shim">Generating…</span></span>'; c.dataset.r = 'pending'; }
    } else {
      c.dataset.r = 'err';
      c.innerHTML = '<div class="cmp-err"><span></span><button type="button" data-act="retry">' + I.retry + ' Try again</button></div>';
      c.querySelector('span').textContent = a.status === 'paused' ? 'Response paused.' : (a.error || 'This model could not answer right now.');
    }
    col.querySelector('[data-act=copy]').hidden = a.status !== 'done';
    col.querySelector('[data-act=expand]').hidden = a.status !== 'done';
    col.querySelector('.cmp-head [data-act=retry]').hidden = !(a.status === 'done' && !m.vote && !busy && d.dataset.turn !== undefined);
    col.classList.toggle('win', m.vote === s);
    col.classList.toggle('lose', (m.vote === 'a' || m.vote === 'b') && m.vote !== s);
  });
  var both = m.answers.a.status === 'done' && m.answers.b.status === 'done';
  d.querySelector('.cmp-vote').hidden = !(both && !m.vote && d.dataset.turn !== undefined);
  var r = d.querySelector('.cmp-result');
  if (m.vote) {
    var txt = { a: 'You voted: A is better', b: 'You voted: B is better', tie: 'You voted: it’s a tie', bad: 'You voted: both are bad' }[m.vote] || 'Voted';
    var h = '<span>' + esc(txt) + '</span>';
    if (chatMode === 'battle' && revealed) { h += '<span class="pill"><b>A</b> ' + esc(m.answers.a.label || '?') + '</span><span class="pill"><b>B</b> ' + esc(m.answers.b.label || '?') + '</span>'; }
    if ((m.vote === 'a' || m.vote === 'b') && revealed && d === lastCompareTurn() && m.answers[m.vote] && m.answers[m.vote].status === 'done') {
      h += '<button type="button" class="fork" data-act="fork" data-side="' + m.vote + '" title="Start a Direct chat with this model, keeping the conversation">' + I.fork + ' Continue with ' + esc(m.answers[m.vote].label || 'winner') + '</button>';
    }
    if (chatMode === 'battle' && d === lastCompareTurn()) { h += '<button type="button" class="alt" data-act="newbattle">' + I.swordsS + ' New battle</button>'; }
    r.innerHTML = h;
    r.classList.add('show');
  } else { r.classList.remove('show'); r.innerHTML = ''; }
}
function renderCompareTurn(m, idx) {
  ['a', 'b'].forEach(function (s) { if (m.answers && m.answers[s] && m.answers[s].status === 'pending') { m.answers[s].status = 'paused'; } });
  return buildCompareTurn(idx, m);
}
function pauseCompareTurns() {
  $$('.cmp-turn').forEach(function (d) {
    var m = d._m; if (!m || !m.answers) { return; }
    ['a', 'b'].forEach(function (s) { if (m.answers[s] && m.answers[s].status === 'pending') { m.answers[s].status = 'paused'; } });
    if (d.dataset.turn === undefined) { d.remove(); return; }
    updateCompareTurn(d);
  });
}
function runCompareSides(d, sides, retryFlags) {
  busy = true;
  var seq = ++sendSeq;
  activeController = window.AbortController ? new AbortController() : null;
  var sig = activeController ? activeController.signal : null;
  var m = d._m, left = sides.length;
  sides.forEach(function (s) { m.answers[s].status = 'pending'; delete m.answers[s].error; });
  resize();
  updateCompareTurn(d);
  sides.forEach(function (s) {
    api('compare_answer', { id: currentChat.id, turn: Number(d.dataset.turn), side: s, retry: retryFlags && retryFlags[s] ? 1 : 0 }, undefined, sig).then(function (r) {
      if (seq !== sendSeq) { return; }
      var a = m.answers[s];
      if (r.ok) { a.status = 'done'; a.content = r.content; a.ms = r.ms; if (r.label) { a.label = r.label; } d.querySelector('.cmp-col[data-side="' + s + '"] .content').dataset.r = ''; }
      else if (r.aborted) { a.status = 'paused'; }
      else { a.status = 'error'; a.error = (r.error || 'This model could not answer right now.'); }
      updateCompareTurn(d);
    }).finally(function () {
      left--;
      if (left === 0 && seq === sendSeq) {
        busy = false;
        activeController = null;
        resize();
        updateCompareTurn(d);
        if (m.answers.a.status === 'done' && m.answers.b.status === 'done') { scrollDown(); }
      }
    });
  });
}
function retryCompareSide(d, side) {
  if (busy) { toast('Wait for the current answers first', 'warning'); return; }
  if (!currentChat || !currentChat.id || d.dataset.turn === undefined) { return; }
  var flags = {}; flags[side] = d._m.answers[side].status === 'done';
  runCompareSides(d, [side], flags);
}
function sendCompare() {
  closePop();
  var text = inp.value.trim();
  if (!text || busy) { return; }
  if (pendingFiles && pendingFiles.length) { toast('Battle and Side by Side are text-only — use AI Mode for files', 'warning'); return; }
  inp.value = '';
  resize();
  addUserMsg(text, '', {});
  var m = { role: 'assistant', compare: 1, vote: '', content: '', answers: {
    a: { status: 'pending', label: chatMode === 'sbs' ? battleLabel(cmpA) : null, model_id: chatMode === 'sbs' ? cmpA : null },
    b: { status: 'pending', label: chatMode === 'sbs' ? battleLabel(cmpB) : null, model_id: chatMode === 'sbs' ? cmpB : null } } };
  var d = buildCompareTurn(null, m);
  scrollDown();
  busy = true;
  var seq = ++sendSeq;
  activeController = window.AbortController ? new AbortController() : null;
  resize();
  var payload = { mode: chatMode, message: text, id: (currentChat && currentChat.id) ? currentChat.id : null };
  if (chatMode === 'sbs') { payload.model_a = cmpA; payload.model_b = cmpB; }
  api('compare_start', payload, undefined, activeController ? activeController.signal : null).then(function (j) {
    if (seq !== sendSeq) { return; }
    if (!j.ok) {
      busy = false; activeController = null; resize();
      d.remove();
      if (j.aborted) { toast('Response paused', 'stop'); } else { addErr(j.error + (j.hint ? '\nHint: ' + j.hint : '')); }
      return;
    }
    if (!currentChat) { currentChat = { messages: [], branch_groups: {} }; }
    isTempChat = false;
    currentChat.temp = false;
    currentChat.id = j.id; currentChat.root_id = j.id; currentChat.slug = j.slug;
    currentChat.mode = j.mode; currentChat.title = j.title;
    if (j.mode === 'battle' && j.revealed) { currentChat.revealed = true; }
    replaceChatUrl();
    loadChats();
    currentChat.messages.push({ role: 'user', content: text });
    ['a', 'b'].forEach(function (s) { if (j.models && j.models[s]) { m.answers[s].label = j.models[s].label; m.answers[s].model_id = j.models[s].id; } });
    currentChat.messages.push(m);
    d.dataset.turn = String(j.turn);
    busy = false;
    runCompareSides(d, ['a', 'b'], null);
    updateChatActions();
  });
}
function voteCompare(d, v) {
  if (!currentChat || !currentChat.id || d.dataset.turn === undefined) { return; }
  d.querySelectorAll('.cmp-vote button').forEach(function (b) { b.disabled = true; });
  api('compare_vote', { id: currentChat.id, turn: Number(d.dataset.turn), vote: v }).then(function (j) {
    d.querySelectorAll('.cmp-vote button').forEach(function (b) { b.disabled = false; });
    if (!j.ok) { toast(j.error || 'Could not save your vote', 'warning'); return; }
    d._m.vote = v;
    if (chatMode === 'battle') {
      currentChat.revealed = true;
      (j.messages || []).forEach(function (sm, i) {
        var cm = currentChat.messages[i];
        if (sm && sm.compare && cm && cm.compare && sm.answers) {
          ['a', 'b'].forEach(function (s) { if (sm.answers[s] && cm.answers[s]) { cm.answers[s].label = sm.answers[s].label; cm.answers[s].model_id = sm.answers[s].model_id; } });
        }
      });
      if (d._m.answers.a && !d._m.answers.a.label && j.messages && j.messages[Number(d.dataset.turn)]) {
        var sm2 = j.messages[Number(d.dataset.turn)];
        d._m.answers.a.label = sm2.answers.a.label; d._m.answers.b.label = sm2.answers.b.label;
      }
    }
    $$('.cmp-turn').forEach(updateCompareTurn);
    toast(j.counted ? 'Vote counted — models revealed!' : 'Thanks for your vote', 'check');
  });
}

/* ── leaderboard (from anonymous Battle votes) ── */
function openLeaderboard() {
  var body = $('#lbBody');
  $('#lbModal').classList.remove('hidden');
  body.innerHTML = '<div class="lbempty">Loading leaderboard…</div>';
  api('battle_leaderboard').then(function (j) {
    lbData = j && j.ok ? j : null;
    if (j.ok && lbTab === 'mine') { renderMyVotes(); return; }
    if (!j.ok) { body.innerHTML = '<div class="lbempty"></div>'; body.firstChild.textContent = j.error || 'Could not load the leaderboard.'; return; }
    var total = j.total || 0;
    $('#lbNote').textContent = total ? ('Based on ' + total + ' anonymous Battle Mode vote' + (total > 1 ? 's' : '') + ' · Elo score (every model starts at 1000)') : 'No battle votes yet — start a battle and vote to build the leaderboard.';
    var rank = 0;
    var rows = (j.models || []).map(function (m) {
      var has = m.votes > 0; if (has) { rank++; }
      return '<tr class="' + (has && rank === 1 ? 'top1' : '') + '"><td class="r">' + (has ? rank : '—') + '</td><td class="n"><span style="display:inline-flex;align-items:center;gap:8px"><span class="oi" style="display:inline-flex;color:var(--dim)">' + battleIcon(m.id) + '</span>' + esc(m.label) + '</span></td>' +
        '<td class="s">' + (has ? m.score : '—') + '</td><td class="d">' + m.votes + '</td><td class="d">' + (m.win_rate === null || m.win_rate === undefined ? '—' : m.win_rate + '%') + '</td></tr>';
    }).join('');
    body.innerHTML = '<table class="lbtable"><thead><tr><th>Rank</th><th>Model</th><th>Score</th><th>Votes</th><th>Win rate</th></tr></thead><tbody>' + rows + '</tbody></table>';
  });
}
(function () {
  var tabs = $('#lbTabs'); if (!tabs) { return; }
  tabs.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-lbt]'); if (!b) { return; }
    lbTab = b.dataset.lbt;
    $$('#lbTabs button').forEach(function (x) { x.classList.toggle('on', x === b); });
    if (!lbData) { openLeaderboard(); return; }
    if (lbTab === 'mine') { renderMyVotes(); } else { openLeaderboard(); }
  });
})();
['sbBoard', 'railBoard'].forEach(function (id) { var el = document.getElementById(id); if (el) { el.addEventListener('click', openLeaderboard); } });
(function () { var lb = $('#lbBattle'); if (lb) { lb.addEventListener('click', function () { window.location.href = 'battle'; }); } })();

/* ── scroll-to-latest button (all modes) ── */
(function () {
  var tb = $('#toBottom'); if (!tb || !scroller) { return; }
  function upd() { tb.classList.toggle('show', scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight > 320); }
  scroller.addEventListener('scroll', upd, { passive: true });
  window.addEventListener('resize', upd);
  tb.addEventListener('click', function () { scroller.scrollTo({ top: scroller.scrollHeight, behavior: 'smooth' }); });
  var comp = $('#composer');
  function compH() { if (comp && tb.parentNode) { tb.parentNode.style.setProperty('--compH', comp.offsetHeight + 'px'); } }
  compH();
  if (comp && window.ResizeObserver) { new ResizeObserver(compH).observe(comp); } else { window.addEventListener('resize', compH); }
})();

/* ── open / delete / rename chats ── */
function openChat(id, variant) {
  if (busy) { return; }
  clearEdit();
  var q = 'chat_load&id=' + encodeURIComponent(id);
  if (variant) { q += '&variant=' + encodeURIComponent(variant); }
  api(q).then(function (j) {
    if (!j.ok) { document.body.classList.remove('loading-chat'); toast(j.error || 'Could not open chat', 'warning'); return; }
    isTempChat = false;
    currentChat = j.chat;
    currentChat.temp = false;
    currentChat.branch_groups = j.branch_groups || currentChat.branch_groups || {};
    setChatMode(currentChat.mode || 'ai');
    if (chatMode === 'sbs') {
      (currentChat.messages || []).forEach(function (m) {
        if (m && m.compare && m.answers) { if (m.answers.a && m.answers.a.model_id) { cmpA = m.answers.a.model_id; } if (m.answers.b && m.answers.b.model_id) { cmpB = m.answers.b.model_id; } cmpFromChat = true; }
      });
      renderCmpPickers();
    }
    syncModeUI();
    replaceChatUrl();
    renderCurrentMessages();
    renderList($('#searchInp').value);
    updateChatActions();
    scrollDown();
    SBX.lastList = 0; SBX.view = null; SBX.files = [];
    if (wsIsOpen()) { renderWorkspace(); }
    if (j.agent_job && chatMode === 'agent') { continueAgent(j.agent_job); }
  });
}

function deleteChat(id) {
  api('chat_delete', { id: id }).then(function (j) {
    if (j.ok) {
      chats = chats.filter(function (c) { return c.id !== id; });
      if (currentChat && currentChat.id === id) { newChatView(); }
      renderList($('#searchInp').value);
      toast('Chat deleted', 'trash');
    } else { toast(j.error || 'Delete failed', 'warning'); }
  });
}

var renameId = null;
function openRename(id) {
  renameId = id;
  var c = chats.filter(function (x) { return x.id === id; })[0];
  $('#renameInp').value = c ? c.title : '';
  $('#renameModal').classList.remove('hidden');
  setTimeout(function () { $('#renameInp').focus(); }, 50);
}
$('#renameSave').addEventListener('click', function () {
  var t = $('#renameInp').value.trim();
  if (!t) { return; }
  api('chat_rename', { id: renameId, title: t }).then(function (j) {
    if (j.ok) {
      var c = chats.filter(function (x) { return x.id === renameId; })[0];
      if (c) { c.title = t; }
      if (currentChat && currentChat.id === renameId) { currentChat.title = t; }
      renderList($('#searchInp').value);
      updateChatActions();
      $('#renameModal').classList.add('hidden');
      toast('Chat renamed');
    }
  });
});
$('#renameInp').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#renameSave').click(); } });

/* ── model picker ── */
function renderModelMenu() {
  var mm = $('#modelMenu');
  mm.innerHTML = '';
  models.forEach(function (m) {
    var b = document.createElement('button');
    b.className = 'mopt' + (m.id === currentModel ? ' on' : '');
    b.dataset.model = m.id;
    b.innerHTML = '<span class="ic">' + (I[m.icon] || I.sparkles) + '</span>' +
      '<span class="tx"><b></b><span></span></span><span class="tick">' + I.check + '</span>';
    b.querySelector('.tx b').textContent = m.label;
    b.querySelector('.tx span').textContent = m.tagline;
    b.addEventListener('click', function () {
      currentModel = m.id;
      store('devil_model', m.id);
      setModelBtn();
      mm.classList.remove('open');
    });
    mm.appendChild(b);
  });
  syncModelMenu();
}
function syncModelMenu() {
  $$('#modelMenu .mopt').forEach(function (b) {
    b.classList.toggle('on', b.dataset.model === currentModel);
  });
  $$('#customModelMenu .cmopt').forEach(function (b) {
    b.classList.toggle('on', b.dataset.custom === currentCustom);
  });
}
function setModelBtn() {
  var m = modelById[currentModel] || models[0];
  if (!m) { return; }
  $('#modelLbl').textContent = m.label;
  $('#modelIco').innerHTML = I[m.icon] || I.sparkles;
  $('#customWrap').classList.toggle('show', currentModel === 'custom');
  document.body.classList.toggle('custom-on', currentModel === 'custom');
  setCustomBtn();
  syncModelMenu();
}
function setCustomBtn() {
  var m = customById[currentCustom] || customModels[0];
  if (!m) { return; }
  currentCustom = m.id;
  $('#customLbl').textContent = m.label;
  $('#customIco').innerHTML = providerIcon(m.icon);
  syncModelMenu();
}
function renderCustomModelMenu(filter) {
  var menu = $('#customModelMenu');
  filter = (filter || '').toLowerCase();
  var existing = menu.querySelector('.csearch');
  var val = existing ? existing.value : '';
  menu.innerHTML = '';
  var search = document.createElement('input');
  search.className = 'csearch';
  search.type = 'search';
  search.placeholder = 'Search custom engines…';
  search.value = val;
  var list = document.createElement('div');
  list.className = 'cmlist';
  var shown = 0;
  customModels.forEach(function (m) {
    var hay = (m.label + ' ' + m.scope).toLowerCase();
    if (filter && hay.indexOf(filter) === -1) { return; }
    shown++;
    var b = document.createElement('button');
    b.className = 'cmopt' + (m.id === currentCustom ? ' on' : '');
    b.dataset.custom = m.id;
    b.innerHTML = '<span class="ic">' + providerIcon(m.icon) + '</span>' +
      '<span class="tx"><b></b><span class="scope"></span></span><span class="tick">' + I.check + '</span>';
    b.querySelector('.tx b').textContent = m.label;
    b.querySelector('.scope').textContent = m.scope + (m.vision ? ' · vision' : '');
    b.addEventListener('click', function () {
      currentCustom = m.id;
      store('devil_custom_model', m.id);
      setCustomBtn();
      menu.classList.remove('open');
    });
    list.appendChild(b);
  });
  if (!shown) { list.innerHTML = '<div class="cmempty">No model found.</div>'; }
  search.addEventListener('input', function () { renderCustomModelMenu(search.value); var s = $('#customModelMenu .csearch'); if (s) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); } });
  menu.appendChild(search);
  menu.appendChild(list);
  syncModelMenu();
}
$('#modelBtn').addEventListener('click', function (e) { e.stopPropagation(); $('#customModelMenu').classList.remove('open'); if (promptMenu) { promptMenu.classList.remove('open'); } $('#modelMenu').classList.toggle('open'); });
$('#customModelBtn').addEventListener('click', function (e) {
  e.stopPropagation();
  $('#modelMenu').classList.remove('open');
  if (promptMenu) { promptMenu.classList.remove('open'); }
  renderCustomModelMenu('');
  $('#customModelMenu').classList.toggle('open');
  setTimeout(function () { var s = $('#customModelMenu .csearch'); if (s) { s.focus(); } }, 20);
});
document.addEventListener('click', function (e) {
  if (!e.target.closest('#modelMenu') && !e.target.closest('#modelBtn')) { $('#modelMenu').classList.remove('open'); }
  if (!e.target.closest('#customModelMenu') && !e.target.closest('#customModelBtn')) { $('#customModelMenu').classList.remove('open'); }
  if (promptMenu && !e.target.closest('#promptMenu') && !e.target.closest('#promptBtn')) { promptMenu.classList.remove('open'); }
});

/* ── user menu ── */
$('#userBtn').addEventListener('click', function (e) { e.stopPropagation(); var o = $('#userMenu').classList.toggle('open'); this.setAttribute('aria-expanded', o ? 'true' : 'false'); });
document.addEventListener('click', function (e) { if (!e.target.closest('.sb-bottom')) { $('#userMenu').classList.remove('open'); $('#userBtn').setAttribute('aria-expanded', 'false'); } });
$('#mLogout').addEventListener('click', function () { api('logout', {}).then(function () { window.location.href = 'index.php'; }); });
$('#mCookies').addEventListener('click', function () { $('#userMenu').classList.remove('open'); if (window.devilOpenCookies) { devilOpenCookies(); } });

$('#mDelAcc').addEventListener('click', function () {
  $('#userMenu').classList.remove('open');
  $('#delAccCode').value = '';
  $('#delAccStatus').textContent = '';
  $('#delAccStep2').classList.add('hidden');
  $('#delAccGo').classList.add('hidden');
  $('#delAccSend').classList.remove('hidden');
  $('#delAccModal').classList.remove('hidden');
});
$('#delAccSend').addEventListener('click', function () {
  $('#delAccStatus').textContent = 'Sending code…';
  api('otp_request', { purpose: 'delete' }).then(function (j) {
    if (j.ok) {
      $('#delAccStatus').textContent = 'Code sent to ' + (j.masked || 'your email') + '. It expires in 10 minutes.';
      $('#delAccSend').classList.add('hidden');
      $('#delAccStep2').classList.remove('hidden');
      $('#delAccGo').classList.remove('hidden');
      setTimeout(function () { $('#delAccCode').focus(); }, 60);
    } else {
      $('#delAccStatus').textContent = j.error || 'Could not send the code.';
    }
  });
});
$('#delAccGo').addEventListener('click', function () {
  var code = $('#delAccCode').value.replace(/\D/g, '');
  if (code.length !== 6) { $('#delAccStatus').textContent = 'Enter the 6-digit code from your email.'; return; }
  api('account_delete', { code: code }).then(function (j) {
    if (j.ok) { window.location.href = 'index.php'; }
    else { $('#delAccStatus').textContent = j.error || 'Delete failed.'; }
  });
});

/* ── modals close ── */
$$('[data-close]').forEach(function (b) { b.addEventListener('click', function () { $('#' + b.dataset.close).classList.add('hidden'); }); });
$$('.modal').forEach(function (m) { m.addEventListener('click', function (e) { if (e.target === m) { m.classList.add('hidden'); } }); });
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { $$('.modal').forEach(function (m) { m.classList.add('hidden'); }); } });

/* ── agent workspace UI wiring ── */
(function () {
  var wb = $('#wsBtn'), wc = $('#wsClose');
  if (wb) { wb.addEventListener('click', function () { setWorkspace(!$('#wsPanel').classList.contains('open')); }); }
  if (wc) { wc.addEventListener('click', function () { setWorkspace(false); }); }
  window.__devilOpenWorkspace = function () { setWorkspace(true); };
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setWorkspace(false); } });
  var on = function (id, fn) { var el = document.getElementById(id); if (el) { el.addEventListener('click', fn); } };
  on('railOpen', function () { setSb(true); });
  on('railUser', function () { setSb(true); });
  on('railHistory', function () { setSb(true); setTimeout(function () { var si = $('#searchInp'); if (si) { si.focus(); } }, 240); });
  on('railNew', function () { if (busy) { pauseSend(); } window.location.href = newChatPath(); });
  on('railTheme', function () { var tb = $('#themeBtn'); if (tb) { tb.click(); } });
  document.body.classList.toggle('sb-closed', sb.classList.contains('closed'));
  if (window.MutationObserver) {
    new MutationObserver(syncEmptyState).observe($('#msgs'), { childList: true });
    new MutationObserver(syncEmptyState).observe(document.body, { attributes: true, attributeFilter: ['class'] });
  }
  syncEmptyState();
})();

/* ═══ Extra features: popup menus, export, shortcuts, follow-ups, retry-with, SBS hand-off, battle extras ═══ */
var popEl = null;
function closePop() { if (popEl) { popEl.remove(); popEl = null; } }
function popMenu(anchor, items, opts) {
  opts = opts || {};
  if (popEl && popEl._anchor === anchor) { closePop(); return; }
  closePop();
  var m = document.createElement('div');
  m.className = 'popmenu' + (opts.cls ? ' ' + opts.cls : '');
  m.setAttribute('role', 'menu');
  items.forEach(function (it) {
    if (it.head) { var h = document.createElement('div'); h.className = 'pmhead'; h.textContent = it.head; m.appendChild(h); return; }
    if (it.sep) { m.appendChild(document.createElement('hr')); return; }
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'pmi' + (it.on ? ' on' : ''); b.setAttribute('role', 'menuitem');
    b.innerHTML = '<span class="pmic">' + (it.icon || '') + '</span><span class="pmtx"><b></b>' + (it.sub ? '<small></small>' : '') + '</span>' + (it.on ? '<span class="pmck">' + I.check + '</span>' : '');
    b.querySelector('b').textContent = it.label;
    if (it.sub) { b.querySelector('small').textContent = it.sub; }
    b.addEventListener('click', function (e) { e.stopPropagation(); closePop(); if (it.fn) { it.fn(); } });
    m.appendChild(b);
  });
  m._anchor = anchor;
  document.body.appendChild(m);
  popEl = m;
  var r = anchor.getBoundingClientRect(), vw = window.innerWidth, vh = window.innerHeight;
  var below = vh - r.bottom - 14, above = r.top - 14;
  var up = m.offsetHeight > below && above > below;
  m.style.maxHeight = Math.max(150, up ? above : below) + 'px';
  var w = m.offsetWidth, h = m.offsetHeight;
  var left = opts.alignRight ? r.right - w : r.left;
  left = Math.max(8, Math.min(left, vw - w - 8));
  m.style.left = left + 'px';
  m.style.top = Math.max(8, up ? r.top - 6 - h : r.bottom + 6) + 'px';
}
document.addEventListener('click', function (e) {
  if (!popEl) { return; }
  if (popEl.contains(e.target) || (popEl._anchor && popEl._anchor.contains(e.target))) { return; }
  closePop();
}, true);
window.addEventListener('resize', closePop);
if (scroller) { scroller.addEventListener('scroll', closePop, { passive: true }); }

/* ── response stats helpers ── */
function wordCount(t) { var m = String(t || '').replace(/```[\s\S]*?```/g, ' ').match(/\S+/g); return m ? m.length : 0; }
function fmtSecs(ms) { if (!ms) { return ''; } return ms < 10000 ? (Math.round(ms / 100) / 10) + 's' : fmtDur(ms); }

/* ── Retry with another model (AI Mode) ── */
function retryWith(id, custom) {
  if (busy) { return; }
  currentModel = id;
  store('devil_model', id);
  if (id === 'custom' && custom) { currentCustom = custom; store('devil_custom_model', custom); }
  setModelBtn();
  retryLast();
}
function openRetryWith(anchor) {
  var items = [{ head: 'Retry with' }];
  models.forEach(function (m) {
    if (m.id === 'custom') { return; }
    items.push({ icon: I[m.icon] || I.sparkles, label: m.label, sub: m.tagline, on: currentModel === m.id, fn: function () { retryWith(m.id); } });
  });
  if (customModels.length) {
    items.push({ sep: 1 }, { head: 'Custom engines' });
    customModels.forEach(function (m) {
      items.push({ icon: providerIcon(m.icon), label: m.label, sub: m.scope, on: currentModel === 'custom' && currentCustom === m.id, fn: function () { retryWith('custom', m.id); } });
    });
  }
  popMenu(anchor, items, { cls: 'pm-models' });
}

/* ── Open an AI Mode prompt in Side by Side ── */
function promptBefore(index) {
  var arr = (currentChat && currentChat.messages) || [];
  for (var i = Math.min(index === undefined ? arr.length : index, arr.length) - 1; i >= 0; i--) {
    if (arr[i] && arr[i].role === 'user') { return String(arr[i].content || ''); }
  }
  return '';
}
function battleIdForMessage(m) {
  var id = (m && m.model_id) || currentModel, cu = (m && m.custom_model) || currentCustom;
  var aid = id === 'custom' ? 'custom:' + cu : id;
  return battleById[aid] ? aid : (battleById[id] ? id : '');
}
function openInSideBySide(index) {
  var text = promptBefore(index).trim();
  if (!text) { toast('No text prompt to compare', 'warning'); return; }
  var m = currentChat && currentChat.messages ? currentChat.messages[index] : null;
  var a = battleIdForMessage(m) || cmpA, b = cmpB;
  if (b === a) { b = (BATTLE_POOL.filter(function (x) { return x.id !== a; })[0] || {}).id || b; }
  try { sessionStorage.setItem('devil_prefill', JSON.stringify({ text: text.slice(0, 4000), a: a, b: b, t: Date.now() })); } catch (e) {}
  window.location.href = MODE_INFO.sbs.path;
}
function applySbsPrefill() {
  var raw = null;
  try { raw = sessionStorage.getItem('devil_prefill'); sessionStorage.removeItem('devil_prefill'); } catch (e) {}
  if (!raw || chatMode !== 'sbs' || INITIAL_CHAT_ID) { return; }
  var p = null; try { p = JSON.parse(raw); } catch (e) {}
  if (!p || !p.text || Date.now() - (p.t || 0) > 120000) { return; }
  if (p.a && battleById[p.a]) { cmpA = p.a; }
  if (p.b && battleById[p.b] && p.b !== cmpA) { cmpB = p.b; }
  renderCmpPickers();
  inp.value = p.text; resize();
  setTimeout(function () { if (!busy && inp.value === p.text) { send(); } }, 250);
}
function applyForcedModel() {
  var raw = null;
  try { raw = sessionStorage.getItem('devil_force_model'); sessionStorage.removeItem('devil_force_model'); } catch (e) {}
  if (!raw) { return; }
  var p = null; try { p = JSON.parse(raw); } catch (e) {}
  if (!p || !p.id || !modelById[p.id]) { return; }
  currentModel = p.id;
  if (p.id === 'custom' && p.custom && customById[p.custom]) { currentCustom = p.custom; }
}

/* ── follow-up suggestion chips (latest AI / Agent answer only) ── */
var fupSeq = 0;
function clearFollowups() { fupSeq++; $$('.fups').forEach(function (x) { x.remove(); }); }
function loadFollowups(el, question, answer) {
  if (voiceMode || !answer || answer.length < 40) { return; }
  var my = ++fupSeq;
  api('followups', { question: question || '', answer: answer }).then(function (j) {
    if (my !== fupSeq || busy || !j || !j.ok || !j.items || !j.items.length) { return; }
    if (!el.isConnected || msgs.lastElementChild !== el) { return; }
    var box = document.createElement('div');
    box.className = 'fups';
    j.items.slice(0, 3).forEach(function (q) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'fup';
      b.innerHTML = '<span class="fi">' + I.arrowR + '</span><span class="ft"></span>';
      b.querySelector('.ft').textContent = q;
      b.addEventListener('click', function () { if (busy) { return; } inp.value = q; resize(); send(); });
      box.appendChild(b);
    });
    var body = el.querySelector('.body') || el;
    body.appendChild(box);
    if (scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 260) { scrollDown(); }
  }).catch(function () {});
}

/* ── agent: sources cards + summary chips + per-step copy ── */
function sourcesFromSteps(steps) {
  var seen = {}, out = [];
  function add(url, title, read) {
    url = String(url || '').trim().replace(/[.,;:]+$/, '');
    var p; try { p = new URL(url); } catch (e) { return; }
    if (!/^https?:$/.test(p.protocol)) { return; }
    var key = p.hostname + p.pathname;
    if (seen[key]) { if (read) { seen[key].read = true; } if (title && !seen[key].title) { seen[key].title = title; } return; }
    var o = { url: url, host: p.hostname.replace(/^www\./, ''), title: title || '', read: !!read };
    seen[key] = o; out.push(o);
  }
  (steps || []).forEach(function (s) {
    if (!s || !s.ok) { return; }
    if (s.tool === 'fetch_url') { add(s.input, '', true); }
    if (s.tool === 'web_search') {
      String(s.output || '').split('\n').forEach(function (line) {
        var mm = line.match(/^\s*\d+\.\s+(.*)\s+\((https?:\/\/\S+)\)\s*$/);
        if (!mm) { return; }
        var t = mm[1], dash = t.indexOf(' — ');
        t = dash > 0 ? t.slice(0, dash) : t;
        if (t.length > 90) { t = t.slice(0, 87) + '…'; }
        add(mm[2], t, false);
      });
    }
  });
  out.sort(function (x, y) { return (y.read ? 1 : 0) - (x.read ? 1 : 0); });
  return out;
}
function faviconUrl(host) { return 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(host) + '&sz=32'; }
function renderSourceCards(el, steps) {
  var src = sourcesFromSteps(steps);
  var body = el && el.querySelector('.body');
  if (!body || !src.length) { return; }
  var old = body.querySelector('.agx-src'); if (old) { old.remove(); }
  var cols = window.innerWidth <= 600 ? 2 : 4;
  var max = src.length > cols ? (cols === 2 ? 3 : cols - 1) : cols;
  var box = document.createElement('div');
  box.className = 'agx-src';
  box.innerHTML = '<div class="srchead">' + I.globeS + ' Sources <span>' + src.length + '</span></div><div class="srcrow"></div>';
  var row = box.querySelector('.srcrow');
  src.slice(0, max).forEach(function (s, i) {
    var a = document.createElement('a');
    a.className = 'srccard'; a.href = s.url; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.title = s.url;
    a.innerHTML = '<span class="st"></span><span class="sh"><span class="fav"><img alt="" loading="lazy"><i></i></span><span class="hn"></span>' + (s.read ? '<span class="rd">read</span>' : '') + '</span>';
    a.querySelector('.st').textContent = s.title || (s.host + (function () { try { var p = new URL(s.url).pathname; return p !== '/' ? p : ''; } catch (e) { return ''; } })());
    a.querySelector('.hn').textContent = s.host;
    a.querySelector('i').textContent = (s.host[0] || '?').toUpperCase();
    var im = a.querySelector('img');
    im.addEventListener('error', function () { im.remove(); });
    im.src = faviconUrl(s.host);
    row.appendChild(a);
  });
  if (src.length > max) {
    var more = document.createElement('button');
    more.type = 'button'; more.className = 'srccard more';
    more.innerHTML = '<span class="st">+' + (src.length - max) + ' more</span><span class="sh"><span class="hn">Open workspace</span></span>';
    more.addEventListener('click', function () { if (window.__devilOpenWorkspace) { window.__devilOpenWorkspace(); } });
    row.appendChild(more);
  }
  var acts = el.querySelector('.acts');
  body.insertBefore(box, acts || null);
}
function agentSummaryHtml(steps) {
  var c = { web_search: 0, fetch_url: 0, calculator: 0, datetime: 0 };
  (steps || []).forEach(function (s) { if (s && c[s.tool] !== undefined) { c[s.tool]++; } });
  var src = sourcesFromSteps(steps).length;
  var chips = [];
  if (c.web_search) { chips.push(I.globeS + ' ' + c.web_search + ' search' + (c.web_search > 1 ? 'es' : '')); }
  if (c.fetch_url) { chips.push(I.fileS + ' ' + c.fetch_url + ' page' + (c.fetch_url > 1 ? 's' : '') + ' read'); }
  if (c.calculator) { chips.push(I.calcS + ' ' + c.calculator + ' calculation' + (c.calculator > 1 ? 's' : '')); }
  if (c.datetime) { chips.push(I.clockS + ' clock'); }
  if (src) { chips.push(I.linkS + ' ' + src + ' source' + (src > 1 ? 's' : '')); }
  return chips.length ? '<div class="agx-sum">' + chips.map(function (x) { return '<span>' + x + '</span>'; }).join('') + '</div>' : '';
}

/* ── chat export: Markdown / PDF ── */
function voteText(v) { return { a: 'A is better', b: 'B is better', tie: 'It’s a tie', bad: 'Both are bad' }[v] || ''; }
function exportBaseName() {
  var t = ((currentChat && currentChat.title) || 'devil-ai-chat').toLowerCase().replace(/[^a-z0-9\u0900-\u097f]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
  return t || 'devil-ai-chat';
}
function chatToMarkdown() {
  var c = currentChat; if (!c || !c.messages || !c.messages.length) { return ''; }
  var mode = (MODE_INFO[c.mode || chatMode] || MODE_INFO.ai).label;
  var revealed = cmpRevealed();
  var out = ['# ' + (c.title || 'Devil AI chat'), '', '*' + mode + ' · exported from Devil AI · ' + new Date().toLocaleString() + '*', ''];
  c.messages.forEach(function (m) {
    if (!m || !m.role) { return; }
    if (m.role === 'user') {
      out.push('---', '', '**You**', '', m.content || (m.img ? '*(image)*' : ''));
      (m.attachments || []).forEach(function (a) { if (a && a.name) { out.push('', '📎 ' + a.name); } });
      out.push('');
      return;
    }
    if (m.compare && m.answers) {
      ['a', 'b'].forEach(function (s) {
        var a = m.answers[s] || {};
        out.push('**Assistant ' + s.toUpperCase() + (revealed && a.label ? ' — ' + a.label : '') + '**' + (a.ms ? ' *(' + fmtSecs(a.ms) + ')*' : ''), '', a.status === 'done' ? (a.content || '') : '*(no answer)*', '');
      });
      if (m.vote) { out.push('> 🗳 Vote: ' + voteText(m.vote), ''); }
      return;
    }
    var st = [];
    if (m.model_label) { st.push(m.model_label); }
    if (m.ms || m.agent_ms) { st.push(fmtSecs(m.ms || m.agent_ms)); }
    out.push('**Devil AI**' + (st.length ? ' *(' + st.join(' · ') + ')*' : ''), '');
    if (m.agent_steps && m.agent_steps.length) {
      out.push('<details><summary>Agent steps (' + m.agent_steps.length + ')</summary>', '');
      m.agent_steps.forEach(function (s) { var t = AGX_TOOLS[s.tool] || { verb: s.tool }; out.push('- ' + t.verb + ': ' + agxArg(s) + (s.ok ? '' : ' (failed)')); });
      out.push('', '</details>', '');
    }
    out.push(m.content || '', '');
    var src = m.agent_steps ? sourcesFromSteps(m.agent_steps) : [];
    if (src.length) { out.push('**Sources**', ''); src.slice(0, 10).forEach(function (s, i) { out.push((i + 1) + '. [' + (s.title || s.host).replace(/[\[\]]/g, '') + '](' + s.url + ')'); }); out.push(''); }
  });
  return out.join('\n').replace(/\n{3,}/g, '\n\n');
}
function downloadText(name, text, mime) {
  var blob = new Blob([text], { type: (mime || 'text/plain') + ';charset=utf-8' });
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
  setTimeout(function () { URL.revokeObjectURL(url); }, 20000);
}
function exportMarkdown() {
  var t = chatToMarkdown(); if (!t) { toast('Nothing to export yet', 'warning'); return; }
  downloadText(exportBaseName() + '.md', t, 'text/markdown');
  toast('Markdown downloaded', 'check');
}
function copyMarkdown() { var t = chatToMarkdown(); if (!t) { toast('Nothing to copy yet', 'warning'); return; } copyText(t); }
function exportPdf() {
  var c = currentChat; if (!c || !c.messages || !c.messages.length) { toast('Nothing to export yet', 'warning'); return; }
  var revealed = cmpRevealed();
  var mode = (MODE_INFO[c.mode || chatMode] || MODE_INFO.ai).label;
  var parts = [];
  c.messages.forEach(function (m) {
    if (!m || !m.role) { return; }
    if (m.role === 'user') { parts.push('<div class="u"><div class="who">You</div><div class="b">' + esc(m.content || (m.img ? '(image)' : '')).replace(/\n/g, '<br>') + '</div></div>'); return; }
    if (m.compare && m.answers) {
      parts.push('<div class="cmp">' + ['a', 'b'].map(function (s) {
        var a = m.answers[s] || {};
        return '<div class="col"><div class="who">Assistant ' + s.toUpperCase() + (revealed && a.label ? ' — ' + esc(a.label) : '') + (a.ms ? ' <small>' + fmtSecs(a.ms) + '</small>' : '') + '</div><div class="md">' + (a.status === 'done' ? md(a.content || '') : '<i>(no answer)</i>') + '</div></div>';
      }).join('') + '</div>' + (m.vote ? '<div class="vote">Vote: ' + esc(voteText(m.vote)) + '</div>' : ''));
      return;
    }
    var src = m.agent_steps ? sourcesFromSteps(m.agent_steps) : [];
    parts.push('<div class="a"><div class="who">Devil AI' + (m.model_label ? ' <small>' + esc(m.model_label) + (m.ms || m.agent_ms ? ' · ' + fmtSecs(m.ms || m.agent_ms) : '') + '</small>' : '') + '</div><div class="md">' + md(m.content || '') + '</div>' +
      (src.length ? '<div class="src"><b>Sources</b><ol>' + src.slice(0, 10).map(function (s) { return '<li><a href="' + esc(s.url) + '">' + esc(s.title || s.host) + '</a> <span>' + esc(s.host) + '</span></li>'; }).join('') + '</ol></div>' : '') + '</div>');
  });
  var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' + esc(c.title || 'Devil AI chat') + '</title><style>' +
    'body{font:14px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1d1b1a;max-width:820px;margin:0 auto;padding:28px 22px}' +
    'h1{font:600 24px/1.3 Georgia,serif;margin:0 0 4px}.meta{color:#77706b;font-size:12px;margin-bottom:22px;border-bottom:1px solid #e7e2dc;padding-bottom:14px}' +
    '.who{font-weight:700;font-size:12px;letter-spacing:.3px;text-transform:uppercase;color:#77706b;margin-bottom:4px}.who small{text-transform:none;font-weight:500;letter-spacing:0}' +
    '.u{margin:18px 0 10px;padding:10px 14px;background:#f3f0ec;border-radius:12px}.a{margin:10px 0 18px}' +
    '.cmp{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:10px 0}.col{border:1px solid #e7e2dc;border-radius:12px;padding:10px 12px;min-width:0}' +
    '.vote{font-size:12px;color:#77706b;margin:-4px 0 16px}' +
    'pre{background:#f6f4f1;border:1px solid #e7e2dc;border-radius:8px;padding:10px;overflow:auto;white-space:pre-wrap;word-break:break-word;font-size:12px}code{font-family:ui-monospace,Consolas,monospace;font-size:.92em}' +
    'table{border-collapse:collapse}td,th{border:1px solid #ddd;padding:4px 8px}blockquote{border-left:3px solid #ddd;margin:0;padding-left:12px;color:#555}' +
    '.src{font-size:12px;margin-top:6px}.src span{color:#999}.src a{color:#1d4ed8}a{color:#1d4ed8}img{max-width:100%}' +
    '.pvbar,.copybtn,button{display:none!important}.foot{margin-top:30px;color:#a39c96;font-size:11px;text-align:center}' +
    '@media print{body{padding:0}.u,.col,pre{break-inside:avoid}}' +
    '</style></head><body><h1>' + esc(c.title || 'Devil AI chat') + '</h1><div class="meta">' + esc(mode) + ' · ' + esc(new Date().toLocaleString()) + '</div>' + parts.join('') +
    '<div class="foot">Exported from Devil AI</div></body></html>';
  var w = null;
  try { w = window.open('', '_blank'); } catch (e) { w = null; }
  if (w && w.document) {
    w.document.open(); w.document.write(html); w.document.close();
    setTimeout(function () { try { w.focus(); w.print(); } catch (e) {} }, 400);
    return;
  }
  var f = document.createElement('iframe');
  f.style.cssText = 'position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0';
  document.body.appendChild(f);
  var d = f.contentWindow.document; d.open(); d.write(html); d.close();
  setTimeout(function () { try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) { toast('Could not open the print dialog', 'warning'); } setTimeout(function () { f.remove(); }, 60000); }, 400);
}
(function () {
  var eb = $('#exportBtn'); if (!eb) { return; }
  eb.addEventListener('click', function (e) {
    e.stopPropagation();
    popMenu(eb, [
      { head: 'Export chat' },
      { icon: I.fileText, label: 'Markdown (.md)', sub: 'Download the whole conversation', fn: exportMarkdown },
      { icon: I.download, label: 'PDF', sub: 'Opens the print dialog — choose “Save as PDF”', fn: exportPdf },
      { icon: I.copy, label: 'Copy as Markdown', sub: 'Paste it into notes or docs', fn: copyMarkdown }
    ], { alignRight: true });
  });
})();

/* ── keyboard shortcuts ── */
function openShortcuts() { closePop(); $('#kbdModal').classList.remove('hidden'); }
function focusSearch() {
  if (sb.classList.contains('closed')) { setSb(true); }
  setTimeout(function () { var s = $('#searchInp'); s.focus(); s.select(); }, 60);
}
function kbdVote(key) {
  if (!isCmp() || busy) { return false; }
  var d = lastCompareTurn(); if (!d) { return false; }
  var v = d.querySelector('.cmp-vote'); if (!v || v.hidden) { return false; }
  var map = { '1': 'a', '2': 'b', '3': 'tie', '4': 'bad' };
  var btn = v.querySelector('[data-vote="' + map[key] + '"]'); if (!btn || btn.disabled) { return false; }
  btn.classList.add('kbd-hit');
  voteCompare(d, map[key]);
  return true;
}
document.addEventListener('keydown', function (e) {
  var k = (e.key || '').toLowerCase(), mod = e.ctrlKey || e.metaKey;
  var tg = e.target || {}, tag = (tg.tagName || '').toLowerCase();
  var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || tg.isContentEditable;
  if (mod && !e.shiftKey && !e.altKey && k === 'k') { e.preventDefault(); focusSearch(); return; }
  if (mod && e.shiftKey && !e.altKey && k === 'o') { e.preventDefault(); window.location.href = newChatPath(); return; }
  if (mod && !e.altKey && (k === '/' || e.code === 'Slash')) { e.preventDefault(); openShortcuts(); return; }
  if (mod && e.shiftKey && !e.altKey && k === 's') { e.preventDefault(); if (sb.classList.contains('closed')) { setSb(true); } else { setSb(false); } return; }
  if (e.shiftKey && k === 'escape') { e.preventDefault(); inp.focus(); return; }
  if (k === 'escape') { closePop(); return; }
  if (!mod && !e.altKey && !e.shiftKey && /^[1-4]$/.test(e.key) && (!typing || (tg === inp && !inp.value))) {
    if (document.querySelector('.modal:not(.hidden)')) { return; }
    if (kbdVote(e.key)) { e.preventDefault(); }
    return;
  }
  if (!typing && !mod && !e.altKey && e.key === '/') { e.preventDefault(); inp.focus(); }
});
(function () { var b = $('#mKbd'); if (b) { b.addEventListener('click', function () { var um = $('#userMenu'); if (um) { um.classList.remove('open'); } openShortcuts(); }); } })();

/* ── Battle / Side by Side extras ── */
function cmpSyncOn() { return read('devil_cmp_sync') !== '0'; }
function wireScrollSync(d) {
  var bodies = d.querySelectorAll('.cmp-body');
  if (bodies.length !== 2) { return; }
  Array.prototype.forEach.call(bodies, function (b, i) {
    b.addEventListener('scroll', function () {
      if (b._syncing) { b._syncing = false; return; }
      if (!cmpSyncOn() || window.innerWidth <= 760) { return; }
      var o = bodies[1 - i];
      var max = b.scrollHeight - b.clientHeight, omax = o.scrollHeight - o.clientHeight;
      if (max <= 0 || omax <= 0) { return; }
      var target = Math.round(b.scrollTop / max * omax);
      if (Math.abs(o.scrollTop - target) < 1) { return; }
      o._syncing = true;
      o.scrollTop = target;
    }, { passive: true });
  });
}
function toggleCmpSync() {
  var on = !cmpSyncOn();
  try { localStorage.setItem('devil_cmp_sync', on ? '1' : '0'); } catch (e) {}
  $$('.cmp-ibtn[data-act=sync]').forEach(function (x) { x.classList.toggle('on', on); x.title = on ? 'Scroll sync on — click to turn off' : 'Scroll sync off — click to turn on'; });
  toast(on ? 'Scroll sync on' : 'Scroll sync off', on ? 'check' : undefined);
}
function regenBoth(d) {
  if (busy) { toast('Wait for the current answers first', 'warning'); return; }
  if (!currentChat || !currentChat.id || d.dataset.turn === undefined || d._m.vote) { return; }
  runCompareSides(d, ['a', 'b'], { a: d._m.answers.a.status === 'done', b: d._m.answers.b.status === 'done' });
}
function continueWithWinner(d, side, btn) {
  if (!currentChat || !currentChat.id) { return; }
  if (btn) { btn.disabled = true; }
  api('compare_fork', { id: currentChat.id, side: side }).then(function (j) {
    if (btn) { btn.disabled = false; }
    if (!j.ok) { toast(j.error || 'Could not continue with that model', 'warning'); return; }
    var mdl = j.model || {};
    if (mdl.id) {
      store('devil_model', mdl.id);
      if (mdl.custom) { store('devil_custom_model', mdl.custom); }
      try { sessionStorage.setItem('devil_force_model', JSON.stringify({ id: mdl.id, custom: mdl.custom || '' })); } catch (e) {}
    }
    window.location.href = chatUrlForItem({ id: j.id, slug: j.slug, url_model: j.url_model, url_type: j.url_type, mode: 'ai' });
  }).catch(function () { if (btn) { btn.disabled = false; } toast('Network error', 'warning'); });
}
function swapCmpModels() {
  if (busy) { return; }
  var t = cmpA; cmpA = cmpB; cmpB = t;
  store('devil_sbs_a', cmpA); store('devil_sbs_b', cmpB);
  renderCmpPickers();
  var sw = $('#cmpSwap'); if (sw) { sw.classList.remove('spin'); void sw.offsetWidth; sw.classList.add('spin'); }
  toast('Swapped: A is ' + battleLabel(cmpA) + ', B is ' + battleLabel(cmpB));
}
(function () { var sw = $('#cmpSwap'); if (sw) { sw.addEventListener('click', function (e) { e.stopPropagation(); swapCmpModels(); }); } })();

/* ── leaderboard: My votes tab ── */
var lbData = null, lbTab = 'board';
function renderMyVotes() {
  var body = $('#lbBody'), mine = (lbData && lbData.mine) || { votes: 0, picks: [] };
  var battles = chats.filter(function (c) { return c.mode === 'battle'; });
  var sbsN = chats.filter(function (c) { return c.mode === 'sbs'; }).length;
  $('#lbNote').textContent = 'Your own Battle Mode votes (counted since this feature launched) and your recent battles.';
  var h = '<div class="mystats">' +
    '<div class="mst"><b>' + (mine.votes || 0) + '</b><span>votes cast</span></div>' +
    '<div class="mst"><b>' + battles.length + '</b><span>battles</span></div>' +
    '<div class="mst"><b>' + ((mine.a || 0) + (mine.b || 0)) + '</b><span>winners picked</span></div>' +
    '<div class="mst"><b>' + ((mine.tie || 0) + (mine.bad || 0)) + '</b><span>ties / both bad</span></div></div>';
  if (mine.picks && mine.picks.length) {
    var top = mine.picks[0].count || 1;
    h += '<div class="lbsec">Your favourite models</div><div class="mypicks">' + mine.picks.map(function (p) {
      return '<div class="mpk"><span class="oi">' + battleIcon(p.id) + '</span><span class="nm"></span><span class="bar"><i style="width:' + Math.max(6, Math.round(p.count / top * 100)) + '%"></i></span><span class="ct">' + p.count + '</span></div>';
    }).join('') + '</div>';
  } else {
    h += '<div class="lbempty">No votes recorded for you yet — vote in a battle to see your picks here.</div>';
  }
  if (battles.length) {
    h += '<div class="lbsec">Recent battles</div><div class="myrecent">' + battles.slice(0, 8).map(function (c, i) {
      return '<a class="mrc" data-i="' + i + '" href="#">' + I.swordsS + '<span class="t"></span><span class="d"></span></a>';
    }).join('') + '</div>';
  }
  if (sbsN) { h += '<div class="lbfoot">' + sbsN + ' Side by Side comparison' + (sbsN > 1 ? 's' : '') + ' — those votes are not counted in the public leaderboard.</div>'; }
  body.innerHTML = '<div class="myv">' + h + '</div>';
  if (mine.picks) { Array.prototype.forEach.call(body.querySelectorAll('.mpk .nm'), function (el, i) { el.textContent = mine.picks[i].label; }); }
  Array.prototype.forEach.call(body.querySelectorAll('.mrc'), function (a) {
    var c = battles[Number(a.dataset.i)];
    a.querySelector('.t').textContent = c.title || 'Battle';
    a.querySelector('.d').textContent = c.updated ? new Date(c.updated * 1000).toLocaleDateString() : '';
    a.href = chatUrlForItem(c);
  });
}

/* ── boot ── */
/* ═══════════ Agent sandbox: live step loop, workspace Files / Preview, ask-user ═══════════ */
var SBX = { on: false, tab: 'files', files: [], open: {}, view: null, ports: [], port: 0, info: null, loading: false, lastList: 0 };
var agentRun = null;   /* { job, el, seq, steps:[], t0, stopped } */

Object.assign(AGX_TOOLS, {
  bash:           { verb: 'Ran command', icon: 'terminal' },
  write_file:     { verb: 'Wrote file', icon: 'pencil' },
  read_file:      { verb: 'Read file', icon: 'fileText' },
  list_files:     { verb: 'Listed files', icon: 'folderS' },
  start_server:   { verb: 'Started app', icon: 'play' },
  browser:        { verb: 'Opened in browser', icon: 'monitor' },
  generate_image: { verb: 'Generated image', icon: 'imageS' },
  ask_user:       { verb: 'Asked you', icon: 'help' }
});
var agxArgBase = agxArg;
agxArg = function (s) {
  var inp = String(s.input || '');
  var first = inp.replace(/^\s*```[\w.+-]*\s*\n/, '').split('\n')[0].trim();
  if (s.tool === 'bash') { return first + (inp.trim().split('\n').length > 1 ? ' …' : ''); }
  if (s.tool === 'write_file' || s.tool === 'read_file' || s.tool === 'generate_image') { return first; }
  if (s.tool === 'list_files') { return first || '.'; }
  if (s.tool === 'start_server') { var l = inp.trim().split('\n'); return 'port ' + (l[0] || '').trim() + (l[1] ? ' — ' + l[1].trim() : ''); }
  return agxArgBase(s);
};
function sbxChatQuery() {
  if (isTempChat || !currentChat || !currentChat.id) { return 'temp=1'; }
  return 'id=' + encodeURIComponent(rootChatId());
}
function sbxFileUrl(path, dl) { return 'api.php?action=sbx_file&' + sbxChatQuery() + '&path=' + encodeURIComponent(path) + (dl ? '&dl=1' : ''); }
function agxOutHtml(s) {
  var out = String(s.output || '').trim(), meta = s.meta || {}, h = '';
  if (s.tool === 'bash') { h = '<div class="agx-cmd">$ ' + esc(String(s.input || '').trim()) + '</div>' + esc(out); }
  else if (s.tool === 'write_file') { var body = String(s.input || '').split('\n').slice(1).join('\n'); h = esc(out) + (body.trim() ? '<div class="agx-code">' + esc(body.replace(/^\s*```[\w.+-]*\s*\n|\n?```\s*$/g, '')) + '</div>' : ''); }
  else { h = esc(out); }
  var img = meta.screenshot || meta.image;
  if (img && SBX.on) { h += '<img class="agx-shot" loading="lazy" alt="" src="' + esc(sbxFileUrl(img)) + '">'; }
  if (meta.url && meta.port) { h += '<button type="button" class="agx-pv" data-port="' + (meta.port || '') + '">' + (I.monitor || '') + ' Open preview</button>'; }
  return h;
}

/* classic activity timeline — still used by AI Mode (web search steps) */
/* render a whole trace (used for saved messages and when a run finishes) */
var renderAgentTraceClassic = function (el, steps, ms, live) {
  steps = steps || [];
  if (!el || (!steps.length && !ms && !live)) { return null; }
  var old = el.querySelector('.agx'); if (old) { old.remove(); }
  var box = document.createElement('div');
  box.className = 'agx' + (steps.length || live ? ' open' : '') + (live ? ' live' : '');
  var label = live ? 'Working…' : ((ms ? 'Worked for ' + fmtDur(ms) : 'Worked') + (steps.length ? ' · ' + steps.length + ' step' + (steps.length > 1 ? 's' : '') : ''));
  box.innerHTML = '<button type="button" class="agx-head"><span class="agx-spark">' + (I.spark || '') + '</span><span class="agx-lbl">' + esc(label) + '</span>' +
    '<span class="agx-time"></span><span class="agx-chev">' + (I.chevR || '') + '</span></button>' + (live ? '' : agentSummaryHtml(steps)) +
    '<div class="agx-list"></div>';
  var list = box.querySelector('.agx-list');
  steps.forEach(function (s, i) { list.appendChild(agxStepElClassic(s, i)); });
  if (!live && steps.length) { var dn = document.createElement('div'); dn.className = 'agx-done'; dn.innerHTML = (I.check || '') + ' Done'; list.appendChild(dn); }
  box.querySelector('.agx-head').addEventListener('click', function () { box.classList.toggle('open'); });
  var body = el.querySelector('.body');
  if (body) { body.insertBefore(box, el.querySelector('.content')); }
  if (!live) { renderSourceCards(el, steps); }
  return box;
};
function agxStepElClassic(s, i, running) {
  var t = AGX_TOOLS[s.tool] || { verb: 'Used ' + (s.tool || 'tool'), icon: 'brainS' };
  var st = document.createElement('div');
  st.className = 'agx-step' + (running ? ' running' : (s.ok ? '' : ' fail'));
  st.dataset.i = i;
  var hasOut = !running && (String(s.output || '').trim() || (s.meta && (s.meta.screenshot || s.meta.image)));
  st.innerHTML = (s.thought ? '<div class="agx-thought">' + esc(s.thought) + '</div>' : '') +
    '<span class="agx-ic">' + (running ? '<span class="agx-spin"></span>' : (I[t.icon] || '')) + '</span>' +
    '<button type="button" class="agx-row"><span class="agx-verb">' + esc(running ? t.verb.replace(/^Ran/, 'Running').replace(/^Wrote/, 'Writing').replace(/^Read /, 'Reading ').replace(/^Listed/, 'Listing').replace(/^Started/, 'Starting').replace(/^Opened/, 'Opening').replace(/^Generated/, 'Generating').replace(/^Searched/, 'Searching').replace(/^Calculated/, 'Calculating') : (s.ok ? t.verb : t.verb + ' — failed')) + '</span>' +
    '<span class="agx-arg">' + esc(agxArg(s)) + '</span>' + (s.ms && !running ? '<span class="agx-ms">' + fmtDur(s.ms) + '</span>' : '') + (hasOut ? '<span class="agx-chev">' + (I.chevR || '') + '</span>' : '') + '</button>' +
    (hasOut ? '<div class="agx-out"><button type="button" class="agx-copy" title="Copy output">' + I.copy + '</button><div class="agx-otx">' + agxOutHtml(s) + '</div></div>' : '');
  if (hasOut) {
    st.querySelector('.agx-row').addEventListener('click', function () { st.classList.toggle('open'); });
    st.querySelector('.agx-copy').addEventListener('click', function (e) { e.stopPropagation(); copyText(String(s.output || '')); });
    var pv = st.querySelector('.agx-pv');
    if (pv) { pv.addEventListener('click', function () { SBX.port = Number(pv.dataset.port) || 0; openWsTab('preview'); }); }
    var im = st.querySelector('.agx-shot');
    if (im) { im.addEventListener('click', function () { window.open(im.src, '_blank', 'noopener'); }); }
  }
  return st;
}

/* ═══════════ Pro-style agent transcript (matches the reference agent) ═══════════
   The agent's own words are plain prose between the tool calls. Shell commands are bordered cards:
   header "›_ $ command…   exit 0 · 2.4s ⌄ ✓", body COMMAND / STDOUT / STDERR. File work is a slim
   inline row ("Write  app.html  120 lines  open ›"), and an HTML file gets a live preview card.
   While the agent works there is one status line at the bottom: a pulsing dot + "Thinking…". */
function agtSvg(p, w, sw) { return '<svg viewBox="0 0 24 24" width="' + (w || 13) + '" height="' + (w || 13) + '" fill="none" stroke="currentColor" stroke-width="' + (sw || 2) + '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; }
var AGT = {
  prompt: agtSvg('<path d="m4 17 6-6-6-6"/><path d="M12 19h8"/>', 13),
  ok: agtSvg('<path d="M20 6 9 17l-5-5"/>', 13, 2.4),
  bad: agtSvg('<path d="M18 6 6 18M6 6l12 12"/>', 13, 2.4),
  chev: agtSvg('<path d="m6 9 6 6 6-6"/>', 13),
  yes: agtSvg('<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>', 15),
  no: agtSvg('<circle cx="12" cy="12" r="9"/><path d="m9.5 9.5 5 5m0-5-5 5"/>', 15),
  pen: agtSvg('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>', 15),
  eye: agtSvg('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>', 14),
  win: agtSvg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 6.5h.01"/>', 15),
  copy: agtSvg('<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>', 12),
  send: agtSvg('<path d="M5 12h14M13 6l6 6-6 6"/>', 14),
  x: agtSvg('<path d="M18 6 6 18M6 6l12 12"/>', 13)
};
var AGT_SBX_TOOLS = /^(bash|write_file|read_file|list_files|start_server|browser|generate_image|ask_user)$/;
function agtUse(steps, live) {
  if (live || agentMode) { return true; }
  return (steps || []).some(function (s) { return s && AGT_SBX_TOOLS.test(s.tool || ''); });
}
function agtMs(ms) {
  if (!ms && ms !== 0) { return ''; }
  if (ms < 1000) { return Math.max(1, Math.round(ms)) + 'ms'; }
  if (ms < 60000) { return (ms / 1000).toFixed(1).replace(/\.0$/, '') + 's'; }
  return Math.floor(ms / 60000) + 'm ' + Math.round((ms % 60000) / 1000) + 's';
}
function agtUnfence(s) {
  var t = String(s || '').replace(/^\s*\n/, '').replace(/\s+$/, '');
  var m = t.match(/^\s*```[\w.+-]*[ \t]*\r?\n([\s\S]*?)\r?\n?```\s*$/);
  return m ? m[1] : t;
}
/* split our bash result text back into stdout / stderr / exit code */
function agtExec(s) {
  var t = String(s.output || ''), code = s.meta && s.meta.exit_code !== undefined ? s.meta.exit_code : null;
  var em = t.match(/\n*\[exit code (-?\d+)\]\s*$/);
  if (em) { code = Number(em[1]); t = t.slice(0, em.index); }
  var out = t, err = '', k = t.indexOf('\n[stderr]\n');
  if (k >= 0) { out = t.slice(0, k); err = t.slice(k + 10); }
  else if (/^\[stderr\]\r?\n/.test(t)) { out = ''; err = t.replace(/^\[stderr\]\r?\n/, ''); }
  out = out.replace(/\s+$/, ''); err = err.replace(/\s+$/, '');
  if (out === '(no output)') { out = ''; }
  return { out: out, err: err, code: code };
}
function agtPre(text, cls) {
  return '<div class="agt-pw"><pre class="agt-pre' + (cls ? ' ' + cls : '') + '">' + esc(text) + '</pre>' +
    '<button type="button" class="agt-cp" title="Copy" aria-label="Copy">' + AGT.copy + '</button></div>';
}
function agtCode(text, opts) {
  opts = opts || {};
  var lines = String(text || '').replace(/\r\n/g, '\n').split('\n');
  if (lines.length > 1 && lines[lines.length - 1] === '') { lines.pop(); }
  var start = opts.start || 1, max = opts.max || 600, cut = lines.length > max;
  if (opts.tail && lines.length > opts.tail) { start += lines.length - opts.tail; lines = lines.slice(-opts.tail); cut = false; }
  return '<div class="agt-code' + (opts.cls ? ' ' + opts.cls : '') + '">' + lines.slice(0, max).map(function (l, i) {
    return '<div class="ln"><i>' + (start + i) + '</i><span>' + (esc(l) || ' ') + '</span></div>';
  }).join('') + (cut || opts.more ? '<div class="ln more"><i></i><span>… ' + esc(opts.more || 'more lines') + '</span></div>' : '') + '</div>';
}
function agtThought(text) {
  var t = String(text || '').trim();
  if (!t) { return null; }
  var d = document.createElement('div');
  d.className = 'agt-say';
  d.innerHTML = md(t);
  return d;
}
function agtBindCopy(root) {
  Array.prototype.forEach.call(root.querySelectorAll('.agt-cp'), function (b) {
    b.addEventListener('click', function (e) { e.stopPropagation(); var p = b.parentNode.querySelector('pre'); copyText(p ? p.textContent : ''); });
  });
}
function agtToggle(box, head) {
  head.setAttribute('aria-expanded', box.classList.contains('open') ? 'true' : 'false');
  head.setAttribute('aria-label', box.classList.contains('open') ? 'Collapse' : 'Expand');
  head.addEventListener('click', function (e) {
    if (e.target.closest('.agt-open')) { return; }
    box.classList.toggle('open');
    head.setAttribute('aria-expanded', box.classList.contains('open') ? 'true' : 'false');
    head.setAttribute('aria-label', box.classList.contains('open') ? 'Collapse' : 'Expand');
  });
}
function agtStatusIc(running, ok) { return '<span class="agt-st">' + (running ? '<span class="agx-spin"></span>' : (ok ? AGT.ok : AGT.bad)) + '</span>'; }

/* terminal card: bash + start_server */
function agtTerm(s, running) {
  var m = s.meta || {}, srv = s.tool === 'start_server', inp = String(s.input || ''), cmd, port = '';
  if (srv) { var l = inp.replace(/^\s+/, '').split('\n'); port = (l[0] || '').trim(); cmd = agtUnfence(l.slice(1).join('\n')) || ('serve on port ' + port); }
  else { cmd = agtUnfence(inp); }
  var x = running ? null : agtExec(s), meta = '';
  if (!running) {
    if (srv) { meta = 'port ' + (m.port || port); }
    else if (m.background) { meta = 'background'; }
    else if (x.code !== null && x.code !== undefined) { meta = 'exit ' + x.code; }
    else if (!s.ok) { meta = 'error'; }
    meta += (meta && s.ms ? ' · ' : '') + (s.ms ? agtMs(s.ms) : '');
  }
  var c = document.createElement('div');
  c.className = 'agt-card agt-term' + (running ? ' running' : ' open') + (!running && !s.ok ? ' fail' : '');
  var body = '';
  if (!running) {
    body = '<div class="agt-lb">Command</div>' + agtPre(cmd);
    if (srv || m.background) { body += '<div class="agt-lb">' + (srv ? 'Log' : 'Output') + '</div>' + agtPre(String(s.output || '').trim() || '(no output)', String(s.output || '').trim() ? '' : 'muted'); }
    else {
      if (x.out) { body += '<div class="agt-lb">Stdout</div>' + agtPre(x.out); }
      if (x.err) { body += '<div class="agt-lb">Stderr</div>' + agtPre(x.err, 'err'); }
      if (!x.out && !x.err) { body += '<div class="agt-lb">Stdout</div>' + agtPre('(no output)', 'muted'); }
    }
    if (m.url && m.port) { body += '<button type="button" class="agt-pvbtn" data-port="' + esc(String(m.port)) + '">' + (I.monitor || '') + ' Open preview</button>'; }
  }
  c.innerHTML = '<button type="button" class="agt-hd"><span class="agt-tic">' + AGT.prompt + '</span>' +
    '<span class="agt-cmd">$ ' + esc(cmd.replace(/\s*\n\s*/g, ' ')) + '</span>' +
    (meta ? '<span class="agt-meta">' + esc(meta) + '</span>' : '') +
    (running ? '' : '<span class="agt-chev">' + AGT.chev + '</span>') + agtStatusIc(running, s.ok) + '</button>' +
    (running ? '' : '<div class="agt-bd">' + body + '</div>');
  if (!running) {
    agtToggle(c, c.querySelector('.agt-hd'));
    agtBindCopy(c);
    var pv = c.querySelector('.agt-pvbtn');
    if (pv) { pv.addEventListener('click', function () { SBX.port = Number(pv.dataset.port) || 0; openWsTab('preview'); }); }
  }
  return c;
}

/* slim inline row for everything else (Write / Read / List / Browse / Search …) */
function agtRow(s, running) {
  var m = s.meta || {}, inp = String(s.input || ''), first = inp.replace(/^\s*```[\w.+-]*\s*\n/, '').split('\n')[0].trim();
  var out = String(s.output || '').trim(), verb, arg = first, info = '', body = '', extra = null, openPath = '', live = '';
  switch (s.tool) {
    case 'write_file': {
      var content = agtUnfence(inp.replace(/^\s*[^\n]*\n?/, ''));
      var nl = (out.match(/(\d+) lines\)/) || [])[1], total = nl ? Number(nl) + 1 : content.split('\n').length;
      verb = running ? 'Writing' : 'Write'; openPath = running ? '' : first;
      info = running ? '' : total + ' line' + (total === 1 ? '' : 's');
      var shown = content.split('\n').length, partial = !running && total > shown + 1;
      body = content.trim() ? agtCode(content, { more: partial ? (total - shown) + ' more lines — open the file to see all' : '' }) : '';
      if (running && content.trim()) { live = agtCode(content, { tail: 8, cls: 'stream' }); }
      if (!running && s.ok && /\.html?$/i.test(first)) { extra = agtHtmlCard(first); }
      break;
    }
    case 'read_file': verb = running ? 'Reading' : 'Read'; openPath = running ? '' : first;
      if (out) { var rl = out.split('\n').length; info = rl + ' line' + (rl === 1 ? '' : 's'); body = agtCode(out); } break;
    case 'list_files': verb = running ? 'Exploring' : 'Explore'; arg = (first && first !== '.' && first !== './') ? first : 'files'; body = out ? agtPre(out) : ''; break;
    case 'browser': verb = running ? 'Browsing' : 'Browse'; body = out ? agtPre(out) : '';
      if (m.screenshot && SBX.on) { extra = agtImg(m.screenshot); } break;
    case 'generate_image': verb = running ? 'Generating image' : 'Generate image'; arg = (m.image || first);
      body = inp.split('\n').slice(1).join('\n').trim() ? '<div class="agt-lb">Prompt</div>' + agtPre(inp.split('\n').slice(1).join('\n').trim()) : '';
      if (m.image && SBX.on) { extra = agtImg(m.image); } break;
    case 'web_search': verb = running ? 'Searching' : 'Search'; body = out ? agtPre(out) : ''; break;
    case 'fetch_url': verb = running ? 'Fetching' : 'Fetch'; arg = agxArg(s); body = out ? agtPre(out) : ''; break;
    case 'calculator': verb = running ? 'Calculating' : 'Calculate'; body = out ? agtPre(out) : ''; break;
    case 'datetime': verb = 'Check time'; arg = first || 'server clock'; body = out ? agtPre(out) : ''; break;
    case 'ask_user': verb = 'Ask'; body = out ? agtPre(out) : ''; break;
    default: verb = (running ? 'Using ' : 'Used ') + (s.tool || 'tool'); body = out ? agtPre(out) : '';
  }
  if (!running && !s.ok) { info = (info ? info + ' · ' : '') + 'failed'; if (!body && out) { body = agtPre(out, 'err'); } }
  if (!running && s.ms && s.tool !== 'write_file' && s.tool !== 'read_file') { info = (info ? info + ' · ' : '') + agtMs(s.ms); }
  var r = document.createElement('div');
  r.className = 'agt-row' + (running ? ' running' : '') + (!running && !s.ok ? ' fail' : '');
  r.innerHTML = '<button type="button" class="agt-rh"><span class="agt-verb' + (running ? ' agx-shimmer' : '') + '">' + esc(verb) + '</span>' +
    (arg ? '<span class="agt-arg">' + esc(arg) + '</span>' : '') + (info ? '<span class="agt-info">' + esc(info) + '</span>' : '') +
    (openPath && SBX.on ? '<span class="agt-open" role="link" tabindex="0">open</span>' : '') +
    (s.tool === 'fetch_url' && /^https?:\/\//i.test(first) ? '<a class="agt-open" href="' + esc(first) + '" target="_blank" rel="noopener noreferrer">open ↗</a>' : '') +
    (body && !running ? '<span class="agt-chev">' + AGT.chev + '</span>' : '') +
    (!running && !s.ok ? agtStatusIc(false, false) : '') + '</button>' +
    (body && !running ? '<div class="agt-rb">' + body + '</div>' : '') + live;
  if (body && !running) { agtToggle(r, r.querySelector('.agt-rh')); agtBindCopy(r); }
  var op = r.querySelector('span.agt-open');
  if (op) {
    var go = function (e) { e.stopPropagation(); e.preventDefault(); SBX.view = { path: openPath }; openWsTab('files'); };
    op.addEventListener('click', go);
    op.addEventListener('keydown', function (e) { if (e.key === 'Enter') { go(e); } });
  }
  if (extra) { r.appendChild(extra); }
  return r;
}
function agtImg(path) {
  var w = document.createElement('div');
  w.className = 'agt-media';
  var im = document.createElement('img');
  im.loading = 'lazy'; im.alt = ''; im.src = sbxFileUrl(path);
  im.addEventListener('error', function () { w.remove(); });
  im.addEventListener('click', function () { openImgView(im.src); });
  w.appendChild(im);
  return w;
}
/* HTML file → preview card (title + HTML badge, live render). The file is fetched as text and shown in a
   sandboxed srcdoc iframe without allow-same-origin, so agent-made pages can never touch our origin. */
function agtHtmlCard(path) {
  if (!SBX.on) { return null; }
  var c = document.createElement('div');
  c.className = 'agt-pvc';
  c.innerHTML = '<div class="agt-pvh"><span class="agt-pvi">' + AGT.win + '</span><span class="tt"></span><span class="bd">HTML</span></div>' +
    '<div class="agt-pvf"><div class="agt-pvload"><span class="agx-spin"></span></div><span class="agt-pvv">' + AGT.eye + ' View</span></div>';
  c.querySelector('.tt').textContent = path.split('/').pop();
  var src = '', started = false;
  function load() {
    if (started) { return; } started = true;
    fetch(sbxFileUrl(path), { credentials: 'same-origin' }).then(function (r) { if (!r.ok) { throw new Error('gone'); } return r.text(); }).then(function (t) {
      src = t;
      var tm = t.match(/<title[^>]*>([^<]{1,140})<\/title>/i);
      if (tm && tm[1].trim()) { c.querySelector('.tt').textContent = tm[1].trim().replace(/&amp;/g, '&').replace(/&mdash;/g, '—'); }
      var f = document.createElement('iframe');
      f.setAttribute('sandbox', 'allow-scripts');
      f.setAttribute('loading', 'lazy');
      f.setAttribute('tabindex', '-1');
      f.title = 'Preview of ' + path;
      f.srcdoc = t;
      var ld = c.querySelector('.agt-pvload'); if (ld) { ld.remove(); }
      c.querySelector('.agt-pvf').insertBefore(f, c.querySelector('.agt-pvv'));
    }).catch(function () { c.remove(); });
  }
  if (window.IntersectionObserver) {
    var io = new IntersectionObserver(function (en) { if (en.some(function (e) { return e.isIntersecting; })) { io.disconnect(); load(); } }, { rootMargin: '200px' });
    setTimeout(function () { io.observe(c); }, 0);
  } else { setTimeout(load, 0); }
  c.querySelector('.agt-pvf').addEventListener('click', function () { if (src) { agtFullView(c.querySelector('.tt').textContent, src, path); } });
  return c;
}
function agtFullView(title, src, path) {
  var old = document.getElementById('agtFull'); if (old) { old.remove(); }
  var d = document.createElement('div');
  d.id = 'agtFull';
  d.innerHTML = '<div class="agt-fbox"><div class="agt-fh"><span class="agt-pvi">' + AGT.win + '</span><span class="tt"></span>' +
    '<button type="button" class="fo" title="Open in workspace">Files</button><button type="button" class="fx" title="Close" aria-label="Close">' + AGT.x + '</button></div><iframe sandbox="allow-scripts allow-forms allow-modals"></iframe></div>';
  d.querySelector('.tt').textContent = title;
  d.querySelector('iframe').srcdoc = src;
  function close() { d.remove(); document.removeEventListener('keydown', onKey); }
  function onKey(e) { if (e.key === 'Escape') { close(); } }
  d.addEventListener('click', function (e) { if (e.target === d) { close(); } });
  d.querySelector('.fx').addEventListener('click', close);
  d.querySelector('.fo').addEventListener('click', function () { close(); SBX.view = { path: path }; openWsTab('files'); });
  document.addEventListener('keydown', onKey);
  document.body.appendChild(d);
}

/* one step = the agent's words (if any) + its tool card/row */
function agxStepEl(s, i, running) {
  var w = document.createElement('div');
  w.className = 'agt-step';
  w.dataset.i = i;
  var th = agtThought(s.thought);
  if (th) { w.appendChild(th); }
  w.appendChild(s.tool === 'bash' || s.tool === 'start_server' ? agtTerm(s, running) : agtRow(s, running));
  return w;
}

/* render a whole trace (saved messages, and the live container while a run is going) */
renderAgentTrace = function (el, steps, ms, live) {
  steps = steps || [];
  if (!agtUse(steps, live)) { return renderAgentTraceClassic(el, steps, ms, live); }
  if (!el || (!steps.length && !live)) { return null; }
  var old = el.querySelector('.agt, .agx'); if (old) { old.remove(); }
  var box = document.createElement('div');
  box.className = 'agt' + (live ? ' live' : '');
  steps.forEach(function (s, i) { box.appendChild(agxStepEl(s, i, false)); });
  if (live) { box.insertAdjacentHTML('beforeend', '<div class="agt-live" aria-live="polite"><span class="agt-dot"></span><span class="agt-lt agx-shimmer">Thinking…</span><span class="agt-tm"></span></div>'); }
  var body = el.querySelector('.body');
  if (body) { body.insertBefore(box, el.querySelector('.content')); }
  if (!live) { renderSourceCards(el, steps); }
  return box;
};
function agtAppend(el, node) {
  var box = el && el.querySelector('.agt');
  if (!box) { return; }
  box.insertBefore(node, box.querySelector('.agt-live'));
}

/* ask_user → Battle clarification card: radio options, "write your own", Skip */
function renderAskChips(el, ask) {
  if (!el || !ask) { return; }
  var opts = ask.options || [], needs = ask.needs || [];
  var w = document.createElement('div');
  w.className = 'agt-ask' + (needs.length ? ' needs' : '');
  function answer(text) {
    if (busy || !text) { return; }
    Array.prototype.forEach.call(w.querySelectorAll('button,input'), function (x) { x.disabled = true; });
    w.classList.add('done');
    inp.value = text; resize(); send();
  }
  /* the agent needs secrets (API keys, tokens, passwords): secure fields, saved straight into the sandbox */
  if (needs.length) {
    w.innerHTML = '<div class="agt-askh"><span>' + '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>' + ' Needed to continue</span><button type="button" class="agt-skip">Skip</button></div>' +
      '<form class="agt-need">' + needs.map(function (n, i) {
        return '<label class="agt-nf"><span class="nl"></span><span class="nn"></span><input type="password" autocomplete="off" spellcheck="false" data-i="' + i + '" required></label>';
      }).join('') +
      '<div class="agt-nb"><span class="agt-nnote">Saved only inside your sandbox — never shown in the chat.</span><button type="submit" class="agt-nsave">Save &amp; continue</button></div><div class="agt-nerr" hidden></div></form>';
    Array.prototype.forEach.call(w.querySelectorAll('.agt-nf'), function (lb, i) {
      lb.querySelector('.nl').textContent = needs[i].label || needs[i].name;
      lb.querySelector('.nn').textContent = needs[i].name;
      lb.querySelector('input').setAttribute('aria-label', needs[i].label || needs[i].name);
    });
    var fm = w.querySelector('form'), er = w.querySelector('.agt-nerr'), sv = w.querySelector('.agt-nsave');
    fm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (busy) { return; }
      var ins = Array.prototype.slice.call(fm.querySelectorAll('input'));
      var vals = ins.map(function (x) { return x.value.trim(); });
      if (vals.some(function (v) { return !v; })) { er.hidden = false; er.textContent = 'Fill in every field.'; return; }
      sv.disabled = true; sv.textContent = 'Saving…'; er.hidden = true;
      var i = 0;
      (function next() {
        if (i >= needs.length) {
          ins.forEach(function (x) { x.value = ''; });
          sv.textContent = 'Saved ✓';
          answer('✅ Provided securely: ' + needs.map(function (n) { return n.name; }).join(', ') + ' (saved in the sandbox as environment variables). Please continue.');
          return;
        }
        api('agent_secret', { id: isTempChat ? '' : rootChatId(), temp: isTempChat ? 1 : 0, name: needs[i].name, value: vals[i] }).then(function (j) {
          if (!j || !j.ok) { sv.disabled = false; sv.textContent = 'Save & continue'; er.hidden = false; er.textContent = (j && j.error) || 'Could not save — try again.'; return; }
          i++; next();
        });
      })();
    });
    w.querySelector('.agt-skip').addEventListener('click', function () { answer("I don't have " + needs.map(function (n) { return n.name; }).join(', ') + ' right now. Continue without it if you can (use a clearly marked placeholder or a free alternative), otherwise tell me exactly what is blocked.'); });
    var c0 = el.querySelector('.content');
    c0.parentNode.insertBefore(w, c0.nextSibling);
    setTimeout(function () { var f = w.querySelector('input'); if (f && !busy) { try { f.focus({ preventScroll: true }); } catch (e) {} } }, 60);
    return;
  }
  w.setAttribute('role', 'radiogroup');
  w.setAttribute('aria-label', 'question');
  w.innerHTML = '<div class="agt-askh"><span>' + (opts.length ? 'Pick an option' : 'Your answer') + '</span><button type="button" class="agt-skip">Skip</button></div>' +
    opts.map(function (o, i) { return '<button type="button" role="radio" aria-checked="false" class="agt-opt" data-i="' + i + '"><span class="rd"></span><span class="ol"><span class="olt"></span><span class="old"></span></span></button>'; }).join('') +
    '<form class="agt-askc"><input type="text" maxlength="2000" placeholder="' + (opts.length ? 'Revise options or write your own...' : 'Write your answer...') + '" aria-label="Write your own answer"><button type="submit" aria-label="Submit custom response" title="Send">' + AGT.send + '</button></form>';
  /* "Label — short explanation" → bold label + muted description (like the reference agent) */
  var parts = opts.map(function (o) { var p = String(o).split(/\s+[—–]\s+/); return { l: p[0], d: p.slice(1).join(' — ') }; });
  Array.prototype.forEach.call(w.querySelectorAll('.agt-opt'), function (b) {
    var p = parts[Number(b.dataset.i)];
    b.querySelector('.olt').textContent = p.l;
    var d = b.querySelector('.old'); if (p.d) { d.textContent = p.d; } else { d.remove(); }
  });
  w.addEventListener('click', function (e) {
    var b = e.target.closest('.agt-opt');
    if (b) { b.setAttribute('aria-checked', 'true'); answer(opts[Number(b.dataset.i)]); return; }
    if (e.target.closest('.agt-skip')) { answer('Skip this question — use your best judgement and continue.'); }
  });
  w.querySelector('form').addEventListener('submit', function (e) { e.preventDefault(); answer(w.querySelector('input').value.trim()); });
  var c = el.querySelector('.content');
  c.parentNode.insertBefore(w, c.nextSibling);
}

/* "Was this task successful?" — shown above the composer after a finished agent run */
var agtTaskObs = null;
function agtTaskCardClose() {
  var c = document.getElementById('agtTask'); if (c) { c.remove(); }
  document.removeEventListener('keydown', agtTaskKey);
  if (agtTaskObs) { agtTaskObs.disconnect(); agtTaskObs = null; }
}
function agtTaskKey(e) { if (e.key === 'Escape' && !document.getElementById('agtFull') && !document.querySelector('.modal.show,#imgView.show')) { agtTaskCardClose(); } }
function agtTaskCard(el, reply, idx) {
  agtTaskCardClose();
  var box = document.querySelector('#composer .compbox');
  if (!box) { return; }
  var c = document.createElement('div');
  c.id = 'agtTask';
  c.innerHTML = '<div class="h"><span>Was this task successful?</span><span class="x"><kbd>Esc</kbd><button type="button" class="cl" aria-label="Dismiss">' + AGT.x + '</button></span></div>' +
    '<div class="ol"><button type="button" data-a="yes">' + AGT.yes + ' Yes</button><button type="button" data-a="no">' + AGT.no + ' No</button><button type="button" data-a="more">' + AGT.pen + ' Keep working</button></div>';
  c.addEventListener('click', function (e) {
    if (e.target.closest('.cl')) { agtTaskCardClose(); return; }
    var b = e.target.closest('[data-a]'); if (!b) { return; }
    var a = b.dataset.a;
    agtTaskCardClose();
    if (a === 'more') { if (!busy) { inp.value = 'Keep working'; resize(); send(); } return; }
    var rating = a === 'yes' ? 'good' : 'bad', meta = { index: idx }, key = feedbackKey(meta, reply);
    var up = el && el.querySelector('.acts button[title="Good response"]'), down = el && el.querySelector('.acts button[title="Bad response"]');
    if (!read(key)) {
      store(key, rating);
      if (up && down) { markFeedbackButtons(up, down, rating); }
      api('feedback', { rating: rating, content: reply, chat_id: (currentChat && currentChat.id) || '', message_index: idx });
    }
    if (a === 'yes') { toast('Thanks for the feedback'); }
    else { toast('Thanks — tell the agent what to fix'); inp.focus(); }
  });
  box.parentNode.insertBefore(c, box);
  document.addEventListener('keydown', agtTaskKey);
  /* chat switched / new chat / message removed → the question no longer applies */
  if (window.MutationObserver && msgs) {
    agtTaskObs = new MutationObserver(function () { if (!el || !document.body.contains(el) || msgs.lastElementChild !== el) { agtTaskCardClose(); } });
    agtTaskObs.observe(msgs, { childList: true });
  }
}

/* ── the live loop ── */
function agentLiveEl() {
  agtTaskCardClose();
  var el = addAiMsg({ modelTag: activeModelLabel() });
  el.classList.add('agent-live');
  var box = renderAgentTrace(el, [], 0, true);
  /* one live status only, at the bottom of the turn (like the reference agent): a pulsing dot + "Thinking…"
     while the model plans; while a tool runs it says what is happening ("Bashing…", "Exploring files…"). */
  el.querySelector('.content').innerHTML = '';
  var t0 = Date.now(), tm = box.querySelector('.agt-tm');
  var iv = setInterval(function () { if (!document.body.contains(el) || !el.classList.contains('agent-live')) { clearInterval(iv); return; } tm.textContent = fmtDur(Date.now() - t0); }, 1000);
  agentStatus(el, ['Orchestrating…', 'Thinking…']);
  refreshMessageActions(); scrollDown();
  return el;
}
/* Pro-style live status: one line whose words follow what the agent is doing right now
   ("Thinking…", "Bashing…", "Running commands…", "Exploring files…", "Installing packages…" …).
   text can be a string or a list of labels that rotate every few seconds. */
function agentStatus(el, text) {
  var s = el && el.querySelector('.agt-live .agt-lt');
  if (!s) { return; }
  var list = Array.isArray(text) ? text.filter(Boolean) : [text];
  if (s._iv) { clearInterval(s._iv); s._iv = null; }
  var i = 0;
  s.textContent = list[0] || 'Thinking…';
  if (list.length > 1) {
    s._iv = setInterval(function () {
      if (!document.body.contains(s)) { clearInterval(s._iv); return; }
      i = (i + 1) % list.length; s.textContent = list[i];
    }, 3200);
  }
}
function agtBase(p) { p = String(p || '').trim().replace(/[`'"]/g, ''); return p.split('/').filter(Boolean).pop() || p; }
/* which words to show for a tool that just started */
function agtLiveLabel(tool, input) {
  var inp = String(input || ''), first = inp.replace(/^\s*```[\w.+-]*\s*\n/, '').split('\n')[0].trim();
  switch (tool) {
    case 'bash': {
      var c = inp.replace(/^\s*```[\w.+-]*\s*\n/, '').replace(/```\s*$/, '').trim();
      var lead = c.replace(/^(cd\s+[^&;|]+(&&|;)\s*)+/, '').trim();
      if (/\b(npm|pnpm|yarn|bun)\s+(i|install|add|ci)\b|\bpip3?\s+install\b|\bapt(-get)?\s+install\b|\bnpx\s+create-|\buv\s+(pip|add)\b/.test(c)) { return ['Installing packages…', 'Running commands…']; }
      if (/\b(npm|pnpm|yarn|bun)\s+(run\s+)?build\b|\bvite\s+build\b|\btsc\b|\bnext\s+build\b|\bmake\b|\bcargo\s+build\b|\bgo\s+build\b/.test(c)) { return ['Building the project…', 'Running commands…']; }
      if (/\b(pytest|jest|vitest|mocha|phpunit)\b|\b(npm|pnpm|yarn)\s+(run\s+)?test\b|\bgo\s+test\b|\bcargo\s+test\b/.test(c)) { return ['Running tests…', 'Running commands…']; }
      if (/^git\b/.test(lead)) { return ['Running git…']; }
      if (/^(curl|wget|http)\b/.test(lead)) { return ['Fetching…', 'Running commands…']; }
      if (/^(ls|find|tree|cat|head|tail|less|grep|rg|ag|wc|du|df|stat|file|pwd|which|sed\s+-n|awk)\b/.test(lead)) { return ['Exploring files…', 'Bashing…']; }
      if (/^(mkdir|cp|mv|rm|touch|chmod|ln|unzip|tar|zip)\b/.test(lead)) { return ['Organizing files…', 'Bashing…']; }
      if (/^(python3?|node|php|ruby|deno|bun|go\s+run|java)\b/.test(lead)) { return ['Running code…', 'Bashing…']; }
      return ['Bashing…', 'Running commands…'];
    }
    case 'start_server': return ['Starting the server…', 'Running commands…'];
    case 'write_file': return [first ? 'Writing ' + agtBase(first) + '…' : 'Writing code…'];
    case 'edit_file': return [first ? 'Editing ' + agtBase(first) + '…' : 'Editing code…'];
    case 'read_file': return [first ? 'Reading ' + agtBase(first) + '…' : 'Reading files…', 'Exploring files…'];
    case 'list_files': return ['Exploring files…'];
    case 'browser': return ['Browsing…', 'Taking a screenshot…'];
    case 'generate_image': return ['Generating image…'];
    case 'web_search': return ['Searching the web…'];
    case 'fetch_url': { var h = ''; try { h = new URL(first).hostname.replace(/^www\./, ''); } catch (e) {} return [h ? 'Reading ' + h + '…' : 'Reading the page…', 'Browsing…']; }
    case 'calculator': return ['Calculating…'];
    case 'datetime': return ['Checking the time…'];
    case 'ask_user': return ['Waiting for your answer…'];
    case 'full_internet': return ['Switching to full internet…'];
    default: return ['Working…'];
  }
}

function runAgent(payload) {
  busy = true;
  clearFollowups(); closePop();
  var seq = ++sendSeq;
  resize();
  var el = agentLiveEl();
  agentRun = { job: null, el: el, seq: seq, steps: [], fails: 0 };
  api('agent_start', payload).then(function (j) {
    if (seq !== sendSeq) { return; }
    if (!j.ok) { el.remove(); addErr((j.error || 'Agent failed to start') + (j.hint ? '\nHint: ' + j.hint : '')); agentEnd(); return; }
    agentRun.job = j.job;
    if (!currentChat) { currentChat = { id: j.id || null, title: j.title || 'New chat', temp: !!payload.temp, messages: [], branch_groups: {} }; }
    if (!currentChat.branch_groups) { currentChat.branch_groups = {}; }
    isTempChat = !!(payload.temp || j.temp || currentChat.temp);
    currentChat.temp = isTempChat;
    if (!isTempChat) {
      currentChat.id = j.id; currentChat.root_id = j.id;
      currentChat.slug = j.slug || currentChat.slug || j.id;
      currentChat.url_model = j.url_model || currentChat.url_model || routeModel();
      currentChat.url_type = j.url_type || currentChat.url_type || routeType();
      currentChat.mode = 'agent';
      currentChat.active_variant = j.variant || currentChat.active_variant || j.id;
      currentChat.variant_chat_id = j.variant || '';
      currentChat.title = j.title || currentChat.title;
      replaceChatUrl();
      loadChats();
    }
    if (!payload.retry) {
      var um = { role: 'user', content: payload.message };
      if (payload.image) { um.img = payload.image; }
      if (payload.attachments) { um.attachments = payload.attachments.map(function (a) { return { name: a.name, type: a.type, size: a.size, is_image: /^data:image\//.test(a.data || '') }; }); }
      currentChat.messages.push(um);
    }
    updateChatActions();
    if (j.uploads && j.uploads.length) { SBX.lastList = 0; if (wsIsOpen()) { renderWorkspace(); } }
    agentLoop();
  });
}
function continueAgent(job) {
  if (busy) { return; }
  busy = true;
  var seq = ++sendSeq;
  var el = agentLiveEl();
  agentStatus(el, 'Resuming…');
  agentRun = { job: job, el: el, seq: seq, steps: [], fails: 0 };
  resize();
  agentLoop();
}
function agentLoop() {
  var r = agentRun;
  if (!r || r.seq !== sendSeq) { return; }
  activeController = window.AbortController ? new AbortController() : null;
  api('agent_step', { job: r.job }, undefined, activeController ? activeController.signal : null).then(function (j) {
    if (!agentRun || r !== agentRun || r.seq !== sendSeq) { return; }
    if (j.aborted) { return; }
    if (!j.ok) {
      if (/expired|Unknown agent job/i.test(j.error || '')) { r.el.remove(); addErr(j.error); agentEnd(); return; }
      r.fails++;
      /* network blips / a sandbox host hand-over (~1 min): keep retrying with back-off before giving up */
      if (r.fails <= 8) { agentStatus(r.el, 'Reconnecting…'); setTimeout(agentLoop, Math.min(10, 2 * r.fails) * 1000); return; }
      agentStatus(r.el, 'Lost connection — ' + (j.error || 'the server did not answer'));
      agentResumeBtn(r);
      return;
    }
    r.fails = 0;
    var ev = j.event || {};
    if (ev.type === 'tool_start') {
      var s = { tool: ev.tool, input: ev.input, thought: ev.thought };
      r.runEl = agxStepEl(s, r.steps.length, true);
      agtAppend(r.el, r.runEl);
      agentStatus(r.el, agtLiveLabel(ev.tool, ev.input));
      scrollDown();
    } else if (ev.type === 'tool_done' && ev.step) {
      r.steps.push(ev.step);
      var done = agxStepEl(ev.step, r.steps.length - 1, false);
      if (r.runEl && r.runEl.parentNode) { r.runEl.parentNode.replaceChild(done, r.runEl); } else { agtAppend(r.el, done); }
      r.runEl = null;
      agentStatus(r.el, ev.step.ok === false ? ['Looking at the error…', 'Thinking…'] : ['Reviewing the output…', 'Thinking…']);
      sbxAfterStep(ev.step);
      scrollDown();
    } else if (ev.type === 'retry') {
      agentStatus(r.el, ev.note || 'Retrying…');
    } else if (ev.type === 'busy') {
      setTimeout(agentLoop, 1500); return;
    }
    if (j.done) { agentFinish(j); return; }
    agentLoop();
  });
}
function agentResumeBtn(r) {
  var b = document.createElement('button');
  b.type = 'button'; b.className = 'ask-chip agx-resume'; b.textContent = 'Resume';
  b.addEventListener('click', function () { b.remove(); r.fails = 0; agentStatus(r.el, 'Resuming…'); agentLoop(); });
  r.el.querySelector('.content').appendChild(b);
}
function agentFinish(j) {
  var r = agentRun;
  var el = r.el;
  el.classList.remove('agent-live', 'thinking');
  var reply = j.reply || (j.error ? '⚠️ ' + j.error : '');
  var steps = (j.agent && j.agent.steps) || r.steps;
  var ms = (j.agent && j.agent.ms) || 0;
  var am = { role: 'assistant', content: reply, model_label: (j.model && j.model.label) || activeModelLabel(), agent: 1, agent_ms: ms };
  if (steps.length) { am.agent_steps = steps; }
  if (j.ask) { am.ask = j.ask; }
  if (j.model && j.model.id) { am.model_id = j.model.id; }
  if (currentChat) {
    currentChat.messages.push(am);
    if (j.title && !isTempChat) { currentChat.title = j.title; }
  }
  var idx = currentChat ? currentChat.messages.length - 1 : 0;
  aiContent(el, reply, { index: idx, ms: ms });
  var lb = el.querySelector('.agt.live');
  if (lb && lb.querySelectorAll('.agt-step').length === steps.length && !lb.querySelector('.agt-row.running,.agt-card.running')) {
    var ll = lb.querySelector('.agt-live'); if (ll) { ll.remove(); }
    lb.classList.remove('live');
    renderSourceCards(el, steps);
  } else { renderAgentTrace(el, steps, ms); }
  var stopped = !!(r.stopped || j.stopped);
  if (stopped) { var gs = document.createElement('div'); gs.className = 'agt-stopped'; gs.setAttribute('aria-label', 'Response ended'); gs.textContent = 'Generation stopped'; el.querySelector('.content').insertAdjacentElement('afterend', gs); }
  if (j.ask) { renderAskChips(el, j.ask); }
  renderWorkspace();
  agentEnd();
  if (!stopped && !j.ask && !j.error && steps.length && reply) { agtTaskCard(el, reply, idx); }
  if (!isTempChat) { loadChats(); }
}
function agentEnd() {
  agentRun = null;
  busy = false;
  activeController = null;
  resize();
  updateChatActions();
}
function agentStop() {
  var r = agentRun;
  if (!r) { return false; }
  sendSeq++;
  if (activeController) { try { activeController.abort(); } catch (e) {} }
  if (!r.job) { r.el.remove(); agentEnd(); return true; }
  agentStatus(r.el, 'Stopping…');
  r.stopped = true;
  var job = r.job;
  api('agent_cancel', { job: job }).then(function (j) {
    agentRun = r; r.seq = sendSeq;
    if (j.ok) { agentFinish(j); } else { r.el.remove(); agentEnd(); }
    toast('Agent stopped', 'stop');
  });
  return true;
}

/* refresh panel bits after a tool ran */
function sbxAfterStep(s) {
  if (!SBX.on) { return; }
  SBX.lastList = 0;
  if (s.ok && s.meta && s.meta.port && (s.tool === 'start_server' || s.meta.url)) {
    SBX.port = Number(s.meta.port);
    openWsTab('preview');
    return;
  }
  if (wsIsOpen()) { renderWorkspace(); }
}

/* ── workspace panel: Files / Preview / Activity ── */
function wsIsOpen() { var p = document.getElementById('wsPanel'); return !!(p && p.classList.contains('open')); }
function openWsTab(tab) { SBX.tab = tab; SBX.view = tab === 'files' ? SBX.view : null; if (!wsIsOpen()) { setWorkspace(true); } else { renderWorkspace(); } }
var renderWorkspaceBase = renderWorkspace;
renderWorkspace = function () {
  var box = document.getElementById('wsBody');
  var tabs = document.getElementById('wsTabs');
  var panel = document.getElementById('wsPanel');
  if (!box) { return; }
  if (!SBX.on || !agentMode) {
    if (tabs) { tabs.hidden = true; }
    if (panel) { panel.classList.remove('wide'); }
    document.body.style.setProperty('--wsw', '340px');
    renderWorkspaceBase();
    return;
  }
  tabs.hidden = false;
  Array.prototype.forEach.call(tabs.querySelectorAll('button[data-tab]'), function (b) { b.classList.toggle('on', b.dataset.tab === SBX.tab); });
  panel.classList.toggle('wide', SBX.tab === 'preview' || (SBX.tab === 'files' && !!SBX.view));
  document.body.style.setProperty('--wsw', panel.classList.contains('wide') ? 'min(760px,52vw)' : '340px');
  if (SBX.tab === 'activity') { renderWorkspaceBase(); return; }
  if (!currentChat || (!currentChat.id && !isTempChat)) {
    box.innerHTML = '<div class="ws-empty"><div class="wi">' + (I.terminal || '') + '</div>Every agent chat gets its own Linux computer.<br>Files the agent creates and apps it runs will show up here.</div>';
    return;
  }
  if (SBX.tab === 'files') { renderWsFiles(box); } else { renderWsPreview(box); }
};

function wsToolbar(html) { return '<div class="ws-tb">' + html + '</div>'; }
function renderWsFiles(box) {
  if (SBX.view) { renderWsViewer(box); return; }
  var fresh = Date.now() - SBX.lastList < 4000 && SBX.listFor === sbxChatQuery();
  if (!fresh) {
    if (!SBX.loading) {
      SBX.loading = true;
      if (!SBX.files.length || SBX.listFor !== sbxChatQuery()) { box.innerHTML = wsToolbar('') + '<div class="ws-empty"><span class="agx-spin"></span><br>Loading files…</div>'; }
      var q = sbxChatQuery();
      api('sbx_files&' + q + '&path=.').then(function (j) {
        SBX.loading = false; SBX.lastList = Date.now(); SBX.listFor = q;
        SBX.files = j.ok ? (j.entries || []) : [];
        SBX.err = j.ok ? '' : (j.error || 'Could not load files');
        if (wsIsOpen() && SBX.tab === 'files' && !SBX.view) { drawWsFiles(box); }
      });
    }
    if (SBX.files.length && SBX.listFor === sbxChatQuery()) { drawWsFiles(box); }
    return;
  }
  drawWsFiles(box);
}
function drawWsFiles(box) {
  var files = SBX.files.filter(function (e) { return !/^\.devil(\/|$)/.test(e.path) || /^\.devil\/screens(\/|$)/.test(e.path); });
  var tb = wsToolbar('<button type="button" class="ws-btn" data-act="up" title="Upload files">' + (I.upload || '') + '<span>Upload</span></button>' +
    '<button type="button" class="ws-btn" data-act="zip" title="Download everything as .zip">' + (I.download || '') + '<span>Zip</span></button>' +
    '<span class="sp"></span><button type="button" class="ws-btn ic" data-act="ref" title="Refresh">' + (I.retry || '') + '</button>');
  if (SBX.err) { box.innerHTML = tb + '<div class="ws-empty"><div class="wi">' + (I.warning || '') + '</div>' + esc(SBX.err) + '</div>'; wsBindTb(box); return; }
  if (!files.length) { box.innerHTML = tb + '<div class="ws-empty"><div class="wi">' + (I.folderS || '') + '</div>No files yet.<br>Ask the agent to build something, or upload files.</div>'; wsBindTb(box); return; }
  var html = '<div class="ws-tree">';
  files.forEach(function (e) {
    var parts = e.path.split('/'), depth = parts.length - 1, name = parts[parts.length - 1];
    var hidden = false;
    for (var k = 1; k < parts.length; k++) { if (SBX.open[parts.slice(0, k).join('/')] === false) { hidden = true; break; } }
    if (hidden) { return; }
    var isDir = e.type === 'dir';
    var collapsed = isDir && SBX.open[e.path] === false;
    html += '<button type="button" class="ws-f' + (isDir ? ' dir' : '') + (collapsed ? ' closed' : '') + '" data-path="' + esc(e.path) + '" data-dir="' + (isDir ? 1 : 0) + '" style="padding-left:' + (8 + depth * 14) + 'px" title="' + esc(e.path) + '">' +
      '<span class="fi">' + (isDir ? '<span class="fchev">' + (I.chevR || '') + '</span>' + (I.folderS || '') : (I[fileIcon(name)] || I.fileS)) + '</span>' +
      '<span class="fn">' + esc(name) + (e.skipped ? ' <small>(not expanded)</small>' : '') + '</span>' + (!isDir ? '<small class="fs">' + fmtBytes(e.size || 0) + '</small>' : '') + '</button>';
  });
  html += '</div>';
  box.innerHTML = tb + html;
  wsBindTb(box);
  Array.prototype.forEach.call(box.querySelectorAll('.ws-f'), function (b) {
    b.addEventListener('click', function () {
      var p = b.dataset.path;
      if (b.dataset.dir === '1') { SBX.open[p] = SBX.open[p] === false ? true : false; drawWsFiles(box); return; }
      SBX.view = { path: p };
      renderWorkspace();
    });
  });
}
function fileIcon(name) {
  if (/\.(png|jpe?g|gif|webp|svg|ico|bmp)$/i.test(name)) { return 'imageS'; }
  if (/\.(js|ts|jsx|tsx|py|php|sh|css|html?|json|go|rs|java|c|cpp|rb|vue|svelte|sql|ya?ml|toml)$/i.test(name)) { return 'codeS'; }
  return 'fileS';
}
function wsBindTb(box) {
  Array.prototype.forEach.call(box.querySelectorAll('.ws-tb [data-act]'), function (b) {
    b.addEventListener('click', function () {
      var a = b.dataset.act;
      if (a === 'ref') { SBX.lastList = 0; renderWorkspace(); }
      else if (a === 'zip') { wsDownload('api.php?action=sbx_zip&' + sbxChatQuery()); }
      else if (a === 'up') { wsPickUpload(); }
      else if (a === 'back') { SBX.view = null; renderWorkspace(); }
      else if (a === 'dl' && SBX.view) { wsDownload(sbxFileUrl(SBX.view.path, true)); }
      else if (a === 'pvref') { var f = box.querySelector('iframe'); if (f) { f.src = f.src; } }
      else if (a === 'pvopen') { var u = (SBX.ports.filter(function (p) { return p.port === SBX.port; })[0] || {}).url; if (u) { window.open(u, '_blank', 'noopener'); } }
    });
  });
}
function wsDownload(url) { var a = document.createElement('a'); a.href = url; a.rel = 'noopener'; a.download = ''; document.body.appendChild(a); a.click(); a.remove(); }
function wsPickUpload() {
  var fi = document.createElement('input');
  fi.type = 'file'; fi.multiple = true;
  fi.addEventListener('change', function () {
    var list = Array.prototype.slice.call(fi.files || []);
    if (!list.length) { return; }
    var left = list.length;
    toast('Uploading ' + left + ' file' + (left > 1 ? 's' : '') + '…', 'loader');
    list.forEach(function (f) {
      if (f.size > 25 * 1024 * 1024) { toast(f.name + ' is larger than 25 MB', 'warning'); if (--left === 0) { SBX.lastList = 0; renderWorkspace(); } return; }
      var fr = new FileReader();
      fr.onload = function () {
        api('sbx_upload', { id: isTempChat ? '' : rootChatId(), temp: isTempChat ? 1 : 0, name: f.name, data: fr.result, dir: 'uploads' }).then(function (j) {
          if (!j.ok) { toast(j.error || ('Upload failed: ' + f.name), 'warning'); }
          if (--left === 0) { toast('Uploaded to uploads/'); SBX.lastList = 0; SBX.open.uploads = true; renderWorkspace(); }
        });
      };
      fr.readAsDataURL(f);
    });
  });
  fi.click();
}
function renderWsViewer(box) {
  var p = SBX.view.path, name = p.split('/').pop();
  var tb = wsToolbar('<button type="button" class="ws-btn ic" data-act="back" title="Back to files">' + (I.arrowL || '') + '</button><span class="ws-path" title="' + esc(p) + '">' + esc(p) + '</span><span class="sp"></span>' +
    '<button type="button" class="ws-btn" data-act="dl" title="Download">' + (I.download || '') + '<span>Download</span></button>');
  if (/\.(png|jpe?g|gif|webp)$/i.test(name)) {
    box.innerHTML = tb + '<div class="ws-view img"><img alt="" src="' + esc(sbxFileUrl(p)) + '"></div>';
    wsBindTb(box); return;
  }
  if (/\.(zip|gz|tgz|tar|7z|rar|pdf|mp4|mp3|wav|woff2?|ttf|exe|bin|so|o|pyc|sqlite|db|xlsx?|docx?|pptx?)$/i.test(name)) {
    box.innerHTML = tb + '<div class="ws-empty"><div class="wi">' + (I.fileS || '') + '</div>' + esc(name) + '<br>Preview isn\'t available for this file type.<br><br><button type="button" class="ws-btn" data-act="dl">' + (I.download || '') + '<span>Download</span></button></div>';
    wsBindTb(box); return;
  }
  box.innerHTML = tb + '<div class="ws-empty"><span class="agx-spin"></span></div>';
  wsBindTb(box);
  fetch(sbxFileUrl(p), { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); }).then(function (t) {
    if (!SBX.view || SBX.view.path !== p) { return; }
    var big = t.length > 300000;
    box.innerHTML = tb + '<pre class="ws-view code">' + esc(big ? t.slice(0, 300000) + '\n… (truncated — download to see everything)' : t) + '</pre>';
    wsBindTb(box);
  }).catch(function () { box.innerHTML = tb + '<div class="ws-empty">Could not open this file.</div>'; wsBindTb(box); });
}
function renderWsPreview(box) {
  box.innerHTML = '<div class="ws-empty"><span class="agx-spin"></span><br>Looking for running apps…</div>';
  api('sbx_info&' + sbxChatQuery()).then(function (j) {
    if (!wsIsOpen() || SBX.tab !== 'preview') { return; }
    if (!j.ok || !j.online) { box.innerHTML = '<div class="ws-empty"><div class="wi">' + (I.warning || '') + '</div>' + esc(j.error || 'Sandbox is offline right now.') + '</div>'; return; }
    SBX.ports = (j.ports || []).filter(function (p) { return !p.localhost_only; });
    if (!SBX.ports.length) {
      box.innerHTML = wsToolbar('<span class="ws-path">No app running</span><span class="sp"></span><button type="button" class="ws-btn ic" data-act="ref2" title="Refresh">' + (I.retry || '') + '</button>') +
        '<div class="ws-empty"><div class="wi">' + (I.monitor || '') + '</div>No app is running yet.<br>Ask the agent to build and start one — it will appear here live.</div>';
      box.querySelector('[data-act="ref2"]').addEventListener('click', function () { renderWorkspace(); });
      return;
    }
    if (!SBX.ports.some(function (p) { return p.port === SBX.port; })) { SBX.port = SBX.ports[0].port; }
    var cur = SBX.ports.filter(function (p) { return p.port === SBX.port; })[0];
    var sel = '<select class="ws-sel" title="Port">' + SBX.ports.map(function (p) { return '<option value="' + p.port + '"' + (p.port === SBX.port ? ' selected' : '') + '>:' + p.port + '</option>'; }).join('') + '</select>';
    box.innerHTML = wsToolbar(sel + '<span class="ws-path ws-url" title="' + esc(cur.url) + '">' + esc(cur.url.replace(/^https:\/\//, '')) + '</span><span class="sp"></span>' +
      '<button type="button" class="ws-btn ic" data-act="pvref" title="Reload">' + (I.retry || '') + '</button><button type="button" class="ws-btn ic" data-act="pvopen" title="Open in new tab">' + (I.external || '') + '</button>') +
      '<iframe class="ws-frame" src="' + esc(cur.url) + '" sandbox="allow-scripts allow-forms allow-same-origin allow-popups allow-modals allow-downloads" referrerpolicy="no-referrer" allow="clipboard-write"></iframe>';
    wsBindTb(box);
    box.querySelector('.ws-sel').addEventListener('change', function (e) { SBX.port = Number(e.target.value); renderWorkspace(); });
  });
}
(function initWsTabs() {
  var head = document.querySelector('#wsPanel .ws-head');
  if (!head || document.getElementById('wsTabs')) { return; }
  var t = document.createElement('div');
  t.id = 'wsTabs'; t.className = 'ws-tabs'; t.hidden = true;
  t.innerHTML = '<button type="button" data-tab="files">' + (I.folderS || '') + 'Files</button><button type="button" data-tab="preview">' + (I.monitor || '') + 'Preview</button><button type="button" data-tab="activity">' + (I.listS || '') + 'Activity</button>';
  head.parentNode.insertBefore(t, head.nextSibling);
  t.addEventListener('click', function (e) { var b = e.target.closest('button[data-tab]'); if (!b) { return; } SBX.tab = b.dataset.tab; if (SBX.tab !== 'files') { SBX.view = null; } renderWorkspace(); });
})();

api('bootstrap').then(function (j) {
  if (!j.ok) { return; }
  models = j.models || [];
  customModels = j.custom_models || [];
  modelById = {}; customById = {};
  models.forEach(function (m) { modelById[m.id] = m; });
  customModels.forEach(function (m) { customById[m.id] = m; });
  var saved = read('devil_model');
  var savedCustom = read('devil_custom_model');
  if (saved && modelById[saved]) { currentModel = saved; }
  else if (j.default && modelById[j.default]) { currentModel = j.default; }
  if (savedCustom && customById[savedCustom]) { currentCustom = savedCustom; }
  else if (customModels[0]) { currentCustom = customModels[0].id; }
  applyForcedModel();
  setModelBtn();
  renderModelMenu();
  renderCustomModelMenu('');
  agentEnabled = j.agent_enabled !== false;
  SBX.on = !!j.sandbox_enabled;
  if (wsIsOpen()) { renderWorkspace(); }
  BATTLE_POOL = j.battle_models || [];
  battleById = {};
  BATTLE_POOL.forEach(function (m) { battleById[m.id] = m; });
  if (!cmpFromChat) {
    var sa = read('devil_sbs_a'), sb2 = read('devil_sbs_b');
    if (sa && battleById[sa]) { cmpA = sa; }
    if (sb2 && battleById[sb2]) { cmpB = sb2; }
  }
  renderCmpPickers();
  syncModeUI();
  applySbsPrefill();
});
syncModeUI();
if (INITIAL_CHAT_ID) {
  openChat(INITIAL_CHAT_ID, INITIAL_VARIANT);
} else if (new URLSearchParams(window.location.search).get('temp') === '1') {
  startTempChat(true);
} else {
  updateChatActions();
}
loadChats();
resize();
if (window.innerWidth > 900) { inp.focus(); }
})();
