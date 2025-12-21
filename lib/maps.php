<?php

require_once __DIR__ . '/../services/CacheService.php';

class Maps {

    private static $cacheService = null;
    
    private static function getCacheService() {
        if (self::$cacheService === null) {
            self::$cacheService = CacheService::getInstance();
        }
        return self::$cacheService;
    }
    
    public static function geocode($address) {
        if (empty($address)) {
            return self::getDefaultCoords();
        }
        
        // Cache key
        $cacheKey = 'geocode_' . md5($address);
        
        // Check cache first - menggunakan CacheService
        $cached = self::getCacheService()->get($cacheKey, 'osm');
        if ($cached !== false) {
            $cached['cached'] = true;
            $cached['source'] = 'cache';
            return $cached;
        }
        
        // ... sisa kode geocode OSM tetap ...
        
        if (!empty($data) && isset($data[0]['lat'])) {
            $result = [
                'lat' => (float)$data[0]['lat'],
                'lng' => (float)$data[0]['lon'],
                'formatted_address' => $data[0]['display_name'],
                'place_id' => $data[0]['place_id'],
                'cached' => false,
                'source' => 'openstreetmap'
            ];
            
            // Save to cache - TTL lebih panjang untuk alamat statis
            self::getCacheService()->set($cacheKey, $result, 'osm', CACHE_TTL_LONG);
            
            return $result;
        }
        
        return self::getDefaultCoords();
    }
    
    public static function reverseGeocode($lat, $lng) {
        $cacheKey = 'reverse_' . md5("{$lat},{$lng}");
        
        // Check cache
        $cached = self::getCacheService()->get($cacheKey, 'osm');
        if ($cached !== false) {
            $cached['cached'] = true;
            $cached['source'] = 'cache';
            return $cached;
        }
        
        
        if ($data && isset($data['display_name'])) {
            $result = [
                'address' => $data['display_name'],
                'components' => $data['address'] ?? [],
                'cached' => false,
                'source' => 'openstreetmap'
            ];
            
            // Save to cache
            self::getCacheService()->set($cacheKey, $result, 'osm', CACHE_TTL_LONG);
            
            return $result;
        }
        
        return null;
    }
    
    public static function getMapboxTile($zoom, $x, $y) {
        if (!defined('MAPBOX_TOKEN') || empty(MAPBOX_TOKEN)) {
            return false;
        }
        
        $cacheKey = "tile_{$zoom}_{$x}_{$y}";
        $cacheFile = CACHE_MAPBOX_DIR . '/' . $cacheKey . '.png';
        
        // Check cache
        if (CACHE_ENABLED && file_exists($cacheFile)) {
            if (time() - filemtime($cacheFile) < CACHE_TTL) {
                header('Content-Type: image/png');
                readfile($cacheFile);
                return true;
            }
        }
        
        // Fetch from Mapbox
        $url = "https://api.mapbox.com/styles/v1/" . MAPBOX_STYLE . "/tiles/256/{$zoom}/{$x}/{$y}?access_token=" . MAPBOX_TOKEN;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $tileData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && !empty($tileData)) {
            // Save to cache
            if (CACHE_ENABLED) {
                if (!is_dir(CACHE_MAPBOX_DIR)) {
                    mkdir(CACHE_MAPBOX_DIR, 0755, true);
                }
                file_put_contents($cacheFile, $tileData);
            }
            
            header('Content-Type: image/png');
            echo $tileData;
            return true;
        }
        
        return false;
    }
    
    public static function getMapboxDirections($fromLat, $fromLng, $toLat, $toLng, $profile = 'driving') {
        if (!defined('MAPBOX_TOKEN') || empty(MAPBOX_TOKEN)) {
            return null;
        }
        
        $cacheKey = "directions_{$profile}_" . md5("{$fromLat},{$fromLng},{$toLat},{$toLng}");
        
        // Check cache
        $cached = self::getCacheService()->get($cacheKey, 'mapbox');
        if ($cached !== false) {
            return $cached;
        }
        
        $url = "https://api.mapbox.com/directions/v5/mapbox/{$profile}/{$fromLng},{$fromLat};{$toLng},{$toLat}";
        $url .= "?access_token=" . MAPBOX_TOKEN . "&geometries=geojson&steps=true";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            
            // Save to cache (TTL pendek karena traffic bisa berubah)
            self::getCacheService()->set($cacheKey, $data, 'mapbox', CACHE_TTL_SHORT);
            
            return $data;
        }
        
        return null;
    }
    
    public static function clearMapCache($type = 'all') {
        $cacheService = self::getCacheService();
        
        switch ($type) {
            case 'osm':
                return $cacheService->clearCategory('osm');
            case 'mapbox':
                return $cacheService->clearCategory('mapbox');
            case 'all':
                return $cacheService->clearCategory('osm') + $cacheService->clearCategory('mapbox');
            default:
                return 0;
        }
    }
    
    public static function getCacheStats() {
        return self::getCacheService()->getStats();
    }
}