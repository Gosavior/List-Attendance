<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
?>
<style>
    color: #0f172a !important;
    background-color: #ffffff !important;
    border-color: #d1d5db !important;
}
    color: #0f172a !important;
    background-color: #f1f5f9 !important;
}
    color: #374151 !important;
}

html[data-theme="dark"] #zoneModal > div > div:first-child {
    background: #1e293b !important;
    color: #e2e8f0 !important;
}
html[data-theme="dark"] #zoneModal form {
    background: #1e293b !important;
}
html[data-theme="dark"] #zoneModal input[type="text"] {
    background: #0f172a !important;
    color: #f8fafc !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] #zoneModal label {
    color: #cbd5e1 !important;
}
html[data-theme="dark"] #zoneModal p {
    color: #94a3b8 !important;
}
html[data-theme="dark"] #zoneModal button[type="button"]:not([onclick*="openAddZone"]) {
    background: #334155 !important;
    color: #e2e8f0 !important;
    border-color: #475569 !important;
}
html[data-theme="dark"] .gps-panel > div[style*="background:#fff"],
html[data-theme="dark"] .gps-panel > div[style*="background-color:#fff"] {
    background: #1e293b !important;
    color: #e2e8f0 !important;
}
html[data-theme="dark"] .gps-panel h2 {
    color: #f1f5f9 !important;
}
html[data-theme="dark"] .gps-panel p {
    color: #94a3b8 !important;
}
html[data-theme="dark"] #geofenceList > div {
    color: #94a3b8 !important;
}
.gps-geofence-card {
    background: #fff;
    color: #0f172a;
}
.dark .gps-geofence-card,
html[data-theme="dark"] .gps-geofence-card {
    background: #1e293b !important;
    color: #e2e8f0 !important;
}
.dark .gps-geofence-card h2,
html[data-theme="dark"] .gps-geofence-card h2 {
    color: #f1f5f9 !important;
}
.dark .gps-geofence-card p,
html[data-theme="dark"] .gps-geofence-card p {
    color: #94a3b8 !important;
}
.dark .gps-geofence-card > div[style*="border-bottom"],
html[data-theme="dark"] .gps-geofence-card > div[style*="border-bottom"] {
    border-color: #334155 !important;
}
</style>
<?php

function getFullAvatarUrl($avatarPath) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : ($protocol . '://' . $host);

    if (empty($avatarPath)) {
        return $baseUrl . '/public/assets/images/avatar-default.png';
    }
    if (preg_match('#^https?://#i', $avatarPath)) return $avatarPath;
    
    
    if (strpos($avatarPath, 'storage/uploads/') === 0) {
        return $baseUrl . '/' . ltrim($avatarPath, '/');
    }
    
    
    return $baseUrl . '/' . ltrim($avatarPath, '/');
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['administrator', 'direktur'])) {
    http_response_code(403);
    die('Access denied. Administrator/Direktur only.');
}


try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.avatar, u.role,
               COALESCE(gl.latitude, a.check_in_lat) as latitude,
               COALESCE(gl.longitude, a.check_in_lng) as longitude,
               COALESCE(gl.location_name, a.check_in_location) as location_name,
               COALESCE(gl.timestamp, a.check_in_time) as timestamp,
               a.attendance_date, a.check_in_time, a.status
        FROM users u
        LEFT JOIN (
            SELECT user_id, latitude, longitude, location_name, timestamp
            FROM gps_logs
            WHERE (user_id, timestamp) IN (
                SELECT user_id, MAX(timestamp)
                FROM gps_logs
                WHERE DATE(timestamp) = CURDATE()
                GROUP BY user_id
            )
        ) gl ON u.id = gl.user_id
        LEFT JOIN (
            SELECT user_id, attendance_date, check_in_time, check_in_lat, check_in_lng, check_in_location, status
            FROM attendances
            WHERE attendance_date = CURDATE()
        ) a ON u.id = a.user_id
        WHERE u.role NOT IN ('administrator','direktur')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute();
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $staff = [];
    $error = "Error loading staff: " . $e->getMessage();
}
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white">GPS Management</h1>
            <p class="text-slate-600 dark:text-slate-400 mt-2">Pantau lokasi real-time staff dan kelola zona absensi</p>
        </div>
    </div>

    <?php if (isset($error) && $error): ?>
    <script>document.addEventListener('DOMContentLoaded',function(){showToast(<?= json_encode($error) ?>,'error');});</script>
    <?php endif; ?>

    
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-1.5">
        <div class="flex gap-1">
            <button type="button" data-gpstab="tracking" class="gps-tab-btn flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all bg-indigo-600 text-white">
                <i class="fas fa-map-marker-alt mr-1"></i> GPS Tracking
            </button>
            <button type="button" data-gpstab="geofence" class="gps-tab-btn flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="fas fa-draw-polygon mr-1"></i> Zona Geofence
            </button>
        </div>
    </div>

    
    <div id="panel-tracking" class="gps-panel">

    
    <div class="flex items-center justify-between">
        <p id="refreshIndicator" class="text-xs text-slate-500 dark:text-slate-400">
            <i class="fas fa-sync-alt mr-1"></i> Auto-refresh setiap 30 detik
        </p>
        <button onclick="refreshGPSData()" class="text-xs px-3 py-1 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-full hover:bg-indigo-200 dark:hover:bg-indigo-900/50 font-medium">
            <i class="fas fa-sync-alt mr-1"></i> Refresh Sekarang
        </button>
    </div>

    
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg overflow-hidden">
        <div id="map" style="height: 500px; position: relative; z-index: 1;" class="w-full"></div>
    </div>

    
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg overflow-hidden">
        <div class="border-b border-slate-200 dark:border-slate-700 p-6">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Status Lokasi Staff</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700 dark:text-slate-300">Staff</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700 dark:text-slate-300">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700 dark:text-slate-300">Lokasi</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700 dark:text-slate-300">Update Terakhir</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700 dark:text-slate-300">Koordinat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staff)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                            Belum ada data GPS staff
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($staff as $s): ?>
                        <tr class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= htmlspecialchars(getFullAvatarUrl($s['avatar'] ?? '')) ?>" 
                                         alt="<?= htmlspecialchars($s['full_name']) ?>" 
                                         class="w-8 h-8 rounded-full object-cover">
                                    <div>
                                        <div class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($s['full_name']) ?></div>
                                        <div class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars($s['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold 
                                    <?php 
                                    $status = $s['status'] ?? 'Alpha';
                                    if ($status === 'Hadir') echo 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
                                    elseif ($status === 'Terlambat') echo 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
                                    elseif ($status === 'Izin' || $status === 'Sakit' || $status === 'Cuti') echo 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
                                    else echo 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
                                    ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                                <?php if (!empty($s['location_name'])): ?>
                                    <a href="#" data-lat="<?= htmlspecialchars($s['latitude']) ?>" 
                                       data-lng="<?= htmlspecialchars($s['longitude']) ?>" 
                                       onclick="highlightLocation(event, this)" 
                                       class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        <?= htmlspecialchars($s['location_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-500 dark:text-slate-400">Lokasi tidak tersedia</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                                <?php if (!empty($s['timestamp'])): ?>
                                    <?= date('H:i:s', strtotime($s['timestamp'])) ?>
                                    <br>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        <?php 
                                        $diff = time() - strtotime($s['timestamp']);
                                        if ($diff < 60) echo 'Baru saja';
                                        elseif ($diff < 3600) echo floor($diff / 60) . ' menit lalu';
                                        else echo floor($diff / 3600) . ' jam lalu';
                                        ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-500 dark:text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-mono text-slate-700 dark:text-slate-300">
                                <?php if (!empty($s['latitude']) && !empty($s['longitude'])): ?>
                                    <code class="text-xs"><?= number_format($s['latitude'], 4) ?>, <?= number_format($s['longitude'], 4) ?></code>
                                <?php else: ?>
                                    <span class="text-slate-500 dark:text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    </div>

    
    <div id="panel-geofence" class="gps-panel" style="display:none">

    
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg overflow-hidden">
        <div id="geofenceMap" style="height:400px;width:100%;position:relative;z-index:1"></div>
    </div>

    
    <div class="gps-geofence-card" style="border-radius:16px;box-shadow:0 4px 14px rgba(0,0,0,0.06);overflow:hidden">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h2 style="font-size:1.15rem;font-weight:700;margin:0">Zona Absensi (Geofence)</h2>
                <p style="font-size:0.82rem;margin:4px 0 0">Kelola lokasi yang diizinkan untuk absensi staff. Staff hanya bisa absen di dalam radius zona yang aktif.</p>
            </div>
            <button onclick="openAddZone()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:10px;border:none;background:#4f46e5;color:#fff;font-size:0.82rem;font-weight:600;cursor:pointer;transition:background 0.15s" onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
                <i class="fas fa-plus-circle"></i> Tambah Lokasi
            </button>
        </div>
        <div id="geofenceList" style="padding:1rem 1.5rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px">
            <div style="text-align:center;padding:2rem;color:#94a3b8">Memuat zona...</div>
        </div>
    </div>

    </div>

</div>


<div id="zoneModal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);padding:16px">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 50px rgba(0,0,0,0.2)">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#4f46e5;border-radius:16px 16px 0 0">
            <h3 id="zoneModalTitle" style="margin:0;font-size:1rem;font-weight:700;color:#fff"><i class="fas fa-map-pin" style="margin-right:6px"></i> Tambah Zona Absensi</h3>
            <button onclick="showZoneModal(false)" style="background:none;border:none;color:rgba(255,255,255,0.7);font-size:1.3rem;cursor:pointer;padding:0">&times;</button>
        </div>
        <form id="zoneForm" onsubmit="saveZone(event)" style="padding:20px">
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#374151;margin-bottom:4px">Nama Lokasi <span style="color:#ef4444">*</span></label>
                <input type="text" id="zone_name" placeholder="Contoh: Kantor Pusat Jakarta" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:0.85rem;background:#fff;color:#111827;box-sizing:border-box">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#374151;margin-bottom:4px">Deskripsi</label>
                <input type="text" id="zone_description" placeholder="Alamat atau keterangan tambahan" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:0.85rem;background:#fff;color:#111827;box-sizing:border-box">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#374151;margin-bottom:6px">Pilih Lokasi di Peta <span style="color:#ef4444">*</span></label>
                <p style="font-size:0.72rem;color:#94a3b8;margin:0 0 6px">Klik pada peta, seret marker, atau ketik koordinat manual</p>
                <div id="zonePickerMap" style="height:250px;border-radius:10px;border:1px solid #e2e8f0"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
                <div>
                    <label style="display:block;font-size:0.78rem;font-weight:600;color:#374151;margin-bottom:4px">Latitude</label>
                    <input type="text" id="zone_latitude" placeholder="-6.200000" oninput="onManualCoordInput()" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:0.82rem;box-sizing:border-box;font-family:monospace" class="bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
                </div>
                <div>
                    <label style="display:block;font-size:0.78rem;font-weight:600;color:#374151;margin-bottom:4px">Longitude</label>
                    <input type="text" id="zone_longitude" placeholder="106.800000" oninput="onManualCoordInput()" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:0.82rem;box-sizing:border-box;font-family:monospace" class="bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
                </div>
            </div>
            <div style="margin-bottom:18px">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#374151;margin-bottom:4px">Radius <span id="radiusDisplay" style="color:#4f46e5;font-weight:700">100m</span></label>
                <input type="range" id="zone_radius" min="50" max="2000" step="10" value="100" oninput="updatePickerRadius()" style="width:100%;accent-color:#4f46e5">
                <div style="display:flex;justify-content:space-between;font-size:0.68rem;color:#94a3b8"><span>50m</span><span>2000m</span></div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" onclick="showZoneModal(false)" style="padding:8px 18px;border-radius:8px;border:1px solid #d1d5db;background:#f9fafb;color:#374151;font-size:0.82rem;font-weight:500;cursor:pointer">Batal</button>
                <button type="submit" id="zoneSaveBtn" style="padding:8px 18px;border-radius:8px;border:none;background:#4f46e5;color:#fff;font-size:0.82rem;font-weight:600;cursor:pointer">Simpan</button>
            </div>
        </form>
    </div>
</div>


<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const map = L.map('map').setView([-6.2088, 106.8456], 12);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

const staffData = <?= json_encode($staff) ?>;
const markers = {};

staffData.forEach(function(staff) {
    if (staff.latitude && staff.longitude) {
        const marker = L.circleMarker([staff.latitude, staff.longitude], {
            radius: 8,
            fillColor: staff.status === 'Hadir' ? '#22c55e' : staff.status === 'Terlambat' ? '#eab308' : '#ef4444',
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        }).addTo(map);
        
        marker.bindPopup(`
            <div style="font-size: 12px;">
                <strong>${staff.full_name}</strong><br>
                Status: ${staff.status || 'N/A'}<br>
                Lokasi: ${staff.location_name || 'Unknown'}<br>
                Lat: ${parseFloat(staff.latitude).toFixed(4)}<br>
                Lng: ${parseFloat(staff.longitude).toFixed(4)}
            </div>
        `);
        
        markers[staff.id] = marker;
    }
});

const markerPositions = staffData.filter(s => s.latitude && s.longitude).map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]);
if (markerPositions.length > 0) {
    map.fitBounds(markerPositions, { padding: [30, 30], maxZoom: 14 });
}

function highlightLocation(e, el) {
    e.preventDefault();
    const lat = parseFloat(el.dataset.lat);
    const lng = parseFloat(el.dataset.lng);
    
    if (!isNaN(lat) && !isNaN(lng)) {
        map.setView([lat, lng], 15);
        Object.values(markers).forEach(m => {
            const latlng = m.getLatLng();
            if (Math.abs(latlng.lat - lat) < 0.0001 && Math.abs(latlng.lng - lng) < 0.0001) {
                m.openPopup();
            }
        });
    }
}

const REFRESH_INTERVAL = 30000;
let refreshTimer = null;
let lastRefresh = Date.now();

function getStatusColor(status) {
    if (status === 'Hadir') return '#22c55e';
    if (status === 'Terlambat') return '#eab308';
    return '#ef4444';
}

function formatTimeAgo(timestamp) {
    if (!timestamp) return '-';
    const diff = Math.floor((Date.now() - new Date(timestamp).getTime()) / 1000);
    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
    return Math.floor(diff / 3600) + ' jam lalu';
}

async function refreshGPSData() {
    try {
        const res = await fetch('app/action/gps-get-live.php', { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) return;

        data.staff.forEach(s => {
            const lat = parseFloat(s.latitude);
            const lng = parseFloat(s.longitude);
            
            if (!lat || !lng) {
                if (markers[s.id]) {
                    map.removeLayer(markers[s.id]);
                    delete markers[s.id];
                }
                return;
            }

            const popupContent = `
                <div style="font-size:12px">
                    <strong>${s.full_name}</strong><br>
                    Status: ${s.status || 'N/A'}<br>
                    Lokasi: ${s.location_name || 'Unknown'}<br>
                    Update: ${formatTimeAgo(s.last_update)}<br>
                    <code style="font-size:10px">${lat.toFixed(5)}, ${lng.toFixed(5)}</code>
                </div>
            `;

            if (markers[s.id]) {
                markers[s.id].setLatLng([lat, lng]);
                markers[s.id].setStyle({ fillColor: getStatusColor(s.status) });
                markers[s.id].setPopupContent(popupContent);
            } else {
                const marker = L.circleMarker([lat, lng], {
                    radius: 8,
                    fillColor: getStatusColor(s.status),
                    color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.8
                }).addTo(map);
                marker.bindPopup(popupContent);
                markers[s.id] = marker;
            }
        });

        const tbody = document.querySelector('#panel-tracking table tbody');
        if (tbody) {
            tbody.innerHTML = data.staff.map(s => {
                const hasLoc = s.latitude && s.longitude;
                const lat = parseFloat(s.latitude);
                const lng = parseFloat(s.longitude);
                const status = s.status || 'Alpha';
                let statusClass = 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
                if (status === 'Hadir') statusClass = 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
                else if (status === 'Terlambat') statusClass = 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
                else if (['Izin','Sakit','Cuti'].includes(status)) statusClass = 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';

                return `<tr class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-600 flex items-center justify-center text-xs font-bold text-slate-600 dark:text-slate-300">${s.full_name.charAt(0)}</div>
                            <div>
                                <div class="font-medium text-slate-900 dark:text-white">${s.full_name}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">${s.username || ''}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold ${statusClass}">${status}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                        ${hasLoc ? `<a href="#" data-lat="${lat}" data-lng="${lng}" onclick="highlightLocation(event, this)" class="text-indigo-600 dark:text-indigo-400 hover:underline">${s.location_name || lat.toFixed(4) + ', ' + lng.toFixed(4)}</a>` : '<span class="text-slate-500 dark:text-slate-400">Lokasi tidak tersedia</span>'}
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                        ${s.last_update ? formatTimeAgo(s.last_update) : '<span class="text-slate-500 dark:text-slate-400">-</span>'}
                    </td>
                    <td class="px-6 py-4 text-sm font-mono text-slate-700 dark:text-slate-300">
                        ${hasLoc ? `<code class="text-xs">${lat.toFixed(4)}, ${lng.toFixed(4)}</code>` : '<span class="text-slate-500 dark:text-slate-400">-</span>'}
                    </td>
                </tr>`;
            }).join('');
        }

        lastRefresh = Date.now();
        const indicator = document.getElementById('refreshIndicator');
        if (indicator) indicator.textContent = 'Terakhir diperbarui: ' + new Date().toLocaleTimeString('id-ID');

    } catch (err) {
        console.error('[GPS Refresh] Error:', err);
    }
}

refreshTimer = setInterval(refreshGPSData, REFRESH_INTERVAL);

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(refreshTimer);
    } else {
        refreshGPSData();
        refreshTimer = setInterval(refreshGPSData, REFRESH_INTERVAL);
    }
});

const geoState = { zones: [], editId: null, mapCircles: {} };
const GEO_BASE = './app/action/';

function geoFetch(endpoint, opts = {}) {
    const url = GEO_BASE + endpoint;
    return fetch(url, {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...opts.headers },
        ...opts
    }).then(r => r.json());
}

function loadGeofences() {
    geoFetch('tracking-get-geofences.php').then(data => {
        if (!data.success) return;
        geoState.zones = data.zones || [];
        renderGeofenceList();
        renderGeofenceCircles();
    }).catch(err => console.error('loadGeofences:', err));
}

function renderGeofenceCircles() {
    Object.values(geoState.mapCircles).forEach(c => map.removeLayer(c));
    geoState.mapCircles = {};
    
    geoState.zones.forEach(z => {
        if (!z.is_active) return;
        const circle = L.circle([z.latitude, z.longitude], {
            radius: z.radius_meters,
            color: z.color || '#3b82f6',
            fillColor: z.color || '#3b82f6',
            fillOpacity: 0.12,
            weight: 2,
            dashArray: '6 4'
        }).addTo(map);
        circle.bindPopup(`<div style="font-size:12px"><strong>${htmlEsc(z.name)}</strong><br>Radius: ${z.radius_meters}m<br>${z.description || ''}</div>`);
        geoState.mapCircles[z.id] = circle;
    });
    if (geoMapInstance) renderGeofenceCirclesOnGeoMap();
}

function htmlEsc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function renderGeofenceList() {
    const container = document.getElementById('geofenceList');
    if (!container) return;
    
    if (!geoState.zones.length) {
        container.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8"><i class="fas fa-map-marker-alt" style="font-size:2rem;margin-bottom:0.5rem;display:block"></i>Belum ada zona absensi.<br>Klik "Tambah Lokasi" untuk menambah.</div>';
        return;
    }
    
    let html = '';
    geoState.zones.forEach(z => {
        const active = z.is_active;
        const statusBadge = active
            ? '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;font-size:0.7rem;font-weight:600;background:#d1fae5;color:#065f46"><i class="fas fa-check-circle"></i> Aktif</span>'
            : '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;font-size:0.7rem;font-weight:600;background:#fee2e2;color:#991b1b"><i class="fas fa-times-circle"></i> Nonaktif</span>';
        html += `
        <div style="border:1px solid #e2e8f0;border-radius:12px;padding:1rem;background:#fff;transition:box-shadow 0.2s;position:relative;${!active ? 'opacity:0.6;' : ''}" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.boxShadow='none'">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px">
                <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0">
                    <div style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem" class="bg-blue-100 text-blue-600">
                        <i class="fas fa-map-pin"></i>
                    </div>
                    <div style="min-width:0">
                        <div style="font-weight:600;font-size:0.9rem;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${htmlEsc(z.name)}</div>
                        ${z.description ? `<div style="font-size:0.75rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${htmlEsc(z.description)}</div>` : ''}
                    </div>
                </div>
                ${statusBadge}
            </div>
            <div style="display:flex;gap:12px;font-size:0.75rem;color:#64748b;margin-bottom:10px;flex-wrap:wrap">
                <span><i class="fas fa-crosshairs" style="margin-right:3px"></i>${z.latitude.toFixed(6)}, ${z.longitude.toFixed(6)}</span>
                <span><i class="fas fa-bullseye" style="margin-right:3px"></i>${z.radius_meters}m radius</span>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button onclick="focusZone(${z.id})" style="padding:4px 10px;border-radius:6px;font-size:0.72rem;border:1px solid #e2e8f0;background:#f8fafc;color:#475569;cursor:pointer;display:inline-flex;align-items:center;gap:4px" title="Lihat di peta"><i class="fas fa-eye"></i> Peta</button>
                <button onclick="openEditZone(${z.id})" style="padding:4px 10px;border-radius:6px;font-size:0.72rem;border:1px solid #e2e8f0;background:#f8fafc;color:#475569;cursor:pointer;display:inline-flex;align-items:center;gap:4px" title="Edit"><i class="fas fa-pen"></i> Edit</button>
                <button onclick="toggleZone(${z.id}, ${active ? 0 : 1})" style="padding:4px 10px;border-radius:6px;font-size:0.72rem;border:1px solid ${active ? '#fee2e2' : '#d1fae5'};background:${active ? '#fef2f2' : '#f0fdf4'};color:${active ? '#dc2626' : '#16a34a'};cursor:pointer;display:inline-flex;align-items:center;gap:4px" title="${active ? 'Nonaktifkan' : 'Aktifkan'}"><i class="fas fa-${active ? 'toggle-off' : 'toggle-on'}"></i> ${active ? 'Nonaktifkan' : 'Aktifkan'}</button>
                <button onclick="deleteZone(${z.id}, '${htmlEsc(z.name).replace(/'/g, "\\'")}')" style="padding:4px 10px;border-radius:6px;font-size:0.72rem;border:1px solid #fee2e2;background:#fef2f2;color:#dc2626;cursor:pointer;display:inline-flex;align-items:center;gap:4px" title="Hapus"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function focusZone(id) {
    const z = geoState.zones.find(x => x.id === id);
    if (!z) return;
    map.setView([z.latitude, z.longitude], 16);
    const circle = geoState.mapCircles[id];
    if (circle) circle.openPopup();
}

function openAddZone() {
    geoState.editId = null;
    document.getElementById('zoneModalTitle').textContent = 'Tambah Zona Absensi';
    document.getElementById('zoneForm').reset();
    document.getElementById('zone_radius').value = 100;
    showZoneModal(true);
    initZonePicker();
}

function openEditZone(id) {
    const z = geoState.zones.find(x => x.id === id);
    if (!z) return;
    geoState.editId = id;
    document.getElementById('zoneModalTitle').textContent = 'Edit Zona Absensi';
    document.getElementById('zone_name').value = z.name;
    document.getElementById('zone_description').value = z.description || '';
    document.getElementById('zone_latitude').value = z.latitude;
    document.getElementById('zone_longitude').value = z.longitude;
    document.getElementById('zone_radius').value = z.radius_meters;
    showZoneModal(true);
    initZonePicker(z.latitude, z.longitude, z.radius_meters);
    setTimeout(() => setLatLngInputs(z.latitude, z.longitude), 250);
}

let zonePickerMap = null, zonePickerMarker = null, zonePickerCircle = null;

function setLatLngInputs(lat, lng) {
    const latEl = document.getElementById('zone_latitude');
    const lngEl = document.getElementById('zone_longitude');
    latEl.value = typeof lat === 'number' ? lat.toFixed(8) : lat;
    lngEl.value = typeof lng === 'number' ? lng.toFixed(8) : lng;
    latEl.style.setProperty('color', '#0f172a', 'important');
    lngEl.style.setProperty('color', '#0f172a', 'important');
    latEl.style.setProperty('background-color', '#ffffff', 'important');
    lngEl.style.setProperty('background-color', '#ffffff', 'important');
}

let _coordInputTimer = null;
function onManualCoordInput() {
    clearTimeout(_coordInputTimer);
    _coordInputTimer = setTimeout(() => {
        const lat = parseFloat(document.getElementById('zone_latitude').value);
        const lng = parseFloat(document.getElementById('zone_longitude').value);
        if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
            if (zonePickerMarker) zonePickerMarker.setLatLng([lat, lng]);
            if (zonePickerCircle) zonePickerCircle.setLatLng([lat, lng]);
            if (zonePickerMap) zonePickerMap.setView([lat, lng], 16);
        }
    }, 400);
}

function initZonePicker(lat, lng, radius) {
    lat = lat || -6.2088;
    lng = lng || 106.8456;
    radius = radius || 100;
    
    setTimeout(() => {
        const container = document.getElementById('zonePickerMap');
        if (!container) return;
        
        if (zonePickerMap) {
            zonePickerMap.remove();
            zonePickerMap = null;
        }
        
        zonePickerMap = L.map('zonePickerMap').setView([lat, lng], lat === -6.2088 ? 12 : 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OSM'
        }).addTo(zonePickerMap);
        
        zonePickerMarker = L.marker([lat, lng], { draggable: true }).addTo(zonePickerMap);
        zonePickerCircle = L.circle([lat, lng], {
            radius: radius,
            color: '#3b82f6',
            fillColor: '#3b82f6',
            fillOpacity: 0.15,
            weight: 2
        }).addTo(zonePickerMap);
        
        zonePickerMarker.on('dragend', function(e) {
            const p = e.target.getLatLng();
            setLatLngInputs(p.lat, p.lng);
            zonePickerCircle.setLatLng(p);
        });
        
        zonePickerMap.on('click', function(e) {
            zonePickerMarker.setLatLng(e.latlng);
            zonePickerCircle.setLatLng(e.latlng);
            setLatLngInputs(e.latlng.lat, e.latlng.lng);
        });
        
        if (lat !== -6.2088) {
            setLatLngInputs(lat, lng);
        }
    }, 200);
}

function updatePickerRadius() {
    const r = parseInt(document.getElementById('zone_radius').value) || 100;
    if (zonePickerCircle) zonePickerCircle.setRadius(r);
    document.getElementById('radiusDisplay').textContent = r + 'm';
}

function showZoneModal(show) {
    const m = document.getElementById('zoneModal');
    if (show) { m.style.display = 'flex'; } else { m.style.display = 'none'; }
}

function saveZone(e) {
    e.preventDefault();
    const name = document.getElementById('zone_name').value.trim();
    const description = document.getElementById('zone_description').value.trim();
    const latitude = parseFloat(document.getElementById('zone_latitude').value);
    const longitude = parseFloat(document.getElementById('zone_longitude').value);
    const radius = parseInt(document.getElementById('zone_radius').value);
    
    if (!name) { showToast('Nama lokasi wajib diisi', 'error'); return; }
    if (isNaN(latitude) || isNaN(longitude)) { showToast('Pilih lokasi di peta terlebih dahulu', 'error'); return; }
    if (radius < 50 || radius > 5000) { showToast('Radius harus 50-5000 meter', 'error'); return; }
    
    const payload = { name, description, latitude, longitude, radius };
    const btn = document.getElementById('zoneSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';
    
    const endpoint = geoState.editId
        ? 'tracking-update-geofence.php'
        : 'tracking-create-geofence.php';
    
    if (geoState.editId) payload.zone_id = geoState.editId;
    
    geoFetch(endpoint, { method: 'POST', body: JSON.stringify(payload) })
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Simpan';
            if (data.success) {
                showToast(geoState.editId ? 'Zona berhasil diperbarui' : 'Zona berhasil ditambahkan', 'success');
                showZoneModal(false);
                loadGeofences();
            } else {
                showToast(data.message || 'Gagal menyimpan zona', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = 'Simpan';
            showToast('Error: ' + err.message, 'error');
        });
}

function toggleZone(id, newState) {
    geoFetch('tracking-toggle-geofence.php', {
        method: 'POST',
        body: JSON.stringify({ zone_id: id, is_active: newState })
    }).then(data => {
        if (data.success) {
            showToast(newState ? 'Zona diaktifkan' : 'Zona dinonaktifkan', 'success');
            loadGeofences();
        } else {
            showToast(data.message || 'Gagal mengubah status', 'error');
        }
    }).catch(err => showToast('Error: ' + err.message, 'error'));
}

function deleteZone(id, name) {
    if (!confirm('Hapus zona "' + name + '"? Aksi ini tidak dapat dibatalkan.')) return;
    geoFetch('tracking-delete-geofence.php', {
        method: 'POST',
        body: JSON.stringify({ zone_id: id })
    }).then(data => {
        if (data.success) {
            showToast('Zona berhasil dihapus', 'success');
            loadGeofences();
        } else {
            showToast(data.message || 'Gagal menghapus', 'error');
        }
    }).catch(err => showToast('Error: ' + err.message, 'error'));
}

let geoMapInstance = null;

document.querySelectorAll('.gps-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tab = this.dataset.gpstab;
        document.querySelectorAll('.gps-tab-btn').forEach(b => {
            b.classList.remove('bg-indigo-600', 'text-white');
            b.classList.add('text-slate-500', 'dark:text-slate-400', 'hover:bg-slate-100', 'dark:hover:bg-slate-700');
        });
        this.classList.remove('text-slate-500', 'dark:text-slate-400', 'hover:bg-slate-100', 'dark:hover:bg-slate-700');
        this.classList.add('bg-indigo-600', 'text-white');
        document.querySelectorAll('.gps-panel').forEach(p => p.style.display = 'none');
        const panel = document.getElementById('panel-' + tab);
        if (panel) panel.style.display = '';
        if (tab === 'geofence') {
            if (!geoMapInstance) {
                geoMapInstance = L.map('geofenceMap').setView([-6.2088, 106.8456], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19
                }).addTo(geoMapInstance);
            }
            setTimeout(() => geoMapInstance.invalidateSize(), 100);
            renderGeofenceCirclesOnGeoMap();
        }
        if (tab === 'tracking') {
            setTimeout(() => map.invalidateSize(), 100);
        }
    });
});

function renderGeofenceCirclesOnGeoMap() {
    if (!geoMapInstance) return;
    Object.values(geoState.geoMapCircles || {}).forEach(c => geoMapInstance.removeLayer(c));
    geoState.geoMapCircles = {};
    geoState.zones.forEach(z => {
        const circle = L.circle([z.latitude, z.longitude], {
            radius: z.radius_meters,
            color: z.is_active ? (z.color || '#3b82f6') : '#94a3b8',
            fillColor: z.is_active ? (z.color || '#3b82f6') : '#94a3b8',
            fillOpacity: z.is_active ? 0.15 : 0.08,
            weight: 2,
            dashArray: z.is_active ? '6 4' : '4 4'
        }).addTo(geoMapInstance);
        circle.bindPopup(`<div style="font-size:12px"><strong>${htmlEsc(z.name)}</strong><br>Radius: ${z.radius_meters}m<br>${z.description || ''}<br><em>${z.is_active ? 'Aktif' : 'Nonaktif'}</em></div>`);
        geoState.geoMapCircles[z.id] = circle;
    });
    if (geoState.zones.length) {
        const group = L.featureGroup(Object.values(geoState.geoMapCircles));
        geoMapInstance.fitBounds(group.getBounds().pad(0.2));
    }
}

const _origFocusZone = focusZone;
focusZone = function(id) {
    const z = geoState.zones.find(x => x.id === id);
    if (!z) return;
    if (geoMapInstance) {
        geoMapInstance.setView([z.latitude, z.longitude], 16);
        const circle = (geoState.geoMapCircles || {})[id];
        if (circle) circle.openPopup();
    }
};

document.addEventListener('DOMContentLoaded', loadGeofences);
</script>

