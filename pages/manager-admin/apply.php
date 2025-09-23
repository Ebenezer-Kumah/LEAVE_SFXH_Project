<?php
// pages/manager-admin/apply.php

// Check if user is manager or admin
if ($_SESSION['user_role'] !== 'manager' && $_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../includes/notifications_helper.php';

// Get leave policies
$query = "SELECT * FROM Leave_Policies WHERE is_active = TRUE";
$stmt = $db->prepare($query);
$stmt->execute();
$leave_policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle leave application
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_leave'])) {
    try {
        $leave_type = $_POST['leave_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = trim($_POST['reason']);

        // Get user name
        $user_query = "SELECT name FROM Users WHERE user_id = :user_id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $user_stmt->execute();
        $user_name = $user_stmt->fetch(PDO::FETCH_ASSOC)['name'] ?? 'User';

        // Calculate duration
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $duration = $end->diff($start)->days + 1;

        // Insert leave request directly to admin (no manager approval needed)
        $query = "INSERT INTO Leave_Requests (employee_id, leave_type, start_date, end_date, reason, manager_id, status)
                  VALUES (:user_id, :leave_type, :start_date, :end_date, :reason, NULL, 'pending')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':leave_type', $leave_type);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->bindParam(':reason', $reason);

        if ($stmt->execute()) {
            // Insert notifications
            $start_formatted = date('M d, Y', strtotime($start_date));
            $end_formatted = date('M d, Y', strtotime($end_date));

            // Notify the user
            $user_message = "Your leave request for {$leave_type} from {$start_formatted} to {$end_formatted} has been submitted and is pending admin approval.";
            insertNotification($db, $_SESSION['user_id'], $user_message);

            // Notify admin
            $admin_query = "SELECT user_id FROM Users WHERE role = 'admin' AND is_active = TRUE";
            $admin_stmt = $db->prepare($admin_query);
            $admin_stmt->execute();
            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($admins as $admin) {
                $admin_message = "New leave request from {$user_name} ({$_SESSION['user_role']}) for {$leave_type} from {$start_formatted} to {$end_formatted}.";
                insertNotification($db, $admin['user_id'], $admin_message);
            }

            $message = 'Leave application submitted successfully! It is now pending admin approval.';

            // Clear form
            $_POST = [];
        }
    } catch (PDOException $e) {
        $error = 'Error submitting leave application: ' . $e->getMessage();
    }
}
?>

<div class="page-header">
    <h2>Apply for Leave</h2>
    <p>Submit a new leave request for admin approval.</p>
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

<div class="content-row">
    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Leave Application</h3>
                <button class="btn btn-primary" onclick="openModal('applyLeaveModal')">
                    <i class="fas fa-plus"></i> New Leave Request
                </button>
            </div>
            <div class="card-body">
                <p>Click the button above to submit a new leave application.</p>
                <div class="application-note">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> As a <?php echo $_SESSION['user_role']; ?>, your leave requests go directly to the admin for approval.
                </div>
            </div>
        </div>
    </div>

    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Quick Tips</h3>
            </div>
            <div class="card-body">
                <div class="tips-list">
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="tip-content">
                            <h4>Plan Ahead</h4>
                            <p>Submit leave requests at least 2 weeks in advance for better approval chances.</p>
                        </div>
                    </div>

                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="tip-content">
                            <h4>Provide Details</h4>
                            <p>Include clear reasons for your leave to help admins make informed decisions.</p>
                        </div>
                    </div>

                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="tip-content">
                            <h4>Direct to Admin</h4>
                            <p>Your requests are sent directly to admin for approval, bypassing manager approval.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Apply Leave Modal -->
<div id="applyLeaveModal" class="modal modal-popout">
    <div class="modal-backdrop" onclick="closeModal('applyLeaveModal')"></div>
    <div class="modal-content animated-popout">
        <div class="modal-header">
            <h3>Leave Application Form</h3>
            <button class="modal-close" onclick="closeModal('applyLeaveModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="" id="leaveForm">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="leave_type">Leave Type *</label>
                        <select id="leave_type" name="leave_type" required onchange="calculateDuration()">
                            <option value="">— Select Leave Type —</option>
                            <?php foreach ($leave_policies as $policy): ?>
                                <option value="<?php echo $policy['leave_type']; ?>">
                                    <?php echo $policy['leave_type']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>" onchange="calculateDuration()">
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date *</label>
                        <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>" onchange="calculateDuration()">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="duration">Duration</label>
                        <input type="text" id="duration" value="—" disabled>
                    </div>

                    <div class="form-group">
                        <label for="working_days">Working Days</label>
                        <input type="text" id="working_days" value="—" disabled>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Leave *</label>
                    <textarea id="reason" name="reason" rows="4" required placeholder="Please provide a reason for your leave application..."></textarea>
                </div>

                <div class="form-note">
                    <i class="fas fa-info-circle"></i>
                    Your leave request will be sent directly to the admin for approval. You'll be notified once it's processed.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('applyLeaveModal')">Cancel</button>
                <button type="reset" class="btn btn-secondary">Reset</button>
                <button type="submit" name="apply_leave" class="btn btn-primary">Submit Application</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function calculateDuration() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const durationField = document.getElementById('duration');
    const workingDaysField = document.getElementById('working_days');

    if (startDate.value && endDate.value) {
        const start = new Date(startDate.value);
        const end = new Date(endDate.value);

        if (start > end) {
            durationField.value = 'Invalid dates';
            workingDaysField.value = '—';
            return;
        }

        // Calculate total days
        const timeDiff = end - start;
        const totalDays = Math.floor(timeDiff / (1000 * 60 * 60 * 24)) + 1;
        durationField.value = totalDays + ' day' + (totalDays !== 1 ? 's' : '');

        // Calculate working days (simple implementation)
        let workingDays = 0;
        let currentDate = new Date(start);

        while (currentDate <= end) {
            const dayOfWeek = currentDate.getDay();
            if (dayOfWeek !== 0 && dayOfWeek !== 6) { // Skip weekends
                workingDays++;
            }
            currentDate.setDate(currentDate.getDate() + 1);
        }

        workingDaysField.value = workingDays + ' working day' + (workingDays !== 1 ? 's' : '');
    } else {
        durationField.value = '—';
        workingDaysField.value = '—';
    }
}

// Initialize form validation
document.getElementById('leaveForm').addEventListener('submit', function(e) {
    const leaveType = document.getElementById('leave_type');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const reason = document.getElementById('reason');

    if (!leaveType.value || !startDate.value || !endDate.value || !reason.value.trim()) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }

    if (new Date(startDate.value) > new Date(endDate.value)) {
        e.preventDefault();
        alert('End date cannot be before start date.');
        return false;
    }

    // Additional validation can be added here
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum dates to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('start_date').min = today;
    document.getElementById('end_date').min = today;
});

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Close alerts
document.querySelectorAll('.alert-close').forEach(button => {
    button.addEventListener('click', function() {
        this.parentElement.style.display = 'none';
    });
});
</script>

<style>
.application-note {
    padding: 15px;
    background-color: #e8f4fd;
    border-radius: 6px;
    margin-top: 15px;
    font-size: 0.9rem;
    color: #0c5460;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.application-note i {
    color: #0c5460;
    margin-top: 2px;
    flex-shrink: 0;
}

.tips-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.tip-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.tip-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.2rem;
    flex-shrink: 0;
}

.tip-content h4 {
    margin: 0 0 5px 0;
    color: var(--dark-color);
    font-size: 1rem;
}

.tip-content p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Modal popout styling */
.modal-popout {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.modal-popout[style*="display: block"] {
    display: flex;
}

.modal-backdrop {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.45);
    z-index: 1;
}

.animated-popout {
    position: relative;
    z-index: 2;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    max-width: 500px;
    width: 100%;
    margin: auto;
    animation: popoutModal 0.25s cubic-bezier(.4,2,.3,1) 1;
    padding: 0;
    overflow: hidden;
}

@keyframes popoutModal {
    from { transform: scale(0.85); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.modal-header {
    background: var(--primary-color, #007bff);
    color: #fff;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.2rem;
    cursor: pointer;
    transition: color 0.2s;
}

.modal-close:hover {
    color: #ffdddd;
}

.modal-body {
    padding: 22px 24px 10px 24px;
    background: #f9f9f9;
}

.form-group {
    margin-bottom: 18px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 6px;
    display: block;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
    font-family: inherit;
    background: #fff;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary-color, #007bff);
    outline: none;
}

.modal-footer {
    padding: 16px 24px;
    background: #f1f1f1;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-note {
    padding: 15px;
    background-color: #e8f4fd;
    border-radius: 6px;
    margin: 20px 0;
    font-size: 0.9rem;
    color: #0c5460;
}

.form-note i {
    color: #0c5460;
    margin-right: 8px;
}

@media (max-width: 600px) {
    .animated-popout {
        max-width: 98vw;
        padding: 0;
    }
    .modal-header, .modal-body, .modal-footer {
        padding-left: 12px;
        padding-right: 12px;
    }
}

@media (max-width: 768px) {
    .tip-item {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
}
</style>