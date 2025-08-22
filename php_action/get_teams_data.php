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
    
    // Get overall statistics
    $stats = [];
    
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $stats['total_users'] = $stmt->fetch()['count'];
    
    // Head admins (admin with parent_admin_id = NULL)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND parent_admin_id IS NULL AND is_active = 1");
    $stats['head_admins'] = $stmt->fetch()['count'];
    
    // Sub admins (admin with parent_admin_id != NULL)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND parent_admin_id IS NOT NULL AND is_active = 1");
    $stats['sub_admins'] = $stmt->fetch()['count'];
    
    // Regular users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND is_active = 1");
    $stats['regular_users'] = $stmt->fetch()['count'];
    
    // Get teams data (Head Admins with their Sub Admins)
    $teamsQuery = "
        SELECT h.user_id as head_id, h.full_name as head_name, h.username as head_username,
               h.email as head_email, h.created_at as head_created,
               d_head.department_name as head_department, p_head.project_name as head_project,
               s.user_id as sub_id, s.full_name as sub_name, s.username as sub_username,
               s.email as sub_email, s.created_at as sub_created,
               d_sub.department_name as sub_department, p_sub.project_name as sub_project,
               (SELECT COUNT(*) FROM personal_licenses WHERE user_id = s.user_id AND is_active = 1) as sub_personal_count,
               (SELECT COUNT(*) FROM vehicle_licenses WHERE user_id = s.user_id AND is_active = 1) as sub_vehicle_count,
               (SELECT COUNT(*) FROM personal_licenses WHERE user_id = h.user_id AND is_active = 1) as head_personal_count,
               (SELECT COUNT(*) FROM vehicle_licenses WHERE user_id = h.user_id AND is_active = 1) as head_vehicle_count
        FROM users h
        LEFT JOIN departments d_head ON h.department_id = d_head.department_id
        LEFT JOIN projects p_head ON h.project_id = p_head.project_id
        LEFT JOIN users s ON s.parent_admin_id = h.user_id AND s.is_active = 1
        LEFT JOIN departments d_sub ON s.department_id = d_sub.department_id
        LEFT JOIN projects p_sub ON s.project_id = p_sub.project_id
        WHERE h.role = 'admin' AND h.parent_admin_id IS NULL AND h.is_active = 1
        ORDER BY h.full_name, s.full_name
    ";
    
    $stmt = $conn->query($teamsQuery);
    $teamsData = $stmt->fetchAll();
    
    // Process teams data
    $teams = [];
    $teamsIndex = [];
    
    foreach ($teamsData as $row) {
        $headId = $row['head_id'];
        
        // If this head admin is not yet processed
        if (!isset($teamsIndex[$headId])) {
            $teams[] = [
                'head_admin' => [
                    'user_id' => $headId,
                    'full_name' => $row['head_name'],
                    'username' => $row['head_username'],
                    'email' => $row['head_email'],
                    'department_name' => $row['head_department'],
                    'project_name' => $row['head_project'],
                    'created_at' => $row['head_created'],
                    'personal_licenses_count' => $row['head_personal_count'],
                    'vehicle_licenses_count' => $row['head_vehicle_count']
                ],
                'sub_admins' => [],
                'stats' => [
                    'total_personal_licenses' => $row['head_personal_count'],
                    'total_vehicle_licenses' => $row['head_vehicle_count'],
                    'expiring_licenses' => 0, // Will calculate separately
                    'expired_licenses' => 0   // Will calculate separately
                ]
            ];
            $teamsIndex[$headId] = count($teams) - 1;
        }
        
        // Add sub admin if exists
        if ($row['sub_id']) {
            $teams[$teamsIndex[$headId]]['sub_admins'][] = [
                'user_id' => $row['sub_id'],
                'full_name' => $row['sub_name'],
                'username' => $row['sub_username'],
                'email' => $row['sub_email'],
                'department_name' => $row['sub_department'],
                'project_name' => $row['sub_project'],
                'created_at' => $row['sub_created'],
                'personal_licenses_count' => $row['sub_personal_count'],
                'vehicle_licenses_count' => $row['sub_vehicle_count']
            ];
            
            // Update team stats
            $teams[$teamsIndex[$headId]]['stats']['total_personal_licenses'] += $row['sub_personal_count'];
            $teams[$teamsIndex[$headId]]['stats']['total_vehicle_licenses'] += $row['sub_vehicle_count'];
        }
    }
    
    // Calculate expiring and expired licenses for each team
    foreach ($teams as &$team) {
        $teamUserIds = [$team['head_admin']['user_id']];
        foreach ($team['sub_admins'] as $subAdmin) {
            $teamUserIds[] = $subAdmin['user_id'];
        }
        
        if (!empty($teamUserIds)) {
            $userIdsStr = implode(',', $teamUserIds);
            
            // Expiring licenses (within 30 days)
            $expiringQuery = "
                SELECT COUNT(*) as count FROM (
                    SELECT expiration_date FROM personal_licenses 
                    WHERE user_id IN ($userIdsStr) AND is_active = 1
                    AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    UNION ALL
                    SELECT expiration_date FROM vehicle_licenses 
                    WHERE user_id IN ($userIdsStr) AND is_active = 1
                    AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ) as expiring
            ";
            $stmt = $conn->query($expiringQuery);
            $team['stats']['expiring_licenses'] = $stmt->fetch()['count'];
            
            // Expired licenses
            $expiredQuery = "
                SELECT COUNT(*) as count FROM (
                    SELECT expiration_date FROM personal_licenses 
                    WHERE user_id IN ($userIdsStr) AND is_active = 1 AND expiration_date < CURDATE()
                    UNION ALL
                    SELECT expiration_date FROM vehicle_licenses 
                    WHERE user_id IN ($userIdsStr) AND is_active = 1 AND expiration_date < CURDATE()
                ) as expired
            ";
            $stmt = $conn->query($expiredQuery);
            $team['stats']['expired_licenses'] = $stmt->fetch()['count'];
        }
    }
    
    // Get independent head admins (those without any sub admins)
    $independentQuery = "
        SELECT h.user_id, h.full_name, h.username, h.email,
               d.department_name, p.project_name, h.created_at,
               (SELECT COUNT(*) FROM personal_licenses WHERE user_id = h.user_id AND is_active = 1) as personal_count,
               (SELECT COUNT(*) FROM vehicle_licenses WHERE user_id = h.user_id AND is_active = 1) as vehicle_count
        FROM users h
        LEFT JOIN departments d ON h.department_id = d.department_id
        LEFT JOIN projects p ON h.project_id = p.project_id
        WHERE h.role = 'admin' AND h.parent_admin_id IS NULL AND h.is_active = 1
        AND h.user_id NOT IN (
            SELECT DISTINCT parent_admin_id 
            FROM users 
            WHERE parent_admin_id IS NOT NULL AND is_active = 1
        )
        ORDER BY h.full_name
    ";
    
    $stmt = $conn->query($independentQuery);
    $independentAdmins = $stmt->fetchAll();
    
    // Convert independent admins to team format for consistency
    foreach ($independentAdmins as $admin) {
        $teams[] = [
            'head_admin' => [
                'user_id' => $admin['user_id'],
                'full_name' => $admin['full_name'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'department_name' => $admin['department_name'],
                'project_name' => $admin['project_name'],
                'created_at' => $admin['created_at'],
                'personal_licenses_count' => $admin['personal_count'],
                'vehicle_licenses_count' => $admin['vehicle_count']
            ],
            'sub_admins' => [],
            'stats' => [
                'total_personal_licenses' => $admin['personal_count'],
                'total_vehicle_licenses' => $admin['vehicle_count'],
                'expiring_licenses' => 0,
                'expired_licenses' => 0
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'teams' => $teams,
        'independent_admins' => $independentAdmins
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Get teams data error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل بيانات الفرق'
    ]);
}
?> 