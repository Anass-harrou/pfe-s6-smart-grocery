<?php
// Database connection parameters
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

// Audit information
$current_timestamp = "2025-06-19 15:53:13"; // UTC
$current_user = "Anass-harrou";

// Create connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully. Updating database structure for authentication...<br>";

// Begin transaction
$conn->begin_transaction();

try {
    // Log the update operation
    $log_message = "Authentication system update initiated at $current_timestamp by $current_user";
    error_log($log_message);
    echo "$log_message<br>";

    // Check if id_utilisateur column exists in client table
    $column_check = $conn->query("SHOW COLUMNS FROM client LIKE 'id_utilisateur'");
    
    if ($column_check->num_rows > 0) {
        echo "Found 'id_utilisateur' column. Preparing for migration...<br>";
        
        // 1. Add authentication_token column if it doesn't exist
        $alter_table = "ALTER TABLE client 
                        ADD COLUMN authentication_token VARCHAR(255) NULL AFTER password,
                        ADD COLUMN token_expiry DATETIME NULL AFTER authentication_token,
                        ADD COLUMN last_login DATETIME NULL AFTER token_expiry,
                        ADD COLUMN last_login_ip VARCHAR(45) NULL AFTER last_login";
        
        if ($conn->query($alter_table)) {
            echo "Added authentication columns to client table.<br>";
        } else {
            throw new Exception("Error adding authentication columns: " . $conn->error);
        }
        
        // 2. Generate unique authentication tokens for existing users
        $users_query = "SELECT id FROM client";
        $users_result = $conn->query($users_query);
        
        if ($users_result->num_rows > 0) {
            echo "Generating authentication tokens for " . $users_result->num_rows . " users...<br>";
            
            while ($user = $users_result->fetch_assoc()) {
                $user_id = $user['id'];
                $auth_token = bin2hex(random_bytes(16)); // Generate a random token
                $token_expiry = date('Y-m-d H:i:s', strtotime('+30 days')); // Set expiry to 30 days
                
                $update_user = "UPDATE client 
                                SET authentication_token = ?, 
                                    token_expiry = ? 
                                WHERE id = ?";
                $stmt = $conn->prepare($update_user);
                $stmt->bind_param("ssi", $auth_token, $token_expiry, $user_id);
                
                if ($stmt->execute()) {
                    echo "Generated token for user ID: $user_id<br>";
                } else {
                    throw new Exception("Error generating token for user ID $user_id: " . $stmt->error);
                }
                $stmt->close();
            }
        } else {
            echo "No users found to update.<br>";
        }
        
        // 3. Create an index on authentication_token for faster lookups
        $create_index = "CREATE INDEX idx_auth_token ON client(authentication_token)";
        if ($conn->query($create_index)) {
            echo "Created index on authentication_token.<br>";
        } else {
            // Index might already exist
            echo "Note: " . $conn->error . "<br>";
        }
        
        // 4. Optionally modify the qr_logins table to reference client.id directly
        $check_qr_table = $conn->query("SHOW TABLES LIKE 'qr_logins'");
        if ($check_qr_table->num_rows > 0) {
            echo "Updating qr_logins table...<br>";
            
            // Rename user_id to client_id for clarity
            $alter_qr_table = "ALTER TABLE qr_logins 
                               CHANGE COLUMN user_id client_id INT NOT NULL,
                               ADD COLUMN auth_token VARCHAR(255) NULL AFTER client_id";
            
            if ($conn->query($alter_qr_table)) {
                echo "Updated qr_logins table structure.<br>";
            } else {
                // Column might already be renamed
                echo "Note: " . $conn->error . "<br>";
            }
        }
        
        echo "Authentication system update completed successfully.<br>";
    } else {
        echo "'id_utilisateur' column doesn't exist in client table. No changes needed.<br>";
    }
    
    // Commit transaction
    $conn->commit();
    echo "All changes committed successfully.<br>";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "<br>";
    echo "All changes have been rolled back.<br>";
}

// Close connection
$conn->close();
?>