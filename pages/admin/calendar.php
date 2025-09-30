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
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$week = isset($_GET['week']) ? (int)$_GET['week'] : (int)date('W');

// Ensure valid date ranges
$year = max(2000, min(2030, $year));
$month = max(1, min(12, $month));
$week = max(1, min(53, $week));

// Get approved leaves for all users
try {
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
} catch (PDOException $e) {
    error_log("Error fetching approved leaves: " . $e->getMessage());
    $approved_leaves = [];
}

// Get all users for statistics
try {
    $query = "SELECT user_id, name, role, department_id FROM Users
              WHERE is_active = TRUE
              ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $all_users = [];
}

// Get current availability across all users
try {
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
} catch (PDOException $e) {
    error_log("Error fetching availability: " . $e->getMessage());
    $availability = [];
}

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
$current_month_start = sprintf('%04d-%02d-01', $year, $month);
$current_month_end = sprintf('%04d-%02d-%02d', $year, $month, (int)date('t', strtotime($current_month_start)));
try {
    $query = "SELECT
                COUNT(DISTINCT lr.employee_id) as employees_on_leave,
                SUM(DATEDIFF(
                    LEAST(lr.end_date, :current_month_end),
                    GREATEST(lr.start_date, :current_month_start)
                ) + 1) as total_leave_days
              FROM Leave_Requests lr
              JOIN Users u ON lr.employee_id = u.user_id
              WHERE lr.status = 'approved'
              AND lr.start_date <= :current_month_end
              AND lr.end_date >= :current_month_start";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':current_month_start', $current_month_start);
    $stmt->bindParam(':current_month_end', $current_month_end);
    $stmt->execute();
    $month_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching month statistics: " . $e->getMessage());
    $month_stats = ['employees_on_leave' => 0, 'total_leave_days' => 0];
}

// Get department statistics
try {
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
} catch (PDOException $e) {
    error_log("Error fetching department statistics: " . $e->getMessage());
    $department_stats = [];
}
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
            <?php echo is_array($all_users) ? count($all_users) : 0; ?>
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
            $available_today = (is_array($availability) ? count($availability) : 0) - (is_array($availability) ? array_sum(array_column($availability, 'on_leave_today')) : 0);
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
            <?php echo is_array($availability) ? array_sum(array_column($availability, 'on_leave_today')) : 0; ?>
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
            <?php echo is_array($month_stats) && isset($month_stats['total_leave_days']) ? $month_stats['total_leave_days'] : 0; ?>
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
                <h3>Organization Calendar -
                    <?php
                    if ($view === 'month') {
                        echo date('F Y', strtotime("$year-$month-01"));
                    } else {
                        $week_start = date('M j', strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT)));
                        $week_end = date('M j, Y', strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT) . ' +6 days'));
                        echo "Week of $week_start ($week_end)";
                    }
                    ?>
                </h3>
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
                    <div class="selection-controls">
                        <button id="range-selection-btn" class="btn btn-outline" onclick="toggleRangeSelection()">
                            <i class="fas fa-square"></i> Select Range
                        </button>
                    </div>
                </div>
                <div id="range-info" class="range-info" style="display: none;"></div>
            </div>
            <div class="card-body">
                <?php if ($view == 'month'): ?>
                    <div class="month-calendar">
                        <div class="calendar-header">
                            <?php
                            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
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
                                            <div class="calendar-day <?php echo $is_today ? 'today' : ''; ?> <?php echo $is_weekend ? 'weekend' : ''; ?>" data-date="<?php echo $current_date; ?>">
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
                                    <div class="day-name"><?php echo date('l', strtotime($day)); ?></div>
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
    const view = url.searchParams.get('view') || 'month';
    const currentYear = parseInt(url.searchParams.get('year')) || new Date().getFullYear();
    const currentMonth = parseInt(url.searchParams.get('month')) || new Date().getMonth() + 1;
    const currentWeek = parseInt(url.searchParams.get('week')) || getWeekNumber(new Date());

    // Add loading state to buttons
    const buttons = document.querySelectorAll('.nav-buttons button');
    buttons.forEach(button => button.disabled = true);

    if (direction === 'today') {
        url.searchParams.delete('year');
        url.searchParams.delete('month');
        url.searchParams.delete('week');
    } else if (direction === 'prev') {
        if (view === 'month') {
            if (currentMonth === 1) {
                url.searchParams.set('year', (currentYear - 1).toString());
                url.searchParams.set('month', '12');
            } else {
                url.searchParams.set('month', (currentMonth - 1).toString());
            }
        } else if (view === 'week') {
            const prevWeek = getPreviousWeek(currentYear, currentWeek);
            url.searchParams.set('year', prevWeek.year.toString());
            url.searchParams.set('week', prevWeek.week.toString());
        }
    } else if (direction === 'next') {
        if (view === 'month') {
            if (currentMonth === 12) {
                url.searchParams.set('year', (currentYear + 1).toString());
                url.searchParams.set('month', '1');
            } else {
                url.searchParams.set('month', (currentMonth + 1).toString());
            }
        } else if (view === 'week') {
            const nextWeek = getNextWeek(currentYear, currentWeek);
            url.searchParams.set('year', nextWeek.year.toString());
            url.searchParams.set('week', nextWeek.week.toString());
        }
    }

    // Navigate to the new URL
    window.location.href = url.toString();
}

function getWeekNumber(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
    return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
}

function getPreviousWeek(year, week) {
    if (week === 1) {
        return { year: year - 1, week: getWeeksInYear(year - 1) };
    }
    return { year: year, week: week - 1 };
}

function getNextWeek(year, week) {
    const weeksInYear = getWeeksInYear(year);
    if (week === weeksInYear) {
        return { year: year + 1, week: 1 };
    }
    return { year: year, week: week + 1 };
}

function getWeeksInYear(year) {
    const d = new Date(year, 11, 31);
    const week = getWeekNumber(d);
    return week === 1 ? 52 : week;
}

function exportCalendar() {
    showExportOptions();
}

function showExportOptions() {
    const modal = document.getElementById('export-modal') || createExportModal();
    const modalContent = modal.querySelector('.modal-content');

    const url = new URL(window.location.href);
    const view = url.searchParams.get('view') || 'month';
    const year = url.searchParams.get('year') || new Date().getFullYear();
    const month = url.searchParams.get('month') || (new Date().getMonth() + 1);
    const week = url.searchParams.get('week') || getWeekNumber(new Date());

    const modalHTML = `
        <div class="modal-header">
            <h3>Export Calendar</h3>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="export-options">
                <div class="export-section">
                    <h4>Export Format</h4>
                    <div class="format-options">
                        <label class="format-option">
                            <input type="radio" name="format" value="pdf" checked>
                            <div class="format-info">
                                <div class="format-name">PDF Document</div>
                                <div class="format-desc">Professional document with calendar layout</div>
                            </div>
                        </label>
                        <label class="format-option">
                            <input type="radio" name="format" value="csv">
                            <div class="format-info">
                                <div class="format-name">CSV Spreadsheet</div>
                                <div class="format-desc">Raw data for analysis in Excel or similar</div>
                            </div>
                        </label>
                        <label class="format-option">
                            <input type="radio" name="format" value="json">
                            <div class="format-info">
                                <div class="format-name">JSON Data</div>
                                <div class="format-desc">Structured data for developers</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="export-section">
                    <h4>Date Range</h4>
                    <div class="date-range-options">
                        <label class="range-option">
                            <input type="radio" name="range" value="current" checked>
                            <div class="range-info">
                                <div class="range-name">Current View</div>
                                <div class="range-desc">${view === 'month' ? 'Current month' : 'Current week'}</div>
                            </div>
                        </label>
                        <label class="range-option">
                            <input type="radio" name="range" value="custom">
                            <div class="range-info">
                                <div class="range-name">Custom Range</div>
                                <div class="range-desc">Select specific start and end dates</div>
                            </div>
                        </label>
                    </div>

                    <div id="custom-range-inputs" style="display: none;">
                        <div class="date-inputs">
                            <div class="input-group">
                                <label>Start Date:</label>
                                <input type="date" id="start-date" value="${year}-${month.toString().padStart(2, '0')}-01">
                            </div>
                            <div class="input-group">
                                <label>End Date:</label>
                                <input type="date" id="end-date" value="${view === 'month' ? getMonthEndDate(year, month) : getWeekEndDate(year, week)}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="export-section">
                    <h4>Include Details</h4>
                    <div class="detail-options">
                        <label class="checkbox-option">
                            <input type="checkbox" id="include-employees" checked>
                            <span>Employee information</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" id="include-departments" checked>
                            <span>Department details</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" id="include-leave-types" checked>
                            <span>Leave types and duration</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeExportModal()">Cancel</button>
                <button class="btn btn-primary" onclick="performExport()">Export Calendar</button>
            </div>
        </div>
    `;

    modalContent.innerHTML = modalHTML;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    // Add event listeners for range selection
    const rangeInputs = modal.querySelectorAll('input[name="range"]');
    rangeInputs.forEach(input => {
        input.addEventListener('change', function() {
            const customInputs = modal.querySelector('#custom-range-inputs');
            if (this.value === 'custom') {
                customInputs.style.display = 'block';
            } else {
                customInputs.style.display = 'none';
            }
        });
    });
}

function createExportModal() {
    const modal = document.createElement('div');
    modal.id = 'export-modal';
    modal.className = 'date-modal export-modal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeExportModal()"></div>
        <div class="modal-content"></div>
    `;
    document.body.appendChild(modal);
    return modal;
}

function closeExportModal() {
    const modal = document.getElementById('export-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function performExport() {
    const modal = document.getElementById('export-modal');
    if (!modal) return;

    // Get selected options
    const format = modal.querySelector('input[name="format"]:checked').value;
    const rangeType = modal.querySelector('input[name="range"]:checked').value;
    const includeEmployees = modal.querySelector('#include-employees').checked;
    const includeDepartments = modal.querySelector('#include-departments').checked;
    const includeLeaveTypes = modal.querySelector('#include-leave-types').checked;

    let exportUrl = `export-calendar.php?format=${format}`;
    exportUrl += `&employees=${includeEmployees}&departments=${includeDepartments}&leave_types=${includeLeaveTypes}`;

    if (rangeType === 'current') {
        const url = new URL(window.location.href);
        const view = url.searchParams.get('view') || 'month';
        const year = url.searchParams.get('year') || new Date().getFullYear();
        const month = url.searchParams.get('month') || (new Date().getMonth() + 1);
        const week = url.searchParams.get('week') || getWeekNumber(new Date());

        exportUrl += `&view=${view}&year=${year}`;
        if (view === 'month') {
            exportUrl += `&month=${month}`;
        } else {
            exportUrl += `&week=${week}`;
        }

        const fileName = `calendar-${view}-${year}${view === 'month' ? '-' + month : '-week' + week}.${format}`;
        downloadFile(exportUrl, fileName);
    } else {
        const startDate = modal.querySelector('#start-date').value;
        const endDate = modal.querySelector('#end-date').value;

        if (!startDate || !endDate) {
            alert('Please select both start and end dates.');
            return;
        }

        exportUrl += `&start_date=${startDate}&end_date=${endDate}`;
        const fileName = `calendar-${startDate}-to-${endDate}.${format}`;
        downloadFile(exportUrl, fileName);
    }

    closeExportModal();
}

function downloadFile(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function getMonthEndDate(year, month) {
    return new Date(year, month, 0).toISOString().split('T')[0];
}

function getWeekEndDate(year, week) {
    const weekStart = new Date(year, 0, 1 + (week - 1) * 7);
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekStart.getDate() + 6);
    return weekEnd.toISOString().split('T')[0];
}

function printCalendar() {
    // Add print-specific CSS
    const printCSS = `
        <style>
            @media print {
                body * { visibility: hidden; }
                .content-card, .content-card * { visibility: visible; }
                .content-card { position: absolute; left: 0; top: 0; width: 100%; }
                .calendar-controls, .quick-actions, .nav-buttons { display: none !important; }
                .content-row { display: block !important; }
                .content-col { width: 100% !important; display: block !important; }
                .card-header { background-color: #f8f9fa !important; border-bottom: 2px solid #dee2e6 !important; }
                .availability-list, .department-list, .upcoming-leaves-list { break-inside: avoid; }
            }
        </style>
    `;

    // Add the print CSS to head
    const head = document.head || document.getElementsByTagName('head')[0];
    const existingPrintCSS = document.getElementById('print-calendar-styles');
    if (!existingPrintCSS) {
        const style = document.createElement('div');
        style.id = 'print-calendar-styles';
        style.innerHTML = printCSS;
        head.appendChild(style);
    }

    // Open print dialog
    window.print();

    // Remove the temporary styles after printing
    setTimeout(() => {
        const tempStyles = document.getElementById('print-calendar-styles');
        if (tempStyles) {
            tempStyles.remove();
        }
    }, 1000);
}

// Add event listeners for calendar interactions
document.addEventListener('DOMContentLoaded', function() {
    const calendarDays = document.querySelectorAll('.calendar-day:not(.empty)');
    let selectedDate = null;

    calendarDays.forEach(day => {
        // Add click handler for date selection
        day.addEventListener('click', function(e) {
            const date = this.querySelector('.day-number').textContent;
            const fullDate = this.dataset.date;

            if (isRangeSelectionMode) {
                selectDateRange(fullDate);
            } else {
                showDateDetails(fullDate, date);
            }
        });

        // Add hover effects for better UX
        day.addEventListener('mouseenter', function() {
            if (!this.classList.contains('selected') && !this.classList.contains('range-start') && !this.classList.contains('range-end')) {
                this.style.backgroundColor = 'rgba(77, 122, 93, 0.1)';
                this.style.cursor = 'pointer';
            }
        });

        day.addEventListener('mouseleave', function() {
            if (!this.classList.contains('selected') && !this.classList.contains('range-start') && !this.classList.contains('range-end')) {
                this.style.backgroundColor = '';
            }
        });

        // Add tooltips to leave events
        const leaveEvents = day.querySelectorAll('.leave-event');
        leaveEvents.forEach(event => {
            event.addEventListener('mouseenter', function(e) {
                showTooltip(e, this);
            });

            event.addEventListener('mouseleave', function() {
                hideTooltip();
            });
        });
    });

    // Keyboard navigation support
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && selectedDate) {
            clearDateSelection();
        }
    });
});

function showDateDetails(dateStr, dayNumber) {
    // Clear previous selection
    clearDateSelection();

    // Highlight selected date
    const selectedDay = document.querySelector(`[data-date="${dateStr}"]`);
    if (selectedDay) {
        selectedDay.classList.add('selected');
        selectedDate = selectedDay;
    }

    // Get leave data for this date
    <?php
    $selected_date_leaves = $calendar_data[$current_date] ?? [];
    ?>

    // Create or update modal
    showDateModal(dateStr, dayNumber);
}

function showDateModal(dateStr, dayNumber) {
    const modal = document.getElementById('date-details-modal') || createDateModal();
    const modalContent = modal.querySelector('.modal-content');

    // Format date for display
    const date = new Date(dateStr);
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const formattedDate = date.toLocaleDateString('en-US', options);

    // Get leave data for this date from PHP
    const leaveData = <?php echo json_encode($calendar_data); ?>[dateStr] || [];

    let modalHTML = `
        <div class="modal-header">
            <h3>Date Details - ${formattedDate}</h3>
            <button class="modal-close" onclick="closeDateModal()">&times;</button>
        </div>
        <div class="modal-body">
    `;

    if (leaveData.length > 0) {
        modalHTML += `
            <div class="leave-summary">
                <h4>Leave Summary</h4>
                <p><strong>${leaveData.length}</strong> employee(s) on leave</p>
            </div>
            <div class="leave-details">
                <h4>Leave Details</h4>
                <div class="leave-list">
        `;

        leaveData.forEach(leave => {
            const startDate = new Date(leave.start_date);
            const endDate = new Date(leave.end_date);
            const duration = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;

            modalHTML += `
                <div class="leave-item">
                    <div class="leave-employee">
                        <div class="employee-avatar">${leave.employee_name.split(' ').map(n => n[0]).join('')}</div>
                        <div class="employee-info">
                            <div class="employee-name">${leave.employee_name}</div>
                            <div class="employee-role">${leave.user_role}</div>
                        </div>
                    </div>
                    <div class="leave-info">
                        <div class="leave-type">${leave.leave_type}</div>
                        <div class="leave-duration">${duration} day(s)</div>
                        <div class="leave-dates">
                            ${startDate.toLocaleDateString()} - ${endDate.toLocaleDateString()}
                        </div>
                    </div>
                </div>
            `;
        });

        modalHTML += `
                </div>
            </div>
        `;
    } else {
        modalHTML += `
            <div class="no-leave">
                <i class="fas fa-calendar-check"></i>
                <p>No employees on leave on this date.</p>
                <p>All team members are available.</p>
            </div>
        `;
    }

    modalHTML += `
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeDateModal()">Close</button>
                <button class="btn btn-primary" onclick="exportDateSchedule('${dateStr}')">Export Schedule</button>
            </div>
        </div>
    `;

    modalContent.innerHTML = modalHTML;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function createDateModal() {
    const modal = document.createElement('div');
    modal.id = 'date-details-modal';
    modal.className = 'date-modal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeDateModal()"></div>
        <div class="modal-content"></div>
    `;
    document.body.appendChild(modal);

    // Close modal on escape key
    modal.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDateModal();
        }
    });

    return modal;
}

function closeDateModal() {
    const modal = document.getElementById('date-details-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = ''; // Restore scrolling
        clearDateSelection();
    }
}

function clearDateSelection() {
    if (selectedDate) {
        selectedDate.classList.remove('selected');
        selectedDate.style.backgroundColor = '';
        selectedDate = null;
    }
}

function exportDateSchedule(dateStr) {
    // Create export URL for specific date
    const exportUrl = `export-calendar.php?date=${dateStr}&format=pdf`;

    // Create a temporary link and trigger download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = `schedule-${dateStr}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Date Range Selection Functionality
let isRangeSelectionMode = false;
let rangeStartDate = null;
let rangeEndDate = null;
let selectedRange = [];

function toggleRangeSelection() {
    isRangeSelectionMode = !isRangeSelectionMode;
    const rangeBtn = document.getElementById('range-selection-btn');

    if (isRangeSelectionMode) {
        rangeBtn.classList.add('active');
        rangeBtn.innerHTML = '<i class="fas fa-check-square"></i> Range Mode';
        showRangeInstructions();
        clearDateSelection();
    } else {
        rangeBtn.classList.remove('active');
        rangeBtn.innerHTML = '<i class="fas fa-square"></i> Select Range';
        hideRangeInstructions();
        clearRangeSelection();
    }
}

function showRangeInstructions() {
    const instructions = document.getElementById('range-instructions') || createRangeInstructions();
    instructions.style.display = 'block';
}

function hideRangeInstructions() {
    const instructions = document.getElementById('range-instructions');
    if (instructions) {
        instructions.style.display = 'none';
    }
}

function createRangeInstructions() {
    const instructions = document.createElement('div');
    instructions.id = 'range-instructions';
    instructions.className = 'range-instructions';
    instructions.innerHTML = `
        <div class="instructions-content">
            <i class="fas fa-info-circle"></i>
            <span>Click on two dates to select a range. Hold Shift and click to extend selection.</span>
            <button class="btn-clear" onclick="clearRangeSelection()">Clear</button>
        </div>
    `;
    document.querySelector('.calendar-controls').appendChild(instructions);
    return instructions;
}

function selectDateRange(dateStr) {
    if (!isRangeSelectionMode) return;

    const dayElement = document.querySelector(`[data-date="${dateStr}"]`);
    if (!dayElement) return;

    if (!rangeStartDate) {
        // First date selection
        rangeStartDate = dateStr;
        selectedRange = [dateStr];
        dayElement.classList.add('range-start');
        updateRangeDisplay();
    } else if (!rangeEndDate) {
        // Second date selection - determine if this is start or end
        const startDate = new Date(rangeStartDate);
        const currentDate = new Date(dateStr);

        if (currentDate < startDate) {
            // Selected date is before start - make it the new start
            clearRangeSelection();
            rangeStartDate = dateStr;
            selectedRange = [dateStr];
            dayElement.classList.add('range-start');
        } else {
            // Selected date is after start - make it the end
            rangeEndDate = dateStr;
            selectedRange = getDatesInRange(rangeStartDate, rangeEndDate);
            highlightRange();
        }
        updateRangeDisplay();
    } else {
        // Both dates already selected - start new range
        clearRangeSelection();
        rangeStartDate = dateStr;
        selectedRange = [dateStr];
        dayElement.classList.add('range-start');
        rangeEndDate = null;
        updateRangeDisplay();
    }
}

function getDatesInRange(startDate, endDate) {
    const dates = [];
    const start = new Date(startDate);
    const end = new Date(endDate);

    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        dates.push(date.toISOString().split('T')[0]);
    }
    return dates;
}

function highlightRange() {
    document.querySelectorAll('.calendar-day.range-highlight, .calendar-day.range-start, .calendar-day.range-end').forEach(day => {
        day.classList.remove('range-highlight', 'range-start', 'range-end');
    });

    selectedRange.forEach((dateStr, index) => {
        const dayElement = document.querySelector(`[data-date="${dateStr}"]`);
        if (dayElement) {
            if (index === 0) {
                dayElement.classList.add('range-start');
            } else if (index === selectedRange.length - 1) {
                dayElement.classList.add('range-end');
            } else {
                dayElement.classList.add('range-highlight');
            }
        }
    });
}

function clearRangeSelection() {
    rangeStartDate = null;
    rangeEndDate = null;
    selectedRange = [];

    document.querySelectorAll('.calendar-day.range-highlight, .calendar-day.range-start, .calendar-day.range-end').forEach(day => {
        day.classList.remove('range-highlight', 'range-start', 'range-end');
    });

    updateRangeDisplay();
}

function updateRangeDisplay() {
    const rangeInfo = document.getElementById('range-info');
    if (!rangeInfo) return;

    if (selectedRange.length > 0) {
        const daysCount = selectedRange.length;
        rangeInfo.innerHTML = `
            <div class="range-summary">
                <strong>${daysCount}</strong> day${daysCount > 1 ? 's' : ''} selected
                <button class="btn-small" onclick="exportRangeSchedule()">Export Range</button>
                <button class="btn-small" onclick="showRangeDetails()">View Details</button>
            </div>
        `;
        rangeInfo.style.display = 'block';
    } else {
        rangeInfo.style.display = 'none';
    }
}

function exportRangeSchedule() {
    if (selectedRange.length === 0) return;

    const startDate = selectedRange[0];
    const endDate = selectedRange[selectedRange.length - 1];
    const exportUrl = `export-calendar.php?start_date=${startDate}&end_date=${endDate}&format=pdf`;

    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = `schedule-${startDate}-to-${endDate}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function showRangeDetails() {
    if (selectedRange.length === 0) return;

    // Get leave data for selected range
    const rangeLeaveData = {};
    selectedRange.forEach(dateStr => {
        if (<?php echo json_encode($calendar_data); ?>[dateStr]) {
            rangeLeaveData[dateStr] = <?php echo json_encode($calendar_data); ?>[dateStr];
        }
    });

    showRangeModal(selectedRange, rangeLeaveData);
}

function showRangeModal(dateRange, leaveData) {
    const modal = document.getElementById('range-details-modal') || createRangeModal();
    const modalContent = modal.querySelector('.modal-content');

    const startDate = new Date(dateRange[0]);
    const endDate = new Date(dateRange[dateRange.length - 1]);
    const totalDays = dateRange.length;

    // Calculate total unique employees on leave in range
    const uniqueEmployees = new Set();
    Object.values(leaveData).forEach(dayLeaves => {
        dayLeaves.forEach(leave => uniqueEmployees.add(leave.employee_name));
    });

    let modalHTML = `
        <div class="modal-header">
            <h3>Date Range Details</h3>
            <button class="modal-close" onclick="closeRangeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="range-summary-large">
                <div class="range-dates">
                    <strong>${startDate.toLocaleDateString()}</strong> to <strong>${endDate.toLocaleDateString()}</strong>
                </div>
                <div class="range-stats">
                    <div class="stat">
                        <span class="stat-value">${totalDays}</span>
                        <span class="stat-label">Days</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">${uniqueEmployees.size}</span>
                        <span class="stat-label">Employees</span>
                    </div>
                </div>
            </div>
    `;

    // Group leaves by date
    dateRange.forEach(dateStr => {
        const dayLeaves = leaveData[dateStr] || [];
        if (dayLeaves.length > 0) {
            const date = new Date(dateStr);
            modalHTML += `
                <div class="range-day">
                    <div class="day-header">
                        <strong>${date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })}</strong>
                        <span class="day-count">${dayLeaves.length} on leave</span>
                    </div>
                    <div class="day-leaves">
            `;

            dayLeaves.forEach(leave => {
                modalHTML += `
                    <div class="leave-item">
                        <div class="employee-avatar">${leave.employee_name.split(' ').map(n => n[0]).join('')}</div>
                        <div class="employee-info">
                            <div class="employee-name">${leave.employee_name}</div>
                            <div class="employee-role">${leave.user_role}</div>
                        </div>
                        <div class="leave-type">${leave.leave_type}</div>
                    </div>
                `;
            });

            modalHTML += `
                    </div>
                </div>
            `;
        }
    });

    modalHTML += `
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeRangeModal()">Close</button>
                <button class="btn btn-primary" onclick="exportRangeSchedule()">Export Schedule</button>
            </div>
        </div>
    `;

    modalContent.innerHTML = modalHTML;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function createRangeModal() {
    const modal = document.createElement('div');
    modal.id = 'range-details-modal';
    modal.className = 'date-modal range-modal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeRangeModal()"></div>
        <div class="modal-content"></div>
    `;
    document.body.appendChild(modal);
    return modal;
}

function closeRangeModal() {
    const modal = document.getElementById('range-details-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Tooltip functionality
let tooltipTimeout;

function showTooltip(event, element) {
    // Clear any existing timeout
    clearTimeout(tooltipTimeout);

    // Get tooltip data from the element's title attribute
    const title = element.getAttribute('title');
    if (!title) return;

    tooltipTimeout = setTimeout(() => {
        const tooltip = document.getElementById('calendar-tooltip') || createTooltip();

        // Parse the title to get employee info
        const tooltipContent = parseTooltipContent(title);
        tooltip.innerHTML = tooltipContent;

        // Position tooltip near the mouse cursor
        const rect = element.getBoundingClientRect();
        const tooltipX = event.clientX + 10;
        const tooltipY = event.clientY - 10;

        tooltip.style.left = tooltipX + 'px';
        tooltip.style.top = tooltipY + 'px';
        tooltip.style.display = 'block';

        // Ensure tooltip stays within viewport
        const tooltipRect = tooltip.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        if (tooltipX + tooltipRect.width > viewportWidth) {
            tooltip.style.left = (event.clientX - tooltipRect.width - 10) + 'px';
        }

        if (tooltipY + tooltipRect.height > viewportHeight) {
            tooltip.style.top = (event.clientY - tooltipRect.height - 10) + 'px';
        }
    }, 500); // 500ms delay
}

function hideTooltip() {
    clearTimeout(tooltipTimeout);
    const tooltip = document.getElementById('calendar-tooltip');
    if (tooltip) {
        tooltip.style.display = 'none';
    }
}

function createTooltip() {
    const tooltip = document.createElement('div');
    tooltip.id = 'calendar-tooltip';
    tooltip.className = 'calendar-tooltip';
    document.body.appendChild(tooltip);
    return tooltip;
}

function parseTooltipContent(title) {
    // Parse the title attribute to create rich tooltip content
    // Format: "Employee Name (Role) - Leave Type"
    const parts = title.split(' - ');
    if (parts.length < 2) return title;

    const employeeInfo = parts[0];
    const leaveType = parts[1];

    const nameMatch = employeeInfo.match(/(.+?)\s*\((.+?)\)/);
    if (nameMatch) {
        const [, name, role] = nameMatch;
        return `
            <div class="tooltip-header">
                <div class="tooltip-avatar">${name.split(' ').map(n => n[0]).join('')}</div>
                <div class="tooltip-info">
                    <div class="tooltip-name">${name}</div>
                    <div class="tooltip-role">${role}</div>
                </div>
            </div>
            <div class="tooltip-leave-type">${leaveType}</div>
        `;
    }

    return title;
}
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

.nav-buttons button {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.nav-buttons button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.nav-buttons button:active:not(:disabled) {
    transform: translateY(0);
}

.nav-buttons button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.nav-buttons button i {
    font-size: 0.9rem;
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
    color: black;
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

.quick-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.quick-action-btn {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.quick-action-btn:active {
    transform: translateY(0);
}

.quick-action-btn i {
    margin-right: 8px;
    font-size: 1rem;
}

.quick-action-btn span {
    font-weight: 500;
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

/* Date Selection and Modal Styles */
.calendar-day.selected {
    background-color: var(--primary-color) !important;
    color: white !important;
    border: 2px solid var(--secondary-color) !important;
    box-shadow: 0 0 10px rgba(77, 122, 93, 0.3);
}

.calendar-day.selected .day-number {
    color: white !important;
    font-weight: 700;
}

.calendar-day.selected .leave-event {
    background-color: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
}

.date-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    display: none;
    animation: fadeIn 0.3s ease;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: white;
    border-radius: var(--border-radius);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
    animation: slideIn 0.3s ease;
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--gray-200);
    background-color: var(--gray-100);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.modal-header h3 {
    margin: 0;
    color: var(--gray-800);
    font-size: 1.2rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--gray-600);
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all var(--transition-speed);
}

.modal-close:hover {
    background-color: var(--gray-200);
    color: var(--gray-800);
}

.modal-body {
    padding: 24px;
}

.leave-summary {
    background-color: var(--primary-color);
    color: white;
    padding: 16px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    text-align: center;
}

.leave-summary h4 {
    margin: 0 0 8px 0;
    font-size: 1rem;
}

.leave-summary p {
    margin: 0;
    font-size: 0.9rem;
    opacity: 0.9;
}

.leave-details h4 {
    color: var(--gray-800);
    margin-bottom: 16px;
    font-size: 1.1rem;
}

.leave-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.leave-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background-color: var(--gray-100);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--primary-color);
}

.employee-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    margin-right: 12px;
}

.employee-info {
    display: flex;
    flex-direction: column;
}

.employee-name {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.95rem;
}

.employee-role {
    font-size: 0.8rem;
    color: var(--gray-600);
    text-transform: capitalize;
}

.leave-info {
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.leave-type {
    font-weight: 600;
    color: var(--primary-color);
    font-size: 0.9rem;
}

.leave-duration {
    font-size: 0.8rem;
    color: var(--gray-600);
}

.leave-dates {
    font-size: 0.8rem;
    color: var(--gray-600);
}

.no-leave {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-600);
}

.no-leave i {
    font-size: 3rem;
    margin-bottom: 16px;
    color: var(--success-color);
    opacity: 0.5;
}

.no-leave p {
    margin: 8px 0;
    font-size: 1rem;
}

.modal-actions {
    margin-top: 24px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: var(--border-radius-sm);
    cursor: pointer;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all var(--transition-speed);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: var(--secondary-color);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
}

.btn-secondary:hover {
    background-color: var(--gray-300);
    transform: translateY(-1px);
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideIn {
    from {
        transform: translate(-50%, -60%);
        opacity: 0;
    }
    to {
        transform: translate(-50%, -50%);
        opacity: 1;
    }
}

/* Range Selection Styles */
.selection-controls {
    margin-left: 15px;
}

.btn-outline {
    background-color: transparent;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
}

.btn-outline:hover {
    background-color: var(--primary-color);
    color: white;
}

.btn-outline.active {
    background-color: var(--primary-color);
    color: white;
}

.range-instructions {
    margin-top: 10px;
    padding: 8px 12px;
    background-color: rgba(77, 122, 93, 0.1);
    border: 1px solid var(--primary-color);
    border-radius: var(--border-radius-sm);
    display: none;
}

.instructions-content {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    color: var(--primary-color);
}

.btn-clear {
    background: none;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    text-decoration: underline;
    font-size: 0.85rem;
    padding: 2px 4px;
}

.btn-clear:hover {
    color: var(--secondary-color);
}

.range-info {
    margin-top: 10px;
    padding: 10px 12px;
    background-color: var(--primary-color);
    color: white;
    border-radius: var(--border-radius-sm);
}

.range-summary {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.9rem;
}

.btn-small {
    padding: 4px 8px;
    font-size: 0.8rem;
    background-color: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 3px;
    cursor: pointer;
    transition: all var(--transition-speed);
}

.btn-small:hover {
    background-color: rgba(255, 255, 255, 0.3);
}

.calendar-day.range-start {
    background-color: var(--primary-color) !important;
    color: white !important;
    border: 2px solid var(--secondary-color) !important;
    box-shadow: 0 0 8px rgba(77, 122, 93, 0.4);
}

.calendar-day.range-end {
    background-color: var(--secondary-color) !important;
    color: white !important;
    border: 2px solid var(--primary-color) !important;
    box-shadow: 0 0 8px rgba(140, 45, 60, 0.4);
}

.calendar-day.range-highlight {
    background-color: rgba(77, 122, 93, 0.15) !important;
    border: 1px solid var(--primary-color) !important;
}

.range-modal .modal-content {
    max-width: 800px;
}

.range-summary-large {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    text-align: center;
}

.range-dates {
    font-size: 1.1rem;
    margin-bottom: 15px;
}

.range-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
}

.stat {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.range-day {
    margin-bottom: 20px;
    padding: 16px;
    background-color: var(--gray-100);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--accent-color);
}

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
}

.day-count {
    background-color: var(--accent-color);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.day-leaves {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.range-day .leave-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    background-color: white;
    border-radius: var(--border-radius-sm);
}

.range-day .employee-info {
    flex: 1;
}

.range-day .leave-type {
    background-color: var(--gray-200);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-700);
}

/* Tooltip Styles */
.calendar-tooltip {
    position: fixed;
    background-color: white;
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius-sm);
    padding: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1001;
    display: none;
    min-width: 200px;
    max-width: 300px;
    font-size: 0.85rem;
    animation: fadeInTooltip 0.2s ease;
}

.tooltip-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.tooltip-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.8rem;
}

.tooltip-info {
    flex: 1;
}

.tooltip-name {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.9rem;
}

.tooltip-role {
    font-size: 0.8rem;
    color: var(--gray-600);
    text-transform: capitalize;
}

.tooltip-leave-type {
    background-color: var(--primary-color);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

@keyframes fadeInTooltip {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced Mobile Touch Interactions */
@media (hover: none) and (pointer: coarse) {
    .calendar-day {
        min-height: 90px;
        padding: 10px;
        position: relative;
    }

    .calendar-day:active {
        background-color: rgba(77, 122, 93, 0.2);
        transform: scale(0.98);
    }

    .leave-event {
        padding: 4px 6px;
        margin: 2px 0;
        border-radius: 4px;
        font-size: 0.75rem;
        touch-action: manipulation;
    }

    .leave-event:active {
        background-color: var(--primary-color);
        color: white;
        transform: scale(1.05);
    }

    .calendar-tooltip {
        display: none !important; /* Disable tooltips on touch devices */
    }

    .nav-buttons button {
        min-height: 44px;
        min-width: 44px;
        padding: 10px;
    }

    .btn-outline {
        min-height: 44px;
        padding: 10px 16px;
    }
}

/* Touch-friendly scrolling */
@media (max-width: 768px) {
    .modal-content {
        -webkit-overflow-scrolling: touch;
    }

    .calendar-tooltip {
        position: fixed;
        left: 10px !important;
        right: 10px !important;
        top: auto !important;
        bottom: 10px !important;
        transform: none !important;
        max-width: none;
        width: calc(100% - 20px);
    }
}

/* Export Modal Styles */
.export-modal .modal-content {
    max-width: 500px;
}

.export-options {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.export-section {
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    padding: 16px;
}

.export-section h4 {
    margin: 0 0 12px 0;
    color: var(--gray-800);
    font-size: 1rem;
    font-weight: 600;
}

.format-options, .date-range-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.format-option, .range-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: var(--border-radius-sm);
    cursor: pointer;
    transition: background-color var(--transition-speed);
}

.format-option:hover, .range-option:hover {
    background-color: var(--gray-100);
}

.format-option input[type="radio"], .range-option input[type="radio"] {
    margin: 0;
    width: 16px;
    height: 16px;
}

.format-info, .range-info {
    flex: 1;
}

.format-name, .range-name {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.9rem;
}

.format-desc, .range-desc {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-top: 2px;
}

.date-inputs {
    display: flex;
    gap: 12px;
    margin-top: 12px;
}

.input-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.input-group label {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gray-700);
}

.input-group input[type="date"] {
    padding: 8px;
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius-sm);
    font-size: 0.9rem;
}

.detail-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.checkbox-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    border-radius: var(--border-radius-sm);
    cursor: pointer;
    transition: background-color var(--transition-speed);
}

.checkbox-option:hover {
    background-color: var(--gray-100);
}

.checkbox-option input[type="checkbox"] {
    margin: 0;
    width: 16px;
    height: 16px;
}

.checkbox-option span {
    font-size: 0.9rem;
    color: var(--gray-700);
}

/* Enhanced Button Styles for Export */
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: var(--border-radius-sm);
    cursor: pointer;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all var(--transition-speed);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: var(--secondary-color);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
}

.btn-secondary:hover {
    background-color: var(--gray-300);
    transform: translateY(-1px);
}

.btn-outline {
    background-color: transparent;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
}

.btn-outline:hover {
    background-color: var(--primary-color);
    color: white;
}

.btn-outline.active {
    background-color: var(--primary-color);
    color: white;
}

/* Loading state for export */
.btn.loading {
    opacity: 0.7;
    cursor: not-allowed;
    position: relative;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    margin: auto;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Responsive adjustments for export modal */
@media (max-width: 600px) {
    .export-modal .modal-content {
        width: 95%;
        margin: 10px;
        max-height: calc(100vh - 20px);
    }

    .date-inputs {
        flex-direction: column;
        gap: 8px;
    }

    .modal-actions {
        flex-direction: column;
        gap: 8px;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Enhanced Responsive Design */
@media (max-width: 1200px) {
    .modal-content {
        width: 95%;
        max-width: 550px;
    }

    .leave-item {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }

    .leave-info {
        text-align: center;
    }
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

    .modal-content {
        width: 98%;
        max-width: none;
        margin: 20px;
        max-height: calc(100vh - 40px);
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

    .modal-header {
        padding: 16px 20px;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .calendar-day {
        min-height: 80px;
        padding: 6px;
    }

    .day-number {
        font-size: 0.9rem;
        margin-bottom: 3px;
    }

    .leave-event {
        font-size: 0.65rem;
        padding: 1px 3px;
    }
}

@media (max-width: 480px) {
    .calendar-day {
        min-height: 70px;
        padding: 4px;
    }

    .day-number {
        font-size: 0.8rem;
    }

    .leave-event {
        font-size: 0.6rem;
        padding: 1px 2px;
    }

    .more-events {
        font-size: 0.6rem;
    }
}
</style>