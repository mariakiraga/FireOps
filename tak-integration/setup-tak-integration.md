## Setup Instructions

### 1. Prerequisites: TAK Server

You need a running TAK server before using this project. It can be run via Docker following the instructions here: [Cloud-RF TAK Server](https://github.com/Cloud-RF/tak-server?tab=readme-ov-file)

Make sure your TAK server is accessible at the IP/port you plan to use and that you have client certificates (.pem files) ready.

### 2. Create .env file

Create a file named `.env` in the root of the project and add the following contents with placeholders:

```
TAK_SERVER_IP=<TAK_SERVER_IP>
TAK_SERVER_PORT=<TAK_SERVER_PORT>
TAK_CLIENT_CERT=<PATH_TO_CLIENT_CERT>
TAK_CLIENT_KEY=<PATH_TO_CLIENT_KEY>
TAK_CA_CERT=<PATH_TO_CA_CERT>
JSON_URL=<EXTERNAL_JSON_URL>
POLL_INTERVAL=<SECONDS_BETWEEN_POLLS>
CONTACT_CALLSIGN=<CALLSIGN>
COT_POINT_TYPE=<COT_POINT_TYPE>
ORIGIN_LAT=<REFERENCE_LATITUDE>
ORIGIN_LON=<REFERENCE_LONGITUDE>
```

Example placeholders:

* `<TAK_SERVER_IP>` -> `127.0.0.1`
* `<PATH_TO_CLIENT_CERT>` -> `cert/user2.pem`
* `<EXTERNAL_JSON_URL>` -> `https://example.com/position`

**Important:** Add `.env` and any certificate files to `.gitignore` to avoid committing sensitive data.

### 3. Set up Python virtual environment and install dependencies

1. Create a virtual environment:

```bash
python3 -m venv venv
```

2. Activate the virtual environment:

* On Linux/macOS:

```bash
source venv/bin/activate
```

* On Windows:

```bash
venv\Scripts\activate
```

3. Install dependencies:
   Make sure you have a `requirements.txt` file in your repo with the following contents:

```
requests
pyproj
python-dotenv
```

Then run:

```bash
pip install -r requirements.txt
```

4. Verify installation:

```bash
python -c "import requests, pyproj, dotenv; print('Dependencies installed successfully!')"
```

### 4. Run the project

Once setup is complete, run your main script (e.g., `main-tak.py`):

```bash
python main-tak.py
```

The script will fetch JSON data from the configured endpoint, convert it to CoT messages, and send them to your TAK server.

Following that you can connect WinTAK, ATAK, or other TAK UI as a client to TAK server and the CoTs will be displayed there.
