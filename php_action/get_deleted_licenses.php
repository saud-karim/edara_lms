<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مسجل الدخول']);
    exit;
}

// Check if user has permission to view deleted licenses
$canAccessDeleted = getUserRole() === 'super_admin' || 
                   hasPermission('licenses_delete') || 
                   hasPermission('personal_licenses_delete') || 
                   hasPermission('vehicle_licenses_delete');

if (!$canAccessDeleted) {
    http_response_code(403);
    echo json_encode(['error' => 'ليس لديك صلاحية لعرض التراخيص المحذوفة']);
    exit;
}

try {
    $conn = getDBConnection();
    $userRole = getUserRole();
    $userDepartment = getUserDepartment();
    
    // Get license type filter (default to 'all')
    $licenseType = $_GET['type'] ?? 'all';
    $allLicenses = [];
    
    // Build conditions and params
    $params = [];
    $conditions = [];
    
    // Role-based filtering - only apply department restriction if user doesn't have global permissions
    $hasGlobalPermissions = hasPermission('licenses_delete') || $userRole === 'super_admin';
    $shouldFilterByDepartment = !$hasGlobalPermissions && $userDepartment;
    
    if ($shouldFilterByDepartment) {
        $departmentCondition = "l.department_id = ?";
        $departmentParam = $userDepartment;
    }
    
    // Search functionality
    $searchCondition = "";
    $searchParams = [];
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
    }
    
    // Additional filters from request
    if (isset($_GET['department_id']) && !empty($_GET['department_id'])) {
        if ($hasGlobalPermissions || $userDepartment == $_GET['department_id']) {
            $filterDepartmentCondition = "l.department_id = ?";
            $filterDepartmentParam = $_GET['department_id'];
        }
    }
    
    // Get Personal Licenses if requested
    if ($licenseType === 'all' || $licenseType === 'personal') {
        $personalQuery = "
            SELECT l.*, p.project_name, d.department_name,
                   'personal' as license_type,
                   CASE 
                       WHEN l.expiration_date < CURDATE() THEN 'منتهي الصلاحية'
                       WHEN l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'ينتهي قريباً'
                       ELSE 'نشط'
                   END as previous_status,
                   DATEDIFF(l.expiration_date, CURDATE()) as days_until_expiration
            FROM personal_licenses l
            JOIN projects p ON l.project_id = p.project_id
            JOIN departments d ON l.department_id = d.department_id
            WHERE l.is_active = 0
        ";
        
        $personalParams = [];
        $personalConditions = [];
        
        // Add role-based filtering
        if ($shouldFilterByDepartment) {
            $personalConditions[] = $departmentCondition;
            $personalParams[] = $userDepartment;
        }
        
        // Search filter
        if (isset($search)) {
            $personalConditions[] = "(l.license_number LIKE ? OR l.full_name LIKE ?)";
            $personalParams[] = $search;
            $personalParams[] = $search;
        }
        
        // Department filter
        if (isset($filterDepartmentCondition)) {
            $personalConditions[] = $filterDepartmentCondition;
            $personalParams[] = $filterDepartmentParam;
        }
        
        // Add conditions to query
        if (!empty($personalConditions)) {
            $personalQuery .= " AND " . implode(" AND ", $personalConditions);
        }
        
        $personalQuery .= " ORDER BY l.created_at DESC";
        
        $personalStmt = $conn->prepare($personalQuery);
        $personalStmt->execute($personalParams);
        $personalLicenses = $personalStmt->fetchAll();
        
        $allLicenses = array_merge($allLicenses, $personalLicenses);
    }
    
    // Get Vehicle Licenses if requested
    if ($licenseType === 'all' || $licenseType === 'vehicle') {
        $vehicleQuery = "
            SELECT l.*, p.project_name, d.department_name,
                   'vehicle' as license_type,
                   CASE 
                       WHEN l.expiration_date < CURDATE() THEN 'منتهي الصلاحية'
                       WHEN l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'ينتهي قريباً'
                       ELSE 'نشط'
                   END as previous_status,
                   DATEDIFF(l.expiration_date, CURDATE()) as days_until_expiration
            FROM vehicle_licenses l
            JOIN projects p ON l.project_id = p.project_id
            JOIN departments d ON l.department_id = d.department_id
            WHERE l.is_active = 0
        ";
        
        $vehicleParams = [];
        $vehicleConditions = [];
        
        // Add role-based filtering
        if ($shouldFilterByDepartment) {
            $vehicleConditions[] = $departmentCondition;
            $vehicleParams[] = $userDepartment;
        }
        
        // Search filter
        if (isset($search)) {
            $vehicleConditions[] = "(l.car_number LIKE ? OR l.vehicle_type LIKE ?)";
            $vehicleParams[] = $search;
            $vehicleParams[] = $search;
        }
        
        // Department filter
        if (isset($filterDepartmentCondition)) {
            $vehicleConditions[] = $filterDepartmentCondition;
            $vehicleParams[] = $filterDepartmentParam;
        }
        
        // Add conditions to query
        if (!empty($vehicleConditions)) {
            $vehicleQuery .= " AND " . implode(" AND ", $vehicleConditions);
        }
        
        $vehicleQuery .= " ORDER BY l.created_at DESC";
        
        $vehicleStmt = $conn->prepare($vehicleQuery);
        $vehicleStmt->execute($vehicleParams);
        $vehicleLicenses = $vehicleStmt->fetchAll();
        
        $allLicenses = array_merge($allLicenses, $vehicleLicenses);
    }
    
    // Sort all licenses by updated_at DESC
    usort($allLicenses, function($a, $b) {
        return strtotime($b['updated_at']) - strtotime($a['updated_at']);
    });
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $recordsPerPage = 10;
    $totalRecords = count($allLicenses);
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $offset = ($page - 1) * $recordsPerPage;
    
    // Get paginated results
    $paginatedLicenses = array_slice($allLicenses, $offset, $recordsPerPage);
    
    echo json_encode([
        'success' => true,
        'data' => $paginatedLicenses,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'records_per_page' => $recordsPerPage
        ],
        'stats' => [
            'personal_count' => count(array_filter($allLicenses, function($l) { return $l['license_type'] === 'personal'; })),
            'vehicle_count' => count(array_filter($allLicenses, function($l) { return $l['license_type'] === 'vehicle'; }))
        ]
    ]);

} catch (Exception $e) {
    error_log("Get deleted licenses error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم']);
}
?> 
