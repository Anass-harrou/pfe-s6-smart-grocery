<?php
session_start();

// Date: 2025-06-23 01:32:16
// User: Anass-harrou

// Vérifie que l'utilisateur est connecté et a le rôle de gestionnaire
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gestionaire') {
    header('Location: ../login.php');
    exit();
}

require_once 'db.php';  // Utilise votre connexion PDO existante

// Récupérer statistiques générales
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$stats['faible_stock'] = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite <= 5")->fetchColumn();
$stats['en_stock'] = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite > 0")->fetchColumn();
$stats['rupture'] = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite = 0")->fetchColumn();
$stats['valeur_totale'] = $pdo->query("SELECT SUM(prix * quantite) FROM produits")->fetchColumn() ?: 0;
$stats['avg_price'] = $pdo->query("SELECT AVG(prix) FROM produits")->fetchColumn() ?: 0;
$stats['produits_dispo'] = $pdo->query("SELECT COUNT(*) FROM produits WHERE disponible = 1")->fetchColumn();

// Récupérer les produits par catégorie pour le graphique
$categoryQuery = $pdo->query("SELECT categorie, COUNT(*) as count FROM produits GROUP BY categorie ORDER BY count DESC");
$categories = [];
while ($row = $categoryQuery->fetch(PDO::FETCH_ASSOC)) {
    $categories[$row['categorie'] ?: 'Non catégorisé'] = $row['count'];
}

// Récupérer l'activité de vente récente (des vraies achats)
$recentActivityQuery = $pdo->query("
    SELECT 
        DATE_FORMAT(a.date_achat, '%d/%m') as date,
        COUNT(DISTINCT a.id_achat) as commandes,
        SUM(a.montant_total) as montant
    FROM 
        achats a 
    GROUP BY 
        DATE_FORMAT(a.date_achat, '%d/%m')
    ORDER BY 
        a.date_achat DESC
    LIMIT 7
");

$salesActivity = [
    'labels' => [],
    'commandes' => [],
    'montant' => []
];

while ($row = $recentActivityQuery->fetch(PDO::FETCH_ASSOC)) {
    array_unshift($salesActivity['labels'], $row['date']);
    array_unshift($salesActivity['commandes'], $row['commandes']);
    array_unshift($salesActivity['montant'], $row['montant']);
}

// Récupérer les produits avec leur détails (limité aux 100 premiers pour performance)
$productsQuery = $pdo->query("SELECT * FROM produits ORDER BY quantite ASC LIMIT 100");
$products = $productsQuery->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les produits les plus vendus
$topProductsQuery = $pdo->query("
    SELECT 
        p.id, 
        p.nom, 
        p.categorie, 
        p.prix,
        SUM(ap.quantite) as quantite_vendue,
        SUM(ap.quantite * ap.prix_unitaire) as montant_total
    FROM 
        achat_produits ap
    JOIN 
        produits p ON ap.id_produit = p.id
    GROUP BY 
        p.id
    ORDER BY 
        quantite_vendue DESC
    LIMIT 5
");
$topProducts = $topProductsQuery->fetchAll(PDO::FETCH_ASSOC);

// Récupérer données pour la répartition des prix
$price_ranges = [
    '0-10' => 0,
    '10-50' => 0,
    '50-100' => 0,
    '100-500' => 0,
    '500+' => 0
];

foreach ($products as $product) {
    $prix = $product['prix'];
    
    if ($prix <= 10) {
        $price_ranges['0-10']++;
    } elseif ($prix <= 50) {
        $price_ranges['10-50']++;
    } elseif ($prix <= 100) {
        $price_ranges['50-100']++;
    } elseif ($prix <= 500) {
        $price_ranges['100-500']++;
    } else {
        $price_ranges['500+']++;
    }
}

// Formater la date pour l'affichage
$date = date("d/m/Y");
$longDate = date("j F Y", strtotime($date));
$month = date("F Y", strtotime($date));

// Générer la liste des produits à faible stock
$faible_stock_list = [];
foreach ($products as $product) {
    if ($product['quantite'] <= 5 && $product['quantite'] > 0) {
        $faible_stock_list[] = [
            'id' => $product['id'],
            'nom' => $product['nom'],
            'quantite' => $product['quantite'],
            'prix' => $product['prix']
        ];
    }
}

// Fonction pour générer des couleurs aléatoires
function random_color() {
    $colors = [
        'rgba(75, 192, 192, 0.8)',
        'rgba(54, 162, 235, 0.8)',
        'rgba(153, 102, 255, 0.8)',
        'rgba(255, 99, 132, 0.8)',
        'rgba(255, 159, 64, 0.8)',
        'rgba(255, 205, 86, 0.8)',
        'rgba(201, 203, 207, 0.8)',
        'rgba(94, 114, 228, 0.8)',
        'rgba(43, 193, 85, 0.8)',
        'rgba(245, 54, 92, 0.8)'
    ];
    static $index = 0;
    return $colors[$index++ % count($colors)];
}

// Format month names in French
setlocale(LC_TIME, 'fr_FR.UTF-8');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport de stock | Smart Grocery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    
    <style>
        :root {
            --primary-bg: rgb(200, 229, 247);
            --sidebar-bg: rgb(86, 117, 148);
            --primary-text: #333;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --white: #fff;
            --card-border-radius: 0.75rem;
            --box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        body {
            background-color: var(--primary-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--primary-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            margin-left: 280px;
            padding: 2rem;
        }

        /* Sidebar Styles */
        .sidebar {
            background-color: var(--sidebar-bg);
            color: var(--white);
            padding: 1.25rem 1rem;
            width: 250px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1010;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-brand img {
            max-width: 140px;
            height: auto;
            transition: transform 0.3s ease;
        }

        .sidebar-brand img:hover {
            transform: scale(1.05);
        }

        .sidebar-nav {
            flex-grow: 1;
            padding-top: 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 0.85rem 1.25rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        .nav-link i {
            margin-right: 0.85rem;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: var(--white);
            transform: translateX(5px);
        }

        .nav-link:hover i {
            transform: scale(1.1);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: var(--white);
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 1rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Report Card Styles */
        .report-card {
            background-color: #fff;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            position: relative;
        }

        .report-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-body {
            padding: 2rem;
        }

        .report-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .report-subtitle {
            color: #6c757d;
            font-size: 1rem;
            margin-top: 0.5rem;
        }

        .report-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-report-action {
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-report-action:hover {
            transform: translateY(-2px);
        }

        .btn-report-action i {
            font-size: 1rem;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: #fff;
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: #fff;
        }

        .stat-icon-blue {
            background-color: #4e73df;
        }

        .stat-icon-green {
            background-color: #1cc88a;
        }

        .stat-icon-yellow {
            background-color: #f6c23e;
        }

        .stat-icon-red {
            background-color: #e74a3b;
        }

        .stat-icon-info {
            background-color: #36b9cc;
        }

        .stat-icon-purple {
            background-color: #7952b3;
        }

        .stat-title {
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .stat-description {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0;
            flex-grow: 1;
        }

        /* Charts Section */
        .charts-container {
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .chart-wrapper {
            background-color: #fff;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-title i {
            color: #6c757d;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Tables */
        .table-wrapper {
            background-color: #fff;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            color: #2c3e50;
            background-color: #f8f9fa;
            white-space: nowrap;
        }

        .stock-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }

        .stock-normal {
            background-color: rgba(28, 200, 138, 0.1);
            color: #1cc88a;
        }
        
        .stock-low {
            background-color: rgba(246, 194, 62, 0.1);
            color: #f6c23e;
        }
        
        .stock-empty {
            background-color: rgba(231, 74, 59, 0.1);
            color: #e74a3b;
        }

        /* Top Products Section */
        .top-products {
            margin-bottom: 2rem;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-item:hover {
            background-color: #f8f9fa;
        }

        .product-rank {
            font-size: 1.25rem;
            font-weight: 700;
            color: #6c757d;
            width: 40px;
            text-align: center;
        }

        .product-info {
            flex-grow: 1;
            padding: 0 1rem;
        }

        .product-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .product-category {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .product-stats {
            text-align: right;
        }

        .product-qty {
            font-size: 1.125rem;
            font-weight: 700;
            color: #4e73df;
            margin-bottom: 0.25rem;
        }

        .product-value {
            font-size: 0.875rem;
            color: #2c3e50;
        }

        /* Text Report Section */
        .text-report {
            background-color: #fff;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .text-report h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e9ecef;
        }

        .text-report p {
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }

        .report-date {
            font-style: italic;
            color: #6c757d;
        }

        .alert-items {
            padding: 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-items h4 {
            font-size: 1.125rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-items ul {
            margin-bottom: 0;
        }

        .alert-items li {
            margin-bottom: 0.5rem;
        }

        /* Print Styles */
        @media print {
            body {
                background-color: #fff;
                padding: 20px;
            }
            
            .sidebar, .report-actions, .no-print {
                display: none !important;
            }
            
            .container {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
                padding: 0;
            }
            
            .report-card, .stat-card, .chart-wrapper, .table-wrapper, .text-report {
                box-shadow: none;
                border: 1px solid #e9ecef;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            h1, h2, h3, h4, h5, h6 {
                color: #000 !important;
            }

            .chart-container {
                page-break-inside: avoid;
                height: 250px;
            }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .container {
                margin-left: 80px;
            }
            
            .sidebar {
                width: 70px;
            }
            
            .sidebar-brand img {
                max-width: 40px;
            }
            
            .nav-link span {
                display: none;
            }
            
            .nav-link i {
                margin-right: 0;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .report-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .report-actions {
                width: 100%;
            }
            
            .btn-report-action {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="../tablet/images/logo.png" alt="Smart Grocery Logo">
        </div>

        <hr class="bg-light opacity-25">

        <div class="sidebar-nav">
            <div class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="nav-item">
                <a class="nav-link" href="stock.php">
                    <i class="fas fa-box"></i>
                    <span>Stock</span>
                </a>
            </div>

            <div class="nav-item">
                <a class="nav-link" href="ajouterproduit.php">
                    <i class="fas fa-plus-circle"></i>
                    <span>Ajouter produit</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a class="nav-link active" href="rapport.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Rapport</span>
                </a>
            </div>

            <div class="nav-item">
                <a class="nav-link" href="reception.php">
                    <i class="fas fa-truck-loading"></i>
                    <span>Réception</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a class="nav-link" href="calendar.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>
            </div>
        </div>

        <div class="sidebar-footer">
            <hr class="bg-light opacity-25">
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </div>

    <div class="container">
        <div class="report-card">
            <div class="report-header">
                <div>
                    <h1 class="report-title">
                        <i class="fas fa-file-alt"></i> Rapport global du stock
                    </h1>
                    <p class="report-subtitle">Généré le <?= $longDate ?> par <?= htmlspecialchars($_SESSION['user']['username'] ?? 'Anass-harrou') ?></p>
                </div>
                <div class="report-actions">
                    <button class="btn btn-primary btn-report-action" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <button class="btn btn-info btn-report-action" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Exporter Excel
                    </button>
                </div>
            </div>

            <div class="report-body">
                <!-- Statistiques globales -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-blue">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-title">Total Produits</div>
                        <div class="stat-value"><?= $stats['total'] ?></div>
                        <div class="stat-description">Nombre total de produits enregistrés en système</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon stat-icon-green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-title">Produits en Stock</div>
                        <div class="stat-value"><?= $stats['en_stock'] ?></div>
                        <div class="stat-description">Produits avec au moins une unité disponible</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon stat-icon-yellow">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-title">Stock Critique</div>
                        <div class="stat-value"><?= $stats['faible_stock'] ?></div>
                        <div class="stat-description">Produits avec 5 unités ou moins</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon stat-icon-red">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-title">Rupture de Stock</div>
                        <div class="stat-value"><?= $stats['rupture'] ?></div>
                        <div class="stat-description">Produits complètement épuisés</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon stat-icon-purple">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-title">Valeur du Stock</div>
                        <div class="stat-value"><?= number_format($stats['valeur_totale'], 2) ?> DH</div>
                        <div class="stat-description">Valeur totale des produits disponibles</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon stat-icon-info">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="stat-title">Prix Moyen</div>
                        <div class="stat-value"><?= number_format($stats['avg_price'], 2) ?> DH</div>
                        <div class="stat-description">Prix moyen des produits en stock</div>
                    </div>
                </div>
                
                <!-- Graphiques -->
                <div class="charts-container">
                    <div class="chart-wrapper">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-pie"></i> Répartition des produits par catégorie
                        </h3>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-wrapper">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-bar"></i> État du stock
                        </h3>
                        <div class="chart-container">
                            <canvas id="stockStatusChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-wrapper">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-line"></i> Activité des ventes récentes
                        </h3>
                        <div class="chart-container">
                            <canvas id="salesActivityChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-wrapper">
                        <h3 class="chart-title">
                            <i class="fas fa-money-bill-alt"></i> Répartition des prix
                        </h3>
                        <div class="chart-container">
                            <canvas id="priceRangeChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Tableau des produits à stock critique -->
                <div class="table-wrapper">
                    <h3 class="mb-3"><i class="fas fa-exclamation-triangle text-warning"></i> Produits à stock critique</h3>
                    <?php if (count($faible_stock_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Produit</th>
                                    <th>Prix</th>
                                    <th>Quantité</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($faible_stock_list as $product): ?>
                                <tr>
                                    <td><?= $product['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($product['nom']) ?></td>
                                    <td><?= number_format($product['prix'], 2) ?> DH</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2"><?= $product['quantite'] ?></span>
                                            <div class="progress flex-grow-1" style="height: 6px; width: 100px;">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, $product['quantite'] * 20) ?>%" aria-valuenow="<?= $product['quantite'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="stock-badge stock-low">Stock faible</span>
                                    </td>
                                    <td>
                                        <a href="update.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Modifier
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i> Aucun produit n'a un stock critique actuellement.
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Produits les plus vendus -->
                <?php if (count($topProducts) > 0): ?>
                <div class="table-wrapper">
                    <h3 class="mb-3"><i class="fas fa-award text-success"></i> Top 5 des produits les plus vendus</h3>
                    <div class="top-products">
                        <?php $rank = 1; foreach($topProducts as $product): ?>
                        <div class="product-item">
                            <div class="product-rank">#<?= $rank++ ?></div>
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($product['nom']) ?></div>
                                <div class="product-category"><?= htmlspecialchars($product['categorie'] ?? 'Non catégorisé') ?></div>
                            </div>
                            <div class="product-stats">
                                <div class="product-qty"><?= $product['quantite_vendue'] ?> unités vendues</div>
                                <div class="product-value"><?= number_format($product['montant_total'], 2) ?> DH</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Rapport textuel complet -->
                <div class="text-report">
                    <h3>Rapport narratif</h3>
                    <p class="report-date">Généré le <?= $longDate ?> pour Smart Grocery</p>
                    
                    <p>
                        À la date du <strong><?= $date ?></strong>, le stock contient actuellement 
                        <strong><?= $stats['total'] ?></strong> produits enregistrés pour une valeur totale de 
                        <strong><?= number_format($stats['valeur_totale'], 2) ?> DH</strong>.
                    </p>
                    
                    <p>
                        Parmi ces produits, <strong><?= $stats['en_stock'] ?></strong> sont disponibles en stock
                        <?php if ($stats['rupture'] > 0): ?>
                            et <strong><?= $stats['rupture'] ?></strong> sont en rupture de stock.
                        <?php else: ?>
                            et aucun n'est en rupture de stock.
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($stats['faible_stock'] > 0): ?>
                    <p>
                        <strong><?= $stats['faible_stock'] ?></strong> produits ont une quantité inférieure ou égale à 
                        <strong>5 unités</strong>, ce qui nécessite un réapprovisionnement.
                    </p>
                    <?php endif; ?>
                    
                    <p>
                        Les catégories les plus importantes en nombre de produits sont :
                        <?php 
                        $top_categories = array_slice($categories, 0, 3);
                        $category_text = [];
                        $i = 0;
                        foreach ($top_categories as $cat => $count) {
                            $i++;
                            $category_text[] = "<strong>" . htmlspecialchars($cat) . "</strong> ($count produits)";
                        }
                        echo implode(', ', $category_text);
                        ?>.
                    </p>

                    <?php if (count($topProducts) > 0): ?>
                    <p>
                        Le produit le plus vendu est <strong><?= htmlspecialchars($topProducts[0]['nom']) ?></strong> avec
                        <strong><?= $topProducts[0]['quantite_vendue'] ?> unités</strong> vendues, 
                        représentant un chiffre d'affaires de <strong><?= number_format($topProducts[0]['montant_total'], 2) ?> DH</strong>.
                    </p>
                    <?php endif; ?>
                    
                    <p>
                        Ce rapport a été généré automatiquement par le système de gestion de stock.
                        Pour plus de détails, consultez les tableaux et graphiques ci-dessus.
                    </p>
                    
                    <div class="row mt-4 pt-3 border-top no-print">
                        <div class="col-sm">
                            <button class="btn btn-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Imprimer le rapport
                            </button>
                        </div>
                        <div class="col-sm text-end">
                            <a href="stock.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Retour au stock
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Register Chart.js plugins
            Chart.register(ChartDataLabels);
            
            // Configuration de Chart.js
            Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
            Chart.defaults.font.size = 14;
            Chart.defaults.color = '#6c757d';
            Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            Chart.defaults.plugins.legend.position = 'bottom';
            
            // Graphique des catégories
            var categoryData = {
                labels: [
                    <?php 
                    foreach ($categories as $cat => $count) {
                        echo "'" . addslashes($cat) . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Produits par catégorie',
                    data: [
                        <?php 
                        foreach ($categories as $count) {
                            echo "$count, ";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        <?php 
                        foreach ($categories as $cat => $count) {
                            echo "'" . random_color() . "', ";
                        }
                        ?>
                    ],
                    borderWidth: 1
                }]
            };
            
            var categoryCtx = document.getElementById('categoryChart').getContext('2d');
            var categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: categoryData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        title: {
                            display: false,
                        },
                        datalabels: {
                            formatter: (value, ctx) => {
                                let sum = 0;
                                let dataArr = ctx.chart.data.datasets[0].data;
                                dataArr.forEach(data => {
                                    sum += data;
                                });
                                let percentage = (value * 100 / sum).toFixed(1) + "%";
                                return percentage;
                            },
                            color: '#fff',
                            font: {
                                weight: 'bold',
                                size: 12
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    },
                    layout: {
                        padding: 20
                    }
                }
            });
            
            // Graphique d'état du stock
            var stockData = {
                labels: ['Stock Normal', 'Stock Faible', 'Rupture'],
                datasets: [{
                    label: 'Nombre de produits',
                    data: [
                        <?= $stats['en_stock'] - $stats['faible_stock'] ?>, 
                        <?= $stats['faible_stock'] - $stats['rupture'] ?>, 
                        <?= $stats['rupture'] ?>
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(220, 53, 69, 0.7)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            };
            
            var stockStatusCtx = document.getElementById('stockStatusChart').getContext('2d');
            var stockStatusChart = new Chart(stockStatusCtx, {
                type: 'bar',
                data: stockData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            formatter: Math.round,
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart'
                    }
                }
            });
            
            // Graphique d'activité des ventes
            var salesData = {
                labels: <?= json_encode($salesActivity['labels']) ?>,
                datasets: [
                    {
                        label: 'Montant (DH)',
                        data: <?= json_encode($salesActivity['montant']) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y1',
                        type: 'line'
                    },
                    {
                        label: 'Commandes',
                        data: <?= json_encode($salesActivity['commandes']) ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        type: 'bar'
                    }
                ]
            };
            
            var salesActivityCtx = document.getElementById('salesActivityChart').getContext('2d');
            var salesActivityChart = new Chart(salesActivityCtx, {
                type: 'bar',
                data: salesData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        datalabels: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Nombre de commandes'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Montant (DH)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
            
            // Graphique de répartition des prix
            var priceRangeData = {
                labels: Object.keys(<?= json_encode($price_ranges) ?>),
                datasets: [{
                    label: 'Nombre de produits',
                    data: Object.values(<?= json_encode($price_ranges) ?>),
                    backgroundColor: 'rgba(153, 102, 255, 0.7)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            };
            
            var priceRangeCtx = document.getElementById('priceRangeChart').getContext('2d');
            var priceRangeChart = new Chart(priceRangeCtx, {
                type: 'bar',
                data: priceRangeData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            title: {
                                display: true,
                                text: 'Nombre de produits'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Fourchette de prix (DH)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            formatter: Math.round,
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            });
            
            // Fonction pour exporter en Excel
            window.exportToExcel = function() {
                // Créer un nouveau classeur
                const wb = XLSX.utils.book_new();
                
                // Créer une feuille avec les statistiques générales
                const statsData = [
                    ['Rapport de stock - Smart Grocery', ''],
                    ['Généré le', '<?= $date ?>'],
                    [''],
                    ['Statistiques générales', ''],
                    ['Total produits', <?= $stats['total'] ?>],
                    ['Produits en stock', <?= $stats['en_stock'] ?>],
                    ['Stock critique', <?= $stats['faible_stock'] ?>],
                    ['Rupture de stock', <?= $stats['rupture'] ?>],
                    ['Valeur du stock', <?= $stats['valeur_totale'] ?>, 'DH'],
                    ['Prix moyen', <?= $stats['avg_price'] ?>, 'DH'],
                    [''],
                    ['Produits à faible stock', '']
                ];
                
                // Ajouter les en-têtes pour les produits à faible stock
                statsData.push(['ID', 'Nom du produit', 'Prix', 'Quantité']);
                
                // Ajouter les produits à faible stock
                <?php foreach($faible_stock_list as $product): ?>
                statsData.push([
                    <?= $product['id'] ?>, 
                    '<?= addslashes($product['nom']) ?>', 
                    <?= $product['prix'] ?>, 
                    <?= $product['quantite'] ?>
                ]);
                <?php endforeach; ?>
                
                // Créer la feuille avec les données
                const ws = XLSX.utils.aoa_to_sheet(statsData);
                
                // Ajouter la feuille au classeur
                XLSX.utils.book_append_sheet(wb, ws, "Rapport de stock");
                
                // Créer une feuille pour les catégories
                const categoryData = [
                    ['Catégorie', 'Nombre de produits']
                ];
                
                <?php foreach($categories as $cat => $count): ?>
                categoryData.push(['<?= addslashes($cat) ?>', <?= $count ?>]);
                <?php endforeach; ?>
                
                const wsCategories = XLSX.utils.aoa_to_sheet(categoryData);
                XLSX.utils.book_append_sheet(wb, wsCategories, "Catégories");
                
                // Sauvegarder le fichier
                const fileName = 'Rapport_Stock_<?= date("Y-m-d") ?>.xlsx';
                XLSX.writeFile(wb, fileName);
            };
        });
    </script>
</body>
</html>