<?php
/**
 * Script PHP pentru actualizare manuală sau verificare status
 * Poate fi accesat via browser: https://tazzstudio.ro/alerte-meteo/update.php
 */

header('Content-Type: text/html; charset=utf-8');

// Configurare căi
$scriptDir = dirname(__FILE__);
$pythonScript = $scriptDir . '/weather_alerts_cron.py';
$logFile = $scriptDir . '/weather_updates.log';
$outputFile = $scriptDir . '/../public_html/alerte-meteo/index.html';

// Verifică dacă este request de update
$action = isset($_GET['action']) ? $_GET['action'] : 'status';

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Alertă Meteo - TazzStudio.ro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .status { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover { background: #5568d3; }
        .log {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        a { color: #667eea; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🌦️ Status Sistem Alertă Meteo</h1>
        
        <?php if ($action === 'update'): ?>
            <div class="status info">
                <h3>⏳ Lansez actualizare...</h3>
            </div>
            <?php
            $command = "cd " . escapeshellarg($scriptDir) . " && python3 weather_alerts_cron.py 2>&1";
            $output = shell_exec($command);
            ?>
            <div class="status success">
                <h3>✅ Actualizare completă!</h3>
                <pre><?php echo htmlspecialchars($output); ?></pre>
            </div>
        <?php endif; ?>
        
        <h2>📊 Informații Sistem</h2>
        
        <div class="status info">
            <p><strong>Script Python:</strong> 
                <?php echo file_exists($pythonScript) ? '✅ Există' : '❌ Lipsește'; ?>
            </p>
            <p><strong>Fișier output:</strong> 
                <?php 
                if (file_exists($outputFile)) {
                    $size = filesize($outputFile) / 1024;
                    $modified = date('d.m.Y H:i:s', filemtime($outputFile));
                    echo "✅ Există ({$size} KB, modificat: {$modified})";
                } else {
                    echo '❌ Nu există';
                }
                ?>
            </p>
            <p><strong>Server time:</strong> <?php echo date('d.m.Y H:i:s'); ?></p>
        </div>
        
        <h2>🔧 Acțiuni</h2>
        <button onclick="window.location.href='?action=update'">🔄 Actualizează Manual</button>
        <button onclick="window.location.href='../alerte-meteo/'">🗺️ Vezi Harta</button>
        <button onclick="window.location.href='?action=status'">📊 Refresh Status</button>
        
        <?php if (file_exists($logFile)): ?>
            <h2>📝 Ultimeleログuri (ultimele 50 linii)</h2>
            <div class="log">
                <?php
                $lines = file($logFile);
                $lastLines = array_slice($lines, -50);
                echo htmlspecialchars(implode('', $lastLines));
                ?>
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
