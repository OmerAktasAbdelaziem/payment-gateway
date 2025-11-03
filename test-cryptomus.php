<?php
/**
 * Test Cryptomus Integration
 * Run this file to verify Cryptomus service is working
 * URL: https://internationalitpro.com/test-cryptomus.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/php-backend/config.php';
require_once __DIR__ . '/php-backend/CryptomusService.php';

echo "<h1>Cryptomus Integration Test</h1>";
echo "<hr>";

// Check configuration
echo "<h2>1. Configuration Check</h2>";
echo "Merchant ID: " . (defined('CRYPTOMUS_MERCHANT_ID') ? substr(CRYPTOMUS_MERCHANT_ID, 0, 10) . "..." : "❌ NOT SET") . "<br>";
echo "Payment API Key: " . (defined('CRYPTOMUS_PAYMENT_API_KEY') ? substr(CRYPTOMUS_PAYMENT_API_KEY, 0, 10) . "..." : "❌ NOT SET") . "<br>";
echo "Payout API Key: " . (defined('CRYPTOMUS_PAYOUT_API_KEY') ? substr(CRYPTOMUS_PAYOUT_API_KEY, 0, 10) . "..." : "❌ NOT SET") . "<br>";
echo "Base URL: " . (defined('BASE_URL') ? BASE_URL : "❌ NOT SET") . "<br>";
echo "<br>";

// Check if configuration is complete
if (!defined('CRYPTOMUS_MERCHANT_ID') || empty(CRYPTOMUS_MERCHANT_ID) || CRYPTOMUS_MERCHANT_ID === 'YOUR_MERCHANT_UUID_HERE') {
    echo "<h2>⚠️ SETUP REQUIRED</h2>";
    echo "<p style='color: red; font-weight: bold;'>Please update your .env file with:</p>";
    echo "<pre>";
    echo "CRYPTOMUS_MERCHANT_ID=your-merchant-uuid-here\n";
    echo "CRYPTOMUS_PAYMENT_API_KEY=your-payment-api-key-here\n";
    echo "</pre>";
    echo "<p>Get these from: <a href='https://app.cryptomus.com/settings' target='_blank'>https://app.cryptomus.com/settings</a></p>";
    exit;
}

if (!defined('CRYPTOMUS_PAYMENT_API_KEY') || empty(CRYPTOMUS_PAYMENT_API_KEY) || CRYPTOMUS_PAYMENT_API_KEY === 'YOUR_PAYMENT_API_KEY_HERE') {
    echo "<h2>⚠️ SETUP REQUIRED</h2>";
    echo "<p style='color: red; font-weight: bold;'>Please update CRYPTOMUS_PAYMENT_API_KEY in your .env file</p>";
    exit;
}

// Test service initialization
echo "<h2>2. Service Initialization</h2>";
try {
    $cryptomusService = new CryptomusService();
    echo "✅ CryptomusService initialized successfully<br>";
} catch (Exception $e) {
    echo "❌ Failed to initialize: " . $e->getMessage() . "<br>";
    exit;
}
echo "<br>";

// Test invoice creation
echo "<h2>3. Test Invoice Creation</h2>";
try {
    $testOrderId = 'TEST-' . time();
    $testAmount = 10.00;
    $testCurrency = 'USD';
    $callbackUrl = BASE_URL . '/api/cryptomus/webhook';
    $successUrl = BASE_URL . '/success.html?order=' . $testOrderId;
    $returnUrl = BASE_URL . '/pay.html';
    
    echo "Creating test invoice...<br>";
    echo "Order ID: {$testOrderId}<br>";
    echo "Amount: {$testAmount} {$testCurrency}<br>";
    echo "<br>";
    
    $result = $cryptomusService->createInvoice(
        $testOrderId,
        $testAmount,
        $testCurrency,
        $callbackUrl,
        $successUrl,
        $returnUrl
    );
    
    if ($result['success']) {
        echo "✅ Invoice created successfully!<br>";
        echo "<h3>Invoice Details:</h3>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        echo "<br>";
        echo "<a href='{$result['payment_url']}' target='_blank' style='background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>";
        echo "🔗 Open Payment Page";
        echo "</a>";
    } else {
        echo "❌ Invoice creation failed<br>";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "<br>";
        echo "<h3>Full Response:</h3>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

echo "<br><br>";
echo "<hr>";
echo "<h2>Test Complete</h2>";
echo "<p>If you see a successful invoice creation above, your Cryptomus integration is working correctly!</p>";
echo "<p>Next steps:</p>";
echo "<ol>";
echo "<li>Click the payment link to test the checkout page</li>";
echo "<li>Make a test payment (small amount recommended)</li>";
echo "<li>Check if webhook is received at: " . BASE_URL . "/api/cryptomus/webhook</li>";
echo "<li>Verify payment status is updated in your database</li>";
echo "</ol>";
