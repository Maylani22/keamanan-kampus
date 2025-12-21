<?php
require_once '../config/constants.php';
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../models/Laporan.php';
require_once '../models/Kategori.php';

checkLogin();

$database = new Database();
$db = $database->connect();

$auth = new Auth();
$user = $auth->getCurrentUser();

// Hanya mahasiswa yang bisa akses
if ($auth->isAdmin()) {
    header('Location: laporan_admin.php');
    exit;
}

$laporanModel = new Laporan($db);
$kategoriModel = new Kategori($db);

// AMBIL DATA NOTIFIKASI (TAMBAHKAN INI)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifikasi WHERE user_id = ? AND status = 'unread'");
$stmt->execute([$user['id']]);
$unread_count = $stmt->fetch()['count'];

// 📊 GET DATA STATISTIK
$laporans = $laporanModel->getByUser($user['id']);
$totalLaporans = count($laporans);
$pendingCount = 0;
$resolvedCount = 0;

foreach ($laporans as $laporan) {
    if ($laporan['status'] == 'pending' || $laporan['status'] == 'Pending') {
        $pendingCount++;
    } elseif ($laporan['status'] == 'resolved' || $laporan['status'] == 'Resolved' || $laporan['status'] == 'selesai') {
        $resolvedCount++;
    }
}

// Ambil 5 laporan terbaru
$recentLaporans = array_slice($laporans, 0, 5);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - <?= SITE_NAME ?></title>
    <?php include 'header.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding-top: 70px;
        }
        .stat-card {
            height: 140px;
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        /* ========== NAVBAR STYLES ========== */
        .navbar-custom {
            background: linear-gradient(135deg, #2c3e50 0%, #1a2530 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 0.5rem 0;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 600;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i {
            font-size: 1.5rem;
            color: #3498db;
        }

        .nav-link {
            color: rgba(255,255,255,0.85) !important;
            font-weight: 500;
            padding: 8px 15px !important;
            border-radius: 5px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link:hover, .nav-link.active {
            color: white !important;
            background: rgba(255,255,255,0.1);
        }

        .nav-link i {
            font-size: 1.1rem;
        }

        .quick-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .dropdown-toggle {
            background: none;
            border: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .dropdown-toggle:hover {
            background: rgba(255,255,255,0.1);
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 10px 0;
            min-width: 200px;
        }

        .dropdown-header {
            font-weight: 600;
            color: #2c3e50;
            padding: 10px 15px;
        }

        .dropdown-item {
            padding: 8px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        .dropdown-item i {
            width: 20px;
            color: #6c757d;
        }

        .dropdown-divider {
            margin: 8px 0;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .notification-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .logo-text {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        /* ========== MAP STYLES ========== */
        #liveMap, #viewMap {
            height: 350px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            z-index: 1;
        }
        
        .location-controls {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            margin-top: 10px;
        }
        
        .pulse-ring {
            position: absolute;
            width: 40px;
            height: 40px;
            border: 3px solid #3498db;
            border-radius: 50%;
            animation: pulsate 1.5s ease-out infinite;
            opacity: 0;
            top: -20px;
            left: -20px;
        }
        
        @keyframes pulsate {
            0% { transform: scale(0.1, 0.1); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: scale(1.2, 1.2); opacity: 0; }
        }
        
        .accuracy-circle {
            stroke-dasharray: 10, 10;
            animation: dash 20s linear infinite;
        }
        
        @keyframes dash {
            to { stroke-dashoffset: 1000; }
        }
        
        .location-loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .location-loading.active {
            display: block;
        }
        
        .accuracy-indicator {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        
        .accuracy-high { background: #d4edda; color: #155724; }
        .accuracy-medium { background: #fff3cd; color: #856404; }
        .accuracy-low { background: #f8d7da; color: #721c24; }
        
        .email-status {
            font-size: 0.85rem;
            padding: 2px 8px;
            border-radius: 3px;
        }
        
        .email-sent {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .email-failed {
            background-color: #f8d7da;
            color: #842029;
        }
        
        .location-help {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .view-mode .form-control-plaintext {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            min-height: 38px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            #liveMap, #viewMap {
                height: 250px;
            }
        }
        
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard_mahasiswa.php">
                <i class="bi bi-shield-check"></i>
                <span class="logo-text">Sistem Keamanan Kampus</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard_mahasiswa.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="laporan_mahasiswa.php">
                            <i class="bi bi-file-text"></i> Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analitik.php">
                            <i class="bi bi-bar-chart"></i> Analitik
                        </a>
                    </li>
                </ul>
                
                <div class="quick-menu">
                    <div class="nav-item">
                        <a class="notification-btn" href="notifikasi.php">
                            <i class="bi bi-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="dropdown">
                        <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                            <span><?= htmlspecialchars($user['name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Akun Mahasiswa</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php" style="color: #e74c3c;"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h2><i class="bi bi-speedometer2"></i> Dashboard Mahasiswa</h2>
                <p class="text-muted">Halo, <?= $user['name'] ?> 👋 Selamat datang di Sistem Pelaporan Keamanan Kampus.</p>
            </div>
            <div class="card-body m-2">
                <a href="laporan_mahasiswa.php?action=create" class="btn btn-primary">
                    <small><i class="bi bi-plus-circle"></i> Buat Laporan </small>
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <a href="laporan_mahasiswa.php" class="text-decoration-none">
                    <div class="card text-white bg-primary stat-card">
                        <div class="card-body">
                            <h6 class="card-title">Laporan</h6>
                            <h2><?= $totalLaporans ?></h2>
                            <div>Lihat Laporan →</div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="laporan_mahasiswa.php?status=Menunggu" class="text-decoration-none">
                    <div class="card text-white bg-warning stat-card">
                        <div class="card-body">
                            <h6 class="card-title">Menunggu Diproses</h6>
                            <h2><?= $pendingCount ?></h2>
                            <div>Lihat Status Laporan →</div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="laporan_mahasiswa.php?status=Selesai" class="text-decoration-none">
                    <div class="card text-white bg-success stat-card">
                        <div class="card-body">
                            <h6 class="card-title">Selesai</h6>
                            <h2><?= $resolvedCount ?></h2>
                            <div>Lihat laporan selesai →</div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="analitik.php" class="text-decoration-none">
                    <div class="card text-white bg-info stat-card">
                        <div class="card-body">
                            <h6 class="card-title">Analitik</h6>
                            <h2><i class="bi bi-graph-up"></i></h2>
                            <div>Lihat statistik →</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Laporan Terbaru Saya</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentLaporans)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                                <h5 class="mt-3">Belum ada laporan</h5>
                                <p class="text-muted">Mulai dengan membuat laporan pertama Anda</p>
                                <a href="laporan.php?action=create" class="btn btn-primary mt-2">
                                    <i class="bi bi-plus-circle"></i> Buat Laporan Pertama
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive"">
                                <table class="table table-sm mb-1">
                                    <thead class="table-light bg-light stick">
                                        <tr>
                                            <th width="50">#</th>
                                            <th width="200">Judul</th>
                                            <th width="120">Kategori</th>
                                            <th width="200">Lokasi</th>
                                            <th width="100">Status</th>
                                            <th width="130">Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentLaporans as $index => $laporan): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <a href="laporan_detail.php?id=<?= $laporan['id'] ?>" class="text-decoration-none">
                                                    <strong><?= htmlspecialchars($laporan['title']) ?></strong>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($laporan['kategori_name']) ?></td>
                                            <td>
                                                <small><?= htmlspecialchars($laporan['location_address']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getStatusBadge($laporan['status']) ?>">
                                                    <?= getStatusText($laporan['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatDate($laporan['created_at']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>