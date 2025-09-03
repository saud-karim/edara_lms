<?php
$pageTitle = 'تقارير التراخيص';
require_once 'config/config.php';
require_once 'php_action/auth.php';

requireLogin();

// Check specific reports permissions first
$hasReportsView = hasPermission('reports_view');
$hasReportsExport = hasPermission('reports_export');

// Check license permissions
$userRole = getUserRole();
$hasVehicleView = hasPermission('vehicle_licenses_view');
$hasPersonalView = hasPermission('personal_licenses_view');
$hasGeneralView = hasPermission('licenses_view');

// Improved logic for regular users
if ($hasVehicleView || $hasPersonalView) {
    $canViewVehicle = $hasVehicleView;
    $canViewPersonal = $hasPersonalView;
} else {
    // For regular users who have general view permission
    $canViewVehicle = $hasGeneralView;
    $canViewPersonal = $hasGeneralView;
}

// Additional fallback for regular users
if (!$canViewVehicle && !$canViewPersonal && $userRole === 'user') {
    // Regular users can see both if they have general access
    $canViewVehicle = $hasGeneralView;
    $canViewPersonal = $hasGeneralView;
}

// Check overall access permission: must have some license view permission
if (!$canViewVehicle && !$canViewPersonal) {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بعرض تقارير التراخيص', 'danger');
    exit;
}

$conn = getDBConnection();
$departments = $conn->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();
$projects = $conn->query("SELECT * FROM projects WHERE is_active = 1 ORDER BY project_name")->fetchAll();

include 'includes/header.php';
?>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="page-header">
                <h2>
                    <i class="glyphicon glyphicon-stats"></i> تقارير التراخيص
                    <small>إنشاء وعرض تقارير شاملة للتراخيص</small>
                </h2>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-filter"></i> فلاتر التقرير
                    </h4>
                </div>
                <div class="panel-body">
                    <form id="reportForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="reportType">نوع التقرير:</label>
                                    <select id="reportType" name="report_type" class="form-control" required>
                                        <option value="">-- اختر نوع التقرير --</option>
                                        <?php if ($canViewPersonal || $canViewVehicle): ?>
                                            <option value="all_summary">تقرير شامل - إحصائيات عامة للنظام</option>
                                        <?php endif; ?>
                                        <?php if ($canViewPersonal): ?>
                                            <option value="personal_expired">رخص القيادة المنتهية</option>
                                            <option value="personal_expiring">رخص القيادة المنتهية قريباً</option>
                                            <option value="personal_active">رخص القيادة النشطة</option>
                                        <?php endif; ?>
                                        <?php if ($canViewVehicle): ?>
                                            <option value="vehicle_expired">رخص المركبات المنتهية</option>
                                            <option value="vehicle_expiring">رخص المركبات المنتهية قريباً</option>
                                            <option value="vehicle_active">رخص المركبات النشطة</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="departmentFilter">فلتر الإدارة:</label>
                                    <select id="departmentFilter" name="department_id" class="form-control">
                                        <option value="">-- كل الإدارات --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>">
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="projectFilter">فلتر المشروع:</label>
                                    <select id="projectFilter" name="project_id" class="form-control">
                                        <option value="">-- كل المشاريع --</option>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?php echo $project['project_id']; ?>">
                                                <?php echo htmlspecialchars($project['project_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="glyphicon glyphicon-search"></i> إنشاء التقرير
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Results -->
    <div id="reportResults" style="display: none;">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="glyphicon glyphicon-list-alt"></i> نتائج التقرير
                            <?php if ($hasReportsExport): ?>
                            <span class="pull-right">
                                <button class="btn btn-sm btn-success" onclick="exportReport('excel')">
                                    <i class="glyphicon glyphicon-export"></i> Excel
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="exportReport('pdf')">
                                    <i class="glyphicon glyphicon-file"></i> PDF
                                </button>
                            </span>
                            <?php endif; ?>
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div id="reportSummary" class="row" style="margin-bottom: 20px;"></div>
                        <div class="table-responsive table-container">
                            <table id="reportTable" class="table table-hover">
                                <thead id="reportTableHead"></thead>
                                <tbody id="reportTableBody"></tbody>
                            </table>
                        </div>
                        <div id="reportPagination" class="text-center"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Report Cards -->
    <div class="row" style="margin-top: 20px;">
        <div class="col-md-12">
            <h3 style="color: #2c3e50; margin-bottom: 20px; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
                <i class="glyphicon glyphicon-dashboard"></i> تقارير سريعة
            </h3>
        </div>
    </div>
    
    <div class="row">
        <?php if ($canViewPersonal): ?>
        <div class="col-md-3 col-sm-6">
            <div class="report-card" onclick="generateQuickReport('personal_expired')">
                <div class="report-card-content" style="background: #e74c3c; color: white;">
                    <div class="report-icon">
                        <i class="glyphicon glyphicon-exclamation-sign"></i>
                    </div>
                    <div class="report-text">
                        <h4>رخص قيادة منتهية</h4>
                        <p>عرض الرخص المنتهية الصلاحية</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="report-card" onclick="generateQuickReport('personal_expiring')">
                <div class="report-card-content" style="background: #f39c12; color: white;">
                    <div class="report-icon">
                        <i class="glyphicon glyphicon-warning-sign"></i>
                    </div>
                    <div class="report-text">
                        <h4>رخص قيادة ستنتهي</h4>
                        <p>خلال الـ 30 يوم القادمة</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($canViewVehicle): ?>
        <div class="col-md-3 col-sm-6">
            <div class="report-card" onclick="generateQuickReport('vehicle_expired')">
                <div class="report-card-content" style="background: #c0392b; color: white;">
                    <div class="report-icon">
                        <i class="glyphicon glyphicon-remove-sign"></i>
                    </div>
                    <div class="report-text">
                        <h4>رخص مركبات منتهية</h4>
                        <p>عرض الرخص المنتهية الصلاحية</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="report-card" onclick="generateQuickReport('vehicle_expiring')">
                <div class="report-card-content" style="background: #d35400; color: white;">
                    <div class="report-icon">
                        <i class="glyphicon glyphicon-time"></i>
                    </div>
                    <div class="report-text">
                        <h4>رخص مركبات ستنتهي</h4>
                        <p>خلال الـ 30 يوم القادمة</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($canViewPersonal || $canViewVehicle): ?>
        <div class="col-md-3 col-sm-6">
            <div class="report-card" onclick="generateQuickReport('all_summary')">
                <div class="report-card-content" style="background: #2980b9; color: white;">
                    <div class="report-icon">
                        <i class="glyphicon glyphicon-stats"></i>
                    </div>
                    <div class="report-text">
                        <h4>تقرير شامل</h4>
                        <p>إحصائيات عامة للنظام</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.report-card {
    cursor: pointer;
    margin-bottom: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.report-card-content {
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.report-icon {
    font-size: 2.5em;
    margin-bottom: 10px;
}

.report-text h4 {
    font-size: 1.1em;
    margin: 10px 0 5px 0;
    font-weight: bold;
}

.report-text p {
    font-size: 0.9em;
    margin: 0;
    opacity: 0.9;
}

.page-header {
    border-bottom: 3px solid #3498db;
    margin-bottom: 30px;
    padding-bottom: 15px;
}

.panel-heading {
    background: linear-gradient(45deg, #3498db, #2980b9) !important;
    color: white !important;
}

.panel-heading .panel-title {
    color: white !important;
    font-weight: bold;
}

#reportResults {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* تحسين شكل الجدول */
.table-responsive {
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#reportTable {
    margin-bottom: 0;
}

#reportTable th {
    background: linear-gradient(45deg, #f8f9fa, #e9ecef);
    border-bottom: 2px solid #dee2e6;
    font-weight: bold;
    text-align: center;
    padding: 12px 8px;
    font-size: 13px;
}

#reportTable tbody tr {
    transition: background-color 0.2s;
}

#reportTable tbody tr:hover {
    background-color: #f8f9fa;
}

#reportTable tbody td {
    padding: 10px 8px;
    font-size: 12px;
    vertical-align: middle;
}

/* تحسين عرض التواريخ */
.date-cell {
    white-space: nowrap;
    font-family: 'Courier New', monospace;
    font-size: 11px;
}

/* تحسين عرض الأرقام */
.number-cell {
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

#reportTable thead th {
    background: linear-gradient(45deg, #34495e, #2c3e50);
    color: white;
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
    border: 1px solid #2c3e50;
    padding: 12px 8px;
    font-size: 13px;
}

#reportTable tbody td {
    vertical-align: middle;
    padding: 8px 6px;
    border: 1px solid #ddd;
    font-size: 12px;
}

#reportTable tbody tr.even {
    background-color: #f8f9fa;
}

#reportTable tbody tr.odd {
    background-color: #ffffff;
}

#reportTable tbody tr:hover {
    background-color: #e3f2fd !important;
    cursor: pointer;
}

/* تنسيق خاص للخلايا */
.table td.text-center {
    text-align: center;
}

.table td.font-weight-bold {
    font-weight: bold;
}

.table td.text-danger {
    color: #dc3545 !important;
}

.table td.text-warning {
    color: #fd7e14 !important;
}

.table td.text-success {
    color: #28a745 !important;
}

.table td.text-muted {
    color: #6c757d !important;
}

.table td.small {
    font-size: 11px;
}

/* Labels للحالة */
.label {
    display: inline-block;
    padding: 4px 8px;
    font-size: 11px;
    font-weight: bold;
    border-radius: 3px;
    text-align: center;
    min-width: 60px;
}

.label-success {
    background-color: #28a745;
    color: white;
}

.label-danger {
    background-color: #dc3545;
    color: white;
}

.label-warning {
    background-color: #fd7e14;
    color: white;
}

/* تحسينات إضافية للعرض */
#reportTable tbody tr:hover td {
    box-shadow: inset 0 0 0 1px #007bff;
}

.table td span[title] {
    cursor: help;
}

.table td span[title]:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .report-icon {
        font-size: 2em;
    }
    
    .report-text h4 {
        font-size: 1em;
    }
    
    .report-text p {
        font-size: 0.8em;
    }
    
    #reportTable thead th,
    #reportTable tbody td {
        font-size: 10px;
        padding: 6px 4px;
    }
    
    .table-responsive {
        font-size: 10px;
    }
}
</style>

<script>
$(document).ready(function() {
    // Set default date range (clear dates for full range)
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
});

// Handle form submission
$('#reportForm').on('submit', function(e) {
    e.preventDefault();
    
    const reportType = $('#reportType').val();
    if (!reportType) {
        alert('يرجى اختيار نوع التقرير');
        return;
    }
    
    generateReport();
});

// Generate report based on selected type
function generateReport() {
    const reportType = $('#reportType').val();
    
    // Show loading
    $('#reportResults').show();
    $('#reportSummary').html('<div class="col-md-12 text-center"><i class="glyphicon glyphicon-refresh glyphicon-spin"></i> جاري إنشاء التقرير...</div>');
    $('#reportTableHead').html('');
    $('#reportTableBody').html('');
    
    // Make AJAX request
    $.ajax({
        url: 'php_action/generate_report.php',
        method: 'POST',
        dataType: 'json',
        data: {
            report_type: reportType,
            department_id: $('#departmentFilter').val(),
            project_id: $('#projectFilter').val()
        },
        success: function(response) {
            if (response.success) {
                displayReport(response.data, response.summary);
                
                // Scroll to results after displaying the report
                setTimeout(function() {
                    scrollToResults();
                }, 500);
            } else {
                $('#reportSummary').html('<div class="col-md-12"><div class="alert alert-danger">' + response.message + '</div></div>');
                $('#reportTableHead').html('');
                $('#reportTableBody').html('');
            }
        },
        error: function() {
            $('#reportSummary').html('<div class="col-md-12"><div class="alert alert-danger">حدث خطأ أثناء إنشاء التقرير</div></div>');
            $('#reportTableHead').html('');
            $('#reportTableBody').html('');
        }
    });
}

// Generate quick report
function generateQuickReport(type) {
    $('#reportType').val(type);
    generateReport();
    // Note: Scroll is handled in generateReport's success callback
}

// Display report results
function displayReport(data, summary) {
    console.log("📊 Displaying report - Raw data:", {data, summary}); // Debug log
    
    // Display summary in specific order
    let summaryHtml = '';
    if (summary) {
        // Force specific order for summary cards
        const summaryOrder = ['total', 'active', 'expiring', 'expired'];
        
        summaryOrder.forEach(key => {
            if (summary.hasOwnProperty(key)) {
                const value = summary[key] !== undefined ? summary[key] : 0; // Ensure value exists
                const color = getSummaryColor(key);
                const label = getSummaryLabel(key);
                
                console.log(`📈 Summary ${key}: ${value} (${typeof value})`); // Debug log
                
                summaryHtml += `
                    <div class="col-md-3 col-sm-6">
                        <div class="summary-stat" style="background: ${color}; color: white; padding: 15px; border-radius: 5px; text-align: center; margin-bottom: 10px;">
                            <div style="font-size: 2em; font-weight: bold;">${value}</div>
                            <div style="font-size: 0.9em;">${label}</div>
                        </div>
                    </div>
                `;
            }
        });
    }
    
    console.log("📊 Generated summaryHtml length:", summaryHtml.length); // Debug log
    $('#reportSummary').html(summaryHtml);
    
    // Display table
    if (data && data.length > 0) {
        const allColumns = Object.keys(data[0]);
        
        // Define important columns in preferred order
        const columnOrder = [
            'license_number', 'full_name', 'car_number', 'vehicle_type',
            'issue_date', 'expiration_date', 'status', 'days_until_expiry',
            'department_name', 'project_name', 'notes'
        ];
        
        // Hidden columns that shouldn't be displayed
        const hiddenColumns = [
            'license_id', 'created_at', 'updated_at', 'is_active', 
            'front_image_path', 'back_image_path', 'project_id', 
            'department_id', 'days_expired'
        ];
        
        // Get visible columns in proper order
        const visibleColumns = [];
        
        // Add ordered columns first (if they exist)
        columnOrder.forEach(col => {
            if (allColumns.includes(col) && !hiddenColumns.includes(col)) {
                visibleColumns.push(col);
            }
        });
        
        // Add any remaining columns (not in order or hidden)
        allColumns.forEach(col => {
            if (!visibleColumns.includes(col) && !hiddenColumns.includes(col)) {
                visibleColumns.push(col);
            }
        });
        
        // Table header
        let headerHtml = '<tr>';
        visibleColumns.forEach(col => {
            headerHtml += `<th>${getColumnLabel(col)}</th>`;
        });
        headerHtml += '</tr>';
        $('#reportTableHead').html(headerHtml);
        
        // Table body
        let bodyHtml = '';
        data.forEach((row, index) => {
            const rowClass = index % 2 === 0 ? 'even' : 'odd';
            bodyHtml += `<tr class="${rowClass}">`;
            
            visibleColumns.forEach(col => {
                let value = row[col] || '';
                let cellClass = '';
                
                if (col.includes('date') && value) {
                    cellClass = 'text-center date-cell';
                    value = formatDate(value);
                } else if (col === 'license_number' || col === 'car_number' || col === 'license_id') {
                    cellClass = 'text-center number-cell';
                    if (value) {
                        value = '<span style="background: #f8f9fa; padding: 2px 8px; border-radius: 3px; border: 1px solid #dee2e6;">' + value + '</span>';
                    }
                } else if (col === 'status') {
                    value = getStatusBadge(value);
                    cellClass = 'text-center';
                } else if (col === 'days_until_expiry' && value) {
                    const days = parseInt(value);
                    if (days < 0) {
                        cellClass = 'text-danger font-weight-bold';
                        value = Math.abs(days) + ' يوم (منتهي)';
                    } else if (days <= 30) {
                        cellClass = 'text-warning font-weight-bold';
                        value = days + ' يوم';
                    } else {
                        cellClass = 'text-success';
                        value = days + ' يوم';
                    }
                } else if (col === 'license_number' || col === 'car_number') {
                    cellClass = 'text-center font-weight-bold';
                    // Add some styling for better visibility
                    if (value) {
                        value = '<span style="background: #f8f9fa; padding: 2px 8px; border-radius: 3px; border: 1px solid #dee2e6;">' + value + '</span>';
                    }
                } else if (col === 'full_name') {
                    cellClass = 'font-weight-bold';
                    // Make names more prominent
                    if (value) {
                        value = '<span style="color: #2c3e50;">' + value + '</span>';
                    }
                } else if (col === 'vehicle_type') {
                    cellClass = 'text-center';
                    // Style vehicle type
                    if (value) {
                        const vehicleColor = value.includes('عربية') ? '#17a2b8' : 
                                          value.includes('موتوسيكل') ? '#28a745' : '#6c757d';
                        value = '<span style="color: ' + vehicleColor + '; font-weight: bold;">' + value + '</span>';
                    }
                } else if (col === 'license_category') {
                    cellClass = 'text-center';
                    // Style license category
                    if (value) {
                        const categoryColor = value.includes('تصريح') ? '#dc3545' : '#17a2b8';
                        value = '<span style="color: ' + categoryColor + '; font-weight: bold;">' + value + '</span>';
                    }
                } else if (col === 'inspection_year') {
                    cellClass = 'text-center';
                    // Style inspection year
                    if (value && value !== '0' && value !== '') {
                        value = '<span style="background: #e3f2fd; padding: 2px 6px; border-radius: 3px; color: #1976d2; font-weight: bold;">' + value + '</span>';
                    } else {
                        value = '<span style="color: #999;">-</span>';
                    }
                } else if (col === 'department_name' || col === 'project_name') {
                    cellClass = 'small';
                    // Style department/project names
                    if (value) {
                        value = '<span style="color: #495057; font-style: italic;">' + value + '</span>';
                    }
                } else if (col === 'notes' && value) {
                    // Truncate long notes
                    if (value.length > 50) {
                        value = value.substring(0, 50) + '...';
                    }
                    cellClass = 'small text-muted';
                    value = '<span title="' + (row[col] || '').replace(/"/g, '&quot;') + '">' + value + '</span>';
                }
                
                bodyHtml += `<td class="${cellClass}">${value}</td>`;
            });
            bodyHtml += '</tr>';
        });
        $('#reportTableBody').html(bodyHtml);
    } else {
        $('#reportTableHead').html('<tr><th class="text-center">لا توجد بيانات</th></tr>');
        $('#reportTableBody').html('<tr><td class="text-center text-muted">لا توجد نتائج للفلاتر المحددة</td></tr>');
    }
}

// Helper functions
function getSummaryColor(key) {
    const colors = {
        total: '#3498db',
        active: '#27ae60',
        expired: '#e74c3c',
        expiring: '#f39c12',
        departments: '#9b59b6',
        projects: '#34495e'
    };
    return colors[key] || '#95a5a6';
}

function getSummaryLabel(key) {
    const labels = {
        total: 'إجمالي التراخيص',
        active: 'نشطة',
        expired: 'منتهية',
        expiring: 'ستنتهي قريباً',
        departments: 'الأقسام',
        projects: 'المشاريع'
    };
    return labels[key] || key;
}

function getColumnLabel(col) {
    const labels = {
        license_id: 'رقم الترخيص',
        license_number: 'رقم الرخصة',
        full_name: 'الاسم الكامل',
        car_number: 'رقم المركبة',
        vehicle_type: 'نوع المركبة',
        license_category: 'فئة الرخصة',
        inspection_year: 'سنة الفحص',
        department_name: 'القسم',
        project_name: 'المشروع',
        issue_date: 'تاريخ الإصدار',
        expiration_date: 'تاريخ الانتهاء',
        status: 'الحالة',
        days_until_expiry: 'أيام للانتهاء',
        days_expired: 'أيام الانتهاء',
        notes: 'ملاحظات',
        count: 'العدد',
        license_type: 'نوع الترخيص',
        total: 'المجموع',
        active: 'نشط',
        expiring: 'ينتهي قريباً',
        expired: 'منتهي'
    };
    return labels[col] || col;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

// Function to smoothly scroll to the results table
function scrollToResults() {
    // Check if reportResults is visible
    if ($('#reportResults').is(':visible')) {
        $('html, body').animate({
            scrollTop: $('#reportResults').offset().top - 20
        }, 800, 'swing');
    }
}

function getStatusBadge(status) {
    const badges = {
        active: '<span class="label label-success">نشط</span>',
        expired: '<span class="label label-danger">منتهي</span>',
        expiring: '<span class="label label-warning">ينتهي قريباً</span>'
    };
    return badges[status] || status;
}

// Export functions
function exportReport(format) {
    const reportType = $('#reportType').val();
    if (!reportType) {
        alert('يرجى إنشاء تقرير أولاً');
        return;
    }
    
    const params = new URLSearchParams({
        report_type: reportType,
        export_format: format
    });
    
    window.open(`php_action/export_report.php?${params.toString()}`, '_blank');
}
</script>

<?php include 'includes/footer.php'; ?> 