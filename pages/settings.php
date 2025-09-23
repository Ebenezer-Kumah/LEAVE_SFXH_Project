<?php
// pages/settings.php

// Include database connection (auth check moved to dashboard.php)
require_once __DIR__ . '/../includes/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle settings update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // This would typically update settings in a settings table
        // For now, we'll just show a success message
        $message = 'Settings updated successfully!';
        
    } catch (PDOException $e) {
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}

// Get current settings (mock data for demonstration)
$settings = [
    'system_name' => 'Employee Leave Management System',
    'organization_name' => 'St. Francis Xavier Hospital',
    'email_notifications' => true,
    'sms_notifications' => false,
    'auto_approval' => false,
    'max_leave_days' => 30,
    'carry_over_enabled' => true,
    'carry_over_limit' => 7,
    'fiscal_year_start' => 'January',
    'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
    'theme' => 'light'
];
?>

<div class="page-header">
    <h2>System Settings</h2>
    <p>Configure system-wide settings and preferences.</p>
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

<form method="POST" action="">
    <div class="content-row">
        <div class="content-col">
            <div class="content-card">
                <div class="card-header">
                    <h3>General Settings</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="system_name">System Name</label>
                        <input type="text" id="system_name" name="system_name" value="<?php echo $settings['system_name']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="organization_name">Organization Name</label>
                        <input type="text" id="organization_name" name="organization_name" value="<?php echo $settings['organization_name']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="fiscal_year_start">Fiscal Year Start</label>
                        <select id="fiscal_year_start" name="fiscal_year_start">
                            <option value="January" <?php echo $settings['fiscal_year_start'] == 'January' ? 'selected' : ''; ?>>January</option>
                            <option value="April" <?php echo $settings['fiscal_year_start'] == 'April' ? 'selected' : ''; ?>>April</option>
                            <option value="July" <?php echo $settings['fiscal_year_start'] == 'July' ? 'selected' : ''; ?>>July</option>
                            <option value="October" <?php echo $settings['fiscal_year_start'] == 'October' ? 'selected' : ''; ?>>October</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="theme">Theme</label>
                        <select id="theme" name="theme">
                            <option value="light" <?php echo $settings['theme'] == 'light' ? 'selected' : ''; ?>>Light</option>
                            <option value="dark" <?php echo $settings['theme'] == 'dark' ? 'selected' : ''; ?>>Dark</option>
                            <option value="auto" <?php echo $settings['theme'] == 'auto' ? 'selected' : ''; ?>>Auto (System)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3>Notification Settings</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="email_notifications" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Enable Email Notifications
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="sms_notifications" <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Enable SMS Notifications
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="auto_approval" <?php echo $settings['auto_approval'] ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Enable Auto-Approval for Managers
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-col">
            <div class="content-card">
                <div class="card-header">
                    <h3>Leave Settings</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="max_leave_days">Maximum Leave Days per Request</label>
                        <input type="number" id="max_leave_days" name="max_leave_days" value="<?php echo $settings['max_leave_days']; ?>" min="1" max="365">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="carry_over_enabled" <?php echo $settings['carry_over_enabled'] ? 'checked' : ''; ?> onchange="toggleCarryOver()">
                            <span class="checkmark"></span>
                            Enable Leave Carry Over
                        </label>
                    </div>
                    
                    <div class="form-group" id="carry_over_limit_group">
                        <label for="carry_over_limit">Maximum Carry Over Days</label>
                        <input type="number" id="carry_over_limit" name="carry_over_limit" value="<?php echo $settings['carry_over_limit']; ?>" min="0" max="365">
                    </div>
                    
                    <div class="form-group">
                        <label>Working Days</label>
                        <div class="checkbox-group">
                            <?php $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']; ?>
                            <?php foreach ($days as $day): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="working_days[]" value="<?php echo $day; ?>" 
                                        <?php echo in_array($day, $settings['working_days']) ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    <?php echo $day; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3>System Information</h3>
                </div>
                <div class="card-body">
                    <div class="system-info">
                        <div class="info-item">
                            <span class="info-label">PHP Version:</span>
                            <span class="info-value"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Database:</span>
                            <span class="info-value">MySQL</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Server Time:</span>
                            <span class="info-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">System Status:</span>
                            <span class="info-value status-active">Operational</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <button type="button" class="btn btn-secondary" onclick="resetToDefault()">Reset to Default</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function toggleCarryOver() {
    const carryOverEnabled = document.querySelector('input[name="carry_over_enabled"]').checked;
    document.getElementById('carry_over_limit_group').style.display = carryOverEnabled ? 'block' : 'none';
}

function resetToDefault() {
    if (confirm('Are you sure you want to reset all settings to default values?')) {
        document.getElementById('system_name').value = 'Employee Leave Management System';
        document.getElementById('organization_name').value = 'St. Francis Xavier Hospital';
        document.getElementById('fiscal_year_start').value = 'January';
        document.getElementById('theme').value = 'light';
        document.querySelector('input[name="email_notifications"]').checked = true;
        document.querySelector('input[name="sms_notifications"]').checked = false;
        document.querySelector('input[name="auto_approval"]').checked = false;
        document.getElementById('max_leave_days').value = 30;
        document.querySelector('input[name="carry_over_enabled"]').checked = true;
        document.getElementById('carry_over_limit').value = 7;
        
        // Reset working days
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        days.forEach(day => {
            const checkbox = document.querySelector(`input[value="${day}"]`);
            checkbox.checked = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'].includes(day);
        });
        
        toggleCarryOver();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleCarryOver();
});
</script>

<style>
.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.system-info {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
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

.info-label {
    font-weight: 600;
    color: var(--dark-color);
}

.info-value {
    color: #666;
}

.form-actions {
    margin-top: 20px;
    text-align: center;
}

@media (max-width: 768px) {
    .checkbox-group {
        grid-template-columns: 1fr 1fr;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>