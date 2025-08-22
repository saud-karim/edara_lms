/**
 * نظام Pagination الموحد - نظام إدارة التراخيص
 * يوفر pagination للجداول الكبيرة مع تصميم موحد
 */

class UnifiedPagination {
    constructor(options = {}) {
        this.options = {
            itemsPerPage: options.itemsPerPage || 10,
            currentPage: options.currentPage || 1,
            totalItems: options.totalItems || 0,
            container: options.container || '.pagination-container',
            table: options.table || '.table',
            onPageChange: options.onPageChange || null,
            showInfo: options.showInfo !== false,
            showPageNumbers: options.showPageNumbers !== false,
            maxPageNumbers: options.maxPageNumbers || 5
        };
        
        this.init();
    }
    
    init() {
        this.totalPages = Math.ceil(this.options.totalItems / this.options.itemsPerPage);
        this.render();
        this.bindEvents();
    }
    
    render() {
        const container = document.querySelector(this.options.container);
        if (!container) return;
        
        container.innerHTML = this.generateHTML();
    }
    
    generateHTML() {
        let html = '';
        
        // معلومات الصفحة
        if (this.options.showInfo) {
            const startItem = (this.options.currentPage - 1) * this.options.itemsPerPage + 1;
            const endItem = Math.min(this.options.currentPage * this.options.itemsPerPage, this.options.totalItems);
            
            html += `
                <div class="pagination-info">
                    عرض ${startItem} إلى ${endItem} من ${this.options.totalItems} نتيجة
                </div>
            `;
        }
        
        // Pagination
        if (this.totalPages > 1) {
            html += '<ul class="pagination">';
            
            // زر السابق
            html += this.generatePageItem(
                this.options.currentPage - 1,
                'السابق',
                this.options.currentPage === 1,
                'page-link'
            );
            
            // أرقام الصفحات
            if (this.options.showPageNumbers) {
                html += this.generatePageNumbers();
            }
            
            // زر التالي
            html += this.generatePageItem(
                this.options.currentPage + 1,
                'التالي',
                this.options.currentPage === this.totalPages,
                'page-link'
            );
            
            html += '</ul>';
        }
        
        return html;
    }
    
    generatePageNumbers() {
        let html = '';
        const maxPages = this.options.maxPageNumbers;
        let startPage = Math.max(1, this.options.currentPage - Math.floor(maxPages / 2));
        let endPage = Math.min(this.totalPages, startPage + maxPages - 1);
        
        // تعديل startPage إذا كان endPage قريب من النهاية
        if (endPage - startPage + 1 < maxPages) {
            startPage = Math.max(1, endPage - maxPages + 1);
        }
        
        // إضافة "..." في البداية إذا لزم الأمر
        if (startPage > 1) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        // أرقام الصفحات
        for (let i = startPage; i <= endPage; i++) {
            html += this.generatePageItem(i, i, false, 'page-link', i === this.options.currentPage);
        }
        
        // إضافة "..." في النهاية إذا لزم الأمر
        if (endPage < this.totalPages) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        return html;
    }
    
    generatePageItem(page, text, disabled, className, active = false) {
        if (disabled || page < 1 || page > this.totalPages) {
            return `<li class="page-item disabled"><span class="${className}">${text}</span></li>`;
        }
        
        const activeClass = active ? 'active' : '';
        return `<li class="page-item ${activeClass}"><a href="#" class="${className}" data-page="${page}">${text}</a></li>`;
    }
    
    bindEvents() {
        const container = document.querySelector(this.options.container);
        if (!container) return;
        
        container.addEventListener('click', (e) => {
            if (e.target.classList.contains('page-link') && !e.target.parentElement.classList.contains('disabled')) {
                e.preventDefault();
                
                const page = parseInt(e.target.dataset.page);
                if (page && page !== this.options.currentPage) {
                    this.goToPage(page);
                }
            }
        });
    }
    
    goToPage(page) {
        if (page < 1 || page > this.totalPages) return;
        
        this.options.currentPage = page;
        this.render();
        
        // استدعاء callback إذا كان موجود
        if (typeof this.options.onPageChange === 'function') {
            this.options.onPageChange(page);
        }
        
        // تمرير البيانات للجدول
        this.updateTableDisplay();
    }
    
    updateTableDisplay() {
        const table = document.querySelector(this.options.table);
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        const startIndex = (this.options.currentPage - 1) * this.options.itemsPerPage;
        const endIndex = startIndex + this.options.itemsPerPage;
        
        rows.forEach((row, index) => {
            if (index >= startIndex && index < endIndex) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // تحديث البيانات
    updateData(totalItems, currentPage = 1) {
        this.options.totalItems = totalItems;
        this.options.currentPage = currentPage;
        this.totalPages = Math.ceil(totalItems / this.options.itemsPerPage);
        this.render();
    }
    
    // الحصول على معلومات الصفحة الحالية
    getCurrentPageInfo() {
        return {
            currentPage: this.options.currentPage,
            totalPages: this.totalPages,
            itemsPerPage: this.options.itemsPerPage,
            totalItems: this.options.totalItems,
            startItem: (this.options.currentPage - 1) * this.options.itemsPerPage + 1,
            endItem: Math.min(this.options.currentPage * this.options.itemsPerPage, this.options.totalItems)
        };
    }
}

/**
 * دالة مساعدة لإنشاء Pagination بسرعة
 */
function createPagination(container, options) {
    return new UnifiedPagination({
        container: container,
        ...options
    });
}

/**
 * دالة مساعدة لتحديث Pagination
 */
function updatePagination(pagination, totalItems, currentPage = 1) {
    if (pagination && typeof pagination.updateData === 'function') {
        pagination.updateData(totalItems, currentPage);
    }
}

/**
 * دالة مساعدة لإنشاء Pagination للجداول
 */
function createTablePagination(tableSelector, options = {}) {
    const table = document.querySelector(tableSelector);
    if (!table) return null;
    
    const defaultOptions = {
        itemsPerPage: 10,
        showInfo: true,
        showPageNumbers: true,
        maxPageNumbers: 5,
        onPageChange: (page) => {
            // تحديث عرض الجدول تلقائياً
            const rows = table.querySelectorAll('tbody tr');
            const startIndex = (page - 1) * defaultOptions.itemsPerPage;
            const endIndex = startIndex + defaultOptions.itemsPerPage;
            
            rows.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    };
    
    const mergedOptions = { ...defaultOptions, ...options };
    
    // إنشاء container للـ pagination إذا لم يكن موجود
    let paginationContainer = table.nextElementSibling;
    if (!paginationContainer || !paginationContainer.classList.contains('pagination-container')) {
        paginationContainer = document.createElement('div');
        paginationContainer.className = 'pagination-container';
        table.parentNode.insertBefore(paginationContainer, table.nextSibling);
    }
    
    // حساب عدد الصفوف
    const totalRows = table.querySelectorAll('tbody tr').length;
    
    return createPagination(paginationContainer, {
        ...mergedOptions,
        totalItems: totalRows
    });
}

// تصدير الكلاس والدوال للاستخدام العام
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { UnifiedPagination, createPagination, updatePagination, createTablePagination };
} 