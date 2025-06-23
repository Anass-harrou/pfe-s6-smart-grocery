<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h1>Database Structure Fix</h1>";
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Create a generic product specifically for QR payments if it doesn't exist
        echo "<h2>Creating Generic QR Payment Product</h2>";
        
        // Check if the generic product already exists
        $result = $conn->query("SELECT id FROM produits WHERE nom = 'QR Payment' LIMIT 1");
        if ($result->num_rows == 0) {
            // Create the generic product
            $sql = "INSERT INTO produits (nom, prix, quantite, categorie, image, disponible, description) 
                    VALUES ('QR Payment', 0.00, 9999, 'System', '../uploads/qr_payment.png', 1, 'Generic product for QR payments')";
            if ($conn->query($sql)) {
                $qr_payment_id = $conn->insert_id;
                echo "<p style='color:green'>Created generic QR Payment product with ID: $qr_payment_id</p>";
            } else {
                throw new Exception("Failed to create generic product: " . $conn->error);
            }
        } else {
            $row = $result->fetch_assoc();
            $qr_payment_id = $row['id'];
            echo "<p style='color:blue'>Generic QR Payment product already exists with ID: $qr_payment_id</p>";
        }
        
        // 2. Fix any existing problematic records in achat_produits
        echo "<h2>Checking for Problematic Records</h2>";
        
        // Find records with invalid product IDs
        $invalid_records = $conn->query("
            SELECT ap.id_achat_produit, ap.id_achat, ap.id_produit
            FROM achat_produits ap
            LEFT JOIN produits p ON ap.id_produit = p.id
            WHERE p.id IS NULL
        ");
        
        if ($invalid_records->num_rows > 0) {
            echo "<p>Found {$invalid_records->num_rows} records with invalid product IDs. Fixing...</p>";
            
            while ($record = $invalid_records->fetch_assoc()) {
                $update_sql = "UPDATE achat_produits SET id_produit = ? WHERE id_achat_produit = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ii", $qr_payment_id, $record['id_achat_produit']);
                if ($stmt->execute()) {
                    echo "<p style='color:green'>Fixed record ID {$record['id_achat_produit']}, changed product ID from {$record['id_produit']} to $qr_payment_id</p>";
                } else {
                    throw new Exception("Failed to update record {$record['id_achat_produit']}: " . $stmt->error);
                }
                $stmt->close();
            }
        } else {
            echo "<p style='color:green'>No problematic records found in achat_produits table.</p>";
        }
        
        // 3. Create a function to handle QR payments safely
        echo "<h2>Creating Database Function for Safe QR Payments</h2>";
        
        // Drop function if it exists
        $conn->query("DROP FUNCTION IF EXISTS process_qr_payment");
        
        // Create function that ensures valid product ID is used
        $create_function_sql = "
        CREATE FUNCTION process_qr_payment(
            p_user_id INT, 
            p_amount DECIMAL(10,2), 
            p_transaction_id VARCHAR(100)
        ) RETURNS INT
        DETERMINISTIC
        BEGIN
            DECLARE v_achat_id INT;
            DECLARE v_product_id INT;
            DECLARE v_current_balance DECIMAL(10,2);
            DECLARE v_new_balance DECIMAL(10,2);
            
            -- Get a valid product ID for QR payments
            SELECT id INTO v_product_id FROM produits WHERE nom = 'QR Payment' LIMIT 1;
            
            -- If no QR Payment product, use any valid product
            IF v_product_id IS NULL THEN
                SELECT id INTO v_product_id FROM produits LIMIT 1;
            END IF;
            
            -- Get user's current balance
            SELECT solde INTO v_current_balance FROM client WHERE id = p_user_id;
            
            -- Calculate new balance
            SET v_new_balance = v_current_balance - p_amount;
            
            -- Create purchase record
            INSERT INTO achats (id_utilisateur, montant_total)
            VALUES (p_user_id, p_amount);
            
            SET v_achat_id = LAST_INSERT_ID();
            
            -- Create purchase item with valid product ID
            INSERT INTO achat_produits (id_achat, id_produit, quantite, prix_unitaire)
            VALUES (v_achat_id, v_product_id, 1, p_amount);
            
            -- Update user balance
            UPDATE client SET solde = v_new_balance WHERE id = p_user_id;
            
            -- Create transaction record
            INSERT INTO transactions (client_id, title, subtitle, amount, type)
            VALUES (p_user_id, CONCAT('Payment via QR: ', p_transaction_id), 'Mobile app payment via QR code.', p_amount, 'debit');
            
            -- Create payment flag
            INSERT INTO payment_flags (user_id, amount, new_balance, timestamp, status)
            VALUES (p_user_id, p_amount, v_new_balance, NOW(), 'pending');
            
            RETURN v_achat_id;
        END;
        ";
        
        if ($conn->multi_query($create_function_sql)) {
            // Need to clear results to continue
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            
            echo "<p style='color:green'>Successfully created database function for safe QR payments.</p>";
        } else {
            throw new Exception("Failed to create function: " . $conn->error);
        }
        
        // Commit all changes
        $conn->commit();
        echo "<p style='color:green'>All database structure fixes have been successfully applied.</p>";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "<div style='color:red; font-weight:bold;'>Transaction failed: " . $e->getMessage() . "</div>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div style='color:red; font-weight:bold;'>Error: " . $e->getMessage() . "</div>";
}
?>