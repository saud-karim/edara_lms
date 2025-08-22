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
    $userRole = getUserRole();
    
    // Query to get departments
    $query = "
        SELECT 
            d.department_id,
            d.department_name,
            d.department_description,
            COUNT(DISTINCT u.user_id) as users_count,
            (COUNT(DISTINCT pl.license_id) + COUNT(DISTINCT vl.license_id)) as licenses_count
        FROM departments d
        LEFT JOIN users u ON d.department_id = u.department_id AND u.is_active = 1
        LEFT JOIN personal_licenses pl ON d.department_id = pl.department_id AND pl.is_active = 1
        LEFT JOIN vehicle_licenses vl ON d.department_id = vl.department_id AND vl.is_active = 1
        WHERE d.is_active = 1
    ";
    
    $params = [];
    
    // Apply user role restrictions - filter by department name for admin/user
    if ($userRole === 'admin' || $userRole === 'user') {
        $userDepartmentName = getUserDepartmentName();
        if ($userDepartmentName) {
            $query .= " AND d.department_name = ?";
            $params[] = $userDepartmentName;
        }
    }
    
    $query .= "
        GROUP BY d.department_id, d.department_name, d.department_description
        ORDER BY d.department_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $departments = $stmt->fetchAll();
    
    // Format data for dropdown use
    $formattedDepartments = [];
    foreach ($departments as $dept) {
        $formattedDepartments[] = [
            'department_id' => $dept['department_id'],
            'department_name' => $dept['department_name'],
            'department_description' => $dept['department_description'],
            'users_count' => $dept['users_count'],
            'licenses_count' => $dept['licenses_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedDepartments
    ]);

} catch (Exception $e) {
    error_log("Get unique departments error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل الأقسام'
    ]);
}
?> 