<?php
/* TEMPORARY audit login for live debugging — deleted after the audit.
 * Needs the secret X-Devil-Audit header (only its sha256 is stored here) and stops working after a fixed time.
 * Signs in as a separate audit user, never as a real user. */
if (time() > 1791557551) { http_response_code(404); exit; }
$tok = (string)($_SERVER['HTTP_X_DEVIL_AUDIT'] ?? '');
if ($tok === '' || !hash_equals('c0ad573a40f990267fcbe4ead963501fb5d5cbe34a8be6843bdb135c09afeea1', hash('sha256', $tok))) { http_response_code(404); exit; }
if (($_GET['cleanup'] ?? '') === '1') {
    /* remove the audit user and everything it created, then this file is deleted */
    $uid = 'uaudit0bot000001'; $file = __DIR__ . '/data/users.json';
    $users = json_decode((string)@file_get_contents($file), true);
    $removed = false;
    if (is_array($users) && isset($users[$uid])) {
        unset($users[$uid]);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false && rename($tmp, $file)) { $removed = true; }
    }
    $rm = static function (string $d) use (&$rm): int { $n = 0; if (!is_dir($d)) { return 0; } foreach (scandir($d) ?: [] as $f) { if ($f === '.' || $f === '..') { continue; } $q = $d . '/' . $f; if (is_dir($q)) { $n += $rm($q); } else { $n += @unlink($q) ? 1 : 0; } } @rmdir($d); return $n; };
    $n = $rm(__DIR__ . '/data/chats/' . $uid) + $rm(__DIR__ . '/data/agent_jobs/' . $uid);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'user_removed' => $removed, 'files_deleted' => $n, 'users_left' => is_array($users) ? count($users) : -1]);
    exit;
}
require __DIR__ . '/inc/session.php';
devil_session_boot();
$uid = 'uaudit0bot000001';
$file = __DIR__ . '/data/users.json';
$fh = fopen($file . '.auditlock', 'c'); if ($fh) { flock($fh, LOCK_EX); }
$users = json_decode((string)@file_get_contents($file), true);
if (is_array($users) && !isset($users[$uid])) {
    $users[$uid] = ['id' => $uid, 'name' => 'Audit', 'email' => 'audit.bot@blazenxt.com', 'created' => time()];
    $tmp = $file . '.tmp' . getmypid();
    if (file_put_contents($tmp, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) { rename($tmp, $file); }
}
if ($fh) { flock($fh, LOCK_UN); fclose($fh); @unlink($file . '.auditlock'); }
session_regenerate_id(true);
$_SESSION['devil_uid'] = $uid;
header('Content-Type: application/json');
echo json_encode(['ok' => is_array($users), 'uid' => $uid]);
