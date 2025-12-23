<?php

require_once '../config/constants.php';

$error = '';
$success = '';
$email = $_SESSION['reset_email'] ?? '';

if (empty($email)) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        $error = 'Kode OTP harus diisi';
    } elseif (!preg_match('/^\d{6}$/', $otp)) {
        $error = 'Kode OTP harus 6 digit angka';
    } else {
   
        $session_otp = $_SESSION['reset_otp'] ?? '';
        $expires = $_SESSION['reset_otp_expires'] ?? 0;
     
        if (time() > $expires) {
            $error = 'Kode OTP sudah kadaluarsa. Silakan request ulang.';
            unset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_otp_expires']);
        } elseif ($session_otp != $otp) {
            $error = 'Kode OTP salah. Silakan coba lagi.';
        } else {
            $_SESSION['otp_verified'] = true;
            header('Location: reset_password.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP - <?= SITE_NAME ?></title>
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
        .otp-header {
            background: linear-gradient(135deg, #d58dbdff 0%, #368b7fff 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            text-align: center;
        }
        .otp-body {
            padding: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #d58dbdff 0%, #368b7fff 100%);
            border: none;
        }
        .otp-input {
            letter-spacing: 10px;
            font-size: 24px;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="otp-header">
                        <h3 class="mb-1"><i class="bi bi-shield-check"></i> Verifikasi OTP</h3>
                        <small><?= SITE_NAME ?></small>
                    </div>
                    <div class="otp-body">
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <p class="text-muted mb-4">
                            Masukkan kode OTP 6 digit yang dikirim ke:<br>
                            <strong><?= htmlspecialchars($email) ?></strong>
                        </p>
                        
                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label">Kode OTP</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="text" class="form-control otp-input" name="otp" 
                                           placeholder="123456" maxlength="6" required
                                           pattern="\d{6}" title="Masukkan 6 digit angka">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i> Verifikasi OTP
                                </button>
                                <a href="login.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i> Kembali ke Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('.otp-input').focus();
        
        document.querySelector('.otp-input').addEventListener('input', function() {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
        
        document.querySelector('.otp-input').addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>