<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Only allow super admin to view content
requireRole('super_admin');

$notificationId = intval($_GET['id'] ?? 0);

if (!$notificationId) {
    echo '<div class="alert alert-danger">معرف الإشعار غير صحيح</div>';
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            notification_id,
            department_name,
            project_name,
            recipient_email,
            subject,
            message,
            total_licenses,
            expired_count,
            expiring_count,
            sent_status,
            error_message,
            sent_at,
            created_at
        FROM email_notifications 
        WHERE notification_id = ?
    ");
    
    $stmt->execute([$notificationId]);
    $notification = $stmt->fetch();
    
    if (!$notification) {
        echo '<div class="alert alert-danger">الإشعار غير موجود</div>';
        exit;
    }
    
    // Display header info and then the email content
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>محتوى الإشعار #<?php echo $notificationId; ?></title>
        <link rel="stylesheet" href="../assests/bootstrap/css/bootstrap.min.css">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                direction: rtl; 
                text-align: right; 
            }
            .info-box { 
                background: #f8f9fa; 
                border: 1px solid #ddd; 
                border-radius: 5px; 
                padding: 15px; 
                margin-bottom: 20px; 
            }
            .email-content { 
                border: 2px solid #007bff; 
                border-radius: 5px; 
                padding: 10px; 
                background: white;
            }
        </style>
    </head>
    <body>
        <div class="container-fluid">
            <h2>تفاصيل الإشعار #<?php echo $notificationId; ?></h2>
            
            <div class="info-box">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>القسم:</strong> <?php echo htmlspecialchars($notification['department_name']); ?></p>
                        <p><strong>المشروع:</strong> <?php echo htmlspecialchars($notification['project_name']); ?></p>
                        <p><strong>البريد الإلكتروني:</strong> <?php echo htmlspecialchars($notification['recipient_email']); ?></p>
                        <p><strong>الموضوع:</strong> <?php echo htmlspecialchars($notification['subject']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>إجمالي الرخص:</strong> <span class="badge"><?php echo $notification['total_licenses']; ?></span></p>
                        <p><strong>رخص منتهية:</strong> <span class="badge badge-danger"><?php echo $notification['expired_count']; ?></span></p>
                        <p><strong>رخص ستنتهي:</strong> <span class="badge badge-warning"><?php echo $notification['expiring_count']; ?></span></p>
                        <p><strong>الحالة:</strong> 
                            <?php
                            $statusClass = $notification['sent_status'] === 'sent' ? 'success' : 
                                          ($notification['sent_status'] === 'failed' ? 'danger' : 'warning');
                            $statusText = $notification['sent_status'] === 'sent' ? 'تم الإرسال' : 
                                         ($notification['sent_status'] === 'failed' ? 'فشل الإرسال' : 'في الانتظار');
                            ?>
                            <span class="label label-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </p>
                        <p><strong>تاريخ الإنشاء:</strong> <?php echo $notification['created_at']; ?></p>
                        <?php if ($notification['sent_at']): ?>
                            <p><strong>تاريخ الإرسال:</strong> <?php echo $notification['sent_at']; ?></p>
                        <?php endif; ?>
                        <?php if ($notification['error_message']): ?>
                            <p><strong>رسالة الخطأ:</strong> <span class="text-danger"><?php echo htmlspecialchars($notification['error_message']); ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <h3>محتوى الإيميل:</h3>
            <div class="email-content">
                <?php echo $notification['message']; ?>
            </div>
        </div>
        
        <script>
            // Print button
            document.addEventListener('DOMContentLoaded', function() {
                const printBtn = document.createElement('button');
                printBtn.className = 'btn btn-primary';
                printBtn.innerHTML = '<i class="glyphicon glyphicon-print"></i> طباعة';
                printBtn.onclick = function() { window.print(); };
                document.querySelector('.container-fluid').insertBefore(printBtn, document.querySelector('.info-box'));
            });
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">خطأ في تحميل محتوى الإشعار: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?> 