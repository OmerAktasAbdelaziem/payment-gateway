<?php
/**
 * Configuration File
 * Loads environment variables and defines constants
 */

// Load environment variables from .env file
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception('.env file not found');
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes
            $value = trim($value, '"\'');
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Load .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    loadEnv($envFile);
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'payment_gateway');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');

// Stripe Configuration
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY'));
define('STRIPE_PUBLIC_KEY', getenv('STRIPE_PUBLISHABLE_KEY'));
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET'));

// NOWPayments Configuration
define('NOWPAYMENTS_API_KEY', getenv('NOWPAYMENTS_API_KEY'));
define('NOWPAYMENTS_IPN_SECRET', getenv('NOWPAYMENTS_IPN_SECRET'));

// Exchange/Binance Configuration
define('EXCHANGE_API_KEY', getenv('EXCHANGE_API_KEY'));
define('EXCHANGE_API_SECRET', getenv('EXCHANGE_API_SECRET'));
define('EXCHANGE_TYPE', getenv('EXCHANGE_TYPE') ?: 'binance');

// USDT Configuration
define('USDT_WALLET_ADDRESS', getenv('USDT_WALLET_ADDRESS'));
define('USDT_NETWORK', getenv('USDT_NETWORK') ?: 'TRC20');
define('AUTO_CONVERT_TO_USDT', getenv('AUTO_CONVERT_TO_USDT') === 'true');

// BTC Configuration
define('BTC_WALLET_ADDRESS', getenv('BTC_WALLET_ADDRESS'));
define('PAYOUT_CURRENCY', getenv('PAYOUT_CURRENCY') ?: 'btc');

// Cryptomus Configuration (MAIN PAYMENT METHOD)
define('CRYPTOMUS_MERCHANT_ID', getenv('CRYPTOMUS_MERCHANT_ID'));
define('CRYPTOMUS_PAYMENT_API_KEY', getenv('CRYPTOMUS_PAYMENT_API_KEY'));
define('CRYPTOMUS_PAYOUT_API_KEY', getenv('CRYPTOMUS_PAYOUT_API_KEY'));

// CoinGate Configuration (BACKUP)
define('COINGATE_API_TOKEN', getenv('COINGATE_API_TOKEN'));

// Application Configuration
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost');
define('PAYMENT_SUCCESS_URL', getenv('PAYMENT_SUCCESS_URL') ?: BASE_URL . '/success.html');
define('PAYMENT_ERROR_URL', getenv('PAYMENT_ERROR_URL') ?: BASE_URL . '/error.html');
define('FRONTEND_URL', getenv('FRONTEND_URL') ?: BASE_URL);

// Session Configuration
define('SESSION_NAME', 'gateway_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// Security
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_COST', 12);

// Timezone
date_default_timezone_set('UTC');

// Error Reporting (disable in production)
$isProduction = (getenv('NODE_ENV') === 'production');
if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// CORS Headers
header('Access-Control-Allow-Origin: ' . FRONTEND_URL);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
