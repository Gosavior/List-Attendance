const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const { salesPool, staffPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

const router = express.Router();


const receiptDir = path.join(__dirname, '..', 'uploads', 'receipts');
if (!fs.existsSync(receiptDir)) fs.mkdirSync(receiptDir, { recursive: true });

const receiptStorage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, receiptDir),
  filename: (req, file, cb) => {
    const safeName = file.originalname.replace(/[^a-zA-Z0-9._\-()]/g, '_');
    cb(null, `${Date.now()}-${safeName}`);
  },
});

const receiptUpload = multer({
  storage: receiptStorage,
  limits: { fileSize: 10 * 1024 * 1024 },
  fileFilter: (req, file, cb) => {
    const allowed = /png|jpg|jpeg|pdf/;
    const ext = path.extname(file.originalname).toLowerCase().replace('.', '');
    cb(null, allowed.test(ext));
  },
});



router.post('/upload-receipt', authenticate, receiptUpload.single('receipt'), (req, res) => {
  if (!req.file) return res.status(400).json({ success: false, message: 'File tidak ditemukan.' });
  res.json({ success: true, filename: req.file.filename });
});



router.post('/', authenticate, authorize('sales', 'administrator'), async (req, res) => {
  try {
    const { project_id, rab_number, notes, sectionA, sectionB, sectionC, sectionD, rab_type } = req.body;

    if (!project_id) {
      return res.status(400).json({ success: false, message: 'project_id wajib diisi.' });
    }

    
    const [projects] = await salesPool.execute('SELECT id, assigned_to, status, ao_number FROM projects WHERE id = ?', [project_id]);
    if (projects.length === 0) {
      return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });
    }

    const project = projects[0];
    if (req.user.role === 'sales' && project.assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    
    let finalRabNumber = rab_number;
    if (!finalRabNumber) {
      if (rab_type === 'nyata') {
        
        if (project.ao_number && project.ao_number.toString().trim()) {
          const ao = project.ao_number.toString().trim();
          finalRabNumber = ao.replace(/^AO-/i, 'RAB-');
        }
        
      }

      if (!finalRabNumber) {
        if (rab_type === 'bayangan') {
          
          const rand = Math.floor(100000 + Math.random() * 900000);
          finalRabNumber = `RAB-BAY-${rand}`;
        } else if (rab_type === 'kasar') {
          const rand = Math.floor(100000 + Math.random() * 900000);
          finalRabNumber = `RAB-KSR-${rand}`;
        } else {
          
          const currentYear = new Date().getFullYear();
          const [maxRows] = await salesPool.execute(
            `SELECT rab_number FROM rab WHERE rab_number LIKE ? ORDER BY id DESC LIMIT 1`,
            [`RAB-%-ASA-${currentYear}`]
          );
          let nextNum = 1;
          if (maxRows.length > 0 && maxRows[0].rab_number) {
            const match = maxRows[0].rab_number.match(/^RAB-(\d+)-ASA-/);
            if (match) nextNum = parseInt(match[1], 10) + 1;
          }
          finalRabNumber = `RAB-${String(nextNum).padStart(3, '0')}-ASA-${currentYear}`;
        }
      }
    }

    
    const calcRowTotal = (row) => (parseFloat(row.qty) || 0) * (parseFloat(row.price) || 0);

    
    const sectionBNames = new Set();
    (sectionB || []).forEach(r => {
      const name = (r.item || '').trim().toLowerCase();
      if (name) sectionBNames.add(name);
    });

    
    const totalA = (sectionA || []).reduce((sum, r) => {
      const name = (r.item || '').trim().toLowerCase();
      if (name && sectionBNames.has(name)) return sum;
      return sum + calcRowTotal(r);
    }, 0);

    let totalBWarehouse = 0;
    let totalBBuy = 0;
    (sectionB || []).forEach(r => {
      const needed = parseFloat(r.qtyNeeded) || 0;
      const available = parseFloat(r.qtyAvailable) || 0;
      const price = parseFloat(r.price) || 0;
      const fromWarehouse = Math.min(needed, available);
      const toBuy = Math.max(0, needed - available);
      totalBWarehouse += fromWarehouse * price;
      totalBBuy += toBuy * price;
    });

    const totalC = (sectionC || []).reduce((sum, r) => sum + calcRowTotal(r), 0);
    const totalD = (sectionD || []).reduce((sum, r) => sum + calcRowTotal(r), 0);
    const grandTotal = totalA + totalBBuy + totalC + totalD;

    
    const rabTypeValue = rab_type === 'bayangan' ? 'bayangan' : 'nyata';
    const [result] = await salesPool.execute(
      `INSERT INTO rab (project_id, rab_number, status, rab_type, total_section_a, total_section_b_warehouse, total_section_b_buy, total_section_c, total_section_d, grand_total, notes, created_by)
       VALUES (?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [project_id, finalRabNumber, rabTypeValue, totalA, totalBWarehouse, totalBBuy, totalC, totalD, grandTotal, notes || null, req.user.id]
    );
    const rabId = result.insertId;

    
    const insertItems = async (items, section, isB = false) => {
      for (let i = 0; i < items.length; i++) {
        const row = items[i];
        const itemName = row.item || row.itemName || '';
        if (!itemName.trim()) continue;

        if (isB) {
          await salesPool.execute(
            `INSERT INTO rab_items (rab_id, section, item_order, item_name, qty_needed, qty_available, unit, price, store_name, receipt_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            [rabId, section, i, itemName, parseFloat(row.qtyNeeded) || 0, parseFloat(row.qtyAvailable) || 0, row.unit || 'pcs', parseFloat(row.price) || 0, row.store || null, row.receiptUrl || null]
          );
        } else {
          await salesPool.execute(
            `INSERT INTO rab_items (rab_id, section, item_order, item_name, qty, unit, price, store_name, receipt_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            [rabId, section, i, itemName, parseFloat(row.qty) || 0, row.unit || 'pcs', parseFloat(row.price) || 0, row.store || null, row.receiptUrl || null]
          );
        }
      }
    };

    if (sectionA?.length) await insertItems(sectionA, 'A');
    if (sectionB?.length) await insertItems(sectionB, 'B', true);
    if (sectionC?.length) await insertItems(sectionC, 'C');
    if (sectionD?.length) await insertItems(sectionD, 'D');

    
    await salesPool.execute('UPDATE projects SET rab_id = ?, updated_at = NOW() WHERE id = ?', [rabId, project_id]);

    res.json({
      success: true,
      message: 'RAB berhasil disimpan.',
      data: { id: rabId, grand_total: grandTotal, rab_number: finalRabNumber },
    });
  } catch (err) {
    console.error('POST /api/rab error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyimpan RAB.' });
  }
});



router.get('/material-items/:projectId', authenticate, async (req, res) => {
  try {
    const { projectId } = req.params;

    
    const [projects] = await salesPool.execute('SELECT id, assigned_to FROM projects WHERE id = ?', [projectId]);
    if (projects.length === 0) {
      return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });
    }
    if (req.user.role === 'sales' && projects[0].assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    
    const [items] = await staffPool.execute(
      `SELECT mri.material_name, mri.quantity, mri.price, mri.source_type,
              mri.qty_from_warehouse, mri.qty_to_purchase, mri.store_name, mri.notes,
              mr.id as request_id, mr.status as request_status, mr.created_at as request_date,
              u.full_name as requester_name
       FROM material_request_items mri
       JOIN material_requests mr ON mri.request_id = mr.id
       JOIN users u ON mr.user_id = u.id
       WHERE mr.project_id = ? AND mr.status IN ('admin_approved', 'driver_pickup', 'delivered', 'completed')
       ORDER BY mr.created_at DESC, mri.id ASC`,
      [projectId]
    );

    
    const warehouseMap = new Map();
    const purchaseMap = new Map();

    for (const item of items) {
      const key = item.material_name.trim().toLowerCase();
      const qty = parseInt(item.quantity) || 0;
      const price = parseFloat(item.price) || 0;
      const fromWarehouse = item.qty_from_warehouse != null ? parseInt(item.qty_from_warehouse) : 0;
      const toPurchase = item.qty_to_purchase != null ? parseInt(item.qty_to_purchase) : 0;

      
      const hasNewData = item.qty_from_warehouse != null || item.qty_to_purchase != null;

      if (hasNewData) {
        
        if (fromWarehouse > 0) {
          if (warehouseMap.has(key)) {
            const existing = warehouseMap.get(key);
            existing.qtyNeeded += qty;
            existing.qtyAvailable += fromWarehouse;
            existing.totalPrice += price * qty;
          } else {
            warehouseMap.set(key, {
              item: item.material_name.trim(),
              qtyNeeded: qty,
              qtyAvailable: fromWarehouse,
              unit: 'pcs',
              price: price,
              totalPrice: price * qty,
            });
          }
        }
        if (toPurchase > 0) {
          if (purchaseMap.has(key)) {
            const existing = purchaseMap.get(key);
            existing.qty += toPurchase;
            existing.totalPrice += price * toPurchase;
          } else {
            purchaseMap.set(key, {
              item: item.material_name.trim(),
              qty: toPurchase,
              unit: 'pcs',
              price: price,
              totalPrice: price * toPurchase,
              store: item.store_name || '',
            });
          }
        }
      } else {
        
        const map = item.source_type === 'purchase' ? purchaseMap : warehouseMap;
        if (map.has(key)) {
          const existing = map.get(key);
          if (item.source_type === 'purchase') {
            existing.qty += qty;
            existing.totalPrice += price * qty;
          } else {
            existing.qtyNeeded += qty;
            existing.qtyAvailable += qty;
            existing.totalPrice += price * qty;
          }
        } else {
          if (item.source_type === 'purchase') {
            map.set(key, { item: item.material_name.trim(), qty, unit: 'pcs', price, totalPrice: price * qty, store: '' });
          } else {
            map.set(key, { item: item.material_name.trim(), qtyNeeded: qty, qtyAvailable: qty, unit: 'pcs', price, totalPrice: price * qty });
          }
        }
      }
    }

    
    const sectionB = Array.from(warehouseMap.values()).map(i => ({
      item: i.item,
      qtyNeeded: String(i.qtyNeeded),
      qtyAvailable: String(i.qtyAvailable),
      unit: i.unit,
      price: String(i.qtyNeeded > 0 ? Math.round(i.totalPrice / i.qtyNeeded) : 0),
    }));

    
    const sectionA = Array.from(purchaseMap.values()).map(i => ({
      item: i.item,
      qty: String(i.qty),
      unit: i.unit,
      price: String(i.qty > 0 ? Math.round(i.totalPrice / i.qty) : 0),
      store: i.store || '',
    }));

    res.json({
      success: true,
      data: { sectionA, sectionB, totalItems: items.length },
    });
  } catch (err) {
    console.error('GET /api/rab/material-items/:projectId error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data material request.' });
  }
});



router.get('/worker-costs/:projectId', authenticate, async (req, res) => {
  try {
    const { projectId } = req.params;

    
    const [projects] = await salesPool.execute('SELECT id, assigned_to FROM projects WHERE id = ?', [projectId]);
    if (projects.length === 0) {
      return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });
    }
    if (req.user.role === 'sales' && projects[0].assigned_to !== req.user.id) {
      return res.status(403).json({ success: false, message: 'Akses ditolak.' });
    }

    
    const [rows] = await staffPool.execute(
      `SELECT u.full_name, u.role,
              COUNT(*) AS total_days, pdu.daily_rate,
              SUM(pdu.daily_rate) AS total_cost
       FROM project_daily_updates pdu
       JOIN users u ON u.id = pdu.user_id
       WHERE pdu.project_id = ?
       GROUP BY pdu.user_id, pdu.daily_rate
       ORDER BY u.full_name`,
      [projectId]
    );

    
    const sectionC = rows.map(r => ({
      item: `${r.full_name} (${r.role === 'technician' ? 'Teknisi' : r.role === 'internship' ? 'Magang' : r.role})`,
      qty: String(r.total_days),
      unit: 'hari',
      price: String(r.daily_rate),
    }));

    res.json({ success: true, data: { sectionC, totalWorkers: rows.length } });
  } catch (err) {
    console.error('GET /api/rab/worker-costs/:projectId error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data biaya pekerja.' });
  }
});



router.get('/:id', authenticate, async (req, res) => {
  try {
    const { id } = req.params;
    const [rabs] = await salesPool.execute('SELECT * FROM rab WHERE id = ?', [id]);
    if (rabs.length === 0) {
      return res.status(404).json({ success: false, message: 'RAB tidak ditemukan.' });
    }

    const rab = rabs[0];

    
    if (req.user.role === 'sales') {
      const [projects] = await salesPool.execute('SELECT assigned_to FROM projects WHERE id = ?', [rab.project_id]);
      if (projects.length > 0 && projects[0].assigned_to !== req.user.id) {
        return res.status(403).json({ success: false, message: 'Akses ditolak.' });
      }
    }

    const [items] = await salesPool.execute('SELECT * FROM rab_items WHERE rab_id = ? ORDER BY section, item_order', [id]);

    const sectionA = items.filter(i => i.section === 'A').map(i => ({ item: i.item_name, qty: i.qty, unit: i.unit, price: i.price, store: i.store_name || '', receipt: i.receipt_file || '' }));
    const sectionB = items.filter(i => i.section === 'B').map(i => ({ item: i.item_name, qtyNeeded: i.qty_needed, qtyAvailable: i.qty_available, unit: i.unit, price: i.price, store: i.store_name || '', receipt: i.receipt_file || '' }))
    const sectionC = items.filter(i => i.section === 'C').map(i => ({ item: i.item_name, qty: i.qty, unit: i.unit, price: i.price }));
    const sectionD = items.filter(i => i.section === 'D').map(i => ({ item: i.item_name, qty: i.qty, unit: i.unit, price: i.price, store: i.store_name || '', receipt: i.receipt_file || '' }));

    res.json({
      success: true,
      data: { ...rab, sectionA, sectionB, sectionC, sectionD },
    });
  } catch (err) {
    console.error('GET /api/rab/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil RAB.' });
  }
});



router.get('/project/:projectId', authenticate, async (req, res) => {
  try {
    const { projectId } = req.params;
    const { type } = req.query;

    let query = 'SELECT * FROM rab WHERE project_id = ?';
    const params = [projectId];
    if (type === 'bayangan' || type === 'nyata') {
      query += ' AND rab_type = ?';
      params.push(type);
    }
    query += ' ORDER BY created_at DESC';

    const [rabs] = await salesPool.execute(query, params);

    if (rabs.length === 0) {
      return res.json({ success: true, data: null });
    }

    const rab = rabs[0];
    const [items] = await salesPool.execute('SELECT * FROM rab_items WHERE rab_id = ? ORDER BY section, item_order', [rab.id]);

    const sectionA = items.filter(i => i.section === 'A').map(i => ({ item: i.item_name, qty: i.qty, unit: i.unit, price: i.price, store: i.store_name || '', receipt: i.receipt_file || '' }));
    const sectionB = items.filter(i => i.section === 'B').map(i => ({ item: i.item_name, qtyNeeded: i.qty_needed, qtyAvailable: i.qty_available, unit: i.unit, price: i.price, store: i.store_name || '', receipt: i.receipt_file || '' }))
    const sectionC = items.filter(i => i.section === 'C').map(i => ({ item: i.item_name, qty: i.qty, unit: i.unit, price: i.price }));
    const sectionD = items.filter(i => i.section === 'D').map(i => ({ item: i.item_name, qty: i.qty, unit: i.unit, price: i.price, store: i.store_name || '', receipt: i.receipt_file || '' }));

    res.json({
      success: true,
      data: { ...rab, sectionA, sectionB, sectionC, sectionD },
    });
  } catch (err) {
    console.error('GET /api/rab/project/:projectId error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil RAB.' });
  }
});



router.put('/:id', authenticate, authorize('sales', 'administrator'), async (req, res) => {
  try {
    const { id } = req.params;
    const { rab_number, notes, sectionA, sectionB, sectionC, sectionD } = req.body;

    const [rabs] = await salesPool.execute('SELECT * FROM rab WHERE id = ?', [id]);
    if (rabs.length === 0) {
      return res.status(404).json({ success: false, message: 'RAB tidak ditemukan.' });
    }

    const rab = rabs[0];

    
    if (req.user.role === 'sales') {
      const [projects] = await salesPool.execute('SELECT assigned_to FROM projects WHERE id = ?', [rab.project_id]);
      if (projects.length > 0 && projects[0].assigned_to !== req.user.id) {
        return res.status(403).json({ success: false, message: 'Akses ditolak.' });
      }
    }

    
    const calcRowTotal = (row) => (parseFloat(row.qty) || 0) * (parseFloat(row.price) || 0);

    
    const sectionBNames = new Set();
    (sectionB || []).forEach(r => {
      const name = (r.item || '').trim().toLowerCase();
      if (name) sectionBNames.add(name);
    });

    
    const totalA = (sectionA || []).reduce((sum, r) => {
      const name = (r.item || '').trim().toLowerCase();
      if (name && sectionBNames.has(name)) return sum;
      return sum + calcRowTotal(r);
    }, 0);

    let totalBWarehouse = 0;
    let totalBBuy = 0;
    (sectionB || []).forEach(r => {
      const needed = parseFloat(r.qtyNeeded) || 0;
      const available = parseFloat(r.qtyAvailable) || 0;
      const price = parseFloat(r.price) || 0;
      totalBWarehouse += Math.min(needed, available) * price;
      totalBBuy += Math.max(0, needed - available) * price;
    });

    const totalC = (sectionC || []).reduce((sum, r) => sum + calcRowTotal(r), 0);
    const totalD = (sectionD || []).reduce((sum, r) => sum + calcRowTotal(r), 0);
    const grandTotal = totalA + totalBBuy + totalC + totalD;

    
    let finalRabNumber = rab_number;
    if (rab.rab_type === 'nyata') {
      const [projRows] = await salesPool.execute('SELECT ao_number FROM projects WHERE id = ?', [rab.project_id]);
      if (projRows.length > 0 && projRows[0].ao_number && projRows[0].ao_number.toString().trim()) {
        const ao = projRows[0].ao_number.toString().trim();
        finalRabNumber = ao.replace(/^AO-/i, 'RAB-');
      }
    }

    
    await salesPool.execute(
      `UPDATE rab SET rab_number = ?, total_section_a = ?, total_section_b_warehouse = ?, total_section_b_buy = ?, total_section_c = ?, total_section_d = ?, grand_total = ?, notes = ?, updated_at = NOW() WHERE id = ?`,
      [finalRabNumber || null, totalA, totalBWarehouse, totalBBuy, totalC, totalD, grandTotal, notes || null, id]
    );

    
    await salesPool.execute('DELETE FROM rab_items WHERE rab_id = ?', [id]);

    const insertItems = async (items, section, isB = false) => {
      for (let i = 0; i < items.length; i++) {
        const row = items[i];
        const itemName = row.item || '';
        if (!itemName.trim()) continue;

        if (isB) {
          await salesPool.execute(
            `INSERT INTO rab_items (rab_id, section, item_order, item_name, qty_needed, qty_available, unit, price, store_name, receipt_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            [id, section, i, itemName, parseFloat(row.qtyNeeded) || 0, parseFloat(row.qtyAvailable) || 0, row.unit || 'pcs', parseFloat(row.price) || 0, row.store || null, row.receiptUrl || null]
          );
        } else {
          await salesPool.execute(
            `INSERT INTO rab_items (rab_id, section, item_order, item_name, qty, unit, price, store_name, receipt_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            [id, section, i, itemName, parseFloat(row.qty) || 0, row.unit || 'pcs', parseFloat(row.price) || 0, row.store || null, row.receiptUrl || null]
          );
        }
      }
    };

    if (sectionA?.length) await insertItems(sectionA, 'A');
    if (sectionB?.length) await insertItems(sectionB, 'B', true);
    if (sectionC?.length) await insertItems(sectionC, 'C');
    if (sectionD?.length) await insertItems(sectionD, 'D');

    res.json({
      success: true,
      message: 'RAB berhasil diupdate.',
      data: { id: parseInt(id), grand_total: grandTotal },
    });
  } catch (err) {
    console.error('PUT /api/rab/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate RAB.' });
  }
});



router.get('/', authenticate, async (req, res) => {
  try {
    let query = `
      SELECT r.*, p.project_name, p.customer_name, p.status as project_status, p.assigned_to,
             u.full_name as sales_name
      FROM rab r
      JOIN projects p ON r.project_id = p.id
      LEFT JOIN arth_Staff.users u ON p.assigned_to = u.id
    `;
    const params = [];

    if (req.user.role === 'sales') {
      query += ' WHERE p.assigned_to = ?';
      params.push(req.user.id);
    }

    query += ' ORDER BY r.updated_at DESC';

    const [rows] = await salesPool.execute(query, params);

    res.json({ success: true, data: rows });
  } catch (err) {
    console.error('GET /api/rab error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data RAB.' });
  }
});

module.exports = router;
