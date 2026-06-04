<?php
require_once __DIR__ . '/../helpers/url-helper.php';

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    redirect_to('/login.php');
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php'; 


if (!isset($user) || empty($user)) {
    $stmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
$avatar_url = getAvatarUrl($user);

// Role dummy dev (staff/admin) dipetakan ke role produksi agar menu tampil
$menuRole = $user['role'] ?? '';
if ($menuRole === 'staff') {
    $menuRole = 'technician';
} elseif ($menuRole === 'admin') {
    $menuRole = 'administrator';
}

$userDivisions = [];
try {
    $divStmt = $pdo->prepare("SELECT LOWER(d.name) FROM divisions d JOIN user_divisions ud ON d.id = ud.division_id WHERE ud.user_id = ?");
    $divStmt->execute([$_SESSION['user_id']]);
    $userDivisions = $divStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $userDivisions = [];
}

$pendingMaterialBadge = 0;
if (in_array(strtolower(trim($user['role'] ?? '')), ['administrator', 'direktur'])) {
    try {
        $stmtMat = $pdo->prepare("SELECT COUNT(*) FROM material_requests WHERE status = 'sales_approved'");
        $stmtMat->execute();
        $pendingMaterialBadge = (int)$stmtMat->fetchColumn();
    } catch (Exception $e) {}
}


$pendingReimburseBadge = 0;
if (in_array(strtolower(trim($user['role'] ?? '')), ['administrator'])) {
    try {
        $stmtReimb = $pdo->prepare("SELECT COUNT(*) FROM fuel_reimbursements WHERE status = 'pending'");
        $stmtReimb->execute();
        $pendingReimburseBadge = (int)$stmtReimb->fetchColumn();
    } catch (Exception $e) {}
}


$pendingCutiBadge = 0;
$cutiRole = strtolower(trim($user['role'] ?? ''));
try {
    if ($cutiRole === 'technician_manager') {
        $stmtCuti = $pdo->prepare("SELECT COUNT(*) FROM cuti_requests WHERE status = 'pending'");
        $stmtCuti->execute();
        $pendingCutiBadge = (int)$stmtCuti->fetchColumn();
    } elseif ($cutiRole === 'administrator') {
        $stmtCuti = $pdo->prepare("SELECT COUNT(*) FROM cuti_requests WHERE status IN ('pending','manager_approved')");
        $stmtCuti->execute();
        $pendingCutiBadge = (int)$stmtCuti->fetchColumn();
    } elseif ($cutiRole === 'direktur') {
        $stmtCuti = $pdo->prepare("SELECT COUNT(*) FROM cuti_requests WHERE status = 'admin_approved'");
        $stmtCuti->execute();
        $pendingCutiBadge = (int)$stmtCuti->fetchColumn();
    }
} catch (Exception $e) {}

$menus = [
    
    '_section_main' => ['section' => 'MENU UTAMA'],
    'dashboard' => [
        'icon' => 'fas fa-home',
        'text' => 'Dashboard',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'sales', 'technician', 'hse', 'internship', 'daily', 'driver'],
        'link' => 'dashboard.php?page=dashboard',
        'dropdown' => []
    ],
    'inbox' => [
        'icon' => 'fas fa-inbox',
        'text' => 'Inbox',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'sales', 'technician', 'hse', 'driver'],
        'link' => 'dashboard.php?page=inbox',
        'dropdown' => []
    ],
    'project-updates' => [
        'icon' => 'fas fa-clipboard-list',
        'text' => 'Update Project',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'sales', 'technician', 'hse', 'internship', 'daily'],
        'link' => 'dashboard.php?page=project-updates',
        'dropdown' => []
    ],

    
    '_section_attendance' => ['section' => 'KEHADIRAN'],
    'absence' => [
        'icon' => 'fas fa-user-check',
        'text' => 'Absen',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'sales', 'technician', 'hse', 'internship', 'daily', 'driver'],
        'link' => 'dashboard.php?page=absence',
        'dropdown' => []
    ],
    'absen-list' => [
        'icon' => 'fas fa-list',
        'text' => 'List Absen',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'sales', 'technician', 'hse', 'internship', 'daily'],
        'link' => 'dashboard.php?page=absen-list',
        'dropdown' => []
    ],
    
    'leave' => [
        'icon' => 'fas fa-calendar-minus',
        'text' => 'Pengajuan Cuti',
        'roles' => ['administrator', 'technician_manager', 'sales', 'technician', 'hse', 'direktur', 'driver'],
        'link' => 'dashboard.php?page=leave',
        'badge' => $pendingCutiBadge,
        'dropdown' => []
    ],
    'schedules' => [
        'icon' => 'fas fa-calendar-alt',
        'text' => 'Jadwal',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'technician', 'hse', 'internship'],
        'link' => 'dashboard.php?page=schedules',
        'dropdown' => []
    ],

    
    '_section_work' => ['section' => 'PEKERJAAN'],
    'tools' => [
        'icon' => 'fas fa-tools',
        'text' => 'Tools',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'technician', 'hse', 'sales', 'internship', 'daily'],
        'link' => 'dashboard.php?page=tools',
        'dropdown' => [
            'tool-history' => [
                'icon' => 'fas fa-history',
                'text' => 'History',
                'link' => 'dashboard.php?page=tool-history',
                'roles' => ['administrator', 'direktur', 'technician_manager', 'technician', 'hse', 'sales', 'internship', 'daily']
            ]
        ]
    ],
    'request-material' => [
        'icon' => 'fas fa-box',
        'text' => 'Request Material',
        'roles' => ['administrator', 'direktur', 'technician', 'hse', 'driver'],
        'link' => 'dashboard.php?page=request-material',
        'badge' => $pendingMaterialBadge,
        'dropdown' => []
    ],
    'fuel-reimbursement' => [
        'icon' => 'fas fa-receipt',
        'text' => 'Reimbursement',
        'roles' => ['administrator', 'direktur', 'technician', 'hse', 'daily', 'internship', 'driver', 'sales'],
        'link' => 'dashboard.php?page=fuel-reimbursement',
        'badge' => $pendingReimburseBadge,
        'dropdown' => []
    ],
    'report' => [
        'icon' => 'fas fa-chart-line',
        'text' => 'Laporan',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'sales', 'technician', 'hse'],
        'link' => 'dashboard.php?page=report',
        'dropdown' => []
    ],

    
    '_section_task' => ['section' => 'TASK MANAGEMENT'],
    'task-board' => [
        'icon' => 'fas fa-tasks',
        'text' => 'Task Board',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'sales', 'technician', 'hse', 'internship', 'daily', 'driver'],
        'link' => 'dashboard.php?page=task-board',
        'dropdown' => []
    ],

    '_section_other' => ['section' => 'LAINNYA'],
    'bug-report' => [
        'icon' => 'fas fa-bug',
        'text' => 'Bug Report',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'sales', 'technician', 'hse', 'internship', 'daily'],
        'link' => 'dashboard.php?page=bug-report',
        'dropdown' => []
    ],

    
    '_section_admin' => ['section' => 'ADMINISTRASI', 'roles' => ['administrator', 'direktur']],
    'payroll' => [
        'icon' => 'fas fa-wallet',
        'text' => 'Payroll',
        'roles' => ['administrator', 'direktur'],
        'link' => 'dashboard.php?page=payroll',
        'dropdown' => []
    ],
    'account' => [
        'icon' => 'fas fa-users-cog',
        'text' => 'Kelola Akun',
        'roles' => ['administrator', 'direktur'],
        'link' => 'dashboard.php?page=account',
        'dropdown' => []
    ],
    'divisions' => [
        'icon' => 'fas fa-sitemap',
        'text' => 'Divisi',
        'roles' => ['administrator', 'direktur', 'technician_manager', 'sales', 'technician', 'hse', 'driver'],
        'link' => 'dashboard.php?page=divisions',
        'dropdown' => []
    ],
    'holidays' => [
        'icon' => 'fas fa-umbrella-beach',
        'text' => 'Hari Libur',
        'roles' => ['administrator', 'direktur'],
        'link' => 'dashboard.php?page=holidays',
        'dropdown' => []
    ],
    'gps' => [
        'icon' => 'fas fa-map-marker-alt',
        'text' => 'GPS Management',
        'roles' => ['administrator', 'direktur'],
        'link' => 'dashboard.php?page=gps',
        'dropdown' => []
    ],
    'audit-log' => [
        'icon' => 'fas fa-shield-alt',
        'text' => 'Audit Log',
        'roles' => ['administrator', 'direktur'],
        'link' => 'dashboard.php?page=audit-log',
        'dropdown' => []
    ],
];

$currentPage = $_GET['page'] ?? 'dashboard';

function isDropdownActive($dropdown, $currentPage, $userRole) {
    foreach ($dropdown as $key => $item) {
        if (in_array($userRole, $item['roles']) && $key === $currentPage) {
            return true;
        }
    }
    return false;
}
?>

<link href="src/output.css?v=<?= @filemtime(__DIR__ . '/../../src/output.css') ?: time() ?>" rel="stylesheet">

<style>
nav.flex-1::-webkit-scrollbar {
    width: 6px;
}
nav.flex-1::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}
nav.flex-1::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.5);
    border-radius: 10px;
    transition: background 0.3s ease;
}
nav.flex-1::-webkit-scrollbar-thumb:hover {
    background: rgba(59, 130, 246, 0.8);
}
.dark nav.flex-1::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}
.dark nav.flex-1::-webkit-scrollbar-thumb {
    background: rgba(96, 165, 250, 0.5);
}
.dark nav.flex-1::-webkit-scrollbar-thumb:hover {
    background: rgba(96, 165, 250, 0.8);
}

.sidebar-dropdown {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    padding-left: 2rem;
    transition:
        max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1),
        opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: max-height, opacity;
}
.sidebar-dropdown.open {
    max-height: 500px;
    opacity: 1;
}
.chevron-animated {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
    cursor: pointer;
}
.chevron-animated.rotate {
    transform: rotate(180deg);
}
.menu-row {
    position: relative;
    display: flex;
    align-items: stretch;
}
.menu-link {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0.6rem 0.75rem;
    border-radius: 0.5rem;
    text-decoration: none;
    transition: background 0.2s;
    min-height: 40px;
    gap: 0;
}
.menu-link .menu-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.875rem;
    margin-left: 0.75rem;
}
.menu-link:hover {
    background: rgba(59,130,246,0.18);
}
.menu-chevron {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    background: none;
    border: none;
    height: 24px;
    width: 24px;
    padding: 0;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

@media (max-width: 767px), (max-height: 500px) {
    #mainSidebar {
        width: 80vw;
        max-width: 320px;
        transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
        transform: translateX(-100%);
        display: flex !important;
    }
    #mainSidebar.show {
        transform: translateX(0);
    }
    #sidebarOverlay {
        display: block !important;
    }
}

@media (min-width: 768px) and (min-height: 501px) {
    #mainSidebar {
        display: flex !important;
        transform: translateX(0) !important;
    }
    #sidebarOverlay {
        display: none !important;
    }
}

@media (max-width: 767px), (max-height: 500px) {
    body > div.md\:ml-64 {
        margin-left: 0 !important;
    }
}

.z-60 { z-index: 60; }
.transition-opacity { transition: opacity 0.3s cubic-bezier(0.4,0,0.2,1);}
.opacity-0 { opacity: 0; }
.opacity-100 { opacity: 1;}

#mainSidebar {
    background: linear-gradient(135deg, #f8fafc 80%, #dbeafe 100%);
    color: #1f2937;
}
.dark #mainSidebar {
    background: linear-gradient(135deg, #0f172a 80%, #1e3a8a 100%);
    color: #ffffff;
}
</style>

<div id="sidebarOverlay"
     class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 md:hidden"></div>

<aside id="mainSidebar"
    class="fixed top-0 left-0 h-screen w-64 shadow-lg
    z-60 hidden md:flex flex-col transition-transform duration-300 ease-in-out">
    <div class="flex items-center gap-3 px-4 py-5 border-b border-white/10 dark:border-white/10 border-slate-200">
        <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center">
            <img src="./public/assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain">
        </div>
        <h1 class="text-sm font-semibold text-slate-800 dark:text-white">PT. Artha Solusi Aditama</h1>
    </div>
    
    <div class="flex flex-col items-center text-center p-4 border-b border-white/10 dark:border-white/10 border-slate-200">
        <div class="w-16 h-16 rounded-full mb-2 border-2 border-white/20 bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
            <img src="<?= htmlspecialchars($avatar_url, ENT_QUOTES) ?>" alt="Avatar" class="w-16 h-16 object-cover rounded-full">
        </div>
        <span class="font-medium text-slate-800 dark:text-white"><?= htmlspecialchars($user['full_name']) ?></span>
        <span class="text-xs opacity-80 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($user['role']) ?></span>
    </div>
    
    <nav class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-0.5 text-slate-800 dark:text-white scroll-smooth">
        <?php foreach ($menus as $menuKey => $menu): ?>
            <?php
            
            if (isset($menu['section'])):
                $sectionRoles = $menu['roles'] ?? null;
                if ($sectionRoles && !in_array($menuRole, $sectionRoles)) continue;
            ?>
                <div class="pt-4 pb-1.5 px-2 first:pt-1">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500"><?= $menu['section'] ?></span>
                </div>
            <?php continue; endif; ?>

            <?php 
            $hasRoleAccess = in_array($menuRole, $menu['roles']);
            $hasDivisionAccess = isset($menu['divisions']) && !empty(array_intersect($userDivisions, $menu['divisions']));
            if ($hasRoleAccess || $hasDivisionAccess): 
            ?>
                <div>
                    <div class="menu-row w-full">
                        <?php 
                        $isParentActive = ($currentPage === $menuKey);
                        if (!$isParentActive && isset($menu['dropdown'])) {
                            $isParentActive = isDropdownActive($menu['dropdown'], $currentPage, $menuRole);
                        }
                        ?>
                        <a href="<?= $menu['link'] ?>"
                           class="menu-link ajax-link<?= $isParentActive ? ' bg-blue-700 text-white' : '' ?>"
                           style="position: relative;">
                            <i class="<?= $menu['icon'] ?> w-5 text-blue-400 flex-shrink-0"></i>
                            <span class="menu-text"><?= $menu['text'] ?></span>
                            <?php if (!empty($menu['badge']) && $menu['badge'] > 0): ?>
                            <span class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold shadow-sm animate-pulse flex-shrink-0"><?= $menu['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <?php if (isset($menu['dropdown']) && !empty($menu['dropdown'])): ?>
                            <?php 
                            $visibleDropdown = array_filter($menu['dropdown'], function($item) use ($user) {
                                return in_array($menuRole, $item['roles']);
                            });
                            ?>
                            
                            <?php if (!empty($visibleDropdown)): ?>
                                <button type="button"
                                    class="menu-chevron dropdown-chevron"
                                    data-target="dropdown-<?= $menuKey ?>"
                                    tabindex="0"
                                    aria-label="<?= $menu['text'] ?> Dropdown"
                                >
                                    <i class="fas fa-chevron-down chevron-animated text-xs<?= isDropdownActive($menu['dropdown'], $currentPage, $menuRole) ? ' rotate' : '' ?>"></i>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($menu['dropdown']) && !empty($visibleDropdown)): ?>
                        <div id="dropdown-<?= $menuKey ?>"
                            class="sidebar-dropdown<?= isDropdownActive($menu['dropdown'], $currentPage, $menuRole) ? ' open' : '' ?>">
                            <?php foreach ($menu['dropdown'] as $subKey => $subMenu): ?>
                                <?php if (in_array($menuRole, $subMenu['roles'])): ?>
                                    <a href="<?= $subMenu['link'] ?>"
                                        class="ajax-link flex items-center gap-3 p-2 rounded-lg hover:bg-blue-500/30 transition <?= $currentPage === $subKey ? 'bg-blue-700 text-white' : '' ?>">
                                        <i class="<?= $subMenu['icon'] ?> w-4 text-blue-400"></i>
                                        <span><?= $subMenu['text'] ?></span>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="border-t border-slate-200 dark:border-slate-700 px-3 py-2 flex items-center justify-around bg-slate-50/80 dark:bg-slate-800/80">
        <a href="dashboard.php?page=profile" class="ajax-link flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition <?= $currentPage === 'profile' ? 'text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-slate-400' ?>">
            <i class="fas fa-user text-sm"></i>
            <span class="text-[10px] font-medium">Profil</span>
        </a>
        <a href="dashboard.php?page=settings" class="ajax-link flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition <?= $currentPage === 'settings' ? 'text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-slate-400' ?>">
            <i class="fas fa-cog text-sm"></i>
            <span class="text-[10px] font-medium">Setelan</span>
        </a>
        <a href="logout.php" class="flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition text-slate-500 dark:text-slate-400 hover:text-red-500">
            <i class="fas fa-sign-out-alt text-sm"></i>
            <span class="text-[10px] font-medium">Keluar</span>
        </a>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chevronButtons = document.querySelectorAll('.dropdown-chevron');
    
    chevronButtons.forEach(chevronBtn => {
        const targetId = chevronBtn.getAttribute('data-target');
        const dropdown = document.getElementById(targetId);
        const icon = chevronBtn.querySelector('.chevron-animated');
        
        if (!dropdown || !icon) return;
        
        let open = dropdown.classList.contains('open');
        
        chevronBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            open = !open;
            
            if (open) {
                dropdown.classList.add('open');
                icon.classList.add('rotate');
                chevronBtn.setAttribute('aria-expanded', 'true');
            } else {
                dropdown.classList.remove('open');
                icon.classList.remove('rotate');
                chevronBtn.setAttribute('aria-expanded', 'false');
            }
        });
    });

    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const hamburger = document.getElementById('sidebarToggle');
    let sidebarOpen = false;
    
    function isMobile() {
        return window.innerWidth < 768;
    }

    function openSidebar() {
        if (!isMobile()) return;
        sidebar.classList.remove('hidden');
        void sidebar.offsetWidth;
        sidebar.classList.add('show', 'flex');
        overlay.classList.remove('pointer-events-none');
        overlay.classList.add('opacity-100');
        overlay.classList.remove('opacity-0');
        document.body.style.overflow = 'hidden';
        sidebarOpen = true;
    }
    
    function closeSidebar() {
        if (!isMobile()) return;
        sidebar.classList.remove('show');
        overlay.classList.remove('opacity-100');
        overlay.classList.add('opacity-0');
        sidebarOpen = false;
        setTimeout(() => {
            sidebar.classList.add('hidden');
            sidebar.classList.remove('flex');
            overlay.classList.add('pointer-events-none');
            document.body.style.overflow = '';
        }, 300);
    }
    
    hamburger?.addEventListener('click', openSidebar);
    overlay?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function(e) { 
        if (e.key === "Escape" && sidebarOpen) closeSidebar(); 
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            sidebar.classList.remove('hidden', 'show');
            sidebar.classList.add('flex');
            overlay.classList.add('pointer-events-none', 'opacity-0');
            overlay.classList.remove('opacity-100');
            document.body.style.overflow = '';
            sidebarOpen = false;
        } else if (!sidebarOpen) {
            sidebar.classList.add('hidden');
            sidebar.classList.remove('flex', 'show');
            overlay.classList.add('pointer-events-none', 'opacity-0');
            overlay.classList.remove('opacity-100');
            document.body.style.overflow = '';
        }
    });
});
</script>