<?php
// Date: 2025-06-23 00:10:06
// User: Anass-harrou

header('Content-Type: application/json');
require_once 'db.php';

try {
    // Calculer la quantité moyenne
    $stmt = $pdo->query("SELECT AVG(quantite) as quantite_moyenne FROM produits");
    $quantite_moyenne = $stmt->fetch(PDO::FETCH_ASSOC)['quantite_moyenne'];
    
    // Top 5 produits par quantité
    $stmt = $pdo->query("SELECT id, nom, quantite FROM produits ORDER BY quantite DESC LIMIT 5");
    $top5 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Renvoyer les données au format JSON
    echo json_encode([
        'quantite_moyenne' => $quantite_moyenne,
        'top5' => $top5
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}