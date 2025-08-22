<?php
require_once '../config/config.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=UTF-8');

// Only Super Admin can access this
if (!isLoggedIn() || getUserRole() !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

try {
    $conn = getDBConnection();
    $excludeUserId = intval($_POST['exclude_user_id'] ?? 0);
    
    // Get all head admins (exclude the user being moved to prevent circular reference)
    $query = "
        SELECT u.user_id, u.full_name, u.username, 
               d.department_name, p.project_name,
               COUNT(sub.user_id) as sub_admins_count
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN projects p ON u.project_id = p.project_id
        LEFT JOIN users sub ON sub.parent_admin_id = u.user_id AND sub.is_active = 1
        WHERE u.role = 'admin' AND u.parent_admin_id IS NULL AND u.is_active = 1
    ";
    
    if ($excludeUserId > 0) {
        $query .= " AND u.user_id != " . $excludeUserId;
    }
    
    $query .= " GROUP BY u.user_id, u.full_name, u.username, d.department_name, p.project_name";
    $query .= " ORDER BY u.full_name";
    
    $stmt = $conn->query($query);
    $admins = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $admins
    ]);
    
} catch (Exception $e) {
    error_log("Get head admins all error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل المديرين الرئيسيين'
    ]);
}
?> 