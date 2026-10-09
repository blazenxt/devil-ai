<?php
/**
 * Devil AI — CodeSandbox provider for the Agent sandbox (inc/sandbox_csb.php)
 *
 * Same contract as inc/sandbox_e2b.php, so every sbx_* function / agent tool works unchanged:
 *   exec  → {ok, exit_code, timed_out, stdout, stderr}   (background: {ok, background, pid, log, output})
 *   files → {ok, root, entries[], truncated}   read → {ok, data, type}   write → {ok, path, size}
 *   ports → {ok, ports:[{port,url,localhost_only}]}     zip → {ok, data}
 *
 * One private microVM per (user, chat), forked from our template (csb_template). When idle it hibernates
 * (memory + running servers kept) and wakes up again on the next request. The template runs a tiny helper
 * (devtools/csb/tpl/.codesandbox/devil-agent.js) on port 8766: commands and files go over HTTPS to it, protected
 * by a per-VM key that we set right after the VM is created (first /claim wins; nobody else knows the VM id).
 *
 * Control plane: REST https://api.codesandbox.io (Bearer csb_… token).
 * Config keys: csb_api_key, csb_template (template sandbox id), csb_tier (Nano), csb_hibernate_min (10),
 *              csb_keep_days (5).
 */
declare(strict_types=1);

const CSB_API = 'https://api.codesandbox.io';
const CSB_WORK = '/home/user/work';
const CSB_AGENT_PORT = 8766;
const CSB_INTERNAL_PORTS = [2222, 8766];

function csb_configured(array $cfg): bool {
    return trim((string)($cfg['csb_api_key'] ?? '')) !== '' && trim((string)($cfg['csb_template'] ?? '')) !== '';
}
function csb_host(string $id, int $port): string { return 'https://' . $id . '-' . $port . '.csb.app'; }

/** control-plane call. Returns [status, data|null, error] */
function csb_http(array $cfg, string $method, string $path, ?array $body = null, int $timeout = 30): array {
    $ch = curl_init(CSB_API . $path);
    $h = ['Authorization: Bearer ' . trim((string)($cfg['csb_api_key'] ?? '')), 'User-Agent: DevilAI-Sandbox/1.0', 'Accept: application/json'];
    $opt = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(5, $timeout), CURLOPT_CONNECTTIMEOUT => 10];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $opt[CURLOPT_POSTFIELDS] = (string)json_encode($body, JSON_UNESCAPED_SLASHES); }
    $opt[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $opt);
    $out = curl_exec($ch);
    $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($out === false) { return [0, null, 'sandbox service unreachable: ' . $cerr]; }
    $j = json_decode((string)$out, true);
    $err = '';
    if ($st >= 400 || (is_array($j) && isset($j['success']) && $j['success'] === false)) {
        $e = is_array($j) ? ($j['errors'] ?? $j['error'] ?? '') : '';
        $err = is_array($e) ? implode('; ', array_map(static function ($x) { return is_array($x) ? (string)json_encode($x) : (string)$x; }, $e)) : (string)$e;
        if ($err === '') { $err = 'sandbox error (HTTP ' . $st . ')'; }
    }
    return [$st, is_array($j) ? ($j['data'] ?? $j) : null, $err];
}

/** user-facing text for a provider error — never shows plan names, provider names or URLs */
function csb_friendly_error(string $err, string $fallback): string {
    if ($err === '') { return $fallback; }
    if (preg_match('/concurren|rate limit|too many|limit/i', $err)) { return 'All workspaces are busy right now. Please try again in a minute.'; }
    if (preg_match('/credit|billing|payment|plan|upgrade|subscription|frozen|suspend|blocked|quota/i', $err)) { return 'The workspace service is temporarily unavailable. Please try again in a minute.'; }
    if (preg_match('/codesandbox|csb|together|https?:\/\//i', $err) || strlen($err) > 200) { return $fallback; }
    return $err;
}

/* ── sid → {id, key, t} (data/sbx_csb.json) ── */
function csb_map_path(): string { return dirname(__DIR__) . '/data/sbx_csb.json'; }
function csb_map_all(): array {
    $f = csb_map_path();
    $m = is_file($f) ? json_decode((string)@file_get_contents($f), true) : [];
    return is_array($m) ? $m : [];
}
function csb_map_get(string $sid): ?array { $m = csb_map_all(); return is_array($m[$sid] ?? null) ? $m[$sid] : null; }
function csb_map_set(string $sid, ?array $val): void {
    $f = csb_map_path();
    @mkdir(dirname($f), 0755, true);
    $fh = @fopen($f, 'c+');
    if (!$fh) { return; }
    flock($fh, LOCK_EX);
    $m = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($m)) { $m = []; }
    if ($val === null) { unset($m[$sid]); } else { $m[$sid] = $val; }
    ftruncate($fh, 0); rewind($fh); fwrite($fh, (string)json_encode($m, JSON_UNESCAPED_SLASHES));
    flock($fh, LOCK_UN); fclose($fh);
}
/** remember that the chat used its VM (for the idle clean-up); written at most every 10 minutes */
function csb_touch(string $sid, array $info): void {
    if ((int)($info['t'] ?? 0) < time() - 600) { $info['t'] = time(); csb_map_set($sid, $info); }
}

/** HTTPS call to the helper inside the VM. Returns [status, body, json|null, error] */
function csb_agent(array $info, string $method, string $path, $body = null, int $timeout = 60, array $extra = []): array {
    $ch = curl_init(csb_host((string)$info['id'], CSB_AGENT_PORT) . $path);
    $h = array_merge(['X-Devil-Key: ' . (string)($info['key'] ?? ''), 'User-Agent: DevilAI-Sandbox/1.0'], $extra);
    $opt = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(5, $timeout), CURLOPT_CONNECTTIMEOUT => 10];
    if (is_array($body)) { $h[] = 'Content-Type: application/json'; $opt[CURLOPT_POSTFIELDS] = (string)json_encode($body, JSON_UNESCAPED_SLASHES); }
    elseif (is_string($body)) { $h[] = 'Content-Type: application/octet-stream'; $opt[CURLOPT_POSTFIELDS] = $body; }
    $opt[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $opt);
    $out = curl_exec($ch);
    $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($out === false) { return [0, '', null, 'sandbox unreachable: ' . $cerr]; }
    $j = json_decode((string)$out, true);
    $ok = $st >= 200 && $st < 300;
    $err = $ok ? '' : (is_array($j) && !empty($j['error']) ? (string)$j['error'] : 'sandbox error (HTTP ' . $st . ')');
    return [$st, (string)$out, is_array($j) ? $j : null, $err];
}

/** start (or wake) the VM; HTTP traffic to its hosts also wakes it while it hibernates */
function csb_start(array $cfg, string $id): array {
    $body = [
        'hibernation_timeout_seconds' => max(2, min(60, (int)($cfg['csb_hibernate_min'] ?? 10))) * 60,
        'automatic_wakeup_config' => ['http' => true, 'websocket' => true],
    ];
    $tier = trim((string)($cfg['csb_tier'] ?? 'Nano'));
    if (in_array($tier, ['Pico', 'Nano', 'Micro'], true)) { $body['tier'] = $tier; }
    return csb_http($cfg, 'POST', '/vm/' . rawurlencode($id) . '/start', $body, 90);
}

/** wait until the helper answers /h (VM booted or resumed) */
function csb_wait_agent(array $info, int $maxS = 45): bool {
    $t0 = time();
    do {
        [$st, , $j] = csb_agent($info, 'GET', '/h', null, 10);
        if ($st === 200 && !empty($j['ok'])) { return true; }
        usleep(1200000);
    } while (time() - $t0 < $maxS);
    return false;
}

/** remove the VMs of chats that were not used for csb_keep_days (runs at most every 6 h) */
function csb_cleanup(array $cfg): void {
    $m = csb_map_all();
    if ((int)($m['_cleanup']['t'] ?? 0) > time() - 21600) { return; }
    csb_map_set('_cleanup', ['t' => time()]);
    $days = max(1, min(60, (int)($cfg['csb_keep_days'] ?? 5)));
    foreach ($m as $sid => $info) {
        if ($sid === '_cleanup' || !is_array($info) || empty($info['id'])) { continue; }
        if ((int)($info['t'] ?? 0) < time() - $days * 86400) {
            [$st] = csb_http($cfg, 'DELETE', '/vm/' . rawurlencode((string)$info['id']), null, 20);
            if (($st >= 200 && $st < 300) || $st === 404) { csb_map_set((string)$sid, null); }
        }
    }
}

function csb_create(array $cfg, string $sid): array {
    $tpl = trim((string)($cfg['csb_template'] ?? ''));
    [$st, $d, $err] = csb_http($cfg, 'POST', '/sandbox/' . rawurlencode($tpl) . '/fork', [
        'title' => 'devil-' . substr(preg_replace('/[^a-z0-9]/', '', strtolower($sid)), 0, 24),
        'privacy' => 2,
        'private_preview' => false,
        'path' => '/devil-ai',
    ], 60);
    $id = is_array($d) ? (string)($d['id'] ?? '') : '';
    if ($st < 200 || $st >= 300 || $id === '') {
        return ['ok' => false, 'error' => csb_friendly_error($err, 'could not create the sandbox'), 'down' => true];
    }
    [$st, , $err] = csb_start($cfg, $id);
    if ($st < 200 || $st >= 300) {
        csb_http($cfg, 'DELETE', '/vm/' . rawurlencode($id), null, 20);
        return ['ok' => false, 'error' => csb_friendly_error($err, 'the sandbox did not start'), 'down' => true];
    }
    $info = ['id' => $id, 'key' => bin2hex(random_bytes(24)), 't' => time()];
    if (!csb_wait_agent(['id' => $id, 'key' => ''], 60)) {
        csb_http($cfg, 'DELETE', '/vm/' . rawurlencode($id), null, 20);
        return ['ok' => false, 'error' => 'the sandbox did not start', 'down' => true];
    }
    [$st, , $j] = csb_agent($info, 'POST', '/claim', ['key' => $info['key']], 15);
    if ($st !== 200 || empty($j['ok'])) {
        /* someone else claimed it (should never happen) — throw this VM away */
        csb_http($cfg, 'DELETE', '/vm/' . rawurlencode($id), null, 20);
        return ['ok' => false, 'error' => 'the sandbox did not start', 'down' => true];
    }
    csb_map_set($sid, $info);
    return ['ok' => true, 'info' => $info];
}

/** true when the VM is running right now (no wake-up) */
function csb_is_running(array $cfg, string $id): ?bool {
    [$st, $d] = csb_http($cfg, 'GET', '/vm/running', null, 15);
    if ($st !== 200 || !is_array($d)) { return null; }
    foreach ((array)($d['vms'] ?? []) as $vm) { if (is_array($vm) && (string)($vm['id'] ?? '') === $id) { return true; } }
    return false;
}

/**
 * VM info for sid, running. $create=false only uses an existing one; $wake=false never wakes a hibernated one.
 * Returns {ok, info:{id,key,t}, new?} | {ok:false, none|asleep|down, error}
 */
function csb_ensure(array $cfg, string $sid, bool $create = true, bool $wake = true): array {
    static $ready = [];
    if (isset($ready[$sid])) { return ['ok' => true, 'info' => $ready[$sid]]; }
    $info = csb_map_get($sid);
    if ($info !== null && !$wake) {
        $run = csb_is_running($cfg, (string)$info['id']);
        if ($run !== true) { return ['ok' => false, 'asleep' => true, 'error' => 'sandbox is asleep']; }
        $ready[$sid] = $info;
        return ['ok' => true, 'info' => $info];
    }
    if ($info !== null) {
        /* running or hibernated: the helper answers (an HTTP request wakes a hibernated VM by itself) */
        [$st, , $j] = csb_agent($info, 'GET', '/h', null, 12);
        if ($st !== 200 || empty($j['ok'])) {
            [$st, , $err] = csb_start($cfg, (string)$info['id']);
            if ($st === 404) { csb_map_set($sid, null); $info = null; }
            elseif ($st < 200 || $st >= 300) {
                return ['ok' => false, 'error' => csb_friendly_error($err, 'the sandbox did not start'), 'down' => $st === 0 || $st === 401 || $st === 402 || $st === 403 || $st === 429 || $st >= 500];
            } elseif (!csb_wait_agent($info, 45)) {
                return ['ok' => false, 'error' => 'The sandbox is still starting — try again in a few seconds.'];
            }
        }
        if ($info !== null) {
            csb_touch($sid, $info);
            $ready[$sid] = $info;
            return ['ok' => true, 'info' => $info];
        }
    }
    if (!$create) { return ['ok' => false, 'none' => true, 'error' => 'no sandbox yet']; }
    csb_cleanup($cfg);
    $c = csb_create($cfg, $sid);
    if (empty($c['ok'])) { return $c; }
    $ready[$sid] = $c['info'];
    return ['ok' => true, 'info' => $c['info'], 'new' => true];
}

/** run a shell command through the helper. Returns {ok, exit_code, timed_out, stdout, stderr} | {ok:false, error, status} */
function csb_rpc_exec(array $info, string $sh, int $timeout, string $cwd = CSB_WORK): array {
    [$st, , $j, $err] = csb_agent($info, 'POST', '/exec', ['sh' => $sh, 'cwd' => $cwd, 'timeout' => $timeout], $timeout + 20);
    if ($st !== 200 || !is_array($j) || empty($j['ok'])) { return ['ok' => false, 'error' => $err ?: (string)($j['error'] ?? 'exec failed'), 'status' => $st]; }
    return ['ok' => true, 'exit_code' => (int)($j['exit_code'] ?? 0), 'timed_out' => !empty($j['timed_out']),
        'stdout' => (string)($j['stdout'] ?? ''), 'stderr' => (string)($j['stderr'] ?? '')];
}

/** run with the VM for sid; when the helper does not answer (VM hibernating / restarting) start it and retry once */
function csb_with(array $cfg, string $sid, callable $fn, bool $create = true) {
    $e = csb_ensure($cfg, $sid, $create);
    if (empty($e['ok'])) { return ['__fail' => true, 'error' => (string)($e['error'] ?? 'sandbox unavailable'), 'none' => !empty($e['none'])]; }
    $r = $fn($e['info']);
    $st = is_array($r) ? (int)($r['status'] ?? ($r[0] ?? 200)) : 200;
    if ($st === 0 || $st === 502 || $st === 503 || $st === 504) {
        csb_start($cfg, (string)$e['info']['id']);
        csb_wait_agent($e['info'], 40);
        $r = $fn($e['info']);
    }
    return $r;
}

function csb_abs(string $rel): string {
    $rel = ltrim(str_replace("\0", '', $rel), '/');
    return $rel === '' || $rel === '.' ? CSB_WORK : CSB_WORK . '/' . $rel;
}

/* ═════════════ provider contract ═════════════ */

function csb_health(array $cfg): array { return ['ok' => csb_configured($cfg), 'provider' => 'csb']; }

function csb_open(array $cfg, string $sid): array {
    $e = csb_ensure($cfg, $sid, true);
    return !empty($e['ok']) ? ['ok' => true, 'sid' => $sid, 'workdir' => CSB_WORK] : ['ok' => false, 'error' => (string)($e['error'] ?? ''), 'down' => !empty($e['down'])];
}

function csb_exec(array $cfg, string $sid, string $cmd, int $timeout = 80, bool $background = false, float $wait = 3.0, string $cwd = ''): array {
    $cwd = $cwd === '' ? CSB_WORK : ($cwd[0] === '/' ? $cwd : CSB_WORK . '/' . $cwd);
    $t0 = microtime(true);
    if ($background) {
        $log = '/home/user/.bg/' . bin2hex(random_bytes(4)) . '.log';
        $w = max(0.0, min($wait, 15.0));
        $sh = 'mkdir -p /home/user/.bg ' . escapeshellarg($cwd) . '; cd ' . escapeshellarg($cwd) . '; nohup setsid bash -lc ' . escapeshellarg($cmd) . ' > ' . $log . ' 2>&1 < /dev/null & echo "__PID=$!"; '
            . 'sleep ' . sprintf('%.1f', $w) . '; tail -c 6000 ' . $log . ' 2>/dev/null';
        $r = csb_with($cfg, $sid, static function ($info) use ($sh, $w) { return csb_rpc_exec($info, $sh, (int)ceil($w) + 20, '/home/user'); });
        if (!empty($r['__fail']) || empty($r['ok'])) { return ['ok' => false, 'error' => csb_friendly_error((string)($r['error'] ?? ''), 'exec failed')]; }
        $res = (string)($r['stdout'] ?? '');
        $pid = preg_match('/__PID=(\d+)/', $res, $m) ? $m[1] : '';
        $out = trim((string)preg_replace('/^.*?__PID=\d+\r?\n?/s', '', $res));
        return ['ok' => (int)($r['exit_code'] ?? 0) === 0, 'background' => true, 'pid' => $pid, 'log' => $log, 'output' => $out, 'ms' => (int)((microtime(true) - $t0) * 1000)];
    }
    $t = max(1, min($timeout, 900));
    $sh = 'mkdir -p ' . escapeshellarg($cwd) . ' 2>/dev/null; cd ' . escapeshellarg($cwd) . ' && timeout -k 5 ' . $t . ' bash -lc ' . escapeshellarg($cmd);
    $r = csb_with($cfg, $sid, static function ($info) use ($sh, $t) { return csb_rpc_exec($info, $sh, $t + 10, '/home/user'); });
    if (!empty($r['__fail']) || empty($r['ok'])) { return ['ok' => false, 'error' => csb_friendly_error((string)($r['error'] ?? ''), 'exec failed')]; }
    $r['ms'] = (int)((microtime(true) - $t0) * 1000);
    unset($r['status']);
    return $r;
}

function csb_files(array $cfg, string $sid, string $path = '.', int $depth = 4): array {
    return sbx_files_via(static function (string $sh, int $t) use ($cfg, $sid) { return csb_exec($cfg, $sid, $sh, $t); }, CSB_WORK, csb_abs($path), $depth);
}

function csb_read(array $cfg, string $sid, string $path): array {
    if ($path === '' || $path === '.') { return ['ok' => false, 'error' => 'path required', 'status' => 400]; }
    $abs = $path[0] === '/' && strpos($path, '/tmp/') === 0 ? $path : csb_abs($path);
    $r = csb_with($cfg, $sid, static function ($info) use ($abs) { return csb_agent($info, 'GET', '/file?path=' . rawurlencode($abs), null, 90); }, false);
    if (!empty($r['__fail'])) { return ['ok' => false, 'error' => !empty($r['none']) ? 'file not found' : csb_friendly_error((string)$r['error'], 'read failed'), 'status' => !empty($r['none']) ? 404 : 502]; }
    [$st, $body, , $err] = $r;
    if ($st === 404) { return ['ok' => false, 'error' => 'file not found', 'status' => 404]; }
    if ($st !== 200) { return ['ok' => false, 'error' => csb_friendly_error($err, 'read failed'), 'status' => $st]; }
    if (strlen($body) > 25 * 1024 * 1024) { return ['ok' => false, 'error' => 'file is larger than 25 MB', 'status' => 413]; }
    return ['ok' => true, 'data' => $body, 'type' => dyt_mime($path)];
}

function csb_write(array $cfg, string $sid, string $path, string $data): array {
    if ($path === '' || $path === '.') { return ['ok' => false, 'error' => 'path required']; }
    if (strlen($data) > 25 * 1024 * 1024) { return ['ok' => false, 'error' => 'file is larger than 25 MB']; }
    $abs = csb_abs($path);
    $r = csb_with($cfg, $sid, static function ($info) use ($abs, $data) { return csb_agent($info, 'PUT', '/file?path=' . rawurlencode($abs), $data, 120); });
    if (!empty($r['__fail'])) { return ['ok' => false, 'error' => csb_friendly_error((string)$r['error'], 'write failed')]; }
    [$st, , , $err] = $r;
    if ($st < 200 || $st >= 300) { return ['ok' => false, 'error' => csb_friendly_error($err, 'write failed')]; }
    return ['ok' => true, 'path' => ltrim($path, '/'), 'size' => strlen($data)];
}

function csb_delete(array $cfg, string $sid, string $path): array {
    $abs = csb_abs($path);
    if ($abs === CSB_WORK || strpos($path, '..') !== false) { return ['ok' => false, 'error' => 'refusing to delete the workspace root']; }
    $r = csb_exec($cfg, $sid, 'test -e ' . escapeshellarg($abs) . ' -o -L ' . escapeshellarg($abs) . ' || { echo __NF; exit 0; }; rm -rf -- ' . escapeshellarg($abs), 30);
    if (empty($r['ok'])) { return ['ok' => false, 'error' => (string)($r['error'] ?? 'delete failed')]; }
    if (strpos((string)($r['stdout'] ?? ''), '__NF') !== false) { return ['ok' => false, 'error' => 'not found']; }
    return ['ok' => true];
}

function csb_ports(array $cfg, string $sid): array {
    $e = csb_ensure($cfg, $sid, false, false);    /* just looking: never create or wake a sandbox for this */
    if (empty($e['ok'])) { return ['ok' => true, 'ports' => []]; }
    $r = csb_rpc_exec($e['info'], 'ss -ltnH', 15, '/home/user');
    if (empty($r['ok'])) { return ['ok' => false, 'error' => csb_friendly_error((string)($r['error'] ?? ''), 'ports failed'), 'ports' => []]; }
    $ports = [];
    foreach (preg_split('/\r?\n/', (string)($r['stdout'] ?? '')) as $line) {
        $c = preg_split('/\s+/', trim($line));
        if (count($c) < 4) { continue; }
        $pos = strrpos($c[3], ':');
        if ($pos === false) { continue; }
        $addr = substr($c[3], 0, $pos); $p = (int)substr($c[3], $pos + 1);
        if ($p < 1024 || $p > 65535 || in_array($p, CSB_INTERNAL_PORTS, true)) { continue; }
        $lo = in_array($addr, ['127.0.0.1', '[::1]', '::1'], true);
        $ports[$p] = ($ports[$p] ?? true) && $lo;
    }
    ksort($ports);
    $list = [];
    foreach ($ports as $p => $lo) {
        $row = ['port' => $p, 'url' => csb_host((string)$e['info']['id'], $p), 'localhost_only' => $lo];
        if ($lo) { $row['hint'] = 'bound to 127.0.0.1 only; restart it with host 0.0.0.0 for the preview'; }
        $list[] = $row;
    }
    return ['ok' => true, 'ports' => $list];
}

function csb_zip(array $cfg, string $sid): array {
    $ex = [];
    foreach (DYT_SKIP_DIRS as $d) { $ex[] = escapeshellarg('*/' . $d . '/*'); $ex[] = escapeshellarg($d . '/*'); }
    $tmp = '/tmp/devil-workspace-' . bin2hex(random_bytes(3)) . '.zip';
    $r = csb_exec($cfg, $sid, 'cd ' . CSB_WORK . ' && rm -f ' . $tmp . ' && (zip -qr -y ' . $tmp . ' . -x ' . implode(' ', $ex) . ' || true) && test -f ' . $tmp . ' || python3 -c "import zipfile;zipfile.ZipFile(\'' . $tmp . '\',\'w\').close()"', 120);
    if (empty($r['ok'])) { return ['ok' => false, 'error' => (string)($r['error'] ?? 'zip failed')]; }
    $d = csb_read($cfg, $sid, $tmp);
    csb_exec($cfg, $sid, 'rm -f ' . $tmp, 10);
    return !empty($d['ok']) ? ['ok' => true, 'data' => $d['data']] : ['ok' => false, 'error' => (string)($d['error'] ?? 'zip download failed')];
}

/** unpack a zip (a workspace moved from another provider) into the working folder */
function csb_unzip(array $cfg, string $sid, string $zip): array {
    $tmp = '.devil-import-' . bin2hex(random_bytes(3)) . '.zip';
    $w = csb_write($cfg, $sid, $tmp, $zip);
    if (empty($w['ok'])) { return $w; }
    $r = csb_exec($cfg, $sid, 'cd ' . CSB_WORK . ' && (unzip -oq ' . $tmp . ' || python3 -m zipfile -e ' . $tmp . ' .); rc=$?; rm -f ' . $tmp . '; exit $rc', 120);
    return !empty($r['ok']) && (int)($r['exit_code'] ?? 1) === 0 ? ['ok' => true] : ['ok' => false, 'error' => (string)($r['error'] ?? $r['stderr'] ?? 'unzip failed')];
}

/** delete the VM of a chat (chat deleted) */
function csb_purge(array $cfg, string $sid): array {
    $info = csb_map_get($sid);
    if ($info === null) { return ['ok' => true]; }
    [$st, , $err] = csb_http($cfg, 'DELETE', '/vm/' . rawurlencode((string)$info['id']), null, 20);
    csb_map_set($sid, null);
    return ($st >= 200 && $st < 300) || $st === 404 ? ['ok' => true] : ['ok' => false, 'error' => csb_friendly_error($err, 'delete failed')];
}
