<?php
/* ══════════════════════════════════════════════════════════════════════════
   Google Drive connector for Devil Agent  —  no Composer, no vendor folder.

   Everything here is plain cURL, and the service-account JWT is signed with
   openssl (RS256). Two ways to authenticate, whichever the site owner sets in
   Admin → Integrations:

     1. Service account  — paste the JSON key file. No browser consent needed;
        the owner shares a Drive folder with the service-account e-mail.
     2. OAuth refresh token — client id + secret + a refresh token obtained
        once through the consent screen. Sees the whole Drive of that account.

   Until credentials are saved every tool answers "not configured" with a short
   pointer, so the agent degrades gracefully instead of failing weirdly.

   ══════════════════════════════════════════════════════════════════════════ */
declare(strict_types=1);

if (!function_exists('drive_api')) {

/* ── configuration ─────────────────────────────────────────────────────── */

/** full-drive scope; a shared-folder-only install could narrow this later */
function drive_scope(): string { return 'https://www.googleapis.com/auth/drive'; }

/** the saved credentials, normalised (service account decoded once) */
function drive_creds(array $cfg): array {
    $sa = $cfg['drive_service_account'] ?? '';
    if (is_array($sa)) { $sa = json_encode($sa, JSON_UNESCAPED_UNICODE); }
    $saArr = null;
    if (is_string($sa) && trim($sa) !== '') {
        $j = json_decode($sa, true);
        if (is_array($j) && !empty($j['client_email']) && !empty($j['private_key'])) { $saArr = $j; }
    }
    return [
        'sa'       => $saArr,
        'id'       => trim((string)($cfg['drive_client_id'] ?? '')),
        'secret'   => trim((string)($cfg['drive_client_secret'] ?? '')),
        'refresh'  => trim((string)($cfg['drive_refresh_token'] ?? '')),
        'root'     => trim((string)($cfg['drive_root_folder'] ?? '')),
    ];
}

function drive_configured(array $cfg): bool {
    if (!function_exists('curl_init')) { return false; }
    $c = drive_creds($cfg);
    if (is_array($c['sa'])) { return true; }
    return $c['id'] !== '' && $c['secret'] !== '' && $c['refresh'] !== '';
}

/** shown to the agent (and in Admin) when nothing is set up yet */
function drive_setup_hint(array $cfg): string {
    if (!function_exists('curl_init')) { return 'Google Drive is unavailable: this server has no cURL.'; }
    return 'Google Drive is not connected yet. Add credentials in Admin → Integrations → Google Drive '
         . '(either a service-account JSON key, or an OAuth client id + secret + refresh token), then retry.';
}

/* ── token handling ────────────────────────────────────────────────────── */

function drive_b64u(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }

function drive_state_path(): string { return data_dir() . '/drive_token.json'; }

/** cache the access token for most of its lifetime (expires_in is 3600 s) */
function drive_cached_token(): string {
    $f = drive_state_path();
    if (!is_readable($f)) { return ''; }
    $j = json_decode((string)@file_get_contents($f), true);
    if (!is_array($j)) { return ''; }
    $tok = (string)($j['access_token'] ?? '');
    $exp = (int)($j['expires_at'] ?? 0);
    return ($tok !== '' && $exp > time() + 60) ? $tok : '';
}

function drive_save_token(string $tok, int $in): void {
    @file_put_contents(drive_state_path(),
        json_encode(['access_token' => $tok, 'expires_at' => time() + max(60, $in - 60)]), LOCK_EX);
}

/** service-account signed JWT (RS256) */
function drive_jwt(array $sa): string {
    if (!function_exists('openssl_sign')) { return ''; }
    $now = time();
    $head = drive_b64u(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
    $body = drive_b64u(json_encode([
        'iss'   => (string)$sa['client_email'],
        'scope' => drive_scope(),
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ], JSON_UNESCAPED_SLASHES));
    $input = $head . '.' . $body;
    $sig = '';
    if (!openssl_sign($input, $sig, (string)$sa['private_key'], 'sha256') || $sig === '') { return ''; }
    return $input . '.' . drive_b64u($sig);
}

/** exchange credentials for a bearer token; ['ok'=>bool,'token'=>'','error'=>''] */
function drive_access_token(array $cfg): array {
    $cached = drive_cached_token();
    if ($cached !== '') { return ['ok' => true, 'token' => $cached]; }
    $c = drive_creds($cfg);
    $post = [];
    if (is_array($c['sa'])) {
        $jwt = drive_jwt($c['sa']);
        if ($jwt === '') { return ['ok' => false, 'error' => 'Could not sign the service-account JWT (openssl missing or bad private key).']; }
        $post = ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt];
    } elseif ($c['id'] !== '' && $c['secret'] !== '' && $c['refresh'] !== '') {
        $post = ['client_id' => $c['id'], 'client_secret' => $c['secret'],
                 'refresh_token' => $c['refresh'], 'grant_type' => 'refresh_token'];
    } else {
        return ['ok' => false, 'error' => drive_setup_hint($cfg)];
    }
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'], CURLOPT_ENCODING => '',
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = is_string($raw) ? json_decode($raw, true) : null;
    if ($code !== 200 || !is_array($j) || empty($j['access_token'])) {
        $why = is_array($j) ? (string)($j['error_description'] ?? $j['error'] ?? 'unknown error') : 'no response';
        return ['ok' => false, 'error' => 'Google rejected the Drive credentials (' . $why . '). Check them in Admin → Integrations.'];
    }
    drive_save_token((string)$j['access_token'], (int)($j['expires_in'] ?? 3600));
    return ['ok' => true, 'token' => (string)$j['access_token']];
}

/* ── raw API call ──────────────────────────────────────────────────────── */

/**
 * @return array{ok:bool, code:int, body:string, json:?array, error:string}
 */
function drive_api(array $cfg, string $method, string $url, $body = null, array $headers = [], int $timeout = 25): array {
    $tok = drive_access_token($cfg);
    if (empty($tok['ok'])) { return ['ok' => false, 'code' => 0, 'body' => '', 'json' => null, 'error' => (string)$tok['error']]; }
    $ch = curl_init($url);
    $h = array_merge(['Authorization: Bearer ' . $tok['token']], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => $h, CURLOPT_ENCODING => '', CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        if ($method === 'POST' || $method === 'PATCH' || $method === 'PUT') { curl_setopt($ch, CURLOPT_POST, true); }
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $raw = is_string($raw) ? $raw : '';
    $j = json_decode($raw, true);
    if ($code >= 400) {
        $why = is_array($j) ? (string)($j['error']['message'] ?? $j['error'] ?? '') : '';
        return ['ok' => false, 'code' => $code, 'body' => $raw, 'json' => is_array($j) ? $j : null,
                'error' => 'Drive API error ' . $code . ($why !== '' ? ': ' . $why : '')];
    }
    return ['ok' => true, 'code' => $code, 'body' => $raw, 'json' => is_array($j) ? $j : null, 'error' => ''];
}

/* ── helpers ───────────────────────────────────────────────────────────── */

function drive_fields(): string { return 'files(id,name,mimeType,modifiedTime,size,webViewLink,parents)'; }

function drive_is_google_doc(string $mime): bool { return strpos($mime, 'application/vnd.google-apps.') === 0; }

/** short one-line description of a file entry */
function drive_line(array $f): string {
    $kind = drive_is_google_doc((string)($f['mimeType'] ?? '')) ? 'doc' : 'file';
    $size = isset($f['size']) ? ' · ' . number_format((int)$f['size'] / 1024, 1) . ' KB' : '';
    return sprintf('%-11s %-52s %s%s', $f['id'] ?? '?', mb_substr((string)($f['name'] ?? '?'), 0, 50),
                   $kind, $size);
}

function drive_escape(string $s): string { return str_replace(["\\", "'"], ["\\\\", "\\'"], $s); }

/** resolve "root" / a folder id / a folder name to a folder id */
function drive_folder_id(array $cfg, string $want): array {
    $want = trim($want);
    if ($want === '' || strtolower($want) === 'root') { return ['ok' => true, 'id' => drive_creds($cfg)['root'] ?: 'root']; }
    if (preg_match('/^[A-Za-z0-9_-]{10,}$/', $want)) { return ['ok' => true, 'id' => $want]; }
    $q = "mimeType = 'application/vnd.google-apps.folder' and name contains '" . drive_escape($want) . "' and trashed = false";
    $r = drive_api($cfg, 'GET',
        'https://www.googleapis.com/drive/v3/files?' . http_build_query(['q' => $q, 'pageSize' => 5, 'fields' => drive_fields()]));
    if (empty($r['ok'])) { return ['ok' => false, 'error' => $r['error']]; }
    $files = (array)($r['json']['files'] ?? []);
    if (!$files) { return ['ok' => false, 'error' => "No Drive folder named \"{$want}\"."]; }
    return ['ok' => true, 'id' => (string)$files[0]['id'], 'name' => (string)$files[0]['name']];
}

/* ── the tools ─────────────────────────────────────────────────────────── */

/** drive_list — INPUT: optional folder (id, name or blank for root), optional "limit: 30" */
function drive_tool_list(array $cfg, string $input): array {
    if (!drive_configured($cfg)) { return ['ok' => false, 'text' => drive_setup_hint($cfg)]; }
    $folder = ''; $limit = 25;
    foreach (preg_split('/\r?\n/', trim($input)) as $l) {
        $l = trim($l);
        if (preg_match('/^limit\s*:\s*(\d+)$/i', $l, $m)) { $limit = max(1, min(100, (int)$m[1])); continue; }
        if (preg_match('/^folder\s*:\s*(.+)$/i', $l, $m)) { $folder = trim($m[1]); continue; }
        if ($l !== '') { $folder = $l; }
    }
    if ($folder === '') { $folder = drive_creds($cfg)['root'] ?: 'root'; }
    $fid = drive_folder_id($cfg, $folder);
    if (empty($fid['ok'])) { return ['ok' => false, 'text' => $fid['error']]; }
    $q = "'" . drive_escape((string)$fid['id']) . "' in parents and trashed = false";
    $r = drive_api($cfg, 'GET', 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
        'q' => $q, 'pageSize' => $limit, 'fields' => drive_fields(), 'orderBy' => 'folder,name',
    ]));
    if (empty($r['ok'])) { return ['ok' => false, 'text' => $r['error']]; }
    $files = (array)($r['json']['files'] ?? []);
    if (!$files) { return ['ok' => true, 'text' => 'No files in that Drive folder.']; }
    $out = 'Drive folder "' . ($fid['name'] ?? $fid['id']) . '" — ' . count($files) . " item(s):\n";
    foreach ($files as $f) { $out .= drive_line($f) . "\n"; }
    $out .= "\nUse drive_read with an id to open one.";
    return ['ok' => true, 'text' => $out];
}

/** drive_search — INPUT: search terms; optional "limit: 20" */
function drive_tool_search(array $cfg, string $input): array {
    if (!drive_configured($cfg)) { return ['ok' => false, 'text' => drive_setup_hint($cfg)]; }
    $term = trim($input); $limit = 20;
    if (preg_match('/^(.*?)\s+limit\s*:\s*(\d+)$/is', $term, $m)) { $term = trim($m[1]); $limit = max(1, min(50, (int)$m[2])); }
    $term = trim($term, " \t\n\r\0\x0B\"'");
    if ($term === '') { return ['ok' => false, 'text' => 'Give me something to search for.']; }
    $q = "(fullText contains '" . drive_escape($term) . "' or name contains '" . drive_escape($term) . "') and trashed = false";
    $r = drive_api($cfg, 'GET', 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
        'q' => $q, 'pageSize' => $limit, 'fields' => drive_fields(), 'orderBy' => 'modifiedTime desc',
    ]));
    if (empty($r['ok'])) { return ['ok' => false, 'text' => $r['error']]; }
    $files = (array)($r['json']['files'] ?? []);
    if (!$files) { return ['ok' => true, 'text' => 'No Drive files matched "' . $term . '".']; }
    $out = count($files) . " Drive file(s) matching \"{$term}\":\n";
    foreach ($files as $f) { $out .= drive_line($f) . "\n"; }
    return ['ok' => true, 'text' => $out];
}

/** drive_read — INPUT: file id (or "name: something"); optional "max: 20000" */
function drive_tool_read(array $cfg, string $input): array {
    if (!drive_configured($cfg)) { return ['ok' => false, 'text' => drive_setup_hint($cfg)]; }
    $id = trim($input); $max = 20000;
    if (preg_match('/^(.*?)\s+max\s*:\s*(\d+)$/is', $id, $m)) { $id = trim($m[1]); $max = max(500, min(200000, (int)$m[2])); }
    $id = trim($id, " \t\n\r\0\x0B\"'");
    if ($id === '') { return ['ok' => false, 'text' => 'Give me a Drive file id (use drive_search or drive_list to find one).']; }

    if (!preg_match('/^[A-Za-z0-9_-]{10,}$/', $id)) {   /* not an id → look it up by name */
        $r = drive_api($cfg, 'GET', 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
            'q' => "name contains '" . drive_escape($id) . "' and trashed = false",
            'pageSize' => 1, 'fields' => drive_fields(),
        ]));
        if (empty($r['ok'])) { return ['ok' => false, 'text' => $r['error']]; }
        $f = $r['json']['files'][0] ?? null;
        if (!$f) { return ['ok' => false, 'text' => "No Drive file named \"{$id}\"."]; }
        $id = (string)$f['id'];
    }
    /* metadata first, so we know whether to export or download */
    $meta = drive_api($cfg, 'GET',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($id) . '?fields=id,name,mimeType,size,webViewLink,modifiedTime');
    if (empty($meta['ok'])) { return ['ok' => false, 'text' => $meta['error']]; }
    $m = (array)$meta['json'];
    $mime = (string)($m['mimeType'] ?? '');
    $name = (string)($m['name'] ?? $id);

    if (drive_is_google_doc($mime)) {
        $export = 'text/plain';
        if ($mime === 'application/vnd.google-apps.spreadsheet') { $export = 'text/csv'; }
        elseif ($mime === 'application/vnd.google-apps.document') { $export = 'text/markdown'; }
        elseif ($mime === 'application/vnd.google-apps.presentation') { $export = 'text/plain'; }
        $r = drive_api($cfg, 'GET',
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode($id) . '/export?' . http_build_query(['mimeType' => $export]));
        if (empty($r['ok'])) { return ['ok' => false, 'text' => $r['error']]; }
        $text = $r['body'];
    } else {
        $binary = ['application/pdf', 'image/', 'video/', 'audio/', 'application/zip', 'application/x-'];
        foreach ($binary as $b) {
            if (strpos($mime, $b) === 0) {
                return ['ok' => true, 'text' => "\"{$name}\" is a binary {$mime} file (" . (isset($m['size']) ? number_format((int)$m['size'] / 1024, 1) . ' KB' : 'unknown size')
                    . "). I cannot read its contents as text — open it here: " . (string)($m['webViewLink'] ?? '(no link)')];
            }
        }
        $r = drive_api($cfg, 'GET',
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode($id) . '?alt=media');
        if (empty($r['ok'])) { return ['ok' => false, 'text' => $r['error']]; }
        $text = $r['body'];
    }
    if (trim($text) === '') { return ['ok' => true, 'text' => "\"{$name}\" is empty."]; }
    $cut = mb_strlen($text) > $max;
    if ($cut) { $text = mb_substr($text, 0, $max); }
    return ['ok' => true, 'text' => "Contents of \"{$name}\"" . ($cut ? " (first {$max} characters)" : '') . ":\n\n" . $text];
}

/** drive_upload — INPUT: line 1 = file name to create, optional "folder: X", then the contents */
function drive_tool_upload(array $cfg, string $input): array {
    if (!drive_configured($cfg)) { return ['ok' => false, 'text' => drive_setup_hint($cfg)]; }
    $lines = preg_split('/\r?\n/', $input);
    $name = trim((string)array_shift($lines));
    $folder = '';
    while ($lines) {
        $first = trim((string)$lines[0]);
        if (preg_match('/^folder\s*:\s*(.+)$/i', $first, $m)) { $folder = trim($m[1]); array_shift($lines); continue; }
        break;
    }
    $content = implode("\n", $lines);
    if ($name === '') { return ['ok' => false, 'text' => 'First line must be the file name to create in Drive.']; }
    if (trim($content) === '') { return ['ok' => false, 'text' => 'Nothing to upload — the file body is empty.']; }
    if (mb_strlen($content) > 5_000_000) { return ['ok' => false, 'text' => 'That content is too large to upload (limit ~5 MB).']; }

    $meta = ['name' => basename($name)];
    if ($folder !== '') {
        $fid = drive_folder_id($cfg, $folder);
        if (empty($fid['ok'])) { return ['ok' => false, 'text' => $fid['error']]; }
        $meta['parents'] = [(string)$fid['id']];
    } elseif (($root = drive_creds($cfg)['root']) !== '') {
        $meta['parents'] = [$root];
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $types = ['txt' => 'text/plain', 'md' => 'text/markdown', 'csv' => 'text/csv', 'json' => 'application/json',
              'html' => 'text/html', 'css' => 'text/css', 'js' => 'application/javascript',
              'xml' => 'application/xml', 'py' => 'text/x-python', 'sql' => 'application/sql',
              'log' => 'text/plain', 'ics' => 'text/calendar', 'svg' => 'image/svg+xml'];
    $mime = $types[$ext] ?? 'text/plain';

    $boundary = 'devil_drive_' . bin2hex(random_bytes(8));
    $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n"
          . json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
          . "\r\n--{$boundary}\r\nContent-Type: {$mime}; charset=UTF-8\r\n\r\n"
          . $content . "\r\n--{$boundary}--";
    $r = drive_api($cfg, 'POST',
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,mimeType,webViewLink',
        $body, ['Content-Type: multipart/related; boundary=' . $boundary]);
    if (empty($r['ok'])) { return ['ok' => false, 'text' => $r['error']]; }
    $j = (array)$r['json'];
    return ['ok' => true, 'text' => 'Uploaded to Drive: "' . (string)($j['name'] ?? $name) . '" (id ' . (string)($j['id'] ?? '?') . ')'
        . (!empty($j['webViewLink']) ? "\n" . $j['webViewLink'] : '')];
}

/** drive_create_folder — INPUT: folder name, optional parent folder */
function drive_tool_create_folder(array $cfg, string $input): array {
    if (!drive_configured($cfg)) { return ['ok' => false, 'text' => drive_setup_hint($cfg)]; }
    $lines = preg_split('/\r?\n/', trim($input));
    $name = trim((string)array_shift($lines));
    $parent = '';
    foreach ($lines as $l) {
        if (preg_match('/^(?:in|parent|folder)\s*:\s*(.+)$/i', trim($l), $m)) { $parent = trim($m[1]); }
    }
    if ($name === '') { return ['ok' => false, 'text' => 'Give the new folder a name.']; }
    $meta = ['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder'];
    if ($parent !== '') {
        $fid = drive_folder_id($cfg, $parent);
        if (empty($fid['ok'])) { return ['ok' => false, 'text' => $fid['error']]; }
        $meta['parents'] = [(string)$fid['id']];
    } elseif (($root = drive_creds($cfg)['root']) !== '') {
        $meta['parents'] = [$root];
    }
    $r = drive_api($cfg, 'POST', 'https://www.googleapis.com/drive/v3/files?fields=id,name,webViewLink',
        json_encode($meta, JSON_UNESCAPED_UNICODE), ['Content-Type: application/json; charset=UTF-8']);
    if (empty($r['ok'])) { return ['ok' => false, 'text' => $r['error']]; }
    $j = (array)$r['json'];
    return ['ok' => true, 'text' => 'Created Drive folder "' . (string)($j['name'] ?? $name) . '" (id ' . (string)($j['id'] ?? '?') . ')'
        . (!empty($j['webViewLink']) ? "\n" . $j['webViewLink'] : '')];
}

}   /* end function_exists guard */
