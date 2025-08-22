<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'رمز الحماية غير صالح']);
    exit;
}

// Check if user has permission to restore users
if (!isLoggedIn() || (!hasPermission('users_delete') && getUserRole() !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك باستعادة المستخدمين']);
    exit;
}

try {
    $userId = intval($_POST['user_id'] ?? 0);
    
    if (!$userId) {
        echo json_encode(['error' => 'معرف المستخدم غير صالح']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Get deleted user details to check permissions
    $stmt = $conn->prepare("
        SELECT u.*, d.department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.department_id 
        WHERE u.user_id = ? AND u.is_active = 0
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['error' => 'المستخدم المحذوف غير موجود']);
        exit;
    }
    
    // Check department access for non-super_admin users
    $currentUserRole = getUserRole();
    $currentUserDepartment = getUserDepartment();
    
    if ($currentUserRole !== 'super_admin' && $currentUserDepartment != $user['department_id']) {
        echo json_encode(['error' => 'غير مصرح لك باستعادة مستخدمين من أقسام أخرى']);
        exit;
    }
    
    // Check if username is still unique among active users
    $checkStmt = $conn->prepare("
        SELECT user_id 
        FROM users 
        WHERE username = ? AND is_active = 1 AND user_id != ?
    ");
    $checkStmt->execute([$user['username'], $userId]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['error' => 'اسم المستخدم موجود بالفعل في المستخدمين النشطين. يرجى تعديل اسم المستخدم أولاً.']);
        exit;
    }
    
    // Check if email is still unique among active users
    $emailCheckStmt = $conn->prepare("
        SELECT user_id 
        FROM users 
        WHERE email = ? AND is_active = 1 AND user_id != ?
    ");
    $emailCheckStmt->execute([$user['email'], $userId]);
    
    if ($emailCheckStmt->fetch()) {
        echo json_encode(['error' => 'البريد الإلكتروني موجود بالفعل في المستخدمين النشطين. يرجى تعديل البريد الإلكتروني أولاً.']);
        exit;
    }
    
    // Restore the user (set is_active = 1)
    $restoreStmt = $conn->prepare("UPDATE users SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
    $result = $restoreStmt->execute([$userId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم استعادة المستخدم بنجاح',
            'data' => [
                'user_id' => $userId,
                'username' => $user['username'],
                'full_name' => $user['full_name']
            ]
        ]);
    } else {
        echo json_encode(['error' => 'فشل في استعادة المستخدم']);
    }
    
} catch (Exception $e) {
    error_log("Restore user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم']);
}
?> 
