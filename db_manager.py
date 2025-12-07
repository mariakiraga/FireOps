import psycopg2
from datetime import datetime
import configparser
import os

def load_db_config(filename="config.ini"):
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

class PSPDatabase:
    def __init__(self, config):
        self.db_params = config["DATABASE"]
        self.conn = None
        self.cur = None

    def connect(self):
        """Nawiązuje połączenie i ustawia schemat."""
        try:
            params = dict(self.db_params)
            self.conn = psycopg2.connect(**params)
            self.cur = self.conn.cursor()
            # Ustawienie schematu na psp_telemetry
            self.cur.execute("SET search_path TO psp_telemetry, public;")
            print("[DB] Połączono z bazą danych.")
        except psycopg2.Error as e:
            print(f"[DB] Błąd połączenia: {e}")
            raise

    def close(self):
        """Zamyka połączenie."""
        if self.conn:
            self.conn.commit()
            self.cur.close()
            self.conn.close()
            print("[DB] Połączenie zamknięte.")

    # =========================================================
    # 1. STRAŻACY (FIREFIGHTERS)
    # =========================================================
    def get_or_create_firefighter(self, first_name, last_name, rank, role):
        """
        Sprawdza czy strażak istnieje. 
        Jeśli TAK -> zwraca jego ID.
        Jeśli NIE -> tworzy go i zwraca nowe ID.
        """
        # 1. Sprawdź czy istnieje
        check_sql = "SELECT id FROM firefighters WHERE first_name = %s AND last_name = %s"
        self.cur.execute(check_sql, (first_name, last_name))
        result = self.cur.fetchone()

        if result:
            return result[0] # Zwracamy istniejące ID
        
        # 2. Utwórz nowego
        insert_sql = """
            INSERT INTO firefighters (first_name, last_name, rank, role, global_status)
            VALUES (%s, %s, %s, %s, 'ACTIVE')
            RETURNING id;
        """
        self.cur.execute(insert_sql, (first_name, last_name, rank, role))
        new_id = self.cur.fetchone()[0]
        self.conn.commit()
        print(f"[DB] Dodano nowego strażaka: {first_name} {last_name} (ID: {new_id})")
        return new_id

    # =========================================================
    # 2. ZESPOŁY (TEAMS)
    # =========================================================
    def get_or_create_team(self, name, number):
        """Dodaje zespół, jeśli nie istnieje (na podstawie numeru)."""
        check_sql = "SELECT id FROM teams WHERE number = %s"
        self.cur.execute(check_sql, (number,))
        result = self.cur.fetchone()

        if result:
            return result[0]

        insert_sql = """
            INSERT INTO teams (name, number)
            VALUES (%s, %s)
            RETURNING id;
        """
        self.cur.execute(insert_sql, (name, number))
        new_id = self.cur.fetchone()[0]
        self.conn.commit()
        print(f"[DB] Dodano nowy zespół: {name} (ID: {new_id})")
        return new_id

    # =========================================================
    # 3. AKCJE (ACTIONS)
    # =========================================================
    def create_action(self, action_type, location_lat=None, location_lng=None):
        """Tworzy nową akcję ratunkową."""
        # W prawdziwym systemie pewnie sprawdzalibyśmy czy akcja już trwa.
        # Tu dla uproszczenia tworzymy nową.
        insert_sql = """
            INSERT INTO actions (type, start_time, location_lat, location_lng)
            VALUES (%s, NOW(), %s, %s)
            RETURNING id;
        """
        self.cur.execute(insert_sql, (action_type, location_lat, location_lng))
        new_id = self.cur.fetchone()[0]
        self.conn.commit()
        print(f"[DB] Rozpoczęto nową akcję (ID: {new_id})")
        return new_id

    def assign_team_to_action(self, action_id, team_id, role="PRIMARY"):
        """Przypisuje zespół do akcji (tabela action_teams)."""
        # Insert with ON CONFLICT DO NOTHING (ignore if already assigned)
        insert_sql = """
            INSERT INTO action_teams (action_id, team_id, role, created_at)
            VALUES (%s, %s, %s, NOW())
            ON CONFLICT (action_id, team_id) DO NOTHING
            RETURNING id;
        """
        self.cur.execute(insert_sql, (action_id, team_id, role))
        # Nie musimy pobierać ID, wystarczy że się wykonało
        self.conn.commit()

    # =========================================================
    # 4. URZĄDZENIA (DEVICES)
    # =========================================================
    def get_or_create_device(self, dev_type, firmware, firefighter_id, is_online=True):
        """
        Znajduje urządzenie przypisane do strażaka.
        Jeśli brak - tworzy nowe. Aktualizuje status online.
        """
        # Szukamy urządzenia przypisanego do tego strażaka
        check_sql = "SELECT id FROM devices WHERE firefighter_id = %s"
        self.cur.execute(check_sql, (firefighter_id,))
        result = self.cur.fetchone()

        if result:
            device_id = result[0]
            # Aktualizacja statusu
            update_sql = "UPDATE devices SET is_online = %s, firmware_version = %s WHERE id = %s"
            self.cur.execute(update_sql, (is_online, firmware, device_id))
            return device_id
        
        # Tworzenie nowego
        insert_sql = """
            INSERT INTO devices (type, firmware_version, firefighter_id, is_online)
            VALUES (%s, %s, %s, %s)
            RETURNING id;
        """
        self.cur.execute(insert_sql, (dev_type, firmware, firefighter_id, is_online))
        new_id = self.cur.fetchone()[0]
        self.conn.commit()
        return new_id

    # =========================================================
    # 5. TELEMETRIA (TELEMETRY_SAMPLES)
    # =========================================================
    def insert_telemetry(self, data):
        """
        Wstawia próbkę telemetryczną.
        Wymaga słownika 'data' z kluczami odpowiadającymi kolumnom.
        """
        insert_sql = """
            INSERT INTO telemetry_samples (
                device_id, firefighter_id, action_id, ts,
                lat, lng, 
                heart_rate, body_temperature, ambient_temperature,
                air_left, battery_level,
                steps_total, seconds_still, is_moving,
                connectivity_type, status
            )
            VALUES (
                %(device_id)s, %(firefighter_id)s, %(action_id)s, %(ts)s,
                %(lat)s, %(lng)s,
                %(heart_rate)s, %(body_temperature)s, %(ambient_temperature)s,
                %(air_left)s, %(battery_level)s,
                %(steps_total)s, %(seconds_still)s, %(is_moving)s,
                %(connectivity_type)s, %(status)s
            )
            RETURNING id;
        """
        self.cur.execute(insert_sql, data)
        # return self.cur.fetchone()[0] # Opcjonalnie, dla wydajności przy masowym imporcie można pominąć

    # =========================================================
    # 6. ALERTY (ALERTS)
    # =========================================================
    def insert_alert(self, data):
        """Wstawia nowy alert."""
        insert_sql = """
            INSERT INTO alerts (
                code, name, severity, 
                firefighter_id, device_id, action_id, telemetry_sample_id,
                ts, details, created_at
            )
            VALUES (
                %(code)s, %(name)s, %(severity)s,
                %(firefighter_id)s, %(device_id)s, %(action_id)s, %(telemetry_sample_id)s,
                %(ts)s, %(details)s, NOW()
            )
        """
        self.cur.execute(insert_sql, data)
        self.conn.commit()
        print(f"[DB] Zapisano ALERT: {data['code']} dla strażaka ID {data['firefighter_id']}")
     
    