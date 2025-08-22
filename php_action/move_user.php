<?php
require_once '../config/config.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=UTF-8');

// Only Super Admin can access this
if (!isLoggedIn() || getUserRole() !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit;
}

try {
    $conn = getDBConnection();
    $conn->beginTransaction();
    
    $userId = intval($_POST['user_id']);
    $newParentId = $_POST['new_parent_id'] === '' || $_POST['new_parent_id'] === 'null' ? null : intval($_POST['new_parent_id']);
    $reason = trim($_POST['reason'] ?? '');
    
    if (!$userId) {
        throw new Exception('معرف المستخدم مطلوب');
    }
    
    // Get user info
    $userStmt = $conn->prepare("SELECT full_name, username, role, parent_admin_id FROM users WHERE user_id = ? AND is_active = 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        throw new Exception('المستخدم غير موجود');
    }
    
    // Validate the move
    if ($user['role'] !== 'admin') {
        throw new Exception('يمكن نقل المديرين فقط');
    }
    
    // If setting as head admin, check they're not already a head admin
    if ($newParentId === null && $user['parent_admin_id'] === null) {
        throw new Exception('المستخدم هو مدير رئيسي بالفعل');
    }
    
    // If moving to another head admin, validate the parent exists
    if ($newParentId !== null) {
        $parentStmt = $conn->prepare("
            SELECT user_id, full_name FROM users 
            WHERE user_id = ? AND role = 'admin' AND parent_admin_id IS NULL AND is_active = 1
        ");
        $parentStmt->execute([$newParentId]);
        $parent = $parentStmt->fetch();
        
        if (!$parent) {
            throw new Exception('المدير الرئيسي المحدد غير موجود أو غير صحيح');
        }
        
        // Check if user is trying to become sub-admin under their own sub-admin (prevent hierarchy loops)
        if ($user['parent_admin_id'] === null) { // If user is currently a head admin
            $subAdminsStmt = $conn->prepare("SELECT user_id FROM users WHERE parent_admin_id = ? AND is_active = 1");
            $subAdminsStmt->execute([$userId]);
            $subAdmins = $subAdminsStmt->fetchAll();
            
            foreach ($subAdmins as $subAdmin) {
                if ($subAdmin['user_id'] == $newParentId) {
                    throw new Exception('لا يمكن جعل المدير الرئيسي تابع لأحد مرؤوسيه');
                }
            }
        }
    }
    
    // Get old parent info for logging
    $oldParentName = 'مستقل';
    if ($user['parent_admin_id']) {
        $oldParentStmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $oldParentStmt->execute([$user['parent_admin_id']]);
        $oldParent = $oldParentStmt->fetch();
        $oldParentName = $oldParent ? $oldParent['full_name'] : 'غير محدد';
    }
    
    $newParentName = 'مستقل';
    if ($newParentId) {
        $newParentName = $parent['full_name'];
    }
    
    // Handle special case: Converting head admin to sub admin
    if ($user['parent_admin_id'] === null && $newParentId !== null) {
        // Move all sub-admins of this head admin to become independent head admins
        $moveSubsStmt = $conn->prepare("UPDATE users SET parent_admin_id = NULL WHERE parent_admin_id = ? AND is_active = 1");
        $moveSubsStmt->execute([$userId]);
        
        $movedCount = $moveSubsStmt->rowCount();
        if ($movedCount > 0) {
            error_log("Moved {$movedCount} sub-admins to become head admins when converting {$user['full_name']} to sub-admin");
        }
    }
    
    // Perform the move
    $updateStmt = $conn->prepare("UPDATE users SET parent_admin_id = ? WHERE user_id = ?");
    $updateStmt->execute([$newParentId, $userId]);
    
    // Log the action
    $logMessage = sprintf(
        "Super Admin moved user: %s (%s) from '%s' to '%s'. Reason: %s",
        $user['full_name'],
        $user['username'],
        $oldParentName,
        $newParentName,
        $reason ?: 'لا يوجد سبب محدد'
    );
    
    error_log("Team Management: " . $logMessage);
    
    // Insert into system logs if the table exists
    try {
        $logStmt = $conn->prepare("
            INSERT INTO system_logs (user_id, action, details, created_at) 
            VALUES (?, 'user_moved', ?, NOW())
        ");
        $logStmt->execute([getUserId(), $logMessage]);
    } catch (Exception $e) {
        // Log table might not exist, ignore this error
    }
    
    $conn->commit();
    
    // Determine the new status
    $newStatus = $newParentId === null ? 'مدير رئيسي' : 'مدير فرعي تحت ' . $newParentName;
    
    echo json_encode([
        'success' => true,
        'message' => "تم نقل {$user['full_name']} بنجاح. النوع الجديد: {$newStatus}"
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Move user error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 