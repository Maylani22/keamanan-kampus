<?php

class Notifikasi {
    private $conn;
    private $table = 'notifikasi';
    
    public $id;
    public $user_id;
    public $laporan_id;
    public $title;
    public $message;
    public $status;
    public $created_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Buat notifikasi baru
     */
    public function create() {
        $query = "INSERT INTO {$this->table} 
                  (user_id, laporan_id, title, message, status) 
                  VALUES (:user_id, :laporan_id, :title, :message, :status)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':laporan_id', $this->laporan_id);
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':message', $this->message);
        $stmt->bindParam(':status', $this->status);
        
        return $stmt->execute();
    }
    
    /**
     * Dapatkan notifikasi berdasarkan user
     */
    public function getByUser($user_id, $limit = null) {
        $query = "SELECT * FROM {$this->table} 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Hitung notifikasi belum dibaca
     */
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} 
                  WHERE user_id = :user_id AND status = 'unread'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }
    
    /**
     * Tandai notifikasi sebagai dibaca
     */
    public function markAsRead($id, $user_id) {
        $query = "UPDATE {$this->table} 
                  SET status = 'read' 
                  WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }
    
    /**
     * Hapus notifikasi lama (lebih dari 30 hari)
     */
    public function cleanOldNotifications($days = 30) {
        $query = "DELETE FROM {$this->table} 
                  WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days);
        
        return $stmt->execute();
    }
}
?>