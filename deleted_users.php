<?php
$pageTitle = 'المستخدمون المحذوفون';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check if user has permission to view deleted users
if (!hasPermission('users_delete') && getUserRole() !== 'super_admin') {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بالوصول إلى هذه الصفحة', 'danger');
    exit;
}

include 'includes/header.php';
?>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><i class="glyphicon glyphicon-trash"></i> المستخدمون المحذوفون</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="users.php" class="btn btn-primary">
                                <i class="glyphicon glyphicon-arrow-right"></i> العودة للمستخدمين النشطين
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <!-- Info Alert -->
                    <div class="alert alert-info">
                        <i class="glyphicon glyphicon-info-sign"></i>
                        <strong>ملاحظة:</strong> هذه الصفحة تعرض المستخدمين المحذوفين مؤقتاً. يمكن استعادتهم بسهولة.
                    </div>
                    
                    <!-- Search and Filter Section -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-3">
                            <input type="text" id="searchInput" class="form-control" placeholder="البحث في المستخدمين المحذوفين...">
                        </div>
                        <div class="col-md-2">
                            <select id="roleFilter" class="form-control">
                                <option value="">جميع الأدوار</option>
                                <option value="regular">مستخدم عادي</option>
                                <option value="admin">مشرف</option>
                                <option value="super_admin">مشرف عام</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="departmentFilter" class="form-control">
                                <option value="">جميع الأقسام</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button id="searchBtn" class="btn btn-info">
                                <i class="glyphicon glyphicon-search"></i> بحث
                            </button>
                        </div>
                        <div class="col-md-3 text-right">
                            <button id="resetBtn" class="btn btn-default">
                                <i class="glyphicon glyphicon-refresh"></i> إعادة تعيين
                            </button>
                        </div>
                    </div>
                    
                    <!-- Loading indicator -->
                    <div id="loadingIndicator" class="text-center" style="display: none;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p>جاري تحميل المستخدمين المحذوفين...</p>
                    </div>
                    
                    <!-- Deleted Users Table -->
                    <div id="usersContainer">
                        <div class="table-responsive table-container">
                            <table id="deletedUsersTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>اسم المستخدم</th>
                                        <th>الاسم الكامل</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الدور</th>
                                        <th>القسم</th>
                                        <th>تاريخ الحذف</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="deletedUsersTableBody">
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div id="paginationContainer" class="text-center">
                            <!-- Pagination will be loaded via AJAX -->
                        </div>
                    </div>
                    
                    <!-- No Data Message -->
                    <div id="noDataMessage" class="text-center" style="display: none; padding: 40px;">
                        <i class="glyphicon glyphicon-ok-circle" style="font-size: 48px; color: #5cb85c; margin-bottom: 15px; display: block;"></i>
                        <h4 style="color: #5cb85c;">لا يوجد مستخدمون محذوفون</h4>
                        <p class="text-muted">جميع المستخدمين نشطون حالياً</p>
                        <a href="users.php" class="btn btn-primary">
                            <i class="glyphicon glyphicon-list"></i> عرض المستخدمين النشطين
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">تأكيد الاستعادة</h4>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من استعادة هذا المستخدم؟</p>
                <div id="userToRestore" class="alert alert-success"></div>
                <small class="text-muted">سيتم إعادة تفعيل المستخدم وإرجاعه للقائمة النشطة.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                <button type="button" id="confirmRestoreBtn" class="btn btn-success">استعادة</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentPage = 1;
    
    // Load initial data
    loadDepartments();
    loadDeletedUsers();
    
    // Search functionality
    $('#searchBtn').click(function() {
        currentPage = 1;
        loadDeletedUsers();
    });
    
    // Enter key for search
    $('#searchInput').keypress(function(e) {
        if (e.which === 13) {
            $('#searchBtn').click();
        }
    });
    
    // Filter changes
    $('#roleFilter, #departmentFilter').change(function() {
        currentPage = 1;
        loadDeletedUsers();
    });
    
    // Reset filters
    $('#resetBtn').click(function() {
        $('#searchInput').val('');
        $('#roleFilter').val('');
        $('#departmentFilter').val('');
        currentPage = 1;
        loadDeletedUsers();
    });
    
    // Load departments function
    function loadDepartments() {
        $.get('php_action/get_departments.php')
            .done(function(response) {
                if (response.success) {
                    const select = $('#departmentFilter');
                    select.find('option:not(:first)').remove();
                    
                    response.data.forEach(function(dept) {
                        select.append(`<option value="${dept.department_id}">${dept.department_name}</option>`);
                    });
                }
            })
            .fail(function() {
                console.error('فشل في تحميل الأقسام');
            });
    }
    
    // Load deleted users
    function loadDeletedUsers(page = 1) {
        currentPage = page;
        
        const params = {
            page: page,
            search: $('#searchInput').val(),
            role: $('#roleFilter').val(),
            department_id: $('#departmentFilter').val()
        };
        
        $('#loadingIndicator').show();
        $('#usersContainer').hide();
        $('#noDataMessage').hide();
        
        $.get('php_action/get_deleted_users.php', params)
            .done(function(response) {
                $('#loadingIndicator').hide();
                
                if (response.success && response.data.length > 0) {
                    renderDeletedUsers(response.data);
                    renderPagination(response.pagination);
                    $('#usersContainer').show();
                } else {
                    $('#noDataMessage').show();
                }
            })
            .fail(function(xhr) {
                $('#loadingIndicator').hide();
                console.error('Failed to load deleted users:', xhr.responseText);
                
                let errorMsg = 'فشل في تحميل المستخدمين المحذوفين';
                if (xhr.status === 401) {
                    errorMsg = 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.';
                    setTimeout(() => window.location.href = 'login.php', 2000);
                }
                
                showAlert(errorMsg, 'danger');
            });
    }
    
    // Render deleted users table
    function renderDeletedUsers(users) {
        const tbody = $('#deletedUsersTableBody');
        tbody.empty();
        
        users.forEach(function(user) {
            const roleBadge = getRoleBadge(user.role);
            const deletedDate = user.updated_at_formatted;
            
            const actionButtons = `
                <a href="view_user.php?id=${user.user_id}&deleted=1" class="btn btn-info btn-xs" title="عرض">
                    <i class="glyphicon glyphicon-eye-open"></i>
                </a>
                <button class="btn btn-success btn-xs restore-user" data-id="${user.user_id}" data-name="${user.full_name}" title="استعادة">
                    <i class="glyphicon glyphicon-refresh"></i>
                </button>
            `;
            
            const row = `
                <tr style="opacity: 0.7;">
                    <td><strong>${user.username}</strong></td>
                    <td>${user.full_name}</td>
                    <td>${user.email}</td>
                    <td>${roleBadge}</td>
                    <td>${user.department_name}</td>
                    <td><small class="text-muted">${deletedDate}</small></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            ${actionButtons}
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
                return '<span class="label label-danger" style="opacity: 0.7;">مشرف عام</span>';
            case 'admin':
                return '<span class="label label-warning" style="opacity: 0.7;">مشرف</span>';
            default:
                return '<span class="label label-info" style="opacity: 0.7;">مستخدم عادي</span>';
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
        paginationHtml += `<p class="text-muted">صفحة ${pagination.current_page} من ${pagination.total_pages} (إجمالي ${pagination.total_records} مستخدم محذوف)</p>`;
        
        container.html(paginationHtml);
        
        // Pagination click handlers
        container.find('a[data-page]').click(function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            loadDeletedUsers(page);
        });
    }
    
    // Deleted users view - no restore functionality
    
    // Restore user functionality
    $(document).on('click', '.restore-user', function() {
        const userId = $(this).data('id');
        const userName = $(this).data('name');
        
        $('#userToRestore').html(`<strong>المستخدم:</strong> ${userName}`);
        $('#confirmRestoreBtn').data('id', userId);
        $('#restoreModal').modal('show');
    });
    
    $('#confirmRestoreBtn').click(function() {
        const userId = $(this).data('id');
        
        $.post('php_action/restore_user.php', {
            user_id: userId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
        .done(function(response) {
            $('#restoreModal').modal('hide');
            
            if (response.success) {
                showAlert(response.message, 'success');
                loadDeletedUsers(currentPage);
            } else {
                showAlert(response.error, 'danger');
            }
        })
        .fail(function() {
            $('#restoreModal').modal('hide');
            showAlert('فشل في استعادة المستخدم', 'danger');
        });
    });
    
    // Reset filters
    $('#resetBtn').click(function() {
        $('#searchInput').val('');
        $('#roleFilter').val('');
        $('#departmentFilter').val('');
        currentPage = 1;
        loadDeletedUsers();
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
</script>

<style>
/* Deleted items styling */
#deletedUsersTable tbody tr {
    background-color: #fff9f9;
}

#deletedUsersTable tbody tr:hover {
    background-color: #fff0f0 !important;
}

/* Restore button animation */
.restore-user:hover {
    transform: rotate(180deg);
    transition: transform 0.3s ease;
}

/* Label badges with reduced opacity */
.label {
    opacity: 0.8;
}
</style>

<?php include 'includes/footer.php'; ?> 