<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Check if user has permission to add users
if (!isLoggedIn() || (!hasPermission('users_add') && getUserRole() !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بإضافة المستخدمين']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['error' => 'رمز الأمان غير صحيح']);
    exit;
}

try {
    $conn = getDBConnection();
    $conn->beginTransaction(); // بدء transaction
    $errors = [];
    
    // Validate required fields
    $requiredFields = [
        'username' => 'اسم المستخدم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
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
    // Input validation and sanitization
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    // $confirmPassword = $_POST['confirm_password'] ?? ''; // Not used
    $fullName = trim($_POST['full_name']);
    $role = $_POST['role'];
    $departmentId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $parentAdminId = !empty($_POST['parent_admin_id']) ? intval($_POST['parent_admin_id']) : null;
    
    // Validate username format (alphanumeric, underscores, min 3 chars)
    if (!preg_match('/^[a-zA-Z0-9_]{3,}$/', $username)) {
        $errors[] = 'اسم المستخدم يجب أن يحتوي على 3 أحرف على الأقل (أحرف إنجليزية وأرقام و _ فقط)';
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح';
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    }
    
    // Validate role
    $validRoles = ['user', 'admin', 'super_admin'];
    if (!in_array($role, $validRoles)) {
        $errors[] = 'الدور المحدد غير صحيح';
    }
    
    // Validate department for admin role
    if ($role === 'admin') {
        if (!$departmentId) {
            $errors[] = 'القسم مطلوب للمشرفين';
        } else {
            // Check if department exists
            $deptStmt = $conn->prepare("SELECT department_id FROM departments WHERE department_id = ? AND is_active = 1");
            $deptStmt->execute([$departmentId]);
            if (!$deptStmt->fetch()) {
                $errors[] = 'القسم المحدد غير موجود';
            }
        }
    } else if ($role === 'user') {
        // Regular users can have department for access control (optional)
        if ($departmentId) {
            // Check if department exists
            $deptStmt = $conn->prepare("SELECT department_id FROM departments WHERE department_id = ? AND is_active = 1");
            $deptStmt->execute([$departmentId]);
            if (!$deptStmt->fetch()) {
                $errors[] = 'القسم المحدد غير موجود';
            }
        }
    }

    // Validate project if provided
    if ($projectId) {
        $projectStmt = $conn->prepare("SELECT project_id FROM projects WHERE project_id = ? AND is_active = 1");
        $projectStmt->execute([$projectId]);
        if (!$projectStmt->fetch()) {
            $errors[] = 'المشروع المحدد غير موجود أو غير نشط';
        }
    }
    
    // Super admins don't need department or project
    if ($role === 'super_admin') {
        $departmentId = null;
        $projectId = null;
        $parentAdminId = null; // Super admin can't have parent
    }
    
    // Validate parent_admin_id for admin role
    if ($role === 'admin' && $parentAdminId) {
        // Check if parent admin exists and is a head admin in same department
        $parentStmt = $conn->prepare("
            SELECT user_id, department_id 
            FROM users 
            WHERE user_id = ? AND role = 'admin' AND parent_admin_id IS NULL AND is_active = 1
        ");
        $parentStmt->execute([$parentAdminId]);
        $parentAdmin = $parentStmt->fetch();
        
        if (!$parentAdmin) {
            $errors[] = 'المدير الرئيسي المحدد غير موجود أو غير صحيح';
        } elseif ($parentAdmin['department_id'] != $departmentId) {
            $errors[] = 'المدير الرئيسي يجب أن يكون من نفس القسم';
        }
    }
    
    // Only admin role can have parent_admin_id
    if ($role !== 'admin') {
        $parentAdminId = null;
    }
    
    // Check if username already exists
    $usernameStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $usernameStmt->execute([$username]);
    if ($usernameStmt->fetch()) {
        $errors[] = 'اسم المستخدم موجود بالفعل';
    }
    
    // Check if email already exists
    $emailStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $emailStmt->execute([$email]);
    if ($emailStmt->fetch()) {
        $errors[] = 'البريد الإلكتروني موجود بالفعل';
    }
    
    if (!empty($errors)) {
        echo json_encode(['error' => implode('<br>', $errors)]);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $insertStmt = $conn->prepare("
        INSERT INTO users (username, email, password, full_name, role, department_id, project_id, parent_admin_id, is_active, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)
    ");
    
    $result = $insertStmt->execute([
        $username,
        $email,
        $hashedPassword,
        $fullName,
        $role,
        $departmentId,
        $projectId,
        $parentAdminId
    ]);
    
    if ($result) {
        $userId = $conn->lastInsertId();
        
        // Handle project permissions for admin users
        if ($role === 'admin' && !empty($_POST['projects'])) {
            $selectedProjects = array_map('intval', $_POST['projects']);
            $selectedProjects = array_filter($selectedProjects, function($pid) { return $pid > 0; });
            
            if (!empty($selectedProjects)) {
                error_log("Processing " . count($selectedProjects) . " selected projects for user $userId");
                
                // Insert user projects
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

        // Handle department permissions for admin users
        if ($role === 'admin' && !empty($_POST['departments'])) {
            $selectedDepartments = array_map('intval', $_POST['departments']);
            $selectedDepartments = array_filter($selectedDepartments, function($did) { return $did > 0; });
            
            if (!empty($selectedDepartments)) {
                error_log("Processing " . count($selectedDepartments) . " selected departments for user $userId");
                
                // Insert user departments
                $insertDepartmentStmt = $conn->prepare("
                    INSERT INTO user_departments (user_id, department_id, created_at) 
                    VALUES (?, ?, NOW())
                ");
                
                $departmentsGranted = 0;
                foreach ($selectedDepartments as $departmentId) {
                    // Verify department exists and is active
                    $departmentCheckStmt = $conn->prepare("SELECT department_id FROM departments WHERE department_id = ? AND is_active = 1");
                    $departmentCheckStmt->execute([$departmentId]);
                    
                    if ($departmentCheckStmt->fetch()) {
                        $result = $insertDepartmentStmt->execute([$userId, $departmentId]);
                        if ($result) {
                            $departmentsGranted++;
                            error_log("✅ Successfully granted department $departmentId to user $userId");
                        } else {
                            error_log("❌ Failed to grant department $departmentId to user $userId");
                        }
                    } else {
                        error_log("⚠️ Department $departmentId not found or inactive, skipping");
                    }
                }
                
                error_log("Total departments granted to user $userId: $departmentsGranted");
            }
        }

        // Handle selected permissions or apply default permissions
        $permissionsToGrant = [];
        
        // Debug logging 
        error_log("Starting permission assignment for user ID $userId with role: $role");
        
        if (!empty($_POST['selected_permissions'])) {
            // Custom permissions selected from advanced form
            $selectedPermissions = json_decode($_POST['selected_permissions'], true);
            
            if (is_array($selectedPermissions) && !empty($selectedPermissions)) {
                $permissionsToGrant = $selectedPermissions;
                error_log("Using custom permissions for user $userId");
            }
        } else {
        // Also check for permissions[] array (from form checkboxes)
            if (!empty($_POST["permissions"])) {
            $permissionsToGrant = array_map("intval", $_POST["permissions"]);
                error_log("Using form checkbox permissions for user $userId");
            } else {
        // No custom permissions selected, apply default permissions based on role
                error_log("Applying default permissions for role: $role");
            
            switch ($role) {
                case 'user':
                        // Use basic permission IDs directly (more reliable)
                        $permissionsToGrant = [1, 5, 23]; // licenses_view, vehicle_licenses_view, personal_licenses_view
                        error_log("User role: assigned permission IDs " . implode(', ', $permissionsToGrant));
                    break;
                        
                case 'admin':
                        // Admin gets more permissions
                        $permissionsToGrant = [1, 2, 3, 4, 5, 6, 7, 8, 23, 24, 25, 26, 28]; // All license permissions + departments_view
                        error_log("Admin role: assigned permission IDs " . implode(', ', $permissionsToGrant));
                    break;
                        
                case 'super_admin':
                        // Super admin gets all active permissions
                        $permStmt = $conn->prepare("SELECT permission_id FROM permissions WHERE is_active = 1 ORDER BY permission_id");
                        $permStmt->execute();
                        $permissionsToGrant = $permStmt->fetchAll(PDO::FETCH_COLUMN);
                        error_log("Super admin assigned " . count($permissionsToGrant) . " permissions");
                        break;
                        
                    default:
                        // Unknown role, give basic permission
                        $permissionsToGrant = [1]; // licenses_view only
                        error_log("Unknown role '$role': assigned basic permission only");
                    break;
            }
            
                // Verify the permissions exist (safety check)
                if (!empty($permissionsToGrant) && $role !== 'super_admin') {
                    $placeholders = str_repeat('?,', count($permissionsToGrant) - 1) . '?';
                    $verifyStmt = $conn->prepare("SELECT permission_id FROM permissions WHERE permission_id IN ($placeholders) AND is_active = 1");
                    $verifyStmt->execute($permissionsToGrant);
                    $validPermissions = $verifyStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $invalidPerms = array_diff($permissionsToGrant, $validPermissions);
                    if (!empty($invalidPerms)) {
                        error_log("Invalid permission IDs found: " . implode(', ', $invalidPerms));
                        $permissionsToGrant = $validPermissions; // Use only valid ones
                    }
                    
                    error_log("Final valid permissions for role $role: " . implode(', ', $permissionsToGrant));
                }
            }
        }

        
        // Grant permissions if any
        $permissionsGranted = 0;
        error_log("About to grant permissions. Count: " . count($permissionsToGrant));
        error_log("Permissions to grant: " . implode(', ', $permissionsToGrant));
        
        if (!empty($permissionsToGrant)) {
            // Insert permissions for the new user
            $insertPermStmt = $conn->prepare("
                INSERT INTO user_permissions (user_id, permission_id, granted_at, granted_by, is_active, notes) 
                VALUES (?, ?, NOW(), ?, 1, ?)
            ");
            
            foreach ($permissionsToGrant as $permissionId) {
                $permissionId = intval($permissionId);
                error_log("Processing permission ID: $permissionId for user $userId");
                
                if ($permissionId > 0) {
                    try {
                        $currentUserId = getUserId();
                        error_log("Current user ID from session: " . ($currentUserId ?? 'NULL'));
                        
                        $params = [
                            $userId,
                            $permissionId,
                            $currentUserId ?? 1, // fallback to user 1 if null
                            'تم منحها تلقائياً أثناء إنشاء المستخدم' // notes
                        ];
                        
                        error_log("Executing INSERT with params: " . print_r($params, true));
                        
                        $result = $insertPermStmt->execute($params);
                        error_log("INSERT result: " . ($result ? 'TRUE' : 'FALSE'));
                        
                        if (!$result) {
                            $errorInfo = $insertPermStmt->errorInfo();
                            error_log("PDO Error Info: " . print_r($errorInfo, true));
                        }
                        
                        if ($result) {
                            $permissionsGranted++;
                            error_log("✅ Successfully granted permission $permissionId to user $userId");
                        } else {
                            error_log("❌ Failed to grant permission $permissionId to user $userId - no error thrown but result is false");
                        }
                    } catch (Exception $grantError) {
                        // Log error but don't fail the user creation
                        error_log("❌ Permission grant error for permission $permissionId: " . $grantError->getMessage());
                        error_log("Error details: " . print_r($grantError, true));
                    }
                } else {
                    error_log("⚠️ Skipping invalid permission ID: $permissionId");
                }
            }
            
            error_log("FINAL RESULT: Successfully granted $permissionsGranted out of " . count($permissionsToGrant) . " permissions to user $userId ($role)");
        } else {
            error_log("❌ WARNING: No permissions found to grant for user $userId with role $role");
        }
        
        error_log("=== PERMISSION DEBUG END ===");
        
        // Get the inserted user with department info
        $selectStmt = $conn->prepare("
            SELECT u.*, d.department_name, p.project_name
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.department_id 
            LEFT JOIN projects p ON u.project_id = p.project_id
            WHERE u.user_id = ?
        ");
        $selectStmt->execute([$userId]);
        $newUser = $selectStmt->fetch();
        
        // Get the count of granted permissions for the response
        $permissionCountStmt = $conn->prepare("
            SELECT COUNT(*) as permission_count 
            FROM user_permissions 
            WHERE user_id = ? AND is_active = 1
        ");
        $permissionCountStmt->execute([$userId]);
        $permissionCount = $permissionCountStmt->fetch()['permission_count'];
        
        // Create success message
        $successMessage = "تم إضافة المستخدم '{$newUser['full_name']}' بنجاح";
        if ($permissionCount > 0) {
            $successMessage .= " مع {$permissionCount} صلاحية";
        } else {
            $successMessage .= " (تحذير: لم يتم إضافة صلاحيات)";
        }
        
        $conn->commit(); // تأكيد جميع التغييرات
        
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'data' => $newUser,
            'permissions_granted' => $permissionCount,
            'role' => $role,
            'username' => $username
        ]);
    } else {
        $conn->rollback(); // تراجع عن التغييرات
        echo json_encode(['error' => 'فشل في إضافة المستخدم']);
    }

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback(); // تراجع عن التغييرات في حالة الخطأ
    }
    error_log("Add user error: " . $e->getMessage());
    error_log("Add user error file: " . $e->getFile());
    error_log("Add user error line: " . $e->getLine());
    echo json_encode([
        'error' => 'حدث خطأ في الخادم', 
        'debug' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?> 
