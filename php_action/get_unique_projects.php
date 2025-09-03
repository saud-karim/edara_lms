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
    
    // Query to get projects with licenses count
    $query = "
        SELECT 
            p.project_id,
            p.project_name,
            p.project_description,
            (COUNT(DISTINCT pl.license_id) + COUNT(DISTINCT vl.license_id)) as licenses_count
        FROM projects p
        LEFT JOIN personal_licenses pl ON p.project_id = pl.project_id AND pl.is_active = 1
        LEFT JOIN vehicle_licenses vl ON p.project_id = vl.project_id AND vl.is_active = 1
        WHERE p.is_active = 1
    ";
    
    $params = [];
    $currentUserId = getCurrentUserId();
    
    // Apply user role restrictions based on permissions system
    if ($userRole === 'admin') {
        // Admin users: show only projects they have access to through user_projects table
        // First check if user_projects table exists and user has projects assigned
        $checkUserProjects = $conn->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_projects'");
        $checkUserProjects->execute();
        $tableExists = $checkUserProjects->fetch()['count'] > 0;
        
        if ($tableExists) {
            $checkUserHasProjects = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM user_projects 
                WHERE user_id = ?
            ");
            $checkUserHasProjects->execute([$currentUserId]);
            $userHasProjects = $checkUserHasProjects->fetch()['count'] > 0;
            
            if ($userHasProjects) {
                // Filter by user's assigned projects
                $query .= " AND p.project_id IN (
                    SELECT up.project_id 
                    FROM user_projects up 
                    WHERE up.user_id = ?
                )";
                $params[] = $currentUserId;
            }
            // If user has no projects assigned, show all projects (admin can see all)
        }
    } elseif ($userRole === 'user') {
        // Regular users: show projects they have access to through user_projects table
        $checkUserProjects = $conn->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_projects'");
        $checkUserProjects->execute();
        $tableExists = $checkUserProjects->fetch()['count'] > 0;
        
        if ($tableExists) {
            $checkUserHasProjects = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM user_projects 
                WHERE user_id = ?
            ");
            $checkUserHasProjects->execute([$currentUserId]);
            $userHasProjects = $checkUserHasProjects->fetch()['count'] > 0;
            
            if ($userHasProjects) {
                // Filter by user's assigned projects
                $query .= " AND p.project_id IN (
                    SELECT up.project_id 
                    FROM user_projects up 
                    WHERE up.user_id = ?
                )";
                $params[] = $currentUserId;
            } else {
                // If user has no projects assigned, show no projects
                $query .= " AND 1 = 0";
            }
        }
    }
    // super_admin: no restrictions - sees all projects
    
    $query .= "
        GROUP BY p.project_id, p.project_name, p.project_description
        ORDER BY p.project_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
    
    // Format data for dropdown use
    $formattedProjects = [];
    foreach ($projects as $project) {
        $formattedProjects[] = [
            'project_id' => $project['project_id'],
            'project_name' => $project['project_name'],
            'project_description' => $project['project_description'],
            'licenses_count' => $project['licenses_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedProjects
    ]);

} catch (Exception $e) {
    error_log("Get unique projects error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل المشاريع'
    ]);
}
?> 