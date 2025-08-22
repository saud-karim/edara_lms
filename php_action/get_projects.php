<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح بالوصول'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    $userRole = getUserRole();
    $userDepartment = getUserDepartment();
    
    // Base query - fix column names to match actual table structure
    $query = "
                SELECT p.project_id, p.project_name, p.project_description,
                       COUNT(DISTINCT u.user_id) as users_count
                FROM projects p 
                LEFT JOIN users u ON p.project_id = u.project_id AND u.is_active = 1
                WHERE p.is_active = 1
                GROUP BY p.project_id, p.project_name, p.project_description
                ORDER BY p.project_name
            ";
    $params = [];
    
    // Check if user has permission to view all projects - include users with license add permissions
    $canViewAllProjects = $userRole === 'super_admin' || 
                         hasPermission('projects_view') || 
                         hasPermission('licenses_add') || 
                         hasPermission('personal_licenses_add') || 
                         hasPermission('vehicle_licenses_add') ||
                         ($userRole === 'admin' && (hasPermission('licenses_add') || hasPermission('personal_licenses_add') || hasPermission('vehicle_licenses_add')));
    
    // If user doesn't have special permissions, filter by department
    if (!$canViewAllProjects && $userDepartment) {
        $userDepartmentName = getUserDepartmentName();
        if ($userDepartmentName) {
            $query = "
                SELECT DISTINCT p.project_id, p.project_name, p.project_description,
                       COUNT(DISTINCT u.user_id) as users_count
                FROM projects p 
                LEFT JOIN users u ON p.project_id = u.project_id AND u.is_active = 1
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE p.is_active = 1 AND d.department_name = ? AND d.is_active = 1
                GROUP BY p.project_id, p.project_name, p.project_description
                ORDER BY p.project_name
            ";
            $params[] = $userDepartmentName;
        }
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $projects
    ]);
    
} catch (Exception $e) {
    error_log("Get projects error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()
    ]);
}
?> 
