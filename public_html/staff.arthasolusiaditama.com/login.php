<?php
require_once 'app/helpers/url-helper.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);


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
}


if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
    
} elseif (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'customer') {
        redirect_to('/dashboard-customer.php');
    } else {
        redirect_to('/dashboard.php');
    }
}

$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}


function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateSalesJWT($user) {
    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: 's4l3s_4rth4_s0lus1_d1t4m4_2026_s3cr3t_k3y!@#';
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = [
        'id'       => (int)$user['id'],
        'name'     => $user['full_name'],
        'username' => $user['username'],
        'email'    => $user['email'] ?? '',
        'role'     => $user['role'],
        'avatar'   => $user['avatar'] ?? $user['photo'] ?? null,
        'iat'      => time(),
        'exp'      => time() + (8 * 3600),
    ];
    $payloadEncoded = base64url_encode(json_encode($payload));
    $signature = base64url_encode(hash_hmac('sha256', "$header.$payloadEncoded", $secret, true));
    return "$header.$payloadEncoded.$signature";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'app/config/database.php';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $requested_role = $_POST['role'] ?? '';

    
    if (empty($username) || empty($password)) {
      $_SESSION['error'] = 'Username dan password harus diisi';
      redirect_to('/login.php');
    }

    try {
        $query = "SELECT id, full_name, username, email, password, role, avatar, photo FROM users 
                  WHERE username = ? AND is_active = 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            
            if ($requested_role === 'customer' && $user['role'] !== 'customer') {
                $_SESSION['error'] = 'Bukan akun customer. Silakan login melalui form yang sesuai.';
                redirect_to('/login.php');
            } else if ($requested_role === 'staff' && $user['role'] === 'customer') {
                $_SESSION['error'] = 'Bukan akun staff. Silakan login melalui form yang sesuai.';
                redirect_to('/login.php');
            } else if ($requested_role === 'sales' && !in_array($user['role'], ['sales', 'administrator', 'direktur'])) {
                $_SESSION['error'] = 'Bukan akun sales. Silakan login melalui form yang sesuai.';
                redirect_to('/login.php');
            }

            
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([$user['id']]);

            require_once __DIR__ . '/app/helpers/audit-log.php';
            auditLog($pdo, 'login', [
                'user_id' => $user['id'],
                'details' => ['username' => $user['username'], 'role' => $user['role']]
            ]);

            
            if ($requested_role === 'sales' && in_array($user['role'], ['sales', 'administrator', 'direktur'])) {
                $salesToken = generateSalesJWT($user);
                $salesUrl = get_base_url() . str_replace('staff', 'sales', $_SERVER['HTTP_HOST']) . '/login?token=' . urlencode($salesToken);
                header('Location: ' . $salesUrl);
            } elseif ($user['role'] === 'customer') {
              redirect_to('/dashboard-customer.php');
            } else {
              redirect_to('/dashboard.php');
            }
            exit;
        } else {
            $_SESSION['error'] = 'Username atau password salah';
            redirect_to('/login.php');
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        error_log("Login error: " . $e->getMessage());
        redirect_to('/login.php');
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="color-scheme" content="light" />
  <meta name="theme-color" content="#0f172a" />
  <link rel="icon" type="image/png" href="public/assets/images/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <title>Login — PT. Artha Solusi Aditama</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { height: 100%; -webkit-tap-highlight-color: transparent; }
    body {
      min-height: 100%;
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      color-scheme: light !important;
      background: #111827;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    .scene {
      position: fixed;
      inset: 0;
      z-index: 0;
      overflow: hidden;
    }
    .scene-photo {
      position: absolute;
      inset: 0;
      background: url('/public/assets/images/hero-section.jpg') center/cover no-repeat;
      filter: blur(4px);
      transform: scale(1.05);
    }
    .scene-overlay {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.65);
    }

    .login-container {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      padding-top: max(24px, env(safe-area-inset-top));
      padding-bottom: max(24px, env(safe-area-inset-bottom));
    }

    .login-card {
      width: 100%;
      max-width: 440px;
      background: rgba(255,255,255,0.07);
      backdrop-filter: blur(48px) saturate(1.2);
      -webkit-backdrop-filter: blur(48px) saturate(1.2);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 24px;
      overflow: hidden;
      box-shadow:
        0 0 0 1px rgba(255,255,255,0.04) inset,
        0 20px 50px -15px rgba(0,0,0,0.45);
    }

    .card-inner {
      padding: 32px 28px;
    }
    @media (min-width: 480px) {
      .card-inner { padding: 40px 36px; }
    }

    .logo-wrap {
      text-align: center;
      margin-bottom: 28px;
    }
    .logo-img {
      width: 72px;
      height: 72px;
      object-fit: contain;
      margin-bottom: 16px;
      border-radius: 16px;
      filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
    }
    .logo-title {
      font-size: 20px;
      font-weight: 700;
      color: #fff;
      letter-spacing: -0.3px;
    }
    .logo-sub {
      font-size: 13px;
      color: rgba(255,255,255,0.4);
      margin-top: 4px;
    }

    .role-selector {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 28px;
    }
    .role-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      padding: 14px 8px;
      border-radius: 16px;
      border: 1.5px solid rgba(255,255,255,0.07);
      background: rgba(255,255,255,0.03);
      cursor: pointer;
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
      -webkit-user-select: none;
      user-select: none;
    }
    .role-btn::before {
      content: '';
      position: absolute;
      inset: 0;
      border-radius: 16px;
      opacity: 0;
      transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .role-btn:not(.role-disabled):hover {
      border-color: rgba(255,255,255,0.12);
      background: rgba(255,255,255,0.05);
    }
    .role-btn:not(.role-disabled):active {
      transform: scale(0.97);
    }

    .role-btn.active-staff {
      border-color: rgba(59,130,246,0.35);
      background: rgba(59,130,246,0.1);
      box-shadow: 0 2px 12px -3px rgba(59,130,246,0.15);
    }
    .role-btn.active-staff::before { background: linear-gradient(180deg, rgba(59,130,246,0.06), transparent); opacity: 1; }
    .role-btn.active-staff .role-icon-wrap { background: linear-gradient(145deg, #3b82f6, #2563eb); box-shadow: 0 3px 10px -2px rgba(37,99,235,0.4); }
    .role-btn.active-staff .role-icon-wrap svg { color: #fff; }

    .role-btn.active-sales {
      border-color: rgba(217,119,6,0.35);
      background: rgba(217,119,6,0.1);
      box-shadow: 0 2px 12px -3px rgba(217,119,6,0.15);
    }
    .role-btn.active-sales::before { background: linear-gradient(180deg, rgba(217,119,6,0.06), transparent); opacity: 1; }
    .role-btn.active-sales .role-icon-wrap { background: linear-gradient(145deg, #f59e0b, #d97706); box-shadow: 0 3px 10px -2px rgba(217,119,6,0.4); }
    .role-btn.active-sales .role-icon-wrap svg { color: #fff; }

    .role-btn.active-customer {
      border-color: rgba(5,150,105,0.35);
      background: rgba(5,150,105,0.1);
      box-shadow: 0 2px 12px -3px rgba(5,150,105,0.15);
    }
    .role-btn.active-customer::before { background: linear-gradient(180deg, rgba(5,150,105,0.06), transparent); opacity: 1; }
    .role-btn.active-customer .role-icon-wrap { background: linear-gradient(145deg, #10b981, #059669); box-shadow: 0 3px 10px -2px rgba(5,150,105,0.4); }
    .role-btn.active-customer .role-icon-wrap svg { color: #fff; }

    .role-icon-wrap {
      width: 40px; height: 40px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255,255,255,0.06);
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .role-icon-wrap svg {
      width: 20px; height: 20px;
      color: rgba(255,255,255,0.5);
      transition: color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .role-label {
      font-size: 12px;
      font-weight: 600;
      color: rgba(255,255,255,0.5);
      transition: color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      letter-spacing: 0.2px;
    }
    .role-btn[class*="active-"] .role-label { color: rgba(255,255,255,0.9); }

    .role-disabled {
      opacity: 0.35;
      cursor: not-allowed;
    }
    .role-badge {
      position: absolute;
      top: 6px;
      right: 6px;
      font-size: 9px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 2px 6px;
      border-radius: 6px;
      background: rgba(255,255,255,0.08);
      color: rgba(255,255,255,0.35);
    }

    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
    }
    .divider-line {
      flex: 1;
      height: 1px;
      background: rgba(255,255,255,0.06);
    }
    .divider-text {
      font-size: 11px;
      font-weight: 500;
      color: rgba(255,255,255,0.25);
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-panel {
      transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1), transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .form-panel.hidden-form {
      opacity: 0;
      transform: translateY(10px);
      pointer-events: none;
      position: absolute;
      visibility: hidden;
      width: 100%;
      left: 0;
    }
    .form-panel.visible-form {
      opacity: 1;
      transform: translateY(0);
    }

    .form-group { margin-bottom: 18px; }
    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: rgba(255,255,255,0.55);
      margin-bottom: 8px;
    }
    .input-wrap {
      position: relative;
    }
    .input-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      color: rgba(255,255,255,0.25);
      transition: color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .input-icon svg { width: 18px; height: 18px; }

    .form-input {
      width: 100%;
      padding: 13px 16px 13px 44px;
      background: rgba(255,255,255,0.05);
      border: 1.5px solid rgba(255,255,255,0.08);
      border-radius: 12px;
      color: #fff;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      transition: border-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                  background 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                  box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .form-input::placeholder {
      color: rgba(255,255,255,0.2);
    }
    .form-input:hover {
      border-color: rgba(255,255,255,0.14);
      background: rgba(255,255,255,0.06);
    }
    .form-input:focus {
      border-color: var(--accent, #3b82f6);
      background: rgba(255,255,255,0.07);
      box-shadow: 0 0 0 3px var(--accent-ring, rgba(59,130,246,0.12));
    }
    .form-input:focus + .input-icon,
    .form-input:focus ~ .input-icon {
      color: var(--accent, #3b82f6);
    }
    .input-wrap:focus-within .input-icon {
      color: var(--accent, #3b82f6);
    }

    .toggle-pw {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      padding: 4px;
      border: none;
      background: none;
      color: rgba(255,255,255,0.3);
      cursor: pointer;
      border-radius: 6px;
      transition: color 0.25s ease, background 0.25s ease;
    }
    .toggle-pw:hover { color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.05); }
    .toggle-pw svg { width: 18px; height: 18px; display: block; }

    .form-footer {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      margin-bottom: 22px;
    }
    .forgot-link {
      font-size: 12px;
      font-weight: 500;
      text-decoration: none;
      transition: color 0.25s ease;
    }
    .forgot-link-staff { color: rgba(59,130,246,0.7); }
    .forgot-link-staff:hover { color: #60a5fa; }
    .forgot-link-sales { color: rgba(217,119,6,0.7); }
    .forgot-link-sales:hover { color: #fbbf24; }

    .btn-login {
      width: 100%;
      padding: 14px 20px;
      border: none;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 700;
      color: #fff;
      cursor: pointer;
      font-family: inherit;
      position: relative;
      overflow: hidden;
      transition: opacity 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      letter-spacing: 0.2px;
    }
    .btn-login::before {
      content: none;
    }
    .btn-login:hover {
      opacity: 0.92;
    }
    .btn-login:active {
      transform: scale(0.98);
    }
    .btn-login svg { width: 16px; height: 16px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-login:hover svg { transform: translateX(2px); }

    .btn-login-staff {
      background: linear-gradient(145deg, #3b82f6, #2563eb);
      box-shadow: 0 4px 16px -4px rgba(37,99,235,0.4);
    }
    .btn-login-sales {
      background: linear-gradient(145deg, #f59e0b, #d97706);
      box-shadow: 0 4px 16px -4px rgba(217,119,6,0.4);
    }

    .btn-login.loading {
      pointer-events: none;
      opacity: 0.7;
    }
    .btn-login.loading .btn-text { opacity: 0; }
    .btn-login.loading .btn-spinner { opacity: 1; }
    .btn-spinner {
      position: absolute;
      opacity: 0;
      transition: opacity 0.25s ease;
    }
    .spinner {
      width: 20px; height: 20px;
      border: 2.5px solid rgba(255,255,255,0.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .ripple { display: none; }

    .error-alert {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 12px;
      background: rgba(239,68,68,0.1);
      border: 1px solid rgba(239,68,68,0.15);
      margin-bottom: 20px;
      animation: errorIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .error-alert svg { width: 18px; height: 18px; color: #f87171; flex-shrink: 0; }
    .error-alert span { font-size: 13px; color: #fca5a5; line-height: 1.4; }
    @keyframes errorIn {
      from { opacity: 0; transform: translateY(-6px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .coming-soon {
      text-align: center;
      padding: 30px 10px;
    }
    .coming-soon-icon {
      width: 60px; height: 60px;
      border-radius: 20px;
      background: rgba(16,185,129,0.08);
      border: 1px solid rgba(16,185,129,0.12);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
    }
    .coming-soon-icon svg { width: 28px; height: 28px; color: #34d399; }
    .coming-soon h3 { font-size: 16px; font-weight: 700; color: rgba(255,255,255,0.8); margin-bottom: 8px; }
    .coming-soon p { font-size: 13px; color: rgba(255,255,255,0.35); line-height: 1.6; max-width: 260px; margin: 0 auto; }

    .login-footer {
      margin-top: 24px;
      text-align: center;
    }
    .login-footer p {
      font-size: 11px;
      color: rgba(255,255,255,0.2);
    }

    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.55);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      z-index: 100;
      align-items: center;
      justify-content: center;
      padding: 16px;
      animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .modal-overlay.show { display: flex; }
    .modal-card {
      width: 100%;
      max-width: 400px;
      background: rgba(255,255,255,0.07);
      backdrop-filter: blur(48px) saturate(1.2);
      -webkit-backdrop-filter: blur(48px) saturate(1.2);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 24px;
      padding: 32px;
      position: relative;
      animation: modalIn 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes modalIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

    .modal-close {
      position: absolute;
      top: 16px;
      right: 16px;
      width: 32px; height: 32px;
      border-radius: 10px;
      border: none;
      background: rgba(255,255,255,0.05);
      color: rgba(255,255,255,0.4);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.25s ease, color 0.25s ease;
    }
    .modal-close:hover { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.7); }
    .modal-close svg { width: 16px; height: 16px; }

    .stagger { opacity: 0; animation: staggerUp 0.55s cubic-bezier(0.4, 0, 0.2, 1) forwards; }
    .stagger-1 { animation-delay: 0.06s; }
    .stagger-2 { animation-delay: 0.12s; }
    .stagger-3 { animation-delay: 0.18s; }
    .stagger-4 { animation-delay: 0.24s; }
    .stagger-5 { animation-delay: 0.3s; }
    .stagger-6 { animation-delay: 0.36s; }
    @keyframes staggerUp {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 380px) {
      .card-inner { padding: 24px 20px; }
      .role-selector { gap: 8px; }
      .role-btn { padding: 12px 6px; }
    }
    @media (min-height: 900px) {
      .login-container { padding-top: 60px; padding-bottom: 60px; }
    }

    @media (max-height: 680px) {
      .login-container { justify-content: flex-start; padding-top: 20px; }
    }
  </style>
</head>
<body>


<div class="scene" aria-hidden="true">
  <div class="scene-photo"></div>
  <div class="scene-overlay"></div>
</div>


<div class="login-container">
  <div class="login-card">
    <div class="card-inner">

      
      <div class="logo-wrap stagger stagger-1">
        <img src="/public/assets/images/logo.png" alt="PT. Artha Solusi Aditama" class="logo-img">
        <div class="logo-title">PT. Artha Solusi Aditama</div>
        <div class="logo-sub">Masuk ke akun Anda untuk melanjutkan</div>
      </div>

      
      <div class="role-selector stagger stagger-2">
        <button type="button" class="role-btn active-staff" id="card-staff" onclick="switchRole('staff')">
          <div class="role-icon-wrap">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          </div>
          <div class="role-label">Staff</div>
        </button>
        <button type="button" class="role-btn" id="card-sales" onclick="switchRole('sales')">
          <div class="role-icon-wrap">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          </div>
          <div class="role-label">Sales</div>
        </button>
        <button type="button" class="role-btn role-disabled" id="card-customer" onclick="switchRole('customer')">
          <span class="role-badge">Soon</span>
          <div class="role-icon-wrap">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </div>
          <div class="role-label">Customer</div>
        </button>
      </div>

      
      <div class="divider stagger stagger-3">
        <div class="divider-line"></div>
        <span class="divider-text" id="dividerLabel">Login Staff</span>
        <div class="divider-line"></div>
      </div>

      
      <?php if ($error): ?>
      <div class="error-alert">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
        </svg>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      
      <div class="relative stagger stagger-4" style="min-height: 295px;">

        
        <form id="form-staff" method="POST" class="form-panel visible-form" onsubmit="handleSubmit(event)">
          <input type="hidden" name="role" value="staff" />
          <div class="form-group">
            <label class="form-label">Username</label>
            <div class="input-wrap">
              <input type="text" name="username" placeholder="Masukkan username" required autocomplete="username"
                     class="form-input" style="--accent:#3b82f6;--accent-ring:rgba(59,130,246,0.15);--accent-glow:rgba(59,130,246,0.1)" />
              <div class="input-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrap">
              <input type="password" name="password" placeholder="Masukkan password" required autocomplete="current-password"
                     class="form-input" style="--accent:#3b82f6;--accent-ring:rgba(59,130,246,0.15);--accent-glow:rgba(59,130,246,0.1);padding-right:44px" />
              <div class="input-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
              </div>
              <button type="button" onclick="togglePassword(this)" class="toggle-pw">
                <svg class="eye-open" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </button>
            </div>
          </div>
          <div class="form-footer">
            <a href="javascript:void(0)" onclick="showForgotPassword()" class="forgot-link forgot-link-staff">Lupa password?</a>
          </div>
          <button type="submit" class="btn-login btn-login-staff">
            <span class="btn-text">Masuk sebagai Staff</span>
            <svg class="btn-text" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            <div class="btn-spinner"><div class="spinner"></div></div>
          </button>
        </form>

        
        <form id="form-sales" method="POST" class="form-panel hidden-form" onsubmit="handleSubmit(event)">
          <input type="hidden" name="role" value="sales" />
          <div class="form-group">
            <label class="form-label">Username</label>
            <div class="input-wrap">
              <input type="text" name="username" placeholder="Masukkan username sales" required autocomplete="username"
                     class="form-input" style="--accent:#d97706;--accent-ring:rgba(245,158,11,0.15);--accent-glow:rgba(245,158,11,0.1)" />
              <div class="input-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrap">
              <input type="password" name="password" placeholder="Masukkan password" required autocomplete="current-password"
                     class="form-input" style="--accent:#d97706;--accent-ring:rgba(245,158,11,0.15);--accent-glow:rgba(245,158,11,0.1);padding-right:44px" />
              <div class="input-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
              </div>
              <button type="button" onclick="togglePassword(this)" class="toggle-pw">
                <svg class="eye-open" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </button>
            </div>
          </div>
          <div class="form-footer">
            <a href="javascript:void(0)" onclick="showForgotPassword()" class="forgot-link forgot-link-sales">Lupa password?</a>
          </div>
          <button type="submit" class="btn-login btn-login-sales">
            <span class="btn-text">Masuk sebagai Sales</span>
            <svg class="btn-text" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            <div class="btn-spinner"><div class="spinner"></div></div>
          </button>
        </form>

        
        <div id="form-customer" class="form-panel hidden-form">
          <div class="coming-soon">
            <div class="coming-soon-icon">
              <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3>Coming Soon</h3>
            <p>Portal Customer sedang dalam tahap pengembangan. Hubungi administrator untuk info lebih lanjut.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  
  <div class="login-footer stagger stagger-6">
    <p>&copy; <?= date('Y') ?> PT. Artha Solusi Aditama. All rights reserved.</p>
  </div>
</div>


<div id="forgotPasswordModal" class="modal-overlay">
  <div class="modal-card">
    <button onclick="closeForgotPassword()" class="modal-close">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>

    <div style="text-align:center;margin-bottom:24px">
      <div style="display:inline-flex;width:52px;height:52px;border-radius:16px;background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.2);align-items:center;justify-content:center;margin-bottom:14px">
        <svg style="width:24px;height:24px;color:#60a5fa" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
      </div>
      <div style="font-size:18px;font-weight:700;color:#fff">Lupa Password?</div>
      <div style="font-size:13px;color:rgba(255,255,255,0.4);margin-top:4px">Masukkan username atau email Anda</div>
    </div>

    <form id="forgotPasswordForm" class="space-y-4">
      <div class="form-group">
        <label class="form-label">Username atau Email</label>
        <div class="input-wrap">
          <input type="text" name="username_or_email" required
                 class="form-input" style="--accent:#3b82f6;--accent-ring:rgba(59,130,246,0.15);--accent-glow:rgba(59,130,246,0.1)"
                 placeholder="Masukkan username atau email">
          <div class="input-icon">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
          </div>
        </div>
      </div>
      <div id="forgotPasswordMessage" style="display:none"></div>
      <button type="submit" class="btn-login btn-login-staff" style="margin-top:8px">
        <span class="btn-text">Kirim Permintaan Reset</span>
        <div class="btn-spinner"><div class="spinner"></div></div>
      </button>
    </form>

    <p style="font-size:11px;color:rgba(255,255,255,0.25);text-align:center;margin-top:20px">
      Jika tidak menerima email, silakan hubungi administrator
    </p>
  </div>
</div>

<script>
  let currentRole = 'staff';
  const dividerLabels = { staff: 'Login Staff', sales: 'Login Sales', customer: 'Customer Portal' };

  (function() {
    const params = new URLSearchParams(window.location.search);
    const role = params.get('role');
    if (role === 'sales' || role === 'staff') {
      currentRole = role;
      document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active-staff', 'active-sales', 'active-customer'));
      document.getElementById('card-' + role).classList.add('active-' + role);
      document.getElementById('dividerLabel').textContent = dividerLabels[role];
      ['staff', 'sales', 'customer'].forEach(r => {
        const form = document.getElementById('form-' + r);
        if (r === role) { form.classList.remove('hidden-form'); form.classList.add('visible-form'); }
        else { form.classList.remove('visible-form'); form.classList.add('hidden-form'); }
      });
    }
  })();

  function switchRole(role) {
    if (role === 'customer') return;
    currentRole = role;

    document.querySelectorAll('.role-btn').forEach(b => {
      b.classList.remove('active-staff', 'active-sales', 'active-customer');
    });
    document.getElementById('card-' + role).classList.add('active-' + role);

    const label = document.getElementById('dividerLabel');
    label.style.transition = 'opacity 0.2s ease';
    label.style.opacity = '0';
    setTimeout(() => {
      label.textContent = dividerLabels[role];
      label.style.opacity = '1';
    }, 200);

    ['staff', 'sales', 'customer'].forEach(r => {
      const form = document.getElementById('form-' + r);
      if (r === role) {
        form.classList.remove('hidden-form');
        form.classList.add('visible-form');
      } else {
        form.classList.remove('visible-form');
        form.classList.add('hidden-form');
      }
    });
  }

  function togglePassword(btn) {
    const input = btn.closest('.input-wrap').querySelector('input');
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.innerHTML = isHidden
      ? '<svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>'
      : '<svg class="eye-open" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>';
  }

  function handleSubmit(e) {
    const btn = e.target.querySelector('.btn-login');
    btn.classList.add('loading');
  }

  document.querySelectorAll('.btn-login').forEach(btn => {
    btn.addEventListener('click', function(e) {
      const rect = this.getBoundingClientRect();
      const ripple = document.createElement('div');
      ripple.className = 'ripple';
      ripple.style.left = (e.clientX - rect.left) + 'px';
      ripple.style.top = (e.clientY - rect.top) + 'px';
      this.appendChild(ripple);
      setTimeout(() => ripple.remove(), 600);
    });
  });

  function showForgotPassword() {
    const modal = document.getElementById('forgotPasswordModal');
    modal.classList.add('show');
    const msgDiv = document.getElementById('forgotPasswordMessage');
    msgDiv.style.display = 'none';
    document.getElementById('forgotPasswordForm').reset();
  }

  function closeForgotPassword() {
    document.getElementById('forgotPasswordModal').classList.remove('show');
  }

  document.getElementById('forgotPasswordModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeForgotPassword();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeForgotPassword();
  });

  document.getElementById('forgotPasswordForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('.btn-login');
    const messageDiv = document.getElementById('forgotPasswordMessage');

    submitBtn.classList.add('loading');

    fetch('app/auth/forgot-password-handler.php', { method: 'POST', body: new FormData(form) })
    .then(r => r.text())
    .then(data => {
      submitBtn.classList.remove('loading');
      messageDiv.style.display = 'block';

      if (data.startsWith('OK')) {
        messageDiv.style.cssText = 'display:block;padding:12px 14px;border-radius:12px;background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.2);font-size:13px;color:#93c5fd;margin-bottom:12px';
        messageDiv.innerHTML = '<div style="display:flex;align-items:start;gap:10px"><svg style="width:18px;height:18px;color:#60a5fa;flex-shrink:0;margin-top:1px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg><div><strong>Request Terkirim!</strong><br>Permintaan telah dikirim ke administrator.</div></div>';
        form.reset();
      } else {
        messageDiv.style.cssText = 'display:block;padding:12px 14px;border-radius:12px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.2);font-size:13px;color:#fca5a5;margin-bottom:12px';
        messageDiv.innerHTML = '<div style="display:flex;align-items:center;gap:10px"><svg style="width:18px;height:18px;color:#f87171;flex-shrink:0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg><span>' + data.replace('ERROR:','').trim() + '</span></div>';
      }
    })
    .catch(() => {
      submitBtn.classList.remove('loading');
      messageDiv.style.display = 'block';
      messageDiv.style.cssText = 'display:block;padding:12px 14px;border-radius:12px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.2);font-size:13px;color:#fca5a5;margin-bottom:12px';
      messageDiv.innerHTML = '<div style="display:flex;align-items:center;gap:10px"><svg style="width:18px;height:18px;color:#f87171;flex-shrink:0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg><span>Terjadi kesalahan. Silakan coba lagi.</span></div>';
    });
  });
</script>

</body>
</html>
