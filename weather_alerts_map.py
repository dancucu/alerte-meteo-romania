#!/usr/bin/env python3
"""
Generator de hartă interactivă cu avertizări meteo pentru România
"""

import json
import requests
import re
from xml.etree import ElementTree as ET
from datetime import datetime
from pyproj import Transformer
from shapely.geometry import shape, mapping
import numpy as np
import os
import time
import hashlib

def fetch_weather_alerts():
    """Descarcă avertizările meteo cu fallback triplu: API nou → Cache → API vechi"""
    api_url = "https://www.meteoromania.ro/wp-json/meteoapi/v2/avertizari-generale"
    fallback_url = "https://tazzstudio.ro/avertizari-meteo.php"
    cache_file = "weather_cache.json"
    
    # NIVEL 1: Încearcă API-ul nou
    try:
        print(f"🌐 [1/3] Încerc API nou: {api_url}", flush=True)
        response = requests.get(api_url, timeout=10)
        
        if response.status_code == 200:
            print("✅ API nou disponibil - cod 200", flush=True)
            data = response.json()
            
            # Salvează în cache
            with open(cache_file, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            print(f"💾 Date salvate în cache: {cache_file}", flush=True)
            
            return data
        else:
            print(f"⚠️  API nou returnează cod {response.status_code}", flush=True)
            raise Exception(f"API error: {response.status_code}")
            
    except Exception as e:
        print(f"❌ API nou indisponibil: {e}", flush=True)
        
        # NIVEL 2: Încearcă cache-ul
        if os.path.exists(cache_file):
            try:
                print(f"📂 [2/3] Încerc cache: {cache_file}", flush=True)
                with open(cache_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                print("✅ Date încărcate din cache", flush=True)
                return data
            except Exception as cache_error:
                print(f"❌ Eroare citire cache: {cache_error}", flush=True)
        else:
            print(f"⚠️  Nu există cache: {cache_file}", flush=True)
        
        # NIVEL 3: Fallback la API-ul vechi
        try:
            print(f"🔄 [3/3] Încerc API vechi (fallback): {fallback_url}", flush=True)
            response = requests.get(fallback_url, timeout=10)
            response.raise_for_status()
            
            # API-ul vechi returnează HTML cu JSON embedded
            html_content = response.text
            print("✅ API vechi disponibil", flush=True)
            
            # Convertim în format cache pentru următoarea rulare
            fallback_data = {"source": "fallback", "html": html_content}
            with open(cache_file, 'w', encoding='utf-8') as f:
                json.dump(fallback_data, f, ensure_ascii=False, indent=2)
            print(f"💾 Date fallback salvate în cache", flush=True)
            
            return fallback_data
            
        except Exception as fallback_error:
            print(f"❌ API vechi indisponibil: {fallback_error}", flush=True)
            raise Exception("Toate sursele sunt indisponibile (API nou, cache, API vechi)")

def parse_multipolygon(coord_string):
    """Parse coordonatele MULTIPOLYGON și le convertește din Web Mercator în WGS84"""
    if not coord_string:
        return []
    
    # Curăță string-ul de MULTIPOLYGON prefix și paranteze
    coord_string = coord_string.replace('MULTIPOLYGON', '')
    coord_string = coord_string.replace('(', '').replace(')', '')
    coord_string = coord_string.strip()
    
    # Extrage coordonatele - pattern pentru numere în format X Y
    pattern = r'(\d+\.?\d*)\s+(\d+\.?\d*)'
    matches = re.findall(pattern, coord_string)
    
    if not matches or len(matches) < 3:
        return []
    
    # Inițializează transformer-ul pentru conversie Web Mercator (EPSG:3857) -> WGS84 (EPSG:4326)
    transformer = Transformer.from_crs("EPSG:3857", "EPSG:4326", always_xy=True)
    
    # Convertim coordonatele
    polygons = []
    current_polygon = []
    
    # Simplificăm numărul de puncte pentru performanță
    step = max(1, len(matches) // 150)  # Maxim 150 puncte per poligon
    
    for i, (x, y) in enumerate(matches):
        # Păstrăm primul, ultimul și fiecare al N-lea punct
        if i % step != 0 and i != 0 and i != len(matches) - 1:
            continue
            
        try:
            x_float = float(x)
            y_float = float(y)
            
            # Conversie din Stereo70 în WGS84 (lat, lon)
            lon, lat = transformer.transform(x_float, y_float)
            
            # Verifică dacă coordonatele sunt valide pentru România
            if 43.0 <= lat <= 48.5 and 20.0 <= lon <= 30.0:
                current_polygon.append([lon, lat])
        except Exception as e:
            continue
    
    if len(current_polygon) >= 3:  # Un poligon valid trebuie să aibă cel puțin 3 puncte
        # Închide poligonul (primul punct = ultimul punct)
        if current_polygon[0] != current_polygon[-1]:
            current_polygon.append(current_polygon[0])
        polygons.append(current_polygon)
    
    return polygons

def extract_alert_data(html_content):
    """Extrage datele structurate din răspunsul HTML"""
    # Caută județele cu cod, culoare și coordonate GIS
    judete_data = {}
    
    # Pattern pentru a găsi toate județele cu atributele lor
    # Căutăm secțiuni "judet" cu cod, culoare și coordGis
    judet_pattern = r'"cod":"([A-Z]{2})","culoare":"(\d+)","useCoordGis":"true","coordGis":"([^"]+)"'
    matches = re.findall(judet_pattern, html_content, re.DOTALL)
    
    # Extrage informații despre avertizare
    tip_mesaj = re.search(r'"numeTipMesaj":"([^"]+)"', html_content)
    culoare_nume = re.search(r'"numeCuloare":"([^"]+)"', html_content)
    fenomene = re.search(r'"fenomeneVizate":"([^"]+)"', html_content)
    data_aparitie = re.search(r'"dataAparitiei":"([^"]+)"', html_content)
    data_expirare = re.search(r'"dataExpir[^"]*":"([^"]+)"', html_content)
    # Regex îmbunătățit pentru mesaj care gestionează corect escape sequences
    mesaj = re.search(r'"mesaj":"((?:[^"\\]|\\.)*)"', html_content)
    
    if not matches:
        return None
    
    # Construiește date structurate
    for cod, culoare, coord_gis in matches:
        if cod not in judete_data:
            judete_data[cod] = {
                'color_code': culoare,
                'coords_gis': coord_gis
            }
    
    # Curăță mesajul HTML de escape characters
    mesaj_html = ''
    if mesaj:
        mesaj_html = mesaj.group(1)
        # Decodifică escape sequences
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
    """Creează fișierul HTML cu harta interactivă"""
    
    if not alerts_data or 'counties' not in alerts_data:
        print("Nu există date de alertă")
        return None
    
    alert_info = alerts_data.get('alert_info', {})
    
    # Mapare culori alertă
    color_map = {
        '0': {'color': '#90EE90', 'name': 'Verde (Fără alertă)'},
        '1': {'color': '#FFD700', 'name': 'Galben'},
        '2': {'color': '#FFA500', 'name': 'Portocaliu'},
        '3': {'color': '#FF0000', 'name': 'Roșu'},
    }
    
    # Creează GeoJSON features pentru județe
    features = []
    
    print("📍 Procesez coordonatele județelor...")
    for code, county_data in alerts_data['counties'].items():
        color_code = county_data.get('color_code', '0')
        coords_gis = county_data.get('coords_gis', '')
        
        # Sari peste județele cu cod verde (fără alertă)
        if color_code == '0':
            print(f"   ⚪ {code}: Fără alertă (verde) - ignorat")
            continue
        
        if not coords_gis:
            print(f"   ⚠️  {code}: Lipsesc coordonate GIS")
            continue
        
        print(f"   🗺️  {code}: Procesez poligonul...", end='')
        
        # Parse și convertește coordonatele
        polygons = parse_multipolygon(coords_gis)
        
        if not polygons or len(polygons[0]) < 3:
            print(" ❌ Eroare")
            continue
        
        print(f" ✅ {len(polygons[0])} puncte")
        
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
    
    # Creează HTML-ul
    html_template = f"""<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avertizări Meteo România - {datetime.now().strftime('%d.%m.%Y')}</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        * {{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }}
        
        body {{
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }}
        
        #header {{
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }}
        
        #header h1 {{
            font-size: 28px;
            margin-bottom: 5px;
        }}
        
        #header p {{
            opacity: 0.9;
            font-size: 14px;
        }}
        
        #map {{
            height: calc(100vh - 90px);
            width: 100%;
        }}
        
        .legend {{
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 250px;
        }}
        
        .legend h4 {{
            margin-bottom: 10px;
            color: #333;
            font-size: 16px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 5px;
        }}
        
        .legend-item {{
            display: flex;
            align-items: center;
            margin: 8px 0;
        }}
        
        .legend-color {{
            width: 30px;
            height: 20px;
            margin-right: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }}
        
        .popup-content {{
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-width: 250px;
        }}
        
        .popup-content h3 {{
            color: #667eea;
            margin-bottom: 10px;
            font-size: 18px;
        }}
        
        .popup-content p {{
            margin: 5px 0;
            font-size: 13px;
            line-height: 1.5;
        }}
        
        .popup-content strong {{
            color: #333;
        }}
        
        .alert-badge {{
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin: 5px 0;
            color: #333;
        }}
    </style>
</head>
<body>
    <div id="header">
        <h1>🌦️ Avertizări Meteo România</h1>
        <p>Hartă interactivă cu contururi reale • {datetime.now().strftime('%d %B %Y, %H:%M')}</p>
    </div>
    
    <div id="map"></div>
    
    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        // Inițializare hartă
        const map = L.map('map').setView([45.9432, 24.9668], 7);
        
        // Adaugă tile layer (OpenStreetMap)
        L.tileLayer('https://{{s}}.tile.openstreetmap.org/{{z}}/{{x}}/{{y}}.png', {{
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18,
        }}).addTo(map);
        
        // Datele GeoJSON
        const geojsonData = {json.dumps(geojson_data, indent=2, ensure_ascii=False)};
        
        // Stilizare pentru fiecare feature
        function style(feature) {{
            return {{
                fillColor: feature.properties.color,
                weight: 2,
                opacity: 1,
                color: '#333',
                dashArray: '',
                fillOpacity: 0.6
            }};
        }}
        
        // Highlight on hover
        function highlightFeature(e) {{
            const layer = e.target;
            layer.setStyle({{
                weight: 4,
                color: '#666',
                dashArray: '',
                fillOpacity: 0.8
            }});
            
            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {{
                layer.bringToFront();
            }}
        }}
        
        function resetHighlight(e) {{
            geojsonLayer.resetStyle(e.target);
        }}
        
        // Click pentru zoom
        function zoomToFeature(e) {{
            map.fitBounds(e.target.getBounds());
        }}
        
        // Popup cu informații
        function onEachFeature(feature, layer) {{
            const props = feature.properties;
            
            const popupContent = `
                <div class="popup-content">
                    <h3>🏛️ Județul ${{props.code}}</h3>
                    <div class="alert-badge" style="background-color: ${{props.color}};">
                        ${{props.alertLevel}}
                    </div>
                    <p><strong>📋 Tip:</strong> ${{props.alertType}}</p>
                    <p><strong>⚠️ Fenomene:</strong> ${{props.phenomena}}</p>
                    <p><strong>⏰ Început:</strong> ${{props.start}}</p>
                    <p><strong>⏱️ Sfârșit:</strong> ${{props.end}}</p>
                    ${{props.message ? `<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">${{props.message}}</div>` : ''}}
                </div>
            `;
            
            layer.bindPopup(popupContent);
            
            // Tooltip pentru hover
            layer.bindTooltip(`<b>${{props.code}}</b><br>${{props.alertLevel}}`, {{
                permanent: false,
                direction: 'center',
                className: 'county-tooltip'
            }});
            
            layer.on({{
                mouseover: highlightFeature,
                mouseout: resetHighlight,
                click: zoomToFeature
            }});
        }}
        
        // Adaugă GeoJSON pe hartă
        const geojsonLayer = L.geoJSON(geojsonData, {{
            style: style,
            onEachFeature: onEachFeature
        }}).addTo(map);
        
        // Ajustează view-ul la toate feature-urile
        if (geojsonData.features.length > 0) {{
            map.fitBounds(geojsonLayer.getBounds(), {{padding: [20, 20]}});
        }}
        
        // Adaugă legenda
        const legend = L.control({{position: 'bottomright'}});
        
        legend.onAdd = function (map) {{
            const div = L.DomUtil.create('div', 'legend');
            div.innerHTML = `
                <h4>🎨 Nivel Alertă</h4>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #90EE90;"></div>
                    <span>Verde</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #FFD700;"></div>
                    <span>Galben</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #FFA500;"></div>
                    <span>Portocaliu</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #FF0000;"></div>
                    <span>Roșu</span>
                </div>
                <p style="margin-top: 10px; font-size: 11px; color: #666;">
                    🖱️ Click pe județ pentru zoom<br>
                    📍 Hover pentru detalii rapide
                </p>
            `;
            return div;
        }};
        
        legend.addTo(map);
        
        console.log('✅ Hartă încărcată cu succes!');
        console.log(`📊 Județe afișate: ${{geojsonData.features.length}}`);
    </script>
</body>
</html>"""
    
    return html_template

def get_data_hash(data):
    """Calculează hash pentru a detecta modificări în date"""
    return hashlib.md5(json.dumps(data, sort_keys=True).encode()).hexdigest()

def process_and_generate_map(open_browser=False):
    """Procesează datele și generează harta"""
    try:
        json_data = fetch_weather_alerts()
        print("✅ Date descărcate cu succes!", flush=True)
        
        print("📊 Procesez datele...", flush=True)
        
        # Verifică dacă datele sunt din fallback (HTML) sau API nou (JSON)
        if isinstance(json_data, dict) and json_data.get("source") == "fallback":
            html_content = json_data.get("html", "")
        else:
            # Convertim JSON-ul în format HTML pentru funcția existentă
            html_content = json.dumps(json_data)
        
        alerts_data = extract_alert_data(html_content)
        
        if not alerts_data:
            print("❌ Nu am putut extrage datele de alertă", flush=True)
            return None
        
        print(f"📋 Găsite {alerts_data.get('alert_count', 0)} alertă(e)", flush=True)
        print(f"📍 Județe afectate: {len(alerts_data.get('counties', {}))}", flush=True)
        
        print("🗺️  Generez harta interactivă...", flush=True)
        map_html = create_map_html(alerts_data)
        
        if map_html:
            output_file = "weather_alerts_map.html"
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(map_html)
            
            print(f"✅ Harta a fost generată cu succes!", flush=True)
            print(f"📁 Fișier: {output_file}", flush=True)
            
            # Deschide în browser doar la prima rulare
            if open_browser:
                print(f"\n🌐 Deschide fișierul în browser pentru a vizualiza harta!", flush=True)
                import webbrowser
                file_path = os.path.abspath(output_file)
                webbrowser.open('file://' + file_path)
            
            return get_data_hash(json_data)
        else:
            print("❌ Eroare la generarea hărții", flush=True)
            return None
            
    except Exception as e:
        print(f"❌ Eroare: {e}", flush=True)
        return None

def main():
    print("🌦️  Generator Hartă Avertizări Meteo România")
    print("🔄 Mod monitoring activ: verificare la fiecare 5 minute")
    print("⏹️  Apasă Ctrl+C pentru a opri\n")
    print("="*60, flush=True)
    
    last_hash = None
    first_run = True
    
    while True:
        try:
            current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            print(f"\n⏰ [{current_time}] Verific API-ul...", flush=True)
            
            current_hash = process_and_generate_map(open_browser=first_run)
            
            if current_hash:
                if last_hash and current_hash != last_hash:
                    print("🔔 MODIFICARE DETECTATĂ! Datele s-au actualizat.", flush=True)
                elif last_hash:
                    print("ℹ️  Nicio modificare - datele sunt la fel.", flush=True)
                
                last_hash = current_hash
            
            first_run = False
            
            print(f"\n⏳ Următoarea verificare în 5 minute...", flush=True)
            print("="*60, flush=True)
            time.sleep(300)  # 5 minute = 300 secunde
            
        except KeyboardInterrupt:
            print("\n\n👋 Monitoring oprit de utilizator. La revedere!", flush=True)
            break
        except Exception as e:
            print(f"\n❌ Eroare neașteptată: {e}", flush=True)
            print(f"⏳ Reîncerc în 5 minute...", flush=True)
            time.sleep(300)

if __name__ == "__main__":
    main()
