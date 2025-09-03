<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<link rel="icon" type="image/x-icon" href="images/edara-logo.png">
<link rel="apple-touch-icon" href="images/edara-logo.png">

	<title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>نظام إدارة التراخيص</title>
<!-- bootstrap -->
	<link rel="stylesheet" href="assests/bootstrap/css/bootstrap.min.css">
	<!-- bootstrap theme-->
	<link rel="stylesheet" href="assests/bootstrap/css/bootstrap-theme.min.css">
	<!-- font awesome -->
	<link rel="stylesheet" href="assests/font-awesome/css/font-awesome.min.css">
	<!-- Google Fonts for Arabic -->
	<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <!-- Custom Arabic CSS -->
  <link rel="stylesheet" href="custom/css/arabic-style.css">

  	<!-- Custom Unified Styles -->
	<link rel="stylesheet" href="css/unified-styles.css">
	<!-- custom css -->
  <style>
    /* Arabic RTL Support */
    body {
      font-family: 'Cairo', 'Tahoma', 'Arial', sans-serif !important;
      direction: rtl;
      text-align: right;
    }
    
    .navbar-brand {
      float: right !important;
    }
    
    .navbar-nav {
      float: left !important;
    }
    
    .navbar-right {
      float: left !important;
    }
    
    .dropdown-menu {
      right: 0;
      left: auto;
      text-align: right;
    }
    
    .pull-right {
      float: left !important;
    }
    
    .pull-left {
      float: right !important;
    }
    
    .text-left {
      text-align: right !important;
    }
    
    .text-right {
      text-align: left !important;
    }
    
    /* Form controls */
    .form-control {
      text-align: right;
    }
    
    /* Tables */
    .table th,
    .table td {
      text-align: right;
    }
    
    /* Buttons */
    .btn-group > .btn:first-child {
      margin-right: 0;
      margin-left: -1px;
    }
    
    .content-wrapper {
      margin-top: 20px;
    }
    .license-card {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 15px;
      background: #fff;
    }
    .status-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: bold;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-expiring { background: #fff3cd; color: #856404; }
    .status-expired { background: #f8d7da; color: #721c24; }
    .dashboard-stats {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    .stat-box {
      text-align: center;
      padding: 15px;
      background: rgba(255,255,255,0.1);
      border-radius: 6px;
      margin-bottom: 10px;
    }
    .stat-number {
      font-size: 2em;
      font-weight: bold;
    }
    .table-responsive {
      margin-top: 20px;
    }
    .btn-group-actions {
      white-space: nowrap;
    }
    .image-preview {
      max-width: 100px;
      max-height: 100px;
      border-radius: 4px;
    }
  </style>
  <!-- jquery -->
	<script src="assests/jquery/jquery-3.6.0.min.js"></script>
  <!-- jquery ui -->  
  <link rel="stylesheet" href="assests/jquery-ui/jquery-ui.min.css">
  <script src="assests/jquery-ui/jquery-ui.min.js"></script>
<script src="assests/bootstrap/js/bootstrap.min.js"></script>	

<!-- Enhanced Mobile Navigation Styles -->
<style>
/* Fix navbar height and ensure proper mobile functionality */
.navbar {
    min-height: 60px !important;
    height: auto !important;
}

.navbar-header {
    height: auto !important;
}

/* Enhanced mobile toggle button */
.navbar-toggle {
    margin-top: 13px;
    margin-right: 15px;
    padding: 9px 10px;
    background-color: #007bff !important;
    border: 1px solid #007bff !important;
    border-radius: 4px;
}

.navbar-toggle:hover,
.navbar-toggle:focus {
    background-color: #0056b3 !important;
    border-color: #0056b3 !important;
}

.navbar-toggle .icon-bar {
    background-color: #fff !important;
}

/* Ensure mobile menu appears correctly */
@media (max-width: 767px) {
    .navbar-collapse {
        border-top: 1px solid #ddd;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.1);
        margin-top: 10px;
        padding-top: 10px;
    }
    
    .navbar-nav {
        margin: 0;
    }
    
    .navbar-nav > li {
        float: none;
    }
    
    .navbar-nav > li > a {
        padding: 10px 15px;
        color: #333 !important;
    }
    
    .navbar-nav > li > a:hover {
        background-color: #f5f5f5 !important;
        color: #007bff !important;
    }
    
    /* Style dropdowns in mobile */
    .navbar-nav .dropdown-menu {
        position: static;
        float: none;
        width: auto;
        margin-top: 0;
        background-color: #f8f9fa;
        border: 0;
        box-shadow: none;
        border-radius: 0;
    }
    
    .navbar-nav .dropdown-menu > li > a {
        padding: 8px 25px;
        color: #555 !important;
        font-size: 14px;
    }
    
    .navbar-nav .dropdown-menu > li > a:hover {
        background-color: #e9ecef !important;
        color: #007bff !important;
    }
    
    /* Open dropdowns by default on mobile */
    .navbar-nav .dropdown.open .dropdown-menu {
        display: block;
    }
}

/* Logo adjustment */
.navbar-brand {
    padding: 5px 15px !important;
}

.navbar-brand img {
    max-height: 50px;
    width: auto;
}

/* Ensure content doesn't overlap with fixed navbar */
body {
    padding-top: 70px;
}
</style>
  
</head>
<body>

	<nav class="navbar navbar-default navbar-fixed-top">
		<div class="container">
    <!-- Brand and toggle get grouped for better mobile display -->
    <div class="navbar-header">
      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>        
      </button>
      <a class="navbar-brand" href="dashboard.php">
		<img src="assests/images/edara-logo.png" alt="EDARA - A SODIC Company" style="max-height: 40px; width: auto;">
	  </a>
    </div>

    <!-- Collect the nav links, forms, and other content for toggling -->
   <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">

    <!-- Main navigation menu on the right -->
    <ul class="nav navbar-nav navbar-right">
        
        <?php require_once "php_action/auth.php"; ?>

        <!-- User menu - first item on the right -->
        <li class="dropdown" id="navSetting">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                <img src="assests/icons/user-icon.png">
                <span class="caret"></span>
            </a>
           <ul class="dropdown-menu">
    <?php if (isLoggedIn()): ?>
        <li><a href="#"><i class="glyphicon glyphicon-user"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></a></li>
        <li><a href="#"><i class="glyphicon glyphicon-briefcase"></i> <?php echo htmlspecialchars($_SESSION['department_name'] ?? 'No Department'); ?></a></li>
        <li><a href="#"><i class="glyphicon glyphicon-star"></i> <?php echo ucfirst($_SESSION['role']); ?></a></li>
        <li class="divider"></li>
        <li><a href="profile.php"><i class="glyphicon glyphicon-edit"></i> الملف الشخصي</a></li>
        <li class="divider"></li>
        <li><a href="php_action/logout.php"><i class="glyphicon glyphicon-log-out"></i> تسجيل الخروج</a></li>
    <?php else: ?>
        <li><a href="login.php"><i class="glyphicon glyphicon-log-in"></i> تسجيل الدخول</a></li>
    <?php endif; ?>
</ul>
        </li>

<?php if (isLoggedIn()): ?>
  <!-- Dashboard -->
  <li><a href="dashboard.php"><i class="glyphicon glyphicon-dashboard"></i> لوحة التحكم</a></li>

  <!-- License Management -->
  <?php if (hasPermission('licenses_view') || hasPermission('personal_licenses_view') || hasPermission('vehicle_licenses_view')): ?>
  <li class="dropdown" id="navLicenses">
    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
      <span style="color: blue;">إدارة التراخيص</span> <span class="caret"></span>
    </a>
    <ul class="dropdown-menu">
      <?php if (hasPermission('licenses_view') || hasPermission('personal_licenses_view')): ?>
      <li><a href="licenses.php?type=personal"><i class="glyphicon glyphicon-user"></i> إدارة رخص القيادة الشخصية</a></li>
      <?php endif; ?>
      
      <?php if (hasPermission('licenses_view') || hasPermission('vehicle_licenses_view')): ?>
      <li><a href="licenses.php?type=vehicle"><i class="glyphicon glyphicon-road"></i> إدارة رخص المركبات</a></li>
      <?php endif; ?>
      
      <?php if ((hasPermission('licenses_view') || hasPermission('personal_licenses_view')) || (hasPermission('licenses_view') || hasPermission('vehicle_licenses_view'))): ?>
      <li class="divider"></li>
      <?php endif; ?>
      
        <?php if (hasPermission('personal_licenses_add')): ?>
        <li><a href="add_license.php?type=personal"><i class="glyphicon glyphicon-plus"></i> إضافة رخصة قيادة</a></li>
      <?php endif; ?>
      
        <?php if (hasPermission('vehicle_licenses_add')): ?>
        <li><a href="add_license.php?type=vehicle"><i class="glyphicon glyphicon-plus"></i> إضافة رخصة مركبة</a></li>
      <?php endif; ?>
      
      <?php if (hasPermission('licenses_delete')): ?>
        <li class="divider"></li>
        <li><a href="deleted_licenses.php"><i class="glyphicon glyphicon-trash"></i> التراخيص المحذوفة</a></li>
      <?php endif; ?>
      
      <?php if (hasPermission('licenses_view') || hasPermission('personal_licenses_view') || hasPermission('vehicle_licenses_view')): ?>
        <li><a href="license_reports.php"><i class="glyphicon glyphicon-stats"></i> التقارير</a></li>
      <?php endif; ?>
    </ul>
  </li>
  <?php endif; ?>

  <!-- User Management -->
  <?php if (hasPermission('users_view') || hasPermission('departments_view') || getUserRole() === 'super_admin'): ?>
  <li class="dropdown" id="navUsers">
    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
      <span style="color: red;">إدارة المستخدمين</span> <span class="caret"></span>
    </a>
    <ul class="dropdown-menu">
      <?php if (hasPermission('users_view') || getUserRole() === 'super_admin'): ?>
      <li><a href="users.php"><i class="glyphicon glyphicon-user"></i> إدارة المستخدمين</a></li>
      <?php endif; ?>
      
      <?php if (hasPermission('users_add') || getUserRole() === 'super_admin'): ?>
      <li><a href="add_user.php"><i class="glyphicon glyphicon-plus"></i> إضافة مستخدم</a></li>
      <?php endif; ?>
      
      <?php if (hasPermission('users_delete') || getUserRole() === 'super_admin'): ?>
      <li><a href="deleted_users.php"><i class="glyphicon glyphicon-trash"></i> المستخدمون المحذوفون</a></li>
      <?php endif; ?>
      
      <?php if (getUserRole() === 'super_admin'): ?>
      <li><a href="team_management.php"><i class="glyphicon glyphicon-tree-deciduous"></i> إدارة الفرق الإدارية</a></li>
      <?php endif; ?>
      
      <?php if (hasPermission('departments_view') || getUserRole() === 'super_admin'): ?>
      <li><a href="departments.php"><i class="glyphicon glyphicon-home"></i> الأقسام</a></li>
      <?php endif; ?>
    </ul>
  </li>
  <?php endif; ?>
  
  <!-- Email Notifications (Super Admin Only) -->
  <?php if (getUserRole() === 'super_admin'): ?>
  <li class="dropdown">
    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
      <span style="color: orange;">الإشعارات</span> <span class="caret"></span>
    </a>
    <ul class="dropdown-menu">
      <li><a href="email_notifications.php"><i class="glyphicon glyphicon-envelope"></i> إرسال الإشعارات</a></li>
      <li><a href="notification_history.php"><i class="glyphicon glyphicon-list-alt"></i> سجل الإشعارات</a></li>
      <li class="divider"></li>
      <li><a href="project_emails_management.php"><i class="glyphicon glyphicon-send"></i> إيميلات المشاريع</a></li>
    </ul>
  </li>
  <?php endif; ?>

<?php endif; ?>
        
    </ul>
</div><!-- /.navbar-collapse -->

  </div><!-- /.container -->
</nav>

<!-- Enhanced JavaScript for stable mobile menu behavior -->
<script>
$(document).ready(function() {
    var isMenuOpen = false;
    var $navbarCollapse = $('#bs-example-navbar-collapse-1');
    var $navbarToggle = $('.navbar-toggle');
    
    // Override default Bootstrap behavior for more control
    $navbarToggle.off('click.bs.collapse.data-api');
    
    // Custom toggle handler
    $navbarToggle.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        isMenuOpen = !isMenuOpen;
        
        if (isMenuOpen) {
            // Open menu
            $navbarCollapse.addClass('in').addClass('show');
            $(this).removeClass('collapsed').attr('aria-expanded', 'true');
        } else {
            // Close menu
            $navbarCollapse.removeClass('in').removeClass('show');
            $(this).addClass('collapsed').attr('aria-expanded', 'false');
            $('.dropdown').removeClass('open');
        }
    });
    
    // Handle dropdown clicks on mobile
    $('.dropdown-toggle').on('click', function(e) {
        if ($(window).width() <= 767) {
            e.preventDefault();
            e.stopPropagation();
            
            var $dropdown = $(this).parent();
            var wasOpen = $dropdown.hasClass('open');
            
            // Close all other dropdowns
            $('.dropdown').removeClass('open');
            
            // Toggle current dropdown
            if (!wasOpen) {
                $dropdown.addClass('open');
            }
        }
    });
    
    // Close menu when clicking outside (but not immediately)
    $(document).on('click', function(e) {
        if (isMenuOpen && !$(e.target).closest('.navbar').length) {
            setTimeout(function() {
                isMenuOpen = false;
                $navbarCollapse.removeClass('in').removeClass('show');
                $navbarToggle.addClass('collapsed').attr('aria-expanded', 'false');
                $('.dropdown').removeClass('open');
            }, 100);
        }
    });
    
    // Close dropdowns when clicking menu items
    $('.navbar-nav a:not(.dropdown-toggle)').on('click', function() {
        if ($(window).width() <= 767) {
            setTimeout(function() {
                isMenuOpen = false;
                $navbarCollapse.removeClass('in').removeClass('show');
                $navbarToggle.addClass('collapsed').attr('aria-expanded', 'false');
                $('.dropdown').removeClass('open');
            }, 200);
        }
    });
    
    // Handle window resize
    $(window).resize(function() {
        if ($(window).width() > 767) {
            isMenuOpen = false;
            $navbarCollapse.removeClass('in').removeClass('show');
            $navbarToggle.addClass('collapsed').attr('aria-expanded', 'false');
            $('.dropdown').removeClass('open');
        }
    });
    
    // Prevent menu from closing when clicking inside dropdown
    $('.dropdown-menu').on('click', function(e) {
        e.stopPropagation();
    });
});
</script>

<!-- Pagination Script -->
<script src="js/pagination.js"></script>

</body>
</html> 