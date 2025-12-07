import socket
import ssl
import time
import requests
import os
from dotenv import load_dotenv

# Import zaktualizowanej funkcji (upewnij się, że plik nazywa się json2cot.py)
from json2cot import create_cot_event

load_dotenv()

# ====== KONFIGURACJA ======
SERVER_IP = os.getenv("TAK_SERVER_IP")
SERVER_PORT = int(os.getenv("TAK_SERVER_PORT"))
# Certyfikaty (jeśli wymagane przez serwer)
CLIENT_CERT = os.getenv("TAK_CLIENT_CERT")
CLIENT_KEY = os.getenv("TAK_CLIENT_KEY")
CA_CERT = os.getenv("TAK_CA_CERT")

JSON_URL = "https://19a4c82bfcd8.ngrok-free.app/firefighters/"
POLL_INTERVAL = 1  # Sekundy między zapytaniami

headers = {
    "ngrok-skip-browser-warning": "true"
}

# ====== KONTEKST SSL ======
# Jeśli Twój serwer TAK nie wymaga SSL, możesz pominąć wrapowanie socketa
context = ssl.create_default_context(ssl.Purpose.SERVER_AUTH, cafile=CA_CERT)
context.load_cert_chain(certfile=CLIENT_CERT, keyfile=CLIENT_KEY)
# Wyłączenie weryfikacji hostname (częste przy testach na IP)
context.check_hostname = False 
context.verify_mode = ssl.CERT_NONE 

# ====== PAMIĘĆ POZYCJI ======
# Słownik do przechowywania ostatniej znanej pozycji każdego strażaka
# Format: { "FF-001": (52.22, 21.01), "FF-002": (...) }
last_positions = {}

def fetch_json():
    try:
        r = requests.get(JSON_URL, headers=headers, timeout=5)
        r.raise_for_status()
        return r.json()
    except Exception as e:
        print(f"[ERROR] Błąd pobierania JSON: {e}")
        return None

# ====== GŁÓWNA PĘTLA ======
print(f"[INFO] Łączenie z TAK Server {SERVER_IP}:{SERVER_PORT}...")

# Używamy pętli retry dla połączenia TCP
while True:
    try:
        # Tworzenie połączenia
        raw_sock = socket.create_connection((SERVER_IP, SERVER_PORT), timeout=10)
        with context.wrap_socket(raw_sock, server_hostname=SERVER_IP) as ssock:
            print(f"[INFO] Połączono z serwerem TAK!")

            while True:
                data = fetch_json()
                
                if data and "firefighters" in data:
                    for ff in data["firefighters"]:
                        try:
                            # 1. Pobierz dane identyfikacyjne
                            # Używamy ID z bazy jako UID w TAK (gwarantuje unikalność i ciągłość śledzenia)
                            uid = ff["firefighter"].get("id") 
                            name = ff["firefighter"].get("name", "Nieznany")
                            role = ff["firefighter"].get("role", "")
                            
                            # Budujemy callsign np. "Jan Kowalski (Dowódca)"
                            callsign = f"{name} ({role})" if role else name

                            # 2. Pobierz współrzędne GPS
                            gps = ff.get("position", {}).get("gps", {})
                            lat = gps.get("lat")
                            lon = gps.get("lon")
                            fix = gps.get("fix", False)

                            # Jeśli brak fixa GPS lub danych, pomijamy
                            if not fix or lat is None or lon is None:
                                continue

                            current_pos = (lat, lon)

                            # 3. Logika wysyłania (tylko jeśli pozycja się zmieniła)
                            # Jeśli nie mamy tego strażaka w pamięci LUB pozycja jest inna
                            if uid not in last_positions or last_positions[uid] != current_pos:
                                
                                # Generujemy XML dla konkretnego strażaka
                                cot_xml = create_cot_event(lat, lon, uid, callsign)
                                
                                # Wysyłamy
                                ssock.sendall((cot_xml + "\n").encode("utf-8"))
                                
                                # Aktualizujemy pamięć
                                last_positions[uid] = current_pos
                                print(f"[SENT] {uid} -> {lat}, {lon}")

                        except Exception as e:
                            print(f"[WARN] Błąd przetwarzania strażaka: {e}")

                time.sleep(POLL_INTERVAL)

    except (socket.error, ssl.SSLError, requests.RequestException) as e:
        print(f"[ERROR] Połączenie zerwane: {e}. Ponawianie za 5s...")
        time.sleep(5)
    except KeyboardInterrupt:
        print("\n[INFO] Zamykanie klienta.")
        break