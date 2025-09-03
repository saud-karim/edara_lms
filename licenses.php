<?php
$pageTitle = 'إدارة التراخيص';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Require login to access this page
requireLogin();

// Check if user has any permission related to licenses
$canAccessLicenses = hasPermission('licenses_view') || 
                     hasPermission('personal_licenses_view') || 
                     hasPermission('vehicle_licenses_view') ||
                     hasPermission('licenses_add') ||
                     hasPermission('personal_licenses_add') ||
                     hasPermission('vehicle_licenses_add') ||
                     hasPermission('licenses_edit') ||
                     hasPermission('personal_licenses_edit') ||
                     hasPermission('vehicle_licenses_edit') ||
                     hasPermission('licenses_delete') ||
                     hasPermission('personal_licenses_delete') ||
                     hasPermission('vehicle_licenses_delete');

if (!$canAccessLicenses) {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بالوصول إلى هذه الصفحة', 'danger');
    exit;
}

$userRole = getUserRole();
$canEdit = hasPermission('licenses_edit') || hasPermission('licenses_add');

// Improved permission logic - specific permissions take priority
$hasVehicleView = hasPermission('vehicle_licenses_view');
$hasPersonalView = hasPermission('personal_licenses_view');
$hasGeneralView = hasPermission('licenses_view');

// If user has specific permissions, use those ONLY
// If user has general permission only, use it for both
if ($hasVehicleView || $hasPersonalView) {
    // User has specific permissions - be strict
    $canViewVehicle = $hasVehicleView;
    $canViewPersonal = $hasPersonalView;
    $canAddVehicle = hasPermission('vehicle_licenses_add');
    $canAddPersonal = hasPermission('personal_licenses_add');
    $canEditVehicle = hasPermission('vehicle_licenses_edit') || hasPermission('vehicle_licenses_view');
    $canEditPersonal = hasPermission('personal_licenses_edit') || hasPermission('personal_licenses_view');
} else {
    // User only has general permissions - apply to both
    $canViewVehicle = $hasGeneralView;
    $canViewPersonal = $hasGeneralView;
    $canAddVehicle = hasPermission('licenses_add');
    $canAddPersonal = hasPermission('licenses_add');
    $canEditVehicle = hasPermission('licenses_edit') || hasPermission('licenses_view');
    $canEditPersonal = hasPermission('licenses_edit') || hasPermission('licenses_view');
}

include 'includes/header.php';
?>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><i class="glyphicon glyphicon-list-alt"></i> إدارة التراخيص</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <?php if ($canAddPersonal): ?>
                                <a href="add_license.php?type=personal" class="btn btn-primary">
                                    <i class="glyphicon glyphicon-plus"></i> إضافة رخصة قيادة
                                </a>
                            <?php endif; ?>
                            <?php if ($canAddVehicle): ?>
                                <a href="add_license.php?type=vehicle" class="btn btn-warning">
                                    <i class="glyphicon glyphicon-plus"></i> إضافة رخصة مركبة
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <!-- License Type Tabs -->
                    <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 20px;">
                        <?php if ($canViewPersonal): ?>
                        <li role="presentation" class="active">
                            <a href="#personal-licenses" aria-controls="personal-licenses" role="tab" data-toggle="tab">
                                <i class="glyphicon glyphicon-user"></i> رخص القيادة الشخصية
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($canViewVehicle): ?>
                        <li role="presentation" <?php echo !$canViewPersonal ? 'class="active"' : ''; ?>>
                            <a href="#vehicle-licenses" aria-controls="vehicle-licenses" role="tab" data-toggle="tab">
                                <i class="glyphicon glyphicon-road"></i> رخص المركبات
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <?php if ($canViewPersonal): ?>
                        <!-- Personal Licenses Tab -->
                        <div role="tabpanel" class="tab-pane active" id="personal-licenses">
                            <?php include 'includes/personal_licenses_tab.php'; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($canViewVehicle): ?>
                        <!-- Vehicle Licenses Tab -->
                        <div role="tabpanel" class="tab-pane <?php echo !$canViewPersonal ? 'active' : ''; ?>" id="vehicle-licenses">
                            <?php include 'includes/vehicle_licenses_tab.php'; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- نافذة تأكيد الحذف -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">تأكيد الحذف</h4>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من أنك تريد حذف هذا الترخيص؟ لا يمكن التراجع عن هذا الإجراء.</p>
                <div id="licenseToDelete"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">حذف</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // CSRF Token for security
    const csrfToken = '<?php echo generateCSRFToken(); ?>';
    
    let currentPersonalPage = 1;
    let currentVehiclePage = 1;
    
    // Determine which tab is active based on URL parameters first, then permissions
    let currentActiveTab = 'none';
    
    // Get URL parameters early to determine initial tab
    const urlParams = new URLSearchParams(window.location.search);
    const typeFilter = urlParams.get('type');
    
    if (typeFilter === 'vehicle' && <?php echo $canViewVehicle ? 'true' : 'false'; ?>) {
        currentActiveTab = 'vehicle';
    } else if (typeFilter === 'personal' && <?php echo $canViewPersonal ? 'true' : 'false'; ?>) {
        currentActiveTab = 'personal';
    } else {
        // Default behavior based on permissions
    <?php if ($canViewPersonal): ?>
            currentActiveTab = 'personal';
    <?php elseif ($canViewVehicle): ?>
            currentActiveTab = 'vehicle';
    <?php endif; ?>
    }
    
    // Initialize the page
    loadDepartments();
    loadProjects();
    
    // Check for URL parameters to set filters (urlParams and typeFilter already declared above)
    const statusFilter = urlParams.get('status');
    
    // Debug: Log URL parameters
    console.log('URL Parameters:', {
        typeFilter: typeFilter,
        statusFilter: statusFilter,
        currentActiveTab: currentActiveTab
    });
    
    // Apply URL filters if present
    if (statusFilter) {
        if (typeFilter === 'vehicle' || (!typeFilter && !<?php echo $canViewPersonal ? 'true' : 'false'; ?>)) {
            $('#vehicleStatusFilter').val(statusFilter);
            console.log('Applied vehicle status filter:', statusFilter);
        } else {
            $('#personalStatusFilter').val(statusFilter);
            console.log('Applied personal status filter:', statusFilter);
        }
    }
    
    // Switch to appropriate tab if specified and load data
    if (typeFilter === 'vehicle' && <?php echo $canViewVehicle ? 'true' : 'false'; ?>) {
        console.log('Switching to vehicle tab');
        // Update currentActiveTab
        currentActiveTab = 'vehicle';
        
        // Switch to vehicle tab with small delay to ensure DOM is ready
        setTimeout(function() {
            $('.nav-tabs li').removeClass('active');
            $('.tab-pane').removeClass('active');
            $('a[href="#vehicle-licenses"]').parent().addClass('active');
            $('#vehicle-licenses').addClass('active');
        }, 50);
        
        // Load vehicle data
        setTimeout(function() {
            console.log('Loading vehicle data after timeout');
            loadVehicleLicenses();
        }, 100);
        
    } else if (typeFilter === 'personal' && <?php echo $canViewPersonal ? 'true' : 'false'; ?>) {
        // Update currentActiveTab
        currentActiveTab = 'personal';
        
        // Switch to personal tab with small delay to ensure DOM is ready  
        setTimeout(function() {
            $('.nav-tabs li').removeClass('active');
            $('.tab-pane').removeClass('active');
            $('a[href="#personal-licenses"]').parent().addClass('active');
            $('#personal-licenses').addClass('active');
        }, 50);
        
        // Load personal data
        setTimeout(function() {
            loadPersonalLicenses();
        }, 100);
        
    } else {
        // Load default tab data
        if (<?php echo $canViewPersonal ? 'true' : 'false'; ?>) {
    loadPersonalLicenses();
        } else if (<?php echo $canViewVehicle ? 'true' : 'false'; ?>) {
        loadVehicleLicenses();
        }
    }
    
    // Tab switch event
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (e.target.getAttribute('aria-controls') === 'vehicle-licenses') {
            currentActiveTab = 'vehicle';
            loadVehicleLicenses();
        } else {
            currentActiveTab = 'personal';
            loadPersonalLicenses();
        }
    });
    
    // Search functionality for both tabs
    $('#personalSearchInput').on('keyup', function() {
        if (currentActiveTab === 'personal') {
            loadPersonalLicenses();
        }
    });
    
    // Vehicle search with car number formatting
    $('#vehicleSearchInput').on('input', function() {
        formatVehicleSearchInput(this);
        if (currentActiveTab === 'vehicle') {
            loadVehicleLicenses();
        }
    });
    
    // Format vehicle search input to match car number format
    function formatVehicleSearchInput(input) {
        let value = input.value;
        
        // Remove any non-Arabic letters and non-digits, keep spaces
        let cleaned = value.replace(/[^\u0600-\u06FF0-9\s]/g, '');
        
        // Split into numbers and letters
        let numbers = cleaned.match(/[0-9]+/g);
        let letters = cleaned.match(/[\u0600-\u06FF]+/g);
        
        if (numbers && letters) {
            // Combine numbers and format letters with spaces
            let numberPart = numbers.join('');
            let letterPart = letters.join('');
            
            // Add spaces between letters
            let spacedLetters = '';
            for (let i = 0; i < letterPart.length; i++) {
                spacedLetters += letterPart[i];
                if (i < letterPart.length - 1) {
                    spacedLetters += ' ';
                }
            }
            
            // Set formatted value
            input.value = numberPart + ' ' + spacedLetters;
        } else if (numbers && numbers.length > 0) {
            // Only numbers entered
            input.value = numbers.join('');
        } else if (letters && letters.length > 0) {
            // Only letters entered - format with spaces
            let letterPart = letters.join('');
            let spacedLetters = '';
            for (let i = 0; i < letterPart.length; i++) {
                spacedLetters += letterPart[i];
                if (i < letterPart.length - 1) {
                    spacedLetters += ' ';
                }
            }
            input.value = spacedLetters;
        }
    }
    
    // Filter changes for both tabs
    $('#personalDepartmentFilter, #personalProjectFilter, #personalStatusFilter, #vehicleDepartmentFilter, #vehicleProjectFilter, #vehicleStatusFilter').on('change', function() {
        if (currentActiveTab === 'personal') {
            loadPersonalLicenses();
        } else {
            loadVehicleLicenses();
        }
    });
    
    // Load departments for filter dropdowns
    function loadDepartments() {
        $.get('php_action/get_unique_departments.php')
            .done(function(response) {
                if (response.success) {
                    const options = '<option value="">جميع الأقسام</option>' + 
                        response.data.map(dept => {
                            return `<option value="${dept.department_name}">${dept.department_name}</option>`;
                        }).join('');
                    $('#personalDepartmentFilter, #vehicleDepartmentFilter').html(options);
                }
            });
    }
    
    // Load projects for filter dropdowns
    function loadProjects() {
        $.get('php_action/get_unique_projects.php')
            .done(function(response) {
                if (response.success) {
                    const options = '<option value="">جميع المشاريع</option>' + 
                        response.data.map(project => {
                            return `<option value="${project.project_name}">${project.project_name}</option>`;
                        }).join('');
                    $('#personalProjectFilter, #vehicleProjectFilter').html(options);
                }
            })
            .fail(function() {
                console.error('فشل في تحميل قائمة المشاريع');
            });
    }
    
    // Load personal licenses
    function loadPersonalLicenses(page = 1) {
        currentPersonalPage = page;
        
        const params = {
            page: page,
            search: $('#personalSearchInput').val(),
            department_id: $('#personalDepartmentFilter').val(),
            project_id: $('#personalProjectFilter').val(),
            status: $('#personalStatusFilter').val()
        };
        
        $('#personalLoadingIndicator').show();
        $('#personalLicensesContainer').hide();
        $('#personalNoDataMessage').hide();
        
        $.get('php_action/get_licenses.php', params)
            .done(function(response) {
                $('#personalLoadingIndicator').hide();
                
                if (response.success && response.data.length > 0) {
                    renderPersonalLicenses(response.data);
                    renderPersonalPagination(response.pagination);
                    $('#personalLicensesContainer').show();
                } else {
                    $('#personalNoDataMessage').show();
                }
            })
            .fail(function(xhr) {
                $('#personalLoadingIndicator').hide();
                handleError(xhr, 'فشل في تحميل التراخيص الشخصية');
            });
    }
    
    // Load vehicle licenses
    function loadVehicleLicenses(page = 1) {
        console.log('loadVehicleLicenses called with page:', page);
        currentVehiclePage = page;
        
        const params = {
            page: page,
            search: $('#vehicleSearchInput').val(),
            department_id: $('#vehicleDepartmentFilter').val(),
            project_id: $('#vehicleProjectFilter').val(),
            status: $('#vehicleStatusFilter').val()
        };
        
        $('#vehicleLoadingIndicator').show();
        $('#vehicleLicensesContainer').hide();
        $('#vehicleNoDataMessage').hide();
        
        $.get('php_action/get_vehicle_licenses.php', params)
            .done(function(response) {
                $('#vehicleLoadingIndicator').hide();
                
                if (response.success && response.data.length > 0) {
                    renderVehicleLicenses(response.data);
                    renderVehiclePagination(response.pagination);
                    $('#vehicleLicensesContainer').show();
                } else {
                    $('#vehicleNoDataMessage').show();
                }
            })
            .fail(function(xhr) {
                $('#vehicleLoadingIndicator').hide();
                handleError(xhr, 'فشل في تحميل رخص المركبات');
            });
    }
    
    // Render personal licenses table
    function renderPersonalLicenses(licenses) {
        const tbody = $('#personalLicensesTableBody');
        tbody.empty();
        
        licenses.forEach(function(license) {
            const statusClass = getStatusClass(license.status);
            const expirationDate = new Date(license.expiration_date).toLocaleDateString('en-GB');
            const issueDate = new Date(license.issue_date).toLocaleDateString('en-GB');
            
            let actionButtons = `
                <a href="view_license.php?id=${license.license_id}&type=personal" class="btn btn-info btn-xs" title="عرض">
                    <i class="glyphicon glyphicon-eye-open"></i>
                </a>
            `;
            
            // Check permissions using Admin Teams System (license-specific permissions)
            if (license.can_edit) {
            actionButtons += `
                <a href="edit_license.php?id=${license.license_id}&type=personal" class="btn btn-warning btn-xs" title="تعديل">
                    <i class="glyphicon glyphicon-edit"></i>
                </a>
            `;
            }
            
            if (license.can_delete) {
            actionButtons += `
                <button class="btn btn-danger btn-xs delete-license" data-id="${license.license_id}" data-name="${license.full_name}" data-type="personal" title="حذف">
                    <i class="glyphicon glyphicon-trash"></i>
                </button>
            `;
            }
            
            // Added by information
            const addedByText = license.added_by_name ? 
                `${license.added_by_name}<br><small class="text-muted">(${license.added_by_username})</small>` : 
                '<span class="text-muted">غير محدد</span>';
            
            const row = `
                <tr>
                    <td>${license.license_number}</td>
                    <td>${license.full_name}</td>
                    <td>${license.department_name}</td>
                    <td>${license.project_name}</td>
                    <td>${issueDate}</td>
                    <td>${expirationDate}</td>
                    <td><span class="status-badge ${statusClass}">${getStatusText(license.status)}</span></td>
                    <td>${addedByText}</td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            ${actionButtons}
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    // Render vehicle licenses table
    function renderVehicleLicenses(licenses) {
        const tbody = $('#vehicleLicensesTableBody');
        tbody.empty();
        
        licenses.forEach(function(license) {
            const statusClass = getStatusClass(license.status);
            const expirationDate = new Date(license.expiration_date).toLocaleDateString('en-GB');
            const issueDate = new Date(license.issue_date).toLocaleDateString('en-GB');
            
            let actionButtons = `
                <a href="view_license.php?id=${license.license_id}&type=vehicle" class="btn btn-info btn-xs" title="عرض">
                                <i class="glyphicon glyphicon-eye-open"></i>
                            </a>
            `;
            
            // Check permissions using Admin Teams System (license-specific permissions)
            if (license.can_edit) {
            actionButtons += `
                <a href="edit_license.php?id=${license.license_id}&type=vehicle" class="btn btn-warning btn-xs" title="تعديل">
                                <i class="glyphicon glyphicon-edit"></i>
                            </a>
            `;
            }
            
            if (license.can_delete) {
            actionButtons += `
                <button class="btn btn-danger btn-xs delete-license" data-id="${license.license_id}" data-name="${license.car_number}" data-type="vehicle" title="حذف">
                                <i class="glyphicon glyphicon-trash"></i>
                            </button>
            `;
            }
            
            // Added by information
            const addedByText = license.added_by_name ? 
                `${license.added_by_name}<br><small class="text-muted">(${license.added_by_username})</small>` : 
                '<span class="text-muted">غير محدد</span>';
            
            const row = `
                <tr>
                    <td>${license.car_number}</td>
                    <td>${license.vehicle_type}</td>
                    <td>${license.license_category || 'رخصة مركبة'}</td>
                    <td>${license.inspection_year || '-'}</td>
                    <td>${license.department_name}</td>
                    <td>${license.project_name}</td>
                    <td>${issueDate}</td>
                    <td>${expirationDate}</td>
                    <td><span class="status-badge ${statusClass}">${getStatusText(license.status)}</span></td>
                    <td>${addedByText}</td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            ${actionButtons}
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    // Render pagination for personal licenses
    function renderPersonalPagination(pagination) {
        renderPagination(pagination, 'personalPaginationContainer', loadPersonalLicenses);
    }
    
    // Render pagination for vehicle licenses
    function renderVehiclePagination(pagination) {
        renderPagination(pagination, 'vehiclePagination', loadVehicleLicenses);
    }
    
    // Generic pagination renderer
    function renderPagination(pagination, containerId, loadFunction) {
        const container = $(`#${containerId}`);
        
        if (pagination.total_pages <= 1) {
            container.empty();
            return;
        }
        
        let paginationHtml = '<nav aria-label="صفحات التراخيص"><ul class="pagination pagination-sm">';
        
        // Previous button
        if (pagination.current_page > 1) {
            paginationHtml += `<li><a href="#" data-page="${pagination.current_page - 1}">السابق</a></li>`;
        }
        
        // Page numbers
        for (let i = 1; i <= pagination.total_pages; i++) {
            if (i === pagination.current_page) {
                paginationHtml += `<li class="active"><span>${i}</span></li>`;
            } else {
                paginationHtml += `<li><a href="#" data-page="${i}">${i}</a></li>`;
            }
        }
        
        // Next button
        if (pagination.current_page < pagination.total_pages) {
            paginationHtml += `<li><a href="#" data-page="${pagination.current_page + 1}">التالي</a></li>`;
        }
        
        paginationHtml += '</ul></nav>';
        container.html(paginationHtml);
        
        // Add click handlers
        container.find('a[data-page]').on('click', function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            loadFunction(page);
        });
    }
    
    // Delete license functionality
    $(document).on('click', '.delete-license', function() {
        const licenseId = $(this).data('id');
        const licenseName = $(this).data('name');
        const licenseType = $(this).data('type');
        
        $('#licenseToDelete').html(`<strong>${licenseName}</strong>`);
        $('#deleteModal').modal('show');
        
        $('#confirmDelete').off('click').on('click', function() {
        $.post('php_action/delete_license.php', {
            license_id: licenseId,
                license_type: licenseType,
                csrf_token: csrfToken
        })
        .done(function(response) {
            $('#deleteModal').modal('hide');
            if (response.success) {
                showAlert(response.message, 'success');
                        if (currentActiveTab === 'personal') {
                            loadPersonalLicenses(currentPersonalPage);
                        } else {
                            loadVehicleLicenses(currentVehiclePage);
                        }
            } else {
                        showAlert(response.message || response.error, 'danger');
            }
        })
        .fail(function() {
            $('#deleteModal').modal('hide');
                    showAlert('فشل في حذف الترخيص', 'danger');
                });
        });
    });
    
    // Utility functions
    function getStatusClass(status) {
        switch(status) {
            case 'active':
            case 'نشط':
                return 'status-active';
            case 'expiring':
            case 'ينتهي قريباً':
                return 'status-expiring';
            case 'expired':
            case 'منتهي الصلاحية':
                return 'status-expired';
            default: 
                return 'status-active';
        }
    }
    
    function getStatusText(status) {
        switch(status) {
            case 'active':
            case 'نشط':
                return 'نشط';
            case 'expiring':
            case 'ينتهي قريباً':
                return 'ينتهي قريباً';
            case 'expired':
            case 'منتهي الصلاحية':
                return 'منتهي الصلاحية';
            default: 
                return 'نشط';
        }
    }
    
    function handleError(xhr, defaultMessage) {
        console.error('Error:', xhr.responseText);
        let errorMsg = defaultMessage;
        if (xhr.status === 401) {
            errorMsg = 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.';
            setTimeout(() => window.location.href = 'login.php', 2000);
        }
        showAlert(errorMsg, 'danger');
    }
    
    function showAlert(message, type) {
        const alertClass = type === 'danger' ? 'alert-danger' : 'alert-success';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible">
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

<style>
/* Tab Styles */
.nav-tabs {
    border-bottom: 2px solid #ddd;
}

.nav-tabs > li.active > a,
.nav-tabs > li.active > a:hover,
.nav-tabs > li.active > a:focus {
    background-color: #337ab7;
    color: white;
    border: 1px solid #337ab7;
    border-bottom-color: transparent;
}

.nav-tabs > li > a {
    border-radius: 4px 4px 0 0;
    margin-right: 2px;
}

.nav-tabs > li > a:hover {
    background-color: #f8f9fa;
    border-color: #ddd #ddd #fff;
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

/* Table Hover Effects */
.table tbody tr:hover {
    background-color: #f5f5f5;
}

/* Loading Indicator */
.loading-indicator {
    padding: 40px 0;
    text-align: center;
}

/* Search and Filter Section */
.form-control {
    border-radius: 4px;
}

/* Pagination Arabic Support */
.pagination {
    direction: ltr;
}

/* No Data Message */
.no-data-message {
    text-align: center;
    padding: 40px;
    color: #999;
}

.no-data-message i {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
}

/* Tab Content Spacing */
.tab-content {
    margin-top: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .nav-tabs > li {
        float: none;
        width: 100%;
    }
    
    .nav-tabs > li > a {
        margin-right: 0;
        margin-bottom: 1px;
    }
}

/* تحسين تخطيط الفلاتر */
.filters-row {
    margin-bottom: 20px;
}

.filters-row .form-group {
    margin-bottom: 15px;
}

.filters-row label {
    font-weight: 500;
    color: #555;
    font-size: 12px;
    margin-bottom: 5px !important;
}

.filters-row .btn-group {
    width: 100%;
}

.filters-row .btn-group .btn {
    margin-left: 5px;
    border-radius: 4px !important;
    font-size: 13px;
}

.filters-row .btn-group .btn:first-child {
    margin-left: 0;
}

.filters-row .btn-group .btn-sm {
    padding: 6px 12px;
}

/* تحسين استجابة الشاشات الصغيرة */
@media (max-width: 768px) {
    .filters-row .form-group {
        margin-bottom: 15px;
    }
    
    .filters-row .btn-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .filters-row .btn {
        flex: 1;
        min-width: 100px;
        margin-left: 0 !important;
    }
}

@media (max-width: 480px) {
    .filters-row .btn {
        min-width: auto;
        padding: 8px 10px;
        font-size: 12px;
    }
}
</style>

<?php include 'includes/footer.php'; ?> 