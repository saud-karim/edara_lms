<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';

try {
    $pdo = getDBConnection();
    
    // جلب جميع الصلاحيات النشطة مع الأعمدة الصحيحة
    $query = "
        SELECT 
            permission_id,
            permission_name,
            permission_display_name,
            permission_description,
            permission_category,
            is_active
        FROM permissions 
        WHERE is_active = 1
        ORDER BY permission_category, permission_display_name ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تنسيق البيانات بشكل صحيح
    $formattedPermissions = [];
    foreach ($permissions as $perm) {
        $formattedPermissions[] = [
            'permission_id' => (int)$perm['permission_id'],
            'permission_name' => $perm['permission_name'],
            'permission_display_name' => $perm['permission_display_name'] ?: $perm['permission_name'],
            'permission_description' => $perm['permission_description'] ?: '',
            'permission_category' => $perm['permission_category'] ?: 'عام',
            'is_active' => (bool)$perm['is_active']
        ];
    }
    
    $response = [
        'success' => true,
        'data' => $formattedPermissions,
        'total_count' => count($formattedPermissions),
        'message' => 'تم تحميل الصلاحيات بنجاح'
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل الصلاحيات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 