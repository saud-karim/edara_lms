<?php
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectTo('dashboard.php');
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.';
    } else {
        if (loginUser($username, $password)) {
            // Successful login - redirect immediately
            redirectTo('dashboard.php');
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        }
    }
}

// Check for timeout message
if (isset($_GET['timeout'])) {
    $error = 'انتهت صلاحية جلستك. يرجى تسجيل الدخول مرة أخرى.';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة التراخيص</title>
    <link rel="stylesheet" href="assests/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assests/font-awesome/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cairo', 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
            text-align: right;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h2 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .login-header p {
            color: #666;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px 15px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            color: white;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: transform 0.2s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .demo-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        .demo-info h6 {
            color: #333;
            margin-bottom: 10px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-image {
            max-width: 120px;
            max-height: 80px;
            width: auto;
            height: auto;
            object-fit: contain;
            margin-bottom: 10px;
        }
        /* Fallback style if logo doesn't load */
        .logo-fallback {
            font-size: 48px;
            color: #667eea;
            display: none;
        }
        .form-control {
            text-align: right;
        }
        label {
            text-align: right;
            display: block;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="./assests/images/edara-logo.png" alt="شعار Edara" class="logo-image" onerror="showFallback()">
            <i class="glyphicon glyphicon-certificate logo-fallback" id="logoFallback"></i>
        </div>
        
        <div class="login-header">
            <h2>نظام إدارة التراخيص</h2>
            <p>يرجى تسجيل الدخول إلى حسابك</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">اسم المستخدم</label>
                <input type="text" 
                       class="form-control" 
                       id="username" 
                       name="username" 
                       placeholder="أدخل اسم المستخدم"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" 
                       class="form-control" 
                       id="password" 
                       name="password" 
                       placeholder="أدخل كلمة المرور"
                       required>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fa fa-sign-in"></i> تسجيل الدخول
            </button>
        </form>
    </div>

    <script src="assests/jquery/jquery.min.js"></script>
    <script src="assests/bootstrap/js/bootstrap.min.js"></script>
    <script>
        function showFallback() {
            // Hide the broken image and show the fallback icon
            $('.logo-image').hide();
            $('#logoFallback').show();
        }
    </script>
</body>
</html> 