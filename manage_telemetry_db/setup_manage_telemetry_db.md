# manage_telemetry_db
Import data fetched from an endpoint to a PostrgreSQL database.
This system ingests telemetry data from sensors, processes signals for indoor localization (Dead Reckoning + Barometry), and stores critical data in a PostgreSQL database for command center visualization.

# Key-fetures
- **Real-time Data Ingestion**: Fetches data streams from the simulation API or real time data from IMU sensors API.
- **Conversion, calculation Engine**:
  - Hypsometry: Calculates relative altitude and floor number based on barometric pressure.
  - Converts local metric offsets into Global Coordinates (Lat/Lon) for mapping.
- **Robust Database Storage**: Uses PostgreSQL with a normalized schema to store historical telemetry, alerts, and firefighter statuses.

# Project Structure
```
manage_telemetry_db/
├── main_import.py       # ENTRY POINT: Main execution loop
├── db_manager.py        # Database interactions (CRUD operations)
├── fetch_sim_data.py    # API communication module
├── config.ini           # Configuration file -- add your own
├── requirements.txt     # Python dependencies
└── lib/                 # Helper Library
    ├── __init__.py      # Makes this folder a package
    ├── utils.py         # Utility functions (config loader, string parsing)
    ├── geo_utils.py     # Math for GPS & Altitude conversions
```

# Prerequisites
- Python 3.8+
- PostgreSQL (running locally or remotely)
  
# Installation & Setup
1. Clone the Repository
``` {bash}
git clone https://github.com/mariakiraga/manage_telemetry_db.git
cd manage_telemetry_db
```
3. Install dependencies
``` {bash}
# Create virtual environment
python -m venv venv

# Activate it (Windows)
venv\Scripts\activate
# Activate it (Mac/Linux)
source venv/bin/activate

# Install libraries
pip install -r requirements.txt
```
5. Database Setup
- Open PgAdmin/other database management tool or your terminal.
- Create a new database named psp_tag_db.
- Open the Query Tool and run the SQL script found in schema.sql (or the DDL provided during development) to create the tables and the psp_telemetry schema.

7. Configuration
Create a file named config.ini in the root directory. Paste the following content and update with your credentials:
``` {Ini}
[DATABASE]
dbname = YOUR_DB_NAME_HERE
user = YOUR_USER_HERE
password = YOUR_DB_PASSWORD_HERE
host = YOUR_HOST_HERE
port = YOUR_PORT_HERE

[SIM_API]
base_url = https://niesmiertelnik.replit.app/api/v1
endpoint = /firefighters
timeout = 5
```

# Usage
To start the telemetry processing pipeline, run the main script:
``` {bash}
python main_import.py
```
