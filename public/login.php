<?php

require_once '../config/constants.php';
require_once '../lib/auth.php';
require_once '../lib/EmailService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    if ($user['role'] === ROLE_ADMIN) {
        header('Location: dashboard_admin.php');
    } else {
        header('Location: dashboard_mahasiswa.php');
    }
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

function checkRateLimit($email) {
    if (!isset($_SESSION['reset_attempts'])) {
        $_SESSION['reset_attempts'] = [];
    }
    
    $now = time();
    $attempts = $_SESSION['reset_attempts'];
    
    foreach ($attempts as $key => $attempt) {
        if ($attempt['time'] < $now - 300) {
            unset($attempts[$key]);
        }
    }
    
    $email_attempts = 0;
    foreach ($attempts as $attempt) {
        if ($attempt['email'] === $email) {
            $email_attempts++;
        }
    }
    
    if ($email_attempts >= 3) {
        return false;
    }
    
    $attempts[] = [
        'email' => $email,
        'time' => $now,
        'ip' => $_SERVER['REMOTE_ADDR']
    ];
    
    $_SESSION['reset_attempts'] = $attempts;
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'reset_request') {
        $reset_email = trim($_POST['reset_email'] ?? '');
        
        if (empty($reset_email)) {
            $_SESSION['reset_error'] = 'Email harus diisi';
            header('Location: login.php');
            exit;
        } elseif (!filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['reset_error'] = 'Format email tidak valid';
            header('Location: login.php');
            exit;
        } elseif (!checkRateLimit($reset_email)) {
            $_SESSION['reset_error'] = 'Terlalu banyak percobaan. Silakan coba lagi setelah 5 menit.';
            header('Location: login.php');
            exit;
        } else {
            try {
                $pdo = getDBConnection();
                
                $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ?");
                $stmt->execute([$reset_email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $otp = rand(100000, 999999);
                    $expires = time() + 600; 
                    
                    $_SESSION['reset_otp'] = $otp;
                    $_SESSION['reset_email'] = $reset_email;
                    $_SESSION['reset_otp_expires'] = $expires;
                    $_SESSION['reset_user_name'] = $user['name'];
                    
                    try {
                        $emailService = new EmailService();
                        $emailSent = $emailService->sendOTP($reset_email, $user['name'], $otp, 10);
                        
                        if ($emailSent) {
                            // Tambahkan pesan sukses ke session
                            $_SESSION['reset_success'] = 'Kode OTP telah dikirim ke email Anda. Silakan cek inbox atau spam folder.';
                            header('Location: verifikasi_otp.php');
                            exit;
                        } else {
                            $_SESSION['reset_error'] = 'Gagal mengirim email OTP. Silakan coba lagi.';
                            header('Location: login.php');
                            exit;
                        }
                        
                    } catch (Exception $e) {
                        $_SESSION['reset_error'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
                        error_log("EmailService error: " . $e->getMessage());
                        header('Location: login.php');
                        exit;
                    }
                    
                } else {
                    // EMAIL TIDAK TERDAFTAR - beri tahu pengguna
                    $_SESSION['reset_error'] = 'Email tidak terdaftar dalam sistem.';
                    header('Location: login.php');
                    exit;
                }
            } catch (PDOException $e) {
                $_SESSION['reset_error'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
                error_log("Reset password error: " . $e->getMessage());
                header('Location: login.php');
                exit;
            }
        }
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Email dan password harus diisi';
            header('Location: login.php');
            exit;
        } elseif ($auth->login($email, $password)) {
            $user = $auth->getCurrentUser();
            if ($user['role'] === ROLE_ADMIN) {
                header('Location: dashboard_admin.php');
            } else {
                header('Location: dashboard_mahasiswa.php');
            }
            exit;
        } else {
            $_SESSION['login_error'] = 'Email atau password salah';
            header('Location: login.php');
            exit;
        }
    }
}

$error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
$reset_error = isset($_SESSION['reset_error']) ? $_SESSION['reset_error'] : '';
$reset_success = isset($_SESSION['reset_success']) ? $_SESSION['reset_success'] : '';

unset($_SESSION['login_error']);
unset($_SESSION['reset_error']);
unset($_SESSION['reset_success']);

$show_reset_form = isset($_POST['action']) && $_POST['action'] === 'reset_request';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
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
        .login-header {
            background: linear-gradient(135deg, #d58dbdff 0%, #368b7fff 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #d58dbdff 0%, #368b7fff 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #d58dbdff 0%, #368b7fff 100%);
            opacity: 0.9;
        }
        .btn-warning {
            background: #ffc107;
            border: none;
            color: #000;
        }
        .form-control:focus {
            border-color: #d58dbdff;
            box-shadow: 0 0 0 0.25rem rgba(213, 141, 189, 0.25);
        }
        .reset-form {
            display: none;
        }
        .reset-link {
            cursor: pointer;
            color: #368b7fff;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-block;
        }
        .reset-link:hover {
            text-decoration: underline;
        }
        .email-info {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .alert-success {
            background-color: #d1e7dd;
            border-color: #badbcc;
            color: #0f5132;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }
        .alert-info {
            background-color: #cff4fc;
            border-color: #b6effb;
            color: #055160;
        }
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffecb5;
            color: #664d03;
        }
        .login-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }
        .forgot-password-wrapper {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="login-header">
                        <h3 class="mb-1"><i class="bi bi-shield"></i> <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></h3>
                        <small class="opacity-75">Sistem Pelaporan Keamanan Kampus</small>
                    </div>
                    <div class="login-body">
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($reset_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($reset_error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($reset_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            <?= htmlspecialchars($reset_success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="loginForm" <?= $show_reset_form ? 'style="display:none;"' : '' ?>>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                        placeholder="contoh@email.com" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                        placeholder="Masukkan password" required>
                                </div>
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary w-100">Login</button>
                            </div>
                            
                            <!-- Lupa Password di kanan -->
                            <div class="forgot-password-wrapper">
                                <a href="#" class="reset-link" id="showResetLink" onclick="showResetForm(); return false;">
                                    <i class="bi bi-question-circle me-1"></i> Lupa Password?
                                </a>
                            </div>
                        </form>
                        
                        <form method="POST" id="resetForm" class="reset-form" <?= $show_reset_form ? 'style="display:block;"' : '' ?>>
                            <input type="hidden" name="action" value="reset_request">
                            
                            <div class="mb-3">
                                <label for="reset_email" class="form-label">Email Terdaftar</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="reset_email" name="reset_email" 
                                        value="<?= htmlspecialchars($_POST['reset_email'] ?? '') ?>" 
                                        placeholder="contoh@email.com" required>
                                </div>
                                <div class="form-text">
                                    Kami akan mengirim kode OTP ke email Anda. Pastikan email terdaftar.
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Keamanan:</strong> 
                                OTP hanya berlaku 10 menit dan maksimal 3 percobaan.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-send me-1"></i> Kirim Kode OTP
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="showLoginForm()">
                                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Login
                                </button>
                            </div>
                        </form>
                        
                        <hr>
                        <p class="text-center mb-0">
                            Belum punya akun? <a href="register.php">Daftar di sini</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showResetForm() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('resetForm').style.display = 'block';
            document.querySelector('hr').style.display = 'none';
            document.querySelector('p.text-center:last-child').style.display = 'none';
        }
        
        function showLoginForm() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('resetForm').style.display = 'none';
            document.querySelector('hr').style.display = 'block';
            document.querySelector('p.text-center:last-child').style.display = 'block';
            
            document.getElementById('resetForm').reset();
        }
        
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 8000);
        
        <?php if ($show_reset_form): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showResetForm();
        });
        <?php endif; ?>
    </script>
</body>
</html>