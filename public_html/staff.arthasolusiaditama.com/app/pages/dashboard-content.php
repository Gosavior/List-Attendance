<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';

$today = date('Y-m-d');
$role = $_SESSION['role'];
// Role dummy dev dipetakan agar widget dashboard konsisten dengan menu sidebar
$dashboardRole = $role;
if ($dashboardRole === 'staff') {
    $dashboardRole = 'technician';
} elseif ($dashboardRole === 'admin') {
    $dashboardRole = 'administrator';
}
$isAdmin = in_array($dashboardRole, ['administrator', 'direktur']);


$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];
$profileAvatarUrl = getAvatarUrl($currentUser);

$roleLabels = [
  'administrator' => 'Administrator',
  'admin' => 'Admin',
  'direktur' => 'Direktur',
  'technician_manager' => 'Manager Teknisi',
  'sales' => 'Sales',
  'technician' => 'Teknisi',
  'staff' => 'Staff',
  'customer' => 'Customer',
  'internship' => 'Internship',
  'daily' => 'Daily',
];

function timeOnly($dt) { return $dt ? date('H:i', strtotime($dt)) : '-'; }


$hari = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$bulan = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
$dateLabel = ($hari[date('l')] ?? date('l')) . ', ' . date('d') . ' ' . ($bulan[date('F')] ?? date('F')) . ' ' . date('Y');
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Pagi' : ($hour < 15 ? 'Siang' : ($hour < 18 ? 'Sore' : 'Malam'));


$attendanceStatus = 'Belum Absen';
$statusBadge = 'background:#f1f5f9;color:#64748b;';
$statusDotColor = '#94a3b8';
try {
    $stmt = $pdo->prepare("SELECT status FROM attendances WHERE user_id = :uid AND attendance_date = :d");
    $stmt->execute([':uid' => $currentUser['id'], ':d' => $today]);
    $att = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($att) {
        $map = [
            'Hadir'=>['Tepat Waktu','background:#ecfdf5;color:#059669;','#10b981'],
            'Terlambat'=>['Terlambat','background:#fffbeb;color:#d97706;','#f59e0b'],
            'Izin'=>['Izin','background:#eff6ff;color:#2563eb;','#3b82f6'],
            'Sakit'=>['Sakit','background:#fef2f2;color:#dc2626;','#ef4444'],
            'Alpha'=>['Alpha','background:#fef2f2;color:#dc2626;','#ef4444'],
            'Cuti'=>['Cuti','background:#f5f3ff;color:#7c3aed;','#8b5cf6'],
            'Not Checked Out'=>['Belum Checkout','background:#fffbeb;color:#d97706;','#f59e0b'],
        ];
        if (isset($map[$att['status']])) {
            [$attendanceStatus, $statusBadge, $statusDotColor] = $map[$att['status']];
        }
    }
} catch (PDOException $e) {}


$onlineCount = 0; $onlineUsers = [];
if ($isAdmin) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.role, uos.last_seen 
            FROM users u 
            INNER JOIN user_online_status uos ON u.id = uos.user_id 
            WHERE uos.is_online = 1 AND u.is_active = 1 AND u.id != :uid 
            ORDER BY uos.last_seen DESC LIMIT 10
        ");
        $stmt->execute([':uid' => $currentUser['id']]);
        $onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $onlineCount = count($onlineUsers);
    } catch (PDOException $e) {}
}


$absenList = [];
try {
    $stmt = $pdo->prepare('SELECT u.id as user_id, u.full_name, u.avatar, a.status, a.check_in_time, a.check_out_time
      FROM users u LEFT JOIN attendances a ON u.id = a.user_id AND a.attendance_date = ?
      WHERE u.is_active = 1 AND u.role IN ("administrator","direktur","technician_manager","sales","technician","hse","internship","daily","staff","admin")
      AND u.id NOT IN (SELECT user_id FROM attendances WHERE attendance_date = ? AND status = "Alpha")
      ORDER BY CASE WHEN a.check_in_time IS NOT NULL THEN 0 WHEN a.status = "Izin" THEN 1 WHEN a.status = "Sakit" THEN 2 WHEN a.status = "Cuti" THEN 3 ELSE 4 END, u.full_name ASC LIMIT 4');
    $stmt->execute([$today, $today]);
    $absenList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $absenList = [];
}


$selectedDate = $isAdmin ? ($_GET['schedule_date'] ?? date('Y-m-d')) : date('Y-m-d', strtotime('+1 day'));
$grouped = [];
try {
    $sql = "SELECT s.id, s.destination, s.details, s.schedule_date, au.full_name AS admin_name, u.full_name AS participant
            FROM schedules s LEFT JOIN users au ON au.id = s.created_by
            INNER JOIN schedule_assignees sa ON sa.schedule_id = s.id
            INNER JOIN users u ON u.id = sa.user_id
            WHERE s.schedule_date = ? ORDER BY s.destination, u.full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selectedDate]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $r['id'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = ['destination'=>$r['destination'],'details'=>$r['details'],'schedule_date'=>$r['schedule_date'],'admin_name'=>$r['admin_name'],'id'=>$r['id'],'participants'=>[]];
        }
        $grouped[$key]['participants'][] = $r['participant'];
    }
} catch (PDOException $e) { $grouped = []; }


$all_history = [];
try {
    $history_result = $pdo->query("SELECT tp.permit_type, tp.created_at, t.name as tool_name, u_from.full_name as from_user, u_from.avatar as from_avatar, u_to.full_name as to_user, u_to.avatar as to_avatar, t.tool_type
        FROM tool_permits tp JOIN tools t ON tp.tool_id = t.id JOIN users u_from ON tp.from_user_id = u_from.id JOIN users u_to ON tp.to_user_id = u_to.id
        WHERE tp.status = 'approved' ORDER BY tp.created_at DESC LIMIT 10");
    $all_history = $history_result ? $history_result->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $all_history = [];
}
$company_data = array_values(array_filter($all_history, fn($i) => $i['tool_type'] === 'company'));
$project_data = array_values(array_filter($all_history, fn($i) => $i['permit_type'] === 'project'));
$personal_data = array_values(array_filter($all_history, fn($i) => $i['tool_type'] === 'personal'));


$reportsDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/uploads/reports/';
$reportsIndexFile = $reportsDir . 'index.json';
$reports = [];
if (file_exists($reportsIndexFile)) {
    $arr = json_decode(@file_get_contents($reportsIndexFile), true);
    if (is_array($arr)) {
        usort($arr, fn($a,$b) => strtotime($b['created_at']??'0') - strtotime($a['created_at']??'0'));
        $reports = array_slice($arr, 0, 4);
    }
}
?>

<style>
.dc-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e8ecf1;
    padding: 24px;
    overflow: hidden;
    position: relative;
}
.dark .dc-card {
    background: #1e293b;
    border-color: #334155;
}
.dc-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    margin-bottom: 16px;
}
.dark .dc-label { color: #64748b; }
.dc-link {
    font-size: 11px;
    color: #3b82f6;
    text-decoration: none;
    white-space: nowrap;
}
.dc-link:hover { text-decoration: underline; }
.dc-name {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dark .dc-name { color: #f1f5f9; }
.dc-sub {
    font-size: 12px;
    color: #94a3b8;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dark .dc-sub { color: #64748b; }
.dc-tiny { font-size: 11px; color: #94a3b8; }
.dark .dc-tiny { color: #64748b; }
.dc-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #e8ecf1;
}
.dark .dc-avatar { border-color: #334155; }
.dc-avatar-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.dc-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s;
    border: none;
    cursor: pointer;
    box-sizing: border-box;
}
.dc-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    min-width: 0;
}
.dc-row > * { min-width: 0; }
.dc-chip {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.dc-divider {
    border: none;
    border-top: 1px solid #f1f5f9;
    margin: 12px 0;
}
.dark .dc-divider { border-color: #1e293b; }
.dc-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 32px 16px;
    text-align: center;
}
.dc-empty svg { width: 36px; height: 36px; color: #cbd5e1; margin-bottom: 8px; }
.dark .dc-empty svg { color: #475569; }
.dc-empty p { font-size: 13px; color: #94a3b8; margin: 0; }
.dark .dc-empty p { color: #64748b; }
.dc-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.dc-schedule-item {
    padding: 14px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    margin-bottom: 10px;
}
.dark .dc-schedule-item {
    background: #0f172a;
    border-color: #1e293b;
}
.dc-schedule-item:last-child { margin-bottom: 0; }
.dc-absen-cell {
    padding: 12px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    overflow: hidden;
}
.dark .dc-absen-cell {
    background: #0f172a;
    border-color: #1e293b;
}
.dc-birthday-card {
    background: linear-gradient(135deg, #fffbeb 0%, #fff 100%);
}
.dark .dc-birthday-card {
    background: linear-gradient(135deg, #1c1917 0%, #1e293b 100%);
}
.dc-birthday-title {
    color: #92400e;
}
.dark .dc-birthday-title {
    color: #fbbf24;
}
.dc-birthday-link {
    background: #fff;
    border: 1px solid #fde68a;
}
.dark .dc-birthday-link {
    background: #0f172a;
    border-color: #854d0e;
}
.dc-birthday-link:hover {
    border-color: #f59e0b;
}
.dark .dc-birthday-link:hover {
    border-color: #fbbf24;
}
.dc-birthday-empty {
    background: #fff;
    border: 1px solid #fde68a;
    color: #92400e;
}
.dark .dc-birthday-empty {
    background: #0f172a;
    border-color: #854d0e;
    color: #fbbf24;
}
.dc-chip-dark {
    background: #f1f5f9;
    color: #64748b;
}
.dark .dc-chip-dark {
    background: #334155;
    color: #94a3b8;
}
</style>


<div style="display:grid;grid-template-columns:repeat(<?= $isAdmin ? 3 : (in_array($role, ['technician', 'hse']) ? 1 : 2) ?>,1fr);gap:20px;margin-bottom:20px;">

    
    <div class="dc-card">
        <div class="dc-label">Profil Saya</div>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">
            <img src="<?= htmlspecialchars($profileAvatarUrl) ?>" alt="" class="dc-avatar">
            <div style="min-width:0;flex:1;">
                <div class="dc-name"><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></div>
                <div class="dc-sub">@<?= htmlspecialchars($currentUser['username'] ?? '') ?></div>
                <div class="dc-chip" style="margin-top:6px;background:#eff6ff;color:#2563eb;font-size:10px;text-transform:uppercase;letter-spacing:0.04em;">
                    <?= $roleLabels[$currentUser['role'] ?? ''] ?? ucfirst($currentUser['role'] ?? '') ?>
                </div>
            </div>
        </div>
        <a href="dashboard.php?page=profile" class="dc-btn" style="background:#eff6ff;color:#2563eb;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Lihat Profil
        </a>
    </div>

    
    <?php if (!in_array($role, ['technician', 'hse'])): ?>
    <div class="dc-card" style="display:flex;flex-direction:column;">
        <div class="dc-label">Kehadiran Hari Ini</div>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:8px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="dc-dot" style="background:<?= $statusDotColor ?>;"></span>
                <span class="dc-chip" style="<?= $statusBadge ?>font-size:14px;padding:6px 16px;"><?= $attendanceStatus ?></span>
            </div>
            <div class="dc-tiny" style="margin-top:4px;"><?= $dateLabel ?></div>
        </div>
        <a href="dashboard.php?page=absence" class="dc-btn" style="background:#ecfdf5;color:#059669;margin-top:16px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Buka Absensi
        </a>
    </div>
    <?php endif; ?>

    
    <?php if ($isAdmin): ?>
    <div class="dc-card">
        <div style="display:flex;align-items:center;justify-content:between;margin-bottom:16px;">
            <div class="dc-label" style="margin-bottom:0;flex:1;">Staff Online</div>
            <span style="font-size:20px;font-weight:700;color:#059669;"><?= $onlineCount ?></span>
        </div>
        <div style="max-height:160px;overflow-y:auto;">
            <div id="staffOnlineList">
            <?php if ($onlineCount > 0): ?>
                <?php foreach ($onlineUsers as $ou):
                    $ago = $ou['last_seen'] ? time() - strtotime($ou['last_seen']) : 0;
                    $agoT = $ago < 60 ? 'Baru saja' : ($ago < 3600 ? floor($ago/60).' mnt' : floor($ago/3600).' jam');
                ?>
                <div class="dc-row" style="padding:6px 0;">
                    <span class="dc-dot" style="background:#10b981;width:6px;height:6px;"></span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:500;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" class="dark:!text-slate-200"><?= htmlspecialchars($ou['full_name']) ?></div>
                        <div class="dc-tiny"><?= $roleLabels[$ou['role']] ?? $ou['role'] ?> · <?= $agoT ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="dc-empty" style="padding:20px 0;">
                    <p>Tidak ada staff online</p>
                </div>
            <?php endif; ?>
            </div>
            <script>
            window.refreshStaffOnline = function() {
                fetch('<?= BASE_URL ?>app/action/chat-handler.php?action=online_users')
                    .then(r => r.json())
                    .then(json => {
                        if (!json.success) return;
                        const users = json.data.filter(u => u.is_online == 1 && u.id != <?= $currentUser['id'] ?>);
                        const countEl = document.querySelector('#staffOnlineList')?.closest('.dc-card')?.querySelector('span[style*="color:#059669"]');
                        if (countEl) countEl.textContent = users.length;
                        const container = document.getElementById('staffOnlineList');
                        if (!container) return;
                        const roleLabels = {administrator:'Admin',direktur:'Direktur',technician_manager:'Manager',sales:'Sales',technician:'Teknisi',hse:'HSE'};
                        if (users.length === 0) {
                            container.innerHTML = '<div class="dc-empty" style="padding:20px 0;"><p>Tidak ada staff online</p></div>';
                            return;
                        }
                        container.innerHTML = users.slice(0, 10).map(u => {
                            return `<div class="dc-row" style="padding:6px 0;"><span class="dc-dot" style="background:#10b981;width:6px;height:6px;"></span><div style="flex:1;min-width:0;"><div style="font-size:13px;font-weight:500;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" class="dark:!text-slate-200">${u.full_name}</div><div class="dc-tiny">${roleLabels[u.role]||u.role} · Online</div></div></div>`;
                        }).join('');
                    })
                    .catch(() => {});
            };
            </script>
        </div>
    </div>
    <?php endif; ?>
</div>


<div style="margin-bottom:20px;">
    <div class="dc-card dc-birthday-card" style="border-left:4px solid #f59e0b;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <span style="font-size:24px;"><i class="fas fa-cake-candles"></i></span>
            <div>
                <div class="dc-birthday-title" style="font-size:14px;font-weight:700;">Ulang Tahun Hari Ini</div>
                <div class="dc-tiny"><?= htmlspecialchars($todayBirthdayLabel ?? date('d F Y')) ?></div>
            </div>
        </div>
        <?php if (!empty($birthdaysToday)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <?php foreach ($birthdaysToday as $bUser): ?>
            <a href="dashboard.php?page=profile&user_id=<?= (int)$bUser['id'] ?>"
               class="dc-birthday-link"
               style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-radius:10px;text-decoration:none;transition:all 0.2s;"
               onmouseover="this.style.boxShadow='0 4px 12px rgba(245,158,11,0.15)';"
               onmouseout="this.style.boxShadow='none';">
                <div style="width:36px;height:36px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;font-size:16px;"><i class="fas fa-gift"></i></div>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($bUser['full_name'] ?? '-') ?></div>
                    <div style="font-size:11px;color:#94a3b8;">@<?= htmlspecialchars($bUser['username'] ?? '-') ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="dc-birthday-empty" style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:10px;">
            <span style="font-size:16px;"><i class="fas fa-face-smile"></i></span>
            <span style="font-size:13px;">Tidak ada karyawan yang berulang tahun hari ini.</span>
        </div>
        <?php endif; ?>
    </div>
</div>


<div style="display:grid;grid-template-columns:3fr 2fr;gap:20px;">

    
    <div class="dc-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
            <div class="dc-label" style="margin-bottom:0;">Jadwal Kerja</div>
            <?php if ($isAdmin || $role === 'technician_manager'): ?>
            <a href="dashboard.php?page=schedules" class="dc-link">Kelola →</a>
            <?php endif; ?>
        </div>
        <div class="dc-tiny" style="margin-bottom:16px;">
            <?php if ($isAdmin): ?>
                Tanggal: <strong style="color:#475569;" class="dark:!text-slate-300"><?= date('d F Y', strtotime($selectedDate)) ?></strong>
            <?php else: ?>
                Hari Esok: <strong style="color:#475569;" class="dark:!text-slate-300"><?= date('d F Y', strtotime('+1 day')) ?></strong>
            <?php endif; ?>
        </div>

        <?php if ($isAdmin): ?>
        <form method="GET" style="margin-bottom:16px;">
            <input type="hidden" name="page" value="dashboard">
            <input type="date" name="schedule_date" value="<?= htmlspecialchars($selectedDate) ?>"
                   style="border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:13px;background:#fff;color:#334155;width:auto;"
                   class="dark:!bg-slate-700 dark:!border-slate-600 dark:!text-white"
                   onchange="this.form.submit()">
        </form>
        <?php endif; ?>

        <div style="max-height:380px;overflow-y:auto;">
            <?php if (empty($grouped)): ?>
                <div class="dc-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p>Tidak ada jadwal pada tanggal ini</p>
                </div>
            <?php else: ?>
                <?php foreach ($grouped as $sc): ?>
                <div class="dc-schedule-item">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px;">
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:14px;font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" class="dark:!text-white"><?= htmlspecialchars($sc['destination']) ?></div>
                            <div class="dc-tiny" style="margin-top:2px;">Oleh: <?= htmlspecialchars($sc['admin_name'] ?? 'Admin') ?></div>
                        </div>
                        <div class="dc-chip dc-chip-dark" style="flex-shrink:0;"><?= date('d/m/Y', strtotime($sc['schedule_date'])) ?></div>
                    </div>
                    <?php if (!empty($sc['participants'])): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px;">
                        <?php foreach ($sc['participants'] as $p): ?>
                        <span class="dc-chip dc-chip-dark" style="border:1px solid #e8ecf1;"><?= htmlspecialchars($p) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($sc['details'])): ?>
                    <div style="font-size:12px;color:#64748b;line-height:1.5;word-break:break-word;" class="dark:!text-slate-400"><?= nl2br(htmlspecialchars($sc['details'])) ?></div>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                    <hr class="dc-divider">
                    <div style="display:flex;gap:8px;">
                        <a href="dashboard.php?page=schedules&id=<?= (int)$sc['id'] ?>" class="dc-chip" style="background:#fffbeb;color:#b45309;text-decoration:none;cursor:pointer;">Edit</a>
                        <button class="dc-chip" style="background:#fef2f2;color:#dc2626;border:none;cursor:pointer;" data-delete-id="<?= (int)$sc['id'] ?>">Hapus</button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    
    <div style="display:flex;flex-direction:column;gap:20px;">

        
        <?php if (!in_array($role, ['technician', 'hse'])): ?>
        <div class="dc-card" style="cursor:pointer;" onclick="window.location.href='dashboard.php?page=absen-list'">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div class="dc-label" style="margin-bottom:0;">Absensi Hari Ini</div>
                <span class="dc-link">Lihat Semua →</span>
            </div>
            <?php if (empty($absenList)): ?>
                <div class="dc-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p>Belum ada absensi hari ini</p>
                </div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <?php foreach ($absenList as $ab):
                    $st = trim($ab['status'] ?? '');
                    $isLeave = in_array($st, ['Izin','Sakit','Cuti','Alpha']);
                    $lc = ['Izin'=>'background:#eff6ff;color:#2563eb;','Sakit'=>'background:#fef2f2;color:#dc2626;','Cuti'=>'background:#f5f3ff;color:#7c3aed;','Alpha'=>'background:#fef2f2;color:#dc2626;'];
                ?>
                    <div class="dc-absen-cell">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <img src="<?= htmlspecialchars(getAvatarUrl($ab)) ?>" alt="" class="dc-avatar-sm">
                            <div style="min-width:0;flex:1;">
                                <div style="font-size:12px;font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" class="dark:!text-slate-200"><?= htmlspecialchars(mb_strimwidth($ab['full_name'], 0, 16, '…')) ?></div>
                            </div>
                        </div>
                        <?php if ($isLeave): ?>
                            <span class="dc-chip" style="<?= $lc[$st] ?? 'background:#f1f5f9;color:#64748b;' ?>"><?= htmlspecialchars($st) ?></span>
                        <?php else: ?>
                            <div style="display:flex;gap:16px;">
                                <div>
                                    <div class="dc-tiny">Masuk</div>
                                    <div style="font-size:13px;font-weight:700;color:#1e293b;" class="dark:!text-slate-200"><?= htmlspecialchars(timeOnly($ab['check_in_time'])) ?></div>
                                </div>
                                <div>
                                    <div class="dc-tiny">Pulang</div>
                                    <div style="font-size:13px;font-weight:700;color:#1e293b;" class="dark:!text-slate-200"><?= htmlspecialchars(timeOnly($ab['check_out_time'])) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        
        <div class="dc-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div class="dc-label" style="margin-bottom:0;">Riwayat Tools</div>
                <a href="dashboard.php?page=tool-history" class="dc-link">Lihat Semua →</a>
            </div>

            <?php
            $sections = [
                ['Company', $company_data, '#6366f1'],
                ['Project', $project_data, '#8b5cf6'],
            ];
            if ($role !== 'administrator') {
                $sections[] = ['Personal', $personal_data, '#10b981'];
            }
            foreach ($sections as [$label, $data, $dotColor]):
            ?>
            <div style="margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                    <span class="dc-dot" style="background:<?= $dotColor ?>;width:6px;height:6px;"></span>
                    <span style="font-size:12px;font-weight:600;color:#334155;" class="dark:!text-slate-300"><?= $label ?></span>
                    <span class="dc-tiny">(<?= count($data) ?>)</span>
                </div>
                <?php if (!empty($data)): ?>
                    <?php foreach (array_slice($data, 0, 2) as $item): ?>
                    <div class="dc-row" style="padding:4px 0 4px 14px;">
                        <img src="<?= htmlspecialchars(getAvatarUrl(['avatar'=>$item['to_avatar']??null,'gender'=>'male','id'=>0])) ?>" alt="" class="dc-avatar-sm" style="width:22px;height:22px;">
                        <div style="flex:1;min-width:0;font-size:12px;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" class="dark:!text-slate-300"><?= htmlspecialchars($item['to_user']) ?></div>
                        <div class="dc-tiny" style="flex-shrink:0;"><?= date('d M', strtotime($item['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding:2px 0 2px 14px;" class="dc-tiny">Tidak ada data</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        
        <div class="dc-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div class="dc-label" style="margin-bottom:0;">Report Terbaru</div>
                <a href="dashboard.php?page=report" class="dc-link">Lihat Semua →</a>
            </div>
            <?php if (!empty($reports)): ?>
                <?php foreach ($reports as $rep):
                    $cId = $rep['creator_id'] ?? 0;
                    $cName = $rep['creator_name'] ?? 'Unknown';
                    $rNum = $rep['report_number'] ?? $rep['report_number_compact'] ?? 'N/A';
                    $rAvatar = 'public/assets/images/avatar-default.png';
                    if ($cId > 0) { try { $u=$pdo->prepare("SELECT avatar FROM users WHERE id=?"); $u->execute([$cId]); $ud=$u->fetch(PDO::FETCH_ASSOC); if($ud&&!empty($ud['avatar'])) $rAvatar=$ud['avatar']; } catch(Exception $e){} }
                ?>
                <div class="dc-row" style="padding:6px 0;">
                    <img src="<?= htmlspecialchars(getAvatarUrl(['avatar'=>$rAvatar,'id'=>$cId])) ?>" alt="" class="dc-avatar-sm">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:12px;font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" class="dark:!text-slate-200"><?= htmlspecialchars($rNum) ?></div>
                        <div class="dc-tiny" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($cName) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="dc-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p>Belum ada report</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>


<style>
@media (max-width: 1024px) {
    div[style*="grid-template-columns:3fr 2fr"],
    div[style*="grid-template-columns:repeat(3,1fr)"] {
        grid-template-columns: 1fr 1fr !important;
    }
}
@media (max-width: 768px) {
    div[style*="grid-template-columns:3fr 2fr"],
    div[style*="grid-template-columns:repeat(3,1fr)"],
    div[style*="grid-template-columns:repeat(2,1fr)"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php if ($isAdmin): ?>
<script>
document.addEventListener('click', function(e){
    const btn = e.target.closest('[data-delete-id]');
    if (!btn) return;
    if (!confirm('Hapus jadwal ini?')) return;
    const id = btn.getAttribute('data-delete-id');
    const fd = new URLSearchParams();
    fd.set('id', id);
    fd.set('csrf', <?= json_encode($_SESSION['csrf'] ?? '') ?>);
    fetch('./app/action/delete_schedule.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString()})
        .then(r=>r.json()).then(j=>{ if(j.success) location.reload(); else showToast(j.message||'Gagal', 'error'); });
});
</script>
<?php endif; ?>
