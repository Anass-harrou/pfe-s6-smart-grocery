<?php
session_start();
require 'db.php';

// Obtenir l'heure et les minutes actuelles
$heureMinute = date('H:i');

// Chemin vers ton logo (à remplacer !)
$logoPath = '../logo2.svg';

// Stats globales
$valeurStock = $pdo->query("SELECT SUM(quantite * prix) FROM produits")->fetchColumn();
$totalProduits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$stockCritique = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite <= 5")->fetchColumn();
// Calcul du stock total (nombre total d'unités en stock)
$stockTotalQuantite = $pdo->query("SELECT SUM(quantite) FROM produits")->fetchColumn();

// Calcul de la valeur totale du stock (quantité * prix)
$valeurStock = $pdo->query("SELECT SUM(quantite * prix) FROM produits")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Gestionnaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            display: flex; /* Pour la mise en page globale avec la sidebar */
            min-height: 100vh; /* Assure que le body prend au moins la hauteur de l'écran */
        }

        /* Sidebar Styles */
        .sidebar {
            background-color:rgb(86, 117, 148); /* Couleur plus sombre pour la sidebar */
            color: white;
            padding: 1rem;
            width: 250px; /* Légèrement plus large pour un meilleur confort */
            flex-shrink: 0; /* Empêche la sidebar de rétrécir */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100vh;
            position: fixed; /* Pour rester visible lors du défilement */
            top: 0; /* Alignement en haut */
            left: 0; /* Alignement à gauche */
            z-index: 1010; /* Au-dessus du contenu principal */
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); /* Ombre légère pour la séparation */
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand img {
            max-height: 3rem; /* Taille du logo */
            margin-right: 0.75rem;
          
        }

        .sidebar-brand span {
            font-size: 1.25rem;
            font-weight: bold;
        }

        .sidebar-nav {
            flex-grow: 1; /* Prend l'espace restant */
            padding-top: 1rem;
        }

        .nav-item {
            margin-bottom: 0.75rem;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            transition: background-color 0.15s ease-in-out;
        }

        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-footer {
            padding: 1rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 250px; /* Correspond à la largeur de la sidebar */
            padding: 2rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column; /* Pour organiser les éléments verticalement */
        }

        /* Top Bar Styles */
        .top-bar {
            background-color: #fff;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e3e6f0;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1rem;
            color: #333;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            position: sticky; /* Reste en haut lors du défilement du contenu principal */
            top: 0;
            z-index: 1000;
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
            color: #007bff; /* Couleur primaire */
        }

        .top-bar-right .fw-bold {
            font-weight: 500 !important;
        }

        /* Dashboard Cards Styles */
        .dashboard-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card {
            background-color: #fff;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            flex: 1 0 calc(50% - 0.75rem); /* Deux cartes par ligne sur les écrans moyens et grands */
            max-width: calc(50% - 0.75rem);
        }

        @media (max-width: 767.98px) {
            .card {
                flex: 0 0 100%; /* Une carte par ligne sur les petits écrans */
                max-width: 100%;
            }
        }

        .card-body {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1rem;
            color: #4e73df; /* Couleur primaire pour les titres */
            margin-bottom: 0.5rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .card-value {
            font-size: 1.75rem;
            font-weight: bold;
            color:rgb(129, 144, 201);
        }

        .card-icon {
            font-size: 2.5rem;
            color: #dddfeb; /* Couleur grise claire pour les icônes */
        }

        .bg-primary-card {
            border-left: 0.25rem solid #4e73df !important;
        }

        .bg-success-card {
            border-left: 0.25rem solid #1cc88a !important;
        }

        /* Conseil Box Styles */
        .conseil-box {
            background-color: #f8f9fa;
            border-left: 0.25rem solid #f6c23e !important; /* Couleur d'avertissement */
            color: #85640a;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0.35rem;
            display: flex;
            align-items: center;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .conseil-box i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
            color: #f6c23e;
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <div class="sidebar">
            <div class="sidebar-brand">
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="../logo2.svg" class="me-2">
                
            </div>
            <hr class="bg-light">
            <div class="sidebar-nav">
                <div class="nav-item">
                    <a class="nav-link" href="stock.php">
                        <i class="fas fa-box me-2"></i>
                        Stock
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="ajouterproduit.php">
                        <i class="fas fa-plus me-2"></i>
                        Ajouter produit
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-bell me-2"></i>
                        Alertes
                    </a>
                </div>
            </div>
            <div class="sidebar-footer">
                <hr class="bg-light">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Déconnexion
                </a>
            </div>
        </div>

        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <span class="me-3"><i class="fas fa-calendar-alt top-bar-icon"></i> <?= date('d/m/Y') ?></span>
                    <span class="top-bar-separator"></span>
                    <span class="me-3"><i class="far fa-clock top-bar-icon"></i> <?= $heureMinute ?></span>
                </div>
                <div class="top-bar-right d-flex align-items-center">
                    <span class="fw-bold text-dark"><i class="fas fa-user me-2 text-primary"></i>Bienvenue, Gestionnaire</span>
                </div>
            </div>

            <div class="conseil-box">
                <i class="fas fa-lightbulb me-2"></i>
                Conseil ! : Pensez à vérifier les produits proches de la rupture de stock avant midi.
            </div>

            <div class="dashboard-cards">
                <div class="card bg-primary-card">
                    <div class="card-body">
                        <div class="me-3">
                            <div class="card-title">Stock total</div>
                            <div class="card-value"><?= htmlspecialchars($totalProduits ?? 0) ?></div>
                        </div>
                        <i class="fas fa-boxes card-icon"></i>
                    </div>
                </div>

                <div class="card bg-success-card">
                    <div class="card-body">
                        <div class="me-3">
                            <div class="card-title">Valeur du stock</div>
                            <div class="card-value"><?= htmlspecialchars(number_format($valeurStock ?? 0, 2, ',', ' ') . ' dhs') ?></div>
                        </div>
                        <i class="fas fa-sack-dollar card-icon"></i>
                    </div>
                </div>
            </div>

            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>