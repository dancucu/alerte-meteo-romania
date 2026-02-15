# 🌦️ Alertă Meteo România - Generator Hartă Interactivă

Generator automat de hartă interactivă cu avertizări meteo pentru România, bazat pe datele oficiale de la ANM (Administrația Națională de Meteorologie).

## 🚀 Caracteristici

- ✅ **Monitoring automat** - Verifică API-ul la fiecare 5 minute
- ✅ **Sistem de fallback triplu** - API oficial → Cache local → API alternativ
- ✅ **Hartă interactivă** - Vizualizare cu Leaflet.js și OpenStreetMap
- ✅ **Detectare modificări** - Notificare când apar noi avertizări
- ✅ **Cache inteligent** - Salvare automată pentru disponibilitate offline

## 📋 Cerințe

```bash
pip install requests pyproj shapely numpy
```

## 🎯 Utilizare

### 💻 Local (Desktop)

```bash
python3 weather_alerts_map.py
```

Scriptul va:
1. Încerca să descarce datele de la API-ul oficial ANM
2. Dacă API-ul nu e disponibil, va folosi cache-ul local
3. Dacă nu există cache, va folosi API-ul alternativ
4. Va genera fișierul `weather_alerts_map.html` cu harta interactivă
5. Va deschide automat harta în browser
6. Va monitoriza API-ul la fiecare 5 minute

### 🌐 Server (Productie)

Pentru deployment pe server web (ex: tazzstudio.ro/alerte-meteo):

1. Urcă fișierele din directorul `server/` pe serverul tău
2. Rulează scriptul de instalare:

```bash
cd server
chmod +x install.sh
./install.sh
```

3. Sau instalează manual - vezi [server/README_SERVER.md](server/README_SERVER.md)

**Live demo:** https://tazzstudio.ro/alerte-meteo/

## 🛑 Oprire

Apasă `Ctrl+C` pentru a opri monitoring-ul.

## 📁 Structura proiectului

- `weather_alerts_map.py` - Script principal cu monitoring automat
- `weather_alerts_map_v1.0.0.py` - Versiune anterioară (backup)
- `avertizari-meteo.php` - Script PHP pentru API alternativ
- `debug_coords*.py`, `test_epsg.py` - Scripturi de test/debug

## 🌐 Surse date

- **API Oficial**: `https://www.meteoromania.ro/wp-json/meteoapi/v2/avertizari-generale`
- **API Alternativ**: `https://tazzstudio.ro/avertizari-meteo.php`

## 📊 Fișiere generate

- `weather_alerts_map.html` - Hartă interactivă (ignorat în git)
- `weather_cache.json` - Cache local (ignorat în git)

## 🎨 Coduri culori alertă

- 🟢 **Verde** - Fără alertă
- 🟡 **Galben** - Atenționare
- 🟠 **Portocaliu** - Cod galben
- 🔴 **Roșu** - Cod roșu

## 📝 Licență

MIT License

---

Creat de [Dan Cucu](https://github.com/dancucu) © 2026
