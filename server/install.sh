#!/bin/bash
# Script de instalare automată pentru serverul tazzstudio.ro

set -e

echo "🌦️  Instalare Sistem Alertă Meteo România"
echo "=========================================="
echo ""

# Detectare directoare
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
HOME_DIR="$HOME"
WEATHER_DIR="$HOME_DIR/weather-scripts"
PUBLIC_DIR="$HOME_DIR/public_html/alerte-meteo"

echo "📁 Configurare directoare:"
echo "   Script curent: $SCRIPT_DIR"
echo "   Home: $HOME_DIR"
echo "   Weather scripts: $WEATHER_DIR"
echo "   Public HTML: $PUBLIC_DIR"
echo ""

# Creează directoare
echo "📂 Creare directoare..."
mkdir -p "$WEATHER_DIR"
mkdir -p "$PUBLIC_DIR"
echo "✅ Directoare create"
echo ""

# Copiază fișiere
echo "📋 Copiere fișiere..."
cp "$SCRIPT_DIR/weather_alerts_cron.py" "$WEATHER_DIR/"
cp "$SCRIPT_DIR/status.php" "$WEATHER_DIR/"
chmod +x "$WEATHER_DIR/weather_alerts_cron.py"
echo "✅ Fișiere copiate"
echo ""

# Verifică Python
echo "🐍 Verificare Python..."
if command -v python3 &> /dev/null; then
    PYTHON_VERSION=$(python3 --version)
    echo "✅ $PYTHON_VERSION găsit"
    PYTHON_PATH=$(which python3)
    echo "   Cale: $PYTHON_PATH"
else
    echo "❌ Python3 nu este instalat!"
    exit 1
fi
echo ""

# Instalează dependențe
echo "📦 Instalare dependențe Python..."
if pip3 install --user requests pyproj shapely numpy 2>&1; then
    echo "✅ Dependențe instalate"
else
    echo "⚠️  Eroare la instalare dependențe (continuăm, dar verifică manual!)"
fi
echo ""

# Test rulare
echo "🧪 Test rulare script..."
cd "$WEATHER_DIR"
if python3 weather_alerts_cron.py; then
    echo "✅ Script funcționează corect!"
else
    echo "❌ Eroare la rularea scriptului!"
    exit 1
fi
echo ""

# Verifică fișier output
if [ -f "$PUBLIC_DIR/index.html" ]; then
    FILE_SIZE=$(du -h "$PUBLIC_DIR/index.html" | cut -f1)
    echo "✅ Fișier output generat: $PUBLIC_DIR/index.html ($FILE_SIZE)"
else
    echo "❌ Fișier output nu a fost generat!"
    exit 1
fi
echo ""

# Configurare cron
echo "⏰ Configurare cron job..."
CRON_LINE="*/5 * * * * cd $WEATHER_DIR && $PYTHON_PATH weather_alerts_cron.py >> weather_cron.log 2>&1"
echo ""
echo "Linia cron de adăugat:"
echo "--------------------------------------"
echo "$CRON_LINE"
echo "--------------------------------------"
echo ""
read -p "Vrei să adaug automat în crontab? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Salvează crontab existent
    crontab -l > /tmp/mycron 2>/dev/null || true
    # Adaugă linia nouă dacă nu există deja
    if ! grep -q "weather_alerts_cron.py" /tmp/mycron; then
        echo "$CRON_LINE" >> /tmp/mycron
        crontab /tmp/mycron
        echo "✅ Cron job adăugat"
    else
        echo "⚠️  Cron job deja există"
    fi
    rm /tmp/mycron
else
    echo "⏭️  Sări peste configurare cron (adaugă manual mai târziu)"
fi
echo ""

echo "✅ INSTALARE COMPLETĂ!"
echo ""
echo "📊 Informații utile:"
echo "   🌐 Hartă: https://tazzstudio.ro/alerte-meteo/"
echo "   📊 Status: https://tazzstudio.ro/alerte-meteo/status.php"
echo "   📁 Scripturi: $WEATHER_DIR"
echo "   📝 Log: $WEATHER_DIR/weather_updates.log"
echo ""
echo "🔧 Comenzi utile:"
echo "   # Verifică crontab"
echo "   crontab -l"
echo ""
echo "   # Monitorizează log-ul"
echo "   tail -f $WEATHER_DIR/weather_updates.log"
echo ""
echo "   # Rulare manuală"
echo "   cd $WEATHER_DIR && python3 weather_alerts_cron.py"
echo ""
echo "🎉 Sistemul este gata de utilizare!"
