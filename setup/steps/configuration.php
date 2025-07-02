<?php
/**
 * Setup Step 4: Application Configuration
 */
?>

<div class="fade-in">
    <h2 class="step-title">Application Configuration</h2>
    <p class="step-description">
        Configure your ROTZ Email Butler installation with security settings, email processing options, and system preferences.
    </p>

    <?php if ($error = get_error()): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success = get_success()): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?step=4" id="configForm">
        <div class="form-group">
            <label for="app_name" class="form-label">Application Name</label>
            <input type="text" id="app_name" name="app_name" class="form-input" 
                   value="<?php echo htmlspecialchars($_POST['app_name'] ?? 'ROTZ Email Butler'); ?>" 
                   placeholder="ROTZ Email Butler" required>
            <small style="color: #718096; font-size: 14px;">
                This name will appear in the interface and email notifications
            </small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="app_url" class="form-label">Application URL</label>
                <input type="url" id="app_url" name="app_url" class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['app_url'] ?? 'http://' . $_SERVER['HTTP_HOST']); ?>" 
                       placeholder="https://your-domain.com" required>
            </div>
            <div class="form-group">
                <label for="timezone" class="form-label">Timezone</label>
                <select id="timezone" name="timezone" class="form-select" required>
                    <option value="">Select Timezone</option>
                    <option value="UTC" <?php echo ($_POST['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                    <option value="America/New_York" <?php echo ($_POST['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                    <option value="America/Chicago" <?php echo ($_POST['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                    <option value="America/Denver" <?php echo ($_POST['timezone'] ?? '') === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                    <option value="America/Los_Angeles" <?php echo ($_POST['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                    <option value="Europe/London" <?php echo ($_POST['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>London</option>
                    <option value="Europe/Paris" <?php echo ($_POST['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                    <option value="Europe/Berlin" <?php echo ($_POST['timezone'] ?? '') === 'Europe/Berlin' ? 'selected' : ''; ?>>Berlin</option>
                    <option value="Asia/Tokyo" <?php echo ($_POST['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo</option>
                    <option value="Asia/Shanghai" <?php echo ($_POST['timezone'] ?? '') === 'Asia/Shanghai' ? 'selected' : ''; ?>>Shanghai</option>
                    <option value="Australia/Sydney" <?php echo ($_POST['timezone'] ?? '') === 'Australia/Sydney' ? 'selected' : ''; ?>>Sydney</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Email Processing Settings</label>
            <div style="margin-top: 10px;">
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" name="auto_process_emails" value="1" 
                           <?php echo isset($_POST['auto_process_emails']) ? 'checked' : 'checked'; ?>>
                    <span>Automatically process new emails with AI analysis</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" name="enable_smart_categorization" value="1" 
                           <?php echo isset($_POST['enable_smart_categorization']) ? 'checked' : 'checked'; ?>>
                    <span>Enable smart email categorization</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" name="enable_priority_detection" value="1" 
                           <?php echo isset($_POST['enable_priority_detection']) ? 'checked' : 'checked'; ?>>
                    <span>Enable priority detection for important emails</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="enable_follow_up_suggestions" value="1" 
                           <?php echo isset($_POST['enable_follow_up_suggestions']) ? 'checked' : 'checked'; ?>>
                    <span>Enable follow-up suggestions and reminders</span>
                </label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="sync_interval" class="form-label">Email Sync Interval (minutes)</label>
                <select id="sync_interval" name="sync_interval" class="form-select">
                    <option value="5" <?php echo ($_POST['sync_interval'] ?? '15') === '5' ? 'selected' : ''; ?>>5 minutes</option>
                    <option value="15" <?php echo ($_POST['sync_interval'] ?? '15') === '15' ? 'selected' : ''; ?>>15 minutes</option>
                    <option value="30" <?php echo ($_POST['sync_interval'] ?? '15') === '30' ? 'selected' : ''; ?>>30 minutes</option>
                    <option value="60" <?php echo ($_POST['sync_interval'] ?? '15') === '60' ? 'selected' : ''; ?>>1 hour</option>
                </select>
            </div>
            <div class="form-group">
                <label for="max_emails_per_sync" class="form-label">Max Emails Per Sync</label>
                <select id="max_emails_per_sync" name="max_emails_per_sync" class="form-select">
                    <option value="25" <?php echo ($_POST['max_emails_per_sync'] ?? '50') === '25' ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo ($_POST['max_emails_per_sync'] ?? '50') === '50' ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo ($_POST['max_emails_per_sync'] ?? '50') === '100' ? 'selected' : ''; ?>>100</option>
                    <option value="200" <?php echo ($_POST['max_emails_per_sync'] ?? '50') === '200' ? 'selected' : ''; ?>>200</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Security Settings</label>
            <div style="margin-top: 10px;">
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" name="enable_2fa" value="1" 
                           <?php echo isset($_POST['enable_2fa']) ? 'checked' : ''; ?>>
                    <span>Enable two-factor authentication for admin accounts</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" name="enable_login_attempts_limit" value="1" 
                           <?php echo isset($_POST['enable_login_attempts_limit']) ? 'checked' : 'checked'; ?>>
                    <span>Enable login attempt limits (5 attempts per 15 minutes)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="enable_activity_logging" value="1" 
                           <?php echo isset($_POST['enable_activity_logging']) ? 'checked' : 'checked'; ?>>
                    <span>Enable detailed activity logging</span>
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">AI Processing Settings</label>
            <div style="margin-top: 10px;">
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" name="enable_multi_ai_consensus" value="1" 
                           <?php echo isset($_POST['enable_multi_ai_consensus']) ? 'checked' : 'checked'; ?>>
                    <span>Enable multi-AI consensus for better accuracy</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" name="enable_cost_optimization" value="1" 
                           <?php echo isset($_POST['enable_cost_optimization']) ? 'checked' : 'checked'; ?>>
                    <span>Enable cost optimization (use cheaper models when possible)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="enable_ai_learning" value="1" 
                           <?php echo isset($_POST['enable_ai_learning']) ? 'checked' : ''; ?>>
                    <span>Enable AI learning from user feedback (improves accuracy over time)</span>
                </label>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Configuration Notes:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>All settings can be modified later in the admin panel</li>
                <li>Email processing requires at least one AI provider to be configured</li>
                <li>Sync intervals affect server load and API costs</li>
                <li>Security settings are recommended for production environments</li>
            </ul>
        </div>

        <div class="btn-group">
            <a href="?step=3" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary" id="saveConfigBtn">
                Save Configuration & Continue →
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('configForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('saveConfigBtn');
    btn.innerHTML = '<span class="loading"></span> Saving Configuration...';
    btn.disabled = true;
});
</script>

