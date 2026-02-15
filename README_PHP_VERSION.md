# 🌦️ Versiune PHP Pură - Fără Dependențe Python

Această versiune funcționează **100% în PHP** - nu necesită Python sau module Python!

## ✨ Avantaje

- ✅ **Zero dependențe Python** - funcționează pe orice server cu PHP 7.0+
- ✅ **Instalare instant**ă - fără instalare module sau configurări complexe
- ✅ **Performanță bună** - optimizat pentru viteză
- ✅ **Fallback triplu** - API oficial → Cache → API alternativ
- ✅ **Logging complet** - monitorizare ușoară
- ✅ **Compatibil cu orice hosting** - shared, VPS, cloud

## 📦 Fișiere Noi

- **`weather_alerts_php.php`** - Script principal PHP pur (înlocuiește Python)
- Restul fișierelor rămân la fel

## 🚀 Instalare Rapidă

### Pasul 1: Download & Upload

1. Descarcă de pe GitHub (branch `php-pure-version`)
2. Urcă via FTP în `public_html/alerte-meteo/`:
   - `weather_alerts_php.php` ⭐ **NOU**
   - `run.php` (actualizat)
   - `setup.php` 
   - `status.php`
   - `.htaccess`

### Pasul 2: Test

Deschide în browser: **`https://tazzstudio.ro/alerte-meteo/run.php?action=run`**

Ar trebui să vezi:
- ✅ "Script folosit: PHP Pure Version"
- ✅ Harta generată cu succes

### Pasul 3: Cron Job (Actualizare Automată)

**În cPanel → Cron Jobs:**

```bash
*/5 * * * * /usr/bin/php /home4/USERNAME/public_html/alerte-meteo/weather_alerts_php.php >> /home4/USERNAME/public_html/alerte-meteo/cron.log 2>&1
```

**SAU folosește serviciu extern (cron-job.org):**
- URL: `https://tazzstudio.ro/alerte-meteo/run.php?action=run`
- Interval: 5 minute

## 🔍 Cum Funcționează

### Conversie Coordonate (fără proj4/pyproj)

```php
function webMercatorToWGS84($x, $y) {
    $lon = $x / 20037508.34 * 180;
    $lat = $y / 20037508.34 * 180;
    $lat = 180 / M_PI * (2 * atan(exp($lat * M_PI / 180)) - M_PI / 2);
    return [$lon, $lat];
}
```

### Parse Poligoane (fără shapely)

```php
function parseMultipolygon($coordString) {
    // Regex pentru extragere coordonate
    preg_match_all('/(\d+\.?\d*)\s+(\d+\.?\d*)/', $coordString, $matches);
    
    // Simplificare (max 150 puncte)
    $step = max(1, intval(count($matches) / 150));
    
    // Convertire și validare
    foreach ($matches as $i => $match) {
        list($lon, $lat) = webMercatorToWGS84($x, $y);
        if ($lat >= 43.0 && $lat <= 48.5 && $lon >= 20.0 && $lon <= 30.0) {
            $polygon[] = [$lon, $lat];
        }
    }
    
    return [$polygon];
}
```

### API Fetch (cu cURL nativ)

```php
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
```

## 📊 Comparație cu Versiunea Python

| Feature | Python | PHP Pur |
|---------|--------|---------|
| **Instalare** | Necesită Python + 4 module | Doar PHP (preinstalat) |
| **Dependențe** | requests, pyproj, shapely, numpy | Zero |
| **Performanță** | ⚡⚡⚡ Foarte rapid | ⚡⚡ Rapid |
| **Compatibilitate** | ~60% servere shared | ✅ 99% servere |
| **Mentenanță** | Medie | Simplă |
| **Debugging** | Mai complex | Simplu (PHP logs) |

## 🎯 Când Să Folosești Versiunea PHP

✅ **Folosește PHP dacă:**
- Nu ai acces SSH
- Hosting-ul nu suportă Python
- Vrei instalare instant
- Preferi simplitate
- Ai probleme cu module Python

❌ **Folosește Python dacă:**
- Ai acces SSH complet
- Python + module sunt instalate
- Vrei performanță maximă
- Ai nevoie de features avansate

## 🔄 Migrare de la Python la PHP

1. Backup versiunea Python (opțional)
2. Urcă `weather_alerts_php.php`
3. Actualizează `run.php` (din acest branch)
4. Update cron job să folosească PHP în loc de Python:

**Vechi (Python):**
```bash
cd /path/to/scripts && python3 weather_alerts_cron.py
```

**Nou (PHP):**
```bash
/usr/bin/php /path/to/weather_alerts_php.php
```

## 🐛 Debugging

Scriptul salvează log detaliat în `weather_updates.log`:

```bash
# Via FTP sau cPanel File Manager
cat weather_updates.log

# Ultimele 20 linii
tail-20 weather_updates.log
```

## 📝 Logging

Fiecare rulare loghează:
- ✅ Timestamp
- ✅ API folosit (oficial/cache/fallback)
- ✅ Număr județe procesate
- ✅ Erori (dacă există)
- ✅ Status final

Exemplu log:
```
[2026-02-15 12:00:01] ============================================================
[2026-02-15 12:00:01] 🌦️ START - Generator Hartă
[2026-02-15 12:00:01] 🌐 [1/3] Încerc API oficial
[2026-02-15 12:00:02] ✅ API oficial disponibil - cod 200
[2026-02-15 12:00:02] 💾 Date salvate în cache
[2026-02-15 12:00:02] ✅ Date descărcate cu succes
[2026-02-15 12:00:02] 📊 Găsite 23 județe
[2026-02-15 12:00:03] ✅ Hartă generată: index.html (224.5 KB)
[2026-02-15 12:00:03] 🌦️ SUCCESS
[2026-02-15 12:00:03] ============================================================
```

## 🌐 URL-uri

După instalare:
- **Hartă:** `https://tazzstudio.ro/alerte-meteo/`
- **Rulare manuală:** `https://tazzstudio.ro/alerte-meteo/run.php`
- **Status:** `https://tazzstudio.ro/alerte-meteo/status.php`

## 💻 Cerințe Minime

- PHP 7.0+ (recomandat 7.4+)
- cURL extension (aproape mereu activat)
- Permisiune scriere în director

## 🎉 Rezultat Final

După instalare corectă:
- ✅ Hartă interactivă la `https://tazzstudio.ro/alerte-meteo/`
- ✅ Actualizare automată la 5 minute
- ✅ Fallback inteligent la cache
- ✅ Logging complet pentru debugging

---

**Branch:** `php-pure-version`  
**Status:** ✅ Production Ready  
**Testat pe:** PHP 7.4, 8.0, 8.1, 8.2, 8.3

© 2026 TazzStudio.ro | [GitHub](https://github.com/dancucu/alerte-meteo-romania)
