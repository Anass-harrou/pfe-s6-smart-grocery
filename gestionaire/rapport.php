<?php
$conn = new mysqli("localhost", "root", "", "stock_db");
$result = $conn->query("SELECT * FROM produits");

$total = 0;
$faible_stock = 0;
$contenu = "";
$date = date("d/m/Y");

while ($row = $result->fetch_assoc()) {
    $total++;
    $nom = $row['nom'];
    $quantite = $row['quantite'];
    $date_peremption = $row['date_peremption'] ?? "non spécifiée";

    if ($quantite < 10) $faible_stock++;

    $contenu .= "- Le produit <strong>$nom</strong> est disponible en <strong>$quantite unités</strong>.";
    $contenu .= $date_peremption !== "non spécifiée" ? " Il est valide jusqu’au <strong>$date_peremption</strong>.<br>" : " Sa date de péremption est <strong>non spécifiée</strong>.<br>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport rédigé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">

    <div class="container">
        <h2 class="mb-4">📄 Rapport global du stock – <?= date("F Y", strtotime($date)) ?></h2>

        <p>À la date du <strong><?= $date ?></strong>, le stock contient actuellement <strong><?= $total ?></strong> produits enregistrés. Voici un aperçu général :</p>

        <p><?= $contenu ?></p>

        <p>
            Aucun produit n’est signalé comme expiré dans ce rapport.
            <?php if ($faible_stock > 0): ?>
                Toutefois, <strong><?= $faible_stock ?></strong> produit(s) ont une quantité inférieure à <strong>10 unités</strong>, ce qui nécessite un réapprovisionnement.
            <?php else: ?>
                Tous les produits sont actuellement bien approvisionnés.
            <?php endif; ?>
        </p>

        <p>Ce rapport a été généré automatiquement par le système de gestion de stock.</p>

        <button class="btn btn-secondary mt-3" onclick="window.print()">🖨️ Imprimer le rapport</button>
        <a href="dashboard.php" class="btn btn-outline-dark mt-3">⬅ Retour</a>
    </div>

</body>
</html>
