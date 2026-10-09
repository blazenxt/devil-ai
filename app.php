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
/* AI Mode lives at /chat — /app.php must never show up in the address bar */
$REQ_PATH = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true) && preg_match('~/app\.php$~i', $REQ_PATH)) {
    $q = $_GET;
    $seg = ['agent' => 'agent', 'battle' => 'battle', 'sbs' => 'side-by-side'][(string)($q['mode'] ?? '')] ?? 'chat';
    unset($q['mode']);
    $chatId = (string)($q['chat'] ?? '');
    $to = ($APP_BASE_PATH ?: '') . '/' . $seg;
    if ($seg !== 'chat' && preg_match('/^[a-f0-9]{128}$/', $chatId)) { $to .= '/' . $chatId; unset($q['chat']); }
    header('Location: ' . $to . ($q ? '?' . http_build_query($q) : ''), true, 301);
    exit;
}
/* No landing page — signed-out visitors see the chat screen; sending asks them to log in */
/* The service worker stores the home page for offline use (a background fetch, not a page visit).
   That copy must never contain anyone's chats → background fetches always get the signed-out page. */
$IS_SHELL_FETCH = isset($_SERVER['HTTP_SEC_FETCH_MODE']) && $_SERVER['HTTP_SEC_FETCH_MODE'] !== 'navigate';
if ($IS_SHELL_FETCH) { $me = null; }
$IS_GUEST = !$me;
if ($IS_GUEST) {
    /* a saved chat link needs the owner's account */
    if (preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', (string)($_GET['chat'] ?? ''))) {
        header('Location: ' . ($APP_BASE_PATH ?: '') . '/login.php?next=' . rawurlencode($REQ_PATH), true, 302);
        exit;
    }
    $me = ['id' => '', 'name' => 'Guest', 'email' => ''];
}
/* /agent and /agent/{slug} are rewritten to app.php?mode=agent */
/* cache-busting version for the static app bundle (changes whenever a file is redeployed) */
$ASSET_V = substr(md5(implode('|', array_map(static function ($f) { return @filemtime($f) . ':' . @filesize($f); }, [__DIR__ . '/assets/app.css', __DIR__ . '/assets/app.js']))), 0, 10);
/* First-screen data travels inside this page: the browser does not have to make a second trip to the
   server (bootstrap + chat list + the open chat) before it can show anything. Any problem → empty, and the
   app simply loads it the normal way. */
$BOOT = [];
try {
    if (!defined('DEVIL_API_AS_LIB')) { define('DEVIL_API_AS_LIB', true); }
    require_once __DIR__ . '/api.php';
    $BOOT['bootstrap'] = bootstrap_payload();
    $BOOT['chats'] = ['ok' => true, 'chats' => $IS_GUEST ? [] : list_chats((string)$me['id'])];
    $bootChat = (string)($_GET['chat'] ?? '');
    $bootVar = (string)($_GET['variant'] ?? '');
    if (!preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', $bootVar) && $bootVar !== 'original') { $bootVar = ''; }
    if (!$IS_GUEST && preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', $bootChat)) {
        $pl = chat_load_payload((string)$me['id'], $bootChat, $bootVar);
        if ($pl !== null && strlen((string)json_encode($pl)) < 700000) { $BOOT['chat_load&id=' . $bootChat . ($bootVar !== '' ? '&variant=' . $bootVar : '')] = $pl; }
    }
} catch (Throwable $e) { $BOOT = []; }
$ROUTE_MODE = in_array((string)($_GET['mode'] ?? ''), ['agent', 'battle', 'sbs'], true) ? (string)$_GET['mode'] : 'ai';
/* chat modes: Battle, Agent, Side by Side, Direct (AI Mode) */
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
    'swordsS' => icon('swords', 13), 'columnsS' => icon('columns', 13), 'sparkS' => icon('spark', 13), 'msgS' => icon('message', 13), 'chevS' => icon('chevron-down', 13),
    'pin' => icon('pin', 14), 'pinS' => icon('pin', 12), 'download' => icon('download', 16), 'keyboard' => icon('keyboard', 16), 'swap' => icon('swap', 15),
    'fork' => icon('fork', 14), 'external' => icon('external', 13), 'listS' => icon('list', 13), 'clockS' => icon('clock', 12), 'globeS' => icon('globe', 12),
    'terminal' => icon('terminal', 14), 'monitor' => icon('monitor', 14), 'upload' => icon('upload', 15), 'help' => icon('help-circle', 14), 'folderS' => icon('folder', 13), 'imageS' => icon('image', 13), 'codeS' => icon('code', 13),
    'fileS' => icon('file-text', 12), 'calcS' => icon('calculator', 12), 'linkS' => icon('link', 12), 'retryS' => icon('retry', 13), 'sparklesS' => icon('sparkles', 13),
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<base href="<?= htmlspecialchars(($APP_BASE_PATH ?: '') . '/', ENT_QUOTES) ?>">
<meta name="theme-color" content="#0c0709">
<?php $IS_HOME = in_array(rtrim($REQ_PATH, '/'), [rtrim($APP_BASE_PATH, '/'), rtrim($APP_BASE_PATH, '/') . '/index.php'], true); ?>
<?php if ($IS_HOME && $IS_GUEST): ?><meta name="description" content="Chat with top AI models, compare them in anonymous battles, build apps with Devil Agent and see the leaderboard. Free.">
<link rel="canonical" href="https://ai.devil.blazenxt.com/"><?php else: ?><meta name="robots" content="noindex"><?php endif; ?>
<script>/* theme boot — runs before paint to avoid a flash of the wrong theme */
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();</script>
<title><?= $IS_HOME ? 'Devil AI — Chat, compare &amp; build with the best AI models' : htmlspecialchars($MODE_DEFS[$ROUTE_MODE]['label'] ?? 'AI Mode') . ' — Devil AI' ?></title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="manifest" href="manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Devil AI">
<link rel="stylesheet" href="assets/app.css?v=<?= $ASSET_V ?>">
</head>
<body class="devil-ui<?= $IS_GUEST ? ' guest' : '' ?><?= $ROUTE_MODE === 'agent' ? ' agent-mode' : '' ?><?= $ROUTE_MODE !== 'ai' ? ' alt-mode' : '' ?><?= $IS_CMP ? ' cmp-mode ' . $ROUTE_MODE . '-mode' : '' ?><?= preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', (string)($_GET['chat'] ?? '')) ? ' loading-chat' : '' ?>">

<div id="app">

  <!-- ═══ SIDEBAR ═══ -->
  <aside id="sidebar">
    <div class="sb-top">
      <button class="iconbtn" id="sbToggle" title="Close sidebar"><?= icon('panel-left') ?></button>
      <a class="brand" href="./"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
      <button class="iconbtn" id="themeBtn" title="Switch theme"><?= icon('sun', 17) ?></button>
    </div>
    <button class="newchat" id="newChatBtn"><?= icon('square-pen', 17) ?> New chat</button>
    <a class="sbnav" id="sbBoard" href="leaderboard"><?= icon('trophy', 16) ?> Leaderboard</a>
    <a class="sbnav" id="sbSearchPage" href="history/search"><?= icon('search', 16) ?> Search</a>
    <div class="sb-search"><?= icon('search', 15) ?><input id="searchInp" type="text" placeholder="Search chats…" autocomplete="off"></div>
    <div class="sb-filter" id="sbFilter" role="tablist" aria-label="Filter chats by mode">
      <button type="button" data-f="all" class="on">All</button>
      <button type="button" data-f="ai" title="AI Mode chats"><?= icon('message', 13) ?><span>AI</span></button>
      <button type="button" data-f="agent" title="Agent Mode chats"><?= icon('spark', 13) ?><span>Agent</span></button>
      <button type="button" data-f="battle" title="Battle chats"><?= icon('swords', 13) ?><span>Battle</span></button>
      <button type="button" data-f="sbs" title="Side by Side chats"><?= icon('columns', 13) ?><span>SBS</span></button>
    </div>
    <nav id="chatList" aria-label="Chat history"></nav>
<?php if ($IS_GUEST): ?>
    <div class="guestcard">
      <b>Save your chats</b>
      <span>Log in to keep your chat history, use Agent Mode and vote in Battles.</span>
      <a class="btn primary" href="login.php?next=<?= rawurlencode($REQ_PATH ?: '/') ?>">Log in</a>
    </div>
<?php endif; ?>
    <div class="sb-legal"><a href="how-it-works">How it works</a><a href="faq">FAQ</a><a href="blog">Blog</a><a href="company/about">About</a><a href="terms-of-use">Terms</a><a href="privacy-policy">Privacy</a><a href="cookie-policy">Cookies</a></div>
    <div class="sb-bottom">
<?php if ($IS_GUEST): ?>
      <div id="userMenu">
        <button class="mi" id="mKbd" type="button"><?= icon('keyboard', 16) ?> Keyboard shortcuts</button>
        <button class="mi" id="mCookies" type="button"><?= icon('cookie', 16) ?> Cookie settings</button>
        <hr>
        <a class="mi" href="login.php?next=<?= rawurlencode($REQ_PATH ?: '/') ?>"><?= icon('user', 16) ?> Log in</a>
        <button class="mi" id="mDelAcc" type="button" hidden></button><button class="mi" id="mLogout" type="button" hidden></button>
      </div>
      <div class="guestrow">
        <a class="userbtn guestlogin" href="login.php?next=<?= rawurlencode($REQ_PATH ?: '/') ?>" id="guestLoginBtn"><span class="av"><?= icon('user', 15) ?></span><span class="nm"><b>Log in</b><span>Sign up or log in</span></span></a>
        <button class="iconbtn" id="userBtn" type="button" title="More" aria-haspopup="menu" aria-expanded="false"><?= icon('settings', 16) ?></button>
      </div>
<?php else: ?>
      <div id="userMenu">
        <?php if (strtolower((string)($me['email'] ?? '')) === 'bk.w.p.bk@gmail.com'): ?><a class="mi" href="admin.php"><?= icon('shield-check', 16) ?> Admin control</a><?php endif; ?>
        <a class="mi" href="developers.php"><?= icon('code', 16) ?> Developer API</a>
        <a class="mi" href="settings.php"><?= icon('settings', 16) ?> Account settings</a>
        <button class="mi" id="mKbd" type="button"><?= icon('keyboard', 16) ?> Keyboard shortcuts</button>
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
<?php endif; ?>
    </div>
  </aside>
  <div id="backdrop"></div>

  <!-- Agent Mode icon rail (shown when the sidebar is collapsed) -->
  <nav id="agentRail" aria-label="Quick navigation">
    <button class="rbtn rtop" id="railOpen" type="button" title="Open sidebar"><?= icon('panel-left', 18) ?></button>
    <button class="rbtn" id="railNew" type="button" title="New chat"><?= icon('square-pen', 17) ?></button>
    <button class="rbtn" id="railHistory" type="button" title="Chat history"><?= icon('list', 17) ?></button>
    <a class="rbtn" id="railBoard" href="leaderboard" title="Leaderboard"><?= icon('trophy', 17) ?></a>
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
      <button class="ctbtn" id="exportBtn" type="button" title="Export chat (Markdown / PDF)" aria-haspopup="menu"><?= icon('download', 17) ?><span class="txt">Export</span></button>
      <button class="ctbtn" id="topNewChatBtn" type="button" title="New chat"><?= icon('square-pen', 17) ?><span class="txt">New</span></button>
      <button class="ctbtn" id="wsBtn" type="button" title="Workspace — files, app preview and activity" aria-expanded="false"><?= icon('folder', 18) ?><span class="txt">Workspace</span></button>
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
            <div class="cmppick" id="cmpPickA" data-side="a"><button type="button" class="cmpbtn" aria-haspopup="menu"><span class="cmpab">A</span><span class="lb">Gemini 3.5 Flash Lite</span><?= icon('chevron-down', 13) ?></button><div class="cmpmenu" role="menu"></div></div>
            <button type="button" id="cmpSwap" class="cmpswap" title="Swap model A and B" aria-label="Swap model A and B"><?= icon('swap', 15) ?></button>
            <div class="cmppick" id="cmpPickB" data-side="b"><button type="button" class="cmpbtn" aria-haspopup="menu"><span class="cmpab">B</span><span class="lb">Gemini 3.6 Flash</span><?= icon('chevron-down', 13) ?></button><div class="cmpmenu" role="menu"></div></div>
            <button id="voiceBtn" title="Live voice chat" type="button" aria-pressed="false"><?= icon('mic', 16) ?></button>
            <button id="promptBtn" title="Prompt library" type="button"><?= icon('lightbulb', 16) ?></button>
            <div id="promptMenu"></div>
            <button id="modelBtn" title="Choose model"><span id="modelIco"><?= icon('zap', 14) ?></span><span class="lb" id="modelLbl">Gemini 3.5 Flash Lite</span><?= icon('chevron-down', 13) ?></button>
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
      <div id="agentTasks" aria-label="Agent task ideas">
        <button type="button" class="atask" data-fill="Research the latest news about "><span class="ai"><?= icon('globe', 16) ?></span><span><b>Research a topic</b><small>Latest news, cited sources</small></span></button>
        <button type="button" class="atask" data-fill="Compare the current prices and specs of "><span class="ai"><?= icon('layers', 16) ?></span><span><b>Compare products</b><small>Prices, specs, pros &amp; cons</small></span></button>
        <button type="button" class="atask" data-fill="Read this page and summarize the key points: https://"><span class="ai"><?= icon('file-text', 16) ?></span><span><b>Summarize a page</b><small>Paste any public link</small></span></button>
        <button type="button" class="atask" data-fill="Plan a 3-day trip to  with a day-by-day itinerary and a budget in INR"><span class="ai"><?= icon('list', 16) ?></span><span><b>Plan a trip</b><small>Day-by-day itinerary + budget</small></span></button>
        <button type="button" class="atask" data-fill="Fact-check this claim with sources: "><span class="ai"><?= icon('shield-check', 16) ?></span><span><b>Fact-check a claim</b><small>Verify with real sources</small></span></button>
        <button type="button" class="atask" data-fill="Calculate the EMI for a loan of ₹10,00,000 at 9% for 5 years and show the formula"><span class="ai"><?= icon('calculator', 16) ?></span><span><b>Crunch numbers</b><small>EMI, percentages, conversions</small></span></button>
      </div>
      <div class="starters" id="starters" aria-label="Get started">
        <button type="button" data-start="Create a sleek, modern landing page for a coffee shop called Brew & Bean — hero, menu highlights, testimonials and a contact section."><?= icon('monitor', 14) ?> Create a landing page</button>
        <button type="button" data-start="Build an interactive sales dashboard with charts for revenue, orders and top products, using sample data."><?= icon('gauge', 14) ?> Build a dashboard</button>
        <button type="button" data-start="Make a playable browser game: a colourful Snake game with score, levels and a restart button."><?= icon('play', 14) ?> Make a game</button>
        <button type="button" data-start="Build a full-stack to-do app with a small backend API, saving tasks, filters and a clean UI."><?= icon('layers', 14) ?> Build a fullstack app</button>
        <button type="button" data-start="Create a beautiful online shop for handmade jewellery — product grid, product page, cart and checkout form."><?= icon('sparkles', 14) ?> Launch a storefront</button>
      </div>
      <p class="hint"><span class="hk">Enter = new line • Ctrl/⌘ + Enter = send • </span>Devil AI can make mistakes.</p>
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
  <div class="shead"><h3><?= icon('trophy', 17) ?> Devil Leaderboard</h3><button class="iconbtn" data-close="lbModal"><?= icon('x', 16) ?></button></div>
  <div class="lbtabs" id="lbTabs" role="tablist"><button type="button" class="on" data-lbt="board"><?= icon('trophy', 14) ?> Leaderboard</button><button type="button" data-lbt="mine"><?= icon('user', 14) ?> My votes</button></div>
  <p class="snote" id="lbNote">Rankings come from anonymous Battle Mode votes (Elo score).</p>
  <div id="lbBody" class="lbbody"></div>
  <div class="btnrow"><button class="btn primary" id="lbBattle" type="button"><?= icon('swords', 15) ?> Start a battle</button><button class="btn ghost" data-close="lbModal">Close</button></div>
</div></div>

<!-- ═══ keyboard shortcuts ═══ -->
<div class="modal hidden" id="kbdModal"><div class="sheet kbdsheet">
  <div class="shead"><h3><?= icon('keyboard', 17) ?> Keyboard shortcuts</h3><button class="iconbtn" data-close="kbdModal"><?= icon('x', 16) ?></button></div>
  <div class="kbdlist">
    <div class="kgrp">General</div>
    <div class="krow"><span>Search chats</span><span class="keys"><kbd class="kmod">Ctrl</kbd><kbd>K</kbd></span></div>
    <div class="krow"><span>New chat</span><span class="keys"><kbd class="kmod">Ctrl</kbd><kbd>Shift</kbd><kbd>O</kbd></span></div>
    <div class="krow"><span>Toggle sidebar</span><span class="keys"><kbd class="kmod">Ctrl</kbd><kbd>Shift</kbd><kbd>S</kbd></span></div>
    <div class="krow"><span>Focus the message box</span><span class="keys"><kbd>/</kbd> or <kbd>Shift</kbd><kbd>Esc</kbd></span></div>
    <div class="krow"><span>Send message</span><span class="keys"><kbd class="kmod">Ctrl</kbd><kbd>Enter</kbd></span></div>
    <div class="krow"><span>Show shortcuts</span><span class="keys"><kbd class="kmod">Ctrl</kbd><kbd>/</kbd></span></div>
    <div class="krow"><span>Close menus and dialogs</span><span class="keys"><kbd>Esc</kbd></span></div>
    <div class="kgrp">Battle &amp; Side by Side</div>
    <div class="krow"><span>A is better</span><span class="keys"><kbd>1</kbd></span></div>
    <div class="krow"><span>B is better</span><span class="keys"><kbd>2</kbd></span></div>
    <div class="krow"><span>It’s a tie</span><span class="keys"><kbd>3</kbd></span></div>
    <div class="krow"><span>Both are bad</span><span class="keys"><kbd>4</kbd></span></div>
  </div>
  <div class="btnrow"><button class="btn ghost" data-close="kbdModal">Close</button></div>
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

<?php if ($IS_GUEST): ?>
<!-- ═══ log-in prompt (signed-out visitors, shown when they try to send) ═══ -->
<div class="modal hidden" id="guestModal" role="dialog" aria-modal="true" aria-labelledby="guestTitle"><div class="sheet guestbox">
  <button class="iconbtn gclose" data-close="guestModal" aria-label="Close"><?= icon('x', 16) ?></button>
  <img class="glogo" src="assets/logo.svg" alt="">
  <h3 id="guestTitle">Log in to continue</h3>
  <p>Create a free account or log in to chat with the models, run the Agent and vote in Battles. Your message will be waiting for you.</p>
  <a class="btn primary gbtn" id="guestEmail" href="login.php"><?= icon('mail', 16) ?> Continue with email</a>
  <a class="btn ghost gbtn" id="guestGithub" href="login.php?github_start=1"><?= icon('github', 16) ?> Continue with GitHub</a>
  <small>By continuing you agree to our <a href="terms-of-use">Terms of Use</a> and <a href="privacy-policy">Privacy Policy</a>.</small>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/inc/cookiebar.php'; ?>

<script>
const I = <?= json_encode($JS_ICONS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const ME = <?= json_encode(['name' => $me['name'], 'email' => $me['email']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const APP_BASE_PATH = <?= json_encode($APP_BASE_PATH, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const ROUTE_MODE = <?= json_encode($ROUTE_MODE) ?>;
window.__GUEST = <?= $IS_GUEST ? 'true' : 'false' ?>;
window.__BOOT = <?= json_encode($BOOT ?: new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const INITIAL_CHAT_ID = <?= json_encode(preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', (string)($_GET['chat'] ?? '')) ? (string)$_GET['chat'] : '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const INITIAL_VARIANT = <?= json_encode((preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', (string)($_GET['variant'] ?? '')) || (string)($_GET['variant'] ?? '') === 'original') ? (string)$_GET['variant'] : '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="assets/app.js?v=<?= $ASSET_V ?>"></script>
<script>
if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); }); }
</script>
</body>
</html>
