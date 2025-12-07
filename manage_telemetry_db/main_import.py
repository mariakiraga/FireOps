import time
from datetime import datetime
from fetch_sim_data import fetch_sim_data
from lib.utils import split_name, calculate_air_percentage, load_config
from db_manager import PSPDatabase

# Konfiguracja
POLL_INTERVAL = 2  # Co ile sekund pobierać dane

def main():
    # 1. Ładowanie konfiguracji
    try:
        config = load_config()
    except Exception as e:
        print(f"Startup Error: {e}")
        return

    # 2. Inicjalizacja bazy danych
    db = PSPDatabase(config)
    try:
        db.connect()
        
        # 3. Tworzenie akcji (Raz na uruchomienie skryptu)
        current_action_id = db.create_action("Pożar Hali - Live", 52.2297, 21.0122)
        
        print("\n" + "="*50)
        print("🚀 ROZPOCZĘTO PROCES IMPORTU DANYCH")
        print(f"ID Akcji: {current_action_id}")
        print("Naciśnij Ctrl+C aby zatrzymać.")
        print("="*50 + "\n")
        
        # 4. Pętla główna (Continuous Loop)
        while True:
            loop_start = time.time()
            
            try:
                # --- A. Pobranie danych ---
                raw_data = fetch_sim_data()
                
                if not raw_data:
                    # Jeśli błąd pobierania, czekamy i próbujemy ponownie
                    time.sleep(POLL_INTERVAL)
                    continue

                firefighters_list = raw_data.get("firefighters", [])
                
                # --- B. Przetwarzanie danych ---
                if firefighters_list:
                    for item in firefighters_list:
                        # Rozpakowanie danych z JSON
                        ff_data = item.get("firefighter", {})
                        device_data = item.get("device", {})
                        pos_data = item.get("position", {}).get("gps", {})
                        vitals = item.get("vitals", {})
                        env = item.get("environment", {})
                        scba_data = item.get("scba", {})
                        
                        # 1. Strażak (Pobierz lub stwórz)
                        f_name, l_name = split_name(ff_data.get("name"))
                        ff_db_id = db.get_or_create_firefighter(
                            f_name, l_name, ff_data.get("rank"), ff_data.get("role")
                        )

                        # 2. Zespół (Opcjonalne)
                        team_name = ff_data.get("team")
                        if team_name:
                            team_db_id = db.get_or_create_team(team_name, f"TM-{team_name[:3].upper()}")
                            db.assign_team_to_action(current_action_id, team_db_id)

                        # 3. Urządzenie (Tag/Sensor)
                        dev_db_id = db.get_or_create_device(
                            "tag_module", 
                            device_data.get("firmware_version", "1.0"), 
                            ff_db_id,
                            is_online=True
                        )

                        # 4. Przygotowanie danych telemetrycznych
                        ts_str = item.get("timestamp")
                        # Fix dla formatu czasu z 'Z' na końcu (jeśli występuje)
                        if ts_str and ts_str.endswith('Z'):
                            ts_str = ts_str[:-1] + '+00:00'
                        
                        telemetry_dict = {
                            "device_id": dev_db_id,
                            "firefighter_id": ff_db_id,
                            "action_id": current_action_id,
                            "ts": ts_str or datetime.now(),
                            "lat": pos_data.get("lat"),
                            "lng": pos_data.get("lon"),
                            "heart_rate": vitals.get("heart_rate_bpm"),
                            "body_temperature": vitals.get("skin_temperature_c"),
                            "ambient_temperature": env.get("temperature_c"),
                            "air_left": calculate_air_percentage(scba_data),
                            "battery_level": device_data.get("battery_percent"),
                            "steps_total": vitals.get("step_count"),
                            "seconds_still": vitals.get("stationary_duration_s"),
                            "is_moving": (vitals.get("motion_state") != "stationary"),
                            "connectivity_type": device_data.get("connection_primary", "unknown"),
                            "status": item.get("pass_status", {}).get("status", "unknown")
                        }

                        # 5. Insert do bazy (jeszcze nie commitujemy)
                        db.insert_telemetry(telemetry_dict)

                    # --- C. Commit Transaction (Raz na cykl pętli) ---
                    db.conn.commit()
                    
                    # Statystyki wydajności
                    process_time = time.time() - loop_start
                    print(f"✅ [{datetime.now().strftime('%H:%M:%S')}] Zapisano {len(firefighters_list)} rekordów (czas: {process_time:.2f}s)")
                
                else:
                    print("⚠️ Pusty JSON (brak strażaków).")

            except Exception as e:
                print(f"❌ Błąd w pętli głównej: {e}")
                if db.conn:
                    db.conn.rollback() # Cofnij zmiany jeśli wystąpił błąd w trakcie zapisu

            # --- D. Czekanie ---
            time.sleep(POLL_INTERVAL)

    except KeyboardInterrupt:
        print("\n🛑 Zatrzymano przez użytkownika.")
    except Exception as e:
        print(f"🔥 Błąd krytyczny aplikacji: {e}")
    finally:
        if db:
            db.close()

if __name__ == "__main__":
    main()
