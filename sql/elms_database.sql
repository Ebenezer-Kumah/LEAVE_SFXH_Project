-- sql/elms_database.sql

-- Create database
CREATE DATABASE IF NOT EXISTS elmsdb;
USE elmsdb;

-- Users table
CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'employee',
    department_id INT,
    contact_info VARCHAR(255),
    profile_picture VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Departments table
CREATE TABLE Departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    manager_id INT,
    description TEXT,
    FOREIGN KEY (manager_id) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- Add foreign key constraint to Users table
ALTER TABLE Users
ADD FOREIGN KEY (department_id) REFERENCES Departments(department_id) ON DELETE SET NULL;

-- Leave_Policies table
CREATE TABLE Leave_Policies (
    policy_id INT AUTO_INCREMENT PRIMARY KEY,
    leave_type VARCHAR(50) NOT NULL,
    entitlement_days INT NOT NULL,
    carry_forward BOOLEAN DEFAULT FALSE,
    max_carry_forward_days INT DEFAULT 0,
    approval_flow ENUM('manager', 'admin', 'both') DEFAULT 'manager',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE Leave_Policies ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;

-- Leave_Requests table
CREATE TABLE Leave_Requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    manager_id INT,
    admin_override BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- Leave_Balances table
CREATE TABLE Leave_Balances (
    balance_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    total_entitlement INT NOT NULL DEFAULT 0,
    used_days INT NOT NULL DEFAULT 0,
    remaining_days INT NOT NULL DEFAULT 0,
    year YEAR NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE Notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('email', 'system') DEFAULT 'system',
    status ENUM('unread', 'read') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Audit_Log table
CREATE TABLE Audit_Log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- Insert sample departments
INSERT INTO Departments (department_name, description) VALUES
('Human Resources', 'Handles all HR related activities'),
('Medicine', 'Medical department for doctors and physicians'),
('Nursing', 'Nursing staff department'),
('Administration', 'Hospital administration department'),
('IT', 'Information Technology department');

-- Insert default admin user (password: admin123)
INSERT INTO Users (name, email, password, role, department_id, contact_info) VALUES
('System Administrator', 'admin@sfxhospital.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 'Ext: 1001'),
('John Manager', 'manager@sfxhospital.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 3, 'Ext: 1002'),
('Jane Employee', 'employee@sfxhospital.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 3, 'Ext: 1003');

-- Update department managers
UPDATE Departments SET manager_id = 2 WHERE department_id = 3;

-- Insert leave policies
INSERT INTO Leave_Policies (leave_type, entitlement_days, carry_forward, max_carry_forward_days, approval_flow) VALUES
('Annual Leave', 21, TRUE, 7, 'manager'),
('Sick Leave', 14, FALSE, 0, 'manager'),
('Maternity Leave', 90, FALSE, 0, 'admin'),
('Paternity Leave', 14, FALSE, 0, 'manager'),
('Emergency Leave', 5, FALSE, 0, 'manager'),
('Unpaid Leave', 0, FALSE, 0, 'admin');

-- Insert sample leave balances
INSERT INTO Leave_Balances (employee_id, leave_type, total_entitlement, used_days, remaining_days, year) VALUES
(3, 'Annual Leave', 21, 5, 16, YEAR(CURDATE())),
(3, 'Sick Leave', 14, 2, 12, YEAR(CURDATE())),
(3, 'Emergency Leave', 5, 1, 4, YEAR(CURDATE()));

-- Insert sample leave requests
INSERT INTO Leave_Requests (employee_id, leave_type, start_date, end_date, reason, status, manager_id) VALUES
(3, 'Annual Leave', CURDATE() + INTERVAL 7 DAY, CURDATE() + INTERVAL 10 DAY, 'Family vacation', 'pending', 2),
(3, 'Sick Leave', CURDATE() - INTERVAL 3 DAY, CURDATE() - INTERVAL 2 DAY, 'Flu', 'approved', 2);