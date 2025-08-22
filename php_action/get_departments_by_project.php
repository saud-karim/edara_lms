<?php
// DEPRECATED: This file is no longer needed after department restructure
// Departments are now independent from projects
// Use get_unique_departments.php instead

header('Content-Type: application/json; charset=UTF-8');
require_once realpath(dirname(__FILE__) . '/../config/config.php');
require_once realpath(dirname(__FILE__) . '/auth.php');

// Ensure user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح بالوصول']);
    exit;
}

// Return empty departments list as this endpoint is deprecated
echo json_encode([
    'success' => true,
    'data' => [],
    'message' => 'هذا الـ endpoint لم يعد مستخدماً. استخدم get_unique_departments.php بدلاً منه'
]);
?> 
