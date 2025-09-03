<?php
$pageTitle = 'إضافة مستخدم جديد';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check if user has permission to add users
if (!hasPermission('users_add') && getUserRole() !== 'super_admin') {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بالوصول إلى هذه الصفحة', 'danger');
    exit;
}

// Include header
require_once 'includes/header.php';
?>

<!-- Permissions CSS -->
<style>
/* حل نهائي وبسيط لمنع التداخل */
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
}

.permissions-section .panel-body {
    padding: 25px;
    background: #fafbfc;
    position: static !important;
}

.alert-info {
    background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
    border: none;
    border-radius: 10px;
    border-left: 4px solid #2196f3;
    box-shadow: 0 2px 10px rgba(33, 150, 243, 0.1);
    position: static !important;
    margin-bottom: 20px !important;
}

/* حقل البحث - بسيط جداً */
#searchPermissions {
    width: 100%;
    padding: 12px 20px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 14px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 20px !important;
    position: static !important;
    z-index: auto !important;
}

#searchPermissions:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

/* العداد - بسيط */
.permission-counter {
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    border: 2px solid #e9ecef;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 20px !important;
    position: static !important;
}

.permission-counter strong {
    color: #667eea;
    font-size: 16px;
}

/* الأزرار - بسيط */
.btn-success, .btn-warning {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 600;
    border: none;
    margin: 5px;
    position: static !important;
}

.btn-success {
    background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%);
}

.btn-warning {
    background: linear-gradient(135deg, #ff9800 0%, #ffc107 100%);
}

/* عناوين الفئات - بسيط */
h5 {
    color: #4a5568;
    font-weight: 700;
    font-size: 16px;
    margin: 30px 0 20px 0 !important;
    padding: 12px 15px;
    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    border-radius: 8px;
    border-right: 4px solid #667eea;
    position: static !important;
    display: block !important;
    clear: both !important;
}

/* صناديق التحديد - حل نهائي بسيط */
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

/* منطقة الصلاحيات */
#permissionsList {
    position: static !important;
    background: transparent !important;
    padding: 0 !important;
    margin-top: 20px !important;
}

/* الصفوف والأعمدة */
.row {
    margin-right: -15px !important;
    margin-left: -15px !important;
    position: static !important;
}

.col-md-6, .col-md-8, .col-md-4 {
    padding-right: 15px !important;
    padding-left: 15px !important;
    position: static !important;
}

/* التحميل */
#loadingIndicator {
    background: white !important;
    border-radius: 10px !important;
    margin: 20px 0 !important;
    padding: 30px !important;
    text-align: center !important;
    position: static !important;
}

/* النموذج العام */
.form-group label {
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 8px;
    position: static !important;
}

.required {
    color: #e53e3e;
    font-weight: bold;
}

.btn-lg {
    padding: 12px 25px;
    margin: 8px;
    border-radius: 10px;
    font-weight: 600;
    position: static !important;
}

/* إزالة أي تأثيرات قد تسبب مشاكل */
* {
    box-sizing: border-box !important;
}

.permissions-section * {
    position: static !important;
    z-index: auto !important;
}

/* تأكيد عدم التداخل */
.permissions-section .panel-body > * {
    position: static !important;
    clear: both !important;
    display: block !important;
    width: auto !important;
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
                            <h4><i class="glyphicon glyphicon-user-plus"></i> إضافة مستخدم جديد</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="users.php" class="btn btn-default">
                                <i class="glyphicon glyphicon-arrow-right"></i> العودة لقائمة المستخدمين
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <form id="addUserForm" method="POST">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name">الاسم الكامل <span class="text-danger">*</span></label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" required 
                                           placeholder="أدخل الاسم الكامل" tabindex="2">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">اسم المستخدم <span class="text-danger">*</span></label>
                                    <input type="text" id="username" name="username" class="form-control" required 
                                           placeholder="أدخل اسم المستخدم (أحرف وأرقام فقط)" pattern="[a-zA-Z0-9_]{3,}" tabindex="1">
                                    <small class="text-muted">3 أحرف على الأقل، أحرف وأرقام و _ فقط</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">كلمة المرور <span class="text-danger">*</span></label>
                                    <input type="password" id="password" name="password" class="form-control" required 
                                           placeholder="أدخل كلمة المرور (6 أحرف على الأقل)" minlength="6" tabindex="4">
                                    <small class="text-muted">6 أحرف على الأقل</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">البريد الإلكتروني <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" required 
                                           placeholder="example@domain.com" tabindex="3">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_id">القسم</label>
                                    <select id="department_id" name="department_id" class="form-control" tabindex="4">
                                        <option value="">اختر القسم</option>
                                        <!-- Options will be loaded via AJAX -->
                                    </select>
                                    <small class="text-muted">تحديد القسم للمدير مطلوب</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="project_id">المشروع</label>
                                    <select id="project_id" name="project_id" class="form-control" tabindex="5">
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
                                        <option value="user">مستخدم عادي</option>
                                        <option value="admin">مشرف</option>
                                        <option value="super_admin">مشرف عام</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" id="parentAdminGroup" style="display: none;">
                                    <label for="parent_admin_id">المدير المباشر <span class="text-info">(اختياري)</span></label>
                                    <select id="parent_admin_id" name="parent_admin_id" class="form-control" tabindex="8">
                                        <option value="">-- مدير مستقل (رئيسي) --</option>
                                    </select>
                                    <small class="text-muted">
                                        إذا تركت فارغ، سيكون مدير رئيسي. إذا اخترت مدير، سيكون تابع له.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Admin Type Display -->
                        <div class="row" id="adminTypeRow" style="display: none;">
                            <div class="col-md-12">
                                <div class="alert alert-info" id="adminTypeAlert" style="margin-bottom: 20px;">
                                    <i class="glyphicon glyphicon-info-sign"></i>
                                    <strong>نوع المدير:</strong> <span id="adminTypeText"></span>
                                    <br><small id="adminTypeHelp"></small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Permissions Selection Section -->
                        <div id="permissionsSection" class="permissions-section" style="display: none;">
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h4><i class="glyphicon glyphicon-lock"></i> اختيار الصلاحيات الإضافية</h4>
                                </div>
                                <div class="panel-body">
                                    <div class="alert alert-info">
                                        <strong>ملاحظة:</strong> يمكنك اختيار صلاحيات إضافية للمستخدم. الصلاحيات الأساسية للدور ستُمنح تلقائياً.
                                    </div>
                                    
                                    <!-- Simple Search and Counter -->
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

                                    <!-- Simple Action Buttons -->
                                    <div class="text-center" style="margin-bottom: 15px;">
                                        <button type="button" class="btn btn-success btn-sm" id="selectAll">
                                            <i class="glyphicon glyphicon-check"></i> تحديد الكل
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm" id="clearAll">
                                            <i class="glyphicon glyphicon-unchecked"></i> إلغاء الكل
                                        </button>
                                    </div>



                                    <!-- Simple Permissions List -->
                                    <div id="permissionsList" style="display: none;">
                                        <!-- Permissions will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        

                        
                        <!-- Projects Permissions Section (Admin/Sub Admin only) -->
                        <div id="projectPermissionsSection" class="permissions-section" style="display: none;">
                            <div class="panel panel-warning">
                                <div class="panel-heading">
                                    <h4><i class="glyphicon glyphicon-folder-open"></i> المشاريع المسموحة</h4>
                                </div>
                                <div class="panel-body">
                                    <div class="alert alert-warning">
                                        <strong>مهم:</strong> حدد المشاريع التي سيتمكن هذا المدير من إضافة رخص فيها. إذا لم تحدد أي مشروع، لن يتمكن من إضافة رخص.
                                    </div>
                                    
                                    <!-- Projects Counter and Actions -->
                                    <div class="row" style="margin-bottom: 15px;">
                                        <div class="col-md-6">
                                            <div class="project-counter">
                                                <strong>المشاريع المختارة: <span id="selectedProjectsCount">0</span></strong>
                                                من <span id="totalProjectsCount">0</span> مشروع
                                            </div>
                                        </div>
                                        <div class="col-md-6 text-left">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-success" id="selectAllProjects">
                                                    <i class="glyphicon glyphicon-check"></i> تحديد الكل
                                                </button>
                                                <button type="button" class="btn btn-warning" id="clearAllProjects">
                                                    <i class="glyphicon glyphicon-unchecked"></i> إلغاء الكل
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="checkbox-group">
                                        <div id="projectsGrid" class="permissions-grid" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                            <!-- Projects will be loaded here dynamically -->
                                            <div class="text-center text-muted" style="padding: 20px;">
                                                <i class="glyphicon glyphicon-refresh fa-spin"></i> جاري تحميل المشاريع...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Departments Permissions Section (Admin/Sub Admin only) -->
                        <div id="departmentPermissionsSection" class="permissions-section" style="display: none;">
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h4><i class="glyphicon glyphicon-th-large"></i> الأقسام المسموحة</h4>
                                </div>
                                <div class="panel-body">
                                    <div class="alert alert-info">
                                        <strong>مهم:</strong> حدد الأقسام التي سيتمكن هذا المدير من إضافة رخص فيها. إذا لم تحدد أي قسم، سيتم استخدام قسمه الافتراضي فقط.
                                    </div>
                                    
                                    <!-- Departments Counter and Actions -->
                                    <div class="row" style="margin-bottom: 15px;">
                                        <div class="col-md-6">
                                            <div class="department-counter">
                                                <strong>الأقسام المختارة: <span id="selectedDepartmentsCount">0</span></strong>
                                                من <span id="totalDepartmentsCount">0</span> قسم
                                            </div>
                                        </div>
                                        <div class="col-md-6 text-left">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-success" id="selectAllDepartments">
                                                    <i class="glyphicon glyphicon-check"></i> تحديد الكل
                                                </button>
                                                <button type="button" class="btn btn-warning" id="clearAllDepartments">
                                                    <i class="glyphicon glyphicon-unchecked"></i> إلغاء الكل
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="checkbox-group">
                                        <div id="departmentsGrid" class="permissions-grid" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                            <!-- Departments will be loaded here dynamically -->
                                            <div class="text-center text-muted" style="padding: 20px;">
                                                <i class="glyphicon glyphicon-refresh fa-spin"></i> جاري تحميل الأقسام...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-success btn-lg" tabindex="8">
                                <i class="glyphicon glyphicon-plus"></i> إضافة المستخدم
                            </button>
                            <a href="users.php" class="btn btn-default btn-lg" tabindex="9">
                                <i class="glyphicon glyphicon-remove"></i> إلغاء
                            </a>
                        </div>
                        
                        <!-- Permissions Info -->
                        <div class="alert alert-info">
                            <h5><i class="glyphicon glyphicon-lock"></i> نظام الصلاحيات المتقدم</h5>
                            <p>يمكنك تحديد صلاحيات مخصصة لهذا المستخدم أثناء إضافته. ستظهر قائمة الصلاحيات تلقائياً عند اختيار الدور.</p>
                            
                            <div class="row" style="margin-top: 15px;">
                                <div class="col-md-4">
                                    <h6><strong>مستخدم عادي:</strong></h6>
                                    <ul style="font-size: 12px;">
                                        <li>عرض التراخيص</li>
                                        <li>عرض التقارير الأساسية</li>
                                        <li>+ صلاحيات مخصصة</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6><strong>مشرف:</strong></h6>
                                    <ul style="font-size: 12px;">
                                        <li>إدارة تراخيص قسمه</li>
                                        <li>إضافة وتعديل التراخيص</li>
                                        <li>+ صلاحيات مخصصة</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h6><strong>مشرف عام:</strong></h6>
                                    <ul style="font-size: 12px;">
                                        <li>صلاحيات كاملة افتراضياً</li>
                                        <li>إدارة المستخدمين</li>
                                        <li>+ صلاحيات مخصصة إضافية</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <p style="margin-top: 10px; margin-bottom: 0;">
                                <strong>💡 نصيحة:</strong> يمكنك أيضاً تعديل الصلاحيات لاحقاً من خلال 
                                <strong>"إدارة الصلاحيات"</strong> في صفحة المستخدمين.
                            </p>
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
    
    // Handle role selection change
    $('#role').on('change', function() {
        const role = $(this).val();
        const departmentSelect = $('#department_id');
        const projectSelect = $('#project_id');
        const permissionsSection = $('#permissionsSection');
        
        if (role === 'admin') {
            projectSelect.prop('disabled', false);
            departmentSelect.parent().find('label').html('القسم <span class="text-danger">*</span>');
            departmentSelect.prop('required', true);
            // Enable department only if project is selected
            if (projectSelect.val()) {
                departmentSelect.prop('disabled', false);
            }
            // Show project permissions section for admin
            $('#projectPermissionsSection').show();
            $('#departmentPermissionsSection').show();
            // Load all projects for selection
            if (typeof loadAllProjects === 'function') {
                loadAllProjects();
                loadAllDepartments(); // Load departments for admin
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
        
        // Show permissions section for all roles
        if (role) {
            permissionsSection.show();
            loadPermissions(role); // Pass role to set default permissions
            // Show project permissions for admin
            if (role === 'admin') {
                $('#projectPermissionsSection').show();
                $('#departmentPermissionsSection').show();
                // Load projects if function exists
                if (typeof loadAllProjects === 'function') {
                    setTimeout(loadAllProjects, 100); // Small delay to ensure DOM is ready
                }
            } else {
                $('#projectPermissionsSection').hide();
                $('#departmentPermissionsSection').hide();
            }
        } else {
            permissionsSection.hide();
            $('#projectPermissionsSection').hide();
            $('#departmentPermissionsSection').hide();
        }
    });
    
    // Load projects function
    function loadProjects() {
        $.get('php_action/get_projects.php')
            .done(function(response) {
                if (response.success) {
                    let options = '<option value="">اختر المشروع</option>';
                    response.data.forEach(function(project) {
                        options += `<option value="${project.project_id}">${project.project_name}</option>`;
                    });
                    $('#project_id').html(options);
                }
            })
            .fail(function() {
                console.error('فشل في تحميل المشاريع');
            });
    }
    
    // Load departments function
    function loadDepartments() {
        $.get('php_action/get_unique_departments_updated.php')
            .done(function(response) {
                if (response.success) {
                    let options = '<option value="">اختر القسم</option>';
                    response.data.forEach(function(dept) {
                        options += `<option value="${dept.department_id}">${dept.department_name}</option>`;
                    });
                    $('#department_id').html(options);
                } else {
                    $('#department_id').html('<option value="">لا توجد أقسام متاحة</option>');
                }
            })
            .fail(function() {
                $('#department_id').html('<option value="">خطأ في تحميل الأقسام</option>');
            });
    }
    
    // Global variable to store permissions
    let allPermissions = [];
    
    // Simple load permissions function with role-based defaults
    function loadPermissions(selectedRole = null) {
        console.log('🔄 بدء تحميل الصلاحيات للدور:', selectedRole);
        
        // Show loading and hide permissions list
        $('#loadingIndicator').show();
        $('#permissionsList').hide();
        
        $.get('php_action/get_permissions.php')
            .done(function(response) {
                console.log('📡 استجابة API:', response);
                
                if (response.success) {
                    allPermissions = response.data;
                    console.log('✅ تم تحميل', allPermissions.length, 'صلاحية');
                    
                    // Hide loading IMMEDIATELY
                    $('#loadingIndicator').hide();
                    
                    // Render permissions with role selection
                    renderSimplePermissions(allPermissions, selectedRole);
                } else {
                    console.error('❌ خطأ في API:', response.message);
                    $('#loadingIndicator').html(`
                        <div class="alert alert-danger">
                            <strong>خطأ:</strong> ${response.message}
                        </div>
                    `);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('🔥 فشل في طلب API:', status, error);
                $('#loadingIndicator').html(`
                    <div class="alert alert-danger">
                        <strong>فشل في التحميل!</strong> خطأ في الاتصال بالخادم.
                    </div>
                `);
            });
    }
    
    // Define default permissions for each role - Updated with new permissions
    function getDefaultPermissions(role) {
        console.log('🎯 الحصول على الصلاحيات الافتراضية للدور:', role);
        
        const defaults = {
            'user': [
                // Basic viewing permissions for regular users
                'licenses_view',
                'personal_licenses_view',
                'vehicle_licenses_view'
            ],
            'admin': [
                // License management permissions for admins
                'licenses_view',
                'licenses_add',
                'licenses_edit',
                'licenses_delete',
                'personal_licenses_view',
                'personal_licenses_add',
                'personal_licenses_edit',
                'personal_licenses_delete',
                'vehicle_licenses_view',
                'vehicle_licenses_add',
                'vehicle_licenses_edit',
                'vehicle_licenses_delete',
                'departments_view',
                'projects_view'
            ],
            'super_admin': [
                // All permissions for super admin
                'licenses_view',
                'licenses_add',
                'licenses_edit',
                'licenses_delete',
                'personal_licenses_view',
                'personal_licenses_add',
                'personal_licenses_edit',
                'personal_licenses_delete',
                'vehicle_licenses_view',
                'vehicle_licenses_add',
                'vehicle_licenses_edit',
                'vehicle_licenses_delete',
                'users_view',
                'users_add',
                'users_edit',
                'users_delete',
                'departments_view',
                'departments_add',
                'departments_edit',
                'departments_delete',
                'projects_view',
                'projects_add',
                'projects_edit',
                'projects_delete',
                'reports_view',
                'analytics_view',
                'system_settings',
                'backup_restore'
            ]
        };
        
        const selectedDefaults = defaults[role] || [];
        console.log('📋 الصلاحيات الافتراضية المختارة:', selectedDefaults);
        console.log('📊 عدد الصلاحيات الافتراضية:', selectedDefaults.length);
        
        return selectedDefaults;
    }
    
    // Enhanced render function with better matching
    function renderSimplePermissions(permissions, selectedRole = null) {
        console.log('🎨 بدء رسم الصلاحيات للدور:', selectedRole);
        console.log('📊 إجمالي الصلاحيات المستلمة:', permissions.length);
        
        // FORCE hide loading indicator first
        $('#loadingIndicator').hide();
        
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
        console.log('📋 جميع أسماء الصلاحيات:', permissions.map(p => p.permission_name));
        
        // Category names
        const categoryNames = {
            'licenses': '📜 التراخيص',
            'users': '👥 المستخدمون', 
            'departments': '🏢 الأقسام',
            'reports': '📊 التقارير',
            'settings': '⚙️ الإعدادات',
            'system': '🔧 النظام'
        };
        
        // Get default permissions for the selected role
        const defaultPermissions = selectedRole ? getDefaultPermissions(selectedRole) : [];
        console.log('✅ الصلاحيات الافتراضية النهائية:', defaultPermissions);
        
        let totalDefaultSelected = 0;
        
        // Render each category
        Object.keys(grouped).forEach(category => {
            html += `<h5 style="color: #4a5568; font-weight: 700; font-size: 16px; margin: 30px 0 20px 0 !important; padding: 12px 15px; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 8px; border-right: 4px solid #667eea; position: static !important; display: block !important; clear: both !important;">${categoryNames[category] || category}</h5>`;
            html += '<div class="row">';
            
            grouped[category].forEach(perm => {
                // Simple and accurate permission matching using exact names
                const isDefaultSelected = defaultPermissions.includes(perm.permission_name);
                
                if (isDefaultSelected) {
                    totalDefaultSelected++;
                    console.log('☑️ تحديد افتراضي:', perm.permission_name, '-', perm.permission_display_name);
                }
                
                html += `
                    <div class="col-md-6 col-sm-12" style="margin-bottom: 10px;">
                        <div class="checkbox" style="margin: 15px 0 !important; position: static !important; display: block !important; clear: both !important; width: 100% !important;">
                            <label style="font-weight: normal !important; padding: 15px 20px !important; background: white !important; border-radius: 8px !important; border: 2px solid #e9ecef !important; cursor: pointer !important; display: block !important; margin-bottom: 10px !important; position: static !important; width: 100% !important; min-height: 60px !important; line-height: 1.5 !important; overflow: visible !important;">
                                <input type="checkbox" name="permissions[]" value="${perm.permission_id}" 
                                       class="permission-checkbox" ${isDefaultSelected ? 'checked' : ''}
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
        
        // Use the ultimate loading hide solution
        forceHideLoading();
        
        // Additional safety timeout
        setTimeout(function() {
            forceHideLoading();
            console.log('⏰ تحقق إضافي من إخفاء التحميل');
        }, 500);
        
        console.log('🎯 إجمالي الصلاحيات المحددة تلقائياً:', totalDefaultSelected);
        
        // Update counter and setup handlers
        updateCounter();
        setupSimpleHandlers();
        
        // Show role-based message if any permissions were selected
        if (selectedRole && totalDefaultSelected > 0) {
            const roleNames = {
                'user': 'مستخدم عادي',
                'admin': 'مدير قسم', 
                'super_admin': 'مدير عام'
            };
            
            showRoleMessage(roleNames[selectedRole], totalDefaultSelected);
        } else if (selectedRole) {
            console.warn('⚠️ لم يتم تحديد أي صلاحيات افتراضية للدور:', selectedRole);
            showRoleMessage('المستخدم', 0);
        }
        
        console.log('✅ تم الانتهاء من رسم الصلاحيات - Loading مخفي:', $('#loadingIndicator').is(':hidden'));
    }
    
    // Simple counter update
    function updateCounter() {
        const total = allPermissions.length;
        const selected = $('.permission-checkbox:checked').length;
        
        console.log('📊 تحديث العداد - المجموع:', total, '، المختار:', selected);
        
        $('#totalCount').text(total);
        $('#selectedCount').text(selected);
    }
    
    // Simple event handlers
    function setupSimpleHandlers() {
        console.log('🔧 إعداد معالجات الأحداث');
        
        // Checkbox change
        $('.permission-checkbox').off('change').on('change', function() {
            console.log('☑️ تغيير في checkbox:', $(this).data('permission-name'), 'محدد:', $(this).is(':checked'));
            updateCounter();
        });
        
        // Search
        $('#searchPermissions').off('input').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            console.log('🔍 البحث عن:', searchTerm);
            
            $('.checkbox').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        });
        
        // Select all
        $('#selectAll').off('click').on('click', function() {
            console.log('✅ تحديد الكل');
            $('.checkbox:visible .permission-checkbox').prop('checked', true);
            updateCounter();
        });
        
        // Clear all
        $('#clearAll').off('click').on('click', function() {
            console.log('❌ إلغاء الكل');
            $('.permission-checkbox').prop('checked', false);
            updateCounter();
        });
    }
    
    // Force hide loading indicator - Ultimate solution
    function forceHideLoading() {
        // Multiple methods to ensure loading is hidden
        $('#loadingIndicator').hide();
        $('#loadingIndicator').css('display', 'none');
        $('#loadingIndicator').addClass('hidden');
        
        // Show permissions list
        $('#permissionsList').show();
        $('#permissionsList').css('display', 'block');
        $('#permissionsList').removeClass('hidden');
        
        console.log('🔒 تم فرض إخفاء التحميل بقوة');
    }
    
    // Show role-based selection message
    function showRoleMessage(roleName, defaultCount) {
        console.log('💬 عرض رسالة الدور:', roleName, '، العدد:', defaultCount);
        
        // Force hide loading again
        forceHideLoading();
        
        let messageHtml = '';
        
        if (defaultCount > 0) {
            messageHtml = `
                <div class="alert alert-success role-message" style="margin-bottom: 15px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border: 2px solid #28a745; border-radius: 10px;">
                    <strong>✅ تم التحديد التلقائي:</strong> 
                    تم اختيار <strong>${defaultCount}</strong> صلاحية افتراضية لدور "<strong>${roleName}</strong>". 
                    <br><small>يمكنك الآن إضافة أو إزالة صلاحيات حسب الحاجة.</small>
                </div>
            `;
        } else {
            messageHtml = `
                <div class="alert alert-info role-message" style="margin-bottom: 15px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 2px solid #2196f3; border-radius: 10px;">
                    <strong>ℹ️ تم تحديد الدور:</strong> 
                    تم اختيار دور "<strong>${roleName}</strong>". لم يتم العثور على صلاحيات افتراضية مطابقة.
                    <br><small>يمكنك اختيار الصلاحيات المناسبة يدوياً.</small>
                </div>
            `;
        }
        
        // Remove any existing role message
        $('.role-message').remove();
        
        // Add new message after the first info alert
        $('.permissions-section .alert-info:first').after(messageHtml);
        
        // Auto-hide after 10 seconds
        setTimeout(function() {
            $('.role-message').fadeOut();
        }, 10000);
    }
    
    // Form submission
    $('#addUserForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Add selected permissions to form data
        const selectedPermissions = [];
        $('.permission-checkbox:checked').each(function() {
            selectedPermissions.push($(this).val());
        });
        
        if (selectedPermissions.length > 0) {
            formData.append('selected_permissions', JSON.stringify(selectedPermissions));
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fa fa-spinner fa-spin"></i> جاري الإضافة...').prop('disabled', true);
        
        $.ajax({
            url: 'php_action/add_user.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(function() {
                        window.location.href = 'users.php';
                    }, 2000);
                } else {
                    showAlert(response.error, 'danger');
                    submitBtn.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                // Try to parse JSON error response
                let errorMessage = 'حدث خطأ في الخادم';
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.error) {
                        errorMessage = errorResponse.error;
                    }
                } catch (e) {
                    // If not JSON, use default message
                }
                
                showAlert(errorMessage, 'danger');
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

    // ===============================
    // Admin Teams Management Functions
    // ===============================
    
    $('#role').on('change', function() {
        var role = $(this).val();
        var department = $('#department_id').val();
        
        if (role === 'admin') {
            $('#parentAdminGroup').show();
            $('#adminTypeRow').show();
            $('#projectPermissionsSection').show();
            $('#departmentPermissionsSection').show();
            loadHeadAdmins(department);
            updateAdminType();
            loadAllProjects(); // Load projects for admin
            loadAllDepartments(); // Load departments for admin
        } else {
            $('#parentAdminGroup').hide();
            $('#adminTypeRow').hide();
            $('#projectPermissionsSection').hide();
            $('#departmentPermissionsSection').hide();
            $('#parent_admin_id').val('');
        }
    });

    // Update when department changes (for admin role)
    $('#department_id').on('change', function() {
        var role = $('#role').val();
        var department = $(this).val();
        
        if (role === 'admin') {
            loadHeadAdmins(department);
        }
    });

    // Update admin type when parent changes
    $('#parent_admin_id').on('change', function() {
        updateAdminType();
    });

    function updateAdminType() {
        var parentId = $('#parent_admin_id').val();
        
        if (parentId === '' || parentId === null) {
            $('#adminTypeText').text('مدير رئيسي (Head Admin)');
            $('#adminTypeHelp').text('سيتمكن من إدارة مديرين فرعيين تحته والتحكم في رخصهم');
            $('#adminTypeAlert').removeClass('alert-warning').addClass('alert-info');
        } else {
            $('#adminTypeText').text('مدير فرعي (Sub Admin)');  
            $('#adminTypeHelp').text('سيتبع لمدير رئيسي ولن يتمكن من إدارة آخرين، ولكن المدير الرئيسي سيرى رخصه');
            $('#adminTypeAlert').removeClass('alert-info').addClass('alert-warning');
        }
    }

    function loadHeadAdmins(departmentId) {
        if (!departmentId) {
            $('#parent_admin_id').empty().append('<option value="">-- اختر القسم أولاً --</option>');
            return;
        }
        
        $('#parent_admin_id').prop('disabled', true).empty().append('<option value="">جاري التحميل...</option>');
        
        $.ajax({
            url: 'php_action/get_head_admins.php',
            method: 'POST', 
            data: {department_id: departmentId},
            dataType: 'json',
            success: function(response) {
                var select = $('#parent_admin_id');
                select.empty();
                select.append('<option value="">-- مدير مستقل (رئيسي) --</option>');
                
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(admin) {
                        select.append('<option value="' + admin.user_id + '">' + 
                                    admin.full_name + ' (' + admin.username + ')</option>');
                    });
                } else {
                    select.append('<option value="">-- لا يوجد مديرين رئيسيين في هذا القسم --</option>');
                }
                
                select.prop('disabled', false);
                updateAdminType();
            },
            error: function() {
                $('#parent_admin_id').empty()
                    .append('<option value="">خطأ في تحميل المديرين</option>')
                    .prop('disabled', false);
            }
        });
    }

    // ===============================
    // Projects Permissions Functions  
    // ===============================
    
    // Load all projects for admin role
    function loadAllProjects() {
        console.log('🔧 تحميل جميع المشاريع...');
        
        $.ajax({
            url: 'php_action/get_projects.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('✅ نجح تحميل المشاريع:', response);
                
                if (response.success && response.data) {
                    renderProjectsGrid(response.data);
                } else {
                    console.error('❌ لا توجد بيانات مشاريع');
                    $('#projectsGrid').html('<div class="text-center text-muted" style="padding: 20px;"><i class="glyphicon glyphicon-exclamation-sign"></i> لا توجد مشاريع متاحة</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ خطأ في تحميل المشاريع:', error);
                $('#projectsGrid').html('<div class="text-center text-danger" style="padding: 20px;"><i class="glyphicon glyphicon-warning-sign"></i> حدث خطأ في تحميل المشاريع</div>');
            }
        });
    }
    
    // Render projects in grid format
    function renderProjectsGrid(projects) {
        console.log('🎨 عرض', projects.length, 'مشروع');
        
        let html = '<div class="row">';
        
        projects.forEach(function(project) {
            html += `
                <div class="col-md-6 col-sm-12" style="margin-bottom: 10px;">
                    <div class="checkbox" style="margin: 10px 0;">
                        <label style="font-weight: normal; padding: 12px 15px; background: white; border-radius: 6px; border: 2px solid #e9ecef; cursor: pointer; display: block; min-height: 50px;">
                            <input type="checkbox" name="projects[]" value="${project.project_id}" 
                                   class="project-checkbox" data-project-name="${project.project_name}"
                                   style="width: 16px; height: 16px; margin: 0 8px 0 0; float: right;"> 
                            <strong style="color: #2d3748;">${project.project_name}</strong>
                            ${project.project_description ? '<small style="color: #6c757d; font-size: 11px; display: block; margin-top: 5px;">' + project.project_description + '</small>' : ''}
                        </label>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        $('#projectsGrid').html(html);
        
        // Update counters
        $('#totalProjectsCount').text(projects.length);
        updateProjectsCounter();
        
        // Add event listeners
        $('.project-checkbox').on('change', updateProjectsCounter);
        
        // Select/Clear all buttons
        $('#selectAllProjects').off('click').on('click', function() {
            $('.project-checkbox').prop('checked', true);
            updateProjectsCounter();
        });
        
        $('#clearAllProjects').off('click').on('click', function() {
            $('.project-checkbox').prop('checked', false);
            updateProjectsCounter();
        });
        
        console.log('✅ تم عرض المشاريع بنجاح');
    }
    
    // Update projects counter
    function updateProjectsCounter() {
        const selectedCount = $('.project-checkbox:checked').length;
        $('#selectedProjectsCount').text(selectedCount);
        
        // Change color based on selection
        if (selectedCount > 0) {
            $('#selectedProjectsCount').parent().css('color', '#28a745');
        } else {
            $('#selectedProjectsCount').parent().css('color', '#6c757d');
        }
    }
    
    // Load all departments for admin role
    function loadAllDepartments() {
        console.log('🔧 تحميل جميع الأقسام...');
        
        $.ajax({
            url: 'php_action/get_departments_no_auth.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('✅ نجح تحميل الأقسام:', response);
                
                if (response.success && response.data) {
                    renderDepartmentsGrid(response.data);
                } else {
                    console.error('❌ لا توجد بيانات أقسام');
                    $('#departmentsGrid').html('<div class="text-center text-muted" style="padding: 20px;"><i class="glyphicon glyphicon-exclamation-sign"></i> لا توجد أقسام متاحة</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ خطأ في تحميل الأقسام:', error);
                $('#departmentsGrid').html('<div class="text-center text-danger" style="padding: 20px;"><i class="glyphicon glyphicon-warning-sign"></i> حدث خطأ في تحميل الأقسام</div>');
            }
        });
    }
    
    // Render departments in grid format
    function renderDepartmentsGrid(departments) {
        console.log('🎨 عرض', departments.length, 'قسم');
        
        let html = '<div class="row">';
        
        departments.forEach(function(department) {
            html += `
                <div class="col-md-6 col-sm-12" style="margin-bottom: 10px;">
                    <div class="checkbox" style="margin: 10px 0;">
                        <label style="font-weight: normal; padding: 12px 15px; background: white; border-radius: 6px; border: 2px solid #e9ecef; cursor: pointer; display: block; min-height: 50px;">
                            <input type="checkbox" name="departments[]" value="${department.department_id}" 
                                   class="department-checkbox" data-department-name="${department.department_name}"
                                   style="width: 16px; height: 16px; margin: 0 8px 0 0; float: right;"> 
                            <strong style="color: #2d3748;">${department.department_name}</strong>
                            ${department.department_description ? '<small style="color: #6c757d; font-size: 11px; display: block; margin-top: 5px;">' + department.department_description + '</small>' : ''}
                        </label>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        $('#departmentsGrid').html(html);
        
        // Update counters
        $('#totalDepartmentsCount').text(departments.length);
        updateDepartmentsCounter();
        
        // Add event listeners
        $('.department-checkbox').on('change', updateDepartmentsCounter);
        
        // Select/Clear all buttons
        $('#selectAllDepartments').off('click').on('click', function() {
            $('.department-checkbox').prop('checked', true);
            updateDepartmentsCounter();
        });
        
        $('#clearAllDepartments').off('click').on('click', function() {
            $('.department-checkbox').prop('checked', false);
            updateDepartmentsCounter();
        });
    }
    
    // Update departments counter
    function updateDepartmentsCounter() {
        const selectedCount = $('.department-checkbox:checked').length;
        $('#selectedDepartmentsCount').text(selectedCount);
        
        // Change color based on selection
        if (selectedCount > 0) {
            $('#selectedDepartmentsCount').parent().css('color', '#28a745');
        } else {
            $('#selectedDepartmentsCount').parent().css('color', '#6c757d');
        }
    }
    
    // Load all departments for admin role
    function loadAllDepartments() {
        console.log('🔧 تحميل جميع الأقسام...');
        
        $.ajax({
            url: 'php_action/get_departments_no_auth.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('✅ نجح تحميل الأقسام:', response);
                
                if (response.success && response.data) {
                    renderDepartmentsGrid(response.data);
                } else {
                    console.error('❌ لا توجد بيانات أقسام');
                    $('#departmentsGrid').html('<div class="text-center text-muted" style="padding: 20px;"><i class="glyphicon glyphicon-exclamation-sign"></i> لا توجد أقسام متاحة</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ خطأ في تحميل الأقسام:', error);
                $('#departmentsGrid').html('<div class="text-center text-danger" style="padding: 20px;"><i class="glyphicon glyphicon-warning-sign"></i> حدث خطأ في تحميل الأقسام</div>');
            }
        });
    }
    
    // Render departments in grid format
    function renderDepartmentsGrid(departments) {
        console.log('🎨 عرض', departments.length, 'قسم');
        
        let html = '<div class="row">';
        
        departments.forEach(function(department) {
            html += `
                <div class="col-md-6 col-sm-12" style="margin-bottom: 10px;">
                    <div class="checkbox" style="margin: 10px 0;">
                        <label style="font-weight: normal; padding: 12px 15px; background: white; border-radius: 6px; border: 2px solid #e9ecef; cursor: pointer; display: block; min-height: 50px;">
                            <input type="checkbox" name="departments[]" value="${department.department_id}" 
                                   class="department-checkbox" data-department-name="${department.department_name}"
                                   style="width: 16px; height: 16px; margin: 0 8px 0 0; float: right;"> 
                            <strong style="color: #2d3748;">${department.department_name}</strong>
                            ${department.department_description ? '<small style="color: #6c757d; font-size: 11px; display: block; margin-top: 5px;">' + department.department_description + '</small>' : ''}
                        </label>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        $('#departmentsGrid').html(html);
        
        // Update counters
        $('#totalDepartmentsCount').text(departments.length);
        updateDepartmentsCounter();
        
        // Add event listeners
        $('.department-checkbox').on('change', updateDepartmentsCounter);
        
        // Select/Clear all buttons
        $('#selectAllDepartments').off('click').on('click', function() {
            $('.department-checkbox').prop('checked', true);
            updateDepartmentsCounter();
        });
        
        $('#clearAllDepartments').off('click').on('click', function() {
            $('.department-checkbox').prop('checked', false);
            updateDepartmentsCounter();
        });
    }
    
    // Update departments counter
    function updateDepartmentsCounter() {
        const selectedCount = $('.department-checkbox:checked').length;
        $('#selectedDepartmentsCount').text(selectedCount);
        
        // Change color based on selection
        if (selectedCount > 0) {
            $('#selectedDepartmentsCount').parent().css('color', '#28a745');
        } else {
            $('#selectedDepartmentsCount').parent().css('color', '#6c757d');
        }
    }
    
});
</script>

<?php include 'includes/footer.php'; ?> 