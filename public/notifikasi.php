<?php

require_once '../config/constants.php';
require_once '../lib/auth.php';
require_once '../lib/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->connect();

$auth = new Auth();
$user = $auth->getCurrentUser();

$message = '';
$error = '';

if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $stmt = $db->prepare("UPDATE notifikasi SET status = 'read' WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$notif_id, $user['id']])) {
        $message = 'Notifikasi ditandai sebagai dibaca';
        header('Location: notifikasi.php?success=read');
        exit;
    } else {
        $error = 'Gagal menandai notifikasi';
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifikasi SET status = 'read' WHERE user_id = ? AND status = 'unread'");
    if ($stmt->execute([$user['id']])) {
        $message = 'Semua notifikasi ditandai sebagai dibaca';
        header('Location: notifikasi.php?success=all_read');
        exit;
    } else {
        $error = 'Gagal menandai semua notifikasi';
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'read') {
        $message = 'Notifikasi ditandai sebagai dibaca';
    } elseif ($_GET['success'] === 'all_read') {
        $message = 'Semua notifikasi ditandai sebagai dibaca';
    }
}

$stmt = $db->prepare("
    SELECT n.*, l.title as laporan_title 
    FROM notifikasi n
    LEFT JOIN laporan l ON n.laporan_id = l.id
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC
    LIMIT 100
");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifikasi WHERE user_id = ? AND status = 'unread'");
$stmt->execute([$user['id']]);
$unread_count = $stmt->fetch()['count'];

$total_count = count($notifications);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - <?= SITE_NAME ?></title>
    <?php include 'header.php'; ?>
    <link rel="stylesheet" href="style.css">
    <style>
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
                        <a class="nav-link" href="analitik.php">
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
                    <div class="nav-item">
                        <a class="nav-link active" href="notifikasi.php">
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
                            <li><h6 class="dropdown-header">Akun <?= $user['role'] === ROLE_ADMIN ? 'Admin' : 'Mahasiswa' ?></h6></li>
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
            <div>
                <h2><i class="bi bi-bell"></i> Notifikasi</h2>
                <p class="text-muted mb-0">
                    <?php if ($unread_count > 0): ?>
                        <span class="text-primary">Anda memiliki <?= $unread_count ?> notifikasi belum dibaca</span>
                    <?php else: ?>
                        <span class="text-success">Semua notifikasi telah dibaca</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <?php if ($unread_count > 0): ?>
                    <form method="POST" class="d-inline me-2" id="markAllForm">
                        <button type="submit" name="mark_all_read" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-all"></i> Tandai Semua Dibaca
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= $user['role'] === ROLE_ADMIN ? 'dashboard_admin.php' : 'dashboard_mahasiswa.php' ?>" 
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Notifikasi</h5>
                    <div>
                        <span class="badge bg-primary me-2"><?= $total_count ?> total</span>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger"><?= $unread_count ?> belum dibaca</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">Belum ada notifikasi</h5>
                        <p class="text-muted">Notifikasi akan muncul di sini ketika ada aktivitas baru</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notif): ?>
                            <?php 
                            $icon = 'bi-bell';
                            $icon_color = 'text-primary';
                            
                            if (strpos(strtolower($notif['title']), 'laporan') !== false) {
                                $icon = 'bi-clipboard';
                                $icon_color = 'text-success';
                            } elseif (strpos(strtolower($notif['title']), 'status') !== false) {
                                $icon = 'bi-info-circle';
                                $icon_color = 'text-info';
                            } elseif (strpos(strtolower($notif['title']), 'komentar') !== false) {
                                $icon = 'bi-chat-dots';
                                $icon_color = 'text-warning';
                            } elseif (strpos(strtolower($notif['title']), 'selesai') !== false || strpos(strtolower($notif['title']), 'completed') !== false) {
                                $icon = 'bi-check-circle';
                                $icon_color = 'text-success';
                            }
                            ?>
                            
                            <div class="list-group-item notification-item <?= $notif['status'] === 'unread' ? 'notification-unread' : 'notification-read' ?>">
                                <div class="d-flex">
                                    <div class="notification-icon <?= $icon_color ?>">
                                        <i class="bi <?= $icon ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="mb-1">
                                                <?php if ($notif['status'] === 'unread'): ?>
                                                    <span class="badge bg-primary mb-1">BARU</span>
                                                <?php endif; ?>
                                                <strong><?= htmlspecialchars($notif['title']) ?></strong>
                                            </div>
                                            <div class="text-end">
                                                <small class="notification-date d-block">
                                                    <i class="bi bi-clock"></i> <?= formatDate($notif['created_at']) ?>
                                                </small>
                                                <?php if ($notif['status'] === 'unread'): ?>
                                                    <a href="?mark_read=<?= $notif['id'] ?>" 
                                                       class="btn btn-sm btn-outline-primary mt-1"
                                                       title="Tandai sebagai dibaca">
                                                        <i class="bi bi-check"></i> Tandai Dibaca
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary mt-1">
                                                        <i class="bi bi-check"></i> Sudah dibaca
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <p class="mb-2"><?= htmlspecialchars($notif['message']) ?></p>
                                        
                                        <?php if ($notif['laporan_id'] && $notif['laporan_title']): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-light text-dark border">
                                                    <i class="bi bi-link-45deg"></i> 
                                                    Terkait Laporan: <?= htmlspecialchars($notif['laporan_title']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white border-top">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            Notifikasi disimpan selama 30 hari terakhir
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">
                            Menampilkan <?= min($total_count, 100) ?> notifikasi terbaru
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('markAllForm')?.addEventListener('submit', function(e) {
            if (!confirm('Apakah Anda yakin ingin menandai SEMUA notifikasi sebagai dibaca?')) {
                e.preventDefault();
            }
        });

        document.querySelectorAll('a[href*="mark_read="]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Tandai notifikasi ini sebagai dibaca?')) {
                    e.preventDefault();
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const firstUnread = document.querySelector('.notification-unread');
            if (firstUnread) {
                setTimeout(() => {
                    firstUnread.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstUnread.style.boxShadow = '0 0 15px rgba(13, 110, 253, 0.3)';
                    setTimeout(() => {
                        firstUnread.style.boxShadow = '';
                    }, 2000);
                }, 500);
            }
        });
        
        <?php if ($unread_count > 0): ?>
        setTimeout(function() {
            window.location.reload();
        }, 60000); 
        <?php endif; ?>
    </script>
</body>
</html>