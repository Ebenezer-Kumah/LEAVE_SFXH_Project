<?php
// pages/admin/departments.php

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

// Add new department
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_department'])) {
    try {
        $department_name = trim($_POST['department_name']);
        $manager_id = $_POST['manager_id'] ?: null;
        $description = trim($_POST['description']);
        
        $query = "INSERT INTO Departments (department_name, manager_id, description) 
                  VALUES (:department_name, :manager_id, :description)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':department_name', $department_name);
        $stmt->bindParam(':manager_id', $manager_id);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            $message = 'Department added successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error adding department: ' . $e->getMessage();
    }
}

// Update department
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_department'])) {
    try {
        $department_id = $_POST['department_id'];
        $department_name = trim($_POST['department_name']);
        $manager_id = $_POST['manager_id'] ?: null;
        $description = trim($_POST['description']);
        
        $query = "UPDATE Departments 
                  SET department_name = :department_name, 
                      manager_id = :manager_id, 
                      description = :description
                  WHERE department_id = :department_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':department_id', $department_id);
        $stmt->bindParam(':department_name', $department_name);
        $stmt->bindParam(':manager_id', $manager_id);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            $message = 'Department updated successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error updating department: ' . $e->getMessage();
    }
}

// Delete department
if ($action == 'delete' && isset($_GET['id'])) {
    try {
        $department_id = $_GET['id'];
        
        // Check if department has users
        $check_query = "SELECT COUNT(*) as user_count FROM Users WHERE department_id = :department_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':department_id', $department_id);
        $check_stmt->execute();
        $user_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['user_count'];
        
        if ($user_count > 0) {
            $error = 'Cannot delete department with assigned users. Please reassign users first.';
        } else {
            $query = "DELETE FROM Departments WHERE department_id = :department_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':department_id', $department_id);
            
            if ($stmt->execute()) {
                $message = 'Department deleted successfully!';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error deleting department: ' . $e->getMessage();
    }
}

// Get all departments with manager information
$query = "SELECT d.*, u.name as manager_name, 
          (SELECT COUNT(*) FROM Users WHERE department_id = d.department_id AND is_active = 1) as employee_count
          FROM Departments d 
          LEFT JOIN Users u ON d.manager_id = u.user_id 
          ORDER BY d.department_name";
$stmt = $db->prepare($query);
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all managers for dropdown
$query = "SELECT user_id, name, email FROM Users WHERE role = 'manager' AND is_active = 1 ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get department statistics
$query = "SELECT 
            COUNT(*) as total_departments,
            SUM(employee_count) as total_employees,
            (SELECT COUNT(*) FROM Departments WHERE manager_id IS NOT NULL) as managed_departments
          FROM (
            SELECT d.department_id, 
                   (SELECT COUNT(*) FROM Users WHERE department_id = d.department_id AND is_active = 1) as employee_count
            FROM Departments d
          ) dept_stats";
$stmt = $db->prepare($query);
$stmt->execute();
$dept_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>Department Management</h2>
    <p>Manage hospital departments, assign managers, and view department statistics.</p>
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
            <h3 class="card-title">Total Departments</h3>
            <div class="card-icon">
                <i class="fas fa-building"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $dept_stats['total_departments']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> All hospital departments
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Total Employees</h3>
            <div class="card-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $dept_stats['total_employees']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Across all departments
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Managed Departments</h3>
            <div class="card-icon">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $dept_stats['managed_departments']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> With assigned managers
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Unmanaged Departments</h3>
            <div class="card-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $dept_stats['total_departments'] - $dept_stats['managed_departments']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Need manager assignment
        </div>
    </div>
</div>

<div class="content-row">
    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>All Departments</h3>
                <button class="btn btn-primary" onclick="openModal('addDeptModal')">
                    <i class="fas fa-plus"></i> Add New Department
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Manager</th>
                                <th>Employees</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td>
                                        <div class="dept-info">
                                            <div class="dept-details">
                                                <div class="dept-name"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                                                <div class="dept-id">ID: <?php echo $dept['department_id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($dept['manager_name']): ?>
                                            <div class="manager-info">
                                                <div class="manager-avatar">
                                                    <i class="fas fa-user-tie"></i>
                                                </div>
                                                <div class="manager-details">
                                                    <div class="manager-name"><?php echo htmlspecialchars($dept['manager_name']); ?></div>
                                                    <div class="manager-status">Assigned</div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-manager">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="employee-count">
                                            <span class="count-number"><?php echo $dept['employee_count']; ?></span>
                                            <span class="count-label">employees</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="dept-description">
                                            <?php echo $dept['description'] ? htmlspecialchars($dept['description']) : '—'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" data-dept-id="<?php echo $dept['department_id']; ?>" data-dept-name="<?php echo htmlspecialchars($dept['department_name']); ?>" data-manager-id="<?php echo $dept['manager_id']; ?>" data-description="<?php echo htmlspecialchars($dept['description']); ?>" onclick="editDepartment(this)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?page=departments&action=delete&id=<?php echo $dept['department_id']; ?>" class="btn-icon btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this department?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="?page=department-users&id=<?php echo $dept['department_id']; ?>" class="btn-icon btn-info" title="View Employees">
                                                <i class="fas fa-users"></i>
                                            </a>
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

<!-- Add Department Modal -->
<div id="addDeptModal" class="modal modal-popout">
    <div class="modal-backdrop" onclick="closeModal('addDeptModal')"></div>
    <div class="modal-content animated-popout">
        <div class="modal-header">
            <h3>Add New Department</h3>
            <button class="modal-close" onclick="closeModal('addDeptModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="dept_name">Department Name *</label>
                    <input type="text" id="dept_name" name="department_name" required>
                </div>
                <div class="form-group">
                    <label for="manager_id">Department Manager</label>
                    <select id="manager_id" name="manager_id">
                        <option value="">— Select Manager —</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?php echo $manager['user_id']; ?>">
                                <?php echo htmlspecialchars($manager['name']); ?> (<?php echo htmlspecialchars($manager['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Brief description of the department"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addDeptModal')">Cancel</button>
                <button type="submit" name="add_department" class="btn btn-primary">Add Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Department Modal -->
<div id="editDeptModal" class="modal modal-popout">
    <div class="modal-backdrop" onclick="closeModal('editDeptModal')"></div>
    <div class="modal-content animated-popout">
        <div class="modal-header">
            <h3>Edit Department</h3>
            <button class="modal-close" onclick="closeModal('editDeptModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <input type="hidden" id="edit_dept_id" name="department_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_dept_name">Department Name *</label>
                    <input type="text" id="edit_dept_name" name="department_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_manager_id">Department Manager</label>
                    <select id="edit_manager_id" name="manager_id">
                        <option value="">— Select Manager —</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?php echo $manager['user_id']; ?>">
                                <?php echo htmlspecialchars($manager['name']); ?> (<?php echo htmlspecialchars($manager['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3" placeholder="Brief description of the department"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editDeptModal')">Cancel</button>
                <button type="submit" name="update_department" class="btn btn-primary">Update Department</button>
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

function editDepartment(button) {
    const deptId = button.dataset.deptId;
    const deptName = button.dataset.deptName;
    const managerId = button.dataset.managerId;
    const description = button.dataset.description || '';
    
    document.getElementById('edit_dept_id').value = deptId;
    document.getElementById('edit_dept_name').value = deptName;
    document.getElementById('edit_manager_id').value = managerId || '';
    document.getElementById('edit_description').value = description;
    
    openModal('editDeptModal');
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
.dept-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dept-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.dept-details {
    display: flex;
    flex-direction: column;
}

.dept-name {
    font-weight: 600;
    color: var(--dark-color);
}

.dept-id {
    font-size: 0.8rem;
    color: #666;
}

.manager-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.manager-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: #e8f4fd;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1rem;
}

.manager-details {
    display: flex;
    flex-direction: column;
}

.manager-name {
    font-weight: 600;
    color: var(--dark-color);
}

.manager-status {
    font-size: 0.8rem;
    color: #28a745;
}

.no-manager {
    color: #dc3545;
    font-style: italic;
}

.employee-count {
    text-align: center;
    padding: 8px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.count-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
}

.count-label {
    font-size: 0.8rem;
    color: #666;
}

.dept-description {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
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
    max-width: 420px;
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

.form-group label {
    font-weight: 500;
    margin-bottom: 6px;
    display: block;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
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
.form-group select:focus,
.form-group textarea:focus {
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
    .dept-info,
    .manager-info {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
    
    .employee-count {
        padding: 5px;
    }
    
    .count-number {
        font-size: 1.2rem;
    }
}
</style>