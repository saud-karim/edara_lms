<?php
$pageTitle = 'عرض تفاصيل الترخيص';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Require login to access this page
requireLogin();

// Check if user has any license view permission
$canViewLicenses = hasPermission('licenses_view') || 
                   hasPermission('personal_licenses_view') || 
                   hasPermission('vehicle_licenses_view') ||
                   getUserRole() === 'super_admin';

if (!$canViewLicenses) {
    setMessage('غير مصرح لك بعرض التراخيص', 'danger');
    header('Location: dashboard.php');
    exit;
}

$userRole = getUserRole();
$userDepartment = getUserDepartment();

// Get license ID and type from URL
$licenseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$licenseType = isset($_GET['type']) ? $_GET['type'] : 'personal'; // default to personal
$isDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

if (!$licenseId) {
    setMessage('معرف الترخيص غير صحيح', 'danger');
    header('Location: licenses.php');
    exit;
}

// Validate license type
if (!in_array($licenseType, ['personal', 'vehicle'])) {
    $licenseType = 'personal';
}

try {
    $conn = getDBConnection();
    
    // Build query based on license type
    $activeCondition = $isDeleted ? "is_active = 0" : "is_active = 1";
    
    if ($licenseType === 'personal') {
        $query = "
            SELECT l.*, p.project_name, d.department_name,
                   CASE 
                       WHEN l.expiration_date < CURDATE() THEN 'منتهي الصلاحية'
                       WHEN l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'ينتهي قريباً'
                       ELSE 'نشط'
                   END as status,
                   DATEDIFF(l.expiration_date, CURDATE()) as days_until_expiration
            FROM personal_licenses l
            LEFT JOIN projects p ON l.project_id = p.project_id
            LEFT JOIN departments d ON l.department_id = d.department_id
            WHERE l.license_id = ? AND l.$activeCondition
        ";
    } else {
        $query = "
            SELECT l.*, p.project_name, d.department_name,
                   CASE 
                       WHEN l.expiration_date < CURDATE() THEN 'منتهي الصلاحية'
                       WHEN l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'ينتهي قريباً'
                       ELSE 'نشط'
                   END as status,
                   DATEDIFF(l.expiration_date, CURDATE()) as days_until_expiration
            FROM vehicle_licenses l
            LEFT JOIN projects p ON l.project_id = p.project_id
            LEFT JOIN departments d ON l.department_id = d.department_id
            WHERE l.license_id = ? AND l.$activeCondition
        ";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$licenseId]);
    $license = $stmt->fetch();
    
    if (!$license) {
        $message = $isDeleted ? 'الترخيص المحذوف غير موجود' : 'الترخيص غير موجود أو تم حذفه';
        setMessage($message, 'danger');
        $redirectUrl = $isDeleted ? 'deleted_licenses.php' : 'licenses.php';
        header("Location: $redirectUrl");
        exit;
    }
    
    // Check if user can view this license using Admin Teams System
    $tableName = ($licenseType === 'personal' ? 'personal_licenses' : 'vehicle_licenses');
    $licenseFilter = getLicenseFilter('l'); // Use table alias 'l'
    $checkViewStmt = $conn->prepare("SELECT COUNT(*) FROM $tableName l WHERE l.license_id = ? AND ($licenseFilter)");
    $checkViewStmt->execute([$licenseId]);
    
    if ($checkViewStmt->fetchColumn() == 0) {
        setMessage('غير مصرح لك بعرض هذا الترخيص', 'danger');
        header('Location: licenses.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("View license error: " . $e->getMessage());
    setMessage('حدث خطأ في تحميل تفاصيل الترخيص', 'danger');
    header('Location: licenses.php');
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
                                تفاصيل <?php echo $licenseType === 'personal' ? 'رخصة القيادة الشخصية' : 'رخصة المركبة'; ?>
                            </h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <?php if ($isDeleted): ?>
                                <?php if (canEditRecords()): ?>
                                    <button id="restoreBtn" class="btn btn-success" data-id="<?php echo $license['license_id']; ?>">
                                        <i class="glyphicon glyphicon-refresh"></i> استعادة الترخيص
                                    </button>
                                <?php endif; ?>

                            <?php else: ?>
                                <?php 
                            // Check if user can edit this specific license using Admin Teams System
                            $canEditThisLicense = canModifyLicense($license['user_id']);
                                ?>
                                <?php if ($canEditThisLicense): ?>
                                    <a href="edit_license.php?id=<?php echo $license['license_id']; ?>&type=<?php echo $licenseType; ?>" class="btn btn-warning">
                                        <i class="glyphicon glyphicon-edit"></i> تعديل
                                    </a>
                                <?php endif; ?>
                                <a href="licenses.php" class="btn btn-info">
                                    <i class="glyphicon glyphicon-arrow-right"></i> العودة للقائمة
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="panel-body">
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-md-6">
                            <div class="info-box">
                                <h5><i class="glyphicon glyphicon-info-sign"></i> المعلومات الأساسية</h5>
                                <table class="table table-bordered info-table">
                                    <?php if ($licenseType === 'personal'): ?>
                                        <tr>
                                            <td><strong>رقم الرخصة:</strong></td>
                                            <td><?php echo htmlspecialchars($license['license_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>اسم صاحب الرخصة:</strong></td>
                                            <td><?php echo htmlspecialchars($license['full_name']); ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td><strong>رقم المركبة:</strong></td>
                                            <td><?php echo htmlspecialchars($license['car_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>نوع المركبة:</strong></td>
                                            <td><?php echo htmlspecialchars($license['vehicle_type']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong>المشروع:</strong></td>
                                        <td><?php echo htmlspecialchars($license['project_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>القسم:</strong></td>
                                        <td><?php echo htmlspecialchars($license['department_name']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Date Information -->
                        <div class="col-md-6">
                            <div class="info-box">
                                <h5><i class="glyphicon glyphicon-calendar"></i> معلومات التواريخ</h5>
                                <table class="table table-bordered info-table">
                                    <tr>
                                        <td><strong>تاريخ الإصدار:</strong></td>
                                        <td><?php echo date('d/m/Y', strtotime($license['issue_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>تاريخ الانتهاء:</strong></td>
                                        <td><?php echo date('d/m/Y', strtotime($license['expiration_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>الحالة:</strong></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusIcon = '';
                                            if ($license['status'] === 'نشط') {
                                                $statusClass = 'status-active';
                                                $statusIcon = 'glyphicon-ok-circle';
                                            } elseif ($license['status'] === 'ينتهي قريباً') {
                                                $statusClass = 'status-expiring';
                                                $statusIcon = 'glyphicon-warning-sign';
                                            } else {
                                                $statusClass = 'status-expired';
                                                $statusIcon = 'glyphicon-remove-circle';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <i class="glyphicon <?php echo $statusIcon; ?>"></i>
                                                <?php echo $license['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($license['days_until_expiration'] > 0): ?>
                                        <tr>
                                            <td><strong>الأيام المتبقية:</strong></td>
                                            <td><?php echo $license['days_until_expiration']; ?> يوم</td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Notes -->
                        <div class="col-md-12">
                            <div class="info-box">
                                <h5><i class="glyphicon glyphicon-comment"></i> ملاحظات</h5>
                                <div class="notes-content">
                                    <?php if (!empty($license['notes'])): ?>
                                        <p><?php echo nl2br(htmlspecialchars($license['notes'])); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted">لا توجد ملاحظات</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- License Images -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="info-box">
                                <h5><i class="glyphicon glyphicon-picture"></i> صور الترخيص</h5>
                                <div class="row" style="text-align: center;">
                                    <!-- الوجه الخلفي على الشمال -->
                                    <div class="col-md-6" style="border-right: 3px solid #3498db;">
                                        <h6><i class="glyphicon glyphicon-arrow-left"></i> الوجه الخلفي <small class="text-muted">(شمال)</small></h6>
                                        <?php if (!empty($license['back_image_path'])): ?>
                                            <?php 
                                            // Build correct image path with fallback handling
                                            $backImagePath = $license['back_image_path'];
                                            
                                            // Check multiple possible paths
                                            $imagePaths = [
                                                $backImagePath,
                                                './' . $backImagePath,
                                                                'assests/uploads/personal_licenses/' . basename($backImagePath),
                'assests/uploads/vehicle_licenses/' . basename($backImagePath),
                'assests/uploads/licenses/' . basename($backImagePath)
                                            ];
                                            
                                            $workingPath = null;
                                            foreach ($imagePaths as $path) {
                                                if (file_exists($path)) {
                                                    $workingPath = $path;
                                                    break;
                                                }
                                            }
                                            ?>
                                            
                                            <?php if ($workingPath): ?>
                                                <div class="license-image">
                                                    <img src="<?php echo htmlspecialchars($workingPath); ?>" 
                                                         alt="الوجه الخلفي للترخيص" 
                                                         class="img-responsive img-thumbnail license-preview"
                                                         onclick="showImageModal(this.src, 'الوجه الخلفي للترخيص')"
                                                         onerror="this.parentElement.innerHTML='<div class=&quot;no-image&quot;><i class=&quot;glyphicon glyphicon-picture&quot;></i><p>خطأ في تحميل الصورة</p></div>'">
                                                </div>
                                            <?php else: ?>
                                                <div class="no-image">
                                                    <i class="glyphicon glyphicon-picture"></i>
                                                    <p>الصورة غير موجودة</p>
                                                    <small class="text-muted">المسار: <?php echo htmlspecialchars($backImagePath); ?></small>
                                                    <br><small style="color:#666;">تحقق من وجود الملف أو أعد رفع الصورة</small>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="glyphicon glyphicon-picture"></i>
                                                <p>لا توجد صورة خلفية</p>
                                                <small style="color:#666;">يمكنك إضافة صورة عبر صفحة التعديل</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <!-- الوجه الأمامي على اليمين -->
                                    <div class="col-md-6" style="border-left: 3px solid #27ae60;">
                                        <h6><i class="glyphicon glyphicon-arrow-right"></i> الوجه الأمامي <small class="text-muted">(يمين)</small></h6>
                                        <?php if (!empty($license['front_image_path'])): ?>
                                            <?php 
                                            // Build correct image path with fallback handling
                                            $frontImagePath = $license['front_image_path'];
                                            
                                            // Check multiple possible paths
                                            $imagePaths = [
                                                $frontImagePath,
                                                './' . $frontImagePath,
                                                                'assests/uploads/personal_licenses/' . basename($frontImagePath),
                'assests/uploads/vehicle_licenses/' . basename($frontImagePath),
                'assests/uploads/licenses/' . basename($frontImagePath)
                                            ];
                                            
                                            $workingPath = null;
                                            foreach ($imagePaths as $path) {
                                                if (file_exists($path)) {
                                                    $workingPath = $path;
                                                    break;
                                                }
                                            }
                                            ?>
                                            
                                            <?php if ($workingPath): ?>
                                                <div class="license-image">
                                                    <img src="<?php echo htmlspecialchars($workingPath); ?>" 
                                                         alt="الوجه الأمامي للترخيص" 
                                                         class="img-responsive img-thumbnail license-preview"
                                                         onclick="showImageModal(this.src, 'الوجه الأمامي للترخيص')"
                                                         onerror="this.parentElement.innerHTML='<div class=&quot;no-image&quot;><i class=&quot;glyphicon glyphicon-picture&quot;></i><p>خطأ في تحميل الصورة</p></div>'">
                                                </div>
                                            <?php else: ?>
                                                <div class="no-image">
                                                    <i class="glyphicon glyphicon-picture"></i>
                                                    <p>الصورة غير موجودة</p>
                                                    <small class="text-muted">المسار: <?php echo htmlspecialchars($frontImagePath); ?></small>
                                                    <br><small style="color:#666;">تحقق من وجود الملف أو أعد رفع الصورة</small>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="glyphicon glyphicon-picture"></i>
                                                <p>لا توجد صورة أمامية</p>
                                                <small style="color:#666;">يمكنك إضافة صورة عبر صفحة التعديل</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isDeleted): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-warning">
                                    <i class="glyphicon glyphicon-warning-sign"></i>
                                    <strong>تحذير:</strong> هذا الترخيص محذوف ولا يظهر في القائمة الرئيسية.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="imageModalTitle"></h4>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="" class="img-responsive" style="max-width: 100%;">
            </div>
        </div>
    </div>
</div>

<script>
// Image modal functionality
function showImageModal(src, title) {
    $('#modalImage').attr('src', src);
    $('#imageModalTitle').text(title);
    $('#imageModal').modal('show');
}

// Enhanced image loading with retry functionality
$(document).ready(function() {
    // Add loading indicators to images
    $('.license-preview').on('load', function() {
        $(this).fadeIn();
    }).on('error', function() {
        console.log('Error loading image:', this.src);
        // Try with different path if the first one fails
        const originalSrc = this.src;
        if (!originalSrc.includes('./')) {
            this.src = './' + originalSrc;
        }
    });
    
    // Add retry button for failed images
    $('.no-image').each(function() {
        const imagePath = $(this).find('small.text-muted').text().replace('المسار: ', '');
        if (imagePath && imagePath !== '') {
            $(this).append('<br><button class="btn btn-sm btn-default retry-image" data-path="' + imagePath + '">إعادة المحاولة</button>');
        }
    });
    
    // Handle retry button clicks
    $(document).on('click', '.retry-image', function() {
        const imagePath = $(this).data('path');
        const container = $(this).closest('.no-image').parent();
        
        // Show loading
        $(this).text('جاري التحميل...').prop('disabled', true);
        
        // Create new image element
        const newImg = $('<img>')
            .attr('src', imagePath)
            .attr('alt', 'صورة الترخيص')
            .addClass('img-responsive img-thumbnail license-preview')
            .attr('onclick', "showImageModal(this.src, 'صورة الترخيص')")
            .on('load', function() {
                container.html('<div class="license-image"></div>');
                container.find('.license-image').append(newImg);
            })
            .on('error', function() {
                $(this).closest('.retry-image').text('فشل في التحميل').removeClass('btn-default').addClass('btn-danger');
            });
    });
});

// Restore license functionality
<?php if ($isDeleted && canEditRecords()): ?>
$('#restoreBtn').on('click', function() {
    const licenseId = $(this).data('id');
    const licenseType = '<?php echo $licenseType; ?>';
    
    if (confirm('هل أنت متأكد من استعادة هذا الترخيص؟')) {
        $.post('php_action/restore_license.php', {
            license_id: licenseId,
            license_type: licenseType
        })
        .done(function(response) {
            if (response.success) {
                alert(response.message);
                window.location.href = 'view_license.php?id=' + licenseId + '&type=' + licenseType;
            } else {
                alert(response.message || 'حدث خطأ في استعادة الترخيص');
            }
        })
        .fail(function() {
            alert('حدث خطأ في الاتصال بالخادم');
        });
    }
});
<?php endif; ?>
</script>

<style>
.info-box {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
}

.info-box h5 {
    margin-top: 0;
}

.license-image {
    text-align: center;
    margin-bottom: 15px;
}

.license-image img {
    max-width: 100%;
    height: auto;
    border: 2px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.license-image img:hover {
    border-color: #337ab7;
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(51, 122, 183, 0.3);
}

.no-image {
    text-align: center;
    padding: 40px 20px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    color: #999;
    background: #f9f9f9;
}

.no-image i {
    font-size: 48px;
    margin-bottom: 10px;
    color: #ccc;
}

.no-image p {
    margin: 5px 0;
    font-size: 14px;
}

.no-image small {
    font-size: 11px;
    color: #666;
    word-break: break-all;
}
    margin-bottom: 15px;
    color: #337ab7;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}

.info-table td {
    padding: 8px 12px !important;
    vertical-align: middle !important;
}

.info-table td:first-child {
    background-color: #f5f5f5;
    width: 40%;
}

.license-image {
    text-align: center;
    border: 2px dashed #ddd;
    padding: 20px;
    margin-bottom: 10px;
    border-radius: 5px;
}

.license-preview {
    max-height: 200px;
    cursor: pointer;
    transition: transform 0.2s;
}

.license-preview:hover {
    transform: scale(1.05);
}

.no-image {
    text-align: center;
    padding: 40px;
    color: #999;
    border: 2px dashed #ddd;
    border-radius: 5px;
}

.no-image i {
    font-size: 48px;
    margin-bottom: 10px;
    display: block;
}

.notes-content {
    min-height: 60px;
    padding: 10px;
    background: white;
    border-radius: 3px;
    border: 1px solid #ddd;
}

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

/* Responsive adjustments */
@media (max-width: 768px) {
    .info-table td:first-child {
        width: 50%;
    }
    
    .license-image {
        padding: 15px;
    }
    
    .license-preview {
        max-height: 150px;
    }
}
</style>

<?php include 'includes/footer.php'; ?> 