<?php
// Include config file
require_once "../config.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Historique des Achats</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .wrapper{
            width: 800px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="mt-5 mb-3 clearfix">
                        <h2 class="pull-left">Historique des Achats</h2>
                    </div>
                    <?php
                    // Attempt select query execution for achats
                    $sql_achats = "SELECT achats.id_achat AS id_achat, client.name AS nom_utilisateur, achats.montant_total, achats.date_achat
                                    FROM achats
                                    INNER JOIN client ON achats.id_utilisateur = client.id
                                    ORDER BY achats.date_achat DESC"; // Tri par date d'achat la plus récente
                    if($result_achats = mysqli_query($link, $sql_achats)){
                        if(mysqli_num_rows($result_achats) > 0){
                            echo '<table class="table table-bordered table-striped">';
                                echo "<thead>";
                                    echo "<tr>";
                                        echo "<th># Achat</th>";
                                        echo "<th>Nom Utilisateur</th>";
                                        echo "<th>Montant Total</th>";
                                        echo "<th>Date d'Achat</th>";
                                        echo "<th>Produits Achetés</th>"; // Nouvelle colonne
                                    echo "</tr>";
                                echo "</thead>";
                                echo "<tbody>";
                                while($row_achat = mysqli_fetch_array($result_achats)){
                                    echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row_achat['id_achat']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row_achat['nom_utilisateur']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row_achat['montant_total']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row_achat['date_achat']) . "</td>";
                                        echo "<td>";
                                            // Récupérer les produits achetés pour cet achat
                                            $id_achat = $row_achat['id_achat'];
                                            $sql_produits = "SELECT produits.nom, achat_produits.quantite
                                                             FROM achat_produits
                                                             INNER JOIN produits ON achat_produits.id_produit = produits.id
                                                             WHERE achat_produits.id_achat = $id_achat";
                                            $result_produits = mysqli_query($link, $sql_produits);
                                            if($result_produits){
                                                if(mysqli_num_rows($result_produits) > 0){
                                                    echo "<ul>";
                                                    while($row_produit = mysqli_fetch_array($result_produits)){
                                                        echo "<li>" . htmlspecialchars($row_produit['nom']) . " (Quantité: " . htmlspecialchars($row_produit['quantite']) . ")</li>";
                                                    }
                                                    echo "</ul>";
                                                } else {
                                                    echo "Aucun produit trouvé pour cet achat.";
                                                }
                                            } else {
                                                echo "Erreur lors de la récupération des produits.";
                                            }
                                        echo "</td>";
                                    echo "</tr>";
                                }
                                echo "</tbody>";
                            echo "</table>";
                            // Free result set
                            mysqli_free_result($result_achats);
                        } else{
                            echo '<div class="alert alert-info"><em>Aucun achat trouvé.</em></div>';
                        }
                    } else{
                        echo "Oops! Une erreur s'est produite lors de la récupération des achats. Veuillez réessayer plus tard.";
                    }
                    ?>
                    <a class="navbar-brand" href="dashboard.php">liste des client</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>