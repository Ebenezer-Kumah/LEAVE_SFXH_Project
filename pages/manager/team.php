<?php
// pages/manager/team.php

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

// Get team members
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM Leave_Requests WHERE employee_id = u.user_id AND status = 'approved' 
           AND CURRENT_DATE() BETWEEN start_date AND end_date) as on_leave
          FROM Users u 
          WHERE u.department_id = :dept_id 
          AND u.role = 'employee' 
          AND u.is_active = TRUE
          ORDER BY u.name";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get team statistics
$query = "SELECT 
            COUNT(*) as total_members,
            SUM(CASE WHEN on_leave > 0 THEN 1 ELSE 0 END) as currently_on_leave,
            (SELECT COUNT(*) FROM Leave_Requests lr 
             JOIN Users u ON lr.employee_id = u.user_id 
             WHERE u.department_id = :dept_id AND lr.status = 'pending') as pending_requests
          FROM (
            SELECT u.user_id, 
                   (SELECT COUNT(*) FROM Leave_Requests 
                    WHERE employee_id = u.user_id AND status = 'approved' 
                    AND CURRENT_DATE() BETWEEN start_date AND end_date) as on_leave
            FROM Users u
            WHERE u.department_id = :dept_id AND u.role = 'employee' AND u.is_active = TRUE
          ) team_stats";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$team_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>My Team - <?php echo $department['department_name']; ?></h2>
    <p>Manage your team members and view their availability.</p>
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
            <?php echo $team_stats['total_members']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Total team members
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">On Leave</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $team_stats['currently_on_leave']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Currently unavailable
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
            <?php echo $team_stats['pending_requests']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Awaiting approval
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Available</h3>
            <div class="card-icon">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $team_stats['total_members'] - $team_stats['currently_on_leave']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Currently working
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3>Team Members</h3>
        <div class="header-actions">
            <button class="btn btn-secondary" onclick="exportTeamData()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Current Leave</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team_members as $member): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($member['name']); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($member['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($member['contact_info'] ?: '—'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $member['on_leave'] ? 'absent' : 'active'; ?>">
                                    <?php echo $member['on_leave'] ? 'On Leave' : 'Available'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($member['on_leave']): ?>
                                    <?php 
                                    $query = "SELECT leave_type, start_date, end_date 
                                              FROM Leave_Requests 
                                              WHERE employee_id = :emp_id 
                                              AND status = 'approved' 
                                              AND CURRENT_DATE() BETWEEN start_date AND end_date 
                                              LIMIT 1";
                                    $stmt = $db->prepare($query);
                                    $stmt->bindParam(':emp_id', $member['user_id']);
                                    $stmt->execute();
                                    $current_leave = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($current_leave): 
                                        $start = new DateTime($current_leave['start_date']);
                                        $end = new DateTime($current_leave['end_date']);
                                    ?>
                                        <div class="leave-info">
                                            <span class="leave-type"><?php echo $current_leave['leave_type']; ?></span>
                                            <span class="leave-dates"><?php echo $start->format('M d') . ' - ' . $end->format('M d'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="no-leave">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?page=employee-detail&id=<?php echo $member['user_id']; ?>" class="btn-icon" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?page=leave-history&emp_id=<?php echo $member['user_id']; ?>" class="btn-icon btn-info" title="Leave History">
                                        <i class="fas fa-history"></i>
                                    </a>
                                    <a href="mailto:<?php echo $member['email']; ?>" class="btn-icon btn-primary" title="Send Email">
                                        <i class="fas fa-envelope"></i>
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

<script>
function exportTeamData() {
    // Simple CSV export implementation
    const table = document.querySelector('.data-table');
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
            // Skip actions column
            if (!cell.querySelector('.action-buttons')) {
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
    link.setAttribute("download", "team_roster_<?php echo date('Y-m-d'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<style>
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 600;
    color: var(--dark-color);
}

.user-email {
    font-size: 0.8rem;
    color: #666;
}

.leave-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.leave-type {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.leave-dates {
    font-size: 0.8rem;
    color: #666;
}

.no-leave {
    color: #999;
    font-style: italic;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-absent {
    background-color: #f8d7da;
    color: #721c24;
}

.header-actions {
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .user-info {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>