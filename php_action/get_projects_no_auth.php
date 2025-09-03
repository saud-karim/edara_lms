<?php
// Get projects without authentication (for admin forms)

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';

try {
    $conn = getDBConnection();
    
    $query = "
        SELECT 
            project_id,
            project_name,
            project_description,
            is_active
        FROM projects 
        WHERE is_active = 1 
        ORDER BY project_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $projects,
        'total_count' => count($projects),
        'message' => 'تم تحميل المشاريع بنجاح'
    ]);
    
} catch (Exception $e) {
    error_log("Get projects no auth error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في تحميل المشاريع',
        'message' => $e->getMessage()
    ]);
}
?> 