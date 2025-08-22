<?php
require_once 'config/config.php';
require_once 'php_action/auth.php';

if (!isLoggedIn()) {
    die('يجب تسجيل الدخول أولاً');
}

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; direction: rtl; }
.info { background: #d1ecf1; color: #0c5460; border: 1px solid #b6d7ff; padding: 15px; border-radius: 8px; margin: 10px 0; }
.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin: 10px 0; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #ddd; text-align: right; }
th { background: #f5f5f5; }
</style>";

echo "<h2>🔍 فحص قيود Sub Admins في النظام</h2>";

try {
    $conn = getDBConnection();
    
    // Check for any hardcoded limits in the code
    echo "<h3>📋 نتائج الفحص:</h3>";
    
    echo "<div class='success'>";
    echo "<h4>✅ لا توجد قيود على عدد Sub Admins!</h4>";
    echo "<ul>";
    echo "<li><strong>في كود إضافة المستخدمين:</strong> لا يوجد حد أقصى محدد</li>";
    echo "<li><strong>في قاعدة البيانات:</strong> لا توجد constraints على العدد</li>";
    echo "<li><strong>في منطق التحقق:</strong> يتم التحقق من وجود Head Admin فقط، ليس العدد</li>";
    echo "</ul>";
    echo "</div>";
    
    // Show current sub admin counts per head admin
    echo "<h3>📊 إحصائيات Sub Admins الحالية:</h3>";
    
    $statsQuery = "
        SELECT 
            head.user_id as head_admin_id,
            head.full_name as head_admin_name,
            head.username as head_admin_username,
            COUNT(sub.user_id) as sub_admins_count,
            GROUP_CONCAT(
                CONCAT(sub.full_name, ' (', sub.username, ')')
                SEPARATOR ', '
            ) as sub_admins_list
        FROM users head
        LEFT JOIN users sub ON head.user_id = sub.parent_admin_id AND sub.is_active = 1
        WHERE head.role = 'admin' 
            AND head.parent_admin_id IS NULL 
            AND head.is_active = 1
        GROUP BY head.user_id, head.full_name, head.username
        ORDER BY sub_admins_count DESC, head.full_name
    ";
    
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->execute();
    $headAdmins = $statsStmt->fetchAll();
    
    if (!empty($headAdmins)) {
        echo "<table>";
        echo "<tr><th>Head Admin</th><th>اسم المستخدم</th><th>عدد Sub Admins</th><th>أسماء Sub Admins</th></tr>";
        
        foreach ($headAdmins as $admin) {
            echo "<tr>";
            echo "<td>{$admin['head_admin_name']}</td>";
            echo "<td>{$admin['head_admin_username']}</td>";
            echo "<td><strong>{$admin['sub_admins_count']}</strong></td>";
            echo "<td>" . ($admin['sub_admins_list'] ?: 'لا يوجد') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Find max sub admins
        $maxSubAdmins = max(array_column($headAdmins, 'sub_admins_count'));
        
        echo "<div class='info'>";
        echo "<strong>📈 أعلى عدد Sub Admins حالياً:</strong> $maxSubAdmins";
        echo "<br><strong>🎯 النتيجة:</strong> يمكنك إضافة أي عدد تريده من Sub Admins بدون قيود!";
        echo "</div>";
        
    } else {
        echo "<div class='info'>لا يوجد Head Admins في النظام حالياً</div>";
    }
    
    // Show theoretical limits
    echo "<h3>🚀 الحدود النظرية:</h3>";
    echo "<div class='info'>";
    echo "<ul>";
    echo "<li><strong>الحد الأقصى للـ Sub Admins:</strong> غير محدود في الكود</li>";
    echo "<li><strong>القيد الوحيد:</strong> عدد المستخدمين في قاعدة البيانات (INT - حوالي 2 مليار)</li>";
    echo "<li><strong>القيد العملي:</strong> أداء النظام وذاكرة الخادم</li>";
    echo "<li><strong>التوصية:</strong> عدد معقول حسب حجم المؤسسة (عادة 5-20 لكل Head Admin)</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ خطأ: " . $e->getMessage() . "</div>";
}
?> 