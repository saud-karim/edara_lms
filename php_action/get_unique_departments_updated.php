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
    $currentUserId = getCurrentUserId();
    
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
    
    // Apply user role restrictions based on new department permissions system
    if ($userRole === 'admin') {
        // Admin users: show only departments they are assigned to in user_departments table
        // First check if user_departments table exists and user has departments assigned
        $checkUserDepts = $conn->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_departments'");
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
                $query .= " AND d.department_id IN (
                    SELECT ud.department_id 
                    FROM user_departments ud 
                    WHERE ud.user_id = ?
                )";
                $params[] = $currentUserId;
            } else {
                // Fallback to old department_name logic
                $userDepartmentName = getUserDepartmentName();
                if ($userDepartmentName) {
                    $query .= " AND d.department_name = ?";
                    $params[] = $userDepartmentName;
                }
            }
        } else {
            // Fallback to old department_name logic
            $userDepartmentName = getUserDepartmentName();
            if ($userDepartmentName) {
                $query .= " AND d.department_name = ?";
                $params[] = $userDepartmentName;
            }
        }
    } elseif ($userRole === 'user') {
        // Regular users: show their default department only
        $userDepartmentName = getUserDepartmentName();
        if ($userDepartmentName) {
            $query .= " AND d.department_name = ?";
            $params[] = $userDepartmentName;
        }
    }
    // super_admin: no restrictions - sees all departments
    
    $query .= "
        GROUP BY d.department_id, d.department_name, d.department_description
        ORDER BY d.department_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $departments = $stmt->fetchAll();
    
    // Debug logging
    error_log("get_unique_departments_updated: User role: $userRole, User ID: $currentUserId, Departments found: " . count($departments));
    
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
        'data' => $formattedDepartments,
        'debug' => [
            'user_role' => $userRole,
            'user_id' => $currentUserId,
            'departments_count' => count($formattedDepartments)
        ]
    ]);

} catch (Exception $e) {
    error_log("Get unique departments error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل الأقسام: ' . $e->getMessage()
    ]);
}
?> 