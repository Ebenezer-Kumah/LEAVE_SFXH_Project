<?php
// includes/sidebar.php
require_once 'includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user profile picture
$user_id = $_SESSION['user_id'];
$query = "SELECT profile_picture FROM Users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user_profile = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $user_profile['profile_picture'] ?? '';

$sidebar_collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true';
?>
<div class="sidebar <?php echo $sidebar_collapsed ? 'collapsed' : ''; ?>">
    <div class="sidebar-header">
        <div class="logo">
            <img src="assets/logo's.png" alt="St. Francis Xavier Hospital Logo">
            <span class="org-name">St. Francis Xavier Hospital</span>
        </div>
        <button id="sidebar-toggle" class="sidebar-toggle">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    
    <div class="sidebar-user">
        <div class="user-avatar">
            <?php if ($profile_picture): ?>
                <img src="uploads/profiles/<?php echo htmlspecialchars($profile_picture); ?>"
                     alt="Profile Picture"
                     onerror="this.src='assets/default-avatar.png'">
            <?php else: ?>
                <img src="assets/default-avatar.png" alt="Default Avatar">
            <?php endif; ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo $user_name; ?></div>
            <div class="user-role"><?php echo ucfirst($user_role); ?></div>
        </div>
    </div>
    
    <style>
    .user-avatar img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255,255,255,0.3);
    }
    </style>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <?php if ($user_role == 'admin'): ?>
                <li class="nav-item">
                    <a href="?page=dashboard" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=users" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Manage Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=departments" class="nav-link">
                        <i class="fas fa-building"></i>
                        <span class="nav-text">Departments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=policies" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        <span class="nav-text">Leave Policies</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=reports" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=calendar" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="nav-text">Organization Calendar</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=apply" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        <span class="nav-text">Apply for Leave</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=leave-requests" class="nav-link">
                        <i class="fas fa-clipboard-list"></i>
                        <span class="nav-text">Leave Requests</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=history" class="nav-link">
                        <i class="fas fa-history"></i>
                        <span class="nav-text">Leave History</span>
                    </a>
                </li>

            <?php elseif ($user_role == 'manager'): ?>
                <li class="nav-item">
                    <a href="?page=dashboard" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=requests" class="nav-link">
                        <i class="fas fa-clipboard-list"></i>
                        <span class="nav-text">Leave Requests</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=calendar" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="nav-text">Team Calendar</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=reports" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Department Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=apply" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        <span class="nav-text">Apply for Leave</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=history" class="nav-link">
                        <i class="fas fa-history"></i>
                        <span class="nav-text">Leave History</span>
                    </a>
                </li>
                
            <?php else: ?>
                <li class="nav-item">
                    <a href="?page=dashboard" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=apply" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        <span class="nav-text">Apply for Leave</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=history" class="nav-link">
                        <i class="fas fa-history"></i>
                        <span class="nav-text">Leave History</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=balance" class="nav-link">
                        <i class="fas fa-chart-pie"></i>
                        <span class="nav-text">Leave Balance</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a href="?page=profile" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span class="nav-text">Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="hospital-info">
            <p><i class="fas fa-phone"></i> +233 24 493 4307 </p>
            <p><i class="fas fa-envelope"></i> sisteric@stfrancishsc.org </p>
            <p><i class="fas fa-map-marker-alt"></i> 120 Mankessim - Kumasi Rd, Fosu</p>
        </div>
    </div>
</div>