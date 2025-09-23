<?php
// pages/admin/calendar.php

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Get calendar view (month or week)
$view = isset($_GET['view']) ? $_GET['view'] : 'month';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');

// Get approved leaves for all users
$query = "SELECT lr.*, u.name as employee_name, u.email as employee_email,
          u.role as user_role, d.department_name,
          DATEDIFF(lr.end_date, lr.start_date) + 1 as duration_days
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          LEFT JOIN Departments d ON u.department_id = d.department_id
          WHERE lr.status = 'approved'
          ORDER BY lr.start_date ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$approved_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for statistics
$query = "SELECT user_id, name, role, department_id FROM Users
          WHERE is_active = TRUE
          ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current availability across all users
$query = "SELECT
            u.name as employee_name,
            u.role as user_role,
            d.department_name,
            SUM(CASE WHEN lr.status = 'approved'
                     AND CURRENT_DATE() BETWEEN lr.start_date AND lr.end_date
                THEN 1 ELSE 0 END) as on_leave_today
          FROM Users u
          LEFT JOIN Leave_Requests lr ON u.user_id = lr.employee_id
          LEFT JOIN Departments d ON u.department_id = d.department_id
          WHERE u.is_active = TRUE
          GROUP BY u.user_id
          ORDER BY u.name";
$stmt = $db->prepare($query);
$stmt->execute();
$availability = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate calendar data
$calendar_data = [];
foreach ($approved_leaves as $leave) {
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $end->modify('+1 day'); // Include end date in range

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    foreach ($period as $date) {
        $date_str = $date->format('Y-m-d');
        if (!isset($calendar_data[$date_str])) {
            $calendar_data[$date_str] = [];
        }
        $calendar_data[$date_str][] = $leave;
    }
}

// Get month statistics
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$query = "SELECT
            COUNT(DISTINCT lr.employee_id) as employees_on_leave,
            SUM(DATEDIFF(
                LEAST(lr.end_date, '$current_month_end'),
                GREATEST(lr.start_date, '$current_month_start')
            ) + 1) as total_leave_days
          FROM Leave_Requests lr
          JOIN Users u ON lr.employee_id = u.user_id
          WHERE lr.status = 'approved'
          AND lr.start_date <= '$current_month_end'
          AND lr.end_date >= '$current_month_start'";
$stmt = $db->prepare($query);
$stmt->execute();
$month_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get department statistics
$query = "SELECT
            d.department_name,
            COUNT(DISTINCT u.user_id) as total_users,
            COUNT(DISTINCT CASE WHEN lr.status = 'approved'
                               AND CURRENT_DATE() BETWEEN lr.start_date AND lr.end_date
                          THEN u.user_id END) as on_leave_today
          FROM Departments d
          LEFT JOIN Users u ON d.department_id = u.department_id AND u.is_active = TRUE
          LEFT JOIN Leave_Requests lr ON u.user_id = lr.employee_id
          GROUP BY d.department_id
          ORDER BY d.department_name";
$stmt = $db->prepare($query);
$stmt->execute();
$department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2>Organization Calendar - All Users</h2>
    <p>View leave schedule for all employees, managers, and administrators across the organization.</p>
</div>

<div class="dashboard-cards">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Total Users</h3>
            <div class="card-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo count($all_users); ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Active users
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Available Today</h3>
            <div class="card-icon">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
        <div class="card-body">
            <?php
            $available_today = count($availability) - array_sum(array_column($availability, 'on_leave_today'));
            echo $available_today;
            ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Currently working
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">On Leave Today</h3>
            <div class="card-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo array_sum(array_column($availability, 'on_leave_today')); ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Currently out
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">This Month</h3>
            <div class="card-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
        </div>
        <div class="card-body">
            <?php echo $month_stats['total_leave_days'] ?? 0; ?>
        </div>
        <div class="card-footer">
            <i class="fas fa-info-circle"></i> Leave days
        </div>
    </div>
</div>

<div class="content-row">
    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Organization Calendar - <?php echo date('F Y'); ?></h3>
                <div class="calendar-controls">
                    <select id="viewSelector" onchange="changeCalendarView()">
                        <option value="month" <?php echo $view == 'month' ? 'selected' : ''; ?>>Month View</option>
                        <option value="week" <?php echo $view == 'week' ? 'selected' : ''; ?>>Week View</option>
                    </select>
                    <div class="nav-buttons">
                        <button class="btn btn-secondary" onclick="navigateCalendar('prev')">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="btn btn-secondary" onclick="navigateCalendar('today')">
                            Today
                        </button>
                        <button class="btn btn-secondary" onclick="navigateCalendar('next')">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($view == 'month'): ?>
                    <div class="month-calendar">
                        <div class="calendar-header">
                            <?php
                            $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                            foreach ($days as $day): ?>
                                <div class="calendar-day-header"><?php echo $day; ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="calendar-grid">
                            <?php
                            $first_day = date('N', strtotime("$year-$month-01"));
                            $days_in_month = date('t', strtotime("$year-$month-01"));
                            $day_count = 1;

                            for ($i = 0; $i < 6; $i++): ?>
                                <div class="calendar-week">
                                    <?php for ($j = 0; $j < 7; $j++): ?>
                                        <?php if (($i === 0 && $j < $first_day - 1) || $day_count > $days_in_month): ?>
                                            <div class="calendar-day empty"></div>
                                        <?php else: ?>
                                            <?php
                                            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day_count);
                                            $day_leaves = $calendar_data[$current_date] ?? [];
                                            $is_today = $current_date == date('Y-m-d');
                                            $is_weekend = $j >= 5;
                                            ?>
                                            <div class="calendar-day <?php echo $is_today ? 'today' : ''; ?> <?php echo $is_weekend ? 'weekend' : ''; ?>">
                                                <div class="day-number"><?php echo $day_count; ?></div>
                                                <div class="day-leaves">
                                                    <?php foreach (array_slice($day_leaves, 0, 3) as $leave): ?>
                                                        <div class="leave-event" style="border-left: 3px solid #<?php echo substr(md5($leave['employee_name']), 0, 6); ?>" title="<?php echo htmlspecialchars($leave['employee_name']); ?> (<?php echo ucfirst($leave['user_role']); ?>) - <?php echo $leave['leave_type']; ?>">
                                                            <?php echo substr($leave['employee_name'], 0, 1) . substr($leave['employee_name'], strpos($leave['employee_name'], ' ') + 1, 1); ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (count($day_leaves) > 3): ?>
                                                        <div class="more-events">+<?php echo count($day_leaves) - 3; ?> more</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php $day_count++; ?>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="week-calendar">
                        <div class="week-header">
                            <div class="time-column">Time</div>
                            <?php
                            $week_days = [];
                            $current_week_start = date('Y-m-d', strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT)));
                            for ($i = 0; $i < 7; $i++) {
                                $week_days[] = date('Y-m-d', strtotime($current_week_start . ' +' . $i . ' days'));
                            }
                            foreach ($week_days as $day): ?>
                                <div class="day-header <?php echo $day == date('Y-m-d') ? 'today' : ''; ?>">
                                    <div class="day-name"><?php echo date('D', strtotime($day)); ?></div>
                                    <div class="day-date"><?php echo date('M j', strtotime($day)); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="week-grid">
                            <div class="time-slots">
                                <?php for ($hour = 8; $hour <= 17; $hour++): ?>
                                    <div class="time-slot"><?php echo sprintf('%02d:00', $hour); ?></div>
                                <?php endfor; ?>
                            </div>
                            <?php foreach ($week_days as $day): ?>
                                <div class="day-column <?php echo $day == date('Y-m-d') ? 'today' : ''; ?>">
                                    <?php
                                    $day_leaves = $calendar_data[$day] ?? [];
                                    $leave_events = [];
                                    foreach ($day_leaves as $leave) {
                                        $leave_events[] = [
                                            'employee' => $leave['employee_name'],
                                            'role' => $leave['user_role'],
                                            'type' => $leave['leave_type'],
                                            'color' => substr(md5($leave['employee_name']), 0, 6)
                                        ];
                                    }
                                    ?>
                                    <?php for ($hour = 8; $hour <= 17; $hour++): ?>
                                        <div class="time-cell"></div>
                                    <?php endfor; ?>
                                    <?php if (!empty($leave_events)): ?>
                                        <div class="all-day-events">
                                            <?php foreach ($leave_events as $event): ?>
                                                <div class="all-day-event" style="border-left: 3px solid #<?php echo $event['color']; ?>">
                                                    <?php echo $event['employee']; ?> (<?php echo ucfirst($event['role']); ?>) - <?php echo $event['type']; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Organization Availability</h3>
                <span class="badge"><?php echo date('M j, Y'); ?></span>
            </div>
            <div class="card-body">
                <div class="availability-list">
                    <?php foreach ($availability as $member): ?>
                        <div class="availability-item">
                            <div class="member-info">
                                <div class="member-avatar <?php echo $member['on_leave_today'] ? 'absent' : 'present'; ?>">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="member-details">
                                    <div class="member-name"><?php echo htmlspecialchars($member['employee_name']); ?></div>
                                    <div class="member-role"><?php echo ucfirst($member['user_role']); ?></div>
                                </div>
                            </div>
                            <div class="availability-status">
                                <span class="status <?php echo $member['on_leave_today'] ? 'absent' : 'present'; ?>">
                                    <?php echo $member['on_leave_today'] ? 'On Leave' : 'Available'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Department Status</h3>
                <span class="badge">Today</span>
            </div>
            <div class="card-body">
                <div class="department-list">
                    <?php foreach ($department_stats as $dept): ?>
                        <div class="department-item">
                            <div class="dept-info">
                                <div class="dept-name"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                                <div class="dept-count"><?php echo $dept['total_users']; ?> members</div>
                            </div>
                            <div class="dept-status">
                                <span class="dept-available">
                                    <?php echo $dept['total_users'] - $dept['on_leave_today']; ?> available
                                </span>
                                <?php if ($dept['on_leave_today'] > 0): ?>
                                    <span class="dept-on-leave">
                                        <?php echo $dept['on_leave_today']; ?> on leave
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Upcoming Leaves</h3>
                <span class="badge">Next 30 days</span>
            </div>
            <div class="card-body">
                <?php
                $upcoming_leaves = array_filter($approved_leaves, function($leave) {
                    return strtotime($leave['start_date']) >= strtotime('today') &&
                           strtotime($leave['start_date']) <= strtotime('+30 days');
                });
                ?>
                <?php if (!empty($upcoming_leaves)): ?>
                    <div class="upcoming-leaves-list">
                        <?php foreach (array_slice($upcoming_leaves, 0, 8) as $leave):
                            $start = new DateTime($leave['start_date']);
                            $end = new DateTime($leave['end_date']);
                            $days_until = $start->diff(new DateTime())->days;
                        ?>
                            <div class="upcoming-item">
                                <div class="upcoming-dates">
                                    <div class="upcoming-month"><?php echo $start->format('M'); ?></div>
                                    <div class="upcoming-day"><?php echo $start->format('d'); ?></div>
                                </div>
                                <div class="upcoming-details">
                                    <div class="upcoming-employee"><?php echo htmlspecialchars($leave['employee_name']); ?></div>
                                    <div class="upcoming-role"><?php echo ucfirst($leave['user_role']); ?></div>
                                    <div class="upcoming-dept"><?php echo $leave['department_name'] ? htmlspecialchars($leave['department_name']) : 'No Department'; ?></div>
                                    <div class="upcoming-type"><?php echo $leave['leave_type']; ?> (<?php echo $leave['duration_days']; ?> days)</div>
                                </div>
                                <div class="upcoming-timing">
                                    <?php if ($days_until === 0): ?>
                                        Starts today
                                    <?php elseif ($days_until === 1): ?>
                                        Starts tomorrow
                                    <?php else: ?>
                                        In <?php echo $days_until; ?> days
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <p>No upcoming leaves in the next 30 days</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="?page=leave-requests" class="quick-action-btn">
                        <i class="fas fa-clipboard-list"></i>
                        <span>View Requests</span>
                    </a>
                    <a href="?page=users" class="quick-action-btn">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                    <a href="?page=reports" class="quick-action-btn">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Reports</span>
                    </a>
                    <button class="quick-action-btn" onclick="exportCalendar()">
                        <i class="fas fa-download"></i>
                        <span>Export Calendar</span>
                    </button>
                    <button class="quick-action-btn" onclick="printCalendar()">
                        <i class="fas fa-print"></i>
                        <span>Print Schedule</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function changeCalendarView() {
    const view = document.getElementById('viewSelector').value;
    const url = new URL(window.location.href);
    url.searchParams.set('view', view);
    window.location.href = url.toString();
}

function navigateCalendar(direction) {
    const url = new URL(window.location.href);

    if (direction === 'today') {
        url.searchParams.delete('year');
        url.searchParams.delete('month');
        url.searchParams.delete('week');
    } else if (direction === 'prev' || direction === 'next') {
        // This would require more complex date handling
        // For simplicity, we'll just reload to current view
        alert('Navigation would be implemented here');
        return;
    }

    window.location.href = url.toString();
}

function exportCalendar() {
    alert('Calendar export functionality would be implemented here');
    // This would typically export to ICS or CSV format
}

function printCalendar() {
    window.print();
}

// Add event listeners for calendar interactions
document.addEventListener('DOMContentLoaded', function() {
    const calendarDays = document.querySelectorAll('.calendar-day:not(.empty)');
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            const date = this.querySelector('.day-number').textContent;
            alert('View details for ' + date);
            // This would show a modal with leave details for that day
        });
    });
});
</script>

<style>
.calendar-controls {
    display: flex;
    gap: 15px;
    align-items: center;
}

.nav-buttons {
    display: flex;
    gap: 5px;
}

.month-calendar {
    width: 100%;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background-color: #e0e0e0;
    border-radius: 8px 8px 0 0;
    overflow: hidden;
}

.calendar-day-header {
    padding: 10px;
    background-color: var(--secondary-color);
    color: white;
    text-align: center;
    font-weight: 600;
    font-size: 0.9rem;
}

.calendar-grid {
    display: flex;
    flex-direction: column;
    gap: 1px;
    background-color: #e0e0e0;
    border-radius: 0 0 8px 8px;
    overflow: hidden;
}

.calendar-week {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
}

.calendar-day {
    min-height: 100px;
    background-color: white;
    padding: 8px;
    position: relative;
}

.calendar-day.empty {
    background-color: #f8f9fa;
}

.calendar-day.today {
    background-color: #e8f4fd;
    border: 2px solid var(--primary-color);
}

.calendar-day.weekend {
    background-color: #f8f9fa;
}

.day-number {
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--dark-color);
}

.day-leaves {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.leave-event {
    padding: 2px 4px;
    background-color: #f8f9fa;
    border-radius: 3px;
    font-size: 0.7rem;
    cursor: pointer;
}

.leave-event:hover {
    background-color: #e9ecef;
}

.more-events {
    font-size: 0.7rem;
    color: #666;
    text-align: center;
    padding: 2px;
}

.week-calendar {
    width: 100%;
    overflow-x: auto;
}

.week-header {
    display: grid;
    grid-template-columns: 80px repeat(7, 1fr);
    gap: 1px;
    background-color: #e0e0e0;
}

.time-column {
    padding: 15px;
    background-color: var(--secondary-color);
    color: white;
    text-align: center;
    font-weight: 600;
}

.day-header {
    padding: 10px;
    background-color: var(--secondary-color);
    color: white;
    text-align: center;
}

.day-header.today {
    background-color: var(--primary-color);
}

.day-name {
    font-weight: 600;
    font-size: 0.9rem;
}

.day-date {
    font-size: 0.8rem;
}

.week-grid {
    display: grid;
    grid-template-columns: 80px repeat(7, 1fr);
    gap: 1px;
    background-color: #e0e0e0;
}

.time-slots {
    display: flex;
    flex-direction: column;
}

.time-slot {
    height: 60px;
    background-color: white;
    padding: 5px;
    border-right: 1px solid #e0e0e0;
    font-size: 0.8rem;
    color: #666;
}

.day-column {
    display: flex;
    flex-direction: column;
}

.time-cell {
    height: 60px;
    background-color: white;
    border-bottom: 1px solid #f0f0f0;
}

.day-column.today {
    background-color: #e8f4fd;
}

.all-day-events {
    grid-column: 1 / -1;
    padding: 10px;
    background-color: white;
}

.all-day-event {
    padding: 5px 8px;
    margin-bottom: 5px;
    background-color: #f8f9fa;
    border-radius: 4px;
    font-size: 0.8rem;
}

.availability-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.availability-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.member-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.member-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.member-avatar.present {
    background-color: #d4edda;
    color: #155724;
}

.member-avatar.absent {
    background-color: #f8d7da;
    color: #721c24;
}

.member-details {
    display: flex;
    flex-direction: column;
}

.member-name {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.member-role {
    font-size: 0.8rem;
    color: #666;
}

.availability-status .status {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status.present {
    background-color: #d4edda;
    color: #155724;
}

.status.absent {
    background-color: #f8d7da;
    color: #721c24;
}

.department-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.department-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.dept-info {
    display: flex;
    flex-direction: column;
}

.dept-name {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.dept-count {
    font-size: 0.8rem;
    color: #666;
}

.dept-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.dept-available {
    font-size: 0.8rem;
    color: #28a745;
    font-weight: 600;
}

.dept-on-leave {
    font-size: 0.8rem;
    color: #dc3545;
    font-weight: 600;
}

.upcoming-leaves-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.upcoming-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.upcoming-dates {
    text-align: center;
    padding: 8px;
    background-color: var(--primary-color);
    color: white;
    border-radius: 6px;
    min-width: 45px;
}

.upcoming-month {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.upcoming-day {
    font-size: 1.1rem;
    font-weight: 700;
}

.upcoming-details {
    flex: 1;
}

.upcoming-employee {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.upcoming-role {
    font-size: 0.8rem;
    color: #666;
    font-weight: 600;
}

.upcoming-dept {
    font-size: 0.8rem;
    color: #666;
}

.upcoming-type {
    font-size: 0.8rem;
    color: #666;
}

.upcoming-timing {
    font-size: 0.8rem;
    color: var(--primary-color);
    white-space: nowrap;
}

@media (max-width: 1024px) {
    .month-calendar {
        overflow-x: auto;
    }

    .calendar-week {
        min-width: 700px;
    }

    .week-calendar {
        min-width: 1000px;
    }
}

@media (max-width: 768px) {
    .calendar-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .availability-item {
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }

    .department-item {
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }

    .upcoming-item {
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }
}
</style>