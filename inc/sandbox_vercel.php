<?php
/**
 * Devil AI — Vercel Sandbox provider for the Agent sandbox (inc/sandbox_vercel.php)
 *
 * Same contract as inc/sandbox_daytona.php (dyt_*), so inc/sandbox.php can switch between them:
 *   exec   → {ok, exit_code, timed_out, stdout, stderr}            (background: {ok, background, pid, log, output})
 *   files  → {ok, root, entries:[…], truncated}    read → {ok, data, type}    write → {ok, path, size}
 *   delete → {ok}    ports → {ok, ports:[{port,url,localhost_only}]}    zip → {ok, data}
 *
 * One persistent Vercel sandbox per (user, chat), named "devil-<sid>". A sandbox runs in "sessions":
 * a session stops after its timeout (Hobby: max 45 min), the file system is snapshotted automatically,
 * and the next call resumes it (~3 s) with the same files and the same preview URLs.
 * Preview URLs exist only for ports declared on the sandbox, so vcl_ports() adds new ports on demand.
 * Config keys: vercel_token, vercel_team_id, vercel_project_id, vercel_vcpus (2), vercel_timeout_min (15).
 */
declare(strict_types=1);

const VCL_API = 'https://api.vercel.com';
const VCL_WORK = '/home/user/work';
const VCL_PORTS = [3000, 5173, 8000, 8080, 4173, 5000];
const VCL_MAX_PORTS = 12;
const VCL_INTERNAL_PORTS = [23456];
/* headless Chromium for the browser tool: Playwright (user site) + browser + system libraries. Idempotent, serialised by a lock. */
const VCL_PW_SETUP = 'flock -w 90 /tmp/devil-pw.lock bash -c \'python3 -c "import playwright" 2>/dev/null || pip install -q playwright; '
    . 'ls -d ~/.cache/ms-playwright/chromium* >/dev/null 2>&1 || python3 -m playwright install chromium; '
    . 'test -f /home/user/.bg/.pwdeps || { sudo env PYTHONPATH="$(python3 -c "import site;print(site.getusersitepackages())")" python3 -m playwright install-deps chromium && touch /home/user/.bg/.pwdeps; }\' >/dev/null 2>&1';

function vcl_configured(array $cfg): bool {
    return trim((string)($cfg['vercel_token'] ?? '')) !== '' && trim((string)($cfg['vercel_team_id'] ?? '')) !== ''
        && trim((string)($cfg['vercel_project_id'] ?? '')) !== '';
}
function vcl_name(string $sid): string { return 'devil-' . preg_replace('/[^a-z0-9-]/', '', strtolower($sid)); }

/** raw HTTP call. $body: array → JSON, string → raw (with $ctype). Returns [status, json|null, body, error, code] */
function vcl_http(array $cfg, string $method, string $path, array $query = [], $body = null, int $timeout = 60, array $extra = []): array {
    $query['teamId'] = (string)($cfg['vercel_team_id'] ?? '');
    $url = VCL_API . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $headers = array_merge(['Authorization: Bearer ' . (string)($cfg['vercel_token'] ?? ''), 'User-Agent: DevilAI-Sandbox/2.0'], $extra);
    $ch = curl_init($url);
    $opt = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(5, $timeout), CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false];
    if (is_array($body)) { $headers[] = 'Content-Type: application/json'; $opt[CURLOPT_POSTFIELDS] = (string)json_encode($body, JSON_UNESCAPED_SLASHES); }
    elseif (is_string($body)) { $opt[CURLOPT_POSTFIELDS] = $body; }
    $opt[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opt);
    $out = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    $timedOut = curl_errno($ch) === 28;
    curl_close($ch);
    if ($out === false) { return [0, null, '', $timedOut ? 'timeout' : ('Sandbox unreachable: ' . $cerr), $timedOut ? 'TIMEOUT' : 'UNREACHABLE']; }
    $j = json_decode((string)$out, true);
    $json = is_array($j) ? $j : null;
    $err = ''; $code = '';
    if ($status >= 400) {
        $e = is_array($json['error'] ?? null) ? $json['error'] : (array)$json;
        $err = (string)($e['message'] ?? ('Sandbox error (HTTP ' . $status . ')'));
        $code = (string)($e['code'] ?? '');
    }
    return [$status, $json, (string)$out, $err, $code];
}

/* ── per-sid session cache (data/sbx_vercel.json): {sid: {s: session id, exp: unix ts, r: {port: url}, ip: interactive port}} ── */
function vcl_map_path(): string { return dirname(__DIR__) . '/data/sbx_vercel.json'; }
function vcl_map_get(string $key): ?array {
    $f = vcl_map_path();
    if (!is_file($f)) { return null; }
    $m = json_decode((string)@file_get_contents($f), true);
    return is_array($m) && is_array($m[$key] ?? null) ? $m[$key] : null;
}
function vcl_map_set(string $key, ?array $val): void {
    $f = vcl_map_path();
    @mkdir(dirname($f), 0755, true);
    $fh = @fopen($f, 'c+');
    if (!$fh) { return; }
    flock($fh, LOCK_EX);
    $m = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($m)) { $m = []; }
    if ($val === null) { unset($m[$key]); } else { $m[$key] = $val; }
    /* forget sessions that ended more than a day ago */
    foreach ($m as $k => $v) { if (is_array($v) && (int)($v['exp'] ?? 0) < time() - 86400) { unset($m[$k]); } }
    ftruncate($fh, 0); rewind($fh); fwrite($fh, (string)json_encode($m, JSON_UNESCAPED_SLASHES));
    flock($fh, LOCK_UN); fclose($fh);
}

/** remember a sandbox+session response */
function vcl_remember(string $sid, array $j): array {
    $s = (array)($j['session'] ?? []);
    $routes = [];
    foreach ((array)($j['routes'] ?? []) as $r) { if (!empty($r['port']) && !empty($r['url'])) { $routes[(string)(int)$r['port']] = (string)$r['url']; } }
    $start = (int)(($s['startedAt'] ?? $s['requestedAt'] ?? 0) / 1000) ?: time();
    $rec = ['s' => (string)($s['id'] ?? ''), 'exp' => $start + (int)(((int)($s['timeout'] ?? 0)) / 1000), 'start' => $start,
        'r' => $routes, 'ip' => (int)($s['interactivePort'] ?? 0), 'st' => (string)($s['status'] ?? '')];
    vcl_map_set($sid, $rec);
    return $rec;
}

function vcl_timeout_ms(array $cfg): int { return max(5, min(45, (int)($cfg['vercel_timeout_min'] ?? 15))) * 60000; }

/** user-facing text for a provider error — never shows plan names, provider names or URLs */
function vcl_friendly_error(string $err, string $fallback = 'The workspace could not start right now. Please try again in a minute.'): string {
    if ($err === '') { return $fallback; }
    if (preg_match('/snapshot|storage|quota|usage limit|payment|billing|plan|upgrade|hobby|credit|suspend|blocked/i', $err)) { return 'The workspace service is temporarily unavailable. Please try again in a minute.'; }
    if (preg_match('/concurren|rate limit|too many|limit/i', $err)) { return 'All workspaces are busy right now. Please try again in a minute.'; }
    if (preg_match('/vercel|https?:\/\//i', $err) || strlen($err) > 200) { return $fallback; }
    return $err;
}

/** true when the account cannot store more snapshots (persistent sandboxes are refused) */
function vcl_is_snap_limit(int $st, string $err, string $code): bool {
    return ($st === 402 || $code === 'payment_required') && preg_match('/snapshot/i', $err) === 1;
}

/**
 * keep the snapshot storage small:
 *  - snapshots that no sandbox uses any more are deleted
 *  - stopped sandboxes of this app that were not used for vercel_keep_days (3) are deleted with their snapshot
 * runs at most every 6 h, or right away when $force (snapshot storage full)
 */
function vcl_cleanup(array $cfg, bool $force = false): int {
    $last = (int)(vcl_map_get('_cleanup')['t'] ?? 0);
    if (!$force && $last > time() - 21600) { return 0; }
    if ($force && $last > time() - 120) { return 0; }
    vcl_map_set('_cleanup', ['t' => time(), 'exp' => time() + 86400 * 30]);
    $days = max(1, min(30, (int)($cfg['vercel_keep_days'] ?? 3)));
    $proj = (string)$cfg['vercel_project_id'];
    $freed = 0;
    [$st, $j] = vcl_http($cfg, 'GET', '/v2/sandboxes', ['project' => $proj, 'limit' => 100], null, 20);
    if ($st !== 200 || !is_array($j)) { return 0; }
    $inUse = [];
    foreach ((array)($j['sandboxes'] ?? []) as $sb) {
        if (!is_array($sb)) { continue; }
        $mine = (($sb['tags']['app'] ?? '') === 'devil-ai') || strpos((string)($sb['name'] ?? ''), 'devil-') === 0;
        $idle = (int)(((int)($sb['statusUpdatedAt'] ?? $sb['updatedAt'] ?? 0)) / 1000);
        if ($mine && ($sb['status'] ?? '') === 'stopped' && $idle > 0 && $idle < time() - $days * 86400) {
            [$ds] = vcl_http($cfg, 'DELETE', '/v2/sandboxes/' . rawurlencode((string)$sb['name']), ['projectId' => $proj, 'deleteOrphanSnapshots' => 'true'], null, 20);
            if ($ds >= 200 && $ds < 300) { $freed++; continue; }
        }
        if (!empty($sb['currentSnapshotId'])) { $inUse[(string)$sb['currentSnapshotId']] = true; }
    }
    [$st, $j] = vcl_http($cfg, 'GET', '/v1/sandboxes/snapshots', ['project' => $proj, 'limit' => 100], null, 20);
    if ($st === 200 && is_array($j)) {
        foreach ((array)($j['snapshots'] ?? []) as $sn) {
            $id = (string)($sn['id'] ?? '');
            if ($id === '' || isset($inUse[$id]) || ($sn['status'] ?? '') !== 'created') { continue; }
            /* a snapshot made in the last few minutes may be about to be attached — leave it */
            if ((int)(((int)($sn['createdAt'] ?? 0)) / 1000) > time() - 600) { continue; }
            [$ds] = vcl_http($cfg, 'DELETE', '/v1/sandboxes/snapshots/' . rawurlencode($id), [], null, 20);
            if ($ds >= 200 && $ds < 300) { $freed++; }
        }
    }
    return $freed;
}

function vcl_create(array $cfg, string $sid): array {
    vcl_cleanup($cfg);
    /* snapshot storage was full recently → start without a snapshot (files live for the session only) */
    $noSnap = (int)(vcl_map_get('_snapfull')['t'] ?? 0) > time() - 3600;
    $body = [
        'projectId' => (string)$cfg['vercel_project_id'],
        'name' => vcl_name($sid),
        'ports' => VCL_PORTS,
        'timeout' => vcl_timeout_ms($cfg),
        'resources' => ['vcpus' => max(1, min(8, (int)($cfg['vercel_vcpus'] ?? 2)))],
        'persistent' => !$noSnap,
        'tags' => ['app' => 'devil-ai'],
    ];
    [$st, $j, , $err, $code] = vcl_http($cfg, 'POST', '/v3/sandboxes', [], $body, 60);
    if (!$noSnap && vcl_is_snap_limit($st, $err, $code)) {
        /* free old snapshots, then retry once with a snapshot; if still full, go without one */
        $freed = vcl_cleanup($cfg, true);
        if ($freed > 0) { [$st, $j, , $err, $code] = vcl_http($cfg, 'POST', '/v3/sandboxes', [], $body, 60); }
        if (vcl_is_snap_limit($st, $err, $code)) {
            vcl_map_set('_snapfull', ['t' => time(), 'exp' => time() + 86400]);
            $body['persistent'] = false;
            [$st, $j, , $err, $code] = vcl_http($cfg, 'POST', '/v3/sandboxes', [], $body, 60);
        }
    }
    if ($st === 409 || $code === 'sandbox_already_exists') { return ['ok' => false, 'exists' => true, 'error' => vcl_friendly_error($err)]; }
    if ($st < 200 || $st >= 300 || empty($j['session']['id'])) { return ['ok' => false, 'error' => vcl_friendly_error($err, 'could not create the sandbox'), 'down' => true]; }
    return ['ok' => true, 'j' => $j];
}

/** GET the sandbox (resuming it when $wake). Returns ['ok', 'j'] | ['ok'=>false, 'none'|'asleep'|'down'] */
function vcl_get(array $cfg, string $sid, bool $wake): array {
    $q = ['projectId' => (string)$cfg['vercel_project_id']];
    if ($wake) { $q['resume'] = 'true'; }
    $t0 = microtime(true);
    while (true) {
        [$st, $j, , $err, $code] = vcl_http($cfg, 'GET', '/v2/sandboxes/' . rawurlencode(vcl_name($sid)), $q, null, 45);
        if ($st === 404) { return ['ok' => false, 'none' => true, 'error' => 'no sandbox yet']; }
        if ($st === 200 && is_array($j)) {
            $sst = (string)($j['session']['status'] ?? '');
            if ($sst === 'running') { return ['ok' => true, 'j' => $j]; }
            if (!$wake) { return ['ok' => false, 'asleep' => true, 'error' => 'sandbox is asleep']; }
            if (in_array($sst, ['failed', 'aborted'], true) && microtime(true) - $t0 > 5) { return ['ok' => false, 'down' => true, 'error' => 'sandbox session ' . $sst]; }
        } elseif ($st === 0 || $st === 401 || $st === 402 || $st === 403 || $st >= 500) {
            return ['ok' => false, 'down' => true, 'error' => vcl_friendly_error($err, 'sandbox unavailable')];
        } elseif ($st !== 409 && $st !== 423 && $st !== 429) {
            return ['ok' => false, 'down' => true, 'error' => vcl_friendly_error($err, 'sandbox error ' . $st)];
        }
        if (microtime(true) - $t0 > 40) { return ['ok' => false, 'error' => 'The sandbox is still starting — try again in a few seconds.']; }
        usleep(1500000);
    }
}

/**
 * session for sid, running and initialised. $create=false only looks it up; $wake=false never resumes a stopped one.
 * Returns ['ok'=>true, 's'=>session id, 'rec'=>cache record] or ['ok'=>false, 'none'|'asleep'|'down', 'error']
 */
function vcl_ensure(array $cfg, string $sid, bool $create = true, bool $wake = true, bool $fresh = false): array {
    static $ready = [];
    if (!$fresh && isset($ready[$sid])) { return $ready[$sid]; }
    $rec = $fresh ? null : vcl_map_get($sid);
    if ($rec && !empty($rec['s']) && (int)$rec['exp'] > time() + 20 && ($rec['st'] ?? '') === 'running') {
        /* keep an active chat alive: push the session timeout forward when < 6 min are left (capped by the plan's maximum) */
        if ((int)$rec['exp'] - time() < 360 && $wake) {
            [$st, $j] = vcl_http($cfg, 'POST', '/v2/sandboxes/sessions/' . rawurlencode($rec['s']) . '/extend-timeout', [], ['duration' => 600000], 15);
            if ($st === 200 && is_array($j) && !empty($j['session'])) { $rec['exp'] = (int)($rec['start'] ?? time()) + (int)(((int)($j['session']['timeout'] ?? 0)) / 1000); vcl_map_set($sid, $rec); }
        }
        return $ready[$sid] = ['ok' => true, 's' => (string)$rec['s'], 'rec' => $rec];
    }
    $g = vcl_get($cfg, $sid, $wake);
    $new = false;
    if (!empty($g['none'])) {
        if (!$create) { return $g; }
        $c = vcl_create($cfg, $sid);
        if (!empty($c['exists'])) { $g = vcl_get($cfg, $sid, true); }
        elseif (empty($c['ok'])) { return $c; }
        else { $g = ['ok' => true, 'j' => $c['j']]; $new = true; }
    }
    if (empty($g['ok'])) { return $g; }
    $rec = vcl_remember($sid, $g['j']);
    if ($rec['s'] === '') { return ['ok' => false, 'down' => true, 'error' => 'no session']; }
    /* every (re)started session: make sure the working folder exists and is ours */
    $init = 'test -w ' . VCL_WORK . ' || { sudo mkdir -p ' . VCL_WORK . ' /home/user/.bg && sudo chown -R "$(id -u):$(id -g)" /home/user; }; mkdir -p /home/user/.bg; '
          . ($new ? '(nohup setsid bash -c ' . escapeshellarg(VCL_PW_SETUP) . ' >/dev/null 2>&1 < /dev/null &); ' : '') . 'true';
    vcl_cmd($cfg, $rec['s'], $init, 30, '/');
    return $ready[$sid] = ['ok' => true, 's' => $rec['s'], 'rec' => $rec, 'new' => $new];
}

/** run a command in a session (wait for it). Returns [ok, exit, stdout, stderr, error, sessionGone] */
function vcl_cmd(array $cfg, string $session, string $sh, int $timeout, string $cwd = VCL_WORK): array {
    $body = ['command' => 'bash', 'args' => ['-c', $sh], 'cwd' => $cwd === '' ? '/' : $cwd, 'wait' => true, 'logs' => true, 'timeout' => ($timeout + 10) * 1000];
    [$st, $j, $raw, $err, $code] = vcl_http($cfg, 'POST', '/v2/sandboxes/sessions/' . rawurlencode($session) . '/cmd', [], $body, $timeout + 25);
    if ($st !== 200) {
        $gone = in_array($st, [404, 409, 410, 422], true) || preg_match('/stopp|not running|session/i', $err . ' ' . $code) === 1;
        if ($st === 400 && stripos($err, 'chdir') !== false) { $gone = false; }
        return [false, -1, '', '', $err ?: ('exec failed (HTTP ' . $st . ')'), $gone, $code];
    }
    $out = ''; $errS = ''; $rc = null; $e = '';
    foreach (preg_split('/\r?\n/', $raw) as $line) {
        if ($line === '') { continue; }
        $o = json_decode($line, true);
        if (!is_array($o)) { continue; }
        if (isset($o['stream'])) {
            if ($o['stream'] === 'stdout') { if (strlen($out) < 400000) { $out .= (string)($o['data'] ?? ''); } }
            elseif ($o['stream'] === 'stderr') { if (strlen($errS) < 200000) { $errS .= (string)($o['data'] ?? ''); } }
            else { $e .= (string)($o['data'] ?? ''); }
        } elseif (isset($o['command']['exitCode']) && $o['command']['exitCode'] !== null) { $rc = (int)$o['command']['exitCode']; }
        elseif (isset($o['error'])) { $e .= is_array($o['error']) ? (string)($o['error']['message'] ?? '') : (string)$o['error']; }
    }
    if ($rc === null) {
        if (stripos($e, 'chdir') !== false) { return [false, -1, $out, $errS, 'folder not found: ' . $cwd, false, '']; }
        return [false, -1, $out, $errS, $e !== '' ? $e : 'the command did not finish', $e !== '' && preg_match('/stopp|not running/i', $e) === 1, ''];
    }
    return [true, $rc, $out, $errS, '', false, ''];
}

/** command for a sid: ensures the session, retries once on a fresh session if it had stopped in between */
function vcl_run(array $cfg, string $sid, string $sh, int $timeout, string $cwd = VCL_WORK): array {
    $e = vcl_ensure($cfg, $sid, true);
    if (empty($e['ok'])) { return [false, -1, '', '', (string)($e['error'] ?? 'sandbox unavailable'), false, '']; }
    $r = vcl_cmd($cfg, $e['s'], $sh, $timeout, $cwd);
    if (!$r[0] && $r[5]) {
        $e = vcl_ensure($cfg, $sid, true, true, true);
        if (!empty($e['ok'])) { $r = vcl_cmd($cfg, $e['s'], $sh, $timeout, $cwd); }
    }
    return $r;
}

/** session API call for a sid with the same stopped-session retry. Returns vcl_http() tuple */
function vcl_scall(array $cfg, string $sid, string $method, string $sub, $body = null, int $timeout = 60, array $extra = [], bool $create = true): array {
    $e = vcl_ensure($cfg, $sid, $create);
    if (empty($e['ok'])) { return [0, null, '', (string)($e['error'] ?? 'sandbox unavailable'), !empty($e['none']) ? 'NONE' : '']; }
    $r = vcl_http($cfg, $method, '/v2/sandboxes/sessions/' . rawurlencode($e['s']) . $sub, [], $body, $timeout, $extra);
    if (in_array($r[0], [409, 410, 422], true) || ($r[0] >= 400 && $r[0] !== 404 && preg_match('/stopp|not running/i', $r[3] . ' ' . $r[4]) === 1)) {
        $e = vcl_ensure($cfg, $sid, $create, true, true);
        if (!empty($e['ok'])) { $r = vcl_http($cfg, $method, '/v2/sandboxes/sessions/' . rawurlencode($e['s']) . $sub, [], $body, $timeout, $extra); }
    }
    return $r;
}

function vcl_abs(string $rel): string {
    $rel = ltrim(str_replace("\0", '', $rel), '/');
    return $rel === '' || $rel === '.' ? VCL_WORK : VCL_WORK . '/' . $rel;
}

/** minimal ustar archive with one file, gzipped (format expected by POST …/fs/write) */
function vcl_targz(string $name, string $data): string {
    $prefix = '';
    if (strlen($name) > 100) {
        $cut = strrpos(substr($name, 0, 156), '/');
        if ($cut === false || strlen($name) - $cut - 1 > 100) { throw new RuntimeException('path too long'); }
        $prefix = substr($name, 0, $cut); $name = substr($name, $cut + 1);
    }
    $h = str_pad($name, 100, "\0") . sprintf('%07o', 0644) . "\0" . sprintf('%07o', 1000) . "\0" . sprintf('%07o', 1000) . "\0"
       . sprintf('%011o', strlen($data)) . "\0" . sprintf('%011o', time()) . "\0" . '        ' . '0' . str_repeat("\0", 100)
       . "ustar\0" . '00' . str_pad('ubuntu', 32, "\0") . str_pad('ubuntu', 32, "\0") . str_repeat("\0", 16) . str_pad($prefix, 155, "\0");
    $h = str_pad($h, 512, "\0");
    $sum = 0;
    for ($i = 0; $i < 512; $i++) { $sum += ord($h[$i]); }
    $h = substr_replace($h, sprintf('%06o', $sum) . "\0 ", 148, 8);
    $pad = (512 - strlen($data) % 512) % 512;
    return (string)gzencode($h . $data . str_repeat("\0", $pad) . str_repeat("\0", 1024), 6);
}

/* ═════════════ provider contract ═════════════ */

function vcl_health(array $cfg): array { return ['ok' => vcl_configured($cfg), 'provider' => 'vercel']; }

function vcl_open(array $cfg, string $sid): array {
    $e = vcl_ensure($cfg, $sid, true);
    return !empty($e['ok']) ? ['ok' => true, 'sid' => $sid, 'workdir' => VCL_WORK] : ['ok' => false, 'error' => (string)($e['error'] ?? ''), 'down' => !empty($e['down'])];
}

function vcl_exec(array $cfg, string $sid, string $cmd, int $timeout = 80, bool $background = false, float $wait = 3.0, string $cwd = ''): array {
    $cwd = $cwd === '' ? VCL_WORK : ($cwd[0] === '/' ? $cwd : VCL_WORK . '/' . $cwd);
    $t0 = microtime(true);
    if ($background) {
        $log = '/home/user/.bg/' . bin2hex(random_bytes(4)) . '.log';
        $w = max(0.0, min($wait, 15.0));
        $sh = 'mkdir -p /home/user/.bg; nohup setsid bash -lc ' . escapeshellarg($cmd) . ' > ' . $log . ' 2>&1 < /dev/null & echo "__PID=$!"; '
            . 'sleep ' . sprintf('%.1f', $w) . '; tail -c 6000 ' . $log . ' 2>/dev/null';
        $r = vcl_run($cfg, $sid, $sh, (int)ceil($w) + 20, $cwd);
        if (!$r[0]) { return ['ok' => false, 'error' => $r[4]]; }
        $pid = preg_match('/__PID=(\d+)/', $r[2], $m) ? $m[1] : '';
        $out = trim((string)preg_replace('/^.*?__PID=\d+\r?\n?/s', '', $r[2]));
        return ['ok' => $r[1] === 0, 'background' => true, 'pid' => $pid, 'log' => $log, 'output' => $out, 'ms' => (int)((microtime(true) - $t0) * 1000)];
    }
    $t = max(1, min($timeout, 900));
    $r = vcl_run($cfg, $sid, 'timeout -k 5 ' . $t . ' bash -lc ' . escapeshellarg($cmd), $t + 5, $cwd);
    if (!$r[0]) {
        if (stripos($r[4], 'timeout') !== false || $r[6] === 'TIMEOUT') {
            return ['ok' => true, 'exit_code' => 124, 'timed_out' => true, 'stdout' => $r[2], 'stderr' => 'timed out after ' . $t . 's', 'ms' => (int)((microtime(true) - $t0) * 1000)];
        }
        return ['ok' => false, 'error' => $r[4]];
    }
    $out = $r[2]; $trunc = false;
    if (strlen($out) > 200000) { $out = substr($out, 0, 20000) . "\n…[output cut]…\n" . substr($out, -150000); $trunc = true; }
    return ['ok' => true, 'exit_code' => $r[1], 'timed_out' => $r[1] === 124, 'stdout' => $out, 'stderr' => $r[3], 'truncated' => $trunc, 'ms' => (int)((microtime(true) - $t0) * 1000)];
}

function vcl_files(array $cfg, string $sid, string $path = '.', int $depth = 4): array {
    return sbx_files_via(static function (string $sh, int $t) use ($cfg, $sid) { return vcl_exec($cfg, $sid, $sh, $t); }, VCL_WORK, vcl_abs($path), $depth);
}

function vcl_read(array $cfg, string $sid, string $path): array {
    if ($path === '' || $path === '.') { return ['ok' => false, 'error' => 'path required', 'status' => 400]; }
    [$st, , $body, $err, $code] = vcl_scall($cfg, $sid, 'POST', '/fs/read', ['path' => vcl_abs($path)], 60, [], false);
    if ($code === 'NONE' || $st === 404) { return ['ok' => false, 'error' => 'file not found', 'status' => 404]; }
    if ($st !== 200) { return ['ok' => false, 'error' => $err ?: 'read failed', 'status' => $st]; }
    if (strlen($body) > 25 * 1024 * 1024) { return ['ok' => false, 'error' => 'file is larger than 25 MB', 'status' => 413]; }
    return ['ok' => true, 'data' => $body, 'type' => dyt_mime($path)];
}

function vcl_write(array $cfg, string $sid, string $path, string $data): array {
    if ($path === '' || $path === '.') { return ['ok' => false, 'error' => 'path required']; }
    if (strlen($data) > 25 * 1024 * 1024) { return ['ok' => false, 'error' => 'file is larger than 25 MB']; }
    $abs = vcl_abs($path);
    if (strpos('/' . $path . '/', '/../') !== false) { return ['ok' => false, 'error' => 'bad path']; }
    try { $tgz = vcl_targz(ltrim($abs, '/'), $data); } catch (Throwable $e) { return ['ok' => false, 'error' => 'path is too long']; }
    [$st, , , $err] = vcl_scall($cfg, $sid, 'POST', '/fs/write', $tgz, 120, ['Content-Type: application/gzip', 'x-cwd: /']);
    if ($st < 200 || $st >= 300) { return ['ok' => false, 'error' => $err ?: 'write failed']; }
    return ['ok' => true, 'path' => ltrim($path, '/'), 'size' => strlen($data)];
}

function vcl_delete(array $cfg, string $sid, string $path): array {
    $abs = vcl_abs($path);
    if ($abs === VCL_WORK || strpos($path, '..') !== false) { return ['ok' => false, 'error' => 'refusing to delete the workspace root']; }
    $r = vcl_exec($cfg, $sid, 'test -e ' . escapeshellarg($abs) . ' -o -L ' . escapeshellarg($abs) . ' || { echo __NF; exit 0; }; rm -rf -- ' . escapeshellarg($abs), 30);
    if (empty($r['ok'])) { return ['ok' => false, 'error' => (string)($r['error'] ?? 'delete failed')]; }
    if (strpos((string)($r['stdout'] ?? ''), '__NF') !== false) { return ['ok' => false, 'error' => 'not found']; }
    return ['ok' => true];
}

function vcl_ports(array $cfg, string $sid): array {
    $e = vcl_ensure($cfg, $sid, false, false);   /* just looking: never create or resume a sandbox for this */
    if (empty($e['ok'])) { return ['ok' => true, 'ports' => []]; }
    $r = vcl_cmd($cfg, $e['s'], 'ss -ltnH', 15, '/');
    if (!$r[0]) {
        if ($r[5]) { vcl_map_set($sid, null); return ['ok' => true, 'ports' => []]; }
        return ['ok' => false, 'error' => $r[4], 'ports' => []];
    }
    $skip = array_merge(VCL_INTERNAL_PORTS, [(int)($e['rec']['ip'] ?? 0)]);
    $ports = [];
    foreach (preg_split('/\r?\n/', $r[2]) as $line) {
        $c = preg_split('/\s+/', trim($line));
        if (count($c) < 4) { continue; }
        $pos = strrpos($c[3], ':');
        if ($pos === false) { continue; }
        $addr = substr($c[3], 0, $pos); $p = (int)substr($c[3], $pos + 1);
        if ($p < 1024 || $p > 65535 || in_array($p, $skip, true)) { continue; }
        $lo = in_array($addr, ['127.0.0.1', '[::1]', '::1'], true) || strpos($addr, '127.') === 0;
        $ports[$p] = ($ports[$p] ?? true) && $lo;
    }
    ksort($ports);
    $routes = (array)($e['rec']['r'] ?? []);
    $missing = [];
    foreach ($ports as $p => $lo) { if (!$lo && !isset($routes[(string)$p])) { $missing[] = $p; } }
    if ($missing) {   /* open a public URL for the new port(s) */
        $want = array_values(array_unique(array_merge(array_map('intval', array_keys($routes)), $missing)));
        if (count($want) > VCL_MAX_PORTS) { $want = array_values(array_unique(array_merge($missing, array_keys($ports), VCL_PORTS))); $want = array_slice($want, 0, VCL_MAX_PORTS); }
        [$st, $j] = vcl_http($cfg, 'PATCH', '/v2/sandboxes/' . rawurlencode(vcl_name($sid)), ['projectId' => (string)$cfg['vercel_project_id']], ['ports' => array_map('intval', $want)], 20);
        if ($st === 200 && is_array($j) && !empty($j['routes'])) {
            $routes = [];
            foreach ((array)$j['routes'] as $rt) { if (!empty($rt['port'])) { $routes[(string)(int)$rt['port']] = (string)$rt['url']; } }
            $rec = (array)$e['rec']; $rec['r'] = $routes; vcl_map_set($sid, $rec);
        }
    }
    $list = [];
    foreach ($ports as $p => $lo) {
        $row = ['port' => $p, 'url' => $lo ? '' : (string)($routes[(string)$p] ?? ''), 'localhost_only' => $lo];
        if ($lo) { $row['hint'] = 'bound to 127.0.0.1 only; restart it with host 0.0.0.0 for the preview'; }
        $list[] = $row;
    }
    return ['ok' => true, 'ports' => $list];
}

function vcl_zip(array $cfg, string $sid): array {
    $ex = [];
    foreach (DYT_SKIP_DIRS as $d) { $ex[] = escapeshellarg('*/' . $d . '/*'); $ex[] = escapeshellarg($d . '/*'); }
    $tmp = '/tmp/devil-workspace-' . bin2hex(random_bytes(3)) . '.zip';
    $r = vcl_exec($cfg, $sid, 'cd ' . VCL_WORK . ' && rm -f ' . $tmp . ' && (zip -qr -y ' . $tmp . ' . -x ' . implode(' ', $ex) . ' || true) && test -f ' . $tmp . ' || python3 -c "import zipfile;zipfile.ZipFile(\'' . $tmp . '\',\'w\').close()"', 120);
    if (empty($r['ok'])) { return ['ok' => false, 'error' => (string)($r['error'] ?? 'zip failed')]; }
    [$st, , $body, $err] = vcl_scall($cfg, $sid, 'POST', '/fs/read', ['path' => $tmp], 120);
    vcl_exec($cfg, $sid, 'rm -f ' . $tmp, 10);
    return $st === 200 ? ['ok' => true, 'data' => $body] : ['ok' => false, 'error' => $err ?: 'zip download failed'];
}

/** unpack a zip (e.g. a workspace moved from another provider) into the working folder */
function vcl_unzip(array $cfg, string $sid, string $zip): array {
    $tmp = '.devil-import-' . bin2hex(random_bytes(3)) . '.zip';
    $w = vcl_write($cfg, $sid, $tmp, $zip);
    if (empty($w['ok'])) { return $w; }
    $r = vcl_exec($cfg, $sid, 'cd ' . VCL_WORK . ' && (unzip -oq ' . $tmp . ' || python3 -m zipfile -e ' . $tmp . ' .); rc=$?; rm -f ' . $tmp . '; exit $rc', 120);
    return !empty($r['ok']) && (int)($r['exit_code'] ?? 1) === 0 ? ['ok' => true] : ['ok' => false, 'error' => (string)($r['error'] ?? $r['stderr'] ?? 'unzip failed')];
}

/** delete the sandbox of a chat (chat deleted) */
function vcl_purge(array $cfg, string $sid): array {
    [$st, , , $err] = vcl_http($cfg, 'DELETE', '/v2/sandboxes/' . rawurlencode(vcl_name($sid)), ['projectId' => (string)$cfg['vercel_project_id'], 'deleteOrphanSnapshots' => 'true'], null, 20);
    vcl_map_set($sid, null);
    return ($st >= 200 && $st < 300) || $st === 404 ? ['ok' => true] : ['ok' => false, 'error' => $err];
}
