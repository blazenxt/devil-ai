<?php
/**
 * Devil AI — E2B provider for the Agent sandbox (inc/sandbox_e2b.php)
 *
 * Same contract as inc/sandbox_daytona.php, so every sbx_* function / agent tool works unchanged:
 *   exec  → {ok, exit_code, timed_out, stdout, stderr}   (background: {ok, background, pid, log, output})
 *   files → {ok, root, entries[], truncated}   read → {ok, data, type}   write → {ok, path, size}
 *   ports → {ok, ports:[{port,url,localhost_only}]}     zip → {ok, data}
 *
 * One sandbox per (user, chat), tagged with metadata devil_sid=<sid>. Sandboxes are created with
 * autoPause (when the timeout runs out the sandbox is PAUSED — files, memory and running servers are kept)
 * and autoResume (opening the preview link wakes it up by itself). Every request extends the timeout.
 * Paused sandboxes of chats that were not used for `e2b_keep_days` are removed to keep the account tidy.
 *
 * Control plane: REST https://api.e2b.app (X-API-KEY). Inside the sandbox: the envd daemon on port 49983 —
 * files over plain HTTP (/files), commands over Connect-RPC server streaming (process.Process/Start).
 * Config keys: e2b_api_key, e2b_template (default "devil-box", falls back to "base"), e2b_timeout_min (15),
 *              e2b_keep_days (7), e2b_api_url.
 */
declare(strict_types=1);

const E2B_WORK = '/home/user/work';
const E2B_ENVD_PORT = 49983;
const E2B_INTERNAL_PORTS = [22, 111, 49983];

function e2b_configured(array $cfg): bool { return trim((string)($cfg['e2b_api_key'] ?? '')) !== ''; }
function e2b_api(array $cfg): string { return rtrim((string)($cfg['e2b_api_url'] ?? '') ?: 'https://api.e2b.app', '/'); }
function e2b_timeout_s(array $cfg): int { return max(5, min(60, (int)($cfg['e2b_timeout_min'] ?? 15))) * 60; }

/** control-plane call → [status, json|null, body, error] */
function e2b_http(array $cfg, string $method, string $path, $body = null, int $timeout = 30): array {
    $ch = curl_init(e2b_api($cfg) . $path);
    $h = ['X-API-KEY: ' . trim((string)($cfg['e2b_api_key'] ?? '')), 'User-Agent: DevilAI-Sandbox/2.0'];
    $opt = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(5, $timeout), CURLOPT_CONNECTTIMEOUT => 10];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $opt[CURLOPT_POSTFIELDS] = is_string($body) ? $body : (string)json_encode($body, JSON_UNESCAPED_SLASHES); }
    $opt[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $opt);
    $out = curl_exec($ch);
    $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($out === false) { return [0, null, '', 'Sandbox unreachable: ' . $cerr]; }
    $j = json_decode((string)$out, true);
    $err = $st >= 400 ? (string)((is_array($j) ? ($j['message'] ?? '') : '') ?: ('Sandbox error (HTTP ' . $st . ')')) : '';
    return [$st, is_array($j) ? $j : null, (string)$out, $err];
}

/** never show the provider's own wording (plans, credits, host names) */
function e2b_friendly_error(string $err, string $fallback): string {
    if ($err === '') { return $fallback; }
    if (preg_match('/concurren|rate limit|too many|limit/i', $err)) { return 'All workspaces are busy right now. Please try again in a minute.'; }
    if (preg_match('/credit|billing|payment|tier|plan|team|suspend|blocked/i', $err)) { return 'The workspace service is temporarily unavailable. Please try again in a minute.'; }
    if (preg_match('/e2b|https?:\/\//i', $err) || strlen($err) > 200) { return $fallback; }
    return $err;
}

/* ── sid → {id, tok, dom} (data/sbx_e2b.json) ── */
function e2b_map_path(): string { return dirname(__DIR__) . '/data/sbx_e2b.json'; }
function e2b_map_get(string $key): ?array {
    $f = e2b_map_path();
    if (!is_file($f)) { return null; }
    $m = json_decode((string)@file_get_contents($f), true);
    return is_array($m) && isset($m[$key]) && is_array($m[$key]) ? $m[$key] : null;
}
function e2b_map_set(string $key, ?array $val): void {
    $f = e2b_map_path();
    @mkdir(dirname($f), 0755, true);
    $fh = @fopen($f, 'c+');
    if (!$fh) { return; }
    flock($fh, LOCK_EX);
    $m = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($m)) { $m = []; }
    if ($val === null) { unset($m[$key]); } else { $m[$key] = $val; }
    ftruncate($fh, 0); rewind($fh); fwrite($fh, (string)json_encode($m, JSON_UNESCAPED_SLASHES));
    flock($fh, LOCK_UN); fclose($fh);
}

/** remove paused sandboxes of this app that were not used for e2b_keep_days (runs at most every 6 h) */
function e2b_cleanup(array $cfg): void {
    $last = (int)(e2b_map_get('_cleanup')['t'] ?? 0);
    if ($last > time() - 21600) { return; }
    e2b_map_set('_cleanup', ['t' => time()]);
    $days = max(1, min(60, (int)($cfg['e2b_keep_days'] ?? 7)));
    [$st, $j] = e2b_http($cfg, 'GET', '/v2/sandboxes?' . http_build_query(['state' => 'paused', 'metadata' => 'app=devil-ai', 'limit' => 100]), null, 20);
    if ($st !== 200 || !is_array($j)) { return; }
    foreach ($j as $it) {
        $end = strtotime((string)($it['endAt'] ?? '')) ?: time();
        if (is_array($it) && $end < time() - $days * 86400 && !empty($it['sandboxID'])) {
            e2b_http($cfg, 'DELETE', '/sandboxes/' . rawurlencode((string)$it['sandboxID']), null, 15);
        }
    }
}

function e2b_create(array $cfg, string $sid): array {
    $tpl = trim((string)($cfg['e2b_template'] ?? '')) ?: 'devil-box';
    $body = [
        'templateID' => $tpl,
        'timeout' => e2b_timeout_s($cfg),
        'secure' => true,
        'autoPause' => true,
        'autoResume' => ['enabled' => true],
        'metadata' => ['app' => 'devil-ai', 'devil_sid' => $sid],
        /* the app inside sees "localhost:<port>" as Host — dev servers (Vite, CRA…) never block the preview */
        'network' => ['maskRequestHost' => 'localhost:${PORT}'],
    ];
    [$st, $j, , $err] = e2b_http($cfg, 'POST', '/sandboxes', $body, 60);
    if (($st === 400 || $st === 404) && $tpl !== 'base' && preg_match('/template/i', $err)) {
        $body['templateID'] = 'base';                      /* custom template missing → stock image */
        [$st, $j, , $err] = e2b_http($cfg, 'POST', '/sandboxes', $body, 60);
    }
    if ($st < 200 || $st >= 300 || empty($j['sandboxID'])) {
        return ['ok' => false, 'error' => e2b_friendly_error($err, 'could not create the sandbox'), 'down' => $st === 0 || $st === 401 || $st === 402 || $st === 403 || $st === 429 || $st >= 500];
    }
    $info = ['id' => (string)$j['sandboxID'], 'tok' => (string)($j['envdAccessToken'] ?? ''), 'dom' => (string)($j['domain'] ?? '') ?: 'e2b.app', 'tpl' => (string)$body['templateID']];
    e2b_map_set($sid, $info);
    return ['ok' => true, 'info' => $info, 'new' => true];
}

/**
 * Sandbox info for sid, running. $create=false only uses an existing one; $wake=false never resumes a paused one.
 * Returns {ok, info:{id,tok,dom}, new?} | {ok:false, none|asleep|down, error}
 */
function e2b_ensure(array $cfg, string $sid, bool $create = true, bool $wake = true): array {
    static $ready = [];
    if (isset($ready[$sid])) { return ['ok' => true, 'info' => $ready[$sid]]; }
    $info = e2b_map_get($sid);
    $new = false;
    if ($info !== null && !$wake) {
        [$st, $j] = e2b_http($cfg, 'GET', '/sandboxes/' . rawurlencode((string)$info['id']), null, 15);
        if ($st === 404) { e2b_map_set($sid, null); return ['ok' => false, 'none' => true, 'error' => 'no sandbox yet']; }
        if ($st !== 200 || (string)($j['state'] ?? '') !== 'running') { return ['ok' => false, 'asleep' => true, 'error' => 'sandbox is asleep']; }
        $ready[$sid] = $info;
        return ['ok' => true, 'info' => $info];
    }
    if ($info !== null) {
        /* resumes a paused sandbox and extends its timeout */
        [$st, $j, , $err] = e2b_http($cfg, 'POST', '/sandboxes/' . rawurlencode((string)$info['id']) . '/connect', ['timeout' => e2b_timeout_s($cfg)], 60);
        if ($st === 200 || $st === 201) {
            if (!empty($j['envdAccessToken']) && $j['envdAccessToken'] !== $info['tok']) { $info['tok'] = (string)$j['envdAccessToken']; e2b_map_set($sid, $info); }
            $ready[$sid] = $info;
            return ['ok' => true, 'info' => $info];
        }
        if ($st !== 404) { return ['ok' => false, 'error' => e2b_friendly_error($err, 'the sandbox did not start'), 'down' => $st === 0 || $st === 401 || $st === 402 || $st === 403 || $st >= 500]; }
        e2b_map_set($sid, null);                            /* sandbox was removed: start a fresh one */
        $info = null;
    }
    if (!$create) { return ['ok' => false, 'none' => true, 'error' => 'no sandbox yet']; }
    e2b_cleanup($cfg);
    $c = e2b_create($cfg, $sid);
    if (empty($c['ok'])) { return $c; }
    $info = $c['info']; $new = true;
    $init = 'sudo -n sysctl -qw vm.overcommit_memory=1 2>/dev/null; mkdir -p ' . E2B_WORK . ' /home/user/.bg; '
          . ($info['tpl'] === 'base' ? '(python3 -c "import playwright" 2>/dev/null || nohup pip install -q playwright >/dev/null 2>&1 &); ' : '') . 'true';
    e2b_rpc_exec($info, $init, 30, '/home/user');
    $ready[$sid] = $info;
    return ['ok' => true, 'info' => $info, 'new' => $new];
}

function e2b_envd(array $info): string { return 'https://' . E2B_ENVD_PORT . '-' . $info['id'] . '.' . ($info['dom'] ?: 'e2b.app'); }
function e2b_envd_headers(array $info): array {
    $h = ['Authorization: Basic ' . base64_encode('user:'), 'User-Agent: DevilAI-Sandbox/2.0'];
    if ((string)($info['tok'] ?? '') !== '') { $h[] = 'X-Access-Token: ' . $info['tok']; }
    return $h;
}

/**
 * Run a shell command through envd (Connect-RPC server stream). The whole stream is read until the process ends.
 * Returns {ok, exit_code, timed_out, stdout, stderr} | {ok:false, error, status}
 */
function e2b_rpc_exec(array $info, string $sh, int $timeout, string $cwd = E2B_WORK): array {
    $payload = (string)json_encode(['process' => ['cmd' => '/bin/bash', 'args' => ['-l', '-c', $sh], 'envs' => new stdClass(), 'cwd' => $cwd], 'stdin' => false], JSON_UNESCAPED_SLASHES);
    $frame = "\0" . pack('N', strlen($payload)) . $payload;
    $ch = curl_init(e2b_envd($info) . '/process.Process/Start');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $frame, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(5, $timeout + 15), CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => array_merge(e2b_envd_headers($info), ['Content-Type: application/connect+json', 'Connect-Protocol-Version: 1']),
    ]);
    $raw = curl_exec($ch);
    $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $eno = curl_errno($ch);
    curl_close($ch);
    if ($raw === false && $eno === 28) { return ['ok' => true, 'exit_code' => 124, 'timed_out' => true, 'stdout' => '', 'stderr' => 'timed out after ' . $timeout . 's']; }
    if ($raw === false) { return ['ok' => false, 'error' => 'Sandbox unreachable', 'status' => 0]; }
    if ($st !== 200) {
        $j = json_decode((string)$raw, true);
        return ['ok' => false, 'error' => (string)((is_array($j) ? ($j['message'] ?? '') : '') ?: 'exec failed (HTTP ' . $st . ')'), 'status' => $st];
    }
    $out = ''; $err = ''; $rc = null; $endErr = '';
    $n = strlen((string)$raw); $i = 0;
    while ($i + 5 <= $n) {
        $flags = ord($raw[$i]);
        $len = unpack('N', substr($raw, $i + 1, 4))[1];
        $j = json_decode(substr($raw, $i + 5, $len), true);
        $i += 5 + $len;
        if (!is_array($j)) { continue; }
        if ($flags & 2) { if (!empty($j['error']['message'])) { $endErr = (string)$j['error']['message']; } continue; }
        $ev = (array)($j['event'] ?? []);
        if (isset($ev['data'])) {
            if (isset($ev['data']['stdout'])) { $out .= (string)base64_decode((string)$ev['data']['stdout']); }
            if (isset($ev['data']['stderr'])) { $err .= (string)base64_decode((string)$ev['data']['stderr']); }
            if (isset($ev['data']['pty'])) { $out .= (string)base64_decode((string)$ev['data']['pty']); }
            if (strlen($out) > 400000) { $out = substr($out, 0, 20000) . "\n…[output cut]…\n" . substr($out, -150000); }
        } elseif (isset($ev['end'])) {
            $rc = (int)($ev['end']['exitCode'] ?? 0);
        }
    }
    if ($rc === null) {
        if ($endErr !== '') { return ['ok' => false, 'error' => $endErr, 'status' => 200]; }
        return ['ok' => true, 'exit_code' => 124, 'timed_out' => true, 'stdout' => $out, 'stderr' => $err . "\n(the command stream ended early)"];
    }
    return ['ok' => true, 'exit_code' => $rc, 'timed_out' => $rc === 124, 'stdout' => $out, 'stderr' => $err];
}

/** envd file HTTP call → [status, body, error] */
function e2b_files_http(array $info, string $method, string $abs, ?array $multipart = null, int $timeout = 60): array {
    $ch = curl_init(e2b_envd($info) . '/files?' . http_build_query(['path' => $abs, 'username' => 'user']));
    $opt = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => e2b_envd_headers($info)];
    if ($multipart !== null) { $opt[CURLOPT_POSTFIELDS] = $multipart; }
    curl_setopt_array($ch, $opt);
    $out = curl_exec($ch);
    $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($out === false) { return [0, '', 'Sandbox unreachable']; }
    $err = '';
    if ($st >= 400) { $j = json_decode((string)$out, true); $err = (string)((is_array($j) ? ($j['message'] ?? '') : '') ?: 'HTTP ' . $st); }
    return [$st, (string)$out, $err];
}

/** run with the sandbox for sid; on "sandbox gone / not running" (502/404 from envd) re-ensure once and retry */
function e2b_with(array $cfg, string $sid, callable $fn, bool $create = true) {
    $e = e2b_ensure($cfg, $sid, $create);
    if (empty($e['ok'])) { return ['__fail' => true, 'error' => (string)($e['error'] ?? 'sandbox unavailable'), 'none' => !empty($e['none'])]; }
    $r = $fn($e['info']);
    $st = is_array($r) ? (int)($r['status'] ?? ($r[0] ?? 200)) : 200;
    if ($st === 502 || $st === 0) {
        e2b_http($cfg, 'POST', '/sandboxes/' . rawurlencode((string)$e['info']['id']) . '/connect', ['timeout' => e2b_timeout_s($cfg)], 60);
        $r = $fn($e['info']);
    }
    return $r;
}

function e2b_abs(string $rel): string {
    $rel = ltrim(str_replace("\0", '', $rel), '/');
    return $rel === '' || $rel === '.' ? E2B_WORK : E2B_WORK . '/' . $rel;
}

/* ═════════════ provider contract ═════════════ */

function e2b_health(array $cfg): array { return ['ok' => e2b_configured($cfg), 'provider' => 'e2b']; }

function e2b_open(array $cfg, string $sid): array {
    $e = e2b_ensure($cfg, $sid, true);
    return !empty($e['ok']) ? ['ok' => true, 'sid' => $sid, 'workdir' => E2B_WORK] : ['ok' => false, 'error' => (string)($e['error'] ?? ''), 'down' => !empty($e['down'])];
}

function e2b_exec(array $cfg, string $sid, string $cmd, int $timeout = 80, bool $background = false, float $wait = 3.0, string $cwd = ''): array {
    $cwd = $cwd === '' ? E2B_WORK : ($cwd[0] === '/' ? $cwd : E2B_WORK . '/' . $cwd);
    $t0 = microtime(true);
    if ($background) {
        $log = '/home/user/.bg/' . bin2hex(random_bytes(4)) . '.log';
        $w = max(0.0, min($wait, 15.0));
        $sh = 'mkdir -p /home/user/.bg ' . escapeshellarg($cwd) . '; cd ' . escapeshellarg($cwd) . '; nohup setsid bash -lc ' . escapeshellarg($cmd) . ' > ' . $log . ' 2>&1 < /dev/null & echo "__PID=$!"; '
            . 'sleep ' . sprintf('%.1f', $w) . '; tail -c 6000 ' . $log . ' 2>/dev/null';
        $r = e2b_with($cfg, $sid, static function ($info) use ($sh, $w) { return e2b_rpc_exec($info, $sh, (int)ceil($w) + 20, '/home/user'); });
        if (!empty($r['__fail']) || empty($r['ok'])) { return ['ok' => false, 'error' => e2b_friendly_error((string)($r['error'] ?? ''), 'exec failed')]; }
        $res = (string)($r['stdout'] ?? '');
        $pid = preg_match('/__PID=(\d+)/', $res, $m) ? $m[1] : '';
        $out = trim((string)preg_replace('/^.*?__PID=\d+\r?\n?/s', '', $res));
        return ['ok' => (int)($r['exit_code'] ?? 0) === 0, 'background' => true, 'pid' => $pid, 'log' => $log, 'output' => $out, 'ms' => (int)((microtime(true) - $t0) * 1000)];
    }
    $t = max(1, min($timeout, 900));
    $sh = 'mkdir -p ' . escapeshellarg($cwd) . ' 2>/dev/null; cd ' . escapeshellarg($cwd) . ' && timeout -k 5 ' . $t . ' bash -lc ' . escapeshellarg($cmd);
    $r = e2b_with($cfg, $sid, static function ($info) use ($sh, $t) { return e2b_rpc_exec($info, $sh, $t, '/home/user'); });
    if (!empty($r['__fail']) || empty($r['ok'])) { return ['ok' => false, 'error' => e2b_friendly_error((string)($r['error'] ?? ''), 'exec failed')]; }
    $r['ms'] = (int)((microtime(true) - $t0) * 1000);
    unset($r['status']);
    return $r;
}

function e2b_files(array $cfg, string $sid, string $path = '.', int $depth = 4): array {
    return sbx_files_via(static function (string $sh, int $t) use ($cfg, $sid) { return e2b_exec($cfg, $sid, $sh, $t); }, E2B_WORK, e2b_abs($path), $depth);
}

function e2b_read(array $cfg, string $sid, string $path): array {
    if ($path === '' || $path === '.') { return ['ok' => false, 'error' => 'path required', 'status' => 400]; }
    $abs = $path[0] === '/' && strpos($path, '/tmp/') === 0 ? $path : e2b_abs($path);
    $r = e2b_with($cfg, $sid, static function ($info) use ($abs) { return e2b_files_http($info, 'GET', $abs, null, 60); }, false);
    if (!empty($r['__fail'])) { return ['ok' => false, 'error' => !empty($r['none']) ? 'file not found' : e2b_friendly_error((string)$r['error'], 'read failed'), 'status' => !empty($r['none']) ? 404 : 502]; }
    [$st, $body, $err] = $r;
    if ($st === 404) { return ['ok' => false, 'error' => 'file not found', 'status' => 404]; }
    if ($st !== 200) { return ['ok' => false, 'error' => e2b_friendly_error($err, 'read failed'), 'status' => $st]; }
    if (strlen($body) > 25 * 1024 * 1024) { return ['ok' => false, 'error' => 'file is larger than 25 MB', 'status' => 413]; }
    return ['ok' => true, 'data' => $body, 'type' => dyt_mime($path)];
}

function e2b_write(array $cfg, string $sid, string $path, string $data): array {
    if ($path === '' || $path === '.') { return ['ok' => false, 'error' => 'path required']; }
    if (strlen($data) > 25 * 1024 * 1024) { return ['ok' => false, 'error' => 'file is larger than 25 MB']; }
    $abs = e2b_abs($path);
    $tmp = null;
    if (class_exists('CURLStringFile')) { $file = new CURLStringFile($data, basename($path), 'application/octet-stream'); }
    else { $tmp = tempnam(sys_get_temp_dir(), 'e2b'); file_put_contents($tmp, $data); $file = new CURLFile($tmp, 'application/octet-stream', basename($path)); }
    $r = e2b_with($cfg, $sid, static function ($info) use ($abs, $file) { return e2b_files_http($info, 'POST', $abs, ['file' => $file], 120); });
    if ($tmp !== null) { @unlink($tmp); }
    if (!empty($r['__fail'])) { return ['ok' => false, 'error' => e2b_friendly_error((string)$r['error'], 'write failed')]; }
    [$st, , $err] = $r;
    if ($st < 200 || $st >= 300) { return ['ok' => false, 'error' => e2b_friendly_error($err, 'write failed')]; }
    return ['ok' => true, 'path' => ltrim($path, '/'), 'size' => strlen($data)];
}

function e2b_delete(array $cfg, string $sid, string $path): array {
    $abs = e2b_abs($path);
    if ($abs === E2B_WORK || strpos($path, '..') !== false) { return ['ok' => false, 'error' => 'refusing to delete the workspace root']; }
    $r = e2b_exec($cfg, $sid, 'test -e ' . escapeshellarg($abs) . ' -o -L ' . escapeshellarg($abs) . ' || { echo __NF; exit 0; }; rm -rf -- ' . escapeshellarg($abs), 30);
    if (empty($r['ok'])) { return ['ok' => false, 'error' => (string)($r['error'] ?? 'delete failed')]; }
    if (strpos((string)($r['stdout'] ?? ''), '__NF') !== false) { return ['ok' => false, 'error' => 'not found']; }
    return ['ok' => true];
}

function e2b_preview_url(array $info, int $port): string { return 'https://' . $port . '-' . $info['id'] . '.' . ($info['dom'] ?: 'e2b.app'); }

function e2b_ports(array $cfg, string $sid): array {
    $e = e2b_ensure($cfg, $sid, false, false);    /* just looking: never create or wake a sandbox for this */
    if (empty($e['ok'])) { return ['ok' => true, 'ports' => []]; }
    $r = e2b_rpc_exec($e['info'], 'ss -ltnH', 15, '/home/user');
    if (empty($r['ok'])) { return ['ok' => false, 'error' => e2b_friendly_error((string)($r['error'] ?? ''), 'ports failed'), 'ports' => []]; }
    $ports = [];
    foreach (preg_split('/\r?\n/', (string)($r['stdout'] ?? '')) as $line) {
        $c = preg_split('/\s+/', trim($line));
        if (count($c) < 4) { continue; }
        $pos = strrpos($c[3], ':');
        if ($pos === false) { continue; }
        $addr = substr($c[3], 0, $pos); $p = (int)substr($c[3], $pos + 1);
        if ($p < 1024 || $p > 65535 || in_array($p, E2B_INTERNAL_PORTS, true)) { continue; }
        $lo = in_array($addr, ['127.0.0.1', '[::1]', '::1'], true);
        $ports[$p] = ($ports[$p] ?? true) && $lo;
    }
    ksort($ports);
    $list = [];
    foreach ($ports as $p => $lo) {
        $row = ['port' => $p, 'url' => e2b_preview_url($e['info'], $p), 'localhost_only' => $lo];
        if ($lo) { $row['hint'] = 'bound to 127.0.0.1 only; restart it with host 0.0.0.0 for the preview'; }
        $list[] = $row;
    }
    return ['ok' => true, 'ports' => $list];
}

function e2b_zip(array $cfg, string $sid): array {
    $ex = [];
    foreach (DYT_SKIP_DIRS as $d) { $ex[] = escapeshellarg('*/' . $d . '/*'); $ex[] = escapeshellarg($d . '/*'); }
    $tmp = '/tmp/devil-workspace-' . bin2hex(random_bytes(3)) . '.zip';
    $r = e2b_exec($cfg, $sid, 'cd ' . E2B_WORK . ' && rm -f ' . $tmp . ' && (zip -qr -y ' . $tmp . ' . -x ' . implode(' ', $ex) . ' || true) && test -f ' . $tmp . ' || python3 -c "import zipfile;zipfile.ZipFile(\'' . $tmp . '\',\'w\').close()"', 120);
    if (empty($r['ok'])) { return ['ok' => false, 'error' => (string)($r['error'] ?? 'zip failed')]; }
    $d = e2b_read($cfg, $sid, $tmp);
    e2b_exec($cfg, $sid, 'rm -f ' . $tmp, 10);
    return !empty($d['ok']) ? ['ok' => true, 'data' => $d['data']] : ['ok' => false, 'error' => (string)($d['error'] ?? 'zip download failed')];
}

/** unpack a zip (a workspace moved from another provider) into the working folder */
function e2b_unzip(array $cfg, string $sid, string $zip): array {
    $tmp = '.devil-import-' . bin2hex(random_bytes(3)) . '.zip';
    $w = e2b_write($cfg, $sid, $tmp, $zip);
    if (empty($w['ok'])) { return $w; }
    $r = e2b_exec($cfg, $sid, 'cd ' . E2B_WORK . ' && (unzip -oq ' . $tmp . ' || python3 -m zipfile -e ' . $tmp . ' .); rc=$?; rm -f ' . $tmp . '; exit $rc', 120);
    return !empty($r['ok']) && (int)($r['exit_code'] ?? 1) === 0 ? ['ok' => true] : ['ok' => false, 'error' => (string)($r['error'] ?? $r['stderr'] ?? 'unzip failed')];
}

/** delete the sandbox of a chat (chat deleted) */
function e2b_purge(array $cfg, string $sid): array {
    $info = e2b_map_get($sid);
    if ($info === null) { return ['ok' => true]; }
    [$st, , , $err] = e2b_http($cfg, 'DELETE', '/sandboxes/' . rawurlencode((string)$info['id']), null, 15);
    e2b_map_set($sid, null);
    return ($st >= 200 && $st < 300) || $st === 404 ? ['ok' => true] : ['ok' => false, 'error' => e2b_friendly_error($err, 'delete failed')];
}
