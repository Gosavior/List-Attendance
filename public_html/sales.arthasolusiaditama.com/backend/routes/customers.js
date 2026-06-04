const express = require('express');
const { salesPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

const router = express.Router();

router.get('/', authenticate, async (req, res) => {
  try {
    const [rows] = await salesPool.execute('SELECT id, name, company, pic_name, phone, email, address, created_by, created_at FROM customers ORDER BY name ASC');
    res.json({ success: true, data: rows });
  } catch (err) {
    console.error('GET /api/customers error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data customers.' });
  }
});

router.post('/', authenticate, authorize('sales', 'administrator'), async (req, res) => {
  try {
    const { name, company, pic_name, phone, email, address } = req.body;
    if (!name || !name.trim()) {
      return res.status(400).json({ success: false, message: 'Nama customer wajib diisi.' });
    }

    const [result] = await salesPool.execute(
      'INSERT INTO customers (name, company, pic_name, phone, email, address, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
      [name.trim(), company || null, pic_name || null, phone || null, email || null, address || null, req.user.id]
    );

    const [rows] = await salesPool.execute('SELECT id, name, company, pic_name, phone, email, address, created_by, created_at FROM customers WHERE id = ?', [result.insertId]);

    res.status(201).json({ success: true, message: 'Customer berhasil ditambahkan.', data: rows[0] });
  } catch (err) {
    console.error('POST /api/customers error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyimpan customer.' });
  }
});

router.put('/:id', authenticate, authorize('administrator'), async (req, res) => {
  try {
    const { id } = req.params;
    const { name, company, pic_name, phone, email, address } = req.body;
    if (!name || !name.trim()) {
      return res.status(400).json({ success: false, message: 'Nama customer wajib diisi.' });
    }

    const [existing] = await salesPool.execute('SELECT id FROM customers WHERE id = ?', [id]);
    if (existing.length === 0) {
      return res.status(404).json({ success: false, message: 'Customer tidak ditemukan.' });
    }

    await salesPool.execute(
      'UPDATE customers SET name = ?, company = ?, pic_name = ?, phone = ?, email = ?, address = ? WHERE id = ?',
      [name.trim(), company || null, pic_name || null, phone || null, email || null, address || null, id]
    );

    const [rows] = await salesPool.execute('SELECT id, name, company, pic_name, phone, email, address, created_by, created_at FROM customers WHERE id = ?', [id]);

    res.json({ success: true, message: 'Customer berhasil diperbarui.', data: rows[0] });
  } catch (err) {
    console.error('PUT /api/customers/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal memperbarui customer.' });
  }
});

router.get('/:id/projects', authenticate, async (req, res) => {
  try {
    const { id } = req.params;

    const [custRows] = await salesPool.execute('SELECT id, name, phone, email, address FROM customers WHERE id = ?', [id]);
    if (custRows.length === 0) return res.status(404).json({ success: false, message: 'Customer tidak ditemukan.' });
    const cust = custRows[0];

    const [projRows] = await salesPool.execute(
      `SELECT id, project_name, status, assigned_to, nominal_estimate, created_at
       FROM projects
       WHERE customer_id = ? OR customer_name = ? OR customer_email = ? OR customer_phone = ?
       ORDER BY created_at DESC`,
      [cust.id, cust.name, cust.email, cust.phone]
    );

    res.json({ success: true, data: projRows });
  } catch (err) {
    console.error('GET /api/customers/:id/projects error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil projects untuk customer.' });
  }
});
module.exports = router;
