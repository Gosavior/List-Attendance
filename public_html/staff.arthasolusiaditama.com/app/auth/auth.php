<?php
require_once __DIR__ . '/../helpers/url-helper.php';

if (session_status() === PHP_SESSION_NONE) {
    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false);

    $rawCookie = $_SERVER['HTTP_COOKIE'] ?? '';
    if (substr_count($rawCookie, 'PHPSESSID=') > 1) {
        setcookie('PHPSESSID', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly'  => true,
            'samesite' => 'Lax'
        ]);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

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



header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');


header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');



if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    redirect_to('/login.php');
}
$_SESSION['LAST_ACTIVITY'] = time();



if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}



function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        $isAjax =
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        redirect_to('/login.php');
    }
}

if (basename($_SERVER['SCRIPT_NAME']) === 'login.php' && isset($_SESSION['user_id'])) {
    redirect_to('/dashboard.php');
}



function require_role(string|array $roles): void {
    $current = $_SESSION['role'] ?? '';
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($current, $allowed, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

function has_role(string|array $roles): bool {
    $current = $_SESSION['role'] ?? '';
    return is_array($roles)
        ? in_array($current, $roles, true)
        : $current === $roles;
}



function csrf_token(): string {
    return $_SESSION['csrf'] ?? '';
}

function verify_csrf(?string $token): bool {
    return isset($_SESSION['csrf']) &&
           $token !== null &&
           hash_equals($_SESSION['csrf'], $token);
}