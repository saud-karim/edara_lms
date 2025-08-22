<?php
// Fixed delete_license.php with correct column names
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/delete_error.log');

header('Content-Type: application/json; charset=UTF-8');

try {
    // Fix path issue
    $configPath = realpath(dirname(__FILE__) . '/../config/config.php');
    $authPath = realpath(dirname(__FILE__) . '/auth.php');

    if (!$configPath || !$authPath) {
        throw new Exception('Configuration files not found');
    }

    require_once $configPath;
    require_once $authPath;

    // Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'غير مسجل الدخول']);
    exit;
}

    // Check delete permissions based on license type
    $canDelete = false;
    $licenseType = $_POST['license_type'] ?? 'personal';
    
    if ($licenseType === 'personal') {
        $canDelete = hasPermission('personal_licenses_delete') || hasPermission('licenses_delete');
    } elseif ($licenseType === 'vehicle') {
        $canDelete = hasPermission('vehicle_licenses_delete') || hasPermission('licenses_delete');
    }
    
    // Allow super_admin to delete everything
    if (getUserRole() === 'super_admin') {
        $canDelete = true;
    }
    
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية حذف هذا النوع من التراخيص']);
        exit;
    }

    // Get POST data
    $licenseId = intval($_POST['license_id'] ?? 0);
    $licenseType = $_POST['license_type'] ?? 'personal';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate input
    if (!$licenseId) {
        echo json_encode(['success' => false, 'error' => 'معرف الترخيص غير صالح']);
        exit;
    }

    if (!in_array($licenseType, ['personal', 'vehicle'])) {
        echo json_encode(['success' => false, 'error' => 'نوع الترخيص غير صالح']);
        exit;
    }

    // Validate CSRF token
    if (!validateCSRFToken($csrfToken)) {
        echo json_encode(['success' => false, 'error' => 'رمز الحماية غير صالح']);
        exit;
    }
    
    // Database operations
    $conn = getDBConnection();
    $userRole = getUserRole();
    $userDepartment = getUserDepartment();
    
    // Set table and fields based on license type
    if ($licenseType === 'personal') {
        $tableName = 'personal_licenses';
        $nameField = 'full_name';
        $numberField = 'license_number';
    } else {
        $tableName = 'vehicle_licenses';
        $nameField = 'car_number';
        $numberField = 'car_number';
    }
    
    // Get license details
    $stmt = $conn->prepare("
        SELECT l.*, d.department_name, p.project_name
        FROM $tableName l 
        LEFT JOIN departments d ON l.department_id = d.department_id 
        LEFT JOIN projects p ON l.project_id = p.project_id
        WHERE l.license_id = ? AND l.is_active = 1
    ");
    
    if (!$stmt->execute([$licenseId])) {
        throw new Exception('Database query failed');
    }
    
    $license = $stmt->fetch();
    
    if (!$license) {
        echo json_encode(['success' => false, 'error' => 'الترخيص غير موجود أو محذوف بالفعل']);
        exit;
    }
    
    // Check permissions using Admin Teams System
    if (!canModifyLicense($license['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'غير مصرح لك بحذف هذا الترخيص']);
        exit;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Soft delete the license
    $deleteStmt = $conn->prepare("UPDATE $tableName SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE license_id = ?");
    
    if (!$deleteStmt->execute([$licenseId])) {
        $conn->rollback();
        throw new Exception('فشل في حذف الترخيص من قاعدة البيانات');
    }
    
    // Log the deletion in license_logs
    $logStmt = $conn->prepare("
        INSERT INTO license_logs (license_id, license_type, action, user_id, old_values, new_values, created_at) 
        VALUES (?, ?, 'deleted', ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $logStmt->execute([
        $licenseId,
        $licenseType,
        getUserId(), 
        json_encode(['is_active' => 1]),
        json_encode(['is_active' => 0])
    ]);
    
    // Log in deletion_logs table (with correct column name: deleted_data)
    $deletionLogStmt = $conn->prepare("
        INSERT INTO deletion_logs (table_name, record_id, deleted_data, deleted_by, deleted_at) 
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $licenseNumber = $license[$numberField];
    $licenseName = $license[$nameField];
    
    $deletionLogStmt->execute([
        $tableName,
        $licenseId,
        json_encode([
            'license_type' => $licenseType,
            'number' => $licenseNumber,
            'name' => $licenseName,
            'department' => $license['department_name'] ?? 'غير محدد',
            'project' => $license['project_name'] ?? 'غير محدد'
        ]),
        getUserId()
    ]);
    
    // Commit transaction
    $conn->commit();
    
    // Success response
        echo json_encode([
            'success' => true,
        'message' => 'تم حذف الترخيص بنجاح. يمكن استعادته من التراخيص المحذوفة.',
        'data' => [
            'license_id' => $licenseId,
            'license_type' => $licenseType,
            'number' => $licenseNumber,
            'name' => $licenseName
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback if transaction was started
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    // Log error
    error_log("Delete license error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في الخادم: ' . $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}
?> 
