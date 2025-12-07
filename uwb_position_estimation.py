import numpy as np
import requests
from scipy.optimize import least_squares

def trilateration(x, beacon_positions, ranges):
    """
    Residuals function for trilateration. 
    Estimates position based on distances to known beacons.
    
    :param x: Current estimate of the position (numpy array)
    :param beacon_positions: Dictionary of beacon positions keyed by beacon ID
    :param ranges: Dictionary of measured ranges to each beacon keyed by beacon ID
    """
    residuals = []
    for i, key in enumerate(beacon_positions):
        dist_calc = np.linalg.norm(x - beacon_positions[key])
        residuals.append(dist_calc - ranges[key])
    return residuals

def estimate_position(beacon_positions, ranges):
    """
    Estimate the position of the target based on beacon positions and measured ranges.
    
    :param beacon_positions: Dictionary of beacon positions keyed by beacon ID
    :param ranges: Dictionary of measured ranges to each beacon keyed by beacon ID
    :return: Estimated position as a numpy array
    """
    
    # Initial guess (can be origin)
    x0 = np.array([0.0, 0.0, 0.0])

    # Solve
    res = least_squares(trilateration, x0, args=(beacon_positions, ranges))
    position = res.x
    return position
    
def get_beacon_data(firefighter_id):
    """
    Fetch beacon positions and measured ranges for a specific tag from the API.
    
    :param tag_id: ID of the tag to locate
    :return: (beacon_positions, ranges) dictionaries
    """
    url = f"https://niesmiertelnik.replit.app/api/v1/firefighters/{firefighter_id}"
    response = requests.get(url)
    data = response.json()
    if data.get("error"):
        print(f"Error fetching data for firefighter {firefighter_id}: {data['error']}")
        return {}, {}
    
    beacon_positions = {}
    ranges = {}
    
    # Loop over UWB measurements
    for meas in data.get("uwb_measurements", []):
        beacon_id = meas["beacon_id"]
        range_m = meas["range_m"]

        
        # For position, we need beacon info from the beacons API
        beacon_url = f"https://niesmiertelnik.replit.app/api/v1/beacons"
        beacon_resp = requests.get(beacon_url)
        beacon_data = beacon_resp.json().get("beacons", [])
        
        # Find the beacon with matching ID
        beacon_info = next((b for b in beacon_data if b["id"] == beacon_id), None)
        if beacon_info:
            pos = beacon_info["position"]
            beacon_positions[beacon_id] = np.array([pos["x"], pos["y"], pos["z"]])
            ranges[beacon_id] = range_m
            
    return beacon_positions, ranges
                
                
if __name__ == "__main__":
    firefighter_id = "FF-001"  # Example firefighter ID
    beacon_positions, ranges = get_beacon_data(firefighter_id)
    print("Beacon Positions:", beacon_positions)
    print("Measured Ranges:", ranges)
    if len(beacon_positions) >= 3:
        estimate_position(beacon_positions, ranges)
    else:
        print("Not enough beacons with valid measurements to perform trilateration.")