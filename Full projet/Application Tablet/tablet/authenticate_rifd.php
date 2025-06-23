<?php
session_start();
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => 'Authentication failed',
    'debug' => []
];

// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    $response['debug'][] = "Database connection failed: " . $conn->connect_error;
    echo json_encode($response);
    exit;
}

// Check if RFID UID is provided
if (isset($_GET['rfid_uid'])) {
    $rfid_uid = $_GET['rfid_uid'];
    $response['debug'][] = "Received RFID UID: " . $rfid_uid;
    
    // Check if this is a web authentication or API call
    $is_web = isset($_GET['web']) && $_GET['web'] == '1';
    
    // Query to check if the RFID UID exists in the database
    $sql = "SELECT id, name, email, address, solde, num_commande, phone, bio, rfid_uid 
            FROM client 
            WHERE rfid_uid = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $rfid_uid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $response['debug'][] = "User found: " . $user['name'];
        
        // If this is a web login, set session variables
        if ($is_web) {
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
            
            $response['debug'][] = "Session variables set for user ID: " . $user['id'];
        }
        
        $response['success'] = true;
        $response['message'] = "Authentication successful";
        $response['user'] = [
            'id' => $user['id'],
            'name' => $user['name']
        ];
    } else {
        $response['message'] = "RFID card not registered";
        $response['debug'][] = "No user found with RFID UID: " . $rfid_uid;
    }
    
    $stmt->close();
} else {
    $response['message'] = "RFID UID not provided";
    $response['debug'][] = "RFID UID parameter missing";
}

// Optional: Log authentication attempts
$log_file = __DIR__ . '/rfid_auth_log.txt';
$log_message = date('Y-m-d H:i:s') . " | Success: " . ($response['success'] ? "Yes" : "No") . 
               " | RFID: " . ($rfid_uid ?? "None") . 
               " | Message: " . $response['message'] . "\n";
file_put_contents($log_file, $log_message, FILE_APPEND);

// Close database connection
$conn->close();

echo json_encode($response);
?>