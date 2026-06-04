const express = require('express');
const { salesPool, staffPool } = require('../config/db');
const { authenticate, authorize } = require('../middleware/auth');

const router = express.Router();


router.use(authenticate);



router.get('/stats', async (req, res) => {
  try {
    const isAdmin = req.user.role === 'administrator';
    const userId = req.user.id;

    const whereClause = isAdmin ? '' : 'WHERE p.assigned_to = ?';
    const params = isAdmin ? [] : [userId];

    
    const [statusRows] = await salesPool.query(
      `SELECT p.status, COUNT(*) as count
       FROM projects p ${whereClause}
       GROUP BY p.status`,
      params
    );
    const statusMap = { PROSPECT: 0, NEAREST: 0, ONGOING: 0, DONE: 0, LOST: 0 };
    statusRows.forEach(r => { statusMap[r.status] = r.count; });
    const total = Object.values(statusMap).reduce((a, b) => a + b, 0);

    
    const [revRows] = await salesPool.query(
      `SELECT
         COALESCE(SUM(CASE WHEN p.status = 'DONE' THEN p.nominal_qo ELSE 0 END), 0) as done_revenue,
         COALESCE(SUM(CASE WHEN p.status = 'LOST' THEN p.nominal_qo ELSE 0 END), 0) as lost_revenue,
         COALESCE(SUM(p.nominal_qo), 0) as total_revenue
       FROM projects p ${whereClause}`,
      params
    );

    const { done_revenue, lost_revenue, total_revenue } = revRows[0];

    
    const closingBase = statusMap.DONE + statusMap.LOST;
    const closingRate = closingBase > 0 ? Math.round((statusMap.DONE / closingBase) * 100) : 0;

    
    let mangkrakCount = 0;
    if (isAdmin) {
      const [mRows] = await salesPool.query(
        `SELECT COUNT(*) as cnt FROM projects
         WHERE status NOT IN ('DONE','LOST')
         AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)`
      );
      mangkrakCount = mRows[0].cnt;
    }

    res.json({
      success: true,
      data: {
        ...statusMap,
        total,
        done_revenue: Number(done_revenue),
        lost_revenue: Number(lost_revenue),
        total_revenue: Number(total_revenue),
        closing_rate: closingRate,
        mangkrak_count: mangkrakCount,
      },
    });
  } catch (err) {
    console.error('Dashboard stats error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat statistik.' });
  }
});



router.get('/sales-performance', authorize('administrator'), async (req, res) => {
  try {
    const [rows] = await salesPool.query(
      `SELECT
         p.assigned_to as user_id,
         COUNT(*) as total_projects,
         SUM(CASE WHEN p.status = 'DONE' THEN 1 ELSE 0 END) as done,
         SUM(CASE WHEN p.status = 'LOST' THEN 1 ELSE 0 END) as lost,
         COALESCE(SUM(CASE WHEN p.status = 'DONE' THEN p.nominal_qo ELSE 0 END), 0) as revenue
       FROM projects p
       WHERE p.assigned_to IS NOT NULL
       GROUP BY p.assigned_to
       ORDER BY done DESC, revenue DESC`
    );

    
    if (rows.length > 0) {
      const userIds = rows.map(r => r.user_id);
      const [users] = await staffPool.query(
        `SELECT id, full_name FROM users WHERE id IN (?)`,
        [userIds]
      );
      const nameMap = {};
      users.forEach(u => { nameMap[u.id] = u.full_name; });
      rows.forEach(r => {
        r.name = nameMap[r.user_id] || `User #${r.user_id}`;
        r.revenue = Number(r.revenue);
        r.win_rate = (r.done + r.lost) > 0 ? Math.round((r.done / (r.done + r.lost)) * 100) : 0;
      });
    }

    res.json({ success: true, data: rows });
  } catch (err) {
    console.error('Sales performance error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat performa sales.' });
  }
});



router.get('/recent-projects', async (req, res) => {
  try {
    const isAdmin = req.user.role === 'administrator';
    const limit = Math.min(parseInt(req.query.limit) || 10, 20);

    let query = `
      SELECT p.id, p.project_name, p.customer_name, p.status, p.nominal_qo,
             p.created_at, p.updated_at, p.assigned_to
      FROM projects p`;
    const params = [];

    if (!isAdmin) {
      query += ' WHERE p.assigned_to = ?';
      params.push(req.user.id);
    }
    query += ' ORDER BY p.created_at DESC LIMIT ?';
    params.push(limit);

    const [rows] = await salesPool.query(query, params);

    
    if (isAdmin && rows.length > 0) {
      const userIds = [...new Set(rows.map(r => r.assigned_to).filter(Boolean))];
      if (userIds.length > 0) {
        const [users] = await staffPool.query('SELECT id, full_name FROM users WHERE id IN (?)', [userIds]);
        const nameMap = {};
        users.forEach(u => { nameMap[u.id] = u.full_name; });
        rows.forEach(r => { r.sales_name = nameMap[r.assigned_to] || '-'; });
      }
    }

    res.json({ success: true, data: rows });
  } catch (err) {
    console.error('Recent projects error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat project terbaru.' });
  }
});



router.get('/monthly-revenue', authorize('administrator'), async (req, res) => {
  try {
    const [rows] = await salesPool.query(
      `SELECT
         DATE_FORMAT(p.updated_at, '%Y-%m') as month_key,
         DATE_FORMAT(p.updated_at, '%b') as month_label,
         COALESCE(SUM(p.nominal_qo), 0) as revenue
       FROM projects p
       WHERE p.status = 'DONE'
         AND p.updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
       GROUP BY month_key, month_label
       ORDER BY month_key ASC`
    );

    rows.forEach(r => { r.revenue = Number(r.revenue); });

    res.json({ success: true, data: rows });
  } catch (err) {
    console.error('Monthly revenue error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat revenue bulanan.' });
  }
});



router.get('/stale-projects', authorize('administrator'), async (req, res) => {
  try {
    const days = parseInt(req.query.days) || 30;

    const [rows] = await salesPool.query(
      `SELECT p.id, p.project_name, p.customer_name, p.status, p.assigned_to,
              p.updated_at, DATEDIFF(NOW(), p.updated_at) as days_stale
       FROM projects p
       WHERE p.status NOT IN ('DONE','LOST')
         AND p.updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
       ORDER BY days_stale DESC
       LIMIT 20`,
      [days]
    );

    if (rows.length > 0) {
      const userIds = [...new Set(rows.map(r => r.assigned_to).filter(Boolean))];
      if (userIds.length > 0) {
        const [users] = await staffPool.query('SELECT id, full_name FROM users WHERE id IN (?)', [userIds]);
        const nameMap = {};
        users.forEach(u => { nameMap[u.id] = u.full_name; });
        rows.forEach(r => { r.sales_name = nameMap[r.assigned_to] || '-'; });
      }
    }

    res.json({ success: true, data: rows });
  } catch (err) {
    console.error('Stale projects error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat project mangkrak.' });
  }
});



router.get('/sales-leaderboard', async (req, res) => {
  try {
    const now = new Date();
    const month = parseInt(req.query.month) || (now.getMonth() + 1);
    const year = parseInt(req.query.year) || now.getFullYear();

    
    const [targets] = await salesPool.query(
      'SELECT target_amount FROM sales_targets WHERE month = ? AND year = ?',
      [month, year]
    );
    const targetAmount = targets.length > 0 ? Number(targets[0].target_amount) : 0;

    
    const [rows] = await salesPool.query(
      `SELECT
         p.assigned_to as user_id,
         COUNT(*) as total_projects,
         SUM(CASE WHEN p.status = 'DONE' THEN 1 ELSE 0 END) as done,
         COALESCE(SUM(CASE WHEN p.status = 'DONE' THEN p.nominal_qo ELSE 0 END), 0) as revenue
       FROM projects p
       WHERE p.assigned_to IS NOT NULL
         AND MONTH(p.updated_at) = ? AND YEAR(p.updated_at) = ?
       GROUP BY p.assigned_to
       ORDER BY revenue DESC`,
      [month, year]
    );

    if (rows.length > 0) {
      const userIds = rows.map(r => r.user_id);
      const [users] = await staffPool.query(
        'SELECT id, full_name FROM users WHERE id IN (?)',
        [userIds]
      );
      const nameMap = {};
      users.forEach(u => { nameMap[u.id] = u.full_name; });
      rows.forEach(r => {
        r.name = nameMap[r.user_id] || `User #${r.user_id}`;
        r.revenue = Number(r.revenue);
      });
    }

    res.json({
      success: true,
      data: {
        month,
        year,
        target: targetAmount,
        leaderboard: rows,
      },
    });
  } catch (err) {
    console.error('Sales leaderboard error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat leaderboard.' });
  }
});



router.get('/chart-data', authorize('administrator'), async (req, res) => {
  try {
    const period = req.query.period || 'monthly';
    const year = parseInt(req.query.year) || new Date().getFullYear();

    if (period === 'yearly') {
      const [rows] = await salesPool.query(
        `SELECT
           YEAR(p.updated_at) as label,
           COALESCE(SUM(CASE WHEN p.status = 'DONE' THEN p.nominal_qo ELSE 0 END), 0) as revenue,
           COALESCE(SUM(CASE WHEN p.status = 'LOST' THEN p.nominal_qo ELSE 0 END), 0) as lost,
           COUNT(CASE WHEN p.status = 'DONE' THEN 1 END) as deals
         FROM projects p
         WHERE YEAR(p.updated_at) >= YEAR(NOW()) - 4
         GROUP BY YEAR(p.updated_at)
         ORDER BY label ASC`
      );
      rows.forEach(r => { r.revenue = Number(r.revenue); r.lost = Number(r.lost); });

      
      const [tRows] = await salesPool.query(
        `SELECT year, SUM(target_amount) as target FROM sales_targets
         WHERE year >= YEAR(NOW()) - 4
         GROUP BY year ORDER BY year ASC`
      );
      const targetMap = {};
      tRows.forEach(t => { targetMap[t.year] = Number(t.target); });
      rows.forEach(r => { r.target = targetMap[r.label] || 0; });

      return res.json({ success: true, data: rows });
    }

    
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    const [rows] = await salesPool.query(
      `SELECT
         MONTH(p.updated_at) as m,
         COALESCE(SUM(CASE WHEN p.status = 'DONE' THEN p.nominal_qo ELSE 0 END), 0) as revenue,
         COALESCE(SUM(CASE WHEN p.status = 'LOST' THEN p.nominal_qo ELSE 0 END), 0) as lost,
         COUNT(CASE WHEN p.status = 'DONE' THEN 1 END) as deals
       FROM projects p
       WHERE YEAR(p.updated_at) = ?
       GROUP BY MONTH(p.updated_at)
       ORDER BY m ASC`,
      [year]
    );

    const revMap = {};
    rows.forEach(r => { revMap[r.m] = { revenue: Number(r.revenue), lost: Number(r.lost), deals: r.deals }; });

    
    const [tRows] = await salesPool.query(
      'SELECT month, target_amount FROM sales_targets WHERE year = ?',
      [year]
    );
    const targetMap = {};
    tRows.forEach(t => { targetMap[t.month] = Number(t.target_amount); });

    const data = months.map((label, i) => ({
      label,
      month: i + 1,
      revenue: revMap[i + 1]?.revenue || 0,
      lost: revMap[i + 1]?.lost || 0,
      deals: revMap[i + 1]?.deals || 0,
      target: targetMap[i + 1] || 0,
    }));

    res.json({ success: true, data });
  } catch (err) {
    console.error('Chart data error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat data chart.' });
  }
});



router.get('/sales-target', async (req, res) => {
  try {
    const now = new Date();
    const month = parseInt(req.query.month) || (now.getMonth() + 1);
    const year = parseInt(req.query.year) || now.getFullYear();

    const [rows] = await salesPool.query(
      'SELECT * FROM sales_targets WHERE month = ? AND year = ?',
      [month, year]
    );

    res.json({
      success: true,
      data: rows.length > 0
        ? { month, year, target_amount: Number(rows[0].target_amount) }
        : { month, year, target_amount: 0 },
    });
  } catch (err) {
    console.error('Get sales target error:', err);
    res.status(500).json({ success: false, message: 'Gagal memuat target.' });
  }
});



router.put('/sales-target', authorize('administrator'), async (req, res) => {
  try {
    const { month, year, target_amount } = req.body;
    if (!month || !year || target_amount == null) {
      return res.status(400).json({ success: false, message: 'month, year, target_amount diperlukan.' });
    }

    await salesPool.execute(
      `INSERT INTO sales_targets (month, year, target_amount, created_by)
       VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE target_amount = VALUES(target_amount), updated_at = NOW()`,
      [month, year, target_amount, req.user.id]
    );

    res.json({ success: true, message: 'Target berhasil disimpan.' });
  } catch (err) {
    console.error('Set sales target error:', err);
    res.status(500).json({ success: false, message: 'Gagal menyimpan target.' });
  }
});

module.exports = router;
