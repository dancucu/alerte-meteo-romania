# 🚨 Troubleshooting - HTML nu s-a generat

Dacă vezi eroarea **"HTML nu s-a generat"** după ce ai rulat `setup.php`, urmează acești pași:

## 🔍 Pas 1: Rulează Debug Tool

1. Urcă fișierul `debug.php` în directorul `alerte-meteo/`
2. Deschide în browser: `https://tazzstudio.ro/alerte-meteo/debug.php`
3. Apasă butonul **"▶️ Rulează Test Script"**
4. Citește cu atenție output-ul

## 📋 Cauze Comune & Soluții

### ❌ Problema 1: Python nu este instalat

**Simptom:** Debug tool arată "Python nu a fost găsit"

**Soluție:**
1. Contactează hosting support: *"Vă rog să activați Python 3.7+ și modulele: requests, pyproj, shapely, numpy"*
2. SAU folosește **Soluția Alternativă** (vezi mai jos)

### ❌ Problema 2: Module Python lipsesc

**Simptom:** Output-ul arată "ModuleNotFoundError: No module named 'requests'"

**Soluție:**
1. Dacă ai acces la cPanel Terminal:
   ```bash
   pip3 install --user requests pyproj shapely numpy
   ```

2. SAU contactează hosting support pentru instalare module

3. SAU folosește **Soluția Alternativă** (vezi mai jos)

### ❌ Problema 3: Permisiuni incorecte

**Simptom:** "Permission denied" în output

**Soluție via FTP:**
1. Click dreapta pe directorul `alerte-meteo/` → Permisiuni → `755`
2. Click dreapta pe `scripts/` → Permisiuni → `755`
3. Click dreapta pe `weather_alerts_cron.py` → Permisiuni → `755`
4. Click dreapta pe fișierele `.php` → Permisiuni → `644`

### ❌ Problema 4: API-ul nu răspunde

**Simptom:** Output arată "Toate sursele sunt indisponibile"

**Soluție:**
- Rulează scriptul din nou peste câteva minute
- API-ul meteoromania.ro poate fi temporar indisponibil
- După prima rulare reușită, se va crea cache

### ❌ Problema 5: shell_exec dezactivat

**Simptom:** Debug tool arată "shell_exec() dezactivată"

**Soluție:**
- Contactează hosting support: *"Vă rog să activați funcția shell_exec() pentru a putea rula scripturi Python"*
- SAU folosește **Soluția Alternativă**

## 🔄 Soluție Alternativă: FĂRĂ Python

Dacă Python nu funcționează deloc sau nu poate fi activat:

### Opțiunea A: API Direct în PHP

Te pot ajuta să creez o versiune 100% PHP (fără Python) care:
- Descarcă datele direct în PHP
- Procesează coordonatele în PHP  
- Generează HTML-ul în PHP
- **Dezavantaj:** Mai lent și mai simplu decât versiunea Python

### Opțiunea B: Serviciu Extern

Folosește un server intermediar care:
1. Rulează scriptul Python pe un server cu Python
2. Generează HTML-ul
3. Îl salvează pe tazzstudio.ro via FTP/API

Pot configura asta pe un server gratuit (Railway, Heroku, PythonAnywhere)

## 📞 Cere Ajutor

Dacă nu reușești să rezolvi:

1. **Rulează debug.php** și copiază tot output-ul
2. **Creează Issue pe GitHub:** https://github.com/dancucu/alerte-meteo-romania/issues
3. Include:
   - Output-ul complet din debug.php
   - Tipul de hosting (shared/VPS/cloud)
   - Numele furnizorului de hosting (dacă posibil)

## ✅ Verificare Rapidă (Checklist)

Înainte de a contacta support, verifică:

- [ ] Am urcat toate fișierele din `server/` în `public_html/alerte-meteo/`
- [ ] Fișierul `weather_alerts_cron.py` este în `alerte-meteo/scripts/`
- [ ] Am setat permisiuni 755 pentru directoare
- [ ] Am setat permisiuni 644 pentru fișiere PHP
- [ ] Am setat permisiuni 755 pentru fișiere Python
- [ ] Am rulat `debug.php` și am citit output-ul
- [ ] Am testat să rulez `setup.php` din nou după ajustări

## 🎯 Test Manual Python (cPanel Terminal)

Dacă ai acces la Terminal în cPanel:

```bash
# Navighează la directorul scripts
cd ~/public_html/alerte-meteo/scripts

# Verifică Python
python3 --version

# Testează modulele
python3 -c "import requests; print('✅ requests OK')"
python3 -c "import pyproj; print('✅ pyproj OK')"
python3 -c "import shapely; print('✅ shapely OK')"
python3 -c "import numpy; print('✅ numpy OK')"

# Rulează scriptul
python3 weather_alerts_cron.py

# Verifică dacă s-a generat HTML
ls -la ../index.html
```

## 💡 Test Rapid: Generează HTML Manual

Pentru a testa dacă problema e legată de script sau de Python, încearcă să creezi un HTML simplu manual:

```bash
# În cPanel Terminal sau via SSH
cd ~/public_html/alerte-meteo
echo "<h1>Test</h1>" > index.html
```

Apoi verifică: `https://tazzstudio.ro/alerte-meteo/`

Dacă vezi "Test" → problema e în script
Dacă NU vezi nimic → problema e în config server/permisiuni

---

© 2026 TazzStudio.ro | [GitHub](https://github.com/dancucu/alerte-meteo-romania)
