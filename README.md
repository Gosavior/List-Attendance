# Artha Solusi Aditama
## Company Management System

Sistem manajemen terintegrasi untuk Artha Solusi Aditama yang mencakup Staff Portal, Sales System, dan Report Generator.

---

## 📋 Sistem Overview

### 1. **Staff Portal** (`staff.arthasolusiaditama.com`)
Portal internal untuk manajemen karyawan dengan fitur:
- Attendance tracking dengan photo verification
- Project daily updates
- Material requests workflow
- GPS tracking & geofencing
- Leave management
- Internal chat system
- Payroll automation
- Tools/equipment management

**Tech Stack:** PHP 8+, MySQL, Tailwind CSS, Socket.IO

### 2. **Sales System** (`sales.arthasolusiaditama.com`)
Sistem penjualan dan manajemen project:
- Dashboard analytics
- Project pipeline management
- Customer database
- RAB (Budget) planning dengan real-time cost tracking
- Document management (QO/AO/PO)
- Material request approval
- Stock management
- Invoice & delivery scheduling

**Tech Stack:** React 18, Node.js, Express, MySQL, Tailwind CSS

### 3. **Report System** (`report.arthasolusiaditama.com`)
Generator laporan service:
- PDF generation
- Report templates
- Digital signatures
- Report storage & management

**Tech Stack:** React 19, Vite, Tailwind CSS, jsPDF

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.0+
- Node.js 18+
- MySQL 8.0+
- Docker & Docker Compose (recommended)
- Git

### Setup Development Environment

#### Option 1: Docker (Recommended)
```bash
# Clone repository
git clone <repository-url>
cd arthasolusiaditama.com

# Run setup script
.\setup-dev.ps1

# Access applications
# - PHPMyAdmin: http://localhost:8080
# - Staff Portal: http://localhost:8081
# - Sales Backend: http://localhost:5000
# - Sales Frontend: http://localhost:5173
# - Report System: http://localhost:5174
```

#### Option 2: Manual Setup
```bash
# 1. Setup environment files
cp public_html/staff.arthasolusiaditama.com/.env.example public_html/staff.arthasolusiaditama.com/.env
cp public_html/sales.arthasolusiaditama.com/backend/.env.example public_html/sales.arthasolusiaditama.com/backend/.env
cp public_html/sales.arthasolusiaditama.com/frontend/.env.example public_html/sales.arthasolusiaditama.com/frontend/.env.development

# 2. Edit .env files dengan credentials Anda

# 3. Install dependencies
cd public_html/staff.arthasolusiaditama.com && npm install
cd ../sales.arthasolusiaditama.com/backend && npm install
cd ../frontend && npm install
cd ../../report.arthasolusiaditama.com && npm install

# 4. Setup database
# Import SQL dump ke MySQL

# 5. Start services
# Staff Portal: Setup web server (Apache/Nginx)
# Sales Backend: npm run dev
# Sales Frontend: npm run dev
# Report System: npm run dev
```

---

## 📁 Project Structure

```
arthasolusiaditama.com/
├── public_html/
│   ├── staff.arthasolusiaditama.com/     # Staff Portal (PHP)
│   │   ├── app/                          # Application logic
│   │   ├── database/                     # SQL schemas
│   │   ├── public/                       # Static assets
│   │   ├── socket-server/                # Chat server
│   │   └── .env.example                  # Environment template
│   │
│   ├── sales.arthasolusiaditama.com/     # Sales System
│   │   ├── backend/                      # Node.js API
│   │   │   ├── routes/                   # API endpoints
│   │   │   ├── middleware/               # Auth, validation
│   │   │   └── .env.example              # Environment template
│   │   └── frontend/                     # React app
│   │       ├── src/                      # React components
│   │       └── .env.example              # Environment template
│   │
│   └── report.arthasolusiaditama.com/    # Report Generator
│       └── src/                          # React components
│
├── docker/                               # Docker configurations
│   ├── php/                              # PHP Apache container
│   ├── node/                             # Node.js container
│   └── mysql/                            # MySQL container
│
├── .gitignore                            # Git ignore rules
├── docker-compose.yml                    # Docker services
├── setup-dev.ps1                         # Setup script
├── CONTRIBUTING.md                       # Development guidelines
├── SECURITY.md                           # Security policy
└── README.md                             # This file
```

---

## 🔐 Security & Access Control

### ⚠️ PENTING: Baca Sebelum Mulai Development

**File yang TIDAK BOLEH di-commit:**
- `.env` files (gunakan `.env.example`)
- Database configuration files
- Backup files (`.tar.gz`, `.sql`)
- Uploaded files (`storage/`, `uploads/`)

**Untuk detail lengkap, baca:**
- `SECURITY.md` - Kebijakan keamanan & access control
- `CONTRIBUTING.md` - Panduan development

---

## 🛠️ Development Workflow

### 1. Create Feature Branch
```bash
git checkout -b feature/nama-fitur
```

### 2. Development
```bash
# Coding...
git add .
git commit -m "feat: deskripsi fitur"
```

### 3. Push & Create Pull Request
```bash
git push origin feature/nama-fitur
# Create PR di GitHub
```

### 4. Code Review
- Tunggu review dari IT ASA
- Perbaiki jika ada feedback
- Merge setelah approved

---

## 📚 Documentation

- **CONTRIBUTING.md** - Panduan untuk developer
- **SECURITY.md** - Kebijakan keamanan
- **API Documentation** - (Request dari IT ASA)
- **Database Schema** - (Request dari IT ASA)

---

## 🐛 Bug Reports & Feature Requests

### Melaporkan Bug:
1. Create issue di GitHub dengan label `bug`
2. Sertakan:
   - Deskripsi bug
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshots (jika ada)

### Security Issues:
**JANGAN buat public issue!**
Langsung hubungi IT ASA.

---

## 🧪 Testing

```bash
# Run tests (jika ada)
npm test

# Build untuk production
npm run build
```

---

## 📞 Support

**Questions:**
- Create issue dengan label `question`
- Atau hubungi Admin ASA

**Urgent Issues:**
- Langsung hubungi Admin ASA

---

## 📝 License

Proprietary - Artha Solusi Aditama
© 2026 All rights reserved.

**CONFIDENTIAL**: Source code ini adalah milik Artha Solusi Aditama dan tidak boleh didistribusikan tanpa izin.

---

## 🎯 Development Guidelines

### Code Style:
- **PHP**: PSR-12 coding standard
- **JavaScript/React**: ESLint + Prettier
- **CSS**: Tailwind CSS utilities

### Commit Messages:
```
feat: add new feature
fix: fix bug
refactor: refactor code
docs: update documentation
style: formatting changes
test: add tests
chore: maintenance tasks
```

### Branch Naming:
```
feature/nama-fitur
bugfix/nama-bug
hotfix/nama-hotfix
```

---

**Last Updated:** 23 Mei 2026

**Version:** 1.0.0
