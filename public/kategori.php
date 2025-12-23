<?php
require_once '../config/constants.php';
require_once '../lib/auth.php';
require_once '../lib/functions.php';

checkLogin();

$message = '';
$error = '';
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$database = new Database();
$db = $database->connect();

$auth = new Auth();
$user = $auth->getCurrentUser();

if ($user['role'] !== ROLE_ADMIN) {
    header('Location: dashboard_admin.php');
    exit;
}

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_kategori'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = 'Nama kategori harus diisi';
        } else {
            // Cek apakah nama kategori sudah ada
            $stmt = $db->prepare("SELECT id FROM kategori WHERE name = ?");
            $stmt->execute([$name]);
            
            if ($stmt->fetch()) {
                $error = 'Nama kategori sudah ada';
            } else {
                $stmt = $db->prepare("INSERT INTO kategori (name, description) VALUES (?, ?)");
                if ($stmt->execute([$name, $description])) {
                    $_SESSION['success_message'] = 'Kategori berhasil dibuat!';
                    header('Location: kategori.php?action=list');
                    exit;
                } else {
                    $error = 'Gagal membuat kategori';
                }
            }
        }
    }
    elseif (isset($_POST['update_kategori'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = 'Nama kategori harus diisi';
        } else {
            $stmt = $db->prepare("UPDATE kategori SET name = ?, description = ? WHERE id = ?");
            if ($stmt->execute([$name, $description, $id])) {
                $_SESSION['success_message'] = 'Kategori berhasil diperbarui!';
                header('Location: kategori.php?action=list');
                exit;
            } else {
                $error = 'Gagal memperbarui kategori';
            }
        }
    }
    elseif (isset($_POST['delete_kategori'])) {
        $id = $_POST['id'];
        
        // Cek apakah kategori digunakan di laporan
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM laporan WHERE kategori_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $error = 'Kategori tidak dapat dihapus karena masih digunakan dalam ' . $result['count'] . ' laporan';
        } else {
            $stmt = $db->prepare("DELETE FROM kategori WHERE id = ?");
            if ($stmt->execute([$id])) {
                $_SESSION['success_message'] = 'Kategori berhasil dihapus!';
                header('Location: kategori.php?action=list');
                exit;
            } else {
                $error = 'Gagal menghapus kategori';
            }
        }
    }
}

if ($action === 'list') {
    $stmt = $db->query("SELECT k.*, 
                        (SELECT COUNT(*) FROM laporan WHERE kategori_id = k.id) as laporan_count
                        FROM kategori k ORDER BY name ASC");
    $kategories = $stmt->fetchAll();
} elseif ($action === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $db->prepare("SELECT * FROM kategori WHERE id = ?");
    $stmt->execute([$id]);
    $kategori = $stmt->fetch();
    
    if (!$kategori) {
        $_SESSION['error_message'] = 'Kategori tidak ditemukan';
        header('Location: kategori.php?action=list');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - <?= SITE_NAME ?></title>
    <?php include 'header.php'; ?>
    <link rel="stylesheet" href="style.css">
    <style>
        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
                    <?php if ($user['role'] === ROLE_ADMIN): ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="kategori.php">
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
            <h2>
                <i class="bi bi-tags"></i> 
                <?= $action === 'create' ? 'Tambah Kategori' : ($action === 'edit' ? 'Edit Kategori' : 'Kelola Kategori') ?>
            </h2>
            <div>
                <a href="dashboard_admin.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action === 'create' || $action === 'edit'): ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id" value="<?= $kategori['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Kategori *</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?= htmlspecialchars($kategori['name'] ?? '') ?>" 
                                   placeholder="Contoh: Pencurian" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Deskripsi kategori (opsional)"><?= htmlspecialchars($kategori['description'] ?? '') ?></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="<?= $action === 'create' ? 'create_kategori' : 'update_kategori' ?>" 
                                    class="btn btn-primary">
                                <?= $action === 'create' ? 'Simpan Kategori' : 'Perbarui Kategori' ?>
                            </button>
                            <a href="kategori.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <div class="card">
                <div class="card-header d-flex justify-content-end mb-3">
                    <a href="kategori.php?action=create" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Buat Kategori
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($kategories)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-tag fs-1 text-muted"></i>
                            <h5 class="mt-3">Belum ada kategori</h5>
                            <p class="text-muted">Mulai dengan menambahkan kategori pertama</p>
                            <a href="?action=create" class="btn btn-primary">Tambah Kategori</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nama Kategori</th>
                                        <th>Deskripsi</th>
                                        <th>Jumlah Laporan</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kategories as $index => $kat): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($kat['name']) ?></strong>
                                        </td>
                                        <td>
                                            <?= $kat['description'] ? htmlspecialchars($kat['description']) : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = $kat['laporan_count'] > 0 ? 'bg-warning text-dark' : 'bg-primary';
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?= $kat['laporan_count'] ?> laporan</span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= formatDate($kat['created_at']) ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="?action=edit&id=<?= $kat['id'] ?>" 
                                                class="btn btn-outline-warning btn-sm" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                
                                                <?php if ($kat['laporan_count'] > 0): ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-secondary btn-sm" 
                                                            title="Tidak dapat dihapus karena ada <?= $kat['laporan_count'] ?> laporan"
                                                            onclick="showCannotDelete('<?= htmlspecialchars(addslashes($kat['name'])) ?>', <?= $kat['laporan_count'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-danger btn-sm" 
                                                            title="Hapus"
                                                            onclick="confirmDelete(<?= $kat['id'] ?>, '<?= htmlspecialchars(addslashes($kat['name'])) ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
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
                        <input type="hidden" name="delete_kategori" value="1">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cannotDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tidak Dapat Dihapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="cannotDeleteMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id, name) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const message = document.getElementById('deleteMessage');
            const deleteId = document.getElementById('deleteId');
            
            message.innerHTML = `Hapus kategori <strong>${name}</strong>? Tindakan ini tidak dapat dibatalkan.`;
            deleteId.value = id;
            modal.show();
        }
        
        function showCannotDelete(name, count) {
            const modal = new bootstrap.Modal(document.getElementById('cannotDeleteModal'));
            const message = document.getElementById('cannotDeleteMessage');
            
            message.innerHTML = `Kategori <strong>${name}</strong> memiliki ${count} laporan. <br><br>
                               <span class="text-danger">Tidak dapat dihapus karena masih digunakan dalam laporan.</span>`;
            modal.show();
        }
    </script>
</body>
</html>