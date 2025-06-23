<?php
// Date: 2025-06-23 01:24:54
// User: Anass-harrou

// Include database connection
require_once "db.php";

// Define variables and initialize with empty values
$nom = $prix = $quantite = $disponible = $categorie = $uid_codebar = $description = $map_section = "";
$nom_err = $prix_err = $quantite_err = $uid_err = $categorie_err = "";
$current_image = "";
$map_position_x = $map_position_y = 0;

// Processing form data when form is submitted
if (isset($_POST["id"]) && !empty($_POST["id"])) {
    // Get hidden input value
    $id = $_POST["id"];

    // Validate nom
    $input_nom = trim($_POST["nom"]);
    if (empty($input_nom)) {
        $nom_err = "Veuillez entrer un nom de produit.";
    } else {
        $nom = $input_nom;
    }

    // Validate prix
    $input_prix = trim($_POST["prix"]);
    if (empty($input_prix)) {
        $prix_err = "Veuillez entrer le prix du produit.";
    } elseif (!is_numeric($input_prix)) {
        $prix_err = "Veuillez entrer un prix valide.";
    } elseif ($input_prix < 0) {
        $prix_err = "Le prix doit être une valeur positive.";
    } else {
        $prix = $input_prix;
    }

    // Validate quantite
    $input_quantite = trim($_POST["quantite"]);
    if (empty($input_quantite)) {
        $quantite_err = "Veuillez entrer la quantité.";
    } elseif (!ctype_digit($input_quantite)) {
        $quantite_err = "La quantité doit être un nombre entier positif.";
    } elseif ($input_quantite < 0) {
        $quantite_err = "La quantité doit être une valeur positive.";
    } else {
        $quantite = $input_quantite;
    }

    // Get disponible value from the form
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    
    // Get other form values
    $categorie = trim($_POST['categorie']);
    $uid_codebar = trim($_POST['uid_codebar']);
    $description = trim($_POST['description']);
    $map_section = trim($_POST['map_section']);
    $map_position_x = !empty($_POST['map_position_x']) ? intval($_POST['map_position_x']) : 0;
    $map_position_y = !empty($_POST['map_position_y']) ? intval($_POST['map_position_y']) : 0;

    // Handle image upload if a new image is selected
    $image_update = "";
    if (!empty($_FILES["image"]["name"])) {
        $target_dir = "../uploads/";
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check if image file is actual image
        $valid_image = getimagesize($_FILES["image"]["tmp_name"]);
        if ($valid_image) {
            // Move uploaded file to target directory
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_update = ", image = '$target_file'";
            }
        }
    }

    // Check input errors before inserting in database
    if (empty($nom_err) && empty($prix_err) && empty($quantite_err)) {
        // Prepare an update statement
        $sql = "UPDATE produits SET nom=?, prix=?, quantite=?, disponible=?, 
                categorie=?, uid_codebar=?, description=?, map_section=?, map_position_x=?, map_position_y=?
                $image_update
                WHERE id=?";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(1, $nom);
            $stmt->bindParam(2, $prix);
            $stmt->bindParam(3, $quantite);
            $stmt->bindParam(4, $disponible, PDO::PARAM_INT);
            $stmt->bindParam(5, $categorie);
            $stmt->bindParam(6, $uid_codebar);
            $stmt->bindParam(7, $description);
            $stmt->bindParam(8, $map_section);
            $stmt->bindParam(9, $map_position_x, PDO::PARAM_INT);
            $stmt->bindParam(10, $map_position_y, PDO::PARAM_INT);
            $stmt->bindParam(11, $id, PDO::PARAM_INT);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Records updated successfully. Redirect to landing page
                header("location: stock.php?updated=true");
                exit();
            } else {
                echo "Une erreur s'est produite. Veuillez réessayer.";
            }

            // Close statement
            unset($stmt);
        }
    }

    // Close connection (PDO closes automatically when the script ends, but it's good practice to unset)
    unset($pdo);
} else {
    // Check existence of id parameter before processing further
    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        // Get URL parameter
        $id = trim($_GET["id"]);

        // Prepare a select statement
        $sql = "SELECT * FROM produits WHERE id = ?";
        if ($stmt = $pdo->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(1, $id, PDO::PARAM_INT);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Retrieve individual field value
                    $nom = $row["nom"];
                    $prix = $row["prix"];
                    $quantite = $row["quantite"];
                    $disponible = $row["disponible"];
                    $categorie = $row["categorie"] ?? '';
                    $uid_codebar = $row["uid_codebar"] ?? '';
                    $current_image = $row["image"] ?? '';
                    $description = $row["description"] ?? '';
                    $map_section = $row["map_section"] ?? 'A1';
                    $map_position_x = $row["map_position_x"] ?? 0;
                    $map_position_y = $row["map_position_y"] ?? 0;
                } else {
                    // URL doesn't contain valid id. Redirect to error page
                    header("location: error.php");
                    exit();
                }
            } else {
                echo "Une erreur s'est produite. Veuillez réessayer.";
            }

            // Close statement
            unset($stmt);
        }

        // Get available categories
        $categories = [];
        $cat_sql = "SELECT DISTINCT categorie FROM produits WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie";
        if ($cat_stmt = $pdo->query($cat_sql)) {
            while ($cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC)) {
                $categories[] = $cat_row['categorie'];
            }
        }
    } else {
        // URL doesn't contain id parameter. Redirect to error page
        header("location: error.php");
        exit();
    }
}

// Function to check if image exists
function imageExists($path) {
    if (empty($path)) return false;
    
    // Try different path combinations
    if (file_exists($path)) return true;
    if (file_exists('../' . $path)) return true;
    if (file_exists('../' . ltrim($path, '../'))) return true;
    
    return false;
}

// Get image path for display
function getImagePath($imagePath) {
    if (empty($imagePath)) return '../uploads/placeholder.png';
    
    if (file_exists($imagePath)) return $imagePath;
    if (file_exists('../' . $imagePath)) return '../' . $imagePath;
    if (file_exists('../' . ltrim($imagePath, '../'))) return '../' . ltrim($imagePath, '../');
    
    return '../uploads/placeholder.png';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Produit | Smart Grocery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
            --bg-color: rgb(200, 229, 247);
        }
        
        body {
            background-color: var(--bg-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            padding-bottom: 40px;
        }
        
        .wrapper {
            max-width: 1100px;
            margin: 20px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 15px;
        }
        
        .form-title {
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .form-container {
            padding: 20px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
            border-color: #bac8f3;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #3a5bbf;
            border-color: #3a5bbf;
        }
        
        .image-preview-container {
            position: relative;
            width: 100%;
            height: 220px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
        }
        
        .image-upload-placeholder {
            font-size: 16px;
            color: #999;
            text-align: center;
            padding: 20px;
        }
        
        .image-upload-placeholder i {
            font-size: 48px;
            margin-bottom: 10px;
            display: block;
        }
        
        .custom-file-input {
            cursor: pointer;
        }
        
        .product-info-card {
            background-color: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .product-info-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            font-weight: 600;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-back {
            color: white;
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-back:hover {
            background-color: #15a775;
            border-color: #15a775;
            color: white;
        }
        
        .map-preview {
            width: 100%;
            height: 150px;
            background-color: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .map-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: red;
            position: absolute;
            transform: translate(-50%, -50%);
        }
        
        .map-section {
            position: absolute;
            font-size: 12px;
            color: #666;
        }
        
        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .wrapper {
                padding: 20px;
                margin: 10px;
            }
            
            .form-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="stock.php">Stock</a></li>
                <li class="breadcrumb-item active" aria-current="page">Modifier Produit</li>
            </ol>
        </nav>
    </div>

    <div class="wrapper">
        <div class="form-header">
            <div>
                <h2 class="form-title">Modifier Produit ID: <?php echo $id; ?></h2>
                <p class="text-muted">Utilisez ce formulaire pour mettre à jour les informations du produit.</p>
            </div>
            <div>
                <a href="stock.php" class="btn btn-back">
                    <i class="fas fa-arrow-left me-1"></i> Retour au stock
                </a>
            </div>
        </div>
        
        <div class="form-container">
            <form action="<?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-8">
                        <div class="product-info-card mb-4">
                            <div class="product-info-title">Informations produit</div>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="nom" class="form-label">Nom du produit</label>
                                    <input type="text" name="nom" id="nom" class="form-control <?php echo (!empty($nom_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $nom; ?>">
                                    <span class="invalid-feedback"><?php echo $nom_err; ?></span>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="categorie" class="form-label">Catégorie</label>
                                    <select name="categorie" id="categorie" class="form-select">
                                        <?php if (!in_array($categorie, $categories) && !empty($categorie)): ?>
                                            <option value="<?php echo htmlspecialchars($categorie); ?>" selected><?php echo htmlspecialchars($categorie); ?></option>
                                        <?php endif; ?>
                                        
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categorie === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                        <?php endforeach; ?>
                                        
                                        <option value="Autre">Autre...</option>
                                    </select>
                                    <div id="newCategoryGroup" class="mt-2 d-none">
                                        <input type="text" id="newCategory" class="form-control" placeholder="Nouvelle catégorie">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="prix" class="form-label">Prix (DH)</label>
                                    <input type="text" name="prix" id="prix" class="form-control <?php echo (!empty($prix_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $prix; ?>">
                                    <span class="invalid-feedback"><?php echo $prix_err; ?></span>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="quantite" class="form-label">Quantité en stock</label>
                                    <input type="number" name="quantite" id="quantite" class="form-control <?php echo (!empty($quantite_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $quantite; ?>" min="0">
                                    <span class="invalid-feedback"><?php echo $quantite_err; ?></span>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="uid_codebar" class="form-label">Code-barres / UID</label>
                                    <input type="text" name="uid_codebar" id="uid_codebar" class="form-control" value="<?php echo $uid_codebar; ?>">
                                    <div class="form-text">Code unique pour l'identification du produit</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label d-block">Disponibilité</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="disponible" id="disponible" value="1" <?= $disponible == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="disponible">
                                            <span id="disponibleText"><?= $disponible == 1 ? 'Produit disponible' : 'Produit indisponible' ?></span>
                                        </label>
                                    </div>
                                    <div class="form-text">Définir si le produit est disponible à la vente</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="3"><?php echo $description; ?></textarea>
                                <div class="form-text">Description détaillée du produit (optionnel)</div>
                            </div>
                        </div>
                        
                        <div class="product-info-card">
                            <div class="product-info-title">Emplacement en magasin</div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="map_section" class="form-label">Section</label>
                                    <input type="text" name="map_section" id="map_section" class="form-control" value="<?php echo $map_section; ?>" maxlength="5">
                                    <div class="form-text">Ex: A1, B2, C3, etc.</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="map_position_x" class="form-label">Position X</label>
                                    <input type="number" name="map_position_x" id="map_position_x" class="form-control" value="<?php echo $map_position_x; ?>" min="0" max="200">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="map_position_y" class="form-label">Position Y</label>
                                    <input type="number" name="map_position_y" id="map_position_y" class="form-control" value="<?php echo $map_position_y; ?>" min="0" max="200">
                                </div>
                            </div>
                            
                            <div class="map-preview">
                                <div class="map-section" id="mapSectionLabel"><?php echo $map_section; ?></div>
                                <div class="map-dot" id="mapDot" style="left: <?php echo $map_position_x; ?>%; top: <?php echo $map_position_y; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="product-info-card">
                            <div class="product-info-title">Image du produit</div>
                            <div class="image-preview-container">
                                <?php if (!empty($current_image) && imageExists(getImagePath($current_image))): ?>
                                    <img src="<?php echo htmlspecialchars(getImagePath($current_image)); ?>" alt="<?php echo htmlspecialchars($nom); ?>" class="image-preview" id="imagePreview">
                                <?php else: ?>
                                    <div class="image-upload-placeholder" id="imagePlaceholder">
                                        <i class="fas fa-camera"></i>
                                        Aucune image
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">Changer l'image</label>
                                <input class="form-control" type="file" id="image" name="image" accept="image/*">
                                <div class="form-text">Formats supportés: JPG, PNG, GIF. Max 2MB.</div>
                            </div>
                            
                            <?php if (!empty($current_image)): ?>
                            <div class="mb-3">
                                <div class="form-text">
                                    Image actuelle: <?php echo basename($current_image); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                                <i class="fas fa-save me-1"></i> Enregistrer les modifications
                            </button>
                            
                            <a href="stock.php" class="btn btn-outline-secondary w-100">
                                Annuler
                            </a>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="id" value="<?php echo $id; ?>"/>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Image preview
            $('#image').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#imagePreview').attr('src', e.target.result);
                        $('#imagePreview').removeClass('d-none');
                        $('#imagePlaceholder').addClass('d-none');
                    }
                    reader.readAsDataURL(file);
                }
            });
            
            // Handle custom category
            $('#categorie').change(function() {
                if ($(this).val() === 'Autre') {
                    $('#newCategoryGroup').removeClass('d-none');
                } else {
                    $('#newCategoryGroup').addClass('d-none');
                }
            });
            
            // Handle new category input
            $('#newCategory').change(function() {
                if ($(this).val().trim() !== '') {
                    // Create new option
                    const newOption = $('<option></option>')
                        .val($(this).val().trim())
                        .text($(this).val().trim())
                        .prop('selected', true);
                    
                    // Replace "Other" with the new category and re-add "Other" at the end
                    $('#categorie option[value="Autre"]').remove();
                    $('#categorie').append(newOption);
                    $('#categorie').append($('<option></option>').val('Autre').text('Autre...'));
                    
                    // Hide the input
                    $('#newCategoryGroup').addClass('d-none');
                }
            });
            
            // Update availability text
            $('#disponible').change(function() {
                if ($(this).is(':checked')) {
                    $('#disponibleText').text('Produit disponible');
                } else {
                    $('#disponibleText').text('Produit indisponible');
                }
            });
            
            // Update map preview
            function updateMapPreview() {
                const section = $('#map_section').val() || 'A1';
                const x = $('#map_position_x').val() || 50;
                const y = $('#map_position_y').val() || 50;
                
                $('#mapSectionLabel').text(section);
                $('#mapDot').css({
                    'left': x + '%',
                    'top': y + '%'
                });
            }
            
            $('#map_section, #map_position_x, #map_position_y').on('input', updateMapPreview);
            
            // Update preview on page load
            updateMapPreview();
        });
    </script>
</body>
</html>