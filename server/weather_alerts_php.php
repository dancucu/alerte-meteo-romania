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
 * Extrage mesaje specifice PER JUDEȚ din răspunsul API
 * Dacă se gaseste o relație de mesaj per județ, va suprascrie mesajul global
 */
function extractPerCountyMessages($htmlContent) {
    $perCountyMessages = [];
    
    // Pattern: cautam structuri unde avem cod județ + mesaj asociat
    // Ex: "judet":"BR","mesaj":"Atenție..." sau similar
    
    // Încercă pattern 1: mesaj direct în objeto județului
    preg_match_all('/"cod":"([A-Z]{2})"[^}]*"mesaj":"((?:[^"\\\\]|\\\\.)*)"/', $htmlContent, $matches, PREG_SET_ORDER);
    
    if (!empty($matches)) {
        foreach ($matches as $match) {
            $cod = $match[1];
            $mesaj = $match[2];
            // Curăță escape chars
            $mesaj = str_replace(['\/', '\"', '\\n', '\\r'], ['/', '"', ' ', ' '], $mesaj);
            $perCountyMessages[$cod] = $mesaj;
        }
    }
    
    return $perCountyMessages;
}

/**
 * Extrage date din răspuns - PE JUDEȚE (mesaje separate)
 */
function extractAlertData($data) {
    // Verifică dacă e fallback (HTML) sau API nou (JSON)
    if (isset($data['source']) && $data['source'] === 'fallback') {
        $htmlContent = $data['html'];
    } else {
        $htmlContent = json_encode($data);
    }
    
    $judeteData = [];
    $alertInfo = [];
    
    // Extrage mesaje per județ
    $perCountyMessages = extractPerCountyMessages($htmlContent);
    
    // Pattern pentru județe cu info completa
    preg_match_all('/"judet_obiect":\{[^}]*"cod":"([A-Z]{2})"[^}]*"culoare":"(\d+)"[^}]*"useCoordGis":"true","coordGis":"([^"]+)"[^}]*\}/', $htmlContent, $matches, PREG_SET_ORDER);
    
    if (empty($matches)) {
        // Fallback: pattern mai simplu
        preg_match_all('/"cod":"([A-Z]{2})","culoare":"(\d+)","useCoordGis":"true","coordGis":"([^"]+)"/', $htmlContent, $matches, PREG_SET_ORDER);
    }

    
    foreach ($matches as $match) {
        $cod = $match[1];
        $culoare = $match[2];
        $coordGis = $match[3];
        
        if (!isset($judeteData[$cod])) {
            // Verifică dacă avem mesaj specific pentru acest județ
            $countyMsg = $perCountyMessages[$cod] ?? '';
            
            $judeteData[$cod] = [
                'color_code' => $culoare,
                'coords_gis' => $coordGis,
                'message' => $countyMsg // Mesaj specific per județ (daca exista)
            ];
        }
    }
    
    // Extrage info GLOBALĂ 
    preg_match('/"numeTipMesaj":"([^"]+)"/', $htmlContent, $tipMesaj);
    preg_match('/"numeCuloare":"([^"]+)"/', $htmlContent, $culoareNume);
    preg_match('/"fenomeneVizate":"([^"]+)"/', $htmlContent, $fenomene);
    preg_match('/"dataAparitiei":"([^"]+)"/', $htmlContent, $dataAparitie);
    preg_match('/"dataExpir[^"]*":"([^"]+)"/', $htmlContent, $dataExpirare);
    
    // Încearcă să extrage mesajul GLOBAL
    preg_match('/"mesaj":"((?:[^"\\\\]|\\\\.)*)"/', $htmlContent, $mesaj);
    
    if (empty($mesaj[1])) {
        preg_match('/"descriereRo":"((?:[^"\\\\]|\\\\.)*)"/', $htmlContent, $descriere);
        $mesajHtml = !empty($descriere[1]) ? $descriere[1] : '';
    } else {
        $mesajHtml = $mesaj[1];
    }
    
    // Curăță escape characters
    if (!empty($mesajHtml)) {
        $mesajHtml = str_replace(['\/', '\"', '\\n', '\\r', '\n', '\r'], ['/', '"', ' ', ' ', ' ', ' '], $mesajHtml);
    }
    
    if (empty($mesajHtml) && !empty($fenomene[1])) {
        $nivel = !empty($culoareNume[1]) ? $culoareNume[1] : 'Galben';
        $mesajHtml = "Nivel: " . $nivel . "\nFenomene: " . $fenomene[1];
    }
    
    // Pentru județele care NU au mesaj specific, adaugă mesajul GLOBAL
    foreach (array_keys($judeteData) as $cod) {
        if (empty($judeteData[$cod]['message'])) {
            $judeteData[$cod]['message'] = $mesajHtml;
        }
    }
    
    // TODO: Pe viitor, parsam mesaji distincti pentru fiecare judet daca API le ofera
    
    return [
        'alert_count' => 1,
        'alert_info' => [
            'type' => $tipMesaj[1] ?? 'Atenționare meteorologică',
            'color_name' => $culoareNume[1] ?? 'galben',
            'phenomena' => $fenomene[1] ?? 'conform textelor și hărții',
            'start' => formatDateRO($dataAparitie[1] ?? ''),
            'end' => formatDateRO($dataExpirare[1] ?? ''),
            'message' => $mesajHtml,
        ],
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
        
        // Mesaj specific per județ (dacă există, altfel mesaj global)
        $countyMessage = $countyData['message'] ?? $alertInfo['message'] ?? '';
        
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'code' => $code,
                'color' => $colorInfo['color'],
                'alertLevel' => $colorInfo['name'],
                'alertType' => $alertInfo['type'] ?? '',
                'phenomena' => $alertInfo['phenomena'] ?? '',
                'start' => $alertInfo['start'] ?? '',
                'end' => $alertInfo['end'] ?? '',
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
                const popupContent = `
                    <div style="min-width: 320px; max-height: 450px; overflow-y: auto; font-family: Arial, sans-serif;">
                        <h3 style="color: #667eea; margin-bottom: 12px; border-bottom: 2px solid #667eea; padding-bottom: 8px;">📍 Județ: \${props.code}</h3>
                        
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                            <tr style="background: #f0f4ff;">
                                <td style="padding: 8px; font-weight: bold; color: #764ba2;">🚨 Nivel Alert:</td>
                                <td style="padding: 8px;">\${props.alertLevel}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold; color: #764ba2;">📋 Tip Alert:</td>
                                <td style="padding: 8px;">\${props.alertType}</td>
                            </tr>
                            <tr style="background: #f0f4ff;">
                                <td style="padding: 8px; font-weight: bold; color: #764ba2;">⚡ Fenomene:</td>
                                <td style="padding: 8px;">\${props.phenomena}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold; color: #764ba2;">⏰ Valabilitate:</td>
                                <td style="padding: 8px; font-size: 0.85em;">
                                    de la: \${props.start}<br>
                                    pana la: \${props.end}
                                </td>
                            </tr>
                        </table>
                        
                        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 12px; margin-top: 10px;">
                            <p style="margin: 0 0 8px 0; font-weight: bold; color: #856404;">📝 Mesaj Detaliat:</p>
                            <p style="margin: 0; font-size: 0.9em; line-height: 1.6; white-space: pre-wrap; word-break: break-word; color: #333;">\${props.message || 'Nu sunt detalii suplimentare'}</p>
                        </div>
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
