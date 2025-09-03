<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Debug: Log received data
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'رمز الأمان غير صحيح']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $conn = getDBConnection();
    $errors = [];
    
    $licenseType = $_POST['license_type'] ?? '';
    error_log("License type: " . $licenseType);
    
    if ($licenseType === 'personal') {
        // Handle Personal License
        $fullName = sanitizeInput($_POST['name'] ?? ''); // Use 'name' from form but store as 'full_name'
        $licenseNumber = strtoupper(trim(sanitizeInput($_POST['license_number'] ?? ''))); // New license number field - normalize format
        $issueDate = $_POST['issue_date'] ?? '';
        $expirationDate = $_POST['expiration_date'] ?? '';
        $projectId = intval($_POST['project_id'] ?? 0);
        $departmentId = intval($_POST['department_id'] ?? 0);
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        error_log("Personal license data: fullName=$fullName, licenseNumber=$licenseNumber, issueDate=$issueDate, expirationDate=$expirationDate, projectId=$projectId, departmentId=$departmentId");
        
        // Validate required fields
        if (empty($fullName)) $errors[] = 'اسم صاحب الترخيص مطلوب';
        if (empty($licenseNumber)) $errors[] = 'رقم الرخصة مطلوب';
        if (empty($issueDate)) $errors[] = 'تاريخ الإصدار مطلوب';
        if (empty($expirationDate)) $errors[] = 'تاريخ الانتهاء مطلوب';
        if (!$projectId) $errors[] = 'المشروع مطلوب';
        if (!$departmentId) $errors[] = 'القسم مطلوب';
        
        // Check for duplicate license number
        if (!empty($licenseNumber)) {
            $checkStmt = $conn->prepare("SELECT license_id FROM personal_licenses WHERE license_number = ? AND is_active = 1");
            $checkStmt->execute([$licenseNumber]);
            if ($checkStmt->fetch()) {
                $errors[] = 'رقم الرخصة موجود بالفعل في النظام';
            }
        }
        
        // Check permissions
        $hasPersonalAdd = hasPermission('personal_licenses_add');
        $hasGeneralAdd = hasPermission('licenses_add');
        
        if (!$hasPersonalAdd && !$hasGeneralAdd) {
            echo json_encode(['success' => false, 'message' => 'غير مصرح لك بإضافة رخص شخصية']);
            exit;
        }
        
        if (!empty($errors)) {
            error_log("Personal license validation errors: " . print_r($errors, true));
            echo json_encode(['success' => false, 'message' => 'فشل التحقق من البيانات', 'details' => $errors]);
            exit;
        }
        
        // Handle file uploads for personal licenses
        $frontImagePath = null;
        $backImagePath = null;
        
        if (isset($_FILES['front_image']) && $_FILES['front_image']['error'] === UPLOAD_ERR_OK) {
            $frontImagePath = uploadImageToPersonalLicenses($_FILES['front_image'], 'front');
            if (!$frontImagePath) {
                $errors[] = 'فشل في رفع صورة الوجه الأمامي';
            }
        }
        
        if (isset($_FILES['back_image']) && $_FILES['back_image']['error'] === UPLOAD_ERR_OK) {
            $backImagePath = uploadImageToPersonalLicenses($_FILES['back_image'], 'back');
            if (!$backImagePath) {
                $errors[] = 'فشل في رفع صورة الوجه الخلفي';
            }
        }
        
        // Log upload attempts for debugging
        error_log("Personal license upload attempt - Front: " . ($frontImagePath ? "Success: $frontImagePath" : "Failed/No file"));
        error_log("Personal license upload attempt - Back: " . ($backImagePath ? "Success: $backImagePath" : "Failed/No file"));
        
        // Check if at least one image was uploaded successfully
        if (!$frontImagePath && !$backImagePath) {
            $errors[] = 'يجب رفع صورة واحدة على الأقل';
        }
        
        if (!empty($errors)) {
            // Clean up uploaded files if there were errors
            if ($frontImagePath && file_exists($frontImagePath)) {
                unlink($frontImagePath);
            }
            if ($backImagePath && file_exists($backImagePath)) {
                unlink($backImagePath);
            }
            
            error_log("Personal license creation failed due to errors: " . implode(', ', $errors));
            echo json_encode(['success' => false, 'message' => 'فشل في إضافة الترخيص', 'details' => $errors]);
            exit;
        }
        
        // Get current user ID
        $currentUserId = getUserId();
        if ($currentUserId === null) {
            echo json_encode(['success' => false, 'message' => 'خطأ في تحديد هوية المستخدم. يرجى تسجيل الدخول مرة أخرى']);
            exit;
        }
        
        // Insert personal license record (using user-provided license number)
        $insertStmt = $conn->prepare("
            INSERT INTO personal_licenses (
                license_number, full_name, issue_date, expiration_date, 
                project_id, department_id, user_id, front_image_path, back_image_path, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $insertStmt->execute([
            $licenseNumber, // Use the license number provided by user
            $fullName,
            $issueDate,
            $expirationDate,
            $projectId,
            $departmentId,
            $currentUserId,
            $frontImagePath,
            $backImagePath,
            $notes
        ]);
        
        if ($result) {
            $licenseId = $conn->lastInsertId();
            
            // Get the inserted license with details
            $selectStmt = $conn->prepare("
                SELECT l.*, p.project_name, d.department_name
                FROM personal_licenses l
                JOIN projects p ON l.project_id = p.project_id
                JOIN departments d ON l.department_id = d.department_id
                WHERE l.license_id = ?
            ");
            $selectStmt->execute([$licenseId]);
            $license = $selectStmt->fetch();
            
            echo json_encode([
                'success' => true,
                'message' => 'تم إضافة رخصة القيادة الشخصية بنجاح',
                'license' => $license
            ]);
        } else {
            // Clean up uploaded files if database insert failed
            if ($frontImagePath && file_exists($frontImagePath)) {
                unlink($frontImagePath);
            }
            if ($backImagePath && file_exists($backImagePath)) {
                unlink($backImagePath);
            }
            
            echo json_encode(['success' => false, 'message' => 'فشل في إضافة الترخيص']);
        }
        
    } elseif ($licenseType === 'vehicle') {
        // Handle Vehicle License
        $carNumbers = trim($_POST['car_numbers'] ?? '');
        $carLetters = trim($_POST['car_letters'] ?? '');
        $vehicleType = sanitizeInput($_POST['vehicle_type'] ?? '');
        $licenseCategory = sanitizeInput($_POST['license_category'] ?? 'رخصة مركبة');
        $inspectionYear = intval($_POST['inspection_year'] ?? 0);
        $licenseCategory = sanitizeInput($_POST['license_category'] ?? 'رخصة مركبة');
        $inspectionYear = intval($_POST['inspection_year'] ?? 0);
        $issueDate = $_POST['issue_date'] ?? '';
        $expirationDate = $_POST['expiration_date'] ?? '';
        $projectId = intval($_POST['project_id'] ?? 0);
        $departmentId = intval($_POST['department_id'] ?? 0);
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        error_log("Vehicle license data: carNumbers=$carNumbers, carLetters=$carLetters, vehicleType=$vehicleType, issueDate=$issueDate, expirationDate=$expirationDate, projectId=$projectId, departmentId=$departmentId");
        
        // Validate required fields
        if (empty($carNumbers)) $errors[] = 'أرقام المركبة مطلوبة';
        if (empty($carLetters)) $errors[] = 'حروف المركبة مطلوبة';
        if (empty($vehicleType)) $errors[] = 'نوع المركبة مطلوب';
        if (empty($issueDate)) $errors[] = 'تاريخ الإصدار مطلوب';
        if (empty($expirationDate)) $errors[] = 'تاريخ الانتهاء مطلوب';
        if (!$projectId) $errors[] = 'المشروع مطلوب';
        if (!$departmentId) $errors[] = 'القسم مطلوب';
        
        // Remove spaces from letters for validation only
        $carLettersClean = str_replace(' ', '', $carLetters);
        
        // Combine car number parts WITH spaces for database storage
        $carNumber = $carLetters . ' ' . $carNumbers;
        
        // Validate car number parts
        if ($carNumbers && !preg_match('/^[0-9]{3,4}$/', $carNumbers)) {
            $errors[] = 'أرقام المركبة يجب أن تحتوي على 3-4 أرقام فقط';
        }
        
        // Validate letters (after removing spaces)
        if ($carLettersClean && !preg_match('/^[\x{0600}-\x{06FF}]{2,3}$/u', $carLettersClean)) {
            $errors[] = 'حروف المركبة يجب أن تحتوي على 2-3 أحرف عربية فقط';
        }
        
        // Validate vehicle type
        $validVehicleTypes = ['موتوسيكل', 'عربية', 'تروسيكل'];
        if (!in_array($vehicleType, $validVehicleTypes)) {
            $errors[] = 'نوع المركبة غير صالح';
        }
        
        // Check permissions
        $hasVehicleAdd = hasPermission('vehicle_licenses_add');
        $hasGeneralAdd = hasPermission('licenses_add');
        
        if (!$hasVehicleAdd && !$hasGeneralAdd) {
            echo json_encode(['success' => false, 'message' => 'غير مصرح لك بإضافة رخص المركبات']);
            exit;
        }
        
        // Check for duplicate car number
        if ($carNumbers && $carLettersClean) {
            $checkStmt = $conn->prepare("SELECT license_id FROM vehicle_licenses WHERE car_number = ? AND is_active = 1");
            $checkStmt->execute([$carNumber]);
            if ($checkStmt->fetch()) {
                $errors[] = 'رقم المركبة موجود بالفعل في النظام';
            }
        }
        
        if (!empty($errors)) {
            error_log("Vehicle license validation errors: " . print_r($errors, true));
            echo json_encode(['success' => false, 'message' => 'فشل التحقق من البيانات', 'details' => $errors]);
            exit;
        }
        
        // Handle file uploads for vehicle licenses
        $frontImagePath = null;
        $backImagePath = null;
        
        if (isset($_FILES['front_image']) && $_FILES['front_image']['error'] === UPLOAD_ERR_OK) {
            $frontImagePath = uploadImageToVehicleLicenses($_FILES['front_image'], 'front');
            if (!$frontImagePath) {
                $errors[] = 'فشل في رفع صورة الوجه الأمامي';
            }
        }
        
        if (isset($_FILES['back_image']) && $_FILES['back_image']['error'] === UPLOAD_ERR_OK) {
            $backImagePath = uploadImageToVehicleLicenses($_FILES['back_image'], 'back');
            if (!$backImagePath) {
                $errors[] = 'فشل في رفع صورة الوجه الخلفي';
            }
        }
        
        // Log upload attempts for debugging
        error_log("Vehicle license upload attempt - Front: " . ($frontImagePath ? "Success: $frontImagePath" : "Failed/No file"));
        error_log("Vehicle license upload attempt - Back: " . ($backImagePath ? "Success: $backImagePath" : "Failed/No file"));
        
        // Check if at least one image was uploaded successfully
        if (!$frontImagePath && !$backImagePath) {
            $errors[] = 'يجب رفع صورة واحدة على الأقل';
        }
        
        if (!empty($errors)) {
            // Clean up uploaded files if there were errors
            if ($frontImagePath && file_exists($frontImagePath)) {
                unlink($frontImagePath);
            }
            if ($backImagePath && file_exists($backImagePath)) {
                unlink($backImagePath);
            }
            
            error_log("Vehicle license creation failed due to errors: " . implode(', ', $errors));
            echo json_encode(['success' => false, 'message' => 'فشل في إضافة الترخيص', 'details' => $errors]);
            exit;
        }
        
        // Get current user ID
        $currentUserId = getUserId();
        if ($currentUserId === null) {
            echo json_encode(['success' => false, 'message' => 'خطأ في تحديد هوية المستخدم. يرجى تسجيل الدخول مرة أخرى']);
            exit;
        }
        
        // Insert vehicle license record with new fields
        $insertStmt = $conn->prepare("
            INSERT INTO vehicle_licenses (
                car_number, vehicle_type, license_category, inspection_year, issue_date, expiration_date, 
                project_id, department_id, user_id, front_image_path, back_image_path, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $insertStmt->execute([
            $carNumber,
            $vehicleType,
            $licenseCategory,
            $inspectionYear > 0 ? $inspectionYear : null,
            $issueDate,
            $expirationDate,
            $projectId,
            $departmentId,
            $currentUserId,
            $frontImagePath,
            $backImagePath,
            $notes
        ]);
        
        if ($result) {
            $licenseId = $conn->lastInsertId();
            
            // Get the inserted license with details
            $selectStmt = $conn->prepare("
                SELECT l.*, p.project_name, d.department_name
                FROM vehicle_licenses l
                JOIN projects p ON l.project_id = p.project_id
                JOIN departments d ON l.department_id = d.department_id
                WHERE l.license_id = ?
            ");
            $selectStmt->execute([$licenseId]);
            $license = $selectStmt->fetch();
            
            echo json_encode([
                'success' => true,
                'message' => 'تم إضافة رخصة المركبة بنجاح',
                'license' => $license
            ]);
        } else {
            // Clean up uploaded files if database insert failed
            if ($frontImagePath && file_exists($frontImagePath)) {
                unlink($frontImagePath);
            }
            if ($backImagePath && file_exists($backImagePath)) {
                unlink($backImagePath);
            }
            
            echo json_encode(['success' => false, 'message' => 'فشل في إضافة الترخيص']);
        }
        
    } else {
        error_log("Invalid license type: " . $licenseType);
        echo json_encode(['success' => false, 'message' => 'نوع الترخيص غير صحيح']);
    }
    
} catch (Exception $e) {
    error_log("Add license error: " . $e->getMessage());
    
    // Clean up uploaded files on error
    if (isset($frontImagePath) && $frontImagePath && file_exists($frontImagePath)) {
        unlink($frontImagePath);
    }
    if (isset($backImagePath) && $backImagePath && file_exists($backImagePath)) {
        unlink($backImagePath);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم']);
}
?> 
