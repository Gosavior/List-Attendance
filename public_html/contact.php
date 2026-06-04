<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../public/assets/images/logo.png" type="image/x-icon">
    <title>Contact Us - PT. Artha Solusi Aditama</title>
    <meta name="description" content="Hubungi PT. Artha Solusi Aditama untuk konsultasi HVACR, layanan darurat, dan kebutuhan proyek Anda di Batam dan seluruh Indonesia.">
    <link rel="canonical" href="https://www.arthasolusiaditama.com/contact">
    <meta name="robots" content="index,follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Contact PT. Artha Solusi Aditama">
    <meta property="og:description" content="Dapatkan dukungan HVACR profesional melalui telepon, email, atau kunjungan kantor kami di Batam.">
    <meta property="og:url" content="https://www.arthasolusiaditama.com/contact">
    <meta property="og:image" content="https://www.arthasolusiaditama.com/public/assets/images/Foto Outdoor.jpg">
    <meta property="og:image:alt" content="Kantor PT. Artha Solusi Aditama">
    <meta property="og:site_name" content="PT. Artha Solusi Aditama">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Contact PT. Artha Solusi Aditama">
    <meta name="twitter:description" content="Terhubung dengan tim HVACR kami untuk konsultasi profesional.">
    <meta name="twitter:image" content="https://www.arthasolusiaditama.com/public/assets/images/Foto Outdoor.jpg">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ContactPage",
        "url": "https://www.arthasolusiaditama.com/contact",
        "publisher": {
            "@type": "Organization",
            "name": "PT. Artha Solusi Aditama",
            "logo": "https://www.arthasolusiaditama.com/public/assets/images/logo.png"
        },
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "+62-811-7001-5158",
            "contactType": "sales",
            "areaServed": "ID",
            "availableLanguage": ["id", "en"],
            "email": "info@pt-asa.com"
        },
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Lytech Industrial Park C2 No 3, Belian",
            "addressLocality": "Batam Kota",
            "addressRegion": "Kepulauan Riau",
            "postalCode": "29444",
            "addressCountry": "ID"
        }
    }
    </script>
    <link rel="stylesheet" href="./src/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Semua style dari index.php disalin persis di sini */
        .hero-gradient-bg {
            position: relative;
            background: linear-gradient(120deg, #fff 0%, #e0f2fe 25%, #2563eb 70%, #1e293b 100%);
            overflow: hidden;
        }
        .hero-gradient-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(circle at 70% 30%, rgba(30,58,138,0.22) 0%, transparent 60%),
                radial-gradient(circle at 20% 80%, rgba(30,41,59,0.18) 0%, transparent 60%),
                linear-gradient(120deg, rgba(255,255,255,0.6) 0%, rgba(30,41,59,0.25) 100%);
            pointer-events: none;
        }
        .hero-gradient-bg > * {
            position: relative;
            z-index: 1;
        }
        .hero-bg {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 25%, #7dd3fc 50%, #38bdf8 75%, #0ea5e9 100%);
            position: relative;
            overflow: hidden;
        }
        .hero-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(14, 165, 233, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 40% 40%, rgba(6, 182, 212, 0.08) 0%, transparent 50%);
            animation: gradientShift 20s ease-in-out infinite;
        }
        @keyframes gradientShift {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .service-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(14, 165, 233, 0.1);
            border-radius: 24px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(14, 165, 233, 0.08);
        }
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.05), transparent);
            transition: left 0.6s;
        }
        .service-card:hover::before {
            left: 100%;
        }
        .service-card:hover {
            transform: translateY(-12px) scale(1.03);
            box-shadow: 0 20px 40px rgba(14, 165, 233, 0.15), 0 0 40px rgba(14, 165, 233, 0.1);
            border-color: rgba(14, 165, 233, 0.2);
        }
        .client-logo {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            filter: grayscale(100%) contrast(0.8);
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(14, 165, 233, 0.15);
            border-radius: 20px;
        }
        .client-logo:hover {
            transform: scale(1.1) rotate(2deg);
            filter: grayscale(0%) contrast(1.2);
            box-shadow: 0 15px 35px rgba(14, 165, 233, 0.2);
            border-color: rgba(14, 165, 233, 0.3);
        }
        .project-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(14, 165, 233, 0.1);
            border-radius: 24px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(14, 165, 233, 0.08);
        }
        .project-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(14, 165, 233, 0.15);
            border-color: rgba(14, 165, 233, 0.2);
        }
        .fade-in { animation: fadeIn 1.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .slide-up { animation: slideUp 1.2s cubic-bezier(0.4, 0, 0.2, 1); }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(60px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .text-gradient {
            background: linear-gradient(135deg, #0ea5e9, #3b82f6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .page-transition {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            z-index: 9999; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.5s ease;
        }
        .page-transition.active { opacity: 1; visibility: visible; }
        .transition-content { text-align: center; color: white; }
        .loading-spinner {
            width: 60px; height: 4px;
            background: linear-gradient(90deg, #3b82f6, #06b6d4, #3b82f6);
            background-size: 200% 100%;
            animation: loading-bar 1.5s ease-in-out infinite;
            margin: 0 auto 20px; border-radius: 2px;
        }
        @keyframes loading-bar {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        /* Contact info hover effects */
        .contact-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.15);
        }
        .contact-item:hover .fa {
            transform: scale(1.1);
        }

        #mobile-sidebar { pointer-events: none; }
        #mobile-sidebar #sidebar-overlay {
            display: none; /* keep it out of flow when closed */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s;
            z-index: 40;
            pointer-events: none;
            background-color: transparent; /* no dark background when closed */
            backdrop-filter: none !important;
        }
        #mobile-sidebar.open { pointer-events: auto; }
        #mobile-sidebar.open #sidebar-overlay {
            display: block;
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: none !important; /* avoid browser-specific blur artifacts */
        }
        #mobile-sidebar-panel { z-index: 50; pointer-events: auto; box-shadow: none !important; }
        #mobile-sidebar.open #mobile-sidebar-panel { box-shadow: 0 25px 50px rgba(0,0,0,0.25) !important; }
    </style>

    <style>
        /* Ensure hero cards equal height and inner panels stretch on small screens */
            /* top-level rules moved elsewhere */
            @media (max-width: 640px) {
                .hero-cards-grid > div { height: auto !important; }
                .hero-cards-grid > div > .bg-white\/10 { height: auto !important; display: block !important; flex-direction: initial !important; justify-content: initial !important; }
                .hero-cards-grid > div > .bg-white\/10 .w-16.h-16 { margin-bottom: 0.75rem !important; }
            }
        @media (min-width: 641px) and (max-width: 768px) {
            .hero-cards-grid > div { height: 200px !important; }
            .hero-cards-grid > div > .bg-white\/10 { height: 100% !important; display: flex !important; flex-direction: column !important; justify-content: space-between !important; }
            .hero-cards-grid > div > .bg-white\/10 .w-16.h-16 { margin-bottom: 0.5rem !important; }
        }
    </style>

    <style>
        /* Desktop footer grid: explicit column widths for better balance */
        /* Tablet range: make footer behave like mobile (two columns) for mid-size viewports
           so widths around ~700-900px (e.g. 800px) don't show an awkward spread. */
        @media (min-width: 769px) and (max-width: 991px) {
            footer .max-w-7xl .grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                grid-auto-rows: auto;
                gap: 1rem !important;
                position: static;
                padding-top: 0.5rem !important;
            }
            /* Logo full width on top and hide its long description to save space */
            footer .max-w-7xl .grid > :nth-child(1) { grid-column: 1 / -1; display: flex; }
            footer .max-w-7xl .grid > :nth-child(1) p { display: none !important; }
            /* Two column layout: Services left, Contact right. Remove Company column */
            footer .max-w-7xl .grid > :nth-child(2) { grid-column: 1 / 2; }
            footer .max-w-7xl .grid > :nth-child(4) { grid-column: 2 / 3; }
            footer .max-w-7xl .grid > :nth-child(3) { display: none !important; }
            /* Slightly smaller social icons so they fit better in tablet width */
            footer .w-10.h-10 { width: 1.9rem !important; height: 1.9rem !important; }
            footer .w-10.h-10 i { font-size: 0.78rem !important; }
            footer .text-sm { white-space: normal !important; word-wrap: break-word !important; }
        }

        @media (min-width: 992px) {
            /* logo narrow, two flexible middle columns, contact column fixed-ish */
            footer .max-w-7xl .grid {
                grid-template-columns: 220px 1fr 1fr 300px !important;
                align-items: start;
                gap: 2.5rem !important;
            }
            footer .max-w-7xl .grid > :nth-child(1) { padding-right: 0.5rem !important; }
            footer .max-w-7xl .grid > :nth-child(4) { padding-left: 0 !important; justify-self: start !important; }
        }
    </style>

    <style>
        /* Contact persons grid - very small screens adjustments */
        @media (max-width: 360px) {
            .contact-grid { grid-template-columns: 1fr !important; gap: 1rem !important; }
            .contact-grid .bg-white\/80 { padding: 0.6rem !important; }
            .contact-grid .text-center h4 { font-size: 0.95rem !important; }
            .contact-grid p, .contact-grid .text-xs { font-size: 0.72rem !important; }
            .contact-grid .w-12.h-12 { width: 44px !important; height: 44px !important; }
            .contact-grid .w-8.h-8 { width: 36px !important; height: 36px !important; }
        }
        @media (max-width: 420px) and (min-width: 361px) {
            .contact-grid { grid-template-columns: 1fr !important; gap: 1rem !important; }
            .contact-grid .bg-white\/80 { padding: 0.75rem !important; }
            .contact-grid .text-center h4 { font-size: 1rem !important; }
            .contact-grid p, .contact-grid .text-xs { font-size: 0.78rem !important; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <style>
        @media (max-width: 768px) {
            html, body { font-size: 14px; }
            h1 { font-size: 1.9rem !important; }
            .max-w-7xl { padding-left: 1rem; padding-right: 1rem; }
            header#main-header, nav#main-nav { position: sticky; top: 0; transform: translateY(0) !important; }
            /* Footer mobile layout: logo above, two columns (Services | Contact), social links under a divider, copyright below */
            footer { position: relative; padding-top: 1.2rem !important; padding-bottom: 3.2rem !important; }

            footer .max-w-7xl .grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                grid-auto-rows: auto;
                gap: 0.75rem !important;
                position: static;
                padding-top: 0.5rem !important;
            }

            /* Logo full width on top, left-aligned (not centered) */
            footer .max-w-7xl .grid > :nth-child(1) {
                grid-column: 1 / -1;
                display: flex;
            }
            /* Hide the long description in the logo column to save space */
            footer .max-w-7xl .grid > :nth-child(1) p { display: none !important; }

            /* Two column layout: Services (2nd child) left, Contact (4th child) right */
            footer .max-w-7xl .grid > :nth-child(2) { grid-column: 1 / 2; }
            footer .max-w-7xl .grid > :nth-child(4) { grid-column: 2 / 3; }

            /* Remove the Company column */
            footer .max-w-7xl .grid > :nth-child(3) { display: none !important; }

            /* Social links are moved outside the grid; no absolute positioning needed here */

            /* Ensure divider and grid have modest spacing so icons and logo don't overlap */
            footer > .max-w-7xl > .border-t { margin-top: 1rem !important; }
            footer > .max-w-7xl > .border-t.pt-8 { padding-top: 0.6rem !important; }

            /* Slightly smaller social icon sizes on narrow screens */
            footer .w-10.h-10 { width: 1.9rem !important; height: 1.9rem !important; }
            footer .w-10.h-10 i { font-size: 0.78rem !important; }
            /* Increase contact icon size on mobile to prevent squishing */
            footer .w-8.h-8 { width: 2rem !important; height: 2rem !important; }
            /* Ensure contact text wraps properly */
            footer .text-sm { white-space: normal !important; word-wrap: break-word !important; }
            /* Remove visible border/separator between header and nav on mobile */
            #main-header { border-bottom: 0 !important; }
            #main-nav { border-top: 0 !important; }
            /* Reduce header/nav background contrast and shadow on mobile */
            #main-header, #main-nav { box-shadow: none !important; background: rgba(255,255,255,0.95) !important; }
            /* Enlarge header logo text slightly on mobile */
            #main-header h1 { font-size: 1.25rem !important; }
        }
    </style>

    <style>
        /* Additional focused mobile tweaks: further reduce fonts and footer spacing */
        @media (max-width: 480px) {
            /* Reduce overall footer vertical padding and inner gaps */
            footer.bg-gradient-to-br { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            footer .max-w-7xl { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
            footer .grid.md\:grid-cols-4 > * { margin-bottom: 0.35rem !important; }
            footer .h-12 { height: 2rem !important; }
            footer img.h-12 { height: 1.8rem !important; }
            footer .text-gray-300, footer .text-sm { font-size: 0.78rem !important; line-height: 1 !important; }
            footer .w-10.h-10 { width: 1.9rem !important; height: 1.9rem !important; }
            footer .w-10.h-10 i { font-size: 0.75rem !important; }
            footer .border-t { padding-top: 0.4rem !important; }
            footer .pt-8 { padding-top: 0.4rem !important; }
            footer .space-y-4 { gap: 0.25rem !important; }
            footer .space-x-4 { gap: 0.25rem !important; }
            /* Reduce contact item padding inside footer */
            footer .flex.items-start, footer .flex.items-center { padding-top: 0.15rem !important; padding-bottom: 0.15rem !important; }
        }

    
    </style>

    <style>
        /* Very small screens: stack hero cards vertically and remove fixed heights */
        @media (max-width: 420px) {
            .hero-cards-grid { grid-template-columns: 1fr !important; gap: 1rem !important; }
            .hero-cards-grid > div { height: auto !important; }
            .hero-cards-grid > div > .bg-white\/10 { height: auto !important; display: block !important; flex-direction: initial !important; justify-content: initial !important; }
            .hero-cards-grid > div > .bg-white\/10 .w-16.h-16 { margin-bottom: 0.75rem !important; }
        }
    </style>

    
    <header class="fixed w-full z-40 bg-white/80 backdrop-blur-md border-b border-white/20 transition-transform duration-300 opacity-100" id="main-header">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <div class="flex items-center gap-4">
                    <div class="bg-white p-2 rounded-full shadow-sm border border-white/30">
                        <img class="h-12 w-auto block" src="../public/assets/images/logo.png" alt="PT. Artha Solusi Aditama">
                    </div>
                    <div>
                        <h1 class="text-xl font-bold bg-gradient-to-r from-blue-600 via-slate-800 to-blue-500 bg-clip-text text-transparent">PT. Artha Solusi Aditama</h1>
                        <p class="text-sm text-slate-600 font-medium">PROVIDING SOLUTION WITH CARE</p>
                    </div>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <a href="http://localhost:8081/login.php" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-full text-sm font-medium transition">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                </div>
                <div class="md:hidden ml-3">
                    <button class="text-slate-700 hover:text-blue-600 p-2" id="mobile-menu-button-header">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    
    <nav class="fixed w-full z-50 bg-white/90 backdrop-blur-md border-b border-white/10 transition-transform duration-300 hidden md:block" id="main-nav" style="top: 80px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center h-16">
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">
                        Home
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a href="/about" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">
                        About Us
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a href="/services" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">
                        Services
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a href="/project" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">
                        Project Reference
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a href="/contact" class="text-slate-700 font-medium">
                        Contact Us
                    </a>
                </div>
            </div>
        </div>
    </nav>

    
    <div id="mobile-sidebar" class="fixed inset-0 z-50 md:hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" id="sidebar-overlay"></div>
        <div id="mobile-sidebar-panel" class="absolute right-0 top-0 h-full w-80 bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out">
            <div class="flex flex-col h-full">
                
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <img class="h-10 w-auto" src="../public/assets/images/logo.png" alt="PT. Artha Solusi Aditama">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">PT. ASA</h2>
                            <p class="text-xs text-gray-600">Menu</p>
                        </div>
                    </div>
                    <button class="text-gray-500 hover:text-gray-700 p-2" id="close-sidebar">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                
                <div class="flex-1 overflow-y-auto py-6">
                    <nav class="px-6 space-y-2">
                        <a href="/" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-home w-5"></i>
                            <span>Home</span>
                        </a>
                        <a href="/about" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-info-circle w-5"></i>
                            <span>About Us</span>
                        </a>
                        <a href="/services" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-cogs w-5"></i>
                            <span>Services</span>
                        </a>
                        <a href="/project" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-project-diagram w-5"></i>
                            <span>Project Reference</span>
                        </a>
                        <a href="/contact" class="flex items-center gap-3 px-4 py-3 bg-blue-50 text-blue-600 rounded-xl">
                            <i class="fas fa-phone w-5"></i>
                            <span>Contact Us</span>
                        </a>
                    </nav>

                    
                    <div class="px-6 mt-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Get In Touch</h3>
                        <div class="space-y-3">
                            <a href="tel:+628117001515" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-phone text-white"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Call Us</p>
                                    <p class="text-xs text-gray-600">+62 811-7001-5158</p>
                                </div>
                            </a>
                            <a href="mailto:info@pt-asa.com" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-envelope text-white"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Email Us</p>
                                    <p class="text-xs text-gray-600">info@pt-asa.com</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                
                <div class="p-6 border-t border-gray-200">
                    <a href="http://localhost:8081/login.php" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-xl transition duration-300 flex items-center justify-center gap-2">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <main class="pt-0 md:pt-36">
        
        <section class="relative min-h-screen flex items-center justify-center overflow-hidden">
            
            <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900">
                <div class="absolute inset-0">
                    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-cyan-400/20 rounded-full blur-3xl animate-pulse"></div>
                    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl animate-pulse delay-1000"></div>
                    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-purple-400/10 rounded-full blur-2xl animate-pulse delay-500"></div>
                </div>
                
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.3) 1px, transparent 0); background-size: 50px 50px;"></div>
                </div>
            </div>

            
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="space-y-8">
                    
                    <div class="space-y-4">
                        <h1 class="text-5xl md:text-7xl font-bold text-white mb-6 glow-text">
                            CONTACT
                        </h1>
                        <div class="w-16 md:w-24 h-1 bg-gradient-to-r from-cyan-400 to-blue-400 mx-auto rounded-full"></div>
                        <p class="text-lg md:text-2xl text-gray-300 font-light max-w-3xl mx-auto leading-relaxed">
                            Let's create something extraordinary together
                        </p>
                    </div>

                    
                    <div class="hero-cards-grid grid grid-cols-1 sm:grid-cols-3 gap-6 md:gap-8 mt-12 md:mt-16">
                        <div class="group">
                            <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl p-6 md:p-8 hover:bg-white/20 transition-all duration-500 hover:scale-105 hover:shadow-2xl hover:shadow-cyan-400/20">
                                <div class="w-16 h-16 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-xl flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                                    <i class="fas fa-phone text-white text-2xl"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-white mb-2">Call Us</h3>
                                <p class="text-gray-300 mb-4">Ready to talk? We're here to help.</p>
                                <a href="tel:+628117001515" class="text-cyan-400 hover:text-cyan-300 font-medium transition-colors">
                                    +62 811-7001-5158
                                </a>
                            </div>
                        </div>

                        <div class="group">
                            <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl p-6 md:p-8 hover:bg-white/20 transition-all duration-500 hover:scale-105 hover:shadow-2xl hover:shadow-blue-400/20">
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-purple-500 rounded-xl flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                                    <i class="fas fa-envelope text-white text-2xl"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-white mb-2">Email Us</h3>
                                <p class="text-gray-300 mb-4">Send us your thoughts and ideas.</p>
                                <a href="mailto:info@pt-asa.com" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">
                                    info@pt-asa.com
                                </a>
                            </div>
                        </div>

                        <div class="group">
                            <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl p-6 md:p-8 hover:bg-white/20 transition-all duration-500 hover:scale-105 hover:shadow-2xl hover:shadow-purple-400/20">
                                <div class="w-16 h-16 bg-gradient-to-br from-purple-400 to-pink-500 rounded-xl flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                                    <i class="fas fa-map-marker-alt text-white text-2xl"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-white mb-2">Visit Us</h3>
                                <p class="text-gray-300 mb-4">Come see us in person at our office.</p>
                                <div class="text-purple-400 font-medium">
                                    Lytech Industrial Park C2 No 3, Batam Kota
                                </div>
                            </div>
                        </div>
                    </div>

                    
                    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
                        <div class="w-6 h-10 border-2 border-white/30 rounded-full flex justify-center">
                            <div class="w-1 h-3 bg-white/50 rounded-full mt-2 animate-pulse"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        
        <section class="py-32 bg-gray-50 relative overflow-hidden">
            
            <div class="absolute inset-0">
                <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-white via-gray-50 to-blue-50/30"></div>
                <div class="absolute top-1/4 right-1/4 w-64 h-64 bg-cyan-400/5 rounded-full blur-3xl"></div>
                <div class="absolute bottom-1/4 left-1/4 w-64 h-64 bg-blue-400/5 rounded-full blur-3xl"></div>
            </div>

            <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    
                    <div class="space-y-8">
                        <div>
                            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
                                Let's Start a
                                <span class="bg-gradient-to-r from-cyan-500 to-blue-500 bg-clip-text text-transparent font-normal">
                                    Conversation
                                </span>
                            </h2>
                            <p class="text-lg text-gray-600 leading-relaxed">
                                Ready to transform your space? Our team of experts is here to bring your vision to life with cutting-edge HVAC solutions.
                            </p>
                        </div>

                        <div class="space-y-6">
                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900">Business Hours</h4>
                                    <p class="text-gray-600">Monday - Friday: 8:30 AM - 5:00 PM WIB</p>
                                    <p class="text-gray-600">Emergency Support: 24/7 Available</p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-shield-alt text-white"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900">Trusted Partner</h4>
                                    <p class="text-gray-600">Licensed and certified HVAC specialists with 10+ years of experience</p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-pink-500 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-rocket text-white"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900">Fast Response</h4>
                                    <p class="text-gray-600">Average response time: 2 hours for emergency calls</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    
                    <div class="bg-white/80 backdrop-blur-xl border border-white/50 rounded-3xl p-8 md:p-12 shadow-2xl">
                        <div class="mb-8">
                            <h3 class="text-2xl font-semibold text-gray-900 mb-2">Send us a message</h3>
                            <p class="text-gray-600">Fill out the form below and we'll get back to you within 24 hours.</p>
                        </div>

                        <form class="space-y-6">
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                    <input type="text" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent bg-white/50 backdrop-blur-sm transition-all duration-300 hover:border-cyan-300" placeholder="John">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                    <input type="text" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent bg-white/50 backdrop-blur-sm transition-all duration-300 hover:border-cyan-300" placeholder="Doe">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent bg-white/50 backdrop-blur-sm transition-all duration-300 hover:border-cyan-300" placeholder="john@example.com">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent bg-white/50 backdrop-blur-sm transition-all duration-300 hover:border-cyan-300" placeholder="+62 811-7001-5158">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Company (Optional)</label>
                                <input type="text" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent bg-white/50 backdrop-blur-sm transition-all duration-300 hover:border-cyan-300" placeholder="Your Company">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Service Type</label>
                                <select class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent bg-white/50 backdrop-blur-sm transition-all duration-300 hover:border-cyan-300">
                                    <option value="">Select a service</option>
                                    <option value="installation">AC Installation</option>
                                    <option value="maintenance">Maintenance & Repair</option>
                                    <option value="hvac-system">HVAC System Design</option>
                                    <option value="consultation">Consultation</option>
                                    <option value="emergency">Emergency Service</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                                <textarea rows="4" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent bg-white/50 backdrop-blur-sm transition-all duration-300 hover:border-cyan-300 resize-none" placeholder="Tell us about your project..."></textarea>
                            </div>

                            <button type="submit" class="w-full bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                                <span class="flex items-center justify-center gap-2">
                                    Send Message
                                    <i class="fas fa-paper-plane"></i>
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        
        <section id="location" class="py-32 bg-white relative">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
                        Find Our
                        <span class="bg-gradient-to-r from-cyan-500 to-blue-500 bg-clip-text text-transparent font-normal">
                            Location
                        </span>
                    </h2>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        Visit our office in the heart of Batam business district
                    </p>
                </div>

                
                <div class="bg-white rounded-3xl overflow-hidden shadow-2xl">
                    <div class="aspect-[16/9] relative">
                        <iframe
                            src="https://www.google.com/maps?cid=6857945058101694791&output=embed"
                            width="100%"
                            height="100%"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>

                        
                        <div class="absolute top-4 right-4 bg-black/70 backdrop-blur-sm text-white px-3 py-2 rounded-lg text-xs">
                            <i class="fas fa-search-plus mr-1"></i>
                            Zoom untuk detail
                        </div>
                    </div>

                    
                    <div class="bg-gradient-to-r from-slate-50 to-gray-50 p-6 border-t border-gray-200">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-building text-white"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-900 text-lg mb-1">PT. Artha Solusi Aditama</h4>
                                <div class="space-y-1 text-gray-600">
                                    <p class="flex items-center gap-2">
                                        <i class="fas fa-map-marker-alt text-cyan-500 w-4"></i>
                                        Lytech Industrial Park C2 No 3
                                    </p>
                                    <p class="flex items-center gap-2 ml-6">
                                        <i class="fas fa-road text-cyan-500 w-4"></i>
                                        Belian, Batam Kota
                                    </p>
                                    <p class="flex items-center gap-2 ml-6">
                                        <i class="fas fa-city text-cyan-500 w-4"></i>
                                        Kepulauan Riau 29444
                                    </p>
                                    <p class="flex items-center gap-2 ml-6">
                                        <i class="fas fa-globe-asia text-cyan-500 w-4"></i>
                                        Indonesia
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        
        <section class="py-32 bg-gradient-to-br from-gray-50 via-white to-cyan-50/30 relative overflow-hidden">
            
            <div class="absolute inset-0">
                <div class="absolute top-1/4 right-1/4 w-64 h-64 bg-cyan-400/5 rounded-full blur-3xl"></div>
                <div class="absolute bottom-1/4 left-1/4 w-64 h-64 bg-blue-400/5 rounded-full blur-3xl"></div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
                        Our
                        <span class="bg-gradient-to-r from-cyan-500 to-blue-500 bg-clip-text text-transparent font-normal">
                            Team
                        </span>
                    </h2>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        Get in touch with our dedicated professionals ready to assist you
                    </p>
                </div>

                
                <div class="bg-gradient-to-r from-cyan-500 to-blue-500 rounded-2xl md:rounded-3xl p-6 md:p-8 lg:p-12 mb-12 md:mb-16 text-center text-white shadow-2xl">
                    <div class="mb-4 text-center">
                        <h3 class="text-2xl font-bold">PT. Artha Solusi Aditama</h3>
                        <p class="text-cyan-100">Main Contact</p>
                    </div>
                    <div class="text-2xl md:text-3xl lg:text-4xl font-bold mb-2">+62 811-7001-5158</div>
                    <p class="text-sm md:text-base text-cyan-100">Available 24/7 for urgent inquiries</p>
                </div>

                
                <!-- Andreas - Direktur (standalone) -->
                <div class="mb-6">
                    <div class="bg-white/80 backdrop-blur-xl border border-white/50 rounded-lg p-4 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 group max-w-md mx-auto">
                        <div class="text-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-user-tie text-white text-lg"></i>
                            </div>
                            <h4 class="text-base font-bold text-gray-900 mb-1">Andreas</h4>
                            <p class="text-sm text-cyan-600 font-medium">Direktur</p>
                        </div>
                        <div class="space-y-2">
                            <a href="tel:+628117710113" class="flex items-center gap-2 p-2 bg-cyan-50 rounded-lg hover:bg-cyan-100 transition-colors group">
                                <div class="w-8 h-8 bg-cyan-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Phone</p>
                                    <p class="font-semibold text-gray-900">+62 811-7710-113</p>
                                </div>
                            </a>
                            <a href="mailto:Andreas@pt-asa.com" class="flex items-center gap-2 p-2 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors group">
                                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Email</p>
                                    <p class="font-semibold text-gray-900">Andreas@pt-asa.com</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Team Grid 2x2 -->
                <div class="grid grid-cols-2 gap-4 contact-grid">
                    
                    <div class="bg-white/80 backdrop-blur-xl border border-white/50 rounded-lg p-4 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 group">
                        <div class="text-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-chart-line text-white text-lg"></i>
                            </div>
                            <h4 class="text-base font-bold text-gray-900 mb-1">Abdul Rasid</h4>
                            <p class="text-sm text-blue-600 font-medium">Project Sales</p>
                        </div>
                        <div class="space-y-2">
                            <a href="tel:+628117715152" class="flex items-center gap-2 p-2 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors group">
                                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Phone</p>
                                    <p class="font-semibold text-gray-900">+62 811-7715-152</p>
                                </div>
                            </a>
                            <a href="mailto:Abdulrasid@pt-asa.com" class="flex items-center gap-2 p-2 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors group">
                                <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Email</p>
                                    <p class="font-semibold text-gray-900">Abdulrasid@pt-asa.com</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    
                    <div class="bg-white/80 backdrop-blur-xl border border-white/50 rounded-lg p-4 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 group">
                        <div class="text-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-chart-line text-white text-lg"></i>
                            </div>
                            <h4 class="text-base font-bold text-gray-900 mb-1">Owen</h4>
                            <p class="text-sm text-blue-600 font-medium">Project Sales</p>
                        </div>
                        <div class="space-y-2">
                            <a href="tel:+6281170015155" class="flex items-center gap-2 p-2 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors group">
                                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Phone</p>
                                    <p class="font-semibold text-gray-900">+62 811-7001-5155</p>
                                </div>
                            </a>
                            <a href="mailto:Owen@pt-asa.com" class="flex items-center gap-2 p-2 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors group">
                                <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Email</p>
                                    <p class="font-semibold text-gray-900">Owen@pt-asa.com</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    
                    <div class="bg-white/80 backdrop-blur-xl border border-white/50 rounded-lg p-4 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 group">
                        <div class="text-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-wrench text-white text-lg"></i>
                            </div>
                            <h4 class="text-base font-bold text-gray-900 mb-1">Asep Ramdan Hidayat</h4>
                            <p class="text-sm text-cyan-600 font-medium">Chiller Senior Technician</p>
                        </div>
                        <div class="space-y-2">
                            <a href="tel:+628117015153" class="flex items-center gap-2 p-2 bg-cyan-50 rounded-lg hover:bg-cyan-100 transition-colors group">
                                <div class="w-8 h-8 bg-cyan-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Phone</p>
                                    <p class="font-semibold text-gray-900">+62 811-7015-153</p>
                                </div>
                            </a>
                            <a href="mailto:a.ramdan@pt-asa.com" class="flex items-center gap-2 p-2 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors group">
                                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Email</p>
                                    <p class="font-semibold text-gray-900">a.ramdan@pt-asa.com</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    
                    <div class="bg-white/80 backdrop-blur-xl border border-white/50 rounded-lg p-4 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 group">
                        <div class="text-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-pink-400 to-red-500 rounded-lg flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-desktop text-white text-lg"></i>
                            </div>
                            <h4 class="text-base font-bold text-gray-900 mb-1">Erika Kusy Maria</h4>
                            <p class="text-sm text-pink-600 font-medium">Office Sales</p>
                        </div>
                        <div class="space-y-2">
                            <a href="tel:+6281170015159" class="flex items-center gap-2 p-2 bg-pink-50 rounded-lg hover:bg-pink-100 transition-colors group">
                                <div class="w-8 h-8 bg-pink-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Phone</p>
                                    <p class="font-semibold text-gray-900">+62 811-7001-5159</p>
                                </div>
                            </a>
                            <a href="mailto:erika@pt-asa.com" class="flex items-center gap-2 p-2 bg-red-50 rounded-lg hover:bg-red-100 transition-colors group">
                                <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Email</p>
                                    <p class="font-semibold text-gray-900">erika@pt-asa.com</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                
                <div class="text-center mt-12 md:mt-16">
                    <p class="text-sm md:text-base text-gray-600 mb-4 md:mb-6">
                        Need immediate assistance? Don't hesitate to reach out to any of our team members.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 md:gap-4 justify-center">
                        <a href="tel:+628117001515" class="inline-flex items-center gap-2 bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-semibold py-3 md:py-4 px-6 md:px-8 rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 text-sm md:text-base">
                            <i class="fas fa-phone"></i>
                            Call Main Office
                        </a>
                        <a href="mailto:info@pt-asa.com" class="inline-flex items-center gap-2 bg-white border-2 border-gray-200 hover:border-cyan-500 text-gray-700 hover:text-cyan-600 font-semibold py-3 md:py-4 px-6 md:px-8 rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 text-sm md:text-base">
                            <i class="fas fa-envelope"></i>
                            Send Email
                        </a>
                    </div>
                </div>
            </div>
        </section>

        
        <section class="py-32 bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 relative overflow-hidden">
            <div class="absolute inset-0">
                <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-cyan-400/10 rounded-full blur-3xl"></div>
                <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-400/10 rounded-full blur-3xl"></div>
            </div>

            <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="space-y-8">
                    <h2 class="text-4xl md:text-6xl font-thin text-white">
                        Ready to Get
                        <span class="block bg-gradient-to-r from-cyan-400 to-blue-400 bg-clip-text text-transparent font-normal">
                            Started?
                        </span>
                    </h2>
                    <p class="text-xl text-gray-300 max-w-2xl mx-auto leading-relaxed">
                        Don't wait any longer. Contact us today and let's discuss how we can help transform your space.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-6 justify-center pt-8">
                        <a href="tel:+628117710113" class="group bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white px-8 py-4 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                            <span class="flex items-center justify-center gap-3">
                                <i class="fas fa-phone group-hover:animate-pulse"></i>
                                Call Now
                                <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                            </span>
                        </a>
                        <a href="mailto:info@pt-asa.com" class="group bg-white/10 backdrop-blur-xl border border-white/20 text-white px-8 py-4 rounded-xl font-semibold transition-all duration-300 hover:bg-white/20 hover:border-white/30">
                            <span class="flex items-center justify-center gap-3">
                                <i class="fas fa-envelope"></i>
                                Send Email
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    
    <footer class="bg-gradient-to-br from-slate-900 via-blue-900 to-slate-800 text-white py-16 relative overflow-hidden">
        
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-sky-500/20 to-blue-500/20"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div class="fade-in">
                    <div class="flex items-center gap-4 mb-6">
                        <img src="../public/assets/images/logo.png" alt="PT. Artha Solusi Aditama" class="h-12 w-auto filter drop-shadow-lg">
                        <span class="text-xl font-bold text-white">PT. ARTHA SOLUSI ADITAMA</span>
                    </div>
                    <p class="text-gray-300 leading-relaxed mb-6">
                        HVACR Contractor and Specialist - Providing solution with care
                    </p>
                </div>
                <div class="fade-in">
                    <h4 class="text-xl font-bold mb-6 bg-gradient-to-r from-sky-400 to-blue-400 bg-clip-text text-transparent">Services</h4>
                    <ul class="space-y-3 text-gray-300">
                        <li><a href="#services" class="hover:text-white transition duration-300 flex items-center group">
                            <span class="w-1 h-1 bg-sky-400 rounded-full mr-3 group-hover:w-2 transition-all duration-300"></span>
                            Air Conditioning Systems
                        </a></li>
                        <li><a href="#services" class="hover:text-white transition duration-300 flex items-center group">
                            <span class="w-1 h-1 bg-sky-400 rounded-full mr-3 group-hover:w-2 transition-all duration-300"></span>
                            HVAC Installation
                        </a></li>
                        <li><a href="#services" class="hover:text-white transition duration-300 flex items-center group">
                            <span class="w-1 h-1 bg-sky-400 rounded-full mr-3 group-hover:w-2 transition-all duration-300"></span>
                            Maintenance & Repair
                        </a></li>
                        <li><a href="#services" class="hover:text-white transition duration-300 flex items-center group">
                            <span class="w-1 h-1 bg-sky-400 rounded-full mr-3 group-hover:w-2 transition-all duration-300"></span>
                            Energy Optimization
                        </a></li>
                    </ul>
                </div>
                <div class="fade-in">
                    <h4 class="text-xl font-bold mb-6 bg-gradient-to-r from-sky-400 to-blue-400 bg-clip-text text-transparent">Company</h4>
                    <ul class="space-y-3 text-gray-300">
                        <li><a href="/" class="hover:text-white transition duration-300 flex items-center group">
                            <span class="w-1 h-1 bg-sky-400 rounded-full mr-3 group-hover:w-2 transition-all duration-300"></span>
                            Home
                        </a></li>
                        <li><a href="/about" class="hover:text-white transition duration-300 flex items-center group">
                            <span class="w-1 h-1 bg-sky-400 rounded-full mr-3 group-hover:w-2 transition-all duration-300"></span>
                            About Us
                        </a></li>
                        <li><a href="/contact" class="hover:text-white transition duration-300 flex items-center group">
                            <span class="w-1 h-1 bg-sky-400 rounded-full mr-3 group-hover:w-2 transition-all duration-300"></span>
                            Contact
                        </a></li>
                        <li><a href="http://localhost:8081/login.php" class="hover:text-white transition duration-300 flex items-center group">
                            <span class="w-1 h-1 bg-sky-400 rounded-full mr-3 group-hover:w-2 transition-all duration-300"></span>
                            Login
                        </a></li>
                    </ul>
                </div>
                <div class="fade-in">
                    <h4 class="text-xl font-bold mb-6 bg-gradient-to-r from-sky-400 to-blue-400 bg-clip-text text-transparent">Contact Info</h4>
                    <div class="space-y-4 text-gray-300">
                        <div class="flex items-start">
                            <div class="w-8 h-8 bg-white/10 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3 mt-0.5 border border-white/20">
                                <i class="fas fa-map-marker-alt text-green-400 text-sm"></i>
                            </div>
                            <span class="text-sm leading-relaxed">Lytech Industrial Park C2 No 3, Belian, Batam Kota, Batam, Kepulauan Riau, Indonesia, 29444</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-white/10 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3 border border-white/20">
                                <i class="fas fa-phone text-blue-400 text-sm"></i>
                            </div>
                            <span class="text-sm">+62 811-7001-5158</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-white/10 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3 border border-white/20">
                                <i class="fas fa-envelope text-purple-400 text-sm"></i>
                            </div>
                            <span class="text-sm">info@pt-asa.com</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="social-links text-center mb-4">
                <a href="#" class="inline-flex mx-2 w-10 h-10 bg-white/10 backdrop-blur-sm rounded-xl items-center justify-center hover:bg-white/20 transition duration-300 border border-white/20">
                    <i class="fab fa-facebook-f text-blue-400"></i>
                </a>
                <a href="#" class="inline-flex mx-2 w-10 h-10 bg-white/10 backdrop-blur-sm rounded-xl items-center justify-center hover:bg-white/20 transition duration-300 border border-white/20">
                    <i class="fab fa-twitter text-blue-300"></i>
                </a>
                <a href="#" class="inline-flex mx-2 w-10 h-10 bg-white/10 backdrop-blur-sm rounded-xl items-center justify-center hover:bg-white/20 transition duration-300 border border-white/20">
                    <i class="fab fa-linkedin-in text-blue-500"></i>
                </a>
                <a href="#" class="inline-flex mx-2 w-10 h-10 bg-white/10 backdrop-blur-sm rounded-xl items-center justify-center hover:bg-white/20 transition duration-300 border border-white/20">
                    <i class="fab fa-instagram text-pink-400"></i>
                </a>
            </div>

            <div class="border-t border-white/10 pt-8 text-center">
                <p class="text-gray-400">&copy; 2025 PT. Artha Solusi Aditama. All rights reserved.</p>
            </div>
        </div>
    </footer>

    
    <div id="page-transition" class="page-transition">
        <div class="transition-content">
            <div class="loading-spinner"></div>
            <h3 class="text-xl font-semibold mb-2">Loading...</h3>
            <p class="text-gray-300 text-sm">Preparing your experience</p>
        </div>
    </div>

    <script>
        // Page transition functionality
        function showPageTransition() {
            const transition = document.getElementById('page-transition');
            if (!transition) return;
            transition.classList.add('active');
        }

        function hidePageTransition() {
            const transition = document.getElementById('page-transition');
            if (!transition) return;
            transition.classList.remove('active');
        }

        // Enhanced link handling for smooth page transitions
        function handlePageTransition(event) {
            const link = event.currentTarget;
            const href = link.getAttribute('href');

            // Skip if it's an internal anchor link or external link
            if (href.startsWith('#') || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                return;
            }

            event.preventDefault();
            showPageTransition();

            // Add fade-out effect to body
            document.body.classList.add('fade-out');

            // Navigate after animation
            setTimeout(() => {
                window.location.href = href;
            }, 800);
        }

        // Apply page transition to all internal links
        function initializePageTransitions() {
            // Get all links that are not external or special links
            const internalLinks = document.querySelectorAll('a[href]:not([href^="http"]):not([href^="mailto:"]):not([href^="tel:"]):not([href^="#"])');
            internalLinks.forEach(link => {
                try {
                    const href = link.getAttribute('href');
                    if (href && typeof href === 'string') link.addEventListener('click', handlePageTransition);
                } catch (e) {}
            });

            // Check if page was loaded from navigation (not refresh)
            let isFromNavigation = false;
            try {
                const navigationType = performance.getEntriesByType('navigation')[0]?.type;
                isFromNavigation = navigationType === 'navigate' || (document.referrer && document.referrer.includes(window.location.hostname) && document.referrer !== window.location.href);
            } catch (e) {
                // Fallback for browsers that don't support performance API
                isFromNavigation = document.referrer && document.referrer.includes(window.location.hostname) && document.referrer !== window.location.href;
            }

            // Ensure header is visible immediately
            const header = document.getElementById('main-header');
            const navbar = document.getElementById('main-nav');
            if (header) header.style.opacity = '1';
            if (navbar) navbar.style.opacity = '1';

            if (isFromNavigation) {
                // Show transition briefly when coming from another page
                showPageTransition();
                setTimeout(() => {
                    hidePageTransition();
                    document.body.classList.add('fade-in');
                }, 300);
            } else {
                // Normal page load
                hidePageTransition();
                document.body.classList.add('fade-in');
            }
        }

        // Initialize on both DOMContentLoaded and load events for better compatibility
        document.addEventListener('DOMContentLoaded', initializePageTransitions);
        window.addEventListener('load', function() {
            // Ensure initialization happens even if DOMContentLoaded already fired
            if (!document.body.classList.contains('fade-in')) {
                initializePageTransitions();
            }
            // Enhanced loading animation - hide transition after initialization
            setTimeout(() => {
                hidePageTransition();
            }, 500);
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add fade-in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all elements with fade-in class
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 1s ease, transform 1s ease';
            observer.observe(el);
        });

        // Scroll behavior for header and navbar
        let lastScrollTop = 0;
        const header = document.getElementById('main-header');
        const navbar = document.getElementById('main-nav');

        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            if (scrollTop > lastScrollTop && scrollTop > 80) {
                // Scrolling down - hide header
                if (header) header.style.transform = 'translateY(-100%)';
                if (navbar) navbar.style.transform = 'translateY(-80px)';
            } else {
                // Scrolling up - show header
                if (header) header.style.transform = 'translateY(0)';
                if (navbar) navbar.style.transform = 'translateY(0)';
            }

            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        });

        // Handle browser back/forward buttons
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                hidePageTransition();
            }
        });

        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobile-menu-button-header');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const closeSidebar = document.getElementById('close-sidebar');
        const mobileSidebarPanel = document.getElementById('mobile-sidebar-panel');

        function openSidebar() {
            if (mobileSidebar && mobileSidebarPanel) {
                mobileSidebar.classList.add('open');
                mobileSidebarPanel.classList.remove('translate-x-full');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeSidebarFunc() {
            if (mobileSidebar && mobileSidebarPanel) {
                mobileSidebar.classList.remove('open');
                mobileSidebarPanel.classList.add('translate-x-full');
                document.body.style.overflow = '';
            }
        }

        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', function() {
                if (mobileSidebar && mobileSidebar.classList.contains('open')) {
                    closeSidebarFunc();
                } else {
                    openSidebar();
                }
            });
        }

        if (closeSidebar) {
            closeSidebar.addEventListener('click', closeSidebarFunc);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebarFunc);
        }

        // Close sidebar when clicking on navigation links
        document.querySelectorAll('#mobile-sidebar a').forEach(link => {
            link.addEventListener('click', function() {
                // Only close if it's not an external link or anchor
                if (!this.href.includes('#') && !this.href.includes('http') && !this.href.includes('mailto:') && !this.href.includes('tel:')) {
                    closeSidebarFunc();
                }
            });
        });

        // Close sidebar on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !mobileSidebar.classList.contains('translate-x-full')) {
                closeSidebarFunc();
            }
        });
    </script>

    <style>
        /* Override: ensure footer uses two-column layout for tablet widths (769-991px)
           to avoid stretched layout around 800px. Placed at the end to increase specificity/order. */
        @media (min-width: 769px) and (max-width: 991px) {
            footer .max-w-7xl .grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                grid-auto-rows: auto;
                gap: 1rem !important;
            }
            footer .max-w-7xl .grid > :nth-child(1) { grid-column: 1 / -1; display: flex; }
            footer .max-w-7xl .grid > :nth-child(1) p { display: none !important; }
            footer .max-w-7xl .grid > :nth-child(2) { grid-column: 1 / 2; }
            footer .max-w-7xl .grid > :nth-child(4) { grid-column: 2 / 3; }
            footer .max-w-7xl .grid > :nth-child(3) { display: none !important; }
            footer .w-10.h-10 { width: 1.9rem !important; height: 1.9rem !important; }
            footer .w-10.h-10 i { font-size: 0.78rem !important; }
            footer .text-sm { white-space: normal !important; word-wrap: break-word !important; }
        }
    </style>
</body>
</html>
