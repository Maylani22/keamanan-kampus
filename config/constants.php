<?php
// Load Composer
require_once dirname(__DIR__) . '/vendor/autoload.php';

$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Session
session_start();

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'keamanan');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site
define('SITE_NAME', 'Sistem Keamanan Kampus');
define('BASE_URL', 'http://localhost/keamanan');

// Status
define('STATUS_PENDING', 'pending');
define('STATUS_PROCESSED', 'processed');
define('STATUS_RESOLVED', 'resolved');

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_MAHASISWA', 'mahasiswa');

define('MAPBOX_TOKEN', getenv('MAPBOX_TOKEN') ?: '');
define('MAPBOX_STYLE', 'mapbox/streets-v12'); 
define('MAPBOX_CENTER_LAT', -6.360); 
define('MAPBOX_CENTER_LNG', 106.830);
define('MAPBOX_DEFAULT_ZOOM', 16);

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_TTL', 86400);
define('CACHE_TTL_SHORT', 3600);
define('CACHE_TTL_LONG', 604800);
define('CACHE_DIR', dirname(__DIR__) . '/cache');
define('CACHE_MAPBOX_DIR', CACHE_DIR . '/mapbox');
define('CACHE_OSM_DIR', CACHE_DIR . '/osm');

// Email Configuration
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Sistem Informasi Keamanan Kampus');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: '');
define('EMAIL_ENABLED', true);
define('EMAIL_TEST_MODE', false);
define('EMAIL_ADMIN_ALERT', getenv('EMAIL_ADMIN_ALERT') ?: '');

define('ENVIRONMENT', 'development');

// Campus Coordinates
define('CAMPUS_LAT', -6.360);
define('CAMPUS_LNG', 106.830);
define('CAMPUS_NAME', 'Universitas Buana Perjuangan Karawang');

// Set timezone
date_default_timezone_set('Asia/Jakarta');
?>