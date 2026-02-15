# 🌐 Instalare Manuală fără SSH (FTP/cPanel)

Ghid pentru instalarea sistemului pe tazzstudio.ro folosind **doar FTP și browser** (fără SSH).

## 📋 Cerințe

- ✅ Acces FTP la server (FileZilla, WinSCP, sau FTP Manager din cPanel)
- ✅ Acces cPanel sau panou control hosting
- ✅ Python3 instalat pe server (verifică în cPanel)

## 🗂️ Structura Directoare pe Server

```
public_html/
├── alerte-meteo/
│   ├── index.html                    # (generat automat)
│   ├── run.php                       # ⭐ Rulează actualizare
│   ├── setup.php                     # ⭐ Instalare & configurare
│   ├── status.php                    # Monitorizare
│   └── scripts/
│       ├── weather_alerts_cron.py    # Script Python
│       ├── weather_cache.json        # (generat automat)
│       └── weather_updates.log       # (generat automat)
```

## 📤 Pasul 1: Upload Fișiere via FTP

### A. Descarcă fișierele de pe GitHub

1. Mergi la: https://github.com/dancucu/alerte-meteo-romania
2. Click pe **Code** → **Download ZIP**
3. Dezarhivează pe calculatorul tău

### B. Conectare FTP

**FileZilla:**
- Host: `ftp.tazzstudio.ro` (sau IP server)
- Username: contul tău FTP
- Password: parola FTP
- Port: 21 (FTP) sau 22 (SFTP dacă e disponibil)

**cPanel File Manager:**
- Login la cPanel
- Click **File Manager**
- Navighează la `public_html`

### C. Creează Directoare

În `public_html/`, creează:
```
alerte-meteo/
alerte-meteo/scripts/
```

### D. Urcă Fișierele

Urcă în `public_html/alerte-meteo/`:
- `server/setup.php` → `alerte-meteo/setup.php`
- `server/run.php` → `alerte-meteo/run.php`
- `server/status.php` → `alerte-meteo/status.php`
- `server/.htaccess` → `alerte-meteo/.htaccess`

Urcă în `public_html/alerte-meteo/scripts/`:
- `server/weather_alerts_cron.py` → `alerte-meteo/scripts/weather_alerts_cron.py`

## 🔧 Pasul 2: Configurare via Browser

### A. Rulează Setup

1. Deschide în browser: `https://tazzstudio.ro/alerte-meteo/setup.php`
2. Scriptul va:
   - Verifica Python
   - Instala dependențe (dacă posibil)
   - Testa scriptul
   - Configura permisiuni
3. Urmează instrucțiunile de pe ecran

### B. Configurare Cron Job (cPanel)

1. Login în **cPanel**
2. Găsește **Cron Jobs** (în secțiunea Advanced)
3. Adaugă cron job nou:

**Setări recomandate:**
- **Minut:** `*/5` (la fiecare 5 minute)
- **Oră:** `*`
- **Zi:** `*`
- **Lună:** `*`
- **Ziua săptămânii:** `*`
- **Comandă:**
  ```bash
  /usr/bin/php /home/USERNAME/public_html/alerte-meteo/run.php >> /home/USERNAME/public_html/alerte-meteo/scripts/cron.log 2>&1
  ```
  
  SAU dacă Python e disponibil direct:
  ```bash
  cd /home/USERNAME/public_html/alerte-meteo/scripts && /usr/bin/python3 weather_alerts_cron.py >> cron.log 2>&1
  ```

**Găsește căile corecte:**
- Cale home: verifică în cPanel → File Manager (sus în dreapta)
- Cale Python: rulează `setup.php` pentru a vedea calea

4. Salvează cron job

## ✅ Pasul 3: Test Manual

### Opțiunea 1: Via Browser

Deschide: `https://tazzstudio.ro/alerte-meteo/run.php`
- Ar trebui să vadă procesul de actualizare
- La final, link către hartă

### Opțiunea 2: Via cPanel Terminal (dacă e disponibil)

1. cPanel → **Terminal**
2. Rulează:
```bash
cd public_html/alerte-meteo/scripts
python3 weather_alerts_cron.py
```

## 📊 Pasul 4: Verificare

1. **Hartă:** https://tazzstudio.ro/alerte-meteo/
2. **Status:** https://tazzstudio.ro/alerte-meteo/status.php

Ar trebui să vezi harta cu alertele meteo!

## 🔄 Actualizare Manuală (fără cron)

Dacă nu poți configura cron, poți actualiza manual:
1. Deschide în browser: `https://tazzstudio.ro/alerte-meteo/run.php`
2. SAU folosește un serviciu extern de cron: https://cron-job.org
   - Adaugă URL: `https://tazzstudio.ro/alerte-meteo/run.php`
   - Interval: 5 minute

## 🚨 Troubleshooting

### Python nu funcționează?

**Soluția 1:** Verifică în cPanel → **Select PHP Version** sau **Python Selector**

**Soluția 2:** Contactează hosting support pentru:
- Activare Python3
- Instalare pachete: requests, pyproj, shapely, numpy

**Soluția 3:** Folosește versiunea PHP pură (fără Python) - contactează-mă pentru aceasta

### Eroare "Permission denied"

Via FTP, setează permisiuni:
- Directoare: `755`
- Fișiere `.php`: `644`
- Fișier `.py`: `755`

În FileZilla: Click dreapta → File permissions

### Eroare "500 Internal Server Error"

Verifică:
1. Fișierul `.htaccess` nu are erori
2. PHP version (minim 7.0)
3. Error logs în cPanel → **Error Log**

### Cron job nu rulează

Verifică în cPanel → **Cron Jobs** → email notificări
SAU verifică fișierul de log manual via FTP

## 📧 Notificări Email

În cPanel Cron Jobs, poți seta email pentru notificări:
```
MAILTO="email@tau.com"
```

## 🔐 Securitate

### Protejare setup.php și status.php

După instalare, șterge sau protejează `setup.php`:

Opțiunea 1: **Șterge setup.php** via FTP după instalare

Opțiunea 2: **Protejează cu parola** - creează `.htaccess`:
```apache
<Files "setup.php">
    Order Deny,Allow
    Deny from all
    Allow from YOUR_IP_ADDRESS
</Files>
```

## 📱 Alternative fără Cron

### Servicii externe de Cron:

1. **https://cron-job.org** (gratuit)
2. **https://www.easycron.com** (gratuit cu limită)
3. **https://console.cron-job.org**

Configurează:
- URL: `https://tazzstudio.ro/alerte-meteo/run.php`
- Interval: 5 minute
- Notificări: opțional

## 📞 Suport

Dacă întâmpini probleme:
1. Verifică `status.php` pentru diagnostic
2. Verifică error logs în cPanel
3. Contactează: https://github.com/dancucu/alerte-meteo-romania/issues

---

© 2026 TazzStudio.ro
