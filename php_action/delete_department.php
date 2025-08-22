<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Enable basic error logging
ini_set('log_errors', 1);

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

// Check permission to delete departments
if (!isLoggedIn() || (!hasPermission('departments_delete') && getUserRole() !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بحذف الأقسام']);
    exit;
}

try {
    $departmentId = intval($_POST['department_id'] ?? 0);
    
    if (!$departmentId) {
        echo json_encode(['error' => 'معرف القسم غير صالح']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Get department details
    $stmt = $conn->prepare("
        SELECT department_id, department_name, department_description, department_email 
        FROM departments 
        WHERE department_id = ? AND is_active = 1
    ");
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        echo json_encode(['error' => 'القسم غير موجود']);
        exit;
    }
    
    // Check if department has any active users
    $usersStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND is_active = 1");
    $usersStmt->execute([$departmentId]);
    $usersCount = $usersStmt->fetchColumn();
    
    if ($usersCount > 0) {
        echo json_encode(['error' => "لا يمكن حذف هذا القسم لأنه يحتوي على $usersCount موظف نشط. قم بنقل الموظفين أولاً."]);
        exit;
    }
    
    // Check if department has any active licenses (personal and vehicle)
    $personalLicensesStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses WHERE department_id = ? AND is_active = 1");
    $personalLicensesStmt->execute([$departmentId]);
    $personalLicensesCount = $personalLicensesStmt->fetchColumn();
    
    $vehicleLicensesStmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_licenses WHERE department_id = ? AND is_active = 1");
    $vehicleLicensesStmt->execute([$departmentId]);
    $vehicleLicensesCount = $vehicleLicensesStmt->fetchColumn();
    
    $totalLicensesCount = $personalLicensesCount + $vehicleLicensesCount;
    
    if ($totalLicensesCount > 0) {
        echo json_encode(['error' => "لا يمكن حذف هذا القسم لأنه يحتوي على $totalLicensesCount ترخيص نشط ($personalLicensesCount شخصي، $vehicleLicensesCount مركبة). قم بنقل التراخيص أولاً."]);
        exit;
    }
    
    // Soft delete the department
    $deleteStmt = $conn->prepare("UPDATE departments SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE department_id = ?");
    $result = $deleteStmt->execute([$departmentId]);
    
    if ($result && $deleteStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف القسم بنجاح',
            'data' => [
                'department_id' => $departmentId,
                'department_name' => $department['department_name']
            ]
        ]);
    } else {
        echo json_encode(['error' => 'فشل في حذف القسم']);
    }
    
} catch (Exception $e) {
    error_log("Delete department error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم']);
}
?> 
