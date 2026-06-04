# Akun Dummy — Development / UI-UX Testing

Hanya untuk lingkungan lokal. Jangan gunakan di production.

## Login Staff Portal

| URL | http://localhost:8081/login.php |
|-----|----------------------------------|
| Password (semua akun) | `admin123` |

### Tab Staff

| Username | Role | Keterangan |
|----------|------|------------|
| `staff` | staff | Karyawan biasa |
| `admin` | admin | Administrator staff portal |

### Tab Sales

| Username | Role | Keterangan |
|----------|------|------------|
| `sales` | sales | Sales manager |
| `direktur` | direktur | Akses sales (redirect ke sales app) |
| `administrator` | administrator | Akses sales |

### Tab Customer (UI — badge "Soon")

| Username | Role |
|----------|------|
| `customer` | customer |

## Reset / isi ulang database dummy

Jika login gagal atau password tidak cocok:

```powershell
cd arthasolusiaditama.com-main
.\scripts\seed-dummy-db.ps1
```

Atau manual via Docker:

```powershell
Get-Content docker\mysql\init\02-seed-dummy-users.sql -Raw | docker exec -i asa_mysql_dev mysql -uasa_dev_user -pasa_dev_pass arth_staff_dev
```

## Database kosong (instalasi baru)

File di `docker/mysql/init/` otomatis dijalankan saat container MySQL **pertama kali** dibuat.

Jika volume sudah ada tapi tabel kosong, jalankan `seed-dummy-db.ps1` di atas.

## PHPMyAdmin

- URL: http://localhost:8080
- User: `asa_dev_user`
- Password: `asa_dev_pass`
- Database: `arth_staff_dev`

## Login Sales System

Sales memakai **akun yang sama** di database staff (`arth_staff_dev`), dengan role khusus.

| URL frontend | http://localhost:5173/login |
| URL backend API | http://localhost:5000/api |
| Password (semua akun sales) | `admin123` |

### Akun yang boleh login Sales

| Username | Role |
|----------|------|
| `sales` | sales |
| `administrator` | administrator |
| `direktur` | direktur |

### Langkah login (disarankan untuk local dev)

1. Pastikan container berjalan: `docker-compose up -d` (minimal `mysql`, `sales_backend`, `sales_frontend`)
2. Setup sekali (jika belum):
   - Salin `sales.../backend/config/db.js.example` → `config/db.js`
   - Salin `sales.../backend/.env.example` → `.env` (atau gunakan `.env` dev yang sudah dibuat)
   - Salin `sales.../frontend/.env.example` → `.env.development` dengan `VITE_API_BASE=http://localhost:5000/api`
   - Jalankan `.\scripts\seed-dummy-db.ps1`
3. Buka http://localhost:5173/login
4. Pilih tab **Sales** (bukan Staff)
5. Login dengan `sales` / `admin123`

### Alternatif: dari Staff Portal

1. Buka http://localhost:8081/login.php
2. Pilih tab **Sales** → login `sales` / `admin123`

Di **production**, setelah login Anda diarahkan ke subdomain `sales.*`. Di **localhost**, redirect otomatis itu tidak mengubah port — lebih mudah langsung buka **localhost:5173**.

### Setup file yang di-gitignore

| File | Template |
|------|----------|
| `sales.../backend/config/db.js` | `config/db.js.example` |
| `sales.../backend/.env` | `.env.example` |
| `sales.../frontend/.env.development` | `.env.example` (`VITE_API_BASE`, bukan `VITE_API_URL`) |

## Request Material

File modul **ada** di codebase (bukan kosong):

| File | Fungsi |
|------|--------|
| `app/pages/request-material-v2.php` | Halaman utama UI (~2300 baris) |
| `app/action/handle-material-request.php` | API submit/approve |
| `app/action/migrate-material-system.php` | Migrasi tabel v1 |
| `app/action/migrate-material-v2.php` | Migrasi workflow v2 |

Tidak ada `request-material.php` lama — dashboard memakai **v2** saja.

**Setup dev** (wajib untuk halaman ini):

1. Salin `app/config/database_sales.php.example` → `database_sales.php`
2. Jalankan `.\scripts\seed-dummy-db.ps1` (membuat DB sales + tabel material + sample project)

Akses: http://localhost:8081/dashboard.php?page=request-material (login `staff` / `admin123`)

## Bypass session (tanpa login — hanya dashboard)

http://localhost:8081/bypass.php

Hanya untuk melihat layout dashboard tanpa form login; tidak menggantikan uji login asli.
