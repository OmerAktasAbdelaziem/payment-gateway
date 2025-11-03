<?php
/**
 * Cryptomus API Endpoint Handler
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CryptomusService.php';

// Get the request URI
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

error_log("Cryptomus endpoint - Method: {$requestMethod}, URI: {$requestUri}");

// Parse the route
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Initialize Cryptomus service
$cryptomusService = new CryptomusService();

// Route: /api/cryptomus/create-invoice/{payment_id}
if ($requestMethod === 'POST' && isset($pathParts[2]) && $pathParts[2] === 'create-invoice' && isset($pathParts[3])) {
    $paymentLinkId = $pathParts[3];
    
    error_log("Processing Cryptomus invoice creation for payment link: {$paymentLinkId}");
    
    try {
        // Connect to database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }
        
        // Get payment link details
        $stmt = $conn->prepare("SELECT id, amount, currency, description FROM payment_links WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("s", $paymentLinkId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Payment link not found or inactive'
            ]);
            $stmt->close();
            $conn->close();
            exit;
        }
        
        $paymentLink = $result->fetch_assoc();
        $stmt->close();
        
        // Generate unique order ID
        $orderId = 'INTL-' . time() . '-' . $paymentLink['id'];
        
        // Create invoice with Cryptomus
        $callbackUrl = BASE_URL . '/api/cryptomus/webhook';
        $successUrl = BASE_URL . '/success.html?order=' . urlencode($orderId);
        $returnUrl = BASE_URL . '/pay.html?payment=' . urlencode($paymentLinkId);
        
        $invoiceResult = $cryptomusService->createInvoice(
            $orderId,
            $paymentLink['amount'],
            $paymentLink['currency'],
            $callbackUrl,
            $successUrl,
            $returnUrl
        );
        
        if (!$invoiceResult['success']) {
            echo json_encode([
                'success' => false,
                'error' => $invoiceResult['error']
            ]);
            $conn->close();
            exit;
        }
        
        // Store payment in database
        $stmt = $conn->prepare("INSERT INTO payments (payment_link_id, external_id, amount, currency, status, provider, created_at) VALUES (?, ?, ?, ?, 'pending', 'cryptomus', NOW())");
        $stmt->bind_param(
            "isds",
            $paymentLink['id'],
            $invoiceResult['invoice_id'],
            $paymentLink['amount'],
            $paymentLink['currency']
        );
        $stmt->execute();
        $paymentId = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        
        error_log("Cryptomus invoice created successfully - Payment ID: {$paymentId}, Invoice ID: {$invoiceResult['invoice_id']}");
        
        // Return payment URL to frontend
        echo json_encode([
            'success' => true,
            'payment_url' => $invoiceResult['payment_url'],
            'invoice_id' => $invoiceResult['invoice_id'],
            'order_id' => $invoiceResult['order_id'],
            'payment_id' => $paymentId
        ]);
        
    } catch (Exception $e) {
        error_log("Cryptomus invoice creation error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Route: /api/cryptomus/webhook
if ($requestMethod === 'POST' && isset($pathParts[2]) && $pathParts[2] === 'webhook') {
    error_log("Cryptomus webhook received");
    
    try {
        // Get webhook data
        $input = file_get_contents('php://input');
        $webhookData = json_decode($input, true);
        
        // Get signature from header
        $receivedSignature = $_SERVER['HTTP_SIGN'] ?? '';
        
        error_log("Webhook data: " . substr($input, 0, 500));
        error_log("Received signature: " . $receivedSignature);
        
        // Verify signature
        if (!$cryptomusService->verifyWebhookSignature($webhookData, $receivedSignature)) {
            error_log("Invalid webhook signature!");
            http_response_code(400);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
        
        // Process webhook
        if (isset($webhookData['order_id']) && isset($webhookData['status'])) {
            $orderId = $webhookData['order_id'];
            $status = $webhookData['status'];
            $invoiceId = $webhookData['uuid'] ?? '';
            
            error_log("Processing webhook - Order: {$orderId}, Status: {$status}");
            
            // Connect to database
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                throw new Exception("Database connection failed");
            }
            
            // Map Cryptomus status to our status
            $paymentStatus = 'pending';
            switch ($status) {
                case 'paid':
                case 'paid_over':
                    $paymentStatus = 'completed';
                    break;
                case 'wrong_amount':
                case 'process':
                case 'check':
                case 'confirm_check':
                    $paymentStatus = 'processing';
                    break;
                case 'fail':
                case 'cancel':
                case 'system_fail':
                case 'refund_process':
                case 'refund_fail':
                case 'refund_paid':
                    $paymentStatus = 'failed';
                    break;
            }
            
            // Update payment status
            $stmt = $conn->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE external_id = ?");
            $stmt->bind_param("ss", $paymentStatus, $invoiceId);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            
            error_log("Payment status updated - Invoice: {$invoiceId}, Status: {$paymentStatus}");
            
            echo json_encode(['status' => 'success']);
        } else {
            error_log("Invalid webhook data structure");
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
        }
        
    } catch (Exception $e) {
        error_log("Webhook processing error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

// Invalid route
error_log("Invalid Cryptomus API route");
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => 'Invalid API route'
]);
