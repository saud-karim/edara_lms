<?php
/**
 * Get User Departments API
 * API لجلب الأقسام المخصصة للمستخدم
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    echo json_encode(['error' => 'غير مسموح بالوصول']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // جلب user_id من GET أو POST
    $user_id = null;
    if (isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
    } elseif (isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
    }
    
    if (!$user_id || $user_id <= 0) {
        echo json_encode(['error' => 'معرف المستخدم مطلوب ويجب أن يكون رقم صحيح']);
        exit;
    }
    
    error_log("get_user_departments.php: Fetching departments for user_id = $user_id");
    
    // التحقق من صلاحية الوصول
    $currentUserId = getCurrentUserId();
    $currentUserRole = getUserRole();
    
    // Super admin يمكنه رؤية كل شيء
    // Admin يمكنه تعديل المستخدمين تحت إدارته فقط
    if ($currentUserRole !== 'super_admin') {
        if ($currentUserId != $user_id) {
            // التحقق من أن المستخدم المطلوب تحت إدارة المستخدم الحالي
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND (parent_admin_id = ? OR user_id = ?)");
            $stmt->execute([$user_id, $currentUserId, $currentUserId]);
            
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'غير مسموح بالوصول لبيانات هذا المستخدم']);
                exit;
            }
        }
    }
    
    // فحص وجود جدول user_departments أولاً
    $tablesCheck = $pdo->query("SHOW TABLES LIKE 'user_departments'");
    if ($tablesCheck->rowCount() == 0) {
        error_log("get_user_departments: user_departments table does not exist, creating it");
        
        // إنشاء الجدول إذا لم يكن موجوداً
        $createTableSQL = "
        CREATE TABLE user_departments (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            department_id int(11) NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_departments_user_id (user_id),
            KEY idx_user_departments_department_id (department_id),
            UNIQUE KEY unique_user_department (user_id, department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createTableSQL);
        
        error_log("get_user_departments: Created user_departments table");
    }
    
    // جلب جميع الأقسام مع تحديد المخصصة للمستخدم
    $query = "
        SELECT 
            d.department_id,
            d.department_name,
            d.department_description,
            d.department_email,
            d.is_active,
            CASE WHEN ud.user_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
        FROM departments d
        LEFT JOIN user_departments ud ON d.department_id = ud.department_id AND ud.user_id = ?
        WHERE d.is_active = 1
        ORDER BY d.department_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات
    $totalDepartments = count($departments);
    $assignedDepartments = count(array_filter($departments, function($d) { return $d['is_assigned'] == 1; }));
    
    error_log("get_user_departments: Found $totalDepartments departments, $assignedDepartments assigned to user $user_id");
    
    echo json_encode([
        'success' => true,
        'departments' => $departments,
        'stats' => [
            'total_departments' => $totalDepartments,
            'assigned_departments' => $assignedDepartments,
            'unassigned_departments' => $totalDepartments - $assignedDepartments
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error in get_user_departments.php: " . $e->getMessage());
    echo json_encode(['error' => 'حدث خطأ في جلب بيانات الأقسام: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?> 