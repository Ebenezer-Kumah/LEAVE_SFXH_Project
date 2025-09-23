<?php
// pages/admin/history.php

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : date('Y');
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$employee_filter = isset($_GET['employee']) ? $_GET['employee'] : '';

// Build query with filters
$query = "SELECT lr.*,
          DATEDIFF(lr.end_date, lr.start_date) + 1 as duration_days,
          u.name as employee_name,
          m.name as manager_name
          FROM Leave_Requests lr
          LEFT JOIN Users u ON lr.employee_id = u.user_id
          LEFT JOIN Users m ON lr.manager_id = m.user_id";

$params = [];

if ($status_filter) {
    $query .= " AND lr.status = :status";
    $params[':status'] = $status_filter;
}

if ($year_filter) {
    $query .= " AND YEAR(lr.created_at) = :year";
    $params[':year'] = $year_filter;
}

if ($type_filter) {
    $query .= " AND lr.leave_type = :type";
    $params[':type'] = $type_filter;
}

if ($employee_filter) {
    $query .= " AND (u.name LIKE :employee OR u.email LIKE :employee)";
    $params[':employee'] = '%' . $employee_filter . '%';
}

$query .= " ORDER BY lr.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$leave_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available years for filter
$query = "SELECT DISTINCT YEAR(created_at) as year
          FROM Leave_Requests
          ORDER BY year DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$available_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available leave types for filter
$query = "SELECT DISTINCT leave_type
          FROM Leave_Requests
          ORDER BY leave_type";
$stmt = $db->prepare($query);
$stmt->execute();
$available_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available employees for filter
$query = "SELECT DISTINCT u.user_id, u.name, u.email
          FROM Users u
          INNER JOIN Leave_Requests lr ON u.user_id = lr.employee_id
          ORDER BY u.name";
$stmt = $db->prepare($query);
$stmt->execute();
$available_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <h2>Organization Leave History</h2>
    <p>Monitor and manage all leave requests across your organization.</p>
</div>

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
            <i class="fas fa-info-circle"></i> Organization-wide
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
            <i class="fas fa-info-circle"></i> Successfully processed
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
            <i class="fas fa-info-circle"></i> Leave days utilized
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pending Review</h3>
            <div class="card-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['pending']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Require admin attention
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3>Organization Leave Requests</h3>
        <div class="header-actions">
            <form method="GET" action="" class="filter-form">
                <input type="hidden" name="page" value="history">

                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>

                <select name="year" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year['year']; ?>" <?php echo $year_filter == $year['year'] ? 'selected' : ''; ?>>
                            <?php echo $year['year']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach ($available_types as $type): ?>
                        <option value="<?php echo $type['leave_type']; ?>" <?php echo $type_filter == $type['leave_type'] ? 'selected' : ''; ?>>
                            <?php echo $type['leave_type']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="employee" placeholder="Search employee..." value="<?php echo htmlspecialchars($employee_filter); ?>" onchange="this.form.submit()">
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($leave_history)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Dates</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Manager</th>
                            <th>Applied On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_history as $request):
                            $start = new DateTime($request['start_date']);
                            $end = new DateTime($request['end_date']);
                        ?>
                            <tr>
                                <td>
                                    <div class="employee-info">
                                        <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
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
                                <td>
                                    <?php if ($request['manager_name']): ?>
                                        <?php echo htmlspecialchars($request['manager_name']); ?>
                                    <?php else: ?>
                                        <span class="no-manager">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon" onclick="viewRequestDetails(<?php echo $request['request_id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <button class="btn-icon btn-success" onclick="approveRequest(<?php echo $request['request_id']; ?>)" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn-icon btn-danger" onclick="rejectRequest(<?php echo $request['request_id']; ?>)" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($request['status'] == 'approved' && new DateTime($request['start_date']) > new DateTime()): ?>
                                            <button class="btn-icon btn-warning" onclick="cancelRequest(<?php echo $request['request_id']; ?>)" title="Cancel Request">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <div class="table-info">
                    Showing <?php echo count($leave_history); ?> of <?php echo $stats['total_requests']; ?> requests
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <h4>No Leave Requests Found</h4>
                <p>No leave requests match your current filter criteria. Try adjusting your filters to see more results.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function viewRequestDetails(requestId) {
    window.location.href = '?page=request-detail&id=' + requestId;
}

function approveRequest(requestId) {
    if (confirm('Are you sure you want to approve this leave request?')) {
        window.location.href = '?page=leave-requests&action=approve&id=' + requestId;
    }
}

function rejectRequest(requestId) {
    if (confirm('Are you sure you want to reject this leave request?')) {
        window.location.href = '?page=leave-requests&action=reject&id=' + requestId;
    }
}

function cancelRequest(requestId) {
    if (confirm('Are you sure you want to cancel this leave request? This action cannot be undone.')) {
        window.location.href = '?page=cancel-request&id=' + requestId;
    }
}
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

.filter-form input {
    min-width: 200px;
}

.date-range {
    font-size: 0.9rem;
    color: var(--dark-color);
}

.no-manager {
    color: #999;
    font-style: italic;
}

.employee-info {
    font-size: 0.9rem;
}

.table-footer {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
    text-align: center;
}

.table-info {
    color: #666;
    font-size: 0.9rem;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state h4 {
    margin: 0 0 10px 0;
    color: #666;
}

.empty-state p {
    margin: 0 0 20px 0;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        gap: 5px;
    }

    .filter-form select,
    .filter-form input {
        width: 100%;
    }

    .header-actions {
        width: 100%;
        margin-top: 10px;
    }
}
</style>