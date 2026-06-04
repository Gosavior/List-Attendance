<?php
header("HTTP/1.0 404 Not Found");
$currentPage = '404';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Tidak Ditemukan - PT. Artha Solusi Aditama</title>
    <link rel="icon" href="public/assets/images/logo.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-slate-100 min-h-screen flex items-center justify-center">
    <div class="max-w-4xl mx-auto px-4 text-center">
        <div class="mb-8">
            <img src="public/assets/images/logo.png" alt="Logo" class="h-24 mx-auto mb-6">
            <h1 class="text-9xl font-bold text-blue-600 mb-4">404</h1>
            <h2 class="text-3xl font-bold text-slate-800 mb-4">Halaman Tidak Ditemukan</h2>
            <p class="text-lg text-slate-600 mb-8 max-w-2xl mx-auto">
                Maaf, halaman yang Anda cari tidak tersedia. Mungkin telah dipindahkan, dihapus, atau URL-nya salah.
            </p>
        </div>
        
        <div class="grid md:grid-cols-3 gap-6 mb-12">
            <a href="/" class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-home text-blue-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-slate-800 mb-2">Kembali ke Home</h3>
                <p class="text-slate-600 text-sm">Kembali ke halaman utama website</p>
            </a>
            
            <a href="/services" class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-cogs text-green-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-slate-800 mb-2">Layanan Kami</h3>
                <p class="text-slate-600 text-sm">Lihat layanan HVACR kami</p>
            </a>
            
            <a href="/contact" class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                <div class="w-16 h-16 bg-sky-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-envelope text-sky-600 text-2xl"></i>
                </div>
                <h3 class="font-bold text-slate-800 mb-2">Hubungi Kami</h3>
                <p class="text-slate-600 text-sm">Konsultasi kebutuhan HVACR Anda</p>
            </a>
        </div>
        
        <div class="space-y-4">
            <a href="/" class="inline-flex items-center gap-3 bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-full font-semibold transition duration-300">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Homepage
            </a>
            <p class="text-slate-500 text-sm mt-6">
                © 2025 PT. Artha Solusi Aditama. HVACR Contractor and Specialist.
            </p>
        </div>
    </div>
</body>
</html>