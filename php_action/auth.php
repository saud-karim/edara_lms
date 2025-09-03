<?php
// Authentication and Authorization System
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
function requireLogin() {
    if (!isLoggedIn()) {
        redirectTo('login.php');
    }
}

// Check if user has specific role
function requireRole($requiredRole) {
    requireLogin();
    
    $userRole = getUserRole();
    
    if ($requiredRole === 'admin' && !in_array($userRole, ['admin', 'super_admin'])) {
        redirectTo('dashboard.php');
    }
    
    if ($requiredRole === 'super_admin' && $userRole !== 'super_admin') {
        redirectTo('dashboard.php');
    }
}

// Check if user can access specific department data
function canAccessDepartment($departmentId) {
    $userRole = getUserRole();
    
    // Super admin can access all departments
    if ($userRole === 'super_admin') {
        return true;
    }
    
    // Regular users can view all departments
    if ($userRole === 'regular' || $userRole === 'user') {
        return true;
    }
    
    // Admins can only access their own department
    if ($userRole === 'admin') {
        return getUserDepartment() == $departmentId;
    }
    
    return false;
}

// Check if user can access specific department by name
function canAccessDepartmentByName($departmentName) {
    $userRole = getUserRole();
    
    // Super admin can access all departments
    if ($userRole === 'super_admin') {
        return true;
    }
    
    // Regular users can view all departments
    if ($userRole === 'regular' || $userRole === 'user') {
        return true;
    }
    
    // Admins can only access their own department
    if ($userRole === 'admin') {
        return getUserDepartmentName() === $departmentName;
    }
    
    return false;
}

// Check if user can edit/delete records
function canEditRecords($departmentId = null) {
    $userRole = getUserRole();
    
    // Regular users cannot edit
    if ($userRole === 'regular') {
        return false;
    }
    
    // Super admin can edit everything
    if ($userRole === 'super_admin') {
        return true;
    }
    
    // Admins can only edit their department
    if ($userRole === 'admin' && $departmentId) {
        return getUserDepartment() == $departmentId;
    }
    
    return $userRole === 'admin';
}

// Get user permissions for navigation
// This function has been moved to the end of the file and updated to work with the new permissions system

// Get user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT u.*, d.department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.department_id 
            WHERE u.user_id = ? AND u.is_active = 1
        ");
        $stmt->execute([getUserId()]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

// Login function
function loginUser($username, $password) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT u.*, d.department_name, p.project_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.department_id 
            LEFT JOIN projects p ON u.project_id = p.project_id
            WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['department_name'] = $user['department_name'];
            $_SESSION['project_id'] = $user['project_id'];
            $_SESSION['project_name'] = $user['project_name'];
            $_SESSION['parent_admin_id'] = $user['parent_admin_id']; // Add parent_admin_id to session
            $_SESSION['login_time'] = time();
            
            return true;
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
    }
    
    return false;
}

// Logout function
function logoutUser() {
    session_destroy();
    redirectTo('login.php');
}

// Check session timeout
function checkSessionTimeout() {
    if (isLoggedIn() && isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            session_destroy();
            redirectTo('login.php?timeout=1');
        }
    }
}

// Get user full name
function getUserFullName() {
    return $_SESSION['full_name'] ?? 'مستخدم';
}

// Auto logout on timeout
checkSessionTimeout();

// ==========================================
// Advanced Permissions System Functions
// دوال نظام الصلاحيات المتقدم
// ==========================================

/**
 * Check if current user has a specific permission
 * التحقق من وجود صلاحية معينة للمستخدم الحالي
 */
function hasPermission($permission_name) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_id = getCurrentUserId();
    
    // Super admin has all permissions
    if (getUserRole() === 'super_admin') {
        return true;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.permission_id
            WHERE up.user_id = ? 
              AND p.permission_name = ?
              AND up.is_active = 1 
              AND p.is_active = 1
        ");
        $stmt->execute([$user_id, $permission_name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if current user has any of the specified permissions
 * التحقق من وجود أي من الصلاحيات المحددة
 */
function hasAnyPermission($permission_names) {
    if (!is_array($permission_names)) {
        return hasPermission($permission_names);
    }
    
    foreach ($permission_names as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if current user has all specified permissions
 * التحقق من وجود جميع الصلاحيات المحددة
 */
function hasAllPermissions($permission_names) {
    if (!is_array($permission_names)) {
        return hasPermission($permission_names);
    }
    
    foreach ($permission_names as $permission) {
        if (!hasPermission($permission)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Require specific permission or throw exception
 * طلب صلاحية محددة أو رمي استثناء
 */
function requirePermission($permission_name, $redirect_url = 'dashboard.php') {
    if (!hasPermission($permission_name)) {
        if (isAjaxRequest()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'ليس لديك صلاحية للوصول إلى هذه الوظيفة'
            ]);
            exit;
        } else {
            $_SESSION['error_message'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة';
            header("Location: $redirect_url");
            exit;
        }
    }
}

/**
 * Require any of the specified permissions or throw exception
 * طلب أي من الصلاحيات المحددة أو رمي استثناء
 */
function requireAnyPermission($permission_names, $redirect_url = 'dashboard.php') {
    if (!hasAnyPermission($permission_names)) {
        if (isAjaxRequest()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'ليس لديك صلاحية للوصول إلى هذه الوظيفة'
            ]);
            exit;
        } else {
            $_SESSION['error_message'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة';
            header("Location: $redirect_url");
            exit;
        }
    }
}

/**
 * Get all permissions for current user
 * جلب جميع صلاحيات المستخدم الحالي
 */
function getCurrentUserPermissions() {
    if (!isLoggedIn()) {
        return [];
    }
    
    $user_id = getCurrentUserId();
    
    // Super admin has all permissions
    if (getUserRole() === 'super_admin') {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                SELECT permission_name 
                FROM permissions 
                WHERE is_active = 1
            ");
            $stmt->execute();
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_name');
        } catch (Exception $e) {
            return [];
        }
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT p.permission_name
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.permission_id
            WHERE up.user_id = ? 
              AND up.is_active = 1 
              AND p.is_active = 1
        ");
        $stmt->execute([$user_id]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_name');
    } catch (Exception $e) {
        error_log("Get user permissions error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if request is AJAX
 * التحقق من كون الطلب AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Get current user ID
 * جلب معرف المستخدم الحالي
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Enhanced access check for files with permissions
 * فحص محسن للوصول للملفات مع الصلاحيات
 */
function hasAccessWithPermissions($page, $required_permissions = []) {
    // Basic access check first
    if (!hasAccess($page)) {
        return false;
    }
    
    // If no specific permissions required, basic access is enough
    if (empty($required_permissions)) {
        return true;
    }
    
    // Check if user has any of the required permissions
    return hasAnyPermission($required_permissions);
}

/**
 * Check if user can edit records (enhanced)
 * التحقق من إمكانية تعديل السجلات (محسن)
 */
function canEditRecordsAdvanced($entity_type = 'licenses') {
    $role = getUserRole();
    
    if ($role === 'super_admin') {
        return true;
    }
    
    if ($role === 'admin') {
        // Check specific permissions based on entity type
        switch ($entity_type) {
            case 'licenses':
                return hasAnyPermission(['licenses_edit', 'licenses_delete']);
            case 'users':
                return hasAnyPermission(['users_edit', 'users_delete']);
            case 'departments':
                return hasAnyPermission(['departments_edit', 'departments_delete']);
            default:
                return hasPermission($entity_type . '_edit');
        }
    }
    
    return false;
}

/**
 * Get permission-based navigation menu
 * جلب قائمة التنقل حسب الصلاحيات
 */
function getPermissionBasedMenu() {
    $menu = [];
    $userPermissions = getCurrentUserPermissions();
    
    // Dashboard - always available for logged in users
    if (isLoggedIn()) {
        $menu['dashboard'] = [
            'url' => 'dashboard.php',
            'title' => 'لوحة التحكم',
            'icon' => 'glyphicon-dashboard'
        ];
    }
    
    // Licenses menu
    if (hasAnyPermission(['licenses_view', 'licenses_add', 'licenses_edit'])) {
        $menu['licenses'] = [
            'title' => 'إدارة التراخيص',
            'icon' => 'glyphicon-file',
            'color' => 'blue',
            'submenu' => []
        ];
        
        if (hasPermission('licenses_view')) {
            $menu['licenses']['submenu']['view'] = [
                'url' => 'licenses.php',
                'title' => 'عرض التراخيص',
                'icon' => 'glyphicon-list-alt'
            ];
        }
        
        if (hasPermission('licenses_add')) {
            $menu['licenses']['submenu']['add'] = [
                'url' => 'add_license.php',
                'title' => 'إضافة ترخيص',
                'icon' => 'glyphicon-plus'
            ];
        }
        
        if (hasPermission('licenses_delete')) {
            $menu['licenses']['submenu']['deleted'] = [
                'url' => 'deleted_licenses.php',
                'title' => 'التراخيص المحذوفة',
                'icon' => 'glyphicon-trash'
            ];
        }
    }
    
    // Users menu
    if (hasAnyPermission(['users_view', 'users_add', 'users_edit'])) {
        $menu['users'] = [
            'title' => 'إدارة المستخدمين',
            'icon' => 'glyphicon-user',
            'color' => 'red',
            'submenu' => []
        ];
        
        if (hasPermission('users_view')) {
            $menu['users']['submenu']['view'] = [
                'url' => 'users.php',
                'title' => 'إدارة المستخدمين',
                'icon' => 'glyphicon-user'
            ];
        }
        
        if (hasPermission('users_add')) {
            $menu['users']['submenu']['add'] = [
                'url' => 'add_user.php',
                'title' => 'إضافة مستخدم',
                'icon' => 'glyphicon-plus'
            ];
        }
        
        if (hasPermission('users_delete')) {
            $menu['users']['submenu']['deleted'] = [
                'url' => 'deleted_users.php',
                'title' => 'المستخدمون المحذوفون',
                'icon' => 'glyphicon-trash'
            ];
        }
        
        if (hasAnyPermission(['departments_view', 'departments_add', 'departments_edit'])) {
            $menu['users']['submenu']['departments'] = [
                'url' => 'departments.php',
                'title' => 'الأقسام',
                'icon' => 'glyphicon-home'
            ];
            
            if (hasPermission('departments_delete')) {
                $menu['users']['submenu']['deleted_departments'] = [
                    'url' => 'deleted_departments.php',
                    'title' => 'الأقسام المحذوفة',
                    'icon' => 'glyphicon-trash'
                ];
            }
        }
    }
    
    return $menu;
}

/**
 * Legacy compatibility functions
 * دوال التوافق مع النظام القديم
 */

// Update existing hasAccess function to work with new permissions
function hasAccess($page, $action = 'view') {
    if (!isLoggedIn()) {
        return false;
    }
    
    $role = getUserRole();
    
    // Super admin has access to everything
    if ($role === 'super_admin') {
        return true;
    }
    
    // Map old page access to new permissions
    $pagePermissions = [
        'dashboard.php' => true, // Always accessible for logged users
        'licenses.php' => ['licenses_view', 'personal_licenses_view', 'vehicle_licenses_view', 'licenses_add', 'personal_licenses_add', 'vehicle_licenses_add', 'licenses_edit', 'personal_licenses_edit', 'vehicle_licenses_edit', 'licenses_delete', 'personal_licenses_delete', 'vehicle_licenses_delete'],
        'add_license.php' => ['licenses_add'],
        'edit_license.php' => ['licenses_edit'],
        'view_license.php' => ['licenses_view'],
        'deleted_licenses.php' => ['licenses_delete', 'personal_licenses_delete', 'vehicle_licenses_delete'],
        'users.php' => ['users_view', 'users_add', 'users_edit', 'users_delete'],
        'add_user.php' => ['users_add'],
        'edit_user.php' => ['users_edit'],
        'view_user.php' => ['users_view'],
        'deleted_users.php' => ['users_delete'],
        'departments.php' => ['departments_view', 'departments_add', 'departments_edit', 'departments_delete'],
        'add_department.php' => ['departments_add'],
        'edit_department.php' => ['departments_edit'],
        'view_department.php' => ['departments_view'],
        'deleted_departments.php' => ['departments_delete'],
    
    ];
    
    if (isset($pagePermissions[$page])) {
        if ($pagePermissions[$page] === true) {
            return true;
        }
        return hasAnyPermission($pagePermissions[$page]);
    }
    
    // Fall back to old role-based logic for unmapped pages
    switch ($role) {
        case 'admin':
            return !in_array($page, ['users.php', 'add_user.php', 'edit_user.php', 'deleted_users.php', 'manage_permissions.php']);
        case 'regular':
        case 'user':
            return in_array($page, ['dashboard.php', 'licenses.php', 'view_license.php']);
        default:
            return false;
    }
} 

// Get navigation items based on user role
function getNavigationItems() {
    if (!isLoggedIn()) {
        return [];
    }
    
    $userRole = getUserRole();
    $items = [];
    
    // License Management
    $licenseItems = [];
    if (hasAccess('licenses.php')) {
        $licenseItems[] = [
            'title' => 'عرض التراخيص',
            'url' => 'licenses.php',
            'icon' => 'glyphicon-list-alt'
        ];
    }
    if (hasAccess('add_license.php')) {
        $licenseItems[] = [
            'title' => 'إضافة ترخيص',
            'url' => 'add_license.php',
            'icon' => 'glyphicon-plus'
        ];
    }
    if (hasAccess('deleted_licenses.php')) {
        $licenseItems[] = [
            'title' => 'التراخيص المحذوفة',
            'url' => 'deleted_licenses.php',
            'icon' => 'glyphicon-trash'
        ];
    }
    
    // Add reports if user can view any licenses
    $hasVehicleView = hasPermission('vehicle_licenses_view');
    $hasPersonalView = hasPermission('personal_licenses_view');
    $hasGeneralView = hasPermission('licenses_view');
    
    if ($hasVehicleView || $hasPersonalView || $hasGeneralView) {
        $licenseItems[] = [
            'title' => 'تقارير التراخيص',
            'url' => 'license_reports.php',
            'icon' => 'glyphicon-stats'
        ];
    }
    
    if (!empty($licenseItems)) {
        $items[] = [
            'title' => 'إدارة التراخيص',
            'color' => 'blue',
            'items' => $licenseItems
        ];
    }
    
    // User Management (Super Admin only)
    if ($userRole === 'super_admin') {
        $userItems = [
            [
                'title' => 'إدارة المستخدمين',
                'url' => 'users.php',
                'icon' => 'glyphicon-user'
            ],
            [
                'title' => 'إضافة مستخدم',
                'url' => 'add_user.php',
                'icon' => 'glyphicon-plus'
            ],
            [
                'title' => 'الأقسام',
                'url' => 'departments.php',
                'icon' => 'glyphicon-home'
            ]
        ];
        
        $items[] = [
            'title' => 'إدارة المستخدمين',
            'color' => 'red',
            'items' => $userItems
        ];
    }
    
    return $items;
}

/**
 * Check if user has any specific permissions in database (regardless of up.is_active)
 * فحص ما إذا كان المستخدم لديه أي صلاحيات محددة في قاعدة البيانات (بغض النظر عن is_active)
 */
function hasAnySpecificPermissionsInDB($userId, $permissionPrefix) {
    try {
        $pdo = getDBConnection();
        $query = "SELECT COUNT(*) as count FROM user_permissions up 
                  JOIN permissions p ON up.permission_id = p.permission_id 
                  WHERE up.user_id = ? AND p.permission_name LIKE ? AND p.is_active = 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $permissionPrefix . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking specific permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * ======================================
 * ADMIN TEAMS MANAGEMENT FUNCTIONS
 * دوال إدارة فرق الأدمن
 * ======================================
 */

/**
 * Get parent admin ID for current user
 * جلب معرف المدير الرئيسي للمستخدم الحالي
 */
function getParentAdminId() {
    return $_SESSION['parent_admin_id'] ?? null;
}

/**
 * Check if current user is a Head Admin (admin with no parent)
 * فحص إذا كان المستخدم الحالي مدير رئيسي
 */
function isHeadAdmin() {
    return getUserRole() === 'admin' && getParentAdminId() === null;
}

/**
 * Check if current user is a Sub Admin (admin with parent)
 * فحص إذا كان المستخدم الحالي مدير فرعي
 */
function isSubAdmin() {
    return getUserRole() === 'admin' && getParentAdminId() !== null;
}

/**
 * Get user type with clear classification
 * جلب نوع المستخدم مع تصنيف واضح
 */
function getUserType() {
    $role = getUserRole();
    
    if ($role === 'super_admin') {
        return 'super_admin';
    }
    
    if ($role === 'admin') {
        return getParentAdminId() === null ? 'head_admin' : 'sub_admin';
    }
    
    return 'user';
}

/**
 * Get IDs of all sub-admins under current Head Admin
 * جلب معرفات جميع المديرين الفرعيين تحت المدير الرئيسي الحالي
 */
function getMyTeamIds() {
    if (!isHeadAdmin()) {
        return [];
    }
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT user_id 
            FROM users 
            WHERE parent_admin_id = ? AND is_active = 1
        ");
        $stmt->execute([getUserId()]);
        
        return array_column($stmt->fetchAll(), 'user_id');
    } catch (Exception $e) {
        error_log("Error getting team IDs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all users under current admin (including self)
 * جلب جميع المستخدمين تحت المدير الحالي (بما في ذلك نفسه)
 */
function getMyTeamAndSelfIds() {
    $teamIds = getMyTeamIds();
    $teamIds[] = getUserId(); // Add current user ID
    return array_unique($teamIds);
}

/**
 * Check if a user ID is in current admin's team
 * فحص إذا كان معرف مستخدم في فريق المدير الحالي
 */
function isInMyTeam($userId) {
    if (!isHeadAdmin()) {
        return false;
    }
    
    $teamIds = getMyTeamIds();
    return in_array($userId, $teamIds);
}

/**
 * Check if current user can modify a license based on its user_id
 * فحص إذا كان المستخدم الحالي يمكنه تعديل رخصة بناءً على user_id الخاص بها
 */
function canModifyLicense($licenseUserId) {
    $currentUserId = getUserId();
    
    // Super admin can modify everything
    if (getUserRole() === 'super_admin') {
        return true;
    }
    
    // Head admin can modify own licenses and team licenses
    if (isHeadAdmin()) {
        return $licenseUserId == $currentUserId || isInMyTeam($licenseUserId);
    }
    
    // Sub admin and users can only modify their own licenses
    return $licenseUserId == $currentUserId;
}

/**
 * Get license filter SQL WHERE clause based on user type
 * جلب شرط SQL للفلترة بناءً على نوع المستخدم
 */
function getLicenseFilter($tableAlias = 'pl') {
    // Super admin sees everything
    if (getUserRole() === 'super_admin') {
        return "1=1";
    }
    
    // Head admin sees licenses from their team + licenses added by super admin to their department
    if (isHeadAdmin()) {
        $teamAndSelfIds = getMyTeamAndSelfIds();
        $userDepartmentId = getUserDepartment();
        
        $conditions = [];
        
        // Include licenses added by team members (including self)
        if (!empty($teamAndSelfIds)) {
            $conditions[] = "{$tableAlias}.user_id IN (" . implode(',', $teamAndSelfIds) . ")";
        }
        
        // Include licenses added by super admin to the same department
        if ($userDepartmentId) {
            try {
                $conn = getDBConnection();
                $stmt = $conn->query("SELECT user_id FROM users WHERE role = 'super_admin' AND is_active = 1");
                $superAdminIds = array_column($stmt->fetchAll(), 'user_id');
                
                if (!empty($superAdminIds)) {
                    $conditions[] = "({$tableAlias}.user_id IN (" . implode(',', $superAdminIds) . ") AND {$tableAlias}.department_id = " . intval($userDepartmentId) . ")";
                }
            } catch (Exception $e) {
                error_log("Error getting super admin IDs in getLicenseFilter: " . $e->getMessage());
            }
        }
        
        if (!empty($conditions)) {
            return "(" . implode(' OR ', $conditions) . ")";
        } else {
            // Fallback to own licenses only
                return "{$tableAlias}.user_id = " . getUserId();
        }
    }
    
    // Sub admin sees only their own licenses
    if (isSubAdmin()) {
        return "{$tableAlias}.user_id = " . getUserId();
    }
    
    // Regular users see all licenses in their department
    if (getUserRole() === 'user') {
        $userDepartmentId = getUserDepartment();
        if ($userDepartmentId) {
            return "{$tableAlias}.department_id = " . intval($userDepartmentId);
        }
    }
    
    // Fallback: only own licenses
    return "{$tableAlias}.user_id = " . getUserId();
}

/**
 * Get team information for Head Admin
 * جلب معلومات الفريق للمدير الرئيسي
 */
function getTeamInfo() {
    if (!isHeadAdmin()) {
        return [];
    }
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT u.user_id, u.username, u.full_name, u.email,
                   d.department_name, p.project_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.department_id
            LEFT JOIN projects p ON u.project_id = p.project_id
            WHERE u.parent_admin_id = ? AND u.is_active = 1
            ORDER BY u.full_name
        ");
        $stmt->execute([getUserId()]);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting team info: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user type display name in Arabic
 * جلب اسم نوع المستخدم باللغة العربية للعرض
 */
function getUserTypeDisplayName() {
    switch (getUserType()) {
        case 'super_admin':
            return 'مدير عام';
        case 'head_admin':
            return 'مدير رئيسي';
        case 'sub_admin':
            return 'مدير فرعي';
        case 'user':
            return 'مستخدم';
        default:
            return 'غير محدد';
    }
}
