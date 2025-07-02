<?php
/**
 * Setup Step 2: Database Configuration
 */

// Get previous values if available
$db_host = $_SESSION['db_config']['host'] ?? 'localhost';
$db_port = $_SESSION['db_config']['port'] ?? 3306;
$db_name = $_SESSION['db_config']['name'] ?? 'rotz_email_butler';
$db_user = $_SESSION['db_config']['user'] ?? '';
?>

<div class="fade-in">
    <h2 class="step-title">Database Configuration</h2>
    <p class="step-description">
        Please provide your MySQL database connection details. If the database doesn't exist, we'll try to create it for you.
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

    <div class="alert alert-info">
        <strong>Database Requirements:</strong>
        <ul style="margin: 10px 0 0 20px;">
            <li>MySQL 8.0+ or MariaDB 10.3+</li>
            <li>Database user with CREATE, ALTER, INSERT, UPDATE, DELETE, SELECT privileges</li>
            <li>At least 100MB of available storage space</li>
        </ul>
    </div>

    <form method="post" action="?step=2" id="databaseForm">
        <div class="form-row">
            <div class="form-group">
                <label for="db_host" class="form-label">Database Host</label>
                <input type="text" id="db_host" name="db_host" class="form-input" 
                       value="<?php echo htmlspecialchars($db_host); ?>" 
                       placeholder="localhost" required>
            </div>
            <div class="form-group">
                <label for="db_port" class="form-label">Port</label>
                <input type="number" id="db_port" name="db_port" class="form-input" 
                       value="<?php echo htmlspecialchars($db_port); ?>" 
                       placeholder="3306" min="1" max="65535">
            </div>
        </div>

        <div class="form-group">
            <label for="db_name" class="form-label">Database Name</label>
            <input type="text" id="db_name" name="db_name" class="form-input" 
                   value="<?php echo htmlspecialchars($db_name); ?>" 
                   placeholder="rotz_email_butler" required>
            <small style="color: #718096; font-size: 14px;">
                If this database doesn't exist, we'll attempt to create it.
            </small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="db_user" class="form-label">Database Username</label>
                <input type="text" id="db_user" name="db_user" class="form-input" 
                       value="<?php echo htmlspecialchars($db_user); ?>" 
                       placeholder="root" required>
            </div>
            <div class="form-group">
                <label for="db_pass" class="form-label">Database Password</label>
                <input type="password" id="db_pass" name="db_pass" class="form-input" 
                       placeholder="Enter password">
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Security Note:</strong> Your database credentials will be encrypted and stored securely. 
            We recommend creating a dedicated database user with limited privileges for this application.
        </div>

        <div class="btn-group">
            <a href="?step=1" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary" id="testConnectionBtn">
                Test Connection & Continue →
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('databaseForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('testConnectionBtn');
    btn.innerHTML = '<span class="loading"></span> Testing Connection...';
    btn.disabled = true;
});
</script>

