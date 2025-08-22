<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Enable error logging for debugging
error_log("=== EDIT LICENSE DEBUG START ===");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    error_log("Method not allowed: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Ensure user is logged in and can edit
if (!isLoggedIn() || !canEditRecords()) {
    http_response_code(403);
    error_log("Access denied - isLoggedIn: " . (isLoggedIn() ? 'true' : 'false') . ", canEditRecords: " . (canEditRecords() ? 'true' : 'false'));
    echo json_encode(['success' => false, 'error' => 'غير مصرح لك بتعديل التراخيص']);
    exit;
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
error_log("CSRF token received: " . $csrfToken);
if (!validateCSRFToken($csrfToken)) {
    error_log("CSRF token validation failed");
    echo json_encode(['success' => false, 'error' => 'رمز الأمان غير صحيح']);
    exit;
}

try {
    $conn = getDBConnection();
    $errors = [];
    
    // Get and validate input
    $licenseId = intval($_POST['license_id'] ?? 0);
    $licenseType = $_POST['license_type'] ?? 'personal';
    
    error_log("License ID: $licenseId, License Type: $licenseType");
    
    if (!$licenseId) {
        error_log("License ID is missing or invalid");
        echo json_encode(['success' => false, 'error' => 'معرف الترخيص مطلوب']);
        exit;
    }
    
    if (!in_array($licenseType, ['personal', 'vehicle'])) {
        error_log("Invalid license type: $licenseType");
        echo json_encode(['success' => false, 'error' => 'نوع الترخيص غير صالح']);
        exit;
    }
    
    // Set table and fields based on license type
    if ($licenseType === 'personal') {
        $tableName = 'personal_licenses';
        $numberField = 'license_number';
        $nameField = 'full_name';
    } else {
        $tableName = 'vehicle_licenses';
        $numberField = 'car_number';
        $nameField = 'vehicle_type';
    }
    
    // Check if license exists and user can edit it
    $checkStmt = $conn->prepare("
        SELECT l.*, d.department_name 
        FROM $tableName l 
        LEFT JOIN departments d ON l.department_id = d.department_id 
        WHERE l.license_id = ? AND l.is_active = 1
    ");
    $checkStmt->execute([$licenseId]);
    $existingLicense = $checkStmt->fetch();
    
    if (!$existingLicense) {
        echo json_encode(['success' => false, 'error' => 'الترخيص غير موجود']);
        exit;
    }
    
    // Check permissions using Admin Teams System
    if (!canModifyLicense($existingLicense['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'غير مصرح لك بتعديل هذا الترخيص']);
        exit;
    }
    
    // Validate common fields
    $projectId = intval($_POST['project_id'] ?? 0);
    $departmentId = intval($_POST['department_id'] ?? 0);
    $issueDate = $_POST['issue_date'] ?? '';
    $expirationDate = $_POST['expiration_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$projectId) $errors[] = 'المشروع مطلوب';
    if (!$departmentId) $errors[] = 'القسم مطلوب';
    if (!$issueDate) $errors[] = 'تاريخ الإصدار مطلوب';
    if (!$expirationDate) $errors[] = 'تاريخ الانتهاء مطلوب';
    
    // Validate license-specific fields
    if ($licenseType === 'personal') {
        $licenseNumber = trim($_POST['license_number'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        
        if (!$licenseNumber) $errors[] = 'رقم الترخيص مطلوب';
        if (!$fullName) $errors[] = 'الاسم الكامل مطلوب';
        
        // Check for duplicate license number (excluding current license)
        $duplicateStmt = $conn->prepare("SELECT license_id FROM personal_licenses WHERE license_number = ? AND license_id != ? AND is_active = 1");
        $duplicateStmt->execute([$licenseNumber, $licenseId]);
        if ($duplicateStmt->fetch()) {
            $errors[] = 'رقم الترخيص موجود بالفعل';
        }
        
    } else {
        // Handle vehicle license - check for both separate and combined car number formats
        $carNumbers = trim($_POST['car_numbers'] ?? '');
        $carLetters = trim($_POST['car_letters'] ?? '');
        $carNumber = trim($_POST['car_number'] ?? '');
        $vehicleType = $_POST['vehicle_type'] ?? '';
        
        // If we have combined car_number but not separate fields, split it
        if (!$carNumbers && !$carLetters && $carNumber) {
            // Split combined car number (format: "letters numbers")
            $parts = explode(' ', $carNumber, 2);
            if (count($parts) === 2) {
                $carLetters = trim($parts[0]);
                $carNumbers = trim($parts[1]);
            }
        }
        
        // Remove spaces from letters for validation only
        $carLettersClean = str_replace(' ', '', $carLetters);
        
        // Combine car number parts WITH spaces for database storage
        $carNumber = $carLetters . ' ' . $carNumbers;
        
        if (!$carNumbers) $errors[] = 'أرقام المركبة مطلوبة';
        if (!$carLetters) $errors[] = 'حروف المركبة مطلوبة';
        if (!$vehicleType) $errors[] = 'نوع المركبة مطلوب';
        if (!in_array($vehicleType, ['موتوسيكل', 'عربية', 'تروسيكل'])) {
            $errors[] = 'نوع المركبة غير صالح';
        }
        
        // Validate car number parts
        if ($carNumbers && !preg_match('/^[0-9]{3,4}$/', $carNumbers)) {
            $errors[] = 'أرقام المركبة يجب أن تحتوي على 3-4 أرقام فقط';
        }
        
        // Validate letters (after removing spaces)
        if ($carLettersClean && !preg_match('/^[\x{0600}-\x{06FF}]{2,3}$/u', $carLettersClean)) {
            $errors[] = 'حروف المركبة يجب أن تحتوي على 2-3 أحرف عربية فقط';
        }
        
        // Check for duplicate car number (excluding current license) - compare without spaces for uniqueness
        if ($carNumbers && $carLettersClean) {
            $carNumberForCheck = $carLettersClean . $carNumbers; // Fixed: letters first, then numbers to match storage
            $duplicateStmt = $conn->prepare("
                SELECT license_id FROM vehicle_licenses 
                WHERE REPLACE(car_number, ' ', '') = ? AND license_id != ? AND is_active = 1
            ");
            $duplicateStmt->execute([$carNumberForCheck, $licenseId]);
            if ($duplicateStmt->fetch()) {
                $errors[] = 'رقم المركبة "' . $carNumber . '" موجود بالفعل في النظام. يرجى التحقق من أرقام وحروف المركبة أو استخدام رقم مختلف.';
            }
        }
    }
    
    // Validate dates
    if ($issueDate && $expirationDate && strtotime($expirationDate) <= strtotime($issueDate)) {
        $errors[] = 'تاريخ الانتهاء يجب أن يكون بعد تاريخ الإصدار';
    }
    
    // Return errors if any
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
        exit;
    }
    
    // Handle file uploads using the uploadImage function from config.php
    $frontImagePath = $existingLicense['front_image_path'];
    $backImagePath = $existingLicense['back_image_path'];
    
    // Upload front image if provided
    if (isset($_FILES['front_image']) && $_FILES['front_image']['error'] === UPLOAD_ERR_OK) {
        // Change working directory to parent to match upload function expectations
        $originalDir = getcwd();
        chdir('..');
        
        // Use appropriate upload function based on license type
        if ($licenseType === 'personal') {
            $uploadResult = uploadImageToPersonalLicenses($_FILES['front_image'], 'front');
        } else {
            $uploadResult = uploadImageToVehicleLicenses($_FILES['front_image'], 'front');
        }
        
        // Restore working directory
        chdir($originalDir);
        
        if ($uploadResult) {
            $frontImagePath = $uploadResult;
        } else {
            echo json_encode(['success' => false, 'error' => 'خطأ في رفع الصورة الأمامية']);
            exit;
        }
    }
    
    // Upload back image if provided
    if (isset($_FILES['back_image']) && $_FILES['back_image']['error'] === UPLOAD_ERR_OK) {
        // Change working directory to parent to match upload function expectations
        $originalDir = getcwd();
        chdir('..');
        
        // Use appropriate upload function based on license type
        if ($licenseType === 'personal') {
            $uploadResult = uploadImageToPersonalLicenses($_FILES['back_image'], 'back');
        } else {
            $uploadResult = uploadImageToVehicleLicenses($_FILES['back_image'], 'back');
        }
        
        // Restore working directory
        chdir($originalDir);
        
        if ($uploadResult) {
            $backImagePath = $uploadResult;
        } else {
            echo json_encode(['success' => false, 'error' => 'خطأ في رفع الصورة الخلفية']);
            exit;
        }
    }

    // Begin transaction
    $conn->beginTransaction();
    
    try {
        
        // Build update query based on license type
        if ($licenseType === 'personal') {
            $updateQuery = "
                UPDATE personal_licenses 
                SET license_number = ?, full_name = ?, issue_date = ?, expiration_date = ?, 
                    project_id = ?, department_id = ?, front_image_path = ?, back_image_path = ?, 
                    notes = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE license_id = ?
            ";
            $params = [$licenseNumber, $fullName, $issueDate, $expirationDate, $projectId, $departmentId, 
                      $frontImagePath, $backImagePath, $notes, $licenseId];
        } else {
            $updateQuery = "
                UPDATE vehicle_licenses 
                SET car_number = ?, vehicle_type = ?, issue_date = ?, expiration_date = ?, 
                    project_id = ?, department_id = ?, front_image_path = ?, back_image_path = ?, 
                    notes = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE license_id = ?
            ";
            $params = [$carNumber, $vehicleType, $issueDate, $expirationDate, $projectId, $departmentId,
                      $frontImagePath, $backImagePath, $notes, $licenseId];
        }
        
        $updateStmt = $conn->prepare($updateQuery);
        error_log("Executing UPDATE with params: " . print_r($params, true));
        error_log("UPDATE Query: " . $updateQuery);
        
        $result = $updateStmt->execute($params);
        
        error_log("UPDATE result: " . ($result ? 'SUCCESS' : 'FAILED'));
        error_log("Affected rows: " . $updateStmt->rowCount());
        
        if (!$result) {
            $errorInfo = $updateStmt->errorInfo();
            error_log("UPDATE error info: " . print_r($errorInfo, true));
            throw new Exception('فشل في تحديث الترخيص: ' . $errorInfo[2]);
        }
        
        if ($updateStmt->rowCount() === 0) {
            error_log("Warning: No rows were affected by the UPDATE");
            // Don't fail, might be no changes
        }
        
        // Log the update (optional - don't fail if logging fails)
        try {
            $logStmt = $conn->prepare("
                INSERT INTO license_logs (license_id, license_type, action, user_id, old_values, new_values, created_at) 
                VALUES (?, ?, 'updated', ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            $oldValues = json_encode($existingLicense);
            $newValues = json_encode($_POST);
            
            $logStmt->execute([$licenseId, $licenseType, getUserId(), $oldValues, $newValues]);
        } catch (Exception $logError) {
            // Log error but don't fail the main operation
            error_log("License log error: " . $logError->getMessage());
        }
        
        // Commit transaction
        $conn->commit();
        
        // After successful commit, clean up old images if new ones were uploaded
        if (isset($_FILES['front_image']) && $_FILES['front_image']['error'] === UPLOAD_ERR_OK) {
            $oldFrontPath = $existingLicense['front_image_path'];
            if ($oldFrontPath && file_exists('../' . $oldFrontPath) && $oldFrontPath !== $frontImagePath) {
                unlink('../' . $oldFrontPath);
            }
        }
        
        if (isset($_FILES['back_image']) && $_FILES['back_image']['error'] === UPLOAD_ERR_OK) {
            $oldBackPath = $existingLicense['back_image_path'];
            if ($oldBackPath && file_exists('../' . $oldBackPath) && $oldBackPath !== $backImagePath) {
                unlink('../' . $oldBackPath);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث الترخيص بنجاح',
            'data' => [
                'license_id' => $licenseId,
                'license_type' => $licenseType
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Edit license error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في تحديث الترخيص: ' . $e->getMessage()
    ]);
}
?> 
