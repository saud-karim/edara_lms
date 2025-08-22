<?php
$pageTitle = 'لوحة التحكم';
require_once 'config/config.php';
require_once 'php_action/auth.php';

// Require login to access this page
requireLogin();

$userRole = getUserRole();
$userId = getUserId();
$userDepartment = getUserDepartment();

// Improved permission logic - specific permissions take priority
$hasVehicleView = hasPermission('vehicle_licenses_view');
$hasPersonalView = hasPermission('personal_licenses_view');
$hasGeneralView = hasPermission('licenses_view');

// If user has specific permissions, use those ONLY
// If user has general permission only, use it for both
if ($hasVehicleView || $hasPersonalView) {
    
    error_log("FLOW DEBUG: Has specific permissions - vehicle: " . ($hasVehicleView ? "true" : "false") . ", personal: " . ($hasPersonalView ? "true" : "false"));
    
    error_log("FLOW DEBUG: Has specific permissions - vehicle: " . ($hasVehicleView ? "true" : "false") . ", personal: " . ($hasPersonalView ? "true" : "false"));
    
    error_log("FLOW DEBUG: Has specific permissions - vehicle: " . ($hasVehicleView ? "true" : "false") . ", personal: " . ($hasPersonalView ? "true" : "false"));
    // User has specific permissions - be strict
    $canViewVehicle = $hasVehicleView;
    $canViewPersonal = $hasPersonalView;
} else {
    // User only has general permissions - apply to both
    $canViewVehicle = $hasGeneralView;
    $canViewPersonal = $hasGeneralView;
}

try {
    $conn = getDBConnection();
    
    // Get statistics based on user role and permissions
    if ($userRole === 'super_admin') {
        // Super admin sees all statistics
        
        // Personal Licenses Statistics (only if can view)
        if ($canViewPersonal) {
            
            
            
            
            
            
            $personalTotalLicenses = $conn->query("SELECT COUNT(*) FROM personal_licenses WHERE is_active = 1")->fetchColumn();
            $personalExpiringLicenses = $conn->query("SELECT COUNT(*) FROM personal_licenses l WHERE l.is_active = 1 AND l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND l.expiration_date >= CURDATE()")->fetchColumn();
            $personalExpiredLicenses = $conn->query("SELECT COUNT(*) FROM personal_licenses l WHERE l.is_active = 1 AND l.expiration_date < CURDATE()")->fetchColumn();
        } else {
            $personalTotalLicenses = $personalExpiringLicenses = $personalExpiredLicenses = 0;
        }
        
        // Vehicle Licenses Statistics (only if can view)
        if ($canViewVehicle) {
            
            
            
            
            
            
            $vehicleTotalLicenses = $conn->query("SELECT COUNT(*) FROM vehicle_licenses WHERE is_active = 1")->fetchColumn();
            $vehicleExpiringLicenses = $conn->query("SELECT COUNT(*) FROM vehicle_licenses l WHERE l.is_active = 1 AND l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND l.expiration_date >= CURDATE()")->fetchColumn();
            $vehicleExpiredLicenses = $conn->query("SELECT COUNT(*) FROM vehicle_licenses l WHERE l.is_active = 1 AND l.expiration_date < CURDATE()")->fetchColumn();
        } else {
            $vehicleTotalLicenses = $vehicleExpiringLicenses = $vehicleExpiredLicenses = 0;
        }
        
        // Administrative Statistics
        $totalUsers = $conn->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
        $totalDepartments = $conn->query("SELECT COUNT(*) FROM departments WHERE is_active = 1")->fetchColumn();
        
        // Recent licenses across all departments (based on permissions)
        if ($canViewPersonal) {
            
            
            
            
            
            
            $recentPersonalLicensesQuery = "
                SELECT l.*, p.project_name, d.department_name
                FROM personal_licenses l
                LEFT JOIN projects p ON l.project_id = p.project_id
                LEFT JOIN departments d ON l.department_id = d.department_id
                WHERE l.is_active = 1
                ORDER BY l.created_at DESC 
                LIMIT 5
            ";
        }
        
        if ($canViewVehicle) {
            
            
            
            
            
            
            $recentVehicleLicensesQuery = "
                SELECT l.*, p.project_name, d.department_name
                FROM vehicle_licenses l
                LEFT JOIN projects p ON l.project_id = p.project_id
                LEFT JOIN departments d ON l.department_id = d.department_id
                WHERE l.is_active = 1
                ORDER BY l.created_at DESC 
                LIMIT 5
            ";
        }
        
    } elseif ($userRole === 'admin') {
    
    error_log("FLOW DEBUG: Entering admin section for user " . getUserId());
    
    error_log("FLOW DEBUG: Entering admin section for user " . getUserId());
    echo "<!--ADMIN_SECTION_ENTERED-->";
    error_log("DASHBOARD DEBUG: Admin section entered for user " . getUserId());
    
    error_log("FLOW DEBUG: Entering admin section for user " . getUserId());
        // Admin sees statistics based on team system
        // Get filters with correct table aliases for dashboard queries
        $personalLicenseFilter = getLicenseFilter('l');
        
        error_log("FLOW DEBUG: personalLicenseFilter = " . $personalLicenseFilter);
        
        error_log("FLOW DEBUG: personalLicenseFilter = " . $personalLicenseFilter);
        
        error_log("FLOW DEBUG: personalLicenseFilter = " . $personalLicenseFilter);  // 'l' alias for main table in dashboard
        $vehicleLicenseFilter = getLicenseFilter('l');
        
        error_log("FLOW DEBUG: vehicleLicenseFilter = " . $vehicleLicenseFilter);
        
        error_log("FLOW DEBUG: vehicleLicenseFilter = " . $vehicleLicenseFilter);
        
        error_log("FLOW DEBUG: vehicleLicenseFilter = " . $vehicleLicenseFilter);   // 'l' alias for main table in dashboard
        
        // Personal Licenses Statistics (only if can view)
        if ($canViewPersonal) {
            
            
            
            
            
            
            $personalTotalStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses l WHERE l.is_active = 1 AND ($personalLicenseFilter)");
            $personalTotalStmt->execute();
            $personalTotalLicenses = $personalTotalStmt->fetchColumn();
            
            $personalExpiringStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses l WHERE l.is_active = 1 AND ($personalLicenseFilter) AND l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND l.expiration_date >= CURDATE()");
            $personalExpiringStmt->execute();
            $personalExpiringLicenses = $personalExpiringStmt->fetchColumn();
            
            $personalExpiredStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses l WHERE l.is_active = 1 AND ($personalLicenseFilter) AND l.expiration_date < CURDATE()");
            $personalExpiredStmt->execute();
            $personalExpiredLicenses = $personalExpiredStmt->fetchColumn();
        } else {
            $personalTotalLicenses = $personalExpiringLicenses = $personalExpiredLicenses = 0;
        }
        
        // Vehicle Licenses Statistics (only if can view)
        if ($canViewVehicle) {
            
            
            
            
            
            
            $vehicleTotalStmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_licenses l WHERE l.is_active = 1 AND ($vehicleLicenseFilter)");
            $vehicleTotalStmt->execute();
            $vehicleTotalLicenses = $vehicleTotalStmt->fetchColumn();
            
            $vehicleExpiringStmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_licenses l WHERE l.is_active = 1 AND ($vehicleLicenseFilter) AND l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND l.expiration_date >= CURDATE()");
            $vehicleExpiringStmt->execute();
            $vehicleExpiringLicenses = $vehicleExpiringStmt->fetchColumn();
            
            $vehicleExpiredStmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_licenses l WHERE l.is_active = 1 AND ($vehicleLicenseFilter) AND l.expiration_date < CURDATE()");
            $vehicleExpiredStmt->execute();
            $vehicleExpiredLicenses = $vehicleExpiredStmt->fetchColumn();
        } else {
            $vehicleTotalLicenses = $vehicleExpiringLicenses = $vehicleExpiredLicenses = 0;
        }
        
        // Recent licenses based on team access (based on permissions)
        if ($canViewPersonal) {
            
            
            
            
            
            
            $recentPersonalLicensesQuery = "
                SELECT l.*, p.project_name, d.department_name, u.full_name as added_by
                FROM personal_licenses l
                LEFT JOIN projects p ON l.project_id = p.project_id
                LEFT JOIN departments d ON l.department_id = d.department_id
                LEFT JOIN users u ON l.user_id = u.user_id
                WHERE l.is_active = 1 AND ($personalLicenseFilter)
                ORDER BY l.created_at DESC 
                LIMIT 5
            ";
        }
        
        if ($canViewVehicle) {
            
            
            
            
            
            
            $recentVehicleLicensesQuery = "
                SELECT l.*, p.project_name, d.department_name, u.full_name as added_by
                FROM vehicle_licenses l
                LEFT JOIN projects p ON l.project_id = p.project_id
                LEFT JOIN departments d ON l.department_id = d.department_id
                LEFT JOIN users u ON l.user_id = u.user_id
                WHERE l.is_active = 1 AND ($vehicleLicenseFilter)
                ORDER BY l.created_at DESC 
                LIMIT 5
            ";
        }
        
    } else {
        // Regular user sees department-based statistics (with permission filtering)
        
        // Get department-based filter for regular users
        $personalLicenseFilter = getLicenseFilter('l');  // Use same filter as admin/head admin
        $vehicleLicenseFilter = getLicenseFilter('l');
        
        // Personal Licenses Statistics (only if can view)
        if ($canViewPersonal) {
            $personalTotalStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses l WHERE l.is_active = 1 AND ($personalLicenseFilter)");
            $personalTotalStmt->execute();
            $personalTotalLicenses = $personalTotalStmt->fetchColumn();
            
            $personalExpiringStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses l WHERE l.is_active = 1 AND ($personalLicenseFilter) AND l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND l.expiration_date >= CURDATE()");
            $personalExpiringStmt->execute();
            $personalExpiringLicenses = $personalExpiringStmt->fetchColumn();
            
            $personalExpiredStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses l WHERE l.is_active = 1 AND ($personalLicenseFilter) AND l.expiration_date < CURDATE()");
            $personalExpiredStmt->execute();
            $personalExpiredLicenses = $personalExpiredStmt->fetchColumn();
        } else {
            $personalTotalLicenses = $personalExpiringLicenses = $personalExpiredLicenses = 0;
        }
        
        // Vehicle Licenses Statistics (only if can view)
        if ($canViewVehicle) {
            $vehicleTotalStmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_licenses l WHERE l.is_active = 1 AND ($vehicleLicenseFilter)");
            $vehicleTotalStmt->execute();
            $vehicleTotalLicenses = $vehicleTotalStmt->fetchColumn();
            
            $vehicleExpiringStmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_licenses l WHERE l.is_active = 1 AND ($vehicleLicenseFilter) AND l.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND l.expiration_date >= CURDATE()");
            $vehicleExpiringStmt->execute();
            $vehicleExpiringLicenses = $vehicleExpiringStmt->fetchColumn();
            
            $vehicleExpiredStmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_licenses l WHERE l.is_active = 1 AND ($vehicleLicenseFilter) AND l.expiration_date < CURDATE()");
            $vehicleExpiredStmt->execute();
            $vehicleExpiredLicenses = $vehicleExpiredStmt->fetchColumn();
        } else {
            $vehicleTotalLicenses = $vehicleExpiringLicenses = $vehicleExpiredLicenses = 0;
        }
        
        // Recent licenses from user's department (based on permissions)
        if ($canViewPersonal) {
            $recentPersonalLicensesQuery = "
                SELECT l.*, p.project_name, d.department_name, u.full_name as added_by
                FROM personal_licenses l
                LEFT JOIN projects p ON l.project_id = p.project_id
                LEFT JOIN departments d ON l.department_id = d.department_id
                LEFT JOIN users u ON l.user_id = u.user_id
                WHERE l.is_active = 1 AND ($personalLicenseFilter)
                ORDER BY l.created_at DESC 
                LIMIT 5
            ";
        }
        
        if ($canViewVehicle) {
            $recentVehicleLicensesQuery = "
                SELECT l.*, p.project_name, d.department_name, u.full_name as added_by
                FROM vehicle_licenses l
                LEFT JOIN projects p ON l.project_id = p.project_id
                LEFT JOIN departments d ON l.department_id = d.department_id
                LEFT JOIN users u ON l.user_id = u.user_id
                WHERE l.is_active = 1 AND ($vehicleLicenseFilter)
                ORDER BY l.created_at DESC 
                LIMIT 5
            ";
        }
    }
    
    // Get recent personal licenses (only if user can view them)
    if ($canViewPersonal && isset($recentPersonalLicensesQuery)) {
        $recentPersonalStmt = $conn->prepare($recentPersonalLicensesQuery);
        // Admin queries already have filtering built-in, no parameters needed
            $recentPersonalStmt->execute();
        $recentPersonalLicenses = $recentPersonalStmt->fetchAll();
    } else {
        $recentPersonalLicenses = [];
    }
    
    // Get recent vehicle licenses (only if user can view them)
    if ($canViewVehicle && isset($recentVehicleLicensesQuery)) {
        $recentVehicleStmt = $conn->prepare($recentVehicleLicensesQuery);
        if ($userRole === 'admin') {
    
    error_log("FLOW DEBUG: Entering admin section for user " . getUserId());
    
    error_log("FLOW DEBUG: Entering admin section for user " . getUserId());
    
    error_log("FLOW DEBUG: Entering admin section for user " . getUserId());
            $recentVehicleStmt->execute(); // Fixed: No parameters needed as filter is built-in
        } else {
            $recentVehicleStmt->execute();
        }
        $recentVehicleLicenses = $recentVehicleStmt->fetchAll();
    } else {
        $recentVehicleLicenses = [];
    }
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $personalTotalLicenses = $personalExpiringLicenses = $personalExpiredLicenses = 0;
    $vehicleTotalLicenses = $vehicleExpiringLicenses = $vehicleExpiredLicenses = 0;
    $totalUsers = $totalDepartments = 0;
    $recentPersonalLicenses = $recentVehicleLicenses = [];
}

include 'includes/header.php';
?>

<style>
/* Team Management Dashboard Styles */
.stat-item {
    padding: 15px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 15px;
    transition: transform 0.2s ease;
}

.stat-item:hover {
    transform: translateY(-2px);
}

.stat-icon {
    font-size: 2.5em;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 2.2em;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

.team-member-card {
    transition: all 0.2s ease;
}

.team-member-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0,123,255,0.2);
}

.panel-info > .panel-heading {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
}

.panel-info {
    border-color: #667eea;
}
</style>

<div class="container content-wrapper">
    <?php displayMessage(); ?>
    
    <!-- Welcome Section -->
    <div class="row">
        <div class="col-md-12">
            <div class="dashboard-stats">
                <h2><i class="glyphicon glyphicon-dashboard"></i> مرحباً بعودتك، <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p>
                    <strong>الدور:</strong> <?php echo ucfirst($userRole); ?> | 
                    <strong>القسم:</strong> <?php echo htmlspecialchars($_SESSION['department_name'] ?? 'جميع الأقسام'); ?> |
                    <strong>آخر دخول:</strong> <?php echo date('d/m/Y H:i', $_SESSION['login_time']); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Personal Licenses Statistics -->
    <?php if ($canViewPersonal): ?>
    <div class="row">
        <div class="col-md-12">
            <h3 style="color: #2c3e50; margin-bottom: 20px; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
                <i class="glyphicon glyphicon-user"></i> إحصائيات رخص القيادة الشخصية
            </h3>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="stat-box bg-primary text-white" style="background: #3498db !important; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                <div class="stat-number"><?php echo number_format($personalTotalLicenses); ?></div>
                <div class="stat-text">إجمالي رخص القيادة</div>
                <a href="licenses.php?type=personal" class="btn btn-sm btn-light" style="margin-top: 10px;">عرض الكل</a>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6">
            <div class="stat-box bg-warning text-white" style="background: #f39c12 !important; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                <div class="stat-number"><?php echo number_format($personalExpiringLicenses); ?></div>
                <div class="stat-text">ستنتهي قريباً</div>
                <a href="licenses.php?type=personal&status=expiring" class="btn btn-sm btn-light" style="margin-top: 10px;">عرض</a>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6">
            <div class="stat-box bg-danger text-white" style="background: #e74c3c !important; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                <div class="stat-number"><?php echo number_format($personalExpiredLicenses); ?></div>
                <div class="stat-text">منتهية الصلاحية</div>
                <a href="licenses.php?type=personal&status=expired" class="btn btn-sm btn-light" style="margin-top: 10px;">عرض</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Vehicle Licenses Statistics -->
    <?php if ($canViewVehicle): ?>
    <div class="row">
        <div class="col-md-12">
            <h3 style="color: #2c3e50; margin-bottom: 20px; border-bottom: 2px solid #e67e22; padding-bottom: 10px;">
                <i class="glyphicon glyphicon-road"></i> إحصائيات رخص المركبات
            </h3>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="stat-box bg-info text-white" style="background: #e67e22 !important; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                <div class="stat-number"><?php echo number_format($vehicleTotalLicenses); ?></div>
                <div class="stat-text">إجمالي رخص المركبات</div>
                <a href="licenses.php?type=vehicle" class="btn btn-sm btn-light" style="margin-top: 10px;">عرض الكل</a>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6">
            <div class="stat-box bg-warning text-white" style="background: #d35400 !important; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                <div class="stat-number"><?php echo number_format($vehicleExpiringLicenses); ?></div>
                <div class="stat-text">ستنتهي قريباً</div>
                <a href="licenses.php?type=vehicle&status=expiring" class="btn btn-sm btn-light" style="margin-top: 10px;">عرض</a>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6">
            <div class="stat-box bg-danger text-white" style="background: #c0392b !important; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                <div class="stat-number"><?php echo number_format($vehicleExpiredLicenses); ?></div>
                <div class="stat-text">منتهية الصلاحية</div>
                <a href="licenses.php?type=vehicle&status=expired" class="btn btn-sm btn-light" style="margin-top: 10px;">عرض</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Administrative Statistics (for Super Admin only) -->
        <?php if ($userRole === 'super_admin'): ?>
    <div class="row">
        <div class="col-md-12">
            <h3 style="color: #2c3e50; margin-bottom: 20px; border-bottom: 2px solid #27ae60; padding-bottom: 10px;">
                <i class="glyphicon glyphicon-cog"></i> الإحصائيات الإدارية
            </h3>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 col-sm-6">
            <div class="stat-box bg-success text-white" style="background: #27ae60 !important; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
                <div class="stat-text">إجمالي المستخدمين</div>
                <a href="users.php" class="btn btn-sm btn-light" style="margin-top: 10px;">إدارة</a>
            </div>
        </div>
        
        <div class="col-md-6 col-sm-6">
            <div class="stat-box bg-info text-white" style="background: #2980b9 !important; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                <div class="stat-number"><?php echo number_format($totalDepartments); ?></div>
                <div class="stat-text">إجمالي الأقسام</div>
                <a href="departments.php" class="btn btn-sm btn-light" style="margin-top: 10px;">إدارة</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Team Management Section (Head Admin Only) -->
    <?php if (isHeadAdmin()): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-users"></i> إدارة الفريق
                        <a href="team_management.php" class="btn btn-xs btn-default pull-right">
                            <i class="glyphicon glyphicon-cog"></i> الإدارة الكاملة
                        </a>
                    </h4>
                </div>
                <div class="panel-body">
                    <?php
                    // Get team information for Head Admin
                    $teamInfo = getTeamInfo();
                    $teamStats = [
                        'sub_admins' => count($teamInfo),
                        'total_personal' => 0,
                        'total_vehicle' => 0,
                        'expiring_soon' => 0
                    ];
                    
                    // Calculate team stats
                    $teamUserIds = getMyTeamAndSelfIds();
                    if (!empty($teamUserIds)) {
                        $userIdsStr = implode(',', $teamUserIds);
                        
                        $personalStmt = $conn->prepare("SELECT COUNT(*) FROM personal_licenses WHERE user_id IN ($userIdsStr) AND is_active = 1");
                        $personalStmt->execute();
                        $teamStats['total_personal'] = $personalStmt->fetchColumn();
                        
                        $vehicleStmt = $conn->prepare("SELECT COUNT(*) FROM vehicle_licenses WHERE user_id IN ($userIdsStr) AND is_active = 1");
                        $vehicleStmt->execute();
                        $teamStats['total_vehicle'] = $vehicleStmt->fetchColumn();
                        
                        $expiringStmt = $conn->prepare("
                            SELECT COUNT(*) FROM (
                                SELECT expiration_date FROM personal_licenses 
                                WHERE user_id IN ($userIdsStr) AND is_active = 1 
                                AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                UNION ALL
                                SELECT expiration_date FROM vehicle_licenses 
                                WHERE user_id IN ($userIdsStr) AND is_active = 1 
                                AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                            ) as expiring
                        ");
                        $expiringStmt->execute();
                        $teamStats['expiring_soon'] = $expiringStmt->fetchColumn();
                    }
                    ?>
                    
                    <div class="row">
                        <!-- Team Stats -->
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-item text-center">
                                <div class="stat-icon">
                                    <i class="glyphicon glyphicon-user" style="color: #007bff;"></i>
                                </div>
                                <div class="stat-number"><?php echo $teamStats['sub_admins']; ?></div>
                                <div class="stat-label">مديرين فرعيين</div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-item text-center">
                                <div class="stat-icon">
                                    <i class="glyphicon glyphicon-file" style="color: #28a745;"></i>
                                </div>
                                <div class="stat-number"><?php echo $teamStats['total_personal']; ?></div>
                                <div class="stat-label">رخص شخصية</div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-item text-center">
                                <div class="stat-icon">
                                    <i class="glyphicon glyphicon-road" style="color: #17a2b8;"></i>
                                </div>
                                <div class="stat-number"><?php echo $teamStats['total_vehicle']; ?></div>
                                <div class="stat-label">رخص مركبات</div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-item text-center">
                                <div class="stat-icon">
                                    <i class="glyphicon glyphicon-warning-sign" style="color: #ffc107;"></i>
                                </div>
                                <div class="stat-number"><?php echo $teamStats['expiring_soon']; ?></div>
                                <div class="stat-label">تنتهي قريباً</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($teamInfo)): ?>
                    <hr>
                    <h5><i class="glyphicon glyphicon-users"></i> أعضاء الفريق:</h5>
                    <div class="row">
                        <?php foreach ($teamInfo as $member): ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="team-member-card" style="background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 10px; border-left: 3px solid #007bff;">
                                <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($member['username']); ?></small>
                                <br><small><i class="glyphicon glyphicon-home"></i> <?php echo htmlspecialchars($member['department_name'] ?? 'لا يوجد قسم'); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="glyphicon glyphicon-info-sign"></i>
                        <strong>لا يوجد مديرين فرعيين تحتك حالياً.</strong>
                        <br>يمكن للمدير العام إضافة مديرين فرعيين لفريقك من خلال <a href="add_user.php">إضافة مستخدم جديد</a>.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Licenses Section -->
    <?php if ($canViewPersonal || $canViewVehicle): ?>
    <div class="row">
        <?php if ($canViewPersonal): ?>
        <div class="<?php echo ($canViewPersonal && $canViewVehicle) ? 'col-md-6' : 'col-md-12'; ?>">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-user"></i> آخر رخص القيادة الشخصية
                    </h4>
                </div>
                <div class="panel-body">
                    <?php if (!empty($recentPersonalLicenses)): ?>
                        <div class="list-group">
                            <?php foreach ($recentPersonalLicenses as $license): ?>
                                <div class="list-group-item">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="list-group-item-heading">
                                                <strong><?php echo htmlspecialchars($license['full_name']); ?></strong>
                                            </h6>
                                            <p class="list-group-item-text">
                                                <small>
                                                    رقم الترخيص: <?php echo htmlspecialchars($license['license_number']); ?><br>
                                                    القسم: <?php echo htmlspecialchars($license['department_name']); ?><br>
                                                    تاريخ الانتهاء: <?php echo date('d/m/Y', strtotime($license['expiration_date'])); ?>
                                                </small>
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-left">
                                            <a href="view_license.php?id=<?php echo $license['license_id']; ?>&type=personal" 
                                               class="btn btn-xs btn-default">
                                                عرض
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center" style="margin-top: 15px;">
                            <a href="licenses.php?type=personal" class="btn btn-primary">عرض جميع رخص القيادة</a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">لا توجد رخص قيادة شخصية حديثة</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canViewVehicle): ?>
        <div class="<?php echo ($canViewPersonal && $canViewVehicle) ? 'col-md-6' : 'col-md-12'; ?>">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="glyphicon glyphicon-road"></i> آخر رخص المركبات
                    </h4>
                </div>
                <div class="panel-body">
                    <?php if (!empty($recentVehicleLicenses)): ?>
                        <div class="list-group">
                            <?php foreach ($recentVehicleLicenses as $license): ?>
                                <div class="list-group-item">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="list-group-item-heading">
                                                <strong><?php echo htmlspecialchars($license['car_number']); ?></strong>
                                            </h6>
                                            <p class="list-group-item-text">
                                                <small>
                                                    نوع المركبة: <?php echo htmlspecialchars($license['vehicle_type']); ?><br>
                                                    القسم: <?php echo htmlspecialchars($license['department_name']); ?><br>
                                                    تاريخ الانتهاء: <?php echo date('d/m/Y', strtotime($license['expiration_date'])); ?>
                                                </small>
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-left">
                                            <a href="view_license.php?id=<?php echo $license['license_id']; ?>&type=vehicle" 
                                               class="btn btn-xs btn-default">
                                                عرض
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center" style="margin-top: 15px;">
                            <a href="licenses.php?type=vehicle" class="btn btn-warning">عرض جميع رخص المركبات</a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">لا توجد رخص مركبات حديثة</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    

</div>

<style>
.stat-box {
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    line-height: 1;
}

.stat-text {
    font-size: 0.9em;
    margin-top: 10px;
    opacity: 0.9;
}

.quick-action {
    margin-bottom: 20px;
}

.quick-action .btn {
    height: 80px;
    padding: 15px;
    line-height: 1.2;
}

.quick-action .btn i {
    font-size: 1.5em;
    margin-bottom: 5px;
}

.list-group-item {
    border-left: 4px solid transparent;
    transition: all 0.3s;
}

.list-group-item:hover {
    border-left-color: #3498db;
    background-color: #f8f9fa;
}

.panel-heading {
    background: linear-gradient(45deg, #3498db, #2980b9) !important;
    color: white !important;
}

.panel-heading .panel-title {
    color: white !important;
    font-weight: bold;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .stat-number {
        font-size: 2em;
    }
    
    .quick-action .btn {
        height: 60px;
        padding: 10px;
    }
    
    .quick-action .btn i {
        font-size: 1.2em;
    }
}
</style>

<?php include 'includes/footer.php'; ?> 