<?php
// pages/manager/dashboard.php

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

// Get statistics for dashboard
$stats = [];

// Team members count
$query = "SELECT COUNT(*) as total 
          FROM Users 
          WHERE department_id = :dept_id 
          AND role = 'employee' 
          AND is_active = TRUE";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$stats['team_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending requests for approval
$query = "SELECT COUNT(*) as total 
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          WHERE u.department_id = :dept_id 
          AND lr.status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$stats['pending_approvals'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Approved requests this month
$query = "SELECT COUNT(*) as total 
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          WHERE u.department_id = :dept_id 
          AND lr.status = 'approved'
          AND MONTH(lr.created_at) = MONTH(CURRENT_DATE())
          AND YEAR(lr.created_at) = YEAR(CURRENT_DATE())";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$stats['approved_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Team on leave today
$query = "SELECT COUNT(*) as total 
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          WHERE u.department_id = :dept_id 
          AND lr.status = 'approved'
          AND CURRENT_DATE() BETWEEN lr.start_date AND lr.end_date";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$stats['on_leave_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent leave requests from team
$query = "SELECT lr.*, u.name as employee_name 
          FROM Leave_Requests lr 
          JOIN Users u ON lr.employee_id = u.user_id 
          WHERE u.department_id = :dept_id
          ORDER BY lr.created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Team availability this week
$query = "SELECT u.name, 
                 SUM(CASE WHEN lr.status = 'approved' 
                          AND CURRENT_DATE() BETWEEN lr.start_date AND lr.end_date 
                     THEN 1 ELSE 0 END) as on_leave
          FROM Users u
          LEFT JOIN Leave_Requests lr ON u.user_id = lr.employee_id
          WHERE u.department_id = :dept_id 
          AND u.role = 'employee'
          AND u.is_active = TRUE
          GROUP BY u.user_id
          ORDER BY u.name";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$team_availability = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-header">
    <h2>Manager Dashboard</h2>
    <p>Welcome, <?php echo $_SESSION['user_name']; ?>. Managing <?php echo $department['department_name']; ?> Department.</p>
</div>

<div class="dashboard-cards">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Team Members</h3>
            <div class="card-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['team_members']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=team">View Team</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pending Approvals</h3>
            <div class="card-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['pending_approvals']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=approvals">Review Requests</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Approved This Month</h3>
            <div class="card-icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['approved_this_month']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=reports">View Reports</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">On Leave Today</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['on_leave_today']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=calendar">View Calendar</a>
        </div>
    </div>
</div>

<div class="dashboard-content">
    <div class="content-row">
        <div class="content-col">
            <div class="content-card">
                <div class="card-header">
                    <h3>Recent Leave Requests</h3>
                    <a href="?page=approvals" class="btn-link">View All</a>
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
                                                        <a href="?page=approvals&action=approve&id=<?php echo $request['request_id']; ?>" class="btn-icon btn-success" title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                        <a href="?page=approvals&action=reject&id=<?php echo $request['request_id']; ?>" class="btn-icon btn-danger" title="Reject">
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
                            <p>No recent leave requests from your team</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-col">
            <div class="content-card">
                <div class="card-header">
                    <h3>Team Availability</h3>
                </div>
                <div class="card-body">
                    <?php if (count($team_availability) > 0): ?>
                        <div class="availability-list">
                            <?php foreach ($team_availability as $member): ?>
                                <div class="availability-item">
                                    <div class="member-name"><?php echo htmlspecialchars($member['name']); ?></div>
                                    <div class="availability-status">
                                        <span class="status-indicator <?php echo $member['on_leave'] ? 'absent' : 'present'; ?>">
                                            <?php echo $member['on_leave'] ? 'On Leave' : 'Available'; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No team members found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="?page=approvals" class="quick-action-btn">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Review Requests</span>
                        </a>
                        <a href="?page=calendar" class="quick-action-btn">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Team Calendar</span>
                        </a>
                        <a href="?page=reports" class="quick-action-btn">
                            <i class="fas fa-chart-bar"></i>
                            <span>Department Reports</span>
                        </a>
                        <a href="?page=team" class="quick-action-btn">
                            <i class="fas fa-users"></i>
                            <span>Manage Team</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.availability-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.availability-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.status-indicator {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-indicator.present {
    background-color: #d4edda;
    color: #155724;
}

.status-indicator.absent {
    background-color: #f8d7da;
    color: #721c24;
}

.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
    text-decoration: none;
    color: var(--dark-color);
    transition: all 0.3s ease;
}

.quick-action-btn:hover {
    background-color: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

.quick-action-btn i {
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.quick-action-btn span {
    font-size: 0.9rem;
    text-align: center;
}

@media (max-width: 768px) {
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>