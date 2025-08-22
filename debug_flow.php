<?php
// Deep debug the dashboard.php flow

$dashboardFile = 'dashboard.php';
$content = file_get_contents($dashboardFile);

// Add flow debugging at key points
$debugPoints = [
    // Debug at start of admin section
    'if ($userRole === \'admin\') {' => 'if ($userRole === \'admin\') {
    echo "<!-- FLOW DEBUG: Entering admin section -->";
    error_log("FLOW DEBUG: Entering admin section for user " . getUserId());',
    
    // Debug permissions
    'if ($hasVehicleView || $hasPersonalView) {' => 'if ($hasVehicleView || $hasPersonalView) {
    echo "<!-- FLOW DEBUG: Has specific permissions - vehicle: " . ($hasVehicleView ? "true" : "false") . ", personal: " . ($hasPersonalView ? "true" : "false") . " -->";
    error_log("FLOW DEBUG: Has specific permissions - vehicle: " . ($hasVehicleView ? "true" : "false") . ", personal: " . ($hasPersonalView ? "true" : "false"));',
    
    // Debug final permissions  
    '$canViewPersonal = $hasPersonalView;
        $canViewVehicle = $hasVehicleView;' => '$canViewPersonal = $hasPersonalView;
        $canViewVehicle = $hasVehicleView;
    echo "<!-- FLOW DEBUG: Final permissions - canViewPersonal: " . ($canViewPersonal ? "true" : "false") . ", canViewVehicle: " . ($canViewVehicle ? "true" : "false") . " -->";
    error_log("FLOW DEBUG: Final permissions - canViewPersonal: " . ($canViewPersonal ? "true" : "false") . ", canViewVehicle: " . ($canViewVehicle ? "true" : "false"));',
    
    // Debug personal section entry
    'if ($canViewPersonal) {' => 'if ($canViewPersonal) {
            echo "<!-- FLOW DEBUG: Entering personal licenses section -->";
            error_log("FLOW DEBUG: Entering personal licenses section");',
            
    // Debug vehicle section entry  
    'if ($canViewVehicle) {' => 'if ($canViewVehicle) {
            echo "<!-- FLOW DEBUG: Entering vehicle licenses section -->";
            error_log("FLOW DEBUG: Entering vehicle licenses section");',
            
    // Debug filter creation
    '$personalLicenseFilter = getLicenseFilter(\'l\');' => '$personalLicenseFilter = getLicenseFilter(\'l\');
        echo "<!-- FLOW DEBUG: personalLicenseFilter = " . htmlspecialchars($personalLicenseFilter) . " -->";
        error_log("FLOW DEBUG: personalLicenseFilter = " . $personalLicenseFilter);',
        
    '$vehicleLicenseFilter = getLicenseFilter(\'l\');' => '$vehicleLicenseFilter = getLicenseFilter(\'l\');
        echo "<!-- FLOW DEBUG: vehicleLicenseFilter = " . htmlspecialchars($vehicleLicenseFilter) . " -->";
        error_log("FLOW DEBUG: vehicleLicenseFilter = " . $vehicleLicenseFilter);',
];

// Apply all debug points
foreach ($debugPoints as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

// Add final variable check right before HTML
$finalDebug = '
// ===== FINAL VARIABLE CHECK =====
echo "<!-- FINAL CHECK: personalTotalLicenses = " . (isset($personalTotalLicenses) ? $personalTotalLicenses : "NOT_SET") . " -->";
echo "<!-- FINAL CHECK: vehicleTotalLicenses = " . (isset($vehicleTotalLicenses) ? $vehicleTotalLicenses : "NOT_SET") . " -->";
echo "<!-- FINAL CHECK: canViewPersonal = " . (isset($canViewPersonal) ? ($canViewPersonal ? "true" : "false") : "NOT_SET") . " -->";
echo "<!-- FINAL CHECK: canViewVehicle = " . (isset($canViewVehicle) ? ($canViewVehicle ? "true" : "false") : "NOT_SET") . " -->";
echo "<!-- FINAL CHECK: userRole = " . ($userRole ?? "NOT_SET") . " -->";

error_log("FINAL CHECK - personalTotalLicenses: " . (isset($personalTotalLicenses) ? $personalTotalLicenses : "NOT_SET"));
error_log("FINAL CHECK - vehicleTotalLicenses: " . (isset($vehicleTotalLicenses) ? $vehicleTotalLicenses : "NOT_SET"));
error_log("FINAL CHECK - canViewPersonal: " . (isset($canViewPersonal) ? ($canViewPersonal ? "true" : "false") : "NOT_SET"));
error_log("FINAL CHECK - canViewVehicle: " . (isset($canViewVehicle) ? ($canViewVehicle ? "true" : "false") : "NOT_SET"));
error_log("FINAL CHECK - userRole: " . ($userRole ?? "NOT_SET"));
// ===== END FINAL CHECK =====
';

$content = str_replace(
    '<?php require_once \'includes/header.php\'; ?>',
    $finalDebug . '<?php require_once \'includes/header.php\'; ?>',
    $content
);

file_put_contents($dashboardFile, $content);

echo "✅ تم إضافة debug تفصيلي للـ flow! افتح dashboard.php وشوف HTML source للـ comments";
?> 