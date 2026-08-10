<?php
session_start();
// Canonical URL: /logout/ → /logout (301) supaya redirect relatif selalu resolve dari root
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($reqPath !== '/' && substr($reqPath, -1) === '/') {
    header('Location: ' . rtrim($reqPath, '/'), true, 301);
    exit;
}
$_SESSION = [];
session_destroy();
header('Location: login');
exit;
