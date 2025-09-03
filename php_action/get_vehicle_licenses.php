<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Set JSON header
header('Content-Type: application/json; charset=UTF-8');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
    ]);
    exit;
}

// Improved permission logic - specific permissions take priority
$hasVehicleView = hasPermission('vehicle_licenses_view');
$hasPersonalView = hasPermission('personal_licenses_view');
$hasGeneralView = hasPermission('licenses_view');

// Determine final vehicle access permission
if ($hasVehicleView || $hasPersonalView) {
    // User has specific permissions - be strict
    $canViewVehicle = $hasVehicleView;
} else {
    // User only has general permissions - apply to both
    $canViewVehicle = $hasGeneralView;
}

if (!$canViewVehicle) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية لعرض رخص المركبات'
    ]);
    exit;
}

// Determine access scope for data filtering
$hasFullAccess = $hasGeneralView && !($hasVehicleView || $hasPersonalView);
$hasLimitedAccess = $hasVehicleView && !$hasGeneralView;

try {
    $conn = getDBConnection();
    
    // Get parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $search = $_GET['search'] ?? '';
    $departmentId = $_GET['department_id'] ?? '';
    $projectId = $_GET['project_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $recordsPerPage = RECORDS_PER_PAGE;
    $offset = ($page - 1) * $recordsPerPage;
    
    // Base query using vehicle_licenses table directly with necessary joins
    $baseQuery = "
        FROM vehicle_licenses vl
        LEFT JOIN departments d ON vl.department_id = d.department_id
        LEFT JOIN projects p ON vl.project_id = p.project_id
        LEFT JOIN users u ON vl.user_id = u.user_id
        WHERE vl.is_active = 1
    ";
    
    $whereConditions = [];
    $params = [];
    
    // Apply access restrictions using Admin Teams System
    $userRole = getUserRole();
    $userDept = getUserDepartment();
    
    // Apply access restrictions using Admin Teams System
    try {
        $licenseFilter = getLicenseFilter('vl'); // Specify table alias to avoid ambiguous column
        error_log("Admin Teams Filter for vehicles - user " . getUserId() . ": " . $licenseFilter);
        
        if ($licenseFilter !== "1=1") {
            // Add user_id filter for team-based access
            $whereConditions[] = "(" . $licenseFilter . ")";
        }
    } catch (Exception $e) {
        error_log("Error getting license filter for vehicles: " . $e->getMessage());
        // Fallback to user ID only
        $whereConditions[] = "vl.user_id = " . intval(getUserId());
    }
    
    // Legacy permission system (still supported for backward compatibility)
    if ($userRole !== 'super_admin' && $licenseFilter === "1=1") {
        if ($hasFullAccess) {
            // User has general licenses_view - can see all departments
            // No additional restrictions
        } elseif ($hasLimitedAccess) {
            // User only has vehicle_licenses_view - restricted to their department
            $userDepartmentName = getUserDepartmentName();
            if (!$userDepartmentName) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'يجب تحديد قسم للمستخدم للوصول لرخص المركبات'
                ]);
                exit;
            }
            $whereConditions[] = "d.department_name = ?";
            $params[] = $userDepartmentName;
        } else {
            // Fallback: apply role-based restrictions
            if ($userRole === 'admin' || $userRole === 'user') {
                $userDepartmentName = getUserDepartmentName();
                if (!$userDepartmentName) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'يجب تحديد قسم للمستخدم للوصول للتراخيص'
                    ]);
                    exit;
                }
                $whereConditions[] = "d.department_name = ?";
                $params[] = $userDepartmentName;
            }
        }
    }
    
    // Search filter - enhanced for car numbers with spaces
    if (!empty($search)) {
        // Clean search term by removing extra spaces and normalizing
        $cleanedSearch = trim(preg_replace('/\s+/', ' ', $search));
        
        // Create search conditions for car number with different formats
        $searchConditions = [
            "vl.car_number LIKE ?",                                    // Direct match with spaces
            "REPLACE(vl.car_number, ' ', '') LIKE ?",                 // Match without spaces
            "vl.car_number LIKE ?",                                   // Partial match
            "vl.vehicle_type LIKE ?",                                 // Vehicle type
            "p.project_name LIKE ?",                                 // Project name from join
            "d.department_name LIKE ?"                               // Department name from join
        ];
        
        $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
        
        // Prepare search parameters
        $searchWithSpaces = '%' . $cleanedSearch . '%';              // Search with spaces
        $searchWithoutSpaces = '%' . str_replace(' ', '', $cleanedSearch) . '%'; // Search without spaces
        $generalSearch = '%' . $cleanedSearch . '%';                 // General search
        
        $params = array_merge($params, [
            $searchWithSpaces,      // Direct match with spaces
            $searchWithoutSpaces,   // Match without spaces  
            $generalSearch,         // Partial match
            $generalSearch,         // Vehicle type
            $generalSearch,         // Project name
            $generalSearch          // Department name
        ]);
    }
    
    // Department filter
    if (!empty($departmentId)) {
        if (is_numeric($departmentId)) {
            $whereConditions[] = "vl.department_id = ?";
            $params[] = $departmentId;
        } else {
            // Filter by department name
            $whereConditions[] = "d.department_name = ?";
            $params[] = $departmentId;
        }
    }
    
    // Project filter
    if (!empty($projectId)) {
        if (is_numeric($projectId)) {
            $whereConditions[] = "vl.project_id = ?";
            $params[] = $projectId;
        } else {
            // Filter by project name
            $whereConditions[] = "p.project_name = ?";
            $params[] = $projectId;
        }
    }
    
    // Status filter
    if (!empty($status)) {
        $currentDate = date('Y-m-d');
        if ($status === 'active') {
            $whereConditions[] = "vl.expiration_date > DATE_ADD(?, INTERVAL 30 DAY)";
            $params[] = $currentDate;
        } elseif ($status === 'expiring') {
            $whereConditions[] = "vl.expiration_date BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY)";
            $params = array_merge($params, [$currentDate, $currentDate]);
        } elseif ($status === 'expired') {
            $whereConditions[] = "vl.expiration_date < ?";
            $params[] = $currentDate;
        }
    }
    
    // Build WHERE clause
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = ' AND ' . implode(' AND ', $whereConditions);
    }
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery . $whereClause;
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get vehicle licenses - using string concatenation for LIMIT/OFFSET to avoid parameter binding issues
    $dataQuery = "
        SELECT vl.*, 
               d.department_name,
               p.project_name,
               u.full_name as added_by_name,
               u.username as added_by_username,
               CASE 
                   WHEN vl.expiration_date < CURDATE() THEN 'expired'
                   WHEN vl.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring'
                   ELSE 'active'
               END as status,
               DATEDIFF(vl.expiration_date, CURDATE()) as days_until_expiration
        " . $baseQuery . $whereClause . "
        ORDER BY vl.created_at DESC
        LIMIT " . intval($recordsPerPage) . " OFFSET " . intval($offset);
    
    $dataStmt = $conn->prepare($dataQuery);
    $dataStmt->execute($params);
    $licenses = $dataStmt->fetchAll();
    
    // Add permission flags for each license based on Admin Teams System
    foreach ($licenses as &$license) {
        $license['can_edit'] = canModifyLicense($license['user_id']);
        $license['can_delete'] = canModifyLicense($license['user_id']);
    }
    
    // Calculate pagination
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    echo json_encode([
        'success' => true,
        'data' => $licenses,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'records_per_page' => $recordsPerPage
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get vehicle licenses error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في استرجاع رخص المركبات'
    ]);
}
?> 
