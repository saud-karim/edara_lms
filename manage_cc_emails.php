<?php
$pageTitle = 'إدارة إيميلات CC';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Only allow super admin
requireRole('super_admin');

$success = '';
$error = '';

if ($_POST && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    if (isset($_POST['update_cc'])) {
        $ccEmails = trim($_POST['cc_emails']);
        
        // Validate emails
        if (!empty($ccEmails)) {
            $emailList = array_map('trim', explode(',', $ccEmails));
            $invalidEmails = [];
            
            foreach ($emailList as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $invalidEmails[] = $email;
                }
            }
            
            if (!empty($invalidEmails)) {
                $error = "إيميلات غير صحيحة: " . implode(', ', $invalidEmails);
            } else {
                // All emails are valid
                try {
                    $conn = getDBConnection();
                    
                    // Create table if it doesn't exist
                    $conn->exec("
                        CREATE TABLE IF NOT EXISTS email_settings (
                            setting_name VARCHAR(100) PRIMARY KEY,
                            setting_value TEXT NOT NULL,
                            description VARCHAR(255),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        )
                    ");
                    
                    // Update or insert CC emails
                    $stmt = $conn->prepare("
                        INSERT INTO email_settings (setting_name, setting_value, description) 
                        VALUES ('cc_emails', ?, 'إيميلات النسخة الكربونية للإشعارات')
                        ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$ccEmails]);
                    
                    $success = "تم تحديث " . count($emailList) . " إيميل CC بنجاح!";
                    setMessage($success, 'success');
                    header('Location: manage_cc_emails.php');
                    exit;
                } catch (Exception $e) {
                    $error = "خطأ في حفظ البيانات: " . $e->getMessage();
                }
            }
        } else {
            // Empty emails - clear CC
            try {
                $conn = getDBConnection();
                $stmt = $conn->prepare("DELETE FROM email_settings WHERE setting_name = 'cc_emails'");
                $stmt->execute();
                
                $success = "تم مسح جميع إيميلات CC!";
                setMessage($success, 'success');
                header('Location: manage_cc_emails.php');
                exit;
            } catch (Exception $e) {
                $error = "خطأ في مسح البيانات: " . $e->getMessage();
            }
        }
    }
}

// Get current CC emails and stats
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT setting_value, updated_at FROM email_settings WHERE setting_name = 'cc_emails' LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch();
    $currentCC = $settings ? $settings['setting_value'] : '';
    $lastUpdated = $settings ? $settings['updated_at'] : null;
    
    // Count emails
    $emailCount = 0;
    if ($currentCC) {
        $emailList = array_map('trim', explode(',', $currentCC));
        $emailCount = count(array_filter($emailList));
    }
} catch (Exception $e) {
    $currentCC = '';
    $emailCount = 0;
    $lastUpdated = null;
}

include 'includes/header.php';
?>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="page-header">
                <h2>
                    <i class="glyphicon glyphicon-envelope"></i> إدارة إيميلات النسخة الكربونية (CC)
                    <small>تحديد الإيميلات التي ستحصل على نسخة من جميع الإشعارات</small>
                </h2>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Statistics Cards -->
        <div class="col-md-4 col-sm-6">
            <div class="panel panel-info">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="glyphicon glyphicon-envelope" style="font-size: 5em; color: #5bc0de;"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $emailCount; ?></div>
                            <div>إيميلات CC نشطة</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6">
            <div class="panel panel-warning">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="glyphicon glyphicon-time" style="font-size: 5em; color: #f0ad4e;"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">
                                <?php echo $lastUpdated ? date('d/m/Y', strtotime($lastUpdated)) : '--'; ?>
                            </div>
                            <div>آخر تحديث</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-12">
            <div class="panel panel-success">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="glyphicon glyphicon-ok-circle" style="font-size: 5em; color: #5cb85c;"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">نشط</div>
                            <div>حالة النظام</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-edit"></i> تحديث إيميلات CC
                    </h4>
                </div>
                <div class="panel-body">
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="glyphicon glyphicon-exclamation-sign"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="ccForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-group">
                            <label for="cc_emails">
                                <i class="glyphicon glyphicon-envelope"></i> إيميلات CC:
                            </label>
                            <textarea 
                                name="cc_emails" 
                                id="cc_emails" 
                                class="form-control" 
                                rows="4" 
                                placeholder="أدخل الإيميلات مفصولة بفاصلة&#10;مثال: admin@company.com, manager@company.com"
                            ><?php echo htmlspecialchars($currentCC); ?></textarea>
                            <div class="help-block">
                                <i class="glyphicon glyphicon-info-sign"></i>
                                أدخل عدة إيميلات مفصولة بفاصلة. يمكن ترك الحقل فارغاً لإلغاء جميع إيميلات CC.
                            </div>
                        </div>
                        
                        <div class="preview-section" style="background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #e9ecef; margin-bottom: 20px;">
                            <h5 style="margin-top: 0;">
                                <i class="glyphicon glyphicon-eye-open"></i> معاينة الإيميلات:
                            </h5>
                            <div id="email-preview">جاري التحميل...</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_cc" class="btn btn-success btn-lg">
                                <i class="glyphicon glyphicon-floppy-disk"></i> حفظ التحديثات
                            </button>
                            
                            <button type="button" id="clearEmails" class="btn btn-warning">
                                <i class="glyphicon glyphicon-trash"></i> مسح الكل
                            </button>
                            
                            <a href="email_notifications.php" class="btn btn-default">
                                <i class="glyphicon glyphicon-arrow-right"></i> العودة للإشعارات
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-question-sign"></i> معلومات مهمة
                    </h4>
                </div>
                <div class="panel-body">
                    <div class="alert alert-info" style="margin-bottom: 15px;">
                        <h5 style="margin-top: 0;">🎯 كيف يعمل النظام:</h5>
                        <ul style="margin: 10px 0; padding-right: 20px;">
                            <li>ستحصل إيميلات CC على نسخة من <strong>كل إشعار</strong></li>
                            <li>كل إشعار للإدارات والمشاريع سيتضمن CC</li>
                            <li>لا يتم إرسال إيميلات غير صحيحة</li>
                            <li>يمكن إضافة عدد غير محدود من الإيميلات</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h5 style="margin-top: 0;">⚠️ تنبيهات:</h5>
                        <ul style="margin: 10px 0; padding-right: 20px;">
                            <li>تأكد من صحة الإيميلات قبل الحفظ</li>
                            <li>سيتم التحقق من الإيميلات تلقائياً</li>
                            <li>الإيميلات المكررة سيتم تجاهلها</li>
                        </ul>
                    </div>
                    
                    <?php if ($emailCount > 0): ?>
                    <div class="alert alert-success">
                        <h5 style="margin-top: 0;">✅ الحالة الحالية:</h5>
                        <p style="margin: 5px 0;"><strong><?php echo $emailCount; ?></strong> إيميل نشط</p>
                        <?php if ($lastUpdated): ?>
                        <p style="margin: 5px 0; font-size: 0.9em;">
                            آخر تحديث: <?php echo date('d/m/Y H:i', strtotime($lastUpdated)); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.huge {
    font-size: 40px;
    font-weight: bold;
    line-height: 1;
}

.panel-body .row {
    align-items: center;
}

.preview-section {
    max-height: 200px;
    overflow-y: auto;
}

.form-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.form-actions .btn {
    margin-left: 10px;
}

.email-item {
    display: inline-block;
    margin: 3px;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.9em;
}

.email-valid {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.email-invalid {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

@media (max-width: 768px) {
    .huge {
        font-size: 30px;
    }
    
    .form-actions .btn {
        display: block;
        width: 100%;
        margin: 5px 0;
    }
}
</style>

<script>
$(document).ready(function() {
    
    function updatePreview() {
        const textarea = document.getElementById('cc_emails');
        const emails = textarea.value.split(',').map(email => email.trim()).filter(email => email);
        const preview = document.getElementById('email-preview');
        
        if (emails.length === 0) {
            preview.innerHTML = '<div class="text-muted"><em>لا توجد إيميلات محددة</em></div>';
            return;
        }
        
        // Remove duplicates
        const uniqueEmails = [...new Set(emails)];
        
        let html = '<div style="line-height: 1.8;">';
        let validCount = 0;
        let invalidCount = 0;
        
        uniqueEmails.forEach((email, index) => {
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            const icon = isValid ? '✅' : '❌';
            const cssClass = isValid ? 'email-valid' : 'email-invalid';
            
            if (isValid) validCount++;
            else invalidCount++;
            
            html += `<span class="email-item ${cssClass}">${icon} ${email}</span>`;
            
            if ((index + 1) % 3 === 0 && index < uniqueEmails.length - 1) {
                html += '<br>';
            }
        });
        
        html += '</div>';
        html += `<div class="text-info" style="margin-top: 10px; font-size: 0.9em;">`;
        html += `<strong>المجموع:</strong> ${uniqueEmails.length} | `;
        html += `<span class="text-success">صحيح: ${validCount}</span> | `;
        html += `<span class="text-danger">خطأ: ${invalidCount}</span>`;
        html += `</div>`;
        
        preview.innerHTML = html;
    }
    
    // Update preview on input
    $('#cc_emails').on('input', updatePreview);
    
    // Initial preview
    updatePreview();
    
    // Clear emails button
    $('#clearEmails').click(function() {
        if (confirm('هل أنت متأكد من مسح جميع إيميلات CC؟')) {
            $('#cc_emails').val('').trigger('input');
        }
    });
    
    // Form validation
    $('#ccForm').submit(function(e) {
        const emails = $('#cc_emails').val().trim();
        
        if (emails) {
            const emailList = emails.split(',').map(email => email.trim());
            const invalidEmails = emailList.filter(email => 
                email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
            );
            
            if (invalidEmails.length > 0) {
                e.preventDefault();
                alert('يوجد إيميلات غير صحيحة:\n' + invalidEmails.join('\n'));
                return false;
            }
        }
        
        return true;
    });
    
});
</script>

<?php include 'includes/footer.php'; ?> 