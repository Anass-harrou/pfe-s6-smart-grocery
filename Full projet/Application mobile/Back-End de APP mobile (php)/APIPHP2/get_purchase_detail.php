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

    // Get purchase ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("Invalid purchase ID");
    }
    
    $purchase_id = intval($_GET['id']);

    // Get purchase details
    $purchase_query = "
        SELECT a.id_achat, a.id_utilisateur, a.date_achat, a.montant_total,
               c.name as customer_name, c.email as customer_email, c.phone as customer_phone
        FROM achats a
        JOIN client c ON a.id_utilisateur = c.id
        WHERE a.id_achat = ?
    ";
    
    $purchase_stmt = $conn->prepare($purchase_query);
    $purchase_stmt->bind_param("i", $purchase_id);
    $purchase_stmt->execute();
    $purchase_result = $purchase_stmt->get_result();
    
    if ($purchase_result->num_rows === 0) {
        throw new Exception("Purchase not found");
    }
    
    $purchase = $purchase_result->fetch_assoc();
    $purchase_stmt->close();
    
    // Get purchase items
    $items_query = "
        SELECT ap.id_achat_produit, ap.id_produit, ap.quantite, ap.prix_unitaire,
               p.nom as product_name, p.categorie as product_category
        FROM achat_produits ap
        JOIN produits p ON ap.id_produit = p.id
        WHERE ap.id_achat = ?
        ORDER BY p.nom ASC
    ";
    
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param("i", $purchase_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = array();
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
    
    $items_stmt->close();
    $conn->close();
    
    // Build response
    $response = array(
        "purchase" => $purchase,
        "items" => $items
    );
    
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