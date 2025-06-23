import json
import time
import datetime
import os
import serial
import mysql.connector
from datetime import timezone

# File path configuration
JSON_FILE = r'C:\xampp\htdocs\ghmariiii\tablet\rfid_data.json'

# Serial port configuration
SERIAL_PORT = 'COM6'  # Change to your RFID reader's COM port
BAUD_RATE = 9600

# MySQL Database configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'gestion_stock'
}


def get_user_from_database(uid):
    """Get user data from MySQL database using RFID UID"""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        cursor = connection.cursor(dictionary=True)

        query = "SELECT id as user_id, name, email, solde as balance, rfid_uid as uid FROM client WHERE rfid_uid = %s"
        cursor.execute(query, (uid,))
        user = cursor.fetchone()

        cursor.close()
        connection.close()

        return user
    except Exception as e:
        print(f"Database error: {e}")
        return None


def save_scan(uid, user_data):
    """Save RFID scan to JSON file"""
    os.makedirs(os.path.dirname(JSON_FILE), exist_ok=True)

    # Load existing data or create new
    if os.path.exists(JSON_FILE):
        try:
            with open(JSON_FILE, 'r') as file:
                data = json.load(file)
        except:
            data = {"scans": []}
    else:
        data = {"scans": []}

    # Update timestamp
    current_time = datetime.datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

    # Create scan entry
    scan_data = {
        'uid': uid,
        'timestamp': current_time,
        'user_id': user_data.get('user_id', 'unknown') if user_data else 'unknown',
        'name': user_data.get('name', 'unknown') if user_data else 'unknown',
        'balance': float(user_data.get('balance', 0.0)) if user_data else 0.0
    }

    # Add scan to data
    data["scans"].append(scan_data)
    data["last_updated"] = current_time

    # Save data
    with open(JSON_FILE, 'w') as file:
        json.dump(data, file, indent=4)

    print(f"Saved scan for UID: {uid}")
    return scan_data


def read_rfid_from_serial():
    """Read RFID UID from serial port"""
    try:
        # Open serial port
        ser = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=1)
        print(f"Connected to RFID reader on {SERIAL_PORT}")

        while True:
            # Read data from serial port
            if ser.in_waiting > 0:
                # Read line from serial
                serial_data = ser.readline().decode('utf-8').strip()

                # Extract UID
                if serial_data.startswith('RFID:'):
                    uid = serial_data.split(':')[1].strip()
                else:
                    uid = serial_data.strip()

                # Get user data
                user_data = get_user_from_database(uid)

                print(f"Card detected - UID: {uid}")

                if user_data:
                    print(f"User: {user_data['name']} (ID: {user_data['user_id']})")
                else:
                    print("Unknown card")

                # Save scan
                save_scan(uid, user_data)
                print("-" * 30)

            time.sleep(0.1)

    except KeyboardInterrupt:
        print("\nStopped")
        if 'ser' in locals():
            ser.close()
    except Exception as e:
        print(f"Error: {e}")
        manual_input_mode()


def manual_input_mode():
    """Manual input mode for testing"""
    print("\nManual Input Mode")
    print("-" * 30)

    while True:
        # Get input
        uid = input("Scan RFID card (or type UID manually): ").strip()

        if uid.lower() == 'exit':
            break

        if not uid:
            continue

        # Get user data
        user_data = get_user_from_database(uid)

        if user_data:
            print(f"User: {user_data['name']} (ID: {user_data['user_id']})")
        else:
            print("Unknown card")

        # Save scan
        save_scan(uid, user_data)
        print("-" * 30)


if __name__ == "__main__":
    print(f"Date: {datetime.datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')}")

    try:
        mode = input("Select (1) Serial or (2) Manual Input [1/2]: ").strip()

        if mode == '2':
            manual_input_mode()
        else:
            read_rfid_from_serial()
    except Exception as e:
        print(f"Error: {e}")
        manual_input_mode()