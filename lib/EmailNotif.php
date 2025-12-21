<?php

class EmailNotif {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db;
    }
    
    // Mengirim notifikasi perubahan status laporan
    public function sendReportStatusChange($toEmail, $toName, $reportId, $reportTitle, $oldStatus, $newStatus) {
        if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) {
            error_log("Email disabled, skipping status change to: $toEmail");
            return true;
        }
        
        $oldStatusText = $this->getStatusText($oldStatus);
        $newStatusText = $this->getStatusText($newStatus);
        
        $subject = "Update Status Laporan #$reportId - " . SITE_NAME;
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; background: #f9f9f9; border: 1px solid #ddd; }
                .status-box { padding: 10px; margin: 15px 0; border-left: 4px solid; }
                .old-status { border-color: #ff9800; background: #fff3e0; }
                .new-status { border-color: #4CAF50; background: #e8f5e9; }
                .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
                .btn { display: inline-block; padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 3px; }
                table { width: 100%; border-collapse: collapse; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>" . SITE_NAME . "</h2>
                </div>
                
                <div class='content'>
                    <h3>Status Laporan Telah Diubah</h3>
                    <p>Halo <strong>$toName</strong>,</p>
                    
                    <p>Status laporan Anda telah diperbarui:</p>
                    
                    <div class='status-box old-status'>
                        <strong>Status Lama:</strong> $oldStatusText
                    </div>
                    
                    <div class='status-box new-status'>
                        <strong>Status Baru:</strong> $newStatusText
                    </div>
                    
                    <table>
                        <tr>
                            <td><strong>ID Laporan:</strong></td>
                            <td>#$reportId</td>
                        </tr>
                        <tr>
                            <td><strong>Judul:</strong></td>
                            <td>$reportTitle</td>
                        </tr>
                        <tr>
                            <td><strong>Waktu Perubahan:</strong></td>
                            <td>" . date('d/m/Y H:i') . "</td>
                        </tr>
                    </table>
                    
                    <p style='margin-top: 20px;'>
                        <a href='http://localhost/keamanan/public/laporan.php' class='btn'>
                            Lihat Detail Laporan
                        </a>
                    </p>
                </div>
                
                <div class='footer'>
                    <p>Email ini dikirim otomatis oleh " . SITE_NAME . ".<br>
                    Harap jangan membalas email ini.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($toEmail, $subject, $body);
    }
    
    // Mengirim notifikasi laporan baru ke admin
    public function sendNewReportToAdmin($adminEmail, $adminName, $reportId, $reportTitle, $reporterName) {
        if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) {
            return true;
        }
        
        $subject = "Laporan Baru Masuk #$reportId - " . SITE_NAME;
        
        $body = "
        <html>
        <body>
            <h2>Laporan Baru Masuk</h2>
            <p>Halo $adminName,</p>
            
            <p>Ada laporan baru yang memerlukan review:</p>
            
            <table border='1' cellpadding='10' cellspacing='0'>
                <tr><th>ID Laporan</th><td>#$reportId</td></tr>
                <tr><th>Judul</th><td>$reportTitle</td></tr>
                <tr><th>Pelapor</th><td>$reporterName</td></tr>
                <tr><th>Waktu</th><td>" . date('d/m/Y H:i') . "</td></tr>
                <tr><th>Status</th><td>Menunggu Review</td></tr>
            </table>
            
            <p style='margin-top: 20px;'>
                <a href='http://localhost/keamanan/public/laporan.php' 
                   style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>
                    Review Laporan
                </a>
            </p>
            
            <hr>
            <p><small>Sistem Informasi Keamanan Kampus</small></p>
        </body>
        </html>
        ";
        
        return $this->sendEmail($adminEmail, $subject, $body);
    }
    
    private function sendEmail($to, $subject, $body) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            if (EMAIL_TEST_MODE) {
                // kirim semua ke admin
                $mail->addAddress(EMAIL_ADMIN_ALERT);
                $mail->addReplyTo($to);
            } else {
                // kirim ke penerima asli
                $mail->addAddress($to);
            }
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            $result = $mail->send();
            
            error_log("Email sent to: $to - Result: " . ($result ? "SUCCESS" : "FAILED"));
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Email error to $to: " . $e->getMessage());
            return false;
        }
    }

    // status pesan
    private function getStatusText($status) {
        switch ($status) {
            case STATUS_PENDING: return "Menunggu Review";
            case STATUS_PROCESSED: return "Sedang Diproses";
            case STATUS_RESOLVED: return "Selesai";
            default: return $status;
        }
    }
}
?>