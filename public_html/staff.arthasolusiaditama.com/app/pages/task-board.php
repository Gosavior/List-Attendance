<?php
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/avatar.php';

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = $_SESSION['role'] ?? '';
$currentName = $_SESSION['full_name'] ?? '';

// Ensure tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        status ENUM('todo','in_progress','review','done') NOT NULL DEFAULT 'todo',
        priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
        created_by INT NOT NULL,
        assigned_to INT NULL,
        deadline DATE NULL,
        completed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_priority (priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS task_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_task_id (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS task_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NOT NULL DEFAULT 0,
        file_type VARCHAR(100) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_task_id (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS task_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        type ENUM('assigned','commented','status_changed','deadline_approaching','mentioned') NOT NULL,
        message VARCHAR(500) NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_unread (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS task_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        action ENUM('created','updated','status_changed','assigned','unassigned','commented','attachment_added','attachment_removed') NOT NULL,
        old_value VARCHAR(255) NULL,
        new_value VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_task_id (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS invitations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(64) NOT NULL UNIQUE,
        role VARCHAR(50) NOT NULL,
        invited_by INT NOT NULL,
        used_by INT NULL,
        expires_at DATETIME NOT NULL,
        is_used TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

// Get users for assignment
$usersStmt = $pdo->query("SELECT id, full_name, role, avatar FROM users WHERE is_active = 1 ORDER BY full_name");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="task-board-container" class="p-4 md:p-6 max-w-full">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Task Board</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola dan pantau progress task tim</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button onclick="TaskBoard.openCreateModal()" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-plus mr-2"></i> Task Baru
            </button>
            <button onclick="TaskBoard.toggleView()" id="btn-toggle-view" class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-list mr-2"></i> List View
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="flex items-center flex-1 min-w-[200px] border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 px-3 py-2 focus-within:ring-2 focus-within:ring-blue-500">
                <i class="fas fa-search text-gray-400 text-sm mr-3"></i>
                <input type="text" id="task-search" placeholder="Cari task..." 
                    class="w-full bg-transparent text-gray-800 dark:text-gray-200 text-sm outline-none placeholder-gray-400"
                    oninput="TaskBoard.debounceSearch()">
            </div>
            <select id="filter-priority" onchange="TaskBoard.loadTasks()" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm">
                <option value="">Semua Priority</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <select id="filter-assignee" onchange="TaskBoard.loadTasks()" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm">
                <option value="">Semua Assignee</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Kanban Board View -->
    <div id="kanban-view" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- To Do Column -->
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                    <h3 class="font-semibold text-gray-700 dark:text-gray-300 text-sm">TO DO</h3>
                    <span id="count-todo" class="bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 text-xs px-2 py-0.5 rounded-full">0</span>
                </div>
            </div>
            <div id="col-todo" class="space-y-3 min-h-[200px]" ondrop="TaskBoard.drop(event,'todo')" ondragover="TaskBoard.allowDrop(event)" ondragenter="TaskBoard.dragEnter(event)" ondragleave="TaskBoard.dragLeave(event)">
            </div>
        </div>

        <!-- In Progress Column -->
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                    <h3 class="font-semibold text-blue-700 dark:text-blue-300 text-sm">IN PROGRESS</h3>
                    <span id="count-in_progress" class="bg-blue-200 dark:bg-blue-800 text-blue-600 dark:text-blue-300 text-xs px-2 py-0.5 rounded-full">0</span>
                </div>
            </div>
            <div id="col-in_progress" class="space-y-3 min-h-[200px]" ondrop="TaskBoard.drop(event,'in_progress')" ondragover="TaskBoard.allowDrop(event)" ondragenter="TaskBoard.dragEnter(event)" ondragleave="TaskBoard.dragLeave(event)">
            </div>
        </div>

        <!-- Review Column -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-4 border border-yellow-200 dark:border-yellow-800">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                    <h3 class="font-semibold text-yellow-700 dark:text-yellow-300 text-sm">REVIEW</h3>
                    <span id="count-review" class="bg-yellow-200 dark:bg-yellow-800 text-yellow-600 dark:text-yellow-300 text-xs px-2 py-0.5 rounded-full">0</span>
                </div>
            </div>
            <div id="col-review" class="space-y-3 min-h-[200px]" ondrop="TaskBoard.drop(event,'review')" ondragover="TaskBoard.allowDrop(event)" ondragenter="TaskBoard.dragEnter(event)" ondragleave="TaskBoard.dragLeave(event)">
            </div>
        </div>

        <!-- Done Column -->
        <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 border border-green-200 dark:border-green-800">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                    <h3 class="font-semibold text-green-700 dark:text-green-300 text-sm">DONE</h3>
                    <span id="count-done" class="bg-green-200 dark:bg-green-800 text-green-600 dark:text-green-300 text-xs px-2 py-0.5 rounded-full">0</span>
                </div>
            </div>
            <div id="col-done" class="space-y-3 min-h-[200px]" ondrop="TaskBoard.drop(event,'done')" ondragover="TaskBoard.allowDrop(event)" ondragenter="TaskBoard.dragEnter(event)" ondragleave="TaskBoard.dragLeave(event)">
            </div>
        </div>
    </div>

    <!-- List View (hidden by default) -->
    <div id="list-view" class="hidden">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Task</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Priority</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Assignee</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Deadline</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Aksi</th>
                    </tr>
                </thead>
                <tbody id="task-list-body" class="divide-y divide-gray-200 dark:divide-gray-700">
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Create/Edit Task Modal -->
<div id="modal-task" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 id="modal-task-title" class="text-lg font-bold text-gray-800 dark:text-white">Task Baru</h2>
                <button onclick="TaskBoard.closeModal('modal-task')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="form-task" onsubmit="TaskBoard.saveTask(event)">
                <input type="hidden" id="task-id" value="">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Judul <span class="text-red-500">*</span></label>
                        <input type="text" id="task-title" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deskripsi</label>
                        <textarea id="task-description" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                            <select id="task-priority" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deadline</label>
                            <input type="date" id="task-deadline" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign ke</label>
                        <select id="task-assignee" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm">
                            <option value="">-- Tidak ada --</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= $u['role'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="TaskBoard.closeModal('modal-task')" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Task Detail Modal -->
<div id="modal-detail" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 id="detail-title" class="text-lg font-bold text-gray-800 dark:text-white"></h2>
                <button onclick="TaskBoard.closeModal('modal-detail')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="detail-content" class="space-y-6">
                <!-- Filled by JS -->
            </div>
        </div>
    </div>
</div>

<script>
const TaskBoard = {
    tasks: [],
    currentView: 'kanban',
    searchTimeout: null,
    actionUrl: 'app/action/task/task-actions.php',

    init() {
        this.loadTasks();
    },

    // ==================== API CALLS ====================
    async api(action, data = {}, method = 'POST') {
        const url = method === 'GET' 
            ? `${this.actionUrl}?action=${action}&${new URLSearchParams(data)}` 
            : this.actionUrl;

        const options = { method };
        if (method === 'POST') {
            const fd = new FormData();
            fd.append('action', action);
            Object.entries(data).forEach(([k, v]) => { if (v !== null && v !== undefined) fd.append(k, v); });
            options.body = fd;
        }

        try {
            const res = await fetch(url, options);
            const json = await res.json();
            if (json.error) {
                this.toast(json.error, 'error');
                return null;
            }
            return json;
        } catch (e) {
            this.toast('Terjadi kesalahan koneksi', 'error');
            return null;
        }
    },

    // ==================== LOAD TASKS ====================
    async loadTasks() {
        const params = {};
        const search = document.getElementById('task-search')?.value;
        const priority = document.getElementById('filter-priority')?.value;
        const assignee = document.getElementById('filter-assignee')?.value;

        if (search) params.search = search;
        if (priority) params.priority = priority;
        if (assignee) params.assignee = assignee;

        const res = await this.api('get_tasks', params, 'GET');
        if (!res) return;

        this.tasks = res.tasks;
        this.renderBoard();
    },

    // ==================== RENDER KANBAN ====================
    renderBoard() {
        const columns = { todo: [], in_progress: [], review: [], done: [] };
        this.tasks.forEach(t => { if (columns[t.status]) columns[t.status].push(t); });

        Object.entries(columns).forEach(([status, tasks]) => {
            const col = document.getElementById(`col-${status}`);
            const count = document.getElementById(`count-${status}`);
            if (count) count.textContent = tasks.length;
            if (!col) return;

            col.innerHTML = tasks.map(t => this.renderCard(t)).join('');
        });

        this.renderList();
    },

    renderCard(task) {
        const priorityColors = {
            urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
        };
        const priorityLabels = { urgent: 'Urgent', high: 'High', medium: 'Medium', low: 'Low' };

        const deadlineHtml = task.deadline ? `<span class="text-xs text-gray-500 dark:text-gray-400"><i class="fas fa-calendar mr-1"></i>${this.formatDate(task.deadline)}</span>` : '';
        const assigneeHtml = task.assignee_name ? `<span class="text-xs text-gray-500 dark:text-gray-400"><i class="fas fa-user mr-1"></i>${this.escHtml(task.assignee_name)}</span>` : '';
        const isOverdue = task.deadline && task.status !== 'done' && new Date(task.deadline) < new Date();

        return `<div class="bg-white dark:bg-gray-700 rounded-lg p-3 shadow-sm border border-gray-200 dark:border-gray-600 cursor-pointer hover:shadow-md transition-shadow ${isOverdue ? 'border-l-4 border-l-red-500' : ''}" 
                    draggable="true" 
                    ondragstart="TaskBoard.dragStart(event, ${task.id})" 
                    onclick="TaskBoard.openDetail(${task.id})">
            <div class="flex items-start justify-between mb-2">
                <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200 line-clamp-2">${this.escHtml(task.title)}</h4>
            </div>
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium ${priorityColors[task.priority]}">${priorityLabels[task.priority]}</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    ${deadlineHtml}
                    ${assigneeHtml}
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-400">
                    ${task.comment_count > 0 ? `<span><i class="fas fa-comment"></i> ${task.comment_count}</span>` : ''}
                    ${task.attachment_count > 0 ? `<span><i class="fas fa-paperclip"></i> ${task.attachment_count}</span>` : ''}
                </div>
            </div>
        </div>`;
    },

    renderList() {
        const tbody = document.getElementById('task-list-body');
        if (!tbody) return;

        const statusLabels = { todo: 'To Do', in_progress: 'In Progress', review: 'Review', done: 'Done' };
        const statusColors = {
            todo: 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300',
            in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            review: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            done: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
        };
        const priorityColors = {
            urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
        };

        tbody.innerHTML = this.tasks.map(t => `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer" onclick="TaskBoard.openDetail(${t.id})">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-800 dark:text-gray-200">${this.escHtml(t.title)}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">oleh ${this.escHtml(t.creator_name || '-')}</div>
                </td>
                <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full font-medium ${statusColors[t.status]}">${statusLabels[t.status]}</span></td>
                <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full font-medium ${priorityColors[t.priority]}">${t.priority}</span></td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">${this.escHtml(t.assignee_name || '-')}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">${t.deadline ? this.formatDate(t.deadline) : '-'}</td>
                <td class="px-4 py-3">
                    <button onclick="event.stopPropagation(); TaskBoard.openEditModal(${t.id})" class="text-blue-500 hover:text-blue-700 mr-2"><i class="fas fa-edit"></i></button>
                    <button onclick="event.stopPropagation(); TaskBoard.deleteTask(${t.id})" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
    },

    dragStart(e, taskId) {
        e.dataTransfer.setData('text/plain', taskId);
        e.target.classList.add('opacity-50');
    },

    allowDrop(e) { e.preventDefault(); },

    dragEnter(e) {
        e.preventDefault();
        e.currentTarget.classList.add('ring-2', 'ring-blue-400', 'ring-inset');
    },

    dragLeave(e) {
        e.currentTarget.classList.remove('ring-2', 'ring-blue-400', 'ring-inset');
    },

    async drop(e, newStatus) {
        e.preventDefault();
        e.currentTarget.classList.remove('ring-2', 'ring-blue-400', 'ring-inset');
        const taskId = e.dataTransfer.getData('text/plain');
        if (!taskId) return;

        const res = await this.api('change_status', { task_id: taskId, status: newStatus });
        if (res && res.success) {
            this.loadTasks();
            this.toast('Status berhasil diubah', 'success');
        }
    },

    openCreateModal() {
        document.getElementById('modal-task-title').textContent = 'Task Baru';
        document.getElementById('task-id').value = '';
        document.getElementById('form-task').reset();
        document.getElementById('task-priority').value = 'medium';
        this.openModal('modal-task');
    },

    async openEditModal(taskId) {
        const res = await this.api('get_task', { id: taskId }, 'GET');
        if (!res || !res.task) return;

        const t = res.task;
        document.getElementById('modal-task-title').textContent = 'Edit Task';
        document.getElementById('task-id').value = t.id;
        document.getElementById('task-title').value = t.title;
        document.getElementById('task-description').value = t.description || '';
        document.getElementById('task-priority').value = t.priority;
        document.getElementById('task-deadline').value = t.deadline || '';
        document.getElementById('task-assignee').value = t.assigned_to || '';
        this.openModal('modal-task');
    },

    async openDetail(taskId) {
        const res = await this.api('get_task', { id: taskId }, 'GET');
        if (!res || !res.task) return;

        const t = res.task;
        const statusLabels = { todo: 'To Do', in_progress: 'In Progress', review: 'Review', done: 'Done' };
        const priorityLabels = { urgent: 'Urgent', high: 'High', medium: 'Medium', low: 'Low' };

        document.getElementById('detail-title').textContent = t.title;

        let html = `
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-500 dark:text-gray-400">Status:</span> <span class="font-medium">${statusLabels[t.status]}</span></div>
                <div><span class="text-gray-500 dark:text-gray-400">Priority:</span> <span class="font-medium">${priorityLabels[t.priority]}</span></div>
                <div><span class="text-gray-500 dark:text-gray-400">Dibuat oleh:</span> <span class="font-medium">${this.escHtml(t.creator_name)}</span></div>
                <div><span class="text-gray-500 dark:text-gray-400">Ditugaskan ke:</span> <span class="font-medium">${this.escHtml(t.assignee_name || 'Belum ada')}</span></div>
                <div><span class="text-gray-500 dark:text-gray-400">Deadline:</span> <span class="font-medium">${t.deadline ? this.formatDate(t.deadline) : '-'}</span></div>
                <div><span class="text-gray-500 dark:text-gray-400">Dibuat:</span> <span class="font-medium">${this.formatDateTime(t.created_at)}</span></div>
            </div>
            ${t.description ? `<div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4"><p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${this.escHtml(t.description)}</p></div>` : ''}

            <!-- Status Change -->
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-600 dark:text-gray-400">Ubah status:</span>
                <select onchange="TaskBoard.changeStatus(${t.id}, this.value)" class="text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                    <option value="todo" ${t.status==='todo'?'selected':''}>To Do</option>
                    <option value="in_progress" ${t.status==='in_progress'?'selected':''}>In Progress</option>
                    <option value="review" ${t.status==='review'?'selected':''}>Review</option>
                    <option value="done" ${t.status==='done'?'selected':''}>Done</option>
                </select>
                <button onclick="TaskBoard.openEditModal(${t.id}); TaskBoard.closeModal('modal-detail');" class="ml-auto text-sm px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg"><i class="fas fa-edit mr-1"></i>Edit</button>
                <button onclick="TaskBoard.deleteTask(${t.id})" class="text-sm px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg"><i class="fas fa-trash mr-1"></i>Hapus</button>
            </div>

            <!-- Attachments -->
            <div>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><i class="fas fa-paperclip mr-1"></i>Lampiran (${res.attachments.length})</h3>
                <div class="space-y-2">
                    ${res.attachments.map(a => `
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                            <a href="${a.file_path}" target="_blank" class="text-sm text-blue-600 dark:text-blue-400 hover:underline"><i class="fas fa-file mr-1"></i>${this.escHtml(a.file_name)}</a>
                            <button onclick="TaskBoard.deleteAttachment(${a.id}, ${t.id})" class="text-red-400 hover:text-red-600 text-xs"><i class="fas fa-trash"></i></button>
                        </div>
                    `).join('')}
                </div>
                <form onsubmit="TaskBoard.uploadAttachment(event, ${t.id})" class="mt-2 flex gap-2">
                    <input type="file" id="attachment-file-${t.id}" class="text-sm text-gray-600 dark:text-gray-400 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-400" required>
                    <button type="submit" class="px-3 py-1 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded-lg">Upload</button>
                </form>
            </div>

            <!-- Comments -->
            <div>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><i class="fas fa-comments mr-1"></i>Komentar (${res.comments.length})</h3>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    ${res.comments.map(c => `
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">${this.escHtml(c.full_name)}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-400">${this.formatDateTime(c.created_at)}</span>
                                    <button onclick="TaskBoard.deleteComment(${c.id}, ${t.id})" class="text-red-400 hover:text-red-600 text-xs"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">${this.escHtml(c.comment)}</p>
                        </div>
                    `).join('')}
                </div>
                <form onsubmit="TaskBoard.addComment(event, ${t.id})" class="mt-3 flex gap-2">
                    <input type="text" id="comment-input-${t.id}" placeholder="Tulis komentar..." required class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg">Kirim</button>
                </form>
            </div>

            <!-- Activity Log -->
            <div>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2"><i class="fas fa-history mr-1"></i>Aktivitas</h3>
                <div class="space-y-2 max-h-40 overflow-y-auto">
                    ${res.activity.map(a => `
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <i class="fas fa-circle text-[4px]"></i>
                            <span class="font-medium">${this.escHtml(a.full_name)}</span>
                            <span>${this.getActivityText(a)}</span>
                            <span class="ml-auto">${this.formatDateTime(a.created_at)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;

        document.getElementById('detail-content').innerHTML = html;
        this.openModal('modal-detail');
    },

    openModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },

    closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    },

    async saveTask(e) {
        e.preventDefault();
        const taskId = document.getElementById('task-id').value;
        const data = {
            title: document.getElementById('task-title').value,
            description: document.getElementById('task-description').value,
            priority: document.getElementById('task-priority').value,
            deadline: document.getElementById('task-deadline').value,
            assigned_to: document.getElementById('task-assignee').value
        };

        if (taskId) data.task_id = taskId;

        const action = taskId ? 'update_task' : 'create_task';
        const res = await this.api(action, data);
        if (res && res.success) {
            this.closeModal('modal-task');
            this.loadTasks();
            this.toast(res.message, 'success');
        }
    },

    async changeStatus(taskId, status) {
        const res = await this.api('change_status', { task_id: taskId, status });
        if (res && res.success) {
            this.loadTasks();
            this.closeModal('modal-detail');
            this.toast('Status berhasil diubah', 'success');
        }
    },

    async deleteTask(taskId) {
        if (!confirm('Yakin ingin menghapus task ini?')) return;
        const res = await this.api('delete_task', { task_id: taskId });
        if (res && res.success) {
            this.closeModal('modal-detail');
            this.loadTasks();
            this.toast('Task berhasil dihapus', 'success');
        }
    },

    async addComment(e, taskId) {
        e.preventDefault();
        const input = document.getElementById(`comment-input-${taskId}`);
        const res = await this.api('add_comment', { task_id: taskId, comment: input.value });
        if (res && res.success) {
            this.openDetail(taskId);
            this.toast('Komentar ditambahkan', 'success');
        }
    },

    async deleteComment(commentId, taskId) {
        if (!confirm('Hapus komentar ini?')) return;
        const res = await this.api('delete_comment', { comment_id: commentId });
        if (res && res.success) {
            this.openDetail(taskId);
        }
    },

    async uploadAttachment(e, taskId) {
        e.preventDefault();
        const fileInput = document.getElementById(`attachment-file-${taskId}`);
        if (!fileInput.files[0]) return;

        const fd = new FormData();
        fd.append('action', 'upload_attachment');
        fd.append('task_id', taskId);
        fd.append('file', fileInput.files[0]);

        try {
            const res = await fetch(this.actionUrl, { method: 'POST', body: fd });
            const json = await res.json();
            if (json.error) { this.toast(json.error, 'error'); return; }
            this.openDetail(taskId);
            this.toast('File berhasil diupload', 'success');
        } catch (e) {
            this.toast('Gagal upload file', 'error');
        }
    },

    async deleteAttachment(attachmentId, taskId) {
        if (!confirm('Hapus lampiran ini?')) return;
        const res = await this.api('delete_attachment', { attachment_id: attachmentId });
        if (res && res.success) {
            this.openDetail(taskId);
        }
    },

    // ==================== VIEW TOGGLE ====================
    toggleView() {
        const kanban = document.getElementById('kanban-view');
        const list = document.getElementById('list-view');
        const btn = document.getElementById('btn-toggle-view');

        if (this.currentView === 'kanban') {
            kanban.classList.add('hidden');
            list.classList.remove('hidden');
            btn.innerHTML = '<i class="fas fa-columns mr-2"></i> Board View';
            this.currentView = 'list';
        } else {
            kanban.classList.remove('hidden');
            list.classList.add('hidden');
            btn.innerHTML = '<i class="fas fa-list mr-2"></i> List View';
            this.currentView = 'kanban';
        }
    },

    // ==================== HELPERS ====================
    debounceSearch() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => this.loadTasks(), 300);
    },

    formatDate(d) {
        if (!d) return '-';
        const date = new Date(d);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    formatDateTime(d) {
        if (!d) return '-';
        const date = new Date(d);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    },

    getActivityText(a) {
        const map = {
            created: 'membuat task',
            updated: 'mengupdate task',
            status_changed: `mengubah status dari ${a.old_value || '-'} ke ${a.new_value || '-'}`,
            assigned: 'menugaskan task',
            commented: 'berkomentar',
            attachment_added: `menambah file: ${a.new_value || ''}`,
            attachment_removed: `menghapus file: ${a.old_value || ''}`
        };
        return map[a.action] || a.action;
    },

    escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    toast(msg, type = 'info') {
        if (typeof showToast === 'function') {
            showToast(msg, type);
        } else {
            alert(msg);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => TaskBoard.init());
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    TaskBoard.init();
}
</script>
