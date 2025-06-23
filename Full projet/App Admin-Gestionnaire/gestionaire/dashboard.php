<?php
session_start();
require_once 'db.php';

// Date: 2025-06-23 01:15:33
// User: Anass-harrou

// Check if the commandes table exists, if not create it
try {
    $checkCommandes = $pdo->query("SHOW TABLES LIKE 'commandes'");
    if ($checkCommandes->rowCount() == 0) {
        // Auto-create the commandes table since it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS `commandes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `reference` varchar(20) NOT NULL,
          `user_id` int(11) NOT NULL,
          `montant` decimal(10,2) NOT NULL DEFAULT 0.00,
          `status` enum('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
          `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `date_modification` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          `mode_paiement` varchar(50) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (PDOException $e) {
    // Log the error but continue
    error_log("Error checking/creating commandes table: " . $e->getMessage());
}

// Check if messages_admin exists
try {
    $checkMessages = $pdo->query("SHOW TABLES LIKE 'messages_admin'");
    if ($checkMessages->rowCount() == 0) {
        // Auto-create the messages_admin table since it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS `messages_admin` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `contenu` text NOT NULL,
          `date_publication` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `is_read` tinyint(1) NOT NULL DEFAULT 0,
          `sender` varchar(50) DEFAULT NULL,
          `importance` enum('low','medium','high') DEFAULT 'medium',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Add some initial messages
        $pdo->exec("INSERT INTO `messages_admin` (`contenu`, `date_publication`, `is_read`, `sender`, `importance`) VALUES
        ('Arrivage de nouveaux produits prévu pour demain à 10h00. Prévoir espace de stockage.', CURRENT_TIMESTAMP, 0, 'Système', 'high'),
        ('Rappel: Inventaire mensuel à effectuer avant la fin de semaine.', CURRENT_TIMESTAMP, 0, 'Système', 'medium')");
    }
} catch (PDOException $e) {
    error_log("Error checking/creating messages_admin table: " . $e->getMessage());
}

// Current time
$heureMinute = date('H:i');

// Stats (with error handling)
try {
    $valeurStock = $pdo->query("SELECT SUM(quantite * prix) FROM produits")->fetchColumn() ?? 0;
    $totalProduits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn() ?? 0;
    $stockCritique = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite <= 5")->fetchColumn() ?? 0;
    $stockTotalQuantite = $pdo->query("SELECT SUM(quantite) FROM produits")->fetchColumn() ?? 0;
} catch (PDOException $e) {
    error_log("Error getting stock stats: " . $e->getMessage());
    $valeurStock = $totalProduits = $stockCritique = $stockTotalQuantite = 0;
}

// Latest message - safely
try {
    $stmt = $pdo->query("SELECT * FROM messages_admin ORDER BY date_publication DESC LIMIT 1");
    $msg = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error getting latest message: " . $e->getMessage());
    $msg = null;
}

// Today's messages - safely
$dateToday = date('Y-m-d');
try {
    $sql = "SELECT COUNT(*) AS SIZE FROM messages_admin WHERE DATE(date_publication) = :dateToday AND is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':dateToday' => $dateToday]);
    $messagesCount = $stmt->fetchColumn() ?? 0;
    
    // Get today's messages
    $sqlMessages = "SELECT * FROM messages_admin WHERE DATE(date_publication) = ? ORDER BY date_publication DESC";
    $stmtMessages = $pdo->prepare($sqlMessages);
    $stmtMessages->execute([$dateToday]);
    $messagesToday = $stmtMessages->fetchAll();
} catch (PDOException $e) {
    error_log("Error counting/fetching messages: " . $e->getMessage());
    $messagesCount = 0;
    $messagesToday = [];
}

// Check out of stock products
try {
    $outOfStock = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite = 0")->fetchColumn() ?? 0;
    $lowStock = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite > 0 AND quantite <= 5")->fetchColumn() ?? 0;
    
    // Get low stock products
    $lowStockProducts = $pdo->query("SELECT id, nom, quantite FROM produits WHERE quantite <= 5 ORDER BY quantite ASC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    error_log("Error checking stock levels: " . $e->getMessage());
    $outOfStock = $lowStock = 0;
    $lowStockProducts = [];
}

// Get top selling products (from achat_produits table)
try {
    $topSelling = $pdo->query("
        SELECT p.nom, SUM(ap.quantite) as total_sold 
        FROM achat_produits ap
        JOIN produits p ON ap.id_produit = p.id
        GROUP BY ap.id_produit
        ORDER BY total_sold DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting top selling products: " . $e->getMessage());
    $topSelling = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Gestionnaire - Smart Grocery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-light: #74ADFF;
            --secondary-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
            --dark-color: #5a5c69;
            --light-bg: #e3f2fd;
            --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #e3f2fd;
            color: #5a5c69;
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #4e73df 0%, #224abe 100%);
            color: white;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1010;
            box-shadow: rgba(0, 0, 0, 0.15) 2px 0px 5px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }
        
        .sidebar-brand {
            padding: 1.5rem 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-brand img {
            width: 120px;
            height: auto;
            max-height: 60px;
        }
        
        .sidebar-nav {
            flex-grow: 1;
            padding: 1rem 0;
            overflow-y: auto;
        }
        
        .sidebar .nav-item {
            padding: 0.2rem 1rem;
        }
        
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem;
            color: rgba(255, 255, 255, 0.8);
            border-radius: 0.35rem;
            white-space: nowrap;
            transition: all 0.2s ease-in-out;
        }
        
        .sidebar .nav-link i {
            flex-shrink: 0;
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
            text-align: center;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            width: calc(100% - 250px);
            transition: all 0.3s;
        }
        
        .top-bar {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--card-shadow);
        }
        
        .top-bar-left {
            display: flex;
            align-items: center;
        }
        
        .date-display, .time-display {
            display: flex;
            align-items: center;
        }
        
        .date-display i, .time-display i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .top-bar-separator {
            height: 1.5rem;
            border-left: 1px solid #e3e6f0;
            margin: 0 1rem;
        }
        
        .user-welcome {
            display: flex;
            align-items: center;
        }
        
        .user-welcome i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .alert-box {
            background-color: white;
            border-left: 0.25rem solid var(--warning-color);
            border-radius: 0.5rem;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            box-shadow: var(--card-shadow);
        }
        
        .alert-box i {
            font-size: 1.5rem;
            margin-right: 1rem;
            color: var(--warning-color);
        }
        
        .alert-box-content {
            font-weight: 500;
            color: #856404;
        }
        
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 0.5rem;
            border-left: 0.25rem solid var(--primary-color);
            padding: 1.5rem;
            position: relative;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.primary {
            border-left-color: var(--primary-color);
        }
        
        .stat-card.success {
            border-left-color: var(--secondary-color);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger-color);
        }
        
        .stat-card-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-card-title {
            text-transform: uppercase;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            letter-spacing: 0.05rem;
        }
        
        .stat-card.success .stat-card-title {
            color: var(--secondary-color);
        }
        
        .stat-card.warning .stat-card-title {
            color: var(--warning-color);
        }
        
        .stat-card.danger .stat-card-title {
            color: var(--danger-color);
        }
        
        .stat-card-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .stat-card-icon {
            font-size: 2rem;
            color: #dddfeb;
        }
        
        .content-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chart-container {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            height: 100%;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .chart-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }
        
        .chart-content {
            position: relative;
            height: 300px;
        }
        
        .table-container {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            height: 100%;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-align: center;
            display: inline-block;
        }
        
        .status-ok {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--secondary-color);
        }
        
        .status-warning {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
        }
        
        .status-danger {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f8f9fc;
            border-top: none;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            color: #4e73df;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: var(--card-shadow);
            border-radius: 0.35rem;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
        }
        
        .progress {
            height: 0.5rem;
        }
        
        @media (max-width: 991.98px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .nav-link span,
            .sidebar-brand span {
                display: none;
            }
            
            .sidebar .nav-link i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .stat-cards {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
            
            .content-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 575.98px) {
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .top-bar-left {
                margin-bottom: 0.5rem;
            }
            
            .stat-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="../tablet/images/logo.png" alt="Smart Grocery" class="img-fluid">
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-item">
                <a class="nav-link active" href="dashboard.php">
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
                    <i class="fas fa-chart-bar"></i>
                    <span>Rapport</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a class="nav-link" href="reception.php">
                    <i class="fas fa-truck"></i>
                    <span>Réception</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a class="nav-link" href="calendar.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendrier</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <div class="nav-item">
                <a class="nav-link position-relative" href="../mess_admin.php">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($messagesCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $messagesCount ?>
                    </span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="date-display">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?= date('d/m/Y') ?></span>
                </div>
                
                <div class="top-bar-separator"></div>
                
                <div class="time-display">
                    <i class="fas fa-clock"></i>
                    <span><?= $heureMinute ?></span>
                </div>
            </div>
            
            <div class="user-welcome">
                <i class="fas fa-user-circle"></i>
                <span class="fw-bold">Bienvenue, Gestionnaire</span>
            </div>
        </div>
        
        <div class="alert-box">
            <i class="fas fa-lightbulb"></i>
            <div class="alert-box-content">
                Conseil ! : Pensez à vérifier les produits proches de la rupture de stock avant midi.
            </div>
        </div>
        
        <div class="stat-cards">
            <div class="stat-card primary">
                <div class="stat-card-content">
                    <div>
                        <div class="stat-card-title">Nombre total des produits</div>
                        <div class="stat-card-value"><?= number_format($totalProduits) ?></div>
                    </div>
                    <i class="fas fa-boxes stat-card-icon"></i>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-card-content">
                    <div>
                        <div class="stat-card-title">Valeur du stock</div>
                        <div class="stat-card-value"><?= number_format($valeurStock, 2, ',', ' ') ?> dhs</div>
                    </div>
                    <i class="fas fa-dollar-sign stat-card-icon"></i>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-card-content">
                    <div>
                        <div class="stat-card-title">Stock faible</div>
                        <div class="stat-card-value"><?= number_format($lowStock) ?></div>
                    </div>
                    <i class="fas fa-exclamation-triangle stat-card-icon"></i>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-card-content">
                    <div>
                        <div class="stat-card-title">Produits épuisés</div>
                        <div class="stat-card-value"><?= number_format($outOfStock) ?></div>
                    </div>
                    <i class="fas fa-ban stat-card-icon"></i>
                </div>
            </div>
        </div>
        
        <div class="content-row">
            <div class="chart-container">
                <div class="chart-header">
                    <h5 class="chart-title">Quantité moyenne en stock par produit</h5>
                </div>
                <div class="chart-content">
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="text-center">
                            <h2 id="quantite-moyenne" class="display-4 fw-bold text-primary">...</h2>
                            <p class="text-muted">produits/référence</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <h5 class="chart-title">Top 5 produits les plus disponibles</h5>
                </div>
                <div class="chart-content">
                    <canvas id="top5Chart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="content-row">
            <div class="table-container">
                <div class="chart-header">
                    <h5 class="chart-title">Produits à stock critique</h5>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lowStockProducts)): ?>
                            <tr>
                                <td colspan="3" class="text-center">Aucun produit à stock critique</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['nom']) ?></td>
                                    <td><?= $product['quantite'] ?></td>
                                    <td>
                                        <?php if ($product['quantite'] == 0): ?>
                                            <span class="status-badge status-danger">Épuisé</span>
                                        <?php elseif ($product['quantite'] <= 2): ?>
                                            <span class="status-badge status-danger">Critique</span>
                                        <?php else: ?>
                                            <span class="status-badge status-warning">Faible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="table-container">
                <div class="chart-header">
                    <h5 class="chart-title">Produits les plus vendus</h5>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Unités vendues</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topSelling)): ?>
                            <tr>
                                <td colspan="2" class="text-center">Aucune donnée disponible</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($topSelling as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['nom']) ?></td>
                                    <td><?= $product['total_sold'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    fetch('get_stats.php')
        .then(response => response.json())
        .then(data => {
            // Display average quantity
            document.getElementById('quantite-moyenne').textContent = parseFloat(data.quantite_moyenne).toFixed(2);
            
            // Prepare chart data
            const labels = data.top5.map(item => item.nom);
            const quantities = data.top5.map(item => item.quantite);
            
            // Create chart
            const ctx = document.getElementById('top5Chart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Quantité en stock',
                        data: quantities,
                        backgroundColor: 'rgba(78, 115, 223, 0.7)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            },
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
                        }
                    }
                }
            });
        })
        .catch(err => {
            console.error('Erreur en récupérant les stats:', err);
            document.getElementById('quantite-moyenne').textContent = 'Erreur';
        });
    </script>
</body>
</html>