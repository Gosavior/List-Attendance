const express = require('express');
const jwt = require('jsonwebtoken');
const { salesPool, staffPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

const router = express.Router();



const sseClients = new Map();


function broadcastSSE(data, userIds) {
  const payload = `event: notification\ndata: ${JSON.stringify(data)}\n\n`;
  if (Array.isArray(userIds) && userIds.length > 0) {
    userIds.forEach(uid => {
      const clients = sseClients.get(parseInt(uid));
      if (clients) clients.forEach(res => res.write(payload));
    });
  } else {
    
    sseClients.forEach(clients => clients.forEach(res => res.write(payload)));
  }
}


router.broadcastSSE = broadcastSSE;



router.get('/stream', (req, res) => {
  const token = req.query.token || (req.headers.authorization || '').replace('Bearer ', '');
  if (!token) return res.status(401).end();

  let decoded;
  try {
    decoded = jwt.verify(token, process.env.JWT_SECRET);
  } catch (e) {
    return res.status(401).end();
  }

  const userId = decoded.id;

  res.writeHead(200, {
    'Content-Type': 'text/event-stream',
    'Cache-Control': 'no-cache',
    'Connection': 'keep-alive',
    'X-Accel-Buffering': 'no',
  });
  res.write('event: ping\ndata: connected\n\n');

  if (!sseClients.has(userId)) sseClients.set(userId, new Set());
  sseClients.get(userId).add(res);

  
  const keepAlive = setInterval(() => {
    res.write('event: ping\ndata: keepalive\n\n');
  }, 30000);

  req.on('close', () => {
    clearInterval(keepAlive);
    const clients = sseClients.get(userId);
    if (clients) {
      clients.delete(res);
      if (clients.size === 0) sseClients.delete(userId);
    }
  });
});



router.post('/push', (req, res) => {
  const { type, message, userIds } = req.body;
  broadcastSSE({ type: type || 'general', message: message || '', timestamp: new Date().toISOString() }, userIds);
  res.json({ success: true });
});


router.use(authenticate);



router.get('/', async (req, res) => {
  try {
    const userId = req.user.id;
    const limit = parseInt(req.query.limit) || 50;
    const unreadOnly = req.query.unread === 'true';

    let sql = `SELECT * FROM notifications WHERE user_id = ?`;
    const params = [userId];

    if (unreadOnly) {
      sql += ` AND is_read = 0`;
    }

    sql += ` ORDER BY created_at DESC LIMIT ?`;
    params.push(limit);

    const [rows] = await salesPool.execute(sql, params);

    
    const [countRows] = await salesPool.execute(
      `SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0`,
      [userId]
    );

    res.json({
      success: true,
      data: rows,
      unreadCount: countRows[0].count,
    });
  } catch (err) {
    console.error('GET /api/notifications error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil notifikasi.' });
  }
});



router.get('/unread-count', async (req, res) => {
  try {
    const [rows] = await salesPool.execute(
      `SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0`,
      [req.user.id]
    );
    res.json({ success: true, data: { count: rows[0].count } });
  } catch (err) {
    console.error('GET /api/notifications/unread-count error:', err);
    res.status(500).json({ success: false, message: 'Gagal.' });
  }
});



router.put('/:id/read', async (req, res) => {
  try {
    const [result] = await salesPool.execute(
      `UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?`,
      [req.params.id, req.user.id]
    );
    if (result.affectedRows === 0) {
      return res.status(404).json({ success: false, message: 'Notifikasi tidak ditemukan.' });
    }
    res.json({ success: true, message: 'Ditandai sudah dibaca.' });
  } catch (err) {
    console.error('PUT /api/notifications/:id/read error:', err);
    res.status(500).json({ success: false, message: 'Gagal update.' });
  }
});



router.put('/read-all', async (req, res) => {
  try {
    await salesPool.execute(
      `UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0`,
      [req.user.id]
    );
    res.json({ success: true, message: 'Semua ditandai sudah dibaca.' });
  } catch (err) {
    console.error('PUT /api/notifications/read-all error:', err);
    res.status(500).json({ success: false, message: 'Gagal update.' });
  }
});



router.delete('/:id', async (req, res) => {
  try {
    const [result] = await salesPool.execute(
      `DELETE FROM notifications WHERE id = ? AND user_id = ?`,
      [req.params.id, req.user.id]
    );
    if (result.affectedRows === 0) {
      return res.status(404).json({ success: false, message: 'Notifikasi tidak ditemukan.' });
    }
    res.json({ success: true, message: 'Notifikasi dihapus.' });
  } catch (err) {
    console.error('DELETE /api/notifications/:id error:', err);
    res.status(500).json({ success: false, message: 'Gagal hapus.' });
  }
});



router.get('/stale-projects', authorize('administrator'), async (req, res) => {
  try {
    const days = parseInt(req.query.days) || 30;

    const [projects] = await salesPool.execute(
      `SELECT p.id, p.project_name, p.customer_name, p.status, p.assigned_to,
              p.updated_at, p.created_at,
              DATEDIFF(NOW(), p.updated_at) as days_stale
       FROM projects p
       WHERE p.status NOT IN ('DONE', 'LOST')
         AND DATEDIFF(NOW(), p.updated_at) >= ?
       ORDER BY days_stale DESC`,
      [days]
    );

    
    if (projects.length > 0) {
      const salesIds = [...new Set(projects.map(p => p.assigned_to))];
      const placeholders = salesIds.map(() => '?').join(',');
      const [salesUsers] = await staffPool.execute(
        `SELECT id, full_name FROM users WHERE id IN (${placeholders})`,
        salesIds
      );
      const salesMap = {};
      salesUsers.forEach(s => { salesMap[s.id] = s.full_name; });
      projects.forEach(p => { p.sales_name = salesMap[p.assigned_to] || 'Unknown'; });
    }

    res.json({ success: true, data: projects, total: projects.length });
  } catch (err) {
    console.error('GET /api/notifications/stale-projects error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengambil data.' });
  }
});



router.post('/send-alert', authorize('administrator'), async (req, res) => {
  try {
    const { project_id, user_id, message } = req.body;
    if (!project_id || !user_id) {
      return res.status(400).json({ success: false, message: 'project_id dan user_id wajib diisi.' });
    }

    
    const [projects] = await salesPool.execute(
      `SELECT id, project_name, status, assigned_to FROM projects WHERE id = ?`,
      [project_id]
    );
    if (projects.length === 0) {
      return res.status(404).json({ success: false, message: 'Project tidak ditemukan.' });
    }

    const project = projects[0];
    const alertMessage = message || `Project "${project.project_name}" sudah lama tidak ada update. Segera follow-up!`;

    
    await salesPool.execute(
      `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'warning', ?)`,
      [
        user_id,
        `Alert: ${project.project_name}`,
        alertMessage,
        `/sales-tracker`,
      ]
    );

    
    const [admins] = await staffPool.execute(
      `SELECT id FROM users WHERE role = 'administrator' AND is_active = 1`
    );

    for (const admin of admins) {
      await salesPool.execute(
        `INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', ?)`,
        [
          admin.id,
          `Alert terkirim: ${project.project_name}`,
          `Alert telah dikirim ke sales untuk project "${project.project_name}".`,
          `/project`,
        ]
      );
    }

    res.json({ success: true, message: 'Alert berhasil dikirim ke sales.' });
  } catch (err) {
    console.error('POST /api/notifications/send-alert error:', err);
    res.status(500).json({ success: false, message: 'Gagal mengirim alert.' });
  }
});

module.exports = router;
