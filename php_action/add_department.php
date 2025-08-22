<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only super admins can add departments
if (!isLoggedIn() || getUserRole() !== 'super_admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح بالوصول'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode([
            'success' => false,
            'message' => 'رمز الحماية غير صحيح'
        ]);
        exit;
    }
    
    // Required fields
    $requiredFields = [
        'department_name' => 'اسم القسم'
    ];
    
    // Check required fields
    foreach ($requiredFields as $field => $fieldName) {
        if (empty($_POST[$field])) {
            echo json_encode([
                'success' => false,
                'message' => "الحقل {$fieldName} مطلوب"
            ]);
            exit;
        }
    }
    
    $departmentName = trim($_POST['department_name']);
    $departmentDescription = trim($_POST['department_description'] ?? '');
    $departmentEmail = trim($_POST['department_email'] ?? '');
    
    // Validate email if provided
    if (!empty($departmentEmail) && !filter_var($departmentEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'بريد القسم الإلكتروني غير صحيح'
        ]);
        exit;
    }
    
    // Validate department name
    if (strlen($departmentName) < 3) {
        echo json_encode([
            'success' => false,
            'message' => 'اسم القسم يجب أن يكون 3 أحرف على الأقل'
        ]);
        exit;
    }
    
    // Check if department name already exists
    $checkStmt = $conn->prepare("
        SELECT department_id 
        FROM departments 
        WHERE department_name = ? AND is_active = 1
    ");
    $checkStmt->execute([$departmentName]);
    
    if ($checkStmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'اسم القسم موجود بالفعل'
        ]);
        exit;
    }
    
    // Insert new department
    $insertStmt = $conn->prepare("
        INSERT INTO departments (department_name, department_description, department_email, is_active, created_at)
        VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)
    ");
    
    if ($insertStmt->execute([$departmentName, $departmentDescription, $departmentEmail])) {
        $departmentId = $conn->lastInsertId();
        
        // Get the inserted department with details
        $selectStmt = $conn->prepare("
            SELECT d.department_id, d.department_name, d.department_description, d.department_email,
                   d.is_active, d.created_at, d.updated_at
            FROM departments d
            WHERE d.department_id = ?
        ");
        $selectStmt->execute([$departmentId]);
        $department = $selectStmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم إضافة القسم بنجاح',
            'department' => $department
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل في إضافة القسم'
        ]);
    }

} catch (Exception $e) {
    error_log("Add department error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء إضافة القسم'
    ]);
}
?> 
