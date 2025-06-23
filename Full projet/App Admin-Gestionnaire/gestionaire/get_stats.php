<?php
// Date: 2025-06-23 01:15:33
// User: Anass-harrou

header('Content-Type: application/json');
require_once 'db.php';

try {
    // Calculate average quantity
    $stmt = $pdo->query("SELECT AVG(quantite) as quantite_moyenne FROM produits");
    $quantite_moyenne = $stmt->fetch(PDO::FETCH_ASSOC)['quantite_moyenne'];
    
    // Top 5 products by quantity
    $stmt = $pdo->query("SELECT id, nom, quantite FROM produits ORDER BY quantite DESC LIMIT 5");
    $top5 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON data
    echo json_encode([
        'quantite_moyenne' => $quantite_moyenne,
        'top5' => $top5
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>