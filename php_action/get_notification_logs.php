<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only allow super admin to view logs
requireRole('super_admin');

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Get the latest 50 notification logs
    $stmt = $conn->prepare("
        SELECT 
            notification_id,
            department_name,
            project_name,
            recipient_email,
            subject,
            total_licenses,
            expired_count,
            expiring_count,
            sent_status,
            error_message,
            sent_at,
            created_at,
            DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as formatted_created_at,
            DATE_FORMAT(sent_at, '%d/%m/%Y %H:%i') as formatted_sent_at
        FROM email_notifications 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    // Format the data for display
    foreach ($logs as &$log) {
        $log['created_at'] = $log['formatted_created_at'];
        $log['sent_at'] = $log['formatted_sent_at'];
        
        // Clean up
        unset($log['formatted_created_at']);
        unset($log['formatted_sent_at']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'message' => 'تم تحميل السجلات بنجاح'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل السجلات: ' . $e->getMessage()
    ]);
}
?> 