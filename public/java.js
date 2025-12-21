let liveMap = null;
let userMarker = null;
let accuracyCircle = null;
let isLocationDetected = false;
let watchId = null;

function initFreeMap() {
    document.getElementById('mapLoading').classList.add('active');
    
    const mapElement = document.getElementById('liveMap');
    const defaultLat = parseFloat(mapElement.dataset.campusLat);
    const defaultLng = parseFloat(mapElement.dataset.campusLng);
    const editLat = mapElement.dataset.editLat ? parseFloat(mapElement.dataset.editLat) : null;
    const editLng = mapElement.dataset.editLng ? parseFloat(mapElement.dataset.editLng) : null;
    
    liveMap = L.map('liveMap').setView([defaultLat, defaultLng], 15);
    
    // Gunakan Mapbox tile layer
    L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
        attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
        id: 'mapbox/streets-v12',
        tileSize: 512,
        zoomOffset: -1,
        accessToken: 'pk.eyJ1IjoibXlsaTIyIiwiYSI6ImNtamVxcm83ZTBlN2wzZXM2ODV6aWF1OWoifQ.B57iu8W4s81qv4UxcemVPw'
    }).addTo(liveMap)
      .bindPopup('<strong>Pusat Kampus</strong><br>Lokasi default sistem');
    
    setTimeout(() => {
        document.getElementById('mapLoading').classList.remove('active');
    }, 500);
    
    if (editLat && editLng) {
        setTimeout(() => {
            updateMapWithLocation(editLat, editLng, 20);
            updateLocationInfo(editLat, editLng, 20);
            
            document.getElementById('detectLocationBtn').innerHTML = '<i class="bi bi-check-circle"></i> Lokasi Terdeteksi';
            document.getElementById('detectLocationBtn').classList.remove('btn-primary');
            document.getElementById('detectLocationBtn').classList.add('btn-success');
            document.getElementById('clearLocationBtn').style.display = 'block';
            isLocationDetected = true;
        }, 1000);
    }
}
function createUserMarkerIcon() {
    return L.divIcon({
        className: 'user-marker',
        html: `
            <div style="position: relative;">
                <div class="pulse-ring"></div>
                <i class="bi bi-geo-alt-fill text-danger fs-3"></i>
            </div>
        `,
        iconSize: [30, 30],
        iconAnchor: [15, 30]
    });
}

function detectUserLocation() {
    const btn = document.getElementById('detectLocationBtn');
    const status = document.getElementById('locationStatus');
    
    if (!navigator.geolocation) {
        status.innerHTML = '<span class="text-danger">Browser tidak mendukung geolocation</span>';
        return;
    }
    
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mendeteksi...';
    btn.disabled = true;
    status.innerHTML = '<span class="text-warning">Meminta izin lokasi...</span>';
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            document.getElementById('latitudeInput').value = lat;
            document.getElementById('longitudeInput').value = lng;
            document.getElementById('accuracyInput').value = accuracy;
            
            updateLocationInfo(lat, lng, accuracy);
            updateMapWithLocation(lat, lng, accuracy);
            getFreeAddress(lat, lng);
            
            isLocationDetected = true;
            status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Lokasi berhasil dideteksi!</span>';
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Lokasi Terdeteksi';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            
            document.getElementById('clearLocationBtn').style.display = 'block';
            startWatchingLocation();
        },
        function(error) {
            let message = 'Gagal mendeteksi lokasi: ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message += 'Izin ditolak.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += 'Lokasi tidak tersedia.';
                    break;
                case error.TIMEOUT:
                    message += 'Waktu habis. Coba lagi.';
                    break;
                default:
                    message += 'Error tidak diketahui.';
            }
            
            status.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ${message}</span>`;
            btn.innerHTML = '<i class="bi bi-geo-alt"></i> Coba Lagi';
            btn.disabled = false;
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}

// Update map with user location
function updateMapWithLocation(lat, lng, accuracy) {
    if (!liveMap) return;
    
    if (userMarker) liveMap.removeLayer(userMarker);
    if (accuracyCircle) liveMap.removeLayer(accuracyCircle);
    
    userMarker = L.marker([lat, lng], {
        icon: createUserMarkerIcon(),
        draggable: true
    }).addTo(liveMap);
    
    accuracyCircle = L.circle([lat, lng], {
        color: '#3498db',
        fillColor: '#3498db',
        fillOpacity: 0.1,
        radius: accuracy,
        className: 'accuracy-circle'
    }).addTo(liveMap);
    
    liveMap.setView([lat, lng], 16);
    
    userMarker.on('dragend', function(e) {
        const newLatLng = e.target.getLatLng();
        updateLocationInfo(newLatLng.lat, newLatLng.lng, accuracy);
        getFreeAddress(newLatLng.lat, newLatLng.lng);
    });
    
    userMarker.bindPopup(`
        <div class="text-center">
            <strong><i class="bi bi-person-circle"></i> Lokasi Anda</strong><br>
            <small>Pindahkan marker jika tidak tepat</small>
        </div>
    `).openPopup();
}

// Get address from coordinates
function getFreeAddress(lat, lng) {
    const status = document.getElementById('locationStatus');
    const addressInput = document.getElementById('locationInput');
    
    status.innerHTML = '<span class="text-info">Mengambil alamat...</span>';
    
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
    
    fetch(url, {
        headers: {
            'Accept-Language': 'id-ID,id;q=0.9',
            'User-Agent': 'SistemKeamananKampus/1.0'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data && data.display_name) {
            let address = data.display_name;
            const components = [];
            if (data.address.road) components.push(data.address.road);
            if (data.address.village || data.address.suburb) 
                components.push(data.address.village || data.address.suburb);
            if (data.address.city_district) components.push(data.address.city_district);
            if (data.address.city) components.push(data.address.city);
            
            if (components.length > 0) {
                address = components.join(', ');
            }
            
            addressInput.value = address;
            status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Alamat ditemukan!</span>';
        } else {
            addressInput.value = `Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            status.innerHTML = '<span class="text-warning">Alamat tidak ditemukan</span>';
        }
    })
    .catch(error => {
        console.error('Reverse geocode error:', error);
        addressInput.value = `Koordinat: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        status.innerHTML = '<span class="text-warning">Gagal mengambil alamat</span>';
    });
}

// Update location info display
function updateLocationInfo(lat, lng, accuracy) {
    const coordInfo = document.getElementById('coordinatesInfo');
    const accInfo = document.getElementById('accuracyInfo');
    
    coordInfo.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    coordInfo.className = 'badge bg-success';
    
    const roundedAcc = Math.round(accuracy);
    accInfo.textContent = `${roundedAcc}m`;
    
    if (accuracy < 50) {
        accInfo.className = 'badge accuracy-high';
    } else if (accuracy < 200) {
        accInfo.className = 'badge accuracy-medium';
    } else {
        accInfo.className = 'badge accuracy-low';
    }
}

// Start watching location
function startWatchingLocation() {
    if (watchId) navigator.geolocation.clearWatch(watchId);
    
    watchId = navigator.geolocation.watchPosition(
        function(position) {
            const newAcc = position.coords.accuracy;
            if (newAcc < 50) {
                updateMapWithLocation(
                    position.coords.latitude,
                    position.coords.longitude,
                    newAcc
                );
            }
        },
        function(error) {
            console.log('Watch position error:', error);
        },
        {
            enableHighAccuracy: true,
            maximumAge: 30000,
            timeout: 10000
        }
    );
}

// Clear location
function clearLocation() {
    if (userMarker) liveMap.removeLayer(userMarker);
    if (accuracyCircle) liveMap.removeLayer(accuracyCircle);
    if (watchId) navigator.geolocation.clearWatch(watchId);
    
    document.getElementById('latitudeInput').value = '';
    document.getElementById('longitudeInput').value = '';
    document.getElementById('accuracyInput').value = '';
    document.getElementById('locationStatus').innerHTML = 'Lokasi dihapus.';
    
    const btn = document.getElementById('detectLocationBtn');
    btn.innerHTML = '<i class="bi bi-geo-alt"></i> Deteksi Lokasi';
    btn.classList.remove('btn-success');
    btn.classList.add('btn-primary');
    btn.disabled = false;
    
    document.getElementById('clearLocationBtn').style.display = 'none';
    isLocationDetected = false;
}

function setupMapControls() {
    document.getElementById('zoomInBtn').addEventListener('click', function() {
        liveMap.zoomIn();
    });
    
    document.getElementById('zoomOutBtn').addEventListener('click', function() {
        liveMap.zoomOut();
    });
    
    document.getElementById('locateBtn').addEventListener('click', function() {
        if (userMarker) {
            liveMap.setView(userMarker.getLatLng(), liveMap.getZoom());
        } else {
            // Ambil data kampus dari atribut data
            const mapElement = document.getElementById('liveMap');
            const campusLat = parseFloat(mapElement.dataset.campusLat);
            const campusLng = parseFloat(mapElement.dataset.campusLng);
            liveMap.setView([campusLat, campusLng], 15);
        }
    });
}

function setupFormValidation() {
    const form = document.getElementById('laporanForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        if (!isLocationDetected && !document.getElementById('locationInput').value.trim()) {
            e.preventDefault();
            alert('Silakan deteksi lokasi atau masukkan alamat manual!');
            return false;
        }
        
        return true;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initFreeMap();
    document.getElementById('detectLocationBtn').addEventListener('click', detectUserLocation);
    document.getElementById('clearLocationBtn').addEventListener('click', clearLocation);
    setupMapControls();
    setupFormValidation();
});


document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('viewMap');
    if (!mapElement) {
        // console.warn('Elemen viewMap tidak ditemukan');
        return; // Silent return, tidak perlu warning
    }
    
    const latStr = mapElement.dataset.lat;
    const lngStr = mapElement.dataset.lng;
    const title = mapElement.dataset.title || 'Laporan';
    
    // Parse dengan aman
    const lat = parseFloat(latStr);
    const lng = parseFloat(lngStr);
    
    if (isNaN(lat) || isNaN(lng)) {
        console.error('Koordinat tidak valid:', latStr, lngStr);
        
        const loadingElement = document.getElementById('viewMapLoading');
        if (loadingElement) {
            loadingElement.classList.remove('active');
            loadingElement.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-map text-muted display-6"></i>
                    <p class="text-muted mt-2">Lokasi tidak tersedia</p>
                </div>
            `;
        }
        return;
    }
    
    const loadingElement = document.getElementById('viewMapLoading');
    if (loadingElement) {
        loadingElement.classList.add('active');
    }
    
    // Gunakan global variable, tidak deklarasi ulang dengan const
    viewMap = L.map('viewMap').setView([lat, lng], 16);
    
    // Gunakan token dari PHP atau default
    const accessToken = window.MAPBOX_TOKEN || 'pk.eyJ1IjoibXlsaTIyIiwiYSI6ImNtamVxcm83ZTBlN2wzZXM2ODV6aWF1OWoifQ.B57iu8W4s81qv4UxcemVPw';
    
    L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
        attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
        id: 'mapbox/streets-v12',
        tileSize: 512,
        zoomOffset: -1,
        accessToken: accessToken
    }).addTo(viewMap);
    
    const incidentIcon = L.divIcon({
        className: 'incident-marker',
        html: '<i class="bi bi-geo-alt-fill text-danger fs-3"></i>',
        iconSize: [30, 30],
        iconAnchor: [15, 30]
    });
    
    const incidentMarker = L.marker([lat, lng], {
        icon: incidentIcon
    }).addTo(viewMap);
    
    incidentMarker.bindPopup(`
        <div class="text-center">
            <strong><i class="bi bi-exclamation-triangle"></i> Lokasi Kejadian</strong><br>
            <small>${escapeHtml(title)}</small>
        </div>
    `).openPopup();
    
    L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.1,
        radius: 30
    }).addTo(viewMap);
    
    // Pastikan peta di-resize setelah dimuat
    setTimeout(() => {
        if (viewMap) {
            viewMap.invalidateSize();
        }
        if (loadingElement) {
            loadingElement.classList.remove('active');
        }
    }, 500);
    
    // Fungsi helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});

// Fungsi untuk inisialisasi view map di laporan_admin
function initAdminViewMap(mapElement) {
    const loadingElement = document.getElementById('viewMapLoading');
    if (loadingElement) {
        loadingElement.classList.add('active');
    }
    
    const accessToken = window.MAPBOX_TOKEN || 'pk.eyJ1IjoibXlsaTIyIiwiYSI6ImNtamVxcm83ZTBlN2wzZXM2ODV6aWF1OWoifQ.B57iu8W4s81qv4UxcemVPw';
    const styleId = window.MAPBOX_STYLE || 'mapbox/streets-v12';
    
    const campusLat = parseFloat(mapElement.dataset.campusLat) || -6.360;
    const campusLng = parseFloat(mapElement.dataset.campusLng) || 106.830;
    const reportLat = parseFloat(mapElement.dataset.reportLat);
    const reportLng = parseFloat(mapElement.dataset.reportLng);
    const reportTitle = mapElement.dataset.reportTitle || 'Laporan';
    
    let centerLat, centerLng, zoom;
    
    if (!isNaN(reportLat) && !isNaN(reportLng)) {
        centerLat = reportLat;
        centerLng = reportLng;
        zoom = 16;
    } else {
        centerLat = campusLat;
        centerLng = campusLng;
        zoom = 15;
    }
    
    const adminViewMap = L.map('mapView').setView([centerLat, centerLng], zoom);
    
    // Gunakan Mapbox tile layer
    L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
        attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
        id: 'mapbox/streets-v12',
        tileSize: 512,
        zoomOffset: -1,
        accessToken: accessToken
    }).addTo(adminViewMap);
    
    // Tambahkan marker untuk laporan
    if (!isNaN(reportLat) && !isNaN(reportLng)) {
        const reportIcon = L.divIcon({
            className: 'report-marker',
            html: '<div class="text-center"><i class="bi bi-geo-alt-fill text-danger fs-3"></i></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 30]
        });
        
        L.marker([reportLat, reportLng], {
            icon: reportIcon
        }).addTo(adminViewMap)
        .bindPopup(`
            <div class="text-center">
                <strong><i class="bi bi-clipboard-check"></i> Lokasi Laporan</strong><br>
                <small>${escapeHtml(reportTitle)}</small>
            </div>
        `).openPopup();
        
        // Tambahkan radius kecil
        L.circle([reportLat, reportLng], {
            color: '#dc3545',
            fillColor: '#dc3545',
            fillOpacity: 0.1,
            radius: 30
        }).addTo(adminViewMap);
    }
    
    // Tambahkan marker untuk kampus
    const campusIcon = L.divIcon({
        className: 'campus-marker',
        html: '<div class="text-center"><i class="bi bi-building text-primary fs-4"></i><br><small class="text-muted">Kampus</small></div>',
        iconSize: [40, 40],
        iconAnchor: [20, 40]
    });
    
    L.marker([campusLat, campusLng], {
        icon: campusIcon
    }).addTo(adminViewMap)
    .bindPopup('<strong>Pusat Kampus</strong>');
    
    // Setup map controls
    document.getElementById('zoomInBtn')?.addEventListener('click', function() {
        adminViewMap.zoomIn();
    });
    
    document.getElementById('zoomOutBtn')?.addEventListener('click', function() {
        adminViewMap.zoomOut();
    });
    
    document.getElementById('centerBtn')?.addEventListener('click', function() {
        if (!isNaN(reportLat) && !isNaN(reportLng)) {
            adminViewMap.setView([reportLat, reportLng], 16);
        } else {
            adminViewMap.setView([campusLat, campusLng], 15);
        }
    });
    
    // Resize map setelah load dan sembunyikan loading
    setTimeout(() => {
        adminViewMap.invalidateSize();
        if (loadingElement) {
            loadingElement.classList.remove('active');
        }
    }, 500);
}