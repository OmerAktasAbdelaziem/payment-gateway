<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Payment.php';
require_once __DIR__ . '/CoinGateService.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$payment = new Payment();
$coingate = new CoinGateService();

// Example endpoint: /api/coingate/create-order/{payment_id}
if (preg_match('#^/api/coingate/create-order/([A-Z0-9_]+)$#', $path, $matches) && $method === 'POST') {
    $paymentId = $matches[1];
    $paymentData = $payment->getByPaymentId($paymentId);
    $callbackUrl = BASE_URL . '/api/coingate/webhook';
    $successUrl = PAYMENT_SUCCESS_URL;
    $cancelUrl = PAYMENT_ERROR_URL;
    $receiveCurrency = defined('PAYOUT_CURRENCY') ? PAYOUT_CURRENCY : 'BTC';
    $result = $coingate->createOrder(
        $paymentId,
        $paymentData['amount'],
        $paymentData['currency'],
        $callbackUrl,
        $successUrl,
        $cancelUrl,
        $receiveCurrency
    );
    echo json_encode($result);
    exit;
}

// TODO: Add webhook handler for /coingate/webhook

http_response_code(404);
echo json_encode(['error' => 'Not found']);
