import json
import os

# Configuration
PRODUCT_ID_TO_ADD = "11"
JSON_FILE_PATH = "/GHMARIIII/tablet/cart_request.json"  # Updated path

# Create the data dictionary
data = {
    'product_id': PRODUCT_ID_TO_ADD,
    'action': 'add'
}

# Ensure the directory exists
directory = os.path.dirname(JSON_FILE_PATH)
if not os.path.exists(directory):
    try:
        os.makedirs(directory)
        print(f"Directory '{directory}' created.")
    except OSError as e:
        print(f"Error creating directory '{directory}': {e}")
        exit()

# Write the data to the JSON file
try:
    with open(JSON_FILE_PATH, 'w') as f:
        json.dump(data, f)
    print(f"Request written to {JSON_FILE_PATH}")
except IOError as e:
    print(f"Error writing to file '{JSON_FILE_PATH}': {e}")
    exit()