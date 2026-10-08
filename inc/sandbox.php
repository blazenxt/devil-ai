<?php
/**
 * Devil AI — Agent sandbox (inc/sandbox.php)
 *
 * Provider-neutral client for the per-chat Linux sandbox used by Agent Mode
 * (bash, files, previews, headless browser). Providers behind the same sbx_* functions:
 *   "daytona" — Daytona cloud sandboxes (inc/sandbox_daytona.php), config sandbox_provider=daytona
 *   "devil"   — the old self-hosted Devil Sandbox gateway (sandbox_url + sandbox_secret)
 *
 * Every call is signed with HMAC-SHA256(sandbox_secret). The sandbox id is
 * derived server-side from (user id, chat id), so a user can only ever reach
 * the sandbox of their own chats.
 */
declare(strict_types=1);

const SBX_WORKDIR = '/home/user/work';
require_once __DIR__ . '/sandbox_daytona.php';
require_once __DIR__ . '/sandbox_vercel.php';

/**
 * Providers: "daytona" | "vercel" | "auto" (Daytona first, Vercel Sandbox as the automatic backup) | "devil" (old gateway).
 * In "auto" every chat is pinned to one backend (data/sbx_route.json) so its files never jump around:
 *  - new chats go to Daytona, unless Daytona failed recently (credit used up, auth/quota error, outage) → Vercel
 *  - a chat whose Daytona sandbox cannot be created/started is moved to Vercel
 *  - the agent can move a chat to Vercel (full internet) with the full_internet tool; its files are copied over
 */
function sbx_provider(array $cfg): string {
    $p = strtolower(trim((string)($cfg['sandbox_provider'] ?? '')));
    return in_array($p, ['daytona', 'vercel', 'auto'], true) ? $p : 'devil';
}
function sbx_has_daytona(array $cfg): bool { return trim((string)($cfg['daytona_api_key'] ?? '')) !== ''; }
function sbx_is_cloud(array $cfg): bool { return sbx_provider($cfg) !== 'devil'; }
/** true when this deployment can use both clouds (auto fail-over / full_internet possible) */
function sbx_dual(array $cfg): bool { return sbx_provider($cfg) === 'auto' && sbx_has_daytona($cfg) && vcl_configured($cfg); }

function sbx_enabled(array $cfg): bool {
    if (empty($cfg['sandbox_enabled'])) { return false; }
    switch (sbx_provider($cfg)) {
        case 'daytona': return sbx_has_daytona($cfg);
        case 'vercel':  return vcl_configured($cfg);
        case 'auto':    return sbx_has_daytona($cfg) || vcl_configured($cfg);
    }
    return trim((string)($cfg['sandbox_url'] ?? '')) !== '' && trim((string)($cfg['sandbox_secret'] ?? '')) !== '';
}

/* ── chat → backend pins (data/sbx_route.json): {sid: "daytona"|"vercel", "sid~why": "failover"|"internet", "_dyt_down": ts} ── */
function sbx_route_path(): string { return dirname(__DIR__) . '/data/sbx_route.json'; }
function sbx_route_all(): array {
    static $cache = null;
    if ($cache !== null && empty($GLOBALS['__sbx_route_dirty'])) { return $cache; }
    $m = is_file(sbx_route_path()) ? json_decode((string)@file_get_contents(sbx_route_path()), true) : [];
    $GLOBALS['__sbx_route_dirty'] = false;
    return $cache = is_array($m) ? $m : [];
}
function sbx_route_get(string $key): string { $m = sbx_route_all(); return isset($m[$key]) ? (string)$m[$key] : ''; }
function sbx_route_set(array $pairs): void {
    $f = sbx_route_path();
    @mkdir(dirname($f), 0755, true);
    $fh = @fopen($f, 'c+');
    if (!$fh) { return; }
    flock($fh, LOCK_EX);
    $m = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($m)) { $m = []; }
    foreach ($pairs as $k => $v) { if ($v === null) { unset($m[$k]); } else { $m[$k] = $v; } }
    ftruncate($fh, 0); rewind($fh); fwrite($fh, (string)json_encode($m, JSON_UNESCAPED_SLASHES));
    flock($fh, LOCK_UN); fclose($fh);
    $GLOBALS['__sbx_route_dirty'] = true;
}
function sbx_daytona_down(): bool { return (int)sbx_route_get('_dyt_down') > time(); }

/** which backend serves this chat right now (no network calls) */
function sbx_backend(array $cfg, string $sid): string {
    $p = sbx_provider($cfg);
    if ($p !== 'auto') { return $p; }
    if (!vcl_configured($cfg)) { return 'daytona'; }
    if (!sbx_has_daytona($cfg)) { return 'vercel'; }
    $r = $sid !== '' ? sbx_route_get($sid) : '';
    if ($r === 'daytona' || $r === 'vercel') { return $r; }
    return sbx_daytona_down() ? 'vercel' : 'daytona';
}

/**
 * backend for an operation that needs a running sandbox; does the Daytona → Vercel fail-over in "auto" mode.
 * $create=false: the operation must not create a sandbox (reading a file).
 */
function sbx_use(array $cfg, string $sid, bool $create = true): string {
    $be = sbx_backend($cfg, $sid);
    if (!sbx_dual($cfg)) { return $be; }
    if ($be === 'vercel') {
        if ($create && sbx_route_get($sid) === '') { sbx_route_set([$sid => 'vercel', $sid . '~why' => 'failover']); }
        return 'vercel';
    }
    $e = dyt_ensure($cfg, $sid, $create);
    if (!empty($e['ok']) || !empty($e['none']) || !empty($e['asleep']) || empty($e['down'])) { return 'daytona'; }
    /* Daytona cannot serve this chat (credit used up, quota/auth error, outage): use the backup for a while */
    $mins = max(2, min(240, (int)($cfg['sandbox_failover_minutes'] ?? 15)));
    sbx_route_set(['_dyt_down' => time() + $mins * 60, '_dyt_down_why' => mb_substr((string)($e['error'] ?? ''), 0, 200), $sid => 'vercel', $sid . '~why' => 'failover']);
    if (function_exists('devil_log')) { @devil_log('sandbox', 'daytona down, using vercel: ' . (string)($e['error'] ?? '')); }
    return 'vercel';
}

/**
 * provider preview URL → our own preview domain (preview_domain + preview_key; proxied by the devil-preview Worker,
 * which also skips Daytona's warning page). Unknown URLs are returned unchanged.
 */
function sbx_public_url(array $cfg, string $url): string {
    $dom = trim((string)($cfg['preview_domain'] ?? ''), " .\t\n");
    $key = (string)($cfg['preview_key'] ?? '');
    if ($dom === '' || $key === '' || $url === '') { return $url; }
    $h = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (preg_match('/^(\d{2,5})-([0-9a-f]{8})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{12})\.daytonaproxy01\.net$/', $h, $m)) {
        $label = 'd' . $m[1] . '-' . $m[2] . $m[3] . $m[4] . $m[5] . $m[6];
    } elseif (preg_match('/^(sb-[a-z0-9]{6,32})\.vercel\.run$/', $h, $m)) {
        $label = 'v-' . $m[1];
    } else {
        return $url;
    }
    $path = (string)parse_url($url, PHP_URL_PATH);
    return 'https://' . $label . '-' . substr(hash_hmac('sha256', $label, $key), 0, 10) . '.' . $dom . ($path !== '/' ? $path : '');
}

/** move a chat from Daytona to Vercel (full internet), copying its workspace. */
function sbx_move_to_vercel(array $cfg, string $sid): array {
    if (sbx_backend($cfg, $sid) === 'vercel') { return ['ok' => true, 'already' => true]; }
    if (!vcl_configured($cfg)) { return ['ok' => false, 'error' => 'the full-internet sandbox is not configured']; }
    $zip = '';
    $e = sbx_has_daytona($cfg) ? dyt_ensure($cfg, $sid, false) : ['ok' => false];
    if (!empty($e['ok'])) {
        $z = dyt_zip($cfg, $sid);
        if (empty($z['ok'])) { return ['ok' => false, 'error' => 'could not pack the current files: ' . (string)($z['error'] ?? '')]; }
        $zip = (string)$z['data'];
    }
    $o = vcl_open($cfg, $sid);
    if (empty($o['ok'])) { return ['ok' => false, 'error' => 'the full-internet sandbox did not start: ' . (string)($o['error'] ?? '')]; }
    $copied = false;
    if (strlen($zip) > 22) {
        $u = vcl_unzip($cfg, $sid, $zip);
        if (empty($u['ok'])) { return ['ok' => false, 'error' => 'could not copy the files: ' . (string)($u['error'] ?? '')]; }
        $copied = true;
    }
    sbx_route_set([$sid => 'vercel', $sid . '~why' => 'internet']);
    if (!empty($e['ok'])) { dyt_purge($cfg, $sid); }   /* the old copy is no longer used */
    return ['ok' => true, 'copied' => $copied, 'bytes' => strlen($zip)];
}

/** stable, unguessable sandbox id for (user, chat) */
function sbx_sid(array $cfg, string $uid, string $chatId): string {
    $key = (string)($cfg['sandbox_secret'] ?? '');
    if ($key === '') { $key = hash('sha256', 'devil-sbx|' . (string)($cfg['daytona_api_key'] ?? '')); }
    return 's' . substr(hash_hmac('sha256', $uid . '|' . ($chatId !== '' ? $chatId : 'temp'), $key), 0, 31);
}

/**
 * Signed request to the sandbox API.
 * Returns ['ok'=>bool, 'status'=>int, 'json'=>?array, 'body'=>string, 'headers'=>array, 'error'=>string]
 */
function sbx_request(array $cfg, string $method, string $path, string $body = '', int $timeout = 60, array $extraHeaders = []): array {
    $base = rtrim((string)($cfg['sandbox_url'] ?? ''), '/');
    $secret = (string)($cfg['sandbox_secret'] ?? '');
    $ts = (string)time();
    $sig = hash_hmac('sha256', $ts . "\n" . $method . "\n" . $path . "\n" . hash('sha256', $body), $secret);
    $headers = array_merge(['X-Sbx-Ts: ' . $ts, 'X-Sbx-Sig: ' . $sig, 'User-Agent: DevilAI-Sandbox/1.0'], $extraHeaders);
    if ($body !== '' && !preg_grep('/^content-type:/i', $headers)) { $headers[] = 'Content-Type: application/json'; }
    $respHeaders = [];
    if (function_exists('curl_init')) {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($c, $h) use (&$respHeaders) {
                $p = strpos($h, ':');
                if ($p !== false) { $respHeaders[strtolower(trim(substr($h, 0, $p)))] = trim(substr($h, $p + 1)); }
                return strlen($h);
            },
        ]);
        if ($body !== '' || in_array($method, ['POST', 'PUT'], true)) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $out = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($out === false) { return ['ok' => false, 'status' => 0, 'json' => null, 'body' => '', 'headers' => [], 'error' => 'Sandbox unreachable: ' . $err]; }
    } else {
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => $timeout, 'ignore_errors' => true]]);
        $out = @file_get_contents($base . $path, false, $ctx);
        $status = 0;
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', (string)$h, $m)) { $status = (int)$m[1]; }
            elseif (($p = strpos((string)$h, ':')) !== false) { $respHeaders[strtolower(trim(substr((string)$h, 0, $p)))] = trim(substr((string)$h, $p + 1)); }
        }
        if ($out === false) { return ['ok' => false, 'status' => 0, 'json' => null, 'body' => '', 'headers' => [], 'error' => 'Sandbox unreachable.']; }
    }
    $json = null;
    if (stripos((string)($respHeaders['content-type'] ?? ''), 'application/json') !== false) {
        $j = json_decode((string)$out, true);
        $json = is_array($j) ? $j : null;
    }
    $err = '';
    if ($status >= 400 || $status === 0) {
        $err = (string)($json['error'] ?? ('Sandbox error (HTTP ' . $status . ')'));
        if ($status === 503 && stripos($err, 'offline') !== false) { $err = 'The sandbox host is restarting — try again in about a minute.'; }
    }
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'json' => $json, 'body' => (string)$out, 'headers' => $respHeaders, 'error' => $err];
}

function sbx_q(array $params): string { return http_build_query($params, '', '&', PHP_QUERY_RFC3986); }

function sbx_health(array $cfg): array {
    if (sbx_is_cloud($cfg)) {
        $p = sbx_provider($cfg);
        $ok = $p === 'daytona' ? sbx_has_daytona($cfg) : ($p === 'vercel' ? vcl_configured($cfg) : (sbx_has_daytona($cfg) || vcl_configured($cfg)));
        return ['ok' => $ok, 'provider' => $p, 'daytona' => sbx_has_daytona($cfg), 'vercel' => vcl_configured($cfg), 'daytona_down' => sbx_daytona_down()];
    }
    $r = sbx_request($cfg, 'GET', '/v1/health', '', 10);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'offline'];
}
function sbx_open(array $cfg, string $sid): array {
    if (sbx_is_cloud($cfg)) { return sbx_use($cfg, $sid) === 'vercel' ? vcl_open($cfg, $sid) : dyt_open($cfg, $sid); }
    $r = sbx_request($cfg, 'POST', '/v1/s/' . $sid, '', 60);
    return $r['ok'] ? (array)$r['json'] : ['ok' => false, 'error' => $r['error']];
}
function sbx_exec(array $cfg, string $sid, string $cmd, int $timeout = 80, bool $background = false, float $wait = 3.0, string $cwd = ''): array {
    if (sbx_is_cloud($cfg)) { return sbx_use($cfg, $sid) === 'vercel' ? vcl_exec($cfg, $sid, $cmd, $timeout, $background, $wait, $cwd) : dyt_exec($cfg, $sid, $cmd, $timeout, $background, $wait, $cwd); }
    $payload = ['cmd' => $cmd, 'timeout' => $timeout, 'background' => $background, 'wait' => $wait];
    if ($cwd !== '') { $payload['cwd'] = $cwd; }
    $r = sbx_request($cfg, 'POST', '/v1/s/' . $sid . '/exec', (string)json_encode($payload), $timeout + 12);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'exec failed'];
}
function sbx_files(array $cfg, string $sid, string $path = '.', int $depth = 4): array {
    if (sbx_is_cloud($cfg)) { return sbx_use($cfg, $sid) === 'vercel' ? vcl_files($cfg, $sid, $path, $depth) : dyt_files($cfg, $sid, $path, $depth); }
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/files?' . sbx_q(['path' => $path, 'depth' => $depth]), '', 30);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'list failed', 'entries' => []];
}
function sbx_read(array $cfg, string $sid, string $path): array {
    if (sbx_is_cloud($cfg)) { return sbx_use($cfg, $sid, false) === 'vercel' ? vcl_read($cfg, $sid, $path) : dyt_read($cfg, $sid, $path); }
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/file?' . sbx_q(['path' => $path]), '', 60);
    return $r['ok'] ? ['ok' => true, 'data' => $r['body'], 'type' => (string)($r['headers']['content-type'] ?? 'application/octet-stream')] : ['ok' => false, 'error' => $r['error'] ?: 'read failed', 'status' => $r['status']];
}
function sbx_write(array $cfg, string $sid, string $path, string $data): array {
    if (sbx_is_cloud($cfg)) { return sbx_use($cfg, $sid) === 'vercel' ? vcl_write($cfg, $sid, $path, $data) : dyt_write($cfg, $sid, $path, $data); }
    $r = sbx_request($cfg, 'PUT', '/v1/s/' . $sid . '/file?' . sbx_q(['path' => $path]), $data, 120, ['Content-Type: application/octet-stream']);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'write failed'];
}
function sbx_delete(array $cfg, string $sid, string $path): array {
    if (sbx_is_cloud($cfg)) { return sbx_use($cfg, $sid) === 'vercel' ? vcl_delete($cfg, $sid, $path) : dyt_delete($cfg, $sid, $path); }
    $r = sbx_request($cfg, 'DELETE', '/v1/s/' . $sid . '/file?' . sbx_q(['path' => $path]), '', 30);
    return $r['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $r['error']];
}
function sbx_ports(array $cfg, string $sid): array {
    if (sbx_is_cloud($cfg)) {
        $r = sbx_backend($cfg, $sid) === 'vercel' ? vcl_ports($cfg, $sid) : dyt_ports($cfg, $sid);
        foreach ((array)($r['ports'] ?? []) as $i => $row) { if (!empty($row['url'])) { $r['ports'][$i]['url'] = sbx_public_url($cfg, (string)$row['url']); } }
        return $r;
    }
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/ports', '', 20);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'], 'ports' => []];
}
function sbx_zip(array $cfg, string $sid): array {
    if (sbx_is_cloud($cfg)) { return sbx_use($cfg, $sid) === 'vercel' ? vcl_zip($cfg, $sid) : dyt_zip($cfg, $sid); }
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/zip', '', 120);
    return $r['ok'] ? ['ok' => true, 'data' => $r['body']] : ['ok' => false, 'error' => $r['error']];
}

/** delete a chat's sandbox for good (chat deleted) */
function sbx_purge(array $cfg, string $sid): array {
    if (sbx_is_cloud($cfg)) {
        $r = ['ok' => true];
        $p = sbx_provider($cfg); $pin = sbx_route_get($sid); $why = sbx_route_get($sid . '~why');
        $onV = $p === 'vercel' || ($p === 'auto' && ($pin === 'vercel' || !sbx_has_daytona($cfg)));
        $onD = $p === 'daytona' || ($p === 'auto' && sbx_has_daytona($cfg) && ($pin !== 'vercel' || $why === 'failover'));
        if ($onD && sbx_has_daytona($cfg)) { $r = dyt_purge($cfg, $sid); }
        if ($onV && vcl_configured($cfg)) { $v = vcl_purge($cfg, $sid); if (empty($v['ok'])) { $r = $v; } }
        if (sbx_route_get($sid) !== '') { sbx_route_set([$sid => null, $sid . '~why' => null]); }
        return $r;
    }
    $r = sbx_request($cfg, 'DELETE', '/v1/s/' . $sid . '?purge=1', '', 8);
    return $r['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $r['error']];
}

/** one line for the agent prompt describing the sandbox computer */
function sbx_env_text(array $cfg, string $sid = ''): string {
    $be = sbx_is_cloud($cfg) ? sbx_backend($cfg, $sid) : 'devil';
    if ($be === 'vercel') {
        $why = $sid !== '' ? sbx_route_get($sid . '~why') : '';
        return 'Your sandbox: Ubuntu Linux, user "ubuntu" with passwordless sudo (apt-get works), working folder /home/user/work (relative paths are relative to it). Installed: Python 3.14 (pip, uv), Node.js 24 (npm, pnpm, bun), git, curl, jq, zip, sqlite3; the browser tool sets up Chromium by itself on first use. '
             . 'FULL internet access: any website or API can be reached from the sandbox. Machine: 2 CPU, 4 GB RAM, plenty of disk. '
             . 'Preview URLs are on *.vercel.run — for Vite set server.allowedHosts: true (or [".vercel.run"]) and host 0.0.0.0. '
             . 'The sandbox sleeps when idle and keeps its files, but running servers stop — restart them with start_server when needed.'
             . ($why === 'internet' ? ' (This chat was moved here for full internet: the earlier files were copied; node_modules / virtualenvs were not — reinstall them.)' : '')
             . ($why === 'failover' ? ' (This chat runs on the backup sandbox; files made earlier on the main sandbox may be missing — recreate them if needed.)' : '');
    }
    if ($be === 'daytona') {
        $more = sbx_dual($cfg) ? ' If the task truly needs the open internet INSIDE the sandbox (scraping a site, calling an outside API from code, downloading from a normal website), call the full_internet tool once — the chat moves to a bigger sandbox with full internet and your files are copied.' : '';
        return 'Your sandbox: Debian Linux, user "daytona" with passwordless sudo, working folder /home/user/work (relative paths are relative to it). Installed: Python 3 (pip), Node.js (npm), git, curl, jq, zip, Chromium. '
             . 'Internet is LIMITED to package registries and code hosts (npm, pip/PyPI, GitHub and similar) — npm/pip install and git clone work, but other websites cannot be opened from the sandbox (use web_search / read_url for the web; generate_image works). '
             . 'The machine is small (1 CPU, 1 GB RAM, 3 GB disk): prefer light tools (Vite, plain HTML/JS, Flask/FastAPI) over heavy builds. The sandbox sleeps when idle and keeps its files, but running servers stop — restart them with start_server when needed.' . $more;
    }
    return 'Your sandbox: Ubuntu 24.04, user "user" with passwordless sudo, working folder /home/user/work (relative paths are relative to it). Installed: Python 3.12 (pip), Node 22 (npm, pnpm, yarn), PHP 8.3, git, curl, ffmpeg, imagemagick, pandoc, sqlite3, Playwright Chromium. Internet access is available (pip/npm install work).';
}

/** fetch an image over HTTP on the web server (used when the sandbox has no open internet) */
function sbx_fetch_image(string $prompt): array {
    $deadline = microtime(true) + (function_exists('devil_time_left') ? max(10, devil_time_left(60) - 8) : 60);
    $get = static function (string $url, ?string $post, int $t) {
        $ch = curl_init($url);
        $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => max(3, $t), CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => 'DevilAI/1.0'];
        if ($post !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = $post; $o[CURLOPT_HTTPHEADER] = ['Content-Type: application/json']; }
        curl_setopt_array($ch, $o);
        $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return [$code, is_string($b) ? $b : ''];
    };
    $isImg = static function (string $b): bool {
        return strncmp($b, "\x89PNG", 4) === 0 || strncmp($b, "\xFF\xD8\xFF", 3) === 0 || (strncmp($b, 'RIFF', 4) === 0 && substr($b, 8, 4) === 'WEBP') || strncmp($b, 'GIF8', 4) === 0;
    };
    $left = static function () use ($deadline): int { return (int)floor($deadline - microtime(true)); };
    $body = (string)json_encode(['prompt' => mb_substr($prompt, 0, 1500), 'width' => '1024', 'height' => '1024']);
    $root = 'https://prexzyapis.com/ai/';
    if ($left() > 12) {
        [$c, $b] = $get($root . 'genimage', $body, min(28, $left() - 4));
        $j = json_decode($b, true);
        $u = is_array($j) ? (string)($j['image_url'] ?? ($j['url'] ?? '')) : '';
        if ($u !== '' && $left() > 4) { [$c2, $img] = $get($u, null, min(15, $left() - 2)); if ($c2 === 200 && $isImg($img)) { return ['ok' => true, 'data' => $img]; } }
    }
    if ($left() > 10) {
        [$c, $img] = $get($root . 'aiappgen', $body, min(25, $left() - 4));
        if ($c === 200 && $isImg($img)) { return ['ok' => true, 'data' => $img]; }
    }
    if ($left() > 5) {
        $fb = 'https://image.pollinations.ai/prompt/' . rawurlencode(mb_substr($prompt, 0, 800)) . '?width=1024&height=1024&nologo=true&seed=' . random_int(1, 999999);
        [$c, $img] = $get($fb, null, $left() - 2);
        if ($c === 200 && $isImg($img)) { return ['ok' => true, 'data' => $img]; }
    }
    return ['ok' => false, 'error' => 'all image services failed or timed out'];
}

/** normalise a user/agent path to a workspace-relative path */
function sbx_rel(string $p): string {
    $p = trim(str_replace("\0", '', $p));
    $p = preg_replace('#^`|`$#', '', $p);
    if (strpos($p, SBX_WORKDIR) === 0) { $p = substr($p, strlen(SBX_WORKDIR)); }
    if (strpos($p, '~/work') === 0) { $p = substr($p, 6); }
    $p = ltrim((string)$p, '/');
    return $p === '' ? '.' : $p;
}

/* ═════════════ agent tools that use the sandbox ═════════════ */

function sbx_agent_tools(array $cfg = [], string $sid = ''): array {
    $t = sbx_agent_tools_all();
    /* full_internet only when this chat is on the limited-internet sandbox and the backup exists */
    if ($cfg !== [] && !(sbx_dual($cfg) && sbx_backend($cfg, $sid) === 'daytona')) { unset($t['full_internet']); }
    return $t;
}

function sbx_agent_tools_all(): array {
    return [
        'bash'           => 'Run a shell command in your Linux sandbox (cwd /home/user/work). Output and exit code come back. A command that is still running after ~25s keeps running in the background and you get its pid and log file — check it later with tail. Use start_server (not bash) for servers. INPUT: the command(s); several lines are fine.',
        'write_file'     => 'Create or overwrite a file. INPUT: first line = path (relative to /home/user/work), then the full file content on the following lines.',
        'read_file'      => 'Read a text file from the sandbox. INPUT: path.',
        'list_files'     => 'List files in the workspace. INPUT: a folder path, or "." for everything.',
        'start_server'   => 'Start a long-running app/dev server in the background and get its public preview URL. INPUT: first line = port, second line = command (bind to 0.0.0.0).',
        'browser'        => 'Open a page in a headless Chromium inside the sandbox (works for http://localhost:PORT too): returns title, visible text, console errors and saves a screenshot. INPUT: URL.',
        'generate_image' => 'Generate an image from a text prompt and save it in the workspace. INPUT: first line = output path (e.g. images/hero.png), second line = the prompt.',
        'full_internet'  => 'Move this chat to a sandbox with FULL internet (2 CPU, 4 GB RAM). Use it only when the task needs websites/APIs that the current sandbox cannot reach. Your /home/user/work files are copied (node_modules / venvs are not); running servers must be restarted. INPUT: one short reason.',
        'ask_user'       => 'Ask the user a clarifying question and stop until they answer. INPUT: first line = the question, then up to 4 short options, one per line starting with "- ".',
    ];
}

function sbx_fmt_exec(array $r): array {
    if (empty($r['ok'])) { return ['ok' => false, 'text' => 'Sandbox error: ' . (string)($r['error'] ?? 'unknown')]; }
    $out = rtrim((string)($r['stdout'] ?? ''));
    $err = rtrim((string)($r['stderr'] ?? ''));
    $code = (int)($r['exit_code'] ?? 0);
    $txt = '';
    if ($out !== '') { $txt .= $out . "\n"; }
    if ($err !== '') { $txt .= ($out !== '' ? "\n[stderr]\n" : "[stderr]\n") . $err . "\n"; }   /* marker always present → the UI splits STDOUT / STDERR */
    if ($txt === '') { $txt = "(no output)\n"; }
    if (!empty($r['timed_out'])) { $txt .= "\n[timed out — for long tasks use start_server or run it in the background with nohup … &]"; }
    $txt .= "\n[exit code {$code}]";
    return ['ok' => $code === 0, 'text' => $txt, 'exit_code' => $code];
}

/** strip one wrapping ``` fence from tool input content */
function sbx_unfence(string $s): string {
    $t = trim($s, "\r\n");
    if (preg_match('/^\s*```[\w.+-]*[ \t]*\r?\n(.*?)\r?\n?```\s*$/s', $t, $m)) { return $m[1]; }
    return $t;
}

/**
 * Run one sandbox tool. $ctx = ['cfg'=>…, 'sid'=>…, 'step'=>int]
 * Returns ['ok'=>bool, 'text'=>string, 'meta'=>array]  (meta goes to the UI only)
 */
function sbx_run_tool(array $ctx, string $name, string $input): array {
    $cfg = $ctx['cfg']; $sid = $ctx['sid'];
    switch ($name) {
        case 'bash': {
            $cmd = sbx_unfence($input);
            if (trim($cmd) === '') { return ['ok' => false, 'text' => 'Empty command.']; }
            /* The command runs as a detached job. We wait up to ~25s for it (short, so one agent step does not
               hold a PHP worker on the shared server for a minute); if it is still going
               (npm install, a dev server in the foreground, a long build…) it keeps running in the
               background and the agent gets the pid + log path instead of a timeout. This keeps every
               agent step well under Cloudflare's 100s request limit. */
            $wait = max(10, min(55, (int)($cfg['agent_bash_wait'] ?? 25)));
            $job = '/home/user/.bg/cmd-' . bin2hex(random_bytes(4));
            $wrapper = 'mkdir -p /home/user/.bg; J=' . $job . '; echo ' . base64_encode($cmd) . ' | base64 -d > "$J.sh"; '
                . 'nohup setsid bash -c \'bash -l "$1" > >(tee "$1.out" >> "$1.log") 2> >(tee "$1.err" >> "$1.log"); r=$?; sleep 0.2; echo $r > "$1.rc"\' _ "$J.sh" > /dev/null 2>&1 < /dev/null & P=$!; '
                . 'i=0; while [ ! -f "$J.sh.rc" ] && [ $i -lt ' . ($wait * 5) . ' ]; do sleep 0.2; i=$((i+1)); done; '
                . 'if [ -f "$J.sh.rc" ]; then sz=$(stat -c %s "$J.sh.out" 2>/dev/null || echo 0); '
                . 'if [ "$sz" -gt 14000 ]; then head -c 3000 "$J.sh.out"; echo; echo "… [$sz bytes of output, middle cut] …"; tail -c 10000 "$J.sh.out"; else cat "$J.sh.out" 2>/dev/null; fi; '
                /* stderr comes back after a marker so the UI can show STDOUT and STDERR separately (like the reference agent) */
                . 'if [ -s "$J.sh.err" ]; then es=$(stat -c %s "$J.sh.err"); echo; echo __DEVIL_STDERR__; if [ "$es" -gt 6000 ]; then head -c 1500 "$J.sh.err"; echo; echo "… [$es bytes, middle cut] …"; tail -c 4000 "$J.sh.err"; else cat "$J.sh.err"; fi; fi; '
                . 'rc=$(cat "$J.sh.rc"); rm -f "$J.sh" "$J.sh.rc" "$J.sh.log" "$J.sh.out" "$J.sh.err"; exit $rc; '
                . 'else echo "__DEVIL_STILL_RUNNING__ pid=$P log=$J.sh.log"; tail -c 3000 "$J.sh.log" 2>/dev/null; exit 0; fi';
            $r = sbx_exec($cfg, $sid, $wrapper, $wait + 15);
            $out = (string)($r['stdout'] ?? '');
            if (preg_match('/__DEVIL_STILL_RUNNING__ pid=(\d+) log=(\S+)\n?/', $out, $m)) {
                $tail = trim(str_replace($m[0], '', $out));
                $meta = ['background' => true, 'pid' => (int)$m[1], 'log' => $m[2]];
                /* a dev server started from bash (npm run dev, vite, flask…): hand back its preview URL */
                $srv = '';
                $p = sbx_ports($cfg, $sid);
                foreach ((array)($p['ports'] ?? []) as $pp) {
                    $pt = (int)($pp['port'] ?? 0);
                    if ($pt < 1024 || $pt === 49983) { continue; }
                    if (!empty($pp['localhost_only'])) {
                        $srv .= "\nA server is listening on 127.0.0.1:{$pt} only, so the user's preview cannot reach it. Restart it bound to 0.0.0.0 with start_server (for Vite: npx vite --host 0.0.0.0 --port {$pt}).";
                    } elseif (!empty($pp['url']) && empty($meta['url'])) {
                        $meta['port'] = $pt; $meta['url'] = (string)$pp['url'];
                        $srv .= "\nA server is running on port {$pt}. Preview URL: {$pp['url']} (the user sees it in the Preview tab — never give the user localhost links).";
                    }
                }
                return ['ok' => true, 'text' => "The command is still running in the background after {$wait}s (pid {$m[1]}). "
                    . "Its output keeps going to {$m[2]} — check it later with: tail -n 40 {$m[2]}  (or wait with: sleep 20; tail -n 40 {$m[2]})." . $srv . "\n"
                    . ($tail !== '' ? "Output so far:\n" . $tail : '(no output yet)'), 'meta' => $meta];
            }
            $k = strpos($out, "\n__DEVIL_STDERR__\n");
            if ($k !== false) {
                $se = trim((string)($r['stderr'] ?? ''));
                $r['stderr'] = rtrim(substr($out, $k + 18)) . ($se !== '' ? "\n" . $se : '');
                $r['stdout'] = substr($out, 0, $k);
            }
            $f = sbx_fmt_exec($r);
            return ['ok' => $f['ok'], 'text' => $f['text'], 'meta' => ['exit_code' => $f['exit_code'] ?? null]];
        }
        case 'write_file': {
            $lines = preg_split('/\r?\n/', ltrim($input, "\r\n"), 2);
            $path = sbx_rel((string)($lines[0] ?? ''));
            $content = sbx_unfence((string)($lines[1] ?? ''));
            if ($path === '.' || $path === '') { return ['ok' => false, 'text' => 'write_file: the first line must be the file path.']; }
            if ($content !== '' && substr($content, -1) !== "\n") { $content .= "\n"; }
            $r = sbx_write($cfg, $sid, $path, $content);
            if (empty($r['ok'])) { return ['ok' => false, 'text' => 'Could not write ' . $path . ': ' . (string)($r['error'] ?? '')]; }
            return ['ok' => true, 'text' => 'Wrote ' . $path . ' (' . strlen($content) . ' bytes, ' . substr_count($content, "\n") . ' lines).', 'meta' => ['path' => $path, 'bytes' => strlen($content)]];
        }
        case 'read_file': {
            $path = sbx_rel(strtok(trim($input), "\n") ?: '');
            $r = sbx_read($cfg, $sid, $path);
            if (empty($r['ok'])) { return ['ok' => false, 'text' => 'Could not read ' . $path . ': ' . (string)($r['error'] ?? '')]; }
            $data = (string)$r['data'];
            if (preg_match('/[\x00-\x08\x0E-\x1F]/', substr($data, 0, 2000))) { return ['ok' => true, 'text' => $path . ' is a binary file (' . strlen($data) . ' bytes).', 'meta' => ['path' => $path]]; }
            $more = strlen($data) > 14000;
            return ['ok' => true, 'text' => ($more ? substr($data, 0, 14000) . "\n…[truncated, " . strlen($data) . " bytes total — use bash with sed -n to read ranges]" : $data), 'meta' => ['path' => $path]];
        }
        case 'list_files': {
            $path = sbx_rel(strtok(trim($input), "\n") ?: '.');
            $r = sbx_files($cfg, $sid, $path, 4);
            if (empty($r['ok']) && stripos((string)($r['error'] ?? ''), 'not a directory') !== false) { return ['ok' => true, 'text' => 'The folder ' . $path . ' does not exist yet (nothing there).']; }
            if (empty($r['ok'])) { return ['ok' => false, 'text' => 'Could not list ' . $path . ': ' . (string)($r['error'] ?? '')]; }
            $rows = [];
            foreach ((array)($r['entries'] ?? []) as $e) {
                if (!is_array($e)) { continue; }
                $rows[] = ($e['type'] === 'dir' ? '[dir]  ' : '       ') . $e['path'] . ($e['type'] === 'dir' ? '/' . (!empty($e['skipped']) ? '  (not expanded)' : '') : '  (' . (int)($e['size'] ?? 0) . ' B)');
                if (count($rows) >= 300) { $rows[] = '…'; break; }
            }
            return ['ok' => true, 'text' => $rows ? implode("\n", $rows) : '(empty folder)'];
        }
        case 'start_server': {
            $lines = preg_split('/\r?\n/', trim(sbx_unfence($input)), 2);
            $port = (int)preg_replace('/\D/', '', (string)($lines[0] ?? ''));
            $cmd = trim((string)($lines[1] ?? ''));
            if ($port < 1024 || $port > 65535 || $cmd === '') { return ['ok' => false, 'text' => 'start_server: first line = port (1024-65535), second line = command.']; }
            sbx_exec($cfg, $sid, 'fuser -k ' . $port . '/tcp >/dev/null 2>&1; true', 15);
            $r = sbx_exec($cfg, $sid, $cmd, 30, true, 6.0);
            if (empty($r['ok'])) { return ['ok' => false, 'text' => 'Could not start: ' . (string)($r['error'] ?? '')]; }
            $listening = false; $url = '';
            for ($i = 0; $i < 6 && !$listening; $i++) {
                $p = sbx_ports($cfg, $sid);
                foreach ((array)($p['ports'] ?? []) as $pp) {
                    if ((int)($pp['port'] ?? 0) === $port) { $listening = empty($pp['localhost_only']); $url = (string)($pp['url'] ?? ''); if (!$listening) { break 2; } }
                }
                if (!$listening) { sleep(3); }
            }
            $log = trim((string)($r['output'] ?? ''));
            if ($log === '' && !empty($r['log'])) { $lr = sbx_exec($cfg, $sid, 'tail -c 3000 ' . escapeshellarg((string)$r['log']), 10); $log = trim((string)($lr['stdout'] ?? '')); }
            if ($url !== '' && !$listening) {
                return ['ok' => false, 'text' => "The app listens on 127.0.0.1:{$port} only, so the preview cannot reach it. Restart it bound to 0.0.0.0.\n\nLog:\n" . mb_substr($log, -2500)];
            }
            if (!$listening) {
                return ['ok' => false, 'text' => "Nothing is listening on port {$port} yet. It may still be starting, or it crashed.\n\nLog (" . (string)($r['log'] ?? '') . "):\n" . mb_substr($log, -2500)];
            }
            /* verify the page really loads — a server started in the wrong folder answers 404 and the preview stays blank */
            $chk = sbx_exec($cfg, $sid, 'c=$(curl -s -o /dev/null -m 8 -w "%{http_code}" http://127.0.0.1:' . $port . '/); echo "HTTP=$c"; if [ "${c:-0}" -ge 400 ] 2>/dev/null || [ "$c" = "000" ]; then echo "--- project folders:"; ls -d */ 2>/dev/null | head -20; ls */package.json */index.html */*/package.json 2>/dev/null | head -10; fi', 20);
            $co = (string)($chk['stdout'] ?? '');
            $code = preg_match('/HTTP=(\d{3})/', $co, $hm) ? (int)$hm[1] : 0;
            if ($code >= 400 || $code === 0 && strpos($co, 'HTTP=000') !== false) {
                $hint = trim((string)preg_replace('/^HTTP=\d+\s*/', '', $co));
                return ['ok' => false, 'text' => "The server is listening on port {$port}, but its home page answers HTTP " . ($code ?: 'no response') . ", so the user's preview is blank."
                    . " Every command starts in /home/user/work — a dev server must be started inside the project folder, e.g. \"cd my-app && npx vite --host 0.0.0.0 --port {$port}\". Fix it and call start_server again.\n"
                    . ($hint !== '' ? $hint . "\n" : '') . "\nLog:\n" . mb_substr($log, -1200), 'meta' => ['port' => $port, 'http' => $code]];
            }
            return ['ok' => true, 'text' => "Server is running on port {$port} (home page HTTP {$code}). Preview URL: {$url}\n(The user sees it in the Preview tab.)\n\nLog:\n" . mb_substr($log, -1500), 'meta' => ['port' => $port, 'url' => $url]];
        }
        case 'browser': {
            $url = trim(strtok(trim($input), "\n") ?: '');
            if (!preg_match('#^https?://#i', $url)) { $url = 'http://' . ltrim($url, '/'); }
            $shot = '.devil/screens/shot-' . date('His') . '-' . substr(bin2hex(random_bytes(3)), 0, 4) . '.png';
            $py = <<<'PY'
import asyncio, json, sys, os
from playwright.async_api import async_playwright
async def main(url, shot):
    out = {"url": url, "console": [], "errors": []}
    async with async_playwright() as p:
        try:
            b = await p.chromium.launch(args=["--no-sandbox"])
        except Exception:
            b = await p.chromium.launch(executable_path="/usr/bin/chromium", args=["--no-sandbox"])
        pg = await b.new_page(viewport={"width": 1280, "height": 800})
        pg.on("console", lambda m: out["console"].append(f"{m.type}: {m.text}"[:300]) if m.type in ("error", "warning") else None)
        pg.on("pageerror", lambda e: out["errors"].append(str(e)[:300]))
        try:
            r = await pg.goto(url, wait_until="networkidle", timeout=25000)
            out["status"] = r.status if r else None
        except Exception as e:
            out["errors"].append("navigation: " + str(e)[:300])
        try:
            out["title"] = await pg.title()
            out["text"] = (await pg.inner_text("body"))[:5000]
        except Exception as e:
            out["text"] = ""
        os.makedirs(os.path.dirname(shot), exist_ok=True)
        try:
            await pg.screenshot(path=shot)
            out["screenshot"] = shot
        except Exception:
            pass
        await b.close()
    print(json.dumps(out))
asyncio.run(main(sys.argv[1], sys.argv[2]))
PY;
            $cmd = "python3 -c 'import playwright' 2>/dev/null || pip install -q playwright >/dev/null 2>&1; mkdir -p .devil && cat > .devil/browse.py <<'DEVILPY'\n" . $py . "\nDEVILPY\npython3 .devil/browse.py " . escapeshellarg($url) . ' ' . escapeshellarg($shot) . ' 2>&1 | tail -c 12000';
            if (sbx_is_cloud($cfg) && sbx_backend($cfg, $sid) === 'vercel') {
                /* Vercel image has no browser: set Playwright Chromium up once (in the background, ~40 s) and wait for it */
                $ready = 'ls -d ~/.cache/ms-playwright/chromium* >/dev/null 2>&1 && test -f /home/user/.bg/.pwdeps';
                $cmd = 'mkdir -p /home/user/.bg; if ! { ' . $ready . '; }; then (nohup setsid bash -c ' . escapeshellarg(VCL_PW_SETUP) . ' >/dev/null 2>&1 < /dev/null &); '
                    . 'for i in $(seq 1 45); do sleep 1; ' . $ready . ' && break; done; fi; '
                    . 'if ! { ' . $ready . '; }; then echo \'{"errors": ["Chromium is still being installed in the sandbox (first use). Wait ~30 seconds and run the browser tool again."]}\'; exit 0; fi; ' . $cmd;
            }
            $r = sbx_exec($cfg, $sid, $cmd, 70);
            if (empty($r['ok'])) { return ['ok' => false, 'text' => 'Browser error: ' . (string)($r['error'] ?? '')]; }
            $raw = trim((string)($r['stdout'] ?? ''));
            $line = trim((string)substr($raw, (int)strrpos("\n" . $raw, "\n")));
            $j = json_decode($line, true);
            if (!is_array($j)) { return ['ok' => false, 'text' => 'Browser failed: ' . mb_substr($raw, -2000)]; }
            $t = 'Page: ' . ($j['title'] ?? '') . ' (' . $url . ', HTTP ' . ($j['status'] ?? '?') . ")\n";
            if (!empty($j['errors'])) { $t .= "Page errors:\n- " . implode("\n- ", array_slice((array)$j['errors'], 0, 8)) . "\n"; }
            if (!empty($j['console'])) { $t .= "Console:\n- " . implode("\n- ", array_slice((array)$j['console'], 0, 10)) . "\n"; }
            $t .= "Visible text:\n" . mb_substr((string)($j['text'] ?? ''), 0, 4000);
            if (!empty($j['screenshot'])) { $t .= "\n\nScreenshot saved: " . $j['screenshot']; }
            return ['ok' => empty($j['errors']), 'text' => $t, 'meta' => ['screenshot' => (string)($j['screenshot'] ?? ''), 'url' => $url]];
        }
        case 'full_internet': {
            $m = sbx_move_to_vercel($cfg, $sid);
            if (empty($m['ok'])) { return ['ok' => false, 'text' => 'Could not switch to the full-internet sandbox: ' . (string)($m['error'] ?? '') . '. Continue on the current sandbox (use web_search / read_url for the web).']; }
            if (!empty($m['already'])) { return ['ok' => true, 'text' => 'This chat already has full internet access.']; }
            return ['ok' => true, 'text' => 'Switched: this chat now runs on a sandbox with FULL internet (Ubuntu, user "ubuntu", 2 CPU, 4 GB RAM, Python 3.14, Node 24). '
                . ($m['copied'] ? 'Your files in /home/user/work were copied (' . (int)$m['bytes'] . ' bytes zipped). Reinstall dependencies (npm install / pip install) before running. ' : 'There were no files to copy. ')
                . 'Restart any servers with start_server. Preview URLs are now on *.vercel.run (for Vite set server.allowedHosts: true).', 'meta' => ['provider' => 'vercel']];
        }
        case 'generate_image': {
            $lines = preg_split('/\r?\n/', trim($input), 2);
            $path = sbx_rel((string)($lines[0] ?? ''));
            $prompt = trim((string)($lines[1] ?? ''));
            if ($prompt === '' && !preg_match('/\.(png|jpe?g|webp)$/i', $path)) { $prompt = trim($input); $path = 'images/image-' . date('His') . '.png'; }
            if (!preg_match('/\.(png|jpe?g|webp)$/i', $path)) { $path = rtrim($path === '.' ? 'images' : $path, '/') . '/image-' . date('His') . '.png'; }
            if ($prompt === '') { return ['ok' => false, 'text' => 'generate_image: second line must be the prompt.']; }
            if (sbx_is_cloud($cfg)) {
                $img = sbx_fetch_image($prompt);
                if (empty($img['ok'])) { return ['ok' => false, 'text' => 'Image generation failed: ' . (string)($img['error'] ?? '')]; }
                if (preg_match('/\.png$/i', $path) && strncmp($img['data'], "\xFF\xD8\xFF", 3) === 0) { $path = (string)preg_replace('/\.png$/i', '.jpg', $path); }
                $w = sbx_write($cfg, $sid, $path, $img['data']);
                if (empty($w['ok'])) { return ['ok' => false, 'text' => 'Could not save the image: ' . (string)($w['error'] ?? '')]; }
                return ['ok' => true, 'text' => 'Image saved to ' . $path . ' (' . strlen($img['data']) . ' bytes).', 'meta' => ['image' => $path]];
            }
            $body = (string)json_encode(['prompt' => mb_substr($prompt, 0, 1500), 'width' => '1024', 'height' => '1024']);
            $root = 'https://prexzyapis.com/ai/';
            $fallback = 'https://image.pollinations.ai/prompt/' . rawurlencode(mb_substr($prompt, 0, 800)) . '?width=1024&height=1024&nologo=true&seed=' . random_int(1, 999999);
            $P = escapeshellarg($path);
            $dir = dirname($path);
            /* everything runs inside the sandbox: 1) GenImage (returns a URL)  2) AiApp image (returns the image)  3) Pollinations */
            $cmd = 'mkdir -p ' . escapeshellarg($dir === '.' ? '.' : $dir) . ' && rm -f ' . $P . '; B=' . escapeshellarg($body) . '; '
                 . 'u=$(curl -fsS --max-time 28 -X POST -H "Content-Type: application/json" -d "$B" ' . escapeshellarg($root . 'genimage') . ' | jq -r ".image_url // .url // empty" 2>/dev/null); '
                 . '[ -n "$u" ] && curl -fsSL --max-time 12 -o ' . $P . ' "$u"; '
                 . 'file -b ' . $P . ' 2>/dev/null | grep -qi image || curl -fsS --max-time $(( SECONDS < 40 ? 25 : 8 )) -X POST -H "Content-Type: application/json" -d "$B" -o ' . $P . ' ' . escapeshellarg($root . 'aiappgen') . '; '
                 . 'file -b ' . $P . ' 2>/dev/null | grep -qi image || { [ $SECONDS -lt 62 ] && curl -fsSL --max-time $(( 70 - SECONDS )) -o ' . $P . ' ' . escapeshellarg($fallback) . '; }; '
                 . 'file -b ' . $P . ' && stat -c %s ' . $P;
            $r = sbx_exec($cfg, $sid, $cmd, 75);
            $out = trim((string)($r['stdout'] ?? ''));
            if ((int)($r['exit_code'] ?? 1) !== 0 || stripos($out, 'image') === false) {
                return ['ok' => false, 'text' => 'Image generation failed. ' . mb_substr($out . ' ' . (string)($r['stderr'] ?? $r['error'] ?? ''), 0, 600)];
            }
            return ['ok' => true, 'text' => 'Image saved to ' . $path . ' (' . str_replace("\n", ', ', $out) . ').', 'meta' => ['image' => $path]];
        }
    }
    return ['ok' => false, 'text' => "Unknown sandbox tool '{$name}'."];
}

/** parse ask_user input → [question, options[]] */
function sbx_parse_ask(string $input): array {
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', trim($input))), 'strlen'));
    $q = (string)array_shift($lines);
    $opts = [];
    foreach ($lines as $l) {
        $l = trim(preg_replace('/^([-*•]|\d+[.)])\s*/u', '', $l));
        if ($l !== '' && count($opts) < 4) { $opts[] = mb_substr($l, 0, 120); }
    }
    if (strpos($q, '|') !== false && !$opts) {
        $parts = array_map('trim', explode('|', $q));
        $q = (string)array_shift($parts);
        $opts = array_slice(array_values(array_filter($parts, 'strlen')), 0, 4);
    }
    return [mb_substr($q, 0, 600), $opts];
}
