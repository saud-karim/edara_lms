<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'رمز الحماية غير صالح']);
    exit;
}

// Check permission to restore departments
if (!isLoggedIn() || (!hasPermission('departments_delete') && getUserRole() !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك باستعادة الأقسام']);
    exit;
}

try {
    $departmentId = intval($_POST['department_id'] ?? 0);
    
    if (!$departmentId) {
        echo json_encode(['error' => 'معرف القسم غير صالح']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Get deleted department details
    $stmt = $conn->prepare("
        SELECT department_id, department_name, department_description, department_email 
        FROM departments 
        WHERE department_id = ? AND is_active = 0
    ");
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        echo json_encode(['error' => 'القسم المحذوف غير موجود']);
        exit;
    }
    
    // Check if department name conflicts with existing active departments
    $checkStmt = $conn->prepare("
        SELECT department_id 
        FROM departments 
        WHERE department_name = ? AND is_active = 1 AND department_id != ?
    ");
    $checkStmt->execute([$department['department_name'], $departmentId]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['error' => 'يوجد قسم نشط بنفس الاسم. لا يمكن تكرار أسماء الأقسام.']);
        exit;
    }
    
    // Restore the department (set is_active = 1)
    $restoreStmt = $conn->prepare("UPDATE departments SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE department_id = ?");
    $result = $restoreStmt->execute([$departmentId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم استعادة القسم بنجاح',
            'data' => [
                'department_id' => $departmentId,
                'department_name' => $department['department_name']
            ]
        ]);
    } else {
        echo json_encode(['error' => 'فشل في استعادة القسم']);
    }
    
} catch (Exception $e) {
    error_log("Restore department error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم']);
}
?> 
