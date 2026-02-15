<?php
/**
 * Script DEBUG - arată exact ce extrage din API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include funcțiile din scriptul principal
require_once __DIR__ . '/weather_alerts_php.php';

echo "<html><head><meta charset='UTF-8'><style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.block { background: white; margin: 20px 0; padding: 15px; border-left: 5px solid #667eea; }
.atentionare { border-left-color: #ff0000; }
.informare { border-left-color: #ffa500; }
h2 { margin-top: 0; }
pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
.counties { background: #d4edda; padding: 10px; margin: 10px 0; }
.no-counties { background: #f8d7da; padding: 10px; margin: 10px 0; }
</style></head><body>";

echo "<h1>🔍 DEBUG - Parsare Alerte Meteo</h1>";

// Încearcă să citești cache-ul
$cacheFile = __DIR__ . '/weather_cache.json';

if (!file_exists($cacheFile)) {
    echo "<p style='color: red;'>❌ Nu există cache local. Încerc să descarc de pe server...</p>";
    
    // Încearcă să descarci de pe server
    $serverCache = 'https://tazzstudio.ro/alerte-meteo/weather_cache.json';
    $content = @file_get_contents($serverCache);
    
    if ($content) {
        file_put_contents($cacheFile, $content);
        echo "<p style='color: green;'>✅ Cache descărcat de pe server</p>";
    } else {
        echo "<p style='color: red;'>❌ Nu se poate accesa cache-ul de pe server. Copiază manual weather_cache.json în directorul local.</p>";
        exit;
    }
}

echo "<p>📂 Citesc cache: <code>$cacheFile</code></p>";

$cacheContent = file_get_contents($cacheFile);
$data = json_decode($cacheContent, true);

if (!$data) {
    echo "<p style='color: red;'>❌ Eroare la parsarea JSON</p>";
    exit;
}

// Verifică tipul de date
if (isset($data['source']) && $data['source'] === 'fallback') {
    $htmlContent = $data['html'];
    echo "<p>📡 Sursă: <strong>Fallback API (HTML)</strong></p>";
} else {
    $htmlContent = json_encode($data);
    echo "<p>📡 Sursă: <strong>API Oficial (JSON)</strong></p>";
}

echo "<hr>";
echo "<h2>📋 Blocuri de Alertă Parseate</h2>";

// Split by major alert blocks
$blocks = preg_split(
    '/(?=(?:INFORMARE|ATENȚIONARE)\s+)/i',
    $htmlContent,
    -1,
    PREG_SPLIT_NO_EMPTY
);

echo "<p>Total blocuri găsite: <strong>" . count($blocks) . "</strong></p>";

$countyMap = [
    'Vaslui' => 'VS', 'Galați' => 'GL', 'Vrancea' => 'VN', 'Tulcea' => 'TL',
    'Constanța' => 'CT', 'Brăila' => 'BR', 'Ialomița' => 'IL', 'Călărași' => 'CL',
    'Giurgiu' => 'GR', 'Teleorman' => 'TR', 'Olt' => 'OT', 'Dolj' => 'DJ', 
    'Mehedinți' => 'MH', 'Argeș' => 'AG', 'Dâmbovița' => 'DB', 'Prahova' => 'PH', 
    'Buzău' => 'BZ', 'Hunedoara' => 'HD', 'Caraș-Severin' => 'CS', 'Timiș' => 'TM', 
    'Arad' => 'AR', 'Bihor' => 'BH', 'Satu Mare' => 'SM', 'Maramureș' => 'MM', 
    'Harghita' => 'HR', 'Mureș' => 'MS', 'Sibiu' => 'SB', 'Alba' => 'AB', 
    'Brașov' => 'BV', 'Suceava' => 'SV', 'Botoșani' => 'BT', 'Iași' => 'IS', 
    'Neamț' => 'NT', 'Bacău' => 'BC', 'Gorj' => 'GJ', 'Bistrița-Năsăud' => 'BN', 
    'Covasna' => 'CV', 'Cluj' => 'CJ', 'Bucharest' => 'B', 'Ilfov' => 'IF'
];

foreach ($blocks as $idx => $block) {
    // Extrage tip
    $type = '';
    if (preg_match('/^(INFORMARE|ATENȚIONARE)\s+METEOROLOGICĂ/i', $block, $typeMatch)) {
        $type = $typeMatch[1];
    }
    
    if (empty($type)) {
        continue;
    }
    
    $class = strtolower($type);
    
    echo "<div class='block $class'>";
    echo "<h2>📌 BLOC #$idx: $type</h2>";
    
    // Extrage cod
    if (preg_match('/COD\s+(GALBEN|PORTOCALIU|ROȘU)/i', $block, $codeMatch)) {
        echo "<p>🎨 <strong>Cod:</strong> {$codeMatch[1]}</p>";
    }
    
    // Extrage fenomene
    if (preg_match('/Fenomene vizate:\s*([^\n]*?)(?=Zone afectate:|Mesaj:|În interval|$)/is', $block, $phenoMatch)) {
        echo "<p>⚡ <strong>Fenomene:</strong> " . htmlspecialchars(trim($phenoMatch[1])) . "</p>";
    }
    
    // Extrage zone
    if (preg_match('/Zone afectate:\s*([^\n]*?)(?=Mesaj:|În interval|$)/is', $block, $zoneMatch)) {
        echo "<p>🗺️ <strong>Zone afectate:</strong> " . htmlspecialchars(trim($zoneMatch[1])) . "</p>";
    }
    
    // CAUTĂ JUDEȚE cu regex-uri multiple
    $countyPatterns = [
        '/(?:în|În)\s+(?:(?:zona\s+(?:joasă|de\s+munte)\s+)?a\s+)?(?:județele|județe)?\s*([^.!]*?)(?=\s+(?:temporar|vor|va|și|se|treptat|izolat|cu|conform|pentru))/i',
        '/(?:în|În)\s+([^.!]*?(?:și[^.!]*?)?)(?=\s+(?:temporar|vor|va|conform|pentru|se\s+va|va\s+fi))/i',
    ];
    
    $foundCounties = [];
    
    foreach ($countyPatterns as $patternIdx => $pattern) {
        if (preg_match($pattern, $block, $match)) {
            echo "<p>🔍 <strong>Pattern #$patternIdx MATCH:</strong> <code>" . htmlspecialchars($match[0]) . "</code></p>";
            echo "<p>📝 <strong>Județe extrase (raw):</strong> <code>" . htmlspecialchars($match[1]) . "</code></p>";
            
            // Parse counties
            $countiesStr = $match[1];
            $countiesStr = str_replace([' și ', ' si '], ',', $countiesStr);
            $countiesArray = array_map('trim', explode(',', $countiesStr));
            
            echo "<p>🔨 <strong>După split:</strong> " . implode(' | ', array_map('htmlspecialchars', $countiesArray)) . "</p>";
            
            // Map to codes
            foreach ($countiesArray as $countyName) {
                $countyName = trim($countyName);
                $countyName = preg_replace('/^\s*o\s+zonă\s+a\s+/', '', $countyName);
                
                if (!empty($countyName)) {
                    if (isset($countyMap[$countyName])) {
                        $foundCounties[] = $countyMap[$countyName];
                        echo "<span style='background: #d4edda; padding: 2px 5px; margin: 2px; display: inline-block;'>✅ $countyName → {$countyMap[$countyName]}</span> ";
                    } else {
                        echo "<span style='background: #f8d7da; padding: 2px 5px; margin: 2px; display: inline-block;'>❌ $countyName (nu e în map)</span> ";
                    }
                }
            }
            
            if (!empty($foundCounties)) {
                break;
            }
        }
    }
    
    if (!empty($foundCounties)) {
        echo "<div class='counties'><strong>✅ JUDEȚE IDENTIFICATE:</strong> " . implode(', ', $foundCounties) . "</div>";
    } else {
        echo "<div class='no-counties'><strong>❌ NU S-AU GĂSIT JUDEȚE SPECIFICE</strong></div>";
    }
    
    // Arată primele 500 caractere din bloc pentru context
    echo "<details><summary>📄 Vezi textul complet al blocului (primele 1000 caractere)</summary>";
    echo "<pre>" . htmlspecialchars(substr($block, 0, 1000)) . "\n...(truncated)</pre>";
    echo "</details>";
    
    echo "</div>";
}

echo "<hr>";
echo "<h2>🔎 Caută manual 'Vaslui' în tot conținutul</h2>";

$vasluiPos = stripos($htmlContent, 'Vaslui');
if ($vasluiPos !== false) {
    echo "<p style='color: green;'>✅ Găsit 'Vaslui' la poziția $vasluiPos</p>";
    
    // Arată context
    $start = max(0, $vasluiPos - 200);
    $end = min(strlen($htmlContent), $vasluiPos + 300);
    $context = substr($htmlContent, $start, $end - $start);
    
    echo "<pre style='background: #ffffcc;'>" . htmlspecialchars($context) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ Nu s-a găsit 'Vaslui' în întregul conținut!</p>";
}

echo "</body></html>";
