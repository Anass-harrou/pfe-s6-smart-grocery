<?php
session_start();



// Check if user is logged in and has admin role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    // Not authorized, redirect to login
    header('Location: ../login.php');
    exit();
}

// Include config file
require_once "../config.php";

// Define variables and initialize with empty values
$name = $email = $address = $solde = $num_commande = $phone = $bio = $password = $confirm_password = $rfid_uid = "";
$name_err = $email_err = $address_err = $solde_err = $num_commande_err = $phone_err = $password_err = $confirm_password_err = $rfid_uid_err = "";

// Current UTC date/time and user info for display
$current_datetime = date('Y-m-d H:i:s');
$current_user = htmlspecialchars($_SESSION['user']['username']);

// Check if RFID scan is submitted via AJAX
if(isset($_GET['check_rfid_scan'])) {
    header('Content-Type: application/json');
    
    // Check for file with RFID scan data
    $scan_file = '/GHMARIIII/rfid_scans/latest_scan.txt';
    $new_scan_detected = false;
    $scan_data = null;
    
    if(file_exists($scan_file) && filesize($scan_file) > 0) {
        $scan_content = file_get_contents($scan_file);
        $scan_info = json_decode($scan_content, true);
        
        if($scan_info && isset($scan_info['rfid_uid']) && isset($scan_info['timestamp'])) {
            // Check if scan is recent (within last 30 seconds)
            $scan_time = strtotime($scan_info['timestamp']);
            $current_time = time();
            
            if(($current_time - $scan_time) <= 30) {
                $new_scan_detected = true;
                $scan_data = $scan_info;
                
                // Check if this RFID is already registered
                $sql = "SELECT id, name FROM client WHERE rfid_uid = ?";
                if($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "s", $scan_info['rfid_uid']);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if(mysqli_num_rows($result) > 0) {
                        $existing_user = mysqli_fetch_assoc($result);
                        echo json_encode([
                            'scan_detected' => true,
                            'rfid_uid' => $scan_info['rfid_uid'],
                            'already_registered' => true,
                            'user' => $existing_user['name']
                        ]);
                        exit;
                    }
                    
                    mysqli_stmt_close($stmt);
                }
                
                // Move the scan file to prevent reuse
                rename($scan_file, '/GHMARIIII/rfid_scans/processed_' . time() . '.txt');
            }
        }
    }
    
    echo json_encode([
        'scan_detected' => $new_scan_detected,
        'rfid_uid' => $new_scan_detected ? $scan_info['rfid_uid'] : null,
        'already_registered' => false
    ]);
    exit;
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Validate name
    $input_name = trim($_POST["name"]);
    if(empty($input_name)){
        $name_err = "Please enter a name.";
    } elseif(!preg_match("/^[a-zA-Z0-9 ]+$/", $input_name)){
        $name_err = "Name can only contain letters, numbers, and spaces.";
    } else{
        $name = $input_name;
    }

    // Validate email
    $input_email = trim($_POST["email"]);
    if(empty($input_email)){
        $email_err = "Please enter an email.";
    } elseif(!filter_var($input_email, FILTER_VALIDATE_EMAIL)){
        $email_err = "Please enter a valid email address.";
    } else{
        // Check if email already exists
        $sql = "SELECT id FROM client WHERE email = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $input_email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) > 0){
                $email_err = "This email is already registered.";
            } else {
                $email = $input_email;
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Validate address
    $input_address = trim($_POST["address"]);
    if(empty($input_address)){
        $address_err = "Please enter an address.";
    } else{
        $address = $input_address;
    }

    // Validate solde (balance)
    $input_solde = trim($_POST["solde"]);
    if(empty($input_solde)){
        $solde_err = "Please enter the initial balance.";
    } elseif(!is_numeric($input_solde)){
        $solde_err = "Please enter a valid number for the balance.";
    } else{
        $solde = $input_solde;
    }

    // Validate num_commande (order number)
    $input_num_commande = trim($_POST["num_commande"]);
    if(!empty($input_num_commande)){
        // Check if the number is already in use
        $sql = "SELECT id FROM client WHERE num_commande = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $input_num_commande);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) > 0){
                $num_commande_err = "This order number is already in use.";
            } else {
                $num_commande = $input_num_commande;
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Generate a random order number if not provided
        $num_commande = "CMD" . rand(10000, 99999);
    }

    // Validate phone (optional)
    $input_phone = trim($_POST["phone"]);
    if(!empty($input_phone) && !preg_match("/^[0-9+\-\s]+$/", $input_phone)){
        $phone_err = "Please enter a valid phone number.";
    } else{
        $phone = $input_phone;
    }
    
    // Get RFID UID
    $rfid_uid = trim($_POST["rfid_uid"]);
    
    // If RFID UID is provided, check if it's already in use
    if(!empty($rfid_uid)) {
        $sql = "SELECT id FROM client WHERE rfid_uid = ?";
        if($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $rfid_uid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) > 0) {
                $rfid_uid_err = "This RFID card is already registered to another user.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Get bio
    $bio = trim($_POST["bio"]);

    // Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter a password.";
    } elseif(strlen(trim($_POST["password"])) < 8){
        $password_err = "Password must have at least 8 characters.";
    } else{
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm password.";
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
        }
    }

    // Check input errors before inserting in database
    if(empty($name_err) && empty($email_err) && empty($address_err) && empty($solde_err) && 
       empty($num_commande_err) && empty($phone_err) && empty($password_err) && empty($confirm_password_err) && empty($rfid_uid_err)){
        
        // Prepare an insert statement
        $sql = "INSERT INTO client (name, email, address, solde, num_commande, phone, bio, password, rfid_uid) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "sssdsssss", $param_name, $param_email, $param_address, 
                                $param_solde, $param_num_commande, $param_phone, $param_bio, $param_password, $param_rfid_uid);

            // Set parameters
            $param_name = $name;
            $param_email = $email;
            $param_address = $address;
            $param_solde = $solde;
            $param_num_commande = $num_commande;
            $param_phone = $phone;
            $param_bio = $bio;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Hash the password
            $param_rfid_uid = $rfid_uid;

            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Records created successfully. Redirect to dashboard
                header("location: dashboard.php");
                exit();
            } else{
                echo "Oops! Something went wrong. Please try again later. Error: " . mysqli_error($link);
            }
        }

        // Close statement
        mysqli_stmt_close($stmt);
    }

    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Client - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --text-color: #5a5c69;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            background-color: #f8f9fc;
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, #224abe 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar .logo {
            font-size: 1.5rem;
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .logo {
    display: flex;
    align-items: center;
    padding: 20px 15px;
    background-color: rgba(0, 0, 0, 0.1);
    margin-bottom: 15px;
}

.sidebar-logo {
    height: 40px; /* Adjust based on your logo and sidebar size */
    width: auto;
    margin-right: 10px;
    object-fit: contain; /* This ensures the logo maintains its aspect ratio */
}

.logo-text {
    font-size: 1.2rem;
    font-weight: 600;
    color: #fff; /* Adjust color based on your sidebar background */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* For collapsed sidebar (if you have this feature) */
.sidebar.collapsed .logo-text {
    display: none;
}

.sidebar.collapsed .sidebar-logo {
    margin-right: 0;
    margin-left: auto;
    margin-right: auto;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .logo {
        padding: 15px 10px;
    }
    
    .sidebar-logo {
        height: 32px; /* Slightly smaller on mobile */
    }
    
    .logo-text {
        font-size: 1rem;
    }
}
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding: 12px 15px;
            transition: all 0.2s;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .content {
            margin-left: 250px;
            padding: 15px;
            transition: all 0.3s;
        }
        
        .form-container {
            background: white;
            border-radius: 5px;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            padding: 30px;
        }
        
        .top-bar {
            padding: 1rem;
            background: white;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);
            margin-bottom: 1.5rem;
            border-radius: 5px;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .user-dropdown .dropdown-toggle::after {
            display: none;
        }
        
        .form-control {
            border-radius: 4px;
            border: 1px solid #d1d3e2;
            font-size: 0.9rem;
            padding: .8rem 1rem;
        }
        
        .form-control:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
        
        .form-section-divider {
            border-top: 1px solid #e3e6f0;
            margin: 30px 0;
        }
        
        .form-section-title {
            color: #4e73df;
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .footer {
            padding: 20px 0;
            margin-top: 20px;
            font-size: 0.8rem;
            color: #858796;
            border-top: 1px solid #e3e6f0;
            text-align: center;
        }
        
        .form-group label {
            font-weight: 500;
            color: #5a5c69;
            font-size: 0.9rem;
        }
        
        .required-field::after {
            content: " *";
            color: #e74a3b;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .content {
                margin-left: 0;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .content.active {
                margin-left: 250px;
            }
        }
        
        .rfid-scanner-section {
            background-color: #f0f8ff;
            border: 1px solid #b8daff;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .scan-status {
            height: 24px;
            font-weight: 500;
        }
        
        .scan-animation {
            animation: scan-pulse 1.5s infinite;
        }
        
        @keyframes scan-pulse {
            0% { opacity: 0.5; }
            50% { opacity: 1; }
            100% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
     <nav class="sidebar">
        <div class="logo">
        <img src="../smart_cart_transparent.png" alt="Smart Grocery Logo" class="sidebar-logo">
        <span class="logo-text">Smart Grocery</span>
    </div>
        <div class="user-info p-3 text-center mb-3">
            <div class="avatar mx-auto mb-2">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></div>
            <small class="text-muted">Administrator</small>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="#">
                    <i class="fas fa-users"></i> Clients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="historique.php">
                    <i class="fas fa-shopping-cart"></i> Purchase History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            
            <li class="nav-item mt-4">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <!-- Top Navigation Bar -->
        <div class="top-bar d-flex justify-content-between align-items-center">
            <div>
                <h4 class="m-0">Create New Client</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="user-dropdown dropdown">
                    <a class="dropdown-toggle text-decoration-none text-dark d-flex align-items-center" href="#" role="button" id="userDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar mr-2">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="d-none d-sm-block">
                            <?php echo htmlspecialchars($_SESSION['user']['username']); ?>
                            <i class="fas fa-chevron-down ml-1 small"></i>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-user-circle fa-sm fa-fw mr-2 text-gray-400"></i> Profile
                        </a>
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="../logout.php">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h5 class="mb-0 text-gray-800">Client Information</h5>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent m-0 p-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Create Client</li>
                    </ol>
                </nav>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <!-- Basic Information -->
                <div class="form-section-title">
                    <i class="fas fa-user-circle mr-2"></i> Basic Information
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required-field">Full Name</label>
                            <input type="text" name="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $name; ?>" placeholder="Enter client's full name">
                            <span class="invalid-feedback"><?php echo $name_err; ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required-field">Email Address</label>
                            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" placeholder="Enter email address">
                            <span class="invalid-feedback"><?php echo $email_err; ?></span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone; ?>" placeholder="Enter phone number">
                            <span class="invalid-feedback"><?php echo $phone_err; ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required-field">Address</label>
                            <textarea name="address" class="form-control <?php echo (!empty($address_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter full address"><?php echo $address; ?></textarea>
                            <span class="invalid-feedback"><?php echo $address_err; ?></span>
                        </div>
                    </div>
                </div>

                <!-- RFID Card Registration Section -->
                <div class="form-section-divider"></div>
                <div class="form-section-title">
                    <i class="fas fa-id-card mr-2"></i> RFID Card Registration
                </div>
                
                <div class="rfid-scanner-section">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group mb-3">
                                <label>RFID Card UID</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    </div>
                                    <input type="text" id="rfid_uid" name="rfid_uid" class="form-control <?php echo (!empty($rfid_uid_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $rfid_uid; ?>" placeholder="Scan card or enter UID manually" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="clearRfidBtn">
                                            <i class="fas fa-times"></i> Clear
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" id="manualEntryBtn">
                                            <i class="fas fa-keyboard"></i> Manual Entry
                                        </button>
                                    </div>
                                    <span class="invalid-feedback"><?php echo $rfid_uid_err; ?></span>
                                </div>
                                <small class="text-muted">Present an RFID card to the reader to capture its UID.</small>
                            </div>
                            
                            <div class="scan-status" id="scanStatus"></div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="d-flex flex-column align-items-center justify-content-center h-100">
                                <div class="mb-2" id="rfidIconContainer">
                                    <i class="fas fa-id-card fa-3x text-primary"></i>
                                </div>
                                <button type="button" class="btn btn-primary" id="startScanBtn">
                                    <i class="fas fa-search mr-1"></i> Start Scanning
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Information -->
                <div class="form-section-divider"></div>
                <div class="form-section-title">
                    <i class="fas fa-wallet mr-2"></i> Account Information
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required-field">Initial Balance (DH)</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-coins"></i></span>
                                </div>
                                <input type="number" step="0.01" name="solde" class="form-control <?php echo (!empty($solde_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $solde; ?>" placeholder="Enter initial balance">
                                <span class="invalid-feedback"><?php echo $solde_err; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Order Number</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                </div>
                                <input type="text" name="num_commande" class="form-control <?php echo (!empty($num_commande_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $num_commande; ?>" placeholder="Leave blank to auto-generate">
                                <span class="invalid-feedback"><?php echo $num_commande_err; ?></span>
                            </div>
                            <small class="text-muted">If left blank, a random order number will be generated.</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Additional Notes</label>
                    <textarea name="bio" class="form-control" rows="3" placeholder="Enter any additional information about the client"><?php echo $bio; ?></textarea>
                </div>

                <!-- Security Information -->
                <div class="form-section-divider"></div>
                <div class="form-section-title">
                    <i class="fas fa-lock mr-2"></i> Security Information
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required-field">Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                </div>
                                <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Create a password">
                                <span class="invalid-feedback"><?php echo $password_err; ?></span>
                            </div>
                            <small class="text-muted">Password must be at least 8 characters long.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required-field">Confirm Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                </div>
                                <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Confirm password">
                                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-section-divider"></div>
                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i> Create Client
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <footer class="footer text-center">
            <div>
                <span>Current Date and Time (UTC): <?php echo $current_datetime; ?> | User: <?php echo $current_user; ?></span>
            </div>
            <div>
                <span>© <?php echo date('Y'); ?> Smart Grocery Admin Dashboard. All rights reserved.</span>
            </div>
        </footer>
    </div>

    <!-- Modal for manual RFID entry -->
    <div class="modal fade" id="manualEntryModal" tabindex="-1" role="dialog" aria-labelledby="manualEntryModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manualEntryModalLabel">Manual RFID Entry</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="manualRfidInput">RFID Card UID</label>
                        <input type="text" class="form-control" id="manualRfidInput" placeholder="Enter RFID UID (e.g. 04CBF8E9)">
                        <small class="form-text text-muted">Enter the RFID card UID in hexadecimal format.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveManualRfidBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            let scanning = false;
            let scanInterval;

            // Start scanning button
            $("#startScanBtn").click(function() {
                if (!scanning) {
                    startScanning();
                } else {
                    stopScanning();
                }
            });
            
            // Clear RFID UID button
            $("#clearRfidBtn").click(function() {
                $("#rfid_uid").val("");
                $("#scanStatus").text("").removeClass("text-success text-danger text-warning");
                stopScanning();
            });
            
            // Manual entry button
            $("#manualEntryBtn").click(function() {
                stopScanning();
                $("#manualEntryModal").modal("show");
            });
            
            // Save manual entry
            $("#saveManualRfidBtn").click(function() {
                const manualInput = $("#manualRfidInput").val().trim().toUpperCase();
                
                // Simple validation for hexadecimal format
                if (/^[0-9A-F]+$/.test(manualInput)) {
                    $("#rfid_uid").val(manualInput);
                    $("#scanStatus").text("Manually entered UID: " + manualInput).addClass("text-info").removeClass("text-success text-danger text-warning");
                    $("#manualEntryModal").modal("hide");
                } else {
                    alert("Please enter a valid hexadecimal UID format (e.g., 04CBF8E9)");
                }
            });

            // Function to start RFID scanning
            function startScanning() {
                scanning = true;
                $("#startScanBtn").html('<i class="fas fa-stop-circle mr-1"></i> Stop Scanning');
                $("#startScanBtn").removeClass("btn-primary").addClass("btn-danger");
                $("#rfidIconContainer i").addClass("scan-animation");
                $("#scanStatus").text("Scanning for RFID cards...").addClass("text-primary").removeClass("text-success text-danger text-warning");
                
                // Start polling for RFID scans
                scanInterval = setInterval(checkRfidScan, 2000);
            }
            
            // Function to stop RFID scanning
            function stopScanning() {
                scanning = false;
                $("#startScanBtn").html('<i class="fas fa-search mr-1"></i> Start Scanning');
                $("#startScanBtn").removeClass("btn-danger").addClass("btn-primary");
                $("#rfidIconContainer i").removeClass("scan-animation");
                clearInterval(scanInterval);
            }
            
            // Function to check for RFID scans
            function checkRfidScan() {
                $.ajax({
                    url: '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?check_rfid_scan=1',
                    type: 'GET',
                    dataType: 'json',
                    cache: false,
                    success: function(response) {
                        if (response.scan_detected) {
                            const rfidUid = response.rfid_uid;
                            
                            // Check if this RFID is already registered
                            if (response.already_registered) {
                                $("#scanStatus").html(`<strong class="text-danger">Card already registered to ${response.user}</strong>`).removeClass("text-success text-primary").addClass("text-danger");
                            } else {
                                $("#rfid_uid").val(rfidUid);
                                $("#scanStatus").html(`<strong class="text-success">Card detected: ${rfidUid}</strong>`).removeClass("text-danger text-primary").addClass("text-success");
                            }
                            
                            // Stop scanning automatically after successful detection
                            stopScanning();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error checking for RFID scans:", error);
                        $("#scanStatus").text("Error checking for scans").removeClass("text-success text-primary").addClass("text-danger");
                    }
                });
            }
            
            // Highlight current nav item
            $(".nav-link").each(function() {
                if ($(this).attr('href') === window.location.pathname.split('/').pop()) {
                    $(this).addClass('active');
                }
            });
        });
    </script>
</body>
</html>