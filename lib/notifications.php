<?php

class SimpleNotification {
    
    public static function add($db, $user_id, $title, $message, $laporan_id = null) {
        try {
            $stmt = $db->prepare("
                INSERT INTO notifikasi (user_id, laporan_id, title, message, status) 
                VALUES (?, ?, ?, ?, 'unread')
            ");
            return $stmt->execute([$user_id, $laporan_id, $title, $message]);
        } catch (Exception $e) {
            error_log("Notification add error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function getUserNotifications($db, $user_id, $limit = 5) {
        try {
            // Gunakan named parameter untuk menghindari LIMIT issue
            $stmt = $db->prepare("
                SELECT * FROM notifikasi 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            return [];
        }
    }
    
    public static function getUnreadCount($db, $user_id) {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM notifikasi 
                WHERE user_id = ? AND status = 'unread'
            ");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Unread count error: " . $e->getMessage());
            return 0;
        }
    }
}
?>