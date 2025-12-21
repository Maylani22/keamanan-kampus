<?php

class CacheService {
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get($key, $category = 'general') {
        if (!CACHE_ENABLED) {
            return false;
        }
        
        $cacheFile = $this->getCachePath($key, $category);
        
        if (file_exists($cacheFile)) {
            $data = file_get_contents($cacheFile);
            $cacheData = json_decode($data, true);
            
            if ($cacheData && isset($cacheData['expires']) && time() < $cacheData['expires']) {
                return $cacheData['data'];
            } else {
                unlink($cacheFile);
            }
        }
        
        return false;
    }
    
    public function set($key, $data, $category = 'general', $ttl = CACHE_TTL) {
        if (!CACHE_ENABLED) {
            return false;
        }
        
        $cacheFile = $this->getCachePath($key, $category);
        $cacheDir = dirname($cacheFile);
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheData = [
            'data' => $data,
            'created' => time(),
            'expires' => time() + $ttl,
            'key' => $key,
            'category' => $category
        ];
        
        return file_put_contents($cacheFile, json_encode($cacheData));
    }
    
    public function delete($key, $category = 'general') {
        $cacheFile = $this->getCachePath($key, $category);
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return false;
    }
    
    public function clearCategory($category) {
        $cacheDir = CACHE_DIR . '/' . $category;
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*.json');
            $deleted = 0;
            foreach ($files as $file) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
            return $deleted;
        }
        return 0;
    }
    
    public function clearAll() {
        $categories = ['mapbox', 'osm', 'analytics', 'general'];
        $totalDeleted = 0;
        
        foreach ($categories as $category) {
            $totalDeleted += $this->clearCategory($category);
        }
        
        return $totalDeleted;
    }
    
    public function getStats() {
        $categories = ['mapbox', 'osm', 'analytics', 'general'];
        $stats = [];
        
        foreach ($categories as $category) {
            $cacheDir = CACHE_DIR . '/' . $category;
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*.json');
                $size = 0;
                $expired = 0;
                $valid = 0;
                
                foreach ($files as $file) {
                    $size += filesize($file);
                    $data = json_decode(file_get_contents($file), true);
                    
                    if ($data && isset($data['expires'])) {
                        if (time() > $data['expires']) {
                            $expired++;
                        } else {
                            $valid++;
                        }
                    }
                }
                
                $stats[$category] = [
                    'files' => count($files),
                    'valid' => $valid,
                    'expired' => $expired,
                    'size' => $this->formatBytes($size)
                ];
            }
        }
        
        return $stats;
    }
    
    private function getCachePath($key, $category) {
        $hash = md5($key);
        return CACHE_DIR . '/' . $category . '/' . $hash . '.json';
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
     
    public function preCacheCampusMap() {
        if (!CACHE_ENABLED || !defined('MAPBOX_TOKEN') || empty(MAPBOX_TOKEN)) {
            return false;
        }
        
        $campusLat = CAMPUS_LAT;
        $campusLng = CAMPUS_LNG;
        
        $zoomLevels = [14, 15, 16, 17, 18];
        $tileUrls = [];
        
        foreach ($zoomLevels as $zoom) {
            $xtile = floor((($campusLng + 180) / 360) * pow(2, $zoom));
            $ytile = floor((1 - log(tan(deg2rad($campusLat)) + 1 / cos(deg2rad($campusLat))) / pi()) / 2 * pow(2, $zoom));
            
            for ($x = $xtile - 1; $x <= $xtile + 1; $x++) {
                for ($y = $ytile - 1; $y <= $ytile + 1; $y++) {
                    if ($x >= 0 && $y >= 0) {
                        $tileKey = "tile_{$zoom}_{$x}_{$y}";
                        $tileUrl = "https://api.mapbox.com/styles/v1/" . MAPBOX_STYLE . "/tiles/256/{$zoom}/{$x}/{$y}?access_token=" . MAPBOX_TOKEN;
                        $tileUrls[$tileKey] = $tileUrl;
                    }
                }
            }
        }
        
        return $tileUrls; 
    }
}