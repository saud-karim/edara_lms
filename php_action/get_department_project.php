<?php
// DEPRECATED: Departments no longer have direct project relationships
// This endpoint returns empty data since departments are now independent

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only authenticated users can access
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح بالوصول'
    ]);
    exit;
}

// Return empty response since departments are no longer tied to projects
echo json_encode([
    'success' => false,
    'message' => 'الأقسام لم تعد مرتبطة بمشاريع محددة',
    'project_id' => null
]);
?> 