<?php
 
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';
$isAdmin = has_role(['administrator', 'direktur']);
$csrfToken = csrf_token();


$stmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<style>
.br-page { max-width: 1200px; margin: 0 auto; }

.br-header { margin-bottom: 28px; }
.br-header-inner {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 16px;
}
.br-header h1 {
    font-size: 1.75rem; font-weight: 800; color: #0f172a;
    display: flex; align-items: center; gap: 10px;
}
.dark .br-header h1 { color: #f1f5f9; }
.br-header p { color: #64748b; margin-top: 4px; font-size: 0.9rem; }
.dark .br-header p { color: #94a3b8; }
.br-header-accent {
    width: 56px; height: 4px; background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    border-radius: 99px; margin-top: 14px;
}
.br-btn-new {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px; background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: #fff; border: none; border-radius: 12px; font-weight: 600;
    font-size: 0.9rem; cursor: pointer; transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(59,130,246,0.3);
}
.br-btn-new:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(59,130,246,0.4);
}

.br-stats {
    display: grid; grid-template-columns: repeat(5, 1fr);
    gap: 12px; margin-bottom: 24px;
}
@media (max-width: 768px) { .br-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .br-stats { grid-template-columns: 1fr 1fr; } }
.br-stat-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
    padding: 18px 20px; position: relative; overflow: hidden;
}
.dark .br-stat-card { background: #1e293b; border-color: #334155; }
.br-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
}
.br-stat-card.stat-total::before { background: linear-gradient(90deg, #64748b, #94a3b8); }
.br-stat-card.stat-open::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.br-stat-card.stat-progress::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.br-stat-card.stat-resolved::before { background: linear-gradient(90deg, #10b981, #34d399); }
.br-stat-card.stat-critical::before { background: linear-gradient(90deg, #ef4444, #f87171); }
.br-stat-num {
    font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1;
}
.dark .br-stat-num { color: #f1f5f9; }
.br-stat-label { font-size: 0.75rem; color: #94a3b8; margin-top: 6px; font-weight: 500; }

.br-filters {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
    padding: 14px 20px; margin-bottom: 24px;
    display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
}
.dark .br-filters { background: #1e293b; border-color: #334155; }
.br-filter-label {
    font-size: 0.8rem; font-weight: 600; color: #94a3b8;
    display: flex; align-items: center; gap: 6px;
}
.br-select {
    padding: 7px 12px; border: 1px solid #d1d5db; border-radius: 10px;
    font-size: 0.85rem; background: #fff; color: #0f172a;
    cursor: pointer; transition: border 0.15s;
}
.br-select:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.dark .br-select { background: #0f172a; border-color: #475569; color: #f1f5f9; }
.br-btn-refresh {
    padding: 7px 14px; border: 1px solid #e5e7eb; border-radius: 10px;
    background: #f8fafc; color: #64748b; font-size: 0.85rem; font-weight: 500;
    cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 6px;
}
.br-btn-refresh:hover { background: #e2e8f0; color: #475569; }
.dark .br-btn-refresh { background: #334155; border-color: #475569; color: #94a3b8; }
.dark .br-btn-refresh:hover { background: #475569; }

.br-reports { display: flex; flex-direction: column; gap: 10px; }
.br-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
    padding: 18px 22px; cursor: pointer; transition: all 0.2s ease;
    position: relative; overflow: hidden;
}
.br-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.06); transform: translateY(-1px); }
.dark .br-card { background: #1e293b; border-color: #334155; }
.dark .br-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.25); }
.br-card::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
}
.br-card.priority-critical::before { background: #ef4444; }
.br-card.priority-high::before { background: #f97316; }
.br-card.priority-medium::before { background: #eab308; }
.br-card.priority-low::before { background: #22c55e; }

.br-card-top {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
}
.br-card-body { flex: 1; min-width: 0; }
.br-card-badges { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.br-card-title {
    font-size: 0.95rem; font-weight: 700; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dark .br-card-title { color: #f1f5f9; }
.br-card-desc {
    font-size: 0.82rem; color: #64748b; margin-top: 4px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.dark .br-card-desc { color: #94a3b8; }
.br-card-meta {
    display: flex; flex-direction: column; align-items: flex-end; gap: 3px; flex-shrink: 0;
}
.br-card-meta span { font-size: 0.75rem; color: #94a3b8; white-space: nowrap; }

.br-card-actions {
    display: flex; gap: 8px; margin-top: 14px; padding-top: 14px;
    border-top: 1px solid #f1f5f9;
}
.dark .br-card-actions { border-top-color: #334155; }
.br-action-btn {
    padding: 5px 14px; border-radius: 8px; font-size: 0.75rem; font-weight: 600;
    cursor: pointer; border: none; transition: all 0.15s;
    display: inline-flex; align-items: center; gap: 5px;
}
.br-action-btn.update { background: #eff6ff; color: #2563eb; }
.br-action-btn.update:hover { background: #dbeafe; }
.br-action-btn.delete { background: #fef2f2; color: #dc2626; }
.br-action-btn.delete:hover { background: #fee2e2; }
.dark .br-action-btn.update { background: rgba(59,130,246,0.15); color: #93c5fd; }
.dark .br-action-btn.update:hover { background: rgba(59,130,246,0.25); }
.dark .br-action-btn.delete { background: rgba(239,68,68,0.15); color: #fca5a5; }
.dark .br-action-btn.delete:hover { background: rgba(239,68,68,0.25); }

.br-status-badge {
    font-size: 0.68rem; padding: 3px 10px; border-radius: 99px;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
    display: inline-flex; align-items: center;
}
.br-status-open { background: #dbeafe; color: #1d4ed8; }
.br-status-in_progress { background: #fef3c7; color: #92400e; }
.br-status-resolved { background: #d1fae5; color: #065f46; }
.br-status-closed { background: #f1f5f9; color: #475569; }
.br-status-wont_fix { background: #fce7f3; color: #9d174d; }
.dark .br-status-open { background: rgba(59,130,246,0.2); color: #93c5fd; }
.dark .br-status-in_progress { background: rgba(245,158,11,0.2); color: #fcd34d; }
.dark .br-status-resolved { background: rgba(16,185,129,0.2); color: #6ee7b7; }
.dark .br-status-closed { background: rgba(100,116,139,0.2); color: #94a3b8; }
.dark .br-status-wont_fix { background: rgba(236,72,153,0.2); color: #f9a8d4; }

.br-cat-badge {
    font-size: 0.65rem; padding: 2px 8px; border-radius: 6px; font-weight: 600;
}
.br-cat-bug { background: #fef2f2; color: #dc2626; }
.br-cat-feature { background: #f5f3ff; color: #7c3aed; }
.br-cat-ui { background: #eef2ff; color: #4f46e5; }
.br-cat-performance { background: #fff7ed; color: #ea580c; }
.br-cat-security { background: #fdf2f8; color: #db2777; }
.br-cat-other { background: #f8fafc; color: #64748b; }
.dark .br-cat-bug { background: rgba(239,68,68,0.15); color: #fca5a5; }
.dark .br-cat-feature { background: rgba(124,58,237,0.15); color: #c4b5fd; }
.dark .br-cat-ui { background: rgba(79,70,229,0.15); color: #a5b4fc; }
.dark .br-cat-performance { background: rgba(234,88,12,0.15); color: #fdba74; }
.dark .br-cat-security { background: rgba(219,39,119,0.15); color: #f9a8d4; }
.dark .br-cat-other { background: rgba(100,116,139,0.15); color: #94a3b8; }

.br-empty {
    text-align: center; padding: 60px 20px;
}
.br-empty i { font-size: 3.5rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
.dark .br-empty i { color: #475569; }
.br-empty p { color: #64748b; font-size: 1rem; }
.dark .br-empty p { color: #94a3b8; }
.br-empty .sub { font-size: 0.85rem; color: #94a3b8; margin-top: 4px; }
.br-loading {
    display: flex; align-items: center; justify-content: center; padding: 48px 0; gap: 12px;
}
.br-loading .spinner {
    width: 28px; height: 28px; border: 3px solid #e5e7eb; border-top-color: #3b82f6;
    border-radius: 50%; animation: br-spin 0.7s linear infinite;
}
@keyframes br-spin { to { transform: rotate(360deg); } }
.br-loading span { color: #94a3b8; font-size: 0.9rem; }

.br-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px); z-index: 70;
    display: none; align-items: center; justify-content: center; padding: 20px;
}
.br-modal-overlay.active { display: flex; }

.br-modal {
    background: #fff; border-radius: 20px; width: 100%;
    max-height: 90vh; overflow-y: auto;
    box-shadow: 0 24px 48px rgba(0,0,0,0.12);
    animation: br-modal-in 0.2s ease; pointer-events: auto;
}
.dark .br-modal { background: #1e293b; }
@keyframes br-modal-in {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes br-sheet-in {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
.br-modal.modal-sm { max-width: 480px; }
.br-modal.modal-md { max-width: 560px; }
.br-modal.modal-lg { max-width: 680px; }

@media (max-width: 640px) {
    .br-modal-overlay { padding: 0; align-items: flex-end; }
    .br-modal {
        border-radius: 20px 20px 0 0; max-height: 92vh;
        animation: br-sheet-in 0.25s ease;
    }
    .br-modal.modal-sm, .br-modal.modal-md, .br-modal.modal-lg { max-width: 100%; }
}

.br-modal-header {
    padding: 22px 28px 0; display: flex; align-items: center; justify-content: space-between;
}
@media (max-width: 640px) { .br-modal-header { padding: 16px 18px 0; } }
.br-modal-header h2 {
    font-size: 1.15rem; font-weight: 700; color: #0f172a;
    display: flex; align-items: center; gap: 8px;
}
@media (max-width: 640px) { .br-modal-header h2 { font-size: 1rem; } }
.dark .br-modal-header h2 { color: #f1f5f9; }
.br-modal-close {
    width: 36px; height: 36px; border-radius: 10px; border: 1px solid #e5e7eb;
    background: #f8fafc; display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 16px; color: #64748b; transition: all 0.15s;
}
.br-modal-close:hover { background: #fee2e2; color: #dc2626; border-color: #fecaca; }
.dark .br-modal-close { background: #334155; border-color: #475569; color: #94a3b8; }
.dark .br-modal-close:hover { background: #7f1d1d; color: #fca5a5; border-color: #991b1b; }
.br-modal-body { padding: 20px 28px 28px; }
@media (max-width: 640px) { .br-modal-body { padding: 14px 18px 20px; } }

.br-form-group { margin-bottom: 16px; }
@media (max-width: 640px) { .br-form-group { margin-bottom: 10px; } }
.br-form-label {
    display: block; font-size: 0.8rem; font-weight: 600; color: #475569;
    margin-bottom: 6px;
}
@media (max-width: 640px) { .br-form-label { font-size: 0.75rem; margin-bottom: 4px; } }
.dark .br-form-label { color: #94a3b8; }
.br-form-label .req { color: #ef4444; }
.br-form-input,
.br-form-select,
.br-form-textarea {
    width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 10px;
    font-size: 16px; background: #fff; color: #0f172a; transition: border 0.15s;
    box-sizing: border-box; font-family: inherit;
}
@media (min-width: 641px) {
    .br-form-input, .br-form-select, .br-form-textarea { font-size: 0.9rem; }
}
@media (max-width: 640px) {
    .br-form-input, .br-form-select, .br-form-textarea { padding: 9px 12px; border-radius: 8px; }
}
.br-form-input:focus,
.br-form-select:focus,
.br-form-textarea:focus {
    border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.dark .br-form-input,
.dark .br-form-select,
.dark .br-form-textarea { background: #0f172a; border-color: #475569; color: #f1f5f9; }
.dark .br-form-input:focus,
.dark .br-form-select:focus,
.dark .br-form-textarea:focus { box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
.br-form-input::placeholder,
.br-form-textarea::placeholder { color: #94a3b8; }

.br-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 640px) { .br-form-row { gap: 8px; } }

.br-char-counter { font-size: 0.72rem; color: #94a3b8; text-align: right; margin-top: 4px; }
.br-char-counter.warn { color: #f59e0b; }
.br-char-counter.danger { color: #ef4444; }

.br-file-upload {
    border: 2px dashed #d1d5db; border-radius: 12px; padding: 16px;
    text-align: center; cursor: pointer; transition: all 0.15s; position: relative;
}
.br-file-upload:hover { border-color: #3b82f6; background: #eff6ff; }
.dark .br-file-upload { border-color: #475569; }
.dark .br-file-upload:hover { border-color: #3b82f6; background: rgba(59,130,246,0.1); }
.br-file-upload input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.br-file-upload i { font-size: 1.5rem; color: #94a3b8; margin-bottom: 6px; }
.br-file-upload .label { font-size: 0.85rem; color: #64748b; font-weight: 500; }
.dark .br-file-upload .label { color: #94a3b8; }
.br-file-upload .hint { font-size: 0.72rem; color: #94a3b8; margin-top: 2px; }
@media (max-width: 640px) {
    .br-file-upload { padding: 10px; border-radius: 10px; }
    .br-file-upload i { font-size: 1.1rem; margin-bottom: 2px; }
    .br-file-upload .label { font-size: 0.78rem; }
    .br-file-upload .hint { font-size: 0.65rem; }
}

.br-screenshot-preview {
    max-height: 200px; object-fit: contain; cursor: pointer;
    border-radius: 10px; border: 1px solid #e5e7eb; margin-top: 10px;
}
@media (max-width: 640px) {
    .br-screenshot-preview { max-height: 120px; margin-top: 8px; }
}
.dark .br-screenshot-preview { border-color: #475569; }
.br-screenshot-preview:hover { opacity: 0.85; }

.br-btn-row { display: flex; gap: 10px; margin-top: 20px; }
@media (max-width: 640px) { .br-btn-row { margin-top: 14px; gap: 8px; } }
.br-btn {
    flex: 1; padding: 11px 20px; border-radius: 12px; font-weight: 600;
    font-size: 0.9rem; cursor: pointer; border: none; transition: all 0.15s;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
}
@media (max-width: 640px) { .br-btn { padding: 10px 14px; font-size: 0.84rem; border-radius: 10px; } }
.br-btn-primary {
    background: linear-gradient(135deg, #3b82f6, #6366f1); color: #fff;
    box-shadow: 0 2px 8px rgba(59,130,246,0.25);
}
.br-btn-primary:hover { box-shadow: 0 4px 12px rgba(59,130,246,0.35); }
.br-btn-secondary {
    background: #f8fafc; color: #475569; border: 1px solid #e5e7eb;
}
.br-btn-secondary:hover { background: #f1f5f9; }
.dark .br-btn-secondary { background: #334155; color: #94a3b8; border-color: #475569; }
.dark .br-btn-secondary:hover { background: #475569; }

.br-detail-header { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.br-detail-title { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
.dark .br-detail-title { color: #f1f5f9; }
.br-detail-meta {
    display: flex; flex-wrap: wrap; gap: 16px; font-size: 0.8rem; color: #94a3b8;
    margin-bottom: 16px;
}
.br-detail-meta i { margin-right: 4px; }
.br-detail-description {
    padding: 16px; background: #f8fafc; border-radius: 12px;
    font-size: 0.9rem; color: #334155; white-space: pre-wrap; line-height: 1.6;
}
.dark .br-detail-description { background: #0f172a; color: #cbd5e1; }
.br-admin-note {
    margin-top: 16px; padding: 14px 16px; background: #eff6ff; border-radius: 12px;
    border: 1px solid #bfdbfe;
}
.dark .br-admin-note { background: rgba(59,130,246,0.1); border-color: rgba(59,130,246,0.2); }
.br-admin-note .note-label {
    font-size: 0.75rem; font-weight: 700; color: #2563eb; margin-bottom: 4px;
    display: flex; align-items: center; gap: 5px;
}
.dark .br-admin-note .note-label { color: #93c5fd; }
.br-admin-note .note-text { font-size: 0.85rem; color: #1e40af; }
.dark .br-admin-note .note-text { color: #bfdbfe; }
.br-resolved-info { font-size: 0.78rem; color: #94a3b8; margin-top: 14px; display: flex; align-items: center; gap: 6px; }

.pointer-events-auto { pointer-events: auto !important; }
</style>

<div class="br-page" id="bug-report-app">
    
    <div class="br-header">
        <div class="br-header-inner">
            <div>
                <h1>
                    <i class="fas fa-bug" style="color:#ef4444;"></i>
                    Bug Report
                </h1>
                <p><?= $isAdmin ? 'Kelola semua laporan bug dari pengguna' : 'Laporkan masalah atau saran untuk perbaikan website' ?></p>
            </div>
            <button class="br-btn-new" onclick="openSubmitModal()">
                <i class="fas fa-plus"></i> Buat Laporan Baru
            </button>
        </div>
        <div class="br-header-accent"></div>
    </div>

    <?php if ($isAdmin): ?>
    
    <div class="br-stats" id="stats-cards">
        <div class="br-stat-card stat-total">
            <div class="br-stat-num" id="stat-total">-</div>
            <div class="br-stat-label">Total Laporan</div>
        </div>
        <div class="br-stat-card stat-open">
            <div class="br-stat-num" id="stat-open" style="color:#3b82f6;">-</div>
            <div class="br-stat-label">Open</div>
        </div>
        <div class="br-stat-card stat-progress">
            <div class="br-stat-num" id="stat-progress" style="color:#f59e0b;">-</div>
            <div class="br-stat-label">In Progress</div>
        </div>
        <div class="br-stat-card stat-resolved">
            <div class="br-stat-num" id="stat-resolved" style="color:#10b981;">-</div>
            <div class="br-stat-label">Resolved</div>
        </div>
        <div class="br-stat-card stat-critical">
            <div class="br-stat-num" id="stat-critical" style="color:#ef4444;">-</div>
            <div class="br-stat-label">Critical Active</div>
        </div>
    </div>

    
    <div class="br-filters">
        <div class="br-filter-label"><i class="fas fa-filter"></i> Filter:</div>
        <select id="filter-status" onchange="loadReports()" class="br-select">
            <option value="">Semua Status</option>
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
            <option value="wont_fix">Won't Fix</option>
        </select>
        <select id="filter-category" onchange="loadReports()" class="br-select">
            <option value="">Semua Kategori</option>
            <option value="bug">Bug</option>
            <option value="feature">Feature Request</option>
            <option value="ui">UI/UX</option>
            <option value="performance">Performance</option>
            <option value="security">Security</option>
            <option value="other">Lainnya</option>
        </select>
        <select id="filter-priority" onchange="loadReports()" class="br-select">
            <option value="">Semua Prioritas</option>
            <option value="critical">Critical</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
        </select>
        <button onclick="loadReports()" class="br-btn-refresh">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
    <?php endif; ?>

    
    <div id="reports-container">
        <div class="br-loading">
            <div class="spinner"></div>
            <span>Memuat laporan...</span>
        </div>
    </div>
</div>


<div id="submit-modal" class="br-modal-overlay" data-bug-overlay="submit">
    <div class="br-modal modal-md" onclick="event.stopPropagation()">
        <div class="br-modal-header">
            <h2><i class="fas fa-bug" style="color:#ef4444;"></i> Buat Laporan Baru</h2>
            <button class="br-modal-close" onclick="closeSubmitModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="br-modal-body">
            <form id="bug-report-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="submit">

                <div class="br-form-group">
                    <label class="br-form-label">Judul <span class="req">*</span></label>
                    <input type="text" name="title" id="bug-title" required maxlength="255"
                        placeholder="Contoh: Tombol submit tidak berfungsi di halaman report"
                        class="br-form-input">
                    <div class="br-char-counter" id="title-counter">0/255</div>
                </div>

                <div class="br-form-row">
                    <div class="br-form-group">
                        <label class="br-form-label">Kategori</label>
                        <select name="category" class="br-form-select">
                            <option value="bug"><i class="fas fa-bug mr-2"></i>Bug</option>
                            <option value="feature"><i class="fas fa-lightbulb mr-2"></i>Feature Request</option>
                            <option value="ui"><i class="fas fa-palette mr-2"></i>UI/UX</option>
                            <option value="performance"><i class="fas fa-tachometer-alt mr-2"></i>Performance</option>
                            <option value="security"><i class="fas fa-lock mr-2"></i>Security</option>
                            <option value="other"><i class="fas fa-file-alt mr-2"></i>Lainnya</option>
                        </select>
                    </div>
                    <div class="br-form-group">
                        <label class="br-form-label">Prioritas</label>
                        <select name="priority" class="br-form-select">
                            <option value="low"><i class="fas fa-circle-notch text-green-500 mr-2"></i>Low</option>
                            <option value="medium" selected><i class="fas fa-adjust text-yellow-500 mr-2"></i>Medium</option>
                            <option value="high"><i class="fas fa-exclamation-circle text-orange-500 mr-2"></i>High</option>
                            <option value="critical"><i class="fas fa-bolt text-red-500 mr-2"></i>Critical</option>
                        </select>
                    </div>
                </div>

                <div class="br-form-group">
                    <label class="br-form-label">Halaman yang Bermasalah</label>
                    <input type="text" name="page_url" placeholder="Contoh: dashboard.php?page=report atau URL lengkap"
                        class="br-form-input">
                </div>

                <div class="br-form-group">
                    <label class="br-form-label">Deskripsi <span class="req">*</span></label>
                    <textarea name="description" id="bug-description" required rows="3" maxlength="5000"
                        placeholder="Jelaskan secara detail:&#10;1. Apa yang terjadi?&#10;2. Apa yang seharusnya terjadi?&#10;3. Langkah untuk mereproduksi masalah"
                        class="br-form-textarea" style="resize:vertical;"></textarea>
                    <div class="br-char-counter" id="desc-counter">0/5000</div>
                </div>

                <div class="br-form-group">
                    <label class="br-form-label">Screenshot (opsional)</label>
                    <div class="br-file-upload">
                        <input type="file" name="screenshot" id="bug-screenshot" accept="image/jpeg,image/png,image/gif,image/webp">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <div class="label">Klik atau seret file ke sini</div>
                        <div class="hint">Maks. 5MB • Format: JPG, PNG, GIF, WebP</div>
                    </div>
                    <div id="screenshot-preview-container" style="display:none;">
                        <img id="screenshot-preview-img" class="br-screenshot-preview">
                    </div>
                </div>

                <div class="br-btn-row">
                    <button type="button" onclick="closeSubmitModal()" class="br-btn br-btn-secondary">Batal</button>
                    <button type="submit" id="submit-btn" class="br-btn br-btn-primary">
                        <i class="fas fa-paper-plane"></i> Kirim Laporan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div id="detail-modal" class="br-modal-overlay" data-bug-overlay="detail">
    <div class="br-modal modal-lg" onclick="event.stopPropagation()">
        <div class="br-modal-header">
            <h2>Detail Laporan</h2>
            <button class="br-modal-close" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="br-modal-body" id="detail-content">
            <div class="br-loading">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>


<?php if ($isAdmin): ?>
<div id="status-modal" class="br-modal-overlay" data-bug-overlay="status">
    <div class="br-modal modal-sm" onclick="event.stopPropagation()">
        <div class="br-modal-header">
            <h2>Update Status</h2>
            <button class="br-modal-close" onclick="closeStatusModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="br-modal-body">
            <form id="status-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="report_id" id="status-report-id">

                <div class="br-form-group">
                    <label class="br-form-label">Status Baru</label>
                    <select name="status" id="status-select" class="br-form-select">
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                        <option value="wont_fix">Won't Fix</option>
                    </select>
                </div>

                <div class="br-form-group">
                    <label class="br-form-label">Catatan Admin</label>
                    <textarea name="admin_notes" id="admin-notes-input" rows="3" placeholder="Catatan atau penjelasan..."
                        class="br-form-textarea" style="resize:vertical;"></textarea>
                </div>

                <div class="br-btn-row">
                    <button type="button" onclick="closeStatusModal()" class="br-btn br-btn-secondary">Batal</button>
                    <button type="submit" class="br-btn br-btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
const CSRF_TOKEN = '<?= htmlspecialchars($csrfToken) ?>';
const HANDLER_URL = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'app/action/bug-report-handler.php';

function htmlEscape(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function getCategoryLabel(cat) {
    const map = { bug: '<i class="fas fa-bug mr-1"></i> Bug', feature: '<i class="fas fa-lightbulb mr-1"></i> Feature', ui: '<i class="fas fa-palette mr-1"></i> UI/UX', performance: '<i class="fas fa-tachometer-alt mr-1"></i> Perf', security: '<i class="fas fa-lock mr-1"></i> Security', other: '<i class="fas fa-file-alt mr-1"></i> Other' };
    return map[cat] || cat;
}

function getCategoryClass(cat) {
    const map = { bug: 'br-cat-bug', feature: 'br-cat-feature', ui: 'br-cat-ui', performance: 'br-cat-performance', security: 'br-cat-security', other: 'br-cat-other' };
    return map[cat] || 'br-cat-other';
}

function getStatusLabel(s) {
    const map = { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved', closed: 'Closed', wont_fix: "Won't Fix" };
    return map[s] || s;
}

function getPriorityIcon(p) {
    const map = { critical: '<i class="fas fa-bolt text-red-500"></i>', high: '<i class="fas fa-exclamation-circle text-orange-500"></i>', medium: '<i class="fas fa-adjust text-yellow-500"></i>', low: '<i class="fas fa-circle-notch text-green-500"></i>' };
    return map[p] || '';
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    const bgMap = { success: '#10b981', error: '#ef4444', info: '#3b82f6' };
    toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:12px;color:#fff;font-size:0.9rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.15);transition:all 0.3s;transform:translateY(10px);opacity:0;background:' + (bgMap[type] || bgMap.info);
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => { toast.style.transform = 'translateY(0)'; toast.style.opacity = '1'; });
    setTimeout(() => {
        toast.style.transform = 'translateY(20px)'; toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function loadReports() {
    const container = document.getElementById('reports-container');
    container.innerHTML = '<div class="br-loading"><div class="spinner"></div><span>Memuat laporan...</span></div>';

    let url = HANDLER_URL + '?action=' + (IS_ADMIN ? 'all_reports' : 'my_reports');

    if (IS_ADMIN) {
        const status = document.getElementById('filter-status')?.value || '';
        const category = document.getElementById('filter-category')?.value || '';
        const priority = document.getElementById('filter-priority')?.value || '';
        if (status) url += '&status=' + status;
        if (category) url += '&category=' + category;
        if (priority) url += '&priority=' + priority;
    }

    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                container.innerHTML = '<div class="br-empty"><i class="fas fa-exclamation-triangle"></i><p style="color:#ef4444;">' + htmlEscape(res.message) + '</p></div>';
                return;
            }

            if (IS_ADMIN && res.stats) {
                document.getElementById('stat-total').textContent = res.stats.total || 0;
                document.getElementById('stat-open').textContent = res.stats.open_count || 0;
                document.getElementById('stat-progress').textContent = res.stats.in_progress_count || 0;
                document.getElementById('stat-resolved').textContent = res.stats.resolved_count || 0;
                document.getElementById('stat-critical').textContent = res.stats.critical_active || 0;
            }

            if (!res.data || res.data.length === 0) {
                container.innerHTML = '<div class="br-empty"><i class="fas fa-clipboard-check"></i><p>Belum ada laporan</p><p class="sub">' + (IS_ADMIN ? 'Belum ada pengguna yang mengirim laporan bug.' : 'Klik "Buat Laporan Baru" jika menemukan masalah.') + '</p></div>';
                return;
            }

            let html = '<div class="br-reports">';
            res.data.forEach(r => {
                html += `
                <div class="br-card priority-${htmlEscape(r.priority)}" onclick="viewDetail(${r.id})">
                    <div class="br-card-top">
                        <div class="br-card-body">
                            <div class="br-card-badges">
                                <span class="br-status-badge br-status-${htmlEscape(r.status)}">${getStatusLabel(r.status)}</span>
                                <span class="br-cat-badge ${getCategoryClass(r.category)}">${getCategoryLabel(r.category)}</span>
                                <span style="font-size:0.75rem;color:#94a3b8;">${getPriorityIcon(r.priority)} ${htmlEscape(r.priority)}</span>
                                ${r.screenshot_path ? '<span style="font-size:0.75rem;color:#94a3b8;"><i class="fas fa-image"></i></span>' : ''}
                            </div>
                            <div class="br-card-title">${htmlEscape(r.title)}</div>
                            <div class="br-card-desc">${htmlEscape(r.description)}</div>
                        </div>
                        <div class="br-card-meta">
                            <span>#${r.id}</span>
                            ${IS_ADMIN ? '<span>' + htmlEscape(r.reporter_name || '') + '</span>' : ''}
                            <span>${formatDate(r.created_at)}</span>
                        </div>
                    </div>
                    ${IS_ADMIN ? `
                    <div class="br-card-actions">
                        <button onclick="event.stopPropagation(); openStatusModal(${r.id}, '${htmlEscape(r.status)}', '${htmlEscape(r.admin_notes || '')}')" class="br-action-btn update">
                            <i class="fas fa-edit"></i> Update
                        </button>
                        <button onclick="event.stopPropagation(); deleteReport(${r.id})" class="br-action-btn delete">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>` : ''}
                </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(err => {
            console.error('Load reports error:', err);
            container.innerHTML = '<div class="br-empty"><i class="fas fa-exclamation-triangle"></i><p style="color:#ef4444;">Gagal memuat laporan.</p></div>';
        });
}

function viewDetail(id) {
    _bugModalProtect();
    const modal = document.getElementById('detail-modal');
    const content = document.getElementById('detail-content');
    modal.classList.add('active');
    content.innerHTML = '<div class="br-loading"><div class="spinner"></div></div>';

    fetch(HANDLER_URL + '?action=detail&id=' + id)
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data) {
                content.innerHTML = '<p style="text-align:center;color:#ef4444;padding:20px 0;">' + htmlEscape(res.message || 'Tidak ditemukan') + '</p>';
                return;
            }
            const r = res.data;
            const screenshotHtml = r.screenshot_path 
                ? `<div style="margin-top:16px;"><p style="font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:8px;">Screenshot:</p><img src="${(typeof BASE_URL !== 'undefined' ? BASE_URL : '') + htmlEscape(r.screenshot_path)}" class="br-screenshot-preview" style="max-height:320px;max-width:100%;display:block;" onclick="window.open(this.src, '_blank')"></div>` 
                : '';
            const adminSection = r.admin_notes 
                ? `<div class="br-admin-note"><div class="note-label"><i class="fas fa-comment-dots"></i> Catatan Admin:</div><div class="note-text">${htmlEscape(r.admin_notes)}</div></div>` 
                : '';
            content.innerHTML = `
                <div>
                    <div class="br-detail-header">
                        <span class="br-status-badge br-status-${htmlEscape(r.status)}">${getStatusLabel(r.status)}</span>
                        <span class="br-cat-badge ${getCategoryClass(r.category)}">${getCategoryLabel(r.category)}</span>
                        <span style="font-size:0.85rem;">${getPriorityIcon(r.priority)} ${htmlEscape(r.priority)}</span>
                        <span style="font-size:0.75rem;color:#94a3b8;margin-left:auto;">#${r.id}</span>
                    </div>
                    <div class="br-detail-title">${htmlEscape(r.title)}</div>
                    <div class="br-detail-meta">
                        <span><i class="fas fa-user"></i> ${htmlEscape(r.reporter_name)}</span>
                        <span><i class="fas fa-clock"></i> ${formatDate(r.created_at)}</span>
                        ${r.page_url ? '<span><i class="fas fa-link"></i> ' + htmlEscape(r.page_url) + '</span>' : ''}
                    </div>
                    <div class="br-detail-description">${htmlEscape(r.description)}</div>
                    ${screenshotHtml}
                    ${adminSection}
                    ${r.resolved_by ? '<div class="br-resolved-info"><i class="fas fa-check-circle" style="color:#10b981;"></i> Diselesaikan oleh ' + htmlEscape(r.resolver_name || '-') + ' pada ' + formatDate(r.resolved_at) + '</div>' : ''}
                </div>`;
        })
        .catch(err => {
            content.innerHTML = '<p style="text-align:center;color:#ef4444;padding:20px 0;">Gagal memuat detail.</p>';
        });
}

let _bugModalJustOpened = false;

function _bugModalProtect() {
    _bugModalJustOpened = true;
    setTimeout(() => { _bugModalJustOpened = false; }, 350);
}

function openSubmitModal() {
    _bugModalProtect();
    document.getElementById('submit-modal').classList.add('active');
    document.getElementById('bug-report-form').reset();
    document.getElementById('screenshot-preview-container').style.display = 'none';
    updateCounters();
}

function closeSubmitModal() {
    document.getElementById('submit-modal').classList.remove('active');
}

function closeDetailModal() {
    document.getElementById('detail-modal').classList.remove('active');
}

<?php if ($isAdmin): ?>
function openStatusModal(id, currentStatus, currentNotes) {
    _bugModalProtect();
    document.getElementById('status-modal').classList.add('active');
    document.getElementById('status-report-id').value = id;
    document.getElementById('status-select').value = currentStatus;
    document.getElementById('admin-notes-input').value = currentNotes || '';
}

function closeStatusModal() {
    document.getElementById('status-modal').classList.remove('active');
}
<?php endif; ?>

(function() {
    var _overlayMouseDownTarget = null;
    var closeFns = { submit: closeSubmitModal, detail: closeDetailModal };
    <?php if ($isAdmin): ?>
    closeFns['status'] = closeStatusModal;
    <?php endif; ?>

    document.querySelectorAll('[data-bug-overlay]').forEach(function(overlay) {
        overlay.addEventListener('mousedown', function(e) {
            _overlayMouseDownTarget = e.target;
        });
        overlay.addEventListener('click', function(e) {
            if (_bugModalJustOpened) { _overlayMouseDownTarget = null; return; }
            if (e.target === overlay && _overlayMouseDownTarget === overlay) {
                var key = overlay.getAttribute('data-bug-overlay');
                if (closeFns[key]) closeFns[key]();
            }
            _overlayMouseDownTarget = null;
        });
    });
})();

document.getElementById('bug-report-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';

    const formData = new FormData(this);

    fetch(HANDLER_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message, 'success');
                closeSubmitModal();
                loadReports();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(() => showToast('Terjadi kesalahan jaringan.', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        });
});

<?php if ($isAdmin): ?>
document.getElementById('status-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch(HANDLER_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message, 'success');
                closeStatusModal();
                loadReports();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(() => showToast('Terjadi kesalahan jaringan.', 'error'));
});

function deleteReport(id) {
    if (!confirm('Yakin ingin menghapus laporan #' + id + '? Tindakan ini tidak bisa dibatalkan.')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('report_id', id);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch(HANDLER_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message, 'success');
                loadReports();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(() => showToast('Gagal menghapus.', 'error'));
}
<?php endif; ?>

function updateCounters() {
    const titleEl = document.getElementById('bug-title');
    const descEl = document.getElementById('bug-description');
    const titleCounter = document.getElementById('title-counter');
    const descCounter = document.getElementById('desc-counter');

    if (titleEl && titleCounter) {
        const len = titleEl.value.length;
        titleCounter.textContent = len + '/255';
        titleCounter.className = 'br-char-counter ' + (len > 230 ? 'danger' : len > 200 ? 'warn' : '');
    }
    if (descEl && descCounter) {
        const len = descEl.value.length;
        descCounter.textContent = len + '/5000';
        descCounter.className = 'br-char-counter ' + (len > 4500 ? 'danger' : len > 4000 ? 'warn' : '');
    }
}

document.getElementById('bug-title')?.addEventListener('input', updateCounters);
document.getElementById('bug-description')?.addEventListener('input', updateCounters);

document.getElementById('bug-screenshot')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const container = document.getElementById('screenshot-preview-container');
    const img = document.getElementById('screenshot-preview-img');
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            img.src = ev.target.result;
            container.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        container.style.display = 'none';
    }
});

loadReports();
</script>
