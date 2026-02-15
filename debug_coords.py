import requests
import re

url = "https://tazzstudio.ro/avertizari-meteo.php"
response = requests.get(url)
html = response.text

# Găsește primul județ cu coordonate
pattern = r'"cod":"([A-Z]{2})","culoare":"(\d+)","useCoordGis":"true","coordGis":"([^"]+)"'
match = re.search(pattern, html, re.DOTALL)

if match:
    cod = match.group(1)
    culoare = match.group(2)
    coords = match.group(3)
    
    print(f"Județ: {cod}")
    print(f"Culoare: {culoare}")
    print(f"Lungime coordonate: {len(coords)}")
    print(f"Primele 500 caractere:\n{coords[:500]}")
else:
    print("Nu s-au găsit date")
    
    # Încearcă alt pattern
    pattern2 = r'\{"@attributes":\{"cod":"([A-Z]{2})","culoare":"(\d+)"'
    matches = re.findall(pattern2, html)
    print(f"\nGăsite {len(matches)} județe cu pattern2")
    if matches:
        print(f"Exemple: {matches[:5]}")
