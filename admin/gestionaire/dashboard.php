<?php
require 'db.php';

// Gestion des filtres
$filtreNom = $_GET['nom'] ?? '';
$filtreQuantite = $_GET['quantite'] ?? '';
$filtreCategorie = $_GET['categorie'] ?? '';

// Requête SQL dynamique
$sql = "SELECT * FROM produits WHERE 1=1";
$params = [];

if (!empty($filtreNom)) {
    $sql .= " AND nom LIKE ?";
    $params[] = "%$filtreNom%";
}
if (!empty($filtreQuantite)) {
    $sql .= " AND quantite <= ?";
    $params[] = $filtreQuantite;
}
if (!empty($filtreCategorie)) {
    $sql .= " AND categorie = ?";
    $params[] = $filtreCategorie;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

// Stats globales
$totalProduits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$stockCritique = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite <= 5")->fetchColumn();

// Obtenir l'heure et les minutes actuelles
$heureMinute = date('H:i');

// Chemin vers ton logo (à remplacer !)
$logoPath = 'chemin/vers/ton/logo.png';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Gestionnaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            display: flex; /* Pour la mise en page globale avec la sidebar */
        }

        .sidebar {
            background-color:rgb(36, 105, 174);
            color: white;
            padding: 1rem;
            width: 230px;
            flex-shrink: 0; /* Empêche la sidebar de rétrécir */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100vh;
            position: fixed; /* Pour rester visible lors du défilement */
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
            margin-left: 230px; /* Pour laisser de l'espace à la sidebar */
            padding: 2rem;
            flex-grow: 1; /* Permet au contenu principal de s'étendre */
        }

        .top-bar {
    background-color: #e9ecef;
    padding: 1rem 1.5rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between; /* Espacer les groupes d'éléments */
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
    color: #007bff; /* Couleur primaire pour les icônes */
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
            color:rgba(2, 9, 17, 0.77);
        }

        .card {
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-title {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .form-control, .form-select {
            border-radius: 0.375rem;
            border: 1px solid #ced4da;
            padding: 0.75rem;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            border-radius: 0.375rem;
            padding: 0.75rem 1.5rem;
            transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .btn-primary:hover {
            background-color:rgb(20, 65, 118);
            border-color:rgb(19, 70, 133);
        }

        .table {
            background-color: #fff;
            border-collapse: collapse;
            width: 100%;
        }

        .table th, .table td {
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

        .table img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 0.25rem;
        }

        .table-danger td {
            background-color: #ffe3e3;
        }

        /* Style pour le logo */
        .logo {
            max-height: 2.5rem; /* Ajuste la taille selon tes besoins */
            margin-right: 1rem;
        }
    </style>
</head>
<body>

<div class="d-flex">
    <div class="sidebar">
        <div>
            <h4 class="mb-3">
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" class="logo">
            </h4>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="fas fa-home me-2"></i> Tableau de bord</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="fas fa-box me-2"></i> Stock</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="ajouterproduit.php"><i class="fas fa-plus me-2"></i> Ajouter produit</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="fas fa-bell me-2"></i> Alertes</a>
                </li>
            </ul>
        </div>
        <div>
            <hr class="bg-light">
            <a class="nav-link" href="../logout.php"><i class="fas fa-lock me-2"></i> Déconnexion</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="top-bar-left">
                <span class="me-2"><i class="fas fa-calendar-alt top-bar-icon"></i> <?= date('d/m/Y') ?></span>
                <span class="top-bar-separator"></span>
                <span><i class="far fa-clock top-bar-icon"></i> <?= $heureMinute ?></span>
            </div>
            <div class="top-bar-right">
                <i class="fas fa-chart-line top-bar-icon"></i>
                <span class="top-bar-separator"></span>
                <i class="fas fa-bell top-bar-icon"></i>
            </div>
        </div>

        <nav class="navbar bg-light shadow-sm mb-4 rounded">
            <h5 class="mb-0"><i class="fas fa-handshake me-2"></i> Bienvenue, Gestionnaire</h5>
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
            <div class="col-md-6">
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-search me-2"></i> Filtrer les produits</h5>
                <form method="GET" class="row g-3 mt-2">
                    <div class="col-md-4">
                        <input type="text" name="nom" class="form-control" placeholder="Nom du produit" value="<?= htmlspecialchars($filtreNom) ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="quantite" class="form-control" placeholder="Quantité maximal" value="<?= htmlspecialchars($filtreQuantite) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="categorie" class="form-select">
                            <option value="">Toutes catégories</option>
                            <option value="boissons" <?= $filtreCategorie == 'boissons' ? 'selected' : '' ?>>Boissons</option>
                            <option value="épicerie" <?= $filtreCategorie == 'épicerie' ? 'selected' : '' ?>>Épicerie</option>
                            <option value="hygiène" <?= $filtreCategorie == 'hygiène' ? 'selected' : '' ?>>Hygiène</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-box me-2"></i> Liste des Produits</h5>
                <table class="table table-hover mt-3">
                    <thead class="table-light">
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Prix</th>
                            <th>Quantité</th>
                            <th>Catégorie</th>
                            <th>UID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $produit): ?>
                            <tr class="<?= $produit['quantite'] <= 5 ? 'table-danger' : '' ?>">
                                <td><img src="<?= htmlspecialchars($produit['image']) ?>" alt="image produit" class="img-thumbnail"></td>
                                <td><?= htmlspecialchars($produit['nom']) ?></td>
                                <td><?= htmlspecialchars(number_format($produit['prix'], 2)) ?> DH</td>
                                <td><?= htmlspecialchars($produit['quantite']) ?></td>
                                <td><?= htmlspecialchars($produit['categorie']) ?></td>
                                <td><?= htmlspecialchars($produit['uid_codebar']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

</body>
</html>