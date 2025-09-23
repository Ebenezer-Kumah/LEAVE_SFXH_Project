<?php
// pages/notifications.php

// Ensure user is logged in
require_once __DIR__ . '/../includes/auth.php';
if (!is_logged_in()) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Handle AJAX mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    header('Content-Type: application/json');
    $notification_id = (int)$_POST['notification_id'];
    if ($notification_id > 0) {
        $query = "UPDATE Notifications SET status = 'read' WHERE notification_id = ? AND user_id = ? AND status = 'unread'";
        $stmt = $db->prepare($query);
        $stmt->execute([$notification_id, $user_id]);
        if ($stmt->rowCount() > 0) {
            // Get updated unread count
            $count_stmt = $db->prepare("SELECT COUNT(*) as unread FROM Notifications WHERE user_id = ? AND status = 'unread'");
            $count_stmt->execute([$user_id]);
            $unread_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['unread'];
            echo json_encode(['success' => true, 'unread_count' => $unread_count]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Notification not found or already read']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    }
    exit();
}

// Handle AJAX get unread count
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_unread_count') {
    header('Content-Type: application/json');
    $count_stmt = $db->prepare("SELECT COUNT(*) as unread FROM Notifications WHERE user_id = ? AND status = 'unread'");
    $count_stmt->execute([$user_id]);
    $unread_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    echo json_encode(['unread_count' => (int)$unread_count]);
    exit();
}

// Handle AJAX get notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_notifications') {
    header('Content-Type: application/json');
    $filter = $_POST['filter'] ?? 'all';
    $limit = (int)($_POST['limit'] ?? 10);
    $offset = (int)($_POST['offset'] ?? 0);
    $query = "SELECT * FROM Notifications WHERE user_id = :user_id";
    $params = [':user_id' => $user_id];
    if ($filter === 'unread') {
        $query .= " AND status = 'unread'";
    } elseif ($filter === 'read') {
        $query .= " AND status = 'read'";
    }
    $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['notifications' => $notifications]);
    exit();
}

// Handle AJAX search notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_notifications') {
    header('Content-Type: application/json');
    $filter = $_POST['filter'] ?? 'all';
    $limit = (int)($_POST['limit'] ?? 10);
    $offset = (int)($_POST['offset'] ?? 0);
    $search = $_POST['search'] ?? '';
    $date_from = $_POST['date_from'] ?? '';
    
    $query = "SELECT * FROM Notifications WHERE user_id = :user_id";
    $params = [':user_id' => $user_id];
    
    if ($filter === 'unread') {
        $query .= " AND status = 'unread'";
    } elseif ($filter === 'read') {
        $query .= " AND status = 'read'";
    }
    
    if ($search) {
        $query .= " AND message LIKE :search";
        $params[':search'] = "%$search%";
    }
    
    if ($date_from) {
        $query .= " AND DATE(created_at) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['notifications' => $notifications]);
    exit();
}

// Handle AJAX delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_notification') {
    header('Content-Type: application/json');
    $notification_id = (int)$_POST['notification_id'];
    if ($notification_id > 0) {
        $query = "DELETE FROM Notifications WHERE notification_id = ? AND user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$notification_id, $user_id]);
        if ($stmt->rowCount() > 0) {
            // Update unread count
            $count_stmt = $db->prepare("SELECT COUNT(*) as unread FROM Notifications WHERE user_id = ? AND status = 'unread'");
            $count_stmt->execute([$user_id]);
            $unread_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['unread'];
            echo json_encode(['success' => true, 'unread_count' => $unread_count]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Notification not found']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    }
    exit();
}

// Handle AJAX clear all notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all_notifications') {
    header('Content-Type: application/json');
    $query = "DELETE FROM Notifications WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true, 'deleted' => $stmt->rowCount(), 'unread_count' => 0]);
    exit();
}
    
    // Handle AJAX mark all as read
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
        header('Content-Type: application/json');
        $query = "UPDATE Notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $updated = $stmt->rowCount();
        if ($updated > 0) {
            echo json_encode(['success' => true, 'updated' => $updated, 'unread_count' => 0]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No unread notifications to mark']);
        }
        exit();
    }

// Handle GET mark as read
$mark_id = isset($_GET['mark_read']) ? (int)$_GET['mark_read'] : 0;
if ($mark_id > 0) {
    $query = "UPDATE Notifications SET status = 'read' WHERE notification_id = :id AND user_id = :user_id AND status = 'unread'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $mark_id);
    $stmt->bindParam(':user_id', $user_id);
    if ($stmt->execute()) {
        $message = "Notification marked as read.";
    } else {
        $error = "Failed to mark as read.";
    }
    // Removed redirect to avoid headers already sent error; page will reload naturally
}

// Handle filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, unread, read

// Build query
$query = "SELECT * FROM Notifications WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if ($filter === 'unread') {
    $query .= " AND status = 'unread'";
} elseif ($filter === 'read') {
    $query .= " AND status = 'read'";
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread
    FROM Notifications WHERE user_id = :user_id";
$stmt = $db->prepare($stats_query);
$stmt->execute([':user_id' => $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>Notifications</h2>
    <p>View and manage your notifications.</p>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-success">
        <span><?php echo $message; ?></span>
        <button class="alert-close"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <span><?php echo $error; ?></span>
        <button class="alert-close"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<div class="dashboard-cards">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Unread Notifications</h3>
            <div class="card-icon">
                <i class="fas fa-bell"></i>
            </div>
        </div>
        <div class="card-body" id="unread-count">
            <?php echo $stats['unread'] ?? 0; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> New alerts
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Total Notifications</h3>
            <div class="card-icon">
                <i class="fas fa-envelope"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $stats['total'] ?? 0; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> All time
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3>All Notifications</h3>
        <div class="header-actions">
            <a href="?page=notifications&filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
            <a href="?page=notifications&filter=unread" class="btn <?php echo $filter === 'unread' ? 'btn-primary' : 'btn-secondary'; ?>">Unread</a>
            <a href="?page=notifications&filter=read" class="btn <?php echo $filter === 'read' ? 'btn-primary' : 'btn-secondary'; ?>">Read</a>
            <?php if (($stats['unread'] ?? 0) > 0): ?>
                <button class="btn btn-sm mark-all-read-btn" id="mark-all-read">
                    <i class="fas fa-check-double"></i> Mark All Read
                </button>
            <?php endif; ?>
            <?php if (($stats['total'] ?? 0) > 0): ?>
                <button class="btn btn-sm clear-all-btn" id="clear-all-notifications">
                    <i class="fas fa-trash-alt"></i> Clear All
                </button>
            <?php endif; ?>
            <input type="text" id="search-input" placeholder="Search notifications..." class="search-input" style="margin-left: 10px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
            <input type="date" id="date-from" placeholder="From date" class="date-input" style="margin-left: 10px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h4>No Notifications</h4>
                <p><?php echo $filter === 'unread' ? 'No unread notifications.' : 'No notifications yet.'; ?></p>
            </div>
        <?php else: ?>
            <div class="notifications-full-list" id="notifications-full-list">
                <?php foreach ($notifications as $notif):
                    $is_unread = $notif['status'] === 'unread';
                    $time_ago_str = time_ago($notif['created_at']);
                    $icon_class = 'fas fa-bell text-primary';
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
                    <div class="notification-item-full <?php echo $is_unread ? 'unread' : 'read'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                        <div class="notification-icon-full">
                            <i class="<?php echo $icon_class; ?>"></i>
                        </div>
                        <div class="notification-content-full">
                            <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notification-meta">
                                <span class="notification-time" data-timestamp="<?php echo strtotime($notif['created_at']); ?>"><?php echo $time_ago_str; ?></span>
                                <div class="notification-actions">
                                    <?php if ($is_unread): ?>
                                        <button class="btn btn-sm mark-read-btn" data-id="<?php echo $notif['notification_id']; ?>">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm delete-btn" data-id="<?php echo $notif['notification_id']; ?>">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Redefine time_ago if not included
if (!function_exists('time_ago')) {
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
}
?>

<style>
.notifications-full-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.notification-item-full {
    display: flex;
    gap: 15px;
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    margin-bottom: 15px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.notification-item-full::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #007bff, #28a745, #dc3545);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.notification-item-full.unread {
    border-left: 4px solid #007bff;
    background: linear-gradient(135deg, #ffffff, #f0f8ff);
    box-shadow: 0 4px 15px rgba(0,123,255,0.1);
}

.notification-item-full.unread::before {
    opacity: 1;
}

.notification-item-full.read {
    opacity: 0.75;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.notification-item-full:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.notification-icon-full {
    flex-shrink: 0;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    position: relative;
    transition: all 0.3s ease;
}

.notification-icon-full::before {
    content: '';
    position: absolute;
    inset: -2px;
    border-radius: 50%;
    background: linear-gradient(135deg, #007bff, #28a745, #dc3545);
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.notification-item-full:hover .notification-icon-full::before {
    opacity: 0.3;
}

.notification-item-full.unread .notification-icon-full {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}

.notification-item-full.read .notification-icon-full {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    opacity: 0.7;
}

.notification-icon-full i {
    transition: transform 0.3s ease;
}

.notification-item-full:hover .notification-icon-full i {
    transform: scale(1.1);
}

.notification-content-full {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification-message {
    margin-bottom: 0;
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.4;
    color: #2c3e50;
    transition: color 0.3s ease;
}

.notification-item-full.unread .notification-message {
    color: #1a202c;
    font-weight: 700;
}

.notification-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: auto;
}

.notification-time {
    background: rgba(0,0,0,0.05);
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.notification-item-full:hover .notification-time {
    background: rgba(0,123,255,0.1);
    color: #007bff;
}

.notification-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    padding: 8px 0;
    border-top: 1px solid rgba(0,0,0,0.05);
    margin-top: 10px;
}

.notification-actions .btn {
    white-space: nowrap;
    position: relative;
}

/* Add subtle animation to action buttons */
.notification-actions .btn {
    animation: slideInUp 0.3s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.85rem;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-sm::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-sm:hover::before {
    left: 100%;
}

.btn-sm:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-sm:active {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.btn-sm i {
    font-size: 0.9rem;
    transition: transform 0.2s ease;
}

.btn-sm:hover i {
    transform: scale(1.1);
}

/* Mark as Read Button */
.mark-read-btn {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border: 2px solid transparent;
}

.mark-read-btn:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
    border-color: rgba(255,255,255,0.3);
}

/* Delete Button */
.delete-btn {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    border: 2px solid transparent;
}

.delete-btn:hover {
    background: linear-gradient(135deg, #c82333, #a02622);
    border-color: rgba(255,255,255,0.3);
}

/* Mark All Read Button */
.mark-all-read-btn {
    background: linear-gradient(135deg, #28a745, #218838);
    color: white;
    border: 2px solid transparent;
    font-weight: 700;
    padding: 10px 20px;
    font-size: 0.9rem;
}

.mark-all-read-btn:hover {
    background: linear-gradient(135deg, #218838, #1e7e34);
    border-color: rgba(255,255,255,0.3);
}

/* Clear All Button */
.clear-all-btn {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    border: 2px solid transparent;
    font-weight: 700;
    padding: 10px 20px;
    font-size: 0.9rem;
}

.clear-all-btn:hover {
    background: linear-gradient(135deg, #c82333, #a02622);
    border-color: rgba(255,255,255,0.3);
}

/* Loading states for buttons */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn:disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* Enhanced notification item styling */
.notification-item-full {
    transition: all 0.3s ease;
}

.notification-item-full:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Better spacing for mobile */
@media (max-width: 768px) {
    .notification-actions {
        justify-content: flex-start;
        gap: 8px;
        flex-direction: column;
        align-items: flex-start;
    }

    .notification-actions .btn {
        font-size: 0.8rem;
        padding: 6px 12px;
        width: 100%;
        justify-content: center;
    }

    .header-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .header-actions .btn {
        width: 100%;
        justify-content: center;
        padding: 12px 20px;
    }
}

/* Loading animation for buttons */
.btn-sm.loading {
    pointer-events: none;
    position: relative;
    color: transparent !important;
}

.btn-sm.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Success animation */
.btn-sm.success {
    background: linear-gradient(135deg, #28a745, #20c997) !important;
    animation: successPulse 0.6s ease-out;
}

@keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
    padding: 15px 0;
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 20px;
}

.header-actions .btn {
    position: relative;
    overflow: hidden;
}

.header-actions .btn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255,255,255,0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s, height 0.3s;
}

.header-actions .btn:active::after {
    width: 300px;
    height: 300px;
}

.btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    background-color: white;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.btn-secondary {
    background-color: #f8f9fa;
    color: var(--dark-color);
}

@media (max-width: 768px) {
    .notification-item-full {
        flex-direction: column;
        text-align: left;
    }
    
    .notification-meta {
        flex-direction: column;
        gap: 5px;
        align-items: flex-start;
    }
    
    .header-actions {
        flex-direction: column;
    }
}
</style>
