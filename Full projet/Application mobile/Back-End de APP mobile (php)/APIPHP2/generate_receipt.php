<?php
// Include FPDF library - make sure this path is correct
require('vendor/fpdf/fpdf.php');

// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

// Initialize variables
$receipt_id = null;
$format = 'pdf';

// Check if receipt ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $receipt_id = intval($_GET['id']);
} else {
    die("Error: No receipt ID provided");
}

// Check if format is specified
if (isset($_GET['format']) && in_array($_GET['format'], ['pdf', 'csv'])) {
    $format = $_GET['format'];
}

try {
    // Connect to database
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get purchase details
    $purchase_query = "
        SELECT a.id_achat, a.id_utilisateur, a.date_achat, a.montant_total,
               c.name as customer_name, c.email as customer_email, c.phone as customer_phone
        FROM achats a
        JOIN client c ON a.id_utilisateur = c.id
        WHERE a.id_achat = ?
    ";

    $purchase_stmt = $conn->prepare($purchase_query);
    $purchase_stmt->bind_param("i", $receipt_id);
    $purchase_stmt->execute();
    $purchase_result = $purchase_stmt->get_result();

    if ($purchase_result->num_rows === 0) {
        throw new Exception("Receipt not found");
    }

    $purchase = $purchase_result->fetch_assoc();
    $purchase_stmt->close();

    // Get purchase items
    $items_query = "
        SELECT ap.id_achat_produit, ap.id_produit, ap.quantite, ap.prix_unitaire,
               p.nom as product_name, p.categorie as product_category
        FROM achat_produits ap
        JOIN produits p ON ap.id_produit = p.id
        WHERE ap.id_achat = ?
    ";

    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param("i", $receipt_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $items = array();

    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }

    $items_stmt->close();
    $conn->close();

    // Store company details
    $company = array(
        'name' => 'Smart Grocery',
        'address' => '123 Smart Street, Anytown, Morocco',
        'phone' => '+212 612345678',
        'email' => 'contact@smartgrocery.ma',
        'website' => 'www.smartgrocery.ma',
    );

    // Generate receipt based on format
    if ($format === 'pdf') {
        // Generate PDF receipt
        generatePDF($purchase, $items, $company);
    } else {
        // Generate CSV receipt
        generateCSV($purchase, $items, $company);
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

/**
 * Generate PDF receipt
 */
function generatePDF($purchase, $items, $company) {
    // Create new PDF document
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('Arial', 'B', 16);
    
    // Company info
    $pdf->Cell(0, 10, $company['name'], 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, $company['address'], 0, 1, 'C');
    $pdf->Cell(0, 6, 'Phone: ' . $company['phone'], 0, 1, 'C');
    $pdf->Cell(0, 6, 'Email: ' . $company['email'], 0, 1, 'C');
    
    // Receipt title
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'PURCHASE RECEIPT', 0, 1, 'C');
    
    // Receipt details
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Receipt Number: #' . $purchase['id_achat'], 0, 1);
    $pdf->Cell(0, 8, 'Date: ' . date('Y-m-d H:i', strtotime($purchase['date_achat'])), 0, 1);
    
    // Customer info
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Customer: ' . $purchase['customer_name'], 0, 1);
    if (!empty($purchase['customer_email'])) {
        $pdf->Cell(0, 6, 'Email: ' . $purchase['customer_email'], 0, 1);
    }
    if (!empty($purchase['customer_phone'])) {
        $pdf->Cell(0, 6, 'Phone: ' . $purchase['customer_phone'], 0, 1);
    }
    
    // Items table
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Purchased Items:', 0, 1);
    
    // Table header
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
    $pdf->Cell(80, 7, 'Item', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Price', 1, 0, 'R', true);
    $pdf->Cell(20, 7, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Total', 1, 1, 'R', true);
    
    // Table content
    $pdf->SetFont('Arial', '', 10);
    $counter = 1;
    foreach ($items as $item) {
        $totalPrice = $item['quantite'] * $item['prix_unitaire'];
        
        $pdf->Cell(10, 7, $counter, 1, 0, 'C');
        $pdf->Cell(80, 7, $item['product_name'], 1, 0, 'L');
        $pdf->Cell(30, 7, number_format($item['prix_unitaire'], 2) . ' MAD', 1, 0, 'R');
        $pdf->Cell(20, 7, $item['quantite'], 1, 0, 'C');
        $pdf->Cell(30, 7, number_format($totalPrice, 2) . ' MAD', 1, 1, 'R');
        
        $counter++;
    }
    
    // Total
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(140, 10, 'Total:', 0, 0, 'R');
    $pdf->Cell(30, 10, number_format($purchase['montant_total'], 2) . ' MAD', 0, 1, 'R');
    
    // Footer
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 6, 'Thank you for shopping with us!', 0, 1, 'C');
    $pdf->Cell(0, 6, 'This receipt was generated on ' . date('Y-m-d H:i:s') . '.', 0, 1, 'C');
    
    // Output PDF
    $pdf->Output('D', 'Receipt_' . $purchase['id_achat'] . '.pdf');
    exit;
}

/**
 * Generate CSV receipt
 */
function generateCSV($purchase, $items, $company) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Receipt_' . $purchase['id_achat'] . '.csv');
    
    // Create a file pointer
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Company details
    fputcsv($output, array('Company', $company['name']));
    fputcsv($output, array('Address', $company['address']));
    fputcsv($output, array('Phone', $company['phone']));
    fputcsv($output, array('Email', $company['email']));
    fputcsv($output, array('Website', $company['website']));
    fputcsv($output, array());
    
    // Receipt details
    fputcsv($output, array('PURCHASE RECEIPT'));
    fputcsv($output, array('Receipt Number', '#' . $purchase['id_achat']));
    fputcsv($output, array('Date', date('Y-m-d H:i', strtotime($purchase['date_achat']))));
    fputcsv($output, array());
    
    // Customer details
    fputcsv($output, array('Customer', $purchase['customer_name']));
    if (!empty($purchase['customer_email'])) {
        fputcsv($output, array('Email', $purchase['customer_email']));
    }
    if (!empty($purchase['customer_phone'])) {
        fputcsv($output, array('Phone', $purchase['customer_phone']));
    }
    fputcsv($output, array());
    
    // Items header
    fputcsv($output, array('PURCHASED ITEMS'));
    fputcsv($output, array('#', 'Item', 'Price (MAD)', 'Quantity', 'Total (MAD)'));
    
    // Items details
    $counter = 1;
    foreach ($items as $item) {
        $totalPrice = $item['quantite'] * $item['prix_unitaire'];
        fputcsv($output, array(
            $counter,
            $item['product_name'],
            number_format($item['prix_unitaire'], 2),
            $item['quantite'],
            number_format($totalPrice, 2)
        ));
        $counter++;
    }
    fputcsv($output, array());
    
    // Total
    fputcsv($output, array('', '', '', 'TOTAL:', number_format($purchase['montant_total'], 2) . ' MAD'));
    fputcsv($output, array());
    
    // Footer
    fputcsv($output, array('Thank you for shopping with us!'));
    fputcsv($output, array('This receipt was generated on ' . date('Y-m-d H:i:s')));
    
    // Close file pointer
    fclose($output);
    exit;
}
?>