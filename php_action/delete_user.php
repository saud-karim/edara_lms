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

// Check if user has permission to delete users
if (!isLoggedIn() || (!hasPermission('users_delete') && getUserRole() !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بحذف المستخدمين']);
    exit;
}

try {
    $userId = intval($_POST['user_id'] ?? 0);
    
    if (!$userId) {
        echo json_encode(['error' => 'معرف المستخدم غير صالح']);
        exit;
    }
    
    $conn = getDBConnection();
    $currentUserId = getUserId();
    
    // Get user details to check permissions
    $stmt = $conn->prepare("
        SELECT u.*, d.department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.department_id 
        WHERE u.user_id = ? AND u.is_active = 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['error' => 'المستخدم غير موجود']);
        exit;
    }
    
    // Check department access for non-super_admin users
    $currentUserRole = getUserRole();
    $currentUserDepartment = getUserDepartment();
    
    if ($currentUserRole !== 'super_admin' && $currentUserDepartment != $user['department_id']) {
        echo json_encode(['error' => 'غير مصرح لك بحذف مستخدمين من أقسام أخرى']);
        exit;
    }
    
    // Prevent user from deleting themselves
    if ($userId == $currentUserId) {
        echo json_encode(['error' => 'لا يمكنك حذف حسابك الخاص']);
        exit;
    }
    
    // Check if super_admin is trying to delete another super_admin
    if (getUserRole() === 'super_admin' && $user['role'] === 'super_admin' && $userId != $currentUserId) {
        echo json_encode(['error' => 'لا يمكن حذف مشرف عام آخر']);
        exit;
    }
    
    // For regular users, check if they're managed by current admin
    if (getUserRole() === 'admin') {
        $currentUserDept = getUserDepartmentName();
        if ($user['department_name'] !== $currentUserDept) {
            echo json_encode(['error' => 'غير مصرح لك بحذف هذا المستخدم']);
            exit;
        }
    }
    
    // Soft delete the user
    $deleteStmt = $conn->prepare("UPDATE users SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
    $result = $deleteStmt->execute([$userId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم إلغاء تفعيل المستخدم بنجاح',
            'data' => [
                'user_id' => $userId,
                'username' => $user['username'],
                'full_name' => $user['full_name']
            ]
        ]);
    } else {
        echo json_encode(['error' => 'فشل في إلغاء تفعيل المستخدم']);
    }
    
} catch (Exception $e) {
    error_log("Delete user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم: ' . $e->getMessage()]);
}
?> 
