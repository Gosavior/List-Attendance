<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth.php';
requireLogin();


$allowedRoles = ['administrator', 'technician_manager', 'sales', 'technician'];
if (!has_role($allowedRoles)) {
    http_response_code(403);
    die('Akses ditolak.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';

$user = $_SESSION;
$userName = htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
$userRole = htmlspecialchars($user['role'] ?? '', ENT_QUOTES, 'UTF-8');
$userId = (int)($user['user_id'] ?? 0);


$reportUrl = 'https://report.arthasolusiaditama.com/?user=' . urlencode($user['full_name'] ?? '') 
           . '&role=' . urlencode($user['role'] ?? '');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Generator - PT. Artha Solusi Aditama</title>
    <link href="../../src/output.css?v=<?= @filemtime(__DIR__ . '/../../../src/output.css') ?: time() ?>" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">

    
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white shadow-lg">
        <div class="max-w-3xl mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <img src="../../public/assets/images/logo.png" alt="Logo" class="h-12 w-12">
                    <div>
                        <h1 class="text-2xl font-extrabold">Report Generator</h1>
                        <p class="text-sm text-blue-200">PT. Artha Solusi Aditama</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-sm font-semibold"><?= $userName ?></p>
                        <p class="text-xs text-blue-200 capitalize"><?= $userRole ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="max-w-3xl mx-auto px-4 py-10">

        
        <div class="bg-white rounded-xl shadow-lg p-8 text-center">
            <div class="mb-6">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full mb-4">
                    <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Report Generator v2</h2>
                <p class="text-gray-500 max-w-md mx-auto">
                    Buat laporan service untuk ASA dan GMS dengan template profesional. 
                    Klik tombol di bawah untuk melanjutkan ke halaman Report Generator.
                </p>
            </div>

            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-blue-600 font-bold text-lg mb-1">ASA</div>
                    <p class="text-xs text-gray-500">Service Report Artha Solusi Aditama</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-green-600 font-bold text-lg mb-1">GMS</div>
                    <p class="text-xs text-gray-500">Service Report Gandri Mitra Sukses</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-purple-600 font-bold text-lg mb-1">PDF</div>
                    <p class="text-xs text-gray-500">Generate & Download PDF</p>
                </div>
            </div>

            
            <a href="<?= $reportUrl ?>" 
               target="_blank"
               rel="noopener noreferrer"
               class="inline-flex items-center gap-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-4 px-8 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 text-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
                Lanjut Membuat Report
            </a>

            <p class="text-xs text-gray-400 mt-4">
                Akan membuka <strong>report.arthasolusiaditama.com</strong> di tab baru
            </p>
        </div>

    </div>

</body>
</html>