# Jalankan ulang data dummy List Absen ke MySQL Docker (development)
# Usage: .\scripts\seed-dummy-absen.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$sqlFile = Join-Path $root 'docker\mysql\init\05-seed-dummy-absen.sql'

if (-not (Test-Path $sqlFile)) {
    Write-Error "File tidak ditemukan: $sqlFile"
}

$sql = Get-Content -Raw $sqlFile
$sql | docker exec -i asa_mysql_dev mysql -uasa_dev_user -pasa_dev_pass arth_staff_dev
if ($LASTEXITCODE -ne 0) {
    Write-Error "Seed gagal (exit $LASTEXITCODE). Pastikan container asa_mysql_dev berjalan."
}

Write-Host "Data dummy absensi berhasil di-import."
Write-Host "Login contoh: staff / admin123 — buka dashboard.php?page=absen-list"
