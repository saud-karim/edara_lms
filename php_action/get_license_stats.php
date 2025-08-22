<?php
header('Content-Type: application/json; charset=UTF-8');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Ensure user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح بالوصول']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get active personal licenses count
    $personalStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM personal_licenses 
        WHERE is_active = 1
    ");
    $personalStmt->execute();
    $activePersonal = $personalStmt->fetch()['count'] ?? 0;
    
    // Get active vehicle licenses count
    $vehicleStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM vehicle_licenses 
        WHERE is_active = 1
    ");
    $vehicleStmt->execute();
    $activeVehicle = $vehicleStmt->fetch()['count'] ?? 0;
    
    // Get expiring licenses count (both types, within 30 days)
    $expiringPersonalStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM personal_licenses 
        WHERE is_active = 1 
        AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND expiration_date >= CURDATE()
    ");
    $expiringPersonalStmt->execute();
    $expiringPersonal = $expiringPersonalStmt->fetch()['count'] ?? 0;
    
    $expiringVehicleStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM vehicle_licenses 
        WHERE is_active = 1 
        AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND expiration_date >= CURDATE()
    ");
    $expiringVehicleStmt->execute();
    $expiringVehicle = $expiringVehicleStmt->fetch()['count'] ?? 0;
    
    $totalExpiring = $expiringPersonal + $expiringVehicle;
    
    // Get expired licenses count (both types)
    $expiredPersonalStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM personal_licenses 
        WHERE is_active = 1 
        AND expiration_date < CURDATE()
    ");
    $expiredPersonalStmt->execute();
    $expiredPersonal = $expiredPersonalStmt->fetch()['count'] ?? 0;
    
    $expiredVehicleStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM vehicle_licenses 
        WHERE is_active = 1 
        AND expiration_date < CURDATE()
    ");
    $expiredVehicleStmt->execute();
    $expiredVehicle = $expiredVehicleStmt->fetch()['count'] ?? 0;
    
    $totalExpired = $expiredPersonal + $expiredVehicle;
    
    echo json_encode([
        'success' => true,
        'active_personal' => $activePersonal,
        'active_vehicle' => $activeVehicle,
        'expiring' => $totalExpiring,
        'expiring_personal' => $expiringPersonal,
        'expiring_vehicle' => $expiringVehicle,
        'expired' => $totalExpired,
        'expired_personal' => $expiredPersonal,
        'expired_vehicle' => $expiredVehicle,
        'total_active' => $activePersonal + $activeVehicle,
        'needs_attention' => $totalExpiring + $totalExpired
    ]);
    
} catch (Exception $e) {
    error_log("Get license stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في تحميل الإحصائيات']);
}
?> 