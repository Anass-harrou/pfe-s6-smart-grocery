<?php
// Just a sketch — you must implement session and token validation!
header("Content-Type: application/json");
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['qr_login_token'], $data['user_id'])) {
    // Validate token and link to user_id, e.g.:
    // 1. Lookup the token in DB or session
    // 2. If valid, log in user or set a flag to auto-login on web
    echo json_encode(['success'=>true, 'message'=>'Login successful']);
} else {
    echo json_encode(['success'=>false, 'message'=>'Missing parameters']);
}