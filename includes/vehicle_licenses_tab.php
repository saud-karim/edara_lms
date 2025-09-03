<!-- Vehicle Licenses Tab Content -->
<div class="row filters-row">
    <div class="col-md-3 col-sm-12">
        <div class="form-group">
            <label for="vehicleSearchInput" style="font-size: 12px; color: #666; margin-bottom: 5px;">البحث برقم المركبة</label>
            <div class="input-group">
                <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
                <input type="text" id="vehicleSearchInput" class="form-control" 
                       placeholder="مثال: 7894 ن ت ي" style="font-size: 14px;">
            </div>
            <small class="help-block" style="margin-top: 2px;">اكتب الأرقام والحروف معاً أو منفصلة</small>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="form-group">
            <label style="font-size: 12px; color: #666; margin-bottom: 5px;">القسم</label>
            <select id="vehicleDepartmentFilter" class="form-control">
                <option value="">جميع الأقسام</option>
            </select>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="form-group">
            <label style="font-size: 12px; color: #666; margin-bottom: 5px;">المشروع</label>
            <select id="vehicleProjectFilter" class="form-control">
                <option value="">جميع المشاريع</option>
            </select>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="form-group">
            <label style="font-size: 12px; color: #666; margin-bottom: 5px;">الحالة</label>
            <select id="vehicleStatusFilter" class="form-control">
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
                <a href="deleted_licenses.php?type=vehicle" class="btn btn-warning btn-sm">
                    <i class="glyphicon glyphicon-trash"></i> المحذوفة
                </a>
                <?php if ($canAddVehicle): ?>
                <a href="add_license.php?type=vehicle" class="btn btn-success btn-sm">
                    <i class="glyphicon glyphicon-plus"></i> إضافة رخصة مركبة
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="vehicleLoadingIndicator" class="loading-indicator text-center" style="display: none;">
    <i class="fa fa-spinner fa-spin fa-2x"></i>
    <p>جاري تحميل رخص المركبات...</p>
</div>

<div id="vehicleLicensesContainer">
    <div class="table-responsive table-container">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>رقم المركبة</th>
                    <th>نوع المركبة</th>
                    <th>فئة الرخصة</th>
                    <th>سنة الفحص</th>
                    <th>القسم</th>
                    <th>المشروع</th>
                    <th>تاريخ الإصدار</th>
                    <th>تاريخ الانتهاء</th>
                    <th>الحالة</th>
                    <th>أضافها</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody id="vehicleLicensesTableBody">
                <!-- Vehicle licenses will be loaded here -->
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div id="vehiclePagination" class="text-center">
        <!-- Pagination will be loaded here -->
    </div>
</div>

<div id="vehicleNoDataMessage" class="no-data-message text-center" style="display: none; padding: 40px;">
    <i class="glyphicon glyphicon-road" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
    <h4 style="color: #999;">لا توجد رخص مركبات</h4>
    <p class="text-muted">لم يتم العثور على رخص مركبات تطابق معايير البحث الحالية</p>
    <?php if ($canAddVehicle): ?>
        <a href="add_license.php?type=vehicle" class="btn btn-primary">
            <i class="glyphicon glyphicon-plus"></i> إضافة أول رخصة مركبة
        </a>
    <?php endif; ?>
</div> 