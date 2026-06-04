# Reset / isi ulang akun dummy untuk development (UI/UX testing)
# Usage: .\scripts\seed-dummy-db.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot

Set-Location $Root

Write-Host "Seeding dummy users ke MySQL (arth_staff_dev)..." -ForegroundColor Cyan

$container = docker ps --filter "name=asa_mysql_dev" --format "{{.Names}}" 2>$null
if (-not $container) {
    Write-Host "Container asa_mysql_dev tidak berjalan. Jalankan: docker-compose up -d" -ForegroundColor Red
    exit 1
}

$seedFiles = @(
    (Join-Path $Root "docker\mysql\init\02-seed-dummy-users.sql"),
    (Join-Path $Root "docker\mysql\init\03-essential-schema.sql"),
    (Join-Path $Root "docker\mysql\init\04-material-sales-dev.sql")
)
foreach ($seedFile in $seedFiles) {
    if (-not (Test-Path $seedFile)) {
        Write-Host "File seed tidak ditemukan: $seedFile" -ForegroundColor Red
        exit 1
    }
    $sql = Get-Content $seedFile -Raw
    if ($seedFile -like "*04-material-sales-dev.sql") {
        $sql | docker exec -i asa_mysql_dev mysql -uroot -proot_password_dev 2>$null
    } else {
        $sql | docker exec -i asa_mysql_dev mysql -uasa_dev_user -pasa_dev_pass arth_staff_dev 2>$null
    }
}
if ($LASTEXITCODE -ne 0) {
    Write-Host "Gagal menjalankan seed SQL." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Selesai. Akun dummy (password: admin123):" -ForegroundColor Green
Write-Host "  Staff tab  -> staff / admin123" -ForegroundColor White
Write-Host "  Sales tab  -> sales / admin123" -ForegroundColor White
Write-Host "  Admin      -> admin / admin123" -ForegroundColor White
Write-Host ""
Write-Host "Login UI: http://localhost:8081/login.php" -ForegroundColor Cyan
Write-Host "PHPMyAdmin: http://localhost:8080" -ForegroundColor Cyan
