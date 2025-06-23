import cv2
import numpy as np
from pyzbar.pyzbar import decode
import json
import os
import datetime
import time # Import time module for cooldown

# --- Configuration ---
JSON_FILE_PATH = "/GHMARIIII/tablet/cart_request.json"  # Path to the JSON file
COOLDOWN_SECONDS = 3 # Time in seconds to wait before processing the same QR code again


# --- Camera initialization ---
cap = cv2.VideoCapture(0) # Open the default camera
# Set camera properties for better resolution (adjust if your camera doesn't support these)
cap.set(3, 1280) # Width
cap.set(4, 1024) # Height
cap.set(15, 0.1) # Brightness (0.1 is usually a good starting point, adjust if needed)


# --- Variables for cooldown mechanism ---
last_scanned_data = None
last_scan_time = 0


# --- Function to write data to JSON ---
def write_data_to_json(product_id, action):
    """
    Writes product ID, action, and timestamp to a JSON file.
    Ensures the directory exists before writing.
    """
    data = {
        'product_id': product_id,
        'action': action,
        'timestamp': datetime.datetime.now().isoformat()
    }

    # Ensure the directory exists
    directory = os.path.dirname(JSON_FILE_PATH)
    if not os.path.exists(directory):
        try:
            os.makedirs(directory)
            print(f"Directory '{directory}' created.")
        except OSError as e:
            print(f"Error creating directory '{directory}': {e}")
            return False

    # Write the data to the JSON file
    try:
        with open(JSON_FILE_PATH, 'w') as f:
            json.dump(data, f)
        print(f"Request written to {JSON_FILE_PATH}")
        return True
    except IOError as e:
        print(f"Error writing to file '{JSON_FILE_PATH}': {e}")
        return False


# --- Main loop ---
while True:
    success, img2 = cap.read() # Read a frame from the camera
    if not success:
        print("Error: Could not capture image. Exiting...")
        break

    # Draw a horizontal red line at y=350 to indicate the scan zones
    cv2.line(img2, (0, 350), (img2.shape[1], 350), (0, 0, 255), 2)
    # Draw a black rectangle at the bottom right (purpose unclear, but kept from original)
    cv2.rectangle(img2, (1250, 655), (1300, 690), (0, 0, 0), -1)

    # Decode QR codes in the current frame
    decoded_barcodes = decode(img2)

    # Check if any barcodes were decoded
    if decoded_barcodes:
        # Process the first detected barcode (assuming one QR at a time for this logic)
        barcode = decoded_barcodes[0] # Consider modifying if you expect multiple QRs simultaneously
        myData = barcode.data.decode('utf-8')

        # Implement cooldown: Only process if it's a new QR or enough time has passed
        current_time = time.time()
        if myData != last_scanned_data or (current_time - last_scan_time) > COOLDOWN_SECONDS:
            print(f"Scanned: {myData}") # Log the scanned data

            pts = np.array([barcode.polygon], np.int32)
            pts = pts.reshape((-1, 1, 2))
            pts2 = barcode.rect

            action = None
            myOutput = ""
            myColor = (0, 0, 0) # Default color

            # Determine action based on QR code position relative to the red line
            if pts2.top < 350:
                myOutput = "Produit Ajoute"
                myColor = (0, 255, 0) # Green
                action = "add"
            elif pts2.top > 350:
                myOutput = "Produit Elimine"
                myColor = (0, 0, 222) # Darker Red/Brown
                action = "remove"
            else:
                # If exactly on the line (unlikely with integer pixels), treat as "dead zone" or add a small buffer
                myOutput = "Zone morte"
                myColor = (120, 122, 22) # Olive
                action = None

            # Draw polygon and text on the image
            cv2.polylines(img2, [pts], True, myColor, 2)
            cv2.putText(img2, myOutput, (pts2.left, pts2.top - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.9, myColor, 2)

            # If a valid action is determined, write to JSON and update cooldown variables
            if action:
                if write_data_to_json(myData, action):
                    last_scanned_data = myData
                    last_scan_time = current_time
        else:
            # If the same QR is detected within cooldown, just draw it without reprocessing
            pts = np.array([barcode.polygon], np.int32)
            pts = pts.reshape((-1, 1, 2))
            cv2.polylines(img2, [pts], True, (0, 255, 255), 2) # Yellow for already processed QRs
            cv2.putText(img2, "Already Processed", (barcode.rect.left, barcode.rect.top - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 255), 2)


    cv2.imshow("video", img2) # Display the processed frame

    # Wait for 1 millisecond and check for 'q' key press to exit
    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

# --- Cleanup ---
cap.release() # Release the camera
cv2.destroyAllWindows() # Close all OpenCV windows
