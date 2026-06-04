const express = require('express');
const cors = require('cors');
const cookieParser = require('cookie-parser');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
require('dotenv').config();

const { testConnections, salesPool, staffPool } = require('./config/db');
const authRoutes = require('./routes/auth');
const projectRoutes = require('./routes/projects');
const notificationRoutes = require('./routes/notifications');
const dashboardRoutes = require('./routes/dashboard');
const rabRoutes = require('./routes/rab');
const customersRoutes = require('./routes/customers');
const usersRoutes = require('./routes/users');
const settingsRoutes = require('./routes/settings');
const materialRequestRoutes = require('./routes/material-requests');
const materialReturnRoutes = require('./routes/material-returns');
const stockRoutes = require('./routes/stock');
const stockCheckRoutes = require('./routes/stock-check');
const suppliersRoutes = require('./routes/suppliers');

const app = express();



app.use(helmet());



app.use(cors({
  origin: function (origin, callback) {
    
    if (!origin) return callback(null, true)
    
    if (origin.match(/^http:\/\/localhost:\d+$/)) return callback(null, true)
    
    if (origin.includes('arthasolusiaditama.com')) return callback(null, true)
    callback(new Error('Not allowed by CORS'))
  },
  credentials: true,
}));
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());



app.use('/api/auth', authRoutes);
app.use('/api/projects', projectRoutes);
app.use('/api/notifications', notificationRoutes);
app.use('/api/dashboard', dashboardRoutes);
app.use('/api/rab', rabRoutes);
app.use('/api/customers', customersRoutes);
app.use('/api/users', usersRoutes);
app.use('/api/settings', settingsRoutes);
app.use('/api/material-requests', materialRequestRoutes);
app.use('/api/suppliers', suppliersRoutes);
app.use('/api/material-returns', materialReturnRoutes);
app.use('/api/stock', stockRoutes);
app.use('/api/stock-check', stockCheckRoutes);


if (notificationRoutes.broadcastSSE && materialRequestRoutes.setBroadcast) {
  materialRequestRoutes.setBroadcast(notificationRoutes.broadcastSSE);
}
if (notificationRoutes.broadcastSSE && materialReturnRoutes.setBroadcast) {
  materialReturnRoutes.setBroadcast(notificationRoutes.broadcastSSE);
}


const path = require('path');
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));



if (process.env.NODE_ENV === 'production') {
  const frontendDist = path.join(__dirname, '../frontend/dist');
  app.use(express.static(frontendDist));

  
  app.get('{*path}', (req, res, next) => {
    if (req.path.startsWith('/api') || req.path.startsWith('/uploads')) {
      return next();
    }
    res.sendFile(path.join(frontendDist, 'index.html'));
  });
}


app.get('/api/health', (req, res) => {
  res.json({
    message: 'Sales API is running',
    version: '1.0.0',
    timestamp: new Date().toISOString(),
  });
});


app.use((req, res) => {
  res.status(404).json({
    success: false,
    message: `Route ${req.method} ${req.url} tidak ditemukan.`,
  });
});


app.use((err, req, res, next) => {
  console.error('Server Error:', err);
  res.status(500).json({
    success: false,
    message: 'Terjadi kesalahan internal server.',
  });
});



const PORT = process.env.PORT || 5000;

app.listen(PORT, async () => {
  console.log(`\n🚀 Sales API berjalan di http://localhost:${PORT}`);
  console.log(`📡 Environment: ${process.env.NODE_ENV || 'development'}\n`);
  await testConnections();
  
  try {
    await staffPool.execute(`
      ALTER TABLE material_requests
        ADD COLUMN IF NOT EXISTS sales_approved_by INT NULL,
        ADD COLUMN IF NOT EXISTS sales_approved_at DATETIME NULL,
        ADD COLUMN IF NOT EXISTS admin_ready_by INT NULL,
        ADD COLUMN IF NOT EXISTS admin_ready_at DATETIME NULL,
        ADD COLUMN IF NOT EXISTS pickup_date DATE NULL
    `);
    console.log('✅ material_requests schema checked/updated');
  } catch (err) {
    console.error('❌ Error migrating material_requests schema:', err.message);
  }

  
  try {
    await salesPool.execute(`
      CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        company VARCHAR(200) DEFAULT NULL,
        pic_name VARCHAR(200) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);
    console.log('✅ customers table ensured');
  } catch (err) {
    console.error('❌ Failed to ensure customers table:', err.message || err);
  }
  
  try {
    await salesPool.execute("ALTER TABLE customers ADD COLUMN IF NOT EXISTS company VARCHAR(200) DEFAULT NULL");
    await salesPool.execute("ALTER TABLE customers ADD COLUMN IF NOT EXISTS pic_name VARCHAR(200) DEFAULT NULL");
    await salesPool.execute("ALTER TABLE customers ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL");
    console.log('✅ customers.company/pic_name/address columns ensured');
  } catch (err) {
    console.warn('⚠️ could not ensure customer columns:', err.message || err);
  }
  
  try {
    
    await salesPool.execute("ALTER TABLE projects ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL");
    console.log('✅ ensured projects.customer_id column exists (IF NOT EXISTS)');
  } catch (err) {
    try {
      
      const [cols] = await salesPool.execute("SHOW COLUMNS FROM projects LIKE 'customer_id'");
      if (!cols || cols.length === 0) {
        await salesPool.execute('ALTER TABLE projects ADD COLUMN customer_id INT DEFAULT NULL');
        console.log('✅ added projects.customer_id column');
      }
    } catch (e) {
      console.warn('⚠️ could not ensure projects.customer_id column:', e.message || e);
    }
  }

  
  try {
    await staffPool.execute(`
      CREATE TABLE IF NOT EXISTS material_returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        project_id INT NOT NULL,
        status ENUM('pending','sales_approved','admin_received','rejected') DEFAULT 'pending',
        note TEXT,
        sales_approved_by INT,
        sales_approved_at DATETIME,
        admin_received_by INT,
        admin_received_at DATETIME,
        rejected_by INT,
        rejected_at DATETIME,
        rejection_reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_project (project_id),
        INDEX idx_status (status),
        INDEX idx_user (user_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    await staffPool.execute(`
      CREATE TABLE IF NOT EXISTS material_return_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        material_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        notes TEXT,
        original_item_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (return_id) REFERENCES material_returns(id) ON DELETE CASCADE,
        INDEX idx_return (return_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    console.log('✅ material_returns tables ensured');
  } catch (err) {
    console.error('❌ Failed to ensure material_returns tables:', err.message || err);
  }

  
  try {
    await staffPool.execute(`
      CREATE TABLE IF NOT EXISTS material_stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_code VARCHAR(50) DEFAULT NULL,
        material_name VARCHAR(255) NOT NULL,
        category VARCHAR(100) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        unit VARCHAR(50) DEFAULT 'pcs',
        stock_qty DECIMAL(15,2) DEFAULT 0,
        avg_price DECIMAL(15,2) DEFAULT 0,
        last_price DECIMAL(15,2) DEFAULT 0,
        total_purchased DECIMAL(15,2) DEFAULT 0,
        total_spent DECIMAL(20,2) DEFAULT 0,
        min_stock DECIMAL(15,2) DEFAULT 0,
        supplier VARCHAR(255) DEFAULT NULL,
        location VARCHAR(100) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE INDEX idx_code (material_code),
        INDEX idx_name (material_name),
        INDEX idx_category (category),
        INDEX idx_active (is_active)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    await staffPool.execute(`
      CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL,
        movement_type ENUM('in', 'out', 'return', 'adjustment') NOT NULL,
        quantity DECIMAL(15,2) NOT NULL,
        price_per_unit DECIMAL(15,2) DEFAULT 0,
        stock_before DECIMAL(15,2) DEFAULT 0,
        stock_after DECIMAL(15,2) DEFAULT 0,
        reference_type VARCHAR(50) DEFAULT NULL,
        reference_id INT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_material (material_id),
        INDEX idx_type (movement_type),
        INDEX idx_reference (reference_type, reference_id),
        INDEX idx_created (created_at),
        FOREIGN KEY (material_id) REFERENCES material_stock(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    console.log('✅ material_stock & stock_movements tables ensured');

    
    await staffPool.execute("ALTER TABLE material_stock ADD COLUMN IF NOT EXISTS actual_qty DECIMAL(15,2) DEFAULT NULL");
    console.log('✅ material_stock.actual_qty column ensured');

    
    await staffPool.execute("ALTER TABLE material_request_items ADD COLUMN IF NOT EXISTS stock_id INT DEFAULT NULL");
    console.log('✅ material_request_items.stock_id column ensured');
  } catch (err) {
    console.error('❌ Failed to ensure stock tables:', err.message || err);
  }

  
  try {
    await staffPool.execute(`
      CREATE TABLE IF NOT EXISTS stock_checks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        check_name VARCHAR(255) NOT NULL,
        check_date DATE NOT NULL,
        status ENUM('draft','in_progress','completed') DEFAULT 'draft',
        total_items INT DEFAULT 0,
        checked_items INT DEFAULT 0,
        discrepancy_count INT DEFAULT 0,
        source_type ENUM('excel','stock') DEFAULT 'stock',
        notes TEXT,
        created_by INT NOT NULL,
        completed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_date (check_date)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    await staffPool.execute(`
      CREATE TABLE IF NOT EXISTS stock_check_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        check_id INT NOT NULL,
        material_stock_id INT DEFAULT NULL,
        material_code VARCHAR(100),
        material_name VARCHAR(255) NOT NULL,
        unit VARCHAR(50),
        category VARCHAR(100),
        description TEXT,
        recorded_qty DECIMAL(15,2) NOT NULL DEFAULT 0,
        actual_qty DECIMAL(15,2) DEFAULT NULL,
        avg_price DECIMAL(15,2) DEFAULT 0,
        difference DECIMAL(15,2) DEFAULT NULL,
        status ENUM('pending','checked','skipped') DEFAULT 'pending',
        checked_at DATETIME DEFAULT NULL,
        checked_by INT DEFAULT NULL,
        notes TEXT,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (check_id) REFERENCES stock_checks(id) ON DELETE CASCADE,
        INDEX idx_check_id (check_id),
        INDEX idx_status (status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
    console.log('✅ stock_checks & stock_check_items tables ensured');

    
    try {
      await staffPool.execute(`ALTER TABLE stock_check_items ADD COLUMN min_stock DECIMAL(15,2) DEFAULT NULL AFTER avg_price`);
    } catch (e) {   }
    try {
      await staffPool.execute(`ALTER TABLE stock_check_items ADD COLUMN adjustment_reason VARCHAR(50) DEFAULT NULL AFTER notes`);
    } catch (e) {   }
    try {
      await staffPool.execute(`ALTER TABLE stock_check_items ADD COLUMN adjustment_detail TEXT DEFAULT NULL AFTER adjustment_reason`);
    } catch (e) {   }
    try {
      await staffPool.execute(`ALTER TABLE stock_check_items ADD COLUMN purchase_store VARCHAR(255) DEFAULT NULL AFTER adjustment_detail`);
    } catch (e) {   }
    try {
      await staffPool.execute(`ALTER TABLE stock_check_items ADD COLUMN purchase_price DECIMAL(15,2) DEFAULT NULL AFTER purchase_store`);
    } catch (e) {   }
  } catch (err) {
    console.error('❌ Failed to ensure stock tables:', err.message || err);
  }

  
  try {
    await staffPool.execute(`ALTER TABLE suppliers ADD COLUMN maps_url VARCHAR(500) DEFAULT NULL AFTER address`);
  } catch (e) {   }

  console.log('');
});
