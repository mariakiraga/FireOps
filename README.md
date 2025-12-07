# FireOps – Real-Time Firefighter Tracking and Health Monitoring in GPS-Denied Environments

FireOps is an advanced system designed for **precise real-time tracking** and **biometric monitoring** of firefighters during operations, even in **GPS-denied environments** such as indoor structures, tunnels, or industrial facilities.  
It integrates **inertial navigation**, **UWB ranging**, **biometric sensing**, and **intelligent analysis** to enhance safety, operational awareness, and decision-making.

---

## Quick start
```bash
pip install -r requirements.txt
python app.py # runs flask backend server with model inference

#in separate terminal
python mock_ubw_client.py # fetches data from https://niesmiertelnik.replit.app/api/v1/firefighters/ and sends them to backend for position estimation
```

Open http://localhost:5000/firefighters to view json data incoming to the backend server.
### Frontend application

### Android application

---

## Problem

Firefighters often operate **inside buildings** or complex facilities where both **GPS** and **reliable communication** are unavailable.  
This makes it extremely difficult for command units to track personnel location, orientation, and health state in real time.

This leads to:
- Delayed reaction to life-threatening events  
- Overload of inconsistent or noisy alerts  
- Lack of situational awareness  
- Increased risk during mission-critical operations  

### Key challenges:
- No GPS inside buildings  
- Limited communication and radio interference  
- Firefighters must focus on rescue, not manually reporting status  
- Too many inconsistent alerts obscure what is truly critical  
- Missing continuous information about movement and biometric state  
- Low situational awareness causes delayed decisions and higher risk  

---

## What FireOps Enables

- **Navigation without GPS**, Internet, or external infrastructure  
- **Real-time position tracking** of all units  
- **Map reconstruction** based on movement of firefighters, rescuers, or animals  
- **Sharing tracks and mission data** to the command portal  
- **Generating heatmaps** of area penetration and search coverage  
- **Integration with Tactical Assault Kit (TAK)**  
- **Detection of critical biometric and behavioral events**  
- **Local data recording** on the personal device (black-box mode)  

---

## FireOps Architecture

FireOps integrates sensors, communication modules, backend services, and command tools into a unified operational ecosystem.

### Firefighter Device
**Sensors:**
- IMU: accelerometer, gyroscope, barometer, magnetometer  
- Biometrics: HR, temperature, SpO₂, respiration, steps  
- Environmental: oxygen, CO₂, gas levels  

**Analysis:**
- Event detection: man-down, critical vitals, SOS  
- Geolocation via GPS, UWB, inertial navigation  

**Communication:**
- BLE, LoRa, GSM  
- Device-to-device relaying to nearest firefighter  
- VPN / private APN  

### Gateway / Cloud
- Secure communication  
- Data processing  
- Backend inference  
- Command tools integration  

---

## Demo Components

### Android Application (Kotlin)
- IMU sensor data collection: acc, gyro, rotation vector, magnetometer, barometer  
- Biometric data collection from smartwatch: heart rate, temperature, oxygen saturation  
- GSM network communication to backend  
- Peer-to-peer BLE communication (TBD)  
- Map display with movement history and return path (Ariadne’s thread)  
- Local inference of **RONIN AI inertial model** (tested)  
- Local raw data storage (black-box mode)

### Backend (Python/Flask)
- Data ingestion from Android app  
- RONIN inertial localization model inference  
- Data ingestion from mission simulator  
- UWB-based trilateration for simulated firefighters  
- Event detection: man-down, high HR, low oxygen, etc.  
- Data storage in PostgreSQL  
- WebSocket communication for the command dashboard  

### Web Frontend (Commander Portal)
Technologies: Bootstrap / Leaflet / jQuery  
Features:
- Real-time map with locations and movement history  
- Firefighter list with filters  
- Biometric data, movement trends, heart-rate trends, battery status  
- Alert handling  

### TAK Integration
- Dockerized TAK Server (Linux)  
- Displaying firefighters and objects on TAK platform  
- Sharing mission data with civil and military responders  

---

## RONIN – Robust Neural Inertial Navigation

FireOps uses the **RONIN** AI model (SFU / Herath, Yan, Furukawa, 2020) for inertial navigation without GPS.

Key characteristics:
- Uses accelerometer + gyroscope data to estimate velocity vectors and trajectories  
- Based on ResNet / LSTM / TCN architecture with robust velocity loss  
- Trained on 42.7 hours of IMU data from 100 participants with ground-truth trajectories  
- Achieves drift < 0.3 m after 10 minutes in authors’ evaluation  
- Reduces orientation drift significantly compared to raw sensor readings  
- Outperforms traditional inertial navigation methods under varied conditions  

More information:  [RoNIN model](https://ieeexplore.ieee.org/abstract/document/9196860) 

RONIN is available on GPL-3.0 license.

---

## Tactical Assault Kit (TAK)

TAK is a military-grade situational awareness ecosystem used by NATO forces and civil protection units.

FireOps integrates with TAK to enable:
- Real-time sharing of positions, tracks, and tactical information  
- Interoperability through Cursor-on-Target (CoT) open standard  
- Map symbology compatible with NATO APP-6 / MIL-STD  
- Support for ATAK (Android), WinTAK (Windows), and CivTAK  
- Extension through custom plugins — FireOps acts as one such plugin  

More information: https://tak.gov/

[Setup information](tak-integration/setup-tak-integration.md)

---

## Use Case: “Fireline”  
**Scenario:** firefighters operating in a chemical warehouse fire.

- Each firefighter carries a phone with the FireOps app  
- Command unit operates inside a vehicle with TAK and the FireOps dashboard  
- Firefighters start the mission inside a truck; they see their trajectory in the app  
- When someone finds a survivor → **MARK SURVIVOR** → marker appears in both systems (Android + TAK)  
- Commander dispatches another team to follow the exact same path  
- The return path is shown in both systems for safe extraction  

---



Current system can be intergated with our FOKZ Nav Mobile Application for Inertial Navigation. 
Our application uses Android raw accelerometer and gyroscope signals paired with rotation to map local (phone based) frame into global navigation frame.

The server takes 200Hz imu data and start position and returns new estimated position. 



### Citation
Please cite the following paper is you use the code, paper or data:  
[Herath, S., Yan, H. and Furukawa, Y., 2020, May. RoNIN: Robust Neural Inertial Navigation in the Wild: Benchmark, Evaluations, & New Methods. In 2020 IEEE International Conference on Robotics and Automation (ICRA) (pp. 3146-3152). IEEE.](https://ieeexplore.ieee.org/abstract/document/9196860)
