# 🌐 Fișiere pentru Server

Directorul `server/` conține tot ce ai nevoie pentru a rula sistemul pe serverul tazzstudio.ro.

## 📁 Fișiere

- **`weather_alerts_cron.py`** - Script principal pentru rulare automată via cron
- **`run.php`** ⭐ - Rulare manuală via browser (fără SSH)
- **`setup.php`** ⭐ - Instalare și verificare automată (fără SSH)
- **`status.php`** - Pagină admin pentru monitorizare  
- **`install.sh`** - Script instalare automată (necesită SSH)
- **`INSTALL_MANUAL.md`** ⭐ - **Ghid detaliat instalare fără SSH** (FTP/cPanel)
- **`README_SERVER.md`** - Ghid instalare cu SSH
- **`crontab.txt`** - Exemplu configurare cron job
- **`.htaccess`** - Configurări Apache pentru directorul public

## 🚀 Quick Start

### 📱 FĂRĂ SSH (Recomandat pentru majoritatea utilizatorilor)

**Pasul 1:** Descarcă fișierele de pe GitHub
- Mergi la: https://github.com/dancucu/alerte-meteo-romania
- Click **Code** → **Download ZIP**

**Pasul 2:** Urcă via FTP în `public_html/alerte-meteo/`
- Urcă toate fișierele din `server/` în `public_html/alerte-meteo/`
- Creează subdirector `scripts/`
- Mută `weather_alerts_cron.py` în `scripts/`

**Pasul 3:** Rulează setup în browser
- Deschide: `https://tazzstudio.ro/alerte-meteo/setup.php`
- Urmează instrucțiunile de pe ecran

**Pasul 4:** Configurează cron în cPanel
- Vezi instrucțiunile din `setup.php`

📖 **Ghid detaliat:** [INSTALL_MANUAL.md](INSTALL_MANUAL.md)

### 💻 CU SSH (Pentru utilizatori avansați)

```bash
# Urcă directorul server/ pe server
# Apoi rulează:
cd server
chmod +x install.sh
./install.sh
```

📖 **Ghid detaliat:** [README_SERVER.md](README_SERVER.md)

## 🌐 URL-uri După Instalare

- **Hartă publică:** `https://tazzstudio.ro/alerte-meteo/`
- **Rulare manuală:** `https://tazzstudio.ro/alerte-meteo/run.php`
- **Status sistem:** `https://tazzstudio.ro/alerte-meteo/status.php`
- **Setup:** `https://tazzstudio.ro/alerte-meteo/setup.php` (șterge după instalare!)

## 🔄 Actualizare Manuală (fără cron)

Dacă nu poți configura cron, poți actualiza manual:

1. **Via browser:** Deschide `run.php` în browser
2. **Serviciu extern:** Folosește https://cron-job.org
   - Adaugă URL: `https://tazzstudio.ro/alerte-meteo/run.php?action=run`
   - Interval: 5 minute

## 📖 Documentație Completă

- 📱 **Fără SSH:** [INSTALL_MANUAL.md](INSTALL_MANUAL.md)
- 💻 **Cu SSH:** [README_SERVER.md](README_SERVER.md)
- 🌐 **GitHub:** https://github.com/dancucu/alerte-meteo-romania
