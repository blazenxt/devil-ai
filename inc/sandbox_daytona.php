<?php
/**
 * Devil AI — Daytona provider for the Agent sandbox (inc/sandbox_daytona.php)
 *
 * Implements the same contract as the old Devil Sandbox gateway, so the sbx_* functions in
 * inc/sandbox.php (and therefore the agent tools and the workspace UI) work unchanged:
 *   exec      → {ok, exit_code, timed_out, stdout, stderr}            (background: {ok, background, pid, log, output})
 *   files     → {ok, root, entries:[{path,type,size|skipped}], truncated}
 *   read      → {ok, data, type}      write → {ok, path, size}      delete → {ok}
 *   ports     → {ok, ports:[{port,url,localhost_only}]}               zip → {ok, data}
 *
 * One Daytona sandbox per (user, chat): it carries the label devil_sid=<sid>. The sandbox auto-stops
 * when idle (files and installed packages are kept), is started again on the next call, and is
 * deleted after a few idle days so the free quota is not filled with old chats.
 * Config keys: sandbox_provider=daytona, daytona_api_key, daytona_target (us|eu), daytona_api_url.
 */
declare(strict_types=1);

const DYT_WORK = '/home/user/work';
const DYT_INTERNAL_PORTS = [2280, 22220, 22222, 33333];
const DYT_SKIP_DIRS = ['node_modules', '.git', '__pycache__', '.venv', 'venv', '.cache', '.npm', '.next', 'dist', 'build'];

function dyt_api(array $cfg): string { return rtrim((string)($cfg['daytona_api_url'] ?? '') ?: 'https://app.daytona.io/api', '/'); }

/** raw HTTP call. $body: string (JSON) or array (multipart). Returns [status, json|null, body, error, code] */
function dyt_http(array $cfg, string $method, string $url, $body = null, int $timeout = 60): array {
    $headers = ['Authorization: Bearer ' . (string)($cfg['daytona_api_key'] ?? ''), 'User-Agent: DevilAI-Sandbox/2.0'];
    $ch = curl_init($url);
    $opt = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(5, $timeout), CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false];
    if (is_array($body)) { $opt[CURLOPT_POSTFIELDS] = $body; }
    elseif ($body !== null) { $headers[] = 'Content-Type: application/json'; $opt[CURLOPT_POSTFIELDS] = $body; }
    $opt[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opt);
    $out = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    $timedOut = curl_errno($ch) === 28;
    curl_close($ch);
    if ($out === false) { return [0, null, '', $timedOut ? 'timeout' : ('Sandbox unreachable: ' . $cerr), $timedOut ? 'TIMEOUT' : '']; }
    $j = json_decode((string)$out, true);
    $json = is_array($j) ? $j : null;
    $err = '';
    $code = '';
    if ($status >= 400) {
        $err = (string)($json['message'] ?? ('Sandbox error (HTTP ' . $status . ')'));
        $code = (string)($json['code'] ?? '');
    }
    return [$status, $json, (string)$out, $err, $code];
}

/* ── sid → sandbox id map (data/sbx_daytona.json) ── */
function dyt_map_path(): string { return dirname(__DIR__) . '/data/sbx_daytona.json'; }
function dyt_map_get(string $key): string {
    $f = dyt_map_path();
    if (!is_file($f)) { return ''; }
    $m = json_decode((string)@file_get_contents($f), true);
    return is_array($m) ? (string)($m[$key] ?? '') : '';
}
function dyt_map_set(string $key, ?string $val): void {
    $f = dyt_map_path();
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

function dyt_find(array $cfg, string $sid): ?array {
    [$st, $j] = dyt_http($cfg, 'GET', dyt_api($cfg) . '/sandbox?' . http_build_query(['labels' => json_encode(['devil_sid' => $sid])]), null, 20);
    if ($st !== 200 || !is_array($j)) { return null; }
    $items = isset($j['items']) ? (array)$j['items'] : (array_is_list($j) ? $j : []);
    foreach ($items as $it) {
        if (is_array($it) && !in_array((string)($it['state'] ?? ''), ['destroyed', 'destroying', 'error', 'build_failed'], true)) { return $it; }
    }
    return null;
}

function dyt_create(array $cfg, string $sid): array {
    $body = [
        'target' => (string)($cfg['daytona_target'] ?? '') ?: 'us',
        'public' => true,                       /* previews open without a token (the URL itself is unguessable) */
        'autoStopInterval' => max(5, (int)($cfg['daytona_auto_stop'] ?? 15)),
        /* archive soon after it stops: archiving moves the files to object storage and frees the org's
           disk quota (30 GiB = only 10 sandboxes otherwise). The files stay; starting it again restores them. */
        'autoArchiveInterval' => max(1, (int)($cfg['daytona_auto_archive'] ?? 15)),
        'autoDeleteInterval' => max(60, (int)($cfg['daytona_auto_delete'] ?? 60 * 24 * 3)),
        'labels' => ['devil_sid' => $sid, 'app' => 'devil-ai'],
    ];
    if (!empty($cfg['daytona_snapshot'])) { $body['snapshot'] = (string)$cfg['daytona_snapshot']; }
    [$st, $j, , $err] = dyt_http($cfg, 'POST', dyt_api($cfg) . '/sandbox', (string)json_encode($body), 60);
    if (($st < 200 || $st >= 300 || empty($j['id'])) && dyt_quota_error($err) && dyt_free_quota($cfg) > 0) {
        /* disk quota full: older stopped sandboxes were just sent to archive — give it a moment, then retry once */
        for ($i = 0; $i < 6; $i++) {
            sleep(3);
            [$st, $j, , $err] = dyt_http($cfg, 'POST', dyt_api($cfg) . '/sandbox', (string)json_encode($body), 60);
            if ($st >= 200 && $st < 300 && !empty($j['id'])) { break; }
            if (!dyt_quota_error($err)) { break; }
        }
    }
    if ($st < 200 || $st >= 300 || empty($j['id'])) { return ['ok' => false, 'error' => dyt_friendly_error($err, 'could not create the sandbox'), 'down' => true]; }
    return ['ok' => true, 'sb' => $j, 'new' => true];
}

/** Never show the provider's own error text (plan tiers, org ids, API host names) to users or the model. */
function dyt_friendly_error(string $err, string $fallback): string {
    if ($err === '') { return $fallback; }
    if (dyt_quota_error($err)) { return 'All workspaces are busy right now (storage is full). Please try again in a minute.'; }
    if (preg_match('/\btier\b|organi[sz]ation|billing|upgrade|suspend|credit|payment/i', $err)) { return 'The workspace service is temporarily unavailable. Please try again in a minute.'; }
    if (preg_match('/daytona|https?:\/\//i', $err) || strlen($err) > 200) { return $fallback; }
    return $err;
}

function dyt_quota_error(string $err): bool {
    return (bool)preg_match('/disk limit|quota|limit exceeded|concurrency limit|not enough (?:disk|resources)/i', $err);
}

/** Free disk quota: archive the oldest STOPPED sandboxes of this app (files are kept in object storage and come
    back on the next start — nothing is deleted). Returns how many were sent to archive. */
function dyt_free_quota(array $cfg, int $want = 2): int {
    [$st, $j] = dyt_http($cfg, 'GET', dyt_api($cfg) . '/sandbox?' . http_build_query(['labels' => json_encode(['app' => 'devil-ai'])]), null, 20);
    if ($st !== 200 || !is_array($j)) { return 0; }
    $items = isset($j['items']) ? (array)$j['items'] : (array_is_list($j) ? $j : []);
    $stopped = array_values(array_filter($items, static function ($it) { return is_array($it) && (string)($it['state'] ?? '') === 'stopped'; }));
    usort($stopped, static function ($a, $b) { return strcmp((string)($a['updatedAt'] ?? ''), (string)($b['updatedAt'] ?? '')); });
    $n = 0;
    foreach (array_slice($stopped, 0, $want) as $it) {
        [$as] = dyt_http($cfg, 'POST', dyt_api($cfg) . '/sandbox/' . rawurlencode((string)$it['id']) . '/archive', '', 20);
        if ($as >= 200 && $as < 300) { $n++; }
    }
    return $n;
}

/** wait until the sandbox is started (starting it if it was stopped/archived) */
function dyt_wait_started(array $cfg, string $id, int $maxSec = 45, bool $wake = true): array {
    $t0 = microtime(true);
    $kicked = false;
    while (true) {
        [$st, $j, , $err] = dyt_http($cfg, 'GET', dyt_api($cfg) . '/sandbox/' . rawurlencode($id), null, 15);
        if ($st === 404) { return ['ok' => false, 'gone' => true, 'error' => 'sandbox not found']; }
        if ($st !== 200 || !is_array($j)) { return ['ok' => false, 'error' => dyt_friendly_error($err, 'sandbox state unknown'), 'down' => $st === 0 || $st === 401 || $st === 402 || $st === 403 || $st >= 500]; }
        $state = (string)($j['state'] ?? '');
        if ($state === 'started') { return ['ok' => true, 'sb' => $j]; }
        if (in_array($state, ['destroyed', 'destroying', 'error', 'build_failed'], true)) { return ['ok' => false, 'gone' => true, 'error' => 'sandbox ' . $state]; }
        if (!$wake && in_array($state, ['stopped', 'archived', 'stopping', 'archiving'], true)) { return ['ok' => false, 'asleep' => true, 'error' => 'sandbox is asleep']; }
        if (!$kicked && in_array($state, ['stopped', 'archived'], true)) {
            dyt_http($cfg, 'POST', dyt_api($cfg) . '/sandbox/' . rawurlencode($id) . '/start', '', 30);
            $kicked = true;
        }
        if (microtime(true) - $t0 > $maxSec) { return ['ok' => false, 'error' => 'The sandbox is still starting — try again in a few seconds.']; }
        usleep(800000);
    }
}

/** sandbox id for sid, started and initialised. $create=false only looks it up; $wake=false never starts a sleeping one. */
function dyt_ensure(array $cfg, string $sid, bool $create = true, bool $wake = true): array {
    static $ready = [];
    if (isset($ready[$sid])) { return ['ok' => true, 'id' => $ready[$sid]]; }
    $id = dyt_map_get($sid);
    $new = false;
    if ($id === '') {
        $f = dyt_find($cfg, $sid);
        if ($f) { $id = (string)$f['id']; }
        elseif (!$create) { return ['ok' => false, 'error' => 'no sandbox yet', 'none' => true]; }
        else {
            $c = dyt_create($cfg, $sid);
            if (empty($c['ok'])) { return $c; }
            $id = (string)$c['sb']['id']; $new = true;
        }
        dyt_map_set($sid, $id);
    }
    $w = dyt_wait_started($cfg, $id, $new ? 60 : 45, $wake || $new);
    if (!empty($w['asleep'])) { return ['ok' => false, 'asleep' => true, 'error' => 'sandbox is asleep']; }
    if (empty($w['ok']) && !empty($w['gone'])) {
        dyt_map_set($sid, null);
        if (!$create) { return ['ok' => false, 'error' => 'no sandbox yet', 'none' => true]; }
        $c = dyt_create($cfg, $sid);
        if (empty($c['ok'])) { return $c; }
        $id = (string)$c['sb']['id']; $new = true;
        dyt_map_set($sid, $id);
        $w = dyt_wait_started($cfg, $id, 60);
    }
    if (empty($w['ok'])) { return ['ok' => false, 'error' => (string)($w['error'] ?? 'sandbox unavailable'), 'down' => !empty($w['down'])]; }
    /* first start: the working folder lives at /home/user/work (same layout the agent prompt describes) */
    $init = 'test -d ' . DYT_WORK . ' || { sudo mkdir -p ' . DYT_WORK . ' /home/user/.bg && sudo chown -R "$(id -u):$(id -g)" /home/user; }; '
          . 'python3 -c "import playwright" 2>/dev/null || (nohup pip install -q playwright >/dev/null 2>&1 &) ; true';
    dyt_tb($cfg, $id, 'POST', '/process/execute', (string)json_encode(['command' => 'bash -c ' . escapeshellarg($init), 'timeout' => 30]), 40);
    $ready[$sid] = $id;
    return ['ok' => true, 'id' => $id, 'new' => $new];
}

/** toolbox call on a known sandbox id */
function dyt_tb(array $cfg, string $id, string $method, string $path, $body = null, int $timeout = 60): array {
    return dyt_http($cfg, $method, 'https://proxy.app.daytona.io/toolbox/' . rawurlencode($id) . $path, $body, $timeout);
}

/** toolbox call for a sid: ensures the sandbox, and retries once if it had stopped in between */
function dyt_call(array $cfg, string $sid, string $method, string $path, $body = null, int $timeout = 60, bool $create = true): array {
    $e = dyt_ensure($cfg, $sid, $create);
    if (empty($e['ok'])) { return [0, null, '', (string)($e['error'] ?? 'sandbox unavailable'), !empty($e['none']) ? 'NONE' : '']; }
    $r = dyt_tb($cfg, $e['id'], $method, $path, $body, $timeout);
    if ($r[4] === 'SANDBOX_NOT_RUNNING' || ($r[0] === 404 && stripos($r[3], 'sandbox') !== false)) {
        $w = dyt_wait_started($cfg, $e['id'], 45);
        if (!empty($w['ok'])) { $r = dyt_tb($cfg, $e['id'], $method, $path, $body, $timeout); }
    }
    return $r;
}

function dyt_abs(string $rel): string {
    $rel = ltrim(str_replace("\0", '', $rel), '/');
    return $rel === '' || $rel === '.' ? DYT_WORK : DYT_WORK . '/' . $rel;
}

/* ═════════════ provider contract ═════════════ */

function dyt_health(array $cfg): array {
    return ['ok' => trim((string)($cfg['daytona_api_key'] ?? '')) !== '', 'provider' => 'daytona'];
}

function dyt_open(array $cfg, string $sid): array {
    $e = dyt_ensure($cfg, $sid, true);
    return !empty($e['ok']) ? ['ok' => true, 'sid' => $sid, 'workdir' => DYT_WORK] : ['ok' => false, 'error' => (string)($e['error'] ?? '')];
}

function dyt_exec(array $cfg, string $sid, string $cmd, int $timeout = 80, bool $background = false, float $wait = 3.0, string $cwd = ''): array {
    $cwd = $cwd === '' ? DYT_WORK : ($cwd[0] === '/' ? $cwd : DYT_WORK . '/' . $cwd);
    $t0 = microtime(true);
    if ($background) {
        $log = '/home/user/.bg/' . bin2hex(random_bytes(4)) . '.log';
        $w = max(0.0, min($wait, 15.0));
        $sh = 'mkdir -p /home/user/.bg; nohup setsid bash -lc ' . escapeshellarg($cmd) . ' > ' . $log . ' 2>&1 < /dev/null & echo "__PID=$!"; '
            . 'sleep ' . sprintf('%.1f', $w) . '; tail -c 6000 ' . $log . ' 2>/dev/null';
        [$st, $j, , $err] = dyt_call($cfg, $sid, 'POST', '/process/execute', (string)json_encode(['command' => 'bash -c ' . escapeshellarg($sh), 'cwd' => $cwd, 'timeout' => (int)ceil($w) + 20]), (int)ceil($w) + 30);
        if ($st !== 200 || !is_array($j)) { return ['ok' => false, 'error' => dyt_friendly_error($err, 'exec failed')]; }
        $res = (string)($j['result'] ?? '');
        $pid = preg_match('/__PID=(\d+)/', $res, $m) ? $m[1] : '';
        $out = trim((string)preg_replace('/^.*?__PID=\d+\r?\n?/s', '', $res));
        return ['ok' => (int)($j['exitCode'] ?? 0) === 0, 'background' => true, 'pid' => $pid, 'log' => $log, 'output' => $out, 'ms' => (int)((microtime(true) - $t0) * 1000)];
    }
    $t = max(1, min($timeout, 900));
    $sh = 'timeout -k 5 ' . $t . ' bash -lc ' . escapeshellarg($cmd);
    [$st, $j, , $err, $code] = dyt_call($cfg, $sid, 'POST', '/process/execute', (string)json_encode(['command' => 'bash -c ' . escapeshellarg($sh), 'cwd' => $cwd, 'timeout' => $t + 10]), $t + 20);
    if ($code === 'PROCESS_EXECUTION_TIMEOUT' || $code === 'TIMEOUT') {
        return ['ok' => true, 'exit_code' => 124, 'timed_out' => true, 'stdout' => '', 'stderr' => 'timed out after ' . $t . 's', 'ms' => (int)((microtime(true) - $t0) * 1000)];
    }
    if ($st !== 200 || !is_array($j)) { return ['ok' => false, 'error' => dyt_friendly_error($err, 'exec failed')]; }
    $rc = (int)($j['exitCode'] ?? 0);
    $out = (string)($j['result'] ?? '');
    $trunc = false;
    if (strlen($out) > 200000) { $out = substr($out, 0, 20000) . "\n…[output cut]…\n" . substr($out, -150000); $trunc = true; }
    return ['ok' => true, 'exit_code' => $rc, 'timed_out' => $rc === 124, 'stdout' => $out, 'stderr' => '', 'truncated' => $trunc, 'ms' => (int)((microtime(true) - $t0) * 1000)];
}

function dyt_files(array $cfg, string $sid, string $path = '.', int $depth = 4): array {
    return sbx_files_via(static function (string $sh, int $t) use ($cfg, $sid) { return dyt_exec($cfg, $sid, $sh, $t); }, DYT_WORK, dyt_abs($path), $depth);
}

/** file listing through any provider's exec: $exec(string $sh, int $timeout) → exec result */
function sbx_files_via(callable $exec, string $work, string $abs, int $depth = 4): array {
    $depth = max(1, min($depth, 8));
    $prune = [];
    foreach (DYT_SKIP_DIRS as $d) { $prune[] = '-name ' . escapeshellarg($d); }
    /* one line per entry: type \t size \t mtime \t path-relative-to-workdir \t skipped */
    $sh = 'cd ' . escapeshellarg($work) . ' 2>/dev/null || exit 3; test -d ' . escapeshellarg($abs) . ' || { echo __NOTDIR; exit 0; }; '
        . 'find ' . escapeshellarg($abs) . ' -mindepth 1 -maxdepth ' . $depth . ' -type d \( ' . implode(' -o ', $prune) . ' \) -printf "d\t0\t%T@\t%p\t1\n" -prune '
        . '-o \( -type d -printf "d\t0\t%T@\t%p\t0\n" \) -o \( -type f -printf "f\t%s\t%T@\t%p\t0\n" \) -o \( -type l -printf "f\t0\t%T@\t%p\t0\n" \) 2>/dev/null | head -n 3001';
    $r = $exec($sh, 30);
    if (empty($r['ok'])) { return ['ok' => false, 'error' => (string)($r['error'] ?? 'list failed'), 'entries' => []]; }
    $out = (string)($r['stdout'] ?? '');
    if (strpos($out, '__NOTDIR') !== false) { return ['ok' => false, 'error' => 'not a directory', 'entries' => []]; }
    $entries = [];
    foreach (preg_split('/\r?\n/', $out) as $line) {
        $c = explode("\t", $line);
        if (count($c) < 5) { continue; }
        $p = $c[3];
        if (strpos($p, $work . '/') === 0) { $p = substr($p, strlen($work) + 1); }
        if ($c[0] === 'd') { $entries[] = ['path' => $p, 'type' => 'dir', 'skipped' => $c[4] === '1']; }
        else { $entries[] = ['path' => $p, 'type' => 'file', 'size' => (int)$c[1], 'mtime' => (int)$c[2]]; }
    }
    usort($entries, static function ($a, $b) { return strcmp($a['path'], $b['path']); });
    return ['ok' => true, 'root' => $work, 'entries' => array_slice($entries, 0, 3000), 'truncated' => count($entries) > 3000];
}

function dyt_mime(string $path): string {
    static $m = ['html' => 'text/html', 'htm' => 'text/html', 'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript', 'json' => 'application/json',
        'txt' => 'text/plain', 'md' => 'text/markdown', 'csv' => 'text/csv', 'xml' => 'application/xml', 'svg' => 'image/svg+xml', 'png' => 'image/png',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'pdf' => 'application/pdf',
        'zip' => 'application/zip', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'py' => 'text/x-python',
        'php' => 'text/x-php', 'ts' => 'text/plain', 'tsx' => 'text/plain', 'jsx' => 'text/plain', 'sh' => 'text/x-shellscript', 'yml' => 'text/yaml', 'yaml' => 'text/yaml'];
    return $m[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}

function dyt_read(array $cfg, string $sid, string $path): array {
    if ($path === '' || $path === '.') { return ['ok' => false, 'error' => 'path required', 'status' => 400]; }
    [$st, , $body, $err, $code] = dyt_call($cfg, $sid, 'GET', '/files/download?' . http_build_query(['path' => dyt_abs($path)]), null, 60, false);
    if ($code === 'NONE') { return ['ok' => false, 'error' => 'file not found', 'status' => 404]; }
    if ($st === 404 || $code === 'FILE_NOT_FOUND') { return ['ok' => false, 'error' => 'file not found', 'status' => 404]; }
    if ($st !== 200) { return ['ok' => false, 'error' => dyt_friendly_error($err, 'read failed'), 'status' => $st]; }
    if (strlen($body) > 25 * 1024 * 1024) { return ['ok' => false, 'error' => 'file is larger than 25 MB', 'status' => 413]; }
    return ['ok' => true, 'data' => $body, 'type' => dyt_mime($path)];
}

function dyt_write(array $cfg, string $sid, string $path, string $data): array {
    if ($path === '' || $path === '.') { return ['ok' => false, 'error' => 'path required']; }
    if (strlen($data) > 25 * 1024 * 1024) { return ['ok' => false, 'error' => 'file is larger than 25 MB']; }
    $file = class_exists('CURLStringFile') ? new CURLStringFile($data, basename($path), 'application/octet-stream') : null;
    if ($file === null) {
        $tmp = tempnam(sys_get_temp_dir(), 'dyt');
        file_put_contents($tmp, $data);
        $file = new CURLFile($tmp, 'application/octet-stream', basename($path));
    }
    [$st, , , $err] = dyt_call($cfg, $sid, 'POST', '/files/upload?' . http_build_query(['path' => dyt_abs($path)]), ['file' => $file], 120);
    if (isset($tmp)) { @unlink($tmp); }
    if ($st < 200 || $st >= 300) { return ['ok' => false, 'error' => dyt_friendly_error($err, 'write failed')]; }
    return ['ok' => true, 'path' => ltrim($path, '/'), 'size' => strlen($data)];
}

function dyt_delete(array $cfg, string $sid, string $path): array {
    $abs = dyt_abs($path);
    if ($abs === DYT_WORK || strpos($path, '..') !== false) { return ['ok' => false, 'error' => 'refusing to delete the workspace root']; }
    $r = dyt_exec($cfg, $sid, 'test -e ' . escapeshellarg($abs) . ' -o -L ' . escapeshellarg($abs) . ' || { echo __NF; exit 0; }; rm -rf -- ' . escapeshellarg($abs), 30);
    if (empty($r['ok'])) { return ['ok' => false, 'error' => (string)($r['error'] ?? 'delete failed')]; }
    if (strpos((string)($r['stdout'] ?? ''), '__NF') !== false) { return ['ok' => false, 'error' => 'not found']; }
    return ['ok' => true];
}

/** public preview URL for a port: https://{port}-{id}.<proxy domain> (domain learned once from the API) */
function dyt_preview_url(array $cfg, string $id, int $port): string {
    $dom = dyt_map_get('_preview_domain');
    if ($dom === '') {
        [$st, $j] = dyt_http($cfg, 'GET', dyt_api($cfg) . '/sandbox/' . rawurlencode($id) . '/ports/' . $port . '/preview-url', null, 15);
        if ($st === 200 && !empty($j['url'])) {
            $h = (string)parse_url((string)$j['url'], PHP_URL_HOST);
            $pre = $port . '-' . $id . '.';
            if (strpos($h, $pre) === 0) { $dom = substr($h, strlen($pre)); dyt_map_set('_preview_domain', $dom); }
            else { return (string)$j['url']; }
        }
    }
    return $dom !== '' ? 'https://' . $port . '-' . $id . '.' . $dom : '';
}

function dyt_ports(array $cfg, string $sid): array {
    $e = dyt_ensure($cfg, $sid, false, false);   /* just looking: never create or wake a sandbox for this */
    if (empty($e['ok'])) { return ['ok' => true, 'ports' => []]; }
    [$st, $j, , $err] = dyt_tb($cfg, $e['id'], 'POST', '/process/execute', (string)json_encode(['command' => 'ss -ltnH', 'timeout' => 15]), 25);
    if ($st !== 200 || !is_array($j)) { return ['ok' => false, 'error' => dyt_friendly_error($err, 'ports failed'), 'ports' => []]; }
    $ports = [];
    foreach (preg_split('/\r?\n/', (string)($j['result'] ?? '')) as $line) {
        $c = preg_split('/\s+/', trim($line));
        if (count($c) < 4) { continue; }
        $pos = strrpos($c[3], ':');
        if ($pos === false) { continue; }
        $addr = substr($c[3], 0, $pos); $p = (int)substr($c[3], $pos + 1);
        if ($p < 1024 || $p > 65535 || in_array($p, DYT_INTERNAL_PORTS, true)) { continue; }
        $lo = in_array($addr, ['127.0.0.1', '[::1]', '::1'], true);
        $ports[$p] = ($ports[$p] ?? true) && $lo;
    }
    ksort($ports);
    $list = [];
    foreach ($ports as $p => $lo) {
        $row = ['port' => $p, 'url' => dyt_preview_url($cfg, $e['id'], $p), 'localhost_only' => $lo];
        if ($lo) { $row['hint'] = 'bound to 127.0.0.1 only; restart it with host 0.0.0.0 for the preview'; }
        $list[] = $row;
    }
    return ['ok' => true, 'ports' => $list];
}

function dyt_zip(array $cfg, string $sid): array {
    $ex = [];
    foreach (DYT_SKIP_DIRS as $d) { $ex[] = escapeshellarg('*/' . $d . '/*'); $ex[] = escapeshellarg($d . '/*'); }
    $tmp = '/tmp/devil-workspace-' . bin2hex(random_bytes(3)) . '.zip';
    $r = dyt_exec($cfg, $sid, 'cd ' . DYT_WORK . ' && rm -f ' . $tmp . ' && (zip -qr -y ' . $tmp . ' . -x ' . implode(' ', $ex) . ' || true) && test -f ' . $tmp . ' || (cd ' . DYT_WORK . ' && python3 -c "import zipfile;zipfile.ZipFile(\'' . $tmp . '\',\'w\').close()")', 120);
    if (empty($r['ok'])) { return ['ok' => false, 'error' => (string)($r['error'] ?? 'zip failed')]; }
    [$st, , $body, $err] = dyt_call($cfg, $sid, 'GET', '/files/download?' . http_build_query(['path' => $tmp]), null, 120);
    dyt_exec($cfg, $sid, 'rm -f ' . $tmp, 10);
    return $st === 200 ? ['ok' => true, 'data' => $body] : ['ok' => false, 'error' => dyt_friendly_error($err, 'zip download failed')];
}

/** delete the sandbox of a chat (chat deleted) */
function dyt_purge(array $cfg, string $sid): array {
    $id = dyt_map_get($sid);
    if ($id === '') { $f = dyt_find($cfg, $sid); $id = $f ? (string)$f['id'] : ''; }
    if ($id === '') { return ['ok' => true]; }
    [$st, , , $err] = dyt_http($cfg, 'DELETE', dyt_api($cfg) . '/sandbox/' . rawurlencode($id), null, 15);
    dyt_map_set($sid, null);
    return ($st >= 200 && $st < 300) || $st === 404 ? ['ok' => true] : ['ok' => false, 'error' => $err];
}
