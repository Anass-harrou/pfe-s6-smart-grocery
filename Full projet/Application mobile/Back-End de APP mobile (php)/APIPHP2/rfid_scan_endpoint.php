<?php
/**
 * RFID Scan Endpoint
 * 
 * AJAX endpoint for checking RFID scans in admin pages
 * 
 * @author Anass-harrou
 * @date 2025-06-21
 */

// Include database connection
if (!isset($link)) {
    require_once "../config.php";
}

// Include RFID Helper class
require_once 'RFIDHelper.php';

// Handle AJAX request for RFID scans
if (isset($_GET['check_rfid_scan'])) {
    header('Content-Type: application/json');
    
    // Get client ID from request if available
    $clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;
    
    // Initialize RFID Helper
    $rfidHelper = new RFIDHelper('/GHMARIIII/rfid_scans/', $link);
    
    // Check for new scan
    $scanResult = $rfidHelper->checkForScan(30, $clientId);
    
    // Send response
    echo json_encode($scanResult);
    exit;
}
?>