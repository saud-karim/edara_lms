<?php
header('Content-Type: application/json; charset=UTF-8');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Ensure user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح بالوصول']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'رمز الأمان غير صحيح']);
    exit;
}

try {
    $conn = getDBConnection();
    $errors = [];
    
    // Get and validate input
    $userId = intval($_POST['user_id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Verify user can only update their own profile (except super_admin)
    $currentUserId = getUserId();
    $currentUserRole = getUserRole();
    
    if ($currentUserRole !== 'super_admin' && $userId !== $currentUserId) {
        echo json_encode(['success' => false, 'error' => 'غير مصرح لك بتعديل هذا الملف الشخصي']);
        exit;
    }
    
    // Validate input
    if (!$userId) $errors[] = 'معرف المستخدم مطلوب';
    if (!$fullName) $errors[] = 'الاسم الكامل مطلوب';
    if (!$email) $errors[] = 'البريد الإلكتروني مطلوب';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح';
    }
    
    // Check if user exists
    $checkStmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE user_id = ? AND is_active = 1");
    $checkStmt->execute([$userId]);
    $existingUser = $checkStmt->fetch();
    
    if (!$existingUser) {
        echo json_encode(['success' => false, 'error' => 'المستخدم غير موجود']);
        exit;
    }
    
    // Check for duplicate email (excluding current user)
    $emailCheckStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? AND is_active = 1");
    $emailCheckStmt->execute([$email, $userId]);
    if ($emailCheckStmt->fetch()) {
        $errors[] = 'البريد الإلكتروني مستخدم بالفعل';
    }
    
    // Return errors if any
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
        exit;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update user information
        $updateStmt = $conn->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE user_id = ?
        ");
        $result = $updateStmt->execute([$fullName, $email, $userId]);
        
        if (!$result) {
            throw new Exception('فشل في تحديث الملف الشخصي');
        }
        
        // Commit transaction
        $conn->commit();
        
        // Update session if updating own profile
        if ($userId === $currentUserId) {
            $_SESSION['user_full_name'] = $fullName;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'full_name' => $fullName,
            'old_name' => $existingUser['full_name']
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Update profile error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في تحديث الملف الشخصي']);
}
?> 