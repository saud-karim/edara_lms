<?php
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Check if user can access permissions (needed for user management)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مسجل الدخول']);
    exit;
}

// Allow super_admin, users with users_edit, or users_add permissions to access permissions list
$canAccessPermissions = getUserRole() === 'super_admin' || 
                       hasPermission('users_edit') || 
                       hasPermission('users_add');

if (!$canAccessPermissions) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول للصلاحيات']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get all active permissions - expanded list for new features
    $stmt = $pdo->prepare("
        SELECT 
            permission_id,
            permission_name,
            permission_display_name,
            permission_description,
            permission_category
        FROM permissions 
        WHERE is_active = 1 
        ORDER BY permission_category, permission_display_name
    ");
    
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($permissions)) {
        echo json_encode([
            'success' => false,
            'message' => 'لا توجد صلاحيات متاحة. يرجى إضافة صلاحيات إلى قاعدة البيانات.',
            'data' => []
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'تم تحميل الصلاحيات بنجاح',
            'data' => $permissions,
            'total_count' => count($permissions)
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get permissions error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في تحميل الصلاحيات',
        'data' => []
    ]);
}
?> 
