const express = require('express');
const bcrypt = require('bcryptjs');
const { staffPool, salesPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

const router = express.Router();


router.get('/', authenticate, authorize('administrator', 'sales'), async (req, res) => {
  try {
    let query, params;
    if (req.user.role === 'sales') {
      
      query = 'SELECT id, full_name, username, email, phone, role, avatar, photo, is_active, created_at, last_login FROM users WHERE role = ? ORDER BY full_name';
      params = ['sales'];
    } else {
      query = 'SELECT id, full_name, username, email, phone, role, avatar, photo, is_active, created_at, last_login FROM users ORDER BY full_name';
      params = [];
    }
    const [users] = await staffPool.execute(query, params);

    
    const [projCounts] = await salesPool.execute(
      `SELECT assigned_to, COUNT(*) as total, SUM(status = 'DONE') as done FROM projects GROUP BY assigned_to`
    );
    const countMap = {};
    projCounts.forEach(r => { countMap[r.assigned_to] = { total: r.total, done: Number(r.done) || 0 }; });

    const data = users.map(u => ({
      ...u,
      avatar: u.avatar || u.photo || null,
      project_count: countMap[u.id]?.total || 0,
      done_count: countMap[u.id]?.done || 0,
    }));

    res.json({ success: true, data });
  } catch (err) {
    console.error('GET /api/users error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data users.' });
  }
});


router.get('/:id/profile', authenticate, authorize('administrator', 'sales'), async (req, res) => {
  try {
    const { id } = req.params;

    
    if (req.user.role === 'sales') {
      const [check] = await staffPool.execute('SELECT role FROM users WHERE id = ?', [id]);
      if (check.length === 0 || check[0].role !== 'sales') {
        return res.status(403).json({ success: false, message: 'Akses ditolak.' });
      }
    }

    const [userRows] = await staffPool.execute(
      'SELECT id, full_name, username, email, phone, role, avatar, photo, is_active, created_at, last_login FROM users WHERE id = ?',
      [id]
    );
    if (userRows.length === 0) return res.status(404).json({ success: false, message: 'User tidak ditemukan.' });

    const user = userRows[0];

    
    const [statRows] = await salesPool.execute(
      `SELECT status, COUNT(*) as count, COALESCE(SUM(nominal_qo), 0) as revenue
       FROM projects WHERE assigned_to = ? GROUP BY status`,
      [id]
    );
    const stats = { PROSPECT: 0, NEAREST: 0, ONGOING: 0, DONE: 0, LOST: 0, total: 0, revenue: 0 };
    statRows.forEach(r => {
      stats[r.status] = r.count;
      stats.total += r.count;
      if (r.status === 'DONE') stats.revenue = Number(r.revenue);
    });
    stats.winRate = (stats.DONE + stats.LOST) > 0
      ? Math.round((stats.DONE / (stats.DONE + stats.LOST)) * 100)
      : 0;

    
    const [recentProjects] = await salesPool.execute(
      `SELECT id, project_name, customer_name, status, nominal_qo, created_at, updated_at
       FROM projects WHERE assigned_to = ? ORDER BY updated_at DESC LIMIT 5`,
      [id]
    );

    res.json({
      success: true,
      data: { ...user, avatar: user.avatar || user.photo || null, stats, recentProjects }
    });
  } catch (err) {
    console.error('GET /api/users/:id/profile error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil profil user.' });
  }
});

router.put('/:id/update-profile', authenticate, async (req, res) => {
  try {
    const { id } = req.params;
    if (String(req.user.id) !== String(id)) {
      return res.status(403).json({ success: false, message: 'Hanya bisa mengedit profil sendiri.' });
    }
    const { phone, email } = req.body;
    await staffPool.execute('UPDATE users SET phone = ?, email = ? WHERE id = ?', [phone || '', email || '', id]);
    res.json({ success: true });
  } catch (err) {
    console.error('PUT /api/users/:id/update-profile error:', err);
    res.status(500).json({ success: false, message: 'Gagal memperbarui profil.' });
  }
});

router.put('/:id/toggle-active', authenticate, authorize('administrator'), async (req, res) => {
  try {
    const { id } = req.params;
    const [rows] = await staffPool.execute('SELECT id, is_active FROM users WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'User tidak ditemukan.' });

    const newStatus = rows[0].is_active ? 0 : 1;
    await staffPool.execute('UPDATE users SET is_active = ? WHERE id = ?', [newStatus, id]);

    res.json({ success: true, is_active: newStatus });
  } catch (err) {
    console.error('PUT /api/users/:id/toggle-active error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengubah status user.' });
  }
});


router.post('/', authenticate, authorize('administrator'), async (req, res) => {
  try {
    const { full_name, username, email, phone, role, password } = req.body;

    if (!full_name || !username || !password || !role) {
      return res.status(400).json({ success: false, message: 'Nama, username, password, dan role wajib diisi.' });
    }

    const allowedRoles = ['administrator', 'sales', 'technician', 'technician_manager'];
    if (!allowedRoles.includes(role)) {
      return res.status(400).json({ success: false, message: 'Role tidak valid.' });
    }

    const [existing] = await staffPool.execute('SELECT id FROM users WHERE username = ?', [username]);
    if (existing.length > 0) {
      return res.status(409).json({ success: false, message: 'Username sudah digunakan.' });
    }

    const hashedPassword = await bcrypt.hash(password, 10);
    const [result] = await staffPool.execute(
      'INSERT INTO users (full_name, username, email, phone, role, password, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)',
      [full_name, username, email || '', phone || '', role, hashedPassword]
    );

    res.json({ success: true, data: { id: result.insertId } });
  } catch (err) {
    console.error('POST /api/users error:', err);
    res.status(500).json({ success: false, message: 'Gagal membuat user.' });
  }
});


router.delete('/:id', authenticate, authorize('administrator'), async (req, res) => {
  try {
    const { id } = req.params;
    if (String(id) === String(req.user.id)) {
      return res.status(400).json({ success: false, message: 'Tidak bisa menghapus akun sendiri.' });
    }
    const [rows] = await staffPool.execute('SELECT id FROM users WHERE id = ?', [id]);
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'User tidak ditemukan.' });

    await staffPool.execute('DELETE FROM users WHERE id = ?', [id]);
    res.json({ success: true });
  } catch (err) {
    console.error('DELETE /api/users/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal menghapus user.' });
  }
});

module.exports = router;
