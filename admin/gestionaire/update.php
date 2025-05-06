<?php

// Include database connection
require_once "db.php";

// Define variables and initialize with empty values
$nom = $prix = $quantite = $disponible = "";
$nom_err = $prix_err = $quantite_err = "";

// Processing form data when form is submitted
if (isset($_POST["id"]) && !empty($_POST["id"])) {
    // Get hidden input value
    $id = $_POST["id"];

    // Validate nom
    $input_nom = trim($_POST["nom"]);
    if (empty($input_nom)) {
        $nom_err = "Please enter a name.";
    } else {
        $nom = $input_nom;
    }

    // Validate prix
    $input_prix = trim($_POST["prix"]);
    if (empty($input_prix)) {
        $prix_err = "Please enter the price amount.";
    } elseif (!is_numeric($input_prix)) {
        $prix_err = "Please enter a valid numeric price.";
    } elseif ($input_prix < 0) {
        $prix_err = "Price must be a non-negative value.";
    } else {
        $prix = $input_prix;
    }

    // Validate quantite
    $input_quantite = trim($_POST["quantite"]);
    if (empty($input_quantite)) {
        $quantite_err = "Please enter the quantity.";
    } elseif (!ctype_digit($input_quantite)) {
        $quantite_err = "Quantity must be a positive integer.";
    } elseif ($input_quantite < 0) {
        $quantite_err = "Quantity must be a non-negative value.";
    } else {
        $quantite = $input_quantite;
    }

    // Get disponible value from the form
    $disponible = isset($_POST['disponible']) ? 1 : 0;

    // Check input errors before inserting in database
    if (empty($nom_err) && empty($prix_err) && empty($quantite_err)) {
        // Prepare an update statement
        $sql = "UPDATE produits SET nom=?, prix=?, quantite=?, disponible=? WHERE id=?";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(1, $nom);
            $stmt->bindParam(2, $prix);
            $stmt->bindParam(3, $quantite);
            $stmt->bindParam(4, $disponible, PDO::PARAM_INT); // Bind disponible
            $stmt->bindParam(5, $id, PDO::PARAM_INT);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Records updated successfully. Redirect to landing page
                header("location: dashboard.php");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
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
                    $disponible = $row["disponible"]; // Retrieve disponible
                } else {
                    // URL doesn't contain valid id. Redirect to error page
                    header("location: error.php");
                    exit();
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            unset($stmt);
        }

        // Close connection (PDO closes automatically when the script ends, but it's good practice to unset)
        unset($pdo);
    } else {
        // URL doesn't contain id parameter. Redirect to error page
        header("location: error.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Record</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .wrapper {
            width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h2 class="mt-5">Update Record</h2>
                <p>Please edit the input values and submit to update the product record.</p>
                <form action="<?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>" method="post">
                    <div class="form-group">
                        <label>Nom</label>
                        <textarea name="nom" class="form-control <?php echo (!empty($nom_err)) ? 'is-invalid' : ''; ?>"><?php echo $nom; ?></textarea>
                        <span class="invalid-feedback"><?php echo $nom_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Prix</label>
                        <input type="text" name="prix" class="form-control <?php echo (!empty($prix_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $prix; ?>">
                        <span class="invalid-feedback"><?php echo $prix_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Quantité</label>
                        <input type="text" name="quantite" class="form-control <?php echo (!empty($quantite_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $quantite; ?>">
                        <span class="invalid-feedback"><?php echo $quantite_err; ?></span>
                    </div>
                    <!-- Add disponible checkbox -->
                    <div class="form-group">
                        <label>Disponibilité</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="disponible" id="disponible" value="1" <?= $disponible == 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="disponible">Disponible</label>
                        </div>
                    </div>
                    <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                    <input type="submit" class="btn btn-primary" value="Submit">
                    <a href="dashboard.php" class="btn btn-secondary ml-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>