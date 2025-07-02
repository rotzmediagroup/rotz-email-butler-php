<?php
/**
 * Setup Step 3: Admin User Creation
 */
?>

<div class="fade-in">
    <h2 class="step-title">Create Admin User</h2>
    <p class="step-description">
        Create your administrator account to manage ROTZ Email Butler. This user will have full access to all features and settings.
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

    <form method="post" action="?step=3" id="adminForm">
        <div class="form-row">
            <div class="form-group">
                <label for="admin_username" class="form-label">Username</label>
                <input type="text" id="admin_username" name="admin_username" class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>" 
                       placeholder="admin" required minlength="3" maxlength="50">
                <small style="color: #718096; font-size: 14px;">
                    3-50 characters, letters, numbers, and underscores only
                </small>
            </div>
            <div class="form-group">
                <label for="admin_email" class="form-label">Email Address</label>
                <input type="email" id="admin_email" name="admin_email" class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" 
                       placeholder="admin@example.com" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="admin_password" class="form-label">Password</label>
                <input type="password" id="admin_password" name="admin_password" class="form-input" 
                       placeholder="Enter secure password" required minlength="8">
                <small style="color: #718096; font-size: 14px;">
                    Minimum 8 characters with uppercase, lowercase, number, and special character
                </small>
            </div>
            <div class="form-group">
                <label for="admin_password_confirm" class="form-label">Confirm Password</label>
                <input type="password" id="admin_password_confirm" name="admin_password_confirm" class="form-input" 
                       placeholder="Confirm password" required>
            </div>
        </div>

        <div class="form-group">
            <label for="admin_display_name" class="form-label">Display Name</label>
            <input type="text" id="admin_display_name" name="admin_display_name" class="form-input" 
                   value="<?php echo htmlspecialchars($_POST['admin_display_name'] ?? 'Administrator'); ?>" 
                   placeholder="Administrator" required>
        </div>

        <div class="alert alert-info">
            <strong>Security Features:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>Password will be securely hashed using bcrypt</li>
                <li>Two-factor authentication can be enabled after setup</li>
                <li>Account lockout protection after failed login attempts</li>
                <li>Session management with secure cookies</li>
            </ul>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="enable_registration" value="1" 
                       <?php echo isset($_POST['enable_registration']) ? 'checked' : ''; ?>>
                <span>Allow user registration (can be changed later in admin settings)</span>
            </label>
            <small style="color: #718096; font-size: 14px; margin-left: 30px;">
                If unchecked, only administrators can create new user accounts
            </small>
        </div>

        <div class="btn-group">
            <a href="?step=2" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary" id="createAdminBtn">
                Create Admin User & Continue →
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('adminForm').addEventListener('submit', function(e) {
    const password = document.getElementById('admin_password').value;
    const confirmPassword = document.getElementById('admin_password_confirm').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    // Basic password strength check
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    if (!passwordRegex.test(password)) {
        e.preventDefault();
        alert('Password must contain at least 8 characters with uppercase, lowercase, number, and special character');
        return false;
    }
    
    const btn = document.getElementById('createAdminBtn');
    btn.innerHTML = '<span class="loading"></span> Creating Admin User...';
    btn.disabled = true;
});

// Real-time password validation
document.getElementById('admin_password').addEventListener('input', function() {
    const password = this.value;
    const confirmField = document.getElementById('admin_password_confirm');
    
    // Password strength indicator
    const hasLower = /[a-z]/.test(password);
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /\d/.test(password);
    const hasSpecial = /[@$!%*?&]/.test(password);
    const hasLength = password.length >= 8;
    
    const strength = [hasLower, hasUpper, hasNumber, hasSpecial, hasLength].filter(Boolean).length;
    
    this.style.borderColor = strength >= 4 ? '#38a169' : strength >= 2 ? '#ed8936' : '#e53e3e';
});

document.getElementById('admin_password_confirm').addEventListener('input', function() {
    const password = document.getElementById('admin_password').value;
    const confirmPassword = this.value;
    
    this.style.borderColor = password === confirmPassword ? '#38a169' : '#e53e3e';
});
</script>

