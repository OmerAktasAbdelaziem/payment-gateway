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

// Webhook handler: /api/coingate/webhook
// CoinGate sends webhooks as application/x-www-form-urlencoded (POST params)
if (preg_match('#^/api/coingate/webhook$#', $path) && $method === 'POST') {
    try {
        // CoinGate sends data as POST parameters, not JSON
        $data = $_POST;
        
        // Also try raw input if POST is empty
        if (empty($data)) {
            $payload = file_get_contents('php://input');
            parse_str($payload, $data);
        }
        
        // Log webhook receipt
        error_log("CoinGate Webhook received: " . json_encode($data));
        
        // Validate required fields
        if (!isset($data['order_id']) || !isset($data['status'])) {
            error_log("CoinGate Webhook: Missing required fields");
            http_response_code(400);
            echo json_encode(['error' => 'Invalid webhook data']);
            exit;
        }
        
        $paymentId = $data['order_id'];
        $coingateStatus = $data['status'];
        $coingateOrderId = $data['id'] ?? null;
        
        // Map CoinGate status to our payment status
        // CoinGate statuses: new, pending, confirming, paid, invalid, expired, canceled, refunded
        $statusMap = [
            'new' => 'pending',
            'pending' => 'processing',
            'confirming' => 'processing',
            'paid' => 'completed',
            'invalid' => 'failed',
            'expired' => 'failed',
            'canceled' => 'cancelled',
            'refunded' => 'cancelled'
        ];
        
        $newStatus = $statusMap[$coingateStatus] ?? 'pending';
        
        // Update payment in database
        $paymentData = $payment->getByPaymentId($paymentId);
        
        if (!$paymentData) {
            error_log("CoinGate Webhook: Payment not found - " . $paymentId);
            http_response_code(404);
            echo json_encode(['error' => 'Payment not found']);
            exit;
        }
        
        // Update payment status and metadata
        $metadata = [
            'coingate_order_id' => $coingateOrderId,
            'coingate_status' => $coingateStatus,
            'payment_amount' => $data['pay_amount'] ?? null,
            'payment_currency' => $data['pay_currency'] ?? null,
            'receive_amount' => $data['receive_amount'] ?? null,
            'receive_currency' => $data['receive_currency'] ?? null,
            'webhook_received_at' => date('Y-m-d H:i:s')
        ];
        
        $payment->updateStatus($paymentId, $newStatus, $coingateOrderId, $metadata);
        
        error_log("CoinGate Webhook: Payment {$paymentId} updated to {$newStatus} (CoinGate: {$coingateStatus})");
        
        // Return success
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Webhook processed',
            'payment_id' => $paymentId,
            'status' => $newStatus
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("CoinGate Webhook error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);
