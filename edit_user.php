<?php
$pageTitle = 'تعديل المستخدم';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check if user has permission to edit users
if (!hasPermission('users_edit') && getUserRole() !== 'super_admin') {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بتعديل المستخدمين', 'danger');
    exit;
}

// Get user ID from URL
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$userId) {
    setMessage('معرف المستخدم غير صحيح', 'danger');
    header('Location: users.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get user details
    $query = "
        SELECT u.*, d.department_name, p.project_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN projects p ON u.project_id = p.project_id
        WHERE u.user_id = ? AND u.is_active = 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setMessage('المستخدم غير موجود أو تم حذفه', 'danger');
        header('Location: users.php');
        exit;
    }
    
    // Check permissions using Admin Teams System
    $currentUserRole = getUserRole();
    
    // Only Super Admin can edit users
    if ($currentUserRole !== 'super_admin') {
        setMessage('غير مصرح لك بتعديل المستخدمين', 'danger');
        header('Location: users.php');
        exit;
    }
    
    // Get user's current permissions
    $permQuery = "
        SELECT p.permission_id, p.permission_name, p.permission_display_name, p.permission_category
        FROM user_permissions up
        JOIN permissions p ON up.permission_id = p.permission_id
        WHERE up.user_id = ? AND up.is_active = 1 AND p.is_active = 1
        ORDER BY p.permission_category, p.permission_display_name
    ";
    
    $permStmt = $conn->prepare($permQuery);
    $permStmt->execute([$userId]);
    $userPermissions = $permStmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Edit user error: " . $e->getMessage());
    setMessage('حدث خطأ في تحميل تفاصيل المستخدم', 'danger');
    header('Location: users.php');
    exit;
}

include 'includes/header.php';
?>

<!-- Admin Teams JavaScript -->
<script src="js/admin_teams_edit.js"></script>

<!-- Permissions CSS -->
<style>
/* Enhanced form styling */
.form-group label {
    font-weight: bold;
    color: #333;
}

.required {
    color: #d9534f;
}

/* Button styling */
.btn-lg {
    padding: 10px 20px;
    margin: 5px;
}

/* Panel styling */
.panel-heading h4 {
    margin: 0;
    color: #333;
}

/* Current values highlight */
input[readonly], select[readonly] {
    background-color: #f5f5f5;
}

/* Permissions section styling */
.permissions-section {
    margin: 25px 0;
    position: static !important;
}

.permissions-section .panel {
    border: none;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    overflow: visible !important;
    position: static !important;
}

.permissions-section .panel-heading {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 20px 25px;
    border: none;
    position: static !important;
}

.permissions-section .panel-heading h4 {
    margin: 0;
    font-weight: 600;
    font-size: 18px;
    position: static !important;
    color: white;
}

.permissions-section .panel-body {
    padding: 25px;
    position: static !important;
    background: white;
}

#searchPermissions {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 12px 15px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

#searchPermissions:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    background: white;
}

.permission-counter {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    font-weight: bold;
    text-align: center;
}

#permissionsList {
    position: static !important;
    background: transparent !important;
    padding: 0 !important;
    margin-top: 20px !important;
}

.permission-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    margin: 4px 0;
    border-radius: 6px;
    background: white;
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
}

.permission-item:hover {
    background: #e8f5e8;
    border-color: #28a745;
}

.permission-item input[type="checkbox"] {
    margin-right: 10px;
    transform: scale(1.2);
}

.permission-name {
    font-weight: 600;
    color: #495057;
    flex: 1;
}

.permission-category {
    background: #6c757d;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    margin-left: 8px;
}

.permissions-section * {
    position: static !important;
}

.permissions-section .panel-body > * {
    position: static !important;
}

/* Checkbox styling - exact copy from add_user.php */
.checkbox {
    margin: 15px 0 !important;
    position: static !important;
    display: block !important;
    clear: both !important;
    width: 100% !important;
}

.checkbox label {
    font-weight: normal !important;
    padding: 15px 20px !important;
    background: white !important;
    border-radius: 8px !important;
    border: 2px solid #e9ecef !important;
    cursor: pointer !important;
    display: block !important;
    margin-bottom: 10px !important;
    position: static !important;
    width: 100% !important;
    min-height: 60px !important;
    line-height: 1.5 !important;
    overflow: visible !important;
}

.checkbox label:hover {
    border-color: #667eea !important;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.15) !important;
    background: #f8f9ff !important;
}

.checkbox input[type="checkbox"] {
    width: 18px !important;
    height: 18px !important;
    margin: 0 10px 0 10px !important;
    position: static !important;
    float: right !important;
    clear: none !important;
}

.checkbox strong {
    color: #2d3748 !important;
    display: inline !important;
    position: static !important;
}

.checkbox small {
    color: #6c757d !important;
    font-size: 12px !important;
    display: block !important;
    margin-top: 8px !important;
    position: static !important;
    clear: both !important;
}

/* Current permissions display */
.current-permissions {
    background: #e8f5e8;
    border: 1px solid #28a745;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.current-permissions h5 {
    color: #155724;
    margin-bottom: 10px;
    font-weight: bold;
}

.permission-badge {
    display: inline-block;
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    margin: 2px;
}
</style>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><i class="glyphicon glyphicon-edit"></i> تعديل المستخدم: <?php echo htmlspecialchars($user['full_name']); ?></h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="view_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-info">
                                <i class="glyphicon glyphicon-eye-open"></i> عرض التفاصيل
                            </a>
                            <a href="users.php" class="btn btn-default">
                                <i class="glyphicon glyphicon-arrow-right"></i> العودة للقائمة
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <form id="editUserForm" method="POST">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name">الاسم الكامل <span class="text-danger">*</span></label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" required 
                                           placeholder="أدخل الاسم الكامل" value="<?php echo htmlspecialchars($user['full_name']); ?>" tabindex="2">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">اسم المستخدم <span class="text-danger">*</span></label>
                                    <input type="text" id="username" name="username" class="form-control" required 
                                           placeholder="أدخل اسم المستخدم (أحرف وأرقام فقط)" pattern="[a-zA-Z0-9_]{3,}"
                                           value="<?php echo htmlspecialchars($user['username']); ?>" tabindex="1">
                                    <small class="text-muted">3 أحرف على الأقل، أحرف وأرقام و _ فقط</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">كلمة المرور الجديدة</label>
                                    <input type="password" id="password" name="password" class="form-control" 
                                           placeholder="اتركه فارغاً إذا كنت لا تريد تغيير كلمة المرور" minlength="6" tabindex="4">
                                    <small class="text-muted">اختياري - 6 أحرف على الأقل إذا تم ملؤه</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">البريد الإلكتروني <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" required 
                                           placeholder="example@domain.com" value="<?php echo htmlspecialchars($user['email']); ?>" tabindex="3">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_id">القسم</label>
                                    <select id="department_id" name="department_id" class="form-control" tabindex="5">
                                        <option value="">اختر القسم</option>
                                        <!-- Options will be loaded via AJAX -->
                                    </select>
                                    <small class="text-muted">مطلوب للمشرفين فقط</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="project_id">المشروع</label>
                                    <select id="project_id" name="project_id" class="form-control" tabindex="6">
                                        <option value="">اختر المشروع (اختياري)</option>
                                        <!-- Options will be loaded via AJAX -->
                                    </select>
                                    <small class="text-muted">يمكن ربط المستخدم بمشروع معين</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role">الدور <span class="text-danger">*</span></label>
                                    <select id="role" name="role" class="form-control" required tabindex="7">
                                        <option value="">اختر الدور</option>
                                        <option value="user" <?php echo ($user['role'] === 'user' || empty(trim($user['role']))) ? 'selected' : ''; ?>>مستخدم عادي</option>
                                        <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>مشرف</option>
                                        <option value="super_admin" <?php echo ($user['role'] === 'super_admin') ? 'selected' : ''; ?>>مشرف عام</option>
                                    </select>
                                </div>
                        
                        <!-- Parent Admin Field (for Sub Admins) -->
                        <div class="form-group" id="parentAdminGroup" style="display: none;">
                            <label for="parentAdminId" class="col-sm-3 control-label">
                                المدير المباشر <span class="text-danger">*</span>
                            </label>
                            <div class="col-sm-9">
                                <select name="parent_admin_id" id="parentAdminId" class="form-control">
                                    <option value="">-- اختر المدير المباشر --</option>
                                </select>
                                                                 <small class="help-block">اختر المدير الرئيسي الذي سيشرف على هذا المدير الفرعي</small>
                            </div>
                        </div>
                        
                        <!-- Admin Type Display -->
                        <div class="form-group" id="adminTypeDisplay" style="display: none;">
                            <div class="col-sm-12">
                                <div class="alert alert-info" style="margin-bottom: 0;">
                                    <strong>نوع المدير:</strong> <span id="adminTypeText">مدير رئيسي</span>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        // Set current parent admin ID for JavaScript
                        $(document).ready(function() {
                            $('#parentAdminId').data('current-parent', '<?php echo $user['parent_admin_id'] ?? ''; ?>');
                        });
                        </script>
                        
                        <!-- Admin Teams Fix for Department Selector -->
                        <script src="fix_edit_user_department.js"></script>
                            </div>
                            <div class="col-md-6">
                                <!-- مساحة فارغة للتوازن -->
                            </div>
                        </div>
                        
                        <!-- Current Permissions Display -->
                        <div class="current-permissions">
                            <h5><i class="glyphicon glyphicon-lock"></i> الصلاحيات الحالية:</h5>
                            <div id="currentPermissionsDisplay">
                                <?php if (!empty($userPermissions)): ?>
                                    <?php foreach ($userPermissions as $perm): ?>
                                        <span class="permission-badge"><?php echo htmlspecialchars($perm['permission_display_name']); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">لا توجد صلاحيات مخصصة للمستخدم</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Permissions Selection Section -->
                        <div id="permissionsSection" class="permissions-section">
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h4><i class="glyphicon glyphicon-lock"></i> تعديل الصلاحيات</h4>
                                </div>
                                <div class="panel-body">
                                    <div class="alert alert-info">
                                        <strong>ملاحظة:</strong> يمكنك تعديل صلاحيات المستخدم. الصلاحيات الأساسية للدور ستُمنح تلقائياً.
                                    </div>
                                    
                                    <!-- Search and Counter -->
                                    <div class="row" style="margin-bottom: 15px;">
                                        <div class="col-md-8">
                                            <input type="text" id="searchPermissions" class="form-control" 
                                                   placeholder="🔍 البحث في الصلاحيات...">
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <div class="permission-counter">
                                                <strong>المختارة: <span id="selectedCount">0</span></strong>
                                                من <span id="totalCount">0</span> صلاحية
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="text-center" style="margin-bottom: 15px;">
                                        <button type="button" class="btn btn-success btn-sm" id="selectAll">
                                            <i class="glyphicon glyphicon-check"></i> تحديد الكل
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm" id="clearAll">
                                            <i class="glyphicon glyphicon-unchecked"></i> إلغاء الكل
                                        </button>

                                    </div>

                                    <!-- Permissions List -->
                                    <div id="permissionsList" style="display: none;">
                                        <!-- Permissions will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-success btn-lg" tabindex="8">
                                <i class="glyphicon glyphicon-save"></i> حفظ التعديلات
                            </button>
                            <a href="view_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-default btn-lg" tabindex="9">
                                <i class="glyphicon glyphicon-remove"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load projects and departments on page load
    loadProjects();
    loadDepartments();
    loadPermissions();
    
    // Set current values
    const currentDepartmentId = <?php echo $user['department_id'] ? $user['department_id'] : 'null'; ?>;
    const currentProjectId = <?php echo $user['project_id'] ? $user['project_id'] : 'null'; ?>;
    
    // Set project value after loading
    setTimeout(function() {
        if (currentProjectId) {
            $('#project_id').val(currentProjectId);
        }
    }, 500);
    
    // Handle role selection change
    $('#role').on('change', function() {
        updateDepartmentRequirement();
        loadDefaultPermissionsForRole(); // Load default permissions for selected role
        handleRoleChange(); // Handle admin team fields
    });
    
    // Handle admin team fields based on role
    function handleRoleChange() {
        const selectedRole = $('#role').val();
        const $parentAdminGroup = $('#parentAdminGroup');
        const $adminTypeDisplay = $('#adminTypeDisplay');
        const $parentAdminSelect = $('#parentAdminId');
        
        if (selectedRole === 'admin') {
            $parentAdminGroup.show();
            $adminTypeDisplay.show();
            loadHeadAdmins();
        } else {
            $parentAdminGroup.hide();
            $adminTypeDisplay.hide();
            $parentAdminSelect.empty().append('<option value="">-- اختر المدير المباشر --</option>');
        }
    }
    
    // Load head admins for parent admin dropdown
    function loadHeadAdmins() {
        const departmentId = $('#department').val();
        const currentUserId = $('input[name="user_id"]').val(); // Exclude current user
        
        if (!departmentId) {
            return;
        }
        
        $.ajax({
            url: 'php_action/get_head_admins.php',
            method: 'POST',
            data: {
                department_id: departmentId,
                exclude_user_id: currentUserId
            },
            dataType: 'json',
            success: function(response) {
                const $select = $('#parentAdminId');
                $select.empty().append('<option value="">-- اختر المدير المباشر --</option>');
                
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(admin) {
                        $select.append(`<option value="${admin.user_id}">${admin.full_name} (${admin.username})</option>`);
                    });
                } else {
                    $select.append('<option value="" disabled>لا يوجد مديرين رئيسيين في هذا القسم</option>');
                }
                
                // Set current parent admin if editing
                const currentParentId = '<?php echo $user['parent_admin_id'] ?? ''; ?>';
                if (currentParentId) {
                    $select.val(currentParentId);
                }
                
                updateAdminTypeDisplay();
            },
            error: function() {
                console.error('خطأ في تحميل المديرين الرئيسيين');
            }
        });
    }
    
    // Update admin type display based on parent admin selection
    $('#parentAdminId').on('change', function() {
        updateAdminTypeDisplay();
    });
    
    function updateAdminTypeDisplay() {
        const parentAdminId = $('#parentAdminId').val();
        const $adminTypeText = $('#adminTypeText');
        
        if (parentAdminId) {
            const parentName = $('#parentAdminId option:selected').text();
            $adminTypeText.text(`مدير فرعي تحت: ${parentName}`);
        } else {
            $adminTypeText.text('مدير رئيسي مستقل');
        }
    }
    
    // Load head admins when department changes (for admin role)
    $('#department').on('change', function() {
        if ($('#role').val() === 'admin') {
            loadHeadAdmins();
        }
    });
    
    
    // Force update department requirement after page fully loads (for edit mode)
    setTimeout(function() {
        console.log('🔄 Forcing department requirement update for edit mode...');
        updateDepartmentRequirement();
    }, 500);

    
    // Global variables for permissions
    let allPermissions = [];
    // Ensure currentUserPermissions contains only integers
    let currentUserPermissions = <?php echo json_encode(array_map('intval', array_column($userPermissions, 'permission_id'))); ?>;
    
    // Debug: Show current user permissions
    console.log('🔍 Current user permissions loaded:', currentUserPermissions);
    console.log('📊 Total user permissions count:', currentUserPermissions.length);
    
    if (currentUserPermissions.length === 0) {
        console.warn('⚠️ المستخدم ليس لديه صلاحيات محددة!');
    } else {
        console.log('✅ المستخدم لديه', currentUserPermissions.length, 'صلاحية');
        currentUserPermissions.forEach(permId => {
            console.log('  - Permission ID:', permId);
        });
    }
    
    function updateDepartmentRequirement() {
        const role = $('#role').val();
        const departmentSelect = $('#department_id');
        const projectSelect = $('#project_id');
        
        if (role === 'admin') {
            projectSelect.prop('disabled', false);
            departmentSelect.parent().find('label').html('القسم <span class="text-danger">*</span>');
            departmentSelect.prop('required', true);
            // Enable department only if project is selected
            if (projectSelect.val()) {
                departmentSelect.prop('disabled', false);
            }
        } else if (role === 'user') {
            // Regular users can have department for access control
            projectSelect.prop('disabled', false);
            departmentSelect.parent().find('label').html('القسم <small class="text-muted">(اختياري)</small>');
            departmentSelect.prop('required', false);
            // Enable department only if project is selected
            if (projectSelect.val()) {
                departmentSelect.prop('disabled', false);
            }
        } else if (role === 'super_admin') {
            // Super admin doesn't need project or department
            projectSelect.prop('disabled', true).val('');
            departmentSelect.prop('disabled', true).prop('required', false).val('');
            departmentSelect.parent().find('label').html('القسم');
        } else {
            // Other roles (empty, unknown) - disable project and department
            projectSelect.prop('disabled', true).val('');
            departmentSelect.prop('disabled', true).prop('required', false).val('');
            departmentSelect.parent().find('label').html('القسم');
        }
    }
    
    // Load projects function
    function loadProjects() {
        $.get('php_action/get_projects.php')
            .done(function(response) {
                if (response.success) {
                    let options = '<option value="">اختر المشروع</option>';
                    let userProjectId = null;
                    
                    response.data.forEach(function(project) {
                        options += `<option value="${project.project_id}">${project.project_name}</option>`;
                    });
                    $('#project_id').html(options);
                    
                    // If user has department, find and select the corresponding project
                    <?php if ($user['department_id']): ?>
                        loadUserProject(<?php echo $user['department_id']; ?>);
                    <?php endif; ?>
                }
            })
            .fail(function() {
                console.error('فشل في تحميل المشاريع');
            });
    }
    
    // Load user's project based on their department
    function loadUserProject(departmentId) {
        $.get('php_action/get_department_project.php', { department_id: departmentId })
            .done(function(response) {
                if (response.success && response.project_id) {
                    $('#project_id').val(response.project_id);
                    // Load departments for this project
                    loadDepartmentsByProject(response.project_id, departmentId);
                }
            });
    }
    
    // Load departments function
    function loadDepartments() {
        const selectedDepartmentId = <?php echo $user['department_id'] ? $user['department_id'] : 'null'; ?>;
        
        $.get('php_action/get_unique_departments.php')
            .done(function(response) {
                if (response.success) {
                    let options = '<option value="">اختر القسم</option>';
                    response.data.forEach(function(dept) {
                        const selected = selectedDepartmentId == dept.department_id ? 'selected' : '';
                        options += `<option value="${dept.department_id}" ${selected}>${dept.department_name}</option>`;
                    });
                    $('#department_id').html(options);
                    
                    // Update requirement after loading
                    updateDepartmentRequirement();
                } else {
                    $('#department_id').html('<option value="">لا توجد أقسام متاحة</option>');
                }
            })
            .fail(function() {
                $('#department_id').html('<option value="">خطأ في تحميل الأقسام</option>');
            });
    }
    
    // Load permissions function
    function loadPermissions() {
        $.get('php_action/get_permissions.php')
            .done(function(response) {
                // === JAVASCRIPT DEBUG ===
                console.log("🔍 AJAX Response received:");
                console.log("- Type:", typeof response);
                console.log("- Raw response:", response);
                
                if (typeof response === "string") {
                    console.log("⚠️ Response is string, trying to parse...");
                    try {
                        response = JSON.parse(response);
                        console.log("✅ Parsed successfully:", response);
                    } catch (e) {
                        console.error("❌ JSON Parse failed:", e);
                        console.log("Raw string:", response);
                    }
                }
                
                console.log("- Success:", response.success);
                console.log("- Message:", response.message);
                console.log("- Permissions data:", response.data);
                console.log("- Permissions count:", response.total_count);
                
                if (!response.data) {
                    console.error("❌ PERMISSIONS DATA IS MISSING!");
                    console.log("Full response object:", response);
                }
                // === END DEBUG ===
                // Validate response structure - this is permissions data, not user data
                if (!response.data) {
                    console.error("❌ response.data is missing!");
                    console.log("Available keys:", Object.keys(response));
                    showAlert("خطأ: بيانات الصلاحيات مفقودة في الاستجابة", "danger");
                    return;
                }


                if (response.success) {
                    allPermissions = response.data;
                    console.log('✅ تم تحميل', allPermissions.length, 'صلاحية');
                    renderPermissions(allPermissions);
                    console.log('✅ Permissions rendered with current user selections preserved');
                } else {
                    showAlert('فشل في تحميل الصلاحيات: ' + response.message, 'danger');
                }
            })
            .fail(function() {
                showAlert('فشل في تحميل الصلاحيات', 'danger');
            });
    }
    
        // Render permissions function - Exact copy from add_user.php style
    function renderPermissions(permissions) {
        console.log('🎨 بدء رسم الصلاحيات:');
        console.log('📊 إجمالي الصلاحيات المستلمة:', permissions.length);
        console.log('🔍 صلاحيات المستخدم الحالية:', currentUserPermissions);
        
        let html = '';
        
        // Group by category
        const grouped = {};
        permissions.forEach(perm => {
            if (!grouped[perm.permission_category]) {
                grouped[perm.permission_category] = [];
            }
            grouped[perm.permission_category].push(perm);
        });
        
        console.log('📂 الفئات المجمعة:', Object.keys(grouped));
        
        // Category names with icons
        const categoryNames = {
            'licenses': '📜 التراخيص',
            'personal_licenses': '👤 رخص القيادة الشخصية',
            'vehicle_licenses': '🚗 رخص المركبات',
            'users': '👥 المستخدمون', 
            'departments': '🏢 الأقسام',
            'projects': '📋 المشاريع',
            'reports': '📊 التقارير',
            'analytics': '📈 الإحصائيات',
            'system': '🔧 النظام'
        };
        
        let selectedCount = 0;
        
        // Render each category
        Object.keys(grouped).forEach(category => {
            html += `<h5 style="color: #4a5568; font-weight: 700; font-size: 16px; margin: 30px 0 20px 0 !important; padding: 12px 15px; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 8px; border-right: 4px solid #667eea; position: static !important; display: block !important; clear: both !important;">${categoryNames[category] || category}</h5>`;
            html += '<div class="row">';
            
            grouped[category].forEach(perm => {
                // CRITICAL FIX: Ensure both values are integers for comparison
                const permId = parseInt(perm.permission_id);
                const userPermIds = currentUserPermissions.map(id => parseInt(id));
                const isSelected = userPermIds.includes(permId);
                
                // Debug logging for troubleshooting
                console.log(`🔍 Checking permission ID ${permId} (${perm.permission_display_name})`);
                console.log(`  - API returned type: ${typeof perm.permission_id}, value: ${perm.permission_id}`);
                console.log(`  - Converted to: ${permId} (type: ${typeof permId})`);
                console.log(`  - User permissions: [${userPermIds.join(', ')}]`);
                console.log(`  - Is selected: ${isSelected}`);
                
                if (isSelected) {
                    selectedCount++;
                    console.log(`✅ PERMISSION SELECTED: ${perm.permission_display_name}`);
                } else {
                    console.log(`❌ Permission not selected: ${perm.permission_display_name}`);
                }
                
                html += `
                    <div class="col-md-6 col-sm-12" style="margin-bottom: 10px;">
                        <div class="checkbox" style="margin: 15px 0 !important; position: static !important; display: block !important; clear: both !important; width: 100% !important;">
                            <label style="font-weight: normal !important; padding: 15px 20px !important; background: white !important; border-radius: 8px !important; border: 2px solid #e9ecef !important; cursor: pointer !important; display: block !important; margin-bottom: 10px !important; position: static !important; width: 100% !important; min-height: 60px !important; line-height: 1.5 !important; overflow: visible !important;">
                                <input type="checkbox" name="permissions[]" value="${permId}" 
                                       class="permission-checkbox" ${isSelected ? 'checked' : ''}
                                       data-permission-name="${perm.permission_name}"
                                       style="width: 18px !important; height: 18px !important; margin: 0 10px 0 10px !important; position: static !important; float: right !important; clear: none !important;"> 
                                <strong style="color: #2d3748 !important; display: inline !important; position: static !important;">${perm.permission_display_name}</strong>
                                ${perm.permission_description ? '<small style="color: #6c757d !important; font-size: 12px !important; display: block !important; margin-top: 8px !important; position: static !important; clear: both !important;">' + perm.permission_description + '</small>' : ''}
                            </label>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        });
        
        // Set the HTML and show
        $('#permissionsList').html(html);
        $('#permissionsList').show();
        $('#permissionsList').css('display', 'block');
        $('#permissionsList').removeClass('hidden');
        
        console.log(`🎨 تم إنشاء HTML للصلاحيات - محدد: ${selectedCount} من ${permissions.length}`);
        
        // Final verification
        setTimeout(() => {
            const checkedBoxes = $('input[name="permissions[]"]:checked').length;
            const totalBoxes = $('input[name="permissions[]"]').length;
            console.log(`📋 نتيجة نهائية: ${checkedBoxes} محدد من ${totalBoxes} إجمالي`);
            
            if (checkedBoxes === 0 && currentUserPermissions.length > 0) {
                console.error('🚨 خطأ: لم يتم تحديد أي صلاحيات رغم وجود صلاحيات للمستخدم!');
                console.error('🔍 للتشخيص: تحقق من تطابق الـ IDs في console أعلاه');
            } else {
                console.log('✅ تم تحديد الصلاحيات بنجاح!');
            }
        }, 100);
        
        // Update counters
        updatePermissionCounters();
        
        // Add event listeners
        $('input[name="permissions[]"]').on('change', updatePermissionCounters);
        
        console.log('✅ انتهى رسم الصلاحيات');
    }

    function updatePermissionCounters() {
        const total = allPermissions.length;
        const selected = $('input[name="permissions[]"]:checked').length;
        
        $('#totalCount').text(total);
        $('#selectedCount').text(selected);
    }
    
    // Search functionality
    $('#searchPermissions').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        if (searchTerm === '') {
            renderPermissions(allPermissions);
        } else {
            const filtered = allPermissions.filter(perm => 
                perm.permission_display_name.toLowerCase().includes(searchTerm) ||
                perm.permission_name.toLowerCase().includes(searchTerm) ||
                perm.permission_category.toLowerCase().includes(searchTerm)
            );
            renderPermissions(filtered);
        }
    });
    
    // Select all permissions
    $('#selectAll').on('click', function() {
        $('input[name="permissions[]"]').prop('checked', true);
        updatePermissionCounters();
    });
    
    // Clear all permissions
    $('#clearAll').on('click', function() {
        $('input[name="permissions[]"]').prop('checked', false);
        updatePermissionCounters();
    });
    
    // Load default permissions for role
    $('#loadDefaultPermissions').on('click', function() {
        loadDefaultPermissionsForRole(); // Load default permissions for selected role
    });
    
    function loadDefaultPermissionsForRole() {
        
        const role = $('#role').val();
        if (!role) return;
        
        // Define default permissions for each role
        const defaults = {
            'user': [
                'licenses_view',
                'personal_licenses_view',
                'vehicle_licenses_view'
            ],
            'admin': [
                'licenses_view', 'licenses_add', 'licenses_edit', 'licenses_delete',
                'personal_licenses_view', 'personal_licenses_add', 'personal_licenses_edit', 'personal_licenses_delete',
                'vehicle_licenses_view', 'vehicle_licenses_add', 'vehicle_licenses_edit', 'vehicle_licenses_delete',
                'departments_view', 'projects_view'
            ],
            'super_admin': allPermissions.map(p => p.permission_name)
        };
        
        const roleDefaults = defaults[role] || [];
        
        // Clear all first
        $('input[name="permissions[]"]').prop('checked', false);
        
        // Select defaults
        roleDefaults.forEach(permName => {
            const perm = allPermissions.find(p => p.permission_name === permName);
            if (perm) {
                $(`input[name="permissions[]"][value="${perm.permission_id}"]`).prop('checked', true);
            }
        });
        
        updatePermissionCounters();
        showAlert(`تم تحديد الصلاحيات الافتراضية لدور "${role}"`, 'success');
    }
    
    // Form submission
    $('#editUserForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Add selected permissions to form data
        const selectedPermissions = [];
        $('input[name="permissions[]"]:checked').each(function() {
            selectedPermissions.push($(this).val());
        });
        
        // Always send permissions array, even if empty
            formData.append('selected_permissions', JSON.stringify(selectedPermissions));
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fa fa-spinner fa-spin"></i> جاري الحفظ...').prop('disabled', true);
        
        $.ajax({
            url: 'php_action/edit_user.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    

                    
                    setTimeout(function() {
                        window.location.href = 'view_user.php?id=' + <?php echo $user['user_id']; ?>;
                    }, 2000);
                } else {
                    showAlert(response.error, 'danger');
                    submitBtn.html(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showAlert('حدث خطأ في الخادم', 'danger');
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });
    

    
    // Show alert function
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible" style="margin-top: 15px;">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                ${message}
            </div>
        `;
        $('.content-wrapper').prepend(alertHtml);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    }
});
// Admin Teams System JavaScript for Edit User
function handleRoleChange() {
    const selectedRole = $('#role').val();
    const $parentAdminGroup = $('#parentAdminGroup');
    const $adminTypeDisplay = $('#adminTypeDisplay');
    const $parentAdminSelect = $('#parentAdminId');
    
    if (selectedRole === 'admin') {
        $parentAdminGroup.show();
        $adminTypeDisplay.show();
        loadHeadAdmins();
    } else {
        $parentAdminGroup.hide();
        $adminTypeDisplay.hide();
        $parentAdminSelect.empty().append('<option value="">-- اختر المدير المباشر --</option>');
    }
}

// Load head admins for parent admin dropdown
function loadHeadAdmins() {
    const departmentId = $('#department').val();
    const currentUserId = $('input[name="user_id"]').val(); // Exclude current user
    
    if (!departmentId) {
        $('#parentAdminId').empty().append('<option value="">اختر القسم أولاً</option>');
        return;
    }
    
    $.ajax({
        url: 'php_action/get_head_admins.php',
        method: 'POST',
        data: {
            department_id: departmentId,
            exclude_user_id: currentUserId
        },
        dataType: 'json',
        success: function(response) {
            const $select = $('#parentAdminId');
            $select.empty().append('<option value="">-- اختر المدير المباشر --</option>');
            
            if (response.success && response.data.length > 0) {
                response.data.forEach(function(admin) {
                    $select.append(`<option value="${admin.user_id}">${admin.full_name} (${admin.username})</option>`);
                });
            } else {
                $select.append('<option value="" disabled>لا يوجد مديرين رئيسيين في هذا القسم</option>');
            }
            
            // Set current parent admin if editing
            const currentParentId = '<?php echo $user['parent_admin_id'] ?? ''; ?>';
            if (currentParentId) {
                $select.val(currentParentId);
            }
            
            updateAdminTypeDisplay();
        },
        error: function() {
            console.error('خطأ في تحميل المديرين الرئيسيين');
            $('#parentAdminId').empty().append('<option value="">خطأ في التحميل</option>');
        }
    });
}

// Update admin type display based on parent admin selection
function updateAdminTypeDisplay() {
    const parentAdminId = $('#parentAdminId').val();
    const $adminTypeText = $('#adminTypeText');
    
    if (parentAdminId) {
        const parentName = $('#parentAdminId option:selected').text();
        $adminTypeText.text(`مدير فرعي تحت: ${parentName}`);
    } else {
        $adminTypeText.text('مدير رئيسي مستقل');
    }
}

// Event handlers for Admin Teams
$('#role').on('change', function() {
    handleRoleChange();
});

$('#parentAdminId').on('change', function() {
    updateAdminTypeDisplay();
});

$('#department_id').on('change', function() {
    if ($('#role').val() === 'admin') {
        loadHeadAdmins();
    }
});

// Initialize on page load
$(document).ready(function() {
    setTimeout(function() {
        handleRoleChange();
    }, 1000); // Wait for other initialization
});

// Admin Teams System JavaScript for Edit User
function handleRoleChange() {
    const selectedRole = $('#role').val();
    const $parentAdminGroup = $('#parentAdminGroup');
    const $adminTypeDisplay = $('#adminTypeDisplay');
    const $parentAdminSelect = $('#parentAdminId');
    
    if (selectedRole === 'admin') {
        $parentAdminGroup.show();
        $adminTypeDisplay.show();
        loadHeadAdmins();
    } else {
        $parentAdminGroup.hide();
        $adminTypeDisplay.hide();
        $parentAdminSelect.empty().append('<option value="">-- اختر المدير المباشر --</option>');
    }
}

// Load head admins for parent admin dropdown
function loadHeadAdmins() {
    const departmentId = $('#department_id').val();
    const currentUserId = $('input[name="user_id"]').val(); // Exclude current user
    
    console.log('🔍 Loading head admins for department:', departmentId);
    
    if (!departmentId || departmentId === '') {
        console.log('❌ No department selected');
        $('#parentAdminId').empty().append('<option value="">اختر القسم أولاً</option>');
        return;
    }
    
    // Show loading state
    $('#parentAdminId').empty().append('<option value="">جاري التحميل...</option>');
    
    $.ajax({
        url: 'php_action/get_head_admins.php',
        method: 'POST',
        data: {
            department_id: departmentId,
            exclude_user_id: currentUserId || 0
        },
        dataType: 'json',
        success: function(response) {
            console.log('✅ Head admins response:', response);
            
            const $select = $('#parentAdminId');
            $select.empty().append('<option value="">-- اختر المدير المباشر --</option>');
            
            if (response.success && response.data && response.data.length > 0) {
                response.data.forEach(function(admin) {
                    $select.append(`<option value="${admin.user_id}">${admin.full_name} (${admin.username})</option>`);
                });
                console.log(`📋 Loaded ${response.data.length} head admins`);
            } else {
                $select.append('<option value="" disabled>لا يوجد مديرين رئيسيين في هذا القسم</option>');
                console.log('ℹ️ No head admins found for this department');
            }
            
            // Set current parent admin if editing
            const currentParentId = $('#parentAdminId').data('current-parent') || '';
            if (currentParentId) {
                console.log('🎯 Setting current parent:', currentParentId);
                $select.val(currentParentId);
            }
            
            updateAdminTypeDisplay();
        },
        error: function(xhr, status, error) {
            console.error('❌ Error loading head admins:', {xhr, status, error});
            $('#parentAdminId').empty().append('<option value="">خطأ في التحميل - حاول مرة أخرى</option>');
        }
    });
}

// Update admin type display based on parent admin selection
function updateAdminTypeDisplay() {
    const parentAdminId = $('#parentAdminId').val();
    const $adminTypeText = $('#adminTypeText');
    
    if (parentAdminId) {
        const parentName = $('#parentAdminId option:selected').text();
        $adminTypeText.text(`مدير فرعي تحت: ${parentName}`);
    } else {
        $adminTypeText.text('مدير رئيسي مستقل');
    }
}

// Event handlers for Admin Teams
$('#role').on('change', function() {
    handleRoleChange();
});

$('#parentAdminId').on('change', function() {
    updateAdminTypeDisplay();
});

$('#department_id').on('change', function() {
    if ($('#role').val() === 'admin') {
        loadHeadAdmins();
    }
});

// Fix department selector for Admin Teams
$('#department_id').off('change.adminteams').on('change.adminteams', function() {
    console.log('🔧 Department changed for admin teams:', $(this).val());
    if ($('#role').val() === 'admin') {
        loadHeadAdmins();
    }
});

</script>

<!-- Admin Teams Fix for Department Selector -->
<script src="fix_edit_user_department.js"></script>

<?php include 'includes/footer.php'; ?> 