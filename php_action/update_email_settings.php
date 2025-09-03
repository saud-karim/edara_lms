<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// التحقق من الصلاحيات - Super Admin فقط
requireRole('super_admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير صحيحة'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$settingName = trim($_POST['setting_name'] ?? '');
$settingValue = trim($_POST['setting_value'] ?? '');

// التحقق من صحة البيانات
if (empty($settingName)) {
    echo json_encode([
        'success' => false,
        'message' => 'اسم الإعداد مطلوب'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من صحة الإيميلات إذا كان الإعداد هو cc_emails
if ($settingName === 'cc_emails' && !empty($settingValue)) {
    $emails = array_map('trim', explode(',', $settingValue));
    $invalidEmails = [];
    
    foreach ($emails as $email) {
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $invalidEmails[] = $email;
        }
    }
    
    if (!empty($invalidEmails)) {
        echo json_encode([
            'success' => false,
            'message' => 'الإيميلات التالية غير صحيحة: ' . implode(', ', $invalidEmails)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $conn = getDBConnection();
    
    // التحقق من وجود الإعداد
    $checkStmt = $conn->prepare("SELECT setting_id FROM email_settings WHERE setting_name = ?");
    $checkStmt->execute([$settingName]);
    $existingSetting = $checkStmt->fetch();
    
    if ($existingSetting) {
        // تحديث الإعداد الموجود
        $updateStmt = $conn->prepare("
            UPDATE email_settings 
            SET setting_value = ?, updated_at = NOW() 
            WHERE setting_name = ?
        ");
        $result = $updateStmt->execute([$settingValue, $settingName]);
        $action = 'تحديث';
    } else {
        // إضافة إعداد جديد
        $insertStmt = $conn->prepare("
            INSERT INTO email_settings (setting_name, setting_value, description, is_active) 
            VALUES (?, ?, ?, 1)
        ");
        
        $description = '';
        if ($settingName === 'cc_emails') {
            $description = 'الإيميلات الثابتة التي ستُضاف كـ CC لكل إشعار';
        }
        
        $result = $insertStmt->execute([$settingName, $settingValue, $description]);
        $action = 'إضافة';
    }
    
    if ($result) {
        // تسجيل العملية في سجل النظام
        $logMessage = "تم {$action} إعداد الإيميل '{$settingName}'";
        $logStmt = $conn->prepare("
            INSERT INTO system_logs (log_level, message, user_id, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $logStmt->execute(['INFO', $logMessage, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => "تم {$action} الإعداد بنجاح",
            'data' => [
                'setting_name' => $settingName,
                'setting_value' => $settingValue,
                'action' => $action,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "فشل في {$action} الإعداد"
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحديث الإعدادات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// التحقق من الصلاحيات - Super Admin فقط
requireRole('super_admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير صحيحة'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$settingName = trim($_POST['setting_name'] ?? '');
$settingValue = trim($_POST['setting_value'] ?? '');

// التحقق من صحة البيانات
if (empty($settingName)) {
    echo json_encode([
        'success' => false,
        'message' => 'اسم الإعداد مطلوب'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من صحة الإيميلات إذا كان الإعداد هو cc_emails
if ($settingName === 'cc_emails' && !empty($settingValue)) {
    $emails = array_map('trim', explode(',', $settingValue));
    $invalidEmails = [];
    
    foreach ($emails as $email) {
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $invalidEmails[] = $email;
        }
    }
    
    if (!empty($invalidEmails)) {
        echo json_encode([
            'success' => false,
            'message' => 'الإيميلات التالية غير صحيحة: ' . implode(', ', $invalidEmails)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $conn = getDBConnection();
    
    // التحقق من وجود الإعداد
    $checkStmt = $conn->prepare("SELECT setting_id FROM email_settings WHERE setting_name = ?");
    $checkStmt->execute([$settingName]);
    $existingSetting = $checkStmt->fetch();
    
    if ($existingSetting) {
        // تحديث الإعداد الموجود
        $updateStmt = $conn->prepare("
            UPDATE email_settings 
            SET setting_value = ?, updated_at = NOW() 
            WHERE setting_name = ?
        ");
        $result = $updateStmt->execute([$settingValue, $settingName]);
        $action = 'تحديث';
    } else {
        // إضافة إعداد جديد
        $insertStmt = $conn->prepare("
            INSERT INTO email_settings (setting_name, setting_value, description, is_active) 
            VALUES (?, ?, ?, 1)
        ");
        
        $description = '';
        if ($settingName === 'cc_emails') {
            $description = 'الإيميلات الثابتة التي ستُضاف كـ CC لكل إشعار';
        }
        
        $result = $insertStmt->execute([$settingName, $settingValue, $description]);
        $action = 'إضافة';
    }
    
    if ($result) {
        // تسجيل العملية في سجل النظام
        $logMessage = "تم {$action} إعداد الإيميل '{$settingName}'";
        $logStmt = $conn->prepare("
            INSERT INTO system_logs (log_level, message, user_id, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $logStmt->execute(['INFO', $logMessage, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => "تم {$action} الإعداد بنجاح",
            'data' => [
                'setting_name' => $settingName,
                'setting_value' => $settingValue,
                'action' => $action,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "فشل في {$action} الإعداد"
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحديث الإعدادات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 
 
 
 
 
 