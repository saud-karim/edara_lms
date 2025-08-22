<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only allow super admin to export
requireRole('super_admin');

try {
    $conn = getDBConnection();
    
    // Get filters from URL parameters
    $status = $_GET['status'] ?? '';
    $department = $_GET['department'] ?? '';
    $project = $_GET['project'] ?? '';
    $search = $_GET['search'] ?? '';
    $fromDate = $_GET['from_date'] ?? '';
    $toDate = $_GET['to_date'] ?? '';
    
    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    
    if (!empty($status)) {
        $whereConditions[] = "sent_status = ?";
        $params[] = $status;
    }
    
    if (!empty($department)) {
        $whereConditions[] = "department_name LIKE ?";
        $params[] = "%$department%";
    }
    
    if (!empty($project)) {
        $whereConditions[] = "project_name LIKE ?";
        $params[] = "%$project%";
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(subject LIKE ? OR recipient_email LIKE ? OR message LIKE ? OR department_name LIKE ? OR project_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($fromDate)) {
        $whereConditions[] = "DATE(created_at) >= ?";
        $params[] = $fromDate;
    }
    
    if (!empty($toDate)) {
        $whereConditions[] = "DATE(created_at) <= ?";
        $params[] = $toDate;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get all notifications for export
    $sql = "
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
            DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_date,
            DATE_FORMAT(sent_at, '%d/%m/%Y %H:%i') as sent_date
        FROM email_notifications 
        $whereClause
        ORDER BY created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    // Set headers for CSV download
    $filename = "notification_history_" . date('Y-m-d_H-i-s') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Add BOM for proper UTF-8 encoding in Excel
    echo "\xEF\xBB\xBF";
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, [
        'رقم الإشعار',
        'تاريخ الإنشاء',
        'تاريخ الإرسال',
        'القسم',
        'المشروع',
        'البريد الإلكتروني',
        'الموضوع',
        'إجمالي الرخص',
        'رخص منتهية',
        'رخص ستنتهي',
        'حالة الإرسال',
        'رسالة الخطأ'
    ]);
    
    // Write data rows
    foreach ($notifications as $notification) {
        $statusText = '';
        switch ($notification['sent_status']) {
            case 'sent':
                $statusText = 'تم الإرسال';
                break;
            case 'failed':
                $statusText = 'فشل الإرسال';
                break;
            case 'pending':
                $statusText = 'في الانتظار';
                break;
        }
        
        fputcsv($output, [
            $notification['notification_id'],
            $notification['created_date'],
            $notification['sent_date'] ?: 'لم يتم الإرسال',
            $notification['department_name'],
            $notification['project_name'],
            $notification['recipient_email'],
            $notification['subject'],
            $notification['total_licenses'],
            $notification['expired_count'],
            $notification['expiring_count'],
            $statusText,
            $notification['error_message'] ?: ''
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    // If export fails, show error message
    header('Content-Type: text/html; charset=UTF-8');
    echo '<div style="font-family: Arial; direction: rtl; text-align: right; margin: 20px;">';
    echo '<h2>خطأ في تصدير البيانات</h2>';
    echo '<p>حدث خطأ أثناء تصدير سجل الإشعارات: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="javascript:history.back()">العودة</a></p>';
    echo '</div>';
}
?> 