<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only allow super admin to view
requireRole('super_admin');

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    $type = $_GET['type'] ?? '';
    
    if ($type === 'departments') {
        $sql = "
            SELECT DISTINCT department_name 
            FROM email_notifications 
            ORDER BY department_name
        ";
    } elseif ($type === 'projects') {
        $sql = "
            SELECT DISTINCT project_name 
            FROM email_notifications 
            ORDER BY project_name
        ";
    } else {
        throw new Exception('نوع الفلتر غير صحيح');
    }
    
    $stmt = $conn->query($sql);
    $data = $stmt->fetchAll();
    
    // Ensure data is an array
    if (!$data) {
        $data = [];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => 'تم تحميل البيانات بنجاح'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل البيانات: ' . $e->getMessage()
    ]);
}
?> 