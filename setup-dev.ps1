# ============================================
# SETUP SCRIPT UNTUK IT BARU
# Artha Solusi Aditama - Development Environment
# ============================================

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "ARTHA SOLUSI ADITAMA" -ForegroundColor Cyan
Write-Host "Development Environment Setup" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Check prerequisites
Write-Host "[1/8] Checking prerequisites..." -ForegroundColor Yellow

# Check Git
try {
    $gitVersion = git --version
    Write-Host "  ✓ Git installed: $gitVersion" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Git not found! Please install Git first." -ForegroundColor Red
    Write-Host "    Download: https://git-scm.com/download/win" -ForegroundColor Yellow
    exit 1
}

# Check Docker
try {
    $dockerVersion = docker --version
    Write-Host "  ✓ Docker installed: $dockerVersion" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Docker not found! Please install Docker Desktop first." -ForegroundColor Red
    Write-Host "    Download: https://www.docker.com/products/docker-desktop" -ForegroundColor Yellow
    exit 1
}

# Check Docker Compose
try {
    $composeVersion = docker-compose --version
    Write-Host "  ✓ Docker Compose installed: $composeVersion" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Docker Compose not found!" -ForegroundColor Red
    exit 1
}

# Check Node.js
try {
    $nodeVersion = node --version
    Write-Host "  ✓ Node.js installed: $nodeVersion" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Node.js not found! Please install Node.js 18+ first." -ForegroundColor Red
    Write-Host "    Download: https://nodejs.org/" -ForegroundColor Yellow
    exit 1
}

Write-Host ""

# Setup environment files
Write-Host "[2/8] Setting up environment files..." -ForegroundColor Yellow

$envFiles = @(
    @{
        Example = "public_html\staff.arthasolusiaditama.com\.env.example"
        Target = "public_html\staff.arthasolusiaditama.com\.env"
    },
    @{
        Example = "public_html\sales.arthasolusiaditama.com\backend\.env.example"
        Target = "public_html\sales.arthasolusiaditama.com\backend\.env"
    },
    @{
        Example = "public_html\sales.arthasolusiaditama.com\frontend\.env.example"
        Target = "public_html\sales.arthasolusiaditama.com\frontend\.env.development"
    }
)

foreach ($env in $envFiles) {
    if (Test-Path $env.Example) {
        if (-not (Test-Path $env.Target)) {
            Copy-Item $env.Example $env.Target
            Write-Host "  ✓ Created: $($env.Target)" -ForegroundColor Green
        } else {
            Write-Host "  ⚠ Already exists: $($env.Target)" -ForegroundColor Yellow
        }
    } else {
        Write-Host "  ✗ Template not found: $($env.Example)" -ForegroundColor Red
    }
}

Write-Host ""

# Create necessary directories
Write-Host "[3/8] Creating necessary directories..." -ForegroundColor Yellow

$directories = @(
    "public_html\staff.arthasolusiaditama.com\storage\uploads",
    "public_html\staff.arthasolusiaditama.com\storage\photos",
    "public_html\sales.arthasolusiaditama.com\backend\public\uploads",
    "docker\mysql\init"
)

foreach ($dir in $directories) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "  ✓ Created: $dir" -ForegroundColor Green
    } else {
        Write-Host "  ⚠ Already exists: $dir" -ForegroundColor Yellow
    }
}

Write-Host ""

# Install dependencies
Write-Host "[4/8] Installing dependencies..." -ForegroundColor Yellow
Write-Host "  This may take a few minutes..." -ForegroundColor Gray

# Staff Portal
Write-Host "  → Installing Staff Portal dependencies..." -ForegroundColor Cyan
Set-Location "public_html\staff.arthasolusiaditama.com"
if (Test-Path "package.json") {
    npm install --silent
    Write-Host "    ✓ Staff Portal dependencies installed" -ForegroundColor Green
}
Set-Location "..\..\.."

# Sales Backend
Write-Host "  → Installing Sales Backend dependencies..." -ForegroundColor Cyan
Set-Location "public_html\sales.arthasolusiaditama.com\backend"
if (Test-Path "package.json") {
    npm install --silent
    Write-Host "    ✓ Sales Backend dependencies installed" -ForegroundColor Green
}
Set-Location "..\..\..\.."

# Sales Frontend
Write-Host "  → Installing Sales Frontend dependencies..." -ForegroundColor Cyan
Set-Location "public_html\sales.arthasolusiaditama.com\frontend"
if (Test-Path "package.json") {
    npm install --silent
    Write-Host "    ✓ Sales Frontend dependencies installed" -ForegroundColor Green
}
Set-Location "..\..\..\.."

# Report System
Write-Host "  → Installing Report System dependencies..." -ForegroundColor Cyan
Set-Location "public_html\report.arthasolusiaditama.com"
if (Test-Path "package.json") {
    npm install --silent
    Write-Host "    ✓ Report System dependencies installed" -ForegroundColor Green
}
Set-Location "..\.."

Write-Host ""

# Docker setup
Write-Host "[5/8] Setting up Docker environment..." -ForegroundColor Yellow

Write-Host "  → Building Docker images..." -ForegroundColor Cyan
docker-compose build --quiet

Write-Host "  → Starting Docker containers..." -ForegroundColor Cyan
docker-compose up -d

Write-Host "  ✓ Docker environment ready" -ForegroundColor Green
Write-Host ""

# Wait for MySQL to be ready
Write-Host "[6/8] Waiting for MySQL to be ready..." -ForegroundColor Yellow
$maxAttempts = 30
$attempt = 0
$ready = $false

while (-not $ready -and $attempt -lt $maxAttempts) {
    $attempt++
    try {
        $result = docker-compose exec -T mysql mysqladmin ping -h localhost --silent 2>$null
        if ($LASTEXITCODE -eq 0) {
            $ready = $true
            Write-Host "  ✓ MySQL is ready" -ForegroundColor Green
        } else {
            Write-Host "  ⏳ Waiting... ($attempt/$maxAttempts)" -ForegroundColor Gray
            Start-Sleep -Seconds 2
        }
    } catch {
        Write-Host "  ⏳ Waiting... ($attempt/$maxAttempts)" -ForegroundColor Gray
        Start-Sleep -Seconds 2
    }
}

if (-not $ready) {
    Write-Host "  ✗ MySQL failed to start. Please check Docker logs." -ForegroundColor Red
    Write-Host "    Run: docker-compose logs mysql" -ForegroundColor Yellow
}

Write-Host ""

# Verify setup
Write-Host "[7/8] Verifying setup..." -ForegroundColor Yellow

$services = @(
    @{ Name = "MySQL"; Port = 3307 },
    @{ Name = "PHPMyAdmin"; Port = 8080 },
    @{ Name = "Staff Portal"; Port = 8081 },
    @{ Name = "Sales Backend"; Port = 5000 },
    @{ Name = "Sales Frontend"; Port = 5173 },
    @{ Name = "Report System"; Port = 5174 },
    @{ Name = "Socket Server"; Port = 3000 }
)

foreach ($service in $services) {
    $connection = Test-NetConnection -ComputerName localhost -Port $service.Port -WarningAction SilentlyContinue -ErrorAction SilentlyContinue
    if ($connection.TcpTestSucceeded) {
        Write-Host "  ✓ $($service.Name) running on port $($service.Port)" -ForegroundColor Green
    } else {
        Write-Host "  ⚠ $($service.Name) not responding on port $($service.Port)" -ForegroundColor Yellow
    }
}

Write-Host ""

# Final instructions
Write-Host "[8/8] Setup complete!" -ForegroundColor Green
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "NEXT STEPS:" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Edit environment files dengan credentials Anda:" -ForegroundColor White
Write-Host "   - public_html\staff.arthasolusiaditama.com\.env" -ForegroundColor Gray
Write-Host "   - public_html\sales.arthasolusiaditama.com\backend\.env" -ForegroundColor Gray
Write-Host "   - public_html\sales.arthasolusiaditama.com\frontend\.env.development" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Import development database:" -ForegroundColor White
Write-Host "   - Minta SQL dump dari supervisor" -ForegroundColor Gray
Write-Host "   - Import via PHPMyAdmin: http://localhost:8080" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Access aplikasi:" -ForegroundColor White
Write-Host "   - PHPMyAdmin:    http://localhost:8080" -ForegroundColor Gray
Write-Host "   - Staff Portal:  http://localhost:8081" -ForegroundColor Gray
Write-Host "   - Sales Backend: http://localhost:5000" -ForegroundColor Gray
Write-Host "   - Sales Frontend: http://localhost:5173" -ForegroundColor Gray
Write-Host "   - Report System: http://localhost:5174" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Baca dokumentasi:" -ForegroundColor White
Write-Host "   - CONTRIBUTING.md - Panduan development" -ForegroundColor Gray
Write-Host "   - SECURITY.md - Kebijakan keamanan" -ForegroundColor Gray
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "USEFUL COMMANDS:" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Start services:   docker-compose up -d" -ForegroundColor Gray
Write-Host "Stop services:    docker-compose down" -ForegroundColor Gray
Write-Host "View logs:        docker-compose logs -f [service-name]" -ForegroundColor Gray
Write-Host "Restart service:  docker-compose restart [service-name]" -ForegroundColor Gray
Write-Host ""
Write-Host "Happy coding! 🚀" -ForegroundColor Green
Write-Host ""
