<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Check if user has permission to edit users
if (!isLoggedIn() || (!hasPermission('users_edit') && getUserRole() !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بتعديل المستخدمين']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['error' => 'رمز الأمان غير صحيح']);
    exit;
}

try {
    $conn = getDBConnection();
    $conn->beginTransaction();
    $errors = [];
    
    // Get user ID
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if (!$userId) {
        echo json_encode(['error' => 'معرف المستخدم غير صحيح']);
        exit;
    }
    
    // Check if user exists and get their department
    $userQuery = "SELECT user_id, department_id FROM users WHERE user_id = ? AND is_active = 1";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute([$userId]);
    $targetUser = $userStmt->fetch();
    
    if (!$targetUser) {
        echo json_encode(['error' => 'المستخدم غير موجود أو تم حذفه']);
        exit;
    }
    
    // Check permissions using Admin Teams System - Only Super Admin can edit users
    $currentUserRole = getUserRole();
    
    if ($currentUserRole !== 'super_admin') {
        echo json_encode(['error' => 'غير مصرح لك بتعديل المستخدمين']);
        exit;
    }
    
    // Validate required fields
    $requiredFields = [
        'username' => 'اسم المستخدم',
        'email' => 'البريد الإلكتروني',
        'full_name' => 'الاسم الكامل',
        'role' => 'الدور'
    ];
    
    foreach ($requiredFields as $field => $fieldName) {
        if (empty($_POST[$field])) {
            $errors[] = $fieldName . ' مطلوب';
        }
    }
    
    if (!empty($errors)) {
        echo json_encode(['error' => implode('<br>', $errors)]);
        exit;
    }
    
    // Get and validate input
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $fullName = sanitizeInput($_POST['full_name']);
    $role = sanitizeInput($_POST['role']);
    $departmentId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    
    // Validate username
    if (!preg_match('/^[a-zA-Z0-9_]{3,}$/', $username)) {
        $errors[] = 'اسم المستخدم يجب أن يحتوي على أحرف وأرقام فقط (3 أحرف على الأقل)';
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح';
    }
    
    // Validate password if provided
    if ($password && strlen($password) < 6) {
        $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    }
    
    // Validate role
    $validRoles = ['user', 'admin', 'super_admin'];
    if (!in_array($role, $validRoles)) {
        $errors[] = 'الدور المحدد غير صحيح';
    }
    
    // Department validation - all users can have departments
    // Only super_admin can be without department
    if ($role !== 'super_admin' && empty($departmentId)) {
        $errors[] = 'يجب اختيار قسم للمستخدم';
    } elseif ($role === 'super_admin') {
        // Super admin can be without department
        $departmentId = !empty($departmentId) ? $departmentId : null;
    }
    
    // Check if username already exists (excluding current user)
    $usernameStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $usernameStmt->execute([$username, $userId]);
    if ($usernameStmt->fetch()) {
        $errors[] = 'اسم المستخدم موجود بالفعل';
    }
    
    // Check if email already exists (excluding current user)
    $emailStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $emailStmt->execute([$email, $userId]);
    if ($emailStmt->fetch()) {
        $errors[] = 'البريد الإلكتروني موجود بالفعل';
    }
    
    if (!empty($errors)) {
        echo json_encode(['error' => implode('<br>', $errors)]);
        exit;
    }
    
    // Get project_id from form
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    
    // Get parent_admin_id from form (Admin Teams System)
    $parentAdminId = null;
    if ($role === 'admin' && !empty($_POST['parent_admin_id'])) {
        $parentAdminId = intval($_POST['parent_admin_id']);
        
        // Validate parent admin exists and is a head admin in same department
        $parentStmt = $conn->prepare("
            SELECT user_id FROM users 
            WHERE user_id = ? AND role = 'admin' AND parent_admin_id IS NULL 
            AND department_id = ? AND is_active = 1
        ");
        $parentStmt->execute([$parentAdminId, $departmentId]);
        
        if (!$parentStmt->fetch()) {
            echo json_encode(['error' => 'المدير المباشر المحدد غير صحيح أو من قسم مختلف']);
            exit;
        }
    }

    // Prepare update query
    $updateFields = [
        'username = ?',
        'email = ?', 
        'full_name = ?',
        'role = ?',
        'department_id = ?',
        'project_id = ?',
        'parent_admin_id = ?',
        'updated_at = NOW()'
    ];
    
    $params = [$username, $email, $fullName, $role, $departmentId, $projectId, $parentAdminId];
    
    // Add password to update if provided
    if ($password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateFields[] = 'password = ?';
        $params[] = $hashedPassword;
    }
    
    // Add user ID at the end
    $params[] = $userId;
    
    // Update user
    $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateResult = $updateStmt->execute($params);
    
    if (!$updateResult) {
        $conn->rollback();
        echo json_encode(['error' => 'فشل في تحديث بيانات المستخدم']);
        exit;
    }
    
    // Handle project permissions for admin users
    if ($role === 'admin' && isset($_POST['projects'])) {
        // First, remove all current project assignments for this user
        $deleteProjectsStmt = $conn->prepare("DELETE FROM user_projects WHERE user_id = ?");
        $deleteProjectsStmt->execute([$userId]);
        
        // Add new project assignments if any selected
        if (!empty($_POST['projects'])) {
            $selectedProjects = array_map('intval', $_POST['projects']);
            $selectedProjects = array_filter($selectedProjects, function($pid) { return $pid > 0; });
            
            if (!empty($selectedProjects)) {
                error_log("Processing " . count($selectedProjects) . " selected projects for user $userId");
                
                $insertProjectStmt = $conn->prepare("
                    INSERT INTO user_projects (user_id, project_id, created_at) 
                    VALUES (?, ?, NOW())
                ");
                
                $projectsGranted = 0;
                foreach ($selectedProjects as $projectId) {
                    // Verify project exists and is active
                    $projectCheckStmt = $conn->prepare("SELECT project_id FROM projects WHERE project_id = ? AND is_active = 1");
                    $projectCheckStmt->execute([$projectId]);
                    
                    if ($projectCheckStmt->fetch()) {
                        $result = $insertProjectStmt->execute([$userId, $projectId]);
                        if ($result) {
                            $projectsGranted++;
                            error_log("✅ Successfully granted project $projectId to user $userId");
                        } else {
                            error_log("❌ Failed to grant project $projectId to user $userId");
                        }
                    } else {
                        error_log("⚠️ Project $projectId not found or inactive, skipping");
                    }
                }
                
                error_log("Total projects granted to user $userId: $projectsGranted");
            }
        }
    } elseif ($role !== 'admin') {
        // If role is not admin, remove all project assignments
        $deleteProjectsStmt = $conn->prepare("DELETE FROM user_projects WHERE user_id = ?");
        $deleteProjectsStmt->execute([$userId]);
        error_log("Removed all project assignments for non-admin user $userId");
    }
    
    // Handle permissions update
    $permissionsUpdated = 0;
    
    if (isset($_POST['selected_permissions'])) {
        $selectedPermissions = json_decode($_POST['selected_permissions'], true);
        
        if (is_array($selectedPermissions)) {
            // First, deactivate all current permissions for this user
            $deactivateStmt = $conn->prepare("UPDATE user_permissions SET is_active = 0 WHERE user_id = ?");
            $deactivateResult = $deactivateStmt->execute([$userId]);
            
            if (!$deactivateResult) {
                $conn->rollback();
                echo json_encode(['error' => 'فشل في إلغاء تفعيل الصلاحيات الحالية']);
                exit;
            }
            
            // Then add/activate the selected permissions
            if (count($selectedPermissions) > 0) {
                $insertPermStmt = $conn->prepare("
                    INSERT INTO user_permissions (user_id, permission_id, granted_at, granted_by, is_active, notes) 
                    VALUES (?, ?, NOW(), ?, 1, 'تم تحديثها أثناء تعديل المستخدم')
                    ON DUPLICATE KEY UPDATE is_active = 1, granted_at = NOW(), granted_by = ?, notes = 'تم تحديثها أثناء تعديل المستخدم'
                ");
                
                $currentUserId = getUserId() ?? 1;
                
                foreach ($selectedPermissions as $permissionId) {
                    $permissionId = intval($permissionId);
                    if ($permissionId > 0) {
                        $result = $insertPermStmt->execute([$userId, $permissionId, $currentUserId, $currentUserId]);
                        if ($result) {
                            $permissionsUpdated++;
                        } else {
                            $conn->rollback();
                            echo json_encode(['error' => "فشل في إضافة الصلاحية $permissionId"]);
                            exit;
                        }
                    }
                }
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Get updated user details
    $selectStmt = $conn->prepare("
        SELECT u.*, d.department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.department_id 
        WHERE u.user_id = ?
    ");
    $selectStmt->execute([$userId]);
    $updatedUser = $selectStmt->fetch();
    
    // Count current permissions
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ? AND is_active = 1");
    $countStmt->execute([$userId]);
    $permissionsCount = $countStmt->fetchColumn();
    
    // Success message
    $successMessage = "تم تحديث بيانات المستخدم '{$updatedUser['full_name']}' بنجاح";
    
    if (isset($_POST['selected_permissions'])) {
        if ($permissionsCount > 0) {
            $successMessage .= " - الآن لديه {$permissionsCount} صلاحية نشطة";
        } else {
            $successMessage .= " - تم إزالة جميع الصلاحيات";
        }
    }
    
    $response = [
        'success' => true,
        'message' => $successMessage,
        'user' => $updatedUser,
        'permissions_count' => $permissionsCount,
        'permissions_updated' => $permissionsUpdated
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Edit user error: " . $e->getMessage());
    error_log("Edit user stack trace: " . $e->getTraceAsString());
    echo json_encode(['error' => 'حدث خطأ في الخادم: ' . $e->getMessage()]);
}
?> 
