<?php
$pageTitle = 'عرض تفاصيل المستخدم';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check if user has permission to view users
if (!hasPermission('users_view') && getUserRole() !== 'super_admin') {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بعرض تفاصيل المستخدمين', 'danger');
    exit;
}

// Get user ID from URL
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

if (!$userId) {
    setMessage('معرف المستخدم غير صحيح', 'danger');
    header('Location: users.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get user details (active or deleted based on request)
    $activeCondition = $isDeleted ? "u.is_active = 0" : "u.is_active = 1";
    $query = "
        SELECT u.*, d.department_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE u.user_id = ? AND $activeCondition
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $message = $isDeleted ? 'المستخدم المحذوف غير موجود' : 'المستخدم غير موجود أو تم حذفه';
        setMessage($message, 'danger');
        $redirectUrl = $isDeleted ? 'deleted_users.php' : 'users.php';
        header("Location: $redirectUrl");
        exit;
    }
    
    // Check department access for non-super_admin users
    $currentUserRole = getUserRole();
    $currentUserDepartment = getUserDepartment();
    
    if ($currentUserRole !== 'super_admin' && $currentUserDepartment != $user['department_id']) {
        setMessage('غير مصرح لك بعرض مستخدمين من أقسام أخرى', 'danger');
        $redirectUrl = $isDeleted ? 'deleted_users.php' : 'users.php';
        header("Location: $redirectUrl");
        exit;
    }
    
    // Get user statistics if active
    $userStats = [];
    // Note: License statistics removed since admin_id column no longer exists
    
} catch (Exception $e) {
    error_log("View user error: " . $e->getMessage());
    setMessage('حدث خطأ في تحميل تفاصيل المستخدم', 'danger');
    header('Location: users.php');
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
                            <h4><i class="glyphicon glyphicon-user"></i> تفاصيل المستخدم</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <?php if ($isDeleted): ?>
                                <a href="deleted_users.php" class="btn btn-warning">
                                    <i class="glyphicon glyphicon-arrow-right"></i> العودة للمستخدمين المحذوفين
                                </a>
                                <button class="btn btn-success restore-user" data-id="<?php echo $user['user_id']; ?>" data-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                    <i class="glyphicon glyphicon-refresh"></i> استعادة المستخدم
                                </button>

                            <?php else: ?>
                                <a href="users.php" class="btn btn-default">
                                    <i class="glyphicon glyphicon-arrow-right"></i> العودة للقائمة
                                </a>
                                <?php if (hasPermission('users_edit') || getUserRole() === 'super_admin'): ?>
                                <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-warning">
                                    <i class="glyphicon glyphicon-edit"></i> تعديل
                                </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <?php if ($isDeleted): ?>
                        <div class="alert alert-warning">
                            <i class="glyphicon glyphicon-exclamation-sign"></i>
                            <strong>تنبيه:</strong> هذا المستخدم محذوف مؤقتاً. يمكن استعادته.
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <!-- User Information -->
                        <div class="col-md-6">
                            <h5><i class="glyphicon glyphicon-info-sign"></i> معلومات المستخدم</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">اسم المستخدم:</th>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>الاسم الكامل:</th>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>البريد الإلكتروني:</th>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>الدور:</th>
                                    <td>
                                        <?php
                                        $roleClass = '';
                                        $roleText = '';
                                        switch ($user['role']) {
                                            case 'super_admin':
                                                $roleClass = 'label-danger';
                                                $roleText = 'مشرف عام';
                                                break;
                                            case 'admin':
                                                $roleClass = 'label-warning';
                                                $roleText = 'مشرف';
                                                break;
                                            default:
                                                $roleClass = 'label-info';
                                                $roleText = 'مستخدم عادي';
                                        }
                                        ?>
                                        <span class="label <?php echo $roleClass; ?>"><?php echo $roleText; ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>القسم:</th>
                                    <td><?php echo htmlspecialchars($user['department_name'] ?? 'غير محدد'); ?></td>
                                </tr>
                                <tr>
                                    <th>الحالة:</th>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="label label-success">نشط</span>
                                        <?php else: ?>
                                            <span class="label label-danger">غير نشط</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>تاريخ الإنشاء:</th>
                                    <td><?php echo formatDateTime($user['created_at']); ?></td>
                                </tr>
                                <?php if ($user['created_at'] !== $user['updated_at']): ?>
                                <tr>
                                    <th>آخر تحديث:</th>
                                    <td><?php echo formatDateTime($user['updated_at']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($user['last_login'])): ?>
                                <tr>
                                    <th>آخر دخول:</th>
                                    <td><?php echo formatDateTime($user['last_login']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        
                        <!-- User Statistics (for admins) -->
                        <div class="col-md-6">
                            <?php if (!$isDeleted && $user['role'] === 'admin' && !empty($userStats)): ?>
                                <h5><i class="glyphicon glyphicon-stats"></i> إحصائيات المشرف</h5>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="panel panel-info">
                                            <div class="panel-heading">
                                                <h6>التراخيص المُدارة</h6>
                                            </div>
                                            <div class="panel-body">
                                                <div class="row text-center">
                                                    <div class="col-md-4">
                                                        <div class="stat-item">
                                                            <h3 class="text-primary"><?php echo $userStats['total_licenses']; ?></h3>
                                                            <p>إجمالي التراخيص</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="stat-item">
                                                            <h3 class="text-success"><?php echo $userStats['active_licenses']; ?></h3>
                                                            <p>التراخيص النشطة</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="stat-item">
                                                            <h3 class="text-danger"><?php echo $userStats['deleted_licenses']; ?></h3>
                                                            <p>التراخيص المحذوفة</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif (!$isDeleted && ($user['role'] === 'regular' || $user['role'] === 'user')): ?>
                                <h5><i class="glyphicon glyphicon-info-sign"></i> معلومات إضافية</h5>
                                <div class="alert alert-info">
                                    <h6>المستخدم العادي</h6>
                                    <p>هذا مستخدم عادي يمكنه عرض التراخيص فقط دون إمكانية التعديل أو الإضافة.</p>
                                </div>
                            <?php elseif (!$isDeleted && $user['role'] === 'super_admin'): ?>
                                <h5><i class="glyphicon glyphicon-info-sign"></i> معلومات إضافية</h5>
                                <div class="alert alert-success">
                                    <h6>مشرف عام</h6>
                                    <p>هذا مشرف عام له صلاحيات كاملة في النظام.</p>
                                </div>
                            <?php elseif ($isDeleted): ?>
                                <h5><i class="glyphicon glyphicon-warning-sign"></i> معلومات الحساب</h5>
                                <div class="alert alert-warning">
                                    <h6>حساب محذوف</h6>
                                    <p>هذا الحساب محذوف مؤقتاً ولا يمكن للمستخدم تسجيل الدخول.</p>
                                    <p>يمكن استعادة الحساب في أي وقت.</p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Quick Actions -->
                            <?php if (!$isDeleted): ?>
                                <h5><i class="glyphicon glyphicon-flash"></i> إجراءات سريعة</h5>
                                <div class="list-group">
                                    <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="list-group-item">
                                        <i class="glyphicon glyphicon-edit"></i> تعديل بيانات المستخدم
                                    </a>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <a href="licenses.php?admin_id=<?php echo $user['user_id']; ?>" class="list-group-item">
                                            <i class="glyphicon glyphicon-list"></i> عرض التراخيص المُدارة
                                        </a>
                                    <?php endif; ?>
                                    <button class="list-group-item delete-user" data-id="<?php echo $user['user_id']; ?>" data-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                        <i class="glyphicon glyphicon-remove"></i> إلغاء تفعيل المستخدم
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isDeleted): ?>
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
                <p>هل أنت متأكد من استعادة هذا المستخدم؟</p>
                <div id="userToRestore" class="alert alert-success"></div>
                <small class="text-muted">سيتم إعادة تفعيل المستخدم وإرجاعه للقائمة النشطة.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                <button type="button" id="confirmRestoreBtn" class="btn btn-success">استعادة</button>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">تأكيد إلغاء التفعيل</h4>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من إلغاء تفعيل هذا المستخدم؟</p>
                <div id="userToDelete" class="alert alert-warning"></div>
                <small class="text-muted">ملاحظة: سيتم إلغاء تفعيل المستخدم مؤقتاً ويمكن استعادته لاحقاً.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger">إلغاء التفعيل</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Deleted user styling */
<?php if ($isDeleted): ?>
.panel-default {
    border-color: #f0ad4e;
}

.panel-body {
    background-color: #fff9f5;
}
<?php endif; ?>

/* Table styling */
.table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

/* Stats styling */
.stat-item {
    padding: 10px;
    border-radius: 4px;
    background-color: #f9f9f9;
    margin-bottom: 10px;
}

.stat-item h3 {
    margin: 0;
    font-weight: bold;
}

.stat-item p {
    margin: 5px 0 0 0;
    color: #666;
}

/* List group styling */
.list-group-item {
    cursor: pointer;
}

.list-group-item:hover {
    background-color: #f5f5f5;
}
</style>

<script>
$(document).ready(function() {
    <?php if ($isDeleted): ?>
    // Restore user functionality
    $('.restore-user').click(function() {
        const userId = $(this).data('id');
        const userName = $(this).data('name');
        
        $('#userToRestore').html(`<strong>المستخدم:</strong> ${userName}`);
        $('#confirmRestoreBtn').data('id', userId);
        $('#restoreModal').modal('show');
    });
    
    $('#confirmRestoreBtn').click(function() {
        const userId = $(this).data('id');
        
        $.post('php_action/restore_user.php', {
            user_id: userId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
        .done(function(response) {
            $('#restoreModal').modal('hide');
            
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    window.location.href = 'view_user.php?id=' + userId;
                }, 2000);
            } else {
                showAlert(response.error, 'danger');
            }
        })
        .fail(function() {
            $('#restoreModal').modal('hide');
            showAlert('فشل في استعادة المستخدم', 'danger');
        });
    });
    <?php else: ?>
    // Delete user functionality
    $('.delete-user').click(function() {
        const userId = $(this).data('id');
        const userName = $(this).data('name');
        
        $('#userToDelete').html(`<strong>المستخدم:</strong> ${userName}`);
        $('#confirmDeleteBtn').data('id', userId);
        $('#deleteModal').modal('show');
    });
    
    $('#confirmDeleteBtn').click(function() {
        const userId = $(this).data('id');
        
        $.post('php_action/delete_user.php', {
            user_id: userId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
        .done(function(response) {
            $('#deleteModal').modal('hide');
            
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    window.location.href = 'users.php';
                }, 2000);
            } else {
                showAlert(response.error, 'danger');
            }
        })
        .fail(function() {
            $('#deleteModal').modal('hide');
            showAlert('فشل في إلغاء تفعيل المستخدم', 'danger');
        });
    });
    <?php endif; ?>
    
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

<?php include 'includes/footer.php'; ?> 