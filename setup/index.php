<?php
/**
 * ROTZ Email Butler - Setup Wizard
 * WordPress-style installation wizard
 */

// Prevent direct access
if (!defined('ROTZ_SETUP')) {
    define('ROTZ_SETUP', true);
}

// Check if already installed
if (file_exists('../config/installed.lock')) {
    header('Location: ../index.php');
    exit;
}

// Start session
session_start();

// Include setup functions
require_once 'functions.php';

// Get current step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$max_step = 6;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // System requirements check
            $step = 2;
            break;
        case 2:
            // Database configuration
            if (handle_database_config()) {
                $step = 3;
            }
            break;
        case 3:
            // Database setup
            if (handle_database_setup()) {
                $step = 4;
            }
            break;
        case 4:
            // Admin user creation
            if (handle_admin_creation()) {
                $step = 5;
            }
            break;
        case 5:
            // Configuration generation
            if (handle_config_generation()) {
                $step = 6;
            }
            break;
        case 6:
            // Installation complete
            complete_installation();
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROTZ Email Butler - Setup Wizard</title>
    <link rel="stylesheet" href="assets/setup.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <div class="logo">
                <div class="logo-icon">R</div>
                <h1>ROTZ Email Butler</h1>
            </div>
            <div class="setup-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($step / $max_step) * 100; ?>%"></div>
                </div>
                <span class="progress-text">Step <?php echo $step; ?> of <?php echo $max_step; ?></span>
            </div>
        </div>

        <div class="setup-content">
            <?php
            switch ($step) {
                case 1:
                    include 'steps/requirements.php';
                    break;
                case 2:
                    include 'steps/database.php';
                    break;
                case 3:
                    include 'steps/database_setup.php';
                    break;
                case 4:
                    include 'steps/admin_user.php';
                    break;
                case 5:
                    include 'steps/configuration.php';
                    break;
                case 6:
                    include 'steps/complete.php';
                    break;
            }
            ?>
        </div>

        <div class="setup-footer">
            <p>&copy; 2025 ROTZ Email Butler. All rights reserved.</p>
        </div>
    </div>

    <script src="assets/setup.js"></script>
</body>
</html>

