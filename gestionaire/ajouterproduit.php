<?php
session_start();
require 'db.php'; // Assure-toi que ce fichier contient ta connexion à la base de données

// Vérification si l'utilisateur est connecté et a le rôle de gestionnaire
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gestionaire') {
    header('Location: ../login.php');
    exit();
}

$erreurAjout = null; // Initialisation de la variable d'erreur

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $prix = $_POST['prix'] ?? '';
    $quantite = $_POST['quantite'] ?? '';
    $categorie = $_POST['categorie'] ?? '';
    $uid = $_POST['uid'] ?? '';

    if (!empty($nom) && !empty($prix) && is_numeric($prix) && is_numeric($quantite) && !empty($categorie) && !empty($uid)) {
        try {
            $sql = "INSERT INTO produits (nom, prix, quantite, categorie, uid_codebar) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom, $prix, $quantite, $categorie, $uid]);

            // Rediriger vers dashboard1.php après l'ajout réussi
            header('Location: dashboard.php');
            exit();

        } catch (PDOException $e) {
            $erreurAjout = 'Erreur lors de l\'ajout du produit : ' . $e->getMessage();
        }
    } else {
        $erreurAjout = 'Veuillez remplir tous les champs correctement.';
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un Produit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
            max-width: 700px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    
<button class="btn btn-secondary" onclick="history.back()">⟵RETOUR</button>

<div class="container">
    <div class="card p-4">
        <h3 class="mb-4 text-center">Ajouter un nouveau produit</h3>
        <?php if ($erreurAjout): ?>
            <div class="alert alert-danger"><?= $erreurAjout ?></div>
        <?php endif; ?>
        <form action="ajouterproduit.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nom" class="form-label">Nom du produit</label>
                <input type="text" class="form-control" name="nom" id="nom" required>
            </div>
            <div class="mb-3">
                <label for="prix" class="form-label">Prix</label>
                <input type="number" class="form-control" name="prix" step="0.01" id="prix" required>
            </div>
            <div class="mb-3">
                <label for="quantite" class="form-label">Quantité</label>
                <input type="number" class="form-control" name="quantite" id="quantite" required>
            </div>
            <div class="mb-3">
                <label for="categorie" class="form-label">Catégorie</label>
                <input type="text" class="form-control" name="categorie" id="categorie" required>
            </div>
            <div class="mb-3">
                <label for="uid" class="form-label">UID codebar</label>
                <input type="text" class="form-control" name="uid" id="uid" required>
            </div>
            <div class="mb-3">
                <label for="image" class="form-label">Image</label>
                <input type="file" class="form-control" name="image" id="image">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="disponible" name="disponible" value="1">
                <label class="form-check-label" for="disponible">Disponible</label>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Ajouter le produit</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>