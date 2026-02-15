<?php
/**
 * Rulare Manuală - Sistem Alertă Meteo România
 * Actualizează harta manual via browser
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120); // 2 minute timeout

header('Content-Type: text/html; charset=utf-8');

// Configurare căi
$baseDir = dirname(__FILE__);
$scriptsDir = $baseDir . '/scripts';
$phpScript = $baseDir . '/weather_alerts_php.php';  // Noul script PHP pur
$pythonScript = $scriptsDir . '/weather_alerts_cron.py';  // Script Python (backup)

// Găsește Python
$pythonPath = null;
$possiblePaths = [
    '/usr/bin/python3',
    '/usr/local/bin/python3',
    '/opt/alt/python39/bin/python3',
    '/opt/alt/python38/bin/python3',
    'python3',
    'python'
];

foreach ($possiblePaths as $path) {
    $output = @shell_exec("$path --version 2>&1");
    if ($output && stripos($output, 'python') !== false) {
        $pythonPath = $path;
        break;
    }
}

// Determină ce script să folosim: PHP pur (prioritate) sau Python (dacă există)
$usePhpScript = file_exists($phpScript);
$usePythonScript = $pythonPath && file_exists($pythonScript);

$action = isset($_GET['action']) ? $_GET['action'] : 'show';

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizare Hartă Meteo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 { color: #333; margin-bottom: 20px; }
        h2 { color: #667eea; margin: 25px 0 15px; }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }
        .warning { background: #fff3cd; color: #856404; border-left-color: #ffc107; }
        button, .button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        button:hover, .button:hover { background: #5568d3; }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 500px;
            overflow-y: auto;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌦️ Actualizare Hartă Meteo</h1>

        <?php if ($action === 'run'): ?>
            
            <div class="status info">
                <h3>🔄 Rulare actualizare în curs...</h3>
                <div class="spinner"></div>
            </div>

            <?php
            if (!$usePhpScript && !$usePythonScript) {
                echo '<div class="status error">';
                echo '<h3>❌ Eroare: Niciun script disponibil</h3>';
                echo '<p>Verifică că ai urcat fișierele corect:</p>';
                echo '<ul style="margin-left: 20px;">';
                echo '<li><code>weather_alerts_php.php</code> (versiune PHP pură - recomandat)</li>';
                echo '<li>SAU <code>scripts/weather_alerts_cron.py</code> (versiune Python)</li>';
                echo '</ul>';
                echo '</div>';
            } else {
                // Prioritate: PHP > Python
                if ($usePhpScript) {
                    // Rulează scriptul PHP
                    $command = "/usr/bin/php " . escapeshellarg($phpScript) . " 2>&1";
                    $scriptType = "PHP Pure Version";
                } else {
                    // Rulează scriptul Python
                    $command = "cd " . escapeshellarg($scriptsDir) . " && " . 
                              escapeshellarg($pythonPath) . " " . 
                              escapeshellarg(basename($pythonScript)) . " 2>&1";
                    $scriptType = "Python Version";
                }
                
                $startTime = microtime(true);
                $output = shell_exec($command);
                $endTime = microtime(true);
                $duration = round($endTime - $startTime, 2);
                
                // Verifică rezultatul
                $htmlFile = $baseDir . '/index.html';
                if (file_exists($htmlFile)) {
                    $fileSize = filesize($htmlFile) / 1024;
                    $fileTime = date('d.m.Y H:i:s', filemtime($htmlFile));
                    
                    echo '<div class="status success">';
                    echo '<h3>✅ Actualizare completă cu succes!</h3>';
                    echo '<p><strong>Script folosit:</strong> ' . $scriptType . '</p>';
                    echo '<p><strong>Durată:</strong> ' . $duration . ' secunde</p>';
                    echo '<p><strong>Hartă:</strong> ' . number_format($fileSize, 2) . ' KB</p>';
                    echo '<p><strong>Modificată:</strong> ' . $fileTime . '</p>';
                    echo '<br>';
                    echo '<a href="index.html" target="_blank" class="button">🗺️ Vezi Harta</a>';
                    echo '<a href="status.php" class="button">📊 Status Sistem</a>';
                    echo '</div>';
                } else {
                    echo '<div class="status error">';
                    echo '<h3>⚠️ Scriptul a rulat dar harta nu s-a generat</h3>';
                    echo '<p>Verifică output-ul de mai jos pentru erori.</p>';
                    echo '</div>';
                }
                
                // Afișează output
                echo '<h2>📋 Output Script</h2>';
                echo '<pre>' . htmlspecialchars($output) . '</pre>';
                
                // Linkuri
                echo '<h2>🔗 Acțiuni</h2>';
                echo '<a href="run.php" class="button">🔄 Refresh Pagină</a>';
                echo '<a href="run.php?action=run" class="button">▶️ Rulează Din Nou</a>';
                echo '<a href="index.html" target="_blank" class="button">🗺️ Vezi Harta</a>';
            }
            ?>

        <?php else: ?>
            
            <div class="status info">
                <h3>ℹ️ Informații Sistem</h3>
                <p><strong>Script disponibil:</strong> 
                    <?php 
                    if ($usePhpScript) {
                        echo "✅ PHP Pure Version (recomandat)";
                    } elseif ($usePythonScript) {
                        echo "✅ Python Version";
                    } else {
                        echo "❌ Niciun script disponibil";
                    }
                    ?>
                </p>
                <?php if ($usePythonScript): ?>
                <p><strong>Python:</strong> 
                    <?php echo $pythonPath ? "✅ $pythonPath" : "❌ Nu a fost găsit"; ?>
                </p>
                <?php endif; ?>
                <p><strong>PHP Script:</strong> 
                    <?php echo file_exists($phpScript) ? "✅ Există" : "❌ Lipsește"; ?>
                </p>
                <?php
                $htmlFile = $baseDir . '/index.html';
                if (file_exists($htmlFile)) {
                    $fileSize = filesize($htmlFile) / 1024;
                    $fileTime = date('d.m.Y H:i:s', filemtime($htmlFile));
                    echo '<p><strong>Hartă curentă:</strong> ' . number_format($fileSize, 2) . ' KB (modificată: ' . $fileTime . ')</p>';
                } else {
                    echo '<p><strong>Hartă curentă:</strong> ❌ Nu există încă</p>';
                }
                ?>
                <p><strong>Server time:</strong> <?php echo date('d.m.Y H:i:s'); ?></p>
            </div>

            <h2>🚀 Actualizare</h2>
            <div class="status warning">
                <p>Apasă butonul de mai jos pentru a actualiza harta meteo manual.</p>
                <p><strong>Notă:</strong> Procesul durează 5-20 secunde.</p>
            </div>

            <a href="?action=run" class="button" onclick="return confirm('Rulezi actualizarea hărții meteo?')">
                ▶️ Rulează Actualizare Acum
            </a>
            
            <?php if (file_exists($htmlFile)): ?>
                <a href="index.html" target="_blank" class="button">🗺️ Vezi Harta Curentă</a>
            <?php endif; ?>
            
            <a href="status.php" class="button">📊 Status Detaliat</a>

            <h2>💡 Automatizare</h2>
            <div class="status info">
                <p>Pentru actualizare automată la fiecare 5 minute, configurează:</p>
                <ul style="margin: 10px 0 10px 20px;">
                    <li><strong>Opțiunea 1:</strong> Cron job în cPanel (recomandat)</li>
                    <li><strong>Opțiunea 2:</strong> Serviciu extern: <a href="https://cron-job.org" target="_blank">cron-job.org</a></li>
                </ul>
                <p>URL pentru cron extern:</p>
                <pre><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]?action=run"; ?></pre>
            </div>

            <h2>📚 Documentație</h2>
            <div class="status">
                <a href="setup.php" class="button">🔧 Setup & Verificare</a>
                <a href="https://github.com/dancucu/alerte-meteo-romania/blob/main/server/INSTALL_MANUAL.md" target="_blank" class="button">📖 Ghid Instalare</a>
            </div>

        <?php endif; ?>

        <hr style="margin: 30px 0;">
        <p style="text-align: center; color: #666;">
            © 2026 <a href="https://tazzstudio.ro">TazzStudio.ro</a> | 
            <a href="https://github.com/dancucu/alerte-meteo-romania">GitHub</a>
        </p>
    </div>
</body>
</html>
