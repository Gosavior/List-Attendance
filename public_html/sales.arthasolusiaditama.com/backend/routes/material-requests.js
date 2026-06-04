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
    const limit = parseInt(req.query.limit) || 50;

    
    let projectFilter = '';
    let projectParams = [];

    if (userRole === 'sales') {
      const [projects] = await salesPool.execute(
        `SELECT id FROM projects WHERE assigned_to = ? AND status IN ('NEAREST','ONGOING')`,
        [userId]
      );
      if (projects.length === 0) {
        return res.json({ success: true, data: [], total: 0 });
      }
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

    
    const [requests] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name, u.role as requester_role
       FROM material_requests mr
       JOIN users u ON mr.user_id = u.id
       WHERE 1=1 ${projectFilter} ${statusFilter}
       ORDER BY mr.created_at DESC
       LIMIT ?`,
      [...projectParams, ...statusParams, limit]
    );

    
    if (requests.length > 0) {
      const projectIds = [...new Set(requests.map(r => r.project_id))];
      const placeholders = projectIds.map(() => '?').join(',');
      const [projects] = await salesPool.execute(
        `SELECT id, project_name, customer_name, status as project_status FROM projects WHERE id IN (${placeholders})`,
        projectIds
      );
      const projectMap = {};
      projects.forEach(p => { projectMap[p.id] = p; });

      
      for (const req_item of requests) {
        const [items] = await staffPool.execute(
          `SELECT * FROM material_request_items WHERE request_id = ?`,
          [req_item.id]
        );
        req_item.items = items;
        req_item.project = projectMap[req_item.project_id] || { project_name: 'Unknown', customer_name: 'Unknown' };
      }
    }

    
    const [counts] = await staffPool.execute(
      `SELECT status, COUNT(*) as count FROM material_requests mr WHERE 1=1 ${projectFilter} GROUP BY status`,
      [...projectParams]
    );
    const statusCounts = {};
    counts.forEach(c => { statusCounts[c.status] = c.count; });

    res.json({
      success: true,
      data: requests,
      total: requests.length,
      statusCounts
    });
  } catch (err) {
    console.error('GET /api/material-requests error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data material requests.' });
  }
});





router.get('/suppliers', authorize('administrator', 'sales', 'driver'), async (req, res) => {
  try {
    const [rows] = await staffPool.execute(
      'SELECT id, name, address, phone, notes FROM suppliers WHERE is_active = 1 ORDER BY name ASC'
    );
    res.json({ success: true, suppliers: rows });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});


router.post('/suppliers', authorize('administrator'), async (req, res) => {
  try {
    const { name, address, phone, notes } = req.body;
    if (!name || !name.trim()) {
      return res.status(400).json({ success: false, message: 'Nama supplier wajib diisi.' });
    }
    const [result] = await staffPool.execute(
      'INSERT INTO suppliers (name, address, phone, notes, created_by) VALUES (?, ?, ?, ?, ?)',
      [name.trim(), (address || '').trim() || null, (phone || '').trim() || null, (notes || '').trim() || null, req.user.id]
    );
    const [rows] = await staffPool.execute('SELECT id, name, address, phone, notes FROM suppliers WHERE id = ?', [result.insertId]);
    res.json({ success: true, supplier: rows[0] });
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(400).json({ success: false, message: 'Supplier dengan nama ini sudah ada.' });
    }
    res.status(500).json({ success: false, message: err.message });
  }
});



router.get('/my-projects', authorize('sales'), async (req, res) => {
  try {
    const [projects] = await salesPool.execute(
      `SELECT id, project_name, customer_name FROM projects WHERE assigned_to = ? AND status IN ('NEAREST','ONGOING') ORDER BY project_name`,
      [req.user.id]
    );
    res.json({ success: true, projects });
  } catch (err) {
    res.status(500).json({ success: false, message: 'Gagal mengambil daftar project.' });
  }
});



router.post('/', authorize('sales'), async (req, res) => {
  try {
    const userId = req.user.id;
    const { project_id, items, pickup_date } = req.body;

    if (!project_id) {
      return res.status(400).json({ success: false, message: 'Project wajib dipilih.' });
    }
    if (!items || !Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ success: false, message: 'Minimal 1 item material.' });
    }

    
    for (const item of items) {
      if (!item.material_name || !item.material_name.trim()) {
        return res.status(400).json({ success: false, message: 'Nama material tidak boleh kosong.' });
      }
    }

    
    const [projects] = await salesPool.execute(
      `SELECT id, project_name FROM projects WHERE id = ? AND assigned_to = ?`,
      [project_id, userId]
    );
    if (projects.length === 0) {
      return res.status(403).json({ success: false, message: 'Project tidak ditemukan atau bukan milik Anda.' });
    }

    
    const [staffUsers] = await staffPool.execute(
      `SELECT id, full_name FROM users WHERE id = ?`,
      [userId]
    );
    if (staffUsers.length === 0) {
      return res.status(400).json({ success: false, message: 'User tidak ditemukan di sistem staff.' });
    }

    const conn = await staffPool.getConnection();
    try {
      await conn.beginTransaction();

      
      const [result] = await conn.execute(
        `INSERT INTO material_requests (user_id, project_id, status, sales_approved_by, sales_approved_at, pickup_date)
         VALUES (?, ?, 'sales_approved', ?, NOW(), ?)`,
        [userId, project_id, userId, pickup_date || null]
      );
      const requestId = result.insertId;

      
      for (const item of items) {
        await conn.execute(
          `INSERT INTO material_request_items (request_id, material_name, quantity, notes) VALUES (?, ?, ?, ?)`,
          [requestId, item.material_name.trim(), parseInt(item.quantity) || 1, (item.notes || '').trim()]
        );
      }

      await conn.commit();

      
      const [admins] = await staffPool.execute(
        `SELECT id FROM users WHERE role = 'administrator' AND is_active = 1`
      );
      const materialList = items.map(i => `${i.material_name} (x${i.quantity || 1})`).join(', ');

      for (const admin of admins) {
        await salesPool.execute(
          `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', '/requestMaterial')`,
          [admin.id, 'Material Request Baru dari Sales', `Sales ${staffUsers[0].full_name} meminta material: ${materialList} untuk project "${projects[0].project_name}"${pickup_date ? '. Pengambilan: ' + pickup_date : ''}`]
        );
      }

      
      if (broadcastSSE) {
        broadcastSSE({ type: 'material_request', message: `Material request baru dari Sales`, timestamp: new Date().toISOString() }, admins.map(a => a.id));
      }

      
      try {
        const http = require('http');
        const payload = JSON.stringify({ type: 'material_request', message: `Material request baru dari Sales ${staffUsers[0].full_name}`, userIds: admins.map(a => a.id) });
        const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
        sReq.on('error', () => {});
        sReq.write(payload);
        sReq.end();
      } catch (e) {   }

      res.json({ success: true, message: 'Material request berhasil dibuat & langsung diteruskan ke Admin.', requestId });
    } catch (err) {
      await conn.rollback();
      throw err;
    } finally {
      conn.release();
    }
  } catch (err) {
    console.error('POST /api/material-requests error:', err);
    res.status(500).json({ success: false, message: 'Gagal membuat material request.' });
  }
});



router.get('/:id', async (req, res) => {
  try {
    const [requests] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name, u.role as requester_role
       FROM material_requests mr
       JOIN users u ON mr.user_id = u.id
       WHERE mr.id = ?`,
      [req.params.id]
    );

    if (requests.length === 0) {
      return res.status(404).json({ success: false, message: 'Request tidak ditemukan.' });
    }

    const request = requests[0];

    
    const [items] = await staffPool.execute(
      `SELECT * FROM material_request_items WHERE request_id = ?`,
      [request.id]
    );
    request.items = items;

    
    const [projects] = await salesPool.execute(
      `SELECT id, project_name, customer_name, status as project_status FROM projects WHERE id = ?`,
      [request.project_id]
    );
    request.project = projects[0] || { project_name: 'Unknown', customer_name: 'Unknown' };

    
    const [approvals] = await staffPool.execute(
      `SELECT * FROM material_request_approvals WHERE request_id = ?`,
      [request.id]
    );
    request.approval = approvals[0] || null;

    res.json({ success: true, data: request });
  } catch (err) {
    console.error('GET /api/material-requests/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil detail request.' });
  }
});



router.put('/:id/sales-approve', async (req, res) => {
  try {
    const userId = req.user.id;
    const requestId = req.params.id;

    
    const [requests] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name FROM material_requests mr 
       JOIN users u ON mr.user_id = u.id WHERE mr.id = ?`,
      [requestId]
    );

    if (requests.length === 0) {
      return res.status(404).json({ success: false, message: 'Request tidak ditemukan.' });
    }

    const request = requests[0];
    if (request.status !== 'pending') {
      return res.status(400).json({ success: false, message: `Request sudah berstatus: ${request.status}` });
    }

    
    if (req.user.role !== 'sales') {
      return res.status(403).json({ success: false, message: 'Hanya pengguna Sales yang dapat meng-approve request.' });
    }

    
    const [projects] = await salesPool.execute(
      `SELECT id, project_name, assigned_to FROM projects WHERE id = ?`,
      [request.project_id]
    );

    if (projects.length === 0) {
      return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });
    }

    

    
    await staffPool.execute(
      `UPDATE material_requests SET status = 'sales_approved', sales_approved_by = ?, sales_approved_at = NOW() WHERE id = ?`,
      [userId, requestId]
    );

    
    const [admins] = await staffPool.execute(
      `SELECT id, full_name as name FROM users WHERE role = 'administrator' AND is_active = 1`
    );

    
    const [items] = await staffPool.execute(
      `SELECT material_name, quantity FROM material_request_items WHERE request_id = ?`,
      [requestId]
    );
    const materialList = items.map(i => `${i.material_name} (x${i.quantity})`).join(', ');

    for (const admin of admins) {
      await salesPool.execute(
        `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', '/requestMaterial')`,
        [
          admin.id,
          `Material Request Approved by Sales`,
          `Request material dari ${request.requester_name} untuk project "${projects[0].project_name}" telah di-approve oleh Sales. Material: ${materialList}. Silahkan review dan sediakan barang di Sales.`,
        ]
      );
    }

    
    await salesPool.execute(
      `INSERT INTO activity_logs (user_id, action, target_type, target_id, description) VALUES (?, 'sales_approve_material', 'material_request', ?, ?)`,
      [userId, requestId, `Sales approved material request #${requestId} for project "${projects[0].project_name}"`]
    );

    
    if (broadcastSSE) {
      const adminIds = admins.map(a => a.id);
      broadcastSSE({ type: 'material_request', message: `Material request baru di-approve oleh Sales`, timestamp: new Date().toISOString() }, adminIds);
    }

    
    try {
      const http = require('http');
      const payload = JSON.stringify({
        type: 'material_request',
        message: `Request material Anda telah di-approve oleh Sales. Menunggu review Admin.`,
        userIds: [request.user_id]
      });
      const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
      sReq.on('error', () => {});
      sReq.write(payload);
      sReq.end();
    } catch (e) {   }

    res.json({ success: true, message: 'Material request berhasil di-approve.' });
  } catch (err) {
    console.error('PUT /api/material-requests/:id/sales-approve error:', err);
    res.status(500).json({ success: false, message: 'Gagal meng-approve request.' });
  }
});



router.put('/:id/sales-reject', async (req, res) => {
  try {
    const userId = req.user.id;
    const requestId = req.params.id;
    const { reason } = req.body;

    
    const [requests] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name FROM material_requests mr 
       JOIN users u ON mr.user_id = u.id WHERE mr.id = ?`,
      [requestId]
    );

    if (requests.length === 0) {
      return res.status(404).json({ success: false, message: 'Request tidak ditemukan.' });
    }

    const request = requests[0];
    if (request.status === 'pending' && req.user.role !== 'sales') {
      return res.status(403).json({ success: false, message: 'Hanya Sales yang dapat menolak request pending.' });
    }
    if (request.status === 'sales_approved' && req.user.role !== 'administrator') {
      return res.status(403).json({ success: false, message: 'Hanya Admin yang dapat menolak request yang sudah di-approve Sales.' });
    }
    if (!['pending', 'sales_approved'].includes(request.status)) {
      return res.status(400).json({ success: false, message: `Request sudah berstatus: ${request.status}` });
    }

    const rejectorLabel = req.user.role === 'administrator' ? 'Admin' : 'Sales';

    
    await staffPool.execute(
      `UPDATE material_requests SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE id = ?`,
      [userId, reason || `Ditolak oleh ${rejectorLabel}`, requestId]
    );

    
    try {
      const http = require('http');
      const rejectMsg = reason ? `Request material Anda ditolak oleh ${rejectorLabel}: ${reason}` : `Request material Anda ditolak oleh ${rejectorLabel}.`;
      const payload = JSON.stringify({
        type: 'material_request',
        message: rejectMsg,
        userIds: [request.user_id]
      });
      const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
      sReq.on('error', () => {});
      sReq.write(payload);
      sReq.end();
    } catch (e) {   }

    res.json({ success: true, message: 'Material request berhasil ditolak.' });
  } catch (err) {
    console.error('PUT /api/material-requests/:id/sales-reject error:', err);
    res.status(500).json({ success: false, message: 'Gagal menolak request.' });
  }
});



router.get('/stats/summary', async (req, res) => {
  try {
    const userId = req.user.id;
    const userRole = req.user.role;

    let projectFilter = '';
    let projectParams = [];

    if (userRole === 'sales') {
      const [projects] = await salesPool.execute(
        `SELECT id FROM projects WHERE assigned_to = ?`,
        [userId]
      );
      if (projects.length === 0) {
        return res.json({ success: true, data: { pending: 0, sales_approved: 0, admin_approved: 0, delivered: 0, rejected: 0, total: 0 } });
      }
      const projectIds = projects.map(p => p.id);
      projectFilter = `AND project_id IN (${projectIds.map(() => '?').join(',')})`;
      projectParams = projectIds;
    }

    const [counts] = await staffPool.execute(
      `SELECT status, COUNT(*) as count FROM material_requests WHERE 1=1 ${projectFilter} GROUP BY status`,
      [...projectParams]
    );

    const stats = { pending: 0, sales_approved: 0, admin_review: 0, admin_approved: 0, driver_pickup: 0, delivered: 0, rejected: 0, total: 0 };
    counts.forEach(c => {
      stats[c.status] = c.count;
      stats.total += c.count;
    });

    res.json({ success: true, data: stats });
  } catch (err) {
    console.error('GET /api/material-requests/stats/summary error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil statistik.' });
  }
});



router.put('/:id/admin-provide', async (req, res) => {
  try {
    const userId = req.user.id;
    const requestId = req.params.id;
    const { items } = req.body;

    if (req.user.role !== 'administrator' && req.user.role !== 'admin') {
      return res.status(403).json({ success: false, message: 'Hanya Administrator yang dapat menyediakan material.' });
    }

    
    const [requests] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name FROM material_requests mr 
       JOIN users u ON mr.user_id = u.id WHERE mr.id = ?`,
      [requestId]
    );

    if (requests.length === 0) {
      return res.status(404).json({ success: false, message: 'Request tidak ditemukan.' });
    }

    const request = requests[0];
    if (!['sales_approved', 'admin_review'].includes(request.status)) {
      return res.status(400).json({ success: false, message: `Request tidak dalam status yang benar: ${request.status}` });
    }

    
    if (Array.isArray(items)) {
      for (const item of items) {
        const price = parseFloat(item.price) || 0;
        const sourceType = item.source_type || 'warehouse';
        const stockId = parseInt(item.stock_id) || null;
        const isSplit = sourceType === 'split' && item.needs_purchase;

        const warehouseQty = isSplit ? (parseFloat(item.warehouse_qty) || 0) : 0;
        const purchaseQty = isSplit ? (parseFloat(item.purchase_qty) || 0) : 0;
        const purchasePrice = isSplit ? (parseFloat(item.purchase_price) || 0) : 0;
        const purchaseStore = isSplit ? (item.purchase_store || '').toString().trim() : null;
        const purchaseStoreAddress = isSplit ? (item.purchase_store_address || '').toString().trim() : null;

        
        const finalStoreName = purchaseStore || (sourceType === 'purchase' ? (item.purchase_store || '').toString().trim() : null);
        const finalStoreAddress = purchaseStoreAddress || (sourceType === 'purchase' ? (item.purchase_store_address || '').toString().trim() : null);

        
        let dbSourceType;
        if (isSplit) {
          dbSourceType = 'split';
        } else if (sourceType === 'purchase') {
          dbSourceType = 'purchase';
        } else {
          dbSourceType = 'warehouse';
        }

        await staffPool.execute(
          `UPDATE material_request_items 
           SET price = ?, source_type = ?, stock_id = ?, qty_from_warehouse = ?, qty_to_purchase = ?, store_name = ?, store_address = ?
           WHERE id = ? AND request_id = ?`,
          [
            isSplit ? parseFloat(item.stock_info?.avg_price || 0) : price,
            dbSourceType, stockId,
            isSplit ? warehouseQty : (dbSourceType === 'warehouse' ? parseFloat(item.quantity) : 0),
            isSplit ? purchaseQty : (dbSourceType === 'purchase' ? parseFloat(item.quantity) : 0),
            finalStoreName, finalStoreAddress,
            item.item_id, requestId
          ]
        );

        
        if (stockId) {
          try {
            const [stockRows] = await staffPool.execute(
              'SELECT * FROM material_stock WHERE id = ? AND is_active = 1',
              [stockId]
            );
            if (stockRows.length === 0) continue;

            const stock = stockRows[0];
            const currentQty = parseFloat(stock.stock_qty);

            if (isSplit) {
              
              if (warehouseQty > 0) {
                const deductQty = Math.min(warehouseQty, currentQty);
                const newQty = currentQty - deductQty;
                await staffPool.execute('UPDATE material_stock SET stock_qty = ? WHERE id = ?', [newQty, stockId]);
                await staffPool.execute(
                  `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                   VALUES (?, 'out', ?, ?, ?, ?, 'material_request', ?, ?, ?)`,
                  [stockId, deductQty, parseFloat(stock.avg_price), currentQty, newQty, requestId, `Request #${requestId} - ${item.material_name} (gudang)`, userId]
                );
              }
              
              if (purchaseQty > 0 && purchasePrice > 0) {
                const [freshStock] = await staffPool.execute('SELECT * FROM material_stock WHERE id = ?', [stockId]);
                const curQty = parseFloat(freshStock[0].stock_qty);
                const afterPurchase = curQty + purchaseQty;
                const newTotalPurchased = parseFloat(freshStock[0].total_purchased) + purchaseQty;
                const newTotalSpent = parseFloat(freshStock[0].total_spent) + (purchaseQty * purchasePrice);
                const newAvgPrice = newTotalPurchased > 0 ? newTotalSpent / newTotalPurchased : purchasePrice;

                
                await staffPool.execute(
                  `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                   VALUES (?, 'in', ?, ?, ?, ?, 'purchase', ?, ?, ?)`,
                  [stockId, purchaseQty, purchasePrice, curQty, afterPurchase, requestId, `Pembelian ${purchaseStore || ''} untuk request #${requestId}`, userId]
                );

                
                const neededFromPurchase = Math.max(0, parseFloat(item.quantity) - warehouseQty);
                const outQty = Math.min(neededFromPurchase, purchaseQty);
                const afterOut = afterPurchase - outQty;
                await staffPool.execute(
                  `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                   VALUES (?, 'out', ?, ?, ?, ?, 'material_request', ?, ?, ?)`,
                  [stockId, outQty, newAvgPrice, afterPurchase, afterOut, requestId, `Request #${requestId} - ${item.material_name} (beli)`, userId]
                );

                await staffPool.execute(
                  'UPDATE material_stock SET stock_qty = ?, avg_price = ?, last_price = ?, total_purchased = ?, total_spent = ? WHERE id = ?',
                  [afterOut, newAvgPrice, purchasePrice, newTotalPurchased, newTotalSpent, stockId]
                );
              }
            } else if (dbSourceType === 'warehouse') {
              const qty = parseFloat(item.quantity) || 0;
              if (qty <= 0) continue;
              const deductQty = Math.min(qty, currentQty);
              const newQty = currentQty - deductQty;
              await staffPool.execute('UPDATE material_stock SET stock_qty = ? WHERE id = ?', [newQty, stockId]);
              await staffPool.execute(
                `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                 VALUES (?, 'out', ?, ?, ?, ?, 'material_request', ?, ?, ?)`,
                [stockId, deductQty, parseFloat(stock.avg_price), currentQty, newQty, requestId, `Request #${requestId} - ${item.material_name}`, userId]
              );
            } else if (dbSourceType === 'purchase') {
              const neededQty = parseFloat(item.quantity) || 0;
              
              const actualPurchaseQty = parseFloat(item.purchase_qty) || neededQty;
              const buyQty = Math.max(neededQty, actualPurchaseQty);
              if (buyQty <= 0) continue;
              const purchaseP = parseFloat(item.purchase_price) || price;
              const afterPurchase = currentQty + buyQty;
              const newTotalPurchased = parseFloat(stock.total_purchased) + buyQty;
              const newTotalSpent = parseFloat(stock.total_spent) + (buyQty * purchaseP);
              const newAvgPrice = newTotalPurchased > 0 ? newTotalSpent / newTotalPurchased : purchaseP;

              
              await staffPool.execute(
                `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                 VALUES (?, 'in', ?, ?, ?, ?, 'purchase', ?, ?, ?)`,
                [stockId, buyQty, purchaseP, currentQty, afterPurchase, requestId, `Pembelian untuk request #${requestId}`, userId]
              );

              
              const outQty = Math.min(neededQty, buyQty);
              const finalQty = afterPurchase - outQty;
              await staffPool.execute(
                `INSERT INTO stock_movements (material_id, movement_type, quantity, price_per_unit, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                 VALUES (?, 'out', ?, ?, ?, ?, 'material_request', ?, ?, ?)`,
                [stockId, outQty, newAvgPrice, afterPurchase, finalQty, requestId, `Request #${requestId} - ${item.material_name}`, userId]
              );

              await staffPool.execute(
                'UPDATE material_stock SET stock_qty = ?, avg_price = ?, last_price = ?, total_purchased = ?, total_spent = ? WHERE id = ?',
                [finalQty, newAvgPrice, purchaseP, newTotalPurchased, newTotalSpent, stockId]
              );
            }
          } catch (stockErr) {
            console.warn('Stock operation failed for item:', item.item_id, stockErr.message);
          }
        }
      }
    }

    
    await staffPool.execute(
      `UPDATE material_requests SET status = 'admin_approved', admin_ready_by = ?, admin_ready_at = NOW() WHERE id = ?`,
      [userId, requestId]
    );

    
    const [projects] = await salesPool.execute(
      `SELECT id, project_name, assigned_to FROM projects WHERE id = ?`,
      [request.project_id]
    );

    
    if (projects.length > 0 && projects[0].assigned_to) {
      await salesPool.execute(
        `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'success', '/requestMaterial')`,
        [
          projects[0].assigned_to,
          'Material Disediakan oleh Admin',
          `Material request #${requestId} untuk project "${projects[0].project_name}" telah disediakan oleh Admin. Menunggu driver pickup.`,
        ]
      );

      if (broadcastSSE) {
        broadcastSSE({ type: 'material_request', message: 'Admin telah menyediakan material', timestamp: new Date().toISOString() }, [projects[0].assigned_to]);
      }
    }

    
    try {
      const http = require('http');
      const payload = JSON.stringify({
        type: 'material_request',
        message: 'Material Anda telah disediakan oleh Admin. Menunggu Driver.',
        userIds: [request.user_id]
      });
      const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
      sReq.on('error', () => {});
      sReq.write(payload);
      sReq.end();
    } catch (e) {   }

    
    try {
      const [drivers] = await staffPool.execute("SELECT id FROM users WHERE role = 'driver' AND is_active = 1");
      const driverIds = drivers.map(d => d.id);
      
      if (driverIds.length > 0) {
        const projectName = projects.length > 0 ? projects[0].project_name : 'Unknown';
        
        const hasPurchase = items.some(i => i.source_type === 'purchase' || i.needs_purchase);
        const notifMsg = hasPurchase
          ? `Material request #${requestId} untuk "${projectName}" perlu diambil dari toko dan diantar.`
          : `Material request #${requestId} untuk "${projectName}" siap diantar dari gudang.`;

        for (const dId of driverIds) {
          await salesPool.execute(
            `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', '/delivery')`,
            [dId, 'Pengantaran Baru', notifMsg]
          );
        }
        if (broadcastSSE) {
          broadcastSSE({ type: 'delivery_schedule', message: 'Ada pengantaran baru', timestamp: new Date().toISOString() }, driverIds);
        }
      }
    } catch (e) { console.warn('Driver notification failed:', e.message); }

    res.json({ success: true, message: 'Material berhasil disediakan.', data: { id: requestId } });
  } catch (err) {
    console.error('PUT /api/material-requests/:id/admin-provide error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyediakan material.' });
  }
});



router.get('/driver/schedule', authorize('administrator', 'driver'), async (req, res) => {
  try {
    const [requests] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name
       FROM material_requests mr
       JOIN users u ON mr.user_id = u.id
       WHERE mr.status IN ('admin_approved', 'driver_pickup')
       ORDER BY mr.admin_ready_at DESC`
    );

    
    for (const req_ of requests) {
      const [items] = await staffPool.execute(
        `SELECT mri.*, ms.material_name as stock_name 
         FROM material_request_items mri 
         LEFT JOIN material_stock ms ON mri.stock_id = ms.id
         WHERE mri.request_id = ?`,
        [req_.id]
      );
      req_.items = items;

      const [projects] = await salesPool.execute(
        `SELECT id, project_name FROM projects WHERE id = ?`,
        [req_.project_id]
      );
      req_.project = projects[0] || null;
    }

    
    const pickupFromStore = []; 
    const deliverToSite = [];   

    for (const req_ of requests) {
      const hasPurchase = (req_.items || []).some(i => 
        i.source_type === 'purchase' || i.qty_to_purchase > 0
      );
      
      if (req_.status === 'admin_approved') {
        if (hasPurchase) {
          req_._type = 'pickup_purchase';
          pickupFromStore.push(req_);
        }
        req_._type = req_._type || 'ready_deliver';
        deliverToSite.push(req_);
      } else if (req_.status === 'driver_pickup') {
        req_._type = 'in_transit';
        deliverToSite.push(req_);
      }
    }

    res.json({ 
      success: true, 
      data: requests,
      summary: {
        ready: requests.filter(r => r.status === 'admin_approved').length,
        in_transit: requests.filter(r => r.status === 'driver_pickup').length,
        total: requests.length
      }
    });
  } catch (err) {
    console.error('GET /api/material-requests/driver/schedule error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil jadwal.' });
  }
});



router.put('/:id/driver-pickup', authorize('administrator', 'driver'), async (req, res) => {
  try {
    const userId = req.user.id;
    const requestId = req.params.id;

    const [requests] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name FROM material_requests mr 
       JOIN users u ON mr.user_id = u.id WHERE mr.id = ?`,
      [requestId]
    );
    if (requests.length === 0) {
      return res.status(404).json({ success: false, message: 'Request tidak ditemukan.' });
    }

    const request = requests[0];
    if (request.status !== 'admin_approved') {
      return res.status(400).json({ success: false, message: 'Request belum siap diambil.' });
    }

    await staffPool.execute(
      `UPDATE material_requests SET status = 'driver_pickup', driver_pickup_by = ?, driver_pickup_at = NOW() WHERE id = ?`,
      [userId, requestId]
    );

    
    const [projects] = await salesPool.execute(
      `SELECT id, project_name, assigned_to FROM projects WHERE id = ?`, [request.project_id]
    );
    if (projects.length > 0 && projects[0].assigned_to) {
      await salesPool.execute(
        `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', '/requestMaterial')`,
        [projects[0].assigned_to, 'Driver Pickup Material', `Material request #${requestId} untuk "${projects[0].project_name}" sedang dalam perjalanan.`]
      );
      if (broadcastSSE) {
        broadcastSSE({ type: 'material_request', message: 'Driver sedang mengantar material', timestamp: new Date().toISOString() }, [projects[0].assigned_to]);
      }
    }

    
    try {
      const http = require('http');
      const payload = JSON.stringify({ type: 'material_request', message: 'Driver sedang mengantar material Anda.', userIds: [request.user_id] });
      const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
      sReq.on('error', () => {});
      sReq.write(payload);
      sReq.end();
    } catch (e) {}

    res.json({ success: true, message: 'Material sedang diantar.', data: { id: requestId } });
  } catch (err) {
    console.error('PUT /api/material-requests/:id/driver-pickup error:', err);
    res.status(500).json({ success: false, message: 'Gagal update status.' });
  }
});



router.put('/:id/driver-delivered', authorize('administrator', 'driver'), async (req, res) => {
  try {
    const userId = req.user.id;
    const requestId = req.params.id;

    const [requests] = await staffPool.execute(
      `SELECT mr.*, u.full_name as requester_name FROM material_requests mr 
       JOIN users u ON mr.user_id = u.id WHERE mr.id = ?`,
      [requestId]
    );
    if (requests.length === 0) {
      return res.status(404).json({ success: false, message: 'Request tidak ditemukan.' });
    }

    const request = requests[0];
    if (request.status !== 'driver_pickup') {
      return res.status(400).json({ success: false, message: 'Request belum dalam status pickup.' });
    }

    await staffPool.execute(
      `UPDATE material_requests SET status = 'delivered', driver_delivered_by = ?, driver_delivered_at = NOW() WHERE id = ?`,
      [userId, requestId]
    );

    
    const [projects] = await salesPool.execute(
      `SELECT id, project_name, assigned_to FROM projects WHERE id = ?`, [request.project_id]
    );
    if (projects.length > 0 && projects[0].assigned_to) {
      await salesPool.execute(
        `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'success', '/requestMaterial')`,
        [projects[0].assigned_to, 'Material Terkirim', `Material request #${requestId} untuk "${projects[0].project_name}" telah diterima.`]
      );
      if (broadcastSSE) {
        broadcastSSE({ type: 'material_request', message: 'Material telah diterima', timestamp: new Date().toISOString() }, [projects[0].assigned_to]);
      }
    }

    try {
      const http = require('http');
      const payload = JSON.stringify({ type: 'material_request', message: 'Material Anda telah diantar!', userIds: [request.user_id] });
      const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
      sReq.on('error', () => {});
      sReq.write(payload);
      sReq.end();
    } catch (e) {}

    res.json({ success: true, message: 'Material berhasil diantar.', data: { id: requestId } });
  } catch (err) {
    console.error('PUT /api/material-requests/:id/driver-delivered error:', err);
    res.status(500).json({ success: false, message: 'Gagal update status.' });
  }
});



router.put('/:id/sales-edit', authorize('sales', 'administrator'), async (req, res) => {
  const conn = await staffPool.getConnection();
  try {
    const userId = req.user.id;
    const requestId = req.params.id;
    const { items, note } = req.body;

    if (!Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ success: false, message: 'Minimal harus ada 1 item material.' });
    }

    
    const [requests] = await conn.execute(
      `SELECT mr.*, u.full_name as requester_name FROM material_requests mr
       JOIN users u ON mr.user_id = u.id WHERE mr.id = ?`,
      [requestId]
    );

    if (requests.length === 0) {
      return res.status(404).json({ success: false, message: 'Request tidak ditemukan.' });
    }

    const request = requests[0];
    if (request.status !== 'pending') {
      return res.status(400).json({ success: false, message: `Request sudah berstatus "${request.status}", tidak bisa diedit.` });
    }

    
    const [projects] = await salesPool.execute(
      `SELECT id, project_name, assigned_to FROM projects WHERE id = ?`,
      [request.project_id]
    );
    const projectName = projects[0]?.project_name || 'Unknown';

    
    const [oldItems] = await conn.execute(
      `SELECT id, material_name, quantity, notes FROM material_request_items WHERE request_id = ?`,
      [requestId]
    );

    await conn.beginTransaction();

    const changes = [];
    const existingIds = new Set();

    for (const item of items) {
      const name = (item.material_name || '').trim();
      const qty = parseInt(item.quantity) || 1;
      const notes = (item.notes || '').trim();

      if (!name) continue;

      if (item.id) {
        
        existingIds.add(item.id);
        const old = oldItems.find(o => o.id === item.id);
        if (old) {
          const modified = old.material_name !== name || old.quantity !== qty || (old.notes || '') !== notes;
          if (modified) {
            changes.push(`Diubah: "${old.material_name} x${old.quantity}" → "${name} x${qty}"`);
          }
        }
        await conn.execute(
          `UPDATE material_request_items SET material_name = ?, quantity = ?, notes = ? WHERE id = ? AND request_id = ?`,
          [name, qty, notes || null, item.id, requestId]
        );
      } else {
        
        changes.push(`Ditambahkan: "${name} x${qty}"`);
        await conn.execute(
          `INSERT INTO material_request_items (request_id, material_name, quantity, notes) VALUES (?, ?, ?, ?)`,
          [requestId, name, qty, notes || null]
        );
      }
    }

    
    const submittedIds = items.filter(i => i.id).map(i => i.id);
    for (const old of oldItems) {
      if (!submittedIds.includes(old.id)) {
        changes.push(`Dihapus: "${old.material_name} x${old.quantity}"`);
        await conn.execute(
          `DELETE FROM material_request_items WHERE id = ? AND request_id = ?`,
          [old.id, requestId]
        );
      }
    }

    
    await conn.execute(
      `UPDATE material_requests SET updated_at = NOW() WHERE id = ?`,
      [requestId]
    );

    await conn.commit();

    
    const changeSummary = changes.length > 0 ? changes.join('; ') : 'Ada perubahan pada material request';
    const notifTitle = 'Material Request Diubah oleh Sales';
    const notifMessage = `Request material Anda untuk project "${projectName}" telah diedit oleh Sales. ${changeSummary}${note ? '. Catatan: ' + note : ''}`;

    
    try {
      await conn.execute(
        `UPDATE material_requests SET sales_edited_at = NOW(), sales_edit_note = ?, sales_edit_read = 0 WHERE id = ?`,
        [changeSummary + (note ? '. Catatan: ' + note : ''), requestId]
      );
    } catch (editErr) {
      console.error('Sales edit tracking failed (non-critical):', editErr.message);
    }

    
    try {
      const http = require('http');
      const payload = JSON.stringify({
        type: 'material_request',
        message: notifMessage,
        userIds: [request.user_id]
      });
      const sReq = http.request({ hostname: 'localhost', port: 3001, path: '/notify', method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) }, timeout: 3000 });
      sReq.on('error', () => {});
      sReq.write(payload);
      sReq.end();
    } catch (e) {   }

    
    try {
      const [admins] = await staffPool.execute(
        `SELECT id FROM users WHERE role = 'administrator' AND is_active = 1`
      );
      const salesName = req.user.full_name || req.user.username || 'Sales';
      const adminNotifTitle = 'Material Request Diedit oleh Sales';
      const adminNotifMsg = `${salesName} mengedit request material untuk project "${projectName}". ${changeSummary}${note ? '. Catatan: ' + note : ''}`;
      for (const admin of admins) {
        await salesPool.execute(
          `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', '/requestMaterial')`,
          [admin.id, adminNotifTitle, adminNotifMsg]
        );
      }
      
      if (broadcastSSE) {
        broadcastSSE({ type: 'material_request', message: adminNotifMsg, timestamp: new Date().toISOString() }, admins.map(a => a.id));
      }
    } catch (adminNotifErr) {
      console.error('Admin notification for sales-edit failed (non-critical):', adminNotifErr.message);
    }

    
    await salesPool.execute(
      `INSERT INTO activity_logs (user_id, action, target_type, target_id, description) VALUES (?, 'sales_edit_material', 'material_request', ?, ?)`,
      [userId, requestId, `Sales edited material request #${requestId}: ${changeSummary}`]
    );

    res.json({ success: true, message: 'Material request berhasil diedit. Teknisi sudah dinotifikasi.' });
  } catch (err) {
    await conn.rollback().catch(() => {});
    console.error('PUT /api/material-requests/:id/sales-edit error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengedit request.' });
  } finally {
    conn.release();
  }
});

module.exports = router;
