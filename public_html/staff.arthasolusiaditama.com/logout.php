<?php
require_once __DIR__ . '/app/helpers/url-helper.php';

if (session_status() === PHP_SESSION_NONE) {
	$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
	session_set_cookie_params([
		'lifetime' => 0,
		'path' => '/',
		'domain' => get_session_domain(),
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax'
	]);
	session_start();
} else {
	session_start();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

redirect_to('/login.php');
?>