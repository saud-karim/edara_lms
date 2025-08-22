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
    
    // Simple query to get all departments with their project associations
    $baseQuery = "
        SELECT 
            d.department_id, 
            d.department_name, 
            d.department_description, 
            d.department_email, 
            d.is_active, 
            d.created_at, 
            d.updated_at,
            p.project_id, 
            p.project_name,
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
            SELECT DISTINCT department_id, project_id FROM users WHERE is_active = 1 AND department_id IS NOT NULL AND project_id IS NOT NULL
            UNION
            SELECT DISTINCT department_id, project_id FROM personal_licenses WHERE is_active = 1 AND department_id IS NOT NULL AND project_id IS NOT NULL
            UNION
            SELECT DISTINCT department_id, project_id FROM vehicle_licenses WHERE is_active = 1 AND department_id IS NOT NULL AND project_id IS NOT NULL
        ) dept_projects ON d.department_id = dept_projects.department_id
        LEFT JOIN projects p ON dept_projects.project_id = p.project_id AND p.is_active = 1
        LEFT JOIN users u ON d.department_id = u.department_id AND (p.project_id IS NULL OR u.project_id = p.project_id) AND u.is_active = 1
        WHERE d.is_active = 1
    ";
    
    $whereConditions = [];
    $params = [];
    
    // Department filter
    if (!empty($departmentId)) {
        if (is_numeric($departmentId)) {
            $whereConditions[] = "d.department_id = ?";
            $params[] = intval($departmentId);
        } else {
            $whereConditions[] = "d.department_name = ?";
            $params[] = $departmentId;
        }
    }
    
    // Project filter
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
    if ($userRole === 'admin' || $userRole === 'user') {
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
    
    // Add GROUP BY and ORDER BY
    $baseQuery .= "
        GROUP BY d.department_id, d.department_name, d.department_description, d.department_email, d.is_active, d.created_at, d.updated_at, p.project_id, p.project_name
        ORDER BY d.department_name, p.project_name
    ";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($baseQuery);
    $stmt->execute($params);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
            'project_name' => $dept['project_name'] ?: 'غير محدد',
            'users_count' => (int)$dept['users_count'],
            'licenses_count' => (int)$dept['licenses_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedDepartments,
        'total' => count($formattedDepartments)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()
    ]);
}
?> 
