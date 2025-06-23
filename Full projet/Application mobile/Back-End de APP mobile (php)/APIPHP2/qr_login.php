<?php
// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Debug log
$log_file = 'qr_login_debug.log';
file_put_contents($log_file, 
    date('Y-m-d H:i:s') . ' - REQUEST: ' . $_SERVER['REQUEST_METHOD'] . "\n" .
    'POST: ' . json_encode($_POST) . "\n" .
    'RAW: ' . file_get_contents('php://input') . "\n\n", 
    FILE_APPEND);

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die(json_encode([
        'success' => false, 
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Get data from either POST variables or JSON input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

// Log the parsed input
file_put_contents($log_file, 
    date('Y-m-d H:i:s') . ' - Parsed input JSON: ' . json_encode($input) . "\n", 
    FILE_APPEND);

// Get parameters from JSON body first, then fallback to POST
$token = '';
$user_id = null;

if (!empty($input)) {
    // JSON data
    $token = isset($input['qr_login_token']) ? $input['qr_login_token'] : '';
    $token = empty($token) && isset($input['token']) ? $input['token'] : $token;
    
    // Check all possible ID field names that mobile app might send
    if (isset($input['user_id'])) {
        $user_id = $input['user_id'];
    } elseif (isset($input['client_id'])) {
        $user_id = $input['client_id'];
    } elseif (isset($input['id'])) {
        $user_id = $input['id'];
    } elseif (isset($input['userId'])) {
        $user_id = $input['userId'];
    } elseif (isset($input['clientId'])) {
        $user_id = $input['clientId'];
    }
    
} else {
    // Form data
    $token = isset($_POST['qr_login_token']) ? $_POST['qr_login_token'] : '';
    $token = empty($token) && isset($_POST['token']) ? $_POST['token'] : $token;
    
    // Check all possible ID field names
    if (isset($_POST['user_id'])) {
        $user_id = $_POST['user_id'];
    } elseif (isset($_POST['client_id'])) {
        $user_id = $_POST['client_id'];
    } elseif (isset($_POST['id'])) {
        $user_id = $_POST['id'];
    } elseif (isset($_POST['userId'])) {
        $user_id = $_POST['userId'];
    } elseif (isset($_POST['clientId'])) {
        $user_id = $_POST['clientId'];
    }
}

// Validate required parameters
if (empty($token)) {
    file_put_contents($log_file, 
        date('Y-m-d H:i:s') . " - ERROR: Missing token parameter\n", 
        FILE_APPEND);
    
    die(json_encode([
        'success' => false, 
        'message' => 'Missing QR login token. Please scan a valid QR code.'
    ]));
}

// IMPORTANT: If no user ID is provided, check if we're simply validating an existing token
if ($user_id === null) {
    // Check if this is a token validation request from the web
    
    // FIXED: Using correct column names from your database
    $query = "SELECT qr.*, c.name, c.email, c.solde 
              FROM qr_logins qr 
              JOIN client c ON qr.client_id = c.id 
              WHERE qr.token = ? AND qr.authenticated = 1 
              AND qr.expires_at > NOW()";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        file_put_contents($log_file, 
            date('Y-m-d H:i:s') . " - ERROR preparing token validation query: " . $conn->error . "\n", 
            FILE_APPEND);
        
        die(json_encode([
            'success' => false,
            'message' => 'Error checking token: ' . $conn->error
        ]));
    }
    
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $login_data = $result->fetch_assoc();
        
        file_put_contents($log_file, 
            date('Y-m-d H:i:s') . " - VALIDATION: QR token is valid and authenticated\n", 
            FILE_APPEND);
        
        echo json_encode([
            'success' => true,
            'message' => 'Token is valid and authenticated',
            'authenticated' => true,
            'user' => [
                'id' => $login_data['client_id'], // FIXED: Use client_id instead of user_id
                'name' => $login_data['name'],
                'email' => $login_data['email'],
                'solde' => $login_data['solde']
            ]
        ]);
    } else {
        file_put_contents($log_file, 
            date('Y-m-d H:i:s') . " - VALIDATION: QR token is invalid, not authenticated, or expired\n", 
            FILE_APPEND);
        
        echo json_encode([
            'success' => true,
            'message' => 'Token is valid but not authenticated yet',
            'authenticated' => false
        ]);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

// We get here when user_id is provided - means the mobile app is authenticating a token
$numeric_id = is_numeric($user_id) ? intval($user_id) : 0;

// 1. Check if the user exists
$query = "SELECT * FROM client WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $numeric_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    file_put_contents($log_file, 
        date('Y-m-d H:i:s') . " - ERROR: User not found with ID: $numeric_id\n", 
        FILE_APPEND);
    
    die(json_encode([
        'success' => false, 
        'message' => 'User not found with provided ID: ' . $numeric_id
    ]));
}

$user = $result->fetch_assoc();
$stmt->close();

// 3. Insert or update QR login entry
try {
    // Check if the token already exists
    $check_token = "SELECT * FROM qr_logins WHERE token = ?";
    $stmt = $conn->prepare($check_token);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $token_result = $stmt->get_result();
    $token_exists = ($token_result->num_rows > 0);
    $stmt->close();
    
    // FIXED: Using actual column names from your database structure
    if ($token_exists) {
        // Update existing token
        $update = "UPDATE qr_logins SET 
                client_id = ?, 
                authenticated = 1, 
                expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) 
                WHERE token = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("is", $numeric_id, $token);
    } else {
        // Insert new token - Match to your ACTUAL table structure
        $insert = "INSERT INTO qr_logins (
                token, client_id, authenticated, expires_at
                ) VALUES (
                ?, ?, 1, DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                )";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("si", $token, $numeric_id);
    }
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'QR login processed successfully',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'solde' => $user['solde']
        ]
    ]);
    
    // Log successful login
    file_put_contents($log_file, 
        date('Y-m-d H:i:s') . " - SUCCESS: QR login processed for user {$user['name']} (ID: {$user['id']})\n", 
        FILE_APPEND);
} catch (Exception $e) {
    file_put_contents($log_file, 
        date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", 
        FILE_APPEND);
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error processing QR login: ' . $e->getMessage()
    ]);
}

$conn->close();
?>