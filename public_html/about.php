<?php
$currentPage = 'about';

include 'seo-config.php';
    echo SEOConfig::generateMetaTags([
     'title' => 'About Us - PT. Artha Solusi Aditama',
     'description' => 'Learn about our HVACR services...',
     'url' => 'https://arthasolusiaditama.com/about'
 ]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - PT. Artha Solusi Aditama</title>
    <link rel="icon" href="../public/assets/images/logo.png" type="image/x-icon">
    <meta name="description" content="Learn more about PT. Artha Solusi Aditama - HVACR Contractor and Specialist providing innovative climate control solutions">
    <link rel="canonical" href="https://arthasolusiaditama.com/about">
    <meta name="robots" content="index,follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="About PT. Artha Solusi Aditama">
    <meta property="og:description" content="Learn more about PT. Artha Solusi Aditama - HVACR Contractor and Specialist providing innovative climate control solutions">
    <meta property="og:url" content="https://arthasolusiaditama.com/about">
    <meta property="og:image" content="https://arthasolusiaditama.com/public/assets/images/ourteam.jpg">
    <meta property="og:image:alt" content="Tim PT. Artha Solusi Aditama">
    <meta property="og:site_name" content="PT. Artha Solusi Aditama">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="About PT. Artha Solusi Aditama">
    <meta name="twitter:description" content="Pelajari lebih jauh tentang PT. Artha Solusi Aditama dan spesialisasi HVACR kami.">
    <meta name="twitter:image" content="https://arthasolusiaditama.com/public/assets/images/ourteam.jpg">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "AboutPage",
        "mainEntityOfPage": "https://arthasolusiaditama.com/about",
        "headline": "About PT. Artha Solusi Aditama",
        "description": "HVACR Contractor and Specialist providing innovative climate control solutions.",
        "publisher": {
            "@type": "Organization",
            "name": "PT. Artha Solusi Aditama",
            "logo": {
                "@type": "ImageObject",
                "url": "https://arthasolusiaditama.com/public/assets/images/logo.png"
            }
        }
    }
    </script>
    <link rel="stylesheet" href="./src/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        html, body { margin: 0; padding: 0; overflow-x: hidden; }
        .hero-bg {
            background: linear-gradient(135deg, #e6f7ff 0%, #d7f0ff 40%, #cfeefe 100%);
            position: relative;
            overflow: hidden;
        }
        .glow-text { text-shadow: 0 8px 30px rgba(59,130,246,0.25); }
        .text-gradient { background: linear-gradient(90deg,#0369a1,#06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .fade-in { opacity: 0; transform: translateY(20px); transition: opacity 1s ease, transform 1s ease; }
        .slide-up { opacity: 0; transform: translateY(50px); transition: opacity 1s ease, transform 1s ease; }
        .slide-in-left {
            opacity: 0;
            transform: translateX(-100px);
            transition: opacity 1s ease, transform 1s ease;
        }

        .slide-in-right {
            opacity: 0;
            transform: translateX(100px);
            transition: opacity 1s ease, transform 1s ease;
        }
        .group:hover .group-hover\:text-blue-600 { color: rgb(37 99 235); }
        .group:hover .group-hover\:text-green-600 { color: rgb(22 163 74); }
        .group:hover .group-hover\:text-purple-600 { color: rgb(147 51 234); }
        .group:hover .group-hover\:text-orange-600 { color: rgb(234 88 12); }

        /* Animasi untuk Core Values */
        .icon-blur-to-normal {
            filter: none;
            opacity: 0;
            transform: scale(0.8);
            animation: none;
        }

        .text-slide-up {
            opacity: 0;
            transform: translateY(30px);
            animation: slideUpText 0.8s ease-out forwards;
        }

        @keyframes blurToNormal {
            0% {
                filter: none;
                opacity: 0;
                transform: scale(0.8);
            }
            100% {
                filter: none;
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slideUpText {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Delay animasi untuk setiap item */
        .icon-delay-1 { animation-delay: 0.2s; }
        .icon-delay-2 { animation-delay: 0.4s; }
        .icon-delay-3 { animation-delay: 0.6s; }
        .icon-delay-4 { animation-delay: 0.8s; }
        .icon-delay-5 { animation-delay: 1.0s; }

        .text-delay-1 { animation-delay: 0.3s; }
        .text-delay-2 { animation-delay: 0.5s; }
        .text-delay-3 { animation-delay: 0.7s; }
        .text-delay-4 { animation-delay: 0.9s; }
        .text-delay-5 { animation-delay: 1.1s; }

        .page-transition {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0 !important;
            visibility: hidden !important;
            transition: all 0.5s ease;
        }

        .page-transition.active {
            opacity: 1;
            visibility: visible;
        }

        .transition-content {
            text-align: center;
            color: white;
        }

        .loading-spinner {
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6 0%, #06b6d4 50%, #3b82f6 100%);
            background-size: 200% 100%;
            animation: loadingBar 1.5s ease-in-out infinite;
            margin: 0 auto 20px;
            border-radius: 2px;
        }

        @keyframes loadingBar {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .fade-out {
            animation: fadeOut 0.3s ease-out forwards;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in forwards;
        }

        @keyframes fadeOut {
            0% { opacity: 1; }
            100% { opacity: 0; }
        }

        /* Mobile Sidebar Styles */
        /* Sidebar overlay: hidden by default, shown when parent has .open
           - Use display:none + transparent background so no dark blur appears when closed
           - Only apply panel shadow when sidebar is open to avoid persistent right-side shadow */
        #mobile-sidebar { pointer-events: none; }
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
<body class="bg-slate-50 text-slate-800 m-0 p-0">

    <style>
        /* Mobile tweaks: reduce base font-size and scale down headings for small screens */
        @media (max-width: 640px) {
            html, body { font-size: 14px; }
            h1 { font-size: 1.75rem !important; }
            h2 { font-size: 1.25rem !important; }
            .hero-bg .text-5xl { font-size: 2rem !important; }
            .max-w-7xl { padding-left: 1rem; padding-right: 1rem; }
            /* Mobile footer layout: logo above, two columns (Services | Contact), social links under a divider, copyright below */
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
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
                gap: 0.25rem;
                padding-left: 0.25rem;
            }
            /* Hide the long description in the logo column to save space */
            footer .max-w-7xl .grid > :nth-child(1) p { display: none !important; }
            /* Two column layout: Services (2nd child) left, Contact (4th child) right */
            footer .max-w-7xl .grid > :nth-child(2) { grid-column: 1 / 2; }
            footer .max-w-7xl .grid > :nth-child(4) { grid-column: 2 / 3; }
            /* Remove the Company column */
            footer .max-w-7xl .grid > :nth-child(3) { display: none !important; }
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
            #main-header, #main-nav { box-shadow: none !important; background: rgba(255,255,255,0.95) !important; backdrop-filter: none !important; }
            /* Enlarge header logo text slightly on mobile */
            #main-header h1 { font-size: 1.25rem !important; }
            /* Enlarge hero title text slightly on mobile */
            #home h1 { font-size: 2rem !important; }
            /* Adjust hero content to fit on mobile without cutting */
            #home .text-2xl { font-size: 0.9rem !important; line-height: 1.3 !important; }
            #home .text-lg { font-size: 0.8rem !important; line-height: 1.2 !important; }
            #home .mb-12 { margin-bottom: 0.5rem !important; }
            #home .px-10 { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
            #home .py-4 { padding-top: 0.375rem !important; padding-bottom: 0.375rem !important; }
            /* Hide the long description paragraph on mobile */
            #home p.opacity-90 { display: none !important; }
            /* Adjust hero padding on mobile to center content better */
            #home { padding-top: 4rem !important; }
            /* Enlarge contact section title on mobile */
            #contact h2 { font-size: 3rem !important; padding-bottom: 0.5rem !important; }
        }

        /* More aggressive footer and spacing reductions (mobile + slight desktop tightening) */
        @media (max-width: 480px) {
            /* Reduce overall footer vertical padding and inner gaps */
            footer.bg-gradient-to-br {
                padding-top: 0.6rem !important;
                padding-bottom: 0.6rem !important;
            }
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

        /* Slight desktop tightening so footer doesn't look overly tall on medium screens */
        footer.bg-gradient-to-br {
            padding-top: 3rem;
            padding-bottom: 3rem;
        }
        @media (min-width: 768px) and (max-width: 1024px) {
            footer.bg-gradient-to-br { padding-top: 2rem; padding-bottom: 2rem; }
        }

        /* Allow contact text to break on desktop to prevent icon squishing */
        footer .flex.items-start span { flex: 1; min-width: 0; word-break: break-word; }
        /* Center icons vertically in contact items */
        footer .flex.items-start { align-items: center !important; }
        /* Make contact text smaller to prevent icon squishing */
        footer .text-sm { font-size: 0.8rem !important; line-height: 1.2 !important; }
        /* Prevent squishing of icons in contact section above footer */
        #contact .w-12.h-12 { flex-shrink: 0 !important; }
        #contact .w-12.h-12 i { font-size: 1.5rem !important; }

        /* Make footer logo larger on desktop and mobile */
        footer img.h-12 { height: 2.5rem !important; }
        header img.h-12 { height: 2.75rem !important; }

        /* Increase margin for contact icon on desktop to move text right */
        footer .w-8.h-8 { margin-right: 1rem !important; }

        @media (max-width: 640px) {
            footer img.h-12 { height: 2.8rem !important; }
            header img.h-12 { height: 2.25rem !important; }
        }

        /* Additional focused mobile tweaks: further reduce fonts and footer spacing */
        @media (max-width: 480px) {
            html, body { font-size: 13px !important; }
            /* Reduce hero headings and general large text */
            h1.text-5xl, h1.text-4xl, .text-5xl, .text-4xl { font-size: 1.6rem !important; line-height: 1.1 !important; }
            h2.text-4xl, h2.text-3xl, .text-3xl, .text-2xl { font-size: 1.1rem !important; }
            /* Make buttons smaller on mobile */
            .button-glow, .inline-flex, .inline-flex.items-center { padding-left: .75rem !important; padding-right: .75rem !important; padding-top: .5rem !important; padding-bottom: .5rem !important; }
            /* Reduce footer vertical padding to make it less tall */
            footer { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            footer .py-16 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            /* Tighter spacing for cards */
            .service-card, .glass-card, .project-card { padding: .75rem !important; }
            /* Slightly reduce the hero section min height on very small screens */
            .hero-bg.min-h-screen { min-height: 60vh !important; }
            /* Hide the third footer column (Company) on small screens to reduce height/clutter */
            footer .max-w-7xl > .grid > :nth-child(3) { display: none !important; }
            /* Disable backdrop-blur globally to prevent blur issues */
            .backdrop-blur-sm { backdrop-filter: none !important; }
            .backdrop-blur-md { backdrop-filter: none !important; }
        }
    </style>

    
    <header class="fixed w-full z-40 bg-white border-b border-white/20 transition-transform duration-300 opacity-100" id="main-header">
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

    
    <nav class="fixed w-full z-50 bg-white border-b border-white/10 transition-transform duration-300 hidden md:block" id="main-nav" style="top: 80px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center h-16">
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">
                        Home
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a href="/about" class="text-blue-600 font-semibold relative group">
                        About Us
                        <span class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600"></span>
                    </a>
                    <a href="/services" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">
                        Services
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a href="/project" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">
                        Project Reference
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a href="/contact" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">
                        Contact Us
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span>
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
                            <h3 class="text-sm font-bold text-gray-900">PT. Artha Solusi Aditama</h3>
                            <p class="text-xs text-gray-600">Menu</p>
                        </div>
                    </div>
                    <button class="text-gray-400 hover:text-gray-600 p-2" onclick="closeMobileSidebar()">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                
                <div class="flex-1 overflow-y-auto py-6">
                    <nav class="px-6 space-y-2">
                        <a href="/" class="flex items-center gap-3 px-4 py-3 font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-home w-5"></i>
                            <span>Home</span>
                        </a>
                        <a href="/about" class="flex items-center gap-3 px-4 py-3 font-semibold text-blue-600 bg-blue-50 rounded-xl transition-colors">
                            <i class="fas fa-info-circle w-5"></i>
                            <span>About Us</span>
                        </a>
                        <a href="/services" class="flex items-center gap-3 px-4 py-3 font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
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

    
    <section id="home" class="hero-bg pt-32 relative flex items-center overflow-hidden" style="height: 75vw;">
        <div class="absolute inset-0">
            <img src="../public/assets/images/hero-section.jpg" alt="HVACR Solutions" class="w-full h-full object-cover" onerror="this.src='https://images.unsplash.com/photo-1581094794329-c8112a89af12?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80'">
            <div class="absolute inset-0 bg-gradient-to-r from-sky-500/20 via-blue-500/15 to-cyan-500/20"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-white/10 via-transparent to-transparent"></div>
        </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 -mt-8">
            <div class="text-center fade-in">
                <h1 class="text-5xl md:text-7xl font-bold text-white mb-6 glow-text">
                    About <span class="text-gradient">Us</span>
                </h1>
                <p class="text-2xl md:text-3xl text-white mb-4 font-semibold tracking-wide">
                    HVACR Contractor and Specialist
                </p>
                <p class="text-lg md:text-xl text-white/90 mb-8 max-w-4xl mx-auto leading-relaxed font-light">
                    Providing solution with care
                </p>
                <p class="text-lg text-white/80 mb-12 max-w-4xl mx-auto leading-relaxed opacity-90">
                    From office towers to industrial plants, discover how we bring comfort, efficiency, and innovation to every space we touch.
                </p>
                <div class="flex flex-col sm:flex-row gap-6 justify-center">
                    <a href="/services" class="button-glow bg-gradient-to-r from-sky-500 via-blue-500 to-cyan-500 hover:from-sky-600 hover:via-blue-600 hover:to-cyan-600 text-white px-10 py-4 rounded-full text-lg font-semibold transition duration-300 shadow-2xl hover:shadow-sky-500/25 transform hover:scale-105">
                        Our Services
                        <i class="fas fa-arrow-right ml-3"></i>
                    </a>
                    <a href="#contact" class="glass-card text-white px-10 py-4 rounded-full text-lg font-semibold transition duration-300 hover:bg-white/30 border-2 border-white/40 hover:border-white/60">
                        Get In Touch
                    </a>
                </div>
            </div>
        </div>
    </section>

    
    <section class="py-20 bg-gradient-to-br from-white/60 via-sky-50/40 to-blue-50/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="slide-in-left flex flex-col justify-center">
                    <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4 text-center md:text-left">About PT. Artha Solusi Aditama</h2>
                    <p class="text-lg text-slate-700 mb-6 text-justify">Established in 2023 and based in Batam, Indonesia, PT. Artha Solusi Aditama specializes in HVACR systems for commercial and industrial clients. We deliver innovative, efficient, and reliable climate control solutions tailored to each project's needs.</p>

                    <div class="space-y-4">
                        <div class="bg-white/70 backdrop-blur-sm p-6 rounded-lg shadow-sm">
                            <h4 class="text-xl font-semibold text-blue-600 mb-2">Equipment Supply & Installation</h4>
                            <p class="text-slate-700">We supply and install a wide selection of equipment, including single split units, multi split systems, VRV/VRF systems, AHU/FCU units, chillers, cooling towers, pumps, and full building management systems.</p>
                        </div>
                        <div class="bg-white/70 backdrop-blur-sm p-6 rounded-lg shadow-sm">
                            <h4 class="text-xl font-semibold text-blue-600 mb-2">Comprehensive Services</h4>
                            <ul class="text-slate-700 list-disc list-inside space-y-1">
                                <li>HVAC installation</li>
                                <li>Ducting system fabrication and installation</li>
                                <li>Chiller piping installation</li>
                                <li>Energy-efficiency optimization</li>
                                <li>Preventive & corrective maintenance</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="slide-in-right">
                    <img src="../public/assets/images/ourteam.jpg" alt="Our Team" class="rounded-lg shadow-lg">
                </div>
            </div>
        </div>
    </section>

    
    <section class="py-24 bg-gradient-to-br from-white via-blue-50/50 to-sky-50 relative overflow-hidden">
        
        <div class="absolute inset-0 opacity-8">
            <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-blue-100/30 via-sky-100/20 to-cyan-100/30"></div>
            <div class="absolute top-20 left-20 w-96 h-96 bg-blue-300 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 right-20 w-80 h-80 bg-sky-300 rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-cyan-300 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="text-center mb-20">
                <h2 class="text-5xl md:text-6xl font-bold bg-gradient-to-r from-blue-600 via-sky-600 to-cyan-600 bg-clip-text text-transparent mb-8 slide-up">
                    VISION & MISSION
                </h2>
                <p class="text-xl text-gray-600 max-w-4xl mx-auto slide-up leading-relaxed">
                    Our vision and mission guide every decision we make, shaping our commitment to excellence in HVACR solutions.
                </p>
            </div>

            
            <div class="grid lg:grid-cols-2 gap-16 fade-in">
                
                <div class="relative">
                    
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50/60 via-sky-50/40 to-cyan-50/60 rounded-3xl"></div>
                    <div class="relative space-y-8 p-8">
                        <div class="text-center lg:text-left">
                            <h3 class="text-4xl md:text-5xl font-bold bg-gradient-to-r from-blue-600 to-sky-600 bg-clip-text text-transparent mb-6 slide-up">
                                VISION
                            </h3>
                            <p class="text-lg text-gray-600 leading-relaxed mb-8">
                                To be the finest HVACR solution provider that highly values accuracy, efficiency, and customer satisfaction.
                            </p>
                        </div>

                        <div class="group">
                            <div class="bg-white/95 backdrop-blur-sm rounded-3xl p-8 shadow-2xl hover:shadow-3xl transition-all duration-500 hover:-translate-y-3 border border-white/30">
                                <div class="flex items-start space-x-4">
                                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-sky-600 rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300 flex-shrink-0">
                                        <i class="fas fa-eye text-white text-2xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-blue-600 transition-colors duration-300">Our Vision</h4>
                                        <p class="text-gray-600 leading-relaxed">
                                            We aim to deliver precise, efficient, and customer-focused HVACR solutions that exceed expectations, setting the standard for excellence in climate control technology and service quality.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                
                <div class="relative">
                    
                    <div class="absolute inset-0 bg-gradient-to-br from-sky-50/60 via-cyan-50/40 to-blue-50/60 rounded-3xl"></div>
                    <div class="relative space-y-8 p-8">
                        <div class="text-center lg:text-left">
                            <h3 class="text-4xl md:text-5xl font-bold bg-gradient-to-r from-sky-600 to-cyan-600 bg-clip-text text-transparent mb-6 slide-up">
                                MISSION
                            </h3>
                            <p class="text-lg text-gray-600 leading-relaxed mb-8">
                                Our comprehensive mission encompasses technology excellence, ethical practices, and community impact.
                            </p>
                        </div>

                        <div class="space-y-6">
                            
                            <div class="group">
                                <div class="bg-white/95 backdrop-blur-sm rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-500 hover:-translate-y-2 border border-white/20">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-sky-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300 flex-shrink-0">
                                            <i class="fas fa-cogs text-white text-lg"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-gray-700 leading-relaxed text-sm">
                                                Encourage the Company's ability to master the latest technology through the development of facilities and the quality of human resources.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="group">
                                <div class="bg-white/95 backdrop-blur-sm rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-500 hover:-translate-y-2 border border-white/20">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-teal-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300 flex-shrink-0">
                                            <i class="fas fa-shield-alt text-white text-lg"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-gray-700 leading-relaxed text-sm">
                                                Carrying out the Company's activities with high ethical standards, honesty and integrity.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="group">
                                <div class="bg-white/95 backdrop-blur-sm rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-500 hover:-translate-y-2 border border-white/20">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-sky-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300 flex-shrink-0">
                                            <i class="fas fa-network-wired text-white text-lg"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-gray-700 leading-relaxed text-sm">
                                                Forming an extensive HVACR business network and cooperating with each other and developing.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="group">
                                <div class="bg-white/95 backdrop-blur-sm rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-500 hover:-translate-y-2 border border-white/20">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300 flex-shrink-0">
                                            <i class="fas fa-users text-white text-lg"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-gray-700 leading-relaxed text-sm">
                                                Optimizing the quality of human resources through continuous development and training, so as to achieve maximum performance and a prosperous life.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="group">
                                <div class="bg-white/95 backdrop-blur-sm rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-500 hover:-translate-y-2 border border-white/20">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300 flex-shrink-0">
                                            <i class="fas fa-heart text-white text-lg"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-gray-700 leading-relaxed text-sm">
                                                Providing appropriate, efficient, and innovative HVACR Solutions to meet customer needs, by prioritizing quality, timeliness, and customer satisfaction.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="group">
                                <div class="bg-white/95 backdrop-blur-sm rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-500 hover:-translate-y-2 border border-white/20">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-teal-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300 flex-shrink-0">
                                            <i class="fas fa-globe text-white text-lg"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-gray-700 leading-relaxed text-sm">
                                                Respect and fulfill the interests of all stakeholders through responsible and fair business practices.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="text-center mt-20 mb-20">
                <h2 class="text-4xl md:text-5xl font-bold bg-gradient-to-r from-blue-600 via-sky-600 to-cyan-600 bg-clip-text text-transparent mb-6 slide-up">
                    CORE VALUES
                </h2>
                <p class="text-xl text-gray-600 max-w-5xl mx-auto slide-up leading-relaxed text-justify">
                    At PT. Artha Solusi Aditama, we are driven by a strong foundation of values that guide every aspect of our business. Integrity is at the heart of our operations, we believe in being honest, transparent, and ethical in all interactions with clients, partners, and team members. We are deeply committed to delivering high-quality HVACR and engineering solutions, tailored to meet the unique needs of each project. Innovation plays a key role in how we operate; we constantly explore smarter, more efficient technologies to stay ahead in a rapidly evolving industry. Our customer-centric approach ensures that we listen, adapt, and provide real value through reliable service and technical expertise. We also prioritize safety and sustainability, embracing environmentally responsible practices that protect both people and the planet. Lastly, we value teamwork and collaboration, believing that excellence is best achieved through mutual respect and shared success.
                </p>
            </div>
        </div>
    </section>

    
    <section class="py-16 bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 relative overflow-hidden">
        
        <div class="absolute inset-0 opacity-5">
            <div class="absolute top-20 left-20 w-96 h-96 bg-blue-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 right-20 w-80 h-80 bg-sky-400 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="max-w-4xl mx-auto fade-in">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div class="space-y-6">
                        <div class="flex items-center space-x-4">
                            <div class="text-blue-600 icon-blur-to-normal icon-delay-1">
                                <i class="fas fa-heart text-2xl"></i>
                            </div>
                            <div class="text-slide-up text-delay-1">
                                <h3 class="text-lg font-bold text-gray-900 text-justify">Customer Satisfaction</h3>
                                <p class="text-gray-600 text-sm text-justify">Our top priority in every project</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-green-600 icon-blur-to-normal icon-delay-2">
                                <i class="fas fa-user-tie text-2xl"></i>
                            </div>
                            <div class="text-slide-up text-delay-2">
                                <h3 class="text-lg font-bold text-gray-900 text-justify">Professional</h3>
                                <p class="text-gray-600 text-sm text-justify">Highest standards in every endeavor</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-purple-600 icon-blur-to-normal icon-delay-3">
                                <i class="fas fa-award text-2xl"></i>
                            </div>
                            <div class="text-slide-up text-delay-3">
                                <h3 class="text-lg font-bold text-gray-900 text-justify">Quality</h3>
                                <p class="text-gray-600 text-sm text-justify">Exceptional results guaranteed</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-orange-600 icon-blur-to-normal icon-delay-4">
                                <i class="fas fa-bolt text-2xl"></i>
                            </div>
                            <div class="text-slide-up text-delay-4">
                                <h3 class="text-lg font-bold text-gray-900 text-justify">Efficiency</h3>
                                <p class="text-gray-600 text-sm text-justify">Optimized performance and sustainability</p>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <div class="text-blue-600 icon-blur-to-normal icon-delay-5 mb-6">
                            <i class="fas fa-shield-alt text-6xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4 text-slide-up text-delay-5 text-justify">Our Foundation</h3>
                        <p class="text-gray-600 leading-relaxed text-slide-up text-justify" style="animation-delay: 1.2s;">
                            These core values form the foundation of everything we do, ensuring that every solution we provide meets the highest standards of excellence and integrity.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    
    <footer class="bg-gradient-to-br from-slate-900 via-blue-900 to-slate-800 text-white py-16 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-sky-500/20 to-blue-500/20"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div class="fade-in">
                    <img src="/public/assets/images/logo.png" alt="PT. Artha Solusi Aditama" class="h-12 w-auto mb-6 filter drop-shadow-lg">
                    <p class="text-gray-300 leading-relaxed mb-6">HVACR Contractor and Specialist - Providing solution with care</p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 bg-white/10 backdrop-blur-sm rounded-xl flex items-center justify-center hover:bg-white/20 transition duration-300 border border-white/20"><i class="fab fa-facebook-f text-blue-400"></i></a>
                        <a href="#" class="w-10 h-10 bg-white/10 backdrop-blur-sm rounded-xl flex items-center justify-center hover:bg-white/20 transition duration-300 border border-white/20"><i class="fab fa-twitter text-blue-300"></i></a>
                        <a href="#" class="w-10 h-10 bg-white/10 backdrop-blur-sm rounded-xl flex items-center justify-center hover:bg-white/20 transition duration-300 border border-white/20"><i class="fab fa-linkedin-in text-blue-500"></i></a>
                        <a href="#" class="w-10 h-10 bg-white/10 backdrop-blur-sm rounded-xl flex items-center justify-center hover:bg-white/20 transition duration-300 border border-white/20"><i class="fab fa-instagram text-pink-400"></i></a>
                    </div>
                </div>
                <div class="fade-in">
                    <h4 class="text-xl font-bold mb-6 bg-gradient-to-r from-sky-400 to-blue-400 bg-clip-text text-transparent">Services</h4>
                    <ul class="space-y-3 text-gray-300">
                        <li><a href="/index.php#services" class="hover:text-white transition duration-300">Air Conditioning Systems</a></li>
                        <li><a href="/index.php#services" class="hover:text-white transition duration-300">HVAC Installation</a></li>
                        <li><a href="/index.php#services" class="hover:text-white transition duration-300">Maintenance & Repair</a></li>
                        <li><a href="/index.php#services" class="hover:text-white transition duration-300">Energy Optimization</a></li>
                    </ul>
                </div>
                <div class="fade-in">
                    <h4 class="text-xl font-bold mb-6 bg-gradient-to-r from-sky-400 to-blue-400 bg-clip-text text-transparent">Company</h4>
                    <ul class="space-y-3 text-gray-300">
                        <li><a href="/" class="hover:text-white transition duration-300">Home</a></li>
                        <li><a href="/about" class="hover:text-white transition duration-300">About Us</a></li>
                        <li><a href="/contact" class="hover:text-white transition duration-300">Contact</a></li>
                        <li><a href="http://localhost:8081/login.php" class="hover:text-white transition duration-300">Login</a></li>
                    </ul>
                </div>
                <div class="fade-in">
                    <h4 class="text-xl font-bold mb-6 bg-gradient-to-r from-sky-400 to-blue-400 bg-clip-text text-transparent">Contact Info</h4>
                    <div class="space-y-4 text-gray-300">
                        <div class="flex items-start">
                            <div class="w-8 h-8 bg-white/10 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3 mt-0.5 border border-white/20"><i class="fas fa-map-marker-alt text-green-400 text-sm"></i></div>
                            <span class="text-sm leading-relaxed">Lytech Industrial Park C2 No 3, Belian, Batam Kota, Batam, Kepulauan Riau, Indonesia, 29444</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-white/10 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3 border border-white/20"><i class="fas fa-phone text-blue-400 text-sm"></i></div>
                            <span class="text-sm">+62 851-8610-1125</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-white/10 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3 border border-white/20"><i class="fas fa-envelope text-purple-400 text-sm"></i></div>
                            <span class="text-sm">info@pt-asa.com</span>
                        </div>
                    </div>
                </div>
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
            const href = link && link.getAttribute ? link.getAttribute('href') : null;
            if (!href || typeof href !== 'string') return;
            if (href.startsWith('#') || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

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
                link.addEventListener('click', handlePageTransition);
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
                }, 300);
            } else {
                // Normal page load
                hidePageTransition();
            }
        }

        // Initialize on DOMContentLoaded only
        document.addEventListener('DOMContentLoaded', initializePageTransitions);

        // Smooth scroll for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // Intersection observers for animations
        const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
                    entry.target.classList.add('animated');
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all elements with animation classes
        document.querySelectorAll('.fade-in, .slide-up, .bounce-in, .slide-in-left, .slide-in-right').forEach(el => {
            observer.observe(el);
        });

        // Add hover effects for core values cards
        document.querySelectorAll('.group').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
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

        if (mobileMenuButton && mobileSidebar) {
            mobileMenuButton.addEventListener('click', function() {
                if (mobileSidebar.classList.contains('open')) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
            });
        }

        function openMobileSidebar() {
            const mobileSidebar = document.getElementById('mobile-sidebar');
            const sidebarPanel = document.getElementById('mobile-sidebar-panel');
            if (mobileSidebar) {
                mobileSidebar.classList.add('open');
            }
            if (sidebarPanel) {
                sidebarPanel.classList.remove('translate-x-full');
            }
        }

        // Close mobile sidebar function
        function closeMobileSidebar() {
            const mobileSidebar = document.getElementById('mobile-sidebar');
            const sidebarPanel = document.getElementById('mobile-sidebar-panel');
            if (mobileSidebar) {
                mobileSidebar.classList.remove('open');
            }
            if (sidebarPanel) {
                sidebarPanel.classList.add('translate-x-full');
            }
        }

        // Close sidebar when clicking on the overlay (blur area)
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeMobileSidebar);

        // Close sidebar when clicking any link inside it (keeps behavior consistent with index.php)
        document.querySelectorAll('#mobile-sidebar a').forEach(link => {
            link.addEventListener('click', closeMobileSidebar);
        });
    </script>

</body>
</html>
