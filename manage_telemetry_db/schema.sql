-- Utworzenie schematu (opcjonalne, ale zalecane dla lepszej organizacji)
CREATE SCHEMA IF NOT EXISTS psp_telemetry;
SET search_path TO psp_telemetry, public;

-- 1. Tabela: firefighters (Strażacy)
------------------------------------------------------
CREATE TABLE firefighters (
    id SERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    rank VARCHAR(100),
    global_status VARCHAR(32) DEFAULT 'INACTIVE' NOT NULL,
    role VARCHAR(50) -- np. COMMANDER, FIREFIGHTER_1
);

-- 2. Tabela: teams (Zespoły)
------------------------------------------------------
CREATE TABLE teams (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL, -- np. Zespół Alfa
    number VARCHAR(50) UNIQUE NOT NULL -- np. A-1
);

-- 3. Tabela: actions (Akcje/Zdarzenia)
------------------------------------------------------
CREATE TABLE actions (
    id SERIAL PRIMARY KEY,
    type VARCHAR(50),
    start_time TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    end_time TIMESTAMP WITH TIME ZONE,
    location_lat NUMERIC(10, 6), -- przybliżony punkt zdarzenia
    location_lng NUMERIC(10, 6)
);

-- 4. Tabela: devices (Urządzenia/Tagi)
------------------------------------------------------
CREATE TABLE devices (
    id SERIAL PRIMARY KEY,
    type VARCHAR(50) NOT NULL, -- np. vest, beacon, tag
    firmware_version VARCHAR(50),
    firefighter_id INTEGER, -- aktualnie przypisany strażak (może być NULL, jeśli urządzenie jest w magazynie)
    
    -- ULEPSZENIE: Status online/offline urządzenia
    is_online BOOLEAN DEFAULT FALSE NOT NULL,
    
    CONSTRAINT fk_firefighter
        FOREIGN KEY (firefighter_id)
        REFERENCES firefighters(id)
        ON DELETE SET NULL
);
-- Dodatkowy indeks na kluczu obcym:
CREATE INDEX idx_devices_firefighter_id ON devices (firefighter_id);

-- 5. Tabela: action_teams (Relacja Wiele-do-Wielu: Akcje - Zespoły)
------------------------------------------------------
CREATE TABLE action_teams (
    id SERIAL PRIMARY KEY,
    action_id INTEGER NOT NULL,
    team_id INTEGER NOT NULL,
    role VARCHAR(50), -- np. PRIMARY, SUPPORT, RESCUE
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    
    CONSTRAINT fk_action
        FOREIGN KEY (action_id)
        REFERENCES actions(id)
        ON DELETE CASCADE,
    
    CONSTRAINT fk_team
        FOREIGN KEY (team_id)
        REFERENCES teams(id)
        ON DELETE RESTRICT,
        
    -- Zapewnienie, że zespół jest przypisany do danej akcji tylko raz
    CONSTRAINT uq_action_team UNIQUE (action_id, team_id)
);

-- 6. Tabela: telemetry_samples (Próbki Telemetryczne)
------------------------------------------------------
CREATE TABLE telemetry_samples (
    id BIGSERIAL PRIMARY KEY, -- Używamy BIGSERIAL ze względu na dużą ilość danych
    device_id INTEGER NOT NULL,
    firefighter_id INTEGER NOT NULL,
    action_id INTEGER NOT NULL,
    ts TIMESTAMP WITH TIME ZONE NOT NULL, -- timestamp próbki (KIEDY pomiar został wykonany)
    
    -- POZYCJA GEO (outdoor)
    lat NUMERIC(10, 6),
    lng NUMERIC(10, 6),
    
    -- PARAMETRY BIOMETRYCZNE
    heart_rate INTEGER, -- bpm
    body_temperature NUMERIC(4, 1), -- °C
    ambient_temperature NUMERIC(4, 1), -- °C
    
    -- SPRZĘT
    air_left INTEGER, -- % powietrza
    battery_level INTEGER, -- % baterii
    
    -- RUCH
    steps_total INTEGER,
    seconds_still INTEGER,
    is_moving BOOLEAN,
    
    -- ULEPSZENIE: Typ używanej łączności w momencie próbki
    connectivity_type VARCHAR(10), -- np. LTE-M, LoRa
    
    -- STATUS OPERACYJNY W DANEJ PRÓBCE
    status VARCHAR(32), -- IN_ACTION, ALERT, OFFLINE
    -- created_at (usunięte, ponieważ ts jest kluczowym znacznikiem czasu dla telemetry)

    CONSTRAINT fk_telemetry_device
        FOREIGN KEY (device_id)
        REFERENCES devices(id)
        ON DELETE RESTRICT,
        
    CONSTRAINT fk_telemetry_firefighter
        FOREIGN KEY (firefighter_id)
        REFERENCES firefighters(id)
        ON DELETE RESTRICT,
        
    CONSTRAINT fk_telemetry_action
        FOREIGN KEY (action_id)
        REFERENCES actions(id)
        ON DELETE CASCADE
);

-- KLUCZOWE INDEKSY dla szybkiego wyszukiwania telemetrycznego
CREATE INDEX idx_telemetry_device_ts ON telemetry_samples (device_id, ts);
CREATE INDEX idx_telemetry_action_ts ON telemetry_samples (action_id, ts);


-- 7. Tabela: alerts (Logi Zdarzeń/Alertów)
------------------------------------------------------
CREATE TABLE alerts (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL, -- MAN-DOWN, SOS, LOW_AIR
    name VARCHAR(150),
    severity VARCHAR(20) NOT NULL, -- INFO, WARNING, CRITICAL
    firefighter_id INTEGER NOT NULL,
    device_id INTEGER,
    action_id INTEGER,
    telemetry_sample_id BIGINT, -- próbka, z której wynikł alert (opcjonalnie)
    ts TIMESTAMP WITH TIME ZONE NOT NULL, -- kiedy wykryto alert
    resolved_at TIMESTAMP WITH TIME ZONE,
    details TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, -- Czas utworzenia rekordu w bazie

    CONSTRAINT fk_alert_firefighter
        FOREIGN KEY (firefighter_id)
        REFERENCES firefighters(id)
        ON DELETE RESTRICT,
        
    CONSTRAINT fk_alert_device
        FOREIGN KEY (device_id)
        REFERENCES devices(id)
        ON DELETE SET NULL,
        
    CONSTRAINT fk_alert_action
        FOREIGN KEY (action_id)
        REFERENCES actions(id)
        ON DELETE CASCADE,
        
    CONSTRAINT fk_alert_sample
        FOREIGN KEY (telemetry_sample_id)
        REFERENCES telemetry_samples(id)
        ON DELETE SET NULL
);
-- Dodatkowy indeks dla wyszukiwania alertów
CREATE INDEX idx_alerts_action_ts ON alerts (action_id, ts);