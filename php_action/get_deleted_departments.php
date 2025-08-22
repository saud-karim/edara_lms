<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Only super admin can view deleted departments
if (!isLoggedIn() || getUserRole() !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Base query for deleted departments - Fixed table references and column names
    $baseQuery = "
        SELECT d.*,
               COUNT(DISTINCT u.user_id) as users_count,
               (COUNT(DISTINCT pl.license_id) + COUNT(DISTINCT vl.license_id)) as licenses_count
        FROM departments d
        LEFT JOIN users u ON d.department_id = u.department_id AND u.is_active = 0
        LEFT JOIN personal_licenses pl ON d.department_id = pl.department_id AND pl.is_active = 0
        LEFT JOIN vehicle_licenses vl ON d.department_id = vl.department_id AND vl.is_active = 0
        WHERE d.is_active = 0
    ";
    
    $params = [];
    $conditions = [];
    
    // Search functionality - Fixed column name to department_description
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $conditions[] = "(d.department_name LIKE ? OR d.department_description LIKE ?)";
        $params = array_merge($params, [$search, $search]);
    }
    
    // Project filter
    if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
        $conditions[] = "p.project_id = ?";
        $params[] = $_GET['project_id'];
    }
    
    // Add conditions to query
    if (!empty($conditions)) {
        $baseQuery .= " AND " . implode(" AND ", $conditions);
    }
    
    // Add GROUP BY
    $baseQuery .= " GROUP BY d.department_id";
    
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
    $baseQuery .= " ORDER BY d.updated_at DESC LIMIT $recordsPerPage OFFSET $offset";
    
    // Execute main query
    $stmt = $conn->prepare($baseQuery);
    $stmt->execute($params);
    $departments = $stmt->fetchAll();
    
    // Add formatted dates to departments
    foreach ($departments as &$dept) {
        $dept['created_at_formatted'] = formatDateTime($dept['created_at']);
        $dept['updated_at_formatted'] = formatDateTime($dept['updated_at']);
        
        // Ensure counts are integers
        $dept['users_count'] = intval($dept['users_count']);
        $dept['licenses_count'] = intval($dept['licenses_count']);
        
        // Handle null description - Fixed column name
        if (!$dept['department_description']) {
            $dept['department_description'] = 'غير محدد';
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $departments,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'records_per_page' => $recordsPerPage
        ]
    ]);

} catch (Exception $e) {
    error_log("Get deleted departments error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم: ' . $e->getMessage()]);
}
?> 
