# 🌐 Fișiere pentru Server

Directorul `server/` conține tot ce ai nevoie pentru a rula sistemul pe serverul tazzstudio.ro.

## 📁 Fișiere

- **`weather_alerts_cron.py`** - Script principal pentru rulare automată via cron
- **`status.php`** - Pagină admin pentru monitorizare și actualizare manuală  
- **`install.sh`** - Script instalare automată (rulează tot setup-ul)
- **`README_SERVER.md`** - Ghid detaliat de instalare pas cu pas
- **`crontab.txt`** - Exemplu configurare cron job
- **`.htaccess`** - Configurări Apache pentru directorul public

## 🚀 Quick Start

```bash
# Urcă directorul server/ pe server
# Apoi rulează:
cd server
chmod +x install.sh
./install.sh
```

## 📖 Documentație Completă

Vezi [README_SERVER.md](README_SERVER.md) pentru instrucțiuni detaliate.

## 🌐 URL Final

După instalare, harta va fi accesibilă la:
- **Hartă:** https://tazzstudio.ro/alerte-meteo/
- **Status:** https://tazzstudio.ro/alerte-meteo/status.php
