<?php
session_start();

// Configuration de la base de données (déplacer vers config.php)
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

// Connexion à la base de données
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Erreur de connexion à la base de données : " . $conn->connect_error);
}

// Initialiser les variables
$login_error = "";
$showLoginForm = false;
$isLoggedIn = isset($_SESSION['user_id']);

// Fonction pour désinfecter les données d’entrée
function sanitizeInput($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// **Gestion de l’inscription**
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $nom = sanitizeInput($conn, $_POST['nom']);
    $email = sanitizeInput($conn, $_POST['email']);
    $password = $_POST['password'];
    $adresse = sanitizeInput($conn, $_POST['adresse']);

    // Hacher le mot de passe
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Préparer et exécuter la requête SQL pour insérer les données dans la table client
    $sql = "INSERT INTO client (name, address, solde, num_commande, email, password) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // Définir les valeurs par défaut pour solde et num_commande
    $default_solde = 0.00;
    $default_num_commande = '';

    $stmt->bind_param("sssdss", $nom, $adresse, $default_solde, $default_num_commande, $email, $hashed_password);

    if ($stmt->execute()) {
        echo "<script>alert('Inscription réussie ! Veuillez vous connecter.'); window.location.href = 'index.php';</script>";
        exit();
    } else {
        echo "Échec de l’inscription : " . $stmt->error;
    }

    $stmt->close();
}

// **Gestion de la connexion**
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $name = sanitizeInput($conn, $_POST['name']);
    $password = $_POST['password'];

    // Préparer la requête SQL pour sélectionner dans la table client
    $sql = "SELECT id, name, address, solde, num_commande, password FROM client WHERE name = ?";
    $stmt = $conn->prepare($sql);

    // Lier le paramètre (nom)
    $stmt->bind_param("s", $name);

    // Exécuter la requête
    $stmt->execute();

    // Obtenir le résultat
    $result = $stmt->get_result();

    // Vérifier si un utilisateur avec le nom donné existe
    if ($result->num_rows > 0) {
        // Récupérer les données de l’utilisateur
        $user = $result->fetch_assoc();

        // Vérifier le mot de passe
        if (password_verify($password, $user['password'])) {
            // Le mot de passe est correct !

            // Régénérer l’ID de session (meilleure pratique de sécurité)
            session_regenerate_id(true);

            // Stocker les données de l’utilisateur dans les variables de session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_address'] = $user['address'];
            $_SESSION['user_solde'] = $user['solde'];
            $_SESSION['user_num_commande'] = $user['num_commande'];

            $isLoggedIn = true;
            echo "<script>alert('Connexion réussie !'); window.location.href = 'index.php';</script>";
            exit();
        } else {
            // Mot de passe incorrect
            $login_error = "Nom ou mot de passe incorrect.";
        }
    } else {
        // Utilisateur introuvable
        $login_error = "Nom ou mot de passe incorrect.";
    }

    $stmt->close();
}

// **Gestion de la déconnexion**
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// **Récupération des produits**
$sql_products = "SELECT id, nom, prix, image, quantite, disponible FROM produits";
$result_products = $conn->query($sql_products);

$products = [];
if ($result_products->num_rows > 0) {
    while ($row = $result_products->fetch_assoc()) {
        $products[] = $row;
    }
}

// **Logique du panier d’achat**
// Initialiser le panier dans la session s’il n’existe pas
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// JSON file paths
$json_file = '/GHMARIIII/tablet/cart_request.json';
$json_file_processed = '/GHMARIIII/tablet/cart_request_processed.json';

// Function to move file
function moveFile($source, $destination) {
    if (rename($source, $destination)) {
        return true;
    } else {
        return false;
    }
}

// Try reading from the JSON file
$json_data = @file_get_contents($json_file); // Use @ to suppress warnings
$notification = null; // Initialize notification variable
$added_product = false; // Flag to indicate if a product was added

if ($json_data !== false) {
    $request_data = json_decode($json_data, true);

    if ($request_data !== null && json_last_error() === JSON_ERROR_NONE) {
        // Get product ID and action from JSON
        $product_id = isset($request_data['product_id']) ? sanitizeInput($conn, $request_data['product_id']) : null;
        $action = isset($request_data['action']) ? strtolower($request_data['action']) : 'add'; // Default action is 'add'
        $quantity = isset($request_data['quantity']) ? intval($request_data['quantity']) : 1; // Default quantity is 1

        if ($product_id) {
            $sql_product = "SELECT id, nom, prix, image, quantite FROM produits WHERE id = ?";
            $stmt = $conn->prepare($sql_product);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
                $available_quantity = intval($product['quantite']);
                $product_name = htmlspecialchars($product['nom']); // Sanitize product name

                if ($action == 'add') {
                    if ($quantity > 0) {
                        // Check if product is already in the cart
                        if (isset($_SESSION['cart'][$product_id])) {
                            // Add the requested quantity to the existing quantity
                            $current_quantity = intval($_SESSION['cart'][$product_id]['quantity']);
                            $new_quantity = $current_quantity + $quantity;

                            // Ensure the new quantity does not exceed available stock
                            if ($new_quantity > $available_quantity) {
                                $new_quantity = $available_quantity;
                                $notification = "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.";
                            }

                            $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                            $notification = "Quantité de \"$product_name\" mise à jour.";
                        } else {
                            // Product not in cart, add it (but still respect available quantity)
                            if ($quantity > $available_quantity) {
                                $quantity = $available_quantity;
                                $notification = "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.";
                            }

                            $_SESSION['cart'][$product_id] = [
                                'id' => $product['id'],
                                'nom' => $product['nom'],
                                'prix' => $product['prix'],
                                'image' => $product['image'],
                                'quantity' => $quantity,
                            ];
                            $notification = "\"$product_name\" ajouté au panier.";
                            $added_product = true; // Set the flag when a product is truly added
                        }


                    }
                } elseif ($action == 'remove') {
                    // Remove quantity
                    if (isset($_SESSION['cart'][$product_id])) {
                        $current_quantity = intval($_SESSION['cart'][$product_id]['quantity']);
                        $new_quantity = $current_quantity - $quantity;

                        if ($new_quantity <= 0) {
                            unset($_SESSION['cart'][$product_id]);
                            $notification = "\"$product_name\" supprimé du panier.";
                        } else {
                            $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                            $notification = "Quantité de \"$product_name\" réduite.";
                        }
                    } else {
                        $notification = "\"$product_name\" n'est pas dans le panier.";
                    }
                } elseif ($action == 'set') {
                    // Set quantity
                    if ($quantity > 0 && $quantity <= $available_quantity) {
                        $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                        $notification = "Quantité de \"$product_name\" définie sur $quantity.";
                    } elseif ($quantity <= 0) {
                        unset($_SESSION['cart'][$product_id]);
                        $notification = "\"$product_name\" supprimé du panier.";
                    } else {
                        $_SESSION['cart'][$product_id]['quantity'] = $available_quantity;
                        $notification = "La quantité demandée pour \"$product_name\" dépasse le stock disponible ($available_quantity). La quantité a été ajustée.";
                    }
                }
            }
            $stmt->close();
        }
    }

    // Move the file after processing to prevent re-adding the same product
    if (!moveFile($json_file, $json_file_processed)) {
        // Handle error. Perhaps log it.
        error_log("Failed to move JSON file from $json_file to $json_file_processed");
    }
}

// **Handle the `update_cart_display` request**
if (isset($_GET['update_cart_display'])) {
    ob_start(); // Start output buffering

    // Calculate the total amount here!
    $total_amount = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total_amount += $item['prix'] * $item['quantity'];
    }

    // Include the cart display logic (the same code you use to display the cart initially)
    if (empty($_SESSION['cart'])): ?>
        <p>Votre panier est vide.</p>
    <?php else: ?>
        <form method="post" action="">
            <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                <div class="cart-item">
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nom']); ?>">
                    <div class="cart-item-details">
                        <h4><?php echo htmlspecialchars($item['nom']); ?></h4>
                        <p>Prix : <?php echo htmlspecialchars($item['prix']); ?> dh</p>
                    </div>
                    <div class="cart-item-quantity">
                        <label for="quantity_<?php echo $product_id; ?>">Quantité :</label>
                        <input type="number" id="quantity_<?php echo $product_id; ?>" name="quantity[<?php echo $product_id; ?>]" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0">
                    </div>
                </div>
            <?php endforeach; ?>
            <button type="submit" name="update_cart">Mettre à jour le panier</button>
        </form>
        <div class="cart-total">
            Total : <?php echo htmlspecialchars(number_format($total_amount, 2)); ?> dh
        </div>
    <?php endif;

    $cart_html = ob_get_clean(); // Get the buffered output

    $response = ['cart_html' => $cart_html, 'added_product' => $added_product, 'notification' => $notification];

    echo json_encode($response); // Send the HTML back to the browser
    exit; // Very important!
}

// Clear the added_products session after processing (outside update_cart_display)
if (isset($_SESSION['added_products'])) {
    unset($_SESSION['added_products']);
}

// Mettre à jour la quantité du panier
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


// Calculer le montant total
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
                echo "Erreur lors de l’enregistrement de l’achat : " . $e->getMessage();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <!-- jQuery avant toastr -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- CSS toastr -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

<!-- JS toastr -->
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

        /* Styles de la fenêtre modale */
        /* Styles de la fenêtre modale */
/* Styles de la fenêtre modale */
.modal {
    display: none; /* Masquée par défaut */
    position: fixed; /* Reste en place */
    z-index: 1; /* Se trouve au-dessus des autres éléments */
    left: 0;
    top: 0;
    width: 100%; /* Largeur totale */
    height: 100%; /* Hauteur totale */
    overflow: auto; /* Permet le défilement si nécessaire */
    background-color: rgba(78, 78, 78, 0.4); /* Grise avec opacité */
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto; /* 15 % du haut et centrée */
    padding: 20px;
    border: 1px solid #888;
    width: 80%; /* Peut être plus ou moins grande, selon la taille de l’écran */
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

/* Style pour la notification */
.notification {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background-color: #28a745; /* Vert */
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    z-index: 1000;
    display: none; /* Masqué par défaut */
}
    </style>

</head>
<body>
    <!-- Notification -->
    <!-- Notification -->
<div id="notification" class="notification" style="display: none;"></div>
    <!-- Message de notification inséré ici -->
    </div>

    <div class="header">
        <img src="images/logo.png" alt="Logo de l’entreprise" class="logo">
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
            <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['user_name']); ?> !</p>
            <p>Adresse : <?php echo htmlspecialchars($_SESSION['user_address']); ?> !</p>
            <p>Solde : <?php echo htmlspecialchars(number_format($_SESSION['user_solde'], 2)); ?> dh</p>
        <?php endif; ?>

        <!-- Affichage des produits -->
        <div class="product-list">
            <?php foreach ($products as $product): ?>
                <div class="product-item">
                    <div class="product-card">
                        <img src="<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['nom']) ?>" width="80">
                        <h4><?= htmlspecialchars($product['nom']) ?></h4>
                        <p>Prix : <?= htmlspecialchars(number_format($product['prix'], 2)) ?> dh</p>

                        <?php if ($product['quantite'] > 0 && $product['disponible'] == 1): ?>
                            <button type="button" class="btn btn-success add-to-cart-btn" data-product-id="<?= htmlspecialchars($product['id']) ?>">Ajouter au panier</button>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>Indisponible</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Affichage du panier d’achat -->
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
                                <p>Prix : <?php echo htmlspecialchars($item['prix']); ?> dh</p>
                            </div>
                            <div class="cart-item-quantity">
                                <label for="quantity_<?php echo $product_id; ?>">Quantité :</label>
                                <input type="number" id="quantity_<?php echo $product_id; ?>" name="quantity[<?php echo $product_id; ?>]" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="update_cart">Mettre à jour le panier</button>
                </form>
                <div class="cart-total">
                    Total : <?php echo htmlspecialchars(number_format($total_amount, 2)); ?> dh
                </div>
            <?php endif; ?>
            </div>

            <!-- Formulaire de paiement (toujours affiché) -->
            <form action="" method="post">
                <button type="submit" name="payer">Payer</button>
            </form>
        </div>

        <!-- Fenêtre modale de connexion -->
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

        <!-- Fenêtre modale d’inscription -->
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
    // Obtenir la fenêtre modale de connexion
    var loginModal = document.getElementById("loginModal");

    // Obtenir la fenêtre modale d’inscription
    var registerModal = document.getElementById("registerModal");

    // Obtenir le bouton qui ouvre la fenêtre modale de connexion
    var loginLink = document.getElementById("loginLink");

    // Obtenir l’élément <span> qui ferme la fenêtre modale de connexion
    var closeLoginModal = document.getElementById("closeLoginModal");

    // Obtenir l’élément <span> qui ferme la fenêtre modale d’inscription
    var closeRegisterModal = document.getElementById("closeRegisterModal");

    // Lorsque l’utilisateur clique sur le bouton, ouvrir la fenêtre modale de connexion
    loginLink.onclick = function() {
        // Vérifier si l’utilisateur est connecté
        <?php if ($isLoggedIn): ?>
        // Si connecté, rediriger vers la déconnexion
        window.location.href = "index.php?logout=true";
        <?php else: ?>
        // Si non connecté, afficher la fenêtre modale de connexion
        loginModal.style.display = "block";
        <?php endif; ?>
    }

    // Lorsque l’utilisateur clique sur <span> (x), fermer la fenêtre modale de connexion
    closeLoginModal.onclick = function() {
        loginModal.style.display = "none";
    }

    // Lorsque l’utilisateur clique sur <span> (x), fermer la fenêtre modale d’inscription
    closeRegisterModal.onclick = function() {
        registerModal.style.display = "none";
    }

    // Fonction pour ouvrir la fenêtre modale d’inscription
    function openRegisterModal() {
        registerModal.style.display = "block";
    }

    // Lorsque l’utilisateur clique n’importe où en dehors de la fenêtre modale, la fermer
    window.onclick = function(event) {
        if (event.target == loginModal) {
            loginModal.style.display = "none";
        }
        if (event.target == registerModal) {
            registerModal.style.display = "none";
        }
    }
    $(document).ready(function() {
        function updateCartDisplay() {
    $.ajax({
        url: 'index.php?update_cart_display=1&_=' + new Date().getTime(),
        type: 'GET',
        dataType: 'json',  // Expect JSON response
        success: function(response) {
            console.log("AJAX Response:", response); // Add this line
            $('#cart-content').html(response.cart_html);

            // Handle "added product" notification using toastr
            if (response.added_product) {
                toastr.success('Produit ajouté au panier!', null, {
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "closeButton": true,
                    "preventDuplicates": true,
                    "timeOut": "2000",
                    "toastClass": 'toastr-custom'
                });
            }

            // Display other notifications using the notification div
            
        },
        error: function(xhr, status, error) {
            console.error("AJAX error:", error);
            toastr.error('Erreur lors de la mise à jour du panier : ' + error);
        }
        
    });
}


        function checkJsonAndAddToCart() {
            $.ajax({
                url: 'index.php', // The same PHP file
                type: 'POST', // Use POST to prevent caching
                data: {action: 'update_cart'},
                success: function(response) {
                   updateCartDisplay(); // Update the cart after each check
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                    toastr.error('Erreur : ' + error);
                }
            });
        }

        // Initial call to update the cart and add the product
        checkJsonAndAddToCart();

        // Set interval to check the JSON file periodically
        setInterval(checkJsonAndAddToCart, 2000); // Check every 2 seconds
    });
    $(document).ready(function () {
    function updateCartDisplay() {
        $.ajax({
            url: 'index.php?update_cart_display=1&_=' + new Date().getTime(),
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                $('#cart-content').html(response.cart_html);

                if (response.added_product) {
                    toastr.success('Produit ajouté au panier !', '', {
                        "progressBar": true,
                        "positionClass": "toast-top-right",
                        "closeButton": true,
                        "preventDuplicates": true,
                        "timeOut": "2500"
                    });
                }

                if (response.notification) {
                    $('#notification').text(response.notification).fadeIn();
                    setTimeout(function () {
                        $('#notification').fadeOut();
                    }, 3000);
                }
            },
            error: function (xhr, status, error) {
                toastr.error("Erreur de mise à jour : " + error);
            }
        });
    }

    function checkJsonAndAddToCart() {
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: { action: 'update_cart' },
            success: function () {
                updateCartDisplay();
            },
            error: function () {
                toastr.error("Erreur AJAX");
            }
        });
    }

    checkJsonAndAddToCart();
    setInterval(checkJsonAndAddToCart, 2000);
});

</script>
</body>
</html>
<?php


$conn->close();
?>