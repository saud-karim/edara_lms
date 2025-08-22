<?php
header('Content-Type: application/json');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

// Check permissions using advanced logic
$currentUserId = getUserId();
$hasAnyPersonalPermsInDB = hasAnySpecificPermissionsInDB($currentUserId, 'personal_licenses_');

// Apply advanced logic for personal licenses access
if ($hasAnyPersonalPermsInDB) {
    // User has specific personal permissions in DB, use ONLY those (ignore general permissions)
    $canAddPersonal = hasPermission('personal_licenses_add');
} else {
    // User has NO specific personal permissions in DB, fall back to general permissions
    $canAddPersonal = hasPermission('licenses_add');
}

if (!$canAddPersonal) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بإضافة رخص القيادة الشخصية']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'رمز الأمان غير صحيح']);
    exit;
}

try {
    $conn = getDBConnection();
    $errors = [];
    
    // Validate required fields
    $requiredFields = [
        'license_number' => 'رقم الرخصة',
        'full_name' => 'الاسم الكامل',
        'issue_date' => 'تاريخ الإصدار',
        'expiration_date' => 'تاريخ الانتهاء',
        'project_id' => 'المشروع',
        'department_id' => 'القسم'
    ];
    
    foreach ($requiredFields as $field => $fieldName) {
        if (empty($_POST[$field])) {
            $errors[] = $fieldName . ' مطلوب';
        }
    }
    
    // Validate image files (optional for testing)
    $frontImageProvided = isset($_FILES['front_image']) && $_FILES['front_image']['error'] !== UPLOAD_ERR_NO_FILE;
    $backImageProvided = isset($_FILES['back_image']) && $_FILES['back_image']['error'] !== UPLOAD_ERR_NO_FILE;
    
    // Only validate if images are provided
    if ($frontImageProvided && $_FILES['front_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'خطأ في رفع صورة الوجه الأمامي';
    }
    
    if ($backImageProvided && $_FILES['back_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'خطأ في رفع صورة الوجه الخلفي';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
        exit;
    }
    
    // Use sanitizeInput function from config.php (already included)
    $licenseNumber = sanitizeInput($_POST['license_number']);
    $fullName = sanitizeInput($_POST['full_name']);
    $issueDate = $_POST['issue_date'];
    $expirationDate = $_POST['expiration_date'];
    $projectId = (int)$_POST['project_id'];
    $departmentId = (int)$_POST['department_id'];
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    // Check if license number already exists
    $checkStmt = $conn->prepare("SELECT license_id FROM personal_licenses WHERE license_number = ? AND is_active = 1");
    $checkStmt->execute([$licenseNumber]);
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'رقم الرخصة موجود بالفعل']);
        exit;
    }
    
    // Validate dates
    if (strtotime($issueDate) > strtotime($expirationDate)) {
        echo json_encode(['success' => false, 'message' => 'تاريخ الانتهاء يجب أن يكون بعد تاريخ الإصدار']);
        exit;
    }
    
    // Validate project and department relationship
    $deptStmt = $conn->prepare("
        SELECT d.department_id 
        FROM departments d 
        WHERE d.department_id = ? AND d.is_active = 1
    ");
    $deptStmt->execute([$departmentId]);
    if (!$deptStmt->fetch()) {
        $errors[] = 'القسم المحدد غير صحيح أو غير نشط';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
        exit;
    }
    
    // Handle image uploads
    $frontImagePath = '';
    $backImagePath = '';
    $uploadDir = '../assests/uploads/personal_licenses/';
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate and upload front image
    if (isset($_FILES['front_image']) && $_FILES['front_image']['error'] === UPLOAD_ERR_OK) {
        $frontImageInfo = pathinfo($_FILES['front_image']['name']);
        $frontImageExt = strtolower($frontImageInfo['extension']);
        
        // Validate file type
        if (!in_array($frontImageExt, ['jpg', 'jpeg', 'png'])) {
            $errors[] = 'نوع ملف الصورة الأمامية غير مدعوم. يُسمح بـ JPG و PNG فقط';
        }
        
        // Validate file size (5MB max)
        if ($_FILES['front_image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'حجم الصورة الأمامية كبير جداً. الحد الأقصى 5MB';
        }
        
        if (empty($errors)) {
            $frontImageName = 'front_' . uniqid() . '_' . time() . '.' . $frontImageExt;
            $frontImagePath = $uploadDir . $frontImageName;
            
            if (!move_uploaded_file($_FILES['front_image']['tmp_name'], $frontImagePath)) {
                $errors[] = 'فشل في رفع الصورة الأمامية';
            } else {
                $frontImagePath = 'assests/uploads/personal_licenses/' . $frontImageName; // Relative path for database
            }
        }
    }
    
    // Validate and upload back image
    if (isset($_FILES['back_image']) && $_FILES['back_image']['error'] === UPLOAD_ERR_OK && empty($errors)) {
        $backImageInfo = pathinfo($_FILES['back_image']['name']);
        $backImageExt = strtolower($backImageInfo['extension']);
        
        // Validate file type
        if (!in_array($backImageExt, ['jpg', 'jpeg', 'png'])) {
            $errors[] = 'نوع ملف الصورة الخلفية غير مدعوم. يُسمح بـ JPG و PNG فقط';
        }
        
        // Validate file size (5MB max)
        if ($_FILES['back_image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'حجم الصورة الخلفية كبير جداً. الحد الأقصى 5MB';
        }
        
        if (empty($errors)) {
            $backImageName = 'back_' . uniqid() . '_' . time() . '.' . $backImageExt;
            $backImagePath = $uploadDir . $backImageName;
            
            if (!move_uploaded_file($_FILES['back_image']['tmp_name'], $backImagePath)) {
                $errors[] = 'فشل في رفع الصورة الخلفية';
            } else {
                $backImagePath = 'assests/uploads/personal_licenses/' . $backImageName; // Relative path for database
            }
        }
    }
    
    if (!empty($errors)) {
        // Clean up uploaded files if there were errors
        if (!empty($frontImagePath) && file_exists('../' . $frontImagePath)) {
            unlink('../' . $frontImagePath);
        }
        if (!empty($backImagePath) && file_exists('../' . $backImagePath)) {
            unlink('../' . $backImagePath);
        }
        
        echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
        exit;
    }
    
    // Get current user ID from session
    $currentUserId = getUserId();
    
    if ($currentUserId === null) {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديد هوية المستخدم. يرجى تسجيل الدخول مرة أخرى']);
        exit;
    }
    
    // Insert personal license
    $insertStmt = $conn->prepare("
        INSERT INTO personal_licenses (
            license_number, full_name, issue_date, expiration_date,
            project_id, department_id, user_id,
            front_image_path, back_image_path, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $insertStmt->execute([
        $licenseNumber,
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
        
        // Log the action
        $logStmt = $conn->prepare("
            INSERT INTO license_logs (license_id, license_type, action, user_id, new_values, ip_address, user_agent)
            VALUES (?, 'personal', 'created', ?, ?, ?, ?)
        ");
        
        $newValues = json_encode([
            'license_number' => $licenseNumber,
            'full_name' => $fullName,
            'issue_date' => $issueDate,
            'expiration_date' => $expirationDate
        ]);
        
        $logStmt->execute([
            $licenseId,
            getUserId(),
            $newValues,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم إضافة رخصة القيادة الشخصية بنجاح',
            'license_id' => $licenseId
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في إضافة رخصة القيادة']);
    }

} catch (Exception $e) {
    error_log("Add personal license error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم']);
}
?> 
