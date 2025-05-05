<?php
session_start();

// Vérification si l'utilisateur est connecté et a le rôle de gestionnaire
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gestionaire') {
    // Non autorisé, redirection vers la page de connexion
    header('Location: ../login.php');
    exit();
}

require 'db.php';

// --- Traitement du filtre de produits ---
$filtreNom = $_GET['nom'] ?? '';
$filtreQuantite = $_GET['quantite'] ?? '';
$filtreCategorie = $_GET['categorie'] ?? '';
$filtreDisponible = $_GET['disponible'] ?? ''; // Get disponible filter

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

// Add disponible filter
if ($filtreDisponible !== '') {
    $sql .= " AND disponible = ?";
    $params[] = $filtreDisponible;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

// --- Récupération du nombre de produits en stock critique ---
$alerteStockCritique = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite <= 5")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Gestionnaire</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            height: 100vh;
            background-color:rgb(121, 161, 219);
            color: white;
            padding: 1rem;
        }
        
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            margin: 1rem 0;
        }
        .card-stat {
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .table img {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar">
            <h4> Panier Intelligent</h4>
            <a href="#">Tableau de bord</a>
            <a href="#"> Stock</a>
            <a href="#">Ajouter produit</a>
            <a href="#">Alertes</a>
            <a href="logout.php">🔒 Déconnexion</a>
        </div>
<!-- zyada li zedta  -->
        <div class="container-fluid p-4">
            <nav class="navbar navbar-light bg-white shadow-sm mb-4">
                <span class="navbar-brand mb-0 h1">Bienvenue, Gestionnaire</span>
            </nav>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card card-stat p-3 text-center">
                        <h6>Total Produits</h6>
                        <h4><?= count($produits) ?></h4> </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-stat p-3 text-center text-danger">
                        <h6>Stock Critique</h6>
                        <h4><?= $alerteStockCritique ?></h4>
                    </div>
                </div>
                </div> 
            <div class="card mb-4 p-4">
                <h5>Ajouter un produit</h5>
                <form action="ajouterproduit.php" method="POST" enctype="multipart/form-data" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="nom" class="form-control" placeholder="Nom du produit" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="prix" step="0.01" class="form-control" placeholder="Prix" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="quantite" class="form-control" placeholder="Quantité" required>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="categorie" class="form-control" placeholder="Catégorie" required>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="uid" class="form-control" placeholder="UID codebar" required>
                    </div>
                    <div class="col-md-4">
                        <input type="file" name="image" class="form-control">
                    </div>
                    <div class="col-md-2">
                         <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="disponible" name="disponible" value="1">
                            <label class="form-check-label" for="disponible">Disponible</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Ajouter</button>
                    </div>
                </form>
            </div>

            <div class="card mb-4 p-4">
                <h5>Filtrer les produits</h5>
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <input type="text" name="nom" class="form-control" placeholder="Filtrer par nom" value="<?= htmlspecialchars($filtreNom) ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="quantite" class="form-control" placeholder="Quantité max" value="<?= htmlspecialchars($filtreQuantite) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="categorie" class="form-select">
                            <option value="">Toutes catégories</option>
                            <option value="boissons" <?= $filtreCategorie == 'boissons' ? 'selected' : '' ?>>Boissons</option>
                            <option value="épicerie" <?= $filtreCategorie == 'épicerie' ? 'selected' : '' ?>>Épicerie</option>
                            <option value="hygiène" <?= $filtreCategorie == 'hygiène' ? 'selected' : '' ?>>Hygiène</option>
                        </select>
                    </div>
                    <!-- Add disponible filter -->
                    <div class="col-md-3">
                        <select name="disponible" class="form-select">
                            <option value="">Toute disponibilité</option>
                            <option value="1" <?= $filtreDisponible === '1' ? 'selected' : '' ?>>Disponible</option>
                            <option value="0" <?= $filtreDisponible === '0' ? 'selected' : '' ?>>Indisponible</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-secondary">Filtrer</button>
                    </div>
                </form>
            </div>

            <div class="card p-4">
                <h5>Produits en stock</h5>
                <table class="table table-hover mt-3 table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Prix</th>
                            <th>Quantité</th>
                            <th>Catégorie</th>
                            <th>UID</th>
                            <th>Disponibilité</th> <!-- Add disponible column -->
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $produit): ?>
                            <tr class="<?= $produit['quantite'] <= 5 ? 'table-danger' : '' ?>">
                                <td><img src="<?= htmlspecialchars($produit['image']) ?>" alt="image produit"></td>
                                <td><?= htmlspecialchars($produit['nom']) ?></td>
                                <td><?= number_format($produit['prix'], 2) ?> DH</td>
                                <td><?= htmlspecialchars($produit['quantite']) ?></td>
                                <td><?= htmlspecialchars($produit['categorie']) ?></td>
                                <td><?= htmlspecialchars($produit['uid_codebar']) ?></td>
                                <td><?= htmlspecialchars($produit['disponible']) ?></td> <!-- Display 0 or 1 -->
                                <?php echo "<td>";

                                            echo '<a href="update.php?id='. $produit['id'] .'" class="mr-3" title="Update Record" data-toggle="tooltip"><span class="fa fa-pencil"></span></a>';
                                            echo '<a href="delete.php?id='. $produit['id'] .'" title="Delete Record" data-toggle="tooltip"><span class="fa fa-trash"></span></a>';
                                echo "</td>";
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($alerteStockCritique > 0): ?>
                <div class="alert alert-danger mt-3" role="alert">
                    ⚠️ Attention : <?= $alerteStockCritique ?> produit(s) en stock critique !
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>