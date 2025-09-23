<?php
// pages/admin/policies.php

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle form actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// Add new policy
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_policy'])) {
    try {
        $leave_type = trim($_POST['leave_type']);
        $entitlement_days = (int)$_POST['entitlement_days'];
        $carry_forward = isset($_POST['carry_forward']) ? 1 : 0;
        $max_carry_forward_days = $carry_forward ? (int)$_POST['max_carry_forward_days'] : 0;
        $approval_flow = $_POST['approval_flow'];
        $description = trim($_POST['description']);
        
        $query = "INSERT INTO Leave_Policies (leave_type, entitlement_days, carry_forward, max_carry_forward_days, approval_flow, description) 
                  VALUES (:leave_type, :entitlement_days, :carry_forward, :max_carry_forward_days, :approval_flow, :description)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':leave_type', $leave_type);
        $stmt->bindParam(':entitlement_days', $entitlement_days);
        $stmt->bindParam(':carry_forward', $carry_forward);
        $stmt->bindParam(':max_carry_forward_days', $max_carry_forward_days);
        $stmt->bindParam(':approval_flow', $approval_flow);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            $message = 'Leave policy added successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error adding leave policy: ' . $e->getMessage();
    }
}

// Update policy
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_policy'])) {
    try {
        $policy_id = $_POST['policy_id'];
        $leave_type = trim($_POST['leave_type']);
        $entitlement_days = (int)$_POST['entitlement_days'];
        $carry_forward = isset($_POST['carry_forward']) ? 1 : 0;
        $max_carry_forward_days = $carry_forward ? (int)$_POST['max_carry_forward_days'] : 0;
        $approval_flow = $_POST['approval_flow'];
        $description = trim($_POST['description']);
        
        $query = "UPDATE Leave_Policies 
                  SET leave_type = :leave_type, 
                      entitlement_days = :entitlement_days, 
                      carry_forward = :carry_forward,
                      max_carry_forward_days = :max_carry_forward_days,
                      approval_flow = :approval_flow,
                      description = :description
                  WHERE policy_id = :policy_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':policy_id', $policy_id);
        $stmt->bindParam(':leave_type', $leave_type);
        $stmt->bindParam(':entitlement_days', $entitlement_days);
        $stmt->bindParam(':carry_forward', $carry_forward);
        $stmt->bindParam(':max_carry_forward_days', $max_carry_forward_days);
        $stmt->bindParam(':approval_flow', $approval_flow);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            $message = 'Leave policy updated successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error updating leave policy: ' . $e->getMessage();
    }
}

// Delete policy
if ($action == 'delete' && isset($_GET['id'])) {
    try {
        $policy_id = $_GET['id'];
        
        // Check if policy is being used
        $check_query = "SELECT COUNT(*) as usage_count FROM Leave_Requests WHERE leave_type = 
                       (SELECT leave_type FROM Leave_Policies WHERE policy_id = :policy_id)";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':policy_id', $policy_id);
        $check_stmt->execute();
        $usage_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['usage_count'];
        
        if ($usage_count > 0) {
            $error = 'Cannot delete policy that is being used by leave requests.';
        } else {
            $query = "DELETE FROM Leave_Policies WHERE policy_id = :policy_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':policy_id', $policy_id);
            
            if ($stmt->execute()) {
                $message = 'Leave policy deleted successfully!';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error deleting leave policy: ' . $e->getMessage();
    }
}

// Toggle policy status
if ($action == 'toggle' && isset($_GET['id'])) {
    try {
        $policy_id = $_GET['id'];
        $query = "UPDATE Leave_Policies SET is_active = NOT is_active WHERE policy_id = :policy_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':policy_id', $policy_id);
        
        if ($stmt->execute()) {
            $message = 'Policy status updated successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error updating policy status: ' . $e->getMessage();
    }
}

// Get all leave policies
$query = "SELECT * FROM Leave_Policies ORDER BY leave_type";
$stmt = $db->prepare($query);
$stmt->execute();
$policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get policy statistics
$query = "SELECT 
            COUNT(*) as total_policies,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_policies,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_policies,
            SUM(entitlement_days) as total_entitlement_days
          FROM Leave_Policies";
$stmt = $db->prepare($query);
$stmt->execute();
$policy_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get usage statistics
$query = "SELECT lp.leave_type, COUNT(lr.request_id) as usage_count
          FROM Leave_Policies lp
          LEFT JOIN Leave_Requests lr ON lp.leave_type = lr.leave_type
          GROUP BY lp.leave_type";
$stmt = $db->prepare($query);
$stmt->execute();
$usage_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>Leave Policy Management</h2>
    <p>Configure and manage leave policies for the hospital. Set entitlements, carry-over rules, and approval workflows.</p>
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
            <h3 class="card-title">Total Policies</h3>
            <div class="card-icon">
                <i class="fas fa-file-alt"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $policy_stats['total_policies']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> All leave policies
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Active Policies</h3>
            <div class="card-icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $policy_stats['active_policies']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Currently enabled
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Total Entitlement</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $policy_stats['total_entitlement_days']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Days allocated
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Inactive Policies</h3>
            <div class="card-icon">
                <i class="fas fa-ban"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $policy_stats['inactive_policies']; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Currently disabled
        </div>
    </div>
</div>

<div class="content-row">
    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Leave Policies</h3>
                <button class="btn btn-primary" onclick="openModal('addPolicyModal')">
                    <i class="fas fa-plus"></i> Add New Policy
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Entitlement</th>
                                <th>Carry Forward</th>
                                <th>Approval Flow</th>
                                <th>Status</th>
                                <th>Usage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($policies as $policy): 
                                $usage_count = 0;
                                foreach ($usage_stats as $usage) {
                                    if ($usage['leave_type'] === $policy['leave_type']) {
                                        $usage_count = $usage['usage_count'];
                                        break;
                                    }
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div class="policy-info">
                                            <div class="policy-icon">
                                                <i class="fas fa-calendar-check"></i>
                                            </div>
                                            <div class="policy-details">
                                                <div class="policy-name"><?php echo htmlspecialchars($policy['leave_type']); ?></div>
                                                <div class="policy-desc"><?php echo htmlspecialchars($policy['description'] ?: '—'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="entitlement-info">
                                            <span class="entitlement-days"><?php echo $policy['entitlement_days']; ?></span>
                                            <span class="entitlement-label">days/year</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($policy['carry_forward']): ?>
                                            <div class="carry-forward-info">
                                                <span class="carry-forward-yes">Yes</span>
                                                <span class="carry-forward-days">(max <?php echo $policy['max_carry_forward_days']; ?> days)</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="carry-forward-no">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="approval-flow approval-<?php echo strtolower($policy['approval_flow']); ?>">
                                            <?php echo ucfirst($policy['approval_flow']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $policy['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $policy['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="usage-info">
                                            <span class="usage-count"><?php echo $usage_count; ?></span>
                                            <span class="usage-label">requests</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" data-policy-id="<?php echo $policy['policy_id']; ?>" data-leave-type="<?php echo htmlspecialchars($policy['leave_type']); ?>" data-entitlement-days="<?php echo $policy['entitlement_days']; ?>" data-carry-forward="<?php echo $policy['carry_forward']; ?>" data-max-carry-forward-days="<?php echo $policy['max_carry_forward_days']; ?>" data-approval-flow="<?php echo $policy['approval_flow']; ?>" data-description="<?php echo htmlspecialchars($policy['description']); ?>" onclick="editPolicy(this)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?page=policies&action=toggle&id=<?php echo $policy['policy_id']; ?>" class="btn-icon <?php echo $policy['is_active'] ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $policy['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas <?php echo $policy['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </a>
                                            <a href="?page=policies&action=delete&id=<?php echo $policy['policy_id']; ?>" class="btn-icon btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this policy?')">
                                                <i class="fas fa-trash"></i>
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
    </div>
</div>

<!-- Add Policy Modal -->
<div id="addPolicyModal" class="modal modal-popout">
    <div class="modal-backdrop" onclick="closeModal('addPolicyModal')"></div>
    <div class="modal-content animated-popout">
        <div class="modal-header">
            <h3>Add New Leave Policy</h3>
            <button class="modal-close" onclick="closeModal('addPolicyModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="leave_type">Leave Type *</label>
                    <input type="text" id="leave_type" name="leave_type" required placeholder="e.g., Annual Leave, Sick Leave">
                </div>
                <div class="form-group">
                    <label for="entitlement_days">Entitlement Days *</label>
                    <input type="number" id="entitlement_days" name="entitlement_days" min="0" max="365" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="carry_forward" name="carry_forward" onchange="toggleCarryForward()">
                            <span class="checkmark"></span>
                            Allow Carry Forward
                        </label>
                    </div>
                    <div class="form-group" id="max_carry_forward_group" style="display: none;">
                        <label for="max_carry_forward_days">Max Carry Forward Days</label>
                        <input type="number" id="max_carry_forward_days" name="max_carry_forward_days" min="0" max="365" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label for="approval_flow">Approval Flow *</label>
                    <select id="approval_flow" name="approval_flow" required>
                        <option value="manager">Manager Only</option>
                        <option value="admin">Admin Only</option>
                        <option value="both">Manager then Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Policy description and guidelines"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPolicyModal')">Cancel</button>
                <button type="submit" name="add_policy" class="btn btn-primary">Add Policy</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Policy Modal -->
<div id="editPolicyModal" class="modal modal-popout">
    <div class="modal-backdrop" onclick="closeModal('editPolicyModal')"></div>
    <div class="modal-content animated-popout">
        <div class="modal-header">
            <h3>Edit Leave Policy</h3>
            <button class="modal-close" onclick="closeModal('editPolicyModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <input type="hidden" id="edit_policy_id" name="policy_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_leave_type">Leave Type *</label>
                    <input type="text" id="edit_leave_type" name="leave_type" required>
                </div>
                <div class="form-group">
                    <label for="edit_entitlement_days">Entitlement Days *</label>
                    <input type="number" id="edit_entitlement_days" name="entitlement_days" min="0" max="365" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="edit_carry_forward" name="carry_forward" onchange="toggleEditCarryForward()">
                            <span class="checkmark"></span>
                            Allow Carry Forward
                        </label>
                    </div>
                    <div class="form-group" id="edit_max_carry_forward_group">
                        <label for="edit_max_carry_forward_days">Max Carry Forward Days</label>
                        <input type="number" id="edit_max_carry_forward_days" name="max_carry_forward_days" min="0" max="365">
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_approval_flow">Approval Flow *</label>
                    <select id="edit_approval_flow" name="approval_flow" required>
                        <option value="manager">Manager Only</option>
                        <option value="admin">Admin Only</option>
                        <option value="both">Manager then Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPolicyModal')">Cancel</button>
                <button type="submit" name="update_policy" class="btn btn-primary">Update Policy</button>
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

function toggleCarryForward() {
    const carryForward = document.getElementById('carry_forward');
    const maxCarryGroup = document.getElementById('max_carry_forward_group');
    maxCarryGroup.style.display = carryForward.checked ? 'block' : 'none';
}

function toggleEditCarryForward() {
    const carryForward = document.getElementById('edit_carry_forward');
    const maxCarryGroup = document.getElementById('edit_max_carry_forward_group');
    maxCarryGroup.style.display = carryForward.checked ? 'block' : 'none';
}

function editPolicy(button) {
    const policyId = button.dataset.policyId;
    const leaveType = button.dataset.leaveType;
    const entitlementDays = button.dataset.entitlementDays;
    const carryForward = button.dataset.carryForward === '1';
    const maxCarryForwardDays = button.dataset.maxCarryForwardDays;
    const approvalFlow = button.dataset.approvalFlow;
    const description = button.dataset.description || '';
    
    document.getElementById('edit_policy_id').value = policyId;
    document.getElementById('edit_leave_type').value = leaveType;
    document.getElementById('edit_entitlement_days').value = entitlementDays;
    document.getElementById('edit_carry_forward').checked = carryForward;
    document.getElementById('edit_max_carry_forward_days').value = maxCarryForwardDays;
    document.getElementById('edit_approval_flow').value = approvalFlow;
    document.getElementById('edit_description').value = description;
    
    toggleEditCarryForward();
    openModal('editPolicyModal');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let i = 0; i < modals.length; i++) {
        if (event.target == modals[i]) {
            modals[i].style.display = 'none';
        }
    }
}

// Close alerts
document.querySelectorAll('.alert-close').forEach(button => {
    button.addEventListener('click', function() {
        this.parentElement.style.display = 'none';
    });
});

// Initialize carry forward toggle
document.addEventListener('DOMContentLoaded', function() {
    toggleCarryForward();
});
</script>

<style>
.policy-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.policy-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.policy-details {
    display: flex;
    flex-direction: column;
}

.policy-name {
    font-weight: 600;
    color: var(--dark-color);
}

.policy-desc {
    font-size: 0.8rem;
    color: #666;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entitlement-info {
    text-align: center;
}

.entitlement-days {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
}

.entitlement-label {
    font-size: 0.8rem;
    color: #666;
}

.carry-forward-info {
    text-align: center;
}

.carry-forward-yes {
    display: block;
    font-weight: 600;
    color: #28a745;
}

.carry-forward-days {
    font-size: 0.8rem;
    color: #666;
}

.carry-forward-no {
    color: #dc3545;
    font-style: italic;
}

.approval-flow {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.approval-manager {
    background-color: #3498db;
    color: white;
}

.approval-admin {
    background-color: #e74c3c;
    color: white;
}

.approval-both {
    background-color: #f39c12;
    color: white;
}

.usage-info {
    text-align: center;
}

.usage-count {
    display: block;
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--dark-color);
}

.usage-label {
    font-size: 0.8rem;
    color: #666;
}

.modal .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    align-items: end;
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
    max-width: 420px;
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

/* ...existing code... */
</style>