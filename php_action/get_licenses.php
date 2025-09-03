<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Ensure user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit;
}

// Improved permission logic - specific permissions take priority
$hasVehicleView = hasPermission('vehicle_licenses_view');
$hasPersonalView = hasPermission('personal_licenses_view');
$hasGeneralView = hasPermission('licenses_view');

// Determine final personal access permission
if ($hasVehicleView || $hasPersonalView) {
    // User has specific permissions - be strict
    $canViewPersonal = $hasPersonalView;
} else {
    // User only has general permissions - apply to both
    $canViewPersonal = $hasGeneralView;
}

if (!$canViewPersonal) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بعرض رخص القيادة الشخصية']);
    exit;
}

// Determine access scope for data filtering
$hasFullAccess = $hasGeneralView && !($hasVehicleView || $hasPersonalView);
$hasLimitedAccess = $hasPersonalView && !$hasGeneralView;

try {
    $conn = getDBConnection();
    $userRole = getUserRole();
    $userDepartment = getUserDepartment();
    
    // Base query - استخدام personal_licenses table مباشرة مع joins للبيانات المطلوبة
    $baseQuery = "
        SELECT pl.*, 
               d.department_name,
               p.project_name,
               CASE 
                   WHEN pl.expiration_date < CURDATE() THEN 'expired'
                   WHEN pl.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring'
                   ELSE 'active'
               END as status,
               DATEDIFF(pl.expiration_date, CURDATE()) as days_until_expiration,
               u.full_name as added_by_name,
               u.username as added_by_username
        FROM personal_licenses pl
        LEFT JOIN departments d ON pl.department_id = d.department_id
        LEFT JOIN projects p ON pl.project_id = p.project_id
        LEFT JOIN users u ON pl.user_id = u.user_id
        WHERE pl.is_active = 1
    ";
    
    $params = [];
    $conditions = [];
    
    // Apply access restrictions using Admin Teams System
    try {
        $licenseFilter = getLicenseFilter('pl'); // Specify table alias to avoid ambiguous column
        error_log("Admin Teams Filter for user " . getUserId() . ": " . $licenseFilter);
        
        if ($licenseFilter !== "1=1") {
            // Add user_id filter for team-based access
            $conditions[] = "(" . $licenseFilter . ")";
        }
    } catch (Exception $e) {
        error_log("Error getting license filter: " . $e->getMessage());
        // Fallback to user ID only
        $conditions[] = "pl.user_id = " . intval(getUserId());
    }
    
    // Legacy permission system (still supported for backward compatibility)
    if ($userRole !== 'super_admin' && $licenseFilter === "1=1") {
        if ($hasFullAccess) {
            // User has general licenses_view - can see all departments
            // No additional restrictions
        } elseif ($hasLimitedAccess) {
            // User only has personal_licenses_view - restricted to their department
            $userDepartmentName = getUserDepartmentName();
            if (!$userDepartmentName) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'يجب تحديد قسم للمستخدم للوصول لرخص القيادة الشخصية'
                ]);
                exit;
            }
            $conditions[] = "d.department_name = ?";
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
                $conditions[] = "d.department_name = ?";
                $params[] = $userDepartmentName;
            }
        }
    }
    
    // Additional filters from request
    if (isset($_GET['department_id']) && !empty($_GET['department_id'])) {
        $departmentFilter = $_GET['department_id'];
        if (is_numeric($departmentFilter)) {
            // Check if user can access this department by ID
            if (canAccessDepartment($departmentFilter)) {
                $conditions[] = "pl.department_id = ?";
                $params[] = $departmentFilter;
            }
        } else {
            // Filter by department name (no access check needed since dropdown is already filtered)
            $conditions[] = "d.department_name = ?";
            $params[] = $departmentFilter;
        }
    }
    
    // Project filter
    if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
        $projectFilter = $_GET['project_id'];
        if (is_numeric($projectFilter)) {
            // Filter by project ID
            $conditions[] = "pl.project_id = ?";
            $params[] = $projectFilter;
        } else {
            // Filter by project name (no access check needed since dropdown is already filtered)
            $conditions[] = "p.project_name = ?";
            $params[] = $projectFilter;
        }
    }
    
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        switch ($_GET['status']) {
            case 'expired':
                $conditions[] = "pl.expiration_date < CURDATE()";
                break;
            case 'expiring':
                $conditions[] = "pl.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND pl.expiration_date >= CURDATE()";
                break;
            case 'active':
                $conditions[] = "pl.expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $searchTerm = '%' . $_GET['search'] . '%';
        $conditions[] = "(pl.full_name LIKE ? OR pl.license_number LIKE ? OR pl.project_name LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Build final query
    if (!empty($conditions)) {
        $baseQuery .= " AND " . implode(" AND ", $conditions);
    }
    
    // Add ordering
    $orderBy = $_GET['order_by'] ?? 'pl.created_at';
    $orderDir = $_GET['order_dir'] ?? 'DESC';
    $allowedOrderBy = ['pl.license_number', 'pl.full_name', 'pl.expiration_date', 'pl.created_at', 'd.department_name'];
    $allowedOrderDir = ['ASC', 'DESC'];
    
    if (in_array($orderBy, $allowedOrderBy) && in_array($orderDir, $allowedOrderDir)) {
        $baseQuery .= " ORDER BY $orderBy $orderDir";
    } else {
        $baseQuery .= " ORDER BY pl.created_at DESC";
    }
    
    // Pagination - simplified like vehicle licenses
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $recordsPerPage = RECORDS_PER_PAGE;
    $offset = ($page - 1) * $recordsPerPage;
    
    // Get total count - use same structure as main query
    $countQuery = "
        SELECT COUNT(*) as total
        FROM personal_licenses pl
        LEFT JOIN departments d ON pl.department_id = d.department_id
        LEFT JOIN projects p ON pl.project_id = p.project_id
        LEFT JOIN users u ON pl.user_id = u.user_id
        WHERE pl.is_active = 1
    ";
    
    if (!empty($conditions)) {
        $countQuery .= " AND " . implode(" AND ", $conditions);
    }
    
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get paginated results - using string concatenation like vehicle licenses
    $dataQuery = $baseQuery . " LIMIT " . intval($recordsPerPage) . " OFFSET " . intval($offset);
    $stmt = $conn->prepare($dataQuery);
    $stmt->execute($params);
    $licenses = $stmt->fetchAll();
    
    // Add permission flags for each license based on Admin Teams System
    foreach ($licenses as &$license) {
        $license['can_edit'] = canModifyLicense($license['user_id']);
        $license['can_delete'] = canModifyLicense($license['user_id']);
    }
    
    // Calculate pagination
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Format response
    $response = [
        'success' => true,
        'data' => $licenses,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $recordsPerPage
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Get licenses error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم']);
}
?> 
