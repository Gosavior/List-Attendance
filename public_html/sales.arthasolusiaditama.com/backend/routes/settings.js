const express = require('express');
const { staffPool } = require('../config/db');
const { authenticate } = require('../middleware/auth');

const router = express.Router();


router.get('/', authenticate, async (req, res) => {
  try {
    const [rows] = await staffPool.execute(
      'SELECT language, theme, notification_settings FROM users WHERE id = ?',
      [req.user.id]
    );

    if (rows.length === 0) {
      return res.status(404).json({ success: false, message: 'User tidak ditemukan.' });
    }

    const user = rows[0];
    let notifications = { projectReminder: true, materialRequest: true, salesUpdate: true };
    if (user.notification_settings) {
      try {
        notifications = typeof user.notification_settings === 'string'
          ? JSON.parse(user.notification_settings)
          : user.notification_settings;
      } catch (_) {   }
    }

    res.json({
      success: true,
      data: {
        language: user.language || 'id',
        theme: user.theme || 'light',
        notifications,
      },
    });
  } catch (err) {
    console.error('GET /api/settings error:', err);
    res.status(500).json({ success: false, message: 'Terjadi kesalahan server.' });
  }
});


router.put('/', authenticate, async (req, res) => {
  try {
    const { language, theme, notifications } = req.body;

    const validLanguages = ['id', 'en'];
    const validThemes = ['light', 'dark'];

    if (language && !validLanguages.includes(language)) {
      return res.status(400).json({ success: false, message: 'Bahasa tidak valid.' });
    }
    if (theme && !validThemes.includes(theme)) {
      return res.status(400).json({ success: false, message: 'Tema tidak valid.' });
    }

    const updates = [];
    const values = [];

    if (language) {
      updates.push('language = ?');
      values.push(language);
    }
    if (theme) {
      updates.push('theme = ?');
      values.push(theme);
    }
    if (notifications && typeof notifications === 'object') {
      updates.push('notification_settings = ?');
      values.push(JSON.stringify(notifications));
    }

    if (updates.length === 0) {
      return res.status(400).json({ success: false, message: 'Tidak ada data yang diubah.' });
    }

    values.push(req.user.id);
    await staffPool.execute(
      `UPDATE users SET ${updates.join(', ')} WHERE id = ?`,
      values
    );

    res.json({ success: true, message: 'Pengaturan berhasil disimpan.' });
  } catch (err) {
    console.error('PUT /api/settings error:', err);
    res.status(500).json({ success: false, message: 'Terjadi kesalahan server.' });
  }
});

module.exports = router;
