<?php
// lib/EmailService.php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configure();
    }
    
    private function configure() {
        try {
            // Konfigurasi SMTP
            $this->mail->isSMTP();
            $this->mail->Host       = SMTP_HOST;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = SMTP_USERNAME;
            $this->mail->Password   = SMTP_PASSWORD;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = SMTP_PORT;
            
            // Aktifkan debugging
            $this->mail->SMTPDebug = 2; // Level 2 untuk verbose output
            // Simpan debug output ke variabel
            $this->mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer debug level $level: $str");
            };
            
            $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("EmailService configuration error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function sendOTP($toEmail, $toName, $otp, $expiryMinutes = 10) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->Subject = 'Kode OTP Reset Password - ' . SITE_NAME;
            
            $html = $this->getOTPEmailTemplate($otp, $expiryMinutes, $toName);
            $this->mail->Body = $html;
            
            // Text alternative
            $text = "Halo $toName,\n\n"
                  . "Kode OTP Anda: $otp\n"
                  . "Berlaku hingga: " . date('H:i', time() + ($expiryMinutes * 60)) . "\n\n"
                  . "Jangan bagikan kode ini kepada siapapun.\n\n"
                  . "Salam,\n" . SITE_NAME;
            $this->mail->AltBody = $text;
            
            // Kirim email
            $this->mail->send();
            error_log("Email OTP berhasil dikirim ke: $toEmail");
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send OTP email to $toEmail. Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    private function getOTPEmailTemplate($otp, $expiryMinutes, $name) {
        $expiryTime = date('H:i', time() + ($expiryMinutes * 60));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #d58dbd 0%, #368b7f 100%); 
                         color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #368b7f; 
                           letter-spacing: 5px; text-align: center; padding: 15px; 
                           background: #e8f4f2; border-radius: 5px; margin: 20px 0; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; 
                          padding: 10px; margin: 15px 0; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; 
                         font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>" . SITE_NAME . "</h2>
                    <p>Sistem Pelaporan Keamanan Kampus</p>
                </div>
                <div class='content'>
                    <h3>Halo, $name!</h3>
                    <p>Anda telah meminta reset password. Gunakan kode OTP berikut:</p>
                    
                    <div class='otp-code'>$otp</div>
                    
                    <div class='warning'>
                        <strong>⏰ Kode berlaku: $expiryMinutes menit (sampai $expiryTime)</strong><br>
                        <strong>🛡️ Jangan bagikan kode ini kepada siapapun</strong>
                    </div>
                    
                    <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
                    
                    <div class='footer'>
                        <p>Email ini dikirim secara otomatis. Mohon tidak membalas.</p>
                        <p>&copy; " . date('Y') . " " . SITE_NAME . ". Semua hak dilindungi.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
}