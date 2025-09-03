<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Check login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

// Apply permission logic
$hasVehicleView = hasPermission('vehicle_licenses_view');
$hasPersonalView = hasPermission('personal_licenses_view');
$hasGeneralView = hasPermission('licenses_view');

if ($hasVehicleView || $hasPersonalView) {
    $canViewVehicle = $hasVehicleView;
    $canViewPersonal = $hasPersonalView;
} else {
    $canViewVehicle = $hasGeneralView;
    $canViewPersonal = $hasGeneralView;
}

// Check if user has any viewing permissions
if (!$canViewVehicle && !$canViewPersonal) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بعرض التقارير']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $reportType = $_POST['report_type'] ?? '';
    $departmentFilter = $_POST['department_id'] ?? '';
    $projectFilter = $_POST['project_id'] ?? '';
    
    // Validate report type permissions
    if (strpos($reportType, 'personal_') === 0 && !$canViewPersonal) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'غير مصرح لك بعرض تقارير رخص القيادة الشخصية']);
        exit;
    }
    
    if (strpos($reportType, 'vehicle_') === 0 && !$canViewVehicle) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'غير مصرح لك بعرض تقارير رخص المركبات']);
        exit;
    }
    
    if ($reportType === 'all_summary' && (!$canViewPersonal && !$canViewVehicle)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'غير مصرح لك بعرض التقرير الشامل']);
        exit;
    }
    
    $userRole = getUserRole();
    $userDept = getUserDepartment();
    
    // Generate report based on type with filters
    switch ($reportType) {
        case 'personal_expired':
            $result = generatePersonalExpiredReport($conn, '', '', $departmentFilter, $projectFilter, $userRole, $userDept, $canViewPersonal, $hasGeneralView);
            break;
            
        case 'personal_expiring':
            $result = generatePersonalExpiringReport($conn, '', '', $departmentFilter, $projectFilter, $userRole, $userDept, $canViewPersonal, $hasGeneralView);
            break;
            
        case 'personal_active':
            $result = generatePersonalActiveReport($conn, '', '', $departmentFilter, $projectFilter, $userRole, $userDept, $canViewPersonal, $hasGeneralView);
            break;
            
        case 'vehicle_expired':
            $result = generateVehicleExpiredReport($conn, '', '', $departmentFilter, $projectFilter, $userRole, $userDept, $canViewVehicle, $hasGeneralView);
            break;
            
        case 'vehicle_expiring':
            $result = generateVehicleExpiringReport($conn, '', '', $departmentFilter, $projectFilter, $userRole, $userDept, $canViewVehicle, $hasGeneralView);
            break;
            
        case 'vehicle_active':
            $result = generateVehicleActiveReport($conn, '', '', $departmentFilter, $projectFilter, $userRole, $userDept, $canViewVehicle, $hasGeneralView);
            break;
            
        case 'all_summary':
            $result = generateAllSummaryReport($conn, '', '', $departmentFilter, $projectFilter, $userRole, $userDept, $canViewPersonal, $canViewVehicle);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'نوع التقرير غير صحيح']);
            exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result['data'],
        'summary' => $result['summary']
    ]);
    
} catch (Exception $e) {
    error_log("Report generation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء إنشاء التقرير: ' . $e->getMessage()
    ]);
}

// Report generation functions
function generatePersonalExpiredReport($conn, $startDate, $endDate, $departmentId, $projectId, $userRole, $userDept, $canViewPersonal, $hasGeneralView) {
    $whereConditions = ["pl.is_active = 1", "pl.expiration_date < CURDATE()"];
    $params = [];
    
    // Apply department filtering based on role and permissions
    if ($userRole === 'super_admin') {
        // Super admin sees everything
    } elseif ($hasGeneralView && !($canViewPersonal && !$hasGeneralView)) {
        // User has general permission - see everything
    } elseif ($userRole === 'admin' || ($canViewPersonal && !$hasGeneralView)) {
        // Admin or limited permission - restrict to user's department
        if ($userDept && $userDept > 0) {
            $whereConditions[] = "pl.department_id = ?";
            $params[] = $userDept;
        }
    }
    
    // Apply user filters
    if ($departmentId) {
        $whereConditions[] = "pl.department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($projectId) {
        $whereConditions[] = "pl.project_id = ?";
        $params[] = $projectId;
    }
    
    if ($startDate) {
        $whereConditions[] = "pl.issue_date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $whereConditions[] = "pl.issue_date <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    $query = "
        SELECT pl.license_id, pl.license_number, pl.full_name, 
               pl.department_name, pl.project_name,
               pl.issue_date, pl.expiration_date,
               DATEDIFF(CURDATE(), pl.expiration_date) as days_expired,
               'expired' as status
        FROM personal_license_overview pl
        $whereClause
        ORDER BY pl.expiration_date ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = [
        'total' => count($data),
        'expired' => count($data)
    ];
    
    return ['data' => $data, 'summary' => $summary];
}

function generatePersonalExpiringReport($conn, $startDate, $endDate, $departmentId, $projectId, $userRole, $userDept, $canViewPersonal, $hasGeneralView) {
    $whereConditions = ["pl.is_active = 1", "pl.expiration_date >= CURDATE()", "pl.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"];
    $params = [];
    
    // Apply department filtering
    if ($userRole === 'super_admin') {
        // Super admin sees everything
    } elseif ($hasGeneralView && !($canViewPersonal && !$hasGeneralView)) {
        // User has general permission - see everything
    } elseif ($userRole === 'admin' || ($canViewPersonal && !$hasGeneralView)) {
        // Admin or limited permission - restrict to user's department
        if ($userDept && $userDept > 0) {
            $whereConditions[] = "pl.department_id = ?";
            $params[] = $userDept;
        }
    }
    
    // Apply user filters
    if ($departmentId) {
        $whereConditions[] = "pl.department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($projectId) {
        $whereConditions[] = "pl.project_id = ?";
        $params[] = $projectId;
    }
    
    if ($startDate) {
        $whereConditions[] = "pl.issue_date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $whereConditions[] = "pl.issue_date <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    $query = "
        SELECT pl.license_id, pl.license_number, pl.full_name, 
               pl.department_name, pl.project_name,
               pl.issue_date, pl.expiration_date,
               DATEDIFF(pl.expiration_date, CURDATE()) as days_until_expiry,
               'expiring' as status
        FROM personal_license_overview pl
        $whereClause
        ORDER BY pl.expiration_date ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = [
        'total' => count($data),
        'expiring' => count($data)
    ];
    
    return ['data' => $data, 'summary' => $summary];
}

function generatePersonalActiveReport($conn, $startDate, $endDate, $departmentId, $projectId, $userRole, $userDept, $canViewPersonal, $hasGeneralView) {
    $whereConditions = ["pl.is_active = 1", "pl.expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)"];
    $params = [];
    
    // Apply department filtering
    if ($userRole === 'super_admin') {
        // Super admin sees everything
    } elseif ($hasGeneralView && !($canViewPersonal && !$hasGeneralView)) {
        // User has general permission - see everything
    } elseif ($userRole === 'admin' || ($canViewPersonal && !$hasGeneralView)) {
        // Admin or limited permission - restrict to user's department
        if ($userDept && $userDept > 0) {
            $whereConditions[] = "pl.department_id = ?";
            $params[] = $userDept;
        }
    }
    
    // Apply user filters
    if ($departmentId) {
        $whereConditions[] = "pl.department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($projectId) {
        $whereConditions[] = "pl.project_id = ?";
        $params[] = $projectId;
    }
    
    if ($startDate) {
        $whereConditions[] = "pl.issue_date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $whereConditions[] = "pl.issue_date <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    $query = "
        SELECT pl.license_id, pl.license_number, pl.full_name, 
               pl.department_name, pl.project_name,
               pl.issue_date, pl.expiration_date,
               DATEDIFF(pl.expiration_date, CURDATE()) as days_until_expiry,
               'active' as status
        FROM personal_license_overview pl
        $whereClause
        ORDER BY pl.expiration_date ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = [
        'total' => count($data),
        'active' => count($data)
    ];
    
    return ['data' => $data, 'summary' => $summary];
}

function generateVehicleExpiredReport($conn, $startDate, $endDate, $departmentId, $projectId, $userRole, $userDept, $canViewVehicle, $hasGeneralView) {
    $whereConditions = ["vl.is_active = 1", "vl.expiration_date < CURDATE()"];
    $params = [];
    
    // Apply department filtering
    if ($userRole === 'super_admin') {
        // Super admin sees everything
    } elseif ($hasGeneralView && !($canViewVehicle && !$hasGeneralView)) {
        // User has general permission - see everything
    } elseif ($userRole === 'admin' || ($canViewVehicle && !$hasGeneralView)) {
        // Admin or limited permission - restrict to user's department
        if ($userDept && $userDept > 0) {
            $whereConditions[] = "vl.department_id = ?";
            $params[] = $userDept;
        }
    }
    
    // Apply user filters
    if ($departmentId) {
        $whereConditions[] = "vl.department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($projectId) {
        $whereConditions[] = "vl.project_id = ?";
        $params[] = $projectId;
    }
    
    if ($startDate) {
        $whereConditions[] = "vl.issue_date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $whereConditions[] = "vl.issue_date <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    $query = "
        SELECT vl.license_id, vl.car_number, vl.vehicle_type, 
               vl.license_category, vl.inspection_year,
               vl.department_name, vl.project_name,
               vl.issue_date, vl.expiration_date,
               DATEDIFF(CURDATE(), vl.expiration_date) as days_expired,
               'expired' as status
        FROM vehicle_license_overview vl
        $whereClause
        ORDER BY vl.expiration_date ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = [
        'total' => count($data),
        'expired' => count($data)
    ];
    
    return ['data' => $data, 'summary' => $summary];
}

function generateVehicleExpiringReport($conn, $startDate, $endDate, $departmentId, $projectId, $userRole, $userDept, $canViewVehicle, $hasGeneralView) {
    $whereConditions = ["vl.is_active = 1", "vl.expiration_date >= CURDATE()", "vl.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"];
    $params = [];
    
    // Apply department filtering
    if ($userRole === 'super_admin') {
        // Super admin sees everything
    } elseif ($hasGeneralView && !($canViewVehicle && !$hasGeneralView)) {
        // User has general permission - see everything
    } elseif ($userRole === 'admin' || ($canViewVehicle && !$hasGeneralView)) {
        // Admin or limited permission - restrict to user's department
        if ($userDept && $userDept > 0) {
            $whereConditions[] = "vl.department_id = ?";
            $params[] = $userDept;
        }
    }
    
    // Apply user filters
    if ($departmentId) {
        $whereConditions[] = "vl.department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($projectId) {
        $whereConditions[] = "vl.project_id = ?";
        $params[] = $projectId;
    }
    
    if ($startDate) {
        $whereConditions[] = "vl.issue_date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $whereConditions[] = "vl.issue_date <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    $query = "
        SELECT vl.license_id, vl.car_number, vl.vehicle_type, 
               vl.license_category, vl.inspection_year,
               vl.department_name, vl.project_name,
               vl.issue_date, vl.expiration_date,
               DATEDIFF(vl.expiration_date, CURDATE()) as days_until_expiry,
               'expiring' as status
        FROM vehicle_license_overview vl
        $whereClause
        ORDER BY vl.expiration_date ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = [
        'total' => count($data),
        'expiring' => count($data)
    ];
    
    return ['data' => $data, 'summary' => $summary];
}

function generateVehicleActiveReport($conn, $startDate, $endDate, $departmentId, $projectId, $userRole, $userDept, $canViewVehicle, $hasGeneralView) {
    $whereConditions = ["vl.is_active = 1", "vl.expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)"];
    $params = [];
    
    // Apply department filtering
    if ($userRole === 'super_admin') {
        // Super admin sees everything
    } elseif ($hasGeneralView && !($canViewVehicle && !$hasGeneralView)) {
        // User has general permission - see everything
    } elseif ($userRole === 'admin' || ($canViewVehicle && !$hasGeneralView)) {
        // Admin or limited permission - restrict to user's department
        if ($userDept && $userDept > 0) {
            $whereConditions[] = "vl.department_id = ?";
            $params[] = $userDept;
        }
    }
    
    // Apply user filters
    if ($departmentId) {
        $whereConditions[] = "vl.department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($projectId) {
        $whereConditions[] = "vl.project_id = ?";
        $params[] = $projectId;
    }
    
    if ($startDate) {
        $whereConditions[] = "vl.issue_date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $whereConditions[] = "vl.issue_date <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    $query = "
        SELECT vl.license_id, vl.car_number, vl.vehicle_type, 
               vl.license_category, vl.inspection_year,
               vl.department_name, vl.project_name,
               vl.issue_date, vl.expiration_date,
               DATEDIFF(vl.expiration_date, CURDATE()) as days_until_expiry,
               'active' as status
        FROM vehicle_license_overview vl
        $whereClause
        ORDER BY vl.expiration_date ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = [
        'total' => count($data),
        'active' => count($data)
    ];
    
    return ['data' => $data, 'summary' => $summary];
}

function generateAllSummaryReport($conn, $startDate, $endDate, $departmentId, $projectId, $userRole, $userDept, $canViewPersonal, $canViewVehicle) {
    $whereConditions = ["is_active = 1"];
    $params = [];
    
    // Only apply role-based filtering if user is not super_admin AND has specific department filters
    if ($userRole !== 'super_admin' && $departmentId && $userDept && $userDept > 0) {
        $whereConditions[] = "department_id = ?";
        $params[] = $userDept;
    } elseif ($departmentId) {
        // Apply user-selected department filter
        $whereConditions[] = "department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($projectId) {
        $whereConditions[] = "project_id = ?";
        $params[] = $projectId;
    }
    
    if ($startDate) {
        $whereConditions[] = "issue_date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $whereConditions[] = "issue_date <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    $data = [];
    $personalStats = ['total' => 0, 'active' => 0, 'expiring' => 0, 'expired' => 0];
    $vehicleStats = ['total' => 0, 'active' => 0, 'expiring' => 0, 'expired' => 0];
    
    // Get summary statistics for personal licenses (only if user has permission)
    if ($canViewPersonal) {
        $personalQuery = "
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN expiration_date < CURDATE() THEN 1 END) as expired,
                COUNT(CASE WHEN expiration_date >= CURDATE() AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring,
                COUNT(CASE WHEN expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as active
            FROM personal_licenses 
            $whereClause
        ";
        
        $personalStmt = $conn->prepare($personalQuery);
        $personalStmt->execute($params);
        $personalStats = $personalStmt->fetch(PDO::FETCH_ASSOC);
        
        $data[] = [
            'license_type' => 'رخص القيادة الشخصية',
            'total' => $personalStats['total'],
            'active' => $personalStats['active'],
            'expiring' => $personalStats['expiring'],
            'expired' => $personalStats['expired']
        ];
    }
    
    // Get summary statistics for vehicle licenses (only if user has permission)
    if ($canViewVehicle) {
        $vehicleQuery = "
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN expiration_date < CURDATE() THEN 1 END) as expired,
                COUNT(CASE WHEN expiration_date >= CURDATE() AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring,
                COUNT(CASE WHEN expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as active
            FROM vehicle_licenses 
            $whereClause
        ";
        
        $vehicleStmt = $conn->prepare($vehicleQuery);
        $vehicleStmt->execute($params);
        $vehicleStats = $vehicleStmt->fetch(PDO::FETCH_ASSOC);
        
        $data[] = [
            'license_type' => 'رخص المركبات',
            'total' => $vehicleStats['total'],
            'active' => $vehicleStats['active'],
            'expiring' => $vehicleStats['expiring'],
            'expired' => $vehicleStats['expired']
        ];
    }
    
    $summary = [
        'total' => intval($personalStats['total']) + intval($vehicleStats['total']),
        'active' => intval($personalStats['active']) + intval($vehicleStats['active']),
        'expiring' => intval($personalStats['expiring']) + intval($vehicleStats['expiring']),
        'expired' => intval($personalStats['expired']) + intval($vehicleStats['expired'])
    ];
    
    return ['data' => $data, 'summary' => $summary];
}
?> 
