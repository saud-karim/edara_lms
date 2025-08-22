<?php
$pageTitle = 'سجل الإشعارات';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Only super admin can access this page
requireRole('super_admin');

include 'includes/header.php';
?>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="page-header">
        <h1><i class="glyphicon glyphicon-list-alt"></i> سجل جميع الإشعارات</h1>
        <p class="lead">عرض تفصيلي لجميع الإشعارات المرسلة والمعلقة والفاشلة</p>
    </div>

    <!-- Filter Controls -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-filter"></i> فلاتر البحث والتصفية
                        <button type="button" class="btn btn-xs btn-info pull-left" onclick="clearFilters()">
                            <i class="glyphicon glyphicon-refresh"></i> مسح الفلاتر
                        </button>
                    </h4>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>حالة الإرسال:</label>
                                <select id="statusFilter" class="form-control">
                                    <option value="">جميع الحالات</option>
                                    <option value="sent">تم الإرسال</option>
                                    <option value="failed">فشل الإرسال</option>
                                    <option value="pending">في الانتظار</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>القسم:</label>
                                <select id="departmentFilter" class="form-control">
                                    <option value="">جميع الأقسام</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>المشروع:</label>
                                <select id="projectFilter" class="form-control">
                                    <option value="">جميع المشاريع</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>البحث الشامل:</label>
                                <input type="text" id="searchFilter" class="form-control" placeholder="بحث في العنوان، المحتوى، الإيميل، القسم، أو المشروع">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>من تاريخ:</label>
                                <input type="date" id="fromDate" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>إلى تاريخ:</label>
                                <input type="date" id="toDate" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                        <i class="glyphicon glyphicon-search"></i> تطبيق الفلاتر
                                    </button>
                                    <button type="button" class="btn btn-success" onclick="refreshData()">
                                        <i class="glyphicon glyphicon-refresh"></i> تحديث
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="exportData()">
                                        <i class="glyphicon glyphicon-export"></i> تصدير
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-info" style="margin-bottom: 0;">
                                <i class="glyphicon glyphicon-info-sign"></i>
                                <strong>ملاحظة:</strong> البحث الشامل يبحث في <strong>العنوان، محتوى الإيميل، البريد الإلكتروني، اسم القسم، واسم المشروع</strong>. 
                                يمكنك البحث عن أي كلمة أو عبارة وستظهر جميع الإشعارات التي تحتوي عليها في أي من هذه الحقول.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div id="statisticsCards" class="row" style="margin-bottom: 20px;">
        <!-- Statistics will be loaded here -->
    </div>

    <!-- Notifications Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-envelope"></i> سجل الإشعارات
                        <span id="recordsCount" class="pull-left badge">0</span>
                    </h4>
                </div>
                <div class="panel-body">
                    <div id="notificationsTable">
                        <div class="text-center">
                            <i class="glyphicon glyphicon-refresh glyphicon-spin"></i> جاري تحميل البيانات...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="row">
        <div class="col-md-12">
            <nav>
                <ul id="pagination" class="pagination">
                    <!-- Pagination will be loaded here -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<style>
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

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-align: center;
    min-width: 70px;
}

.status-sent { background-color: #28a745; color: white; }
.status-failed { background-color: #dc3545; color: white; }
.status-pending { background-color: #ffc107; color: black; }

.stats-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    border-left: 4px solid;
}

.stats-card.total { border-left-color: #007bff; }
.stats-card.sent { border-left-color: #28a745; }
.stats-card.failed { border-left-color: #dc3545; }
.stats-card.pending { border-left-color: #ffc107; }

.stats-number {
    font-size: 2.5em;
    font-weight: bold;
    margin-bottom: 5px;
}

.stats-label {
    font-size: 0.9em;
    color: #666;
    text-transform: uppercase;
}

.table-actions {
    white-space: nowrap;
}

.table-actions .btn {
    margin-right: 2px;
}

@media (max-width: 768px) {
    .stats-number {
        font-size: 1.8em;
    }
    
    .table-responsive {
        font-size: 12px;
    }
}

.notification-row:hover {
    background-color: #f8f9fa !important;
}

.error-message {
    color: #dc3545;
    font-size: 0.85em;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.error-message:hover {
    white-space: normal;
    overflow: visible;
}
</style>

<script>
let currentPage = 1;
let totalPages = 1;
let currentFilters = {};

$(document).ready(function() {
    loadFiltersData();
    loadNotifications();
    
    // Set up event listeners
    $('#statusFilter, #departmentFilter, #projectFilter').change(function() {
        applyFilters();
    });
    
    $('#searchFilter').on('keyup', function() {
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(function() {
            applyFilters();
        }, 500);
    });
    
    $('#fromDate, #toDate').change(function() {
        applyFilters();
    });
});

// Load filter options
function loadFiltersData() {
    // Load departments
    $.get('php_action/get_notification_filters.php?type=departments', function(data) {
        if (data.success) {
            let options = '<option value="">جميع الأقسام</option>';
            data.data.forEach(function(item) {
                options += `<option value="${item.department_name}">${item.department_name}</option>`;
            });
            $('#departmentFilter').html(options);
        }
    }, 'json');
    
    // Load projects
    $.get('php_action/get_notification_filters.php?type=projects', function(data) {
        if (data.success) {
            let options = '<option value="">جميع المشاريع</option>';
            data.data.forEach(function(item) {
                options += `<option value="${item.project_name}">${item.project_name}</option>`;
            });
            $('#projectFilter').html(options);
        }
    }, 'json');
}

// Load notifications with filters
function loadNotifications(page = 1) {
    currentPage = page;
    
    const filters = {
        status: $('#statusFilter').val(),
        department: $('#departmentFilter').val(),
        project: $('#projectFilter').val(),
        search: $('#searchFilter').val(),
        from_date: $('#fromDate').val(),
        to_date: $('#toDate').val(),
        page: page
    };
    
    currentFilters = filters;
    
    $('#notificationsTable').html('<div class="text-center"><i class="glyphicon glyphicon-refresh glyphicon-spin"></i> جاري تحميل البيانات...</div>');
    
    $.post('php_action/get_notification_history.php', filters, function(response) {
        if (response.success) {
            displayNotifications(response.data);
            displayStatistics(response.statistics);
            displayPagination(response.pagination);
            $('#recordsCount').text(response.pagination.total_records);
        } else {
            $('#notificationsTable').html('<div class="alert alert-danger">خطأ في تحميل البيانات: ' + response.message + '</div>');
        }
    }, 'json').fail(function() {
        $('#notificationsTable').html('<div class="alert alert-danger">خطأ في الاتصال بالخادم</div>');
    });
}

// Display statistics cards
function displayStatistics(stats) {
    const html = `
        <div class="col-md-3">
            <div class="stats-card total">
                <div class="stats-number">${stats.total}</div>
                <div class="stats-label">إجمالي الإشعارات</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card sent">
                <div class="stats-number">${stats.sent}</div>
                <div class="stats-label">تم الإرسال</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card failed">
                <div class="stats-number">${stats.failed}</div>
                <div class="stats-label">فشل الإرسال</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card pending">
                <div class="stats-number">${stats.pending}</div>
                <div class="stats-label">في الانتظار</div>
            </div>
        </div>
    `;
    $('#statisticsCards').html(html);
}

// Display notifications table
function displayNotifications(notifications) {
    if (notifications.length === 0) {
        $('#notificationsTable').html('<div class="alert alert-info">لا توجد إشعارات مطابقة للفلاتر المحددة</div>');
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>القسم</th>
                        <th>المشروع</th>
                        <th>البريد الإلكتروني</th>
                        <th>الموضوع</th>
                        <th>الرخص</th>
                        <th>منتهية</th>
                        <th>ستنتهي</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    notifications.forEach(function(notification) {
        const statusClass = notification.sent_status === 'sent' ? 'sent' : 
                           (notification.sent_status === 'failed' ? 'failed' : 'pending');
        const statusText = notification.sent_status === 'sent' ? 'تم الإرسال' : 
                          (notification.sent_status === 'failed' ? 'فشل الإرسال' : 'في الانتظار');
        
        const errorMessage = notification.error_message ? 
            `<br><small class="error-message" title="${notification.error_message}">${notification.error_message}</small>` : '';
        
        html += `
            <tr class="notification-row">
                <td>
                    <div>${notification.created_at}</div>
                    ${notification.sent_at ? '<small class="text-muted">أرسل: ' + notification.sent_at + '</small>' : ''}
                </td>
                <td>${notification.department_name}</td>
                <td>${notification.project_name}</td>
                <td>${notification.recipient_email}</td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                    title="${notification.subject}">${notification.subject}</td>
                <td><span class="badge">${notification.total_licenses}</span></td>
                <td><span class="badge badge-danger">${notification.expired_count}</span></td>
                <td><span class="badge badge-warning">${notification.expiring_count}</span></td>
                <td>
                    <span class="status-badge status-${statusClass}">${statusText}</span>
                    ${errorMessage}
                </td>
                <td class="table-actions">
                    <button class="btn btn-xs btn-info" onclick="viewNotificationContent(${notification.notification_id})" 
                            title="عرض المحتوى">
                        <i class="glyphicon glyphicon-eye-open"></i>
                    </button>
                    ${notification.sent_status === 'failed' ? 
                        '<button class="btn btn-xs btn-warning" onclick="resendNotification(' + notification.notification_id + ')" title="إعادة الإرسال"><i class="glyphicon glyphicon-repeat"></i></button>' : ''}
                    <button class="btn btn-xs btn-danger" onclick="deleteNotification(${notification.notification_id})" 
                            title="حذف الإشعار">
                        <i class="glyphicon glyphicon-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    $('#notificationsTable').html(html);
}

// Display pagination
function displayPagination(pagination) {
    totalPages = pagination.total_pages;
    currentPage = pagination.current_page;
    
    let html = '';
    
    // Previous button
    if (currentPage > 1) {
        html += `<li><a href="#" onclick="loadNotifications(${currentPage - 1})">السابق</a></li>`;
    }
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === currentPage ? 'active' : '';
        html += `<li class="${activeClass}"><a href="#" onclick="loadNotifications(${i})">${i}</a></li>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        html += `<li><a href="#" onclick="loadNotifications(${currentPage + 1})">التالي</a></li>`;
    }
    
    $('#pagination').html(html);
}

// Apply filters
function applyFilters() {
    loadNotifications(1);
}

// Clear filters
function clearFilters() {
    $('#statusFilter').val('');
    $('#departmentFilter').val('');
    $('#projectFilter').val('');
    $('#searchFilter').val('');
    $('#fromDate').val('');
    $('#toDate').val('');
    loadNotifications(1);
}

// Refresh data
function refreshData() {
    loadNotifications(currentPage);
}

// Export data
function exportData() {
    const params = new URLSearchParams(currentFilters);
    window.open('php_action/export_notification_history.php?' + params.toString(), '_blank');
}

// View notification content
function viewNotificationContent(notificationId) {
    window.open('php_action/view_notification_content.php?id=' + notificationId, '_blank');
}

// Resend notification
function resendNotification(notificationId) {
    if (confirm('هل تريد إعادة إرسال هذا الإشعار؟')) {
        $.post('php_action/resend_notification.php', {notification_id: notificationId}, function(response) {
            if (response.success) {
                showMessage('success', 'تم إعادة الإرسال بنجاح');
                refreshData();
            } else {
                showMessage('error', 'خطأ في إعادة الإرسال: ' + response.message);
            }
        }, 'json');
    }
}

// Delete notification
function deleteNotification(notificationId) {
    if (confirm('هل تريد حذف هذا الإشعار نهائياً؟ لا يمكن التراجع عن هذا الإجراء.')) {
        $.post('php_action/delete_notification.php', {notification_id: notificationId}, function(response) {
            if (response.success) {
                showMessage('success', 'تم حذف الإشعار بنجاح');
                refreshData();
            } else {
                showMessage('error', 'خطأ في حذف الإشعار: ' + response.message);
            }
        }, 'json');
    }
}

// Show message function
function showMessage(type, message) {
    const alertType = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertType} alert-dismissible fade in" role="alert">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            ${message}
        </div>
    `;
    $('.content-wrapper').prepend(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}
</script>

<?php include 'includes/footer.php'; ?> 