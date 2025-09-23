<?php
// pages/admin/leave-requests.php

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle approval actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$request_id = isset($_GET['id']) ? $_GET['id'] : '';
$message = '';
$error = '';

if ($action && $request_id && in_array($action, ['approve', 'reject'])) {
    try {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $query = "UPDATE Leave_Requests 
                  SET status = :status, 
                      updated_at = CURRENT_TIMESTAMP 
                  WHERE request_id = :request_id 
                  AND status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':request_id', $request_id);
        
        if ($stmt->execute()) {
            $message = "Leave request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
            
            // Update leave balance if approved (only for employees, not managers/admins)
            if ($action === 'approve') {
                $query = "SELECT lr.employee_id, lr.leave_type, u.role,
                         DATEDIFF(lr.end_date, lr.start_date) + 1 as days
                         FROM Leave_Requests lr
                         JOIN Users u ON lr.employee_id = u.user_id
                         WHERE lr.request_id = :request_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':request_id', $request_id);
                $stmt->execute();
                $request_info = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($request_info && $request_info['role'] === 'employee') {
                    $query = "UPDATE Leave_Balances
                              SET used_days = used_days + :days,
                                  remaining_days = remaining_days - :days
                              WHERE employee_id = :emp_id
                              AND leave_type = :leave_type
                              AND year = YEAR(CURRENT_DATE())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':days', $request_info['days']);
                    $stmt->bindParam(':emp_id', $request_info['employee_id']);
                    $stmt->bindParam(':leave_type', $request_info['leave_type']);
                    $stmt->execute();
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Error " . $action . "ing request: " . $e->getMessage();
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_requests = $_POST['selected_requests'] ?? [];
    
    if (!empty($selected_requests) && in_array($bulk_action, ['approved', 'rejected', 'pending'])) {
        try {
            $placeholders = implode(',', array_fill(0, count($selected_requests), '?'));
            $query = "UPDATE Leave_Requests SET status = ? WHERE request_id IN ($placeholders) AND status = 'pending'";
            $stmt = $db->prepare($query);
            $stmt->execute(array_merge([$bulk_action], $selected_requests));
            
            $message = "Bulk action completed successfully!";
        } catch (PDOException $e) {
            $error = "Error processing bulk action: " . $e->getMessage();
        }
    }
}

// Handle filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$employee_filter = isset($_GET['employee']) ? $_GET['employee'] : '';

// Build query with filters
$query = "SELECT lr.*, u.name as employee_name, u.email as employee_email,
          u.contact_info, d.department_name, u.role as user_role,
          DATEDIFF(lr.end_date, lr.start_date) + 1 as duration_days
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          LEFT JOIN Departments d ON u.department_id = d.department_id";
          
$params = [];

// Filters apply to all
if ($status_filter) {
    $query .= " WHERE lr.status = :status";
    $params[':status'] = $status_filter;
} else {
    $query .= " WHERE 1=1";
}

if ($type_filter) {
    $query .= " AND lr.leave_type = :type";
    $params[':type'] = $type_filter;
}

if ($date_filter) {
    $query .= " AND :date_filter BETWEEN lr.start_date AND lr.end_date";
    $params[':date_filter'] = $date_filter;
}

if ($employee_filter) {
    $query .= " AND u.user_id = :employee";
    $params[':employee'] = $employee_filter;
}

$query .= " ORDER BY lr.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users (employees, managers, admins) for filter
$query = "SELECT user_id, name, role FROM Users
          WHERE role IN ('employee', 'manager', 'admin')
          AND is_active = TRUE
          ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$query = "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(DATEDIFF(end_date, start_date) + 1) as total_days
          FROM Leave_Requests";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>Leave Requests - System Wide</h2>
    <p>Manage all leave requests from employees, managers, and admins.</p>
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
            <h3 class="card-title">Total Requests</h3>
            <div class="card-icon">
                <i class="fas fa-file-alt"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['total_requests']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> All time
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pending</h3>
            <div class="card-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['pending']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Awaiting review
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Approved</h3>
            <div class="card-icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['approved']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Requests approved
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Total Days</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['total_days']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Days allocated
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3>Leave Requests</h3>
        <div class="header-actions">
            <form method="GET" action="" class="filter-form">
                <input type="hidden" name="page" value="leave-requests">
                
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                
                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php 
                    $types = array_unique(array_column($leave_requests, 'leave_type'));
                    foreach ($types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $type_filter == $type ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="employee" onchange="this.form.submit()">
                    <option value="">All Users</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['user_id']; ?>" <?php echo $employee_filter == $emp['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['name'] . ' (' . ucfirst($emp['role']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()" placeholder="Filter by date">
            </form>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="bulkForm">
            <div class="table-actions">
                <select name="bulk_action" id="bulkAction">
                    <option value="">Bulk Actions</option>
                    <option value="approved">Approve Selected</option>
                    <option value="rejected">Reject Selected</option>
                    <option value="pending">Mark as Pending</option>
                </select>
                <button type="submit" class="btn btn-primary">Apply</button>
                <button type="button" class="btn btn-secondary" onclick="selectAll()">Select All</button>
                <button type="button" class="btn btn-secondary" onclick="deselectAll()">Deselect All</button>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Leave Type</th>
                            <th>Dates</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Applied On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_requests as $request): 
                            $start = new DateTime($request['start_date']);
                            $end = new DateTime($request['end_date']);
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_requests[]" value="<?php echo $request['request_id']; ?>" class="request-checkbox">
                                </td>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-details">
                                            <div class="employee-name"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                            <div class="employee-email"><?php echo htmlspecialchars($request['employee_email']); ?></div>
                                            <div class="employee-role">
                                                <span class="role-badge role-<?php echo $request['user_role']; ?>">
                                                    <?php echo ucfirst($request['user_role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $request['department_name'] ? htmlspecialchars($request['department_name']) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                <td>
                                    <div class="date-range">
                                        <?php echo $start->format('M d, Y'); ?> - <?php echo $end->format('M d, Y'); ?>
                                    </div>
                                </td>
                                <td><?php echo $request['duration_days']; ?> days</td>
                                <td>
                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?page=request-detail&id=<?php echo $request['request_id']; ?>" class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <a href="?page=leave-requests&action=approve&id=<?php echo $request['request_id']; ?>" class="btn-icon btn-success" title="Approve" onclick="return confirm('Approve this leave request?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="?page=leave-requests&action=reject&id=<?php echo $request['request_id']; ?>" class="btn-icon btn-danger" title="Reject" onclick="return confirm('Reject this leave request?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="mailto:<?php echo $request['employee_email']; ?>?subject=Leave Request #<?php echo $request['request_id']; ?>" class="btn-icon btn-primary" title="Send Email">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
        
        <?php if (empty($leave_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No Leave Requests</h4>
                <p>No leave requests found matching your filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.request-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.request-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = true;
    });
    document.getElementById('selectAll').checked = true;
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.request-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAll').checked = false;
}

// Handle bulk form submission
document.getElementById('bulkForm').addEventListener('submit', function(e) {
    const bulkAction = document.getElementById('bulkAction').value;
    const selectedCount = document.querySelectorAll('.request-checkbox:checked').length;
    
    if (!bulkAction) {
        e.preventDefault();
        alert('Please select a bulk action.');
        return false;
    }
    
    if (selectedCount === 0) {
        e.preventDefault();
        alert('Please select at least one leave request.');
        return false;
    }
    
    if (!confirm(`Are you sure you want to ${bulkAction} ${selectedCount} request(s)?`)) {
        e.preventDefault();
        return false;
    }
});
</script>

<style>
.filter-form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-form select,
.filter-form input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: white;
}

.table-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 15px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.table-actions select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: white;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.employee-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.employee-details {
    display: flex;
    flex-direction: column;
}

.employee-name {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.employee-email {
    font-size: 0.8rem;
    color: #666;
}

.employee-role {
    margin-top: 4px;
}

.role-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-employee {
    background-color: #e8f5e8;
    color: #2d5a2d;
}

.role-manager {
    background-color: #fff3cd;
    color: #856404;
}

.role-admin {
    background-color: #d1ecf1;
    color: #0c5460;
}

.date-range {
    font-size: 0.9rem;
    color: var(--dark-color);
}

.request-checkbox {
    transform: scale(1.2);
}

@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .table-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .employee-info {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
}
</style>