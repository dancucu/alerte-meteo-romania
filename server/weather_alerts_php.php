<?php
/**
 * Generator Hartă Avertizări Meteo România - Versiune PHP Pură
 * Fără dependențe Python - funcționează cu PHP 7.0+
 * © 2026 TazzStudio.ro
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for debugging
ini_set('max_execution_time', 120);

// Configurare căi
$baseDir = dirname(__FILE__);
$cacheFile = $baseDir . '/weather_cache.json';
$outputFile = $baseDir . '/index.html';
$logFile = $baseDir . '/weather_updates.log';

/**
 * Logging
 */
function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

/**
 * Convertește coordonate din Web Mercator (EPSG:3857) în WGS84 (EPSG:4326)
 */
function webMercatorToWGS84($x, $y) {
    $lon = $x / 20037508.34 * 180;
    $lat = $y / 20037508.34 * 180;
    $lat = 180 / M_PI * (2 * atan(exp($lat * M_PI / 180)) - M_PI / 2);
    return [$lon, $lat];
}

/**
 * Formează data în format uman pentru România
 * Input: "2026-02-14T10:00" -> Output: "14 februarie 2026, ora 10:00"
 */
function formatDateRO($dateString) {
    if (empty($dateString)) {
        return 'N/A';
    }
    
    // Fix data invalida ca "2026-02-16T80:00" (80:00 nu e valabil)
    $dateString = preg_replace('/T(\d{2}):/', 'T00:', $dateString); // Replace invalid hours with 00
    
    try {
        $date = DateTime::createFromFormat('Y-m-d\TH:i', $dateString);
        if ($date === false) {
            return $dateString;
        }
        
        $lunile = [
            1 => 'ianuarie', 2 => 'februarie', 3 => 'martie', 4 => 'aprilie',
            5 => 'mai', 6 => 'iunie', 7 => 'iulie', 8 => 'august',
            9 => 'septembrie', 10 => 'octombrie', 11 => 'noiembrie', 12 => 'decembrie'
        ];
        
        $day = $date->format('d');
        $month = $lunile[(int)$date->format('m')];
        $year = $date->format('Y');
        $time = $date->format('H:i');
        
        // Remove leading zero from day
        $day = ltrim($day, '0') ?: '0';
        
        return "$day $month $year, ora $time";
    } catch (Exception $e) {
        return $dateString;
    }
}

/**
 * Parse coordonate MULTIPOLYGON
 */
function parseMultipolygon($coordString) {
    if (empty($coordString)) {
        return [];
    }
    
    // Curăță string
    $coordString = str_replace('MULTIPOLYGON', '', $coordString);
    $coordString = str_replace(['(', ')'], '', $coordString);
    $coordString = trim($coordString);
    
    // Extrage coordonate - pattern pentru X Y
    preg_match_all('/(\d+\.?\d*)\s+(\d+\.?\d*)/', $coordString, $matches, PREG_SET_ORDER);
    
    if (empty($matches) || count($matches) < 3) {
        return [];
    }
    
    $polygon = [];
    $step = max(1, intval(count($matches) / 150)); // Max 150 puncte
    
    foreach ($matches as $i => $match) {
        // Păstrăm primul, ultimul și fiecare al N-lea punct
        if ($i % $step === 0 || $i === 0 || $i === count($matches) - 1) {
            $x = floatval($match[1]);
            $y = floatval($match[2]);
            
            // Convertim din Web Mercator în WGS84
            list($lon, $lat) = webMercatorToWGS84($x, $y);
            
            // Verifică că sunt în România
            if ($lat >= 43.0 && $lat <= 48.5 && $lon >= 20.0 && $lon <= 30.0) {
                $polygon[] = [$lon, $lat];
            }
        }
    }
    
    if (count($polygon) >= 3) {
        // Închide poligonul
        if ($polygon[0] !== $polygon[count($polygon) - 1]) {
            $polygon[] = $polygon[0];
        }
        return [$polygon];
    }
    
    return [];
}

/**
 * Descarcă alertele meteo cu fallback triplu
 */
function fetchWeatherAlerts($cacheFile, $logFile) {
    $apiUrl = "https://www.meteoromania.ro/wp-json/meteoapi/v2/avertizari-generale";
    $fallbackUrl = "https://tazzstudio.ro/avertizari-meteo.php";
    
    // NIVEL 1: API Oficial
    logMessage("🌐 [1/3] Încerc API oficial: $apiUrl", $logFile);
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        logMessage("✅ API oficial disponibil - cod 200", $logFile);
        $data = json_decode($response, true);
        if ($data) {
            file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            logMessage("💾 Date salvate în cache", $logFile);
            return $data;
        }
    }
    
    logMessage("⚠️ API oficial returnează cod $httpCode", $logFile);
    
    // NIVEL 2: Cache
    if (file_exists($cacheFile)) {
        logMessage("📂 [2/3] Încerc cache: $cacheFile", $logFile);
        $cacheContent = file_get_contents($cacheFile);
        $data = json_decode($cacheContent, true);
        if ($data) {
            logMessage("✅ Date încărcate din cache", $logFile);
            return $data;
        }
    }
    
    logMessage("⚠️ Nu există cache valid", $logFile);
    
    // NIVEL 3: API Fallback
    logMessage("🔄 [3/3] Încerc API fallback: $fallbackUrl", $logFile);
    
    $ch = curl_init($fallbackUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        logMessage("✅ API fallback disponibil", $logFile);
        $fallbackData = ['source' => 'fallback', 'html' => $response];
        file_put_contents($cacheFile, json_encode($fallbackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        logMessage("💾 Date fallback salvate în cache", $logFile);
        return $fallbackData;
    }
    
    logMessage("❌ Toate sursele sunt indisponibile", $logFile);
    throw new Exception("API indisponibil și fără cache valid");
}

/**
 * Parseaza API și extrage blocuri de alertă separate cu județele afectate
 * Returnează array cu alertele parseate: fiecare alert conține județele și mesajul specific
 */
function parseAlertsBlocks($htmlContent) {
    $alerts = [];
    
    // Regex pentru a separa blocuri de alertă
    // Pattern: "INFORMARE|ATENȚIONARE ... COD [GALBEN/PORTOCALIU/ROȘU] ... Interval ... Fenomene ... [Zone sau județe] ... [Mesaj]"
    
    // Split by major alert blocks - cauta "INFORMARE" sau "ATENȚIONARE"
    $blocks = preg_split(
        '/(?=(?:INFORMARE|ATENȚIONARE)\s+)/i',
        $htmlContent,
        -1,
        PREG_SPLIT_NO_EMPTY
    );
    
    $countyMap = [
        'Vaslui' => 'VS', 'Galați' => 'GL', 'Vrancea' => 'VN', 'Tulcea' => 'TL',
        'Constanța' => 'CT', 'Brăila' => 'BR', 'Ialomița' => 'IL', 'Călărași' => 'CL',
        'Giurgiu' => 'GR', 'Teleorman' => 'TR', 'Olt' => 'OT', 'Dolj' => 'DJ', 'Mehedinți' => 'MH',
        'Argeș' => 'AG', 'Dâmbovița' => 'DB', 'Prahova' => 'PH', 'Buzău' => 'BZ',
        'Hunedoara' => 'HD', 'Caraș-Severin' => 'CS', 'Timiș' => 'TM', 'Arad' => 'AR',
        'Bihor' => 'BH', 'Satu Mare' => 'SM', 'Maramureș' => 'MM', 'Harghita' => 'HR',
        'Mureș' => 'MS', 'Sibiu' => 'SB', 'Alba' => 'AB', 'Brașov' => 'BV',
        'Suceava' => 'SV', 'Botoșani' => 'BT', 'Iași' => 'IS', 'Neamț' => 'NT',
        'Bacău' => 'BC', 'Gorj' => 'GJ', 'Bistrița-Năsăud' => 'BN', 'Covasna' => 'CV',
        'Cluj' => 'CJ', 'Bucharest' => 'B', 'Ilfov' => 'IF', 'Timiș' => 'TM'
    ];
    
    $zoneMap = [
        'Dobrogea' => ['CT', 'TL'],
        'estul Munteniei' => ['BR', 'GL', 'VN', 'BZ'],
        'estul României' => ['VS', 'GL', 'VN', 'TL', 'CT', 'BR', 'BZ', 'BT', 'IS'],
        'muntenia' => ['AG', 'DB', 'PH', 'BZ', 'IL', 'CL', 'GR', 'TR', 'BR'],
        'bucurești' => ['B', 'IF'],
        'zona de munte' => ['AG', 'DB', 'BV', 'SB', 'AB', 'BN', 'HD', 'CS'],
    ];
    
    foreach ($blocks as $blockIdx => $block) {
        $alert = [
            'type' => '',
            'code' => '',
            'interval' => '',
            'phenomena' => '',
            'zones' => '',
            'message' => '',
            'counties' => [],
            'alertInfo' => []
        ];
        
        // Extrage tipul alertei
        if (preg_match('/^(INFORMARE|ATENȚIONARE)\s+METEOROLOGICĂ/i', $block, $typeMatch)) {
            $alert['type'] = $typeMatch[1];
        }
        
        // Extrage codul de culoare
        if (preg_match('/COD\s+(GALBEN|PORTOCALIU|ROȘU)/i', $block, $codeMatch)) {
            $alert['code'] = $codeMatch[1];
        }
        
        // Extrage interval
        if (preg_match('/Interval de valabilitate:\s*([^\n]*?(?:\d{1,2}\s+\w+,\s+ora\s+\d{2}:\d{2}[^,]*)+)/i', 
            $block, $intervalMatch)) {
            $alert['interval'] = trim($intervalMatch[1]);
        }
        
        // Extrage fenomene
        if (preg_match('/Fenomene vizate:\s*([^\n]*?)(?=Zone afectate:|Mesaj:|În interval|$)/is', 
            $block, $phenoMatch)) {
            $alert['phenomena'] = trim($phenoMatch[1]);
        }
        
        // Extrage zone afectate
        if (preg_match('/Zone afectate:\s*([^\n]*?)(?=Mesaj:|În interval|$)/is', 
            $block, $zoneMatch)) {
            $alert['zones'] = trim($zoneMatch[1]);
        }
        
        // Extrage mesajul principal și counties from "în județele X, Y, Z..."
        if (preg_match('/În intervalul menționat,?\s+în\s+județele\s+([^,]*(?:,\s*[^,]+)*?)\s*,?\s+(.+?)(?=În interval|Mesaj:|$)/is', 
            $block, $messageMatch)) {
            
            // Parse counties
            $countiesStr = $messageMatch[1];
            $countiesStr = str_replace(' și ', ',', $countiesStr);
            $countiesArray = array_map('trim', explode(',', $countiesStr));
            
            $message = trim($messageMatch[2]);
            $message = preg_replace('/\n\s+/', ' ', $message); // Curăță line breaks
            
            $alert['message'] = $message;
            
            // Map county names to codes
            foreach ($countiesArray as $countyName) {
                $countyName = trim($countyName);
                if (isset($countyMap[$countyName])) {
                    $alert['counties'][] = $countyMap[$countyName];
                }
            }
        }
        
        // Dacă nu s-au găsit județe specifice, încearcă din "Zone afectate"
        if (empty($alert['counties']) && !empty($alert['zones'])) {
            preg_match_all(
                '/(' . implode('|', array_keys($zoneMap)) . ')/i',
                $alert['zones'],
                $zoneMatches
            );
            
            if (!empty($zoneMatches[0])) {
                foreach ($zoneMatches[0] as $zone) {
                    $zoneLower = strtolower($zone);
                    if (isset($zoneMap[$zoneLower])) {
                        $alert['counties'] = array_merge($alert['counties'], $zoneMap[$zoneLower]);
                    }
                }
                $alert['counties'] = array_unique($alert['counties']);
            }
        }
        
        // Extrage info generale din bloc
        $alert['alertInfo'] = [
            'type' => $alert['type'],
            'code' => $alert['code'],
            'interval' => $alert['interval'],
            'phenomena' => $alert['phenomena'],
            'zones' => $alert['zones'],
        ];
        
        if (!empty($alert['interval']) || !empty($alert['phenomena']) || !empty($alert['counties'])) {
            $alerts[] = $alert;
        }
    }
    
    return $alerts;
}

/**
 * Extrage date din răspuns - IMPROVED: Parsez alert blocks separate cu județe específice
 */
function extractAlertData($data) {
    // Verifică dacă e fallback (HTML) sau API nou (JSON)
    if (isset($data['source']) && $data['source'] === 'fallback') {
        $htmlContent = $data['html'];
    } else {
        $htmlContent = json_encode($data);
    }
    
    // Parse toate blocurile de alertă separat
    $alertsBlocks = parseAlertsBlocks($htmlContent);
    
    $judeteData = [];
    $alertInfo = [];
    
    // STEP 1: Parse județe din răspundere (standard pattern - harta counties cu culori)
    preg_match_all('/"cod":"([A-Z]{2})","culoare":"(\d+)","useCoordGis":"true","coordGis":"([^"]+)"/', $htmlContent, $matches, PREG_SET_ORDER);
    
    if (empty($matches)) {
        logMessage("⚠️ Nu s-au găsit județe în răspuns", isset($logFile) ? $logFile : dirname(__FILE__) . '/weather_updates.log');
        return [
            'alert_count' => 0,
            'alert_info' => [],
            'counties' => []
        ];
    }
    
    // Inițializează toate județele
    foreach ($matches as $match) {
        $cod = $match[1];
        $culoare = $match[2];
        $coordGis = $match[3];
        
        if (!isset($judeteData[$cod])) {
            $judeteData[$cod] = [
                'color_code' => $culoare,
                'coords_gis' => $coordGis,
                'message' => '',
                'phenomena' => '',
                'alert_type' => '',
                'alert_code' => '',
                'interval' => '',
            ];
        }
    }
    
    // STEP 2: Assign mesajele din alertsBlocks DOAR la județele afectate
    foreach ($alertsBlocks as $alertBlock) {
        // Pentru fiecare județ în această alertă
        foreach ($alertBlock['counties'] as $countyCode) {
            if (isset($judeteData[$countyCode])) {
                // Construiește mesajul complet
                $message = $alertBlock['message'];
                if (!empty($alertBlock['phenomena'])) {
                    $message = $alertBlock['phenomena'] . (empty($message) ? '' : "\n\n" . $message);
                }
                
                $judeteData[$countyCode]['message'] = $message;
                $judeteData[$countyCode]['phenomena'] = $alertBlock['phenomena'];
                $judeteData[$countyCode]['alert_type'] = $alertBlock['type'];
                $judeteData[$countyCode]['alert_code'] = $alertBlock['code'];
                $judeteData[$countyCode]['interval'] = $alertBlock['interval'];
            }
        }
    }
    
    // STEP 3: Extrage info GLOBALĂ (pentru popups)
    preg_match('/COD\s+([A-Z]+)/i', $htmlContent, $codeMatch);
    $colorName = !empty($codeMatch[1]) ? strtolower($codeMatch[1]) : 'galben';
    
    // Extrage fenomene GLOBALE
    preg_match('/Fenomene vizate:\s*([^Z]*?)(?=Zone afectate:|Interval|$)/is', $htmlContent, $fenMatch);
    $fenomene = !empty($fenMatch[1]) ? trim($fenMatch[1]) : 'conform textelor și hărții';
    
    // Tipul alertei (din prima alertă cu ATENȚIONARE)
    $typeName = 'Atenționare meteorologică';
    if (preg_match('/INFORMARE/i', $htmlContent)) {
        $typeName = 'Informare meteorologică';
    }
    if (strpos(strtoupper($htmlContent), 'NOWCASTING') !== false) {
        $typeName = 'Atenționare nowcasting';
    }
    
    // Parse interval global (prima ATENȚIONARE)
    preg_match('/Interval de valabilitate:\s*([^\n]*?(?:\d{1,2}\s+\w+,\s+ora\s+\d{2}:\d{2}[^,]*)+)/i', 
        $htmlContent, $intervalMatch);
    $intervalText = !empty($intervalMatch[1]) ? trim($intervalMatch[1]) : '';
    
    // Parsez din interval: data start - data end
    $dateStart = 'N/A';
    $dateEnd = 'N/A';
    
    if (!empty($intervalText)) {
        // Try format: "15 februarie, ora 14:00 – 16 februarie, ora 08:00"
        if (preg_match('/(\d+)\s+(\w+),\s+ora\s+(\d+:\d+)\s*(?:–|-)\s*(\d+)\s+(\w+),\s+ora\s+(\d+:\d+)/', $intervalText, $dateMatch)) {
            $startDay = $dateMatch[1];
            $startMonth = $dateMatch[2]; // "februarie"
            $startTime = $dateMatch[3]; // "14:00"
            
            $endDay = $dateMatch[4];
            $endMonth = $dateMatch[5];
            $endTime = $dateMatch[6];
            
            $monthMap = [
                'ianuarie'=>'01', 'februarie'=>'02', 'martie'=>'03', 'aprilie'=>'04',
                'mai'=>'05', 'iunie'=>'06', 'iulie'=>'07', 'august'=>'08',
                'septembrie'=>'09', 'octombrie'=>'10', 'noiembrie'=>'11', 'decembrie'=>'12'
            ];
            
            $startMonthNum = isset($monthMap[strtolower($startMonth)]) ? $monthMap[strtolower($startMonth)] : '02';
            $endMonthNum = isset($monthMap[strtolower($endMonth)]) ? $monthMap[strtolower($endMonth)] : '02';
            
            $dateStart = formatDateRO("2026-$startMonthNum-$startDay" . "T" . $startTime);
            $dateEnd = formatDateRO("2026-$endMonthNum-$endDay" . "T" . $endTime);
        }
    }
    
    $alertInfo = [
        'type' => $typeName,
        'color_name' => $colorName,
        'phenomena' => $fenomene,
        'start' => $dateStart,
        'end' => $dateEnd,
    ];
    
    return [
        'alert_count' => count($alertsBlocks),
        'alert_info' => $alertInfo,
        'counties' => $judeteData
    ];
}

/**
 * Creează HTML-ul hărții
 */
function createMapHTML($alertsData) {
    if (empty($alertsData['counties'])) {
        return null;
    }
    
    $alertInfo = $alertsData['alert_info'];
    
    $colorMap = [
        '0' => ['color' => '#90EE90', 'name' => 'Verde (Fără alertă)'],
        '1' => ['color' => '#FFD700', 'name' => 'Galben'],
        '2' => ['color' => '#FFA500', 'name' => 'Portocaliu'],
        '3' => ['color' => '#FF0000', 'name' => 'Roșu'],
    ];
    
    $features = [];
    
    foreach ($alertsData['counties'] as $code => $countyData) {
        $colorCode = $countyData['color_code'] ?? '0';
        $coordsGis = $countyData['coords_gis'] ?? '';
        
        // Skip verde
        if ($colorCode === '0' || empty($coordsGis)) {
            continue;
        }
        
        $polygons = parseMultipolygon($coordsGis);
        
        if (empty($polygons) || count($polygons[0]) < 3) {
            continue;
        }
        
        $colorInfo = $colorMap[$colorCode] ?? $colorMap['0'];
        
        // Mesaj specific per județ (dacă există)
        $countyMessage = $countyData['message'] ?? '';
        
        // Alert type si code: per-county dacă existază, altfel global
        $alertType = !empty($countyData['alert_type']) ? $countyData['alert_type'] : ($alertInfo['type'] ?? '');
        $alertCode = !empty($countyData['alert_code']) ? $countyData['alert_code'] : ($alertInfo['color_name'] ?? 'necunoscut');
        $phenomena = !empty($countyData['phenomena']) ? $countyData['phenomena'] : ($alertInfo['phenomena'] ?? '');
        $interval = !empty($countyData['interval']) ? $countyData['interval'] : '';
        $dateStart = !empty($interval) ? $interval : ($alertInfo['start'] ?? '');
        $dateEnd = $alertInfo['end'] ?? '';
        
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'code' => $code,
                'color' => $colorInfo['color'],
                'alertLevel' => $colorInfo['name'],
                'alertType' => $alertType,
                'alertCode' => $alertCode,
                'phenomena' => $phenomena,
                'interval' => $interval,
                'start' => $dateStart,
                'end' => $dateEnd,
                'message' => $countyMessage, // Mesaj per județ
            ],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [$polygons[0]]
            ]
        ];
    }
    
    $geojson = [
        'type' => 'FeatureCollection',
        'features' => $features
    ];
    
    $currentTime = date('d.m.Y H:i');
    $geojsonStr = json_encode($geojson, JSON_UNESCAPED_UNICODE);
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌦️ Alertă Meteo România - {$alertInfo['type']}</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .alert-info {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }
        #map { width: 100%; height: 600px; }
        .footer {
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            color: #666;
            font-size: 0.9em;
        }
        .update-time { margin-top: 10px; font-size: 0.9em; opacity: 0.9; }
        a { color: #667eea; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌦️ Alertă Meteo România</h1>
            <div class="alert-info">
                <h2>{$alertInfo['type']}</h2>
                <p><strong>Fenomene:</strong> {$alertInfo['phenomena']}</p>
                <p><strong>Valabilitate:</strong> {$alertInfo['start']} - {$alertInfo['end']}</p>
                <p class="update-time">📅 Actualizat: $currentTime</p>
            </div>
        </div>
        
        <div id="map"></div>
        
        <div class="footer">
            <p>📊 Sursă date: Administrația Națională de Meteorologie (ANM)</p>
            <p>🔄 Actualizare automată la fiecare 5 minute</p>
            <p>© 2026 <a href="https://tazzstudio.ro" target="_blank">TazzStudio.ro</a> | 
            <a href="https://github.com/dancucu/alerte-meteo-romania" target="_blank">GitHub</a></p>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const geojsonData = $geojsonStr;
        
        const map = L.map('map').setView([45.9432, 24.9668], 7);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        L.geoJSON(geojsonData, {
            style: function(feature) {
                return {
                    fillColor: feature.properties.color,
                    weight: 2,
                    opacity: 1,
                    color: '#333',
                    fillOpacity: 0.6
                };
            },
            onEachFeature: function(feature, layer) {
                const props = feature.properties;
                
                // Construiește valabilitate - dacă avem interval per-county, nu mai arătăm start/end repetate
                let validityHTML = '';
                if (props.interval) {
                    validityHTML = `<td style="padding: 8px; font-size: 0.85em;">\${props.interval}</td>`;
                } else {
                    validityHTML = `<td style="padding: 8px; font-size: 0.85em;">
                        \${props.start ? 'de la: \${props.start}<br>' : ''}
                        \${props.end ? 'pana la: \${props.end}' : 'N/A'}
                    </td>`;
                }
                
                const popupContent = `
                    <div style="min-width: 320px; max-height: 500px; overflow-y: auto; font-family: Arial, sans-serif;">
                        <h3 style="color: #667eea; margin-bottom: 12px; border-bottom: 2px solid #667eea; padding-bottom: 8px;">📍 Județ: \${props.code}</h3>
                        
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                            <tr style="background: #f0f4ff;">
                                <td style="padding: 8px; font-weight: bold; color: #764ba2; width: 40%;">🚨 Nivel:</td>
                                <td style="padding: 8px;">\${props.alertLevel || 'N/A'}</td>
                            </tr>
                            \${props.alertCode ? `<tr>
                                <td style="padding: 8px; font-weight: bold; color: #764ba2;">📌 Cod:</td>
                                <td style="padding: 8px;">COD \${props.alertCode.toUpperCase()}</td>
                            </tr>` : ''}
                            <tr style="background: #f0f4ff;">
                                <td style="padding: 8px; font-weight: bold; color: #764ba2;">📋 Tip Alert:</td>
                                <td style="padding: 8px;">\${props.alertType || 'N/A'}</td>
                            </tr>
                            \${props.phenomena ? `<tr>
                                <td style="padding: 8px; font-weight: bold; color: #764ba2;">⚡ Fenomene:</td>
                                <td style="padding: 8px;">\${props.phenomena}</td>
                            </tr>` : ''}
                            <tr style="background: #f0f4ff;">
                                <td style="padding: 8px; font-weight: bold; color: #764ba2;">⏰ Valabilitate:</td>
                                \${validityHTML}
                            </tr>
                        </table>
                        
                        \${props.message ? `<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 12px; margin-top: 10px;">
                            <p style="margin: 0 0 8px 0; font-weight: bold; color: #856404;">📝 Situația pentru acest județ:</p>
                            <p style="margin: 0; font-size: 0.9em; line-height: 1.6; white-space: pre-wrap; word-break: break-word; color: #333;">\${props.message}</p>
                        </div>` : '<p style="color: #666; font-size: 0.9em;">Nu sunt detalii suplimentare pentru acest județ.</p>'}
                    </div>
                `;
                layer.bindPopup(popupContent, {maxWidth: 500, maxHeight: 500});
                
                // Hover effect
                layer.on('mouseover', function() {
                    this.setStyle({
                        weight: 3,
                        fillOpacity: 0.85,
                        color: '#000'
                    });
                    this.bringToFront();
                });
                
                layer.on('mouseout', function() {
                    this.setStyle({
                        weight: 2,
                        fillOpacity: 0.6,
                        color: '#333'
                    });
                });
            }
        }).addTo(map);
    </script>
</body>
</html>
HTML;
    
    return $html;
}

/**
 * MAIN
 */
try {
    // Debug: Log execution source
    $source = '';
    if (php_sapi_name() === 'cli') {
        $source = "CLI/CRON-JOB";
    } else {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        if (strpos($userAgent, 'cron-job') !== false || strpos($userAgent, 'Bot') !== false) {
            $source = "CRON-JOB.ORG";
        } else {
            $source = "Manual/Browser";
        }
        $source .= " (IP: $remoteIp, UA: $userAgent)";
    }
    
    logMessage("============================================================", $logFile);
    logMessage("🌦️ START - Generator Hartă Alertă Meteo (PHP Pure Version)", $logFile);
    logMessage("📡 Source: $source", $logFile);
    
    // Descarcă date
    $data = fetchWeatherAlerts($cacheFile, $logFile);
    logMessage("✅ Date descărcate cu succes", $logFile);
    
    // Extrage alertele
    $alertsData = extractAlertData($data);
    logMessage("📊 Găsite " . count($alertsData['counties']) . " județe", $logFile);
    
    // Generează HTML
    $html = createMapHTML($alertsData);
    
    if ($html) {
        file_put_contents($outputFile, $html);
        $fileSize = filesize($outputFile) / 1024;
        logMessage("✅ Hartă generată: $outputFile (" . number_format($fileSize, 1) . " KB)", $logFile);
        logMessage("🌦️ SUCCESS - Hartă actualizată cu succes", $logFile);
        exit(0);
    } else {
        logMessage("❌ Eroare la generarea hărții", $logFile);
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("❌ EROARE: " . $e->getMessage(), $logFile);
    logMessage("Stack trace: " . $e->getTraceAsString(), $logFile);
    exit(1);
} finally {
    logMessage("============================================================", $logFile);
}
