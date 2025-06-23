<?php
header('Content-Type: application/json');
session_start();

// Initialize response
$response = [
    'status' => 'no_scan',
    'message' => 'No recent RFID scan detected',
    'timestamp' => date('Y-m-d H:i:s'),
    'debug' => []
];

// Define database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

// Path to RFID data file
$rfid_data_file = __DIR__ . '/rfid_data.json';

// Check if user is already logged in
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
    // Only proceed if the user is not already logged in
    if (file_exists($rfid_data_file)) {
        $response['debug'][] = "RFID data file exists";
        
        $file_content = @file_get_contents($rfid_data_file);
        if ($file_content !== false) {
            $rfid_data = json_decode($file_content, true);
            
            if ($rfid_data && isset($rfid_data['scans']) && !empty($rfid_data['scans'])) {
                // Get the most recent scan
                $latest_scan = end($rfid_data['scans']);
                $response['debug'][] = "Found latest scan: " . json_encode($latest_scan);
                
                // Check if scan is recent (within last 10 seconds)
                $scan_time = strtotime($latest_scan['timestamp']);
                $current_time = time();
                $time_diff = $current_time - $scan_time;
                
                if ($time_diff <= 10) {  // Only process scans from the last 10 seconds
                    $response['debug'][] = "Scan is recent: {$time_diff} seconds ago";
                    
                    // Connect to database
                    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
                    
                    if (!$conn->connect_error) {
                        $uid = $latest_scan['uid'];
                        $response['debug'][] = "Checking UID: {$uid}";
                        
                        $stmt = $conn->prepare("SELECT id, name, email, address, solde, num_commande, phone, bio, rfid_uid FROM client WHERE rfid_uid = ?");
                        $stmt->bind_param("s", $uid);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $user = $result->fetch_assoc();
                            $response['debug'][] = "User found: {$user['name']}";
                            
                            // Store user information in session
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['user_email'] = $user['email'] ?? '';
                            $_SESSION['user_address'] = $user['address'] ?? '';
                            $_SESSION['user_solde'] = $user['solde'] ?? '0.00';
                            $_SESSION['user_num_commande'] = $user['num_commande'] ?? '';
                            $_SESSION['user_phone'] = $user['phone'] ?? '';
                            $_SESSION['user_bio'] = $user['bio'] ?? '';
                            $_SESSION['user_rfid_uid'] = $user['rfid_uid'] ?? '';
                            
                            // Set success response
                            $response['status'] = 'success';
                            $response['message'] = 'Authentification RFID réussie';
                            $response['user'] = [
                                'id' => $user['id'],
                                'name' => $user['name']
                            ];
                            $response['reload'] = true;
                            
                            // Log successful authentication
                            error_log("RFID AJAX Login: Successful for UID {$uid}, User: {$user['name']} (ID: {$user['id']})");
                        } else {
                            $response['status'] = 'error';
                            $response['message'] = 'Carte RFID non reconnue';
                            $response['error_details'] = 'Cette carte RFID n\'est pas enregistrée dans le système.';
                            $response['debug'][] = "No user found for UID {$uid}";
                            
                            // Log failed attempt
                            error_log("RFID AJAX Login: Failed for UID {$uid}, card not registered");
                        }
                        
                        $stmt->close();
                        $conn->close();
                    } else {
                        $response['status'] = 'error';
                        $response['message'] = 'Erreur de connexion à la base de données';
                        $response['debug'][] = "Database connection failed: {$conn->connect_error}";
                    }
                } else {
                    $response['status'] = 'no_recent_scan';
                    $response['message'] = 'Dernier scan trop ancien';
                    $response['debug'][] = "Scan is too old: {$time_diff} seconds ago";
                }
            } else {
                $response['debug'][] = "No scans found in RFID data";
            }
        } else {
            $response['debug'][] = "Could not read RFID data file";
        }
    } else {
        $response['debug'][] = "RFID data file does not exist";
    }
} else {
    $response['status'] = 'already_logged_in';
    $response['message'] = 'Utilisateur déjà connecté';
    $response['debug'][] = "User already logged in: {$_SESSION['user_name']}";
}

// Return response as JSON
echo json_encode($response);
?>