<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403);
    exit('Akses ditolak.');
}

function getToolPhotoPath($tool_photo, $user_id)
{
    if (empty($tool_photo)) return null;
    $tool_photo = ltrim($tool_photo, './');
    if (preg_match('#^https?://#i', $tool_photo)) return $tool_photo;
    if (strpos($tool_photo, 'public/assets/') === 0) return $tool_photo;
    if (strpos($tool_photo, 'public/') === 0) return str_replace('public/', 'public/assets/', $tool_photo);
    if (strpos($tool_photo, '/') === false) return 'public/assets/uploads/tools/personal/' . $user_id . '/' . $tool_photo;
    if (strpos($tool_photo, 'uploads/') !== false && strpos($tool_photo, 'public/assets/') !== 0) return 'public/assets/' . $tool_photo;
    return 'public/assets/' . $tool_photo;
}

$current_month = date('Y-m');
$selected_month = $_GET['month'] ?? $current_month;
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) $selected_month = $current_month;

$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
if ($selected_user_id <= 0) $selected_user_id = null;

// Get all technicians with tools
$all_stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.username, u.avatar,
           (SELECT COUNT(*) FROM tool_assignments ta JOIN tools t ON ta.tool_id=t.id WHERE ta.user_id=u.id AND t.tool_type='personal') as total_tools,
           mc.id as check_id, mc.checked_at,
           (SELECT COUNT(*) FROM monthly_check_items mci WHERE mci.check_id=mc.id) as checked_tools
    FROM users u
    LEFT JOIN monthly_checks mc ON mc.user_id = u.id AND mc.check_month = ?
    WHERE u.role IN ('technician', 'technician_manager')
      AND u.is_active = 1
      AND EXISTS (
          SELECT 1 FROM tool_assignments ta2
          JOIN tools t2 ON t2.id = ta2.tool_id AND t2.tool_type = 'personal'
          WHERE ta2.user_id = u.id
      )
    ORDER BY u.full_name
");
$all_stmt->execute([$selected_month]);
$all_technicians = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$overview_done = 0; $overview_pending = 0;
foreach ($all_technicians as $ov) {
    if (!empty($ov['checked_at'])) $overview_done++;
    else $overview_pending++;
}

// If user selected, get their tools
$selected_user = null;
$user_tools = [];
$monthly_check_id = null;
$monthly_check_checked_at = null;

if ($selected_user_id) {
    $user_stmt = $pdo->prepare('SELECT id, full_name, username FROM users WHERE id = ?');
    $user_stmt->execute([$selected_user_id]);
    $selected_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($selected_user) {
        $check_stmt = $pdo->prepare('SELECT id, checked_at FROM monthly_checks WHERE user_id = ? AND check_month = ?');
        $check_stmt->execute([$selected_user_id, $selected_month]);
        $monthly_check = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($monthly_check) {
            $monthly_check_id = (int)$monthly_check['id'];
            $monthly_check_checked_at = $monthly_check['checked_at'];
        }

        $existing_checks = [];
        if ($monthly_check_id) {
            $items_stmt = $pdo->prepare('SELECT tool_id, status, notes FROM monthly_check_items WHERE check_id = ?');
            $items_stmt->execute([$monthly_check_id]);
            $existing_checks = array_column($items_stmt->fetchAll(PDO::FETCH_ASSOC), null, 'tool_id');
        }

        $tools_stmt = $pdo->prepare("
            SELECT t.id, t.name, t.code, t.photo_path, t.condition_notes
            FROM tools t
            INNER JOIN tool_assignments ta ON t.id = ta.tool_id
            WHERE ta.user_id = ? AND t.tool_type = 'personal'
            ORDER BY t.name
        ");
        $tools_stmt->execute([$selected_user_id]);
        $tool_rows = $tools_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tool_rows as $row) {
            $tool_id = (int)$row['id'];
            $check = $existing_checks[$tool_id] ?? null;
            $user_tools[] = [
                'id' => $tool_id,
                'name' => $row['name'],
                'code' => $row['code'],
                'photo_path' => getToolPhotoPath($row['photo_path'] ?? '', $selected_user_id),
                'condition_notes' => $row['condition_notes'] ?? '',
                'status' => $check['status'] ?? null,
                'notes' => $check['notes'] ?? '',
            ];
        }

        // Reopen if new tools added
        $tool_count = count($user_tools);
        $completed_count = count(array_filter($user_tools, fn($t) => !empty($t['status'])));
        $remaining_count = $tool_count - $completed_count;

        if ($monthly_check_id && $monthly_check_checked_at && $remaining_count > 0) {
            $pdo->prepare('UPDATE monthly_checks SET checked_at = NULL WHERE id = ?')->execute([$monthly_check_id]);
            $monthly_check_checked_at = null;
        }
    }
}

$tool_count = count($user_tools);
$completed_count = count(array_filter($user_tools, fn($t) => !empty($t['status'])));
$remaining_count = $tool_count - $completed_count;
$is_finalized = !empty($monthly_check_checked_at) && $remaining_count === 0;
$can_finalize = $monthly_check_id && $remaining_count === 0 && !$is_finalized;

$tools_json = json_encode($user_tools, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>

<!-- HEADER -->
<div class="cmt-header">
    <div class="cmt-title-row">
        <h1><i class="fas fa-clipboard-check"></i> Pengecekan Tools Bulanan</h1>
        <div class="cmt-month-picker">
            <form method="GET" action="dashboard.php" class="cmt-month-form">
                <input type="hidden" name="page" value="check-monthly-tools">
                <?php if ($selected_user_id): ?><input type="hidden" name="user_id" value="<?= $selected_user_id ?>"><?php endif; ?>
                <input type="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" onchange="this.form.submit()" class="cmt-input">
            </form>
            <a href="app/action/export_monthly_check.php?month=<?= htmlspecialchars($selected_month) ?>" class="cmt-btn cmt-btn-export"><i class="fas fa-file-excel"></i> Export</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="cmt-alert cmt-alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="cmt-alert cmt-alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Stats -->
    <div class="cmt-stats">
        <div class="cmt-stat"><span class="cmt-stat-num"><?= count($all_technicians) ?></span><span class="cmt-stat-label">Total</span></div>
        <div class="cmt-stat cmt-stat-done"><span class="cmt-stat-num"><?= $overview_done ?></span><span class="cmt-stat-label">Selesai</span></div>
        <div class="cmt-stat cmt-stat-pending"><span class="cmt-stat-num"><?= $overview_pending ?></span><span class="cmt-stat-label">Belum</span></div>
    </div>
</div>

<!-- TECHNICIAN GRID -->
<div class="cmt-grid">
    <?php foreach ($all_technicians as $tech):
        $total = (int)($tech['total_tools'] ?? 0);
        $checked = (int)($tech['checked_tools'] ?? 0);
        $isDone = !empty($tech['checked_at']);
        $pct = $total > 0 ? round(($checked / $total) * 100) : 0;
        $isActive = $selected_user_id === (int)$tech['id'];
        $cardClass = $isDone ? 'cmt-card-done' : ($isActive ? 'cmt-card-active' : '');
    ?>
    <a href="dashboard.php?page=check-monthly-tools&user_id=<?= (int)$tech['id'] ?>&month=<?= htmlspecialchars($selected_month) ?>"
       class="cmt-card <?= $cardClass ?>">
        <div class="cmt-card-top">
            <div class="cmt-card-avatar">
                <?php if ($isDone): ?>
                    <i class="fas fa-check-circle cmt-card-check"></i>
                <?php endif; ?>
                <span><?= strtoupper(substr($tech['full_name'], 0, 1)) ?></span>
            </div>
            <div class="cmt-card-info">
                <strong><?= htmlspecialchars($tech['full_name']) ?></strong>
                <small><?= $checked ?>/<?= $total ?> tools</small>
            </div>
        </div>
        <div class="cmt-card-bar">
            <div class="cmt-card-bar-fill <?= $isDone ? 'cmt-bar-done' : '' ?>" style="width:<?= $pct ?>%"></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- TOOLS CHECK SECTION -->
<?php if ($selected_user && $tool_count > 0): ?>
<div class="cmt-workspace">
    <div class="cmt-ws-header">
        <div>
            <h2><?= htmlspecialchars($selected_user['full_name']) ?></h2>
            <p><?= $completed_count ?>/<?= $tool_count ?> tools dicek
                <?php if ($is_finalized): ?><span class="cmt-badge-done">Finalized</span><?php endif; ?>
            </p>
        </div>
        <div class="cmt-ws-actions">
            <?php if (!$is_finalized): ?>
            <button type="button" id="setAllGoodBtn" class="cmt-btn cmt-btn-good"><i class="fas fa-check-double"></i> Set All Good</button>
            <?php endif; ?>
            <?php if ($can_finalize): ?>
            <form method="POST" action="app/action/finalize_monthly_check.php" onsubmit="return confirm('Yakin finalisasi pengecekan?')" style="display:inline">
                <input type="hidden" name="user_id" value="<?= (int)$selected_user_id ?>">
                <input type="hidden" name="month" value="<?= htmlspecialchars($selected_month) ?>">
                <button type="submit" class="cmt-btn cmt-btn-finalize"><i class="fas fa-flag-checkered"></i> Finalisasi</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progress bar -->
    <div class="cmt-progress">
        <div class="cmt-progress-fill" style="width:<?= $tool_count > 0 ? round(($completed_count/$tool_count)*100) : 0 ?>%"></div>
    </div>

    <!-- Tools Table -->
    <div class="cmt-tools-list">
        <?php foreach ($user_tools as $i => $tool):
            $st = $tool['status'] ?? '';
            $stClass = $st ? 'cmt-tool-' . strtolower($st) : 'cmt-tool-unchecked';
        ?>
        <div class="cmt-tool-row <?= $stClass ?>" data-tool-id="<?= $tool['id'] ?>" data-status="<?= htmlspecialchars($st) ?>">
            <div class="cmt-tool-info">
                <div class="cmt-tool-name"><?= htmlspecialchars($tool['name']) ?></div>
                <div class="cmt-tool-code"><?= htmlspecialchars($tool['code']) ?></div>
            </div>
            <div class="cmt-tool-status-btns" <?= $is_finalized ? 'style="pointer-events:none;opacity:0.6"' : '' ?>>
                <button type="button" data-set-status="Good" class="cmt-sbtn cmt-sbtn-good <?= $st==='Good'?'active':'' ?>"><i class="fas fa-check"></i></button>
                <button type="button" data-set-status="Repair" class="cmt-sbtn cmt-sbtn-repair <?= $st==='Repair'?'active':'' ?>"><i class="fas fa-wrench"></i></button>
                <button type="button" data-set-status="Missing" class="cmt-sbtn cmt-sbtn-missing <?= $st==='Missing'?'active':'' ?>"><i class="fas fa-times"></i></button>
            </div>
            <div class="cmt-tool-notes-wrap <?= $is_finalized ? 'cmt-readonly' : '' ?>">
                <input type="text" class="cmt-tool-notes" data-notes placeholder="Catatan..." value="<?= htmlspecialchars($tool['notes'] ?? '') ?>" <?= $is_finalized ? 'readonly' : '' ?>>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$is_finalized && $can_finalize): ?>
    <div class="cmt-finalize-bottom">
        <form method="POST" action="app/action/finalize_monthly_check.php" onsubmit="return confirm('Yakin finalisasi pengecekan?')">
            <input type="hidden" name="user_id" value="<?= (int)$selected_user_id ?>">
            <input type="hidden" name="month" value="<?= htmlspecialchars($selected_month) ?>">
            <button type="submit" class="cmt-btn cmt-btn-finalize cmt-btn-lg"><i class="fas fa-flag-checkered"></i> Finalisasi Pengecekan</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php elseif ($selected_user && $tool_count === 0): ?>
<div class="cmt-empty"><i class="fas fa-box-open"></i> <?= htmlspecialchars($selected_user['full_name']) ?> belum memiliki tools personal.</div>
<?php elseif (!$selected_user_id): ?>
<div class="cmt-empty"><i class="fas fa-hand-pointer"></i> Pilih teknisi di atas untuk mulai pengecekan.</div>
<?php endif; ?>

<!-- JAVASCRIPT -->
<script>
(function() {
    const tools = <?= $tools_json ?>;
    const userId = <?= $selected_user_id ? (int)$selected_user_id : 'null' ?>;
    const month = '<?= htmlspecialchars($selected_month) ?>';
    const isFinalized = <?= $is_finalized ? 'true' : 'false' ?>;

    if (isFinalized || !tools.length) return;

    const _toast = window.showToast || function(msg) { /* silent */ };

    // Inline status buttons
    document.querySelectorAll('.cmt-tool-row').forEach(row => {
        const toolId = parseInt(row.dataset.toolId);
        const tool = tools.find(t => t.id === toolId);
        if (!tool) return;

        row.querySelectorAll('[data-set-status]').forEach(btn => {
            btn.addEventListener('click', async function() {
                const status = this.dataset.setStatus;
                const notesInput = row.querySelector('[data-notes]');
                const notes = notesInput ? notesInput.value : '';

                // Visual feedback
                row.querySelectorAll('[data-set-status]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                this.disabled = true;

                try {
                    const payload = new URLSearchParams({ tool_id: toolId, status, notes, month });
                    if (userId) payload.append('user_id', userId);

                    const res = await fetch('app/action/update_tool_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: payload
                    });
                    const result = await res.json();

                    if (result.success) {
                        tool.status = status;
                        tool.notes = notes;
                        row.dataset.status = status;
                        row.className = 'cmt-tool-row cmt-tool-' + status.toLowerCase();
                        updateProgress();
                        _toast(tool.name + ' → ' + status, 'success');
                    } else {
                        throw new Error(result.error || 'Gagal');
                    }
                } catch(e) {
                    _toast('Error: ' + e.message, 'error');
                    // revert
                    this.classList.remove('active');
                    if (tool.status) {
                        row.querySelector('[data-set-status="' + tool.status + '"]').classList.add('active');
                    }
                } finally {
                    this.disabled = false;
                }
            });
        });

        // Notes blur save
        const notesInput = row.querySelector('[data-notes]');
        if (notesInput) {
            notesInput.addEventListener('blur', async function() {
                if (!tool.status) return;
                const notes = this.value;
                if (notes === (tool.notes || '')) return;

                const payload = new URLSearchParams({ tool_id: toolId, status: tool.status, notes, month });
                if (userId) payload.append('user_id', userId);

                try {
                    const res = await fetch('app/action/update_tool_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: payload
                    });
                    const result = await res.json();
                    if (result.success) tool.notes = notes;
                } catch(e) {}
            });
        }
    });

    // Set All Good
    const setAllBtn = document.getElementById('setAllGoodBtn');
    if (setAllBtn) {
        setAllBtn.addEventListener('click', async function() {
            const unchecked = tools.filter(t => !t.status);
            if (!unchecked.length) { _toast('Semua sudah dicek', 'info'); return; }
            if (!confirm('Set ' + unchecked.length + ' tools menjadi Good?')) return;

            setAllBtn.disabled = true;
            setAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            for (const tool of unchecked) {
                try {
                    const payload = new URLSearchParams({ tool_id: tool.id, status: 'Good', notes: '', month });
                    if (userId) payload.append('user_id', userId);
                    const res = await fetch('app/action/update_tool_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: payload
                    });
                    const result = await res.json();
                    if (result.success) {
                        tool.status = 'Good';
                        tool.notes = '';
                        const row = document.querySelector('[data-tool-id="' + tool.id + '"]');
                        if (row) {
                            row.className = 'cmt-tool-row cmt-tool-good';
                            row.dataset.status = 'Good';
                            row.querySelectorAll('[data-set-status]').forEach(b => b.classList.remove('active'));
                            row.querySelector('[data-set-status="Good"]').classList.add('active');
                        }
                    }
                } catch(e) {}
            }

            updateProgress();
            setAllBtn.disabled = false;
            setAllBtn.innerHTML = '<i class="fas fa-check-double"></i> Set All Good';
            _toast('Selesai!', 'success');
        });
    }

    function updateProgress() {
        const done = tools.filter(t => t.status).length;
        const total = tools.length;
        const pct = total > 0 ? Math.round((done/total)*100) : 0;
        const bar = document.querySelector('.cmt-progress-fill');
        if (bar) bar.style.width = pct + '%';

        const info = document.querySelector('.cmt-ws-header p');
        if (info) {
            info.innerHTML = done + '/' + total + ' tools dicek';
            if (done === total) {
                info.innerHTML += ' <span class="cmt-badge-done">Siap Finalisasi</span>';
                // Reload to show finalize button
                if (done === total && !document.querySelector('.cmt-btn-finalize')) {
                    location.reload();
                }
            }
        }
    }
})();
</script>

<!-- CSS -->
<style>
/* Reset for this page */
.cmt-header { margin-bottom: 1.5rem; }
.cmt-title-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; }
.cmt-title-row h1 { font-size: 1.4rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem; margin: 0; }
.dark .cmt-title-row h1 { color: #f1f5f9; }

.cmt-month-form { display: inline-flex; align-items: center; gap: 0.5rem; }
.cmt-month-picker { display: flex; align-items: center; gap: 0.5rem; }
.cmt-input { padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #cbd5e1; font-size: 0.875rem; background: #fff; color: #1e293b; }
.dark .cmt-input { background: #1e293b; color: #f1f5f9; border-color: #475569; }

.cmt-alert { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; }
.cmt-alert-success { background: #dcfce7; color: #166534; }
.cmt-alert-error { background: #fee2e2; color: #b91c1c; }
.dark .cmt-alert-success { background: rgba(22,163,74,0.15); color: #86efac; }
.dark .cmt-alert-error { background: rgba(185,28,28,0.15); color: #fca5a5; }

/* Stats */
.cmt-stats { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
.cmt-stat { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem 1.25rem; text-align: center; min-width: 80px; }
.dark .cmt-stat { background: #1e293b; border-color: #334155; }
.cmt-stat-num { display: block; font-size: 1.5rem; font-weight: 700; color: #334155; }
.dark .cmt-stat-num { color: #e2e8f0; }
.cmt-stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.cmt-stat-done .cmt-stat-num { color: #16a34a; }
.cmt-stat-pending .cmt-stat-num { color: #ea580c; }

/* Technician Grid */
.cmt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
.cmt-card { display: block; background: #fff; border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 0.875rem; text-decoration: none; transition: all 0.15s ease; }
.dark .cmt-card { background: #1e293b; border-color: #334155; }
.cmt-card:hover { border-color: #3b82f6; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.15); }
.cmt-card-active { border-color: #3b82f6; background: #eff6ff; }
.dark .cmt-card-active { background: rgba(59,130,246,0.1); border-color: #3b82f6; }
.cmt-card-done { border-color: #16a34a; opacity: 0.7; }
.cmt-card-done:hover { opacity: 1; }

.cmt-card-top { display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.5rem; }
.cmt-card-avatar { width: 36px; height: 36px; border-radius: 50%; background: #e0e7ff; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #4338ca; font-size: 0.875rem; position: relative; flex-shrink: 0; }
.dark .cmt-card-avatar { background: #312e81; color: #a5b4fc; }
.cmt-card-check { position: absolute; bottom: -2px; right: -2px; color: #16a34a; font-size: 0.75rem; background: #fff; border-radius: 50%; }
.dark .cmt-card-check { background: #1e293b; }
.cmt-card-info { overflow: hidden; }
.cmt-card-info strong { display: block; font-size: 0.8rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dark .cmt-card-info strong { color: #f1f5f9; }
.cmt-card-info small { font-size: 0.7rem; color: #64748b; }

.cmt-card-bar { height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
.dark .cmt-card-bar { background: #334155; }
.cmt-card-bar-fill { height: 100%; background: #3b82f6; border-radius: 2px; transition: width 0.3s ease; }
.cmt-bar-done { background: #16a34a; }

/* Workspace */
.cmt-workspace { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem; }
.dark .cmt-workspace { background: #1e293b; border-color: #334155; }

.cmt-ws-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; }
.cmt-ws-header h2 { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0; }
.dark .cmt-ws-header h2 { color: #f1f5f9; }
.cmt-ws-header p { font-size: 0.8rem; color: #64748b; margin: 0.125rem 0 0; }
.cmt-ws-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.cmt-badge-done { display: inline-block; background: #dcfce7; color: #166534; font-size: 0.7rem; padding: 0.125rem 0.5rem; border-radius: 1rem; font-weight: 600; margin-left: 0.5rem; }
.dark .cmt-badge-done { background: rgba(22,163,74,0.2); color: #86efac; }

/* Buttons */
.cmt-btn { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.15s ease; text-decoration: none; }
.cmt-btn-export { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.cmt-btn-export:hover { background: #16a34a; color: #fff; }
.cmt-btn-good { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.cmt-btn-good:hover { background: #16a34a; color: #fff; }
.cmt-btn-good:disabled { opacity: 0.5; cursor: wait; }
.cmt-btn-finalize { background: #2563eb; color: #fff; }
.cmt-btn-finalize:hover { background: #1d4ed8; }
.cmt-btn-lg { padding: 0.75rem 1.5rem; font-size: 0.9rem; }

/* Progress */
.cmt-progress { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-bottom: 1rem; }
.dark .cmt-progress { background: #334155; }
.cmt-progress-fill { height: 100%; background: linear-gradient(90deg, #3b82f6, #16a34a); border-radius: 3px; transition: width 0.3s ease; }

/* Tools List */
.cmt-tools-list { display: flex; flex-direction: column; gap: 0.5rem; }
.cmt-tool-row { display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: #fafbfc; transition: all 0.15s ease; }
.dark .cmt-tool-row { background: #0f172a; border-color: #334155; }

.cmt-tool-good { border-left: 3px solid #16a34a; background: #f0fdf4; }
.dark .cmt-tool-good { background: rgba(22,163,74,0.08); }
.cmt-tool-repair { border-left: 3px solid #f59e0b; background: #fffbeb; }
.dark .cmt-tool-repair { background: rgba(245,158,11,0.08); }
.cmt-tool-missing { border-left: 3px solid #ef4444; background: #fef2f2; }
.dark .cmt-tool-missing { background: rgba(239,68,68,0.08); }
.cmt-tool-unchecked { border-left: 3px solid #cbd5e1; }

.cmt-tool-info { min-width: 0; }
.cmt-tool-name { font-size: 0.85rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dark .cmt-tool-name { color: #f1f5f9; }
.cmt-tool-code { font-size: 0.7rem; color: #64748b; }

.cmt-tool-status-btns { display: flex; gap: 0.25rem; }
.cmt-sbtn { width: 32px; height: 32px; border-radius: 0.375rem; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; transition: all 0.15s ease; color: #94a3b8; }
.dark .cmt-sbtn { background: #1e293b; border-color: #475569; color: #64748b; }
.cmt-sbtn:hover { transform: scale(1.1); }
.cmt-sbtn-good:hover, .cmt-sbtn-good.active { background: #16a34a; color: #fff; border-color: #16a34a; }
.cmt-sbtn-repair:hover, .cmt-sbtn-repair.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }
.cmt-sbtn-missing:hover, .cmt-sbtn-missing.active { background: #ef4444; color: #fff; border-color: #ef4444; }

.cmt-tool-notes-wrap { min-width: 0; }
.cmt-tool-notes { width: 100%; min-width: 120px; max-width: 200px; padding: 0.375rem 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.375rem; font-size: 0.75rem; background: #fff; color: #1e293b; }
.dark .cmt-tool-notes { background: #0f172a; border-color: #475569; color: #f1f5f9; }
.cmt-tool-notes:focus { outline: none; border-color: #3b82f6; }
.cmt-readonly .cmt-tool-notes { background: #f1f5f9; cursor: not-allowed; }

.cmt-finalize-bottom { text-align: center; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; }
.dark .cmt-finalize-bottom { border-color: #334155; }

.cmt-empty { text-align: center; padding: 3rem 1rem; color: #64748b; font-size: 0.9rem; }
.cmt-empty i { display: block; font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5; }

/* Mobile responsive */
@media (max-width: 640px) {
    .cmt-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.5rem; }
    .cmt-tool-row { grid-template-columns: 1fr; gap: 0.5rem; }
    .cmt-tool-status-btns { justify-content: flex-start; }
    .cmt-tool-notes { max-width: 100%; }
    .cmt-ws-header { flex-direction: column; align-items: flex-start; }
    .cmt-title-row { flex-direction: column; align-items: flex-start; }
    .cmt-stats { flex-wrap: wrap; }
}
</style>