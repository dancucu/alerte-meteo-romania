#!/usr/bin/env python3
"""
Script pentru rulare automată pe server via cron
Generează harta și o salvează în directorul public_html
"""

import json
import requests
import re
from datetime import datetime
from pyproj import Transformer
import os
import sys
import hashlib

# Configurare căi pentru server
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

# Detectează dacă suntem în subdirectorul scripts/ sau direct în root
if os.path.basename(SCRIPT_DIR) == 'scripts':
    # Suntem în scripts/, generăm HTML în directorul părinte
    PUBLIC_HTML_DIR = os.path.dirname(SCRIPT_DIR)
else:
    # Suntem în root, generăm în public_html/alerte-meteo
    PUBLIC_HTML_DIR = os.path.join(SCRIPT_DIR, '..', 'public_html', 'alerte-meteo')

CACHE_FILE = os.path.join(SCRIPT_DIR, 'weather_cache.json')
OUTPUT_FILE = os.path.join(PUBLIC_HTML_DIR, 'index.html')
LOG_FILE = os.path.join(SCRIPT_DIR, 'weather_updates.log')

def log_message(message):
    """Scrie mesaj în log și pe ecran"""
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    log_line = f"[{timestamp}] {message}"
    print(log_line)
    
    try:
        with open(LOG_FILE, 'a', encoding='utf-8') as f:
            f.write(log_line + '\n')
    except Exception as e:
        print(f"Eroare scriere log: {e}")

def fetch_weather_alerts():
    """Descarcă avertizările meteo cu fallback triplu"""
    api_url = "https://www.meteoromania.ro/wp-json/meteoapi/v2/avertizari-generale"
    fallback_url = "https://tazzstudio.ro/avertizari-meteo.php"
    
    # NIVEL 1: API nou
    try:
        log_message(f"Încerc API nou: {api_url}")
        response = requests.get(api_url, timeout=10)
        
        if response.status_code == 200:
            log_message("✅ API nou disponibil - cod 200")
            data = response.json()
            
            # Salvează în cache
            with open(CACHE_FILE, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            log_message(f"Cache actualizat: {CACHE_FILE}")
            
            return data
        else:
            log_message(f"⚠️ API nou returnează cod {response.status_code}")
            raise Exception(f"API error: {response.status_code}")
            
    except Exception as e:
        log_message(f"❌ API nou indisponibil: {e}")
        
        # NIVEL 2: Cache
        if os.path.exists(CACHE_FILE):
            try:
                log_message(f"Încerc cache: {CACHE_FILE}")
                with open(CACHE_FILE, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                log_message("✅ Date încărcate din cache")
                return data
            except Exception as cache_error:
                log_message(f"❌ Eroare citire cache: {cache_error}")
        
        # NIVEL 3: API vechi
        try:
            log_message(f"Încerc API vechi: {fallback_url}")
            response = requests.get(fallback_url, timeout=10)
            response.raise_for_status()
            
            html_content = response.text
            log_message("✅ API vechi disponibil")
            
            fallback_data = {"source": "fallback", "html": html_content}
            with open(CACHE_FILE, 'w', encoding='utf-8') as f:
                json.dump(fallback_data, f, ensure_ascii=False, indent=2)
            
            return fallback_data
            
        except Exception as fallback_error:
            log_message(f"❌ API vechi indisponibil: {fallback_error}")
            raise Exception("Toate sursele sunt indisponibile")

def parse_multipolygon(coord_string):
    """Parse coordonatele MULTIPOLYGON și le convertește în WGS84"""
    if not coord_string:
        return []
    
    coord_string = coord_string.replace('MULTIPOLYGON', '')
    coord_string = coord_string.replace('(', '').replace(')', '')
    coord_string = coord_string.strip()
    
    pattern = r'(\d+\.?\d*)\s+(\d+\.?\d*)'
    matches = re.findall(pattern, coord_string)
    
    if not matches or len(matches) < 3:
        return []
    
    transformer = Transformer.from_crs("EPSG:3857", "EPSG:4326", always_xy=True)
    
    polygons = []
    current_polygon = []
    
    step = max(1, len(matches) // 150)
    
    for i, (x, y) in enumerate(matches):
        if i % step != 0 and i != 0 and i != len(matches) - 1:
            continue
            
        try:
            x_float = float(x)
            y_float = float(y)
            
            lon, lat = transformer.transform(x_float, y_float)
            
            if 43.0 <= lat <= 48.5 and 20.0 <= lon <= 30.0:
                current_polygon.append([lon, lat])
        except:
            continue
    
    if len(current_polygon) >= 3:
        if current_polygon[0] != current_polygon[-1]:
            current_polygon.append(current_polygon[0])
        polygons.append(current_polygon)
    
    return polygons

def extract_alert_data(html_content):
    """Extrage datele din răspuns"""
    judete_data = {}
    
    judet_pattern = r'"cod":"([A-Z]{2})","culoare":"(\d+)","useCoordGis":"true","coordGis":"([^"]+)"'
    matches = re.findall(judet_pattern, html_content, re.DOTALL)
    
    tip_mesaj = re.search(r'"numeTipMesaj":"([^"]+)"', html_content)
    culoare_nume = re.search(r'"numeCuloare":"([^"]+)"', html_content)
    fenomene = re.search(r'"fenomeneVizate":"([^"]+)"', html_content)
    data_aparitie = re.search(r'"dataAparitiei":"([^"]+)"', html_content)
    data_expirare = re.search(r'"dataExpir[^"]*":"([^"]+)"', html_content)
    mesaj = re.search(r'"mesaj":"((?:[^"\\\\]|\\\\.)*)"', html_content)
    
    if not matches:
        return None
    
    for cod, culoare, coord_gis in matches:
        if cod not in judete_data:
            judete_data[cod] = {
                'color_code': culoare,
                'coords_gis': coord_gis
            }
    
    mesaj_html = ''
    if mesaj:
        mesaj_html = mesaj.group(1)
        mesaj_html = mesaj_html.replace('\\/', '/').replace('\\"', '"')
        mesaj_html = mesaj_html.replace('\\n', '').replace('\\r', '')
    
    data = {
        'alert_count': 1,
        'alert_info': {
            'type': tip_mesaj.group(1) if tip_mesaj else 'Atenționare meteorologică',
            'color_name': culoare_nume.group(1) if culoare_nume else 'galben',
            'phenomena': fenomene.group(1) if fenomene else 'conform textelor și hărții',
            'start': data_aparitie.group(1) if data_aparitie else '',
            'end': data_expirare.group(1) if data_expirare else '',
            'message': mesaj_html,
        },
        'counties': judete_data
    }
    
    return data

def create_map_html(alerts_data):
    """Creează HTML-ul hărții"""
    if not alerts_data or 'counties' not in alerts_data:
        return None
    
    alert_info = alerts_data.get('alert_info', {})
    
    color_map = {
        '0': {'color': '#90EE90', 'name': 'Verde (Fără alertă)'},
        '1': {'color': '#FFD700', 'name': 'Galben'},
        '2': {'color': '#FFA500', 'name': 'Portocaliu'},
        '3': {'color': '#FF0000', 'name': 'Roșu'},
    }
    
    features = []
    
    log_message("Procesez coordonate...")
    for code, county_data in alerts_data['counties'].items():
        color_code = county_data.get('color_code', '0')
        coords_gis = county_data.get('coords_gis', '')
        
        if color_code == '0' or not coords_gis:
            continue
        
        polygons = parse_multipolygon(coords_gis)
        
        if not polygons or len(polygons[0]) < 3:
            continue
        
        color_info = color_map.get(color_code, color_map['0'])
        
        feature = {
            'type': 'Feature',
            'properties': {
                'code': code,
                'color': color_info['color'],
                'alertLevel': color_info['name'],
                'alertType': alert_info.get('type', ''),
                'phenomena': alert_info.get('phenomena', ''),
                'start': alert_info.get('start', ''),
                'end': alert_info.get('end', ''),
                'message': alert_info.get('message', ''),
            },
            'geometry': {
                'type': 'Polygon',
                'coordinates': [polygons[0]]
            }
        }
        features.append(feature)
    
    geojson_data = {
        'type': 'FeatureCollection',
        'features': features
    }
    
    current_time = datetime.now().strftime("%d.%m.%Y %H:%M")
    
    html_template = f"""<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌦️ Alertă Meteo România - {alert_info.get('type', 'Avertizare')}</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * {{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }}
        body {{
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }}
        .container {{
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }}
        .header {{
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }}
        .header h1 {{
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }}
        .alert-info {{
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }}
        #map {{
            width: 100%;
            height: 600px;
        }}
        .footer {{
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            color: #666;
            font-size: 0.9em;
        }}
        .update-time {{
            margin-top: 10px;
            font-size: 0.9em;
            opacity: 0.9;
        }}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌦️ Alertă Meteo România</h1>
            <div class="alert-info">
                <h2>{alert_info.get('type', 'Atenționare meteorologică')}</h2>
                <p><strong>Fenomene:</strong> {alert_info.get('phenomena', 'conform textelor și hărții')}</p>
                <p><strong>Valabilitate:</strong> {alert_info.get('start', '')} - {alert_info.get('end', '')}</p>
                <p class="update-time">📅 Actualizat: {current_time}</p>
            </div>
        </div>
        
        <div id="map"></div>
        
        <div class="footer">
            <p>📊 Sursă date: Administrația Națională de Meteorologie (ANM)</p>
            <p>🔄 Actualizare automată la fiecare 5 minute | Actualizat automat la {current_time}</p>
            <p>© 2026 <a href="https://tazzstudio.ro" target="_blank">TazzStudio.ro</a> | 
            <a href="https://github.com/dancucu/alerte-meteo-romania" target="_blank">GitHub</a></p>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const geojsonData = {json.dumps(geojson_data, ensure_ascii=False)};
        
        const map = L.map('map').setView([45.9432, 24.9668], 7);
        
        L.tileLayer('https://{{s}}.tile.openstreetmap.org/{{z}}/{{x}}/{{y}}.png', {{
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }}).addTo(map);
        
        L.geoJSON(geojsonData, {{
            style: function(feature) {{
                return {{
                    fillColor: feature.properties.color,
                    weight: 2,
                    opacity: 1,
                    color: '#333',
                    fillOpacity: 0.6
                }};
            }},
            onEachFeature: function(feature, layer) {{
                const props = feature.properties;
                const popupContent = `
                    <div style="min-width: 250px;">
                        <h3>Județ: ${{props.code}}</h3>
                        <p><strong>Nivel:</strong> ${{props.alertLevel}}</p>
                        <p><strong>Tip:</strong> ${{props.alertType}}</p>
                        <p><strong>Fenomene:</strong> ${{props.phenomena}}</p>
                        <p><strong>Valabilitate:</strong><br>${{props.start}} - ${{props.end}}</p>
                    </div>
                `;
                layer.bindPopup(popupContent);
            }}
        }}).addTo(map);
    </script>
</body>
</html>"""
    
    return html_template

def main():
    log_message("="*60)
    log_message("🌦️ START - Generator Hartă Alertă Meteo")
    
    try:
        # Creează directoare dacă nu există
        os.makedirs(PUBLIC_HTML_DIR, exist_ok=True)
        log_message(f"Director output: {PUBLIC_HTML_DIR}")
        
        # Descarcă datele
        json_data = fetch_weather_alerts()
        
        # Procesează datele
        if isinstance(json_data, dict) and json_data.get("source") == "fallback":
            html_content = json_data.get("html", "")
        else:
            html_content = json.dumps(json_data)
        
        alerts_data = extract_alert_data(html_content)
        
        if not alerts_data:
            log_message("❌ Nu am putut extrage datele de alertă")
            return 1
        
        log_message(f"✅ Găsite {len(alerts_data.get('counties', {}))} județe")
        
        # Generează harta
        map_html = create_map_html(alerts_data)
        
        if map_html:
            with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
                f.write(map_html)
            
            file_size = os.path.getsize(OUTPUT_FILE) / 1024
            log_message(f"✅ Hartă generată: {OUTPUT_FILE} ({file_size:.1f} KB)")
            log_message("🌦️ SUCCESS - Hartă actualizată cu succes")
            return 0
        else:
            log_message("❌ Eroare la generarea hărții")
            return 1
            
    except Exception as e:
        log_message(f"❌ EROARE: {e}")
        import traceback
        log_message(traceback.format_exc())
        return 1
    finally:
        log_message("="*60)

if __name__ == "__main__":
    sys.exit(main())
