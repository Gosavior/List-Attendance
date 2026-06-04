const express = require('express');
const { staffPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

const router = express.Router();


router.use(authenticate);



router.get('/', async (req, res) => {
  try {
    const { search, category, low_stock, page = 1, limit = 50 } = req.query;
    const offset = (parseInt(page) - 1) * parseInt(limit);

    let where = 'WHERE ms.is_active = 1';
    const params = [];

    if (search) {
      where += ' AND (ms.material_name LIKE ? OR ms.material_code LIKE ? OR ms.description LIKE ?)';
      const searchTerm = `%${search}%`;
      params.push(searchTerm, searchTerm, searchTerm);
    }

    if (category) {
      where += ' AND ms.category = ?';
      params.push(category);
    }

    if (low_stock === '1') {
      where += ' AND ms.stock_qty <= ms.min_stock AND ms.min_stock > 0';
    }

    
    const [countResult] = await staffPool.execute(
      `SELECT COUNT(*) as total FROM material_stock ms ${where}`,
      params
    );
    const total = countResult[0].total;

    
    const [items] = await staffPool.execute(
      `SELECT ms.*,
              (ms.stock_qty * ms.avg_price) as total_value,
              (ms.stock_qty <= ms.min_stock AND ms.min_stock > 0) as is_low_stock,
              (ms.actual_qty IS NOT NULL AND ms.actual_qty != ms.stock_qty) as has_discrepancy
       FROM material_stock ms
       ${where}
       ORDER BY ms.category ASC, ms.material_name ASC
       LIMIT ? OFFSET ?`,
      [...params, parseInt(limit), offset]
    );

    
    const [categories] = await staffPool.execute(
      `SELECT DISTINCT category FROM material_stock WHERE category IS NOT NULL AND category != '' AND is_active = 1 ORDER BY category`
    );

    
    const [summary] = await staffPool.execute(
      `SELECT 
        COUNT(*) as total_items,
        COALESCE(SUM(stock_qty), 0) as total_stock,
        COALESCE(SUM(stock_qty * avg_price), 0) as total_value,
        SUM(CASE WHEN stock_qty <= min_stock AND min_stock > 0 THEN 1 ELSE 0 END) as low_stock_count
       FROM material_stock WHERE is_active = 1`
    );

    
    const [monthlyIO] = await staffPool.execute(
      `SELECT 
        COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity * price_per_unit ELSE 0 END), 0) as month_in_value,
        COALESCE(SUM(CASE WHEN movement_type = 'out' THEN quantity * price_per_unit ELSE 0 END), 0) as month_out_value,
        COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END), 0) as month_in_qty,
        COALESCE(SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END), 0) as month_out_qty
       FROM stock_movements 
       WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())`
    );

    res.json({
      success: true,
      data: items,
      categories: categories.map(c => c.category),
      summary: { ...summary[0], ...monthlyIO[0] },
      pagination: {
        page: parseInt(page),
        limit: parseInt(limit),
        total,
        totalPages: Math.ceil(total / parseInt(limit))
      }
    });
  } catch (err) {
    console.error('GET /api/stock error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data stok.' });
  }
});



router.get('/:id', async (req, res) => {
  try {
    const [items] = await staffPool.execute(
      `SELECT ms.*, (ms.stock_qty * ms.avg_price) as total_value FROM material_stock ms WHERE ms.id = ? AND ms.is_active = 1`,
      [req.params.id]
    );

    if (items.length === 0) {
      return res.status(404).json({ success: false, message: 'Item tidak ditemukan.' });
    }

    
    const [movements] = await staffPool.execute(
      `SELECT sm.*, u.full_name as created_by_name
       FROM stock_movements sm
       LEFT JOIN users u ON sm.created_by = u.id
       WHERE sm.material_id = ?
       ORDER BY sm.created_at DESC
       LIMIT 20`,
      [req.params.id]
    );

    res.json({ success: true, data: { ...items[0], movements } });
  } catch (err) {
    console.error('GET /api/stock/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil detail stok.' });
  }
});



router.post('/', authorize('administrator'), async (req, res) => {
  try {
    const { material_code, material_name, category, description, unit, stock_qty, avg_price, min_stock, supplier, location } = req.body;

    if (!material_name || !material_name.trim()) {
      return res.status(400).json({ success: false, message: 'Nama material wajib diisi.' });
    }

    
    if (material_code && material_code.trim()) {
      const [existing] = await staffPool.execute(
        'SELECT id FROM material_stock WHERE material_code = ?',
        [material_code.trim()]
      );
      if (existing.length > 0) {
        return res.status(400).json({ success: false, message: `Kode material "${material_code}" sudah digunakan.` });
      }
    }

    
    {
      const [existingName] = await staffPool.execute(
        'SELECT id, material_name, unit FROM material_stock WHERE LOWER(TRIM(material_name)) = LOWER(?) AND LOWER(TRIM(unit)) = LOWER(?) AND is_active = 1',
        [material_name.trim(), (unit || 'pcs').trim()]
      );
      if (existingName.length > 0) {
        return res.json({ success: true, message: 'Material sudah ada, menggunakan yang existing.', data: { id: existingName[0].id }, existing: true });
      }
    }

    const qty = parseFloat(stock_qty) || 0;
    const price = parseFloat(avg_price) || 0;
    const totalSpent = qty * price;

    const [result] = await staffPool.execute(
      `INSERT INTO material_stock (material_code, material_name, category, description, unit, stock_qty, avg_price, last_price, total_purchased, total_spent, min_stock, supplier, location, created_by)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        material_code?.trim() || null,
        material_name.trim(),
        category?.trim() || null,
        description?.trim() || null,
        unit?.trim() || 'pcs',
        qty,
        price,
        price,
        qty,
        totalSpent,
        parseFloat(min_stock) || 0,
        supplier?.trim() || null,
        location?.trim() || null,
        req.user.id
      ]
    );

    
    if (qty > 0) {
      await staffPool.execute(
        `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, notes, created_by)
         VALUES (?, 'in', ?, ?, 0, ?, 'manual', 'Stok awal', ?)`,
        [result.insertId, qty, price, qty, req.user.id]
      );
    }

    res.json({ success: true, message: 'Material berhasil ditambahkan.', data: { id: result.insertId } });
  } catch (err) {
    console.error('POST /api/stock error:', err);
    res.status(500).json({ success: false, message: 'Gagal menambahkan material.' });
  }
});



router.put('/:id', authorize('administrator'), async (req, res) => {
  try {
    const { material_code, material_name, category, description, unit, min_stock, supplier, location } = req.body;

    if (!material_name || !material_name.trim()) {
      return res.status(400).json({ success: false, message: 'Nama material wajib diisi.' });
    }

    const [current] = await staffPool.execute('SELECT * FROM material_stock WHERE id = ? AND is_active = 1', [req.params.id]);
    if (current.length === 0) {
      return res.status(404).json({ success: false, message: 'Item tidak ditemukan.' });
    }

    
    if (material_code && material_code.trim() && material_code.trim() !== current[0].material_code) {
      const [existing] = await staffPool.execute(
        'SELECT id FROM material_stock WHERE material_code = ? AND id != ?',
        [material_code.trim(), req.params.id]
      );
      if (existing.length > 0) {
        return res.status(400).json({ success: false, message: `Kode material "${material_code}" sudah digunakan.` });
      }
    }

    await staffPool.execute(
      `UPDATE material_stock SET material_code = ?, material_name = ?, category = ?, description = ?, unit = ?, min_stock = ?, supplier = ?, location = ? WHERE id = ?`,
      [
        material_code?.trim() || null,
        material_name.trim(),
        category?.trim() || null,
        description?.trim() || null,
        unit?.trim() || 'pcs',
        parseFloat(min_stock) || 0,
        supplier?.trim() || null,
        location?.trim() || null,
        req.params.id
      ]
    );

    res.json({ success: true, message: 'Material berhasil diupdate.' });
  } catch (err) {
    console.error('PUT /api/stock/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengupdate material.' });
  }
});



router.delete('/:id', authorize('administrator'), async (req, res) => {
  try {
    const [result] = await staffPool.execute(
      'UPDATE material_stock SET is_active = 0 WHERE id = ?',
      [req.params.id]
    );

    if (result.affectedRows === 0) {
      return res.status(404).json({ success: false, message: 'Item tidak ditemukan.' });
    }

    res.json({ success: true, message: 'Material berhasil dihapus.' });
  } catch (err) {
    console.error('DELETE /api/stock/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal menghapus material.' });
  }
});



router.post('/:id/purchase', authorize('administrator'), async (req, res) => {
  try {
    const { quantity, price_per_unit, notes } = req.body;
    const qty = parseFloat(quantity);
    const price = parseFloat(price_per_unit);

    if (!qty || qty <= 0) {
      return res.status(400).json({ success: false, message: 'Jumlah harus lebih dari 0.' });
    }
    if (!price || price < 0) {
      return res.status(400).json({ success: false, message: 'Harga per unit wajib diisi.' });
    }

    const [current] = await staffPool.execute(
      'SELECT * FROM material_stock WHERE id = ? AND is_active = 1',
      [req.params.id]
    );
    if (current.length === 0) {
      return res.status(404).json({ success: false, message: 'Item tidak ditemukan.' });
    }

    const item = current[0];
    const stockBefore = parseFloat(item.stock_qty);
    const stockAfter = stockBefore + qty;

    
    const newTotalPurchased = parseFloat(item.total_purchased) + qty;
    const newTotalSpent = parseFloat(item.total_spent) + (qty * price);
    const newAvgPrice = newTotalPurchased > 0 ? newTotalSpent / newTotalPurchased : price;

    await staffPool.execute(
      `UPDATE material_stock SET stock_qty = ?, avg_price = ?, last_price = ?, total_purchased = ?, total_spent = ? WHERE id = ?`,
      [stockAfter, newAvgPrice, price, newTotalPurchased, newTotalSpent, req.params.id]
    );

    await staffPool.execute(
      `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, notes, created_by)
       VALUES (?, 'in', ?, ?, ?, ?, 'purchase', ?, ?)`,
      [req.params.id, qty, price, stockBefore, stockAfter, notes?.trim() || 'Pembelian material', req.user.id]
    );

    res.json({
      success: true,
      message: `Berhasil menambah ${qty} ${item.unit}. Harga rata-rata baru: Rp ${Math.round(newAvgPrice).toLocaleString('id-ID')}`,
      data: { stock_qty: stockAfter, avg_price: newAvgPrice, last_price: price }
    });
  } catch (err) {
    console.error('POST /api/stock/:id/purchase error:', err);
    res.status(500).json({ success: false, message: 'Gagal mencatat pembelian.' });
  }
});



router.post('/:id/adjust', authorize('administrator'), async (req, res) => {
  try {
    const { new_qty, notes } = req.body;
    const newQty = parseFloat(new_qty);

    if (isNaN(newQty) || newQty < 0) {
      return res.status(400).json({ success: false, message: 'Jumlah stok tidak valid.' });
    }

    const [current] = await staffPool.execute(
      'SELECT * FROM material_stock WHERE id = ? AND is_active = 1',
      [req.params.id]
    );
    if (current.length === 0) {
      return res.status(404).json({ success: false, message: 'Item tidak ditemukan.' });
    }

    const stockBefore = parseFloat(current[0].stock_qty);

    await staffPool.execute('UPDATE material_stock SET stock_qty = ? WHERE id = ?', [newQty, req.params.id]);

    await staffPool.execute(
      `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, notes, created_by)
       VALUES (?, 'adjustment', ?, ?, ?, ?, 'manual', ?, ?)`,
      [req.params.id, Math.abs(newQty - stockBefore), parseFloat(current[0].avg_price), stockBefore, newQty, notes?.trim() || `Penyesuaian stok: ${stockBefore} â†’ ${newQty}`, req.user.id]
    );

    res.json({ success: true, message: 'Stok berhasil disesuaikan.', data: { stock_before: stockBefore, stock_after: newQty } });
  } catch (err) {
    console.error('POST /api/stock/:id/adjust error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyesuaikan stok.' });
  }
});



router.post('/import', authorize('administrator'), async (req, res) => {
  const conn = await staffPool.getConnection();
  try {
    const { items } = req.body;

    if (!Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ success: false, message: 'Data import kosong.' });
    }

    if (items.length > 5000) {
      return res.status(400).json({ success: false, message: 'Maksimal 5000 item per import.' });
    }

    await conn.beginTransaction();

    let imported = 0;
    let updated = 0;
    let skipped = 0;
    const errors = [];

    for (let i = 0; i < items.length; i++) {
      const item = items[i];
      const rowNum = i + 1;

      const name = (item.material_name || '').toString().trim();
      if (!name) {
        skipped++;
        errors.push(`Baris ${rowNum}: Nama material kosong, dilewati.`);
        continue;
      }

      const code = (item.material_code || '').toString().trim() || null;
      const category = (item.category || '').toString().trim() || null;
      const description = (item.description || '').toString().trim() || null;
      const unit = (item.unit || 'pcs').toString().trim();
      const stockQty = parseFloat(item.stock_qty) || 0;
      const avgPrice = parseFloat(item.avg_price) || 0;
      const minStock = parseFloat(item.min_stock) || 0;
      const actualQty = item.actual_qty !== undefined && item.actual_qty !== '' ? parseFloat(item.actual_qty) : null;
      const supplier = (item.supplier || '').toString().trim() || null;
      const location = (item.location || '').toString().trim() || null;

      const totalPurchased = stockQty;
      const totalSpent = stockQty * avgPrice;

      
      let existingId = null;
      if (code) {
        const [existing] = await conn.execute(
          'SELECT id FROM material_stock WHERE material_code = ?',
          [code]
        );
        if (existing.length > 0) existingId = existing[0].id;
      }

      if (!existingId) {
        const [existing] = await conn.execute(
          'SELECT id FROM material_stock WHERE material_name = ?',
          [name]
        );
        if (existing.length > 0) existingId = existing[0].id;
      }

      if (existingId) {
        await conn.execute(
          `UPDATE material_stock SET
            material_code = COALESCE(?, material_code),
            category = COALESCE(?, category),
            description = COALESCE(?, description),
            unit = ?, stock_qty = ?, avg_price = ?, last_price = ?,
            total_purchased = ?, total_spent = ?,
            min_stock = ?, actual_qty = ?,
            supplier = COALESCE(?, supplier),
            location = COALESCE(?, location),
            is_active = 1
           WHERE id = ?`,
          [code, category, description, unit, stockQty, avgPrice, avgPrice, totalPurchased, totalSpent, minStock, actualQty, supplier, location, existingId]
        );
        updated++;
      } else {
        await conn.execute(
          `INSERT INTO material_stock (material_code, material_name, category, description, unit, stock_qty, avg_price, last_price, total_purchased, total_spent, min_stock, actual_qty, supplier, location, created_by)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
          [code, name, category, description, unit, stockQty, avgPrice, avgPrice, totalPurchased, totalSpent, minStock, actualQty, supplier, location, req.user.id]
        );
        imported++;
      }
    }

    await conn.commit();

    res.json({
      success: true,
      message: `Import selesai: ${imported} ditambahkan, ${updated} diupdate, ${skipped} dilewati.`,
      data: { imported, updated, skipped, errors: errors.slice(0, 10) }
    });
  } catch (err) {
    await conn.rollback();
    console.error('POST /api/stock/import error:', err);
    res.status(500).json({ success: false, message: 'Gagal import data stok.' });
  } finally {
    conn.release();
  }
});



router.get('/movements/history', async (req, res) => {
  try {
    const { material_id, type, limit = 50, page = 1 } = req.query;
    const offset = (parseInt(page) - 1) * parseInt(limit);

    let where = 'WHERE 1=1';
    const params = [];

    if (material_id) {
      where += ' AND sm.material_id = ?';
      params.push(parseInt(material_id));
    }

    if (type) {
      where += ' AND sm.movement_type = ?';
      params.push(type);
    }

    const [movements] = await staffPool.execute(
      `SELECT sm.*, ms.material_name, ms.material_code, ms.unit, u.full_name as created_by_name
       FROM stock_movements sm
       JOIN material_stock ms ON sm.material_id = ms.id
       LEFT JOIN users u ON sm.created_by = u.id
       ${where}
       ORDER BY sm.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, parseInt(limit), offset]
    );

    res.json({ success: true, data: movements });
  } catch (err) {
    console.error('GET /api/stock/movements/history error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil riwayat pergerakan stok.' });
  }
});

module.exports = router;
