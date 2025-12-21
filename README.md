# Sistem Pelaporan dan Monitoring Keamanan Kampus

Sistem pelaporan dan monitoring keamanan kampus berbasis web dengan fitur pelaporan insiden, analitik data, notifikasi real-time, dan peta interaktif.

## 🚀 Fitur Utama

### 📊 Dashboard
- Dashboard Mahasiswa: Statistik laporan pribadi, laporan terbaru, quick actions
- Dashboard Admin: Overview sistem, manajemen laporan, analitik kampus

### 📝 Sistem Pelaporan
- Buat laporan insiden dengan lokasi presisi
- Kategori insiden yang dapat dikustomisasi
- Status tracking: Menunggu → Diproses → Selesai

### 🗺️ Mapping & Geolocation
- Peta interaktif dengan Mapbox GL JS
- Deteksi lokasi otomatis menggunakan GPS browser
- Geocoding alamat ke koordinat (OpenStreetMap)
- Reverse geocoding koordinat ke alamat
- Caching tiles untuk performa optimal

### 📈 Analitik & Visualisasi
- Frekuensi insiden per bulan (time-series chart)
- 10 area rawan teratas (hotspot analysis)
- Pola waktu kejadian (per jam & per hari)
- Korelasi kategori & waktu
- Export data ke CSV

### 🔔 Sistem Notifikasi
- Email Notifikasi menggunakan PHPMailer
- Web Notifikasi real-time
- Notifikasi status laporan berubah
- Alert laporan baru untuk admin

### 👤 Manajemen User
- Registrasi mahasiswa
- Multi-role system (admin/mahasiswa)
- Profile management
- Reset password dengan OTP via email

## 🛠️ Teknologi yang Digunakan

### Backend
- PHP 8.0+ (Native, tanpa framework)
- MySQL 5.7+
- PDO dengan prepared statements

### Frontend
- Bootstrap 5.3.3
- Chart.js 4.x
- Mapbox GL JS 2.15.0
- Leaflet.js (untuk peta alternatif)
- Bootstrap Icons

### Library & Tools
- PHPMailer (email notifications)
- Composer (dependency management)
- OpenStreetMap Nominatim API (geocoding)
- Mapbox API (tiles & mapping)

## 👥 Penggunaan

- Admin: Login dengan email dan password admin untuk mengelola laporan dan melihat analitik.
- Mahasiswa: Register atau login untuk membuat laporan, melihat daftar laporan sendiri dan status laporan.

## 🎯 Demo Akun

### Admin
- Email: if24.maylani@mhs.ubpkarawang.ac.id
- Password: admin123

### Mahasiswa
- Email: student@example.com
- Password: student123

## 📁 Struktur Folder

Keamanan/
├── config/
│   ├── Constants.php
│   └── Database.php
├── lib/
│   ├── Auth.php
│   ├── EmailNotif.php
│   ├──EmailService.php
│   ├──maps.php
│   └── notifications.php
├── models/
│   ├── Kategori.php
│   ├── Laporan.php
│   ├── Notifikasi.php
│   └── User.php
├── public/
│   ├── analitik.php
│   ├── dashboard_admin.php
│   ├── dashboard_mahasiswa.php
│   ├── header.php
│   ├── indexphp
│   ├── java.js
│   ├── kategori.php
│   ├── laporan_admin.php
│   ├── laporan_mahasiswa.php
│   ├── login.php
│   ├── logout.php
│   ├── notifikasi.php
│   ├── register.php
|   ├── reset_password.php
│   ├── style.css
│   └── verifikasi_otp.php
├── services/
│   ├── AnalyticsService.php
│   └── CacheService.php
├── vendor/
├── .env
├── composer.json
├── composer.lock
├── keamanan.sql
└── README.md


## ⚙️ Instalasi & Setup Lokal

### Prasyarat
- PHP 8.0+ dengan extension PDO, MySQL, cURL
- MySQL 5.7+ atau MariaDB 10.4+
- Composer
- Web server (Apache/MySQL)
- Git (opsional)

### Langkah 1: Clone Repository
```bash
- git clone [URL_REPOSITORY_ANDA] keamanan-kampus
- cd keamanan-kampus

### Langkah 2: Install Dependencies
- composer install

### Langkah 3: Setup Database
- Buat database (CREATE DATABASE keamanan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;)
- Import database (SOURCE /path/to/keamanan.sql;)

### Langkah 4: Konfigurasi Environment
buat file .env di root folder
# Database Configuration
DB_HOST=localhost
DB_NAME=keamanan
DB_USER=root
DB_PASS=your_password

# Site Configuration
SITE_NAME=Sistem Keamanan Kampus
BASE_URL=http://localhost/keamanan/public/

# Campus Coordinates
CAMPUS_LAT=-6.323832
CAMPUS_LNG=107.300924

# API Configuration
MAPBOX_TOKEN=your_mapbox_token_here 

# Email Configuration (GMAIL Example)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password      
SMTP_FROM_EMAIL=your_email@gmail.com
SMTP_FROM_NAME=Sistem Informasi Keamanan Kampus

# Email Notification Settings
EMAIL_ENABLED=true
EMAIL_TEST_MODE=false
EMAIL_ADMIN_ALERT=admin@example.com

# Cache settings
CACHE_ENABLED=true
CACHE_TTL=86400
CACHE_DIR=../cache

# Application Settings
DEBUG_MODE=true
TIMEZONE=Asia/Jakarta
SESSION_LIFETIME=86400

### Langkah 5: Jalankan Aplikasi
- aktifkan XAMPP (APACHE & MySQL)
- Buka browser: http://localhost/(nama_folder)/public