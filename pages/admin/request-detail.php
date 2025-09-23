<?php
// pages/admin/request-detail.php

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Get request ID
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Handle approval actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
if ($action && $request_id && in_array($action, ['approve', 'reject'])) {
    try {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $query = "UPDATE Leave_Requests 
                  SET status = :status, 
                      updated_at = CURRENT_TIMESTAMP 
                  WHERE request_id = :request_id 
                  AND status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':request_id', $request_id);
        
        if ($stmt->execute()) {
            $message = "Leave request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
            
            // Update leave balance if approved (only for employees, not managers/admins)
            if ($action === 'approve') {
                $query = "SELECT lr.employee_id, lr.leave_type, u.role,
                         DATEDIFF(lr.end_date, lr.start_date) + 1 as days
                         FROM Leave_Requests lr
                         JOIN Users u ON lr.employee_id = u.user_id
                         WHERE lr.request_id = :request_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':request_id', $request_id);
                $stmt->execute();
                $request_info = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($request_info && $request_info['role'] === 'employee') {
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
            
            // Redirect to leave requests after action
            header("Location: ?page=leave-requests&success=" . $action);
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error " . $action . "ing request: " . $e->getMessage();
    }
}

// Fetch request details
if ($request_id) {
    $query = "SELECT lr.*, u.name as employee_name, u.email as employee_email, u.contact_info,
              d.department_name, u.role as user_role, lb.remaining_days as balance_remaining
              FROM Leave_Requests lr
              JOIN Users u ON lr.employee_id = u.user_id
              LEFT JOIN Departments d ON u.department_id = d.department_id
              LEFT JOIN Leave_Balances lb ON u.user_id = lb.employee_id AND lr.leave_type = lb.leave_type AND lb.year = YEAR(CURDATE())
              WHERE lr.request_id = :request_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':request_id', $request_id);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $error = "Request not found.";
    }
} else {
    $error = "No request ID provided.";
}
?>

<div class="page-header">
    <h2>Leave Request Details</h2>
    <p>Request #<?php echo $request_id; ?></p>
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

<?php if ($request): ?>
    <div class="content-card request-detail-card">
        <div class="card-header">
            <h3><?php echo htmlspecialchars($request['leave_type']); ?> Leave Request</h3>
            <div class="header-badges">
                <span class="status-badge status-<?php echo $request['status']; ?>">
                    <?php echo ucfirst($request['status']); ?>
                </span>
                <span class="role-badge role-<?php echo $request['user_role']; ?>">
                    <?php echo ucfirst($request['user_role']); ?>
                </span>
            </div>
        </div>
        
        <div class="card-body">
            <div class="detail-sections">
                <!-- Employee Information -->
                <div class="detail-section">
                    <h4><i class="fas fa-user"></i> Employee Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['employee_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['employee_email']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Department:</span>
                            <span class="detail-value"><?php echo $request['department_name'] ? htmlspecialchars($request['department_name']) : '—'; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value"><?php echo $request['contact_info'] ? htmlspecialchars($request['contact_info']) : '—'; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Leave Details -->
                <div class="detail-section">
                    <h4><i class="fas fa-calendar"></i> Leave Details</h4>
                    <?php 
                        $start = new DateTime($request['start_date']);
                        $end = new DateTime($request['end_date']);
                        $duration = $request['end_date'] ? (new DateTime($request['end_date']))->diff(new DateTime($request['start_date']))->days + 1 : 1;
                    ?>
                    <div class="detail-grid">
                        <div class="detail-item full-width">
                            <span class="detail-label">Dates:</span>
                            <span class="detail-value"><?php echo $start->format('M d, Y'); ?> - <?php echo $end->format('M d, Y'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Duration:</span>
                            <span class="detail-value"><?php echo $duration; ?> day(s)</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Type:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['leave_type']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Remaining Balance:</span>
                            <span class="detail-value">
                                <?php
                                if ($request['user_role'] === 'employee') {
                                    echo ($request['balance_remaining'] ?? '—') . ' days';
                                } else {
                                    echo 'N/A (Manager/Admin)';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Request Information -->
                <div class="detail-section">
                    <h4><i class="fas fa-info-circle"></i> Request Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item full-width">
                            <span class="detail-label">Reason:</span>
                            <div class="detail-value reason-text"><?php echo $request['reason'] ? htmlspecialchars($request['reason']) : 'No reason provided'; ?></div>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Applied On:</span>
                            <span class="detail-value"><?php echo date('M d, Y g:i A', strtotime($request['created_at'])); ?></span>
                        </div>
                        <?php if ($request['updated_at']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Last Updated:</span>
                            <span class="detail-value"><?php echo date('M d, Y g:i A', strtotime($request['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <?php if ($request['status'] == 'pending'): ?>
            <div class="request-actions-section">
                <h4>Actions</h4>
                <div class="action-buttons large">
                    <a href="?page=request-detail&id=<?php echo $request_id; ?>&action=approve" class="btn btn-success" onclick="return confirm('Approve this leave request?<?php echo ($request['user_role'] === 'employee' ? ' This will deduct from the employee\'s balance.' : ''); ?>')">
                        <i class="fas fa-check"></i> Approve Request
                    </a>
                    <a href="?page=request-detail&id=<?php echo $request_id; ?>&action=reject" class="btn btn-danger" onclick="return confirm('Reject this leave request?')">
                        <i class="fas fa-times"></i> Reject Request
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Back Link -->
            <div class="back-link">
                <a href="?page=leave-requests" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Leave Requests
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.alert-close').forEach(button => {
    button.addEventListener('click', function() {
        this.parentElement.style.display = 'none';
    });
});
</script>

<style>
.request-detail-card {
    max-width: 800px;
    margin: 0 auto;
}

.detail-sections {
    display: flex;
    flex-direction: column;
    gap: 30px;
    margin-bottom: 30px;
}

.detail-section h4 {
    color: var(--dark-color);
    margin-bottom: 15px;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    font-weight: 600;
    color: #666;
    margin-bottom: 4px;
    font-size: 0.95rem;
}

.detail-value {
    color: var(--dark-color);
    padding: 8px 12px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid var(--primary-color);
}

.reason-text {
    white-space: pre-wrap;
    line-height: 1.5;
}

.request-actions-section {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.request-actions-section h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: var(--dark-color);
}

.action-buttons.large {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.action-buttons.large .btn {
    padding: 12px 24px;
    font-size: 1rem;
    min-width: 150px;
}

.back-link {
    text-align: center;
    margin-top: 20px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.role-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-employee {
    background-color: rgba(255, 255, 255, 0.2);
    color: #fff;
}

.role-manager {
    background-color: rgba(255, 193, 7, 0.2);
    color: #fff;
}

.role-admin {
    background-color: rgba(0, 123, 255, 0.2);
    color: #fff;
}

.status-pending { background-color: #fff3cd; color: #856404; }
.status-approved { background-color: #d4edda; color: #155724; }
.status-rejected { background-color: #f8d7da; color: #721c24; }
.status-cancelled { background-color: #e2e3e5; color: #383d41; }

@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons.large {
        flex-direction: column;
        align-items: center;
    }
    
    .action-buttons.large .btn {
        width: 100%;
        max-width: 250px;
    }
}
</style>