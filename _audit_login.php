<?php
/* TEMPORARY audit login for live debugging — deleted after the audit.
 * Needs the secret X-Devil-Audit header (only its sha256 is stored here) and stops working after a fixed time.
 * Signs in as a separate audit user, never as a real user. */
if (time() > 1791557551) { http_response_code(404); exit; }
$tok = (string)($_SERVER['HTTP_X_DEVIL_AUDIT'] ?? '');
if ($tok === '' || !hash_equals('c0ad573a40f990267fcbe4ead963501fb5d5cbe34a8be6843bdb135c09afeea1', hash('sha256', $tok))) { http_response_code(404); exit; }
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
