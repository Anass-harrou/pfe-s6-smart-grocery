<?php
/**
 * Enhanced Login API for Smart Grocery App
 * Date: 2025-06-23 03:00:15
 * Author: Anass-harrou
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "gestion_stock";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Process request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email and password are required'
        ]);
        exit;
    }
    
    // Sanitize input
    $email = $conn->real_escape_string($email);
    
    // Query database
    $sql = "SELECT * FROM client WHERE email = '$email'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // For debugging - remove in production
        file_put_contents('login_debug.txt', 
            "Attempted login for email: $email\n" .
            "RFID_UID: " . ($user['rfid_uid'] ?? 'Not set') . "\n" .
            "Timestamp: " . date('Y-m-d H:i:s') . "\n\n", 
            FILE_APPEND);
        
        // Password verification
        $password_verified = false;
        
        // Direct comparison (for plaintext passwords in database)
        if ($password === $user['password']) {
            $password_verified = true;
        }
        // MD5 hashed passwords
        else if (md5($password) === $user['password']) {
            $password_verified = true;
        }
        // PHP's password_hash format
        else if (function_exists('password_verify') && password_verify($password, $user['password'])) {
            $password_verified = true;
        }
        
        if ($password_verified) {
            // Update last login time
            $now = date('Y-m-d H:i:s');
            $conn->query("UPDATE client SET last_login = '$now' WHERE id = " . $user['id']);
            
            // Generate RFID if it doesn't exist or is empty
            if (empty($user['rfid_uid']) || $user['rfid_uid'] === NULL) {
                $rfid = generateRFID();
                $conn->query("UPDATE client SET rfid_uid = '$rfid' WHERE id = " . $user['id']);
                $user['rfid_uid'] = $rfid;
            }
            
            // Prepare response data
            $userData = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'solde' => $user['solde'],
                'rfid' => $user['rfid_uid'], // Send rfid_uid as rfid in response
                'last_login' => $now
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $userData
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Incorrect password'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed'
    ]);
}

/**
 * Generate a random RFID number
 * @return string The generated RFID
 */
function generateRFID() {
    // Generate a 14-digit RFID number
    $rfid = '';
    for ($i = 0; $i < 14; $i++) {
        $rfid .= mt_rand(0, 9);
    }
    return $rfid;
}

$conn->close();
?>