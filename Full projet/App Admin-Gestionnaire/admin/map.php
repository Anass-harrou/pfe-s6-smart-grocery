<?php
/**
 * Smart Cart Tracking Map Interface
 * 
 * Date: 2025-06-23 05:26:20
 * Author: Anass-harrou
 * 
 * This file provides real-time tracking functionality for smart carts
 * in the store, displaying their locations, status, and contents.
 */

// Start session
session_start();

// Vérification simplifiée de l'authentification (sans require_once)


// Configuration de la base de données directement dans ce fichier
$db_host = 'localhost';
$db_name = 'smartgrocery';
$db_user = 'root';
$db_pass = '';

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fonction de vérification du rôle admin
function isAdmin() {
    // Dans un environnement de développement, on retourne true
    // En production, vérifiez le rôle de l'utilisateur dans la base de données
    return true;
}

// Get all active carts
$query = "SELECT c.cart_id, c.status, c.battery_level, c.last_seen, 
          c.position_x, c.position_y, c.zone_id, z.zone_name, 
          IFNULL(u.username, 'Non assigné') as username, 
          IFNULL(u.user_id, 0) as user_id,
          (SELECT SUM(p.price * ci.quantity) FROM cart_items ci 
           JOIN products p ON ci.product_id = p.product_id 
           WHERE ci.cart_id = c.cart_id) as total_amount
          FROM carts c
          LEFT JOIN users u ON c.assigned_user_id = u.user_id
          LEFT JOIN store_zones z ON c.zone_id = z.zone_id
          ORDER BY c.status DESC";

// Pour éviter l'erreur en développement, on simule des données
$carts = [];
$zones = [];
$alerts = [];

// Simulation de données pour développement
if (!isset($_GET['action'])) {
    // Créer des zones simulées
    $zones = [
        [
            'zone_id' => 1, 
            'zone_name' => 'Entrée', 
            'x_coord' => 50, 
            'y_coord' => 50,
            'width' => 150,
            'height' => 100,
            'color' => '#e3f2fd'
        ],
        [
            'zone_id' => 2, 
            'zone_name' => 'Produits Frais', 
            'x_coord' => 250, 
            'y_coord' => 50,
            'width' => 200,
            'height' => 200,
            'color' => '#e8f5e9'
        ],
        [
            'zone_id' => 3, 
            'zone_name' => 'Épicerie', 
            'x_coord' => 50, 
            'y_coord' => 200,
            'width' => 150,
            'height' => 300,
            'color' => '#fff3e0'
        ],
        [
            'zone_id' => 4, 
            'zone_name' => 'Électronique', 
            'x_coord' => 500, 
            'y_coord' => 50,
            'width' => 180,
            'height' => 150,
            'color' => '#e0f7fa'
        ],
        [
            'zone_id' => 5, 
            'zone_name' => 'Caisses', 
            'x_coord' => 700, 
            'y_coord' => 50,
            'width' => 220,
            'height' => 120,
            'color' => '#f3e5f5'
        ]
    ];
    
    // Créer des paniers simulés
    $carts = [
        [
            'cart_id' => 1,
            'status' => 'active',
            'battery_level' => 85,
            'last_seen' => '2025-06-23 05:25:10',
            'position_x' => 100,
            'position_y' => 100,
            'zone_id' => 1,
            'zone_name' => 'Entrée',
            'username' => 'client001',
            'user_id' => 101,
            'total_amount' => 235.50
        ],
        [
            'cart_id' => 2,
            'status' => 'active',
            'battery_level' => 65,
            'last_seen' => '2025-06-23 05:24:30',
            'position_x' => 300,
            'position_y' => 150,
            'zone_id' => 2,
            'zone_name' => 'Produits Frais',
            'username' => 'client002',
            'user_id' => 102,
            'total_amount' => 475.75
        ],
        [
            'cart_id' => 3,
            'status' => 'idle',
            'battery_level' => 100,
            'last_seen' => '2025-06-23 05:00:00',
            'position_x' => 750,
            'position_y' => 80,
            'zone_id' => 5,
            'zone_name' => 'Caisses',
            'username' => 'Non assigné',
            'user_id' => 0,
            'total_amount' => 0
        ],
        [
            'cart_id' => 4,
            'status' => 'active',
            'battery_level' => 15,
            'last_seen' => '2025-06-23 05:25:50',
            'position_x' => 550,
            'position_y' => 120,
            'zone_id' => 4,
            'zone_name' => 'Électronique',
            'username' => 'client005',
            'user_id' => 105,
            'total_amount' => 1275.00
        ],
        [
            'cart_id' => 5,
            'status' => 'maintenance',
            'battery_level' => 5,
            'last_seen' => '2025-06-23 04:30:00',
            'position_x' => 780,
            'position_y' => 90,
            'zone_id' => 5,
            'zone_name' => 'Caisses',
            'username' => 'Non assigné',
            'user_id' => 0,
            'total_amount' => 0
        ]
    ];
    
    // Créer des alertes simulées
    $alerts = [
        [
            'cart_id' => 4,
            'type' => 'battery',
            'message' => 'Niveau de batterie critique (15%)',
            'created_at' => '2025-06-23 05:20:12'
        ],
        [
            'cart_id' => 2,
            'type' => 'zone',
            'message' => 'Sortie de zone autorisée détectée',
            'created_at' => '2025-06-23 05:15:30'
        ],
        [
            'cart_id' => 3,
            'type' => 'inactive',
            'message' => 'Panier inactif depuis 25 minutes',
            'created_at' => '2025-06-23 04:55:00'
        ]
    ];
}

// Process ajax requests for real-time updates
if (isset($_GET['action']) && $_GET['action'] == 'update_carts') {
    header('Content-Type: application/json');
    
    // En développement, mélangez un peu les positions pour simuler le mouvement
    foreach ($carts as &$cart) {
        if ($cart['status'] == 'active') {
            $cart['position_x'] += rand(-10, 10);
            $cart['position_y'] += rand(-10, 10);
            $cart['last_seen'] = date('Y-m-d H:i:s');
        }
    }
    
    echo json_encode([
        'carts' => $carts,
        'alerts' => $alerts
    ]);
    exit();
}

// Process cart details request
if (isset($_GET['get_cart_details']) && is_numeric($_GET['get_cart_details'])) {
    $cart_id = $_GET['get_cart_details'];
    
    // En développement, simulons des produits dans le panier
    $items = [];
    
    if ($cart_id == 1) {
        $items = [
            [
                'item_id' => 1,
                'product_name' => 'Lait frais',
                'price' => 25.00,
                'quantity' => 2,
                'subtotal' => 50.00,
                'added_at' => '2025-06-23 05:10:00'
            ],
            [
                'item_id' => 2,
                'product_name' => 'Pain complet',
                'price' => 8.50,
                'quantity' => 1,
                'subtotal' => 8.50,
                'added_at' => '2025-06-23 05:12:00'
            ],
            [
                'item_id' => 3,
                'product_name' => 'Pommes (1kg)',
                'price' => 15.00,
                'quantity' => 2,
                'subtotal' => 30.00,
                'added_at' => '2025-06-23 05:15:00'
            ],
            [
                'item_id' => 4,
                'product_name' => 'Café moulu',
                'price' => 65.00,
                'quantity' => 1,
                'subtotal' => 65.00,
                'added_at' => '2025-06-23 05:20:00'
            ]
        ];
    } elseif ($cart_id == 2) {
        $items = [
            [
                'item_id' => 5,
                'product_name' => 'Viande hachée (500g)',
                'price' => 60.00,
                'quantity' => 1,
                'subtotal' => 60.00,
                'added_at' => '2025-06-23 05:10:00'
            ],
            [
                'item_id' => 6,
                'product_name' => 'Fromage',
                'price' => 45.00,
                'quantity' => 1,
                'subtotal' => 45.00,
                'added_at' => '2025-06-23 05:15:00'
            ]
        ];
    } elseif ($cart_id == 4) {
        $items = [
            [
                'item_id' => 7,
                'product_name' => 'Écouteurs Bluetooth',
                'price' => 350.00,
                'quantity' => 1,
                'subtotal' => 350.00,
                'added_at' => '2025-06-23 05:05:00'
            ],
            [
                'item_id' => 8,
                'product_name' => 'Chargeur USB-C',
                'price' => 125.00,
                'quantity' => 1,
                'subtotal' => 125.00,
                'added_at' => '2025-06-23 05:10:00'
            ]
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['items' => $items]);
    exit();
}

// Process remote cart control
if (isset($_POST['action']) && $_POST['action'] == 'control_cart' && isset($_POST['cart_id'])) {
    $cart_id = $_POST['cart_id'];
    $command = $_POST['command'] ?? '';
    
    // Security check
    if (!in_array($command, ['lock', 'unlock', 'send_message', 'reboot'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid command']);
        exit();
    }
    
    // Simulated success response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Commande envoyée au panier']);
    exit();
}

$pageTitle = "Suivi des Paniers Intelligents";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Administration</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        .map-container {
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
        }
        
        #store-map {
            transition: transform 0.3s;
            transform-origin: 0 0;
        }
        
        .cart-icon {
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .cart-icon:hover {
            transform: scale(1.2);
        }
        
        .map-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: #6c757d;
        }
        
        .card-header .card-title {
            margin-bottom: 0;
        }
        
        .sidebar-dark-primary {
            background-color: #343a40;
        }
        
        .content-wrapper {
            background-color: #f4f6f9;
            padding: 20px;
        }
        
        .content-header {
            padding-bottom: 0;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Main Header -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="index.php" class="nav-link">Accueil</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="#" class="nav-link">Support</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="index.php" class="brand-link">
            <i class="fas fa-shopping-cart brand-image img-circle elevation-3" style="opacity: .8"></i>
            <span class="brand-text font-weight-light">Smart Grocery</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <i class="fas fa-user-circle fa-2x text-light"></i>
                </div>
                <div class="info">
                    <a href="#" class="d-block">Anass-harrou</a>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Tableau de bord</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Clients</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link">
                            <i class="nav-icon fas fa-box"></i>
                            <p>Produits</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="map.php" class="nav-link active">
                            <i class="nav-icon fas fa-map-marker-alt"></i>
                            <p>Suivi des Paniers</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Paramètres</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Suivi des Paniers Intelligents</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                        <li class="breadcrumb-item active">Suivi des Paniers</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-9">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Cartographie du Magasin</h3>
                            <div class="card-tools">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-tool btn-sm" id="zoom-in">
                                        <i class="fas fa-search-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-tool btn-sm" id="zoom-out">
                                        <i class="fas fa-search-minus"></i>
                                    </button>
                                    <button type="button" class="btn btn-tool btn-sm" id="reset-view">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="map-container">
                                <div id="store-map" style="width: 100%; height: 600px; position: relative; border: 1px solid #ddd; overflow: hidden;">
                                    <!-- Store map will be rendered here by JavaScript -->
                                    <!-- This is a placeholder for the SVG/Canvas map -->
                                    <div class="map-loading">Chargement de la carte...</div>
                                </div>
                            </div>
                            <div class="map-legend mt-3">
                                <div class="row">
                                    <div class="col">
                                        <span class="badge bg-success">● En utilisation</span>
                                        <span class="badge bg-warning">● Inactif</span>
                                        <span class="badge bg-danger">● Alerte</span>
                                        <span class="badge bg-secondary">● En maintenance</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Filtres</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>État des paniers</label>
                                <select class="form-control" id="status-filter">
                                    <option value="all">Tous</option>
                                    <option value="active">En utilisation</option>
                                    <option value="idle">Disponibles</option>
                                    <option value="maintenance">En maintenance</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Zone du magasin</label>
                                <select class="form-control" id="zone-filter">
                                    <option value="all">Toutes les zones</option>
                                    <?php foreach ($zones as $zone): ?>
                                    <option value="<?= $zone['zone_id'] ?>"><?= htmlspecialchars($zone['zone_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Recherche par ID</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="cart-search" placeholder="ID du panier">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="search-btn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="show-heatmap">
                                <label class="form-check-label" for="show-heatmap">Afficher carte de chaleur</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" id="cart-details-card" style="display: none;">
                        <div class="card-header bg-primary">
                            <h3 class="card-title">Détails du Panier</h3>
                        </div>
                        <div class="card-body">
                            <div id="cart-details-content">
                                <!-- Cart details will be loaded here by JavaScript -->
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group btn-block">
                                <button class="btn btn-sm btn-warning" id="lock-cart">
                                    <i class="fas fa-lock"></i> Verrouiller
                                </button>
                                <button class="btn btn-sm btn-info" id="send-message">
                                    <i class="fas fa-comment"></i> Message
                                </button>
                                <button class="btn btn-sm btn-danger" id="cart-help">
                                    <i class="fas fa-medkit"></i> Assistance
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Alertes Récentes</h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="products-list product-list-in-card pl-2 pr-2" id="alerts-list">
                                <?php if (empty($alerts)): ?>
                                <li class="item">
                                    <div class="product-info">
                                        <span class="text-muted">Aucune alerte récente</span>
                                    </div>
                                </li>
                                <?php else: ?>
                                    <?php foreach ($alerts as $alert): ?>
                                    <li class="item">
                                        <div class="product-info">
                                            <a href="javascript:void(0)" class="product-title">
                                                Panier #<?= $alert['cart_id'] ?>
                                                <?php if ($alert['type'] == 'battery'): ?>
                                                <span class="badge badge-warning float-right">Batterie faible</span>
                                                <?php elseif ($alert['type'] == 'zone'): ?>
                                                <span class="badge badge-danger float-right">Zone non autorisée</span>
                                                <?php elseif ($alert['type'] == 'inactive'): ?>
                                                <span class="badge badge-secondary float-right">Inactif</span>
                                                <?php endif; ?>
                                            </a>
                                            <span class="product-description">
                                                <?= htmlspecialchars($alert['message']) ?>
                                                <small class="text-muted">
                                                    <?= date('H:i', strtotime($alert['created_at'])) ?>
                                                </small>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Statistiques en temps réel</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="row">
                                <div class="col-lg-3 col-6">
                                    <div class="small-box bg-info">
                                        <div class="inner">
                                            <h3 id="active-carts-count">0</h3>
                                            <p>Paniers en utilisation</p>
                                        </div>
                                        <div class="icon">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-6">
                                    <div class="small-box bg-success">
                                        <div class="inner">
                                            <h3 id="avg-cart-time">0</h3>
                                            <p>Durée moyenne (min)</p>
                                        </div>
                                        <div class="icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-6">
                                    <div class="small-box bg-warning">
                                        <div class="inner">
                                            <h3 id="avg-cart-amount">0</h3>
                                            <p>Panier moyen (MAD)</p>
                                        </div>
                                        <div class="icon">
                                            <i class="fas fa-money-bill"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-6">
                                    <div class="small-box bg-danger">
                                        <div class="inner">
                                            <h3 id="alert-count">0</h3>
                                            <p>Alertes actives</p>
                                        </div>
                                        <div class="icon">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<footer class="main-footer">
    <div class="float-right d-none d-sm-inline">
        Projet Panier Intelligent
    </div>
    <strong>Copyright &copy; 2025 <a href="#">Smart Grocery</a>.</strong> Tous droits réservés.
</footer>
</div>

<!-- Modal pour envoi de message au panier -->
<div class="modal fade" id="message-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Envoyer un message</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="message-form">
                    <input type="hidden" id="message-cart-id" name="cart_id">
                    <div class="form-group">
                        <label>Message à afficher sur la tablette</label>
                        <textarea class="form-control" id="message-text" rows="3" placeholder="Entrez votre message ici..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="send-message-btn">Envoyer</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Font Awesome -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"></script>

<!-- JavaScript for the map functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Store map configuration
    const storeMap = {
        container: document.getElementById('store-map'),
        zoom: 1,
        cartElements: {},
        selectedCart: null,
        zones: <?= json_encode($zones) ?>,
        carts: <?= json_encode($carts) ?>,
        updateInterval: 5000,  // Update every 5 seconds
    };
    
    // Initialize the map
    initializeMap();
    
    // Update cart positions periodically
    setInterval(updateCartPositions, storeMap.updateInterval);
    
    // Calculate and update statistics
    updateStatistics();
    
    // Event listeners for controls
    document.getElementById('zoom-in').addEventListener('click', () => zoomMap(0.2));
    document.getElementById('zoom-out').addEventListener('click', () => zoomMap(-0.2));
    document.getElementById('reset-view').addEventListener('click', resetMapView);
    document.getElementById('status-filter').addEventListener('change', applyFilters);
    document.getElementById('zone-filter').addEventListener('change', applyFilters);
    document.getElementById('search-btn').addEventListener('click', searchCart);
    document.getElementById('cart-search').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') searchCart();
    });
    document.getElementById('show-heatmap').addEventListener('change', toggleHeatmap);
    
    // Cart control buttons
    document.getElementById('lock-cart').addEventListener('click', function() {
        if (storeMap.selectedCart) controlCart(storeMap.selectedCart, 'lock');
    });
    document.getElementById('send-message').addEventListener('click', function() {
        if (storeMap.selectedCart) {
            document.getElementById('message-cart-id').value = storeMap.selectedCart;
            $('#message-modal').modal('show');
        }
    });
    document.getElementById('cart-help').addEventListener('click', function() {
        if (storeMap.selectedCart) sendHelp(storeMap.selectedCart);
    });
    document.getElementById('send-message-btn').addEventListener('click', sendCartMessage);
    
    /**
     * Initialize the store map with zones and carts
     */
    function initializeMap() {
        // Create the store layout
        createStoreLayout();
        
        // Add carts to the map
        storeMap.carts.forEach(cart => {
            addCartToMap(cart);
        });
        
        console.log('Map initialized with ' + storeMap.carts.length + ' carts');
    }
    
    /**
     * Create the store layout with zones
     */
    function createStoreLayout() {
        const mapContainer = storeMap.container;
        
        // Clear existing content
        mapContainer.innerHTML = '';
        
        // Create SVG element for the map
        const svgNS = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(svgNS, "svg");
        svg.setAttribute("width", "100%");
        svg.setAttribute("height", "100%");
        svg.setAttribute("viewBox", "0 0 1000 800");
        
        // Create store zones
        storeMap.zones.forEach(zone => {
            const rect = document.createElementNS(svgNS, "rect");
            rect.setAttribute("x", zone.x_coord);
            rect.setAttribute("y", zone.y_coord);
            rect.setAttribute("width", zone.width);
            rect.setAttribute("height", zone.height);
            rect.setAttribute("fill", zone.color || "#f0f0f0");
            rect.setAttribute("stroke", "#ccc");
            rect.setAttribute("stroke-width", "2");
            rect.setAttribute("rx", "5");
            rect.setAttribute("data-zone-id", zone.zone_id);
            
            // Add zone label
            const text = document.createElementNS(svgNS, "text");
            text.setAttribute("x", parseInt(zone.x_coord) + parseInt(zone.width) / 2);
            text.setAttribute("y", parseInt(zone.y_coord) + parseInt(zone.height) / 2);
            text.setAttribute("text-anchor", "middle");
            text.setAttribute("dominant-baseline", "middle");
            text.setAttribute("fill", "#333");
            text.setAttribute("font-size", "12");
            text.textContent = zone.zone_name;
            
            svg.appendChild(rect);
            svg.appendChild(text);
        });
        
        // Create a group for carts
        const cartGroup = document.createElementNS(svgNS, "g");
        cartGroup.setAttribute("id", "cart-layer");
        svg.appendChild(cartGroup);
        
        // Add the SVG to the map container
        mapContainer.appendChild(svg);
    }
    
    /**
     * Add a cart to the map
     */
    function addCartToMap(cart) {
        const cartGroup = document.getElementById('cart-layer');
        const svgNS = "http://www.w3.org/2000/svg";
        
        // Create cart icon group
        const cartElement = document.createElementNS(svgNS, "g");
        cartElement.setAttribute("class", "cart-icon");
        cartElement.setAttribute("data-cart-id", cart.cart_id);
        cartElement.setAttribute("transform", `translate(${cart.position_x}, ${cart.position_y})`);
        
        // Determine cart color based on status
        let cartColor;
        switch (cart.status) {
            case 'active':
                cartColor = "#28a745"; // Green
                break;
            case 'idle':
                cartColor = "#ffc107"; // Yellow
                break;
            case 'maintenance':
                cartColor = "#6c757d"; // Gray
                break;
            default:
                cartColor = "#007bff"; // Blue
        }
        
        // Create cart icon (circle with cart icon)
        const circle = document.createElementNS(svgNS, "circle");
        circle.setAttribute("cx", "0");
        circle.setAttribute("cy", "0");
        circle.setAttribute("r", "15");
        circle.setAttribute("fill", cartColor);
        circle.setAttribute("stroke", "#fff");
        circle.setAttribute("stroke-width", "2");
        
        // Create cart text (ID)
        const text = document.createElementNS(svgNS, "text");
        text.setAttribute("x", "0");
        text.setAttribute("y", "0");
        text.setAttribute("text-anchor", "middle");
        text.setAttribute("dominant-baseline", "middle");
        text.setAttribute("fill", "#fff");
        text.setAttribute("font-size", "10");
        text.textContent = cart.cart_id;
        
        // Add elements to cart group
        cartElement.appendChild(circle);
        cartElement.appendChild(text);
        
        // Add click handler
        cartElement.addEventListener('click', () => showCartDetails(cart.cart_id));
        
        // Add to map and store reference
        cartGroup.appendChild(cartElement);
        storeMap.cartElements[cart.cart_id] = cartElement;
    }
    
    /**
     * Update cart positions periodically
     */
    function updateCartPositions() {
        fetch('map.php?action=update_carts')
            .then(response => response.json())
            .then(data => {
                // Update cart positions and status
                data.carts.forEach(cart => {
                    if (storeMap.cartElements[cart.cart_id]) {
                        // Update position
                        storeMap.cartElements[cart.cart_id].setAttribute("transform", 
                            `translate(${cart.position_x}, ${cart.position_y})`);
                        
                        // Update status color
                        let cartColor;
                        switch (cart.status) {
                            case 'active':
                                cartColor = "#28a745"; // Green
                                break;
                            case 'idle':
                                cartColor = "#ffc107"; // Yellow
                                break;
                            case 'maintenance':
                                cartColor = "#6c757d"; // Gray
                                break;
                            default:
                                cartColor = "#007bff"; // Blue
                        }
                        
                        storeMap.cartElements[cart.cart_id].querySelector('circle')
                            .setAttribute("fill", cartColor);
                    } else {
                        // Add new cart if it doesn't exist
                        addCartToMap(cart);
                    }
                });
                
                // Update alerts list
                //updateAlertsList(data.alerts);
                
                // Update statistics
                updateStatistics(data.carts);
                
                // Update selected cart details if any
                if (storeMap.selectedCart) {
                    const selectedCartData = data.carts.find(c => c.cart_id == storeMap.selectedCart);
                    if (selectedCartData) updateSelectedCartDetails(selectedCartData);
                }
                
                // Store updated cart data
                storeMap.carts = data.carts;
                
                // Apply current filters
                applyFilters();
            })
            .catch(error => console.error('Error updating cart positions:', error));
    }
    
    /**
     * Show cart details when clicked
     */
    function showCartDetails(cartId) {
        storeMap.selectedCart = cartId;
        
        // Find the cart data
        const cartData = storeMap.carts.find(c => c.cart_id == cartId);
        if (!cartData) return;
        
        // Update the cart details panel
        const detailsPanel = document.getElementById('cart-details-content');
        detailsPanel.innerHTML = `
            <div class="text-center mb-3">
                <span class="badge badge-primary">Panier #${cartData.cart_id}</span>
            </div>
            <dl class="row">
                <dt class="col-sm-6">État:</dt>
                <dd class="col-sm-6">${getStatusLabel(cartData.status)}</dd>
                
                <dt class="col-sm-6">Client:</dt>
                <dd class="col-sm-6">${cartData.username}</dd>
                
                <dt class="col-sm-6">Zone:</dt>
                <dd class="col-sm-6">${cartData.zone_name}</dd>
                
                <dt class="col-sm-6">Batterie:</dt>
                <dd class="col-sm-6">
                    <div class="progress">
                        <div class="progress-bar bg-${getBatteryClass(cartData.battery_level)}" 
                             role="progressbar" style="width: ${cartData.battery_level}%" 
                             aria-valuenow="${cartData.battery_level}" aria-valuemin="0" aria-valuemax="100">
                            ${cartData.battery_level}%
                        </div>
                    </div>
                </dd>
                
                <dt class="col-sm-6">Dernière activité:</dt>
                <dd class="col-sm-6">${formatTime(cartData.last_seen)}</dd>
                
                <dt class="col-sm-6">Montant total:</dt>
                <dd class="col-sm-6">${formatCurrency(cartData.total_amount)}</dd>
            </dl>
            <hr>
            <div class="text-center">
                <button class="btn btn-sm btn-outline-primary" onclick="loadCartItems(${cartData.cart_id})">
                    <i class="fas fa-list"></i> Voir les produits
                </button>
            </div>
            <div id="cart-items" class="mt-3"></div>
        `;
        
        // Show the details card
        document.getElementById('cart-details-card').style.display = 'block';
        
        // Highlight the selected cart
        highlightSelectedCart(cartId);
    }
    
    /**
     * Update details of selected cart
     */
    function updateSelectedCartDetails(cartData) {
        // Similar to showCartDetails but doesn't reset the view
        const detailsPanel = document.getElementById('cart-details-content');
        detailsPanel.innerHTML = `
            <div class="text-center mb-3">
                <span class="badge badge-primary">Panier #${cartData.cart_id}</span>
            </div>
            <dl class="row">
                <dt class="col-sm-6">État:</dt>
                <dd class="col-sm-6">${getStatusLabel(cartData.status)}</dd>
                
                <dt class="col-sm-6">Client:</dt>
                <dd class="col-sm-6">${cartData.username}</dd>
                
                <dt class="col-sm-6">Zone:</dt>
                <dd class="col-sm-6">${cartData.zone_name}</dd>
                
                <dt class="col-sm-6">Batterie:</dt>
                <dd class="col-sm-6">
                    <div class="progress">
                        <div class="progress-bar bg-${getBatteryClass(cartData.battery_level)}" 
                             role="progressbar" style="width: ${cartData.battery_level}%" 
                             aria-valuenow="${cartData.battery_level}" aria-valuemin="0" aria-valuemax="100">
                            ${cartData.battery_level}%
                        </div>
                    </div>
                </dd>
                
                <dt class="col-sm-6">Dernière activité:</dt>
                <dd class="col-sm-6">${formatTime(cartData.last_seen)}</dd>
                
                <dt class="col-sm-6">Montant total:</dt>
                <dd class="col-sm-6">${formatCurrency(cartData.total_amount)}</dd>
            </dl>
            <hr>
            <div class="text-center">
                <button class="btn btn-sm btn-outline-primary" onclick="loadCartItems(${cartData.cart_id})">
                    <i class="fas fa-list"></i> Voir les produits
                </button>
            </div>
            <div id="cart-items" class="mt-3"></div>
        `;
    }
    
    /**
     * Highlight the selected cart on the map
     */
    function highlightSelectedCart(cartId) {
        // Reset all carts to normal appearance
        for (const id in storeMap.cartElements) {
            const cartElement = storeMap.cartElements[id];
            cartElement.querySelector('circle').setAttribute('stroke-width', '2');
            cartElement.querySelector('circle').setAttribute('r', '15');
        }
        
        // Highlight selected cart
        const selectedCart = storeMap.cartElements[cartId];
        if (selectedCart) {
            selectedCart.querySelector('circle').setAttribute('stroke-width', '3');
            selectedCart.querySelector('circle').setAttribute('r', '18');
        }
    }
    
    /**
     * Load cart items
     */
    function loadCartItems(cartId) {
        fetch(`map.php?get_cart_details=${cartId}`)
            .then(response => response.json())
            .then(data => {
                const itemsContainer = document.getElementById('cart-items');
                
                if (data.items.length === 0) {
                    itemsContainer.innerHTML = '<p class="text-center text-muted">Panier vide</p>';
                    return;
                }
                
                let html = `
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Qté</th>
                                <th>Prix</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.items.forEach(item => {
                    html += `
                        <tr>
                            <td>${item.product_name}</td>
                            <td>${item.quantity}</td>
                            <td>${formatCurrency(item.subtotal)}</td>
                        </tr>
                    `;
                });
                
                html += `
                        </tbody>
                    </table>
                `;
                
                itemsContainer.innerHTML = html;
            })
            .catch(error => console.error('Error loading cart items:', error));
    }
    
    /**
     * Apply filters to the carts on the map
     */
    function applyFilters() {
        const statusFilter = document.getElementById('status-filter').value;
        const zoneFilter = document.getElementById('zone-filter').value;
        
        storeMap.carts.forEach(cart => {
            const cartElement = storeMap.cartElements[cart.cart_id];
            if (!cartElement) return;
            
            let visible = true;
            
            // Apply status filter
            if (statusFilter !== 'all' && cart.status !== statusFilter) {
                visible = false;
            }
            
            // Apply zone filter
            if (zoneFilter !== 'all' && cart.zone_id != zoneFilter) {
                visible = false;
            }
            
            // Show/hide cart
            cartElement.style.display = visible ? 'block' : 'none';
        });
    }
    
    /**
     * Search for a specific cart by ID
     */
    function searchCart() {
        const searchValue = document.getElementById('cart-search').value.trim();
        if (!searchValue) return;
        
        const cartId = parseInt(searchValue);
        const cartData = storeMap.carts.find(c => c.cart_id == cartId);
        
        if (cartData) {
            showCartDetails(cartId);
            // Center view on cart
            const cartElement = storeMap.cartElements[cartId];
            if (cartElement) {
                // TODO: Implement center view
            }
        } else {
            alert(`Panier #${cartId} non trouvé.`);
        }
    }
    
    /**
     * Toggle heatmap visualization
     */
    function toggleHeatmap() {
        const showHeatmap = document.getElementById('show-heatmap').checked;
        
        if (showHeatmap) {
            // Create and show heatmap visualization
            // This would typically use a heatmap library
            alert("Fonctionnalité de carte de chaleur en cours de développement.");
        } else {
            // Hide heatmap
        }
    }
    
    /**
     * Send a command to control a cart
     */
    function controlCart(cartId, command) {
        // Confirm action
        if (!confirm(`Êtes-vous sûr de vouloir ${command === 'lock' ? 'verrouiller' : 'déverrouiller'} le panier #${cartId}?`)) {
            return;
        }
        
        // Send command to server
        const formData = new FormData();
        formData.append('action', 'control_cart');
        formData.append('cart_id', cartId);
        formData.append('command', command);
        
        fetch('map.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(`Commande envoyée au panier #${cartId}`);
            } else {
                alert(`Erreur: ${data.message}`);
            }
        })
        .catch(error => console.error('Error sending cart command:', error));
    }
    
    /**
     * Send a message to a cart's tablet
     */
    function sendCartMessage() {
        const cartId = document.getElementById('message-cart-id').value;
        const message = document.getElementById('message-text').value.trim();
        
        if (!message) {
            alert('Veuillez entrer un message.');
            return;
        }
        
        // Send message to server
        const formData = new FormData();
        formData.append('action', 'control_cart');
        formData.append('cart_id', cartId);
        formData.append('command', 'send_message');
        formData.append('message', message);
        
        fetch('map.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(`Message envoyé au panier #${cartId}`);
                $('#message-modal').modal('hide');
                document.getElementById('message-text').value = '';
            } else {
                alert(`Erreur: ${data.message}`);
            }
        })
        .catch(error => console.error('Error sending message to cart:', error));
    }
    
    /**
     * Send help request for a cart
     */
    function sendHelp(cartId) {
        if (!confirm(`Envoyer une assistance au panier #${cartId}?`)) {
            return;
        }
        
        // This would typically dispatch a staff member to help
        alert(`Une demande d'assistance a été envoyée pour le panier #${cartId}`);
    }
    
    /**
     * Update statistics display
     */
    function updateStatistics(carts = null) {
        const cartsData = carts || storeMap.carts;
        
        // Calculate statistics
        const activeCarts = cartsData.filter(c => c.status === 'active').length;
        document.getElementById('active-carts-count').textContent = activeCarts;
        
        // Calculate average time (simulated data)
        document.getElementById('avg-cart-time').textContent = '18';
        
        // Calculate average cart amount
        const activeCartsWithAmount = cartsData.filter(c => c.status === 'active' && c.total_amount);
        let avgAmount = 0;
        if (activeCartsWithAmount.length > 0) {
            avgAmount = activeCartsWithAmount.reduce((sum, cart) => sum + parseFloat(cart.total_amount || 0), 0) / activeCartsWithAmount.length;
        }
        document.getElementById('avg-cart-amount').textContent = formatCurrency(avgAmount).replace(' MAD', '');
        
        // Count alerts
        document.getElementById('alert-count').textContent = <?= count($alerts) ?>;
    }
    
    /**
     * Zoom the map in or out
     */
    function zoomMap(delta) {
        storeMap.zoom = Math.max(0.5, Math.min(2, storeMap.zoom + delta));
        document.getElementById('store-map').style.transform = `scale(${storeMap.zoom})`;
    }
    
    /**
     * Reset map view to default
     */
    function resetMapView() {
        storeMap.zoom = 1;
        document.getElementById('store-map').style.transform = 'scale(1)';
    }
    
    /**
     * Helper function to format time
     */
    function formatTime(timeString) {
        if (!timeString) return 'N/A';
        const date = new Date(timeString);
        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    
    /**
     * Helper function to format currency
     */
    function formatCurrency(amount) {
        if (amount === null || amount === undefined) return '0.00 MAD';
        return parseFloat(amount).toFixed(2) + ' MAD';
    }
    
    /**
     * Get battery level class
     */
    function getBatteryClass(level) {
        if (level < 20) return 'danger';
        if (level < 40) return 'warning';
        return 'success';
    }
    
    /**
     * Get status label text
     */
    function getStatusLabel(status) {
        switch (status) {
            case 'active':
                return '<span class="badge badge-success">En utilisation</span>';
            case 'idle':
                return '<span class="badge badge-warning">Disponible</span>';
            case 'maintenance':
                return '<span class="badge badge-secondary">En maintenance</span>';
            default:
                return '<span class="badge badge-info">' + status + '</span>';
        }
    }
});

// Make functions available globally for onclick handlers
function loadCartItems(cartId) {
    fetch(`map.php?get_cart_details=${cartId}`)
        .then(response => response.json())
        .then(data => {
            const itemsContainer = document.getElementById('cart-items');
            
            if (data.items.length === 0) {
                itemsContainer.innerHTML = '<p class="text-center text-muted">Panier vide</p>';
                return;
            }
            
            let html = `
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Qté</th>
                            <th>Prix</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.items.forEach(item => {
                html += `
                    <tr>
                        <td>${item.product_name}</td>
                        <td>${item.quantity}</td>
                        <td>${item.price.toFixed(2)} MAD</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            itemsContainer.innerHTML = html;
        })
        .catch(error => console.error('Error loading cart items:', error));
}

function showCartDetails(cartId) {
    // Find the cart data - requires window access to storeMap
    const carts = document.querySelectorAll('[data-cart-id]');
    carts.forEach(cart => {
        if (cart.getAttribute('data-cart-id') == cartId) {
            cart.dispatchEvent(new Event('click'));
        }
    });
}
</script>

</body>
</html>