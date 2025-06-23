<?php
session_start();
header('Content-Type: application/json');

// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die(json_encode([
        'success' => false, 
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

$payment_completed = false;
$payment_data = null;

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Check for pending payment flags for this user
    $flag_query = "SELECT * FROM payment_flags WHERE user_id = ? AND status = 'pending' ORDER BY timestamp DESC LIMIT 1";
    $stmt = $conn->prepare($flag_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $flag = $result->fetch_assoc();
        $payment_completed = true;
        $payment_data = $flag;
        
        // Mark the flag as processed
        $update_flag = "UPDATE payment_flags SET status = 'processed' WHERE id = ?";
        $stmt = $conn->prepare($update_flag);
        $stmt->bind_param("i", $flag['id']);
        $stmt->execute();
        
        // Update session balance
        $_SESSION['user_solde'] = $flag['new_balance'];
        
        // Clear the user's cart
        $_SESSION['cart'] = [];
    }
    
    $stmt->close();
}

$conn->close();

// Return response
echo json_encode([
    'payment_completed' => $payment_completed,
    'payment_data' => $payment_data
]);
?>