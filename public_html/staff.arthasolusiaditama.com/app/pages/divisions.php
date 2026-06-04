<?php
 

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';

$role = $_SESSION['role'] ?? '';
$canManage = in_array($role, ['administrator', 'technician_manager']);


$divisions = [];
try {
    $stmt = $pdo->query("
        SELECT d.*, 
            (SELECT COUNT(*) FROM user_divisions ud WHERE ud.division_id = d.id) as member_count,
            u.full_name as creator_name
        FROM divisions d
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.is_active = 1
        ORDER BY d.name ASC
    ");
    $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $divisions = [];
}


$allUsers = [];
if ($canManage) {
    try {
        $stmt = $pdo->query("SELECT id, full_name, username, role, avatar, gender FROM users WHERE is_active = 1 ORDER BY full_name ASC");
        $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allUsers as &$_u) {
            $_u['avatar_url'] = getAvatarUrl($_u);
        }
        unset($_u);
    } catch (Throwable $e) {
        $allUsers = [];
    }
}
?>

<style>
.div-page { max-width: 1200px; margin: 0 auto; }
.div-header { margin-bottom: 32px; }
.div-header h1 { font-size: 1.875rem; font-weight: 800; color: #0f172a; }
.dark .div-header h1 { color: #f1f5f9; }
.div-header p { color: #64748b; margin-top: 6px; font-size: 0.95rem; }
.dark .div-header p { color: #94a3b8; }

.div-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; }
@media (max-width: 768px) { .div-grid { grid-template-columns: 1fr; } }

.div-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
}
.div-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.08); transform: translateY(-2px); }
.dark .div-card { background: #1e293b; border-color: #334155; }
.dark .div-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.3); }

.div-card-top { padding: 24px 24px 16px; }
.div-card-accent { height: 4px; width: 100%; }
.div-card-name { font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
.dark .div-card-name { color: #f1f5f9; }
.div-card-desc { font-size: 0.85rem; color: #64748b; line-height: 1.5; }
.dark .div-card-desc { color: #94a3b8; }

.div-card-footer {
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.dark .div-card-footer { border-top-color: #334155; }

.div-member-count {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.85rem; color: #64748b; font-weight: 600;
}
.dark .div-member-count { color: #94a3b8; }

.div-badge {
    display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px;
    border-radius: 8px; font-size: 0.75rem; font-weight: 600;
}

.div-add-card {
    background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 16px;
    display: flex; align-items: center; justify-content: center; min-height: 180px;
    cursor: pointer; transition: all 0.2s; flex-direction: column; gap: 8px;
    color: #64748b; font-weight: 600; font-size: 0.95rem;
}
.div-add-card:hover { border-color: #3b82f6; color: #3b82f6; background: #eff6ff; }
.dark .div-add-card { background: #0f172a; border-color: #475569; color: #94a3b8; }
.dark .div-add-card:hover { border-color: #3b82f6; color: #3b82f6; background: #1e3a5f; }

.div-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000;
    display: none; align-items: center; justify-content: center; padding: 20px;
    backdrop-filter: blur(4px);
}
.div-modal-overlay.active { display: flex; }
.div-modal {
    background: #fff; border-radius: 20px; width: 100%; max-width: 640px;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 24px 48px rgba(0,0,0,0.15);
    animation: divModalIn 0.2s ease;
}
.dark .div-modal { background: #1e293b; color: #f1f5f9; }
@keyframes divModalIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.div-modal-header {
    padding: 24px 28px 0; display: flex; align-items: center; justify-content: space-between;
}
.div-modal-header h2 { font-size: 1.25rem; font-weight: 700; color: #0f172a; }
.dark .div-modal-header h2 { color: #f1f5f9; }
.div-modal-close {
    width: 36px; height: 36px; border-radius: 10px; border: 1px solid #e5e7eb;
    background: #f8fafc; display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 18px; color: #64748b; transition: all 0.15s;
}
.div-modal-close:hover { background: #fee2e2; color: #dc2626; border-color: #fecaca; }
.dark .div-modal-close { background: #334155; border-color: #475569; color: #94a3b8; }
.dark .div-modal-close:hover { background: #7f1d1d; color: #fca5a5; border-color: #991b1b; }
.div-modal-body { padding: 20px 28px 28px; }

.div-form-group { margin-bottom: 16px; }
.div-form-label { display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em; }
.dark .div-form-label { color: #94a3b8; }
.div-form-input {
    width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 10px;
    font-size: 0.95rem; background: #fff; color: #0f172a; transition: border 0.15s;
    box-sizing: border-box;
}
.div-form-input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.dark .div-form-input { background: #0f172a; border-color: #475569; color: #f1f5f9; }
.dark .div-form-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }

.div-btn {
    padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 0.9rem;
    cursor: pointer; border: none; transition: all 0.15s; display: inline-flex;
    align-items: center; gap: 6px;
}
.div-btn-primary { background: #3b82f6; color: #fff; }
.div-btn-primary:hover { background: #2563eb; }
.div-btn-danger { background: #fee2e2; color: #dc2626; }
.div-btn-danger:hover { background: #fecaca; }
.div-btn-ghost { background: transparent; color: #64748b; }
.div-btn-ghost:hover { background: #f1f5f9; }
.dark .div-btn-ghost { color: #94a3b8; }
.dark .div-btn-ghost:hover { background: #334155; }
.dark .div-btn-danger { background: #7f1d1d20; color: #fca5a5; }

.div-member-item {
    display: flex; align-items: center; gap: 12px; padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}
.div-member-item:last-child { border-bottom: none; }
.dark .div-member-item { border-bottom-color: #334155; }
.div-member-avatar {
    width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
    background: #e2e8f0; flex-shrink: 0;
}
.div-member-name { font-size: 0.95rem; font-weight: 600; color: #0f172a; }
.dark .div-member-name { color: #f1f5f9; }
.div-member-role { font-size: 0.8rem; color: #94a3b8; }

.div-color-pick {
    width: 44px; height: 44px; border: 2px solid #e5e7eb; border-radius: 10px;
    cursor: pointer; padding: 0; background: none;
}
.div-color-pick::-webkit-color-swatch-wrapper { padding: 2px; }
.div-color-pick::-webkit-color-swatch { border: none; border-radius: 6px; }

.div-empty {
    text-align: center; padding: 40px 20px; color: #94a3b8; font-size: 0.9rem;
}

.div-assign-search {
    width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;
    font-size: 0.9rem; margin-bottom: 8px; background: #fff; color: #0f172a;
    box-sizing: border-box;
}
.dark .div-assign-search { background: #0f172a; border-color: #475569; color: #f1f5f9; }
.div-assign-list { max-height: 240px; overflow-y: auto; }
.div-assign-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 0; border-bottom: 1px solid #f8fafc; gap: 8px;
}
.dark .div-assign-item { border-bottom-color: #1e293b; }
.div-assign-item-info { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.div-assign-item-name { font-size: 0.85rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dark .div-assign-item-name { color: #f1f5f9; }
.div-assign-item-sub { font-size: 0.75rem; color: #94a3b8; }
.div-assign-btn {
    padding: 4px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;
    cursor: pointer; border: none; transition: all 0.15s; flex-shrink: 0;
}
.div-assign-btn-add { background: #dbeafe; color: #2563eb; }
.div-assign-btn-add:hover { background: #bfdbfe; }
.div-assign-btn-remove { background: #fee2e2; color: #dc2626; }
.div-assign-btn-remove:hover { background: #fecaca; }
</style>

<div class="div-page">
    
    <div class="div-header">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <h1>Divisi</h1>
                <p>Lihat dan kelola divisi serta anggota tim perusahaan</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:0.85rem;color:#94a3b8;"><?= count($divisions) ?> divisi aktif</span>
            </div>
        </div>
        <div style="width:64px;height:4px;background:#3b82f6;border-radius:99px;margin-top:16px;"></div>
    </div>

    
    <div class="div-grid" id="divisionGrid">
        <?php foreach ($divisions as $div): ?>
        <div class="div-card" onclick="openDivisionDetail(<?= $div['id'] ?>, this)" data-id="<?= $div['id'] ?>">
            <div class="div-card-accent" style="background:<?= htmlspecialchars($div['color']) ?>;"></div>
            <div class="div-card-top">
                <div class="div-card-name"><?= htmlspecialchars($div['name']) ?></div>
                <div class="div-card-desc"><?= htmlspecialchars($div['description'] ?? 'Tidak ada deskripsi') ?></div>
            </div>
            <div class="div-card-footer">
                <div class="div-member-count">
                    <i class="fas fa-users" style="font-size:14px;"></i>
                    <span><?= (int)$div['member_count'] ?> anggota</span>
                </div>
                <div class="div-badge" style="background:<?= htmlspecialchars($div['color']) ?>15;color:<?= htmlspecialchars($div['color']) ?>;">
                    <i class="fas fa-eye" style="font-size:10px;"></i> Lihat
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($canManage): ?>
        <div class="div-add-card" onclick="openCreateModal()">
            <i class="fas fa-plus-circle" style="font-size:28px;"></i>
            <span>Tambah Divisi Baru</span>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($divisions) && !$canManage): ?>
    <div class="div-empty">
        <i class="fas fa-sitemap" style="font-size:48px;margin-bottom:12px;display:block;color:#cbd5e1;"></i>
        <p>Belum ada divisi yang dibuat.</p>
    </div>
    <?php endif; ?>
</div>


<div class="div-modal-overlay" id="detailModal">
    <div class="div-modal" style="max-width:560px;">
        <div class="div-modal-header">
            <h2 id="detailTitle">Detail Divisi</h2>
            <button class="div-modal-close" onclick="closeModal('detailModal')">&times;</button>
        </div>
        <div class="div-modal-body">
            <div id="detailDesc" style="font-size:0.9rem;color:#64748b;margin-bottom:20px;"></div>
            
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <span style="font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#94a3b8;">Anggota</span>
                <?php if ($canManage): ?>
                <button class="div-btn div-btn-primary" style="padding:6px 14px;font-size:0.8rem;" onclick="openAssignModal()">
                    <i class="fas fa-plus"></i> Tambah
                </button>
                <?php endif; ?>
            </div>

            <div id="detailMembers">
                <div class="div-empty">Memuat...</div>
            </div>

            <?php if ($canManage): ?>
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9;display:flex;gap:8px;justify-content:flex-end;">
                <button class="div-btn div-btn-ghost" onclick="openEditModal()"><i class="fas fa-edit"></i> Edit</button>
                <button class="div-btn div-btn-danger" onclick="deleteDivision()"><i class="fas fa-trash"></i> Hapus</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<div class="div-modal-overlay" id="formModal">
    <div class="div-modal" style="max-width:480px;">
        <div class="div-modal-header">
            <h2 id="formTitle">Tambah Divisi</h2>
            <button class="div-modal-close" onclick="closeModal('formModal')">&times;</button>
        </div>
        <div class="div-modal-body">
            <form id="divisionForm" onsubmit="saveDivision(event)">
                <input type="hidden" id="formDivId" value="">
                <div class="div-form-group">
                    <label class="div-form-label">Nama Divisi</label>
                    <input type="text" class="div-form-input" id="formName" placeholder="contoh: IT, Finance" required>
                </div>
                <div class="div-form-group">
                    <label class="div-form-label">Deskripsi</label>
                    <textarea class="div-form-input" id="formDesc" rows="3" placeholder="Deskripsi singkat divisi..." style="resize:vertical;"></textarea>
                </div>
                <div class="div-form-group">
                    <label class="div-form-label">Warna Aksen</label>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <input type="color" class="div-color-pick" id="formColor" value="#3b82f6">
                        <span style="font-size:0.85rem;color:#94a3b8;" id="formColorHex">#3b82f6</span>
                    </div>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;">
                    <button type="button" class="div-btn div-btn-ghost" onclick="closeModal('formModal')">Batal</button>
                    <button type="submit" class="div-btn div-btn-primary" id="formSubmitBtn">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="div-modal-overlay" id="assignModal">
    <div class="div-modal" style="max-width:480px;">
        <div class="div-modal-header">
            <h2>Kelola Anggota</h2>
            <button class="div-modal-close" onclick="closeModal('assignModal')">&times;</button>
        </div>
        <div class="div-modal-body">
            <input type="text" class="div-assign-search" id="assignSearch" placeholder="Cari nama user..." oninput="filterAssignList()">
            <div class="div-assign-list" id="assignList">
                <div class="div-empty">Memuat...</div>
            </div>
        </div>
    </div>
</div>

<script>
const canManage = <?= json_encode($canManage) ?>;
const allUsers = <?= json_encode($allUsers) ?>;
const handlerUrl = './app/action/division-handler.php';
let currentDivisionId = null;
let currentMembers = [];

function openDivisionDetail(id, el) {
    currentDivisionId = id;
    const name = el?.querySelector('.div-card-name')?.textContent || 'Divisi';
    const desc = el?.querySelector('.div-card-desc')?.textContent || '';
    document.getElementById('detailTitle').textContent = name;
    document.getElementById('detailDesc').textContent = desc;
    document.getElementById('detailMembers').innerHTML = '<div class="div-empty">Memuat...</div>';
    showModal('detailModal');
    loadMembers(id);
}

async function loadMembers(id) {
    try {
        const r = await fetch(handlerUrl + '?action=members&division_id=' + id);
        const j = await r.json();
        currentMembers = j.data || [];
        renderMembers();
    } catch (e) {
        document.getElementById('detailMembers').innerHTML = '<div class="div-empty">Gagal memuat anggota.</div>';
    }
}

function renderMembers() {
    const container = document.getElementById('detailMembers');
    if (!currentMembers.length) {
        container.innerHTML = '<div class="div-empty">Belum ada anggota di divisi ini.</div>';
        return;
    }
    let html = '';
    currentMembers.forEach(m => {
        const avatarUrl = m.avatar_url || m.avatar || 'public/assets/images/avatar-default.png';
        const roleLabel = {administrator:'Administrator',direktur:'Direktur',technician_manager:'Manager Teknisi',sales:'Sales',technician:'Teknisi',hse:'HSE',daily:'Daily',internship:'Internship',customer:'Customer'}[m.role] || m.role;
        html += `
        <div class="div-member-item">
            <img src="${avatarUrl}" alt="" class="div-member-avatar" onerror="this.src='public/assets/images/avatar-default.png'">
            <div style="flex:1;min-width:0;">
                <a href="dashboard.php?page=profile&user_id=${m.id}" class="div-member-name" style="text-decoration:none;">${escH(m.full_name)}</a>
                <div class="div-member-role">@${escH(m.username)} · ${roleLabel}</div>
            </div>
            ${canManage ? `<button class="div-assign-btn div-assign-btn-remove" onclick="unassignUser(${m.id}, '${escH(m.full_name)}')"><i class="fas fa-times"></i></button>` : ''}
        </div>`;
    });
    container.innerHTML = html;
}

function openCreateModal() {
    document.getElementById('formTitle').textContent = 'Tambah Divisi Baru';
    document.getElementById('formDivId').value = '';
    document.getElementById('formName').value = '';
    document.getElementById('formDesc').value = '';
    document.getElementById('formColor').value = '#3b82f6';
    document.getElementById('formColorHex').textContent = '#3b82f6';
    showModal('formModal');
}

function openEditModal() {
    const name = document.getElementById('detailTitle').textContent;
    const desc = document.getElementById('detailDesc').textContent;
    document.getElementById('formTitle').textContent = 'Edit Divisi';
    document.getElementById('formDivId').value = currentDivisionId;
    document.getElementById('formName').value = name;
    document.getElementById('formDesc').value = desc === 'Tidak ada deskripsi' ? '' : desc;
    const card = document.querySelector(`.div-card[data-id="${currentDivisionId}"]`);
    const accent = card?.querySelector('.div-card-accent');
    const color = accent?.style.background || '#3b82f6';
    document.getElementById('formColor').value = color;
    document.getElementById('formColorHex').textContent = color;
    closeModal('detailModal');
    showModal('formModal');
}

async function saveDivision(e) {
    e.preventDefault();
    const id = document.getElementById('formDivId').value;
    const fd = new FormData();
    fd.set('action', id ? 'update' : 'create');
    if (id) fd.set('id', id);
    fd.set('name', document.getElementById('formName').value);
    fd.set('description', document.getElementById('formDesc').value);
    fd.set('color', document.getElementById('formColor').value);

    try {
        const r = await fetch(handlerUrl, { method: 'POST', body: fd });
        const j = await r.json();
        if (j.success) {
            showToast(j.message || 'Divisi berhasil disimpan!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(j.message || 'Gagal menyimpan.', 'error');
        }
    } catch (e) {
        showToast('Terjadi kesalahan jaringan.', 'error');
    }
}

async function deleteDivision() {
    if (!confirm('Yakin ingin menghapus divisi ini?')) return;
    const fd = new FormData();
    fd.set('action', 'delete');
    fd.set('id', currentDivisionId);
    try {
        const r = await fetch(handlerUrl, { method: 'POST', body: fd });
        const j = await r.json();
        if (j.success) { showToast('Divisi berhasil dihapus!', 'success'); setTimeout(() => location.reload(), 1500); }
        else showToast(j.message || 'Gagal menghapus.', 'error');
    } catch (e) {
        showToast('Terjadi kesalahan.', 'error');
    }
}

function openAssignModal() {
    closeModal('detailModal');
    showModal('assignModal');
    document.getElementById('assignSearch').value = '';
    renderAssignList();
}

function renderAssignList(filter = '') {
    const container = document.getElementById('assignList');
    const memberIds = new Set(currentMembers.map(m => m.id));
    const filtered = allUsers.filter(u => {
        const q = filter.toLowerCase();
        return (!q || u.full_name.toLowerCase().includes(q) || u.username.toLowerCase().includes(q));
    });

    if (!filtered.length) {
        container.innerHTML = '<div class="div-empty">Tidak ditemukan.</div>';
        return;
    }

    let html = '';
    filtered.forEach(u => {
        const isMember = memberIds.has(u.id);
        const roleLabel = {administrator:'Admin',technician_manager:'Mgr Teknisi',sales:'Sales',technician:'Teknisi',hse:'HSE'}[u.role] || u.role;
        html += `
        <div class="div-assign-item">
            <div class="div-assign-item-info">
                <div style="min-width:0;">
                    <div class="div-assign-item-name">${escH(u.full_name)}</div>
                    <div class="div-assign-item-sub">@${escH(u.username)} · ${roleLabel}</div>
                </div>
            </div>
            ${isMember
                ? `<button class="div-assign-btn div-assign-btn-remove" onclick="toggleAssign(${u.id}, false)"><i class="fas fa-minus"></i> Hapus</button>`
                : `<button class="div-assign-btn div-assign-btn-add" onclick="toggleAssign(${u.id}, true)"><i class="fas fa-plus"></i> Tambah</button>`
            }
        </div>`;
    });
    container.innerHTML = html;
}

function filterAssignList() {
    renderAssignList(document.getElementById('assignSearch').value);
}

async function toggleAssign(userId, add) {
    const fd = new FormData();
    fd.set('action', add ? 'assign' : 'unassign');
    fd.set('division_id', currentDivisionId);
    fd.set('user_id', userId);
    try {
        const r = await fetch(handlerUrl, { method: 'POST', body: fd });
        const j = await r.json();
        if (j.success) {
            await loadMembers(currentDivisionId);
            renderAssignList(document.getElementById('assignSearch').value);
            updateCardMemberCount(currentDivisionId, currentMembers.length);
        } else {
            showToast(j.message, 'error');
        }
    } catch (e) {
        showToast('Gagal.', 'error');
    }
}

async function unassignUser(userId, name) {
    if (!confirm('Hapus ' + name + ' dari divisi ini?')) return;
    await toggleAssign(userId, false);
}

document.getElementById('formColor')?.addEventListener('input', function() {
    document.getElementById('formColorHex').textContent = this.value;
});

let _divModalJustOpened = false;

function showModal(id) {
    _divModalJustOpened = true;
    document.getElementById(id).classList.add('active');
    setTimeout(() => { _divModalJustOpened = false; }, 300);
}
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function escH(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function updateCardMemberCount(divId, count) {
    const card = document.querySelector(`.div-card[data-id="${divId}"]`);
    if (card) {
        const countEl = card.querySelector('.div-member-count span');
        if (countEl) countEl.textContent = count + ' anggota';
    }
}

document.querySelectorAll('.div-modal-overlay .div-modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});

let _divOverlayMouseDownTarget = null;
document.querySelectorAll('.div-modal-overlay').forEach(overlay => {
    overlay.addEventListener('mousedown', function(e) {
        _divOverlayMouseDownTarget = e.target;
    });
    overlay.addEventListener('click', function(e) {
        if (_divModalJustOpened) { _divOverlayMouseDownTarget = null; return; }
        if (e.target === this && _divOverlayMouseDownTarget === this) {
            this.classList.remove('active');
        }
        _divOverlayMouseDownTarget = null;
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.div-modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});
</script>
