<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// التحقق من الصلاحيات - Super Admin فقط
requireRole('super_admin');

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // جلب جميع المشاريع مع إيميلاتها وعدد المستخدمين
    $stmt = $conn->prepare("
        SELECT 
            p.project_id,
            p.project_name,
            p.project_description,
            p.project_email,
            p.is_active,
            p.created_at,
            p.updated_at,
            (
                SELECT COUNT(DISTINCT up.user_id) 
                FROM user_projects up 
                WHERE up.project_id = p.project_id
            ) as users_count,
            (
                SELECT COUNT(*) 
                FROM personal_licenses pl 
                WHERE pl.project_id = p.project_id AND pl.is_active = 1
            ) as personal_licenses_count,
            (
                SELECT COUNT(*) 
                FROM vehicle_licenses vl 
                WHERE vl.project_id = p.project_id AND vl.is_active = 1
            ) as vehicle_licenses_count
        FROM projects p
        WHERE p.is_active = 1
        ORDER BY p.project_name ASC
    ");
    
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إضافة معلومات إضافية لكل مشروع
    foreach ($projects as &$project) {
        $project['total_licenses'] = $project['personal_licenses_count'] + $project['vehicle_licenses_count'];
        $project['has_email'] = !empty($project['project_email']);
        $project['created_at_formatted'] = date('d/m/Y', strtotime($project['created_at']));
        
        // حالة الإيميل
        if (empty($project['project_email'])) {
            $project['email_status'] = 'not_configured';
            $project['email_status_text'] = 'غير مُعد';
        } else {
            $project['email_status'] = 'configured';
            $project['email_status_text'] = 'مُعد';
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $projects,
        'total_count' => count($projects),
        'configured_count' => count(array_filter($projects, function($p) { return $p['has_email']; })),
        'unconfigured_count' => count(array_filter($projects, function($p) { return !$p['has_email']; }))
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في جلب بيانات المشاريع: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// التحقق من الصلاحيات - Super Admin فقط
requireRole('super_admin');

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // جلب جميع المشاريع مع إيميلاتها وعدد المستخدمين
    $stmt = $conn->prepare("
        SELECT 
            p.project_id,
            p.project_name,
            p.project_description,
            p.project_email,
            p.is_active,
            p.created_at,
            p.updated_at,
            (
                SELECT COUNT(DISTINCT up.user_id) 
                FROM user_projects up 
                WHERE up.project_id = p.project_id
            ) as users_count,
            (
                SELECT COUNT(*) 
                FROM personal_licenses pl 
                WHERE pl.project_id = p.project_id AND pl.is_active = 1
            ) as personal_licenses_count,
            (
                SELECT COUNT(*) 
                FROM vehicle_licenses vl 
                WHERE vl.project_id = p.project_id AND vl.is_active = 1
            ) as vehicle_licenses_count
        FROM projects p
        WHERE p.is_active = 1
        ORDER BY p.project_name ASC
    ");
    
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إضافة معلومات إضافية لكل مشروع
    foreach ($projects as &$project) {
        $project['total_licenses'] = $project['personal_licenses_count'] + $project['vehicle_licenses_count'];
        $project['has_email'] = !empty($project['project_email']);
        $project['created_at_formatted'] = date('d/m/Y', strtotime($project['created_at']));
        
        // حالة الإيميل
        if (empty($project['project_email'])) {
            $project['email_status'] = 'not_configured';
            $project['email_status_text'] = 'غير مُعد';
        } else {
            $project['email_status'] = 'configured';
            $project['email_status_text'] = 'مُعد';
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $projects,
        'total_count' => count($projects),
        'configured_count' => count(array_filter($projects, function($p) { return $p['has_email']; })),
        'unconfigured_count' => count(array_filter($projects, function($p) { return !$p['has_email']; }))
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في جلب بيانات المشاريع: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 
 
 
 
 
 