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

<style>
/* General permissions sections styling */
.permissions-section { 
    margin: 25px 0; 
}

.permissions-section .panel { 
    border-radius: 12px; 
    box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
    overflow: hidden;
}

.permissions-section .panel-heading { 
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
    color: white; 
    padding: 20px 25px; 
    border: none; 
}

.permissions-section .panel-body { 
    padding: 25px; 
    background: #fafafa;
}

/* Improved Arabic checkbox styling */
.checkbox { 
    margin: 10px 0; 
    position: relative;
}

.checkbox label { 
    font-weight: normal; 
    padding: 12px 15px; 
    background: white; 
    border-radius: 8px; 
    border: 2px solid #e9ecef; 
    cursor: pointer; 
    display: block;
    min-height: 50px;
    transition: all 0.3s ease;
    line-height: 1.4;
    font-size: 14px;
    position: relative;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.checkbox label:hover { 
    border-color: #667eea; 
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.15); 
    background: #f8f9ff; 
    transform: translateY(-1px);
}

.checkbox label::after {
    content: "";
    display: table;
    clear: both;
}

.checkbox input[type="checkbox"] { 
    width: 18px; 
    height: 18px; 
    margin: 0 0 0 10px;
    cursor: pointer;
    float: right;
    margin-top: 3px;
}

.checkbox input[type="checkbox"]:checked + label,
.checkbox label:has(input[type="checkbox"]:checked) {
    background: linear-gradient(135deg, #e8f5e8 0%, #f0fdf4 100%);
    border-color: #28a745;
    color: #155724;
}

/* Grid improvements for better layout */
.permissions-section .row {
    margin: 0 -10px;
}

.permissions-section .col-md-6 {
    padding: 0 10px;
}

/* Text content styling */
.checkbox label .text-content {
    overflow: hidden;
    text-align: right;
    direction: rtl;
    padding-right: 35px;
}

/* Small text styling */
.checkbox label small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
    line-height: 1.3;
}

/* Better responsive design */
@media (max-width: 768px) {
    .checkbox label {
        padding: 10px 12px;
        min-height: 45px;
        font-size: 13px;
    }
    
    .checkbox input[type="checkbox"] {
        width: 16px;
        height: 16px;
        margin: 0 0 0 8px;
        margin-top: 2px;
    }
    
    .checkbox label .text-content {
        padding-right: 30px;
    }
    
    .permissions-section .panel-body {
        padding: 15px;
    }
}

/* Counter and button styling */
.permissions-section .btn {
    margin: 0 5px;
    border-radius: 6px;
    font-size: 13px;
    padding: 6px 12px;
}

/* Search input styling */
#searchPermissions {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 10px 15px;
    font-size: 14px;
    direction: rtl;
    text-align: right;
}

#searchPermissions:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

/* Grid container improvements */
#projectsGrid, #departmentsGrid {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    background: white;
    max-height: 350px;
    overflow-y: auto;
}

/* Scrollbar styling */
#projectsGrid::-webkit-scrollbar, 
#departmentsGrid::-webkit-scrollbar,
#permissionsList::-webkit-scrollbar {
    width: 8px;
}

#projectsGrid::-webkit-scrollbar-track,
#departmentsGrid::-webkit-scrollbar-track,
#permissionsList::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

#projectsGrid::-webkit-scrollbar-thumb,
#departmentsGrid::-webkit-scrollbar-thumb,
#permissionsList::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

#projectsGrid::-webkit-scrollbar-thumb:hover,
#departmentsGrid::-webkit-scrollbar-thumb:hover,
#permissionsList::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
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
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name">الاسم الكامل <span class="text-danger">*</span></label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" required 
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">اسم المستخدم <span class="text-danger">*</span></label>
                                    <input type="text" id="username" name="username" class="form-control" required 
                                           value="<?php echo htmlspecialchars($user['username']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">كلمة المرور الجديدة</label>
                                    <input type="password" id="password" name="password" class="form-control" 
                                           placeholder="اتركه فارغاً إذا كنت لا تريد تغيير كلمة المرور">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">البريد الإلكتروني <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" required 
                                           value="<?php echo htmlspecialchars($user['email']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_id">القسم</label>
                                    <select id="department_id" name="department_id" class="form-control">
                                        <option value="">اختر القسم</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="project_id">المشروع</label>
                                    <select id="project_id" name="project_id" class="form-control">
                                        <option value="">اختر المشروع</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role">الدور <span class="text-danger">*</span></label>
                                    <select id="role" name="role" class="form-control" required>
                                        <option value="">اختر الدور</option>
                                        <option value="user" <?php echo ($user['role'] === 'user') ? 'selected' : ''; ?>>مستخدم عادي</option>
                                        <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>مشرف</option>
                                        <option value="super_admin" <?php echo ($user['role'] === 'super_admin') ? 'selected' : ''; ?>>مشرف عام</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Projects Section -->
                        <div id="projectPermissionsSection" class="permissions-section" style="display: none;">
                            <div class="panel panel-warning">
                                <div class="panel-heading">
                                    <h4><i class="glyphicon glyphicon-folder-open"></i> المشاريع المسموحة</h4>
                                </div>
                                <div class="panel-body">
                                                            <div class="row" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                        <div class="col-md-6">
                                <strong style="color: #495057;">المشاريع المختارة: <span id="selectedProjectsCount" style="color: #28a745;">0</span></strong>
                                من <span id="totalProjectsCount" style="color: #6c757d;">0</span> مشروع
                                        </div>
                                        <div class="col-md-6 text-left">
                                <button type="button" class="btn btn-success btn-sm" id="selectAllProjects">
                                    <i class="glyphicon glyphicon-check"></i> تحديد الكل
                                </button>
                                <button type="button" class="btn btn-warning btn-sm" id="clearAllProjects">
                                    <i class="glyphicon glyphicon-remove"></i> إلغاء الكل
                                </button>
                                        </div>
                                    </div>
                                                        <div id="projectsGrid">
                        <div class="text-center" style="padding: 40px; color: #6c757d;">
                            <i class="fa fa-spinner fa-spin fa-2x" style="color: #28a745;"></i>
                            <br><br>
                            <strong>جاري تحميل المشاريع...</strong>
                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Departments Section -->
                        <div id="departmentPermissionsSection" class="permissions-section" style="display: none;">
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h4><i class="glyphicon glyphicon-th-large"></i> الأقسام المسموحة</h4>
                                </div>
                                <div class="panel-body">
                                                            <div class="row" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                        <div class="col-md-6">
                                <strong style="color: #495057;">الأقسام المختارة: <span id="selectedDepartmentsCount" style="color: #17a2b8;">0</span></strong>
                                من <span id="totalDepartmentsCount" style="color: #6c757d;">0</span> قسم
                                        </div>
                                        <div class="col-md-6 text-left">
                                <button type="button" class="btn btn-success btn-sm" id="selectAllDepartments">
                                    <i class="glyphicon glyphicon-check"></i> تحديد الكل
                                </button>
                                <button type="button" class="btn btn-info btn-sm" id="clearAllDepartments">
                                    <i class="glyphicon glyphicon-remove"></i> إلغاء الكل
                                </button>
                                        </div>
                                    </div>
                                                        <div id="departmentsGrid">
                        <div class="text-center" style="padding: 40px; color: #6c757d;">
                            <i class="fa fa-spinner fa-spin fa-2x" style="color: #17a2b8;"></i>
                            <br><br>
                            <strong>جاري تحميل الأقسام...</strong>
                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Permissions Section -->
                        <div id="permissionsSection" class="permissions-section">
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h4><i class="glyphicon glyphicon-lock"></i> تعديل الصلاحيات</h4>
                                </div>
                                <div class="panel-body">
                                                            <div class="row" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                        <div class="col-md-8">
                                            <input type="text" id="searchPermissions" class="form-control" placeholder="🔍 البحث في الصلاحيات...">
                                        </div>
                                        <div class="col-md-4 text-center">
                                <strong style="color: #495057;">المختارة: <span id="selectedCount" style="color: #28a745;">0</span></strong>
                                من <span id="totalCount" style="color: #6c757d;">0</span> صلاحية
                                        </div>
                                    </div>
                                    <div class="text-center" style="margin-bottom: 15px;">
                            <button type="button" class="btn btn-success btn-sm" id="selectAll">
                                <i class="glyphicon glyphicon-check"></i> تحديد الكل
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" id="clearAll">
                                <i class="glyphicon glyphicon-remove"></i> إلغاء الكل
                            </button>
                                    </div>
                                    <div id="permissionsList" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="glyphicon glyphicon-save"></i> حفظ التعديلات
                            </button>
                            <a href="view_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-default btn-lg">
                                <i class="glyphicon glyphicon-remove"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/fix_projects_loading.js"></script>
<script>
$(document).ready(function() {
    // Check if jQuery is loaded
    console.log('🟢 jQuery loaded successfully, version:', $.fn.jquery);
    console.log('🔍 Starting edit user page initialization...');
    
    // Global variables
    let allPermissions = [];
    let currentUserPermissions = <?php echo json_encode(array_map('intval', array_column($userPermissions, 'permission_id'))); ?>;
    
    console.log('👤 Current user permissions:', currentUserPermissions);
    console.log('👤 User role:', '<?php echo $user["role"]; ?>');
    console.log('👤 User ID:', <?php echo $userId; ?>);
    
    // Add test function for troubleshooting
    window.testDepartments = function() {
        console.log('🧪 Testing departments manually...');
        console.log('Available checkboxes:', $('.department-checkbox').length);
        $('.department-checkbox').each(function() {
            console.log('Checkbox ID:', $(this).val(), 'Checked:', $(this).is(':checked'));
        });
    };
    
    console.log('💡 Run testDepartments() in console to debug departments');
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Load data on page load
    console.log('📡 Loading initial data...');
    loadProjects();
    loadDepartments();
    loadPermissions();
    
    // Show sections for admins
    const userRole = '<?php echo $user["role"]; ?>';
    if (userRole === 'admin' || userRole === 'super_admin') {
        $('#projectPermissionsSection').show();
        $('#departmentPermissionsSection').show();
        
        setTimeout(function() {
            loadUserProjects(<?php echo $userId; ?>);
            loadAllDepartments();
            // loadUserDepartments سيتم استدعاؤها تلقائياً بعد renderDepartmentsGrid
        }, 1000);
    }
    
    // Handle role change
    $('#role').on('change', function() {
        const role = $(this).val();
        
        if (role === 'admin') {
            $('#projectPermissionsSection').show();
            $('#departmentPermissionsSection').show();
            loadProjects();
            loadDepartments();
            loadUserProjects(<?php echo $userId; ?>);
            loadAllDepartments();
        } else {
            $('#projectPermissionsSection').hide();
            $('#departmentPermissionsSection').hide();
        }
    });
    
    // Load departments function
    function loadDepartments() {
        const selectedDepartmentId = <?php echo $user['department_id'] ? $user['department_id'] : 'null'; ?>;
        
        $.get('php_action/get_departments_no_auth.php')
            .done(function(response) {
                if (response.success) {
                    let options = '<option value="">اختر القسم</option>';
                    response.data.forEach(function(dept) {
                        const selected = selectedDepartmentId == dept.department_id ? 'selected' : '';
                        options += `<option value="${dept.department_id}" ${selected}>${dept.department_name}</option>`;
                    });
                    $('#department_id').html(options);
                }
            });
    }
    
    // Load projects function
    function loadProjects() {
        console.log('🔄 Loading projects...');
        const selectedProjectId = <?php echo $user['project_id'] ? $user['project_id'] : 'null'; ?>;
        console.log('User current project ID:', selectedProjectId);
        
        $.ajax({
            url: 'php_action/get_projects_no_auth.php',
            method: 'GET',
            dataType: 'json',
            timeout: 10000, // 10 seconds timeout
            success: function(response) {
                console.log('📊 Projects response:', response);
                if (response.success && response.data && Array.isArray(response.data)) {
                    let options = '<option value="">اختر المشروع</option>';
                    response.data.forEach(function(project) {
                        const selected = selectedProjectId == project.project_id ? 'selected' : '';
                        options += `<option value="${project.project_id}" ${selected}>${project.project_name}</option>`;
                    });
                    $('#project_id').html(options);
                    console.log('✅ Projects loaded successfully:', response.data.length, 'projects');
                } else {
                    console.error('❌ Projects loading failed - Invalid response:', response);
                    $('#project_id').html('<option value="">خطأ في تحميل المشاريع</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Projects API error:', status, error);
                console.error('Response text:', xhr.responseText);
                console.error('Status code:', xhr.status);
                $('#project_id').html('<option value="">خطأ في الاتصال بالخادم</option>');
                }
            });
    }
    
    // Load permissions function
    function loadPermissions() {
        $.get('php_action/get_permissions_no_auth.php')
            .done(function(response) {
                if (response.success) {
                    allPermissions = response.data;
                    renderPermissions(allPermissions);
                }
            });
    }
    
    // Load user projects function
    function loadUserProjects(userId) {
        $.ajax({
            url: 'php_action/get_user_projects.php?user_id=' + userId,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.projects) {
                    renderUserProjectsGrid(response.projects);
                }
            }
        });
    }
    
    // Load all departments function
    function loadAllDepartments() {
        console.log('🔄 Loading all departments...');
        
        $.ajax({
            url: 'php_action/get_departments_no_auth.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('📊 All departments response:', response);
                
                if (response.success && response.data) {
                    console.log('✅ Found', response.data.length, 'departments');
                    renderDepartmentsGrid(response.data);
                } else {
                    console.error('❌ Failed to load departments:', response);
                    $('#departmentsGrid').html('<div class="text-center text-danger">خطأ في تحميل الأقسام</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Departments API error:', status, error);
                console.error('Response:', xhr.responseText);
                $('#departmentsGrid').html('<div class="text-center text-danger">خطأ في الاتصال بالخادم</div>');
            }
        });
    }
    
    // Load user departments function
    function loadUserDepartments(userId) {
        console.log('🔄 Loading user departments for user ID:', userId);
        
        $.ajax({
            url: 'php_action/get_user_departments.php',
            method: 'GET',
            data: { user_id: userId },
            dataType: 'json',
            success: function(response) {
                console.log('📊 User departments response:', response);
                
                if (response.success && response.departments) {
                    // المفتاح الصحيح هو departments وليس data
                    console.log('✅ Processing', response.departments.length, 'departments');
                    
                    response.departments.forEach(function(dept) {
                        if (dept.is_assigned == 1 || dept.is_assigned === '1') {
                            console.log('✅ Setting department as checked:', dept.department_name, 'ID:', dept.department_id);
                                $(".department-checkbox[value='" + dept.department_id + "']").prop("checked", true);
                        } else {
                            console.log('❌ Department not assigned:', dept.department_name, 'ID:', dept.department_id, 'is_assigned:', dept.is_assigned);
                            }
                        });
                    
                        updateDepartmentsCounter();
                    console.log('✅ User departments loaded and selected successfully');
                } else {
                    console.error('❌ Failed to load user departments:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ User departments API error:', status, error);
                console.error('Response:', xhr.responseText);
            }
        });
    }
    
    // Render departments grid function
    function renderDepartmentsGrid(departments) {
        console.log('🎨 Rendering departments grid with', departments.length, 'departments');
        
        let html = '<div class="row">';
        
        departments.forEach(function(department) {
            const safeDepartmentName = escapeHtml(department.department_name);
            const safeDepartmentDesc = department.department_description ? escapeHtml(department.department_description) : '';
            
            html += `
                <div class="col-md-6 col-sm-12" style="margin-bottom: 10px;">
                    <div class="checkbox">
                        <label for="dept_${department.department_id}">
                            <input type="checkbox" name="departments[]" value="${department.department_id}" 
                                   class="department-checkbox" id="dept_${department.department_id}">
                            <div class="text-content">
                                <strong>${safeDepartmentName}</strong>
                                ${safeDepartmentDesc ? '<small>' + safeDepartmentDesc + '</small>' : ''}
                            </div>
                        </label>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        $('#departmentsGrid').html(html);
        $('#totalDepartmentsCount').text(departments.length);
        updateDepartmentsCounter();
        
        console.log('✅ Departments grid rendered successfully');
        
        // بعد رسم الشبكة، نحمل الأقسام المخصصة للمستخدم
        setTimeout(function() {
            console.log('🔄 Starting delayed user departments loading...');
            loadUserDepartments(<?php echo $userId; ?>);
        }, 500);
        
        $('.department-checkbox').on('change', updateDepartmentsCounter);
        
        $('#selectAllDepartments').off('click').on('click', function() {
            $('.department-checkbox').prop('checked', true);
            updateDepartmentsCounter();
        });
        
        $('#clearAllDepartments').off('click').on('click', function() {
            $('.department-checkbox').prop('checked', false);
            updateDepartmentsCounter();
        });
    }
    
    // Update departments counter function
    function updateDepartmentsCounter() {
        const selectedCount = $('.department-checkbox:checked').length;
        const totalCount = $('.department-checkbox').length;
        $('#selectedDepartmentsCount').text(selectedCount);
        console.log('📊 Departments counter updated:', selectedCount, 'of', totalCount, 'selected');
    }
    
    // Render user projects grid function
    function renderUserProjectsGrid(projects) {
        console.log('🎨 Rendering projects grid with', projects.length, 'projects');
        
        let html = '<div class="row">';
        
        projects.forEach(function(project) {
            const isChecked = project.is_assigned == 1 ? 'checked' : '';
            const safeProjectName = escapeHtml(project.project_name);
            const safeProjectDesc = project.description ? escapeHtml(project.description) : '';
            
            html += `
                <div class="col-md-6 col-sm-12" style="margin-bottom: 10px;">
                    <div class="checkbox">
                        <label for="proj_${project.project_id}">
                            <input type="checkbox" name="projects[]" value="${project.project_id}" 
                                   class="project-checkbox" ${isChecked} id="proj_${project.project_id}">
                            <div class="text-content">
                                <strong>${safeProjectName}</strong>
                                ${safeProjectDesc ? '<small>' + safeProjectDesc + '</small>' : ''}
                            </div>
                        </label>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        $('#projectsGrid').html(html);
        $('#totalProjectsCount').text(projects.length);
        updateProjectsCounter();
        
        console.log('✅ Projects grid rendered successfully');
        
        $('.project-checkbox').on('change', updateProjectsCounter);
        
        $('#selectAllProjects').off('click').on('click', function() {
            $('.project-checkbox').prop('checked', true);
            updateProjectsCounter();
        });
        
        $('#clearAllProjects').off('click').on('click', function() {
            $('.project-checkbox').prop('checked', false);
            updateProjectsCounter();
        });
    }
    
    // Update projects counter function
    function updateProjectsCounter() {
        const selectedCount = $('.project-checkbox:checked').length;
        $('#selectedProjectsCount').text(selectedCount);
    }
    
    // Render permissions function
    function renderPermissions(permissions) {
        let html = '';
        const grouped = {};
        
        permissions.forEach(perm => {
            if (!grouped[perm.permission_category]) {
                grouped[perm.permission_category] = [];
            }
            grouped[perm.permission_category].push(perm);
        });
        
        Object.keys(grouped).forEach(category => {
            const safeCategoryName = escapeHtml(category);
            html += `<h5 style="color: #2c3e50; margin: 20px 0 15px 0; padding-bottom: 8px; border-bottom: 2px solid #e9ecef;">${safeCategoryName}</h5><div class="row">`;
            
            grouped[category].forEach(perm => {
                const permId = parseInt(perm.permission_id);
                const userPermIds = currentUserPermissions.map(id => parseInt(id));
                const isSelected = userPermIds.includes(permId);
                const safePermName = escapeHtml(perm.permission_display_name);
                
                html += `
                    <div class="col-md-6">
                        <div class="checkbox">
                            <label for="perm_${permId}">
                                <input type="checkbox" name="permissions[]" value="${permId}" 
                                       class="permission-checkbox" ${isSelected ? 'checked' : ''} id="perm_${permId}">
                                <div class="text-content">
                                    <strong>${safePermName}</strong>
                                </div>
                            </label>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        });
        
        $('#permissionsList').html(html).show();
        updatePermissionCounters();
        
        $('.permission-checkbox').on('change', updatePermissionCounters);
        
        $('#selectAll').on('click', function() {
            $('.permission-checkbox').prop('checked', true);
            updatePermissionCounters();
        });
        
        $('#clearAll').on('click', function() {
            $('.permission-checkbox').prop('checked', false);
            updatePermissionCounters();
        });
    }
    
    // Update permission counters function
    function updatePermissionCounters() {
        const total = allPermissions.length;
        const selected = $('.permission-checkbox:checked').length;
        $('#totalCount').text(total);
        $('#selectedCount').text(selected);
    }
    
    // Search permissions function
    $('#searchPermissions').on('input', function() {
        const searchTerm = $(this).val().toLowerCase().trim();
        
        if (searchTerm === '') {
            $('.checkbox').show();
        } else {
            $('.checkbox').each(function() {
                const permissionText = $(this).find('.text-content strong').text().toLowerCase();
                if (permissionText.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
        
        // Update visible counter
        const visibleTotal = $('.checkbox:visible').length;
        const visibleSelected = $('.checkbox:visible input[type="checkbox"]:checked').length;
        
        if (searchTerm !== '') {
            $('#totalCount').text(visibleTotal + ' (من ' + allPermissions.length + ')');
            $('#selectedCount').text(visibleSelected);
        } else {
            updatePermissionCounters();
        }
    });
    
    // Form submission
    $('#editUserForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Add selected permissions
        const selectedPermissions = [];
        $('.permission-checkbox:checked').each(function() {
            selectedPermissions.push($(this).val());
        });
        formData.append('selected_permissions', JSON.stringify(selectedPermissions));
        
        // Add selected projects
        const selectedProjects = [];
        $('.project-checkbox:checked').each(function() {
            selectedProjects.push($(this).val());
        });
        formData.append('selected_projects', JSON.stringify(selectedProjects));
        
        // Add selected departments
        const selectedDepartments = [];
        $('.department-checkbox:checked').each(function() {
            selectedDepartments.push($(this).val());
        });
        formData.append('selected_departments', JSON.stringify(selectedDepartments));
        
        $.ajax({
            url: 'php_action/edit_user.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('تم تحديث المستخدم بنجاح');
                    window.location.href = 'view_user.php?id=' + <?php echo $user['user_id']; ?>;
                } else {
                    alert('خطأ: ' + response.error);
                }
            },
            error: function() {
                alert('حدث خطأ في الخادم');
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?> 