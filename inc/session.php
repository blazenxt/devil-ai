<?php
/**
 * Devil AI — durable session/cookie bootstrap.
 * Keeps logins alive across pages and avoids shared-host /tmp session cleanup.
 */

if (!defined('DEVIL_SESSION_LIFETIME')) {
    define('DEVIL_SESSION_LIFETIME', 60 * 60 * 24 * 30); // 30 days
}

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/security_store.php';
devil_security_boot();

function devil_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');
}

function devil_cookie_path(): string {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim(dirname($script), '/');
    if ($dir === '' || $dir === '.' || $dir === '/') { return '/'; }
    return $dir . '/';
}

function devil_session_cookie_options(?int $expires = null): array {
    return [
        'expires'  => $expires ?? (time() + DEVIL_SESSION_LIFETIME),
        'path'     => devil_cookie_path(),
        'secure'   => devil_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function devil_session_refresh(): void {
    if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) { return; }
    setcookie(session_name(), session_id(), devil_session_cookie_options());
}

function devil_session_boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) { devil_session_refresh(); return; }

    $savePath = dirname(__DIR__) . '/data/sessions';
    if (!is_dir($savePath)) { @mkdir($savePath, 0755, true); }
    if (is_dir($savePath) && is_writable($savePath)) {
        ini_set('session.save_path', $savePath);
    }

    ini_set('session.gc_maxlifetime', (string)DEVIL_SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', (string)DEVIL_SESSION_LIFETIME);
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '1000');

    session_name('DEVILAISESSID');
    $hadCookie = isset($_COOKIE[session_name()]);
    session_set_cookie_params([
        'lifetime' => DEVIL_SESSION_LIFETIME,
        'path'     => devil_cookie_path(),
        'secure'   => devil_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    /* New sessions already get a cookie from session_start(); existing sessions are refreshed for rolling expiry. */
    if ($hadCookie) { devil_session_refresh(); }
}

function devil_session_destroy_all(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { return; }
    $_SESSION = [];
    if (!headers_sent()) {
        setcookie(session_name(), '', devil_session_cookie_options(time() - 3600));
    }
    @session_destroy();
}
