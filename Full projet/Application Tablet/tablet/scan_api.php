<?php
// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

// Connect to database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Get JSON data or POST data
$input_data = file_get_contents('php://input');
$json_data = json_decode($input_data, true);

// If JSON parsing failed, try POST data
if (json_last_error() !== JSON_ERROR_NONE) {
    $json_data = $_POST;
}

// Log the request for debugging
file_put_contents('scan_requests.log', date('Y-m-d H:i:s') . ' - Input: ' . print_r($json_data, true) . "\n", FILE_APPEND);

// Check if required data exists
if (!isset($json_data['product_id']) || !isset($json_data['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: product_id and action are required'
    ]);
    exit;
}

// Sanitize and prepare data
$product_id = $conn->real_escape_string($json_data['product_id']);
$action = $conn->real_escape_string($json_data['action']);
$quantity = isset($json_data['quantity']) ? intval($json_data['quantity']) : 1;
$timestamp = date('Y-m-d H:i:s');

// Insert scan request into database
$sql = "INSERT INTO scan_requests (product_id, action, quantity, timestamp, status) 
        VALUES (?, ?, ?, ?, 'pending')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssis", $product_id, $action, $quantity, $timestamp);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Scan request saved successfully',
        'request_id' => $conn->insert_id,
        'timestamp' => $timestamp
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save scan request: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>