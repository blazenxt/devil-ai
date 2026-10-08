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
$APP_BASE_PATH = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/app.php'))), '/');
if ($APP_BASE_PATH === '.' || $APP_BASE_PATH === '/') { $APP_BASE_PATH = ''; }
if (!$me) { header('Location: ' . ($APP_BASE_PATH ?: '') . '/login.php'); exit; }
/* /agent and /agent/{slug} are rewritten to app.php?mode=agent */
$ROUTE_MODE = in_array((string)($_GET['mode'] ?? ''), ['agent', 'battle', 'sbs'], true) ? (string)$_GET['mode'] : 'ai';
/* Pro-style modes (order matches the reference site): Battle, Agent, Side by Side, Direct (AI Mode) */
$MODE_DEFS = [
    'battle' => ['label' => 'Battle Mode',  'chip' => 'Battle',       'icon' => 'swords',  'desc' => 'Battle 2 anonymous models — vote, then names are revealed'],
    'agent'  => ['label' => 'Agent Mode',   'chip' => 'Agent',        'icon' => 'spark',   'desc' => 'Built for complex tasks — searches the web, reads pages, calculates'],
    'sbs'    => ['label' => 'Side by Side', 'chip' => 'Side by Side', 'icon' => 'columns', 'desc' => 'Compare 2 models of your choice'],
    'ai'     => ['label' => 'AI Mode',      'chip' => 'Direct',       'icon' => 'message', 'desc' => 'Direct — chat with 1 model at a time'],
];
$IS_CMP = ($ROUTE_MODE === 'battle' || $ROUTE_MODE === 'sbs');
function mode_options_html(array $defs, string $cur): string {
    $h = '';
    foreach ($defs as $id => $d) {
        $on = $id === $cur;
        $h .= '<button class="modeopt' . ($on ? ' on' : '') . '" type="button" role="menuitemradio" data-mode="' . $id . '" aria-checked="' . ($on ? 'true' : 'false') . '">'
            . '<span class="mic">' . icon($d['icon'], 17) . '</span><span class="mtx"><b>' . htmlspecialchars($d['label']) . '</b><small>' . htmlspecialchars($d['desc']) . '</small></span>'
            . '<span class="mck">' . icon('check', 16) . '</span></button>';
    }
    return $h;
}

$JS_ICONS = [
    'send' => icon('send', 17), 'stop' => icon('stop', 17), 'copy' => icon('copy', 15), 'retry' => icon('retry', 15),
    'thumbUp' => icon('thumb-up', 15), 'thumbDown' => icon('thumb-down', 15), 'share' => icon('share', 15), 'paperclip' => icon('paperclip', 15), 'mic' => icon('mic', 15), 'micOff' => icon('mic-off', 15), 'volume' => icon('volume', 15), 'volumeX' => icon('volume-x', 15),
    'trash' => icon('trash', 15), 'pencil' => icon('pencil', 14), 'check' => icon('check', 15),
    'x' => icon('x', 16), 'chev' => icon('chevron-down', 14), 'zap' => icon('zap', 15),
    'sparkles' => icon('sparkles', 15), 'crown' => icon('crown', 15), 'ghost' => icon('ghost', 15),
    'user' => icon('user', 16), 'logout' => icon('logout', 16), 'settings' => icon('settings', 16),
    'panel' => icon('panel-left', 18), 'newchat' => icon('square-pen', 17), 'search' => icon('search', 15),
    'loader' => icon('loader', 16), 'warning' => icon('warning', 16), 'lock' => icon('lock', 16),
    'message' => icon('message', 16), 'menu' => icon('menu', 18), 'flame' => icon('flame', 15),
    'lightbulb' => icon('lightbulb', 17), 'shield' => icon('shield', 16), 'code' => icon('code', 16), 'gauge' => icon('gauge', 16),
    'sun' => icon('sun', 17), 'moon' => icon('moon', 17), 'play' => icon('play', 13),
    'globe' => icon('globe', 14), 'spark' => icon('spark', 15), 'calc' => icon('calculator', 14), 'clock' => icon('clock', 14),
    'link' => icon('link', 14), 'fileText' => icon('file-text', 14), 'brainS' => icon('brain', 14), 'chevR' => icon('chevron-right', 13),
    'layers' => icon('layers', 15), 'brain' => icon('brain', 15), 'server' => icon('server', 15),
    'swords' => icon('swords', 15), 'columns' => icon('columns', 15), 'trophy' => icon('trophy', 16), 'maximize' => icon('maximize', 14),
    'shuffle' => icon('shuffle', 15), 'arrowL' => icon('arrow-left', 15), 'arrowR' => icon('arrow-right', 15), 'arrowD' => icon('arrow-down', 17),
    'equal' => icon('equal', 15), 'sparkM' => icon('spark', 17), 'swordsM' => icon('swords', 17), 'columnsM' => icon('columns', 17), 'messageM' => icon('message', 15),
    'swordsS' => icon('swords', 13), 'columnsS' => icon('columns', 13), 'sparkS' => icon('spark', 13), 'chevS' => icon('chevron-down', 13),
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<base href="<?= htmlspecialchars(($APP_BASE_PATH ?: '') . '/', ENT_QUOTES) ?>">
<meta name="theme-color" content="#0c0709">
<meta name="robots" content="noindex">
<script>/* theme boot — runs before paint to avoid a flash of the wrong theme */
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();</script>
<title>Devil AI — Chat</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="manifest" href="manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Devil AI">
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
html,body{height:100%;overflow-x:hidden}
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
main{flex:1;display:flex;flex-direction:column;min-width:0;position:relative;overflow:hidden;overflow:clip;background:radial-gradient(1000px 500px at 70% -10%,rgba(225,29,72,.07),transparent 55%),var(--bg)}
.m-top{display:none;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border);background:rgba(12,7,9,.85);backdrop-filter:blur(10px)}
.m-top .brand{display:flex;align-items:center;gap:8px;font-weight:700;flex:1}
.m-top .brand img{width:24px;height:24px}
.chattools{position:absolute;top:14px;right:18px;z-index:25;display:flex;align-items:center;gap:9px;padding:6px;border:1px solid var(--border);background:rgba(23,16,20,.72);backdrop-filter:blur(14px);border-radius:18px;box-shadow:0 14px 40px rgba(0,0,0,.22)}
.ctbtn{height:36px;min-width:36px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;gap:8px;color:var(--dim);padding:0 11px;font-size:.8rem;font-weight:700;transition:.16s;border:1px solid transparent;white-space:nowrap}
.ctbtn:hover{background:rgba(244,63,94,.12);color:var(--soft);border-color:var(--border)}
.ctbtn.primary{background:linear-gradient(135deg,rgba(244,63,94,.18),rgba(190,18,60,.12));color:var(--soft);border-color:var(--border)}
.ctbtn.on{background:rgba(244,63,94,.22);color:#fff;border-color:var(--border-hi);box-shadow:0 0 0 3px rgba(244,63,94,.08)}
.modesw{position:absolute;top:14px;left:18px;z-index:26}
.modebtn{height:48px;display:inline-flex;align-items:center;gap:9px;padding:0 12px 0 10px;border:1px solid var(--border);background:rgba(23,16,20,.72);backdrop-filter:blur(14px);border-radius:16px;box-shadow:0 14px 40px rgba(0,0,0,.22);color:var(--text);font-weight:800;font-size:.95rem;letter-spacing:.01em;transition:.16s}
.modebtn:hover,.modesw.open .modebtn{border-color:var(--border-hi);background:rgba(244,63,94,.10)}
.modebtn img{width:24px;height:24px;filter:drop-shadow(0 0 7px rgba(244,63,94,.45))}
.modebtn .mtag{font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;padding:3px 7px;border-radius:999px;background:rgba(244,63,94,.14);color:var(--soft);border:1px solid var(--border)}
.modesw.agent .modebtn .mtag{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;border-color:transparent}
.modebtn .mchev{display:inline-flex;color:var(--dim);transition:transform .18s}
.modesw.open .modebtn .mchev{transform:rotate(180deg)}
.modemenu{position:absolute;top:calc(100% + 8px);left:0;width:300px;max-width:calc(100vw - 28px);background:var(--panel2);border:1px solid var(--border-hi);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.6);padding:6px;display:none;z-index:80}
.modesw.open .modemenu{display:block;animation:rise .16s ease}
.modemenu .mhead{padding:8px 10px 6px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--dim2)}
.modeopt{width:100%;display:flex;align-items:center;gap:11px;padding:10px;border-radius:11px;text-align:left;color:var(--text);transition:.12s}
.modeopt:hover{background:rgba(244,63,94,.10)}
.modeopt.on{background:rgba(244,63,94,.14)}
.modeopt .mic{width:34px;height:34px;flex-shrink:0;border-radius:10px;display:flex;align-items:center;justify-content:center;background:rgba(244,63,94,.12);color:var(--soft)}
.modeopt.on .mic{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff}
.modeopt .mtx{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
.modeopt .mtx b{font-size:.86rem}
.modeopt .mtx small{font-size:.72rem;color:var(--dim);line-height:1.35}
.modeopt .mck{display:none;color:var(--soft)}
.modeopt.on .mck{display:inline-flex}
.modeopt[disabled]{opacity:.45;cursor:not-allowed}
.agent-trace{margin:0 0 10px;border:1px solid var(--border);border-radius:12px;background:var(--panel2);overflow:hidden;font-size:.74rem}
.agent-head{padding:8px 12px;font-weight:700;color:var(--soft);border-bottom:1px solid var(--border)}
.agent-step{padding:7px 12px;border-bottom:1px solid var(--border);color:var(--dim);display:flex;flex-direction:column;gap:2px}
.agent-step:last-child{border-bottom:none}
.agent-tool{color:var(--text);font-weight:700;display:flex;align-items:center;gap:6px}
.agent-io{color:var(--dim2);word-break:break-word}
.agent-err{color:#fca5a5;font-weight:700}
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
body.loading-chat #welcome{display:none!important}
body.loading-chat #thread:after{content:'Loading chat…';display:block;margin:18vh auto 0;width:max-content;max-width:90%;color:var(--dim);font-size:.86rem;border:1px solid var(--border);background:var(--panel);border-radius:999px;padding:9px 14px;box-shadow:0 12px 32px rgba(0,0,0,.18)}
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
#sendBtn,#quickVoiceBtn{margin-left:auto;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:.15s;flex-shrink:0;position:relative}
#sendBtn{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;box-shadow:0 5px 16px rgba(244,63,94,.4)}
#quickVoiceBtn{background:rgba(244,63,94,.09);color:var(--soft);border:1px solid var(--border);box-shadow:none}
#sendBtn:hover,#quickVoiceBtn:hover{transform:scale(1.06)}
#quickVoiceBtn:hover{background:rgba(244,63,94,.15);border-color:var(--border-hi);box-shadow:0 5px 16px rgba(244,63,94,.16)}
#sendBtn:disabled,#quickVoiceBtn:disabled{opacity:.45;transform:none;cursor:default}
#sendBtn.stopmode{background:rgba(190,18,60,.22);border:1px solid rgba(248,113,113,.45);color:#fecaca;box-shadow:none}
#quickVoiceBtn.on{background:rgba(244,63,94,.16);color:#fecdd3;border-color:rgba(244,63,94,.36);box-shadow:0 0 0 3px rgba(244,63,94,.08)}
#quickVoiceBtn.listening:after{content:'';position:absolute;inset:-3px;border-radius:50%;border:1px solid rgba(244,63,94,.55);animation:voice-pulse 1.25s infinite}
#quickVoiceBtn.speaking{color:#fff;background:linear-gradient(135deg,#f43f5e,#be123c);border-color:transparent;box-shadow:0 5px 16px rgba(244,63,94,.32)}
#sendBtn[hidden],#quickVoiceBtn[hidden]{display:none!important}
.hint{text-align:center;font-size:.68rem;color:var(--dim2);margin-top:9px}
#attachBtn,#voiceBtn,#promptBtn{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--dim);transition:.15s;flex-shrink:0;position:relative}
#attachBtn:hover,#voiceBtn:hover,#promptBtn:hover{background:rgba(244,63,94,.12);color:var(--soft)}
#voiceBtn.on{background:rgba(244,63,94,.16);color:var(--soft);box-shadow:0 0 0 3px rgba(244,63,94,.08)}
#voiceBtn.listening:after{content:'';position:absolute;inset:-3px;border-radius:50%;border:1px solid rgba(244,63,94,.55);animation:voice-pulse 1.25s infinite}
#voiceBtn.speaking{color:#fff;background:linear-gradient(135deg,#f43f5e,#be123c);box-shadow:0 5px 16px rgba(244,63,94,.32)}
#voiceChip{display:none;align-items:center;justify-content:space-between;gap:10px;margin:12px 16px 0;padding:9px 11px;background:rgba(244,63,94,.08);border:1px solid var(--border);border-radius:12px;color:var(--soft);font-size:.76rem;line-height:1.45}
#voiceChip.show{display:flex}
#voiceChip span{display:flex;align-items:center;gap:7px;min-width:0}
#voiceChip button{font-size:.72rem;font-weight:700;color:var(--dim);padding:5px 8px;border-radius:8px;flex-shrink:0}
#voiceChip button:hover{background:rgba(244,63,94,.12);color:var(--soft)}
@keyframes voice-pulse{0%{transform:scale(.92);opacity:.9}100%{transform:scale(1.35);opacity:0}}
body.voice-open{overflow:hidden}
#voiceLive{position:fixed;inset:0;z-index:520;display:none;align-items:center;justify-content:center;padding:max(18px,env(safe-area-inset-top)) 18px max(18px,env(safe-area-inset-bottom));background:radial-gradient(700px 420px at 50% 28%,rgba(244,63,94,.22),transparent 62%),rgba(5,2,4,.88);backdrop-filter:blur(16px);color:var(--text);overflow:auto}
#voiceLive.show{display:flex}
.voiceShell{width:min(520px,100%);height:min(720px,calc(100svh - 44px));min-height:min(560px,calc(100svh - 44px));max-height:calc(100svh - 44px);display:flex;flex-direction:column;align-items:center;justify-content:space-between;gap:18px;background:linear-gradient(180deg,rgba(39,24,31,.88),rgba(16,10,13,.9));border:1px solid rgba(244,63,94,.22);border-radius:32px;padding:22px;box-shadow:0 35px 110px rgba(0,0,0,.62);position:relative;overflow:hidden}
.voiceShell:before{content:'';position:absolute;inset:-40% -20% auto;height:58%;background:radial-gradient(circle,rgba(244,63,94,.18),transparent 62%);pointer-events:none}
.voiceHead{position:relative;z-index:1;width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px}
.voiceHead .vt{min-width:0}.voiceHead b{display:block;font-size:.95rem;letter-spacing:.2px}.voiceHead span{display:block;color:var(--dim2);font-size:.72rem;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.voiceCloseTop{width:38px;height:38px;border-radius:50%;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--dim);transition:.15s;flex-shrink:0}.voiceCloseTop:hover{background:rgba(244,63,94,.14);color:var(--soft);border-color:var(--border-hi)}
.voiceStage{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;flex:1;width:100%;min-height:230px}
.voiceOrb{width:180px;height:180px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;background:radial-gradient(circle at 35% 30%,#fb7185,#e11d48 45%,#7f1d1d 78%);box-shadow:0 0 0 1px rgba(255,255,255,.08) inset,0 20px 70px rgba(244,63,94,.32);position:relative;transition:.25s ease;isolation:isolate}
.voiceOrb svg{width:42px;height:42px;filter:drop-shadow(0 6px 18px rgba(0,0,0,.35))}.voiceOrb:before,.voiceOrb:after{content:'';position:absolute;inset:-18px;border-radius:50%;border:1px solid rgba(251,113,133,.38);opacity:.45;z-index:-1}.voiceOrb:after{inset:-34px;opacity:.2}
#voiceLive.listening .voiceOrb{animation:orb-breathe 1.4s ease-in-out infinite;box-shadow:0 0 0 1px rgba(255,255,255,.08) inset,0 0 70px rgba(244,63,94,.52)}
#voiceLive.listening .voiceOrb:before{animation:voice-pulse 1.25s infinite}#voiceLive.listening .voiceOrb:after{animation:voice-pulse 1.25s .22s infinite}
#voiceLive.speaking .voiceOrb{background:radial-gradient(circle at 35% 30%,#fda4af,#f43f5e 48%,#9f1239 80%);animation:orb-speak .72s ease-in-out infinite alternate}
#voiceLive.thinking .voiceOrb{animation:orb-think 1s linear infinite}#voiceLive.muted .voiceOrb{filter:grayscale(.55);opacity:.72;animation:none}
@keyframes orb-breathe{0%,100%{transform:scale(.98)}50%{transform:scale(1.04)}}@keyframes orb-speak{from{transform:scale(.98) rotate(-1deg)}to{transform:scale(1.07) rotate(1deg)}}@keyframes orb-think{from{transform:rotate(0)}to{transform:rotate(360deg)}}
.voiceState{text-align:center;font-weight:800;font-size:1.1rem;margin:0}.voiceHint{text-align:center;color:var(--dim2);font-size:.78rem;line-height:1.55;max-width:360px;margin:-8px 0 0}
.voiceTranscript{position:relative;z-index:1;width:100%;display:grid;gap:10px}.voiceLine{border:1px solid var(--border);background:rgba(255,255,255,.04);border-radius:16px;padding:11px 13px;min-height:70px}.voiceLine span{display:block;color:var(--soft);font-size:.68rem;font-weight:900;text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px}.voiceLine p{margin:0;color:var(--text);font-size:.86rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.voiceLine p.ghost{color:var(--dim2)}
.voiceControls{position:relative;z-index:1;display:flex;align-items:center;justify-content:center;gap:12px;width:100%;flex-wrap:wrap}.voiceCtl{min-width:88px;border:1px solid var(--border);border-radius:18px;padding:10px 13px;display:flex;align-items:center;justify-content:center;gap:8px;color:var(--soft);background:rgba(255,255,255,.04);font-weight:800;font-size:.78rem;transition:.15s}.voiceCtl:hover:not(:disabled){transform:translateY(-1px);background:rgba(244,63,94,.12);border-color:var(--border-hi)}.voiceCtl:disabled{opacity:.45;cursor:default}.voiceCtl.on{background:rgba(244,63,94,.16);border-color:rgba(244,63,94,.35);color:#fecdd3}.voiceCtl.end{min-width:60px;width:58px;height:58px;border-radius:50%;padding:0;background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;border:none;box-shadow:0 12px 32px rgba(244,63,94,.35)}
@media(max-width:680px){#voiceLive{padding:0}.voiceShell{height:100svh;min-height:0;max-height:none;border-radius:0;border-left:none;border-right:none;padding:18px 16px max(18px,env(safe-area-inset-bottom));gap:14px}.voiceOrb{width:142px;height:142px}.voiceStage{min-height:205px;gap:12px}.voiceState{font-size:1rem}.voiceHint{font-size:.73rem}.voiceHead span{max-width:240px}.voiceTranscript{gap:8px}.voiceCtl{min-width:82px}.voiceLine{min-height:58px;padding:9px 11px}.voiceLine p{-webkit-line-clamp:2}}
#imgChip{display:none;align-items:stretch;gap:8px;margin:12px 16px 0;max-width:calc(100% - 32px);flex-wrap:wrap}
#imgChip.show{display:flex}
.filechip{display:flex;align-items:center;gap:10px;padding:7px 10px;background:var(--panel2);border:1px solid var(--border);border-radius:12px;max-width:270px;min-width:0}
.filechip img,.filechip .fic{width:42px;height:42px;object-fit:cover;border-radius:8px;flex-shrink:0;background:rgba(244,63,94,.1);display:flex;align-items:center;justify-content:center;color:var(--soft)}
.filechip .meta{font-size:.68rem;color:var(--dim2);min-width:0;line-height:1.5}
.filechip .meta b{display:block;font-size:.76rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:165px}
.filechip .rm{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--dim2);flex-shrink:0}
.filechip .rm:hover{background:rgba(190,18,60,.15);color:var(--pink)}
.msg-files{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px;justify-content:flex-end}
.msg-file{display:inline-flex;align-items:center;gap:7px;max-width:230px;border:1px solid var(--border);background:rgba(244,63,94,.08);color:var(--soft);border-radius:999px;padding:5px 9px;font-size:.7rem;font-weight:700}
.msg-file span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
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
#promptMenu{position:absolute;bottom:calc(100% + 8px);left:0;width:330px;max-width:calc(100vw - 44px);background:var(--panel2);border:1px solid var(--border-hi);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.6);padding:7px;display:none;z-index:78}
#promptMenu.open{display:block}
.popt{display:flex;gap:10px;align-items:flex-start;width:100%;padding:10px 11px;border-radius:11px;text-align:left;transition:.12s;color:var(--text)}
.popt:hover{background:rgba(244,63,94,.1)}
.popt .ic{width:30px;height:30px;border-radius:9px;background:rgba(244,63,94,.1);display:flex;align-items:center;justify-content:center;color:var(--pink);flex-shrink:0}
.popt b{display:block;font-size:.82rem}.popt span{display:block;font-size:.7rem;color:var(--dim2);line-height:1.4;margin-top:1px}
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
@media(max-width:680px){#customModelBtn{max-width:150px}#customModelMenu,#promptMenu{left:auto;right:0;width:340px}.modelwrap{gap:6px}#modelBtn{max-width:150px}}

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
  .m-top .brand{visibility:hidden}
  .modesw{top:9px;left:54px}
  .modebtn{height:38px;gap:7px;padding:0 9px 0 8px;border-radius:12px;font-size:.88rem;box-shadow:none;background:transparent;border-color:transparent;backdrop-filter:none}
  .modebtn img{width:22px;height:22px}
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
[data-theme=light] .modebtn{background:rgba(255,255,255,.86);box-shadow:0 14px 40px rgba(120,80,90,.14)}
[data-theme=light] .modebtn:hover,[data-theme=light] .modesw.open .modebtn{background:rgba(190,30,60,.07);border-color:rgba(190,30,60,.3)}
[data-theme=light] .modebtn .mtag{background:rgba(190,30,60,.08);color:#a63d57;border-color:rgba(190,30,60,.2)}
[data-theme=light] .modesw.agent .modebtn .mtag{background:linear-gradient(135deg,#e11d48,#be123c);color:#fff}
[data-theme=light] .modemenu{box-shadow:0 18px 50px rgba(120,80,90,.18)}
[data-theme=light] .modeopt .mic{background:rgba(190,30,60,.08);color:#a63d57}
@media (max-width:900px){[data-theme=light] .modebtn{background:transparent;box-shadow:none;border-color:transparent}}
[data-theme=light] .chattools{background:rgba(255,255,255,.86);box-shadow:0 14px 40px rgba(120,80,90,.14)}
[data-theme=light] .ctbtn{color:#6e5f65}
[data-theme=light] .ctbtn:hover{background:rgba(190,30,60,.09);color:#a63d57;border-color:rgba(190,30,60,.22)}
[data-theme=light] .ctbtn.primary{background:rgba(190,30,60,.08);color:#a63d57;border-color:rgba(190,30,60,.20)}
[data-theme=light] .ctbtn.on{background:rgba(190,30,60,.16);color:#7f1d1d;border-color:rgba(190,30,60,.38);box-shadow:0 0 0 3px rgba(190,30,60,.08)}
[data-theme=light] .ctbtn.stop,[data-theme=light] #sendBtn.stopmode{background:rgba(190,18,60,.10);border-color:rgba(190,18,60,.32);color:#9f1239;box-shadow:none}
[data-theme=light] #editChip,[data-theme=light] #voiceChip{background:rgba(190,30,60,.07);border-color:rgba(190,30,60,.20);color:#9f1239}
[data-theme=light] #voiceLive{background:radial-gradient(700px 420px at 50% 28%,rgba(190,30,60,.14),transparent 62%),rgba(250,249,247,.78)}
[data-theme=light] .voiceShell{background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(244,242,238,.94));border-color:rgba(190,30,60,.22);box-shadow:0 30px 90px rgba(120,80,90,.22)}
[data-theme=light] .voiceHead b,[data-theme=light] .voiceState,[data-theme=light] .voiceLine p{color:#262023}
[data-theme=light] .voiceHead span,[data-theme=light] .voiceHint,[data-theme=light] .voiceLine p.ghost{color:#82696f}
[data-theme=light] .voiceLine{background:rgba(190,30,60,.045);border-color:rgba(120,80,90,.18)}
[data-theme=light] .voiceCtl{background:rgba(190,30,60,.055);border-color:rgba(120,80,90,.18);color:#a63d57}
[data-theme=light] .voiceCtl:hover:not(:disabled),[data-theme=light] .voiceCtl.on{background:rgba(190,30,60,.12);border-color:rgba(190,30,60,.35);color:#9f1239}
[data-theme=light] .voiceCtl.end{background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;border:none}
[data-theme=light] .voiceCloseTop{background:rgba(255,255,255,.55);border-color:rgba(120,80,90,.18);color:#6e5f65}
[data-theme=light] .voiceCloseTop:hover{background:rgba(190,30,60,.1);color:#9f1239;border-color:rgba(190,30,60,.3)}
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
[data-theme=light] #userMenu,[data-theme=light] #modelMenu,[data-theme=light] #customModelMenu,[data-theme=light] #promptMenu{box-shadow:0 18px 50px rgba(120,80,90,.18)}
[data-theme=light] #sendBtn.stopmode svg{color:#9f1239}
[data-theme=light] #quickVoiceBtn{background:rgba(190,30,60,.07);border-color:rgba(190,30,60,.20);color:#a63d57}
[data-theme=light] #quickVoiceBtn:hover,[data-theme=light] #quickVoiceBtn.on{background:rgba(190,30,60,.12);border-color:rgba(190,30,60,.34);color:#9f1239}
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

/* ═══════════ PRO-STYLE UI (AI Mode + Agent Mode) ═══════════ */
body.devil-ui{
  --bg:#262624; --bg2:#2a2a28; --sb:#262624; --panel:#30302e; --panel2:#2c2c2a; --panel3:#3a3a37;
  --border:rgba(255,255,255,.1); --border-hi:rgba(255,255,255,.22);
  --text:#ecebe7; --dim:#a8a59e; --dim2:#85827b; --soft:#e6e3dc; --pink:#e6e3dc;
  --ag-accent:#f43f5e; --ag-head:#d6d2c9; --ag-line:rgba(255,255,255,.11); --ag-hover:rgba(255,255,255,.06); --ag-user:#353532;
  --ag-mark:#f43f5e; --ag-mark-text:#1c1b1a;
  background:var(--bg)
}
[data-theme=light] body.devil-ui{
  --bg:#faf9f7; --bg2:#f5f4f0; --sb:#faf9f7; --panel:#ffffff; --panel2:#f3f1ec; --panel3:#ebe8e2;
  --border:#e6e3dc; --border-hi:#cfcac0;
  --text:#2f2d29; --dim:#77726a; --dim2:#9a958c; --soft:#3d3a35; --pink:#3d3a35;
  --ag-accent:#e11d48; --ag-head:#615c54; --ag-line:#e6e3dc; --ag-hover:rgba(0,0,0,.035); --ag-user:#f0eee9;
  --ag-mark:#fb7185; --ag-mark-text:#1f1d1a;
  background:var(--bg)
}
body.devil-ui main{background:var(--bg)}
body.devil-ui ::-webkit-scrollbar-thumb{background:var(--border-hi)}
body.devil-ui #sidebar{background:var(--sb);border-right-color:var(--border)}
body.devil-ui .newchat{background:var(--panel);border-color:var(--border);color:var(--text)}
body.devil-ui .newchat:hover{background:var(--panel2);border-color:var(--border-hi)}
body.devil-ui .chatitem:hover{background:var(--ag-hover)}
body.devil-ui .chatitem.on{background:var(--panel3)}
body.devil-ui .sb-search{background:var(--panel);border-color:var(--border)}
body.devil-ui .userbtn .av{background:var(--panel3);color:var(--text)}

/* top bar */
body.devil-ui .m-top{display:flex;height:52px;padding:0 14px;background:var(--bg)!important;border-bottom:1px solid var(--border);backdrop-filter:none}
body.devil-ui:not(.agent-mode).agent-empty .m-top{border-bottom-color:transparent}
body.devil-ui .m-top .brand{visibility:hidden}
@media (min-width:901px){ body.devil-ui #sbOpen,body.devil-ui #themeBtnM{display:none} }
body.devil-ui .modesw{top:8px;left:14px}
body.devil-ui .modebtn{height:36px;gap:7px;padding:0 10px;border-radius:9px;border:1px solid transparent;background:transparent!important;box-shadow:none!important;backdrop-filter:none;font-weight:500;font-size:.95rem;color:var(--text)}
body.devil-ui .modebtn:hover,body.devil-ui .modesw.open .modebtn{background:var(--ag-hover)!important;border-color:transparent}
body.devil-ui .modebtn img{width:20px;height:20px;filter:none}
body.devil-ui .modebtn .mtag{display:none}
body.alt-mode .modebtn img,body.alt-mode .modebtn .mname{display:none}
.modebtn .aglabel{display:none;align-items:center;gap:7px}
body.alt-mode .modebtn .aglabel{display:inline-flex}
.aglabel .agspark{display:inline-flex;color:var(--ag-accent)}
body.devil-ui .modebtn .mchev{color:var(--dim)}
@media (max-width:900px){ body.devil-ui .modesw{left:52px} }
body.devil-ui .modemenu,body.devil-ui .modemenu2{background:var(--panel);border:1px solid var(--border);border-radius:12px;box-shadow:0 18px 50px rgba(0,0,0,.18)}
body.devil-ui .modeopt:hover{background:var(--ag-hover)}
body.devil-ui .modeopt.on{background:var(--panel2)}
body.devil-ui .modeopt .mic{background:var(--panel2);color:var(--text)}
body.devil-ui .modeopt.on .mic{background:var(--text);color:var(--bg)}
body.devil-ui .modeopt .mck{color:var(--text)}
body.devil-ui .modemenu .mhead,body.devil-ui .modemenu2 .mhead{color:var(--dim2)}

/* right header tools */
body.devil-ui .chattools{top:8px;right:12px;padding:0;gap:2px;border:none;background:transparent!important;box-shadow:none!important;backdrop-filter:none}
body.devil-ui .ctbtn{height:36px;min-width:36px;padding:0 9px;border-radius:9px;color:var(--dim)!important;background:transparent!important;border-color:transparent!important;box-shadow:none!important}
body.devil-ui .ctbtn:hover{background:var(--ag-hover)!important;color:var(--text)!important}
body.devil-ui .ctbtn.on{background:var(--panel3)!important;color:var(--text)!important}
body.devil-ui .ctbtn .txt{display:none}
#wsBtn{display:none}
body.agent-mode #wsBtn{display:inline-flex}
@media (max-width:900px){ body.devil-ui .chattools{right:52px} }
body.devil-ui.temp-chat #thread:before{background:var(--panel2);border-color:var(--border);color:var(--dim)}

/* icon rail (sidebar collapsed, desktop) */
#agentRail{display:none}
@media (min-width:901px){
  body.devil-ui.sb-closed #agentRail{display:flex;flex-direction:column;align-items:center;gap:6px;width:56px;flex-shrink:0;padding:10px 0;border-right:1px solid var(--border);background:var(--bg)}
}
#agentRail .rbtn{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--dim);transition:.12s}
#agentRail .rbtn:hover{background:var(--ag-hover);color:var(--text)}
#agentRail .rsp{flex:1}
#agentRail .rav{width:30px;height:30px;border-radius:50%;background:var(--panel3);color:var(--text);font-size:.78rem;font-weight:700;display:flex;align-items:center;justify-content:center}
#agentRail .rtop{margin-bottom:10px}

/* welcome / empty state */
#welcome .wmark{display:none}
body.devil-ui #welcome img.big,body.devil-ui #welcome .cards,body.devil-ui #welcome .sub{display:none}
body.devil-ui:not(.agent-mode) #welcome .wmark{display:inline-flex;align-items:center;gap:9px;margin-bottom:6px;font-family:'Iowan Old Style','Palatino Linotype',Palatino,'Book Antiqua',Georgia,serif;font-size:1.9rem;letter-spacing:-.02em;color:var(--text)}
#welcome .wmark img{width:34px;height:34px}
body.devil-ui #welcome h2{text-wrap:balance;font-family:'Iowan Old Style','Palatino Linotype',Palatino,'Book Antiqua',Georgia,serif;font-weight:400;font-size:clamp(2rem,4.6vw,3.1rem);letter-spacing:-.025em;color:var(--ag-head);line-height:1.15}
body.devil-ui #welcome h2 mark{background:var(--ag-mark);color:var(--ag-mark-text);font-style:italic;padding:0 .14em;margin:0 .02em}
body.devil-ui.agent-empty #scroller{flex:0 0 auto;margin-top:auto;overflow:visible}
body.devil-ui.agent-empty #thread{padding-top:0;padding-bottom:0}
body.devil-ui.agent-empty #welcome{padding:0 10px 22px}
body.devil-ui.agent-empty #composer{margin-bottom:auto;padding-bottom:12vh;background:none}
body.devil-ui.agent-empty .hint{visibility:hidden}
body.devil-ui.agent-empty #inp{min-height:64px}
@media (max-width:600px){
  body.devil-ui #welcome h2{font-size:1.95rem}
  body.agent-mode.agent-empty #composer{padding-bottom:18vh}
  /* AI Mode on phones: heading in the middle, composer docked at the bottom (like Battle Direct) */
  body.devil-ui:not(.agent-mode).agent-empty #scroller{flex:1 1 auto;margin-top:0;display:flex;flex-direction:column;justify-content:center;overflow:auto}
  body.devil-ui:not(.agent-mode).agent-empty #composer{margin-bottom:0;padding-bottom:12px}
  body.devil-ui:not(.agent-mode).agent-empty #inp{min-height:40px}
}

/* composer */
body.devil-ui #composer{background:linear-gradient(transparent,var(--bg) 30%)}
body.devil-ui .compbox{max-width:680px;border-radius:14px;background:var(--panel);border-color:var(--border);box-shadow:0 1px 2px rgba(0,0,0,.04),0 8px 28px rgba(0,0,0,.05)}
body.devil-ui .compbox:focus-within{border-color:var(--border-hi);box-shadow:0 1px 2px rgba(0,0,0,.04),0 10px 32px rgba(0,0,0,.08)}
body.devil-ui #inp{color:var(--text)}
body.devil-ui #inp::placeholder{color:var(--dim2)}
body.devil-ui .comprow{padding:8px}
body.devil-ui .modelwrap{gap:4px}
#attachBtn .atxt{display:none}
body.devil-ui #attachBtn,body.devil-ui #voiceBtn,body.devil-ui #promptBtn{width:32px;height:32px;border-radius:8px;color:var(--dim)}
body.devil-ui #attachBtn:hover,body.devil-ui #voiceBtn:hover,body.devil-ui #promptBtn:hover{background:var(--ag-hover);color:var(--text)}
body.devil-ui #voiceBtn.on{background:var(--panel3);color:var(--text);box-shadow:none}
body.agent-mode #attachBtn{width:auto;padding:0 10px;gap:6px;border:1px solid var(--border);color:var(--text);font-size:.82rem}
body.agent-mode #attachBtn .atxt{display:inline}
body.agent-mode #voiceBtn,body.agent-mode #promptBtn{display:none!important}
body.devil-ui #quickVoiceBtn{display:none!important}
body.devil-ui:not(.agent-mode) #attachBtn{order:1}
body.devil-ui:not(.agent-mode) .modechipwrap{order:2}
body.devil-ui:not(.agent-mode) #modelBtn{order:3}
body.devil-ui:not(.agent-mode) #customWrap{order:4}
body.devil-ui:not(.agent-mode) #promptBtn{order:5}
body.devil-ui:not(.agent-mode) #voiceBtn{order:6}
body.devil-ui #modelBtn,body.devil-ui #customModelBtn{border-color:transparent;color:var(--text);font-weight:500;padding:6px 9px;border-radius:8px}
body.agent-mode #modelBtn,body.agent-mode #customModelBtn{color:var(--dim)}
body.devil-ui #modelBtn:hover,body.devil-ui #customModelBtn:hover{background:var(--ag-hover);border-color:transparent;color:var(--text)}
body.devil-ui #sendBtn[hidden]{display:flex!important}
body.devil-ui #sendBtn{width:32px;height:32px;border-radius:8px;background:transparent;color:var(--dim);border:1px solid var(--border);box-shadow:none}
body.devil-ui #sendBtn:not(:disabled){background:var(--text);color:var(--bg);border-color:var(--text)}
body.devil-ui #sendBtn:hover{transform:none}
body.devil-ui #sendBtn.stopmode{background:var(--panel3);color:var(--text);border-color:var(--border-hi)}
body.devil-ui #modelMenu,body.devil-ui #customModelMenu,body.devil-ui #promptMenu{background:var(--panel);border-color:var(--border);border-radius:12px}
body.devil-ui .hint{color:var(--dim2)}
body.devil-ui #editChip,body.devil-ui #voiceChip{background:var(--panel2);border-color:var(--border);color:var(--dim)}

/* mode chip inside the composer (AI Mode, like Battle's "Direct ⌄") */
.modechipwrap{display:none;position:relative}
body.devil-ui:not(.agent-mode) .modechipwrap{display:block}
#modeChip{height:32px;display:inline-flex;align-items:center;gap:6px;padding:0 9px;border-radius:8px;color:var(--text);font-size:.82rem;font-weight:500;white-space:nowrap}
#modeChip:hover,.modechipwrap.open #modeChip{background:var(--ag-hover)}
#modeChip .mcc{display:inline-flex;color:var(--dim)}
.modemenu2{display:none;position:absolute;bottom:calc(100% + 8px);left:0;width:290px;max-width:calc(100vw - 40px);padding:6px;z-index:80}
.modechipwrap.open .modemenu2{display:block;animation:rise .16s ease}
.modemenu2 .mhead{padding:8px 10px 6px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}
body.devil-ui.agent-empty:not(.agent-mode) .modemenu2{bottom:auto;top:calc(100% + 8px)}
@media (max-width:600px){ body.devil-ui.agent-empty:not(.agent-mode) .modemenu2{top:auto;bottom:calc(100% + 8px)} #modeChip .mcl{display:none} }

/* messages */
body.devil-ui #thread{max-width:740px}
body.devil-ui .msg-user .bub{background:var(--ag-user);border-color:transparent;border-radius:14px;color:var(--text)}
body.devil-ui .msg-ai .ava{display:none}
body.devil-ui .msg-ai .who{gap:7px;margin-bottom:8px}
body.devil-ui .msg-ai .who:before{content:'';width:18px;height:18px;background:url(assets/logo.svg) center/contain no-repeat;flex-shrink:0}
body.devil-ui .msg-ai .who b{font-size:.84rem;font-weight:600;color:var(--text)}
body.devil-ui .msg-ai .who .mtag{border:none;padding:0;color:var(--dim);font-size:.78rem;letter-spacing:0}
body.devil-ui .msg-ai .who .mtag:before{content:'·';margin-right:6px;color:var(--dim2)}
body.agent-mode .msg-ai .who{display:none}
body.devil-ui .msg-ai .content{color:var(--text)}
body.devil-ui .content a{color:var(--ag-accent)}
body.devil-ui .content pre,body.devil-ui .content code{border-color:var(--border)}
body.devil-ui .acts button{color:var(--dim2)}
body.devil-ui .acts button:hover{background:var(--ag-hover);color:var(--text)}
body.devil-ui .dots span{background:var(--dim)}

/* activity timeline (agent steps) */
.agx{margin:0 0 12px;font-size:.84rem;color:var(--dim)}
.agx-head{display:inline-flex;align-items:center;gap:7px;padding:4px 8px 4px 2px;border-radius:8px;color:var(--dim);font-weight:500;transition:.12s}
.agx-head:hover{color:var(--text)}
.agx-head .agx-chev{display:inline-flex;transition:transform .16s}
.agx.open .agx-head .agx-chev{transform:rotate(90deg)}
.agx-spark{display:inline-flex;color:var(--ag-accent,#f43f5e)}
.agx-list{display:none;position:relative;margin:6px 0 2px 9px;padding-left:18px;border-left:1px solid var(--ag-line,var(--border))}
.agx.open .agx-list{display:block}
.agx-step{position:relative;padding:3px 0}
.agx-row{width:100%;display:flex;align-items:center;gap:8px;text-align:left;padding:5px 8px;margin-left:-8px;border-radius:8px;color:var(--dim);transition:.12s;min-width:0}
.agx-row:hover{background:var(--ag-hover,rgba(255,255,255,.05));color:var(--text)}
.agx-ic{position:absolute;left:-28px;top:7px;width:19px;height:19px;border-radius:50%;background:var(--bg);border:1px solid var(--ag-line,var(--border));display:flex;align-items:center;justify-content:center;color:var(--dim)}
.agx-ic svg{width:11px;height:11px}
.agx-verb{color:var(--text);font-weight:500;white-space:nowrap}
.agx-arg{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--dim)}
.agx-row .agx-chev{display:inline-flex;color:var(--dim2);transition:transform .16s}
.agx-step.open .agx-row .agx-chev{transform:rotate(90deg)}
.agx-out{display:none;margin:4px 0 6px;padding:10px 12px;border:1px solid var(--ag-line,var(--border));border-radius:10px;background:var(--panel2);font:12px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:var(--dim);white-space:pre-wrap;word-break:break-word;max-height:240px;overflow:auto}
.agx-step.open .agx-out{display:block}
.agx-step.fail .agx-verb{color:#e05d6f}
.agx-done{display:flex;align-items:center;gap:8px;padding:5px 0 2px;color:var(--dim2);font-size:.8rem}
.agx-live{display:inline-flex;align-items:center;gap:9px;color:var(--dim);font-size:.88rem}
.agx-live .agx-spark svg{animation:agspin 2.4s linear infinite}
.agx-live .agx-time{color:var(--dim2);font-variant-numeric:tabular-nums}
.agx-shimmer{background:linear-gradient(90deg,var(--dim) 0%,var(--text) 50%,var(--dim) 100%);background-size:200% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:agshim 1.8s linear infinite}
@keyframes agspin{to{transform:rotate(360deg)}}
@keyframes agshim{from{background-position:200% 0}to{background-position:-200% 0}}

/* workspace panel */
#wsPanel{position:absolute;top:0;right:0;bottom:0;width:340px;max-width:100%;z-index:45;background:var(--panel);border-left:1px solid var(--border);box-shadow:none;transform:translateX(102%);visibility:hidden;transition:transform .22s ease,visibility 0s linear .22s;display:flex;flex-direction:column}
#wsPanel.open{transform:none;visibility:visible;box-shadow:-20px 0 50px rgba(0,0,0,.12);transition:transform .22s ease,visibility 0s}
.ws-head{height:52px;display:flex;align-items:center;gap:8px;padding:0 10px 0 16px;border-bottom:1px solid var(--border);font-weight:600;font-size:.92rem;color:var(--text)}
.ws-head .sp{flex:1}
.ws-head button{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--dim)}
.ws-head button:hover{background:var(--ag-hover);color:var(--text)}
.ws-body{flex:1;overflow:auto;padding:14px 12px 20px}
.ws-sec{font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--dim2);padding:6px 6px 8px}
.ws-item{display:flex;align-items:center;gap:10px;padding:8px;border-radius:9px;color:var(--text);font-size:.82rem;min-width:0}
a.ws-item:hover{background:var(--ag-hover)}
.ws-item .wi{width:28px;height:28px;flex-shrink:0;border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--dim)}
.ws-item .wt{flex:1;min-width:0;display:flex;flex-direction:column}
.ws-item .wt b{font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ws-item .wt small{color:var(--dim2);font-size:.72rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ws-empty{margin:30px 10px;text-align:center;color:var(--dim2);font-size:.82rem;line-height:1.6}
.ws-empty .wi{width:44px;height:44px;margin:0 auto 10px;border-radius:12px;border:1px dashed var(--border-hi);display:flex;align-items:center;justify-content:center;color:var(--dim)}
.ws-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:0 4px 16px}
.ws-stat{border:1px solid var(--border);border-radius:10px;padding:10px 12px}
.ws-stat b{display:block;font-size:1.1rem;color:var(--text);font-weight:600}
.ws-stat span{font-size:.72rem;color:var(--dim2)}
@media (max-width:900px){ #wsPanel{width:100%} }

/* ═══════════ BATTLE MODES: Battle + Side by Side, leaderboard, chat upgrades ═══════════ */
.sbnav{display:flex;align-items:center;gap:9px;width:calc(100% - 24px);margin:6px 12px 0;padding:8px 12px;border-radius:10px;color:var(--dim);font-size:.86rem;font-weight:500;transition:.12s}
.sbnav:hover{background:var(--ag-hover,rgba(255,255,255,.06));color:var(--text)}
.chatitem .cmode{display:inline-flex;color:var(--dim2);flex-shrink:0}
.chatitem.on .cmode,.chatitem:hover .cmode{color:var(--text)}
body.alt-mode .modebtn .agspark{color:var(--ag-accent)}
body.cmp-mode .modebtn .agspark{color:var(--text)}

/* header chat title (centered, like Battle's conversation header) */
.m-top .ctitle{display:none}
@media (min-width:901px){
  body.devil-ui .m-top .ctitle{display:block;position:absolute;left:50%;top:26px;z-index:5;transform:translate(-50%,-50%);max-width:min(46%,520px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.86rem;font-weight:500;color:var(--dim);pointer-events:none}
}

/* composer: compare-mode controls */
.cmpchip,.cmppick{display:none}
body.cmp-mode #modelBtn,body.cmp-mode #customWrap,body.cmp-mode #attachBtn,body.cmp-mode #voiceBtn{display:none!important}
body.battle-mode .cmpchip{display:inline-flex}
body.sbs-mode .cmppick{display:block}
.cmpchip{align-items:center;gap:6px;height:32px;padding:0 9px;border-radius:8px;color:var(--dim);font-size:.82rem;font-weight:500;white-space:nowrap;order:3;cursor:default}
.cmppick{position:relative;order:3}
#cmpPickB{order:4}
.cmpbtn{height:32px;display:inline-flex;align-items:center;gap:6px;padding:0 8px;border-radius:8px;color:var(--text);font-size:.82rem;font-weight:500;white-space:nowrap;max-width:190px}
.cmpbtn .lb{overflow:hidden;text-overflow:ellipsis}
.cmpbtn svg{color:var(--dim);flex-shrink:0}
.cmpbtn:hover,.cmppick.open .cmpbtn{background:var(--ag-hover)}
.cmpab{width:18px;height:18px;border-radius:5px;display:inline-flex;align-items:center;justify-content:center;font-size:.66rem;font-weight:800;background:var(--panel3);color:var(--text);flex-shrink:0}
.cmpmenu{display:none;position:absolute;bottom:calc(100% + 8px);left:0;width:250px;max-height:320px;overflow:auto;padding:6px;z-index:80;background:var(--panel);border:1px solid var(--border);border-radius:12px;box-shadow:0 18px 50px rgba(0,0,0,.18)}
body.devil-ui.agent-empty .cmpmenu{bottom:auto;top:calc(100% + 8px)}
.cmppick.open .cmpmenu{display:block;animation:rise .16s ease}
.cmpmenu .mhead{padding:8px 10px 6px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--dim2)}
.cmpopt{display:flex;align-items:center;gap:9px;width:100%;padding:8px 10px;border-radius:8px;font-size:.84rem;color:var(--text);text-align:left}
.cmpopt:hover{background:var(--ag-hover)}
.cmpopt.on{background:var(--panel2);font-weight:600}
.cmpopt .oi{display:inline-flex;color:var(--dim)}
.cmpopt .ock{margin-left:auto;display:none}
.cmpopt.on .ock{display:inline-flex}
@media (max-width:600px){
  body.devil-ui.agent-empty .cmpmenu{top:auto;bottom:calc(100% + 8px)}
  .cmpchip .lb{display:none}
  .cmpbtn{max-width:118px}
  .cmpmenu{position:fixed;left:12px;right:12px;width:auto;bottom:150px}
  body.sbs-mode #promptBtn{display:none}
}

/* wider thread for two-column answers */
body.cmp-mode #thread{max-width:1180px}
body.cmp-mode .msg-user{max-width:740px;margin-left:auto;margin-right:auto}
body.cmp-mode .msg-user .bub{margin-left:auto}
body.cmp-mode .compbox{max-width:760px}

.cmp-turn{margin:6px 0 26px}
.cmp-tabs{display:none}
@media (max-width:760px){
  .cmp-tabs{display:flex;gap:4px;padding:3px;margin:0 0 10px;border-radius:10px;background:var(--panel2);border:1px solid var(--border)}
  .cmp-tabs button{flex:1;min-width:0;height:30px;border-radius:7px;font-size:.78rem;font-weight:600;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:0 8px}
  .cmp-tabs button.on{background:var(--panel);color:var(--text);box-shadow:0 1px 3px rgba(0,0,0,.08)}
}
.cmp-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start}
.cmp-col{min-width:0;border:1px solid var(--border);border-radius:14px;background:var(--panel);overflow:hidden;transition:border-color .2s,box-shadow .2s}
[data-theme=light] .cmp-col{background:#fff}
.cmp-col.win{border-color:var(--text);box-shadow:0 0 0 1px var(--text)}
.cmp-col.lose{opacity:.78}
.cmp-head{display:flex;align-items:center;gap:8px;padding:9px 10px 9px 12px;border-bottom:1px solid var(--border);font-size:.8rem}
.cmp-head .cmpab{width:20px;height:20px}
.cmp-name{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.78rem;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cmp-col.revealed .cmp-name{font-family:inherit;font-size:.82rem;font-weight:600;color:var(--text)}
.cmp-time{color:var(--dim2);font-size:.72rem;white-space:nowrap}
.cmp-head .sp{flex:1}
.cmp-ibtn{width:28px;height:28px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;color:var(--dim);flex-shrink:0}
.cmp-ibtn:hover{background:var(--ag-hover);color:var(--text)}
.cmp-ibtn[hidden]{display:none}
.cmp-body{padding:12px 14px 14px;max-height:62vh;overflow:auto}
.cmp-body .content{font-size:.9rem;line-height:1.68;color:var(--text)}
.cmp-body .content > :first-child{margin-top:0}
.cmp-wait{display:flex;align-items:center;gap:10px;color:var(--dim);font-size:.86rem}
.cmp-wait .shim{background:linear-gradient(90deg,var(--dim2),var(--text),var(--dim2));background-size:200% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:cmpShim 1.6s linear infinite}
@keyframes cmpShim{to{background-position:-200% 0}}
.cmp-err{color:var(--dim);font-size:.86rem;display:flex;flex-direction:column;gap:8px;align-items:flex-start}
.cmp-err button{border:1px solid var(--border);border-radius:8px;padding:5px 10px;font-size:.8rem;color:var(--text)}
.cmp-err button:hover{background:var(--ag-hover)}

.cmp-vote{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-top:14px}
.cmp-vote[hidden]{display:none}
.cmp-vote button{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 15px;border-radius:10px;border:1px solid var(--border);background:var(--panel);color:var(--text);font-size:.85rem;font-weight:500;transition:.14s}
.cmp-vote button:hover{border-color:var(--border-hi);background:var(--panel2);transform:translateY(-1px)}
.cmp-vote button svg{color:var(--dim)}
.cmp-vote .vq{width:100%;text-align:center;font-size:.8rem;color:var(--dim2);margin-bottom:2px}
.cmp-result{display:none;margin-top:12px;text-align:center;font-size:.84rem;color:var(--dim)}
.cmp-result.show{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:8px}
.cmp-result .pill{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;background:var(--panel2);border:1px solid var(--border);color:var(--text);font-weight:500}
.cmp-result .pill b{font-weight:700}
.cmp-result button{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;background:var(--text);color:var(--bg);font-weight:600;font-size:.82rem}
@media (max-width:760px){
  .cmp-grid{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:10px;margin:0 -14px;padding:0 14px 4px;scrollbar-width:none}
  .cmp-grid::-webkit-scrollbar{display:none}
  .cmp-col{flex:0 0 86%;scroll-snap-align:center}
  .cmp-body{max-height:none}
  .cmp-vote button{flex:1 1 calc(50% - 8px);justify-content:center;padding:0 8px}
}

/* modals */
.lbsheet{max-width:640px!important;width:calc(100vw - 32px)}
.lbbody{margin-top:12px;max-height:56vh;overflow:auto;border:1px solid var(--border);border-radius:12px}
.lbtable{width:100%;border-collapse:collapse;font-size:.85rem}
.lbtable th{position:sticky;top:0;background:var(--panel2);text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--dim);padding:9px 12px;font-weight:700}
.lbtable td{padding:10px 12px;border-top:1px solid var(--border);color:var(--text)}
.lbtable td.r{font-weight:700;color:var(--dim);width:44px}
.lbtable td.n{font-weight:600}
.lbtable td.s{font-variant-numeric:tabular-nums;font-weight:700}
.lbtable td.d{color:var(--dim);font-variant-numeric:tabular-nums}
.lbtable tr.top1 td.r{color:#d4a017}
.lbempty{padding:22px;text-align:center;color:var(--dim);font-size:.86rem}
.cmpsheet{max-width:860px!important;width:calc(100vw - 32px)}
.cmpmodalbody{margin-top:10px;max-height:72vh;overflow:auto;font-size:.93rem;line-height:1.7}

/* scroll-to-latest button (all modes) */
#toBottom{position:absolute;left:50%;bottom:150px;transform:translate(-50%,10px);width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--panel);border:1px solid var(--border);color:var(--text);box-shadow:0 6px 20px rgba(0,0,0,.12);opacity:0;pointer-events:none;transition:.18s;z-index:30}
#toBottom.show{opacity:1;pointer-events:auto;transform:translate(-50%,0)}
#toBottom:hover{background:var(--panel2)}
</style>
</head>
<body class="devil-ui<?= $ROUTE_MODE === 'agent' ? ' agent-mode' : '' ?><?= $ROUTE_MODE !== 'ai' ? ' alt-mode' : '' ?><?= $IS_CMP ? ' cmp-mode ' . $ROUTE_MODE . '-mode' : '' ?><?= preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', (string)($_GET['chat'] ?? '')) ? ' loading-chat' : '' ?>">

<div id="app">

  <!-- ═══ SIDEBAR ═══ -->
  <aside id="sidebar">
    <div class="sb-top">
      <button class="iconbtn" id="sbToggle" title="Close sidebar"><?= icon('panel-left') ?></button>
      <a class="brand" href="index.php"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
      <button class="iconbtn" id="themeBtn" title="Switch theme"><?= icon('sun', 17) ?></button>
    </div>
    <button class="newchat" id="newChatBtn"><?= icon('square-pen', 17) ?> New chat</button>
    <button class="sbnav" id="sbBoard" type="button"><?= icon('trophy', 16) ?> Leaderboard</button>
    <div class="sb-search"><?= icon('search', 15) ?><input id="searchInp" type="text" placeholder="Search chats…" autocomplete="off"></div>
    <nav id="chatList" aria-label="Chat history"></nav>
    <div class="sb-bottom">
      <div id="userMenu">
        <?php if (strtolower((string)($me['email'] ?? '')) === 'bk.w.p.bk@gmail.com'): ?><a class="mi" href="admin.php"><?= icon('shield-check', 16) ?> Admin control</a><?php endif; ?>
        <a class="mi" href="developers.php"><?= icon('code', 16) ?> Developer API</a>
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

  <!-- Agent Mode icon rail (shown when the sidebar is collapsed) -->
  <nav id="agentRail" aria-label="Quick navigation">
    <button class="rbtn rtop" id="railOpen" type="button" title="Open sidebar"><?= icon('panel-left', 18) ?></button>
    <button class="rbtn" id="railNew" type="button" title="New chat"><?= icon('square-pen', 17) ?></button>
    <button class="rbtn" id="railHistory" type="button" title="Chat history"><?= icon('list', 17) ?></button>
    <button class="rbtn" id="railBoard" type="button" title="Leaderboard"><?= icon('trophy', 17) ?></button>
    <span class="rsp"></span>
    <button class="rbtn" id="railTheme" type="button" title="Switch theme"><?= icon('sun', 17) ?></button>
    <button class="rbtn" id="railUser" type="button" title="Account"><span class="rav"><?= htmlspecialchars(strtoupper(mb_substr($me['name'], 0, 1))) ?></span></button>
  </nav>

  <!-- ═══ MAIN ═══ -->
  <main>
    <header class="m-top">
      <button class="iconbtn" id="sbOpen" title="Open sidebar"><?= icon('menu', 19) ?></button>
      <span class="brand"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</span>
      <span class="ctitle" id="chatTitle"></span>
      <button class="iconbtn" id="themeBtnM" title="Switch theme"><?= icon('sun', 17) ?></button>
    </header>

    <div class="modesw<?= $ROUTE_MODE !== 'ai' ? ' agent' : '' ?>" id="modeSw">
      <button class="modebtn" id="modeBtn" type="button" aria-haspopup="menu" aria-expanded="false" title="Switch mode">
        <img src="assets/logo.svg" alt=""><span class="mname">Devil AI</span><span class="mtag" id="modeTag"><?= htmlspecialchars($MODE_DEFS[$ROUTE_MODE]['chip']) ?></span><span class="aglabel"><span class="agspark" id="agIco"><?= icon($MODE_DEFS[$ROUTE_MODE]['icon'], 17) ?></span><span id="agTxt"><?= htmlspecialchars($MODE_DEFS[$ROUTE_MODE]['label']) ?></span></span><span class="mchev"><?= icon('chevron-down', 15) ?></span>
      </button>
      <div class="modemenu" id="modeMenu" role="menu" aria-label="Chat mode">
        <div class="mhead">Choose mode</div>
        <?= mode_options_html($MODE_DEFS, $ROUTE_MODE) ?>
      </div>
    </div>

    <div class="chattools" id="chatTools" aria-label="Chat actions">
      <button class="ctbtn primary" id="tempChatBtn" type="button" title="Start temporary chat"><?= icon('ghost', 17) ?><span class="txt">Temp</span></button>
      <button class="ctbtn" id="topNewChatBtn" type="button" title="New chat"><?= icon('square-pen', 17) ?><span class="txt">New</span></button>
      <button class="ctbtn" id="wsBtn" type="button" title="Workspace — sources and tool activity" aria-expanded="false"><?= icon('folder', 18) ?><span class="txt">Workspace</span></button>
    </div>

    <aside id="wsPanel" aria-label="Agent workspace" aria-hidden="true">
      <div class="ws-head"><?= icon('folder', 17) ?><span>Workspace</span><span class="sp"></span><button type="button" id="wsClose" title="Close workspace"><?= icon('x', 16) ?></button></div>
      <div class="ws-body" id="wsBody"></div>
    </aside>

    <div id="scroller"><div id="thread">
      <div id="welcome">
        <img class="big" src="assets/logo.svg" alt="Devil AI logo">
        <div class="wmark"><img src="assets/logo.svg" alt="">Devil AI</div>
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

    <button id="toBottom" type="button" title="Scroll to latest" aria-label="Scroll to latest"><?= icon('arrow-down', 17) ?></button>
    <div id="composer">
      <div class="compbox">
        <div id="imgChip"></div>
        <div id="voiceChip"><span><?= icon('mic', 14) ?><b id="voiceStatus">Voice mode ready</b></span><button id="voiceClose" type="button">Turn off</button></div>
        <div id="editChip"><span><?= icon('pencil', 14) ?> Editing message — original chat stays saved, a new branch will be created.</span><button id="editCancel" type="button">Cancel</button></div>
        <textarea id="inp" rows="1" maxlength="4000" placeholder="Message Devil AI…"></textarea>
        <div class="comprow">
          <div class="modelwrap">
            <button id="attachBtn" title="Attach files" type="button"><?= icon('paperclip', 16) ?><span class="atxt">Add files</span></button>
            <div class="modechipwrap" id="modeChipWrap">
              <button id="modeChip" type="button" title="Switch mode" aria-haspopup="menu" aria-expanded="false"><span class="mcc" id="modeChipIco"><?= icon($MODE_DEFS[$ROUTE_MODE]['icon'], 15) ?></span><span class="mcl" id="modeChipLbl"><?= htmlspecialchars($MODE_DEFS[$ROUTE_MODE]['chip']) ?></span><span class="mcc"><?= icon('chevron-down', 13) ?></span></button>
              <div class="modemenu2" role="menu" aria-label="Chat mode">
                <div class="mhead">Choose mode</div>
                <?= mode_options_html($MODE_DEFS, $ROUTE_MODE) ?>
              </div>
            </div>
            <span id="cmpAuto" class="cmpchip" title="Two random Devil models are picked for every battle. Their names stay hidden until you vote."><?= icon('shuffle', 14) ?><span class="lb">Random models</span></span>
            <div class="cmppick" id="cmpPickA" data-side="a"><button type="button" class="cmpbtn" aria-haspopup="menu"><span class="cmpab">A</span><span class="lb">Devil Flash</span><?= icon('chevron-down', 13) ?></button><div class="cmpmenu" role="menu"></div></div>
            <div class="cmppick" id="cmpPickB" data-side="b"><button type="button" class="cmpbtn" aria-haspopup="menu"><span class="cmpab">B</span><span class="lb">Devil Pro</span><?= icon('chevron-down', 13) ?></button><div class="cmpmenu" role="menu"></div></div>
            <button id="voiceBtn" title="Live voice chat" type="button" aria-pressed="false"><?= icon('mic', 16) ?></button>
            <button id="promptBtn" title="Prompt library" type="button"><?= icon('lightbulb', 16) ?></button>
            <div id="promptMenu"></div>
            <button id="modelBtn" title="Choose model"><span id="modelIco"><?= icon('zap', 14) ?></span><span class="lb" id="modelLbl">Devil Flash</span><?= icon('chevron-down', 13) ?></button>
            <div id="modelMenu"></div>
            <div id="customWrap" class="customwrap">
              <button id="customModelBtn" title="Choose custom AI model" type="button"><span id="customIco"></span><span class="lb" id="customLbl">Devil Smart</span><?= icon('chevron-down', 13) ?></button>
              <div id="customModelMenu"></div>
            </div>
          </div>
          <button id="quickVoiceBtn" title="Voice input" type="button"><?= icon('mic', 17) ?></button>
          <button id="sendBtn" title="Send (Enter)" type="button" disabled hidden><?= icon('send', 17) ?></button>
        </div>
      </div>
      <p class="hint">Enter = new line • Ctrl/⌘ + Enter = send • Devil AI can make mistakes.</p>
    </div>
  </main>
</div>

<input type="file" id="fileInput" multiple hidden>
<div id="imgView"><?= icon('x', 22) ?></div>

<div id="voiceLive" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Live voice chat">
  <div class="voiceShell">
    <div class="voiceHead">
      <div class="vt"><b>Devil AI Live</b><span>Hands-free voice conversation</span></div>
      <button class="voiceCloseTop" id="voiceLiveClose" type="button" title="End voice chat"><?= icon('x', 17) ?></button>
    </div>
    <div class="voiceStage">
      <div class="voiceOrb" id="voiceOrb"><?= icon('mic', 38) ?></div>
      <p class="voiceState" id="voiceLiveState">Ready for live voice</p>
      <p class="voiceHint">Speak naturally. Devil AI will listen, reply, and continue the conversation automatically.</p>
    </div>
    <div class="voiceTranscript">
      <div class="voiceLine"><span>You</span><p id="voiceUserText" class="ghost">Tap the mic and start speaking…</p></div>
      <div class="voiceLine"><span>Devil AI</span><p id="voiceAiText" class="ghost">I’ll reply out loud here.</p></div>
    </div>
    <div class="voiceControls">
      <button class="voiceCtl on" id="voiceMute" type="button"><?= icon('mic', 15) ?><span>Mute</span></button>
      <button class="voiceCtl" id="voiceInterrupt" type="button" disabled><?= icon('stop', 15) ?><span>Stop</span></button>
      <button class="voiceCtl end" id="voiceEnd" type="button" title="End voice chat"><?= icon('x', 20) ?></button>
    </div>
  </div>
</div>

<!-- ═══ leaderboard modal (Battle Mode votes) ═══ -->
<div class="modal hidden" id="lbModal"><div class="sheet lbsheet">
  <div class="shead"><h3><?= icon('trophy', 17) ?> Devil Battle Leaderboard</h3><button class="iconbtn" data-close="lbModal"><?= icon('x', 16) ?></button></div>
  <p class="snote" id="lbNote">Rankings come from anonymous Battle Mode votes (Elo score).</p>
  <div id="lbBody" class="lbbody"></div>
  <div class="btnrow"><button class="btn primary" id="lbBattle" type="button"><?= icon('swords', 15) ?> Start a battle</button><button class="btn ghost" data-close="lbModal">Close</button></div>
</div></div>

<!-- ═══ compare answer full view ═══ -->
<div class="modal hidden" id="cmpModal"><div class="sheet cmpsheet">
  <div class="shead"><h3 id="cmpModalTitle">Assistant A</h3><button class="iconbtn" data-close="cmpModal"><?= icon('x', 16) ?></button></div>
  <div class="content cmpmodalbody" id="cmpModalBody"></div>
</div></div>

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
const APP_BASE_PATH = <?= json_encode($APP_BASE_PATH, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const ROUTE_MODE = <?= json_encode($ROUTE_MODE) ?>;
const INITIAL_CHAT_ID = <?= json_encode(preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', (string)($_GET['chat'] ?? '')) ? (string)$_GET['chat'] : '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const INITIAL_VARIANT = <?= json_encode((preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', (string)($_GET['variant'] ?? '')) || (string)($_GET['variant'] ?? '') === 'original') ? (string)$_GET['variant'] : '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
(function () {
'use strict';
var $ = function (s) { return document.querySelector(s); };
var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };

/* ── state ── */
var models = [], modelById = {}, currentModel = 'flash';
var customModels = [], customById = {}, currentCustom = 'devil-09';
var chats = [], currentChat = null;   /* currentChat = {id, title, messages, temp?} */
var busy = false, isTempChat = false, activeController = null, sendSeq = 0, inlineEdit = null, editRestoreChat = null;
var chatMode = ROUTE_MODE, agentMode = ROUTE_MODE === 'agent', agentEnabled = true;
var BATTLE_POOL = [], battleById = {}, cmpA = 'flash', cmpB = 'pro', cmpFromChat = false;
var MODE_INFO = {
  battle: { label: 'Battle Mode', chip: 'Battle', icon: 'swordsM', chipIcon: 'swords', path: 'battle', seg: 'battle' },
  agent:  { label: 'Agent Mode', chip: 'Agent', icon: 'sparkM', chipIcon: 'spark', path: 'agent', seg: 'agent' },
  sbs:    { label: 'Side by Side', chip: 'Side by Side', icon: 'columnsM', chipIcon: 'columns', path: 'side-by-side', seg: 'side-by-side' },
  ai:     { label: 'AI Mode', chip: 'Direct', icon: 'messageM', chipIcon: 'messageM', path: 'app.php', seg: '' }
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
      var isOn = currentChat && (currentChat.id === c.id || currentChat.root_id === c.id || (currentChat.slug && currentChat.slug === c.slug));
      html += '<div class="chatitem' + (isOn ? ' on' : '') + '" data-id="' + c.id + '" data-slug="' + (c.slug || '') + '">' +
        (c.mode && c.mode !== 'ai' && MODE_INFO[c.mode] ? '<span class="cmode" title="' + MODE_INFO[c.mode].label + '">' + I[c.mode === 'battle' ? 'swordsS' : (c.mode === 'sbs' ? 'columnsS' : 'sparkS')] + '</span>' : '') +
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
      (out ? '<div class="agx-out">' + esc(out) + '</div>' : '') + '</div>';
  }).join('');
  box.innerHTML = '<button type="button" class="agx-head"><span class="agx-spark">' + (I.spark || '') + '</span><span>' + esc(label) + '</span>' +
    (steps.length ? '<span class="agx-chev">' + (I.chevR || '') + '</span>' : '') + '</button>' +
    (steps.length ? '<div class="agx-list">' + rows + '<div class="agx-done">' + (I.check || '') + ' Done</div></div>' : '');
  box.querySelector('.agx-head').addEventListener('click', function () { if (steps.length) { box.classList.toggle('open'); } });
  Array.prototype.forEach.call(box.querySelectorAll('.agx-step'), function (st) {
    var r = st.querySelector('.agx-row');
    if (st.querySelector('.agx-out')) { r.addEventListener('click', function () { st.classList.toggle('open'); }); }
  });
  var body = el.querySelector('.body');
  if (body) { body.insertBefore(box, el.querySelector('.content')); }
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
    else { var el = addAiMsg({ modelTag: m.model_label }); aiContent(el, m.content || '', { index: idx }); if ((m.agent_steps && m.agent_steps.length) || m.agent_ms) { renderAgentTrace(el, m.agent_steps || [], m.agent_ms); } }
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

function updateChatActions() {
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
  if (file.size > MAX_FILE) { toast((file.name || 'File') + ' is too large (max 4 MB)', 'warning'); return; }
  var total = pendingFiles.reduce(function (sum, f) { return sum + (f.size || 0); }, 0);
  if (total + file.size > MAX_TOTAL_ATTACH) { toast('Attachments are too large together (max 7 MB)', 'warning'); return; }
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
  busy = true;
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
      if (currentChat && j.mode) { currentChat.mode = j.mode; }
      currentChat.messages.push(am);
      var aiIndex = currentChat.messages.length - 1;
      var el = addAiMsg({ modelTag: (j.model && j.model.label) || activeModelLabel() });
      aiContent(el, j.reply, { index: aiIndex });
      if (j.agent) { renderAgentTrace(el, j.agent.steps || [], j.agent.ms); renderWorkspace(); }
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
      '<button type="button" class="cmp-ibtn" data-act="retry" title="Regenerate this answer">' + I.retry + '</button>' +
      '<button type="button" class="cmp-ibtn" data-act="expand" title="Expand">' + I.maximize + '</button></div>' +
      '<div class="cmp-body"><div class="content"></div></div></div>';
  });
  html += '</div><div class="cmp-vote" hidden><div class="vq">Which response is better?</div>' +
    '<button type="button" data-vote="a">' + I.arrowL + ' A is better</button>' +
    '<button type="button" data-vote="tie">' + I.equal + ' It’s a tie</button>' +
    '<button type="button" data-vote="bad">' + I.thumbDown + ' Both are bad</button>' +
    '<button type="button" data-vote="b">B is better ' + I.arrowR + '</button></div><div class="cmp-result"></div>';
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
  });
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
    if (chatMode === 'battle' && d === lastCompareTurn()) { h += '<button type="button" data-act="newbattle">' + I.swordsS + ' New battle</button>'; }
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
['sbBoard', 'railBoard'].forEach(function (id) { var el = document.getElementById(id); if (el) { el.addEventListener('click', openLeaderboard); } });
(function () { var lb = $('#lbBattle'); if (lb) { lb.addEventListener('click', function () { window.location.href = 'battle'; }); } })();

/* ── scroll-to-latest button (all modes) ── */
(function () {
  var tb = $('#toBottom'); if (!tb || !scroller) { return; }
  function upd() { tb.classList.toggle('show', scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight > 320); }
  scroller.addEventListener('scroll', upd, { passive: true });
  window.addEventListener('resize', upd);
  tb.addEventListener('click', function () { scroller.scrollTo({ top: scroller.scrollHeight, behavior: 'smooth' }); });
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

/* ── agent workspace UI wiring ── */
(function () {
  var wb = $('#wsBtn'), wc = $('#wsClose');
  if (wb) { wb.addEventListener('click', function () { setWorkspace(!$('#wsPanel').classList.contains('open')); }); }
  if (wc) { wc.addEventListener('click', function () { setWorkspace(false); }); }
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
  agentEnabled = j.agent_enabled !== false;
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
</script>
<script>
if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); }); }
</script>
</body>
</html>
