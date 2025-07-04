
<?php
// Process delete operation after confirmation
if(isset($_POST["id"]) && !empty($_POST["id"])){
    // Include config file
    require_once "db.php";

    // Prepare a delete statement
    $sql = "DELETE FROM produits WHERE id = :id";
    if($stmt = $pdo->prepare($sql)){
        // Bind variables to the prepared statement as parameters
        $stmt->bindParam(':id', $param_id, PDO::PARAM_INT);

        // Set parameters
        $param_id = trim($_POST["id"]);

        // Attempt to execute the prepared statement
        if($stmt->execute()){
            // Records deleted successfully. Redirect to landing page
            header("location: dashboard.php");
            exit();
        } else{
            echo " Something went wrong. Please try again later.";
        }
    }

    // Close statement (not strictly necessary with PDO)
    unset($stmt);

    // Close connection (not strictly necessary with PDO at this point)
    unset($pdo);
} else{
    // Check existence of id parameter
    if(empty(trim($_GET["id"]))){
        // URL doesn't contain id parameter. Redirect to error page
        header("location: dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Record</title>
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
                    <h2 class="mt-5 mb-3">Delete Record</h2>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="alert alert-danger">
                            <input type="hidden" name="id" value="<?php echo trim($_GET["id"]); ?>"/>
                            <p>Are you sure you want to delete this product ?</p>
                            <p>
                                <input type="submit" value="Yes" class="btn btn-danger">
                                <a href="stock.php" class="btn btn-secondary">No</a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>