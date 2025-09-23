<?php
// pages/employee/balance.php

// Check if user is employee
if ($_SESSION['user_role'] !== 'employee') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Get current year and available years
$current_year = date('Y');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;

// Get available years for filter
$query = "SELECT DISTINCT year 
          FROM Leave_Balances 
          WHERE employee_id = :emp_id 
          ORDER BY year DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':emp_id', $_SESSION['user_id']);
$stmt->execute();
$available_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get leave balances for selected year
$query = "SELECT * FROM Leave_Balances 
          WHERE employee_id = :emp_id 
          AND year = :year
          ORDER BY leave_type";
$stmt = $db->prepare($query);
$stmt->bindParam(':emp_id', $_SESSION['user_id']);
$stmt->bindParam(':year', $selected_year);
$stmt->execute();
$leave_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get leave usage statistics
$query = "SELECT 
            lb.leave_type,
            lb.total_entitlement,
            lb.used_days,
            lb.remaining_days,
            ROUND((lb.used_days / lb.total_entitlement) * 100, 2) as utilization_rate,
            (SELECT COUNT(*) FROM Leave_Requests 
             WHERE employee_id = :emp_id 
             AND leave_type = lb.leave_type 
             AND YEAR(created_at) = :year
             AND status = 'approved') as approved_requests,
            (SELECT COUNT(*) FROM Leave_Requests 
             WHERE employee_id = :emp_id 
             AND leave_type = lb.leave_type 
             AND YEAR(created_at) = :year
             AND status = 'pending') as pending_requests
          FROM Leave_Balances lb
          WHERE lb.employee_id = :emp_id 
          AND lb.year = :year";
$stmt = $db->prepare($query);
$stmt->bindParam(':emp_id', $_SESSION['user_id']);
$stmt->bindParam(':year', $selected_year);
$stmt->execute();
$balance_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall statistics
$total_entitlement = 0;
$total_used = 0;
$total_remaining = 0;
$total_requests = 0;

foreach ($balance_stats as $stat) {
    $total_entitlement += $stat['total_entitlement'];
    $total_used += $stat['used_days'];
    $total_remaining += $stat['remaining_days'];
    $total_requests += $stat['approved_requests'] + $stat['pending_requests'];
}

// Get upcoming leaves
$query = "SELECT lr.*, 
          DATEDIFF(lr.end_date, lr.start_date) + 1 as duration_days
          FROM Leave_Requests lr
          WHERE lr.employee_id = :emp_id 
          AND lr.status = 'approved'
          AND lr.start_date >= CURDATE()
          ORDER BY lr.start_date ASC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(':emp_id', $_SESSION['user_id']);
$stmt->execute();
$upcoming_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent leave transactions
$query = "SELECT lr.*, 
          DATEDIFF(lr.end_date, lr.start_date) + 1 as duration_days,
          CASE 
            WHEN lr.status = 'approved' THEN 'deduction'
            WHEN lr.status = 'cancelled' THEN 'addition'
            ELSE 'pending'
          END as transaction_type
          FROM Leave_Requests lr
          WHERE lr.employee_id = :emp_id 
          AND lr.status IN ('approved', 'cancelled')
          AND YEAR(lr.created_at) = :year
          ORDER BY lr.updated_at DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':emp_id', $_SESSION['user_id']);
$stmt->bindParam(':year', $selected_year);
$stmt->execute();
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>My Leave Balance</h2>
    <p>Track your leave entitlements, usage, and upcoming time off.</p>
</div>

<div class="filter-bar">
    <form method="GET" action="" class="year-filter">
        <input type="hidden" name="page" value="balance">
        <label for="year">View Balance for:</label>
        <select id="year" name="year" onchange="this.form.submit()">
            <?php foreach ($available_years as $year): ?>
                <option value="<?php echo $year['year']; ?>" <?php echo $selected_year == $year['year'] ? 'selected' : ''; ?>>
                    <?php echo $year['year']; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="dashboard-cards">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Total Entitlement</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $total_entitlement; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Days allocated
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Days Used</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-minus"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $total_used; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Days taken
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Days Remaining</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $total_remaining; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Available balance
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Utilization Rate</h3>
            <div class="card-icon">
                <i class="fas fa-chart-pie"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $total_entitlement > 0 ? round(($total_used / $total_entitlement) * 100, 1) : 0; ?>%
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Of total entitlement
        </div>
    </div>
</div>

<div class="content-row">
    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Leave Balance Details - <?php echo $selected_year; ?></h3>
                <span class="badge"><?php echo count($balance_stats); ?> leave types</span>
            </div>
            <div class="card-body">
                <?php if (!empty($balance_stats)): ?>
                    <div class="balance-cards">
                        <?php foreach ($balance_stats as $balance): ?>
                            <div class="balance-card">
                                <div class="balance-header">
                                    <h4><?php echo htmlspecialchars($balance['leave_type']); ?></h4>
                                    <span class="utilization-rate <?php echo $balance['utilization_rate'] > 80 ? 'high' : ($balance['utilization_rate'] > 50 ? 'medium' : 'low'); ?>">
                                        <?php echo $balance['utilization_rate']; ?>%
                                    </span>
                                </div>
                                
                                <div class="balance-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo min($balance['utilization_rate'], 100); ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="balance-details">
                                    <div class="balance-item">
                                        <span class="label">Entitlement:</span>
                                        <span class="value"><?php echo $balance['total_entitlement']; ?> days</span>
                                    </div>
                                    <div class="balance-item">
                                        <span class="label">Used:</span>
                                        <span class="value used"><?php echo $balance['used_days']; ?> days</span>
                                    </div>
                                    <div class="balance-item">
                                        <span class="label">Remaining:</span>
                                        <span class="value remaining"><?php echo $balance['remaining_days']; ?> days</span>
                                    </div>
                                </div>
                                
                                <div class="balance-footer">
                                    <div class="balance-stats">
                                        <span class="stat">
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo $balance['approved_requests']; ?> approved
                                        </span>
                                        <span class="stat">
                                            <i class="fas fa-clock"></i>
                                            <?php echo $balance['pending_requests']; ?> pending
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-pie"></i>
                        <h4>No Balance Information</h4>
                        <p>No leave balance data available for <?php echo $selected_year; ?>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Recent Transactions</h3>
                <span class="badge"><?php echo count($recent_transactions); ?> transactions</span>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_transactions)): ?>
                    <div class="transactions-list">
                        <?php foreach ($recent_transactions as $transaction): 
                            $start = new DateTime($transaction['start_date']);
                            $end = new DateTime($transaction['end_date']);
                        ?>
                            <div class="transaction-item">
                                <div class="transaction-icon <?php echo $transaction['transaction_type']; ?>">
                                    <i class="fas fa-<?php echo $transaction['transaction_type'] === 'deduction' ? 'minus' : 'plus'; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-type">
                                        <?php echo htmlspecialchars($transaction['leave_type']); ?>
                                        <span class="transaction-amount <?php echo $transaction['transaction_type']; ?>">
                                            <?php echo $transaction['transaction_type'] === 'deduction' ? '-' : '+'; ?>
                                            <?php echo $transaction['duration_days']; ?> days
                                        </span>
                                    </div>
                                    <div class="transaction-date">
                                        <?php echo $start->format('M d') . ' - ' . $end->format('M d, Y'); ?>
                                    </div>
                                    <div class="transaction-reason">
                                        <?php echo htmlspecialchars($transaction['reason'] ?: 'No reason provided'); ?>
                                    </div>
                                </div>
                                <div class="transaction-time">
                                    <?php echo date('M j, Y', strtotime($transaction['updated_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exchange-alt"></i>
                        <p>No transactions found for <?php echo $selected_year; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Upcoming Approved Leaves</h3>
                <span class="badge"><?php echo count($upcoming_leaves); ?> upcoming</span>
            </div>
            <div class="card-body">
                <?php if (!empty($upcoming_leaves)): ?>
                    <div class="upcoming-leaves">
                        <?php foreach ($upcoming_leaves as $leave): 
                            $start = new DateTime($leave['start_date']);
                            $end = new DateTime($leave['end_date']);
                            $days_until = $start->diff(new DateTime())->days;
                        ?>
                            <div class="upcoming-item">
                                <div class="upcoming-dates">
                                    <div class="upcoming-month"><?php echo $start->format('M'); ?></div>
                                    <div class="upcoming-day"><?php echo $start->format('d'); ?></div>
                                </div>
                                <div class="upcoming-details">
                                    <div class="upcoming-type"><?php echo htmlspecialchars($leave['leave_type']); ?></div>
                                    <div class="upcoming-duration">
                                        <?php echo $leave['duration_days']; ?> day<?php echo $leave['duration_days'] !== 1 ? 's' : ''; ?>
                                    </div>
                                    <div class="upcoming-timing">
                                        <i class="fas fa-clock"></i>
                                        <?php if ($days_until === 0): ?>
                                            Starts today
                                        <?php elseif ($days_until === 1): ?>
                                            Starts tomorrow
                                        <?php else: ?>
                                            In <?php echo $days_until; ?> days
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="upcoming-actions">
                                    <a href="?page=request-detail&id=<?php echo $leave['request_id']; ?>" class="btn-icon" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($days_until > 1): ?>
                                        <a href="?page=cancel-request&id=<?php echo $leave['request_id']; ?>" class="btn-icon btn-warning" title="Cancel Leave">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
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
                <h3>Leave Utilization Chart</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($balance_stats)): ?>
                    <div class="chart-container">
                        <canvas id="utilizationChart" height="250"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <p>No data available for chart</p>
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
                    <a href="?page=history" class="quick-action-btn">
                        <i class="fas fa-history"></i>
                        <span>View History</span>
                    </a>
                    <button class="quick-action-btn" onclick="printBalance()">
                        <i class="fas fa-print"></i>
                        <span>Print Summary</span>
                    </button>
                    <button class="quick-action-btn" onclick="exportBalance()">
                        <i class="fas fa-download"></i>
                        <span>Export Data</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function viewLeaveDetails(requestId) {
    alert('Viewing details for leave request #' + requestId);
    // In real implementation, show modal with detailed leave information
}

function cancelLeave(requestId) {
    if (confirm('Are you sure you want to cancel this approved leave?')) {
        window.location.href = '?page=cancel-request&id=' + requestId;
    }
}

function printBalance() {
    window.print();
}

function exportBalance() {
    // Simple CSV export implementation
    const data = [
        ['Leave Type', 'Entitlement', 'Used', 'Remaining', 'Utilization %']
    ];
    
    <?php foreach ($balance_stats as $balance): ?>
        data.push([
            '<?php echo $balance['leave_type']; ?>',
            '<?php echo $balance['total_entitlement']; ?>',
            '<?php echo $balance['used_days']; ?>',
            '<?php echo $balance['remaining_days']; ?>',
            '<?php echo $balance['utilization_rate']; ?>%'
        ]);
    <?php endforeach; ?>
    
    let csvContent = "data:text/csv;charset=utf-8,";
    data.forEach(row => {
        csvContent += row.join(',') + "\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "leave_balance_<?php echo $selected_year; ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize chart
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($balance_stats)): ?>
    const ctx = document.getElementById('utilizationChart').getContext('2d');
    const chartData = {
        labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['leave_type'] . "'"; }, $balance_stats)); ?>],
        datasets: [{
            label: 'Used Days',
            data: [<?php echo implode(',', array_map(function($item) { return $item['used_days']; }, $balance_stats)); ?>],
            backgroundColor: '#3498db'
        }, {
            label: 'Remaining Days',
            data: [<?php echo implode(',', array_map(function($item) { return $item['remaining_days']; }, $balance_stats)); ?>],
            backgroundColor: '#2ecc71'
        }]
    };
    
    new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Days'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Leave Utilization by Type'
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<style>
.filter-bar {
    background: white;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.year-filter {
    display: flex;
    align-items: center;
    gap: 10px;
}

.year-filter label {
    font-weight: 600;
    color: var(--dark-color);
}

.year-filter select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: white;
}

.balance-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.balance-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.balance-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.balance-header h4 {
    margin: 0;
    color: var(--dark-color);
    font-size: 1.1rem;
}

.utilization-rate {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.utilization-rate.low {
    background-color: #d4edda;
    color: #155724;
}

.utilization-rate.medium {
    background-color: #fff3cd;
    color: #856404;
}

.utilization-rate.high {
    background-color: #f8d7da;
    color: #721c24;
}

.balance-progress {
    margin-bottom: 15px;
}

.progress-bar {
    height: 8px;
    background-color: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3498db, #2980b9);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.balance-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 15px;
}

.balance-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.balance-item .label {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.balance-item .value {
    font-weight: 600;
}

.balance-item .value.used {
    color: #e74c3c;
}

.balance-item .value.remaining {
    color: #27ae60;
}

.balance-footer {
    border-top: 1px solid #f0f0f0;
    padding-top: 15px;
}

.balance-stats {
    display: flex;
    justify-content: space-around;
    gap: 10px;
}

.balance-stats .stat {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.8rem;
    color: #666;
}

.transactions-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.transaction-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.transaction-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.transaction-icon.deduction {
    background-color: #f8d7da;
    color: #721c24;
}

.transaction-icon.addition {
    background-color: #d4edda;
    color: #155724;
}

.transaction-details {
    flex: 1;
}

.transaction-type {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.transaction-amount {
    font-weight: 700;
    font-size: 0.9rem;
}

.transaction-amount.deduction {
    color: #e74c3c;
}

.transaction-amount.addition {
    color: #27ae60;
}

.transaction-date {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 3px;
}

.transaction-reason {
    font-size: 0.8rem;
    color: #999;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.transaction-time {
    font-size: 0.8rem;
    color: #999;
    text-align: right;
    flex-shrink: 0;
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
    font-size: 0.9rem;
    color: #666;
}

.upcoming-timing {
    font-size: 0.8rem;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 4px;
}

.upcoming-actions {
    display: flex;
    gap: 5px;
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
    border: none;
    cursor: pointer;
    text-align: center;
}

.quick-action-btn:hover {
    background-color: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

.quick-action-btn.primary {
    background-color: var(--primary-color);
    color: white;
}

.quick-action-btn.primary:hover {
    background-color: var(--secondary-color);
}

.quick-action-btn i {
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.quick-action-btn span {
    font-size: 0.9rem;
    font-weight: 600;
}

.chart-container {
    position: relative;
    height: 250px;
}

@media (max-width: 768px) {
    .balance-cards {
        grid-template-columns: 1fr;
    }
    
    .transaction-item {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .transaction-time {
        text-align: center;
    }
    
    .upcoming-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
    
    .year-filter {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>