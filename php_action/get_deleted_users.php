<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Check if user has permission to view deleted users
if (!isLoggedIn() || (!hasPermission('users_delete') && getUserRole() !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بعرض المستخدمين المحذوفين']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Base query for deleted users
    $baseQuery = "
        SELECT u.*, d.department_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE u.is_active = 0
    ";
    
    $params = [];
    $conditions = [];
    
    // Department filtering for non-super_admin users
    $userRole = getUserRole();
    $userDepartment = getUserDepartment();
    
    // If not super_admin, restrict to same department only
    if ($userRole !== 'super_admin' && $userDepartment) {
        $conditions[] = "u.department_id = ?";
        $params[] = $userDepartment;
    }
    
    // Search functionality
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $conditions[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR d.department_name LIKE ?)";
        $params = array_merge($params, [$search, $search, $search, $search]);
    }
    
    // Role filter
    if (isset($_GET['role']) && !empty($_GET['role'])) {
        $conditions[] = "u.role = ?";
        $params[] = $_GET['role'];
    }
    
    // Department filter
    if (isset($_GET['department_id']) && !empty($_GET['department_id'])) {
        $conditions[] = "u.department_id = ?";
        $params[] = $_GET['department_id'];
    }
    
    // Add conditions to query
    if (!empty($conditions)) {
        $baseQuery .= " AND " . implode(" AND ", $conditions);
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) FROM (" . $baseQuery . ") as total_query";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $recordsPerPage = 10;
    $offset = ($page - 1) * $recordsPerPage;
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Add ordering and pagination
    $baseQuery .= " ORDER BY u.updated_at DESC LIMIT $recordsPerPage OFFSET $offset";
    
    // Execute main query
    $stmt = $conn->prepare($baseQuery);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Add role translations to users
    foreach ($users as &$user) {
        switch ($user['role']) {
            case 'super_admin':
                $user['role_arabic'] = 'مشرف عام';
                break;
            case 'admin':
                $user['role_arabic'] = 'مشرف';
                break;
            case 'regular':
                $user['role_arabic'] = 'مستخدم عادي';
                break;
            default:
                $user['role_arabic'] = $user['role'];
        }
        
        // Add formatted dates
        $user['created_at_formatted'] = formatDateTime($user['created_at']);
        $user['updated_at_formatted'] = formatDateTime($user['updated_at']);
        
        // Handle null department
        if (!$user['department_name']) {
            $user['department_name'] = 'غير محدد';
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $users,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'records_per_page' => $recordsPerPage
        ]
    ]);

} catch (Exception $e) {
    error_log("Get deleted users error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم']);
}
?> 
