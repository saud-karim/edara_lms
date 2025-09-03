<?php
session_start();
require_once '../config/config.php';
require_once 'auth.php';

// Require admin or super admin access
if (!hasPermission('user_management') && getUserRole() !== 'super_admin') {
    echo json_encode(['success' => false, 'error' => 'غير مصرح لك بالوصول']);
    exit;
}

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Get current user ID to exclude from list
    $currentUserId = isset($_GET['current_user_id']) ? intval($_GET['current_user_id']) : 0;
    
    // Get current user's department for filtering head admins
    $currentUserDept = null;
    if ($currentUserId > 0) {
        $deptStmt = $conn->prepare("SELECT department_id FROM users WHERE user_id = ? AND is_active = 1");
        $deptStmt->execute([$currentUserId]);
        $user = $deptStmt->fetch();
        $currentUserDept = $user ? $user['department_id'] : null;
    }
    
    // If user has no department, no direct managers available
    if (!$currentUserDept) {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit;
    }
    
    // Get users who can be direct managers:
    // Only Head admins (parent_admin_id IS NULL) from SAME department
    // Super admins are excluded as they already have full access
    $sql = "
        SELECT 
            user_id,
            full_name,
            username,
            role,
            department_id,
            'مشرف رئيسي' as role_text
        FROM users 
        WHERE is_active = 1 
        AND user_id != ?
        AND role = 'admin' 
        AND parent_admin_id IS NULL 
        AND department_id = ?
        ORDER BY full_name
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$currentUserId, $currentUserDept]);
    $managers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $managers
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في تحميل المديرين: ' . $e->getMessage()
    ]);
}
?> 