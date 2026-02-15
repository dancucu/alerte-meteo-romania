import requests
import re
from pyproj import Transformer

url = "https://tazzstudio.ro/avertizari-meteo.php"
response = requests.get(url)
html = response.text

# Găsește primul județ cu coordonate
pattern = r'"cod":"([A-Z]{2})","culoare":"(\d+)","useCoordGis":"true","coordGis":"([^"]+)"'
match = re.search(pattern, html, re.DOTALL)

if match:
    cod = match.group(1)
    coords = match.group(3)
    
    print(f"Județ: {cod}")
    print(f"Lungime coordonate originale: {len(coords)}")
    print(f"Primele 200 caractere:\n{coords[:200]}\n")
    
    # Curăță string-ul
    coords_clean = coords.replace('MULTIPOLYGON', '').replace('(', '').replace(')', '').strip()
    print(f"Lungime după curățare: {len(coords_clean)}")
    print(f"Primele 200 caractere după curățare:\n{coords_clean[:200]}\n")
    
    # Extrage coordonatele
    pattern = r'(\d+\.?\d*)\s+(\d+\.?\d*)'
    matches = re.findall(pattern, coords_clean)
    
    print(f"Număr perechi de coordonate găsite: {len(matches)}")
    if matches:
        print(f"Primele 5 perechi: {matches[:5]}\n")
        
        # Încearcă conversia pentru prima pereche
        transformer = Transformer.from_crs("EPSG:31700", "EPSG:4326", always_xy=True)
        x, y = float(matches[0][0]), float(matches[0][1])
        print(f"Prima coordonată Stereo70: X={x}, Y={y}")
        
        lon, lat = transformer.transform(x, y)
        print(f"După conversie WGS84: lon={lon}, lat={lat}")
        print(f"Valid pentru România? {43.0 <= lat <= 48.5 and 20.0 <= lon <= 30.0}")
else:
    print("Nu s-au găsit date")
