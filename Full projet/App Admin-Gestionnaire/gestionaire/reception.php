<?php
session_start();

// Date: 2025-06-23 01:40:46
// User: Anass-harrou

// Vérifie que l'utilisateur est connecté et a le rôle de gestionnaire
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gestionaire') {
    header('Location: ../login.php');
    exit();
}

// Connexion à la base de données
require_once 'db.php';  // Use your existing db.php with PDO instead of mysqli

// Récupérer les produits pour le formulaire
$productsQuery = $pdo->query("
    SELECT id, nom, categorie, prix, quantite
    FROM produits 
    ORDER BY nom ASC
");
$products = $productsQuery->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les dernières réceptions
$recentReceptionsQuery = $pdo->query("
    SELECT r.id, r.id_produit, p.nom AS nom_produit, r.quantite_recue, 
           r.date_reception, r.date_peremption, r.qualite_validee,
           p.categorie
    FROM receptions r
    JOIN produits p ON r.id_produit = p.id
    ORDER BY r.date_reception DESC, r.id DESC
    LIMIT 10
");
$recentReceptions = $recentReceptionsQuery->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les fournisseurs
$suppliersQuery = $pdo->query("
    SELECT DISTINCT fournisseur 
    FROM receptions 
    WHERE fournisseur IS NOT NULL AND fournisseur != ''
    ORDER BY fournisseur ASC
");
$suppliers = $suppliersQuery->fetchAll(PDO::FETCH_COLUMN) ?: ['Supplier 1', 'Supplier 2', 'Supplier 3']; 
// Dummy data if no suppliers found

$message = "";
$alertClass = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $id_produit = isset($_POST['id_produit']) ? intval($_POST['id_produit']) : 0;
        $quantite = isset($_POST['quantite']) ? intval($_POST['quantite']) : 0;
        $date_peremption = isset($_POST['date_peremption']) ? $_POST['date_peremption'] : null;
        $fournisseur = isset($_POST['fournisseur']) ? $_POST['fournisseur'] : null;
        $numero_lot = isset($_POST['numero_lot']) ? $_POST['numero_lot'] : null;
        $commentaires = isset($_POST['commentaires']) ? $_POST['commentaires'] : null;
        $date_reception = date("Y-m-d");
        $qualite_validee = isset($_POST['qualite_validee']) ? 1 : 0;

        // Validation supplémentaire
        if ($id_produit <= 0) {
            throw new Exception("Veuillez sélectionner un produit valide");
        }
        
        if ($quantite <= 0) {
            throw new Exception("La quantité doit être un nombre positif");
        }
        
        if (empty($date_peremption)) {
            throw new Exception("La date de péremption est requise");
        }

        // Début de la transaction
        $pdo->beginTransaction();

        // 1. Enregistrer la réception
        $stmt = $pdo->prepare("
            INSERT INTO receptions (id_produit, quantite_recue, date_reception, date_peremption, qualite_validee, fournisseur, numero_lot, commentaires)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_produit, $quantite, $date_reception, $date_peremption, 
            $qualite_validee, $fournisseur, $numero_lot, $commentaires
        ]);

        // 2. Mettre à jour le stock du produit
        $updateStmt = $pdo->prepare("UPDATE produits SET quantite = quantite + ? WHERE id = ?");
        $updateStmt->execute([$quantite, $id_produit]);

        // 3. Ajouter un événement au calendrier si la fonction existe
        if (function_exists('addCalendarEvent')) {
            $productName = '';
            foreach ($products as $product) {
                if ($product['id'] == $id_produit) {
                    $productName = $product['nom'];
                    break;
                }
            }
            addCalendarEvent(
                "Réception: $productName", 
                "Réception de $quantite unités de $productName, Lot: $numero_lot", 
                $date_reception
            );
        }

        // Valider la transaction
        $pdo->commit();

        $message = "Produit enregistré et stock mis à jour avec succès.";
        $alertClass = "alert-success";
        
        // Récupérer les dernières réceptions après l'ajout
        $recentReceptionsQuery = $pdo->query("
            SELECT r.id, r.id_produit, p.nom AS nom_produit, r.quantite_recue, 
                   r.date_reception, r.date_peremption, r.qualite_validee,
                   p.categorie
            FROM receptions r
            JOIN produits p ON r.id_produit = p.id
            ORDER BY r.date_reception DESC, r.id DESC
            LIMIT 10
        ");
        $recentReceptions = $recentReceptionsQuery->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $message = "Erreur: " . $e->getMessage();
        $alertClass = "alert-danger";
    }
}

// Récupérer les statistiques pour le tableau de bord
$stats = [
    'total_receptions' => $pdo->query("SELECT COUNT(*) FROM receptions")->fetchColumn(),
    'products_count' => $pdo->query("SELECT COUNT(DISTINCT id_produit) FROM receptions")->fetchColumn(),
    'total_quantity' => $pdo->query("SELECT SUM(quantite_recue) FROM receptions")->fetchColumn() ?: 0,
    'month_receptions' => $pdo->query("SELECT COUNT(*) FROM receptions WHERE MONTH(date_reception) = MONTH(CURRENT_DATE()) AND YEAR(date_reception) = YEAR(CURRENT_DATE())")->fetchColumn()
];

// Format date
$today = new DateTime();
$formattedDate = $today->format('d/m/Y');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réception Produits | Smart Grocery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <style>
        :root {
            --primary-bg: rgb(200, 229, 247);
            --sidebar-bg: rgb(86, 117, 148);
            --primary-text: #333;
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --white: #fff;
            --card-border-radius: 0.75rem;
            --box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        body {
            background-color: var(--primary-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--primary-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, #224abe 100%);
            color: white;
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
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-brand img {
            max-width: 120px;
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

        .container {
            max-width: 1200px;
            margin-left: 280px;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .page-title {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.75rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-title i {
            color: var(--primary-color);
        }

        .page-date {
            font-size: 1rem;
            color: #6c757d;
            font-weight: normal;
        }

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
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card:nth-child(2) {
            border-left-color: var(--success-color);
        }

        .stat-card:nth-child(3) {
            border-left-color: var(--warning-color);
        }

        .stat-card:nth-child(4) {
            border-left-color: var(--info-color);
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

        .stat-card:nth-child(1) .stat-card-icon {
            color: var(--primary-color);
        }

        .stat-card:nth-child(2) .stat-card-icon {
            color: var(--success-color);
        }

        .stat-card:nth-child(3) .stat-card-icon {
            color: var(--warning-color);
        }

        .stat-card:nth-child(4) .stat-card-icon {
            color: var(--info-color);
        }

        .stat-card-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .stat-card-title {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 500;
        }

        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .card {
            background-color: #fff;
            border: none;
            border-radius: 0.75rem;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
        }
        
        .card-header {
            background-color: rgba(78, 115, 223, 0.05);
            border-bottom: 1px solid rgba(78, 115, 223, 0.1);
            font-weight: 600;
            color: #2c3e50;
            padding: 1rem 1.5rem;
        }

        .card-header i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-floating > label {
            padding-left: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #3a57ca;
            border-color: #3a57ca;
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .table {
            vertical-align: middle;
        }

        .table > :not(:first-child) {
            border-top: 2px solid currentColor;
        }

        .table th {
            background-color: rgba(248, 249, 250, 1);
            color: #2c3e50;
            font-weight: 600;
        }

        .badge-status {
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            border-radius: 0.25rem;
            font-weight: 600;
        }

        .quality-yes {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .quality-no {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }

        @media (max-width: 1200px) {
            .content-wrapper {
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
        }
        
        .divider {
            margin: 1.5rem 0;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .form-floating label.required::after {
            content: "*";
            color: var(--danger-color);
            margin-left: 0.25rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .table-reception td {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .thumbnail-preview {
            width: 32px;
            height: 32px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 0.5rem;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="../tablet/images/logo.png" alt="logo">
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
                <a class="nav-link" href="rapport.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Rapport</span>
                </a>
            </div>

            <div class="nav-item">
                <a class="nav-link active" href="reception.php">
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
        <header class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-truck-loading"></i> Réception des produits
                </h1>
                <div class="page-date">
                    <i class="far fa-calendar-alt me-1"></i> <?= $formattedDate ?>
                </div>
            </div>
            <div>
                <a href="stock.php" class="btn btn-outline-primary">
                    <i class="fas fa-box me-1"></i> Retour au stock
                </a>
            </div>
        </header>

        <?php if (!empty($message)): ?>
        <div class="alert <?= $alertClass ?> alert-dismissible fade show" role="alert">
            <i class="fas <?= $alertClass === 'alert-success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="stats-container fade-in">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-title">Réceptions totales</div>
                    <i class="fas fa-truck-loading stat-card-icon"></i>
                </div>
                <div class="stat-card-value"><?= number_format($stats['total_receptions']) ?></div>
                <div class="stat-card-footer">Enregistrements</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-title">Produits différents</div>
                    <i class="fas fa-box-open stat-card-icon"></i>
                </div>
                <div class="stat-card-value"><?= number_format($stats['products_count']) ?></div>
                <div class="stat-card-footer">Types de produits</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-title">Unités reçues</div>
                    <i class="fas fa-boxes stat-card-icon"></i>
                </div>
                <div class="stat-card-value"><?= number_format($stats['total_quantity']) ?></div>
                <div class="stat-card-footer">Quantité totale</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-title">Ce mois-ci</div>
                    <i class="fas fa-calendar-check stat-card-icon"></i>
                </div>
                <div class="stat-card-value"><?= number_format($stats['month_receptions']) ?></div>
                <div class="stat-card-footer">Réceptions</div>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- Formulaire de réception -->
            <div class="card fade-in">
                <div class="card-header">
                    <i class="fas fa-plus-circle"></i> Nouvelle réception
                </div>
                <div class="card-body">
                    <form method="POST" id="receptionForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="id_produit" name="id_produit" required>
                                        <option value="">Sélectionner un produit</option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?= $product['id'] ?>" data-price="<?= $product['prix'] ?>" data-category="<?= htmlspecialchars($product['categorie']) ?>">
                                                <?= htmlspecialchars($product['nom']) ?> (Stock: <?= $product['quantite'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="id_produit" class="required">Produit</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="quantite" name="quantite" min="1" required>
                                    <label for="quantite" class="required">Quantité reçue</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="date_peremption" name="date_peremption" required>
                                    <label for="date_peremption" class="required">Date de péremption</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="numero_lot" name="numero_lot" placeholder="Ex: LOT-2025-001">
                                    <label for="numero_lot">Numéro de lot</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="fournisseur" name="fournisseur">
                                        <option value="">Choisir un fournisseur</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?= htmlspecialchars($supplier) ?>"><?= htmlspecialchars($supplier) ?></option>
                                        <?php endforeach; ?>
                                        <option value="new">Nouveau fournisseur...</option>
                                    </select>
                                    <label for="fournisseur">Fournisseur</label>
                                </div>
                                <div class="mb-3 d-none" id="newSupplierField">
                                    <input type="text" class="form-control" id="newSupplier" placeholder="Nom du nouveau fournisseur">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3 mt-3">
                                    <input class="form-check-input" type="checkbox" id="qualite_validee" name="qualite_validee" checked>
                                    <label class="form-check-label" for="qualite_validee">Qualité validée</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <textarea class="form-control" id="commentaires" name="commentaires" style="height: 100px"></textarea>
                                    <label for="commentaires">Commentaires</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="divider">
                                <div class="d-flex justify-content-between">
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i> Réinitialiser
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Enregistrer la réception
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tableau des dernières réceptions -->
            <div class="card fade-in">
                <div class="card-header">
                    <i class="fas fa-history"></i> Dernières réceptions
                </div>
                <div class="card-body">
                    <?php if (count($recentReceptions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-reception">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Quantité</th>
                                        <th>Date</th>
                                        <th>Péremption</th>
                                        <th>Qualité</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReceptions as $reception): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($reception['nom_produit']) ?>
                                            </td>
                                            <td><?= $reception['quantite_recue'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($reception['date_reception'])) ?></td>
                                            <td><?= date('d/m/Y', strtotime($reception['date_peremption'])) ?></td>
                                            <td>
                                                <span class="badge-status <?= $reception['qualite_validee'] ? 'quality-yes' : 'quality-no' ?>">
                                                    <?= $reception['qualite_validee'] ? 'Validée' : 'Non validée' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-truck-loading"></i>
                            <h4>Aucune réception enregistrée</h4>
                            <p>Les réceptions récentes apparaîtront ici une fois créées.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set min date for date input to today
            const todayStr = new Date().toISOString().substr(0, 10);
            document.getElementById('date_peremption').min = todayStr;
            
            // Set default date to 1 year from now
            const nextYear = new Date();
            nextYear.setFullYear(nextYear.getFullYear() + 1);
            document.getElementById('date_peremption').value = nextYear.toISOString().substr(0, 10);
            
            // Handle new supplier option
            const fournisseurSelect = document.getElementById('fournisseur');
            const newSupplierField = document.getElementById('newSupplierField');
            const newSupplierInput = document.getElementById('newSupplier');
            
            fournisseurSelect.addEventListener('change', function() {
                if (this.value === 'new') {
                    newSupplierField.classList.remove('d-none');
                    newSupplierInput.setAttribute('required', 'required');
                    newSupplierInput.focus();
                } else {
                    newSupplierField.classList.add('d-none');
                    newSupplierInput.removeAttribute('required');
                }
            });
            
            // Update form before submit to include new supplier
            document.getElementById('receptionForm').addEventListener('submit', function(e) {
                if (fournisseurSelect.value === 'new' && newSupplierInput.value.trim() !== '') {
                    // Create new option
                    const newOption = document.createElement('option');
                    newOption.value = newSupplierInput.value.trim();
                    newOption.text = newSupplierInput.value.trim();
                    newOption.selected = true;
                    
                    // Add to select and update value
                    fournisseurSelect.add(newOption);
                    fournisseurSelect.value = newSupplierInput.value.trim();
                }
            });
            
            // Show today's date
            document.querySelectorAll('.current-date').forEach(function(el) {
                el.textContent = new Date().toLocaleDateString('fr-FR', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>