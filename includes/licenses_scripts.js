// Additional JavaScript for vehicle licenses tab
$(document).ready(function() {
    // Refresh button for vehicle licenses
    $('#vehicleRefreshBtn').on('click', function() {
        loadVehicleLicenses(1);
    });
    
    // Auto-refresh functionality - refresh when switching to vehicle tab if not loaded yet
    let vehicleTabLoaded = false;
    
    $('a[href="#vehicle-licenses"]').on('shown.bs.tab', function() {
        if (!vehicleTabLoaded) {
            loadVehicleLicenses(1);
            vehicleTabLoaded = true;
        }
    });
}); 