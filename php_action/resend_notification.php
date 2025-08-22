<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only allow super admin to resend
requireRole('super_admin');

header('Content-Type: application/json');

$notificationId = intval($_POST['notification_id'] ?? 0);

if (!$notificationId) {
    echo json_encode(['success' => false, 'message' => 'معرف الإشعار غير صحيح']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get notification details
    $stmt = $conn->prepare("
        SELECT 
            notification_id,
            department_name,
            project_name,
            recipient_email,
            subject,
            message,
            sent_status
        FROM email_notifications 
        WHERE notification_id = ?
    ");
    
    $stmt->execute([$notificationId]);
    $notification = $stmt->fetch();
    
    if (!$notification) {
        echo json_encode(['success' => false, 'message' => 'الإشعار غير موجود']);
        exit;
    }
    
    if ($notification['sent_status'] === 'sent') {
        echo json_encode(['success' => false, 'message' => 'هذا الإشعار تم إرساله بالفعل']);
        exit;
    }
    
    // Update status to pending
    $updateStmt = $conn->prepare("
        UPDATE email_notifications 
        SET sent_status = 'pending', error_message = NULL 
        WHERE notification_id = ?
    ");
    $updateStmt->execute([$notificationId]);
    
    // Try to send email
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // SMTP settings
    $mail->isSMTP();
    $mail->SMTPDebug = 0;
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'edarasec@gmail.com';
    $mail->Password = 'vxwgihbbcuhvmimc';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    
    // Email setup
    $mail->setFrom('shehab.sayed@edaraproperty.net', 'نظام إدارة التراخيص');
    $mail->addReplyTo('shehab.sayed@edaraproperty.net', 'نظام إدارة التراخيص');
    $mail->addAddress($notification['recipient_email'], $notification['department_name']);
    
    $mail->isHTML(true);
    $mail->Subject = $notification['subject'];
    $mail->Body = $notification['message'];
    
    // Send email
    $mail->send();
    
    // Update status to sent
    $updateStmt = $conn->prepare("
        UPDATE email_notifications 
        SET sent_status = 'sent', sent_at = NOW(), error_message = NULL 
        WHERE notification_id = ?
    ");
    $updateStmt->execute([$notificationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إعادة إرسال الإشعار بنجاح'
    ]);
    
} catch (Exception $e) {
    // Update status to failed with error message
    try {
        $updateStmt = $conn->prepare("
            UPDATE email_notifications 
            SET sent_status = 'failed', error_message = ? 
            WHERE notification_id = ?
        ");
        $updateStmt->execute([$e->getMessage(), $notificationId]);
    } catch (Exception $updateErr) {
        // Silent fail for update
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'فشل في إعادة الإرسال: ' . $e->getMessage()
    ]);
}
?> 