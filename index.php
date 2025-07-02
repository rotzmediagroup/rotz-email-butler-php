<?php
/**
 * ROTZ Email Butler - Main Application Entry Point
 * 
 * This file serves as the main entry point for the ROTZ Email Butler application.
 * It handles routing, authentication, and serves the appropriate interface.
 */

// Start session
session_start();

// Check if setup is needed
if (!file_exists(__DIR__ . '/config/app.php')) {
    header('Location: setup/');
    exit;
}

// Load configuration
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/MultiAIEnsemble.php';
require_once __DIR__ . '/classes/EmailProvider.php';

use Rotz\EmailButler\Classes\Database;

// Initialize database
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user = null;

if ($isLoggedIn) {
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    if (!$user) {
        session_destroy();
        $isLoggedIn = false;
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $user = $db->fetchOne("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            // Update last login
            $db->update('users', 
                ['last_login' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$user['id']]
            );
            
            // Log activity
            $db->logActivity($user['id'], 'login');
            
            header('Location: index.php');
            exit;
        } else {
            $loginError = 'Invalid username or password';
        }
    } else {
        $loginError = 'Please enter both username and password';
    }
}

// Check


// Check registration settings
$registrationEnabled = $db->getSetting('allow_registration', false);

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    if (!$registrationEnabled) {
        $registerError = 'Registration is currently disabled';
    } else {
        $username = trim($_POST['reg_username'] ?? '');
        $email = trim($_POST['reg_email'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $displayName = trim($_POST['reg_display_name'] ?? '');
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($displayName)) {
            $registerError = 'All fields are required';
        } elseif (strlen($password) < 8) {
            $registerError = 'Password must be at least 8 characters';
        } else {
            // Check if user exists
            $existingUser = $db->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
            
            if ($existingUser) {
                $registerError = 'Username or email already exists';
            } else {
                // Create user
                $userData = [
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'display_name' => $displayName,
                    'role' => 'user',
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $userId = $db->insert('users', $userData);
                
                if ($userId) {
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_role'] = 'user';
                    
                    // Log activity
                    $db->logActivity($userId, 'register');
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $registerError = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isLoggedIn ? 'Dashboard' : 'Login'; ?> - ROTZ Email Butler</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
<body>
    <?php if ($isLoggedIn): ?>
        <!-- Main Application Interface -->
        <div id="app">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <div class="logo">R</div>
                    <div class="sidebar-title">ROTZ Email Butler</div>
                </div>
                
                <nav class="sidebar-nav">
                    <a href="#dashboard" class="nav-item active" data-view="dashboard">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="#emails" class="nav-item" data-view="emails">
                        <span class="nav-icon">📧</span>
                        <span class="nav-text">Emails</span>
                    </a>
                    <a href="#ai-providers" class="nav-item" data-view="ai-providers">
                        <span class="nav-icon">🤖</span>
                        <span class="nav-text">AI Providers</span>
                    </a>
                    <a href="#email-accounts" class="nav-item" data-view="email-accounts">
                        <span class="nav-icon">📮</span>
                        <span class="nav-text">Email Accounts</span>
                    </a>
                    <a href="#analytics" class="nav-item" data-view="analytics">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Analytics</span>
                    </a>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a href="#admin" class="nav-item" data-view="admin">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Admin</span>
                    </a>
                    <?php endif; ?>
                </nav>
                
                <div class="sidebar-footer">
                    <div class="user-info">
                        <div class="user-avatar"><?php echo strtoupper(substr($user['display_name'], 0, 1)); ?></div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($user['display_name']); ?></div>
                            <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
                        </div>
                    </div>
                    <a href="?action=logout" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <div id="dashboard-view" class="view active">
                    <div class="view-header">
                        <h1>Dashboard</h1>
                        <p>Welcome back, <?php echo htmlspecialchars($user['display_name']); ?>!</p>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">📧</div>
                            <div class="stat-content">
                                <div class="stat-number" id="total-emails">0</div>
                                <div class="stat-label">Total Emails</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">🤖</div>
                            <div class="stat-content">
                                <div class="stat-number" id="active-ai-providers">0</div>
                                <div class="stat-label">Active AI Providers</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">📮</div>
                            <div class="stat-content">
                                <div class="stat-number" id="connected-accounts">0</div>
                                <div class="stat-label">Connected Accounts</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">⚡</div>
                            <div class="stat-content">
                                <div class="stat-number" id="processing-accuracy">0%</div>
                                <div class="stat-label">AI Accuracy</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-grid">
                        <div class="dashboard-card">
                            <h3>Recent Email Activity</h3>
                            <div id="recent-emails">
                                <p class="empty-state">No recent emails to display</p>
                            </div>
                        </div>
                        
                        <div class="dashboard-card">
                            <h3>AI Provider Status</h3>
                            <div id="ai-provider-status">
                                <p class="empty-state">No AI providers configured</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="ai-providers-view" class="view">
                    <div class="view-header">
                        <h1>AI Providers</h1>
                        <p>Configure and manage your AI providers with enable/disable toggles</p>
                        <button class="btn btn-primary" onclick="showAddAIProvider()">Add AI Provider</button>
                    </div>
                    
                    <div class="ai-providers-grid" id="ai-providers-list">
                        <!-- AI providers will be loaded here -->
                    </div>
                </div>
                
                <div id="email-accounts-view" class="view">
                    <div class="view-header">
                        <h1>Email Accounts</h1>
                        <p>Connect and manage your email accounts</p>
                        <button class="btn btn-primary" onclick="showAddEmailAccount()">Connect Email Account</button>
                    </div>
                    
                    <div class="email-accounts-grid" id="email-accounts-list">
                        <!-- Email accounts will be loaded here -->
                    </div>
                </div>
                
                <div id="emails-view" class="view">
                    <div class="view-header">
                        <h1>Emails</h1>
                        <p>View and manage your processed emails</p>
                        <div class="view-actions">
                            <button class="btn btn-secondary" onclick="syncAllEmails()">Sync All</button>
                            <button class="btn btn-primary" onclick="processEmails()">Process with AI</button>
                        </div>
                    </div>
                    
                    <div class="emails-container" id="emails-list">
                        <!-- Emails will be loaded here -->
                    </div>
                </div>
                
                <div id="analytics-view" class="view">
                    <div class="view-header">
                        <h1>Analytics</h1>
                        <p>Monitor your email processing performance and AI accuracy</p>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <h3>Processing Performance</h3>
                            <canvas id="performance-chart"></canvas>
                        </div>
                        
                        <div class="analytics-card">
                            <h3>AI Provider Comparison</h3>
                            <canvas id="provider-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <?php if ($user['role'] === 'admin'): ?>
                <div id="admin-view" class="view">
                    <div class="view-header">
                        <h1>Admin Panel</h1>
                        <p>System administration and configuration</p>
                    </div>
                    
                    <div class="admin-grid">
                        <div class="admin-card">
                            <h3>System Settings</h3>
                            <div class="setting-item">
                                <label>
                                    <input type="checkbox" id="allow-registration" onchange="toggleRegistration()">
                                    Allow User Registration
                                </label>
                            </div>
                            <div class="setting-item">
                                <label>
                                    <input type="checkbox" id="auto-process" onchange="toggleAutoProcess()">
                                    Auto-process New Emails
                                </label>
                            </div>
                        </div>
                        
                        <div class="admin-card">
                            <h3>User Management</h3>
                            <div id="user-list">
                                <!-- Users will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Modals -->
        <div id="add-ai-provider-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add AI Provider</h2>
                    <button class="modal-close" onclick="closeModal('add-ai-provider-modal')">&times;</button>
                </div>
                <form id="add-ai-provider-form">
                    <div class="form-group">
                        <label>Provider</label>
                        <select name="provider_name" required>
                            <option value="">Select Provider</option>
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic</option>
                            <option value="google">Google Gemini</option>
                            <option value="qwen">Qwen</option>
                            <option value="groq">Groq</option>
                            <option value="cohere">Cohere</option>
                            <option value="mistral">Mistral</option>
                            <option value="together">Together AI</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <select name="model_name" required>
                            <option value="">Select Model</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="password" name="api_key" required>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_enabled" checked>
                            Enable this provider
                        </label>
                    </div>
                    <div class="form-actions">
                        <button type="button" onclick="closeModal('add-ai-provider-modal')">Cancel</button>
                        <button type="submit">Add Provider</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="add-email-account-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Connect Email Account</h2>
                    <button class="modal-close" onclick="closeModal('add-email-account-modal')">&times;</button>
                </div>
                <form id="add-email-account-form">
                    <div class="form-group">
                        <label>Email Provider</label>
                        <select name="provider_type" required onchange="updateEmailProviderFields()">
                            <option value="">Select Provider</option>
                            <optgroup label="Popular Providers">
                                <option value="gmail">Gmail</option>
                                <option value="outlook">Outlook.com / Hotmail</option>
                                <option value="office365">Office 365 / Exchange Online</option>
                                <option value="yahoo">Yahoo Mail</option>
                                <option value="icloud">iCloud Mail</option>
                            </optgroup>
                            <optgroup label="Privacy-Focused">
                                <option value="protonmail">ProtonMail</option>
                                <option value="tutanota">Tutanota</option>
                            </optgroup>
                            <optgroup label="Business & Enterprise">
                                <option value="fastmail">Fastmail</option>
                                <option value="zoho">Zoho Mail</option>
                            </optgroup>
                            <optgroup label="International & Other">
                                <option value="aol">AOL Mail</option>
                                <option value="yandex">Yandex Mail</option>
                                <option value="gmx">GMX Mail</option>
                                <option value="mail_com">Mail.com</option>
                            </optgroup>
                            <optgroup label="Sending Services">
                                <option value="mailgun">Mailgun (API)</option>
                                <option value="sendgrid">SendGrid (API)</option>
                                <option value="amazon_ses">Amazon SES (API)</option>
                                <option value="postmark">Postmark (API)</option>
                                <option value="mandrill">Mandrill (API)</option>
                                <option value="sparkpost">SparkPost (API)</option>
                            </optgroup>
                            <optgroup label="Custom Configuration">
                                <option value="custom_imap">Custom IMAP</option>
                                <option value="custom_exchange">Custom Exchange</option>
                                <option value="custom_api">Custom API</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div id="email-provider-fields">
                        <!-- Dynamic fields will be inserted here -->
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeModal('add-email-account-modal')">Cancel</button>
                        <button type="submit">Connect Account</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script src="assets/js/app.js"></script>
        
    <?php else: ?>
        <!-- Login/Register Interface -->
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="logo">R</div>
                    <h1>ROTZ Email Butler</h1>
                    <p>AI-Powered Email Management</p>
                </div>
                
                <div class="auth-tabs">
                    <button class="auth-tab active" onclick="showLogin()">Sign In</button>
                    <?php if ($registrationEnabled): ?>
                    <button class="auth-tab" onclick="showRegister()">Sign Up</button>
                    <?php endif; ?>
                </div>
                
                <!-- Login Form -->
                <div id="login-form" class="auth-form active">
                    <?php if (isset($loginError)): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($loginError); ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <input type="text" name="username" placeholder="Username or Email" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">Sign In</button>
                    </form>
                </div>
                
                <!-- Register Form -->
                <?php if ($registrationEnabled): ?>
                <div id="register-form" class="auth-form">
                    <?php if (isset($registerError)): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($registerError); ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <input type="text" name="reg_username" placeholder="Username" required>
                        </div>
                        <div class="form-group">
                            <input type="email" name="reg_email" placeholder="Email Address" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="reg_display_name" placeholder="Display Name" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="reg_password" placeholder="Password" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">Create Account</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function showLogin() {
            document.getElementById('login-form').classList.add('active');
            document.getElementById('register-form').classList.remove('active');
            document.querySelectorAll('.auth-tab')[0].classList.add('active');
            document.querySelectorAll('.auth-tab')[1].classList.remove('active');
        }
        
        function showRegister() {
            document.getElementById('login-form').classList.remove('active');
            document.getElementById('register-form').classList.add('active');
            document.querySelectorAll('.auth-tab')[0].classList.remove('active');
            document.querySelectorAll('.auth-tab')[1].classList.add('active');
        }
        </script>
    <?php endif; ?>
</body>
</html>

