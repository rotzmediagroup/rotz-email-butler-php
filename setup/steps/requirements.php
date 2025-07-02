<?php
/**
 * Setup Step 1: System Requirements Check
 */

$requirements = check_system_requirements();
$all_passed = true;

foreach ($requirements as $req) {
    if (!$req['status']) {
        $all_passed = false;
        break;
    }
}
?>

<div class="fade-in">
    <h2 class="step-title">System Requirements</h2>
    <p class="step-description">
        Before we begin, let's make sure your server meets all the requirements for ROTZ Email Butler.
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

    <table class="requirements-table">
        <thead>
            <tr>
                <th>Requirement</th>
                <th>Required</th>
                <th>Current</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requirements as $key => $req): ?>
                <tr>
                    <td><?php echo htmlspecialchars($req['name']); ?></td>
                    <td><?php echo htmlspecialchars($req['required']); ?></td>
                    <td><?php echo htmlspecialchars($req['current']); ?></td>
                    <td class="<?php echo $req['status'] ? 'status-pass' : 'status-fail'; ?>">
                        <?php echo $req['status'] ? '✓ Pass' : '✗ Fail'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!$all_passed): ?>
        <div class="alert alert-error">
            <strong>Requirements Not Met:</strong> Please ensure all requirements are satisfied before continuing.
            You may need to contact your hosting provider or system administrator.
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <strong>Great!</strong> All system requirements are met. You can proceed with the installation.
        </div>
    <?php endif; ?>

    <div class="feature-list">
        <h3>What You'll Get:</h3>
        <ul>
            <li>Multi-AI ensemble email processing system</li>
            <li>Support for 20+ email providers (Gmail, Outlook, ProtonMail, etc.)</li>
            <li>8+ AI providers with enable/disable toggles</li>
            <li>Advanced email categorization and priority detection</li>
            <li>Smart follow-up suggestions and automation</li>
            <li>Real-time analytics and performance monitoring</li>
            <li>Secure credential encryption and OAuth support</li>
            <li>WordPress-style admin panel</li>
        </ul>
    </div>

    <form method="post" action="?step=1">
        <div class="btn-group">
            <span></span>
            <?php if ($all_passed): ?>
                <button type="submit" class="btn btn-primary">
                    Continue to Database Setup →
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-secondary" onclick="location.reload()">
                    Recheck Requirements
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

