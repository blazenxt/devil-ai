<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — page shell for the public site pages
 *  (leaderboard, search, how it works, FAQ, blog, company, legal).
 *  Same sidebar + theme as the chat app, so every page feels like one product.
 * ═══════════════════════════════════════════════════════
 */
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/icons.php';

function site_base(): string {
    /* site.php lives in the app root; pretty URLs (/company/about) need an absolute base */
    $b = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/site.php'))), '/');
    return ($b === '.' || $b === '/') ? '' : $b;
}

function site_me(): ?array {
    static $me = false;
    if ($me !== false) { return $me; }
    $me = null;
    if (isset($_SESSION['devil_uid'])) {
        $users = json_decode((string)@file_get_contents(dirname(__DIR__) . '/data/users.json'), true);
        if (is_array($users) && isset($users[$_SESSION['devil_uid']])) {
            $u = $users[$_SESSION['devil_uid']];
            $me = ['id' => (string)$_SESSION['devil_uid'], 'name' => (string)($u['name'] ?? 'Devil'), 'email' => (string)($u['email'] ?? '')];
        }
    }
    return $me;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* $active: leaderboard | search | how-it-works | faq | blog | about | careers | legal */
function site_head(string $title, string $active, string $desc = ''): void {
    $base = site_base();
    $me = site_me();
    $v = substr(md5(implode('|', array_map(static function ($f) { return @filemtime($f) . ':' . @filesize($f); }, [dirname(__DIR__) . '/assets/app.css', dirname(__DIR__) . '/assets/site.css']))), 0, 10);
    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $nav = function (string $id, string $href, string $ic, string $label) use ($active) {
        return '<a class="sbnav' . ($active === $id ? ' on' : '') . '" href="' . h($href) . '"' . ($active === $id ? ' aria-current="page"' : '') . '>' . icon($ic, 16) . ' ' . h($label) . '</a>';
    };
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<base href="<?= h($base . '/') ?>">
<meta name="theme-color" content="#faf9f7">
<script>/* theme boot — runs before paint to avoid a flash of the wrong theme */
(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}
document.documentElement.setAttribute('data-theme',t);})();</script>
<title><?= h($title) ?> — Devil AI</title>
<?php if ($desc !== ''): ?><meta name="description" content="<?= h($desc) ?>"><?php endif; ?>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="manifest" href="manifest.webmanifest">
<link rel="stylesheet" href="assets/app.css?v=<?= $v ?>">
<link rel="stylesheet" href="assets/site.css?v=<?= $v ?>">
</head>
<body class="devil-ui site-page<?= $me ? '' : ' guest' ?>">
<div id="app">
  <aside id="sidebar">
    <div class="sb-top">
      <button class="iconbtn" id="sbToggle" type="button" title="Close sidebar"><?= icon('panel-left') ?></button>
      <a class="brand" href="./"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
      <button class="iconbtn" id="themeBtn" type="button" title="Switch theme"><?= icon('sun', 17) ?></button>
    </div>
    <a class="newchat" href="./"><?= icon('square-pen', 17) ?> New chat</a>
    <?= $nav('leaderboard', 'leaderboard', 'trophy', 'Leaderboard') ?>
    <?= $nav('search', 'history/search', 'search', 'Search') ?>
    <div class="sb-sect">Explore</div>
    <?= $nav('how-it-works', 'how-it-works', 'lightbulb', 'How it works') ?>
    <?= $nav('faq', 'faq', 'help-circle', 'FAQ') ?>
    <?= $nav('blog', 'blog', 'file-text', 'Blog') ?>
    <?= $nav('about', 'company/about', 'flame', 'About') ?>
    <?= $nav('careers', 'company/careers', 'user', 'Careers') ?>
    <div class="sb-fill"></div>
<?php if (!$me): ?>
    <div class="guestcard">
      <b>Save your chats</b>
      <span>Log in to keep your chat history, use Agent Mode and vote in Battles.</span>
      <a class="btn primary" href="login.php?next=<?= rawurlencode($path ?: '/') ?>">Log in</a>
    </div>
<?php endif; ?>
    <div class="sb-legal"><a href="terms-of-use">Terms of Use</a><a href="privacy-policy">Privacy Policy</a><a href="cookie-policy">Cookies</a></div>
    <div class="sb-bottom">
<?php if ($me): ?>
      <a class="userbtn" href="settings.php" title="Account settings">
        <span class="av"><?= h(strtoupper(mb_substr($me['name'], 0, 1))) ?></span>
        <span class="nm"><b><?= h($me['name']) ?></b><span><?= h($me['email']) ?></span></span>
        <span class="chev"><?= icon('settings', 15) ?></span>
      </a>
<?php else: ?>
      <a class="userbtn guestlogin" href="login.php?next=<?= rawurlencode($path ?: '/') ?>"><span class="av"><?= icon('user', 15) ?></span><span class="nm"><b>Log in</b><span>Sign up or log in</span></span></a>
<?php endif; ?>
    </div>
  </aside>
  <div id="backdrop"></div>
  <main class="site-main">
    <header class="m-top site-top">
      <button class="iconbtn" id="sbOpen" type="button" title="Open sidebar"><?= icon('menu', 19) ?></button>
      <a class="brand" href="./"><img src="assets/logo.svg" alt="Devil AI logo">Devil AI</a>
      <a class="btn primary site-cta" href="./"><?= icon('square-pen', 15) ?> New chat</a>
    </header>
    <div class="site-scroll" id="siteScroll"><div class="site-wrap">
<?php
}

function site_foot(): void {
    ?>
    </div>
    <footer class="site-foot">
      <div class="sf-brand"><img src="assets/logo.svg" alt=""> <b>Devil AI</b> <span>by BlazeNXT</span></div>
      <nav>
        <a href="./">Chat</a><a href="leaderboard">Leaderboard</a><a href="how-it-works">How it works</a><a href="faq">FAQ</a><a href="blog">Blog</a>
        <a href="company/about">About</a><a href="company/careers">Careers</a><a href="terms-of-use">Terms of Use</a><a href="privacy-policy">Privacy Policy</a><a href="cookie-policy">Cookie Policy</a>
      </nav>
      <p>Inputs are processed by third-party AI and responses may be inaccurate. © <?= date('Y') ?> BlazeNXT.</p>
    </footer>
    </div>
  </main>
</div>
<?php require dirname(__DIR__) . '/inc/cookiebar.php'; ?>
<script>
(function () {
  var SUN = <?= json_encode(icon('sun', 17)) ?>, MOON = <?= json_encode(icon('moon', 17)) ?>;
  var sb = document.getElementById('sidebar'), bd = document.getElementById('backdrop');
  function narrow() { return window.innerWidth <= 900; }
  function setSb(open) {
    sb.classList.toggle('closed', !open);
    document.body.classList.toggle('sb-closed', !open);
    bd.classList.toggle('show', open && narrow());
  }
  if (narrow()) { setSb(false); }
  document.getElementById('sbToggle').addEventListener('click', function () { setSb(false); });
  document.getElementById('sbOpen').addEventListener('click', function () { setSb(true); });
  bd.addEventListener('click', function () { setSb(false); });
  /* theme toggle (respects the personalization cookie choice) */
  var b = document.getElementById('themeBtn');
  function cur() { return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'; }
  function setIco() { b.innerHTML = cur() === 'dark' ? SUN : MOON; }
  b.addEventListener('click', function () {
    var t = cur() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', t);
    try {
      var prefs = JSON.parse(localStorage.getItem('devil_cookie_prefs') || 'null');
      if (!prefs || prefs.personalization !== false) {
        localStorage.setItem('devil_theme', t);
        if (window.devilCookieSet) { window.devilCookieSet('devil_theme', t, 365); }
        else { document.cookie = 'devil_theme=' + encodeURIComponent(t) + '; Max-Age=31536000; Path=/; SameSite=Lax'; }
      }
    } catch (e) {}
    setIco();
  });
  setIco();
  /* tabs ([data-tabs] → buttons[data-tab] show [data-pane]) */
  document.querySelectorAll('[data-tabs]').forEach(function (g) {
    g.querySelectorAll('[data-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        g.querySelectorAll('[data-tab]').forEach(function (x) { x.classList.toggle('on', x === btn); x.setAttribute('aria-selected', x === btn ? 'true' : 'false'); });
        document.querySelectorAll('[data-pane="' + g.dataset.tabs + '"]').forEach(function (p) { p.hidden = p.dataset.id !== btn.dataset.tab; });
      });
    });
  });
})();
</script>
</body>
</html>
<?php
}
