<?php
$pageTitle = 'إضافة ترخيص جديد';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Improved permission logic - specific permissions take priority
$hasVehicleAdd = hasPermission('vehicle_licenses_add');
$hasPersonalAdd = hasPermission('personal_licenses_add');
$hasGeneralAdd = hasPermission('licenses_add');

// If user has specific permissions, use those ONLY
// If user has general permission only, use it for both
if ($hasVehicleAdd || $hasPersonalAdd) {
    // User has specific permissions - be strict
    $canAddVehicle = $hasVehicleAdd;
    $canAddPersonal = $hasPersonalAdd;
} else {
    // User only has general permissions - apply to both
    $canAddVehicle = $hasGeneralAdd;
    $canAddPersonal = $hasGeneralAdd;
}

// If user has no add permissions at all, redirect
if (!$canAddPersonal && !$canAddVehicle) {
    header('Location: dashboard.php');
    setMessage('غير مصرح لك بإضافة تراخيص', 'danger');
    exit;
}

// Determine license type based on permissions and URL parameter
$license_type = $_GET['type'] ?? 'personal';
if (!in_array($license_type, ['personal', 'vehicle'])) {
    $license_type = 'personal';
}

// If requested type is not allowed, redirect to allowed type
if ($license_type === 'personal' && !$canAddPersonal) {
    if ($canAddVehicle) {
        header('Location: add_license.php?type=vehicle');
        exit;
    } else {
        header('Location: dashboard.php');
        setMessage('غير مصرح لك بإضافة رخص شخصية', 'danger');
        exit;
    }
}

if ($license_type === 'vehicle' && !$canAddVehicle) {
    if ($canAddPersonal) {
        header('Location: add_license.php?type=personal');
        exit;
    } else {
        header('Location: dashboard.php');
        setMessage('غير مصرح لك بإضافة رخص مركبات', 'danger');
        exit;
    }
}

include 'includes/header.php';
?>

<div class="container content-wrapper">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4>
                        <i class="glyphicon glyphicon-plus"></i> إضافة ترخيص جديد
                    </h4>
                </div>
                <div class="panel-body">
                    
                    <!-- License Type Selection -->
                    <?php if ($canAddPersonal && $canAddVehicle): ?>
                    <div class="form-group">
                        <label class="control-label">نوع الترخيص <span class="text-danger">*</span></label>
                        <div class="license-type-selection" style="margin-top: 10px;">
                            <div class="row">
                                <?php if ($canAddPersonal): ?>
                                <div class="<?php echo ($canAddPersonal && $canAddVehicle) ? 'col-md-6' : 'col-md-12'; ?>">
                                    <div class="license-type-card <?php echo $license_type === 'personal' ? 'active' : ''; ?>" 
                                         onclick="selectLicenseType('personal')">
                                        <div class="card-icon">
                                            <i class="glyphicon glyphicon-user" style="font-size: 30px; color: #337ab7;"></i>
                                        </div>
                                        <div class="card-title">رخصة قيادة شخصية</div>
                                        <div class="card-description">رخص القيادة للأفراد</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($canAddVehicle): ?>
                                <div class="<?php echo ($canAddPersonal && $canAddVehicle) ? 'col-md-6' : 'col-md-12'; ?>">
                                    <div class="license-type-card <?php echo $license_type === 'vehicle' ? 'active' : ''; ?>" 
                                         onclick="selectLicenseType('vehicle')">
                                        <div class="card-icon">
                                            <i class="glyphicon glyphicon-road" style="font-size: 30px; color: #5cb85c;"></i>
                                        </div>
                                        <div class="card-title">رخصة مركبة</div>
                                        <div class="card-description">رخص للمركبات والعربيات</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Personal License Form -->
                    <?php if ($canAddPersonal): ?>
                    <div id="personalLicenseForm" style="display: <?php echo $license_type === 'personal' ? 'block' : 'none'; ?>;">
                        <h5 style="color: #337ab7; margin-bottom: 20px;">
                            <i class="glyphicon glyphicon-user"></i> بيانات رخصة القيادة الشخصية
                        </h5>
                        
                        <form id="addPersonalLicenseForm" enctype="multipart/form-data">
                            <input type="hidden" name="license_type" value="personal">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="personal_name" class="control-label">اسم صاحب الترخيص <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="personal_name" name="name" placeholder="اسم صاحب الترخيص" tabindex="1" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="personal_license_number" class="control-label">رقم الرخصة <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="personal_license_number" name="license_number" placeholder="رقم رخصة القيادة" tabindex="2" required>
                                        <small class="help-block">أدخل رقم رخصة القيادة الشخصية</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="personal_department_id" class="control-label">القسم <span class="text-danger">*</span></label>
                                        <select class="form-control" id="personal_department_id" name="department_id" tabindex="3" required>
                                            <option value="">اختر القسم</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="personal_project_id" class="control-label">المشروع <span class="text-danger">*</span></label>
                                        <select class="form-control" id="personal_project_id" name="project_id" tabindex="4" required>
                                            <option value="">اختر المشروع</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="personal_expiration_date" class="control-label">تاريخ الانتهاء <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="personal_expiration_date" name="expiration_date" tabindex="5" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="personal_issue_date" class="control-label">تاريخ الإصدار <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="personal_issue_date" name="issue_date" tabindex="6" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="personal_back_image" class="control-label">صورة الوجه الخلفي <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control image-upload" id="personal_back_image" name="back_image" accept=".jpg,.jpeg,.png" tabindex="9" required onchange="previewImage(this, 'personal_back_preview')">
                                        <small class="help-block">يُسمح بالصور فقط (JPG, PNG) - حد أقصى 5MB</small>
                                        <div class="image-preview-container" style="margin-top: 10px;">
                                            <img id="personal_back_preview" src="" alt="معاينة صورة الوجه الخلفي" class="img-thumbnail" style="max-width: 200px; max-height: 150px; display: none;">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="personal_front_image" class="control-label">صورة الوجه الأمامي <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control image-upload" id="personal_front_image" name="front_image" accept=".jpg,.jpeg,.png" tabindex="8" required onchange="previewImage(this, 'personal_front_preview')">
                                        <small class="help-block">يُسمح بالصور فقط (JPG, PNG) - حد أقصى 5MB</small>
                                        <div class="image-preview-container" style="margin-top: 10px;">
                                            <img id="personal_front_preview" src="" alt="معاينة صورة الوجه الأمامي" class="img-thumbnail" style="max-width: 200px; max-height: 150px; display: none;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="personal_notes" class="control-label">ملاحظات</label>
                                <textarea class="form-control" id="personal_notes" name="notes" rows="3" placeholder="ملاحظات إضافية (اختياري)" tabindex="10"></textarea>
                            </div>

                            <div class="form-group text-center">
                                <button type="submit" class="btn btn-primary btn-lg" tabindex="11">
                                    <i class="glyphicon glyphicon-plus"></i> إضافة رخصة القيادة
                                </button>
                                <a href="licenses.php" class="btn btn-default btn-lg" tabindex="12">
                                    <i class="glyphicon glyphicon-arrow-right"></i> إلغاء
                                </a>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Vehicle License Form -->
                    <?php if ($canAddVehicle): ?>
                    <div id="vehicleLicenseForm" style="display: <?php echo $license_type === 'vehicle' ? 'block' : 'none'; ?>;">
                        <h5 style="color: #5cb85c; margin-bottom: 20px;">
                            <i class="glyphicon glyphicon-road"></i> بيانات رخصة المركبة
                        </h5>
                        
                        <form id="addVehicleLicenseForm" enctype="multipart/form-data">
                            <input type="hidden" name="license_type" value="vehicle">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <!-- Car Number Section -->
                            <div class="panel panel-info">
                                <div class="panel-heading">
                                    <h6><i class="glyphicon glyphicon-certificate"></i> رقم المركبة</h6>
                                </div>
                                <div class="panel-body">
                                    <div class="form-group">
                                        <label class="control-label">رقم المركبة <span class="text-danger">*</span></label>
                                        <small class="help-block">يجب أن يحتوي على 3-4 أرقام و 2-3 حروف عربية</small>
                                        
                                        <div class="car-number-input" style="margin-top: 10px;">
                                            <div class="row">
                                                
                                                <div class="col-md-6">
                                                    <div class="input-group">
                                                        <span class="input-group-addon">الأرقام</span>
                                                        <input type="text" class="form-control text-center" id="car_numbers" 
                                                               name="car_numbers" placeholder="123" pattern="[0-9]{3,4}" 
                                                               maxlength="4" style="font-size: 18px; font-weight: bold;" tabindex="2" required>
                                                    </div>
                                                    <small class="help-block">3-4 أرقام</small>
                                                </div>
												<div class="col-md-6">
                                                    <div class="input-group">
                                                        <span class="input-group-addon">الحروف</span>
                                                        <input type="text" class="form-control text-center" id="car_letters" 
                                                               name="car_letters" placeholder="أ ب ج" 
                                                               maxlength="7" style="font-size: 18px; font-weight: bold;" tabindex="1" required>
                                                    </div>
                                                    <small class="help-block">2-3 حروف عربية</small>
                                                </div>
                                            </div>
                                            
                                            <div class="car-number-preview" style="margin-top: 15px; text-align: center;">
                                                <label>معاينة رقم المركبة:</label>
                                                <div id="carNumberPreview" style="font-size: 24px; font-weight: bold; color: #2c3e50; border: 2px solid #3498db; padding: 10px; border-radius: 5px; background: #ecf0f1;">
                                                    --- ---
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="car_number_combined" name="car_number">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vehicle_department_id" class="control-label">القسم <span class="text-danger">*</span></label>
                                        <select class="form-control" id="vehicle_department_id" name="department_id" tabindex="4" required>
                                            <option value="">اختر القسم</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vehicle_project_id" class="control-label">المشروع <span class="text-danger">*</span></label>
                                        <select class="form-control" id="vehicle_project_id" name="project_id" tabindex="3" required>
                                            <option value="">اختر المشروع</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vehicle_type" class="control-label">نوع المركبة <span class="text-danger">*</span></label>
                                        <select class="form-control" id="vehicle_type" name="vehicle_type" tabindex="5" required>
                                            <option value="">اختر نوع المركبة</option>
                                            <option value="موتوسيكل">موتوسيكل</option>
                                            <option value="عربية">عربية</option>
                                            <option value="تروسيكل">تروسيكل</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="license_category" class="control-label">فئة الرخصة</label>
                                        <select class="form-control" id="license_category" name="license_category" tabindex="6">
                                            <option value="رخصة مركبة" selected>رخصة مركبة</option>
                                            <option value="تصريح مركبة">تصريح مركبة</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row" id="inspection_year_row" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="inspection_year" class="control-label">سنة الفحص الدوري</label>
                                        <select class="form-control" id="inspection_year" name="inspection_year" tabindex="7">
                                            <option value="">اختر سنة الفحص</option>
                                            <?php 
                                            $currentYear = date('Y');
                                            for ($i = $currentYear - 5; $i <= $currentYear + 10; $i++) {
                                                echo "<option value='$i'>$i</option>";
                                            }
                                            ?>
                                        </select>
                                        <small class="help-block text-info">يظهر فقط للتصاريح - اختياري</small>
                                    </div>
                                </div>
                            </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                        <label for="vehicle_expiration_date" class="control-label">تاريخ الانتهاء <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="vehicle_expiration_date" name="expiration_date" tabindex="8" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vehicle_issue_date" class="control-label">تاريخ الإصدار <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="vehicle_issue_date" name="issue_date" tabindex="7" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vehicle_back_image" class="control-label">صورة الوجه الخلفي <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control image-upload" id="vehicle_back_image" name="back_image" accept=".jpg,.jpeg,.png" tabindex="10" required onchange="previewImage(this, 'vehicle_back_preview')">
                                        <small class="help-block">يُسمح بالصور فقط (JPG, PNG) - حد أقصى 5MB</small>
                                        <div class="image-preview-container" style="margin-top: 10px;">
                                            <img id="vehicle_back_preview" src="" alt="معاينة صورة الوجه الخلفي" class="img-thumbnail" style="max-width: 200px; max-height: 150px; display: none;">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vehicle_front_image" class="control-label">صورة الوجه الأمامي <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control image-upload" id="vehicle_front_image" name="front_image" accept=".jpg,.jpeg,.png" tabindex="9" required onchange="previewImage(this, 'vehicle_front_preview')">
                                        <small class="help-block">يُسمح بالصور فقط (JPG, PNG) - حد أقصى 5MB</small>
                                        <div class="image-preview-container" style="margin-top: 10px;">
                                            <img id="vehicle_front_preview" src="" alt="معاينة صورة الوجه الأمامي" class="img-thumbnail" style="max-width: 200px; max-height: 150px; display: none;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                        <div class="form-group">
                                <label for="vehicle_notes" class="control-label">ملاحظات</label>
                                <textarea class="form-control" id="vehicle_notes" name="notes" rows="3" placeholder="ملاحظات إضافية (اختياري)" tabindex="11"></textarea>
                        </div>
                        
                        <div class="form-group text-center">
                                <button type="submit" class="btn btn-success btn-lg" tabindex="12">
                                    <i class="glyphicon glyphicon-plus"></i> إضافة رخصة المركبة
                                </button>
                                <a href="licenses.php" class="btn btn-default btn-lg" tabindex="13">
                                    <i class="glyphicon glyphicon-arrow-right"></i> إلغاء
                                </a>
                        </div>
                    </form>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Container -->
<div id="alertContainer"></div>

<style>
.license-type-card {
    border: 2px solid #e5e5e5;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    margin-bottom: 15px;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.license-type-card:hover {
    border-color: #337ab7;
    box-shadow: 0 2px 10px rgba(51, 122, 183, 0.2);
    transform: translateY(-2px);
}

.license-type-card.active {
    border-color: #337ab7;
    background: #f8f9ff;
    box-shadow: 0 4px 15px rgba(51, 122, 183, 0.3);
}

.license-type-card .card-title {
    font-size: 16px;
    font-weight: bold;
    margin: 10px 0 5px 0;
    color: #2c3e50;
}

.license-type-card .card-description {
    font-size: 12px;
    color: #7f8c8d;
}

.car-number-input .input-group-addon {
    background: #337ab7;
    color: white;
    border-color: #337ab7;
    font-weight: bold;
}

.car-number-input input {
    direction: ltr;
    text-align: center;
}

#carNumberPreview {
    direction: ltr;
    font-family: 'Arial', sans-serif;
}

.image-preview-container {
    text-align: center;
}

.image-preview-container img {
    border: 2px solid #ddd;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.image-preview-container img:hover {
    border-color: #337ab7;
    transform: scale(1.05);
}
</style>

<script>
$(document).ready(function() {
    // Show loading indicators
    $('#personal_project_id, #vehicle_project_id').html('<option value="">جاري التحميل...</option>');
    $('#personal_department_id, #vehicle_department_id').html('<option value="">جاري التحميل...</option>');
    
    // Load initial data
    loadProjects();
    loadDepartments();
    
    // Handle license category change for vehicle licenses
    $('#license_category').on('change', function() {
        const selectedCategory = $(this).val();
        console.log('🔄 License category changed to:', selectedCategory);
        
        // Show inspection year for both vehicle license types
        if (selectedCategory === 'تصريح مركبة' || selectedCategory === 'رخصة مركبة') {
            $('#inspection_year_row').slideDown(300);
            console.log('✅ Showing inspection year field');
        } else {
            $('#inspection_year_row').slideUp(300);
            $('#inspection_year').val(''); // Clear the value
            console.log('❌ Hiding inspection year field');
        }
    });
    
    // Initialize inspection year visibility on page load
    const initialCategory = $('#license_category').val();
    if (initialCategory === 'تصريح مركبة' || initialCategory === 'رخصة مركبة') {
        $('#inspection_year_row').show();
    } else {
        $('#inspection_year_row').hide();
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
    
    // License number formatting for personal licenses
    $('#personal_license_number').on('input', function() {
        let value = $(this).val().trim().toUpperCase();
        $(this).val(value);
    });
    
    // Initialize preview on page load
    if ($('#car_numbers').length && $('#car_letters').length) {
        updateCarNumberPreview();
    }
    
    // Form submissions
    $('#addPersonalLicenseForm').on('submit', function(e) {
        e.preventDefault();
        submitLicenseForm('personal');
    });
    
    $('#addVehicleLicenseForm').on('submit', function(e) {
        e.preventDefault();
        submitLicenseForm('vehicle');
    });
});

function selectLicenseType(type) {
    $('.license-type-card').removeClass('active');
    $(`.license-type-card:contains('${type === 'personal' ? 'شخصية' : 'مركبة'}')`).addClass('active');
    
    if (type === 'personal') {
        $('#personalLicenseForm').show();
        $('#vehicleLicenseForm').hide();
    } else {
        $('#personalLicenseForm').hide();
        $('#vehicleLicenseForm').show();
    }
    
    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('type', type);
    window.history.pushState({}, '', url);
}

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
    
    // Real-time validation feedback
    const previewElement = $('#carNumberPreview');
    if (letters && numbers) {
        // Validate letters (should be 2-3 Arabic letters with spaces)
        const lettersWithoutSpaces = letters.replace(/\s/g, '');
        const arabicPattern = /^[\u0600-\u06FF]+$/u;
        const numbersPattern = /^[0-9]{3,4}$/;
        
        const isValidLetters = lettersWithoutSpaces.length >= 2 && 
                              lettersWithoutSpaces.length <= 3 && 
                              arabicPattern.test(lettersWithoutSpaces);
        const isValidNumbers = numbersPattern.test(numbers);
        
        if (isValidLetters && isValidNumbers) {
            previewElement.css({
                'border-color': '#27ae60',
                'background': '#d4edda',
                'color': '#155724'
            });
        } else {
            previewElement.css({
                'border-color': '#e74c3c',
                'background': '#f8d7da',
                'color': '#721c24'
            });
        }
    } else {
        previewElement.css({
            'border-color': '#3498db',
            'background': '#ecf0f1',
            'color': '#2c3e50'
        });
    }
}

function submitLicenseForm(type) {
    const formId = type === 'personal' ? '#addPersonalLicenseForm' : '#addVehicleLicenseForm';
    const form = $(formId);
    const submitBtn = form.find('button[type="submit"]');
    const originalText = submitBtn.html();
    
    // Validate form
    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return;
    }
    
    // Additional validation for dates
    const issueDate = form.find('input[name="issue_date"]').val();
    const expirationDate = form.find('input[name="expiration_date"]').val();
    
    if (issueDate && expirationDate && new Date(issueDate) >= new Date(expirationDate)) {
        showAlert('danger', 'تاريخ الإصدار يجب أن يكون قبل تاريخ الانتهاء');
        return;
    }
    
    // Check if images are selected
    const frontImage = form.find('input[name="front_image"]')[0];
    const backImage = form.find('input[name="back_image"]')[0];
    
    if (!frontImage.files.length) {
        showAlert('danger', 'يرجى اختيار صورة الوجه الأمامي');
        return;
    }
    
    if (!backImage.files.length) {
        showAlert('danger', 'يرجى اختيار صورة الوجه الخلفي');
        return;
    }
    
    // Validate file sizes (5MB = 5 * 1024 * 1024 bytes)
    const maxSize = 5 * 1024 * 1024;
    
    if (frontImage.files[0].size > maxSize) {
        showAlert('danger', 'حجم صورة الوجه الأمامي يجب أن يكون أقل من 5 ميجابايت');
        return;
    }
    
    if (backImage.files[0].size > maxSize) {
        showAlert('danger', 'حجم صورة الوجه الخلفي يجب أن يكون أقل من 5 ميجابايت');
        return;
    }
    
    // Additional validation for personal license
    if (type === 'personal') {
        const licenseNumber = form.find('input[name="license_number"]').val().trim();
        
        if (!licenseNumber) {
            showAlert('danger', 'يرجى إدخال رقم الرخصة');
            return;
        }
        
        // Validate license number format (at least 3 characters)
        if (licenseNumber.length < 3) {
            showAlert('danger', 'رقم الرخصة يجب أن يحتوي على 3 أحرف على الأقل');
            return;
        }
    }
    
    // Additional validation for vehicle license
    if (type === 'vehicle') {
        const carLetters = form.find('input[name="car_letters"]').val();
        const carNumbers = form.find('input[name="car_numbers"]').val();
        const lettersWithoutSpaces = carLetters ? carLetters.replace(/\s/g, '') : '';
        
        console.log('Vehicle validation - Numbers:', carNumbers, 'Letters:', carLetters);
        
        if (!carNumbers || carNumbers.length < 3 || carNumbers.length > 4) {
            console.log('Number validation failed');
            showAlert('danger', 'يجب أن تحتوي الأرقام على 3-4 أرقام');
            return;
        }
        
        if (!lettersWithoutSpaces || lettersWithoutSpaces.length < 2 || lettersWithoutSpaces.length > 3) {
            console.log('Letters validation failed');
            showAlert('danger', 'يجب أن تحتوي الحروف على 2-3 أحرف عربية');
            return;
        }
        
        // Check if it's valid Arabic letters
        const arabicPattern = /^[\u0600-\u06FF]+$/u;
        if (!arabicPattern.test(lettersWithoutSpaces)) {
            console.log('Arabic letters validation failed');
            showAlert('danger', 'يجب استخدام حروف عربية فقط');
            return;
        }
    }
    
    // Show loading state
    submitBtn.html('<i class="glyphicon glyphicon-refresh glyphicon-spin"></i> جاري الإضافة...').prop('disabled', true);
    
    // Create FormData object
    const formData = new FormData(form[0]);
    
    $.ajax({
        url: 'php_action/add_license.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        timeout: 30000,
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message || 'تم إضافة الترخيص بنجاح');
                
                // Redirect to licenses.php after a short delay
                setTimeout(function() {
                    window.location.href = 'licenses.php';
                }, 2000);
            } else {
                showAlert('danger', response.message || 'حدث خطأ أثناء إضافة الترخيص');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error Details:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                readyState: xhr.readyState,
                statusCode: xhr.status
            });
            
            let errorMessage = 'حدث خطأ في الاتصال بالخادم';
            
            if (xhr.status === 413) {
                errorMessage = 'حجم الملفات كبير جداً. يرجى اختيار صور أصغر حجماً';
            } else if (xhr.status === 403) {
                errorMessage = 'ليس لديك صلاحية لتنفيذ هذا الإجراء';
            } else if (xhr.status === 500) {
                errorMessage = 'خطأ في الخادم. يرجى المحاولة مرة أخرى';
            } else if (status === 'timeout') {
                errorMessage = 'انتهت مهلة الاتصال. يرجى المحاولة مرة أخرى';
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    console.error('Could not parse error response:', xhr.responseText);
                }
            }
            
            showAlert('danger', errorMessage);
        },
        complete: function() {
            submitBtn.html(originalText).prop('disabled', false);
        }
    });
}

function loadProjects() {
    $.get('php_action/get_projects.php', function(response) {
        if (response.success) {
            let options = '<option value="">اختر المشروع</option>';
            response.data.forEach(function(project) {
                options += `<option value="${project.project_id}">${project.project_name}</option>`;
            });
            $('#personal_project_id, #vehicle_project_id').html(options);
        }
    }).fail(function() {
        $('#personal_project_id, #vehicle_project_id').html('<option value="">خطأ في التحميل - يرجى إعادة تحميل الصفحة</option>');
        showAlert('warning', 'خطأ في تحميل المشاريع');
    });
}

function loadDepartments() {
    $.get('php_action/get_unique_departments_updated.php', function(response) {
        if (response.success) {
            let options = '<option value="">اختر القسم</option>';
            response.data.forEach(function(department) {
                options += `<option value="${department.department_id}">${department.department_name}</option>`;
            });
            $('#personal_department_id, #vehicle_department_id').html(options);
        }
    }).fail(function() {
        $('#personal_department_id, #vehicle_department_id').html('<option value="">خطأ في التحميل - يرجى إعادة تحميل الصفحة</option>');
        showAlert('warning', 'خطأ في تحميل الأقسام');
    });
}

function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 
                     type === 'warning' ? 'alert-warning' : 'alert-danger';
    
        const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <button type="button" class="close" data-dismiss="alert" aria-label="إغلاق">
                <span aria-hidden="true">&times;</span>
            </button>
                ${message}
            </div>
        `;
    
    $('#alertContainer').append(alertHtml);
        
    // Auto-remove after 5 seconds
            setTimeout(function() {
        $('.alert').fadeOut(function() {
            $(this).remove();
        });
            }, 5000);
        }

// دالة معاينة الصور
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
        preview.src = '';
    }
}
</script>

<?php include 'includes/footer.php'; ?> 
