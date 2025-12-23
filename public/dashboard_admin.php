<?php
require_once '../config/constants.php';
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../models/Laporan.php';
require_once '../models/Kategori.php';

checkLogin();
checkAdmin();

$database = new Database();
$db = $database->connect();

$auth = new Auth();
$user = $auth->getCurrentUser();

$laporanModel = new Laporan($db);
$kategoriModel = new Kategori($db);

$stats = $laporanModel->getStats();
$categories = $kategoriModel->getAll();
$recentLaporans = array_slice($stats['recent'], 0, 5);

$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifikasi WHERE user_id = ? AND status = 'unread'");
$stmt->execute([$user['id']]);
$unread_count = $stmt->fetch()['count'];

$pendingCount = 0;
foreach ($stats['by_status'] as $status) {
    if ($status['status'] === STATUS_PENDING) {
        $pendingCount = $status['count'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - <?= SITE_NAME ?></title>
    <?php include 'header.php'; ?>
    <link rel="stylesheet" href="style.css">
    <style>
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
                    <?php if ($user['role'] === ROLE_ADMIN): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="kategori.php">
                            <i class="bi bi-tags"></i> Kategori
                        </a>
                    </li>
                    <?php endif; ?>
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
        <div class="row mb-4">
            <div class="col">
                <h2><i class="bi bi-speedometer2"></i> Dashboard Admin</h2>
                <p class="text-muted">Selamat datang, <?= $user['name'] ?></p>
            </div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h6 class="card-title"> Laporan</h6>
                        <h2><?= $stats['total'] ?></h2>
                        <a href="laporan_admin.php" class="text-white">Lihat semua →</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h6 class="card-title">Menunggu Diproses</h6>
                        <h2><?= $pendingCount ?></h2>
                        <a href="laporan_admin.php?filter=pending" class="text-white">Tinjau sekarang →</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h6 class="card-title">Kategori</h6>
                        <h2><?= count($categories) ?></h2>
                        <a href="kategori.php" class="text-white">Kelola kategori →</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h6 class="card-title">Analitik</h6>
                        <h2><i class="bi bi-graph-up"></i></h2>
                        <a href="analitik.php" class="text-white">Lihat statistik →</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Laporan Terbaru</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentLaporans)): ?>
                            <p class="text-muted text-center">Belum ada laporan</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light bg-light stick">
                                        <tr>
                                            <th>#</th>
                                            <th>Judul</th>
                                            <th>Lokasi</th>
                                            <th>Status</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentLaporans as $index => $laporan): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($laporan['title']) ?></td>
                                            <td><?= htmlspecialchars($laporan['location_address']) ?></td>
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