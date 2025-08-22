<?php
$pageTitle = 'الأقسام المحذوفة';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Only super admin can access this page
requireRole('super_admin');

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
                            <h4><i class="glyphicon glyphicon-trash"></i> الأقسام المحذوفة</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="departments.php" class="btn btn-primary">
                                <i class="glyphicon glyphicon-arrow-right"></i> العودة للأقسام النشطة
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <!-- Info Alert -->
                    <div class="alert alert-info">
                        <i class="glyphicon glyphicon-info-sign"></i>
                        <strong>ملاحظة:</strong> هذه الصفحة تعرض الأقسام المحذوفة مؤقتاً. يمكن استعادتها بسهولة.
                    </div>
                    
                    <!-- Search and Filter Section -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-4">
                            <input type="text" id="searchInput" class="form-control" placeholder="البحث في الأقسام المحذوفة...">
                        </div>
                        <div class="col-md-3">
                            <select id="projectFilter" class="form-control">
                                <option value="">جميع المشاريع</option>
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
                        <p>جاري تحميل الأقسام المحذوفة...</p>
                    </div>
                    
                    <!-- Deleted Departments Table -->
                    <div id="departmentsContainer">
                        <div class="table-responsive table-container">
                            <table id="deletedDepartmentsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>اسم القسم</th>
                                        <th>المشروع</th>
                                        <th>الوصف</th>
                                        <th>عدد الموظفين</th>
                                        <th>عدد التراخيص</th>
                                        <th>تاريخ الحذف</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="deletedDepartmentsTableBody">
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
                        <h4 style="color: #5cb85c;">لا يوجد أقسام محذوفة</h4>
                        <p class="text-muted">جميع الأقسام نشطة حالياً</p>
                        <a href="departments.php" class="btn btn-primary">
                            <i class="glyphicon glyphicon-list"></i> عرض الأقسام النشطة
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
                <p>هل أنت متأكد من استعادة هذا القسم؟</p>
                <div id="departmentToRestore" class="alert alert-success"></div>
                <small class="text-muted">سيتم إعادة تفعيل القسم وإرجاعه للقائمة النشطة.</small>
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
    loadProjects();
    loadDeletedDepartments();
    
    // Search functionality
    $('#searchBtn').click(function() {
        currentPage = 1;
        loadDeletedDepartments();
    });
    
    // Enter key for search
    $('#searchInput').keypress(function(e) {
        if (e.which === 13) {
            $('#searchBtn').click();
        }
    });
    
    // Filter changes
    $('#projectFilter').change(function() {
        currentPage = 1;
        loadDeletedDepartments();
    });
    
    // Reset filters
    $('#resetBtn').click(function() {
        $('#searchInput').val('');
        $('#projectFilter').val('');
        currentPage = 1;
        loadDeletedDepartments();
    });
    
    // Load projects function
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
            })
            .fail(function() {
                console.error('فشل في تحميل المشاريع');
            });
    }
    
    // Load deleted departments
    function loadDeletedDepartments(page = 1) {
        currentPage = page;
        
        const params = {
            page: page,
            search: $('#searchInput').val(),
            project_id: $('#projectFilter').val()
        };
        
        $('#loadingIndicator').show();
        $('#departmentsContainer').hide();
        $('#noDataMessage').hide();
        
        $.get('php_action/get_deleted_departments.php', params)
            .done(function(response) {
                $('#loadingIndicator').hide();
                
                if (response.success && response.data.length > 0) {
                    renderDeletedDepartments(response.data);
                    renderPagination(response.pagination);
                    $('#departmentsContainer').show();
                } else {
                    $('#noDataMessage').show();
                }
            })
            .fail(function(xhr) {
                $('#loadingIndicator').hide();
                console.error('Failed to load deleted departments:', xhr.responseText);
                
                let errorMsg = 'فشل في تحميل الأقسام المحذوفة';
                if (xhr.status === 401) {
                    errorMsg = 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.';
                    setTimeout(() => window.location.href = 'login.php', 2000);
                }
                
                showAlert(errorMsg, 'danger');
            });
    }
    
    // Render deleted departments table
    function renderDeletedDepartments(departments) {
        const tbody = $('#deletedDepartmentsTableBody');
        tbody.empty();
        
        departments.forEach(function(dept) {
            const deletedDate = dept.updated_at_formatted;
            const description = dept.department_description && dept.department_description !== 'غير محدد' ? 
                dept.department_description.substring(0, 50) + '...' : 'غير محدد';
            
            const actionButtons = `
                <a href="view_department.php?id=${dept.department_id}&deleted=1" class="btn btn-info btn-xs" title="عرض">
                    <i class="glyphicon glyphicon-eye-open"></i>
                </a>
                <button class="btn btn-success btn-xs restore-department" data-id="${dept.department_id}" 
                        data-name="${dept.department_name}" data-project="${dept.project_name}" title="استعادة">
                    <i class="glyphicon glyphicon-refresh"></i>
                </button>

            `;
            
            const row = `
                <tr style="opacity: 0.7;">
                    <td><strong>${dept.department_name}</strong></td>
                    <td>${dept.project_name}</td>
                    <td><small>${description}</small></td>
                    <td><span class="badge badge-info" style="opacity: 0.7;">${dept.users_count || 0}</span></td>
                    <td><span class="badge badge-primary" style="opacity: 0.7;">${dept.licenses_count || 0}</span></td>
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
        paginationHtml += `<p class="text-muted">صفحة ${pagination.current_page} من ${pagination.total_pages} (إجمالي ${pagination.total_records} قسم محذوف)</p>`;
        
        container.html(paginationHtml);
        
        // Pagination click handlers
        container.find('a[data-page]').click(function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            loadDeletedDepartments(page);
        });
    }
    
    // Restore department functionality
    $(document).on('click', '.restore-department', function() {
        const deptId = $(this).data('id');
        const deptName = $(this).data('name');
        const projectName = $(this).data('project');
        
        $('#departmentToRestore').html(`
            <strong>القسم:</strong> ${deptName}<br>
            <strong>المشروع:</strong> ${projectName}
        `);
        $('#confirmRestoreBtn').data('id', deptId);
        $('#restoreModal').modal('show');
    });
    
    $('#confirmRestoreBtn').click(function() {
        const deptId = $(this).data('id');
        
        $.post('php_action/restore_department.php', {
            department_id: deptId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
        .done(function(response) {
            $('#restoreModal').modal('hide');
            
            if (response.success) {
                showAlert(response.message, 'success');
                loadDeletedDepartments(currentPage);
            } else {
                showAlert(response.error, 'danger');
            }
        })
        .fail(function() {
            $('#restoreModal').modal('hide');
            showAlert('فشل في استعادة القسم', 'danger');
        });
    });
    
    // Reset filters
    $('#resetBtn').click(function() {
        $('#searchInput').val('');
        $('#projectFilter').val('');
        currentPage = 1;
        loadDeletedDepartments();
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
#deletedDepartmentsTable tbody tr {
    background-color: #fff9f9;
}

#deletedDepartmentsTable tbody tr:hover {
    background-color: #fff0f0 !important;
}

/* Restore button animation */
.restore-department:hover {
    transform: rotate(180deg);
    transition: transform 0.3s ease;
}

/* Badge styling for deleted items */
.badge {
    font-size: 11px;
    padding: 3px 6px;
}
</style>

<?php include 'includes/footer.php'; ?> 