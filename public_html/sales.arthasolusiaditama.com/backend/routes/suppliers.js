const express = require('express');
const { staffPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

const router = express.Router();


router.get('/', authenticate, authorize('administrator', 'direktur'), async (req, res) => {
  try {
    const [rows] = await staffPool.execute(
      `SELECT s.id, s.name, s.address, s.maps_url, s.phone, s.notes, s.is_active, s.created_by, s.created_at, s.updated_at,
              u.full_name as created_by_name
       FROM suppliers s
       LEFT JOIN users u ON s.created_by = u.id
       WHERE s.is_active = 1
       ORDER BY s.name ASC`
    );
    res.json({ success: true, data: rows });
  } catch (err) {
    console.error('GET /api/suppliers error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data suppliers.' });
  }
});


router.post('/', authenticate, authorize('administrator', 'direktur'), async (req, res) => {
  try {
    const { name, address, maps_url, phone, notes } = req.body;
    if (!name || !name.trim()) {
      return res.status(400).json({ success: false, message: 'Nama supplier wajib diisi.' });
    }

    const [existing] = await staffPool.execute('SELECT id FROM suppliers WHERE name = ?', [name.trim()]);
    if (existing.length > 0) {
      return res.status(409).json({ success: false, message: 'Supplier dengan nama ini sudah ada.' });
    }

    const [result] = await staffPool.execute(
      'INSERT INTO suppliers (name, address, maps_url, phone, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)',
      [name.trim(), address || null, maps_url || null, phone || null, notes || null, req.user.id]
    );

    const [rows] = await staffPool.execute(
      `SELECT s.*, u.full_name as created_by_name FROM suppliers s LEFT JOIN users u ON s.created_by = u.id WHERE s.id = ?`,
      [result.insertId]
    );

    res.status(201).json({ success: true, message: 'Supplier berhasil ditambahkan.', data: rows[0] });
  } catch (err) {
    console.error('POST /api/suppliers error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyimpan supplier.' });
  }
});


router.put('/:id', authenticate, authorize('administrator', 'direktur'), async (req, res) => {
  try {
    const { id } = req.params;
    const { name, address, maps_url, phone, notes, is_active } = req.body;
    if (!name || !name.trim()) {
      return res.status(400).json({ success: false, message: 'Nama supplier wajib diisi.' });
    }

    const [existing] = await staffPool.execute('SELECT id FROM suppliers WHERE id = ?', [id]);
    if (existing.length === 0) {
      return res.status(404).json({ success: false, message: 'Supplier tidak ditemukan.' });
    }

    
    const [dup] = await staffPool.execute('SELECT id FROM suppliers WHERE name = ? AND id != ?', [name.trim(), id]);
    if (dup.length > 0) {
      return res.status(409).json({ success: false, message: 'Supplier dengan nama ini sudah ada.' });
    }

    await staffPool.execute(
      'UPDATE suppliers SET name = ?, address = ?, maps_url = ?, phone = ?, notes = ?, is_active = ? WHERE id = ?',
      [name.trim(), address || null, maps_url || null, phone || null, notes || null, is_active !== undefined ? is_active : 1, id]
    );

    const [rows] = await staffPool.execute(
      `SELECT s.*, u.full_name as created_by_name FROM suppliers s LEFT JOIN users u ON s.created_by = u.id WHERE s.id = ?`,
      [id]
    );

    res.json({ success: true, message: 'Supplier berhasil diperbarui.', data: rows[0] });
  } catch (err) {
    console.error('PUT /api/suppliers/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal memperbarui supplier.' });
  }
});


router.delete('/:id', authenticate, authorize('administrator', 'direktur'), async (req, res) => {
  try {
    const { id } = req.params;
    const [existing] = await staffPool.execute('SELECT id, name FROM suppliers WHERE id = ?', [id]);
    if (existing.length === 0) {
      return res.status(404).json({ success: false, message: 'Supplier tidak ditemukan.' });
    }

    await staffPool.execute('UPDATE suppliers SET is_active = 0 WHERE id = ?', [id]);
    res.json({ success: true, message: `Supplier "${existing[0].name}" berhasil dinonaktifkan.` });
  } catch (err) {
    console.error('DELETE /api/suppliers/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal menghapus supplier.' });
  }
});

module.exports = router;
