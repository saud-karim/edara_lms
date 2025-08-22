<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Check login and export permission
if (!isLoggedIn()) {
    http_response_code(401);
    die('<h1>Unauthorized</h1><p>يجب تسجيل الدخول أولاً</p>');
}

if (!hasPermission('reports_export')) {
    http_response_code(403);
    die('<h1>Forbidden</h1><p>غير مصرح لك بتصدير التقارير</p>');
}

try {
    $conn = getDBConnection();
    
    // Get parameters
    $reportType = $_GET['report_type'] ?? '';
    $exportFormat = $_GET['export_format'] ?? 'excel';
    
    if (empty($reportType)) {
        die('<h1>Error</h1><p>نوع التقرير مطلوب</p>');
    }
    
    // Apply permission logic (same as generate_report.php)
    $hasVehicleView = hasPermission('vehicle_licenses_view');
    $hasPersonalView = hasPermission('personal_licenses_view');
    $hasGeneralView = hasPermission('licenses_view');

    if ($hasVehicleView || $hasPersonalView) {
        $canViewVehicle = $hasVehicleView;
        $canViewPersonal = $hasPersonalView;
    } else {
        $canViewVehicle = $hasGeneralView;
        $canViewPersonal = $hasGeneralView;
    }
    
    // Validate report type permissions
    if (strpos($reportType, 'personal_') === 0 && !$canViewPersonal) {
        http_response_code(403);
        die('<h1>Forbidden</h1><p>غير مصرح لك بتصدير تقارير رخص القيادة الشخصية</p>');
    }
    
    if (strpos($reportType, 'vehicle_') === 0 && !$canViewVehicle) {
        http_response_code(403);
        die('<h1>Forbidden</h1><p>غير مصرح لك بتصدير تقارير رخص المركبات</p>');
    }
    
    if ($reportType === 'all_summary' && (!$canViewPersonal && !$canViewVehicle)) {
        http_response_code(403);
        die('<h1>Forbidden</h1><p>غير مصرح لك بتصدير التقرير الشامل</p>');
    }
    
    // Generate report data directly (same logic as generate_report.php)
    $currentUser = getCurrentUser();
    $userRole = $currentUser['role'];
    $userDept = $userRole === 'super_admin' ? null : $currentUser['department_id'];
    
    // Apply department filter based on user selection and permissions
    $selectedDeptId = '';
    if ($userRole !== 'super_admin') {
        $selectedDeptId = $userDept;
    }
    
    // Build WHERE conditions
    $whereConditions = ["is_active = 1"];
    $params = [];
    
    if (!empty($selectedDeptId)) {
        $whereConditions[] = "department_id = ?";
        $params[] = $selectedDeptId;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    // Generate data based on report type
    $data = [];
    $summary = [];
    
    if ($reportType === 'all_summary') {
        // Generate summary for both license types
        $summary = ['total' => 0, 'active' => 0, 'expiring' => 0, 'expired' => 0];
        $data = [];
        
        if ($canViewPersonal) {
            $personalQuery = "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN expiration_date < CURDATE() THEN 1 END) as expired,
                COUNT(CASE WHEN expiration_date >= CURDATE() AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring,
                COUNT(CASE WHEN expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as active
                FROM personal_licenses $whereClause";
            
            $stmt = $conn->prepare($personalQuery);
            if (!empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            $personalStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $summary['total'] += $personalStats['total'];
            $summary['active'] += $personalStats['active'];
            $summary['expiring'] += $personalStats['expiring'];
            $summary['expired'] += $personalStats['expired'];
            
            $data[] = [
                'license_type' => 'رخص القيادة الشخصية',
                'total' => $personalStats['total'],
                'active' => $personalStats['active'],
                'expiring' => $personalStats['expiring'],
                'expired' => $personalStats['expired']
            ];
        }
        
        if ($canViewVehicle) {
            $vehicleQuery = "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN expiration_date < CURDATE() THEN 1 END) as expired,
                COUNT(CASE WHEN expiration_date >= CURDATE() AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring,
                COUNT(CASE WHEN expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as active
                FROM vehicle_licenses $whereClause";
            
            $stmt = $conn->prepare($vehicleQuery);
            if (!empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            $vehicleStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $summary['total'] += $vehicleStats['total'];
            $summary['active'] += $vehicleStats['active'];
            $summary['expiring'] += $vehicleStats['expiring'];
            $summary['expired'] += $vehicleStats['expired'];
            
            $data[] = [
                'license_type' => 'رخص المركبات',
                'total' => $vehicleStats['total'],
                'active' => $vehicleStats['active'],
                'expiring' => $vehicleStats['expiring'],
                'expired' => $vehicleStats['expired']
            ];
        }
    } else {
        // Handle other report types with detailed data
        if (strpos($reportType, 'personal_') === 0 && $canViewPersonal) {
            $table = 'personal_license_overview';
            $statusFilter = '';
            
            if ($reportType === 'personal_expired') {
                $statusFilter = ' AND expiration_date < CURDATE()';
            } elseif ($reportType === 'personal_expiring') {
                $statusFilter = ' AND expiration_date >= CURDATE() AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
            } elseif ($reportType === 'personal_active') {
                $statusFilter = ' AND expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
            }
            
            $query = "SELECT * FROM $table $whereClause $statusFilter ORDER BY expiration_date ASC";
            
        } elseif (strpos($reportType, 'vehicle_') === 0 && $canViewVehicle) {
            $table = 'vehicle_license_overview';
            $statusFilter = '';
            
            if ($reportType === 'vehicle_expired') {
                $statusFilter = ' AND expiration_date < CURDATE()';
            } elseif ($reportType === 'vehicle_expiring') {
                $statusFilter = ' AND expiration_date >= CURDATE() AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
            } elseif ($reportType === 'vehicle_active') {
                $statusFilter = ' AND expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
            }
            
            $query = "SELECT * FROM $table $whereClause $statusFilter ORDER BY expiration_date ASC";
        }
        
        if (isset($query)) {
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add status calculation to each row and calculate summary
            $summary = [
                'total' => count($data),
                'active' => 0,
                'expiring' => 0,
                'expired' => 0
            ];
            
            foreach ($data as &$row) {
                if ($row['expiration_date'] < date('Y-m-d')) {
                    $row['status'] = 'expired';
                    $summary['expired']++;
                } elseif ($row['expiration_date'] <= date('Y-m-d', strtotime('+30 days'))) {
                    $row['status'] = 'expiring';
                    $summary['expiring']++;
                } else {
                    $row['status'] = 'active';
                    $summary['active']++;
                }
            }
            unset($row); // Break reference
        }
    }
    
    // Validate that we have data
    if (empty($data) && empty($summary)) {
        die('<h1>Error</h1><p>لا توجد بيانات للتصدير</p>');
    }
    
    // Generate filename
    $reportTitles = [
        'personal_expired' => 'رخص_القيادة_المنتهية',
        'personal_expiring' => 'رخص_القيادة_ستنتهي_قريباً',
        'personal_active' => 'رخص_القيادة_النشطة',
        'vehicle_expired' => 'رخص_المركبات_المنتهية',
        'vehicle_expiring' => 'رخص_المركبات_ستنتهي_قريباً',
        'vehicle_active' => 'رخص_المركبات_النشطة',
        'all_summary' => 'ملخص_شامل_التراخيص'
    ];
    
    $reportTitle = $reportTitles[$reportType] ?? 'تقرير_التراخيص';
    $filename = $reportTitle . '_' . date('Y-m-d_H-i-s');
    
    if ($exportFormat === 'excel') {
        exportToExcel($data, $summary, $reportTitle, $filename);
    } elseif ($exportFormat === 'pdf') {
        exportToPDF($data, $summary, $reportTitle, $filename);
    } else {
        die('<h1>Error</h1><p>صيغة التصدير غير مدعومة</p>');
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    error_log("Export error trace: " . $e->getTraceAsString());
    die('<h1>Error</h1><p>خطأ في التصدير: ' . $e->getMessage() . '</p><p><small>تفاصيل إضافية: ' . $e->getFile() . ':' . $e->getLine() . '</small></p>');
}

function exportToExcel($data, $summary, $reportTitle, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "body { font-family: 'Arial', 'Tahoma', sans-serif; direction: rtl; }";
    echo ".header { background-color: #2c3e50; color: white; text-align: center; padding: 20px; margin-bottom: 20px; }";
    echo ".header h1 { margin: 0; font-size: 24px; }";
    echo ".header .date { font-size: 14px; margin-top: 10px; opacity: 0.9; }";
    echo ".summary-table { background-color: #ecf0f1; border: 2px solid #34495e; margin: 20px 0; }";
    echo ".summary-table th { background-color: #34495e; color: white; padding: 12px; text-align: center; font-weight: bold; border: 1px solid #2c3e50; }";
    echo ".summary-table td { padding: 10px; text-align: center; border: 1px solid #bdc3c7; }";
    echo ".summary-table .total { background-color: #3498db; color: white; font-weight: bold; }";
    echo ".summary-table .active { background-color: #27ae60; color: white; }";
    echo ".summary-table .expiring { background-color: #f39c12; color: white; }";
    echo ".summary-table .expired { background-color: #e74c3c; color: white; }";
    echo ".data-table { border: 2px solid #34495e; margin: 20px 0; }";
    echo ".data-table th { background-color: #34495e; color: white; padding: 12px; text-align: center; font-weight: bold; border: 1px solid #2c3e50; font-size: 14px; }";
    echo ".data-table td { padding: 8px; text-align: right; border: 1px solid #bdc3c7; font-size: 13px; }";
    echo ".data-table tr:nth-child(even) { background-color: #f8f9fa; }";
    echo ".data-table tr:nth-child(odd) { background-color: #ffffff; }";
    echo ".status-active { background-color: #d4edda; color: #155724; font-weight: bold; text-align: center; }";
    echo ".status-expiring { background-color: #fff3cd; color: #856404; font-weight: bold; text-align: center; }";
    echo ".status-expired { background-color: #f8d7da; color: #721c24; font-weight: bold; text-align: center; }";
    echo ".section-title { background-color: #34495e; color: white; padding: 15px; margin: 20px 0 0 0; text-align: center; font-size: 18px; font-weight: bold; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    // Header
    echo "<div class='header'>";
    echo "<h1>📊 $reportTitle</h1>";
    echo "<div class='date'>تاريخ الإنشاء: " . date('Y-m-d H:i:s') . " | نظام إدارة التراخيص</div>";
    echo "</div>";
    
    // Summary
    if (!empty($summary)) {
        echo "<div class='section-title'>📈 ملخص التقرير</div>";
        echo "<table class='summary-table' style='width: 100%; border-collapse: collapse;'>";
        echo "<tr>";
        foreach ($summary as $key => $value) {
            $labels = [
                'total' => 'المجموع',
                'active' => 'نشط',
                'expiring' => 'سينتهي قريباً',
                'expired' => 'منتهي'
            ];
            $label = $labels[$key] ?? $key;
            echo "<th>$label</th>";
        }
        echo "</tr>";
        echo "<tr>";
        foreach ($summary as $key => $value) {
            $class = '';
            switch($key) {
                case 'total': $class = 'total'; break;
                case 'active': $class = 'active'; break;
                case 'expiring': $class = 'expiring'; break;
                case 'expired': $class = 'expired'; break;
            }
            echo "<td class='$class'><strong>$value</strong></td>";
        }
        echo "</tr>";
        echo "</table>";
    }
    
    // Data
    if (!empty($data)) {
        echo "<div class='section-title'>📋 البيانات التفصيلية</div>";
        echo "<table class='data-table' style='width: 100%; border-collapse: collapse;'>";
        
        // Headers
        if (count($data) > 0) {
            // Define column order and translations
            $columnOrder = [
                'license_number', 'full_name', 'car_number', 'vehicle_type',
                'issue_date', 'expiration_date', 'status', 'days_until_expiry',
                'department_name', 'project_name', 'notes'
            ];
            
            $arabicHeaders = [
                'license_id' => 'رقم الترخيص',
                'license_number' => 'رقم الرخصة',
                'full_name' => 'الاسم الكامل',
                'car_number' => 'رقم المركبة',
                'vehicle_type' => 'نوع المركبة',
                'issue_date' => 'تاريخ الإصدار',
                'expiration_date' => 'تاريخ الانتهاء',
                'status' => 'الحالة',
                'days_until_expiry' => 'أيام للانتهاء',
                'days_expired' => 'أيام منذ الانتهاء',
                'department_name' => 'القسم',
                'project_name' => 'المشروع',
                'notes' => 'ملاحظات',
                'license_type' => 'نوع الترخيص',
                'count' => 'العدد',
                'total' => 'المجموع',
                'active' => 'نشط',
                'expiring' => 'ينتهي قريباً',
                'expired' => 'منتهي'
            ];
            
            // Hidden columns
            $hiddenColumns = ['license_id', 'created_at', 'updated_at', 'is_active', 
                            'front_image_path', 'back_image_path', 'project_id', 
                            'department_id', 'admin_id', 'days_expired'];
            
            // Get visible columns in order
            $allColumns = array_keys($data[0]);
            $visibleColumns = [];
            
            // Add ordered columns first
            foreach ($columnOrder as $col) {
                if (in_array($col, $allColumns) && !in_array($col, $hiddenColumns)) {
                    $visibleColumns[] = $col;
                }
            }
            
            // Add remaining visible columns
            foreach ($allColumns as $col) {
                if (!in_array($col, $visibleColumns) && !in_array($col, $hiddenColumns)) {
                    $visibleColumns[] = $col;
                }
            }
            
            echo "<tr>";
            foreach ($visibleColumns as $header) {
                $arabicHeader = $arabicHeaders[$header] ?? $header;
                echo "<th>$arabicHeader</th>";
            }
            echo "</tr>";
            
            // Data rows
            foreach ($data as $row) {
                echo "<tr>";
                foreach ($visibleColumns as $key) {
                    $cell = $row[$key] ?? '';
                    $class = '';
                    
                    if ($key === 'status') {
                        switch($cell) {
                            case 'active': 
                            case 'نشط': 
                                $class = 'status-active';
                                $cell = 'نشط';
                                break;
                            case 'expiring':
                            case 'سينتهي قريباً':
                                $class = 'status-expiring';
                                $cell = 'سينتهي قريباً';
                                break;
                            case 'expired':
                            case 'منتهي':
                                $class = 'status-expired';
                                $cell = 'منتهي';
                                break;
                        }
                    } elseif ($key === 'days_until_expiry' && $cell) {
                        $days = intval($cell);
                        if ($days < 0) {
                            $cell = abs($days) . ' يوم (منتهي)';
                            $class = 'status-expired';
                        } elseif ($days <= 30) {
                            $cell = $days . ' يوم';
                            $class = 'status-expiring';
                        } else {
                            $cell = $days . ' يوم';
                            $class = 'status-active';
                        }
                    } elseif (strpos($key, 'date') !== false && $cell) {
                        // Format dates nicely
                        $date = DateTime::createFromFormat('Y-m-d', $cell);
                        if ($date) {
                            $cell = $date->format('d/m/Y');
                        }
                    } elseif ($key === 'notes' && $cell && strlen($cell) > 50) {
                        // Truncate long notes for Excel
                        $cell = substr($cell, 0, 50) . '...';
                    }
                    
                    echo "<td class='$class'>" . htmlspecialchars($cell) . "</td>";
                }
                echo "</tr>";
            }
        }
        echo "</table>";
    }
    
    // Footer
    echo "<div style='margin-top: 30px; text-align: center; font-size: 12px; color: #7f8c8d; border-top: 1px solid #bdc3c7; padding-top: 15px;'>";
    echo "تم إنشاء هذا التقرير بواسطة نظام إدارة التراخيص | " . date('Y-m-d H:i:s');
    echo "</div>";
    
    echo "</body></html>";
}

function exportToPDF($data, $summary, $reportTitle, $filename) {
    // For PDF export, we'll create a beautiful HTML that can be printed to PDF
    header('Content-Type: text/html; charset=utf-8');
    
    echo "<!DOCTYPE html>";
    echo "<html dir='rtl' lang='ar'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<meta name='color-scheme' content='light'>";
    echo "<meta name='print-color-adjust' content='exact'>";
    echo "<title>$reportTitle</title>";
    echo "<style>";
    
    // Base styles
    echo "* { box-sizing: border-box; }";
    echo "body { font-family: 'Arial', 'Tahoma', sans-serif; margin: 0; padding: 20px; direction: rtl; background: #f8f9fa; color: #333; line-height: 1.6; }";
    echo "@page { margin: 2cm; }";
    
    // Header styles
    echo ".report-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }";
    echo ".report-header h1 { margin: 0; font-size: 28px; font-weight: bold; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }";
    echo ".report-header .subtitle { font-size: 16px; margin-top: 10px; opacity: 0.9; }";
    echo ".report-header .meta { font-size: 14px; margin-top: 15px; opacity: 0.8; }";
    
    // Control buttons
    echo ".controls { margin-bottom: 30px; text-align: center; }";
    echo ".btn { background: #007bff; color: white; border: none; padding: 12px 24px; margin: 0 5px; border-radius: 5px; cursor: pointer; font-size: 14px; transition: all 0.3s; }";
    echo ".btn:hover { background: #0056b3; transform: translateY(-2px); }";
    echo ".btn-secondary { background: #6c757d; }";
    echo ".btn-secondary:hover { background: #545b62; }";
    
    // Summary section
    echo ".summary-section { background: white; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }";
    echo ".section-title { font-size: 20px; color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #3498db; display: flex; align-items: center; }";
    echo ".section-title i { margin-left: 10px; color: #3498db; }";
    echo ".summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }";
    echo ".summary-card { text-align: center; padding: 20px; border-radius: 8px; color: white; font-weight: bold; }";
    echo ".summary-card.total { background: linear-gradient(135deg, #3498db, #2980b9); }";
    echo ".summary-card.active { background: linear-gradient(135deg, #27ae60, #229954); }";
    echo ".summary-card.expiring { background: linear-gradient(135deg, #f39c12, #e67e22); }";
    echo ".summary-card.expired { background: linear-gradient(135deg, #e74c3c, #c0392b); }";
    echo ".summary-card .number { font-size: 32px; font-weight: bold; margin-bottom: 5px; }";
    echo ".summary-card .label { font-size: 14px; opacity: 0.9; }";
    
    // Data table section
    echo ".data-section { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }";
    echo ".data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }";
    echo ".data-table th { background: linear-gradient(135deg, #34495e, #2c3e50); color: white; padding: 15px 10px; text-align: center; font-weight: bold; font-size: 14px; border: 1px solid #2c3e50; }";
    echo ".data-table td { padding: 12px 8px; text-align: right; border: 1px solid #ddd; font-size: 13px; }";
    echo ".data-table tbody tr:nth-child(even) { background-color: #f8f9fa; }";
    echo ".data-table tbody tr:nth-child(odd) { background-color: #ffffff; }";
    echo ".data-table tbody tr:hover { background-color: #e3f2fd; }";
    
    // Status styling
    echo ".status { padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 11px; text-align: center; }";
    echo ".status.active { background: #d4edda; color: #155724; }";
    echo ".status.expiring { background: #fff3cd; color: #856404; }";
    echo ".status.expired { background: #f8d7da; color: #721c24; }";
    
    // Footer
    echo ".report-footer { margin-top: 40px; text-align: center; padding: 20px; border-top: 2px solid #ecf0f1; color: #7f8c8d; font-size: 12px; }";
    
    // Print styles - محسن للطباعة والـ PDF
    echo "@media print {";
    echo "  * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }";
    echo "  .no-print { display: none !important; }";
    echo "  body { background: white !important; margin: 0; padding: 15px; font-size: 12px; }";
    echo "  .report-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; color: white !important; margin-bottom: 20px; page-break-after: avoid; }";
    echo "  .report-header h1 { color: white !important; }";
    echo "  .summary-section, .data-section { background: white !important; margin-bottom: 15px; page-break-inside: avoid; }";
    echo "  .summary-card.total { background: linear-gradient(135deg, #3498db, #2980b9) !important; color: white !important; }";
    echo "  .summary-card.active { background: linear-gradient(135deg, #27ae60, #229954) !important; color: white !important; }";
    echo "  .summary-card.expiring { background: linear-gradient(135deg, #f39c12, #e67e22) !important; color: white !important; }";
    echo "  .summary-card.expired { background: linear-gradient(135deg, #e74c3c, #c0392b) !important; color: white !important; }";
    echo "  .summary-grid { grid-template-columns: repeat(4, 1fr) !important; gap: 10px; }";
    echo "  .data-table { page-break-inside: avoid; font-size: 11px; }";
    echo "  .data-table th { background: linear-gradient(135deg, #34495e, #2c3e50) !important; color: white !important; border: 1px solid #2c3e50 !important; }";
    echo "  .data-table td { border: 1px solid #ddd !important; }";
    echo "  .data-table tbody tr:nth-child(even) { background-color: #f8f9fa !important; }";
    echo "  .data-table thead { display: table-header-group; }";
    echo "  .status.active { background: #d4edda !important; color: #155724 !important; }";
    echo "  .status.expiring { background: #fff3cd !important; color: #856404 !important; }";
    echo "  .status.expired { background: #f8d7da !important; color: #721c24 !important; }";
    echo "  .section-title { background: none !important; color: #2c3e50 !important; border-bottom: 2px solid #3498db !important; }";
    echo "  @page { margin: 1.5cm; size: A4; }";
    echo "}";
    
    // Mobile responsive
    echo "@media (max-width: 768px) {";
    echo "  .summary-grid { grid-template-columns: repeat(2, 1fr); }";
    echo "  .data-table { font-size: 11px; }";
    echo "  .data-table th, .data-table td { padding: 8px 4px; }";
    echo "}";
    
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    // Control buttons
    echo "<div class='controls no-print'>";
    echo "<button class='btn' onclick='printReport()'>🖨️ طباعة / حفظ كـ PDF</button>";
    echo "<button class='btn btn-secondary' onclick='window.close()'>❌ إغلاق</button>";
    echo "<div style='margin-top: 10px; font-size: 12px; color: #6c757d;'>";
    echo "💡 <strong>نصيحة:</strong> في نافذة الطباعة، اختر 'حفظ كـ PDF' للحصول على ملف PDF، أو اختر طابعتك للطباعة المباشرة";
    echo "</div>";
    echo "</div>";
    
    // Header
    echo "<div class='report-header'>";
    echo "<h1>📊 $reportTitle</h1>";
    echo "<div class='subtitle'>نظام إدارة التراخيص - تقرير شامل</div>";
    echo "<div class='meta'>تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "</div>";
    echo "</div>";
    
    // Summary
    if (!empty($summary)) {
        echo "<div class='summary-section'>";
        echo "<div class='section-title'><i>📈</i> ملخص التقرير</div>";
        echo "<div class='summary-grid'>";
        
        foreach ($summary as $key => $value) {
            $labels = [
                'total' => 'المجموع',
                'active' => 'نشط',
                'expiring' => 'سينتهي قريباً',
                'expired' => 'منتهي'
            ];
            $label = $labels[$key] ?? $key;
            echo "<div class='summary-card $key'>";
            echo "<div class='number'>$value</div>";
            echo "<div class='label'>$label</div>";
            echo "</div>";
        }
        echo "</div>";
        echo "</div>";
    }
    
    // Data
    if (!empty($data)) {
        echo "<div class='data-section'>";
        echo "<div class='section-title'><i>📋</i> البيانات التفصيلية (" . count($data) . " سجل)</div>";
        echo "<table class='data-table'>";
        
        // Headers
        if (count($data) > 0) {
            // Use same column order as Excel
            $columnOrder = [
                'license_number', 'full_name', 'car_number', 'vehicle_type',
                'issue_date', 'expiration_date', 'status', 'days_until_expiry',
                'department_name', 'project_name', 'notes'
            ];
            
            $arabicHeaders = [
                'license_id' => 'رقم الترخيص',
                'license_number' => 'رقم الرخصة',
                'full_name' => 'الاسم الكامل',
                'car_number' => 'رقم المركبة',
                'vehicle_type' => 'نوع المركبة',
                'issue_date' => 'تاريخ الإصدار',
                'expiration_date' => 'تاريخ الانتهاء',
                'status' => 'الحالة',
                'days_until_expiry' => 'أيام للانتهاء',
                'days_expired' => 'أيام منذ الانتهاء',
                'department_name' => 'القسم',
                'project_name' => 'المشروع',
                'notes' => 'ملاحظات',
                'license_type' => 'نوع الترخيص',
                'count' => 'العدد',
                'total' => 'المجموع',
                'active' => 'نشط',
                'expiring' => 'ينتهي قريباً',
                'expired' => 'منتهي'
            ];
            
            // Hidden columns
            $hiddenColumns = ['license_id', 'created_at', 'updated_at', 'is_active', 
                            'front_image_path', 'back_image_path', 'project_id', 
                            'department_id', 'admin_id', 'days_expired'];
            
            // Get visible columns in order
            $allColumns = array_keys($data[0]);
            $visibleColumns = [];
            
            // Add ordered columns first
            foreach ($columnOrder as $col) {
                if (in_array($col, $allColumns) && !in_array($col, $hiddenColumns)) {
                    $visibleColumns[] = $col;
                }
            }
            
            // Add remaining visible columns
            foreach ($allColumns as $col) {
                if (!in_array($col, $visibleColumns) && !in_array($col, $hiddenColumns)) {
                    $visibleColumns[] = $col;
                }
            }
            
            echo "<thead><tr>";
            foreach ($visibleColumns as $header) {
                $arabicHeader = $arabicHeaders[$header] ?? $header;
                echo "<th>$arabicHeader</th>";
            }
            echo "</tr></thead>";
            
            // Data rows
            echo "<tbody>";
            foreach ($data as $row) {
                echo "<tr>";
                foreach ($visibleColumns as $key) {
                    $cell = $row[$key] ?? '';
                    
                    if ($key === 'status') {
                        $statusClass = '';
                        $statusText = $cell;
                        switch($cell) {
                            case 'active': 
                            case 'نشط': 
                                $statusClass = 'active';
                                $statusText = 'نشط';
                                break;
                            case 'expiring':
                            case 'سينتهي قريباً':
                                $statusClass = 'expiring';
                                $statusText = 'سينتهي قريباً';
                                break;
                            case 'expired':
                            case 'منتهي':
                                $statusClass = 'expired';
                                $statusText = 'منتهي';
                                break;
                        }
                        echo "<td><span class='status $statusClass'>$statusText</span></td>";
                    } elseif ($key === 'days_until_expiry' && $cell) {
                        $days = intval($cell);
                        $statusClass = '';
                        if ($days < 0) {
                            $cell = abs($days) . ' يوم (منتهي)';
                            $statusClass = 'status expired';
                        } elseif ($days <= 30) {
                            $cell = $days . ' يوم';
                            $statusClass = 'status expiring';
                        } else {
                            $cell = $days . ' يوم';
                            $statusClass = 'status active';
                        }
                        echo "<td><span class='$statusClass'>$cell</span></td>";
                    } elseif (strpos($key, 'date') !== false && $cell) {
                        // Format dates nicely
                        $date = DateTime::createFromFormat('Y-m-d', $cell);
                        if ($date) {
                            $cell = $date->format('d/m/Y');
                        }
                        echo "<td>" . htmlspecialchars($cell) . "</td>";
                    } elseif ($key === 'notes' && $cell && strlen($cell) > 50) {
                        // Truncate long notes for PDF
                        $cell = substr($cell, 0, 50) . '...';
                        echo "<td style='font-size: 11px; color: #666;'>" . htmlspecialchars($cell) . "</td>";
                    } else {
                        echo "<td>" . htmlspecialchars($cell) . "</td>";
                    }
                }
                echo "</tr>";
            }
            echo "</tbody>";
        }
        echo "</table>";
        echo "</div>";
    }
    
    // Footer
    echo "<div class='report-footer'>";
    echo "<strong>نظام إدارة التراخيص</strong><br>";
    echo "تم إنشاء هذا التقرير في: " . date('Y-m-d H:i:s') . "<br>";
    echo "عدد السجلات: " . (isset($data) ? count($data) : 0) . " سجل";
    echo "</div>";
    
    ?>
    <script>
    console.log('🚀 JavaScript loaded successfully');

    window.onload = function() { 
        window.focus(); 
        console.log('✅ Window loaded and focused');
    };

    function printReport() {
        console.log('🖨️ Print button clicked');
        try {
            document.body.style.webkitPrintColorAdjust = 'exact';
            document.body.style.colorAdjust = 'exact';
            console.log('🎨 Color adjustments applied');
            
            window.print();
            console.log('✅ Print dialog opened');
        } catch (error) {
            console.error('❌ Print error:', error);
            alert('خطأ في فتح نافذة الطباعة: ' + error.message);
        }
    }



    // تحسين جودة الطباعة
    window.addEventListener('beforeprint', function() {
        console.log('📄 Preparing for print...');
        document.querySelectorAll('.summary-card, .data-table th, .status').forEach(function(el) {
            el.style.webkitPrintColorAdjust = 'exact';
            el.style.colorAdjust = 'exact';
        });
        console.log('🎨 All elements styled for print');
    });

    // معالجة بعد الطباعة
    window.addEventListener('afterprint', function() {
        console.log('✅ Print completed');
    });

    // إضافة معالج keyboard shortcut
    document.addEventListener('keydown', function(event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'p') {
            event.preventDefault();
            console.log('⌨️ Keyboard shortcut Ctrl+P pressed');
            printReport();
        }
    });
    </script>
    <?php
    
    echo "</body></html>";
}
?> 
