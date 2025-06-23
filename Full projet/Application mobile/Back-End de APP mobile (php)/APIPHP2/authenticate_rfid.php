<?php
session_start();
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

header('Content-Type: application/json');

// Check if RFID UID is provided
if (!isset($_GET['rfid_uid'])) {
    echo json_encode(['success' => false, 'message' => 'RFID UID is required']);
    exit;
}

$rfid_uid = $_GET['rfid_uid'];

// Log the authentication attempt
error_log("RFID Authentication attempt: $rfid_uid");

// Connect to database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if we need to add rfid_uid column
$check_column = $conn->query("SHOW COLUMNS FROM client LIKE 'rfid_uid'");
if ($check_column->num_rows == 0) {
    // Add the column if it doesn't exist
    $alter_query = "ALTER TABLE client ADD COLUMN rfid_uid VARCHAR(50) DEFAULT NULL";
    if (!$conn->query($alter_query)) {
        error_log("Failed to add rfid_uid column: " . $conn->error);
    } else {
        error_log("Added rfid_uid column to client table");
    }
}

// First, check if this is a registration request
if (isset($_GET['register']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    
    // Update user with RFID UID
    $stmt = $conn->prepare("UPDATE client SET rfid_uid = ? WHERE id = ?");
    $stmt->bind_param("si", $rfid_uid, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'RFID card registered successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to register RFID card']);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

// If not registration, this is an authentication request
$stmt = $conn->prepare("SELECT id, name, email, address, solde, num_commande, phone, bio FROM client WHERE rfid_uid = ?");
$stmt->bind_param("s", $rfid_uid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // No user found with this RFID card
    echo json_encode(['success' => false, 'message' => 'No user found with this RFID card']);
} else {
    // User found, create session if this is a web request
    $user = $result->fetch_assoc();
    
    if (isset($_GET['web']) && $_GET['web'] == 1) {
        // Web browser login - set up session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_address'] = $user['address'] ?? '';
        $_SESSION['user_solde'] = $user['solde'] ?? '0.00';
        $_SESSION['user_num_commande'] = $user['num_commande'] ?? '';
        $_SESSION['user_phone'] = $user['phone'] ?? '';
        $_SESSION['user_bio'] = $user['bio'] ?? '';
        
        // Clear any QR login tokens
        if (isset($_SESSION['qr_login_token'])) {
            unset($_SESSION['qr_login_token']);
        }
        
        error_log("RFID Web login successful for user ID: " . $user['id']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Authentication successful',
            'redirect' => true
        ]);
    } else {
        // API request - return user data
        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'] ?? '',
                'balance' => $user['solde'] ?? '0.00'
            ]
        ]);
    }
}

$stmt->close();
$conn->close();
?>