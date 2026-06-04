<?php
require_once __DIR__ . '/../helpers/url-helper.php';

if (session_status() === PHP_SESSION_NONE) {
    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => get_session_domain(),
        'secure'   => $isHttps,
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$_SESSION = [];
setcookie(session_name(), '', time() - 42000, '/', get_session_domain());
setcookie(session_name(), '', time() - 42000, '/');
session_destroy();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

redirect_to('/login.php');
?>