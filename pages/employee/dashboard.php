<?php
// pages/employee/dashboard.php

// Check if user is employee
if ($_SESSION['user_role'] !== 'employee') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../includes/notifications_helper.php';

// Get employee's leave balances
$query = "SELECT leave_type, total_entitlement, used_days, remaining_days 
          FROM Leave_Balances 
          WHERE employee_id = :employee_id 
          AND year = YEAR(CURRENT_DATE())";
$stmt = $db->prepare($query);
$stmt->bindParam(':employee_id', $_SESSION['user_id']);
$stmt->execute();
$leave_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent leave requests
$query = "SELECT * FROM Leave_Requests 
          WHERE employee_id = :employee_id 
          ORDER BY created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(':employee_id', $_SESSION['user_id']);
$stmt->execute();
$recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming approved leaves
$query = "SELECT * FROM Leave_Requests 
          WHERE employee_id = :employee_id 
          AND status = 'approved'
          AND start_date >= CURRENT_DATE()
          ORDER BY start_date ASC 
          LIMIT 3";
$stmt = $db->prepare($query);
$stmt->bindParam(':employee_id', $_SESSION['user_id']);
$stmt->execute();
$upcoming_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check and insert reminders for upcoming leaves
checkAndInsertReminders($db, $_SESSION['user_id']);

// Calculate statistics
$stats = [
    'total_requests' => 0,
    'approved_requests' => 0,
    'pending_requests' => 0,
    'remaining_days' => 0
];

foreach ($leave_balances as $balance) {
    $stats['remaining_days'] += $balance['remaining_days'];
}

$query = "SELECT status, COUNT(*) as count 
          FROM Leave_Requests 
          WHERE employee_id = :employee_id 
          GROUP BY status";
$stmt = $db->prepare($query);
$stmt->bindParam(':employee_id', $_SESSION['user_id']);
$stmt->execute();
$status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($status_counts as $count) {
    $stats['total_requests'] += $count['count'];
    if ($count['status'] === 'approved') {
        $stats['approved_requests'] = $count['count'];
    } elseif ($count['status'] === 'pending') {
        $stats['pending_requests'] = $count['count'];
    }
}
?>

<div class="dashboard-header">
    <h2>Employee Dashboard</h2>
    <p>Welcome back, <?php echo $_SESSION['user_name']; ?>. Here's your leave overview.</p>
</div>

<div class="dashboard-cards">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Remaining Leave Days</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['remaining_days']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=balance">View Details</a>
        </div>
    </div>

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
            <i class="fas fa-eye"></i> <a href="?page=history">View History</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Approved Requests</h3>
            <div class="card-icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['approved_requests']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-eye"></i> <a href="?page=history&status=approved">View Approved</a>
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
            <i class="fas fa-eye"></i> <a href="?page=history&status=pending">View Pending</a>
        </div>
    </div>
</div>

<div class="dashboard-content">
    <div class="content-row">
        <div class="content-col">
            <div class="content-card">
                <div class="card-header">
                    <h3>My Leave Balances</h3>
                    <a href="?page=balance" class="btn-link">View Details</a>
                </div>
                <div class="card-body">
                    <?php if (count($leave_balances) > 0): ?>
                        <div class="balances-list">
                            <?php foreach ($leave_balances as $balance): ?>
                                <div class="balance-item">
                                    <div class="balance-type"><?php echo htmlspecialchars($balance['leave_type']); ?></div>
                                    <div class="balance-details">
                                        <span class="balance-remaining"><?php echo $balance['remaining_days']; ?> days left</span>
                                        <span class="balance-total">of <?php echo $balance['total_entitlement']; ?> days</span>
                                    </div>
                                    <div class="balance-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo ($balance['used_days'] / $balance['total_entitlement']) * 100; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-pie"></i>
                            <p>No leave balance information available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3>Recent Leave Requests</h3>
                    <a href="?page=history" class="btn-link">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_requests) > 0): ?>
                        <div class="requests-list">
                            <?php foreach ($recent_requests as $request): ?>
                                <div class="request-item">
                                    <div class="request-type"><?php echo htmlspecialchars($request['leave_type']); ?></div>
                                    <div class="request-dates">
                                        <?php 
                                        $start = new DateTime($request['start_date']);
                                        $end = new DateTime($request['end_date']);
                                        echo $start->format('M d') . ' - ' . $end->format('M d');
                                        ?>
                                    </div>
                                    <div class="request-status">
                                        <span class="status-badge status-<?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </div>
                                    <div class="request-actions">
                                        <a href="?page=request-detail&id=<?php echo $request['request_id']; ?>" class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <a href="?page=cancel-request&id=<?php echo $request['request_id']; ?>" class="btn-icon btn-warning" title="Cancel Request">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No leave requests yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-col">
            <div class="content-card">
                <div class="card-header">
                    <h3>Upcoming Approved Leaves</h3>
                    <a href="?page=history&status=approved" class="btn-link">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_leaves) > 0): ?>
                        <div class="upcoming-leaves">
                            <?php foreach ($upcoming_leaves as $leave): ?>
                                <div class="upcoming-item">
                                    <div class="upcoming-dates">
                                        <div class="upcoming-month"><?php echo (new DateTime($leave['start_date']))->format('M'); ?></div>
                                        <div class="upcoming-day"><?php echo (new DateTime($leave['start_date']))->format('d'); ?></div>
                                    </div>
                                    <div class="upcoming-details">
                                        <div class="upcoming-type"><?php echo htmlspecialchars($leave['leave_type']); ?></div>
                                        <div class="upcoming-duration">
                                            <?php 
                                            $start = new DateTime($leave['start_date']);
                                            $end = new DateTime($leave['end_date']);
                                            $diff = $start->diff($end)->days + 1;
                                            echo $diff . ' day' . ($diff > 1 ? 's' : '');
                                            ?>
                                        </div>
                                    </div>
                                    <div class="upcoming-actions">
                                        <a href="?page=request-detail&id=<?php echo $leave['request_id']; ?>" class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>No upcoming approved leaves</p>
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
                        <a href="?page=apply" class="quick-action-btn primary">
                            <i class="fas fa-plus-circle"></i>
                            <span>Apply for Leave</span>
                        </a>
                        <a href="?page=balance" class="quick-action-btn">
                            <i class="fas fa-chart-pie"></i>
                            <span>Leave Balance</span>
                        </a>
                        <a href="?page=history" class="quick-action-btn">
                            <i class="fas fa-history"></i>
                            <span>Request History</span>
                        </a>
                        <a href="?page=profile" class="quick-action-btn">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.balances-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.balance-item {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 10px;
    align-items: center;
}

.balance-type {
    font-weight: 600;
    color: var(--dark-color);
}

.balance-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.balance-remaining {
    font-weight: 600;
    color: var(--primary-color);
}

.balance-total {
    font-size: 0.8rem;
    color: #666;
}

.balance-progress {
    grid-column: 1 / -1;
}

.progress-bar {
    height: 6px;
    background-color: #f0f0f0;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background-color: var(--primary-color);
    border-radius: 3px;
}

.requests-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.request-item {
    display: grid;
    grid-template-columns: 1fr 1fr auto auto;
    gap: 15px;
    align-items: center;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.request-dates {
    font-size: 0.9rem;
    color: #666;
}

.upcoming-leaves {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.upcoming-item {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 15px;
    align-items: center;
    padding: 12px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.upcoming-dates {
    text-align: center;
    padding: 8px;
    background-color: var(--primary-color);
    color: white;
    border-radius: 6px;
    min-width: 50px;
}

.upcoming-month {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.upcoming-day {
    font-size: 1.2rem;
    font-weight: 700;
}

.upcoming-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.upcoming-type {
    font-weight: 600;
    color: var(--dark-color);
}

.upcoming-duration {
    font-size: 0.8rem;
    color: #666;
}

.quick-action-btn.primary {
    background-color: var(--primary-color);
    color: white;
}

.quick-action-btn.primary:hover {
    background-color: var(--secondary-color);
}

.btn-warning {
    background-color: var(--warning-color);
}

@media (max-width: 768px) {
    .balance-item {
        grid-template-columns: 1fr;
    }
    
    .request-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .upcoming-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
}
</style>