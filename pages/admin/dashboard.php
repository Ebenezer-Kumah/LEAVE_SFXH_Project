<?php
// pages/admin/dashboard.php

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection with correct path
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Get statistics for dashboard
$stats = [];

// Total employees
$query = "SELECT COUNT(*) as total FROM Users WHERE role = 'employee' AND is_active = TRUE";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total managers
$query = "SELECT COUNT(*) as total FROM Users WHERE role = 'manager' AND is_active = TRUE";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_managers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending leave requests
$query = "SELECT COUNT(*) as total FROM Leave_Requests WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Departments count
$query = "SELECT COUNT(*) as total FROM Departments";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_departments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent leave requests
$query = "SELECT lr.*, u.name as employee_name 
          FROM Leave_Requests lr 
          JOIN Users u ON lr.employee_id = u.user_id 
          ORDER BY lr.created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Leave balance summary
$query = "SELECT leave_type, SUM(remaining_days) as total_remaining 
          FROM Leave_Balances 
          WHERE year = YEAR(CURDATE()) 
          GROUP BY leave_type";
$stmt = $db->prepare($query);
$stmt->execute();
$leave_balance_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Department-wise employee count
$query = "SELECT d.department_name, COUNT(u.user_id) as employee_count 
          FROM Departments d 
          LEFT JOIN Users u ON d.department_id = u.department_id AND u.is_active = TRUE AND u.role = 'employee'
          GROUP BY d.department_id 
          ORDER BY employee_count DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-header">
    <h2>Admin Dashboard</h2>
    <p>Welcome, <?php echo $_SESSION['user_name']; ?>. Here's an overview of the hospital's leave management.</p>
</div>

<div class="dashboard-cards">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Total Employees</h3>
            <div class="card-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['total_employees']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=users">View all employees</a>
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
            <?php echo $stats['total_managers']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=users">View all managers</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pending Requests</h3>
            <div class="card-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['pending_requests']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=leave-requests">Review requests</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Departments</h3>
            <div class="card-icon">
                <i class="fas fa-building"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['total_departments']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=departments">Manage departments</a>
        </div>
    </div>
</div>

<div class="dashboard-content">
    <div class="content-row">
        <div class="content-col">
            <div class="content-card">
                <div class="card-header">
                    <h3>Recent Leave Requests</h3>
                    <a href="?page=leave-requests" class="btn-link">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_requests) > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_requests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                            <td>
                                                <?php 
                                                $start = new DateTime($request['start_date']);
                                                $end = new DateTime($request['end_date']);
                                                echo $start->format('M d') . ' - ' . $end->format('M d, Y');
                                                ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?page=request-detail&id=<?php echo $request['request_id']; ?>" class="btn-icon" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($request['status'] == 'pending'): ?>
                                                        <a href="?page=leave-requests&action=approve&id=<?php echo $request['request_id']; ?>" class="btn-icon btn-success" title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                        <a href="?page=leave-requests&action=reject&id=<?php echo $request['request_id']; ?>" class="btn-icon btn-danger" title="Reject">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No recent leave requests</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-col">
            <div class="content-card">
                <div class="card-header">
                    <h3>Leave Balance Summary</h3>
                </div>
                <div class="card-body">
                    <?php if (count($leave_balance_summary) > 0): ?>
                        <div class="chart-container">
                            <canvas id="leaveBalanceChart" height="250"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-pie"></i>
                            <p>No leave balance data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3>Employees by Department</h3>
                </div>
                <div class="card-body">
                    <?php if (count($department_stats) > 0): ?>
                        <div class="stats-list">
                            <?php foreach ($department_stats as $dept): ?>
                                <div class="stat-item">
                                    <div class="stat-label"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                                    <div class="stat-value"><?php echo $dept['employee_count']; ?> employees</div>
                                    <div class="stat-bar">
                                        <div class="stat-progress" style="width: <?php echo ($dept['employee_count'] / max(array_column($department_stats, 'employee_count')) * 100); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <p>No department data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js for charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Leave Balance Chart
    <?php if (count($leave_balance_summary) > 0): ?>
    const leaveBalanceCtx = document.getElementById('leaveBalanceChart').getContext('2d');
    const leaveBalanceChart = new Chart(leaveBalanceCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php echo implode(', ', array_map(function($item) { return "'" . $item['leave_type'] . "'"; }, $leave_balance_summary)); ?>],
            datasets: [{
                data: [<?php echo implode(', ', array_map(function($item) { return $item['total_remaining']; }, $leave_balance_summary)); ?>],
                backgroundColor: [
                    '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', '#1abc9c'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Total Remaining Leave Days'
                }
            }
        }
    });
    <?php endif; ?>

    // Make cards clickable
    document.querySelectorAll('.dashboard-cards .card').forEach(card => {
        card.addEventListener('click', function() {
            const link = this.querySelector('a');
            if (link) {
                window.location.href = link.href;
            }
        });
    });
});
</script>