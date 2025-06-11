<?php
// Connexion PDO
$conn = new PDO("mysql:host=localhost;dbname=stock_db", "root", "");

// 4. Quantité moyenne en stock
$stmt1 = $conn->query("SELECT AVG(quantite) AS quantite_moyenne FROM produits");
$quantite_moyenne = $stmt1->fetch(PDO::FETCH_ASSOC)['quantite_moyenne'];

// 5. Top 5 produits avec le plus grand stock disponible
$stmt2 = $conn->query("SELECT nom, quantite FROM produits ORDER BY quantite DESC LIMIT 5");
$top5 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Renvoyer tout en JSON
header('Content-Type: application/json');
echo json_encode([
    'quantite_moyenne' => $quantite_moyenne,
    'top5' => $top5
]);
?>
