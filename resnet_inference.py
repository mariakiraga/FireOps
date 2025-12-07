import torch
import numpy as np
import matplotlib.pyplot as plt
import sys
import os
import quaternion
import h5py
import json

sys.path.append(os.path.join( "source"))  # uncomment and set path if necessary
from ronin_resnet import get_model  # your get_model() from the big script
from data_glob_speed import StreamingIMUDataset  # your data loading class

import argparse

def load_model(model_path, device):
    checkpoint = torch.load(model_path, map_location=device)
    network = get_model('resnet18')
    network.load_state_dict(checkpoint['model_state_dict'])
    network.eval().to(device)
    print(f'Model {model_path} loaded to device {device}.')
    return network

def get_new_position(start_pos, imu_sample, device, network, dt=1.0):
    """
    Given a start position and an IMU sample, predict the new position after dt seconds.
    imu_sample: numpy array of shape (1, 6, 200) - single IMU sample, accelerometer + gyroscope data of 200 timesteps (each 5ms = 200Hz sampling)
    start_pos: numpy array of shape (2,) - starting (x,y) position
    dt: time duration in seconds
    """
    # load imu sample to tensor (feature shape (1,6,200))
    # Ensure tensor is float32
    feat = torch.from_numpy(imu_sample).float().to(device)
    
    with torch.no_grad():
        pred = network(feat)  # output shape (1,2) -> (vx, vy)
    vx, vy = pred.cpu().numpy()[0]
    #apply bias correction
    #vx -= -0.6629
    #vy -= -0.2710
    print(f"Predicted velocity: vx={vx:.4f}, vy={vy:.4f}")

    # compute new position
    new_pos = start_pos + np.array([vx, vy]) * dt
    print(f"Computed new position: {new_pos}")

    return new_pos, vx, vy


def generate_imu_data_from_file(data_path, sampling_rate_hz=200, stride=20):
    """
    Simulates IMU streaming by reading RoNIN HDF5 files and appending
    1-second windows (200 samples) to the dataset, exactly as if they
    arrived in real-time.
    """

    if data_path.endswith('/'):
        data_path = data_path[:-1]


    # Load IMU sequences
    with h5py.File(os.path.join(data_path, 'data.hdf5')) as f:
        gyro = np.copy(f['synced/gyro'])          # (N, 3)
        acce = np.copy(f['synced/acce'])          # (N, 3) 
        # Mean accelerometer biases:
        # X-axis: -0.051124
        # Y-axis: 0.036182
        # Z-axis: 0.007724
        #acce += np.array([-0.051124, 0.036182, 0.007724])  # bias correction
        game_rv = np.copy(f['synced/game_rv'])    # (N, 4) rotation vector

    # Initialize our streaming dataset
    dataset = StreamingIMUDataset(
        window_size=sampling_rate_hz,  # 200 samples = 1 second at 200Hz
        stride=stride,                     # 20 = 0.1s sliding window
    )

    # Simulate real-time streaming in 1-second blocks
    for start_idx in range(0, gyro.shape[0], sampling_rate_hz):
        end_idx = start_idx + sampling_rate_hz

        if end_idx > gyro.shape[0]:
            break  # drop incomplete last chunk (or pad if needed)

        # ------------ MAKE CHUNK (200 samples) ------------
        gyro_chunk = gyro[start_idx:end_idx]        # (200, 3)
        acc_chunk = acce[start_idx:end_idx]         # (200, 3)
        game_rv_chunk = game_rv[start_idx:end_idx]  # (200, 4)

        # ------------ Append streaming chunk ------------
        dataset.append_chunk(gyro_chunk, acc_chunk, game_rv_chunk)

    return dataset




def main():
    parser = argparse.ArgumentParser(description="RoNIN ResNet Inference Example")
    parser.add_argument('--data_path', 
                        type=str, 
                        default='unseen_subjects_test_set/a006_2', 
                        help='Path to directory containing info.json + data.hdf5')
    args = parser.parse_args()

    # -------------------- DEVICE --------------------
    device = torch.device('cuda:0' if torch.cuda.is_available() else 'cpu')

    # -------------------- MODEL ---------------------
    model_path = "ronin_resnet/checkpoint_gsn_latest.pt"
    network = load_model(model_path, device)

    # -------------------- LOAD STREAMING IMU DATA ------------------------
    dataset = generate_imu_data_from_file(args.data_path, stride=20)
    print(f"Dataset contains {len(dataset)} sliding windows.")

    # -------------------- RUN INFERENCE ------------------------
    start_pos = np.array([0.0, 0.0], dtype=np.float32)
    generated_positions = [start_pos.copy()]

    for idx in range(len(dataset)):
        # dataset[idx] must return (1, 6, 200)
        imu_sample = dataset[idx]                   # numpy array
        new_pos, _, _ = get_new_position(start_pos, imu_sample, device, network, dt=1.0)
        start_pos = new_pos
        generated_positions.append(start_pos.copy())

    generated_positions = np.array(generated_positions)

    # -------------------- PLOT TRAJECTORY ------------------------
    plt.figure()
    plt.plot(generated_positions[:, 0], generated_positions[:, 1])
    plt.annotate("start", generated_positions[0])
    plt.annotate("end", generated_positions[-1])
    plt.axis("equal")
    plt.title("RoNIN Estimated Trajectory")

    output_path = "trajectory_plot.png"
    plt.savefig(output_path)
    print(f"Trajectory saved to {output_path}")
    plt.close()


if __name__ == '__main__':
    main()
