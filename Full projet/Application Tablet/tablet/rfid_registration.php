<?php
session_start();
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion_stock');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Connect to the database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user already has an RFID card registered
$stmt = $conn->prepare("SELECT rfid_uid FROM client WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$has_rfid = false;
$current_rfid = '';

if ($row = $result->fetch_assoc()) {
    if (!empty($row['rfid_uid'])) {
        $has_rfid = true;
        $current_rfid = $row['rfid_uid'];
    }
}
$stmt->close();

// Check for new RFID scan
$scan_file = '/GHMARIIII/rfid_scans/latest_scan.txt';
$new_scan_detected = false;
$new_rfid = '';

if (file_exists($scan_file) && filesize($scan_file) > 0) {
    $scan_data = file_get_contents($scan_file);
    $scan_info = json_decode($scan_data, true);
    
    if ($scan_info && isset($scan_info['rfid_uid']) && isset($scan_info['timestamp'])) {
        // Check if scan is recent (within last 30 seconds)
        $scan_time = strtotime($scan_info['timestamp']);
        $current_time = time();
        
        if (($current_time - $scan_time) <= 30) {
            $new_scan_detected = true;
            $new_rfid = $scan_info['rfid_uid'];
        }
    }
}

// Process registration if form submitted
$registration_status = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_rfid'])) {
    $rfid_to_register = $_POST['rfid_uid'];
    
    // Check if RFID is already registered to another user
    $check_stmt = $conn->prepare("SELECT id FROM client WHERE rfid_uid = ? AND id != ?");
    $check_stmt->bind_param("si", $rfid_to_register, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $registration_status = '<div style="color: red; font-weight: bold;">Cette carte RFID est déjà enregistrée pour un autre utilisateur.</div>';
    } else {
        // Update user with new RFID UID
        $update_stmt = $conn->prepare("UPDATE client SET rfid_uid = ? WHERE id = ?");
        $update_stmt->bind_param("si", $rfid_to_register, $user_id);
        
        if ($update_stmt->execute()) {
            $registration_status = '<div style="color: green; font-weight: bold;">Carte RFID enregistrée avec succès!</div>';
            $has_rfid = true;
            $current_rfid = $rfid_to_register;
            
            // Move the scan file to prevent reuse
            if (file_exists($scan_file)) {
                rename($scan_file, '/GHMARIIII/rfid_scans/processed_' . time() . '.txt');
            }
        } else {
            $registration_status = '<div style="color: red; font-weight: bold;">Erreur lors de l\'enregistrement de la carte RFID.</div>';
        }
        $update_stmt->close();
    }
    $check_stmt->close();
}

// Process removal if requested
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_rfid'])) {
    $update_stmt = $conn->prepare("UPDATE client SET rfid_uid = NULL WHERE id = ?");
    $update_stmt->bind_param("i", $user_id);
    
    if ($update_stmt->execute()) {
        $registration_status = '<div style="color: green; font-weight: bold;">Carte RFID supprimée avec succès!</div>';
        $has_rfid = false;
        $current_rfid = '';
    } else {
        $registration_status = '<div style="color: red; font-weight: bold;">Erreur lors de la suppression de la carte RFID.</div>';
    }
    $update_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Carte RFID - Smart Grocery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .rfid-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .rfid-icon {
            font-size: 48px;
            color: #6200EE;
            margin-bottom: 20px;
        }
        .status-box {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            background-color: #f0f0f0;
        }
        .btn-primary {
            background-color: #6200EE;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .scan-status {
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="images/logo.png" alt="Logo de l'entreprise" class="logo">
        <div class="navbar">
            <a href="index.php">Accueil</a>
            <a href="carte.php">Carte</a>
            <a href="index.php?logout=true">Se déconnecter</a>
        </div>
    </div>

    <div class="content">
        <h1>Gestion de Votre Carte RFID</h1>
        
        <div class="rfid-container">
            <div style="text-align: center;">
                <i class="fas fa-id-card rfid-icon"></i>
                <h2>Bonjour, <?php echo htmlspecialchars($user_name); ?></h2>
            </div>
            
            <?php echo $registration_status; ?>
            
            <div class="status-box">
                <h3>Statut de votre carte RFID:</h3>
                <?php if ($has_rfid): ?>
                    <p style="color: green;"><i class="fas fa-check-circle"></i> Vous avez une carte RFID enregistrée</p>
                    <p><strong>Identifiant:</strong> <?php echo htmlspecialchars($current_rfid); ?></p>
                    
                    <form method="post" action="">
                        <button type="submit" name="remove_rfid" class="btn-danger">
                            <i class="fas fa-trash"></i> Supprimer cette carte
                        </button>
                    </form>
                <?php else: ?>
                    <p style="color: orange;"><i class="fas fa-exclamation-triangle"></i> Vous n'avez pas encore de carte RFID enregistrée</p>
                    
                    <div id="scanInstructions">
                        <h3>Enregistrer une nouvelle carte:</h3>
                        <p>1. Approchez votre carte RFID du lecteur</p>
                        <p>2. Attendez que la carte soit détectée</p>
                        <p>3. Cliquez sur "Enregistrer cette carte"</p>
                        
                        <div class="scan-status" id="scanStatus">En attente de carte...</div>
                        
                        <?php if ($new_scan_detected): ?>
                            <div style="color: green; margin-top: 15px;">
                                <i class="fas fa-check-circle"></i> Nouvelle carte détectée!
                                
                                <form method="post" action="">
                                    <input type="hidden" name="rfid_uid" value="<?php echo htmlspecialchars($new_rfid); ?>">
                                    <p>UID: <?php echo htmlspecialchars($new_rfid); ?></p>
                                    <button type="submit" name="register_rfid" class="btn-primary">
                                        <i class="fas fa-save"></i> Enregistrer cette carte
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 30px; text-align: center;">
                <a href="index.php" class="btn-primary">Retour à l'Accueil</a>
            </div>
        </div>
    </div>
    
    <script>
    // Check for new scans every 3 seconds
    <?php if (!$has_rfid): ?>
    function checkForNewScans() {
        fetch('check_rfid_scan.php')
            .then(response => response.json())
            .then(data => {
                if (data.scan_detected) {
                    // Reload the page to show the new scan
                    window.location.reload();
                }
            })
            .catch(error => console.error('Error checking for RFID scans:', error));
    }
    
    // Start checking for scans
    setInterval(checkForNewScans, 3000);
    <?php endif; ?>
    </script>
</body>
</html>