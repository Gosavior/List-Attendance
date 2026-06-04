const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { staffPool } = require('../config/db');
const { authenticate } = require('../middleware/auth');

const router = express.Router();
router.post('/login', async (req, res) => {
  try {
    const { username, password } = req.body;

    if (!username || !password) {
      return res.status(400).json({
        success: false,
        message: 'Username dan password harus diisi.',
      });
    }

    const [rows] = await staffPool.execute(
      `SELECT id, full_name, username, email, password, role, avatar, photo, is_active,
              failed_logins, lock_until
       FROM users 
       WHERE (username = ? OR email = ?)
         AND role IN ('administrator', 'direktur', 'sales')`,
      [username, username]
    );

    if (rows.length === 0) {
      return res.status(401).json({
        success: false,
        message: 'Username atau password salah.',
      });
    }

    const user = rows[0];

    if (!user.is_active) {
      return res.status(403).json({
        success: false,
        message: 'Akun Anda telah dinonaktifkan. Hubungi admin.',
      });
    }

    if (user.lock_until && new Date(user.lock_until) > new Date()) {
      const lockMinutes = Math.ceil((new Date(user.lock_until) - new Date()) / 60000);
      return res.status(429).json({
        success: false,
        message: `Akun terkunci. Coba lagi dalam ${lockMinutes} menit.`,
      });
    }

    
    const normalizedHash = user.password.replace(/^\$2y\$/, '$2a$');
    const isMatch = await bcrypt.compare(password, normalizedHash);

    if (!isMatch) {
      const newFailedCount = (user.failed_logins || 0) + 1;
      let lockUntil = null;

      if (newFailedCount >= 5) {
        lockUntil = new Date(Date.now() + 15 * 60 * 1000);
      }

      await staffPool.execute(
        'UPDATE users SET failed_logins = ?, lock_until = ? WHERE id = ?',
        [newFailedCount, lockUntil, user.id]
      );

      return res.status(401).json({
        success: false,
        message: 'Username atau password salah.',
        remaining: Math.max(0, 5 - newFailedCount),
      });
    }

    await staffPool.execute(
      'UPDATE users SET failed_logins = 0, lock_until = NULL, last_login = NOW() WHERE id = ?',
      [user.id]
    );

    const tokenPayload = {
      id: user.id,
      name: user.full_name,
      username: user.username,
      email: user.email,
      role: user.role,
      avatar: user.avatar || user.photo || null,
    };

    const token = jwt.sign(tokenPayload, process.env.JWT_SECRET, {
      expiresIn: process.env.JWT_EXPIRES_IN || '8h',
    });

    res.cookie('token', token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'lax',
      maxAge: 8 * 60 * 60 * 1000,
    });

    return res.json({
      success: true,
      message: `Selamat datang, ${user.full_name}!`,
      token,
      user: {
        id: user.id,
        name: user.full_name,
        username: user.username,
        email: user.email,
        role: user.role,
        avatar: user.avatar || user.photo || null,
      },
    });
  } catch (err) {
    console.error('Login error:', err);
    return res.status(500).json({
      success: false,
      message: 'Terjadi kesalahan server. Coba lagi nanti.',
    });
  }
});



router.post('/logout', (req, res) => {
  res.clearCookie('token', {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
  });
  return res.json({
    success: true,
    message: 'Berhasil logout.',
  });
});



router.get('/me', authenticate, async (req, res) => {
  try {
    const [rows] = await staffPool.execute(
      `SELECT id, full_name, username, email, phone, role, avatar, photo, 
              created_at, last_login, language, theme, notification_settings
       FROM users WHERE id = ? AND is_active = 1`,
      [req.user.id]
    );

    if (rows.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'User tidak ditemukan.',
      });
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
    return res.json({
      success: true,
      user: {
        id: user.id,
        name: user.full_name,
        username: user.username,
        email: user.email,
        phone: user.phone,
        role: user.role,
        avatar: user.avatar || user.photo || null,
        createdAt: user.created_at,
        lastLogin: user.last_login,
        language: user.language || 'id',
        theme: user.theme || 'light',
        notifications,
      },
    });
  } catch (err) {
    console.error('Get me error:', err);
    return res.status(500).json({
      success: false,
      message: 'Terjadi kesalahan server.',
    });
  }
});



router.put('/change-password', authenticate, async (req, res) => {
  try {
    const { currentPassword, newPassword } = req.body;

    if (!currentPassword || !newPassword) {
      return res.status(400).json({
        success: false,
        message: 'Password lama dan baru harus diisi.',
      });
    }

    if (newPassword.length < 6) {
      return res.status(400).json({
        success: false,
        message: 'Password baru minimal 6 karakter.',
      });
    }

    const [rows] = await staffPool.execute(
      'SELECT password FROM users WHERE id = ?',
      [req.user.id]
    );

    if (rows.length === 0) {
      return res.status(404).json({ success: false, message: 'User tidak ditemukan.' });
    }

    const isMatch = await bcrypt.compare(currentPassword, rows[0].password);
    if (!isMatch) {
      return res.status(401).json({
        success: false,
        message: 'Password lama salah.',
      });
    }

    const salt = await bcrypt.genSalt(10);
    const hashedPassword = await bcrypt.hash(newPassword, salt);

    await staffPool.execute(
      'UPDATE users SET password = ? WHERE id = ?',
      [hashedPassword, req.user.id]
    );

    return res.json({
      success: true,
      message: 'Password berhasil diubah.',
    });
  } catch (err) {
    console.error('Change password error:', err);
    return res.status(500).json({
      success: false,
      message: 'Terjadi kesalahan server.',
    });
  }
});

module.exports = router;
