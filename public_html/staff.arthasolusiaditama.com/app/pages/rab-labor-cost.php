<?php
/**
 * RAB Labor Cost Management Page
 * 
 * This page allows users to manage labor cost items for a specific RAB
 */

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/database.php';

@date_default_timezone_set('Asia/Jakarta');

// Get RAB ID from URL
$rabId = intval($_GET['rab_id'] ?? 0);

if ($rabId <= 0) {
    header('Location: ../pages/projects.php');
    exit;
}

// Get sales database connection
function getSalesPdo() {
    require __DIR__ . '/../config/database_sales.php';
    return new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

$salesPdo = getSalesPdo();

// Get RAB details
$stmt = $salesPdo->prepare("
    SELECT r.*, p.project_name, p.ao_number 
    FROM rab r
    LEFT JOIN projects p ON p.id = r.project_id
    WHERE r.id = ?
");
$stmt->execute([$rabId]);
$rab = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rab) {
    header('Location: ../pages/projects.php');
    exit;
}

// Get user info
$stmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Labor Cost - RAB ' . $rab['rab_number'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 1.5rem;
        }
        .table-responsive {
            border-radius: 0.375rem;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .total-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .total-box h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        .total-box p {
            margin: 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="bi bi-people-fill"></i> Labor Cost Management</h2>
                        <p class="text-muted mb-0">
                            RAB: <strong><?php echo htmlspecialchars($rab['rab_number']); ?></strong> | 
                            Project: <strong><?php echo htmlspecialchars($rab['project_name'] ?? 'N/A'); ?></strong>
                        </p>
                    </div>
                    <div>
                        <a href="../pages/projects.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Labor Cost -->
        <div class="row">
            <div class="col-md-4">
                <div class="total-box">
                    <p>Total Labor Cost</p>
                    <h3 id="totalLaborCost">Rp <?php echo number_format($rab['total_labor_cost'] ?? 0, 0, ',', '.'); ?></h3>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">RAB Summary</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Section A (Purchase):</strong> Rp <?php echo number_format($rab['total_section_a'] ?? 0, 0, ',', '.'); ?></p>
                                <p class="mb-1"><strong>Section B (Warehouse):</strong> Rp <?php echo number_format($rab['total_section_b_warehouse'] ?? 0, 0, ',', '.'); ?></p>
                                <p class="mb-1"><strong>Section B (Buy):</strong> Rp <?php echo number_format($rab['total_section_b_buy'] ?? 0, 0, ',', '.'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Section C:</strong> Rp <?php echo number_format($rab['total_section_c'] ?? 0, 0, ',', '.'); ?></p>
                                <p class="mb-1"><strong>Section D:</strong> Rp <?php echo number_format($rab['total_section_d'] ?? 0, 0, ',', '.'); ?></p>
                                <p class="mb-1"><strong>Grand Total:</strong> <span class="text-primary fw-bold">Rp <?php echo number_format($rab['grand_total'] ?? 0, 0, ',', '.'); ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Labor Cost Button -->
        <div class="row mb-3">
            <div class="col-12">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLaborCostModal">
                    <i class="bi bi-plus-circle"></i> Tambah Labor Cost
                </button>
            </div>
        </div>

        <!-- Labor Cost Items Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Labor Cost Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="laborCostTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="25%">Deskripsi Pekerjaan</th>
                                        <th width="15%">Nama Pekerja</th>
                                        <th width="8%">Qty</th>
                                        <th width="8%">Unit</th>
                                        <th width="8%">Hari</th>
                                        <th width="12%">Rate/Hari</th>
                                        <th width="12%">Total</th>
                                        <th width="7%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="laborCostTableBody">
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <div class="spinner-border spinner-border-sm" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            Loading...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Labor Cost Modal -->
    <div class="modal fade" id="addLaborCostModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Labor Cost</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addLaborCostForm">
                    <div class="modal-body">
                        <input type="hidden" name="rab_id" value="<?php echo $rabId; ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Deskripsi Pekerjaan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="job_description" required placeholder="Contoh: Instalasi Kabel Listrik">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Pekerja</label>
                                <input type="text" class="form-control" name="worker_name" placeholder="Opsional">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Qty <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="qty" value="1" min="1" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Unit</label>
                                <input type="text" class="form-control" name="unit" value="orang" placeholder="orang">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jumlah Hari <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="days" value="1" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rate per Hari (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="rate_per_day" value="0" min="0" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Catatan</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="Catatan tambahan (opsional)"></textarea>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Total Cost = Qty × Hari × Rate per Hari
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Labor Cost Modal -->
    <div class="modal fade" id="editLaborCostModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Labor Cost</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editLaborCostForm">
                    <div class="modal-body">
                        <input type="hidden" name="item_id" id="edit_item_id">
                        <input type="hidden" name="action" value="update">
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Deskripsi Pekerjaan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="job_description" id="edit_job_description" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Pekerja</label>
                                <input type="text" class="form-control" name="worker_name" id="edit_worker_name">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Qty <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="qty" id="edit_qty" min="1" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Unit</label>
                                <input type="text" class="form-control" name="unit" id="edit_unit">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jumlah Hari <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="days" id="edit_days" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rate per Hari (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="rate_per_day" id="edit_rate_per_day" min="0" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Catatan</label>
                                <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const rabId = <?php echo $rabId; ?>;
        
        // Load labor cost items
        function loadLaborCostItems() {
            fetch(`../action/handle-labor-cost.php?action=get_items&rab_id=${rabId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderLaborCostTable(data.data.items);
                        updateTotalDisplay(data.data.labor_total);
                    } else {
                        showError('Gagal memuat data: ' + data.error);
                    }
                })
                .catch(error => {
                    showError('Error: ' + error.message);
                });
        }
        
        // Render table
        function renderLaborCostTable(items) {
            const tbody = document.getElementById('laborCostTableBody');
            
            if (items.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center text-muted">
                            <i class="bi bi-inbox"></i> Belum ada data labor cost
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = items.map((item, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(item.job_description)}</td>
                    <td>${escapeHtml(item.worker_name || '-')}</td>
                    <td>${item.qty}</td>
                    <td>${escapeHtml(item.unit)}</td>
                    <td>${item.days}</td>
                    <td>Rp ${formatNumber(item.rate_per_day)}</td>
                    <td><strong>Rp ${formatNumber(item.total_cost)}</strong></td>
                    <td>
                        <button class="btn btn-sm btn-warning btn-action" onclick="editItem(${item.id})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger btn-action" onclick="deleteItem(${item.id})" title="Hapus">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }
        
        // Update total display
        function updateTotalDisplay(total) {
            document.getElementById('totalLaborCost').textContent = 'Rp ' + formatNumber(total);
        }
        
        // Add labor cost
        document.getElementById('addLaborCostForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../action/handle-labor-cost.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('addLaborCostModal')).hide();
                    this.reset();
                    loadLaborCostItems();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(data.error);
                }
            })
            .catch(error => {
                showError('Error: ' + error.message);
            });
        });
        
        // Edit item
        function editItem(itemId) {
            fetch(`../action/handle-labor-cost.php?action=get_items&rab_id=${rabId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const item = data.data.items.find(i => i.id == itemId);
                        if (item) {
                            document.getElementById('edit_item_id').value = item.id;
                            document.getElementById('edit_job_description').value = item.job_description;
                            document.getElementById('edit_worker_name').value = item.worker_name || '';
                            document.getElementById('edit_qty').value = item.qty;
                            document.getElementById('edit_unit').value = item.unit;
                            document.getElementById('edit_days').value = item.days;
                            document.getElementById('edit_rate_per_day').value = item.rate_per_day;
                            document.getElementById('edit_notes').value = item.notes || '';
                            
                            new bootstrap.Modal(document.getElementById('editLaborCostModal')).show();
                        }
                    }
                });
        }
        
        // Update labor cost
        document.getElementById('editLaborCostForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../action/handle-labor-cost.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('editLaborCostModal')).hide();
                    loadLaborCostItems();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(data.error);
                }
            })
            .catch(error => {
                showError('Error: ' + error.message);
            });
        });
        
        // Delete item
        function deleteItem(itemId) {
            if (!confirm('Yakin ingin menghapus item labor cost ini?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('item_id', itemId);
            
            fetch('../action/handle-labor-cost.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    loadLaborCostItems();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(data.error);
                }
            })
            .catch(error => {
                showError('Error: ' + error.message);
            });
        }
        
        // Utility functions
        function formatNumber(num) {
            return parseFloat(num).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0});
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showSuccess(message) {
            alert(message);
        }
        
        function showError(message) {
            alert('Error: ' + message);
        }
        
        // Load items on page load
        loadLaborCostItems();
    </script>
</body>
</html>
