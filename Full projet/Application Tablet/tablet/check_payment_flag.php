<?php
session_start();
header('Content-Type: application/json');

$response = ['payment_completed' => false];

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode($response);
    exit;
}

// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['payment_completed' => false, 'error' => 'DB connection failed']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Check if there are any pending payment flags for this user
$flag_query = "SELECT id, user_id, amount, new_balance, timestamp FROM payment_flags 
               WHERE user_id = ? AND status = 'pending'
               AND timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
               LIMIT 1";
               
$stmt = $conn->prepare($flag_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $payment_data = $result->fetch_assoc();
    
    // Mark flag as processed
    $update_flag = $conn->prepare("UPDATE payment_flags SET status = 'processed' WHERE id = ?");
    $update_flag->bind_param("i", $payment_data['id']);
    $update_flag->execute();
    
    // Update session with new balance
    $_SESSION['user_solde'] = $payment_data['new_balance'];
    
    // Clear cart in session
    $_SESSION['cart'] = [];
    
    // Set notification
    $_SESSION['notification'] = 'Paiement effectué avec succès via l\'application mobile!';
    $_SESSION['notification_type'] = 'success';
    
    // Return success
    $response = [
        'payment_completed' => true,
        'new_balance' => $payment_data['new_balance']
    ];
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>