<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير مسموحة'
    ]);
    exit;
}

// Check restore permissions based on license type
$licenseType = $_POST['license_type'] ?? 'personal';
$canRestore = false;

if ($licenseType === 'personal') {
    $canRestore = hasPermission('personal_licenses_delete') || hasPermission('licenses_delete');
} elseif ($licenseType === 'vehicle') {
    $canRestore = hasPermission('vehicle_licenses_delete') || hasPermission('licenses_delete');
}

// Allow super_admin to restore everything
if (getUserRole() === 'super_admin') {
    $canRestore = true;
}

if (!$canRestore) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية استعادة هذا النوع من التراخيص'
    ]);
    exit;
}

try {
    $licenseId = intval($_POST['license_id'] ?? 0);
    $licenseType = $_POST['license_type'] ?? 'personal';
    
    if (!$licenseId) {
        echo json_encode([
            'success' => false,
            'message' => 'معرف الترخيص غير صالح'
        ]);
        exit;
    }
    
    // Validate license type
    if (!in_array($licenseType, ['personal', 'vehicle'])) {
        echo json_encode([
            'success' => false,
            'message' => 'نوع الترخيص غير صالح'
        ]);
        exit;
    }
    
    $conn = getDBConnection();
    $userRole = getUserRole();
    $userDepartment = getUserDepartment();
    
    // Build query based on license type
    if ($licenseType === 'personal') {
        $tableName = 'personal_licenses';
        $numberField = 'license_number';
        $nameField = 'full_name';
    } else {
        $tableName = 'vehicle_licenses';
        $numberField = 'car_number';
        $nameField = 'car_number'; // Use car_number as identifier for vehicles
    }
    
    // Get deleted license details to check permissions
    $stmt = $conn->prepare("
        SELECT l.*, d.department_name, p.project_name
        FROM $tableName l 
        JOIN departments d ON l.department_id = d.department_id 
        JOIN projects p ON l.project_id = p.project_id
        WHERE l.license_id = ? AND l.is_active = 0
    ");
    $stmt->execute([$licenseId]);
    $license = $stmt->fetch();
    
    if (!$license) {
        echo json_encode([
            'success' => false,
            'message' => 'الترخيص المحذوف غير موجود'
        ]);
        exit;
    }
    
    // Check department permissions - only restrict if user doesn't have global permissions
    $userRole = getUserRole();
    $userDepartment = getUserDepartment();
    $hasGlobalPermissions = hasPermission('licenses_delete') || $userRole === 'super_admin';
    
    if (!$hasGlobalPermissions && $userDepartment && $license['department_id'] != $userDepartment) {
        echo json_encode([
            'success' => false,
            'message' => 'لا يمكنك استعادة تراخيص من قسم آخر'
        ]);
        exit;
    }
    
    // Check if license number/car number is still unique among active licenses
    $checkStmt = $conn->prepare("
        SELECT license_id 
        FROM $tableName 
        WHERE $numberField = ? AND is_active = 1 AND license_id != ?
    ");
    $checkStmt->execute([$license[$numberField], $licenseId]);
    
    if ($checkStmt->fetch()) {
        $numberLabel = $licenseType === 'personal' ? 'رقم الترخيص' : 'رقم المركبة';
        echo json_encode([
            'success' => false,
            'message' => "$numberLabel موجود بالفعل في التراخيص النشطة. يرجى تعديل الرقم أولاً."
        ]);
        exit;
    }
    
    // Restore the license (set is_active = 1)
    $restoreStmt = $conn->prepare("UPDATE $tableName SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE license_id = ?");
    $result = $restoreStmt->execute([$licenseId]);
    
    if ($result) {
        // Log the restoration
        $logStmt = $conn->prepare("
            INSERT INTO license_logs (license_id, action, user_id, old_values, new_values, created_at) 
            VALUES (?, 'restored', ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $licenseName = $licenseType === 'personal' ? $license['full_name'] : $license['car_number'];
        $licenseNumber = $license[$numberField];
        $logDetails = "تم استعادة " . ($licenseType === 'personal' ? 'رخصة القيادة' : 'رخصة المركبة') . " رقم $licenseNumber";
        if ($licenseType === 'personal') {
            $logDetails .= " للموظف $licenseName";
        }
        
        $logStmt->execute([
            $licenseId, 
            getUserId(), 
            json_encode(['is_active' => 0]),
            json_encode(['is_active' => 1]),
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم استعادة الترخيص بنجاح',
            'data' => [
                'license_id' => $licenseId,
                'license_type' => $licenseType,
                'number' => $licenseNumber,
                'name' => $licenseName
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل في استعادة الترخيص'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Restore license error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()
    ]);
}
?> 
