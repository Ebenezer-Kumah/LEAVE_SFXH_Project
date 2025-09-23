<?php
// pages/manager/reports.php

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

// Handle report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'summary';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$employee_id = isset($_GET['employee']) ? $_GET['employee'] : '';

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

// Generate reports based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'summary':
        $report_title = 'Department Summary Report';
        $query = "SELECT 
                    lr.leave_type,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(DATEDIFF(lr.end_date, lr.start_date) + 1) as total_days,
                    AVG(DATEDIFF(lr.end_date, lr.start_date) + 1) as avg_duration
                  FROM Leave_Requests lr
                  JOIN Users u ON lr.employee_id = u.user_id
                  WHERE u.department_id = :dept_id
                  AND lr.created_at BETWEEN :start_date AND :end_date";
        
        if ($employee_id) {
            $query .= " AND u.user_id = :employee_id";
        }
        
        $query .= " GROUP BY lr.leave_type ORDER BY total_requests DESC";
        break;
        
    case 'employee':
        $report_title = 'Employee-wise Report';
        $query = "SELECT 
                    u.name as employee_name,
                    COUNT(lr.request_id) as total_requests,
                    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(DATEDIFF(lr.end_date, lr.start_date) + 1) as total_days,
                    AVG(DATEDIFF(lr.end_date, lr.start_date) + 1) as avg_duration
                  FROM Users u
                  LEFT JOIN Leave_Requests lr ON u.user_id = lr.employee_id
                  AND lr.created_at BETWEEN :start_date AND :end_date
                  WHERE u.department_id = :dept_id
                  AND u.role = 'employee'";
        
        if ($employee_id) {
            $query .= " AND u.user_id = :employee_id";
        }
        
        $query .= " GROUP BY u.user_id ORDER BY u.name";
        break;
        
    case 'utilization':
        $report_title = 'Leave Utilization Report';
        $query = "SELECT 
                    u.name as employee_name,
                    lb.leave_type,
                    lb.total_entitlement,
                    lb.used_days,
                    lb.remaining_days,
                    ROUND((lb.used_days / lb.total_entitlement) * 100, 2) as utilization_rate
                  FROM Leave_Balances lb
                  JOIN Users u ON lb.employee_id = u.user_id
                  WHERE u.department_id = :dept_id
                  AND lb.year = YEAR(CURRENT_DATE())";
        
        if ($employee_id) {
            $query .= " AND u.user_id = :employee_id";
        }
        
        $query .= " ORDER BY u.name, lb.leave_type";
        break;
        
    case 'trends':
        $report_title = 'Monthly Trends Report';
        $query = "SELECT 
                    DATE_FORMAT(lr.created_at, '%Y-%m') as month,
                    lr.leave_type,
                    COUNT(*) as request_count,
                    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(DATEDIFF(lr.end_date, lr.start_date) + 1) as total_days
                  FROM Leave_Requests lr
                  JOIN Users u ON lr.employee_id = u.user_id
                  WHERE u.department_id = :dept_id
                  AND lr.created_at BETWEEN :start_date AND :end_date";
        
        if ($employee_id) {
            $query .= " AND u.user_id = :employee_id";
        }
        
        $query .= " GROUP BY DATE_FORMAT(lr.created_at, '%Y-%m'), lr.leave_type
                  ORDER BY month, lr.leave_type";
        break;
}

$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);

if ($employee_id) {
    $stmt->bindParam(':employee_id', $employee_id);
}

$stmt->execute();
$report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get department statistics for the period
$query = "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
            SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(DATEDIFF(lr.end_date, lr.start_date) + 1) as total_leave_days,
            COUNT(DISTINCT lr.employee_id) as employees_with_leave,
            AVG(DATEDIFF(lr.end_date, lr.start_date) + 1) as avg_leave_duration
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          WHERE u.department_id = :dept_id
          AND lr.created_at BETWEEN :start_date AND :end_date";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$department_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>Department Reports - <?php echo $department['department_name']; ?></h2>
    <p>Generate detailed reports on leave patterns and utilization.</p>
</div>

<div class="report-controls">
    <div class="report-filters">
        <form method="GET" action="">
            <input type="hidden" name="page" value="reports">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="type">Report Type</label>
                    <select id="type" name="type" onchange="this.form.submit()">
                        <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                        <option value="employee" <?php echo $report_type == 'employee' ? 'selected' : ''; ?>>Employee Report</option>
                        <option value="utilization" <?php echo $report_type == 'utilization' ? 'selected' : ''; ?>>Utilization Report</option>
                        <option value="trends" <?php echo $report_type == 'trends' ? 'selected' : ''; ?>>Trends Report</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="form-group">
                    <label for="employee">Employee</label>
                    <select id="employee" name="employee" onchange="this.form.submit()">
                        <option value="">All Employees</option>
                        <?php foreach ($team_members as $member): ?>
                            <option value="<?php echo $member['user_id']; ?>" <?php echo $employee_id == $member['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
    
    <div class="report-actions">
        <button class="btn btn-secondary" onclick="printReport()">
            <i class="fas fa-print"></i> Print
        </button>
        <button class="btn btn-primary" onclick="exportToExcel()">
            <i class="fas fa-download"></i> Export Excel
        </button>
    </div>
</div>

<div class="report-summary">
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo $department_stats['total_requests'] ?? 0; ?></div>
                <div class="summary-label">Total Requests</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon approved">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo $department_stats['approved_requests'] ?? 0; ?></div>
                <div class="summary-label">Approved</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo $department_stats['total_leave_days'] ?? 0; ?></div>
                <div class="summary-label">Total Days</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo $department_stats['employees_with_leave'] ?? 0; ?></div>
                <div class="summary-label">Employees</div>
            </div>
        </div>
    </div>
</div>

<div class="report-content">
    <div class="content-card">
        <div class="card-header">
            <h3><?php echo $report_title; ?></h3>
            <span class="report-period">
                <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>
                <?php echo $employee_id ? ' • Filtered by employee' : ''; ?>
            </span>
        </div>
        <div class="card-body">
            <?php if (!empty($report_data)): ?>
                <div class="table-responsive">
                    <table class="data-table report-table">
                        <thead>
                            <tr>
                                <?php switch ($report_type):
                                    case 'summary': ?>
                                        <th>Leave Type</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Rejected</th>
                                        <th>Pending</th>
                                        <th>Total Days</th>
                                        <th>Avg Duration</th>
                                        <?php break; ?>
                                    
                                    <?php case 'employee': ?>
                                        <th>Employee</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Rejected</th>
                                        <th>Total Days</th>
                                        <th>Avg Duration</th>
                                        <?php break; ?>
                                    
                                    <?php case 'utilization': ?>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Entitlement</th>
                                        <th>Used</th>
                                        <th>Remaining</th>
                                        <th>Utilization %</th>
                                        <?php break; ?>
                                    
                                    <?php case 'trends': ?>
                                        <th>Month</th>
                                        <th>Leave Type</th>
                                        <th>Requests</th>
                                        <th>Approved</th>
                                        <th>Total Days</th>
                                        <?php break; ?>
                                    
                                <?php endswitch; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php switch ($report_type):
                                        case 'summary': ?>
                                            <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                            <td><?php echo $row['total_requests']; ?></td>
                                            <td><?php echo $row['approved']; ?></td>
                                            <td><?php echo $row['rejected']; ?></td>
                                            <td><?php echo $row['pending']; ?></td>
                                            <td><?php echo $row['total_days']; ?></td>
                                            <td><?php echo round($row['avg_duration'], 1); ?> days</td>
                                            <?php break; ?>
                                        
                                        <?php case 'employee': ?>
                                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                            <td><?php echo $row['total_requests']; ?></td>
                                            <td><?php echo $row['approved']; ?></td>
                                            <td><?php echo $row['rejected']; ?></td>
                                            <td><?php echo $row['total_days']; ?></td>
                                            <td><?php echo round($row['avg_duration'], 1); ?> days</td>
                                            <?php break; ?>
                                        
                                        <?php case 'utilization': ?>
                                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                            <td><?php echo $row['total_entitlement']; ?> days</td>
                                            <td><?php echo $row['used_days']; ?> days</td>
                                            <td><?php echo $row['remaining_days']; ?> days</td>
                                            <td>
                                                <div class="utilization-bar">
                                                    <div class="utilization-fill" style="width: <?php echo min($row['utilization_rate'], 100); ?>%"></div>
                                                    <span class="utilization-text"><?php echo $row['utilization_rate']; ?>%</span>
                                                </div>
                                            </td>
                                            <?php break; ?>
                                        
                                        <?php case 'trends': ?>
                                            <td><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td>
                                            <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                            <td><?php echo $row['request_count']; ?></td>
                                            <td><?php echo $row['approved_count']; ?></td>
                                            <td><?php echo $row['total_days']; ?></td>
                                            <?php break; ?>
                                        
                                    <?php endswitch; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($report_type == 'summary' || $report_type == 'employee'): ?>
                <div class="chart-container">
                    <canvas id="reportChart" height="300"></canvas>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <p>No data available for the selected criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function printReport() {
    window.print();
}

function exportToExcel() {
    const table = document.querySelector('.report-table');
    let csv = [];
    
    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(header => {
        headers.push(header.textContent);
    });
    csv.push(headers.join(','));
    
    // Get rows
    table.querySelectorAll('tbody tr').forEach(row => {
        const rowData = [];
        row.querySelectorAll('td').forEach(cell => {
            // Handle utilization bars
            if (cell.querySelector('.utilization-bar')) {
                const text = cell.querySelector('.utilization-text').textContent;
                rowData.push(text.replace('%', ''));
            } else {
                rowData.push('"' + cell.textContent.replace(/"/g, '""') + '"');
            }
        });
        csv.push(rowData.join(','));
    });
    
    // Download CSV
    const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "department_report_<?php echo date('Y-m-d'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize chart
document.addEventListener('DOMContentLoaded', function() {
    <?php if (($report_type == 'summary' || $report_type == 'employee') && !empty($report_data)): ?>
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    let chartData;
    <?php if ($report_type == 'summary'): ?>
    chartData = {
        labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['leave_type'] . "'"; }, $report_data)); ?>],
        datasets: [{
            label: 'Approved',
            data: [<?php echo implode(',', array_map(function($item) { return $item['approved']; }, $report_data)); ?>],
            backgroundColor: '#28a745'
        }, {
            label: 'Rejected',
            data: [<?php echo implode(',', array_map(function($item) { return $item['rejected']; }, $report_data)); ?>],
            backgroundColor: '#dc3545'
        }, {
            label: 'Pending',
            data: [<?php echo implode(',', array_map(function($item) { return $item['pending']; }, $report_data)); ?>],
            backgroundColor: '#ffc107'
        }]
    };
    <?php else: ?>
    chartData = {
        labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['employee_name'] . "'"; }, $report_data)); ?>],
        datasets: [{
            label: 'Total Requests',
            data: [<?php echo implode(',', array_map(function($item) { return $item['total_requests']; }, $report_data)); ?>],
            backgroundColor: '#3498db',
            borderColor: '#2980b9',
            borderWidth: 1
        }]
    };
    <?php endif; ?>
    
    new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            scales: {
                x: <?php echo $report_type == 'summary' ? '{ stacked: true }' : '{}'; ?>,
                y: <?php echo $report_type == 'summary' ? '{ stacked: true, beginAtZero: true }' : '{ beginAtZero: true }'; ?>
            }
        }
    });
    <?php endif; ?>
});
</script>

<style>
.report-controls {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.report-filters {
    margin-bottom: 15px;
}

.report-filters .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.report-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.report-summary {
    margin-bottom: 20px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--primary-color);
}

.summary-icon.approved {
    background-color: #d4edda;
    color: #28a745;
}

.summary-content {
    display: flex;
    flex-direction: column;
}

.summary-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark-color);
}

.summary-label {
    color: #666;
    font-size: 0.9rem;
}

.report-period {
    color: #666;
    font-size: 0.9rem;
}

.utilization-bar {
    position: relative;
    height: 20px;
    background-color: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.utilization-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 10px;
    transition: width 0.3s ease;
}

.utilization-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.8rem;
    font-weight: 600;
    color: #333;
}

.chart-container {
    margin-top: 30px;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

@media (max-width: 768px) {
    .report-filters .form-row {
        grid-template-columns: 1fr;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .report-actions {
        justify-content: center;
    }
}

@media print {
    .sidebar, .header, .report-controls, .report-actions {
        display: none !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
}
</style>