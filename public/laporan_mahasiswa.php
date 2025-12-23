<?php
require_once '../config/constants.php';
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../lib/maps.php';
require_once '../lib/EmailNotif.php';
require_once '../lib/notifications.php';
require_once '../models/Laporan.php';
require_once '../models/Kategori.php';

checkLogin();

$database = new Database();
$db = $database->connect();

$auth = new Auth();
$user = $auth->getCurrentUser();

if ($auth->isAdmin()) {
    header('Location: laporan_admin.php');
    exit;
}

$laporanModel = new Laporan($db);
$kategoriModel = new Kategori($db);

$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

$emailNotifier = new EmailNotif($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create'])) {
        $laporanModel->user_id = $user['id'];
        $laporanModel->kategori_id = $_POST['kategori_id'];
        $laporanModel->title = $_POST['title'];
        $laporanModel->description = $_POST['description'];
        $laporanModel->location_address = $_POST['location_address'];
        $laporanModel->status = STATUS_PENDING;
        
        // Gunakan koordinat dari form jika ada, atau geocode dari alamat
        if (!empty($_POST['latitude']) && !empty($_POST['longitude'])) {
            $laporanModel->latitude = $_POST['latitude'];
            $laporanModel->longitude = $_POST['longitude'];
        } else {
            $coords = Maps::geocode($laporanModel->location_address);
            $laporanModel->latitude = $coords['lat'];
            $laporanModel->longitude = $coords['lng'];
        }
        
        if ($laporanModel->create()) {
            $message = 'Laporan berhasil dibuat!';
            
            try {

                SimpleNotification::add(
                    $db,
                    $user['id'],
                    "Laporan Baru Dibuat",
                    "Laporan Anda '{$laporanModel->title}' telah berhasil dibuat dan sedang dalam proses review.",
                    $laporanModel->id
                );

                $stmt = $db->prepare("SELECT id FROM users WHERE role = ?");
                $stmt->execute([ROLE_ADMIN]);
                $admins = $stmt->fetchAll();
        
                foreach ($admins as $admin) {
                    SimpleNotification::add(
                        $db,
                        $admin['id'],
                        "Laporan Baru Masuk",
                        "Laporan baru '{$laporanModel->title}' dari {$user['name']} memerlukan review.",
                        $laporanModel->id
                    );
                }
        
                $message .= ' Notifikasi telah dikirim.';
                
            } catch (Exception $e) {
                error_log("Notification error: " . $e->getMessage());
                $message .= ' (Notifikasi gagal: ' . $e->getMessage() . ')';
            }
            $action = 'list';
        } else {
            $error = 'Gagal membuat laporan';
        }
    }
    elseif (isset($_POST['update'])) {
        $editLaporan = $laporanModel->getById($_POST['id']);
        
        if (!$editLaporan || $editLaporan['user_id'] != $user['id']) {
            $error = 'Anda tidak memiliki izin untuk mengedit laporan ini';
        } else {
            $laporanModel->id = $_POST['id'];
            $laporanModel->kategori_id = $_POST['kategori_id'];
            $laporanModel->title = $_POST['title'];
            $laporanModel->description = $_POST['description'];
            $laporanModel->location_address = $_POST['location_address'];
            $laporanModel->status = $editLaporan['status'];
            
            if (!empty($_POST['latitude']) && !empty($_POST['longitude'])) {
                $laporanModel->latitude = $_POST['latitude'];
                $laporanModel->longitude = $_POST['longitude'];
            }
            
            if ($laporanModel->update()) {
                $message = 'Laporan berhasil diperbarui!';
                $action = 'list';
            } else {
                $error = 'Gagal memperbarui laporan';
            }
        }
    }
    elseif (isset($_POST['delete'])) {
        $report = $laporanModel->getById($_POST['id']);
        if (!$report || $report['user_id'] != $user['id']) {
            $error = 'Anda tidak memiliki izin';
        } else {
            $laporanModel->id = $_POST['id'];
            if ($laporanModel->delete()) {
                $message = 'Laporan berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus laporan';
            }
        }
    }
}

if ($action === 'create' || $action === 'edit' || $action === 'view') {
    $categories = $kategoriModel->getAll();
    if (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
        $editLaporan = $laporanModel->getById($_GET['id']);
        if (!$editLaporan) {
            $error = 'Laporan tidak ditemukan';
            $action = 'list';
        } elseif ($editLaporan['user_id'] != $user['id']) {
            $error = 'Anda tidak memiliki izin';
            $action = 'list';
        }
    }
} else {
    $laporans = $laporanModel->getByUser($user['id']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - <?= SITE_NAME ?></title>
    <?php include 'header.php'; ?>
    <script src="java.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
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
        
        .user-marker, .incident-marker {
            background: none;
            border: none;
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
        
        @media (max-width: 768px) {
            #liveMap, #viewMap {
                height: 250px;
            }
        }

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

        .nav-menu {
            display: flex;
            gap: 20px;
            margin-left: 30px;
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
                        <a class="nav-link" href="dashboard_mahasiswa.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="laporan_mahasiswa.php">
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

    <div class="container mt-4 pt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-clipboard"></i> 
                <?= $action === 'create' ? 'Buat Laporan Insiden' : 
                   ($action === 'edit' ? 'Edit Laporan' : 
                   ($action === 'view' ? 'Detail Laporan' : 'Daftar Laporan')) ?>
            </h2>
            <div>
                <a href="dashboard_mahasiswa.php" 
                   class="btn btn-outline-secondary btn-sm">
                   <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action === 'create' || $action === 'edit' || $action === 'view'): ?>
            <div class="card <?= $action === 'view' ? 'view-mode' : '' ?>">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-<?= 
                            $action === 'create' ? 'plus' : 
                            ($action === 'edit' ? 'pencil' : 'eye')
                        ?>"></i>
                        <?= 
                            $action === 'create' ? 'Formulir Laporan Baru' : 
                            ($action === 'edit' ? 'Edit Laporan' : 'Detail Laporan')
                        ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($action === 'view'): ?>
                        <div id="laporanView">
                    <?php else: ?>
                        <form method="POST" id="laporanForm">
                        <?php if ($action === 'create'): ?>
                            <input type="hidden" name="create" value="1">
                        <?php else: ?>
                            <input type="hidden" name="update" value="1">
                        <?php endif; ?>

                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id" value="<?= $editLaporan['id'] ?>">
                        <?php endif; ?>
                    <?php endif; ?>

                        <!-- Hidden coordinates -->
                        <input type="hidden" name="latitude" id="latitudeInput" value="<?= $editLaporan['latitude'] ?? '' ?>">
                        <input type="hidden" name="longitude" id="longitudeInput" value="<?= $editLaporan['longitude'] ?? '' ?>">
                        <input type="hidden" name="accuracy" id="accuracyInput">

                        <div class="row">
                            <div class="col-md-8">
                                <?php if ($action === 'view'): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Status Laporan</label>
                                        <div class="form-control-plaintext border rounded p-2 bg-light">
                                            <span class="badge bg-<?= getStatusBadge($editLaporan['status'] ?? STATUS_PENDING) ?> fs-6">
                                                <i class="bi bi-<?= 
                                                    ($editLaporan['status'] ?? STATUS_PENDING) == STATUS_PENDING ? 'clock' : 
                                                    (($editLaporan['status'] ?? STATUS_PENDING) == STATUS_PROCESSED ? 'gear' : 'check-circle')
                                                ?>"></i>
                                                <?= getStatusText($editLaporan['status'] ?? STATUS_PENDING) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label">Judul Laporan <?= $action !== 'view' ? '*' : '' ?></label>
                                    <?php if ($action === 'view'): ?>
                                        <div class="form-control-plaintext border rounded p-2 bg-light">
                                            <?= htmlspecialchars($editLaporan['title'] ?? '') ?>
                                        </div>
                                    <?php else: ?>
                                        <input type="text" class="form-control" name="title" 
                                               value="<?= htmlspecialchars($editLaporan['title'] ?? '') ?>" 
                                               placeholder="Contoh: Pencurian Sepeda di Parkiran FT" 
                                               required>
                                    <?php endif; ?>
                                    <?php if ($action !== 'view'): ?>
                                        <div class="form-text">Buat judul yang jelas dan deskriptif</div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Kategori Insiden <?= $action !== 'view' ? '*' : '' ?></label>
                                    <?php if ($action === 'view'): ?>
                                        <div class="form-control-plaintext border rounded p-2 bg-light">
                                            <?= htmlspecialchars($editLaporan['kategori_name'] ?? '-') ?>
                                        </div>
                                    <?php else: ?>
                                        <select class="form-select" name="kategori_id" required>
                                            <option value="">Pilih Kategori</option>
                                            <?php foreach ($categories as $kategori): ?>
                                                <option value="<?= $kategori['id'] ?>" 
                                                    <?= (isset($editLaporan) && $editLaporan['kategori_id'] == $kategori['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($kategori['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Lokasi Pelapor <?= $action !== 'view' ? '*' : '' ?></label>
                                    <div class="input-group mb-2">
                                        <?php if ($action === 'view'): ?>
                                            <div class="form-control-plaintext border rounded p-2 bg-light w-100">
                                                <?= htmlspecialchars($editLaporan['location_address'] ?? '') ?>
                                            </div>
                                        <?php else: ?>
                                            <input type="text" class="form-control" name="location_address" 
                                                   value="<?= htmlspecialchars($editLaporan['location_address'] ?? '') ?>" 
                                                   required
                                                   id="locationInput">
                                            <button type="button" class="btn btn-primary" id="detectLocationBtn">
                                                <i class="bi bi-geo-alt"></i> Deteksi Lokasi
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($action !== 'view'): ?>
                                        <div class="location-help">
                                            <i class="bi bi-info-circle"></i>
                                            Klik tombol "Deteksi Lokasi" untuk mendapatkan lokasi kejadian
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                
                                <div class="mb-3">
                                    <label class="form-label">Catatan Laporan <?= $action !== 'view' ? '*' : '' ?></label>
                                    <?php if ($action === 'view'): ?>
                                        <div class="form-control-plaintext border rounded p-2 bg-light" style="min-height: 120px;">
                                            <?= nl2br(htmlspecialchars($editLaporan['description'] ?? '')) ?>
                                        </div>
                                    <?php else: ?>
                                        <textarea class="form-control" name="description" rows="4" 
                                                placeholder="Contoh: Motor hilang di parkiran FT, Honda Beat hitam plat B 1234 ABC"
                                                required><?= htmlspecialchars($editLaporan['description'] ?? '') ?></textarea>
                                        <small class="text-muted">Tulis singkat: lokasi, barang, kejadian</small>
                                    <?php endif; ?>
                                </div>

                                <?php if ($action === 'view'): ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Pelapor</label>
                                                <div class="form-control-plaintext border rounded p-2 bg-light">
                                                    <?= htmlspecialchars($editLaporan['user_name'] ?? '-') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Tanggal Dibuat</label>
                                                <div class="form-control-plaintext border rounded p-2 bg-light">
                                                    <?= formatDate($editLaporan['created_at'] ?? '', 'd/m/Y H:i') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <?php if ($action !== 'view'): ?>
                                    <div class="card mb-3">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="bi bi-map"></i> Peta Lokasi</h6>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" id="zoomInBtn" class="btn btn-outline-secondary">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                                <button type="button" id="zoomOutBtn" class="btn btn-outline-secondary">
                                                    <i class="bi bi-dash"></i>
                                                </button>
                                                <button type="button" id="locateBtn" class="btn btn-outline-primary">
                                                    <i class="bi bi-crosshair"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="location-loading" id="mapLoading">
                                                <div class="text-center py-5">
                                                    <div class="spinner-border text-primary mb-3" role="status"></div>
                                                    <p>Memuat peta...</p>
                                                </div>
                                            </div>
                                            
                                            <div id="liveMap" 
                                                 data-campus-lat="<?= CAMPUS_LAT ?>" 
                                                 data-campus-lng="<?= CAMPUS_LNG ?>"
                                                 data-edit-lat="<?= isset($editLaporan['latitude']) ? $editLaporan['latitude'] : '' ?>" 
                                                 data-edit-lng="<?= isset($editLaporan['longitude']) ? $editLaporan['longitude'] : '' ?>">
                                            </div>
                                            
                                            <div class="p-3 border-top">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">
                                                            <i class="bi bi-info-circle"></i> 
                                                            <span id="locationStatus">
                                                                <?php if ($action === 'edit' && isset($editLaporan['latitude'])): ?>
                                                                    Lokasi terdeteksi
                                                                <?php else: ?>
                                                                    Tekan tombol "Deteksi Lokasi" untuk menentukan lokasi
                                                                <?php endif; ?>
                                                            </span>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-6 text-end">
                                                        <small>Koordinat: <span id="coordinatesInfo" class="badge bg-secondary">
                                                            <?php if ($action === 'edit' && isset($editLaporan['latitude'])): ?>
                                                                <?= round($editLaporan['latitude'], 4) ?>, <?= round($editLaporan['longitude'], 4) ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </span></small>
                                                        <br>
                                                        <small>Akurasi: <span id="accuracyInfo" class="badge bg-info">
                                                            <?php if ($action === 'edit' && isset($editLaporan['latitude'])): ?>
                                                                Terdeteksi
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </span></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="location-controls mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-light text-dark">
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                    Pastikan lokasi tepat di area kejadian
                                                </span>
                                            </div>
                                            <div>
                                                <button type="button" id="clearLocationBtn" class="btn btn-sm btn-outline-danger" style="display: none;">
                                                    <i class="bi bi-x-circle"></i> Hapus Lokasi
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bi bi-map"></i> Peta Lokasi</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <?php if (isset($editLaporan['latitude']) && isset($editLaporan['longitude'])): ?>
                                                <div class="location-loading" id="viewMapLoading">
                                                    <div class="text-center py-5">
                                                        <div class="spinner-border text-primary mb-3" role="status"></div>
                                                        <p>Memuat peta...</p>
                                                    </div>
                                                </div>
                                                
                                                <div id="viewMap" 
                                                    data-lat="<?= !empty($editLaporan['latitude']) ? floatval($editLaporan['latitude']) : '' ?>" 
                                                    data-lng="<?= !empty($editLaporan['longitude']) ? floatval($editLaporan['longitude']) : '' ?>" 
                                                    data-title="<?= htmlspecialchars($editLaporan['title'] ?? 'Laporan') ?>">
                                                </div>
                                                
                                                <div class="p-3 border-top">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <small class="text-muted">
                                                                <i class="bi bi-info-circle"></i> 
                                                                <span>Lokasi Pelapor</span>
                                                            </small>
                                                        </div>
                                                        <div class="col-md-6 text-end">
                                                            <small>Koordinat: <span class="badge bg-secondary">
                                                                <?= round($editLaporan['latitude'], 4) ?>, <?= round($editLaporan['longitude'], 4) ?>
                                                            </span></small>
                                                            <br>
                                                            <small>Alamat: <span class="badge bg-info">
                                                                <?= htmlspecialchars($editLaporan['location_address'] ?? '') ?>
                                                            </span></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center py-5">
                                                    <i class="bi bi-map text-muted display-6"></i>
                                                    <p class="text-muted mt-2">Lokasi tidak tersedia</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                <?php if (EMAIL_ENABLED): ?>
                                    <span class="email-status email-sent">
                                        <i class="bi bi-check-circle"></i> Email Notifikasi Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="email-status email-failed">
                                        <i class="bi bi-x-circle"></i> Email Notifikasi Nonaktif
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($action === 'view'): ?>
                                    <a href="laporan_mahasiswa.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                                    </a>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-<?= $action === 'create' ? 'send' : 'save' ?>"></i>
                                        <?= $action === 'create' ? 'Kirim Laporan' : 'Simpan Perubahan' ?>
                                    </button>
                                    
                                    <a href="laporan_mahasiswa.php" class="btn btn-secondary ms-2">
                                        <i class="bi bi-x"></i> Batal
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php if ($action === 'view'): ?>
                        </div> 
                    <?php else: ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-list-check"></i> Daftar Laporan Saya</h5>
                        <small class="text-muted">Total: <?= count($laporans) ?> laporan</small>
                    </div>
                    <div>
                        <?php if (EMAIL_ENABLED): ?>
                            <span class="badge bg-info me-2">
                                <i class="bi bi-envelope"></i> Email Aktif
                            </span>
                        <?php endif; ?>
                        <a href="?action=create" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Buat Laporan Baru
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($laporans)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-x display-1 text-muted mb-3"></i>
                            <h4 class="text-muted">Belum ada laporan</h4>
                            <p class="text-muted mb-4">
                                Mulai dengan membuat laporan insiden pertama Anda
                            </p>
                            <a href="?action=create" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-circle"></i> Buat Laporan Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped table-mahasiswa">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Judul</th>
                                        <th>Kategori</th>
                                        <th>Lokasi</th>
                                        <th width="150">Status</th>
                                        <th width="120">Tanggal</th>
                                        <th width="100">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($laporans as $index => $laporan): ?>
                                    <tr>
                                        <td class="text-muted"><?= $index + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($laporan['title']) ?></strong>
                                            <br><small class="text-muted">ID: #<?= $laporan['id'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($laporan['kategori_name'] ?? '-') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars($laporan['location_address']) ?></small>
                                            <?php if ($laporan['latitude'] && $laporan['longitude']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-geo-alt"></i> 
                                                    <?= round($laporan['latitude'], 4) ?>, <?= round($laporan['longitude'], 4) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Status View (Mahasiswa) -->
                                            <span class="badge bg-<?= getStatusBadge($laporan['status']) ?>">
                                                <i class="bi bi-<?= 
                                                    $laporan['status'] == STATUS_PENDING ? 'clock' : 
                                                    ($laporan['status'] == STATUS_PROCESSED ? 'gear' : 'check-circle')
                                                ?>"></i>
                                                <?= getStatusText($laporan['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?= formatDate($laporan['created_at'], 'd/m/Y') ?></small>
                                            <br>
                                            <small class="text-muted"><?= formatDate($laporan['created_at'], 'H:i') ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1"> 
                                                <a href="laporan_mahasiswa.php?action=view&id=<?= $laporan['id'] ?>" 
                                                class="btn btn-outline-info btn-sm" 
                                                title="Lihat Detail">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                
                                                <a href="laporan_mahasiswa.php?action=edit&id=<?= $laporan['id'] ?>" 
                                                class="btn btn-outline-primary btn-sm" 
                                                title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-sm" 
                                                        title="Hapus"
                                                        onclick="confirmDelete(<?= $laporan['id'] ?>, '<?= htmlspecialchars(addslashes($laporan['title'])) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Delete -->
        <div class="modal fade" id="deleteModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Konfirmasi Hapus</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="deleteMessage"></p>
                    </div>
                    <div class="modal-footer">
                        <form method="POST" id="deleteForm">
                            <input type="hidden" name="delete" value="1">
                            <input type="hidden" name="id" id="deleteId">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($action === 'create' || $action === 'edit' || $action === 'view'): ?>
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            
            <script>
                window.MAPBOX_TOKEN = '<?= MAPBOX_TOKEN ?>';
                window.MAPBOX_STYLE = '<?= MAPBOX_STYLE ?>';
            </script>
            
            <script src="java.js"></script>
        <?php endif; ?>

        <script>
            function confirmDelete(id, title) {
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                const message = document.getElementById('deleteMessage');
                const deleteId = document.getElementById('deleteId');
                
                message.innerHTML = `Hapus laporan <strong>"${title}"</strong>?<br><small class="text-danger">Tindakan ini tidak dapat dibatalkan!</small>`;
                deleteId.value = id;
                modal.show();
            }
            
            document.getElementById('laporanForm')?.addEventListener('submit', function(e) {
                const location = document.getElementById('locationInput')?.value;
                if (!location || location.trim().length < 5) {
                    e.preventDefault();
                    alert('Lokasi harus diisi dengan detail minimal 5 karakter');
                    return false;
                }
                return true;
            });
            
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        </script>
        
    </body>
</html>