// Admin Teams System JavaScript for Edit User
$(document).ready(function() {
    
    // Initialize admin teams handling after page loads
    setTimeout(function() {
        handleRoleChange();
        console.log('Admin Teams for Edit User initialized');
    }, 1000);
    
    // Handle role selection change
    $('#role').on('change', function() {
        handleRoleChange();
    });
    
    // Handle parent admin selection change
    $('#parentAdminId').on('change', function() {
        updateAdminTypeDisplay();
    });
    
    // Handle department change
    $('#department').on('change', function() {
        if ($('#role').val() === 'admin') {
            loadHeadAdmins();
        }
    });
});

// Handle admin team fields based on role
function handleRoleChange() {
    const selectedRole = $('#role').val();
    const $parentAdminGroup = $('#parentAdminGroup');
    const $adminTypeDisplay = $('#adminTypeDisplay');
    const $parentAdminSelect = $('#parentAdminId');
    
    if (selectedRole === 'admin') {
        $parentAdminGroup.show();
        $adminTypeDisplay.show();
        loadHeadAdmins();
    } else {
        $parentAdminGroup.hide();
        $adminTypeDisplay.hide();
        $parentAdminSelect.empty().append('<option value="">-- اختر المدير المباشر --</option>');
    }
}

// Load head admins for parent admin dropdown
function loadHeadAdmins() {
    const departmentId = $('#department').val();
    const currentUserId = $('input[name="user_id"]').val(); // Exclude current user
    
    if (!departmentId) {
        $('#parentAdminId').empty().append('<option value="">اختر القسم أولاً</option>');
        return;
    }
    
    $.ajax({
        url: 'php_action/get_head_admins.php',
        method: 'POST',
        data: {
            department_id: departmentId,
            exclude_user_id: currentUserId
        },
        dataType: 'json',
        success: function(response) {
            const $select = $('#parentAdminId');
            $select.empty().append('<option value="">-- اختر المدير المباشر --</option>');
            
            if (response.success && response.data.length > 0) {
                response.data.forEach(function(admin) {
                    $select.append(`<option value="${admin.user_id}">${admin.full_name} (${admin.username})</option>`);
                });
            } else {
                $select.append('<option value="" disabled>لا يوجد مديرين رئيسيين في هذا القسم</option>');
            }
            
            // Set current parent admin if editing
            const currentParentId = $select.data('current-parent') || '';
            if (currentParentId) {
                $select.val(currentParentId);
            }
            
            updateAdminTypeDisplay();
        },
        error: function() {
            console.error('خطأ في تحميل المديرين الرئيسيين');
            $('#parentAdminId').empty().append('<option value="">خطأ في التحميل</option>');
        }
    });
}

// Update admin type display based on parent admin selection
function updateAdminTypeDisplay() {
    const parentAdminId = $('#parentAdminId').val();
    const $adminTypeText = $('#adminTypeText');
    
    if (parentAdminId) {
        const parentName = $('#parentAdminId option:selected').text();
        $adminTypeText.text(`مدير فرعي تحت: ${parentName}`);
    } else {
        $adminTypeText.text('مدير رئيسي مستقل');
    }
} 