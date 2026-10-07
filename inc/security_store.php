<?php
/**
 * Devil AI security data store.
 *
 * This module keeps security state separate from users, chats and API keys.
 * It is intentionally additive and never overwrites existing runtime data.
 */
declare(strict_types=1);

function devil_security_store_root(): string {
    return dirname(__DIR__) . '/data/security';
}

function devil_security_store_paths(): array {
    $root = devil_security_store_root();
    return [
        'sessions'       => $root . '/sessions.json',
        'login_history'  => $root . '/login_history.json',
        'security_alerts'=> $root . '/security_alerts.json',
        'two_factor'     => $root . '/two_factor.json',
        'recovery'       => $root . '/recovery.json',
        'oauth_links'    => $root . '/oauth_links.json',
        'audit'          => $root . '/audit.json',
    ];
}

function devil_security_store_boot(): void {
    $root = devil_security_store_root();
    if (!is_dir($root)) { @mkdir($root, 0755, true); }
    if (!is_dir($root) || !is_writable($root)) { return; }
    foreach (devil_security_store_paths() as $path) {
        if (!is_file($path)) { @file_put_contents($path, "{}\n", LOCK_EX); @chmod($path, 0600); }
    }
}

function devil_security_store_read(string $name): array {
    devil_security_store_boot();
    $paths = devil_security_store_paths();
    $path = $paths[$name] ?? '';
    if ($path === '' || !is_readable($path)) { return []; }
    $data = json_decode((string)@file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function devil_security_store_write(string $name, array $data): bool {
    devil_security_store_boot();
    $paths = devil_security_store_paths();
    $path = $paths[$name] ?? '';
    if ($path === '') { return false; }
    return @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) !== false;
}

function devil_security_store_user(string $uid): array {
    return [
        'uid' => substr(hash('sha256', $uid), 0, 32),
        'updated' => time(),
    ];
}

function devil_security_store_event(string $uid, string $type, array $details = []): void {
    if ($uid === '' || $type === '') { return; }
    $events = devil_security_store_read('audit');
    $id = 'sec_' . bin2hex(random_bytes(12));
    $events[$id] = [
        'id' => $id,
        'uid' => substr(hash('sha256', $uid), 0, 32),
        'type' => substr($type, 0, 80),
        'details' => $details,
        'created' => time(),
    ];
    // Keep the security audit store bounded without deleting user/chat data.
    if (count($events) > 5000) {
        uasort($events, static function ($a, $b) { return (int)($a['created'] ?? 0) <=> (int)($b['created'] ?? 0); });
        $events = array_slice($events, -5000, null, true);
    }
    devil_security_store_write('audit', $events);
}

function devil_security_record_login(string $uid, string $method): void {
    if ($uid === '' || $method === '') { return; }
    $hash = substr(hash('sha256', $uid), 0, 32);
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 240);
    /* A sign-in counts as a new device only when no other known session of
       this user shares the same IP + browser fingerprint. The current session
       is not in the store yet (it is touched on the next request). */
    $newDevice = true;
    foreach (devil_security_store_read('sessions') as $item) {
        if (is_array($item) && ($item['uid'] ?? '') === $hash && ($item['ip'] ?? '') === $ip && ($item['user_agent'] ?? '') === $ua) {
            $newDevice = false;
            break;
        }
    }
    $events = devil_security_store_read('login_history');
    $id = 'lh_' . bin2hex(random_bytes(12));
    $events[$id] = [
        'id' => $id,
        'uid' => $hash,
        'method' => substr($method, 0, 24),
        'ip' => $ip,
        'user_agent' => $ua,
        'new_device' => $newDevice,
        'created' => time(),
    ];
    // Keep the login history bounded without deleting user/chat data.
    if (count($events) > 1000) {
        uasort($events, static function ($a, $b) { return (int)($a['created'] ?? 0) <=> (int)($b['created'] ?? 0); });
        $events = array_slice($events, -1000, null, true);
    }
    devil_security_store_write('login_history', $events);
    devil_security_store_event($uid, 'login', ['method' => substr($method, 0, 24), 'new_device' => $newDevice]);
}

function devil_security_session_id(): string {
    return hash('sha256', session_id());
}
function devil_security_session_touch(string $uid): void {
    if ($uid === '' || session_id() === '') { return; }
    $items = devil_security_store_read('sessions');
    $sid = devil_security_session_id();
    $items[$sid] = [
        'id' => $sid,
        'uid' => substr(hash('sha256', $uid), 0, 32),
        'created' => (int)($items[$sid]['created'] ?? time()),
        'last_seen' => time(),
        'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 240),
    ];
    foreach ($items as $key => $item) {
        if (!is_array($item) || (int)($item['last_seen'] ?? 0) < time() - (30 * 86400)) { unset($items[$key]); }
    }
    devil_security_store_write('sessions', $items);
}
function devil_security_session_revoke(string $uid, string $sid = ''): void {
    $items = devil_security_store_read('sessions');
    foreach ($items as $key => $item) {
        if (is_array($item) && ($item['uid'] ?? '') === substr(hash('sha256', $uid), 0, 32) && ($sid === '' || $key === $sid)) { unset($items[$key]); }
    }
    devil_security_store_write('sessions', $items);
}

// Initialize only the new security directory/files; existing data is untouched.
devil_security_store_boot();
