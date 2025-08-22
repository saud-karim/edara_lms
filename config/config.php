<?php
// Main Configuration File for License Management System

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'database.php';

// Include image helper functions
require_once __DIR__ . '/../includes/image_helpers.php';

// Application Settings
define('SITE_NAME', 'License Management System');
define('SITE_URL', 'http://localhost/License_Management_System'); // XAMPP standard path
define('UPLOAD_PATH', 'assests/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Email Configuration (Update with your SMTP settings)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'no-reply@company.com');
define('FROM_NAME', 'نظام إدارة التراخيص');
define('NOTIFICATIONS_EMAIL', 'krems840@gmail.com'); // Email for license expiration notifications

// Security Settings
define('HASH_ALGORITHM', 'sha256');
define('SESSION_TIMEOUT', 3600); // 1 hour

// File Upload Settings
define('UPLOAD_MAX_SIZE', '5M');
define('UPLOAD_ALLOWED_TYPES', 'jpg|jpeg|png|gif');

// Pagination Settings
define('RECORDS_PER_PAGE', 10);

// Date Format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');

// for xss attack


// Application Functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'غير محدد';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'وقت غير صحيح';
    }
    
    return date($format, $timestamp);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserDepartment() {
    return $_SESSION['department_id'] ?? null;
}

function getUserDepartmentName() {
    return $_SESSION['department_name'] ?? null;
}

function getUserProject() {
    return $_SESSION['project_id'] ?? null;
}

function getUserProjectName() {
    return $_SESSION['project_name'] ?? null;
}

function redirectTo($url) {
    // If URL starts with http or is absolute, use as is
    if (strpos($url, 'http') === 0 || strpos($url, '/') === 0) {
        header("Location: $url");
        exit();
    }
    
    // Check if we're in php_action directory and need to go up one level
    $currentDir = dirname($_SERVER['SCRIPT_NAME']);
    if (strpos($currentDir, '/php_action') !== false) {
        $redirectUrl = '../' . $url;
    } else {
        $redirectUrl = $url;
    }
    
    header("Location: $redirectUrl");
    exit();
}

function generateLicenseNumber() {
    return 'LIC' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function uploadImage($file, $directory = 'assests/uploads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    // Create upload directory if it doesn't exist
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $destination = $directory . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $destination;
    }
    
    return false;
}

// Enhanced upload functions for license images
function uploadImageToPersonalLicenses($file, $imageType = 'front') {
    // Check if file exists and has no errors
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File is larger than upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File is larger than MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown error';
        error_log("Personal license image upload failed - " . $errorMsg . " (Error code: " . ($file['error'] ?? 'unknown') . ")");
        return false;
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        error_log("Personal license image upload failed - invalid type: " . $file['type']);
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        error_log("Personal license image upload failed - file too large: " . $file['size'] . " bytes (max: " . MAX_FILE_SIZE . ")");
        return false;
    }
    
    // Create upload directory (use absolute path from project root)
    $directory = __DIR__ . '/../assests/uploads/personal_licenses/';
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            error_log("Personal license image upload failed - cannot create directory: " . $directory);
            return false;
        }
    }
    
    // For database storage, use relative path
    $relativePath = 'assests/uploads/personal_licenses/';
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $imageType . '_' . uniqid() . '_' . time() . '.' . $extension;
    $destination = $directory . $filename;
    $relativeDestination = $relativePath . $filename;
    
    // More detailed debugging
    error_log("Attempting to move file from: " . $file['tmp_name'] . " to: " . $destination);
    error_log("File size: " . $file['size'] . " bytes");
    error_log("Temp file exists: " . (file_exists($file['tmp_name']) ? 'Yes' : 'No'));
    error_log("Destination directory writable: " . (is_writable($directory) ? 'Yes' : 'No'));
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        error_log("Personal license image uploaded successfully: " . $destination);
        // Verify file was actually created
        if (file_exists($destination)) {
            error_log("File verified to exist after upload: " . $destination);
            return $relativeDestination; // Return relative path for database
        } else {
            error_log("File does not exist after supposedly successful upload: " . $destination);
            return false;
        }
    }
    
    $lastError = error_get_last();
    error_log("Personal license image upload failed - move_uploaded_file failed. Last error: " . ($lastError['message'] ?? 'None'));
    return false;
}

function uploadImageToVehicleLicenses($file, $imageType = 'front') {
    // Check if file exists and has no errors
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File is larger than upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File is larger than MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown error';
        error_log("Vehicle license image upload failed - " . $errorMsg . " (Error code: " . ($file['error'] ?? 'unknown') . ")");
        return false;
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        error_log("Vehicle license image upload failed - invalid type: " . $file['type']);
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        error_log("Vehicle license image upload failed - file too large: " . $file['size'] . " bytes (max: " . MAX_FILE_SIZE . ")");
        return false;
    }
    
    // Create upload directory (use absolute path from project root)
    $directory = __DIR__ . '/../assests/uploads/vehicle_licenses/';
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            error_log("Vehicle license image upload failed - cannot create directory: " . $directory);
            return false;
        }
    }
    
    // For database storage, use relative path
    $relativePath = 'assests/uploads/vehicle_licenses/';
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $imageType . '_' . uniqid() . '_' . time() . '.' . $extension;
    $destination = $directory . $filename;
    $relativeDestination = $relativePath . $filename;
    
    // More detailed debugging
    error_log("Attempting to move file from: " . $file['tmp_name'] . " to: " . $destination);
    error_log("File size: " . $file['size'] . " bytes");
    error_log("Temp file exists: " . (file_exists($file['tmp_name']) ? 'Yes' : 'No'));
    error_log("Destination directory writable: " . (is_writable($directory) ? 'Yes' : 'No'));
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        error_log("Vehicle license image uploaded successfully: " . $destination);
        // Verify file was actually created
        if (file_exists($destination)) {
            error_log("File verified to exist after upload: " . $destination);
            return $relativeDestination; // Return relative path for database
        } else {
            error_log("File does not exist after supposedly successful upload: " . $destination);
            return false;
        }
    }
    
    $lastError = error_get_last();
    error_log("Vehicle license image upload failed - move_uploaded_file failed. Last error: " . ($lastError['message'] ?? 'None'));
    return false;
}

// Error and Success Messages
function setMessage($message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function getMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'success';
        unset($_SESSION['message'], $_SESSION['message_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

function displayMessage() {
    $message = getMessage();
    if ($message) {
        $alertClass = $message['type'] === 'error' ? 'alert-danger' : 'alert-success';
        echo '<div class="alert ' . $alertClass . ' alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                ' . htmlspecialchars($message['message']) . '
              </div>';
    }
}
?>