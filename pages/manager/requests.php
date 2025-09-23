<?php
// pages/manager/requests.php

// Check if user is manager
if ($_SESSION['user_role'] !== 'manager') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Get manager's department
$query = "SELECT d.department_id, d.department_name 
          FROM Departments d 
          WHERE d.manager_id = :manager_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':manager_id', $_SESSION['user_id']);
$stmt->execute();
$department = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$employee_filter = isset($_GET['employee']) ? $_GET['employee'] : '';

// Build query with filters
$query = "SELECT lr.*, u.name as employee_name, u.email as employee_email,
          u.contact_info, DATEDIFF(lr.end_date, lr.start_date) + 1 as duration_days
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          WHERE u.department_id = :dept_id";
          
$params = [':dept_id' => $department['department_id']];

if ($status_filter) {
    $query .= " AND lr.status = :status";
    $params[':status'] = $status_filter;
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

// Get team members for filter
$query = "SELECT user_id, name FROM Users 
          WHERE department_id = :dept_id 
          AND role = 'employee' 
          AND is_active = TRUE
          ORDER BY name";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$query = "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN lr.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(DATEDIFF(lr.end_date, lr.start_date) + 1) as total_days
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          WHERE u.department_id = :dept_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_requests = $_POST['selected_requests'] ?? [];
    
    if (!empty($selected_requests)) {
        try {
            $placeholders = implode(',', array_fill(0, count($selected_requests), '?'));
            $query = "UPDATE Leave_Requests SET status = ? WHERE request_id IN ($placeholders)";
            $stmt = $db->prepare($query);
            $stmt->execute(array_merge([$action], $selected_requests));
            
            $message = "Bulk action completed successfully!";
        } catch (PDOException $e) {
            $error = "Error processing bulk action: " . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <h2>Leave Requests - <?php echo $department['department_name']; ?></h2>
    <p>Manage all leave requests from your team members.</p>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-success">
        <span><?php echo $message; ?></span>
        <button class="alert-close"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
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
                <input type="hidden" name="page" value="requests">
                
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
                    <option value="">All Employees</option>
                    <?php foreach ($team_members as $member): ?>
                        <option value="<?php echo $member['user_id']; ?>" <?php echo $employee_filter == $member['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($member['name']); ?>
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
                                        <div class="employee-avatar">
                                            <i class="fas fa-user-circle"></i>
                                        </div>
                                        <div class="employee-details">
                                            <div class="employee-name"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                            <div class="employee-email"><?php echo htmlspecialchars($request['employee_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
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
                                            <a href="?page=approvals&action=approve&id=<?php echo $request['request_id']; ?>" class="btn-icon btn-success" title="Approve" onclick="return confirm('Approve this leave request?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="?page=approvals&action=reject&id=<?php echo $request['request_id']; ?>" class="btn-icon btn-danger" title="Reject" onclick="return confirm('Reject this leave request?')">
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

function viewRequestDetails(requestId) {
    // This would open a modal with detailed request information
    alert('Viewing details for request #' + requestId);
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