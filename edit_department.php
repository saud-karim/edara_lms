<?php
$pageTitle = 'تعديل القسم';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check if user has permission to edit departments
if (!hasPermission('departments_edit') && getUserRole() !== 'super_admin') {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بتعديل الأقسام', 'danger');
    exit;
}

// Get department ID from URL
$departmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$departmentId) {
    setMessage('معرف القسم غير صحيح', 'danger');
    header('Location: departments.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get department details
    $query = "
        SELECT d.*
        FROM departments d
        WHERE d.department_id = ? AND d.is_active = 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        setMessage('القسم غير موجود أو تم حذفه', 'danger');
        header('Location: departments.php');
        exit;
    }
    
    // Get usage statistics for warnings
    $usersStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND is_active = 1");
    $usersStmt->execute([$departmentId]);
    $usersCount = $usersStmt->fetchColumn();
    
    $licensesStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses WHERE department_id = ? AND is_active = 1");
    $licensesStmt->execute([$departmentId]);
    $licensesCount = $licensesStmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Edit department error: " . $e->getMessage());
    setMessage('حدث خطأ في تحميل تفاصيل القسم', 'danger');
    header('Location: departments.php');
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
                            <h4><i class="glyphicon glyphicon-edit"></i> تعديل القسم</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="view_department.php?id=<?php echo $department['department_id']; ?>" class="btn btn-info">
                                <i class="glyphicon glyphicon-eye-open"></i> عرض التفاصيل
                            </a>
                            <a href="departments.php" class="btn btn-default">
                                <i class="glyphicon glyphicon-arrow-right"></i> العودة للقائمة
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <?php if ($usersCount > 0 || $licensesCount > 0): ?>
                        <div class="alert alert-warning">
                            <i class="glyphicon glyphicon-warning-sign"></i>
                            <strong>تحذير:</strong> هذا القسم مرتبط بـ 
                            <strong><?php echo $usersCount; ?></strong> موظف و 
                            <strong><?php echo $licensesCount; ?></strong> ترخيص.
                            تغيير المشروع قد يؤثر على هذه البيانات.
                        </div>
                    <?php endif; ?>
                    
                    <form id="editDepartmentForm" method="POST">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="department_id" value="<?php echo $department['department_id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_name">اسم القسم <span class="text-danger">*</span></label>
                                    <input type="text" id="department_name" name="department_name" class="form-control" required 
                                           placeholder="أدخل اسم القسم" minlength="3"
                                           value="<?php echo htmlspecialchars($department['department_name']); ?>" tabindex="1">
                                    <small class="text-muted">3 أحرف على الأقل</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_email">البريد الإلكتروني للقسم</label>
                                    <input type="email" id="department_email" name="department_email" class="form-control" 
                                           placeholder="أدخل البريد الإلكتروني للقسم (اختياري)" tabindex="2"
                                           value="<?php echo htmlspecialchars($department['department_email'] ?? ''); ?>">
                                    <small class="text-muted">سيتم إرسال الإشعارات لهذا البريد</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="department_description">وصف القسم</label>
                                    <textarea id="department_description" name="department_description" class="form-control" 
                                           placeholder="أدخل وصف القسم (اختياري)" tabindex="3" rows="3"><?php echo htmlspecialchars($department['department_description'] ?? ''); ?></textarea>
                                    <small class="text-muted">وصف مختصر للقسم وأنشطته</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-success btn-lg" tabindex="3">
                                <i class="glyphicon glyphicon-save"></i> حفظ التعديلات
                            </button>
                            <a href="view_department.php?id=<?php echo $department['department_id']; ?>" class="btn btn-default btn-lg" tabindex="4">
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

    
    // Form submission
    $('#editDepartmentForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fa fa-spinner fa-spin"></i> جاري الحفظ...').prop('disabled', true);
        
        $.ajax({
            url: 'php_action/edit_department.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(function() {
                        window.location.href = 'view_department.php?id=' + <?php echo $department['department_id']; ?>;
                    }, 2000);
                } else {
                    showAlert(response.message, 'danger');
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

/* Warning styling */
.alert-warning {
    border-left: 4px solid #f0ad4e;
}
</style>

<?php include 'includes/footer.php'; ?> 
