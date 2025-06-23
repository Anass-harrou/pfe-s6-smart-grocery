<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    // Not authorized, redirect to login
    header('Location: ../login.php');
    exit();
}

// Include config file
require_once "../config.php";

// Current UTC date/time and user info as specified
$current_datetime = "2025-06-18 14:57:19"; // Hardcoded as requested
$current_user = "Anass-harrou"; // Hardcoded as requested

// Filter parameters
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'week';
$filter_client = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$filter_product = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Date range based on period
$date_start = '';
$date_end = date('Y-m-d H:i:s');
$period_label = '';

switch($filter_period) {
    case 'today':
        $date_start = date('Y-m-d 00:00:00');
        $period_label = 'Today';
        break;
    case 'yesterday':
        $date_start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $date_end = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $period_label = 'Yesterday';
        break;
    case 'week':
        $date_start = date('Y-m-d 00:00:00', strtotime('-7 days'));
        $period_label = 'Last 7 Days';
        break;
    case 'month':
        $date_start = date('Y-m-d 00:00:00', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
        break;
    case 'quarter':
        $date_start = date('Y-m-d 00:00:00', strtotime('-90 days'));
        $period_label = 'Last 90 Days';
        break;
    case 'year':
        $date_start = date('Y-m-d 00:00:00', strtotime('-365 days'));
        $period_label = 'Last 365 Days';
        break;
    case 'custom':
        $date_start = isset($_GET['date_start']) ? $_GET['date_start'] . ' 00:00:00' : date('Y-m-d 00:00:00', strtotime('-30 days'));
        $date_end = isset($_GET['date_end']) ? $_GET['date_end'] . ' 23:59:59' : date('Y-m-d 23:59:59');
        $period_label = 'Custom Range';
        break;
}

// Build WHERE clause for filters
$where_clause = " WHERE achats.date_achat BETWEEN ? AND ? ";
$params = [$date_start, $date_end];
$param_types = "ss";

if($filter_client > 0) {
    $where_clause .= " AND achats.id_utilisateur = ? ";
    $params[] = $filter_client;
    $param_types .= "i";
}

if($filter_product > 0) {
    $where_clause .= " AND achat_produits.id_produit = ? ";
    $params[] = $filter_product;
    $param_types .= "i";
}

// Get all clients for dropdown
$clients = [];
$sql_clients = "SELECT id, name FROM client ORDER BY name ASC";
if($result_clients = mysqli_query($link, $sql_clients)){
    while($row_client = mysqli_fetch_assoc($result_clients)) {
        $clients[] = $row_client;
    }
    mysqli_free_result($result_clients);
}

// Get all products for dropdown
$products = [];
$sql_products = "SELECT id, nom FROM produits ORDER BY nom ASC";
if($result_products = mysqli_query($link, $sql_products)){
    while($row_product = mysqli_fetch_assoc($result_products)) {
        $products[] = $row_product;
    }
    mysqli_free_result($result_products);
}

// Summary statistics
$sales_summary = [
    'total_sales' => 0,
    'total_transactions' => 0,
    'total_products_sold' => 0,
    'avg_order_value' => 0,
    'active_clients' => 0
];

// Get summary statistics
$stats_query = "SELECT 
                COUNT(DISTINCT achats.id_achat) AS total_transactions,
                SUM(achats.montant_total) AS total_sales,
                COUNT(DISTINCT achats.id_utilisateur) AS active_clients,
                SUM(achat_produits.quantite) AS total_products_sold
                FROM achats 
                LEFT JOIN achat_produits ON achats.id_achat = achat_produits.id_achat"
                . $where_clause;

if($stmt_stats = mysqli_prepare($link, $stats_query)) {
    mysqli_stmt_bind_param($stmt_stats, $param_types, ...$params);
    mysqli_stmt_execute($stmt_stats);
    $stats_result = mysqli_stmt_get_result($stmt_stats);
    
    if($stats_row = mysqli_fetch_assoc($stats_result)) {
        $sales_summary['total_transactions'] = $stats_row['total_transactions'] ?? 0;
        $sales_summary['total_sales'] = $stats_row['total_sales'] ?? 0;
        $sales_summary['active_clients'] = $stats_row['active_clients'] ?? 0;
        $sales_summary['total_products_sold'] = $stats_row['total_products_sold'] ?? 0;
        
        // Calculate average order value
        if($sales_summary['total_transactions'] > 0) {
            $sales_summary['avg_order_value'] = $sales_summary['total_sales'] / $sales_summary['total_transactions'];
        }
    }
    mysqli_stmt_close($stmt_stats);
}

// Sales by day for chart
$sales_by_day = [];
$days_query = "SELECT 
               DATE(achats.date_achat) AS day,
               SUM(achats.montant_total) AS daily_sales,
               COUNT(DISTINCT achats.id_achat) AS transaction_count
               FROM achats"
               . $where_clause .
               " GROUP BY DATE(achats.date_achat)
               ORDER BY day ASC";

if($stmt_days = mysqli_prepare($link, $days_query)) {
    mysqli_stmt_bind_param($stmt_days, $param_types, ...$params);
    mysqli_stmt_execute($stmt_days);
    $days_result = mysqli_stmt_get_result($stmt_days);
    
    while($day_row = mysqli_fetch_assoc($days_result)) {
        $sales_by_day[] = [
            'day' => $day_row['day'],
            'daily_sales' => $day_row['daily_sales'],
            'transaction_count' => $day_row['transaction_count']
        ];
    }
    mysqli_stmt_close($stmt_days);
}

// Top selling products
$top_products = [];
$products_query = "SELECT 
                  produits.id, 
                  produits.nom,
                  SUM(achat_produits.quantite) AS total_quantity,
                  SUM(achat_produits.quantite * achat_produits.prix_unitaire) AS total_sales
                  FROM achat_produits
                  JOIN produits ON achat_produits.id_produit = produits.id
                  JOIN achats ON achat_produits.id_achat = achats.id_achat"
                  . $where_clause .
                  " GROUP BY produits.id
                  ORDER BY total_quantity DESC
                  LIMIT 10";

if($stmt_products = mysqli_prepare($link, $products_query)) {
    mysqli_stmt_bind_param($stmt_products, $param_types, ...$params);
    mysqli_stmt_execute($stmt_products);
    $products_result = mysqli_stmt_get_result($stmt_products);
    
    while($product_row = mysqli_fetch_assoc($products_result)) {
        $top_products[] = $product_row;
    }
    mysqli_stmt_close($stmt_products);
}

// Top clients
$top_clients = [];
$clients_query = "SELECT 
                 client.id,
                 client.name,
                 COUNT(achats.id_achat) AS purchase_count,
                 SUM(achats.montant_total) AS total_spent
                 FROM achats
                 JOIN client ON achats.id_utilisateur = client.id"
                 . $where_clause .
                 " GROUP BY client.id
                 ORDER BY total_spent DESC
                 LIMIT 10";

if($stmt_clients = mysqli_prepare($link, $clients_query)) {
    mysqli_stmt_bind_param($stmt_clients, $param_types, ...$params);
    mysqli_stmt_execute($stmt_clients);
    $clients_result = mysqli_stmt_get_result($stmt_clients);
    
    while($client_row = mysqli_fetch_assoc($clients_result)) {
        $top_clients[] = $client_row;
    }
    mysqli_stmt_close($stmt_clients);
}

// Sales by hour (for daily patterns)
$sales_by_hour = [];
$hours_query = "SELECT 
               HOUR(achats.date_achat) AS hour,
               SUM(achats.montant_total) AS hourly_sales,
               COUNT(DISTINCT achats.id_achat) AS transaction_count
               FROM achats"
               . $where_clause .
               " GROUP BY HOUR(achats.date_achat)
               ORDER BY hour ASC";

if($stmt_hours = mysqli_prepare($link, $hours_query)) {
    mysqli_stmt_bind_param($stmt_hours, $param_types, ...$params);
    mysqli_stmt_execute($stmt_hours);
    $hours_result = mysqli_stmt_get_result($stmt_hours);
    
    while($hour_row = mysqli_fetch_assoc($hours_result)) {
        $sales_by_hour[$hour_row['hour']] = [
            'hourly_sales' => $hour_row['hourly_sales'],
            'transaction_count' => $hour_row['transaction_count']
        ];
    }
    mysqli_stmt_close($stmt_hours);
}

// Format data for charts
$chart_days = [];
$chart_sales = [];
$chart_transactions = [];
foreach($sales_by_day as $day_data) {
    $chart_days[] = date('M d', strtotime($day_data['day']));
    $chart_sales[] = round($day_data['daily_sales'], 2);
    $chart_transactions[] = $day_data['transaction_count'];
}

$hourly_labels = [];
$hourly_sales = [];
$hourly_transactions = [];
for($i = 0; $i < 24; $i++) {
    $hourly_labels[] = sprintf("%02d:00", $i);
    $hourly_sales[] = isset($sales_by_hour[$i]) ? round($sales_by_hour[$i]['hourly_sales'], 2) : 0;
    $hourly_transactions[] = isset($sales_by_hour[$i]) ? $sales_by_hour[$i]['transaction_count'] : 0;
}

// Close connection
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales & Analytics Reports - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Datepicker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --text-color: #5a5c69;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            background-color: #f8f9fc;
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: rgb(86, 117, 148);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar .logo {
            font-size: 1.5rem;
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding: 12px 15px;
            transition: all 0.2s;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .content {
            margin-left: 250px;
            padding: 15px;
            transition: all 0.3s;
        }
        
        .stats-card {
            border-left: 4px solid;
            border-radius: 4px;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .card-sales { border-left-color: var(--primary-color); }
        .card-transactions { border-left-color: var(--success-color); }
        .card-products { border-left-color: var(--info-color); }
        .card-clients { border-left-color: var(--warning-color); }
        .card-avg { border-left-color: var(--danger-color); }
        
        .stats-card .icon {
            opacity: 0.3;
            font-size: 2rem;
        }
        .logo {
    display: flex;
    align-items: center;
    padding: 20px 15px;
    background-color: rgba(0, 0, 0, 0.1);
    margin-bottom: 15px;
}

.sidebar-logo {
    height: 40px; /* Adjust based on your logo and sidebar size */
    width: auto;
    margin-right: 10px;
    object-fit: contain; /* This ensures the logo maintains its aspect ratio */
}

.logo-text {
    font-size: 1.2rem;
    font-weight: 600;
    color: #fff; /* Adjust color based on your sidebar background */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* For collapsed sidebar (if you have this feature) */
.sidebar.collapsed .logo-text {
    display: none;
}

.sidebar.collapsed .sidebar-logo {
    margin-right: 0;
    margin-left: auto;
    margin-right: auto;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .logo {
        padding: 15px 10px;
    }
    
    .sidebar-logo {
        height: 32px; /* Slightly smaller on mobile */
    }
    
    .logo-text {
        font-size: 1rem;
    }
}
        
        .table-container {
            background: white;
            border-radius: 5px;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .top-bar {
            padding: 1rem;
            background: white;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            margin-bottom: 1.5rem;
            border-radius: 5px;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .user-dropdown .dropdown-toggle::after {
            display: none;
        }
        
        .filter-card {
            background: white;
            border-radius: 5px;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .btn-reset {
            background-color: #f8f9fc;
            color: #5a5c69;
            border: 1px solid #d1d3e2;
        }
        
        .btn-reset:hover {
            background-color: #eaecf4;
        }
        
        .progress {
            height: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .footer {
            padding: 20px 0;
            margin-top: 20px;
            font-size: 0.8rem;
            color: #858796;
            border-top: 1px solid #e3e6f0;
        }
        
        .datepicker {
            border-radius: 4px;
        }
        
        .period-badge {
            background-color: var(--primary-color);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        
        .table th {
            background-color: #f8f9fc;
            font-weight: 500;
            color: #5a5c69;
            border-top: none !important;
        }
        
        .chart-container {
            position: relative;
            margin: auto;
            height: 300px;
        }
        
        .rank-badge {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            border-radius: 50%;
            background-color: #f8f9fc;
            color: #5a5c69;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        
        .rank-1 { background-color: gold; color: #5a5c69; }
        .rank-2 { background-color: silver; color: #5a5c69; }
        .rank-3 { background-color: #cd7f32; color: white; }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .content {
                margin-left: 0;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .content.active {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
     <nav class="sidebar">
       <div class="logo">
        <img src="../smart_cart_transparent.png" alt="Smart Grocery Logo" class="sidebar-logo">
        <span class="logo-text">Smart Grocery</span>
    </div>
        <div class="user-info p-3 text-center mb-3">
            <div class="avatar mx-auto mb-2">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></div>
            <small class="text-muted">Administrator</small>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="create.php">
                    <i class="fas fa-users"></i> Clients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="historique.php">
                    <i class="fas fa-shopping-cart"></i> Purchase History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            
            <li class="nav-item mt-4">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <!-- Top Navigation Bar -->
        <div class="top-bar d-flex justify-content-between align-items-center">
            <div>
                <h4 class="m-0">Sales & Analytics Reports</h4>
                <span class="period-badge"><?php echo $period_label; ?></span>
            </div>
            <div class="d-flex align-items-center">
                <div class="user-dropdown dropdown">
                    <a class="dropdown-toggle text-decoration-none text-dark d-flex align-items-center" href="#" role="button" id="userDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar mr-2">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="d-none d-sm-block">
                            <?php echo htmlspecialchars($_SESSION['user']['username']); ?>
                            <i class="fas fa-chevron-down ml-1 small"></i>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-user-circle fa-sm fa-fw mr-2 text-gray-400"></i> Profile
                        </a>
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="../logout.php">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row">
                <div class="col-md-3 mb-3">
                    <label for="period">Time Period</label>
                    <select name="period" id="period" class="form-control" onchange="toggleCustomDateFields()">
                        <option value="today" <?php echo $filter_period == 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $filter_period == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="week" <?php echo $filter_period == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $filter_period == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="quarter" <?php echo $filter_period == 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="year" <?php echo $filter_period == 'year' ? 'selected' : ''; ?>>Last 365 Days</option>
                        <option value="custom" <?php echo $filter_period == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 custom-date-field" style="<?php echo $filter_period != 'custom' ? 'display: none;' : ''; ?>">
                    <label for="date_start">From Date</label>
                    <div class="input-group date">
                        <input type="text" class="form-control datepicker" id="date_start" name="date_start" value="<?php echo substr($date_start, 0, 10); ?>" placeholder="YYYY-MM-DD">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3 custom-date-field" style="<?php echo $filter_period != 'custom' ? 'display: none;' : ''; ?>">
                    <label for="date_end">To Date</label>
                    <div class="input-group date">
                        <input type="text" class="form-control datepicker" id="date_end" name="date_end" value="<?php echo substr($date_end, 0, 10); ?>" placeholder="YYYY-MM-DD">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="client">Filter by Client</label>
                    <select name="client_id" id="client" class="form-control">
                        <option value="0">All Clients</option>
                        <?php foreach($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo $filter_client == $client['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="product">Filter by Product</label>
                    <select name="product_id" id="product" class="form-control">
                        <option value="0">All Products</option>
                        <?php foreach($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" <?php echo $filter_product == $product['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12 text-right">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter mr-1"></i> Apply Filters
                        </button>
                        <a href="reports.php" class="btn btn-reset">
                            <i class="fas fa-redo mr-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dashboard Stats -->
        <div class="row mb-4">
            <div class="col-xl col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-sales">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Sales</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($sales_summary['total_sales'], 2); ?> DH</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-transactions">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Transactions</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($sales_summary['total_transactions']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-receipt fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-products">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Products Sold</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($sales_summary['total_products_sold']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-basket fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-clients">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Unique Clients</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($sales_summary['active_clients']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-avg">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Avg Order Value</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($sales_summary['avg_order_value'], 2); ?> DH</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sales Over Time -->
            <div class="col-lg-8 mb-4">
                <div class="table-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="m-0 font-weight-bold text-primary">Sales Trend</h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="exportDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-download fa-sm"></i> Export
                            </button>
                            <div class="dropdown-menu dropdown-menu-right shadow" aria-labelledby="exportDropdown">
                                <a class="dropdown-item" href="#" id="export-csv">
                                    <i class="fas fa-file-csv fa-sm fa-fw mr-2 text-gray-400"></i> CSV
                                </a>
                                <a class="dropdown-item" href="#" id="export-excel">
                                    <i class="fas fa-file-excel fa-sm fa-fw mr-2 text-gray-400"></i> Excel
                                </a>
                                <a class="dropdown-item" href="#" id="export-pdf">
                                    <i class="fas fa-file-pdf fa-sm fa-fw mr-2 text-gray-400"></i> PDF
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sales by Hour -->
            <div class="col-lg-4 mb-4">
                <div class="table-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="m-0 font-weight-bold text-primary">Sales by Hour</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                    <div class="text-center mt-3">
                        <small class="text-muted">Peak hours highlighted in blue</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Top Selling Products -->
            <div class="col-lg-6 mb-4">
                <div class="table-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="m-0 font-weight-bold text-primary">Top Selling Products</h5>
                        <a href="#" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-search fa-sm"></i> View All
                        </a>
                    </div>
                    
                    <?php if(count($top_products) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Product</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-right">Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_products as $index => $product): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge <?php echo $index < 3 ? 'rank-'.($index+1) : ''; ?>">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['nom']); ?></td>
                                            <td class="text-center"><?php echo number_format($product['total_quantity']); ?></td>
                                            <td class="text-right"><?php echo number_format($product['total_sales'], 2); ?> DH</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No product data available for the selected period.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Clients -->
            <div class="col-lg-6 mb-4">
                <div class="table-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="m-0 font-weight-bold text-primary">Top Clients</h5>
                        <a href="#" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-search fa-sm"></i> View All
                        </a>
                    </div>
                    
                    <?php if(count($top_clients) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Client</th>
                                        <th class="text-center">Purchases</th>
                                        <th class="text-right">Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_clients as $index => $client): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge <?php echo $index < 3 ? 'rank-'.($index+1) : ''; ?>">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                                            <td class="text-center"><?php echo number_format($client['purchase_count']); ?></td>
                                            <td class="text-right"><?php echo number_format($client['total_spent'], 2); ?> DH</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No client data available for the selected period.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sales Performance Metrics -->
        <div class="row">
            <div class="col-lg-12 mb-4">
                <div class="table-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="m-0 font-weight-bold text-primary">Sales Performance Insights</h5>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Average Transaction Value</span>
                                    <span class="font-weight-bold"><?php echo number_format($sales_summary['avg_order_value'], 2); ?> DH</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(100, ($sales_summary['avg_order_value'] / 250) * 100); ?>%" aria-valuenow="<?php echo $sales_summary['avg_order_value']; ?>" aria-valuemin="0" aria-valuemax="250"></div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Total Sales vs Target (1000 DH)</span>
                                    <span class="font-weight-bold"><?php echo number_format(($sales_summary['total_sales'] / 1000) * 100, 1); ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, ($sales_summary['total_sales'] / 1000) * 100); ?>%" aria-valuenow="<?php echo $sales_summary['total_sales']; ?>" aria-valuemin="0" aria-valuemax="1000"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Customers Reached vs Target (20)</span>
                                    <span class="font-weight-bold"><?php echo number_format(($sales_summary['active_clients'] / 20) * 100, 1); ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo min(100, ($sales_summary['active_clients'] / 20) * 100); ?>%" aria-valuenow="<?php echo $sales_summary['active_clients']; ?>" aria-valuemin="0" aria-valuemax="20"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Items per Transaction</span>
                                    <span class="font-weight-bold"><?php echo $sales_summary['total_transactions'] > 0 ? number_format($sales_summary['total_products_sold'] / $sales_summary['total_transactions'], 1) : 0; ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo min(100, ($sales_summary['total_transactions'] > 0 ? ($sales_summary['total_products_sold'] / $sales_summary['total_transactions']) / 5 * 100 : 0)); ?>%" aria-valuenow="<?php echo $sales_summary['total_transactions'] > 0 ? ($sales_summary['total_products_sold'] / $sales_summary['total_transactions']) : 0; ?>" aria-valuemin="0" aria-valuemax="5"></div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Daily Sales Rate</span>
                                    <span class="font-weight-bold"><?php echo number_format(count($sales_by_day) > 0 ? $sales_summary['total_sales'] / count($sales_by_day) : 0, 2); ?> DH</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo min(100, (count($sales_by_day) > 0 ? ($sales_summary['total_sales'] / count($sales_by_day)) / 500 * 100 : 0)); ?>%" aria-valuenow="<?php echo count($sales_by_day) > 0 ? ($sales_summary['total_sales'] / count($sales_by_day)) : 0; ?>" aria-valuemin="0" aria-valuemax="500"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Conversion Rate (Est.)</span>
                                    <span class="font-weight-bold"><?php echo number_format(min(100, $sales_summary['total_transactions'] * 5), 1); ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(100, $sales_summary['total_transactions'] * 5); ?>%" aria-valuenow="<?php echo min(100, $sales_summary['total_transactions'] * 5); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer text-center">
            <div>
                <span>Current Date and Time (UTC): <?php echo $current_datetime; ?> | User: <?php echo $current_user; ?></span>
            </div>
            <div>
                <span>© <?php echo date('Y'); ?> Smart Grocery Admin Dashboard. All rights reserved.</span>
            </div>
        </footer>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Datepicker -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    
    <script>
        $(document).ready(function(){
            // Initialize datepickers
            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true
            });
            
            // Highlight current nav item
            $(".nav-link").each(function() {
                if ($(this).attr('href') === window.location.pathname.split('/').pop()) {
                    $(this).addClass('active');
                }
            });
            
            // Sales Chart
            var ctx = document.getElementById('salesChart').getContext('2d');
            var salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_days); ?>,
                    datasets: [{
                        label: 'Sales (DH)',
                        data: <?php echo json_encode($chart_sales); ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.2)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                    }, {
                        label: 'Transactions',
                        data: <?php echo json_encode($chart_transactions); ?>,
                        type: 'line',
                        fill: false,
                        borderColor: 'rgba(28, 200, 138, 1)',
                        tension: 0.4,
                        pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                        yAxisID: 'y1',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sales Amount (DH)'
                            },
                            grid: {
                                drawBorder: false
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Transaction Count'
                            },
                            grid: {
                                display: false
                            }
                        },
                        x: {
                            grid: {
                                drawBorder: false,
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.dataset.yAxisID === 'y') {
                                        label += new Intl.NumberFormat('en-US', { 
                                            style: 'currency', 
                                            currency: 'MAD',
                                            minimumFractionDigits: 2
                                        }).format(context.raw);
                                    } else {
                                        label += context.raw;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            
            // Hourly Sales Chart
            var hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
            var hourlyChart = new Chart(hourlyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($hourly_labels); ?>,
                    datasets: [{
                        label: 'Sales by Hour (DH)',
                        data: <?php echo json_encode($hourly_sales); ?>,
                        backgroundColor: function(context) {
                            var index = context.dataIndex;
                            var value = context.dataset.data[index];
                            // Calculate average
                            var sum = context.dataset.data.reduce((a, b) => a + b, 0);
                            var avg = sum / context.dataset.data.length;
                            
                            // Highlight peak hours (above average)
                            return value > avg * 1.2 
                                ? 'rgba(54, 185, 204, 0.8)' // Highlight color
                                : 'rgba(54, 185, 204, 0.4)'; // Normal color
                        },
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sales (DH)'
                            },
                            grid: {
                                drawBorder: false
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
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += new Intl.NumberFormat('en-US', { 
                                        style: 'currency', 
                                        currency: 'MAD',
                                        minimumFractionDigits: 2
                                    }).format(context.raw);
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
        
        // Toggle custom date fields based on period selection
        function toggleCustomDateFields() {
            var periodValue = document.getElementById('period').value;
            var customDateFields = document.getElementsByClassName('custom-date-field');
            
            for (var i = 0; i < customDateFields.length; i++) {
                if (periodValue === 'custom') {
                    customDateFields[i].style.display = 'block';
                } else {
                    customDateFields[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>