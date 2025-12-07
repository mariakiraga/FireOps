# **Firefighter Indoor/Outdoor Tracking API**

This API provides endpoints for real-time tracking of firefighters using a combination of Inertial Measurement Unit (IMU) data processed by a ResNet model (Dead Reckoning) and Ultra-Wideband (UWB) technology.

The base path for all production endpoints is assumed to be /v1/.


## **1. Get All Firefighter Positions**

Retrieves the latest known positions and metadata for all tracked firefighters.

|            |                |                                                           |
| ---------- | -------------- | --------------------------------------------------------- |
| **Method** | **Endpoint**   | **Description**                                           |
| GET        | /firefighters/ | Returns a list of all current firefighter status records. |


### **Response (200 OK)**

Returns a JSON object containing a list of firefighter status records.

{\
  "firefighters": \[\
    {\
      "timestamp": "2025-12-07T03:20:00.000Z",\
      "firefighter": {\
        "id": "FF123",\
        "name": "Janusz Androidowski",\
        "rank": "Lieutenant",\
        "role": "Entry",\
        "team": "Alpha"\
      },\
      "position": {\
        "x": 10.5,\
        "y": 5.2,\
        "z": 0.0,\
        "floor": 0,\
        "source": "imu\_model"\
      },\
      "gps": {\
        "latitude": 50.0614,\
        "longitude": 19.9333\
      }\
    }\
    // ... more firefighter objects\
  ]\
}


## **2. Predict Position from IMU Data (ResNet Model)**

Calculates the next position of a firefighter based on a sequence of IMU data (accelerometer, gyroscope, and rotation vector) and their known starting position. This endpoint uses the pre-loaded ResNet model for inference.

|            |                                       |                                                      |
| ---------- | ------------------------------------- | ---------------------------------------------------- |
| **Method** | **Endpoint**                          | **Description**                                      |
| POST       | /predict\_position/\<firefighter\_id> | Calculates position change based on 200 IMU samples. |


### **Path Parameters**

|                 |          |                                                         |
| --------------- | -------- | ------------------------------------------------------- |
| **Parameter**   | **Type** | **Description**                                         |
| firefighter\_id | string   | The unique identifier of the firefighter being tracked. |


### **Request Body**

The request expects exactly **200 interpolated IMU samples** covering the segment of movement.

|           |            |                                                                                                                       |
| --------- | ---------- | --------------------------------------------------------------------------------------------------------------------- |
| **Field** | **Type**   | **Description**                                                                                                       |
| start     | number\[2] | The starting 2D position \[x0, y0] in local coordinates.                                                              |
| imu\_data | array      | An array of IMU records. The client should aim to provide a raw sequence of records covering the desired time window. |


#### **imu\_data Object Structure**

|           |            |                                                            |
| --------- | ---------- | ---------------------------------------------------------- |
| **Field** | **Type**   | **Description**                                            |
| t\_ns     | integer    | Timestamp in nanoseconds. Used for server-side resampling. |
| acc       | number\[3] | Accelerometer data \[ax, ay, az] (m/s²).                   |
| gyro      | number\[3] | Gyroscope data \[gx, gy, gz] (rad/s).                      |
| rv        | number\[4] | Game Rotation Vector quaternion \[rx, ry, rz, rw].         |

{\
  "start": \[10.5, 5.2],\
  "imu\_data": \[\
    {\
      "t\_ns": 1733519880000000000,\
      "acc": \[0.05, 9.81, 0.02],\
      "gyro": \[0.001, -0.002, 0.003],\
      "rv": \[0.001, 0.002, 0.999, 0.001]\
    },\
    // ... 198 more IMU records ...\
    {\
      "t\_ns": 1733519890000000000,\
      "acc": \[0.15, 9.75, 0.08],\
      "gyro": \[0.010, 0.005, -0.005],\
      "rv": \[0.002, 0.005, 0.998, 0.001]\
    }\
  ]\
}


### **Response (200 OK)**

|                    |            |                                                             |
| ------------------ | ---------- | ----------------------------------------------------------- |
| **Field**          | **Type**   | **Description**                                             |
| firefighter\_id    | string     | The ID of the firefighter whose position was updated.       |
| new\_position      | number\[2] | The predicted new 2D position \[x, y] in local coordinates. |
| inference\_time\_s | number     | The time taken for model inference in seconds.              |

{\
  "firefighter\_id": "FF123",\
  "new\_position": \[11.1, 5.5],\
  "inference\_time\_s": 0.008451\
}


## **3. Calculate Position from UWB Data**

Calculates the current position of a firefighter using Ultra-Wideband (UWB) ranging data. This typically serves as a reference point for the IMU-based dead reckoning.

|            |                                              |                                                           |
| ---------- | -------------------------------------------- | --------------------------------------------------------- |
| **Method** | **Endpoint**                                 | **Description**                                           |
| POST       | /calculate\_position\_uwb/\<firefighter\_id> | Estimates position via UWB trilateration/multilateration. |


### **Path Parameters**

|                 |          |                                                     |
| --------------- | -------- | --------------------------------------------------- |
| **Parameter**   | **Type** | **Description**                                     |
| firefighter\_id | string   | The unique identifier of the firefighter to locate. |


### **Request Body**

The server-side implementation implies this endpoint expects the **entire current list of firefighter data** in the request body (e.g., the output of GET /firefighters/). This body will be modified on the server and saved.

**Expected Body Structure:** Same as the response from GET /firefighters/.


### **Response (200 OK)**

|                 |            |                                                            |
| --------------- | ---------- | ---------------------------------------------------------- |
| **Field**       | **Type**   | **Description**                                            |
| firefighter\_id | string     | The ID of the firefighter.                                 |
| position        | number\[3] | The estimated 3D position \[x, y, z] in local coordinates. |

{\
  "firefighter\_id": "FF123",\
  "position": \[11.1, 5.5, 0.5]\
}


## **4. Test Inference (Development Endpoint)**

A utility endpoint to test the model loading and inference pipeline with randomly generated mock data.

|            |                  |                                                                            |
| ---------- | ---------------- | -------------------------------------------------------------------------- |
| **Method** | **Endpoint**     | **Description**                                                            |
| GET        | /test\_inference | Runs a prediction with randomized IMU data. **For testing purposes only.** |


### **Response (200 OK)**

|                 |            |                                                      |
| --------------- | ---------- | ---------------------------------------------------- |
| **Field**       | **Type**   | **Description**                                      |
| start\_position | number\[2] | The mock starting 2D position used for the test.     |
| new\_position   | number\[2] | The predicted new 2D position.                       |
| vx              | number     | The predicted velocity component in the X-direction. |
| vy              | number     | The predicted velocity component in the Y-direction. |

{\
  "start\_position": \[3.456, 8.765],\
  "new\_position": \[3.456015, 8.765005],\
  "vx": 0.000015,\
  "vy": 0.000005\
}
