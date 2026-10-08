<?php
/**
 * Devil AI — Agent sandbox (inc/sandbox.php)
 *
 * Provider-neutral client for the per-chat Linux sandbox used by Agent Mode
 * (bash, files, previews, headless browser). Provider today: "devil" — the
 * GitHub-Actions-hosted Devil Sandbox (github.com/devilsandbox/Sandbox).
 * An E2B provider can be added behind the same sbx_* functions.
 *
 * Every call is signed with HMAC-SHA256(sandbox_secret). The sandbox id is
 * derived server-side from (user id, chat id), so a user can only ever reach
 * the sandbox of their own chats.
 */
declare(strict_types=1);

const SBX_WORKDIR = '/home/user/work';

function sbx_enabled(array $cfg): bool {
    return !empty($cfg['sandbox_enabled']) && trim((string)($cfg['sandbox_url'] ?? '')) !== '' && trim((string)($cfg['sandbox_secret'] ?? '')) !== '';
}

/** stable, unguessable sandbox id for (user, chat) */
function sbx_sid(array $cfg, string $uid, string $chatId): string {
    $key = (string)($cfg['sandbox_secret'] ?? '');
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
    $r = sbx_request($cfg, 'GET', '/v1/health', '', 10);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'offline'];
}
function sbx_open(array $cfg, string $sid): array {
    $r = sbx_request($cfg, 'POST', '/v1/s/' . $sid, '', 60);
    return $r['ok'] ? (array)$r['json'] : ['ok' => false, 'error' => $r['error']];
}
function sbx_exec(array $cfg, string $sid, string $cmd, int $timeout = 80, bool $background = false, float $wait = 3.0, string $cwd = ''): array {
    $payload = ['cmd' => $cmd, 'timeout' => $timeout, 'background' => $background, 'wait' => $wait];
    if ($cwd !== '') { $payload['cwd'] = $cwd; }
    $r = sbx_request($cfg, 'POST', '/v1/s/' . $sid . '/exec', (string)json_encode($payload), $timeout + 12);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'exec failed'];
}
function sbx_files(array $cfg, string $sid, string $path = '.', int $depth = 4): array {
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/files?' . sbx_q(['path' => $path, 'depth' => $depth]), '', 30);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'list failed', 'entries' => []];
}
function sbx_read(array $cfg, string $sid, string $path): array {
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/file?' . sbx_q(['path' => $path]), '', 60);
    return $r['ok'] ? ['ok' => true, 'data' => $r['body'], 'type' => (string)($r['headers']['content-type'] ?? 'application/octet-stream')] : ['ok' => false, 'error' => $r['error'] ?: 'read failed', 'status' => $r['status']];
}
function sbx_write(array $cfg, string $sid, string $path, string $data): array {
    $r = sbx_request($cfg, 'PUT', '/v1/s/' . $sid . '/file?' . sbx_q(['path' => $path]), $data, 120, ['Content-Type: application/octet-stream']);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'] ?: 'write failed'];
}
function sbx_delete(array $cfg, string $sid, string $path): array {
    $r = sbx_request($cfg, 'DELETE', '/v1/s/' . $sid . '/file?' . sbx_q(['path' => $path]), '', 30);
    return $r['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $r['error']];
}
function sbx_ports(array $cfg, string $sid): array {
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/ports', '', 20);
    return $r['ok'] && is_array($r['json']) ? $r['json'] : ['ok' => false, 'error' => $r['error'], 'ports' => []];
}
function sbx_zip(array $cfg, string $sid): array {
    $r = sbx_request($cfg, 'GET', '/v1/s/' . $sid . '/zip', '', 120);
    return $r['ok'] ? ['ok' => true, 'data' => $r['body']] : ['ok' => false, 'error' => $r['error']];
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

function sbx_agent_tools(): array {
    return [
        'bash'           => 'Run a shell command in your Linux sandbox (cwd /home/user/work). Output and exit code come back. A command that is still running after ~55s keeps running in the background and you get its pid and log file — check it later with tail. Use start_server (not bash) for servers. INPUT: the command(s); several lines are fine.',
        'write_file'     => 'Create or overwrite a file. INPUT: first line = path (relative to /home/user/work), then the full file content on the following lines.',
        'read_file'      => 'Read a text file from the sandbox. INPUT: path.',
        'list_files'     => 'List files in the workspace. INPUT: a folder path, or "." for everything.',
        'start_server'   => 'Start a long-running app/dev server in the background and get its public preview URL. INPUT: first line = port, second line = command (bind to 0.0.0.0).',
        'browser'        => 'Open a page in a headless Chromium inside the sandbox (works for http://localhost:PORT too): returns title, visible text, console errors and saves a screenshot. INPUT: URL.',
        'generate_image' => 'Generate an image from a text prompt and save it in the workspace. INPUT: first line = output path (e.g. images/hero.png), second line = the prompt.',
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
    if ($err !== '') { $txt .= ($out !== '' ? "\n[stderr]\n" : '') . $err . "\n"; }
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
            /* The command runs as a detached job. We wait up to ~55s for it; if it is still going
               (npm install, a dev server in the foreground, a long build…) it keeps running in the
               background and the agent gets the pid + log path instead of a timeout. This keeps every
               agent step well under Cloudflare's 100s request limit. */
            $wait = max(10, min(55, (int)($cfg['agent_bash_wait'] ?? 55)));
            $job = '/home/user/.bg/cmd-' . bin2hex(random_bytes(4));
            $wrapper = 'mkdir -p /home/user/.bg; J=' . $job . '; echo ' . base64_encode($cmd) . ' | base64 -d > "$J.sh"; '
                . 'nohup setsid bash -c \'bash -l "$1" > "$1.log" 2>&1; echo $? > "$1.rc"\' _ "$J.sh" > /dev/null 2>&1 < /dev/null & P=$!; '
                . 'i=0; while [ ! -f "$J.sh.rc" ] && [ $i -lt ' . ($wait * 5) . ' ]; do sleep 0.2; i=$((i+1)); done; '
                . 'if [ -f "$J.sh.rc" ]; then sz=$(stat -c %s "$J.sh.log" 2>/dev/null || echo 0); '
                . 'if [ "$sz" -gt 14000 ]; then head -c 3000 "$J.sh.log"; echo; echo "… [$sz bytes of output, middle cut] …"; tail -c 10000 "$J.sh.log"; else cat "$J.sh.log"; fi; '
                . 'rc=$(cat "$J.sh.rc"); rm -f "$J.sh" "$J.sh.rc" "$J.sh.log"; exit $rc; '
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
        b = await p.chromium.launch(args=["--no-sandbox"])
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
            $cmd = "mkdir -p .devil && cat > .devil/browse.py <<'DEVILPY'\n" . $py . "\nDEVILPY\npython3 .devil/browse.py " . escapeshellarg($url) . ' ' . escapeshellarg($shot) . ' 2>&1 | tail -c 12000';
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
        case 'generate_image': {
            $lines = preg_split('/\r?\n/', trim($input), 2);
            $path = sbx_rel((string)($lines[0] ?? ''));
            $prompt = trim((string)($lines[1] ?? ''));
            if ($prompt === '' && !preg_match('/\.(png|jpe?g|webp)$/i', $path)) { $prompt = trim($input); $path = 'images/image-' . date('His') . '.png'; }
            if (!preg_match('/\.(png|jpe?g|webp)$/i', $path)) { $path = rtrim($path === '.' ? 'images' : $path, '/') . '/image-' . date('His') . '.png'; }
            if ($prompt === '') { return ['ok' => false, 'text' => 'generate_image: second line must be the prompt.']; }
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
