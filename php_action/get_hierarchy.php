<?php
require_once '../config/config.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=UTF-8');

// Only Super Admin can access this
if (!isLoggedIn() || getUserRole() !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get Super Admin count
    $superAdminStmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'super_admin' AND is_active = 1");
    $superAdminCount = $superAdminStmt->fetch()['count'];
    
    // Get teams hierarchy
    $teamsQuery = "
        SELECT h.user_id as head_id, h.full_name as head_name, h.username as head_username,
               d_head.department_name as head_department,
               s.user_id as sub_id, s.full_name as sub_name, s.username as sub_username
        FROM users h
        LEFT JOIN departments d_head ON h.department_id = d_head.department_id
        LEFT JOIN users s ON s.parent_admin_id = h.user_id AND s.is_active = 1
        WHERE h.role = 'admin' AND h.parent_admin_id IS NULL AND h.is_active = 1
        ORDER BY h.full_name, s.full_name
    ";
    
    $stmt = $conn->query($teamsQuery);
    $teamsData = $stmt->fetchAll();
    
    // Process hierarchy data
    $teams = [];
    $currentTeamId = null;
    $currentTeamIndex = -1;
    
    foreach ($teamsData as $row) {
        if ($row['head_id'] !== $currentTeamId) {
            // New team
            $teams[] = [
                'head_admin_id' => $row['head_id'],
                'head_admin_name' => $row['head_name'] . ' (' . $row['head_username'] . ')',
                'head_admin_department' => $row['head_department'] ?? 'لا يوجد قسم',
                'sub_admins' => []
            ];
            $currentTeamId = $row['head_id'];
            $currentTeamIndex++;
        }
        
        // Add sub admin if exists
        if ($row['sub_id']) {
            $teams[$currentTeamIndex]['sub_admins'][] = [
                'id' => $row['sub_id'],
                'name' => $row['sub_name'] . ' (' . $row['sub_username'] . ')'
            ];
        }
    }
    
    // Get regular users count
    $regularUsersStmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND is_active = 1");
    $regularUsersCount = $regularUsersStmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'hierarchy' => [
            'super_admin_count' => $superAdminCount,
            'regular_users_count' => $regularUsersCount,
            'teams' => $teams
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get hierarchy error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل الهيكل التنظيمي'
    ]);
}
?> 