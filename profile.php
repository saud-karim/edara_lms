<?php
$pageTitle = 'الملف الشخصي';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check if user is logged in
requireLogin();

$userId = getUserId();
$userRole = getUserRole();

try {
    $conn = getDBConnection();
    
    // Get user information with department and project details
    $stmt = $conn->prepare("
        SELECT u.*, d.department_name, p.project_name, p.project_id
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN projects p ON u.project_id = p.project_id
        WHERE u.user_id = ? AND u.is_active = 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setMessage('المستخدم غير موجود', 'danger');
        header('Location: dashboard.php');
        exit;
    }
    
    // Get user permissions count
    $permStmt = $conn->prepare("
        SELECT COUNT(*) as permissions_count 
        FROM user_permissions up 
        JOIN permissions p ON up.permission_id = p.permission_id 
        WHERE up.user_id = ? AND up.is_active = 1 AND p.is_active = 1
    ");
    $permStmt->execute([$userId]);
    $permissionsData = $permStmt->fetch();
    $permissionsCount = $permissionsData['permissions_count'] ?? 0;
    
} catch (Exception $e) {
    error_log("Profile page error: " . $e->getMessage());
    setMessage('حدث خطأ في تحميل الملف الشخصي', 'danger');
    header('Location: dashboard.php');
    exit;
}

include 'includes/header.php';
?>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="row">
        <div class="col-md-4">
            <!-- User Info Card -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4><i class="glyphicon glyphicon-user"></i> معلومات المستخدم</h4>
                </div>
                <div class="panel-body text-center">
                    <div class="user-avatar">
                        <i class="glyphicon glyphicon-user" style="font-size: 80px; color: #337ab7;"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                    
                    <?php 
                    $roleNames = [
                        'super_admin' => 'مدير عام',
                        'admin' => 'مشرف',
                        'user' => 'مستخدم عادي'
                    ];
                    $roleBadgeClass = [
                        'super_admin' => 'label-danger',
                        'admin' => 'label-warning',
                        'user' => 'label-info'
                    ];
                    ?>
                    
                    <p>
                        <span class="label <?php echo $roleBadgeClass[$user['role']] ?? 'label-default'; ?>">
                            <?php echo $roleNames[$user['role']] ?? $user['role']; ?>
                        </span>
                    </p>
                    
                    <?php if ($user['department_name']): ?>
                        <p><strong>القسم:</strong> <?php echo htmlspecialchars($user['department_name']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($user['project_name']): ?>
                        <p><strong>المشروع:</strong> <?php echo htmlspecialchars($user['project_name']); ?></p>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-xs-12 text-center">
                            <h4><?php echo $permissionsCount; ?></h4>
                            <p class="text-muted">صلاحية مخصصة</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Status -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4><i class="glyphicon glyphicon-info-sign"></i> حالة الحساب</h4>
                </div>
                <div class="panel-body">
                    <p><strong>الحالة:</strong> 
                        <span class="label label-success">نشط</span>
                    </p>
                    <p><strong>تاريخ الإنشاء:</strong><br>
                        <small><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></small>
                    </p>
                    <?php if ($user['last_login']): ?>
                        <p><strong>آخر تسجيل دخول:</strong><br>
                            <small><?php echo date('Y-m-d H:i', strtotime($user['last_login'])); ?></small>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Edit Profile Form -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4><i class="glyphicon glyphicon-edit"></i> تحديث الملف الشخصي</h4>
                </div>
                <div class="panel-body">
                    <form id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name" class="required">الاسم الكامل</label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                           tabindex="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="required">البريد الإلكتروني</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                                           tabindex="2" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">اسم المستخدم</label>
                                    <input type="text" id="username" name="username" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" 
                                           tabindex="3" readonly>
                                    <small class="text-muted">لا يمكن تغيير اسم المستخدم</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role">الدور</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo $roleNames[$user['role']] ?? $user['role']; ?>" readonly>
                                    <small class="text-muted">يتم تحديد الدور من قبل المشرف</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-primary btn-lg" tabindex="4">
                                <i class="glyphicon glyphicon-save"></i> حفظ التعديلات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Change Password Form -->
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h4><i class="glyphicon glyphicon-lock"></i> تغيير كلمة المرور</h4>
                </div>
                <div class="panel-body">
                    <form id="passwordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="current_password" class="required">كلمة المرور الحالية</label>
                                    <input type="password" id="current_password" name="current_password" 
                                           class="form-control" tabindex="5" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="new_password" class="required">كلمة المرور الجديدة</label>
                                    <input type="password" id="new_password" name="new_password" 
                                           class="form-control" tabindex="6" required minlength="6">
                                    <small class="text-muted">6 أحرف على الأقل</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="confirm_password" class="required">تأكيد كلمة المرور</label>
                                    <input type="password" id="confirm_password" name="confirm_password" 
                                           class="form-control" tabindex="7" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-warning btn-lg" tabindex="8">
                                <i class="glyphicon glyphicon-lock"></i> تغيير كلمة المرور
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Profile update form
    $('#profileForm').submit(function(e) {
        e.preventDefault();
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جاري الحفظ...');
        
        $.post('php_action/update_profile.php', $(this).serialize())
            .done(function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    // Update displayed name if changed
                    if (response.full_name) {
                        $('h4:contains("' + response.old_name + '")').text(response.full_name);
                    }
                } else {
                    showAlert(response.error || response.message, 'danger');
                }
            })
            .fail(function(xhr) {
                console.error('Profile update failed:', xhr.responseText);
                showAlert('حدث خطأ في تحديث الملف الشخصي', 'danger');
            })
            .always(function() {
                submitBtn.prop('disabled', false).html(originalText);
            });
    });
    
    // Password change form
    $('#passwordForm').submit(function(e) {
        e.preventDefault();
        
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (newPassword !== confirmPassword) {
            showAlert('كلمة المرور الجديدة وتأكيدها غير متطابقان', 'danger');
            return;
        }
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جاري التغيير...');
        
        $.post('php_action/change_password.php', $(this).serialize())
            .done(function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#passwordForm')[0].reset();
                } else {
                    showAlert(response.error || response.message, 'danger');
                }
            })
            .fail(function(xhr) {
                console.error('Password change failed:', xhr.responseText);
                showAlert('حدث خطأ في تغيير كلمة المرور', 'danger');
            })
            .always(function() {
                submitBtn.prop('disabled', false).html(originalText);
            });
    });
    
    // Password confirmation validation
    $('#confirm_password').on('input', function() {
        const newPassword = $('#new_password').val();
        const confirmPassword = $(this).val();
        
        if (newPassword && confirmPassword) {
            if (newPassword === confirmPassword) {
                $(this).removeClass('has-error').addClass('has-success');
            } else {
                $(this).removeClass('has-success').addClass('has-error');
            }
        }
    });
    
    // Show alert function
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
.required:after {
    content: " *";
    color: red;
}

.user-avatar {
    margin-bottom: 20px;
}

.panel .panel-body .row h4 {
    margin-top: 0;
    margin-bottom: 5px;
}

.panel .panel-body .row p.text-muted {
    margin-bottom: 0;
    font-size: 12px;
}

.has-success {
    border-color: #5cb85c !important;
}

.has-error {
    border-color: #d9534f !important;
}
</style>

<?php include 'includes/footer.php'; ?> 
