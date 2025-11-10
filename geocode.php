<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$hasLat = isset($_GET['lat']) && $_GET['lat'] !== '';
$hasLon = isset($_GET['lon']) && $_GET['lon'] !== '';
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

try {
    // Reverse geocoding branch: lat+lon -> label
    if ($hasLat && $hasLon) {
        $lat = (float)$_GET['lat'];
        $lon = (float)$_GET['lon'];
        if ($GEO_PROVIDER === 'locationiq' && $GEO_API_KEY !== '') {
            $url = 'https://us1.locationiq.com/v1/reverse?'
                 . http_build_query(['key' => $GEO_API_KEY, 'lat' => $lat, 'lon' => $lon, 'format' => 'json']);
            $resp = @file_get_contents($url);
            if ($resp === false) throw new RuntimeException('reverse request failed');
            $data = json_decode($resp, true);
            $label = $data['display_name'] ?? '';
            echo json_encode(['label' => $label, 'lat' => $lat, 'lon' => $lon]);
            exit;
        }

        // Fallback: Nominatim reverse
        $url = 'https://nominatim.openstreetmap.org/reverse?'
             . http_build_query(['lat' => $lat, 'lon' => $lon, 'format' => 'json']);
        $opts = ['http' => ['header' => "User-Agent: NoStarve/1.0\r\n"]];
        $resp = @file_get_contents($url, false, stream_context_create($opts));
        if ($resp === false) throw new RuntimeException('reverse request failed');
        $data = json_decode($resp, true);
        $label = $data['display_name'] ?? '';
        echo json_encode(['label' => $label, 'lat' => $lat, 'lon' => $lon]);
        exit;
    }

    // Forward geocoding branch: q -> suggestions
    if ($q === '') {
        echo json_encode(['error' => 'missing q']);
        exit;
    }

    if ($GEO_PROVIDER === 'locationiq' && $GEO_API_KEY !== '') {
        $url = 'https://api.locationiq.com/v1/autocomplete.php?'
             . http_build_query(['key' => $GEO_API_KEY, 'q' => $q, 'limit' => 5]);
        $resp = @file_get_contents($url);
        if ($resp === false) throw new RuntimeException('geocode request failed');
        $data = json_decode($resp, true);
        $results = [];
        foreach (($data ?? []) as $item) {
            $results[] = [
                'label' => $item['display_name'] ?? ($item['address'] ?? ''),
                'lat' => isset($item['lat']) ? (float)$item['lat'] : null,
                'lon' => isset($item['lon']) ? (float)$item['lon'] : null,
            ];
        }
        echo json_encode(['results' => $results]);
        exit;
    }

    // Fallback: OpenStreetMap Nominatim (no key). Respect usage policy; light usage only.
    $url = 'https://nominatim.openstreetmap.org/search?'
         . http_build_query(['q' => $q, 'format' => 'json', 'limit' => 5, 'addressdetails' => 0]);
    $opts = ['http' => ['header' => "User-Agent: NoStarve/1.0\r\n"]];
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    if ($resp === false) throw new RuntimeException('geocode request failed');
    $data = json_decode($resp, true);
    $results = [];
    foreach (($data ?? []) as $item) {
        $results[] = [
            'label' => $item['display_name'] ?? '',
            'lat' => isset($item['lat']) ? (float)$item['lat'] : null,
            'lon' => isset($item['lon']) ? (float)$item['lon'] : null,
        ];
    }
    echo json_encode(['results' => $results]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}