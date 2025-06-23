<?php
header('Content-Type: application/json');

$scan_file = '/GHMARIIII/rfid_scans/latest_scan.txt';
$new_scan_detected = false;
$scan_data = null;

if (file_exists($scan_file) && filesize($scan_file) > 0) {
    $scan_content = file_get_contents($scan_file);
    $scan_info = json_decode($scan_content, true);
    
    if ($scan_info && isset($scan_info['rfid_uid']) && isset($scan_info['timestamp'])) {
        // Check if scan is recent (within last 30 seconds)
        $scan_time = strtotime($scan_info['timestamp']);
        $current_time = time();
        
        if (($current_time - $scan_time) <= 30) {
            $new_scan_detected = true;
            $scan_data = $scan_info;
        }
    }
}

echo json_encode([
    'scan_detected' => $new_scan_detected,
    'scan_data' => $scan_data
]);
?>