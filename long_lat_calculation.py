# Warsaw/Poland GPS origin and scaling factors
GPS_ORIGIN = { "lat": 52.2297, "lon": 21.0122 }
SCALE_LAT = 111320  # meters per degree latitude
SCALE_LON = 71695   # meters per degree longitude (at 52°N)

# Conversion from local (x, y) to GPS
def local_to_gps(x, y):
    return {
        "lat": GPS_ORIGIN["lat"] + (y / SCALE_LAT),
        "lon": GPS_ORIGIN["lon"] + (x / SCALE_LON)
    }

