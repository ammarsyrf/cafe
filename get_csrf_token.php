<?php
// File: get_csrf_token.php
// Endpoint untuk mendapatkan CSRF token baru

require_once 'app/config/db_connect.php';

// Set JSON header
header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Generate and return new CSRF token
$security = Security::getInstance();
$token = $security->generateCSRFToken();

echo json_encode([
    'success' => true,
    'token' => $token
]);
