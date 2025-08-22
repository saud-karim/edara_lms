<?php
/**
 * Cron Job for License Notifications - New Version
 * Based on working sendemail example
 * 
 * Usage: php cron_notifications_new.php
 * Add to crontab: 0 9 * * * php /path/to/cron_notifications_new.php
 */

// Set timezone
date_default_timezone_set('Asia/Riyadh');

// Include required files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Log function - now stores in database
function logMessage($message, $conn = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;
    
    // Echo for CLI
    echo $logEntry;
    
    // Optionally store critical messages in database
    if ($conn && (strpos($message, '✅') !== false || strpos($message, '💥') !== false || strpos($message, '📊') !== false)) {
        try {
            $stmt = $conn->prepare("INSERT INTO system_logs (log_level, message, created_at) VALUES (?, ?, NOW())");
            $level = strpos($message, '💥') !== false ? 'error' : (strpos($message, '📊') !== false ? 'info' : 'success');
            $stmt->execute([$level, $message]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
    }
}

// Function to convert days to months and days format
function formatDaysToReadable($days) {
    $days = intval(abs($days));
    
    if ($days < 30) {
        return $days . ' يوم';
    }
    
    $months = intval($days / 30);
    $remainingDays = intval($days % 30);
    
    // Multi-line approach for HTML emails
    if ($months == 1 && $remainingDays == 0) {
        return 'شهر';
    } elseif ($months == 2 && $remainingDays == 0) {
        return 'شهرين';
    } elseif ($remainingDays == 0) {
        return $months . ' أشهر';
    } elseif ($months == 1) {
        return 'شهر<br>' . $remainingDays . ' يوم';
    } elseif ($months == 2) {
        return 'شهرين<br>' . $remainingDays . ' يوم';
    } else {
        return $months . ' أشهر<br>' . $remainingDays . ' يوم';
    }
}

function buildEmailContent($dept, $expiredLicenses, $expiringLicenses) {
    $currentDate = date('d/m/Y - H:i');
    
    $html = '
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>تنبيه رخص التراخيص</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                direction: rtl;
                text-align: right;
                margin: 0;
                padding: 0;
                background-color: #f5f5f5;
                line-height: 1.6;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: white;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .header {
                background: #8e44ad;
                color: white;
                padding: 25px 20px;
                text-align: center;
                position: relative;
            }
            .header .warning-icon {
                font-size: 28px;
                margin-bottom: 10px;
                display: block;
            }
            .header h1 {
                margin: 0;
                font-size: 22px;
                font-weight: bold;
            }
            .header .subtitle {
                margin-top: 8px;
                font-size: 14px;
                opacity: 0.9;
            }
            .content {
                padding: 25px 20px;
            }
            .report-info {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                border-right: 4px solid #3498db;
            }
            .report-info p {
                margin: 5px 0;
                font-size: 14px;
                color: #2c3e50;
            }
            .section {
                margin-bottom: 25px;
                border-radius: 5px;
                overflow: hidden;
                background-color: white;
            }
            .section-title {
                background-color: #e74c3c;
                color: white;
                padding: 12px 15px;
                font-weight: bold;
                font-size: 16px;
                display: flex;
                align-items: center;
            }
            .section-title.expiring {
                background-color: #f39c12;
            }
            .section-title .icon {
                margin-left: 10px;
                font-size: 18px;
            }
            .expired-icon {
                color: #e74c3c;
            }
            .expiring-icon {
                color: #f39c12;
            }
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 15px !important;
                background-color: white !important;
                border-radius: 5px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .table th {
                background-color: #8e44ad !important;
                color: white !important;
                padding: 12px;
                text-align: center;
                font-weight: bold;
                font-size: 14px;
            }
            .table td {
                padding: 12px !important;
                text-align: center !important;
                border-bottom: 1px solid #ecf0f1 !important;
                font-size: 13px !important;
            }
            .table tr.even {
                background-color: #f8f9fa !important;
            }
            .table tr.odd {
                background-color: white !important;
            }
            .days-expired {
                color: #e74c3c;
                font-weight: bold;
            }
            .days-remaining {
                color: #f39c12;
                font-weight: bold;
            }
            .actions {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 5px;
                margin-top: 20px;
                border-right: 4px solid #27ae60;
            }
            .actions h3 {
                margin: 0 0 15px 0;
                color: #2c3e50;
                font-size: 16px;
                display: flex;
                align-items: center;
            }
            .actions h3 .icon {
                margin-left: 10px;
                font-size: 16px;
            }
            .actions ul {
                margin: 0;
                padding-right: 20px;
            }
            .actions li {
                margin-bottom: 8px;
                color: #34495e;
                font-size: 14px;
            }
            .footer {
                text-align: center;
                padding: 20px;
                color: #7f8c8d;
                font-size: 12px;
                border-top: 1px solid #ecf0f1;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
        <div class="header">
            <div class="warning-icon">⚠️</div>
            <h1>تنبيه: حالة التراخيص</h1>
            <div class="subtitle">نظام إدارة التراخيص</div>
        </div>
        
        <div class="content">
            <div class="report-info">
                <p><strong>قسم:</strong> ' . htmlspecialchars($dept['department_name']) . '</p>
                <p><strong>المشروع:</strong> ' . htmlspecialchars($dept['project_name']) . '</p>
                <p><strong>تاريخ التقرير:</strong> ' . $currentDate . '</p>
            </div>';
    
    // Expired Licenses Section
    if (!empty($expiredLicenses)) {
        $html .= '
            <div class="section">
                <div class="section-title">
                    <span class="icon expired-icon">!</span>
                    رخص منتهية الصلاحية (' . count($expiredLicenses) . ')
                </div>
                <p style="color: #e74c3c; margin-bottom: 15px;">الرخص التالية منتهية الصلاحية ويجب تجديدها فوراً</p>
                <table class="table">
                    <thead>
                        <tr>
                            <th>فترة انتهاء الصلاحية</th>
                            <th>تاريخ الانتهاء</th>
                            <th>حامل الرخصة</th>
                            <th>رقم الرخصة</th>
                            <th>نوع الرخصة</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $rowIndex = 0;
        foreach ($expiredLicenses as $license) {
            $daysExpired = isset($license['days_diff']) ? intval(abs($license['days_diff'])) : 0;
            $formattedDays = formatDaysToReadable($daysExpired);
            $rowBgColor = ($rowIndex % 2 == 0) ? '#f8f9fa' : 'white';
            $html .= '
                        <tr style="background-color: ' . $rowBgColor . ';">
                            <td style="color: #e74c3c; font-weight: bold;">' . $formattedDays . '</td>
                            <td>' . date('d/m/Y', strtotime($license['expiration_date'])) . '</td>
                            <td>' . htmlspecialchars($license['holder_name']) . '</td>
                            <td>' . htmlspecialchars($license['license_number']) . '</td>
                            <td>' . htmlspecialchars($license['license_type']) . '</td>
                        </tr>';
            $rowIndex++;
        }
        
        $html .= '
                    </tbody>
                </table>
            </div>';
    }
    
    // Expiring Licenses Section
    if (!empty($expiringLicenses)) {
        $html .= '
            <div class="section">
                <div class="section-title expiring">
                    <span class="icon expiring-icon">⏰</span>
                    رخص تنتهي قريباً (' . count($expiringLicenses) . ')
                </div>
                <p style="color: #f39c12; margin-bottom: 15px;">الرخص التالية ستنتهي خلال الـ 30 يوم القادمة</p>
                <table class="table">
                    <thead>
                        <tr>
                            <th>أيام متبقية</th>
                            <th>تاريخ الانتهاء</th>
                            <th>حامل الرخصة</th>
                            <th>رقم الرخصة</th>
                            <th>نوع الرخصة</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $rowIndex = 0;
        foreach ($expiringLicenses as $license) {
            $remainingDays = isset($license['days_diff']) ? intval($license['days_diff']) : 0;
            $formattedRemainingDays = formatDaysToReadable($remainingDays);
            $rowBgColor = ($rowIndex % 2 == 0) ? '#f8f9fa' : 'white';
            $html .= '
                        <tr style="background-color: ' . $rowBgColor . ';">
                            <td style="color: #f39c12; font-weight: bold;">' . $formattedRemainingDays . '</td>
                            <td>' . date('d/m/Y', strtotime($license['expiration_date'])) . '</td>
                            <td>' . htmlspecialchars($license['holder_name']) . '</td>
                            <td>' . htmlspecialchars($license['license_number']) . '</td>
                            <td>' . htmlspecialchars($license['license_type']) . '</td>
                        </tr>';
            $rowIndex++;
        }
        
        $html .= '
                    </tbody>
                </table>
            </div>';
    }
    
    $html .= '
            <div class="actions">
                <h3><span class="icon">📋</span> الإجراءات المطلوبة</h3>
                <ul>
                    <li><strong>للرخص المنتهية:</strong> يجب تجديدها فوراً لتجنب المخالفات القانونية.</li>
                    <li><strong>للرخص التي تنتهي قريباً:</strong> ابدأ إجراءات التجديد الآن.</li>
                    <li><strong>للحصول على المساعدة في إجراءات التجديد:</strong> تواصل مع الإدارة المختصة.</li>
                </ul>
            </div>
        </div>
        
        <div class="footer">
            هذا تنبيه تلقائي من نظام إدارة التراخيص<br>
            تم إنشاؤه في ' . $currentDate . '
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

logMessage("🚀 Starting cron notifications at " . date('Y-m-d H:i:s'));

try {
    $conn = getDBConnection();
    logMessage("✅ Database connected successfully", $conn);
    
    // Get departments with emails and their expiring licenses
    $stmt = $conn->prepare("
        SELECT 
            d.department_id,
            d.department_name,
            d.department_email,
            p.project_id,
            p.project_name,
            COUNT(*) as total_licenses,
            SUM(CASE WHEN license_data.expiration_date < CURDATE() THEN 1 ELSE 0 END) as expired_count,
            SUM(CASE WHEN license_data.expiration_date >= CURDATE() AND license_data.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_count,
            GROUP_CONCAT(
                CONCAT(
                    license_data.license_type, '|',
                    CASE 
                        WHEN license_data.license_type = 'personal' THEN license_data.full_name
                        ELSE '........'
                    END, '|',
                    CASE 
                        WHEN license_data.license_type = 'personal' THEN license_data.license_number
                        ELSE license_data.car_number
                    END, '|',
                    license_data.expiration_date, '|',
                    DATEDIFF(license_data.expiration_date, CURDATE()), '|',
                    CASE 
                        WHEN license_data.license_type = 'personal' THEN 'رخصة قيادة شخصية'
                        ELSE 'رخصة مركبة'
                    END
                ) 
                SEPARATOR '||'
            ) as license_details
        FROM departments d
        INNER JOIN projects p ON 1=1
        INNER JOIN (
            SELECT 'personal' as license_type, pl.department_id, pl.project_id, pl.full_name, pl.license_number, pl.expiration_date, NULL as car_number, NULL as vehicle_type
            FROM personal_licenses pl 
            WHERE pl.is_active = 1 
            AND pl.department_id IS NOT NULL 
            AND pl.project_id IS NOT NULL 
            AND pl.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            UNION ALL
            SELECT 'vehicle' as license_type, vl.department_id, vl.project_id, NULL as full_name, NULL as license_number, vl.expiration_date, vl.car_number, vl.vehicle_type
            FROM vehicle_licenses vl 
            WHERE vl.is_active = 1 
            AND vl.department_id IS NOT NULL 
            AND vl.project_id IS NOT NULL 
            AND vl.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ) license_data ON d.department_id = license_data.department_id AND p.project_id = license_data.project_id
        WHERE d.is_active = 1 
        AND p.is_active = 1
        AND d.department_email IS NOT NULL
        AND d.department_email != ''
        GROUP BY d.department_id, d.department_name, d.department_email, p.project_id, p.project_name
        HAVING total_licenses > 0
        ORDER BY d.department_name, p.project_name
    ");
    
    $stmt->execute();
    $departments = $stmt->fetchAll();
    
    logMessage("📧 Found " . count($departments) . " departments with notifications", $conn);
    
    $sent_count = 0;
    $error_count = 0;
    
    foreach ($departments as $dept) {
        // Only send if there are expired or expiring licenses
        if ($dept['expired_count'] == 0 && $dept['expiring_count'] == 0) {
            continue;
        }
        
        $notificationId = null;
        
        try {
            // Parse license details using updated format
            $licenseDetailsList = explode('||', $dept['license_details']);
            $expiredLicenses = [];
            $expiringLicenses = [];
            
            foreach ($licenseDetailsList as $licenseDetail) {
                $parts = explode('|', $licenseDetail);
                if (count($parts) >= 6) {
                    $licenseType = $parts[0];
                    $holderName = ($licenseType == 'personal') ? $parts[1] : 'مركبة';
                    $licenseNumber = $parts[2];
                    $expirationDate = $parts[3];
                    $daysDiff = intval($parts[4]);
                    $typeLabel = $parts[5];
                    
                    $license = [
                        'holder_name' => $holderName,
                        'license_number' => $licenseNumber,
                        'expiration_date' => $expirationDate,
                        'days_diff' => $daysDiff,
                        'license_type' => $typeLabel
                    ];
                    
                    if ($daysDiff <= 0) {
                        $expiredLicenses[] = $license;
                    } else {
                        $expiringLicenses[] = $license;
                    }
                }
            }
            
            // Build email content
            $emailContent = buildEmailContent($dept, $expiredLicenses, $expiringLicenses);
            $subject = "تنبيه: حالة التراخيص - {$dept['department_name']} - {$dept['project_name']}";
            
            // Save notification to database first
            $stmt = $conn->prepare("
                INSERT INTO email_notifications (
                    department_id, department_name, project_id, project_name, 
                    recipient_email, subject, message, total_licenses, 
                    expired_count, expiring_count, sent_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $dept['department_id'], $dept['department_name'],
                $dept['project_id'], $dept['project_name'],
                $dept['department_email'], $subject, $emailContent,
                $dept['total_licenses'], $dept['expired_count'], $dept['expiring_count']
            ]);
            
            $notificationId = $conn->lastInsertId();
            logMessage("📝 Notification saved to database (ID: $notificationId)", $conn);
            
            // Now send email
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
            $mail->addAddress($dept['department_email'], $dept['department_name']);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $emailContent;
            
            // Send email
            $mail->send();
            $sent_count++;
            
            // Update notification status to sent
            $updateStmt = $conn->prepare("
                UPDATE email_notifications 
                SET sent_status = 'sent', sent_at = NOW() 
                WHERE notification_id = ?
            ");
            $updateStmt->execute([$notificationId]);
            
            logMessage("✅ Email sent successfully to {$dept['department_name']} (ID: $notificationId)", $conn);
            
        } catch (Exception $e) {
            $error_count++;
            $errorMessage = $e->getMessage();
            logMessage("💥 Email error for {$dept['department_name']}: $errorMessage", $conn);
            
            // Update notification status to failed if we have ID
            if ($notificationId) {
                try {
                    $updateStmt = $conn->prepare("
                        UPDATE email_notifications 
                        SET sent_status = 'failed', error_message = ? 
                        WHERE notification_id = ?
                    ");
                    $updateStmt->execute([$errorMessage, $notificationId]);
                } catch (Exception $updateErr) {
                    // Silent fail for update
                }
            }
        }
    }
    
    logMessage("📊 Final results: {$sent_count} sent, {$error_count} errors", $conn);
    logMessage("🏁 Cron job completed successfully", $conn);
    
} catch (Exception $e) {
    logMessage("💥 Fatal error: " . $e->getMessage());
    exit(1);
}
?> 