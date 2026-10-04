<?php
/* 😈 DEVIL AI — index.php (Chat UI) • v1.0.0.0 • Pure PHP */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$history = (isset($_SESSION['devil_history']) && is_array($_SESSION['devil_history'])) ? $_SESSION['devil_history'] : [];
$boot = json_encode($history, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if (!is_string($boot)) { $boot = '[]'; }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0a0507">
<title>😈 Devil AI — Sinfully Smart Assistant</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0a0507; --panel:#170c10; --panel2:#0d060a;
  --border:rgba(244,63,94,.22); --border-hi:rgba(244,63,94,.55);
  --red:#e11d48; --pink:#fb7185; --soft:#fda4af;
  --text:#f3e9ec; --dim:#a1707b; --dim2:#7c5b63;
}
html,body{height:100%}
body{
  font-family:'Segoe UI',system-ui,-apple-system,Roboto,'Noto Sans',sans-serif;
  background:
    radial-gradient(1100px 500px at 80% -10%, rgba(225,29,72,.16), transparent 60%),
    radial-gradient(900px 500px at -10% 110%, rgba(190,18,60,.12), transparent 55%),
    var(--bg);
  color:var(--text);
  height:100dvh; display:flex; flex-direction:column; overflow:hidden;
}
button{font:inherit;cursor:pointer}
a{color:var(--pink)}
img{-webkit-user-drag:none}

/* ── header ── */
header{
  display:flex;align-items:center;gap:10px;
  padding:12px 16px;
  background:rgba(12,5,8,.75);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
  border-bottom:1px solid var(--border);z-index:20;
}
.logo{width:40px;height:40px;flex-shrink:0;animation:pulse 3.2s ease-in-out infinite;filter:drop-shadow(0 0 8px rgba(244,63,94,.5))}
.titles{display:flex;flex-direction:column;min-width:0;flex:1}
h1{font-size:1.2rem;letter-spacing:.4px;background:linear-gradient(90deg,#fff,#fda4af);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.tag{font-size:.7rem;color:var(--dim);letter-spacing:.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.badge{font-size:.68rem;padding:4px 10px;border-radius:999px;border:1px solid;white-space:nowrap;letter-spacing:.3px}
.badge.demo{color:#fdba74;border-color:rgba(251,146,60,.4);background:rgba(124,45,18,.25)}
.badge.ai{color:#4ade80;border-color:rgba(74,222,128,.4);background:rgba(20,83,45,.25)}
.badge.warn{color:#fca5a5;border-color:rgba(248,113,113,.4);background:rgba(127,29,29,.25)}
.ghostbtn{background:rgba(244,63,94,.08);border:1px solid var(--border);color:var(--soft);border-radius:10px;padding:8px 12px;font-size:.85rem;transition:.2s}
.ghostbtn:hover{background:rgba(244,63,94,.18);border-color:var(--border-hi)}

/* ── chat area ── */
#chat{flex:1;overflow-y:auto;padding:18px 4vw 26px;scroll-behavior:smooth}
#chat::-webkit-scrollbar{width:8px}
#chat::-webkit-scrollbar-thumb{background:rgba(244,63,94,.25);border-radius:4px}
.wrap{max-width:860px;margin:0 auto}

#welcome{text-align:center;padding-top:7vh}
.biglogo{width:92px;height:92px;margin:0 auto 14px;animation:pulse 3.2s ease-in-out infinite;filter:drop-shadow(0 0 22px rgba(225,29,72,.45))}
#welcome h2{font-size:2.2rem;background:linear-gradient(90deg,#fff 10%,#f43f5e 60%,#fda4af);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.wtag{color:var(--dim);margin-top:6px;font-size:.95rem}
.chips{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:22px}
.chip{padding:10px 16px;border:1px solid rgba(244,63,94,.35);border-radius:999px;background:rgba(244,63,94,.06);color:var(--soft);font-size:.88rem;transition:.2s}
.chip:hover{background:rgba(244,63,94,.16);transform:translateY(-2px)}

/* ── messages ── */
.msg{display:flex;gap:10px;margin:14px 0;align-items:flex-start;animation:rise .25s ease}
.msg.user{flex-direction:row-reverse}
.msg.err{margin-left:44px}
.ava{width:34px;height:34px;border-radius:50%;background:var(--panel);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.col{display:flex;flex-direction:column;min-width:0;max-width:78%;align-items:flex-start}
.msg.user .col{align-items:flex-end;max-width:78%}
.bubble{padding:12px 16px;border-radius:16px;font-size:.95rem;line-height:1.6;overflow-wrap:break-word}
.msg.bot .bubble{background:var(--panel);border:1px solid rgba(244,63,94,.16);border-top-left-radius:4px}
.msg.user .bubble{background:linear-gradient(135deg,#e11d48,#9f1239);border-bottom-right-radius:4px;color:#fff;white-space:pre-wrap}
.msg.err .bubble{background:#2a0b10;border:1px solid rgba(248,113,113,.45);color:#fecaca;white-space:pre-wrap}
.meta{font-size:.68rem;color:var(--dim2);margin-bottom:4px}
.bubble p{margin:.3em 0}
.bubble h3,.bubble h4{margin:.5em 0 .2em;color:#fecdd3}
.bubble ul{margin:.4em 0 .4em 1.25em}
.bubble li{margin:.15em 0}
.bubble strong{color:#fff}
.bubble code{background:#24121a;color:#fda4af;padding:.15em .4em;border-radius:5px;font-family:ui-monospace,Consolas,monospace;font-size:.85em}
.bubble pre{background:#0a0508;border:1px solid rgba(244,63,94,.25);padding:12px;border-radius:10px;overflow:auto;margin:.5em 0;max-width:100%}
.bubble pre code{background:none;color:#fecdd3;padding:0}
.sp{height:.55em}
.dots span{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--pink);margin-right:3px;animation:blink 1.2s infinite}
.dots span:nth-child(2){animation-delay:.2s}
.dots span:nth-child(3){animation-delay:.4s}
.dim2{color:var(--dim2);font-size:.85rem}

/* ── footer / input ── */
footer{padding:10px 4vw 14px;background:rgba(10,5,7,.85);backdrop-filter:blur(10px);border-top:1px solid var(--border)}
.inputwrap{max-width:860px;margin:0 auto;display:flex;align-items:flex-end;gap:10px;background:var(--panel);border:1px solid var(--border);border-radius:18px;padding:10px 12px;transition:.2s;box-shadow:0 8px 30px rgba(225,29,72,.12)}
.inputwrap:focus-within{border-color:var(--border-hi);box-shadow:0 8px 34px rgba(225,29,72,.25)}
#inp{flex:1;background:none;border:none;outline:none;color:var(--text);font:inherit;resize:none;max-height:140px;line-height:1.5;padding:6px 4px}
#inp::placeholder{color:var(--dim2)}
#send{width:42px;height:42px;border-radius:50%;border:none;background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;font-size:1.05rem;transition:.2s;box-shadow:0 4px 15px rgba(244,63,94,.4);flex-shrink:0}
#send:hover{transform:scale(1.07)}
#send:disabled{opacity:.5;transform:none;cursor:default}
.foot{text-align:center;font-size:.68rem;color:var(--dim2);margin-top:8px}

/* ── modal ── */
.modal{position:fixed;inset:0;background:rgba(5,2,4,.72);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;z-index:50;padding:16px}
.hidden{display:none !important}
.sheet{background:#150a0f;border:1px solid var(--border);border-radius:20px;max-width:480px;width:100%;padding:20px 22px;max-height:90vh;overflow:auto}
.sheethead{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;font-weight:600}
.sheet label{display:block;font-size:.78rem;color:var(--soft);margin:14px 0 6px;font-weight:600}
.sheet select,.sheet input{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:10px 12px;font:inherit;outline:none}
.sheet select:focus,.sheet input:focus{border-color:var(--border-hi)}
.dim{color:var(--dim2);font-weight:400}
.helper{margin-top:10px;font-size:.78rem;color:#c08d97;background:rgba(244,63,94,.06);border:1px solid rgba(244,63,94,.18);padding:9px 12px;border-radius:10px;line-height:1.6}
.btnrow{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap}
.btn{border:none;border-radius:12px;padding:11px 18px;font-weight:600;transition:.2s}
.btn.primary{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 4px 15px rgba(244,63,94,.35)}
.btn.primary:hover{transform:translateY(-1px)}
.btn.ghost{background:none;border:1px solid var(--border);color:var(--soft)}
#sStatus,#lockStatus{margin-top:12px;font-size:.8rem;min-height:1.3em;white-space:pre-wrap;color:var(--dim)}
.note{margin-top:14px;font-size:.72rem;color:var(--dim2);line-height:1.5}

/* ── toast ── */
#toast{position:fixed;top:16px;left:50%;transform:translateX(-50%);background:#2a0b10;border:1px solid rgba(248,113,113,.5);color:#fecaca;padding:10px 18px;border-radius:12px;font-size:.85rem;z-index:99;box-shadow:0 10px 30px rgba(0,0,0,.5);animation:rise .25s ease;max-width:90vw;text-align:center}

@keyframes rise{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{filter:drop-shadow(0 0 6px rgba(244,63,94,.5))}50%{filter:drop-shadow(0 0 18px rgba(244,63,94,.9))}}
@keyframes blink{0%,80%,100%{opacity:.25}40%{opacity:1}}

@media (max-width:640px){
  header{padding:10px 12px}
  h1{font-size:1.02rem}
  .ghostbtn span{display:none}
  #welcome h2{font-size:1.6rem}
  .col,.msg.user .col{max-width:88%}
}
@media (prefers-reduced-motion:reduce){*{animation:none !important;transition:none !important}}
</style>
</head>
<body>

<header>
  <img class="logo" src="assets/logo.svg" alt="Devil AI logo">
  <div class="titles">
    <h1>Devil AI</h1>
    <div class="tag">🔥 Sinfully smart — surprisingly helpful</div>
  </div>
  <span id="badge" class="badge warn">⚡ Loading…</span>
  <button id="newBtn" class="ghostbtn" title="New chat">💬 <span>New</span></button>
  <button id="setBtn" class="ghostbtn" title="Settings">⚙️</button>
</header>

<main id="chat">
  <div class="wrap">
    <div id="welcome">
      <img class="biglogo" src="assets/logo.svg" alt="Devil AI">
      <h2>Devil AI</h2>
      <p class="wtag">I am Devil AI 😈 — ask me anything!</p>
      <div class="chips">
        <button class="chip">😈 Who are you?</button>
        <button class="chip">🔥 Tell me a devil joke</button>
        <button class="chip">🧮 What is 45*12+8?</button>
        <button class="chip">🌍 Tell me a fun fact</button>
      </div>
    </div>
    <div id="msgs"></div>
  </div>
</main>

<footer>
  <div class="inputwrap">
    <textarea id="inp" rows="1" maxlength="4000" placeholder="Ask Devil AI anything… 😈"></textarea>
    <button id="send" title="Send (Enter)">🔥</button>
  </div>
  <div class="foot">😈 Devil AI v1.0.0.0 • 100% PHP • <span id="mode2">…</span></div>
</footer>

<!-- ⚙️ Settings -->
<div id="overlay" class="modal hidden">
  <div class="sheet">
    <div class="sheethead"><span>⚙️ Devil AI Settings</span><button id="sClose" class="ghostbtn">✕</button></div>

    <!-- 🔒 Lock screen (public visitors see only this) -->
    <div id="lockView" class="hidden">
      <label for="lockPw">Admin Password 🔒</label>
      <input id="lockPw" type="password" placeholder="Enter the admin password" autocomplete="off">
      <div class="btnrow"><button id="lockBtn" class="btn primary">🔓 Unlock</button></div>
      <div id="lockStatus"></div>
      <p class="note">These settings are for the owner only. Visitors can just chat — no password needed 😈</p>
    </div>

    <!-- ⚙️ Main settings (visible to the owner only) -->
    <div id="mainView">
      <label for="sProvider">AI Brain (Provider)</label>
      <select id="sProvider"></select>
      <div id="sHelper" class="helper hidden"></div>

      <div id="keyRow">
        <label for="sKey">API Key <span id="keyState" class="dim"></span></label>
        <input id="sKey" type="password" placeholder="Paste your key here…" autocomplete="off">
      </div>

      <label for="sModel">Model <span class="dim">(empty = default)</span></label>
      <input id="sModel" type="text" placeholder="model-name (optional)">

      <div id="pwRow" class="hidden">
        <label for="sPw">Admin Password 🔒</label>
        <input id="sPw" type="password" placeholder="Password set in config.php" autocomplete="off">
      </div>

      <div class="btnrow">
        <button id="sSave" class="btn primary">💾 Save</button>
        <button id="sTest" class="btn ghost">🔌 Test Connection</button>
      </div>
      <div id="sStatus"></div>
      <div class="note">🔒 The key is stored only on your server (<code>data/config.json</code>) — it is never sent to the browser.</div>
    </div>
  </div>
</div>

<div id="toast" class="hidden"></div>

<script>
const BOOT_HISTORY = <?= $boot ?>;
(function () {
'use strict';

const $ = (s) => document.querySelector(s);
const msgs = $('#msgs'), inp = $('#inp'), sendBtn = $('#send'), chat = $('#chat');
const welcome = $('#welcome'), badge = $('#badge'), mode2 = $('#mode2'), toast = $('#toast');
const overlay = $('#overlay');

const AVA = '<img src="assets/logo.svg" width="22" height="22" alt="" draggable="false">';

let hist = Array.isArray(BOOT_HISTORY) ? BOOT_HISTORY.filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string') : [];
let busy = false;
let PROVIDERS = {};
let adminUnlocked = false;
let unlockedPw = '';

/* ── mini markdown (escape first, then format — XSS safe) ── */
function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function inline(s) {
  return s
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
}
function md(src) {
  const lines = esc(src).split('\n');
  let out = [], inCode = false, codeBuf = [], inList = false;
  const closeList = () => { if (inList) { out.push('</ul>'); inList = false; } };
  for (const line of lines) {
    if (line.trim().startsWith('```')) {
      if (inCode) { out.push('<pre><code>' + codeBuf.join('\n') + '</code></pre>'); codeBuf = []; inCode = false; }
      else { closeList(); inCode = true; }
      continue;
    }
    if (inCode) { codeBuf.push(line); continue; }
    const t = line.trim();
    if (/^(?:\d+\.|[-*])\s+/.test(t)) {
      if (!inList) { out.push('<ul>'); inList = true; }
      out.push('<li>' + inline(t.replace(/^(?:\d+\.|[-*])\s+/, '')) + '</li>');
      continue;
    }
    closeList();
    if (/^###\s+/.test(t)) { out.push('<h4>' + inline(t.replace(/^###\s+/, '')) + '</h4>'); }
    else if (/^#{1,2}\s+/.test(t)) { out.push('<h3>' + inline(t.replace(/^#{1,2}\s+/, '')) + '</h3>'); }
    else if (t === '') { out.push('<div class="sp"></div>'); }
    else { out.push('<p>' + inline(t) + '</p>'); }
  }
  if (inList) { out.push('</ul>'); }
  if (inCode && codeBuf.length) { out.push('<pre><code>' + codeBuf.join('\n') + '</code></pre>'); }
  return out.join('');
}

/* ── UI bits ── */
function nowTime() { return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
function scrollBottom() { chat.scrollTop = chat.scrollHeight; }

function addMsg(kind, text) {
  const row = document.createElement('div');
  row.className = 'msg ' + kind;
  if (kind === 'bot') {
    const a = document.createElement('div'); a.className = 'ava'; a.innerHTML = AVA;
    const col = document.createElement('div'); col.className = 'col';
    const meta = document.createElement('div'); meta.className = 'meta'; meta.textContent = '😈 Devil AI · ' + nowTime();
    const b = document.createElement('div'); b.className = 'bubble'; b.innerHTML = md(text);
    col.appendChild(meta); col.appendChild(b);
    row.appendChild(a); row.appendChild(col);
  } else if (kind === 'user') {
    const col = document.createElement('div'); col.className = 'col';
    const b = document.createElement('div'); b.className = 'bubble'; b.textContent = text;
    col.appendChild(b); row.appendChild(col);
  } else {
    const b = document.createElement('div'); b.className = 'bubble'; b.textContent = '⚠️ ' + text;
    row.appendChild(b);
  }
  msgs.appendChild(row);
  welcome.classList.add('hidden');
  scrollBottom();
  return row;
}

function showTyping() {
  const row = document.createElement('div');
  row.className = 'msg bot';
  row.innerHTML = '<div class="ava">' + AVA + '</div><div class="col"><div class="meta">😈 Devil AI</div>'
    + '<div class="bubble"><span class="dots"><span></span><span></span><span></span></span> <span class="dim2">thinking…</span></div></div>';
  msgs.appendChild(row);
  scrollBottom();
  return row;
}

function showToast(msg) {
  toast.textContent = msg;
  toast.classList.remove('hidden');
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.add('hidden'), 3200);
}

/* ── api ── */
async function api(action, body, method) {
  method = method || 'POST';
  const url = 'api.php' + (action ? '?action=' + encodeURIComponent(action) : '');
  const opt = { method: method, headers: { 'Content-Type': 'application/json' } };
  if (method === 'POST') { opt.body = JSON.stringify(body || {}); }
  try {
    const r = await fetch(url, opt);
    let j = null;
    try { j = await r.json(); } catch (e) { j = { ok: false, error: 'Server returned an invalid response (HTTP ' + r.status + ')' }; }
    return j;
  } catch (e) {
    return { ok: false, error: 'Network error: ' + e.message };
  }
}

/* Badge — never shows the provider name to the public */
function setMode(mode) {
  let cls = 'badge warn', txt = '⚠️ Setup Needed';
  if (mode === 'demo') { cls = 'badge demo'; txt = '😈 Demo Mode'; }
  else if (mode === 'ai') { cls = 'badge ai'; txt = '🔥 AI Mode'; }
  badge.className = cls; badge.textContent = txt;
  mode2.textContent = txt;
}

/* ── send ── */
async function send(text) {
  if (busy) { return; }
  text = (text || '').trim();
  if (!text) { return; }
  busy = true; sendBtn.disabled = true;
  inp.value = ''; resize();
  addMsg('user', text);
  const t = showTyping();
  const j = await api(null, { message: text, history: hist.slice(-16) });
  t.remove();
  if (j.ok) {
    hist.push({ role: 'user', content: text });
    hist.push({ role: 'assistant', content: j.reply });
    addMsg('bot', j.reply);
    if (j.mode) { setMode(j.mode); }
  } else {
    addMsg('err', (j.error || 'Unknown error') + (j.hint ? '\n💡 ' + j.hint : ''));
  }
  busy = false; sendBtn.disabled = false;
  inp.focus();
}

/* ── settings ── */
function updateProviderUI() {
  const pid = $('#sProvider').value;
  const p = PROVIDERS[pid] || {};
  const h = $('#sHelper');
  const keyRow = $('#keyRow');
  if (!p.key_required && pid !== 'demo') {
    h.innerHTML = '✅ <b>No API key needed!</b> Free AI — just hit Save.';
    h.classList.remove('hidden');
    keyRow.style.display = 'none';
  } else if (pid === 'demo') {
    h.innerHTML = '😈 Offline demo mode — basic replies, jokes, calculator. No key, no internet needed.';
    h.classList.remove('hidden');
    keyRow.style.display = 'none';
  } else if (p.key_url) {
    h.innerHTML = '🔑 Get a FREE key here: <a href="' + p.key_url + '" target="_blank" rel="noopener">' + p.key_url.replace('https://', '') + ' ↗</a> — best quality + full conversation memory.';
    h.classList.remove('hidden');
    keyRow.style.display = '';
  } else { h.classList.add('hidden'); keyRow.style.display = ''; }
  $('#sModel').placeholder = p.default_model || 'model-name';
}

function fillSettings(j) {
  PROVIDERS = {};
  const sel = $('#sProvider');
  sel.innerHTML = '';
  (j.providers || []).forEach((p) => {
    PROVIDERS[p.id] = p;
    const o = document.createElement('option');
    o.value = p.id; o.textContent = p.label;
    sel.appendChild(o);
  });
  sel.value = j.provider || (j.providers && j.providers[0] ? j.providers[0].id : 'demo');
  $('#sKey').value = '';
  $('#keyState').textContent = j.has_key ? ('✅ Saved: ' + (j.key_mask || '****')) : '';
  $('#sKey').placeholder = j.has_key ? 'New key — or leave empty' : 'Paste your key here…';
  $('#sModel').value = j.model || '';
  $('#pwRow').classList.toggle('hidden', !j.admin);
  $('#sPw').value = '';
  $('#sStatus').textContent = '';
  updateProviderUI();
  setMode(j.mode);
}

/* j must be a FULL settings payload (open mode GET, or POST auth response) */
function showMainSettings(j) {
  $('#lockView').classList.add('hidden');
  $('#mainView').classList.remove('hidden');
  fillSettings(j);
  overlay.classList.remove('hidden');
}

async function openSettings() {
  const j = await api('settings', null, 'GET');
  if (!j.ok) { showToast('Settings failed to load: ' + (j.error || '')); return; }
  /* Password-locked: the public GET returns only {ok, version, mode, admin} */
  if (j.admin && !adminUnlocked) {
    $('#mainView').classList.add('hidden');
    $('#lockView').classList.remove('hidden');
    $('#lockPw').value = '';
    $('#lockStatus').textContent = '';
    overlay.classList.remove('hidden');
    setTimeout(() => { $('#lockPw').focus(); }, 60);
    return;
  }
  showMainSettings(j);
}

async function unlockSettings() {
  const pw = $('#lockPw').value;
  if (!pw) { $('#lockStatus').textContent = 'Enter the password first.'; return; }
  $('#lockStatus').textContent = 'Checking…';
  const r = await api('auth', { admin_password: pw });
  if (r.ok && r.providers) {
    adminUnlocked = true;
    unlockedPw = pw;
    showMainSettings(r);
    $('#sPw').value = pw;
    showToast('🔓 Unlocked!');
  } else if (r.ok) {
    /* no password was set after all — settings GET is already full */
    adminUnlocked = true;
    const j = await api('settings', null, 'GET');
    if (j.ok) { showMainSettings(j); showToast('🔓 Unlocked!'); }
  } else {
    $('#lockStatus').textContent = '❌ Wrong password!';
  }
}

function closeSettings() { overlay.classList.add('hidden'); }

function settingsPayload() {
  return {
    provider: $('#sProvider').value,
    api_key: $('#sKey').value.trim(),
    model: $('#sModel').value.trim(),
    admin_password: $('#sPw').value
  };
}

async function saveSettings() {
  $('#sStatus').textContent = 'Saving…';
  const j = await api('settings', settingsPayload());
  if (j.ok) {
    $('#sStatus').textContent = '✅ Saved!';
    setMode(j.mode);
    showToast('✅ Settings saved 😈');
    /* refresh the full form (locked mode: full payload only via auth) */
    if (adminUnlocked && unlockedPw) {
      const r2 = await api('auth', { admin_password: unlockedPw });
      if (r2.ok && r2.providers) { fillSettings(r2); $('#sPw').value = unlockedPw; }
    } else {
      const j2 = await api('settings', null, 'GET');
      if (j2.ok && j2.providers) { fillSettings(j2); }
    }
  } else {
    $('#sStatus').textContent = '❌ ' + (j.error || 'Save failed');
  }
}

async function testSettings() {
  $('#sStatus').textContent = '🔌 Testing…';
  const j = await api('test', settingsPayload());
  if (j.ok) { $('#sStatus').textContent = '✅ AI is alive! Reply: ' + (j.reply || '').slice(0, 120); }
  else { $('#sStatus').textContent = '❌ ' + (j.error || 'Test failed') + (j.hint ? '\n💡 ' + j.hint : ''); }
}

/* ── input ── */
function resize() { inp.style.height = 'auto'; inp.style.height = Math.min(inp.scrollHeight, 140) + 'px'; }

sendBtn.addEventListener('click', () => send(inp.value));
inp.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(inp.value); }
});
inp.addEventListener('input', resize);

document.querySelectorAll('.chip').forEach((c) => c.addEventListener('click', () => send(c.textContent)));

$('#newBtn').addEventListener('click', async () => {
  if (busy) { return; }
  await api('reset', {});
  hist = [];
  msgs.innerHTML = '';
  welcome.classList.remove('hidden');
  showToast('🔥 New chat started!');
  inp.focus();
});

$('#setBtn').addEventListener('click', openSettings);
badge.addEventListener('click', openSettings);
$('#sClose').addEventListener('click', closeSettings);
$('#lockBtn').addEventListener('click', unlockSettings);
$('#lockPw').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); unlockSettings(); } });
overlay.addEventListener('click', (e) => { if (e.target === overlay) { closeSettings(); } });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeSettings(); } });
$('#sProvider').addEventListener('change', updateProviderUI);
$('#sSave').addEventListener('click', saveSettings);
$('#sTest').addEventListener('click', testSettings);

/* ── boot ── */
if (hist.length) {
  hist.forEach((m) => addMsg(m.role === 'user' ? 'user' : 'bot', m.content));
}
(async () => {
  const j = await api('settings', null, 'GET');
  if (j.ok) {
    (j.providers || []).forEach((p) => { PROVIDERS[p.id] = p; });
    setMode(j.mode);
  } else {
    setMode('demo');
  }
})();
})();
</script>
</body>
</html>
