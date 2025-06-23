<?php
// Process delete operation after confirmation
if(isset($_POST["id"]) && !empty($_POST["id"])){
    // Include config file
    require_once "../config.php";
    
    // Begin transaction to ensure data integrity
    mysqli_begin_transaction($link);
    
    try {
        $client_id = trim($_POST["id"]);
        
        // 1. First delete records from achat_produits linked to this client's purchases
        $sql_get_purchases = "SELECT id_achat FROM achats WHERE id_utilisateur = ?";
        if($stmt_purchases = mysqli_prepare($link, $sql_get_purchases)){
            mysqli_stmt_bind_param($stmt_purchases, "i", $client_id);
            mysqli_stmt_execute($stmt_purchases);
            $purchase_result = mysqli_stmt_get_result($stmt_purchases);
            
            while($purchase_row = mysqli_fetch_assoc($purchase_result)) {
                $purchase_id = $purchase_row['id_achat'];
                
                // Delete purchase items for this purchase
                $sql_delete_items = "DELETE FROM achat_produits WHERE id_achat = ?";
                $stmt_items = mysqli_prepare($link, $sql_delete_items);
                mysqli_stmt_bind_param($stmt_items, "i", $purchase_id);
                mysqli_stmt_execute($stmt_items);
                mysqli_stmt_close($stmt_items);
            }
            
            mysqli_stmt_close($stmt_purchases);
        }
        
        // 2. Delete purchases (achats) related to the client
        $sql_delete_purchases = "DELETE FROM achats WHERE id_utilisateur = ?";
        $stmt_delete_purchases = mysqli_prepare($link, $sql_delete_purchases);
        mysqli_stmt_bind_param($stmt_delete_purchases, "i", $client_id);
        mysqli_stmt_execute($stmt_delete_purchases);
        mysqli_stmt_close($stmt_delete_purchases);
        
        // 3. Delete transactions related to the client
        $sql_delete_transactions = "DELETE FROM transactions WHERE client_id = ?";
        $stmt_delete_transactions = mysqli_prepare($link, $sql_delete_transactions);
        mysqli_stmt_bind_param($stmt_delete_transactions, "i", $client_id);
        mysqli_stmt_execute($stmt_delete_transactions);
        mysqli_stmt_close($stmt_delete_transactions);
        
        // 4. Delete payment flags related to the client
        $sql_delete_flags = "DELETE FROM payment_flags WHERE user_id = ?";
        $stmt_delete_flags = mysqli_prepare($link, $sql_delete_flags);
        mysqli_stmt_bind_param($stmt_delete_flags, "i", $client_id);
        mysqli_stmt_execute($stmt_delete_flags);
        mysqli_stmt_close($stmt_delete_flags);
        
        // 5. Finally, delete the client
        $sql_delete_client = "DELETE FROM client WHERE id = ?";
        $stmt_delete_client = mysqli_prepare($link, $sql_delete_client);
        mysqli_stmt_bind_param($stmt_delete_client, "i", $client_id);
        mysqli_stmt_execute($stmt_delete_client);
        mysqli_stmt_close($stmt_delete_client);
        
        // Commit the transaction if everything was successful
        mysqli_commit($link);
        
        // Records deleted successfully. Redirect to landing page
        header("location: dashboard.php");
        exit();
        
    } catch (Exception $e) {
        // Roll back the transaction if something failed
        mysqli_rollback($link);
        echo "Error: " . $e->getMessage();
    }

    // Close connection
    mysqli_close($link);
} else{
    // Check existence of id parameter
    if(empty(trim($_GET["id"]))){
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
    <title>Delete Client Record</title>
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
                    <h2 class="mt-5 mb-3">Delete Client Record</h2>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="alert alert-danger">
                            <input type="hidden" name="id" value="<?php echo trim($_GET["id"]); ?>"/>
                            <p>Are you sure you want to delete this client record?</p>
                            <p><b>Warning:</b> This will also delete all purchases, transactions, and payment records associated with this client.</p>
                            <p>
                                <input type="submit" value="Yes, Delete Everything" class="btn btn-danger">
                                <a href="dashboard.php" class="btn btn-secondary ml-2">No, Cancel</a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>