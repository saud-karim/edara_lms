<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Ensure user is logged in and is super admin
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit;
}

// Check if user has any permission related to users
$canAccessUsers = getUserRole() === 'super_admin' || 
                  hasPermission('users_view') || 
                  hasPermission('users_add') || 
                  hasPermission('users_edit') || 
                  hasPermission('users_delete');

if (!$canAccessUsers) {
    http_response_code(403);
    echo json_encode(['error' => 'تم رفض الوصول - غير مصرح لك بعرض المستخدمين']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Base query
    $baseQuery = "
        SELECT u.user_id, u.username, u.email, u.full_name, u.role, 
               u.is_active, u.created_at, u.last_login,
               d.department_name, p.project_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN projects p ON u.project_id = p.project_id
        WHERE u.is_active = 1
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
        $searchTerm = '%' . $_GET['search'] . '%';
        $conditions[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Role filter
    if (isset($_GET['role']) && !empty($_GET['role'])) {
        $conditions[] = "u.role = ?";
        $params[] = $_GET['role'];
    }
    
    // Department filter (by name or ID)
    if (isset($_GET['department_id']) && !empty($_GET['department_id'])) {
        $departmentFilter = $_GET['department_id'];
        if (is_numeric($departmentFilter)) {
            $conditions[] = "u.department_id = ?";
            $params[] = $departmentFilter;
        } else {
            $conditions[] = "d.department_name = ?";
            $params[] = $departmentFilter;
        }
    }
    
    // Project filter (now on users table, not departments)
    if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
        $conditions[] = "u.project_id = ?";
        $params[] = $_GET['project_id'];
    }
    
    // Build final query
    if (!empty($conditions)) {
        $baseQuery .= " AND " . implode(" AND ", $conditions);
    }
    
    // Add ordering
    $orderBy = $_GET['order_by'] ?? 'u.created_at';
    $orderDir = $_GET['order_dir'] ?? 'DESC';
    $allowedOrderBy = ['u.username', 'u.full_name', 'u.email', 'u.role', 'u.created_at', 'u.last_login', 'd.department_name'];
    $allowedOrderDir = ['ASC', 'DESC'];
    
    if (in_array($orderBy, $allowedOrderBy) && in_array($orderDir, $allowedOrderDir)) {
        $baseQuery .= " ORDER BY $orderBy $orderDir";
    } else {
        $baseQuery .= " ORDER BY u.created_at DESC";
    }
    
    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? RECORDS_PER_PAGE)));
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $countQuery = str_replace(
        "SELECT u.user_id, u.username, u.email, u.full_name, u.role, 
               u.is_active, u.created_at, u.last_login,
               d.department_name, p.project_name",
        "SELECT COUNT(*)",
        $baseQuery
    );
    $countQuery = preg_replace('/ORDER BY.*$/', '', $countQuery);
    
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    // Get paginated results
    $baseQuery .= " LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($baseQuery);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Format the data
    foreach ($users as &$user) {
        // Format dates
        if ($user['created_at']) {
            $user['created_at_formatted'] = date('d/m/Y H:i', strtotime($user['created_at']));
        }
        if ($user['last_login']) {
            $user['last_login_formatted'] = date('d/m/Y H:i', strtotime($user['last_login']));
        } else {
            $user['last_login_formatted'] = 'لم يسجل دخول من قبل';
        }
        
        // Translate role names
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
        
        // Set department name fallback
        if (!$user['department_name']) {
            $user['department_name'] = 'غير محدد';
        }
    }
    
    // Format response
    $response = [
        'success' => true,
        'data' => $users,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalRecords / $limit),
            'total_records' => $totalRecords,
            'per_page' => $limit
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Get users error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم: ' . $e->getMessage()]);
}
?> 
