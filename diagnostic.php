<?php
// Diagnostic script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Diagnostic Test ===\n\n";

// Test 1: Check if .env exists
$envFile = __DIR__ . '/.env';
echo "1. .env file exists: " . (file_exists($envFile) ? "YES" : "NO") . "\n";

// Test 2: Try to load config
try {
    require_once __DIR__ . '/php-backend/config.php';
    echo "2. config.php loaded: YES\n";
    echo "3. DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
    echo "4. NOWPAYMENTS_API_KEY: " . (defined('NOWPAYMENTS_API_KEY') ? 'DEFINED' : 'NOT DEFINED') . "\n";
} catch (Exception $e) {
    echo "2. config.php ERROR: " . $e->getMessage() . "\n";
    exit;
}

// Test 3: Try to connect to database
try {
    require_once __DIR__ . '/php-backend/Database.php';
    $db = Database::getInstance();
    echo "5. Database connection: YES\n";
} catch (Exception $e) {
    echo "5. Database connection ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Try to load classes
try {
    require_once __DIR__ . '/php-backend/Auth.php';
    require_once __DIR__ . '/php-backend/Payment.php';
    require_once __DIR__ . '/php-backend/StripeService.php';
    require_once __DIR__ . '/php-backend/NOWPaymentsService.php';
    echo "6. All classes loaded: YES\n";
} catch (Exception $e) {
    echo "6. Class loading ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
