<?php
// pages/profile.php

// Include database connection
require_once __DIR__ . '/../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, d.department_name 
          FROM Users u 
          LEFT JOIN Departments d ON u.department_id = d.department_id 
          WHERE u.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get leave balances
$query = "SELECT * FROM Leave_Balances 
          WHERE employee_id = :user_id 
          AND year = YEAR(CURRENT_DATE())";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$leave_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle profile update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    try {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $contact_info = trim($_POST['contact_info']);
        
        // Handle profile picture upload
        $profile_picture = $user['profile_picture']; // Keep existing if no new upload
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            
            // Validate file type
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'jfif', 'bmp', 'tiff'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                    $profile_picture = $target_path;
                    
                    // Delete old profile picture if exists and it's not the default
                    if ($user['profile_picture'] && file_exists($user['profile_picture']) && 
                        !str_contains($user['profile_picture'], 'default-avatar')) {
                        unlink($user['profile_picture']);
                    }
                }
            }
        }
        
        $query = "UPDATE Users 
                  SET name = :name, email = :email, contact_info = :contact_info, profile_picture = :profile_picture 
                  WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':contact_info', $contact_info);
        $stmt->bindParam(':profile_picture', $profile_picture);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name;
            $_SESSION['profile_picture'] = $profile_picture;
            $message = 'Profile updated successfully!';
            // Refresh user data
            $stmt = $db->prepare("SELECT u.*, d.department_name FROM Users u LEFT JOIN Departments d ON u.department_id = d.department_id WHERE u.user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = 'Error updating profile: ' . $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } else {
            // Verify current password
            $query = "SELECT password FROM Users WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $db_password = $stmt->fetch(PDO::FETCH_ASSOC)['password'];
            
            if (password_verify($current_password, $db_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE Users SET password = :password WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':user_id', $user_id);
                
                if ($stmt->execute()) {
                    $message = 'Password changed successfully!';
                }
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error changing password: ' . $e->getMessage();
    }
}
?>

<div class="page-header">
    <h2>My Profile</h2>
    <p>Manage your personal information and account settings.</p>
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
                <h3>Personal Information</h3>
                <button class="btn btn-primary" onclick="openModal('updateProfileModal')">
                    <i class="fas fa-edit"></i> Edit Profile
                </button>
            </div>
            <div class="card-body">
                <div class="profile-info">
                    <div class="profile-avatar-section">
                        <div class="profile-avatar">
                            <img src="<?php echo $user['profile_picture'] ?: '../assets/default-avatar.png'; ?>" 
                                 alt="Profile Picture" 
                                 onerror="this.src='../assets/default-avatar.png'">
                        </div>
                        <div class="profile-details">
                            <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                            <p class="profile-role"><?php echo ucfirst($user['role']); ?></p>
                            <p class="profile-department"><?php echo htmlspecialchars($user['department_name'] ?: 'Not assigned'); ?></p>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-row">
                            <span class="info-label">Email Address:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Contact Information:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['contact_info'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">User ID:</span>
                            <span class="info-value"><?php echo $user['user_id']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Status:</span>
                            <span class="info-value status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Leave Balances</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($leave_balances)): ?>
                    <div class="balances-grid">
                        <?php foreach ($leave_balances as $balance): ?>
                            <div class="balance-card">
                                <div class="balance-type"><?php echo htmlspecialchars($balance['leave_type']); ?></div>
                                <div class="balance-details">
                                    <div class="balance-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min(($balance['used_days'] / $balance['total_entitlement']) * 100, 100); ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="balance-numbers">
                                        <span class="remaining"><?php echo $balance['remaining_days']; ?> days left</span>
                                        <span class="total">of <?php echo $balance['total_entitlement']; ?> days</span>
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
    </div>

    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Change Password</h3>
                <button class="btn btn-primary" onclick="openModal('changePasswordModal')">
                    <i class="fas fa-edit"></i> Change Password
                </button>
            </div>
            <div class="card-body">
                <div class="password-info">
                    <p>Your password was last changed on <?php echo date('M j, Y', strtotime($user['updated_at'])); ?>.</p>
                    <p class="password-tip"><i class="fas fa-info-circle"></i> Use a strong password with at least 6 characters.</p>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Account Information</h3>
            </div>
            <div class="card-body">
                <div class="account-info">
                    <div class="info-item">
                        <span class="info-label">Account Created:</span>
                        <span class="info-value"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Updated:</span>
                        <span class="info-value"><?php echo date('M j, Y', strtotime($user['updated_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Login:</span>
                        <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($user['last_login'] ?? $user['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Recent Activity</h3>
            </div>
            <div class="card-body">
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="activity-content">
                            <p>Profile information updated</p>
                            <span class="activity-time">2 hours ago</span>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="activity-content">
                            <p>Password changed</p>
                            <span class="activity-time">3 days ago</span>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="activity-content">
                            <p>Leave request submitted</p>
                            <span class="activity-time">1 week ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Profile Modal -->
<div id="updateProfileModal" class="modal modal-popout">
    <div class="modal-backdrop" onclick="closeModal('updateProfileModal')"></div>
    <div class="modal-content animated-popout">
        <div class="modal-header">
            <h3>Edit Profile</h3>
            <button class="modal-close" onclick="closeModal('updateProfileModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="modal-body">
                <div class="form-group text-center">
                    <div class="avatar-upload">
                        <div class="avatar-preview">
                            <img src="<?php echo $user['profile_picture'] ?: '../assets/default-avatar.png'; ?>" 
                                 alt="Profile Preview" 
                                 onerror="this.src='../assets/default-avatar.png'"
                                 id="avatarPreview">
                        </div>
                        <label for="profile_picture" class="avatar-upload-btn">
                            <i class="fas fa-camera"></i> Change Photo
                        </label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*" 
                               style="display: none;" onchange="previewImage(this)">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="contact_info">Contact Information</label>
                    <input type="text" id="contact_info" name="contact_info" value="<?php echo htmlspecialchars($user['contact_info'] ?: ''); ?>" placeholder="Phone number or extension">
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <input type="text" id="role" value="<?php echo ucfirst($user['role']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" id="department" value="<?php echo htmlspecialchars($user['department_name'] ?: 'Not assigned'); ?>" disabled>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateProfileModal')">Cancel</button>
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="modal modal-popout">
    <div class="modal-backdrop" onclick="closeModal('changePasswordModal')"></div>
    <div class="modal-content animated-popout">
        <div class="modal-header">
            <h3>Change Password</h3>
            <button class="modal-close" onclick="closeModal('changePasswordModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="current_password">Current Password *</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                    <div class="form-help">Minimum 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

function previewImage(input) {
    const preview = document.getElementById('avatarPreview');
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-backdrop')) {
        closeModal(event.target.parentElement.id);
    }
});

// Close alerts
document.querySelectorAll('.alert-close').forEach(button => {
    button.addEventListener('click', function() {
        this.parentElement.style.display = 'none';
    });
});

// Password confirmation validation
document.querySelector('form[name="change_password"]').addEventListener('submit', function(e) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New passwords do not match.');
        return false;
    }
});

// File upload size validation
document.getElementById('profile_picture').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file && file.size > 2 * 1024 * 1024) { // 2MB limit
        alert('File size must be less than 2MB.');
        this.value = '';
    }
});
</script>

<style>
.profile-avatar-section {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid rgba(255,255,255,0.3);
    flex-shrink: 0;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-details h3 {
    margin: 0 0 5px 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.profile-role {
    margin: 0 0 3px 0;
    opacity: 0.9;
    font-size: 0.9rem;
}

.profile-department {
    margin: 0;
    opacity: 0.8;
    font-size: 0.85rem;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--dark-color);
}

.info-value {
    color: #666;
    text-align: right;
}

.status-active {
    color: #28a745;
    font-weight: 600;
}

.status-inactive {
    color: #dc3545;
    font-weight: 600;
}

/* Avatar upload styles */
.avatar-upload {
    text-align: center;
    margin-bottom: 20px;
}

.avatar-preview {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto 15px;
    border: 3px solid #e0e0e0;
}

.avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-upload-btn {
    display: inline-block;
    padding: 8px 16px;
    background: var(--primary-color);
    color: white;
    border-radius: 20px;
    cursor: pointer;
    transition: background 0.3s ease;
    font-size: 0.9rem;
}

.avatar-upload-btn:hover {
    background: var(--secondary-color);
}

/* Responsive design */
@media (max-width: 768px) {
    .profile-avatar-section {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .info-value {
        text-align: left;
    }
}

/* Modal enhancements */
.modal-popout {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.modal-backdrop {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1;
}

.animated-popout {
    position: relative;
    z-index: 2;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    max-width: 500px;
    width: 95%;
    margin: 20px;
    animation: popoutModal 0.3s ease;
    max-height: 90vh;
    overflow-y: auto;
}

@keyframes popoutModal {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.modal-header {
    background: var(--primary-color);
    color: white;
    padding: 20px;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.3rem;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.text-center {
    text-align: center;
}

/* Additional styles for the existing elements */
.balances-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.balance-card {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid var(--primary-color);
}

.balance-type {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 10px;
}

.balance-numbers {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
}

.remaining {
    font-weight: 600;
    color: var(--primary-color);
}

.total {
    font-size: 0.9rem;
    color: #666;
}

.password-info {
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.password-tip {
    font-size: 0.9rem;
    color: #666;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.account-info {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.info-item:last-child {
    border-bottom: none;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.activity-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 0.9rem;
}

.activity-content p {
    margin: 0;
    font-weight: 500;
}

.activity-time {
    font-size: 0.8rem;
    color: #666;
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

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}
</style>