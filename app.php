<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Chat App (app.php) • v1.0.0.0
 *  Claude-style layout: sidebar + isolated chats +
 *  model picker + admin settings. Login required.
 * ═══════════════════════════════════════════════════════
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/inc/icons.php';

/* ── current user (must be signed in) ── */
$me = null;
if (isset($_SESSION['devil_uid'])) {
    $uf = __DIR__ . '/data/users.json';
    if (is_readable($uf)) {
        $users = json_decode((string)file_get_contents($uf), true);
        if (is_array($users) && isset($users[$_SESSION['devil_uid']])) {
            $u = $users[$_SESSION['devil_uid']];
            $me = ['id' => $_SESSION['devil_uid'], 'name' => (string)($u['name'] ?? 'Devil'), 'email' => (string)($u['email'] ?? '')];
        }
    }
}
if (!$me) { header('Location: login.php'); exit; }

$JS_ICONS = [
    'send' => icon('send', 17), 'copy' => icon('copy', 15), 'retry' => icon('retry', 15),
    'trash' => icon('trash', 15), 'pencil' => icon('pencil', 14), 'check' => icon('check', 15),
    'x' => icon('x', 16), 'chev' => icon('chevron-down', 14), 'zap' => icon('zap', 15),
    'sparkles' => icon('sparkles', 15), 'crown' => icon('crown', 15), 'ghost' => icon('ghost', 15),
    'user' => icon('user', 16), 'logout' => icon('logout', 16), 'settings' => icon('settings', 16),
    'panel' => icon('panel-left', 18), 'newchat' => icon('square-pen', 17), 'search' => icon('search', 15),
    'loader' => icon('loader', 16), 'warning' => icon('warning', 16), 'lock' => icon('lock', 16),
    'message' => icon('message', 16), 'menu' => icon('menu', 18), 'flame' => icon('flame', 15),
    'lightbulb' => icon('lightbulb', 17), 'shield' => icon('shield', 16),
    'sun' => icon('sun', 17), 'moon' => icon('moon', 17), 'play' => icon('play', 13),
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0709">
<meta name="robots" content="noindex">
<script>/* theme boot — runs before paint to avoid a flash of the wrong theme */
(function(){var t=null;try{t=localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();</script>
<title>Devil AI — Chat</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0c0709; --bg2:#100a0d; --sb:#120b0f; --panel:#171014; --panel2:#1d1216; --panel3:#241721;
  --border:rgba(244,63,94,.16); --border-hi:rgba(244,63,94,.45);
  --red:#e11d48; --red2:#f43f5e; --pink:#fb7185; --soft:#fda4af;
  --text:#efe6ea; --dim:#a8929b; --dim2:#7c5b63;
  --serif:Georgia,'Times New Roman',serif;
  --sans:'Segoe UI',system-ui,-apple-system,Roboto,'Noto Sans',sans-serif;
}
html,body{height:100%}
body{font-family:var(--sans);background:var(--bg);color:var(--text);height:100dvh;overflow:hidden}
button{font:inherit;cursor:pointer;background:none;border:none;color:inherit}
a{color:inherit;text-decoration:none}
img{-webkit-user-drag:none}
::-webkit-scrollbar{width:9px;height:9px}
::-webkit-scrollbar-thumb{background:rgba(244,63,94,.22);border-radius:5px}
::-webkit-scrollbar-thumb:hover{background:rgba(244,63,94,.4)}
::-webkit-scrollbar-track{background:transparent}

#app{display:flex;height:100dvh}

/* ═══════════ SIDEBAR ═══════════ */
#sidebar{width:272px;flex-shrink:0;background:var(--sb);border-right:1px solid var(--border);display:flex;flex-direction:column;transition:margin .22s ease;z-index:40}
#sidebar.closed{margin-left:-272px}
.sb-top{display:flex;align-items:center;gap:10px;padding:14px 14px 10px}
.sb-top .brand{display:flex;align-items:center;gap:9px;font-weight:700;font-size:1rem;flex:1;min-width:0}
.sb-top .brand img{width:26px;height:26px;filter:drop-shadow(0 0 7px rgba(244,63,94,.45))}
.iconbtn{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--dim);transition:.15s;flex-shrink:0}
.iconbtn:hover{background:rgba(244,63,94,.12);color:var(--soft)}
.newchat{display:flex;align-items:center;gap:10px;margin:6px 12px 10px;padding:10px 13px;border-radius:12px;background:rgba(244,63,94,.09);border:1px solid var(--border);color:var(--text);font-weight:600;font-size:.88rem;transition:.15s}
.newchat:hover{background:rgba(244,63,94,.18);border-color:var(--border-hi)}
.sb-search{display:flex;align-items:center;gap:8px;margin:0 12px 10px;background:rgba(0,0,0,.25);border:1px solid var(--border);border-radius:11px;padding:8px 11px;color:var(--dim2)}
.sb-search input{flex:1;background:none;border:none;outline:none;color:var(--text);font:inherit;font-size:.82rem;min-width:0}
.sb-search input::placeholder{color:var(--dim2)}
#chatList{flex:1;overflow-y:auto;padding:2px 8px 8px}
.grp{font-size:.66rem;letter-spacing:1.2px;text-transform:uppercase;color:var(--dim2);padding:14px 8px 6px;font-weight:700}
.chatitem{display:flex;align-items:center;gap:6px;padding:8px 8px 8px 10px;border-radius:10px;cursor:pointer;transition:.12s;position:relative}
.chatitem:hover{background:rgba(244,63,94,.09)}
.chatitem.on{background:rgba(244,63,94,.15)}
.chatitem .t{flex:1;font-size:.84rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--dim)}
.chatitem.on .t,.chatitem:hover .t{color:var(--text)}
.chatitem .act{display:none;gap:2px}
.chatitem:hover .act{display:flex}
.chatitem .act button{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--dim2)}
.chatitem .act button:hover{background:rgba(244,63,94,.18);color:var(--soft)}
.list-empty{color:var(--dim2);font-size:.8rem;padding:16px 10px;text-align:center;line-height:1.6}

/* user menu */
.sb-bottom{border-top:1px solid var(--border);padding:10px 12px;position:relative}
.userbtn{display:flex;align-items:center;gap:10px;width:100%;padding:9px 10px;border-radius:11px;transition:.15s;text-align:left}
.userbtn:hover{background:rgba(244,63,94,.1)}
.userbtn .av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#f43f5e,#7f1d1d);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;color:#fff;flex-shrink:0}
.userbtn .nm{flex:1;min-width:0}
.userbtn .nm b{display:block;font-size:.84rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.userbtn .nm span{display:block;font-size:.7rem;color:var(--dim2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.userbtn .chev{color:var(--dim2)}
#userMenu{position:absolute;bottom:calc(100% + 6px);left:12px;right:12px;background:var(--panel2);border:1px solid var(--border-hi);border-radius:14px;box-shadow:0 18px 50px rgba(0,0,0,.6);overflow:hidden;display:none;z-index:60}
#userMenu.open{display:block}
#userMenu .mi{display:flex;align-items:center;gap:10px;width:100%;padding:11px 14px;font-size:.84rem;color:var(--dim);transition:.12s}
#userMenu .mi:hover{background:rgba(244,63,94,.12);color:var(--text)}
#userMenu .mi.danger:hover{background:rgba(190,18,60,.2);color:#fca5a5}
#userMenu hr{border:none;border-top:1px solid var(--border)}

/* ═══════════ MAIN ═══════════ */
main{flex:1;display:flex;flex-direction:column;min-width:0;position:relative;background:radial-gradient(1000px 500px at 70% -10%,rgba(225,29,72,.07),transparent 55%),var(--bg)}
.m-top{display:none;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border);background:rgba(12,7,9,.85);backdrop-filter:blur(10px)}
.m-top .brand{display:flex;align-items:center;gap:8px;font-weight:700;flex:1}
.m-top .brand img{width:24px;height:24px}

#scroller{flex:1;overflow-y:auto;scroll-behavior:smooth}
#thread{max-width:760px;margin:0 auto;padding:28px 20px 30px}

/* welcome */
#welcome{text-align:center;padding:9vh 10px 20px}
#welcome img.big{width:72px;height:72px;filter:drop-shadow(0 0 24px rgba(244,63,94,.5));margin-bottom:18px}
#welcome h2{font-family:var(--serif);font-weight:500;font-size:clamp(1.7rem,4vw,2.4rem)}
#welcome .sub{color:var(--dim);font-size:.9rem;margin-top:8px}
.cards{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:34px}
.card{display:flex;gap:12px;align-items:flex-start;text-align:left;background:var(--panel);border:1px solid var(--border);border-radius:15px;padding:15px;transition:.18s;cursor:pointer}
.card:hover{border-color:var(--border-hi);transform:translateY(-2px);box-shadow:0 10px 30px rgba(225,29,72,.1)}
.card .ic{width:34px;height:34px;border-radius:10px;background:rgba(244,63,94,.1);display:flex;align-items:center;justify-content:center;color:var(--pink);flex-shrink:0}
.card b{display:block;font-size:.86rem}
.card span{display:block;font-size:.75rem;color:var(--dim2);margin-top:2px;line-height:1.5}

/* messages */
.msg-user{display:flex;justify-content:flex-end;margin:26px 0}
.msg-user .bub{max-width:78%;background:var(--panel2);border:1px solid var(--border);border-radius:18px;border-bottom-right-radius:6px;padding:12px 16px;font-size:.92rem;line-height:1.6;white-space:pre-wrap;overflow-wrap:break-word}
.msg-ai{display:flex;gap:12px;margin:28px 0}
.msg-ai .ava{width:30px;height:30px;border-radius:50%;background:var(--panel);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.msg-ai .ava img{width:22px;height:22px}
.msg-ai .body{flex:1;min-width:0}
.msg-ai .who{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.msg-ai .who b{font-size:.86rem}
.msg-ai .who .mtag{font-size:.66rem;color:var(--soft);border:1px solid var(--border);border-radius:999px;padding:2px 8px;letter-spacing:.3px;white-space:nowrap}
.msg-ai .who .mtag svg{vertical-align:-2px;margin-right:3px}
.msg-ai .content{font-size:.93rem;line-height:1.7;color:var(--text)}
.content p{margin:.35em 0}
.content h3,.content h4{font-family:var(--serif);font-weight:600;margin:.7em 0 .25em;color:#fff}
.content ul,.content ol{margin:.45em 0 .45em 1.3em}
.content li{margin:.2em 0}
.content strong{color:#fff}
.content em{color:var(--soft)}
.content code{background:var(--panel3);color:var(--soft);padding:.14em .45em;border-radius:6px;font-family:ui-monospace,Consolas,monospace;font-size:.84em}
.content pre{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:14px;overflow:auto;margin:.6em 0;max-width:100%}
.content pre code{background:none;padding:0;color:#f3d0d7}
.content .sp{height:.5em}
.acts{display:flex;gap:4px;margin-top:8px;opacity:0;transition:.15s}
.msg-ai:hover .acts{opacity:1}
.acts button{display:flex;align-items:center;gap:6px;font-size:.72rem;color:var(--dim2);padding:5px 9px;border-radius:8px}
.acts button:hover{background:rgba(244,63,94,.12);color:var(--soft)}
.thinking .content{display:flex;align-items:center;gap:10px;color:var(--dim)}
.dots span{display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--pink);animation:blink 1.2s infinite}
.dots span:nth-child(2){animation-delay:.2s}
.dots span:nth-child(3){animation-delay:.4s}
.msg-err{margin:14px 0 14px 42px;background:rgba(190,18,60,.1);border:1px solid rgba(248,113,113,.35);color:#fecaca;border-radius:13px;padding:12px 15px;font-size:.84rem;line-height:1.6;white-space:pre-wrap;overflow-wrap:break-word}

/* ═══════════ COMPOSER ═══════════ */
#composer{padding:6px 20px 14px;background:linear-gradient(transparent,var(--bg) 30%)}
.compbox{max-width:760px;margin:0 auto;position:relative;background:var(--panel);border:1px solid var(--border);border-radius:24px;box-shadow:0 12px 40px rgba(0,0,0,.35);transition:.18s}
.compbox:focus-within{border-color:var(--border-hi);box-shadow:0 12px 44px rgba(244,63,94,.18)}
#inp{width:100%;background:none;border:none;outline:none;resize:none;color:var(--text);font:inherit;font-size:.93rem;line-height:1.55;padding:16px 18px 6px;max-height:190px}
#inp::placeholder{color:var(--dim2)}
.comprow{display:flex;align-items:center;gap:10px;padding:8px 10px 10px 14px}
#modelBtn{display:flex;align-items:center;gap:7px;font-size:.8rem;font-weight:600;color:var(--soft);border:1px solid var(--border);border-radius:999px;padding:7px 13px;transition:.15s;max-width:220px}
#modelBtn:hover{background:rgba(244,63,94,.1);border-color:var(--border-hi)}
#modelBtn .lb{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#sendBtn{margin-left:auto;width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 16px rgba(244,63,94,.4);transition:.15s;flex-shrink:0}
#sendBtn:hover{transform:scale(1.06)}
#sendBtn:disabled{opacity:.45;transform:none;cursor:default}
.hint{text-align:center;font-size:.68rem;color:var(--dim2);margin-top:9px}

/* model menu */
#modelMenu{position:absolute;bottom:calc(100% + 8px);left:0;width:300px;background:var(--panel2);border:1px solid var(--border-hi);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.6);padding:6px;display:none;z-index:70}
#modelMenu.open{display:block}
.mopt{display:flex;gap:11px;align-items:center;width:100%;padding:11px 12px;border-radius:11px;text-align:left;transition:.12s}
.mopt:hover{background:rgba(244,63,94,.1)}
.mopt.on{background:rgba(244,63,94,.14)}
.mopt .ic{width:32px;height:32px;border-radius:9px;background:rgba(244,63,94,.1);display:flex;align-items:center;justify-content:center;color:var(--pink);flex-shrink:0}
.mopt .tx{flex:1;min-width:0}
.mopt .tx b{display:block;font-size:.85rem}
.mopt .tx span{display:block;font-size:.7rem;color:var(--dim2);margin-top:1px;line-height:1.45}
.mopt .tick{color:var(--pink);opacity:0;flex-shrink:0}
.mopt.on .tick{opacity:1}

/* ═══════════ MODALS ═══════════ */
.modal{position:fixed;inset:0;background:rgba(5,2,4,.72);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;z-index:200;padding:16px}
.modal.hidden{display:none}
.sheet{background:var(--panel);border:1px solid var(--border);border-radius:20px;max-width:460px;width:100%;padding:24px;max-height:90vh;overflow:auto}
.sheet.wide{max-width:540px}
.shead{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
.shead h3{font-size:1.02rem;display:flex;align-items:center;gap:9px}
.shead h3 svg{color:var(--pink)}
.sheet label{display:block;font-size:.74rem;font-weight:600;color:var(--soft);margin:15px 0 6px;letter-spacing:.3px}
.sheet select,.sheet input{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:11px;color:var(--text);padding:11px 13px;font:inherit;font-size:.88rem;outline:none;transition:.15s}
.sheet select:focus,.sheet input:focus{border-color:var(--border-hi)}
.snote{font-size:.72rem;color:var(--dim2);line-height:1.55;margin-top:6px}
.snote a{color:var(--soft);text-decoration:underline}
.btnrow{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
.btn{border:none;border-radius:12px;padding:11px 18px;font-weight:600;font-size:.86rem;display:inline-flex;align-items:center;gap:8px;transition:.15s}
.btn.primary{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 6px 18px rgba(244,63,94,.3)}
.btn.primary:hover{filter:brightness(1.1)}
.btn.ghost{border:1px solid var(--border);color:var(--soft)}
.btn.ghost:hover{background:rgba(244,63,94,.1)}
.btn.danger{background:rgba(190,18,60,.15);border:1px solid rgba(248,113,113,.4);color:#fca5a5}
.btn.danger:hover{background:rgba(190,18,60,.28)}
.status{margin-top:12px;font-size:.78rem;min-height:1.4em;white-space:pre-wrap;color:var(--dim);line-height:1.55}
.status.ok{color:#86efac}
.status.bad{color:#fca5a5}

/* toast */
#toast{position:fixed;bottom:22px;left:50%;transform:translateX(-50%);background:var(--panel2);border:1px solid var(--border-hi);color:var(--text);padding:11px 18px;border-radius:13px;font-size:.83rem;z-index:300;box-shadow:0 14px 40px rgba(0,0,0,.55);display:none;align-items:center;gap:9px;max-width:90vw}
#toast svg{color:var(--pink)}
#backdrop{display:none;position:fixed;inset:0;background:rgba(5,2,4,.6);z-index:35}

@keyframes blink{0%,80%,100%{opacity:.25}40%{opacity:1}}
@keyframes rise{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}
.msg-user,.msg-ai{animation:rise .22s ease}

@media (max-width:900px){
  #sidebar{position:fixed;top:0;bottom:0;left:0;box-shadow:20px 0 60px rgba(0,0,0,.5)}
  #sidebar.closed{margin-left:-272px;box-shadow:none}
  #backdrop.show{display:block}
  .m-top{display:flex}
  #thread{padding:20px 16px 24px}
  .cards{grid-template-columns:1fr}
  .msg-user .bub{max-width:88%}
  .hint{padding:0 6px}
}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important}}

/* ═══════════ LIGHT THEME (Claude-style warm) ═══════════ */
[data-theme=light]{
  --bg:#faf9f7; --bg2:#f4f2ee; --sb:#f2f0ea; --panel:#ffffff; --panel2:#f0ede9; --panel3:#e7e3dd;
  --border:rgba(120,80,90,.18); --border-hi:rgba(190,30,60,.45);
  --red:#e11d48; --red2:#f43f5e; --pink:#c2415f; --soft:#a63d57;
  --text:#262023; --dim:#6e5f65; --dim2:#9c8b91;
}
[data-theme=light] body{background:radial-gradient(1000px 500px at 70% -10%,rgba(225,29,72,.05),transparent 55%),var(--bg)}
[data-theme=light] .m-top{background:rgba(250,249,247,.92)}
[data-theme=light] .sb-search{background:rgba(120,80,90,.08)}
[data-theme=light] .compbox{box-shadow:0 12px 40px rgba(120,80,90,.16)}
[data-theme=light] .modal{background:rgba(60,40,48,.35)}
[data-theme=light] .iconbtn:hover{background:rgba(190,30,60,.1)}
[data-theme=light] .content strong{color:#141013}
[data-theme=light] .content h3,[data-theme=light] .content h4{color:#141013}
[data-theme=light] .content pre code{color:#43323a}

/* ═══════════ CODE PREVIEW (mini artifacts) ═══════════ */
.pvwrap{margin:.6em 0}
.pvwrap pre{margin:0!important;border-radius:0 0 12px 12px;border-top:none!important}
.pvbar{display:flex;align-items:center;justify-content:space-between;background:var(--bg2);border:1px solid var(--border);border-bottom:none;border-radius:12px 12px 0 0;padding:5px 6px 5px 12px}
.pvlang{font-size:.66rem;letter-spacing:1.2px;text-transform:uppercase;color:var(--dim2);font-weight:700}
.pvbtn{display:inline-flex;align-items:center;gap:6px;font-size:.72rem;font-weight:600;color:var(--soft);padding:5px 10px;border-radius:8px;transition:.12s}
.pvbtn:hover{background:rgba(244,63,94,.12);color:var(--pink)}
.pvFrame{border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-top:14px;background:#fff;height:58vh}
.pvFrame iframe{width:100%;height:100%;border:none;display:block;background:#fff}
#previewModal .sheet{max-width:880px;width:94vw}
</style>
</head>
<body>

<div id="app">

  <!-- ═══ SIDEBAR ═══ -->
  <aside id="sidebar">
    <div class="sb-top">
      <button class="iconbtn" id="sbToggle" title="Close sidebar"><?= icon('panel-left') ?></button>
      <a class="brand" href="index.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
      <button class="iconbtn" id="themeBtn" title="Switch theme"><?= icon('sun', 17) ?></button>
    </div>
    <button class="newchat" id="newChatBtn"><?= icon('square-pen', 17) ?> New chat</button>
    <div class="sb-search"><?= icon('search', 15) ?><input id="searchInp" type="text" placeholder="Search chats…" autocomplete="off"></div>
    <nav id="chatList" aria-label="Chat history"></nav>
    <div class="sb-bottom">
      <div id="userMenu">
        <button class="mi" id="mCookies"><?= icon('cookie', 16) ?> Cookie settings</button>
        <hr>
        <button class="mi danger" id="mDelAcc"><?= icon('warning', 16) ?> Delete account</button>
        <button class="mi" id="mLogout"><?= icon('logout', 16) ?> Log out</button>
      </div>
      <button class="userbtn" id="userBtn">
        <span class="av"><?= htmlspecialchars(strtoupper(mb_substr($me['name'], 0, 1))) ?></span>
        <span class="nm"><b><?= htmlspecialchars($me['name']) ?></b><span><?= htmlspecialchars($me['email']) ?></span></span>
        <span class="chev"><?= icon('chevron-down', 15) ?></span>
      </button>
    </div>
  </aside>
  <div id="backdrop"></div>

  <!-- ═══ MAIN ═══ -->
  <main>
    <header class="m-top">
      <button class="iconbtn" id="sbOpen" title="Open sidebar"><?= icon('menu', 19) ?></button>
      <span class="brand"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</span>
      <button class="iconbtn" id="themeBtnM" title="Switch theme"><?= icon('sun', 17) ?></button>
    </header>

    <div id="scroller"><div id="thread">
      <div id="welcome">
        <img class="big" src="assets/logo.svg" alt="Devil AI logo">
        <h2>What shall we summon today?</h2>
        <p class="sub">Pick a model in the chat box and ask me anything.</p>
        <div class="cards">
          <button class="card" data-fill="Explain quantum computing like I'm five — but make it devilish."><span class="ic"><?= icon('lightbulb') ?></span><span><b>Explain a concept</b><span>Quantum computing, but make it simple</span></span></button>
          <button class="card" data-fill="Help me write a witty birthday message for my best friend."><span class="ic"><?= icon('pencil') ?></span><span><b>Help me write</b><span>A witty birthday message for a friend</span></span></button>
          <button class="card" data-fill="Brainstorm 10 devilish startup names for my new project."><span class="ic"><?= icon('sparkles') ?></span><span><b>Brainstorm ideas</b><span>10 devilish names for a project</span></span></button>
          <button class="card" data-fill="Tell me something fascinating about space."><span class="ic"><?= icon('flame') ?></span><span><b>Just chat</b><span>Something fascinating about space</span></span></button>
        </div>
      </div>
      <div id="msgs"></div>
    </div></div>

    <div id="composer">
      <div class="compbox">
        <textarea id="inp" rows="1" maxlength="4000" placeholder="Message Devil AI…"></textarea>
        <div class="comprow">
          <button id="modelBtn" title="Choose model"><span id="modelIco"><?= icon('zap', 14) ?></span><span class="lb" id="modelLbl">Devil Flash</span><?= icon('chevron-down', 13) ?></button>
          <button id="sendBtn" title="Send (Enter)" disabled><?= icon('send', 17) ?></button>
        </div>
        <div id="modelMenu"></div>
      </div>
      <p class="hint">Devil AI v1.0.0.0 — can make mistakes. Double-check important info.</p>
    </div>
  </main>
</div>

<!-- ═══ rename modal ═══ -->
<div class="modal hidden" id="renameModal"><div class="sheet">
  <div class="shead"><h3><?= icon('pencil', 17) ?> Rename chat</h3><button class="iconbtn" data-close="renameModal"><?= icon('x', 16) ?></button></div>
  <label for="renameInp">Chat title</label>
  <input id="renameInp" type="text" maxlength="80" autocomplete="off">
  <div class="btnrow"><button class="btn primary" id="renameSave">Save</button><button class="btn ghost" data-close="renameModal">Cancel</button></div>
</div></div>

<!-- ═══ delete account modal (email-code confirmation) ═══ -->
<div class="modal hidden" id="delAccModal"><div class="sheet">
  <div class="shead"><h3><?= icon('warning', 17) ?> Delete account</h3><button class="iconbtn" data-close="delAccModal"><?= icon('x', 16) ?></button></div>
  <p class="snote" style="margin-top:12px">This permanently deletes your account, <b>all your chats and your sign-in</b>. There is no undo. We'll send a confirmation code to your email.</p>
  <div class="btnrow"><button class="btn ghost" id="delAccSend" type="button"><?= icon('mail', 15) ?> Send code to my email</button></div>
  <div id="delAccStep2" class="hidden">
    <label for="delAccCode">Confirmation code</label>
    <input id="delAccCode" type="text" inputmode="numeric" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code">
  </div>
  <div class="status bad" id="delAccStatus"></div>
  <div class="btnrow"><button class="btn danger" id="delAccGo" type="button"><?= icon('trash', 15) ?> Delete everything</button><button class="btn ghost" data-close="delAccModal">Cancel</button></div>
</div></div>

<!-- ═══ code preview modal (mini artifacts) ═══ -->
<div class="modal hidden" id="previewModal"><div class="sheet">
  <div class="shead">
    <h3 id="pvTitle"><?= icon('play', 16) ?> Preview</h3>
    <div style="display:flex;gap:8px;align-items:center">
      <button class="btn ghost" id="pvNewTab" title="Open in a new tab"><?= icon('external', 14) ?> New tab</button>
      <button class="iconbtn" data-close="previewModal"><?= icon('x', 16) ?></button>
    </div>
  </div>
  <div class="pvFrame"><iframe id="pvFrame" sandbox="allow-scripts" title="Code preview"></iframe></div>
</div></div>

<div id="toast"><?= icon('check', 16) ?><span id="toastTxt"></span></div>

<?php require __DIR__ . '/inc/cookiebar.php'; ?>

<script>
const I = <?= json_encode($JS_ICONS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const ME = <?= json_encode(['name' => $me['name'], 'email' => $me['email']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
(function () {
'use strict';
var $ = function (s) { return document.querySelector(s); };
var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };

/* ── state ── */
var models = [], modelById = {}, currentModel = 'flash';
var chats = [], currentChat = null;   /* currentChat = {id, title, messages} */
var busy = false;
var personalOK = true;
try {
  var prefs = JSON.parse(localStorage.getItem('devil_cookie_prefs') || 'null');
  if (prefs && prefs.personalization === false) { personalOK = false; }
} catch (e) {}

function store(key, val) { if (!personalOK) { return; } try { localStorage.setItem(key, val); } catch (e) {} }
function read(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }

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
function api(action, body, method) {
  method = method || (body === undefined ? 'GET' : 'POST');
  var opt = { method: method, headers: { 'Content-Type': 'application/json' } };
  if (method === 'POST') { opt.body = JSON.stringify(body || {}); }
  /* NOTE: action may carry extra query params (chat_load&id=…) whose values
     are already encodeURIComponent'd by the caller — so don't re-encode here */
  return fetch('api.php' + (action ? '?action=' + action : ''), opt).then(function (r) {
    if (r.status === 401) { window.location.href = 'login.php'; throw new Error('signed out'); }
    return r.json();
  }).catch(function (e) { return { ok: false, error: 'Network error — please try again.' }; });
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

/* ── sidebar ── */
var sb = $('#sidebar'), bd = $('#backdrop');
function setSb(open) {
  sb.classList.toggle('closed', !open);
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
  var groups = { today: [], yest: [], week: [], older: [] };
  chats.forEach(function (c) {
    if (filter && (c.title || '').toLowerCase().indexOf(filter) === -1) { return; }
    var t = c.updated ? c.updated * 1000 : 0;
    if (t >= today) { groups.today.push(c); }
    else if (t >= yest) { groups.yest.push(c); }
    else if (t >= week) { groups.week.push(c); }
    else { groups.older.push(c); }
  });
  var html = '';
  var labels = { today: 'Today', yest: 'Yesterday', week: 'Previous 7 days', older: 'Older' };
  var any = false;
  Object.keys(labels).forEach(function (k) {
    if (!groups[k].length) { return; }
    any = true;
    html += '<div class="grp">' + labels[k] + '</div>';
    groups[k].forEach(function (c) {
      html += '<div class="chatitem' + (currentChat && currentChat.id === c.id ? ' on' : '') + '" data-id="' + c.id + '">' +
        '<span class="t"></span><span class="act">' +
        '<button data-rename="' + c.id + '" title="Rename">' + I.pencil + '</button>' +
        '<button data-del="' + c.id + '" title="Delete">' + I.trash + '</button></span></div>';
    });
  });
  if (!any) { html = '<div class="list-empty">' + (filter ? 'No chats match your search.' : 'No chats yet — your conversations will appear here.') + '</div>'; }
  box.innerHTML = html;
  $$('#chatList .chatitem').forEach(function (el) {
    el.querySelector('.t').textContent = (chats.filter(function (c) { return c.id === el.dataset.id; })[0] || {}).title || 'New chat';
  });
}

function loadChats() {
  return api('chats').then(function (j) {
    if (j.ok) { chats = j.chats || []; renderList($('#searchInp').value); }
  });
}
$('#searchInp').addEventListener('input', function () { renderList(this.value); });

$('#chatList').addEventListener('click', function (e) {
  var rn = e.target.closest('[data-rename]'), del = e.target.closest('[data-del]');
  if (rn) { e.stopPropagation(); openRename(rn.dataset.rename); return; }
  if (del) { e.stopPropagation(); deleteChat(del.dataset.del); return; }
  var it = e.target.closest('.chatitem');
  if (it) { openChat(it.dataset.id); if (window.innerWidth <= 900) { setSb(false); } }
});

/* ── messages ── */
var msgs = $('#msgs'), welcome = $('#welcome'), scroller = $('#scroller');
function scrollDown() { scroller.scrollTop = scroller.scrollHeight; }

function addUserMsg(text) {
  var d = document.createElement('div');
  d.className = 'msg-user';
  var b = document.createElement('div');
  b.className = 'bub';
  b.textContent = text;
  d.appendChild(b);
  msgs.appendChild(d);
  welcome.style.display = 'none';
  scrollDown();
}

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

function aiContent(el, text) {
  el.querySelector('.content').innerHTML = md(text);
  enhancePre(el);
  var acts = el.querySelector('.acts');
  acts.innerHTML = '';
  var cp = document.createElement('button');
  cp.innerHTML = I.copy + ' Copy';
  cp.addEventListener('click', function () {
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { toast('Copied to clipboard'); }, function () { toast('Copy failed — select the text manually', 'warning'); });
  });
  acts.appendChild(cp);
  if (optsCanRetry(el)) {
    var rt = document.createElement('button');
    rt.innerHTML = I.retry + ' Retry';
    rt.addEventListener('click', retryLast);
    acts.appendChild(rt);
  }
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
  var d = addAiMsg({ modelTag: (modelById[currentModel] || {}).label });
  d.classList.add('thinking');
  d.querySelector('.content').innerHTML = '<span class="dots"><span></span><span></span><span></span></span> thinking…';
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
var inp = $('#inp'), sendBtn = $('#sendBtn');
function resize() { inp.style.height = 'auto'; inp.style.height = Math.min(inp.scrollHeight, 190) + 'px'; sendBtn.disabled = busy || !inp.value.trim(); }
inp.addEventListener('input', resize);
inp.addEventListener('keydown', function (e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
});
sendBtn.addEventListener('click', function () { send(); });

function newChatView() {
  currentChat = null;
  msgs.innerHTML = '';
  welcome.style.display = '';
  renderList($('#searchInp').value);
  if (window.innerWidth > 900) { inp.focus(); }
}
$('#newChatBtn').addEventListener('click', newChatView);

$$('#welcome .card').forEach(function (c) {
  c.addEventListener('click', function () { inp.value = c.dataset.fill; resize(); send(); });
});

/* ── send / retry ── */
function send() {
  var text = inp.value.trim();
  if (!text || busy) { return; }
  inp.value = ''; resize();
  addUserMsg(text);
  runSend({ message: text, model: currentModel, id: currentChat ? currentChat.id : null });
}

function retryLast() {
  if (busy || !currentChat) { return; }
  var m = currentChat.messages;
  if (!m.length || m[m.length - 1].role !== 'assistant') { return; }
  /* drop last assistant message visually + in memory */
  m.pop();
  if (msgs.lastElementChild && msgs.lastElementChild.classList.contains('msg-ai')) { msgs.lastElementChild.remove(); }
  runSend({ id: currentChat.id, retry: true, model: currentModel });
}

function runSend(payload) {
  busy = true; sendBtn.disabled = true;
  var th = addThinking();
  api('chat_send', payload).then(function (j) {
    th.remove();
    if (j.ok) {
      if (!currentChat) { currentChat = { id: j.id, title: j.title, messages: [] }; }
      currentChat.id = j.id; currentChat.title = j.title;
      if (payload.retry) {
        /* keep existing user msg, replace assistant */
      } else {
        currentChat.messages.push({ role: 'user', content: payload.message });
      }
      currentChat.messages.push({ role: 'assistant', content: j.reply });
      var el = addAiMsg({ modelTag: (j.model && j.model.label) || modelById[currentModel].label });
      aiContent(el, j.reply);
      loadChats();
    } else {
      if (payload.retry) { /* put a placeholder assistant error, keep chat usable */ }
      addErr(j.error + (j.hint ? '\nHint: ' + j.hint : ''));
    }
  }).finally(function () {
    busy = false; resize();
  });
}

/* ── open / delete / rename chats ── */
function openChat(id) {
  if (busy) { return; }
  api('chat_load&id=' + encodeURIComponent(id)).then(function (j) {
    if (!j.ok) { toast(j.error || 'Could not open chat', 'warning'); return; }
    currentChat = j.chat;
    msgs.innerHTML = '';
    welcome.style.display = currentChat.messages.length ? 'none' : '';
    currentChat.messages.forEach(function (m) {
      if (m.role === 'user') { addUserMsg(m.content); }
      else { var el = addAiMsg({ modelTag: m.model_label }); aiContent(el, m.content); }
    });
    renderList($('#searchInp').value);
    scrollDown();
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
}
function setModelBtn() {
  var m = modelById[currentModel] || models[0];
  if (!m) { return; }
  $('#modelLbl').textContent = m.label;
  $('#modelIco').innerHTML = I[m.icon] || I.sparkles;
}
$('#modelBtn').addEventListener('click', function (e) { e.stopPropagation(); $('#modelMenu').classList.toggle('open'); });
document.addEventListener('click', function (e) {
  if (!e.target.closest('#modelMenu') && !e.target.closest('#modelBtn')) { $('#modelMenu').classList.remove('open'); }
});

/* ── user menu ── */
$('#userBtn').addEventListener('click', function (e) { e.stopPropagation(); $('#userMenu').classList.toggle('open'); });
document.addEventListener('click', function (e) { if (!e.target.closest('.sb-bottom')) { $('#userMenu').classList.remove('open'); } });
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

/* ── boot ── */
api('bootstrap').then(function (j) {
  if (!j.ok) { return; }
  models = j.models || [];
  modelById = {};
  models.forEach(function (m) { modelById[m.id] = m; });
  var saved = read('devil_model');
  if (saved && modelById[saved]) { currentModel = saved; }
  else if (j.default && modelById[j.default]) { currentModel = j.default; }
  setModelBtn();
  renderModelMenu();
});
loadChats();
resize();
if (window.innerWidth > 900) { inp.focus(); }
})();
</script>
</body>
</html>
