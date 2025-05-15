<?php
session_start();

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Erreur de connexion à la base de données : " . $conn->connect_error);
}

$login_error = "";
$showLoginForm = false;
$isLoggedIn = isset($_SESSION['user_id']);

function sanitizeInput($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $nom = sanitizeInput($conn, $_POST['nom']);
    $email = sanitizeInput($conn, $_POST['email']);
    $password = $_POST['password'];
    $adresse = sanitizeInput($conn, $_POST['adresse']);

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO client (name, address, solde, num_commande, email, password) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    $default_solde = 0.00;
    $default_num_commande = '';

    $stmt->bind_param("sssdss", $nom, $adresse, $default_solde, $default_num_commande, $email, $hashed_password);

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

    $sql = "SELECT id, name, address, solde, num_commande, password FROM client WHERE name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_address'] = $user['address'];
            $_SESSION['user_solde'] = $user['solde'];
            $_SESSION['user_num_commande'] = $user['num_commande'];

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
    session_destroy();
    header("Location: index.php");
    exit();
}

$sql_products = "SELECT id, nom, prix, image, quantite, disponible FROM produits";
$result_products = $conn->query($sql_products);

$products = [];
if ($result_products->num_rows > 0) {
    while ($row = $result_products->fetch_assoc()) {
        $products[] = $row;
    }
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$json_file = '/GHMARIIII/tablet/cart_request.json';
$json_file_processed = '/GHMARIIII/tablet/cart_request_processed.json';

function moveFile($source, $destination) {
    return rename($source, $destination);
}

$json_data = @file_get_contents($json_file);
$notification = null;
$notification_type = null;

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
                                $notification = "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.";
                                $notification_type = 'warning';
                            } else {
                                $notification = "Quantité de \"$product_name\" mise à jour.";
                                $notification_type = 'success';
                            }
                            $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                        } else {
                            if ($quantity > $available_quantity) {
                                $quantity = $available_quantity;
                                $notification = "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.";
                                $notification_type = 'warning';
                            }
                            $_SESSION['cart'][$product_id] = [
                                'id' => $product['id'],
                                'nom' => $product['nom'],
                                'prix' => $product['prix'],
                                'image' => $product['image'],
                                'quantity' => $quantity,
                            ];
                            $notification = "\"$product_name\" ajouté au panier.";
                            $notification_type = 'success';
                        }
                    }
                } elseif ($action == 'remove') {
                    if (isset($_SESSION['cart'][$product_id])) {
                        $current_quantity = intval($_SESSION['cart'][$product_id]['quantity']);
                        $new_quantity = $current_quantity - $quantity;

                        if ($new_quantity <= 0) {
                            unset($_SESSION['cart'][$product_id]);
                            $notification = "\"$product_name\" supprimé du panier.";
                            $notification_type = 'info';
                        } else {
                            $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                            $notification = "Quantité de \"$product_name\" réduite.";
                            $notification_type = 'info';
                        }
                    } else {
                        $notification = "\"$product_name\" n'est pas dans le panier.";
                        $notification_type = 'error';
                    }
                } elseif ($action == 'set') {
                    if ($quantity > 0 && $quantity <= $available_quantity) {
                        $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                        $notification = "Quantité de \"$product_name\" définie sur $quantity.";
                        $notification_type = 'success';
                    } elseif ($quantity <= 0) {
                        unset($_SESSION['cart'][$product_id]);
                        $notification = "\"$product_name\" supprimé du panier.";
                        $notification_type = 'info';
                    } else {
                        $_SESSION['cart'][$product_id]['quantity'] = $available_quantity;
                        $notification = "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.";
                        $notification_type = 'warning';
                    }
                }
            }
            $stmt->close();
        }
    }

    if (!moveFile($json_file, $json_file_processed)) {
        error_log("Failed to move JSON file from $json_file to $json_file_processed");
    }
}

if (isset($_GET['update_cart_display'])) {
    ob_start();
    $total_amount = 0;
    
    if (empty($_SESSION['cart'])): ?>
        <p>Votre panier est vide.</p>
    <?php else: ?>
        <form method="post" action="">
            <?php foreach ($_SESSION['cart'] as $product_id => $item): 
                $total_amount += $item['prix'] * $item['quantity']; ?>
                <div class="cart-item">
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nom']); ?>">
                    <div class="cart-item-details">
                        <h4><?php echo htmlspecialchars($item['nom']); ?></h4>
                        <p>Prix : <?php echo htmlspecialchars($item['prix']); ?> dh</p>
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
            Total : <?php echo htmlspecialchars(number_format($total_amount, 2)); ?> dh
        </div>
    <?php endif;

    $cart_html = ob_get_clean();
    
    $response = [
        'cart_html' => $cart_html,
        'notification' => $notification,
        'notification_type' => $notification_type
    ];
    
    echo json_encode($response);
    exit;
}

if (isset($_SESSION['added_products'])) {
    unset($_SESSION['added_products']);
}

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

            if ($quantity > 0 && $quantity <= $available_quantity) {
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            } else {
                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$product_id]);
                } else {
                    $_SESSION['cart'][$product_id]['quantity'] = $available_quantity;
                    echo "<script>alert('La quantité demandée pour \"".htmlspecialchars($product['nom'])."\" dépasse le stock disponible (".$available_quantity."). La quantité a été ajustée.');</script>";
                }
            }
        }
        $stmt->close();
    }
}

$total_amount = 0;
foreach ($_SESSION['cart'] as $item) {
    $total_amount += $item['prix'] * $item['quantity'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payer'])) {
    if (!empty($_SESSION['cart'])) {
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

            $sql_check_user = "SELECT id FROM client WHERE id = ?";
            $stmt_check_user = $conn->prepare($sql_check_user);
            $stmt_check_user->bind_param("i", $user_id);
            $stmt_check_user->execute();
            $result_check_user = $stmt_check_user->get_result();

            if ($result_check_user->num_rows == 0) {
                echo "<script>alert('Erreur : Utilisateur introuvable dans la table client.');</script>";
                exit();
            }
            $stmt_check_user->close();

            $sql_solde = "SELECT solde FROM client WHERE id = ?";
            $stmt_solde = $conn->prepare($sql_solde);
            $stmt_solde->bind_param("i", $user_id);
            $stmt_solde->execute();
            $result_solde = $stmt_solde->get_result();

            if ($row_solde = $result_solde->fetch_assoc()) {
                $solde_client = floatval($row_solde['solde']);
            } else {
                echo "<script>alert('Erreur : Solde du client introuvable.');</script>";
                exit();
            }
            $stmt_solde->close();

            $total_amount = 0;
            foreach ($_SESSION['cart'] as $item) {
                $total_amount += $item['prix'] * $item['quantity'];
            }

            $nouveau_solde = $solde_client - $total_amount;

            if ($nouveau_solde < 0) {
                echo "<script>alert('Solde insuffisant. Veuillez recharger votre compte.');</script>";
                exit();
            }

            $conn->begin_transaction();
            try {
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

                $user_id = $_SESSION['user_id'];
                $total_amount = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $total_amount += $item['prix'] * $item['quantity'];
                }

                $insert_achat_sql = "INSERT INTO achats (id_utilisateur, montant_total) VALUES (?, ?)";
                $insert_achat_stmt = $conn->prepare($insert_achat_sql);
                $insert_achat_stmt->bind_param("id", $user_id, $total_amount);
                $insert_achat_stmt->execute();
                $achat_id = $conn->insert_id;
                $insert_achat_stmt->close();

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

                $update_solde_sql = "UPDATE client SET solde = ? WHERE id = ?";
                $update_solde_stmt = $conn->prepare($update_solde_sql);
                $update_solde_stmt->bind_param("di", $nouveau_solde, $user_id);
                $update_solde_stmt->execute();
                $update_solde_stmt->close();

                $_SESSION['user_solde'] = $nouveau_solde;

                $conn->commit();

                unset($_SESSION['cart']);
                echo "<script>alert('Paiement effectué, stock mis à jour, historique enregistré et solde débité.');</script>";
                echo "<script>window.location.href='index.php';</script>";
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                echo "Erreur lors de l'enregistrement de l'achat : " . $e->getMessage();
            }
        }
    }
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
            margin-bottom: 10px;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
        }
        .cart-total {
            font-weight: bold;
            margin-top: 10px;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="images/logo.png" alt="Logo de l'entreprise" class="logo">
        <div class="navbar">
            <a href="#">Accueil</a>
            <a href="#">Solde</a>
            <a href="#">Carte</a>
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
            <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['user_name']); ?> !</p>
            <p>Adresse : <?php echo htmlspecialchars($_SESSION['user_address']); ?> !</p>
            <p>Solde : <?php echo htmlspecialchars(number_format($_SESSION['user_solde'], 2)); ?> dh</p>
        <?php endif; ?>

        <div class="product-list">
            <?php foreach ($products as $product): ?>
                <div class="product-item">
                    <div class="product-card">
                        <img src="<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['nom']) ?>" width="80">
                        <h4><?= htmlspecialchars($product['nom']) ?></h4>
                        <p>Prix : <?= htmlspecialchars(number_format($product['prix'], 2)) ?> dh</p>

                        <?php if ($product['quantite'] > 0 && $product['disponible'] == 1): ?>
                            <button type="button" class="btn btn-success add-to-cart-btn" data-product-id="<?= htmlspecialchars($product['id']) ?>">Ajouter au panier</button>
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
            <?php if (empty($_SESSION['cart'])): ?>
                <p>Votre panier est vide.</p>
            <?php else: ?>
                <form method="post" action="">
                    <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                        <div class="cart-item">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nom']); ?>">
                            <div class="cart-item-details">
                                <h4><?php echo htmlspecialchars($item['nom']); ?></h4>
                                <p>Prix : <?php echo htmlspecialchars($item['prix']); ?> dh</p>
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
                    Total : <?php echo htmlspecialchars(number_format($total_amount, 2)); ?> dh
                </div>
            <?php endif; ?>
            </div>

            <form action="" method="post">
                <button type="submit" name="payer">Payer</button>
            </form>
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
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": true,
            "timeOut": "3000",
            "extendedTimeOut": "1000"
        };

        function updateCartDisplay() {
            $.ajax({
                url: 'index.php?update_cart_display=1&_=' + new Date().getTime(),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#cart-content').html(response.cart_html);
                    
                    if (response.notification && response.notification_type) {
                        toastr[response.notification_type](response.notification);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                    toastr.error('Erreur lors de la mise à jour du panier');
                }
            });
        }

        function checkJsonAndAddToCart() {
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: {action: 'update_cart'},
                success: function() {
                    updateCartDisplay();
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                    toastr.error('Erreur lors de la mise à jour du panier');
                }
            });
        }

        checkJsonAndAddToCart();
        setInterval(checkJsonAndAddToCart, 2000);

        $(document).on('click', '.add-to-cart-btn', function() {
            var productId = $(this).data('product-id');
            $.post('index.php', {
                product_id: productId,
                action: 'add',
                quantity: 1
            }, function() {
                updateCartDisplay();
            });
        });
    });
    </script>
</body>
</html>
<?php
$conn->close();
?>