<?php

require_once '../config/constants.php';

$error = '';
$success = '';
$email = $_SESSION['reset_email'] ?? '';

if (empty($email) || !isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header('Location: login.php');
    exit;
}

function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Password dan konfirmasi password harus diisi';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirm_password) {
        $error = 'Password tidak cocok';
    } else {
        try {
            $pdo = getDBConnection();
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $result = $updateStmt->execute([$hashed_password, $email]);
            
            if ($result) {
                $success = 'Password berhasil direset! Silakan login dengan password baru.';
                
                unset(
                    $_SESSION['reset_otp'],
                    $_SESSION['reset_email'], 
                    $_SESSION['reset_otp_expires'], // DIUBAH: reset_otp_expires bukan reset_expires
                    $_SESSION['otp_verified']
                );
                
                // Juga hapus rate limiting untuk email ini
                if (isset($_SESSION['reset_attempts'])) {
                    $attempts = $_SESSION['reset_attempts'];
                    foreach ($attempts as $key => $attempt) {
                        if ($attempt['email'] === $email) {
                            unset($attempts[$key]);
                        }
                    }
                    $_SESSION['reset_attempts'] = $attempts;
                }
            } else {
                $error = 'Gagal mereset password. Silakan coba lagi.';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= SITE_NAME ?></title>
    <?php include 'header.php'; ?>
    <style>
        body {
            background: linear-gradient(135deg, #d58dbdff 0%, #368b7fff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 100%;
        }
        .reset-header {
            background: linear-gradient(135deg, #d58dbdff 0%, #368b7fff 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            text-align: center;
        }
        .reset-body {
            padding: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #d58dbdff 0%, #368b7fff 100%);
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="reset-header">
                        <h3 class="mb-1"><i class="bi bi-shield-lock"></i> Reset Password</h3>
                        <small><?= SITE_NAME ?></small>
                    </div>
                    <div class="reset-body">
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle me-2"></i>
                            <?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            <div class="mt-3 text-center">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-2"></i> Login Sekarang
                                </a>
                            </div>
                        </div>
                        <?php else: ?>
                        
                        <p class="text-muted mb-4">
                            Masukkan password baru untuk akun: <strong><?= htmlspecialchars($email) ?></strong>
                        </p>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Password Baru</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" name="password" 
                                           placeholder="Minimal 6 karakter" required minlength="6">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Konfirmasi Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           placeholder="Ulangi password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                                <a href="login.php" class="btn btn-outline-secondary">Kembali ke Login</a>
                            </div>
                        </form>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>