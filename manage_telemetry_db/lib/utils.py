# utils.py
import configparser
import os

def load_config(filename="config.ini"):
    """Wczytuje konfigurację z pliku INI."""
    utils_path = os.path.abspath(__file__)
    lib_dir = os.path.dirname(utils_path)
    project_root = os.path.dirname(lib_dir)
    file_path = os.path.join(project_root, filename)

    if not os.path.exists(file_path):
        raise FileNotFoundError(f"Nie znaleziono pliku konfiguracyjnego: {file_path}")
    
    config = configparser.ConfigParser()
    config.read(file_path)
    return config

def split_name(full_name):
    """Rozdziela 'Jan Kowalski' na ('Jan', 'Kowalski')."""
    if not full_name:
        return "N/N", "N/N"
    parts = full_name.strip().split(' ', 1)
    if len(parts) == 2:
        return parts[0], parts[1]
    return parts[0], ""

def calculate_air_percentage(scba_data):
    """
    Oblicza procent powietrza w butli.
    Zwraca liczbę całkowitą 0-100.
    """
    if not scba_data:
        return 0
        
    current = scba_data.get('cylinder_pressure_bar', 0)
    maximum = scba_data.get('max_pressure_bar', 300) # Domyślnie 300 bar
    
    if maximum <= 0:
        return 0
        
    percent = (current / maximum) * 100
    
    # Zwracamy jako int, ograniczony do zakresu 0-100
    return max(0, min(100, int(percent)))