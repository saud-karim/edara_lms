<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only super admins can edit departments
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
    
    $departmentId = intval($_POST['department_id'] ?? 0);
    
    if (!$departmentId) {
        echo json_encode([
            'success' => false,
            'message' => 'معرف القسم مطلوب'
        ]);
        exit;
    }
    
    // Check if department exists
    $checkStmt = $conn->prepare("SELECT * FROM departments WHERE department_id = ? AND is_active = 1");
    $checkStmt->execute([$departmentId]);
    $existingDepartment = $checkStmt->fetch();
    
    if (!$existingDepartment) {
        echo json_encode([
            'success' => false,
            'message' => 'القسم غير موجود'
        ]);
        exit;
    }
    
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
    
    // Validate department name
    if (strlen($departmentName) < 3) {
        echo json_encode([
            'success' => false,
            'message' => 'اسم القسم يجب أن يكون 3 أحرف على الأقل'
        ]);
        exit;
    }
    
    // Validate email if provided
    if (!empty($departmentEmail) && !filter_var($departmentEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'البريد الإلكتروني غير صحيح'
        ]);
        exit;
    }
    
    // Check if department name already exists (excluding current department)
    $duplicateStmt = $conn->prepare("
        SELECT department_id
        FROM departments 
        WHERE department_name = ? AND department_id != ? AND is_active = 1
    ");
    $duplicateStmt->execute([$departmentName, $departmentId]);
    
    if ($duplicateStmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'اسم القسم موجود بالفعل'
        ]);
        exit;
    }
    
    // Check if department has users or licenses and name is changing
    if ($departmentName !== $existingDepartment['department_name']) {
        $usersStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND is_active = 1");
        $usersStmt->execute([$departmentId]);
        $usersCount = $usersStmt->fetchColumn();
        
        $licensesStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses WHERE department_id = ? AND is_active = 1");
        $licensesStmt->execute([$departmentId]);
        $personalLicensesCount = $licensesStmt->fetchColumn();
        
        $vehicleLicensesStmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_licenses WHERE department_id = ? AND is_active = 1");
        $vehicleLicensesStmt->execute([$departmentId]);
        $vehicleLicensesCount = $vehicleLicensesStmt->fetchColumn();
        
        if ($usersCount > 0 || $personalLicensesCount > 0 || $vehicleLicensesCount > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'لا يمكن تغيير اسم القسم لأنه يحتوي على مستخدمين أو تراخيص. يجب نقل البيانات أولاً.'
            ]);
            exit;
        }
    }
    
    // Update department
    $updateStmt = $conn->prepare("
        UPDATE departments 
        SET department_name = ?, department_description = ?, department_email = ?, updated_at = CURRENT_TIMESTAMP
        WHERE department_id = ?
    ");
    
    if ($updateStmt->execute([$departmentName, $departmentDescription, $departmentEmail, $departmentId])) {
        // Get updated department
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
            'message' => 'تم تحديث القسم بنجاح',
            'department' => $department
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل في تحديث القسم'
        ]);
    }

} catch (Exception $e) {
    error_log("Edit department error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء تحديث القسم'
    ]);
}
?> 
