<?php
$pageTitle = 'التراخيص المحذوفة';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Require login and edit permissions to access this page
requireLogin();

// Check if user has permission to view/restore deleted licenses
$canAccessDeleted = getUserRole() === 'super_admin' || 
                   hasPermission('licenses_delete') || 
                   hasPermission('personal_licenses_delete') || 
                   hasPermission('vehicle_licenses_delete');

if (!$canAccessDeleted) {
    setMessage('غير مصرح لك بالوصول لهذه الصفحة', 'danger');
    header('Location: dashboard.php');
    exit;
}

$userRole = getUserRole();
$canEdit = $canAccessDeleted; // Use the same permission check

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
                            <h4><i class="glyphicon glyphicon-trash"></i> التراخيص المحذوفة</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="licenses.php" class="btn btn-primary">
                                <i class="glyphicon glyphicon-arrow-right"></i> العودة للتراخيص النشطة
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <!-- Info Alert -->
                    <div class="alert alert-info">
                        <i class="glyphicon glyphicon-info-sign"></i>
                        <strong>ملاحظة:</strong> هذه الصفحة تعرض التراخيص المحذوفة مؤقتاً. يمكن استعادتها أو حذفها نهائياً.
                    </div>
                    
                    <!-- Search and Filter Section -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-3">
                            <input type="text" id="searchInput" class="form-control" placeholder="البحث في التراخيص المحذوفة...">
                        </div>
                        <div class="col-md-2">
                            <select id="licenseTypeFilter" class="form-control">
                                <option value="all">جميع التراخيص</option>
                                <option value="personal">رخص القيادة الشخصية</option>
                                <option value="vehicle">رخص المركبات</option>
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
                    
                    <!-- Statistics Section -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-4">
                            <div class="alert alert-info text-center">
                                <h5><strong id="personalCount">0</strong> رخصة قيادة شخصية محذوفة</h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-warning text-center">
                                <h5><strong id="vehicleCount">0</strong> رخصة مركبة محذوفة</h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-danger text-center">
                                <h5><strong id="totalCount">0</strong> إجمالي التراخيص المحذوفة</h5>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading indicator -->
                    <div id="loadingIndicator" class="text-center" style="display: none;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p>جاري تحميل التراخيص المحذوفة...</p>
                    </div>
                    
                    <!-- Deleted Licenses Table -->
                    <div id="licensesContainer">
                        <div class="table-responsive table-container">
                            <table id="deletedLicensesTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>نوع الترخيص</th>
                                        <th>رقم الترخيص</th>
                                        <th>الاسم/رقم المركبة</th>
                                        <th>القسم</th>
                                        <th>المشروع</th>
                                        <th>تاريخ الإصدار</th>
                                        <th>تاريخ الانتهاء</th>
                                        <th>تاريخ الحذف</th>
                                        <th>الحالة السابقة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="deletedLicensesTableBody">
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
                        <h4 style="color: #5cb85c;">لا توجد تراخيص محذوفة</h4>
                        <p class="text-muted">جميع التراخيص نشطة حالياً</p>
                        <a href="licenses.php" class="btn btn-primary">
                            <i class="glyphicon glyphicon-list"></i> عرض التراخيص النشطة
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
                <p>هل أنت متأكد من استعادة هذا الترخيص؟</p>
                <div id="licenseToRestore" class="alert alert-success"></div>
                <small class="text-muted">سيتم إرجاع الترخيص للقائمة النشطة.</small>
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
    loadDeletedLicenses();
    
    // Search functionality
    $('#searchBtn').click(function() {
        currentPage = 1;
        loadDeletedLicenses();
    });
    
    // Enter key for search
    $('#searchInput').keypress(function(e) {
        if (e.which === 13) {
            $('#searchBtn').click();
        }
    });
    
    // Filter changes
    $('#departmentFilter').change(function() {
        currentPage = 1;
        loadDeletedLicenses();
    });
    
    // Reset filters
    $('#resetBtn').click(function() {
        $('#searchInput').val('');
        $('#departmentFilter').val('');
        currentPage = 1;
        loadDeletedLicenses();
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
    
    // Update statistics from API response
    function updateStatistics(stats) {
        $('#personalCount').text(stats.personal_count || 0);
        $('#vehicleCount').text(stats.vehicle_count || 0);
        $('#totalCount').text((stats.personal_count || 0) + (stats.vehicle_count || 0));
    }
    
    // Load deleted licenses
    function loadDeletedLicenses(page = 1) {
        currentPage = page;
        
        const params = {
            page: page,
            search: $('#searchInput').val(),
            department_id: $('#departmentFilter').val(),
            type: $('#licenseTypeFilter').val()
        };
        
        $('#loadingIndicator').show();
        $('#licensesContainer').hide();
        $('#noDataMessage').hide();
        
        $.get('php_action/get_deleted_licenses.php', params)
            .done(function(response) {
                $('#loadingIndicator').hide();
                
                if (response.success && response.data.length > 0) {
                    renderDeletedLicenses(response.data);
                    renderPagination(response.pagination);
                    updateStatistics(response.stats);
                    $('#licensesContainer').show();
                } else {
                    updateStatistics({personal_count: 0, vehicle_count: 0});
                    $('#noDataMessage').show();
                }
            })
            .fail(function(xhr) {
                $('#loadingIndicator').hide();
                console.error('Failed to load deleted licenses:', xhr.responseText);
                
                let errorMsg = 'فشل في تحميل التراخيص المحذوفة';
                if (xhr.status === 401) {
                    errorMsg = 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.';
                    setTimeout(() => window.location.href = 'login.php', 2000);
                }
                
                showAlert(errorMsg, 'danger');
            });
    }
    
    // Render deleted licenses table
    function renderDeletedLicenses(licenses) {
        const tbody = $('#deletedLicensesTableBody');
        tbody.empty();
        
        licenses.forEach(function(license) {
            const statusClass = getStatusClass(license.previous_status);
            const expirationDate = new Date(license.expiration_date).toLocaleDateString('en-GB');
            const issueDate = new Date(license.issue_date).toLocaleDateString('en-GB');
            const deletedDate = new Date(license.updated_at).toLocaleDateString('en-GB');
            
            // Determine display values based on license type
            let licenseNumber, displayName, licenseTypeArabic;
            if (license.license_type === 'personal') {
                licenseNumber = license.license_number;
                displayName = license.full_name;
                licenseTypeArabic = 'رخصة قيادة شخصية';
            } else {
                licenseNumber = license.car_number;
                displayName = license.vehicle_type;
                licenseTypeArabic = 'رخصة مركبة';
            }
            
            const actionButtons = `
                <a href="view_license.php?id=${license.license_id}&type=${license.license_type}&deleted=1" class="btn btn-info btn-xs" title="عرض">
                    <i class="glyphicon glyphicon-eye-open"></i>
                </a>
                <button class="btn btn-success btn-xs restore-license" data-id="${license.license_id}" data-type="${license.license_type}" data-name="${displayName}" data-number="${licenseNumber}" title="استعادة">
                    <i class="glyphicon glyphicon-refresh"></i>
                </button>
            `;
            
            const row = `
                <tr style="opacity: 0.7;">
                    <td><span class="label label-info">${licenseTypeArabic}</span></td>
                    <td><strong>${licenseNumber}</strong></td>
                    <td>${displayName}</td>
                    <td>${license.department_name}</td>
                    <td>${license.project_name || 'غير محدد'}</td>
                    <td>${issueDate}</td>
                    <td>${expirationDate}</td>
                    <td><small class="text-muted">${deletedDate}</small></td>
                    <td><span class="status-badge ${statusClass}">${license.previous_status}</span></td>
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
        paginationHtml += `<p class="text-muted">صفحة ${pagination.current_page} من ${pagination.total_pages} (إجمالي ${pagination.total_records} سجل محذوف)</p>`;
        
        container.html(paginationHtml);
        
        // Pagination click handlers
        container.find('a[data-page]').click(function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            loadDeletedLicenses(page);
        });
    }
    
    // Get status CSS class
    function getStatusClass(status) {
        switch (status) {
            case 'منتهي الصلاحية': return 'status-expired';
            case 'ينتهي قريباً': return 'status-expiring';
            case 'نشط': return 'status-active';
            default: return 'status-active';
        }
    }
    
    // Restore license functionality
    $(document).on('click', '.restore-license', function() {
        const licenseId = $(this).data('id');
        const licenseType = $(this).data('type');
        const licenseName = $(this).data('name');
        const licenseNumber = $(this).data('number');
        
        $('#licenseToRestore').html(`<strong>ترخيص لـ:</strong> ${licenseName} (رقم: ${licenseNumber})`);
        $('#confirmRestoreBtn').data('id', licenseId);
        $('#confirmRestoreBtn').data('type', licenseType);
        $('#restoreModal').modal('show');
    });
    
    $('#confirmRestoreBtn').click(function() {
        const licenseId = $(this).data('id');
        const licenseType = $(this).data('type');
        
        $.post('php_action/restore_license.php', {
            license_id: licenseId,
            license_type: licenseType,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
        .done(function(response) {
            $('#restoreModal').modal('hide');
            
            if (response.success) {
                showAlert(response.message, 'success');
                loadDeletedLicenses(currentPage);
            } else {
                showAlert(response.error, 'danger');
            }
        })
        .fail(function() {
            $('#restoreModal').modal('hide');
            showAlert('فشل في استعادة الترخيص', 'danger');
        });
    });
    
    // Reset filters
    $('#resetBtn').click(function() {
        $('#searchInput').val('');
        $('#typeFilter').val('');
        $('#departmentFilter').val('');
        currentPage = 1;
        loadDeletedLicenses();
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
#deletedLicensesTable tbody tr {
    background-color: #fff9f9;
}

#deletedLicensesTable tbody tr:hover {
    background-color: #fff0f0 !important;
}

/* Status Badge Colors */
.status-badge {
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 0.5px;
    display: inline-block;
    text-align: center;
    min-width: 80px;
    opacity: 0.7;
}

.status-active {
    background-color: #5cb85c;
    color: white;
}

.status-expiring {
    background-color: #f0ad4e;
    color: white;
}

.status-expired {
    background-color: #d9534f;
    color: white;
}

/* Action Buttons */
.btn-group-sm .btn {
    margin-left: 2px;
}

/* Restore button animation */
.restore-license:hover {
    transform: rotate(180deg);
    transition: transform 0.3s ease;
}
</style>

<?php include 'includes/footer.php'; ?> 