# License Management System

A comprehensive web-based license management system built with PHP, MySQL, and Bootstrap. This system allows organizations to manage employee licenses with role-based access control, automatic expiration notifications, and file upload capabilities.

## 🌟 Features

### Core Functionality
- **Multi-role Access Control**: Super Admin, Admin, and Regular User roles
- **License Management**: Create, Read, Update, Delete (CRUD) operations
- **Department-based Organization**: Organize licenses by departments
- **Project Assignment**: Associate licenses with specific projects
- **File Upload**: Upload and manage front/back images of licenses
- **Soft Delete**: Deactivate records instead of permanent deletion

### Advanced Features
- **Email Notifications**: Automatic notifications for expiring licenses
- **Search & Filter**: Advanced search and filtering capabilities
- **Responsive Design**: Mobile-friendly interface using Bootstrap
- **Security**: CSRF protection, input sanitization, role-based permissions
- **Dashboard Analytics**: Real-time statistics and quick actions
- **Export Functionality**: Export license data for reporting

## 🏗️ System Architecture

### User Roles
1. **Super Admin**
   - Full access to all features
   - User management (add/edit/delete users)
   - Department management
   - Access to all departments' data

2. **Admin**
   - Manage licenses for their assigned department
   - Add, edit, and delete licenses
   - View expiring licenses for their department
   - Receive email notifications

3. **Regular User**
   - Read-only access to all license records
   - View licenses across all departments
   - No edit, add, or delete permissions

### Database Schema
- **users**: User accounts with role-based access
- **departments**: Organization departments
- **projects**: Department-specific projects
- **licenses**: Main license records with expiration tracking
- **email_notifications**: Email notification logs

## 📁 Project Structure

```
License_Management_System/
├── config/
│   ├── config.php          # Main configuration
│   └── database.php        # Database connection
├── php_action/             # API endpoints
│   ├── get_licenses.php    # Fetch licenses with filtering
│   ├── add_license.php     # Add new license
│   ├── delete_license.php  # Soft delete license
│   ├── get_departments.php # Fetch departments
│   └── send_notifications.php # Email notifications
├── database/
│   └── license_management.sql # Database schema
├── assests/                # Static assets and resources
│   ├── bootstrap/
│   ├── jquery/
│   ├── font-awesome/
│   ├── jquery-ui/
│   ├── images/             # System images and logos
│   ├── icons/              # Interface icons
│   └── uploads/            # License image storage
├── includes/               # Common templates and helpers
│   ├── header.php          # Common header with navigation
│   ├── footer.php          # Common footer
│   └── image_helpers.php   # Image utility functions
├── auth.php                # Authentication & authorization
├── index.php               # Main entry point
├── login.php               # Login page
├── logout.php              # Logout functionality
├── dashboard.php           # Main dashboard
├── licenses.php            # License listing page
├── add_license.php         # Add license form
├── users.php               # User management (Super Admin)
├── cron_notifications.php  # Cron job for email notifications
└── README.md               # This file
```

## 🚀 Installation & Setup

### 1. Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (optional, for production dependencies)

### 2. Database Setup
1. Create a new MySQL database:
   ```sql
   CREATE DATABASE license_management;
   ```

2. Import the database schema:
   ```bash
   mysql -u your_username -p license_management < database/license_management.sql
   ```

3. Update database credentials in `config/database.php`:
   ```php
   private $host = 'localhost';
   private $db_name = 'license_management';
   private $username = 'your_username';
   private $password = 'your_password';
   ```

### 3. Configuration
1. Update site settings in `config/config.php`:
   ```php
   define('SITE_URL', 'http://your-domain.com/License_Management_System');
   ```

2. Configure email settings for notifications:
   ```php
   define('SMTP_HOST', 'your-smtp-host');
   define('SMTP_USERNAME', 'your-email@domain.com');
   define('SMTP_PASSWORD', 'your-email-password');
   define('FROM_EMAIL', 'noreply@yourdomain.com');
   ```

### 4. File Permissions
Set proper permissions for upload directory:
```bash
chmod 755 assests/uploads/
chown www-data:www-data assests/uploads/
```

### 5. Email Notifications (Optional)
Set up the cron job for automatic email notifications:
```bash
# Edit crontab
crontab -e

# Add this line to run daily at 9 AM
0 9 * * * /usr/bin/php /path/to/your/project/cron_notifications.php
```

## 👤 Default Login Credentials

The system comes with pre-configured demo accounts:

| Role | Username | Password | Access Level |
|------|----------|----------|--------------|
| Super Admin | `superadmin` | `admin123` | Full system access |
| HR Admin | `hr_admin` | `admin123` | HR department only |
| IT Admin | `it_admin` | `admin123` | IT department only |
| Finance Admin | `finance_admin` | `admin123` | Finance department only |
| Regular User | `user1` | `admin123` | Read-only access |
| Regular User | `user2` | `admin123` | Read-only access |

> **⚠️ Security Note**: Change all default passwords immediately after installation!

## 📱 Usage Guide

### For Super Admins
1. **User Management**: Navigate to "User Management" → "Manage Users"
2. **Add New Admin**: Go to "User Management" → "Add User"
3. **Department Setup**: Access "User Management" → "Departments"
4. **Full License Access**: View and manage licenses across all departments

### For Department Admins
1. **Add Licenses**: Click "License Management" → "Add License"
2. **Manage Department Licenses**: View licenses for your department only
3. **Expiring Licenses**: Monitor licenses expiring soon
4. **Update License Info**: Edit license details and upload images

### For Regular Users
1. **View All Licenses**: Read-only access to all license records
2. **Search & Filter**: Use advanced search to find specific licenses
3. **Department Browsing**: Browse licenses by department

## 🔧 API Endpoints

### License Management
- `GET php_action/get_licenses.php` - Fetch licenses with pagination and filters
- `POST php_action/add_license.php` - Add new license with file upload
- `POST php_action/delete_license.php` - Soft delete license

### Department Management
- `GET php_action/get_departments.php` - Fetch departments based on user role

### Notifications
- `GET php_action/send_notifications.php?ajax=1` - Trigger email notifications manually

## 🔒 Security Features

### Authentication & Authorization
- Session-based authentication
- Role-based access control (RBAC)
- CSRF token protection
- Input sanitization and validation

### Data Security
- SQL injection prevention using prepared statements
- XSS protection through input sanitization
- File upload validation and type checking
- Soft delete for data recovery

### Password Security
- bcrypt password hashing
- Secure password storage
- Session timeout management

## 📧 Email Notification System

### Automatic Notifications
The system automatically sends email notifications for:
- **Expiring Licenses**: 30 days before expiration
- **Expired Licenses**: Daily reminders for expired licenses

### Email Templates
- Professional HTML email templates
- Detailed license information
- Direct links to edit licenses
- Automatic department admin targeting

### Notification Logging
- Track all sent notifications
- Prevent duplicate notifications
- Email delivery status monitoring

## 🎨 Customization

### Styling
- Bootstrap 3 framework for responsive design
- Custom CSS in `includes/header.php` for additional styling
- Font Awesome icons for interface elements

### Configuration
- Easily configurable in `config/config.php`
- Modular authentication system
- Extensible role-based permissions

## 🚨 Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Check database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Verify database exists and permissions are correct

2. **File Upload Issues**
   - Check `assests/uploads/` directory permissions
   - Verify PHP `upload_max_filesize` setting
   - Ensure adequate disk space

3. **Email Notifications Not Working**
   - Verify SMTP settings in `config/config.php`
   - Check server mail() function or SMTP configuration
   - Review email logs for delivery issues

4. **Permission Denied Errors**
   - Check user roles and department assignments
   - Verify session is active
   - Ensure proper authentication

### Debug Mode
Enable error reporting for debugging:
```php
// Add to config/config.php for development
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 🔄 Updates & Maintenance

### Regular Maintenance
- Monitor disk space for uploaded images
- Review email notification logs
- Update passwords regularly
- Backup database regularly

### System Updates
- Keep PHP and MySQL updated
- Update Bootstrap and jQuery libraries
- Review and update security configurations

## 📞 Support

### Development Team
This system was developed as a complete license management solution with:
- Modern PHP development practices
- Responsive web design
- Comprehensive security measures
- Extensive documentation

### Contributing
To contribute to this project:
1. Follow the existing code structure
2. Maintain security best practices
3. Update documentation for new features
4. Test thoroughly before deployment

## 📄 License

This License Management System is developed for internal organizational use. Modify and adapt according to your organization's requirements.

---

## 🎯 Key Benefits

✅ **Organized License Tracking**: Never lose track of important licenses
✅ **Automated Reminders**: Proactive expiration notifications
✅ **Role-Based Security**: Appropriate access for different user types
✅ **Mobile Responsive**: Access from any device
✅ **Easy File Management**: Upload and organize license images
✅ **Comprehensive Reporting**: Track and analyze license data
✅ **Scalable Architecture**: Grows with your organization

---

*For technical support or feature requests, please contact your system administrator.* 