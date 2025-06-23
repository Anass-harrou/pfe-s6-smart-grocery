<?php
// Date: 2025-06-23 01:30:24
// User: Anass-harrou

session_start();

// Vérifie que l'utilisateur est connecté et a le rôle de gestionnaire
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gestionaire') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once 'db.php';

// Vérifie si un ID a été passé
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid product ID']);
    exit();
}

$productId = (int)$_GET['id'];

// Récupère les détails du produit
try {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Product not found']);
        exit();
    }
    
    // Renvoie les données au format JSON
    header('Content-Type: application/json');
    echo json_encode($product);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}