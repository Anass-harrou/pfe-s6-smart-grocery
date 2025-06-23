<?php
// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Connect to database
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get user ID
    if (!isset($_GET['user_id'])) {
        throw new Exception("User ID is required");
    }
    
    $user_id = intval($_GET['user_id']);

    // Get purchase history with product details
    $query = "
        SELECT 
            a.id_achat,
            a.date_achat,
            a.montant_total,
            (
                SELECT GROUP_CONCAT(
                    CONCAT(p.nom, ' (', ap.quantite, ')')
                    SEPARATOR ', '
                )
                FROM achat_produits ap
                JOIN produits p ON ap.id_produit = p.id
                WHERE ap.id_achat = a.id_achat
            ) AS products
        FROM 
            achats a
        WHERE 
            a.id_utilisateur = ?
        ORDER BY 
            a.date_achat DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Build response
    $response = array();
    while ($row = $result->fetch_assoc()) {
        $response[] = $row;
    }
    
    // Close connection
    $stmt->close();
    $conn->close();
    
    // Return response
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>