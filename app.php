<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Chat App (app.php) • v1.0.0.0
 *  Claude-style layout: sidebar + isolated chats +
 *  model picker + admin settings. Login required.
 * ═══════════════════════════════════════════════════════
 */
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
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
    'send' => icon('send', 17), 'stop' => icon('stop', 17), 'copy' => icon('copy', 15), 'retry' => icon('retry', 15),
    'thumbUp' => icon('thumb-up', 15), 'thumbDown' => icon('thumb-down', 15), 'share' => icon('share', 15),
    'trash' => icon('trash', 15), 'pencil' => icon('pencil', 14), 'check' => icon('check', 15),
    'x' => icon('x', 16), 'chev' => icon('chevron-down', 14), 'zap' => icon('zap', 15),
    'sparkles' => icon('sparkles', 15), 'crown' => icon('crown', 15), 'ghost' => icon('ghost', 15),
    'user' => icon('user', 16), 'logout' => icon('logout', 16), 'settings' => icon('settings', 16),
    'panel' => icon('panel-left', 18), 'newchat' => icon('square-pen', 17), 'search' => icon('search', 15),
    'loader' => icon('loader', 16), 'warning' => icon('warning', 16), 'lock' => icon('lock', 16),
    'message' => icon('message', 16), 'menu' => icon('menu', 18), 'flame' => icon('flame', 15),
    'lightbulb' => icon('lightbulb', 17), 'shield' => icon('shield', 16),
    'sun' => icon('sun', 17), 'moon' => icon('moon', 17), 'play' => icon('play', 13),
    'layers' => icon('layers', 15), 'brain' => icon('brain', 15), 'server' => icon('server', 15),
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0709">
<meta name="robots" content="noindex">
<script>/* theme boot — runs before paint to avoid a flash of the wrong theme */
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}
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
.chattools{position:absolute;top:14px;right:18px;z-index:25;display:flex;align-items:center;gap:9px;padding:6px;border:1px solid var(--border);background:rgba(23,16,20,.72);backdrop-filter:blur(14px);border-radius:18px;box-shadow:0 14px 40px rgba(0,0,0,.22)}
.ctbtn{height:36px;min-width:36px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;gap:8px;color:var(--dim);padding:0 11px;font-size:.8rem;font-weight:700;transition:.16s;border:1px solid transparent;white-space:nowrap}
.ctbtn:hover{background:rgba(244,63,94,.12);color:var(--soft);border-color:var(--border)}
.ctbtn.primary{background:linear-gradient(135deg,rgba(244,63,94,.18),rgba(190,18,60,.12));color:var(--soft);border-color:var(--border)}
.ctbtn.on{background:rgba(244,63,94,.22);color:#fff;border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.08)}
.ctbtn.stop{display:none;color:#fecaca;background:rgba(190,18,60,.18);border-color:rgba(248,113,113,.35)}
.ctbtn.stop.show{display:inline-flex}
.ctbtn .txt{display:inline}
body.temp-chat #thread:before{content:'Temporary chat — not saved in history';display:block;width:max-content;max-width:100%;margin:0 auto 12px;padding:7px 12px;border-radius:999px;border:1px solid var(--border);background:rgba(244,63,94,.08);color:var(--soft);font-size:.72rem;font-weight:700;letter-spacing:.2px}

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
.msg-user{display:flex;flex-direction:column;align-items:flex-end;margin:26px 0}
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
.acts{display:flex;gap:6px;margin-top:9px;opacity:.72;transition:.18s;flex-wrap:wrap}
.msg-ai:hover .acts,.msg-user:hover .acts{opacity:1}
.msg-user .acts{justify-content:flex-end;max-width:78%;margin-top:7px}
.acts button{width:30px;height:30px;display:flex;align-items:center;justify-content:center;color:var(--dim2);padding:0;border-radius:10px;border:1px solid transparent;transition:transform .16s ease,background .16s ease,color .16s ease,border-color .16s ease,box-shadow .16s ease;position:relative}
.acts button svg{width:15px;height:15px;transition:transform .16s ease}
.acts button:hover{background:rgba(244,63,94,.12);color:var(--soft);border-color:var(--border);transform:translateY(-1px) scale(1.06);box-shadow:0 8px 22px rgba(0,0,0,.16)}
.acts button:hover svg{transform:scale(1.08)}
.acts button:active{transform:translateY(0) scale(.94)}
.acts button.on{background:rgba(244,63,94,.14);color:var(--soft);border-color:var(--border-hi);animation:act-pop .22s ease}
.acts button:disabled{opacity:.55;cursor:default;transform:none;box-shadow:none}
.acts button[hidden]{display:none!important}
@keyframes act-pop{0%{transform:scale(.86)}70%{transform:scale(1.12)}100%{transform:scale(1)}}
.msg-user.editing .bub,.msg-user.editing>.acts{display:none!important}
.inlineEditBox{width:min(78%,100%);align-self:flex-end;background:var(--panel);border:1px solid var(--border-hi);border-radius:18px;border-bottom-right-radius:6px;padding:10px;box-shadow:0 14px 40px rgba(0,0,0,.22);animation:edit-pop .16s ease}
.inlineEditBox textarea{width:100%;min-height:92px;max-height:260px;resize:none;background:var(--panel2);border:1px solid var(--border);border-radius:13px;color:var(--text);font:inherit;font-size:.92rem;line-height:1.6;padding:11px 12px;outline:none;white-space:pre-wrap}
.inlineEditBox textarea:focus{border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.08)}
.inlineEditActions{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:9px}
.inlineEditActions button{border-radius:10px;padding:7px 12px;font-size:.76rem;font-weight:800;transition:.15s}
.inlineEditActions .cancel{color:var(--dim);border:1px solid var(--border)}
.inlineEditActions .cancel:hover{background:rgba(244,63,94,.08);color:var(--soft)}
.inlineEditActions .save{background:linear-gradient(135deg,#f43f5e,#be123c);color:white;box-shadow:0 6px 18px rgba(244,63,94,.28)}
.inlineEditActions .save:hover{transform:translateY(-1px);box-shadow:0 10px 26px rgba(244,63,94,.34)}
.editBadge{display:block;width:max-content;margin-top:7px;margin-left:auto;font-size:.66rem;font-weight:700;color:var(--soft);border:1px solid var(--border);border-radius:999px;padding:2px 8px;background:rgba(244,63,94,.08)}
.branchNav{display:flex;align-items:center;justify-content:flex-end;gap:7px;max-width:78%;margin-top:7px;color:var(--dim2);font-size:.72rem}
.branchNav button{width:28px;height:28px;border-radius:9px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--dim);background:transparent;font-weight:900;transition:transform .15s ease,background .15s ease,color .15s ease,border-color .15s ease}
.branchNav button:hover:not(:disabled){background:rgba(244,63,94,.12);color:var(--soft);border-color:var(--border-hi);transform:translateY(-1px) scale(1.05)}
.branchNav button:active:not(:disabled){transform:scale(.94)}
.branchNav button:disabled{opacity:.38;cursor:default}
.branchNav .count{min-width:42px;text-align:center;font-weight:800;color:var(--soft);letter-spacing:.2px}
.branchNav .hint{margin:0;color:var(--dim2);font-size:.68rem;white-space:nowrap}
@keyframes edit-pop{from{opacity:0;transform:translateY(5px) scale(.98)}to{opacity:1;transform:none}}
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
#modelBtn,#customModelBtn{display:flex;align-items:center;gap:7px;font-size:.8rem;font-weight:600;color:var(--soft);border:1px solid var(--border);border-radius:999px;padding:7px 13px;transition:.15s;max-width:220px;min-width:0}
.modelwrap{display:flex;align-items:center;gap:8px;position:relative;min-width:0}
.customwrap{display:none;align-items:center;position:relative;min-width:0}
.customwrap.show{display:flex}
#customModelBtn{max-width:260px;color:var(--text);background:rgba(244,63,94,.07)}
#modelBtn:hover,#customModelBtn:hover{background:rgba(244,63,94,.1);border-color:var(--border-hi)}
#modelBtn .lb,#customModelBtn .lb{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#customIco{display:flex;align-items:center;justify-content:center;flex-shrink:0}
#sendBtn{margin-left:auto;width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 16px rgba(244,63,94,.4);transition:.15s;flex-shrink:0}
#sendBtn:hover{transform:scale(1.06)}
#sendBtn:disabled{opacity:.45;transform:none;cursor:default}
#sendBtn.stopmode{background:rgba(190,18,60,.22);border:1px solid rgba(248,113,113,.45);color:#fecaca;box-shadow:none}
.hint{text-align:center;font-size:.68rem;color:var(--dim2);margin-top:9px}
#attachBtn{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--dim);transition:.15s;flex-shrink:0}
#attachBtn:hover{background:rgba(244,63,94,.12);color:var(--soft)}
#imgChip{display:none;align-items:center;gap:10px;margin:12px 16px 0;padding:7px 10px;background:var(--panel2);border:1px solid var(--border);border-radius:12px;width:fit-content;max-width:calc(100% - 32px)}
#imgChip.show{display:flex}
#imgChip img{width:42px;height:42px;object-fit:cover;border-radius:8px;flex-shrink:0}
#imgChip .meta{font-size:.68rem;color:var(--dim2);min-width:0;line-height:1.5}
#imgChip .meta b{display:block;font-size:.76rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
#imgChip .rm{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--dim2);flex-shrink:0}
#imgChip .rm:hover{background:rgba(190,18,60,.15);color:var(--pink)}
#editChip{display:none;align-items:center;justify-content:space-between;gap:10px;margin:12px 16px 0;padding:9px 11px;background:rgba(244,63,94,.08);border:1px solid var(--border);border-radius:12px;color:var(--soft);font-size:.76rem;line-height:1.45}
#editChip.show{display:flex}
#editChip span{display:flex;align-items:center;gap:7px;min-width:0}
#editChip button{font-size:.72rem;font-weight:700;color:var(--dim);padding:5px 8px;border-radius:8px;flex-shrink:0}
#editChip button:hover{background:rgba(244,63,94,.12);color:var(--soft)}
.msg-img{display:block;max-width:min(260px,100%);max-height:280px;border-radius:14px;border:1px solid var(--border);margin-bottom:8px;cursor:zoom-in;object-fit:cover}
#imgView{position:fixed;inset:0;background:rgba(5,2,4,.85);backdrop-filter:blur(8px);z-index:400;display:none;align-items:center;justify-content:center;padding:24px;cursor:zoom-out}
#imgView.show{display:flex}
#imgView img{max-width:100%;max-height:100%;border-radius:14px;box-shadow:0 30px 90px rgba(0,0,0,.6)}

/* model menu */
#modelMenu{position:absolute;bottom:calc(100% + 8px);left:0;width:300px;max-width:calc(100vw - 44px);background:var(--panel2);border:1px solid var(--border-hi);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.6);padding:6px;display:none;z-index:70}
#modelMenu.open{display:block}
#customModelMenu{position:absolute;bottom:calc(100% + 8px);left:0;width:390px;max-width:calc(100vw - 44px);background:var(--panel2);border:1px solid var(--border-hi);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.6);padding:8px;display:none;z-index:75}
#customModelMenu.open{display:block}
.csearch{width:100%;background:var(--panel);border:1px solid var(--border);color:var(--text);border-radius:11px;padding:10px 12px;font:inherit;font-size:.8rem;outline:none;margin-bottom:6px}
.csearch:focus{border-color:var(--border-hi)}
.cmlist{max-height:360px;overflow:auto;padding-right:2px}
.mopt,.cmopt{display:flex;gap:11px;align-items:center;width:100%;padding:11px 12px;border-radius:11px;text-align:left;transition:.12s}
.mopt:hover,.cmopt:hover{background:rgba(244,63,94,.1)}
.mopt.on,.cmopt.on{background:rgba(244,63,94,.14)}
.mopt .ic,.cmopt .ic{width:32px;height:32px;border-radius:9px;background:rgba(244,63,94,.1);display:flex;align-items:center;justify-content:center;color:var(--pink);flex-shrink:0;overflow:hidden}
.cmopt .ic svg,#customIco svg{display:block}
.mopt .tx,.cmopt .tx{flex:1;min-width:0}
.mopt .tx b,.cmopt .tx b{display:block;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mopt .tx span,.cmopt .tx span{display:block;font-size:.7rem;color:var(--dim2);margin-top:1px;line-height:1.45;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cmopt .tx em{font-style:normal;color:var(--soft)}
.mopt .tick,.cmopt .tick{color:var(--pink);opacity:0;flex-shrink:0}
.mopt.on .tick,.cmopt.on .tick{opacity:1}
.cmempty{padding:14px;text-align:center;color:var(--dim2);font-size:.78rem}
@media(max-width:680px){#customModelBtn{max-width:150px}#customModelMenu{left:auto;right:0;width:340px}.modelwrap{gap:6px}#modelBtn{max-width:150px}}

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
  .chattools{top:8px;right:56px;padding:4px;border-radius:14px;background:rgba(23,16,20,.86)}
  .ctbtn{height:34px;min-width:34px;padding:0 9px}.ctbtn .txt{display:none}
  #thread{padding:20px 16px 24px}
  .cards{grid-template-columns:1fr}
  .msg-user .bub{max-width:88%}.msg-user .acts{max-width:88%}.branchNav{max-width:88%}.inlineEditBox{width:min(88%,100%)}
  .hint{padding:0 6px}
}
@media (hover:none){.acts{opacity:1}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important}}

/* ═══════════ LIGHT THEME (Claude-style warm) ═══════════ */
[data-theme=light]{
  --bg:#faf9f7; --bg2:#f4f2ee; --sb:#f2f0ea; --panel:#ffffff; --panel2:#f0ede9; --panel3:#e7e3dd;
  --border:rgba(120,80,90,.18); --border-hi:rgba(190,30,60,.45);
  --red:#e11d48; --red2:#f43f5e; --pink:#c2415f; --soft:#a63d57;
  --text:#262023; --dim:#6e5f65; --dim2:#82696f;
}
[data-theme=light] body{background:radial-gradient(1000px 500px at 70% -10%,rgba(225,29,72,.05),transparent 55%),var(--bg)}
[data-theme=light] .m-top{background:rgba(250,249,247,.92)}
[data-theme=light] .chattools{background:rgba(255,255,255,.86);box-shadow:0 14px 40px rgba(120,80,90,.14)}
[data-theme=light] .ctbtn{color:#6e5f65}
[data-theme=light] .ctbtn:hover{background:rgba(190,30,60,.09);color:#a63d57;border-color:rgba(190,30,60,.22)}
[data-theme=light] .ctbtn.primary{background:rgba(190,30,60,.08);color:#a63d57;border-color:rgba(190,30,60,.20)}
[data-theme=light] .ctbtn.on{background:rgba(190,30,60,.16);color:#7f1d1d;border-color:rgba(190,30,60,.38);box-shadow:0 0 0 3px rgba(190,30,60,.08)}
[data-theme=light] .ctbtn.stop,[data-theme=light] #sendBtn.stopmode{background:rgba(190,18,60,.10);border-color:rgba(190,18,60,.32);color:#9f1239;box-shadow:none}
[data-theme=light] #editChip{background:rgba(190,30,60,.07);border-color:rgba(190,30,60,.20);color:#9f1239}
[data-theme=light] .inlineEditBox{background:#fff;border-color:rgba(190,30,60,.28);box-shadow:0 14px 34px rgba(120,80,90,.14)}
[data-theme=light] .inlineEditBox textarea{background:#f7f3ef;border-color:rgba(120,80,90,.18)}
[data-theme=light] .editBadge{background:rgba(190,30,60,.08);border-color:rgba(190,30,60,.20);color:#9f1239}
[data-theme=light] .branchNav button:hover:not(:disabled){background:rgba(190,30,60,.09);color:#a63d57;border-color:rgba(190,30,60,.28)}
[data-theme=light] body.temp-chat #thread:before{background:rgba(190,30,60,.08);border-color:rgba(190,30,60,.22);color:#9f1239}
[data-theme=light] .sb-search{background:rgba(120,80,90,.08)}
[data-theme=light] .compbox{box-shadow:0 12px 40px rgba(120,80,90,.16)}
[data-theme=light] .modal{background:rgba(60,40,48,.35)}
[data-theme=light] .iconbtn:hover{background:rgba(190,30,60,.1)}
[data-theme=light] .content strong{color:#141013}
[data-theme=light] .content h3,[data-theme=light] .content h4{color:#141013}
[data-theme=light] .acts button:hover{background:rgba(190,30,60,.09);color:#a63d57;border-color:rgba(190,30,60,.22)}
[data-theme=light] .acts button.on{background:rgba(190,30,60,.12);color:#9f1239;border-color:rgba(190,30,60,.32)}
[data-theme=light] .content pre code{color:#43323a}
[data-theme=light] .msg-err{background:rgba(190,18,60,.07);border-color:rgba(190,18,60,.3);color:#9f1239}
[data-theme=light] .btn.danger{background:rgba(190,18,60,.08);border-color:rgba(190,18,60,.35);color:#9f1239}
[data-theme=light] .btn.danger:hover{background:rgba(190,18,60,.15)}
[data-theme=light] .status.ok{color:#15803d}
[data-theme=light] .status.bad{color:#b91c1c}
[data-theme=light] #userMenu,[data-theme=light] #modelMenu,[data-theme=light] #customModelMenu{box-shadow:0 18px 50px rgba(120,80,90,.18)}
[data-theme=light] #sendBtn.stopmode svg{color:#9f1239}
[data-theme=light] #toast{box-shadow:0 14px 40px rgba(120,80,90,.18)}
[data-theme=light] #userMenu .mi.danger:hover{background:rgba(190,18,60,.12);color:#b91c1c}

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
        <a class="mi" href="settings.php"><?= icon('settings', 16) ?> Account settings</a>
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

    <div class="chattools" id="chatTools" aria-label="Chat actions">
      <button class="ctbtn primary" id="tempChatBtn" type="button" title="Start temporary chat"><?= icon('ghost', 17) ?><span class="txt">Temp</span></button>
      <button class="ctbtn" id="topNewChatBtn" type="button" title="New chat"><?= icon('square-pen', 17) ?><span class="txt">New</span></button>
    </div>

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
        <div id="imgChip"></div>
        <div id="editChip"><span><?= icon('pencil', 14) ?> Editing message — original chat stays saved, a new branch will be created.</span><button id="editCancel" type="button">Cancel</button></div>
        <textarea id="inp" rows="1" maxlength="4000" placeholder="Message Devil AI…"></textarea>
        <div class="comprow">
          <div class="modelwrap">
            <button id="attachBtn" title="Attach image" type="button"><?= icon('paperclip', 16) ?></button>
            <button id="modelBtn" title="Choose model"><span id="modelIco"><?= icon('zap', 14) ?></span><span class="lb" id="modelLbl">Devil Flash</span><?= icon('chevron-down', 13) ?></button>
            <div id="modelMenu"></div>
            <div id="customWrap" class="customwrap">
              <button id="customModelBtn" title="Choose custom AI model" type="button"><span id="customIco"></span><span class="lb" id="customLbl">Devil Smart</span><?= icon('chevron-down', 13) ?></button>
              <div id="customModelMenu"></div>
            </div>
          </div>
          <button id="sendBtn" title="Send (Enter)" disabled><?= icon('send', 17) ?></button>
        </div>
      </div>
      <p class="hint">Enter = new line • Ctrl/⌘ + Enter = send • Devil AI can make mistakes.</p>
    </div>
  </main>
</div>

<input type="file" id="fileInput" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
<div id="imgView"><?= icon('x', 22) ?></div>

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
const INITIAL_CHAT_ID = <?= json_encode(preg_match('/^c[a-f0-9]{16}$/', (string)($_GET['chat'] ?? '')) ? (string)$_GET['chat'] : '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
(function () {
'use strict';
var $ = function (s) { return document.querySelector(s); };
var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };

/* ── state ── */
var models = [], modelById = {}, currentModel = 'flash';
var customModels = [], customById = {}, currentCustom = 'devil-09';
var chats = [], currentChat = null;   /* currentChat = {id, title, messages, temp?} */
var busy = false, isTempChat = false, activeController = null, sendSeq = 0, inlineEdit = null, editRestoreChat = null;
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
  var opt = { method: method, headers: { 'Content-Type': 'application/json' } };
  if (signal) { opt.signal = signal; }
  if (method === 'POST') { opt.body = JSON.stringify(body || {}); }
  /* NOTE: action may carry extra query params (chat_load&id=…) whose values
     are already encodeURIComponent'd by the caller — so don't re-encode here */
  return fetch('api.php' + (action ? '?action=' + action : ''), opt).then(function (r) {
    if (r.status === 401) { window.location.href = 'login.php'; throw new Error('signed out'); }
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
    var rb = last.querySelector('.retryAct');
    if (rb) { rb.hidden = false; }
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
  inlineEdit = { chatId: currentChat.id, index: index, original: text || '', el: msgEl, form: form, textarea: ta };
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
  if (bubble) { fillUserBubble(bubble, text, originalMsg.img, true); }
  var acts = state.el ? state.el.querySelector('.acts') : null;
  if (acts) { acts.style.display = 'none'; }
  while (state.el && state.el.nextSibling) { state.el.nextSibling.remove(); }

  busy = true;
  var seq = ++sendSeq;
  activeController = window.AbortController ? new AbortController() : null;
  resize();
  toast('Regenerating from edited message…', 'pencil');
  var th = addThinking();
  api('chat_edit', { id: state.chatId, message_index: state.index, message: text }, undefined, activeController ? activeController.signal : null).then(function (j) {
    if (seq !== sendSeq) { return; }
    th.remove();
    if (j.aborted) { toast('Edit paused', 'stop'); renderCurrentMessages(); return; }
    if (j.ok && j.chat) {
      editRestoreChat = null;
      isTempChat = false;
      currentChat = j.chat;
      currentChat.temp = false;
      currentChat.branch_groups = j.branch_groups || currentChat.branch_groups || {};
      if (window.history && currentChat.id) { history.replaceState(null, '', 'app.php?chat=' + encodeURIComponent(currentChat.id)); }
      renderCurrentMessages();
      loadChats();
      updateChatActions();
      toast('Edited branch created');
    } else if (j.ok && j.id) {
      editRestoreChat = null;
      window.location.href = 'app.php?chat=' + encodeURIComponent(j.id);
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
  api('chat_share', { id: currentChat.id }).then(function (j) {
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
  if (it) { window.location.href = 'app.php?chat=' + encodeURIComponent(it.dataset.id); }
});

/* ── messages ── */
var msgs = $('#msgs'), welcome = $('#welcome'), scroller = $('#scroller');
function scrollDown() { scroller.scrollTop = scroller.scrollHeight; }
function branchGroupFor(index) {
  if (index === undefined || index === null || !currentChat || !currentChat.branch_groups) { return null; }
  return currentChat.branch_groups[String(index)] || currentChat.branch_groups[index] || null;
}
function activeVariantIndex(variants) {
  var id = currentChat && currentChat.id;
  var n = variants.findIndex(function (v) { return v && v.id === id; });
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
  if (!id || (currentChat && currentChat.id === id)) { return; }
  if (busy) { toast('Pause the response before switching versions', 'warning'); return; }
  cancelInlineEdit(false);
  api('chat_load&id=' + encodeURIComponent(id)).then(function (j) {
    if (!j.ok) { toast(j.error || 'Could not open that version', 'warning'); return; }
    isTempChat = false;
    currentChat = j.chat;
    currentChat.temp = false;
    currentChat.branch_groups = j.branch_groups || currentChat.branch_groups || {};
    if (window.history && currentChat.id) { history.replaceState(null, '', 'app.php?chat=' + encodeURIComponent(currentChat.id)); }
    renderCurrentMessages();
    renderList($('#searchInp').value);
    updateChatActions();
    setTimeout(function () {
      var el = msgs.querySelector('[data-index="' + index + '"]');
      if (el && el.scrollIntoView) { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
    }, 60);
  });
}

function fillUserBubble(b, text, img, edited) {
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
  fillUserBubble(b, text, img, !!meta.edited);
  d.appendChild(b);
  var nav = makeBranchNav(meta.index, meta.branchGroup || branchGroupFor(meta.index));
  if (nav) { d.appendChild(nav); }
  if (text) {
    var acts = document.createElement('div');
    acts.className = 'acts';
    acts.appendChild(actionBtn(I.copy, 'Copy', 'Copy message', function () { copyText(text); }));
    var eb = actionBtn(I.pencil, 'Edit', 'Edit and regenerate from here', function () {
      beginEdit(meta.index, text, d);
    });
    if (meta.index === undefined || isTempChat) { eb.hidden = true; }
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

function aiContent(el, text, meta) {
  meta = meta || {};
  el.querySelector('.content').innerHTML = md(text);
  enhancePre(el);
  var acts = el.querySelector('.acts');
  acts.innerHTML = '';
  acts.appendChild(actionBtn(I.copy, 'Copy', 'Copy response', function () { copyText(text); }));
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
  refreshMessageActions();
  scrollDown();
}

function renderCurrentMessages() {
  msgs.innerHTML = '';
  var arr = (currentChat && currentChat.messages) ? currentChat.messages : [];
  welcome.style.display = arr.length ? 'none' : '';
  arr.forEach(function (m, idx) {
    if (!m || !m.role) { return; }
    if (m.role === 'user') { addUserMsg(m.content || '', m.img || '', { index: idx, edited: !!m.edited, branchGroup: branchGroupFor(idx) }); }
    else { var el = addAiMsg({ modelTag: m.model_label }); aiContent(el, m.content || '', { index: idx }); }
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
var inp = $('#inp'), sendBtn = $('#sendBtn');
function updateChatActions() {
  document.body.classList.toggle('temp-chat', isTempChat);
  var tb = $('#tempChatBtn');
  var showTemp = !busy && (!currentChat || isTempChat);
  tb.style.display = showTemp ? 'inline-flex' : 'none';
  tb.classList.toggle('on', isTempChat);
  tb.setAttribute('aria-pressed', isTempChat ? 'true' : 'false');
  tb.title = isTempChat ? 'Turn off temporary chat' : 'Turn on temporary chat';
  var tt = tb.querySelector('.txt');
  if (tt) { tt.textContent = isTempChat ? 'Temp on' : 'Temp'; }
  $('#topNewChatBtn').style.display = (currentChat && !isTempChat) ? 'inline-flex' : 'none';
}
function updateSendButton() {
  if (busy) {
    sendBtn.disabled = false;
    sendBtn.classList.add('stopmode');
    sendBtn.title = 'Pause response';
    sendBtn.innerHTML = I.stop;
  } else {
    sendBtn.classList.remove('stopmode');
    sendBtn.title = 'Send (Ctrl/⌘ + Enter)';
    sendBtn.innerHTML = I.send;
    sendBtn.disabled = (!inp.value.trim() && !pendingImg);
  }
  updateChatActions();
}
function resize() { inp.style.height = 'auto'; inp.style.height = Math.min(inp.scrollHeight, 190) + 'px'; updateSendButton(); }
function compactHistoryForTemp() {
  if (!currentChat || !currentChat.messages) { return []; }
  return currentChat.messages.slice(-18).map(function (m) { return { role: m.role, content: m.content || '' }; }).filter(function (m) { return m.content; });
}
function startTempChat(forceOn) {
  if (busy) { return; }
  clearEdit();
  if (isTempChat && forceOn !== true) { stopTempChat(); return; }
  isTempChat = true;
  currentChat = { id: null, title: 'Temporary chat', temp: true, messages: [] };
  msgs.innerHTML = '';
  welcome.style.display = '';
  if (window.history) { history.replaceState(null, '', 'app.php?temp=1'); }
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
  if (window.history) { history.replaceState(null, '', 'app.php'); }
  renderList($('#searchInp').value);
  updateChatActions();
  toast('Temporary chat off — new chats will be saved');
  if (window.innerWidth > 900) { inp.focus(); }
}
function pauseSend() {
  if (!busy) { return; }
  sendSeq++;
  if (activeController) { try { activeController.abort(); } catch (e) {} }
  busy = false;
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

/* ── image attach ── */
var pendingImg = null;   /* data URL */
var fileInput = $('#fileInput'), imgChip = $('#imgChip');
var IMG_TYPES = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];

function handleFile(file) {
  if (!file || busy) { return; }
  if (IMG_TYPES.indexOf(file.type) === -1) { toast('Only PNG, JPEG, GIF or WebP images', 'warning'); return; }
  if (file.size > 8 * 1024 * 1024) { toast('Image too large (max 8 MB)', 'warning'); return; }
  var fr = new FileReader();
  fr.onload = function () {
    downscale(fr.result, file.type, function (dataUrl) {
      pendingImg = dataUrl;
      showChip(file.name, dataUrl.length);
      resize();
    });
  };
  fr.readAsDataURL(file);
}

/* shrink to ≤1024px / JPEG so chats stay light; small originals pass through */
function downscale(dataUrl, type, cb) {
  var im = new Image();
  im.onload = function () {
    var small = im.width <= 1400 && im.height <= 1400 && dataUrl.length < 300000;
    if (small) { cb(dataUrl); return; }
    var s = Math.min(1024 / Math.max(im.width, im.height), 1);
    var cv = document.createElement('canvas');
    cv.width = Math.max(1, Math.round(im.width * s));
    cv.height = Math.max(1, Math.round(im.height * s));
    cv.getContext('2d').drawImage(im, 0, 0, cv.width, cv.height);
    cb(cv.toDataURL('image/jpeg', 0.85));
  };
  im.onerror = function () { toast('Could not read that image', 'warning'); };
  im.src = dataUrl;
}

function showChip(name, chars) {
  var kb = Math.round(chars * 0.75 / 1024);
  imgChip.innerHTML = '';
  var th = document.createElement('img');
  th.src = pendingImg; th.alt = '';
  var meta = document.createElement('div');
  meta.className = 'meta';
  var b = document.createElement('b');
  b.textContent = name || 'image';
  var sz = document.createElement('span');
  sz.textContent = kb + ' KB — attached';
  meta.appendChild(b); meta.appendChild(sz);
  var rm = document.createElement('button');
  rm.className = 'rm'; rm.type = 'button'; rm.title = 'Remove image';
  rm.innerHTML = I.x;
  rm.addEventListener('click', function () { pendingImg = null; imgChip.classList.remove('show'); imgChip.innerHTML = ''; resize(); });
  imgChip.appendChild(th); imgChip.appendChild(meta); imgChip.appendChild(rm);
  imgChip.classList.add('show');
}

$('#attachBtn').addEventListener('click', function () { if (!busy) { fileInput.click(); } });
fileInput.addEventListener('change', function () { handleFile(fileInput.files[0]); fileInput.value = ''; });
document.addEventListener('paste', function (e) {
  if (busy) { return; }
  var items = (e.clipboardData || {}).items || [];
  for (var i = 0; i < items.length; i++) {
    if (items[i].type && items[i].type.indexOf('image/') === 0) {
      var f = items[i].getAsFile();
      if (f) { e.preventDefault(); handleFile(f); }
      return;
    }
  }
});
inp.addEventListener('input', resize);
inp.addEventListener('keydown', function (e) {
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); send(); }
});
sendBtn.addEventListener('click', function () { if (busy) { pauseSend(); } else { send(); } });
$('#tempChatBtn').addEventListener('click', startTempChat);
$('#topNewChatBtn').addEventListener('click', function () { if (busy) { pauseSend(); } window.location.href = 'app.php'; });
$('#editCancel').addEventListener('click', function () { clearEdit(); inp.value = ''; resize(); inp.focus(); });

function newChatView() {
  clearEdit();
  isTempChat = false;
  currentChat = null;
  msgs.innerHTML = '';
  welcome.style.display = '';
  renderList($('#searchInp').value);
  updateChatActions();
  if (window.innerWidth > 900) { inp.focus(); }
}
$('#newChatBtn').addEventListener('click', function () { window.location.href = 'app.php'; });

$$('#welcome .card').forEach(function (c) {
  c.addEventListener('click', function () { inp.value = c.dataset.fill; resize(); send(); });
});

/* ── send / retry ── */
function send() {
  if (inlineEdit) { toast('Save or cancel the edited message first', 'warning'); return; }
  var text = inp.value.trim();
  var img = pendingImg;
  if ((!text && !img) || busy) { return; }
  inp.value = ''; pendingImg = null; imgChip.classList.remove('show'); imgChip.innerHTML = '';
  resize();
  addUserMsg(text, img);
  var payload = { message: text, model: currentModel, id: (currentChat && !isTempChat) ? currentChat.id : null };
  if (isTempChat) { payload.temp = true; payload.history = compactHistoryForTemp(); }
  if (currentModel === 'custom') { payload.custom_model = currentCustom; }
  if (img) { payload.image = img; }
  runSend(payload);
}

function retryLast() {
  if (busy || !currentChat) { return; }
  var m = currentChat.messages;
  if (!m.length || m[m.length - 1].role !== 'assistant') { return; }
  /* drop last assistant message visually + in memory */
  m.pop();
  if (msgs.lastElementChild && msgs.lastElementChild.classList.contains('msg-ai')) { msgs.lastElementChild.remove(); }
  var payload = { id: isTempChat ? null : currentChat.id, retry: true, model: currentModel, custom_model: currentModel === 'custom' ? currentCustom : undefined };
  if (isTempChat) { payload.temp = true; payload.history = compactHistoryForTemp(); }
  runSend(payload);
}

function runSend(payload) {
  busy = true;
  var seq = ++sendSeq;
  activeController = window.AbortController ? new AbortController() : null;
  resize();
  var th = addThinking();
  api('chat_send', payload, undefined, activeController ? activeController.signal : null).then(function (j) {
    if (seq !== sendSeq) { return; }
    th.remove();
    if (j.aborted) { toast('Response paused', 'stop'); return; }
    if (j.ok) {
      if (!currentChat) { currentChat = { id: j.id || null, title: j.title || 'New chat', temp: !!payload.temp, messages: [], branch_groups: {} }; }
      if (!currentChat.branch_groups) { currentChat.branch_groups = {}; }
      isTempChat = !!(payload.temp || j.temp || currentChat.temp);
      currentChat.temp = isTempChat;
      currentChat.id = isTempChat ? null : j.id;
      currentChat.title = isTempChat ? 'Temporary chat' : j.title;
      if (window.history && currentChat.id) { history.replaceState(null, '', 'app.php?chat=' + encodeURIComponent(currentChat.id)); }
      if (payload.retry) {
        /* keep existing user msg, replace assistant */
      } else {
        var um = { role: 'user', content: payload.message };
        if (payload.image) { um.img = payload.image; }
        currentChat.messages.push(um);
      }
      currentChat.messages.push({ role: 'assistant', content: j.reply, model_label: (j.model && j.model.label) || activeModelLabel() });
      var aiIndex = currentChat.messages.length - 1;
      var el = addAiMsg({ modelTag: (j.model && j.model.label) || activeModelLabel() });
      aiContent(el, j.reply, { index: aiIndex });
      if (!isTempChat) { loadChats(); }
      updateChatActions();
    } else {
      if (payload.retry) { /* put a placeholder assistant error, keep chat usable */ }
      addErr(j.error + (j.hint ? '\nHint: ' + j.hint : ''));
    }
  }).finally(function () {
    if (seq === sendSeq) {
      busy = false;
      activeController = null;
      resize();
    }
  });
}

/* ── open / delete / rename chats ── */
function openChat(id) {
  if (busy) { return; }
  clearEdit();
  api('chat_load&id=' + encodeURIComponent(id)).then(function (j) {
    if (!j.ok) { toast(j.error || 'Could not open chat', 'warning'); return; }
    isTempChat = false;
    currentChat = j.chat;
    currentChat.temp = false;
    currentChat.branch_groups = j.branch_groups || currentChat.branch_groups || {};
    renderCurrentMessages();
    renderList($('#searchInp').value);
    updateChatActions();
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
$('#modelBtn').addEventListener('click', function (e) { e.stopPropagation(); $('#customModelMenu').classList.remove('open'); $('#modelMenu').classList.toggle('open'); });
$('#customModelBtn').addEventListener('click', function (e) {
  e.stopPropagation();
  $('#modelMenu').classList.remove('open');
  renderCustomModelMenu('');
  $('#customModelMenu').classList.toggle('open');
  setTimeout(function () { var s = $('#customModelMenu .csearch'); if (s) { s.focus(); } }, 20);
});
document.addEventListener('click', function (e) {
  if (!e.target.closest('#modelMenu') && !e.target.closest('#modelBtn')) { $('#modelMenu').classList.remove('open'); }
  if (!e.target.closest('#customModelMenu') && !e.target.closest('#customModelBtn')) { $('#customModelMenu').classList.remove('open'); }
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
  setModelBtn();
  renderModelMenu();
  renderCustomModelMenu('');
});
loadChats().then(function () {
  if (INITIAL_CHAT_ID) { openChat(INITIAL_CHAT_ID); }
  else if (new URLSearchParams(window.location.search).get('temp') === '1') { startTempChat(true); }
  else { updateChatActions(); }
});
resize();
if (window.innerWidth > 900) { inp.focus(); }
})();
</script>
</body>
</html>
