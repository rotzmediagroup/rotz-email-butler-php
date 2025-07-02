<?php
/**
 * ROTZ Email Butler - Setup Functions
 */

if (!defined('ROTZ_SETUP')) {
    exit('Direct access not allowed');
}

/**
 * Check system requirements
 */
function check_system_requirements() {
    $requirements = [
        'php_version' => [
            'name' => 'PHP Version (8.0+)',
            'required' => '8.0.0',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '8.0.0', '>=')
        ],
        'pdo' => [
            'name' => 'PDO Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('pdo') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('pdo')
        ],
        'pdo_mysql' => [
            'name' => 'PDO MySQL Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('pdo_mysql')
        ],
        'curl' => [
            'name' => 'cURL Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('curl') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('curl')
        ],
        'openssl' => [
            'name' => 'OpenSSL Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('openssl') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('openssl')
        ],
        'json' => [
            'name' => 'JSON Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('json')
        ],
        'mbstring' => [
            'name' => 'Mbstring Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('mbstring') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('mbstring')
        ],
        'config_writable' => [
            'name' => 'Config Directory Writable',
            'required' => 'Writable',
            'current' => is_writable('../config') ? 'Writable' : 'Not Writable',
            'status' => is_writable('../config')
        ]
    ];

    return $requirements;
}

/**
 * Handle database configuration
 */
function handle_database_config() {
    if (!isset($_POST['db_host'], $_POST['db_name'], $_POST['db_user'], $_POST['db_pass'])) {
        $_SESSION['error'] = 'All database fields are required.';
        return false;
    }

    $config = [
        'host' => trim($_POST['db_host']),
        'name' => trim($_POST['db_name']),
        'user' => trim($_POST['db_user']),
        'pass' => $_POST['db_pass'],
        'port' => !empty($_POST['db_port']) ? (int)$_POST['db_port'] : 3306,
        'charset' => 'utf8mb4'
    ];

    // Test database connection
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Try to create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Test connection to the specific database
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $_SESSION['db_config'] = $config;
        $_SESSION['success'] = 'Database connection successful!';
        return true;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database connection failed: ' . $e->getMessage();
        return false;
    }
}

/**
 * Handle database setup
 */
function handle_database_setup() {
    if (!isset($_SESSION['db_config'])) {
        $_SESSION['error'] = 'Database configuration not found.';
        return false;
    }

    $config = $_SESSION['db_config'];

    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Read and execute SQL schema
        $sql = file_get_contents('database/schema.sql');
        $statements = explode(';', $sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }

        $_SESSION['success'] = 'Database tables created successfully!';
        return true;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database setup failed: ' . $e->getMessage();
        return false;
    }
}

/**
 * Handle admin user creation
 */
function handle_admin_creation() {
    if (!isset($_POST['admin_username'], $_POST['admin_email'], $_POST['admin_password'], $_POST['admin_password_confirm'])) {
        $_SESSION['error'] = 'All admin user fields are required.';
        return false;
    }

    $username = trim($_POST['admin_username']);
    $email = trim($_POST['admin_email']);
    $password = $_POST['admin_password'];
    $password_confirm = $_POST['admin_password_confirm'];

    // Validation
    if (strlen($username) < 3) {
        $_SESSION['error'] = 'Username must be at least 3 characters long.';
        return false;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Please enter a valid email address.';
        return false;
    }

    if (strlen($password) < 8) {
        $_SESSION['error'] = 'Password must be at least 8 characters long.';
        return false;
    }

    if ($password !== $password_confirm) {
        $_SESSION['error'] = 'Passwords do not match.';
        return false;
    }

    // Create admin user
    try {
        $config = $_SESSION['db_config'];
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, status, created_at) 
            VALUES (?, ?, ?, 'admin', 'active', NOW())
        ");
        
        $stmt->execute([$username, $email, $password_hash]);

        $_SESSION['admin_user'] = [
            'username' => $username,
            'email' => $email
        ];

        $_SESSION['success'] = 'Admin user created successfully!';
        return true;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to create admin user: ' . $e->getMessage();
        return false;
    }
}

/**
 * Handle configuration file generation
 */
function handle_config_generation() {
    if (!isset($_SESSION['db_config'], $_SESSION['admin_user'])) {
        $_SESSION['error'] = 'Setup data not found.';
        return false;
    }

    $config = $_SESSION['db_config'];
    
    // Generate random keys
    $app_key = bin2hex(random_bytes(32));
    $jwt_secret = bin2hex(random_bytes(32));
    $encryption_key = bin2hex(random_bytes(32));

    // Create .env file
    $env_content = "# ROTZ Email Butler Configuration
# Generated on " . date('Y-m-d H:i:s') . "

# Application
APP_NAME=\"ROTZ Email Butler\"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
APP_KEY={$app_key}

# Database
DB_HOST={$config['host']}
DB_PORT={$config['port']}
DB_DATABASE={$config['name']}
DB_USERNAME={$config['user']}
DB_PASSWORD={$config['pass']}
DB_CHARSET={$config['charset']}

# Security
JWT_SECRET={$jwt_secret}
ENCRYPTION_KEY={$encryption_key}
SESSION_LIFETIME=120

# Email Settings
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@rotz.ai
MAIL_FROM_NAME=\"ROTZ Email Butler\"

# AI Providers (Add your API keys here)
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GOOGLE_AI_API_KEY=
QWEN_API_KEY=
GROQ_API_KEY=
COHERE_API_KEY=
MISTRAL_API_KEY=
TOGETHER_API_KEY=

# System Settings
REGISTRATION_ENABLED=false
MAX_EMAIL_PROVIDERS_PER_USER=10
MAX_AI_PROVIDERS_PER_USER=20
EMAIL_PROCESSING_BATCH_SIZE=50
AI_REQUEST_TIMEOUT=30
";

    // Create database config file
    $db_config_content = "<?php
/**
 * ROTZ Email Butler - Database Configuration
 * Generated on " . date('Y-m-d H:i:s') . "
 */

return [
    'host' => '{$config['host']}',
    'port' => {$config['port']},
    'database' => '{$config['name']}',
    'username' => '{$config['user']}',
    'password' => '{$config['pass']}',
    'charset' => '{$config['charset']}',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
";

    // Create app config file
    $app_config_content = "<?php
/**
 * ROTZ Email Butler - Application Configuration
 * Generated on " . date('Y-m-d H:i:s') . "
 */

return [
    'name' => 'ROTZ Email Butler',
    'version' => '1.0.0',
    'environment' => 'production',
    'debug' => false,
    'timezone' => 'UTC',
    'app_key' => '{$app_key}',
    'jwt_secret' => '{$jwt_secret}',
    'encryption_key' => '{$encryption_key}',
    'session_lifetime' => 120,
    'registration_enabled' => false,
    'max_email_providers_per_user' => 10,
    'max_ai_providers_per_user' => 20,
    'email_processing_batch_size' => 50,
    'ai_request_timeout' => 30,
];
";

    try {
        // Write configuration files
        file_put_contents('../.env', $env_content);
        file_put_contents('../config/database.php', $db_config_content);
        file_put_contents('../config/app.php', $app_config_content);

        // Set proper permissions
        chmod('../.env', 0600);
        chmod('../config/database.php', 0600);
        chmod('../config/app.php', 0644);

        $_SESSION['success'] = 'Configuration files generated successfully!';
        return true;

    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to create configuration files: ' . $e->getMessage();
        return false;
    }
}

/**
 * Complete installation
 */
function complete_installation() {
    try {
        // Create installation lock file
        file_put_contents('../config/installed.lock', date('Y-m-d H:i:s'));
        
        // Clear session data
        session_destroy();
        
        // Redirect to main application
        header('Location: ../index.php?installed=1');
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to complete installation: ' . $e->getMessage();
        return false;
    }
}

/**
 * Get error message
 */
function get_error() {
    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
        unset($_SESSION['error']);
        return $error;
    }
    return null;
}

/**
 * Get success message
 */
function get_success() {
    if (isset($_SESSION['success'])) {
        $success = $_SESSION['success'];
        unset($_SESSION['success']);
        return $success;
    }
    return null;
}
?>

