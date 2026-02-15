<?php
// avertizari-meteo.php
header('Content-Type: application/json; charset=utf-8');

// CONFIG
$apiUrl    = 'https://www.meteoromania.ro/wp-json/meteoapi/v2/avertizari-generale';
$cacheFile = __DIR__ . '/cache-avertizari-meteo.json';
// poți folosi $cacheTtl dacă vrei să ignori cache-ul mai vechi de X secunde
$cacheTtl  = 300;

// Parametru optional: county=GL (cod judet, ex: GL, CT, B, etc.)
$county = isset($_GET['county']) ? strtoupper(trim($_GET['county'])) : null;

// Helper: trimite răspuns JSON și oprește scriptul
function send_json($data, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Citim cache-ul (fallback)
$cachedData = null;
$hasCache   = false;

if (file_exists($cacheFile)) {
    $rawCache = file_get_contents($cacheFile);
    if ($rawCache !== false) {
        $decoded = json_decode($rawCache, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $cachedData = $decoded;
            $hasCache   = true;
        }
    }
}

// 2. Incearcăm date live de la API
$data = null;

if (!function_exists('curl_init')) {
    // Nu avem cURL – folosim cache dacă există
    if ($hasCache) {
        $cachedData['_source'] = 'cache';
        $cachedData['_refresh_error'] = 'curl_not_available';
        $data = $cachedData;
    } else {
        send_json([
            'alert_count' => 0,
            'alerts'      => [],
            'error'       => 'curl_not_available',
            'http_code'   => null,
            '_source'     => 'none'
        ], 500);
    }
} else {
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'TazzStudioProxy/1.0',
    ]);
    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);

        // Eroare cURL – dacă avem cache, îl folosim
        if ($hasCache) {
            $cachedData['_source'] = 'cache';
            $cachedData['_refresh_error'] = 'curl_error: ' . $err;
            $data = $cachedData;
        } else {
            send_json([
                'alert_count' => 0,
                'alerts'      => [],
                'error'       => 'curl_error',
                'details'     => $err,
                'http_code'   => null,
                '_source'     => 'none'
            ], 500);
        }
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // HTTP != 2xx → fallback la cache, dacă există
        if ($httpCode < 200 || $httpCode >= 300) {
            if ($hasCache) {
                $cachedData['_source'] = 'cache';
                $cachedData['_refresh_error'] = 'http_error: ' . $httpCode;
                $data = $cachedData;
            } else {
                send_json([
                    'alert_count' => 0,
                    'alerts'      => [],
                    'error'       => 'http_error',
                    'http_code'   => $httpCode,
                    '_source'     => 'none'
                ], 500);
            }
        } else {
            // HTTP 2xx – încercăm să decodăm JSON-ul de la Meteo
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                if ($hasCache) {
                    $cachedData['_source'] = 'cache';
                    $cachedData['_refresh_error'] = 'json_decode_error';
                    $data = $cachedData;
                } else {
                    send_json([
                        'alert_count' => 0,
                        'alerts'      => [],
                        'error'       => 'json_decode_error',
                        'http_code'   => $httpCode,
                        '_source'     => 'none',
                        'raw'         => $response
                    ], 500);
                }
            } else {
                // AICI API-ul raspunde bine: salvăm structura completă în cache
                // Presupunem că $decoded este lista/structura principală de avertizari
                $alertCount = is_array($decoded) ? count($decoded) : 0;

                $result = [
                    'alert_count' => $alertCount,
                    'alerts'      => $decoded,
                    'error'       => null,
                    'http_code'   => $httpCode,
                    '_source'     => 'live'
                ];

                @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
                $data = $result;
            }
        }
    }
}

// Daca, din orice motiv, nu s-a setat $data, folosim cache-ul sau un fallback simplu
if (!isset($data)) {
    if ($hasCache) {
        $data = $cachedData;
        $data['_source'] = 'cache';
    } else {
        $data = [
            'alert_count' => 0,
            'alerts'      => [],
            'error'       => 'no_data',
            'http_code'   => null,
            '_source'     => 'none'
        ];
    }
}

// 3. Daca nu avem parametrul county, returnam toata structura
if ($county === null || $county === '') {
    send_json($data, 200);
}

// 4. Avem county=GL/CT/etc. → filtram doar pe judetul respectiv
$alerts = $data['alerts'] ?? [];
$matches = [];

// Parcurgem recursiv alerts si cautam nodurile cu @attributes.cod == $county
$iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($alerts));

foreach ($iterator as $key => $value) {
    if ($key === '@attributes' && is_array($value)) {
        if (isset($value['cod']) && strtoupper($value['cod']) === $county) {
            // Aici avem toate atributele pentru judet:
            // cod, culoare, useCoordGis, coordGis etc.
            $matches[] = $value;
        }
    }
}

$resultCounty = [
    'county'       => $county,
    'match_count'  => count($matches),
    'matches'      => $matches,
    'error'        => $data['error'] ?? null,
    'http_code'    => $data['http_code'] ?? null,
    '_source'      => $data['_source'] ?? 'unknown',
    '_refresh_err' => $data['_refresh_error'] ?? null,
];

send_json($resultCounty, 200);
