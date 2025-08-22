<?php
$pageTitle = 'تشخيص الصور';
require_once '../config/config.php';
require_once '../php_action/auth.php';

// Only superadmin can access this page
if (getUserRole() !== 'superadmin') {
    header('Location: ../dashboard.php');
    exit;
}

$conn = getDBConnection();
$results = validateAndFixImagePaths($conn);

include '../includes/header.php';
?>

<div class="container content-wrapper">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4><i class="glyphicon glyphicon-search"></i> تشخيص مسارات الصور</h4>
                </div>
                <div class="panel-body">
                    
                    <!-- Personal Licenses Results -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h5><i class="glyphicon glyphicon-user"></i> الرخص الشخصية</h5>
                                </div>
                                <div class="panel-body">
                                    <p><strong>تم فحص:</strong> <?php echo $results['personal_licenses']['checked']; ?> رخصة</p>
                                    <p><strong>المشاكل:</strong> <?php echo count($results['personal_licenses']['errors']); ?></p>
                                    
                                    <?php if (!empty($results['personal_licenses']['errors'])): ?>
                                        <div class="alert alert-warning">
                                            <h6><strong>المشاكل الموجودة:</strong></h6>
                                            <ul class="list-unstyled">
                                                <?php foreach ($results['personal_licenses']['errors'] as $error): ?>
                                                    <li><small><?php echo htmlspecialchars($error); ?></small></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-success">
                                            <i class="glyphicon glyphicon-ok"></i> جميع الصور موجودة ويمكن الوصول إليها
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Vehicle Licenses Results -->
                        <div class="col-md-6">
                            <div class="panel panel-success">
                                <div class="panel-heading">
                                    <h5><i class="glyphicon glyphicon-road"></i> رخص المركبات</h5>
                                </div>
                                <div class="panel-body">
                                    <p><strong>تم فحص:</strong> <?php echo $results['vehicle_licenses']['checked']; ?> رخصة</p>
                                    <p><strong>المشاكل:</strong> <?php echo count($results['vehicle_licenses']['errors']); ?></p>
                                    
                                    <?php if (!empty($results['vehicle_licenses']['errors'])): ?>
                                        <div class="alert alert-warning">
                                            <h6><strong>المشاكل الموجودة:</strong></h6>
                                            <ul class="list-unstyled">
                                                <?php foreach ($results['vehicle_licenses']['errors'] as $error): ?>
                                                    <li><small><?php echo htmlspecialchars($error); ?></small></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-success">
                                            <i class="glyphicon glyphicon-ok"></i> جميع الصور موجودة ويمكن الوصول إليها
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upload Directory Information -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h5><i class="glyphicon glyphicon-folder-open"></i> معلومات مجلد الرفع</h5>
                                </div>
                                <div class="panel-body">
                                    <?php
                                    $uploadsPath = '../assests/uploads';
$uploadsExists = is_dir($uploadsPath);
$uploadsWritable = is_writable($uploadsPath);
                                    ?>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="alert <?php echo $uploadsExists ? 'alert-success' : 'alert-danger'; ?>">
                                                <i class="glyphicon glyphicon-<?php echo $uploadsExists ? 'ok' : 'remove'; ?>"></i>
                                                <strong>مجلد uploads:</strong><br>
                                                <?php echo $uploadsExists ? 'موجود' : 'غير موجود'; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="alert <?php echo $uploadsWritable ? 'alert-success' : 'alert-warning'; ?>">
                                                <i class="glyphicon glyphicon-<?php echo $uploadsWritable ? 'ok' : 'exclamation-sign'; ?>"></i>
                                                <strong>صلاحيات الكتابة:</strong><br>
                                                <?php echo $uploadsWritable ? 'متاحة' : 'غير متاحة'; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <?php
                                            $htaccessExists = file_exists($uploadsPath . '/.htaccess');
                                            ?>
                                            <div class="alert <?php echo $htaccessExists ? 'alert-success' : 'alert-info'; ?>">
                                                <i class="glyphicon glyphicon-<?php echo $htaccessExists ? 'ok' : 'info-sign'; ?>"></i>
                                                <strong>ملف .htaccess:</strong><br>
                                                <?php echo $htaccessExists ? 'موجود' : 'غير موجود'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Subdirectory Information -->
                                    <h6><strong>المجلدات الفرعية:</strong></h6>
                                    <div class="row">
                                        <?php
                                        $subDirs = ['personal_licenses', 'vehicle_licenses', 'users', 'licenses'];
                                        foreach ($subDirs as $dir):
                                            $dirPath = $uploadsPath . '/' . $dir;
                                            $dirExists = is_dir($dirPath);
                                        ?>
                                        <div class="col-md-3">
                                            <span class="label label-<?php echo $dirExists ? 'success' : 'danger'; ?>">
                                                <?php echo $dir; ?>: <?php echo $dirExists ? 'موجود' : 'غير موجود'; ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <a href="../dashboard.php" class="btn btn-default">
                                <i class="glyphicon glyphicon-arrow-left"></i> العودة للوحة التحكم
                            </a>
                            <button onclick="window.location.reload()" class="btn btn-primary">
                                <i class="glyphicon glyphicon-refresh"></i> إعادة الفحص
                            </button>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.alert {
    margin-bottom: 10px;
}
.label {
    display: block;
    padding: 8px;
    margin-bottom: 5px;
}
</style>

<?php include '../includes/footer.php'; ?> 