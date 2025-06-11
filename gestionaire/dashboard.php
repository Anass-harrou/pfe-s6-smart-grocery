<?php
session_start();
require_once 'db.php';

// Obtenir l'heure et les minutes actuelles
$heureMinute = date('H:i');

// Chemin vers ton logo (à remplacer !)
$logoPath = '../logo2.svg';

// Stats globales
$valeurStock = $pdo->query("SELECT SUM(quantite * prix) FROM produits")->fetchColumn();
$totalProduits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$stockCritique = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite <= 5")->fetchColumn();
$stockTotalQuantite = $pdo->query("SELECT SUM(quantite) FROM produits")->fetchColumn();
 $msg = $pdo->query("SELECT * FROM messages_admin ORDER BY date_publication DESC LIMIT 1")->fetch();


$dateToday = date('Y-m-d');
$sql = "SELECT COUNT(*) AS SIZE FROM messages_admin WHERE DATE(date_publication) = :dateToday";
$stmt = $pdo->prepare($sql); // hayda ? brask hhh chof wach khadama baz
$stmt->execute([':dateToday' => $dateToday]);
$messagesCount = $stmt->fetchColumn(); // ehya nada
echo $messagesCount;

// جلب الرسائل ديال اليوم
$sqlMessages = "SELECT * FROM messages_admin WHERE DATE(date_publication) = ? ORDER BY date_publication DESC";
$stmtMessages = $pdo->prepare($sqlMessages);
$stmtMessages->execute([$dateToday]);
$messagesToday = $stmtMessages->fetchAll();



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
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            background-color: rgb(86, 117, 148);
            color: white;
            padding: 1rem;
            width: 250px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1010;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand img {
            max-height: 3rem;
            margin-right: 0.75rem;
        }

        .sidebar-brand span {
            font-size: 1.25rem;
            font-weight: bold;
        }

        .sidebar-nav {
            flex-grow: 1;
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

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

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
            position: sticky;
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
            color: #007bff;
        }

        .top-bar-right .fw-bold {
            font-weight: 500 !important;
        }

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
            flex: 1 0 calc(50% - 0.75rem);
            max-width: calc(50% - 0.75rem);
        }

        @media (max-width: 767.98px) {
            .card {
                flex: 0 0 100%;
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
            color: #4e73df;
            margin-bottom: 0.5rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .card-value {
            font-size: 1.75rem;
            font-weight: bold;
            color: rgb(129, 144, 201);
        }

        .card-icon {
            font-size: 2.5rem;
            color: #dddfeb;
        }

        .bg-primary-card {
            border-left: 0.25rem solid #4e73df !important;
        }

        .bg-success-card {
            border-left: 0.25rem solid #1cc88a !important;
        }

        .conseil-box {
            background-color: #f8f9fa;
            border-left: 0.25rem solid #f6c23e !important;
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
        .annonce-admin {
    background-color: #fff3cd;
    border-left: 5px solid #ffeeba;
    padding: 1rem;
    margin: 2rem auto;
    max-width: 800px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

    </style>
</head>

<body>

<div class="d-flex">
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo">
        </div>
        <!-- hi -->
         <ul class="navbar-nav ms-auto">
  <li class="nav-item dropdown">
    <a class="nav-link position-relative" href="../mess_admin.php" id="messagesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-bell"></i>
      <?php if ($messagesCount > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
          <?= $messagesCount ?>
          <span class="visually-hidden">messages non lus</span>
        </span>
      <?php endif; ?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="messagesDropdown" style="min-width: 300px;">
      <?php if ($messagesCount == 0): ?>
        <li><span class="dropdown-item-text">Pas de messages aujourd'hui.</span></li>
      <?php else: ?>
        <?php foreach ($messagesToday as $msg): ?>
          <li>
            <div class="dropdown-item">
              <small class="text-muted"><?= date('H:i', strtotime($msg['date_publication'])) ?></small><br>
              <?= htmlspecialchars($msg['contenu']) ?>
            </div>
          </li>
          <li><hr class="dropdown-divider"></li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </li>
</ul>
<!-- hna -->
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
                <a class="nav-link" href="rapport.php">
                    <i class="fas fa-file-alt me-2"></i>
                    Rapport
                </a>
               

            </div>
            <div class="nav-item">
                <a class="nav-link" href="reception.php">
                    <i class="fas fa-bell me-2"></i>
                    Réception
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
          <!-- zyada li hna -->
          <div class="stats-section" style="margin-top: 2rem;">
    <div class="card" style="max-width: 600px; margin: auto;">
        <div class="card-body">
            <h5 class="card-title">Quantité moyenne en stock par produit</h5>
            <p id="quantite-moyenne" style="font-size: 1.5rem; color: #4e73df;">Chargement...</p>
        </div>
    </div>

   <div class="card" style="max-width: 600px; margin: 2rem auto;">
    <div class="card-body" style="display: block; text-align: center;">
        <h5 class="card-title" style="margin-bottom: 1rem;">Top 5 produits les plus disponibles en stock </h5>
        <canvas id="top5Chart" width="600" height="400"></canvas>
    </div>
    </div>

</div>

<!-- saaafi -->

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
fetch('get_stats.php')
.then(response => response.json())
.then(data => {
    // Afficher la quantité moyenne
    document.getElementById('quantite-moyenne').textContent = parseFloat(data.quantite_moyenne).toFixed(2);

    // Préparer les données pour le graphique
    const labels = data.top5.map(item => item.nom);
    const quantites = data.top5.map(item => item.quantite);

    const ctx = document.getElementById('top5Chart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Quantité en stock',
                data: quantites,
                backgroundColor: 'rgba(78, 115, 223, 0.7)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
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
    document.getElementById('quantite-moyenne').textContent = 'Erreur lors du chargement';
});
</script>




</body>
</html>
