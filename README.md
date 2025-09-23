# Employee Leave Management System (ELMS)

A comprehensive web-based leave management system designed for St. Francis Xavier Hospital to streamline employee leave requests, approvals, and tracking processes.

## 🏥 About

The Employee Leave Management System (ELMS) is a robust PHP-based application that facilitates efficient management of employee leave requests within healthcare organizations. Built specifically for St. Francis Xavier Hospital, this system provides role-based access for administrators, managers, and employees to handle leave applications seamlessly.

## ✨ Features

### 👤 **Multi-Role System**
- **Admin**: Full system access, user management, policy configuration
- **Manager**: Team management, leave approval/rejection, department oversight
- **Employee**: Leave requests, balance tracking, personal dashboard

### 📋 **Leave Management**
- Multiple leave types (Annual, Sick, Maternity, Paternity, Emergency, Unpaid)
- Leave balance tracking with yearly entitlements
- Configurable leave policies with carry-forward options
- Request history and status tracking

### 🏢 **Organizational Features**
- Department management with assigned managers
- User profile management with contact information
- Notification system for leave requests and updates
- Audit logging for system activities

### 📊 **Reporting & Analytics**
- Leave request reports
- Department-wise leave statistics
- Historical data tracking
- Calendar view for leave planning

## 🛠️ Technology Stack

- **Backend**: PHP 8.2+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Server**: Apache (via XAMPP)
- **Architecture**: MVC pattern with modular design

## 📋 Prerequisites

Before setting up the ELMS, ensure you have the following installed:

- **XAMPP** (includes Apache, MySQL, PHP)
- **Web Browser** (Chrome, Firefox, Safari, Edge)
- **Git** (for version control)

## 🚀 Installation

### 1. **Environment Setup**
```bash
# Download and install XAMPP from https://www.apachefriends.org/
# Start XAMPP Control Panel
# Ensure Apache and MySQL services are running
```

### 2. **Project Setup**
```bash
# Clone or download the project files
# Place all files in your XAMPP htdocs directory
# For example: C:\xampp\htdocs\n_elms\ (Windows)
# Or: /opt/lampp/htdocs/n_elms/ (Linux/Mac)
```

### 3. **Database Configuration**
```bash
# Open phpMyAdmin (http://localhost/phpmyadmin)
# Create a new database named 'elmsdb'
# Import the database schema from sql/elmsdb.sql
```

### 4. **Configuration**
```php
// Update includes/config.php if needed
define('DB_HOST', 'localhost');
define('DB_NAME', 'elmsdb');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/n_elms');
```

### 5. **Access the Application**
```bash
# Open your web browser and navigate to:
# http://localhost/n_elms/
```

## 🔐 Default Login Credentials

The system comes with pre-configured demo accounts:

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@sfxhospital.org | password123 |
| **Manager** | manager@sfxhospital.org | password123 |
| **Employee** | employee@sfxhospital.org | password123 |

## 📁 Project Structure

```
n_elms/
├── index.php                 # Login page
├── dashboard.php             # Main dashboard
├── logout.php                # Logout functionality
├── unauthorized.php          # Access denied page
├── assets/                   # Images and static files
│   ├── bg1.jpeg - bg7.jpeg   # Background images
│   ├── logo.png              # Hospital logo
│   └── default-avatar.png    # Default user avatar
├── css/
│   └── style.css             # Main stylesheet
├── includes/                 # Core PHP files
│   ├── config.php            # Database configuration
│   ├── database.php          # Database connection
│   ├── auth.php              # Authentication functions
│   ├── header.php            # Page header
│   ├── footer.php            # Page footer
│   ├── sidebar.php           # Navigation sidebar
│   └── notifications_helper.php # Notification utilities
├── js/
│   └── script.js             # JavaScript functionality
├── pages/                    # Application pages
│   ├── admin/                # Admin-specific pages
│   ├── manager/              # Manager-specific pages
│   ├── employee/             # Employee-specific pages
│   ├── manager-admin/        # Shared manager/admin pages
│   └── *.php                 # General pages
├── sql/                      # Database files
│   ├── elmsdb.sql            # Main database schema
│   ├── elms_database.sql     # Alternative schema
│   └── sample_notifications.sql # Sample data
└── README.md                 # This file
```

## 🗄️ Database Schema

The system uses the following main tables:

- **users**: User accounts with roles and profiles
- **departments**: Organizational departments
- **leave_requests**: Leave applications and their status
- **leave_balances**: Employee leave entitlements and usage
- **leave_policies**: Configurable leave policies
- **notifications**: System notifications
- **audit_log**: System activity tracking

## 🎯 Key Features Explained

### Leave Request Workflow
1. **Employee** submits leave request with dates and reason
2. **Manager** reviews and approves/rejects the request
3. **Admin** can override manager decisions if needed
4. **System** tracks leave balances and updates automatically

### Leave Types
- **Annual Leave**: Standard vacation time (21 days)
- **Sick Leave**: Medical leave (14 days)
- **Maternity Leave**: Extended leave for new mothers (90 days)
- **Paternity Leave**: Leave for new fathers (14 days)
- **Emergency Leave**: Short-term emergency situations (5 days)
- **Unpaid Leave**: Leave without pay (unlimited)

### Approval Flows
- **Manager Approval**: Standard approval process
- **Admin Approval**: Required for certain leave types
- **Both**: Requires approval from both manager and admin

## 🔧 Configuration Options

### Leave Policies
- Configure entitlement days for each leave type
- Set carry-forward allowances
- Define approval workflows
- Enable/disable specific leave types

### User Management
- Create and manage user accounts
- Assign roles and departments
- Reset passwords
- Deactivate accounts

### Department Management
- Create organizational departments
- Assign department managers
- Track department-specific leave patterns

## 📱 User Interface

The system features a responsive design that works on:
- Desktop computers
- Tablets
- Mobile devices

### Navigation
- **Dashboard**: Overview of leave status and notifications
- **Apply**: Submit new leave requests
- **History**: View past leave requests
- **Profile**: Manage personal information
- **Calendar**: View leave calendar (for managers/admins)

## 🔒 Security Features

- **Session Management**: Secure user sessions
- **Role-Based Access**: Different permissions per user role
- **Input Validation**: Form data sanitization
- **SQL Injection Prevention**: Prepared statements
- **Password Hashing**: Secure password storage

## 🐛 Troubleshooting

### Common Issues

**Database Connection Error**
```bash
# Check if MySQL service is running in XAMPP
# Verify database credentials in includes/config.php
# Ensure database 'elmsdb' exists
```

**Login Issues**
```bash
# Use the demo credentials provided above
# Check if user account is active in database
# Verify password matches 'password123' for demo accounts
```

**File Permissions**
```bash
# Ensure all files have proper read/write permissions
# Check uploads directory exists and is writable
```

## 📞 Support

For technical support or questions:
- Check the help pages within the application
- Review the FAQ section
- Contact your system administrator

## 🔄 Updates & Maintenance

### Regular Tasks
- Backup database regularly
- Monitor disk space for uploaded files
- Review audit logs for security
- Update user passwords periodically

### Backup Procedure
```bash
# Export database via phpMyAdmin
# Copy assets and uploaded files
# Store backups in secure location
```

## 📄 License

This software is proprietary to St. Francis Xavier Hospital. All rights reserved.

## 👥 Development Team

Developed for St. Francis Xavier Hospital
- **Organization**: St. Francis Xavier Hospital
- **System**: Employee Leave Management System (ELMS)
- **Version**: 1.0.0

---

*For questions or support, please contact your system administrator or refer to the help documentation within the application.*