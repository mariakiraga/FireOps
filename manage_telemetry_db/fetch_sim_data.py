import requests
from lib.utils import load_config

# Konfiguracja ładowana raz przy starcie modułu
try:
    config = load_config()
    BASE_URL = config["SIM_API"]["base_url"]
    ENDPOINT = config["SIM_API"]["endpoint"]
    FULL_URL = f"{BASE_URL}{ENDPOINT}"
    TIMEOUT = int(config["SIM_API"]["timeout"])
except Exception as e:
    print(f"[API ERROR] Błąd konfiguracji API: {e}")
    FULL_URL = None

def fetch_sim_data():
    """
    Pobiera dane z API symulatora i zwraca je jako słownik (dict).
    Zwraca None w przypadku błędu.
    """
    if not FULL_URL:
        return None

    try:
        # Wysyłamy zapytanie GET
        response = requests.get(FULL_URL, timeout=TIMEOUT)

        if response.status_code == 200:
            try:
                return response.json()
            except ValueError:
                print(f"[API ERROR] Odpowiedź nie jest poprawnym JSON-em.")
                return None
        elif response.status_code == 404:
            print(f"[API ERROR] 404 - Nie znaleziono endpointu: {ENDPOINT}")
        else:
            print(f"[API ERROR] Status: {response.status_code}")

    except requests.exceptions.ConnectionError:
        print("[API ERROR] Nie można połączyć się z serwerem (ConnectionError).")
    except Exception as e:
        print(f"[API ERROR] Nieoczekiwany błąd: {e}")
    
    return None
