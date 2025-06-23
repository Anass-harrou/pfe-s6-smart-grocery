<?php
header('Content-Type: application/json');

// Path to the JSON file from the Python script
$rfid_data_path = __DIR__ . '/rfid_data.json';

// Initialize response
$response = [
    'scan_detected' => false,
    'scan_data' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

// Check if the file exists and is readable
if (file_exists($rfid_data_path) && is_readable($rfid_data_path)) {
    // Read the JSON file
    $json_content = file_get_contents($rfid_data_path);
    
    if ($json_content !== false) {
        $rfid_data = json_decode($json_content, true);
        
        // Check if the JSON was parsed successfully and has the expected structure
        if ($rfid_data !== null && isset($rfid_data['scans']) && is_array($rfid_data['scans']) && !empty($rfid_data['scans'])) {
            // Get the most recent scan
            $latest_scan = end($rfid_data['scans']);
            
            // Check if the scan is recent (within the last 10 seconds)
            $scan_time = strtotime($latest_scan['timestamp']);
            $current_time = time();
            $time_diff = $current_time - $scan_time;
            
            if ($time_diff <= 10) { // 10 second window for authentication
                $response['scan_detected'] = true;
                $response['scan_data'] = [
                    'rfid_uid' => $latest_scan['uid'],
                    'user_id' => $latest_scan['user_id'],
                    'name' => $latest_scan['name'],
                    'timestamp' => $latest_scan['timestamp'],
                    'time_diff' => $time_diff
                ];
            }
        }
    }
}

echo json_encode($response);
?>