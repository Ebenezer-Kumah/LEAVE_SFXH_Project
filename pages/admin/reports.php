<?php
// pages/admin/reports.php

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle report generation
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$department_id = isset($_GET['department_id']) ? $_GET['department_id'] : '';

// Get all departments for filter
$query = "SELECT * FROM Departments ORDER BY department_name";
$stmt = $db->prepare($query);
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get report data based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'overview':
        $report_title = 'Leave Overview Report';
        $query = "SELECT 
                    lr.leave_type,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    AVG(DATEDIFF(lr.end_date, lr.start_date) + 1) as avg_duration
                  FROM Leave_Requests lr
                  WHERE lr.created_at BETWEEN :start_date AND :end_date
                  GROUP BY lr.leave_type
                  ORDER BY total_requests DESC";
        break;
        
    case 'department':
        $report_title = 'Department-wise Report';
        $query = "SELECT
                    d.department_name,
                    COUNT(lr.request_id) as total_requests,
                    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    AVG(DATEDIFF(lr.end_date, lr.start_date) + 1) as avg_duration
                  FROM Departments d
                  LEFT JOIN Users u ON d.department_id = u.department_id
                  LEFT JOIN Leave_Requests lr ON u.user_id = lr.employee_id AND lr.created_at BETWEEN :start_date AND :end_date
                  WHERE (:department_id = '' OR d.department_id = :department_id)
                  GROUP BY d.department_id
                  ORDER BY total_requests DESC";
        break;
        
    case 'utilization':
        $report_title = 'Leave Utilization Report';
        $query = "SELECT 
                    u.name as employee_name,
                    d.department_name,
                    lb.leave_type,
                    lb.total_entitlement,
                    lb.used_days,
                    lb.remaining_days,
                    ROUND((lb.used_days / lb.total_entitlement) * 100, 2) as utilization_rate
                  FROM Leave_Balances lb
                  JOIN Users u ON lb.employee_id = u.user_id
                  LEFT JOIN Departments d ON u.department_id = d.department_id
                  WHERE lb.year = YEAR(CURRENT_DATE())
                  ORDER BY utilization_rate DESC";
        break;
        
    case 'trends':
        $report_title = 'Monthly Trends Report';
        $query = "SELECT 
                    DATE_FORMAT(lr.created_at, '%Y-%m') as month,
                    lr.leave_type,
                    COUNT(*) as request_count,
                    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_count
                  FROM Leave_Requests lr
                  WHERE lr.created_at BETWEEN :start_date AND :end_date
                  GROUP BY DATE_FORMAT(lr.created_at, '%Y-%m'), lr.leave_type
                  ORDER BY month, lr.leave_type";
        break;
}

$stmt = $db->prepare($query);
if ($report_type !== 'utilization') {
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    if ($report_type === 'department') {
        $stmt->bindParam(':department_id', $department_id);
    }
}
$stmt->execute();
$report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$summary_query = "SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                    AVG(DATEDIFF(end_date, start_date) + 1) as avg_duration
                  FROM Leave_Requests 
                  WHERE created_at BETWEEN :start_date AND :end_date";
$summary_stmt = $db->prepare($summary_query);
$summary_stmt->bindParam(':start_date', $start_date);
$summary_stmt->bindParam(':end_date', $end_date);
$summary_stmt->execute();
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>Reports & Analytics</h2>
    <p>Generate comprehensive reports on leave patterns, utilization, and trends.</p>
</div>

<div class="report-controls">
    <div class="report-filters">
        <form method="GET" action="">
            <input type="hidden" name="page" value="reports">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="report_type">Report Type</label>
                    <select id="report_type" name="report_type" onchange="this.form.submit()">
                        <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview</option>
                        <option value="department" <?php echo $report_type == 'department' ? 'selected' : ''; ?>>Department-wise</option>
                        <option value="utilization" <?php echo $report_type == 'utilization' ? 'selected' : ''; ?>>Utilization</option>
                        <option value="trends" <?php echo $report_type == 'trends' ? 'selected' : ''; ?>>Monthly Trends</option>
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
                
                <?php if ($report_type == 'department'): ?>
                <div class="form-group">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo $department_id == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
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
                <div class="summary-number"><?php echo $summary['total_requests'] ?? 0; ?></div>
                <div class="summary-label">Total Requests</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon approved">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo $summary['approved_requests'] ?? 0; ?></div>
                <div class="summary-label">Approved</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon rejected">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo $summary['rejected_requests'] ?? 0; ?></div>
                <div class="summary-label">Rejected</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo $summary['pending_requests'] ?? 0; ?></div>
                <div class="summary-label">Pending</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="summary-content">
                <div class="summary-number"><?php echo round($summary['avg_duration'] ?? 0, 1); ?></div>
                <div class="summary-label">Avg Days</div>
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
            </span>
        </div>
        <div class="card-body">
            <?php if (!empty($report_data)): ?>
                <div class="table-responsive">
                    <table class="data-table report-table">
                        <thead>
                            <tr>
                                <?php switch ($report_type):
                                    case 'overview': ?>
                                        <th>Leave Type</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Rejected</th>
                                        <th>Pending</th>
                                        <th>Avg Duration</th>
                                        <?php break; ?>
                                    
                                    <?php case 'department': ?>
                                        <th>Department</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Rejected</th>
                                        <th>Pending</th>
                                        <th>Avg Duration</th>
                                        <?php break; ?>
                                    
                                    <?php case 'utilization': ?>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Entitlement</th>
                                        <th>Used</th>
                                        <th>Remaining</th>
                                        <th>Utilization %</th>
                                        <?php break; ?>
                                    
                                    <?php case 'trends': ?>
                                        <th>Month</th>
                                        <th>Leave Type</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <?php break; ?>
                                    
                                <?php endswitch; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php switch ($report_type):
                                        case 'overview': ?>
                                            <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                            <td><?php echo $row['total_requests']; ?></td>
                                            <td><?php echo $row['approved']; ?></td>
                                            <td><?php echo $row['rejected']; ?></td>
                                            <td><?php echo $row['pending']; ?></td>
                                            <td><?php echo round($row['avg_duration'], 1); ?> days</td>
                                            <?php break; ?>
                                        
                                        <?php case 'department': ?>
                                            <td><?php echo htmlspecialchars($row['department_name'] ?: 'No Department'); ?></td>
                                            <td><?php echo $row['total_requests']; ?></td>
                                            <td><?php echo $row['approved']; ?></td>
                                            <td><?php echo $row['rejected']; ?></td>
                                            <td><?php echo $row['pending'] ?? 0; ?></td>
                                            <td><?php echo round($row['avg_duration'], 1); ?> days</td>
                                            <?php break; ?>
                                        
                                        <?php case 'utilization': ?>
                                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department_name'] ?: '—'); ?></td>
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
                                            <?php break; ?>
                                        
                                    <?php endswitch; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($report_type == 'overview' || $report_type == 'department'): ?>
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
    // Simple Excel export implementation
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
            rowData.push('"' + cell.textContent.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    // Download CSV
    const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "leave_report_<?php echo date('Y-m-d'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize chart
document.addEventListener('DOMContentLoaded', function() {
    <?php if (($report_type == 'overview' || $report_type == 'department') && !empty($report_data)): ?>
    const ctx = document.getElementById('reportChart').getContext('2d');
    const chartData = {
        labels: [<?php echo implode(',', array_map(function($item) use ($report_type) { 
            return "'" . ($report_type == 'overview' ? $item['leave_type'] : $item['department_name']) . "'"; 
        }, $report_data)); ?>],
        datasets: [
            {
                label: 'Approved',
                data: [<?php echo implode(',', array_map(function($item) { return $item['approved']; }, $report_data)); ?>],
                backgroundColor: '#4CAF50'
            },
            {
                label: 'Rejected',
                data: [<?php echo implode(',', array_map(function($item) { return $item['rejected']; }, $report_data)); ?>],
                backgroundColor: '#F44336'
            },
            {
                label: 'Pending',
                data: [<?php echo implode(',', array_map(function($item) { return $item['pending']; }, $report_data)); ?>],
                backgroundColor: '#FFC107'
            }
        ]
    };

    new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { size: 14 }
                    }
                },
                title: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { display: false },
                    ticks: { font: { size: 13 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: "#eee" },
                    ticks: { font: { size: 13 }, stepSize: 1 }
                }
            },
            barPercentage: 0.6,
            categoryPercentage: 0.6
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

.summary-icon.rejected {
    background-color: #f8d7da;
    color: #dc3545;
}

.summary-icon.pending {
    background-color: #fff3cd;
    color: #ffc107;
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

.report-table th {
    background-color: #f8f9fa;
    font-weight: 600;
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
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

#reportChart {
    max-width: 100%;
    height: 320px !important;
}

@media (max-width: 768px) {
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .report-controls .form-row {
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
    
    .report-summary, .report-content {
        break-inside: avoid;
    }
}
</style>