<?php
$pageTitle = 'إدارة المستخدمين';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check if user has any permission related to users
$canAccessUsers = getUserRole() === 'super_admin' || 
                  hasPermission('users_view') || 
                  hasPermission('users_add') || 
                  hasPermission('users_edit') || 
                  hasPermission('users_delete');

if (!$canAccessUsers) {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بالوصول إلى هذه الصفحة', 'danger');
    exit;
}

include 'includes/header.php';

// Pass permissions to JavaScript
$canEditUsers = hasPermission('users_edit') || getUserRole() === 'super_admin';
$canDeleteUsers = hasPermission('users_delete') || getUserRole() === 'super_admin';
$canAddUsers = hasPermission('users_add') || getUserRole() === 'super_admin';
?>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><i class="glyphicon glyphicon-user"></i> إدارة المستخدمين</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <?php if (hasPermission('users_add') || getUserRole() === 'super_admin'): ?>
                            <a href="add_user.php" class="btn btn-primary">
                                <i class="glyphicon glyphicon-plus"></i> إضافة مستخدم جديد
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <!-- Search and Filter Section -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-3 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <input type="text" id="searchInput" class="form-control" placeholder="البحث في المستخدمين...">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <select id="roleFilter" class="form-control">
                                    <option value="">جميع الأدوار</option>
                                    <option value="super_admin">مشرف عام</option>
                                    <option value="admin">مشرف</option>
                                    <option value="user">مستخدم عادي</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <select id="departmentFilter" class="form-control">
                                    <option value="">جميع الأقسام</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <select id="projectFilter" class="form-control">
                                    <option value="">جميع المشاريع</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-12 col-xs-12">
                            <div class="form-group">
                                <a href="deleted_users.php" class="btn btn-warning btn-block">
                                        <i class="glyphicon glyphicon-trash"></i> <span class="hidden-xs">المحذوفون</span>
                                    </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading indicator -->
                    <div id="loadingIndicator" class="text-center" style="display: none;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p>جاري تحميل المستخدمين...</p>
                    </div>
                    
                    <!-- Users Table -->
                    <div id="usersContainer">
                        <div class="table-responsive table-container">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>اسم المستخدم</th>
                                        <th class="hidden-xs">الاسم الكامل</th>
                                        <th class="hidden-xs hidden-sm">البريد الإلكتروني</th>
                                        <th class="text-center">الدور</th>
                                        <th class="hidden-xs hidden-sm">المشروع</th>
                                        <th class="hidden-xs">القسم</th>
                                        <th class="hidden-xs">الحالة</th>
                                        <th class="hidden-xs hidden-sm">تاريخ الإنشاء</th>
                                        <th class="text-center">الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div id="paginationContainer" class="text-center">
                            <!-- Pagination will be loaded via AJAX -->
                        </div>
                    </div>
                    
                    <!-- No data message -->
                    <div id="noDataMessage" class="text-center" style="display: none; padding: 40px;">
                        <i class="glyphicon glyphicon-user" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                        <h4>لا يوجد مستخدمون</h4>
                        <p class="text-muted">لم يتم العثور على مستخدمين تطابق معايير البحث الحالية</p>
                        <?php if ($canAddUsers): ?>
                        <a href="add_user.php" class="btn btn-primary">
                            <i class="glyphicon glyphicon-plus"></i> إضافة أول مستخدم
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">تأكيد الحذف</h4>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من إلغاء تفعيل هذا المستخدم؟ لن يتمكن من تسجيل الدخول مرة أخرى.</p>
                <div id="userToDelete"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger">إلغاء التفعيل</button>
            </div>
        </div>
    </div>
</div>

<script>
// Pass PHP permissions to JavaScript
const canEditUsers = <?php echo $canEditUsers ? 'true' : 'false'; ?>;
const canDeleteUsers = <?php echo $canDeleteUsers ? 'true' : 'false'; ?>;
const canAddUsers = <?php echo $canAddUsers ? 'true' : 'false'; ?>;

$(document).ready(function() {
    let currentPage = 1;
    
    // Load departments and projects for filters
    loadDepartments();
    loadProjects();
    
    // Load initial data
    loadUsers();
    
    // Search functionality - onchange/keyup
    $('#searchInput').on('keyup', function() {
        currentPage = 1;
        loadUsers();
    });
    
    // Filter changes for all dropdowns
    $('#roleFilter, #departmentFilter, #projectFilter').on('change', function() {
        currentPage = 1;
        loadUsers();
    });
    
    // Load departments
    function loadDepartments() {
        $.get('php_action/get_unique_departments.php')
            .done(function(response) {
                if (response.success) {
                    const select = $('#departmentFilter');
                    select.find('option:not(:first)').remove();
                    
                    response.data.forEach(function(dept) {
                        select.append(`<option value="${dept.department_name}">${dept.department_name}</option>`);
                    });
                }
            });
    }
    
    // Load projects
    function loadProjects() {
        $.get('php_action/get_projects.php')
            .done(function(response) {
                if (response.success) {
                    const select = $('#projectFilter');
                    select.find('option:not(:first)').remove();
                    
                    response.data.forEach(function(project) {
                        select.append(`<option value="${project.project_id}">${project.project_name}</option>`);
                    });
                }
            });
    }
    
    // Load users function (you'll need to create this API endpoint)
    function loadUsers(page = 1) {
        currentPage = page;
        
        const params = {
            page: page,
            search: $('#searchInput').val(),
            role: $('#roleFilter').val(),
            department_id: $('#departmentFilter').val(),
            project_id: $('#projectFilter').val()
        };
        
        $('#loadingIndicator').show();
        $('#usersContainer').hide();
        $('#noDataMessage').hide();
        
        // This endpoint needs to be created
        $.get('php_action/get_users.php', params)
            .done(function(response) {
                $('#loadingIndicator').hide();
                
                if (response.success && response.data.length > 0) {
                    renderUsers(response.data);
                    renderPagination(response.pagination);
                    $('#usersContainer').show();
                } else {
                    $('#noDataMessage').show();
                }
            })
            .fail(function() {
                $('#loadingIndicator').hide();
                showAlert('فشل في تحميل المستخدمين', 'danger');
            });
    }
    
    // Render users table
    function renderUsers(users) {
        const tbody = $('#usersTableBody');
        tbody.empty();
        
        users.forEach(function(user) {
            const statusBadge = user.is_active ? 
                '<span class="label label-success">نشط</span>' :
                '<span class="label label-danger">غير نشط</span>';
            
            const roleBadge = getRoleBadge(user.role);
            const createdDate = new Date(user.created_at).toLocaleDateString();
            
            const row = `
                <tr>
                    <td>
                        <strong>${user.username}</strong>
                        <div class="visible-xs">
                            <small class="text-muted">${user.full_name}</small><br>
                            ${roleBadge}
                            ${statusBadge}
                        </div>
                    </td>
                    <td class="hidden-xs">${user.full_name}</td>
                    <td class="hidden-xs hidden-sm"><small>${user.email}</small></td>
                    <td class="text-center hidden-xs">${roleBadge}</td>
                    <td class="hidden-xs hidden-sm">
                        <small>${user.project_name || 'غير محدد'}</small>
                    </td>
                    <td class="hidden-xs">
                        <small>${user.department_name || 'غير محدد'}</small>
                    </td>
                    <td class="hidden-xs">${statusBadge}</td>
                    <td class="hidden-xs hidden-sm"><small>${createdDate}</small></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <a href="view_user.php?id=${user.user_id}" class="btn btn-info btn-xs" title="عرض">
                                <i class="glyphicon glyphicon-eye-open"></i>
                                <span class="sr-only">عرض</span>
                            </a>
                            ${canEditUsers ? `
                            <a href="edit_user.php?id=${user.user_id}" class="btn btn-warning btn-xs" title="تعديل">
                                <i class="glyphicon glyphicon-edit"></i>
                                <span class="sr-only">تعديل</span>
                            </a>
                            ` : ''}
                            ${canDeleteUsers ? `
                            <button class="btn btn-danger btn-xs delete-user" data-id="${user.user_id}" data-name="${user.full_name}" title="إلغاء التفعيل">
                                <i class="glyphicon glyphicon-remove"></i>
                                <span class="sr-only">إلغاء التفعيل</span>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    // Get role badge
    function getRoleBadge(role) {
        switch (role) {
            case 'super_admin':
                return '<span class="label label-danger">مشرف عام</span>';
            case 'admin':
                return '<span class="label label-warning">مشرف</span>';
            default:
                return '<span class="label label-info">مستخدم عادي</span>';
        }
    }
    
    // Render pagination
    function renderPagination(pagination) {
        const container = $('#paginationContainer');
        container.empty();
        
        if (pagination.total_pages <= 1) return;
        
        let paginationHtml = '<nav><ul class="pagination">';
        
        // Previous page
        if (pagination.current_page > 1) {
            paginationHtml += `<li><a href="#" data-page="${pagination.current_page - 1}">&laquo; السابق</a></li>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.current_page ? 'active' : '';
            paginationHtml += `<li class="${activeClass}"><a href="#" data-page="${i}">${i}</a></li>`;
        }
        
        // Next page
        if (pagination.current_page < pagination.total_pages) {
            paginationHtml += `<li><a href="#" data-page="${pagination.current_page + 1}">التالي &raquo;</a></li>`;
        }
        
        paginationHtml += '</ul></nav>';
        paginationHtml += `<p class="text-muted">صفحة ${pagination.current_page} من ${pagination.total_pages} (إجمالي ${pagination.total_records} مستخدم)</p>`;
        
        container.html(paginationHtml);
        
        // Pagination click handlers
        container.find('a[data-page]').click(function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            loadUsers(page);
        });
    }
    
    // Delete user functionality
    $(document).on('click', '.delete-user', function() {
        const userId = $(this).data('id');
        const userName = $(this).data('name');
        
        $('#userToDelete').html(`<strong>المستخدم:</strong> ${userName}`);
        $('#confirmDeleteBtn').data('id', userId);
        $('#deleteModal').modal('show');
    });
    
    $('#confirmDeleteBtn').click(function() {
        const userId = $(this).data('id');
        
        $.post('php_action/delete_user.php', {
            user_id: userId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
        .done(function(response) {
            $('#deleteModal').modal('hide');
            
            if (response.success) {
                showAlert(response.message, 'success');
                loadUsers(currentPage);
            } else {
                showAlert(response.error, 'danger');
            }
        })
        .fail(function() {
            $('#deleteModal').modal('hide');
            showAlert('فشل في إلغاء تفعيل المستخدم', 'danger');
        });
    });
    
    // Show alert function
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                ${message}
            </div>
        `;
        $('.content-wrapper').prepend(alertHtml);
        
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    }
});
</script>

<?php include 'includes/footer.php'; ?>