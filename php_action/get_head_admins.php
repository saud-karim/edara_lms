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
    $departmentId = intval($_POST['department_id'] ?? 0);
    
    if (!$departmentId) {
        echo json_encode(['success' => false, 'message' => 'معرف القسم مطلوب']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Get Head Admins from the same department
    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.username, d.department_name
        FROM users u
        JOIN departments d ON u.department_id = d.department_id
        WHERE u.role = 'admin' 
        AND u.parent_admin_id IS NULL
        AND u.department_id = ?
        AND u.is_active = 1
        ORDER BY u.full_name
    ");
    
    $stmt->execute([$departmentId]);
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $admins
    ]);
    
} catch (Exception $e) {
    error_log("Get head admins error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل المديرين الرئيسيين'
    ]);
}
?> 