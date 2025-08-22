<?php
session_start();
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

$debug_info = [];
$debug_info[] = "🚀 Starting new notification system at " . date('Y-m-d H:i:s');

try {
    $conn = getDBConnection();
    $debug_info[] = "✅ Database connected successfully";
    
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
            WHERE pl.is_active = 1 AND pl.department_id IS NOT NULL AND pl.project_id IS NOT NULL
            UNION ALL
            SELECT 'vehicle' as license_type, vl.department_id, vl.project_id, NULL as full_name, NULL as license_number, vl.expiration_date, vl.car_number, vl.vehicle_type
            FROM vehicle_licenses vl 
            WHERE vl.is_active = 1 AND vl.department_id IS NOT NULL AND vl.project_id IS NOT NULL
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
    
    $debug_info[] = "📧 Found " . count($departments) . " departments with notifications";
    
    $sent_count = 0;
    $error_count = 0;
    
    foreach ($departments as $dept) {
        // Only send if there are expired or expiring licenses
        if ($dept['expired_count'] == 0 && $dept['expiring_count'] == 0) {
            continue;
        }
        
        $notificationId = null;
        
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            
            // Use the same SMTP settings as the working example
            $mail->isSMTP();
            $mail->SMTPDebug = 0; // Set to 0 for production
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'edarasec@gmail.com';  // Same as working example
            $mail->Password = 'vxwgihbbcuhvmimc';    // Same as working example
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            
            $debug_info[] = "📮 SMTP configured for: {$dept['department_name']}";
            
            // Set sender (same as working example)
            $mail->setFrom('shehab.sayed@edaraproperty.net', 'نظام إدارة التراخيص');
            $mail->addReplyTo('shehab.sayed@edaraproperty.net', 'نظام إدارة التراخيص');
            
            // Set recipient
            $mail->addAddress($dept['department_email'], $dept['department_name']);
            $debug_info[] = "📬 Recipient added: {$dept['department_email']}";
            
            // Email content
            $mail->isHTML(true);
            $mail->Subject = "تنبيه: حالة التراخيص - {$dept['department_name']} - {$dept['project_name']}";
            
            // Parse license details
            $licenseDetails = explode('||', $dept['license_details']);
            $expiredLicenses = [];
            $expiringLicenses = [];
            
            foreach ($licenseDetails as $detail) {
                $parts = explode('|', $detail);
                if (count($parts) >= 6) {
                    $licenseType = $parts[0];
                    $holderName = $parts[1];
                    $licenseNumber = $parts[2];
                    $expirationDate = $parts[3];
                    $daysDiff = (int)$parts[4];
                    $licenseTypeText = $parts[5];
                    
                    $licenseInfo = [
                        'holder_name' => $holderName,
                        'license_number' => $licenseNumber,
                        'expiration_date' => $expirationDate,
                        'days_diff' => $daysDiff,
                        'license_type' => $licenseTypeText
                    ];
                    
                    if ($daysDiff < 0) {
                        $expiredLicenses[] = $licenseInfo;
                    } elseif ($daysDiff <= 30) {
                        $expiringLicenses[] = $licenseInfo;
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
            $debug_info[] = "📝 Notification saved to database (ID: $notificationId)";
            
            $mail->Body = $emailContent;
            $debug_info[] = "✉️ Email content prepared for {$dept['department_name']}";
            
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
            
            $debug_info[] = "✅ Email sent successfully to {$dept['department_name']} (ID: $notificationId)";
            
        } catch (Exception $e) {
            $error_count++;
            $errorMessage = $e->getMessage();
            $debug_info[] = "💥 Email error for {$dept['department_name']}: $errorMessage";
            
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
    
    $debug_info[] = "📊 Final results: {$sent_count} sent, {$error_count} errors";
    
    echo json_encode([
        'success' => $error_count === 0,
        'message' => "تم إرسال {$sent_count} إيميل، مع {$error_count} أخطاء",
        'debug_info' => $debug_info,
        'sent_count' => $sent_count,
        'error_count' => $error_count
    ]);
    
} catch (Exception $e) {
    $debug_info[] = "💥 Fatal error: " . $e->getMessage();
    echo json_encode([
        'success' => false,
        'message' => 'خطأ عام: ' . $e->getMessage(),
        'debug_info' => $debug_info
    ]);
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
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="x-apple-disable-message-reformatting">
        <title>تنبيه: حالة التراخيص</title>
        <!--[if mso]>
        <noscript>
            <xml>
                <o:OfficeDocumentSettings>
                    <o:AllowPNG/>
                    <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
            </xml>
        </noscript>
        <![endif]-->
        <style>
            body {
                font-family: Arial, sans-serif !important;
                margin: 0 !important;
                padding: 0 !important;
                background-color: #f5f5f5 !important;
                direction: rtl !important;
                -webkit-text-size-adjust: 100% !important;
                -ms-text-size-adjust: 100% !important;
            }
            .header {
                background-color: #2c3e50 !important;
                background: #2c3e50;
                color: white !important;
                padding: 20px !important;
                text-align: center !important;
                position: relative;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: bold;
            }
            .header .subtitle {
                margin-top: 5px;
                font-size: 14px;
                opacity: 0.9;
            }
            .warning-icon {
                position: absolute;
                left: 20px;
                top: 50%;
                transform: translateY(-50%);
                width: 30px;
                height: 30px;
                background-color: #e74c3c;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
            }
            .content {
                background-color: white !important;
                margin: 20px !important;
                padding: 20px !important;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                mso-table-lspace: 0pt !important;
                mso-table-rspace: 0pt !important;
            }
            .report-info {
                margin-bottom: 20px;
                padding: 15px;
                background-color: #f8f9fa;
                border-radius: 5px;
                border-right: 4px solid #3498db;
            }
            .report-info p {
                margin: 5px 0;
                font-size: 14px;
                color: #2c3e50;
            }
            .section {
                margin-bottom: 30px;
            }
            .section-title {
                font-size: 20px;
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
            }
            .section-title .icon {
                margin-left: 10px;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                color: white;
            }
            .expired-icon {
                background-color: #e74c3c;
            }
            .expiring-icon {
                background-color: #f39c12;
            }
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 15px !important;
                background-color: white !important;
                border-radius: 5px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                mso-table-lspace: 0pt !important;
                mso-table-rspace: 0pt !important;
                border-spacing: 0 !important;
            }
            .table th {
                background-color: #8e44ad !important;
                background: #8e44ad;
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
                mso-table-lspace: 0pt !important;
                mso-table-rspace: 0pt !important;
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
        <div style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #f5f5f5;">
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
                <table class="table" style="width: 100%; border-collapse: collapse; background-color: white; border-radius: 5px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <thead>
                        <tr>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">فترة انتهاء الصلاحية</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">تاريخ الانتهاء</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">حامل الرخصة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">رقم الرخصة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">نوع الرخصة</th>
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
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px; color: #e74c3c; font-weight: bold;">' . $formattedDays . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . date('d/m/Y', strtotime($license['expiration_date'])) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['holder_name']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_number']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_type']) . '</td>
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
                <div class="section-title">
                    <span class="icon expiring-icon">⏰</span>
                    رخص تنتهي قريباً (' . count($expiringLicenses) . ')
                </div>
                <p style="color: #f39c12; margin-bottom: 15px;">الرخص التالية ستنتهي خلال الـ 30 يوم القادمة</p>
                <table class="table" style="width: 100%; border-collapse: collapse; background-color: white; border-radius: 5px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <thead>
                        <tr>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">أيام متبقية</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">تاريخ الانتهاء</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">حامل الرخصة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">رقم الرخصة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">نوع الرخصة</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $rowIndex = 0;
        foreach ($expiringLicenses as $license) {
            $rowClass = ($rowIndex % 2 == 0) ? 'even' : 'odd';
            $remainingDays = isset($license['days_diff']) ? intval($license['days_diff']) : 0;
            $formattedRemainingDays = formatDaysToReadable($remainingDays);
            $rowBgColor = ($rowIndex % 2 == 0) ? '#f8f9fa' : 'white';
            $html .= '
                        <tr style="background-color: ' . $rowBgColor . ';">
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px; color: #f39c12; font-weight: bold;">' . $formattedRemainingDays . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . date('d/m/Y', strtotime($license['expiration_date'])) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['holder_name']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_number']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_type']) . '</td>
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
?> 