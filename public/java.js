let map;
let marker;

function initFreeMap() {
    const el = document.getElementById('liveMap');
    if (!el) return;

    mapboxgl.accessToken = window.MAPBOX_TOKEN;

    const campusLat = parseFloat(el.dataset.campusLat);
    const campusLng = parseFloat(el.dataset.campusLng);

    map = new mapboxgl.Map({
        container: 'liveMap',
        style: 'mapbox://styles/' + (window.MAPBOX_STYLE || 'mapbox/streets-v12'),
        center: [campusLng, campusLat],
        zoom: 15
    });

    map.addControl(new mapboxgl.NavigationControl());

    const editLat = parseFloat(el.dataset.editLat);
    const editLng = parseFloat(el.dataset.editLng);

    map.on('load', () => {
        if (!isNaN(editLat) && !isNaN(editLng)) {
            setMarker(editLng, editLat);
            updateForm(editLat, editLng);
            map.flyTo({ center: [editLng, editLat], zoom: 16 });
        }
    });

    map.on('click', (e) => {
        setMarker(e.lngLat.lng, e.lngLat.lat);
        updateForm(e.lngLat.lat, e.lngLat.lng);
    });
}

function setMarker(lng, lat) {
    if (marker) marker.remove();

    marker = new mapboxgl.Marker({ draggable: true })
        .setLngLat([lng, lat])
        .addTo(map);

    marker.on('dragend', () => {
        const pos = marker.getLngLat();
        updateForm(pos.lat, pos.lng);
    });
}

function updateForm(lat, lng) {
    document.getElementById('latitudeInput').value = lat;
    document.getElementById('longitudeInput').value = lng;

    document.getElementById('locationStatus').innerText = 'Lokasi terdeteksi';
    document.getElementById('coordinatesInfo').innerText =
        `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
    document.getElementById('accuracyInfo').innerText = 'Mapbox';
}

function detectUserLocation() {
    if (!navigator.geolocation) {
        alert('Browser tidak mendukung geolocation');
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            setMarker(lng, lat);
            map.flyTo({ center: [lng, lat], zoom: 16 });

            document.getElementById('latitudeInput').value = lat;
            document.getElementById('longitudeInput').value = lng;

            document.getElementById('coordinatesInfo').innerText =
                `${lat.toFixed(4)}, ${lng.toFixed(4)}`;

            document.getElementById('accuracyInfo').innerText = 'Terdeteksi';

            reverseGeocodeMapbox(lat, lng);
        },
        () => {
            alert('Gagal mendeteksi lokasi');
        },
        {
            enableHighAccuracy: true,
            timeout: 15000
        }
    );
}


function reverseGeocodeMapbox(lat, lng) {
    const token = window.MAPBOX_TOKEN;

    if (!token) {
        console.warn('MAPBOX_TOKEN kosong');
        return;
    }

    const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?language=id&access_token=${token}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.features && data.features.length > 0) {
                const place = data.features[0].place_name;

                const input = document.getElementById('locationInput');
                if (input) {
                    input.value = place;
                }

                document.getElementById('locationStatus').innerText =
                    'Alamat terdeteksi otomatis';
            }
        })
        .catch(err => {
            console.error('Reverse geocode error:', err);
        });
}

function initViewMap(mapElement) {

    const loading = document.getElementById('viewMapLoading');
    if (loading) loading.classList.remove('active'); 

    const lat = parseFloat(mapElement.dataset.lat);
    const lng = parseFloat(mapElement.dataset.lng);
    const title = mapElement.dataset.title || 'Laporan';

    if (isNaN(lat) || isNaN(lng)) {
        console.warn('Koordinat tidak valid');
        return;
    }

    mapboxgl.accessToken = window.MAPBOX_TOKEN;

    mapElement.innerHTML = '';

    const viewMap = new mapboxgl.Map({
        container: mapElement,
        style: 'mapbox://styles/' + (window.MAPBOX_STYLE || 'mapbox/streets-v12'),
        center: [lng, lat],
        zoom: 16
    });

    viewMap.addControl(new mapboxgl.NavigationControl());

    viewMap.on('load', () => {
        new mapboxgl.Marker({ color: '#44b3ebff' }) 
            .setLngLat([lng, lat])
            .addTo(viewMap);

        viewMap.resize();
    });
}

document.addEventListener('DOMContentLoaded', () => {

    if (document.getElementById('liveMap')) {
        initFreeMap();
    }

    const detectBtn = document.getElementById('detectLocationBtn');
    if (detectBtn) {
        detectBtn.addEventListener('click', () => {
            detectUserLocation();
        });
    }

    const viewMapEl = document.getElementById('viewMap');
    if (viewMapEl) {
        initViewMap(viewMapEl);
    }
});

