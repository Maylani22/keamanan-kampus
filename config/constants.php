<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'keamanan');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

define('SITE_NAME', 'Sistem Keamanan Kampus');
define('BASE_URL', 'http://localhost/keamanan');

define('STATUS_PENDING', 'pending');
define('STATUS_PROCESSED', 'processed');
define('STATUS_RESOLVED', 'resolved');

define('ROLE_ADMIN', 'admin');
define('ROLE_MAHASISWA', 'mahasiswa');

define('MAPBOX_TOKEN', $_ENV['MAPBOX_TOKEN'] ?? '');
define('MAPBOX_STYLE', $_ENV['MAPBOX_STYLE'] ?? 'mapbox/streets-v12');
define('MAPBOX_CENTER_LAT', -6.360); 
define('MAPBOX_CENTER_LNG', 106.830);
define('MAPBOX_DEFAULT_ZOOM', 16);

define('CACHE_ENABLED', true);
define('CACHE_TTL', 86400);
define('CACHE_TTL_SHORT', 3600);
define('CACHE_TTL_LONG', 604800);
define('CACHE_DIR', dirname(__DIR__) . '/cache');
define('CACHE_MAPBOX_DIR', CACHE_DIR . '/mapbox');
define('CACHE_OSM_DIR', CACHE_DIR . '/osm');

define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? null);
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? null);
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? null);
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Sistem Informasi Keamanan Kampus');
define('ADMIN_EMAIL', $_ENV['ADMIN_EMAIL'] ?? null);
define('EMAIL_ENABLED', true);
define('EMAIL_TEST_MODE', false);
define('EMAIL_ADMIN_ALERT', $_ENV['EMAIL_ADMIN_ALERT'] ?? null);

define('ENVIRONMENT', 'development');

define('CAMPUS_LAT', -6.360);
define('CAMPUS_LNG', 106.830);
define('CAMPUS_NAME', 'Universitas Buana Perjuangan Karawang');

date_default_timezone_set('Asia/Jakarta');
?>