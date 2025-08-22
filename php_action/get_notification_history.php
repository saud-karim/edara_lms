<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only allow super admin to view
requireRole('super_admin');

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Get filters
    $status = $_POST['status'] ?? '';
    $department = $_POST['department'] ?? '';
    $project = $_POST['project'] ?? '';
    $search = $_POST['search'] ?? '';
    $fromDate = $_POST['from_date'] ?? '';
    $toDate = $_POST['to_date'] ?? '';
    $page = intval($_POST['page'] ?? 1);
    $limit = 20; // Records per page
    $offset = ($page - 1) * $limit;
    
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
    
    // Count total records
    $countSql = "SELECT COUNT(*) as total FROM email_notifications $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $countResult = $countStmt->fetch();
    $totalRecords = $countResult ? intval($countResult['total']) : 0;
    $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 1;
    
    // Get notifications with pagination
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
            sent_at,
            created_at,
            DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as formatted_created_at,
            DATE_FORMAT(sent_at, '%d/%m/%Y %H:%i') as formatted_sent_at
        FROM email_notifications 
        $whereClause
        ORDER BY created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    // Ensure notifications is an array
    if (!$notifications) {
        $notifications = [];
    }
    
    // Format the data
    foreach ($notifications as &$notification) {
        $notification['created_at'] = $notification['formatted_created_at'] ?: '';
        $notification['sent_at'] = $notification['formatted_sent_at'] ?: '';
        
        // Clean up
        unset($notification['formatted_created_at']);
        unset($notification['formatted_sent_at']);
    }
    
    // Get statistics
    $statsSql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN sent_status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN sent_status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN sent_status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM email_notifications 
        $whereClause
    ";
    
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->execute($params);
    $statistics = $statsStmt->fetch();
    
    // Ensure statistics are integers
    if (!$statistics) {
        $statistics = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'pending' => 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'statistics' => [
            'total' => intval($statistics['total']),
            'sent' => intval($statistics['sent']),
            'failed' => intval($statistics['failed']),
            'pending' => intval($statistics['pending'])
        ],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $limit
        ],
        'message' => 'تم تحميل البيانات بنجاح'
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Notification History Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل البيانات: ' . $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'sql_params' => $params ?? []
        ]
    ]);
}
?> 