<?php
/**
 * Devil AI — Agent sandbox (inc/sandbox.php)
 *
 * Provider-neutral client for the per-chat Linux sandbox used by Agent Mode
 * (bash, files, previews, headless browser). Providers behind the same sbx_* functions:
 *   "daytona" — Daytona cloud sandboxes (inc/sandbox_daytona.php), config sandbox_provider=daytona
 *   "e2b"     — E2B cloud sandboxes (inc/sandbox_e2b.php): backup + full internet in "auto" mode
 *   "devil"   — the old self-hosted Devil Sandbox gateway (sandbox_url + sandbox_secret)
 *
 * Every call is signed with HMAC-SHA256(sandbox_secret). The sandbox id is
 * derived server-side from (user id, chat id), so a user can only ever reach
 * the sandbox of their own chats.
 */
declare(strict_types=1);
require_once __DIR__ . '/agent_tools_extra.php';

const SBX_WORKDIR = '/home/user/work';
require_once __DIR__ . '/sandbox_daytona.php';
require_once __DIR__ . '/sandbox_vercel.php';
require_once __DIR__ . '/sandbox_e2b.php';
require_once __DIR__ . '/sandbox_csb.php';
require_once __DIR__ . '/hosting.php';

/**
 * Providers: "daytona" | "e2b" | "vercel" | "auto" | "devil" (old gateway).
 * "auto" = a chain of every configured cloud, in this order: Daytona (main) → E2B (backup + full internet) → Vercel (last backup).
 * Every chat is pinned to one backend (data/sbx_route.json) so its files never jump around:
 *  - new chats go to the first backend that has not failed recently (credit used up, auth/quota error, outage)
 *  - a chat whose sandbox cannot be created/started is moved to the next backend
 *  - the agent can move a chat from Daytona (limited internet) to the backup with the full_internet tool; files are copied
 */
function sbx_provider(array $cfg): string {
    $p = strtolower(trim((string)($cfg['sandbox_provider'] ?? '')));
    return in_array($p, ['daytona', 'vercel', 'e2b', 'csb', 'auto'], true) ? $p : 'devil';
}
function sbx_has_daytona(array $cfg): bool { return trim((string)($cfg['daytona_api_key'] ?? '')) !== ''; }
function sbx_has_e2b(array $cfg): bool { return function_exists('e2b_configured') && e2b_configured($cfg); }
function sbx_has_csb(array $cfg): bool { return function_exists('csb_configured') && csb_configured($cfg); }
function sbx_is_cloud(array $cfg): bool { return sbx_provider($cfg) !== 'devil'; }
/** configured cloud backends in fail-over order */
function sbx_chain(array $cfg): array {
    $p = sbx_provider($cfg);
    if ($p !== 'auto') { return $p === 'devil' ? [] : [$p]; }
    $c = [];
    if (sbx_has_daytona($cfg)) { $c[] = 'daytona'; }
    if (sbx_has_e2b($cfg)) { $c[] = 'e2b'; }
    if (sbx_has_csb($cfg)) { $c[] = 'csb'; }
    if (vcl_configured($cfg)) { $c[] = 'vercel'; }
    return $c;
}
/** the full-internet backup for a Daytona chat ('' when there is none) */
function sbx_backup(array $cfg): string {
    foreach (sbx_chain($cfg) as $b) { if ($b !== 'daytona') { return $b; } }
    return '';
}
/** true when this deployment can fail over / offer full_internet (Daytona + at least one backup) */
function sbx_dual(array $cfg): bool { return sbx_provider($cfg) === 'auto' && sbx_has_daytona($cfg) && sbx_backup($cfg) !== ''; }
/** function-name prefix of a backend */
function sbx_px(string $be): string { return $be === 'vercel' ? 'vcl' : ($be === 'e2b' ? 'e2b' : ($be === 'csb' ? 'csb' : 'dyt')); }

function sbx_enabled(array $cfg): bool {
    if (empty($cfg['sandbox_enabled'])) { return false; }
    if (sbx_is_cloud($cfg)) { return sbx_chain($cfg) !== []; }
    return trim((string)($cfg['sandbox_url'] ?? '')) !== '' && trim((string)($cfg['sandbox_secret'] ?? '')) !== '';
}

/* ── chat → backend pins (data/sbx_route.json): {sid: "daytona"|"e2b"|"vercel", "sid~why": "failover"|"internet", "_dyt_down": ts, "_e2b_down": ts} ── */
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
function sbx_down_key(string $be): string { return $be === 'daytona' ? '_dyt_down' : '_' . sbx_px($be) . '_down'; }
function sbx_is_down(string $be): bool { return (int)sbx_route_get(sbx_down_key($be)) > time(); }
function sbx_daytona_down(): bool { return sbx_is_down('daytona'); }

/** which backend serves this chat right now (no network calls) */
function sbx_backend(array $cfg, string $sid): string {
    $p = sbx_provider($cfg);
    if ($p !== 'auto') { return $p; }
    $chain = sbx_chain($cfg);
    if ($chain === []) { return 'daytona'; }
    $r = $sid !== '' ? sbx_route_get($sid) : '';
    if ($r !== '' && in_array($r, $chain, true)) { return $r; }
    foreach ($chain as $b) { if (!sbx_is_down($b)) { return $b; } }
    return $chain[count($chain) - 1];
}

/**
 * backend for an operation that needs a running sandbox; walks the fail-over chain in "auto" mode.
 * $create=false: the operation must not create a sandbox (reading a file).
 */
function sbx_use(array $cfg, string $sid, bool $create = true): string {
    $be = sbx_backend($cfg, $sid);
    if (sbx_provider($cfg) !== 'auto') { return $be; }
    $chain = sbx_chain($cfg);
    $i = array_search($be, $chain, true);
    if ($i === false) { return $be; }
    for (; $i < count($chain); $i++) {
        $be = $chain[$i];
        $last = $i === count($chain) - 1;
        if ($be === 'vercel' || $last) {
            if ($create && $be !== $chain[0] && sbx_route_get($sid) === '') { sbx_route_set([$sid => $be, $sid . '~why' => 'failover']); }
            return $be;
        }
        $e = (sbx_px($be) . '_ensure')($cfg, $sid, $create);
        if (!empty($e['ok']) || !empty($e['none']) || !empty($e['asleep']) || empty($e['down'])) {
            if ($create && !empty($e['ok']) && $be !== $chain[0] && sbx_route_get($sid) === '') { sbx_route_set([$sid => $be, $sid . '~why' => 'failover']); }
            return $be;
        }
        /* this backend cannot serve the chat (credit used up, quota/auth error, outage): use the next one for a while */
        $mins = max(2, min(240, (int)($cfg['sandbox_failover_minutes'] ?? 15)));
        $next = $chain[$i + 1];
        sbx_route_set([sbx_down_key($be) => time() + $mins * 60, sbx_down_key($be) . '_why' => mb_substr((string)($e['error'] ?? ''), 0, 200), $sid => $next, $sid . '~why' => 'failover']);
        if (function_exists('devil_log')) { @devil_log('sandbox', $be . ' down, using ' . $next . ': ' . (string)($e['error'] ?? '')); }
    }
    return $be;
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
    } elseif (preg_match('/^(\d{2,5})-([a-z0-9]{12,40})\.e2b\.app$/', $h, $m)) {
        $label = 'e' . $m[1] . '-' . $m[2];
    } elseif (preg_match('/^([a-z0-9]{5,10})-(\d{2,5})\.csb\.app$/', $h, $m)) {
        $label = 'c' . $m[2] . '-' . $m[1];
    } else {
        return $url;
    }
    $path = (string)parse_url($url, PHP_URL_PATH);
    return 'https://' . $label . '-' . substr(hash_hmac('sha256', $label, $key), 0, 10) . '.' . $dom . ($path !== '/' ? $path : '');
}

/** move a chat from Daytona to the backup sandbox (full internet), copying its workspace. */
function sbx_move_to_backup(array $cfg, string $sid): array {
    $to = sbx_backup($cfg);
    if ($to === '') { return ['ok' => false, 'error' => 'the full-internet sandbox is not configured']; }
    if (sbx_backend($cfg, $sid) !== 'daytona') { return ['ok' => true, 'already' => true]; }
    $zip = '';
    $e = sbx_has_daytona($cfg) ? dyt_ensure($cfg, $sid, false) : ['ok' => false];
    if (!empty($e['ok'])) {
        $z = dyt_zip($cfg, $sid);
        if (empty($z['ok'])) { return ['ok' => false, 'error' => 'could not pack the current files: ' . (string)($z['error'] ?? '')]; }
        $zip = (string)$z['data'];
    }
    /* try every backup in order (E2B → CodeSandbox → Vercel) until one starts */
    $o = ['ok' => false, 'error' => ''];
    $px = '';
    foreach (sbx_chain($cfg) as $b) {
        if ($b === 'daytona') { continue; }
        $to = $b; $px = sbx_px($to);
        $o = ($px . '_open')($cfg, $sid);
        if (!empty($o['ok'])) { break; }
    }
    if (empty($o['ok'])) { return ['ok' => false, 'error' => 'the full-internet sandbox did not start: ' . sbx_scrub((string)($o['error'] ?? ''))]; }
    $copied = false;
    if (strlen($zip) > 22) {
        $u = ($px . '_unzip')($cfg, $sid, $zip);
        if (empty($u['ok'])) { return ['ok' => false, 'error' => 'could not copy the files: ' . (string)($u['error'] ?? '')]; }
        $copied = true;
    }
    sbx_route_set([$sid => $to, $sid . '~why' => 'internet']);
    if (!empty($e['ok'])) { dyt_purge($cfg, $sid); }   /* the old copy is no longer used */
    return ['ok' => true, 'copied' => $copied, 'bytes' => strlen($zip), 'to' => $to];
}
/* old name kept for callers */
function sbx_move_to_vercel(array $cfg, string $sid): array { return sbx_move_to_backup($cfg, $sid); }

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
        return ['ok' => sbx_chain($cfg) !== [], 'provider' => $p, 'chain' => sbx_chain($cfg), 'daytona' => sbx_has_daytona($cfg), 'e2b' => sbx_has_e2b($cfg), 'csb' => sbx_has_csb($cfg), 'vercel' => vcl_configured($cfg),
                'daytona_down' => sbx_daytona_down(), 'e2b_down' => sbx_is_down('e2b'), 'csb_down' => sbx_is_down('csb')];
    }
    $r = sbx_request($cfg, 'GET', '/v1/health', '', 10);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'offline'];
}
function sbx_open(array $cfg, string $sid): array {
    if (sbx_is_cloud($cfg)) { return (sbx_px(sbx_use($cfg, $sid)) . '_open')($cfg, $sid); }
    $r = sbx_request($cfg, 'POST', '/v1/s/' . $sid, '', 60);
    return $r['ok'] ? (array)$r['json'] : ['ok' => false, 'error' => $r['error']];
}
function sbx_exec(array $cfg, string $sid, string $cmd, int $timeout = 80, bool $background = false, float $wait = 3.0, string $cwd = ''): array {
    if (sbx_is_cloud($cfg)) { return (sbx_px(sbx_use($cfg, $sid)) . '_exec')($cfg, $sid, $cmd, $timeout, $background, $wait, $cwd); }
    $payload = ['cmd' => $cmd, 'timeout' => $timeout, 'background' => $background, 'wait' => $wait];
    if ($cwd !== '') { $payload['cwd'] = $cwd; }
    $r = sbx_request($cfg, 'POST', '/v1/s/' . $sid . '/exec', (string)json_encode($payload), $timeout + 12);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'exec failed'];
}
function sbx_files(array $cfg, string $sid, string $path = '.', int $depth = 4): array {
    if (sbx_is_cloud($cfg)) { return (sbx_px(sbx_use($cfg, $sid)) . '_files')($cfg, $sid, $path, $depth); }
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/files?' . sbx_q(['path' => $path, 'depth' => $depth]), '', 30);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'list failed', 'entries' => []];
}
function sbx_read(array $cfg, string $sid, string $path): array {
    if (sbx_is_cloud($cfg)) { return (sbx_px(sbx_use($cfg, $sid, false)) . '_read')($cfg, $sid, $path); }
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/file?' . sbx_q(['path' => $path]), '', 60);
    return $r['ok'] ? ['ok' => true, 'data' => $r['body'], 'type' => (string)($r['headers']['content-type'] ?? 'application/octet-stream')] : ['ok' => false, 'error' => $r['error'] ?: 'read failed', 'status' => $r['status']];
}
function sbx_write(array $cfg, string $sid, string $path, string $data): array {
    if (sbx_is_cloud($cfg)) { return (sbx_px(sbx_use($cfg, $sid)) . '_write')($cfg, $sid, $path, $data); }
    $r = sbx_request($cfg, 'PUT', '/v1/s/' . $sid . '/file?' . sbx_q(['path' => $path]), $data, 120, ['Content-Type: application/octet-stream']);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'write failed'];
}
function sbx_delete(array $cfg, string $sid, string $path): array {
    if (sbx_is_cloud($cfg)) { return (sbx_px(sbx_use($cfg, $sid)) . '_delete')($cfg, $sid, $path); }
    $r = sbx_request($cfg, 'DELETE', '/v1/s/' . $sid . '/file?' . sbx_q(['path' => $path]), '', 30);
    return $r['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $r['error']];
}
function sbx_ports(array $cfg, string $sid): array {
    if (sbx_is_cloud($cfg)) {
        $r = (sbx_px(sbx_backend($cfg, $sid)) . '_ports')($cfg, $sid);
        foreach ((array)($r['ports'] ?? []) as $i => $row) { if (!empty($row['url'])) { $r['ports'][$i]['url'] = sbx_public_url($cfg, (string)$row['url']); } }
        return $r;
    }
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/ports', '', 20);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'], 'ports' => []];
}
function sbx_zip(array $cfg, string $sid): array {
    if (sbx_is_cloud($cfg)) { return (sbx_px(sbx_use($cfg, $sid)) . '_zip')($cfg, $sid); }
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/zip', '', 120);
    return $r['ok'] ? ['ok' => true, 'data' => $r['body']] : ['ok' => false, 'error' => $r['error']];
}

/** delete a chat's sandbox for good (chat deleted) */
function sbx_purge(array $cfg, string $sid): array {
    if (sbx_is_cloud($cfg)) {
        $r = ['ok' => true];
        $chain = sbx_chain($cfg); $pin = sbx_route_get($sid); $why = sbx_route_get($sid . '~why');
        foreach ($chain as $b) {
            /* the pinned backend, plus the main one when the chat failed over (it may hold an older copy) */
            $on = $b === $pin || ($pin === '' && $b === $chain[0]) || ($b === 'daytona' && $why === 'failover') || ($b === 'e2b' && e2b_map_get($sid) !== null) || ($b === 'csb' && csb_map_get($sid) !== null);
            if (!$on) { continue; }
            $v = (sbx_px($b) . '_purge')($cfg, $sid);
            if (empty($v['ok'])) { $r = $v; }
        }
        if (sbx_route_get($sid) !== '') { sbx_route_set([$sid => null, $sid . '~why' => null]); }
        return $r;
    }
    $r = sbx_request($cfg, 'DELETE', '/v1/s/' . $sid . '?purge=1', '', 8);
    return $r['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $r['error']];
}

/** one line for the agent prompt describing the sandbox computer */
function sbx_env_text(array $cfg, string $sid = ''): string {
    $be = sbx_is_cloud($cfg) ? sbx_backend($cfg, $sid) : 'devil';
    if ($be === 'e2b') {
        $why = $sid !== '' ? sbx_route_get($sid . '~why') : '';
        return 'Your sandbox (the Devil AI sandbox): Debian 12 Linux, user "user" with passwordless sudo (apt-get works), working folder /home/user/work (relative paths are relative to it). Installed: Node.js 22 (npm, pnpm, yarn), Python 3 (pip, Flask, FastAPI), git, curl, jq, zip, sqlite3, Playwright Chromium for the browser tool. '
             . 'FULL internet access: any website or API can be reached from the sandbox. Machine: 2 CPU, small RAM — avoid running several heavy dev servers at once. '
             . 'Previews already reach dev servers as "localhost", so Vite/CRA host checks never block them; always listen on 0.0.0.0. '
             . 'When idle the sandbox is paused and later resumed exactly as it was (files AND running servers are kept).'
             . ($why === 'internet' ? ' (This chat was moved here for full internet: the earlier files were copied; node_modules / virtualenvs were not — reinstall them.)' : '')
             . ($why === 'failover' ? ' (This chat runs on the backup sandbox; files made earlier on the main sandbox may be missing — recreate them if needed.)' : '');
    }
    if ($be === 'csb') {
        $why = $sid !== '' ? sbx_route_get($sid . '~why') : '';
        return 'Your sandbox (the Devil AI sandbox): Ubuntu 20.04 Linux, you are root (apt-get works), working folder /home/user/work (relative paths are relative to it). Installed: Node.js 20 (npm, yarn, pnpm), Python 3.10 (pip), git, curl, zip, sqlite3, Playwright Chromium for the browser tool. '
             . 'FULL internet access: any website or API can be reached from the sandbox. Machine: 2 CPU, 4 GB RAM. '
             . 'Always listen on 0.0.0.0. For Vite keep server.allowedHosts: true. '
             . 'When idle the sandbox hibernates and later wakes up exactly as it was (files AND running servers are kept).'
             . ($why === 'internet' ? ' (This chat was moved here for full internet: the earlier files were copied; node_modules / virtualenvs were not — reinstall them.)' : '')
             . ($why === 'failover' ? ' (This chat runs on the backup sandbox; files made earlier on the main sandbox may be missing — recreate them if needed.)' : '');
    }
    if ($be === 'vercel') {
        $why = $sid !== '' ? sbx_route_get($sid . '~why') : '';
        return 'Your sandbox (the Devil AI sandbox): Linux with passwordless sudo (apt-get works), working folder /home/user/work (relative paths are relative to it). Installed: Python 3.14 (pip, uv), Node.js 24 (npm, pnpm, bun), git, curl, jq, zip, sqlite3; the browser tool sets up Chromium by itself on first use. '
             . 'FULL internet access: any website or API can be reached from the sandbox. Machine: 2 CPU, 4 GB RAM, plenty of disk. '
             . 'Previews are served through a proxy on a different hostname — for Vite set server.allowedHosts: true and host 0.0.0.0 (other dev servers: allow any host). '
             . 'The sandbox sleeps when idle and keeps its files, but running servers stop — restart them with start_server when needed.'
             . ($why === 'internet' ? ' (This chat was moved here for full internet: the earlier files were copied; node_modules / virtualenvs were not — reinstall them.)' : '')
             . ($why === 'failover' ? ' (This chat runs on the backup sandbox; files made earlier on the main sandbox may be missing — recreate them if needed.)' : '');
    }
    if ($be === 'daytona') {
        $more = sbx_dual($cfg) ? ' If the task truly needs the open internet INSIDE the sandbox (scraping a site, calling an outside API from code, downloading from a normal website), call the full_internet tool once — the chat moves to a bigger sandbox with full internet and your files are copied.' : '';
        return 'Your sandbox (the Devil AI sandbox): Linux with passwordless sudo, working folder /home/user/work (relative paths are relative to it). Installed: Python 3 (pip), Node.js (npm), git, curl, jq, zip, Chromium. '
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

/** make several images AT ONCE (parallel requests); failures fall back to the one-by-one fetcher while time is left */
function sbx_fetch_images(array $prompts): array {
    $isImg = static function (string $b): bool {
        return strncmp($b, "\x89PNG", 4) === 0 || strncmp($b, "\xFF\xD8\xFF", 3) === 0 || (strncmp($b, 'RIFF', 4) === 0 && substr($b, 8, 4) === 'WEBP');
    };
    $t0 = microtime(true);
    $budget = function_exists('devil_time_left') ? max(20, devil_time_left(90) - 10) : 80;
    $mh = curl_multi_init(); $hs = []; $out = [];
    foreach ($prompts as $k => $pr) {
        $ch = curl_init('https://prexzyapis.com/ai/aiappgen');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => (int)min(45, $budget - 5), CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => 'DevilAI/1.0', CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => (string)json_encode(['prompt' => mb_substr((string)$pr, 0, 1500), 'width' => '1024', 'height' => '1024'])]);
        curl_multi_add_handle($mh, $ch); $hs[$k] = $ch;
    }
    do { $st = curl_multi_exec($mh, $run); if ($run) { curl_multi_select($mh, 1.0); } } while ($run && $st === CURLM_OK);
    foreach ($hs as $k => $ch) {
        $b = (string)curl_multi_getcontent($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
        $out[$k] = ($code === 200 && $isImg($b)) ? ['ok' => true, 'data' => $b] : ['ok' => false, 'error' => 'image service failed'];
    }
    curl_multi_close($mh);
    foreach ($out as $k => $r) {
        if (!empty($r['ok'])) { continue; }
        if (microtime(true) - $t0 > $budget - 25) { $out[$k] = ['ok' => false, 'error' => 'not made in time — call generate_image again for this one']; continue; }
        $out[$k] = sbx_fetch_image((string)$prompts[$k]);
    }
    return $out;
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
    /* github_pr only when this chat has a connected repository */
    if ($cfg !== [] && empty($cfg['_gh'])) { unset($t['github_pr']); }
    return $t;
}

function sbx_agent_tools_all(): array {
    return [
        'bash'           => 'Run a shell command in your Linux sandbox (cwd /home/user/work). Output and exit code come back. A command that is still running after ~25s keeps running in the background and you get its pid and log file — check it later with tail. Use start_server (not bash) for servers. INPUT: the command(s); several lines are fine.',
        'write_file'     => 'Create or overwrite a file. INPUT: first line = path (relative to /home/user/work), then the full file content on the following lines.',
        'append_file'    => 'Add text to the END of a file (creates it if missing). Use it to write a big file in parts: write_file the first part, then append_file the rest. INPUT: first line = path, then the text to add.',
        'edit_file'      => 'Change PART of an existing file without rewriting it (best for fixes and small changes). INPUT: line 1 = path, then one or more blocks: a line "<<<<<<< SEARCH", the exact current lines, a line "=======", the new lines, a line ">>>>>>> REPLACE". The SEARCH lines must match the file (indentation may differ) and appear only once.',
        'read_file'      => 'Read a text file from the sandbox. INPUT: path.',
        'list_files'     => 'List files in the workspace. INPUT: a folder path, or "." for everything.',
        'start_server'   => 'Start a long-running app/dev server in the background and get its public preview URL. INPUT: first line = port, second line = command (bind to 0.0.0.0).',
        'deploy_site'    => 'Publish a finished website to the USER\'S OWN hosting server (their FTP/SFTP account from Settings → Hosting) and get its live URL. Use only when the user wants the site hosted/published/deployed on their server. INPUT: first line = folder inside /home/user/work to upload (e.g. site or app/dist — for React/Vite run the build first and deploy dist), optional second line = site name (letters, numbers, dashes; it becomes the sub-folder and URL path).',
        'browser'        => 'Open a page in a headless Chromium inside the sandbox (works for http://localhost:PORT too): returns title, visible text, console errors, failed files, BROKEN IMAGES, sideways overflow and saves a screenshot. INPUT: line 1 = URL; optional next lines = actions: "mobile", "click <css selector>", "fill <css selector> = <text>", "select <css selector> = <value>", "check <css selector>", "press Enter", "wait 1000", "goto <url>". Authorized logins are allowed only after user authorization is clear. For credentials use secure ask_user need fields and fill with {{ENV_NAME}} placeholders — never put literal passwords in actions. CAPTCHA/MFA/access controls must not be bypassed.',
        'generate_image' => 'Generate an image from a text prompt and save it in the workspace. INPUT: first line = output path (e.g. images/hero.png), second line = the prompt. SEVERAL IMAGES? Make them in ONE call (much faster, made at the same time, max 8): one image per line as "path | prompt", e.g. "images/g1.jpg | cozy cafe interior" newline "images/g2.jpg | latte art close-up".',
        'image_search'   => 'Find REAL photos on the web (openly licensed: Openverse / Wikimedia Commons) and save them in the workspace — for real places, foods, animals, landmarks, products, people at work… INPUT: line 1 = what to look for in simple English (e.g. "masala chai glass"); optional lines "count: 3" (1-6) and "folder: images". Returns the saved paths with credit/license.',
        'generate_speech'=> 'Turn text into natural spoken audio (voiceovers, narration, podcasts, pronunciation). INPUT: line 1 = output file (.mp3 or .wav, e.g. audio/intro.mp3); optional "voice: Kore" (voices: Kore firm, Puck upbeat, Charon informative, Zephyr bright, Aoede breezy, Leda youthful, Fenrir excitable, Sulafat warm, Achird friendly, Gacrux mature …); optional "voices: Host=Kore, Guest=Puck" for a two-person dialogue whose lines start with "Host:" / "Guest:"; optional "style: warm and slow"; then the text (up to ~4,000 characters, any language).',
        'present_file'   => 'Open a finished file (report, document, slides, sheet, image, audio, video, PDF, page) in the user\'s viewer so they see the result right away. INPUT: path. Use it once for the main deliverable at the end.',
        'stop_server'    => 'Stop the app/server listening on a port. INPUT: port.',
        'github_pr'      => 'Commit all changes in the connected GitHub repository on your working branch, push it and open (or update) the pull request. INPUT: line 1 = PR title, then a short description (what changed, how it was tested). Returns the PR link. To MERGE the open pull request write only "merge" as the input — do this ONLY when the latest user message explicitly asks to merge / accept the PR (never on your own).',
        'full_internet'  => 'Move this chat to a sandbox with FULL internet (2 CPU, 4 GB RAM). Use it only when the task needs websites/APIs that the current sandbox cannot reach. Your /home/user/work files are copied (node_modules / venvs are not); running servers must be restarted. INPUT: one short reason.',
        'ask_user'       => 'Ask the user a clarifying question (or for something you need) and stop until they answer. INPUT: first line = the question, then up to 4 short options, one per line starting with "- " (optional " — explanation"). For secrets add lines "need: ENV_NAME | label" instead of options. Several questions at once (max 4): start each with "Q: " and put its "- " options under it.',
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
/* ── never reveal which cloud runs the sandbox ──
   Every tool result (text + meta) and every sandbox error shown to the user or the model goes through
   sbx_scrub(): provider names become "sandbox" and raw provider preview links become our own preview links. */
/* Environment every agent command starts with: the user's saved secrets, plus dev-server host checks
   opened up so the public preview link works (Vite blocks unknown hosts; CRA/webpack too). */
function sbx_env_prefix(array $cfg, string $sid): string {
    $s = '[ -f ' . SBX_SECRETS_FILE . ' ] && . ' . SBX_SECRETS_FILE . '; export DANGEROUSLY_DISABLE_HOST_CHECK=true;';
    if (sbx_is_cloud($cfg)) {
        $be = sbx_backend($cfg, $sid);
        $s .= ' export __VITE_ADDITIONAL_SERVER_ALLOWED_HOSTS=' . ($be === 'vercel' ? '.vercel.run' : ($be === 'e2b' ? '.e2b.app' : ($be === 'csb' ? '.csb.app' : '.daytonaproxy01.net'))) . ';';
    }
    return $s;
}

/* After write_file / append_file: warn the agent when a file looks cut off (its reply hit the length limit)
   so it finishes the file with append_file instead of shipping half a stylesheet. */
function sbx_file_check(string $path, string $content, bool $cut = false): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $c = rtrim($content);
    $why = '';
    if (in_array($ext, ['css', 'scss', 'js', 'mjs', 'ts', 'tsx', 'jsx', 'json', 'php', 'java', 'c', 'cpp', 'go', 'rs'], true)) {
        $code = preg_replace('#/\*.*?\*/#s', '', $c);
        $code = preg_replace('#(["\'`])(?:\\\\.|(?!\1).)*\1#s', '""', (string)$code);
        $open = substr_count((string)$code, '{') - substr_count((string)$code, '}');
        if ($open > 0) { $why = $open . ' unclosed { brace' . ($open > 1 ? 's' : ''); }
        elseif ($ext !== 'css' && $ext !== 'scss') {
            $po = substr_count((string)$code, '(') - substr_count((string)$code, ')');
            $bo = substr_count((string)$code, '[') - substr_count((string)$code, ']');
            if ($po > 0) { $why = $po . ' unclosed ( parenthes' . ($po > 1 ? 'es' : 'is'); }
            elseif ($bo > 0) { $why = $bo . ' unclosed [ bracket' . ($bo > 1 ? 's' : ''); }
            elseif (preg_match('/[\'"`][^\'"`\n]*$/', rtrim($c)) && preg_match('/[(,=+:]\s*[\'"`][^\'"`\n]*$/', rtrim($c))) { $why = 'it ends inside an unfinished string'; }
        }
    } elseif (in_array($ext, ['html', 'htm'], true)) {
        if (stripos($c, '<html') !== false && stripos($c, '</html>') === false) { $why = 'no closing </html>'; }
        elseif (stripos($c, '<body') !== false && stripos($c, '</body>') === false) { $why = 'no closing </body>'; }
    }
    if ($why === '' && !$cut) { return ''; }
    $tail = mb_substr($c, -120);
    if ($why === '') {
        return "\nNote: your message was very long and may have been cut off by the length limit. The file ends with:\n" . $tail
            . "\nIf that is not the real end, add the missing rest with append_file (only the missing part). If it is complete, carry on.";
    }
    return "\n⚠ This file looks UNFINISHED (" . ($why !== '' ? $why : 'your message was cut off by the length limit') . "). It currently ends with:\n" . $tail
        . "\nYour replies are cut at about 5,000 characters. Add the missing rest with append_file (only the missing part, continuing exactly from that point) before moving on.";
}

/**
 * Static check of the web files the agent built (runs inside the sandbox, no model call):
 * missing local files referenced by HTML/CSS, cut-off HTML/CSS, JavaScript syntax errors, placeholder text.
 * $roots: folders relative to the work folder ('.' = top level). Returns a list of problems (max 30).
 */
/** right after a .js/.html write: do the ids in JS and HTML still match? (catches the #1 bug class at write time) */
function sbx_id_check_after_write(array $cfg, string $sid, string $path, string $content): string {
    if (!preg_match('/\.(js|html?)$/i', $path) || strpos($path, 'node_modules/') !== false || preg_match('/\.min\.js$/i', $path)) { return ''; }
    if (preg_match('/\.html?$/i', $path) && stripos($content, '</html>') === false) { return ''; } /* still being written in parts */
    $root = strpos($path, '/') !== false ? strstr($path, '/', true) : '.';
    try { $iss = sbx_site_doctor($cfg, $sid, [$root]); } catch (\Throwable $e) { return ''; }
    $keep = [];
    foreach ($iss as $x) { if (strpos((string)$x, 'uses ids that NO HTML page has') !== false || strpos((string)$x, 'DUPLICATE id') !== false) { $keep[] = (string)$x; } }
    return $keep ? "\n⚠ ID CHECK (JS and HTML must use the same ids):\n- " . implode("\n- ", array_slice($keep, 0, 4)) : '';
}

function sbx_site_doctor(array $cfg, string $sid, array $roots): array {
    $roots = array_values(array_unique(array_filter(array_map('strval', $roots), static function ($r) { return $r !== '' && strpos($r, '..') === false; })));
    if (!$roots) { return []; }
    $py = <<<'PY'
import os, re, sys, json, shutil, subprocess
W = os.getcwd()
roots = json.load(open(sys.argv[1]))
SKIP = {"node_modules", ".git", ".devil", "dist", "build", ".next", "venv", ".venv", "__pycache__", "uploads", ".cache"}
ATTR = re.compile(r"""\s(?:src|href|poster|data-src)\s*=\s*(?:"([^"]+)"|'([^']+)'|([^\s"'>]+))""", re.I)
CSSU = re.compile(r"""url\(\s*["']?([^"')]+)["']?\s*\)""", re.I)
SCRIPT = re.compile(r"""<script[^>]+src\s*=\s*(?:"([^"]+)"|'([^']+)'|([^\s"'>]+))""", re.I)
PLACE = re.compile(r"lorem ipsum|coming soon|\bTODO\b|your content here|placeholder text", re.I)
issues, seen, jsfiles = [], set(), set()
allids, inline_js = set(), []
FW = ("react", "vue", "next", "vite", "svelte", "@sveltejs/kit", "@angular/core", "nuxt", "astro", "parcel", "webpack", "solid-js", "preact", "gatsby", "@remix-run/react", "react-scripts")
def is_framework(top):
    pj = os.path.join(top, "package.json")
    if not os.path.exists(pj):
        return False
    try:
        d = json.load(open(pj, encoding="utf-8"))
        deps = {}
        deps.update(d.get("dependencies") or {}); deps.update(d.get("devDependencies") or {})
        return any(k in deps for k in FW)
    except Exception:
        return True
def id_words(x):
    return [w.lower() for w in re.findall(r"[A-Z]?[a-z0-9]+|[A-Z]+(?![a-z])", x)]
def same_id_meaning(a, b):
    """expense-form = expenseForm; expList = expenseList (short form of a word, all other words equal)"""
    if norm_id(a) == norm_id(b):
        return True
    wa, wb = id_words(a), id_words(b)
    if len(wa) != len(wb) or len(wa) < 2:
        return False
    for x, y in zip(wa, wb):
        if not (x == y or (len(x) >= 2 and len(y) >= 2 and (x.startswith(y) or y.startswith(x)))):
            return False
    return True
def norm_id(x):
    return re.sub(r"[-_\s]", "", x).lower()
node = shutil.which("node")
def local(ref):
    r = ref.strip()
    if not r or r.startswith(("http:", "https:", "//", "data:", "mailto:", "tel:", "javascript:", "#", "blob:")) or "{" in r or "$" in r:
        return None
    return r.split("#")[0].split("?")[0]
def resolve(base_dir, site_root, ref):
    ref = ref.replace("%20", " ")
    return os.path.normpath(os.path.join(site_root, ref.lstrip("/"))) if ref.startswith("/") else os.path.normpath(os.path.join(base_dir, ref))
def exists(base_dir, site_root, ref):
    c = resolve(base_dir, site_root, ref)
    return os.path.exists(c) or os.path.exists(os.path.join(c, "index.html"))
for root in roots:
    top = os.path.normpath(os.path.join(W, root))
    if not os.path.isdir(top):
        continue
    framework = is_framework(top)
    depth0 = top.count(os.sep)
    for dp, dn, fn in os.walk(top):
        dn[:] = [d for d in dn if d not in SKIP and not d.startswith(".")]
        if root == "." or dp.count(os.sep) - depth0 >= 4:
            dn[:] = []
        for f in sorted(fn):
            p = os.path.join(dp, f)
            if p in seen or len(seen) > 400:
                continue
            seen.add(p)
            ext = f.rsplit(".", 1)[-1].lower() if "." in f else ""
            if ext not in ("html", "htm", "css"):
                continue
            try:
                t = open(p, encoding="utf-8", errors="replace").read()
            except Exception:
                continue
            rel = os.path.relpath(p, W)
            refs = []
            if ext in ("html", "htm"):
                low = t.lower()
                if "<html" in low and "</html>" not in low:
                    issues.append(rel + ": UNFINISHED - no closing </html> (the file was cut off; append the missing end)")
                if not framework:
                    cnt = {}
                    for i in re.findall(r'\bid\s*=\s*["\']([^"\']+)["\']', t):
                        cnt[i] = cnt.get(i, 0) + 1
                        allids.add(i)
                    for blk in re.findall(r"<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>", t, re.S | re.I):
                        if blk.strip():
                            inline_js.append((rel + " (inline script)", blk))
                    dups = [k for k, v in cnt.items() if v > 1]
                    if dups:
                        issues.append(rel + ": DUPLICATE id(s) " + ", ".join("#" + k for k in dups[:8]) + " - a section/form was written twice (write_file + append_file overlap?); keep ONE copy")
                    m = PLACE.search(re.sub(r"<[^>]+>", " ", t))
                    if m:
                        issues.append(rel + ": contains placeholder text (" + m.group(0) + ") - write the real content")
                    refs = ["".join(g) for g in ATTR.findall(t)] + CSSU.findall(t)
                    for sref in ["".join(g) for g in SCRIPT.findall(t)]:
                        l = local(sref)
                        if l and exists(dp, top, l):
                            jsfiles.add(resolve(dp, top, l))
            else:
                o = t.count("{") - t.count("}")
                if o:
                    issues.append(rel + ": " + str(abs(o)) + " unbalanced { } brace(s) - the CSS is probably cut off")
                if not framework:
                    refs = CSSU.findall(t)
            missing = []
            for r in refs:
                l = local(r)
                if l and l not in missing and not exists(dp, top, l):
                    missing.append(l)
            if missing:
                issues.append(rel + ": " + str(len(missing)) + " missing file(s): " + ", ".join(missing[:12]) + (" ..." if len(missing) > 12 else ""))
if node:
    for jp in sorted(jsfiles)[:30]:
        if not os.path.isfile(jp):
            continue
        try:
            src = open(jp, encoding="utf-8", errors="replace").read()
            mod = re.search(r"^\s*(import|export)\s", src, re.M) is not None
            r = subprocess.run([node, "--input-type=module" if mod else "--input-type=commonjs", "--check"], input=src, capture_output=True, text=True, timeout=20)
            if r.returncode != 0:
                lines = [x for x in r.stderr.splitlines() if x.strip()]
                where = next((x for x in lines if x.startswith("[stdin]:")), "")
                err = next((x for x in lines if "Error" in x), lines[-1] if lines else "syntax error")
                issues.append(os.path.relpath(jp, W) + ": JavaScript syntax error " + where.replace("[stdin]", "line") + ": " + err[:160])
        except Exception:
            pass
# quality: a too-basic design, and generated images that no page uses
IMGX = (".jpg", ".jpeg", ".png", ".webp", ".gif", ".svg", ".avif")
for root in roots:
    top = os.path.normpath(os.path.join(W, root))
    if not os.path.isdir(top) or is_framework(top):
        continue
    pages, html_bytes, rules, text, imgs = 0, 0, 0, "", []
    depth0 = top.count(os.sep)
    for dp, dn, fn in os.walk(top):
        dn[:] = [d for d in dn if d not in SKIP and not d.startswith(".")]
        if root == "." or dp.count(os.sep) - depth0 >= 4:
            dn[:] = []
        for f in fn:
            p = os.path.join(dp, f)
            low = f.lower()
            if low.endswith(IMGX):
                imgs.append(os.path.relpath(p, W))
                continue
            if not low.endswith((".html", ".htm", ".css", ".js")) or low.endswith(".min.js"):
                continue
            try:
                t = open(p, encoding="utf-8", errors="replace").read()
            except Exception:
                continue
            text += t + "\n"
            if low.endswith((".html", ".htm")):
                pages += 1
                html_bytes += len(t)
                for blk in re.findall(r"<style[^>]*>(.*?)</style>", t, re.S | re.I):
                    rules += blk.count("{") - len(re.findall(r"@media|@keyframes|@supports", blk))
            elif low.endswith(".css"):
                rules += t.count("{") - len(re.findall(r"@media|@keyframes|@supports", t))
    css_fw = re.search(r"tailwind|bootstrap(\.min)?\.css|bulma|daisyui|materialize|uikit|foundation(\.min)?\.css|pico(\.min)?\.css", text, re.I)
    if pages and rules < 30 and (pages >= 2 or html_bytes > 1200) and not css_fw:
        issues.append((root if root != "." else "site") + ": the design is too basic (only " + str(rules) + " CSS rules for " + str(pages) + " page(s)). Make it look professional: colour palette, typography, spacing, a styled nav/header and footer, cards, buttons with hover, hero, and responsive @media rules.")
    unused = [i for i in imgs if os.path.basename(i) not in text and "/.devil/" not in i and not os.path.basename(i).lower().startswith(("favicon", "shot-"))]
    if unused and pages:
        issues.append(str(len(unused)) + " image(s) are not used by any page: " + ", ".join(unused[:8]) + (" ..." if len(unused) > 8 else "") + " - show them on the right pages (e.g. <img src=...> on the menu/gallery cards) or delete them.")

# JavaScript asks for ids that no HTML page has (getElementById returns null → the feature silently does nothing)
IDREF = re.compile(r"""getElementById\(\s*['"`]([\w-]+)['"`]\s*\)|querySelector(?:All)?\(\s*['"`]#([\w-]+)['"`]\s*\)""")
if allids:
    srcs = []
    for jp in sorted(jsfiles)[:30]:
        try:
            srcs.append((os.path.relpath(jp, W), open(jp, encoding="utf-8", errors="replace").read()))
        except Exception:
            pass
    srcs += inline_js[:20]
    normmap = {}
    for i in allids:
        normmap.setdefault(norm_id(i), i)
    for name, txt in srcs:
        bad = []
        # helpers like  const $ = id => document.getElementById(id)  /  function byId(x) { return document.getElementById(x) }
        helpers = set(re.findall(r"(?:const|let|var)\s+([\w$]+)\s*=\s*(?:function\s*)?\(?\s*\w+\s*\)?\s*(?:=>)?\s*\{?\s*(?:return\s+)?document\.(?:getElementById|querySelector)\(", txt))
        helpers |= set(re.findall(r"function\s+([\w$]+)\s*\(\s*\w+\s*\)\s*\{\s*return\s+document\.(?:getElementById|querySelector)\(", txt))
        hre = re.compile(r"(?<![\w$.])(?:" + "|".join(re.escape(h) for h in helpers) + r")\(\s*['\"`]#?([\w-]+)['\"`]\s*\)") if helpers else None
        for ln_no, ln in enumerate(txt.split("\n"), 1):
            found = [m.group(1) or m.group(2) for m in IDREF.finditer(ln)] + ([m.group(1) for m in hre.finditer(ln)] if hre else [])
            for i in found:
                if i in allids or any(i == b[0] for b in bad):
                    continue
                if re.search(r"""(?:\bid\s*[=:]\s*\\?['"`]|\.id\s*=\s*['"`]|setAttribute\(\s*['"]id['"]\s*,\s*['"`])""" + re.escape(i) + r"\b", txt):
                    continue
                bad.append((i, ln_no))
        if bad:
            parts, sure = [], []
            for i, ln_no in bad[:12]:
                cands = [h for h in sorted(allids) if same_id_meaning(i, h)]
                sim = cands[0] if len(cands) == 1 else None
                if sim:
                    sure.append((i, sim))
                parts.append("#" + i + " (line " + str(ln_no) + ")" + (" = #" + sim + " in the HTML" if sim else ""))
            issues.append(name + ": uses ids that NO HTML page has, so that code silently does nothing: " + ", ".join(parts) + ". Ids in the HTML: " + ", ".join("#" + x for x in sorted(allids)[:40]) + ". Make the ids match: keep the HTML, change only the names in the JS (pick the HTML id with the SAME meaning; if the element really is missing, add it to the HTML). Do NOT rewrite both files - that invents new mismatches."
                + ((" QUICK FIX - run exactly this bash command: perl -pi -e '" + "; ".join("s/(?<=[#\"\\x27\\x60])" + a + "(?=[\"\\x27\\x60])/" + b + "/g" for a, b in sure) + "' " + name) if sure and "(inline" not in name else ""))
print(json.dumps(issues[:30]))
PY;
    $cmd = "mkdir -p .devil && cat > .devil/doctor.py <<'DEVILPY'\n" . $py . "\nDEVILPY\ncat > .devil/doctor.json <<'DEVILJS'\n" . json_encode($roots, JSON_UNESCAPED_SLASHES) . "\nDEVILJS\npython3 .devil/doctor.py .devil/doctor.json 2>/dev/null | tail -c 8000";
    $r = sbx_exec($cfg, $sid, $cmd, 45);
    if (empty($r['ok'])) { return []; }
    $raw = trim((string)($r['stdout'] ?? ''));
    $j = json_decode(trim((string)substr($raw, (int)strrpos("\n" . $raw, "\n"))), true);
    return is_array($j) ? array_values(array_map('strval', $j)) : [];
}

/** generated photos come as big JPEG/PNG files (1–2 MB): resize to ≤1600 px and re-encode (JPEG q80 / WebP) so sites load fast */
function sbx_compress_image(string $data, string &$path): string {
    if (strlen($data) < 200000 || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) { return $data; }
    $im = @imagecreatefromstring($data);
    if (!$im) { return $data; }
    $w = imagesx($im); $h = imagesy($im);
    $max = 1600;
    if ($w > $max || $h > $max) {
        $r = min($max / $w, $max / $h); $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im); $im = $dst;
    }
    $newPath = $path;
    ob_start();
    if (preg_match('/\.webp$/i', $path) && function_exists('imagewebp')) { imagewebp($im, null, 80); }
    else {
        if (preg_match('/\.png$/i', $path)) { $newPath = (string)preg_replace('/\.png$/i', '.jpg', $path); }
        imageinterlace($im, true);
        imagejpeg($im, null, 80);
    }
    $out = (string)ob_get_clean();
    imagedestroy($im);
    if ($out === '' || strlen($out) >= strlen($data)) { return $data; }
    $path = $newPath;
    return $out;
}

/** browser tool action lines → [[kind, selector, value], …] (max 25) */
function sbx_browser_actions(array $lines): array {
    $out = [];
    foreach ($lines as $ln) {
        $ln = trim((string)$ln);
        if ($ln === '' || count($out) >= 25) { continue; }
        $ln = (string)preg_replace('/^(?:[-*•]|\d+[.)])\s+/', '', $ln);
        if (!preg_match('/^(mobile|click|fill|type|select|check|press|wait|goto)\b\s*(.*)$/i', $ln, $m)) { continue; }
        $k = strtolower($m[1]); if ($k === 'type') { $k = 'fill'; }
        $rest = trim($m[2]); $val = '';
        if (in_array($k, ['fill', 'select'], true)) {
            $pos = strpos($rest, ' = ');
            if ($pos === false) { $pos = strpos($rest, ' => '); $sepLen = 4; } else { $sepLen = 3; }
            if ($pos !== false) { $val = trim(substr($rest, $pos + $sepLen)); $rest = trim(substr($rest, 0, $pos)); }
            $val = (string)preg_replace('/^"(.*)"$|^\'(.*)\'$/s', '$1$2', $val);
        }
        $out[] = [$k, $rest, $val];
    }
    return $out;
}
function sbx_scrub(string $t, array $cfg = []): string {
    if ($t === '') { return $t; }
    /* provider billing text ("Hobby plan usage limit exceeded … upgrade …") never reaches the user */
    if (stripos($t, 'usage limit exceeded') !== false) {
        $t = (string)preg_replace('/\b[A-Za-z]+ plan usage limit exceeded[^\n]*/i', 'the workspace service is temporarily unavailable', $t);
    }
    if (!preg_match('/daytona|vercel|e2b|csb|codesandbox/i', $t)) { return $t; }
    $t = (string)preg_replace_callback('#https?://[a-z0-9.-]+\.(?:vercel\.run|daytonaproxy\d*\.net|e2b\.app|csb\.app)(?::\d+)?[^\s"\'<>)\]]*#i', static function ($m) use ($cfg) {
        $u = $cfg ? sbx_public_url($cfg, $m[0]) : $m[0];
        return $u !== $m[0] ? $u : 'the preview link';
    }, $t);
    $t = (string)preg_replace('#[a-z0-9.-]*\.(?:vercel\.run|daytonaproxy\d*\.net|e2b\.(?:app|dev|local)|csb\.app|codesandbox\.(?:io|stream))#i', 'preview-host', $t);
    $t = (string)preg_replace('/\bcode[ -]?sandbox(?:[ -]?(?:sdk|preview|devbox|vm))?\b/i', 'sandbox', $t);
    $t = (string)preg_replace('/\bCSB_[A-Z0-9_]*/', 'SANDBOX_ENV', $t);
    $t = (string)preg_replace('/\bE2B_[A-Z0-9_]*/', 'SANDBOX_ENV', $t);
    $t = (string)preg_replace('/\be2b(?:[-_ ]?(?:dev|sandbox|code[-_ ]interpreter|desktop))?\b/i', 'sandbox', $t);
    $t = (string)preg_replace('#[a-z0-9.-]*\bdaytona\.(?:io|work|app)\b[^\s"\'<>)]*#i', 'sandbox-api', $t);
    $t = (string)preg_replace('#/home/(?:daytona|vercel-sandbox)\b#', '/home/sandbox', $t);
    $t = (string)preg_replace('/\b(?:VERCEL|DAYTONA)_[A-Z0-9_]*/', 'SANDBOX_ENV', $t);
    $t = (string)preg_replace('/vercel[-_ ]?sandbox(?:es)?/i', 'sandbox', $t);
    $t = (string)preg_replace('/\bvercel(?=\s+sandbox|\s+session|\s+api\b)/i', 'the', $t);
    $t = (string)preg_replace('/daytona(?:proxy\d*)?/i', 'sandbox', $t);
    return $t;
}
function sbx_scrub_any($v, array $cfg = []) {
    if (is_string($v)) { return sbx_scrub($v, $cfg); }
    if (is_array($v)) { foreach ($v as $k => $x) { $v[$k] = sbx_scrub_any($x, $cfg); } }
    return $v;
}
function sbx_run_tool(array $ctx, string $name, string $input): array {
    /* the model only ever sees /home/sandbox (scrubbed) — map it back to the real home folder */
    if (strpos($input, '/home/sandbox') !== false && sbx_is_cloud((array)$ctx['cfg'])) {
        $beNow = sbx_backend((array)$ctx['cfg'], (string)$ctx['sid']);
        $real = $beNow === 'vercel' ? '/home/vercel-sandbox' : (($beNow === 'e2b' || $beNow === 'csb') ? '/home/user' : '/home/daytona');
        $input = str_replace('/home/sandbox', $real, $input);
    }
    $r = sbx_run_tool_raw($ctx, $name, $input);
    return sbx_secret_mask(sbx_scrub_any($r, (array)$ctx['cfg']), sbx_secret_values((string)$ctx['sid'], (array)$ctx['cfg']), $name === 'browser' ? 1 : 6);
}
function sbx_run_tool_raw(array $ctx, string $name, string $input): array {
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
            $cmd = sbx_env_prefix($cfg, $sid) . "\n" . $cmd;
            $wrapper = 'mkdir -p /home/user/.bg; J=' . $job . '; echo ' . base64_encode($cmd) . ' | base64 -d > "$J.sh"; '
                . 'nohup setsid bash -c \'bash -l "$1" > >(tee "$1.out" >> "$1.log") 2> >(tee "$1.err" >> "$1.log"); r=$?; sleep 0.2; echo $r > "$1.rc"\' _ "$J.sh" > /dev/null 2>&1 < /dev/null & P=$!; '
                . 'i=0; while [ ! -f "$J.sh.rc" ] && [ $i -lt ' . ($wait * 5) . ' ]; do sleep 0.2; i=$((i+1)); done; '
                . 'if [ -f "$J.sh.rc" ]; then sz=$(stat -c %s "$J.sh.out" 2>/dev/null || echo 0); '
                . 'if [ "$sz" -gt 14000 ]; then head -c 3000 "$J.sh.out"; echo; echo "… [$sz bytes of output, middle cut] …"; tail -c 10000 "$J.sh.out"; else cat "$J.sh.out" 2>/dev/null; fi; '
                /* stderr comes back after a marker so the UI can show STDOUT and STDERR separately */
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
            return ['ok' => true, 'text' => 'Wrote ' . $path . ' (' . strlen($content) . ' bytes, ' . substr_count($content, "\n") . ' lines).' . sbx_file_check($path, $content, !empty($ctx['cut'])) . sbx_id_check_after_write($cfg, $sid, $path, $content), 'meta' => ['path' => $path, 'bytes' => strlen($content)]];
        }
        case 'append_file': {
            $lines = preg_split('/\r?\n/', ltrim($input, "\r\n"), 2);
            $path = sbx_rel((string)($lines[0] ?? ''));
            $add = sbx_unfence((string)($lines[1] ?? ''));
            if ($path === '.' || $path === '') { return ['ok' => false, 'text' => 'append_file: the first line must be the file path.']; }
            if ($add === '') { return ['ok' => false, 'text' => 'append_file: nothing to add.']; }
            $old = sbx_read($cfg, $sid, $path);
            $prev = !empty($old['ok']) ? (string)$old['data'] : '';
            if (empty($old['ok']) && (int)($old['status'] ?? 404) !== 404 && stripos((string)($old['error'] ?? ''), 'not found') === false && stripos((string)($old['error'] ?? ''), 'no such') === false) {
                return ['ok' => false, 'text' => 'Could not read ' . $path . ' to append: ' . (string)($old['error'] ?? '')];
            }
            if ($prev !== '' && substr($prev, -1) !== "\n") { $prev .= "\n"; }
            $content = $prev . $add;
            if (substr($content, -1) !== "\n") { $content .= "\n"; }
            $r = sbx_write($cfg, $sid, $path, $content);
            if (empty($r['ok'])) { return ['ok' => false, 'text' => 'Could not write ' . $path . ': ' . (string)($r['error'] ?? '')]; }
            $dupWarn = '';
            if ($prev !== '' && preg_match('/\.(html?|php|vue|jsx|tsx|svelte)$/i', $path)) {
                preg_match_all('/\bid\s*=\s*["\']([^"\'{}$]+)["\']/i', $prev, $m1); preg_match_all('/\bid\s*=\s*["\']([^"\'{}$]+)["\']/i', $add, $m2);
                $rep = array_values(array_unique(array_intersect($m2[1], $m1[1])));
                $bits = [];
                if ($rep) { $bits[] = 'it adds id ' . implode(', ', array_map(static function ($x) { return '#' . $x; }, array_slice($rep, 0, 6))) . ' which the file ALREADY had'; }
                if (stripos($prev, '</html>') !== false && trim($add) !== '') { $bits[] = 'the file already ended with </html>, so this text landed AFTER the end of the page'; }
                if (stripos($add, '<!doctype') !== false || stripos($add, '<head') !== false) { $bits[] = 'it starts a second <head>/<!DOCTYPE> page inside the same file'; }
                if ($bits) { $dupWarn = "\n⚠ DUPLICATE CONTENT: " . implode('; ', $bits) . '. You probably repeated a part that was already written. Read the file (bash: grep -n \'id=\' ' . $path . ') and remove the repeated part, or rewrite the file cleanly with write_file.'; }
            }
            return ['ok' => true, 'text' => 'Appended ' . strlen($add) . ' bytes to ' . $path . ' (now ' . strlen($content) . ' bytes, ' . substr_count($content, "\n") . ' lines).' . $dupWarn . sbx_file_check($path, $content, !empty($ctx['cut'])) . sbx_id_check_after_write($cfg, $sid, $path, $content), 'meta' => ['path' => $path, 'bytes' => strlen($content)]];
        }
        case 'edit_file':
            return xt_tool_edit_file($cfg, $sid, $input, !empty($ctx['cut']));
        case 'image_search':
            return xt_tool_image_search($cfg, $sid, $input);
        case 'generate_speech':
            return xt_tool_speech($cfg, $sid, $input);
        case 'present_file':
            return xt_tool_present($cfg, $sid, $input);
        case 'stop_server':
            return xt_tool_stop_server($cfg, $sid, $input);
        case 'github_pr': {
            if (empty($cfg['_gh']) || empty($ctx['uid'])) { return ['ok' => false, 'text' => 'No GitHub repository is connected to this chat. The user can connect one with the GitHub button next to the message box.']; }
            $r = gh_tool_pr($cfg, $sid, (string)$ctx['uid'], (array)$cfg['_gh'], $input);
            if (!empty($r['meta']['pr']) && function_exists('gh_remember_pr')) { gh_remember_pr((string)$ctx['uid'], (array)$cfg['_gh'], (string)$r['meta']['pr'], (string)($r['meta']['pr_state'] ?? 'open')); }
            return $r;
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
            $r = sbx_exec($cfg, $sid, sbx_env_prefix($cfg, $sid) . "\n" . $cmd, 30, true, 6.0);
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
            $blines = preg_split('/\r?\n/', trim($input));
            $url = trim((string)array_shift($blines));
            if (preg_match('~^https?://[^/?#]*@~i', $url) || preg_match('/[?&](?:password|passwd|token|secret|api[_-]?key|auth|code|username|user|email)=/i', $url)) {
                return ['ok' => false, 'text' => 'For security, do not put account credentials or tokens in a URL. Ask for secure fields, then fill the page with {{ENV_NAME}} placeholders.'];
            }
            if (!preg_match('#^https?://#i', $url)) { $url = 'http://' . ltrim($url, '/'); }
            $acts = sbx_browser_actions($blines);
            foreach ($acts as $act) {
                $nav = (string)($act[1] ?? '');
                if (($act[0] ?? '') === 'goto' && (preg_match('~^https?://[^/?#]*@~i', $nav) || preg_match('/[?&](?:password|passwd|token|secret|api[_-]?key|auth|code|username|user|email)=/i', $nav))) {
                    return ['ok' => false, 'text' => 'For security, do not put account credentials or tokens in a URL. Use secure fields and browser fill placeholders.'];
                }
            }
            $shot = '.devil/screens/shot-' . date('His') . '-' . substr(bin2hex(random_bytes(3)), 0, 4) . '.png';
            $py = <<<'PY'
import asyncio, json, sys, os, re
from playwright.async_api import async_playwright
def scrub_urls(v, secrets=()):
    if isinstance(v, str):
        for secret in secrets:
            if secret: v = v.replace(secret, "[hidden]")
        v = re.sub(r"(https?://)[^/\s@]+@", r"\1[hidden]@", v, flags=re.I)
        return re.sub(r"([?&](?:password|passwd|token|secret|api[_-]?key|auth|code|username|user|email)=)[^&\s<>\"']+", r"\1[hidden]", v, flags=re.I)
    if isinstance(v, list): return [scrub_urls(x, secrets) for x in v]
    if isinstance(v, dict): return {k: scrub_urls(x, secrets) for k, x in v.items()}
    return v
async def main(url, shot, acts):
    out = {"url": url, "console": [], "errors": [], "failed": [], "actions": [], "dialogs": []}
    secret_used = False
    secret_values_used = []
    def action_label(a):
        kind = str(a[0]) if len(a) > 0 else ""
        sel = str(a[1]) if len(a) > 1 else ""
        if kind in ("fill", "select"): return kind + " " + sel + " = [hidden]"
        return " ".join(str(x) for x in a if x)
    mobile = any(a[0] == "mobile" for a in acts)
    async with async_playwright() as p:
        try:
            b = await p.chromium.launch(args=["--no-sandbox", "--disable-dev-shm-usage"])
        except Exception:
            b = await p.chromium.launch(executable_path="/usr/bin/chromium", args=["--no-sandbox"])
        pg = await b.new_page(viewport={"width": 390, "height": 844} if mobile else {"width": 1280, "height": 800}, is_mobile=mobile, has_touch=mobile)
        async def _dlg(d):
            out["dialogs"].append((d.type + ": " + d.message)[:200])
            try:
                await d.accept()
            except Exception:
                pass
        pg.on("dialog", lambda d: asyncio.ensure_future(_dlg(d)))
        pg.on("console", lambda m: out["console"].append(f"{m.type}: {m.text}"[:300]) if m.type in ("error", "warning") else None)
        pg.on("pageerror", lambda e: out["errors"].append(str(e)[:300]))
        pg.on("requestfailed", lambda q: out["failed"].append((q.url[:160] + " (" + str(q.failure)[:60] + ")")) if len(out["failed"]) < 12 else None)
        pg.on("response", lambda s: out["failed"].append(s.url[:160] + " -> HTTP " + str(s.status)) if s.status >= 400 and s.request.resource_type in ("stylesheet", "script", "image", "font", "fetch", "xhr") and len(out["failed"]) < 12 else None)
        try:
            r = await pg.goto(url, wait_until="networkidle", timeout=25000)
            out["status"] = r.status if r else None
        except Exception as e:
            out["errors"].append("navigation: " + str(e)[:300])
        try:
            if await pg.locator('input[type=password]').count():
                secret_used = True
        except Exception:
            pass
        try:
            # scroll through the page so lazy images and scroll animations load, then back to the top
            await pg.evaluate("""async () => { const h = document.body ? document.body.scrollHeight : 0;
                for (let y = 0; y < h; y += 700) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); }
                window.scrollTo(0, 0); }""")
            await pg.wait_for_timeout(500)
        except Exception:
            pass
        fails = 0
        for a in acts:
            kind, sel, val = a[0], a[1], a[2]
            if kind == "mobile":
                continue
            if fails >= 3:
                out["actions"].append("SKIP " + action_label(a) + " (3 actions in a row failed)")
                continue
            try:
                if kind == "click":
                    await pg.click(sel, timeout=3000)
                elif kind == "fill":
                    m = re.fullmatch(r"\{\{([A-Z_][A-Z0-9_]*)\}\}", val)
                    info = await pg.locator(sel).evaluate("(e) => ({type:e.type||'',name:e.name||'',id:e.id||'',placeholder:e.placeholder||'',label:e.getAttribute('aria-label')||''})", timeout=3000)
                    hints = " ".join(str(info.get(k, "")) for k in ("type", "name", "id", "placeholder", "label"))
                    password_target = str(info.get("type", "")).lower() == "password" or bool(re.search(r"pass(?:word)?|secret|token|api.?key|auth", hints, re.I))
                    login_form = await pg.locator('input[type=password]').count() > 0
                    if login_form:
                        secret_used = True
                    if m:
                        env_name = m.group(1)
                        secret_used = True
                        if env_name not in os.environ: raise Exception("secure input missing: " + env_name)
                        val = os.environ[env_name]
                        secret_values_used.append(val)
                    elif password_target or login_form:
                        secret_used = True
                        raise Exception("use a secure need field and a {{ENV_NAME}} placeholder")
                    await pg.fill(sel, val, timeout=3000)
                elif kind == "select":
                    try:
                        await pg.select_option(sel, val, timeout=2000)
                    except Exception:
                        try:
                            await pg.select_option(sel, label=val, timeout=1500)
                        except Exception:
                            opt = await pg.eval_on_selector(sel, """(el, v) => { v = v.toLowerCase(); const o = [...el.options].find(o => o.value.toLowerCase().includes(v) || o.text.toLowerCase().includes(v)); return o ? o.value : null; }""", val)
                            if opt is None:
                                raise Exception("no option matches '" + val + "'")
                            await pg.select_option(sel, opt, timeout=1500)
                elif kind == "check":
                    await pg.check(sel, timeout=3000)
                elif kind == "press":
                    await pg.keyboard.press(sel or "Enter")
                elif kind == "wait":
                    await pg.wait_for_timeout(min(8000, int(sel or "1000")))
                elif kind == "goto":
                    await pg.goto(sel, wait_until="networkidle", timeout=20000)
                await pg.wait_for_timeout(350)
                out["actions"].append("OK   " + action_label(a))
                fails = 0
            except Exception as e:
                fails += 1
                msg = str(e).splitlines()[0][:120]
                if "Timeout" in msg:
                    msg = "element not found or not visible/enabled (hidden, covered, or the selector is wrong)"
                out["actions"].append("FAIL " + action_label(a) + " -> " + msg)
        if acts:
            try:
                await pg.wait_for_load_state("networkidle", timeout=5000)
            except Exception:
                pass
        if any(x.startswith("FAIL") for x in out["actions"]):
            try:
                out["fields"] = await pg.evaluate("""() => [...document.querySelectorAll('input:not([type=hidden]), select, textarea, button, a.btn, [role=button]')].slice(0, 40).map(e => {
                    let d = e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.name ? '[name=' + e.name + ']' : '') + (e.type && e.tagName !== 'BUTTON' ? ' type=' + e.type : '') + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\\s+/).slice(0, 2).join('.') : '');
                    if (e.tagName === 'SELECT') d += ' options: ' + [...e.options].slice(0, 8).map(o => o.value + (o.text && o.text !== o.value ? '="' + o.text.slice(0, 20) + '"' : '')).join(', ');
                    const t = (e.innerText || e.value || e.placeholder || '').trim().slice(0, 30); if (t && e.tagName !== 'SELECT') d += ' "' + t + '"';
                    if (!e.offsetParent && e.type !== 'checkbox' && e.type !== 'radio') d += ' (hidden)';
                    return d; })""")
            except Exception:
                pass
        if True:
            try:
                out["missingIds"] = await pg.evaluate("""async () => {
                    const srcs = [];
                    for (const s of document.scripts) {
                        if (s.src) { try { const u = new URL(s.src, location.href); if (u.origin !== location.origin) continue; const r = await fetch(u.href); srcs.push([u.pathname.split('/').pop() || 'script', await r.text()]); } catch (e) {} }
                        else if (s.textContent.trim()) srcs.push(['inline <script>', s.textContent]);
                    }
                    const miss = [], seen = {}, fixes = [];
                    const re = /getElementById\(\s*['"`]([\w-]+)['"`]\s*\)|querySelector(?:All)?\(\s*['"`]#([\w-]+)['"`]\s*\)/g;
                    for (const [name, txt] of srcs) {
                        const hs = new Set(); let hm;
                        const hr1 = /(?:const|let|var)\s+([\w$]+)\s*=\s*(?:function\s*)?\(?\s*\w+\s*\)?\s*(?:=>)?\s*\{?\s*(?:return\s+)?document\.(?:getElementById|querySelector)\(/g;
                        const hr2 = /function\s+([\w$]+)\s*\(\s*\w+\s*\)\s*\{\s*return\s+document\.(?:getElementById|querySelector)\(/g;
                        while ((hm = hr1.exec(txt))) hs.add(hm[1]); while ((hm = hr2.exec(txt))) hs.add(hm[1]);
                        const esc = x => x.replace(/[$]/g, '\\\\$&');
                        const hre = hs.size ? new RegExp('(?<![\\\\w$.])(?:' + [...hs].map(esc).join('|') + ')\\\\(\\\\s*[\\\\x27\\\\x22\\\\x60]#?([\\\\w-]+)[\\\\x27\\\\x22\\\\x60]\\\\s*\\\\)', 'g') : null;
                        txt.split('\\n').forEach((ln, i) => { const ids = []; re.lastIndex = 0; let m; while ((m = re.exec(ln))) ids.push(m[1] || m[2]); if (hre) { hre.lastIndex = 0; while ((m = hre.exec(ln))) ids.push(m[1]); } for (const id of ids) {
                            if (seen[id] || document.getElementById(id)) continue;
                            if (txt.includes('id="' + id + '"') || txt.includes("id='" + id + "'") || txt.includes('.id = "' + id) || txt.includes(".id = '" + id) || txt.includes('id=\\"' + id)) continue;
                            seen[id] = 1; const nm = x => x.replace(/[-_]/g, '').toLowerCase(); const all = [...document.querySelectorAll('[id]')].map(e => e.id); const words = x => (x.match(/[A-Z]?[a-z0-9]+|[A-Z]+(?![a-z])/g) || []).map(w => w.toLowerCase()); const same = (a, b) => { if (nm(a) === nm(b)) return true; const A = words(a), B = words(b); if (A.length !== B.length || A.length < 2) return false; return A.every((x, k) => x === B[k] || (x.length >= 2 && B[k].length >= 2 && (x.startsWith(B[k]) || B[k].startsWith(x)))); }; const cands = all.filter(x => same(id, x)); const sim = cands.length === 1 ? cands[0] : null; if (sim && !name.startsWith('inline')) fixes.push([id, sim, name]);
                            miss.push('#' + id + ' (' + name + ' line ' + (i + 1) + ')' + (sim ? ' = #' + sim + ' on this page' : '')); } });
                    }
                    return {miss: miss.slice(0, 20), fixes: fixes.slice(0, 12), ids: [...document.querySelectorAll('[id]')].map(e => '#' + e.id).slice(0, 40)};
                }""")
            except Exception:
                pass
        try:
            out["checks"] = await pg.evaluate("""() => {
                const bad = [...document.images].filter(i => i.complete && i.naturalWidth === 0 && (i.getAttribute('src') || '').trim() !== '' && (i.currentSrc || i.src) !== location.href).map(i => (i.currentSrc || i.src).slice(0, 160));
                const de = document.documentElement;
                const ids = {}; document.querySelectorAll('[id]').forEach(e => { ids[e.id] = (ids[e.id] || 0) + 1; });
                const dup = Object.keys(ids).filter(k => ids[k] > 1).map(k => '#' + k + ' x' + ids[k]).slice(0, 15);
                return { dupIds: dup, badImages: [...new Set(bad)].slice(0, 20), images: document.images.length, overflow: de.scrollWidth > window.innerWidth + 2, scrollWidth: de.scrollWidth, width: window.innerWidth, url: location.href };
            }""")
        except Exception:
            pass
        try:
            out["title"] = await pg.title()
            out["text"] = (await pg.inner_text("body"))[:5000]
        except Exception as e:
            out["text"] = ""
        try:
            out["style"] = await pg.evaluate("""() => { const b = getComputedStyle(document.body); let rules = 0;
                for (const s of document.styleSheets) { try { rules += s.cssRules.length; } catch (e) { rules += 1; } }
                return { sheets: document.styleSheets.length, rules: rules, bg: b.backgroundColor, color: b.color, font: b.fontFamily.slice(0, 60) }; }""")
        except Exception:
            pass
        if secret_used:
            out["screenshot_suppressed"] = "Secure fields were filled; screenshot omitted for privacy."
        else:
            os.makedirs(os.path.dirname(shot), exist_ok=True)
            try:
                await pg.screenshot(path=shot)
                out["screenshot"] = shot
            except Exception:
                pass
        await b.close()
    print(json.dumps(scrub_urls(out, secret_values_used)))
asyncio.run(main(sys.argv[1], sys.argv[2], json.load(open(sys.argv[3])) if len(sys.argv) > 3 else []))
PY;
            $cmd = "python3 -c 'import playwright' 2>/dev/null || pip install -q playwright >/dev/null 2>&1; mkdir -p .devil && cat > .devil/browse.py <<'DEVILPY'\n" . $py . "\nDEVILPY\ncat > .devil/acts.json <<'DEVILACT'\n" . json_encode($acts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\nDEVILACT\npython3 .devil/browse.py " . escapeshellarg($url) . ' ' . escapeshellarg($shot) . ' .devil/acts.json 2>&1 | tail -c 14000';
            $cmd = sbx_env_prefix($cfg, $sid) . "\n" . $cmd;
            if (sbx_is_cloud($cfg) && sbx_backend($cfg, $sid) === 'e2b') {
                /* small-RAM VM: Chromium needs memory overcommit; stock image (no custom template) gets Playwright on first use */
                $ready = 'python3 -c "import playwright" 2>/dev/null && ls -d ${PLAYWRIGHT_BROWSERS_PATH:-$HOME/.cache/ms-playwright}/chromium* >/dev/null 2>&1';
                $cmd = 'sudo -n sysctl -qw vm.overcommit_memory=1 2>/dev/null; mkdir -p /home/user/.bg; if ! { ' . $ready . '; }; then (nohup setsid bash -c ' . escapeshellarg('flock -w 120 /tmp/devil-pw.lock bash -c "python3 -c \"import playwright\" 2>/dev/null || pip install -q playwright; python3 -m playwright install --with-deps --only-shell chromium"') . ' >/dev/null 2>&1 < /dev/null &); '
                    . 'for i in $(seq 1 50); do sleep 1; ' . $ready . ' && break; done; fi; '
                    . 'if ! { ' . $ready . '; }; then echo \'{"errors": ["Chromium is still being installed in the sandbox (first use). Wait ~30 seconds and run the browser tool again."]}\'; exit 0; fi; ' . $cmd;
            } elseif (sbx_is_cloud($cfg) && sbx_backend($cfg, $sid) === 'csb') {
                /* Ubuntu 20.04 image: newest Playwright has no Chromium build for it — stay on 1.49 */
                $ready = 'python3 -c "import playwright" 2>/dev/null && ls -d ~/.cache/ms-playwright/chromium* >/dev/null 2>&1';
                $cmd = 'mkdir -p /home/user/.bg; if ! { ' . $ready . '; }; then (nohup setsid bash -c ' . escapeshellarg('flock -w 120 /tmp/devil-pw.lock bash -c "pip install -q playwright==1.49.1 && python3 -m playwright install --with-deps chromium"') . ' >/dev/null 2>&1 < /dev/null &); '
                    . 'for i in $(seq 1 50); do sleep 1; ' . $ready . ' && break; done; fi; '
                    . 'if ! { ' . $ready . '; }; then echo \'{"errors": ["Chromium is still being installed in the sandbox (first use). Wait ~30 seconds and run the browser tool again."]}\'; exit 0; fi; ' . str_replace('pip install -q playwright', 'pip install -q playwright==1.49.1', $cmd);
            } elseif (sbx_is_cloud($cfg) && sbx_backend($cfg, $sid) === 'vercel') {
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
            $idFix = '';
            if (isset($j['missingIds']['miss'])) {
                $fx = (array)($j['missingIds']['fixes'] ?? []);
                $byFile = [];
                foreach ($fx as $f) { if (is_array($f) && count($f) === 3 && preg_match('/^[\w-]+$/', (string)$f[0]) && preg_match('/^[\w-]+$/', (string)$f[1])) { $byFile[(string)$f[2]][] = 's/(?<=[#"\x27\x60])' . $f[0] . '(?=["\x27\x60])/' . $f[1] . '/g'; } }
                foreach ($byFile as $fn => $subs) { $idFix .= "\nQUICK FIX — run exactly this bash command (in the folder that has " . $fn . "; find it with: find . -name " . escapeshellarg($fn) . " -not -path '*/node_modules/*'): perl -pi -e '" . implode('; ', $subs) . "' " . escapeshellarg($fn); }
                $pageIds = implode(', ', array_map('strval', (array)($j['missingIds']['ids'] ?? [])));
                if ($pageIds !== '') { $idFix = ' Ids on this page: ' . $pageIds . ' (pick the one with the SAME meaning; if the element really is missing, add it to the HTML).' . $idFix; }
                $j['missingIds'] = (array)$j['missingIds']['miss'];
            }
            $nullErr = false; foreach ((array)($j['errors'] ?? []) as $e0) { if (stripos((string)$e0, 'null') !== false) { $nullErr = true; } }
            if (!empty($j['missingIds']) && !$nullErr) { $t .= '⚠ The JavaScript looks for ids that are NOT on this page: ' . implode(', ', (array)$j['missingIds']) . " — getElementById returns null, so that feature silently does nothing (buttons/forms/lists that never react). Make them match: keep the HTML, change only the names in the JS (sed -i), do NOT rewrite both files (unless that code is meant for a different page)." . $idFix . "\n"; }
            if (!empty($j['missingIds']) && $nullErr) { $j['errors'] = (array)($j['errors'] ?? []); array_unshift($j['errors'], 'LIKELY CAUSE of the "null" error: the JavaScript looks for these ids, but NO element on this page has them: ' . implode(', ', (array)$j['missingIds']) . ' — make them match: keep the HTML and change only the names in the JS (bash: sed -i "s/\'old-id\'/\'newId\'/g" path/script.js). Do NOT rewrite both files — that invents new mismatches. (If the script is shared by several pages, wrap that code in if (element) { … }.)' . $idFix); }
            if (!empty($j['errors'])) { $t .= "Page errors:\n- " . implode("\n- ", array_slice((array)$j['errors'], 0, 8)) . "\n(These are REAL JavaScript errors in this page — the code after them does not run. They are never a false alarm: fix them. Note: top-level const/let in separate classic <script> tags share ONE global scope, so the same name declared twice breaks the page.)\n"; }
            if (!empty($j['console'])) { $t .= "Console:\n- " . implode("\n- ", array_slice((array)$j['console'], 0, 10)) . "\n"; }
            if (!empty($j['failed'])) { $t .= "Failed to load (fix these paths / files):\n- " . implode("\n- ", array_slice(array_unique((array)$j['failed']), 0, 10)) . "\n"; }
            if (!empty($j['style']) && is_array($j['style'])) {
                $st = $j['style'];
                $plain = (int)($st['rules'] ?? 0) < 5 || (preg_match('/^"?(times|serif)/i', (string)($st['font'] ?? '')) && preg_match('/rgba\(0, 0, 0, 0\)|rgb\(255, 255, 255\)/', (string)($st['bg'] ?? '')));
                $t .= 'Styling: ' . (int)($st['rules'] ?? 0) . ' CSS rules in ' . (int)($st['sheets'] ?? 0) . ' stylesheet(s); body background ' . ($st['bg'] ?? '?') . ', text ' . ($st['color'] ?? '?') . ', font ' . ($st['font'] ?? '?') . "\n";
                if ($plain) { $t .= "⚠ The page looks UNSTYLED (default black-on-white HTML). The CSS is missing, not linked, or failed to load — check the <link href> paths against the real file names and the failed list above, then fix it before telling the user it is styled.\n"; }
            }
            if (!empty($j['actions'])) { $t .= "Actions:\n- " . implode("\n- ", array_slice((array)$j['actions'], 0, 20)) . "\n"; }
            if (!empty($j['fields'])) { $t .= "Form fields / buttons on this page (use these selectors):\n- " . implode("\n- ", array_slice((array)$j['fields'], 0, 40)) . "\n"; }
            if (!empty($j['dialogs'])) { $t .= "Dialogs shown (auto-accepted):\n- " . implode("\n- ", array_slice((array)$j['dialogs'], 0, 6)) . "\n"; }
            $ck = is_array($j['checks'] ?? null) ? $j['checks'] : [];
            if ($acts && !empty($ck['url']) && $ck['url'] !== $url) { $t .= 'Now at: ' . $ck['url'] . "\n"; }
            if (!empty($ck['badImages'])) { $t .= '⚠ BROKEN IMAGES (' . count((array)$ck['badImages']) . ' of ' . (int)($ck['images'] ?? 0) . " did not load) — fix the paths or create these files:\n- " . implode("\n- ", (array)$ck['badImages']) . "\n"; }
            if (!empty($ck['dupIds'])) { $t .= '⚠ DUPLICATE IDs: ' . implode(', ', (array)$ck['dupIds']) . " — the same id is on several elements, so the page probably has a repeated section/form (often from write_file + append_file adding the same part twice). JavaScript only finds the FIRST one. Remove the duplicate part (rewrite the file cleanly).\n"; }
            if (!empty($ck['overflow'])) { $t .= '⚠ HORIZONTAL OVERFLOW: the page is ' . (int)$ck['scrollWidth'] . 'px wide in a ' . (int)$ck['width'] . "px window (sideways scrolling" . (in_array('mobile', array_column($acts, 0), true) ? ' on mobile' : '') . "). Find the too-wide element (fixed widths, big images, long words, grids without wrap) and fix it.\n"; }
            $t .= ($acts ? "Visible text (after the actions):\n" : "Visible text:\n") . mb_substr((string)($j['text'] ?? ''), 0, 4000);
            if (!empty($j['screenshot'])) { $t .= "\n\nScreenshot saved: " . $j['screenshot']; }
            $failedActs = count(array_filter((array)($j['actions'] ?? []), static function ($x) { return strncmp((string)$x, 'FAIL', 4) === 0; }));
            if ($failedActs > 0) { $t .= "\n\nNOTE: a FAILED action is almost never a 'timing issue of the test tool' — the tool already waits. Either the selector is wrong (use the exact selectors in the 'Form fields / buttons' list), or the element is (hidden) because the thing that should show it (menu, modal, lightbox) did NOT open — that is a REAL bug, usually caused by the Page errors above. Fix the cause, then test again."; }
            return ['ok' => empty($j['errors']) && $failedActs === 0, 'text' => $t, 'meta' => ['screenshot' => (string)($j['screenshot'] ?? ''), 'url' => $url]];
        }
        case 'deploy_site': {
            if (empty($ctx['uid'])) { return ['ok' => false, 'text' => 'deploy_site is not available here.']; }
            return host_deploy_tool($cfg, $sid, (string)$ctx['uid'], $input);
        }
        case 'full_internet': {
            $m = sbx_move_to_vercel($cfg, $sid);
            if (empty($m['ok'])) { return ['ok' => false, 'text' => 'Could not switch to the full-internet sandbox: ' . (string)($m['error'] ?? '') . '. Continue on the current sandbox (use web_search / read_url for the web).']; }
            if (!empty($m['already'])) { return ['ok' => true, 'text' => 'This chat already has full internet access.']; }
            return ['ok' => true, 'text' => 'Switched: this chat now has FULL internet access (' . (($m['to'] ?? '') === 'e2b' ? 'Node 22, Python 3, Chromium; files and servers survive idle pauses' : (($m['to'] ?? '') === 'csb' ? 'Node 20, Python 3.10, Chromium, 2 CPU / 4 GB RAM; files and servers survive idle pauses' : 'bigger machine: 2 CPU, 4 GB RAM, Python 3.14, Node 24')) . '). '
                . ($m['copied'] ? 'Your files in /home/user/work were copied (' . (int)$m['bytes'] . ' bytes zipped). Reinstall dependencies (npm install / pip install) before running. ' : 'There were no files to copy. ')
                . 'Restart any servers with start_server (for Vite keep server.allowedHosts: true).', 'meta' => ['internet' => true]];
        }
        case 'generate_image': {
            /* batch mode: one "path | prompt" per line → all made at the same time */
            $batch = [];
            foreach (preg_split('/\r?\n/', trim($input)) as $bl) {
                if (preg_match('/^\s*[-*]?\s*`?([^\s|`]+\.(?:png|jpe?g|webp))`?\s*(?:\||::|=>)\s*(.+)$/i', $bl, $bm)) { $batch[sbx_rel($bm[1])] = trim($bm[2]); }
            }
            if (count($batch) >= 2 && sbx_is_cloud($cfg)) {
                $batch = array_slice($batch, 0, 8, true);
                $res = sbx_fetch_images($batch);
                $done = []; $fail = []; $big = [];
                foreach ($res as $bp => $img) {
                    if (empty($img['ok'])) { $fail[] = $bp . ' (' . (string)($img['error'] ?? 'failed') . ')'; continue; }
                    $data = sbx_compress_image((string)$img['data'], $bp);
                    $w = sbx_write($cfg, $sid, $bp, $data);
                    if (empty($w['ok'])) { $fail[] = $bp . ' (could not save)'; continue; }
                    $done[$bp] = (int)round(strlen($data) / 1024);
                    if ($done[$bp] > 200 && preg_match('/\.(png|jpe?g)$/i', $bp)) { $big[] = $bp; }
                }
                if ($big && $done) {
                    /* shrink the big ones inside the sandbox in ONE command */
                    $sh = 'command -v convert >/dev/null 2>&1 || exit 0; for f in ' . implode(' ', array_map('escapeshellarg', $big)) . "; do convert \"\$f\" -resize '1600x1600>' -strip -interlace Plane -quality 80 \"\$f.tmp.jpg\" && [ \$(stat -c %s \"\$f.tmp.jpg\") -lt \$(stat -c %s \"\$f\") ] && mv -f \"\$f.tmp.jpg\" \"\$f\"; rm -f \"\$f.tmp.jpg\"; echo \"S \$f \$(stat -c %s \"\$f\")\"; done";
                    $cr = sbx_exec($cfg, $sid, $sh, 40);
                    if (preg_match_all('/^S (\S+) (\d+)$/m', (string)($cr['stdout'] ?? ''), $cm, PREG_SET_ORDER)) { foreach ($cm as $x) { if (isset($done[$x[1]])) { $done[$x[1]] = (int)round((int)$x[2] / 1024); } } }
                }
                $t = $done ? 'Saved ' . count($done) . ' image(s) (optimised for fast loading): ' . implode(', ', array_map(static function ($p, $kb) { return $p . ' ' . $kb . ' KB'; }, array_keys($done), $done)) . '.' : '';
                if ($fail) { $t .= ($t !== '' ? "\n" : '') . '⚠ NOT made: ' . implode('; ', $fail) . ' — call generate_image again for these (or remove them from the page).'; }
                return ['ok' => $done !== [], 'text' => $t, 'meta' => ['image' => (string)array_key_first($done ?: ['' => 0])]];
            }
            $lines = preg_split('/\r?\n/', trim($input), 2);
            $path = sbx_rel((string)($lines[0] ?? ''));
            $prompt = trim((string)($lines[1] ?? ''));
            if ($prompt === '' && !preg_match('/\.(png|jpe?g|webp)$/i', $path)) { $prompt = trim($input); $path = 'images/image-' . date('His') . '.png'; }
            if (!preg_match('/\.(png|jpe?g|webp)$/i', $path)) { $path = rtrim($path === '.' ? 'images' : $path, '/') . '/image-' . date('His') . '.png'; }
            if ($prompt === '') { return ['ok' => false, 'text' => 'generate_image: second line must be the prompt.']; }
            if (sbx_is_cloud($cfg)) {
                $img = sbx_fetch_image($prompt);
                if (empty($img['ok'])) { return ['ok' => false, 'text' => 'Image generation failed: ' . (string)($img['error'] ?? '')]; }
                /* the file keeps the exact name the agent asked for (browsers detect JPEG/PNG by content), so links never break */
                $asked = $path;
                $img['data'] = sbx_compress_image((string)$img['data'], $path);
                $path = $asked;
                $w = sbx_write($cfg, $sid, $path, $img['data']);
                if (empty($w['ok'])) { return ['ok' => false, 'text' => 'Could not save the image: ' . (string)($w['error'] ?? '')]; }
                $kb = (int)round(strlen($img['data']) / 1024);
                if ($kb > 200 && preg_match('/\.(png|jpe?g)$/i', $path)) {
                    /* PHP could not shrink it → do it inside the sandbox (ImageMagick, else Pillow) */
                    $out = $path;
                    $A = escapeshellarg($path); $B = escapeshellarg($out);
                    $sh = 'if command -v convert >/dev/null 2>&1; then convert ' . $A . " -resize '1600x1600>' -strip -interlace Plane -quality 80 " . $B . '.tmp.jpg; '
                        . 'elif python3 -c "import PIL" 2>/dev/null; then python3 -c ' . escapeshellarg('import sys;from PIL import Image;i=Image.open(sys.argv[1]).convert("RGB");i.thumbnail((1600,1600));i.save(sys.argv[2],"JPEG",quality=80,optimize=True,progressive=True)') . ' ' . $A . ' ' . $B . '.tmp.jpg; fi; '
                        . 'if [ -s ' . $B . '.tmp.jpg ] && [ $(stat -c %s ' . $B . '.tmp.jpg) -lt $(stat -c %s ' . $A . ') ]; then mv -f ' . $B . '.tmp.jpg ' . $B . ' && { [ ' . $A . ' = ' . $B . ' ] || rm -f ' . $A . '; }; echo "OK $(stat -c %s ' . $B . ')"; else rm -f ' . $B . '.tmp.jpg; echo SKIP; fi';
                    $cr = sbx_exec($cfg, $sid, $sh, 40);
                    if (preg_match('/OK (\d+)/', (string)($cr['stdout'] ?? ''), $cm)) { $path = $out; $kb = (int)round((int)$cm[1] / 1024); }
                }
                return ['ok' => true, 'text' => 'Image saved to ' . $path . ' (' . $kb . ' KB, optimised for fast loading).', 'meta' => ['image' => $path]];
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
    $opts = []; $needs = [];
    foreach ($lines as $l) {
        $l = trim(preg_replace('/^([-*•]|\d+[.)])\s*/u', '', $l));
        if (preg_match('/^(?:need|secret|require[sd]?)\s*:\s*([A-Za-z_][A-Za-z0-9_]{0,63})\s*(?:\|\s*(.*))?$/i', $l, $nm)) {
            if (count($needs) < 4) { $needs[] = ['name' => strtoupper($nm[1]), 'label' => mb_substr(trim((string)($nm[2] ?? '')) ?: strtoupper($nm[1]), 0, 80)]; }
            continue;
        }
        if ($l !== '' && count($opts) < 4) { $opts[] = mb_substr($l, 0, 160); }
    }
    if (strpos($q, '|') !== false && !$opts) {
        $parts = array_map('trim', explode('|', $q));
        $q = (string)array_shift($parts);
        $opts = array_slice(array_values(array_filter($parts, 'strlen')), 0, 4);
    }
    return [mb_substr($q, 0, 600), $opts, $needs];
}

/* ── secure values are encrypted in a private per-chat server file for output masking and kept in the sandbox environment file; never add them to chat history ── */
const SBX_SECRETS_FILE = '/home/user/.secrets/env';
function sbx_secret_store_path(string $sid): string { return dirname(__DIR__) . '/data/agent_secrets/' . hash('sha256', $sid) . '.json'; }
function sbx_secret_store_key(array $cfg): string {
    $master = trim((string)($cfg['sandbox_secret'] ?? ''));
    return $master !== '' ? hash_hmac('sha256', 'agent-secret-store-v1', $master, true) : '';
}
function sbx_secret_values(string $sid, array $cfg = []): array {
    $f = sbx_secret_store_path($sid);
    if (!is_file($f)) { return []; }
    $raw = (string)@file_get_contents($f);
    $j = json_decode($raw, true);
    if (!is_array($j)) { return []; }
    if (($j['v'] ?? null) === 1 && isset($j['iv'], $j['tag'], $j['data'])) {
        $key = sbx_secret_store_key($cfg);
        if ($key === '' || !function_exists('openssl_decrypt')) { return []; }
        $iv = base64_decode((string)$j['iv'], true); $tag = base64_decode((string)$j['tag'], true); $ct = base64_decode((string)$j['data'], true);
        if ($iv === false || $tag === false || $ct === false) { return []; }
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $sid);
        $values = $plain === false ? null : json_decode($plain, true);
        return is_array($values) ? $values : [];
    }
    /* read old per-chat files so existing sessions keep working; the next save migrates them to encrypted storage */
    return $j;
}
function sbx_secret_store_write(string $sid, array $cfg, array $values): bool {
    $key = sbx_secret_store_key($cfg);
    if ($key === '' || !function_exists('openssl_encrypt')) { return false; }
    try { $iv = random_bytes(12); } catch (\Throwable $e) { return false; }
    $tag = '';
    $plain = (string)json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $sid, 16);
    if ($cipher === false) { return false; }
    $data = json_encode(['v' => 1, 'iv' => base64_encode($iv), 'tag' => base64_encode($tag), 'data' => base64_encode($cipher)]);
    $path = sbx_secret_store_path($sid); $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) { return false; }
    @chmod($dir, 0700);
    if (!is_file($dir . '/.htaccess')) { @file_put_contents($dir . '/.htaccess', "Require all denied\n"); @chmod($dir . '/.htaccess', 0600); }
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, (string)$data, LOCK_EX) === false) { return false; }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    @chmod($path, 0600);
    return true;
}
function sbx_secret_set(array $cfg, string $sid, string $name, string $value): array {
    $name = strtoupper(trim($name));
    if (!preg_match('/^[A-Z_][A-Z0-9_]{0,63}$/', $name)) { return ['ok' => false, 'error' => 'Invalid name.']; }
    if ($value === '' || strlen($value) > 8000 || strpos($value, "\n") !== false || strpos($value, "\0") !== false) { return ['ok' => false, 'error' => 'Invalid value (one line, up to 8000 characters).']; }
    if (sbx_secret_store_key($cfg) === '') { return ['ok' => false, 'error' => 'Secure secret storage is not configured.']; }
    $line = 'export ' . $name . '=' . "'" . str_replace("'", "'\\''", $value) . "'\n";
    $F = SBX_SECRETS_FILE;
    $cmd = 'umask 077; mkdir -p ' . dirname($F) . '; touch ' . $F . '; chmod 600 ' . $F . '; '
        . 'grep -v "^export ' . $name . '=" ' . $F . ' > ' . $F . '.t 2>/dev/null; echo ' . base64_encode($line) . ' | base64 -d >> ' . $F . '.t; mv ' . $F . '.t ' . $F . '; echo ok';
    $r = sbx_exec($cfg, $sid, $cmd, 40);
    if (empty($r['ok']) || strpos((string)($r['stdout'] ?? ''), 'ok') === false) { return ['ok' => false, 'error' => sbx_scrub((string)($r['error'] ?? ($r['stderr'] ?? 'could not save the secret')), $cfg)]; }
    $vals = sbx_secret_values($sid, $cfg); $vals[$name] = $value;
    if (!sbx_secret_store_write($sid, $cfg, $vals)) { return ['ok' => false, 'error' => 'The sandbox received the value, but private masking storage failed. Try again; do not repeat the value in chat.']; }
    return ['ok' => true, 'name' => $name];
}
function sbx_secret_mask($v, array $vals, int $minLen = 6) {
    if (!$vals) { return $v; }
    if (is_string($v)) {
        foreach ($vals as $sv) { $sv = (string)$sv; if (strlen($sv) >= max(1, $minLen)) { $v = str_replace($sv, '••••••', $v); } }
        return $v;
    }
    if (is_array($v)) { foreach ($v as $k => $x) { $v[$k] = sbx_secret_mask($x, $vals, $minLen); } }
    return $v;
}

/* ═════════════ File viewer: /sbx-view/{token}/{path} ═════════════
   Serves files from a chat's sandbox with their real folder layout, so an HTML file's own CSS / JS /
   images load (the old viewer showed one file alone → pages looked unstyled). The token is signed and
   short-lived (no cookies needed — the viewer iframe has an opaque origin). Every response except PDF
   carries a CSP sandbox, so agent-made pages never run with Devil AI's origin. */
function sbx_view_key(array $cfg): string {
    $k = (string)($cfg['sandbox_secret'] ?? '');
    if ($k === '') { $k = (string)($cfg['daytona_api_key'] ?? '') . '|' . (string)($cfg['vercel_token'] ?? ''); }
    return hash('sha256', 'sbx-view|' . $k);
}
function sbx_view_token(array $cfg, string $sid, int $ttl = 21600): string {
    $p = $sid . '.' . base_convert((string)(time() + $ttl), 10, 36);
    return $p . '.' . substr(hash_hmac('sha256', $p, sbx_view_key($cfg)), 0, 22);
}
function sbx_view_check(array $cfg, string $tok): string {
    if (!preg_match('/^(s[a-f0-9]{31})\.([a-z0-9]{4,10})\.([a-f0-9]{22})$/', $tok, $m)) { return ''; }
    if (!hash_equals(substr(hash_hmac('sha256', $m[1] . '.' . $m[2], sbx_view_key($cfg)), 0, 22), $m[3])) { return ''; }
    if ((int)base_convert($m[2], 36, 10) < time()) { return ''; }
    return $m[1];
}
function sbx_view_mime(string $path): string {
    static $m = ['html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8', 'json' => 'application/json; charset=utf-8', 'map' => 'application/json', 'wasm' => 'application/wasm',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif',
        'bmp' => 'image/bmp', 'ico' => 'image/x-icon', 'pdf' => 'application/pdf', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
        'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'flac' => 'audio/flac', 'opus' => 'audio/ogg', 'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg', 'mov' => 'video/quicktime',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'xml' => 'application/xml; charset=utf-8', 'webmanifest' => 'application/manifest+json',
        'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/plain; charset=utf-8', 'md' => 'text/plain; charset=utf-8'];
    $e = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return $m[$e] ?? 'text/plain; charset=utf-8';
}
/** Word / Excel / PowerPoint → a simple readable HTML page (no ZipArchive on the server: PharData reads zips) */
function sbx_office_html(string $data, string $ext, string $title): string {
    $tmp = sys_get_temp_dir() . '/devil_office_' . bin2hex(random_bytes(6)) . '.zip';
    file_put_contents($tmp, $data);
    $read = static function (string $inner) use ($tmp): string {
        $f = 'phar://' . $tmp . '/' . $inner;
        return is_file($f) ? (string)@file_get_contents($f) : '';
    };
    $esc = static function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
    $body = '';
    try {
        new PharData($tmp);
        if ($ext === 'docx') {
            $x = $read('word/document.xml');
            preg_match_all('#<w:p[ >].*?</w:p>#s', $x, $ps);
            foreach ($ps[0] as $p) {
                preg_match_all('#<w:t[^>]*>(.*?)</w:t>#s', $p, $ts);
                $t = html_entity_decode(implode('', $ts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                $hd = preg_match('#<w:pStyle w:val="(Heading|Title)(\d?)"#', $p, $hm);
                if (trim($t) === '') { $body .= '<div style="height:.6em"></div>'; continue; }
                $body .= $hd ? '<h' . max(1, min(4, (int)($hm[2] ?: 1))) . '>' . $esc($t) . '</h' . max(1, min(4, (int)($hm[2] ?: 1))) . '>' : '<p>' . $esc($t) . '</p>';
            }
        } elseif ($ext === 'xlsx') {
            $ss = [];
            if (preg_match_all('#<si>(.*?)</si>#s', $read('xl/sharedStrings.xml'), $sm)) {
                foreach ($sm[1] as $si) { preg_match_all('#<t[^>]*>(.*?)</t>#s', $si, $tt); $ss[] = html_entity_decode(implode('', $tt[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'); }
            }
            for ($n = 1; $n <= 3; $n++) {
                $x = $read('xl/worksheets/sheet' . $n . '.xml');
                if ($x === '') { break; }
                $body .= '<h3>Sheet ' . $n . '</h3><div class="tw"><table>';
                preg_match_all('#<row[^>]*>(.*?)</row>#s', $x, $rows);
                foreach (array_slice($rows[1], 0, 300) as $ri => $row) {
                    preg_match_all('#<c r="([A-Z]+)\d+"([^>]*?)(?:/>|>(.*?)</c>)#s', $row, $cs, PREG_SET_ORDER);
                    $cells = [];
                    foreach ($cs as $c) {
                        $col = 0; foreach (str_split($c[1]) as $ch) { $col = $col * 26 + (ord($ch) - 64); }
                        if ($col > 40) { continue; }
                        $v = preg_match('#<v>(.*?)</v>#s', (string)($c[3] ?? ''), $vm) ? $vm[1] : (preg_match('#<t[^>]*>(.*?)</t>#s', (string)($c[3] ?? ''), $im) ? $im[1] : '');
                        if (strpos($c[2], 't="s"') !== false) { $v = $ss[(int)$v] ?? ''; } else { $v = html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
                        $cells[$col] = $v;
                    }
                    $max = $cells ? max(array_keys($cells)) : 0;
                    $tag = $ri === 0 ? 'th' : 'td';
                    $body .= '<tr>'; for ($i = 1; $i <= $max; $i++) { $body .= "<{$tag}>" . $esc($cells[$i] ?? '') . "</{$tag}>"; } $body .= '</tr>';
                }
                $body .= '</table></div>';
            }
        } elseif ($ext === 'pptx') {
            for ($n = 1; $n <= 60; $n++) {
                $x = $read('ppt/slides/slide' . $n . '.xml');
                if ($x === '') { break; }
                preg_match_all('#<a:p>(.*?)</a:p>#s', $x, $ps);
                $body .= '<section><div class="sn">Slide ' . $n . '</div>';
                foreach ($ps[1] as $i => $p) {
                    preg_match_all('#<a:t>(.*?)</a:t>#s', $p, $ts);
                    $t = trim(html_entity_decode(implode('', $ts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    if ($t !== '') { $body .= $i === 0 ? '<h3>' . $esc($t) . '</h3>' : '<p>' . $esc($t) . '</p>'; }
                }
                $body .= '</section>';
            }
        }
    } catch (Throwable $e) {
        $body = '<p>Could not read this file (' . $esc($e->getMessage()) . ').</p>';
    }
    @unlink($tmp);
    if ($body === '') { $body = '<p>No readable text found in this file.</p>'; }
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $esc($title) . '</title><style>'
        . 'body{font:15px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:28px;background:#fff;color:#1f2328}h1,h2,h3,h4{margin:.8em 0 .3em}p{margin:.35em 0}'
        . '.tw{overflow:auto;border:1px solid #d0d7de;border-radius:8px;margin-bottom:18px}table{border-collapse:collapse;font-size:13px}th,td{border:1px solid #e5e7eb;padding:5px 9px;white-space:nowrap;text-align:left}th{background:#f6f8fa}'
        . 'section{border:1px solid #d0d7de;border-radius:10px;padding:14px 18px;margin-bottom:14px}.sn{font-size:11px;color:#57606a;text-transform:uppercase;letter-spacing:.05em}'
        . '@media(prefers-color-scheme:dark){body{background:#1e1e1e;color:#e6e6e6}th{background:#2a2a2a}th,td{border-color:#3a3a3a}.tw,section{border-color:#3a3a3a}}</style></head><body>' . $body . '</body></html>';
}
/** handle GET sbx-view/{token}/{path} (exits) */
function sbx_view_serve(array $cfg, string $tok, string $path): void {
    $sid = sbx_view_check($cfg, $tok);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: private, no-store');
    if ($sid === '') { http_response_code(403); header('Content-Type: text/plain'); echo 'This preview link expired — reopen the file.'; exit; }
    $path = str_replace(["\0", '\\'], ['', '/'], $path);
    $path = (string)preg_replace('#/+#', '/', ltrim($path, '/'));
    foreach (explode('/', $path) as $seg) { if ($seg === '..') { http_response_code(400); exit; } }
    if ($path === '' || substr($path, -1) === '/') { $path .= 'index.html'; }
    $as = (string)($_GET['as'] ?? '');
    $r = sbx_read($cfg, $sid, $path);
    if (empty($r['ok']) && pathinfo($path, PATHINFO_EXTENSION) === '') {
        $r2 = sbx_read($cfg, $sid, $path . '/index.html');
        if (!empty($r2['ok'])) { header('Location: ' . basename($path) . '/', true, 302); exit; }
    }
    if (empty($r['ok'])) {
        http_response_code((int)($r['status'] ?? 0) === 404 ? 404 : 502);
        header('Content-Type: text/plain; charset=utf-8');
        echo (int)($r['status'] ?? 0) === 404 ? 'Not found: ' . $path : 'The sandbox is waking up — reload in a few seconds.';
        exit;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $data = (string)$r['data'];
    $csp = "sandbox allow-scripts allow-forms allow-popups allow-modals allow-downloads allow-popups-to-escape-sandbox";
    if ($as === 'html' && in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'");
        echo sbx_office_html($data, $ext, basename($path));
        exit;
    }
    $ct = sbx_view_mime($path);
    if ($ext !== 'pdf') { header('Content-Security-Policy: ' . $csp); }
    if (strpos($ct, 'text/html') === 0) {
        /* root-relative links ("/style.css") → relative to this page's folder */
        $data = (string)preg_replace('#(\s(?:src|href|action|poster)\s*=\s*["\'])/(?!/)#i', '$1./', $data);
    }
    header('Content-Type: ' . $ct);
    header('Content-Length: ' . strlen($data));
    echo $data;
    exit;
}
