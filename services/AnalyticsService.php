<?php
//Analytics
class AnalyticsService {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // ================ METHOD UMUM YANG DIGUNAKAN OLEH SEMUA ROLE ================
    
    // 1. Data frekuensi per bulan
    public function getMonthlyData($months = 6) {
        $query = "
            SELECT 
                DATE_FORMAT(created_at, '%b %Y') as bulan_label,
                DATE_FORMAT(created_at, '%Y-%m') as bulan,
                COUNT(*) as jumlah
            FROM laporan
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b')
            ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$months]);
        return $stmt->fetchAll();
    }
    
    // 2. Area rawan/hotspots
    public function getHotspots($limit = 10, $months = null) {
        $where_clause = $months ? "WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)" : "";
        $params = $months ? [$months] : [];
        
        $limit_val = (int)$limit;
        
        $query = "
            SELECT 
                l.location_address as lokasi,
                k.name as kategori,
                COUNT(*) as frekuensi,
                ROUND(
                    SUM(CASE 
                        WHEN LOWER(l.status) LIKE '%selesai%' OR 
                             LOWER(l.status) LIKE '%resolved%' OR
                             l.status IN ('Selesai', 'Resolved', 'Completed') 
                        THEN 1 
                        ELSE 0 
                    END) * 100.0 / COUNT(*), 
                0) as persentase_selesai
            FROM laporan l
            LEFT JOIN kategori k ON l.kategori_id = k.id
            {$where_clause}
            GROUP BY l.location_address, k.name
            HAVING COUNT(*) >= 1
            ORDER BY COUNT(*) DESC
            LIMIT {$limit_val}
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // 3. Statistik umum
    public function getGeneralStats($months = 6) {
        // Data per bulan untuk statistik
        $monthly_counts = $this->getMonthlyData($months);
        
        $total = 0;
        $counts_array = [];
        
        foreach ($monthly_counts as $month) {
            $total += $month['jumlah'];
            $counts_array[] = $month['jumlah'];
        }
        
        $rata_per_bulan = count($counts_array) > 0 ? round(array_sum($counts_array) / count($counts_array), 1) : 0;
        $tertinggi = count($counts_array) > 0 ? max($counts_array) : 0;
        $terendah = count($counts_array) > 0 ? min($counts_array) : 0;
        
        return [
            'total' => $total,
            'rata_per_bulan' => $rata_per_bulan,
            'tertinggi' => $tertinggi,
            'terendah' => $terendah
        ];
    }
    
    // 4. Pola per jam
    public function getHourPattern($months = 6) {
        $query = "
            SELECT 
                HOUR(created_at) as jam,
                COUNT(*) as frekuensi
            FROM laporan
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY HOUR(created_at)
            ORDER BY jam
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$months]);
        return $stmt->fetchAll();
    }
    
    // 5. Pola per hari
    public function getDayPattern($months = 6) {
        $query = "
            SELECT 
                DAYNAME(created_at) as hari,
                COUNT(*) as frekuensi
            FROM laporan
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DAYNAME(created_at), DAYOFWEEK(created_at)
            ORDER BY DAYOFWEEK(created_at)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$months]);
        return $stmt->fetchAll();
    }
    
    // 6. Pola korelasi kategori & waktu (utamanya untuk admin) - DIPERBAIKI
    public function getCorrelationPattern($months = 6, $limit = 15) {
        // Cast limit ke integer dan validasi
        $limit_val = (int)$limit;
        if ($limit_val <= 0) {
            $limit_val = 15;
        }
        
        $query = "
            SELECT 
                k.name as kategori,
                HOUR(l.created_at) as jam,
                DAYNAME(l.created_at) as hari,
                COUNT(*) as frekuensi
            FROM laporan l
            JOIN kategori k ON l.kategori_id = k.id
            WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY k.name, HOUR(l.created_at), DAYNAME(l.created_at)
            HAVING COUNT(*) >= 2
            ORDER BY COUNT(*) DESC
            LIMIT {$limit_val}
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$months]);
        return $stmt->fetchAll();
    }
    
    // 7. Jam puncak
    public function getPeakHour($months = 6) {
        $hour_pattern = $this->getHourPattern($months);
        
        if (empty($hour_pattern)) {
            return 14; // Default jika tidak ada data
        }
        
        $peak_hour = 0;
        $max_frequency = 0;
        
        foreach ($hour_pattern as $hour) {
            if ($hour['frekuensi'] > $max_frequency) {
                $max_frequency = $hour['frekuensi'];
                $peak_hour = $hour['jam'];
            }
        }
        
        return $peak_hour;
    }
    
    // 8. Hari puncak
    public function getPeakDay($months = 6) {
        $day_pattern = $this->getDayPattern($months);
        
        if (empty($day_pattern)) {
            return 'Wednesday'; // Default jika tidak ada data
        }
        
        $peak_day = '';
        $max_frequency = 0;
        
        foreach ($day_pattern as $day) {
            if ($day['frekuensi'] > $max_frequency) {
                $max_frequency = $day['frekuensi'];
                $peak_day = $day['hari'];
            }
        }
        
        return $peak_day;
    }
    
    // ================ METHOD KHUSUS UNTUK MASING-MASING ROLE ================
    
    // Data untuk mahasiswa
    public function getMahasiswaAnalytics($months = 6) {
        $data = [];
        
        $data['monthly'] = $this->getMonthlyData($months);
        $data['hotspots'] = $this->getHotspots(5, $months);
        $data['stats'] = $this->getGeneralStats($months);
        $data['hour_pattern'] = $this->getHourPattern($months);
        $data['day_pattern'] = $this->getDayPattern($months);
        $data['peak_hour'] = $this->getPeakHour($months);
        $data['peak_day'] = $this->getPeakDay($months);
        
        return $data;
    }
    
    // Data untuk admin
    public function getAdminAnalytics($months = 12) {
        $data = [];
        
        $data['monthly'] = $this->getMonthlyData($months);
        $data['hotspots'] = $this->getHotspots(10, $months);
        $data['stats'] = $this->getGeneralStats($months);
        $data['hour_pattern'] = $this->getHourPattern($months);
        $data['day_pattern'] = $this->getDayPattern($months);
        $data['correlations'] = $this->getCorrelationPattern($months, 15);
        $data['peak_hour'] = $this->getPeakHour($months);
        $data['peak_day'] = $this->getPeakDay($months);
        
        return $data;
    }
    
    public function getByMonth($months = 12) {
        $query = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM laporan
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY DATE_FORMAT(created_at, '%Y-%m') DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$months]);
        return $stmt->fetchAll();
    }

    public function getPublicAnalytics($months = 12) {
       
        $data = $this->getAdminAnalytics($months);
        
        
        unset($data['sensitive_info']); // jika ada
        
        return $data;
}
}
?>