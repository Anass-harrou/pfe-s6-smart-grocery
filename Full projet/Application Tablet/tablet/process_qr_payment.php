<?php
session_start(); // Start session to access $_SESSION variables

// Set CORS headers to allow requests from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: access");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Allow POST and OPTIONS for preflight requests
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle OPTIONS method for CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection details
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Ensure this matches your MySQL root password (often empty for XAMPP)
define('DB_NAME', 'gestion_stock');

// Establish database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check database connection
if ($conn->connect_error) {
    error_log("API Database Connection Error: " . $conn->connect_error);
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Erreur de connexion à la base de données.']);
    exit();
}

// Function to sanitize input
function sanitizeInput($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// --- Handle QR Payment Request from Android App ---
// This file *only* processes POST requests with 'qr_payment_request' set.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['qr_payment_request'])) {
    $response = ['status' => 'error', 'message' => 'Une erreur inconnue est survenue.'];

    // Sanitize input from Android app
    $product_id_from_qr = isset($_POST['product_id']) ? sanitizeInput($conn, $_POST['product_id']) : null;
    $amount_from_qr = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
    $user_id_from_app = isset($_POST['user_id']) ? sanitizeInput($conn, $_POST['user_id']) : null;

    // Check if user is logged in (session user ID exists)
    // The Android app sends user_id, which should correspond to client.id
    $isLoggedIn = isset($_SESSION['user_id']);
    if (!$isLoggedIn) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Utilisateur non connecté ou session expirée. Veuillez vous connecter.';
        echo json_encode($response);
        $conn->close();
        exit();
    }
    
    // IMPORTANT: Security Check - The user ID from the app MUST match the session user ID.
    // This prevents one user from attempting to pay using another logged-in user's session.
    if ($user_id_from_app !== (string)$_SESSION['user_id']) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Erreur de sécurité: ID utilisateur invalide ou non autorisé.';
        error_log("Security Alert: User ID mismatch during QR payment. Session ID: {$_SESSION['user_id']}, App ID: {$user_id_from_app}");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // Recalculate total amount from the server-side cart session for accuracy and security
    $server_side_total_amount = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $server_side_total_amount += $item['prix'] * $item['quantity'];
        }
    }

    // Compare amount from QR code with server-side cart total (allow for minor float discrepancies)
    // The QR amount is a reference, but the server-side cart total is authoritative for payment.
    if (abs($amount_from_qr - $server_side_total_amount) > 0.01) { // 0.01 tolerance for float comparison
        http_response_code(422); // Unprocessable Entity
        $response['message'] = 'Le montant du panier a changé ou ne correspond pas. Veuillez rafraîchir la page du panier.';
        error_log("QR Payment Mismatch: QR Amount {$amount_from_qr} vs Server Cart Total {$server_side_total_amount} for user ID {$user_id_from_app}");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // Use the server-side calculated total for the actual payment transaction
    $amount_to_deduct = $server_side_total_amount;

    // Ensure cart is not empty before proceeding
    if (empty($_SESSION['cart'])) {
        http_response_code(422); // Unprocessable Entity
        $response['message'] = 'Votre panier est vide. Impossible de procéder au paiement.';
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // Check stock for all items in the cart before initiating transaction
    $stockInsuffisant = false;
    $produitInsuffisant = '';
    foreach ($_SESSION['cart'] as $p_id => $item) {
        $id = intval($p_id);
        $qty = intval($item['quantity']);

        $sql_check_stock = "SELECT nom, quantite FROM produits WHERE id = ?";
        $stmt_check_stock = $conn->prepare($sql_check_stock);
        if (!$stmt_check_stock) {
            throw new Exception("Erreur de préparation de la requête de vérification de stock: " . $conn->error);
        }
        $stmt_check_stock->bind_param("i", $id);
        $stmt_check_stock->execute();
        $result_check_stock = $stmt_check_stock->get_result();
        
        if ($row_stock = $result_check_stock->fetch_assoc()) {
            $current_stock = intval($row_stock['quantite']);
            if ($qty > $current_stock) {
                $stockInsuffisant = true;
                $produitInsuffisant = $row_stock['nom'];
                break;
            }
        }
        $stmt_check_stock->close();
    }

    if ($stockInsuffisant) {
        http_response_code(422); // Unprocessable Entity
        $response['message'] = "Stock insuffisant pour le produit \"$produitInsuffisant\". Veuillez corriger votre panier.";
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // Check user balance
    $user_id = $_SESSION['user_id']; // Use the authenticated user ID from session
    $sql_solde = "SELECT solde FROM client WHERE id = ?";
    $stmt_solde = $conn->prepare($sql_solde);
    if (!$stmt_solde) {
        throw new Exception("Erreur de préparation de la requête de solde: " . $conn->error);
    }
    $stmt_solde->bind_param("i", $user_id);
    $stmt_solde->execute();
    $result_solde = $stmt_solde->get_result();

    if ($row_solde = $result_solde->fetch_assoc()) {
        $solde_client = floatval($row_solde['solde']);
    } else {
        http_response_code(500); // Internal Server Error
        $response['message'] = 'Erreur interne: Solde du client introuvable.';
        error_log("Error: User with ID $user_id not found in client table when checking balance during QR payment.");
        echo json_encode($response);
        $conn->close();
        exit();
    }
    $stmt_solde->close();

    $nouveau_solde = $solde_client - $amount_to_deduct;

    if ($nouveau_solde < 0) {
        http_response_code(422); // Unprocessable Entity
        $response['message'] = 'Solde insuffisant. Veuillez recharger votre compte.';
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // --- Perform Transaction (Database Operations) ---
    // Use transactions for atomicity (all or nothing)
    $conn->begin_transaction();
    try {
        // 1. Update product quantities
        foreach ($_SESSION['cart'] as $p_id => $item) {
            $id = intval($p_id);
            $qty = intval($item['quantity']);

            // Get current stock
            $sql_get_stock = "SELECT quantite FROM produits WHERE id = ?";
            $stmt_get_stock = $conn->prepare($sql_get_stock);
            if (!$stmt_get_stock) {
                throw new Exception("Erreur de préparation (stock update get): " . $conn->error);
            }
            $stmt_get_stock->bind_param("i", $id);
            $stmt_get_stock->execute();
            $result_get_stock = $stmt_get_stock->get_result();
            
            if ($row_get_stock = $result_get_stock->fetch_assoc()) {
                $current_stock = intval($row_get_stock['quantite']);
                $new_stock = max(0, $current_stock - $qty); // Ensure stock doesn't go negative

                // Update product stock
                $update_sql = "UPDATE produits SET quantite = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                if (!$update_stmt) {
                    throw new Exception("Erreur de préparation (stock update): " . $conn->error);
                }
                $update_stmt->bind_param("ii", $new_stock, $id);
                $update_stmt->execute();
                $update_stmt->close();

                // If stock reaches 0, mark product as unavailable
                if ($new_stock == 0) {
                    $disable_sql = "UPDATE produits SET disponible = 0 WHERE id = ?";
                    $disable_stmt = $conn->prepare($disable_sql);
                    if (!$disable_stmt) {
                        throw new Exception("Erreur de préparation (disable product): " . $conn->error);
                    }
                    $disable_stmt->bind_param("i", $id);
                    $disable_stmt->execute();
                    $disable_stmt->close();
                }
            }
            $stmt_get_stock->close(); // Close statement for each iteration
        }

        // 2. Insert into 'achats' table (main purchase record)
        $insert_achat_sql = "INSERT INTO achats (id_utilisateur, montant_total) VALUES (?, ?)";
        $insert_achat_stmt = $conn->prepare($insert_achat_sql);
        if (!$insert_achat_stmt) {
            throw new Exception("Erreur de préparation (insert achats): " . $conn->error);
        }
        $insert_achat_stmt->bind_param("id", $user_id, $amount_to_deduct);
        $insert_achat_stmt->execute();
        $achat_id = $conn->insert_id; // Get the ID of the newly inserted purchase
        $insert_achat_stmt->close();

        // 3. Insert into 'achat_produits' table (linking products to the purchase)
        foreach ($_SESSION['cart'] as $p_id => $item) {
            $product_id = intval($p_id);
            $quantity = intval($item['quantity']);
            $prix = floatval($item['prix']); // Price at the time of purchase

            $insert_achat_produit_sql = "INSERT INTO achat_produits (id_achat, id_produit, quantite, prix_unitaire) VALUES (?, ?, ?, ?)";
            $insert_achat_produit_stmt = $conn->prepare($insert_achat_produit_sql);
            if (!$insert_achat_produit_stmt) {
                throw new Exception("Erreur de préparation (insert achat_produits): " . $conn->error);
            }
            $insert_achat_produit_stmt->bind_param("iiid", $achat_id, $product_id, $quantity, $prix);
            $insert_achat_produit_stmt->execute();
            $insert_achat_produit_stmt->close();
        }

        // 4. Add a new transaction record to the 'transactions' table
        // Based on your 'gestion_stock (4).sql', the `transactions` table has 'title', 'subtitle', 'amount', 'type'.
        // It does NOT have a 'description' column. We will use 'title' and 'subtitle'.
        $transaction_title = 'Paiement QR';
        $transaction_subtitle = 'Achat de ' . count($_SESSION['cart']) . ' article(s) du panier.';
        $transaction_type_enum = 'debit'; // Use 'debit' for payments as per your schema

        $insert_transaction_sql = "INSERT INTO transactions (client_id, title, subtitle, amount, type) VALUES (?, ?, ?, ?, ?)";
        $insert_transaction_stmt = $conn->prepare($insert_transaction_sql);
        if (!$insert_transaction_stmt) {
            throw new Exception("Erreur de préparation (insert transactions): " . $conn->error);
        }
        // Bind parameters: i=integer (client_id), s=string (title), s=string (subtitle), d=double (amount), s=string (type_enum)
        $insert_transaction_stmt->bind_param("issds", $user_id, $transaction_title, $transaction_subtitle, $amount_to_deduct, $transaction_type_enum);
        $insert_transaction_stmt->execute();
        $insert_transaction_stmt->close();


        // 5. Update client balance in the 'client' table
        $update_solde_sql = "UPDATE client SET solde = ? WHERE id = ?";
        $update_solde_stmt = $conn->prepare($update_solde_sql);
        if (!$update_solde_stmt) {
            throw new Exception("Erreur de préparation (update solde): " . $conn->error);
        }
        $update_solde_stmt->bind_param("di", $nouveau_solde, $user_id);
        $update_solde_stmt->execute();
        $update_solde_stmt->close();

        // Update session balance after successful transaction
        $_SESSION['user_solde'] = $nouveau_solde;

        $conn->commit(); // Commit transaction to make all changes permanent

        unset($_SESSION['cart']); // Clear cart after successful payment

        // Set session notification for the web page to pick up via AJAX polling
        $_SESSION['notification'] = 'Paiement effectué avec succès, stock mis à jour, historique enregistré et solde débité.';
        $_SESSION['notification_type'] = 'success';

        http_response_code(200); // OK
        $response['status'] = 'success';
        $response['message'] = 'Paiement effectué avec succès.'; // Simpler message for Android toast

    } catch (Exception $e) {
        $conn->rollback(); // Rollback all changes if any error occurs
        http_response_code(500); // Internal Server Error
        $response['message'] = "Erreur interne lors du traitement du paiement: " . $e->getMessage();
        error_log("QR Payment Transaction Error: " . $e->getMessage());
    }

    echo json_encode($response);
    $conn->close(); // Close connection before exiting
    exit(); // Terminate script after handling AJAX request
} else {
    // If it's not a POST request with qr_payment_request, respond with Method Not Allowed or Bad Request
    http_response_code(405); // Method Not Allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée. Seules les requêtes POST sont acceptées.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Requête invalide. Paramètre "qr_payment_request" manquant.']);
    }
    $conn->close();
    exit();
}

// IMPORTANT: It's best practice to omit the closing ?>
