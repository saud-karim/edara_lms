<?php
require_once '../config/config.php';

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'غير مصرح']);
        exit;
    }
    
    // Get department ID from request
    $departmentId = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
    
    if (!$departmentId) {
        echo json_encode(['success' => false, 'error' => 'معرف القسم مطلوب']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Get head admins from the same department (parent_admin_id IS NULL)
    // These are eligible to be direct managers for other admins
    $query = "
        SELECT 
            u.user_id,
            u.full_name,
            u.username,
            u.email
        FROM users u
        WHERE u.department_id = ? 
        AND u.role = 'admin'
        AND u.parent_admin_id IS NULL
        AND u.is_active = 1
        ORDER BY u.full_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$departmentId]);
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $admins,
        'count' => count($admins)
    ]);
    
} catch (Exception $e) {
    error_log("Get parent admins error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'حدث خطأ في تحميل قائمة المشرفين'
    ]);
}
?> 