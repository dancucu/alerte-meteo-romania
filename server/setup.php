<?php
/**
 * Setup & Instalare - Sistem Alertă Meteo România
 * Script de verificare și configurare via browser (fără SSH)
 */

// Setări securitate - ȘTERGE ACEST FIȘIER după instalare sau limitează accesul
$ALLOWED_IPS = ['127.0.0.1', '::1']; // Adaugă IP-ul tău aici
// Uncomment pentru a activa restricția:
// if (!in_array($_SERVER['REMOTE_ADDR'], $ALLOWED_IPS)) {
//     die('Acces interzis. IP-ul tău: ' . $_SERVER['REMOTE_ADDR']);
// }

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minute timeout

header('Content-Type: text/html; charset=utf-8');

// Configurare căi
$baseDir = dirname(__FILE__);
$scriptsDir = $baseDir . '/scripts';
$publicDir = $baseDir;

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Alertă Meteo România</title>
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
        h1 { color: #333; margin-bottom: 10px; }
        h2 { color: #667eea; margin: 30px 0 15px; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .step {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 10px 0;
        }
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
            margin: 5px;
        }
        button:hover, .button:hover { background: #5568d3; }
        .path { color: #667eea; font-weight: bold; }
        ul { margin: 10px 0 10px 30px; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌦️ Setup - Sistem Alertă Meteo România</h1>
        <p style="color: #666; margin-bottom: 30px;">Verificare și configurare automată</p>

        <?php
        $checks = [];
        $errors = [];
        $warnings = [];

        // ====== VERIFICĂRI ======
        
        echo "<h2>📋 Pas 1: Verificare Sistem</h2>";

        // 1. PHP Version
        echo "<div class='step'>";
        $phpVersion = phpversion();
        if (version_compare($phpVersion, '7.0.0', '>=')) {
            echo "<p>✅ <strong>PHP Version:</strong> $phpVersion (OK)</p>";
            $checks[] = 'php';
        } else {
            echo "<p>❌ <strong>PHP Version:</strong> $phpVersion (Necesită minim 7.0)</p>";
            $errors[] = 'PHP prea vechi';
        }
        echo "</div>";

        // 2. Python
        echo "<div class='step'>";
        $pythonPath = null;
        $pythonVersion = null;
        
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
                $pythonVersion = trim($output);
                break;
            }
        }
        
        if ($pythonPath) {
            echo "<p>✅ <strong>Python găsit:</strong> $pythonVersion</p>";
            echo "<p class='path'>📍 Cale: <code>$pythonPath</code></p>";
            $checks[] = 'python';
        } else {
            echo "<p>❌ <strong>Python:</strong> Nu a fost găsit</p>";
            echo "<p>Contactează hosting support pentru activare Python3</p>";
            $errors[] = 'Python nu este instalat';
        }
        echo "</div>";

        // 3. Directoare
        echo "<div class='step'>";
        if (!is_dir($scriptsDir)) {
            mkdir($scriptsDir, 0755, true);
            echo "<p>✅ <strong>Director scripts:</strong> Creat cu succes</p>";
        } else {
            echo "<p>✅ <strong>Director scripts:</strong> Există</p>";
        }
        echo "<p class='path'>📁 <code>$scriptsDir</code></p>";
        
        if (is_writable($scriptsDir)) {
            echo "<p>✅ <strong>Permisiuni:</strong> Scriere OK</p>";
            $checks[] = 'writable';
        } else {
            echo "<p>⚠️ <strong>Permisiuni:</strong> Lipsă permisiune scriere</p>";
            $warnings[] = 'Lipsă permisiune scriere';
        }
        echo "</div>";

        // 4. Fișiere necesare
        echo "<div class='step'>";
        $requiredFiles = [
            'scripts/weather_alerts_cron.py' => 'Script Python principal'
        ];
        
        foreach ($requiredFiles as $file => $desc) {
            $filePath = $baseDir . '/' . $file;
            if (file_exists($filePath)) {
                echo "<p>✅ <strong>$desc:</strong> Există</p>";
                $checks[] = 'file_' . $file;
            } else {
                echo "<p>❌ <strong>$desc:</strong> Lipsește ($file)</p>";
                $errors[] = "Lipsește $file";
            }
        }
        echo "</div>";

        // ====== TESTE ======
        
        if ($pythonPath && empty($errors)) {
            echo "<h2>🧪 Pas 2: Test Funcționalitate</h2>";
            
            echo "<div class='step info'>";
            echo "<p>🔄 Rulez scriptul Python...</p>";
            echo "</div>";
            
            $scriptPath = $scriptsDir . '/weather_alerts_cron.py';
            
            if (file_exists($scriptPath)) {
                $command = "cd " . escapeshellarg($scriptsDir) . " && " . 
                          escapeshellarg($pythonPath) . " " . 
                          escapeshellarg($scriptPath) . " 2>&1";
                
                echo "<div class='step'>";
                echo "<p><strong>Comandă:</strong></p>";
                echo "<pre>$command</pre>";
                
                $output = shell_exec($command);
                
                echo "<p><strong>Output:</strong></p>";
                echo "<pre>" . htmlspecialchars($output) . "</pre>";
                
                // Verifică dacă s-a generat HTML
                $outputHtml = $publicDir . '/index.html';
                if (file_exists($outputHtml)) {
                    $size = filesize($outputHtml) / 1024;
                    echo "<p>✅ <strong>Hartă generată:</strong> index.html (" . number_format($size, 2) . " KB)</p>";
                    $checks[] = 'html_generated';
                } else {
                    echo "<p>❌ <strong>Hartă:</strong> Nu s-a generat index.html</p>";
                    $errors[] = 'HTML nu s-a generat';
                }
                echo "</div>";
            }
        }

        // ====== INSTRUCȚIUNI CRON ======
        
        if (!empty($checks) && empty($errors)) {
            echo "<h2>⏰ Pas 3: Configurare Cron Job</h2>";
            
            echo "<div class='step success'>";
            echo "<p>✅ Toate verificările au trecut! Acum configurează cron job-ul.</p>";
            echo "</div>";
            
            echo "<div class='step'>";
            echo "<h3>📝 Opțiunea 1: Cron via cPanel</h3>";
            echo "<ol>";
            echo "<li>Deschide <strong>cPanel</strong> → <strong>Cron Jobs</strong></li>";
            echo "<li>Adaugă cron job nou cu setările:</li>";
            echo "</ol>";
            echo "<ul>";
            echo "<li><strong>Interval:</strong> <code>*/5 * * * *</code> (la fiecare 5 minute)</li>";
            echo "<li><strong>Comandă:</strong></li>";
            echo "</ul>";
            echo "<pre>";
            echo "cd " . $scriptsDir . " && " . $pythonPath . " weather_alerts_cron.py >> cron.log 2>&1";
            echo "</pre>";
            echo "</div>";
            
            echo "<div class='step'>";
            echo "<h3>🌐 Opțiunea 2: Cron via PHP (run.php)</h3>";
            echo "<p>Dacă cPanel cron nu funcționează, folosește un serviciu extern:</p>";
            echo "<ol>";
            echo "<li>Mergi la <a href='https://cron-job.org' target='_blank'>https://cron-job.org</a></li>";
            echo "<li>Creează cont gratuit</li>";
            echo "<li>Adaugă cron job care accesează:</li>";
            echo "</ol>";
            echo "<pre>https://tazzstudio.ro/alerte-meteo/run.php</pre>";
            echo "<p>Interval: La fiecare 5 minute</p>";
            echo "</div>";
        }

        // ====== SUMAR ======
        
        echo "<h2>📊 Sumar</h2>";
        
        if (empty($errors)) {
            echo "<div class='step success'>";
            echo "<h3>🎉 Instalare Completă!</h3>";
            echo "<p>Sistemul este gata de utilizare.</p>";
            echo "<br>";
            echo "<p><strong>Link-uri utile:</strong></p>";
            echo "<ul>";
            echo "<li><a href='index.html' target='_blank'>🗺️ Vezi Harta</a></li>";
            echo "<li><a href='run.php' target='_blank'>🔄 Actualizare Manuală</a></li>";
            echo "<li><a href='status.php' target='_blank'>📊 Status Sistem</a></li>";
            echo "</ul>";
            echo "<br>";
            echo "<p><strong>⚠️ IMPORTANT:</strong> După configurarea cron-ului, <strong>șterge acest fișier setup.php</strong> pentru securitate!</p>";
            echo "</div>";
        } else {
            echo "<div class='step error'>";
            echo "<h3>❌ S-au întâmpinat erori:</h3>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul>";
            echo "<p><strong>Soluții:</strong></p>";
            echo "<ul>";
            echo "<li>Contactează hosting support pentru activare Python3</li>";
            echo "<li>Verifică că ai urcat toate fișierele corect via FTP</li>";
            echo "<li>Verifică permisiunile directoarelor (755 pentru folders, 644 pentru files)</li>";
            echo "</ul>";            echo "<br>";
            echo "<p><strong>🔍 Pentru diagnostic detaliat:</strong></p>";
            echo "<a href='debug.php' class='button'>🐛 Rulează Debug Tool</a>";            echo "</div>";
        }
        
        if (!empty($warnings)) {
            echo "<div class='step warning'>";
            echo "<h3>⚠️ Avertismente:</h3>";
            echo "<ul>";
            foreach ($warnings as $warning) {
                echo "<li>$warning</li>";
            }
            echo "</ul>";
            echo "</div>";
        }

        ?>

        <h2>📖 Documentație</h2>
        <div class="step info">
            <p>Pentru ghid detaliat, vezi:</p>
            <ul>
                <li><a href="https://github.com/dancucu/alerte-meteo-romania/blob/main/server/INSTALL_MANUAL.md" target="_blank">INSTALL_MANUAL.md</a></li>
                <li><a href="https://github.com/dancucu/alerte-meteo-romania" target="_blank">GitHub Repository</a></li>
            </ul>
        </div>

        <hr style="margin: 30px 0;">
        <p style="text-align: center; color: #666;">
            © 2026 <a href="https://tazzstudio.ro">TazzStudio.ro</a> | 
            <a href="https://github.com/dancucu/alerte-meteo-romania">GitHub</a>
        </p>
    </div>
</body>
</html>
