<?php
// Include config file
require_once "config.php";

// Define variables and initialize with empty values
$name = $address = $solde = $num_compte = $password = "";
$name_err = $address_err = $solde_err = $num_compte_err = $password_err = "";

// Processing form data when form is submitted
if(isset($_POST["id"]) && !empty($_POST["id"])){
    // Get hidden input value
    $id = $_POST["id"];

    // Validate name
    $input_name = trim($_POST["name"]);
    if(empty($input_name)){
        $name_err = "Please enter a name.";
    } elseif(!filter_var($input_name, FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/^[a-zA-Z\s]+$/")))){
        $name_err = "Please enter a valid name.";
    } else{
        $name = $input_name;
    }

    // Validate address address
    $input_address = trim($_POST["address"]);
    if(empty($input_address)){
        $address_err = "Please enter an address.";
    } else{
        $address = $input_address;
    }

    // Validate solde
    $input_solde = trim($_POST["solde"]);
    if(empty($input_solde)){
        $solde_err = "Please enter the solde amount.";
    } elseif(!ctype_digit($input_solde)){
        $solde_err = "Please enter a positive integer value.";
    } else{
        $solde = $input_solde;
    }
    // Validate num_compte
    $input_num_compte = trim($_POST["num_compte"]);
    if(empty($input_num_compte)){
        $num_compte_err = "Please enter the account number.";
    } else{
        $num_compte = $input_num_compte;
    }

    // Validate password (optional - only update if provided)
    if(!empty(trim($_POST["password"]))){
        if(strlen(trim($_POST["password"])) < 8){
            $password_err = "Password must have at least 8 characters.";
        } else {
            $password = trim($_POST["password"]);
            $password = password_hash($password, PASSWORD_DEFAULT); // Hash the password
        }
    }

    // Check input errors before inserting in database
    if(empty($name_err) && empty($address_err) && empty($solde_err) && empty($num_compte_err) && empty($password_err)){
        // Prepare an update statement
        // If password is being updated
        if(!empty($password)){
            $sql = "UPDATE client SET name=?, address=?, solde=?, num_commande=?, password=? WHERE id=?";
        }
        // If password is not being updated
        else{
            $sql = "UPDATE client SET name=?, address=?, solde=?, num_commande=? WHERE id=?";
        }

        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            if(!empty($password)){
                mysqli_stmt_bind_param($stmt, "sssssi", $param_name, $param_address, $param_solde, $param_num_compte, $param_password, $param_id);
            } else {
                 mysqli_stmt_bind_param($stmt, "ssssi", $param_name, $param_address, $param_solde, $param_num_compte, $param_id);
            }


            // Set parameters
            $param_name = $name;
            $param_address = $address;
            $param_solde = $solde;
            $param_num_compte = $num_compte;
            if(!empty($password)){
                $param_password = $password;
            }
            $param_id = $id;

            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Records updated successfully. Redirect to landing page
                header("location: dashboard.php");
                exit();
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }
        }

        // Close statement
        mysqli_stmt_close($stmt);
    }

    // Close connection
    mysqli_close($link);
} else{
    // Check existence of id parameter before processing further
    if(isset($_GET["id"]) && !empty(trim($_GET["id"]))){
        // Get URL parameter
        $id =  trim($_GET["id"]);

        // Prepare a select statement
        $sql = "SELECT * FROM client WHERE id = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "i", $param_id);

            // Set parameters
            $param_id = $id;

            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);

                if(mysqli_num_rows($result) == 1){
                    /* Fetch result row as an associative array. Since the result set
                    contains only one row, we don't need to use while loop */
                    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);

                    // Retrieve individual field value
                    $name = $row["name"];
                    $address = $row["address"];
                    $solde = $row["solde"];
                    $num_compte = $row["num_commande"];
                } else{
                    // URL doesn't contain valid id. Redirect to error page
                    header("location: error.php");
                    exit();
                }

            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }
        }

        // Close statement
        mysqli_stmt_close($stmt);

        // Close connection
        mysqli_close($link);
    }  else{
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
        .wrapper{
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
                    <p>Please edit the input values and submit to update the employee record.</p>
                    <form action="<?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>" method="post">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $name; ?>">
                            <span class="invalid-feedback"><?php echo $name_err;?></span>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control <?php echo (!empty($address_err)) ? 'is-invalid' : ''; ?>"><?php echo $address; ?></textarea>
                            <span class="invalid-feedback"><?php echo $address_err;?></span>
                        </div>
                        <div class="form-group">
                            <label>solde</label>
                            <input type="text" name="solde" class="form-control <?php echo (!empty($solde_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $solde; ?>">
                            <span class="invalid-feedback"><?php echo $solde_err;?></span>
                        </div>
                        <div class="form-group">
                            <label>num de compte</label>
                            <input type="text" name="num_compte" class="form-control <?php echo (!empty($num_compte_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $num_compte; ?>">
                            <span class="invalid-feedback"><?php echo $num_compte_err;?></span>
                        </div>
                         <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="">
                            <span class="invalid-feedback"><?php echo $password_err;?></span>
                        </div>
                        <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="index.php" class="btn btn-secondary ml-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>