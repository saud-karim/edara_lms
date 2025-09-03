<?php
session_start();
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Only allow super admin
requireRole('super_admin');

$message = '';

if ($_POST) {
    if (isset($_POST['update_cc'])) {
        $ccEmails = trim($_POST['cc_emails']);
        
        try {
            $conn = getDBConnection();
            
            // Update or insert CC emails
            $stmt = $conn->prepare("
                INSERT INTO email_settings (setting_name, setting_value, description) 
                VALUES ('cc_emails', ?, 'إيميلات النسخة الكربونية للإشعارات')
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$ccEmails]);
            
            $message = "✅ تم تحديث إيميلات CC بنجاح!";
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
        }
    }
}

// Get current CC emails
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM email_settings WHERE setting_name = 'cc_emails' LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch();
    $currentCC = $settings ? $settings['setting_value'] : '';
} catch (Exception $e) {
    $currentCC = '';
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة إيميلات CC</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; direction: rtl; }
        .container { max-width: 800px; margin: 50px auto; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .alert { margin-bottom: 20px; }
        .help-text { font-size: 0.9em; color: #666; margin-top: 5px; }
        .preview { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4>📧 إدارة إيميلات النسخة الكربونية (CC)</h4>
        </div>
        <div class="card-body">
            
            <?php if ($message): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="cc_emails">إيميلات CC:</label>
                    <textarea 
                        name="cc_emails" 
                        id="cc_emails" 
                        class="form-control" 
                        rows="3" 
                        placeholder="أدخل الإيميلات مفصولة بفاصلة"
                        required
                    ><?php echo htmlspecialchars($currentCC); ?></textarea>
                    <div class="help-text">
                        📝 أدخل عدة إيميلات مفصولة بفاصلة<br>
                        مثال: admin@company.com, manager@company.com, supervisor@company.com
                    </div>
                </div>
                
                <div class="preview">
                    <h6>👁️ معاينة الإيميلات:</h6>
                    <div id="email-preview"></div>
                </div>
                
                <button type="submit" name="update_cc" class="btn btn-success">
                    💾 حفظ التحديثات
                </button>
                
                <a href="email_notifications.php" class="btn btn-secondary">
                    ↩️ العودة للإشعارات
                </a>
            </form>
            
            <hr>
            
            <div class="alert alert-info">
                <h6>ℹ️ معلومات مهمة:</h6>
                <ul>
                    <li>ستحصل إيميلات CC على نسخة من <strong>كل إشعار</strong> يتم إرساله</li>
                    <li>كل إشعار منفصل للإدارات والمشاريع سيتضمن CC</li>
                    <li>لا تتم إضافة إيميلات غير صحيحة للـ CC تلقائياً</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('cc_emails').addEventListener('input', function() {
    const emails = this.value.split(',').map(email => email.trim()).filter(email => email);
    const preview = document.getElementById('email-preview');
    
    if (emails.length === 0) {
        preview.innerHTML = '<em>لا توجد إيميلات</em>';
        return;
    }
    
    let html = '';
    emails.forEach(email => {
        const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        const icon = isValid ? '✅' : '❌';
        const textClass = isValid ? 'text-success' : 'text-danger';
        html += `<span class="${textClass}">${icon} ${email}</span><br>`;
    });
    
    preview.innerHTML = html;
});

// Trigger on page load
document.getElementById('cc_emails').dispatchEvent(new Event('input'));
</script>

</body>
</html> 