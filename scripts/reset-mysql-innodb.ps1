#Requires -RunAsAdministrator
<#
  إعادة تهيئة MySQL 8 من الصفر (حذف InnoDB التالف) — يشغّل كمسؤول:
  PowerShell -ExecutionPolicy Bypass -File "D:\GisReport\scripts\reset-mysql-innodb.ps1"
#>
$ErrorActionPreference = 'Stop'

$serviceName = 'MySQL80'
$myIni = 'C:\ProgramData\MySQL\MySQL Server 8.0\my.ini'
$dataDir = 'C:\ProgramData\MySQL\MySQL Server 8.0\Data'
$mysqld = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe'
$mysql = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe'
$newDb = 'gis_report'

if (-not (Test-Path $mysqld)) {
    Write-Error "لم يُعثر على mysqld. تأكد من تثبيت MySQL Server 8.0."
}

Write-Host "إيقاف خدمة $serviceName ..."
Stop-Service $serviceName -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

$backup = "$dataDir`_backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
if (Test-Path $dataDir) {
    Write-Host "نسخ احتياطي للبيانات القديمة إلى: $backup"
    Rename-Item -Path $dataDir -NewName (Split-Path $backup -Leaf) -Force
}
New-Item -ItemType Directory -Path $dataDir -Force | Out-Null

Write-Host "تهيئة InnoDB من جديد ..."
& $mysqld --defaults-file="$myIni" --initialize-insecure

Write-Host "تشغيل الخدمة ..."
Start-Service $serviceName
Start-Sleep -Seconds 4

Write-Host "إنشاء قاعدة البيانات $newDb ..."
& $mysql -u root -e "CREATE DATABASE IF NOT EXISTS ``$newDb`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

Write-Host ""
Write-Host "تم. عدّل ملف .env:"
Write-Host "  DB_CONNECTION=mysql"
Write-Host "  DB_DATABASE=$newDb"
Write-Host "  DB_USERNAME=root"
Write-Host "  DB_PASSWORD="
Write-Host ""
Write-Host "ثم من مجلد المشروع: php artisan migrate --seed"
