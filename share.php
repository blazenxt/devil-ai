<?php
/** Devil AI — Public shared chat page */
require_once __DIR__ . '/inc/session.php';
devil_session_boot();
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/share_safety.php';

function share_load_json(string $path): array {
    if (!is_readable($path)) { return []; }
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}
function share_md_inline(string $s): string {
    $keep = [];
    $hold = static function (string $h) use (&$keep): string { $keep[] = $h; return "\x00" . (count($keep) - 1) . "\x00"; };
    $s = preg_replace_callback('/`([^`]+)`/u', static function ($m) use ($hold) { return $hold('<code>' . $m[1] . '</code>'); }, $s);
    $s = preg_replace_callback('/\\\\{1,2}([\\\\`*_{}\[\]()#+\-.!|~])/u', static function ($m) use ($hold) { return $hold($m[1]); }, $s);
    $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/u', static function ($m) use ($hold) {
        return $hold('<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer nofollow">' . $m[1] . '</a>');
    }, $s);
    $s = preg_replace_callback('/(^|[\s(])(https?:\/\/[^\s<]+)/u', static function ($m) use ($hold) {
        $u = $m[2]; $tail = '';
        while ($u !== '' && strpos('.,;:!?)\'"', substr($u, -1)) !== false) { $tail = substr($u, -1) . $tail; $u = substr($u, 0, -1); }
        return $m[1] . $hold('<a href="' . $u . '" target="_blank" rel="noopener noreferrer nofollow">' . $u . '</a>') . $tail;
    }, $s);
    $s = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $s);
    $s = preg_replace('/(^|[^\w*])\*([^*\s][^*]*?)\*(?!\w)/u', '$1<em>$2</em>', $s);
    return preg_replace_callback('/\x00(\d+)\x00/', static function ($m) use (&$keep) { return $keep[(int)$m[1]]; }, $s);
}
/* small safe markdown for shared chats: escape first, then headings, lists, code blocks, links, bold/italic */
function share_md(string $text): string {
    $lines = explode("\n", htmlspecialchars(str_replace(["\r\n", "\r"], "\n", $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $out = []; $para = []; $list = ''; $code = null;
    $flushP = static function () use (&$para, &$out) { if ($para) { $out[] = '<p>' . implode('<br>', array_map('share_md_inline', $para)) . '</p>'; $para = []; } };
    $closeL = static function () use (&$list, &$out) { if ($list !== '') { $out[] = '</' . $list . '>'; $list = ''; } };
    foreach ($lines as $line) {
        if ($code !== null) {
            if (preg_match('/^\s*(```|~~~)/', $line)) { $out[] = '<pre><code>' . implode("\n", $code) . '</code></pre>'; $code = null; } else { $code[] = $line; }
            continue;
        }
        if (preg_match('/^\s*(```|~~~)/', $line)) { $flushP(); $closeL(); $code = []; continue; }
        if (trim($line) === '') { $flushP(); $closeL(); continue; }
        if (preg_match('/^\s*(#{1,6})\s+(.*)$/', $line, $m)) { $flushP(); $closeL(); $n = min(6, strlen($m[1]) + 1); $out[] = "<h{$n}>" . share_md_inline($m[2]) . "</h{$n}>"; continue; }
        if (preg_match('/^\s*(?:\\\\{1,2}(?=[-*+]))?([-*+]|\d{1,3}\\\\?[.)])\s+(.*)$/', $line, $m)) {
            $flushP(); $want = ctype_digit($m[1][0]) ? 'ol' : 'ul';
            if ($list !== $want) { $closeL(); $out[] = '<' . $want . '>'; $list = $want; }
            $out[] = '<li>' . share_md_inline($m[2]) . '</li>'; continue;
        }
        $closeL(); $para[] = $line;
    }
    if ($code !== null) { $out[] = '<pre><code>' . implode("\n", $code) . '</code></pre>'; }
    $flushP(); $closeL();
    return implode("\n", $out);
}
function share_fmt_size(int $n): string {
    if ($n <= 0) { return ''; }
    if ($n < 1024) { return $n . ' B'; }
    if ($n < 1024 * 1024) { return (string)round($n / 1024) . ' KB'; }
    return rtrim(rtrim(number_format($n / 1024 / 1024, 1), '0'), '.') . ' MB';
}
function share_transcript(array $share): string {
    $out = [];
    $title = (string)($share['title'] ?? 'Shared chat');
    $out[] = devil_share_contains_secret($title) ? 'Shared chat' : $title;
    $out[] = 'Shared from Devil AI';
    $out[] = str_repeat('=', 24);
    foreach (($share['messages'] ?? []) as $m) {
        if (!is_array($m)) { continue; }
        $role = (string)($m['role'] ?? '');
        if ($role !== 'user' && $role !== 'assistant') { continue; }
        $m = devil_share_public_message($m);
        $name = $role === 'assistant' ? 'Devil AI' : 'User';
        $out[] = "\n" . $name . ':';
        $content = trim((string)($m['content'] ?? ''));
        if ($content !== '') { $out[] = $content; }
        if (!empty($m['attachments']) && is_array($m['attachments'])) {
            foreach ($m['attachments'] as $a) {
                if (!is_array($a)) { continue; }
                $out[] = '[Attachment] ' . (string)($a['name'] ?? 'file') . ' ' . (string)($a['type'] ?? '');
            }
        }
    }
    return implode("\n", $out);
}

$APP_BASE_PATH = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/share.php'))), '/');
if ($APP_BASE_PATH === '.' || $APP_BASE_PATH === '/') { $APP_BASE_PATH = ''; }
$id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['id']) : '';
$share = $id ? share_load_json(__DIR__ . '/data/shares/' . $id . '.json') : [];
$ok = $share && isset($share['messages']) && is_array($share['messages']);
$redactedCount = 0;
if ($ok) {
    foreach ($share['messages'] as $i => $m) {
        if (!is_array($m)) { continue; }
        $share['messages'][$i] = devil_share_public_message($m);
        if (!empty($share['messages'][$i]['share_redacted']) || !empty($m['redacted'])) { $redactedCount++; }
    }
}
$title = $ok ? (string)($share['title'] ?? 'Shared chat') : 'Shared chat not found';
if ($ok && devil_share_contains_secret($title)) { $title = 'Shared chat'; }
$transcript = $ok ? share_transcript($share) : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<base href="<?= htmlspecialchars(($APP_BASE_PATH ?: '') . '/', ENT_QUOTES) ?>">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0c0709">
<script>(function(){function ck(n){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):null;}var t=ck('devil_theme');try{t=t||localStorage.getItem('devil_theme');}catch(e){}if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';}document.documentElement.setAttribute('data-theme',t);})();</script>
<title><?= htmlspecialchars($title) ?> — Devil AI Share</title>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="manifest" href="manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Devil AI">
<style>
*{box-sizing:border-box;margin:0;padding:0}:root{--bg:#0c0709;--panel:#171014;--panel2:#1d1216;--border:rgba(244,63,94,.16);--border-hi:rgba(244,63,94,.45);--pink:#fb7185;--soft:#fda4af;--text:#efe6ea;--dim:#a8929b;--dim2:#7c5b63;--serif:Georgia,'Times New Roman',serif;--sans:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif}[data-theme=light]{--bg:#faf9f7;--panel:#fff;--panel2:#f0ede9;--border:rgba(120,80,90,.18);--border-hi:rgba(190,30,60,.45);--pink:#c2415f;--soft:#a63d57;--text:#262023;--dim:#6e5f65;--dim2:#82696f}body{min-height:100dvh;background:radial-gradient(1000px 480px at 70% -10%,rgba(225,29,72,.10),transparent 60%),var(--bg);color:var(--text);font-family:var(--sans)}a{text-decoration:none;color:inherit}.top{position:sticky;top:0;z-index:20;background:rgba(12,7,9,.82);backdrop-filter:blur(14px);border-bottom:1px solid var(--border)}[data-theme=light] .top{background:rgba(250,249,247,.88)}.topin{max-width:900px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}.brand{display:flex;align-items:center;gap:10px;font-weight:800}.brand img{width:30px;height:30px}.actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}.btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:12px;padding:9px 14px;color:var(--soft);font-size:.84rem;background:transparent;cursor:pointer}.btn:hover{background:rgba(244,63,94,.10)}main{max-width:900px;margin:0 auto;padding:38px 20px 70px}.hero{margin-bottom:28px}.hero .pill{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:999px;padding:6px 12px;color:var(--soft);font-size:.76rem;background:rgba(244,63,94,.06)}h1{font-family:var(--serif);font-weight:500;font-size:clamp(1.8rem,4vw,2.8rem);margin-top:14px}.meta{color:var(--dim2);font-size:.82rem;margin-top:8px}.thread{background:var(--panel);border:1px solid var(--border);border-radius:22px;padding:22px;box-shadow:0 22px 70px rgba(0,0,0,.28)}[data-theme=light] .thread{box-shadow:0 22px 70px rgba(120,80,90,.14)}.msg{display:flex;gap:12px;margin:24px 0}.msg.user{justify-content:flex-end}.bubble{max-width:78%;border:1px solid var(--border);border-radius:18px;padding:12px 15px;line-height:1.7;font-size:.94rem;overflow-wrap:anywhere}.user .bubble{background:var(--panel2);border-bottom-right-radius:6px}.assistant .bubble{background:transparent;border-color:transparent;padding:0;max-width:100%}.ava{width:30px;height:30px;border-radius:50%;border:1px solid var(--border);background:var(--panel2);display:flex;align-items:center;justify-content:center;flex-shrink:0}.ava img{width:22px;height:22px}.who{display:flex;align-items:center;gap:8px;margin-bottom:5px;font-weight:700;font-size:.86rem}.mtag{font-size:.66rem;color:var(--soft);border:1px solid var(--border);border-radius:999px;padding:2px 8px;font-weight:600}.content{color:var(--text)}.content strong{color:var(--text)}code{background:var(--panel2);border:1px solid var(--border);border-radius:6px;padding:.12em .4em;color:var(--soft)}.msg-img{display:block;max-width:min(260px,100%);max-height:280px;border-radius:14px;border:1px solid var(--border);margin-bottom:8px;object-fit:cover}.files{display:flex;gap:7px;flex-wrap:wrap;margin-top:9px}.user .files{justify-content:flex-end}.file{display:inline-flex;align-items:center;gap:7px;max-width:260px;border:1px solid var(--border);background:rgba(244,63,94,.08);color:var(--soft);border-radius:999px;padding:5px 9px;font-size:.72rem;font-weight:700}.file span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}.file small{color:var(--dim2);font-weight:600}.empty{background:var(--panel);border:1px solid var(--border);border-radius:20px;padding:30px;text-align:center;color:var(--dim)}.privacy-note{margin:-12px 0 16px;padding:10px 13px;border:1px solid var(--border);border-radius:10px;background:var(--panel2);color:var(--dim);font-size:.82rem;line-height:1.5}.toast{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);background:var(--panel2);border:1px solid var(--border-hi);border-radius:12px;color:var(--text);padding:10px 14px;display:none;z-index:50;font-size:.84rem}.content p,.content ul,.content ol,.content pre,.bubble p{margin:.55em 0}.content>:first-child,.bubble>:first-child{margin-top:0}.content ul,.content ol{padding-left:1.4em}.content li{margin:.2em 0}.content h2,.content h3,.content h4,.content h5,.content h6{font-family:var(--sans);font-weight:700;line-height:1.3;margin:1em 0 .45em}.content h2{font-size:1.3rem}.content h3{font-size:1.12rem}.content h4,.content h5,.content h6{font-size:1rem}.content pre{background:var(--panel2);border:1px solid var(--border);border-radius:10px;padding:12px 14px;overflow:auto;font-size:.84rem;line-height:1.55}.content pre code{background:none;border:0;padding:0;color:var(--text)}.content a,.bubble a{color:var(--pink);text-decoration:underline;text-underline-offset:2px}.content em{font-style:italic}@media(max-width:650px){.bubble{max-width:88%}.thread{padding:14px}.topin{padding:12px 14px;align-items:flex-start}.actions{gap:6px}.btn{padding:8px 10px}.file{max-width:210px}}
</style>
</head>
<body>
<div class="top"><div class="topin"><a class="brand" href="./"><img src="assets/logo.svg" alt="Devil AI">Devil AI</a><div class="actions"><?php if ($ok): ?><button class="btn" id="copyTranscript" type="button"><?= icon('copy', 15) ?> Copy chat</button><?php endif; ?><a class="btn" href="login.php"><?= icon('message', 15) ?> Open Devil AI</a></div></div></div>
<main>
<?php if (!$ok): ?>
  <div class="empty"><h1>Shared chat not found</h1><p class="meta">This link may be wrong, expired, or the share may have been removed.</p><p class="meta">If you copied this from Devil AI, create a fresh share link and try again.</p></div>
<?php else: ?>
  <section class="hero"><span class="pill"><?= icon('share', 14) ?> Public shared chat</span><h1><?= htmlspecialchars($title) ?></h1><p class="meta">Shared by <?= htmlspecialchars((string)($share['shared_by']['name'] ?? 'Devil AI user')) ?><?= !empty($share['created']) ? ' • ' . date('d M Y', (int)$share['created']) : '' ?><?= !empty($share['message_count']) ? ' • ' . (int)$share['message_count'] . ' messages' : '' ?></p></section>
  <?php if ($redactedCount): ?><p class="privacy-note"><?= (int)$redactedCount ?> message<?= $redactedCount === 1 ? ' was' : 's were' ?> hidden because it contained login credentials or secret details.</p><?php endif; ?>
  <section class="thread">
  <?php foreach ($share['messages'] as $m): $role = (string)($m['role'] ?? ''); if ($role !== 'user' && $role !== 'assistant') { continue; } ?>
    <?php if ($role === 'user'): ?>
      <div class="msg user"><div class="bubble"><?php if (!empty($m['img'])): ?><img class="msg-img" src="<?= htmlspecialchars((string)$m['img']) ?>" alt="Attached image"><?php endif; ?><?= share_md((string)($m['content'] ?? '')) ?><?php if (!empty($m['attachments']) && is_array($m['attachments'])): ?><div class="files"><?php foreach ($m['attachments'] as $a): if (!is_array($a)) { continue; } ?><span class="file"><?= icon('paperclip', 13) ?><span><?= htmlspecialchars((string)($a['name'] ?? 'attachment')) ?></span><small><?= htmlspecialchars(share_fmt_size((int)($a['size'] ?? 0))) ?></small></span><?php endforeach; ?></div><?php endif; ?></div></div>
    <?php else: ?>
      <div class="msg assistant"><div class="ava"><img src="assets/logo.svg" alt=""></div><div class="bubble"><div class="who">Devil AI<?php if (!empty($m['model_label'])): ?><span class="mtag"><?= htmlspecialchars((string)$m['model_label']) ?></span><?php endif; ?></div><div class="content"><?= share_md((string)($m['content'] ?? '')) ?></div></div></div>
    <?php endif; ?>
  <?php endforeach; ?>
  </section>
  <textarea id="transcript" hidden><?= htmlspecialchars($transcript, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
<?php endif; ?>
</main>
<div class="toast" id="toast">Copied</div>
<script>
(function(){
  var b=document.getElementById('copyTranscript'), t=document.getElementById('transcript'), toast=document.getElementById('toast');
  function show(msg){ if(!toast) return; toast.textContent=msg; toast.style.display='block'; setTimeout(function(){toast.style.display='none';},2200); }
  if(b&&t){ b.addEventListener('click',function(){ var text=t.value||''; (navigator.clipboard?navigator.clipboard.writeText(text):Promise.reject()).then(function(){show('Chat copied');},function(){t.hidden=false;t.select();show('Select and copy the transcript');}); }); }
})();
</script>
<script>
if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); }); }
</script>
</body>
</html>
