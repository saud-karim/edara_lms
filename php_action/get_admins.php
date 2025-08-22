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
    
    // Get department_id from request (optional)
    $departmentId = intval($_GET['department_id'] ?? 0);
    
    // Base query - get active users with admin roles
    $query = "
        SELECT u.user_id, u.full_name, u.role, d.department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.department_id 
        WHERE u.is_active = 1 AND u.role IN ('super_admin', 'admin')
    ";
    $params = [];
    
    // Filter by department if specified
    if ($departmentId > 0) {
        $query .= " AND u.department_id = ?";
        $params[] = $departmentId;
    } else {
        // If admin user, only show admins from their department + super_admins
        if ($userRole === 'admin' && $userDepartment) {
            $query .= " AND (u.role = 'super_admin' OR u.department_id = ?)";
            $params[] = $userDepartment;
        }
    }
    
    $query .= " ORDER BY u.role DESC, u.full_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $admins = $stmt->fetchAll();
    
    // Format the data
    foreach ($admins as &$admin) {
        // Add role display name
        switch ($admin['role']) {
            case 'super_admin':
                $admin['role_display'] = 'مشرف عام';
                break;
            case 'admin':
                $admin['role_display'] = 'مشرف';
                break;
            default:
                $admin['role_display'] = $admin['role'];
        }
        
        // Create display name with role and department
        $displayParts = [$admin['full_name']];
        if ($admin['department_name']) {
            $displayParts[] = "({$admin['department_name']})";
        }
        $admin['display_name'] = implode(' ', $displayParts);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $admins
    ]);
    
} catch (Exception $e) {
    error_log("Get admins error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()
    ]);
}
?> 
