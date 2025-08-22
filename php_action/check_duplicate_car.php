<?php
require_once '../config/config.php';
require_once 'auth.php';

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $conn = getDBConnection();
    $carNumber = trim($_POST['car_number'] ?? '');
    
    if (empty($carNumber)) {
        echo json_encode(['exists' => false]);
        exit;
    }
    
    // Check if car number exists (without spaces, letters first + numbers)
    $stmt = $conn->prepare("
        SELECT license_id FROM vehicle_licenses 
        WHERE REPLACE(car_number, ' ', '') = ? AND is_active = 1
        LIMIT 1
    ");
    
    $stmt->execute([$carNumber]);
    $result = $stmt->fetch();
    
    echo json_encode(['exists' => (bool)$result]);
    
} catch (Exception $e) {
    error_log("Check duplicate car error: " . $e->getMessage());
    echo json_encode(['exists' => false, 'error' => 'Database error']);
} 