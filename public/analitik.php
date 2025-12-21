<?php
require_once '../config/constants.php';
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../services/AnalyticsService.php';
require_once '../models/Laporan.php';

checkLogin(); 

$auth = new Auth();
$user = $auth->getCurrentUser();

// Cek role user
$isAdmin = ($user['role'] === 'admin');
$isMahasiswa = ($user['role'] === 'mahasiswa');

$database = new Database();
$db = $database->connect();
$laporanModel = new Laporan($db);

$AnalyticsService = new AnalyticsService($db);
$analyticsData = $AnalyticsService->getAdminAnalytics(12); // 12 bulan data

$monthlyData = $analyticsData['monthly'] ?? [];
$hotspots = $analyticsData['hotspots'] ?? [];
$hourPattern = $analyticsData['hour_pattern'] ?? [];
$dayPattern = $analyticsData['day_pattern'] ?? [];
$correlations = $analyticsData['correlations'] ?? [];

$totalLaporan = $analyticsData['stats']['total'] ?? 0;
$avgPerMonth = $analyticsData['stats']['rata_per_bulan'] ?? 0;

$months = [];
$counts = [];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifikasi WHERE user_id = ? AND status = 'unread'");
$stmt->execute([$user['id']]);
$unread_count = $stmt->fetch()['count'];

foreach (array_reverse($monthlyData) as $data) {
    $months[] = $data['bulan_label'] ?? $data['bulan'];
    $counts[] = $data['jumlah'] ?? $data['count'] ?? 0;
}

// Fungsi konversi jam dari format HHMM (300, 1400) ke 0-23
function convertHourFromHHMM($hourValue) {
    $hourValue = intval($hourValue);
    if ($hourValue >= 100) {
        return floor($hourValue / 100); // 300 -> 3, 1400 -> 14
    }
    return $hourValue;
}

// mapping untuk hotspot data
foreach ($hotspots as &$spot) {
    $spot['location_address'] = $spot['lokasi'] ?? '';
    $spot['kategori'] = $spot['kategori'] ?? '';
    $spot['jumlah'] = $spot['frekuensi'] ?? 0;
    $spot['tingkat_penyelesaian'] = ($spot['persentase_selesai'] ?? 0) / 100;
}

foreach ($hourPattern as &$hour) {
    $hour['jam'] = convertHourFromHHMM($hour['jam'] ?? 0);
    $hour['jumlah'] = $hour['frekuensi'] ?? 0;
}

foreach ($dayPattern as &$day) {
    $day['hari'] = $day['hari'] ?? '';
    $day['jumlah'] = $day['frekuensi'] ?? 0;
}

if (isset($_GET['export']) && $_GET['export'] == 'csv_all') {
    if (!$isAdmin) {
        header('Location: analitik.php?error=unauthorized');
        exit();
    }
    
    // Set header untuk file CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analitik_keamanan_' . date('Y-m-d') . '.csv"');
    
    // Buat output stream
    $output = fopen('php://output', 'w');
    
    // Tambah BOM untuk UTF-8
    fputs($output, "\xEF\xBB\xBF");
    
    // 1. DATA FREKUENSI BULANAN
    fputcsv($output, ['FREKUENSI INSIDEN PER BULAN']);
    fputcsv($output, ['Bulan', 'Tahun', 'Label Bulan', 'Jumlah Insiden']);
    foreach ($monthlyData as $data) {
        fputcsv($output, [
            $data['bulan'] ?? '',
            $data['tahun'] ?? '',
            $data['bulan_label'] ?? '',
            $data['jumlah'] ?? 0
        ]);
    }
    fputcsv($output, []);
    
    // 2. DATA AREA RAWAN (HOTSPOTS)
    fputcsv($output, ['10 AREA RAWAN TERATAS']);
    fputcsv($output, ['Lokasi', 'Kategori', 'Frekuensi', 'Persentase Selesai (%)']);
    foreach ($hotspots as $spot) {
        fputcsv($output, [
            $spot['location_address'] ?? '',
            $spot['kategori'] ?? '',
            $spot['jumlah'] ?? 0,
            round(($spot['tingkat_penyelesaian'] * 100), 1)
        ]);
    }
    fputcsv($output, []);
    
    // 3. DATA POLA JAM
    fputcsv($output, ['POLA KEJADIAN PER JAM']);
    fputcsv($output, ['Jam', 'Frekuensi']);
    foreach ($hourPattern as $hour) {
        fputcsv($output, [
            sprintf("%02d:00", $hour['jam'] ?? 0),
            $hour['jumlah'] ?? 0
        ]);
    }
    fputcsv($output, []);
    
    // 4. DATA POLA HARI
    fputcsv($output, ['POLA KEJADIAN PER HARI']);
    fputcsv($output, ['Hari', 'Frekuensi']);
    foreach ($dayPattern as $day) {
        fputcsv($output, [
            $day['hari'] ?? '',
            $day['jumlah'] ?? 0
        ]);
    }
    fputcsv($output, []);
    
    // 5. DATA POLA KORELASI
    fputcsv($output, ['POLA KORELASI KATEGORI & WAKTU']);
    fputcsv($output, ['Kategori', 'Jam', 'Hari', 'Frekuensi']);
    foreach ($correlations as $pattern) {
        fputcsv($output, [
            $pattern['kategori'] ?? '',
            sprintf("%02d:00", $pattern['jam'] ?? 0),
            $pattern['hari'] ?? '',
            $pattern['frekuensi'] ?? 0
        ]);
    }
    
    fclose($output);
    exit();
}

if (isset($_GET['error']) && $_GET['error'] == 'unauthorized') {
    $errorMessage = "Akses ditolak. Hanya administrator yang dapat meng-export data.";
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analitik Keamanan - <?= SITE_NAME ?></title>
    <?php include 'header.php'; ?>
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding-top: 70px;
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


        .chart-container { 
            height: 280px; 
            position: relative;
        }
        
        .card { 
            border-radius: 10px; 
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding: 0.75rem 1rem;
        }
        
        .card-body {
            padding: 1rem;
            flex: 1;
            overflow: hidden;
        }
        
        .card-footer {
            background-color: #f8f9fa;
            border-top: 1px solid rgba(0,0,0,0.1);
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
        }
        
        .table-responsive { 
            max-height: 320px; 
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        
        .table-sm th, 
        .table-sm td {
            padding: 0.5rem;
            vertical-align: middle;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .badge {
            font-weight: 500;
        }
        
        .progress {
            height: 8px;
            background-color: #e9ecef;
        }
        
        
        .stats-card {
            padding: 0.75rem;
            margin: 0.25rem 0;
            border-radius: 6px;
            background-color: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                height: 250px;
            }
            
            .table-responsive {
                max-height: 250px;
            }
            
            .nav-tabs .nav-link {
                font-size: 0.85rem;
                padding: 0.4rem 0.6rem;
            }
        }
        
        .alert-fixed {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
        }

        .btn-group.btn-group-sm {
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn-group.btn-group-sm .btn {
            border: 1px solid #dee2e6;
            font-size: 0.85rem;
            padding: 0.25rem 0.75rem;
            transition: all 0.2s ease;
        }

        .btn-group.btn-group-sm .btn.active {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-group.btn-group-sm .btn:not(.active):hover {
            background-color: #f8f9fa;
        }

        .btn-group.btn-group-sm .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
                        <a class="nav-link" href="dashboard_mahasiswa.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="laporan_mahasiswa.php">
                            <i class="bi bi-file-text"></i> Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="analitik.php">
                            <i class="bi bi-bar-chart"></i> Analitik
                        </a>
                    </li>
                    
                    <?php if ($user['role'] === ROLE_ADMIN): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="kategori.php">
                            <i class="bi bi-tags"></i> Kategori
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="quick-menu">
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

    <?php if (!empty($errorMessage)): ?>
    <div class="alert-fixed">
        <div class="alert alert-warning alert-dismissible fade show shadow" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>

    <div class="container mt-4">
        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-bar-chart me-2"></i>Analitik Keamanan Kampus
                </h2>
                <p class="text-muted mb-0">
                    Analisis frekuensi insiden dan identifikasi area rawan
                    <span class="badge bg-info ms-2">Total: <?= $totalLaporan ?> laporan</span>
                    <?php if (!$isAdmin): ?>
                    <span class="badge bg-warning ms-2">View Only</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="d-flex gap-2">
                <?php if ($isAdmin): ?>
                <a href="?export=csv_all" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-download me-1"></i>Export Data
                </a>
                <?php endif; ?>
                <a href="<?= $isAdmin ? 'dashboard_admin.php' : 'dashboard_mahasiswa.php' ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
                </a>
            </div>
        </div>

        <!-- ROW 1: GRAFIK FREKUENSI & POLA KORELASI -->
        <div class="row mb-4">
            <!-- GRAFIK FREKUENSI -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-month me-2"></i>Frekuensi Insiden per Bulan
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                        <div class="row mt-3 g-2">
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <small class="text-muted d-block">Rata-rata</small>
                                    <strong><?= $avgPerMonth ?> insiden/bulan</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <small class="text-muted d-block">Tertinggi</small>
                                    <strong><?= !empty($counts) ? max($counts) : 0 ?> insiden</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <small class="text-muted d-block">Terendah</small>
                                    <strong><?= !empty($counts) ? min($counts) : 0 ?> insiden</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- POLA KORELASI -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-diagram-3 text-success me-2"></i>Pola Kategori & Waktu
                        </h5>
                        <span class="badge bg-secondary">Patterns</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">Kategori</th>
                                        <th class="text-center">Jam</th>
                                        <th class="text-center">Hari</th>
                                        <th class="text-center">Frek</th>
                                        <th class="text-center">Risiko</th>
                                        <th class="text-center">Rekomendasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $hariMap = [
                                        'Sunday' => 'Min', 'Monday' => 'Sen', 'Tuesday' => 'Sel',
                                        'Wednesday' => 'Rab', 'Thursday' => 'Kam', 'Friday' => 'Jum',
                                        'Saturday' => 'Sab'
                                    ];
                                    
                                    if (!empty($correlations)):
                                    foreach ($correlations as $pattern): 
                                        $riskLevel = $pattern['frekuensi'] >= 5 ? 'Tinggi' : 
                                                    ($pattern['frekuensi'] >= 3 ? 'Sedang' : 'Rendah');
                                        $riskColor = $pattern['frekuensi'] >= 5 ? 'danger' : 
                                                ($pattern['frekuensi'] >= 3 ? 'warning' : 'success');
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= htmlspecialchars($pattern['kategori'] ?? '') ?></span>
                                        </td>
                                        <td class="text-center fw-bold"><?= sprintf("%02d:00", $pattern['jam'] ?? 0) ?></td>
                                        <td class="text-center"><?= $hariMap[$pattern['hari'] ?? ''] ?? substr($pattern['hari'] ?? '', 0, 3) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary rounded-pill"><?= $pattern['frekuensi'] ?? 0 ?>x</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $riskColor ?>"><?= $riskLevel ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $jam = $pattern['jam'] ?? 0;
                                            $hari = $pattern['hari'] ?? '';
                                            if ($jam >= 20 || $jam <= 6) {
                                                echo '<span class="text-nowrap">🌙 Patroli malam</span>';
                                            } elseif (in_array($hari, ['Friday', 'Saturday'])) {
                                                echo '<span class="text-nowrap">🎯 Akhir pekan</span>';
                                            } elseif ($pattern['frekuensi'] >= 5) {
                                                echo '<span class="text-nowrap">⚠️ Prioritas</span>';
                                            } else {
                                                echo '<span class="text-nowrap">👁️ Monitoring</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; 
                                    else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="bi bi-search text-muted fs-1"></i>
                                            <p class="text-muted mb-0 mt-2">Belum ada pola teridentifikasi</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i> 
                            Pola teridentifikasi jika frekuensi ≥ 2x pada kategori, jam, dan hari yang sama
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 2: AREA RAWAN & POLA WAKTU -->
        <div class="row">
            <!-- AREA RAWAN -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-map-fill text-danger me-2"></i>10 Area Rawan Teratas
                        </h5>
                        <span class="badge bg-danger">Hotspots</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 35%;">Lokasi</th>
                                        <th style="width: 20%;">Kategori</th>
                                        <th style="width: 15%;">Frekuensi</th>
                                        <?php if ($isAdmin): ?>
                                        <th style="width: 25%;">% Selesai</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($hotspots)): ?>
                                    <?php foreach ($hotspots as $index => $spot): ?>
                                    <tr>
                                        <td class="fw-bold"><?= $index + 1 ?></td>
                                        <td>
                                            <small title="<?= htmlspecialchars($spot['location_address']) ?>">
                                                <?= htmlspecialchars(substr($spot['location_address'], 0, 30)) ?>
                                                <?= strlen($spot['location_address']) > 30 ? '...' : '' ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($spot['kategori']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $spot['jumlah'] >= 5 ? 'danger' : ($spot['jumlah'] >= 3 ? 'warning' : 'info') ?>">
                                                <?= $spot['jumlah'] ?>x
                                            </span>
                                        </td>
                                        <?php if ($isAdmin): ?>
                                        <td>
                                            <?php 
                                            $percent = round($spot['tingkat_penyelesaian'] * 100);
                                            $color = $percent >= 70 ? 'success' : ($percent >= 40 ? 'warning' : 'danger');
                                            ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1">
                                                    <div class="progress-bar bg-<?= $color ?>" style="width: <?= $percent ?>%"></div>
                                                </div>
                                                <small class="text-nowrap"><?= $percent ?>%</small>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="<?= $isAdmin ? 5 : 4 ?>" class="text-center py-5">
                                            <i class="bi bi-check-circle text-success fs-1"></i>
                                            <p class="text-muted mt-2">Tidak ada area rawan teridentifikasi</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i> 
                            Area dengan ≥ 2 insiden dikategorikan sebagai rawan
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-5">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history text-primary me-2"></i>Pola Waktu Kejadian
                        </h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" id="showHourChart">
                                <i class="bi bi-clock me-1"></i> Per Jam
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="showDayChart">
                                <i class="bi bi-calendar-week me-1"></i> Per Hari
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- GRAFIK PER JAM (Default Tampil) -->
                        <div id="hourChartContainer">
                            <div class="chart-container">
                                <canvas id="hourChart"></canvas>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-lightbulb me-1"></i> 
                                    <strong>Jam Puncak:</strong> 
                                    <?php 
                                    if (!empty($hourPattern)) {
                                        $maxHour = max(array_column($hourPattern, 'jumlah'));
                                        $peakHours = array_filter($hourPattern, fn($h) => $h['jumlah'] == $maxHour);
                                        $peakHoursFormatted = array_map(function($h) {
                                            return sprintf('%02d:00', $h['jam']);
                                        }, $peakHours);
                                        echo implode(', ', $peakHoursFormatted);
                                    } else {
                                        echo 'Tidak ada data';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- GRAFIK PER HARI (Awalnya Tersembunyi) -->
                        <div id="dayChartContainer" style="display: none;">
                            <div class="chart-container">
                                <canvas id="dayChart"></canvas>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-lightbulb me-1"></i> 
                                    <strong>Hari Rawan:</strong> 
                                    <?php 
                                    if (!empty($dayPattern)) {
                                        $maxDay = max(array_column($dayPattern, 'jumlah'));
                                        $peakDays = array_filter($dayPattern, fn($d) => $d['jumlah'] == $maxDay);
                                        echo implode(', ', array_map(fn($d) => $d['hari'] ?? '', $peakDays));
                                    } else {
                                        echo 'Tidak ada data';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i> 
                            Analisis pola untuk optimalisasi patroli keamanan
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    
    <script>
        // 1. GRAFIK FREKUENSI BULANAN
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Jumlah Insiden',
                    data: <?= json_encode($counts) ?>,
                    backgroundColor: (ctx) => {
                        const value = ctx.dataset.data[ctx.dataIndex];
                        const dataArray = <?= json_encode($counts) ?>;
                        const max = dataArray.length > 0 ? Math.max(...dataArray) : 1;
                        const ratio = max > 0 ? value / max : 0;
                        return ratio > 0.7 ? 'rgba(220, 53, 69, 0.8)' :
                               ratio > 0.4 ? 'rgba(255, 193, 7, 0.8)' :
                               'rgba(25, 135, 84, 0.8)';
                    },
                    borderColor: '#343a40',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Jumlah Insiden' },
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        title: { display: true, text: 'Bulan' }
                    }
                }
            }
        });

        // 2. GRAFIK POLA JAM
        const hourCtx = document.getElementById('hourChart').getContext('2d');
        const hourChart = new Chart(hourCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function($h) { 
                    return sprintf('%02d:00', $h['jam']); 
                }, $hourPattern)) ?>,
                datasets: [{
                    label: 'Frekuensi',
                    data: <?= json_encode(array_column($hourPattern, 'jumlah')) ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.3,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });

        // 3. GRAFIK POLA HARI
        const dayCtx = document.getElementById('dayChart').getContext('2d');
        const dayChart = new Chart(dayCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($dayPattern, 'hari')) ?>,
                datasets: [{
                    label: 'Frekuensi',
                    data: <?= json_encode(array_column($dayPattern, 'jumlah')) ?>,
                    backgroundColor: '#20c997',
                    borderColor: '#198754',
                    borderWidth: 1,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });

        // EXPORT FUNCTION - Hanya untuk admin
        function saveChart() {
            <?php if ($isAdmin): ?>
            const link = document.createElement('a');
            link.download = 'grafik_frekuensi_' + new Date().toISOString().slice(0,10) + '.png';
            link.href = monthlyChart.toBase64Image();
            link.click();
            <?php else: ?>
            alert('Fitur ini hanya tersedia untuk administrator.');
            <?php endif; ?>
        }

        // Initialize tabs dan auto-hide alert
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alert setelah 5 detik
            const alert = document.querySelector('.alert');
            if (alert) {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
        });
    </script>
    // Tambahkan script ini sebelum </body>
    <script>
    // Fungsi untuk switch antara grafik Jam dan Hari
    document.addEventListener('DOMContentLoaded', function() {
        const showHourBtn = document.getElementById('showHourChart');
        const showDayBtn = document.getElementById('showDayChart');
        const hourContainer = document.getElementById('hourChartContainer');
        const dayContainer = document.getElementById('dayChartContainer');
        
        if (showHourBtn && showDayBtn) {
            // Tombol Per Jam
            showHourBtn.addEventListener('click', function() {
                hourContainer.style.display = 'block';
                dayContainer.style.display = 'none';
                showHourBtn.classList.add('active');
                showDayBtn.classList.remove('active');
            });
            
            // Tombol Per Hari
            showDayBtn.addEventListener('click', function() {
                hourContainer.style.display = 'none';
                dayContainer.style.display = 'block';
                showDayBtn.classList.add('active');
                showHourBtn.classList.remove('active');
            });
            
            // Inisialisasi tab pertama aktif
            showHourBtn.classList.add('active');
        }
        
        // Cek apakah ada data untuk grafik hari
        const dayData = <?= json_encode(array_column($dayPattern, 'jumlah')) ?>;
        if (!dayData || dayData.length === 0) {
            // Jika tidak ada data hari, nonaktifkan tombol hari
            if (showDayBtn) {
                showDayBtn.disabled = true;
                showDayBtn.innerHTML = '<i class="bi bi-calendar-week me-1"></i> Per Hari <span class="badge bg-secondary">No Data</span>';
                showDayBtn.title = "Tidak ada data pola hari";
            }
        }
        
        // Cek apakah ada data untuk grafik jam
        const hourData = <?= json_encode(array_column($hourPattern, 'jumlah')) ?>;
        if (!hourData || hourData.length === 0) {
            // Jika tidak ada data jam, nonaktifkan tombol jam
            if (showHourBtn) {
                showHourBtn.disabled = true;
                showHourBtn.innerHTML = '<i class="bi bi-clock me-1"></i> Per Jam <span class="badge bg-secondary">No Data</span>';
                showHourBtn.title = "Tidak ada data pola jam";
            }
        }
    });
</script>
</body>
</html>