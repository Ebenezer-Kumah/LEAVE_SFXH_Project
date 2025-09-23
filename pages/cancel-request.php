<?php
// pages/cancel-request.php - Handle cancel leave requests

// Include auth and database
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$database = new Database();
$db = $database->getConnection();

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$message = '';
$error = '';

// Perform cancel if valid request
if ($request_id) {
    // Fetch request details
    $query = "SELECT lr.*, u.department_id FROM Leave_Requests lr JOIN Users u ON lr.employee_id = u.user_id WHERE lr.request_id = :request_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':request_id', $request_id);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $error = "Request not found.";
    } else {
        // Check access: owner or manager of dept
        $can_cancel = false;
        $is_owner = ($request['employee_id'] == $user_id);
        if ($user_role == 'admin') {
            $can_cancel = true; // Admins can cancel any
        } elseif ($user_role == 'manager') {
            // Check if manager of employee's dept
            $mgr_query = "SELECT manager_id FROM Departments WHERE department_id = :dept_id";
            $mgr_stmt = $db->prepare($mgr_query);
            $mgr_stmt->bindParam(':dept_id', $request['department_id']);
            $mgr_stmt->execute();
            $dept_mgr = $mgr_stmt->fetchColumn();
            if ($dept_mgr == $user_id) {
                $can_cancel = true;
            }
        } elseif ($user_role == 'employee' && $is_owner) {
            $can_cancel = true;
        }

        if (!$can_cancel) {
            $error = "You do not have permission to cancel this request.";
        } elseif (!in_array($request['status'], ['pending', 'approved'])) {
            $error = "This request cannot be cancelled (status: " . ucfirst($request['status']) . ").";
        } else {
            try {
                // If approved, restore balance
                if ($request['status'] == 'approved') {
                    $days = (new DateTime($request['end_date']))->diff(new DateTime($request['start_date']))->days + 1;
                    $balance_query = "UPDATE Leave_Balances SET used_days = used_days - :days, remaining_days = remaining_days + :days WHERE employee_id = :emp_id AND leave_type = :leave_type AND year = YEAR(CURRENT_DATE())";
                    $balance_stmt = $db->prepare($balance_query);
                    $balance_stmt->bindParam(':days', $days);
                    $balance_stmt->bindParam(':emp_id', $request['employee_id']);
                    $balance_stmt->bindParam(':leave_type', $request['leave_type']);
                    $balance_stmt->execute();
                }

                // Update status
                $status = 'cancelled';
                $update_query = "UPDATE Leave_Requests SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE request_id = :request_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':status', $status);
                $update_stmt->bindParam(':request_id', $request_id);
                if ($update_stmt->execute()) {
                    $message = "Request cancelled successfully!";
                } else {
                    $error = "Failed to cancel request.";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
} else {
    $error = "No request ID provided.";
}

// Redirect based on role after action
$redirect_page = '';
if ($message || $error) {
    $redirect_page = $user_role == 'admin' ? 'leave-requests' : ($user_role == 'manager' ? 'requests' : 'history');
    $redirect_url = "?page=" . $redirect_page . ($message ? "&success=cancelled" : "&error=" . urlencode($error));
    echo "<script>window.location.href = '" . $redirect_url . "';</script>";
    echo "Redirecting... <a href='" . $redirect_url . "'>Click here if you are not redirected</a>";
    exit();
} else {
    // If no action, redirect to appropriate page
    $redirect_page = $user_role == 'admin' ? 'leave-requests' : ($user_role == 'manager' ? 'requests' : 'history');
    $redirect_url = "?page=" . $redirect_page;
    echo "<script>window.location.href = '" . $redirect_url . "';</script>";
    echo "Redirecting... <a href='" . $redirect_url . "'>Click here if you are not redirected</a>";
    exit();
}
?>