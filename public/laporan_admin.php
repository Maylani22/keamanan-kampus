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

if (!$auth->isAdmin()) {
    if ($auth->isMahasiswa()) {
        header('Location: dashboard_mahasiswa.php');
    } else {
        header('Location: index.php'); 
    }
    exit;
}

$laporanModel = new Laporan($db);
$kategoriModel = new Kategori($db);
$emailNotifier = new EmailNotif($db);

$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        // DELETE LAPORAN
        $report = $laporanModel->getById($_POST['id']);
        if (!$report) {
            $error = 'Laporan tidak ditemukan';
        } else {
            $laporanModel->id = $_POST['id'];
            if ($laporanModel->delete()) {
                $message = 'Laporan berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus laporan';
            }
        }
    }
    elseif (isset($_POST['update_status'])) {
        $reportId = $_POST['id'];
        $newStatus = $_POST['status'];
        
        $report = $laporanModel->getById($reportId);
        if ($report) {
            $oldStatus = $report['status'];
            
            $stmt = $db->prepare("UPDATE laporan SET status = ? WHERE id = ?");
            if ($stmt->execute([$newStatus, $reportId])) {
                $message = 'Status berhasil diperbarui!';
                
                if ($oldStatus !== $newStatus) {
                    try {
                        $stmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                        $stmt->execute([$report['user_id']]);
                        $reportUser = $stmt->fetch();
                        
                        if ($reportUser) {
                            // KIRIM EMAIL KE MAHASISWA
                            $result = $emailNotifier->sendReportStatusChange(
                                $reportUser['email'],
                                $reportUser['name'],
                                $reportId,
                                $report['title'],
                                $oldStatus,
                                $newStatus
                            );
                            // NOTIFIKASI UNTUK MAHASISWA
                            SimpleNotification::add(
                                $db,
                                $report['user_id'],
                                "Status Laporan Diperbarui",
                                "Status laporan '{$report['title']}' telah diubah dari " . 
                                getStatusText($oldStatus) . " menjadi " . getStatusText($newStatus),
                                $reportId
                            );
                            if ($result) {
                                $message .= ' Notifikasi telah dikirim ke pelapor.';
                            } else {
                                $message .= ' (Email notification gagal)';
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Quick status email error: " . $e->getMessage());
                        $message .= ' (Email notification gagal: ' . $e->getMessage() . ')';
                    }
                }
            }
        }
    }
}

// DATA
if ($action === 'view') {
    if (isset($_GET['id'])) {
        $laporan = $laporanModel->getById($_GET['id']);
        if (!$laporan) {
            $error = 'Laporan tidak ditemukan';
            $action = 'list';
        }
    }
} else {
    $laporans = $laporanModel->getAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - <?= SITE_NAME ?></title>
    <?php include 'header.php'; ?>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .status-select {
            min-width: 120px;
            transition: all 0.3s;
        }
        .status-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .table-admin tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        /* Styling untuk peta di view */
        #viewMap {
            height: 300px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }
        
        .map-container {
            position: relative;
        }
        
        .map-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 5px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .location-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            z-index: 999;
            display: none;
        }
        
        .location-loading.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            #viewMap {
                height: 250px;
            }
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard_admin.php"> <!-- PERBAIKAN: dashboard_admin.php -->
                <i class="bi bi-shield-check"></i>
                <span class="logo-text">Sistem Keamanan Kampus</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard_admin.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="laporan_admin.php"> <!-- PERBAIKAN: laporan_admin.php -->
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
                    
                    <div class="dropdown">
                        <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                            <span><?= htmlspecialchars($user['name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Akun Admin</h6></li> <!-- PERBAIKAN: Akun Admin -->
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php" style="color: #e74c3c;"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-clipboard"></i> 
                <?= $action === 'view' ? 'Detail Laporan' : 'Daftar Laporan' ?>
            </h2>
            <div>
                <a href="dashboard_admin.php" class="btn btn-outline-secondary btn-sm">
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

        <?php if ($action === 'view'): ?>
            <div class="card view-mode">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-eye"></i> Detail Laporan #<?= $laporan['id'] ?>
                        <span class="badge bg-info ms-2">Mode View</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div id="laporanView">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Status Laporan</label>
                                    <div class="form-control-plaintext border rounded p-2 bg-light">
                                        <span class="badge bg-<?= getStatusBadge($laporan['status'] ?? STATUS_PENDING) ?> fs-6">
                                            <i class="bi bi-<?= 
                                                ($laporan['status'] ?? STATUS_PENDING) == STATUS_PENDING ? 'clock' : 
                                                (($laporan['status'] ?? STATUS_PENDING) == STATUS_PROCESSED ? 'gear' : 'check-circle')
                                            ?>"></i>
                                            <?= getStatusText($laporan['status'] ?? STATUS_PENDING) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Form Input-->
                                <div class="mb-3">
                                    <label class="form-label">Judul Laporan</label>
                                    <div class="form-control-plaintext border rounded p-2 bg-light">
                                        <?= htmlspecialchars($laporan['title'] ?? '') ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Kategori Insiden</label>
                                    <div class="form-control-plaintext border rounded p-2 bg-light">
                                        <?= htmlspecialchars($laporan['kategori_name'] ?? '-') ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Lokasi Kejadian</label>
                                    <div class="form-control-plaintext border rounded p-2 bg-light w-100">
                                        <?= htmlspecialchars($laporan['location_address'] ?? '') ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Deskripsi Lengkap</label>
                                    <div class="form-control-plaintext border rounded p-2 bg-light" style="min-height: 120px;">
                                        <?= nl2br(htmlspecialchars($laporan['description'] ?? '')) ?>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Pelapor</label>
                                            <div class="form-control-plaintext border rounded p-2 bg-light">
                                                <?= htmlspecialchars($laporan['user_name'] ?? '-') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tanggal Dibuat</label>
                                            <div class="form-control-plaintext border rounded p-2 bg-light">
                                                <?= formatDate($laporan['created_at'] ?? '', 'd/m/Y H:i') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Peta -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-map"></i> Peta Lokasi</h6>
                                    </div>
                                    <div class="card-body p-0 map-container">
                                        <!-- Loading State -->
                                        <div class="location-loading active" id="viewMapLoading">
                                            <div class="text-center py-5">
                                                <div class="spinner-border text-primary mb-3" role="status"></div>
                                                <p>Memuat peta...</p>
                                            </div>
                                        </div>
                                        
                                        <!-- PERBAIKAN: Gunakan data dari $laporan, bukan $editLaporan -->
                                        <div id="viewMap" 
                                            data-lat="<?= !empty($laporan['latitude']) ? floatval($laporan['latitude']) : '' ?>" 
                                            data-lng="<?= !empty($laporan['longitude']) ? floatval($laporan['longitude']) : '' ?>" 
                                            data-title="<?= htmlspecialchars($laporan['title'] ?? 'Laporan') ?>">
                                        </div>
                                        <div class="map-controls">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" id="zoomInBtn" class="btn btn-outline-secondary">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                                <button type="button" id="zoomOutBtn" class="btn btn-outline-secondary">
                                                    <i class="bi bi-dash"></i>
                                                </button>
                                                <button type="button" id="centerBtn" class="btn btn-outline-primary">
                                                    <i class="bi bi-crosshair"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <small>
                                            <strong>Alamat:</strong> <?= htmlspecialchars($laporan['location_address'] ?? '') ?><br>
                                            <?php if ($laporan['latitude'] && $laporan['longitude']): ?>
                                                <strong>Koordinat:</strong> 
                                                <span class="badge bg-secondary">
                                                    <?= round($laporan['latitude'], 6) ?>, <?= round($laporan['longitude'], 6) ?>
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-person"></i> Informasi Pelapor</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-1"><strong>Nama:</strong> <?= htmlspecialchars($laporan['user_name'] ?? '-') ?></p>
                                        <?php 
                                        $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
                                        $stmt->execute([$laporan['user_id']]);
                                        $userData = $stmt->fetch();
                                        if ($userData):
                                        ?>
                                            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($userData['email'] ?? '-') ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                <?php if (EMAIL_ENABLED): ?>
                                    <span class="badge bg-info">
                                        <i class="bi bi-envelope-check"></i> Email Notifikasi Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i class="bi bi-envelope-x"></i> Email Notifikasi Nonaktif
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="laporan_admin.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- List/Tabel Laporan -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-list-check"></i> Daftar Laporan</h5>
                        <small class="text-muted">Total: <?= count($laporans) ?> laporan</small>
                    </div>
                    <div>
                        <?php if (EMAIL_ENABLED): ?>
                            <span class="badge bg-info me-2">
                                <i class="bi bi-envelope"></i> Email Aktif
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($laporans)): ?>
                        <!-- Empty State Message -->
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-x display-1 text-muted mb-3"></i>
                            <h4 class="text-muted">Belum ada laporan</h4>
                            <p class="text-muted mb-4">
                                Belum ada laporan yang dibuat oleh mahasiswa
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped table-admin">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Judul</th>
                                        <th>Pelapor</th>
                                        <th>Email</th>
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
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($laporan['user_name'] ?? '-') ?>
                                            <?php if ($laporan['user_id'] == $user['id']): ?>
                                                <span class="badge bg-secondary">Anda</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php 
                                                $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
                                                $stmt->execute([$laporan['user_id']]);
                                                $userData = $stmt->fetch();
                                                echo $userData ? htmlspecialchars($userData['email']) : '-';
                                                ?>
                                            </small>
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
                                            <!-- Quick Status Update (Admin) di tabel -->
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="id" value="<?= $laporan['id'] ?>">
                                                <select name="status" 
                                                        class="form-select form-select-sm status-select" 
                                                        data-id="<?= $laporan['id'] ?>"
                                                        onchange="updateStatus(this)">
                                                    <option value="<?= STATUS_PENDING ?>" 
                                                        <?= $laporan['status'] == STATUS_PENDING ? 'selected' : '' ?>
                                                        data-class="warning">
                                                        Menunggu
                                                    </option>
                                                    <option value="<?= STATUS_PROCESSED ?>" 
                                                        <?= $laporan['status'] == STATUS_PROCESSED ? 'selected' : '' ?>
                                                        data-class="info">
                                                        Diproses
                                                    </option>
                                                    <option value="<?= STATUS_RESOLVED ?>" 
                                                        <?= $laporan['status'] == STATUS_RESOLVED ? 'selected' : '' ?>
                                                        data-class="success">
                                                        Selesai
                                                    </option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <small><?= formatDate($laporan['created_at'], 'd/m/Y') ?></small>
                                            <br>
                                            <small class="text-muted"><?= formatDate($laporan['created_at'], 'H:i') ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1"> 
                                                <a href="laporan_admin.php?action=view&id=<?= $laporan['id'] ?>" 
                                                class="btn btn-outline-info btn-sm" 
                                                title="Lihat Detail">
                                                    <i class="bi bi-eye"></i>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteMessage"></p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Perhatian Admin:</strong> Penghapusan laporan akan mengirim notifikasi ke pelapor.
                    </div>
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

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        window.MAPBOX_TOKEN = '<?= MAPBOX_TOKEN ?>';
        window.MAPBOX_STYLE = '<?= MAPBOX_STYLE ?>';
    </script>
    <script src="java.js"></script>

    <script>
        function confirmDelete(id, title) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const message = document.getElementById('deleteMessage');
            const deleteId = document.getElementById('deleteId');
            
            message.innerHTML = `Hapus laporan <strong>"${title}"</strong>?<br><small class="text-danger">Tindakan ini tidak dapat dibatalkan!</small>`;
            deleteId.value = id;
            modal.show();
        }
        
        function updateStatus(select) {
            if (confirm('Update status laporan ini? Notifikasi email akan dikirim ke pelapor.')) {
                select.form.submit();
            } else {
                select.value = select.dataset.original;
            }
        }
        
        document.querySelectorAll('.status-select').forEach(select => {
            select.dataset.original = select.value;
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