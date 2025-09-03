<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$debug_info = [];
$debug_info[] = "🚀 Starting preview at " . date('Y-m-d H:i:s');

try {
    $conn = getDBConnection();
    $debug_info[] = "✅ Database connected successfully";
    
    // Get departments with emails and their expiring licenses
    $stmt = $conn->prepare("
        SELECT 
            d.department_id,
            d.department_name,
            d.department_email,
            p.project_id,
            p.project_name,
            p.project_email,
            COUNT(*) as total_licenses,
            SUM(CASE WHEN license_data.expiration_date < CURDATE() THEN 1 ELSE 0 END) as expired_count,
            SUM(CASE WHEN license_data.expiration_date >= CURDATE() AND license_data.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_count,
            GROUP_CONCAT(
                CONCAT(
                    CASE 
                        WHEN license_data.license_type = 'personal' THEN license_data.full_name
                        ELSE '........'
                    END,
                    ' - ',
                    CASE 
                        WHEN license_data.license_type = 'personal' THEN license_data.license_number
                        ELSE CONCAT('مركبة ', license_data.vehicle_type)
                    END,
                    ' (ينتهي في ', 
                    DATEDIFF(license_data.expiration_date, CURDATE()), 
                    CASE 
                        WHEN DATEDIFF(license_data.expiration_date, CURDATE()) <= 0 THEN ' - انتهت)'
                        ELSE ' يوم)'
                    END
                ) 
                SEPARATOR '<br>'
            ) as license_details
        FROM departments d
        INNER JOIN projects p ON 1=1
        INNER JOIN (
            SELECT 'personal' as license_type, pl.department_id, pl.project_id, pl.full_name, pl.license_number, pl.expiration_date, NULL as vehicle_type
            FROM personal_licenses pl 
            WHERE pl.is_active = 1 AND pl.department_id IS NOT NULL AND pl.project_id IS NOT NULL
            UNION ALL
            SELECT 'vehicle' as license_type, vl.department_id, vl.project_id, NULL as full_name, NULL as license_number, vl.expiration_date, vl.vehicle_type
            FROM vehicle_licenses vl 
            WHERE vl.is_active = 1 AND vl.department_id IS NOT NULL AND vl.project_id IS NOT NULL
        ) license_data ON d.department_id = license_data.department_id AND p.project_id = license_data.project_id
        WHERE d.is_active = 1 
        AND p.is_active = 1
        AND d.department_email IS NOT NULL
        AND d.department_email != ''
        GROUP BY d.department_id, d.department_name, d.department_email, p.project_id, p.project_name, p.project_email
        HAVING total_licenses > 0
        ORDER BY d.department_name, p.project_name
    ");
    
    $stmt->execute();
    $departments = $stmt->fetchAll();
    
    $debug_info[] = "📧 Found " . count($departments) . " department-project combinations for preview";
    
    $previewData = [];
    $totalExpired = 0;
    $totalExpiring = 0;
    $willSendCount = 0;
    $uniqueDepartmentsData = []; // To collect data per unique department
    
    // First pass: collect all data and group by department
    foreach ($departments as $dept) {
        $expiredCount = (int)$dept['expired_count'];
        $expiringCount = (int)$dept['expiring_count'];
        $willSend = !empty($dept['department_email']) && ($expiredCount > 0 || $expiringCount > 0);
        
        $deptName = $dept['department_name'];
        
        // Initialize department data if not exists
        if (!isset($uniqueDepartmentsData[$deptName])) {
            $uniqueDepartmentsData[$deptName] = [
                'email' => $dept['department_email'],
                'will_send' => false,
                'total_expired' => 0,
                'total_expiring' => 0,
                'projects' => []
            ];
        }
        
        // Add this project's data to the department
        $uniqueDepartmentsData[$deptName]['projects'][] = $dept['project_name'];
        $uniqueDepartmentsData[$deptName]['total_expired'] += $expiredCount;
        $uniqueDepartmentsData[$deptName]['total_expiring'] += $expiringCount;
        
        // If any project in this department will send, mark department as will_send
        if ($willSend) {
            $uniqueDepartmentsData[$deptName]['will_send'] = true;
        }
        
        $totalExpired += $expiredCount;
        $totalExpiring += $expiringCount;
        
        // Keep individual rows for the table display
        $previewData[] = [
            'department_id' => $dept['department_id'],
            'project_id' => $dept['project_id'],
            'department' => $dept['department_name'],
            'project' => $dept['project_name'],
            'email' => $dept['department_email'],
            'project_email' => $dept['project_email'] ?? null,
            'total_licenses' => $dept['total_licenses'],
            'expired_count' => $expiredCount,
            'expiring_count' => $expiringCount,
            'license_details' => $dept['license_details'],
            'will_send' => $willSend
        ];
    }
    
    // Count unique departments that will send (this should be the same as willSendCount now)
    $uniqueDepartmentsCount = 0;
    foreach ($uniqueDepartmentsData as $deptData) {
        if ($deptData['will_send']) {
            $uniqueDepartmentsCount++;
        }
    }
    
    // Update willSendCount to match unique departments count
    $willSendCount = $uniqueDepartmentsCount;
    
    // Add debug info about unique departments count
    $debug_info[] = "📊 Total rows in preview: " . count($previewData);
    $debug_info[] = "📊 Unique departments that will receive notifications: " . $uniqueDepartmentsCount;
    $debug_info[] = "📊 Will send count (should match unique departments): " . $willSendCount;
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تحميل المعاينة بنجاح',
        'data' => $previewData,
        'summary' => [
            'will_send' => $willSendCount,
            'expired' => $totalExpired,
            'expiring' => $totalExpiring,
            'total_departments' => $uniqueDepartmentsCount
        ],
        'debug_info' => $debug_info
    ]);
    
} catch (Exception $e) {
    $debug_info[] = "💥 Fatal error: " . $e->getMessage();
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل المعاينة: ' . $e->getMessage(),
        'debug_info' => $debug_info
    ]);
}
?> 