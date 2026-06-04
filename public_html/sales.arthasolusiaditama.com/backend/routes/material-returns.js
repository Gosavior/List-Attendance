const express = require('express');
const { salesPool, staffPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

const router = express.Router();

let broadcastSSE = null;
router.setBroadcast = (fn) => { broadcastSSE = fn; };

router.use(authenticate);



router.get('/', async (req, res) => {
  try {
    const userId = req.user.id;
    const userRole = req.user.role;
    const status = req.query.status || 'all';

    let projectFilter = '';
    let projectParams = [];

    if (userRole === 'sales') {
      const [projects] = await salesPool.execute(
        `SELECT id FROM projects WHERE assigned_to = ?`,
        [userId]
      );
      if (projects.length === 0) return res.json({ success: true, data: [] });
      const projectIds = projects.map(p => p.id);
      projectFilter = `AND mr.project_id IN (${projectIds.map(() => '?').join(',')})`;
      projectParams = projectIds;
    }

    let statusFilter = '';
    let statusParams = [];
    if (status !== 'all') {
      statusFilter = `AND mr.status = ?`;
      statusParams = [status];
    }

    const [returns] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name
       FROM material_returns mr
       JOIN users u ON mr.user_id = u.id
       WHERE 1=1 ${projectFilter} ${statusFilter}
       ORDER BY mr.created_at DESC`,
      [...projectParams, ...statusParams]
    );

    if (returns.length > 0) {
      const projectIds = [...new Set(returns.map(r => r.project_id))];
      const placeholders = projectIds.map(() => '?').join(',');
      const [projects] = await salesPool.execute(
        `SELECT id, project_name, customer_name FROM projects WHERE id IN (${placeholders})`,
        projectIds
      );
      const projectMap = {};
      projects.forEach(p => { projectMap[p.id] = p; });

      for (const ret of returns) {
        const [items] = await staffPool.execute(
          `SELECT * FROM material_return_items WHERE return_id = ?`,
          [ret.id]
        );
        ret.items = items;
        ret.project = projectMap[ret.project_id] || { project_name: 'Unknown', customer_name: '' };
      }
    }

    res.json({ success: true, data: returns });
  } catch (err) {
    console.error('GET /api/material-returns error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data pengembalian.' });
  }
});



router.get('/completed-items/:projectId', async (req, res) => {
  try {
    const projectId = req.params.projectId;

    
    const [items] = await staffPool.execute(
      `SELECT mri.id, mri.material_name, mri.quantity, mri.notes, mri.request_id,
              mr.status as request_status
       FROM material_request_items mri
       JOIN material_requests mr ON mri.request_id = mr.id
       WHERE mr.project_id = ? AND mr.status IN ('delivered', 'completed')`,
      [projectId]
    );

    
    const [returned] = await staffPool.execute(
      `SELECT mrti.original_item_id, SUM(mrti.quantity) as returned_qty
       FROM material_return_items mrti
       JOIN material_returns mrt ON mrti.return_id = mrt.id
       WHERE mrt.project_id = ? AND mrt.status != 'rejected'
       GROUP BY mrti.original_item_id`,
      [projectId]
    );

    const returnedMap = {};
    returned.forEach(r => { returnedMap[r.original_item_id] = r.returned_qty; });

    const available = items.map(item => ({
      ...item,
      returned_qty: returnedMap[item.id] || 0,
      returnable_qty: item.quantity - (returnedMap[item.id] || 0)
    })).filter(item => item.returnable_qty > 0);

    res.json({ success: true, data: available });
  } catch (err) {
    console.error('GET /api/material-returns/completed-items error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data item.' });
  }
});



router.post('/', async (req, res) => {
  try {
    const userId = req.user.id;
    const { project_id, items, note } = req.body;

    if (!project_id || !Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ success: false, message: 'Project dan minimal 1 item wajib diisi.' });
    }

    const validItems = items.filter(i => (i.material_name || '').trim() && parseInt(i.quantity) > 0);
    if (validItems.length === 0) {
      return res.status(400).json({ success: false, message: 'Minimal 1 item valid untuk dikembalikan.' });
    }

    const [result] = await staffPool.execute(
      `INSERT INTO material_returns (user_id, project_id, note, status) VALUES (?, ?, ?, 'pending')`,
      [userId, project_id, note || null]
    );
    const returnId = result.insertId;

    for (const item of validItems) {
      await staffPool.execute(
        `INSERT INTO material_return_items (return_id, material_name, quantity, notes, original_item_id)
         VALUES (?, ?, ?, ?, ?)`,
        [returnId, item.material_name.trim(), parseInt(item.quantity), item.notes || null, item.original_item_id || null]
      );
    }

    
    const [projects] = await salesPool.execute(
      `SELECT id, project_name, assigned_to FROM projects WHERE id = ?`,
      [project_id]
    );
    if (projects[0]) {
      const projectName = projects[0].project_name;
      const salesId = projects[0].assigned_to;
      const itemList = validItems.map(i => `${i.material_name} (x${i.quantity})`).join(', ');
      await salesPool.execute(
        `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', '/requestMaterial')`,
        [salesId, 'Permintaan Pengembalian Material', `Teknisi mengajukan pengembalian material untuk project "${projectName}": ${itemList}`]
      );
      if (broadcastSSE) {
        broadcastSSE({ type: 'material_return', message: `Pengembalian material baru untuk project "${projectName}"`, timestamp: new Date().toISOString() }, [salesId]);
      }
    }

    res.json({ success: true, message: 'Permintaan pengembalian material berhasil dikirim.' });
  } catch (err) {
    console.error('POST /api/material-returns error:', err);
    res.status(500).json({ success: false, message: 'Gagal membuat permintaan pengembalian.' });
  }
});



router.put('/:id/sales-approve', authorize('sales', 'administrator'), async (req, res) => {
  try {
    const userId = req.user.id;
    const returnId = req.params.id;

    const [returns] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name FROM material_returns mr
       JOIN users u ON mr.user_id = u.id WHERE mr.id = ?`,
      [returnId]
    );
    if (returns.length === 0) return res.status(404).json({ success: false, message: 'Data tidak ditemukan.' });

    const ret = returns[0];
    if (ret.status !== 'pending') {
      return res.status(400).json({ success: false, message: `Sudah berstatus "${ret.status}".` });
    }

    await staffPool.execute(
      `UPDATE material_returns SET status = 'sales_approved', sales_approved_by = ?, sales_approved_at = NOW() WHERE id = ?`,
      [userId, returnId]
    );

    
    const [admins] = await staffPool.execute(
      `SELECT id FROM users WHERE role = 'administrator' AND is_active = 1`
    );
    const [projects] = await salesPool.execute(
      `SELECT project_name FROM projects WHERE id = ?`, [ret.project_id]
    );
    const projectName = projects[0]?.project_name || 'Unknown';

    const [items] = await staffPool.execute(
      `SELECT material_name, quantity FROM material_return_items WHERE return_id = ?`, [returnId]
    );
    const itemList = items.map(i => `${i.material_name} (x${i.quantity})`).join(', ');

    for (const admin of admins) {
      await salesPool.execute(
        `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', '/requestMaterial')`,
        [admin.id, 'Pengembalian Material Disetujui Sales', `Pengembalian material untuk project "${projectName}" disetujui Sales. Item: ${itemList}. Silahkan terima barang.`]
      );
    }

    if (broadcastSSE) {
      broadcastSSE({ type: 'material_return', message: `Pengembalian material disetujui Sales`, timestamp: new Date().toISOString() }, admins.map(a => a.id));
    }

    
    try {
      const http = require('http');
      const payload = JSON.stringify({
        type: 'material_return', message: `Pengembalian material Anda untuk project "${projectName}" telah disetujui Sales. Menunggu admin menerima barang.`,
        userIds: [ret.user_id]
      });
      const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
      sReq.on('error', () => {});
      sReq.write(payload);
      sReq.end();
    } catch (e) {}

    res.json({ success: true, message: 'Pengembalian material disetujui.' });
  } catch (err) {
    console.error('PUT /api/material-returns/:id/sales-approve error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyetujui pengembalian.' });
  }
});



router.put('/:id/reject', authorize('sales', 'administrator'), async (req, res) => {
  try {
    const userId = req.user.id;
    const returnId = req.params.id;
    const { reason } = req.body;

    const [returns] = await staffPool.execute(
      `SELECT * FROM material_returns WHERE id = ?`, [returnId]
    );
    if (returns.length === 0) return res.status(404).json({ success: false, message: 'Data tidak ditemukan.' });

    const ret = returns[0];
    if (!['pending', 'sales_approved'].includes(ret.status)) {
      return res.status(400).json({ success: false, message: `Tidak bisa ditolak, sudah berstatus "${ret.status}".` });
    }

    await staffPool.execute(
      `UPDATE material_returns SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE id = ?`,
      [userId, reason || null, returnId]
    );

    
    try {
      const [projects] = await salesPool.execute(`SELECT project_name FROM projects WHERE id = ?`, [ret.project_id]);
      const http = require('http');
      const payload = JSON.stringify({
        type: 'material_return', message: `Pengembalian material Anda untuk project "${projects[0]?.project_name || ''}" ditolak.${reason ? ' Alasan: ' + reason : ''}`,
        userIds: [ret.user_id]
      });
      const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
      sReq.on('error', () => {});
      sReq.write(payload);
      sReq.end();
    } catch (e) {}

    res.json({ success: true, message: 'Pengembalian material ditolak.' });
  } catch (err) {
    console.error('PUT /api/material-returns/:id/reject error:', err);
    res.status(500).json({ success: false, message: 'Gagal menolak pengembalian.' });
  }
});



router.put('/:id/admin-receive', authorize('administrator'), async (req, res) => {
  const conn = await staffPool.getConnection();
  try {
    const userId = req.user.id;
    const returnId = req.params.id;

    const [returns] = await conn.execute(
      `SELECT * FROM material_returns WHERE id = ?`, [returnId]
    );
    if (returns.length === 0) return res.status(404).json({ success: false, message: 'Data tidak ditemukan.' });

    const ret = returns[0];
    if (ret.status !== 'sales_approved') {
      return res.status(400).json({ success: false, message: `Tidak bisa diterima, status saat ini "${ret.status}".` });
    }

    await conn.beginTransaction();

    await conn.execute(
      `UPDATE material_returns SET status = 'admin_received', admin_received_by = ?, admin_received_at = NOW() WHERE id = ?`,
      [userId, returnId]
    );

    
    const [returnItems] = await conn.execute(
      `SELECT * FROM material_return_items WHERE return_id = ?`, [returnId]
    );

    
    const [rabRows] = await salesPool.execute(
      `SELECT id FROM rab WHERE project_id = ? ORDER BY id DESC LIMIT 1`,
      [ret.project_id]
    );

    if (rabRows.length > 0) {
      const rabId = rabRows[0].id;

      for (const item of returnItems) {
        const materialName = item.material_name.trim().toLowerCase();
        const returnQty = parseInt(item.quantity);

        
        const [rabItems] = await salesPool.execute(
          `SELECT id, section, qty, qty_needed, qty_available, price FROM rab_items
           WHERE rab_id = ? AND LOWER(TRIM(item_name)) = ? AND section IN ('A', 'B')
           ORDER BY section ASC`,
          [rabId, materialName]
        );

        for (const rabItem of rabItems) {
          if (rabItem.section === 'A') {
            const newQty = Math.max(0, (rabItem.qty || 0) - returnQty);
            await salesPool.execute(
              `UPDATE rab_items SET qty = ? WHERE id = ?`,
              [newQty, rabItem.id]
            );
          } else if (rabItem.section === 'B') {
            const newNeeded = Math.max(0, (rabItem.qty_needed || 0) - returnQty);
            await salesPool.execute(
              `UPDATE rab_items SET qty_needed = ? WHERE id = ?`,
              [newNeeded, rabItem.id]
            );
          }
        }
      }

      
      const [allItems] = await salesPool.execute(
        `SELECT * FROM rab_items WHERE rab_id = ?`, [rabId]
      );

      const sectionBNames = new Set();
      allItems.filter(i => i.section === 'B').forEach(i => sectionBNames.add((i.item_name || '').trim().toLowerCase()));

      let totalA = 0, totalBWarehouse = 0, totalBBuy = 0, totalC = 0, totalD = 0;

      for (const item of allItems) {
        const price = parseFloat(item.price) || 0;
        if (item.section === 'A') {
          const name = (item.item_name || '').trim().toLowerCase();
          if (!sectionBNames.has(name)) {
            totalA += (parseFloat(item.qty) || 0) * price;
          }
        } else if (item.section === 'B') {
          const needed = parseFloat(item.qty_needed) || 0;
          const available = parseFloat(item.qty_available) || 0;
          totalBWarehouse += Math.min(needed, available) * price;
          totalBBuy += Math.max(0, needed - available) * price;
        } else if (item.section === 'C') {
          totalC += (parseFloat(item.qty) || 0) * price;
        } else if (item.section === 'D') {
          totalD += (parseFloat(item.qty) || 0) * price;
        }
      }

      const grandTotal = totalA + totalBBuy + totalC + totalD;

      await salesPool.execute(
        `UPDATE rab SET total_section_a = ?, total_section_b_warehouse = ?, total_section_b_buy = ?,
         total_section_c = ?, total_section_d = ?, grand_total = ?, updated_at = NOW() WHERE id = ?`,
        [totalA, totalBWarehouse, totalBBuy, totalC, totalD, grandTotal, rabId]
      );
    }

    await conn.commit();

    
    const [projects] = await salesPool.execute(
      `SELECT project_name, assigned_to FROM projects WHERE id = ?`, [ret.project_id]
    );
    const projectName = projects[0]?.project_name || 'Unknown';
    const salesId = projects[0]?.assigned_to;
    const itemList = returnItems.map(i => `${i.material_name} (x${i.quantity})`).join(', ');

    
    if (salesId) {
      await salesPool.execute(
        `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'success', '/requestMaterial')`,
        [salesId, 'Pengembalian Material Diterima', `Admin telah menerima pengembalian material untuk project "${projectName}": ${itemList}. RAB telah diperbarui.`]
      );
      if (broadcastSSE) {
        broadcastSSE({ type: 'material_return', message: `Pengembalian material diterima, RAB diperbarui`, timestamp: new Date().toISOString() }, [salesId]);
      }
    }

    
    try {
      const http = require('http');
      const payload = JSON.stringify({
        type: 'material_return', message: `Pengembalian material Anda untuk project "${projectName}" telah diterima oleh Admin.`,
        userIds: [ret.user_id]
      });
      const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
      sReq.on('error', () => {});
      sReq.write(payload);
      sReq.end();
    } catch (e) {}

    res.json({ success: true, message: 'Barang diterima dan RAB telah diperbarui.' });
  } catch (err) {
    await conn.rollback().catch(() => {});
    console.error('PUT /api/material-returns/:id/admin-receive error:', err);
    res.status(500).json({ success: false, message: 'Gagal menerima pengembalian.' });
  } finally {
    conn.release();
  }
});

module.exports = router;
