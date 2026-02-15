<?php
/**
 * Debug & Diagnostic Tool
 * Ajută la identificarea problemelor de instalare
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);

header('Content-Type: text/html; charset=utf-8');

$baseDir = dirname(__FILE__);
$scriptsDir = $baseDir . '/scripts';

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Alertă Meteo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            border-radius: 8px;
            padding: 30px;
        }
        h1 { color: #4ec9b0; margin-bottom: 20px; }
        h2 { color: #569cd6; margin: 25px 0 15px; border-bottom: 1px solid #3e3e42; padding-bottom: 5px; }
        .check { margin: 10px 0; padding: 10px; background: #2d2d30; border-radius: 4px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #9cdcfe; }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            border-left: 3px solid #007acc;
            margin: 10px 0;
        }
        button {
            background: #007acc;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 5px 10px 0;
        }
        button:hover { background: #005a9e; }
        .code { color: #ce9178; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug & Diagnostic Tool</h1>

        <h2>1. Informații Server</h2>
        <div class="check">
            <p><strong>PHP Version:</strong> <span class="info"><?php echo phpversion(); ?></span></p>
            <p><strong>Server Software:</strong> <span class="info"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span></p>
            <p><strong>Document Root:</strong> <span class="code"><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></span></p>
            <p><strong>Current Script:</strong> <span class="code"><?php echo __FILE__; ?></span></p>
            <p><strong>Base Dir:</strong> <span class="code"><?php echo $baseDir; ?></span></p>
            <p><strong>Scripts Dir:</strong> <span class="code"><?php echo $scriptsDir; ?></span></p>
        </div>

        <h2>2. Verificare Python</h2>
        <?php
        $pythonPaths = [
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            '/usr/bin/python',
            '/opt/alt/python39/bin/python3',
            '/opt/alt/python38/bin/python3',
            '/opt/alt/python37/bin/python3',
            'python3',
            'python'
        ];

        $foundPython = null;
        foreach ($pythonPaths as $path) {
            echo "<div class='check'>";
            echo "<p>Testez: <span class='code'>$path</span></p>";
            
            $output = @shell_exec("$path --version 2>&1");
            if ($output && stripos($output, 'python') !== false) {
                echo "<p class='success'>✅ Găsit: $output</p>";
                $foundPython = $path;
                
                // Testează și care module sunt instalate
                $modulesTest = @shell_exec("$path -c \"import sys; print('Python executable:', sys.executable)\" 2>&1");
                echo "<pre>$modulesTest</pre>";
                
                echo "<p><strong>Testez module necesare:</strong></p>";
                $modules = ['requests', 'pyproj', 'shapely', 'numpy', 're', 'json', 'os'];
                foreach ($modules as $module) {
                    $moduleCheck = @shell_exec("$path -c \"import $module; print('$module OK')\" 2>&1");
                    if (stripos($moduleCheck, 'OK') !== false) {
                        echo "<p class='success'>✅ $module</p>";
                    } else {
                        echo "<p class='error'>❌ $module: $moduleCheck</p>";
                    }
                }
                
                break;
            } else {
                echo "<p class='error'>❌ Nu există sau eroare: $output</p>";
            }
            echo "</div>";
        }

        if (!$foundPython) {
            echo "<div class='check error'>";
            echo "<p>❌ Python nu a fost găsit în nicio locație standard!</p>";
            echo "<p><strong>Soluție:</strong> Contactează hosting support pentru instalare Python3</p>";
            echo "</div>";
        }
        ?>

        <h2>3. Verificare Fișiere</h2>
        <?php
        $files = [
            'scripts/weather_alerts_cron.py',
            'setup.php',
            'run.php',
            'status.php',
            '.htaccess'
        ];

        foreach ($files as $file) {
            $path = $baseDir . '/' . $file;
            echo "<div class='check'>";
            echo "<p><strong>$file:</strong> ";
            
            if (file_exists($path)) {
                $size = filesize($path);
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                echo "<span class='success'>✅ Există</span></p>";
                echo "<p>Mărime: <span class='info'>" . number_format($size) . " bytes</span></p>";
                echo "<p>Permisiuni: <span class='info'>$perms</span></p>";
                
                if (is_readable($path)) {
                    echo "<p class='success'>✅ Citibil</p>";
                } else {
                    echo "<p class='error'>❌ Nu poate fi citit</p>";
                }
                
                if ($file === 'scripts/weather_alerts_cron.py') {
                    if (is_executable($path)) {
                        echo "<p class='success'>✅ Executabil</p>";
                    } else {
                        echo "<p class='warning'>⚠️ Nu este executabil (încearcă chmod +x via FTP)</p>";
                    }
                }
            } else {
                echo "<span class='error'>❌ Nu există</span></p>";
            }
            echo "</div>";
        }
        ?>

        <h2>4. Verificare Directoare & Permisiuni</h2>
        <?php
        $dirs = [
            $baseDir,
            $scriptsDir
        ];

        foreach ($dirs as $dir) {
            echo "<div class='check'>";
            echo "<p><strong>$dir:</strong></p>";
            
            if (is_dir($dir)) {
                $perms = substr(sprintf('%o', fileperms($dir)), -4);
                echo "<p class='success'>✅ Există</p>";
                echo "<p>Permisiuni: <span class='info'>$perms</span></p>";
                
                if (is_writable($dir)) {
                    echo "<p class='success'>✅ Scriere permisă</p>";
                } else {
                    echo "<p class='error'>❌ Scriere nepermisă (setează 755 via FTP)</p>";
                }
            } else {
                echo "<p class='error'>❌ Nu există</p>";
            }
            echo "</div>";
        }
        ?>

        <h2>5. Test PHP Functions</h2>
        <?php
        $functions = ['shell_exec', 'exec', 'system', 'passthru'];
        echo "<div class='check'>";
        foreach ($functions as $func) {
            if (function_exists($func)) {
                echo "<p class='success'>✅ $func() disponibilă</p>";
            } else {
                echo "<p class='error'>❌ $func() dezactivată</p>";
            }
        }
        
        $disabled = ini_get('disable_functions');
        if ($disabled) {
            echo "<p class='warning'>⚠️ Funcții dezactivate: <span class='code'>$disabled</span></p>";
        }
        echo "</div>";
        ?>

        <h2>6. Test Rulare Script</h2>
        <?php
        if ($foundPython && file_exists($scriptsDir . '/weather_alerts_cron.py')) {
            echo "<form method='post'>";
            echo "<button type='submit' name='test_run'>▶️ Rulează Test Script</button>";
            echo "</form>";
            
            if (isset($_POST['test_run'])) {
                echo "<div class='check'>";
                echo "<p class='info'>📝 Rulare în curs...</p>";
                
                $scriptPath = $scriptsDir . '/weather_alerts_cron.py';
                $command = "cd " . escapeshellarg($scriptsDir) . " && " . 
                          escapeshellarg($foundPython) . " " . 
                          escapeshellarg(basename($scriptPath)) . " 2>&1";
                
                echo "<p><strong>Comandă:</strong></p>";
                echo "<pre>$command</pre>";
                
                $startTime = microtime(true);
                $output = shell_exec($command);
                $endTime = microtime(true);
                
                echo "<p><strong>Durată:</strong> <span class='info'>" . round($endTime - $startTime, 2) . " secunde</span></p>";
                echo "<p><strong>Output complet:</strong></p>";
                echo "<pre>" . htmlspecialchars($output ?: 'Niciun output') . "</pre>";
                
                // Verifică rezultatul
                $htmlFile = $baseDir . '/index.html';
                if (file_exists($htmlFile)) {
                    $size = filesize($htmlFile) / 1024;
                    $time = date('Y-m-d H:i:s', filemtime($htmlFile));
                    echo "<p class='success'>✅ index.html generat (" . number_format($size, 2) . " KB)</p>";
                    echo "<p>Modificat: <span class='info'>$time</span></p>";
                } else {
                    echo "<p class='error'>❌ index.html NU s-a generat</p>";
                    echo "<p><strong>Cauze posibile:</strong></p>";
                    echo "<ul style='margin-left: 20px;'>";
                    echo "<li>Lipsesc module Python (requests, pyproj, shapely, numpy)</li>";
                    echo "<li>Script are erori (vezi output-ul de mai sus)</li>";
                    echo "<li>Lipsă permisiuni de scriere</li>";
                    echo "<li>API-ul nu răspunde și nu există cache</li>";
                    echo "</ul>";
                }
                echo "</div>";
            }
        } else {
            echo "<div class='check error'>";
            echo "<p>❌ Nu se poate rula - Python sau scriptul lipsesc</p>";
            echo "</div>";
        }
        ?>

        <h2>7. Comenzi Utile</h2>
        <div class="check">
            <p>Dacă ai acces la Terminal în cPanel, testează:</p>
            <pre>cd <?php echo $scriptsDir; ?>

# Verifică Python
<?php echo $foundPython ?? 'python3'; ?> --version

# Testează module
<?php echo $foundPython ?? 'python3'; ?> -c "import requests; print('requests OK')"

# Rulează script
<?php echo $foundPython ?? 'python3'; ?> weather_alerts_cron.py

# Verifică output
ls -la <?php echo $baseDir; ?>/index.html</pre>
        </div>

        <h2>8. Recomandări</h2>
        <div class="check info">
            <?php if (!$foundPython): ?>
                <p><strong>1. Python nu este instalat</strong></p>
                <p>→ Contactează hosting support: "Vă rog să activați Python 3.7+ pe contul meu"</p>
                <br>
            <?php endif; ?>
            
            <p><strong>2. Alternative dacă Python nu funcționează:</strong></p>
            <p>→ Pot crea o versiune PHP pură (fără Python)</p>
            <p>→ Sau folosește un serviciu extern pentru procesare</p>
            <br>
            
            <p><strong>3. Setări recomandate FTP:</strong></p>
            <p>→ Directoare: 755</p>
            <p>→ Fișiere PHP: 644</p>
            <p>→ Fișiere Python: 755</p>
        </div>

        <hr style="margin: 30px 0; border-color: #3e3e42;">
        <p style="text-align: center; color: #858585;">
            <a href="setup.php" style="color: #569cd6;">← Înapoi la Setup</a> | 
            <a href="https://github.com/dancucu/alerte-meteo-romania/issues" style="color: #569cd6;" target="_blank">Raportează Problemă</a>
        </p>
    </div>
</body>
</html>
