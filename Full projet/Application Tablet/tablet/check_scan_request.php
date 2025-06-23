<?php
session_start();
header('Content-Type: application/json');

// Database connection
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

function sanitizeInput($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

function process_scan_requests($conn) {
    // Get pending requests ordered by timestamp (oldest first)
    $sql = "SELECT id, product_id, action, quantity, timestamp FROM scan_requests 
            WHERE status = 'pending' 
            ORDER BY timestamp ASC 
            LIMIT 10"; // Process up to 10 requests at a time
            
    $result = $conn->query($sql);
    $processed_count = 0;
    $notifications = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $scan_id = $row['id'];
            $product_id = sanitizeInput($conn, $row['product_id']);
            $action = $row['action'];
            $quantity = intval($row['quantity']);
            
            // Get product details
            $sql_product = "SELECT id, nom, prix, image, quantite FROM produits WHERE id = ?";
            $stmt = $conn->prepare($sql_product);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result_product = $stmt->get_result();
            
            if ($result_product->num_rows > 0) {
                $product = $result_product->fetch_assoc();
                $available_quantity = intval($product['quantite']);
                $product_name = htmlspecialchars($product['nom']);
                
                // Initialize cart if not set
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                // Process according to action
                if ($action == 'add') {
                    if ($quantity > 0) {
                        if (isset($_SESSION['cart'][$product_id])) {
                            // Product already in cart, update quantity
                            $current_quantity = intval($_SESSION['cart'][$product_id]['quantity']);
                            $new_quantity = $current_quantity + $quantity;
                            
                            if ($new_quantity > $available_quantity) {
                                $new_quantity = $available_quantity;
                                $notifications[] = [
                                    'message' => "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.",
                                    'type' => 'warning'
                                ];
                            } else {
                                $notifications[] = [
                                    'message' => "Quantité de \"$product_name\" mise à jour.",
                                    'type' => 'success'
                                ];
                            }
                            
                            $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                        } else {
                            // New product in cart
                            if ($quantity > $available_quantity) {
                                $quantity = $available_quantity;
                                $notifications[] = [
                                    'message' => "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.",
                                    'type' => 'warning'
                                ];
                            }
                            
                            $_SESSION['cart'][$product_id] = [
                                'id' => $product['id'],
                                'nom' => $product['nom'],
                                'prix' => $product['prix'],
                                'image' => $product['image'],
                                'quantity' => $quantity,
                            ];
                            
                            $notifications[] = [
                                'message' => "\"$product_name\" ajouté au panier.",
                                'type' => 'success'
                            ];
                        }
                    }
                } elseif ($action == 'remove') {
                    if (isset($_SESSION['cart'][$product_id])) {
                        $current_quantity = intval($_SESSION['cart'][$product_id]['quantity']);
                        $new_quantity = $current_quantity - $quantity;
                        
                        if ($new_quantity <= 0) {
                            unset($_SESSION['cart'][$product_id]);
                            $notifications[] = [
                                'message' => "\"$product_name\" supprimé du panier.",
                                'type' => 'info'
                            ];
                        } else {
                            $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                            $notifications[] = [
                                'message' => "Quantité de \"$product_name\" réduite.",
                                'type' => 'info'
                            ];
                        }
                    } else {
                        $notifications[] = [
                            'message' => "\"$product_name\" n'est pas dans le panier.",
                            'type' => 'error'
                        ];
                    }
                }
                
                // Mark request as processed
                $update_sql = "UPDATE scan_requests SET status = 'processed' WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $scan_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                $processed_count++;
            } else {
                // Product not found, mark as error
                $update_sql = "UPDATE scan_requests SET status = 'error' WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $scan_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                $notifications[] = [
                    'message' => "Produit ID:$product_id introuvable.",
                    'type' => 'error'
                ];
            }
            
            $stmt->close();
        }
    }
    
    // Set the latest notification for display (if any)
    if (!empty($notifications)) {
        $latest = end($notifications);
        $_SESSION['notification'] = $latest['message'];
        $_SESSION['notification_type'] = $latest['type'];
    }
    
    return [
        'processed' => $processed_count,
        'notifications' => $notifications
    ];
}

// Connect to database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Process scan requests
$result = process_scan_requests($conn);

// Calculate total
$total_amount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total_amount += $item['prix'] * $item['quantity'];
    }
}

// Return updated cart information
echo json_encode([
    'success' => true,
    'processed' => $result['processed'],
    'notifications' => $result['notifications'],
    'cart_count' => count($_SESSION['cart'] ?? []),
    'cart_total' => number_format($total_amount, 2)
]);

$conn->close();

$(document).ready(function() {
    // Existing toastr options...
    toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": true,
            "timeOut": "3000",
            "extendedTimeOut": "1000"
        };
    
    // Function to update the cart display, QR code, and user balance via AJAX
    function updateCartDisplay() {
        $.ajax({
            url: 'index.php?update_cart_display=1&_=' + new Date().getTime(),
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#cart-content').html(response.cart_html);
                
                // Display notification if any
                if (response.notification) {
                    if (response.notification_type === 'success') {
                        toastr.success(response.notification);
                    } else if (response.notification_type === 'warning') {
                        toastr.warning(response.notification);
                    } else if (response.notification_type === 'info') {
                        toastr.info(response.notification);
                    } else if (response.notification_type === 'error') {
                        toastr.error(response.notification);
                    } else {
                        toastr.info(response.notification); // Default to info
                    }
                }

                // --- Real-time QR Code Update ---
                var qrCodeSection = $('#qrCodeSection');
                var qrCodeImage = $('#qrCodeImage');
                var qrTotalAmount = $('#qrTotalAmount');

                if (response.qr_code_path) { 
                    qrCodeImage.attr('src', response.qr_code_path + '?_=' + new Date().getTime());
                    qrTotalAmount.text(response.qr_total_amount);
                    qrCodeSection.show(); 
                } else {
                    qrCodeSection.hide();
                }
                // --- End Real-time QR Code Update ---

                // --- Update User Balance Display ---
                var currentBalanceDisplay = $('#currentBalanceDisplay');
                if (currentBalanceDisplay.length && response.user_solde_display !== undefined) {
                    currentBalanceDisplay.text(response.user_solde_display);
                }
                // --- End Update User Balance Display ---
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
                toastr.error('Erreur lors de la mise à jour du panier');
            }
        });
    }

    // Handle clicks on "Ajouter au panier" buttons
    $(document).on('click', '.add-to-cart-btn', function() {
        var productId = $(this).data('product-id');
        $.post('scan_api.php', {
            product_id: productId,
            action: 'add',
            quantity: 1
        }, function(response) {
            updateCartDisplay(); // Refresh cart display and QR code after successful addition
        }, 'json')
        .fail(function(xhr, status, error) {
            console.error("AJAX error:", error);
            toastr.error('Erreur lors de l\'ajout au panier');
        });
    });

    // Initial update of the cart display and QR code when the page loads
    updateCartDisplay();

    // --- Polling Mechanism ---
    var pollingInterval = 3000; // Poll every 3 seconds
    
    function pollForUpdates() {
        checkScanRequests(); // Check for new scan requests
    }

    // Start polling when the document is ready
    setInterval(pollForUpdates, pollingInterval);
    // --- End Polling Mechanism ---
});