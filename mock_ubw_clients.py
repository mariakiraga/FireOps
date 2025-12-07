import requests
import time
import json
from concurrent.futures import ThreadPoolExecutor, as_completed

SIMULATION_API_URL = "https://niesmiertelnik.replit.app/api/v1/firefighters/"
FLASK_APP_URL = "https://93df9d0e6824.ngrok-free.app/calculate_position_uwb/"
HEADERS = {"ngrok-skip-browser-warning": "true"}

def fetch_firefighters():
    try:
        response = requests.get(SIMULATION_API_URL, timeout=5)
        response.raise_for_status()
        return response.json()
    except Exception as e:
        print(f"[ERROR] Failed to fetch data: {e}")
        return None

def calculate_position(ff, full_data):
    firefighter_id = ff.get("firefighter", {}).get("id")
    name = ff.get("firefighter", {}).get("name")

    try:
        resp = requests.post(
            f"{FLASK_APP_URL}{firefighter_id}",
            json=full_data,
            headers=HEADERS,
            timeout=5
        )
        if resp.ok:
            resp_data = resp.json()
            pos = resp_data.get("position", {})
            if len(pos) == 3:
                return f"{firefighter_id} | {name} | x={pos[0]} y={pos[1]} z={pos[2]}"
            else:
                return f"{firefighter_id} | {name} | [ERROR] Invalid position data"
        else:
            return f"{firefighter_id} | {name} | [ERROR] Flask app response: {resp.status_code} {resp.text}"
    except Exception as e:
        return f"{firefighter_id} | {name} | [ERROR] Exception: {e}"

def main():
    print("Firefighter Telemetry Simulator Started")

    while True:
        data = fetch_firefighters()
        if data and "firefighters" in data:
            firefighters = data["firefighters"]
            print(f"Received {len(firefighters)} telemetry packets")

            # Use ThreadPoolExecutor for parallel processing
            with ThreadPoolExecutor(max_workers=5) as executor:
                futures = [executor.submit(calculate_position, ff, data) for ff in firefighters]
                for future in as_completed(futures):
                    print(" -", future.result())

        print("-" * 60)
        time.sleep(1)  # Polling interval

if __name__ == "__main__":
    main()
