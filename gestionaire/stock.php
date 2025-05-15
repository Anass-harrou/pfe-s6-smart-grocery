<?php
session_start();


// Vérifie que l'utilisateur est connecté et a le rôle de gestionnaire
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gestionaire') {
    header('Location: ../login.php');
    exit();
}
require_once 'db.php';



// Récupération des produits
$produits = $pdo->query("SELECT * FROM produits ORDER BY id DESC")->fetchAll();

// Statistiques
$totalProduits = count($produits);
$stockCritique = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite <= 5")->fetchColumn();

// Heure actuelle
$heureMinute = date('H:i');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Stock Produits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            color: #333;
        }

        .sidebar {
            background-color: rgb(36, 105, 174);
            color: white;
            padding: 1rem;
            width: 230px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100vh;
            position: fixed;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            margin: 0.5rem 0;
            font-weight: 500;
            display: block;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: 230px;
            padding: 2rem;
            flex-grow: 1;
        }

        .top-bar {
            background-color: #e9ecef;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.1rem;
            color: #6c757d;
            font-weight: 500;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .top-bar-left {
            display: flex;
            align-items: center;
        }

        .top-bar-separator {
            height: 1.5rem;
            border-left: 1px solid #ced4da;
            margin: 0 1rem;
        }

        .top-bar-icon {
            margin-right: 0.5rem;
            color: #007bff;
        }

        .table {
            background-color: #fff;
            border-collapse: collapse;
            width: 100%;
        }

       
        .table td {
            padding: 0.75rem;
            border-bottom: 1px solid #dee2e6;
            text-align: left;
        }

        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table tbody tr:hover {
            background-color: #f0f0f0;
        }

        .table-danger td {
            background-color: #ffe3e3;
        }

        .card-stat {
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            text-align: center;
            padding: 1.5rem;
        }

        .card-stat h4 {
            font-size: 2rem;
            color: rgba(2, 9, 17, 0.77);
        }
        .main-content{
            margin-left: 2px;
        }
    </style>
</head>

<body>
    
    
    <div class="main-content">
        <div class="top-bar">
            <div class="top-bar-left">
                <span><i class="fas fa-calendar-alt top-bar-icon"></i> <?= date('d/m/Y') ?></span>
                <span class="top-bar-separator"></span>
                <span><i class="far fa-clock top-bar-icon"></i> <?= $heureMinute ?></span>
            </div>
        </div>

        <nav class="navbar bg-light shadow-sm mb-4 rounded">
            <h5 class="mb-0"><i class="fas fa-box me-2"></i> Stock des Produits</h5>
        </nav>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card-stat">
                    <small class="text-muted">Total Produits</small>
                    <h4><?= $totalProduits ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-stat bg-danger text-white">
                    <small>Stock Critique</small>
                    <h4><?= $stockCritique ?></h4>
                </div>
            </div>
        </div>
<?php
        // Sort products: low quantity first
usort($produits, function ($a, $b) {
    $aPriority = $a['quantite'] <= 5 ? 0 : 1;
    $bPriority = $b['quantite'] <= 5 ? 0 : 1;
    return $aPriority <=> $bPriority;
});
?>

<table class="table table-bordered shadow-sm">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Prix</th>
            <th>Quantité</th>
            <th>Catégorie</th>
            <th>UID</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($produits as $produit): ?>
            <tr class="<?= $produit['quantite'] <= 5 ? 'table-danger' : '' ?>">
                <td><?= htmlspecialchars($produit['id']) ?></td>
                <td><?= htmlspecialchars($produit['nom']) ?></td>
                <td><?= htmlspecialchars($produit['prix']) ?> MAD</td>
                <td><?= htmlspecialchars($produit['quantite']) ?></td>
                <td><?= htmlspecialchars($produit['categorie']) ?></td>
                <td><?= htmlspecialchars($produit['uid_codebar']) ?></td>
                <td>
                    <a href="update.php?id=<?= $produit['id'] ?>" class="mr-3" title="Update Record" data-toggle="tooltip">
                        <span class="fa fa-pencil"></span>
                    </a><br>
                    <a href="delete.php?id=<?= $produit['id'] ?>" title="Delete Record" data-toggle="tooltip">
                        <span class="fa fa-trash"></span>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
    </div>
</body>
</html>
