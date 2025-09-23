<div class="page-header">
    <h2><i class="fas fa-question-circle"></i> Help Center</h2>
    <p>Find answers to common questions and get assistance with using the Employee Leave Management System.</p>
</div>

<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-book"></i> Getting Started</h3>
    </div>
    <div class="card-body">
        <div class="help-section">
            <h4>Logging In</h4>
            <p>To access the system:</p>
            <ol class="help-list">
                <li>Navigate to the login page</li>
                <li>Enter your employee ID and password</li>
                <li>Click the "Sign In" button</li>
                <li>You will be redirected to your dashboard based on your role</li>
            </ol>
        </div>

        <div class="help-section">
            <h4>Password Reset</h4>
            <p>If you forget your password:</p>
            <ul class="help-list">
                <li>Click "Forgot Password?" on the login page</li>
                <li>Enter your registered email address</li>
                <li>Check your email for reset instructions</li>
                <li>Follow the link to create a new password</li>
            </ul>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-alt"></i> Leave Management</h3>
    </div>
    <div class="card-body">
        <div class="help-section">
            <h4>Applying for Leave</h4>
            <p>To submit a leave request:</p>
            <ol class="help-list">
                <li>Click "Apply for Leave" from the sidebar or dashboard</li>
                <li>Select the type of leave you need</li>
                <li>Choose your start and end dates</li>
                <li>Provide a reason for your leave request</li>
                <li>Attach any supporting documents if required</li>
                <li>Click "Submit Request"</li>
            </ol>
        </div>

        <div class="help-section">
            <h4>Checking Leave Balance</h4>
            <p>To view your available leave days:</p>
            <ul class="help-list">
                <li>Go to your dashboard</li>
                <li>Check the leave balance widget</li>
                <li>Or visit the "Leave Balance" page from the sidebar</li>
                <li>Your remaining days for each leave type will be displayed</li>
            </ul>
        </div>

        <div class="help-section">
            <h4>Leave Request Status</h4>
            <p>Understanding request statuses:</p>
            <ul class="help-list">
                <li><strong>Pending:</strong> Request submitted and awaiting approval</li>
                <li><strong>Approved:</strong> Request has been approved by your manager</li>
                <li><strong>Rejected:</strong> Request has been declined (reason provided)</li>
                <li><strong>Cancelled:</strong> Request has been withdrawn by you</li>
            </ul>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-users-cog"></i> Manager Functions</h3>
    </div>
    <div class="card-body">
        <div class="help-section">
            <h4>Approving Leave Requests</h4>
            <p>For managers to approve leave requests:</p>
            <ol class="help-list">
                <li>Navigate to "Leave Requests" or "Team Requests"</li>
                <li>Review the pending requests in your queue</li>
                <li>Click on a request to view details</li>
                <li>Review the employee's leave balance and reason</li>
                <li>Click "Approve" or "Reject" with an optional comment</li>
            </ol>
        </div>

        <div class="help-section">
            <h4>Team Calendar View</h4>
            <p>To view your team's leave schedule:</p>
            <ul class="help-list">
                <li>Go to the "Calendar" section</li>
                <li>Select "Team Calendar" view</li>
                <li>See all approved leave for your team members</li>
                <li>Plan team activities around leave schedules</li>
            </ul>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-cog"></i> System Features</h3>
    </div>
    <div class="card-body">
        <div class="help-section">
            <h4>Profile Management</h4>
            <p>To update your profile information:</p>
            <ul class="help-list">
                <li>Click on your name in the top navigation</li>
                <li>Select "Profile" from the dropdown</li>
                <li>Update your personal information</li>
                <li>Change your password if needed</li>
                <li>Click "Save Changes"</li>
            </ul>
        </div>

        <div class="help-section">
            <h4>Notifications</h4>
            <p>About system notifications:</p>
            <ul class="help-list">
                <li>Check the notification bell in the top navigation</li>
                <li>Notifications include leave approvals, rejections, and reminders</li>
                <li>Mark notifications as read by clicking on them</li>
                <li>View all notifications in the dedicated notifications page</li>
            </ul>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-headset"></i> Getting Additional Help</h3>
    </div>
    <div class="card-body">
        <div class="help-section">
            <p>If you need further assistance:</p>
            <ul class="help-list">
                <li>Check the FAQ page for common questions</li>
                <li>Contact HR through the Contact HR page</li>
                <li>Email the system administrator at <a href="mailto:admin@stfrancishsc.org">admin@stfrancishsc.org</a></li>
                <li>Call the IT helpdesk at extension 1234</li>
            </ul>
        </div>
    </div>
</div>

<style>
.help-section {
    margin-bottom: 25px;
}

.help-section h4 {
    color: var(--primary-color);
    margin-bottom: 15px;
    font-size: 1.1rem;
    font-weight: 600;
}

.help-list {
    list-style: none;
    padding-left: 0;
    margin: 15px 0;
}

.help-list li {
    padding: 8px 0;
    padding-left: 20px;
    position: relative;
    line-height: 1.5;
}

.help-list li:before {
    content: "→";
    color: var(--primary-color);
    font-weight: bold;
    position: absolute;
    left: 0;
}

.help-list ol {
    counter-reset: item;
}

.help-list ol li {
    counter-increment: item;
}

.help-list ol li:before {
    content: counter(item) ".";
    color: var(--primary-color);
    font-weight: bold;
}

@media (max-width: 768px) {
    .help-list li {
        padding-left: 15px;
    }
}
</style>