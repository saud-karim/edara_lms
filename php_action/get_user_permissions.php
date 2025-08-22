<?php
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Check if user has permission to view user permissions (needed for user management)
$canViewUserPermissions = getUserRole() === 'super_admin' || 
                          hasPermission('users_view') || 
                          hasPermission('users_edit') || 
                          hasPermission('users_add');

if (!$canViewUserPermissions) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بعرض صلاحيات المستخدمين']);
    exit;
}

header('Content-Type: application/json');

try {
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('معرف المستخدم مطلوب');
    }
    
    $pdo = getDBConnection();
    
    // Verify user exists
    $userStmt = $pdo->prepare("
        SELECT user_id, username, full_name, role 
        FROM users 
        WHERE user_id = ? AND is_active = 1
    ");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('المستخدم غير موجود');
    }
    
    // Get only permissions that are actually used in the system (7 permissions)
    $usedPermissions = [
        'departments_delete',
        'licenses_add',
        'licenses_delete',
        'licenses_view',
        'users_add',
        'users_delete',
        'users_view'
    ];
    
    $placeholders = str_repeat('?,', count($usedPermissions) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT 
            p.permission_id,
            p.permission_name,
            p.permission_display_name,
            p.permission_description,
            p.permission_category,
            CASE WHEN up.user_id IS NOT NULL THEN 1 ELSE 0 END as is_granted,
            up.granted_at,
            up.granted_by,
            granter.full_name as granted_by_name,
            up.notes
        FROM permissions p
        LEFT JOIN user_permissions up ON p.permission_id = up.permission_id 
            AND up.user_id = ? AND up.is_active = 1
        LEFT JOIN users granter ON up.granted_by = granter.user_id
        WHERE p.is_active = 1 
            AND p.permission_name IN ($placeholders)
        ORDER BY p.permission_category, p.permission_display_name
    ");
    
    // Execute with user_id and used permissions list
    $executeParams = array_merge([$user_id], $usedPermissions);
    $stmt->execute($executeParams);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group permissions by category
    $groupedPermissions = [];
    $categoryNames = [
        'licenses' => 'التراخيص',
        'users' => 'المستخدمين',
        'departments' => 'الأقسام',
        'reports' => 'التقارير',
        'settings' => 'الإعدادات',
        'system' => 'النظام'
    ];
    
    $grantedCount = 0;
    foreach ($permissions as $permission) {
        $category = $permission['permission_category'];
        if (!isset($groupedPermissions[$category])) {
            $groupedPermissions[$category] = [
                'category_name' => $categoryNames[$category] ?? $category,
                'permissions' => []
            ];
        }
        $groupedPermissions[$category]['permissions'][] = $permission;
        
        if ($permission['is_granted']) {
            $grantedCount++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'permissions' => $groupedPermissions,
        'stats' => [
            'total_permissions' => count($permissions),
            'granted_permissions' => $grantedCount,
            'percentage' => count($permissions) > 0 ? round(($grantedCount / count($permissions)) * 100, 1) : 0
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في جلب صلاحيات المستخدم: ' . $e->getMessage()
    ]);
}
?> 
