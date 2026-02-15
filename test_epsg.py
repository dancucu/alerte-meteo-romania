from pyproj import Transformer

# Test cu o coordonată din CT
x, y = 3165347.542734, 5570313.778848

# Testăm diferite EPSG-uri
epsg_list = [
    ("31700", "Stereo70 standard"),
    ("3844", "Pulkovo 1942 / Stereo70"),  
    ("3035", "ETRS89 / LAEA Europe"),
    ("3857", "Web Mercator"),
    ("32634", "WGS 84 / UTM zone 34N"),
    ("32635", "WGS 84 / UTM zone 35N"),
]

print(f"Testez coordonata X={x}, Y={y}\n")

for epsg, name in epsg_list:
    try:
        transformer = Transformer.from_crs(f"EPSG:{epsg}", "EPSG:4326", always_xy=True)
        lon, lat = transformer.transform(x, y)
        valid = 43.0 <= lat <= 48.5 and 20.0 <= lon <= 30.0
        status = "✅ VALID" if valid else "❌ invalid"
        print(f"{status} EPSG:{epsg} ({name})")
        print(f"         lon={lon:.6f}, lat={lat:.6f}\n")
    except Exception as e:
        print(f"❌ EPSG:{epsg} - Eroare: {e}\n")
