<?php
 
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';

$currentUserId = $_SESSION['user_id'];
$currentRole = $_SESSION['role'] ?? '';
$sessionId = session_id();
$canManage = in_array($currentRole, ['administrator', 'technician_manager']);


$stmtUser = $pdo->prepare("SELECT id, full_name, username, avatar, role FROM users WHERE id = ?");
$stmtUser->execute([$currentUserId]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);


$socketUrl = '';  
?>

<style>
.chat-wrapper {
    display: flex;
    height: calc(100vh - 80px);
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.dark .chat-wrapper { background: #1e293b; border-color: #334155; }

.chat-sidebar {
    width: 340px;
    min-width: 340px;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    background: #f8fafc;
}
.dark .chat-sidebar { background: #0f172a; border-right-color: #334155; }

.chat-sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.dark .chat-sidebar-header { border-bottom-color: #334155; }

.chat-sidebar-title {
    font-size: 1.3rem;
    font-weight: 800;
    color: #0f172a;
}
.dark .chat-sidebar-title { color: #f1f5f9; }

.chat-sidebar-actions {
    display: flex;
    gap: 8px;
}
.chat-sidebar-actions button {
    width: 36px; height: 36px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    font-size: 14px;
}
.chat-sidebar-actions button:hover { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.dark .chat-sidebar-actions button { background: #1e293b; border-color: #334155; color: #94a3b8; }
.dark .chat-sidebar-actions button:hover { background: #3b82f6; color: #fff; border-color: #3b82f6; }

.chat-search {
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
}
.dark .chat-search { border-bottom-color: #334155; }

.chat-search input {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font-size: 0.9rem;
    outline: none;
    color: #0f172a;
    transition: border-color 0.15s;
}
.chat-search input:focus { border-color: #3b82f6; }
.dark .chat-search input { background: #1e293b; border-color: #334155; color: #f1f5f9; }
.dark .chat-search input:focus { border-color: #3b82f6; }

.chat-room-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.chat-room-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.15s;
    position: relative;
}
.chat-room-item:hover { background: #e2e8f0; }
.chat-room-item.active { background: #3b82f6; }
.chat-room-item.active * { color: #fff !important; }
.dark .chat-room-item:hover { background: #334155; }
.dark .chat-room-item.active { background: #3b82f6; }

.chat-room-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    object-fit: cover;
    background: #e2e8f0;
    flex-shrink: 0;
}
.chat-room-avatar-group {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
}

.chat-room-info { flex: 1; min-width: 0; }
.chat-room-name {
    font-weight: 600;
    font-size: 0.95rem;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dark .chat-room-name { color: #f1f5f9; }

.chat-room-last {
    font-size: 0.8rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}
.dark .chat-room-last { color: #94a3b8; }

.chat-room-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    flex-shrink: 0;
}
.chat-room-time {
    font-size: 0.7rem;
    color: #94a3b8;
}
.chat-unread-badge {
    background: #3b82f6;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
}

.chat-online-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #22c55e;
    border: 2px solid #fff;
    position: absolute;
    bottom: 12px;
    left: 46px;
}
.dark .chat-online-dot { border-color: #0f172a; }

.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.chat-main-header {
    padding: 16px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 14px;
    background: #fff;
}
.dark .chat-main-header { background: #1e293b; border-bottom-color: #334155; }

.chat-main-header-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.chat-main-header-info { flex: 1; }
.chat-main-header-name {
    font-weight: 700;
    font-size: 1rem;
    color: #0f172a;
}
.dark .chat-main-header-name { color: #f1f5f9; }
.chat-main-header-status {
    font-size: 0.78rem;
    color: #64748b;
}
.dark .chat-main-header-status { color: #94a3b8; }
.chat-main-header-status.online { color: #22c55e; }

.chat-messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    background: #f8fafc;
}
.dark .chat-messages { background: #0f172a; }

.chat-msg-group {
    display: flex;
    gap: 10px;
    max-width: 75%;
    margin-bottom: 4px;
}
.chat-msg-group.self {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.chat-msg-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    margin-top: 4px;
}
.chat-msg-group.self .chat-msg-avatar { display: none; }

.chat-msg-content { display: flex; flex-direction: column; gap: 2px; }

.chat-msg-sender {
    font-size: 0.75rem;
    font-weight: 600;
    color: #3b82f6;
    margin-bottom: 2px;
}
.chat-msg-group.self .chat-msg-sender { display: none; }

.chat-msg-bubble {
    padding: 10px 16px;
    border-radius: 16px;
    font-size: 0.9rem;
    line-height: 1.5;
    color: #0f172a;
    background: #fff;
    border: 1px solid #e2e8f0;
    word-break: break-word;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.dark .chat-msg-bubble { background: #1e293b; border-color: #334155; color: #f1f5f9; }
.chat-msg-group.self .chat-msg-bubble {
    background: #3b82f6;
    color: #fff;
    border-color: #3b82f6;
}
.dark .chat-msg-group.self .chat-msg-bubble { background: #3b82f6; border-color: #3b82f6; }

.chat-msg-time {
    font-size: 0.65rem;
    color: #94a3b8;
    margin-top: 2px;
}
.chat-msg-group.self .chat-msg-time { text-align: right; }

.chat-date-sep {
    text-align: center;
    padding: 12px 0;
    font-size: 0.75rem;
    color: #94a3b8;
    font-weight: 600;
}
.chat-date-sep span {
    background: #e2e8f0;
    padding: 4px 14px;
    border-radius: 99px;
}
.dark .chat-date-sep span { background: #334155; color: #94a3b8; }

.chat-typing {
    font-size: 0.78rem;
    color: #64748b;
    font-style: italic;
    padding: 4px 24px;
    min-height: 24px;
}
.dark .chat-typing { color: #94a3b8; }

.chat-input-area {
    padding: 16px 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
    align-items: flex-end;
    background: #fff;
}
.dark .chat-input-area { background: #1e293b; border-top-color: #334155; }

.chat-input-area textarea {
    flex: 1;
    resize: none;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 0.9rem;
    outline: none;
    font-family: inherit;
    max-height: 120px;
    line-height: 1.4;
    color: #0f172a;
    background: #f8fafc;
    transition: border-color 0.15s;
}
.chat-input-area textarea:focus { border-color: #3b82f6; }
.dark .chat-input-area textarea { background: #0f172a; border-color: #334155; color: #f1f5f9; }
.dark .chat-input-area textarea:focus { border-color: #3b82f6; }

.chat-send-btn {
    width: 44px; height: 44px;
    border-radius: 12px;
    border: none;
    background: #3b82f6;
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: all 0.15s;
    flex-shrink: 0;
}
.chat-send-btn:hover { background: #2563eb; transform: scale(1.05); }
.chat-send-btn:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }

.chat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #94a3b8;
    gap: 12px;
    text-align: center;
    padding: 40px;
}
.chat-empty i { font-size: 48px; color: #cbd5e1; }
.dark .chat-empty i { color: #475569; }
.chat-empty h3 { font-size: 1.2rem; color: #64748b; font-weight: 700; }
.dark .chat-empty h3 { color: #94a3b8; }

.chat-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px);
    z-index: 100;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.chat-modal-overlay.active { display: flex; }

.chat-modal {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 440px;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.dark .chat-modal { background: #1e293b; }

.chat-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.dark .chat-modal-header { border-bottom-color: #334155; }
.chat-modal-header h2 { font-size: 1.1rem; font-weight: 700; color: #0f172a; }
.dark .chat-modal-header h2 { color: #f1f5f9; }
.chat-modal-close {
    width: 32px; height: 32px;
    border: none;
    background: #f1f5f9;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 18px;
}
.chat-modal-close:hover { background: #e2e8f0; }
.dark .chat-modal-close { background: #334155; color: #94a3b8; }

.chat-modal-body { padding: 16px 24px; overflow-y: auto; flex: 1; }

.chat-user-list-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s;
}
.chat-user-list-item:hover { background: #f1f5f9; }
.dark .chat-user-list-item:hover { background: #334155; }

.chat-user-list-avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    object-fit: cover;
    background: #e2e8f0;
}
.chat-user-list-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: #0f172a;
}
.dark .chat-user-list-name { color: #f1f5f9; }
.chat-user-list-sub {
    font-size: 0.75rem;
    color: #94a3b8;
}

.chat-online-panel {
    width: 260px;
    min-width: 260px;
    border-left: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.dark .chat-online-panel { background: #0f172a; border-left-color: #334155; }

.chat-online-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 700;
    font-size: 0.95rem;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dark .chat-online-header { color: #f1f5f9; border-bottom-color: #334155; }

.chat-online-count {
    background: #22c55e;
    color: #fff;
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 99px;
    font-weight: 700;
}

.chat-online-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 12px;
}

.chat-online-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s;
}
.chat-online-item:hover { background: #e2e8f0; }
.dark .chat-online-item:hover { background: #334155; }

.chat-online-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}
.chat-online-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    object-fit: cover;
}
.chat-online-dot-sm {
    width: 8px; height: 8px;
    border-radius: 50%;
    position: absolute;
    bottom: 0; right: 0;
    border: 2px solid #f8fafc;
}
.dark .chat-online-dot-sm { border-color: #0f172a; }
.chat-online-dot-sm.on { background: #22c55e; }
.chat-online-dot-sm.off { background: #94a3b8; }

.chat-online-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dark .chat-online-name { color: #f1f5f9; }
.chat-online-role {
    font-size: 0.7rem;
    color: #94a3b8;
}

.chat-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 99px;
    font-weight: 600;
}
.chat-status-badge.online { background: #dcfce7; color: #16a34a; }
.dark .chat-status-badge.online { background: rgba(34,197,94,0.15); color: #4ade80; }
.chat-status-badge.offline { background: #f1f5f9; color: #94a3b8; }
.dark .chat-status-badge.offline { background: #334155; color: #64748b; }

@media (max-width: 900px) {
    .chat-online-panel { display: none; }
}
@media (max-width: 700px) {
    .chat-sidebar { width: 100%; min-width: 100%; }
    .chat-main { display: none; }
    .chat-wrapper.room-open .chat-sidebar { display: none; }
    .chat-wrapper.room-open .chat-main { display: flex; }
    .chat-back-btn { display: flex !important; }
}

.chat-room-list::-webkit-scrollbar,
.chat-messages::-webkit-scrollbar,
.chat-online-list::-webkit-scrollbar { width: 5px; }
.chat-room-list::-webkit-scrollbar-thumb,
.chat-messages::-webkit-scrollbar-thumb,
.chat-online-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
.dark .chat-room-list::-webkit-scrollbar-thumb,
.dark .chat-messages::-webkit-scrollbar-thumb,
.dark .chat-online-list::-webkit-scrollbar-thumb { background: #475569; }

.chat-conn-status {
    padding: 6px 16px;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    display: none;
}
.chat-conn-status.disconnected {
    display: block;
    background: #fef2f2;
    color: #dc2626;
}
.dark .chat-conn-status.disconnected { background: rgba(239,68,68,0.1); color: #f87171; }
.chat-conn-status.connecting {
    display: block;
    background: #fefce8;
    color: #ca8a04;
}

.chat-ctx-menu {
    position: fixed;
    z-index: 200;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    padding: 6px;
    min-width: 170px;
    display: none;
}
.dark .chat-ctx-menu { background: #1e293b; border-color: #334155; }
.chat-ctx-menu.visible { display: block; }
.chat-ctx-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #334155;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.12s;
}
.chat-ctx-item:hover { background: #f1f5f9; }
.dark .chat-ctx-item { color: #e2e8f0; }
.dark .chat-ctx-item:hover { background: #334155; }
.chat-ctx-item i { width: 16px; text-align: center; font-size: 13px; }
.chat-ctx-item.danger { color: #ef4444; }
.chat-ctx-item.danger:hover { background: #fef2f2; }
.dark .chat-ctx-item.danger:hover { background: rgba(239,68,68,0.1); }

.chat-reply-bar {
    display: none;
    padding: 8px 24px;
    background: #f1f5f9;
    border-top: 1px solid #e2e8f0;
    border-left: 3px solid #3b82f6;
    margin: 0;
    align-items: center;
    gap: 12px;
}
.chat-reply-bar.active { display: flex; }
.dark .chat-reply-bar { background: #1e293b; border-top-color: #334155; }
.chat-reply-bar-content { flex: 1; min-width: 0; }
.chat-reply-bar-name { font-size: 0.78rem; font-weight: 700; color: #3b82f6; }
.chat-reply-bar-text { font-size: 0.8rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 500px; }
.dark .chat-reply-bar-text { color: #94a3b8; }
.chat-reply-bar-close {
    width: 28px; height: 28px;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.chat-reply-bar-close:hover { background: #e2e8f0; color: #64748b; }
.dark .chat-reply-bar-close:hover { background: #334155; }

.chat-msg-reply-ref {
    background: rgba(59,130,246,0.08);
    border-left: 3px solid #3b82f6;
    border-radius: 6px;
    padding: 6px 10px;
    margin-bottom: 6px;
    cursor: pointer;
}
.dark .chat-msg-reply-ref { background: rgba(59,130,246,0.12); }
.chat-msg-reply-ref-name { font-size: 0.72rem; font-weight: 700; color: #3b82f6; }
.chat-msg-reply-ref-text { font-size: 0.78rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px; }
.dark .chat-msg-reply-ref-text { color: #94a3b8; }
.chat-msg-group.self .chat-msg-reply-ref { background: rgba(255,255,255,0.15); border-left-color: rgba(255,255,255,0.5); }
.chat-msg-group.self .chat-msg-reply-ref-name { color: rgba(255,255,255,0.85); }
.chat-msg-group.self .chat-msg-reply-ref-text { color: rgba(255,255,255,0.7); }

.chat-msg-forwarded {
    font-size: 0.7rem;
    color: #94a3b8;
    font-style: italic;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.chat-msg-forwarded i { font-size: 10px; }
.chat-msg-group.self .chat-msg-forwarded { color: rgba(255,255,255,0.7); }

.chat-msg-group { position: relative; }
.chat-msg-kebab {
    position: absolute;
    top: 2px;
    right: -4px;
    width: 26px; height: 26px;
    border: none;
    background: #fff;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    z-index: 10;
    transition: all 0.12s;
}
.chat-msg-group.self .chat-msg-kebab { left: -4px; right: auto; }
.dark .chat-msg-kebab { background: #334155; color: #94a3b8; }
.chat-msg-group:hover .chat-msg-kebab { display: flex; }
.chat-msg-kebab:hover { background: #f1f5f9; color: #334155; transform: scale(1.1); }
.dark .chat-msg-kebab:hover { background: #475569; color: #e2e8f0; }
</style>

<div class="chat-wrapper" id="chatWrapper">
    
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <span class="chat-sidebar-title">Chat</span>
            <div class="chat-sidebar-actions">
                <button onclick="openNewDmModal()" title="Pesan Baru"><i class="fas fa-pen-to-square"></i></button>
                <?php if ($canManage): ?>
                <button onclick="openNewGroupModal()" title="Buat Grup"><i class="fas fa-users"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <div class="chat-search">
            <input type="text" placeholder="Cari percakapan..." id="chatSearchInput" oninput="filterRooms()">
        </div>
        <div id="chatConnStatus" class="chat-conn-status"></div>
        <div class="chat-room-list" id="chatRoomList">
            <div class="chat-empty" style="padding:40px 20px;">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Memuat percakapan...</p>
            </div>
        </div>
    </div>

    
    <div class="chat-main" id="chatMain">
        <div class="chat-empty" id="chatEmptyState">
            <i class="fas fa-comments"></i>
            <h3>Selamat datang di Chat</h3>
            <p>Pilih percakapan dari daftar atau mulai percakapan baru</p>
        </div>

        <div id="chatActiveRoom" style="display:none;flex-direction:column;height:100%;">
            <div class="chat-main-header">
                <button onclick="backToRoomList()" class="chat-send-btn" style="display:none;width:36px;height:36px;background:#f1f5f9;color:#64748b;font-size:14px;" id="chatBackBtn">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <img id="chatRoomAvatar" src="" class="chat-main-header-avatar" alt="">
                <div class="chat-main-header-info">
                    <div class="chat-main-header-name" id="chatRoomName"></div>
                    <div class="chat-main-header-status" id="chatRoomStatus"></div>
                </div>
            </div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-typing" id="chatTyping"></div>
            <div class="chat-reply-bar" id="chatReplyBar">
                <div class="chat-reply-bar-content">
                    <div class="chat-reply-bar-name" id="replyBarName"></div>
                    <div class="chat-reply-bar-text" id="replyBarText"></div>
                </div>
                <button class="chat-reply-bar-close" onclick="cancelReply()" title="Batal"><i class="fas fa-times"></i></button>
            </div>
            <div class="chat-input-area">
                <textarea id="chatInput" placeholder="Tulis pesan..." rows="1" onkeydown="handleInputKeydown(event)"></textarea>
                <button class="chat-send-btn" onclick="sendMessage()" id="chatSendBtn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    
    <div class="chat-online-panel" id="chatOnlinePanel">
        <div class="chat-online-header">
            <i class="fas fa-circle" style="color:#22c55e;font-size:8px;"></i>
            Online
            <span class="chat-online-count" id="onlineCount">0</span>
        </div>
        <div class="chat-online-list" id="onlineList">
            <div style="padding:20px;text-align:center;color:#94a3b8;font-size:0.85rem;">Memuat...</div>
        </div>
    </div>
</div>


<div class="chat-modal-overlay" id="newDmModal">
    <div class="chat-modal">
        <div class="chat-modal-header">
            <h2>Pesan Baru</h2>
            <button class="chat-modal-close" onclick="closeModal('newDmModal')">&times;</button>
        </div>
        <div style="padding:8px 24px;border-bottom:1px solid #e2e8f0;">
            <input type="text" placeholder="Cari nama..." id="dmSearchInput" oninput="filterDmUsers()" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:0.9rem;outline:none;background:#f8fafc;color:#0f172a;">
        </div>
        <div class="chat-modal-body" id="dmUserList" style="max-height:400px;">
            <div style="text-align:center;color:#94a3b8;padding:20px;">Memuat...</div>
        </div>
    </div>
</div>


<?php if ($canManage): ?>
<div class="chat-modal-overlay" id="newGroupModal">
    <div class="chat-modal" style="max-width:500px;">
        <div class="chat-modal-header">
            <h2>Buat Grup Baru</h2>
            <button class="chat-modal-close" onclick="closeModal('newGroupModal')">&times;</button>
        </div>
        <div class="chat-modal-body">
            <div style="margin-bottom:16px;">
                <label style="font-size:0.85rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;">Nama Grup</label>
                <input type="text" id="groupNameInput" placeholder="Nama grup..." style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:0.9rem;outline:none;background:#f8fafc;color:#0f172a;">
            </div>
            <label style="font-size:0.85rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;">Pilih Anggota</label>
            <div id="groupMemberList" style="max-height:300px;overflow-y:auto;"></div>
            <button onclick="createGroup()" style="margin-top:16px;width:100%;padding:12px;border:none;border-radius:10px;background:#3b82f6;color:#fff;font-weight:700;font-size:0.9rem;cursor:pointer;">
                <i class="fas fa-plus"></i> Buat Grup
            </button>
        </div>
    </div>
</div>
<?php endif; ?>


<div class="chat-modal-overlay" id="forwardModal">
    <div class="chat-modal">
        <div class="chat-modal-header">
            <h2>Teruskan Pesan</h2>
            <button class="chat-modal-close" onclick="closeModal('forwardModal')">&times;</button>
        </div>
        <div style="padding:8px 24px;border-bottom:1px solid #e2e8f0;">
            <input type="text" placeholder="Cari percakapan..." id="fwdSearchInput" oninput="filterForwardRooms()" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:0.9rem;outline:none;background:#f8fafc;color:#0f172a;">
        </div>
        <div class="chat-modal-body" id="fwdRoomList" style="max-height:400px;"></div>
    </div>
</div>


<div class="chat-ctx-menu" id="chatCtxMenu">
    <div class="chat-ctx-item" onclick="ctxReply()"><i class="fas fa-reply"></i> Balas</div>
    <div class="chat-ctx-item" onclick="ctxForward()"><i class="fas fa-share"></i> Teruskan</div>
    <div class="chat-ctx-item danger" id="ctxDeleteBtn" onclick="ctxDelete()"><i class="fas fa-trash"></i> Hapus untuk semua</div>
</div>

<script>
const CURRENT_USER = <?= json_encode($currentUser) ?>;
const SESSION_TOKEN = <?= json_encode($sessionId) ?>;
const HANDLER_URL = '<?= BASE_URL ?>app/action/chat-handler.php';
const SOCKET_URL = '<?= $socketUrl ?>';
const canManage = <?= json_encode($canManage) ?>;

let socket = null;
let rooms = [];
let allChatUsers = [];
let currentRoomId = null;
let typingTimeout = null;
let onlineUserIds = new Set();
let replyingTo = null; // { id, sender_name, message }
let forwardingMsgId = null; // message id being forwarded
let ctxTargetMsg = null; // { id, sender_id, message, sender_name }

document.addEventListener('DOMContentLoaded', () => {
    initSocket();
    loadRooms();
    loadAllUsers();
});

function initSocket() {
    const statusEl = document.getElementById('chatConnStatus');

    if (window._globalSocket && window._globalSocket.connected) {
        socket = window._globalSocket;
        console.log('Chat: reusing global socket:', socket.id);
        statusEl.className = 'chat-conn-status';
        statusEl.textContent = '';
        attachSocketListeners(socket, statusEl);
        return;
    }

    socket = io(SOCKET_URL || window.location.origin, {
        auth: {
            token: SESSION_TOKEN,
            userId: parseInt(CURRENT_USER.id)
        },
        transports: ['polling'],
        reconnection: true,
        reconnectionDelay: 3000,
        reconnectionDelayMax: 15000,
        reconnectionAttempts: 8,
        timeout: 8000
    });

    attachSocketListeners(socket, statusEl);
}

function attachSocketListeners(socket, statusEl) {

    socket.on('connect', () => {
        console.log('Socket connected:', socket.id);
        statusEl.className = 'chat-conn-status';
        statusEl.textContent = '';
    });

    socket.on('disconnect', () => {
        statusEl.className = 'chat-conn-status disconnected';
        statusEl.textContent = 'Terputus. Menghubungkan kembali...';
    });

    socket.on('connect_error', (err) => {
        console.error('Socket error:', err.message);
        statusEl.className = 'chat-conn-status disconnected';
        statusEl.textContent = 'Gagal terhubung ke server chat';
    });

    socket.on('new_message', (msg) => {
        updateRoomLastMessage(msg);

        if (currentRoomId === msg.room_id) {
            appendMessage(msg);
            scrollToBottom();
            socket.emit('mark_read', { roomId: msg.room_id });
        } else {
            incrementUnread(msg.room_id);
        }
    });

    socket.on('user_online', (data) => {
        const uid = parseInt(data.userId);
        if (data.online) {
            onlineUserIds.add(uid);
        } else {
            onlineUserIds.delete(uid);
        }
        updateOnlinePanel();
        updateRoomOnlineStatus();
    });

    socket.on('user_typing', (data) => {
        if (data.roomId === currentRoomId && data.userId !== parseInt(CURRENT_USER.id)) {
            const typingEl = document.getElementById('chatTyping');
            if (data.isTyping) {
                typingEl.textContent = data.fullName + ' sedang mengetik...';
            } else {
                typingEl.textContent = '';
            }
        }
    });

    socket.on('message_deleted', (data) => {
        if (data.room_id === currentRoomId) {
            const msgEl = document.querySelector(`[data-msg-id="${data.message_id}"]`);
            if (msgEl) {
                msgEl.style.transition = 'opacity 0.3s, transform 0.3s';
                msgEl.style.opacity = '0';
                msgEl.style.transform = 'scale(0.95)';
                setTimeout(() => msgEl.remove(), 300);
            }
        }
        loadRooms();
    });
}

async function loadRooms() {
    try {
        const res = await fetch(HANDLER_URL + '?action=rooms');
        const json = await res.json();
        if (json.success) {
            rooms = json.data;
            renderRooms();
        }
    } catch (e) {
        console.error('Load rooms error:', e);
    }
}

function renderRooms(filter = '') {
    const container = document.getElementById('chatRoomList');
    const q = filter.toLowerCase();
    const filtered = rooms.filter(r => !q || (r.display_name || '').toLowerCase().includes(q));

    if (!filtered.length) {
        container.innerHTML = '<div class="chat-empty" style="padding:40px 20px;"><i class="fas fa-inbox"></i><p>Belum ada percakapan</p></div>';
        return;
    }

    let html = '';
    filtered.forEach(r => {
        const isActive = r.id == currentRoomId;
        const isDm = r.type === 'direct';
        const otherOnline = isDm && r.other_user && onlineUserIds.has(r.other_user.id);
        const displayName = r.display_name || 'Chat';
        const avatarUrl = isDm && r.other_user?.avatar
            ? r.other_user.avatar
            : '';
        const initial = displayName.charAt(0).toUpperCase();

        let lastMsg = r.last_message || '';
        if (lastMsg.length > 40) lastMsg = lastMsg.substring(0, 40) + '...';
        if (r.last_sender_name && r.last_message) {
            const senderShort = r.last_sender_id == CURRENT_USER.id ? 'Anda' : r.last_sender_name.split(' ')[0];
            lastMsg = senderShort + ': ' + lastMsg;
        }

        const timeStr = r.last_message_at ? formatTimeShort(r.last_message_at) : '';

        html += `
        <div class="chat-room-item ${isActive ? 'active' : ''}" onclick="openRoom(${r.id})" data-room-id="${r.id}">
            ${isDm && avatarUrl
                ? `<img src="${escH(avatarUrl)}" class="chat-room-avatar" onerror="this.style.display='none';" alt="">`
                : `<div class="chat-room-avatar-group">${escH(initial)}</div>`
            }
            ${otherOnline ? '<div class="chat-online-dot"></div>' : ''}
            <div class="chat-room-info">
                <div class="chat-room-name">${escH(displayName)}</div>
                <div class="chat-room-last">${escH(lastMsg) || '<i>Belum ada pesan</i>'}</div>
            </div>
            <div class="chat-room-meta">
                <span class="chat-room-time">${timeStr}</span>
                ${r.unread_count > 0 && !isActive ? `<span class="chat-unread-badge">${r.unread_count}</span>` : ''}
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function filterRooms() {
    renderRooms(document.getElementById('chatSearchInput').value);
}

async function openRoom(roomId) {
    currentRoomId = roomId;
    const wrapper = document.getElementById('chatWrapper');
    wrapper.classList.add('room-open');

    document.getElementById('chatEmptyState').style.display = 'none';
    document.getElementById('chatActiveRoom').style.display = 'flex';

    const room = rooms.find(r => r.id == roomId);
    if (room) {
        const isDm = room.type === 'direct';
        const displayName = room.display_name || 'Chat';
        const avatarUrl = isDm && room.other_user?.avatar ? room.other_user.avatar : '';

        document.getElementById('chatRoomName').textContent = displayName;
        const avatarEl = document.getElementById('chatRoomAvatar');

        if (isDm && avatarUrl) {
            avatarEl.src = avatarUrl;
            avatarEl.style.display = '';
        } else {
            avatarEl.style.display = 'none';
        }

        const statusEl = document.getElementById('chatRoomStatus');
        if (isDm && room.other_user) {
            const isOn = onlineUserIds.has(room.other_user.id);
            statusEl.textContent = isOn ? 'Online' : 'Offline';
            statusEl.className = 'chat-main-header-status' + (isOn ? ' online' : '');
        } else {
            statusEl.textContent = (room.member_count || '') + ' anggota';
            statusEl.className = 'chat-main-header-status';
        }

        room.unread_count = 0;
    }

    renderRooms(document.getElementById('chatSearchInput').value);

    document.getElementById('chatMessages').innerHTML = '<div class="chat-empty"><i class="fas fa-spinner fa-spin"></i></div>';

    try {
        const res = await fetch(HANDLER_URL + '?action=messages&room_id=' + roomId);
        const json = await res.json();
        if (json.success) {
            renderMessages(json.data);
            scrollToBottom();
        }
    } catch (e) {
        document.getElementById('chatMessages').innerHTML = '<div class="chat-empty"><p>Gagal memuat pesan</p></div>';
    }

    if (socket?.connected) {
        socket.emit('join_room', { roomId });
        socket.emit('mark_read', { roomId });
    }

    document.getElementById('chatInput').focus();
}

function renderMessages(messages) {
    const container = document.getElementById('chatMessages');
    if (!messages.length) {
        container.innerHTML = '<div class="chat-empty" style="padding:40px;"><i class="fas fa-hand-peace" style="font-size:36px;"></i><p>Belum ada pesan. Mulai percakapan!</p></div>';
        return;
    }

    let html = '';
    let lastDate = '';
    let lastSenderId = null;

    messages.forEach(m => {
        const dt = new Date(m.created_at);
        const dateStr = formatDate(dt);
        const isSelf = m.sender_id == CURRENT_USER.id;

        if (dateStr !== lastDate) {
            html += `<div class="chat-date-sep"><span>${escH(dateStr)}</span></div>`;
            lastDate = dateStr;
            lastSenderId = null;
        }

        const showAvatar = !isSelf && m.sender_id !== lastSenderId;
        const avatarUrl = m.sender_avatar || 'public/assets/images/avatar-default.png';
        const canDel = isSelf || canManage;

        let replyHtml = '';
        if (m.reply_to_id && m.reply_message) {
            const rpName = m.reply_sender_id == CURRENT_USER.id ? 'Anda' : (m.reply_sender_name || 'User');
            const rpText = (m.reply_message || '').substring(0, 80);
            replyHtml = `<div class="chat-msg-reply-ref" onclick="scrollToMsg(${m.reply_to_id})">
                <div class="chat-msg-reply-ref-name">${escH(rpName)}</div>
                <div class="chat-msg-reply-ref-text">${escH(rpText)}</div>
            </div>`;
        }

        let fwdHtml = '';
        if (m.forwarded_from_id) {
            const fwdName = m.forwarded_sender_name || 'User';
            fwdHtml = `<div class="chat-msg-forwarded"><i class="fas fa-share"></i> Diteruskan dari ${escH(fwdName)}</div>`;
        }

        html += `
        <div class="chat-msg-group ${isSelf ? 'self' : ''}" data-msg-id="${m.id}" data-sender-id="${m.sender_id}" data-msg-text="${escAttr(m.message)}" data-sender-name="${escAttr(m.sender_name)}" oncontextmenu="showCtxMenu(event, this)">
            ${showAvatar
                ? `<img src="${escH(avatarUrl)}" class="chat-msg-avatar" onerror="this.src='public/assets/images/avatar-default.png'" alt="">`
                : (!isSelf ? '<div style="width:32px;flex-shrink:0;"></div>' : '')
            }
            <div class="chat-msg-content">
                ${!isSelf && m.sender_id !== lastSenderId ? `<div class="chat-msg-sender">${escH(m.sender_name)}</div>` : ''}
                ${fwdHtml}
                ${replyHtml}
                <div class="chat-msg-bubble">${linkify(escH(m.message))}</div>
                <div class="chat-msg-time">${formatTimeShort(m.created_at)}</div>
            </div>
            <button class="chat-msg-kebab" onclick="showCtxMenu(event, this.parentElement)"><i class="fas fa-ellipsis-vertical"></i></button>
        </div>`;

        lastSenderId = m.sender_id;
    });
    container.innerHTML = html;
}

function appendMessage(msg) {
    const container = document.getElementById('chatMessages');
    const emptyState = container.querySelector('.chat-empty');
    if (emptyState) container.innerHTML = '';

    const isSelf = msg.sender_id == CURRENT_USER.id;
    const avatarUrl = msg.sender_avatar || 'public/assets/images/avatar-default.png';
    const canDel = isSelf || canManage;

    let replyHtml = '';
    if (msg.reply_to_id && msg.reply_message) {
        const rpName = msg.reply_sender_id == CURRENT_USER.id ? 'Anda' : (msg.reply_sender_name || 'User');
        const rpText = (msg.reply_message || '').substring(0, 80);
        replyHtml = `<div class="chat-msg-reply-ref" onclick="scrollToMsg(${msg.reply_to_id})">
            <div class="chat-msg-reply-ref-name">${escH(rpName)}</div>
            <div class="chat-msg-reply-ref-text">${escH(rpText)}</div>
        </div>`;
    }

    let fwdHtml = '';
    if (msg.forwarded_from_id) {
        const fwdName = msg.forwarded_sender_name || 'User';
        fwdHtml = `<div class="chat-msg-forwarded"><i class="fas fa-share"></i> Diteruskan dari ${escH(fwdName)}</div>`;
    }

    const div = document.createElement('div');
    div.innerHTML = `
    <div class="chat-msg-group ${isSelf ? 'self' : ''}" data-msg-id="${msg.id}" data-sender-id="${msg.sender_id}" data-msg-text="${escAttr(msg.message)}" data-sender-name="${escAttr(msg.sender_name)}" oncontextmenu="showCtxMenu(event, this)">
        ${!isSelf ? `<img src="${escH(avatarUrl)}" class="chat-msg-avatar" onerror="this.src='public/assets/images/avatar-default.png'" alt="">` : ''}
        <div class="chat-msg-content">
            ${!isSelf ? `<div class="chat-msg-sender">${escH(msg.sender_name)}</div>` : ''}
            ${fwdHtml}
            ${replyHtml}
            <div class="chat-msg-bubble">${linkify(escH(msg.message))}</div>
            <div class="chat-msg-time">${formatTimeShort(msg.created_at || new Date().toISOString())}</div>
        </div>
        <button class="chat-msg-kebab" onclick="showCtxMenu(event, this.parentElement)"><i class="fas fa-ellipsis-vertical"></i></button>
    </div>`;
    container.appendChild(div.firstElementChild);

    document.getElementById('chatTyping').textContent = '';
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message || !currentRoomId) return;

    const replyToId = replyingTo ? replyingTo.id : null;

    if (socket?.connected) {
        socket.emit('send_message', { roomId: currentRoomId, message, replyToId }, (resp) => {
            if (!resp?.success) {
                console.error('Send failed:', resp?.message);
            }
        });
    } else {
        const bodyParams = { action: 'send_message', room_id: currentRoomId, message };
        if (replyToId) bodyParams.reply_to_id = replyToId;
        fetch(HANDLER_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(bodyParams)
        }).then(r => r.json()).then(j => {
            if (j.success) {
                appendMessage(j.data);
                scrollToBottom();
            }
        });
    }

    input.value = '';
    input.style.height = 'auto';
    cancelReply();
    socket?.emit('typing', { roomId: currentRoomId, isTyping: false });
}

function handleInputKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
        return;
    }

    const el = e.target;
    setTimeout(() => {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }, 0);

    if (socket?.connected && currentRoomId) {
        socket.emit('typing', { roomId: currentRoomId, isTyping: true });
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            socket.emit('typing', { roomId: currentRoomId, isTyping: false });
        }, 2000);
    }
}

function updateRoomLastMessage(msg) {
    const room = rooms.find(r => r.id == msg.room_id);
    if (room) {
        room.last_message = msg.message;
        room.last_message_at = msg.created_at || new Date().toISOString();
        room.last_sender_id = msg.sender_id;
        room.last_sender_name = msg.sender_name;
        rooms.sort((a, b) => {
            const ta = a.last_message_at || a.created_at || '';
            const tb = b.last_message_at || b.created_at || '';
            return tb.localeCompare(ta);
        });
        renderRooms(document.getElementById('chatSearchInput').value);
    }
}

function incrementUnread(roomId) {
    const room = rooms.find(r => r.id == roomId);
    if (room) {
        room.unread_count = (parseInt(room.unread_count) || 0) + 1;
        renderRooms(document.getElementById('chatSearchInput').value);
    }
}

function updateRoomOnlineStatus() {
    if (!currentRoomId) return;
    const room = rooms.find(r => r.id == currentRoomId);
    if (room && room.type === 'direct' && room.other_user) {
        const isOn = onlineUserIds.has(room.other_user.id);
        const statusEl = document.getElementById('chatRoomStatus');
        statusEl.textContent = isOn ? 'Online' : 'Offline';
        statusEl.className = 'chat-main-header-status' + (isOn ? ' online' : '');
    }
}

async function loadAllUsers() {
    try {
        const res = await fetch(HANDLER_URL + '?action=all_users');
        const json = await res.json();
        if (json.success) {
            allChatUsers = json.data;
            allChatUsers.forEach(u => {
                u.id = parseInt(u.id);
                if (u.is_online == 1) onlineUserIds.add(u.id);
            });
            updateOnlinePanel();

            if (socket?.connected) {
                socket.emit('get_online_users', (resp) => {
                    if (resp?.online) {
                        onlineUserIds = new Set(resp.online.map(id => parseInt(id)));
                        updateOnlinePanel();
                    }
                });
            }
        }
    } catch (e) {
        console.error('Load users error:', e);
    }
}

function updateOnlinePanel() {
    const container = document.getElementById('onlineList');
    const countEl = document.getElementById('onlineCount');

    const sorted = [...allChatUsers].sort((a, b) => {
        const aOn = onlineUserIds.has(a.id) ? 1 : 0;
        const bOn = onlineUserIds.has(b.id) ? 1 : 0;
        if (bOn !== aOn) return bOn - aOn;
        return (a.full_name || '').localeCompare(b.full_name || '');
    });

    const onlineCount = sorted.filter(u => onlineUserIds.has(u.id)).length;
    countEl.textContent = onlineCount;

    let html = '';
    sorted.forEach(u => {
        const isOn = onlineUserIds.has(u.id);
        const roleLabel = { administrator: 'Admin', technician_manager: 'Manager', sales: 'Sales', technician: 'Teknisi', hse: 'HSE' }[u.role] || u.role;
        const avatarUrl = u.avatar || 'public/assets/images/avatar-default.png';

        html += `
        <div class="chat-online-item" onclick="startDm(${u.id})">
            <div class="chat-online-avatar-wrap">
                <img src="${escH(avatarUrl)}" class="chat-online-avatar" onerror="this.src='public/assets/images/avatar-default.png'" alt="">
                <div class="chat-online-dot-sm ${isOn ? 'on' : 'off'}"></div>
            </div>
            <div style="min-width:0;">
                <div class="chat-online-name">${escH(u.full_name)}</div>
                <div class="chat-online-role">${escH(roleLabel)}</div>
            </div>
        </div>`;
    });
    container.innerHTML = html || '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:0.85rem;">Tidak ada user</div>';

    renderRooms(document.getElementById('chatSearchInput').value);
}

function openNewDmModal() {
    document.getElementById('newDmModal').classList.add('active');
    document.getElementById('dmSearchInput').value = '';
    renderDmUserList();
}

function renderDmUserList(filter = '') {
    const container = document.getElementById('dmUserList');
    const q = filter.toLowerCase();
    const filtered = allChatUsers.filter(u =>
        u.id != CURRENT_USER.id && (!q || u.full_name.toLowerCase().includes(q) || u.username.toLowerCase().includes(q))
    );

    if (!filtered.length) {
        container.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:20px;">Tidak ditemukan</div>';
        return;
    }

    let html = '';
    filtered.forEach(u => {
        const isOn = onlineUserIds.has(u.id);
        const avatarUrl = u.avatar || 'public/assets/images/avatar-default.png';
        html += `
        <div class="chat-user-list-item" onclick="startDm(${u.id})">
            <img src="${escH(avatarUrl)}" class="chat-user-list-avatar" onerror="this.src='public/assets/images/avatar-default.png'" alt="">
            <div style="flex:1;min-width:0;">
                <div class="chat-user-list-name">${escH(u.full_name)}</div>
                <div class="chat-user-list-sub">@${escH(u.username)}</div>
            </div>
            <span class="chat-status-badge ${isOn ? 'online' : 'offline'}">${isOn ? 'Online' : 'Offline'}</span>
        </div>`;
    });
    container.innerHTML = html;
}

function filterDmUsers() {
    renderDmUserList(document.getElementById('dmSearchInput').value);
}

async function startDm(targetUserId) {
    closeModal('newDmModal');
    try {
        const res = await fetch(HANDLER_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'create_dm', target_user_id: targetUserId })
        });
        const json = await res.json();
        if (json.success) {
            if (!json.existing) {
                await loadRooms();
            }
            openRoom(json.room_id);
        }
    } catch (e) {
        console.error('Create DM error:', e);
    }
}

function openNewGroupModal() {
    document.getElementById('newGroupModal').classList.add('active');
    document.getElementById('groupNameInput').value = '';
    renderGroupMemberList();
}

function renderGroupMemberList() {
    const container = document.getElementById('groupMemberList');
    let html = '';
    allChatUsers.forEach(u => {
        if (u.id == CURRENT_USER.id) return;
        html += `
        <label class="chat-user-list-item" style="cursor:pointer;">
            <input type="checkbox" value="${u.id}" style="accent-color:#3b82f6;width:18px;height:18px;">
            <div style="min-width:0;">
                <div class="chat-user-list-name">${escH(u.full_name)}</div>
                <div class="chat-user-list-sub">@${escH(u.username)}</div>
            </div>
        </label>`;
    });
    container.innerHTML = html;
}

async function createGroup() {
    const name = document.getElementById('groupNameInput').value.trim();
    const checkboxes = document.querySelectorAll('#groupMemberList input[type="checkbox"]:checked');
    const members = Array.from(checkboxes).map(cb => parseInt(cb.value));

    if (!name) return showToast('Nama grup wajib diisi.', 'warning');
    if (!members.length) return showToast('Pilih minimal 1 anggota.', 'warning');

    try {
        const res = await fetch(HANDLER_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'create_group', name, members: JSON.stringify(members) })
        });
        const json = await res.json();
        if (json.success) {
            closeModal('newGroupModal');
            await loadRooms();
            openRoom(json.room_id);
        } else {
            showToast(json.message || 'Gagal membuat grup.', 'error');
        }
    } catch (e) {
        console.error('Create group error:', e);
    }
}

function backToRoomList() {
    document.getElementById('chatWrapper').classList.remove('room-open');
    currentRoomId = null;
    document.getElementById('chatActiveRoom').style.display = 'none';
    document.getElementById('chatEmptyState').style.display = '';
}

function scrollToBottom() {
    const el = document.getElementById('chatMessages');
    setTimeout(() => { el.scrollTop = el.scrollHeight; }, 50);
}

function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function escH(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function linkify(text) {
    return text.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;">$1</a>');
}

function formatTimeShort(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const isToday = d.toDateString() === now.toDateString();
    if (isToday) {
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return 'Kemarin';
    }
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

function formatDate(d) {
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return 'Hari ini';
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return 'Kemarin';
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

document.querySelectorAll('.chat-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});

function replyToMsg(msgId, senderName, msgText) {
    hideCtxMenu();
    replyingTo = { id: msgId, sender_name: senderName, message: msgText };
    const bar = document.getElementById('chatReplyBar');
    document.getElementById('replyBarName').textContent = senderName;
    document.getElementById('replyBarText').textContent = msgText.substring(0, 100);
    bar.classList.add('active');
    document.getElementById('chatInput').focus();
}

function cancelReply() {
    replyingTo = null;
    document.getElementById('chatReplyBar').classList.remove('active');
}

function scrollToMsg(msgId) {
    const el = document.querySelector(`[data-msg-id="${msgId}"]`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.transition = 'background 0.3s';
        el.style.background = 'rgba(59,130,246,0.08)';
        setTimeout(() => { el.style.background = ''; }, 1500);
    }
}

function openForwardModal(msgId) {
    hideCtxMenu();
    forwardingMsgId = msgId;
    document.getElementById('forwardModal').classList.add('active');
    document.getElementById('fwdSearchInput').value = '';
    renderForwardRoomList();
}

function renderForwardRoomList(filter = '') {
    const container = document.getElementById('fwdRoomList');
    const q = filter.toLowerCase();
    const filtered = rooms.filter(r => r.id != currentRoomId && (!q || (r.display_name || '').toLowerCase().includes(q)));

    if (!filtered.length) {
        container.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:20px;">Tidak ada percakapan lain</div>';
        return;
    }

    let html = '';
    filtered.forEach(r => {
        const displayName = r.display_name || 'Chat';
        const isDm = r.type === 'direct';
        const initial = displayName.charAt(0).toUpperCase();
        const avatarUrl = isDm && r.other_user?.avatar ? r.other_user.avatar : '';

        html += `
        <div class="chat-user-list-item" onclick="forwardToRoom(${r.id})">
            ${isDm && avatarUrl
                ? `<img src="${escH(avatarUrl)}" class="chat-user-list-avatar" onerror="this.src='public/assets/images/avatar-default.png'" alt="">`
                : `<div class="chat-room-avatar-group" style="width:38px;height:38px;font-size:14px;">${escH(initial)}</div>`
            }
            <div style="flex:1;min-width:0;">
                <div class="chat-user-list-name">${escH(displayName)}</div>
                <div class="chat-user-list-sub">${isDm ? 'Chat Pribadi' : r.member_count + ' anggota'}</div>
            </div>
            <i class="fas fa-share" style="color:#94a3b8;font-size:12px;"></i>
        </div>`;
    });
    container.innerHTML = html;
}

function filterForwardRooms() {
    renderForwardRoomList(document.getElementById('fwdSearchInput').value);
}

function forwardToRoom(targetRoomId) {
    if (!forwardingMsgId) return;

    if (socket?.connected) {
        socket.emit('forward_message', { messageId: forwardingMsgId, targetRoomId }, (resp) => {
            if (resp?.success) {
                closeModal('forwardModal');
                forwardingMsgId = null;
                if (targetRoomId !== currentRoomId) {
                    openRoom(targetRoomId);
                }
            } else {
                showToast(resp?.message || 'Gagal meneruskan pesan', 'error');
            }
        });
    } else {
        fetch(HANDLER_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'forward_message', message_id: forwardingMsgId, target_room_id: targetRoomId })
        }).then(r => r.json()).then(j => {
            if (j.success) {
                closeModal('forwardModal');
                forwardingMsgId = null;
                loadRooms();
                openRoom(targetRoomId);
            } else {
                showToast(j.message || 'Gagal meneruskan pesan', 'error');
            }
        });
    }
}

function deleteMsg(msgId) {
    hideCtxMenu();
    if (!confirm('Hapus pesan ini untuk semua orang?')) return;

    if (socket?.connected) {
        socket.emit('delete_message', { messageId: msgId }, (resp) => {
            if (!resp?.success) {
                showToast(resp?.message || 'Gagal menghapus pesan', 'error');
            }
        });
    } else {
        fetch(HANDLER_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'delete_message', message_id: msgId })
        }).then(r => r.json()).then(j => {
            if (j.success) {
                const el = document.querySelector(`[data-msg-id="${msgId}"]`);
                if (el) el.remove();
                loadRooms();
            } else {
                showToast(j.message || 'Gagal menghapus pesan', 'error');
            }
        });
    }
}

function showCtxMenu(e, msgEl) {
    e.preventDefault();
    e.stopPropagation();
    const msgId = parseInt(msgEl.dataset.msgId);
    const senderId = parseInt(msgEl.dataset.senderId);
    const msgText = msgEl.dataset.msgText;
    const senderName = msgEl.dataset.senderName;

    ctxTargetMsg = { id: msgId, sender_id: senderId, message: msgText, sender_name: senderName };

    const isOwn = senderId == parseInt(CURRENT_USER.id);
    document.getElementById('ctxDeleteBtn').style.display = (isOwn || canManage) ? '' : 'none';

    const menu = document.getElementById('chatCtxMenu');
    menu.classList.add('visible');

    let x = e.clientX, y = e.clientY;
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';

    requestAnimationFrame(() => {
        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) menu.style.left = (window.innerWidth - rect.width - 8) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (window.innerHeight - rect.height - 8) + 'px';
    });
}

function hideCtxMenu() {
    document.getElementById('chatCtxMenu').classList.remove('visible');
    ctxTargetMsg = null;
}

function ctxReply() {
    if (!ctxTargetMsg) return;
    replyToMsg(ctxTargetMsg.id, ctxTargetMsg.sender_name, ctxTargetMsg.message);
}

function ctxForward() {
    if (!ctxTargetMsg) return;
    openForwardModal(ctxTargetMsg.id);
}

function ctxDelete() {
    if (!ctxTargetMsg) return;
    deleteMsg(ctxTargetMsg.id);
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.chat-ctx-menu')) hideCtxMenu();
});

function escAttr(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/'/g,'&#39;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
