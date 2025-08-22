<?php
$pageTitle = 'إدارة الأقسام';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check if user has any permission related to departments
$canAccessDepartments = getUserRole() === 'super_admin' || 
                        hasPermission('departments_view') || 
                        hasPermission('departments_add') || 
                        hasPermission('departments_edit') || 
                        hasPermission('departments_delete');

if (!$canAccessDepartments) {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بالوصول إلى هذه الصفحة', 'danger');
    exit;
}

include 'includes/header.php';

// Pass permissions to JavaScript
$canEditDepartments = hasPermission('departments_edit') || getUserRole() === 'super_admin';
$canDeleteDepartments = hasPermission('departments_delete') || getUserRole() === 'super_admin';
$canAddDepartments = hasPermission('departments_add') || getUserRole() === 'super_admin';
?>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><i class="glyphicon glyphicon-home"></i> إدارة الأقسام</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <?php if (hasPermission('departments_add') || getUserRole() === 'super_admin'): ?>
                            <a href="add_department.php" class="btn btn-primary">
                                <i class="glyphicon glyphicon-plus"></i> إضافة قسم جديد
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <!-- Filter Section -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-4 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <select id="departmentFilter" class="form-control">
                                    <option value="">اختر قسم معين</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6 col-xs-12">
                            <div class="form-group">
                                <select id="projectFilter" class="form-control">
                                    <option value="">جميع المشاريع</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12 col-xs-12">
                            <div class="form-group">
                                <div class="btn-group btn-block" role="group">
                                    <a href="deleted_departments.php" class="btn btn-warning">
                                        <i class="glyphicon glyphicon-trash"></i> <span class="hidden-xs">الأقسام المحذوفة</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading indicator -->
                    <div id="loadingIndicator" class="text-center" style="display: none;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p>جاري تحميل الأقسام...</p>
                    </div>
                    
                    <!-- Departments Table -->
                    <div id="departmentsContainer">
                                            <div class="table-responsive table-container">
                        <table class="table table-hover">
                                <thead>
                                    <tr id="dept-${dept.department_id}-proj-${dept.project_id}">
                                        <th>اسم القسم</th>
                                        <th class="hidden-xs">المشروع</th>
                                        <th class="text-center">الموظفون</th>
                                        <th class="text-center">التراخيص</th>
                                        <th class="hidden-xs">الحالة</th>
                                        <th class="hidden-xs hidden-sm">تاريخ الإنشاء</th>
                                        <th class="text-center">الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="departmentsTableBody">
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
                        <i class="glyphicon glyphicon-home" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                        <h4>لا توجد أقسام</h4>
                        <p class="text-muted">لم يتم العثور على أقسام تطابق معايير البحث الحالية</p>
                        <?php if (hasPermission('departments_add') || getUserRole() === 'super_admin'): ?>
                        <a href="add_department.php" class="btn btn-primary">
                            <i class="glyphicon glyphicon-plus"></i> إضافة أول قسم
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
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">تأكيد الحذف</h4>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من حذف هذا القسم؟</p>
                <div id="departmentToDelete" class="alert alert-warning"></div>
                <div id="deleteWarnings" class="alert alert-danger" style="display: none;"></div>
                <small class="text-muted">ملاحظة: سيتم حذف القسم مؤقتاً ويمكن استعادته لاحقاً.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger">حذف القسم</button>
            </div>
        </div>
    </div>
</div>

<script>
// Pass PHP permissions to JavaScript
const canEditDepartments = <?php echo $canEditDepartments ? 'true' : 'false'; ?>;
const canDeleteDepartments = <?php echo $canDeleteDepartments ? 'true' : 'false'; ?>;
const canAddDepartments = <?php echo $canAddDepartments ? 'true' : 'false'; ?>;

$(document).ready(function() {
    let currentPage = 1;
    
    // Load initial data
    loadProjects();
    loadDepartmentsDropdown();
    loadDepartments();
    
    // Filter changes - automatic on change for project filter
    $('#projectFilter').on('change', function() {
        currentPage = 1;
        loadDepartments();
    });
    
    // Department filter selection
    $('#departmentFilter').on('change', function() {
        currentPage = 1;
        loadDepartments();
    });
    
    // Load projects function
    function loadProjects() {
        $.ajax({url: 'php_action/get_projects.php', dataType: 'json'})
            .done(function(response) {
                console.log('Projects response:', response); // Debug line
                if (response.success) {
                    const select = $('#projectFilter');
                    select.find('option:not(:first)').remove();
                    
                    response.data.forEach(function(project) {
                        console.log('Adding project:', project.project_name);
                        var optionText = project.project_name || "Project " + project.project_id;
                        var newOption = '<option value="' + project.project_id + '">' + optionText + '</option>';
                        select.append(newOption);
                    });
                } else {
                    console.error('Projects API returned success: false');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('فشل في تحميل المشاريع:', status, error);
                console.error('Response text:', xhr.responseText);
            });
    }
    
    // Load departments for dropdown
    function loadDepartmentsDropdown() {
        $.get('php_action/get_unique_departments.php')
            .done(function(response) {
                if (response.success) {
                    const select = $('#departmentFilter');
                    select.find('option:not(:first)').remove();
                    
                    response.data.forEach(function(dept) {
                        select.append('<option value="' + dept.department_name + '">' + dept.department_name + '</option>');
                    });
                }
            })
            .fail(function() {
                console.error('فشل في تحميل الأقسام للقائمة المنسدلة');
            });
    }
    
    // Load departments
    function loadDepartments(page = 1) {
        currentPage = page;
        
        const params = {
            page: page,
            department_id: $('#departmentFilter').val(),
            project_id: $('#projectFilter').val()
        };
        
        $('#loadingIndicator').show();
        $('#departmentsContainer').hide();
        $('#noDataMessage').hide();
        
        $.get('php_action/get_departments.php', params)
            .done(function(response) {
                $('#loadingIndicator').hide();
                
                if (response.success && response.data.length > 0) {
                    renderDepartments(response.data);
                    renderPagination(response.pagination);
                    $('#departmentsContainer').show();
                } else {
                    $('#noDataMessage').show();
                }
            })
            .fail(function(xhr) {
                $('#loadingIndicator').hide();
                console.error('Failed to load departments:', xhr.responseText);
                
                let errorMsg = 'فشل في تحميل الأقسام';
                if (xhr.status === 401) {
                    errorMsg = 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.';
                    setTimeout(() => window.location.href = 'login.php', 2000);
                }
                
                showAlert(errorMsg, 'danger');
            });
    }
    
    // Render departments table
    function renderDepartments(departments) {
        const tbody = $('#departmentsTableBody');
        tbody.empty();
        
        departments.forEach(function(dept) {
            const statusBadge = dept.is_active ? 
                '<span class="label label-success">نشط</span>' :
                '<span class="label label-danger">غير نشط</span>';
            
            const createdDate = new Date(dept.created_at).toLocaleDateString('ar-EG');
            
            let actionButtons = `
                <a href="view_department.php?id=${dept.department_id}" class="btn btn-info btn-xs" title="عرض">
                    <i class="glyphicon glyphicon-eye-open"></i>
                </a>
            `;
            
            if (canEditDepartments) {
                actionButtons += `
                    <a href="edit_department.php?id=${dept.department_id}" class="btn btn-warning btn-xs" title="تعديل">
                        <i class="glyphicon glyphicon-edit"></i>
                    </a>
                `;
            }
            
            if (canDeleteDepartments) {
                actionButtons += `
                    <button class="btn btn-danger btn-xs delete-department" data-id="${dept.department_id}" 
                            data-name="${dept.department_name}" data-users="${dept.users_count}" 
                            data-licenses="${dept.licenses_count}" title="حذف">
                        <i class="glyphicon glyphicon-remove"></i>
                    </button>
                `;
            }
            
            const row = `
                <tr id="dept-${dept.department_id}-proj-${dept.project_id}">
                    <td>
                        <strong>${dept.department_name}</strong>
                        <div class="visible-xs">
                            <small class="text-muted">${dept.project_name || 'غير محدد'}</small><br>
                            ${statusBadge}
                        </div>
                    </td>
                    <td class="hidden-xs">
                        ${dept.project_name === 'غير محدد' ? 
                            '<span class="label label-default">غير محدد</span>' : 
                            dept.project_name}
                    </td>
                    <td class="text-center">
                        <span class="badge badge-info">${dept.users_count || 0}</span>
                        <span class="visible-xs-inline-block text-muted" style="font-size: 10px;"> موظف</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-primary">${dept.licenses_count || 0}</span>
                        <span class="visible-xs-inline-block text-muted" style="font-size: 10px;"> ترخيص</span>
                    </td>
                    <td class="hidden-xs">${statusBadge}</td>
                    <td class="hidden-xs hidden-sm"><small>${createdDate}</small></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            ${actionButtons}
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
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
        paginationHtml += `<p class="text-muted">صفحة ${pagination.current_page} من ${pagination.total_pages} (إجمالي ${pagination.total_records} قسم)</p>`;
        
        container.html(paginationHtml);
        
        // Pagination click handlers
        container.find('a[data-page]').click(function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            loadDepartments(page);
        });
    }
    
    // Delete department functionality
    $(document).on('click', '.delete-department', function() {
        const deptId = $(this).data('id');
        const deptName = $(this).data('name');
        const usersCount = $(this).data('users');
        const licensesCount = $(this).data('licenses');
        
        $('#departmentToDelete').html(`<strong>القسم:</strong> ${deptName}`);
        
        let warnings = '';
        if (usersCount > 0) {
            warnings += `<li>يوجد ${usersCount} موظف مرتبط بهذا القسم</li>`;
        }
        if (licensesCount > 0) {
            warnings += `<li>يوجد ${licensesCount} ترخيص مرتبط بهذا القسم</li>`;
        }
        
        if (warnings) {
            $('#deleteWarnings').html(`
                <strong>تحذير:</strong> لا يمكن حذف هذا القسم للأسباب التالية:
                <ul>${warnings}</ul>
                يرجى نقل الموظفين والتراخيص أولاً.
            `).show();
            $('#confirmDeleteBtn').prop('disabled', true).text('لا يمكن الحذف');
        } else {
            $('#deleteWarnings').hide();
            $('#confirmDeleteBtn').prop('disabled', false).text('حذف القسم');
        }
        
        $('#confirmDeleteBtn').data('id', deptId);
        $('#deleteModal').modal('show');
    });
    
    $('#confirmDeleteBtn').click(function() {
        if ($(this).prop('disabled')) return;
        
        const deptId = $(this).data('id');
        
        console.log('Sending delete request for department ID:', deptId);
        
        $.post('php_action/delete_department.php', {
            department_id: deptId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
        
        .done(function(response) {
            console.log('Delete response received:', response);
            $('#deleteModal').modal('hide');
            
            if (response.success) {
                showAlert(response.message, 'success');
                loadDepartments(currentPage);
            } else {
                showAlert(response.error || response.message || 'حدث خطأ غير معروف', 'danger');
                console.error('Delete failed:', response);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Delete request failed:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                statusCode: xhr.status
            });
            $('#deleteModal').modal('hide');
            
            let errorMsg = 'فشل في حذف القسم';
            if (xhr.status === 403) {
                errorMsg = 'غير مصرح لك بحذف الأقسام';
            } else if (xhr.status === 500) {
                errorMsg = 'خطأ في الخادم - راجع سجل الأخطاء';
            }
            
            showAlert(errorMsg, 'danger');
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
</script>

<style>
/* Custom styling for departments page */
.badge {
    font-size: 11px;
    padding: 3px 6px;
}

.table td {
    vertical-align: middle;
}

.btn-group-sm .btn {
    margin-left: 2px;
}

/* Table hover effects */
.table-hover tbody tr:hover {
    background-color: #f5f5f5;
}

/* Status badges */
.label {
    font-size: 11px;
    padding: 4px 8px;
}
</style>

<?php include 'includes/footer.php'; ?> 
