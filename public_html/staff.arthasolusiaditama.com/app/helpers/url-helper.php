<?php
/**
 * URL Helper Functions
 * Supports both production and localhost environments
 */

if (!function_exists('get_base_url')) {
    function get_base_url() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . '://' . $host;
    }
}

if (!function_exists('get_session_domain')) {
    function get_session_domain() {
        $host = $_SERVER['HTTP_HOST'];
        
        // For localhost or local IPs, use empty string
        if ($host === 'localhost' || 
            strpos($host, 'localhost:') === 0 || 
            strpos($host, '127.0.0.1') === 0 ||
            strpos($host, '192.168.') === 0) {
            return '';
        }
        
        // For production domains, allow subdomain cookies
        return '.arthasolusiaditama.com';
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to($path) {
        header('Location: ' . get_base_url() . $path);
        exit;
    }
}
?>
