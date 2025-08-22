<?php
$pageTitle = 'إضافة قسم جديد';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check if user has permission to add departments
if (!hasPermission('departments_add') && getUserRole() !== 'super_admin') {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بإضافة الأقسام', 'danger');
    exit;
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
                            <h4><i class="glyphicon glyphicon-plus"></i> إضافة قسم جديد</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="departments.php" class="btn btn-default">
                                <i class="glyphicon glyphicon-arrow-right"></i> العودة لقائمة الأقسام
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <form id="addDepartmentForm" method="POST">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_name">اسم القسم <span class="text-danger">*</span></label>
                                    <input type="text" id="department_name" name="department_name" class="form-control" required 
                                           placeholder="أدخل اسم القسم" minlength="3" tabindex="1">
                                    <small class="text-muted">3 أحرف على الأقل</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_email">بريد القسم الإلكتروني</label>
                                    <input type="email" id="department_email" name="department_email" class="form-control" 
                                           placeholder="department@company.com" tabindex="2">
                                    <small class="text-muted">لاستقبال إشعارات التراخيص</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="department_description">وصف القسم</label>
                                    <textarea id="department_description" name="department_description" class="form-control" rows="3"
                                           placeholder="أدخل وصف القسم (اختياري)" tabindex="3"></textarea>
                                    <small class="text-muted">وصف مختصر للقسم وأنشطته</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-success btn-lg" tabindex="3">
                                <i class="glyphicon glyphicon-plus"></i> إضافة القسم
                            </button>
                            <a href="departments.php" class="btn btn-default btn-lg" tabindex="4">
                                <i class="glyphicon glyphicon-remove"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load projects on page load
    // loadProjects(); // This function is no longer needed
    
    // Load projects function
    // function loadProjects() {
    //     $.get('php_action/get_projects.php')
    //         .done(function(response) {
    //             if (response.success) {
    //                 const select = $('#project_id');
    //                 select.find('option:not(:first)').remove();
                    
    //                 response.data.forEach(function(project) {
    //                     select.append(`<option value="${project.project_id}">${project.project_name}</option>`);
    //                 });
    //             }
    //         })
    //         .fail(function() {
    //             showAlert('فشل في تحميل المشاريع', 'danger');
    //         });
    // }
    
    // Form submission
    $('#addDepartmentForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fa fa-spinner fa-spin"></i> جاري الإضافة...').prop('disabled', true);
        
        $.ajax({
            url: 'php_action/add_department.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(function() {
                        window.location.href = 'departments.php';
                    }, 2000);
                } else {
                    showAlert(response.error, 'danger');
                    submitBtn.html(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showAlert('حدث خطأ في الخادم', 'danger');
                submitBtn.html(originalText).prop('disabled', false);
            }
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
/* Form styling */
.form-group label {
    font-weight: bold;
    color: #333;
}

.required {
    color: #d9534f;
}

/* Button styling */
.btn-lg {
    padding: 10px 20px;
    margin: 5px;
}

/* Panel styling */
.panel-heading h4 {
    margin: 0;
    color: #333;
}

/* Form validation */
.form-control:focus {
    border-color: #66afe9;
    box-shadow: 0 1px 1px rgba(0,0,0,.075), 0 0 0 3px rgba(102,175,233,.1);
}

/* Textarea */
textarea.form-control {
    resize: vertical;
    min-height: 100px;
}
</style>

<?php include 'includes/footer.php'; ?> 