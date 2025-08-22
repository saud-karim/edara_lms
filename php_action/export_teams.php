<?php
require_once '../config/config.php';
require_once 'auth.php';

// Only Super Admin can access this
if (!isLoggedIn() || getUserRole() !== 'super_admin') {
    header("Location: ../dashboard.php");
    exit;
}

try {
    $conn = getDBConnection();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="teams_export_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    fputcsv($output, [
        'نوع المستخدم',
        'اسم المستخدم',
        'اسم المستخدم (Username)',
        'البريد الإلكتروني',
        'القسم',
        'المشروع',
        'المدير الرئيسي',
        'عدد الرخص الشخصية',
        'عدد رخص المركبات',
        'إجمالي الرخص',
        'تاريخ الانضمام'
    ]);
    
    // Get all users data
    $query = "
        SELECT u.user_id, u.full_name, u.username, u.email, u.role, u.parent_admin_id, u.created_at,
               d.department_name, p.project_name,
               parent.full_name as parent_name,
               (SELECT COUNT(*) FROM personal_licenses WHERE user_id = u.user_id AND is_active = 1) as personal_count,
               (SELECT COUNT(*) FROM vehicle_licenses WHERE user_id = u.user_id AND is_active = 1) as vehicle_count
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN projects p ON u.project_id = p.project_id
        LEFT JOIN users parent ON u.parent_admin_id = parent.user_id
        WHERE u.is_active = 1
        ORDER BY 
            CASE 
                WHEN u.role = 'super_admin' THEN 1
                WHEN u.role = 'admin' AND u.parent_admin_id IS NULL THEN 2
                WHEN u.role = 'admin' AND u.parent_admin_id IS NOT NULL THEN 3
                ELSE 4
            END,
            u.full_name
    ";
    
    $stmt = $conn->query($query);
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        // Determine user type
        $userType = '';
        if ($user['role'] === 'super_admin') {
            $userType = 'مدير عام';
        } elseif ($user['role'] === 'admin') {
            if ($user['parent_admin_id'] === null) {
                $userType = 'مدير رئيسي';
            } else {
                $userType = 'مدير فرعي';
            }
        } else {
            $userType = 'مستخدم عادي';
        }
        
        // Format date
        $joinDate = $user['created_at'] ? date('Y-m-d H:i', strtotime($user['created_at'])) : '';
        
        // Calculate total licenses
        $totalLicenses = $user['personal_count'] + $user['vehicle_count'];
        
        // Write row
        fputcsv($output, [
            $userType,
            $user['full_name'],
            $user['username'],
            $user['email'],
            $user['department_name'] ?? 'لا يوجد',
            $user['project_name'] ?? 'لا يوجد',
            $user['parent_name'] ?? 'مستقل',
            $user['personal_count'],
            $user['vehicle_count'],
            $totalLicenses,
            $joinDate
        ]);
    }
    
    // Add summary section
    fputcsv($output, []); // Empty row
    fputcsv($output, ['=== ملخص الإحصائيات ===']);
    fputcsv($output, []);
    
    // Get statistics
    $superAdminCount = 0;
    $headAdminCount = 0;
    $subAdminCount = 0;
    $regularUserCount = 0;
    $totalPersonalLicenses = 0;
    $totalVehicleLicenses = 0;
    
    foreach ($users as $user) {
        if ($user['role'] === 'super_admin') {
            $superAdminCount++;
        } elseif ($user['role'] === 'admin') {
            if ($user['parent_admin_id'] === null) {
                $headAdminCount++;
            } else {
                $subAdminCount++;
            }
        } else {
            $regularUserCount++;
        }
        
        $totalPersonalLicenses += $user['personal_count'];
        $totalVehicleLicenses += $user['vehicle_count'];
    }
    
    fputcsv($output, ['نوع الإحصائية', 'العدد']);
    fputcsv($output, ['مديرين عامين', $superAdminCount]);
    fputcsv($output, ['مديرين رئيسيين', $headAdminCount]);
    fputcsv($output, ['مديرين فرعيين', $subAdminCount]);
    fputcsv($output, ['مستخدمين عاديين', $regularUserCount]);
    fputcsv($output, ['إجمالي المستخدمين', count($users)]);
    fputcsv($output, []);
    fputcsv($output, ['إجمالي الرخص الشخصية', $totalPersonalLicenses]);
    fputcsv($output, ['إجمالي رخص المركبات', $totalVehicleLicenses]);
    fputcsv($output, ['إجمالي الرخص', $totalPersonalLicenses + $totalVehicleLicenses]);
    fputcsv($output, []);
    fputcsv($output, ['تاريخ التصدير', date('Y-m-d H:i:s')]);
    fputcsv($output, ['المستخدم', $_SESSION['full_name'] ?? 'غير محدد']);
    
    // Add teams breakdown
    fputcsv($output, []);
    fputcsv($output, ['=== تفاصيل الفرق ===']);
    fputcsv($output, []);
    fputcsv($output, ['المدير الرئيسي', 'عدد المديرين الفرعيين', 'إجمالي رخص الفريق']);
    
    // Get teams data
    $teamsQuery = "
        SELECT h.full_name as head_name,
               COUNT(DISTINCT s.user_id) as sub_count,
               (
                   SELECT COUNT(*) FROM personal_licenses 
                   WHERE user_id IN (
                       SELECT user_id FROM users 
                       WHERE user_id = h.user_id OR parent_admin_id = h.user_id
                   ) AND is_active = 1
               ) +
               (
                   SELECT COUNT(*) FROM vehicle_licenses 
                   WHERE user_id IN (
                       SELECT user_id FROM users 
                       WHERE user_id = h.user_id OR parent_admin_id = h.user_id
                   ) AND is_active = 1
               ) as total_licenses
        FROM users h
        LEFT JOIN users s ON s.parent_admin_id = h.user_id AND s.is_active = 1
        WHERE h.role = 'admin' AND h.parent_admin_id IS NULL AND h.is_active = 1
        GROUP BY h.user_id, h.full_name
        ORDER BY h.full_name
    ";
    
    $teamsStmt = $conn->query($teamsQuery);
    $teams = $teamsStmt->fetchAll();
    
    foreach ($teams as $team) {
        fputcsv($output, [
            $team['head_name'],
            $team['sub_count'],
            $team['total_licenses']
        ]);
    }
    
    // Close output stream
    fclose($output);
    
} catch (Exception $e) {
    error_log("Export teams error: " . $e->getMessage());
    
    // If headers haven't been sent, redirect with error
    if (!headers_sent()) {
        header("Location: ../team_management.php?error=" . urlencode('خطأ في تصدير البيانات'));
    } else {
        echo "خطأ في تصدير البيانات: " . $e->getMessage();
    }
}
?> 