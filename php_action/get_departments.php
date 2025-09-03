<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only authenticated users can access
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح بالوصول'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get filter parameters
    $departmentId = $_GET['department_id'] ?? '';
    $projectId = $_GET['project_id'] ?? '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10; // Items per page
    $offset = ($page - 1) * $limit;
    
    // Build base query - Show department-project combinations that have actual data (users OR licenses)
    // Also include departments without projects
    $baseQuery = "
        SELECT d.department_id, d.department_name, d.department_description, d.department_email, d.is_active, d.created_at, d.updated_at,
               COALESCE(p.project_id, 0) as project_id, 
               COALESCE(p.project_name, 'غير محدد') as project_name,
               COUNT(DISTINCT u.user_id) as users_count,
               (
                   SELECT COUNT(DISTINCT pl.license_id) 
                   FROM personal_licenses pl 
                   WHERE pl.department_id = d.department_id 
                   AND (p.project_id IS NULL OR pl.project_id = p.project_id)
                   AND pl.is_active = 1
               ) + (
                   SELECT COUNT(DISTINCT vl.license_id) 
                   FROM vehicle_licenses vl 
                   WHERE vl.department_id = d.department_id 
                   AND (p.project_id IS NULL OR vl.project_id = p.project_id)
                   AND vl.is_active = 1
               ) as licenses_count
        FROM departments d
        LEFT JOIN (
            -- Get all department-project combinations that have users
            SELECT DISTINCT u.department_id, u.project_id
            FROM users u
            WHERE u.is_active = 1 
            AND u.department_id IS NOT NULL 
            AND u.project_id IS NOT NULL
            
            UNION
            
            -- Get all department-project combinations that have personal licenses
            SELECT DISTINCT pl.department_id, pl.project_id
            FROM personal_licenses pl
            WHERE pl.is_active = 1 
            AND pl.department_id IS NOT NULL 
            AND pl.project_id IS NOT NULL
            
            UNION
            
            -- Get all department-project combinations that have vehicle licenses
            SELECT DISTINCT vl.department_id, vl.project_id
            FROM vehicle_licenses vl
            WHERE vl.is_active = 1 
            AND vl.department_id IS NOT NULL 
            AND vl.project_id IS NOT NULL
        ) combinations ON d.department_id = combinations.department_id
        LEFT JOIN projects p ON combinations.project_id = p.project_id AND p.is_active = 1
        LEFT JOIN users u ON d.department_id = u.department_id AND (p.project_id IS NULL OR u.project_id = p.project_id) AND u.is_active = 1
        WHERE d.is_active = 1
    ";
    
    $whereConditions = [];
    $params = [];
    
    // Department filter (specific department selection by name or ID)
    if (!empty($departmentId)) {
        // Check if it's a numeric ID or department name
        if (is_numeric($departmentId)) {
            $whereConditions[] = "d.department_id = ?";
            $params[] = intval($departmentId);
        } else {
            // Filter by department name
            $whereConditions[] = "d.department_name = ?";
            $params[] = $departmentId;
        }
    }
    
    // Project filter - Filter by selected project
    if (!empty($projectId)) {
        if ($projectId === 'undefined' || $projectId === 'null') {
            $whereConditions[] = "p.project_id IS NULL";
        } else {
            $whereConditions[] = "p.project_id = ?";
            $params[] = intval($projectId);
        }
    }
    
    // Apply user role restrictions
    $userRole = getUserRole();
    $currentUserId = getCurrentUserId();
    
    if ($userRole === 'super_admin') {
        // Super admin sees all departments
    } elseif ($userRole === 'admin') {
        // Admin sees only assigned departments via user_departments table
        // Check if user_departments table exists and has data for this user
        $checkUserDepts = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() AND table_name = 'user_departments'
        ");
        $checkUserDepts->execute();
        $tableExists = $checkUserDepts->fetch()['count'] > 0;
        
        if ($tableExists) {
            $checkUserHasDepts = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM user_departments 
                WHERE user_id = ?
            ");
            $checkUserHasDepts->execute([$currentUserId]);
            $userHasDepts = $checkUserHasDepts->fetch()['count'] > 0;
            
            if ($userHasDepts) {
                // Filter by user's assigned departments
                $whereConditions[] = "d.department_id IN (
                    SELECT ud.department_id 
                    FROM user_departments ud 
                    WHERE ud.user_id = ?
                )";
                $params[] = $currentUserId;
            } else {
                // Fallback to old department_name logic
                $userDepartmentName = getUserDepartmentName();
                if ($userDepartmentName) {
                    $whereConditions[] = "d.department_name = ?";
                    $params[] = $userDepartmentName;
                }
            }
        } else {
            // Fallback to old department_name logic
            $userDepartmentName = getUserDepartmentName();
            if ($userDepartmentName) {
                $whereConditions[] = "d.department_name = ?";
                $params[] = $userDepartmentName;
            }
        }
    } elseif ($userRole === 'user') {
        // Regular users see their department only
        $userDepartmentName = getUserDepartmentName();
        if ($userDepartmentName) {
            $whereConditions[] = "d.department_name = ?";
            $params[] = $userDepartmentName;
        }
    }
    
    // Add WHERE conditions
    if (!empty($whereConditions)) {
        $baseQuery .= " AND " . implode(" AND ", $whereConditions);
    }
    
    // Add GROUP BY
    $baseQuery .= "
        GROUP BY d.department_id, d.department_name, d.department_description, d.department_email, d.is_active, d.created_at, d.updated_at, p.project_id, p.project_name
    ";
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM ($baseQuery) as subquery";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Add ORDER BY and LIMIT for pagination
    $baseQuery .= " ORDER BY d.department_name, p.project_name LIMIT $limit OFFSET $offset";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($baseQuery);
    $stmt->execute($params);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination info
    $totalPages = ceil($totalRecords / $limit);
    
    // Format the response
    $formattedDepartments = [];
    foreach ($departments as $dept) {
        $formattedDepartments[] = [
            'department_id' => $dept['department_id'],
            'department_name' => $dept['department_name'],
            'department_description' => $dept['department_description'],
            'department_email' => $dept['department_email'],
            'is_active' => (bool)$dept['is_active'],
            'created_at' => $dept['created_at'],
            'updated_at' => $dept['updated_at'],
            'project_id' => $dept['project_id'] ?: null,
            'project_name' => $dept['project_name'],
            'users_count' => (int)$dept['users_count'],
            'licenses_count' => (int)$dept['licenses_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedDepartments,
        'total' => count($formattedDepartments),
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $limit,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()
    ]);
}
?> 
