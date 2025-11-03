<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'CoinGateService.php';

echo "Testing CoinGate Integration\n";
echo "=============================\n\n";

echo "API Token: " . (defined('COINGATE_API_TOKEN') ? substr(COINGATE_API_TOKEN, 0, 10) . "..." : "NOT SET") . "\n";
echo "Environment: " . (defined('COINGATE_ENV') ? COINGATE_ENV : "NOT SET") . "\n";
echo "BTC Address: " . (defined('BTC_WALLET_ADDRESS') ? BTC_WALLET_ADDRESS : "NOT SET") . "\n\n";

echo "Creating test order...\n";
echo str_repeat('-', 50) . "\n";

$coingate = new CoinGateService();
$result = $coingate->createOrder(
    'TEST' . time(),
    100.00,
    'USD',
    'Test Payment',
    'Test payment for integration testing'
);

echo "\nResult:\n";
echo json_encode($result, JSON_PRETTY_PRINT);
echo "\n\n";

if ($result['success']) {
    echo "✓ SUCCESS! Payment URL: " . $result['payment_url'] . "\n";
} else {
    echo "✗ FAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
}
