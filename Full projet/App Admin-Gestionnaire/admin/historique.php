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

// Current UTC date/time for display
$current_datetime = date('Y-m-d H:i:s');
$current_user = "Anass-harrou";

// For filtering
$filter_client = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Build the query with possible filters
$where_clause = "";
$params = [];
$param_types = "";

if($filter_client > 0) {
    $where_clause .= " AND achats.id_utilisateur = ?";
    $params[] = $filter_client;
    $param_types .= "i";
}

if(!empty($filter_date_start) && !empty($filter_date_end)) {
    $where_clause .= " AND achats.date_achat BETWEEN ? AND ?";
    $params[] = $filter_date_start . " 00:00:00";
    $params[] = $filter_date_end . " 23:59:59";
    $param_types .= "ss";
} else if(!empty($filter_date_start)) {
    $where_clause .= " AND achats.date_achat >= ?";
    $params[] = $filter_date_start . " 00:00:00";
    $param_types .= "s";
} else if(!empty($filter_date_end)) {
    $where_clause .= " AND achats.date_achat <= ?";
    $params[] = $filter_date_end . " 23:59:59";
    $param_types .= "s";
}

$sql_achats = "SELECT achats.id_achat AS id_achat, 
                      client.id AS client_id,
                      client.name AS nom_utilisateur, 
                      achats.montant_total, 
                      achats.date_achat,
                      COUNT(achat_produits.id_achat_produit) AS product_count
               FROM achats
               INNER JOIN client ON achats.id_utilisateur = client.id
               LEFT JOIN achat_produits ON achats.id_achat = achat_produits.id_achat
               WHERE 1=1 " . $where_clause . "
               GROUP BY achats.id_achat
               ORDER BY achats.date_achat DESC";

// Get summary statistics
$total_sales = 0;
$total_products = 0;
$total_clients = 0;

$stats_query = "SELECT 
                COUNT(DISTINCT achats.id_achat) AS total_transactions,
                COUNT(DISTINCT achats.id_utilisateur) AS total_clients,
                SUM(achats.montant_total) AS total_sales,
                COUNT(achat_produits.id_achat_produit) AS total_products
                FROM achats 
                LEFT JOIN achat_produits ON achats.id_achat = achat_produits.id_achat
                WHERE 1=1 " . $where_clause;

if($stmt_stats = mysqli_prepare($link, $stats_query)) {
    if(!empty($param_types)) {
        mysqli_stmt_bind_param($stmt_stats, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt_stats);
    $stats_result = mysqli_stmt_get_result($stmt_stats);
    
    if($stats_row = mysqli_fetch_assoc($stats_result)) {
        $total_transactions = $stats_row['total_transactions'];
        $total_clients = $stats_row['total_clients'];
        $total_sales = $stats_row['total_sales'];
        $total_products = $stats_row['total_products'];
    }
}

// Get all clients for the filter dropdown
$clients = [];
$sql_clients = "SELECT id, name FROM client ORDER BY name ASC";
if($result_clients = mysqli_query($link, $sql_clients)){
    while($row_client = mysqli_fetch_assoc($result_clients)) {
        $clients[] = $row_client;
    }
    mysqli_free_result($result_clients);
}

// Pagination
$items_per_page = 10;
$total_pages_query = "SELECT COUNT(DISTINCT achats.id_achat) as count FROM achats 
                     INNER JOIN client ON achats.id_utilisateur = client.id
                     WHERE 1=1 " . $where_clause;

$total_items = 0;
if($stmt_count = mysqli_prepare($link, $total_pages_query)) {
    if(!empty($param_types)) {
        mysqli_stmt_bind_param($stmt_count, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    $count_result = mysqli_stmt_get_result($stmt_count);
    if($count_row = mysqli_fetch_assoc($count_result)) {
        $total_items = $count_row['count'];
    }
}

$total_pages = ceil($total_items / $items_per_page);
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Add pagination to query
$sql_achats .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $items_per_page;
$param_types .= "ii";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <!-- Date Picker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
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
        
        .stats-card .icon {
            opacity: 0.3;
            font-size: 2rem;
        }
        
        .table-container {
            background: white;
            border-radius: 5px;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            padding: 20px;
        }
        
        .top-bar {
            padding: 1rem;
            background: white;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            margin-bottom: 1.5rem;
            border-radius: 5px;
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
        
        .badge-product-count {
            font-size: 0.7rem;
            padding: 0.25em 0.6em;
            border-radius: 50%;
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
        
        .purchase-product-list {
            max-height: 150px;
            overflow-y: auto;
            padding: 0.5rem;
            background-color: #f8f9fc;
            border-radius: 0.35rem;
            font-size: 0.85rem;
        }
        
        .purchase-product-list ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }
        
        .modal-header {
            background: var(--primary-color);
            color: white;
        }
        
        .purchase-date {
            font-size: 0.85rem;
            color: #858796;
        }
        
        .table th {
            background-color: #f8f9fc;
            font-weight: 500;
            color: #5a5c69;
            border-top: none !important;
        }
        
        .pagination {
            margin-bottom: 0;
        }
        
        .pagination .page-link {
            color: var(--primary-color);
            border: 1px solid #dddfeb;
            line-height: 1.25;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .pagination .page-item.disabled .page-link {
            color: #858796;
        }
        
        .datepicker {
            border-radius: 4px;
        }
        
        .footer {
            padding: 20px 0;
            margin-top: 20px;
            font-size: 0.8rem;
            color: #858796;
            border-top: 1px solid #e3e6f0;
        }
        
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
                <a class="nav-link " href="dashboard.php">
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
                <h4 class="m-0">Purchase History</h4>
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

        <!-- Dashboard Stats -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-sales">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Sales</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_sales, 2); ?> DH</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-transactions">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Transactions</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_transactions); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-receipt fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-products">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Products Sold</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_products); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-boxes fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-clients">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Unique Clients</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_clients); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-check fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row">
                <div class="col-md-4 mb-3">
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
                    <label for="date_start">From Date</label>
                    <div class="input-group date">
                        <input type="text" class="form-control datepicker" id="date_start" name="date_start" value="<?php echo $filter_date_start; ?>" placeholder="YYYY-MM-DD">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="date_end">To Date</label>
                    <div class="input-group date">
                        <input type="text" class="form-control datepicker" id="date_end" name="date_end" value="<?php echo $filter_date_end; ?>" placeholder="YYYY-MM-DD">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>
                        <a href="historique.php" class="btn btn-reset">
                            <i class="fas fa-redo mr-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Purchase History Table -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="m-0 font-weight-bold text-primary">Purchase Transactions</h5>
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

            <?php
            // Execute statement with prepared parameters
            if($stmt = mysqli_prepare($link, $sql_achats)) {
                if(!empty($param_types)) {
                    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
                }
                
                mysqli_stmt_execute($stmt);
                $result_achats = mysqli_stmt_get_result($stmt);
                
                if(mysqli_num_rows($result_achats) > 0) {
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-hover" id="purchaseTable">';
                    echo "<thead>";
                    echo "<tr>";
                    echo "<th>ID</th>";
                    echo "<th>Client</th>";
                    echo "<th>Total Amount (DH)</th>";
                    echo "<th>Date</th>";
                    echo "<th>Products</th>";
                    echo "<th>Actions</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";
                    
                    while($row_achat = mysqli_fetch_array($result_achats)) {
                        $purchase_date = new DateTime($row_achat['date_achat']);
                        $formatted_date = $purchase_date->format("M d, Y");
                        $formatted_time = $purchase_date->format("H:i");
                        
                        echo "<tr>";
                        echo "<td>#" . htmlspecialchars($row_achat['id_achat']) . "</td>";
                        echo "<td>";
                        echo "<a href='dashboard.php?client_id=" . htmlspecialchars($row_achat['client_id']) . "' class='font-weight-bold text-primary'>" . htmlspecialchars($row_achat['nom_utilisateur']) . "</a>";
                        echo "</td>";
                        echo "<td>" . number_format($row_achat['montant_total'], 2) . " DH</td>";
                        echo "<td>";
                        echo "<span class='font-weight-bold'>" . $formatted_date . "</span><br>";
                        echo "<span class='purchase-date'>" . $formatted_time . "</span>";
                        echo "</td>";
                        
                        echo "<td>";
                        echo "<span class='badge badge-primary badge-product-count'>" . $row_achat['product_count'] . "</span> ";
                        echo "<button type='button' class='btn btn-sm btn-link view-products' data-toggle='modal' data-target='#productsModal' data-achat='" . $row_achat['id_achat'] . "'>";
                        echo "View Products";
                        echo "</button>";
                        echo "</td>";
                        
                        echo "<td>";
                        echo "<div class='btn-group'>";
                        echo "<a href='view_purchase.php?id=" . $row_achat['id_achat'] . "' class='btn btn-sm btn-info' title='Details'>";
                        echo "<i class='fas fa-eye'></i>";
                        echo "</a>";
                        echo "<a href='print_invoice.php?id=" . $row_achat['id_achat'] . "' class='btn btn-sm btn-secondary ml-1' title='Print Receipt'>";
                        echo "<i class='fas fa-print'></i>";
                        echo "</a>";
                        echo "</div>";
                        echo "</td>";
                        
                        echo "</tr>";
                    }
                    
                    echo "</tbody>";
                    echo "</table>";
                    echo "</div>";
                    
                } else {
                    echo '<div class="alert alert-info"><em>No purchase records found.</em></div>';
                }
                
                mysqli_stmt_close($stmt);
                
            } else {
                echo '<div class="alert alert-danger">Error preparing statement: ' . mysqli_error($link) . '</div>';
            }
            
            // Close connection
            mysqli_close($link);
            ?>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="small text-muted">
                    Showing <?php echo min(($current_page - 1) * $items_per_page + 1, $total_items); ?> to 
                    <?php echo min($current_page * $items_per_page, $total_items); ?> of 
                    <?php echo $total_items; ?> entries
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php if($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo '?page=1' . 
                                    ($filter_client ? '&client_id='.$filter_client : '') . 
                                    ($filter_date_start ? '&date_start='.$filter_date_start : '') . 
                                    ($filter_date_end ? '&date_end='.$filter_date_end : ''); ?>" aria-label="First">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo '?page='.($current_page-1) . 
                                    ($filter_client ? '&client_id='.$filter_client : '') . 
                                    ($filter_date_start ? '&date_start='.$filter_date_start : '') . 
                                    ($filter_date_end ? '&date_end='.$filter_date_end : ''); ?>">
                                    Previous
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">&laquo;</span>
                            </li>
                            <li class="page-item disabled">
                                <span class="page-link">Previous</span>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        // Calculate range of pages to show
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Show first page if not in range
                        if($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1' . 
                                ($filter_client ? '&client_id='.$filter_client : '') . 
                                ($filter_date_start ? '&date_start='.$filter_date_start : '') . 
                                ($filter_date_end ? '&date_end='.$filter_date_end : '') . '">1</a></li>';
                            if($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        // Show page links in range
                        for($i = $start_page; $i <= $end_page; $i++) {
                            echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">';
                            echo '<a class="page-link" href="?page=' . $i . 
                                ($filter_client ? '&client_id='.$filter_client : '') . 
                                ($filter_date_start ? '&date_start='.$filter_date_start : '') . 
                                ($filter_date_end ? '&date_end='.$filter_date_end : '') . '">' . $i . '</a>';
                            echo '</li>';
                        }
                        
                        // Show last page if not in range
                        if($end_page < $total_pages) {
                            if($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . 
                                ($filter_client ? '&client_id='.$filter_client : '') . 
                                ($filter_date_start ? '&date_start='.$filter_date_start : '') . 
                                ($filter_date_end ? '&date_end='.$filter_date_end : '') . '">' . $total_pages . '</a></li>';
                        }
                        ?>
                        
                        <?php if($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo '?page='.($current_page+1) . 
                                    ($filter_client ? '&client_id='.$filter_client : '') . 
                                    ($filter_date_start ? '&date_start='.$filter_date_start : '') . 
                                    ($filter_date_end ? '&date_end='.$filter_date_end : ''); ?>">
                                    Next
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo '?page='.$total_pages . 
                                    ($filter_client ? '&client_id='.$filter_client : '') . 
                                    ($filter_date_start ? '&date_start='.$filter_date_start : '') . 
                                    ($filter_date_end ? '&date_end='.$filter_date_end : ''); ?>" aria-label="Last">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">Next</span>
                            </li>
                            <li class="page-item disabled">
                                <span class="page-link">&raquo;</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
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

    <!-- Products Modal -->
    <div class="modal fade" id="productsModal" tabindex="-1" role="dialog" aria-labelledby="productsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productsModalLabel">Purchase Products</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading products...</p>
                    </div>
                    <div class="product-list-container" style="display: none;">
                        <ul class="product-list list-group"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <!-- Date Picker -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    
    <script>
        $(document).ready(function(){
            // Initialize datepickers
            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true
            });
            
            // Product modal
            $('.view-products').on('click', function() {
                const achatId = $(this).data('achat');
                const modal = $('#productsModal');
                
                // Reset modal and show loading spinner
                modal.find('.product-list').html('');
                modal.find('.loading-spinner').show();
                modal.find('.product-list-container').hide();
                
                // Mock API call - in real app, you would fetch from server
                // Since we don't have an actual API endpoint, we'll simulate it
                setTimeout(function() {
                    $.ajax({
                        url: 'get_purchase_products.php',
                        type: 'POST',
                        data: {purchase_id: achatId},
                        dataType: 'json',
                        success: function(data) {
                            let productHtml = '';
                            if(data && data.length > 0) {
                                data.forEach(function(product) {
                                    productHtml += `
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="font-weight-bold">${product.nom}</span>
                                                <small class="d-block text-muted">Unit Price: ${product.prix_unitaire} DH</small>
                                            </div>
                                            <span class="badge badge-primary badge-pill">Qty: ${product.quantite}</span>
                                        </li>
                                    `;
                                });
                            } else {
                                productHtml = '<li class="list-group-item">No products found for this purchase.</li>';
                            }
                            
                            modal.find('.product-list').html(productHtml);
                            modal.find('.loading-spinner').hide();
                            modal.find('.product-list-container').show();
                        },
                        error: function() {
                            modal.find('.product-list').html('<li class="list-group-item text-danger">Error loading products.</li>');
                            modal.find('.loading-spinner').hide();
                            modal.find('.product-list-container').show();
                        }
                    });
                }, 500);
            });
            
            // Highlight current nav item
            $(".nav-link").each(function() {
                if ($(this).attr('href') === window.location.pathname.split('/').pop()) {
                    $(this).addClass('active');
                }
            });
        });
    </script>
</body>
</html>