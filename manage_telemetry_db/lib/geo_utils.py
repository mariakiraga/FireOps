import math

# Stała: przybliżona długość 1 stopnia szerokości geograficznej w metrach
METERS_PER_DEGREE_LAT = 111132.92

def meters_to_latlon(origin_lat, origin_lng, x_meters, y_meters):
    """
    Zamienia przesunięcie w metrach (x, y) na nowe współrzędne Lat/Lon.
    
    :param origin_lat: Szerokość geograficzna punktu startowego (0,0)
    :param origin_lng: Długość geograficzna punktu startowego (0,0)
    :param x_meters: Przesunięcie na Wschód (East) w metrach
    :param y_meters: Przesunięcie na Północ (North) w metrach
    :return: (new_lat, new_lng)
    """
    
    # Obliczamy zmianę szerokości (Latitude) - to proste, bo 1 stopień to stała liczba metrów
    delta_lat = y_meters / METERS_PER_DEGREE_LAT
    
    # Obliczamy zmianę długości (Longitude) - to zależy od szerokości (im bliżej bieguna, tym ciaśniej)
    # Cosinus bierzemy z radianów
    meters_per_degree_lng = METERS_PER_DEGREE_LAT * math.cos(math.radians(origin_lat))
    delta_lng = x_meters / meters_per_degree_lng
    
    new_lat = origin_lat + delta_lat
    new_lng = origin_lng + delta_lng
    
    return new_lat, new_lng

def pressure_to_altitude(current_pressure_pa, base_pressure_pa):
    """
    Oblicza różnicę wysokości względem ciśnienia bazowego (poziom 0).
    Zwraca: (wysokość_w_metrach, numer_piętra)
    """
    if current_pressure_pa is None or base_pressure_pa is None:
        return 0.0, 0

    # Wzór barometryczny (uproszczony dla małych różnic wysokości)
    # Dla dokładności inżynierskiej: h = 44330 * (1 - (P/P0)^(1/5.255))
    
    altitude_m = 44330 * (1 - (current_pressure_pa / base_pressure_pa) ** (1 / 5.255))
    
    # Przyjmujemy 3.5m na kondygnację
    floor = round(altitude_m / 3.5)
    
    return altitude_m, floor