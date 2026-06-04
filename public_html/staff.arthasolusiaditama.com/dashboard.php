<?php
error_reporting(0);
ini_set('display_errors', 0);


define('BASE_PATH', dirname(__FILE__));
define('PUBLIC_PATH', BASE_PATH . '/public');

if (isset($_GET['action']) && $_GET['action'] === 'get_notifications') {
    require_once BASE_PATH . '/app/action/get_notifications.php';
    exit;
}


if (isset($_GET['page'], $_REQUEST['action'])) {
    $api_pages = [
        'project-updates' => 'app/pages/project-updates.php',
        'task-board' => 'app/pages/task-board.php',
    ];
    $pg = $_GET['page'];
    if (isset($api_pages[$pg])) {
        require_once BASE_PATH . '/app/auth/auth.php';
        requireLogin();
        require_once BASE_PATH . '/app/config/database.php';
        require_once BASE_PATH . '/app/helpers/avatar.php';
        require_once BASE_PATH . '/' . $api_pages[$pg];
        exit;
    }
}


if (isset($_GET['page']) && $_GET['page'] === 'absen-list' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_attendance_modal'])) {
    require_once BASE_PATH . '/app/auth/auth.php';
    requireLogin();
    require_once BASE_PATH . '/app/config/database.php';
    require_once BASE_PATH . '/app/helpers/avatar.php';
    require_once BASE_PATH . '/app/dashboard/absent-list.php';
    exit;
}


if (isset($_GET['page']) && $_GET['page'] === 'payroll' && (isset($_GET['ajax_count']) || isset($_GET['ajax_detail']) || isset($_GET['export']) || isset($_POST['action']))) {
    require_once BASE_PATH . '/app/auth/auth.php';
    requireLogin();
    require_once BASE_PATH . '/app/config/database.php';
    require_once BASE_PATH . '/app/helpers/avatar.php';
    require_once BASE_PATH . '/app/pages/payroll.php';
    exit;
}


$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$isHttps = (
    (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
    (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string)$_SERVER['REQUEST_SCHEME']) === 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos((string)$_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') ||
    (!empty($_SERVER['HTTP_CF_VISITOR']) && stripos((string)$_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false)
);
$protocol = $isHttps ? 'https://' : 'http://';
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $script_path . '/');

require_once BASE_PATH . '/app/auth/auth.php';
requireLogin();

require_once BASE_PATH . '/app/config/database.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$resolvedTheme = (($user['theme'] ?? ($_SESSION['theme'] ?? 'light')) === 'dark') ? 'dark' : 'light';

require_once BASE_PATH . '/app/helpers/avatar.php';

$allowed_pages = [
    'dashboard' => 'app/pages/dashboard-content.php',  
    'inbox' => 'app/controllers/Inbox.php',
    'profile' => 'app/accounts/profile.php',
    'settings' => 'app/accounts/settings.php',
    'absence' => 'app/pages/attendance.php',
    'report' => 'app/pages/report.php',
    'tools' => 'app/pages/tools.php',
    'holidays' => 'app/pages/holiday-management.php',
    'gps' => 'app/pages/gps-management.php',
    'account' => 'app/pages/account-handler.php',
    'admin' => 'app/pages/admin-dashboard.php',
    'attendance-reset' => 'app/pages/attendance-reset.php',
    'absen-list' => 'app/dashboard/absent-list.php',
    'tool-history' => 'app/dashboard/history-tools.php',
    'check-monthly-tools' => 'app/dashboard/check_monthly_tools.php',
    'schedules' => 'app/controllers/Schedules.php',
    'request-material' => 'app/pages/request-material-v2.php',
    'delivery-order' => 'app/pages/delivery-order.php',
    'divisions' => 'app/pages/divisions.php',
    'project-updates' => 'app/pages/project-updates.php',
    'bug-report' => 'app/pages/bug-report.php',
    'payroll' => 'app/pages/payroll.php',
    'leave' => 'app/pages/leave-request.php',
    'fuel-reimbursement' => 'app/pages/fuel-reimbursement.php',
    'shifts' => 'app/pages/shifts.php',
    'audit-log' => 'app/dashboard/audit-log.php',
    'task-board' => 'app/pages/task-board.php'
];


$page = $_GET['page'] ?? 'dashboard';
if (!array_key_exists($page, $allowed_pages)) {
    $page = 'dashboard';
}


$internship_allowed_pages = [
    'dashboard', 'profile', 'settings', 'absence', 'absen-list',
    'schedules', 'bug-report', 'project-updates', 'tools', 'tool-history', 'leave', 'fuel-reimbursement'
];
if (($user['role'] ?? '') === 'internship' && !in_array($page, $internship_allowed_pages)) {
    $page = 'dashboard';
}


$daily_allowed_pages = [
    'dashboard', 'profile', 'settings', 'absence', 'absen-list',
    'bug-report', 'project-updates', 'tools', 'fuel-reimbursement'
];
if (($user['role'] ?? '') === 'daily' && !in_array($page, $daily_allowed_pages)) {
    $page = 'dashboard';
}





$page_file = BASE_PATH . '/' . $allowed_pages[$page];

if (!file_exists($page_file)) {
    $page = 'dashboard';
    $page_file = BASE_PATH . '/' . $allowed_pages[$page];
}


$avatarUrl = getAvatarUrl($user);


if (in_array($page, ['absence', 'leave', 'fuel-reimbursement'])) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
?>

<!DOCTYPE html>
<html lang="en" class="<?= $resolvedTheme === 'dark' ? 'dark' : '' ?>" data-theme="<?= $resolvedTheme ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes" />
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>" />
    <meta name="theme-mode" content="<?= htmlspecialchars($resolvedTheme) ?>" />
    <meta name="theme-color" content="<?= $resolvedTheme === 'dark' ? '#0f172a' : '#ffffff' ?>" />
    <meta name="supported-color-schemes" content="<?= $resolvedTheme === 'dark' ? 'dark' : 'light' ?>" />
    <meta name="color-scheme" content="<?= $resolvedTheme === 'dark' ? 'dark' : 'light' ?>" />
    <meta name="apple-mobile-web-app-status-bar-style" content="<?= $resolvedTheme === 'dark' ? 'black-translucent' : 'default' ?>" />
    
    
    <link rel="icon" type="image/png" href="public/assets/images/logo.png">
    
    <title>Dashboard • PT. Artha Solusi Aditama</title>
    
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    
    <link rel="stylesheet" href="src/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    
    <script src="public/assets/js/theme-manager.js"></script>
    <script src="public/assets/js/toast.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            background-color: #f8fafc;
            color: #1e293b;
        }
        
        .dark body {
            background-color: #0f172a;
            color: #e2e8f0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #3b82f6;
            color: white;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn:hover {
            background: #2563eb;
        }
        
            padding-top:  90px !important;
        }
    </style>
    
    <script>
        const BASE_URL = window.location.origin + "<?php echo htmlspecialchars(rtrim($script_path, '/')); ?>/";
        const USER_AVATAR = "<?php echo htmlspecialchars($avatarUrl); ?>";
        
        if (window.ThemeManager) {
            ThemeManager.syncWithServer('<?= htmlspecialchars($resolvedTheme) ?>');
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-white">

<?php 

require BASE_PATH . '/app/includes/sidebar.php';
?>

<div class="flex flex-col md:ml-64 min-h-screen">
    <?php require BASE_PATH . '/app/includes/header.php'; ?>
    
    <main class="flex-1 p-6 pt-[72px] bg-slate-50 dark:bg-slate-900" id="content">
        <?php
        
        
        if (file_exists($page_file)) {
            include $page_file;
        } else {
            error_log("CRITICAL: File not found - " . $page_file);
            
            
            echo '<div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 rounded">';
            echo '<div class="flex">';
            echo '<div class="flex-shrink-0">';
            echo '<svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">';
            echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>';
            echo '</svg>';
            echo '</div>';
            echo '<div class="ml-3">';
            echo '<h3 class="text-sm font-medium text-red-800 dark:text-red-200">File Tidak Ditemukan</h3>';
            echo '<div class="mt-2 text-sm text-red-700 dark:text-red-300">';
            echo '<p>File yang dicari: <code>' . htmlspecialchars($page_file) . '</code></p>';
            echo '<p class="mt-2">Pastikan file tersebut ada dan path-nya benar.</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        ?>
    </main>
    <?php require BASE_PATH . '/app/includes/footer.php'; ?>
</div>

<script>
const pageMap = <?= json_encode($allowed_pages) ?>;
const fullReloadPages = new Set(['dashboard','tools', 'account', 'absence', 'report', 'profile', 
    'check-monthly-tools', 'absen-list', 'holidays', 'gps', 'schedules', 'tool-history', 'bug-report', 'divisions', 'chat', 'audit-log']);

function getCurrentPage() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('page') || 'dashboard';
}

function assetUrl(p) {
    const baseUrl = window.BASE_URL || '';
    
    if (!p) {
        return baseUrl + '/public/assets/images/avatar-default.png';
    }
    
    try {
        let str = String(p).trim();
        
        if (/^https?:\/\//i.test(str)) {
            return str;
        }
        
        if (str.startsWith('/')) {
            return baseUrl + str;
        }
        
        if (str.includes('storage/uploads/')) {
            return baseUrl ? (baseUrl + '/serve_image.php?path=' + encodeURIComponent(str)) : ('/serve_image.php?path=' + encodeURIComponent(str));
        }
        
        if (str.includes('public/assets/images/')) {
            return baseUrl + '/' + str;
        }
        
        return baseUrl + '/public/assets/images/' + str;
    } catch(e) {
        console.error('assetUrl error:', e);
        return baseUrl + '/public/assets/images/avatar-default.png';
    }
}

function loadContent(page, pushState = true) {
    const currentPage = getCurrentPage();
    
    if (page === currentPage && !fullReloadPages.has(page)) {
        return false;
    }
    
    if (fullReloadPages.has(page)) {
        if (pushState) {
            window.location.href = `?page=${page}`;
        }
        return true;
    }
    
    const contentUrl = pageMap[page] || pageMap['dashboard'];
    if (!contentUrl) return false;
    
    const url = new URL(window.location.origin + window.location.pathname);
    url.searchParams.set('page', page);
    
    const fetchUrl = BASE_URL + contentUrl + (window.location.search ? '?' + window.location.search.substring(1) : '');
    
    fetch(fetchUrl)
        .then(res => {
            if (!res.ok) throw new Error('Failed to load content');
            return res.text();
        })
        .then(data => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const title = doc.querySelector('title')?.textContent || 'Dashboard • PT. Artha Solusi Aditama';
            
            document.getElementById('content').innerHTML = data;
            document.title = title;
            
            if (pushState) {
                window.history.pushState({ page }, title, url.toString());
            }
            
            setSidebarActive();
            
            bindAjaxLinks();
            bindModalEvents();
            executeScriptsInContent();
            
            document.dispatchEvent(new CustomEvent('pageLoaded', { 
                detail: { page: page }
            }));
        })
        .catch(error => {
            console.error('Error loading content:', error);
            if (pushState) {
                window.location.href = `?page=${page}`;
            }
        });
    
    return true;
}

function setSidebarActive() {
    const currentPage = getCurrentPage();
    document.querySelectorAll('.ajax-link').forEach(link => {
        const url = new URL(link.href, window.location.origin);
        const linkPage = url.searchParams.get('page') || 'dashboard';
        
        link.classList.remove('bg-blue-700', 'text-white');
        
        if (linkPage === currentPage) {
            link.classList.add('bg-blue-700', 'text-white');
        }
    });
}

function bindAjaxLinks() {
    document.querySelectorAll('.ajax-link').forEach(link => {
        link.removeEventListener('click', handleAjaxLinkClick);
        link.addEventListener('click', handleAjaxLinkClick);
    });
}

function handleAjaxLinkClick(e) {
    const url = new URL(this.href, window.location.origin);
    const clickedPage = url.searchParams.get('page') || 'dashboard';
    
    e.preventDefault();
    loadContent(clickedPage, true);
}

function executeScriptsInContent() {
    const content = document.getElementById('content');
    if (!content) return;
    
    const styles = content.querySelectorAll('style');
    styles.forEach(style => {
        const newStyle = document.createElement('style');
        newStyle.textContent = style.textContent;
        document.head.appendChild(newStyle);
    });
    
    const scripts = content.querySelectorAll('script');
    scripts.forEach(script => {
        if (script.src) {
            if (!document.querySelector(`script[src="${script.src}"]`)) {
                const newScript = document.createElement('script');
                newScript.src = script.src;
                newScript.async = false;
                document.head.appendChild(newScript);
            }
        } else {
            try {
                Function(script.textContent)();
            } catch (e) {
                console.warn('Script execution warning:', e);
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setSidebarActive();
    bindAjaxLinks();
});
</script>


<script src="https://cdn.socket.io/4.7.4/socket.io.min.js"></script>
<script>
(function() {
    try {
        if (typeof io === 'undefined') {
            console.warn('[Presence] Socket.IO library not loaded, skipping');
            return;
        }

        const GLOBAL_SOCKET_URL = window.location.origin;
        const GLOBAL_USER_ID = <?= json_encode((int)$user['id']) ?>;

        window._globalSocket = io(GLOBAL_SOCKET_URL, {
            auth: {
                token: 'presence',
                userId: GLOBAL_USER_ID
            },
            transports: ['polling'],
            reconnection: true,
            reconnectionDelay: 5000,
            reconnectionDelayMax: 30000,
            reconnectionAttempts: 10,
            timeout: 10000
        });

        window._globalSocket.on('connect', () => {
        });

        window._globalSocket.on('connect_error', (err) => {
        });

        window._globalSocket.io.on('reconnect_failed', () => {
        });

        window._globalSocket.on('user_online', (data) => {
            if (typeof window.refreshStaffOnline === 'function') {
                window.refreshStaffOnline();
            }
        });

        window._globalSocket.on('notification_update', (data) => {
            if (typeof fetchAndRenderNotifications === 'function') {
                fetchAndRenderNotifications();
            }
        });
    } catch (e) {
    }
})();
</script>


<script>
(function() {
    const GPS_INTERVAL = 5 * 60 * 1000; // 5 minutes
    const WORK_START = 7;  // 07:00
    const WORK_END = 19;   // 19:00
    let gpsTimerId = null;
    let lastSentAt = 0;

    function isWorkHours() {
        const h = new Date().getHours();
        return h >= WORK_START && h < WORK_END;
    }

    function sendGPS() {
        if (!isWorkHours()) return;
        if (!navigator.geolocation) return;
        
        if (Date.now() - lastSentAt < 4 * 60 * 1000) return;

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const data = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy
                };
                
                fetch('app/action/gps-ping.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) lastSentAt = Date.now();
                })
                .catch(() => {});
            },
            function(err) {
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 120000 }
        );
    }

    if (navigator.geolocation && isWorkHours()) {
        setTimeout(sendGPS, 30000);
        gpsTimerId = setInterval(sendGPS, GPS_INTERVAL);
    }

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && isWorkHours()) {
            sendGPS();
        }
    });
})();
</script>

</body>
</html>