<?php
$pageTitle = 'عرض تفاصيل القسم';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Check permission to view department details
if (!hasPermission('departments_view') && getUserRole() !== 'super_admin') {
    setMessage('ليس لديك صلاحية لعرض تفاصيل الأقسام', 'danger');
    header('Location: dashboard.php');
    exit;
}

// Get department ID from URL
$departmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

if (!$departmentId) {
    setMessage('معرف القسم غير صحيح', 'danger');
    header('Location: departments.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get department details (active or deleted based on request)
    $activeCondition = $isDeleted ? "d.is_active = 0" : "d.is_active = 1";
    $query = "
        SELECT d.*
        FROM departments d
        WHERE d.department_id = ? AND $activeCondition
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        $message = $isDeleted ? 'القسم المحذوف غير موجود' : 'القسم غير موجود أو تم حذفه';
        setMessage($message, 'danger');
        $redirectUrl = $isDeleted ? 'deleted_departments.php' : 'departments.php';
        header("Location: $redirectUrl");
        exit;
    }
    
    // Get associated projects through users
    $projectsQuery = "
        SELECT DISTINCT p.project_name 
        FROM users u 
        JOIN projects p ON u.project_id = p.project_id 
        WHERE u.department_id = ? AND u.is_active = 1 AND p.is_active = 1
        ORDER BY p.project_name
    ";
    $projectsStmt = $conn->prepare($projectsQuery);
    $projectsStmt->execute([$departmentId]);
    $departmentProjects = $projectsStmt->fetchAll(PDO::FETCH_COLUMN);
    $department['project_names'] = implode(', ', $departmentProjects) ?: 'غير محدد';
    
    // Get department statistics
    $stats = [];
    
    // Users count (active and inactive)
    $usersStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
        FROM users 
        WHERE department_id = ?
    ");
    $usersStmt->execute([$departmentId]);
    $stats['users'] = $usersStmt->fetch();
    
    // Personal licenses count (active and inactive)
    $personalLicensesStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_licenses,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_licenses,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_licenses
        FROM personal_licenses 
        WHERE department_id = ?
    ");
    $personalLicensesStmt->execute([$departmentId]);
    $personalLicensesStats = $personalLicensesStmt->fetch();
    
    // Vehicle licenses count (active and inactive)
    $vehicleLicensesStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_licenses,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_licenses,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_licenses
        FROM vehicle_licenses 
        WHERE department_id = ?
    ");
    $vehicleLicensesStmt->execute([$departmentId]);
    $vehicleLicensesStats = $vehicleLicensesStmt->fetch();
    
    // Combine license statistics
    $stats['licenses'] = [
        'total_licenses' => $personalLicensesStats['total_licenses'] + $vehicleLicensesStats['total_licenses'],
        'active_licenses' => $personalLicensesStats['active_licenses'] + $vehicleLicensesStats['active_licenses'],
        'inactive_licenses' => $personalLicensesStats['inactive_licenses'] + $vehicleLicensesStats['inactive_licenses']
    ];
    
    // License status breakdown for personal licenses (for active licenses only)
    $personalStatusStats = [];
    if (!$isDeleted) {
        $personalStatusStmt = $conn->prepare("
            SELECT 
                CASE 
                    WHEN expiration_date < CURDATE() THEN 'منتهي الصلاحية'
                    WHEN expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'ينتهي قريباً'
                    ELSE 'نشط'
                END as status,
                COUNT(*) as count
            FROM personal_licenses 
            WHERE department_id = ? AND is_active = 1
            GROUP BY status
        ");
        $personalStatusStmt->execute([$departmentId]);
        $personalStatusStats = $personalStatusStmt->fetchAll();
    }
    
    // License status breakdown for vehicle licenses (for active licenses only)
    $vehicleStatusStats = [];
    if (!$isDeleted) {
        $vehicleStatusStmt = $conn->prepare("
            SELECT 
                CASE 
                    WHEN expiration_date < CURDATE() THEN 'منتهي الصلاحية'
                    WHEN expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'ينتهي قريباً'
                    ELSE 'نشط'
                END as status,
                COUNT(*) as count
            FROM vehicle_licenses 
            WHERE department_id = ? AND is_active = 1
            GROUP BY status
        ");
        $vehicleStatusStmt->execute([$departmentId]);
        $vehicleStatusStats = $vehicleStatusStmt->fetchAll();
    }
    
    // Combine status statistics
    $combinedStatusStats = [];
    $statusCounts = ['نشط' => 0, 'ينتهي قريباً' => 0, 'منتهي الصلاحية' => 0];
    
    foreach ($personalStatusStats as $stat) {
        $statusCounts[$stat['status']] += $stat['count'];
    }
    
    foreach ($vehicleStatusStats as $stat) {
        $statusCounts[$stat['status']] += $stat['count'];
    }
    
    foreach ($statusCounts as $status => $count) {
        if ($count > 0) {
            $combinedStatusStats[] = ['status' => $status, 'count' => $count];
        }
    }
    
    $stats['license_status'] = $combinedStatusStats;
    
    // Recent users (last 5)
    $recentUsersStmt = $conn->prepare("
        SELECT user_id, username, full_name, role, created_at
        FROM users 
        WHERE department_id = ? AND is_active = 1
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentUsersStmt->execute([$departmentId]);
    $recentUsers = $recentUsersStmt->fetchAll();
    
    // Recent personal licenses (last 3)
    $recentPersonalLicensesStmt = $conn->prepare("
        SELECT license_id, license_number, full_name, expiration_date, created_at
        FROM personal_licenses 
        WHERE department_id = ? AND is_active = 1
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $recentPersonalLicensesStmt->execute([$departmentId]);
    $recentPersonalLicenses = $recentPersonalLicensesStmt->fetchAll();
    
    // Recent vehicle licenses (last 2)
    $recentVehicleLicensesStmt = $conn->prepare("
        SELECT license_id, car_number as license_number, expiration_date, created_at
        FROM vehicle_licenses 
        WHERE department_id = ? AND is_active = 1
        ORDER BY created_at DESC 
        LIMIT 2
    ");
    $recentVehicleLicensesStmt->execute([$departmentId]);
    $recentVehicleLicenses = $recentVehicleLicensesStmt->fetchAll();
    
    // Combine and limit recent licenses
    $recentLicenses = [];
    
    // Add personal licenses with type identifier
    foreach ($recentPersonalLicenses as $license) {
        $license['license_type'] = 'personal';
        $recentLicenses[] = $license;
    }
    
    // Add vehicle licenses with type identifier
    foreach ($recentVehicleLicenses as $license) {
        $license['license_type'] = 'vehicle';
        $license['full_name'] = 'رخصة مركبة'; // Add descriptive name
        $recentLicenses[] = $license;
    }
    
    // Sort by created_at and limit to 5
    usort($recentLicenses, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recentLicenses = array_slice($recentLicenses, 0, 5);
    
} catch (Exception $e) {
    error_log("View department error: " . $e->getMessage());
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
                            <h4>
                                <i class="glyphicon glyphicon-eye-open"></i> 
                                تفاصيل القسم
                            </h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <?php if ($isDeleted): ?>
                                <button class="btn btn-success restore-department" data-id="<?php echo $department['department_id']; ?>" data-name="<?php echo htmlspecialchars($department['department_name']); ?>">
                                    <i class="glyphicon glyphicon-refresh"></i> استعادة القسم
                                </button>
                                <a href="deleted_departments.php" class="btn btn-info">
                                    <i class="glyphicon glyphicon-arrow-right"></i> العودة للأقسام المحذوفة
                                </a>
                            <?php else: ?>
                                <?php if (hasPermission('departments_edit') || getUserRole() === 'super_admin'): ?>
                                <a href="edit_department.php?id=<?php echo $department['department_id']; ?>" class="btn btn-warning">
                                    <i class="glyphicon glyphicon-edit"></i> تعديل
                                </a>
                                <?php endif; ?>
                                <a href="departments.php" class="btn btn-info">
                                    <i class="glyphicon glyphicon-arrow-right"></i> العودة للقائمة
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <!-- Department Information -->
                    <div class="row">
                        <div class="col-md-8">
                            <h3 style="color: #2c3e50; margin-top: 0;">
                                <i class="glyphicon glyphicon-briefcase"></i>
                                <?php echo htmlspecialchars($department['department_name']); ?>
                            </h3>
                            
                            <div class="row">
                        <div class="col-md-6">
                                    <p><strong><i class="glyphicon glyphicon-folder-open"></i> المشاريع المرتبطة:</strong></p>
                                    <p class="text-muted"><?php echo htmlspecialchars($department['project_names']); ?></p>
                                    
                                    <?php if (!empty($department['department_email'])): ?>
                                    <p><strong><i class="glyphicon glyphicon-envelope"></i> بريد القسم الإلكتروني:</strong></p>
                                    <p class="text-muted">
                                        <a href="mailto:<?php echo htmlspecialchars($department['department_email']); ?>">
                                            <?php echo htmlspecialchars($department['department_email']); ?>
                                        </a>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($department['department_description'])): ?>
                                    <p><strong><i class="glyphicon glyphicon-file-text"></i> الوصف:</strong></p>
                                    <p class="text-muted"><?php echo htmlspecialchars($department['department_description']); ?></p>
                                        <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <p><strong><i class="glyphicon glyphicon-calendar"></i> تاريخ الإنشاء:</strong></p>
                                    <p class="text-muted"><?php echo formatDateTime($department['created_at'], 'd/m/Y - h:i A'); ?></p>
                                    
                                    <p><strong><i class="glyphicon glyphicon-edit"></i> آخر تحديث:</strong></p>
                                    <p class="text-muted"><?php echo formatDateTime($department['updated_at'], 'd/m/Y - h:i A'); ?></p>
                                    

                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Department Statistics -->
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h4 class="panel-title"><i class="glyphicon glyphicon-stats"></i> إحصائيات القسم</h4>
                                </div>
                                <div class="panel-body">
                                    <!-- Users Statistics -->
                                    <div class="row">
                                        <div class="col-xs-6">
                                            <div class="stat-box text-center" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px; background: #f9f9f9;">
                                                <h3 style="margin: 0; color: #3498db;"><?php echo $stats['users']['total_users']; ?></h3>
                                                <p style="margin: 0; font-size: 12px;">إجمالي الموظفين</p>
                                            </div>
                                        </div>
                                        <div class="col-xs-6">
                                            <div class="stat-box text-center" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px; background: #f9f9f9;">
                                                <h3 style="margin: 0; color: #27ae60;"><?php echo $stats['users']['active_users']; ?></h3>
                                                <p style="margin: 0; font-size: 12px;">الموظفين النشطين</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Licenses Statistics -->
                                    <div class="row">
                                        <div class="col-xs-6">
                                            <div class="stat-box text-center" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px; background: #f9f9f9;">
                                                <h3 style="margin: 0; color: #9b59b6;"><?php echo $stats['licenses']['total_licenses']; ?></h3>
                                                <p style="margin: 0; font-size: 12px;">إجمالي التراخيص</p>
                                            </div>
                                        </div>
                                        <div class="col-xs-6">
                                            <div class="stat-box text-center" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px; background: #f9f9f9;">
                                                <h3 style="margin: 0; color: #27ae60;"><?php echo $stats['licenses']['active_licenses']; ?></h3>
                                                <p style="margin: 0; font-size: 12px;">التراخيص النشطة</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- License Status Breakdown -->
                                    <?php if (!empty($stats['license_status'])): ?>
                                    <h5 style="margin: 15px 0 10px 0; color: #34495e;">حالة التراخيص:</h5>
                                            <?php foreach ($stats['license_status'] as $status): ?>
                                        <div class="row" style="margin-bottom: 5px;">
                                            <div class="col-xs-8">
                                                <small><?php echo $status['status']; ?></small>
                                            </div>
                                            <div class="col-xs-4 text-right">
                                                <span class="badge 
                                                        <?php
                                                    if ($status['status'] === 'نشط') echo 'badge-success';
                                                    elseif ($status['status'] === 'ينتهي قريباً') echo 'badge-warning';
                                                    else echo 'badge-danger';
                                                    ?>
                                                "><?php echo $status['count']; ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Recent Data -->
                    <div class="row">
                        <!-- Recent Users -->
                        <div class="col-md-6">
                            <h5><i class="glyphicon glyphicon-user"></i> أحدث الموظفين</h5>
                            <?php if (empty($recentUsers)): ?>
                                <div class="alert alert-info">
                                    <i class="glyphicon glyphicon-info-sign"></i>
                                    لا يوجد موظفين في هذا القسم حتى الآن.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive table-container">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>اسم المستخدم</th>
                                                <th>الاسم الكامل</th>
                                                <th>الدور</th>
                                                <th>تاريخ الانضمام</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentUsers as $user): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
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
                                                    <td><small><?php echo formatDateTime($user['created_at'], 'd/m/Y'); ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center">
                                    <a href="users.php?department_id=<?php echo $departmentId; ?>" class="btn btn-info btn-sm">
                                        <i class="glyphicon glyphicon-list"></i> عرض جميع الموظفين
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Recent Licenses -->
                        <div class="col-md-6">
                            <h5><i class="glyphicon glyphicon-file"></i> أحدث التراخيص</h5>
                            <?php if (empty($recentLicenses)): ?>
                                <div class="alert alert-info">
                                    <i class="glyphicon glyphicon-info-sign"></i>
                                    لا يوجد تراخيص في هذا القسم حتى الآن.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive table-container">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>رقم الترخيص</th>
                                                <th>الاسم</th>
                                                <th>تاريخ الانتهاء</th>
                                                <th>تاريخ الإضافة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentLicenses as $license): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($license['license_number']); ?></strong></td>
                                                    <td>
                                                        <?php 
                                                        if ($license['license_type'] === 'vehicle') {
                                                            echo '<span class="label label-primary">رخصة مركبة</span>';
                                                        } else {
                                                            echo htmlspecialchars($license['full_name']);
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $expirationDate = new DateTime($license['expiration_date']);
                                                        $today = new DateTime();
                                                        $diff = $today->diff($expirationDate);
                                                        
                                                        if ($expirationDate < $today) {
                                                            echo '<span class="text-danger">' . $expirationDate->format('d/m/Y') . '</span>';
                                                        } elseif ($diff->days <= 30) {
                                                            echo '<span class="text-warning">' . $expirationDate->format('d/m/Y') . '</span>';
                                                        } else {
                                                            echo '<span class="text-success">' . $expirationDate->format('d/m/Y') . '</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><small><?php echo formatDateTime($license['created_at'], 'd/m/Y'); ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center">
                                    <a href="licenses.php?department_id=<?php echo $departmentId; ?>" class="btn btn-info btn-sm">
                                        <i class="glyphicon glyphicon-list"></i> عرض جميع التراخيص
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>



<!-- نافذة تأكيد الاستعادة -->
<div class="modal fade" id="restoreModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">تأكيد استعادة القسم</h4>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من أنك تريد استعادة هذا القسم؟</p>
                <div id="departmentToRestore"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" id="confirmRestore">استعادة</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const csrfToken = '<?php echo generateCSRFToken(); ?>';
    

    
    // Restore department functionality
    $(document).on('click', '.restore-department', function() {
        const departmentId = $(this).data('id');
        const departmentName = $(this).data('name');
        
        $('#departmentToRestore').html(`<strong>${departmentName}</strong>`);
        $('#restoreModal').modal('show');
        
        $('#confirmRestore').off('click').on('click', function() {
        $.post('php_action/restore_department.php', {
                department_id: departmentId,
                csrf_token: csrfToken
        })
        .done(function(response) {
            $('#restoreModal').modal('hide');
            if (response.success) {
                    window.location.href = 'departments.php?message=' + encodeURIComponent(response.message);
            } else {
                    alert('خطأ: ' + response.message);
            }
        })
        .fail(function() {
            $('#restoreModal').modal('hide');
                alert('فشل في استعادة القسم');
            });
        });
    });
});
</script>

<style>
.info-box {
    margin-bottom: 20px;
}

.info-table {
    margin-bottom: 0;
}

.info-table td:first-child {
    background-color: #f5f5f5;
    font-weight: 500;
}

.badge-primary {
    background-color: #3498db;
}

.badge-success {
    background-color: #27ae60;
}

.badge-info {
    background-color: #9b59b6;
}

.badge-warning {
    background-color: #f39c12;
}

.badge-danger {
    background-color: #e74c3c;
}

.btn-block {
    height: 60px;
    margin-bottom: 10px;
}

.btn-block i {
    font-size: 18px;
    margin-bottom: 5px;
}
</style>

<?php include 'includes/footer.php'; ?> 