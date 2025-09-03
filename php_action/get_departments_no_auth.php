<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';

// نسخة مؤقتة بدون مصادقة للاختبار

error_log("🔧 get_departments_no_auth: بدء تحميل الأقسام (بدون مصادقة)");

try {
    $pdo = getDBConnection();
    error_log("✅ get_departments_no_auth: اتصال قاعدة البيانات نجح");
    
    // جلب جميع الأقسام النشطة
    $query = "
        SELECT 
            department_id,
            department_name,
            department_description,
            department_email,
            is_active,
            created_at
        FROM departments 
        WHERE is_active = 1
        ORDER BY department_name ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("✅ get_departments_no_auth: تم جلب " . count($departments) . " قسم");
    
    // تنسيق البيانات
    $formattedDepartments = [];
    foreach ($departments as $dept) {
        $formattedDepartments[] = [
            'department_id' => (int)$dept['department_id'],
            'department_name' => $dept['department_name'],
            'department_description' => $dept['department_description'] ?: '',
            'department_email' => $dept['department_email'] ?: '',
            'is_active' => (bool)$dept['is_active']
        ];
    }
    
    $response = [
        'success' => true,
        'data' => $formattedDepartments,
        'total' => count($formattedDepartments),
        'message' => 'تم تحميل الأقسام بنجاح (بدون مصادقة)',
        'auth_bypassed' => true
    ];
    
    error_log("✅ get_departments_no_auth: إرسال " . count($formattedDepartments) . " قسم");
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("❌ get_departments_no_auth error: " . $e->getMessage());
    error_log("❌ Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل الأقسام: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]
    ]);
}
?> 