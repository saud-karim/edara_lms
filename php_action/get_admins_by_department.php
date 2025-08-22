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
    
    $departmentId = intval($_GET['department_id'] ?? 0);
    
    // Modified: Show all active admins, not department-specific
    // This allows any admin to be responsible for licenses in any department
    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.role, d.department_name
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE u.is_active = 1 AND u.role IN ('super_admin', 'admin')
        ORDER BY u.role DESC, u.full_name ASC
    ");
    
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $admins
    ]);

} catch (Exception $e) {
    error_log("Get admins by department error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'خطأ في تحميل الإداريين']);
}
?> 