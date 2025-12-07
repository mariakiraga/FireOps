import psycopg2
from datetime import datetime

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

    # --- 1. FIREFIGHTERS ---
    def get_or_create_firefighter(self, first_name, last_name, rank, role):
        check_sql = "SELECT id FROM firefighters WHERE first_name = %s AND last_name = %s"
        self.cur.execute(check_sql, (first_name, last_name))
        result = self.cur.fetchone()
        if result:
            return result[0]
        
        insert_sql = """
            INSERT INTO firefighters (first_name, last_name, rank, role, global_status)
            VALUES (%s, %s, %s, %s, 'ACTIVE')
            RETURNING id;
        """
        self.cur.execute(insert_sql, (first_name, last_name, rank, role))
        return self.cur.fetchone()[0]

    # --- 2. TEAMS ---
    def get_or_create_team(self, name, number):
        check_sql = "SELECT id FROM teams WHERE number = %s"
        self.cur.execute(check_sql, (number,))
        result = self.cur.fetchone()
        if result:
            return result[0]

        insert_sql = "INSERT INTO teams (name, number) VALUES (%s, %s) RETURNING id;"
        self.cur.execute(insert_sql, (name, number))
        return self.cur.fetchone()[0]

    # --- 3. ACTIONS ---
    def create_action(self, action_type, location_lat=None, location_lng=None):
        insert_sql = """
            INSERT INTO actions (type, start_time, location_lat, location_lng)
            VALUES (%s, NOW(), %s, %s)
            RETURNING id;
        """
        self.cur.execute(insert_sql, (action_type, location_lat, location_lng))
        new_id = self.cur.fetchone()[0]
        self.conn.commit()
        return new_id

    def assign_team_to_action(self, action_id, team_id, role="PRIMARY"):
        insert_sql = """
            INSERT INTO action_teams (action_id, team_id, role, created_at)
            VALUES (%s, %s, %s, NOW())
            ON CONFLICT (action_id, team_id) DO NOTHING;
        """
        self.cur.execute(insert_sql, (action_id, team_id, role))

    # --- 4. DEVICES ---
    def get_or_create_device(self, dev_type, firmware, firefighter_id, is_online=True):
        check_sql = "SELECT id FROM devices WHERE firefighter_id = %s"
        self.cur.execute(check_sql, (firefighter_id,))
        result = self.cur.fetchone()

        if result:
            device_id = result[0]
            # Opcjonalnie: aktualizuj status online przy każdym odczycie
            # update_sql = "UPDATE devices SET is_online = %s WHERE id = %s"
            # self.cur.execute(update_sql, (is_online, device_id))
            return device_id
        
        insert_sql = """
            INSERT INTO devices (type, firmware_version, firefighter_id, is_online)
            VALUES (%s, %s, %s, %s)
            RETURNING id;
        """
        self.cur.execute(insert_sql, (dev_type, firmware, firefighter_id, is_online))
        return self.cur.fetchone()[0]

    # --- 5. TELEMETRY ---
    def insert_telemetry(self, data):
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
        """
        self.cur.execute(insert_sql, data)

    # --- 6. ALERTS ---
    def insert_alert(self, data):
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
