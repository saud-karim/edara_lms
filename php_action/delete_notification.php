<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only allow super admin to delete
requireRole('super_admin');

header('Content-Type: application/json');

$notificationId = intval($_POST['notification_id'] ?? 0);

if (!$notificationId) {
    echo json_encode(['success' => false, 'message' => 'معرف الإشعار غير صحيح']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check if notification exists
    $checkStmt = $conn->prepare("SELECT notification_id FROM email_notifications WHERE notification_id = ?");
    $checkStmt->execute([$notificationId]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'الإشعار غير موجود']);
        exit;
    }
    
    // Delete the notification
    $deleteStmt = $conn->prepare("DELETE FROM email_notifications WHERE notification_id = ?");
    $result = $deleteStmt->execute([$notificationId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف الإشعار بنجاح'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل في حذف الإشعار'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في حذف الإشعار: ' . $e->getMessage()
    ]);
}
?> 