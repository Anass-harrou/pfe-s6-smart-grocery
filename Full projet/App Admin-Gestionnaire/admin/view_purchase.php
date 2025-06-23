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
$current_datetime = "2025-06-18 14:52:17"; // Hardcoded as requested
$current_user = "Anass-harrou"; // Hardcoded as requested

// Check if purchase ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: historique.php");
    exit();
}

$purchase_id = intval($_GET['id']);
$purchase_data = null;
$purchase_items = [];
$client_data = null;

// Get purchase details
$sql_purchase = "SELECT a.id_achat, a.date_achat, a.montant_total, 
                       c.id AS client_id, c.name, c.email, c.phone, c.address
                FROM achats a
                INNER JOIN client c ON a.id_utilisateur = c.id
                WHERE a.id_achat = ?";

if($stmt = mysqli_prepare($link, $sql_purchase)) {
    mysqli_stmt_bind_param($stmt, "i", $purchase_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($result)) {
        $purchase_data = $row;
        $client_data = [
            'id' => $row['client_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address']
        ];
    } else {
        // Purchase not found
        header("Location: historique.php");
        exit();
    }
    mysqli_stmt_close($stmt);
}

// Get purchase items
$sql_items = "SELECT p.id, p.nom, p.image, ap.quantite, ap.prix_unitaire, 
                    (ap.quantite * ap.prix_unitaire) AS subtotal
             FROM achat_produits ap
             INNER JOIN produits p ON ap.id_produit = p.id
             WHERE ap.id_achat = ?
             ORDER BY p.nom ASC";

if($stmt = mysqli_prepare($link, $sql_items)) {
    mysqli_stmt_bind_param($stmt, "i", $purchase_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while($row = mysqli_fetch_assoc($result)) {
        $purchase_items[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Close connection
mysqli_close($link);

// Calculate summary
$total_items = 0;
foreach($purchase_items as $item) {
    $total_items += $item['quantite'];
}

// Format purchase date
$purchase_date = new DateTime($purchase_data['date_achat']);
$formatted_date = $purchase_date->format("F d, Y");
$formatted_time = $purchase_date->format("h:i A");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Details #<?php echo $purchase_id; ?> - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
            background: linear-gradient(180deg, var(--primary-color) 0%, #224abe 100%);
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
        
        .top-bar {
            padding: 1rem;
            background: white;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            margin-bottom: 1.5rem;
            border-radius: 5px;
        }
        
        .purchase-container {
            background: white;
            border-radius: 5px;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            padding: 0;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .purchase-header {
            background-color: #4e73df;
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .purchase-body {
            padding: 20px;
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
        
        .client-info {
            background-color: #f8f9fc;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .purchase-summary {
            background-color: #f8f9fc;
            border-radius: 5px;
            padding: 15px;
        }
        
        .purchase-item {
            padding: 15px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .purchase-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #e3e6f0;
        }
        
        .purchase-item .product-name {
            font-weight: bold;
            color: #4e73df;
        }
        
        .purchase-item .product-price {
            color: #5a5c69;
        }
        
        .purchase-item .product-quantity {
            background-color: #4e73df;
            color: white;
            border-radius: 50px;
            padding: 5px 15px;
            font-size: 0.8rem;
        }
        
        .purchase-total {
            border-top: 2px solid #e3e6f0;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .purchase-status {
            position: absolute;
            top: -10px;
            right: 20px;
            background-color: #1cc88a;
            color: white;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 6px rgba(50,50,93,.11), 0 1px 3px rgba(0,0,0,.08);
        }
        
        .action-buttons {
            position: absolute;
            bottom: 20px;
            right: 20px;
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
            <i class="fas fa-store-alt"></i> Smart Grocery
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
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
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
                <h4 class="m-0">Purchase Details</h4>
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

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="historique.php">Purchase History</a></li>
                <li class="breadcrumb-item active">Purchase #<?php echo $purchase_id; ?></li>
            </ol>
        </nav>

        <!-- Purchase Details -->
        <div class="row">
            <div class="col-lg-12">
                <div class="purchase-container">
                    <div class="purchase-header">
                        <div class="purchase-status">Completed</div>
                        <h5 class="mb-0">Purchase #<?php echo $purchase_id; ?></h5>
                        <p class="mb-0 mt-2 text-white-50">
                            <i class="fas fa-calendar-alt mr-2"></i> <?php echo $formatted_date; ?> at <?php echo $formatted_time; ?>
                        </p>
                        <div class="action-buttons">
                            <a href="print_invoice.php?id=<?php echo $purchase_id; ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-print mr-1"></i> Print Invoice
                            </a>
                            <a href="#" class="btn btn-light btn-sm ml-2">
                                <i class="fas fa-envelope mr-1"></i> Email Receipt
                            </a>
                        </div>
                    </div>
                    <div class="purchase-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="client-info">
                                    <h6 class="font-weight-bold">Client Information</h6>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Name:</strong></p>
                                            <p class="mb-3"><?php echo htmlspecialchars($client_data['name']); ?></p>
                                            
                                            <p class="mb-1"><strong>Email:</strong></p>
                                            <p class="mb-0"><?php echo htmlspecialchars($client_data['email'] ?? 'N/A'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Phone:</strong></p>
                                            <p class="mb-3"><?php echo htmlspecialchars($client_data['phone'] ?? 'N/A'); ?></p>
                                            
                                            <p class="mb-1"><strong>Address:</strong></p>
                                            <p class="mb-0"><?php echo htmlspecialchars($client_data['address'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="purchase-summary">
                                    <h6 class="font-weight-bold">Purchase Summary</h6>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Purchase ID:</strong></p>
                                            <p class="mb-3">#<?php echo $purchase_id; ?></p>
                                            
                                            <p class="mb-1"><strong>Date:</strong></p>
                                            <p class="mb-0"><?php echo $formatted_date; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Items:</strong></p>
                                            <p class="mb-3"><?php echo $total_items; ?> items</p>
                                            
                                            <p class="mb-1"><strong>Total Amount:</strong></p>
                                            <p class="mb-0 font-weight-bold text-primary"><?php echo number_format($purchase_data['montant_total'], 2); ?> DH</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="font-weight-bold">Purchased Products</h6>
                            <hr>
                            
                            <?php if(count($purchase_items) > 0): ?>
                                <div class="purchase-items">
                                    <?php foreach($purchase_items as $item): ?>
                                        <div class="purchase-item d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nom']); ?>" class="product-image mr-3">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <a href="#" class="product-name"><?php echo htmlspecialchars($item['nom']); ?></a>
                                                    <div class="text-right">
                                                        <span class="product-price"><?php echo number_format($item['prix_unitaire'], 2); ?> DH</span>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <span class="product-quantity">Qty: <?php echo $item['quantite']; ?></span>
                                                    <span class="font-weight-bold"><?php echo number_format($item['subtotal'], 2); ?> DH</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="purchase-total">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="text-muted mb-0">Thank you for your purchase!</p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-right">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Subtotal:</span>
                                                    <span><?php echo number_format($purchase_data['montant_total'], 2); ?> DH</span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Tax:</span>
                                                    <span>0.00 DH</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="font-weight-bold">Total:</span>
                                                    <span class="font-weight-bold text-primary"><?php echo number_format($purchase_data['montant_total'], 2); ?> DH</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">No products found for this purchase.</div>
                            <?php endif; ?>
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
    
    <script>
        $(document).ready(function(){
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