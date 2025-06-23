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
                       c.id AS client_id, c.name, c.email, c.phone, c.address, c.num_commande
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
            'address' => $row['address'],
            'num_commande' => $row['num_commande']
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
$subtotal = 0;
$total_items = 0;
foreach($purchase_items as $item) {
    $subtotal += $item['subtotal'];
    $total_items += $item['quantite'];
}

// Format purchase date
$purchase_date = new DateTime($purchase_data['date_achat']);
$formatted_date = $purchase_date->format("F d, Y");
$formatted_time = $purchase_date->format("h:i A");
$invoice_number = "INV-" . str_pad($purchase_id, 6, "0", STR_PAD_LEFT);

// Generate unique tracking ID (just for demo)
$tracking_id = "TRK" . date("Ymd") . "-" . $purchase_id;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoice_number; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom Styles -->
    <style>
        body {
            background-color: #f8f9fc;
            color: #5a5c69;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 20px;
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .invoice-header img {
            max-height: 60px;
        }
        
        .invoice-header {
            border-bottom: 1px solid #e3e6f0;
            padding-bottom: 20px;
        }
        
        .invoice-title {
            font-size: 2rem;
            color: #4e73df;
            font-weight: 600;
            margin: 0;
        }
        
        .invoice-details {
            padding: 20px 0;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .invoice-details-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #5a5c69;
        }
        
        .invoice-table th {
            background-color: #f8f9fc;
            color: #5a5c69;
            border-top: 1px solid #e3e6f0;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .invoice-table td {
            border-top: 1px solid #e3e6f0;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .invoice-total {
            margin-top: 20px;
            background-color: #f8f9fc;
            padding: 15px;
            border-radius: 5px;
        }
        
        .invoice-footer {
            margin-top: 40px;
            border-top: 1px solid #e3e6f0;
            padding-top: 20px;
            font-size: 0.85rem;
        }
        
        .barcode {
            margin-top: 20px;
            text-align: center;
        }
        
        .barcode-img {
            max-width: 250px;
        }
        
        .print-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .signature {
            margin-top: 40px;
            border-top: 1px dotted #ccc;
            width: 200px;
        }
        
        @media print {
            body {
                background-color: white;
            }
            
            .invoice-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            
            .print-button {
                display: none;
            }
            
            .barcode-img {
                max-width: 200px;
                height: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button class="btn btn-primary print-button" onclick="window.print();">
        <i class="fas fa-print mr-2"></i> Print Invoice
    </button>

    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="invoice-title">INVOICE</h1>
                <p class="mb-0"><?php echo $invoice_number; ?></p>
            </div>
            <div class="logo">
        <img src="../smart_cart_transparent.png" alt="Smart Grocery Logo" class="img-fluid">
        <span class="logo-text">Smart Grocery</span>
    </div>
        </div>

        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="invoice-details-title">Billed To:</h5>
                    <p class="mb-1"><strong><?php echo htmlspecialchars($client_data['name']); ?></strong></p>
                    <p class="mb-1"><?php echo htmlspecialchars($client_data['address'] ?? 'N/A'); ?></p>
                    <p class="mb-1">Email: <?php echo htmlspecialchars($client_data['email'] ?? 'N/A'); ?></p>
                    <p class="mb-1">Phone: <?php echo htmlspecialchars($client_data['phone'] ?? 'N/A'); ?></p>
                    <p class="mb-0">Client ID: <?php echo htmlspecialchars($client_data['num_commande'] ?? 'N/A'); ?></p>
                </div>
                <div class="col-md-6 text-right">
                    <h5 class="invoice-details-title">Invoice Information:</h5>
                    <p class="mb-1"><strong>Invoice Number:</strong> <?php echo $invoice_number; ?></p>
                    <p class="mb-1"><strong>Date:</strong> <?php echo $formatted_date; ?></p>
                    <p class="mb-1"><strong>Time:</strong> <?php echo $formatted_time; ?></p>
                    <p class="mb-1"><strong>Transaction ID:</strong> <?php echo $purchase_id; ?></p>
                    <p class="mb-0"><strong>Tracking ID:</strong> <?php echo $tracking_id; ?></p>
                </div>
            </div>
        </div>

        <!-- Invoice Items -->
        <div class="invoice-items">
            <h5 class="invoice-details-title mt-4">Purchased Items</h5>
            <div class="table-responsive">
                <table class="table invoice-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Product</th>
                            <th scope="col" class="text-center">Quantity</th>
                            <th scope="col" class="text-right">Unit Price (DH)</th>
                            <th scope="col" class="text-right">Total (DH)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($purchase_items as $index => $item): ?>
                            <tr>
                                <th scope="row"><?php echo $index + 1; ?></th>
                                <td><?php echo htmlspecialchars($item['nom']); ?></td>
                                <td class="text-center"><?php echo $item['quantite']; ?></td>
                                <td class="text-right"><?php echo number_format($item['prix_unitaire'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Invoice Total -->
        <div class="invoice-total">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Payment Method:</strong> In-Store Payment</p>
                    <p class="mb-0"><strong>Items Count:</strong> <?php echo $total_items; ?> items</p>
                </div>
                <div class="col-md-6">
                    <div class="text-right">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?php echo number_format($purchase_data['montant_total'], 2); ?> DH</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sales Tax (0%):</span>
                            <span>0.00 DH</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="font-weight-bold">Total:</span>
                            <span class="font-weight-bold"><?php echo number_format($purchase_data['montant_total'], 2); ?> DH</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Signature Area -->
        <div class="row mt-5">
            <div class="col-md-6">
                <p class="mb-5">Authorized by:</p>
                <div class="signature"></div>
                <p class="mt-2">Smart Grocery Management</p>
            </div>
            <div class="col-md-6 text-right">
                <p class="mb-0">Thank you for your business!</p>
                <p class="mb-0">We appreciate your patronage.</p>
            </div>
        </div>
        
        <!-- Barcode -->
        <div class="barcode">
            <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo $invoice_number; ?>&code=Code128&translate-esc=true" alt="Barcode" class="barcode-img">
            <p class="mb-0 mt-2"><?php echo $invoice_number; ?></p>
        </div>

        <!-- Invoice Footer -->
        <div class="invoice-footer text-center">
            <p class="mb-1">Smart Grocery Store | 123 Main Street, Cityville | contact@smartgrocery.com | +1-234-567-8901</p>
            <p class="mb-0">Generated on <?php echo $current_datetime; ?> by <?php echo $current_user; ?></p>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>