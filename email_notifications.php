<?php
$pageTitle = 'إشعارات البريد الإلكتروني';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Only super admin can access this page
requireRole('super_admin');

include 'includes/header.php';
?>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="page-header">
        <h1><i class="glyphicon glyphicon-envelope"></i> إشعارات البريد الإلكتروني</h1>
        <p class="lead">إرسال تنبيهات للمشرفين حول الرخص المنتهية والتي تنتهي قريباً</p>
    </div>

    <!-- Control Panel -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-send"></i> لوحة التحكم في الإشعارات
                    </h4>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4>إرسال إشعارات التراخيص</h4>
                            <p class="text-muted">
                                سيتم إرسال إشعارات موحدة تشمل:
                            </p>
                            <ul class="text-muted">
                                <li><strong>للإدارات:</strong> إيميل لكل مشرف قسم بالرخص المنتهية في قسمه</li>
                                <li><strong>للمشاريع:</strong> إيميل لكل مشروع + إدارة بالرخص المنتهية في المشروع</li>
                                <li>تفاصيل كل رخصة (النوع، الرقم، حامل الرخصة، تاريخ الانتهاء)</li>
                                <li>إضافة الإيميلات الثابتة كـ CC تلقائياً</li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-center">
                            <button id="sendNotificationsBtn" class="btn btn-primary btn-lg">
                                <i class="glyphicon glyphicon-send"></i>
                                إرسال الإشعارات الآن
                            </button>
                            <br><br>
                            <button id="previewBtn" class="btn btn-info">
                                <i class="glyphicon glyphicon-eye-open"></i>
                                معاينة الإشعارات
                            </button>
                            <br><br>
                            <a href="manage_cc_emails.php" class="btn btn-warning">
                                <i class="glyphicon glyphicon-envelope"></i>
                                إدارة إيميلات CC
                            </a>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Section -->
    <div id="resultsSection" style="display: none;">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="glyphicon glyphicon-list-alt"></i> نتائج الإرسال
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div id="summaryCards" class="row" style="margin-bottom: 20px;"></div>
                        <div id="detailsTable"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Section -->
    <div id="previewSection" style="display: none;">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="glyphicon glyphicon-eye-open"></i> معاينة الإشعارات
                            <button type="button" class="close" onclick="$('#previewSection').hide();">
                                <span>&times;</span>
                            </button>
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div id="previewContent"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Logs Section -->
    <div class="row" style="margin-top: 20px;">
        <div class="col-md-12">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-time"></i> سجل الإشعارات الأخيرة (آخر 50 إشعار)
                        <button class="btn btn-xs btn-info pull-left" onclick="refreshNotificationLogs()">
                            <i class="glyphicon glyphicon-refresh"></i> تحديث
                        </button>
                    </h4>
                </div>
                <div class="panel-body">
                    <div id="notificationLogs">
                        <div class="text-center">
                            <i class="glyphicon glyphicon-refresh glyphicon-spin"></i> جاري تحميل السجلات...
                        </div>
                    </div>
                </div>
            </div>
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

.summary-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 15px;
    text-align: center;
}

.summary-card h3 {
    margin: 0 0 10px 0;
    font-size: 2.5em;
}

.summary-card p {
    margin: 0;
    font-weight: bold;
}

.card-sent { border-right: 4px solid #27ae60; }
.card-skipped { border-right: 4px solid #f39c12; }
.card-failed { border-right: 4px solid #e74c3c; }

.status-sent { color: #27ae60; }
.status-skipped { color: #f39c12; }
.status-failed { color: #e74c3c; }

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Enhanced preview table styles */
.preview-table th {
    font-weight: bold;
    text-shadow: 1px 1px 1px rgba(0,0,0,0.3);
    border: none !important;
}

.preview-table td {
    vertical-align: middle !important;
    border-color: #ddd !important;
}

.preview-table .label {
    font-size: 12px;
    padding: 5px 8px;
    border-radius: 3px;
}

/* Row hover effect */
.preview-table tbody tr:hover {
    background-color: #f8f9fa !important;
    transform: translateY(-1px);
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Email icon styling */
.email-icon {
    margin-right: 5px;
    color: #3498db;
}

/* Project badge styling */
.project-badge {
    background: linear-gradient(45deg, #17a2b8, #138496);
    border: none;
    text-shadow: 1px 1px 1px rgba(0,0,0,0.2);
}

/* Department badge styling */
.department-badge {
    background: linear-gradient(45deg, #007bff, #0056b3);
    border: none;
    text-shadow: 1px 1px 1px rgba(0,0,0,0.2);
}
</style>

<script>
$(document).ready(function() {
    $('#sendNotificationsBtn').click(function() {
        if (!confirm('هل أنت متأكد من إرسال الإشعارات لجميع مشرفي الأقسام؟')) {
            return;
        }
        
        sendNotifications();
    });
    
    $('#previewBtn').click(function() {
        previewNotifications();
    });
});

function sendNotifications() {
    const btn = $('#sendNotificationsBtn');
    const originalText = btn.html();
    
    // Show loading
    btn.prop('disabled', true)
       .html('<span class="loading-spinner"></span> جاري الإرسال...');
    
    $('#resultsSection').hide();
    
    $.ajax({
                    url: 'php_action/send_license_notifications_separated.php',
        method: 'POST',
        dataType: 'json',
        timeout: 120000, // 120 seconds timeout
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                console.log('Debug Info:', response.debug_info);
            } else {
                showAlert(response.message, 'danger');
                if (response.debug_info) {
                    console.log('Debug Info:', response.debug_info);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', xhr.responseText);
            if (status === 'timeout') {
                showAlert('انتهت مهلة الاتصال. قد يكون الإرسال قيد التقدم.', 'warning');
            } else {
                showAlert('حدث خطأ في إرسال الإشعارات', 'danger');
            }
        },
        complete: function() {
            btn.prop('disabled', false).html(originalText);
        }
    });
}

function previewNotifications() {
    $('#previewSection').show();
    $('#previewContent').html('<div class="text-center"><i class="glyphicon glyphicon-refresh glyphicon-spin"></i> جاري التحميل...</div>');
    
    $.ajax({
        url: 'php_action/preview_license_notifications_new.php',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
            console.log('AJAX SUCCESS - Full response:', response);
            if (response.success) {
                displayPreview(response);
            } else {
                $('#previewContent').html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Preview error:', xhr.responseText);
            $('#previewContent').html('<div class="alert alert-danger">حدث خطأ في تحميل المعاينة: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'خطأ غير معروف') + '</div>');
        }
    });
}

function displayResults(response) {
    const summary = response.summary;
    
    // Summary cards
    let summaryHtml = `
        <div class="col-md-3">
            <div class="summary-card card-sent">
                <h3 class="status-sent">${summary.sent}</h3>
                <p>تم الإرسال</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card card-skipped">
                <h3 class="status-skipped">${summary.skipped}</h3>
                <p>تم التخطي</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card card-failed">
                <h3 class="status-failed">${summary.failed}</h3>
                <p>فشل الإرسال</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <h3>${summary.total_managers}</h3>
                <p>إجمالي المشرفين</p>
            </div>
        </div>
    `;
    
    $('#summaryCards').html(summaryHtml);
    
    // Details table
    let tableHtml = `
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>المشرف</th>
                    <th>القسم</th>
                    <th>البريد الإلكتروني</th>
                    <th>الحالة</th>
                    <th>رخص منتهية</th>
                    <th>رخص تنتهي قريباً</th>
                    <th>ملاحظات</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    response.details.forEach(function(detail) {
        const statusClass = `status-${detail.status}`;
        const statusText = {
            'sent': 'تم الإرسال',
            'skipped': 'تم التخطي',
            'failed': 'فشل'
        }[detail.status] || detail.status;
        
        tableHtml += `
            <tr>
                <td>${detail.manager}</td>
                <td>${detail.department}</td>
                <td>${detail.email || '-'}</td>
                <td><span class="${statusClass}"><strong>${statusText}</strong></span></td>
                <td>${detail.expired_count || 0}</td>
                <td>${detail.expiring_count || 0}</td>
                <td>${detail.reason || detail.error || '-'}</td>
            </tr>
        `;
    });
    
    tableHtml += '</tbody></table>';
    $('#detailsTable').html(tableHtml);
    
    $('#resultsSection').show();
}

function displayPreview(response) {
    const data = response.data || response;
    const summary = response.summary;
    
    // Debug logging
    console.log('DEBUG - Response:', response);
    console.log('DEBUG - Data length:', data ? data.length : 0);
    console.log('DEBUG - Summary:', summary);
    
    if (!data || data.length === 0) {
        $('#previewContent').html('<div class="alert alert-info"><i class="glyphicon glyphicon-info-sign"></i> لا توجد إشعارات للإرسال.</div>');
        return;
    }
    
    // Enhanced summary with statistics
    const willSendCount = summary ? summary.will_send : data.filter(item => item.will_send).length;
    const totalExpired = summary ? summary.expired : data.reduce((sum, item) => sum + (item.expired_count || 0), 0);
    const totalExpiring = summary ? summary.expiring : data.reduce((sum, item) => sum + (item.expiring_count || 0), 0);
    const totalDepartments = summary ? summary.total_departments : data.length;
    
    // More debug logging
    console.log('DEBUG - Will send count:', willSendCount);
    console.log('DEBUG - Total departments:', totalDepartments);
    
    let summaryHtml = `
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-md-3">
                <div class="panel panel-primary">
                    <div class="panel-body text-center">
                        <h3 style="margin: 0; color: #3498db;">${willSendCount}</h3>
                        <p style="margin: 0;"><strong>سيتم الإرسال</strong></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-danger">
                    <div class="panel-body text-center">
                        <h3 style="margin: 0; color: #e74c3c;">${totalExpired}</h3>
                        <p style="margin: 0;"><strong>رخص انتهت</strong></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-warning">
                    <div class="panel-body text-center">
                        <h3 style="margin: 0; color: #f39c12;">${totalExpiring}</h3>
                        <p style="margin: 0;"><strong>رخص ستنتهي</strong></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-info">
                    <div class="panel-body text-center">
                        <h3 style="margin: 0; color: #5bc0de;">${totalDepartments}</h3>
                        <p style="margin: 0;"><strong>إجمالي الأقسام</strong></p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Enhanced table with better styling
    let previewHtml = summaryHtml + `
        <div class="table-responsive">
            <table class="table table-striped table-hover preview-table" style="background: white;"">
                <thead style="background: linear-gradient(45deg, #3498db, #2980b9); color: white;">
                    <tr>
                        <th style="text-align: center; vertical-align: middle;">القسم</th>
                        <th style="text-align: center; vertical-align: middle;">المشروع</th>
                        <th style="text-align: center; vertical-align: middle;">البريد الإلكتروني</th>
                        <th style="text-align: center; vertical-align: middle;">رخص انتهت</th>
                        <th style="text-align: center; vertical-align: middle;">رخص ستنتهي قريباً</th>
                        <th style="text-align: center; vertical-align: middle;">سيتم الإرسال</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(function(item) {
        const willSendText = item.will_send ? 
            '<span class="label label-success"><i class="glyphicon glyphicon-ok"></i> نعم</span>' : 
            '<span class="label label-default"><i class="glyphicon glyphicon-remove"></i> لا</span>';
        
        const emailText = item.email === 'لا يوجد بريد إلكتروني' ? 
            '<span class="text-muted"><i class="glyphicon glyphicon-exclamation-sign"></i> لا يوجد</span>' : 
            `<span style="color: #2c3e50;"><i class="glyphicon glyphicon-envelope"></i> ${item.email}</span>`;
        
        const expiredBadge = item.expired_count > 0 ? 
            `<span class="label label-danger" style="font-size: 12px;">${item.expired_count}</span>` : 
            '<span class="label label-default">0</span>';
        
        const expiringBadge = item.expiring_count > 0 ? 
            `<span class="label label-warning" style="font-size: 12px;">${item.expiring_count}</span>` : 
            '<span class="label label-default">0</span>';
        
        const rowClass = item.will_send ? 'success' : (item.expired_count > 0 || item.expiring_count > 0) ? 'warning' : '';
        
        const projectBadge = item.project ? 
            `<span class="label label-info" style="font-size: 11px;">${item.project}</span>` : 
            '<span class="label label-default">-</span>';
        
        previewHtml += `
            <tr class="${rowClass}">
                <td style="text-align: center; vertical-align: middle;"><span class="label label-primary department-badge" style="font-size: 11px;">${item.department}</span></td>
                <td style="text-align: center; vertical-align: middle;">${projectBadge}</td>
                <td style="text-align: center; vertical-align: middle;">${emailText}</td>
                <td style="text-align: center; vertical-align: middle;">${expiredBadge}</td>
                <td style="text-align: center; vertical-align: middle;">${expiringBadge}</td>
                <td style="text-align: center; vertical-align: middle;">${willSendText}</td>
            </tr>
        `;
    });
    
    previewHtml += `
                </tbody>
            </table>
        </div>
        <div class="alert alert-info" style="margin-top: 15px;">
            <i class="glyphicon glyphicon-info-sign"></i>
            <strong>ملاحظة:</strong> كل قسم له بريد إلكتروني واحد يستقبل جميع الإشعارات الخاصة بالتراخيص المنتهية أو التي ستنتهي قريباً في جميع المشاريع.
            <br>
            <strong>إحصائية الأقسام:</strong> يتم حساب الأقسام الفريدة فقط، حتى لو كان القسم يظهر في عدة مشاريع يُحسب مرة واحدة.
            <br><br>
            <div class="row">
                <div class="col-md-4">
                    <strong><i class="glyphicon glyphicon-envelope"></i> عدد الإشعارات:</strong> ${willSendCount} إشعار
                </div>
                <div class="col-md-4">
                    <strong><i class="glyphicon glyphicon-exclamation-sign"></i> رخص انتهت:</strong> ${totalExpired} ترخيص
                </div>
                <div class="col-md-4">
                    <strong><i class="glyphicon glyphicon-time"></i> رخص ستنتهي:</strong> ${totalExpiring} ترخيص
                </div>
            </div>
        </div>
    `;
    
    $('#previewContent').html(previewHtml);
}



function showAlert(message, type) {
    const alertClass = type === 'danger' ? 'alert-danger' : type === 'warning' ? 'alert-warning' : 'alert-success';
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

// Function to refresh notification logs
function refreshNotificationLogs() {
    $('#notificationLogs').html('<div class="text-center"><i class="glyphicon glyphicon-refresh glyphicon-spin"></i> جاري تحميل السجلات...</div>');
    
    $.ajax({
        url: 'php_action/get_notification_logs.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayNotificationLogs(response.data);
            } else {
                $('#notificationLogs').html('<div class="alert alert-danger">خطأ في تحميل السجلات: ' + response.message + '</div>');
            }
        },
        error: function() {
            $('#notificationLogs').html('<div class="alert alert-danger">خطأ في الاتصال بالخادم</div>');
        }
    });
}

// Function to display notification logs
function displayNotificationLogs(logs) {
    if (logs.length === 0) {
        $('#notificationLogs').html('<div class="alert alert-info">لا توجد إشعارات مسجلة</div>');
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
                        <th>إجمالي الرخص</th>
                        <th>منتهية</th>
                        <th>ستنتهي</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    logs.forEach(function(log) {
        let statusClass = log.sent_status === 'sent' ? 'success' : (log.sent_status === 'failed' ? 'danger' : 'warning');
        let statusText = log.sent_status === 'sent' ? 'تم الإرسال' : (log.sent_status === 'failed' ? 'فشل الإرسال' : 'في الانتظار');
        let statusIcon = log.sent_status === 'sent' ? 'glyphicon-ok' : (log.sent_status === 'failed' ? 'glyphicon-remove' : 'glyphicon-time');
        
        html += `
            <tr>
                <td>${log.created_at}</td>
                <td>${log.department_name}</td>
                <td>${log.project_name}</td>
                <td>${log.recipient_email}</td>
                <td><span class="badge">${log.total_licenses}</span></td>
                <td><span class="badge badge-danger">${log.expired_count}</span></td>
                <td><span class="badge badge-warning">${log.expiring_count}</span></td>
                <td>
                    <span class="label label-${statusClass}">
                        <i class="glyphicon ${statusIcon}"></i> ${statusText}
                    </span>
                    ${log.error_message ? '<br><small class="text-danger">' + log.error_message + '</small>' : ''}
                </td>
                <td>
                    <button class="btn btn-xs btn-info" onclick="viewNotificationContent(${log.notification_id})" title="عرض المحتوى">
                        <i class="glyphicon glyphicon-eye-open"></i>
                    </button>
                    ${log.sent_status === 'failed' ? '<button class="btn btn-xs btn-warning" onclick="resendNotification(' + log.notification_id + ')" title="إعادة الإرسال"><i class="glyphicon glyphicon-repeat"></i></button>' : ''}
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    $('#notificationLogs').html(html);
}

// Function to view notification content
function viewNotificationContent(notificationId) {
    // Show notification content in modal or new tab
    window.open('php_action/view_notification_content.php?id=' + notificationId, '_blank');
}

// Function to resend failed notification
function resendNotification(notificationId) {
    if (confirm('هل تريد إعادة إرسال هذا الإشعار؟')) {
        $.post('php_action/resend_notification.php', {notification_id: notificationId}, function(response) {
            if (response.success) {
                showMessage('success', 'تم إعادة الإرسال بنجاح');
                refreshNotificationLogs();
            } else {
                showMessage('error', 'خطأ في إعادة الإرسال: ' + response.message);
            }
        }, 'json');
    }
}

// Load logs on page load
$(document).ready(function() {
    refreshNotificationLogs();
});
</script>

<?php include 'includes/footer.php'; ?> 
  


