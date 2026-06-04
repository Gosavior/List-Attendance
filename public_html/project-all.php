<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../public/assets/images/logo.png" type="image/x-icon">
    <title>All Projects - PT. Artha Solusi Aditama</title>
    <meta name="description" content="Daftar lengkap proyek HVACR PT. Artha Solusi Aditama mencakup instalasi, service, dan optimasi di berbagai industri.">
    <link rel="canonical" href="http://localhost/projects">
    <meta name="robots" content="index,follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="All Projects - PT. Artha Solusi Aditama">
    <meta property="og:description" content="Katalog lengkap proyek HVACR PT. Artha Solusi Aditama.">
    <meta property="og:url" content="http://localhost/projects">
    <meta property="og:image" content="http://localhost/public/assets/images/project/Ducting Installation Project.jpg">
    <meta property="og:image:alt" content="Daftar proyek PT. Artha Solusi Aditama">
    <meta property="og:site_name" content="PT. Artha Solusi Aditama">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="All Projects - PT. Artha Solusi Aditama">
    <meta name="twitter:description" content="Telusuri katalog proyek HVACR kami.">
    <meta name="twitter:image" content="http://localhost/public/assets/images/project/Ducting Installation Project.jpg">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        "name": "All Projects",
        "description": "Katalog proyek HVACR PT. Artha Solusi Aditama",
        "url": "http://localhost/projects"
    }
    </script>
    <link rel="stylesheet" href="./src/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Copy styles from project.php */
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
        /* Hide scrollbar for project photos containers */
        .project-photos-container::-webkit-scrollbar {
            display: none;
        }
        .project-photos-container {
            scrollbar-width: none;
            -ms-overflow-style: none;
            display: flex !important;
            align-items: flex-start !important; /* Prevent stretching */
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
        .project-photo {
            /* Larger fixed-height container so thumbnails appear bigger. */
            position: relative;
            transition: all 0.3s ease-in-out;
            height: 260px !important;
            min-height: 260px !important;
            min-width: 160px;
            max-width: 900px;
            overflow: hidden !important;
            border-radius: 12px;
            background: #f8fafc;
        }

        .project-photo img {
            /* Fill the container with cover so thumbnails look larger and consistent.
               Full image is shown in the lightbox. */
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            height: 100% !important;
            width: 100% !important;
            max-width: none !important;
            transform: translate(-50%, -50%) !important;
            object-fit: cover !important;
        }
        /* Sidebar overlay: hidden by default, shown when parent has .open
           - Keep overlay inert when closed, reveal with semi-transparent background when open */
        #mobile-sidebar { pointer-events: none; }
        #mobile-sidebar #sidebar-overlay {
            display: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s;
            z-index: 40;
            pointer-events: none;
            background-color: transparent;
            backdrop-filter: none !important;
        }
        #mobile-sidebar.open { pointer-events: auto; }
        #mobile-sidebar.open #sidebar-overlay {
            display: block;
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            background-color: rgba(0,0,0,0.45);
            backdrop-filter: blur(4px) !important;
        }
        #mobile-sidebar-panel { z-index: 50; pointer-events: auto; box-shadow: none !important; }
        #mobile-sidebar.open #mobile-sidebar-panel { box-shadow: 0 25px 50px rgba(0,0,0,0.25) !important; }
    </style>

    <style>
        /* Desktop-specific: make project photos wider so the carousel is useful on large screens */
        @media (min-width: 769px) {
            .project-photo {
                min-width: 220px !important;
                height: 260px !important;
                min-height: 260px !important;
                flex: 0 0 auto !important; /* ensure photos don't shrink */
            }
            /* On desktop, force horizontal (side-by-side) layout and enable horizontal scroll when needed */
            .project-photos-container {
                gap: 0.5rem !important; /* slightly tighter so thumbnails feel larger */
                display: flex !important;
                flex-wrap: nowrap !important;
                justify-content: flex-start !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }
            /* Nav buttons visibility will be controlled by JS depending on overflow */
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <style>
        /* Mobile tweaks: reduce base font-size and adjust gallery height */
        @media (max-width: 768px) {
            html, body { font-size: 14px; }
            h1, h2 { font-size: 1.5rem !important; }
            .project-photo { height: 140px !important; min-height: 140px !important; }
            .max-w-7xl { padding-left: 1rem; padding-right: 1rem; }
            /* Allow the hide-on-scroll JS to control header/nav visibility by
               not forcing a translateY style here. */
            header#main-header, nav#main-nav { position: sticky; top: 0; }

            /* Ensure mobile hamburger is visible, on top, and accepts pointer/touch events */
            #mobile-menu-button { display: inline-flex !important; align-items: center; justify-content: center; z-index: 9999 !important; position: relative !important; font-size: 1.25rem; padding: 0.45rem; color: #0ea5e9; cursor: pointer; pointer-events: auto !important; }

            /* Prevent icon containers from shrinking when adjacent text is long */
            .w-10.h-10, .contact-icon { flex-shrink: 0 !important; min-width: 40px !important; }

            /* Small safety for long nav link text inside the sidebar */
            .mobile-sidebar-nav a { min-width: 0; word-break: break-word; }
            /* Hide the horizontal nav on mobile; header contains the hamburger instead */
            #main-nav { display: none !important; }
            /* Reduce the large desktop top padding (pt-36) on mobile so hero sits closer to the top */
            main.pt-36 { padding-top: 0.5rem !important; }
            /* (carousel/photo mobile overrides removed to restore original behavior) */
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
                flex-direction: column;
                align-items: flex-start; /* left align children */
                text-align: left;
                gap: 0.25rem;
                padding-left: 0.25rem; /* small inset so logo isn't flush to edge */
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
            /* Reduce horizontal gap between project photos on small screens so slides aren't too spaced out */
            .project-photos-container {
                gap: 0.25rem !important; /* ~4px */
                padding-left: 0 !important; /* remove extra inset so first photo sits near edge */
                padding-right: 0 !important;
                justify-content: flex-start !important; /* ensure items stay together */
                scroll-snap-type: x mandatory !important; /* make snaps consistent */
                -webkit-overflow-scrolling: touch !important;
            }

            /* Slightly reduce min width for each photo container on very small screens so more photos fit */
            .project-photo {
                min-width: 160px !important;
                height: 180px !important;
                min-height: 180px !important;
                flex: 0 0 auto !important; /* avoid flex grow */
                scroll-snap-align: start !important;
                border-radius: 10px !important;
                background: #f8fafc;
            }
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
                
                <div class="md:hidden">
                    <button class="text-slate-700 hover:text-blue-600" id="mobile-menu-button">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <a href="http://localhost:8081/login.php" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-full text-sm font-medium transition">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    
    <nav class="fixed w-full z-50 bg-white/90 backdrop-blur-md border-b border-white/10 transition-transform duration-300" id="main-nav" style="top: 80px;">
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
                    <a href="/contact" class="text-slate-700 hover:text-blue-600 transition font-medium relative group">
                        Contact Us
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-blue-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                </div>
                <div class="md:hidden ml-auto">
                    
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-36">
        <section class="hero-gradient-bg py-20 lg:py-32">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-gradient mb-6">All Projects</h1>
                    <p class="text-lg md:text-xl text-slate-600 max-w-3xl mx-auto">Complete gallery of our completed projects showcasing our expertise in HVAC solutions.</p>
                </div>
            </div>
        </section>

        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-gradient mb-4">Project Gallery</h2>
                    <p class="text-lg text-slate-600">Explore our complete portfolio of HVAC projects</p>
                </div>

                
                <div class="mb-16 fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Cleaning VRF Samsung</h3>
                            <p class="text-slate-600">Professional cleaning and maintenance of Samsung VRF systems</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center text-sm text-slate-500 mb-1">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Completed 2024</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-500">
                                <i class="fas fa-tools mr-2"></i>
                                <span>Maintenance</span>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container">
                            <?php
                            
                            $folder = 'Cleaning VRF';
                            $mediaDir = __DIR__ . '/public/assets/images/project/' . $folder;
                            $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                            $projectRoot = __DIR__ . '/../public/assets/images/project';
                            
                            echo "<!-- DEBUG: mediaDir: " . htmlspecialchars($mediaDir) . " -->";
                            echo "<!-- DEBUG: webDir: " . htmlspecialchars($webDir) . " -->";
                            echo "<!-- DEBUG: Folder exists: " . (is_dir($mediaDir) ? 'YES' : 'NO') . " -->";
                            
                            $files = [];
                            if (is_dir($mediaDir)) {
                                $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                                foreach ($patterns as $p) {
                                    foreach (glob($mediaDir . '/' . $p) as $f) {
                                        $files[] = $f;
                                    }
                                }
                                usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });
                                
                                echo "<!-- DEBUG: Found " . count($files) . " files -->";
                            } else {
                                echo "<!-- DEBUG: Directory not found! -->";
                                
                                
                                $altDir1 = __DIR__ . '/public/assets/images/project/' . $folder;
                                $altDir2 = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/images/project/' . $folder;
                                
                                echo "<!-- DEBUG: altDir1 exists: " . (is_dir($altDir1) ? 'YES' : 'NO') . " -->";
                                echo "<!-- DEBUG: altDir2 exists: " . (is_dir($altDir2) ? 'YES' : 'NO') . " -->";
                            }
                        
                            foreach ($files as $i => $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $url = $webDir . rawurlencode(basename($file));
                                
                                echo "<!-- DEBUG: File: " . htmlspecialchars(basename($file)) . " -->";
                                echo "<!-- DEBUG: URL: " . htmlspecialchars($url) . " -->";
                                
                                echo "<div class=\"project-photo\">\n";
                                if (in_array($ext, ['mp4','webm'])) {
                                    echo "<video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                    echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                    echo "Your browser does not support the video tag.";
                                    echo "</video>\n";
                                } else {
                                    echo "<img src=\"" . htmlspecialchars($url) . "\" alt=\"Cleaning VRF Samsung - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                                }
                                echo "</div>\n";
                            }
                            
                            
                            if (empty($files)) {
                                echo "<!-- DEBUG: Showing placeholder -->";
                                echo "<div class=\"project-photo\">";
                                echo "<img src=\"../public/assets/images/logo.png\" alt=\"Placeholder\" class=\"transition-transform duration-300\">";
                                echo "</div>";
                                echo "<div class=\"project-photo\">";
                                echo "<div style=\"width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;\">No Image</div>";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                
                <div class="mb-16 fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Installation Sensor</h3>
                            <p class="text-slate-600">Precise installation of advanced sensors for enhanced system monitoring</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center text-sm text-slate-500 mb-1">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Completed 2024</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-500">
                                <i class="fas fa-tools mr-2"></i>
                                <span>Installation</span>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container">
                            <?php
                            
                            $folder = 'Installation Sensor';
                            $mediaDir = __DIR__ . '/public/assets/images/project/' . $folder;
                            $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                            $projectRoot = __DIR__ . '/../public/assets/images/project';
                            
                            echo "<!-- DEBUG: mediaDir: " . htmlspecialchars($mediaDir) . " -->";
                            echo "<!-- DEBUG: webDir: " . htmlspecialchars($webDir) . " -->";
                            echo "<!-- DEBUG: Folder exists: " . (is_dir($mediaDir) ? 'YES' : 'NO') . " -->";
                            
                            $files = [];
                            if (is_dir($mediaDir)) {
                                $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                                foreach ($patterns as $p) {
                                    foreach (glob($mediaDir . '/' . $p) as $f) {
                                        $files[] = $f;
                                    }
                                }
                                usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });
                                
                                echo "<!-- DEBUG: Found " . count($files) . " files -->";
                            } else {
                                echo "<!-- DEBUG: Directory not found! -->";
                                
                                
                                $altDir1 = __DIR__ . '/public/assets/images/project/' . $folder;
                                $altDir2 = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/images/project/' . $folder;
                                
                                echo "<!-- DEBUG: altDir1 exists: " . (is_dir($altDir1) ? 'YES' : 'NO') . " -->";
                                echo "<!-- DEBUG: altDir2 exists: " . (is_dir($altDir2) ? 'YES' : 'NO') . " -->";
                            }
                        
                            foreach ($files as $i => $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $url = $webDir . rawurlencode(basename($file));
                                
                                echo "<!-- DEBUG: File: " . htmlspecialchars(basename($file)) . " -->";
                                echo "<!-- DEBUG: URL: " . htmlspecialchars($url) . " -->";
                                
                                echo "<div class=\"project-photo\">\n";
                                if (in_array($ext, ['mp4','webm'])) {
                                    echo "<video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                    echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                    echo "Your browser does not support the video tag.";
                                    echo "</video>\n";
                                } else {
                                    echo "<img src=\"" . htmlspecialchars($url) . "\" alt=\"Cleaning VRF Samsung - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                                }
                                echo "</div>\n";
                            }
                            
                            
                            if (empty($files)) {
                                echo "<!-- DEBUG: Showing placeholder -->";
                                echo "<div class=\"project-photo\">";
                                echo "<img src=\"../public/assets/images/logo.png\" alt=\"Placeholder\" class=\"transition-transform duration-300\">";
                                echo "</div>";
                                echo "<div class=\"project-photo\">";
                                echo "<div style=\"width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;\">No Image</div>";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                
                <div class="mb-16 fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Installation Cassette</h3>
                            <p class="text-slate-600">Expert installation of cassette air conditioning units for commercial spaces</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center text-sm text-slate-500 mb-1">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Completed 2024</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-500">
                                <i class="fas fa-tools mr-2"></i>
                                <span>Installation</span>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container">
                            <?php
                            
                            $folder = 'Installation Cassette';
                            $mediaDir = __DIR__ . '/public/assets/images/project/' . $folder;
                            $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                            $projectRoot = __DIR__ . '/../public/assets/images/project';
                            
                            echo "<!-- DEBUG: mediaDir: " . htmlspecialchars($mediaDir) . " -->";
                            echo "<!-- DEBUG: webDir: " . htmlspecialchars($webDir) . " -->";
                            echo "<!-- DEBUG: Folder exists: " . (is_dir($mediaDir) ? 'YES' : 'NO') . " -->";
                            
                            $files = [];
                            if (is_dir($mediaDir)) {
                                $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                                foreach ($patterns as $p) {
                                    foreach (glob($mediaDir . '/' . $p) as $f) {
                                        $files[] = $f;
                                    }
                                }
                                usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });
                                
                                echo "<!-- DEBUG: Found " . count($files) . " files -->";
                            } else {
                                echo "<!-- DEBUG: Directory not found! -->";
                                
                                
                                $altDir1 = __DIR__ . '/public/assets/images/project/' . $folder;
                                $altDir2 = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/images/project/' . $folder;
                                
                                echo "<!-- DEBUG: altDir1 exists: " . (is_dir($altDir1) ? 'YES' : 'NO') . " -->";
                                echo "<!-- DEBUG: altDir2 exists: " . (is_dir($altDir2) ? 'YES' : 'NO') . " -->";
                            }
                        
                            foreach ($files as $i => $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $url = $webDir . rawurlencode(basename($file));
                                
                                echo "<!-- DEBUG: File: " . htmlspecialchars(basename($file)) . " -->";
                                echo "<!-- DEBUG: URL: " . htmlspecialchars($url) . " -->";
                                
                                echo "<div class=\"project-photo\">\n";
                                if (in_array($ext, ['mp4','webm'])) {
                                    echo "<video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                    echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                    echo "Your browser does not support the video tag.";
                                    echo "</video>\n";
                                } else {
                                    echo "<img src=\"" . htmlspecialchars($url) . "\" alt=\"Cleaning VRF Samsung - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                                }
                                echo "</div>\n";
                            }
                            
                            
                            if (empty($files)) {
                                echo "<!-- DEBUG: Showing placeholder -->";
                                echo "<div class=\"project-photo\">";
                                echo "<img src=\"../public/assets/images/logo.png\" alt=\"Placeholder\" class=\"transition-transform duration-300\">";
                                echo "</div>";
                                echo "<div class=\"project-photo\">";
                                echo "<div style=\"width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;\">No Image</div>";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                
                <div class="mb-16 fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Install AC Split Floorstanding</h3>
                            <p class="text-slate-600">Installation of floorstanding split AC systems for optimal space utilization</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center text-sm text-slate-500 mb-1">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Completed 2024</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-500">
                                <i class="fas fa-tools mr-2"></i>
                                <span>Installation</span>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container">
                            <?php
                            
                            $folder = 'Install AC Split Floorstanding';
                            $mediaDir = __DIR__ . '/public/assets/images/project/' . $folder;
                            $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                            $projectRoot = __DIR__ . '/../public/assets/images/project';
                            
                            echo "<!-- DEBUG: mediaDir: " . htmlspecialchars($mediaDir) . " -->";
                            echo "<!-- DEBUG: webDir: " . htmlspecialchars($webDir) . " -->";
                            echo "<!-- DEBUG: Folder exists: " . (is_dir($mediaDir) ? 'YES' : 'NO') . " -->";
                            
                            $files = [];
                            if (is_dir($mediaDir)) {
                                $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                                foreach ($patterns as $p) {
                                    foreach (glob($mediaDir . '/' . $p) as $f) {
                                        $files[] = $f;
                                    }
                                }
                                usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });
                                
                                echo "<!-- DEBUG: Found " . count($files) . " files -->";
                            } else {
                                echo "<!-- DEBUG: Directory not found! -->";
                                
                                
                                $altDir1 = __DIR__ . '/public/assets/images/project/' . $folder;
                                $altDir2 = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/images/project/' . $folder;
                                
                                echo "<!-- DEBUG: altDir1 exists: " . (is_dir($altDir1) ? 'YES' : 'NO') . " -->";
                                echo "<!-- DEBUG: altDir2 exists: " . (is_dir($altDir2) ? 'YES' : 'NO') . " -->";
                            }
                        
                            foreach ($files as $i => $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $url = $webDir . rawurlencode(basename($file));
                                
                                echo "<!-- DEBUG: File: " . htmlspecialchars(basename($file)) . " -->";
                                echo "<!-- DEBUG: URL: " . htmlspecialchars($url) . " -->";
                                
                                echo "<div class=\"project-photo\">\n";
                                if (in_array($ext, ['mp4','webm'])) {
                                    echo "<video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                    echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                    echo "Your browser does not support the video tag.";
                                    echo "</video>\n";
                                } else {
                                    echo "<img src=\"" . htmlspecialchars($url) . "\" alt=\"Cleaning VRF Samsung - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                                }
                                echo "</div>\n";
                            }
                            
                            
                            if (empty($files)) {
                                echo "<!-- DEBUG: Showing placeholder -->";
                                echo "<div class=\"project-photo\">";
                                echo "<img src=\"../public/assets/images/logo.png\" alt=\"Placeholder\" class=\"transition-transform duration-300\">";
                                echo "</div>";
                                echo "<div class=\"project-photo\">";
                                echo "<div style=\"width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;\">No Image</div>";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                
                <div class="mb-16 fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Preventive Maintenance</h3>
                            <p class="text-slate-600">Comprehensive preventive maintenance services to ensure system reliability</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center text-sm text-slate-500 mb-1">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Completed 2024</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-500">
                                <i class="fas fa-tools mr-2"></i>
                                <span>Maintenance</span>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container">
                            <?php
                            
                            $folder = 'Preventive Maintenance';
                            $mediaDir = __DIR__ . '/public/assets/images/project/' . $folder;
                            $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                            $projectRoot = __DIR__ . '/../public/assets/images/project';
                            
                            echo "<!-- DEBUG: mediaDir: " . htmlspecialchars($mediaDir) . " -->";
                            echo "<!-- DEBUG: webDir: " . htmlspecialchars($webDir) . " -->";
                            echo "<!-- DEBUG: Folder exists: " . (is_dir($mediaDir) ? 'YES' : 'NO') . " -->";
                            
                            $files = [];
                            if (is_dir($mediaDir)) {
                                $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                                foreach ($patterns as $p) {
                                    foreach (glob($mediaDir . '/' . $p) as $f) {
                                        $files[] = $f;
                                    }
                                }
                                usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });
                                
                                echo "<!-- DEBUG: Found " . count($files) . " files -->";
                            } else {
                                echo "<!-- DEBUG: Directory not found! -->";
                                
                                
                                $altDir1 = __DIR__ . '/public/assets/images/project/' . $folder;
                                $altDir2 = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/images/project/' . $folder;
                                
                                echo "<!-- DEBUG: altDir1 exists: " . (is_dir($altDir1) ? 'YES' : 'NO') . " -->";
                                echo "<!-- DEBUG: altDir2 exists: " . (is_dir($altDir2) ? 'YES' : 'NO') . " -->";
                            }
                        
                            foreach ($files as $i => $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $url = $webDir . rawurlencode(basename($file));
                                
                                echo "<!-- DEBUG: File: " . htmlspecialchars(basename($file)) . " -->";
                                echo "<!-- DEBUG: URL: " . htmlspecialchars($url) . " -->";
                                
                                echo "<div class=\"project-photo\">\n";
                                if (in_array($ext, ['mp4','webm'])) {
                                    echo "<video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                    echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                    echo "Your browser does not support the video tag.";
                                    echo "</video>\n";
                                } else {
                                    echo "<img src=\"" . htmlspecialchars($url) . "\" alt=\"Cleaning VRF Samsung - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                                }
                                echo "</div>\n";
                            }
                            
                            
                            if (empty($files)) {
                                echo "<!-- DEBUG: Showing placeholder -->";
                                echo "<div class=\"project-photo\">";
                                echo "<img src=\"../public/assets/images/logo.png\" alt=\"Placeholder\" class=\"transition-transform duration-300\">";
                                echo "</div>";
                                echo "<div class=\"project-photo\">";
                                echo "<div style=\"width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#666;\">No Image</div>";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                
                <div class="mb-16 fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Chiller Fan Motor Bearing Replacement </h3>
                            <p class="text-slate-600">Comprehensive chiller maintenance including fan motor bearing replacement</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center text-sm text-slate-500 mb-1">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Completed 2024</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-500">
                                <i class="fas fa-tools mr-2"></i>
                                <span>Maintenance & Repair</span>
                            </div>
                        </div>
                    </div>
                
                    <div class="relative">
                        <div class="flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container">
                            <?php
                            
                            $folder = 'Chiller Fan Motor Bearing Replacement';
                            $altPrefix = 'Chiller Fan Motor Bearing Replacement';
                            $mediaDir = __DIR__ . '/public/assets/images/project/' . $folder;
                            $files = [];
                            if (is_dir($mediaDir)) {
                                $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                                foreach ($patterns as $p) {
                                    foreach (glob($mediaDir . '/' . $p) as $f) {
                                        $files[] = $f;
                                    }
                                }
                                usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });
                            }
                            foreach ($files as $i => $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                                $url = $webDir . rawurlencode(basename($file));
                                echo "<div class=\"project-photo\">\n";
                                if (in_array($ext, ['mp4','webm'])) {
                                    echo "<video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                    echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                    echo "Your browser does not support the video tag.";
                                    echo "</video>\n";
                                } else {
                                    echo "<img src=\"" . htmlspecialchars($url) . "\" alt=\"" . htmlspecialchars($altPrefix) . " - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                                }
                                echo "</div>\n";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                
                <div class="mb-16 fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Install AC Split Wall Mounted</h3>
                            <p class="text-slate-600">Professional installation of wall-mounted split AC units for residential and commercial spaces</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center text-sm text-slate-500 mb-1">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Completed 2024</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-500">
                                <i class="fas fa-tools mr-2"></i>
                                <span>Installation</span>
                            </div>
                        </div>
                    </div>
                
                    <div class="relative">
                        <div class="flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container">
                            <?php
                            
                            $folder = 'Install AC Split Wall Mounted';
                            $altPrefix = 'Install AC Split Wall Mounted';
                            $mediaDir = __DIR__ . '/public/assets/images/project/' . $folder;
                            $files = [];
                            if (is_dir($mediaDir)) {
                                $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                                foreach ($patterns as $p) {
                                    foreach (glob($mediaDir . '/' . $p) as $f) {
                                        $files[] = $f;
                                    }
                                }
                                usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });
                            }
                            foreach ($files as $i => $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                                $url = $webDir . rawurlencode(basename($file));
                                echo "<div class=\"project-photo\">\n";
                                if (in_array($ext, ['mp4','webm'])) {
                                    echo "<video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                    echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                    echo "Your browser does not support the video tag.";
                                    echo "</video>\n";
                                } else {
                                    echo "<img src=\"" . htmlspecialchars($url) . "\" alt=\"" . htmlspecialchars($altPrefix) . " - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                                }
                                echo "</div>\n";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                
                <div class="mb-16 fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Replace V-Belt AHU</h3>
                            <p class="text-slate-600">Professional replacement of V-belts in Air Handling Units for optimal performance</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center text-sm text-slate-500 mb-1">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Completed 2024</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-500">
                                <i class="fas fa-tools mr-2"></i>
                                <span>Maintenance</span>
                            </div>
                        </div>
                    </div>
                
                    <div class="relative">
                        <div class="flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container">
                            <?php
                            
                            $folder = 'Replace V-Belt AHU';
                            $altPrefix = 'Replace V-Belt AHU';
                            $mediaDir = __DIR__ . '/public/assets/images/project/' . $folder;
                            $files = [];
                            if (is_dir($mediaDir)) {
                                $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                                foreach ($patterns as $p) {
                                    foreach (glob($mediaDir . '/' . $p) as $f) {
                                        $files[] = $f;
                                    }
                                }
                                usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });
                            }
                            foreach ($files as $i => $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                                $url = $webDir . rawurlencode(basename($file));
                                echo "<div class=\"project-photo\">\n";
                                if (in_array($ext, ['mp4','webm'])) {
                                    echo "<video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                    echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                    echo "Your browser does not support the video tag.";
                                    echo "</video>\n";
                                } else {
                                    echo "<img src=\"" . htmlspecialchars($url) . "\" alt=\"" . htmlspecialchars($altPrefix) . " - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                                }
                                echo "</div>\n";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                
                <div class="mb-16 fade-in">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Welding Pipe</h3>
                            <p class="text-slate-600">Precision pipe welding services for HVAC systems and industrial applications</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center text-sm text-slate-500 mb-1">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Completed 2024</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-500">
                                <i class="fas fa-tools mr-2"></i>
                                <span>Fabrication</span>
                            </div>
                        </div>
                    </div>
                
                    <div class="relative">
                        <div class="flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container">
                            <?php
                            
                            $folder = 'Welding Pipe';
                            $altPrefix = 'Welding Pipe';
                            $mediaDir = __DIR__ . '/public/assets/images/project/' . $folder;
                            $files = [];
                            if (is_dir($mediaDir)) {
                                $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                                foreach ($patterns as $p) {
                                    foreach (glob($mediaDir . '/' . $p) as $f) {
                                        $files[] = $f;
                                    }
                                }
                                usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });
                            }
                            foreach ($files as $i => $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                                $url = $webDir . rawurlencode(basename($file));
                                echo "<div class=\"project-photo\">\n";
                                if (in_array($ext, ['mp4','webm'])) {
                                    echo "<video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                    echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                    echo "Your browser does not support the video tag.";
                                    echo "</video>\n";
                                } else {
                                    echo "<img src=\"" . htmlspecialchars($url) . "\" alt=\"" . htmlspecialchars($altPrefix) . " - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                                }
                                echo "</div>\n";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <?php
                
                $projectRoot = __DIR__ . '../public/assets/images/project';
                $already = [
                    'Cleaning VRF',
                    'Installation Sensor',
                    'Installation Cassette',
                    'Install AC Split Floorstanding',
                    'Preventive Maintenance',
                    'Chiller Fan Motor Bearing Replacement',
                    'Install AC Split Wall Mounted',
                    'Replace V-Belt AHU',
                    'Wedding Pipe'
                ];

                if (is_dir($projectRoot)) {
                    $dirs = array_filter(glob($projectRoot . '/*'), 'is_dir');
                    usort($dirs, function($a, $b){ return strnatcasecmp(basename($a), basename($b)); });

                    foreach ($dirs as $dir) {
                        $folder = basename($dir);
                        if (in_array($folder, $already)) continue; 

                        
                        $title = htmlspecialchars($folder);
                        $altPrefix = $folder;
                        echo "\n                <div class=\"mb-16 fade-in\">\n";
                        echo "                    <div class=\"flex items-center justify-between mb-8\">\n";
                        echo "                        <div>\n";
                        echo "                            <h3 class=\"text-2xl md:text-3xl font-bold text-slate-800 mb-2\">" . $title . "</h3>\n";
                        echo "                            <p class=\"text-slate-600\">project: " . $title . "</p>\n";
                        echo "                        </div>\n";
                        echo "                        <div class=\"text-right\">\n";
                        echo "                            <div class=\"flex items-center text-sm text-slate-500 mb-1\">\n";
                        echo "                                <i class=\"fas fa-calendar-alt mr-2\"></i>\n";
                        echo "                                <span>Completed 2024</span>\n";
                        echo "                            </div>\n";
                        echo "                            <div class=\"flex items-center text-sm text-slate-500\">\n";
                        echo "                                <i class=\"fas fa-tools mr-2\"></i>\n";
                        echo "                                <span>project</span>\n";
                        echo "                            </div>\n";
                        echo "                        </div>\n";
                        echo "                    </div>\n";

                        echo "                    <div class=\"relative\">\n";
                        echo "                        <div class=\"flex gap-4 overflow-x-auto scroll-smooth pb-4 project-photos-container\">\n";

                        
                        $files = [];
                        $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.mp4','*.webm'];
                        foreach ($patterns as $p) {
                            foreach (glob($dir . '/' . $p) as $f) {
                                $files[] = $f;
                            }
                        }
                        usort($files, function($a, $b){ return strnatcmp(basename($a), basename($b)); });

                        foreach ($files as $i => $file) {
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            $webDir = '../public/assets/images/project/' . rawurlencode($folder) . '/';
                            $url = $webDir . rawurlencode(basename($file));
                            echo "                            <div class=\"project-photo\">\n";
                            if (in_array($ext, ['mp4','webm'])) {
                                echo "                                <video class=\"transition-transform duration-300\" controls playsinline preload=\"metadata\" style=\"width:100%;height:100%;object-fit:contain;\">";
                                echo "<source src=\"" . htmlspecialchars($url) . "\" type=\"video/" . htmlspecialchars($ext) . "\">";
                                echo "Your browser does not support the video tag.";
                                echo "</video>\n";
                            } else {
                                echo "                                <img src=\"" . htmlspecialchars($url) . "\" alt=\"" . htmlspecialchars($altPrefix) . " - Photo " . ($i+1) . "\" class=\"transition-transform duration-300\">\n";
                            }
                            echo "                            </div>\n";
                        }

                        echo "                        </div>\n";
                        echo "                    </div>\n";
                        echo "                </div>\n";
                    }
                }
                ?>
            </div>
        </section>
    </main>

    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center my-12">
        <div class="text-slate-700 font-extrabold text-3xl sm:text-4xl md:text-5xl">AND MORE..</div>
    </div>

    
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

    
    <style>
        /* Keep prev/next lightbox buttons fixed at the viewport sides so they don't move with image size */
        #prev-lightbox, #next-lightbox {
            position: fixed !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            z-index: 99999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: rgba(0,0,0,0.5) !important;
            backdrop-filter: none !important;
        }
        #prev-lightbox { left: 12px !important; }
        #next-lightbox { right: 12px !important; }

        /* Slightly smaller controls on very small screens to avoid overlap */
        @media (max-width: 420px) {
            #prev-lightbox, #next-lightbox { width: 40px !important; height: 40px !important; left: 8px !important; right: 8px !important; }
        }
    </style>
    <div id="lightbox-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 opacity-0 invisible flex items-center justify-center p-4 transition-all duration-300">
        <div class="relative max-w-4xl max-h-full">
            <img id="lightbox-image" src="" alt="" class="max-w-full max-h-full object-contain rounded-2xl">
            <button id="close-lightbox" class="absolute -top-12 right-0 text-white hover:text-gray-300 text-2xl">
                <i class="fas fa-times"></i>
            </button>
            <button id="prev-lightbox" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 text-3xl bg-black/50 hover:bg-black/70 rounded-full w-12 h-12 flex items-center justify-center transition-all duration-300">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button id="next-lightbox" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 text-3xl bg-black/50 hover:bg-black/70 rounded-full w-12 h-12 flex items-center justify-center transition-all duration-300">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    

    
    <div id="mobile-sidebar" class="fixed inset-0 z-50 md:hidden">
    <div class="absolute inset-0 bg-black/0" id="sidebar-overlay"></div>
        <div id="mobile-sidebar-panel" class="absolute right-0 top-0 h-full w-80 bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out">
            <div class="flex flex-col h-full">
                
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <img class="h-10 w-auto" src="../public/assets/images/logo.png" alt="PT. Artha Solusi Aditama">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">PT. Artha Solusi Aditama</h2>
                            <p class="text-xs text-gray-600">Menu</p>
                        </div>
                    </div>
                    <button class="text-gray-500 hover:text-gray-700 p-2" id="close-sidebar">
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
                        <a href="/services" class="flex items-center gap-3 px-4 py-3 font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-cogs w-5"></i>
                            <span>Services</span>
                        </a>
                        <a href="/project" class="flex items-center gap-3 px-4 py-3 font-semibold text-blue-600 bg-blue-50 rounded-xl transition-colors">
                            <i class="fas fa-project-diagram w-5"></i>
                            <span>project Reference</span>
                        </a>
                        <a href="/contact" class="flex items-center gap-3 px-4 py-3 font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                            <i class="fas fa-phone w-5"></i>
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
            </div>
        </div>
    </div>

    <script>
        // Copy scripts from project.php
        function showPageTransition() {
            const transition = document.getElementById('page-transition');
            if (transition) transition.classList.add('active');
        }

        function hidePageTransition() {
            const transition = document.getElementById('page-transition');
            if (transition) transition.classList.remove('active');
        }

        function handlePageTransition(event) {
            const link = event.currentTarget;
            const href = link && link.getAttribute ? link.getAttribute('href') : null;
            if (!href || typeof href !== 'string') return;
            if (href.startsWith('#') || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

            event.preventDefault();
            showPageTransition();
            document.body.classList.add('fade-out');

            setTimeout(() => {
                window.location.href = href;
            }, 800);
        }

        function initializePageTransitions() {
            const internalLinks = document.querySelectorAll('a[href]:not([href^="http"]):not([href^="mailto:"]):not([href^="tel:"]):not([href^="#"])');

            internalLinks.forEach(link => {
                try {
                    const href = link.getAttribute && link.getAttribute('href') ? link.getAttribute('href') : null;
                    if (href && typeof href === 'string') link.addEventListener('click', handlePageTransition);
                } catch (e) {}
            });

            let isFromNavigation = false;
            try {
                const navigationType = performance.getEntriesByType('navigation')[0]?.type;
                isFromNavigation = navigationType === 'navigate' || (document.referrer && document.referrer.includes(window.location.hostname) && document.referrer !== window.location.href);
            } catch (e) {
                isFromNavigation = document.referrer && document.referrer.includes(window.location.hostname) && document.referrer !== window.location.href;
            }

            const header = document.getElementById('main-header');
            const navbar = document.getElementById('main-nav');
            if (header) header.style.opacity = '1';
            if (navbar) navbar.style.opacity = '1';

            if (isFromNavigation) {
                showPageTransition();
                setTimeout(() => {
                    hidePageTransition();
                    document.body.classList.add('fade-in');
                }, 300);
            } else {
                hidePageTransition();
                document.body.classList.add('fade-in');
            }
        }

        document.addEventListener('DOMContentLoaded', initializePageTransitions);
        window.addEventListener('load', function() {
            if (!document.body.classList.contains('fade-in')) {
                initializePageTransitions();
            }
            setTimeout(() => {
                hidePageTransition();
            }, 500);
        });

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const href = this.getAttribute && this.getAttribute('href') ? this.getAttribute('href') : null;
                if (!href) return;
                const target = document.querySelector(href);
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

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

        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 1s ease, transform 1s ease';
            observer.observe(el);
        });

        let lastScrollTop = 0;
        const header = document.getElementById('main-header');
        const navbar = document.getElementById('main-nav');

        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            if (scrollTop > lastScrollTop && scrollTop > 80) {
                if (header) header.style.transform = 'translateY(-100%)';
                if (navbar) navbar.style.transform = 'translateY(-80px)';
            } else {
                if (header) header.style.transform = 'translateY(0)';
                if (navbar) navbar.style.transform = 'translateY(0)';
            }

            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        });

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                hidePageTransition();
            }
        });

        // Project Horizontal Scroll Functionality
        const projectContainers = document.querySelectorAll('.project-photos-container');

        // Helper: compute a sensible scroll step based on first .project-photo width + container gap
        function getScrollStep(container) {
            try {
                const child = container.querySelector('.project-photo');
                const childRect = child ? child.getBoundingClientRect() : null;
                // Read gap from computed style (fallback to 12px)
                const style = getComputedStyle(container);
                const gap = parseFloat(style.gap || style.columnGap) || 12;
                const width = childRect ? Math.round(childRect.width) : 240;
                return Math.max(80, Math.round(width + gap));
            } catch (e) {
                return 276; // fallback
            }
        }

        projectContainers.forEach((container, index) => {
            // Add navigation buttons for each project
            const projectSection = container.closest('.mb-16');
            const navContainer = document.createElement('div');
            navContainer.className = 'flex justify-center mt-6 space-x-4';
            navContainer.innerHTML = `
                <button class="scroll-left-btn bg-white/90 backdrop-blur-sm hover:bg-white text-slate-700 hover:text-blue-600 w-10 h-10 rounded-full shadow-lg flex items-center justify-center transition-all duration-300 border border-white/20" data-container="${index}">
                    <i class="fas fa-chevron-left text-sm"></i>
                </button>
                <button class="scroll-right-btn bg-white/90 backdrop-blur-sm hover:bg-white text-slate-700 hover:text-blue-600 w-10 h-10 rounded-full shadow-lg flex items-center justify-center transition-all duration-300 border border-white/20" data-container="${index}">
                    <i class="fas fa-chevron-right text-sm"></i>
                </button>
            `;
            if (projectSection) projectSection.appendChild(navContainer);

            // Add event listeners for navigation buttons
            const leftBtn = navContainer.querySelector('.scroll-left-btn');
            const rightBtn = navContainer.querySelector('.scroll-right-btn');

            if (leftBtn) leftBtn.addEventListener('click', () => {
                const step = getScrollStep(container);
                container.scrollBy({ left: -step, behavior: 'smooth' });
            });

            if (rightBtn) rightBtn.addEventListener('click', () => {
                const step = getScrollStep(container);
                container.scrollBy({ left: step, behavior: 'smooth' });
            });

            // (removed per-gallery AND MORE insertion - single site-wide label will be shown above the footer)
        });

        // Auto-scroll functionality for each container
        projectContainers.forEach((container) => {
            let autoScrollInterval;

            function startAutoScroll() {
                autoScrollInterval = setInterval(() => {
                    const maxScroll = container.scrollWidth - container.clientWidth;
                    if (container.scrollLeft >= maxScroll - 10) {
                        // Reset to beginning
                        container.scrollTo({ left: 0, behavior: 'smooth' });
                    } else {
                        const step = getScrollStep(container);
                        container.scrollBy({ left: step, behavior: 'smooth' });
                    }
                }, 5000); // Auto scroll every 5 seconds
            }

            function stopAutoScroll() {
                clearInterval(autoScrollInterval);
            }

            // Start auto-scroll only if overflow
            if (container.scrollWidth > container.clientWidth) {
                startAutoScroll();
            }

            // Pause on hover
            container.addEventListener('mouseenter', stopAutoScroll);
            container.addEventListener('mouseleave', startAutoScroll);
        });

        // Keyboard navigation (global)
        document.addEventListener('keydown', (e) => {
            // Close lightbox on Escape
            if (e.key === 'Escape' && lightbox && lightbox.classList.contains('visible')) {
                closeLightboxModal();
                return;
            }
            const focusedContainer = document.activeElement?.closest('.project-photos-container') ||
                                   document.querySelector('.project-photos-container');

            if (focusedContainer) {
                const step = getScrollStep(focusedContainer);
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    focusedContainer.scrollBy({ left: -step, behavior: 'smooth' });
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    focusedContainer.scrollBy({ left: step, behavior: 'smooth' });
                }
            }
        });

        // Lightbox Functionality
        const lightbox = document.getElementById('lightbox-modal');
        const lightboxImage = document.getElementById('lightbox-image');
        const closeLightboxBtn = document.getElementById('close-lightbox');
        const prevLightboxBtn = document.getElementById('prev-lightbox');
        const nextLightboxBtn = document.getElementById('next-lightbox');

        let currentProjectImages = [];
        let currentImageIndex = 0;

        function openLightbox(src, alt, projectContainer) {
            if (lightboxImage && lightbox) {
                // Get all images from the same project
                const projectImages = projectContainer.querySelectorAll('img');
                currentProjectImages = Array.from(projectImages).map(img => ({
                    src: img.src,
                    alt: img.alt
                }));

                // Find current image index
                currentImageIndex = currentProjectImages.findIndex(img => img.src === src);

                lightboxImage.src = src;
                lightboxImage.alt = alt;
                lightboxImage.style.maxWidth = '60vw';
                lightboxImage.style.maxHeight = '60vh';
                lightboxImage.style.objectFit = 'contain';
                lightbox.classList.remove('opacity-0', 'invisible');
                lightbox.classList.add('opacity-100', 'visible');
                document.body.style.overflow = 'hidden';

                // Show/hide navigation buttons based on number of images
                if (prevLightboxBtn && nextLightboxBtn) {
                    const hasMultipleImages = currentProjectImages.length > 1;
                    prevLightboxBtn.style.display = hasMultipleImages ? 'flex' : 'none';
                    nextLightboxBtn.style.display = hasMultipleImages ? 'flex' : 'none';
                }
            }
        }

        function navigateLightbox(direction) {
            if (currentProjectImages.length <= 1) return;

            currentImageIndex += direction;

            if (currentImageIndex < 0) {
                currentImageIndex = currentProjectImages.length - 1;
            } else if (currentImageIndex >= currentProjectImages.length) {
                currentImageIndex = 0;
            }

            const currentImage = currentProjectImages[currentImageIndex];
            lightboxImage.src = currentImage.src;
            lightboxImage.alt = currentImage.alt;
        }

        function closeLightboxModal() {
            if (lightbox) {
                lightbox.classList.remove('opacity-100', 'visible');
                lightbox.classList.add('opacity-0', 'invisible');
                document.body.style.overflow = 'auto';
                currentProjectImages = [];
                currentImageIndex = 0;
            }
        }

        // Add click event to project images
        document.querySelectorAll('.project-photo img').forEach(img => {
            img.style.cursor = 'pointer';
            img.addEventListener('click', (e) => {
                const projectContainer = e.target.closest('.project-photos-container');
                openLightbox(img.src, img.alt, projectContainer);
            });
        });

        if (closeLightboxBtn) {
            closeLightboxBtn.addEventListener('click', closeLightboxModal);
        }

        if (prevLightboxBtn) {
            prevLightboxBtn.addEventListener('click', () => navigateLightbox(-1));
        }

        if (nextLightboxBtn) {
            nextLightboxBtn.addEventListener('click', () => navigateLightbox(1));
        }

        if (lightbox) {
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) {
                    closeLightboxModal();
                }
            });
        }

        // Dynamic approach: keep all photo containers the same height but adjust widths
        // so each image displays at the same visible height without cropping.
        (function() {
            // Use a fixed-height that respects mobile CSS: smaller on narrow viewports
            const FIXED_HEIGHT = (window.innerWidth <= 768) ? 140 : 260; // px
            // Use a larger minimum width on desktop so photos aren't tiny inside the carousel
            const MIN_WIDTH = (window.innerWidth <= 768) ? 120 : 180; // px
            const MAX_WIDTH = 600; // reasonable maximum width for very wide images

            function adjustPhotoContainers() {
                const photoContainers = document.querySelectorAll('.project-photo');

                photoContainers.forEach(container => {
                    // Ensure height is fixed
                    container.style.height = FIXED_HEIGHT + 'px';
                    container.style.minHeight = FIXED_HEIGHT + 'px';
                    container.style.maxHeight = FIXED_HEIGHT + 'px';
                    container.style.flexShrink = '0';

                    const img = container.querySelector('img');
                    if (!img) return;

                    const applyWidthForImage = () => {
                        // On mobile we want consistent widths so gaps stay uniform.
                        if (window.innerWidth <= 768) {
                            const UNIFORM_MOBILE_WIDTH = 200; // px: uniform width for all photos on mobile
                            const mobileCap = 220; // safety cap
                            const desiredWidth = Math.max(MIN_WIDTH, Math.min(mobileCap, UNIFORM_MOBILE_WIDTH));
                            container.style.width = desiredWidth + 'px';
                            container.style.flex = '0 0 auto';
                            return;
                        }

                        // Use natural dimensions when available for larger screens
                        const iw = img.naturalWidth || img.width || 1;
                        const ih = img.naturalHeight || img.height || 1;
                        const aspect = iw / ih || 1;

                        // Width required so image height fills FIXED_HEIGHT
                        let desiredWidth = Math.round(FIXED_HEIGHT * aspect);
                        desiredWidth = Math.max(MIN_WIDTH, Math.min(MAX_WIDTH, desiredWidth));
                        container.style.width = desiredWidth + 'px';
                        container.style.flex = '0 0 auto';
                    };

                    // Run now if already loaded, otherwise on load
                    if (img.complete) {
                        applyWidthForImage();
                    } else {
                        img.addEventListener('load', applyWidthForImage, { once: true });
                    }
                });
            }

            // Run on key lifecycle events and on resize
            document.addEventListener('DOMContentLoaded', adjustPhotoContainers);
            window.addEventListener('load', adjustPhotoContainers);
            window.addEventListener('resize', adjustPhotoContainers);

            // A few delayed runs to catch late-loading images
            setTimeout(adjustPhotoContainers, 150);
            setTimeout(adjustPhotoContainers, 700);
            // Extra delayed runs and micro-scroll to force layout/reflow on slow images/devices
            // Only run these on mobile to avoid desktop layout side-effects
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    adjustPhotoContainers();
                    // micro-bump each container to force repaint so spacing becomes final
                    document.querySelectorAll('.project-photos-container').forEach(c => {
                        try {
                            const max = c.scrollWidth - c.clientWidth;
                            if (max > 0) {
                                // bump by 1px then return to 0 (instant) to force layout without visible animation
                                c.scrollLeft = Math.min(1, max);
                                c.scrollLeft = 0;
                            }
                        } catch (e) {}
                    });
                }, 1200);
                setTimeout(adjustPhotoContainers, 1800);
                setTimeout(adjustPhotoContainers, 2500);
            }

            // Expose for debugging if needed
            window.__adjustPhotoContainers = adjustPhotoContainers;

            // Show/hide sliding behavior only when container actually overflows
            function manageGalleryMode() {
                // Force slider mode for all galleries: nowrap, horizontal scroll and show nav buttons
                document.querySelectorAll('.project-photos-container').forEach(container => {
                    try {
                        const nav = container.closest('.mb-16')?.querySelector('.flex.justify-center');
                        container.style.flexWrap = 'nowrap';
                        container.style.overflowX = 'auto';
                        container.style.justifyContent = 'flex-start';
                        // Check overflow after layout
                        requestAnimationFrame(() => {
                            if (nav) {
                                if (container.scrollWidth <= container.clientWidth) {
                                    nav.style.display = 'none';
                                } else {
                                    nav.style.display = 'flex';
                                }
                            }
                            // Remove duplicate photos based on src
                            const photos = container.querySelectorAll('.project-photo img, .project-photo video');
                            const seen = new Set();
                            photos.forEach(media => {
                                const src = media.src || media.currentSrc;
                                if (seen.has(src)) {
                                    media.closest('.project-photo').remove();
                                } else {
                                    seen.add(src);
                                }
                            });
                        });
                    } catch (e) { /* ignore */ }
                });
            }

            // Run manageGalleryMode alongside adjustPhotoContainers at lifecycle points
            window.addEventListener('load', manageGalleryMode);
            window.addEventListener('resize', function() { adjustPhotoContainers(); manageGalleryMode(); });
        })();

        // Mobile menu toggle functionality (match index.php behavior)
        (function() {
            try {
                const mobileMenuButton = document.getElementById('mobile-menu-button');
                const mobileSidebar = document.getElementById('mobile-sidebar');
                const sidebarOverlay = document.getElementById('sidebar-overlay');
                const closeSidebar = document.getElementById('close-sidebar');
                const mobileSidebarPanel = document.getElementById('mobile-sidebar-panel');

                // Defensive logs for debugging on devices where the menu doesn't show
                if (!mobileMenuButton) console.debug('[project-all] mobileMenuButton not found (id: mobile-menu-button)');
                if (!mobileSidebar) console.debug('[project-all] mobileSidebar not found (id: mobile-sidebar)');
                if (!mobileSidebarPanel) console.debug('[project-all] mobileSidebarPanel not found (id: mobile-sidebar-panel)');

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
                    // ensure button accepts pointer events
                    try { mobileMenuButton.style.pointerEvents = 'auto'; } catch(e) {}
                    const _toggleFromBtn = function(e) {
                        console.debug('[project-all] hamburger clicked', { hasSidebar: !!mobileSidebar, hasPanel: !!mobileSidebarPanel, event: e.type });
                        if (mobileSidebar && mobileSidebar.classList.contains('open')) {
                            closeSidebarFunc();
                        } else {
                            openSidebar();
                        }
                    };
                    mobileMenuButton.addEventListener('click', _toggleFromBtn);
                    mobileMenuButton.addEventListener('touchstart', _toggleFromBtn, { passive: true });
                    
                } else {
                    // If button wasn't found at script time, try attaching after DOM content loads
                    document.addEventListener('DOMContentLoaded', function() {
                        const btn = document.getElementById('mobile-menu-button');
                        if (btn) {
                            console.debug('[project-all] attached hamburger listener on DOMContentLoaded');
                            try { btn.style.pointerEvents = 'auto'; } catch(e) {}
                            btn.addEventListener('click', function() {
                                const mobileSidebarNow = document.getElementById('mobile-sidebar');
                                const mobileSidebarPanelNow = document.getElementById('mobile-sidebar-panel');
                                if (mobileSidebarNow && mobileSidebarNow.classList.contains('open')) {
                                    mobileSidebarNow.classList.remove('open');
                                    mobileSidebarPanelNow?.classList.add('translate-x-full');
                                    document.body.style.overflow = '';
                                } else if (mobileSidebarNow) {
                                    mobileSidebarNow.classList.add('open');
                                    mobileSidebarPanelNow?.classList.remove('translate-x-full');
                                    document.body.style.overflow = 'hidden';
                                }
                            });
                        } else {
                            console.debug('[project-all] hamburger still not found after DOMContentLoaded');
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
            } catch (err) {
                // Log the error to help debugging in mobile console
                console.error('[project-all] Error initializing mobile sidebar:', err);
            }
        })();
    </script>

</body>
</html></content>