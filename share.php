<?php
/** Devil AI — Public shared chat page */
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
require_once __DIR__ . '/inc/icons.php';

function share_load_json(string $path): array {
    if (!is_readable($path)) { return []; }
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}
function share_md(string $text): string {
    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $safe);
    $safe = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $safe);
    return nl2br($safe);
}

$id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['id']) : '';
$share = $id ? share_load_json(__DIR__ . '/data/shares/' . $id . '.json') : [];
$ok = $share && isset($share['messages']) && is_array($share['messages']);
$title = $ok ? (string)($share['title'] ?? 'Shared chat') : 'Shared chat not found';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0c0709">
<script>(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}document.documentElement.setAttribute('data-theme',t);})();</script>
<title><?= htmlspecialchars($title) ?> — Devil AI Share</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}:root{--bg:#0c0709;--panel:#171014;--panel2:#1d1216;--border:rgba(244,63,94,.16);--border-hi:rgba(244,63,94,.45);--pink:#fb7185;--soft:#fda4af;--text:#efe6ea;--dim:#a8929b;--dim2:#7c5b63;--serif:Georgia,'Times New Roman',serif;--sans:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif}[data-theme=light]{--bg:#faf9f7;--panel:#fff;--panel2:#f0ede9;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f}body{min-height:100dvh;background:radial-gradient(1000px 480px at 70% -10%,rgba(225,29,72,.10),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans)}a{text-decoration:none;color:inherit}.top{position:sticky;top:0;z-index:20;background:rgba(12,7,9,.82);backdrop-filter:blur(14px);border-bottom:1px solid var(--border)}[data-theme=light] .top{background:rgba(250,249,247,.88)}.topin{max-width:860px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}.brand{display:flex;align-items:center;gap:10px;font-weight:800}.brand img{width:30px;height:30px}.btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:12px;padding:9px 14px;color:var(--soft);font-size:.84rem}.btn:hover{background:rgba(244,63,94,.10)}main{max-width:860px;margin:0 auto;padding:38px 20px 70px}.hero{margin-bottom:28px}.hero .pill{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:999px;padding:6px 12px;color:var(--soft);font-size:.76rem;background:rgba(244,63,94,.06)}h1{font-family:var(--serif);font-weight:500;font-size:clamp(1.8rem,4vw,2.8rem);margin-top:14px}.meta{color:var(--dim2);font-size:.82rem;margin-top:8px}.thread{background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:22px;box-shadow:0 22px 70px rgba(0,0,0,.28)}[data-theme=light] .thread{box-shadow:0 22px 70px rgba(120,80,90,.14)}.msg{display:flex;gap:12px;margin:24px 0}.msg.user{justify-content:flex-end}.bubble{max-width:78%;border:1px solid var(--border);border-radius:18px;padding:12px 15px;line-height:1.7;font-size:.94rem;overflow-wrap:anywhere}.user .bubble{background:var(--panel2);border-bottom-right-radius:6px}.assistant .bubble{background:transparent;border-color:transparent;padding:0;max-width:100%}.ava{width:30px;height:30px;border-radius:50%;border:1px solid var(--border);background:var(--panel2);display:flex;align-items:center;justify-content:center;flex-shrink:0}.ava img{width:22px;height:22px}.who{display:flex;align-items:center;gap:8px;margin-bottom:5px;font-weight:700;font-size:.86rem}.mtag{font-size:.66rem;color:var(--soft);border:1px solid var(--border);border-radius:999px;padding:2px 8px;font-weight:600}.content{color:var(--text)}.content strong{color:var(--text)}code{background:var(--panel2);border:1px solid var(--border);border-radius:6px;padding:.12em .4em;color:var(--soft)}.msg-img{display:block;max-width:min(260px,100%);max-height:280px;border-radius:14px;border:1px solid var(--border);margin-bottom:8px;object-fit:cover}.empty{background:var(--panel);border:1px solid var(--border);border-radius:20px;padding:30px;text-align:center;color:var(--dim)}@media(max-width:650px){.bubble{max-width:88%}.thread{padding:14px}.topin{padding:12px 14px}}
</style>
</head>
<body>
<div class="top"><div class="topin"><a class="brand" href="index.php"><img src="assets/logo.svg" alt="Devil AI">Devil AI</a><a class="btn" href="login.php"><?= icon('message', 15) ?> Open Devil AI</a></div></div>
<main>
<?php if (!$ok): ?>
  <div class="empty"><h1>Shared chat not found</h1><p class="meta">This link may be wrong or the share may have been removed.</p></div>
<?php else: ?>
  <section class="hero"><span class="pill"><?= icon('share', 14) ?> Public shared chat</span><h1><?= htmlspecialchars($title) ?></h1><p class="meta">Shared by <?= htmlspecialchars((string)($share['shared_by']['name'] ?? 'Devil AI user')) ?><?= !empty($share['created']) ? ' • ' . date('d M Y', (int)$share['created']) : '' ?></p></section>
  <section class="thread">
  <?php foreach ($share['messages'] as $m): $role = (string)($m['role'] ?? ''); if ($role !== 'user' && $role !== 'assistant') { continue; } ?>
    <?php if ($role === 'user'): ?>
      <div class="msg user"><div class="bubble"><?php if (!empty($m['img'])): ?><img class="msg-img" src="<?= htmlspecialchars((string)$m['img']) ?>" alt="Attached image"><?php endif; ?><?= share_md((string)($m['content'] ?? '')) ?></div></div>
    <?php else: ?>
      <div class="msg assistant"><div class="ava"><img src="assets/logo.svg" alt=""></div><div class="bubble"><div class="who">Devil AI<?php if (!empty($m['model_label'])): ?><span class="mtag"><?= htmlspecialchars((string)$m['model_label']) ?></span><?php endif; ?></div><div class="content"><?= share_md((string)($m['content'] ?? '')) ?></div></div></div>
    <?php endif; ?>
  <?php endforeach; ?>
  </section>
<?php endif; ?>
</main>
</body>
</html>
