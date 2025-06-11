<?php
// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "stock_db");

// Vérifie la connexion
if ($conn->connect_error) {
    die("Connexion échouée: " . $conn->connect_error);
}

// Récupérer les produits pour le formulaire
$result = $conn->query("SELECT * FROM produits");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_produit = $_POST['id_produit'];
    $quantite = $_POST['quantite'];
    $date_peremption = $_POST['date_peremption'];
    $date_reception = date("Y-m-d");
    $qualite = isset($_POST['qualite']) ? 1 : 0;

    // 1. Enregistrer la réception
    $stmt = $conn->prepare("INSERT INTO receptions (id_produit, quantite_recue, date_reception, date_peremption, qualite_validee)
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $id_produit, $quantite, $date_reception, $date_peremption, $qualite);
    $stmt->execute();

    // 2. Mettre à jour le stock du produit
    $update = $conn->prepare("UPDATE produits SET quantite = quantite + ? WHERE id = ?");
    $update->bind_param("ii", $quantite, $id_produit);
    $update->execute();

    echo "<div class='alert alert-success'>Produit enregistré et stock mis à jour avec succès.</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
    
<body>
    <button class="btn btn-secondary" onclick="history.back()">⟵RETOUR</button>




<div class="container mt-5">
    <h2 class="mb-4">Réception de produits</h2>
    <form method="POST" class="p-4 border rounded shadow-sm bg-light">
        <div class="mb-3">
            <label for="id_produit" class="form-label">Produit :</label>
            <select name="id_produit" id="id_produit" class="form-select" required>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <option value="<?= $row['id'] ?>"><?= $row['nom'] ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="quantite" class="form-label">Quantité reçue :</label>
            <input type="number" name="quantite" id="quantite" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="date_peremption" class="form-label">Date de péremption :</label>
            <input type="date" name="date_peremption" id="date_peremption" class="form-control" required>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="qualite" id="qualite" class="form-check-input">
            <label class="form-check-label" for="qualite">Qualité validée</label>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
</div>
</body>
</html>