from datetime import datetime
import sys
import os
import time
import torch
import numpy as np
from flask import Flask, request, jsonify
import json
import quaternion

from resnet_inference import get_new_position
from uwb_position_estimation import estimate_position, get_beacon_data
from long_lat_calculation import local_to_gps

# Add ronin source to path
sys.path.append(os.path.join("source"))
from ronin_resnet import get_model  
from preprocessing.gen_dataset_v2 import interpolate_vector_linear

from db_manager import PSPDatabase, load_db_config

# ---- Load model globally ----
device = torch.device('cuda:0' if torch.cuda.is_available() else 'cpu')
model_path = "ronin_resnet/checkpoint_gsn_latest.pt"

checkpoint = torch.load(model_path, map_location=device)
network = get_model('resnet18')
network.load_state_dict(checkpoint['model_state_dict'])
network.eval().to(device)
print(f'Model {model_path} loaded to device {device}.')

EXPECTED_SAMPLES = 200
DT_NS = 5_000_000  # 5 ms in nanoseconds

FIREFIGHTERS_JSON = "firefighters_info.json"

"""
contains all data that can be found at api/v1/firefighters/
Positions as given timestamps are replaced with real positions estimated by the UWB or the model


"""
## Init database connection
try:
    config = load_db_config("FireOps/config.ini")
    db = PSPDatabase(config)
    db.connect()
except Exception as e:
    print(f"DB init Error: {e}")




# ---- Flask app ----
app = Flask(__name__)


def insert_full_figherfighter_data(self, ff_full_data):

    def split_name(full_name):
        """Rozdziela 'Jan Kowalski' na ('Jan', 'Kowalski')."""
        if not full_name:
            return "N/N", "N/N"
        parts = full_name.strip().split(' ', 1)
        if len(parts) == 2:
            return parts[0], parts[1]
        return parts[0], ""

    ff_data = ff_full_data.get("firefighter", {})
    device_data = ff_full_data.get("device", {})
    pos_data = ff_full_data.get("position", {}).get("gps", {})
    vitals = ff_full_data.get("vitals", {})
    env = ff_full_data.get("environment", {})

    scba_data = ff_full_data.get("scba", {})
    
    # --- 1. Strażak ---
    f_name, l_name = split_name(ff_data.get("name"))
    ff_db_id = db.get_or_create_firefighter(
        f_name, l_name, ff_data.get("rank"), ff_data.get("role")
    )

    # --- 2. Zespół (jeśli jest w JSON) ---
    team_name = ff_data.get("team")
    if team_name:
        # Generujemy numer np. na podstawie nazwy lub bierzemy z JSON jeśli jest
        team_db_id = db.get_or_create_team(team_name, f"TM-{team_name[:3].upper()}")


    # --- 3. Urządzenie ---
    dev_db_id = db.get_or_create_device(
        "tag_module", 
        device_data.get("firmware_version", "1.0"), 
        ff_db_id,
        is_online=True
    )

    # --- 4. Telemetria ---
    # Parsowanie czasu (API zwraca np. '2025-12-06T18:24:31.450Z')
    ts_str = ff_full_data.get("timestamp")
    # Python < 3.11 słabo radzi sobie z 'Z' na końcu, zamieniamy na '+00:00'
    if ts_str and ts_str.endswith('Z'):
        ts_str = ts_str[:-1] + '+00:00'
    
    telemetry_dict = {
        "device_id": dev_db_id,
        "firefighter_id": ff_db_id,
        "ts": ts_str or datetime.now(),
        "lat": pos_data.get("lat"),
        "lng": pos_data.get("lon"),
        "heart_rate": vitals.get("heart_rate_bpm"),
        "body_temperature": vitals.get("skin_temperature_c"), # Używamy skin temp jako body temp
        "ambient_temperature": env.get("temperature_c"),
        "battery_level": device_data.get("battery_percent"),
        "steps_total": vitals.get("step_count"),
        "seconds_still": vitals.get("stationary_duration_s"),
        "is_moving": (vitals.get("motion_state") != "stationary"),
        "connectivity_type": device_data.get("connection_primary", "unknown"),
        "status": ff_full_data.get("pass_status", {}).get("status", "unknown")
    }

    db.insert_telemetry(telemetry_dict)

    # Zatwierdzamy wszystkie inserty telemetrii
    db.conn.commit()
    print("Pomyślnie zaimportowano dane bieżącego cyklu.")

def dump_firefighter_data(data):
    """
    Dummy function to dump position to a JSON file.
    In real application, replace with database save
    """
    with open(FIREFIGHTERS_JSON, "w") as f:    
        json.dump(data, f, indent=2)


# --- New GET endpoint to serve the JSON file with firefighters info---
@app.route('/firefighters/', methods=['GET'])
def firefighters():
    
    ## TODO: Replace with loading latest data from db
    if not os.path.exists(FIREFIGHTERS_JSON):
        return jsonify({"error": "Position file not found"}), 404

    with open(FIREFIGHTERS_JSON, "r") as f:
        data = json.load(f)
    return jsonify(data)


def make_target_ts(timestamps, desired_count):
    t = np.array(timestamps)
    return np.linspace(t[0], t[-1], desired_count)

@app.route('/predict_position/<firefighter_id>', methods=['POST'])
def predict_position(firefighter_id):
    """
    Data format:
    {
        "start": [x0, y0],
        "imu_data": [
            {
                "t_ns": timestamp in nanoseconds,
                "acc": [ax, ay, az],
                "gyro": [gx, gy, gz],
                "rv": [rx, ry, rz, rw]  # game rotation vector
            }
            ... (total 200 records) ...
        ]
    }
                
    """
    data = request.json
    if 'start' not in data or 'imu_data' not in data:
        return jsonify({"error": "Request must contain 'start' and 'imu_data'"}), 400

    
    print(f"Received request  with {len(data['imu_data'])} IMU samples.")
    start_pos = np.array(data['start'], dtype=np.float32)
    print(f"Start position: {start_pos}")
    imu_records = data['imu_data']

    valid_records = []
    for rec in imu_records:
        if 't_ns' in rec and 'acc' in rec and 'gyro' in rec and 'rv' in rec:
            # ensure they have the correct length too
            if len(rec['acc']) == 3 and len(rec['gyro']) == 3 and len(rec['rv']) == 4:
                valid_records.append(rec)
                
    

    print(f"Using {len(valid_records)} valid IMU records after filtering.")
    if len(valid_records) < 2:
        return jsonify({"error": "Not enough valid IMU records"}), 400


    
    # Now convert to numpy arrays
    t_ns = np.array([rec['t_ns'] for rec in valid_records], dtype=np.int64)
    rv = np.array([rec['rv'] for rec in valid_records], dtype=np.float32)   # (N, 3) or (N, 4)
    
    acc = np.array([rec['acc'] for rec in valid_records], dtype=np.float32)   # (N, 3)
    gyro = np.array([rec['gyro'] for rec in valid_records], dtype=np.float32) # (N, 3)
    
    # Resample to exactly EXPECTED_SAMPLES
    t_ns_interp = make_target_ts(t_ns, EXPECTED_SAMPLES)
    acc_interp = interpolate_vector_linear(acc, t_ns, t_ns_interp) 
    gyro_interp = interpolate_vector_linear(gyro, t_ns, t_ns_interp)
    rv_interp = interpolate_vector_linear(rv, t_ns, t_ns_interp)
    
    

    # 1) Convert orientation (game_rv) to quaternion array
    ori_q = quaternion.from_float_array(rv_interp)     # (W,)
    # 2) Build gyro + acc as quaternions with w=0
    gyro_q = quaternion.from_float_array(
        np.concatenate([np.zeros((gyro_interp.shape[0], 1)), gyro_interp], axis=1)
    )
    acc_q = quaternion.from_float_array(
        np.concatenate([np.zeros((acc_interp.shape[0], 1)), acc_interp], axis=1)
    )

    # 3) Rotate into global frame
    #   q * v * q*
    glob_gyro = quaternion.as_float_array(ori_q * gyro_q * ori_q.conj())[:, 1:]
    glob_acc  = quaternion.as_float_array(ori_q * acc_q  * ori_q.conj())[:, 1:]

    # 4) Concatenate final features
    features = np.concatenate([glob_gyro, glob_acc], axis=1)   # (W, 6)

    imu_sample = features.T[np.newaxis, :, :]  # (1, 6, W)
    print(f"Prepared IMU sample shape: {imu_sample.shape}")


    try:
        start_time = time.time()
        new_pos, _, _ = get_new_position(start_pos, imu_sample, device, network)        
        inference_time = time.time() - start_time
        
        print(f"Predicted new position: {new_pos} (inference time: {inference_time:.6f} s)")
        
        # TODO: Replace with saving to db
        firefighters_data = json.load(open(FIREFIGHTERS_JSON, "r"))
        new_data = {
            "timestamp": time.strftime("%Y-%m-%dT%H:%M:%S.%fZ", time.gmtime()),
            "firefighter": {
                "id": firefighter_id,
                "name": "Janusz Androidowski",
                "rank": "Unknown",
                "role": "Unknown",
                "team": "Unknown"
            },
            "position": {
                "x": float(new_pos[0]),
                "y": float(new_pos[1]),
                "z": 0.0,
                "floor": 0,
                "source": "imu_model"
            },
            "gps": local_to_gps(float(new_pos[0]), float(new_pos[1]))
        }
        firefighters_data["firefighters"].append(new_data)
    
        dump_firefighter_data(firefighters_data)
    
        
        return jsonify({
            "firefighter_id": firefighter_id,
            "new_position": [float(x) for x in new_pos],
            "inference_time_s": float(inference_time)
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/calculate_position_uwb/<firefighter_id>', methods=['POST'])
def calculate_position_uwb(firefighter_id):
    data = request.json
    if data is None:
        return jsonify({"error": "No JSON data provided"}), 400
    
    beacon_positions, ranges = get_beacon_data(firefighter_id)
    
    if len(beacon_positions) < 3:
        return jsonify({"error": "Not enough beacons with valid measurements"}), 400
    estimated_position = estimate_position(beacon_positions, ranges)
    
    # TODO: Replace with saving to db
    firefighters_data = data
    for ff in firefighters_data.get("firefighters", []):
        if ff.get("firefighter", {}).get("id") == firefighter_id:
            mock_pos_x = ff['position']['x']
            mock_pos_y = ff['position']['y']
            ff["position"] = {
                "x": float(estimated_position[0]),
                "y": float(estimated_position[1]),
                "z": float(estimated_position[2]),
                "floor": 0,
                "source": "uwb_fusion",
                "mock_pos_x": mock_pos_x,
                "mock_pos_y": mock_pos_y
            }
            ff["timestamp"] = time.strftime("%Y-%m-%dT%H:%M:%S.%fZ", time.gmtime())
            ff["gps"] = local_to_gps(float(estimated_position[0]), float(estimated_position[1]))
            break
    dump_firefighter_data(firefighters_data)
    
    return jsonify({
        "firefighter_id": firefighter_id,
        "position":  [float(x) for x in estimated_position] if estimated_position is not None else []
    })


@app.route('/test_inference', methods=['GET'])
def test_inference():
    # generate mock data
    imu_sample = np.random.randn(1, 6, 200).astype(np.float32) * 0.1
    start_pos = np.random.rand(2) * 10

    print("==== TEST INFERENCE ====")
    print("Start position:", start_pos)
    print("Mock IMU sample shape:", imu_sample.shape)
    print("Sample values (first timestep of first channel):", imu_sample[0, 0, 0])

    # perform prediction
    new_pos, vx,vy = get_new_position(start_pos, imu_sample, device=device, network=network)

    print("Predicted velocities: vx={:.6f}, vy={:.6f}".format(vx, vy))
    print("New position:", new_pos)

    print("========================")

    return jsonify({
        "start_position": start_pos.tolist(),       # already converted to list
        "new_position": [float(x) for x in new_pos],
        "vx": float(vx),
        "vy": float(vy),
    })


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
