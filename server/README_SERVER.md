# 🌦️ Instalare pe Server tazzstudio.ro

Ghid complet pentru instalarea și configurarea sistemului de alertă meteo pe serverul tazzstudio.ro.

## 📁 Structura Directoare (Exemple)

```
/home/username/
├── weather-scripts/              # Scripturile Python
│   ├── weather_alerts_cron.py   # Script principal cron
│   ├── status.php               # Pagină status/admin
│   ├── weather_cache.json       # Cache (generat automat)
│   ├── weather_updates.log      # Log actualizări
│   └── weather_cron.log         # Log cron
│
└── public_html/
    └── alerte-meteo/             # Director public accesibil web
        └── index.html            # Hartă (generată automat)
```

## 🚀 Instalare Pas cu Pas

### 1. Conectare la Server

```bash
ssh username@tazzstudio.ro
```

### 2. Creare Directoare

```bash
# Creează directorul pentru scripturi
mkdir -p ~/weather-scripts
cd ~/weather-scripts

# Creează directorul public
mkdir -p ~/public_html/alerte-meteo
```

### 3. Upload Fișiere

Urcă fișierele de pe GitHub în directorul `~/weather-scripts/`:
- `weather_alerts_cron.py`
- `status.php`

```bash
# Opțiune 1: Clone repo
cd ~/weather-scripts
git clone https://github.com/dancucu/alerte-meteo-romania.git temp
mv temp/server/* .
rm -rf temp

# Opțiune 2: Download direct
wget https://raw.githubusercontent.com/dancucu/alerte-meteo-romania/main/server/weather_alerts_cron.py
wget https://raw.githubusercontent.com/dancucu/alerte-meteo-romania/main/server/status.php
```

### 4. Setare Permisiuni

```bash
cd ~/weather-scripts
chmod +x weather_alerts_cron.py
chmod 644 status.php
```

### 5. Verificare Python și Dependențe

```bash
# Verifică versiunea Python
python3 --version

# Instalează dependențe (dacă ai acces)
pip3 install --user requests pyproj shapely numpy

# SAU cu Python virtual environment
python3 -m venv venv
source venv/bin/activate
pip install requests pyproj shapely
```

### 6. Test Manual

```bash
cd ~/weather-scripts
python3 weather_alerts_cron.py
```

Verifică output-ul:
- ✅ Ar trebui să vadă "SUCCESS - Hartă actualizată"
- 📁 Fișier creat: `~/public_html/alerte-meteo/index.html`

### 7. Configurare Cron Job

Editează crontab:
```bash
crontab -e
```

Adaugă linia (actualizare la fiecare 5 minute):
```bash
*/5 * * * * cd /home/USERNAME/weather-scripts && /usr/bin/python3 weather_alerts_cron.py >> weather_cron.log 2>&1
```

**Important:** Înlocuiește `/home/USERNAME/` cu calea ta reală!

Pentru a găsi calea exactă:
```bash
pwd
which python3
```

### 8. Verificare Cron Job

```bash
# Vezi crontab-ul activ
crontab -l

# Monitorizează log-ul
tail -f ~/weather-scripts/weather_cron.log

# Verifică ultimele actualizări
tail -20 ~/weather-scripts/weather_updates.log
```

## 🌐 Acces Web

După configurare, site-ul va fi accesibil la:

- **Hartă publică:** `https://tazzstudio.ro/alerte-meteo/`
- **Pagină status:** `https://tazzstudio.ro/alerte-meteo/status.php` (sau pune în alt director)

## 🔧 Configurare Avansată

### Modificare Căi în Script

Editează `weather_alerts_cron.py` și ajustează căile dacă e necesar:

```python
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PUBLIC_HTML_DIR = os.path.join(SCRIPT_DIR, '..', 'public_html', 'alerte-meteo')
CACHE_FILE = os.path.join(SCRIPT_DIR, 'weather_cache.json')
```

### Debugging

```bash
# Rulare cu output detaliat
cd ~/weather-scripts
python3 -u weather_alerts_cron.py

# Verifică procesele Python active
ps aux | grep python

# Verifică dimensiunea fișierelor
ls -lh ~/public_html/alerte-meteo/
ls -lh ~/weather-scripts/
```

## 📊 Monitorizare

### Log Files

```bash
# Log actualizări sistem
tail -f ~/weather-scripts/weather_updates.log

# Log cron execuții
tail -f ~/weather-scripts/weather_cron.log

# Căutare erori
grep "ERROR\|EROARE" ~/weather-scripts/*.log
```

### Test Actualizare Manuală

```bash
# Oprește cron temporar
crontab -r

# Rulează manual
cd ~/weather-scripts
python3 weather_alerts_cron.py

# Repornește cron
crontab -e  # adaugă din nou linia
```

## 🚨 Troubleshooting

### Eroare: "Module not found"
```bash
pip3 install --user requests pyproj shapely numpy
```

### Eroare: "Permission denied"
```bash
chmod +x weather_alerts_cron.py
chmod 755 ~/public_html/alerte-meteo
```

### Eroare: "No such file or directory"
Verifică căile în script:
```bash
python3 -c "import os; print(os.path.dirname(os.path.abspath(__file__)))"
```

### Cron nu rulează
```bash
# Verifică logurile cron system
grep CRON /var/log/syslog
# sau
journalctl -u cron
```

## 🔄 Update Sistem

Pentru actualizare cod:

```bash
cd ~/weather-scripts
git pull  # dacă ai clonat repo
# SAU download manual fișiere noi
```

## 📱 Notificări Email (Opțional)

Adaugă în crontab pentru notificări:
```bash
MAILTO="email@tau.com"
*/5 * * * * cd /home/USERNAME/weather-scripts && /usr/bin/python3 weather_alerts_cron.py >> weather_cron.log 2>&1
```

## 🔐 Securitate

### Protejează status.php

Crează `.htaccess` în directorul cu `status.php`:
```apache
AuthType Basic
AuthName "Restricted Area"
AuthUserFile /home/USERNAME/.htpasswd
Require valid-user
```

Creează parola:
```bash
htpasswd -c ~/.htpasswd admin
```

## ✅ Checklist Final

- [ ] Python3 instalat și funcțional
- [ ] Toate dependențele instalate
- [ ] Scriptul rulează manual cu succes
- [ ] Directoare create cu permisiuni corecte
- [ ] Cron job configurat și activ
- [ ] Fișier `index.html` se generează în `public_html/alerte-meteo/`
- [ ] Site-ul este accesibil la `https://tazzstudio.ro/alerte-meteo/`
- [ ] Log-urile se scriu corect

## 📞 Suport

- GitHub: https://github.com/dancucu/alerte-meteo-romania
- Issues: https://github.com/dancucu/alerte-meteo-romania/issues

---

© 2026 TazzStudio.ro
