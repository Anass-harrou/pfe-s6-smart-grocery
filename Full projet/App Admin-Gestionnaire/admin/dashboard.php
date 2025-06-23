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

// Dashboard statistics
$stats = [
    'total_clients' => 0,
    'total_balance' => 0,
    'recent_purchases' => 0,
    'active_clients' => 0
];

// Get total clients count
$sql_count = "SELECT COUNT(*) as total FROM client";
if($result_count = mysqli_query($link, $sql_count)){
    $row_count = mysqli_fetch_assoc($result_count);
    $stats['total_clients'] = $row_count['total'];
}

// Get total balance
$sql_balance = "SELECT SUM(solde) as total_balance FROM client";
if($result_balance = mysqli_query($link, $sql_balance)){
    $row_balance = mysqli_fetch_assoc($result_balance);
    $stats['total_balance'] = $row_balance['total_balance'];
}

// Get recent purchases (last 30 days)
$sql_purchases = "SELECT COUNT(*) as recent FROM achats WHERE date_achat >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
if($result_purchases = mysqli_query($link, $sql_purchases)){
    $row_purchases = mysqli_fetch_assoc($result_purchases);
    $stats['recent_purchases'] = $row_purchases['recent'];
}

// Get active clients (with purchases in last 60 days)
$sql_active = "SELECT COUNT(DISTINCT id_utilisateur) as active FROM achats WHERE date_achat >= DATE_SUB(NOW(), INTERVAL 60 DAY)";
if($result_active = mysqli_query($link, $sql_active)){
    $row_active = mysqli_fetch_assoc($result_active);
    $stats['active_clients'] = $row_active['active'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Client Management</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom Styles -->
    <style>
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
        
        .card-clients { border-left-color: var(--primary-color); }
        .card-balance { border-left-color: var(--success-color); }
        .card-purchases { border-left-color: var(--info-color); }
        .card-active { border-left-color: var(--warning-color); }
        
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
        
        .top-bar .search-box {
            border-radius: 25px;
            background: #f1f3f9;
            border: none;
            padding-left: 15px;
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
        
        table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        table th {
            background-color: #f8f9fc;
            font-weight: 500;
            color: #5a5c69;
            border-top: none !important;
        }
        
        .table-action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            margin-right: 5px;
        }
        
        .btn-view { 
            background-color: rgba(78, 115, 223, 0.1); 
            color: var(--primary-color);
        }
        
        .btn-edit { 
            background-color: rgba(28, 200, 138, 0.1); 
            color: var(--success-color);
        }
        
        .btn-delete { 
            background-color: rgba(231, 74, 59, 0.1); 
            color: var(--danger-color);
        }
        
        .table-action-btn:hover {
            transform: scale(1.1);
        }
        
        .btn-add {
            border-radius: 50px;
            padding: 6px 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .footer {
            padding: 20px 0;
            margin-top: 20px;
            font-size: 0.8rem;
            color: #858796;
            border-top: 1px solid #e3e6f0;
        }
        
        .badge-status {
            border-radius: 50px;
            padding: 5px 12px;
            font-size: 0.75rem;
            font-weight: 500;
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
            <small class="text">Administrator</small>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
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
                <h4 class="m-0">Dashboard</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="input-group mr-3" style="width: 250px;">
                    <input type="text" class="form-control search-box" placeholder="Search...">
                    <div class="input-group-append">
                        <button class="btn btn-outline-primary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
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
                <div class="card border-0 h-100 py-2 stats-card card-clients">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Clients</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_clients']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-balance">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Balance</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_balance'], 2); ?> DH</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-purchases">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Recent Purchases</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['recent_purchases']); ?></div>
                                <div class="small text-muted">(Last 30 days)</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-basket fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 h-100 py-2 stats-card card-active">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Active Clients</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['active_clients']); ?></div>
                                <div class="small text-muted">(With recent purchases)</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-check fa-2x text-gray-300 icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client List Section -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="m-0 font-weight-bold text-primary">Client Management</h5>
                <a href="create.php" class="btn btn-primary btn-add">
                    <i class="fas fa-plus-circle mr-1"></i> Add New Client
                </a>
            </div>
            
            <?php
            // Attempt select query execution
            $sql = "SELECT c.*, 
                    (SELECT COUNT(*) FROM achats WHERE id_utilisateur = c.id) as purchase_count,
                    (SELECT MAX(date_achat) FROM achats WHERE id_utilisateur = c.id) as last_purchase
                    FROM client c
                    ORDER BY c.id DESC";
            
            if($result = mysqli_query($link, $sql)){
                if(mysqli_num_rows($result) > 0){
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-hover">';
                    echo "<thead>";
                    echo "<tr>";
                    echo "<th>ID</th>";
                    echo "<th>Name</th>";
                    echo "<th>Email</th>";
                    echo "<th>Address</th>";
                    echo "<th>Balance</th>";
                    echo "<th>Order No.</th>";
                    echo "<th>Purchases</th>";
                    echo "<th>Last Activity</th>";
                    echo "<th>Actions</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";
                    
                    while($row = mysqli_fetch_array($result)){
                        // Calculate client status based on purchase activity
                        $status = "Inactive";
                        $status_class = "badge-secondary";
                        
                        if(!empty($row['last_purchase'])) {
                            $last_purchase_date = new DateTime($row['last_purchase']);
                            $now = new DateTime();
                            $days_since = $now->diff($last_purchase_date)->days;
                            
                            if($days_since <= 30) {
                                $status = "Active";
                                $status_class = "badge-success";
                            } else if($days_since <= 90) {
                                $status = "Recent";
                                $status_class = "badge-primary";
                            }
                        }
                        
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['email'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['address'] ?? 'N/A') . "</td>";
                        echo "<td>" . number_format($row['solde'], 2) . " DH</td>";
                        echo "<td>" . htmlspecialchars($row['num_commande'] ?? 'N/A') . "</td>";
                        echo "<td>" . $row['purchase_count'] . "</td>";
                        echo "<td>";
                        if(!empty($row['last_purchase'])) {
                            echo "<span class='badge " . $status_class . " badge-status'>" . $status . "</span><br>";
                            echo "<small class='text-muted'>" . date("Y-m-d", strtotime($row['last_purchase'])) . "</small>";
                        } else {
                            echo "<span class='badge badge-secondary badge-status'>Never</span>";
                        }
                        echo "</td>";
                        echo "<td>";
                        echo '<a href="read.php?id='. $row['id'] .'" class="table-action-btn btn-view" title="View"><i class="fas fa-eye"></i></a>';
                        echo '<a href="update.php?id='. $row['id'] .'" class="table-action-btn btn-edit" title="Edit"><i class="fas fa-edit"></i></a>';
                        echo '<a href="delete.php?id='. $row['id'] .'" class="table-action-btn btn-delete" title="Delete"><i class="fas fa-trash"></i></a>';
                        echo "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody>";
                    echo "</table>";
                    echo "</div>";
                    
                    // Free result set
                    mysqli_free_result($result);
                } else{
                    echo '<div class="alert alert-info"><em>No records were found.</em></div>';
                }
            } else{
                echo '<div class="alert alert-danger">Oops! Something went wrong. Please try again later. Error: ' . mysqli_error($link) . '</div>';
            }

            // Close connection
            mysqli_close($link);
            ?>
        </div>

        <!-- Footer -->
        <footer class="footer text-center">
            <div>
                <span>Current Date and Time (UTC): <?php echo date('Y-m-d H:i:s'); ?> | User: <?php echo htmlspecialchars($_SESSION['user']['username']); ?></span>
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
    
    <script>
        $(document).ready(function(){
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Toggle sidebar for mobile
            $("#sidebarToggle").on('click', function(e) {
                e.preventDefault();
                $(".sidebar").toggleClass("active");
                $(".content").toggleClass("active");
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