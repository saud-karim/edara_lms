// Fix Projects Loading Issues
// This file ensures projects are loaded correctly in all forms

console.log('🔧 Fix Projects Loading script loaded');

// Override jQuery.get to add better error handling
(function($) {
    const originalGet = $.get;
    $.get = function(url, data, success, dataType) {
        console.log('🌐 AJAX GET request to:', url);
        
        const xhr = originalGet.apply(this, arguments);
        
        xhr.fail(function(jqXHR, textStatus, errorThrown) {
            console.error('🚫 AJAX GET failed:');
            console.error('  URL:', url);
            console.error('  Status:', textStatus);
            console.error('  Error:', errorThrown);
            console.error('  Response:', jqXHR.responseText);
            console.error('  Status Code:', jqXHR.status);
        });
        
        return xhr;
    };
})(jQuery);

// Add global error handler for AJAX requests
$(document).ajaxError(function(event, xhr, settings, thrownError) {
    console.error('🚨 Global AJAX Error Handler:');
    console.error('  URL:', settings.url);
    console.error('  Type:', settings.type);
    console.error('  Status:', xhr.status);
    console.error('  Error:', thrownError);
    console.error('  Response Text:', xhr.responseText);
});

// Add global success handler for AJAX requests
$(document).ajaxSuccess(function(event, xhr, settings) {
    console.log('✅ AJAX Success:', settings.url);
});

// Function to test projects endpoint
function testProjectsEndpoint() {
    console.log('🧪 Testing projects endpoint...');
    
    $.ajax({
        url: 'php_action/get_projects_no_auth.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('✅ Projects endpoint test successful:', response);
        },
        error: function(xhr, status, error) {
            console.error('❌ Projects endpoint test failed:', status, error);
            console.error('Response:', xhr.responseText);
        }
    });
}

// Function to test departments endpoint
function testDepartmentsEndpoint() {
    console.log('🧪 Testing departments endpoint...');
    
    $.ajax({
        url: 'php_action/get_departments_no_auth.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('✅ Departments endpoint test successful:', response);
        },
        error: function(xhr, status, error) {
            console.error('❌ Departments endpoint test failed:', status, error);
            console.error('Response:', xhr.responseText);
        }
    });
}

// Test endpoints when document is ready
$(document).ready(function() {
    setTimeout(function() {
        testProjectsEndpoint();
        testDepartmentsEndpoint();
    }, 100);
}); 