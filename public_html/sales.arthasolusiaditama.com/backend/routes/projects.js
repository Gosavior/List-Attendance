const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const { salesPool, staffPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

let XLSX;
try { XLSX = require('xlsx'); } catch (e) { XLSX = null; }

const router = express.Router();



const uploadDir = path.join(__dirname, '..', 'uploads');
if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir, { recursive: true });

const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    const sub = req.uploadSubDir || 'misc';
    const dir = path.join(uploadDir, sub);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    cb(null, dir);
  },
  filename: (req, file, cb) => {
    const safeName = file.originalname.replace(/[^a-zA-Z0-9._\-()]/g, '_');
    cb(null, `${Date.now()}-${safeName}`);
  },
});

const upload = multer({
  storage,
  limits: { fileSize: 20 * 1024 * 1024 },
  fileFilter: (req, file, cb) => {
    const allowedTypes = /pdf|doc|docx|xls|xlsx|png|jpg|jpeg/;
    const ext = path.extname(file.originalname).toLowerCase().replace('.', '');
    if (allowedTypes.test(ext)) {
      cb(null, true);
    } else {
      cb(new Error('Format file tidak diizinkan. Gunakan PDF, DOC, DOCX, XLS, XLSX, PNG, JPG.'));
    }
  },
});



const getSalesUsers = async () => {
  const [rows] = await staffPool.execute(
    `SELECT id, full_name, username, email, role FROM users WHERE role IN ('sales', 'administrator', 'direktur') AND is_active = 1 ORDER BY FIELD(role, 'administrator', 'direktur', 'sales'), full_name`
  );
  return rows;
};



router.get('/stats/summary', authenticate, async (req, res) => {
  try {
    const user = req.user;
    let where = '';
    const params = [];

    if (user.role === 'sales') {
      where = 'WHERE assigned_to = ?';
      params.push(user.id);
    }

    const [rows] = await salesPool.execute(
      `SELECT status, COUNT(*) as count FROM projects ${where} GROUP BY status`,
      params
    );

    const stats = { PROSPECT: 0, NEAREST: 0, ONGOING: 0, DONE: 0, LOST: 0 };
    rows.forEach(r => { stats[r.status] = r.count; });
    stats.total = Object.values(stats).reduce((a, b) => a + b, 0);

    res.json({ success: true, data: stats });
  } catch (err) {
    console.error('GET /api/projects/stats/summary error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil statistik.' });
  }
});



router.get('/', authenticate, async (req, res) => {
  try {
    const { status, assigned_to, search, limit = 100, offset = 0 } = req.query;
    const user = req.user;

    let where = 'WHERE 1=1';
    const params = [];

    if (user.role === 'sales') {
      where += ' AND p.assigned_to = ?';
      params.push(user.id);
    } else if (assigned_to) {
      where += ' AND p.assigned_to = ?';
      params.push(assigned_to);
    }

    if (status) {
      const statuses = status.split(',').map(s => s.trim()).filter(Boolean);
      if (statuses.length === 1) {
        where += ' AND p.status = ?';
        params.push(statuses[0]);
      } else if (statuses.length > 1) {
        where += ` AND p.status IN (${statuses.map(() => '?').join(',')})`;
        params.push(...statuses);
      }
    }

    if (search) {
      where += ' AND (p.project_name LIKE ? OR p.customer_name LIKE ?)';
      params.push(`%${search}%`, `%${search}%`);
    }

    params.push(parseInt(limit), parseInt(offset));

    const [rows] = await salesPool.execute(
      `SELECT p.*, 
              r.id as rab_id_check, r.grand_total as rab_grand_total, r.status as rab_status, r.rab_number as rab_number,
              rb.id as rab_bayangan_id, rb.grand_total as rab_bayangan_total, rb.rab_number as rab_bayangan_number
       FROM projects p
       LEFT JOIN rab r ON r.project_id = p.id AND r.rab_type = 'nyata'
       LEFT JOIN rab rb ON rb.project_id = p.id AND rb.rab_type = 'bayangan'
       ${where}
       ORDER BY p.updated_at DESC
       LIMIT ? OFFSET ?`,
      params
    );

    const salesUsers = await getSalesUsers();
    const salesMap = {};
    salesUsers.forEach(u => { salesMap[u.id] = u.full_name; });

    const projects = rows.map(p => ({
      ...p,
      sales_name: salesMap[p.assigned_to] || 'Unknown',
      nominal_qo: p.nominal_qo ? parseFloat(p.nominal_qo) : null,
      nominal_estimate: p.nominal_estimate ? parseFloat(p.nominal_estimate) : null,
      rab_grand_total: p.rab_grand_total ? parseFloat(p.rab_grand_total) : null,
      rab_bayangan_total: p.rab_bayangan_total ? parseFloat(p.rab_bayangan_total) : null,
    }));

    const [countResult] = await salesPool.execute(
      `SELECT COUNT(*) as total FROM projects p ${where}`,
      params.slice(0, -2)
    );

    res.json({
      success: true,
      data: projects,
      total: countResult[0].total,
      salesList: (user.role === 'administrator' || user.role === 'direktur') ? salesUsers : undefined,
    });
  } catch (err) {
    console.error('GET /api/projects error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data project.' });
  }
});



router.get('/:id', authenticate, async (req, res) => {
  try {
    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [req.params.id]);
    if (rows.length === 0) {
      return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });
    }

    const project = rows[0];

    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    const [salesRows] = await staffPool.execute('SELECT full_name FROM users WHERE id = ?', [project.assigned_to]);
    project.sales_name = salesRows[0]?.full_name || 'Unknown';

    res.json({ success: true, data: project });
  } catch (err) {
    console.error('GET /api/projects/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil detail project.' });
  }
});



router.post('/', authenticate, authorize('sales', 'administrator'), async (req, res) => {
  try {
    const { project_name, customer_id, customer_name, customer_phone, customer_email, nominal_estimate } = req.body;

    if (!project_name) {
      return res.status(400).json({ success: false, message: 'Project name wajib diisi.' });
    }

    let custName = customer_name || '';
    let custPhone = customer_phone || '';
    let custEmail = customer_email || '';
    let custId = customer_id || null;

    if (custId) {
      const [custRows] = await salesPool.execute('SELECT name, phone, email FROM customers WHERE id = ?', [custId]);
      if (custRows.length === 0) {
        return res.status(400).json({ success: false, message: 'Customer tidak ditemukan.' });
      }
      custName = custRows[0].name || custName;
      custPhone = custRows[0].phone || custPhone;
      custEmail = custRows[0].email || custEmail;
    }

    if (!custName) {
      return res.status(400).json({
        success: false,
        message: 'Nama customer wajib diisi.',
      });
    }

    const nextDue = new Date();
    nextDue.setMonth(nextDue.getMonth() + 1);
    const nextDueStr = nextDue.toISOString().split('T')[0];

    const [result] = await salesPool.execute(
      `INSERT INTO projects (project_name, customer_id, customer_name, customer_phone, customer_email, nominal_estimate, assigned_to, created_by, status, next_update_due)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PROSPECT', ?)`,
      [project_name, custId, custName, custPhone || null, custEmail || null, nominal_estimate || null, req.user.id, req.user.id, nextDueStr]
    );

    res.status(201).json({ success: true, message: 'Project berhasil dibuat.', data: { id: result.insertId } });
  } catch (err) {
    console.error('POST /api/projects error:', err);
    res.status(500).json({ success: false, message: 'Gagal membuat project.' });
  }
});



router.put('/:id/to-nearest', authenticate, authorize('sales', 'administrator'), (req, res, next) => {
  req.uploadSubDir = 'qo';
  next();
}, upload.single('file_qo'), async (req, res) => {
  try {
    const { id } = req.params;
    const { nominal_qo, qo_number } = req.body;

    if (!nominal_qo || !req.file || !qo_number || !qo_number.trim()) {
      return res.status(400).json({
        success: false,
        message: 'Nominal QO, Nomor QO, dan File QO wajib diisi.',
      });
    }

    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    const project = rows[0];
    if (project.status !== 'PROSPECT') {
      return res.status(400).json({ success: false, message: 'Hanya project PROSPECT yang bisa diupdate ke NEAREST.' });
    }

    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    const [rabCheck] = await salesPool.execute(
      `SELECT id FROM rab WHERE project_id = ? AND rab_type = 'bayangan' LIMIT 1`, [id]
    );
    if (rabCheck.length === 0) {
      return res.status(400).json({ success: false, message: 'RAB Bayangan wajib dibuat terlebih dahulu.' });
    }

    const qoNumber = qo_number.trim();

    await salesPool.execute(
      `UPDATE projects SET nominal_qo = ?, qo_number = ?, file_qo = ?, status = 'NEAREST', updated_at = NOW() WHERE id = ?`,
      [parseFloat(nominal_qo), qoNumber, req.file.filename, id]
    );

    res.json({
      success: true,
      message: 'Project berhasil diupdate ke NEAREST.',
      data: { qo_number: qoNumber, file_qo: req.file.filename },
    });
  } catch (err) {
    console.error('PUT /api/projects/:id/to-nearest error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate project.' });
  }
});



router.put('/:id/to-lost', authenticate, authorize('sales', 'administrator'), async (req, res) => {
  try {
    const { id } = req.params;
    const { lost_reason } = req.body;

    if (!lost_reason || !lost_reason.trim()) {
      return res.status(400).json({ success: false, message: 'Alasan lost wajib diisi.' });
    }

    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    const project = rows[0];

    if (project.status !== 'PROSPECT') {
      return res.status(400).json({
        success: false,
        message: 'Hanya project PROSPECT yang bisa diupdate ke LOST.',
      });
    }

    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    await salesPool.execute(
      `UPDATE projects SET lost_reason = ?, status = 'LOST', updated_at = NOW() WHERE id = ?`,
      [lost_reason.trim(), id]
    );

    res.json({ success: true, message: 'Project berhasil diupdate ke LOST.' });
  } catch (err) {
    console.error('PUT /api/projects/:id/to-lost error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate project.' });
  }
});



router.put('/:id/update-qo', authenticate, authorize('sales', 'administrator'), (req, res, next) => {
  req.uploadSubDir = 'qo';
  next();
}, upload.single('file_qo'), async (req, res) => {
  try {
    const { id } = req.params;
    const { qo_number } = req.body;

    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    const project = rows[0];
    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    const updates = [];
    const params = [];

    if (req.file) {
      updates.push('file_qo = ?');
      params.push(req.file.filename);
    }

    if (qo_number && qo_number.trim()) {
      updates.push('qo_number = ?');
      params.push(qo_number.trim());
    }

    if (updates.length === 0) {
      return res.status(400).json({ success: false, message: 'Tidak ada perubahan.' });
    }

    updates.push('updated_at = NOW()');
    params.push(id);

    await salesPool.execute(`UPDATE projects SET ${updates.join(', ')} WHERE id = ?`, params);
    res.json({ success: true, message: 'File QO berhasil diupdate.' });
  } catch (err) {
    console.error('PUT /api/projects/:id/update-qo error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate QO.' });
  }
});



router.put('/:id/update-ao', authenticate, authorize('sales', 'administrator'), (req, res, next) => {
  req.uploadSubDir = 'ao';
  next();
}, upload.single('file_ao'), async (req, res) => {
  try {
    const { id } = req.params;
    const { ao_number } = req.body;

    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    const project = rows[0];
    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    const updates = [];
    const params = [];

    if (req.file) {
      updates.push('file_ao = ?');
      params.push(req.file.filename);
      if (!ao_number || !ao_number.trim()) {
        updates.push('ao_number = ?');
        params.push(req.file.originalname.replace(/\.[^/.]+$/, ''));
      }
    }

    if (ao_number && ao_number.trim()) {
      updates.push('ao_number = ?');
      params.push(ao_number.trim());
    }

    if (updates.length === 0) {
      return res.status(400).json({ success: false, message: 'Tidak ada perubahan.' });
    }

    updates.push('updated_at = NOW()');
    params.push(id);
    await salesPool.execute(`UPDATE projects SET ${updates.join(', ')} WHERE id = ?`, params);

    res.json({ success: true, message: 'File AO berhasil diupdate.' });
  } catch (err) {
    console.error('PUT /api/projects/:id/update-ao error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate AO.' });
  }
});



router.put('/:id/update-invoice', authenticate, authorize('sales', 'administrator'), (req, res, next) => {
  req.uploadSubDir = 'invoice';
  next();
}, upload.single('file_invoice'), async (req, res) => {
  try {
    const { id } = req.params;

    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    const project = rows[0];
    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    if (!req.file) {
      return res.status(400).json({ success: false, message: 'File invoice wajib diupload.' });
    }

    await salesPool.execute(
      'UPDATE projects SET file_invoice = ?, updated_at = NOW() WHERE id = ?',
      [req.file.filename, id]
    );

    res.json({ success: true, message: 'File Invoice berhasil diupdate.' });
  } catch (err) {
    console.error('PUT /api/projects/:id/update-invoice error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate Invoice.' });
  }
});



router.put('/:id/update-report', authenticate, authorize('sales', 'administrator'), (req, res, next) => {
  req.uploadSubDir = 'report';
  next();
}, upload.single('file_report'), async (req, res) => {
  try {
    const { id } = req.params;

    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    const project = rows[0];
    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    if (!req.file) {
      return res.status(400).json({ success: false, message: 'File report wajib diupload.' });
    }

    const ext = req.file.originalname.split('.').pop().toLowerCase();
    if (ext !== 'pdf') {
      return res.status(400).json({ success: false, message: 'File report harus berformat PDF.' });
    }

    await salesPool.execute(
      'UPDATE projects SET file_report = ?, updated_at = NOW() WHERE id = ?',
      [req.file.filename, id]
    );

    res.json({ success: true, message: 'File Report berhasil diupdate.' });
  } catch (err) {
    console.error('PUT /api/projects/:id/update-report error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate Report.' });
  }
});



router.put('/:id/to-ongoing', authenticate, authorize('sales', 'administrator'), (req, res, next) => {
  req.uploadSubDir = 'po';
  next();
}, upload.single('file_po'), async (req, res) => {
  try {
    const { id } = req.params;
    const { rab_id, ao_number, po_number } = req.body;

    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    const project = rows[0];
    if (project.status !== 'NEAREST') {
      return res.status(400).json({ success: false, message: 'Hanya project NEAREST yang bisa diupdate ke ONGOING.' });
    }

    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    if (!ao_number || !ao_number.toString().trim()) {
      return res.status(400).json({ success: false, message: 'Number AO wajib diisi.' });
    }
    if (!po_number || !po_number.toString().trim()) {
      return res.status(400).json({ success: false, message: 'Number PO wajib diisi.' });
    }
    if (!req.file) {
      return res.status(400).json({ success: false, message: 'File PO wajib diupload.' });
    }

    const aoNumber = ao_number.toString().trim();
    const poNumber = po_number.toString().trim();

    await salesPool.execute(
      `UPDATE projects SET ao_number = ?, po_number = ?, file_po = ?, rab_id = ?, status = 'ONGOING', updated_at = NOW() WHERE id = ?`,
      [aoNumber, poNumber, req.file.filename, rab_id || null, id]
    );

    res.json({
      success: true,
      message: 'Project berhasil diupdate ke ONGOING.',
      data: { ao_number: aoNumber, po_number: poNumber, file_po: req.file.filename },
    });
  } catch (err) {
    console.error('PUT /api/projects/:id/to-ongoing error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate project.' });
  }
});



router.put('/:id/to-done', authenticate, authorize('sales', 'administrator'), (req, res, next) => {
  req.uploadSubDir = 'reports';
  next();
}, upload.fields([
  { name: 'file_report', maxCount: 1 },
  { name: 'file_invoice', maxCount: 1 },
]), async (req, res) => {
  try {
    const { id } = req.params;

    if (!req.files?.file_report?.[0] || !req.files?.file_invoice?.[0]) {
      return res.status(400).json({ success: false, message: 'File Report dan File Invoice wajib diupload.' });
    }

    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    const project = rows[0];
    if (project.status !== 'ONGOING') {
      return res.status(400).json({ success: false, message: 'Hanya project ONGOING yang bisa diupdate ke DONE.' });
    }

    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    await salesPool.execute(
      `UPDATE projects SET file_report = ?, file_invoice = ?, status = 'DONE', updated_at = NOW() WHERE id = ?`,
      [req.files.file_report[0].filename, req.files.file_invoice[0].filename, id]
    );

    res.json({ success: true, message: 'Project berhasil diupdate ke DONE.' });
  } catch (err) {
    console.error('PUT /api/projects/:id/to-done error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate project.' });
  }
});



router.put('/:id/update-frequency', authenticate, authorize('administrator'), async (req, res) => {
  try {
    const { id } = req.params;
    const { frequency } = req.body;

    if (!['WEEKLY', 'BIWEEKLY', 'MONTHLY'].includes(frequency)) {
      return res.status(400).json({ success: false, message: 'Frequency harus WEEKLY, BIWEEKLY, atau MONTHLY.' });
    }

    const nextDue = new Date();
    if (frequency === 'WEEKLY') nextDue.setDate(nextDue.getDate() + 7);
    else if (frequency === 'BIWEEKLY') nextDue.setDate(nextDue.getDate() + 14);
    else nextDue.setMonth(nextDue.getMonth() + 1);
    const nextDueStr = nextDue.toISOString().split('T')[0];

    await salesPool.execute(
      'UPDATE projects SET update_frequency = ?, next_update_due = ? WHERE id = ?',
      [frequency, nextDueStr, id]
    );

    res.json({ success: true, message: 'Frekuensi update berhasil diubah.' });
  } catch (err) {
    console.error('PUT /api/projects/:id/update-frequency error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengubah frekuensi.' });
  }
});



router.get('/:id/updates', authenticate, async (req, res) => {
  try {
    const { id } = req.params;
    const [rows] = await salesPool.execute(
      `SELECT pu.*, u.full_name as user_name
       FROM project_updates pu
       LEFT JOIN arth_Staff.users u ON pu.user_id = u.id
       WHERE pu.project_id = ?
       ORDER BY pu.created_at DESC`,
      [id]
    );
    res.json({ success: true, data: rows });
  } catch (err) {
    console.error('GET /api/projects/:id/updates error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil update.' });
  }
});



router.post('/:id/updates', authenticate, authorize('sales', 'administrator'), async (req, res) => {
  try {
    const { id } = req.params;
    const { update_text } = req.body;

    if (!update_text || !update_text.trim()) {
      return res.status(400).json({ success: false, message: 'Update text wajib diisi.' });
    }

    const [project] = await salesPool.execute('SELECT id, update_frequency FROM projects WHERE id = ?', [id]);
    if (project.length === 0) {
      return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });
    }

    await salesPool.execute(
      'INSERT INTO project_updates (project_id, user_id, update_text) VALUES (?, ?, ?)',
      [id, req.user.id, update_text.trim()]
    );

    const freq = project[0].update_frequency || 'MONTHLY';
    const nextDue = new Date();
    if (freq === 'WEEKLY') nextDue.setDate(nextDue.getDate() + 7);
    else if (freq === 'BIWEEKLY') nextDue.setDate(nextDue.getDate() + 14);
    else nextDue.setMonth(nextDue.getMonth() + 1);
    const nextDueStr = nextDue.toISOString().split('T')[0];

    await salesPool.execute(
      'UPDATE projects SET updated_at = NOW(), next_update_due = ? WHERE id = ?',
      [nextDueStr, id]
    );

    res.status(201).json({ success: true, message: 'Update berhasil disimpan.' });
  } catch (err) {
    console.error('POST /api/projects/:id/updates error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyimpan update.' });
  }
});



router.put('/:id/transfer', authenticate, authorize('administrator'), async (req, res) => {
  try {
    const { id } = req.params;
    const { new_sales_id } = req.body;

    if (!new_sales_id) {
      return res.status(400).json({ success: false, message: 'Sales tujuan wajib dipilih.' });
    }

    const [project] = await salesPool.execute('SELECT id, assigned_to, project_name FROM projects WHERE id = ?', [id]);
    if (project.length === 0) {
      return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });
    }

    if (project[0].assigned_to === parseInt(new_sales_id)) {
      return res.status(400).json({ success: false, message: 'Project sudah dimiliki oleh sales ini.' });
    }

    const [targetUser] = await staffPool.execute(
      "SELECT id, full_name FROM users WHERE id = ? AND role = 'sales' AND is_active = 1",
      [new_sales_id]
    );
    if (targetUser.length === 0) {
      return res.status(400).json({ success: false, message: 'Sales tujuan tidak valid.' });
    }

    await salesPool.execute(
      'UPDATE projects SET assigned_to = ?, updated_at = NOW() WHERE id = ?',
      [new_sales_id, id]
    );

    res.json({ success: true, message: `Project "${project[0].project_name}" berhasil dipindahkan ke ${targetUser[0].full_name}.` });
  } catch (err) {
    console.error('PUT /api/projects/:id/transfer error:', err);
    res.status(500).json({ success: false, message: 'Gagal memindahkan project.' });
  }
});



router.put('/bulk-transfer', authenticate, authorize('administrator'), async (req, res) => {
  try {
    const { from_sales_id, to_sales_id } = req.body;

    if (!from_sales_id || !to_sales_id) {
      return res.status(400).json({ success: false, message: 'Sales asal dan tujuan wajib dipilih.' });
    }

    if (parseInt(from_sales_id) === parseInt(to_sales_id)) {
      return res.status(400).json({ success: false, message: 'Sales asal dan tujuan tidak boleh sama.' });
    }

    const [fromUser] = await staffPool.execute(
      "SELECT id, full_name FROM users WHERE id = ? AND role = 'sales' AND is_active = 1",
      [from_sales_id]
    );
    const [toUser] = await staffPool.execute(
      "SELECT id, full_name FROM users WHERE id = ? AND role = 'sales' AND is_active = 1",
      [to_sales_id]
    );
    if (fromUser.length === 0 || toUser.length === 0) {
      return res.status(400).json({ success: false, message: 'Sales asal atau tujuan tidak valid.' });
    }

    const [result] = await salesPool.execute(
      'UPDATE projects SET assigned_to = ?, updated_at = NOW() WHERE assigned_to = ?',
      [to_sales_id, from_sales_id]
    );

    res.json({
      success: true,
      message: `${result.affectedRows} project berhasil dipindahkan dari ${fromUser[0].full_name} ke ${toUser[0].full_name}.`,
      count: result.affectedRows
    });
  } catch (err) {
    console.error('PUT /api/projects/bulk-transfer error:', err);
    res.status(500).json({ success: false, message: 'Gagal memindahkan project.' });
  }
});



router.put('/:id/edit', authenticate, authorize('sales', 'administrator'), async (req, res) => {
  try {
    const { id } = req.params;
    const { project_name, nominal_estimate } = req.body;

    const [rows] = await salesPool.execute('SELECT * FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    const project = rows[0];
    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    const updates = [];
    const params = [];

    if (project_name && project_name.trim()) {
      updates.push('project_name = ?');
      params.push(project_name.trim());
    }

    if (nominal_estimate !== undefined && nominal_estimate !== null && nominal_estimate !== '') {
      updates.push('nominal_estimate = ?');
      params.push(Number(nominal_estimate));
    }

    if (updates.length === 0) {
      return res.status(400).json({ success: false, message: 'Tidak ada perubahan.' });
    }

    updates.push('updated_at = NOW()');
    params.push(id);

    await salesPool.execute(`UPDATE projects SET ${updates.join(', ')} WHERE id = ?`, params);
    res.json({ success: true, message: 'Project berhasil diupdate.' });
  } catch (err) {
    console.error('PUT /api/projects/:id/edit error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate project.' });
  }
});



router.delete('/:id', authenticate, authorize('administrator'), async (req, res) => {
  try {
    const { id } = req.params;

    const [rows] = await salesPool.execute('SELECT id, project_name FROM projects WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });

    
    await salesPool.execute('DELETE FROM project_updates WHERE project_id = ?', [id]);
    await salesPool.execute('DELETE FROM rab_items WHERE rab_id IN (SELECT id FROM rab WHERE project_id = ?)', [id]);
    await salesPool.execute('DELETE FROM rab WHERE project_id = ?', [id]);
    await salesPool.execute('DELETE FROM projects WHERE id = ?', [id]);

    res.json({ success: true, message: `Project "${rows[0].project_name}" berhasil dihapus.` });
  } catch (err) {
    console.error('DELETE /api/projects/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal menghapus project.' });
  }
});



router.post('/admin-create', authenticate, authorize('administrator'), async (req, res) => {
  try {
    const { project_name, customer_id, customer_name, customer_phone, customer_email, nominal_estimate, assigned_to, status } = req.body;

    if (!project_name) {
      return res.status(400).json({ success: false, message: 'Nama project wajib diisi.' });
    }
    if (!assigned_to) {
      return res.status(400).json({ success: false, message: 'Sales wajib dipilih.' });
    }

    const allowedStatuses = ['PROSPECT', 'NEAREST', 'ONGOING', 'DONE', 'LOST'];
    const projStatus = allowedStatuses.includes(status) ? status : 'PROSPECT';

    let custName = customer_name || '';
    let custPhone = customer_phone || '';
    let custEmail = customer_email || '';
    let custId = customer_id || null;

    if (custId) {
      const [custRows] = await salesPool.execute('SELECT name, phone, email FROM customers WHERE id = ?', [custId]);
      if (custRows.length === 0) {
        return res.status(400).json({ success: false, message: 'Customer tidak ditemukan.' });
      }
      custName = custRows[0].name || custName;
      custPhone = custRows[0].phone || custPhone;
      custEmail = custRows[0].email || custEmail;
    }

    if (!custName) {
      return res.status(400).json({ success: false, message: 'Nama customer wajib diisi.' });
    }

    const nextDue = new Date();
    nextDue.setMonth(nextDue.getMonth() + 1);
    const nextDueStr = nextDue.toISOString().split('T')[0];

    const [result] = await salesPool.execute(
      `INSERT INTO projects (project_name, customer_id, customer_name, customer_phone, customer_email, nominal_estimate, assigned_to, created_by, status, next_update_due)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [project_name, custId, custName, custPhone || null, custEmail || null, nominal_estimate || null, assigned_to, req.user.id, projStatus, nextDueStr]
    );

    res.status(201).json({ success: true, message: 'Project berhasil dibuat.', data: { id: result.insertId } });
  } catch (err) {
    console.error('POST /api/projects/admin-create error:', err);
    res.status(500).json({ success: false, message: 'Gagal membuat project.' });
  }
});



router.post('/import-excel', authenticate, authorize('administrator'), (req, res, next) => {
  req.uploadSubDir = 'imports';
  next();
}, upload.single('file'), async (req, res) => {
  try {
    if (!XLSX) {
      return res.status(500).json({ success: false, message: 'Library xlsx belum terinstall di server. Jalankan: npm install xlsx' });
    }

    if (!req.file) {
      return res.status(400).json({ success: false, message: 'File Excel wajib diupload.' });
    }

    
    const workbook = XLSX.readFile(req.file.path);
    const sheetName = workbook.SheetNames[0];
    const sheet = workbook.Sheets[sheetName];
    const rawData = XLSX.utils.sheet_to_json(sheet, { defval: '' });

    if (!rawData || rawData.length === 0) {
      return res.status(400).json({ success: false, message: 'File Excel kosong atau format tidak sesuai.' });
    }

    
    const [salesUsers] = await staffPool.execute(
      `SELECT id, full_name, username FROM users WHERE role IN ('sales', 'administrator', 'direktur') AND is_active = 1`
    );

    
    const salesMap = {};
    salesUsers.forEach(u => {
      salesMap[u.full_name.toLowerCase().trim()] = u.id;
      if (u.username) salesMap[u.username.toLowerCase().trim()] = u.id;
    });

    
    function determineStatus(row) {
      const invoice = String(row['No.Invoice'] || row['no_invoice'] || row['No. Invoice'] || row['invoice'] || '').trim();
      const po = String(row['No.PO/WO'] || row['no_po_wo'] || row['No. PO/WO'] || row['po'] || row['PO'] || '').trim();
      const ao = String(row['No.AO'] || row['no_ao'] || row['No. AO'] || row['ao'] || row['AO'] || '').trim();
      const qo = String(row['Quotation'] || row['quotation'] || row['QO'] || row['qo'] || '').trim();

      if (invoice) return 'DONE';
      if (po || ao) return 'ONGOING';
      if (qo) return 'NEAREST';
      return 'PROSPECT';
    }

    
    function getVal(row, ...keys) {
      for (const k of keys) {
        
        if (row[k] !== undefined && row[k] !== '') return String(row[k]).trim();
        
        const found = Object.keys(row).find(rk => rk.toLowerCase().trim() === k.toLowerCase().trim());
        if (found && row[found] !== undefined && row[found] !== '') return String(row[found]).trim();
      }
      return '';
    }

    function parseNumber(val) {
      if (!val) return null;
      
      const cleaned = String(val).replace(/[Rp.\s]/g, '').replace(/,/g, '.');
      const num = parseFloat(cleaned);
      return isNaN(num) ? null : num;
    }

    const results = { imported: 0, skipped: 0, errors: [] };
    const nextDue = new Date();
    nextDue.setMonth(nextDue.getMonth() + 1);
    const nextDueStr = nextDue.toISOString().split('T')[0];

    for (let i = 0; i < rawData.length; i++) {
      const row = rawData[i];
      const rowNum = i + 2; 

      try {
        const customerName = getVal(row, 'Nama Customer', 'nama_customer', 'Customer', 'customer', 'customer_name');
        const salesPerson = getVal(row, 'Sales Person', 'sales_person', 'Sales', 'sales', 'salesperson');
        const quotation = getVal(row, 'Quotation', 'quotation', 'QO', 'qo', 'No.QO', 'no_qo');
        const aoNumber = getVal(row, 'No.AO', 'no_ao', 'No. AO', 'AO', 'ao');
        const poNumber = getVal(row, 'No.PO/WO', 'no_po_wo', 'No. PO/WO', 'PO', 'po', 'WO', 'wo', 'No.PO', 'no_po');
        const invoiceNumber = getVal(row, 'No.Invoice', 'no_invoice', 'No. Invoice', 'Invoice', 'invoice');
        const nilaiProject = parseNumber(getVal(row, 'Nilai Project', 'nilai_project', 'Nominal', 'nominal', 'Value', 'value'));
        const nilaiRAB = parseNumber(getVal(row, 'Nilai RAB (estimasi)', 'nilai_rab', 'Nilai RAB', 'RAB', 'rab', 'Estimasi', 'estimasi'));
        const description = getVal(row, 'Description', 'description', 'Deskripsi', 'deskripsi', 'Keterangan', 'keterangan', 'Project Name', 'project_name');

        
        if (!customerName && !description) {
          results.skipped++;
          continue;
        }

        
        const projectName = description || customerName || 'Import Project';

        
        let assignedTo = req.user.id; 
        if (salesPerson) {
          const key = salesPerson.toLowerCase().trim();
          if (salesMap[key]) {
            assignedTo = salesMap[key];
          } else {
            
            const match = Object.entries(salesMap).find(([name]) => 
              name.includes(key) || key.includes(name)
            );
            if (match) {
              assignedTo = match[1];
            } else {
              results.errors.push(`Baris ${rowNum}: Sales "${salesPerson}" tidak ditemukan, di-assign ke admin.`);
            }
          }
        }

        
        const explicitStatus = getVal(row, 'Status', 'status', 'STATUS');
        let status;
        const allowedStatuses = ['PROSPECT', 'NEAREST', 'ONGOING', 'DONE', 'LOST'];
        if (explicitStatus && allowedStatuses.includes(explicitStatus.toUpperCase())) {
          status = explicitStatus.toUpperCase();
        } else {
          status = determineStatus(row);
        }

        
        const [existingRows] = await salesPool.execute(
          `SELECT id FROM projects WHERE project_name = ? AND customer_name = ? LIMIT 1`,
          [projectName, customerName || '-']
        );
        if (existingRows.length > 0) {
          results.errors.push(`Baris ${rowNum}: Project "${projectName}" untuk customer "${customerName}" sudah ada (ID: ${existingRows[0].id}), dilewati.`);
          results.skipped++;
          continue;
        }

        
        const [result] = await salesPool.execute(
          `INSERT INTO projects (project_name, customer_name, customer_phone, customer_email, nominal_estimate, nominal_qo, qo_number, ao_number, po_number, assigned_to, created_by, status, next_update_due)
           VALUES (?, ?, '', '', ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
          [projectName, customerName || '-', nilaiRAB || nilaiProject, nilaiProject, quotation || null, aoNumber || null, poNumber || null, assignedTo, req.user.id, status, nextDueStr]
        );

        
        if (invoiceNumber) {
          await salesPool.execute(
            `UPDATE projects SET file_invoice = ? WHERE id = ?`,
            [invoiceNumber, result.insertId]
          );
        }

        results.imported++;
      } catch (rowErr) {
        results.errors.push(`Baris ${rowNum}: ${rowErr.message}`);
        results.skipped++;
      }
    }

    
    try { fs.unlinkSync(req.file.path); } catch (e) {}

    res.json({
      success: true,
      message: `Import selesai: ${results.imported} project berhasil diimport, ${results.skipped} dilewati.`,
      data: results
    });
  } catch (err) {
    console.error('POST /api/projects/import-excel error:', err);
    res.status(500).json({ success: false, message: 'Gagal import Excel: ' + err.message });
  }
});

module.exports = router;