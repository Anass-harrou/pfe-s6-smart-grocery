<?php
session_start();
require_once 'phpqrcode/qrlib.php'; 
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    error_log("Web Page Database Connection Error: " . $conn->connect_error);
    die("Erreur de connexion à la base de données.");
}

// Current timestamp and user data
$current_timestamp = "2025-06-18 16:02:24"; // From your provided timestamp
$current_user = "Anass-harrou"; // From your provided user

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Check if the required columns exist, if not, create them
$table_check_query = "SHOW COLUMNS FROM produits LIKE 'map_section'";
$result = $conn->query($table_check_query);
if ($result->num_rows == 0) {
    // Columns don't exist, create them
    $add_columns_query = "
    ALTER TABLE produits 
    ADD COLUMN map_section VARCHAR(10) DEFAULT 'A1',
    ADD COLUMN map_position_x INT DEFAULT 0,
    ADD COLUMN map_position_y INT DEFAULT 0;
    ";
    $conn->query($add_columns_query);
    
    // Add some random map data to existing products
    $sections = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3'];
    $update_query = "UPDATE produits SET 
                     map_section = CASE 
                         WHEN id % 6 = 0 THEN 'A1'
                         WHEN id % 6 = 1 THEN 'A2'
                         WHEN id % 6 = 2 THEN 'A3'
                         WHEN id % 6 = 3 THEN 'B1'
                         WHEN id % 6 = 4 THEN 'B2'
                         ELSE 'B3'
                     END,
                     map_position_x = (id * 7) % 100 + 20,
                     map_position_y = (id * 13) % 100 + 20";
    $conn->query($update_query);
}

// Fetch products for the map with fallback for missing columns
$sql_products = "SELECT id, nom, prix, image, quantite, disponible, 
                    COALESCE(map_section, 'A1') as map_section, 
                    COALESCE(map_position_x, 0) as map_position_x, 
                    COALESCE(map_position_y, 0) as map_position_y 
                 FROM produits ORDER BY nom ASC";
$result_products = $conn->query($sql_products);

$products = [];
$map_data = []; // For storing product positions on the map
if ($result_products->num_rows > 0) {
    while ($row = $result_products->fetch_assoc()) {
        $products[] = $row;
        
        // Also prepare map data
        $map_data[] = [
            'id' => $row['id'],
            'name' => $row['nom'],
            'section' => $row['map_section'],
            'x' => intval($row['map_position_x']),
            'y' => intval($row['map_position_y']),
            'image' => $row['image'],
            'price' => $row['prix'],
            'available' => ($row['quantite'] > 0 && $row['disponible'] == 1) ? true : false,
            'quantity' => $row['quantite']
        ];
    }
}

// Get store sections
$sections = [];
$sql_sections = "SELECT DISTINCT map_section FROM produits WHERE map_section IS NOT NULL";
$result_sections = $conn->query($sql_sections);
if ($result_sections && $result_sections->num_rows > 0) {
    while($row = $result_sections->fetch_assoc()) {
        $sections[] = $row['map_section'];
    }
} else {
    // Default sections if none are defined in the database
    $sections = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3'];
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan du Supermarché - Smart Grocery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <style>
        /* Basic styling */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .header {
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header .logo {
            max-height: 50px;
        }
        
        .navbar {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .navbar a {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .navbar a:hover, 
        .navbar a.active {
            color: #4CAF50;
            background-color: rgba(76, 175, 80, 0.1);
        }
        
        .content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-title {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .page-description {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        /* Store Map Styles */
        .store-map-container {
            margin: 30px 0;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .store-map-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .store-map-title {
            font-size: 1.5em;
            color: #333;
            margin: 0;
            margin-bottom: 10px;
        }
        
        .map-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
        .map-filter {
            padding: 8px 15px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .map-filter:hover {
            background-color: #f5f5f5;
        }
        
        .map-filter.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        .store-map {
            width: 100%;
            height: 600px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
            background-image: url('images/grid-pattern.png');
            background-repeat: repeat;
        }
        
        .map-section {
            position: absolute;
            border: 1px solid #ccc;
            background-color: rgba(240, 240, 240, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            color: #666;
            text-align: center;
            padding: 10px;
            flex-direction: column;
            transition: all 0.3s;
        }
        
        .map-section.highlighted {
            background-color: rgba(76, 175, 80, 0.3);
            border-color: #4CAF50;
            z-index: 5;
        }
        
        .map-section-name {
            font-size: 1.2em;
            margin-bottom: 5px;
        }
        
        .map-section-description {
            font-size: 0.9em;
            font-weight: normal;
        }
        
        .map-product {
            position: absolute;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #4CAF50;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .map-product:hover {
            transform: scale(1.2);
            z-index: 15;
        }
        
        .map-product.unavailable {
            background-color: #ccc;
        }
        
        .map-product-tooltip {
            position: absolute;
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            z-index: 20;
            pointer-events: none;
            white-space: nowrap;
            display: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            max-width: 250px;
        }
        
        .map-legend {
            margin-top: 20px;
            display: flex;
            gap: 20px;
            font-size: 14px;
            flex-wrap: wrap;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        
        .legend-available {
            background-color: #4CAF50;
        }
        
        .legend-unavailable {
            background-color: #ccc;
        }
        
        .map-entrance {
            position: absolute;
            width: 80px;
            height: 40px;
            background-color: #3f51b5;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px;
            z-index: 5;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .map-cashier {
            position: absolute;
            width: 100px;
            height: 40px;
            background-color: #ff9800;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px;
            z-index: 5;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .map-legend-title {
            font-weight: bold;
            margin-right: 20px;
        }
        
        /* Product List Panel */
        .product-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 250px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            padding: 15px;
            z-index: 15;
            max-height: 560px;
            overflow-y: auto;
        }
        
        .product-panel-title {
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .product-panel-close {
            cursor: pointer;
            color: #666;
        }
        
        .product-panel-close:hover {
            color: #333;
        }
        
        .product-panel-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .product-panel-item {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .product-panel-item:hover {
            background-color: #f5f5f5;
        }
        
        .product-panel-item.unavailable {
            opacity: 0.6;
        }
        
        .product-panel-item-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .dot-available {
            background-color: #4CAF50;
        }
        
        .dot-unavailable {
            background-color: #ccc;
        }
        
        .product-search {
            margin-bottom: 15px;
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        /* Product Details Modal */
        .product-modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .product-modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .product-modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            color: #aaa;
            cursor: pointer;
        }
        
        .product-modal-close:hover {
            color: #333;
        }
        
        .product-modal-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .product-modal-title {
            font-size: 1.4em;
            margin-bottom: 10px;
            color: #333;
        }
        
        .product-modal-price {
            font-size: 1.2em;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 15px;
        }
        
        .product-modal-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        
        .status-available {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .status-unavailable {
            background-color: rgba(204, 204, 204, 0.2);
            color: #666;
            border: 1px solid #ccc;
        }
        
        .product-modal-location {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        
        .product-modal-section {
            font-weight: bold;
            color: #3f51b5;
        }
        
        /* Audit timestamp display */
        .audit-timestamp {
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #f8f9fc;
            border-radius: 5px;
            text-align: center;
            font-size: 0.8rem;
            color: #6c757d;
            border: 1px solid #e3e6f0;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .content {
                padding: 1rem;
            }
            
            .store-map-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .map-controls {
                margin-top: 10px;
            }
            
            .store-map {
                height: 450px;
            }
            
            .product-panel {
                position: relative;
                width: 100%;
                top: auto;
                right: auto;
                margin-bottom: 20px;
                max-height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="images/logo.png" alt="Logo de l'entreprise" class="logo">
        <div class="navbar">
            <a href="index.php">Accueil</a>
            <a href="carte.php" class="active">Carte</a>
             <a href="guide.php" class="">Guide</a>
            <?php if ($isLoggedIn): ?>
                <a href="index.php?logout=true"><i class="fas fa-sign-out-alt"></i> Se déconnecter</a>
            <?php else: ?>
                <a href="index.php#login"><i class="fas fa-user"></i> Se connecter</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="content">
        <h1 class="page-title">Plan du Supermarché</h1>
        <p class="page-description">
            Explorez notre supermarché et trouvez facilement l'emplacement de tous nos produits. 
            Cliquez sur un produit pour voir plus de détails ou utilisez les filtres pour trouver exactement ce que vous cherchez.
        </p>

        <!-- Store Map Section -->
        <div class="store-map-container">
            <div class="store-map-header">
                <h2 class="store-map-title">Plan Interactif</h2>
                <div class="map-controls">
                    <button class="map-filter active" data-filter="all">Tous les produits</button>
                    <button class="map-filter" data-filter="available">Produits disponibles</button>
                    <button class="map-filter" data-filter="unavailable">Produits indisponibles</button>
                </div>
            </div>
            
            <div class="store-map">
                <!-- Map sections -->
                <div class="map-section" style="left: 5%; top: 5%; width: 28%; height: 40%;">
                    <div class="map-section-name">Section A1</div>
                    <div class="map-section-description">Produits Frais</div>
                </div>
                <div class="map-section" style="left: 35%; top: 5%; width: 28%; height: 40%;">
                    <div class="map-section-name">Section A2</div>
                    <div class="map-section-description">Boulangerie</div>
                </div>
                <div class="map-section" style="left: 65%; top: 5%; width: 28%; height: 40%;">
                    <div class="map-section-name">Section A3</div>
                    <div class="map-section-description">Boissons</div>
                </div>
                <div class="map-section" style="left: 5%; top: 50%; width: 28%; height: 40%;">
                    <div class="map-section-name">Section B1</div>
                    <div class="map-section-description">Épicerie</div>
                </div>
                <div class="map-section" style="left: 35%; top: 50%; width: 28%; height: 40%;">
                    <div class="map-section-name">Section B2</div>
                    <div class="map-section-description">Hygiène</div>
                </div>
                <div class="map-section" style="left: 65%; top: 50%; width: 28%; height: 40%;">
                    <div class="map-section-name">Section B3</div>
                    <div class="map-section-description">Ménage</div>
                </div>
                
                <!-- Entrance and Cashiers -->
                <div class="map-entrance" style="left: 45%; top: 93%;">
                    <i class="fas fa-door-open mr-2"></i> ENTRÉE
                </div>
                <div class="map-cashier" style="left: 80%; top: 93%;">
                    <i class="fas fa-cash-register mr-2"></i> CAISSES
                </div>
                
                <!-- Products will be added dynamically by JavaScript -->
                <div class="map-product-tooltip"></div>
                
                <!-- Product Panel -->
                <div class="product-panel">
                    <div class="product-panel-title">
                        Liste des Produits
                        <span class="product-panel-close"><i class="fas fa-times"></i></span>
                    </div>
                    <input type="text" class="product-search" placeholder="Rechercher un produit...">
                    <ul class="product-panel-list">
                        <?php foreach ($products as $product): ?>
                            <li class="product-panel-item <?php echo ($product['quantite'] > 0 && $product['disponible'] == 1) ? '' : 'unavailable'; ?>" 
                                data-id="<?php echo $product['id']; ?>"
                                data-name="<?php echo htmlspecialchars($product['nom']); ?>"
                                data-section="<?php echo $product['map_section']; ?>">
                                <div class="product-panel-item-dot <?php echo ($product['quantite'] > 0 && $product['disponible'] == 1) ? 'dot-available' : 'dot-unavailable'; ?>"></div>
                                <?php echo htmlspecialchars($product['nom']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="map-legend">
                <span class="map-legend-title">Légende :</span>
                <div class="legend-item">
                    <div class="legend-color legend-available"></div>
                    <span>Produit disponible</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-unavailable"></div>
                    <span>Produit indisponible</span>
                </div>
                <div class="legend-item">
                    <div style="background-color:#3f51b5" class="legend-color"></div>
                    <span>Entrée</span>
                </div>
                <div class="legend-item">
                    <div style="background-color:#ff9800" class="legend-color"></div>
                    <span>Caisses</span>
                </div>
            </div>
        </div>

        <!-- Product Details Modal -->
        <div id="productModal" class="product-modal">
            <div class="product-modal-content">
                <span class="product-modal-close">&times;</span>
                <img class="product-modal-image" src="" alt="Product Image">
                <h3 class="product-modal-title"></h3>
                <div class="product-modal-price"></div>
                <div class="product-modal-status"></div>
                <div class="product-modal-location">
                    Emplacement: <span class="product-modal-section"></span>
                </div>
            </div>
        </div>
        
        <!-- Audit timestamp display -->
        <div class="audit-timestamp">
            <p>Date et heure actuelles (UTC): <strong><?= $current_timestamp ?></strong> | Utilisateur: <strong><?= $current_user ?></strong></p>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Map data from PHP
        var mapData = <?php echo json_encode($map_data); ?>;
        
        // Initialize the map
        function initializeMap() {
            var mapContainer = $('.store-map');
            var tooltip = $('.map-product-tooltip');
            
            // Clear existing products on the map
            $('.map-product').remove();
            
            // Add product markers
            mapData.forEach(function(product, index) {
                // Determine section coordinates
                var section = product.section;
                var sectionElement = $('.map-section').filter(function() {
                    return $(this).text().includes(section);
                });
                
                if (sectionElement.length) {
                    // Calculate position within section
                    var sectionLeft = sectionElement.position().left;
                    var sectionTop = sectionElement.position().top;
                    var sectionWidth = sectionElement.width();
                    var sectionHeight = sectionElement.height();
                    
                    // Use provided coordinates or generate random ones within the section
                    var left = sectionLeft + (product.x > 0 ? product.x : 10 + Math.random() * (sectionWidth - 50));
                    var top = sectionTop + (product.y > 0 ? product.y : 10 + Math.random() * (sectionHeight - 50));
                    
                    // Create product marker
                    var marker = $('<div>')
                        .addClass('map-product')
                        .addClass(product.available ? 'available' : 'unavailable')
                        .attr('data-id', product.id)
                        .attr('data-name', product.name)
                        .attr('data-status', product.available ? 'available' : 'unavailable')
                        .attr('data-section', product.section)
                        .attr('data-price', product.price)
                        .attr('data-image', product.image)
                        .attr('data-quantity', product.quantity)
                        .css({
                            left: left + 'px',
                            top: top + 'px'
                        })
                        .html('<i class="fas fa-shopping-basket"></i>')
                        .appendTo(mapContainer);
                    
                    // Add hover effects for tooltip
                    marker.on('mouseover', function() {
                        tooltip.text(product.name + (product.available ? ' (Disponible)' : ' (Indisponible)'))
                            .css({
                                left: (left + 40) + 'px',
                                top: (top - 5) + 'px',
                                display: 'block'
                            });
                    }).on('mouseout', function() {
                        tooltip.hide();
                    });
                    
                    // Highlight section when hovering over product
                    marker.on('mouseover', function() {
                        sectionElement.addClass('highlighted');
                    }).on('mouseout', function() {
                        sectionElement.removeClass('highlighted');
                    });
                    
                    // Click event to show product details
                    marker.on('click', function() {
                        showProductDetails(product);
                    });
                }
            });
        }
        
        // Initialize map on page load
        initializeMap();
        
        // Filter map products
        $('.map-filter').on('click', function() {
            var filter = $(this).data('filter');
            
            // Update active filter button
            $('.map-filter').removeClass('active');
            $(this).addClass('active');
            
            // Filter products
            if (filter === 'all') {
                $('.map-product').show();
            } else {
                $('.map-product').hide();
                $('.map-product[data-status="' + filter + '"]').show();
            }
        });
        
        // Product panel item click
        $('.product-panel-item').on('click', function() {
            var productId = $(this).data('id');
            var productName = $(this).data('name');
            var section = $(this).data('section');
            
            // Find the product marker
            var marker = $('.map-product[data-id="' + productId + '"]');
            
            if (marker.length) {
                // Highlight section
                $('.map-section').removeClass('highlighted');
                $('.map-section').filter(function() {
                    return $(this).text().includes(section);
                }).addClass('highlighted');
                
                // Highlight product
                $('.map-product').removeClass('highlight-pulse');
                marker.addClass('highlight-pulse');
                
                // Add temporary pulse animation
                $('<style>').text(`
                    .highlight-pulse {
                        animation: pulse 1.5s infinite;
                        z-index: 50 !important;
                    }
                    @keyframes pulse {
                        0% { transform: scale(1); }
                        50% { transform: scale(1.5); }
                        100% { transform: scale(1); }
                    }
                `).appendTo('head');
                
                // Find product data
                var product = mapData.find(function(p) {
                    return p.id == productId;
                });
                
                if (product) {
                    showProductDetails(product);
                }
            }
        });
        
        // Product search functionality
        $('.product-search').on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            
            if (searchTerm.length > 0) {
                $('.product-panel-item').hide();
                $('.product-panel-item').filter(function() {
                    return $(this).data('name').toLowerCase().includes(searchTerm);
                }).show();
            } else {
                $('.product-panel-item').show();
            }
        });
        
        // Show product details modal
        function showProductDetails(product) {
            var modal = $('#productModal');
            
            // Set product details
            modal.find('.product-modal-image').attr('src', product.image);
            modal.find('.product-modal-title').text(product.name);
            modal.find('.product-modal-price').text(product.price.toFixed(2) + ' DH');
            
            var statusClass = product.available ? 'status-available' : 'status-unavailable';
            var statusText = product.available ? 'Disponible (' + product.quantity + ' en stock)' : 'Indisponible';
            modal.find('.product-modal-status')
                .attr('class', 'product-modal-status ' + statusClass)
                .text(statusText);
            
            modal.find('.product-modal-section').text('Section ' + product.section);
            
            // Show modal
            modal.css('display', 'block');
        }
        
        // Close product modal
        $('.product-modal-close').on('click', function() {
            $('#productModal').css('display', 'none');
            
            // Remove highlighting
            $('.map-section').removeClass('highlighted');
            $('.map-product').removeClass('highlight-pulse');
        });
        
        // Close modal when clicking outside
        $(window).on('click', function(event) {
            if (event.target == document.getElementById('productModal')) {
                $('#productModal').css('display', 'none');
                
                // Remove highlighting
                $('.map-section').removeClass('highlighted');
                $('.map-product').removeClass('highlight-pulse');
            }
        });
        
        // Toggle product panel on mobile
        $('.product-panel-close').on('click', function() {
            $('.product-panel').toggle();
        });
    });
    </script>
</body>
</html>