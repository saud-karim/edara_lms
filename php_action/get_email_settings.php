<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// التحقق من الصلاحيات - Super Admin فقط
requireRole('super_admin');

header('Content-Type: application/json');

$settingName = $_GET['setting_name'] ?? '';

if (empty($settingName)) {
    echo json_encode([
        'success' => false,
        'message' => 'اسم الإعداد مطلوب'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            setting_id,
            setting_name,
            setting_value,
            description,
            is_active,
            created_at,
            updated_at
        FROM email_settings 
        WHERE setting_name = ? AND is_active = 1
    ");
    
    $stmt->execute([$settingName]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($setting) {
        echo json_encode([
            'success' => true,
            'data' => $setting
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'الإعداد غير موجود',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في جلب الإعدادات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// التحقق من الصلاحيات - Super Admin فقط
requireRole('super_admin');

header('Content-Type: application/json');

$settingName = $_GET['setting_name'] ?? '';

if (empty($settingName)) {
    echo json_encode([
        'success' => false,
        'message' => 'اسم الإعداد مطلوب'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            setting_id,
            setting_name,
            setting_value,
            description,
            is_active,
            created_at,
            updated_at
        FROM email_settings 
        WHERE setting_name = ? AND is_active = 1
    ");
    
    $stmt->execute([$settingName]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($setting) {
        echo json_encode([
            'success' => true,
            'data' => $setting
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'الإعداد غير موجود',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في جلب الإعدادات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 
 
 
 
 
 