<?php
/**
 * RFID Listener Endpoint
 * 
 * AJAX endpoint for checking RFID scans on login page
 * 
 * Current Date and Time (UTC): 2025-06-21 12:14:21
 * Author: Anass-harrou
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once "config.php";

// Include RFID authentication class
require_once "RFIDAuthentication.php";

// Set content type to JSON
header('Content-Type: application/json');

// Create RFID authentication handler
$rfidAuth = new RFIDAuthentication($link);

// Check for direct POST of RFID data from Python
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_direct']) && $_POST['rfid_direct'] === 'true') {
    // Get RFID UID
    $rfidUid = isset($_POST['rfid_uid']) ? trim($_POST['rfid_uid']) : '';
    $timestamp = isset($_POST['timestamp']) ? trim($_POST['timestamp']) : date('Y-m-d H:i:s');
    
    if (!empty($rfidUid)) {
        // Store in session
        $_SESSION['global_rfid_scan'] = [
            'rfid_uid' => $rfidUid,
            'timestamp' => $timestamp,
            'processed' => false
        ];
        
        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'RFID data received'
        ]);
        exit;
    }
    
    // Return error
    echo json_encode([
        'success' => false,
        'message' => 'No RFID UID provided'
    ]);
    exit;
}

// Handle AJAX request for RFID scan check
if (isset($_GET['check_scan'])) {
    // Check for new RFID scan
    $rfidUid = $rfidAuth->checkForScan();
    
    if ($rfidUid) {
        // Try to authenticate user
        $user = $rfidAuth->authenticateUser($rfidUid);
        
        if ($user) {
            // Authentication successful
            $_SESSION['user'] = $user;
            
            // Return success with redirect info
            echo json_encode([
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'name' => $user['name'],
                    'email' => $user['email']
                ],
                'redirect' => $user['role'] === 'admin' ? 'admin/dashboard.php' : 'client/dashboard.php'
            ]);
        } else {
            // Authentication failed
            echo json_encode([
                'success' => true,
                'authenticated' => false,
                'message' => 'RFID card not recognized',
                'rfid_uid' => $rfidUid
            ]);
        }
    } else {
        // No new scan
        echo json_encode([
            'success' => false
        ]);
    }
    exit;
}

// Return error for invalid requests
echo json_encode([
    'success' => false,
    'message' => 'Invalid request'
]);
?>