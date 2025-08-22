<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

// Check permissions using advanced logic
$currentUserId = getUserId();
$hasAnyVehiclePermsInDB = hasAnySpecificPermissionsInDB($currentUserId, 'vehicle_licenses_');

// Apply advanced logic for vehicle licenses access
if ($hasAnyVehiclePermsInDB) {
    // User has specific vehicle permissions in DB, use ONLY those (ignore general permissions)
    $canAddVehicle = hasPermission('vehicle_licenses_add');
} else {
    // User has NO specific vehicle permissions in DB, fall back to general permissions
    $canAddVehicle = hasPermission('licenses_add');
}

if (!$canAddVehicle) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'غير مصرح لك بإضافة رخص المركبات']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'رمز الأمان غير صحيح']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $conn = getDBConnection();
    $errors = [];
    
    // Get separate car number fields
    $carNumbers = trim($_POST['car_numbers'] ?? '');
    $carLetters = trim($_POST['car_letters'] ?? '');
    $vehicleType = sanitizeInput($_POST['vehicle_type']);
    $issueDate = $_POST['issue_date'];
    $expirationDate = $_POST['expiration_date'];
    $projectId = intval($_POST['project_id']);
    $departmentId = intval($_POST['department_id']);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
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
    
    // Combine car number parts WITH spaces for database storage - letters first, then numbers
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
    
    // Validate dates
    if ($issueDate && $expirationDate) {
        $issueTimestamp = strtotime($issueDate);
        $expirationTimestamp = strtotime($expirationDate);
        
        if ($expirationTimestamp <= $issueTimestamp) {
            $errors[] = 'تاريخ الانتهاء يجب أن يكون بعد تاريخ الإصدار';
        }
    }
    
    // Check for duplicate car number - compare without spaces for uniqueness
    if ($carNumbers && $carLettersClean) {
        $carNumberForCheck = $carLettersClean . $carNumbers; // Fixed: letters first, then numbers to match storage
        $duplicateStmt = $conn->prepare("
            SELECT license_id FROM vehicle_licenses 
            WHERE REPLACE(car_number, ' ', '') = ? AND is_active = 1
        ");
        $duplicateStmt->execute([$carNumberForCheck]);
        if ($duplicateStmt->fetch()) {
            $errors[] = 'رقم المركبة "' . $carNumber . '" موجود بالفعل في النظام. يرجى التحقق من أرقام وحروف المركبة أو استخدام رقم مختلف.';
        }
    }
    
    // Return errors if validation fails
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
        exit;
    }
    
    // Check user permissions for department
    $userRole = getUserRole();
    $userDepartment = getUserDepartment();
    
    if ($userRole === 'admin' && $departmentId != $userDepartment) {
        echo json_encode(['success' => false, 'error' => 'غير مصرح لك بإضافة تراخيص لهذا القسم']);
        exit;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Handle file uploads
        $frontImagePath = '';
        $backImagePath = '';
        
        // Upload front image if provided
        if (isset($_FILES['front_image']) && $_FILES['front_image']['error'] === UPLOAD_ERR_OK) {
            // Change working directory to parent to match upload function expectations
            $originalDir = getcwd();
            chdir('..');
            
            $uploadResult = uploadImageToVehicleLicenses($_FILES['front_image'], 'front');
            
            // Restore working directory
            chdir($originalDir);
            
            if ($uploadResult) {
                $frontImagePath = $uploadResult;
            } else {
                $errors[] = 'خطأ في رفع الصورة الأمامية';
            }
        }
        
        // Upload back image if provided
        if (isset($_FILES['back_image']) && $_FILES['back_image']['error'] === UPLOAD_ERR_OK) {
            // Change working directory to parent to match upload function expectations
            $originalDir = getcwd();
            chdir('..');
            
            $uploadResult = uploadImageToVehicleLicenses($_FILES['back_image'], 'back');
            
            // Restore working directory
            chdir($originalDir);
            
            if ($uploadResult) {
                $backImagePath = $uploadResult;
            } else {
                $errors[] = 'خطأ في رفع الصورة الخلفية';
            }
        }
        
        // Return errors if any upload failed
        if (!empty($errors)) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
            exit;
        }
        
        // Get current user ID from session
        $currentUserId = getUserId();
        
        if ($currentUserId === null) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'خطأ في تحديد هوية المستخدم. يرجى تسجيل الدخول مرة أخرى']);
            exit;
        }
        
        // Insert vehicle license - car_number includes spaces
        $insertQuery = "
            INSERT INTO vehicle_licenses (
                car_number, vehicle_type, issue_date, expiration_date, 
                project_id, department_id, user_id, front_image_path, 
                back_image_path, notes, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ";
        
        $stmt = $conn->prepare($insertQuery);
        $result = $stmt->execute([
            $carNumber, // This includes spaces: "7894 ن ت ي"
            $vehicleType,
            $issueDate,
            $expirationDate,
            $projectId,
            $departmentId,
            $currentUserId,
            $frontImagePath,
            $backImagePath,
            $notes
        ]);
        
        if (!$result) {
            throw new Exception('فشل في إضافة رخصة المركبة');
        }
        
        $licenseId = $conn->lastInsertId();
        
        // Log the addition
        $logStmt = $conn->prepare("
            INSERT INTO license_logs (license_id, license_type, action, user_id, old_values, new_values, created_at) 
            VALUES (?, 'vehicle', 'created', ?, NULL, ?, CURRENT_TIMESTAMP)
        ");
        
        $newValues = json_encode([
            'car_number' => $carNumber,
            'vehicle_type' => $vehicleType,
            'issue_date' => $issueDate,
            'expiration_date' => $expirationDate,
            'project_id' => $projectId,
            'department_id' => $departmentId
        ]);
        
        $logStmt->execute([$licenseId, getUserId(), $newValues]);
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم إضافة رخصة المركبة بنجاح',
            'data' => [
                'license_id' => $licenseId,
                'car_number' => $carNumber
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Add vehicle license error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في إضافة رخصة المركبة: ' . $e->getMessage()
    ]);
}
?> 
