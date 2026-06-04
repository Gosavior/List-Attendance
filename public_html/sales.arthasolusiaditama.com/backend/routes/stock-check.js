const express = require('express');
const { staffPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

const router = express.Router();

router.use(authenticate);



router.get('/', async (req, res) => {
  try {
    const [rows] = await staffPool.execute(`
      SELECT sc.*, u.full_name as created_by_name
      FROM stock_checks sc
      LEFT JOIN users u ON u.id = sc.created_by
      ORDER BY sc.created_at DESC
    `);
    res.json({ success: true, data: rows });
  } catch (err) {
    console.error('GET /api/stock-check error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat data.' });
  }
});



router.post('/', authorize('administrator'), async (req, res) => {
  const conn = await staffPool.getConnection();
  try {
    const { check_name, notes, source } = req.body;
    if (!check_name || !check_name.trim()) {
      return res.status(400).json({ success: false, message: 'Nama pengecekan wajib diisi.' });
    }

    await conn.beginTransaction();

    const [result] = await conn.execute(
      `INSERT INTO stock_checks (check_name, check_date, status, source_type, notes, created_by) VALUES (?, CURDATE(), 'draft', ?, ?, ?)`,
      [check_name.trim(), source === 'excel' ? 'excel' : 'stock', notes?.trim() || null, req.user.id]
    );
    const checkId = result.insertId;

    
    if (source !== 'excel') {
      const [stockItems] = await conn.execute(
        `SELECT id, material_code, material_name, unit, category, description, stock_qty, avg_price, min_stock
         FROM material_stock WHERE is_active = 1 ORDER BY category ASC, material_name ASC`
      );

      if (stockItems.length > 0) {
        const values = stockItems.map((item, i) => [
          checkId, item.id, item.material_code, item.material_name,
          item.unit, item.category, item.description,
          parseFloat(item.stock_qty) || 0, parseFloat(item.avg_price) || 0, parseFloat(item.min_stock) || 0, i + 1
        ]);

        const placeholders = values.map(() => '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)').join(', ');
        await conn.execute(
          `INSERT INTO stock_check_items (check_id, material_stock_id, material_code, material_name, unit, category, description, recorded_qty, avg_price, min_stock, sort_order) VALUES ${placeholders}`,
          values.flat()
        );

        await conn.execute(
          `UPDATE stock_checks SET total_items = ?, status = 'in_progress' WHERE id = ?`,
          [stockItems.length, checkId]
        );
      }
    }

    await conn.commit();
    res.json({ success: true, message: 'Sesi pengecekan berhasil dibuat.', data: { id: checkId } });
  } catch (err) {
    await conn.rollback();
    console.error('POST /api/stock-check error:', err);
    res.status(500).json({ success: false, message: 'Gagal membuat sesi pengecekan.' });
  } finally {
    conn.release();
  }
});



router.post('/:id/import', authorize('administrator'), async (req, res) => {
  const conn = await staffPool.getConnection();
  try {
    const { items } = req.body;
    const checkId = req.params.id;

    const [check] = await conn.execute('SELECT * FROM stock_checks WHERE id = ?', [checkId]);
    if (check.length === 0) {
      return res.status(404).json({ success: false, message: 'Sesi tidak ditemukan.' });
    }

    if (!Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ success: false, message: 'Data import kosong.' });
    }

    await conn.beginTransaction();

    
    await conn.execute('DELETE FROM stock_check_items WHERE check_id = ?', [checkId]);

    let sortOrder = 0;
    let checkedCount = 0;
    for (const item of items) {
      const name = (item.material_name || '').toString().trim();
      if (!name) continue;
      sortOrder++;

      
      let stockId = null;
      const code = (item.material_code || '').toString().trim();
      if (code) {
        const [match] = await conn.execute(
          'SELECT id FROM material_stock WHERE material_code = ? AND is_active = 1 LIMIT 1', [code]
        );
        if (match.length > 0) stockId = match[0].id;
      }
      if (!stockId) {
        const [match] = await conn.execute(
          'SELECT id FROM material_stock WHERE material_name = ? AND is_active = 1 LIMIT 1', [name]
        );
        if (match.length > 0) stockId = match[0].id;
      }

      await conn.execute(
        `INSERT INTO stock_check_items (check_id, material_stock_id, material_code, material_name, unit, category, description, recorded_qty, actual_qty, difference, status, avg_price, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          checkId, stockId, code || null, name,
          (item.unit || '').toString().trim() || null,
          (item.category || '').toString().trim() || null,
          (item.description || '').toString().trim() || null,
          parseFloat(item.recorded_qty) || 0,
          item.actual_qty !== undefined && item.actual_qty !== '' ? parseFloat(item.actual_qty) : null,
          item.actual_qty !== undefined && item.actual_qty !== '' ? (parseFloat(item.actual_qty) - (parseFloat(item.recorded_qty) || 0)) : null,
          item.actual_qty !== undefined && item.actual_qty !== '' ? 'checked' : 'pending',
          parseFloat(item.avg_price) || 0,
          sortOrder
        ]
      );

      if (item.actual_qty !== undefined && item.actual_qty !== '') checkedCount++;
    }

    const discrepancyCount = checkedCount > 0 ? (
      await conn.execute(
        `SELECT COUNT(*) as cnt FROM stock_check_items WHERE check_id = ? AND status = 'checked' AND difference != 0`, [checkId]
      )
    )[0][0].cnt : 0;

    await conn.execute(
      `UPDATE stock_checks SET total_items = ?, checked_items = ?, discrepancy_count = ?, status = 'in_progress' WHERE id = ?`,
      [sortOrder, checkedCount, discrepancyCount, checkId]
    );

    await conn.commit();
    res.json({ success: true, message: `${sortOrder} item berhasil diimport.` });
  } catch (err) {
    await conn.rollback();
    console.error('POST /api/stock-check/:id/import error:', err);
    res.status(500).json({ success: false, message: 'Gagal import data.' });
  } finally {
    conn.release();
  }
});



router.get('/:id', async (req, res) => {
  try {
    const [check] = await staffPool.execute(
      `SELECT sc.*, u.full_name as created_by_name FROM stock_checks sc LEFT JOIN users u ON u.id = sc.created_by WHERE sc.id = ?`,
      [req.params.id]
    );
    if (check.length === 0) {
      return res.status(404).json({ success: false, message: 'Sesi tidak ditemukan.' });
    }

    const { search, status, category, page = 1, limit = 50 } = req.query;
    const offset = (parseInt(page) - 1) * parseInt(limit);
    let where = 'WHERE sci.check_id = ?';
    const params = [req.params.id];

    if (search) {
      where += ' AND (sci.material_name LIKE ? OR sci.material_code LIKE ?)';
      params.push(`%${search}%`, `%${search}%`);
    }
    if (status) {
      where += ' AND sci.status = ?';
      params.push(status);
    }
    if (category) {
      where += ' AND sci.category = ?';
      params.push(category);
    }

    const [countResult] = await staffPool.execute(
      `SELECT COUNT(*) as total FROM stock_check_items sci ${where}`, params
    );

    const [items] = await staffPool.execute(
      `SELECT sci.* FROM stock_check_items sci ${where} ORDER BY sci.sort_order ASC LIMIT ? OFFSET ?`,
      [...params, parseInt(limit), offset]
    );

    const [categories] = await staffPool.execute(
      `SELECT DISTINCT category FROM stock_check_items WHERE check_id = ? AND category IS NOT NULL AND category != '' ORDER BY category`,
      [req.params.id]
    );

    res.json({
      success: true,
      data: {
        ...check[0],
        items,
        categories: categories.map(c => c.category),
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total: countResult[0].total,
          totalPages: Math.ceil(countResult[0].total / parseInt(limit))
        }
      }
    });
  } catch (err) {
    console.error('GET /api/stock-check/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat data.' });
  }
});



router.get('/:id/next', async (req, res) => {
  try {
    const { sort } = req.query;
    let orderBy = 'sort_order ASC';
    if (sort === 'name') orderBy = 'material_name ASC';
    else if (sort === 'category') orderBy = 'category ASC, material_name ASC';

    const [items] = await staffPool.execute(
      `SELECT * FROM stock_check_items WHERE check_id = ? AND status = 'pending' ORDER BY ${orderBy} LIMIT 1`,
      [req.params.id]
    );

    
    const [progress] = await staffPool.execute(
      `SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status != 'pending' THEN 1 ELSE 0 END) as checked
       FROM stock_check_items WHERE check_id = ?`,
      [req.params.id]
    );

    res.json({
      success: true,
      data: items.length > 0 ? items[0] : null,
      progress: {
        total: progress[0].total,
        checked: parseInt(progress[0].checked) || 0
      }
    });
  } catch (err) {
    console.error('GET /api/stock-check/:id/next error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat item.' });
  }
});



router.get('/:id/prev', async (req, res) => {
  try {
    const { sort } = req.query;
    let orderBy = 'sort_order DESC';
    if (sort === 'name') orderBy = 'material_name DESC';
    else if (sort === 'category') orderBy = 'category DESC, material_name DESC';

    const [items] = await staffPool.execute(
      `SELECT * FROM stock_check_items WHERE check_id = ? AND status IN ('checked','skipped') ORDER BY checked_at DESC LIMIT 1`,
      [req.params.id]
    );

    if (items.length === 0) {
      return res.json({ success: false, message: 'Tidak ada item sebelumnya.' });
    }

    
    await staffPool.execute(
      `UPDATE stock_check_items SET status = 'pending', actual_qty = NULL, difference = NULL, checked_at = NULL, checked_by = NULL, notes = NULL, adjustment_reason = NULL, adjustment_detail = NULL, purchase_store = NULL, purchase_price = NULL WHERE id = ?`,
      [items[0].id]
    );

    
    const [stats] = await staffPool.execute(
      `SELECT 
        SUM(CASE WHEN status != 'pending' THEN 1 ELSE 0 END) as checked,
        SUM(CASE WHEN status = 'checked' AND difference != 0 THEN 1 ELSE 0 END) as discrepancies
       FROM stock_check_items WHERE check_id = ?`,
      [req.params.id]
    );
    await staffPool.execute(
      `UPDATE stock_checks SET checked_items = ?, discrepancy_count = ? WHERE id = ?`,
      [parseInt(stats[0].checked) || 0, parseInt(stats[0].discrepancies) || 0, req.params.id]
    );

    
    const [progress] = await staffPool.execute(
      `SELECT COUNT(*) as total, SUM(CASE WHEN status != 'pending' THEN 1 ELSE 0 END) as checked FROM stock_check_items WHERE check_id = ?`,
      [req.params.id]
    );

    res.json({
      success: true,
      data: items[0],
      progress: { total: progress[0].total, checked: parseInt(progress[0].checked) || 0 }
    });
  } catch (err) {
    console.error('GET /api/stock-check/:id/prev error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat item sebelumnya.' });
  }
});



router.get('/:id/items/:itemId', async (req, res) => {
  try {
    const [items] = await staffPool.execute(
      `SELECT * FROM stock_check_items WHERE id = ? AND check_id = ?`,
      [req.params.itemId, req.params.id]
    );
    if (items.length === 0) {
      return res.status(404).json({ success: false, message: 'Item tidak ditemukan.' });
    }

    
    const [progress] = await staffPool.execute(
      `SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status != 'pending' THEN 1 ELSE 0 END) as checked
       FROM stock_check_items WHERE check_id = ?`,
      [req.params.id]
    );

    res.json({
      success: true,
      data: items[0],
      progress: {
        total: progress[0].total,
        checked: parseInt(progress[0].checked) || 0
      }
    });
  } catch (err) {
    console.error('GET /api/stock-check/:id/items/:itemId error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat item.' });
  }
});



router.put('/:id/items/:itemId', authorize('administrator'), async (req, res) => {
  const conn = await staffPool.getConnection();
  try {
    const { actual_qty, notes, skip, min_stock, adjustment_reason, adjustment_detail, purchase_store, purchase_price } = req.body;
    const { id: checkId, itemId } = req.params;

    const [item] = await conn.execute(
      'SELECT * FROM stock_check_items WHERE id = ? AND check_id = ?', [itemId, checkId]
    );
    if (item.length === 0) {
      return res.status(404).json({ success: false, message: 'Item tidak ditemukan.' });
    }

    await conn.beginTransaction();

    if (skip) {
      await conn.execute(
        `UPDATE stock_check_items SET status = 'skipped', notes = ?, checked_at = NOW(), checked_by = ? WHERE id = ?`,
        [notes?.trim() || null, req.user.id, itemId]
      );
    } else {
      const qty = parseFloat(actual_qty);
      if (isNaN(qty) || qty < 0) {
        await conn.rollback();
        return res.status(400).json({ success: false, message: 'Jumlah tidak valid.' });
      }
      const recorded = parseFloat(item[0].recorded_qty) || 0;
      const difference = qty - recorded;

      await conn.execute(
        `UPDATE stock_check_items SET actual_qty = ?, difference = ?, status = 'checked', notes = ?, min_stock = COALESCE(?, min_stock), adjustment_reason = ?, adjustment_detail = ?, purchase_store = ?, purchase_price = ?, checked_at = NOW(), checked_by = ? WHERE id = ?`,
        [qty, difference, notes?.trim() || null, min_stock !== undefined && min_stock !== '' ? parseFloat(min_stock) : null, adjustment_reason?.trim() || null, adjustment_detail?.trim() || null, purchase_store?.trim() || null, purchase_price !== undefined && purchase_price !== '' ? parseFloat(purchase_price) : null, req.user.id, itemId]
      );
    }

    
    const [stats] = await conn.execute(
      `SELECT 
        SUM(CASE WHEN status != 'pending' THEN 1 ELSE 0 END) as checked,
        SUM(CASE WHEN status = 'checked' AND difference != 0 THEN 1 ELSE 0 END) as discrepancies
       FROM stock_check_items WHERE check_id = ?`,
      [checkId]
    );

    await conn.execute(
      `UPDATE stock_checks SET checked_items = ?, discrepancy_count = ? WHERE id = ?`,
      [parseInt(stats[0].checked) || 0, parseInt(stats[0].discrepancies) || 0, checkId]
    );

    await conn.commit();

    
    const { sort } = req.query;
    let orderBy = 'sort_order ASC';
    if (sort === 'name') orderBy = 'material_name ASC';
    else if (sort === 'category') orderBy = 'category ASC, material_name ASC';

    const [nextItem] = await staffPool.execute(
      `SELECT * FROM stock_check_items WHERE check_id = ? AND status = 'pending' ORDER BY ${orderBy} LIMIT 1`,
      [checkId]
    );

    const [progress] = await staffPool.execute(
      `SELECT COUNT(*) as total, SUM(CASE WHEN status != 'pending' THEN 1 ELSE 0 END) as checked FROM stock_check_items WHERE check_id = ?`,
      [checkId]
    );

    res.json({
      success: true,
      message: skip ? 'Item dilewati.' : 'Berhasil dicek.',
      next: nextItem.length > 0 ? nextItem[0] : null,
      progress: { total: progress[0].total, checked: parseInt(progress[0].checked) || 0 }
    });
  } catch (err) {
    await conn.rollback();
    console.error('PUT /api/stock-check/:id/items/:itemId error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyimpan.' });
  } finally {
    conn.release();
  }
});



router.put('/:id/complete', authorize('administrator'), async (req, res) => {
  const conn = await staffPool.getConnection();
  try {
    const { apply_adjustments } = req.body;

    const [check] = await conn.execute('SELECT * FROM stock_checks WHERE id = ?', [req.params.id]);
    if (check.length === 0) {
      return res.status(404).json({ success: false, message: 'Sesi tidak ditemukan.' });
    }

    await conn.beginTransaction();

    
    if (apply_adjustments) {
      const [checkedItems] = await conn.execute(
        `SELECT * FROM stock_check_items WHERE check_id = ? AND status = 'checked' AND difference != 0 AND material_stock_id IS NOT NULL`,
        [req.params.id]
      );

      for (const item of checkedItems) {
        const [current] = await conn.execute(
          'SELECT stock_qty, avg_price FROM material_stock WHERE id = ? AND is_active = 1', [item.material_stock_id]
        );
        if (current.length === 0) continue;

        const stockBefore = parseFloat(current[0].stock_qty);
        const stockAfter = parseFloat(item.actual_qty);
        const oldAvgPrice = parseFloat(current[0].avg_price) || 0;

        
        let newAvgPrice = oldAvgPrice;
        const diff = stockAfter - stockBefore;
        if (item.adjustment_reason === 'pembelian' && item.purchase_price && diff > 0) {
          const purchasePrice = parseFloat(item.purchase_price);
          
          if (stockAfter > 0) {
            newAvgPrice = ((stockBefore * oldAvgPrice) + (diff * purchasePrice)) / stockAfter;
          }
        }

        
        const updateFields = ['stock_qty = ?'];
        const updateParams = [stockAfter];
        if (item.min_stock !== null && item.min_stock !== undefined) {
          updateFields.push('min_stock = ?');
          updateParams.push(parseFloat(item.min_stock));
        }
        if (newAvgPrice !== oldAvgPrice) {
          updateFields.push('avg_price = ?', 'last_price = ?');
          updateParams.push(newAvgPrice, parseFloat(item.purchase_price));
        }
        updateParams.push(item.material_stock_id);
        await conn.execute(`UPDATE material_stock SET ${updateFields.join(', ')} WHERE id = ?`, updateParams);

        
        let movementNotes = `Stock opname: ${check[0].check_name}`;
        if (item.adjustment_reason) movementNotes += ` (${item.adjustment_reason})`;
        if (item.purchase_store) movementNotes += ` - Toko: ${item.purchase_store}`;
        if (item.purchase_price) movementNotes += ` @ Rp${parseFloat(item.purchase_price).toLocaleString('id-ID')}`;
        if (item.adjustment_detail) movementNotes += ` - ${item.adjustment_detail}`;

        await conn.execute(
          `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, reference_id, notes, created_by)
           VALUES (?, 'adjustment', ?, ?, ?, ?, 'stock_check', ?, ?, ?)`,
          [
            item.material_stock_id,
            Math.abs(stockAfter - stockBefore),
            item.adjustment_reason === 'pembelian' && item.purchase_price ? parseFloat(item.purchase_price) : oldAvgPrice,
            stockBefore, stockAfter,
            req.params.id,
            movementNotes,
            req.user.id
          ]
        );
      }

      
      const [minStockItems] = await conn.execute(
        `SELECT * FROM stock_check_items WHERE check_id = ? AND status = 'checked' AND (difference = 0 OR difference IS NULL) AND min_stock IS NOT NULL AND material_stock_id IS NOT NULL`,
        [req.params.id]
      );
      for (const item of minStockItems) {
        await conn.execute('UPDATE material_stock SET min_stock = ? WHERE id = ?', [parseFloat(item.min_stock), item.material_stock_id]);
      }
    }

    await conn.execute(
      `UPDATE stock_checks SET status = 'completed', completed_at = NOW() WHERE id = ?`,
      [req.params.id]
    );

    await conn.commit();
    res.json({ success: true, message: apply_adjustments ? 'Pengecekan selesai & stok disesuaikan.' : 'Pengecekan selesai.' });
  } catch (err) {
    await conn.rollback();
    console.error('PUT /api/stock-check/:id/complete error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyelesaikan pengecekan.' });
  } finally {
    conn.release();
  }
});



router.delete('/:id', authorize('administrator'), async (req, res) => {
  try {
    const [result] = await staffPool.execute('DELETE FROM stock_checks WHERE id = ?', [req.params.id]);
    if (result.affectedRows === 0) {
      return res.status(404).json({ success: false, message: 'Sesi tidak ditemukan.' });
    }
    res.json({ success: true, message: 'Sesi pengecekan berhasil dihapus.' });
  } catch (err) {
    console.error('DELETE /api/stock-check/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal menghapus.' });
  }
});

module.exports = router;
