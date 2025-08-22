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
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    // Verify user can only change their own password (except super_admin)
    $currentUserId = getUserId();
    $currentUserRole = getUserRole();
    
    if ($currentUserRole !== 'super_admin' && $userId !== $currentUserId) {
        echo json_encode(['success' => false, 'error' => 'غير مصرح لك بتغيير كلمة مرور هذا المستخدم']);
        exit;
    }
    
    // Validate input
    if (!$userId) $errors[] = 'معرف المستخدم مطلوب';
    if (!$currentPassword) $errors[] = 'كلمة المرور الحالية مطلوبة';
    if (!$newPassword) $errors[] = 'كلمة المرور الجديدة مطلوبة';
    if (!$confirmPassword) $errors[] = 'تأكيد كلمة المرور مطلوب';
    if (strlen($newPassword) < 6) $errors[] = 'كلمة المرور الجديدة يجب أن تحتوي على 6 أحرف على الأقل';
    if ($newPassword !== $confirmPassword) $errors[] = 'كلمة المرور الجديدة وتأكيدها غير متطابقان';
    if ($currentPassword === $newPassword) $errors[] = 'كلمة المرور الجديدة يجب أن تختلف عن الحالية';
    
    // Get current user data
    $userStmt = $conn->prepare("SELECT user_id, password FROM users WHERE user_id = ? AND is_active = 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'المستخدم غير موجود']);
        exit;
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        $errors[] = 'كلمة المرور الحالية غير صحيحة';
    }
    
    // Return errors if any
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
        exit;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $conn->prepare("
            UPDATE users 
            SET password = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE user_id = ?
        ");
        $result = $updateStmt->execute([$hashedPassword, $userId]);
        
        if (!$result) {
            throw new Exception('فشل في تغيير كلمة المرور');
        }
        
        // Log password change (optional - for security audit)
        $logStmt = $conn->prepare("
            INSERT INTO user_logs (user_id, action, performed_by, created_at) 
            VALUES (?, 'password_changed', ?, CURRENT_TIMESTAMP)
        ");
        
        // Create user_logs table if it doesn't exist (silent fail if exists)
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS user_logs (
                    log_id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    performed_by INT NOT NULL,
                    details TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE SET NULL
                )
            ");
            
            $logStmt->execute([$userId, $currentUserId]);
        } catch (Exception $e) {
            // Silent fail for logging - don't break the main functionality
            error_log("Password change logging failed: " . $e->getMessage());
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Change password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في تغيير كلمة المرور']);
}
?> 