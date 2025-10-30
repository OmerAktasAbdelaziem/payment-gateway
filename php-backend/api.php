<?php
/**
 * API Router
 * Handles all API requests and routes them to appropriate controllers
 */

// Load configuration and classes
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Payment.php';
require_once __DIR__ . '/StripeService.php';
require_once __DIR__ . '/NOWPaymentsService.php';

// Set JSON response header
header('Content-Type: application/json');

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove /api prefix if present
$path = preg_replace('#^/api#', '', $path);

// Initialize classes
$auth = new Auth();
$payment = new Payment();
$stripe = new StripeService();
$nowpayments = new NOWPaymentsService();

try {
    // Route handling
    switch (true) {
        // Health check
        case $path === '/health' && $method === 'GET':
            echo json_encode([
                'status' => 'ok',
                'message' => 'Payment Gateway API is running',
                'timestamp' => date('c'),
                'environment' => getenv('NODE_ENV') ?: 'development'
            ]);
            break;
        
        // Authentication endpoints
        case $path === '/auth/login' && $method === 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $auth->login($data['username'] ?? '', $data['password'] ?? '');
            echo json_encode($result);
            break;
        
        case $path === '/auth/logout' && $method === 'POST':
            $result = $auth->logout();
            echo json_encode($result);
            break;
        
        case $path === '/auth/check' && $method === 'GET':
            $isAuth = $auth->isAuthenticated();
            echo json_encode([
                'authenticated' => $isAuth,
                'user' => $isAuth ? $auth->getCurrentUser() : null
            ]);
            break;
        
        // Payment endpoints (require authentication)
        case $path === '/payments' && $method === 'GET':
            $auth->requireAuth();
            $filters = [
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit' => $_GET['limit'] ?? null
            ];
            $payments = $payment->getAll($filters);
            echo json_encode(['payments' => $payments]);
            break;
        
        case $path === '/payments' && $method === 'POST':
            $auth->requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $payment->create($data);
            echo json_encode($result);
            break;
        
        case preg_match('#^/payments/([A-Z0-9_]+)$#', $path, $matches) && $method === 'GET':
            $auth->requireAuth();
            $paymentId = $matches[1];
            $paymentData = $payment->getByPaymentId($paymentId);
            echo json_encode(['payment' => $paymentData]);
            break;
        
        case preg_match('#^/payments/([A-Z0-9_]+)$#', $path, $matches) && $method === 'PATCH':
            $auth->requireAuth();
            $paymentId = $matches[1];
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $payment->updateStatus($paymentId, $data['status'] ?? 'pending');
            echo json_encode(['payment' => $result]);
            break;
        
        case preg_match('#^/payments/([A-Z0-9_]+)$#', $path, $matches) && $method === 'DELETE':
            $auth->requireAuth();
            $paymentId = $matches[1];
            $result = $payment->delete($paymentId);
            echo json_encode($result);
            break;
        
        case $path === '/payments/stats' && $method === 'GET':
            $auth->requireAuth();
            $stats = $payment->getStats();
            echo json_encode(['stats' => $stats]);
            break;
        
        // Stripe endpoints
        case preg_match('#^/stripe/create-intent/([A-Z0-9_]+)$#', $path, $matches) && $method === 'POST':
            $paymentId = $matches[1];
            $paymentData = $payment->getByPaymentId($paymentId);
            
            $result = $stripe->createPaymentIntent(
                $paymentId,
                $paymentData['amount'],
                $paymentData['currency'],
                $paymentData['description']
            );
            
            // Update payment with Stripe payment intent ID
            $payment->updateStatus($paymentId, 'processing', $result['payment_intent_id']);
            
            echo json_encode($result);
            break;
        
        case $path === '/stripe/config' && $method === 'GET':
            echo json_encode([
                'publishableKey' => $stripe->getPublicKey()
            ]);
            break;
        
        case $path === '/stripe/webhook' && $method === 'POST':
            $result = $stripe->handleWebhook();
            echo json_encode($result);
            break;
        
        // NOWPayments endpoints
        case preg_match('#^/nowpayments/create-invoice/([A-Z0-9_]+)$#', $path, $matches) && $method === 'POST':
            $paymentId = $matches[1];
            $paymentData = $payment->getByPaymentId($paymentId);
            
            $result = $nowpayments->createPayment(
                $paymentId,
                $paymentData['amount'],
                strtolower($paymentData['currency']),
                $paymentData['description']
            );
            
            if ($result['success']) {
                // Update payment with NOWPayments invoice ID
                $payment->updateStatus($paymentId, 'pending', $result['invoice_id']);
            }
            
            echo json_encode($result);
            break;
        
        case preg_match('#^/nowpayments/status/([A-Z0-9_]+)$#', $path, $matches) && $method === 'GET':
            $invoiceId = $matches[1];
            $result = $nowpayments->getPaymentStatus($invoiceId);
            echo json_encode($result);
            break;
        
        case $path === '/nowpayments/webhook' && $method === 'POST':
            $payload = file_get_contents('php://input');
            $signature = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';
            
            $result = $nowpayments->handleWebhook($payload, $signature);
            echo json_encode($result);
            break;
        
        // Public payment view (no auth required)
        case preg_match('#^/public/payment/([A-Z0-9_]+)$#', $path, $matches) && $method === 'GET':
            $paymentId = $matches[1];
            $paymentData = $payment->getByPaymentId($paymentId);
            
            // Only return necessary public data
            echo json_encode([
                'payment' => [
                    'payment_id' => $paymentData['payment_id'],
                    'amount' => $paymentData['amount'],
                    'currency' => $paymentData['currency'],
                    'description' => $paymentData['description'],
                    'status' => $paymentData['status']
                ]
            ]);
            break;
        
        // 404 - Route not found
        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Not Found',
                'message' => 'API endpoint not found',
                'path' => $path,
                'method' => $method
            ]);
            break;
    }
} catch (Exception $e) {
    // Handle errors
    $statusCode = 500;
    
    // Check for specific error types
    if (strpos($e->getMessage(), 'not found') !== false) {
        $statusCode = 404;
    } elseif (strpos($e->getMessage(), 'required') !== false) {
        $statusCode = 400;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'error' => 'Error',
        'message' => $e->getMessage()
    ]);
}
