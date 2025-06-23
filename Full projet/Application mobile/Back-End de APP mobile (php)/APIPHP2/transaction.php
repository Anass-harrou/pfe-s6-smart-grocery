<?php
// Set headers for cross-origin requests and JSON response
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Database connection parameters
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

// Current timestamp and user (for audit purposes)
$current_timestamp = "2025-06-18 16:12:42"; // YYYY-MM-DD HH:MM:SS format
$current_user = "Anass-harrou";

// Create connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error,
        'timestamp' => $current_timestamp,
        'user' => $current_user
    ]));
}

// Get client ID from request
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Log API access attempt
$log_message = "API accessed at $current_timestamp by $current_user - Client ID: $client_id";
error_log($log_message);

// Validate input
if ($client_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid client ID',
        'client_input_id' => $client_id,
        'timestamp' => $current_timestamp,
        'user' => $current_user
    ]);
    $conn->close();
    exit;
}

// Initialize transactions array
$transactions = [];

// First, get transactions from the transactions table (if it exists)
$transactions_check = $conn->query("SHOW TABLES LIKE 'transactions'");
if ($transactions_check->num_rows > 0) {
    $transactions_query = "SELECT 
                            id, 
                            title, 
                            subtitle, 
                            amount, 
                            type, 
                            created_at as date 
                          FROM transactions 
                          WHERE client_id = ?
                          ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($transactions_query);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'subtitle' => $row['subtitle'],
            'amount' => (float)$row['amount'],
            'type' => $row['type'],
            'date' => $row['date'],
            'source' => 'transactions'
        ];
    }
    $stmt->close();
}

// Now, get transactions from the achats table
$achats_query = "SELECT 
                  a.id_achat as id, 
                  'Purchase' as title, 
                  CONCAT('Purchase #', a.id_achat) as subtitle, 
                  a.montant_total as amount, 
                  'debit' as type, 
                  a.date_achat as date 
                FROM achats a 
                WHERE a.id_utilisateur = ?
                ORDER BY a.date_achat DESC";

$stmt = $conn->prepare($achats_query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get purchase details (number of items)
    $items_query = "SELECT SUM(quantite) as total_items 
                   FROM achat_produits 
                   WHERE id_achat = ?";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param("i", $row['id']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $items_data = $items_result->fetch_assoc();
    $total_items = $items_data ? $items_data['total_items'] : 0;
    $items_stmt->close();
    
    // Create a more descriptive subtitle
    $subtitle = "Purchase #" . $row['id'] . " - " . $total_items . " items";
    
    $transactions[] = [
        'id' => $row['id'],
        'title' => 'Grocery Purchase',
        'subtitle' => $subtitle,
        'amount' => (float)$row['amount'],
        'type' => 'debit',
        'date' => $row['date'],
        'source' => 'achats'
    ];
}
$stmt->close();

// Sort all transactions by date (newest first)
usort($transactions, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Return the response
echo json_encode([
    'success' => true,
    'message' => 'Transactions retrieved successfully',
    'transactions' => $transactions,
    'count' => count($transactions),
    'client_id' => $client_id,
    'timestamp' => $current_timestamp,
    'user' => $current_user
]);

// Close the database connection
$conn->close();
?>