<?php
/**
 * Stripe API Endpoint Handler
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/StripeService.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

error_log("Stripe endpoint - Method: {$requestMethod}, URI: {$requestUri}");

$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

error_log("Stripe endpoint - Path parts: " . print_r($pathParts, true));

$stripeService = new StripeService();

// Route: /api/stripe/create-payment-intent/{payment_id}
$isCreatePaymentIntent = false;
$isCreateCheckout = false;
$paymentLinkIdFromUrl = null;

// Check routes
if ($requestMethod === 'POST') {
    foreach ($pathParts as $i => $part) {
        if ($part === 'create-payment-intent' && isset($pathParts[$i + 1])) {
            $isCreatePaymentIntent = true;
            $paymentLinkIdFromUrl = $pathParts[$i + 1];
            break;
        }
        if ($part === 'create-checkout-session' && isset($pathParts[$i + 1])) {
            $isCreateCheckout = true;
            $paymentLinkIdFromUrl = $pathParts[$i + 1];
            break;
        }
    }
}

// Route: /api/stripe/create-payment-intent/{payment_id}
if ($isCreatePaymentIntent) {
    $paymentLinkId = $paymentLinkIdFromUrl;
    
    error_log("Creating Stripe payment intent for payment link: {$paymentLinkId}");
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        // Get payment link details (allow active or processing status)
        $stmt = $conn->prepare("SELECT id, amount, currency, description, client_name, status FROM payment_links WHERE id = ? AND status IN ('active', 'processing') LIMIT 1");
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
        
        // Create Stripe Payment Intent
        $paymentIntentResult = $stripeService->createPaymentIntent(
            $paymentLink['id'],
            $paymentLink['amount'],
            $paymentLink['currency'],
            $paymentLink['description'] ?: 'Payment for ' . $paymentLink['client_name']
        );
        
        if (!$paymentIntentResult['success']) {
            echo json_encode([
                'success' => false,
                'error' => $paymentIntentResult['error']
            ]);
            $conn->close();
            exit;
        }
        
        // Store payment in database
        $stmt = $conn->prepare("INSERT INTO payments (payment_link_id, external_id, amount, currency, status, provider, created_at) VALUES (?, ?, ?, ?, 'pending', 'stripe', NOW())");
        $stmt->bind_param(
            "ssds",
            $paymentLink['id'],
            $paymentIntentResult['payment_intent_id'],
            $paymentLink['amount'],
            $paymentLink['currency']
        );
        $stmt->execute();
        $paymentId = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        
        error_log("Stripe payment intent created - Payment ID: {$paymentId}, Intent ID: {$paymentIntentResult['payment_intent_id']}");
        
        echo json_encode([
            'success' => true,
            'clientSecret' => $paymentIntentResult['client_secret'],
            'payment_intent_id' => $paymentIntentResult['payment_intent_id'],
            'payment_id' => $paymentId
        ]);
        
    } catch (Exception $e) {
        error_log("Stripe payment intent error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

if ($isCreateCheckout) {
    $paymentLinkId = $paymentLinkIdFromUrl;
    
    error_log("Creating Stripe checkout session for payment link: {$paymentLinkId}");
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        // Get payment link details (allow active or processing status)
        $stmt = $conn->prepare("SELECT id, amount, currency, description, status FROM payment_links WHERE id = ? AND status IN ('active', 'processing') LIMIT 1");
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
        
        // Create Stripe checkout session
        $sessionResult = $stripeService->createCheckoutSession(
            $paymentLink['id'],
            $paymentLink['amount'],
            $paymentLink['currency'],
            $paymentLink['description'] ?: 'Payment'
        );
        
        if (!$sessionResult['success']) {
            echo json_encode([
                'success' => false,
                'error' => $sessionResult['error']
            ]);
            $conn->close();
            exit;
        }
        
        // Store payment in database
        $stmt = $conn->prepare("INSERT INTO payments (payment_link_id, external_id, amount, currency, status, provider, created_at) VALUES (?, ?, ?, ?, 'pending', 'stripe', NOW())");
        $stmt->bind_param(
            "ssds",
            $paymentLink['id'],
            $sessionResult['sessionId'],
            $paymentLink['amount'],
            $paymentLink['currency']
        );
        $stmt->execute();
        $paymentId = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        
        error_log("Stripe checkout session created - Payment ID: {$paymentId}, Session ID: {$sessionResult['sessionId']}");
        
        echo json_encode([
            'success' => true,
            'sessionId' => $sessionResult['sessionId'],
            'url' => $sessionResult['url'],
            'payment_id' => $paymentId
        ]);
        
    } catch (Exception $e) {
        error_log("Stripe checkout error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Route: /api/stripe/webhook
// Check if this is webhook route
$isWebhook = false;
if ($requestMethod === 'POST') {
    foreach ($pathParts as $part) {
        if ($part === 'webhook') {
            $isWebhook = true;
            break;
        }
    }
}

if ($isWebhook) {
    error_log("Stripe webhook received");
    
    try {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        $event = $stripeService->verifyWebhook($payload, $signature);
        
        if (!$event) {
            error_log("Invalid webhook signature!");
            http_response_code(400);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
        
        error_log("Webhook event type: " . $event->type);
        
        // Handle payment_intent.succeeded
        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $paymentIntentId = $paymentIntent->id;
            $paymentLinkId = $paymentIntent->metadata->payment_id ?? null;
            
            error_log("Payment intent succeeded - Intent: {$paymentIntentId}");
            
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                throw new Exception("Database connection failed");
            }
            
            // Update payment link status to completed
            if ($paymentLinkId) {
                $completedStatus = 'completed';
                $stmt = $conn->prepare("UPDATE payment_links SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ss", $completedStatus, $paymentLinkId);
                $stmt->execute();
                $stmt->close();
                
                error_log("Payment link status updated to completed: {$paymentLinkId}");
            }
            
            // Update payment status
            $stmt = $conn->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE external_id = ?");
            $stmt->bind_param("s", $paymentIntentId);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            
            error_log("Payment status updated - Intent: {$paymentIntentId}, Status: completed");
        }
        
        // Handle checkout.session.completed
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $sessionId = $session->id;
            $paymentStatus = $session->payment_status;
            $paymentLinkId = $session->metadata->payment_link_id ?? null;
            
            error_log("Checkout completed - Session: {$sessionId}, Payment Status: {$paymentStatus}");
            
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                throw new Exception("Database connection failed");
            }
            
            // Update payment link status to completed
            if ($paymentLinkId && $paymentStatus === 'paid') {
                $completedStatus = 'completed';
                $stmt = $conn->prepare("UPDATE payment_links SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ss", $completedStatus, $paymentLinkId);
                $stmt->execute();
                $stmt->close();
                
                error_log("Payment link status updated to completed: {$paymentLinkId}");
            }
            
            // Update payment status
            $status = ($paymentStatus === 'paid') ? 'completed' : 'processing';
            $stmt = $conn->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE external_id = ?");
            $stmt->bind_param("ss", $status, $sessionId);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            
            error_log("Payment status updated - Session: {$sessionId}, Status: {$status}");
        }
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        error_log("Webhook processing error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

// Invalid route
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => 'Invalid API route'
]);
