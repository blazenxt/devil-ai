<?php
/**
 * Devil AI — "Hosting (FTP / SFTP)": each user can connect their own web server, and the agent's
 * deploy_site tool uploads a folder from the sandbox to it.
 *
 *   data/hosting/{uid}.json   protocol, host, port, username, password (AES-256-GCM encrypted),
 *                             remote_dir (base folder for sites), public_url (URL of that folder)
 *   data/hosting/.key         random 32-byte key (created on first use, never leaves the server)
 *
 * Uploads run from this PHP server with libcurl (sftp:// / ftp:// / explicit FTPS). Sites always go
 * into a sub-folder of remote_dir (remote_dir/{site-name}/) and files are only ever added or
 * overwritten there — nothing on the server is deleted.
 */

function host_dir(): string {
    $d = dirname(__DIR__) . '/data/hosting';
    if (!is_dir($d)) { @mkdir($d, 0700, true); @file_put_contents($d . '/.htaccess', "Require all denied\n"); }
    return $d;
}
function host_key(): string {
    $f = host_dir() . '/.key';
    $k = is_file($f) ? (string)@file_get_contents($f) : '';
    if (strlen($k) !== 32) {
        $k = random_bytes(32);
        @file_put_contents($f, $k, LOCK_EX);
        @chmod($f, 0600);
    }
    return $k;
}
function host_encrypt(string $plain): string {
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', host_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'g1:' . base64_encode($iv . $tag . (string)$ct);
}
function host_decrypt(string $enc): string {
    if (strpos($enc, 'g1:') !== 0) { return ''; }
    $raw = base64_decode(substr($enc, 3), true);
    if ($raw === false || strlen($raw) < 29) { return ''; }
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', host_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? '' : $p;
}
function host_file(string $uid): string { return host_dir() . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', $uid) . '.json'; }

/** saved settings with the password decrypted, or null */
function host_load(string $uid): ?array {
    $f = host_file($uid);
    if ($uid === '' || !is_file($f)) { return null; }
    $j = json_decode((string)@file_get_contents($f), true);
    if (!is_array($j) || empty($j['host'])) { return null; }
    $j['password'] = host_decrypt((string)($j['password_enc'] ?? ''));
    unset($j['password_enc']);
    return $j;
}
/** what the settings page may see (never the password) */
function host_public(?array $h): ?array {
    if (!$h) { return null; }
    return ['protocol' => $h['protocol'], 'host' => $h['host'], 'port' => (int)$h['port'], 'username' => $h['username'],
        'remote_dir' => $h['remote_dir'], 'public_url' => $h['public_url'], 'has_password' => ($h['password'] ?? '') !== '', 'updated' => (int)($h['updated'] ?? 0)];
}
function host_caps(): array {
    $p = function_exists('curl_version') ? (array)(curl_version()['protocols'] ?? []) : [];
    return ['sftp' => in_array('sftp', $p, true), 'ftp' => in_array('ftp', $p, true), 'ftps' => in_array('ftps', $p, true)];
}

/** validate input from the settings form → [settings|null, error] (an empty password keeps the saved one) */
function host_validate(array $in, ?array $old): array {
    $proto = strtolower(trim((string)($in['protocol'] ?? 'sftp')));
    if (!in_array($proto, ['sftp', 'ftp', 'ftps'], true)) { return [null, 'Choose SFTP, FTP or FTPS.']; }
    $host = strtolower(trim((string)($in['host'] ?? '')));
    $host = preg_replace('#^[a-z]+://#', '', $host);
    $host = rtrim((string)preg_replace('#/.*$#', '', $host), '.');
    if ($host === '' || strlen($host) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$|^\d{1,3}(?:\.\d{1,3}){3}$/', $host)) { return [null, 'Enter a valid server host name or IP.']; }
    /* never let the form point at private / local networks */
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (array)@gethostbynamel($host);
    if (!$ips) { return [null, 'That host name does not resolve.']; }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return [null, 'Private or local addresses are not allowed.']; }
    }
    $port = (int)($in['port'] ?? 0);
    if ($port <= 0) { $port = $proto === 'sftp' ? 22 : 21; }
    if ($port > 65535) { return [null, 'Port must be 1-65535.']; }
    $user = trim((string)($in['username'] ?? ''));
    if ($user === '' || strlen($user) > 128 || preg_match('/[\s:@\/]/', $user)) { return [null, 'Enter the login user name.']; }
    $pass = (string)($in['password'] ?? '');
    if ($pass === '' && $old && ($old['host'] ?? '') === $host && ($old['username'] ?? '') === $user) { $pass = (string)($old['password'] ?? ''); }
    if ($pass === '' || strlen($pass) > 256) { return [null, 'Enter the password.']; }
    $dir = trim((string)($in['remote_dir'] ?? ''));
    $dir = '/' . trim(str_replace('\\', '/', $dir), '/');
    if (strpos($dir, '..') !== false || strlen($dir) > 400) { return [null, 'Invalid remote folder.']; }
    $url = trim((string)($in['public_url'] ?? ''));
    if ($url !== '' && !preg_match('#^https?://[^\s"<>]+$#i', $url)) { return [null, 'Public URL must start with http:// or https://']; }
    return [['protocol' => $proto, 'host' => $host, 'port' => $port, 'username' => $user, 'password' => $pass,
        'remote_dir' => $dir, 'public_url' => rtrim($url, '/'), 'updated' => time()], ''];
}
function host_save(string $uid, array $h): bool {
    $row = $h; $row['password_enc'] = host_encrypt((string)$h['password']); unset($row['password']);
    $ok = @file_put_contents(host_file($uid), json_encode($row, JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    @chmod(host_file($uid), 0600);
    return $ok;
}
function host_delete(string $uid): void { @unlink(host_file($uid)); }

/* ── libcurl plumbing ── */
function host_url(array $h, string $path): string {
    $segs = array_map('rawurlencode', array_values(array_filter(explode('/', $path), 'strlen')));
    $trail = substr($path, -1) === '/' ? '/' : '';
    if ($h['protocol'] === 'sftp') {
        return 'sftp://' . $h['host'] . ':' . (int)$h['port'] . '/' . implode('/', $segs) . ($segs ? $trail : '');
    }
    /* FTP: an absolute path needs %2F after the host (otherwise it is relative to the login folder) */
    return 'ftp://' . $h['host'] . ':' . (int)$h['port'] . '/%2F' . implode('/', $segs) . ($segs ? $trail : '');
}
function host_curl_base(array $h) {
    $ch = curl_init();
    $o = [CURLOPT_USERPWD => $h['username'] . ':' . $h['password'], CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 60,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FTP_CREATE_MISSING_DIRS => defined('CURLFTP_CREATE_DIR_RETRY') ? CURLFTP_CREATE_DIR_RETRY : 2];
    if ($h['protocol'] === 'sftp' && defined('CURLOPT_SSH_AUTH_TYPES')) { $o[CURLOPT_SSH_AUTH_TYPES] = CURLSSH_AUTH_PASSWORD | CURLSSH_AUTH_KEYBOARD; }
    if ($h['protocol'] === 'ftps') { $o[CURLOPT_USE_SSL] = CURLUSESSL_ALL; $o[CURLOPT_SSL_VERIFYPEER] = false; $o[CURLOPT_SSL_VERIFYHOST] = 0; }
    if ($h['protocol'] !== 'sftp') { $o[CURLOPT_FTP_USE_EPSV] = true; }
    curl_setopt_array($ch, $o);
    return $ch;
}
function host_err(string $e): string {
    $e = trim($e);
    if (preg_match('/Login denied|Authentication failure|auth|530/i', $e)) { return 'Login failed — check the user name and password.'; }
    if (preg_match('/timed out|Connection refused|Couldn\'t connect|resolve/i', $e)) { return 'Could not reach the server — check host and port (' . $e . ').'; }
    return $e !== '' ? $e : 'unknown error';
}

/** try to log in and list the base folder */
function host_test(array $h): array {
    $caps = host_caps();
    if (empty($caps[$h['protocol']])) { return ['ok' => false, 'error' => strtoupper($h['protocol']) . ' is not supported by this server\'s PHP.']; }
    $ch = host_curl_base($h);
    curl_setopt_array($ch, [CURLOPT_URL => host_url($h, rtrim($h['remote_dir'], '/') . '/'), CURLOPT_DIRLISTONLY => true, CURLOPT_TIMEOUT => 25]);
    $out = curl_exec($ch); $err = curl_error($ch); $no = curl_errno($ch);
    curl_close($ch);
    if ($out === false || $no) {
        /* the folder may simply not exist yet: check the login with the root folder */
        if ($no === 78 || preg_match('/No such file|not found|550/i', $err)) {
            $ch = host_curl_base($h);
            curl_setopt_array($ch, [CURLOPT_URL => host_url($h, '/'), CURLOPT_DIRLISTONLY => true, CURLOPT_TIMEOUT => 25]);
            $o2 = curl_exec($ch); $e2 = curl_error($ch); curl_close($ch);
            if ($o2 !== false && $e2 === '') { return ['ok' => true, 'note' => 'Login works. The folder ' . $h['remote_dir'] . ' does not exist yet — it will be created on the first deploy.', 'entries' => []]; }
        }
        return ['ok' => false, 'error' => host_err($err)];
    }
    $names = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)$out)), static function ($n) { return $n !== '' && $n !== '.' && $n !== '..'; }));
    return ['ok' => true, 'entries' => array_slice($names, 0, 30), 'count' => count($names)];
}

/**
 * upload every file under $localDir to remote_dir/$site/… — resumable: files already sent for the
 * same bundle ($bundleId) are skipped, and the call stops before $deadline (returns done=false).
 */
function host_upload_dir(array $h, string $localDir, string $site, string $bundleId, float $deadline): array {
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($localDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || $f->isLink()) { continue; }
        $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($localDir))), '/');
        if ($rel === '' || strpos($rel, '..') !== false) { continue; }
        $files[$rel] = $f->getPathname();
    }
    ksort($files);
    /* Apache hosts often only list index.php as the folder index: make index.html work too */
    if (!isset($files['.htaccess']) && (isset($files['index.html']) || isset($files['index.htm']))) {
        $tmp = $localDir . '/.htaccess';
        @file_put_contents($tmp, "DirectoryIndex index.html index.htm index.php\n");
        $files = ['.htaccess' => $tmp] + $files;
    }
    $progFile = host_dir() . '/progress_' . hash('sha256', $bundleId . '|' . $site) . '.json';
    $done = is_file($progFile) ? (array)json_decode((string)@file_get_contents($progFile), true) : [];
    $base = rtrim($h['remote_dir'], '/') . '/' . $site . '/';
    $ch = host_curl_base($h);
    $sent = 0; $bytes = 0; $failed = []; $stopped = false;
    foreach ($files as $rel => $abs) {
        if (!empty($done[$rel])) { continue; }
        if (microtime(true) > $deadline) { $stopped = true; break; }
        $fp = fopen($abs, 'rb');
        if (!$fp) { $failed[$rel] = 'cannot read'; continue; }
        $size = (int)filesize($abs);
        curl_setopt_array($ch, [CURLOPT_URL => host_url($h, $base . $rel), CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fp, CURLOPT_INFILESIZE => $size, CURLOPT_DIRLISTONLY => false]);
        $ok = curl_exec($ch) !== false && curl_errno($ch) === 0;
        $err = $ok ? '' : curl_error($ch);
        fclose($fp);
        if ($ok) { $done[$rel] = 1; $sent++; $bytes += $size; }
        else {
            $failed[$rel] = host_err($err);
            if (count($failed) >= 3 && $sent === 0) { break; }   /* login/permission problem: stop early */
        }
    }
    curl_close($ch);
    $allDone = !$stopped && !$failed && count($done) >= count($files);
    if ($allDone) { @unlink($progFile); } else { @file_put_contents($progFile, json_encode($done), LOCK_EX); }
    return ['ok' => !$failed || $sent > 0, 'done' => $allDone, 'total' => count($files), 'uploaded_now' => $sent, 'uploaded_total' => count($done), 'bytes' => $bytes,
        'failed' => array_slice($failed, 0, 8, true), 'remote' => $base, 'url' => $h['public_url'] !== '' ? $h['public_url'] . '/' . $site . '/' : ''];
}

function host_rrmdir(string $d): void {
    if (!is_dir($d)) { return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($d);
}

/** the agent tool: bundle a sandbox folder, bring it here, upload it to the user's server */
function host_deploy_tool(array $cfg, string $sid, string $uid, string $input): array {
    $h = host_load($uid);
    if (!$h) {
        return ['ok' => false, 'text' => 'No hosting server is connected for this user. Ask the user (ask_user) to open Settings → "Hosting (FTP / SFTP)", add their server (host, port, user, password, remote folder, public URL) and press Save — then call deploy_site again. Do not ask them to paste passwords in the chat.'];
    }
    $caps = host_caps();
    if (empty($caps[$h['protocol']])) { return ['ok' => false, 'text' => strtoupper($h['protocol']) . ' uploads are not supported on this server. Ask the user to switch the hosting protocol in Settings.']; }
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', trim(sbx_unfence($input)))), 'strlen'));
    $folder = sbx_rel((string)($lines[0] ?? '.'));
    if (strpos($folder, '..') !== false) { return ['ok' => false, 'text' => 'deploy_site: the folder must be inside /home/user/work.']; }
    $site = strtolower(trim((string)($lines[1] ?? '')));
    if ($site === '') { $site = $folder === '.' ? 'site' : basename($folder); if (in_array($site, ['dist', 'build', 'out', 'public', 'www'], true)) { $site = basename(dirname($folder)) ?: 'site'; } }
    $site = trim((string)preg_replace('/[^a-z0-9-]+/', '-', $site), '-');
    if ($site === '' || $site === '.') { $site = 'site'; }
    $site = substr($site, 0, 60);

    /* 1. bundle the folder inside the sandbox (no node_modules / .git / .env files) */
    $tgz = '.devil/deploy-' . bin2hex(random_bytes(4)) . '.tgz';
    $dirQ = escapeshellarg('/home/user/work/' . ($folder === '.' ? '' : $folder));
    $cmd = 'cd ' . $dirQ . ' 2>/dev/null || { echo "__NOFOLDER__"; exit 0; }; mkdir -p /home/user/work/.devil; '
        . 'find . -type f -not -path "./node_modules/*" -not -path "*/node_modules/*" -not -path "./.git/*" -not -path "./.devil/*" -not -name ".env" -not -name ".env.*" -not -name "*.pyc" -print0 > /tmp/.devil_list; '
        . 'N=$(tr -cd "\\0" < /tmp/.devil_list | wc -c); tar czf /home/user/work/' . $tgz . ' --null -T /tmp/.devil_list 2>/dev/null; '
        . 'echo "__N=$N __S=$(stat -c %s /home/user/work/' . $tgz . ' 2>/dev/null || echo 0)"; test -f index.html && echo __INDEX__';
    $r = sbx_exec($cfg, $sid, $cmd, 60);
    $o = (string)($r['stdout'] ?? '');
    if (strpos($o, '__NOFOLDER__') !== false) { return ['ok' => false, 'text' => "deploy_site: folder '{$folder}' does not exist in /home/user/work."]; }
    if (!preg_match('/__N=(\d+) __S=(\d+)/', $o, $m)) { return ['ok' => false, 'text' => 'deploy_site: could not bundle the folder: ' . trim(($r['error'] ?? '') . ' ' . ($r['stderr'] ?? ''))]; }
    $n = (int)$m[1]; $size = (int)$m[2];
    $cleanup = static function () use ($cfg, $sid, $tgz) { sbx_exec($cfg, $sid, 'rm -f /home/user/work/' . $tgz, 15); };
    if ($n === 0) { $cleanup(); return ['ok' => false, 'text' => "deploy_site: '{$folder}' has no files."]; }
    if ($n > 2000) { $cleanup(); return ['ok' => false, 'text' => "deploy_site: {$n} files is too many (max 2000). Deploy the built output folder (e.g. dist/) instead of the whole project."]; }
    if ($size > 24 * 1024 * 1024) { $cleanup(); return ['ok' => false, 'text' => 'deploy_site: the bundle is ' . round($size / 1048576, 1) . ' MB (max 24 MB). Deploy only the built site (e.g. dist/), and compress big images/videos.']; }
    $warnNoIndex = strpos($o, '__INDEX__') === false && !preg_match('/\.php/i', $o);

    /* 2. bring it to this server and unpack */
    $rd = sbx_read($cfg, $sid, $tgz);
    $cleanup();
    if (empty($rd['ok'])) { return ['ok' => false, 'text' => 'deploy_site: could not read the bundle: ' . (string)($rd['error'] ?? '')]; }
    $work = sys_get_temp_dir() . '/devil_deploy_' . bin2hex(random_bytes(6));
    @mkdir($work, 0700, true);
    $arc = $work . '.tar.gz';
    file_put_contents($arc, (string)$rd['data']);
    try {
        $pd = new PharData($arc);
        foreach (new RecursiveIteratorIterator($pd) as $entry) {
            $name = str_replace('phar://' . $arc . '/', '', (string)$entry->getPathname());
            if (strpos($name, '..') !== false) { throw new Exception('unsafe path in bundle'); }
        }
        $pd->extractTo($work, null, true);
    } catch (Throwable $e) {
        @unlink($arc); host_rrmdir($work);
        return ['ok' => false, 'text' => 'deploy_site: could not unpack the bundle: ' . $e->getMessage()];
    }
    @unlink($arc);

    /* 3. upload (resumable within the step time budget) */
    $deadline = (float)($GLOBALS['DEVIL_DEADLINE'] ?? (microtime(true) + 70)) - 6;
    $bundleId = hash('sha256', (string)$rd['data']);
    $u = host_upload_dir($h, $work, $site, $bundleId, $deadline);
    host_rrmdir($work);

    $where = strtoupper($h['protocol']) . ' ' . $h['host'] . ':' . $u['remote'];
    $meta = ['deploy' => ['site' => $site, 'files' => $u['uploaded_total'], 'total' => $u['total'], 'url' => $u['url'], 'where' => $where, 'done' => $u['done']]];
    if (!$u['ok']) {
        $f = []; foreach ($u['failed'] as $k => $v) { $f[] = "- {$k}: {$v}"; }
        return ['ok' => false, 'text' => "Deploy failed ({$where}).\n" . implode("\n", $f) . "\nIf it is a login or permission problem, ask the user to check Settings → Hosting.", 'meta' => $meta];
    }
    if (!$u['done']) {
        $t = "Uploaded {$u['uploaded_total']} of {$u['total']} files so far to {$where}. Call deploy_site again with the same input to upload the rest (finished files are skipped).";
        if ($u['failed']) { $t .= "\nFailed:\n"; foreach ($u['failed'] as $k => $v) { $t .= "- {$k}: {$v}\n"; } }
        return ['ok' => true, 'text' => $t, 'meta' => $meta];
    }
    $t = "Deployed {$u['total']} files to {$where}" . ($u['url'] !== '' ? "\nLive URL: {$u['url']}" : "\n(No public URL is set in Settings → Hosting, so I cannot tell the web address — ask the user.)");
    if ($warnNoIndex) { $t .= "\nNote: there is no index.html in the folder root, so the bare URL may not show a page."; }
    $t .= "\nOpen the live URL with the browser tool to verify, then give the user the link.";
    return ['ok' => true, 'text' => $t, 'meta' => $meta];
}
