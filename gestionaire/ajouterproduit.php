<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = $_POST['nom'];
    $prix = $_POST['prix'];
    $quantite = $_POST['quantite'];
    $categorie = $_POST['categorie'];
    $uid = $_POST['uid'];
    $disponible = isset($_POST['disponible']) ? 1 : 0; // Get disponible from the form

    // Upload image
    $imagePath = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $targetDir = "../uploads/";
        $imagePath = $targetDir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $imagePath);
    }

    $stmt = $pdo->prepare("INSERT INTO produits (nom, prix, quantite, categorie, uid_codebar, image, disponible)
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nom, $prix, $quantite, $categorie, $uid, $imagePath, $disponible]);

    header("Location: dashboard.php");
    exit();
}
?>