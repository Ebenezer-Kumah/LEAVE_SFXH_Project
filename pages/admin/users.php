<?php
// pages/admin/users.php

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle form actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// Add new user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    try {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = password_hash('password123', PASSWORD_DEFAULT); // Default password
        $role = $_POST['role'];
        $department_id = $_POST['department_id'] ?: null;
        $contact_info = trim($_POST['contact_info']);
        
        $query = "INSERT INTO Users (name, email, password, role, department_id, contact_info) 
                  VALUES (:name, :email, :password, :role, :department_id, :contact_info)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':department_id', $department_id);
        $stmt->bindParam(':contact_info', $contact_info);
        
        if ($stmt->execute()) {
            $message = 'User added successfully! Default password: password123';
        }
    } catch (PDOException $e) {
        $error = 'Error adding user: ' . $e->getMessage();
    }
}

// Update user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    try {
        $user_id = $_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $department_id = $_POST['department_id'] ?: null;
        $contact_info = trim($_POST['contact_info']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE Users 
                  SET name = :name, email = :email, role = :role, 
                      department_id = :department_id, contact_info = :contact_info, is_active = :is_active
                  WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':department_id', $department_id);
        $stmt->bindParam(':contact_info', $contact_info);
        $stmt->bindParam(':is_active', $is_active);
        
        if ($stmt->execute()) {
            $message = 'User updated successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error updating user: ' . $e->getMessage();
    }
}

// Delete user
if ($action == 'delete' && isset($_GET['id'])) {
    try {
        $user_id = $_GET['id'];
        $query = "UPDATE Users SET is_active = 0 WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $message = 'User deactivated successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error deactivating user: ' . $e->getMessage();
    }
}

// Reset password
if ($action == 'reset_password' && isset($_GET['id'])) {
    try {
        $user_id = $_GET['id'];
        $password = password_hash('password123', PASSWORD_DEFAULT);
        $query = "UPDATE Users SET password = :password WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $message = 'Password reset successfully! New password: password123';
        }
    } catch (PDOException $e) {
        $error = 'Error resetting password: ' . $e->getMessage();
    }
}

// Get all users with department information
$query = "SELECT u.*, d.department_name 
          FROM Users u 
          LEFT JOIN Departments d ON u.department_id = d.department_id 
          ORDER BY u.role, u.name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all departments for dropdown
$query = "SELECT * FROM Departments ORDER BY department_name";
$stmt = $db->prepare($query);
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$query = "SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) as managers,
            SUM(CASE WHEN role = 'employee' THEN 1 ELSE 0 END) as employees,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
          FROM Users";
$stmt = $db->prepare($query);
$stmt->execute();
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>User Management</h2>
    <p>Manage all system users, their roles, and permissions.</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-success">
        <span><?php echo $message; ?></span>
        <button class="alert-close"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <span><?php echo $error; ?></span>
        <button class="alert-close"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<div class="dashboard-cards">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Total Users</h3>
            <div class="card-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $user_stats['total_users']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> All system users
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Admins</h3>
            <div class="card-icon">
                <i class="fas fa-user-shield"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $user_stats['admins']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> System administrators
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Managers</h3>
            <div class="card-icon">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $user_stats['managers']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Department managers
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Employees</h3>
            <div class="card-icon">
                <i class="fas fa-user"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $user_stats['employees']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Regular employees
        </div>
    </div>
</div>

<div class="content-row">
    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>All Users</h3>
                <button class="btn btn-primary" onclick="openModal('addUserModal')">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            </div>
                                            <div class="user-details">
                                                <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['department_name'] ? htmlspecialchars($user['department_name']) : '—'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" data-user-id="<?php echo $user['user_id']; ?>" data-name="<?php echo htmlspecialchars($user['name']); ?>" data-email="<?php echo htmlspecialchars($user['email']); ?>" data-role="<?php echo $user['role']; ?>" data-department-id="<?php echo $user['department_id']; ?>" data-contact-info="<?php echo htmlspecialchars($user['contact_info']); ?>" data-is-active="<?php echo $user['is_active']; ?>" onclick="editUser(this)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?page=users&action=reset_password&id=<?php echo $user['user_id']; ?>" class="btn-icon btn-warning" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            <?php if ($user['is_active']): ?>
                                                <a href="?page=users&action=delete&id=<?php echo $user['user_id']; ?>" class="btn-icon btn-danger" title="Deactivate" onclick="return confirm('Are you sure you want to deactivate this user?')">
                                                    <i class="fas fa-user-times"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?page=users&action=activate&id=<?php echo $user['user_id']; ?>" class="btn-icon btn-success" title="Activate">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal modal-popout">
    <div class="modal-backdrop" onclick="closeModal('addUserModal')"></div>
    <div class="modal-content animated-popout">
        <div class="modal-header">
            <h3>Add New User</h3>
            <button class="modal-close" onclick="closeModal('addUserModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required>
                            <option value="employee">Employee</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id">
                            <option value="">— Select Department —</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="contact_info">Contact Information</label>
                    <input type="text" id="contact_info" name="contact_info" placeholder="Phone number or extension">
                </div>
                <div class="form-note">
                    <i class="fas fa-info-circle"></i>
                    Default password will be set to: <strong>password123</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal modal-popout">
    <div class="modal-backdrop" onclick="closeModal('editUserModal')"></div>
    <div class="modal-content animated-popout">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="modal-close" onclick="closeModal('editUserModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <input type="hidden" id="edit_user_id" name="user_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_name">Full Name *</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email Address *</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_role">Role *</label>
                        <select id="edit_role" name="role" required>
                            <option value="employee">Employee</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_department_id">Department</label>
                        <select id="edit_department_id" name="department_id">
                            <option value="">— Select Department —</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_contact_info">Contact Information</label>
                    <input type="text" id="edit_contact_info" name="contact_info" placeholder="Phone number or extension">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                        <span class="checkmark"></span>
                        Active User
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function editUser(button) {
    const userId = button.dataset.userId;
    const name = button.dataset.name;
    const email = button.dataset.email;
    const role = button.dataset.role;
    const departmentId = button.dataset.departmentId;
    const contactInfo = button.dataset.contactInfo;
    const isActive = button.dataset.isActive === '1';
    
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_department_id').value = departmentId || '';
    document.getElementById('edit_contact_info').value = contactInfo;
    document.getElementById('edit_is_active').checked = isActive;
    
    openModal('editUserModal');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let i = 0; i < modals.length; i++) {
        if (event.target == modals[i]) {
            modals[i].style.display = 'none';
        }
    }
}

// Close alerts
document.querySelectorAll('.alert-close').forEach(button => {
    button.addEventListener('click', function() {
        this.parentElement.style.display = 'none';
    });
});
</script>

<style>
.page-header {
    margin-bottom: 30px;
}

.page-header h2 {
    color: var(--dark-color);
    margin-bottom: 5px;
}

.page-header p {
    color: #666;
}

.user-info {
    display: flex;
    align-items: left;
    gap: 10px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 600;
    color: var(--dark-color);
}

.user-contact {
    font-size: 0.8rem;
    color: #666;
}

.role-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.role-admin {
    background-color: #e74c3c;
    color: white;
}

.role-manager {
    background-color: #3498db;
    color: white;
}

.role-employee {
    background-color: #2ecc71;
    color: white;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}

/* Modal popout styling */
.modal-popout {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.modal-popout[style*="display: block"] {
    display: flex;
}

.modal-backdrop {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.45);
    z-index: 1;
}

.animated-popout {
    position: relative;
    z-index: 2;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    max-width: 600px;
    width: 100%;
    margin: auto;
    animation: popoutModal 0.25s cubic-bezier(.4,2,.3,1) 1;
    padding: 0;
    overflow: hidden;
}

@keyframes popoutModal {
    from { transform: scale(0.85); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.modal-header {
    background: var(--primary-color, #007bff);
    color: #fff;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.2rem;
    cursor: pointer;
    transition: color 0.2s;
}

.modal-close:hover {
    color: #ffdddd;
}

.modal-body {
    padding: 22px 24px 10px 24px;
    background: #f9f9f9;
}

.form-group {
    margin-bottom: 18px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 6px;
    display: block;
    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
    font-family: inherit;
    background: #fff;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary-color, #007bff);
    outline: none;
}

.modal-footer {
    padding: 16px 24px;
    background: #f1f1f1;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-note {
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 4px;
    font-size: 0.9rem;
    color: #666;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: normal;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
    margin: 0;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-secondary {
    background-color: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background-color: #7f8c8d;
}

.btn-warning {
    background-color: var(--warning-color);
    color: white;
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

@media (max-width: 600px) {
    .animated-popout {
        max-width: 98vw;
        padding: 0;
    }
    .modal-header, .modal-body, .modal-footer {
        padding-left: 12px;
        padding-right: 12px;
    }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .user-info {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
}
</style>