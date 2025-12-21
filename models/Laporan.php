<?php
class Laporan {
    private $conn;
    private $table = 'laporan';

    public $id;
    public $user_id;
    public $kategori_id;
    public $title;
    public $description;
    public $location_address;
    public $latitude;
    public $longitude;
    public $status;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create report
    public function create() {
        $query = "INSERT INTO {$this->table} 
                  (user_id, kategori_id, title, description, location_address, latitude, longitude, status) 
                  VALUES (:user_id, :kategori_id, :title, :description, :location_address, :latitude, :longitude, :status)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':kategori_id', $this->kategori_id);
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':location_address', $this->location_address);
        $stmt->bindParam(':latitude', $this->latitude);
        $stmt->bindParam(':longitude', $this->longitude);
        $stmt->bindParam(':status', $this->status);
        
        return $stmt->execute();
    }

    // Get all reports (for admin)
    public function getAll() {
        $query = "SELECT l.*, u.name as user_name, k.name as kategori_name 
                  FROM {$this->table} l
                  LEFT JOIN users u ON l.user_id = u.id
                  LEFT JOIN kategori k ON l.kategori_id = k.id
                  ORDER BY l.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get reports by user with optional limit
    public function getByUser($user_id, $limit = null) {
        $query = "SELECT l.*, k.name as kategori_name 
                  FROM {$this->table} l
                  LEFT JOIN kategori k ON l.kategori_id = k.id
                  WHERE l.user_id = ?
                  ORDER BY l.created_at DESC";
        
        if ($limit !== null) {
            $query .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get report by ID
    public function getById($id) {
        $query = "SELECT l.*, u.name as user_name, k.name as kategori_name 
                  FROM {$this->table} l
                  LEFT JOIN users u ON l.user_id = u.id
                  LEFT JOIN kategori k ON l.kategori_id = k.id
                  WHERE l.id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update report
    public function update() {
        $query = "UPDATE {$this->table} 
                  SET kategori_id = :kategori_id, 
                      title = :title, 
                      description = :description, 
                      location_address = :location_address,
                      latitude = :latitude,
                      longitude = :longitude,
                      status = :status
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':kategori_id', $this->kategori_id);
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':location_address', $this->location_address);
        $stmt->bindParam(':latitude', $this->latitude);
        $stmt->bindParam(':longitude', $this->longitude);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    // Delete report
    public function delete() {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$this->id]);
    }

    // Get stats for dashboard
    public function getStats() {
        $stats = [];
        
        // Total reports
        $query = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total'] = $stmt->fetch()['total'];
        
        // By status
        $query = "SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent reports
        $query = "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }

    // Get reports by month for chart
    public function getByMonth() {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                  FROM {$this->table}
                  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                  ORDER BY month DESC
                  LIMIT 6";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========== METHOD UNTUK DASHBOARD MAHASISWA ==========
    
    // Count total reports by user
    public function countByUser($userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    // Count reports by status and user
    public function countByStatusAndUser($status, $userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = ? AND user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$status, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    // Get popular categories by user - FIXED VERSION
    public function getPopularCategoriesByUser($userId, $limit = 5) {
        $limit = (int)$limit; // Pastikan integer
        
        $sql = "SELECT k.name, COUNT(l.id) as count 
                FROM {$this->table} l 
                JOIN kategori k ON l.kategori_id = k.id 
                WHERE l.user_id = ? 
                GROUP BY k.id, k.name 
                ORDER BY count DESC 
                LIMIT " . $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get reports by status (for filtering)
    public function getByStatus($status) {
        $query = "SELECT l.*, u.name as user_name, k.name as kategori_name 
                  FROM {$this->table} l
                  LEFT JOIN users u ON l.user_id = u.id
                  LEFT JOIN kategori k ON l.kategori_id = k.id
                  WHERE l.status = ?
                  ORDER BY l.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get reports by category
    public function getByCategory($categoryId) {
        $query = "SELECT l.*, u.name as user_name, k.name as kategori_name 
                  FROM {$this->table} l
                  LEFT JOIN users u ON l.user_id = u.id
                  LEFT JOIN kategori k ON l.kategori_id = k.id
                  WHERE l.kategori_id = ?
                  ORDER BY l.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Search reports
    public function search($keyword, $userId = null) {
        $query = "SELECT l.*, u.name as user_name, k.name as kategori_name 
                  FROM {$this->table} l
                  LEFT JOIN users u ON l.user_id = u.id
                  LEFT JOIN kategori k ON l.kategori_id = k.id
                  WHERE (l.title LIKE :keyword OR l.description LIKE :keyword OR l.location_address LIKE :keyword)";
        
        if ($userId !== null) {
            $query .= " AND l.user_id = :user_id";
        }
        
        $query .= " ORDER BY l.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $keyword = "%{$keyword}%";
        $stmt->bindParam(':keyword', $keyword);
        
        if ($userId !== null) {
            $stmt->bindParam(':user_id', $userId);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get monthly stats by user
    public function getMonthlyStatsByUser($userId) {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                  FROM {$this->table}
                  WHERE user_id = ?
                  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                  ORDER BY month DESC
                  LIMIT 6";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Update report status
    public function updateStatus($id, $status) {
        $query = "UPDATE {$this->table} SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    // Get total reports count by status for all users (admin)
    public function getStatusCounts() {
        $query = "SELECT 
                    status,
                    COUNT(*) as count
                  FROM {$this->table}
                  GROUP BY status";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $counts = [
            'pending' => 0,
            'processed' => 0,
            'completed' => 0
        ];
        
        foreach ($result as $row) {
            $counts[$row['status']] = $row['count'];
        }
        
        return $counts;
    }
    
    // Get recent activity (last 30 days)
    public function getRecentActivity($userId = null, $days = 30) {
        $query = "SELECT 
                    l.*,
                    u.name as user_name,
                    k.name as kategori_name,
                    DATEDIFF(NOW(), l.created_at) as days_ago
                  FROM {$this->table} l
                  LEFT JOIN users u ON l.user_id = u.id
                  LEFT JOIN kategori k ON l.kategori_id = k.id
                  WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        if ($userId !== null) {
            $query .= " AND l.user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$days, $userId]);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$days]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>