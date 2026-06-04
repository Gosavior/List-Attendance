<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$token_valid = false;
$user = null;

if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("
            SELECT prt.*, u.id as user_id, u.username, u.full_name, u.email 
            FROM password_reset_tokens prt
            JOIN users u ON u.id = prt.user_id
            WHERE prt.token = ? 
            AND prt.expires_at > NOW() 
            AND prt.used_at IS NULL
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_data) {
            $token_valid = true;
            $user = $token_data;
        } else {
            $error = 'Token tidak valid atau sudah kedaluwarsa. Silakan request reset password lagi.';
        }
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan. Silakan coba lagi.';
        error_log("Reset password token validation error: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Password harus diisi';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Password tidak sama';
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed, $user['user_id']]);
            
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = 'Password berhasil direset! Silakan login dengan password baru.';
            $token_valid = false;
            
            error_log("Password reset successful for user: {$user['username']} (ID: {$user['user_id']})");
            
        } catch (Exception $e) {
            $error = 'Gagal reset password. Silakan coba lagi.';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PT. Artha Solusi Aditama</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8">
        <div class="text-center mb-6">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-key text-blue-600 text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Reset Password</h1>
            <p class="text-gray-600 text-sm mt-2">PT. Artha Solusi Aditama</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <p class="text-green-700"><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
            <a href="login.php" class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-3 rounded-lg font-semibold transition">
                <i class="fas fa-sign-in-alt mr-2"></i> Login Sekarang
            </a>
        
        <?php elseif ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
            <a href="login.php" class="block w-full bg-gray-600 hover:bg-gray-700 text-white text-center py-3 rounded-lg font-semibold transition">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Login
            </a>
        
        <?php elseif ($token_valid): ?>
            <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    Reset password untuk: <strong><?= htmlspecialchars($user['username']) ?></strong>
                </p>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Password Baru
                    </label>
                    <input type="password" name="new_password" required minlength="6"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        placeholder="Minimal 6 karakter">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Konfirmasi Password
                    </label>
                    <input type="password" name="confirm_password" required minlength="6"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        placeholder="Ulangi password baru">
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-semibold transition flex items-center justify-center gap-2">
                    <i class="fas fa-check"></i> Reset Password
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="login.php" class="text-sm text-gray-600 hover:text-gray-800 transition">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Login
                </a>
            </div>
        
        <?php else: ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-yellow-500 text-xl mr-3"></i>
                    <p class="text-yellow-700">Link reset password tidak valid atau sudah kedaluwarsa.</p>
                </div>
            </div>
            <a href="login.php" class="block w-full bg-gray-600 hover:bg-gray-700 text-white text-center py-3 rounded-lg font-semibold transition">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Login
            </a>
        <?php endif; ?>

        
        <div class="mt-8 text-center text-xs text-gray-500">
            <p>© 2025 PT. Artha Solusi Aditama</p>
        </div>
    </div>
</body>
</html>
