<?php
session_start();
require_once 'config/config.php';
require_once 'php_action/auth.php';

// التحقق من صلاحيات الوصول - Super Admin فقط
requireRole('super_admin');

$pageTitle = 'إدارة إيميلات المشاريع';

include 'includes/header.php';
?>

<style>
.project-email-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 20px;
}

.project-email-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.project-email-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    font-weight: bold;
}

.email-status {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.email-status.configured {
    background-color: #d4edda;
    color: #155724;
}

.email-status.not-configured {
    background-color: #f8d7da;
    color: #721c24;
}

.cc-emails-section {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
}

.stats-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}

.stats-number {
    font-size: 2.5em;
    font-weight: bold;
    margin-bottom: 5px;
}

.btn-gradient {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    transition: all 0.3s ease;
}

.btn-gradient:hover {
    background: linear-gradient(135deg, #764ba2, #667eea);
    color: white;
    transform: translateY(-1px);
}

.page-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.page-header h1 {
    color: white;
    margin: 0;
}

.alert-custom {
    border-radius: 10px;
    border: none;
    padding: 15px 20px;
}
</style>

<div class="container content-wrapper">
    <!-- Page Header -->
    <div class="page-header">
        <div class="row">
            <div class="col-md-8">
                <h1>
                    <i class="fa fa-envelope-open-o"></i>
                    إدارة إيميلات المشاريع
                </h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">إعداد وإدارة إيميلات المشاريع والإشعارات التلقائية</p>
            </div>
                            <div class="col-md-4 text-left">
                    <a href="email_notifications.php" class="btn btn-gradient" style="margin-top: 10px;">
                        <i class="fa fa-send"></i>
                        إرسال الإشعارات
                    </a>
                </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number" id="totalProjects">-</div>
                <div>إجمالي المشاريع</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number" id="configuredEmails">-</div>
                <div>إيميلات مُعدة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number" id="unconfiguredEmails">-</div>
                <div>غير مُعدة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-number" id="ccEmailsCount">-</div>
                <div>إيميلات CC</div>
            </div>
        </div>
    </div>

    <!-- CC Emails Section -->
    <div class="cc-emails-section">
        <h4 style="margin-bottom: 15px;">
            <i class="fa fa-users text-primary"></i>
            إعدادات الإيميلات الثابتة (CC)
        </h4>
        <p class="text-muted">هذه الإيميلات ستُضاف تلقائياً كـ CC لكل إشعار يتم إرساله</p>
        
        <div class="row">
            <div class="col-md-8">
                <div class="form-group">
                    <label for="ccEmails" class="control-label">الإيميلات الثابتة (فصل بفاصلة)</label>
                    <textarea 
                        class="form-control" 
                        id="ccEmails" 
                        rows="3" 
                        placeholder="email1@company.com, email2@company.com, email3@company.com"
                    ></textarea>
                </div>
            </div>
            <div class="col-md-4">
                <label class="control-label">&nbsp;</label>
                <div>
                    <button type="button" class="btn btn-gradient btn-block" onclick="updateCCEmails()" style="margin-top: 25px;">
                        <i class="fa fa-save"></i>
                        حفظ إعدادات CC
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Projects Email Management -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title">
                <i class="fa fa-sitemap text-primary"></i>
                إيميلات المشاريع
            </h4>
        </div>
        <div class="panel-body">
            <div class="row" id="projectsContainer">
                <!-- سيتم تحميل المشاريع هنا بواسطة JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Project Email Modal -->
<div class="modal fade" id="projectEmailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="fa fa-envelope"></i>
                    تعديل إيميل المشروع
                </h4>
            </div>
            <div class="modal-body">
                <form id="projectEmailForm">
                    <input type="hidden" id="projectId" name="project_id">
                    
                    <div class="form-group">
                        <label for="projectName" class="control-label">اسم المشروع</label>
                        <input type="text" class="form-control" id="projectName" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="projectEmail" class="control-label">إيميل المشروع</label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="projectEmail" 
                            name="project_email"
                            placeholder="project@company.com"
                            required
                        >
                        <p class="help-block">سيتم إرسال إشعارات التراخيص لهذا الإيميل</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-gradient" onclick="saveProjectEmail()">
                    <i class="fa fa-save"></i>
                    حفظ التغييرات
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadProjects();
    loadCCEmails();
    updateStatistics();
});

// تحميل المشاريع
function loadProjects() {
    console.log('🔄 تحميل المشاريع...');
    
    $.ajax({
        url: 'php_action/get_projects_emails.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('📧 استجابة المشاريع:', response);
            
            if (response.success) {
                displayProjects(response.data);
                updateStatistics();
            } else {
                showAlert('danger', 'خطأ في تحميل المشاريع: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ خطأ في تحميل المشاريع:', error);
            showAlert('danger', 'خطأ في الاتصال بالخادم');
        }
    });
}

// عرض المشاريع
function displayProjects(projects) {
    const container = $('#projectsContainer');
    container.empty();
    
    if (projects.length === 0) {
        container.html(`
            <div class="col-md-12">
                <div class="alert alert-info text-center">
                    <i class="fa fa-info-circle"></i>
                    لا توجد مشاريع متاحة
                </div>
            </div>
        `);
        return;
    }
    
    projects.forEach(function(project) {
        const hasEmail = project.project_email && project.project_email.trim() !== '';
        const statusClass = hasEmail ? 'configured' : 'not-configured';
        const statusText = hasEmail ? 'مُعد' : 'غير مُعد';
        const statusIcon = hasEmail ? 'fa fa-check-circle' : 'fa fa-exclamation-circle';
        
        const projectCard = `
            <div class="col-md-6 col-lg-4">
                <div class="project-email-card">
                    <div class="project-email-header">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>${project.project_name}</span>
                            <span class="email-status ${statusClass}">
                                <i class="${statusIcon}"></i>
                                ${statusText}
                            </span>
                        </div>
                    </div>
                    <div style="padding: 15px;">
                        <div style="margin-bottom: 10px;">
                            <small class="text-muted">الإيميل الحالي:</small><br>
                            <strong>${hasEmail ? project.project_email : 'غير محدد'}</strong>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <small class="text-muted">المستخدمين:</small>
                            <span class="label label-primary">${project.users_count || 0}</span>
                        </div>
                        <button 
                            type="button" 
                            class="btn btn-primary btn-block btn-sm"
                            onclick="editProjectEmail(${project.project_id}, '${project.project_name}', '${project.project_email || ''}')"
                        >
                            <i class="fa fa-edit"></i>
                            ${hasEmail ? 'تعديل الإيميل' : 'إضافة إيميل'}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        container.append(projectCard);
    });
}

// تحميل إيميلات CC
function loadCCEmails() {
    $.ajax({
        url: 'php_action/get_email_settings.php',
        method: 'GET',
        data: { setting_name: 'cc_emails' },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                $('#ccEmails').val(response.data.setting_value || '');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ خطأ في تحميل إيميلات CC:', error);
        }
    });
}

// تحديث الإحصائيات
function updateStatistics() {
    setTimeout(function() {
        const projectCards = $('.project-email-card').length;
        const configuredEmails = $('.email-status.configured').length;
        const unconfiguredEmails = $('.email-status.not-configured').length;
        
        $('#totalProjects').text(projectCards);
        $('#configuredEmails').text(configuredEmails);
        $('#unconfiguredEmails').text(unconfiguredEmails);
        
        // حساب عدد إيميلات CC
        const ccEmailsText = $('#ccEmails').val();
        const ccEmailsCount = ccEmailsText ? ccEmailsText.split(',').length : 0;
        $('#ccEmailsCount').text(ccEmailsCount);
    }, 500);
}

// تعديل إيميل المشروع
function editProjectEmail(projectId, projectName, currentEmail) {
    $('#projectId').val(projectId);
    $('#projectName').val(projectName);
    $('#projectEmail').val(currentEmail);
    
    $('#projectEmailModal').modal('show');
}

// حفظ إيميل المشروع
function saveProjectEmail() {
    const formData = new FormData($('#projectEmailForm')[0]);
    
    $.ajax({
        url: 'php_action/update_project_email.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', 'تم حفظ إيميل المشروع بنجاح');
                $('#projectEmailModal').modal('hide');
                loadProjects();
            } else {
                showAlert('danger', 'خطأ في الحفظ: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ خطأ في حفظ إيميل المشروع:', error);
            showAlert('danger', 'خطأ في الاتصال بالخادم');
        }
    });
}

// تحديث إيميلات CC
function updateCCEmails() {
    const ccEmails = $('#ccEmails').val().trim();
    
    if (!ccEmails) {
        showAlert('warning', 'يرجى إدخال إيميلات CC');
        return;
    }
    
    $.ajax({
        url: 'php_action/update_email_settings.php',
        method: 'POST',
        data: {
            setting_name: 'cc_emails',
            setting_value: ccEmails
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', 'تم حفظ إعدادات CC بنجاح');
                updateStatistics();
            } else {
                showAlert('danger', 'خطأ في الحفظ: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ خطأ في حفظ إعدادات CC:', error);
            showAlert('danger', 'خطأ في الاتصال بالخادم');
        }
    });
}

// تم نقل إرسال الإشعارات إلى صفحة email_notifications.php

// عرض التنبيهات
function showAlert(type, message) {
    // إزالة التنبيهات السابقة
    $('.alert-custom').remove();
    
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible alert-custom" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            ${message}
        </div>
    `;
    
    $('body').append(alertHtml);
    
    // إزالة التنبيه تلقائياً بعد 5 ثوان
    setTimeout(function() {
        $('.alert-custom').fadeOut();
    }, 5000);
}
</script>

<?php include 'includes/footer.php'; ?> 