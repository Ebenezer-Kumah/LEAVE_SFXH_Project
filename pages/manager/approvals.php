<?php
// pages/manager/approvals.php

// Check if user is manager
if ($_SESSION['user_role'] !== 'manager') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../includes/notifications_helper.php';

// Get manager's department
$query = "SELECT d.department_id, d.department_name 
          FROM Departments d 
          WHERE d.manager_id = :manager_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':manager_id', $_SESSION['user_id']);
$stmt->execute();
$department = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle approval actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$request_id = isset($_GET['id']) ? $_GET['id'] : '';
$message = '';
$error = '';

if ($action && $request_id) {
    try {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        // Fetch request details for notification
        $info_query = "SELECT lr.employee_id, lr.leave_type, lr.start_date, lr.end_date, u.name as employee_name
                       FROM Leave_Requests lr
                       JOIN Users u ON lr.employee_id = u.user_id
                       WHERE lr.request_id = :request_id";
        $info_stmt = $db->prepare($info_query);
        $info_stmt->bindParam(':request_id', $request_id);
        $info_stmt->execute();
        $request_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request_info) {
            throw new Exception('Request not found');
        }
        
        $query = "UPDATE Leave_Requests
                  SET status = :status,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE request_id = :request_id
                  AND status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':request_id', $request_id);
        
        if ($stmt->execute()) {
            $message = "Leave request {$action}d successfully!";
            
            // Update leave balance if approved
            if ($action === 'approve') {
                $query = "SELECT employee_id, leave_type, 
                         DATEDIFF(end_date, start_date) + 1 as days 
                         FROM Leave_Requests WHERE request_id = :request_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':request_id', $request_id);
                $stmt->execute();
                $request_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($request_info) {
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
            
            // Send notification to employee
            $start_formatted = date('M d, Y', strtotime($request_info['start_date']));
            $end_formatted = date('M d, Y', strtotime($request_info['end_date']));
            $emp_message = "Your leave request for {$request_info['leave_type']} from {$start_formatted} to {$end_formatted} has been {$status}.";
            insertNotification($db, $request_info['employee_id'], $emp_message);
        }
    } catch (PDOException $e) {
        $error = "Error {$action}ing request: " . $e->getMessage();
    }
}

// Get pending leave requests
$query = "SELECT lr.*, u.name as employee_name, u.contact_info,
          DATEDIFF(lr.end_date, lr.start_date) + 1 as duration_days
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          WHERE u.department_id = :dept_id 
          AND lr.status = 'pending'
          ORDER BY lr.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approval statistics
$query = "SELECT 
            COUNT(*) as total_pending,
            SUM(CASE WHEN lr.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_requests
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          WHERE u.department_id = :dept_id 
          AND lr.status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department['department_id']);
$stmt->execute();
$approval_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>Leave Approvals - <?php echo $department['department_name']; ?></h2>
    <p>Review and manage leave requests from your team members.</p>
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
            <h3 class="card-title">Pending Requests</h3>
            <div class="card-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $approval_stats['total_pending']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Awaiting review
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Requests</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-week"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $approval_stats['recent_requests']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Last 7 days
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Avg Response Time</h3>
            <div class="card-icon">
                <i class="fas fa-stopwatch"></i>
            </div>
        </div>
        <div class="card-body">
            2.3
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Days to approve
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Approval Rate</h3>
            <div class="card-icon">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
        <div class="card-body">
            85%
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Requests approved
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3>Pending Leave Requests</h3>
        <span class="badge"><?php echo count($pending_requests); ?> requests</span>
    </div>
    <div class="card-body">
        <?php if (!empty($pending_requests)): ?>
            <div class="requests-grid">
                <?php foreach ($pending_requests as $request): 
                    $start = new DateTime($request['start_date']);
                    $end = new DateTime($request['end_date']);
                ?>
                    <div class="request-card">
                        <div class="request-header">
                            <div class="employee-info">
                                <div class="employee-avatar">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <div class="employee-details">
                                    <div class="employee-name"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                    <div class="employee-contact"><?php echo htmlspecialchars($request['contact_info'] ?: 'No contact info'); ?></div>
                                </div>
                            </div>
                            <div class="request-meta">
                                <span class="request-date"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></span>
                                <span class="request-id">#<?php echo $request['request_id']; ?></span>
                            </div>
                        </div>
                        
                        <div class="request-body">
                            <div class="request-dates">
                                <div class="date-range">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo $start->format('M d, Y') . ' - ' . $end->format('M d, Y'); ?>
                                </div>
                                <div class="duration">
                                    <i class="fas fa-clock"></i>
                                    <?php echo $request['duration_days']; ?> days
                                </div>
                            </div>
                            
                            <div class="request-details">
                                <div class="detail-item">
                                    <span class="detail-label">Leave Type:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($request['leave_type']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Reason:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($request['reason'] ?: 'No reason provided'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="request-footer">
                            <div class="request-actions">
                                <a href="?page=approvals&action=approve&id=<?php echo $request['request_id']; ?>" class="btn btn-success" onclick="return confirm('Approve this leave request?')">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="?page=approvals&action=reject&id=<?php echo $request['request_id']; ?>" class="btn btn-danger" onclick="return confirm('Reject this leave request?')">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                                <button class="btn btn-secondary" onclick="viewRequestDetails(<?php echo $request['request_id']; ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No Pending Requests</h4>
                <p>All leave requests have been processed. Great job!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function viewRequestDetails(requestId) {
    // This would typically open a modal with detailed request information
    alert('Viewing details for request #' + requestId);
    // In a real implementation, you would fetch and display detailed request info
}
</script>

<style>
.requests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.request-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.employee-avatar {
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

.employee-details {
    display: flex;
    flex-direction: column;
}

.employee-name {
    font-weight: 600;
    color: var(--dark-color);
}

.employee-contact {
    font-size: 0.8rem;
    color: #666;
}

.request-meta {
    text-align: right;
}

.request-date {
    display: block;
    font-size: 0.8rem;
    color: #666;
}

.request-id {
    display: block;
    font-size: 0.7rem;
    color: #999;
    font-family: monospace;
}

.request-body {
    margin-bottom: 15px;
}

.request-dates {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.date-range, .duration {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
}

.date-range {
    color: var(--dark-color);
    font-weight: 500;
}

.duration {
    color: var(--primary-color);
    font-weight: 600;
}

.request-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
}

.detail-label {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.detail-value {
    color: #666;
    font-size: 0.9rem;
    text-align: right;
    max-width: 200px;
}

.request-footer {
    border-top: 1px solid #f0f0f0;
    padding-top: 15px;
}

.request-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.request-actions .btn {
    padding: 8px 12px;
    font-size: 0.8rem;
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
    margin: 0;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .requests-grid {
        grid-template-columns: 1fr;
    }
    
    .request-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .request-meta {
        text-align: left;
    }
    
    .request-actions {
        flex-direction: column;
    }
    
    .request-dates {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
}
</style>