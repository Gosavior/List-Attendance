<?php
require_once 'app/helpers/url-helper.php';

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


if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $action = $_GET['action'];
    
    if ($action === 'get_notifications') {
        require_once __DIR__ . '/app/action/get_notifications.php';
        exit;
    }
    
    if ($action === 'birthday_thread_fetch') {
        require_once __DIR__ . '/app/action/birthday_thread_fetch.php';
        exit;
    }
    
    if ($action === 'birthday_thread_post') {
        require_once __DIR__ . '/app/action/birthday_thread_post.php';
        exit;
    }
    
    
    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
    exit;
}


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    redirect_to('/login.php');
}

if ($_SESSION['role'] === 'customer') {
    redirect_to('/dashboard-customer.php');
}

redirect_to('/dashboard.php');
?>
