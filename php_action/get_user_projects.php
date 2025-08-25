<?php
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مخول'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if user has permission to view users (needed for project assignment)
$canViewUsers = getUserRole() === 'super_admin' || 
                hasPermission('users_view') || 
                hasPermission('users_edit');

if (!$canViewUsers) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بعرض مشاريع المستخدمين'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

try {
    // Get user_id from both GET and POST (flexible)
    $user_id = null;
    
    if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
    } elseif (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
    }
    
    // Debug logging
    error_log("get_user_projects.php: GET = " . print_r($_GET, true));
    error_log("get_user_projects.php: POST = " . print_r($_POST, true));
    error_log("get_user_projects.php: parsed user_id = $user_id");
    
    if (!$user_id || $user_id <= 0) {
        echo json_encode([
            'error' => 'معرف المستخدم مطلوب',
            'debug' => [
                'received_get' => $_GET,
                'received_post' => $_POST,
                'parsed_user_id' => $user_id
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Verify user exists
    $userStmt = $pdo->prepare("
        SELECT user_id, username, full_name, role 
        FROM users 
        WHERE user_id = ? AND is_active = 1
    ");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'المستخدم غير موجود'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // فحص وجود جدول user_projects أولاً
    $tablesCheck = $pdo->query("SHOW TABLES LIKE 'user_projects'");
    
    if ($tablesCheck->rowCount() == 0) {
        // إنشاء الجدول إذا لم يكن موجود
        $createSQL = "
        CREATE TABLE user_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            project_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            notes TEXT NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_project (user_id, project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($createSQL);
        
        // إدراج بيانات تجريبية
        $insertSQL = "INSERT IGNORE INTO user_projects (user_id, project_id) VALUES (2, 1), (2, 2), (26, 1), (26, 3), (26, 5)";
        $pdo->exec($insertSQL);
        
        error_log("get_user_projects: Created user_projects table and inserted sample data");
    }
    
    // فحص هيكل جدول projects لتحديد العمود الصحيح للوصف
    $projectColumns = $pdo->query("DESCRIBE projects")->fetchAll(PDO::FETCH_COLUMN);
    $descriptionColumn = 'project_description'; // افتراضي
    
    if (in_array('description', $projectColumns)) {
        $descriptionColumn = 'description';
    } elseif (in_array('project_description', $projectColumns)) {
        $descriptionColumn = 'project_description';
    } else {
        $descriptionColumn = "'' as description"; // عمود فارغ
    }
    
    // الحصول على المشاريع المخصصة للمستخدم
    $stmt = $pdo->prepare("
        SELECT p.project_id, p.project_name, p.$descriptionColumn as description,
               CASE WHEN up.user_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
        FROM projects p
        LEFT JOIN user_projects up ON p.project_id = up.project_id AND up.user_id = ?
        WHERE p.is_active = 1
        ORDER BY p.project_name ASC
    ");
    
    $stmt->execute([$user_id]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'projects' => $projects
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error in get_user_projects.php: " . $e->getMessage());
    echo json_encode(['error' => 'حدث خطأ في جلب المشاريع'], JSON_UNESCAPED_UNICODE);
}
?> 