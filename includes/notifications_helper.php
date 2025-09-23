<?php
// includes/notifications_helper.php
// Helper functions for managing notifications in ELMS

require_once __DIR__ . '/database.php';

function insertNotification($db, $user_id, $message, $type = 'system') {
    if (empty($message) || $user_id <= 0) {
        return false;
    }
    
    $query = "INSERT INTO Notifications (user_id, message, type, status) 
              VALUES (:user_id, :message, :type, 'unread')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':type', $type);
    
    return $stmt->execute();
}

function getUnreadCount($db, $user_id) {
    $query = "SELECT COUNT(*) as count FROM Notifications 
              WHERE user_id = :user_id AND status = 'unread'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$result['count'];
}

function checkAndInsertReminders($db, $user_id) {
    // Check for approved leaves needing reminders (2-3 days before start)
    $query = "SELECT lr.*, DATEDIFF(lr.start_date, CURRENT_DATE()) as days_until 
              FROM Leave_Requests lr 
              WHERE lr.employee_id = :user_id 
              AND lr.status = 'approved' 
              AND lr.start_date > CURRENT_DATE() 
              AND DATEDIFF(lr.start_date, CURRENT_DATE()) BETWEEN 1 AND 3";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $upcoming_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($upcoming_leaves as $leave) {
        $days = $leave['days_until'];
        $message = "Reminder: Your {$leave['leave_type']} leave starts in {$days} day" . ($days > 1 ? 's' : '') . ".";
        
        // Avoid duplicate reminders (check last 24 hours)
        $check_query = "SELECT COUNT(*) as count FROM Notifications 
                        WHERE user_id = :user_id 
                        AND message LIKE :message_pattern 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $check_stmt = $db->prepare($check_query);
        $pattern = "%{$message}%";
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->bindParam(':message_pattern', $pattern);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            insertNotification($db, $user_id, $message);
        }
    }
    
    // Day before leave starts
    $day_before_query = "SELECT lr.* FROM Leave_Requests lr 
                         WHERE lr.employee_id = :user_id 
                         AND lr.status = 'approved' 
                         AND lr.start_date = DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)";
    $day_before_stmt = $db->prepare($day_before_query);
    $day_before_stmt->bindParam(':user_id', $user_id);
    $day_before_stmt->execute();
    $day_before_leaves = $day_before_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($day_before_leaves as $leave) {
        $message = "Tomorrow is the start of your {$leave['leave_type']} leave. Prepare accordingly.";
        insertNotification($db, $user_id, $message);
    }
    
    // Leave ends today
    $end_today_query = "SELECT lr.* FROM Leave_Requests lr 
                        WHERE lr.employee_id = :user_id 
                        AND lr.status = 'approved' 
                        AND lr.end_date = CURRENT_DATE()";
    $end_stmt = $db->prepare($end_today_query);
    $end_stmt->bindParam(':user_id', $user_id);
    $end_stmt->execute();
    $ending_leaves = $end_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ending_leaves as $leave) {
        $message = "Your {$leave['leave_type']} leave ends today. Resume work tomorrow.";
        insertNotification($db, $user_id, $message);
    }
}
?>