<?php
session_start();
require 'db.php'; // Assure-toi que ce fichier contient ta connexion à la base de données

// Vérification si l'utilisateur est connecté et a le rôle de gestionnaire
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gestionaire') {
    header('Location: ../login.php');
    exit();
}

$erreurAjout = null; // Initialisation de la variable d'erreur

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $prix = $_POST['prix'] ?? '';
    $quantite = $_POST['quantite'] ?? '';
    $categorie = $_POST['categorie'] ?? '';
    $uid = $_POST['uid'] ?? '';
    $poids = $_POST['poids'] ?? '';
    $disponible = isset($_POST['disponible']) ? 1 : 0;

    if (!empty($nom) && !empty($prix) && is_numeric($prix) && is_numeric($quantite) && !empty($categorie) && !empty($uid) && is_numeric($poids)) {
        try {
            // Gestion de l'upload d'image
            $image_path = 'images/default-product.png'; // Image par défaut
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['image']['name'];
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                
                if (in_array(strtolower($ext), $allowed)) {
                    $newname = 'product_' . time() . '.' . $ext;
                    $destination = '../tablet/images/' . $newname;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                        $image_path = 'images/' . $newname;
                    } else {
                        $erreurAjout = "Impossible de déplacer le fichier uploadé.";
                    }
                } else {
                    $erreurAjout = "Format d'image non autorisé. Utilisez JPG, JPEG, PNG ou GIF.";
                }
            }
            
            if (!$erreurAjout) {
                $sql = "INSERT INTO produits (nom, prix, quantite, categorie, uid_codebar, poids, image, disponible) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom, $prix, $quantite, $categorie, $uid, $poids, $image_path, $disponible]);

                // Rediriger vers dashboard après l'ajout réussi
                header('Location: dashboard.php?success=1');
                exit();
            }
        } catch (PDOException $e) {
            $erreurAjout = 'Erreur lors de l\'ajout du produit : ' . $e->getMessage();
        }
    } else {
        $erreurAjout = 'Veuillez remplir tous les champs correctement.';
    }
}

// Récupération des catégories existantes pour les suggérer
try {
    $stmt = $pdo->query("SELECT DISTINCT categorie FROM produits ORDER BY categorie");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Produit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: rgb(200, 229, 247);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            margin-left: 280px; /* same width as sidebar */
            padding: 2rem;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            background-color: #fff;
            border: none;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        h3 {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 25px;
            position: relative;
            display: inline-block;
        }

        h3::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -10px;
            height: 3px;
            width: 50%;
            background-color: #0d6efd;
        }

        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(13, 110, 253, 0.15);
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(13, 110, 253, 0.2);
        }

        .btn-primary:active {
            transform: translateY(0px);
        }

        .alert {
            border-radius: 10px;
            font-size: 0.95rem;
            border: none;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.7rem 1rem;
            border: 1px solid #ced4da;
            font-size: 0.95rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
            border-color: #86b7fe;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .form-check-label {
            cursor: pointer;
        }

        .input-group {
            margin-bottom: 1rem;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 8px 0 0 8px;
        }

        .form-control.is-invalid, .was-validated .form-control:invalid {
            border-color: #dc3545;
        }

        .invalid-feedback {
            font-size: 0.85rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #495057;
        }

        .image-preview {
            width: 100%;
            height: 200px;
            border: 2px dashed #ced4da;
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1rem;
            overflow: hidden;
            position: relative;
            background-color: #f8f9fa;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .image-preview-text {
            position: absolute;
            color: #6c757d;
        }

        /* Sidebar styles */
        .sidebar {
            background-color: rgb(86, 117, 148);
            color: white;
            padding: 1.5rem 1rem;
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1010;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-brand img {
            max-width: 120px;
            height: auto;
        }

        .sidebar-nav {
            flex-grow: 1;
            padding-top: 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
        }

        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(3px);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 1rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Form grid layout */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -10px;
            margin-left: -10px;
        }

        .form-col {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 10px;
        }

        @media (max-width: 992px) {
            .form-col {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .container {
                margin-left: 0;
                margin-top: 70px;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: fixed;
                padding: 0.5rem;
                z-index: 1030;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Success alert */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
        }
        
        .toast {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <img src="../tablet/images/logo.png" alt="logo">
            </div>

            <div class="sidebar-nav">
                <div class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </div>

                <div class="nav-item">
                    <a class="nav-link" href="stock.php">
                        <i class="fas fa-box"></i>
                        Stock
                    </a>
                </div>

                <div class="nav-item">
                    <a class="nav-link active" href="ajouterproduit.php">
                        <i class="fas fa-plus-circle"></i>
                        Ajouter produit
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="rapport.php">
                        <i class="fas fa-chart-line"></i>
                        Rapport
                    </a>
                </div>

                <div class="nav-item">
                    <a class="nav-link" href="reception.php">
                        <i class="fas fa-inbox"></i>
                        Réception
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="calendar.php">
                        <i class="fas fa-calendar-alt"></i>
                        Calendar
                    </a>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container">
            <!-- Display success message when redirected with success parameter -->
            <?php if (isset($_GET['success'])): ?>
            <div class="toast-container">
                <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                    <div class="toast-header">
                        <i class="fas fa-check-circle me-2 text-success"></i>
                        <strong class="me-auto">Succès</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        Le produit a été ajouté avec succès!
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <h3 class="mb-4">Ajouter un nouveau produit</h3>

                <?php if ($erreurAjout): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= $erreurAjout ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <form action="ajouterproduit.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="form-section">
                        <div class="form-section-title">Informations générales</div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">
                                        <i class="fas fa-tag me-1"></i> Nom du produit
                                    </label>
                                    <input type="text" class="form-control" name="nom" id="nom" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer le nom du produit.
                                    </div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="mb-3">
                                    <label for="categorie" class="form-label">
                                        <i class="fas fa-layer-group me-1"></i> Catégorie
                                    </label>
                                    <input type="text" class="form-control" name="categorie" id="categorie" list="categoriesList" required>
                                    <datalist id="categoriesList">
                                        <?php foreach($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner une catégorie.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="mb-3">
                                    <label for="prix" class="form-label">
                                        <i class="fas fa-money-bill-wave me-1"></i> Prix (DH)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="prix" step="0.01" id="prix" min="0" required>
                                        <span class="input-group-text">DH</span>
                                        <div class="invalid-feedback">
                                            Veuillez entrer un prix valide.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="mb-3">
                                    <label for="poids" class="form-label">
                                        <i class="fas fa-weight me-1"></i> Poids (kg)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="poids" id="poids" step="0.01" min="0" required>
                                        <span class="input-group-text">kg</span>
                                        <div class="invalid-feedback">
                                            Veuillez entrer un poids valide.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Stock et identification</div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="mb-3">
                                    <label for="quantite" class="form-label">
                                        <i class="fas fa-cubes me-1"></i> Quantité
                                    </label>
                                    <input type="number" class="form-control" name="quantite" id="quantite" min="0" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer une quantité valide.
                                    </div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="mb-3">
                                    <label for="uid" class="form-label">
                                        <i class="fas fa-barcode me-1"></i> UID codebar
                                    </label>
                                    <input type="text" class="form-control" name="uid" id="uid" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer un code-barres valide.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="disponible" name="disponible" value="1" checked>
                            <label class="form-check-label" for="disponible">
                                <i class="fas fa-toggle-on me-1"></i> Produit disponible à la vente
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-section-title">Image du produit</div>
                        <div class="image-preview mb-3" id="imagePreview">
                            <span class="image-preview-text">
                                <i class="fas fa-cloud-upload-alt me-2"></i> Sélectionnez une image
                            </span>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">
                                <i class="fas fa-image me-1"></i> Image du produit
                            </label>
                            <input type="file" class="form-control" name="image" id="image" accept="image/*">
                            <div class="form-text text-muted">
                                Formats acceptés : JPG, JPEG, PNG, GIF. Taille maximale : 5 MB
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="stock.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Retour
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Ajouter le produit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview
        document.getElementById('image').addEventListener('change', function(event) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                }
                reader.readAsDataURL(this.files[0]);
            } else {
                preview.innerHTML = '<span class="image-preview-text"><i class="fas fa-cloud-upload-alt me-2"></i> Sélectionnez une image</span>';
            }
        });

        // Form validation
        (function () {
            'use strict'
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Auto-hide toast after 5 seconds
        const toastEl = document.querySelector('.toast');
        if (toastEl) {
            setTimeout(() => {
                const toast = bootstrap.Toast.getInstance(toastEl);
                if (toast) {
                    toast.hide();
                }
            }, 5000);
        }
    </script>
</body>
</html>
