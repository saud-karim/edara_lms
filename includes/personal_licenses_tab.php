<!-- Personal Licenses Tab Content -->
<div class="row filters-row">
    <div class="col-md-3 col-sm-12">
        <div class="form-group">
            <label style="font-size: 12px; color: #666; margin-bottom: 5px;">البحث</label>
        <input type="text" id="personalSearchInput" class="form-control" placeholder="البحث في رخص القيادة...">
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="form-group">
            <label style="font-size: 12px; color: #666; margin-bottom: 5px;">القسم</label>
        <select id="personalDepartmentFilter" class="form-control">
            <option value="">جميع الأقسام</option>
        </select>
    </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="form-group">
            <label style="font-size: 12px; color: #666; margin-bottom: 5px;">المشروع</label>
            <select id="personalProjectFilter" class="form-control">
                <option value="">جميع المشاريع</option>
            </select>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="form-group">
            <label style="font-size: 12px; color: #666; margin-bottom: 5px;">الحالة</label>
        <select id="personalStatusFilter" class="form-control">
            <option value="">جميع الحالات</option>
            <option value="active">نشط</option>
            <option value="expiring">ينتهي قريباً</option>
            <option value="expired">منتهي الصلاحية</option>
        </select>
    </div>
    </div>
    <div class="col-md-3 col-sm-12">
        <div class="form-group">
            <label style="font-size: 12px; color: #666; margin-bottom: 5px; opacity: 0;">&nbsp;</label>
            <div class="btn-group" role="group" style="display: block; width: 100%;">
                <button id="personalRefreshBtn" class="btn btn-info btn-sm">
                <i class="glyphicon glyphicon-refresh"></i> تحديث
            </button>
                <a href="deleted_licenses.php?type=personal" class="btn btn-warning btn-sm">
                <i class="glyphicon glyphicon-trash"></i> المحذوفة
            </a>
            <?php if ($canAddPersonal): ?>
                <a href="add_license.php?type=personal" class="btn btn-success btn-sm">
                <i class="glyphicon glyphicon-plus"></i> إضافة رخصة
            </a>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="personalLoadingIndicator" class="loading-indicator text-center" style="display: none;">
    <i class="fa fa-spinner fa-spin fa-2x"></i>
    <p>جاري تحميل رخص القيادة الشخصية...</p>
</div>

<div id="personalLicensesContainer">
    <div class="table-responsive table-container">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>رقم الرخصة</th>
                    <th>اسم الموظف</th>
                    <th>القسم</th>
                    <th>المشروع</th>
                    <th>تاريخ الإصدار</th>
                    <th>تاريخ الانتهاء</th>
                    <th>الحالة</th>
                    <th>أضافها</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody id="personalLicensesTableBody">
            </tbody>
        </table>
    </div>
    
    <div id="personalPaginationContainer" class="text-center">
    </div>
</div>

<div id="personalNoDataMessage" class="no-data-message text-center" style="display: none; padding: 40px;">
    <i class="glyphicon glyphicon-user" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
    <h4 style="color: #999;">لا توجد رخص قيادة شخصية</h4>
    <p class="text-muted">لم يتم العثور على رخص قيادة شخصية تطابق معايير البحث الحالية</p>
    <?php if ($canAddPersonal): ?>
        <a href="add_license.php?type=personal" class="btn btn-primary">
            <i class="glyphicon glyphicon-plus"></i> إضافة أول رخصة قيادة شخصية
        </a>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Refresh button for personal licenses
    $('#personalRefreshBtn').on('click', function() {
        loadPersonalLicenses(1);
    });
});
</script> 