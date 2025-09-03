<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// التحقق من الصلاحيات - Super Admin فقط
requireRole('super_admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير صحيحة'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$projectId = intval($_POST['project_id'] ?? 0);
$projectEmail = trim($_POST['project_email'] ?? '');

// التحقق من صحة البيانات
if (!$projectId) {
    echo json_encode([
        'success' => false,
        'message' => 'معرف المشروع مطلوب'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!empty($projectEmail) && !filter_var($projectEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'صيغة الإيميل غير صحيحة'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = getDBConnection();
    
    // التحقق من وجود المشروع
    $checkStmt = $conn->prepare("SELECT project_id, project_name FROM projects WHERE project_id = ? AND is_active = 1");
    $checkStmt->execute([$projectId]);
    $project = $checkStmt->fetch();
    
    if (!$project) {
        echo json_encode([
            'success' => false,
            'message' => 'المشروع غير موجود'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // تحديث إيميل المشروع
    $updateStmt = $conn->prepare("
        UPDATE projects 
        SET project_email = ?, updated_at = NOW() 
        WHERE project_id = ?
    ");
    
    $result = $updateStmt->execute([$projectEmail, $projectId]);
    
    if ($result) {
        // تسجيل العملية في سجل النظام
        $logMessage = "تم تحديث إيميل المشروع '{$project['project_name']}' إلى: " . ($projectEmail ?: 'غير محدد');
        $logStmt = $conn->prepare("
            INSERT INTO system_logs (log_level, message, user_id, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $logStmt->execute(['INFO', $logMessage, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث إيميل المشروع بنجاح',
            'data' => [
                'project_id' => $projectId,
                'project_name' => $project['project_name'],
                'project_email' => $projectEmail,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل في تحديث إيميل المشروع'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحديث إيميل المشروع: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 