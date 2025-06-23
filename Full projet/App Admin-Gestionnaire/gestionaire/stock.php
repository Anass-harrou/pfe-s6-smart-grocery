<?php
session_start();

// Date: 2025-06-23 01:30:24
// User: Anass-harrou

// Vérifie que l'utilisateur est connecté et a le rôle de gestionnaire
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gestionaire') {
    header('Location: ../login.php');
    exit();
}
require_once 'db.php';

// Traitement du filtre de catégorie
$categorieFilter = isset($_GET['categorie']) ? $_GET['categorie'] : '';

// Traitement de la recherche
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Construction de la requête avec filtres
$sql = "SELECT * FROM produits WHERE 1=1";

if (!empty($categorieFilter)) {
    $sql .= " AND categorie = :categorie";
}

if (!empty($searchTerm)) {
    $sql .= " AND (nom LIKE :search OR categorie LIKE :search OR uid_codebar LIKE :search)";
}

$sql .= " ORDER BY quantite <= 5 DESC, id DESC";

$stmt = $pdo->prepare($sql);

if (!empty($categorieFilter)) {
    $stmt->bindValue(':categorie', $categorieFilter, PDO::PARAM_STR);
}

if (!empty($searchTerm)) {
    $searchParam = "%" . $searchTerm . "%";
    $stmt->bindValue(':search', $searchParam, PDO::PARAM_STR);
}

$stmt->execute();
$produits = $stmt->fetchAll();

// Récupérer les catégories pour le filtre
$categoriesStmt = $pdo->query("SELECT DISTINCT categorie FROM produits ORDER BY categorie");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

// Statistiques
$totalProduits = count($produits);
$stockCritique = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite <= 5")->fetchColumn();
$valeurStock = $pdo->query("SELECT SUM(prix * quantite) FROM produits")->fetchColumn();

// Vérifier s'il existe des produits en rupture de stock
$rupture = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite = 0")->fetchColumn();

// Heure actuelle
$heureMinute = date('H:i');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Stock | Smart Grocery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
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
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            background-color: var(--sidebar-bg);
            color: var(--white);
            padding: 1.25rem 1rem;
            width: 280px;
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

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background-color: var(--white);
            border-radius: 0 4px 4px 0;
        }

        .sidebar-footer {
            padding: 1rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Main Content */
        .main-content {
            margin-left: 300px;
            padding: 2rem;
            width: calc(100% - 300px);
            transition: all 0.3s ease;
        }

        /* Top Bar */
        .top-bar {
            background-color: var(--white);
            padding: 1.25rem 1.5rem;
            border-radius: var(--card-border-radius);
            margin-bottom: 1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--box-shadow);
            position: relative;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-bar-separator {
            height: 25px;
            border-left: 2px solid #e9ecef;
            margin: 0 15px;
        }

        .top-bar-icon {
            margin-right: 8px;
            color: #007bff;
        }

        .user-profile {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            padding: 8px 15px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-profile:hover {
            background-color: #e9ecef;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 10px;
            background-color: #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .navbar {
            background-color: var(--white);
            color: var(--primary-text);
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            font-weight: 600;
            border-left: 0.25rem solid #000000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
        }

        /* Dashboard Cards */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: var(--white);
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);
        }

        .stat-card-primary {
            border-left: 0.25rem solid #4e73df;
            color: #4e73df;
        }

        .stat-card-danger {
            border-left: 0.25rem solid var(--danger-color);
            color: var(--danger-color);
        }

        .stat-card-success {
            border-left: 0.25rem solid var(--success-color);
            color: var(--success-color);
        }

        .stat-card-warning {
            border-left: 0.25rem solid var(--warning-color);
            color: var(--warning-color);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .stat-card-icon {
            font-size: 2rem;
            opacity: 0.8;
        }

        .stat-card-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card-title {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 500;
        }

        /* Table Styles */
        .content-card {
            background-color: var(--white);
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
            border-bottom-width: 2px;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 0.85rem;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.04);
        }

        .table-row-danger {
            background-color: rgba(220, 53, 69, 0.1);
        }

        .table-row-warning {
            background-color: rgba(255, 193, 7, 0.1);
        }

        .product-name {
            font-weight: 500;
        }

        .product-category {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            background-color: #f0f0f0;
            color: #555;
        }

        /* Action Buttons */
        .action-icons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-action {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: #fff;
        }

        .btn-edit {
            background-color: #4e73df;
        }

        .btn-delete {
            background-color: #e74a3b;
        }

        .btn-view {
            background-color: #36b9cc;
        }

        .btn-action:hover {
            transform: scale(1.1);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }

        /* Filters and Search */
        .filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
        }

        .filter-item {
            flex: 1;
            min-width: 200px;
        }

        .filter-item select,
        .filter-item input {
            border-radius: 0.5rem;
        }

        .badge {
            padding: 0.5em 0.75em;
            font-weight: 600;
            font-size: 0.75em;
        }
        

        .badge-stock {
            background-color: #28a745;
        }
        /* Product Image Styles - Add to your stylesheet */
.product-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.product-image-wrapper {
    width: 70px;
    height: 70px;
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    background-color: #f9f9f9;
    position: relative;
    box-shadow: 0 2px 5px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.product-image-wrapper:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.12);
    cursor: pointer;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-details {
    display: flex;
    flex-direction: column;
}

.product-name {
    font-weight: 500;
    color: #333;
    margin-bottom: 2px;
}

.product-meta {
    font-size: 0.8rem;
}

.zoom-icon {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background-color: rgba(255,255,255,0.7);
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.product-image-wrapper:hover .zoom-icon {
    opacity: 1;
}

        .badge-low {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-out {
            background-color: #dc3545;
        }

        /* Quantity Column */
        .quantity-column {
            position: relative;
        }

        .quantity-progress {
            height: 6px;
            width: 100%;
            background-color: #e9ecef;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }

        .quantity-bar {
            height: 100%;
            border-radius: 3px;
        }

        .quantity-high {
            background-color: #28a745;
        }

        .quantity-medium {
            background-color: #ffc107;
        }

        .quantity-low {
            background-color: #dc3545;
        }

        /* Product Image Styles */
        .product-image-container {
            width: 60px;
            height: 60px;
            overflow: hidden;
            border-radius: 6px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }

        .product-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-info {
            display: flex;
            align-items: center;
        }

        .product-image-container:hover .product-image {
            transform: scale(1.1);
        }

        /* Image Modal */
        .modal-image {
            max-width: 100%;
            max-height: 70vh;
            display: block;
            margin: 0 auto;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                padding: 1rem 0.5rem;
            }

            .sidebar-brand img {
                max-width: 40px;
            }

            .nav-link span {
                display: none;
            }

            .nav-link i {
                margin-right: 0;
                font-size: 1.25rem;
            }

            .main-content {
                margin-left: 80px;
                width: calc(100% - 80px);
            }
        }

        @media (max-width: 768px) {
            .cards-container {
                grid-template-columns: 1fr;
            }
            
            .filters-container {
                flex-direction: column;
            }
            
            .filter-item {
                width: 100%;
                min-width: auto;
            }
            
            .navbar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .top-bar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .top-bar-right {
                width: 100%;
                justify-content: flex-start;
            }
        }

        /* DataTables Customization */
        .dataTables_filter {
            margin-bottom: 1rem;
        }
        
        .dataTables_filter input {
            border: 1px solid #ced4da;
            border-radius: 0.5rem;
            padding: 0.375rem 0.75rem;
        }
        
        .dataTables_length select {
            border: 1px solid #ced4da;
            border-radius: 0.5rem;
            padding: 0.375rem 0.75rem;
        }
        
        .paginate_button {
            border-radius: 0.25rem !important;
        }
        
        /* Chart Container */
        .chart-container {
            height: 300px;
            margin-bottom: 2rem;
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        /* Improve accessibility */
        .badge {
            line-height: 1.2;
        }
        
        /* Print styles */
        @media print {
            .sidebar, .top-bar, .filters-container, .action-icons, .dataTables_filter,
            .dataTables_length, .dataTables_paginate, .btn-print, .btn-export {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .content-card, .table {
                box-shadow: none;
            }
            
            body {
                background-color: white;
            }
            
            .table th {
                color: #000 !important;
                background-color: #f0f0f0 !important;
            }
            
            @page {
                size: landscape;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="../tablet/images/logo.png" alt="Smart Grocery Logo">
        </div>

        <hr class="bg-light opacity-25 my-0">

        <div class="sidebar-nav">
            <div class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="nav-item">
                <a class="nav-link active" href="stock.php">
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
                <a class="nav-link" href="rapport.php">
                    <i class="fas fa-chart-line"></i>
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
            <hr class="bg-light opacity-25 my-0">
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="top-bar-left">
                <span><i class="fas fa-calendar-alt top-bar-icon"></i><?= date('d/m/Y') ?></span>
                <span class="top-bar-separator"></span>
                <span><i class="far fa-clock top-bar-icon"></i><?= $heureMinute ?></span>
            </div>
            <div class="top-bar-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user']['role'], 0, 1)) ?>
                    </div>
                    <span><?= htmlspecialchars($_SESSION['user']['username'] ?? 'Utilisateur') ?></span>
                </div>
            </div>
        </div>

        <nav class="navbar">
            <h5 class="mb-0"><i class="fas fa-box me-2"></i>Gestion de Stock</h5>
            <div>
                <button class="btn btn-outline-primary me-2" id="exportExcel">
                    <i class="fas fa-file-excel me-1"></i> Exporter
                </button>
                <button class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Imprimer
                </button>
            </div>
        </nav>

        <div class="cards-container fade-in">
            <div class="stat-card stat-card-primary">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?= $totalProduits ?></div>
                        <div class="stat-card-title">Produits en stock</div>
                    </div>
                    <div class="stat-card-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
                <div class="stat-card-footer">
                    <small><i class="fas fa-info-circle"></i> Nombre total de produits</small>
                </div>
            </div>

            <div class="stat-card stat-card-danger">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?= $stockCritique ?></div>
                        <div class="stat-card-title">Stock critique</div>
                    </div>
                    <div class="stat-card-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-card-footer">
                    <small><i class="fas fa-info-circle"></i> Produits à réapprovisionner</small>
                </div>
            </div>

            <div class="stat-card stat-card-success">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?= number_format($valeurStock, 2) ?> DH</div>
                        <div class="stat-card-title">Valeur du stock</div>
                    </div>
                    <div class="stat-card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <div class="stat-card-footer">
                    <small><i class="fas fa-info-circle"></i> Valeur totale des produits</small>
                </div>
            </div>
            
            <div class="stat-card stat-card-warning">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?= $rupture ?></div>
                        <div class="stat-card-title">Rupture de stock</div>
                    </div>
                    <div class="stat-card-icon">
                        <i class="fas fa-store-slash"></i>
                    </div>
                </div>
                <div class="stat-card-footer">
                    <small><i class="fas fa-info-circle"></i> Produits épuisés</small>
                </div>
            </div>
        </div>

        <div class="content-card fade-in">
            <div class="filters-container">
                <div class="filter-item">
                    <form action="" method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <div class="filter-item">
                    <form action="" method="GET" class="d-flex gap-2">
                        <select name="categorie" class="form-select" onchange="this.form.submit()">
                            <option value="">Toutes les catégories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $categorieFilter === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <div class="filter-item text-end">
                    <a href="ajouterproduit.php" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> Nouveau produit
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table id="productsTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produit</th>
                            <th>Prix</th>
                            <th>Quantité</th>
                            <th>Catégorie</th>
                            <th>UID</th>
                            <?php if (isset($produits[0]['poids'])): ?>
                            <th>Poids (kg)</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rowClass = '';
                        foreach ($produits as $produit): 
                            if ($produit['quantite'] <= 0) {
                                $rowClass = 'table-row-danger';
                                $stockStatus = 'badge-out';
                                $stockText = 'Épuisé';
                                $barClass = 'quantity-low';
                                $barWidth = '0%';
                            } elseif ($produit['quantite'] <= 5) {
                                $rowClass = 'table-row-warning';
                                $stockStatus = 'badge-low';
                                $stockText = 'Critique';
                                $barClass = 'quantity-medium';
                                $barWidth = '25%';
                            } else {
                                $rowClass = '';
                                $stockStatus = 'badge-stock';
                                $stockText = 'En stock';
                                $barClass = 'quantity-high';
                                $barWidth = min(100, $produit['quantite'] * 5) . '%';
                            }

                            // Image placeholder if missing
                            $imagePath = !empty($produit['image']) && file_exists(ltrim($produit['image'], '.')) 
                                ? $produit['image'] 
                                : '../uploads/placeholder.png';
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= htmlspecialchars($produit['id']) ?></td>
                            <td>
    <div class="product-info">
        <div class="product-image-wrapper" 
             data-bs-toggle="modal" 
             data-bs-target="#imageModal" 
             data-img-src="<?= !empty($produit['image']) ? $produit['image'] : '../uploads/placeholder.png' ?>" 
             data-img-name="<?= htmlspecialchars($produit['nom']) ?>"
             data-product-id="<?= $produit['id'] ?>">
            
            <?php 
            // Determine correct image path
            $imagePath = !empty($produit['image']) ? $produit['image'] : '../uploads/placeholder.png';
            
            // Check if image exists
            if (!empty($produit['image']) && !file_exists($produit['image'])) {
                // Try alternative paths
                if (file_exists('../' . $produit['image'])) {
                    $imagePath = '../' . $produit['image'];
                } else if (file_exists('../uploads/' . basename($produit['image']))) {
                    $imagePath = '../uploads/' . basename($produit['image']);
                } else {
                    $imagePath = '../uploads/placeholder.png';
                }
            }
            ?>
            
            <img src="<?= htmlspecialchars($imagePath) ?>" 
                 alt="<?= htmlspecialchars($produit['nom']) ?>"
                 class="product-image"
                 onerror="this.onerror=null; this.src='../uploads/placeholder.png';">
            
            <div class="zoom-icon">
                <i class="fas fa-search-plus fa-xs"></i>
            </div>
        </div>
        
        <div class="product-details">
            <span class="product-name"><?= htmlspecialchars($produit['nom']) ?></span>
            <?php if (!empty($produit['description']) && strlen($produit['description']) > 0): ?>
                <small class="product-meta text-muted"><?= htmlspecialchars(substr($produit['description'], 0, 25)) ?><?= strlen($produit['description']) > 25 ? '...' : '' ?></small>
            <?php endif; ?>
        </div>
    </div>
</td>
                            <td><?= number_format($produit['prix'], 2) ?> DH</td>
                            <td class="quantity-column">
                                <div class="d-flex align-items-center">
                                    <?= htmlspecialchars($produit['quantite']) ?>
                                    <span class="badge <?= $stockStatus ?> ms-2"><?= $stockText ?></span>
                                </div>
                                <div class="quantity-progress">
                                    <div class="quantity-bar <?= $barClass ?>" style="width: <?= $barWidth ?>"></div>
                                </div>
                            </td>
                            <td>
                                <span class="product-category"><?= htmlspecialchars($produit['categorie']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($produit['uid_codebar']) ?></td>
                            <?php if (isset($produit['poids'])): ?>
                            <td><?= number_format($produit['poids'], 2) ?> kg</td>
                            <?php endif; ?>
                            <td>
                                <div class="action-icons">
                                    <a href="#" class="btn-action btn-view" title="Voir détails" data-bs-toggle="tooltip"
                                       onclick="viewProduct(<?= $produit['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="update.php?id=<?= $produit['id'] ?>" class="btn-action btn-edit" title="Modifier" data-bs-toggle="tooltip">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn-action btn-delete" title="Supprimer" data-bs-toggle="tooltip"
                                           onclick="confirmDelete(<?= $produit['id'] ?>, '<?= htmlspecialchars(addslashes($produit['nom'])) ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($produits) === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                    <h5 class="mb-3">Aucun produit trouvé</h5>
                                    <p class="text-muted">Essayez d'utiliser des termes de recherche différents ou <a href="stock.php">voir tous les produits</a>.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmation de suppression</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le produit <span id="deleteProductName" class="fw-bold"></span>?</p>
                    <p class="text-danger mb-0"><small><i class="fas fa-exclamation-triangle"></i> Cette action est irréversible.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <a href="#" id="deleteProductLink" class="btn btn-danger">Supprimer</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Aperçu de l'image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" class="modal-image" id="modalImage" alt="Aperçu du produit">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="productDetailsModalLabel">Détails du produit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5 text-center mb-3">
                            <img id="detailsProductImage" src="" alt="Image du produit" class="img-fluid mb-3" style="max-height: 200px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        </div>
                        <div class="col-md-7">
                            <h4 id="detailsProductName" class="mb-3"></h4>
                            
                            <div class="mb-3">
                                <span class="badge bg-primary" id="detailsProductCategory"></span>
                                <span class="badge bg-secondary ms-2" id="detailsProductUID"></span>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="fw-bold d-block">Prix:</label>
                                    <span id="detailsProductPrice" class="fs-5"></span>
                                </div>
                                <div class="col-6">
                                    <label class="fw-bold d-block">Quantité:</label>
                                    <div class="d-flex align-items-center">
                                        <span id="detailsProductQuantity" class="fs-5"></span>
                                        <span id="detailsProductStockStatus" class="badge ms-2"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="fw-bold d-block">Emplacement:</label>
                                <div class="d-flex align-items-center">
                                    <span class="me-2">Section:</span>
                                    <span id="detailsProductSection" class="badge bg-info me-3"></span>
                                    <span class="me-2">Position:</span>
                                    <span id="detailsProductPosition"></span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="fw-bold d-block">Description:</label>
                                <p id="detailsProductDescription" class="text-muted"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a id="editProductLink" href="#" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i> Modifier
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JavaScript -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <!-- ExcelJS for export -->
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.3.0/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#productsTable').DataTable({
                responsive: true,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                },
                "pageLength": 10,
                "order": [[ 3, "asc" ]], // Sort by quantity ascending
                "columnDefs": [
                    { "orderable": false, "targets": -1 } // Disable sorting on action column
                ]
            });
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Handle export to Excel
            $('#exportExcel').on('click', function() {
                exportToExcel();
            });
            
            // Image preview modal
            $('.img-preview').click(function() {
                var imgSrc = $(this).data('img-src');
                var imgName = $(this).data('img-name');
                
                $('#modalImage').attr('src', imgSrc);
                $('#modalImage').attr('alt', imgName);
                $('#imageModalLabel').text('Image: ' + imgName);
            });
            
            // Handle error on image load
            $('img.product-image').on('error', function() {
                $(this).attr('src', '../uploads/placeholder.png');
            });
        });
        
        // View product details
        function viewProduct(id) {
            // AJAX request to get product details
            $.ajax({
                url: 'get_product_details.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(data) {
                    // Populate modal with product details
                    $('#detailsProductName').text(data.nom);
                    $('#detailsProductCategory').text(data.categorie);
                    $('#detailsProductUID').text('UID: ' + data.uid_codebar);
                    $('#detailsProductPrice').text(parseFloat(data.prix).toFixed(2) + ' DH');
                    $('#detailsProductQuantity').text(data.quantite);
                    $('#detailsProductDescription').text(data.description || 'Aucune description disponible.');
                    
                    // Set stock status badge
                    if (data.quantite <= 0) {
                        $('#detailsProductStockStatus').removeClass().addClass('badge bg-danger').text('Épuisé');
                    } else if (data.quantite <= 5) {
                        $('#detailsProductStockStatus').removeClass().addClass('badge bg-warning text-dark').text('Critique');
                    } else {
                        $('#detailsProductStockStatus').removeClass().addClass('badge bg-success').text('En stock');
                    }
                    
                    // Set location info
                    $('#detailsProductSection').text(data.map_section || 'N/A');
                    let position = 'X: ' + (data.map_position_x || 'N/A') + ', Y: ' + (data.map_position_y || 'N/A');
                    $('#detailsProductPosition').text(position);
                    
                    // Set image
                    if (data.image && data.image.trim() !== '') {
                        $('#detailsProductImage').attr('src', data.image);
                    } else {
                        $('#detailsProductImage').attr('src', '../uploads/placeholder.png');
                    }
                    
                    // Set edit link
                    $('#editProductLink').attr('href', 'update.php?id=' + id);
                    
                    // Show modal
                    $('#productDetailsModal').modal('show');
                },
                error: function() {
                    alert('Erreur lors de la récupération des détails du produit.');
                }
            });
        }
        
        // Delete confirmation
        function confirmDelete(id, name) {
            document.getElementById('deleteProductName').textContent = name;
            document.getElementById('deleteProductLink').href = 'delete.php?id=' + id;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        // Export to Excel function
        function exportToExcel() {
            // Create a new workbook
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet('Stock');
            
            // Add headers
            const headers = ['ID', 'Produit', 'Prix (DH)', 'Quantité', 'Catégorie', 'UID Codebar'];
            
            <?php if (isset($produits[0]['poids'])): ?>
            headers.push('Poids (kg)');
            <?php endif; ?>
            
            worksheet.addRow(headers);
            
            // Style header row
            worksheet.getRow(1).font = { bold: true };
            worksheet.getRow(1).fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: 'FFE9ECEF' }
            };
            
            // Add data rows
            <?php foreach ($produits as $produit): ?>
            worksheet.addRow([
                <?= $produit['id'] ?>,
                "<?= addslashes(htmlspecialchars($produit['nom'])) ?>",
                <?= $produit['prix'] ?>,
                <?= $produit['quantite'] ?>,
                "<?= addslashes(htmlspecialchars($produit['categorie'])) ?>",
                "<?= addslashes(htmlspecialchars($produit['uid_codebar'])) ?>"
                <?php if (isset($produit['poids'])): ?>
                , <?= $produit['poids'] ?? 0 ?>
                <?php endif; ?>
            ]);
            <?php endforeach; ?>
            
            // Style based on stock level
            for (let i = 2; i <= <?= count($produits) + 1 ?>; i++) {
                const quantityCell = worksheet.getCell(`D${i}`);
                const quantity = Number(quantityCell.value);
                
                if (quantity <= 0) {
                    worksheet.getRow(i).fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: 'FFFFEBED' } // Light red
                    };
                } else if (quantity <= 5) {
                    worksheet.getRow(i).fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: 'FFFFF8E7' } // Light yellow
                    };
                }
            }
            
            // Format columns
            worksheet.getColumn('C').numFmt = '#,##0.00 "DH"';
            <?php if (isset($produits[0]['poids'])): ?>
            worksheet.getColumn('G').numFmt = '#,##0.00 "kg"';
            <?php endif; ?>
            
            // Auto size columns
            worksheet.columns.forEach(column => {
                let maxLength = 0;
                column.eachCell({ includeEmpty: true }, cell => {
                    const columnWidth = cell.value ? cell.value.toString().length : 10;
                    if (columnWidth > maxLength) {
                        maxLength = columnWidth;
                    }
                });
                column.width = maxLength < 10 ? 10 : maxLength + 2;
            });
            
            // Add summary at the bottom
            worksheet.addRow([]);
            worksheet.addRow(['Rapport généré le', new Date().toLocaleString('fr-FR')]);
            worksheet.addRow(['Total produits', <?= $totalProduits ?>]);
            worksheet.addRow(['Stock critique', <?= $stockCritique ?>]);
            worksheet.addRow(['Valeur stock', <?= $valeurStock ?>, 'DH']);
            
            // Generate the Excel file
            workbook.xlsx.writeBuffer().then(buffer => {
                const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                saveAs(blob, 'Stock_Produits_' + new Date().toISOString().slice(0, 10) + '.xlsx');
            });
        }
    </script>
</body>
</html>