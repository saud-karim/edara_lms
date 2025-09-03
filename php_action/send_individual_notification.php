<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// التحقق من صحة البيانات المطلوبة
if (!isset($_POST['department_id']) || !isset($_POST['project_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'معرف القسم والمشروع مطلوبان'
    ]);
    exit;
}

$departmentId = intval($_POST['department_id']);
$projectId = intval($_POST['project_id']);
$userId = $_SESSION['user_id'] ?? 0;

try {
    $conn = getDBConnection();
    
    // جلب بيانات القسم والمشروع مع الرخص المنتهية أو التي ستنتهي قريباً
    $stmt = $conn->prepare("
        SELECT 
            d.department_id,
            d.department_name,
            d.department_email,
            p.project_id,
            p.project_name,
            p.project_email,
            COUNT(*) as total_licenses,
            SUM(CASE WHEN license_data.expiration_date < CURDATE() THEN 1 ELSE 0 END) as expired_count,
            SUM(CASE WHEN license_data.expiration_date >= CURDATE() AND license_data.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_count,
            SUM(CASE 
                WHEN license_data.license_type = 'vehicle' 
                     AND license_data.inspection_year IS NOT NULL 
                     AND YEAR(license_data.expiration_date) = license_data.inspection_year 
                     AND license_data.expiration_date >= CURDATE()
                THEN 1 ELSE 0 
            END) as inspection_needed_count,
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
                    END, '|',
                    license_data.license_category_display, '|',
                    license_data.inspection_status
                )
                SEPARATOR '||'
            ) as license_details
        FROM departments d
        INNER JOIN projects p ON p.project_id = ?
        INNER JOIN (
            SELECT 'personal' as license_type, pl.department_id, pl.project_id, pl.full_name, pl.license_number, pl.expiration_date, NULL as car_number, NULL as inspection_year,
                   'رخصة' as license_category_display,
                   '......' as inspection_status
            FROM personal_licenses pl 
            WHERE pl.is_active = 1 AND pl.department_id = ? AND pl.project_id = ?
            AND (pl.expiration_date < CURDATE() OR pl.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
            
            UNION ALL
            
            SELECT 'vehicle' as license_type, vl.department_id, vl.project_id, NULL as full_name, NULL as license_number, vl.expiration_date, vl.car_number, vl.inspection_year,
                   CASE 
                       WHEN vl.license_category = 'رخصة مركبة' THEN 'رخصة'
                       WHEN vl.license_category = 'تصريح مركبة' THEN 'تصريح'
                       ELSE vl.license_category
                   END as license_category_display,
                   CASE 
                       WHEN vl.inspection_year IS NOT NULL AND YEAR(vl.expiration_date) = vl.inspection_year THEN 'فحص'
                       ELSE '......'
                   END as inspection_status
            FROM vehicle_licenses vl 
            WHERE vl.is_active = 1 AND vl.department_id = ? AND vl.project_id = ?
            AND (vl.expiration_date < CURDATE() OR vl.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                 OR (vl.inspection_year IS NOT NULL AND YEAR(vl.expiration_date) = vl.inspection_year AND vl.expiration_date >= CURDATE()))
        ) as license_data ON license_data.department_id = d.department_id
        WHERE d.department_id = ?
        GROUP BY d.department_id, d.department_name, d.department_email, p.project_id, p.project_name, p.project_email
        HAVING total_licenses > 0
    ");
    
    $stmt->execute([$projectId, $departmentId, $projectId, $departmentId, $projectId, $departmentId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        echo json_encode([
            'success' => false, 
            'message' => 'لم يتم العثور على رخص منتهية أو ستنتهي قريباً لهذا القسم والمشروع'
        ]);
        exit;
    }
    
    // التحقق من وجود إيميلات للإرسال
    $hasValidEmails = false;
    if (!empty($data['department_email']) && filter_var($data['department_email'], FILTER_VALIDATE_EMAIL)) {
        $hasValidEmails = true;
    }
    if (!empty($data['project_email']) && filter_var($data['project_email'], FILTER_VALIDATE_EMAIL)) {
        $hasValidEmails = true;
    }
    
    if (!$hasValidEmails) {
        echo json_encode([
            'success' => false, 
            'message' => 'لا توجد عناوين بريد إلكتروني صحيحة للإرسال'
        ]);
        exit;
    }
    
    // استخدام نفس دالة بناء المحتوى من النظام الموجود (تماماً نفس التصميم)
    $licenseDetails = explode('||', $data['license_details']);
    $expiredLicenses = [];
    $expiringLicenses = [];
    $inspectionNeededLicenses = [];
    
    foreach ($licenseDetails as $license) {
        $parts = explode('|', $license);
        if (count($parts) >= 8) {
            $licenseType = $parts[0];
            $holderName = $parts[1];
            $licenseNumber = $parts[2];
            $expirationDate = $parts[3];
            $daysDiff = intval($parts[4]);
            $licenseTypeText = $parts[5];
            $licenseCategoryDisplay = $parts[6];
            $inspectionStatus = $parts[7];
            
            $licenseInfo = [
                'holder_name' => $holderName,
                'license_number' => $licenseNumber,
                'expiration_date' => $expirationDate,
                'days_diff' => $daysDiff,
                'license_type' => $licenseTypeText,
                'license_category_display' => $licenseCategoryDisplay,
                'inspection_status' => $inspectionStatus
            ];
            
            if ($daysDiff < 0) {
                $expiredLicenses[] = $licenseInfo;
            } elseif ($daysDiff <= 30) {
                $expiringLicenses[] = $licenseInfo;
            } elseif ($inspectionStatus === 'فحص' && $daysDiff > 30) {
                $inspectionNeededLicenses[] = $licenseInfo;
            }
        }
    }
    
    // استخدام نفس buildEmailContent من النظام الموجود  
    $subject = "تنبيه: حالة التراخيص - {$data['department_name']} - {$data['project_name']}";
    $emailContent = buildIndividualEmailContent($data, $expiredLicenses, $expiringLicenses, $inspectionNeededLicenses);
    
    // جلب إيميلات CC
    function getCCAddresses($conn) {
        try {
            $stmt = $conn->prepare("SELECT setting_value FROM email_settings WHERE setting_name = 'cc_emails' AND is_active = 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['setting_value'])) {
                $emails = array_map('trim', explode(',', $result['setting_value']));
                return array_filter($emails, function($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
            }
        } catch (Exception $e) {
            error_log("Error fetching CC addresses: " . $e->getMessage());
        }
        return [];
    }
    
    // إعداد PHPMailer (نفس إعدادات النظام الموجود)
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->SMTPDebug = 0;
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'edarasec@gmail.com';
    $mail->Password = 'vxwgihbbcuhvmimc';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    
    $mail->setFrom('shehab.sayed@edaraproperty.net', 'نظام إدارة التراخيص');
    $mail->addReplyTo('shehab.sayed@edaraproperty.net', 'نظام إدارة التراخيص');
    
    // حفظ الإشعار في قاعدة البيانات أولاً كـ pending
    $stmt = $conn->prepare("
        INSERT INTO email_notifications 
        (department_id, department_name, project_id, project_name, recipient_email, subject, message, total_licenses, expired_count, expiring_count, sent_status, notification_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'individual_department')
    ");
    
    $stmt->execute([
        $data['department_id'], 
        $data['department_name'],
        $data['project_id'], 
        $data['project_name'],
        $data['department_email'], 
        $subject,
        $emailContent, 
        $data['total_licenses'], 
        $data['expired_count'], 
        $data['expiring_count']
    ]);
    
    $notificationId = $conn->lastInsertId();
    
    // إضافة المستلمين الأساسيين (نفس طريقة النظام الموجود)
    $recipientCount = 0;
    
    // المرسل إليه الأساسي: الإدارة
    $mail->addAddress($data['department_email'], $data['department_name']);
    $recipientCount++;
    
    // إضافة المشروع إذا له إيميل
    if (!empty($data['project_email']) && filter_var($data['project_email'], FILTER_VALIDATE_EMAIL)) {
        $mail->addAddress($data['project_email'], "مدير مشروع {$data['project_name']}");
        $recipientCount++;
    }
    
    // إضافة CC لكل إيميل
    $ccEmails = getCCAddresses($conn);
    $ccCount = 0;
    foreach ($ccEmails as $ccEmail) {
        if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addCC($ccEmail);
            $ccCount++;
        }
    }
    
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $emailContent;
    
    // إرسال الإيميل (نفس طريقة النظام الموجود)
    $mail->send();
    
    // تحديث حالة الإشعار إلى sent (نفس طريقة النظام الموجود)
    $updateStmt = $conn->prepare("
        UPDATE email_notifications 
        SET sent_status = 'sent', sent_at = NOW() 
        WHERE notification_id = ?
    ");
    $updateStmt->execute([$notificationId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'تم إرسال الإشعار بنجاح',
        'details' => [
            'department' => $data['department_name'],
            'project' => $data['project_name'],
            'total_licenses' => $data['total_licenses'],
            'expired_count' => $data['expired_count'],
            'expiring_count' => $data['expiring_count'],
            'inspection_needed' => count($inspectionNeededLicenses),
            'recipients' => $recipientCount,
            'cc_count' => $ccCount,
            'notification_id' => $notificationId
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error sending individual notification: " . $e->getMessage());
    
    // تحديث حالة الإشعار إلى failed (نفس طريقة النظام الموجود)
    if (isset($notificationId)) {
        try {
            $updateStmt = $conn->prepare("
                UPDATE email_notifications 
                SET sent_status = 'failed', error_message = ? 
                WHERE notification_id = ?
            ");
            $updateStmt->execute([$e->getMessage(), $notificationId]);
        } catch (Exception $dbError) {
            error_log("Error updating failed notification status: " . $dbError->getMessage());
        }
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ أثناء إرسال الإشعار: ' . $e->getMessage()
    ]);
}

// دالة formatDaysToReadable - نفس النسخة من النظام الموجود
function formatDaysToReadableIndividual($days) {
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

// دالة buildIndividualEmailContent - نفس النسخة من النظام الموجود تماماً
function buildIndividualEmailContent($dept, $expiredLicenses, $expiringLicenses, $inspectionNeededLicenses = []) {
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
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">الفئة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">الفحص</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $rowIndex = 0;
        foreach ($expiredLicenses as $license) {
            $daysExpired = isset($license['days_diff']) ? intval(abs($license['days_diff'])) : 0;
            $formattedDays = formatDaysToReadableIndividual($daysExpired);
            $rowBgColor = ($rowIndex % 2 == 0) ? '#f8f9fa' : 'white';
            $html .= '
                        <tr style="background-color: ' . $rowBgColor . ';">
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px; color: #e74c3c; font-weight: bold;">' . $formattedDays . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . date('d/m/Y', strtotime($license['expiration_date'])) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['holder_name']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_number']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_type']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_category_display']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['inspection_status']) . '</td>
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
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">الفئة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">الفحص</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $rowIndex = 0;
        foreach ($expiringLicenses as $license) {
            $rowClass = ($rowIndex % 2 == 0) ? 'even' : 'odd';
            $remainingDays = isset($license['days_diff']) ? intval($license['days_diff']) : 0;
            $formattedRemainingDays = formatDaysToReadableIndividual($remainingDays);
            $rowBgColor = ($rowIndex % 2 == 0) ? '#f8f9fa' : 'white';
            $html .= '
                        <tr style="background-color: ' . $rowBgColor . ';">
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px; color: #f39c12; font-weight: bold;">' . $formattedRemainingDays . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . date('d/m/Y', strtotime($license['expiration_date'])) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['holder_name']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_number']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_type']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_category_display']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['inspection_status']) . '</td>
                        </tr>';
            $rowIndex++;
        }
        
        $html .= '
                    </tbody>
                </table>
            </div>';
    }
    
    // Inspection Needed Licenses Section
    if (!empty($inspectionNeededLicenses)) {
        $html .= '
            <div class="section">
                <div class="section-title">
                    <span class="icon expiring-icon" style="background-color: #2196F3;">🔍</span>
                    رخص تحتاج فحص (' . count($inspectionNeededLicenses) . ')
                </div>
                <p style="color: #2196F3; margin-bottom: 15px;">الرخص التالية تحتاج فحص لأن سنة الفحص تطابق سنة انتهاء الرخصة</p>
                <table class="table" style="width: 100%; border-collapse: collapse; background-color: white; border-radius: 5px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <thead>
                        <tr>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">متبقي حتى الانتهاء</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">تاريخ الانتهاء</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">حامل الرخصة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">رقم الرخصة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">نوع الرخصة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">الفئة</th>
                            <th style="background-color: #8e44ad; background: #8e44ad; color: white; padding: 12px; text-align: center; font-weight: bold; font-size: 14px;">حالة الفحص</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $rowIndex = 0;
        foreach ($inspectionNeededLicenses as $license) {
            $rowClass = ($rowIndex % 2 == 0) ? 'even' : 'odd';
            $remainingDays = isset($license['days_diff']) ? intval($license['days_diff']) : 0;
            $formattedRemainingDays = formatDaysToReadableIndividual($remainingDays);
            $rowBgColor = ($rowIndex % 2 == 0) ? '#f8f9fa' : 'white';
            $html .= '
                        <tr style="background-color: ' . $rowBgColor . ';">
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px; color: #2196F3; font-weight: bold;">' . $formattedRemainingDays . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . date('d/m/Y', strtotime($license['expiration_date'])) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['holder_name']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_number']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_type']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px;">' . htmlspecialchars($license['license_category_display']) . '</td>
                            <td style="padding: 12px; text-align: center; border-bottom: 1px solid #ecf0f1; font-size: 13px; color: #ff9800; font-weight: bold;">يحتاج فحص</td>
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
                    <li><strong>للرخص التي تحتاج فحص:</strong> أجرِ فحص المركبة قبل انتهاء الرخصة.</li>
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