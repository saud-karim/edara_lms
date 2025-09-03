<?php
$pageTitle = 'تعديل الترخيص';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Require login and edit permissions
requireLogin();

// Check if user has any license edit permission
$canEditLicenses = hasPermission('licenses_edit') || 
                   hasPermission('personal_licenses_edit') || 
                   hasPermission('vehicle_licenses_edit') ||
                   getUserRole() === 'super_admin';

if (!$canEditLicenses) {
    setMessage('غير مصرح لك بتعديل التراخيص', 'danger');
    header('Location: dashboard.php');
    exit;
}

$userRole = getUserRole();
$userDepartment = getUserDepartment();

// Get license ID and type from URL
$licenseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$licenseType = isset($_GET['type']) ? $_GET['type'] : 'personal'; // default to personal

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
    if ($licenseType === 'personal') {
        $tableName = 'personal_licenses';
        $query = "
            SELECT l.*, p.project_name, d.department_name
            FROM personal_licenses l
            LEFT JOIN projects p ON l.project_id = p.project_id
            LEFT JOIN departments d ON l.department_id = d.department_id
            WHERE l.license_id = ? AND l.is_active = 1
        ";
    } else {
        $tableName = 'vehicle_licenses';
        $query = "
            SELECT l.*, p.project_name, d.department_name
            FROM vehicle_licenses l
            LEFT JOIN projects p ON l.project_id = p.project_id
            LEFT JOIN departments d ON l.department_id = d.department_id
            WHERE l.license_id = ? AND l.is_active = 1
        ";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$licenseId]);
    $license = $stmt->fetch();
    
    if (!$license) {
        setMessage('الترخيص غير موجود', 'danger');
        header('Location: licenses.php');
        exit;
    }
    
    // Check user permissions using Admin Teams System
    if (!canModifyLicense($license['user_id'])) {
        setMessage('غير مصرح لك بتعديل هذا الترخيص', 'danger');
        header('Location: licenses.php');
        exit;
    }
    
    // Get projects based on user role and permissions (like add_license.php)
    $userRole = getUserRole();
    $currentUserId = getUserId();
    
    if ($userRole === 'super_admin') {
        // Super Admin sees all projects
        $projectsQuery = "
            SELECT project_id, project_name, project_description
            FROM projects 
            WHERE is_active = 1 
            ORDER BY project_name
        ";
        $projectsStmt = $conn->prepare($projectsQuery);
        $projectsStmt->execute();
    } elseif ($userRole === 'admin') {
        // Admin/Sub Admin sees only assigned projects
        $projectsQuery = "
            SELECT DISTINCT p.project_id, p.project_name, p.project_description
            FROM projects p 
            INNER JOIN user_projects up ON p.project_id = up.project_id
            WHERE p.is_active = 1 AND up.user_id = ?
            ORDER BY p.project_name
        ";
        $projectsStmt = $conn->prepare($projectsQuery);
        $projectsStmt->execute([$currentUserId]);
    } else {
        // Regular users see projects by department (unchanged from previous logic)
        $userDepartmentId = getUserDepartment();
        if ($userDepartmentId) {
            $projectsQuery = "
                SELECT DISTINCT p.project_id, p.project_name, p.project_description
                FROM projects p 
                LEFT JOIN users u ON p.project_id = u.project_id AND u.is_active = 1
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE p.is_active = 1 AND d.department_id = ? AND d.is_active = 1
                ORDER BY p.project_name
            ";
            $projectsStmt = $conn->prepare($projectsQuery);
            $projectsStmt->execute([$userDepartmentId]);
        } else {
            // Fallback: no projects
            $projectsStmt = $conn->prepare("SELECT project_id, project_name, project_description FROM projects WHERE 1=0");
            $projectsStmt->execute();
        }
    }
    
    $projects = $projectsStmt->fetchAll();
    
    // Debug log for troubleshooting
    error_log("Edit license - User role: $userRole, User ID: $currentUserId, Projects found: " . count($projects));
    
    // Get all departments (now independent from projects)
    $departmentsStmt = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
    $departments = $departmentsStmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Edit license page error: " . $e->getMessage());
    setMessage('حدث خطأ في تحميل بيانات الترخيص', 'danger');
    header('Location: licenses.php');
    exit;
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4>
                            <i class="glyphicon glyphicon-edit"></i>
                            تعديل <?php echo $licenseType === 'personal' ? 'رخصة القيادة الشخصية' : 'رخصة المركبة'; ?>
                        </h4>
                    </div>
                    <div class="panel-body">
                        
                        <!-- License Info -->
                        <div class="alert alert-info">
                            <strong>معلومات الترخيص:</strong>
                            <?php if ($licenseType === 'personal'): ?>
                                رقم الترخيص: <?php echo htmlspecialchars($license['license_number']); ?> |
                                الاسم: <?php echo htmlspecialchars($license['full_name']); ?>
                            <?php else: ?>
                                رقم المركبة: <?php echo htmlspecialchars($license['car_number']); ?> |
                                نوع المركبة: <?php echo htmlspecialchars($license['vehicle_type']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Edit Form -->
                        <form id="editLicenseForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="license_id" value="<?php echo $licenseId; ?>">
                            <input type="hidden" name="license_type" value="<?php echo $licenseType; ?>">
                            
                            <!-- Basic Information -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="panel panel-default">
                                        <div class="panel-heading">
                                            <h5><i class="glyphicon glyphicon-info-sign"></i> المعلومات الأساسية</h5>
                                        </div>
                                        <div class="panel-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    
                                                    <?php if ($licenseType === 'personal'): ?>
                                                        <!-- Personal License Fields - First Column (Right) -->
                                                        <div class="form-group">
                                                            <label for="project_id" class="required">المشروع</label>
                                                            <select id="project_id" name="project_id" class="form-control" tabindex="6" required>
                                                                <option value="">اختر المشروع</option>
                                                                <?php foreach ($projects as $project): ?>
                                                                    <option value="<?php echo $project['project_id']; ?>" 
                                                                            <?php echo $license['project_id'] == $project['project_id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="department_id" class="required">القسم</label>
                                                            <select id="department_id" name="department_id" class="form-control" tabindex="7" required>
                                                                <option value="">اختر القسم</option>
                                                                <?php foreach ($departments as $department): ?>
                                                                    <option value="<?php echo $department['department_id']; ?>" 
                                                                            <?php echo $license['department_id'] == $department['department_id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($department['department_name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        
                                                    <?php else: ?>
                                                        <!-- Vehicle License Fields -->
                                                        <!-- Right Column (Previously Left) -->
                                                        <div class="form-group">
                                                            <label for="vehicle_type" class="required">نوع المركبة</label>
                                                            <select id="vehicle_type" name="vehicle_type" class="form-control" tabindex="1" required>
                                                                <option value="">اختر نوع المركبة</option>
                                                                <option value="موتوسيكل" <?php echo $license['vehicle_type'] === 'موتوسيكل' ? 'selected' : ''; ?>>موتوسيكل</option>
                                                                <option value="عربية" <?php echo $license['vehicle_type'] === 'عربية' ? 'selected' : ''; ?>>عربية</option>
                                                                <option value="تروسيكل" <?php echo $license['vehicle_type'] === 'تروسيكل' ? 'selected' : ''; ?>>تروسيكل</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="license_category" class="required">فئة الرخصة</label>
                                                            <select id="license_category" name="license_category" class="form-control" tabindex="6" required>
                                                                <option value="">اختر فئة الرخصة</option>
                                                                <option value="رخصة مركبة" <?php echo ($license['license_category'] ?? 'رخصة مركبة') === 'رخصة مركبة' ? 'selected' : ''; ?>>رخصة مركبة</option>
                                                                <option value="تصريح مركبة" <?php echo ($license['license_category'] ?? '') === 'تصريح مركبة' ? 'selected' : ''; ?>>تصريح مركبة</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="form-group" id="inspection_year_group">
                                                            <label for="inspection_year">سنة الفحص</label>
                                                            <input type="number" id="inspection_year" name="inspection_year" class="form-control" 
                                                                   placeholder="مثال: 2024" min="2020" max="2030" tabindex="7"
                                                                   value="<?php echo htmlspecialchars($license['inspection_year'] ?? ''); ?>">
                                                            <small class="help-block text-info">يظهر فقط للتصاريح - اختياري</small>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="issue_date" class="required">تاريخ الإصدار</label>
                                                            <input type="date" id="issue_date" name="issue_date" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($license['issue_date']); ?>" tabindex="8" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="expiration_date" class="required">تاريخ الانتهاء</label>
                                                            <input type="date" id="expiration_date" name="expiration_date" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($license['expiration_date']); ?>" tabindex="9" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="notes">ملاحظات</label>
                                                            <textarea id="notes" name="notes" class="form-control" rows="4" 
                                                                      placeholder="أي ملاحظات إضافية..." tabindex="10"><?php echo htmlspecialchars($license['notes'] ?? ''); ?></textarea>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <!-- Second Column -->
                                                    <?php if ($licenseType === 'personal'): ?>
                                                        <!-- Personal License Second Column (Left) -->
                                                        <div class="form-group">
                                                            <label for="license_number" class="required">رقم الترخيص</label>
                                                            <input type="text" id="license_number" name="license_number" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($license['license_number']); ?>" tabindex="1" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="full_name" class="required">الاسم الكامل</label>
                                                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($license['full_name']); ?>" tabindex="2" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="issue_date" class="required">تاريخ الإصدار</label>
                                                            <input type="date" id="issue_date" name="issue_date" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($license['issue_date']); ?>" tabindex="3" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="expiration_date" class="required">تاريخ الانتهاء</label>
                                                            <input type="date" id="expiration_date" name="expiration_date" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($license['expiration_date']); ?>" tabindex="4" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="notes">ملاحظات</label>
                                                            <textarea id="notes" name="notes" class="form-control" rows="4" 
                                                                      placeholder="أي ملاحظات إضافية..." tabindex="5"><?php echo htmlspecialchars($license['notes'] ?? ''); ?></textarea>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Vehicle License Left Column (Previously Right) -->
                                                        <div class="form-group">
                                                            <label for="car_number" class="required">رقم المركبة</label>
                                                            <?php 
                                                            // Split existing car number - handles both formats: "letters numbers" and "numbers letters"
                                                            $carNumber = $license['car_number'] ?? '';
                                                            $carNumbers = '';
                                                            $carLetters = '';
                                                            if ($carNumber) {
                                                                // Check format: "letters numbers" (like "ا ت ل 5648")
                                                                if (preg_match('/^([\x{0600}-\x{06FF}\s]{3,7})\s+([0-9]{3,4})$/u', $carNumber, $matches)) {
                                                                    $carLetters = trim($matches[1]);
                                                                    $carNumbers = $matches[2];
                                                                } 
                                                                // Check format: "numbers letters" (like "5648 ا ت ل")
                                                                elseif (preg_match('/^([0-9]{3,4})\s+([\x{0600}-\x{06FF}\s]{3,7})$/u', $carNumber, $matches)) {
                                                                    $carNumbers = $matches[1];
                                                                    $carLetters = trim($matches[2]);
                                                                }
                                                                // Fallback for old format without spaces - letters then numbers
                                                                elseif (preg_match('/^([\x{0600}-\x{06FF}]{2,3})([0-9]{3,4})$/u', $carNumber, $matches)) {
                                                                    $letters = $matches[1];
                                                                    $carNumbers = $matches[2];
                                                                    // Add spaces between letters for display
                                                                    $spacedLetters = '';
                                                                    for ($i = 0; $i < mb_strlen($letters); $i++) {
                                                                        $spacedLetters .= mb_substr($letters, $i, 1);
                                                                        if ($i < mb_strlen($letters) - 1) {
                                                                            $spacedLetters .= ' ';
                                                                        }
                                                                    }
                                                                    $carLetters = $spacedLetters;
                                                                } 
                                                                // Fallback for old format without spaces - numbers then letters
                                                                elseif (preg_match('/^([0-9]{3,4})([\x{0600}-\x{06FF}]{2,3})$/u', $carNumber, $matches)) {
                                                                    $carNumbers = $matches[1];
                                                                    $letters = $matches[2];
                                                                    // Add spaces between letters for display
                                                                    $spacedLetters = '';
                                                                    for ($i = 0; $i < mb_strlen($letters); $i++) {
                                                                        $spacedLetters .= mb_substr($letters, $i, 1);
                                                                        if ($i < mb_strlen($letters) - 1) {
                                                                            $spacedLetters .= ' ';
                                                                        }
                                                                    }
                                                                    $carLetters = $spacedLetters;
                                                                }
                                                            }
                                                            ?>
                                                            <div class="car-number-input" style="margin-top: 10px;">
                                                                <div class="row">
                                                                    
                                                                    <div class="col-md-6">
                                                                        <div class="input-group">
                                                                            <span class="input-group-addon">الأرقام</span>
                                                                            <input type="text" class="form-control text-center" id="car_numbers" 
                                                                                   name="car_numbers" placeholder="123" pattern="[0-9]{3,4}" 
                                                                                   maxlength="4" style="font-size: 18px; font-weight: bold;" 
                                                                                   value="<?php echo htmlspecialchars($carNumbers); ?>" tabindex="3" required>
                                                                        </div>
                                                                        <small class="help-block">3-4 أرقام</small>
                                                                    </div>
																	<div class="col-md-6">
                                                                        <div class="input-group">
                                                                            <span class="input-group-addon">الحروف</span>
                                                                            <input type="text" class="form-control text-center" id="car_letters" 
                                                                                   name="car_letters" placeholder="أ ب ج" 
                                                                                   maxlength="7" style="font-size: 18px; font-weight: bold;" 
                                                                                   value="<?php echo htmlspecialchars($carLetters); ?>" tabindex="2" required>
                                                                        </div>
                                                                        <small class="help-block">2-3 حروف عربية</small>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="car-number-preview" style="margin-top: 15px; text-align: center;">
                                                                    <label>معاينة رقم المركبة:</label>
                                                                    <div id="carNumberPreview" style="font-size: 24px; font-weight: bold; color: #2c3e50; border: 2px solid #3498db; padding: 10px; border-radius: 5px; background: #ecf0f1;">
                                                                    <?php echo htmlspecialchars($carNumber ?? '--- ---'); ?>
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Hidden field to store combined car number WITH spaces -->
                                                                <input type="hidden" id="car_number_combined" name="car_number" value="<?php echo htmlspecialchars($carNumber); ?>">
                                                            </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="project_id" class="required">المشروع</label>
                                                        <select id="project_id" name="project_id" class="form-control" tabindex="4" required>
                                                            <option value="">اختر المشروع</option>
                                                            <?php foreach ($projects as $project): ?>
                                                                <option value="<?php echo $project['project_id']; ?>" 
                                                                        <?php echo $license['project_id'] == $project['project_id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($project['project_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="department_id" class="required">القسم</label>
                                                        <select id="department_id" name="department_id" class="form-control" tabindex="5" required>
                                                            <option value="">اختر القسم</option>
                                                            <?php foreach ($departments as $department): ?>
                                                                <option value="<?php echo $department['department_id']; ?>" 
                                                                        <?php echo $license['department_id'] == $department['department_id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($department['department_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Images Section -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="panel panel-default">
                                        <div class="panel-heading">
                                            <h5><i class="glyphicon glyphicon-camera"></i> صور الترخيص</h5>
                                        </div>
                                        <div class="panel-body">
                                            <div class="row">
                                                <!-- Back Image (Now First) -->
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="back_image">صورة الوجه الخلفي <span class="text-danger">*</span></label>
                                                        <?php if (!empty($license['back_image_path'])): ?>
                                                            <div class="current-image">
                                                                <p><strong>الصورة الحالية:</strong></p>
                                                                <?php
                                                                // Check multiple possible paths for existing image
                                                                $backImagePath = $license['back_image_path'];
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
                                                                    <img src="<?php echo htmlspecialchars($workingPath); ?>" 
                                                                         alt="صورة الوجه الخلفي" 
                                                                         class="img-thumbnail" 
                                                                         style="max-width: 200px;"
                                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                                    <div style="display: none; padding: 10px; border: 1px dashed #ccc; text-align: center; color: #666;">
                                                                        <i class="glyphicon glyphicon-picture"></i><br>
                                                                        <small>خطأ في تحميل الصورة</small><br>
                                                                        <small class="text-muted">المسار: <?php echo htmlspecialchars($backImagePath); ?></small>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div style="padding: 20px; border: 1px dashed #ccc; text-align: center; color: #999; background: #f9f9f9;">
                                                                        <i class="glyphicon glyphicon-picture" style="font-size: 30px;"></i><br>
                                                                        <small>الصورة غير موجودة</small><br>
                                                                        <small class="text-muted">المسار: <?php echo htmlspecialchars($backImagePath); ?></small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <p class="text-muted">اختر صورة جديدة لاستبدال الحالية</p>
                                                            </div>
                                                        <?php endif; ?>
                                                        <input type="file" id="back_image" name="back_image" class="form-control" accept="image/*" tabindex="11">
                                                        <small class="help-block">أنواع الملفات المسموحة: JPG, PNG, GIF (حد أقصى: 5MB)</small>
                                                    </div>
                                                </div>
                                                
                                                <!-- Front Image (Now Second) -->
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="front_image">صورة الوجه الأمامي <span class="text-danger">*</span></label>
                                                        <?php if (!empty($license['front_image_path'])): ?>
                                                            <div class="current-image">
                                                                <p><strong>الصورة الحالية:</strong></p>
                                                                <?php
                                                                // Check multiple possible paths for existing image
                                                                $frontImagePath = $license['front_image_path'];
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
                                                                    <img src="<?php echo htmlspecialchars($workingPath); ?>" 
                                                                         alt="صورة الوجه الأمامي" 
                                                                         class="img-thumbnail" 
                                                                         style="max-width: 200px;"
                                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                                    <div style="display: none; padding: 10px; border: 1px dashed #ccc; text-align: center; color: #666;">
                                                                        <i class="glyphicon glyphicon-picture"></i><br>
                                                                        <small>خطأ في تحميل الصورة</small><br>
                                                                        <small class="text-muted">المسار: <?php echo htmlspecialchars($frontImagePath); ?></small>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div style="padding: 20px; border: 1px dashed #ccc; text-align: center; color: #999; background: #f9f9f9;">
                                                                        <i class="glyphicon glyphicon-picture" style="font-size: 30px;"></i><br>
                                                                        <small>الصورة غير موجودة</small><br>
                                                                        <small class="text-muted">المسار: <?php echo htmlspecialchars($frontImagePath); ?></small>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <p class="text-muted">اختر صورة جديدة لاستبدال الحالية</p>
                                                            </div>
                                                        <?php endif; ?>
                                                        <input type="file" id="front_image" name="front_image" class="form-control" accept="image/*" tabindex="10">
                                                        <small class="help-block">أنواع الملفات المسموحة: JPG, PNG, GIF (حد أقصى: 5MB)</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="panel panel-default">
                                        <div class="panel-body text-center">
                                            <button type="submit" class="btn btn-success btn-lg" tabindex="12">
                                                <i class="glyphicon glyphicon-floppy-disk"></i> حفظ التعديلات
                                            </button>
                                            <a href="view_license.php?id=<?php echo $licenseId; ?>&type=<?php echo $licenseType; ?>" class="btn btn-info btn-lg" tabindex="13">
                                                <i class="glyphicon glyphicon-eye-open"></i> عرض الترخيص
                                            </a>
                                            <a href="licenses.php" class="btn btn-default btn-lg" tabindex="14">
                                                <i class="glyphicon glyphicon-arrow-right"></i> العودة للقائمة
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load departments on page load
    loadDepartments();
    
    // Handle license category change for vehicle licenses
    $('#license_category').on('change', function() {
        const selectedCategory = $(this).val();
        console.log('🔄 License category changed to:', selectedCategory);
        
        // Show inspection year for both vehicle license types
        if (selectedCategory === 'تصريح مركبة' || selectedCategory === 'رخصة مركبة') {
            $('#inspection_year_group').slideDown(300);
            console.log('✅ Showing inspection year field');
        } else {
            $('#inspection_year_group').slideUp(300);
            $('#inspection_year').val(''); // Clear the value
            console.log('❌ Hiding inspection year field');
        }
    });
    
    // Initialize inspection year visibility on page load
    const initialCategory = $('#license_category').val();
    console.log('🔄 Initial license category:', initialCategory);
    if (initialCategory === 'تصريح مركبة' || initialCategory === 'رخصة مركبة') {
        $('#inspection_year_group').show();
    } else {
        $('#inspection_year_group').hide();
    }
    
    // Load departments function
    function loadDepartments() {
        $.get('php_action/get_unique_departments.php')
            .done(function(response) {
                if (response.success && response.data) {
                    let options = '<option value="">اختر القسم</option>';
                    response.data.forEach(function(dept) {
                        const selected = dept.department_id == <?php echo $license['department_id'] ?? 'null'; ?> ? 'selected' : '';
                        options += `<option value="${dept.department_id}" ${selected}>${dept.department_name}</option>`;
                    });
                    $('#department_id').html(options);
                }
            })
            .fail(function() {
                $('#department_id').html('<option value="">خطأ في تحميل الأقسام</option>');
            });
    }
    
    // Car number validation for vehicle licenses
    $('#car_numbers').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 4);
        updateCarNumberPreview();
    });
    
    $('#car_letters').on('input', function() {
        // Allow only Arabic letters and remove spaces first
        let value = this.value.replace(/[^\u0600-\u06FF]/g, '').substring(0, 3);
        
        // Add spaces between letters for display
        let displayValue = '';
        for (let i = 0; i < value.length; i++) {
            displayValue += value[i];
            if (i < value.length - 1) {
                displayValue += ' ';
            }
        }
        
        this.value = displayValue;
        updateCarNumberPreview();
    });
    
    // Update car number preview and combined field
    function updateCarNumberPreview() {
        const numbers = $('#car_numbers').val();
        const letters = $('#car_letters').val();
        
        // Display preview - letters first, then numbers
        const preview = (letters || '---') + ' ' + (numbers || '---');
        $('#carNumberPreview').text(preview);
        
        // Update combined field - letters first, then numbers (for database storage)
        if (numbers && letters) {
            $('#car_number_combined').val(letters + ' ' + numbers);
        } else {
            $('#car_number_combined').val('');
        }
    }
    
    // Initialize preview on page load
    if ($('#car_numbers').length && $('#car_letters').length) {
        updateCarNumberPreview();
    }
    
    // Form submission
    $('#editLicenseForm').submit(function(e) {
        console.log('Form submission started');
        e.preventDefault();
        
        // Additional validation for vehicle licenses
        if ($('#car_numbers').length && $('#car_letters').length) {
            console.log('Validating vehicle license');
            const numbers = $('#car_numbers').val();
            const letters = $('#car_letters').val();
            const lettersWithoutSpaces = letters.replace(/\s/g, '');
            
            console.log('Numbers:', numbers, 'Letters:', letters);
            
            if (!numbers || numbers.length < 3 || numbers.length > 4) {
                console.log('Number validation failed');
                showAlert('يجب أن تحتوي الأرقام على 3-4 أرقام', 'danger');
                return false;
            }
            
            if (!lettersWithoutSpaces || lettersWithoutSpaces.length < 2 || lettersWithoutSpaces.length > 3) {
                console.log('Letters validation failed');
                showAlert('يجب أن تحتوي الحروف على 2-3 أحرف عربية', 'danger');
                return false;
            }
            
            // Check if it's valid Arabic letters
            const arabicPattern = /^[\u0600-\u06FF]+$/u;
            if (!arabicPattern.test(lettersWithoutSpaces)) {
                console.log('Arabic letters validation failed');
                showAlert('يجب استخدام حروف عربية فقط', 'danger');
                return false;
            }
        }
        
        const formData = new FormData(this);
        console.log('FormData created, starting AJAX');
        
        // Show loading
        const submitBtn = $('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جاري الحفظ...');
        
        $.ajax({
            url: 'php_action/edit_license.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
        .done(function(response) {
            console.log('AJAX success:', response);
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    window.location.href = 'view_license.php?id=<?php echo $licenseId; ?>&type=<?php echo $licenseType; ?>';
                }, 1500);
            } else {
                console.log('Server returned error:', response.error);
                showAlert(response.error || response.message, 'danger');
            }
        })
        .fail(function(xhr) {
            console.error('AJAX failed:', xhr);
            console.error('Response text:', xhr.responseText);
            console.error('Status:', xhr.status);
            console.error('Status text:', xhr.statusText);
            showAlert('حدث خطأ في تحديث الترخيص', 'danger');
        })
        .always(function() {
            console.log('AJAX completed');
            submitBtn.prop('disabled', false).html(originalText);
        });
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

.current-image {
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #f9f9f9;
}

.current-image img {
    display: block;
    margin: 10px auto;
    max-height: 200px;
    max-width: 100%;
    border: 2px solid #ddd;
    border-radius: 5px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    cursor: pointer;
}

.current-image img:hover {
    border-color: #337ab7;
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(51, 122, 183, 0.3);
}

/* Mobile responsive styles */
@media (max-width: 768px) {
    .current-image img {
        max-height: 150px;
    }
    
    .current-image {
        margin-bottom: 10px;
        padding: 8px;
    }
}

.panel-heading h5 {
    margin: 0;
    font-weight: bold;
}

.car-number-input {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #e9ecef;
}

.car-number-preview {
    background: #ffffff;
    border-radius: 5px;
    margin-top: 10px;
}
</style>

<?php include 'includes/footer.php'; ?> 
