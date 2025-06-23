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

// --- QR LOGIN: If not logged in, generate a QR login token ---
$isLoggedIn = isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    if (!isset($_SESSION['qr_login_token'])) {
        $_SESSION['qr_login_token'] = bin2hex(random_bytes(16));
    }
    $qr_login_token = $_SESSION['qr_login_token'];
    
    // SIMPLIFIED FORMAT: Generate a simple JSON string that's easier to parse
    $qr_data = json_encode([
        'type' => 'login',
        'qr_login_token' => $qr_login_token
    ]);
    
    $qr_code_file = 'qrcodes/qr_login_' . md5($qr_login_token) . '.png';
    $qr_code_full_path = __DIR__ . '/' . $qr_code_file;
    if (!file_exists(dirname($qr_code_full_path))) {
        mkdir(dirname($qr_code_full_path), 0777, true);
    }
    if (!file_exists($qr_code_full_path)) {
        QRcode::png($qr_data, $qr_code_full_path, QR_ECLEVEL_L, 4);
    }
}
// --- RFID Login handling ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rfid_login'])) {
    // This endpoint handles RFID login requests from the Python script
    $rfid_uid = isset($_POST['rfid_uid']) ? $_POST['rfid_uid'] : '';
    
    if (!empty($rfid_uid)) {
        // Check the database for this RFID UID
        $stmt = $conn->prepare("SELECT id, name, email, address, solde, num_commande, phone, bio, rfid_uid FROM client WHERE rfid_uid = ?");
        $stmt->bind_param("s", $rfid_uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Store user information in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'] ?? '';
            $_SESSION['user_address'] = $user['address'] ?? '';
            $_SESSION['user_solde'] = $user['solde'] ?? '0.00';
            $_SESSION['user_num_commande'] = $user['num_commande'] ?? '';
            $_SESSION['user_phone'] = $user['phone'] ?? '';
            $_SESSION['user_bio'] = $user['bio'] ?? '';
            $_SESSION['user_rfid_uid'] = $user['rfid_uid'] ?? '';
            
            // Log successful RFID login
            error_log("RFID Login successful for user: " . $user['name'] . " (ID: " . $user['id'] . ")");
            
            // Return success for programmatic login
            if (isset($_POST['api_call']) && $_POST['api_call'] == 1) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'user_id' => $user['id'], 'user_name' => $user['name']]);
                exit;
            }
            
            // Redirect to homepage for browser-based login
            header("Location: index.php?rfid_success=1");
            exit;
        } else {
            // Log failed login attempt
            error_log("RFID Login failed: UID not found in database: " . $rfid_uid);
            
            if (isset($_POST['api_call']) && $_POST['api_call'] == 1) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'RFID card not registered']);
                exit;
            }
            
            // Set error message for browser-based login
            $_SESSION['rfid_error'] = "Carte RFID non reconnue. Veuillez vous enregistrer.";
            header("Location: index.php?rfid_error=1");
            exit;
        }
        $stmt->close();
    }
}

// --- Check if the RFID login.json file exists (for direct integration with Python script) ---
$rfid_login_file = __DIR__ . '/rfid_login.json';
if (file_exists($rfid_login_file) && !$isLoggedIn) {
    $login_data = json_decode(file_get_contents($rfid_login_file), true);
    $file_timestamp = filemtime($rfid_login_file);
    $current_timestamp = time();
    
    // Only process if the file is less than 10 seconds old
    if (($current_timestamp - $file_timestamp) <= 10 && 
        isset($login_data['rfid_uid']) && !empty($login_data['rfid_uid'])) {
        
        $rfid_uid = $login_data['rfid_uid'];
        
        // Check the database for this RFID UID
        $stmt = $conn->prepare("SELECT id, name, email, address, solde, num_commande, phone, bio, rfid_uid FROM client WHERE rfid_uid = ?");
        $stmt->bind_param("s", $rfid_uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Store user information in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'] ?? '';
            $_SESSION['user_address'] = $user['address'] ?? '';
            $_SESSION['user_solde'] = $user['solde'] ?? '0.00';
            $_SESSION['user_num_commande'] = $user['num_commande'] ?? '';
            $_SESSION['user_phone'] = $user['phone'] ?? '';
            $_SESSION['user_bio'] = $user['bio'] ?? '';
            $_SESSION['user_rfid_uid'] = $user['rfid_uid'] ?? '';
            
            $isLoggedIn = true; // Update login status
            
            // Log successful RFID login
            error_log("RFID Login (file-based) successful for user: " . $user['name'] . " (ID: " . $user['id'] . ")");
            
            // Delete the login file since it's been processed
            @unlink($rfid_login_file);
            
            // Set a success message
            $_SESSION['notification'] = "Connexion par carte RFID réussie.";
            $_SESSION['notification_type'] = 'success';
        } else {
            // Log failed login attempt
            error_log("RFID Login (file-based) failed: UID not found in database: " . $rfid_uid);
            
            // Set an error notification
            $_SESSION['notification'] = "Carte RFID non reconnue. Veuillez vous enregistrer.";
            $_SESSION['notification_type'] = 'error';
        }
        $stmt->close();
        
        // Delete the file regardless of success to prevent repeated processing
        @unlink($rfid_login_file);
    }
}

// --- Check RFID data file for recent scans ---
$rfid_data_path = __DIR__ . '/rfid_data.json';
if (file_exists($rfid_data_path) && !$isLoggedIn) {
    $json_content = @file_get_contents($rfid_data_path);
    
    if ($json_content !== false) {
        $rfid_data = json_decode($json_content, true);
        
        // Check if the JSON was parsed successfully and has the expected structure
        if ($rfid_data !== null && isset($rfid_data['scans']) && 
            is_array($rfid_data['scans']) && !empty($rfid_data['scans'])) {
            
            // Get the most recent scan
            $latest_scan = end($rfid_data['scans']);
            
            // Check if the scan is recent (within the last 10 seconds)
            $scan_time = strtotime($latest_scan['timestamp']);
            $current_time = time();
            
            if (($current_time - $scan_time) <= 10) {
                $rfid_uid = $latest_scan['uid'];
                
                // Check the database for this RFID UID
                $stmt = $conn->prepare("SELECT id, name, email, address, solde, num_commande, phone, bio, rfid_uid FROM client WHERE rfid_uid = ?");
                $stmt->bind_param("s", $rfid_uid);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    
                    // Store user information in session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'] ?? '';
                    $_SESSION['user_address'] = $user['address'] ?? '';
                    $_SESSION['user_solde'] = $user['solde'] ?? '0.00';
                    $_SESSION['user_num_commande'] = $user['num_commande'] ?? '';
                    $_SESSION['user_phone'] = $user['phone'] ?? '';
                    $_SESSION['user_bio'] = $user['bio'] ?? '';
                    $_SESSION['user_rfid_uid'] = $user['rfid_uid'] ?? '';
                    
                    $isLoggedIn = true; // Update login status
                    
                    // Log successful RFID login
                    error_log("RFID Login (data file) successful for user: " . $user['name'] . " (ID: " . $user['id'] . ")");
                    
                    // Set a success message
                    $_SESSION['notification'] = "Connexion par carte RFID réussie.";
                    $_SESSION['notification_type'] = 'success';
                } else {
                    // Log failed login attempt
                    error_log("RFID Login (data file) failed: UID not found in database: " . $rfid_uid);
                    
                    // Set an error notification
                    $_SESSION['notification'] = "Carte RFID non reconnue. Veuillez vous enregistrer.";
                    $_SESSION['notification_type'] = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// --- AJAX endpoint: Check if QR login is authenticated ---
if (isset($_GET['check_qr_login'])) {
    header('Content-Type: application/json');
    $token = $_SESSION['qr_login_token'] ?? '';
    $user_id = null;
    
    if ($token) {
        // CRITICAL FIX: Log the token we're checking for debugging
        error_log("Checking QR login for token: $token");
        
        // FIXED: Using client_id column and adding debug information
        $stmt = $conn->prepare("SELECT client_id, authenticated, expires_at FROM qr_logins WHERE token = ? AND authenticated = 1 AND expires_at > NOW() LIMIT 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // IMPROVED: Better result handling with more detailed logging
        if ($row = $result->fetch_assoc()) {
            $user_id = $row['client_id'];
            error_log("Found authenticated token for user ID: $user_id, expires: {$row['expires_at']}");
        } else {
            // Debug query to identify why token isn't matching
            $debug_stmt = $conn->prepare("SELECT token, client_id, authenticated, expires_at FROM qr_logins WHERE token LIKE ? LIMIT 5");
            $like_token = '%' . substr($token, -8) . '%'; // Search for tokens with similar ending
            $debug_stmt->bind_param("s", $like_token);
            $debug_stmt->execute();
            $debug_result = $debug_stmt->get_result();
            
            $possible_matches = [];
            while ($debug_row = $debug_result->fetch_assoc()) {
                $possible_matches[] = $debug_row;
            }
            
            error_log("No exact match found for token: $token. Similar tokens: " . json_encode($possible_matches));
            $debug_stmt->close();
        }
        
        $stmt->close();
    } else {
        error_log("No QR login token in session");
    }
    
    echo json_encode(['authenticated' => $user_id ? true : false]);
    
    if ($user_id) {
        // User authenticated successfully - load user data
        $stmt = $conn->prepare("SELECT id, name, email, address, solde, num_commande, phone, bio, rfid_uid FROM client WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Store user information in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'] ?? '';
            $_SESSION['user_address'] = $user['address'] ?? '';
            $_SESSION['user_solde'] = $user['solde'] ?? '0.00';
            $_SESSION['user_num_commande'] = $user['num_commande'] ?? '';
            $_SESSION['user_phone'] = $user['phone'] ?? '';
            $_SESSION['user_bio'] = $user['bio'] ?? '';
            $_SESSION['user_rfid_uid'] = $user['rfid_uid'] ?? '';
            
            // Remove QR login token from session since we're now logged in
            unset($_SESSION['qr_login_token']);
            
            error_log("User data loaded for ID $user_id, name: {$user['name']}");
        } else {
            error_log("ERROR: User with ID $user_id not found in database");
        }
        $stmt->close();
    }
    
    $conn->close();
    exit;
}

// --- Android App Endpoint: Authenticate QR login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_login_token'], $_POST['user_id'])) {
    // Debug: log POST data (for troubleshooting)
    file_put_contents('debug_qr_login.txt', date('c')." ".json_encode($_POST)."\n", FILE_APPEND);

    $token = $_POST['qr_login_token'];
    $user_id = intval($_POST['user_id']);
    $stmt = $conn->prepare("INSERT INTO qr_logins (token, client_id, authenticated, expires_at) VALUES (?, ?, 1, DATE_ADD(NOW(), INTERVAL 3 MINUTE)) ON DUPLICATE KEY UPDATE client_id=?, authenticated=1, expires_at=DATE_ADD(NOW(), INTERVAL 3 MINUTE)");
    $stmt->bind_param("sii", $token, $user_id, $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    $conn->close();
    exit;
}

// Function to sanitize input
function sanitizeInput($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// --- Handle standard form submissions from the web page (Register, Login, Update Cart) ---

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $nom = sanitizeInput($conn, $_POST['nom']);
    $email = sanitizeInput($conn, $_POST['email']);
    $password = $_POST['password'];
    $adresse = sanitizeInput($conn, $_POST['adresse']);

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Added columns 'phone' and 'bio' from client table schema, assuming default values
    $sql = "INSERT INTO client (name, address, solde, num_commande, email, password, phone, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    $default_solde = 0.00;
    $default_num_commande = ''; // Or generate a unique one
    $default_phone = '';
    $default_bio = '';

    $stmt->bind_param("ssdsisss", $nom, $adresse, $default_solde, $default_num_commande, $email, $hashed_password, $default_phone, $default_bio);

    if ($stmt->execute()) {
        echo "<script>alert('Inscription réussie ! Veuillez vous connecter.'); window.location.href = 'index.php';</script>";
        exit();
    } else {
        echo "Échec de l'inscription : " . $stmt->error;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $name = sanitizeInput($conn, $_POST['name']);
    $password = $_POST['password'];

    // Select all fields needed for session management
    $sql = "SELECT id, name, email, address, solde, num_commande, id_utilisateur, password, phone, bio, rfid_uid FROM client WHERE name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true); // Regenerate session ID for security

            // Store all relevant user data in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'] ?? '';
            $_SESSION['user_address'] = $user['address'] ?? '';
            $_SESSION['user_solde'] = $user['solde'] ?? '0.00';
            $_SESSION['user_num_commande'] = $user['num_commande'] ?? '';
            $_SESSION['user_id_utilisateur'] = $user['id_utilisateur'] ?? '';
            $_SESSION['user_phone'] = $user['phone'] ?? '';
            $_SESSION['user_bio'] = $user['bio'] ?? '';
            $_SESSION['user_rfid_uid'] = $user['rfid_uid'] ?? '';

            $isLoggedIn = true;
            echo "<script>alert('Connexion réussie !'); window.location.href = 'index.php';</script>";
            exit();
        } else {
            $login_error = "Nom ou mot de passe incorrect.";
        }
    } else {
        $login_error = "Nom ou mot de passe incorrect.";
    }
    $stmt->close();
}

if (isset($_GET['logout'])) {
    session_destroy(); // Destroy all session data
    header("Location: index.php"); // Redirect to home page
    exit();
}

// Fetch products for display on the page
$sql_products = "SELECT id, nom, prix, image, quantite, disponible FROM produits ORDER BY nom ASC";
$result_products = $conn->query($sql_products);

$products = [];
if ($result_products->num_rows > 0) {
    while ($row = $result_products->fetch_assoc()) {
        $products[] = $row;
    }
}

// Initialize cart if not already set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Session variables for Toastr notifications (set by other operations like cart updates or payments)
if (!isset($_SESSION['notification'])) {
    $_SESSION['notification'] = null;
    $_SESSION['notification_type'] = null;
}

// File paths for inter-process communication (tablet cart updates)
$json_file = '/GHMARIIII/tablet/cart_request.json'; // Path for incoming cart requests
$json_file_processed = '/GHMARIIII/tablet/cart_request_processed.json'; // Path for processed requests

// Helper function to move files (for tablet cart updates)
function moveFile($source, $destination) {
    if (!file_exists($source)) {
        error_log("moveFile: Source file does not exist: $source");
        return false;
    }
    return rename($source, $destination);
}

// --- Handle incoming cart updates from tablet/other sources (via JSON file) ---
if (file_exists($json_file) && filesize($json_file) > 0) {
    $json_data = @file_get_contents($json_file);
    if ($json_data !== false) {
        $request_data = json_decode($json_data, true);

        if ($request_data !== null && json_last_error() === JSON_ERROR_NONE) {
            $product_id = isset($request_data['product_id']) ? sanitizeInput($conn, $request_data['product_id']) : null;
            $action = isset($request_data['action']) ? strtolower($request_data['action']) : 'add';
            $quantity = isset($request_data['quantity']) ? intval($request_data['quantity']) : 1;

            if ($product_id) {
                $sql_product = "SELECT id, nom, prix, image, quantite FROM produits WHERE id = ?";
                $stmt = $conn->prepare($sql_product);
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $product = $result->fetch_assoc();
                    $available_quantity = intval($product['quantite']);
                    $product_name = htmlspecialchars($product['nom']);

                    if ($action == 'add') {
                        if ($quantity > 0) {
                            if (isset($_SESSION['cart'][$product_id])) {
                                $current_quantity = intval($_SESSION['cart'][$product_id]['quantity']);
                                $new_quantity = $current_quantity + $quantity;

                                if ($new_quantity > $available_quantity) {
                                    $new_quantity = $available_quantity;
                                    $_SESSION['notification'] = "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.";
                                    $_SESSION['notification_type'] = 'warning';
                                } else {
                                    $_SESSION['notification'] = "Quantité de \"$product_name\" mise à jour.";
                                    $_SESSION['notification_type'] = 'success';
                                }
                                $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                            } else {
                                if ($quantity > $available_quantity) {
                                    $quantity = $available_quantity;
                                    $_SESSION['notification'] = "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.";
                                    $_SESSION['notification_type'] = 'warning';
                                }
                                $_SESSION['cart'][$product_id] = [
                                    'id' => $product['id'],
                                    'nom' => $product['nom'],
                                    'prix' => $product['prix'],
                                    'image' => $product['image'],
                                    'quantity' => $quantity,
                                ];
                                $_SESSION['notification'] = "\"$product_name\" ajouté au panier.";
                                $_SESSION['notification_type'] = 'success';
                            }
                        }
                    } elseif ($action == 'remove') {
                        if (isset($_SESSION['cart'][$product_id])) {
                            $current_quantity = intval($_SESSION['cart'][$product_id]['quantity']);
                            $new_quantity = $current_quantity - $quantity;

                            if ($new_quantity <= 0) {
                                unset($_SESSION['cart'][$product_id]);
                                $_SESSION['notification'] = "\"$product_name\" supprimé du panier.";
                                $_SESSION['notification_type'] = 'info';
                            } else {
                                $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                                $_SESSION['notification'] = "Quantité de \"$product_name\" réduite.";
                                $_SESSION['notification_type'] = 'info';
                            }
                        } else {
                            $_SESSION['notification'] = "\"$product_name\" n'est pas dans le panier.";
                            $_SESSION['notification_type'] = 'error';
                        }
                    } elseif ($action == 'set') {
                        if ($quantity > 0 && $quantity <= $available_quantity) {
                            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                            $_SESSION['notification'] = "Quantité de \"$product_name\" définie sur $quantity.";
                            $_SESSION['notification_type'] = 'success';
                        } elseif ($quantity <= 0) {
                            unset($_SESSION['cart'][$product_id]);
                            $_SESSION['notification'] = "\"$product_name\" supprimé du panier.";
                            $_SESSION['notification_type'] = 'info';
                        } else {
                            $_SESSION['cart'][$product_id]['quantity'] = $available_quantity;
                            $_SESSION['notification'] = "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.";
                            $_SESSION['notification_type'] = 'warning';
                        }
                    }
                }
                $stmt->close();
            }
        }

        // Move the file only after successful processing
        if (!moveFile($json_file, $json_file_processed)) {
            error_log("Failed to move JSON file from $json_file to $json_file_processed");
        }
    }
}


// --- AJAX endpoint for updating cart display and QR code on the web page ---
if (isset($_GET['update_cart_display'])) {
    ob_start(); // Start output buffering to capture generated HTML
    $total_amount_display = 0; // Initialize total for display

    if (empty($_SESSION['cart'])): ?>
        <p>Votre panier est vide.</p>
    <?php else: ?>
        <form method="post" action="">
            <?php foreach ($_SESSION['cart'] as $product_id => $item): 
                $total_amount_display += $item['prix'] * $item['quantity']; ?>
                <div class="cart-item">
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nom']); ?>">
                    <div class="cart-item-details">
                        <h4><?php echo htmlspecialchars($item['nom']); ?></h4>
                        <p>Prix : <?php echo htmlspecialchars(number_format($item['prix'], 2)); ?> dh</p>
                    </div>
                    <div class="cart-item-quantity">
                        <label for="quantity_<?php echo $product_id; ?>">Quantité :</label>
                        <input type="number" id="quantity_<?php echo $product_id; ?>" name="quantity[<?php echo $product_id; ?>]" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0">
                    </div>
                </div>
            <?php endforeach; ?>
            <button type="submit" name="update_cart">Mettre à jour le panier</button>
        </form>
        <div class="cart-total">
            Total : <?php echo htmlspecialchars(number_format($total_amount_display, 2)); ?> dh
        </div>
    <?php endif;

    $cart_html = ob_get_clean(); // Get the buffered HTML content
    
    // --- Generate QR Code Data for AJAX Response (for display on web page) ---
    $qr_code_path = '';
    $current_total_amount_for_qr = $total_amount_display; // Use the calculated total from cart

    // Only generate QR if user is logged in AND cart is not empty
    if ($isLoggedIn && !empty($_SESSION['cart']) && $current_total_amount_for_qr > 0) {
        // We need at least one product ID for the QR data, pick the first one if cart not empty
        reset($_SESSION['cart']); 
        $first_product_in_cart_id = key($_SESSION['cart']); 
        
        // --- IMPORTANT: Ensure this QR data format matches what Android app expects (JSON) ---
        // The Android app is now expecting JSON, so the QR content should be JSON.
        $qr_data_array = [
            'transaction_id' => uniqid('qr_pay_'), // Generate a unique ID for this specific QR
            'amount' => $current_total_amount_for_qr,
            'user_id' => $_SESSION['user_id'] // Send the user's ID from session
        ];
        $qr_data_for_payment = json_encode($qr_data_array);
        // --- END IMPORTANT ---


        // Define file names and paths for saving and accessing the QR image
        $qr_code_file_name = 'qr_payment_' . md5($qr_data_for_payment) . '.png';
        $qr_code_file_path_full = 'qrcodes/' . $qr_code_file_name; // This is the relative path for the <img> src
        $qr_code_save_path = __DIR__ . '/' . $qr_code_file_path_full; // Absolute path for saving to disk

        // Create 'qrcodes' directory if it doesn't exist
        if (!file_exists(dirname($qr_code_save_path))) {
            mkdir(dirname($qr_code_save_path), 0777, true);
        }

        // Generate QR code image only if it doesn't already exist to save resources
        if (!file_exists($qr_code_save_path)) {
            QRcode::png($qr_data_for_payment, $qr_code_save_path, QR_ECLEVEL_L, 4);
        }
        $qr_code_path = $qr_code_file_path_full; // Path to send to JS for img src
    }
    // --- End QR Code Generation Data for AJAX Response ---

    // Fetch and prepare current user balance for real-time display update
    $current_user_solde = '0.00'; // Default if not logged in
    if ($isLoggedIn && isset($_SESSION['user_id'])) {
        $user_id_for_balance = $_SESSION['user_id'];
        $sql_current_solde = "SELECT solde FROM client WHERE id = ?";
        $stmt_current_solde = $conn->prepare($sql_current_solde);
        if ($stmt_current_solde) {
            $stmt_current_solde->bind_param("i", $user_id_for_balance);
            $stmt_current_solde->execute();
            $result_current_solde = $stmt_current_solde->get_result();
            if ($row_current_solde = $result_current_solde->fetch_assoc()) {
                $current_user_solde = number_format($row_current_solde['solde'], 2);
            }
            $stmt_current_solde->close();
        }
    }


    // Prepare JSON response for AJAX
    header('Content-Type: application/json'); // Ensure JSON header is sent
    $response = [
        'cart_html' => $cart_html,
        'notification' => $_SESSION['notification'], 
        'notification_type' => $_SESSION['notification_type'],
        'qr_code_path' => $qr_code_path, // Path to the generated QR code image
        'qr_total_amount' => htmlspecialchars(number_format($current_total_amount_for_qr, 2)), // Total amount for QR display
        'user_solde_display' => $current_user_solde // User's current balance
    ];
    
    echo json_encode($response);

    // Clear notification from session after sending it to the client
    $_SESSION['notification'] = null;
    $_SESSION['notification_type'] = null;

    $conn->close(); // Close connection for AJAX request and exit
    exit;
}

// --- Handle 'Update Cart' from web form ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $product_id => $quantity) {
        $product_id = sanitizeInput($conn, $product_id);
        $quantity = intval($quantity);

        $sql_product = "SELECT id, nom, quantite FROM produits WHERE id = ?";
        $stmt = $conn->prepare($sql_product);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            $available_quantity = intval($product['quantite']);
            $product_name = htmlspecialchars($product['nom']);

            if ($quantity > 0 && $quantity <= $available_quantity) {
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                $_SESSION['notification'] = "Quantité de \"$product_name\" définie sur $quantity.";
                $_SESSION['notification_type'] = 'success';
            } else {
                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$product_id]);
                    $_SESSION['notification'] = "\"$product_name\" supprimé du panier.";
                    $_SESSION['notification_type'] = 'info';
                } else {
                    $_SESSION['cart'][$product_id]['quantity'] = $available_quantity;
                    $_SESSION['notification'] = "La quantité demandée pour \"".htmlspecialchars($product['nom'])."\" dépasse le stock disponible (".$available_quantity."). La quantité a été ajustée.";
                    $_SESSION['notification_type'] = 'warning';
                }
            }
        }
        $stmt->close();
    }
    // After updating cart via form, the next page load (due to form submission)
    // or subsequent polling will pick up the session notification and update.
    // Redirect to self to prevent form resubmission on refresh
    header("Location: index.php");
    exit();
}

// --- Handle 'Payer' button click from web form (direct web payment) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payer'])) {
    if (!empty($_SESSION['cart'])) {
        // Ensure user is logged in for payment
        if (!$isLoggedIn) {
            echo "<script>alert('Veuillez vous connecter pour effectuer un paiement.');</script>";
            echo "<script>window.location.href='index.php';</script>";
            exit();
        }

        $stockInsuffisant = false;
        $produitInsuffisant = '';

        foreach ($_SESSION['cart'] as $product_id => $item) {
            $id = intval($product_id);
            $qty = intval($item['quantity']);

            $sql = "SELECT nom, quantite FROM produits WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $current_stock = intval($row['quantite']);
                if ($qty > $current_stock) {
                    $stockInsuffisant = true;
                    $produitInsuffisant = $row['nom'];
                    break;
                }
            }
            $stmt->close();
        }

        if ($stockInsuffisant) {
            echo "<script>alert('Stock insuffisant pour le produit \"$produitInsuffisant\". Veuillez corriger votre panier.');</script>";
        } else {
            $user_id = $_SESSION['user_id'];

            // Re-check user existence and balance from DB for security and freshness
            $sql_check_user = "SELECT solde FROM client WHERE id = ?";
            $stmt_check_user = $conn->prepare($sql_check_user);
            $stmt_check_user->bind_param("i", $user_id);
            $stmt_check_user->execute();
            $result_check_user = $stmt_check_user->get_result();

            if ($row_solde = $result_check_user->fetch_assoc()) {
                $solde_client = floatval($row_solde['solde']);
            } else {
                echo "<script>alert('Erreur : Solde du client introuvable.');</script>";
                $stmt_check_user->close();
                exit();
            }
            $stmt_check_user->close();

            $total_amount_for_web_payment = 0; 
            foreach ($_SESSION['cart'] as $item) {
                $total_amount_for_web_payment += $item['prix'] * $item['quantity'];
            }

            $nouveau_solde_web = $solde_client - $total_amount_for_web_payment;

            if ($nouveau_solde_web < 0) {
                echo "<script>alert('Solde insuffisant. Veuillez recharger votre compte.');</script>";
                exit();
            }

            // --- Perform Web Payment Transaction ---
            $conn->begin_transaction();
            try {
                // Update product quantities
                foreach ($_SESSION['cart'] as $product_id => $item) {
                    $id = intval($product_id);
                    $qty = intval($item['quantity']);

                    $sql = "SELECT quantite FROM produits WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($row = $result->fetch_assoc()) {
                        $current_stock = intval($row['quantite']);
                        $new_stock = max(0, $current_stock - $qty);

                        $update_sql = "UPDATE produits SET quantite = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("ii", $new_stock, $id);
                        $update_stmt->execute();
                        $update_stmt->close();

                        if ($new_stock == 0) {
                            $disable_sql = "UPDATE produits SET disponible = 0 WHERE id = ?";
                            $disable_stmt = $conn->prepare($disable_sql);
                            $disable_stmt->bind_param("i", $id);
                            $disable_stmt->execute();
                            $disable_stmt->close();
                        }
                    }
                    $stmt->close();
                }

                // Insert into achats table
                $insert_achat_sql = "INSERT INTO achats (id_utilisateur, montant_total) VALUES (?, ?)";
                $insert_achat_stmt = $conn->prepare($insert_achat_sql);
                $insert_achat_stmt->bind_param("id", $user_id, $total_amount_for_web_payment);
                $insert_achat_stmt->execute();
                $achat_id = $conn->insert_id;
                $insert_achat_stmt->close();

                // Insert into achat_produits table
                foreach ($_SESSION['cart'] as $product_id => $item) {
                    $product_id = intval($product_id);
                    $quantity = intval($item['quantity']);
                    $prix = floatval($item['prix']);

                    $insert_achat_produit_sql = "INSERT INTO achat_produits (id_achat, id_produit, quantite, prix_unitaire) VALUES (?, ?, ?, ?)";
                    $insert_achat_produit_stmt = $conn->prepare($insert_achat_produit_sql);
                    $insert_achat_produit_stmt->bind_param("iiid", $achat_id, $product_id, $quantity, $prix);
                    $insert_achat_produit_stmt->execute();
                    $insert_achat_produit_stmt->close();
                }

                // Add a new transaction record for web payment
                $transaction_title_web = 'Paiement Web';
                $transaction_subtitle_web = 'Achat de ' . count($_SESSION['cart']) . ' articles du panier via interface web.';
                $transaction_type_enum_web = 'debit'; 

                $insert_transaction_sql_web = "INSERT INTO transactions (client_id, title, subtitle, amount, type) VALUES (?, ?, ?, ?, ?)";
                $insert_transaction_stmt_web = $conn->prepare($insert_transaction_sql_web);
                $insert_transaction_stmt_web->bind_param("issds", $user_id, $transaction_title_web, $transaction_subtitle_web, $total_amount_for_web_payment, $transaction_type_enum_web);
                $insert_transaction_stmt_web->execute();
                $insert_transaction_stmt_web->close();


                $update_solde_sql = "UPDATE client SET solde = ? WHERE id = ?";
                $update_solde_stmt = $conn->prepare($update_solde_sql);
                $update_solde_stmt->bind_param("di", $nouveau_solde_web, $user_id);
                $update_solde_stmt->execute();
                $update_solde_stmt->close();

                $_SESSION['user_solde'] = $nouveau_solde_web; // Update session balance

                $conn->commit(); // Commit all changes

                unset($_SESSION['cart']); // Clear cart after successful payment
                $_SESSION['notification'] = 'Paiement effectué avec succès via le site web, stock mis à jour, historique enregistré et solde débité.';
                $_SESSION['notification_type'] = 'success';
                echo "<script>alert('Paiement effectué, stock mis à jour, historique enregistré et solde débité.');</script>";
                echo "<script>window.location.href='index.php';</script>"; // Redirect after alert
                exit();

            } catch (Exception $e) {
                $conn->rollback(); // Rollback on error
                error_log("Web Payment Transaction Error: " . $e->getMessage());
                echo "<script>alert('Erreur lors de l\\'enregistrement de l\\'achat : " . addslashes($e->getMessage()) . "');</script>";
            }
        }
    }
}

// Initial total amount calculation for the first page render
$total_amount_initial_display = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) { // Ensure cart is array before iterating
    foreach ($_SESSION['cart'] as $item) {
        $total_amount_initial_display += $item['prix'] * $item['quantity'];
    }
}

// Initial QR code generation for the web page on first load
// This ensures the QR code is present even before the first AJAX update
$qr_code_file_path_initial = '';
if ($isLoggedIn && !empty($_SESSION['cart']) && $total_amount_initial_display > 0) {
    reset($_SESSION['cart']);
    $first_product_in_cart_id_initial = key($_SESSION['cart']); 
    
    // --- IMPORTANT: Ensure this QR data format matches what Android app expects (JSON) ---
    // The Android app is now expecting JSON, so the QR content should be JSON.
    $qr_data_array_initial = [
        'transaction_id' => uniqid('qr_pay_'), // Generate a unique ID for this specific QR
        'amount' => $total_amount_initial_display,
        'user_id' => $_SESSION['user_id'] // Send the user's ID from session
    ];
    $qr_data_for_payment_initial = json_encode($qr_data_array_initial);
    // --- END IMPORTANT ---


    $qr_code_file_name_initial = 'qr_payment_' . md5($qr_data_for_payment_initial) . '.png';
    $qr_code_file_path_full_initial = 'qrcodes/' . $qr_code_file_name_initial;
    $qr_code_save_path_initial = __DIR__ . '/' . $qr_code_file_path_full_initial;

    if (!file_exists(dirname($qr_code_save_path_initial))) {
        mkdir(dirname($qr_code_save_path_initial), 0777, true);
    }
    if (!file_exists($qr_code_save_path_initial)) {
        QRcode::png($qr_data_for_payment_initial, $qr_code_save_path_initial, QR_ECLEVEL_L, 4);
    }
    $qr_code_file_path_initial = $qr_code_file_path_full_initial;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Grocery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <style>
        /* Basic styling from previous versions, ensure this aligns with your style.css */
        <?php if (!$isLoggedIn): ?>
/* Hide product list when not logged in */
.product-list {
    display: none !important;
}
<?php endif; ?>

/* Additional styles for login options */
@keyframes pulse-animation {
    0% { transform:scale(0.8); opacity:0.8; }
    50% { transform:scale(1.2); opacity:0.2; }
    100% { transform:scale(0.8); opacity:0.8; }
}
/* Minimalist User Info Card Styles */
.user-info-card {
  max-width: 500px;
  margin: 30px auto;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.user-info-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  background: #f9f9f9;
  border-bottom: 1px solid #f0f0f0;
}

.user-info-title {
  font-size: 18px;
  font-weight: 500;
  color: #333;
}

.user-badge {
  width: 36px;
  height: 36px;
  background: #6200EE;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 500;
  font-size: 16px;
  text-transform: uppercase;
}

.user-info-content {
  padding: 16px 24px;
}

.info-row {
  display: flex;
  padding: 12px 0;
  border-bottom: 1px solid #f5f5f5;
}

.info-row:last-child {
  border-bottom: none;
}

.info-label {
  flex: 0 0 100px;
  color: #666;
  font-size: 14px;
}

.info-value {
  flex: 1;
  color: #333;
  font-size: 15px;
}

.balance-row {
  margin-top: 4px;
}

.balance-value {
  font-weight: 600;
  color: #4CAF50;
}

.user-info-footer {
  padding: 16px 24px;
  background: #fafafa;
  border-top: 1px solid #f0f0f0;
  text-align: right;
}

.rfid-button {
  display: inline-flex;
  align-items: center;
  background: #6200EE;
  color: white;
  border: none;
  border-radius: 6px;
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.2s;
}

.rfid-button:hover {
  background: #5000c5;
}

.rfid-button i {
  margin-right: 8px;
}

@media (max-width: 600px) {
  .user-info-card {
    margin: 20px 16px;
  }
  
  .info-row {
    flex-direction: column;
  }
  
  .info-label {
    margin-bottom: 4px;
  }
  
  .info-value {
    padding-left: 0;
  }
}
.login-container {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    margin: 0 auto 40px auto;
    max-width: 900px;
}

.login-option {
    text-align: center;
    flex: 1;
    min-width: 300px;
    max-width: 400px;
    margin: 0 15px 20px 15px;
    padding: 25px;
    background: #f5f5f5;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.login-divider {
    display: flex;
    align-items: center;
    margin: 10px 15px;
    font-weight: bold;
    color: #666;
}

.login-divider-circle {
    text-align: center;
    width: 40px;
    height: 40px;
    line-height: 40px;
    border-radius: 50%;
    background: #eee;
}
        
        .product-card {
            border: 1px solid #ccc;
            padding: 10px;
            margin: 10px;
            width: 200px;
            text-align: center;
        }
        .btn {
            padding: 5px 10px;
            border: none;
            color: white;
            cursor: pointer;
        }
        .btn-success {
            background-color: #4CAF50;
        }
        .btn-secondary {
            background-color: #aaa;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(78, 78, 78, 0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            border-radius: 8px; /* Added for modern look */
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .cart-item {
            display: flex; /* Use flexbox for better layout */
            align-items: center; /* Vertically align items */
            margin-bottom: 10px;
            padding: 10px;
            border-bottom: 1px solid #eee;
            gap: 10px; /* Space between items */
        }
        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px; /* Slightly rounded corners for images */
        }
        .cart-item-details {
            flex-grow: 1; /* Allow details to take available space */
        }
        .cart-item-quantity input[type="number"] {
            width: 60px; /* Adjust width as needed */
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .cart-total {
            font-weight: bold;
            margin-top: 10px;
            font-size: 1.2em;
            text-align: right; /* Align total to the right */
        }
        .qr-code-section {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-align: center;
            background-color: #f9f9f9;
        }
        .qr-code-section img {
            max-width: 200px; /* Adjust size as needed */
            height: auto;
            margin: 10px auto;
            border: 1px solid #ccc;
            padding: 5px;
        }
        .user-balance-display {
            font-size: 1.1em;
            font-weight: bold;
            color: #28a745; /* Green color for balance */
            margin-top: 5px;
            margin-bottom: 15px;
        }
        .rfid-login-section {
            margin-top: 40px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .rfid-login-card {
            max-width: 400px;
            margin: 0 auto;
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="images/logo.png" alt="Logo de l'entreprise" class="logo">
        <div class="navbar">
            <a href="#">Accueil</a>
            <a href="carte.php">Carte</a>
            <a href="guide.php">Guide</a>
            <?php if ($isLoggedIn): ?>
                <span id="loginLink" style="cursor: pointer;"><i class="fas fa-user"></i><a href="index.php?logout=true">Se déconnecter</a></span>
            <?php else: ?>
                <span id="loginLink" style="cursor: pointer;"><i class="fas fa-user"></i> Se connecter</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="content">
        <h1>Bienvenue sur notre site</h1>
        
        <?php if ($isLoggedIn): ?>
        <div class="user-info-card">
  <div class="user-info-header">
    <span class="user-info-title">Mes informations</span>
    <span class="user-badge"><?= substr(htmlspecialchars($_SESSION['user_name']), 0, 1) ?></span>
  </div>
  
  <div class="user-info-content">
    <div class="info-row">
      <div class="info-label">Nom</div>
      <div class="info-value"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
    </div>
    
    <div class="info-row">
      <div class="info-label">Email</div>
      <div class="info-value"><?= htmlspecialchars($_SESSION['user_email']) ?></div>
    </div>
    
    <div class="info-row">
      <div class="info-label">Adresse</div>
      <div class="info-value"><?= htmlspecialchars($_SESSION['user_address']) ?></div>
    </div>
    
    <div class="info-row balance-row">
      <div class="info-label">Solde</div>
      <div class="info-value balance-value"><?= number_format($_SESSION['user_solde'], 2) ?> DH</div>
    </div>
  </div>
  
  <div class="user-info-footer">
    <a href="rfid_registration.php" class="rfid-button">
      <i class="fas fa-id-card"></i>
      Gérer ma carte RFID
    </a>
  </div>
</div>
        <?php endif; ?>
        
        <?php if (!$isLoggedIn): ?>
<!-- Main container with text explaining the login options -->
<div style="text-align:center; margin:30px auto 15px auto; max-width:900px;">
    <h2>Connexion à votre compte</h2>
    <p style="font-size:16px; color:#555; margin-bottom:25px; padding:0 15px;">
        <strong>Choisissez l'une des méthodes suivantes pour vous connecter :</strong>
    </p>
</div>

<!-- Centered flex container for both methods -->
<div class="login-container" style="display:flex; justify-content:center; flex-wrap:wrap; margin:0 auto 40px auto; max-width:900px;">
    <!-- QR Login Section -->
    <div id="qrLoginSection" style="text-align:center; flex:1; min-width:300px; max-width:400px; margin:0 15px 20px 15px; padding:25px; background:#f5f5f5; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0;">Option 1: QR Code</h3>
        <img src="<?= htmlspecialchars($qr_code_file) ?>?t=<?= time() ?>" alt="QR Login" style="max-width:200px; margin:15px 0;">
        <p>Scannez ce code avec l'application Android pour vous connecter.</p>
        <div id="qrLoginStatus" style="color:green; font-weight:bold; margin:15px; min-height:24px;"></div>
    </div>
    
    <div style="display:flex; align-items:center; margin:10px 15px; font-weight:bold; color:#666;">
        <div style="text-align:center; width:40px; height:40px; line-height:40px; border-radius:50%; background:#eee;">
            OU
        </div>
    </div>
    
    <!-- RFID Login Section -->
    
<div id="rfidLoginSection" class="login-option">
    <h3 style="margin-top:0;">Option 2: Carte RFID</h3>
    
    <!-- Improved RFID card icon -->
    <div style="margin:15px 0;">
        <i class="fas fa-id-card" style="font-size:48px; color:#6200EE; margin-bottom:15px;"></i>
    </div>
    
    <p>Présentez votre carte RFID au lecteur pour vous connecter.</p>
    
    <!-- Improved RFID scanning animation -->
    <div class="rfid-animation" style="margin:20px auto; width:60px; height:60px; position:relative;">
        <!-- Pulsing circle -->
        <div class="rfid-pulse" style="
            width:60px;
            height:60px;
            border-radius:50%;
            background:rgba(98,0,238,0.3);
            position:absolute;
            top:0;
            left:0;
            animation:pulse-animation 2s infinite;">
        </div>
        
        <!-- RFID icon instead of WiFi -->
        <i class="fas fa-rss" style="
            position:absolute;
            top:50%;
            left:50%;
            transform:translate(-50%,-50%);
            font-size:28px;
            color:#6200EE;">
        </i>
    </div>
    
    <!-- Status text -->
    <div id="rfidLoginStatus" style="font-weight:bold; margin-top:15px; min-height:24px;">
        En attente d'une carte RFID...
    </div>
</div>
</div>

<!-- Traditional login link -->
<div style="text-align:center; margin:0 auto 30px auto; max-width:900px;">
    <p>Vous préférez une méthode traditionnelle? 
        <a href="#" id="traditionalLoginLink" style="color:#6200EE; text-decoration:underline; font-weight:bold;">
            Se connecter avec identifiant et mot de passe
        </a>
    </p>
</div>

<style>
@keyframes pulse-animation {
    0% { transform:scale(0.8); opacity:0.8; }
    50% { transform:scale(1.2); opacity:0.2; }
    100% { transform:scale(0.8); opacity:0.8; }
}
</style>

<script>
// Add this to your existing JavaScript
document.addEventListener('DOMContentLoaded', function() {
    var traditionalLoginLink = document.getElementById('traditionalLoginLink');
    if (traditionalLoginLink) {
        traditionalLoginLink.addEventListener('click', function(e) {
            e.preventDefault();
            var loginModal = document.getElementById("loginModal");
            if (loginModal) loginModal.style.display = "block";
        });
    }
});
</script>

<style>
@keyframes pulse-animation {
    0% { transform: scale(0.8); opacity: 0.8; }
    50% { transform: scale(1.2); opacity: 0.2; }
    100% { transform: scale(0.8); opacity: 0.8; }
}
</style>
        
        <script>
        // Check for QR login status every 2 seconds
        setInterval(function() {
            fetch('index.php?check_qr_login=1')
                .then(resp => resp.json())
                .then(data => {
                    if (data.authenticated) {
                        document.getElementById('qrLoginStatus').textContent = 'Connexion réussie, rechargement...';
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    }
                });
        }, 2000);
        
        // Check for RFID authentication every 2 seconds
        
        </script>
        <?php endif; ?>
        
        <div class="product-list">
            <?php foreach ($products as $product): ?>
                <div class="product-item">
                    <div class="product-card">
                        <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['nom']) ?>" width="80">
                        <h4><?= htmlspecialchars($product['nom']) ?></h4>
                        <p>Prix : <?= htmlspecialchars(number_format($product['prix'], 2)) ?> dh</p>

                        <?php if ($product['quantite'] > 0 && $product['disponible'] == 1): ?>
                            <button type="button" class="btn btn-success add-to-cart-btn" data-product-id="<?= htmlspecialchars($product['id']) ?>">Disponible</button>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>Indisponible</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-container">
            <h2>Panier</h2>
            <div id="cart-content">
            <?php // Initial cart content will be rendered here. This will be updated by AJAX polling. ?>
            </div>

            <form action="" method="post">
                <button type="submit" name="payer">Payer</button>
            </form>
        </div>

        <?php // Only show the QR section if the user is logged in AND there's a cart total > 0 ?>
        <div class="qr-code-section" id="qrCodeSection" 
            style="display: <?= ($isLoggedIn && !empty($_SESSION['cart']) && $total_amount_initial_display > 0) ? 'block' : 'none'; ?>;">
            <h3>Scannez ce code pour payer</h3>
            <img id="qrCodeImage" src="<?= htmlspecialchars($qr_code_file_path_initial) ?>" alt="QR Code de paiement">
            <p>Montant total : <span id="qrTotalAmount"><?= htmlspecialchars(number_format($total_amount_initial_display, 2)); ?></span> dh</p>
            <small>Ce QR code contient les informations de paiement pour votre panier.</small>
        </div>

        <div id="loginModal" class="modal">
            <div class="modal-content">
                <span class="close" id="closeLoginModal">&times;</span>
                <h2>Connexion</h2>
                <form method="post">
                    <input type="text" name="name" placeholder="Nom" required>
                    <input type="password" name="password" placeholder="Mot de passe" required>
                    <button type="submit" name="login">Connexion</button>
                </form>
                <?php if (!empty($login_error)): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($login_error); ?></p>
                <?php endif; ?>
                <p style="margin-top:15px">Pas encore de compte ? <a href="#" onclick="openRegisterModal(); return false;">S'inscrire</a></p>
            </div>
        </div>

        <div id="registerModal" class="modal">
            <div class="modal-content">
                <span class="close" id="closeRegisterModal">&times;</span>
                <h2>Inscription</h2>
                <form method="post">
                    <input type="text" name="nom" placeholder="Nom" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Mot de passe" required>
                    <input type="text" name="adresse" placeholder="Adresse" required>
                    <button type="submit" name="register">Inscription</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    var loginModal = document.getElementById("loginModal");
    var registerModal = document.getElementById("registerModal");
    var loginLink = document.getElementById("loginLink");
    var closeLoginModal = document.getElementById("closeLoginModal");
    var closeRegisterModal = document.getElementById("closeRegisterModal");

    loginLink.onclick = function() {
        <?php if ($isLoggedIn): ?>
        window.location.href = "index.php?logout=true";
        <?php else: ?>
        loginModal.style.display = "block";
        <?php endif; ?>
    }

    closeLoginModal.onclick = function() {
        loginModal.style.display = "none";
    }

    closeRegisterModal.onclick = function() {
        registerModal.style.display = "none";
    }

    function openRegisterModal() {
        loginModal.style.display = "none";
        registerModal.style.display = "block";
    }

    window.onclick = function(event) {
        if (event.target == loginModal) {
            loginModal.style.display = "none";
        }
        if (event.target == registerModal) {
            registerModal.style.display = "none";
        }
    }

    $(document).ready(function() {
        // Check for login notification parameter
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('rfid_success')) {
    // Create and show login notification
    const notification = document.createElement('div');
    notification.className = 'login-notification';
    notification.innerHTML = '<i class="fas fa-check-circle"></i> Connexion RFID réussie!';
    document.body.appendChild(notification);
    
    // Show the notification
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Hide and remove after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 500);
    }, 5000);
    
    // Clean URL parameters
    const cleanUrl = window.location.pathname;
    history.replaceState({}, document.title, cleanUrl);
}
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

                    // Show QR section only if there's a QR code path and it's not empty (i.e., cart is not empty and user logged in)
                    // The PHP now directly controls qr_code_path in the response based on isLoggedIn and !empty($_SESSION['cart'])
                    if (response.qr_code_path) { 
                        qrCodeImage.attr('src', response.qr_code_path + '?_=' + new Date().getTime()); // Add cache-buster
                        qrTotalAmount.text(response.qr_total_amount);
                        qrCodeSection.show(); 
                    } else {
                        qrCodeSection.hide(); // Hide QR section if cart is empty or not logged in
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
            $.post('index.php', {
                product_id: productId,
                action: 'add',
                quantity: 1
            }, function(response) {
                // The PHP now directly updates $_SESSION['notification'] which is picked up by updateCartDisplay
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
        var pollingInterval = 3000; // Poll every 3 seconds (adjust as needed)
       
        function pollForUpdates() {
            updateCartDisplay(); 
        }

        // Start polling when the document is ready
        setInterval(pollForUpdates, pollingInterval);

        // --- End Polling Mechanism ---
    });
    
    function checkPaymentCompletion() {
        $.ajax({
            url: 'check_payment_flag.php',
            type: 'GET',
            dataType: 'json',
            cache: false,
            success: function(response) {
                console.log("Payment check response:", response);
                if (response.payment_completed) {
                    // Payment was completed, show message and reload
                    toastr.success('Paiement effectué avec succès via l\'application mobile!');
                    
                    // Update the displayed balance if present
                    if (response.payment_data && response.payment_data.new_balance) {
                        // Update the balance display in the user info section
                        $('li:contains("Solde:")').html('<strong>Solde:</strong> ' + response.payment_data.new_balance + ' DH');
                    }
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error checking payment:", error);
            }
        });
    }

    // Check for payment flags every 5 seconds
    setInterval(checkPaymentCompletion, 5000);

    function checkScanRequests() {
        $.ajax({
            url: 'check_scan_requests.php',
            type: 'GET',
            dataType: 'json',
            cache: false,
            success: function(response) {
                console.log("Scan check response:", response);
                // If any requests were processed, update the cart display
                if (response.processed > 0) {
                    // Show notifications if any
                    if (response.notifications && response.notifications.length > 0) {
                        response.notifications.forEach(function(notification) {
                            if (notification.type === 'success') {
                                toastr.success(notification.message);
                            } else if (notification.type === 'warning') {
                                toastr.warning(notification.message);
                            } else if (notification.type === 'info') {
                                toastr.info(notification.message);
                            } else if (notification.type === 'error') {
                                toastr.error(notification.message);
                            }
                        });
                    }
                    // Update the cart display
                    updateCartDisplay();
                }
            },
            error: function(xhr, status, error) {
                console.error("Error checking scan requests:", error);
            }
        });
    }

    // Check for scan requests every 2 seconds
    setInterval(checkScanRequests, 2000);
    <?php if (!$isLoggedIn): ?>
// RFID Authorization Status Display Element
let rfidStatusElement = document.getElementById('rfidLoginStatus');
if (!rfidStatusElement) {
    // Create status element if it doesn't exist
    rfidStatusElement = document.createElement('div');
    rfidStatusElement.id = 'rfidLoginStatus';
    rfidStatusElement.style.fontWeight = 'bold';
    rfidStatusElement.style.margin = '15px 0';
    
    // Find rfid login section or create one
    let rfidSection = document.getElementById('rfidLoginSection');
    if (!rfidSection) {
        rfidSection = document.createElement('div');
        rfidSection.id = 'rfidLoginSection';
        rfidSection.className = 'rfid-login-section';
        rfidSection.innerHTML = '<h2>Connexion via Carte RFID</h2>';
        document.querySelector('.content').appendChild(rfidSection);
    }
    
    // Add the status element to the section
    rfidSection.appendChild(rfidStatusElement);
}

// Start RFID listening process
let rfidListenerActive = true;
let rfidErrorCount = 0;
const maxRfidErrors = 5;
const rfidPollInterval = 2000; // Poll every 2 seconds

function pollRfidScans() {
    if (!rfidListenerActive) return;
    
    fetch('rfid_ajax_listener.php?t=' + new Date().getTime())
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response error');
            }
            return response.json();
        })
        .then(data => {
            console.log('RFID Poll:', data.status);
            
            // Reset error counter on successful request
            rfidErrorCount = 0;
            
            // Handle different response statuses
            if (data.status === 'success') {
                rfidStatusElement.textContent = 'Carte RFID reconnue. Connexion...';
                rfidStatusElement.style.color = 'green';
                
                // Show success message using toastr if available
                if (typeof toastr !== 'undefined') {
                    toastr.success('Connexion RFID réussie. Bienvenue ' + data.user.name + '!');
                }
                
                // Stop polling and reload page
                rfidListenerActive = false;
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
            else if (data.status === 'error') {
                rfidStatusElement.textContent = data.message;
                rfidStatusElement.style.color = 'red';
                
                // Show error message using toastr if available
                if (typeof toastr !== 'undefined') {
                    toastr.error(data.message);
                }
                
                // Clear status after 3 seconds
                setTimeout(() => {
                    rfidStatusElement.textContent = 'En attente d\'une carte RFID...';
                    rfidStatusElement.style.color = 'inherit';
                }, 3000);
            }
            else {
                // No scan or already logged in - just keep polling
                if (rfidStatusElement.textContent === '') {
                    rfidStatusElement.textContent = 'En attente d\'une carte RFID...';
                    rfidStatusElement.style.color = 'inherit';
                }
            }
        })
        .catch(error => {
            console.error('RFID Polling Error:', error);
            rfidErrorCount++;
            
            if (rfidErrorCount >= maxRfidErrors) {
                console.error('Maximum RFID polling errors reached, stopping polling');
                rfidListenerActive = false;
                rfidStatusElement.textContent = 'Service de lecture RFID indisponible';
                rfidStatusElement.style.color = 'red';
            }
        })
        .finally(() => {
            // Continue polling if still active
            if (rfidListenerActive) {
                setTimeout(pollRfidScans, rfidPollInterval);
            }
        });
}

// Start the polling process
pollRfidScans();

<?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>