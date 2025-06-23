<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include config file
require_once "../config.php";

// Set response header
header('Content-Type: application/json');

// Check if purchase_id is set
if(!isset($_POST['purchase_id']) || !is_numeric($_POST['purchase_id'])) {
    echo json_encode(['error' => 'Invalid purchase ID']);
    exit();
}

$purchase_id = intval($_POST['purchase_id']);

// Prepare query to get products
$sql = "SELECT produits.nom, achat_produits.quantite, achat_produits.prix_unitaire
        FROM achat_produits
        INNER JOIN produits ON achat_produits.id_produit = produits.id
        WHERE achat_produits.id_achat = ?
        ORDER BY produits.nom ASC";

if($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $purchase_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $products = [];
    while($row = mysqli_fetch_assoc($result)) {
        $products[] = [
            'nom' => htmlspecialchars($row['nom']),
            'quantite' => intval($row['quantite']),
            'prix_unitaire' => floatval($row['prix_unitaire'])
        ];
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    
    echo json_encode($products);
} else {
    echo json_encode(['error' => 'Database error: ' . mysqli_error($link)]);
    mysqli_close($link);
}
?>