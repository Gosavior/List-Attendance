<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';

if (!in_array($_SESSION['role'], ['administrator', 'direktur'])) {
    http_response_code(403); exit('Akses ditolak.');
}

$current_tab = $_GET['tab'] ?? 'accounts';

$db = $pdo;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject'])) {
    $requestId = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    
    if ($requestId) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        
        $query = "UPDATE password_reset_requests 
                  SET status = :status, 
                      processed_at = NOW(), 
                      processed_by = :admin_id,
                      notes = :notes
                  WHERE id = :request_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':request_id', $requestId);
        
        if ($stmt->execute()) {
            if ($action === 'approve') {
                $query = "SELECT u.id, u.username, u.full_name as nama_lengkap 
                          FROM password_reset_requests prr 
                          JOIN users u ON prr.user_id = u.id 
                          WHERE prr.id = :request_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':request_id', $requestId);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['approve_success'] = [
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'nama_lengkap' => $user['nama_lengkap']
                ];
            }
            
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => $action === 'approve' 
                    ? 'Request disetujui. Silakan reset password user.' 
                    : 'Request ditolak.'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => 'Gagal memproses request.'
            ];
        }
        
        header('Location: ?page=account&tab=approval');
        exit;
    }
}

$stmt = $pdo->query("SELECT id, full_name, username, email, avatar, role, is_active, created_at, gender FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendingQuery = "SELECT 
    prr.id,
    prr.created_at,
    u.id as user_id,
    u.username,
    u.full_name as nama_lengkap,
    u.email,
    u.role
FROM password_reset_requests prr
JOIN users u ON prr.user_id = u.id
WHERE prr.status = 'pending'
ORDER BY prr.created_at ASC";

$pendingStmt = $db->prepare($pendingQuery);
$pendingStmt->execute();
$pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

$processedQuery = "SELECT 
    prr.id,
    prr.created_at,
    prr.processed_at,
    prr.status,
    prr.notes,
    u.username,
    u.full_name as nama_lengkap,
    admin.full_name as processed_by_name
FROM password_reset_requests prr
JOIN users u ON prr.user_id = u.id
LEFT JOIN users admin ON prr.processed_by = admin.id
WHERE prr.status IN ('approved', 'rejected')
    AND prr.processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY prr.processed_at DESC
LIMIT 50";

$processedStmt = $db->prepare($processedQuery);
$processedStmt->execute();
$processedRequests = $processedStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="icon" type="image/png" href="./public/assets/images/logo.png">
  <title>Manajemen Akun Karyawan - PT Artha Solusi Aditama</title>
  <link rel="stylesheet" href="./src/output.css">
  
  <style>
      box-shadow: 0 2px 8px 0 rgba(49,71,255,0.06);
      border: 1.5px solid #e0e7ff;
      transition: box-shadow 0.2s, border-color 0.2s;
    }
    .dark #userModal .input-tw {
      background: rgba(51, 65, 85, 0.97);
      border-color: rgba(100, 116, 139, 0.5);
      color: #fff;
    }
      border-color: #6366f1;
      box-shadow: 0 0 0 2px #6366f155;
    }
      color: #a5b4fc;
      opacity: 1;
    }
    .dark #userModal .input-tw::placeholder {
      color: #64748b;
    }
    
      box-shadow: 0 2px 8px 0 rgba(49,71,255,0.06);
      border: 1.5px solid #e0e7ff;
      transition: box-shadow 0.2s, border-color 0.2s;
    }
    .dark #resetPasswordModal .input-tw {
      background: rgba(51, 65, 85, 0.97);
      border-color: rgba(100, 116, 139, 0.5);
      color: #fff;
    }
      border-color: #6366f1;
      box-shadow: 0 0 0 2px #6366f155;
    }
      color: #a5b4fc;
      opacity: 1;
    }
    .dark #resetPasswordModal .input-tw::placeholder {
      color: #64748b;
    }
    
      box-shadow: 0 2px 8px 0 rgba(49,71,255,0.06);
      border: 1.5px solid #e0e7ff;
      transition: box-shadow 0.2s, border-color 0.2s;
    }
    .dark #deleteAccountModal .input-tw {
      background: rgba(51, 65, 85, 0.97);
      border-color: rgba(100, 116, 139, 0.5);
      color: #fff;
    }
      border-color: #6366f1;
      box-shadow: 0 0 0 2px #6366f155;
    }
      color: #a5b4fc;
      opacity: 1;
    }
    .dark #deleteAccountModal .input-tw::placeholder {
      color: #64748b;
    }
    
      box-shadow: 0 2px 8px 0 rgba(49,71,255,0.07);
      transition: box-shadow 0.2s, background 0.2s, color 0.2s;
    }
      box-shadow: 0 4px 16px 0 rgba(49,71,255,0.13);
    }
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(10px) saturate(160%);
      border: 1.5px solid #e0e7ff;
      box-shadow: 0 8px 40px 0 rgba(49,71,255,0.10),0 2px 12px 0 rgba(49,71,255,0.07);
    }
    .dark #userModal .glass {
      background: rgba(30, 41, 59, 0.95);
      border: 1.5px solid rgba(100, 116, 139, 0.3);
      box-shadow: 0 8px 40px 0 rgba(0,0,0,0.3),0 2px 12px 0 rgba(0,0,0,0.2);
    }
      border: 4px solid #e0e7ff;
      box-shadow: 0 2px 12px 0 rgba(99,102,241,0.10);
      background: linear-gradient(135deg, #f1f5ff 60%, #e0e7ff 100%);
    }
    .dark #userModal #userFormAvatarPreview {
      border: 4px solid rgba(100, 116, 139, 0.5);
      box-shadow: 0 2px 12px 0 rgba(0,0,0,0.3);
      background: linear-gradient(135deg, #1e293b 60%, #334155 100%);
    }
    
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(10px) saturate(160%);
      border: 1.5px solid #e0e7ff;
      box-shadow: 0 8px 40px 0 rgba(49,71,255,0.10),0 2px 12px 0 rgba(49,71,255,0.07);
    }
    .dark #resetPasswordModal .glass {
      background: rgba(30, 41, 59, 0.95);
      border: 1.5px solid rgba(100, 116, 139, 0.3);
      box-shadow: 0 8px 40px 0 rgba(0,0,0,0.3),0 2px 12px 0 rgba(0,0,0,0.2);
    }
    
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(10px) saturate(160%);
      border: 1.5px solid #e0e7ff;
      box-shadow: 0 8px 40px 0 rgba(49,71,255,0.10),0 2px 12px 0 rgba(49,71,255,0.07);
    }
    .dark #deleteAccountModal .glass {
      background: rgba(30, 41, 59, 0.95);
      border: 1.5px solid rgba(100, 116, 139, 0.3);
      box-shadow: 0 8px 40px 0 rgba(0,0,0,0.3),0 2px 12px 0 rgba(0,0,0,0.2);
    }
    
      background: rgba(248,250,255,0.7);
      border-radius: 0.75rem;
      padding: 0.5rem 0.75rem;
      margin-bottom: 0.1rem;
      transition: background 0.2s;
    }
    .dark #userModal .grid > div {
      background: rgba(51, 65, 85, 0.3);
    }
      background: rgba(224,231,255,0.7);
    }
    .dark #userModal .grid > div:focus-within {
      background: rgba(71, 85, 105, 0.5);
    }
      -webkit-appearance: none;
      margin: 0;
    }
      -moz-appearance: textfield;
    }
    body { font-family: 'Inter', sans-serif; background: #f6f8fa; }
    .dark body { background: #0f172a; }
      animation: fadeInUp .25s cubic-bezier(.6,1.5,.6,1) both;
    }
    @keyframes fadeInUp {
      from { transform: translateY(40px) scale(.95); opacity: 0; }
      to   { transform: translateY(0) scale(1); opacity: 1;}
    }
    .glass {
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(6px) saturate(140%);
      box-shadow: 0 6px 36px 0 rgba(49,71,255,0.07),0 1.5px 8px 0 rgba(49,71,255,0.05);
    }
    .dark .glass {
      background: rgba(30, 41, 59, 0.85);
      border-color: rgba(100, 116, 139, 0.3) !important;
    }
    .input-tw {
      @apply w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-900 transition-all text-sm;
    }
    .input-tw:focus {
      @apply outline-none ring-2 ring-indigo-500 border-indigo-500;
    }
    .dark .input-tw {
      @apply bg-slate-700 border-slate-600 text-white;
    }
    .dark .input-tw:focus {
      @apply ring-indigo-400 border-indigo-400;
    }
    .dark .input-tw::placeholder {
      @apply text-slate-400;
    }
    .btn-tw {
      @apply px-4 py-2 rounded-lg font-semibold shadow-sm transition-all text-sm;
    }
    .btn-main {
      @apply btn-tw bg-indigo-600 text-white hover:bg-indigo-700;
    }
    .btn-alt {
      @apply btn-tw bg-white text-indigo-600 border border-indigo-200 hover:bg-indigo-50;
    }
    .dark .btn-alt {
      @apply bg-slate-700 text-indigo-400 border-slate-600 hover:bg-slate-600;
    }
    .btn-danger {
      @apply btn-tw bg-red-600 text-white hover:bg-red-700;
    }
    .btn-sm { @apply px-2.5 py-1.5 rounded-md text-xs; }
      background: #e0e7ff; border-radius: 5px;
    }
  </style>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-slate-900">

<div class="max-w-7xl mx-auto px-4 py-10">
  
  <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="mb-6 p-4 rounded-xl shadow-sm <?= $_SESSION['flash_message']['type'] === 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300' ?>">
      <div class="flex items-center gap-3">
        <i class="fas <?= $_SESSION['flash_message']['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-xl"></i>
        <span><?= htmlspecialchars($_SESSION['flash_message']['message']) ?></span>
      </div>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
  <?php endif; ?>

  
  <?php if (isset($_SESSION['approve_success'])): ?>
    <div id="approveSuccessModal" class="fixed inset-0 bg-black/60 dark:bg-black/80 flex items-center justify-center z-50 backdrop-blur-sm">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4 border border-gray-200 dark:border-slate-700">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-xl flex items-center justify-center">
            <i class="fas fa-key text-white text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white">Reset Password User</h3>
        </div>
        
        <div class="mb-6 p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-xl">
          <div class="flex items-start gap-3">
            <i class="fas fa-user-circle text-indigo-600 dark:text-indigo-400 text-2xl mt-1"></i>
            <div class="flex-1">
              <p class="text-sm text-gray-700 dark:text-slate-300 mb-1">
                <span class="font-semibold">Username:</span> 
                <span class="font-mono"><?= htmlspecialchars($_SESSION['approve_success']['username']) ?></span>
              </p>
              <p class="text-sm text-gray-700 dark:text-slate-300">
                <span class="font-semibold">Nama Lengkap:</span> 
                <?= htmlspecialchars($_SESSION['approve_success']['nama_lengkap']) ?>
              </p>
            </div>
          </div>
        </div>
        
        <form id="approveResetPasswordForm" class="space-y-4">
          <input type="hidden" name="user_id" value="<?= $_SESSION['approve_success']['user_id'] ?>">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
              <i class="fas fa-lock mr-1"></i>Password Baru
            </label>
            <div class="relative">
              <input type="password" name="new_password" id="approveNewPassword" required
                class="w-full px-4 py-3 pr-10 border border-gray-300 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 text-gray-800 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-600 focus:border-transparent transition"
                placeholder="Minimal 6 karakter">
              <button type="button" onclick="togglePasswordField('approveNewPassword')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300">
                <i class="fas fa-eye" id="approveNewPassword-icon"></i>
              </button>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
              <i class="fas fa-lock mr-1"></i>Konfirmasi Password
            </label>
            <div class="relative">
              <input type="password" name="confirm_password" id="approveConfirmPassword" required
                class="w-full px-4 py-3 pr-10 border border-gray-300 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 text-gray-800 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-600 focus:border-transparent transition"
                placeholder="Ulangi password baru">
              <button type="button" onclick="togglePasswordField('approveConfirmPassword')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300">
                <i class="fas fa-eye" id="approveConfirmPassword-icon"></i>
              </button>
            </div>
          </div>
          <div id="approvePasswordError" class="hidden p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-600 dark:text-red-400">
            <i class="fas fa-exclamation-triangle mr-2"></i><span id="approvePasswordErrorText"></span>
          </div>
          <div class="flex flex-col sm:flex-row gap-3 pt-2">
            <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition flex items-center justify-center gap-2">
              <i class="fas fa-check-circle"></i>
              Reset Password
            </button>
            <button type="button" onclick="closeApproveSuccessModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-800 dark:text-white font-semibold py-3 px-4 rounded-xl transition flex items-center justify-center gap-2">
              <i class="fas fa-times"></i>
              Batal
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php unset($_SESSION['approve_success']); ?>
  <?php endif; ?>

  
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 mb-6">
    <div class="flex-1">
      <h1 class="text-3xl font-bold text-indigo-800 dark:text-indigo-300 mb-1 tracking-tight">Manajemen Akun Karyawan</h1>
      <p class="text-sm text-gray-600 dark:text-slate-400">Kelola akun karyawan dan permintaan reset password</p>
    </div>
    <?php if ($current_tab === 'accounts'): ?>
    <button onclick="openUserForm()" class="btn-main flex items-center gap-2 cursor-pointer">
      <svg class="w-5 h-5 cursor-pointer" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
      Tambah Akun
    </button>
    <?php endif; ?>
  </div>

  
  <div class="glass rounded-xl border border-indigo-100 dark:border-slate-600 mb-8 overflow-hidden">
    <div class="flex border-b border-indigo-100 dark:border-slate-600">
      <a href="?page=account&tab=accounts" 
         class="flex-1 px-6 py-4 text-center font-semibold transition-all <?= $current_tab === 'accounts' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-400 hover:bg-indigo-50 dark:hover:bg-slate-700' ?>">
        <i class="fas fa-users mr-2"></i>Manage Accounts
      </a>
      <a href="?page=account&tab=approval" 
         class="flex-1 px-6 py-4 text-center font-semibold transition-all relative <?= $current_tab === 'approval' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-400 hover:bg-indigo-50 dark:hover:bg-slate-700' ?>">
        <i class="fas fa-user-lock mr-2"></i>Password Reset Approval
        <?php if (count($pendingRequests) > 0): ?>
          <span class="absolute top-2 right-2 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center animate-pulse">
            <?= count($pendingRequests) ?>
          </span>
        <?php endif; ?>
      </a>
    </div>
  </div>

  
  <?php if ($current_tab === 'approval'): ?>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="glass rounded-2xl border border-indigo-100 dark:border-slate-600 p-6 hover:shadow-md transition">
        <div class="flex items-center">
          <div class="p-4 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl">
            <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 text-2xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-600 dark:text-slate-400 font-medium">Pending</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= count($pendingRequests) ?></p>
          </div>
        </div>
      </div>
      <div class="glass rounded-2xl border border-indigo-100 dark:border-slate-600 p-6 hover:shadow-md transition">
        <div class="flex items-center">
          <div class="p-4 bg-green-100 dark:bg-green-900/30 rounded-xl">
            <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-2xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-600 dark:text-slate-400 font-medium">Approved (30d)</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">
              <?= count(array_filter($processedRequests, fn($r) => $r['status'] === 'approved')) ?>
            </p>
          </div>
        </div>
      </div>
      <div class="glass rounded-2xl border border-indigo-100 dark:border-slate-600 p-6 hover:shadow-md transition">
        <div class="flex items-center">
          <div class="p-4 bg-red-100 dark:bg-red-900/30 rounded-xl">
            <i class="fas fa-times-circle text-red-600 dark:text-red-400 text-2xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-600 dark:text-slate-400 font-medium">Rejected (30d)</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">
              <?= count(array_filter($processedRequests, fn($r) => $r['status'] === 'rejected')) ?>
            </p>
          </div>
        </div>
      </div>
    </div>

    
    <div class="glass rounded-2xl border border-indigo-100 dark:border-slate-600 mb-8 overflow-hidden">
      <div class="p-6 border-b border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
          <span class="w-3 h-3 bg-yellow-500 rounded-full animate-pulse mr-3"></span>
          Permintaan Pending
        </h2>
      </div>
      <div class="overflow-x-auto">
        <?php if (count($pendingRequests) > 0): ?>
          <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
            <thead class="bg-gray-50 dark:bg-slate-700/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Request Time</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Username</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Role</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
              <?php foreach ($pendingRequests as $request): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    <i class="fas fa-calendar-alt mr-2 text-gray-400 dark:text-slate-500"></i>
                    <?= date('d/m/Y H:i', strtotime($request['created_at'])) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($request['nama_lengkap']) ?></div>
                    <div class="text-sm text-gray-500 dark:text-slate-400">
                      <i class="fas fa-envelope mr-1"></i><?= htmlspecialchars($request['email']) ?>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-white">
                    <i class="fas fa-at mr-1 text-gray-400 dark:text-slate-500"></i>
                    <?= htmlspecialchars($request['username']) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-400">
                      <i class="fas fa-user-tag mr-1"></i>
                      <?= htmlspecialchars($request['role']) ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button onclick="approveRequestAction(<?= $request['id'] ?>)" 
                      class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-100 dark:bg-green-900/30 hover:bg-green-200 dark:hover:bg-green-900/50 text-green-700 dark:text-green-400 rounded-lg font-medium transition mr-2">
                      <i class="fas fa-check"></i> Approve
                    </button>
                    <button onclick="rejectRequestAction(<?= $request['id'] ?>)" 
                      class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50 text-red-700 dark:text-red-400 rounded-lg font-medium transition">
                      <i class="fas fa-times"></i> Reject
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="p-12 text-center bg-white dark:bg-slate-800">
            <div class="w-20 h-20 bg-gray-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-check-circle text-gray-300 dark:text-slate-600 text-4xl"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-lg font-medium">Tidak ada permintaan pending</p>
            <p class="text-gray-400 dark:text-slate-500 text-sm mt-1">Semua permintaan telah diproses</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    
    <div class="glass rounded-2xl border border-indigo-100 dark:border-slate-600 overflow-hidden">
      <div class="p-6 border-b border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <i class="fas fa-history text-indigo-600"></i>
          Riwayat (30 Hari Terakhir)
        </h2>
      </div>
      <div class="overflow-x-auto">
        <?php if (count($processedRequests) > 0): ?>
          <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
            <thead class="bg-gray-50 dark:bg-slate-700/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Request Time</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Processed By</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Processed Time</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Notes</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
              <?php foreach ($processedRequests as $request): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    <i class="fas fa-calendar-alt mr-2 text-gray-400 dark:text-slate-500"></i>
                    <?= date('d/m/Y H:i', strtotime($request['created_at'])) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($request['nama_lengkap']) ?></div>
                    <div class="text-sm text-gray-500 dark:text-slate-400 font-mono">
                      <i class="fas fa-at mr-1"></i><?= htmlspecialchars($request['username']) ?>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $request['status'] === 'approved' ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400' ?>">
                      <i class="fas <?= $request['status'] === 'approved' ? 'fa-check-circle' : 'fa-times-circle' ?> mr-1"></i>
                      <?= $request['status'] === 'approved' ? 'Approved' : 'Rejected' ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    <i class="fas fa-user-shield mr-1 text-gray-400 dark:text-slate-500"></i>
                    <?= htmlspecialchars($request['processed_by_name'] ?? '-') ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    <i class="fas fa-clock mr-2 text-gray-400 dark:text-slate-500"></i>
                    <?= date('d/m/Y H:i', strtotime($request['processed_at'])) ?>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500 dark:text-slate-400 max-w-xs truncate">
                    <?= htmlspecialchars($request['notes'] ?: '-') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="p-12 text-center bg-white dark:bg-slate-800">
            <div class="w-20 h-20 bg-gray-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-history text-gray-300 dark:text-slate-600 text-4xl"></i>
            </div>
            <p class="text-gray-500 dark:text-slate-400 text-lg font-medium">Belum ada riwayat</p>
            <p class="text-gray-400 dark:text-slate-500 text-sm mt-1">Riwayat akan muncul setelah ada permintaan yang diproses</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    
    <div id="rejectApprovalModal" class="fixed inset-0 bg-black/60 dark:bg-black/80 items-center justify-center z-50 backdrop-blur-sm hidden">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 max-w-md w-full mx-4 border border-gray-200 dark:border-slate-700">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center">
            <i class="fas fa-times-circle text-red-600 dark:text-red-400 text-xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white">Reject Request</h3>
        </div>
        
        <form id="rejectApprovalForm" method="POST">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="request_id" id="rejectRequestId">
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">
              <i class="fas fa-comment-alt mr-1"></i>Alasan Penolakan <span class="text-gray-400 dark:text-slate-500">(Opsional)</span>
            </label>
            <textarea name="notes" rows="4" 
              class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 text-gray-800 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-red-500 dark:focus:ring-red-600 focus:border-transparent transition"
              placeholder="Tulis alasan penolakan di sini..."></textarea>
          </div>
          <div class="flex flex-col sm:flex-row gap-3">
            <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded-xl transition flex items-center justify-center gap-2">
              <i class="fas fa-ban"></i>
              Reject Request
            </button>
            <button type="button" onclick="closeRejectApprovalModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-800 dark:text-white font-semibold py-3 px-4 rounded-xl transition flex items-center justify-center gap-2">
              <i class="fas fa-times"></i>
              Batal
            </button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  
  <?php if ($current_tab === 'accounts'): ?>
  
  <div class="glass rounded-xl px-4 py-4 flex flex-col md:flex-row gap-3 md:gap-4 items-center mb-8 border border-indigo-100 dark:border-slate-600 shadow-sm">
    <input type="text" placeholder="Cari nama, username, atau email..." class="w-full md:max-w-xs px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:border-indigo-500 dark:focus:border-indigo-400 transition-all text-sm" id="userSearch" oninput="filterUsers()">
    <select class="w-full md:max-w-xs px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:border-indigo-500 dark:focus:border-indigo-400 transition-all text-sm" id="roleFilter" onchange="filterUsers()">
      <option value="">Semua Role</option>
      <option value="administrator">Administrator</option>
      <option value="direktur">Direktur</option>
      <option value="technician_manager">Technician Manager</option>
      <option value="technician">Technician</option>
      <option value="hse">HSE</option>
      <option value="sales">Sales</option>
      <option value="internship">Internship</option>
      <option value="daily">Daily</option>
    </select>
    <select class="w-full md:max-w-xs px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:border-indigo-500 dark:focus:border-indigo-400 transition-all text-sm" id="statusFilter" onchange="filterUsers()">
      <option value="">Semua Status</option>
      <option value="1">Aktif</option>
      <option value="0">Nonaktif</option>
    </select>
  </div>

  
  <div class="grid gap-8 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4" id="userGrid">
    <?php foreach ($users as $u): ?>
      <div class="user-card glass border border-indigo-100 dark:border-slate-600 shadow-sm rounded-2xl p-6 flex flex-col items-center gap-3 min-h-[370px] relative transition-all hover:shadow-xl"
        data-id="<?= $u['id'] ?>"
        data-full_name="<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>"
        data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
        data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>"
        data-gender="<?= htmlspecialchars($u['gender']) ?>"
        data-role="<?= htmlspecialchars($u['role']) ?>"
        data-status="<?= $u['is_active'] ?>"
        data-avatar="<?= htmlspecialchars($u['avatar']) ?>"
        data-phone="<?= htmlspecialchars($u['phone'] ?? '', ENT_QUOTES) ?>"
        data-address="<?= htmlspecialchars($u['address'] ?? '', ENT_QUOTES) ?>"
        data-birth_date="<?= htmlspecialchars($u['birth_date'] ?? '') ?>"
        data-linkedin="<?= htmlspecialchars($u['linkedin'] ?? '', ENT_QUOTES) ?>"
      >
        <div class="relative">
          <?php $__avatarUrl = getAvatarUrl($u); $__cacheSep = (strpos($__avatarUrl, '?') === false) ? '?' : '&'; ?>
          <img src="<?= htmlspecialchars($__avatarUrl . $__cacheSep . 'v=' . time(), ENT_QUOTES) ?>" alt="Avatar" class="w-16 h-16 rounded-full object-cover border-4 border-indigo-50 dark:border-slate-600 bg-slate-100 dark:bg-slate-700 shadow" />
          <?php if(!$u['is_active']): ?>
            <div class="absolute inset-0 bg-white/80 dark:bg-slate-800/80 rounded-full flex items-center justify-center">
              <svg class="w-7 h-7 text-gray-300 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M18.364 5.636l-12.728 12.728" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
          <?php endif; ?>
        </div>
        <div class="font-semibold text-indigo-900 dark:text-indigo-300 text-lg text-center"><?= htmlspecialchars($u['full_name']) ?></div>
        <div class="flex flex-wrap gap-1 justify-center items-center mb-2">
          <span class="text-xs bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 rounded-full px-2"><?= htmlspecialchars($u['username']) ?></span>
          <?php if (!empty($u['gender'])): ?>
            <span class="inline-flex items-center gap-1 text-xs rounded-full px-2 py-0.5 
              <?= $u['gender']=='male'?'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400':'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-400' ?>">
              <?= $u['gender']=='male'
                ? '<svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 7V3m0 0h4m-4 0l-5 5m6 3a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> Laki-laki'
                : '<svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 14a7 7 0 100-14 7 7 0 000 14zm0 0v7"/></svg> Perempuan'
              ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400 text-center mb-1"><?= htmlspecialchars($u['email']) ?></div>
        <div class="flex gap-2 items-center justify-center flex-wrap">
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold
            <?= ($u['role']=='administrator' || $u['role']=='direktur') ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400' :
                 ($u['role']=='technician_manager' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' :
                 ($u['role']=='technician' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400')) ?>">
            <?= ucwords(str_replace('_',' ',$u['role'])) ?>
          </span>
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $u['is_active']?'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400':'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500' ?>">
            <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
          </span>
        </div>
        <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 mb-2">Dibuat: <?= date('d M Y', strtotime($u['created_at'])) ?></div>
        <div class="flex gap-2 mt-auto flex-wrap justify-center w-full">
          <button class="btn-main btn-sm flex items-center gap-1" onclick="openUserForm(<?= $u['id'] ?>)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-2.036a2.5 2.5 0 11-3.536 3.536L6 21h-3v-3l9.732-9.732z"></path></svg>
            Edit
          </button>
          <button class="btn-alt btn-sm flex items-center gap-1 reset-password-btn" data-userid="<?= $u['id'] ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m0 4v.01m5-4a7 7 0 10-10 0"></path></svg>
            Reset
          </button>
          <?php if($u['is_active']): ?>
            <button class="btn-alt btn-sm flex items-center gap-1" onclick="toggleStatus(<?= $u['id'] ?>,0)">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18.364 5.636l-12.728 12.728"></path></svg>
            </button>
          <?php else: ?>
            <button class="btn-alt btn-sm flex items-center gap-1" onclick="toggleStatus(<?= $u['id'] ?>,1)">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg>
            </button>
          <?php endif; ?>
          <button class="btn-danger btn-sm flex items-center gap-1 delete-account-btn" data-userid="<?= $u['id'] ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Hapus
          </button>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
      <div class="col-span-full text-center text-slate-400 text-lg py-20">Belum ada data karyawan.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>


<div class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 transition-all duration-200 p-4" id="userModal" style="backdrop-filter: blur(4px);">
  <div class="backdrop-blur-md bg-white/80 dark:bg-slate-800/80 rounded-2xl shadow-2xl w-full max-w-2xl relative max-h-[90vh] overflow-hidden flex flex-col border border-white/50 dark:border-slate-700/50 animate-fadeInUp">
    <div class="flex items-start justify-between p-6 border-b border-white/30 dark:border-slate-700/50">
      <div>
        <h2 class="text-3xl font-bold text-slate-900 dark:text-white" id="userModalTitle">Tambah Akun Karyawan</h2>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Isi formulir di bawah untuk menambahkan atau mengedit akun karyawan baru</p>
      </div>
      <button class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-400 transition p-1" onclick="closeUserForm()" aria-label="Tutup">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
    </div>
    
    <form id="userForm" autocomplete="off" enctype="multipart/form-data" class="flex flex-col gap-6 overflow-y-auto p-6">
      <input type="hidden" name="id" id="userFormId" />
      
      
      <div class="flex flex-col items-center">
        <div class="relative">
          <img id="userFormAvatarPreview" src="" alt="Preview" class="w-32 h-32 rounded-full object-cover border-4 border-indigo-200 dark:border-indigo-700 bg-slate-100 dark:bg-slate-700 shadow-lg hidden transition-all duration-200" />
          <div id="userFormAvatarPlaceholder" class="w-32 h-32 rounded-full bg-gradient-to-br from-indigo-100 to-indigo-50 dark:from-indigo-900/40 dark:to-indigo-800/40 border-4 border-indigo-200 dark:border-indigo-700 flex items-center justify-center shadow-lg">
            <svg class="w-16 h-16 text-indigo-300 dark:text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
          </div>
        </div>
        <label class="mt-4 inline-block px-5 py-2 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-700 dark:hover:bg-indigo-600 text-white rounded-lg cursor-pointer border border-indigo-600 dark:border-indigo-600 transition font-medium text-sm shadow-md">
          Pilih Avatar
          <input type="file" name="avatar" id="userFormAvatar" accept="image/*" class="hidden" />
        </label>
        <button type="button" id="resetAvatarBtn" class="mt-2 px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600 text-xs hover:bg-slate-200 dark:hover:bg-slate-600 transition hidden">Reset Foto</button>
        <span id="userFormAvatarName" class="text-xs text-slate-500 dark:text-slate-400 mt-2"></span>
      </div>

      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="flex flex-col">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Nama Lengkap</label>
          <input type="text" name="full_name" id="userFormFullName" required class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition" placeholder="John Doe" />
        </div>

        <div class="flex flex-col">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Username</label>
          <input type="text" name="username" id="userFormUsername" required class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition" placeholder="johndoe" />
        </div>

        <div class="flex flex-col md:col-span-2">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Email</label>
          <input type="email" name="email" id="userFormEmail" required class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition" placeholder="john@example.com" />
        </div>

        <div class="flex flex-col">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">No. HP</label>
          <input type="text" name="phone" id="userFormPhone" class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition" placeholder="08xxxxxxxxxx" />
        </div>

        <div class="flex flex-col">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Tanggal Lahir</label>
          <input type="date" name="birth_date" id="userFormBirthDate" class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition" />
        </div>

        <div class="flex flex-col">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Gender</label>
          <select name="gender" id="userFormGender" class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition">
            <option value="">Pilih Gender</option>
            <option value="male">Laki-laki</option>
            <option value="female">Perempuan</option>
          </select>
        </div>

        <div class="flex flex-col">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Role</label>
          <select name="role" id="userFormRole" required class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition">
            <option value="">Pilih Role</option>
            <option value="administrator">Administrator</option>
            <option value="direktur">Direktur</option>
            <option value="technician_manager">Technician Manager</option>
            <option value="technician">Technician</option>
            <option value="hse">HSE</option>
            <option value="sales">Sales</option>
            <option value="internship">Internship</option>
            <option value="daily">Daily</option>
          </select>
        </div>

        <div class="flex flex-col" id="userFormPasswordWrap">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Password</label>
          <input type="text" name="password" id="userFormPassword" minlength="6" class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition" placeholder="Minimal 6 karakter" />
        </div>

        <div class="flex flex-col">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Status</label>
          <select name="is_active" id="userFormStatus" class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition">
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
          </select>
        </div>

        <div class="flex flex-col">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">LinkedIn</label>
          <input type="text" name="linkedin" id="userFormLinkedin" class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition" placeholder="https://linkedin.com/in/..." />
        </div>

        <div class="flex flex-col md:col-span-2">
          <label class="block text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Alamat</label>
          <textarea name="address" id="userFormAddress" rows="2" class="px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition" placeholder="Alamat lengkap"></textarea>
        </div>
      </div>
    </form>

    
    <div class="flex justify-end gap-3 p-6 border-t border-white/30 dark:border-slate-700/50 bg-white/40 dark:bg-slate-900/30">
      <button type="button" onclick="closeUserForm()" class="px-6 py-2.5 bg-slate-200/80 dark:bg-slate-700/80 hover:bg-slate-300/80 dark:hover:bg-slate-600/80 text-slate-700 dark:text-slate-200 rounded-lg font-medium transition">Batal</button>
      <button type="submit" form="userForm" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-700 dark:hover:bg-indigo-600 text-white rounded-lg font-medium transition shadow-lg">Simpan Akun</button>
    </div>
  </div>
</div>


<div id="resetPasswordModal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
  <div class="glass rounded-2xl p-8 w-full max-w-sm border border-indigo-100 dark:border-slate-600">
    <h3 class="text-lg font-bold mb-3 text-indigo-900 dark:text-indigo-300">Reset Password User</h3>
    <form id="resetPasswordForm" class="flex flex-col gap-3">
      <input type="hidden" name="user_id" id="resetUserId" />
      <div>
        <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Password Baru</label>
        <input type="text" name="new_password" required class="input-tw" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Konfirmasi Password</label>
        <input type="text" name="confirm_password" required class="input-tw" />
      </div>
      <div class="flex justify-end gap-2 mt-2">
        <button type="button" onclick="closeResetPasswordModal()" class="btn-alt">Batal</button>
        <button type="submit" class="btn-main">Reset</button>
      </div>
    </form>
  </div>
</div>


<div id="deleteAccountModal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
  <div class="glass rounded-2xl p-8 w-full max-w-sm border border-indigo-100 dark:border-slate-600">
    <h3 class="text-lg font-bold mb-3 text-red-700 dark:text-red-400">Hapus Akun</h3>
    <p class="mb-4 text-slate-700 dark:text-slate-300">Apakah Anda yakin ingin menghapus akun ini? <br><span class="text-sm text-red-500 dark:text-red-400 font-semibold">Tindakan ini tidak dapat dibatalkan!</span></p>
    <form id="deleteAccountForm" class="flex flex-col gap-3">
      <input type="hidden" name="user_id" id="deleteUserId" />
      <div class="flex justify-end gap-2">
        <button type="button" onclick="closeDeleteAccountModal()" class="btn-alt">Batal</button>
        <button type="submit" class="btn-danger">Hapus</button>
      </div>
    </form>
  </div>
</div>

<div id="snackbar" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-indigo-700 dark:bg-indigo-600 text-white px-6 py-3 rounded-full shadow-lg z-50 hidden text-base font-semibold"></div>

<script>

function filterUsers() {
  const search = document.getElementById('userSearch').value.toLowerCase();
  const role = document.getElementById('roleFilter').value;
  const status = document.getElementById('statusFilter').value;
  document.querySelectorAll('.user-card').forEach(card => {
    let visible = true;
    if (search && !(card.dataset.full_name.toLowerCase().includes(search) || card.dataset.username.toLowerCase().includes(search) || card.dataset.email.toLowerCase().includes(search))) visible = false;
    if (role && card.dataset.role !== role) visible = false;
    if (status && card.dataset.status !== status) visible = false;
    card.style.display = visible ? '' : 'none';
  });
}


function openUserForm(id) {
  document.getElementById('userModalTitle').innerText = id ? "Edit Akun Karyawan" : "Tambah Akun Karyawan";
  document.getElementById('userForm').reset();
  document.getElementById('userModal').classList.remove('hidden');
  document.getElementById('userFormId').value = id || '';

  var passWrap = document.getElementById('userFormPasswordWrap');
  if (passWrap) passWrap.style.display = id ? 'none' : '';

  const avatarPreview = document.getElementById('userFormAvatarPreview');
  const avatarPlaceholder = document.getElementById('userFormAvatarPlaceholder');
  if (avatarPreview) {
    avatarPreview.classList.add('hidden');
    avatarPreview.src = "";
  }
  if (avatarPlaceholder) {
    avatarPlaceholder.classList.remove('hidden');
  }
  if (document.getElementById('userFormAvatarName')) {
    document.getElementById('userFormAvatarName').textContent = "";
  }
  if (document.getElementById('resetAvatarBtn')) {
    document.getElementById('resetAvatarBtn').classList.add('hidden');
  }
  if (document.getElementById('userFormAvatar')) {
    document.getElementById('userFormAvatar').value = "";
  }

  if (id) {
    var card = document.querySelector('.user-card[data-id="' + id + '"]');
    if (card) {
      document.getElementById('userFormFullName').value = card.getAttribute('data-full_name');
      document.getElementById('userFormUsername').value = card.getAttribute('data-username');
      document.getElementById('userFormEmail').value = card.getAttribute('data-email');
      document.getElementById('userFormGender').value = card.getAttribute('data-gender');
      document.getElementById('userFormRole').value = card.getAttribute('data-role');
      document.getElementById('userFormStatus').value = card.getAttribute('data-status');
      document.getElementById('userFormPhone').value = card.getAttribute('data-phone') || '';
      document.getElementById('userFormBirthDate').value = card.getAttribute('data-birth_date') || '';
      document.getElementById('userFormLinkedin').value = card.getAttribute('data-linkedin') || '';
      document.getElementById('userFormAddress').value = card.getAttribute('data-address') || '';
      let avatarUrl = card.querySelector('img').getAttribute('src');
      if (avatarUrl.includes('serve_image.php?')) {
        avatarUrl = avatarUrl.replace(/&?\d{10,}$/, '').replace(/\?(\d{10,})$/, '');
      } else {
        avatarUrl = avatarUrl.split('?')[0];
      }
      if (avatarPreview) {
        avatarPreview.src = avatarUrl;
        avatarPreview.classList.remove('hidden');
      }
      if (avatarPlaceholder) {
        avatarPlaceholder.classList.add('hidden');
      }
      if (document.getElementById('resetAvatarBtn')) {
        document.getElementById('resetAvatarBtn').classList.remove('hidden');
      }
    }
  }
}
function closeUserForm() {
  document.getElementById('userModal').classList.add('hidden'); 
}

(function() {
  var avatarInput = document.getElementById('userFormAvatar');
  if (avatarInput) {
    avatarInput.addEventListener('change', function() {
      var preview = document.getElementById('userFormAvatarPreview');
      var placeholder = document.getElementById('userFormAvatarPlaceholder');
      var nameSpan = document.getElementById('userFormAvatarName');
      var resetBtn = document.getElementById('resetAvatarBtn');
      if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
          if (preview) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
          }
          if (placeholder) {
            placeholder.classList.add('hidden');
          }
          if (resetBtn) {
            resetBtn.classList.remove('hidden');
          }
        };
        reader.readAsDataURL(this.files[0]);
        if (nameSpan) nameSpan.textContent = this.files[0].name;
      }
    });
  }

  var resetBtn = document.getElementById('resetAvatarBtn');
  if (resetBtn) {
    resetBtn.addEventListener('click', function() {
      var preview = document.getElementById('userFormAvatarPreview');
      var placeholder = document.getElementById('userFormAvatarPlaceholder');
      var avatarInput = document.getElementById('userFormAvatar');
      var nameSpan = document.getElementById('userFormAvatarName');
      if (preview) {
        preview.src = '';
        preview.classList.add('hidden');
      }
      if (placeholder) {
        placeholder.classList.remove('hidden');
      }
      if (avatarInput) {
        avatarInput.value = '';
      }
      if (nameSpan) {
        nameSpan.textContent = '';
      }
      this.classList.add('hidden');
    });
  }
})();


document.querySelectorAll('.reset-password-btn').forEach(btn => {
  btn.onclick = function() {
    document.getElementById('resetUserId').value = this.getAttribute('data-userid');
    document.getElementById('resetPasswordModal').classList.remove('hidden');
  };
});
function closeResetPasswordModal() {
  document.getElementById('resetPasswordModal').classList.add('hidden');
}
document.getElementById('resetPasswordForm').onsubmit = function(e) {
  e.preventDefault();
  var uid = document.getElementById('resetUserId').value;
  var pass1 = this.new_password.value;
  var pass2 = this.confirm_password.value;
  if(pass1 !== pass2) { showSnackbar('Password tidak sama!'); return; }
  fetch(BASE_URL + 'app/action/account-handler-resetpw.php', {
    method: "POST",
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({user_id: uid, new_password: pass1})
  })
  .then(r=>r.text()).then(r=>{
    if(r.trim()==='OK') {
      showSnackbar('Password berhasil direset!');
      setTimeout(()=>location.reload(), 1000);
    } else {
      showSnackbar('Gagal reset password: ' + r);
    }
  }).catch(err => {
    showSnackbar('Error: ' + err.message);
  });
};


document.querySelectorAll('.delete-account-btn').forEach(btn => {
  btn.onclick = function() {
    document.getElementById('deleteUserId').value = this.getAttribute('data-userid');
    document.getElementById('deleteAccountModal').classList.remove('hidden');
  };
});
function closeDeleteAccountModal() {
  document.getElementById('deleteAccountModal').classList.add('hidden');
}
document.getElementById('deleteAccountForm').onsubmit = function(e) {
  e.preventDefault();
  var uid = document.getElementById('deleteUserId').value;
  fetch(BASE_URL + 'app/action/account-handler-delete.php', {
    method: "POST",
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({user_id: uid})
  })
  .then(r=>r.text()).then(r=>{
    if(r.trim()==='OK') {
      showSnackbar('Akun berhasil dihapus!');
      setTimeout(()=>location.reload(), 1000);
    } else {
      showSnackbar('Gagal menghapus akun: ' + r);
    }
  }).catch(err => {
    showSnackbar('Error: ' + err.message);
  });
};

function showSnackbar(msg) {
  const bar = document.getElementById('snackbar');
  bar.innerText = msg;
  bar.classList.remove('hidden');
  bar.style.opacity = 1;
  setTimeout(() => {
    bar.style.opacity = 0;
    setTimeout(() => bar.classList.add('hidden'), 350);
  }, 2200);
}

function toggleStatus(id, to) {
  fetch(BASE_URL + 'app/action/account-handler-toggle-status.php', {
    method: "POST",
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({user_id: id, to: to})
  })
  .then(r=>r.text()).then(r=>{
    if(r.trim()==='OK') {
      showSnackbar('Status akun berhasil diubah!');
      setTimeout(()=>location.reload(), 1000);
    } else {
      showSnackbar('Gagal mengubah status akun: ' + r);
    }
  }).catch(err => {
    showSnackbar('Error: ' + err.message);
  });
}

document.getElementById('userForm').onsubmit = function(e) {
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);
  fetch(BASE_URL + 'app/action/account-handler-save.php', {
    method: "POST",
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: data
  })
  .then(resp => resp.text())
  .then(resp => {
    if (resp.trim() === "OK") {
      showSnackbar('Data akun berhasil disimpan!');
      setTimeout(() => location.reload(), 1000);
    } else {
      showSnackbar('Gagal simpan akun: ' + resp);
    }
  }).catch(err => {
    showSnackbar('Error: ' + err.message);
  });
};


function togglePasswordField(inputId) {
  const input = document.getElementById(inputId);
  const icon = document.getElementById(inputId + '-icon');
  if (input && icon) {
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }
}

function approveRequestAction(requestId) {
  if (confirm('Approve password reset request ini?\n\nAnda akan diminta untuk mengatur password baru setelah approval.')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="request_id" value="${requestId}">
    `;
    document.body.appendChild(form);
    form.submit();
  }
}

function rejectRequestAction(requestId) {
  document.getElementById('rejectRequestId').value = requestId;
  const modal = document.getElementById('rejectApprovalModal');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
}

function closeRejectApprovalModal() {
  const modal = document.getElementById('rejectApprovalModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
  const form = document.getElementById('rejectApprovalForm');
  if (form) form.reset();
}

function closeApproveSuccessModal() {
  if (confirm('[PERINGATAN] Password belum direset!\n\nYakin ingin membatalkan? Request akan tetap berstatus Approved dan user masih belum bisa login dengan password baru.')) {
    window.location.href = '?page=account&tab=approval';
  }
}


const approveResetForm = document.getElementById('approveResetPasswordForm');
if (approveResetForm) {
  approveResetForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const newPassword = document.getElementById('approveNewPassword').value;
    const confirmPassword = document.getElementById('approveConfirmPassword').value;
    const errorDiv = document.getElementById('approvePasswordError');
    const errorText = document.getElementById('approvePasswordErrorText');
    const submitBtn = this.querySelector('button[type="submit"]');
    
    if (errorDiv) errorDiv.classList.add('hidden');
    

    if (newPassword.length < 6) {
      if (errorText) errorText.textContent = 'Password minimal 6 karakter';
      if (errorDiv) errorDiv.classList.remove('hidden');
      document.getElementById('approveNewPassword').focus();
      return;
    }
    
    if (newPassword !== confirmPassword) {
      if (errorText) errorText.textContent = 'Password tidak cocok! Periksa kembali kedua password yang Anda masukkan.';
      if (errorDiv) errorDiv.classList.remove('hidden');
      document.getElementById('approveConfirmPassword').focus();
      return;
    }
    

    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...';
    
    const formData = new FormData(this);
    
    try {
      const response = await fetch(BASE_URL + 'app/action/account-handler-resetpw.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });
      
      const data = await response.text();
      
      if (data.startsWith('OK')) {

        submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Berhasil!';
        submitBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        submitBtn.classList.add('bg-green-600');
        
        setTimeout(() => {
          showToast('Password berhasil direset! User sekarang dapat login dengan password baru.', 'success');
          window.location.href = '?page=account&tab=approval';
        }, 500);
      } else {
        if (errorText) errorText.textContent = data;
        if (errorDiv) errorDiv.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    } catch (error) {
      if (errorText) errorText.textContent = 'Terjadi kesalahan koneksi: ' + error.message;
      if (errorDiv) errorDiv.classList.remove('hidden');
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }
  });
}


document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const rejectModal = document.getElementById('rejectApprovalModal');
    if (rejectModal && !rejectModal.classList.contains('hidden')) {
      closeRejectApprovalModal();
    }
  }
});


const rejectModal = document.getElementById('rejectApprovalModal');
if (rejectModal) {
  rejectModal.addEventListener('click', function(e) {
    if (e.target === this) {
      closeRejectApprovalModal();
    }
  });
}
</script>
</body>

