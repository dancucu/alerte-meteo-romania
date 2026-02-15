# Per-County Messages Branch

## 📋 Overview

Noul branch `per-county-messages` conține suport pentru **mesaje separate per județ** din răspunsul API meteorologic.

## 🔧 Funcționalități Adăugate

### 1. **Extracție Mesaje per Județ** 
- Nouă funcție `extractPerCountyMessages()` parseaza răspunsul API
- Caută pattern: `"cod":"XX"` + `"mesaj":"..."` pentru fiecare județ
- Stochează mesajele într-un array asociativ `$perCountyMessages[codJudet]`

### 2. **Procesare în extractAlertData()**
- Pentru fiecare județ, verifică dacă are mesaj specific
- Dacă NU are mesaj specific, folosește mesajul GLOBAL
- Structura: 
  ```php
  $judeteData[$cod] = [
      'color_code' => '2',
      'coords_gis' => 'MULTIPOLYGON(...)',
      'message' => 'Mesaj specific pentru acest județ'
  ];
  ```

### 3. **Afișare în Popup**
- Fiecare județ arată propriul mesaj în popup
- Template-ul JavaScript usando `props.message` din feature properties
- Dacă mesajul e gol, afișează "Nu sunt detalii suplimentare"

## 📊 Structură API Așteptată

Scriptul caută în răspunsul JSON:

```json
{
  "judet": {
    "cod": "BR",
    "culoare": "2",
    "mesaj": "Atenție la furtuni puternice cu grindină"
  }
}
```

Dar acceptă și format simplu dacă structura e diferită.

## 🚀 Cum se Folosește

1. **Checkout branch-ul:**
   ```bash
   git checkout per-county-messages
   ```

2. **Upload pe server:**
   ```bash
   # Opțional: testează local cu PHP development server
   php -r "include 'server/weather_alerts_php.php';"
   ```

3. **Monitorizare:**
   - Verifica `status.php` pentru logs
   - Click pe județ → Vezi mesajul specific

## 📝 Limitări Curente

- Mesajele per județ sunt parseate doar dacă API le furnizează în format specific
- Pentru acum, mayoritate alertelor folosesc **mesajul GLOBAL** (same for all counties)
- Funcția `extractPerCountyMessages()` e _ready but not commonly used_ până când API o suportă mai bine

## 🔮 Roadmap

- [ ] Suport pentru mesaje **completamente diferite** per județ (ex: ploi în vest, secetă în est)
- [ ] Caching pe nivel de județ (nu global)
- [ ] Interface pentru editarea mesajelor pe nivel administrativ
- [ ] API endpoint separate pentru mesaje per județ

## 🔗 Referință

- **Branch main**: Python version (deprecated)
- **Branch php-pure-version**: PHP cu date formatate în Română (current production)
- **Branch cache-only-version**: PHP fără fallback API
- **Branch per-county-messages**: THIS BRANCH - mesaje separate per județ

## ✅ Testing

```bash
# Test parsing mesaje
grep -n "extractPerCountyMessages" server/weather_alerts_php.php

# Run script manual
curl "https://tazzstudio.ro/alerte-meteo/run.php?action=run"

# Check output
tail -50 server/weather_updates.log
```

---

**Edited:** 2026-02-15  
**Author:** Coding Agent  
**Status:** Development / Ready for Per-County Message Integration
