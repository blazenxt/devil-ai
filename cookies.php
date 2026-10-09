<?php
/* The Cookie Policy moved to /cookie-policy (public site pages, see site.php). */
$b = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/cookies.php'))), '/');
header('Location: ' . (($b === '.' || $b === '/') ? '' : $b) . '/cookie-policy', true, 301);
exit;
