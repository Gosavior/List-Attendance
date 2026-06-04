<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan Kami - PT. Artha Solusi Aditama</title>
    <link rel="icon" href="public/assets/images/logo.png" type="image/x-icon">
    <meta name="description" content="Jelajahi layanan HVACR komprehensif dari PT. Artha Solusi Aditama. Solusi modern dan efisien untuk kebutuhan industri dan komersial Anda.">
    <link rel="canonical" href="https://arthasolusiaditama.com/services">
    <meta name="robots" content="index,follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Layanan HVACR PT. Artha Solusi Aditama">
    <meta property="og:description" content="Solusi HVACR komprehensif untuk kebutuhan industri dan komersial Anda.">
    <meta property="og:url" content="https://arthasolusiaditama.com/services">
    <meta property="og:image" content="public/assets/images/Hero Service.jpg">
    <meta property="og:image:alt" content="Portofolio layanan PT. Artha Solusi Aditama">
    <meta property="og:site_name" content="PT. Artha Solusi Aditama">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Layanan HVACR PT. Artha Solusi Aditama">
    <meta name="twitter:description" content="Temukan layanan HVACR terbaik dari PT. Artha Solusi Aditama.">
    <meta name="twitter:image" content="public/assets/images/Hero Service.jpg">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Service",
        "name": "HVACR Solutions",
        "serviceType": "Heating, Ventilation, Air Conditioning, Refrigeration",
        "description": "Instalasi, pemeliharaan, dan optimalisasi sistem HVACR untuk segmen komersial dan industri.",
        "provider": {
            "@type": "Organization",
            "name": "PT. Artha Solusi Aditama",
            "url": "https://arthasolusiaditama.com/"
        },
        "areaServed": "ID",
        "url": "https://arthasolusiaditama.com/services"
    }
    </script>
    <link rel="stylesheet" href="./src/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
            animation: none;
        }
        @keyframes gradientShift {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .service-card {
            background: rgba(255, 255, 255, 0.95);
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
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(14, 165, 233, 0.15);
            border-radius: 20px;
        }
        .client-logo:hover {
            transform: scale(1.1) rotate(2deg);
            filter: grayscale(0%) contrast(1.2);
            box-shadow: 0 15px 35px rgba(14, 165, 233, 0.2);
            border-color: rgba(14, 165, 233, 0.3);
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
        /* Prevent horizontal overflow on mobile */
        html, body { overflow-x: hidden !important; max-width: 100vw !important; }

        /* Mobile Sidebar Styles (overlay hidden by default, shown when .open on #mobile-sidebar) */
        #mobile-sidebar { pointer-events: none; overflow: hidden; }
        #mobile-sidebar #sidebar-overlay {
            display: none;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.2s ease, visibility 0.2s;
            z-index: 40;
            background-color: transparent;
            backdrop-filter: none !important;
        }
        #mobile-sidebar.open { pointer-events: auto; }
        #mobile-sidebar.open #sidebar-overlay {
            display: block;
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: none !important;
        }
        #mobile-sidebar-panel { z-index: 50; pointer-events: auto; box-shadow: none !important; }
        #mobile-sidebar.open #mobile-sidebar-panel { box-shadow: 0 25px 50px rgba(0,0,0,0.25) !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen" style="overflow-x:hidden;">
    <style>
        
        @media (max-width: 650px) {
            /* Keep base font similar to desktop for <=640 (index.php only reduces further at 480px) */
            html, body { font-size: 14px; }
            h1 { font-size: 1.9rem !important; }
            .max-w-7xl { padding-left: 1rem; padding-right: 1rem; }
            header#main-header, nav#main-nav { position: sticky; top: 0; }

            /* Mobile footer layout: logo above, two columns (Services | Contact), social links under a divider, copyright below */
            /* Make footer relative so absolutely-positioned icons can be placed relative to it */
                footer { position: relative; padding-top: 1.2rem !important; padding-bottom: 3.2rem !important; }

            footer .max-w-7xl .grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                grid-auto-rows: auto;
                gap: 0.75rem !important;
                position: static;
                padding-top: 0.5rem !important;
            }

            footer .max-w-7xl .grid > :nth-child(1) {
                grid-column: 1 / -1;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
                gap: 0.25rem;
                padding-left: 0.25rem;
            }
            
            footer .max-w-7xl .grid > :nth-child(1) p { display: none !important; }
            footer .max-w-7xl .grid > :nth-child(2) { grid-column: 1 / 2; }
            footer .max-w-7xl .grid > :nth-child(4) { grid-column: 2 / 3; }
            footer .max-w-7xl .grid > :nth-child(3) { display: none !important; }
            footer > .max-w-7xl > .border-t { margin-top: 1rem !important; }
            footer > .max-w-7xl > .border-t.pt-8 { padding-top: 0.6rem !important; }
            footer .w-10.h-10 { width: 1.9rem !important; height: 1.9rem !important; }
            footer .w-10.h-10 i { font-size: 0.78rem !important; }
            footer .w-8.h-8 { width: 2rem !important; height: 2rem !important; display: flex !important; align-items: center !important; justify-content: center !important; margin-top: 0 !important; }
            footer .w-8.h-8 i { font-size: 1.05rem !important; line-height: 1 !important; display: inline-block !important; }
            footer .text-sm { white-space: normal !important; word-wrap: break-word !important; }
            
            #main-header { border-bottom: 0 !important; }
            #main-nav { border-top: 0 !important; }
            #main-header, #main-nav { box-shadow: none !important; background: rgba(255,255,255,0.95) !important; }
            #main-header h1 { font-size: 1.25rem !important; }
            #home h1 { font-size: 2rem !important; }
            #home .text-2xl { font-size: 0.9rem !important; line-height: 1.3 !important; }
            #home .text-lg { font-size: 0.8rem !important; line-height: 1.2 !important; }
            #home .mb-12 { margin-bottom: 0.5rem !important; }
            #home .px-10 { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
            #home .py-4 { padding-top: 0.375rem !important; padding-bottom: 0.375rem !important; }
            #home p.opacity-90 { display: none !important; }
            #home { padding-top: 4rem !important; }
            #contact h2 { font-size: 3rem !important; padding-bottom: 0.5rem !important; }
                main > section:first-of-type {
                    overflow: visible !important;
                    height: auto !important;
                    min-height: 0 !important;
                    padding-top: 5rem !important;
                    position: relative !important;
                }
                /* Hero image: no longer covers the whole section, just a fixed-height block */
                main > section:first-of-type > img {
                    position: relative !important;
                    height: 200px !important;
                    width: 100% !important;
                    object-fit: cover !important;
                    border-radius: 0 !important;
                }
                /* Hide the overlay div */
                main > section:first-of-type > div:first-of-type {
                    display: none !important;
                }
                /* The white card: flow layout centered with ID selector */
                #hero-card {
                    position: relative !important;
                    bottom: auto !important;
                    top: auto !important;
                    left: auto !important;
                    right: auto !important;
                    transform: none !important;
                    translate: none !important;
                    width: 90% !important;
                    max-width: 90% !important;
                    margin: -7rem auto 0 auto !important;
                    z-index: 20 !important;
                    display: block !important;
                }
                /* The card inner white box */
                #hero-card > div:first-child {
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1) !important;
                }
                /* Hide the thin line under hero card on mobile */
                #hero-card > div[aria-hidden="true"] {
                    display: none !important;
                }
                /* Hero card title smaller on mobile */
                #hero-card h1 {
                    font-size: 1.8rem !important;
                    margin-bottom: 0.75rem !important;
                }
                #hero-card p {
                    font-size: 0.85rem !important;
                    line-height: 1.4 !important;
                }

                main > section.bg-white.pt-48.pb-24,
                section.bg-white.pt-48.pb-24 {
                    padding-top: 2.5rem !important;
                    padding-bottom: 1.5rem !important;
                }

                /* Gradient section: reduce inner card padding on mobile */
                section[style*="linear-gradient"] .grid.grid-cols-2 .aspect-square.p-8 {
                    padding: 1rem !important;
                }
                section[style*="linear-gradient"] .grid.grid-cols-2 .aspect-square h3 {
                    font-size: 0.95rem !important;
                }
        }
        
        @media (max-width: 480px) {
            html, body { font-size: 13px !important; }
            /* Reduce footer vertical padding and inner gaps similar to index.php */
            footer.bg-gradient-to-br { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            footer .text-sm { font-size: 0.78rem !important; line-height: 1 !important; }

            /* Stack gradient section inner cards vertically on very small screens */
            section[style*="linear-gradient"] .grid.grid-cols-2 {
                grid-template-columns: 1fr !important;
            }
            section[style*="linear-gradient"] .grid.grid-cols-2 .aspect-square {
                aspect-ratio: auto !important;
            }
            section[style*="linear-gradient"] .grid.grid-cols-2 .aspect-square.p-8 {
                padding: 1rem !important;
            }
            section[style*="linear-gradient"] .grid.grid-cols-2 .aspect-square img {
                max-height: 200px;
            }
        }
        /* High-specificity overrides to prevent other styles from squashing footer icons on mobile */
        @media (max-width: 640px) {
            footer.bg-gradient-to-br .max-w-7xl .grid .w-8.h-8 {
                width: 2rem !important;
                height: 2rem !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                margin-top: 0.125rem !important; /* match mt-0.5 visually */
            }
            footer.bg-gradient-to-br .max-w-7xl .grid .w-8.h-8 i {
                font-size: 1.05rem !important;
                line-height: 1 !important;
                display: inline-block !important;
            }
        }
        /* Ensure footer contact icons keep correct aspect ratio and don't get squashed */
        footer .max-w-7xl .grid .flex.items-start .w-8.h-8 i,
        footer .max-w-7xl .grid .flex.items-center .w-8.h-8 i {
            font-size: 1.05rem !important;
            line-height: 1 !important;
            display: inline-block !important;
            width: auto !important;
            height: auto !important;
            transform: none !important;
            vertical-align: middle !important;
        }
        /* Make the contact text flexible so long addresses can wrap and not push into the icon */
        footer .flex.items-start span,
        footer .flex.items-center span {
            flex: 1; /* allow the text to take remaining space */
            min-width: 0; /* allow the flex item to shrink and wrap properly */
            word-break: break-word;
        }
        /* Give icons a touch more gap so long addresses don't butt up against the icon */
        footer .flex.items-start > div.w-8.h-8,
        footer .flex.items-center > div.w-8.h-8 {
            margin-right: 0.75rem !important; /* slightly larger gap */
        }
        /* Prevent ellipsis icon from stretching if affected by other icon rules */
        .fa-ellipsis-h, .fa-ellipsis-v {
            font-size: 1.25rem !important;
            line-height: 1 !important;
            display: inline-block !important;
            width: auto !important;
            transform: none !important;
        }
        /* Keep the small "dot" indicators in Services/Company lists circular and capped
           Some utility classes or higher-specificity rules might accidentally stretch
           these (making them look like long bars). Scope this to footer lists only. */
        footer ul.space-y-3 li span.w-1.h-1,
        footer ul.space-y-3 li span.w-1.h-1.rounded-full {
            display: inline-block !important;
            width: 0.25rem !important; /* 4px */
            height: 0.25rem !important; /* 4px */
            min-width: 0.25rem !important;
            max-width: 0.5rem !important; /* prevents accidental expansion */
            border-radius: 9999px !important;
            overflow: hidden !important;
            transition: width 180ms ease !important; /* animate to a small size only */
            vertical-align: middle !important;
        }
        /* When a group-hover is intended, allow a small, controlled expansion only */
        footer ul.space-y-3 li.group:hover span.w-1.h-1,
        footer ul.space-y-3 li:hover span.w-1.h-1 {
            width: 0.5rem !important; /* 8px */
            max-width: 0.5rem !important;
        }
    </style>

    <header class="fixed w-full z-40 bg-white/80 backdrop-blur-md border-b border-white/20 transition-transform duration-300" id="main-header">
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
                <div class="md:hidden">
                    <button class="text-slate-700 hover:text-blue-600" id="mobile-menu-button-header">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <nav class="fixed w-full z-30 bg-white/90 backdrop-blur-md border-b border-white/10 transition-transform duration-300 hidden md:block" id="main-nav" style="top: 80px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center h-16">
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">Home<span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span></a>
                    <a href="/about" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">About Us<span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span></a>
                    <a href="/services" class="text-blue-600 font-bold transition relative group">Services<span class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600"></span></a>
                    <a href="/project" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">Project Reference<span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span></a>
                    <a href="/contact" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">Contact Us<span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span></a>
                </div>
                <div class="md:hidden ml-auto">
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
                            <h3 class="text-sm font-bold text-gray-900">PT. Artha Solusi Aditama</h3>
                            <p class="text-xs text-gray-600">Menu</p>
                        </div>
                    </div>
                    <button class="text-gray-400 hover:text-gray-600 p-2" id="close-sidebar">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                
                <div class="flex-1 overflow-y-auto py-6">
                    <nav class="px-6 space-y-2">
                        <a href="/" class="flex items-center gap-3 px-4 py-3 font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-home w-5"></i>
                            <span>Home</span>
                        </a>
                        <a href="/about" class="flex items-center gap-3 px-4 py-3 font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-info-circle w-5"></i>
                            <span>About Us</span>
                        </a>
                        <a href="/services" class="flex items-center gap-3 px-4 py-3 font-semibold text-blue-600 bg-blue-50 rounded-xl transition-colors">
                            <i class="fas fa-cogs w-5"></i>
                            <span>Services</span>
                        </a>
                        <a href="/project" class="flex items-center gap-3 px-4 py-3 font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-project-diagram w-5"></i>
                            <span>Project Reference</span>
                        </a>
                        <a href="/contact" class="flex items-center gap-3 px-4 py-3 font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-envelope w-5"></i>
                            <span>Contact Us</span>
                        </a>
                    </nav>
                    
                    
                    <div class="px-6 mt-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Get In Touch</h3>
                        <div class="space-y-3">
                            <a href="tel:+62 851-8610-1125" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-phone text-white"></i>
                                </div>
                                <span class="text-sm">+62 851-8610-1125</span>
                            </a>
                            <a href="mailto:artha@artha.co.id" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                <div class="w-10 h-10 bg-sky-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-envelope text-white"></i>
                                </div>
                                <span class="text-sm">artha@artha.co.id</span>
                            </a>
                        </div>
                    </div>
                </div>

                
                <div class="p-6 border-t border-gray-200">
                    <div class="flex items-center gap-4 mb-4">
                        <a href="http://localhost:8081/login.php" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center font-medium transition">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login
                        </a>
                    </div>
                    <div class="text-center text-sm text-gray-500 space-y-1">
                        <div><a href="mailto:artha@artha.co.id" class="text-sm text-blue-600 hover:underline">artha@artha.co.id</a></div>
                        <div>© 2025 PT. Artha Solusi Aditama</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main>
        <section id="hero-section" class="relative pt-36 h-[50vh]">
            <img src="public/assets/images/Hero Service.jpg" 
                 class="absolute inset-0 w-full h-full object-cover z-0" alt="HVAC System">
            <div class="absolute inset-0 z-10"></div>

                <div id="hero-card" class="absolute bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2 z-20 w-[90%] max-w-2xl">
                <div class="bg-white rounded-2xl p-8 md:p-12 text-center">
                    <h1 class="text-4xl md:text-5xl font-bold mb-6 text-slate-800">
                        Our <span class="text-gradient">Services</span>
                    </h1>
                    <p class="text-lg text-slate-600 leading-relaxed">
                        At PT. Artha Solusi Aditama, we are committed to delivering comprehensive, reliable, and customized solutions tailored to meet the unique needs of each client. Guided by our core principle, “Providing Solution with Care,” we strive to uphold the highest standards of professionalism, precision, and integrity in every service we offer.
                    </p>
                </div>
                
                <div class="absolute bottom-0 left-1/2 -translate-x-1/2 translate-y-[calc(100%+8px)] w-[90%] max-w-2xl" aria-hidden="true">
                    <div style="height:2px;background:#000;border-radius:1px;width:100%;"></div>
                </div>
            </div>
        </section>

        <section class="bg-white pt-48 pb-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-12">
                    <div class="flex items-start gap-6">
                        <div class="flex-shrink-0 w-16 h-16 rounded-full bg-gradient-to-br from-sky-100 to-blue-200 flex items-center justify-center text-3xl text-blue-600">
                            <i class="fas fa-snowflake"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Chiller Selection, Supply and Installation</h3>
                            <p class="text-slate-600">We provide expert consultation in selecting the right chiller systems, followed by efficient supply and professional installation to ensure optimal performance and energy efficiency.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-6">
                        <div class="flex-shrink-0 w-16 h-16 rounded-full bg-gradient-to-br from-sky-100 to-blue-200 flex items-center justify-center text-3xl text-blue-600">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Chiller Maintenance, Service, and Repair</h3>
                            <p class="text-slate-600">We offer routine maintenance, performance diagnostics, and repair services for chiller systems, aiming to extend lifespan, reduce downtime, and enhance system reliability.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-6">
                        <div class="flex-shrink-0 w-16 h-16 rounded-full bg-gradient-to-br from-sky-100 to-blue-200 flex items-center justify-center text-3xl text-blue-600">
                            <i class="fas fa-wrench"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Maintenance & Repair of AHU, FCU, and DX Split AC Units</h3>
                            <p class="text-slate-600">Comprehensive after-sales support including servicing, preventive maintenance, and repairs for AHU, FCU, and DX Split AC units to maintain peak performance and indoor comfort.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-6">
                        <div class="flex-shrink-0 w-16 h-16 rounded-full bg-gradient-to-br from-sky-100 to-blue-200 flex items-center justify-center text-3xl text-blue-600">
                            <i class="fas fa-wind"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">AHU FCU Selection, Supply, and Installation</h3>
                            <p class="text-slate-600">Our team specializes in selecting and installing Air Handling Units (AHU) and Fan Coil Units (FCU) tailored to each project’s cooling requirements, ensuring a comfortable and controlled environment.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-6">
                        <div class="flex-shrink-0 w-16 h-16 rounded-full bg-gradient-to-br from-sky-100 to-blue-200 flex items-center justify-center text-3xl text-blue-600">
                            <i class="fas fa-water"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Refrigerant and Chilled Water Piping Systems</h3>
                            <p class="text-slate-600">From refrigerant pipe installation to complete chilled water piping and pump systems, we ensure precise engineering, seamless integration, and long-term durability.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-6">
                        <div class="flex-shrink-0 w-16 h-16 rounded-full bg-gradient-to-br from-sky-100 to-blue-200 flex items-center justify-center text-3xl text-blue-600">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Ducting Installation Project</h3>
                            <p class="text-slate-600">We design and install efficient ducting systems that optimize airflow distribution, minimize energy loss, and meet industry standards for air quality and ventilation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    
    <section class="py-20" style="background: linear-gradient(180deg, #ffffff 0%, #bae6fd 30%, #3b82f6 65%, #0f172a 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <div class="grid grid-cols-2 gap-0 bg-white/0">
                    <div class="aspect-square overflow-hidden">
                        <img src="../public/assets/images/ASA/Chiller Selection, Supply and Install.jpg" alt="Chiller Selection" class="w-full h-full object-contain">
                    </div>
                    <div class="aspect-square bg-blue-900 flex items-center p-8">
                        <h3 class="text-white text-xl font-semibold">Chiller Selection, Supply and Install</h3>
                    </div>
                </div>

                
                <div class="grid grid-cols-2 gap-0 bg-white/0">
                    <div class="aspect-square overflow-hidden">
                        <img src="../public/assets/images/ASA/Pump and Chilled Water Piping Systems.jpg" alt="Pump and Piping" class="w-full h-full object-contain">
                    </div>
                    <div class="aspect-square bg-blue-900 flex items-center p-8">
                        <h3 class="text-white text-xl font-semibold">Pump and Chilled Water Piping Systems</h3>
                    </div>
                </div>

                
                <div class="grid grid-cols-2 gap-0 bg-white/0">
                    <div class="aspect-square overflow-hidden">
                        <img src="../public/assets/images/ASA/AHU FCU Selection, Supply, and Install.jpg" alt="AHU FCU Selection" class="w-full h-full object-contain">
                    </div>
                    <div class="aspect-square bg-blue-900 flex items-center p-8">
                        <h3 class="text-white text-xl font-semibold">AHU FCU Selection, Supply, and Install</h3>
                    </div>
                </div>

                
                <div class="grid grid-cols-2 gap-0 bg-white/0">
                    <div class="aspect-square overflow-hidden">
                        <img src="../public/assets/images/ASA/Ducting Installation Project.jpg" alt="Ducting" class="w-full h-full object-contain">
                    </div>
                    <div class="aspect-square bg-blue-900 flex items-center p-8">
                        <h3 class="text-white text-xl font-semibold">Ducting Installation Project</h3>
                    </div>
                </div>

                
                <div class="grid grid-cols-2 gap-0 bg-white/0">
                    <div class="aspect-square overflow-hidden">
                        <img src="../public/assets/images/ASA/Chiller Maintenance, Service, and Repair.jpg" alt="Chiller Maintenance" class="w-full h-full object-contain">
                    </div>
                    <div class="aspect-square bg-blue-900 flex items-center p-8">
                        <h3 class="text-white text-xl font-semibold">Chiller Maintenance, Service, and Repair</h3>
                    </div>
                </div>

                
                <div class="grid grid-cols-2 gap-0 bg-white/0">
                    <div class="aspect-square overflow-hidden">
                        <img src="../public/assets/images/ASA/AHU FCU Maintenance, Service, and Repair.jpg" alt="AHU FCU Maintenance" class="w-full h-full object-contain">
                    </div>
                    <div class="aspect-square bg-blue-900 flex items-center p-8">
                        <h3 class="text-white text-xl font-semibold">AHU FCU Maintenance, Service, and Repair</h3>
                    </div>
                </div>

                
                <div class="grid grid-cols-2 gap-0 bg-white/0">
                    <div class="aspect-square overflow-hidden">
                        <img src="../public/assets/images/ASA/Refrigerant Pipe Installation Project.jpg" alt="Refrigerant" class="w-full h-full object-contain">
                    </div>
                    <div class="aspect-square bg-blue-900 flex items-center p-8">
                        <h3 class="text-white text-xl font-semibold">Refrigerant Pipe Installation Project</h3>
                    </div>
                </div>

                
                <div class="grid grid-cols-2 gap-0 bg-white/0">
                    <div class="aspect-square overflow-hidden">
                        <img src="../public/assets/images/ASA/DX Split AC Maintenance, Service, and Repair.jpg" alt="DX Split" class="w-full h-full object-contain">
                    </div>
                    <div class="aspect-square bg-blue-900 flex items-center p-8">
                        <h3 class="text-white text-xl font-semibold">DX Split AC Maintenance, Service, and Repair</h3>
                    </div>
                </div>
            </div>
        </div>
    </section>

    
    <section class="py-12 bg-blue-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h3 class="text-2xl md:text-3xl font-bold mb-3">Order Placement & Quotation Requests</h3>
            <p class="mb-6 text-white/90">For orders or quotation requests, please reach out to our team via email. You may also click the button below to access the order form.</p>
            <a href="/contact" class="inline-flex items-center justify-center gap-3 bg-white/10 hover:bg-white/20 border border-white/20 px-6 py-3 rounded-md text-white font-medium transition">
                <i class="fas fa-envelope"></i>
                Email Our Team
            </a>
        </div>
    </section>

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
                            <span class="text-sm">+62 851-8610-1125</span>
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
            if (!transition) return; // defensive: element may be missing
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
            const href = link && link.getAttribute ? link.getAttribute('href') : null;
            // Defensive guard: ensure href exists and is a string
            if (!href || typeof href !== 'string') return;

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
            }, 300);
        }

        // Apply page transition to all internal links
        function initializePageTransitions() {
            // Get all links that are not external or special links
            const internalLinks = document.querySelectorAll('a[href]:not([href^="http"]):not([href^="mailto:"]):not([href^="tel:"]):not([href^="#"])');
            internalLinks.forEach(link => {
                try {
                    // only attach when href is present
                    const href = link.getAttribute('href');
                    if (href && typeof href === 'string') link.addEventListener('click', handlePageTransition);
                } catch (e) {
                    // ignore malformed link nodes
                }
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
                const href = this.getAttribute && this.getAttribute('href') ? this.getAttribute('href') : null;
                if (!href) return;
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
                    if (entry.target.classList.contains('staggered-slide-up')) {
                        // Animate children sequentially
                        const children = entry.target.querySelectorAll('.client-logo');
                        children.forEach((child, index) => {
                            setTimeout(() => {
                                child.classList.add('animate');
                            }, index * 400); // 400ms delay between each
                        });
                    } else {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
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

        // Observe all elements with slide-up class
        document.querySelectorAll('.slide-up').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(50px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Observe staggered-slide-up and animate children sequentially
        document.querySelectorAll('.staggered-slide-up').forEach(container => {
            observer.observe(container);
        });

        // Scroll behavior for header and navbar
        let lastScrollTop = 0;
        const header = document.getElementById('main-header');
        const navbar = document.getElementById('main-nav');

        let ticking = false;
        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(function() {
                    let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                    if (scrollTop > lastScrollTop && scrollTop > 80) {
                        if (header) header.style.transform = 'translateY(-100%)';
                        if (navbar) navbar.style.transform = 'translateY(-80px)';
                    } else {
                        if (header) header.style.transform = 'translateY(0)';
                        if (navbar) navbar.style.transform = 'translateY(0)';
                    }
                    lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });

        // Handle browser back/forward buttons
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                hidePageTransition();
            }
        });

        // Mobile menu toggle functionality
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

        if (closeSidebar) closeSidebar.addEventListener('click', closeSidebarFunc);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebarFunc);

        // Close sidebar when clicking on a link inside it
        const sidebarLinks = document.querySelectorAll('#mobile-sidebar a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', closeSidebarFunc);
        });
    </script>
</body>
</html>