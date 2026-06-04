const jwt = require('jsonwebtoken');



const authenticate = (req, res, next) => {
  try {
    
    let token = null;

    
    const authHeader = req.headers.authorization;
    if (authHeader && authHeader.startsWith('Bearer ')) {
      token = authHeader.split(' ')[1];
    }

    
    if (!token && req.cookies) {
      token = req.cookies.token;
    }

    if (!token) {
      return res.status(401).json({
        success: false,
        message: 'Akses ditolak. Token tidak ditemukan.',
      });
    }

    
    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    req.user = decoded; 
    next();
  } catch (err) {
    if (err.name === 'TokenExpiredError') {
      return res.status(401).json({
        success: false,
        message: 'Sesi telah berakhir. Silakan login kembali.',
      });
    }
    return res.status(401).json({
      success: false,
      message: 'Token tidak valid.',
    });
  }
};



const authorize = (...roles) => {
  return (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({
        success: false,
        message: 'Akses ditolak. Belum terautentikasi.',
      });
    }
    
    const effectiveRole = req.user.role === 'direktur' ? 'administrator' : req.user.role;
    if (!roles.includes(effectiveRole)) {
      return res.status(403).json({
        success: false,
        message: `Akses ditolak. Role "${req.user.role}" tidak memiliki izin.`,
      });
    }
    next();
  };
};

module.exports = { authenticate, authorize };
