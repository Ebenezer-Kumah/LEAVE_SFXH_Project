<?php
// includes/header.php
require_once 'includes/database.php';
$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT name, role, profile_picture FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user['name'] ?? 'User';
$user_role = $user['role'] ?? 'Employee';
$profile_picture = $user['profile_picture'] ?? '';

// Helper function for time ago
function time_ago($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff_in_hours = $diff->days * 24 + $diff->h;
    if ($diff_in_hours < 1) {
        return $diff->i . ' minutes ago';
    } elseif ($diff_in_hours < 24) {
        return $diff_in_hours . ' hours ago';
    } elseif ($diff->days < 7) {
        return $diff->days . ' days ago';
    } else {
        return $ago->format('M j');
    }
}

// Fetch unread count
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM Notifications WHERE user_id = ? AND status = 'unread'");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

// Fetch recent notifications (last 5, unread first)
$stmt = $conn->prepare("
    SELECT * FROM Notifications
    WHERE user_id = ?
    ORDER BY
        CASE WHEN status = 'unread' THEN 0 ELSE 1 END,
        created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<header class="header">
    <div class="header-left">
        <button id="mobile-menu-toggle" class="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="header-title">Employee Leave Management System</h1>
    </div>
    
    <div class="header-center">
        <!-- Empty or future use -->
    </div>
    
    <div class="header-right">
        <div class="header-user">
            <div class="user-greeting">Hello, <?php echo $user_name; ?></div>
            <div class="notification-bell" id="notification-bell">
                <i class="fas fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                <span class="notification-count"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </div>
            <div class="user-role-badge"><?php echo ucfirst($user_role); ?></div>
        </div>
        
        <div class="dropdown">
            <button class="dropdown-toggle">
                <?php if ($profile_picture): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($profile_picture); ?>"
                         alt="Profile Picture"
                         class="header-profile-img"
                         onerror="this.src='assets/default-avatar.png'">
                <?php else: ?>
                    <img src="assets/default-avatar.png" alt="Default Avatar" class="header-profile-img">
                <?php endif; ?>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu">
                <a href="?page=profile" class="dropdown-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="?page=settings" class="dropdown-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Notifications Panel -->
<div class="notifications-panel">
    <div class="notifications-header">
        <h3>Notifications</h3>
        <button class="close-notifications"><i class="fas fa-times"></i></button>
    </div>
    <div class="notifications-list">
        <?php if (empty($notifications)): ?>
            <div class="notification-item">
                <div class="notification-content">
                    <p>No notifications yet.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif):
                $is_unread = $notif['status'] === 'unread';
                $time_ago_str = time_ago($notif['created_at']);
                $icon_class = 'fas fa-bell text-primary'; // Default
                $message_lower = strtolower($notif['message']);
                if (strpos($message_lower, 'approved') !== false) {
                    $icon_class = 'fas fa-calendar-check text-success';
                } elseif (strpos($message_lower, 'rejected') !== false) {
                    $icon_class = 'fas fa-times-circle text-danger';
                } elseif (strpos($message_lower, 'requires') !== false || strpos($message_lower, 'pending') !== false) {
                    $icon_class = 'fas fa-exclamation-circle text-warning';
                } elseif (strpos($message_lower, 'update') !== false || strpos($message_lower, 'policy') !== false) {
                    $icon_class = 'fas fa-info-circle text-info';
                }
            ?>
                <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>" onclick="markAsRead(<?php echo $notif['notification_id']; ?>)">
                    <div class="notification-icon">
                        <i class="<?php echo $icon_class; ?>"></i>
                    </div>
                    <div class="notification-content">
                        <p><?php echo htmlspecialchars($notif['message']); ?></p>
                        <span class="notification-time"><?php echo $time_ago_str; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="notifications-footer">
        <a href="?page=notifications">View All Notifications</a>
        <button class="btn btn-sm btn-danger clear-all-btn" id="clear-all-notifications" style="margin-left: 10px;">Clear All</button>
    </div>
</div>

<!-- System Alerts -->
<div class="alert-container">
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success">
            <span><?php echo htmlspecialchars($_GET['message']); ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <span><?php echo htmlspecialchars($_GET['error']); ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notification-bell');
    const panel = document.querySelector('.notifications-panel');
    const closeBtn = document.querySelector('.close-notifications');

    if (bell) {
        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            panel.style.display = 'none';
        });
    }

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!panel.contains(e.target) && !bell.contains(e.target)) {
            panel.style.display = 'none';
        }
    });

    // Mark as read function with AJAX
    window.markAsRead = function(id) {
        const item = document.querySelector(`[onclick="markAsRead(${id})"]`);
        if (!item) return;

        // Show loading (optional)
        const icon = item.querySelector('.notification-icon i');
        if (icon) icon.style.opacity = '0.5';

        fetch('pages/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_read&notification_id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove unread class
                item.classList.remove('unread');
                // Update count badge
                const countEl = document.querySelector('.notification-count');
                if (countEl) {
                    if (data.unread_count > 0) {
                        countEl.textContent = data.unread_count;
                    } else {
                        countEl.style.display = 'none';
                    }
                }
            } else {
                console.error('Error marking as read:', data.error);
                // Optionally show error message
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
        })
        .finally(() => {
            // Restore icon
            if (icon) icon.style.opacity = '1';
        });
    };

    // Handle profile image error
    const profileImgs = document.querySelectorAll('.header-profile-img');
    profileImgs.forEach(img => {
        img.addEventListener('error', function() {
            this.src = 'assets/default-avatar.png';
        });
    });
});
</script>

<style>
.header-profile-img {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 5px;
    border: 2px solid rgba(255,255,255,0.2);
}
</style>