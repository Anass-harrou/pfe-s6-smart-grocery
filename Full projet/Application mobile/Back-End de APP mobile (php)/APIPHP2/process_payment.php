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
$log_file = 'payment_debug_' . date('Y-m-d') . '.log';
file_put_contents($log_file, 
    date('Y-m-d H:i:s') . ' - REQUEST: ' . $_SERVER['REQUEST_METHOD'] . "\n" .
    'POST: ' . json_encode($_POST) . "\n" .
    'RAW: ' . file_get_contents('php://input') . "\n\n", 
    FILE_APPEND);

try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Get data from either POST variables or JSON input
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // Check for POST data if JSON parsing failed
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    // Check if we have the required data
    if (empty($input)) {
        throw new Exception('No payment data received');
    }

    if (!isset($input['user_id'])) {
        throw new Exception('User ID is required');
    }

    if (!isset($input['amount'])) {
        throw new Exception('Amount is required');
    }

    // Get parameters
    $user_id = intval($input['user_id']);
    $amount = floatval($input['amount']);
    $transaction_id = $input['transaction_id'] ?? uniqid('txn_', true);
    
    // NEW: Get cart_data if provided
    $cart_data = null;
    if (isset($input['cart_data'])) {
        $cart_data = $input['cart_data'];
    } elseif (isset($input['cart'])) {
        $cart_data = $input['cart'];
    }

    file_put_contents($log_file, 
        date('Y-m-d H:i:s') . " - Processing payment for user ID: $user_id, Amount: $amount, Transaction: $transaction_id\n", 
        FILE_APPEND);

    // Check for existing transaction to prevent duplicates
    if (isset($input['transaction_id'])) {
        $check_transaction = $conn->prepare("SELECT id FROM transactions WHERE title LIKE CONCAT('%', ?, '%') LIMIT 1");
        $check_transaction->bind_param("s", $input['transaction_id']);
        $check_transaction->execute();
        $check_transaction->store_result();
        
        if ($check_transaction->num_rows > 0) {
            $check_transaction->close();
            throw new Exception('This payment has already been processed');
        }
        $check_transaction->close();
    }

    // First check if user exists
    $user_query = $conn->prepare("SELECT id, name, solde FROM client WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();

    if ($user_result->num_rows === 0) {
        $user_query->close();
        throw new Exception('User not found with ID: ' . $user_id);
    }

    $user = $user_result->fetch_assoc();
    $user_query->close();

    // Check if user has enough balance
    $current_balance = floatval($user['solde']);
    if ($current_balance < $amount) {
        throw new Exception('Insufficient balance. You need ' . number_format($amount, 2) . ' DH but have ' . number_format($current_balance, 2) . ' DH');
    }

    // Process payment
    $conn->begin_transaction();
    
    try {
        // 1. Update user balance
        $new_balance = $current_balance - $amount;
        $update_balance = $conn->prepare("UPDATE client SET solde = ? WHERE id = ?");
        $update_balance->bind_param("di", $new_balance, $user['id']);
        $update_balance->execute();
        $update_balance->close();

        // 2. Create transaction record
        $transaction_title = "Payment via QR: " . $transaction_id;
        $transaction_subtitle = "Mobile app payment via QR code.";
        
        $create_transaction = $conn->prepare("INSERT INTO transactions (client_id, title, subtitle, amount, type, transaction_date) VALUES (?, ?, ?, ?, 'debit', NOW())");
        $create_transaction->bind_param("issd", $user['id'], $transaction_title, $transaction_subtitle, $amount);
        $create_transaction->execute();
        $create_transaction->close();

        // 3. Create purchase record to track the purchase
        $insert_achat_sql = "INSERT INTO achats (id_utilisateur, montant_total) VALUES (?, ?)";
        $insert_achat_stmt = $conn->prepare($insert_achat_sql);
        $insert_achat_stmt->bind_param("id", $user['id'], $amount);
        $insert_achat_stmt->execute();
        $achat_id = $conn->insert_id;
        $insert_achat_stmt->close();
        
        // 4. Process cart items
        if ($cart_data && is_array($cart_data) && count($cart_data) > 0) {
            // Use the actual cart data to create purchase items
            foreach ($cart_data as $item) {
                $product_id = isset($item['id']) ? intval($item['id']) : 0;
                $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
                $price = isset($item['prix']) ? floatval($item['prix']) : ($amount / count($cart_data));
                
                // Verify product exists
                $check_product = $conn->prepare("SELECT id FROM produits WHERE id = ? LIMIT 1");
                $check_product->bind_param("i", $product_id);
                $check_product->execute();
                $check_product->store_result();
                
                if ($check_product->num_rows > 0) {
                    // Product exists, add it to the purchase
                    $insert_item = $conn->prepare("INSERT INTO achat_produits (id_achat, id_produit, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
                    $insert_item->bind_param("iiid", $achat_id, $product_id, $quantity, $price);
                    $insert_item->execute();
                    $insert_item->close();
                } else {
                    // If product doesn't exist, log it and use a default product
                    file_put_contents($log_file, 
                        date('Y-m-d H:i:s') . " - Warning: Product ID $product_id not found in database\n", 
                        FILE_APPEND);
                        
                    // Find a valid product ID
                    $default_product = $conn->query("SELECT id FROM produits LIMIT 1");
                    if ($default_row = $default_product->fetch_assoc()) {
                        $default_id = $default_row['id'];
                        $insert_item = $conn->prepare("INSERT INTO achat_produits (id_achat, id_produit, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
                        $insert_item->bind_param("iiid", $achat_id, $default_id, $quantity, $price);
                        $insert_item->execute();
                        $insert_item->close();
                    }
                }
                $check_product->close();
            }
        } else {
            // No cart data, use the best available product(s)
            
            // Try to find the Abtal product (ID 11) which is commonly used
            $abtal_id = 11;
            $check_abtal = $conn->prepare("SELECT id FROM produits WHERE id = ? LIMIT 1");
            $check_abtal->bind_param("i", $abtal_id);
            $check_abtal->execute();
            $check_abtal->store_result();
            
            if ($check_abtal->num_rows > 0) {
                // Use Abtal product
                $insert_item = $conn->prepare("INSERT INTO achat_produits (id_achat, id_produit, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
                $quantity = floor($amount / 2); // Calculate quantity based on standard price
                $quantity = $quantity > 0 ? $quantity : 1; // Ensure at least 1
                $unit_price = $quantity > 1 ? $amount / $quantity : $amount;
                $insert_item->bind_param("iiid", $achat_id, $abtal_id, $quantity, $unit_price);
                $insert_item->execute();
                $insert_item->close();
            } else {
                // Find any valid product
                $result = $conn->query("SELECT id, prix FROM produits LIMIT 1");
                if ($row = $result->fetch_assoc()) {
                    $product_id = $row['id'];
                    $product_price = floatval($row['prix']);
                    $quantity = $product_price > 0 ? floor($amount / $product_price) : 1;
                    $quantity = $quantity > 0 ? $quantity : 1;
                    $unit_price = $quantity > 1 ? $amount / $quantity : $amount;
                    
                    $insert_item = $conn->prepare("INSERT INTO achat_produits (id_achat, id_produit, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
                    $insert_item->bind_param("iiid", $achat_id, $product_id, $quantity, $unit_price);
                    $insert_item->execute();
                    $insert_item->close();
                } else {
                    throw new Exception("No valid products found in database");
                }
            }
            $check_abtal->close();
        }
        
        // 5. Add payment flag in database
        $flag_sql = "INSERT INTO payment_flags (user_id, amount, new_balance, timestamp, status) VALUES (?, ?, ?, NOW(), 'pending')";
        $flag_stmt = $conn->prepare($flag_sql);
        $flag_stmt->bind_param("idd", $user['id'], $amount, $new_balance);
        $flag_stmt->execute();
        $flag_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Return success
        echo json_encode([
            'success' => true, 
            'message' => 'Payment processed successfully',
            'new_balance' => number_format($new_balance, 2),
            'transaction_id' => $transaction_id
        ]);
        
        file_put_contents($log_file, 
            date('Y-m-d H:i:s') . " - Payment successful: User ID {$user['id']}, Amount $amount, New balance $new_balance\n", 
            FILE_APPEND);
            
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' - ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Error processing payment: ' . $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>