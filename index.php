<?php
/**
 * DEVIL AI — home (index.php)
 * There is no landing page — the home page IS the chat.
 * Signed-out visitors see the chat screen; sending a message asks them to log in.
 */
$devilHomePath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('~/index\.php$~i', $devilHomePath)) {
    header('Location: ' . preg_replace('~index\.php$~i', '', $devilHomePath), true, 301);
    exit;
}
require __DIR__ . '/app.php';
